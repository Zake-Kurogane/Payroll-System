<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCode;
use App\Models\AttendanceCodeSetting;
use App\Models\OvertimeRule;
use App\Models\PayGroup;
use App\Models\PayrollCalendarSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
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

    public function getPayGroups()
    {
        if (PayGroup::query()->count() === 0) {
            PayGroup::query()->insert([
                [
                    'name' => 'Tagum',
                    'pay_frequency' => 'Semi-monthly',
                    'calendar' => 'Payroll Calendar',
                ],
                [
                    'name' => 'Davao',
                    'pay_frequency' => 'Semi-monthly',
                    'calendar' => 'Payroll Calendar',
                ],
                [
                    'name' => 'Area',
                    'pay_frequency' => 'Semi-monthly',
                    'calendar' => 'Payroll Calendar',
                ],
            ]);
        }

        return response()->json(PayGroup::query()->orderBy('id')->get());
    }

    public function savePayGroups(Request $request)
    {
        $validated = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:100'],
            'groups.*.pay_frequency' => ['required', 'string', 'max:100'],
            'groups.*.calendar' => ['required', 'string', 'max:100'],
            'groups.*.default_shift_start' => ['nullable', 'date_format:H:i'],
            'groups.*.default_ot_mode' => ['nullable', Rule::in(['flat_rate', 'rate_based'])],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['groups'] as $g) {
                PayGroup::updateOrCreate(
                    ['name' => $g['name']],
                    [
                        'pay_frequency' => $g['pay_frequency'],
                        'calendar' => $g['calendar'],
                        'default_shift_start' => $g['default_shift_start'] ?: null,
                        'default_ot_mode' => $g['default_ot_mode'] ?: null,
                    ]
                );
            }
        });

        return response()->json(['saved' => true]);
    }

    public function getOvertimeRules()
    {
        $row = OvertimeRule::query()->first();
        if (!$row) {
            $row = OvertimeRule::create([]);
        }
        return response()->json($row);
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
            $row = OvertimeRule::create($validated);
        } else {
            $row->update($validated);
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
