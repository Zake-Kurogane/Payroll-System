<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCode;
use App\Models\AttendanceCodeSetting;
use App\Models\CompanySetup;
use App\Models\CashAdvance;
use App\Models\CashAdvancePolicy;
use App\Models\OvertimeRule;
use App\Models\PayrollCalendarSetting;
use App\Models\SalaryProrationRule;
use App\Models\StatutorySetup;
use App\Models\TimekeepingRule;
use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxPolicy;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function getCompanySetup()
    {
        $row = CompanySetup::query()->first();
        if (!$row) {
            $row = CompanySetup::create([]);
        }
        return response()->json($row);
    }

    public function saveCompanySetup(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_tin' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_rdo' => ['nullable', 'string', 'max:255'],
            'company_contact' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'payroll_frequency' => ['required', Rule::in(['semi_monthly', 'weekly', 'monthly'])],
            'currency' => ['required', 'string', 'max:10'],
        ]);

        $row = CompanySetup::query()->first();
        if (!$row) {
            $row = CompanySetup::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getSalaryProration()
    {
        $row = SalaryProrationRule::query()->first();
        if (!$row) {
            $row = SalaryProrationRule::create([]);
        }
        return response()->json($row);
    }

    public function saveSalaryProration(Request $request)
    {
        $validated = $request->validate([
            'salary_mode' => ['required', Rule::in(['prorate_workdays', 'fixed_50_50'])],
            'work_minutes_per_day' => ['required', 'integer', 'min:1'],
            'minutes_rounding' => ['required', Rule::in(['per_minute', 'nearest_15'])],
            'money_rounding' => ['required', Rule::in(['2_decimals', 'nearest_peso'])],
        ]);

        $row = SalaryProrationRule::query()->first();
        if (!$row) {
            $row = SalaryProrationRule::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getTimekeepingRules()
    {
        $row = TimekeepingRule::query()->first();
        if (!$row) {
            $row = TimekeepingRule::create([]);
        }
        return response()->json($row);
    }

    public function saveTimekeepingRules(Request $request)
    {
        $validated = $request->validate([
            'shift_start' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0'],
            'late_rule_type' => ['required', Rule::in(['rate_based', 'flat_penalty'])],
            'late_penalty_per_minute' => ['required', 'numeric', 'min:0'],
            'late_rounding' => ['required', Rule::in(['none', 'nearest_5', 'nearest_15'])],
            'undertime_enabled' => ['required', 'boolean'],
            'undertime_rule_type' => ['required', Rule::in(['rate_based', 'flat_penalty'])],
            'undertime_penalty_per_minute' => ['required', 'numeric', 'min:0'],
            'work_minutes_per_day' => ['required', 'integer', 'min:1'],
        ]);

        $row = TimekeepingRule::query()->first();
        if (!$row) {
            $row = TimekeepingRule::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getStatutorySetup()
    {
        $row = StatutorySetup::query()->first();
        if (!$row) {
            $row = StatutorySetup::create([]);
        }
        return response()->json($row);
    }

    public function saveStatutorySetup(Request $request)
    {
        $validated = $request->validate([
            'ph_ee_percent' => ['sometimes', 'numeric', 'min:0'],
            'ph_er_percent' => ['sometimes', 'numeric', 'min:0'],
            'ph_min_cap' => ['sometimes', 'numeric', 'min:0'],
            'ph_max_cap' => ['sometimes', 'numeric', 'min:0'],
            'ph_split_rule' => ['sometimes', Rule::in(['monthly', 'split_cutoffs', 'cutoff1_only', 'cutoff2_only'])],
            'pi_ee_percent' => ['sometimes', 'numeric', 'min:0'],
            'pi_ee_percent_low' => ['sometimes', 'numeric', 'min:0'],
            'pi_ee_threshold' => ['sometimes', 'numeric', 'min:0'],
            'pi_er_percent' => ['sometimes', 'numeric', 'min:0'],
            'pi_cap' => ['sometimes', 'numeric', 'min:0'],
            'pi_split_rule' => ['sometimes', Rule::in(['monthly', 'split_cutoffs', 'cutoff1_only', 'cutoff2_only'])],
            'sss_split_rule' => ['sometimes', Rule::in(['monthly', 'split_cutoffs', 'cutoff1_only', 'cutoff2_only'])],
            'sss_ee_percent' => ['sometimes', 'numeric', 'min:0'],
            'sss_er_percent' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $row = StatutorySetup::query()->first();
        if (!$row) {
            $row = StatutorySetup::create([]);
        }
        if ($request->has('sss_table')) {
            $row->sss_table = $request->input('sss_table');
        }
        if ($validated) {
            $row->fill($validated);
        }
        $row->save();

        return response()->json($row);
    }

    public function getWithholdingTaxPolicy()
    {
        $row = WithholdingTaxPolicy::query()->first();
        if (!$row) {
            $row = WithholdingTaxPolicy::create([]);
        }
        return response()->json($row);
    }

    public function saveWithholdingTaxPolicy(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'method' => ['required', Rule::in(['table', 'manual_fixed', 'manual_percent'])],
            'pay_frequency' => ['required', Rule::in(['semi_monthly', 'monthly', 'weekly'])],
            'basis_sss' => ['required', 'boolean'],
            'basis_ph' => ['required', 'boolean'],
            'basis_pi' => ['required', 'boolean'],
            'timing' => ['required', Rule::in(['monthly', 'per_pay_period'])],
            'split_rule' => ['required', Rule::in(['monthly', 'split_cutoffs', 'cutoff1_only', 'cutoff2_only'])],
            'fixed_amount' => ['required', 'numeric', 'min:0'],
            'percent' => ['required', 'numeric', 'min:0'],
        ]);

        $row = WithholdingTaxPolicy::query()->first();
        if (!$row) {
            $row = WithholdingTaxPolicy::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getWithholdingTaxBrackets(Request $request)
    {
        $query = WithholdingTaxBracket::query();
        $freq = $request->query('pay_frequency');
        if ($freq) {
            $query->where('pay_frequency', $freq);
        }
        return response()->json($query->orderBy('sort_order')->get());
    }

    public function saveWithholdingTaxBrackets(Request $request)
    {
        $validated = $request->validate([
            'brackets' => ['nullable', 'array'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'brackets.*.pay_frequency' => ['nullable', 'string', 'max:50'],
            'brackets.*.bracket_no' => ['nullable', 'integer', 'min:1'],
            'brackets.*.compensation_range' => ['nullable', 'string', 'max:255'],
            'brackets.*.prescribed_withholding_tax' => ['nullable', 'string', 'max:255'],
            'brackets.*.bracket_from' => ['required', 'numeric', 'min:0'],
            'brackets.*.bracket_to' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.base_tax' => ['required', 'numeric', 'min:0'],
            'brackets.*.excess_percent' => ['required', 'numeric', 'min:0'],
        ]);

        $brackets = $validated['brackets'] ?? [];

        DB::transaction(function () use ($validated, $brackets) {
            WithholdingTaxBracket::query()->delete();
            $rows = [];
            foreach ($brackets as $idx => $b) {
                $rows[] = [
                    'pay_frequency' => $b['pay_frequency'] ?? null,
                    'bracket_no' => $b['bracket_no'] ?? null,
                    'compensation_range' => $b['compensation_range'] ?? null,
                    'prescribed_withholding_tax' => $b['prescribed_withholding_tax'] ?? null,
                    'bracket_from' => $b['bracket_from'],
                    'bracket_to' => $b['bracket_to'],
                    'base_tax' => $b['base_tax'],
                    'excess_percent' => $b['excess_percent'],
                    'sort_order' => $idx,
                ];
            }
            if ($rows) {
                WithholdingTaxBracket::query()->insert($rows);
            }

            $policy = WithholdingTaxPolicy::query()->first();
            if (!$policy) {
                $policy = WithholdingTaxPolicy::create([]);
            }
            if (empty($brackets)) {
                $policy->wt_table_source = null;
                $policy->wt_table_imported_at = null;
            } elseif (!empty($validated['source_name'])) {
                $policy->wt_table_source = $validated['source_name'];
                $policy->wt_table_imported_at = now();
            }
            $policy->save();
        });

        return response()->json(['saved' => true]);
    }

    public function getCashAdvancePolicy()
    {
        $row = CashAdvancePolicy::query()->first();
        if (!$row) {
            $row = CashAdvancePolicy::create([]);
        }
        return response()->json($row);
    }

    public function saveCashAdvancePolicy(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'default_method' => ['required', Rule::in(['salary_deduction', 'manual_payment'])],
            'default_term_months' => ['required', 'integer', 'min:1'],
            'deduct_timing' => ['required', Rule::in(['cutoff1', 'cutoff2', 'split'])],
            'priority' => ['required', 'integer', 'min:1'],
            'deduction_method' => ['required', Rule::in(['equal_amortization', 'manual_per_cutoff'])],
        ]);

        $row = CashAdvancePolicy::query()->first();
        if (!$row) {
            $row = CashAdvancePolicy::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getCashAdvances()
    {
        $rows = CashAdvance::query()->orderByDesc('id')->get();
        $policy = CashAdvancePolicy::query()->first();
        $cutoffsPerMonth = 2;
        if ($policy && in_array($policy->deduct_timing, ['cutoff1', 'cutoff2'], true)) {
            $cutoffsPerMonth = 1;
        }
        $employeeIds = $rows->pluck('employee_id')->unique()->values();
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->get(['id', 'emp_no', 'first_name', 'last_name', 'middle_name'])
            ->keyBy('id');

        $result = $rows->map(function ($row) use ($employees, $cutoffsPerMonth) {
            $emp = $employees->get($row->employee_id);
            $name = $emp ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : '')) : null;
            if ($row->per_cutoff_deduction === null) {
                $totalCutoffs = max(1, (int) $row->term_months * $cutoffsPerMonth);
                $row->per_cutoff_deduction = $row->amount / $totalCutoffs;
                $row->save();
            }
            return [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'employee_name' => $name,
                'amount' => $row->amount,
                'term_months' => $row->term_months,
                'start_month' => $row->start_month,
                'method' => $row->method,
                'per_cutoff_deduction' => $row->per_cutoff_deduction,
                'status' => $row->status,
            ];
        });

        return response()->json($result);
    }

    public function saveCashAdvance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'term_months' => ['required', 'integer', 'min:1'],
            'start_month' => ['required', 'date_format:Y-m'],
            'method' => ['required', Rule::in(['salary_deduction', 'manual_payment'])],
        ]);

        $policy = CashAdvancePolicy::query()->first();
        $cutoffsPerMonth = 2;
        if ($policy && in_array($policy->deduct_timing, ['cutoff1', 'cutoff2'], true)) {
            $cutoffsPerMonth = 1;
        }
        $totalCutoffs = max(1, (int) $validated['term_months'] * $cutoffsPerMonth);
        $perCutoff = $validated['amount'] / $totalCutoffs;

        $row = CashAdvance::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $validated['amount'],
            'term_months' => $validated['term_months'],
            'start_month' => $validated['start_month'] . '-01',
            'method' => $validated['method'],
            'per_cutoff_deduction' => $perCutoff,
            'status' => 'Active',
        ]);

        return response()->json($row);
    }

    public function updateCashAdvanceStatus(Request $request, CashAdvance $cashAdvance)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Completed'])],
        ]);

        $cashAdvance->update(['status' => $validated['status']]);

        return response()->json($cashAdvance);
    }
    public function getPayrollCalendar()
    {
        $row = PayrollCalendarSetting::query()->first();
        if (!$row) {
            $row = PayrollCalendarSetting::create([]);
        }
        return response()->json($row);
    }

    public function savePayrollCalendar(Request $request)
    {
        $validated = $request->validate([
            'pay_date_a' => ['required', 'integer', 'min:1', 'max:31'],
            'pay_date_b' => ['required', 'string', Rule::in(['EOM', '30', '31'])],
            'cutoff_a_from' => ['required', 'integer', 'min:1', 'max:31'],
            'cutoff_a_to' => ['required', 'integer', 'min:1', 'max:31'],
            'cutoff_b_from' => ['required', 'integer', 'min:1', 'max:31'],
            'cutoff_b_to' => ['required', 'integer', 'min:1', 'max:31'],
            'work_mon_sat' => ['required', 'boolean'],
            'pay_on_prev_workday_if_sunday' => ['required', 'boolean'],
        ]);

        $row = PayrollCalendarSetting::query()->first();
        if (!$row) {
            $row = PayrollCalendarSetting::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getOvertimeRules()
    {
        $row = OvertimeRule::query()->first();
        if (!$row) {
            $row = OvertimeRule::create([]);
        }
        $payload = $row->toArray();
        if (!array_key_exists('rounding', $payload) && array_key_exists('rounding_option', $payload)) {
            $payload['rounding'] = $payload['rounding_option'];
        }
        return response()->json($payload);
    }

    public function saveOvertimeRules(Request $request)
    {
        $validated = $request->validate([
            'ot_mode' => ['required', Rule::in(['flat_rate', 'rate_based'])],
            'require_approval' => ['required', 'boolean'],
            'flat_rate' => ['required', 'numeric', 'min:0'],
            'multiplier' => ['required', 'numeric', 'min:0'],
            'rounding' => ['required', Rule::in(['none', 'nearest_15', 'nearest_30', 'nearest_60', 'down_15', 'up_15'])],
        ]);

        $row = OvertimeRule::query()->first();
        if (!$row) {
            $row = OvertimeRule::create($validated + ['rounding_option' => $validated['rounding']]);
        } else {
            $row->update($validated + ['rounding_option' => $validated['rounding']]);
        }

        return response()->json($row);
    }

    public function getAttendanceCodes()
    {
        if (AttendanceCode::query()->count() === 0) {
            AttendanceCode::query()->insert([
                [
                    'code' => 'P',
                    'description' => 'Present',
                    'counts_as_present' => true,
                    'counts_as_paid' => true,
                    'affects_deductions' => false,
                    'notes' => '',
                ],
                [
                    'code' => 'PL',
                    'description' => 'Paid Leave',
                    'counts_as_present' => true,
                    'counts_as_paid' => true,
                    'affects_deductions' => false,
                    'notes' => '',
                ],
                [
                    'code' => 'UL',
                    'description' => 'Unpaid Leave',
                    'counts_as_present' => false,
                    'counts_as_paid' => false,
                    'affects_deductions' => true,
                    'notes' => '',
                ],
                [
                    'code' => 'A',
                    'description' => 'Absent',
                    'counts_as_present' => false,
                    'counts_as_paid' => false,
                    'affects_deductions' => true,
                    'notes' => 'Default no log',
                ],
                [
                    'code' => 'HD',
                    'description' => 'Half-day',
                    'counts_as_present' => true,
                    'counts_as_paid' => true,
                    'affects_deductions' => true,
                    'notes' => 'Depends later',
                ],
                [
                    'code' => 'OFF',
                    'description' => 'Rest Day',
                    'counts_as_present' => false,
                    'counts_as_paid' => false,
                    'affects_deductions' => false,
                    'notes' => 'Default Sunday',
                ],
            ]);
        }

        $defaults = AttendanceCodeSetting::query()->first();
        if (!$defaults) {
            $defaults = AttendanceCodeSetting::create([
                'default_no_log_code' => 'A',
                'default_sunday_code' => 'OFF',
            ]);
        }

        return response()->json([
            'codes' => AttendanceCode::query()->orderBy('code')->get(),
            'defaults' => $defaults,
        ]);
    }

    public function saveAttendanceCodes(Request $request)
    {
        $validated = $request->validate([
            'codes' => ['required', 'array', 'min:1'],
            'codes.*.code' => ['required', 'string', 'max:10'],
            'codes.*.description' => ['required', 'string', 'max:255'],
            'codes.*.counts_as_present' => ['required', 'boolean'],
            'codes.*.counts_as_paid' => ['required', 'boolean'],
            'codes.*.affects_deductions' => ['required', 'boolean'],
            'codes.*.notes' => ['nullable', 'string'],
            'defaults.default_no_log_code' => ['nullable', 'string', 'max:10'],
            'defaults.default_sunday_code' => ['nullable', 'string', 'max:10'],
        ]);

        DB::transaction(function () use ($validated) {
            AttendanceCode::query()->delete();
            $rows = array_map(function ($c) {
                return [
                    'code' => strtoupper($c['code']),
                    'description' => $c['description'],
                    'counts_as_present' => $c['counts_as_present'],
                    'counts_as_paid' => $c['counts_as_paid'],
                    'affects_deductions' => $c['affects_deductions'],
                    'notes' => $c['notes'] ?? null,
                ];
            }, $validated['codes']);
            AttendanceCode::query()->insert($rows);

            $defaults = $validated['defaults'] ?? [];
            $settings = AttendanceCodeSetting::query()->first();
            if (!$settings) {
                AttendanceCodeSetting::create([
                    'default_no_log_code' => $defaults['default_no_log_code'] ?? null,
                    'default_sunday_code' => $defaults['default_sunday_code'] ?? null,
                ]);
            } else {
                $settings->update([
                    'default_no_log_code' => $defaults['default_no_log_code'] ?? null,
                    'default_sunday_code' => $defaults['default_sunday_code'] ?? null,
                ]);
            }
        });

        return response()->json(['saved' => true]);
    }
}
