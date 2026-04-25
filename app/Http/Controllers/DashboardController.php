<?php

namespace App\Http\Controllers;

use App\Models\AreaPlace;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\EmployeeAreaHistory;
use App\Models\EmployeeCase;
use App\Models\Payslip;
use App\Models\PayslipClaim;
use App\Models\PayslipClaimProof;
use App\Models\PayrollCalendarSetting;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $assignments = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        $assignmentOrder = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label')
            ->all();

        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);

        $grouped = [];
        foreach ($rows as $row) {
            $parent = $row->parent_assignment ?? 'Field';
            $grouped[$parent][] = $row->label;
        }

        $ordered = [];
        foreach ($assignmentOrder as $label) {
            if (isset($grouped[$label])) {
                $ordered[$label] = $grouped[$label];
            }
        }

        $groupedAreaPlaces = $ordered;

        $latestProcessedRun = PayrollRun::query()
            ->where('status', 'Released')
            ->orderByDesc('id')
            ->first();

        return view('layouts.dashboard', [
            'assignments' => $assignments,
            'groupedAreaPlaces' => $groupedAreaPlaces,
            'latestProcessedRunId' => $latestProcessedRun?->id,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['nullable', 'in:all,11-25,26-10,A,B'],
            'assignment' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:150'],
            'deduction_type' => ['nullable', 'in:all,loans,government,attendance,charges,cash_advance'],
        ]);

        $latestProcessedGlobal = PayrollRun::query()
            ->where('status', 'Released')
            ->orderByDesc('id')
            ->first();
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);

        $defaultMonth = $latestProcessedGlobal
            ? $this->displayMonthForRun(
                (string) ($latestProcessedGlobal->period_month ?? ''),
                (string) ($latestProcessedGlobal->cutoff ?? ''),
                $calendar
            )
            : now()->format('Y-m');

        $month = (string) ($validated['month'] ?? $defaultMonth);
        $cutoff = $this->normalizeCutoff((string) ($validated['cutoff'] ?? ($latestProcessedGlobal?->cutoff ?? 'all')));
        $assignment = trim((string) ($validated['assignment'] ?? 'All'));
        if ($assignment === '') {
            $assignment = 'All';
        }
        $place = trim((string) ($validated['place'] ?? ''));
        $deductionType = strtolower(trim((string) ($validated['deduction_type'] ?? 'all')));
        if ($deductionType === '') {
            $deductionType = 'all';
        }
        $deductionExpr = $this->deductionExpression($deductionType);
        $periodMonthsForDisplay = [$month];
        if ((int) ($calendar->cutoff_b_from ?? 26) > (int) ($calendar->cutoff_b_to ?? 10)) {
            $periodMonthsForDisplay[] = $this->shiftYearMonth($month, -1);
        }
        $periodMonthsForDisplay = array_values(array_unique(array_filter($periodMonthsForDisplay)));

        $releasedRunsForMonth = PayrollRun::query()
            ->where('status', 'Released')
            ->when(!empty($periodMonthsForDisplay), fn ($q) => $q->whereIn('period_month', $periodMonthsForDisplay))
            ->where(function ($q) use ($assignment, $place) {
                if ($assignment === 'All' && $place === '') {
                    $q->whereRaw('1=1');
                    return;
                }
                $q->whereExists(function ($sub) use ($assignment, $place) {
                    $sub->selectRaw('1')
                        ->from('payroll_run_rows as prr')
                        ->join('employees as e', 'e.id', '=', 'prr.employee_id')
                        ->whereColumn('prr.payroll_run_id', 'payroll_runs.id')
                        ->when($assignment !== 'All', fn ($x) => $x->where('e.assignment_type', $assignment))
                        ->when($place !== '', fn ($x) => $x->where('e.area_place', $place));
                });
            })->orderByDesc('id')->get();

        $matchingReleasedRuns = $releasedRunsForMonth
            ->filter(function ($r) use ($month, $cutoff, $calendar) {
                $display = $this->displayMonthForRun((string) ($r->period_month ?? ''), (string) ($r->cutoff ?? ''), $calendar);
                if ($display !== $month) return false;
                if ($cutoff !== 'all' && (string) ($r->cutoff ?? '') !== $cutoff) return false;
                return true;
            })
            ->values();

        $latestRun = $matchingReleasedRuns->first();

        $runScopeIds = collect();
        if ($cutoff === 'all') {
            $runScopeIds = $matchingReleasedRuns->pluck('id')->values();
        } elseif ($latestRun) {
            $runScopeIds = collect([$latestRun->id]);
        }

        $employees = 0;
        $gross = 0.0;
        $deductions = 0.0;
        $net = 0.0;
        $unclaimedPayslips = 0;
        $payslipsNotGenerated = 0;
        $assignmentBreakdown = [];

        if ($runScopeIds->isNotEmpty()) {
            $rowQuery = PayrollRunRow::query()
                ->whereIn('payroll_run_id', $runScopeIds)
                ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place));

            $employees = (int) (clone $rowQuery)->distinct('payroll_run_rows.employee_id')->count('payroll_run_rows.employee_id');
            $gross = (float) ((clone $rowQuery)->sum('payroll_run_rows.gross') ?? 0);
            $deductions = (float) ((clone $rowQuery)->selectRaw("SUM({$deductionExpr}) as deductions_value")->value('deductions_value') ?? 0);
            $net = (float) ((clone $rowQuery)->sum('payroll_run_rows.net_pay') ?? 0);
            $expectedPayslips = (int) (clone $rowQuery)->count('payroll_run_rows.id');

            $claimedCount = PayslipClaim::query()
                ->whereIn('payroll_run_id', $runScopeIds)
                ->whereNotNull('claimed_at')
                ->join('employees as e', 'e.id', '=', 'payslip_claims.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->count('payslip_claims.id');

            $unclaimedPayslips = max(0, $expectedPayslips - (int) $claimedCount);

            $generatedCount = Payslip::query()
                ->whereIn('payroll_run_id', $runScopeIds)
                ->join('employees as e', 'e.id', '=', 'payslips.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->count('payslips.id');

            $payslipsNotGenerated = max(0, $expectedPayslips - (int) $generatedCount);

            $breakdownRows = PayrollRunRow::query()
                ->whereIn('payroll_run_id', $runScopeIds)
                ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->selectRaw("COALESCE(NULLIF(TRIM(e.assignment_type), ''), 'Unassigned') as assignment_label")
                ->selectRaw('COUNT(DISTINCT payroll_run_rows.employee_id) as employees')
                ->selectRaw('SUM(payroll_run_rows.gross) as gross')
                ->selectRaw("SUM({$deductionExpr}) as deductions")
                ->selectRaw('SUM(payroll_run_rows.net_pay) as net')
                ->groupBy('e.assignment_type')
                ->get();

            $claimedByAssignment = PayslipClaim::query()
                ->whereIn('payroll_run_id', $runScopeIds)
                ->whereNotNull('claimed_at')
                ->join('employees as e', 'e.id', '=', 'payslip_claims.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->selectRaw("COALESCE(NULLIF(TRIM(e.assignment_type), ''), 'Unassigned') as assignment_label")
                ->selectRaw('COUNT(payslip_claims.id) as claimed')
                ->groupBy('e.assignment_type')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) ($r->assignment_label ?? 'Unassigned') => (int) ($r->claimed ?? 0)]);

            $assignmentBreakdown = $breakdownRows
                ->map(function ($r) use ($claimedByAssignment) {
                    $label = (string) ($r->assignment_label ?? 'Unassigned');
                    $emp = (int) ($r->employees ?? 0);
                    $claimed = (int) ($claimedByAssignment[$label] ?? 0);
                    return [
                        'assignment' => $label,
                        'employees' => $emp,
                        'gross' => (float) ($r->gross ?? 0),
                        'deductions' => (float) ($r->deductions ?? 0),
                        'net' => (float) ($r->net ?? 0),
                        'unclaimed_payslips' => max(0, $emp - $claimed),
                    ];
                })
                ->sortBy('assignment', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        }

        $draftRunsForMonth = PayrollRun::query()
            ->where('status', 'Draft')
            ->when(!empty($periodMonthsForDisplay), fn ($q) => $q->whereIn('period_month', $periodMonthsForDisplay))
            ->where(function ($q) use ($assignment, $place) {
                if ($assignment === 'All' && $place === '') {
                    $q->whereRaw('1=1');
                    return;
                }
                $q->whereExists(function ($sub) use ($assignment, $place) {
                    $sub->selectRaw('1')
                        ->from('payroll_run_rows as prr')
                        ->join('employees as e', 'e.id', '=', 'prr.employee_id')
                        ->whereColumn('prr.payroll_run_id', 'payroll_runs.id')
                        ->when($assignment !== 'All', fn ($x) => $x->where('e.assignment_type', $assignment))
                        ->when($place !== '', fn ($x) => $x->where('e.area_place', $place));
                });
            })
            ->get();

        $pendingRuns = $draftRunsForMonth
            ->filter(function ($r) use ($month, $cutoff, $calendar) {
                $display = $this->displayMonthForRun((string) ($r->period_month ?? ''), (string) ($r->cutoff ?? ''), $calendar);
                if ($display !== $month) return false;
                if ($cutoff !== 'all' && (string) ($r->cutoff ?? '') !== $cutoff) return false;
                return true;
            })
            ->count();

        $baseMonth = $month;
        if (!preg_match('/^\d{4}-\d{2}$/', $baseMonth)) {
            $baseMonth = now()->format('Y-m');
        }
        $base = Carbon::createFromFormat('Y-m', $baseMonth)->startOfMonth();

        $trendLabels = [];
        $trendNet = [];
        $trendDed = [];
        $trendMonthsMap = [];

        // Build the 6 display months first (payday months), newest on the right.
        for ($i = 5; $i >= 0; $i--) {
            $m = $base->copy()->subMonths($i);
            $displayYm = $m->format('Y-m');
            $trendLabels[] = $m->format('M');
            $sourceForFirst = $this->sourcePeriodMonthForDisplay($displayYm, '26-10', $calendar);
            $sourceForSecond = $this->sourcePeriodMonthForDisplay($displayYm, '11-25', $calendar);

            $trendMonthsMap[$displayYm] = [
                'month' => $displayYm,
                'label' => $m->format('M'),
                'total' => [
                    'net' => 0.0,
                    'deductions' => 0.0,
                ],
                'cutoffs' => [
                    '26-10' => array_merge(
                        $this->buildTrendCutoffMeta($sourceForFirst, '26-10', '1st cutoff', $calendar),
                        ['net' => 0.0, 'deductions' => 0.0]
                    ),
                    '11-25' => array_merge(
                        $this->buildTrendCutoffMeta($sourceForSecond, '11-25', '2nd cutoff', $calendar),
                        ['net' => 0.0, 'deductions' => 0.0]
                    ),
                ],
            ];
        }

        // Include one extra source month on the left for cross-month first cutoff mapping.
        $sourceStartYm = $this->shiftYearMonth($base->copy()->subMonths(5)->format('Y-m'), -1);
        $sourceEndYm = $base->format('Y-m');

        $trendRows = PayrollRunRow::query()
            ->join('payroll_runs as pr', 'pr.id', '=', 'payroll_run_rows.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
            ->where('pr.status', 'Released')
            ->whereBetween('pr.period_month', [$sourceStartYm, $sourceEndYm])
            ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
            ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
            ->selectRaw('pr.period_month as period_month, pr.cutoff as cutoff')
            ->selectRaw('SUM(payroll_run_rows.net_pay) as net')
            ->selectRaw("SUM({$deductionExpr}) as deductions")
            ->groupBy('pr.period_month', 'pr.cutoff')
            ->get();

        foreach ($trendRows as $row) {
            $sourceYm = (string) ($row->period_month ?? '');
            $code = (string) ($row->cutoff ?? '');
            if (!in_array($code, ['26-10', '11-25'], true)) {
                continue;
            }

            $displayYm = $this->displayMonthForRun($sourceYm, $code, $calendar);
            if (!isset($trendMonthsMap[$displayYm])) {
                continue;
            }

            $meta = $this->buildTrendCutoffMeta($sourceYm, $code, $code === '26-10' ? '1st cutoff' : '2nd cutoff', $calendar);
            $trendMonthsMap[$displayYm]['cutoffs'][$code] = array_merge($meta, [
                'net' => (float) ($row->net ?? 0),
                'deductions' => (float) ($row->deductions ?? 0),
            ]);
        }

        foreach ($trendMonthsMap as &$monthEntry) {
            if ($cutoff === '26-10') {
                $monthEntry['cutoffs']['11-25']['net'] = 0.0;
                $monthEntry['cutoffs']['11-25']['deductions'] = 0.0;
            } elseif ($cutoff === '11-25') {
                $monthEntry['cutoffs']['26-10']['net'] = 0.0;
                $monthEntry['cutoffs']['26-10']['deductions'] = 0.0;
            }

            $monthEntry['total']['net'] =
                (float) ($monthEntry['cutoffs']['26-10']['net'] ?? 0) +
                (float) ($monthEntry['cutoffs']['11-25']['net'] ?? 0);
            $monthEntry['total']['deductions'] =
                (float) ($monthEntry['cutoffs']['26-10']['deductions'] ?? 0) +
                (float) ($monthEntry['cutoffs']['11-25']['deductions'] ?? 0);
        }
        unset($monthEntry);

        $trendMonths = array_values($trendMonthsMap);
        foreach ($trendMonths as $entry) {
            $trendNet[] = (float) ($entry['total']['net'] ?? 0);
            $trendDed[] = (float) ($entry['total']['deductions'] ?? 0);
        }

        return response()->json([
            'filters' => [
                'month' => $month,
                'cutoff' => $cutoff,
                'assignment' => $assignment,
                'place' => $place,
                'deduction_type' => $deductionType,
            ],
            'latest_run' => $latestRun ? [
                'id' => $latestRun->id,
                'run_code' => $latestRun->run_code,
                'period_month' => $latestRun->period_month,
                'cutoff' => $latestRun->cutoff,
                'status' => $latestRun->status,
            ] : null,
            'kpis' => [
                'employees' => $employees,
                'gross' => $gross,
                'deductions' => $deductions,
                'net' => $net,
                'unclaimed_payslips' => $unclaimedPayslips,
                'assignment_breakdown' => $assignmentBreakdown,
            ],
            'todo' => [
                'payroll_pending' => (int) $pendingRuns,
                'payslips_not_generated' => (int) $payslipsNotGenerated,
            ],
            'recent_activity' => $this->recentActivity(12),
            'trend' => [
                'labels' => $trendLabels,
                'net' => $trendNet,
                'deductions' => $trendDed,
                'months' => $trendMonths,
                'default_mode' => 'by_cutoff',
            ],
        ]);
    }

    private function normalizeCutoff(string $cutoff): string
    {
        $c = strtoupper(trim($cutoff));
        if ($c === 'A' || $c === '26-10') {
            return '26-10';
        }
        if ($c === 'B' || $c === '11-25') {
            return '11-25';
        }
        return 'all';
    }

    private function deductionExpression(string $deductionType): string
    {
        return match ($deductionType) {
            'loans' => 'COALESCE(payroll_run_rows.loan_deduction, 0)',
            'government' => 'COALESCE(payroll_run_rows.sss_ee, 0) + COALESCE(payroll_run_rows.philhealth_ee, 0) + COALESCE(payroll_run_rows.pagibig_ee, 0) + COALESCE(payroll_run_rows.tax, 0)',
            'attendance' => 'COALESCE(payroll_run_rows.attendance_deduction, 0)',
            'charges' => 'COALESCE(payroll_run_rows.charges_deduction, 0)',
            'cash_advance' => 'COALESCE(payroll_run_rows.cash_advance, 0)',
            default => 'COALESCE(payroll_run_rows.deductions_total, 0)',
        };
    }

    private function displayMonthForRun(string $periodMonth, string $cutoff, PayrollCalendarSetting $calendar): string
    {
        if ($periodMonth === '' || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return $periodMonth;
        }

        $isCrossMonth = (int) ($calendar->cutoff_b_from ?? 26) > (int) ($calendar->cutoff_b_to ?? 10);
        if ($cutoff === '26-10' && $isCrossMonth) {
            return $this->shiftYearMonth($periodMonth, 1);
        }

        return $periodMonth;
    }

    private function sourcePeriodMonthForDisplay(string $displayMonth, string $cutoff, PayrollCalendarSetting $calendar): string
    {
        $isCrossMonth = (int) ($calendar->cutoff_b_from ?? 26) > (int) ($calendar->cutoff_b_to ?? 10);
        if ($cutoff === '26-10' && $isCrossMonth) {
            return $this->shiftYearMonth($displayMonth, -1);
        }
        return $displayMonth;
    }

    private function shiftYearMonth(string $yearMonth, int $deltaMonths): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return $yearMonth;
        }
        return Carbon::createFromFormat('Y-m', $yearMonth)
            ->startOfMonth()
            ->addMonthsNoOverflow($deltaMonths)
            ->format('Y-m');
    }

    private function buildTrendCutoffMeta(
        string $periodMonth,
        string $cutoff,
        string $cutoffLabel,
        PayrollCalendarSetting $calendar
    ): array {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return [
                'code' => $cutoff,
                'label' => $cutoffLabel,
                'range_label' => '',
                'payday_label' => '',
            ];
        }

        $base = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();

        if ($cutoff === '26-10') {
            $fromDay = (int) ($calendar->cutoff_b_from ?? 26);
            $toDay = (int) ($calendar->cutoff_b_to ?? 10);
            $isCrossMonth = $fromDay > $toDay;

            $start = $base->copy()->day($fromDay);
            $end = $isCrossMonth
                ? $base->copy()->addMonthNoOverflow()->day($toDay)
                : $base->copy()->day($toDay);

            $payBase = $isCrossMonth ? $base->copy()->addMonthNoOverflow() : $base->copy();
            $payDayRaw = $calendar->pay_date_a ?? 15;
        } else {
            $fromDay = (int) ($calendar->cutoff_a_from ?? 11);
            $toDay = (int) ($calendar->cutoff_a_to ?? 25);
            $isCrossMonth = $fromDay > $toDay;

            $start = $base->copy()->day($fromDay);
            $end = $isCrossMonth
                ? $base->copy()->addMonthNoOverflow()->day($toDay)
                : $base->copy()->day($toDay);

            $payBase = $base->copy();
            $payDayRaw = $calendar->pay_date_b ?? 'EOM';
        }

        if (strtoupper((string) $payDayRaw) === 'EOM') {
            $payDate = $payBase->copy()->endOfMonth();
        } else {
            $payDate = $payBase->copy()->day((int) $payDayRaw);
        }

        return [
            'code' => $cutoff,
            'label' => $cutoffLabel,
            'range_label' => $start->format('M d') . '-' . $end->format('M d'),
            'payday_label' => $payDate->format('M d'),
        ];
    }

    private function recentActivity(int $limit = 12): array
    {
        $roleUsers = User::query()
            ->whereIn('role', ['admin', 'hr'])
            ->get(['id', 'name', 'role'])
            ->keyBy('id');
        $roleUserIds = $roleUsers->keys()->all();

        if (empty($roleUserIds)) {
            return [];
        }

        $items = collect();

        $runs = PayrollRun::query()
            ->whereIn('created_by', $roleUserIds)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'run_code', 'period_month', 'cutoff', 'created_by', 'created_at']);
        foreach ($runs as $r) {
            $u = $roleUsers->get((int) $r->created_by);
            $items->push([
                'type' => 'payroll_run_created',
                'title' => 'Created payroll run',
                'description' => ($r->run_code ?: ('RUN-' . $r->id)) . ' • ' . ($r->period_month ?: '-') . ' (' . ($r->cutoff ?: '-') . ')',
                'actor_name' => $u?->name ?: 'Unknown',
                'actor_role' => strtoupper((string) ($u?->role ?: '')),
                'occurred_at' => optional($r->created_at)?->toIso8601String(),
                'ts' => optional($r->created_at)?->timestamp ?? 0,
                'target_url' => url('/payroll-processing') . '?run_id=' . $r->id,
            ]);
        }

        $proofs = PayslipClaimProof::query()
            ->whereIn('uploaded_by_user_id', $roleUserIds)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'payroll_run_id', 'uploaded_by_user_id', 'original_name', 'created_at']);
        foreach ($proofs as $p) {
            $u = $roleUsers->get((int) $p->uploaded_by_user_id);
            $items->push([
                'type' => 'claim_proof_uploaded',
                'title' => 'Uploaded claim proof',
                'description' => (string) ($p->original_name ?: ('proof #' . $p->id)),
                'actor_name' => $u?->name ?: 'Unknown',
                'actor_role' => strtoupper((string) ($u?->role ?: '')),
                'occurred_at' => optional($p->created_at)?->toIso8601String(),
                'ts' => optional($p->created_at)?->timestamp ?? 0,
                'target_url' => $p->payroll_run_id ? (url('/payslip-claims') . '?run_id=' . $p->payroll_run_id) : url('/payslip-claims'),
            ]);
        }

        $claims = PayslipClaim::query()
            ->leftJoin('employees as e', 'e.id', '=', 'payslip_claims.employee_id')
            ->whereIn('claimed_by_user_id', $roleUserIds)
            ->whereNotNull('claimed_at')
            ->orderByDesc('payslip_claims.claimed_at')
            ->limit(40)
            ->get([
                'payslip_claims.id',
                'payslip_claims.payroll_run_id',
                'payslip_claims.claimed_by_user_id',
                'payslip_claims.employee_id',
                'payslip_claims.claimed_at',
                'e.emp_no as employee_emp_no',
            ]);
        foreach ($claims as $c) {
            $u = $roleUsers->get((int) $c->claimed_by_user_id);
            $claimTargetParams = [];
            if ($c->payroll_run_id) {
                $claimTargetParams['run_id'] = (int) $c->payroll_run_id;
            }
            if ($c->employee_id) {
                $claimTargetParams['employee_id'] = (int) $c->employee_id;
            }
            $items->push([
                'type' => 'payslip_claim_confirmed',
                'title' => 'Confirmed payslip claim',
                'description' => 'Employee ID: ' . (string) ($c->employee_emp_no ?: $c->employee_id ?: '-'),
                'actor_name' => $u?->name ?: 'Unknown',
                'actor_role' => strtoupper((string) ($u?->role ?: '')),
                'occurred_at' => optional($c->claimed_at)?->toIso8601String(),
                'ts' => optional($c->claimed_at)?->timestamp ?? 0,
                'target_url' => url('/payslip-claims')
                    . (!empty($claimTargetParams) ? ('?' . http_build_query($claimTargetParams)) : ''),
            ]);
        }

        $cases = EmployeeCase::query()
            ->whereIn('created_by', $roleUserIds)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'case_no', 'title', 'created_by', 'created_at']);
        foreach ($cases as $c) {
            $u = $roleUsers->get((int) $c->created_by);
            $items->push([
                'type' => 'employee_case_created',
                'title' => 'Created employee case',
                'description' => trim((string) ($c->case_no ?: 'CASE-' . $c->id) . ' • ' . ($c->title ?: '')),
                'actor_name' => $u?->name ?: 'Unknown',
                'actor_role' => strtoupper((string) ($u?->role ?: '')),
                'occurred_at' => optional($c->created_at)?->toIso8601String(),
                'ts' => optional($c->created_at)?->timestamp ?? 0,
                'target_url' => url('/employee-cases') . '?view_case=' . $c->id,
            ]);
        }

        $areas = EmployeeAreaHistory::query()
            ->whereIn('created_by', $roleUserIds)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'employee_id', 'area_place', 'created_by', 'created_at']);
        foreach ($areas as $a) {
            $u = $roleUsers->get((int) $a->created_by);
            $items->push([
                'type' => 'employee_area_updated',
                'title' => 'Updated employee area',
                'description' => 'Employee ID: ' . (string) ($a->employee_id ?: '-') . ' • Area: ' . (string) ($a->area_place ?: '-'),
                'actor_name' => $u?->name ?: 'Unknown',
                'actor_role' => strtoupper((string) ($u?->role ?: '')),
                'occurred_at' => optional($a->created_at)?->toIso8601String(),
                'ts' => optional($a->created_at)?->timestamp ?? 0,
                'target_url' => url('/employee-records'),
            ]);
        }

        return $items
            ->sortByDesc('ts')
            ->take($limit)
            ->map(fn ($x) => [
                'type' => $x['type'],
                'title' => $x['title'],
                'description' => $x['description'],
                'actor_name' => $x['actor_name'],
                'actor_role' => $x['actor_role'],
                'occurred_at' => $x['occurred_at'],
                'target_url' => $x['target_url'] ?? null,
            ])
            ->values()
            ->all();
    }
}
