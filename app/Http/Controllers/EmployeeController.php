<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAreaHistory;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Assignment;
use App\Models\AreaPlace;
use App\Models\Department;
use App\Models\BasedLocation;
use App\Models\ExternalPosition;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $canViewComp = Gate::allows('viewCompensation');
        $q = trim((string) request()->query('q', ''));
        $status = request()->query('status');
        $dept = request()->query('department');
        $rawAssign = request()->query('assignment');
        $assignParts = $rawAssign ? explode('|', $rawAssign, 2) : [];
        $assign = $assignParts[0] ?? null;
        $areaPlace = $assignParts[1] ?? request()->query('area_place');

        $with = ['employmentStatus'];
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $with[] = 'positions:id,name';
        }
        if (Schema::hasColumn('employees', 'external_position_id') && Schema::hasTable('external_positions')) {
            $with[] = 'externalPosition:id,name';
        }
        if (Schema::hasColumn('employees', 'external_position_id') && Schema::hasTable('external_positions')) {
            $with[] = 'externalPosition:id,name';
        }

        $employees = Employee::query()
            ->with($with)
            ->when(!$canViewComp, function ($query) {
                $query->select([
                    'id',
                    'emp_no',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'status',
                    'employment_status_id',
                    'birthday',
                    'mobile',
                    'address',
                    'address_province',
                    'address_city',
                    'address_barangay',
                    'address_street',
                    'email',
                    'department',
                    'based_location',
                    'position',
                    'employment_type',
                    'pay_type',
                    'date_hired',
                    'assignment_type',
                    'area_place',
                    'external_area',
                    'external_position_id',
                    'bank_name',
                    'bank_account_name',
                    'bank_account_number',
                    'payout_method',
                    'sss',
                    'philhealth',
                    'pagibig',
                    'tin',
                ]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('emp_no', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$q}%"])
                        ->orWhereRaw("CONCAT(last_name,', ',first_name) LIKE ?", ["%{$q}%"]);
                });
            })
            ->when($status, function ($query) use ($status) {
                if (is_numeric($status)) {
                    $query->where('employment_status_id', (int) $status);
                    return;
                }
                $label = trim((string) $status);
                if ($label === '' || strtolower($label) === 'all') {
                    return;
                }
                $query->where(function ($qq) use ($label) {
                    $qq->whereRaw('LOWER(status) = ?', [strtolower($label)])
                       ->orWhereHas('employmentStatus', function ($qs) use ($label) {
                           $qs->whereRaw('LOWER(label) = ?', [strtolower($label)]);
                       });
                });
            })
            ->when($dept && strtolower((string) $dept) !== 'all', fn($query) => $query->where('department', $dept))
            ->when($assign && strtolower((string) $assign) !== 'all', fn($query) => $query->where('assignment_type', $assign))
            ->when($areaPlace, fn($query) => $query->where('area_place', $areaPlace))
            ->orderBy('emp_no')
            ->get();

        return response()->json($employees);
    }

    public function page(Request $request)
    {
        $canViewComp = Gate::allows('viewCompensation');
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $dept = $request->query('department');
        $rawAssign = $request->query('assignment');
        $assignParts = $rawAssign ? explode('|', $rawAssign, 2) : [];
        $assign = $assignParts[0] ?? null;
        $areaPlace = $assignParts[1] ?? $request->query('area_place');
        $perPage = (int) $request->query('rows', 20);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $with = ['employmentStatus'];
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $with[] = 'positions:id,name';
        }

        $employees = Employee::query()
            ->with($with)
            ->when(!$canViewComp, function ($query) {
                $query->select([
                    'id',
                    'emp_no',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'status',
                    'employment_status_id',
                    'birthday',
                    'mobile',
                    'address',
                    'address_province',
                    'address_city',
                    'address_barangay',
                    'address_street',
                    'email',
                    'department',
                    'based_location',
                    'position',
                    'employment_type',
                    'pay_type',
                    'date_hired',
                    'assignment_type',
                    'area_place',
                    'external_area',
                    'external_position_id',
                    'bank_name',
                    'bank_account_name',
                    'bank_account_number',
                    'payout_method',
                    'sss',
                    'philhealth',
                    'pagibig',
                    'tin',
                ]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('emp_no', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$q}%"])
                        ->orWhereRaw("CONCAT(last_name,', ',first_name) LIKE ?", ["%{$q}%"]);
                });
            })
            ->when($status, function ($query) use ($status) {
                if (is_numeric($status)) {
                    $statusId = (int) $status;
                    $label = EmploymentStatus::where('id', $statusId)->value('label');
                    $query->where(function ($qq) use ($statusId, $label) {
                        $qq->where('employment_status_id', $statusId);
                        if ($label) {
                            $qq->orWhereRaw('LOWER(status) = ?', [strtolower($label)]);
                        }
                    });
                    return;
                }
                $label = trim((string) $status);
                if ($label === '' || strtolower($label) === 'all') {
                    return;
                }
                $query->where(function ($qq) use ($label) {
                    $qq->whereRaw('LOWER(status) = ?', [strtolower($label)])
                        ->orWhereHas('employmentStatus', function ($qs) use ($label) {
                            $qs->whereRaw('LOWER(label) = ?', [strtolower($label)]);
                        });
                });
            })
            ->when($dept && strtolower((string) $dept) !== 'all', fn($query) => $query->where('department', $dept))
            ->when($assign && strtolower((string) $assign) !== 'all', fn($query) => $query->where('assignment_type', $assign))
            ->when($areaPlace, fn($query) => $query->where('area_place', $areaPlace))
            ->orderBy('emp_no')
            ->paginate($perPage)
            ->withQueryString();

        $statuses = Cache::remember('employees.statuses', 300, function () {
            return EmploymentStatus::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label']);
        });

        $departments = Cache::remember('employees.departments', 300, function () {
            if (Schema::hasTable('departments')) {
                return Department::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->pluck('label');
            }
            return Employee::query()
                ->select('department')
                ->whereNotNull('department')
                ->where('department', '<>', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');
        });

        $basedLocations = Cache::remember('employees.based_locations', 300, function () {
            if (Schema::hasTable('based_locations')) {
                return BasedLocation::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->pluck('label');
            }
            return collect();
        });

        $assignments = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        // Order groups by the assignments sort_order
        $assignmentOrder = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label')
            ->all();
        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);
        $grouped = [];
        foreach ($rows as $row) {
            $parent = $row->parent_assignment ?? 'Field';
            $grouped[$parent][] = $row->label;
        }
        // Sort by assignment sort_order
        $ordered = [];
        foreach ($assignmentOrder as $label) {
            if (isset($grouped[$label])) {
                $ordered[$label] = $grouped[$label];
            }
        }
        $groupedAreaPlaces = $ordered;

        $positions = collect();
        if (Schema::hasTable('positions')) {
            $positions = Position::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $externalPositions = collect();
        if (Schema::hasTable('external_positions')) {
            $externalPositions = ExternalPosition::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('layouts.emp_records', [
            'employees' => $employees,
            'statuses' => $statuses,
            'departments' => $departments,
            'basedLocations' => $basedLocations,
            'assignments' => $assignments,
            'groupedAreaPlaces' => $groupedAreaPlaces,
            'positions' => $positions,
            'externalPositions' => $externalPositions,
        ]);
    }

    public function filters()
    {
        $statuses = Cache::remember('employees.statuses', 300, function () {
            return EmploymentStatus::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label']);
        });

        $departments = Cache::remember('employees.departments', 300, function () {
            if (Schema::hasTable('departments')) {
                return Department::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->pluck('label');
            }
            return Employee::query()
                ->select('department')
                ->whereNotNull('department')
                ->where('department', '<>', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');
        });

        $basedLocations = Cache::remember('employees.based_locations', 300, function () {
            if (Schema::hasTable('based_locations')) {
                return BasedLocation::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->pluck('label');
            }
            return collect();
        });

        $assignments = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        // Return grouped and ordered by assignment sort_order
        $assignmentOrder = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label')
            ->all();
        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);
        $grouped = [];
        foreach ($rows as $row) {
            $parent = $row->parent_assignment ?? 'Field';
            $grouped[$parent][] = $row->label;
        }
        $ordered = [];
        foreach ($assignmentOrder as $label) {
            if (isset($grouped[$label])) {
                $ordered[$label] = $grouped[$label];
            }
        }
        $areaPlaces = $ordered;

        $employmentTypes = EmploymentType::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        $positions = collect();
        if (Schema::hasTable('positions')) {
            $positions = Position::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $externalPositions = collect();
        if (Schema::hasTable('external_positions')) {
            $externalPositions = ExternalPosition::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return response()->json([
            'statuses' => $statuses,
            'departments' => $departments,
            'based_locations' => $basedLocations,
            'assignments' => $assignments,
            'area_places' => $areaPlaces,
            'employment_types' => $employmentTypes,
            'positions' => $positions,
            'external_positions' => $externalPositions,
        ]);
    }

    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rows = Employee::query()
            ->select(['id', 'emp_no', 'first_name', 'last_name', 'middle_name'])
            ->where(function ($qq) use ($q) {
                $qq->where('emp_no', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$q}%"])
                    ->orWhereRaw("CONCAT(last_name,', ',first_name) LIKE ?", ["%{$q}%"]);
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get()
            ->map(function ($e) {
                $name = trim($e->last_name . ', ' . $e->first_name . ($e->middle_name ? ' ' . $e->middle_name : ''));
                return [
                    'id'     => $e->id,
                    'emp_no' => $e->emp_no,
                    'name'   => $name,
                    'label'  => "{$e->emp_no} — {$name}",
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function nextId()
    {
        $maxNum = Employee::pluck('emp_no')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->max() ?? 0;

        $next = min($maxNum + 1, 9999);

        return response()->json(['next_id' => str_pad($next, 4, '0', STR_PAD_LEFT)]);
    }

    public function heartbeat()
    {
        $row = Employee::query()
            ->selectRaw('MAX(updated_at) as max_updated_at, COUNT(*) as total')
            ->first();

        return response()->json([
            'max_updated_at' => $row?->max_updated_at ? (string) $row->max_updated_at : null,
            'total' => (int) ($row->total ?? 0),
        ]);
    }

    public function areaHistory(string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();

        // Build area history directly from attendance records —
        // same source as the DTR drawer, so they always match.
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNotNull('area_place')
            ->where('area_place', '<>', '')
            ->orderBy('date')
            ->pluck('area_place', 'date');

        if ($records->isNotEmpty()) {
            // Collapse into change-point entries (first date each area started)
            $entries = [];
            $prevArea = null;
            foreach ($records as $date => $area) {
                if ($area !== $prevArea) {
                    $entries[] = ['area_place' => $area, 'effective_date' => $date];
                    $prevArea = $area;
                }
            }
            return response()->json(array_reverse($entries));
        }

        // Fallback: no attendance records yet — use formal history table
        $history = EmployeeAreaHistory::where('employee_id', $employee->id)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'area_place', 'effective_date', 'created_at'])
            ->map(fn($h) => [
                'area_place'     => $h->area_place,
                'effective_date' => $h->effective_date?->format('Y-m-d'),
            ]);

        return response()->json($history);
    }

    public function paidLeaveBalances(Request $request)
    {
        $year = $request->integer('year', now()->year);

        $employees = Employee::query()
            ->whereNotNull('assignment_type')
            ->where('assignment_type', '<>', '')
            ->whereRaw('LOWER(TRIM(COALESCE(employment_type,""))) = ?', ['regular'])
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereRaw('LOWER(status) NOT IN (?,?)', ['inactive', 'resigned']);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'emp_no', 'first_name', 'middle_name', 'last_name', 'assignment_type']);

        $usedCounts = \App\Models\AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'Paid Leave')
            ->whereYear('date', $year)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) as used')
            ->pluck('used', 'employee_id');

        return response()->json($employees->map(fn($emp) => [
            'emp_no'          => $emp->emp_no,
            'name'            => trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : '')),
            'assignment_type' => $emp->assignment_type,
            'total'           => 5,
            'used'            => (int) ($usedCounts[$emp->id] ?? 0),
            'remaining'       => 5 - (int) ($usedCounts[$emp->id] ?? 0),
        ]));
    }

    public function paidLeaveBalance(string $empNo, Request $request)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();
        $isRegular = strtolower((string) ($employee->employment_type ?? '')) === 'regular';
        $hasAssignment = !empty($employee->assignment_type);
        if (!$isRegular || !$hasAssignment) {
            return response()->json(['applicable' => false]);
        }
        $year  = $request->integer('year', now()->year);
        $total = 5;
        $used  = \App\Models\AttendanceRecord::where('employee_id', $employee->id)
            ->where('status', 'Paid Leave')
            ->whereYear('date', $year)
            ->count();
        return response()->json([
            'applicable' => true,
            'total'      => $total,
            'used'       => $used,
            'remaining'  => $total - $used,
            'year'       => $year,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEmployee($request);

        $positionIds = [];
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $positionIds = $this->normalizePositionIds($validated['position_ids'] ?? []);
            unset($validated['position_ids']);
            $validated['position'] = $this->positionDisplayFromIds($positionIds);
        }

        // Department is no longer collected in the Add Employee form; keep DB insert valid.
        if (!array_key_exists('department', $validated) || $validated['department'] === null) {
            $validated['department'] = '';
        }

        if (empty($validated['employment_status_id']) && !empty($validated['status'])) {
            $statusId = EmploymentStatus::whereRaw('LOWER(label) = ?', [strtolower($validated['status'])])->value('id');
            $validated['employment_status_id'] = $statusId;
        }

        if (strtolower(trim((string) ($validated['employment_type'] ?? ''))) !== 'regular') {
            $validated['external_area'] = null;
            $validated['external_position_id'] = null;
        }

        $employee = Employee::create($validated);
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $employee->positions()->sync($positionIds);
        }

        if (($validated['assignment_type'] ?? '') === 'Field' && !empty($validated['area_place'])) {
            EmployeeAreaHistory::create([
                'employee_id'    => $employee->id,
                'area_place'     => $validated['area_place'],
                'effective_date' => now()->toDateString(),
                'created_by'     => Auth::id(),
            ]);
        }

        $this->forgetEmployeeCaches();

        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $employee->load('positions:id,name');
        }
        if (Schema::hasColumn('employees', 'external_position_id') && Schema::hasTable('external_positions')) {
            $employee->load('externalPosition:id,name');
        }
        return response()->json($employee, 201);
    }

    public function update(Request $request, string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();
        $oldAreaPlace = $employee->area_place;
        $validated = $this->validateEmployee($request, $employee->id);

        $positionIds = [];
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $positionIds = $this->normalizePositionIds($validated['position_ids'] ?? []);
            unset($validated['position_ids']);
            $validated['position'] = $this->positionDisplayFromIds($positionIds);
        }

        if (empty($validated['employment_status_id']) && !empty($validated['status'])) {
            $statusId = EmploymentStatus::whereRaw('LOWER(label) = ?', [strtolower($validated['status'])])->value('id');
            $validated['employment_status_id'] = $statusId;
        }

        if (strtolower(trim((string) ($validated['employment_type'] ?? ''))) !== 'regular') {
            $validated['external_area'] = null;
            $validated['external_position_id'] = null;
        }

        $employee->update($validated);
        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $employee->positions()->sync($positionIds);
        }

        if (($validated['assignment_type'] ?? '') === 'Field'
            && !empty($validated['area_place'])
            && $validated['area_place'] !== $oldAreaPlace
        ) {
            // If no history exists yet, backfill the previous area starting from hire date
            if (!empty($oldAreaPlace) && !EmployeeAreaHistory::where('employee_id', $employee->id)->exists()) {
                $startDate = $employee->date_hired
                    ? $employee->date_hired->format('Y-m-d')
                    : $employee->created_at->toDateString();
                EmployeeAreaHistory::create([
                    'employee_id'    => $employee->id,
                    'area_place'     => $oldAreaPlace,
                    'effective_date' => $startDate,
                    'created_by'     => Auth::id(),
                ]);
            }

            EmployeeAreaHistory::create([
                'employee_id'    => $employee->id,
                'area_place'     => $validated['area_place'],
                'effective_date' => now()->toDateString(),
                'created_by'     => Auth::id(),
            ]);
        }

        $this->forgetEmployeeCaches();

        if (Schema::hasTable('positions') && Schema::hasTable('employee_position')) {
            $employee->load('positions:id,name');
        }
        if (Schema::hasColumn('employees', 'external_position_id') && Schema::hasTable('external_positions')) {
            $employee->load('externalPosition:id,name');
        }
        return response()->json($employee);
    }

    public function bulkAssign(Request $request)
    {
        $assignment = trim((string) $request->input('assignment', ''));
        $areaPlaceLabelsForAssignment = $assignment !== ''
            ? AreaPlace::where('is_active', true)->where('parent_assignment', $assignment)->pluck('label')->all()
            : [];

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'max:50'],
            'assignment' => ['required', Rule::in(Assignment::where('is_active', true)->pluck('label')->all())],
            'area_place' => count($areaPlaceLabelsForAssignment)
                ? ['required', 'string', 'max:255', Rule::in($areaPlaceLabelsForAssignment)]
                : ['nullable', 'string', 'max:255'],
        ]);

        $assignment = $validated['assignment'];
        $areaPlace = trim((string) ($validated['area_place'] ?? '')) !== '' ? $validated['area_place'] : null;
        if (!count($areaPlaceLabelsForAssignment)) {
            $areaPlace = null;
        }
        $update = [
            'assignment_type' => $assignment,
            'area_place' => $areaPlace,
        ];

        if ($assignment === 'Field' && $areaPlace) {
            $today = now()->toDateString();
            $userId = Auth::id();
            $now = now();

            $affected = Employee::whereIn('emp_no', $validated['ids'])
                ->where(function ($q) use ($areaPlace) {
                    $q->where('area_place', '!=', $areaPlace)
                      ->orWhereNull('area_place');
                })
                ->get(['id']);

            if ($affected->isNotEmpty()) {
                $historyRows = $affected->map(fn($e) => [
                    'employee_id'    => $e->id,
                    'area_place'     => $areaPlace,
                    'effective_date' => $today,
                    'created_by'     => $userId,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ])->all();

                EmployeeAreaHistory::insert($historyRows);
            }
        }

        $updated = Employee::whereIn('emp_no', $validated['ids'])
            ->update($update);

        $this->forgetEmployeeCaches();

        return response()->json(['updated' => $updated]);
    }

    public function destroy(string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();
        $employee->delete();
        $this->forgetEmployeeCaches();

        return response()->json(['deleted' => true]);
    }

    private function externalAreaRequiredRule(Request $request): array
    {
        $employmentType = strtolower(trim((string) $request->input('employment_type', '')));
        if ($employmentType === 'regular') {
            return ['required'];
        }
        return [];
    }

    private function externalPositionRequiredRule(Request $request): array
    {
        $employmentType = strtolower(trim((string) $request->input('employment_type', '')));
        if ($employmentType === 'regular') {
            return ['required'];
        }
        return [];
    }

    private function forgetEmployeeCaches(): void
    {
        Cache::forget('employees.statuses');
        Cache::forget('employees.departments');
        Cache::forget('employees.based_locations');
        Cache::forget('employees.assignments');
        Cache::forget('employees.area_places');
        Cache::forget('employees.area_places_grouped');
    }

    private function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        $uniqueEmpNo = Rule::unique('employees', 'emp_no');
        if ($ignoreId !== null) {
            $uniqueEmpNo = $uniqueEmpNo->ignore($ignoreId);
        }

        $activeAssignmentLabels = Assignment::where('is_active', true)->pluck('label')->all();
        if ($ignoreId !== null) {
            $existingAssignment = Employee::where('id', $ignoreId)->value('assignment_type');
            if ($existingAssignment && !in_array($existingAssignment, $activeAssignmentLabels, true)) {
                $activeAssignmentLabels[] = $existingAssignment;
            }
        }

        $assignment = trim((string) $request->input('assignment_type', ''));
        $areaPlaceLabelsForAssignment = $assignment !== ''
            ? AreaPlace::where('is_active', true)->where('parent_assignment', $assignment)->pluck('label')->all()
            : [];

        $rules = [
            'emp_no' => ['required', 'digits:4', $uniqueEmpNo],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'birthday' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_province' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'address_barangay' => ['nullable', 'string', 'max:255'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => array_merge(
                ['nullable', 'string', 'max:255'],
                Schema::hasTable('departments')
                    ? [Rule::in(Department::where('is_active', true)->pluck('label')->all())]
                    : [],
            ),
            'based_location' => array_merge(
                ['nullable', 'string', 'max:255'],
                Schema::hasTable('based_locations')
                    ? [Rule::in(BasedLocation::where('is_active', true)->pluck('label')->all())]
                    : [],
            ),
            'position' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['nullable', 'string', 'max:50'],
            'date_hired' => ['nullable', 'date'],
            'assignment_type' => ['required', Rule::in($activeAssignmentLabels)],
            'area_place' => count($areaPlaceLabelsForAssignment)
                ? ['required', 'string', 'max:255', Rule::in($areaPlaceLabelsForAssignment)]
                : ['nullable', 'string', 'max:255'],
            'external_area' => array_merge(
                ['nullable', 'string', 'max:255'],
                AreaPlace::where('is_active', true)->pluck('label')->isNotEmpty()
                    ? [Rule::in(AreaPlace::where('is_active', true)->pluck('label')->all())]
                    : [],
                $this->externalAreaRequiredRule($request)
            ),
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'allowance' => ['required', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'payout_method' => ['nullable', Rule::in(['CASH', 'BANK'])],
            'sss' => ['nullable', 'string', 'max:50'],
            'philhealth' => ['nullable', 'string', 'max:50'],
            'pagibig' => ['nullable', 'string', 'max:50'],
            'tin' => ['nullable', 'string', 'max:50'],
        ];

        if (Schema::hasTable('positions')) {
            $rules['position_ids'] = ['required', 'array', 'min:1'];
            $rules['position_ids.*'] = ['integer', 'distinct', Rule::exists('positions', 'id')->where('is_active', true)];
            // Position display becomes derived from selected ids when positions are enabled.
            $rules['position'] = ['nullable', 'string', 'max:255'];
        } else {
            // Backward-compatible fallback if migrations are not yet applied.
            $rules['position'] = ['required', 'string', 'max:255'];
        }

        if (Schema::hasColumn('employees', 'external_position_id') && Schema::hasTable('external_positions')) {
            $rules['external_position_id'] = array_merge(
                ['nullable', 'integer', Rule::exists('external_positions', 'id')->where('is_active', true)],
                $this->externalPositionRequiredRule($request),
            );
        }

        $validated = $request->validate($rules);
        $validated['area_place'] = trim((string) ($validated['area_place'] ?? '')) !== '' ? $validated['area_place'] : null;
        if (!count($areaPlaceLabelsForAssignment)) {
            $validated['area_place'] = null;
        }
        return $validated;
    }

    private function normalizePositionIds(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (int) $v : null,
            $ids,
        ), fn ($v) => $v !== null && $v > 0)));

        return $clean;
    }

    private function positionDisplayFromIds(array $positionIds): string
    {
        if (!count($positionIds)) {
            return '';
        }

        $names = Position::whereIn('id', $positionIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $display = implode(', ', $names);
        if (mb_strlen($display) > 255) {
            return mb_substr($display, 0, 252) . '...';
        }
        return $display;
    }
}
