<?php

namespace App\Services\Payroll;

use App\Models\AttendanceCodeSetting;
use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\CashAdvancePolicy;
use App\Models\Employee;
use App\Models\OvertimeRule;
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
    public function compute(string $periodMonth, string $cutoff, string $assignment = 'All', ?string $area = null, ?Collection $overrides = null): array
    {
        [$start, $end] = $this->cutoffRange($periodMonth, $cutoff);
        $employees = $this->employeesForFilters($assignment, $area);

        $timekeeping = TimekeepingRule::query()->first() ?? TimekeepingRule::create([]);
        $proration = SalaryProrationRule::query()->first() ?? SalaryProrationRule::create([]);
        $overtime = OvertimeRule::query()->first() ?? OvertimeRule::create([]);
        $statutory = StatutorySetup::query()->first() ?? StatutorySetup::create([]);
        $wtPolicy = WithholdingTaxPolicy::query()->first() ?? WithholdingTaxPolicy::create([]);
        $wtBrackets = WithholdingTaxBracket::query()->orderBy('sort_order')->get();
        $cashPolicy = CashAdvancePolicy::query()->first();
        $attDefaults = AttendanceCodeSetting::query()->first();

        $employeeIds = $employees->pluck('id')->all();
        $attendance = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $cashAdvances = CashAdvance::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'Active')
            ->get()
            ->groupBy('employee_id');

        $rows = $this->computeRows($employees, $start, $end, $cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $attendance, $cashAdvances);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'rows' => $rows,
        ];
    }

    public function computeForEmployee(Employee $emp, Carbon $start, Carbon $end, string $cutoff, ?Collection $overrides, TimekeepingRule $timekeeping, SalaryProrationRule $proration, OvertimeRule $overtime, StatutorySetup $statutory, WithholdingTaxPolicy $wtPolicy, Collection $wtBrackets, ?CashAdvancePolicy $cashPolicy, Collection $attendance, Collection $cashAdvances): array
    {
        $records = $attendance->get($emp->id, collect());
        $summary = $this->attendanceSummary($records, $start, $end, $this->workdaysRule());
        $workdaysInMonth = 26;
        $dailyRate = (float) $emp->basic_pay / $workdaysInMonth;
        $allowanceDaily = (float) $emp->allowance / $workdaysInMonth;

        if (($proration->salary_mode ?? 'prorate_workdays') === 'fixed_50_50') {
            $basicCutoff = (float) $emp->basic_pay / 2;
            $allowanceCutoff = (float) $emp->allowance / 2;
        } else {
            $basicCutoff = $dailyRate * $summary['paid_days'];
            $allowanceCutoff = $allowanceDaily * $summary['paid_days'];
        }

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

        $sss = $this->computeSss($statutory, (float) $emp->basic_pay, $cutoff);
        $philhealth = $this->computePhilHealth($statutory, (float) $emp->basic_pay, $cutoff);
        $pagibig = $this->computePagibig($statutory, (float) $emp->basic_pay, $cutoff);

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

        $withholding = $this->computeWithholdingTax(
            $wtPolicy,
            $wtBrackets,
            $taxable,
            $cutoff
        );

        $deductionsTotal = $attendanceDeduction
            + $dedAdj
            + $cashAdvance
            + $sss['ee']
            + $philhealth['ee']
            + $pagibig['ee']
            + $withholding;

        $net = $gross - $deductionsTotal;

        return [
            'employee_id' => $emp->id,
            'emp_no' => $emp->emp_no,
            'name' => $this->employeeName($emp),
            'department' => $emp->department,
            'assignment' => $emp->assignment_type,
            'area_place' => $emp->area_place,
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

    private function computeRows(Collection $employees, Carbon $start, Carbon $end, string $cutoff, ?Collection $overrides, TimekeepingRule $timekeeping, SalaryProrationRule $proration, OvertimeRule $overtime, StatutorySetup $statutory, WithholdingTaxPolicy $wtPolicy, Collection $wtBrackets, ?CashAdvancePolicy $cashPolicy, Collection $attendance, Collection $cashAdvances): array
    {
        $rows = [];
        foreach ($employees as $emp) {
            $rows[] = $this->computeForEmployee($emp, $start, $end, $cutoff, $overrides, $timekeeping, $proration, $overtime, $statutory, $wtPolicy, $wtBrackets, $cashPolicy, $attendance, $cashAdvances);
        }
        return $rows;
    }

    private function employeesForFilters(string $assignment, ?string $area): Collection
    {
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

        if ($assignment !== 'All') {
            $q->where('assignment_type', $assignment);
        }
        if ($assignment === 'Area' && $area) {
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

    private function attendanceSummary(Collection $records, Carbon $start, Carbon $end, array $rule): array
    {
        $map = $records->keyBy(fn ($r) => $r->date?->format('Y-m-d'));
        $paidDays = 0.0;
        $present = 0;
        $absent = 0;
        $leave = 0;
        $minutesLate = 0;
        $minutesUndertime = 0;
        $otHours = 0.0;

        $d = $start->copy();
        while ($d->lte($end)) {
            $dow = (int) $d->dayOfWeekIso;
            $isWorkday = ($rule['work_mon_sat'] ?? true) ? $dow <= 6 : $dow <= 5;
            if ($isWorkday) {
                $key = $d->format('Y-m-d');
                $rec = $map->get($key);
                $status = $rec?->status ?? null;
                $paidDays += $this->paidDayFraction($status);
                $present += in_array($status, ['Present', 'Late', 'Half-day'], true) ? 1 : 0;
                $absent += in_array($status, ['Absent', 'Unpaid Leave', 'LOA'], true) ? 1 : 0;
                $leave += in_array($status, ['Leave', 'Paid Leave'], true) ? 1 : 0;
                $minutesLate += (int) ($rec?->minutes_late ?? 0);
                $minutesUndertime += (int) ($rec?->minutes_undertime ?? 0);
                $otHours += (float) ($rec?->ot_hours ?? 0);
            }
            $d->addDay();
        }

        return [
            'paid_days' => $paidDays,
            'present' => $present,
            'absent' => $absent,
            'leave' => $leave,
            'minutes_late' => $minutesLate,
            'minutes_undertime' => $minutesUndertime,
            'ot_hours' => $otHours,
            'has_any' => $records->isNotEmpty(),
        ];
    }

    private function paidDayFraction(?string $status): float
    {
        if ($status === null) return 0.0;
        $paid = [
            'Present',
            'Late',
            'Paid Leave',
            'Holiday',
            'Day Off',
        ];
        if (in_array($status, $paid, true)) return 1.0;
        if ($status === 'Half-day') return 0.5;
        return 0.0;
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

    private function computeCashAdvanceDeduction(?CashAdvancePolicy $policy, Collection $advances, string $cutoff): float
    {
        if (!$policy || !$policy->enabled) return 0.0;
        if ($advances->isEmpty()) return 0.0;
        $timing = $policy->deduct_timing ?? 'split';
        $isFirst = $cutoff === '11-25';
        if ($timing === 'cutoff1' && !$isFirst) return 0.0;
        if ($timing === 'cutoff2' && $isFirst) return 0.0;
        return (float) $advances->sum(fn ($a) => (float) ($a->per_cutoff_deduction ?? 0));
    }

    private function computeSss(StatutorySetup $statutory, float $basicPay, string $cutoff): array
    {
        $table = $statutory->sss_table;
        $ee = 0.0;
        $er = 0.0;

        $eeRate = (float) ($statutory->sss_ee_percent ?? 0);
        $erRate = (float) ($statutory->sss_er_percent ?? 0);
        if ($eeRate > 0 || $erRate > 0) {
            $ee = $basicPay * ($eeRate / 100);
            $er = $basicPay * ($erRate / 100);
        } elseif (is_array($table) && !empty($table['rows'])) {
            $mapped = $this->mapSssTable($table);
            foreach ($mapped as $row) {
                if ($basicPay >= $row['from'] && ($row['to'] === null || $basicPay <= $row['to'])) {
                    $ee = (float) ($row['ee'] ?? 0);
                    $er = (float) ($row['er'] ?? 0);
                    break;
                }
            }
        }

        $splitRule = (string) ($statutory->sss_split_rule ?? 'monthly');
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
        $rangeIdx = $this->findHeaderIndex($header, ['rangeofcompensation', 'range', 'compensation', 'salaryrange']);
        $eeIdx = $this->findHeaderIndex($header, ['ee', 'employee', 'employeeshare', 'sssee', 'employeeshareee']);
        $erIdx = $this->findHeaderIndex($header, ['er', 'employer', 'employershare', 'ssser', 'employershareer']);
        $ecIdx = $this->findHeaderIndex($header, ['ec', 'employercompensation', 'employercomp', 'employercontributionec']);
        $totalIdx = $this->findHeaderIndex($header, ['total', 'totalcontribution', 'totalcontributions']);

        $out = [];
        foreach ($rows as $r) {
            $rangeText = $rangeIdx >= 0 ? (string) ($r[$rangeIdx] ?? '') : '';
            [$from, $to] = $this->parseRange($rangeText);
            if ($from === null) $from = $this->parseNumber($r[$fromIdx] ?? null);
            if ($to === null) $to = $this->parseNumber($r[$toIdx] ?? null);
            $ee = $this->parseNumber($r[$eeIdx] ?? null);
            $er = $this->parseNumber($r[$erIdx] ?? null);
            $ec = $this->parseNumber($r[$ecIdx] ?? null);
            $total = $this->parseNumber($r[$totalIdx] ?? null);
            if ($ec !== null) {
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
        $split = $this->applySplitRule($ee, $er, (string) ($statutory->ph_split_rule ?? 'monthly'), $cutoff);
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

        $split = $this->applySplitRule($ee, $er, (string) ($statutory->pi_split_rule ?? 'monthly'), $cutoff);
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
        if ($policy->method === 'manual_fixed') {
            return $this->applySplitRuleSingle((float) $policy->fixed_amount, (string) ($policy->split_rule ?? 'monthly'), $cutoff);
        }
        if ($policy->method === 'manual_percent') {
            $amount = $taxable * ((float) $policy->percent / 100);
            return $this->applySplitRuleSingle($amount, (string) ($policy->split_rule ?? 'monthly'), $cutoff);
        }

        $periodsPerMonth = $this->periodsPerMonth((string) $policy->pay_frequency);
        $timing = (string) ($policy->timing ?? 'monthly');
        $taxableBase = $taxable;
        if ($timing === 'monthly') {
            $taxableBase = $taxable * $periodsPerMonth;
        }

        $brackets = $brackets->filter(function ($b) use ($policy) {
            if (!$b->pay_frequency) return true;
            return $b->pay_frequency === $policy->pay_frequency;
        })->values();
        if ($brackets->isEmpty()) {
            $brackets = $brackets->values();
        }

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
            $amount = $this->applySplitRuleSingle($amount, (string) ($policy->split_rule ?? 'monthly'), $cutoff);
        }

        return $amount;
    }

    private function applySplitRule(float $ee, float $er, string $rule, string $cutoff): array
    {
        $isFirst = $cutoff === '11-25';
        if ($rule === 'cutoff1_only') {
            return ['ee' => $isFirst ? $ee : 0.0, 'er' => $isFirst ? $er : 0.0];
        }
        if ($rule === 'cutoff2_only' || $rule === 'monthly') {
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
        if ($rule === 'cutoff1_only') return $isFirst ? $amount : 0.0;
        if ($rule === 'cutoff2_only' || $rule === 'monthly') return $isFirst ? 0.0 : $amount;
        if ($rule === 'split_cutoffs') return $amount / 2;
        return $amount;
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
        $cleaned = preg_replace('/[₱,%\s]/', '', (string) $raw);
        $cleaned = str_replace(',', '', $cleaned);
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
}
