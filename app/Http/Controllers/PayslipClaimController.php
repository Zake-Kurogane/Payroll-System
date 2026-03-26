<?php

namespace App\Http\Controllers;

use App\Models\CompanySetup;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayslipClaim;
use App\Models\PayslipClaimProof;
use App\Services\PayslipClaims\ClaimSheetScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayslipClaimController extends Controller
{
    public function page(Request $request)
    {
        $runId = $request->query('run_id');

        $runs = PayrollRun::query()
            ->whereIn('status', ['Released'])
            ->orderByDesc('id')
            ->get();

        $selectedRun = null;
        $employees = collect();
        $summary = null;
        $proofs = collect();

        if ($runId) {
            $selectedRun = $runs->firstWhere('id', (int) $runId) ?? PayrollRun::query()->find($runId);
            if ($selectedRun) {
                $employees = $this->runEmployeesWithClaimStatus($selectedRun);
                $summary = $this->computeRunClaimSummary($selectedRun);
                $proofs = PayslipClaimProof::query()
                    ->where('payroll_run_id', $selectedRun->id)
                    ->orderByDesc('id')
                    ->get();
            }
        }

        return view('layouts.payslip_claims', [
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'employees' => $employees,
            'summary' => $summary,
            'proofs' => $proofs,
        ]);
    }

    public function downloadClaimSheet(PayrollRun $run)
    {
        if ($run->status !== 'Released') {
            return response()->json(['message' => 'Claim sheet is available only for released runs.'], 409);
        }

        $company = CompanySetup::query()->first();
        $rows = $this->runEmployeesForClaimSheet($run);
        $pages = $this->buildClaimSheetPages($rows, 25);

        $html = view('print.payslip_claim_sheet', [
            'company' => $company,
            'run' => $run,
            'pages' => $pages,
        ])->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = 'payslip_claim_sheet_' . ($run->run_code ?: $run->id) . '.pdf';
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function uploadProof(Request $request, PayrollRun $run)
    {
        if ($run->status !== 'Released') {
            return back()->withErrors(['proof' => 'Proof upload is available only for released runs.']);
        }

        $validated = $request->validate([
            'proofs' => ['required', 'array', 'min:1'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $files = $validated['proofs'];
        usort($files, function ($a, $b) {
            return strcmp((string) $a->getClientOriginalName(), (string) $b->getClientOriginalName());
        });

        $storedProofs = [];
        foreach ($files as $file) {
            $path = $file->store('payslip_claim_proofs/' . $run->id, ['disk' => 'local']);
            $proof = PayslipClaimProof::create([
                'payroll_run_id' => $run->id,
                'uploaded_by_user_id' => Auth::id(),
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => (string) $file->getClientMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'storage_path' => $path,
            ]);
            $storedProofs[] = $proof;
        }

        $this->processUploadedProofs($run, $storedProofs);

        return redirect()->route('payslip.claims', ['run_id' => $run->id])
            ->with('success', count($storedProofs) . ' proof file(s) uploaded and processed.');
    }

    public function downloadProof(PayslipClaimProof $proof)
    {
        $disk = Storage::disk('local');
        if (!$disk->exists($proof->storage_path)) {
            abort(404);
        }
        $filename = $proof->original_name ?: ('proof_' . $proof->id);
        return $disk->download($proof->storage_path, $filename);
    }

    private function runEmployeesForClaimSheet(PayrollRun $run)
    {
        return PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->join('employees', 'employees.id', '=', 'payroll_run_rows.employee_id')
            ->orderByRaw("LOWER(COALESCE(NULLIF(TRIM(employees.area_place), ''), 'zzzz'))")
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('employees.emp_no')
            ->get([
                'employees.id as employee_id',
                'employees.emp_no as emp_no',
                'employees.last_name as last_name',
                'employees.first_name as first_name',
                'employees.middle_name as middle_name',
                'employees.assignment_type as assignment_type',
                'employees.area_place as area_place',
            ])
            ->map(function ($r) {
                $name = trim(
                    ($r->last_name ?? '') . ', ' . ($r->first_name ?? '') . (($r->middle_name ?? '') ? (' ' . $r->middle_name) : '')
                );
                return [
                    'employee_id' => (int) $r->employee_id,
                    'emp_no' => (string) ($r->emp_no ?? ''),
                    'name' => $name,
                    'assignment_type' => (string) ($r->assignment_type ?? ''),
                    'area_place' => (string) ($r->area_place ?? ''),
                ];
            });
    }

    private function buildClaimSheetPages($rows, int $rowsPerPage = 25): array
    {
        $items = collect($rows)->map(function ($r) {
            $area = is_array($r) ? ($r['area_place'] ?? '') : ($r->area_place ?? '');
            $area = is_string($area) ? trim($area) : '';
            return array_merge(is_array($r) ? $r : (array) $r, [
                'area_place' => $area,
                'area_label' => $area !== '' ? $area : '-',
            ]);
        });

        $groups = $items->groupBy(fn ($r) => (string) ($r['area_label'] ?? '-'));
        $areas = $groups->keys()->sort()->values();

        $pages = [];
        foreach ($areas as $area) {
            $rowsInArea = $groups->get($area, collect())->values();
            $chunks = array_chunk($rowsInArea->all(), $rowsPerPage);
            $areaPages = max(1, count($chunks));

            $areaSeq = 0;
            foreach ($chunks as $areaPageIndex => $chunk) {
                $pageRows = [];
                foreach ($chunk as $row) {
                    $areaSeq++;
                    $pageRows[] = array_merge($row, [
                        'no' => $areaSeq,
                    ]);
                }

                $pages[] = [
                    'area' => (string) $area,
                    'area_page' => (int) $areaPageIndex + 1,
                    'area_pages' => (int) $areaPages,
                    'rows' => $pageRows,
                ];
            }
        }

        return $pages;
    }

    private function runEmployeesWithClaimStatus(PayrollRun $run)
    {
        $claims = PayslipClaim::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $rows = $this->runEmployeesForClaimSheet($run);

        return $rows->map(function ($r) use ($claims) {
            $claim = $claims->get($r['employee_id']);
            return array_merge($r, [
                'claimed_at' => $claim?->claimed_at,
                'proof_id' => $claim?->payslip_claim_proof_id,
            ]);
        });
    }

    private function computeRunClaimSummary(PayrollRun $run): array
    {
        $total = PayrollRunRow::query()->where('payroll_run_id', $run->id)->count();
        $claimed = PayslipClaim::query()
            ->where('payroll_run_id', $run->id)
            ->whereNotNull('claimed_at')
            ->count();
        return [
            'total' => (int) $total,
            'claimed' => (int) $claimed,
            'unclaimed' => max(0, (int) $total - (int) $claimed),
        ];
    }

    private function processUploadedProofs(PayrollRun $run, array $proofs): void
    {
        $rowsPerPage = 25;
        $employees = $this->runEmployeesForClaimSheet($run)->values();
        $pages = $this->buildClaimSheetPages($employees, $rowsPerPage);
        $scanner = new ClaimSheetScanner();
        $usedPageIndexes = [];

        foreach (array_values($proofs) as $i => $proof) {
            $pageIndex = $this->inferPageIndexFromFilename($proof->original_name);
            if ($pageIndex === null || !isset($pages[$pageIndex]) || isset($usedPageIndexes[$pageIndex])) {
                $pageIndex = $i;
            }
            // If the fallback index is still invalid (e.g., sparse uploads), map to the next available page.
            if (!isset($pages[$pageIndex]) || isset($usedPageIndexes[$pageIndex])) {
                $candidate = 0;
                while (isset($pages[$candidate]) && isset($usedPageIndexes[$candidate])) {
                    $candidate++;
                }
                $pageIndex = isset($pages[$candidate]) ? $candidate : $pageIndex;
            }
            $usedPageIndexes[$pageIndex] = true;

            $page = $pages[$pageIndex] ?? null;
            $slice = $page ? (array) ($page['rows'] ?? []) : [];
            $expectedRows = count($slice);

            if ($expectedRows === 0) {
                $proof->processed_at = now();
                $proof->processed_summary = [
                    'error' => 'No matching employees for this page index.',
                ];
                $proof->save();
                continue;
            }

            $claimedNew = 0;
            $claimedDetected = 0;
            $rowsScanned = 0;
            $error = null;

            try {
                $imgPath = Storage::disk('local')->path($proof->storage_path);
                $scan = $scanner->scanImageFile($imgPath, $expectedRows);
                $rowsScanned = min($expectedRows, count($scan));

                $empIds = array_values(array_filter(array_map(function ($r) {
                    return (int) ($r['employee_id'] ?? 0);
                }, $slice)));
                $existing = PayslipClaim::query()
                    ->where('payroll_run_id', $run->id)
                    ->whereIn('employee_id', $empIds)
                    ->get()
                    ->keyBy('employee_id');

                DB::transaction(function () use (
                    $run,
                    $proof,
                    $slice,
                    $scan,
                    $existing,
                    $rowsScanned,
                    &$claimedNew,
                    &$claimedDetected
                ) {
                    for ($idx = 0; $idx < $rowsScanned; $idx++) {
                        $row = $slice[$idx] ?? null;
                        $res = $scan[$idx] ?? null;
                        if (!$row || !$res) continue;

                        $empId = (int) ($row['employee_id'] ?? 0);
                        if (!$empId) continue;

                        if (!($res['claimed'] ?? false)) {
                            continue;
                        }

                        $claimedDetected++;
                        $already = $existing->get($empId);
                        if ($already && $already->claimed_at) {
                            continue;
                        }

                        PayslipClaim::updateOrCreate(
                            ['payroll_run_id' => $run->id, 'employee_id' => $empId],
                            [
                                'claimed_at' => now(),
                                'claimed_by_user_id' => Auth::id(),
                                'payslip_claim_proof_id' => $proof->id,
                                'ink_ratio' => (float) ($res['ink_ratio'] ?? 0),
                            ]
                        );
                        $claimedNew++;
                    }
                });
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $proof->processed_at = now();
            $proof->processed_summary = array_filter([
                'pages' => 1,
                'page_index' => $pageIndex + 1,
                'rows_scanned' => $rowsScanned,
                'claimed_detected' => $claimedDetected,
                'claimed_new' => $claimedNew,
                'error' => $error,
            ], fn ($v) => $v !== null && $v !== '');
            $proof->save();
        }
    }

    private function inferPageIndexFromFilename(?string $name): ?int
    {
        $name = (string) ($name ?? '');
        if ($name === '') return null;

        if (preg_match('/(?:page|pg)[^0-9]*([0-9]{1,3})/i', $name, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? ($n - 1) : null;
        }
        if (preg_match('/\\b([0-9]{1,3})\\b/', $name, $m)) {
            $n = (int) $m[1];
            if ($n > 0 && $n <= 999) return $n - 1;
        }

        return null;
    }
}
