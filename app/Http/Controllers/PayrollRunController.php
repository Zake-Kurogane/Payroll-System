<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayrollRunRowOverride;
use App\Models\Payslip;
use App\Models\PayslipAdjustment;
use App\Models\AttendanceSummary;
use App\Models\CashAdvance;
use App\Models\DeductionSchedule;
use App\Models\EmployeeLoanSchedule;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanHistory;
use App\Models\PayrollCalendarSetting;
use App\Models\PayrollRunLoanItem;
use App\Models\PayrollDeductionPolicy;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayrollRunController extends Controller
{
    public function index()
    {
        $stats = PayrollRunRow::query()
            ->selectRaw('payroll_run_id, COUNT(*) as headcount, SUM(gross) as gross, SUM(deductions_total) as deductions, SUM(net_pay) as net')
            ->groupBy('payroll_run_id')
            ->get()
            ->keyBy('payroll_run_id');

        $runs = PayrollRun::query()
            ->with(['createdBy'])
            ->orderByDesc('id')
            ->get()
            ->map(function (PayrollRun $run) use ($stats) {
                $rowStats = $stats->get($run->id);
                $headcount = (int) ($rowStats->headcount ?? 0);
                $gross = (float) ($rowStats->gross ?? 0);
                $deductions = (float) ($rowStats->deductions ?? 0);
                $net = (float) ($rowStats->net ?? 0);

                return [
                    'id' => $run->id,
                    'run_code' => $run->run_code,
                    'period_month' => $run->period_month,
                    'cutoff' => $run->cutoff,
                    'run_type' => $run->run_type ?? 'External',
                    'assignment_filter' => $run->assignment_filter,
                    'area_place_filter' => $run->area_place_filter,
                    'display_label' => $run->displayLabel(),
                    'status' => $run->status,
                    'created_by' => $run->created_by,
                    'created_by_name' => $run->createdBy?->name,
                    'created_at' => $run->created_at,
                    'locked_at' => $run->locked_at,
                    'released_at' => $run->released_at,
                    'headcount' => $headcount,
                    'gross' => $gross,
                    'deductions' => $deductions,
                    'net' => $net,
                ];
            });

        return response()->json($runs);
    }

    public function store(Request $request)
    {
        $assignmentLabels = Assignment::where('is_active', true)->pluck('label')->all();
        // 'All' removed — every run must target a specific assignment
        $allowedAssignments = array_values(array_unique($assignmentLabels));

        $areaPlaceLabels = \App\Models\AreaPlace::where('is_active', true)->pluck('label')->all();

        $validated = $request->validate([
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['required', Rule::in(['11-25', '26-10'])],
            'run_type' => ['required', Rule::in(['Internal', 'External'])],
            'assignment_filter' => ['required', Rule::in(array_merge($allowedAssignments, ['All']))],
            'area_place_filter' => [
                $request->input('assignment_filter') === 'Field' ? 'required' : 'nullable',
                'string',
                Rule::in($areaPlaceLabels),
            ],
        ]);

        // External runs must target a specific assignment (not All)
        if ($validated['run_type'] === 'External' && $validated['assignment_filter'] === 'All') {
            return response()->json(['message' => 'External runs must target a specific assignment.'], 422);
        }

        $areaFilter = $validated['area_place_filter'] ?? null;
        $existing = PayrollRun::query()
            ->where('period_month', $validated['period_month'])
            ->where('cutoff', $validated['cutoff'])
            ->where('run_type', $validated['run_type'])
            ->where('assignment_filter', $validated['assignment_filter'])
            ->when(
                $areaFilter === null,
                fn ($q) => $q->whereNull('area_place_filter'),
                fn ($q) => $q->where('area_place_filter', $areaFilter)
            )
            ->whereIn('status', ['Draft', 'Locked'])
            ->orderByDesc('id')
            ->first();

        // Prevent duplicate runs for the same period + filters. Reuse the existing run instead.
        if ($existing) {
            $existing->load(['createdBy']);
            $payload = $existing->toArray();
            $payload['created_by_name'] = $existing->createdBy?->name;
            $payload['reused'] = true;
            return response()->json($payload, 200);
        }

        $run = PayrollRun::create([
            'run_code' => 'RUN-' . strtoupper(Str::random(6)),
            'period_month' => $validated['period_month'],
            'cutoff' => $validated['cutoff'],
            'run_type' => $validated['run_type'],
            'assignment_filter' => $validated['assignment_filter'],
            'area_place_filter' => $areaFilter,
            'status' => 'Draft',
            'created_by' => Auth::id(),
        ]);

        $run->load(['createdBy']);
        $payload = $run->toArray();
        $payload['created_by_name'] = $run->createdBy?->name;
        $payload['reused'] = false;
        return response()->json($payload, 201);
    }

    public function destroy(PayrollRun $run)
    {
        if ($run->status !== 'Draft') {
            return response()->json(['message' => 'Only Draft runs can be deleted.'], 409);
        }

        DB::transaction(function () use ($run) {
            // Most related tables have FK cascade; still explicitly delete core children for safety.
            PayrollRunRow::query()->where('payroll_run_id', $run->id)->delete();
            PayrollRunLoanItem::query()->where('payroll_run_id', $run->id)->delete();
            PayrollRunRowOverride::query()->where('payroll_run_id', $run->id)->delete();
            PayslipAdjustment::query()
                ->whereHas('payslip', fn ($q) => $q->where('payroll_run_id', $run->id))
                ->delete();
            Payslip::query()->where('payroll_run_id', $run->id)->delete();
            $run->delete();
        });

        return response()->json(['message' => 'Payroll run deleted.']);
    }

    public function compute(Request $request, PayrollRun $run, PayrollCalculator $calculator)
    {
        if ($run->status !== 'Draft') {
            return response()->json(['message' => 'Run is locked or released.'], 409);
        }

        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id')
            ->map(function (PayrollRunRowOverride $o) {
                return [
                    'ot_override_on' => $o->ot_override_on,
                    'ot_override_hours' => $o->ot_override_hours,
                    'cash_advance' => $o->cash_advance,
                    'adjustments' => $o->adjustments ?? [],
                ];
            });

        $payload = $calculator->compute($run->period_month, $run->cutoff, $run->assignment_filter, $run->area_place_filter, $overrides, $run->run_type ?? 'External');

        PayrollRunRow::query()->where('payroll_run_id', $run->id)->delete();
        PayrollRunLoanItem::query()->where('payroll_run_id', $run->id)->delete();
        $rows = [];
        $loanItems = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = [
                'payroll_run_id' => $run->id,
                'employee_id' => $row['employee_id'],
                'basic_pay_cutoff' => $row['basic_pay_cutoff'],
                'allowance_cutoff' => $row['allowance_cutoff'],
                'ot_hours' => $row['ot_hours'],
                'ot_pay' => $row['ot_pay'],
                'gross' => $row['gross'],
                'sss_ee' => $row['sss_ee'],
                'philhealth_ee' => $row['philhealth_ee'],
                'pagibig_ee' => $row['pagibig_ee'],
                'tax' => $row['tax'],
                'attendance_deduction' => $row['attendance_deduction'],
                'charges_deduction' => $row['charges_deduction'] ?? 0,
                'cash_advance' => $row['cash_advance'],
                'loan_deduction' => $row['loan_deduction'] ?? 0,
                'deductions_total' => $row['deductions_total'],
                'net_pay' => $row['net_pay'],
                'sss_er' => $row['sss_er'],
                'philhealth_er' => $row['philhealth_er'],
                'pagibig_er' => $row['pagibig_er'],
                'employer_share_total' => $row['employer_share_total'],
                'pay_method' => $row['payout_method'] ?? 'CASH',
                'bank_account_masked' => $row['account_masked'],
            ];

            foreach (($row['loan_items'] ?? []) as $item) {
                if (empty($item['loan_id'])) continue;
                $loanItems[] = [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $row['employee_id'],
                    'employee_loan_id' => $item['loan_id'],
                    'employee_loan_schedule_id' => $item['schedule_id'] ?? null,
                    'scheduled_amount' => (float) ($item['scheduled_amount'] ?? 0),
                    'deducted_amount' => (float) ($item['deducted_amount'] ?? 0),
                    'balance_before' => (float) ($item['balance_before'] ?? 0),
                    'balance_after' => (float) ($item['balance_after'] ?? 0),
                    'status' => (string) ($item['status'] ?? 'scheduled'),
                ];
            }
        }
        if ($rows) {
            PayrollRunRow::query()->insert($rows);
        }
        if ($loanItems) {
            PayrollRunLoanItem::query()->insert($loanItems);
        }

        return response()->json($payload);
    }

    public function rows(PayrollRun $run, PayrollCalculator $calculator)
    {
        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $loanItems = PayrollRunLoanItem::query()
            ->with('loan')
            ->where('payroll_run_id', $run->id)
            ->get()
            ->groupBy('employee_id');

        $rawRows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->with(['employee'])
            ->get();

        $employeeIds = $rawRows->pluck('employee_id')->filter()->values()->all();
        $attendanceAgg = collect();
        if (!empty($employeeIds) && !empty($run->period_month) && !empty($run->cutoff)) {
            [$start, $end] = $calculator->cutoffRangePublic((string) $run->period_month, (string) $run->cutoff);

            $aggQuery = AttendanceRecord::query()
                ->selectRaw("
                    employee_id,
                    SUM(CASE WHEN status IN ('Present','Late','Half-day') THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status IN ('Absent','Unpaid Leave','LOA') THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status IN ('Leave','Paid Leave') THEN 1 ELSE 0 END) as leave_days
                ")
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->groupBy('employee_id');

            $attendanceAgg = $aggQuery->get()->keyBy('employee_id');
        }

        $rows = $rawRows
            ->map(function (PayrollRunRow $r) use ($overrides, $loanItems, $attendanceAgg) {
                $emp = $r->employee;
                $name = $emp
                    ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                    : '';
                $summary = $attendanceAgg->get($r->employee_id);
                $ov = null;
                $rowOverride = $overrides->get($r->employee_id);
                if ($rowOverride) {
                    $ov = [
                    'ot_override_on' => $rowOverride->ot_override_on,
                    'ot_override_hours' => $rowOverride->ot_override_hours,
                    'cash_advance' => $rowOverride->cash_advance,
                    'adjustments' => $rowOverride->adjustments ?? [],
                ];
                }

                return [
                    'employee_id' => $r->employee_id,
                    'emp_no' => $emp?->emp_no,
                    'name' => $name,
                    'department' => $emp?->department,
                    'employment_type' => $emp?->employment_type,
                    'assignment' => $emp?->assignment_type,
                    'area_place' => $emp?->area_place,
                    'external_area' => $emp?->external_area,
                    'present_days' => (float) ($summary?->present_days ?? 0),
                    'absent_days' => (float) ($summary?->absent_days ?? 0),
                    'leave_days' => (float) ($summary?->leave_days ?? 0),
                    'daily_rate' => round(((float) ($emp?->basic_pay ?? 0)) / 26, 2),
                    'basic_pay_cutoff' => (float) $r->basic_pay_cutoff,
                    'allowance_cutoff' => (float) $r->allowance_cutoff,
                    'ot_hours' => (float) $r->ot_hours,
                    'ot_pay' => (float) $r->ot_pay,
                    'gross' => (float) $r->gross,
                    'attendance_deduction' => (float) $r->attendance_deduction,
                    'cash_advance' => (float) $r->cash_advance,
                    'loan_deduction' => (float) $r->loan_deduction,
                    'sss_ee' => (float) $r->sss_ee,
                    'philhealth_ee' => (float) $r->philhealth_ee,
                    'pagibig_ee' => (float) $r->pagibig_ee,
                    'tax' => (float) $r->tax,
                    'sss_er' => (float) $r->sss_er,
                    'philhealth_er' => (float) $r->philhealth_er,
                    'pagibig_er' => (float) $r->pagibig_er,
                    'deductions_total' => (float) $r->deductions_total,
                    'net_pay' => (float) $r->net_pay,
                    'employer_share_total' => (float) $r->employer_share_total,
                    'charges_deduction' => (float) ($r->charges_deduction ?? 0),
                    'pay_method' => $r->pay_method,
                    'bank_account_masked' => $r->bank_account_masked,
                    'override' => $ov,
                    'loan_items' => $loanItems->get($r->employee_id, collect())->map(fn ($i) => [
                        'loan_id' => $i->employee_loan_id,
                        'loan_type' => $i->loan?->loan_type,
                        'schedule_id' => $i->employee_loan_schedule_id,
                        'scheduled_amount' => (float) $i->scheduled_amount,
                        'deducted_amount' => (float) $i->deducted_amount,
                        'balance_before' => (float) $i->balance_before,
                        'balance_after' => (float) $i->balance_after,
                        'status' => $i->status,
                    ])->values(),
                ];
            });

        return response()->json($rows);
    }

    public function fieldAreaAllocations(PayrollRun $run, PayrollCalculator $calculator)
    {
        $periodMonth = (string) ($run->period_month ?? '');
        $cutoff = (string) ($run->cutoff ?? '');
        if ($periodMonth === '' || $cutoff === '') {
            return response()->json(['message' => 'Run is missing period_month/cutoff.'], 409);
        }

        [$start, $end] = $calculator->cutoffRangePublic($periodMonth, $cutoff);

        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        $workMonSat = (bool) ($calendar->work_mon_sat ?? true);

        $runRows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->with(['employee'])
            ->get();

        $employees = [];
        foreach ($runRows as $r) {
            $emp = $r->employee;
            if (!$emp) continue;
            if (strcasecmp((string) ($emp->assignment_type ?? ''), 'Field') !== 0) continue;

            $name = trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''));
            $basePay = (float) ($r->basic_pay_cutoff ?? 0) + (float) ($r->allowance_cutoff ?? 0);

            $employees[(int) $emp->id] = [
                'employee' => $emp,
                'employee_id' => (int) $emp->id,
                'emp_no' => (string) ($emp->emp_no ?? ''),
                'name' => $name,
                'base_pay' => $basePay,
            ];
        }

        $empIds = array_keys($employees);
        if (empty($empIds)) {
            return response()->json([
                'range' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                    'work_mon_sat' => $workMonSat,
                ],
                'employees' => [],
                'area_totals' => [],
            ]);
        }

        $q = AttendanceRecord::query()
            ->whereIn('employee_id', $empIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        if ($workMonSat) {
            $q->whereRaw("DAYOFWEEK(`date`) != 1");
        } else {
            $q->whereRaw("DAYOFWEEK(`date`) NOT IN (1,7)");
        }

        $records = $q
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get(['employee_id', 'date', 'status', 'area_place']);

        $byEmp = $records->groupBy('employee_id');
        $areaTotals = [];
        $areaEmployees = [];

        foreach ($employees as $empId => $meta) {
            $emp = $meta['employee'];
            $basePay = (float) ($meta['base_pay'] ?? 0);

            $rows = $byEmp->get($empId, collect());
            $items = [];
            $totalUnits = 0.0;

            foreach ($rows as $rec) {
                $units = $this->paidUnitsForStatus((string) ($rec->status ?? ''));
                if ($units <= 0) continue;

                $date = $rec->date instanceof \DateTimeInterface
                    ? $rec->date->format('Y-m-d')
                    : Carbon::parse((string) $rec->date)->format('Y-m-d');

                $area = trim((string) ($rec->area_place ?? ''));
                if ($area === '') {
                    $area = trim((string) ($emp->resolveAreaForDate($date) ?? ''));
                }
                if ($area === '') $area = '—';

                $items[] = ['area_place' => $area, 'date' => $date, 'units' => $units];
                $totalUnits += $units;
            }

            $areas = [];
            foreach ($items as $it) {
                $area = $it['area_place'];
                if (!isset($areas[$area])) {
                    $areas[$area] = ['area_place' => $area, 'paid_units' => 0.0, 'dates' => []];
                }
                $areas[$area]['paid_units'] += (float) $it['units'];
                $areas[$area]['dates'][] = (string) $it['date'];
            }

            $dailyRate = ($totalUnits > 0 && $basePay > 0) ? ($basePay / $totalUnits) : 0.0;

            $allocs = [];
            foreach ($areas as $area => $a) {
                $paidUnits = (float) ($a['paid_units'] ?? 0);
                $amount = $dailyRate > 0 ? ($dailyRate * $paidUnits) : 0.0;

                $allocs[] = [
                    'area_place' => $area,
                    'paid_units' => $paidUnits,
                    'amount' => round($amount, 2),
                    'date_ranges' => $this->compressDateRanges((array) ($a['dates'] ?? [])),
                ];

                if (!isset($areaTotals[$area])) {
                    $areaTotals[$area] = ['area_place' => $area, 'amount' => 0.0, 'paid_units' => 0.0];
                }
                $areaTotals[$area]['amount'] += $amount;
                $areaTotals[$area]['paid_units'] += $paidUnits;

                if (!isset($areaEmployees[$area])) {
                    $areaEmployees[$area] = [];
                }
                $areaEmployees[$area][] = [
                    'employee_id' => (int) $meta['employee_id'],
                    'emp_no' => (string) $meta['emp_no'],
                    'name' => (string) $meta['name'],
                    'daily_rate' => round($dailyRate, 2),
                    'paid_units' => $paidUnits, // days (can be 0.5)
                    'date_ranges' => $this->compressDateRanges((array) ($a['dates'] ?? [])),
                    'allocated_amount' => round($amount, 2),
                ];
            }
        }

        $areaTotalsList = array_values(array_map(function ($a) {
            $a['amount'] = round((float) ($a['amount'] ?? 0), 2);
            $a['paid_units'] = (float) ($a['paid_units'] ?? 0);
            return $a;
        }, $areaTotals));
        usort($areaTotalsList, fn ($a, $b) => strcmp((string) $a['area_place'], (string) $b['area_place']));

        $areasList = [];
        foreach ($areaEmployees as $area => $rows) {
            usort($rows, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
            $tot = collect($areaTotalsList)->firstWhere('area_place', $area);
            $areasList[] = [
                'area_place' => (string) $area,
                'rows' => $rows,
                'total_paid_units' => (float) ($tot['paid_units'] ?? 0),
                'total_allocated_amount' => (float) ($tot['amount'] ?? 0),
            ];
        }
        usort($areasList, fn ($a, $b) => strcmp((string) $a['area_place'], (string) $b['area_place']));

        return response()->json([
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'work_mon_sat' => $workMonSat,
            ],
            'areas' => $areasList,
            'area_totals' => $areaTotalsList,
        ]);
    }

    public function saveOverride(Request $request, PayrollRun $run, PayrollCalculator $calculator)
    {
        if ($run->status !== 'Draft') {
            return response()->json(['message' => 'Run is locked or released.'], 409);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'ot_override_on' => ['required', 'boolean'],
            'ot_override_hours' => ['nullable', 'numeric', 'min:0'],
            'cash_advance' => ['nullable', 'numeric', 'min:0'],
            'adjustments' => ['nullable', 'array'],
            'adjustments.*.type' => ['required_with:adjustments', Rule::in(['earning', 'deduction'])],
            'adjustments.*.name' => ['nullable', 'string', 'max:255'],
            'adjustments.*.amount' => ['required_with:adjustments', 'numeric', 'min:0'],
        ]);

        PayrollRunRowOverride::updateOrCreate(
            [
                'payroll_run_id' => $run->id,
                'employee_id' => $validated['employee_id'],
            ],
            [
                'ot_override_on' => $validated['ot_override_on'],
                'ot_override_hours' => $validated['ot_override_hours'] ?? null,
                'cash_advance' => $validated['cash_advance'] ?? null,
                'adjustments' => $validated['adjustments'] ?? [],
            ]
        );

        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id')
            ->map(function (PayrollRunRowOverride $o) {
                return [
                    'ot_override_on' => $o->ot_override_on,
                    'ot_override_hours' => $o->ot_override_hours,
                    'cash_advance' => $o->cash_advance,
                    'adjustments' => $o->adjustments ?? [],
                ];
            });

        $emp = \App\Models\Employee::findOrFail($validated['employee_id']);
        [$start, $end] = $calculator->cutoffRangePublic($run->period_month, $run->cutoff);
        $timekeeping = \App\Models\TimekeepingRule::query()->first() ?? \App\Models\TimekeepingRule::create([]);
        $proration = \App\Models\SalaryProrationRule::query()->first() ?? \App\Models\SalaryProrationRule::create([]);
        $overtime = \App\Models\OvertimeRule::query()->first() ?? \App\Models\OvertimeRule::create([]);
        $statutory = \App\Models\StatutorySetup::query()->first() ?? \App\Models\StatutorySetup::create([]);
        $wtPolicy = \App\Models\WithholdingTaxPolicy::query()->first() ?? \App\Models\WithholdingTaxPolicy::create([]);
        $wtBrackets = \App\Models\WithholdingTaxBracket::query()->orderBy('sort_order')->get();
        $cashPolicy = \App\Models\CashAdvancePolicy::query()->first();
        $dedPolicy = PayrollDeductionPolicy::query()->first() ?? PayrollDeductionPolicy::create([]);

        $summaries = AttendanceSummary::query()
            ->where('employee_id', $emp->id)
            ->where('period_month', $run->period_month)
            ->where('cutoff', $run->cutoff)
            ->get()
            ->keyBy('employee_id');
        $cashAdvances = \App\Models\CashAdvance::query()
            ->where('employee_id', $emp->id)
            ->where('status', 'Active')
            ->get()
            ->groupBy('employee_id');
        $loanSchedules = EmployeeLoanSchedule::query()
            ->where('employee_id', $emp->id)
            ->where('due_month', $run->period_month)
            ->where('due_cutoff', $run->cutoff)
            ->where('status', 'scheduled')
            ->whereHas('loan', function ($q) {
                $q->where('status', 'active')
                  ->where('auto_deduct', true);
            })
            ->get()
            ->groupBy('employee_id');

        $row = $calculator->computeForEmployee($emp, $start, $end, $run->cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $dedPolicy, $summaries, $cashAdvances, $loanSchedules, $run->run_type ?? 'External');

        PayrollRunRow::updateOrCreate(
            [
                'payroll_run_id' => $run->id,
                'employee_id' => $emp->id,
            ],
            [
                'basic_pay_cutoff' => $row['basic_pay_cutoff'],
                'allowance_cutoff' => $row['allowance_cutoff'],
                'ot_hours' => $row['ot_hours'],
                'ot_pay' => $row['ot_pay'],
                'gross' => $row['gross'],
                'sss_ee' => $row['sss_ee'],
                'philhealth_ee' => $row['philhealth_ee'],
                'pagibig_ee' => $row['pagibig_ee'],
                'tax' => $row['tax'],
                'attendance_deduction' => $row['attendance_deduction'],
                'charges_deduction' => $row['charges_deduction'] ?? 0,
                'cash_advance' => $row['cash_advance'],
                'loan_deduction' => $row['loan_deduction'] ?? 0,
                'deductions_total' => $row['deductions_total'],
                'net_pay' => $row['net_pay'],
                'sss_er' => $row['sss_er'],
                'philhealth_er' => $row['philhealth_er'],
                'pagibig_er' => $row['pagibig_er'],
                'employer_share_total' => $row['employer_share_total'],
                'pay_method' => $row['payout_method'] ?? 'CASH',
                'bank_account_masked' => $row['account_masked'],
            ]
        );

        PayrollRunLoanItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $emp->id)
            ->delete();

        $loanItems = [];
        foreach (($row['loan_items'] ?? []) as $item) {
            if (empty($item['loan_id'])) continue;
            $loanItems[] = [
                'payroll_run_id' => $run->id,
                'employee_id' => $emp->id,
                'employee_loan_id' => $item['loan_id'],
                'employee_loan_schedule_id' => $item['schedule_id'] ?? null,
                'scheduled_amount' => (float) ($item['scheduled_amount'] ?? 0),
                'deducted_amount' => (float) ($item['deducted_amount'] ?? 0),
                'balance_before' => (float) ($item['balance_before'] ?? 0),
                'balance_after' => (float) ($item['balance_after'] ?? 0),
                'status' => (string) ($item['status'] ?? 'scheduled'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($loanItems) {
            PayrollRunLoanItem::query()->insert($loanItems);
        }

        return response()->json($row);
    }

    

    public function lock(PayrollRun $run)
    {
        if ($run->status !== 'Draft') {
            return response()->json(['message' => 'Run already locked/released.'], 409);
        }
        $run->status = 'Locked';
        $run->locked_at = now();
        $run->save();

        // Mark all scheduled charge/shortage deductions for this period as applied
        $employeeIds = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->pluck('employee_id')
            ->all();

        if ($employeeIds) {
            $dedPolicy = PayrollDeductionPolicy::query()->first() ?? PayrollDeductionPolicy::create([]);

            if (!(bool) ($dedPolicy->carry_forward_unpaid_charges ?? true)) {
                // Legacy behavior: apply all scheduled charge/shortage lines as-is.
                DeductionSchedule::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('due_month', $run->period_month)
                    ->where('due_cutoff', $run->cutoff)
                    ->where('status', 'scheduled')
                    ->update([
                        'status' => 'applied',
                        'payroll_run_id' => $run->id,
                        'applied_at' => now(),
                    ]);
            } else {
                // Apply charge/shortage schedules up to the computed deduction amount.
                // Any unpaid remainder is carried forward to the next cutoff.
                $chargeByEmp = PayrollRunRow::query()
                    ->where('payroll_run_id', $run->id)
                    ->whereIn('employee_id', $employeeIds)
                    ->get(['employee_id', 'charges_deduction'])
                    ->keyBy('employee_id');

                $schedByEmp = DeductionSchedule::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('due_month', $run->period_month)
                    ->where('due_cutoff', $run->cutoff)
                    ->where('status', 'scheduled')
                    ->orderBy('employee_id')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('employee_id');

                [$nextMonth, $nextCutoff] = $this->nextCutoff($run->period_month, $run->cutoff);

                foreach ($employeeIds as $empId) {
                    $available = (float) ($chargeByEmp->get($empId)?->charges_deduction ?? 0);
                    $lines = $schedByEmp->get($empId, collect());
                    foreach ($lines as $line) {
                        $scheduled = (float) $line->amount;
                        $deducted = min($scheduled, max(0.0, $available));
                        $available = max(0.0, $available - $deducted);

                        $remaining = round($scheduled - $deducted, 2);

                        // Mark this cutoff's line as applied for the amount actually deducted.
                        $line->update([
                            'amount' => $deducted,
                            'status' => 'applied',
                            'payroll_run_id' => $run->id,
                            'applied_at' => now(),
                        ]);

                        // Carry forward any unpaid remainder to next cutoff.
                        if ($remaining > 0) {
                            DeductionSchedule::query()->create([
                                'deduction_case_id' => $line->deduction_case_id,
                                'employee_id' => $line->employee_id,
                                'due_month' => $nextMonth,
                                'due_cutoff' => $nextCutoff,
                                'amount' => $remaining,
                                'status' => 'scheduled',
                            ]);
                        }
                    }
                }
            }

            EmployeeLoanHistory::query()
                ->where('payroll_run_id', $run->id)
                ->delete();

            $loanItems = PayrollRunLoanItem::query()
                ->where('payroll_run_id', $run->id)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_loan_id');

            foreach ($loanItems as $loanId => $items) {
                $loan = EmployeeLoan::query()->find($loanId);
                if (!$loan) continue;

                $amountDeducted = (float) ($loan->amount_deducted ?? 0);
                $balanceRemaining = (float) ($loan->balance_remaining ?? 0);

                foreach ($items as $item) {
                    $scheduled = (float) $item->scheduled_amount;
                    $deducted = (float) $item->deducted_amount;
                    $balanceBefore = (float) $item->balance_before;
                    $balanceAfter = (float) $item->balance_after;

                    $status = $item->status;
                    if ($deducted <= 0) $status = 'skipped';
                    elseif ($deducted < $scheduled) $status = 'partial';
                    else $status = 'applied';

                    if ($item->employee_loan_schedule_id) {
                        EmployeeLoanSchedule::query()
                            ->where('id', $item->employee_loan_schedule_id)
                            ->update([
                                'status' => $status,
                                'payroll_run_id' => $run->id,
                                'applied_at' => now(),
                            ]);

                        if ($loan->carry_forward && $deducted < $scheduled) {
                            $remaining = round($scheduled - $deducted, 2);
                            if ($remaining > 0) {
                                [$nextMonth, $nextCutoff] = $this->nextCutoff($run->period_month, $run->cutoff);
                                EmployeeLoanSchedule::query()->create([
                                    'employee_loan_id' => $loan->id,
                                    'employee_id' => $loan->employee_id,
                                    'due_month' => $nextMonth,
                                    'due_cutoff' => $nextCutoff,
                                    'amount' => $remaining,
                                    'status' => 'scheduled',
                                ]);
                            }
                        }
                    }

                    EmployeeLoanHistory::query()->create([
                        'employee_loan_id' => $loan->id,
                        'employee_id' => $loan->employee_id,
                        'payroll_run_id' => $run->id,
                        'period_month' => $run->period_month,
                        'cutoff' => $run->cutoff,
                        'scheduled_amount' => $scheduled,
                        'deducted_amount' => $deducted,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'status' => $status,
                        'posted_by' => $run->created_by,
                        'posted_at' => now(),
                    ]);

                    $amountDeducted += $deducted;
                    $balanceRemaining = max(0.0, $balanceRemaining - $deducted);
                }

                $loan->amount_deducted = $amountDeducted;
                $loan->balance_remaining = $balanceRemaining;
                if ($loan->stop_on_zero && $balanceRemaining <= 0) {
                    $loan->status = 'completed';
                }
                $loan->save();
            }
        }

        // Apply Cash Advance deductions to balances (one active CA per employee).
        $caByEmployee = CashAdvance::query()
            ->whereIn('employee_id', $employeeIds ?: [0])
            ->whereRaw("LOWER(TRIM(COALESCE(status,''))) = 'active'")
            ->get()
            ->keyBy('employee_id');

        if ($employeeIds) {
            $rows = PayrollRunRow::query()
                ->where('payroll_run_id', $run->id)
                ->whereIn('employee_id', $employeeIds)
                ->get(['employee_id', 'cash_advance']);

            foreach ($rows as $r) {
                $deducted = (float) ($r->cash_advance ?? 0);
                if ($deducted <= 0) continue;
                $ca = $caByEmployee->get($r->employee_id);
                if (!$ca) continue;

                $before = (float) ($ca->balance_remaining ?? $ca->amount ?? 0);
                $applied = min($deducted, max(0.0, $before));
                $after = max(0.0, $before - $applied);

                $ca->amount_deducted = (float) ($ca->amount_deducted ?? 0) + $applied;
                $ca->balance_remaining = $after;
                if ($after <= 0.0) {
                    $ca->status = 'Completed';
                }
                $ca->save();
            }
        }

        $this->generatePayslipsForRun($run);
        return response()->json($run);
    }

    public function unlock(Request $request, PayrollRun $run)
    {
        if ($run->status === 'Draft') {
            return response()->json(['message' => 'Run already in Draft.'], 409);
        }
        if ($run->status === 'Released') {
            return response()->json(['message' => 'Released runs cannot be unlocked.'], 409);
        }
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $user = $request->user();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid password.'], 422);
        }
        $run->status = 'Draft';
        $run->locked_at = null;
        $run->released_at = null;
        $run->save();
        return response()->json($run);
    }

    public function release(PayrollRun $run)
    {
        if ($run->status !== 'Locked') {
            return response()->json(['message' => 'Run must be locked before release.'], 409);
        }
        $run->status = 'Released';
        $run->released_at = now();
        $run->save();
        return response()->json($run);
    }

    private function generatePayslipsForRun(PayrollRun $run): void
    {
        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $empMap = Employee::query()
            ->whereIn('id', $rows->pluck('employee_id')->filter()->values())
            ->get()
            ->keyBy('id');

        $existing = Payslip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('employee_id', $rows->pluck('employee_id')->filter()->values())
            ->get()
            ->keyBy('employee_id');

        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $payslipIds = [];
        $adjRows = [];

        DB::transaction(function () use ($rows, $overrides, $run, $empMap, $existing, &$payslipIds, &$adjRows) {
            foreach ($rows as $row) {
                $emp = $empMap->get($row->employee_id);
                $baseNo = 'PS-' . ($run->run_code ?: ('RUN-' . $run->id)) . '-' . ($emp?->emp_no ?: $row->employee_id);
                $payslip = $existing->get($row->employee_id);

                if (!$payslip) {
                    $payslipNo = $this->uniquePayslipNo($baseNo);
                    $payslip = Payslip::create([
                        'payroll_run_id' => $run->id,
                        'employee_id' => $row->employee_id,
                        'payslip_no' => $payslipNo,
                        'generated_at' => now(),
                        'release_status' => 'Draft',
                        'delivery_status' => 'Not Sent',
                    ]);
                    $existing->put($row->employee_id, $payslip);
                } elseif (!$payslip->generated_at) {
                    $payslip->generated_at = now();
                    $payslip->save();
                }

                $payslipIds[] = $payslip->id;

                $override = $overrides->get($row->employee_id);
                $adjustments = $override?->adjustments ?? [];
                if (is_string($adjustments)) {
                    $adjustments = json_decode($adjustments, true) ?: [];
                }

                foreach ($adjustments as $adj) {
                    $adjRows[] = [
                        'payslip_id' => $payslip->id,
                        'type' => ($adj['type'] ?? 'earning') === 'deduction' ? 'deduction' : 'earning',
                        'name' => (string) ($adj['name'] ?? 'Adjustment'),
                        'amount' => (float) ($adj['amount'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($payslipIds) {
                PayslipAdjustment::query()->whereIn('payslip_id', $payslipIds)->delete();
            }
            if ($adjRows) {
                PayslipAdjustment::query()->insert($adjRows);
            }
        });
    }

    private function paidUnitsForStatus(string $status): float
    {
        $s = trim($status);
        if ($s === '') return 0.0;
        if (in_array($s, ['Present', 'Late', 'Paid Leave', 'Holiday', 'Day Off'], true)) return 1.0;
        if ($s === 'Half-day') return 0.5;
        return 0.0;
    }

    private function compressDateRanges(array $dates): array
    {
        $dates = array_values(array_filter(array_map(fn ($d) => (string) $d, $dates), fn ($d) => $d !== ''));
        sort($dates);
        $dates = array_values(array_unique($dates));
        if (empty($dates)) return [];

        $ranges = [];
        $start = $dates[0];
        $prev = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $cur = $dates[$i];
            $prevDate = Carbon::parse($prev);
            $curDate = Carbon::parse($cur);
            if ($prevDate->copy()->addDay()->toDateString() === $curDate->toDateString()) {
                $prev = $cur;
                continue;
            }
            $ranges[] = ($start === $prev) ? $start : ($start . ' to ' . $prev);
            $start = $cur;
            $prev = $cur;
        }
        $ranges[] = ($start === $prev) ? $start : ($start . ' to ' . $prev);
        return $ranges;
    }

    private function uniquePayslipNo(string $base): string
    {
        $candidate = $base;
        $i = 1;
        while (Payslip::query()->where('payslip_no', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private function nextCutoff(string $month, string $cutoff): array
    {
        if ($cutoff === '11-25') {
            return [$month, '26-10'];
        }
        [$y, $m] = array_map('intval', explode('-', $month));
        $m++;
        if ($m > 12) { $m = 1; $y++; }
        return [sprintf('%04d-%02d', $y, $m), '11-25'];
    }
}
