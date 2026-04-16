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
            // Cleanup stale low-confidence auto-review rows produced by older scanner
            // logic (e.g., no-QR shaded guesses at ~55% confidence).
            PayslipClaim::query()
                ->where('payroll_run_id', $selectedRun->id)
                ->where('review_status', 'needs_review')
                ->whereNull('claimed_at')
                ->whereNotNull('payslip_claim_proof_id')
                ->where('confidence', '<=', 0.56)
                ->delete();

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

    public function destroyProof(Request $request, int $proof)
    {
        $proofRow = PayslipClaimProof::query()->find($proof);
        if (!$proofRow) {
            return redirect()->route('payslip.claims', [
                'run_id' => null,
                'month' => $this->normalizePeriodMonth($request->input('month')),
                'cutoff' => $this->normalizeCutoff($request->input('cutoff')),
                'assignment' => $this->normalizeAssignment($request->input('assignment')),
                'area_place' => $this->normalizeAreaPlace($request->input('area_place')),
            ])->with('success', 'Proof already deleted.');
        }
        $runId = (int) ($proofRow->payroll_run_id ?? 0);

        DB::transaction(function () use ($proofRow) {
            // If we delete the proof, the FK will null-out automatically, but the "claimed_at"
            // would still be set. Clear any auto-claims derived from this proof first.
            PayslipClaim::query()
                ->where('payslip_claim_proof_id', $proofRow->id)
                ->update([
                    'claimed_at'             => null,
                    'claimed_by_user_id'     => null,
                    'payslip_claim_proof_id' => null,
                    'ink_ratio'              => null,
                    'review_status'          => null,
                    'confidence'             => null,
                ]);

            try {
                Storage::disk('local')->delete($proofRow->storage_path);
            } catch (\Throwable $e) {
                // Best-effort: still remove DB record even if the file is already gone.
            }

            $proofRow->delete();
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
        $qrRequiredRaw = env('PAYSLIP_CLAIMS_REQUIRE_QR', config('services.payslip_claims.require_qr', false));
        $qrRequired = filter_var($qrRequiredRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $qrRequired = $qrRequired ?? false;

        $checkboxConfirmCutoff = (float) env(
            'PAYSLIP_CLAIMS_CHECKBOX_CONFIRM_CUTOFF',
            config('services.payslip_claims.checkbox_confirm_cutoff', 0.55)
        );
        $checkboxReviewCutoff = (float) env(
            'PAYSLIP_CLAIMS_CHECKBOX_REVIEW_CUTOFF',
            config('services.payslip_claims.checkbox_review_cutoff', 0.30)
        );
        $noQrReviewSigCutoff = (float) env(
            'PAYSLIP_CLAIMS_NO_QR_REVIEW_SIG_CUTOFF',
            config('services.payslip_claims.no_qr_review_sig_cutoff', 0.04)
        );
        $checkboxConfirmCutoff = max(0.10, min(0.95, $checkboxConfirmCutoff));
        $checkboxReviewCutoff = max(0.05, min($checkboxConfirmCutoff, $checkboxReviewCutoff));
        $noQrReviewSigCutoff = max(0.0, min(1.0, $noQrReviewSigCutoff));
        $employees = $this->runEmployeesForClaimSheet($run)->values();
        // Important: include run id so expected per-row QR tokens are generated.
        // Without this, expected tokens are empty and token_match is always false.
        $pages = $this->buildClaimSheetPages($employees, $rowsPerPage, (int) $run->id);
        $scanner = new ClaimSheetScanner();
        $gemini  = new \App\Services\PayslipClaims\GeminiClaimScanner();
        $useGemini = $gemini->isAvailable();
        $usedPageIndexes = [];
        $nextPageIndex = 0;

        // Build a flat token→page-index lookup so a quick QR probe can identify
        // the correct employee slice even when the filename page number doesn't
        // align with the all-employee page index (e.g. after a filtered download).
        $tokenPageMap = [];
        foreach ($pages as $pageIdx => $page) {
            foreach (($page['rows'] ?? []) as $row) {
                $knownTokens = $this->knownRowTokens($run, (array) $row);
                foreach ($knownTokens as $t) {
                    if ($t !== '') $tokenPageMap[$t] = $pageIdx;
                }
            }
        }

        foreach (array_values($proofs) as $i => $proof) {
            // Reprocessing the same proof should replace its prior auto results
            // instead of accumulating stale/incorrect rows from earlier scanner logic.
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

            $claimedNewTotal = 0;
            $claimedDetectedTotal = 0;
            $needsReviewDetectedTotal = 0;
            $rowsScannedTotal = 0;
            $inkSoft = [];
            $inkStrict = [];
            $claimedRowIndexes = [];
            $claimedEmpNos = [];
            $shadedRowIndexes = [];
            $shadedResolvedEmpNos = [];
            $shadedUnresolvedRows = [];
            $scanDebug = null;
            $error = null;
            $pagesProcessed = 0;
            $firstPageIndexUsed = null;
            $sliceFirstEmpNo = null;
            $sliceLastEmpNo = null;
            $qrFoundTotal = 0;
            $tokenMatchTotal = 0;
            $tokenInPageTotal = 0;
            $summaryMode = $useGemini ? 'gemini' : ($qrRequired ? 'qr' : 'no_qr');

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
                $filenameNorm = $this->normalizeForTokenMatch((string) ($proof->original_name ?? ''));

                try {
                    // ── Page identification (QR probe, unchanged) ────────────────
                    $probeTokens    = $scanner->probeQrTokens($asset['path']);
                    $probePageIndex = null;
                    foreach ($probeTokens as $probed) {
                        if (isset($tokenPageMap[$probed]) && !isset($usedPageIndexes[$tokenPageMap[$probed]])) {
                            $probePageIndex = $tokenPageMap[$probed];
                            break;
                        }
                    }

                    if ($probePageIndex !== null) {
                        $candidates = [$probePageIndex];
                    } else {
                        $candidates = [$seedPageIndex];
                        $hintIdx = ($suggested !== null && isset($pages[$suggested]) && !isset($usedPageIndexes[$suggested]))
                            ? $suggested : $seedPageIndex;
                        foreach ([-1, 1, -2, 2] as $off) {
                            $cand = $hintIdx + $off;
                            if ($cand >= 0 && isset($pages[$cand]) && !isset($usedPageIndexes[$cand])) {
                                $candidates[] = $cand;
                            }
                        }
                        $candidates = array_values(array_unique($candidates));
                    }

                    // Pick the best candidate page (area-name match + seed distance).
                    $pageIndex = (int) $candidates[0];
                    if (count($candidates) > 1) {
                        $bestCandScore = -INF;
                        foreach ($candidates as $candPageIndex) {
                            $page = $pages[$candPageIndex] ?? null;
                            if (!$page) continue;
                            $pageAreaNorm  = $this->normalizeForTokenMatch((string) ($page['area'] ?? ''));
                            $areaBonus     = ($filenameNorm !== '' && $pageAreaNorm !== '' && str_contains($filenameNorm, $pageAreaNorm)) ? 100 : 0;
                            $distPenalty   = abs($candPageIndex - $seedPageIndex);
                            $candScore     = $areaBonus - $distPenalty;
                            if ($candScore > $bestCandScore) {
                                $bestCandScore = $candScore;
                                $pageIndex = $candPageIndex;
                            }
                        }
                    }

                    $page         = $pages[$pageIndex] ?? null;
                    $slice        = $page ? (array) ($page['rows'] ?? []) : [];
                    $expectedRows = count($slice);
                    if ($expectedRows === 0) continue;

                    $usedPageIndexes[$pageIndex] = true;
                    $nextPageIndex = max($nextPageIndex, $pageIndex + 1);
                    $firstPageIndexUsed = $firstPageIndexUsed ?? $pageIndex;

                    $sliceFirst      = $slice[0] ?? null;
                    $sliceLast       = $slice[$expectedRows - 1] ?? null;
                    $sliceFirstEmpNo = $sliceFirstEmpNo ?? (string) ($sliceFirst['emp_no'] ?? '');
                    $sliceLastEmpNo  = (string) ($sliceLast['emp_no'] ?? $sliceLastEmpNo);

                    $scanDebug = [];
                    if ($probePageIndex !== null) {
                        $scanDebug['probe_page_index'] = $probePageIndex;
                    }

                    // ── Gemini vision path ───────────────────────────────────────
                    if (!empty($manualShadedRows)) {
                        $summaryMode = 'manual_rows_override';
                        $scanDebug['mode'] = 'manual_rows_override';
                        $scanDebug['manual_rows'] = implode(',', $manualShadedRows);

                        $rowsScannedTotal += $expectedRows;
                        $pagesProcessed++;

                        $manualRowsInPage = array_values(array_unique(array_filter(
                            $manualShadedRows,
                            fn ($rowNo) => is_int($rowNo) && $rowNo >= 1 && $rowNo <= $expectedRows
                        )));
                        sort($manualRowsInPage);

                        $empIds = array_values(array_filter(array_map(fn ($r) => (int) ($r['employee_id'] ?? 0), $slice)));
                        $existing = PayslipClaim::query()
                            ->where('payroll_run_id', $run->id)
                            ->whereIn('employee_id', $empIds)
                            ->get()
                            ->keyBy('employee_id');

                        DB::transaction(function () use (
                            $run, $proof, $slice, $manualRowsInPage, $existing,
                            &$claimedNewTotal, &$claimedDetectedTotal,
                            &$claimedRowIndexes, &$claimedEmpNos,
                            &$shadedResolvedEmpNos, &$shadedRowIndexes
                        ) {
                            foreach ($manualRowsInPage as $rowNo) {
                                $idx = $rowNo - 1;
                                $row = $slice[$idx] ?? null;
                                if (!is_array($row)) continue;
                                $empId = (int) ($row['employee_id'] ?? 0);
                                if (!$empId) continue;

                                $empNo = (string) ($row['emp_no'] ?? '');
                                $claimedDetectedTotal++;
                                $claimedRowIndexes[] = $rowNo;
                                if ($empNo !== '') $claimedEmpNos[] = $empNo;
                                $shadedRowIndexes[] = $rowNo;
                                if ($empNo !== '') $shadedResolvedEmpNos[] = $empNo;

                                $already = $existing->get($empId);
                                if ($already && $already->claimed_at) continue;

                                PayslipClaim::updateOrCreate(
                                    ['payroll_run_id' => $run->id, 'employee_id' => $empId],
                                    [
                                        'claimed_at'             => now(),
                                        'claimed_by_user_id'     => Auth::id(),
                                        'payslip_claim_proof_id' => $proof->id,
                                        'ink_ratio'              => 1.0,
                                        'review_status'          => 'confirmed',
                                        'confidence'             => 0.99,
                                    ]
                                );
                                $claimedNewTotal++;
                            }
                        });

                        continue; // next asset
                    }
                    if ($useGemini) {
                        try {
                        $geminiResults = $gemini->scanPage($asset['path'], $slice);
                        $scanDebug['mode'] = 'gemini';

                        $rowsScannedTotal += $expectedRows;
                        $pagesProcessed++;

                        // Build emp_no → employee_id map for this slice.
                        $empNoToRow = [];
                        foreach ($slice as $sliceRow) {
                            $en = (string) ($sliceRow['emp_no'] ?? '');
                            if ($en !== '') $empNoToRow[$en] = $sliceRow;
                        }

                        $empIds  = array_values(array_filter(array_map(fn ($r) => (int) ($r['employee_id'] ?? 0), $slice)));
                        $existing = PayslipClaim::query()
                            ->where('payroll_run_id', $run->id)
                            ->whereIn('employee_id', $empIds)
                            ->get()
                            ->keyBy('employee_id');

                        DB::transaction(function () use (
                            $run, $proof, $geminiResults, $empNoToRow, $existing,
                            &$claimedNewTotal, &$claimedDetectedTotal,
                            &$claimedRowIndexes, &$claimedEmpNos,
                            &$shadedResolvedEmpNos, &$shadedRowIndexes
                        ) {
                            foreach ($geminiResults as $idx => $gr) {
                                $empNo     = (string) ($gr['emp_no']     ?? '');
                                $claimed   = (bool)   ($gr['claimed']    ?? false);
                                $confidence = (float) ($gr['confidence'] ?? 0.5);
                                $row = $empNoToRow[$empNo] ?? null;
                                if (!$row) continue;
                                $empId  = (int) ($row['employee_id'] ?? 0);
                                if (!$empId) continue;
                                $already = $existing->get($empId);

                                if ($claimed) {
                                    $claimedDetectedTotal++;
                                    $claimedRowIndexes[]    = $idx + 1;
                                    $claimedEmpNos[]        = $empNo;
                                    $shadedRowIndexes[]     = $idx + 1;
                                    $shadedResolvedEmpNos[] = $empNo;

                                    if ($already && $already->claimed_at) continue;
                                    PayslipClaim::updateOrCreate(
                                        ['payroll_run_id' => $run->id, 'employee_id' => $empId],
                                        [
                                            'claimed_at'             => now(),
                                            'claimed_by_user_id'     => Auth::id(),
                                            'payslip_claim_proof_id' => $proof->id,
                                            'ink_ratio'              => 1.0,
                                            'review_status'          => 'confirmed',
                                            'confidence'             => $confidence,
                                        ]
                                    );
                                    $claimedNewTotal++;
                                }
                            }
                        });

                        continue; // next asset
                        } catch (\Throwable $geminiError) {
                            // Gemini failed (e.g. 403 permission denied); continue with local scanner.
                            $useGemini = false;
                            $summaryMode = 'scanner_fallback';
                            $scanDebug['mode'] = 'scanner_fallback';
                            $scanDebug['fallback_reason'] = $this->friendlyScannerFallbackReason($geminiError->getMessage());
                        }
                    }

                    // ── Pixel scanner fallback (original logic) ──────────────────
                    $rowTokens = array_map(
                        fn ($r) => ClaimToken::generate($run->id, (int) ($r['employee_id'] ?? 0)),
                        $slice
                    );
                    $legacyRowTokens = array_map(
                        fn ($r) => $this->legacyRowToken($run, (array) $r),
                        $slice
                    );
                    $scanTokens = $qrRequired ? $rowTokens : [];
                    $scan       = $scanner->scanImageFile($asset['path'], $expectedRows, $scanTokens);
                    $scanDebug  = array_merge($scanDebug, $scanner->getLastDebug());

                    $rowsScanned = min($expectedRows, count($scan));
                    $rowsScannedTotal += $rowsScanned;
                    $pagesProcessed++;
                    $pageTrusted = true;

                    $tokenToSliceIndex = [];
                    foreach ($rowTokens as $tokIdx => $tok) {
                        $tok = strtoupper(trim((string) $tok));
                        if ($tok !== '') $tokenToSliceIndex[$tok] = (int) $tokIdx;
                    }
                    foreach ($legacyRowTokens as $tokIdx => $tok) {
                        $tok = strtoupper(trim((string) $tok));
                        if ($tok !== '') $tokenToSliceIndex[$tok] = (int) $tokIdx;
                    }

                    $statusRank = ['unclaimed' => 0, 'needs_review' => 1, 'confirmed' => 2];
                    $resolvedByEmpId = [];
                    $remappedRows = 0;
                    $promotedByTokenMap = 0;
                    $scanCutoff = (float) ($scanDebug['checkbox_cutoff'] ?? 0.30);
                    $shadeCutoff = max(0.22, $scanCutoff * 0.90);
                    for ($k = 0; $k < $rowsScanned; $k++) {
                        $inkSoft[]   = (float) ($scan[$k]['ink_ratio'] ?? 0);
                        $inkStrict[] = (float) ($scan[$k]['ink_ratio_strict'] ?? 0);

                        $res = (array) ($scan[$k] ?? []);
                        $status = (string) ($res['status'] ?? 'unclaimed');
                        $tokenFound = strtoupper(trim((string) ($res['token_found'] ?? '')));
                        $mappedIdx = $k;
                        $tokenMapped = $tokenFound !== '' && isset($tokenToSliceIndex[$tokenFound]);
                        if ($tokenMapped) {
                            $mappedIdx = (int) $tokenToSliceIndex[$tokenFound];
                            if ($mappedIdx !== $k) $remappedRows++;
                        }

                        $rowInkStrict = (float) ($res['ink_ratio_strict'] ?? $res['ink_ratio'] ?? 0.0);
                        if ($rowInkStrict >= $shadeCutoff) {
                            $shadedRowIndexes[] = $k + 1;
                            if (isset($slice[$mappedIdx])) {
                                $shadedResolvedEmpNos[] = (string) ($slice[$mappedIdx]['emp_no'] ?? '');
                            } else {
                                $shadedUnresolvedRows[] = $k + 1;
                            }
                        }

                        if (
                            $tokenMapped
                            && !empty($res['qr_found'])
                            && in_array($status, ['needs_review', 'unclaimed'], true)
                            && $rowInkStrict >= 0.30
                        ) {
                            $status = 'confirmed';
                            $res['status'] = 'confirmed';
                            $res['confidence'] = max((float) ($res['confidence'] ?? 0.0), 0.90);
                            $res['token_match'] = true;
                            $promotedByTokenMap++;
                        }

                        if (!$qrRequired) {
                            $qrConfirmed = !empty($res['token_match']) && $status === 'confirmed';
                            if (!$qrConfirmed) {
                                $effectiveConfirmCutoff = max($checkboxReviewCutoff + 0.01, $scanCutoff);
                                $rowSigInk = (float) ($res['sig_ink'] ?? 0.0);
                                if ($rowInkStrict >= $effectiveConfirmCutoff) {
                                    $status = 'confirmed';
                                    $res['status'] = 'confirmed';
                                    $res['confidence'] = max((float) ($res['confidence'] ?? 0.0), 0.82);
                                } elseif ($rowInkStrict >= $checkboxReviewCutoff && $rowSigInk >= $noQrReviewSigCutoff) {
                                    $status = 'needs_review';
                                    $res['status'] = 'needs_review';
                                    $res['confidence'] = max((float) ($res['confidence'] ?? 0.0), 0.60);
                                } else {
                                    $status = 'unclaimed';
                                    $res['status'] = 'unclaimed';
                                }
                            }
                        }

                        $row = $slice[$mappedIdx] ?? null;
                        if (!$row) continue;
                        $empId = (int) ($row['employee_id'] ?? 0);
                        if (!$empId) continue;

                        $candidate = [
                            'row' => $row,
                            'res' => $res,
                            'status' => $status,
                            'mapped_idx' => $mappedIdx,
                            'confidence' => (float) ($res['confidence'] ?? 0.0),
                        ];
                        $prev = $resolvedByEmpId[$empId] ?? null;
                        if (
                            !$prev
                            || (($statusRank[$status] ?? 0) > ($statusRank[$prev['status']] ?? 0))
                            || ((($statusRank[$status] ?? 0) === ($statusRank[$prev['status']] ?? 0))
                                && $candidate['confidence'] > (float) ($prev['confidence'] ?? 0.0))
                        ) {
                            $resolvedByEmpId[$empId] = $candidate;
                        }
                    }

                    $scanAssignments = array_values($resolvedByEmpId);
                    foreach ($scanAssignments as $assignment) {
                        if (($assignment['status'] ?? 'unclaimed') === 'confirmed') {
                            $claimedRowIndexes[] = ((int) ($assignment['mapped_idx'] ?? 0)) + 1;
                            $claimedEmpNos[] = (string) (($assignment['row']['emp_no'] ?? ''));
                        }
                    }

                    if ($remappedRows > 0) $scanDebug['token_row_remap'] = $remappedRows;
                    if ($promotedByTokenMap > 0) $scanDebug['token_promoted_confirmed'] = $promotedByTokenMap;

                    $empIds = array_values(array_filter(array_map(fn ($r) => (int) ($r['employee_id'] ?? 0), $slice)));
                    $existing = PayslipClaim::query()
                        ->where('payroll_run_id', $run->id)
                        ->whereIn('employee_id', $empIds)
                        ->get()
                        ->keyBy('employee_id');

                    DB::transaction(function () use (
                        $run, $proof, $scanAssignments, $existing, $pageTrusted,
                        &$claimedNewTotal, &$claimedDetectedTotal, &$needsReviewDetectedTotal
                    ) {
                        foreach ($scanAssignments as $assignment) {
                            $row = $assignment['row'] ?? null;
                            $res = $assignment['res'] ?? null;
                            if (!is_array($row) || !is_array($res)) continue;
                            $empId = (int) ($row['employee_id'] ?? 0);
                            if (!$empId) continue;
                            $status     = (string) ($assignment['status'] ?? ($res['status'] ?? 'unclaimed'));
                            $confidence = (float)  ($res['confidence'] ?? 0.0);
                            $already    = $existing->get($empId);
                            if (!$pageTrusted && $status !== 'unclaimed') continue;

                            if ($status === 'confirmed') {
                                $claimedDetectedTotal++;
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
                                $needsReviewDetectedTotal++;
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
                'mode' => $summaryMode,
                'pages' => max(1, $pagesProcessed),
                'page_index' => $firstPageIndexUsed !== null ? $firstPageIndexUsed + 1 : null,
                'rows_scanned' => $rowsScannedTotal,
                'claimed_detected' => $claimedDetectedTotal,
                'claimed_new' => $claimedNewTotal,
                'needs_review_detected' => $needsReviewDetectedTotal,
                'shaded_rows_detected' => array_values(array_unique(array_map('intval', $shadedRowIndexes))),
                'shaded_rows_count' => count(array_unique(array_map('intval', $shadedRowIndexes))),
                'shaded_emp_nos_resolved' => array_values(array_filter(array_unique($shadedResolvedEmpNos), fn ($v) => $v !== '')),
                'shaded_rows_unresolved' => array_values(array_unique(array_map('intval', $shadedUnresolvedRows))),
                'qr_found' => $qrFoundTotal,
                'token_matches' => $tokenMatchTotal,
                'token_in_page' => $tokenInPageTotal,
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

    private function friendlyScannerFallbackReason(string $message): string
    {
        $raw = trim($message);
        $upper = strtoupper($raw);

        if (str_contains($upper, 'PERMISSION_DENIED') || str_contains($upper, 'GEMINI API ERROR 403')) {
            return 'Gemini access denied (403). Used local scanner fallback.';
        }

        if (strlen($raw) > 220) {
            return substr($raw, 0, 220) . '...';
        }

        return $raw !== '' ? $raw : 'Gemini unavailable. Used local scanner fallback.';
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
        // Keep rendering fast enough for web request limits; 240 DPI balances
        // readability and avoids frequent 60s request timeouts on PHP/FPM setups.
        $imagick->setResolution(240, 240);
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

    private function normalizeForTokenMatch(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') return '';
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
        return $value;
    }

    private function inferPageIndexFromFilename(?string $name): ?int
    {
        $name = (string) ($name ?? '');
        if ($name === '') return null;

        if (preg_match('/(?:page|pg)[^0-9]*([0-9]{1,3})/i', $name, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? ($n - 1) : null;
        }

        // Supports names like "..._1.pdf" or "...-2.jpg" when page/pg keyword isn't present.
        if (preg_match('/(?:^|[_\\-\\s])([0-9]{1,3})(?:\\.[a-z0-9]+)?$/i', $name, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? ($n - 1) : null;
        }

        return null;
    }

    private function knownRowTokens(PayrollRun $run, array $row): array
    {
        $current = ClaimToken::generate($run->id, (int) ($row['employee_id'] ?? 0));
        $legacy = $this->legacyRowToken($run, $row);
        return array_values(array_unique(array_filter([
            strtoupper(trim((string) $current)),
            strtoupper(trim((string) $legacy)),
        ], fn ($v) => $v !== '')));
    }

    private function legacyRowToken(PayrollRun $run, array $row): string
    {
        $runCode = trim((string) ($run->run_code ?: ('RUN-' . $run->id)));
        $empNo = trim((string) ($row['emp_no'] ?? ''));
        if ($runCode === '' || $empNo === '') return '';
        return strtoupper($runCode . ':' . $empNo);
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

