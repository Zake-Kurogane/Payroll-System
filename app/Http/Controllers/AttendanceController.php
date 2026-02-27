<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollCalendarSetting;
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
        'Unpaid Leave',
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
        'UL' => 'Unpaid Leave',
        'LWOP' => 'Unpaid Leave',
        'HD' => 'Half-day',
        'OFF' => 'Day Off',
        'HOL' => 'Holiday',
        'LOA' => 'LOA',
    ];

    private const TEMPLATE_CODES = ['P', 'L', 'A', 'UL', 'PL', 'HD', 'OFF', 'HOL', 'LOA'];

    public function index(Request $request)
    {
        $v = Validator::make($request->query(), [
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['nullable', Rule::in(['11-25', '26-10'])],
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ])->validate();

        $records = AttendanceRecord::query()
            ->with(['employee'])
            ->orderByDesc('date')
            ->orderByDesc('id');

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
        if ($assignment === 'Area' && $area) {
            $records->whereHas('employee', fn ($q) => $q->where('area_place', $area));
        }

        $payload = $records->get()->map(function (AttendanceRecord $r) {
            $emp = $r->employee;
            $name = $emp
                ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                : '';
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
                'area_place' => $emp?->area_place,
                'date' => $r->date?->format('Y-m-d'),
                'status' => $r->status,
                'clock_in' => $r->clock_in,
                'clock_out' => $r->clock_out,
                'minutes_late' => $r->minutes_late ?? 0,
                'minutes_undertime' => $r->minutes_undertime ?? 0,
                'ot_hours' => (float) ($r->ot_hours ?? 0),
            ];
        })->values();

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRecord($request);
        $record = AttendanceRecord::create($validated);

        return response()->json(['id' => $record->id], 201);
    }

    public function update(Request $request, AttendanceRecord $record)
    {
        $validated = $this->validateRecord($request);
        $record->update($validated);

        return response()->json(['updated' => true]);
    }

    public function destroy(AttendanceRecord $record)
    {
        $record->delete();
        return response()->json(['deleted' => true]);
    }

    public function downloadTemplate(Request $request)
    {
        $v = Validator::make($request->query(), [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['required', Rule::in(['11-25', '26-10'])],
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ])->validate();

        $month = $v['month'];
        $cutoff = $v['cutoff'];
        $assignment = $v['assignment'] ?? 'All';
        $area = $v['area'] ?? null;

        $employees = $this->employeesForFilters($assignment, $area);
        [$from, $to] = $this->cutoffRange($month, $cutoff);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $statusListFormula = '"' . implode(',', self::TEMPLATE_CODES) . '"';
        $noTimeFormula = 'OR($F{row}="A",$F{row}="UL",$F{row}="PL",$F{row}="OFF",$F{row}="HOL",$F{row}="LOA")';
        $timeRequiredFormula = 'OR($F{row}="P",$F{row}="L",$F{row}="HD")';

        $date = $from->copy();
        while ($date->lte($to)) {
            $sheetTitle = $date->format('Y-m-d');
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            $headers = [
                'emp_no',
                'name',
                'dept',
                'assignment',
                'area',
                'status',
                'clock_in',
                'clock_out',
                'minutes_late',
                'minutes_undertime',
                'ot_hours',
            ];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:K1')->applyFromArray([
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
                $defaultStatus = ($isSunday && in_array($assignType, ['Tagum', 'Davao'], true)) ? 'OFF' : '';
                $name = trim($e->last_name . ', ' . $e->first_name . ($e->middle_name ? ' ' . $e->middle_name : ''));
                $sheet->fromArray([
                    $e->emp_no,
                    $name,
                    $e->department ?? '',
                    $e->assignment_type ?? '',
                    $e->area_place ?? '',
                    $defaultStatus,
                    '',
                    '',
                    '',
                    '',
                    '',
                ], null, "A{$row}");

                $dv = new DataValidation();
                $dv->setType(DataValidation::TYPE_LIST);
                $dv->setErrorStyle(DataValidation::STYLE_STOP);
                $dv->setAllowBlank(false);
                $dv->setShowDropDown(true);
                $dv->setFormula1($statusListFormula);
                $dv->setErrorTitle('Invalid status');
                $dv->setError('Choose from the dropdown list.');

                $sheet->getCell("F{$row}")->setDataValidation($dv);

                // Minutes late: Late => compute after 07:35, otherwise 0
                $lateFormula = sprintf(
                    '=IF($F%d="L",MAX(0,ROUND(($G%d-TIME(7,35,0))*1440,0)),0)',
                    $row,
                    $row
                );
                $sheet->setCellValue("I{$row}", $lateFormula);

                // Validation: block time/undertime/ot inputs for no-time statuses
                $rowFormula = str_replace('{row}', (string) $row, $noTimeFormula);

                foreach (['G', 'H'] as $col) {
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
                foreach (['G', 'H'] as $col) {
                    $dvReq = new DataValidation();
                    $dvReq->setType(DataValidation::TYPE_CUSTOM);
                    $dvReq->setErrorStyle(DataValidation::STYLE_STOP);
                    $dvReq->setAllowBlank(true);
                    $dvReq->setErrorTitle('Missing time');
                    $dvReq->setError('Clock in/out is required for this status.');
                    $dvReq->setFormula1(sprintf('=IF(%s,%s%d<>"",TRUE)', $reqFormula, $col, $row));
                    $sheet->getCell("{$col}{$row}")->setDataValidation($dvReq);
                }

                foreach (['J', 'K'] as $col) {
                    $dvNum = new DataValidation();
                    $dvNum->setType(DataValidation::TYPE_CUSTOM);
                    $dvNum->setErrorStyle(DataValidation::STYLE_STOP);
                    $dvNum->setAllowBlank(true);
                    $dvNum->setErrorTitle('Invalid input');
                    $dvNum->setError('Minutes/OT must be 0 or blank for this status.');
                    $dvNum->setFormula1(sprintf('=IF(%s,OR(%s%d="",%s%d=0),TRUE)', $rowFormula, $col, $row, $col, $row));
                    $sheet->getCell("{$col}{$row}")->setDataValidation($dvNum);
                }
                $row++;
            }

            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $date->addDay();
        }

        $label = $assignment === 'Area' && $area ? "AREA - {$area}" : $assignment;
        $cutoffLabel = $cutoff === '11-25' ? '11-25' : '26-10';
        $filename = "{$label} ATTENDANCE ({$cutoffLabel} {$month}).xlsx";

        $tmpPath = storage_path('app/tmp_attendance_template.xlsx');
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $v = Validator::make($request->all(), [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'cutoff' => ['required', Rule::in(['11-25', '26-10'])],
            'assignment' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:xlsx'],
        ])->validate();

        $month = $v['month'];
        $cutoff = $v['cutoff'];

        [$from, $to] = $this->cutoffRange($month, $cutoff);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());

        $errors = [];
        $rowsToUpsert = [];
        $expectedHeader = [
            'emp_no',
            'name',
            'dept',
            'assignment',
            'area',
            'status',
            'clock_in',
            'clock_out',
            'minutes_late',
            'minutes_undertime',
            'ot_hours',
        ];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetName = $sheet->getTitle();
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sheetName)) {
                $errors[] = "{$sheetName}: Sheet name must be YYYY-MM-DD.";
                continue;
            }

            $workDate = Carbon::parse($sheetName)->startOfDay();
            if ($workDate->lt($from) || $workDate->gt($to)) {
                $errors[] = "{$sheetName}: Date is outside selected cutoff.";
                continue;
            }

            $header = [];
            foreach (range('A', 'K') as $col) {
                $header[] = trim((string) $sheet->getCell("{$col}1")->getValue());
            }
            if ($header !== $expectedHeader) {
                $errors[] = "{$sheetName}: Header mismatch. Please use the system template.";
                continue;
            }

            $highestRow = $sheet->getHighestDataRow();
            for ($r = 2; $r <= $highestRow; $r++) {
                $empNo = trim((string) $sheet->getCell("A{$r}")->getValue());
                $rawStatus = trim((string) $sheet->getCell("F{$r}")->getValue());

                $clockIn = $this->readTimeCell($sheet->getCell("G{$r}"));
                $clockOut = $this->readTimeCell($sheet->getCell("H{$r}"));

                $lateCell = $sheet->getCell("I{$r}");
                $underCell = $sheet->getCell("J{$r}");
                $otCell = $sheet->getCell("K{$r}");

                if (
                    $empNo === '' &&
                    $rawStatus === '' &&
                    $clockIn === '' &&
                    $clockOut === '' &&
                    $lateCell->getValue() === null &&
                    $underCell->getValue() === null &&
                    $otCell->getValue() === null
                ) {
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
                $ot = $this->parseNumberCell($otCell, $errors, "{$sheetName} row {$r}: ot_hours must be a number.");

                if ($status) {
                    $noTime = ['Absent', 'Leave', 'Unpaid Leave', 'Paid Leave', 'Day Off', 'LOA', 'Holiday'];

                    if (in_array($status, $noTime, true)) {
                        if ($clockIn !== '' || $clockOut !== '') {
                            $errors[] = "{$sheetName} row {$r}: clock_in/out must be empty for {$status}.";
                        }
                        if (($late ?? 0) > 0 || ($under ?? 0) > 0 || ($ot ?? 0) > 0) {
                            $errors[] = "{$sheetName} row {$r}: minutes/ot must be 0 for {$status}.";
                        }
                    } elseif ($status === 'Half-day') {
                        if ($clockIn === '' && $clockOut === '') {
                            $errors[] = "{$sheetName} row {$r}: clock_in or clock_out is required for {$status}.";
                        }
                    } else {
                        if ($clockIn === '' || $clockOut === '') {
                            $errors[] = "{$sheetName} row {$r}: clock_in and clock_out are required for {$status}.";
                        }
                    }
                }

                if ($empNo !== '' && !Employee::where('emp_no', $empNo)->exists()) {
                    $errors[] = "{$sheetName} row {$r}: emp_no \"{$empNo}\" not found.";
                }

                if (in_array($status, ['Late', 'Present'], true)) {
                    $computedLate = $this->computeLateMinutes($clockIn);
                    if ($computedLate !== null) {
                        $late = $computedLate;
                    }
                }

                $rowsToUpsert[] = [
                    'date' => $workDate->toDateString(),
                    'emp_no' => $empNo,
                    'status' => $status ?: '',
                    'clock_in' => $clockIn ?: null,
                    'clock_out' => $clockOut ?: null,
                    'minutes_late' => (int) ($late ?? 0),
                    'minutes_undertime' => (int) ($under ?? 0),
                    'ot_hours' => (float) ($ot ?? 0),
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Import failed. Fix the errors and re-upload.',
                'errors' => $errors,
            ], 422);
        }

        DB::transaction(function () use ($rowsToUpsert) {
            foreach ($rowsToUpsert as $row) {
                $emp = Employee::where('emp_no', $row['emp_no'])->first();
                if (!$emp) {
                    continue;
                }

                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $emp->id, 'date' => $row['date']],
                    [
                        'status' => $row['status'],
                        'clock_in' => $row['clock_in'],
                        'clock_out' => $row['clock_out'],
                        'minutes_late' => $row['minutes_late'],
                        'minutes_undertime' => $row['minutes_undertime'],
                        'ot_hours' => $row['ot_hours'],
                    ]
                );
            }
        });

        return response()->json(['message' => 'Imported successfully.']);
    }

    private function validateRecord(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(self::STATUS_VALUES)],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'minutes_late' => ['nullable', 'integer', 'min:0'],
            'minutes_undertime' => ['nullable', 'integer', 'min:0'],
            'ot_hours' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function cutoffRange(string $month, string $cutoff): array
    {
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        [$y, $m] = array_map('intval', explode('-', $month));
        $base = Carbon::create($y, $m, 1)->startOfDay();

        if ($cutoff === '11-25') {
            return $this->cutoffWindow($base, (int) ($calendar->cutoff_a_from ?? 11), (int) ($calendar->cutoff_a_to ?? 25));
        }
        return $this->cutoffWindow($base, (int) ($calendar->cutoff_b_from ?? 26), (int) ($calendar->cutoff_b_to ?? 10));
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
        if ($assignment === 'Area' && $area) {
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
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $formatted, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$hh}:{$m[2]}";
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
