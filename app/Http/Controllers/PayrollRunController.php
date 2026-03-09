<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayrollRunRowOverride;
use App\Models\Payslip;
use App\Models\PayslipAdjustment;
use App\Models\AttendanceSummary;
use App\Models\DeductionSchedule;
use App\Services\Payroll\PayrollCalculator;
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

        $run = PayrollRun::create([
            'run_code' => 'RUN-' . strtoupper(Str::random(6)),
            'period_month' => $validated['period_month'],
            'cutoff' => $validated['cutoff'],
            'run_type' => $validated['run_type'],
            'assignment_filter' => $validated['assignment_filter'],
            'area_place_filter' => $validated['area_place_filter'] ?? null,
            'status' => 'Draft',
            'created_by' => Auth::id(),
        ]);

        $run->load(['createdBy']);
        $payload = $run->toArray();
        $payload['created_by_name'] = $run->createdBy?->name;
        return response()->json($payload, 201);
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
        $rows = [];
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
                'cash_advance' => $row['cash_advance'],
                'deductions_total' => $row['deductions_total'],
                'net_pay' => $row['net_pay'],
                'sss_er' => $row['sss_er'],
                'philhealth_er' => $row['philhealth_er'],
                'pagibig_er' => $row['pagibig_er'],
                'employer_share_total' => $row['employer_share_total'],
                'pay_method' => $row['payout_method'] ?? 'CASH',
                'bank_account_masked' => $row['account_masked'],
            ];
        }
        if ($rows) {
            PayrollRunRow::query()->insert($rows);
        }

        return response()->json($payload);
    }

    public function rows(PayrollRun $run)
    {
        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->with(['employee'])
            ->get()
            ->map(function (PayrollRunRow $r) use ($overrides) {
                $emp = $r->employee;
                $name = $emp
                    ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                    : '';
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
                    'assignment' => $emp?->assignment_type,
                    'area_place' => $emp?->area_place,
                    'basic_pay_cutoff' => (float) $r->basic_pay_cutoff,
                    'allowance_cutoff' => (float) $r->allowance_cutoff,
                    'ot_hours' => (float) $r->ot_hours,
                    'ot_pay' => (float) $r->ot_pay,
                    'gross' => (float) $r->gross,
                    'attendance_deduction' => (float) $r->attendance_deduction,
                    'cash_advance' => (float) $r->cash_advance,
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
                    'pay_method' => $r->pay_method,
                    'bank_account_masked' => $r->bank_account_masked,
                    'override' => $ov,
                ];
            });

        return response()->json($rows);
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

        $row = $calculator->computeForEmployee($emp, $start, $end, $run->cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $summaries, $cashAdvances, $run->run_type ?? 'External');

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
                'cash_advance' => $row['cash_advance'],
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
}
