<?php

namespace App\Services\Payroll;

use App\Models\AttendanceCodeSetting;
use App\Models\AttendanceSummary;
use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\CashAdvancePolicy;
use App\Models\DeductionSchedule;
use App\Models\Employee;
use App\Models\EmployeeLoanSchedule;
use App\Models\OvertimeRule;
use App\Models\PayrollDeductionPolicy;
use App\Models\PayrollCalendarSetting;
use App\Models\SalaryProrationRule;
use App\Models\StatutorySetup;
use App\Models\TimekeepingRule;
use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxPolicy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollCalculator
{
    public function compute(string $periodMonth, string $cutoff, string $assignment = 'All', ?string $area = null, ?Collection $overrides = null, string $runType = 'External'): array
    {
        [$start, $end] = $this->cutoffRange($periodMonth, $cutoff);
        $employees = $this->employeesForFilters($assignment, $area, $runType);

        $timekeeping = TimekeepingRule::query()->first() ?? TimekeepingRule::create([]);
        $proration = SalaryProrationRule::query()->first() ?? SalaryProrationRule::create([]);
        $overtime = OvertimeRule::query()->first() ?? OvertimeRule::create([]);
        $statutory = StatutorySetup::query()->first() ?? StatutorySetup::create([]);
        $wtPolicy = WithholdingTaxPolicy::query()->first() ?? WithholdingTaxPolicy::create([]);
        $wtBrackets = WithholdingTaxBracket::query()->orderBy('sort_order')->get();
        $cashPolicy = CashAdvancePolicy::query()->first();
        $dedPolicy = PayrollDeductionPolicy::query()->first() ?? PayrollDeductionPolicy::create([]);
        $attDefaults = AttendanceCodeSetting::query()->first();

        $employeeIds = $employees->pluck('id')->all();
        $summaries = AttendanceSummary::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('period_month', $periodMonth)
            ->where('cutoff', $cutoff)
            ->get()
            ->keyBy('employee_id');
        if (count($employeeIds) > 0) {
            $missingIds = array_values(array_diff($employeeIds, $summaries->keys()->all()));
            if ($missingIds) {
                $this->buildSummariesForPeriod($missingIds, $periodMonth, $cutoff, $start, $end);
                $summaries = AttendanceSummary::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('period_month', $periodMonth)
                    ->where('cutoff', $cutoff)
                    ->get()
                    ->keyBy('employee_id');
            }
        }

        $cashAdvances = CashAdvance::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'Active')
            ->get()
            ->groupBy('employee_id');

        $loanSchedules = EmployeeLoanSchedule::query()
            ->with('loan')
            ->whereIn('employee_id', $employeeIds)
            ->where('due_month', $periodMonth)
            ->where('due_cutoff', $cutoff)
            ->where('status', 'scheduled')
            ->whereHas('loan', function ($q) {
                $q->where('status', 'active')
                  ->where('auto_deduct', true);
            })
            ->get()
            ->groupBy('employee_id');

        $rows = $this->computeRows($employees, $start, $end, $cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $dedPolicy, $summaries, $cashAdvances, $loanSchedules, $runType);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'rows' => $rows,
        ];
    }

    public function computeForEmployee(Employee $emp, Carbon $start, Carbon $end, string $cutoff, ?Collection $overrides, TimekeepingRule $timekeeping, SalaryProrationRule $proration, OvertimeRule $overtime, StatutorySetup $statutory, WithholdingTaxPolicy $wtPolicy, Collection $wtBrackets, ?CashAdvancePolicy $cashPolicy, PayrollDeductionPolicy $dedPolicy, Collection $summaries, Collection $cashAdvances, Collection $loanSchedules, string $runType = 'External'): array
    {
        $employmentType = strtolower(trim((string) ($emp->employment_type ?? '')));
        $isRegular = $employmentType === 'regular';

        $summary = $this->summaryFromAggregate($summaries->get($emp->id));
        $workdaysInMonth = 26;
        $dailyRate = (float) $emp->basic_pay / $workdaysInMonth;
        $allowanceDaily = (float) $emp->allowance / $workdaysInMonth;

        if (($proration->salary_mode ?? 'prorate_workdays') === 'fixed_50_50') {
            $basicCutoff = (float) $emp->basic_pay / 2;
        } else {
            $basicCutoff = $dailyRate * $summary['paid_days'];
        }
        // Allowance is evenly distributed across both cutoffs
        $allowanceCutoff = (float) $emp->allowance / 2;

        $computedOtHours = (float) $summary['ot_hours'];
        $computedOtHours = $this->roundOtHours($computedOtHours, (string) ($overtime->rounding ?? $overtime->rounding_option ?? 'none'));

        $override = $overrides ? $overrides->get($emp->id) : null;
        $otOverrideOn = (bool) ($override['ot_override_on'] ?? false);
        $otOverrideHours = $otOverrideOn ? (float) ($override['ot_override_hours'] ?? 0) : $computedOtHours;
        $otPay = $this->computeOtPay($otOverrideHours, $dailyRate, $timekeeping, $overtime);

        $attendanceDeduction = $this->computeAttendanceDeductions(
            $summary,
            $dailyRate,
            $timekeeping,
            $proration
        );

        $computedCashAdvance = $this->computeCashAdvanceDeduction(
            $cashPolicy,
            $cashAdvances->get($emp->id, collect()),
            $start->format('Y-m'),
            $cutoff
        );
        $cashAdvance = array_key_exists('cash_advance', $override ?? []) ? (float) ($override['cash_advance'] ?? 0) : $computedCashAdvance;

        $adjustments = $override['adjustments'] ?? [];
        $earnAdj = 0.0;
        $dedAdj = 0.0;
        foreach ($adjustments as $adj) {
            $amt = (float) ($adj['amount'] ?? 0);
            if (($adj['type'] ?? '') === 'deduction') $dedAdj += $amt;
            else $earnAdj += $amt;
        }

        $zeros = ['ee' => 0.0, 'er' => 0.0, 'ee_full' => 0.0, 'er_full' => 0.0];
        $applyPremiums = $isRegular || (bool) ($dedPolicy->apply_premiums_to_non_regular ?? false);
        $sss = ($applyPremiums && $this->hasGovId($emp->sss))
            ? $this->computeSss($statutory, (float) $emp->basic_pay, $cutoff)
            : $zeros;
        $philhealth = ($applyPremiums && $this->hasGovId($emp->philhealth))
            ? $this->computePhilHealth($statutory, (float) $emp->basic_pay, $cutoff)
            : $zeros;
        $pagibig = ($applyPremiums && $this->hasGovId($emp->pagibig))
            ? $this->computePagibig($statutory, (float) $emp->basic_pay, $cutoff)
            : $zeros;

        $gross = $basicCutoff + $allowanceCutoff + $otPay + $earnAdj;

        $payFreq = (string) ($wtPolicy->pay_frequency ?? 'semi_monthly');
        $periodsPerMonth = $this->periodsPerMonth($payFreq);
        $timing = (string) ($wtPolicy->timing ?? 'monthly');

        if ($timing === 'monthly') {
            $monthlyTaxable = $gross * $periodsPerMonth;
            if ($wtPolicy->basis_sss) $monthlyTaxable -= (float) ($sss['ee_full'] ?? 0);
            if ($wtPolicy->basis_ph) $monthlyTaxable -= (float) ($philhealth['ee_full'] ?? 0);
            if ($wtPolicy->basis_pi) $monthlyTaxable -= (float) ($pagibig['ee_full'] ?? 0);
            $monthlyTaxable = max(0, $monthlyTaxable);
            $taxable = $periodsPerMonth > 0 ? ($monthlyTaxable / $periodsPerMonth) : 0.0;
        } else {
            $taxable = $gross;
            if ($wtPolicy->basis_sss) $taxable -= $sss['ee'];
            if ($wtPolicy->basis_ph) $taxable -= $philhealth['ee'];
            if ($wtPolicy->basis_pi) $taxable -= $pagibig['ee'];
            $taxable = max(0, $taxable);
        }

        $applyTax = $isRegular || (bool) ($dedPolicy->apply_tax_to_non_regular ?? false);
        $withholding = ($applyTax && ($wtPolicy->enabled ?? true))
            ? $this->computeWithholdingTax($wtPolicy, $wtBrackets, $taxable, $cutoff)
            : 0.0;

        $periodMonth = $start->format('Y-m');
        $chargesScheduled = (float) DeductionSchedule::query()
            ->where('employee_id', $emp->id)
            ->where('due_month', $periodMonth)
            ->where('due_cutoff', $cutoff)
            ->where('status', 'scheduled')
            ->sum('amount');

        // Priority order: statutory/tax, then loans, then charges/shortages, then cash advance (capped to avoid negative net).
        $baseBeforeLoans = $attendanceDeduction
            + $dedAdj
            + $sss['ee']
            + $philhealth['ee']
            + $pagibig['ee']
            + $withholding;

        [$loanDeduction, $loanItems] = $this->computeLoanDeductions(
            $loanSchedules->get($emp->id, collect()),
            $gross,
            $baseBeforeLoans
        );

        $availableAfterLoans = max(0.0, $gross - ($baseBeforeLoans + $loanDeduction));
        $capCharges = (bool) ($dedPolicy->cap_charges_to_net_pay ?? true) && (bool) ($dedPolicy->carry_forward_unpaid_charges ?? true);
        $chargesDeduction = $capCharges ? min($chargesScheduled, $availableAfterLoans) : $chargesScheduled;

        $availableAfterLoansAndCharges = max(0.0, $availableAfterLoans - $chargesDeduction);
        $capCa = (bool) ($dedPolicy->cap_cash_advance_to_net_pay ?? true);
        if ($capCa) {
            $cashAdvance = min($cashAdvance, $availableAfterLoansAndCharges);
        }

        $deductionsTotal = $baseBeforeLoans + $loanDeduction + $chargesDeduction + $cashAdvance;

        $net = $gross - $deductionsTotal;

        return [
            'employee_id' => $emp->id,
            'emp_no' => $emp->emp_no,
            'name' => $this->employeeName($emp),
            'department' => $emp->department,
            'assignment' => $emp->assignment_type,
            'area_place' => $emp->area_place,
            'external_area' => $emp->external_area,
            'daily_rate' => $this->roundMoney($dailyRate, $proration),
            'ot_hours' => $otOverrideHours,
            'ot_hours_computed' => $computedOtHours,
            'ot_override_on' => $otOverrideOn,
            'ot_override_hours' => $otOverrideOn ? $otOverrideHours : null,
            'ot_pay' => $this->roundMoney($otPay, $proration),
            'attendance_deduction' => $this->roundMoney($attendanceDeduction, $proration),
            'adjustments' => $adjustments,
            'adjustments_earn' => $this->roundMoney($earnAdj, $proration),
            'adjustments_ded' => $this->roundMoney($dedAdj, $proration),
            'sss_ee' => $this->roundMoney($sss['ee'], $proration),
            'philhealth_ee' => $this->roundMoney($philhealth['ee'], $proration),
            'pagibig_ee' => $this->roundMoney($pagibig['ee'], $proration),
            'tax' => $this->roundMoney($withholding, $proration),
            'sss_er' => $this->roundMoney($sss['er'], $proration),
            'philhealth_er' => $this->roundMoney($philhealth['er'], $proration),
            'pagibig_er' => $this->roundMoney($pagibig['er'], $proration),
            'basic_pay_cutoff' => $this->roundMoney($basicCutoff, $proration),
            'allowance_cutoff' => $this->roundMoney($allowanceCutoff, $proration),
            'gross' => $this->roundMoney($gross, $proration),
            'cash_advance' => $this->roundMoney($cashAdvance, $proration),
            'charges_deduction' => $this->roundMoney($chargesDeduction, $proration),
            'loan_deduction' => $this->roundMoney($loanDeduction, $proration),
            'loan_items' => $loanItems,
            'deductions_total' => $this->roundMoney($deductionsTotal, $proration),
            'net_pay' => $this->roundMoney($net, $proration),
            'employer_share_total' => $this->roundMoney($sss['er'] + $philhealth['er'] + $pagibig['er'], $proration),
            'present_days' => $summary['present'],
            'absent_days' => $summary['absent'],
            'leave_days' => $summary['leave'],
            'status' => $this->rowStatus($emp, $summary),
            'payout_method' => $emp->payout_method ?: ($emp->bank_account_number ? 'BANK' : 'CASH'),
            'account_masked' => $emp->bank_account_number ? '****' . substr((string) $emp->bank_account_number, -4) : null,
        ];
    }

    private function computeRows(Collection $employees, Carbon $start, Carbon $end, string $cutoff, ?Collection $overrides, TimekeepingRule $timekeeping, SalaryProrationRule $proration, OvertimeRule $overtime, StatutorySetup $statutory, WithholdingTaxPolicy $wtPolicy, Collection $wtBrackets, ?CashAdvancePolicy $cashPolicy, PayrollDeductionPolicy $dedPolicy, Collection $summaries, Collection $cashAdvances, Collection $loanSchedules, string $runType = 'External'): array
    {
        $rows = [];
        foreach ($employees as $emp) {
            $rows[] = $this->computeForEmployee($emp, $start, $end, $cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $dedPolicy, $summaries, $cashAdvances, $loanSchedules, $runType);
        }
        return $rows;
    }

    private function employeesForFilters(string $assignment, ?string $area, string $runType = 'External'): Collection
    {
        if (is_string($area) && trim(strtolower($area)) === 'all') {
            $area = null;
        }
        $q = Employee::query();
        $q->where(function ($qq) {
            $qq->whereHas('employmentStatus', function ($qs) {
                $qs->whereRaw('LOWER(label) NOT IN (?, ?)', ['inactive', 'resigned']);
            })->orWhereNull('employment_status_id');
        });
        $q->where(function ($qs) {
            $qs->whereNull('status')
                ->orWhereRaw('LOWER(status) NOT IN (?, ?)', ['inactive', 'resigned']);
        });

        if ($runType === 'External') {
            $q->whereRaw("LOWER(TRIM(COALESCE(employment_type, ''))) = 'regular'");
        } else {
            // Internal runs cover non-regular employees (e.g., probationary, trainees).
            $q->whereRaw("LOWER(TRIM(COALESCE(employment_type, ''))) != 'regular'");
        }

        if ($assignment !== 'All') {
            $q->where('assignment_type', $assignment);
        }
        if ($assignment === 'Field' && $area) {
            $q->where('area_place', $area);
        }

        return $q->orderBy('last_name')->orderBy('first_name')->get();
    }

    private function cutoffRange(string $periodMonth, string $cutoff): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        [$y, $m] = array_map('intval', explode('-', $periodMonth));
        $base = Carbon::create($y, $m, 1)->startOfDay();

        if ($cutoff === '11-25') {
            return $this->cutoffWindow($base, (int) ($calendar->cutoff_a_from ?? 11), (int) ($calendar->cutoff_a_to ?? 25));
        }
        return $this->cutoffWindow($base, (int) ($calendar->cutoff_b_from ?? 26), (int) ($calendar->cutoff_b_to ?? 10));
    }

    public function cutoffRangePublic(string $periodMonth, string $cutoff): array
    {
        return $this->cutoffRange($periodMonth, $cutoff);
    }

    private function cutoffWindow(Carbon $base, int $fromDay, int $toDay): array
    {
        $start = $base->copy()->day($fromDay);
        $end = $base->copy()->day($toDay);
        if ($fromDay > $toDay) {
            $end = $end->addMonthNoOverflow();
        }
        return [$start, $end];
    }

    private function workdaysRule(): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        $workMonSat = (bool) ($calendar->work_mon_sat ?? true);
        return [
            'work_mon_sat' => $workMonSat,
        ];
    }

    private function countWorkdaysInMonth(string $periodMonth, array $rule): int
    {
        [$y, $m] = array_map('intval', explode('-', $periodMonth));
        $start = Carbon::create($y, $m, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        return $this->countWorkdaysInRange($start, $end, $rule);
    }

    private function countWorkdaysInRange(Carbon $start, Carbon $end, array $rule): int
    {
        $workMonSat = (bool) ($rule['work_mon_sat'] ?? true);
        $count = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            $dow = (int) $d->dayOfWeekIso; // 1=Mon ... 7=Sun
            $isWorkday = $workMonSat ? $dow <= 6 : $dow <= 5;
            if ($isWorkday) $count++;
            $d->addDay();
        }
        return $count;
    }

    private function summaryFromAggregate($row): array
    {
        if (!$row) {
            return [
                'paid_days' => 0.0,
                'present' => 0.0,
                'absent' => 0.0,
                'leave' => 0.0,
                'minutes_late' => 0,
                'minutes_undertime' => 0,
                'ot_hours' => 0.0,
                'has_any' => false,
            ];
        }

        $present = (float) ($row->present_days ?? 0);
        $absent = (float) ($row->absent_days ?? 0);
        $leave = (float) ($row->leave_days ?? 0);
        $paid = (float) ($row->paid_days ?? 0);
        $minutesLate = (int) ($row->minutes_late ?? 0);
        $minutesUndertime = (int) ($row->minutes_undertime ?? 0);
        $otHours = (float) ($row->ot_hours ?? 0);
        $hasAny = ($present + $absent + $leave) > 0 || $minutesLate > 0 || $minutesUndertime > 0 || $otHours > 0;

        return [
            'paid_days' => $paid,
            'present' => $present,
            'absent' => $absent,
            'leave' => $leave,
            'minutes_late' => $minutesLate,
            'minutes_undertime' => $minutesUndertime,
            'ot_hours' => $otHours,
            'has_any' => $hasAny,
        ];
    }

    private function buildSummariesForPeriod(array $employeeIds, string $periodMonth, string $cutoff, Carbon $start, Carbon $end): void
    {
        if (!$employeeIds) return;

        $query = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        $rule = $this->workdaysRule();
        $workMonSat = (bool) ($rule['work_mon_sat'] ?? true);
        if ($workMonSat) {
            $query->whereRaw("DAYOFWEEK(`date`) != 1");
        } else {
            $query->whereRaw("DAYOFWEEK(`date`) NOT IN (1,7)");
        }

        $rows = $query
            ->selectRaw("
                employee_id,
                SUM(CASE WHEN status IN ('Present','Late','Half-day') THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status IN ('Absent','Unpaid Leave','LOA') THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status IN ('Leave','Paid Leave') THEN 1 ELSE 0 END) as leave_days,
                SUM(CASE
                    WHEN status IN ('Present','Late','Paid Leave','Holiday','Day Off') THEN 1
                    WHEN status = 'Half-day' THEN 0.5
                    ELSE 0
                END) as paid_days,
                SUM(COALESCE(minutes_late, 0)) as minutes_late,
                SUM(COALESCE(minutes_undertime, 0)) as minutes_undertime,
                SUM(COALESCE(ot_hours, 0)) as ot_hours
            ")
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $upsertRows = [];
        foreach ($employeeIds as $empId) {
            $r = $rows->get($empId);
            $upsertRows[] = [
                'employee_id' => $empId,
                'period_month' => $periodMonth,
                'cutoff' => $cutoff,
                'present_days' => (float) ($r->present_days ?? 0),
                'absent_days' => (float) ($r->absent_days ?? 0),
                'leave_days' => (float) ($r->leave_days ?? 0),
                'paid_days' => (float) ($r->paid_days ?? 0),
                'minutes_late' => (int) ($r->minutes_late ?? 0),
                'minutes_undertime' => (int) ($r->minutes_undertime ?? 0),
                'ot_hours' => (float) ($r->ot_hours ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        AttendanceSummary::upsert(
            $upsertRows,
            ['employee_id', 'period_month', 'cutoff'],
            ['present_days', 'absent_days', 'leave_days', 'paid_days', 'minutes_late', 'minutes_undertime', 'ot_hours', 'updated_at']
        );
    }

    private function computeAttendanceDeductions(array $summary, float $dailyRate, TimekeepingRule $timekeeping, SalaryProrationRule $proration): float
    {
        $workMinutes = (int) ($timekeeping->work_minutes_per_day ?? 480);
        $lateMinutes = $this->roundMinutes((int) $summary['minutes_late'], (string) ($timekeeping->late_rounding ?? 'none'));
        $lateDed = 0.0;
        if (($timekeeping->late_rule_type ?? 'rate_based') === 'flat_penalty') {
            $lateDed = $lateMinutes * (float) ($timekeeping->late_penalty_per_minute ?? 0);
        } else {
            $lateDed = $workMinutes > 0 ? ($lateMinutes * ($dailyRate / $workMinutes)) : 0.0;
        }

        $underDed = 0.0;
        if ($timekeeping->undertime_enabled) {
            $underMinutes = $this->roundMinutes((int) $summary['minutes_undertime'], (string) ($timekeeping->late_rounding ?? 'none'));
            if (($timekeeping->undertime_rule_type ?? 'rate_based') === 'flat_penalty') {
                $underDed = $underMinutes * (float) ($timekeeping->undertime_penalty_per_minute ?? 0);
            } else {
                $underDed = $workMinutes > 0 ? ($underMinutes * ($dailyRate / $workMinutes)) : 0.0;
            }
        }

        $absentDed = 0.0;
        if (($proration->salary_mode ?? 'prorate_workdays') === 'fixed_50_50') {
            $absentDed = max(0.0, (float) $summary['absent']) * $dailyRate;
        }

        return $lateDed + $underDed + $absentDed;
    }

    private function roundMinutes(int $minutes, string $rule): int
    {
        if ($rule === 'nearest_5') return (int) (round($minutes / 5) * 5);
        if ($rule === 'nearest_15') return (int) (round($minutes / 15) * 15);
        return $minutes;
    }

    private function roundOtHours(float $hours, string $rule): float
    {
        if ($rule === 'none') return $hours;
        $minutes = $hours * 60;
        if ($rule === 'nearest_15') $minutes = round($minutes / 15) * 15;
        if ($rule === 'nearest_30') $minutes = round($minutes / 30) * 30;
        if ($rule === 'nearest_60') $minutes = round($minutes / 60) * 60;
        if ($rule === 'down_15') $minutes = floor($minutes / 15) * 15;
        if ($rule === 'up_15') $minutes = ceil($minutes / 15) * 15;
        return $minutes / 60;
    }

    private function computeOtPay(float $otHours, float $dailyRate, TimekeepingRule $timekeeping, OvertimeRule $overtime): float
    {
        if ($otHours <= 0) return 0.0;
        if (($overtime->ot_mode ?? 'flat_rate') === 'flat_rate') {
            return $otHours * (float) ($overtime->flat_rate ?? 0);
        }
        $workMinutes = (int) ($timekeeping->work_minutes_per_day ?? 480);
        $hourlyRate = $workMinutes > 0 ? ($dailyRate / ($workMinutes / 60)) : 0.0;
        return $otHours * $hourlyRate * (float) ($overtime->multiplier ?? 1);
    }

    private function computeCashAdvanceDeduction(?CashAdvancePolicy $policy, Collection $advances, string $periodMonth, string $cutoff): float
    {
        if (!$policy || !$policy->enabled) return 0.0;
        if ($advances->isEmpty()) return 0.0;

        $cutoffOffset = $cutoff === '26-10' ? 1 : 0;
        $period = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();

        $total = 0.0;
        foreach ($advances as $a) {
            $status = strtolower(trim((string) ($a->status ?? '')));
            if ($status !== 'active') continue;

            $balance = (float) ($a->balance_remaining ?? $a->amount ?? 0);
            if ($balance <= 0) continue;

            $startMonth = $a->start_month ? Carbon::parse($a->start_month)->startOfMonth() : null;
            if (!$startMonth || $period->lt($startMonth)) continue;

            $fullDeduct = (bool) ($a->full_deduct ?? false);
            if ($fullDeduct) {
                $total += $balance;
                continue;
            }

            $termMonths = max(1, (int) ($a->term_months ?? 1));
            $monthsDiff = $startMonth->diffInMonths($period, false);
            $cutoffIndex = ($monthsDiff * 2) + $cutoffOffset;
            $totalCutoffs = $termMonths * 2;
            $remainingCutoffs = $totalCutoffs - $cutoffIndex;
            if ($remainingCutoffs < 1) {
                $remainingCutoffs = 1;
            }

            $due = round($balance / $remainingCutoffs, 2);
            $total += min($balance, max(0.0, $due));
        }

        return $total;
    }

    private function computeSss(StatutorySetup $statutory, float $basicPay, string $cutoff): array
    {
        $table = $statutory->sss_table;
        $ee = 0.0;
        $er = 0.0;

        // If an SSS table is uploaded, use it. Otherwise fall back to percent.
        $usedTable = false;
        if (is_array($table) && !empty($table['rows'])) {
            $mapped = $this->mapSssTable($table);
            foreach ($mapped as $row) {
                if ($basicPay >= $row['from'] && ($row['to'] === null || $basicPay <= $row['to'])) {
                    $ee = (float) ($row['ee'] ?? 0);
                    $er = (float) ($row['er'] ?? 0);
                    $usedTable = true;
                    break;
                }
            }
        }

        $eeRate = (float) ($statutory->sss_ee_percent ?? 0);
        $erRate = (float) ($statutory->sss_er_percent ?? 0);
        if ((!$usedTable || ($ee == 0.0 && $er == 0.0)) && ($eeRate > 0 || $erRate > 0)) {
            // Fallback to percent if table is missing or has no match.
            $ee = $basicPay * ($eeRate / 100);
            $er = $basicPay * ($erRate / 100);
        } else {
            // If table provided only one side, fill missing side using percent.
            if ($ee == 0.0 && $eeRate > 0) {
                $ee = $basicPay * ($eeRate / 100);
            }
            if ($er == 0.0 && $erRate > 0) {
                $er = $basicPay * ($erRate / 100);
            }
        }

        $splitRule = $this->normalizeSplitRule($statutory->sss_split_rule ?? 'monthly');
        $split = $this->applySplitRule($ee, $er, $splitRule, $cutoff);
        return [
            'ee' => $split['ee'],
            'er' => $split['er'],
            'ee_full' => $ee,
            'er_full' => $er,
        ];
    }

    private function mapSssTable(array $table): array
    {
        $header = array_map(fn ($h) => $this->normalizeHeader($h), $table['header'] ?? []);
        $rows = $table['rows'] ?? [];

        $fromIdx = $this->findHeaderIndex($header, ['from', 'rangefrom', 'compfrom', 'salaryfrom', 'min']);
        $toIdx = $this->findHeaderIndex($header, ['to', 'rangeto', 'compto', 'salaryto', 'max', 'upto']);
        $rangeIdx = $this->findHeaderIndex($header, [
            'rangeofcompensation',
            'rangeofcomp',
            'rangeofcompe',
            'range',
            'compensation',
            'compensationrange',
            'salaryrange',
            'ofcompensation',
            'ofcomp',
            'ofcompe',
        ]);
        $mscIdx = $this->findHeaderIndex($header, ['msc', 'monthlysalarycredit', 'salarycredit', 'monthlysalary', 'mscregularsss']);
        $eeIdx = $this->findHeaderIndex($header, [
            'ee',
            'employee',
            'employeeshare',
            'sssee',
            'employeeshareee',
            'ssseecontribution',
            'eeshare',
            'employeesss',
            'regularee',
            // Support normalized "ss_ee" headers coming from the UI import.
            'ssee',
            'sseeshare',
            // Common exports with explicit totals.
            'eetotal',
        ]);
        $erIdx = $this->findHeaderIndex($header, [
            'er',
            'employer',
            'employershare',
            'ssser',
            'employershareer',
            'sssercontribution',
            'ershare',
            'employersss',
            'regularer',
            // Support normalized "ss_er" headers coming from the UI import.
            'sser',
            'ssershare',
            // Common exports with explicit totals.
            'ertotal',
        ]);
        $ecIdx = $this->findHeaderIndex($header, ['ec', 'employercompensation', 'employercomp', 'employercontributionec']);
        $totalIdx = $this->findHeaderIndex($header, ['total', 'totalcontribution', 'totalcontributions', 'totalss', 'totalsscontribution']);

        if ($rangeIdx < 0) {
            foreach ($header as $i => $h) {
                if (str_contains($h, 'range') || str_contains($h, 'comp')) {
                    $rangeIdx = $i;
                    break;
                }
            }
        }
        $erHeader = $erIdx >= 0 ? $header[$erIdx] : null;
        $erIsTotal = in_array($erHeader, ['ertotal', 'totaler', 'ercontributiontotal'], true);

        $out = [];
        foreach ($rows as $r) {
            $rangeText = $rangeIdx >= 0 ? (string) ($r[$rangeIdx] ?? '') : '';
            [$from, $to] = $this->parseRange($rangeText);
            if ($from === null) $from = $this->parseNumber($r[$fromIdx] ?? null);
            if ($to === null) $to = $this->parseNumber($r[$toIdx] ?? null);
            if ($from === null && $mscIdx >= 0) {
                $from = $this->parseNumber($r[$mscIdx] ?? null);
                if ($to === null) $to = $from;
            }
            if ($from === null && $rangeIdx < 0 && $fromIdx < 0 && $toIdx < 0) {
                $rangeText = (string) ($r[0] ?? '');
                [$from, $to] = $this->parseRange($rangeText);
            }
            $ee = $this->parseNumber($r[$eeIdx] ?? null);
            $er = $this->parseNumber($r[$erIdx] ?? null);
            $ec = $this->parseNumber($r[$ecIdx] ?? null);
            $total = $this->parseNumber($r[$totalIdx] ?? null);
            if ($ec !== null && !$erIsTotal) {
                $er = ($er ?? 0) + $ec;
            }
            if ($total !== null) {
                if ($ee === null && $er !== null) {
                    $ee = max(0.0, $total - $er);
                } elseif ($er === null && $ee !== null) {
                    $er = max(0.0, $total - $ee);
                } elseif ($ee === null && $er === null) {
                    $ee = $total;
                    $er = 0.0;
                } elseif ($ee !== null && $er !== null && $er < $ee) {
                    // If total is provided and ER looks too small, derive ER from total.
                    $er = max(0.0, $total - $ee);
                }
            }
            if ($from === null || $ee === null) continue;
            $out[] = [
                'from' => $from,
                'to' => $to,
                'ee' => $ee ?? 0,
                'er' => $er ?? 0,
            ];
        }
        return $out;
    }

    private function computePhilHealth(StatutorySetup $statutory, float $basicPay, string $cutoff): array
    {
        $base = $basicPay;
        if (($statutory->ph_min_cap ?? 0) > 0) {
            $base = max($base, (float) $statutory->ph_min_cap);
        }
        if (($statutory->ph_max_cap ?? 0) > 0) {
            $base = min($base, (float) $statutory->ph_max_cap);
        }
        $ee = $base * ((float) ($statutory->ph_ee_percent ?? 0) / 100);
        $er = $base * ((float) ($statutory->ph_er_percent ?? 0) / 100);
        $split = $this->applySplitRule($ee, $er, $this->normalizeSplitRule($statutory->ph_split_rule ?? 'monthly'), $cutoff);
        return [
            'ee' => $split['ee'],
            'er' => $split['er'],
            'ee_full' => $ee,
            'er_full' => $er,
        ];
    }

    private function computePagibig(StatutorySetup $statutory, float $basicPay, string $cutoff): array
    {
        $cap = (float) ($statutory->pi_cap ?? 0);
        if ($cap <= 0) $cap = 5000.0;

        $threshold = (float) ($statutory->pi_ee_threshold ?? 1500);
        $lowRate = (float) ($statutory->pi_ee_percent_low ?? 1);
        $highRate = (float) ($statutory->pi_ee_percent ?? 2);
        $erRate = (float) ($statutory->pi_er_percent ?? 2);

        $base = min($basicPay, $cap);
        $eeRate = $basicPay <= $threshold ? $lowRate : $highRate;
        $ee = $base * ($eeRate / 100);
        $er = $base * ($erRate / 100);

        $split = $this->applySplitRule($ee, $er, $this->normalizeSplitRule($statutory->pi_split_rule ?? 'monthly'), $cutoff);
        return [
            'ee' => $split['ee'],
            'er' => $split['er'],
            'ee_full' => $ee,
            'er_full' => $er,
        ];
    }

    private function computeWithholdingTax(WithholdingTaxPolicy $policy, Collection $brackets, float $taxable, string $cutoff): float
    {
        if (!$policy->enabled) return 0.0;
        $splitRule = $this->normalizeSplitRule($policy->split_rule ?? 'monthly');
        if ($policy->method === 'manual_fixed') {
            return $this->applySplitRuleSingle((float) $policy->fixed_amount, $splitRule, $cutoff);
        }
        if ($policy->method === 'manual_percent') {
            $amount = $taxable * ((float) $policy->percent / 100);
            return $this->applySplitRuleSingle($amount, $splitRule, $cutoff);
        }

        $periodsPerMonth = $this->periodsPerMonth((string) $policy->pay_frequency);
        $timing = (string) ($policy->timing ?? 'monthly');
        $taxableBase = $taxable;
        if ($timing === 'monthly') {
            $taxableBase = $taxable * $periodsPerMonth;
        }

        $originalBrackets = $brackets->values();

        // When timing is monthly, taxableBase is monthly; prefer monthly tax brackets to avoid using
        // semi-monthly/weekly ranges against a monthly base (which can dramatically over-withhold).
        $targetFreq = $timing === 'monthly' ? 'monthly' : (string) $policy->pay_frequency;

        $filtered = $originalBrackets->filter(function ($b) use ($targetFreq) {
            if (!$b->pay_frequency) return true;
            return $b->pay_frequency === $targetFreq;
        })->values();

        // Fallbacks for legacy tables (no frequency column) or mis-tagged imports.
        if ($filtered->isEmpty() && $timing === 'monthly' && (string) $policy->pay_frequency !== 'monthly') {
            $fallback = $originalBrackets->filter(function ($b) use ($policy) {
                if (!$b->pay_frequency) return true;
                return $b->pay_frequency === $policy->pay_frequency;
            })->values();
            $filtered = $fallback->isEmpty() ? $originalBrackets : $fallback;
        }

        $brackets = $filtered->isEmpty() ? $originalBrackets : $filtered;

        $amount = 0.0;
        foreach ($brackets as $b) {
            $from = (float) $b->bracket_from;
            $to = $b->bracket_to !== null ? (float) $b->bracket_to : null;
            if ($taxableBase >= $from && ($to === null || $taxableBase <= $to)) {
                $amount = (float) $b->base_tax + (($taxableBase - $from) * ((float) $b->excess_percent / 100));
                break;
            }
        }

        if ($timing === 'monthly') {
            $amount = $this->applySplitRuleSingle($amount, $splitRule, $cutoff);
        }

        return $amount;
    }

    private function applySplitRule(float $ee, float $er, string $rule, string $cutoff): array
    {
        $isFirst = $cutoff === '11-25';
        if ($rule === 'cutoff1_only' || $rule === 'monthly') {
            return ['ee' => $isFirst ? $ee : 0.0, 'er' => $isFirst ? $er : 0.0];
        }
        if ($rule === 'cutoff2_only') {
            return ['ee' => $isFirst ? 0.0 : $ee, 'er' => $isFirst ? 0.0 : $er];
        }
        if ($rule === 'split_cutoffs') {
            return ['ee' => $ee / 2, 'er' => $er / 2];
        }
        return ['ee' => $ee, 'er' => $er];
    }

    private function applySplitRuleSingle(float $amount, string $rule, string $cutoff): float
    {
        $isFirst = $cutoff === '11-25';
        if ($rule === 'cutoff1_only' || $rule === 'monthly') return $isFirst ? $amount : 0.0;
        if ($rule === 'cutoff2_only') return $isFirst ? 0.0 : $amount;
        if ($rule === 'split_cutoffs') return $amount / 2;
        return $amount;
    }

    private function computeLoanDeductions(Collection $loanSchedules, float $gross, float $baseDeductions): array
    {
        $available = max(0.0, $gross - $baseDeductions);
        if ($loanSchedules->isEmpty()) {
            return [0.0, []];
        }

        $ordered = $loanSchedules->sortBy(function ($s) {
            $priority = (int) ($s->loan?->priority_order ?? 100);
            $loanId = (int) ($s->employee_loan_id ?? 0);
            $cutoffWeight = ($s->due_cutoff ?? '') === '11-25' ? 1 : 2;
            return [$priority, $loanId, $cutoffWeight, $s->id];
        });

        $balances = [];
        $items = [];
        $total = 0.0;

        foreach ($ordered as $s) {
            $loan = $s->loan;
            if (!$loan || $loan->status !== 'active' || !$loan->auto_deduct) {
                $items[] = [
                    'loan_id' => $loan?->id,
                    'loan_type' => $loan?->loan_type ?? 'Loan',
                    'schedule_id' => $s->id,
                    'scheduled_amount' => (float) $s->amount,
                    'deducted_amount' => 0.0,
                    'balance_before' => (float) ($loan?->balance_remaining ?? 0),
                    'balance_after' => (float) ($loan?->balance_remaining ?? 0),
                    'status' => 'skipped',
                ];
                continue;
            }

            $balance = $balances[$loan->id] ?? (float) ($loan->balance_remaining ?? 0);
            $scheduled = (float) $s->amount;
            $capped = $loan->stop_on_zero ? min($scheduled, max(0.0, $balance)) : $scheduled;

            if ($capped <= 0) {
                $items[] = [
                    'loan_id' => $loan->id,
                    'loan_type' => $loan->loan_type,
                    'schedule_id' => $s->id,
                    'scheduled_amount' => 0.0,
                    'deducted_amount' => 0.0,
                    'balance_before' => $balance,
                    'balance_after' => $balance,
                    'status' => 'skipped',
                ];
                continue;
            }

            $deducted = $loan->allow_partial ? min($capped, $available) : ($available >= $capped ? $capped : 0.0);
            $status = $deducted <= 0 ? 'skipped' : ($deducted < $capped ? 'partial' : 'deducted');
            $balanceAfter = max(0.0, $balance - $deducted);

            $available = max(0.0, $available - $deducted);
            $total += $deducted;
            $balances[$loan->id] = $balanceAfter;

            $items[] = [
                'loan_id' => $loan->id,
                'loan_type' => $loan->loan_type,
                'schedule_id' => $s->id,
                'scheduled_amount' => $capped,
                'deducted_amount' => $deducted,
                'balance_before' => $balance,
                'balance_after' => $balanceAfter,
                'status' => $status,
            ];
        }

        return [$total, $items];
    }

    private function normalizeSplitRule(?string $rule): string
    {
        $r = strtolower(trim((string) $rule));
        $aliases = [
            'split' => 'split_cutoffs',
            'splitboth' => 'split_cutoffs',
            'split_both' => 'split_cutoffs',
            'splitbothcutoffs' => 'split_cutoffs',
            'split_both_cutoffs' => 'split_cutoffs',
            'cutoff1' => 'cutoff1_only',
            'cutoff2' => 'cutoff2_only',
            'first' => 'cutoff1_only',
            'second' => 'cutoff2_only',
        ];
        if (array_key_exists($r, $aliases)) {
            $r = $aliases[$r];
        }
        $allowed = ['monthly', 'split_cutoffs', 'cutoff1_only', 'cutoff2_only'];
        return in_array($r, $allowed, true) ? $r : 'monthly';
    }

    private function periodsPerMonth(string $freq): int
    {
        if ($freq === 'weekly') return 4;
        if ($freq === 'monthly') return 1;
        return 2;
    }

    private function normalizeHeader($h): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $h));
    }

    private function findHeaderIndex(array $headers, array $keys): int
    {
        foreach ($headers as $i => $h) {
            if (in_array($h, $keys, true)) return $i;
        }
        return -1;
    }

    private function parseRange(string $text): array
    {
        $nums = preg_match_all('/[\d,.]+/', $text, $m) ? $m[0] : [];
        $values = array_map(fn ($n) => $this->parseNumber($n), $nums);
        $values = array_values(array_filter($values, fn ($n) => $n !== null));
        if (empty($values)) return [null, null];
        if (str_contains(strtolower($text), 'and below') || str_contains(strtolower($text), 'below')) {
            return [0.0, $values[0]];
        }
        if (str_contains(strtolower($text), 'and above') || str_contains(strtolower($text), 'above')) {
            return [$values[0], null];
        }
        if (count($values) >= 2) return [$values[0], $values[1]];
        return [$values[0], null];
    }

    private function parseNumber($raw): ?float
    {
        if ($raw === null) return null;
        $cleaned = (string) $raw;
        $cleaned = str_ireplace(["PHP"], "", $cleaned);
        // Strip commas/whitespace and keep only digits, dot, minus.
        $cleaned = str_replace([",", " "], "", $cleaned);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);
        if ($cleaned === '') return null;
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function roundMoney(float $value, SalaryProrationRule $proration): float
    {
        $rule = (string) ($proration->money_rounding ?? '2_decimals');
        if ($rule === 'nearest_peso') {
            return round($value, 0);
        }
        return round($value, 2);
    }

    private function rowStatus(Employee $emp, array $summary): string
    {
        if (!$summary['has_any']) return 'Missing Attendance';
        if (!$emp->basic_pay || (float) $emp->basic_pay <= 0) return 'Missing Basic Pay';
        return 'Ready';
    }

    private function employeeName(Employee $emp): string
    {
        return trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''));
    }

    private function hasGovId(?string $value): bool
    {
        $raw = trim((string) $value);
        if ($raw === '') return false;
        $digits = preg_replace('/\D+/', '', $raw);
        return $digits !== '';
    }
}
