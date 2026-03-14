<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDisciplinaryRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeDisciplineController extends Controller
{
    private const ALLOWED_TYPES = ['memo', 'sanction', 'nte'];

    public function index(string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();

        $rows = EmployeeDisciplinaryRecord::where('employee_id', $employee->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'type', 'reference', 'issued_at', 'remarks', 'created_at']);

        return response()->json($rows->map(function ($r) {
            return [
                'id' => $r->id,
                'type' => $r->type,
                'reference' => $r->reference,
                'issued_at' => $r->issued_at?->format('Y-m-d'),
                'remarks' => $r->remarks,
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ];
        }));
    }

    public function tardiness(string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();

        $base = AttendanceRecord::where('employee_id', $employee->id);
        $totalMinutes = (int) ((clone $base)->sum('minutes_late') ?? 0);
        $lateDays = (int) ((clone $base)->where('minutes_late', '>', 0)->count() ?? 0);

        $now = Carbon::now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        $yearMinutes = (int) AttendanceRecord::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->sum('minutes_late');

        $monthMinutes = (int) AttendanceRecord::where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('minutes_late');

        return response()->json([
            'total_minutes' => $totalMinutes,
            'late_days' => $lateDays,
            'year' => $year,
            'year_minutes' => $yearMinutes,
            'month' => sprintf('%04d-%02d', $year, $month),
            'month_minutes' => $monthMinutes,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);

        $expectedHeaders = ['emp_no', 'type', 'date', 'remarks', 'reference'];
        $headers = [];
        foreach (range('A', 'E') as $idx => $col) {
            $headers[] = strtolower(trim((string) $sheet->getCell("{$col}1")->getValue()));
        }
        if ($headers !== $expectedHeaders) {
            return response()->json([
                'message' => 'Import failed. Header must be: emp_no, type, date, remarks, reference',
            ], 422);
        }

        $errors = [];
        $created = 0;

        $highestRow = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $empNo = trim((string) $sheet->getCell("A{$row}")->getValue());
            $typeRaw = strtolower(trim((string) $sheet->getCell("B{$row}")->getValue()));
            $dateCell = $sheet->getCell("C{$row}");
            $remarks = trim((string) $sheet->getCell("D{$row}")->getValue());
            $reference = trim((string) $sheet->getCell("E{$row}")->getValue());

            if ($empNo === '' && $typeRaw === '' && $dateCell->getValue() === null && $remarks === '' && $reference === '') {
                continue;
            }

            if ($empNo === '') {
                $errors[] = "Row {$row}: emp_no is required.";
                continue;
            }
            if (!in_array($typeRaw, self::ALLOWED_TYPES, true)) {
                $errors[] = "Row {$row}: type must be Memo, Sanction, or NTE.";
                continue;
            }

            $employee = Employee::where('emp_no', $empNo)->first();
            if (!$employee) {
                $errors[] = "Row {$row}: emp_no {$empNo} not found.";
                continue;
            }

            $issuedAt = null;
            try {
                $raw = $dateCell->getValue();
                if ($raw !== null && $raw !== '') {
                    if (is_numeric($raw)) {
                        $issuedAt = ExcelDate::excelToDateTimeObject($raw)->format('Y-m-d');
                    } else {
                        $issuedAt = Carbon::parse((string) $raw)->format('Y-m-d');
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$row}: invalid date.";
                continue;
            }

            EmployeeDisciplinaryRecord::create([
                'employee_id' => $employee->id,
                'type' => $typeRaw,
                'issued_at' => $issuedAt,
                'remarks' => $remarks ?: null,
                'reference' => $reference ?: null,
            ]);
            $created++;
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Import completed with some errors.',
                'created' => $created,
                'errors' => $errors,
            ], 422);
        }

        return response()->json(['message' => 'Imported successfully.', 'created' => $created]);
    }
}
