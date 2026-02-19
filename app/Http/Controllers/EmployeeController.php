<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Assignment;
use App\Models\AreaPlace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $q = trim((string) request()->query('q', ''));
        $status = request()->query('status');
        $dept = request()->query('department');
        $assign = request()->query('assignment');
        $areaPlace = request()->query('area_place');

        $employees = Employee::query()
            ->with(['employmentStatus'])
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
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $dept = $request->query('department');
        $assign = $request->query('assignment');
        $areaPlace = $request->query('area_place');
        $perPage = (int) $request->query('rows', 20);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $employees = Employee::query()
            ->with(['employmentStatus'])
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
            return Employee::query()
                ->select('department')
                ->whereNotNull('department')
                ->where('department', '<>', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');
        });

        $assignments = Cache::remember('employees.assignments', 300, function () {
            return Assignment::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label');
        });

        $areaPlaces = Cache::remember('employees.area_places', 300, function () {
            return AreaPlace::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label');
        });

        return view('layouts.emp_records', [
            'employees' => $employees,
            'statuses' => $statuses,
            'departments' => $departments,
            'assignments' => $assignments,
            'areaPlaces' => $areaPlaces,
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
            return Employee::query()
                ->select('department')
                ->whereNotNull('department')
                ->where('department', '<>', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');
        });

        $assignments = Cache::remember('employees.assignments', 300, function () {
            return Assignment::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label');
        });

        $areaPlaces = Cache::remember('employees.area_places', 300, function () {
            return AreaPlace::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label');
        });

        return response()->json([
            'statuses' => $statuses,
            'departments' => $departments,
            'assignments' => $assignments,
            'area_places' => $areaPlaces,
        ]);
    }

    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rows = Employee::query()
            ->select(['emp_no', 'first_name', 'last_name', 'middle_name'])
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
                    'emp_no' => $e->emp_no,
                    'name' => $name,
                    'label' => "{$e->emp_no} — {$name}",
                ];
            })
            ->values();

        return response()->json($rows);
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

    public function store(Request $request)
    {
        $validated = $this->validateEmployee($request);

        if (empty($validated['employment_status_id']) && !empty($validated['status'])) {
            $statusId = EmploymentStatus::whereRaw('LOWER(label) = ?', [strtolower($validated['status'])])->value('id');
            $validated['employment_status_id'] = $statusId;
        }

        $employee = Employee::create($validated);
        $this->forgetEmployeeCaches();

        return response()->json($employee, 201);
    }

    public function update(Request $request, string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();
        $validated = $this->validateEmployee($request, $employee->id);

        if (empty($validated['employment_status_id']) && !empty($validated['status'])) {
            $statusId = EmploymentStatus::whereRaw('LOWER(label) = ?', [strtolower($validated['status'])])->value('id');
            $validated['employment_status_id'] = $statusId;
        }

        $employee->update($validated);
        $this->forgetEmployeeCaches();

        return response()->json($employee);
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'max:50'],
            'assignment' => ['required', Rule::in(Assignment::where('is_active', true)->pluck('label')->all())],
            'area_place' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = $validated['assignment'];
        $areaPlace = $validated['area_place'] ?? null;
        $update = ['assignment_type' => $assignment];
        if ($assignment === 'Area') {
            $update['area_place'] = $areaPlace;
        } else {
            $update['area_place'] = null;
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

    private function forgetEmployeeCaches(): void
    {
        Cache::forget('employees.statuses');
        Cache::forget('employees.departments');
        Cache::forget('employees.assignments');
        Cache::forget('employees.area_places');
    }

    private function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        $uniqueEmpNo = Rule::unique('employees', 'emp_no');
        if ($ignoreId !== null) {
            $uniqueEmpNo = $uniqueEmpNo->ignore($ignoreId);
        }

        return $request->validate([
            'emp_no' => ['required', 'digits:4', $uniqueEmpNo],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'birthday' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['nullable', 'string', 'max:50'],
            'date_hired' => ['nullable', 'date'],
            'assignment_type' => ['required', Rule::in(Assignment::where('is_active', true)->pluck('label')->all())],
            'area_place' => ['nullable', 'string', 'max:255', Rule::in(AreaPlace::where('is_active', true)->pluck('label')->all())],
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
        ]);
    }
}
