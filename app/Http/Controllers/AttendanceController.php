<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $records = AttendanceRecord::query()
            ->with(['employee'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $payload = $records->map(function (AttendanceRecord $r) {
            $emp = $r->employee;
            $name = $emp ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : '')) : '';
            return [
                'id' => (string) $r->id,
                'employee_id' => $r->employee_id,
                'emp_no' => $emp?->emp_no,
                'emp_name' => $name ?: ($emp?->emp_no ?? ''),
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

    private function validateRecord(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['Present', 'Late', 'Absent', 'Leave'])],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'minutes_late' => ['nullable', 'integer', 'min:0'],
            'minutes_undertime' => ['nullable', 'integer', 'min:0'],
            'ot_hours' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
