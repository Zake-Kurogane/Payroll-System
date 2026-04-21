<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollCalendarSetting;
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
    private const STATUS_VALUES = [
        'Present',
        'Late',
        'Absent',
        'Leave',
        'RNR',
        'Paid Leave',
        'Half-day',
        'Day Off',
        'Holiday',
        'LOA',
    ];

    private const STATUS_CODES = [
        'P' => 'Present',
        'PR' => 'Present',
        'L' => 'Late',
        'LT' => 'Late',
        'A' => 'Absent',
        'AB' => 'Absent',
        'PL' => 'Paid Leave',
        'VL' => 'Paid Leave',
        'SL' => 'Paid Leave',
        'SPL' => 'Paid Leave',
        'UL' => 'Absent',
        'LWOP' => 'Absent',
        'RNR' => 'RNR',
        'HD' => 'Half-day',
        'OFF' => 'Day Off',
        'HOL' => 'Holiday',
        'LOA' => 'LOA',
    ];

    private const TEMPLATE_CODES = ['P', 'L', 'A', 'RNR', 'PL', 'HD', 'OFF', 'HOL', 'LOA'];
    private const NO_TIME_STATUSES = ['Absent', 'Leave', 'Paid Leave', 'Day Off', 'Holiday', 'LOA', 'RNR'];
    private const TIME_TRACKED_STATUSES = ['Present', 'Late', 'Half-day'];

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

        return response()->json(['area_place' => $employee->resolveAreaForDate($v['date'])]);
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

        $records = AttendanceRecord::query()
            ->from($recordsTable)
            ->select([
                "{$recordsTable}.id",
                "{$recordsTable}.employee_id",
                "{$recordsTable}.date",
                "{$recordsTable}.status",
                "{$recordsTable}.area_place",
                "{$recordsTable}.clock_in",
                "{$recordsTable}.clock_out",
                "{$recordsTable}.minutes_late",
                "{$recordsTable}.minutes_undertime",
            ])
            ->leftJoin($employeesTable, "{$employeesTable}.id", '=', "{$recordsTable}.employee_id")
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
                $q->orderBy("{$recordsTable}.area_place", $dir)
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
            $records->where("{$recordsTable}.area_place", $area);
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
        if ($date) {
            $records->whereDate("{$recordsTable}.date", $date);
        }

        $status = $v['status'] ?? null;
        if ($status && strtolower($status) !== 'all') {
            $records->where("{$recordsTable}.status", $status);
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
        $leaveSet = ['Leave', 'Paid Leave', 'LOA', 'Holiday', 'Day Off'];
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
            $recordArea = is_string($r->area_place) ? trim($r->area_place) : '';
            $employeeArea = is_string($emp?->area_place) ? trim((string) $emp?->area_place) : '';
            $areaPlace = $recordArea !== '' ? $recordArea : ($employeeArea !== '' ? $employeeArea : null);
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
            ],
        ]);
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
        if (($validated['status'] ?? '') === 'Paid Leave') {
            $this->checkPLBalance($validated['employee_id'], $validated['date']);
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
        if (($validated['status'] ?? '') === 'Paid Leave') {
            $this->checkPLBalance($validated['employee_id'], $validated['date'], $record->id);
        }
        $record->update($validated);
        $svc = new AttendanceSummaryService();
        $svc->refreshForEmployeeDate($prevEmployeeId, $prevDate);
        $svc->refreshForEmployeeDate((int) $validated['employee_id'], (string) $validated['date']);

        return response()->json(['updated' => true]);
    }

    private function checkPLBalance(int $employeeId, string $date, ?int $excludeId = null): void
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            abort(422, 'Employee not found.');
        }

        $isRegular = strtolower(trim((string) ($employee->employment_type ?? ''))) === 'regular';
        $isField = strtolower(trim((string) ($employee->assignment_type ?? ''))) === 'field';
        $hasAssignment = !empty($employee->assignment_type);
        if ($isField) {
            abort(422, 'Paid Leave is not applicable for Field employees.');
        }
        if (!$isRegular || !$hasAssignment) {
            abort(422, 'Paid Leave is only allowed for eligible regular employees with PL allowance.');
        }

        $year = Carbon::parse($date)->year;
        $used = AttendanceRecord::where('employee_id', $employeeId)
            ->where('status', 'Paid Leave')
            ->whereYear('date', $year)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->count();
        if ($used >= 5) {
            abort(422, "Paid Leave balance exhausted (0 of 5 days remaining for {$year}).");
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

        $employees = $this->employeesForFilters($assignment, $area);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $statusListFormula = '"' . implode(',', self::TEMPLATE_CODES) . '"';
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
                if ($assignLower === 'field') {
                    $defaultStatus = 'RNR';
                } elseif (in_array($assignLower, ['davao', 'tagum'], true)) {
                    $defaultStatus = 'OFF';
                }
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

            // Auto status based on grace time (07:35): Present if within grace, Late if beyond
            $sheet->setCellValue("J{$row}", sprintf('=IF($F%d="","",IF($F%d<=TIME(7,35,0),"P","L"))', $row, $row));

            // Minutes late: compute after 07:35, otherwise 0
            $lateFormula = sprintf(
                '=IF($F%d="","",MAX(0,ROUND(($F%d-TIME(7,35,0))*1440,0)))',
                $row,
                $row
            );
            $sheet->setCellValue("H{$row}", $lateFormula);

            // Minutes undertime: compute when clock_out is before 17:00
            $underFormula = sprintf(
                '=IF($G%d="","",MAX(0,ROUND((TIME(17,0,0)-$G%d)*1440,0)))',
                $row,
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

                $clockIn = $this->readTimeCell($sheet->getCell("F{$r}"));
                $clockOut = $this->readTimeCell($sheet->getCell("G{$r}"));

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

                $late = $this->parseNumberCell($lateCell, $errors, "{$sheetName} row {$r}: minutes_late must be a number.");
                $under = $this->parseNumberCell($underCell, $errors, "{$sheetName} row {$r}: minutes_undertime must be a number.");

                $underFromClockOut = $this->computeUndertimeMinutesFromClockOut($clockOut);

                // Normalize undertime from clock_out when available so values are always consistent.
                if ($underFromClockOut !== null) {
                    $under = $underFromClockOut;
                } elseif ($under === null) {
                    $under = 0;
                }

                if ($status) {
                    if (in_array($status, self::NO_TIME_STATUSES, true)) {
                        if ($clockIn !== '' || $clockOut !== '') {
                            $errors[] = "{$sheetName} row {$r}: clock_in/out must be empty for {$status}.";
                        }
                        if (($late ?? 0) > 0 || ($under ?? 0) > 0) {
                            $errors[] = "{$sheetName} row {$r}: minutes must be 0 for {$status}.";
                        }
                    }
                    // Clock times are optional for Present, Late, Half-day, etc.
                }

                if (in_array($status, self::TIME_TRACKED_STATUSES, true)) {
                    $computedLate = $this->computeLateMinutes($clockIn);
                    if ($computedLate !== null) {
                        $late = $computedLate;
                    }
                }

                $areaVal = trim((string) $sheet->getCell("E{$r}")->getValue());

                $rowsToUpsert[] = [
                    'date' => $workDate->toDateString(),
                    'emp_no' => $empNo,
                    '_src' => "{$sheetName} row {$r}",
                    'status' => $status ?: '',
                    'area_place' => $areaVal !== '' ? $areaVal : null,
                    'clock_in' => $clockIn ?: null,
                    'clock_out' => $clockOut ?: null,
                    'minutes_late' => (int) ($late ?? 0),
                    'minutes_undertime' => (int) ($under ?? 0),
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

        foreach ($rowsToUpsert as $row) {
            $empNo = (string) ($row['emp_no'] ?? '');
            if ($empNo !== '' && !isset($employeesByNo[$empNo])) {
                $src = (string) ($row['_src'] ?? 'Row');
                $errors[] = "{$src}: emp_no \"{$empNo}\" not found.";
                continue;
            }
            if ($empNo !== '') {
                $emp = $employeesByNo[$empNo] ?? null;
                if ($emp) {
                    $status = (string) ($row['status'] ?? '');
                    $ruleErr = $this->validateRestDayStatusForEmployee($emp, $status);
                    if ($ruleErr) {
                        $src = (string) ($row['_src'] ?? 'Row');
                        $errors[] = "{$src}: {$ruleErr}";
                    }
                    if ($status === 'Paid Leave') {
                        $fullEmp = Employee::find((int) $emp->id);
                        $isRegular = strtolower(trim((string) ($fullEmp?->employment_type ?? ''))) === 'regular';
                        $isField = strtolower(trim((string) ($fullEmp?->assignment_type ?? ''))) === 'field';
                        $hasAssignment = !empty($fullEmp?->assignment_type);
                        if ($isField) {
                            $src = (string) ($row['_src'] ?? 'Row');
                            $errors[] = "{$src}: Paid Leave is not applicable for Field employees.";
                            continue;
                        }
                        if (!$isRegular || !$hasAssignment) {
                            $src = (string) ($row['_src'] ?? 'Row');
                            $errors[] = "{$src}: Paid Leave is only allowed for eligible regular employees with PL allowance.";
                        }
                    }
                }
            }
        }

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
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(self::STATUS_VALUES)],
            'area_place' => ['nullable', 'string', 'max:255'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'minutes_late' => ['nullable', 'integer', 'min:0'],
            'minutes_undertime' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function normalizeComputedFields(array $record): array
    {
        $status = (string) ($record['status'] ?? '');
        $clockIn = (string) ($record['clock_in'] ?? '');
        $clockOut = (string) ($record['clock_out'] ?? '');

        if (in_array($status, self::NO_TIME_STATUSES, true)) {
            $record['clock_in'] = null;
            $record['clock_out'] = null;
            $record['minutes_late'] = 0;
            $record['minutes_undertime'] = 0;
            $record['ot_hours'] = 0;
            return $record;
        }

        $record['minutes_late'] = (int) ($this->computeLateMinutes($clockIn) ?? 0);
        $record['minutes_undertime'] = (int) ($this->computeUndertimeMinutesFromClockOut($clockOut) ?? 0);
        $record['ot_hours'] = 0;

        return $record;
    }

    private function validateRestDayStatusForEmployee(Employee $employee, string $status): ?string
    {
        $s = trim($status);
        if ($s === '') {
            return null;
        }

        $assignment = strtolower(trim((string) ($employee->assignment_type ?? '')));
        $isField = $assignment === 'field';
        $isDavaoOrTagum = in_array($assignment, ['davao', 'tagum'], true);

        if ($s === 'RNR' && !$isField) {
            return 'RNR is only allowed for Field employees. Use Day Off for Davao/Tagum.';
        }

        if ($s === 'Day Off' && !$isDavaoOrTagum) {
            return 'Day Off is only allowed for Davao/Tagum employees. Use RNR for Field.';
        }

        if ($s === 'Paid Leave' && $isField) {
            return 'Paid Leave is not applicable for Field employees.';
        }

        return null;
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

    private function employeesForFilters(string $assignment, ?string $area)
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

        return $q->orderBy('last_name')->orderBy('first_name')->get();
    }

    private function mapStatus(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $upper = strtoupper($raw);
        if (isset(self::STATUS_CODES[$upper])) {
            return self::STATUS_CODES[$upper];
        }
        foreach (self::STATUS_VALUES as $status) {
            if (strcasecmp($raw, $status) === 0) {
                return $status;
            }
        }
        return null;
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

    private function readTimeCell($cell): string
    {
        $raw = $cell->getValue();
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject($raw)->format('H:i');
            } catch (\Throwable $e) {
                return '';
            }
        }

        $formatted = trim((string) $cell->getFormattedValue());
        if ($formatted === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?:\s*([AP]M))?$/i', $formatted, $m)) {
            $hh = (int) $m[1];
            $mm = str_pad((string) ((int) $m[2]), 2, '0', STR_PAD_LEFT);
            $meridiem = strtoupper((string) ($m[3] ?? ''));
            if ($meridiem === 'AM' && $hh === 12) {
                $hh = 0;
            } elseif ($meridiem === 'PM' && $hh < 12) {
                $hh += 12;
            }
            $hh = max(0, min(23, $hh));
            return str_pad((string) $hh, 2, '0', STR_PAD_LEFT) . ":{$mm}";
        }
        return '';
    }

    private function computeLateMinutes(string $clockIn): ?int
    {
        $minutesIn = $this->parseTimeToMinutes($clockIn);
        if ($minutesIn === null) {
            return null;
        }
        $grace = (7 * 60) + 35; // 07:35
        return max(0, $minutesIn - $grace);
    }

    private function computeUndertimeMinutesFromClockOut(string $clockOut): ?int
    {
        if ($clockOut === '') {
            return null;
        }
        $minutesOut = $this->parseTimeToMinutes($clockOut);
        if ($minutesOut === null) {
            return null;
        }
        $endOfWork = 17 * 60; // 17:00
        if ($minutesOut >= $endOfWork) {
            return 0;
        }
        return max(0, $endOfWork - $minutesOut);
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
