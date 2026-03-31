<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSummary;
use App\Models\PayrollCalendarSetting;
use Carbon\Carbon;

class AttendanceSummaryService
{
    public function refreshForEmployeeDate(int $employeeId, string $date): void
    {
        [$periodMonth, $cutoff] = $this->periodForDate($date);
        [$start, $end] = $this->cutoffRange($periodMonth, $cutoff);
        $this->refreshForEmployeeRange($employeeId, $periodMonth, $cutoff, $start, $end);
    }

    public function refreshForEmployeePeriod(int $employeeId, string $periodMonth, string $cutoff): void
    {
        [$start, $end] = $this->cutoffRange($periodMonth, $cutoff);
        $this->refreshForEmployeeRange($employeeId, $periodMonth, $cutoff, $start, $end);
    }

    private function refreshForEmployeeRange(int $employeeId, string $periodMonth, string $cutoff, Carbon $start, Carbon $end): void
    {
        $query = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        $this->applyWorkdayFilter($query);

        $row = $query
            ->selectRaw("
                SUM(CASE WHEN status IN ('Present','Late','Half-day') THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status IN ('Absent','Unpaid Leave','LOA') THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status IN ('Leave','Paid Leave') THEN 1 ELSE 0 END) as leave_days,
                SUM(CASE
                    WHEN status IN ('Present','Late','Paid Leave','Holiday') THEN 1
                    WHEN status = 'Half-day' THEN 0.5
                    ELSE 0
                END) as paid_days,
                SUM(COALESCE(minutes_late, 0)) as minutes_late,
                SUM(COALESCE(minutes_undertime, 0)) as minutes_undertime,
                SUM(COALESCE(ot_hours, 0)) as ot_hours
            ")
            ->first();

        AttendanceSummary::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'period_month' => $periodMonth,
                'cutoff' => $cutoff,
            ],
            [
                'present_days' => (float) ($row->present_days ?? 0),
                'absent_days' => (float) ($row->absent_days ?? 0),
                'leave_days' => (float) ($row->leave_days ?? 0),
                'paid_days' => (float) ($row->paid_days ?? 0),
                'minutes_late' => (int) ($row->minutes_late ?? 0),
                'minutes_undertime' => (int) ($row->minutes_undertime ?? 0),
                'ot_hours' => (float) ($row->ot_hours ?? 0),
            ]
        );
    }

    private function periodForDate(string $date): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);

        $cutoffAFrom = (int) ($calendar->cutoff_a_from ?? 11);
        $cutoffATo = (int) ($calendar->cutoff_a_to ?? 25);
        $cutoffBFrom = (int) ($calendar->cutoff_b_from ?? 26);
        $cutoffBTo = (int) ($calendar->cutoff_b_to ?? 10);

        $d = Carbon::parse($date)->startOfDay();
        $day = (int) $d->day;

        // Cutoff A: always within the same month (e.g., 11-25)
        if ($cutoffAFrom <= $cutoffATo && $day >= $cutoffAFrom && $day <= $cutoffATo) {
            return [$d->format('Y-m'), '11-25'];
        }

        // Cutoff B usually spans months (e.g., 26-10)
        if ($cutoffBFrom > $cutoffBTo) {
            if ($day >= $cutoffBFrom) {
                return [$d->format('Y-m'), '26-10'];
            }
            if ($day <= $cutoffBTo) {
                return [$d->copy()->subMonthNoOverflow()->format('Y-m'), '26-10'];
            }
        } else {
            if ($day >= $cutoffBFrom && $day <= $cutoffBTo) {
                return [$d->format('Y-m'), '26-10'];
            }
        }

        // Fallback: map to closest cutoff A in current month.
        return [$d->format('Y-m'), '11-25'];
    }

    private function cutoffRange(string $periodMonth, string $cutoff): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        [$y, $m] = array_map('intval', explode('-', $periodMonth));
        $base = Carbon::create($y, $m, 1)->startOfDay();

        if ($cutoff === '11-25') {
            $from = (int) ($calendar->cutoff_a_from ?? 11);
            $to = (int) ($calendar->cutoff_a_to ?? 25);
            return $this->cutoffWindow($base, $from, $to);
        }

        $from = (int) ($calendar->cutoff_b_from ?? 26);
        $to = (int) ($calendar->cutoff_b_to ?? 10);
        return $this->cutoffWindow($base, $from, $to);
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

    private function applyWorkdayFilter($query): void
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        $workMonSat = (bool) ($calendar->work_mon_sat ?? true);
        if ($workMonSat) {
            $query->whereRaw("DAYOFWEEK(`date`) != 1");
        } else {
            $query->whereRaw("DAYOFWEEK(`date`) NOT IN (1,7)");
        }
    }
}
