<?php

namespace App\Services\Attendance;

use App\Models\AttendanceCode;

class AttendanceStatusRuleService
{
    private const CODE_STATUS_MAP = [
        'P' => 'Present',
        'L' => 'Late',
        'A' => 'Absent',
        'PL' => 'Paid Leave',
        'HD' => 'Half-day',
        'OFF' => 'Day Off',
        'HOL' => 'Holiday',
        'RNR' => 'RNR',
        'LOA' => 'LOA',
        'UL' => 'Absent',
        'LWOP' => 'Absent',
    ];

    private ?array $cachedBuckets = null;

    public function buckets(): array
    {
        if ($this->cachedBuckets !== null) {
            return $this->cachedBuckets;
        }

        $rows = AttendanceCode::query()
            ->get(['code', 'description', 'counts_as_present', 'counts_as_paid', 'affects_deductions']);

        if ($rows->isEmpty()) {
            $this->cachedBuckets = [
                'present' => [],
                'late' => [],
                'paid' => [],
                'absent' => [],
                'leave' => [],
                'half_day' => [],
            ];
            return $this->cachedBuckets;
        }

        $present = [];
        $paid = [];
        $late = [];
        $absent = [];
        $leave = [];
        $halfDay = [];

        foreach ($rows as $row) {
            $codeKey = strtoupper(trim((string) ($row->code ?? '')));
            $statuses = $this->statusesForCodeAndDescription((string) ($row->code ?? ''), (string) ($row->description ?? ''));
            foreach ($statuses as $status) {
                $key = $this->key($status);
                if ($key === '') {
                    continue;
                }

                if ((bool) $row->counts_as_present) {
                    $present[$key] = $status;
                }
                if ((bool) $row->counts_as_paid) {
                    $paid[$key] = $status;
                }
                if ($codeKey === 'L' || str_contains(strtolower($status), 'late')) {
                    $late[$key] = $status;
                }
                if (!(bool) $row->counts_as_present && !(bool) $row->counts_as_paid && (bool) $row->affects_deductions) {
                    $absent[$key] = $status;
                }

                $lowerStatus = strtolower($status);
                if (str_contains($lowerStatus, 'leave')) {
                    $leave[$key] = $status;
                }
                if (str_contains($lowerStatus, 'half')) {
                    $halfDay[$key] = $status;
                }
            }
        }

        $this->cachedBuckets = [
            'present' => array_values($present),
            'late' => array_values($late),
            'paid' => array_values($paid),
            'absent' => array_values($absent),
            'leave' => array_values($leave),
            'half_day' => array_values($halfDay),
        ];

        return $this->cachedBuckets;
    }

    public function summarize(iterable $rows, string $assignmentType = ''): array
    {
        $buckets = $this->buckets();
        $presentSet = $this->toSet($buckets['present']);
        $paidSet = $this->toSet($buckets['paid']);
        $lateSet = $this->toSet($buckets['late']);
        $absentSet = $this->toSet($buckets['absent']);
        $leaveSet = $this->toSet($buckets['leave']);
        $halfDaySet = $this->toSet($buckets['half_day']);
        $isField = strcasecmp(trim($assignmentType), 'Field') === 0;

        $presentDays = 0.0;
        $absentDays = 0.0;
        $lateDays = 0.0;
        $leaveDays = 0.0;
        $paidDays = 0.0;
        $minutesLate = 0;
        $minutesUndertime = 0;
        $otHours = 0.0;

        foreach ($rows as $row) {
            $status = '';
            $late = 0;
            $under = 0;
            $ot = 0.0;

            if (is_array($row)) {
                $status = (string) ($row['status'] ?? '');
                $late = (int) ($row['minutes_late'] ?? 0);
                $under = (int) ($row['minutes_undertime'] ?? 0);
                $ot = (float) ($row['ot_hours'] ?? 0);
            } else {
                $status = (string) ($row->status ?? '');
                $late = (int) ($row->minutes_late ?? 0);
                $under = (int) ($row->minutes_undertime ?? 0);
                $ot = (float) ($row->ot_hours ?? 0);
            }

            $key = $this->key($status);
            if ($key !== '') {
                // Hard business rule: RNR is always unpaid and treated as absent.
                if ($key === 'rnr') {
                    $absentDays += 1.0;
                } else {
                    if (isset($lateSet[$key])) {
                        $lateDays += 1.0;
                    } elseif (isset($presentSet[$key])) {
                        $presentDays += isset($halfDaySet[$key]) ? 0.5 : 1.0;
                    }
                    if (isset($absentSet[$key])) {
                        $absentDays += 1.0;
                    }
                    if (isset($leaveSet[$key])) {
                        $leaveDays += 1.0;
                    }
                    // Field rule: RNR/Absent/Leave statuses are always unpaid.
                    $isUnpaidForField = $isField && (isset($absentSet[$key]) || isset($leaveSet[$key]));
                    if (!$isUnpaidForField) {
                        if (isset($halfDaySet[$key])) {
                            $paidDays += 0.5;
                        } elseif (isset($paidSet[$key])) {
                            $paidDays += 1.0;
                        }
                    }
                }
            }

            $minutesLate += $late;
            $minutesUndertime += $under;
            $otHours += $ot;
        }

        return [
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'paid_days' => $paidDays,
            'minutes_late' => $minutesLate,
            'minutes_undertime' => $minutesUndertime,
            'ot_hours' => $otHours,
        ];
    }

    private function statusesForCodeAndDescription(string $code, string $description): array
    {
        $statuses = [];
        $codeKey = strtoupper(trim($code));
        $description = trim($description);

        if ($codeKey !== '' && isset(self::CODE_STATUS_MAP[$codeKey])) {
            $statuses[] = self::CODE_STATUS_MAP[$codeKey];
        }
        if ($description !== '') {
            $statuses[] = $description;
        }

        return array_values(array_unique($statuses));
    }

    private function toSet(array $statuses): array
    {
        $set = [];
        foreach ($statuses as $status) {
            $key = $this->key((string) $status);
            if ($key !== '') {
                $set[$key] = true;
            }
        }
        return $set;
    }

    private function key(string $status): string
    {
        return strtolower(trim($status));
    }
}
