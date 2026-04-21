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
use App\Models\PayrollDeductionPolicy;
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
                $prescribed = $b['prescribed_withholding_tax'] ?? null;
                $baseTax = (float) ($b['base_tax'] ?? 0);
                $excessPercent = (float) ($b['excess_percent'] ?? 0);

                // Defensive parsing: older clients may fail to extract base tax from strings like
                // "₱1,875.00 + 20% over ₱33,333" and send base_tax=0.
                if (is_string($prescribed) && trim($prescribed) !== '') {
                    if ($baseTax <= 0 && preg_match('/([\\d,.]+)/', $prescribed, $m)) {
                        $candidate = (float) str_replace(',', '', $m[1]);
                        if ($candidate > 0) $baseTax = $candidate;
                    }
                    if ($excessPercent <= 0 && preg_match('/(\\d+(?:\\.\\d+)?)\\s*%/', $prescribed, $m)) {
                        $excessPercent = (float) $m[1];
                    }
                }

                $rows[] = [
                    'pay_frequency' => $b['pay_frequency'] ?? null,
                    'bracket_no' => $b['bracket_no'] ?? null,
                    'compensation_range' => $b['compensation_range'] ?? null,
                    'prescribed_withholding_tax' => $prescribed,
                    'bracket_from' => $b['bracket_from'],
                    'bracket_to' => $b['bracket_to'],
                    'base_tax' => $baseTax,
                    'excess_percent' => $excessPercent,
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
            'regular_default_term_months' => ['nullable', 'integer', 'min:1'],
            'probationary_term_months' => ['nullable', 'integer', 'min:1'],
            'trainee_term_months' => ['nullable', 'integer', 'min:1'],
            'trainee_force_full_deduct' => ['nullable', 'boolean'],
            'allow_full_deduct' => ['nullable', 'boolean'],
            'max_payback_months' => ['required', 'integer', 'min:1'],
            'deduct_timing' => ['required', Rule::in(['cutoff1', 'cutoff2', 'split'])],
            'priority' => ['required', 'integer', 'min:1'],
            'deduction_method' => ['required', Rule::in(['equal_amortization', 'manual_per_cutoff'])],
        ]);

        // Fallbacks to keep existing clients working.
        $validated['regular_default_term_months'] = (int) ($validated['regular_default_term_months'] ?? $validated['default_term_months']);
        $validated['probationary_term_months'] = (int) ($validated['probationary_term_months'] ?? 1);
        $validated['trainee_term_months'] = (int) ($validated['trainee_term_months'] ?? 1);
        $validated['trainee_force_full_deduct'] = (bool) ($validated['trainee_force_full_deduct'] ?? true);
        $validated['allow_full_deduct'] = (bool) ($validated['allow_full_deduct'] ?? true);

        $row = CashAdvancePolicy::query()->first();
        if (!$row) {
            $row = CashAdvancePolicy::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getPayrollDeductionPolicy()
    {
        $row = PayrollDeductionPolicy::query()->first();
        if (!$row) {
            $row = PayrollDeductionPolicy::create([]);
        }
        return response()->json($row);
    }

    public function savePayrollDeductionPolicy(Request $request)
    {
        $validated = $request->validate([
            'apply_premiums_to_non_regular' => ['required', 'boolean'],
            'apply_tax_to_non_regular' => ['required', 'boolean'],
            'cap_charges_to_net_pay' => ['required', 'boolean'],
            'carry_forward_unpaid_charges' => ['required', 'boolean'],
            'cap_cash_advance_to_net_pay' => ['required', 'boolean'],
            'loans_before_charges' => ['required', 'boolean'],
            'charges_before_cash_advance' => ['required', 'boolean'],
        ]);

        $row = PayrollDeductionPolicy::query()->first();
        if (!$row) {
            $row = PayrollDeductionPolicy::create($validated);
        } else {
            $row->update($validated);
        }

        return response()->json($row);
    }

    public function getCashAdvances()
    {
        $rows = CashAdvance::query()->orderByDesc('id')->get();
        $policy = CashAdvancePolicy::query()->first();
        $cutoffsPerMonth = 2; // Cash advance deducts on both cutoffs by default.
        $employeeIds = $rows->pluck('employee_id')->unique()->values();
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->get(['id', 'emp_no', 'first_name', 'last_name', 'middle_name'])
            ->keyBy('id');

        $result = $rows->map(function ($row) use ($employees, $cutoffsPerMonth) {
            $emp = $employees->get($row->employee_id);
            $name = $emp ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : '')) : null;
            if ($row->per_cutoff_deduction === null) {
                $fullDeduct = (bool) ($row->full_deduct ?? false);
                if ($fullDeduct) {
                    $row->per_cutoff_deduction = $row->amount;
                } else {
                    $totalCutoffs = max(1, (int) $row->term_months * $cutoffsPerMonth);
                    $row->per_cutoff_deduction = $row->amount / $totalCutoffs;
                }
                $row->save();
            }
            // Backward-compat initialization for legacy rows only.
            // Do not reset posted payroll deductions when balance is already zero.
            if ($row->balance_remaining === null) {
                $amount = (float) ($row->amount ?? 0);
                $deducted = (float) ($row->amount_deducted ?? 0);
                $row->balance_remaining = max(0.0, $amount - max(0.0, $deducted));
                $row->save();
            }
            return [
                'id' => $row->id,
                'reference_no' => $row->reference_no,
                'employee_id' => $row->employee_id,
                'employee_name' => $name,
                'amount' => $row->amount,
                'balance_remaining' => (float) ($row->balance_remaining ?? $row->amount),
                'amount_deducted' => (float) ($row->amount_deducted ?? 0),
                'term_months' => $row->term_months,
                'start_month' => $row->start_month,
                'method' => $row->method,
                'full_deduct' => (bool) ($row->full_deduct ?? false),
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
            'full_deduct' => ['nullable', 'boolean'],
        ]);

        $policy = CashAdvancePolicy::query()->first();
        $cutoffsPerMonth = 2;
        $employee = Employee::query()->find($validated['employee_id']);
        $employmentType = strtolower(trim((string) ($employee?->employment_type ?? '')));
        $fullDeduct = (bool) ($validated['full_deduct'] ?? false);

        // Only one active cash advance per employee (simplifies deduction + balance tracking).
        $existingActive = CashAdvance::query()
            ->where('employee_id', $validated['employee_id'])
            ->whereRaw("LOWER(TRIM(COALESCE(status,''))) = 'active'")
            ->exists();
        if ($existingActive) {
            return response()->json(['message' => 'Employee already has an active cash advance.'], 422);
        }

        if ($employmentType === 'trainees' || $employmentType === 'trainee') {
            $termMonths = (int) ($policy?->trainee_term_months ?? 1);
            $fullDeduct = (bool) ($policy?->trainee_force_full_deduct ?? true);
        } elseif ($employmentType === 'probationary') {
            $termMonths = (int) ($policy?->probationary_term_months ?? 1);
            $fullDeduct = false;
        } else {
            $termMonths = (int) $validated['term_months'];
            if (!$policy || !$policy->allow_full_deduct) {
                $fullDeduct = false;
            }
            if ($fullDeduct) {
                $termMonths = 1;
            }
        }

        $maxMonths = (int) ($policy?->max_payback_months ?? 0);
        if ($maxMonths > 0) {
            $termMonths = min($termMonths, $maxMonths);
        }

        $totalCutoffs = max(1, $termMonths * $cutoffsPerMonth);
        $perCutoff = $fullDeduct ? (float) $validated['amount'] : ((float) $validated['amount'] / $totalCutoffs);

        $row = CashAdvance::create([
            'reference_no' => null,
            'employee_id' => $validated['employee_id'],
            'amount' => $validated['amount'],
            'balance_remaining' => $validated['amount'],
            'amount_deducted' => 0,
            'term_months' => $termMonths,
            'start_month' => $validated['start_month'] . '-01',
            'method' => $validated['method'],
            'full_deduct' => $fullDeduct,
            'per_cutoff_deduction' => $perCutoff,
            'status' => 'Active',
        ]);

        if (!$row->reference_no) {
            $row->reference_no = 'CA-' . now()->format('Y') . '-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT);
            $row->save();
        }

        return response()->json($row);
    }

    public function updateCashAdvanceStatus(Request $request, CashAdvance $cashAdvance)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['Active', 'Completed'])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'term_months' => ['nullable', 'integer', 'min:1'],
            'start_month' => ['nullable', 'date_format:Y-m'],
            'method' => ['nullable', Rule::in(['salary_deduction', 'manual_payment'])],
            'full_deduct' => ['nullable', 'boolean'],
        ]);

        $isStatusOnly = array_key_exists('status', $validated) && count(array_filter($validated, fn ($v) => $v !== null)) === 1;
        if ($isStatusOnly) {
            $updates = ['status' => $validated['status']];
            if ($validated['status'] === 'Completed') {
                $updates['balance_remaining'] = 0;
                $updates['per_cutoff_deduction'] = 0;
            }
            $cashAdvance->update($updates);
            return response()->json($cashAdvance);
        }

        $policy = CashAdvancePolicy::query()->first();
        $cutoffsPerMonth = 2;

        $employee = Employee::query()->find($cashAdvance->employee_id);
        $employmentType = strtolower(trim((string) ($employee?->employment_type ?? '')));

        $amount = (float) ($validated['amount'] ?? $cashAdvance->amount);
        $termMonths = (int) ($validated['term_months'] ?? $cashAdvance->term_months ?? 1);
        $method = (string) ($validated['method'] ?? $cashAdvance->method ?? 'salary_deduction');
        $fullDeduct = (bool) ($validated['full_deduct'] ?? $cashAdvance->full_deduct ?? false);

        // Enforce policy defaults/constraints.
        if ($employmentType === 'trainees' || $employmentType === 'trainee') {
            $fullDeduct = (bool) ($policy?->trainee_force_full_deduct ?? true);
            if ($fullDeduct) $termMonths = 1;
        } elseif ($employmentType === 'probationary') {
            $fullDeduct = false;
        } else {
            if (!$policy || !$policy->allow_full_deduct) {
                $fullDeduct = false;
            }
            if ($fullDeduct) $termMonths = 1;
        }

        $maxMonths = (int) ($policy?->max_payback_months ?? 0);
        if ($maxMonths > 0) {
            $termMonths = min($termMonths, $maxMonths);
        }
        $termMonths = max(1, (int) $termMonths);

        $startMonth = $validated['start_month'] ?? null;
        $startDate = $startMonth ? ($startMonth . '-01') : ($cashAdvance->start_month?->format('Y-m-d') ?? now()->format('Y-m-01'));

        $amountDeducted = (float) ($cashAdvance->amount_deducted ?? 0);
        $balanceRemaining = max(0, $amount - $amountDeducted);
        $totalCutoffs = max(1, $termMonths * $cutoffsPerMonth);
        $perCutoff = $fullDeduct ? $balanceRemaining : ($balanceRemaining / $totalCutoffs);

        $cashAdvance->update([
            'amount' => $amount,
            'term_months' => $termMonths,
            'start_month' => $startDate,
            'method' => $method,
            'full_deduct' => $fullDeduct,
            'balance_remaining' => $balanceRemaining,
            'per_cutoff_deduction' => $perCutoff,
        ]);

        return response()->json($cashAdvance);
    }

    public function destroyCashAdvance(CashAdvance $cashAdvance)
    {
        $deducted = (float) ($cashAdvance->amount_deducted ?? 0);
        if ($deducted > 0) {
            return response()->json(['message' => 'Cannot delete: cash advance already has deductions applied.'], 422);
        }

        $cashAdvance->delete();
        return response()->json(['ok' => true]);
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
                    'description' => 'Day-off',
                    'counts_as_present' => false,
                    'counts_as_paid' => false,
                    'affects_deductions' => false,
                    'notes' => 'Default Sunday',
                ],
            ]);
        }

        // Unpaid Leave is treated the same as Absent; keep the settings list clean.
        AttendanceCode::query()
            ->whereIn('code', ['UL', 'LWOP'])
            ->orWhere('description', 'Unpaid Leave')
            ->delete();

        // Normalize legacy label: OFF should be "Day-off", not "Rest Day".
        AttendanceCode::query()
            ->whereRaw('UPPER(code) = ?', ['OFF'])
            ->whereRaw('LOWER(description) = ?', ['rest day'])
            ->update(['description' => 'Day-off']);

        $defaults = AttendanceCodeSetting::query()->first();
        if (!$defaults) {
            $defaults = AttendanceCodeSetting::create([
                'default_no_log_code' => 'A',
                'default_sunday_code' => 'OFF',
            ]);
        } else {
            $changed = false;
            $noLog = strtoupper((string) ($defaults->default_no_log_code ?? ''));
            $sun = strtoupper((string) ($defaults->default_sunday_code ?? ''));
            if (in_array($noLog, ['UL', 'LWOP'], true)) {
                $defaults->default_no_log_code = 'A';
                $changed = true;
            }
            if (in_array($sun, ['UL', 'LWOP'], true)) {
                $defaults->default_sunday_code = 'OFF';
                $changed = true;
            }
            if ($changed) {
                $defaults->save();
            }
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

        $codes = array_values(array_filter($validated['codes'], function ($c) {
            $code = strtoupper((string) ($c['code'] ?? ''));
            $desc = trim((string) ($c['description'] ?? ''));
            if (in_array($code, ['UL', 'LWOP'], true)) return false;
            if (strcasecmp($desc, 'Unpaid Leave') === 0) return false;
            return true;
        }));

        if (count($codes) < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'codes' => 'At least one attendance code is required.',
            ]);
        }

        $defaults = $validated['defaults'] ?? [];
        $noLog = strtoupper((string) ($defaults['default_no_log_code'] ?? ''));
        $sun = strtoupper((string) ($defaults['default_sunday_code'] ?? ''));
        if (in_array($noLog, ['UL', 'LWOP'], true)) {
            $defaults['default_no_log_code'] = 'A';
        }
        if (in_array($sun, ['UL', 'LWOP'], true)) {
            $defaults['default_sunday_code'] = 'OFF';
        }

        DB::transaction(function () use ($codes, $defaults) {
            AttendanceCode::query()->delete();
            $rows = array_map(function ($c) {
                $code = strtoupper((string) ($c['code'] ?? ''));
                $description = (string) ($c['description'] ?? '');
                if ($code === 'OFF' && strcasecmp(trim($description), 'Rest Day') === 0) {
                    $description = 'Day-off';
                }
                return [
                    'code' => $code,
                    'description' => $description,
                    'counts_as_present' => $c['counts_as_present'],
                    'counts_as_paid' => $c['counts_as_paid'],
                    'affects_deductions' => $c['affects_deductions'],
                    'notes' => $c['notes'] ?? null,
                ];
            }, $codes);
            AttendanceCode::query()->insert($rows);

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
