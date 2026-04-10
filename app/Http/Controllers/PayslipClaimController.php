<?php

namespace App\Http\Controllers;

use App\Models\CompanySetup;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayslipClaim;
use App\Models\PayslipClaimProof;
use App\Services\PayslipClaims\ClaimSheetScanner;
use App\Services\PayslipClaims\ClaimToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayslipClaimController extends Controller
{
    public function page(Request $request)
    {
        $runId = $request->query('run_id');
        $monthFilter = $this->normalizePeriodMonth($request->query('month'));
        $cutoffFilter = $this->normalizeCutoff($request->query('cutoff'));
        $assignmentFilter = $this->normalizeAssignment($request->query('assignment'));
        $areaPlaceFilter = $this->normalizeAreaPlace($request->query('area_place'));

        $runs = PayrollRun::query()
            ->whereIn('status', ['Released'])
            ->when($monthFilter, fn ($q) => $q->where('period_month', $monthFilter))
            ->when($cutoffFilter, fn ($q) => $q->where('cutoff', $cutoffFilter))
            ->orderByDesc('id')
            ->get();

        $selectedRun = null;
        $employees = collect();
        $summary = null;
        $proofs = collect();
        $assignmentOptions = collect();
        $assignmentAreaPlaces = [];

        if ($runId) {
            $selectedRun = $runs->firstWhere('id', (int) $runId);
        } elseif ($monthFilter || $cutoffFilter) {
            $selectedRun = $runs->first();
        }

        // If no run matched the current filters, fall back to the latest released run.
        if (!$selectedRun) {
            $fallbackRuns = PayrollRun::query()
                ->whereIn('status', ['Released'])
                ->orderByDesc('id')
                ->get();

            if ($fallbackRuns->isNotEmpty()) {
                $selectedRun = $fallbackRuns->first();
                $runs = $fallbackRuns;
            }
        }

        // Always sync month/cutoff inputs from the selected run so the filter
        // fields are never blank while a run is displayed — this matters when
        // arriving via run_id redirect (after upload/delete) which carries no
        // month or cutoff in the URL.
        if ($selectedRun) {
            $monthFilter  = $monthFilter  ?: $selectedRun->period_month;
            $cutoffFilter = $cutoffFilter ?: $selectedRun->cutoff;
        }

        if ($selectedRun) {
            $employees = $this->runEmployeesWithClaimStatus($selectedRun);
            $assignmentOptions = $employees
                ->pluck('assignment_type')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $assignmentAreaPlaces = $employees
                ->groupBy(fn ($r) => trim((string) ($r['assignment_type'] ?? '')))
                ->map(function ($rows) {
                    return collect($rows)
                        ->pluck('area_place')
                        ->map(fn ($v) => trim((string) $v))
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();
                })
                ->filter(fn ($v, $k) => $k !== '')
                ->all();

            if (!$assignmentFilter || !array_key_exists($assignmentFilter, $assignmentAreaPlaces)) {
                $areaPlaceFilter = null;
            } elseif ($areaPlaceFilter && !in_array($areaPlaceFilter, $assignmentAreaPlaces[$assignmentFilter] ?? [], true)) {
                $areaPlaceFilter = null;
            }

            $employees = $this->filterEmployees($employees, $assignmentFilter, $areaPlaceFilter);
            $summary = $this->computeClaimSummaryFromRows($employees);
            $proofs = PayslipClaimProof::query()
                ->where('payroll_run_id', $selectedRun->id)
                ->orderByDesc('id')
                ->get();
        }

        return view('layouts.payslip_claims', [
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'employees' => $employees,
            'summary' => $summary,
            'proofs' => $proofs,
            'monthFilter' => $monthFilter,
            'cutoffFilter' => $cutoffFilter,
            'assignmentFilter' => $assignmentFilter,
            'assignmentOptions' => $assignmentOptions,
            'areaPlaceFilter' => $areaPlaceFilter,
            'assignmentAreaPlaces' => $assignmentAreaPlaces,
        ]);
    }

    public function downloadClaimSheet(Request $request, PayrollRun $run)
    {
        if ($run->status !== 'Released') {
            return response()->json(['message' => 'Claim sheet is available only for released runs.'], 409);
        }

        $company = CompanySetup::query()->first();
        $assignmentFilter = $this->normalizeAssignment($request->query('assignment'));
        $areaPlaceFilter = $this->normalizeAreaPlace($request->query('area_place'));
        $rows = $this->filterEmployees($this->runEmployeesWithClaimStatus($run), $assignmentFilter, $areaPlaceFilter);
        $pages = $this->buildClaimSheetPages($rows, 25, $run->id);

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
        $this->extendExecutionForProofScanning();

        if ($run->status !== 'Released') {
            return back()->withErrors(['proof' => 'Proof upload is available only for released runs.']);
        }

        $validated = $request->validate([
            'proofs' => ['required', 'array', 'min:1'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
        ]);

        $files = $validated['proofs'];
        usort($files, function ($a, $b) {
            // Natural sort so IMG_2 comes before IMG_10 (reduces page/order mismatches).
            return strnatcasecmp((string) $a->getClientOriginalName(), (string) $b->getClientOriginalName());
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

        return redirect()->route('payslip.claims', [
            'run_id' => $run->id,
            'month' => $this->normalizePeriodMonth($request->input('month')),
            'cutoff' => $this->normalizeCutoff($request->input('cutoff')),
            'assignment' => $this->normalizeAssignment($request->input('assignment')),
            'area_place' => $this->normalizeAreaPlace($request->input('area_place')),
        ])
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

    public function toggleClaim(Request $request, PayrollRun $run, int $employeeId)
    {
        $claim = PayslipClaim::firstOrNew([
            'payroll_run_id' => $run->id,
            'employee_id'    => $employeeId,
        ]);

        if ($claim->claimed_at) {
            $claim->claimed_at             = null;
            $claim->claimed_by_user_id     = null;
            $claim->payslip_claim_proof_id = null;
            $claim->ink_ratio              = null;
            $claim->review_status          = null;
            $claim->confidence             = null;
        } else {
            $claim->claimed_at         = now();
            $claim->claimed_by_user_id = Auth::id();
            $claim->review_status      = 'confirmed';
        }
        $claim->save();

        return redirect()->route('payslip.claims', [
            'run_id' => $run->id,
            'month' => $this->normalizePeriodMonth($request->input('month')),
            'cutoff' => $this->normalizeCutoff($request->input('cutoff')),
            'assignment' => $this->normalizeAssignment($request->input('assignment')),
            'area_place' => $this->normalizeAreaPlace($request->input('area_place')),
        ]);
    }

    public function destroyProof(Request $request, PayslipClaimProof $proof)
    {
        $runId = (int) ($proof->payroll_run_id ?? 0);

        DB::transaction(function () use ($proof) {
            // If we delete the proof, the FK will null-out automatically, but the "claimed_at"
            // would still be set. Clear any auto-claims derived from this proof first.
            PayslipClaim::query()
                ->where('payslip_claim_proof_id', $proof->id)
                ->update([
                    'claimed_at'             => null,
                    'claimed_by_user_id'     => null,
                    'payslip_claim_proof_id' => null,
                    'ink_ratio'              => null,
                    'review_status'          => null,
                    'confidence'             => null,
                ]);

            try {
                Storage::disk('local')->delete($proof->storage_path);
            } catch (\Throwable $e) {
                // Best-effort: still remove DB record even if the file is already gone.
            }

            $proof->delete();
        });

        return redirect()->route('payslip.claims', [
            'run_id' => $runId ?: null,
            'month' => $this->normalizePeriodMonth($request->input('month')),
            'cutoff' => $this->normalizeCutoff($request->input('cutoff')),
            'assignment' => $this->normalizeAssignment($request->input('assignment')),
            'area_place' => $this->normalizeAreaPlace($request->input('area_place')),
        ])
            ->with('success', 'Proof deleted.');
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

    private function buildClaimSheetPages($rows, int $rowsPerPage = 25, int $runId = 0): array
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
                    $empId = (int) ($row['employee_id'] ?? 0);
                    $token = ($runId && $empId) ? ClaimToken::generate($runId, $empId) : '';
                    $pageRows[] = array_merge($row, [
                        'no'          => $areaSeq,
                        'token'       => $token,
                        'qr_data_uri' => $token ? ClaimToken::qrDataUri($token) : '',
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
                'claimed_at'    => $claim?->claimed_at,
                'proof_id'      => $claim?->payslip_claim_proof_id,
                'review_status' => $claim?->review_status,
                'confidence'    => $claim?->confidence,
            ]);
        });
    }

    private function computeClaimSummaryFromRows($rows): array
    {
        $rows = collect($rows);
        $total = $rows->count();
        $claimed = $rows->filter(fn ($r) => !empty($r['claimed_at']))->count();
        $needsReview = $rows->filter(fn ($r) => (($r['review_status'] ?? null) === 'needs_review'))->count();
        return [
            'total'        => (int) $total,
            'claimed'      => (int) $claimed,
            'needs_review' => (int) $needsReview,
            'unclaimed'    => max(0, (int) $total - (int) $claimed),
        ];
    }

    private function filterEmployees($rows, ?string $assignmentFilter, ?string $areaPlaceFilter = null)
    {
        $rows = collect($rows);
        if (!$assignmentFilter) {
            return $rows->values();
        }

        return $rows->filter(function ($r) use ($assignmentFilter, $areaPlaceFilter) {
            $assignment = trim((string) ($r['assignment_type'] ?? ''));
            if (strcasecmp($assignment, $assignmentFilter) !== 0) return false;
            if (!$areaPlaceFilter) return true;
            $area = trim((string) ($r['area_place'] ?? ''));
            return strcasecmp($area, $areaPlaceFilter) === 0;
        })->values();
    }

    private function processUploadedProofs(PayrollRun $run, array $proofs): void
    {
        $this->extendExecutionForProofScanning();

        $rowsPerPage = 25;
        $employees = $this->runEmployeesForClaimSheet($run)->values();
        $pages = $this->buildClaimSheetPages($employees, $rowsPerPage);
        $scanner = new ClaimSheetScanner();
        $usedPageIndexes = [];
        $nextPageIndex = 0;

        foreach (array_values($proofs) as $i => $proof) {
            $claimedNewTotal = 0;
            $claimedDetectedTotal = 0;
            $rowsScannedTotal = 0;
            $inkSoft = [];
            $inkStrict = [];
            $claimedRowIndexes = [];
            $claimedEmpNos = [];
            $scanDebug = null;
            $error = null;
            $pagesProcessed = 0;
            $firstPageIndexUsed = null;
            $sliceFirstEmpNo = null;
            $sliceLastEmpNo = null;

            $assets = [];
            try {
                $assets = $this->scannableAssetsForProof($proof);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            if (empty($assets) && !$error) {
                $error = 'No scannable pages found in upload.';
            }

            foreach ($assets as $assetIndex => $asset) {
                $suggested = $assetIndex === 0 ? $this->inferPageIndexFromFilename($proof->original_name) : null;
                $seedPageIndex = $this->nextAvailablePageIndex($pages, $usedPageIndexes, $nextPageIndex, $suggested, $i);
                if ($seedPageIndex === null) {
                    break;
                }

                try {
                    // Try nearby page indexes and pick the page with strongest QR token matches.
                    $candidates = [$seedPageIndex];
                    if ($suggested !== null && isset($pages[$suggested]) && !isset($usedPageIndexes[$suggested])) {
                        // If filename says PAGE3, prioritize that exact page to avoid cross-page misassignment.
                        $candidates = [$suggested];
                    } else {
                        foreach ([-1, 1, -2, 2] as $off) {
                            $cand = $seedPageIndex + $off;
                            if ($cand >= 0 && isset($pages[$cand]) && !isset($usedPageIndexes[$cand])) {
                                $candidates[] = $cand;
                            }
                        }
                    }
                    $candidates = array_values(array_unique($candidates));

                    $best = null;
                    foreach ($candidates as $candPageIndex) {
                        $page = $pages[$candPageIndex] ?? null;
                        $candSlice = $page ? (array) ($page['rows'] ?? []) : [];
                        $candExpectedRows = count($candSlice);
                        if ($candExpectedRows === 0) continue;

                        $rowTokens = array_map(
                            fn ($r) => ClaimToken::generate($run->id, (int) ($r['employee_id'] ?? 0)),
                            $candSlice
                        );
                        $candScan = $scanner->scanImageFile($asset['path'], $candExpectedRows, $rowTokens);
                        $candDebug = $scanner->getLastDebug();

                        $matchCount = 0;
                        $qrFoundCount = 0;
                        $confirmedCount = 0;
                        $rows = min($candExpectedRows, count($candScan));
                        for ($m = 0; $m < $rows; $m++) {
                            if (!empty($candScan[$m]['token_match'])) $matchCount++;
                            if (!empty($candScan[$m]['qr_found'])) $qrFoundCount++;
                            if (($candScan[$m]['status'] ?? '') === 'confirmed') $confirmedCount++;
                        }
                        $score = ($matchCount * 10) + ($qrFoundCount * 2) + $confirmedCount;

                        if (!$best || $score > $best['score']) {
                            $best = [
                                'score' => $score,
                                'matchCount' => $matchCount,
                                'qrFoundCount' => $qrFoundCount,
                                'pageIndex' => $candPageIndex,
                                'slice' => $candSlice,
                                'expectedRows' => $candExpectedRows,
                                'scan' => $candScan,
                                'scanDebug' => $candDebug,
                            ];
                        }
                    }

                    if (!$best) {
                        continue;
                    }

                    $pageIndex = (int) $best['pageIndex'];
                    $slice = (array) $best['slice'];
                    $expectedRows = (int) $best['expectedRows'];
                    $scan = (array) $best['scan'];
                    $scanDebug = (array) ($best['scanDebug'] ?? []);
                    $bestMatchCount = (int) ($best['matchCount'] ?? 0);
                    // Only trust a page when at least one QR token actually matches.
                    $pageTrusted = $bestMatchCount > 0;
                    if (!$pageTrusted && !$error) {
                        $error = 'Skipped auto-assignment for this page because no QR token matched the expected page rows.';
                    }

                    $usedPageIndexes[$pageIndex] = true;
                    $nextPageIndex = max($nextPageIndex, $pageIndex + 1);
                    $firstPageIndexUsed = $firstPageIndexUsed ?? $pageIndex;

                    $sliceFirst = $slice[0] ?? null;
                    $sliceLast = $slice[$expectedRows - 1] ?? null;
                    $sliceFirstEmpNo = $sliceFirstEmpNo ?? (string) ($sliceFirst['emp_no'] ?? '');
                    $sliceLastEmpNo = (string) ($sliceLast['emp_no'] ?? $sliceLastEmpNo);

                    $rowsScanned = min($expectedRows, count($scan));
                    $rowsScannedTotal += $rowsScanned;
                    $pagesProcessed++;

                    for ($k = 0; $k < $rowsScanned; $k++) {
                        $inkSoft[]   = (float) ($scan[$k]['ink_ratio'] ?? 0);
                        $inkStrict[] = (float) ($scan[$k]['ink_ratio_strict'] ?? 0);
                        if (($scan[$k]['status'] ?? '') === 'confirmed' && isset($slice[$k])) {
                            $claimedRowIndexes[] = $k + 1;
                            $claimedEmpNos[]     = (string) ($slice[$k]['emp_no'] ?? '');
                        }
                    }

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
                        $pageTrusted,
                        $rowsScanned,
                        &$claimedNewTotal,
                        &$claimedDetectedTotal
                    ) {
                        for ($idx = 0; $idx < $rowsScanned; $idx++) {
                            $row = $slice[$idx] ?? null;
                            $res = $scan[$idx] ?? null;
                            if (!$row || !$res) continue;

                            $empId = (int) ($row['employee_id'] ?? 0);
                            if (!$empId) continue;

                            $status     = (string) ($res['status'] ?? 'unclaimed');
                            $confidence = (float)  ($res['confidence'] ?? 0.0);
                            $already    = $existing->get($empId);

                            // Hard guard against wrong-employee tagging when page-to-row mapping is uncertain.
                            if (!$pageTrusted && $status !== 'unclaimed') {
                                continue;
                            }

                            if ($status === 'confirmed') {
                                $claimedDetectedTotal++;
                                // Don't overwrite a pre-existing confirmed claim.
                                if ($already && $already->claimed_at) continue;

                                PayslipClaim::updateOrCreate(
                                    ['payroll_run_id' => $run->id, 'employee_id' => $empId],
                                    [
                                        'claimed_at'             => now(),
                                        'claimed_by_user_id'     => Auth::id(),
                                        'payslip_claim_proof_id' => $proof->id,
                                        'ink_ratio'              => (float) ($res['ink_ratio'] ?? 0),
                                        'review_status'          => 'confirmed',
                                        'confidence'             => $confidence,
                                    ]
                                );
                                $claimedNewTotal++;
                            } elseif ($status === 'needs_review') {
                                // Don't overwrite an already-confirmed claim.
                                if ($already && $already->claimed_at) continue;

                                PayslipClaim::updateOrCreate(
                                    ['payroll_run_id' => $run->id, 'employee_id' => $empId],
                                    [
                                        'review_status'          => 'needs_review',
                                        'confidence'             => $confidence,
                                        'payslip_claim_proof_id' => $proof->id,
                                        'ink_ratio'              => (float) ($res['ink_ratio'] ?? 0),
                                    ]
                                );
                            }
                            // unclaimed: no record created
                        }
                    });
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }

            foreach ($assets as $asset) {
                if (!empty($asset['cleanup'])) {
                    @unlink((string) $asset['path']);
                }
            }

            $proof->processed_at = now();
            $proof->processed_summary = array_filter([
                'pages' => max(1, $pagesProcessed),
                'page_index' => $firstPageIndexUsed !== null ? $firstPageIndexUsed + 1 : null,
                'rows_scanned' => $rowsScannedTotal,
                'claimed_detected' => $claimedDetectedTotal,
                'claimed_new' => $claimedNewTotal,
                'claimed_rows_detected' => $claimedRowIndexes,
                'claimed_emp_nos_detected' => array_values(array_filter($claimedEmpNos, fn ($v) => $v !== '')),
                'slice_first_emp_no' => (string) ($sliceFirstEmpNo ?? ''),
                'slice_last_emp_no' => (string) ($sliceLastEmpNo ?? ''),
                'ink_soft_avg' => !empty($inkSoft) ? round(array_sum($inkSoft) / max(1, count($inkSoft)), 5) : null,
                'ink_soft_max' => !empty($inkSoft) ? round(max($inkSoft), 5) : null,
                'ink_strict_avg' => !empty($inkStrict) ? round(array_sum($inkStrict) / max(1, count($inkStrict)), 5) : null,
                'ink_strict_max' => !empty($inkStrict) ? round(max($inkStrict), 5) : null,
                'error' => $error,
                'geo' => !empty($scanDebug) ? $scanDebug : null,
            ], fn ($v) => $v !== null && $v !== '');
            $proof->save();
        }
    }

    private function scannableAssetsForProof(PayslipClaimProof $proof): array
    {
        $fullPath = Storage::disk('local')->path($proof->storage_path);
        $mime = strtolower((string) ($proof->mime ?? ''));
        $name = strtolower((string) ($proof->original_name ?? ''));
        $isPdf = str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');

        if (!$isPdf) {
            return [[
                'path' => $fullPath,
                'cleanup' => false,
            ]];
        }

        $imagickExtLoaded = extension_loaded('imagick');
        $imagickClassOk = class_exists(\Imagick::class);
        if (!$imagickExtLoaded && !$imagickClassOk) {
            throw new \RuntimeException(
                'PDF uploaded, but Imagick is not installed on the server. '
                . 'Install php-imagick to enable PDF claim scanning. '
                . '[sapi=' . php_sapi_name()
                . ', ini=' . (php_ini_loaded_file() ?: 'none')
                . ', ext=' . ($imagickExtLoaded ? '1' : '0')
                . ', class=' . ($imagickClassOk ? '1' : '0') . ']'
            );
        }

        try {
            $imagick = new \Imagick();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Imagick is available but failed to initialize for PDF scanning: '
                . $e->getMessage()
                . ' [sapi=' . php_sapi_name()
                . ', ini=' . (php_ini_loaded_file() ?: 'none')
                . ', ext=' . ($imagickExtLoaded ? '1' : '0')
                . ', class=' . ($imagickClassOk ? '1' : '0') . ']'
            );
        }
        // 300 DPI is expensive for large scans and often causes request timeouts.
        // 220 DPI keeps checkbox/QR detection quality while staying much faster.
        $imagick->setResolution(220, 220);
        $imagick->readImage($fullPath);

        $assets = [];
        foreach ($imagick as $page) {
            $page->setImageFormat('png');
            $tmp = tempnam(sys_get_temp_dir(), 'claim_pdf_');
            if ($tmp === false) {
                continue;
            }
            $png = $tmp . '.png';
            @unlink($tmp);
            $page->writeImage($png);
            $assets[] = [
                'path' => $png,
                'cleanup' => true,
            ];
        }
        $imagick->clear();
        $imagick->destroy();

        return $assets;
    }

    private function nextAvailablePageIndex(array $pages, array $usedPageIndexes, int $nextPageIndex, ?int $suggested, int $fallback): ?int
    {
        $isAvailable = function (?int $idx) use ($pages, $usedPageIndexes): bool {
            return $idx !== null && isset($pages[$idx]) && !isset($usedPageIndexes[$idx]);
        };

        if ($isAvailable($suggested)) {
            return $suggested;
        }
        if ($isAvailable($fallback)) {
            return $fallback;
        }
        if ($isAvailable($nextPageIndex)) {
            return $nextPageIndex;
        }

        $candidate = 0;
        while (isset($pages[$candidate]) && isset($usedPageIndexes[$candidate])) {
            $candidate++;
        }

        return $isAvailable($candidate) ? $candidate : null;
    }

    private function inferPageIndexFromFilename(?string $name): ?int
    {
        $name = (string) ($name ?? '');
        if ($name === '') return null;

        if (preg_match('/(?:page|pg)[^0-9]*([0-9]{1,3})/i', $name, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? ($n - 1) : null;
        }

        return null;
    }

    private function extendExecutionForProofScanning(): void
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
    }

    private function normalizePeriodMonth(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : null;
    }

    private function normalizeCutoff(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $upper = strtoupper($value);
        if ($upper === 'A' || $value === '26-10') return '26-10';
        if ($upper === 'B' || $value === '11-25') return '11-25';
        return null;
    }

    private function normalizeAssignment(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'all') === 0) return null;
        return $value;
    }

    private function normalizeAreaPlace(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'all') === 0) return null;
        return $value;
    }
}
