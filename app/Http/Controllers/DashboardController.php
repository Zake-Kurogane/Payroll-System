<?php

namespace App\Http\Controllers;

use App\Models\AreaPlace;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\PayslipClaim;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
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
        ]);

        $latestProcessedGlobal = PayrollRun::query()
            ->where('status', 'Released')
            ->orderByDesc('id')
            ->first();

        $month = (string) ($validated['month'] ?? ($latestProcessedGlobal?->period_month ?? now()->format('Y-m')));
        $cutoff = $this->normalizeCutoff((string) ($validated['cutoff'] ?? ($latestProcessedGlobal?->cutoff ?? 'all')));
        $assignment = trim((string) ($validated['assignment'] ?? 'All'));
        if ($assignment === '') {
            $assignment = 'All';
        }
        $place = trim((string) ($validated['place'] ?? ''));

        $latestRun = PayrollRun::query()
            ->where('status', 'Released')
            ->when($month !== '', fn ($q) => $q->where('period_month', $month))
            ->when($cutoff !== 'all', fn ($q) => $q->where('cutoff', $cutoff))
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
            ->orderByDesc('id')
            ->first();

        $employees = 0;
        $gross = 0.0;
        $deductions = 0.0;
        $net = 0.0;
        $unclaimedPayslips = 0;
        $payslipsNotGenerated = 0;
        $assignmentBreakdown = [];

        if ($latestRun) {
            $rowQuery = PayrollRunRow::query()
                ->where('payroll_run_id', $latestRun->id)
                ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place));

            $employees = (int) (clone $rowQuery)->distinct('payroll_run_rows.employee_id')->count('payroll_run_rows.employee_id');
            $gross = (float) ((clone $rowQuery)->sum('payroll_run_rows.gross') ?? 0);
            $deductions = (float) ((clone $rowQuery)->sum('payroll_run_rows.deductions_total') ?? 0);
            $net = (float) ((clone $rowQuery)->sum('payroll_run_rows.net_pay') ?? 0);

            $claimedCount = PayslipClaim::query()
                ->where('payroll_run_id', $latestRun->id)
                ->whereNotNull('claimed_at')
                ->join('employees as e', 'e.id', '=', 'payslip_claims.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->distinct('payslip_claims.employee_id')
                ->count('payslip_claims.employee_id');

            $unclaimedPayslips = max(0, $employees - (int) $claimedCount);

            $generatedCount = Payslip::query()
                ->where('payroll_run_id', $latestRun->id)
                ->join('employees as e', 'e.id', '=', 'payslips.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->distinct('payslips.employee_id')
                ->count('payslips.employee_id');

            $payslipsNotGenerated = max(0, $employees - (int) $generatedCount);

            $breakdownRows = PayrollRunRow::query()
                ->where('payroll_run_id', $latestRun->id)
                ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->selectRaw("COALESCE(NULLIF(TRIM(e.assignment_type), ''), 'Unassigned') as assignment_label")
                ->selectRaw('COUNT(DISTINCT payroll_run_rows.employee_id) as employees')
                ->selectRaw('SUM(payroll_run_rows.gross) as gross')
                ->selectRaw('SUM(payroll_run_rows.deductions_total) as deductions')
                ->selectRaw('SUM(payroll_run_rows.net_pay) as net')
                ->groupBy('e.assignment_type')
                ->get();

            $claimedByAssignment = PayslipClaim::query()
                ->where('payroll_run_id', $latestRun->id)
                ->whereNotNull('claimed_at')
                ->join('employees as e', 'e.id', '=', 'payslip_claims.employee_id')
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place))
                ->selectRaw("COALESCE(NULLIF(TRIM(e.assignment_type), ''), 'Unassigned') as assignment_label")
                ->selectRaw('COUNT(DISTINCT payslip_claims.employee_id) as claimed')
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

        $pendingRuns = PayrollRun::query()
            ->where('status', 'Draft')
            ->when($month !== '', fn ($q) => $q->where('period_month', $month))
            ->when($cutoff !== 'all', fn ($q) => $q->where('cutoff', $cutoff))
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
            ->count();

        $baseMonth = $month;
        if (!preg_match('/^\d{4}-\d{2}$/', $baseMonth)) {
            $baseMonth = now()->format('Y-m');
        }
        $base = Carbon::createFromFormat('Y-m', $baseMonth)->startOfMonth();

        $trendLabels = [];
        $trendNet = [];
        $trendDed = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $base->copy()->subMonths($i);
            $ym = $m->format('Y-m');
            $trendLabels[] = $m->format('M');

            $trendQuery = PayrollRunRow::query()
                ->join('payroll_runs as pr', 'pr.id', '=', 'payroll_run_rows.payroll_run_id')
                ->join('employees as e', 'e.id', '=', 'payroll_run_rows.employee_id')
                ->where('pr.status', 'Released')
                ->where('pr.period_month', $ym)
                ->when($cutoff !== 'all', fn ($q) => $q->where('pr.cutoff', $cutoff))
                ->when($assignment !== 'All', fn ($q) => $q->where('e.assignment_type', $assignment))
                ->when($place !== '', fn ($q) => $q->where('e.area_place', $place));

            $trendNet[] = (float) ((clone $trendQuery)->sum('payroll_run_rows.net_pay') ?? 0);
            $trendDed[] = (float) ((clone $trendQuery)->sum('payroll_run_rows.deductions_total') ?? 0);
        }

        return response()->json([
            'filters' => [
                'month' => $month,
                'cutoff' => $cutoff,
                'assignment' => $assignment,
                'place' => $place,
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
            'trend' => [
                'labels' => $trendLabels,
                'net' => $trendNet,
                'deductions' => $trendDed,
            ],
        ]);
    }

    private function normalizeCutoff(string $cutoff): string
    {
        $c = strtoupper(trim($cutoff));
        if ($c === 'A' || $c === '11-25') {
            return '11-25';
        }
        if ($c === 'B' || $c === '26-10') {
            return '26-10';
        }
        return 'all';
    }
}
