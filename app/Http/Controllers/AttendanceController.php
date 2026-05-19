<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCode;
use App\Models\AttendanceCodeSetting;
use App\Models\AttendanceAssignmentStatusRule;
use App\Models\Employee;
use App\Models\PayrollCalendarSetting;
use App\Models\TimekeepingRule;
use App\Services\Attendance\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceController extends Controller
{
    public function resolveArea(Request $request)
    {
        $v = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date'        => ['required', 'date_format:Y-m-d'],
        ]);

        $employee = Employee::find((int) $v['employee_id']);
        if (!$employee) {
            return response()->json(['area_place' => null]);
        }

        return response()->json(['area_place' => $employee->area_place]);
    }

    public function index(Request $request)
    {
        if ($request->boolean('latest')) {
            $v = Validator::make($request->query(), [
                'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
                'cutoff' => ['nullable', Rule::in(['A', 'B', '11-25', '26-10'])],
                'assignment' => ['nullable', 'string'],
                'area' => ['nullable', 'string'],
                'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
                'emp_no' => ['nullable', 'string'],
            ])->validate();

            $q = AttendanceRecord::query()
                ->whereNotNull('date');

            $month = $v['month'] ?? null;
            $cutoff = $v['cutoff'] ?? null;
            if ($month && $cutoff) {
                [$from, $to] = $this->cutoffRange($month, $cutoff);
                $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            }

            $assignment = $v['assignment'] ?? null;
            $area = $v['area'] ?? null;
            if ($assignment && $assignment !== 'All') {
                $q->whereHas('employee', fn ($qq) => $qq->where('assignment_type', $assignment));
            }
            if ($area && trim(strtolower($area)) !== 'all') {
                $q->where('area_place', $area);
            }

            if (!empty($v['employee_id'])) {
                $q->where('employee_id', (int) $v['employee_id']);
            } elseif (!empty($v['emp_no'])) {
                $empNo = trim((string) $v['emp_no']);
                if ($empNo !== '') {
                    $q->whereHas('employee', fn ($qq) => $qq->where('emp_no', $empNo));
                }
            }

            $latestDate = $q->orderByDesc('date')->value('date');

            if (!$latestDate) {
                return response()->json(['date' => null]);
            }

            $dateString = $latestDate instanceof \DateTimeInterface
                ? $latestDate->format('Y-m-d')
                : Carbon::parse($latestDate)->format('Y-m-d');

            return response()->json(['date' => $dateString]);
        }

        $v = Validator::make($request->query(), [
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['nullable', Rule::in(['A', 'B', '11-25', '26-10'])],
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in(['date', 'empId', 'name', 'assignment', 'area'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'emp_no' => ['nullable', 'string'],
        ])->validate();

        $sort = $v['sort'] ?? 'name';
        $dir = strtolower((string) ($v['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $recordsTable = (new AttendanceRecord())->getTable();
        $employeesTable = (new Employee())->getTable();
        $employmentStatusesTable = (new \App\Models\EmploymentStatus())->getTable();

        $records = AttendanceRecord::query()
            ->from($recordsTable)
            ->select([
                "{$recordsTable}.id",
                "{$recordsTable}.employee_id",
                "{$recordsTable}.date",
                "{$recordsTable}.status",
                "{$recordsTable}.paid_leave_units",
                "{$recordsTable}.area_place",
                "{$recordsTable}.clock_in",
                "{$recordsTable}.clock_out",
                "{$recordsTable}.minutes_late",
                "{$recordsTable}.minutes_undertime",
            ])
            ->leftJoin($employeesTable, "{$employeesTable}.id", '=', "{$recordsTable}.employee_id")
            ->leftJoin("{$employmentStatusesTable} as es", "es.id", '=', "{$employeesTable}.employment_status_id")
            // Exclude inactive/resigned employees from attendance records and KPI counts.
            ->whereRaw('LOWER(TRIM(COALESCE(es.label, ""))) NOT IN (?, ?)', ['inactive', 'resigned'])
            ->whereRaw('LOWER(TRIM(COALESCE(' . $employeesTable . '.status, ""))) NOT IN (?, ?)', ['inactive', 'resigned'])
            // Exclude attendance that predates employee hire date.
            ->where(function ($q) use ($employeesTable, $recordsTable) {
                $q->whereNull("{$employeesTable}.date_hired")
                  ->orWhereColumn("{$employeesTable}.date_hired", '<=', "{$recordsTable}.date");
            })
            ->with(['employee:id,emp_no,first_name,middle_name,last_name,department,position,assignment_type,area_place'])
            ->when($sort === 'date', function ($q) use ($recordsTable, $dir) {
                $q->orderBy("{$recordsTable}.date", $dir)->orderBy("{$recordsTable}.id", $dir);
            })
            ->when($sort === 'empId', function ($q) use ($employeesTable, $dir, $recordsTable) {
                $q->orderBy("{$employeesTable}.emp_no", $dir)->orderBy("{$recordsTable}.date", 'desc')->orderBy("{$recordsTable}.id", 'desc');
            })
            ->when($sort === 'assignment', function ($q) use ($employeesTable, $dir, $recordsTable) {
                $q->orderBy("{$employeesTable}.assignment_type", $dir)
                  ->orderBy("{$employeesTable}.area_place", $dir)
                  ->orderBy("{$employeesTable}.last_name", $dir)
                  ->orderBy("{$employeesTable}.first_name", $dir)
                  ->orderBy("{$recordsTable}.date", 'desc')
                  ->orderBy("{$recordsTable}.id", 'desc');
            })
            ->when($sort === 'area', function ($q) use ($recordsTable, $dir, $employeesTable) {
                $q->orderBy("{$employeesTable}.area_place", $dir)
                  ->orderBy("{$employeesTable}.last_name", $dir)
                  ->orderBy("{$employeesTable}.first_name", $dir)
                  ->orderBy("{$recordsTable}.date", 'desc')
                  ->orderBy("{$recordsTable}.id", 'desc');
            })
            ->when($sort === 'name', function ($q) use ($employeesTable, $dir, $recordsTable) {
                $q->orderBy("{$employeesTable}.last_name", $dir)
                  ->orderBy("{$employeesTable}.first_name", $dir)
                  ->orderBy("{$employeesTable}.middle_name", $dir)
                  ->orderBy("{$employeesTable}.emp_no", $dir)
                  ->orderBy("{$recordsTable}.date", 'desc')
                  ->orderBy("{$recordsTable}.id", 'desc');
            });

        // Fallback: always have a deterministic order (if invalid sort slipped through)
        if (!in_array($sort, ['date', 'empId', 'assignment', 'area', 'name'], true)) {
            $records->orderBy("{$employeesTable}.last_name", 'asc')
                ->orderBy("{$employeesTable}.first_name", 'asc')
                ->orderBy("{$recordsTable}.date", 'desc')
                ->orderBy("{$recordsTable}.id", 'desc');
        }

        $month = $v['month'] ?? null;
        $cutoff = $v['cutoff'] ?? null;

        if ($month && $cutoff) {
            [$from, $to] = $this->cutoffRange($month, $cutoff);
            $records->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
        }

        $assignment = $v['assignment'] ?? 'All';
        $area = $v['area'] ?? null;
        if ($assignment !== 'All') {
            $records->whereHas('employee', fn ($q) => $q->where('assignment_type', $assignment));
        }
        if ($area && trim(strtolower((string) $area)) !== 'all') {
            $records->where("{$employeesTable}.area_place", $area);
        }

        if (!empty($v['employee_id'])) {
            $records->where('employee_id', (int) $v['employee_id']);
        } elseif (!empty($v['emp_no'])) {
            $empNo = trim((string) $v['emp_no']);
            if ($empNo !== '') {
                $records->whereHas('employee', fn ($q) => $q->where('emp_no', $empNo));
            }
        }

        $date = $v['date'] ?? null;
        $autoFilledMissing = 0;
        if ($date) {
            $autoFilledMissing = $this->ensureDailyRosterRecords($date, (string) $assignment, $area);
        }
        if ($date) {
            $records->whereDate("{$recordsTable}.date", $date);
        }

        $status = $v['status'] ?? null;
        if ($status && strtolower($status) !== 'all') {
            $normalizedStatus = $this->normalizeStatusForStorage((string) $status);
            $records->where("{$recordsTable}.status", $normalizedStatus);
        }

        $q = trim((string) ($v['q'] ?? ''));
        if ($q !== '') {
            $records->where(function ($query) use ($q, $recordsTable) {
                $query->where("{$recordsTable}.date", 'like', "%{$q}%")
                    ->orWhere("{$recordsTable}.status", 'like', "%{$q}%")
                    ->orWhere("{$recordsTable}.clock_in", 'like', "%{$q}%")
                    ->orWhere("{$recordsTable}.clock_out", 'like', "%{$q}%")
                    ->orWhereHas('employee', function ($qq) use ($q) {
                        $qq->where('emp_no', 'like', "%{$q}%")
                            ->orWhere('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('department', 'like', "%{$q}%")
                            ->orWhere('position', 'like', "%{$q}%")
                            ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$q}%"])
                            ->orWhereRaw("CONCAT(last_name,', ',first_name) LIKE ?", ["%{$q}%"]);
                    });
            });
        }

        $baseQuery = clone $records;
        $leaveSet = ['Leave', 'Unpaid Leave', 'RNR', 'Paid Leave', 'LOA', 'Holiday', 'Day Off'];
        $stats = [
            'total' => (int) (clone $baseQuery)->count(),
            'present' => (int) (clone $baseQuery)->whereIn("{$recordsTable}.status", ['Present', 'Half-day'])->count(),
            'late' => (int) (clone $baseQuery)->where("{$recordsTable}.status", 'Late')->count(),
            'absent' => (int) (clone $baseQuery)->where("{$recordsTable}.status", 'Absent')->count(),
            'leave' => (int) (clone $baseQuery)->whereIn("{$recordsTable}.status", $leaveSet)->count(),
        ];

        $perPage = (int) ($v['per_page'] ?? 20);
        $paginator = $records->paginate($perPage)->withQueryString();

         $payload = collect($paginator->items())->map(function (AttendanceRecord $r) {
             $emp = $r->employee;
             $name = $emp
                 ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                 : '';
            $employeeArea = is_string($emp?->area_place) ? trim((string) $emp?->area_place) : '';
            $areaPlace = $employeeArea !== '' ? $employeeArea : null;
             return [
                 'id' => (string) $r->id,
                 'employee_id' => $r->employee_id,
                 'emp_no' => $emp?->emp_no,
                 'emp_name' => $name ?: ($emp?->emp_no ?? ''),
                 'first_name' => $emp?->first_name,
                 'middle_name' => $emp?->middle_name,
                 'last_name' => $emp?->last_name,
                 'department' => $emp?->department,
                 'position' => $emp?->position,
                 'assignment_type' => $emp?->assignment_type,
                 'area_place' => $areaPlace,
                 'date' => $r->date?->format('Y-m-d'),
                 'status' => $r->status,
                 'paid_leave_units' => (float) ($r->paid_leave_units ?? 0),
                 'clock_in' => $r->clock_in,
                 'clock_out' => $r->clock_out,
                 'minutes_late' => $r->minutes_late ?? 0,
                 'minutes_undertime' => $r->minutes_undertime ?? 0,
             ];
         })->values();

        return response()->json([
            'data' => $payload,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'stats' => $stats,
                'auto_filled_missing' => $autoFilledMissing,
            ],
        ]);
    }

    private function ensureDailyRosterRecords(string $date, string $assignment, ?string $area): int
    {
        $workDate = Carbon::parse($date)->toDateString();
        $employees = $this->employeesForFilters($assignment, $area, $workDate);
        if ($employees->isEmpty()) {
            return 0;
        }

        $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existingIds = AttendanceRecord::query()
            ->whereDate('date', $workDate)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $existingMap = array_fill_keys($existingIds, true);

        $isSunday = Carbon::parse($workDate)->dayOfWeek === Carbon::SUNDAY;
        $now = now();
        $rows = [];
        foreach ($employees as $emp) {
            $empId = (int) $emp->id;
            if (isset($existingMap[$empId])) {
                continue;
            }
            $defaultStatus = $isSunday
                ? ($this->defaultSundayStatusForAssignment((string) ($emp->assignment_type ?? '')) ?: 'Absent')
                : 'Absent';
            $rows[] = [
                'employee_id' => $empId,
                'date' => $workDate,
                'status' => $defaultStatus,
                'area_place' => (string) ($emp->area_place ?? ''),
                'clock_in' => null,
                'clock_out' => null,
                'minutes_late' => 0,
                'minutes_undertime' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            AttendanceRecord::insert($rows);
        }
        return count($rows);
    }

    public function store(Request $request)
    {
        $validated = $this->normalizeComputedFields($this->validateRecord($request));
        $employee = Employee::find((int) $validated['employee_id']);
        if ($employee) {
            $restDayError = $this->validateRestDayStatusForEmployee($employee, (string) ($validated['status'] ?? ''));
            if ($restDayError) {
                abort(422, $restDayError);
            }
        }
        $paidLeaveUnits = (float) ($validated['paid_leave_units'] ?? 0);
        if ($paidLeaveUnits > 0) {
            $this->checkPLBalance($validated['employee_id'], $validated['date'], null, $paidLeaveUnits);
        }
        $record = AttendanceRecord::create($validated);
        (new AttendanceSummaryService())->refreshForEmployeeDate(
            (int) $validated['employee_id'],
            (string) $validated['date']
        );

        return response()->json(['id' => $record->id], 201);
    }

    public function update(Request $request, AttendanceRecord $record)
    {
        $prevEmployeeId = (int) $record->employee_id;
        $prevDate = $record->date?->format('Y-m-d') ?? (string) $record->date;
        $validated = $this->normalizeComputedFields($this->validateRecord($request));
        $employee = Employee::find((int) $validated['employee_id']);
        if ($employee) {
            $restDayError = $this->validateRestDayStatusForEmployee($employee, (string) ($validated['status'] ?? ''));
            if ($restDayError) {
                abort(422, $restDayError);
            }
        }
        $paidLeaveUnits = (float) ($validated['paid_leave_units'] ?? 0);
        if ($paidLeaveUnits > 0) {
            $this->checkPLBalance($validated['employee_id'], $validated['date'], $record->id, $paidLeaveUnits);
        }
        $record->update($validated);
        $svc = new AttendanceSummaryService();
        $svc->refreshForEmployeeDate($prevEmployeeId, $prevDate);
        $svc->refreshForEmployeeDate((int) $validated['employee_id'], (string) $validated['date']);

        return response()->json(['updated' => true]);
    }

    private function checkPLBalance(int $employeeId, string $date, ?int $excludeId = null, float $requestedUnits = 1.0): void
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            abort(422, 'Employee not found.');
        }

        $isRegular = strtolower(trim((string) ($employee->employment_type ?? ''))) === 'regular';
        $hasAssignment = !empty($employee->assignment_type);
        $ruleErr = $this->validateRestDayStatusForEmployee($employee, 'Paid Leave');
        if ($ruleErr) {
            abort(422, $ruleErr);
        }
        if (!$isRegular || !$hasAssignment) {
            abort(422, 'Paid Leave is only allowed for eligible regular employees with PL allowance.');
        }

        $year = Carbon::parse($date)->year;
        $usedRow = AttendanceRecord::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->selectRaw("COALESCE(SUM(CASE WHEN paid_leave_units IS NULL OR paid_leave_units = 0 THEN (CASE WHEN status = 'Paid Leave' THEN 1 ELSE 0 END) ELSE paid_leave_units END), 0) as used_units")
            ->first();
        $used = (float) ($usedRow?->used_units ?? 0);
        $cap = $this->paidLeaveCapDays();
        $remaining = max(0.0, $cap - $used);
        if ($remaining + 1e-9 < $requestedUnits) {
            $remainingFmt = rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.');
            abort(422, "Paid Leave balance exhausted ({$remainingFmt} of {$cap} days remaining for {$year}).");
        }
    }

    public function destroy(AttendanceRecord $record)
    {
        $employeeId = (int) $record->employee_id;
        $date = $record->date?->format('Y-m-d') ?? (string) $record->date;
        $record->delete();
        (new AttendanceSummaryService())->refreshForEmployeeDate($employeeId, $date);
        return response()->json(['deleted' => true]);
    }

    public function downloadTemplate(Request $request)
    {
        $v = Validator::make($request->query(), [
            'date' => ['required', 'date_format:Y-m-d'],
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ])->validate();

        $date = Carbon::parse($v['date'])->startOfDay();
        $assignment = $v['assignment'] ?? 'All';
        $area = $v['area'] ?? null;

        $employees = $this->employeesForFilters($assignment, $area, $date->toDateString());

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $statusListFormula = '"' . implode(',', $this->templateCodes()) . '"';
        $noTimeFormula = 'OR($J{row}="A",$J{row}="PL",$J{row}="OFF",$J{row}="HOL",$J{row}="LOA",$J{row}="RNR")';
        $timeRequiredFormula = 'OR($J{row}="P",$J{row}="L",$J{row}="HD")';

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($date->format('Y-m-d'));

        $headers = [
            'emp_no',
            'name',
            'dept',
            'assignment',
            'area',
            'clock_in',
            'clock_out',
            'minutes_late',
            'minutes_undertime',
            'status',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '7A1530'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F4DCE3'],
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'C98A9B'],
                ],
            ],
        ]);

        $isSunday = $date->isSunday();
        $row = 2;
        foreach ($employees as $e) {
            $assignType = $e->assignment_type ?? '';
            $assignLower = strtolower(trim((string) $assignType));
            $defaultStatus = '';
            if ($isSunday) {
                $defaultStatus = $this->defaultSundayStatusForAssignment($assignType) ?? '';
            }
            $name = trim($e->last_name . ', ' . $e->first_name . ($e->middle_name ? ' ' . $e->middle_name : ''));
            $sheet->fromArray([
                $e->emp_no,
                $name,
                $e->department ?? '',
                $e->assignment_type ?? '',
                $e->area_place ?? '',
                '',
                '',
                '',
                '',
                $defaultStatus,
            ], null, "A{$row}");

            $dv = new DataValidation();
            $dv->setType(DataValidation::TYPE_LIST);
            $dv->setErrorStyle(DataValidation::STYLE_STOP);
            $dv->setAllowBlank(false);
            $dv->setShowDropDown(true);
            $dv->setFormula1($statusListFormula);
            $dv->setErrorTitle('Invalid status');
            $dv->setError('Choose from the dropdown list.');

            $sheet->getCell("J{$row}")->setDataValidation($dv);

            $schedule = $this->timeScheduleForAssignment((string) ($e->assignment_type ?? ''), (string) ($e->area_place ?? ''));
            $start = $schedule['start'] ?? '07:30';
            $end = $schedule['end'] ?? '17:00';
            [$startH, $startM] = $this->splitTimeParts($start);
            [$endH, $endM] = $this->splitTimeParts($end);
            // No grace period: late starts immediately after assignment start time.
            $sheet->setCellValue("J{$row}", sprintf('=IF($F%d="","",IF($F%d<=TIME(%d,%d,0),"P","L"))', $row, $row, $startH, $startM));

            // Minutes late: compute after assignment start, otherwise 0.
            $lateFormula = sprintf(
                '=IF($F%d="","",MAX(0,ROUND(($F%d-TIME(%d,%d,0))*1440,0)))',
                $row,
                $row,
                $startH,
                $startM
            );
            $sheet->setCellValue("H{$row}", $lateFormula);

            // Minutes undertime: compute when clock_out is before assignment end time.
            $underFormula = sprintf(
                '=IF($G%d="","",MAX(0,ROUND((TIME(%d,%d,0)-$G%d)*1440,0)))',
                $row,
                $endH,
                $endM,
                $row
            );
            $sheet->setCellValue("I{$row}", $underFormula);

            // Validation: block time/undertime inputs for no-time statuses
            $rowFormula = str_replace('{row}', (string) $row, $noTimeFormula);

            foreach (['F', 'G'] as $col) {
                $dvTime = new DataValidation();
                $dvTime->setType(DataValidation::TYPE_CUSTOM);
                $dvTime->setErrorStyle(DataValidation::STYLE_STOP);
                $dvTime->setAllowBlank(true);
                $dvTime->setErrorTitle('Invalid input');
                $dvTime->setError('Clock in/out must be blank for this status.');
                $dvTime->setFormula1(sprintf('=IF(%s,%s%d="",TRUE)', $rowFormula, $col, $row));
                $sheet->getCell("{$col}{$row}")->setDataValidation($dvTime);
            }

            // Require time for Present / Late / Half-day
            $reqFormula = str_replace('{row}', (string) $row, $timeRequiredFormula);
            foreach (['F', 'G'] as $col) {
                $dvReq = new DataValidation();
                $dvReq->setType(DataValidation::TYPE_CUSTOM);
                $dvReq->setErrorStyle(DataValidation::STYLE_STOP);
                $dvReq->setAllowBlank(true);
                $dvReq->setErrorTitle('Missing time');
                $dvReq->setError('Clock in/out is required for this status.');
                $dvReq->setFormula1(sprintf('=IF(%s,%s%d<>"",TRUE)', $reqFormula, $col, $row));
                $sheet->getCell("{$col}{$row}")->setDataValidation($dvReq);
            }

            foreach (['H', 'I'] as $col) {
                $dvNum = new DataValidation();
                $dvNum->setType(DataValidation::TYPE_CUSTOM);
                $dvNum->setErrorStyle(DataValidation::STYLE_STOP);
                $dvNum->setAllowBlank(true);
                $dvNum->setErrorTitle('Invalid input');
                $dvNum->setError('Minutes must be 0 or blank for this status.');
                $dvNum->setFormula1(sprintf('=IF(%s,OR(%s%d="",%s%d=0),TRUE)', $rowFormula, $col, $row, $col, $row));
                $sheet->getCell("{$col}{$row}")->setDataValidation($dvNum);
            }
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Show AM/PM for time columns
        $sheet->getStyle("F2:G{$row}")->getNumberFormat()->setFormatCode('h:mm AM/PM');
        $label = $area ? "{$assignment} - {$area}" : $assignment;
        $filename = "{$label} ATTENDANCE {$date->format('Y-m-d')}.xlsx";

        $tmpPath = storage_path('app/tmp_attendance_template.xlsx');
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $v = Validator::make($request->all(), [
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            // Optional override: allow UI-selected date for single-sheet imports.
            'date' => ['nullable', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'mimes:xlsx'],
        ])->validate();

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $overrideDate = $v['date'] ?? null;
        $sheetCount = method_exists($spreadsheet, 'getSheetCount') ? (int) $spreadsheet->getSheetCount() : 0;

        $errors = [];
        $rowsToUpsert = [];
        $expectedHeader = [
            'emp_no',
            'name',
            'dept',
            'assignment',
            'area',
            'clock_in',
            'clock_out',
            'minutes_late',
            'minutes_undertime',
            'status',
        ];

        // Daily import: read all sheets; each sheet name must be a valid YYYY-MM-DD date
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetName = $sheet->getTitle();
            if ($overrideDate && $sheetCount === 1) {
                $workDate = Carbon::parse($overrideDate)->startOfDay();
            } else {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sheetName)) {
                    $errors[] = "{$sheetName}: Sheet name must be YYYY-MM-DD.";
                    continue;
                }
                $workDate = Carbon::parse($sheetName)->startOfDay();
            }

            $header = [];
            foreach (range('A', 'J') as $col) {
                $header[] = trim((string) $sheet->getCell("{$col}1")->getValue());
            }
            if ($header !== $expectedHeader) {
                $errors[] = "{$sheetName}: Header mismatch. Please use the system template.";
                continue;
            }

            $highestRow = $sheet->getHighestDataRow();
            for ($r = 2; $r <= $highestRow; $r++) {
                $empNo = trim((string) $sheet->getCell("A{$r}")->getValue());
                $statusCell = $sheet->getCell("J{$r}");
                $rawStatus = $statusCell->isFormula()
                    ? (string) $statusCell->getCalculatedValue()
                    : (string) $statusCell->getValue();
                $rawStatus = trim($rawStatus);

                $clockIn = $this->readTimeCell($sheet->getCell("F{$r}"), 'clock_in');
                $clockOut = $this->readTimeCell($sheet->getCell("G{$r}"), 'clock_out');

                $lateCell = $sheet->getCell("H{$r}");
                $underCell = $sheet->getCell("I{$r}");

                // Skip unfilled rows: status blank and no clock data entered.
                // This handles pre-populated template rows (emp_no set, but row not filled in).
                if (
                    $rawStatus === '' &&
                    $clockIn === '' &&
                    $clockOut === ''
                ) {
                    continue;
                }

                // Also skip completely blank rows (no emp_no, no data at all).
                if ($empNo === '' && $rawStatus === '') {
                    continue;
                }

                if ($empNo === '') {
                    $errors[] = "{$sheetName} row {$r}: emp_no is required.";
                }

                $status = $this->mapStatus($rawStatus);
                if (!$status) {
                    $errors[] = "{$sheetName} row {$r}: invalid status or code.";
                }
                if ($this->shouldAutoMarkHalfDay($status, $clockIn, $clockOut)) {
                    $status = 'Half-day';
                }

                $late = $this->parseNumberCell($lateCell, $errors, "{$sheetName} row {$r}: minutes_late must be a number.");
                $under = $this->parseNumberCell($underCell, $errors, "{$sheetName} row {$r}: minutes_undertime must be a number.");

                $sheetAssignment = trim((string) $sheet->getCell("D{$r}")->getValue());
                $sheetArea = trim((string) $sheet->getCell("E{$r}")->getValue());
                [$status, $paidLeaveUnits] = $this->applyPaidLeaveWorkedTimeRule(
                    (string) ($status ?? ''),
                    $clockIn,
                    $clockOut,
                    $sheetAssignment,
                    $sheetArea
                );
                $underFromClockOut = $this->computeUndertimeMinutesFromClockOut($clockOut, $sheetAssignment, $sheetArea);

                // Normalize undertime from clock_out when available so values are always consistent.
                if ($underFromClockOut !== null) {
                    $under = $underFromClockOut;
                } elseif ($under === null) {
                    $under = 0;
                }

                if ($status) {
                    if (in_array($status, $this->noTimeStatuses(), true)) {
                        if ($clockIn !== '' || $clockOut !== '') {
                            $errors[] = "{$sheetName} row {$r}: clock_in/out must be empty for {$status}.";
                        }
                        if (($late ?? 0) > 0 || ($under ?? 0) > 0) {
                            $errors[] = "{$sheetName} row {$r}: minutes must be 0 for {$status}.";
                        }
                    }
                    // Clock times are optional for Present, Late, Half-day, etc.
                }

                $areaVal = trim((string) $sheet->getCell("E{$r}")->getValue());

                $rowsToUpsert[] = [
                    'date' => $workDate->toDateString(),
                    'emp_no' => $empNo,
                    '_src' => "{$sheetName} row {$r}",
                    'status' => $this->normalizeStatusForStorage($status ?: ''),
                    'paid_leave_units' => (float) ($paidLeaveUnits ?? 0),
                    'area_place' => $areaVal !== '' ? $areaVal : null,
                    'clock_in' => $this->sanitizeTimeForStorage($clockIn),
                    'clock_out' => $this->sanitizeTimeForStorage($clockOut),
                    'minutes_late' => (int) ($late ?? 0),
                    'minutes_undertime' => (int) ($under ?? 0),
                    '_sheet_assignment' => $sheetAssignment,
                    '_sheet_area' => $sheetArea,
                ];
            }
        }

        // Preload employees once to avoid N+1 checks and lookups during validation + upsert.
        $empNos = array_values(array_unique(array_filter(array_map(
            fn ($r) => $r['emp_no'] ?? '',
            $rowsToUpsert
        ))));
        $employeesByNo = Employee::query()
            ->whereIn('emp_no', $empNos)
            ->get(['id', 'emp_no', 'assignment_type'])
            ->keyBy('emp_no');

        $existingStatusByEmployeeDate = [];
        $existingPaidLeaveUnitsByEmployeeDate = [];
        if (!empty($rowsToUpsert) && $employeesByNo->isNotEmpty()) {
            $employeeIds = $employeesByNo->pluck('id')->map(fn ($id) => (int) $id)->all();
            $dates = array_values(array_unique(array_map(
                fn ($r) => (string) ($r['date'] ?? ''),
                $rowsToUpsert
            )));
            $dates = array_values(array_filter($dates, fn ($d) => $d !== ''));

            if (!empty($employeeIds) && !empty($dates)) {
                $existingRecords = AttendanceRecord::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->whereIn('date', $dates)
                    ->get(['employee_id', 'date', 'status', 'paid_leave_units']);

                foreach ($existingRecords as $existing) {
                    $dateStr = $existing->date instanceof \DateTimeInterface
                        ? $existing->date->format('Y-m-d')
                        : Carbon::parse((string) $existing->date)->format('Y-m-d');
                    $existingStatusByEmployeeDate[((int) $existing->employee_id) . '|' . $dateStr] = (string) ($existing->status ?? '');
                    $existingPaidLeaveUnitsByEmployeeDate[((int) $existing->employee_id) . '|' . $dateStr] = $this->effectivePaidLeaveUnits(
                        (string) ($existing->status ?? ''),
                        $existing->paid_leave_units
                    );
                }
            }
        }

        $plUsageByEmployeeYear = [];
        if ($employeesByNo->isNotEmpty()) {
            $employeeIds = $employeesByNo->pluck('id')->map(fn ($id) => (int) $id)->all();
            $plUsedRows = AttendanceRecord::query()
                ->whereIn('employee_id', $employeeIds)
                ->selectRaw("employee_id, YEAR(date) as year_key, COALESCE(SUM(CASE WHEN paid_leave_units IS NULL OR paid_leave_units = 0 THEN (CASE WHEN status = 'Paid Leave' THEN 1 ELSE 0 END) ELSE paid_leave_units END), 0) as used_count")
                ->groupBy('employee_id', 'year_key')
                ->get();

            foreach ($plUsedRows as $plUsed) {
                $plUsageByEmployeeYear[((int) $plUsed->employee_id) . '|' . ((int) $plUsed->year_key)] = (int) ($plUsed->used_count ?? 0);
            }
        }

        foreach ($rowsToUpsert as &$row) {
            $empNo = (string) ($row['emp_no'] ?? '');
            if ($empNo !== '' && !isset($employeesByNo[$empNo])) {
                $src = (string) ($row['_src'] ?? 'Row');
                $errors[] = "{$src}: emp_no \"{$empNo}\" not found.";
                continue;
            }
            if ($empNo !== '') {
                $emp = $employeesByNo[$empNo] ?? null;
                if ($emp) {
                    $assignmentType = (string) ($emp->assignment_type ?? ($row['_sheet_assignment'] ?? ''));
                    $areaPlace = (string) ($row['area_place'] ?? ($row['_sheet_area'] ?? ''));
                    if (in_array((string) ($row['status'] ?? ''), $this->timeTrackedStatuses(), true)) {
                        $computedLate = $this->computeLateMinutes((string) ($row['clock_in'] ?? ''), $assignmentType, $areaPlace);
                        if ($computedLate !== null) {
                            $row['minutes_late'] = (int) $computedLate;
                        }
                    }
                    $computedUnder = $this->computeUndertimeMinutesFromClockOut((string) ($row['clock_out'] ?? ''), $assignmentType, $areaPlace);
                    if ($computedUnder !== null) {
                        $row['minutes_undertime'] = (int) $computedUnder;
                    }
                    [$status, $paidLeaveUnits] = $this->applyPaidLeaveWorkedTimeRule(
                        (string) ($row['status'] ?? ''),
                        (string) ($row['clock_in'] ?? ''),
                        (string) ($row['clock_out'] ?? ''),
                        $assignmentType,
                        $areaPlace
                    );
                    $row['status'] = $this->normalizeStatusForStorage($status);
                    $row['paid_leave_units'] = (float) ($paidLeaveUnits ?? ($row['paid_leave_units'] ?? 0));
                    $ruleErr = $this->validateRestDayStatusForEmployee($emp, $status);
                    if ($ruleErr) {
                        $src = (string) ($row['_src'] ?? 'Row');
                        $errors[] = "{$src}: {$ruleErr}";
                    }
                    $requestedPaidLeaveUnits = $this->effectivePaidLeaveUnits($status, $row['paid_leave_units'] ?? null);
                    $row['paid_leave_units'] = $requestedPaidLeaveUnits;
                    if ($requestedPaidLeaveUnits > 0) {
                        $fullEmp = Employee::find((int) $emp->id);
                        $isRegular = strtolower(trim((string) ($fullEmp?->employment_type ?? ''))) === 'regular';
                        $hasAssignment = !empty($fullEmp?->assignment_type);
                        $plRuleErr = $fullEmp ? $this->validateRestDayStatusForEmployee($fullEmp, 'Paid Leave') : null;
                        if ($plRuleErr) {
                            $src = (string) ($row['_src'] ?? 'Row');
                            $errors[] = "{$src}: {$plRuleErr}";
                            continue;
                        }
                        if (!$isRegular || !$hasAssignment) {
                            $src = (string) ($row['_src'] ?? 'Row');
                            $errors[] = "{$src}: Paid Leave is only allowed for eligible regular employees with PL allowance.";
                            continue;
                        }

                        $workDate = (string) ($row['date'] ?? '');
                        $workYear = Carbon::parse($workDate)->year;
                        $usageKey = ((int) $emp->id) . '|' . $workYear;
                        $employeeDateKey = ((int) $emp->id) . '|' . $workDate;
                        $existingUnits = (float) ($existingPaidLeaveUnitsByEmployeeDate[$employeeDateKey] ?? 0.0);
                        $used = (float) ($plUsageByEmployeeYear[$usageKey] ?? 0.0);
                        $delta = max(0.0, $requestedPaidLeaveUnits - $existingUnits);

                        $cap = $this->paidLeaveCapDays();
                        if ($used + $delta > $cap + 1e-9) {
                            $src = (string) ($row['_src'] ?? 'Row');
                            $remaining = max(0.0, $cap - $used);
                            $remainingFmt = rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.');
                            $errors[] = "{$src}: Paid Leave balance exhausted ({$remainingFmt} of {$cap} days remaining for {$workYear}).";
                            continue;
                        }

                        $plUsageByEmployeeYear[$usageKey] = $used + $delta;
                    }
                }
            }
        }
        unset($row);

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Import failed. Fix the errors and re-upload.',
                'errors' => $errors,
            ], 422);
        }

        $summaryTouches = [];
        DB::transaction(function () use ($rowsToUpsert, $employeesByNo, &$summaryTouches) {
            foreach ($rowsToUpsert as $row) {
                $empNo = (string) ($row['emp_no'] ?? '');
                $emp = $empNo !== '' ? ($employeesByNo[$empNo] ?? null) : null;
                if (!$emp) {
                    continue;
                }

                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $emp->id, 'date' => $row['date']],
                    [
                        'status' => $row['status'],
                        'paid_leave_units' => (float) ($row['paid_leave_units'] ?? 0),
                        'area_place' => $row['area_place'],
                        'clock_in' => $row['clock_in'],
                        'clock_out' => $row['clock_out'],
                        'minutes_late' => $row['minutes_late'],
                        'minutes_undertime' => $row['minutes_undertime'],
                        'ot_hours' => 0,
                    ]
                );

                $summaryTouches[] = [
                    'employee_id' => (int) $emp->id,
                    'date' => (string) $row['date'],
                ];
            }
        });
        if ($summaryTouches) {
            $svc = new AttendanceSummaryService();
            $seen = [];
            foreach ($summaryTouches as $touch) {
                $key = $touch['employee_id'] . '|' . $touch['date'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $svc->refreshForEmployeeDate($touch['employee_id'], $touch['date']);
            }
        }

        $dates = array_values(array_unique(array_map(
            fn ($r) => (string) ($r['date'] ?? ''),
            $rowsToUpsert
        )));
        $dates = array_values(array_filter($dates, fn ($d) => $d !== ''));
        rsort($dates);
        $maxDate = $dates[0] ?? null;

        return response()->json([
            'message' => 'Imported successfully.',
            'max_date' => $maxDate,
            'dates' => $dates,
        ]);
    }

    private function validateRecord(Request $request): array
    {
        $payload = $request->all();
        if (array_key_exists('status', $payload)) {
            $payload['status'] = $this->normalizeStatusAlias((string) $payload['status']);
        }
        return Validator::make($payload, [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in($this->statusValuesWithAliases())],
            'paid_leave_units' => ['nullable', 'regex:/^(0|0\\.5|1(?:\\.0+)?)$/'],
            'area_place' => ['nullable', 'string', 'max:255'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'minutes_late' => ['nullable', 'integer', 'min:0'],
            'minutes_undertime' => ['nullable', 'integer', 'min:0'],
        ])->validate();
    }

    private function normalizeComputedFields(array $record): array
    {
        $status = $this->normalizeStatusForStorage((string) ($record['status'] ?? ''));
        $clockIn = (string) ($this->sanitizeTimeForStorage((string) ($record['clock_in'] ?? '')) ?? '');
        $clockOut = (string) ($this->sanitizeTimeForStorage((string) ($record['clock_out'] ?? '')) ?? '');
        $employee = !empty($record['employee_id']) ? Employee::find((int) $record['employee_id']) : null;
        $assignmentType = (string) ($employee?->assignment_type ?? '');
        $areaPlace = (string) ($record['area_place'] ?? $employee?->area_place ?? '');
        [$status, $paidLeaveUnits] = $this->applyPaidLeaveWorkedTimeRule($status, $clockIn, $clockOut, $assignmentType, $areaPlace);
        $record['status'] = $status;
        $record['paid_leave_units'] = $this->effectivePaidLeaveUnits($status, $paidLeaveUnits ?? ($record['paid_leave_units'] ?? null));
        $record['clock_in'] = $clockIn !== '' ? $clockIn : null;
        $record['clock_out'] = $clockOut !== '' ? $clockOut : null;

        if (in_array($status, $this->noTimeStatuses(), true)) {
            if ($status !== 'Paid Leave') {
                $record['paid_leave_units'] = 0.0;
            }
            $record['clock_in'] = null;
            $record['clock_out'] = null;
            $record['minutes_late'] = 0;
            $record['minutes_undertime'] = 0;
            $record['ot_hours'] = 0;
            return $record;
        }

        $record['minutes_late'] = (int) ($this->computeLateMinutes($clockIn, $assignmentType, $areaPlace) ?? 0);
        $record['minutes_undertime'] = (int) ($this->computeUndertimeMinutesFromClockOut($clockOut, $assignmentType, $areaPlace) ?? 0);
        $record['ot_hours'] = 0;

        return $record;
    }

    private function effectivePaidLeaveUnits(string $status, $rawUnits): float
    {
        $normalizedStatus = $this->normalizeStatusForStorage($status);
        $hasProvidedUnits = $rawUnits !== null && trim((string) $rawUnits) !== '';
        $units = $hasProvidedUnits ? (float) $rawUnits : null;

        if ($normalizedStatus === 'Paid Leave') {
            return $units !== null ? max(0.0, min(1.0, $units)) : 1.0;
        }

        if ($normalizedStatus === 'Half-day') {
            if ($units === null) {
                return 0.0;
            }
            // Half-day can only consume half-day paid leave.
            return $units >= 0.5 ? 0.5 : 0.0;
        }

        return 0.0;
    }

    private function applyPaidLeaveWorkedTimeRule(string $status, string $clockIn, string $clockOut, string $assignmentType = '', string $areaPlace = ''): array
    {
        $normalizedStatus = $this->normalizeStatusForStorage($status);
        if ($normalizedStatus !== 'Paid Leave') {
            return [$normalizedStatus, null];
        }

        $workedMinutes = $this->workedMinutesFromTimeRange($clockIn, $clockOut);
        if ($workedMinutes === null) {
            return ['Paid Leave', 1.0];
        }

        // Paid leave with worked time becomes either half-day leave or a worked day.
        if ($workedMinutes < 360) {
            return ['Half-day', 0.5];
        }

        $late = (int) ($this->computeLateMinutes($clockIn, $assignmentType, $areaPlace) ?? 0);
        return [$late > 0 ? 'Late' : 'Present', 0.0];
    }

    private function workedMinutesFromTimeRange(string $clockIn, string $clockOut): ?int
    {
        $in = $this->parseTimeToMinutes($clockIn);
        $out = $this->parseTimeToMinutes($clockOut);
        if ($in === null || $out === null) {
            return null;
        }
        if ($out <= $in) {
            return null;
        }
        return max(0, $out - $in);
    }

    private function attendanceCodes()
    {
        return AttendanceCode::query()->orderBy('code')->get(['code', 'description', 'counts_as_present', 'counts_as_paid']);
    }

    private function attendanceCodeSettings(): ?AttendanceCodeSetting
    {
        return AttendanceCodeSetting::query()->first();
    }

    private function statusValues(): array
    {
        $values = $this->attendanceCodes()
            ->pluck('description')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
        return count($values) ? $values : ['Present'];
    }

    private function statusValuesWithAliases(): array
    {
        $values = $this->statusValues();
        $aliases = [];
        foreach ($values as $status) {
            $n = $this->normalizeStatusAlias((string) $status);
            if ($n !== '' && !in_array($n, $values, true)) {
                $aliases[] = $n;
            }
        }
        return array_values(array_unique(array_merge($values, $aliases)));
    }

    private function normalizeStatusAlias(string $status): string
    {
        $s = trim($status);
        if (preg_match('/^day[\s-]*off$/i', $s)) {
            return 'Day-off';
        }
        return $s;
    }

    private function statusCodeMap(): array
    {
        $map = [];
        foreach ($this->attendanceCodes() as $row) {
            $code = strtoupper(trim((string) $row->code));
            $desc = trim((string) $row->description);
            if ($code === '' || $desc === '') {
                continue;
            }
            $map[$code] = $desc;
        }
        return $map;
    }

    private function templateCodes(): array
    {
        $settings = $this->attendanceCodeSettings();
        $codes = collect($settings?->template_codes ?? [])
            ->map(fn ($v) => strtoupper(trim((string) $v)))
            ->filter()
            ->values();
        if ($codes->isNotEmpty()) {
            return $codes->all();
        }
        return array_values(array_keys($this->statusCodeMap()));
    }

    private function noTimeStatuses(): array
    {
        $settings = $this->attendanceCodeSettings();
        $statuses = collect($settings?->no_time_statuses ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();
        if ($statuses->isNotEmpty()) {
            return $statuses->all();
        }

        return $this->attendanceCodes()
            ->filter(fn ($c) => !$c->counts_as_present && !$c->counts_as_paid)
            ->pluck('description')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();
    }

    private function timeTrackedStatuses(): array
    {
        $settings = $this->attendanceCodeSettings();
        $statuses = collect($settings?->time_tracked_statuses ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();
        if ($statuses->isNotEmpty()) {
            return $statuses->all();
        }
        $noTime = $this->noTimeStatuses();
        return collect($this->statusValues())
            ->reject(fn ($s) => in_array($s, $noTime, true))
            ->values()
            ->all();
    }

    private function paidLeaveCapDays(): int
    {
        $settings = $this->attendanceCodeSettings();
        $cap = (int) ($settings?->paid_leave_cap_days ?? 5);
        return $cap > 0 ? $cap : 5;
    }

    private function validateRestDayStatusForEmployee(Employee $employee, string $status): ?string
    {
        $s = trim($status);
        if ($s === '') {
            return null;
        }

        $assignment = strtolower(trim((string) ($employee->assignment_type ?? '')));
        $rulesByAssignment = $this->assignmentStatusRules();
        $rules = $rulesByAssignment[$assignment] ?? [];
        $statusKey = strtolower($s);
        if (!isset($rules[$statusKey])) {
            return null;
        }

        $rule = $rules[$statusKey];
        if (!($rule['is_allowed'] ?? true)) {
            return (string) ($rule['message'] ?? 'Selected status is not allowed for this assignment.');
        }

        return null;
    }

    private function defaultSundayStatusForAssignment(string $assignmentType): ?string
    {
        $assignment = strtolower(trim($assignmentType));
        if ($assignment === '') {
            return null;
        }
        $rulesByAssignment = $this->assignmentStatusRules();
        $rules = $rulesByAssignment[$assignment] ?? [];
        foreach ($rules as $rule) {
            $default = trim((string) ($rule['default_sunday_status'] ?? ''));
            if ($default !== '') {
                return $default;
            }
        }
        return null;
    }

    private function assignmentStatusRules(): array
    {
        $rows = AttendanceAssignmentStatusRule::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['assignment_type', 'status', 'is_allowed', 'message', 'default_sunday_status']);

        $grouped = [];
        foreach ($rows as $row) {
            $assignmentKey = strtolower(trim((string) $row->assignment_type));
            $statusKey = strtolower(trim((string) $row->status));
            if ($assignmentKey === '' || $statusKey === '') {
                continue;
            }
            $grouped[$assignmentKey][$statusKey] = [
                'is_allowed' => (bool) $row->is_allowed,
                'message' => (string) ($row->message ?? ''),
                'default_sunday_status' => (string) ($row->default_sunday_status ?? ''),
            ];
        }

        return $grouped;
    }

    private function cutoffRange(string $month, string $cutoff): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        [$y, $m] = array_map('intval', explode('-', $month));
        $base = Carbon::create($y, $m, 1)->startOfDay();

        [$fromDay, $toDay] = $this->cutoffDays($cutoff, $calendar);
        return $this->cutoffWindow($base, $fromDay, $toDay);
    }

    private function normalizeCutoffKey(string $cutoff): string
    {
        $c = strtoupper(trim($cutoff));
        if ($c === '26-10') {
            return 'A';
        }
        if ($c === '11-25') {
            return 'B';
        }
        return in_array($c, ['A', 'B'], true) ? $c : 'A';
    }

    private function cutoffDays(string $cutoff, ?PayrollCalendarSetting $calendar = null): array
    {
        $calendar = $calendar ?? (PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]));
        $key = $this->normalizeCutoffKey($cutoff);
        if ($key === 'A') {
            $from = (int) ($calendar->cutoff_b_from ?? 26);
            $to = (int) ($calendar->cutoff_b_to ?? 10);
            return [$from > 0 ? $from : 1, $to > 0 ? $to : 10];
        }
        $from = (int) ($calendar->cutoff_a_from ?? 11);
        $to = (int) ($calendar->cutoff_a_to ?? 25);
        return [$from > 0 ? $from : 1, $to > 0 ? $to : 25];
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

    private function employeesForFilters(string $assignment, ?string $area, ?string $forDate = null)
    {
        if (is_string($area) && trim(strtolower($area)) === 'all') {
            $area = null;
        }
        $q = Employee::query();

        // Exclude inactive/resigned employees based on either employment_status label or legacy status field
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
        if ($area) {
            $q->where('area_place', $area);
        }
        if ($forDate) {
            // Exclude employees hired after the selected attendance date.
            $q->where(function ($qq) use ($forDate) {
                $qq->whereNull('date_hired')
                   ->orWhereDate('date_hired', '<=', $forDate);
            });
        }

        return $q->orderBy('last_name')->orderBy('first_name')->get();
    }

    private function mapStatus(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $upper = strtoupper($raw);
        $map = $this->statusCodeMap();
        if (isset($map[$upper])) {
            return $map[$upper];
        }
        foreach ($this->statusValues() as $status) {
            if (strcasecmp($raw, $status) === 0) {
                return $this->normalizeStatusForStorage($status);
            }
        }
        return null;
    }

    private function normalizeStatusForStorage(string $status): string
    {
        $s = trim($status);
        if (strcasecmp($s, 'Day-off') === 0) {
            return 'Day Off';
        }
        return $s;
    }

    private function sanitizeTimeForStorage(?string $time): ?string
    {
        $t = trim((string) $time);
        if ($t === '') {
            return null;
        }
        if (!preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $t, 2));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }
        return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    private function parseNumberCell($cell, array &$errors, string $message): ?float
    {
        $raw = $cell->getValue();
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($cell->isFormula()) {
            $raw = $cell->getCalculatedValue();
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '', $value);
        if (!is_numeric($value)) {
            $errors[] = $message;
            return null;
        }

        return (float) $value;
    }

    private function readTimeCell($cell, ?string $context = null): string
    {
        $raw = $cell->getValue();
        $rawInput = trim((string) $raw);

        // Parse raw typed text first so "5:30" keeps user intent (no meridiem),
        // independent of Excel's display format (which may show AM by default).
        if (is_string($raw) && preg_match('/^(\d{1,2}):(\d{2})(?:\s*([AP]M))?$/i', $rawInput, $mRaw)) {
            $hh = (int) $mRaw[1];
            $mm = str_pad((string) ((int) $mRaw[2]), 2, '0', STR_PAD_LEFT);
            $meridiem = strtoupper((string) ($mRaw[3] ?? ''));
            if ($meridiem === '') {
                $hh = $this->normalizeAmbiguousHourWithoutMeridiem($hh, $context);
            } elseif ($meridiem === 'AM' && $hh === 12) {
                $hh = 0;
            } elseif ($meridiem === 'PM' && $hh < 12) {
                $hh += 12;
            }
            $hh = max(0, min(23, $hh));
            return str_pad((string) $hh, 2, '0', STR_PAD_LEFT) . ":{$mm}";
        }

        if (is_numeric($raw)) {
            try {
                $time = ExcelDate::excelToDateTimeObject($raw)->format('H:i');
                // Numeric Excel time has no AM/PM marker; apply context defaults.
                if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
                    $hh = $this->normalizeAmbiguousHourWithoutMeridiem((int) $m[1], $context);
                    $mm = str_pad((string) ((int) $m[2]), 2, '0', STR_PAD_LEFT);
                    return str_pad((string) max(0, min(23, $hh)), 2, '0', STR_PAD_LEFT) . ":{$mm}";
                }
                return $time;
            } catch (\Throwable $e) {
                return '';
            }
        }

        $hasExplicitMeridiemInRaw = preg_match('/\b(?:AM|PM)\b/i', $rawInput) === 1;
        $formatted = trim((string) $cell->getFormattedValue());
        if ($formatted === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?:\s*([AP]M))?$/i', $formatted, $m)) {
            $hh = (int) $m[1];
            $mm = str_pad((string) ((int) $m[2]), 2, '0', STR_PAD_LEFT);
            $meridiem = strtoupper((string) ($m[3] ?? ''));
            if ($meridiem === '') {
                $hh = $this->normalizeAmbiguousHourWithoutMeridiem($hh, $context);
            } elseif ($meridiem === 'AM' && $hh === 12) {
                $hh = 0;
            } elseif ($meridiem === 'PM' && $hh < 12) {
                $hh += 12;
            }

            // For clock_out, default to PM unless user explicitly typed AM in the source value.
            if (
                $context === 'clock_out'
                && !$hasExplicitMeridiemInRaw
                && $hh >= 1
                && $hh <= 11
            ) {
                $hh += 12;
            }
            $hh = max(0, min(23, $hh));
            return str_pad((string) $hh, 2, '0', STR_PAD_LEFT) . ":{$mm}";
        }
        return '';
    }

    private function normalizeAmbiguousHourWithoutMeridiem(int $hour, ?string $context): int
    {
        // Excel text like "5:00" has no AM/PM. Apply import-friendly defaults:
        // clock_out values are typically afternoon/evening; clock_in keeps morning default,
        // except noon start half-day patterns (12:00 / 1:00).
        if ($hour < 0 || $hour > 23) {
            return $hour;
        }

        if ($hour === 12) {
            return 12; // treat as noon for import shorthand
        }

        if ($context === 'clock_out' && $hour >= 1 && $hour <= 11) {
            return $hour + 12;
        }

        if ($context === 'clock_in' && $hour === 1) {
            return 13;
        }

        return $hour;
    }

    private function shouldAutoMarkHalfDay(?string $status, string $clockIn, string $clockOut): bool
    {
        if (!in_array((string) $status, $this->timeTrackedStatuses(), true)) {
            return false;
        }

        $clockInMinutes = $this->parseTimeToMinutes($clockIn);
        $clockOutMinutes = $this->parseTimeToMinutes($clockOut);

        if ($clockOutMinutes === (12 * 60)) {
            return true; // out at 12:00 PM
        }

        return in_array($clockInMinutes, [12 * 60, 13 * 60], true); // in at 12:00 PM or 1:00 PM
    }

    private function computeLateMinutes(string $clockIn, string $assignmentType = '', string $areaPlace = ''): ?int
    {
        $minutesIn = $this->parseTimeToMinutes($clockIn);
        if ($minutesIn === null) {
            return null;
        }
        $schedule = $this->timeScheduleForAssignment($assignmentType, $areaPlace);
        $start = $this->parseTimeToMinutes((string) ($schedule['start'] ?? '07:30'));
        if ($start === null) {
            $start = (7 * 60) + 30;
        }
        return max(0, $minutesIn - $start);
    }

    private function computeUndertimeMinutesFromClockOut(string $clockOut, string $assignmentType = '', string $areaPlace = ''): ?int
    {
        if ($clockOut === '') {
            return null;
        }
        $minutesOut = $this->parseTimeToMinutes($clockOut);
        if ($minutesOut === null) {
            return null;
        }
        $schedule = $this->timeScheduleForAssignment($assignmentType, $areaPlace);
        $endOfWork = $this->parseTimeToMinutes((string) ($schedule['end'] ?? '17:00'));
        if ($endOfWork === null) {
            $endOfWork = 17 * 60;
        }
        if ($minutesOut >= $endOfWork) {
            return 0;
        }
        return max(0, $endOfWork - $minutesOut);
    }

    private function timeScheduleForAssignment(string $assignmentType, string $areaPlace = ''): array
    {
        $rule = TimekeepingRule::query()->first();
        $defaults = [
            'davao' => ['start' => '07:45', 'end' => '17:00'],
            'tagum' => ['start' => '07:30', 'end' => '17:00'],
            'field' => ['start' => '06:30', 'end' => '17:30'],
            'mebatas' => ['start' => '07:00', 'end' => '19:00'],
        ];
        $saved = is_array($rule?->assignment_schedules) ? $rule->assignment_schedules : [];
        $schedules = array_replace_recursive($defaults, $saved);

        $key = $this->scheduleKeyForAssignment($assignmentType, $areaPlace);
        return is_array($schedules[$key] ?? null) ? $schedules[$key] : $defaults[$key];
    }

    private function scheduleKeyForAssignment(string $assignmentType, string $areaPlace = ''): string
    {
        $a = strtolower(trim($assignmentType));
        $p = strtolower(trim($areaPlace));
        // Field areas use Field schedule, except Mebatas which has its own schedule.
        if (str_contains($a, 'field')) {
            return str_contains($p, 'mebatas') ? 'mebatas' : 'field';
        }

        if (str_contains($a, 'davao')) {
            return 'davao';
        }
        if (str_contains($a, 'tagum')) {
            return 'tagum';
        }
        if (str_contains($a, 'mebatas') || str_contains($p, 'mebatas')) {
            return 'mebatas';
        }
        return 'tagum';
    }

    private function splitTimeParts(string $time): array
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return [7, 30];
        }
        return [(int) $m[1], (int) $m[2]];
    }

    private function parseTimeToMinutes(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return null;
        }
        $hh = (int) $m[1];
        $mm = (int) $m[2];
        if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59) {
            return null;
        }
        return ($hh * 60) + $mm;
    }
}
