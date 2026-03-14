<?php

namespace App\Http\Controllers;

use App\Models\AreaPlace;
use App\Models\AttendanceRecord;
use App\Models\CaseLookupValue;
use App\Models\Employee;
use App\Models\EmployeeCase;
use App\Models\EmployeeCaseDecision;
use App\Models\EmployeeCaseHearing;
use App\Models\EmployeeCaseParty;
use App\Models\EmployeeCaseSanction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeCaseController extends Controller
{
    public function page()
    {
        return view('layouts.employee_cases');
    }

    public function filters()
    {
        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);

        $areaPlaces = [];
        foreach ($rows as $row) {
            $areaPlaces[$row->parent_assignment ?? 'Other'][] = $row->label;
        }

        return response()->json([
            'stages'      => CaseLookupValue::ofType('stage'),
            'sanctions'   => CaseLookupValue::ofType('sanction'),
            'area_places' => $areaPlaces,
        ]);
    }

    public function index(Request $request)
    {
        $v = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'case_type' => ['nullable', 'string'],
            'sanction_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $cases = EmployeeCase::query()
            ->with(['parties.employee', 'sanctions'])
            ->when(!empty($v['employee_id']), function ($q) use ($v) {
                $q->whereHas('parties', fn($p) => $p->where('employee_id', $v['employee_id']));
            })
            ->when(!empty($v['search']), function ($q) use ($v) {
                $s = '%' . $v['search'] . '%';
                $q->where('case_no', 'like', $s)
                    ->orWhere('title', 'like', $s);
            })
            ->when(!empty($v['status']) && $v['status'] !== 'all', fn($q) => $q->where('status', $v['status']))
            ->when(!empty($v['case_type']) && $v['case_type'] !== 'all', fn($q) => $q->where('case_type', $v['case_type']))
            ->when(!empty($v['month']), function ($q) use ($v) {
                [$y, $m] = array_map('intval', explode('-', $v['month']));
                $from = sprintf('%04d-%02d-01', $y, $m);
                $to = \Carbon\Carbon::create($y, $m, 1)->endOfMonth()->toDateString();
                $q->whereBetween('date_reported', [$from, $to]);
            })
            ->when(!empty($v['date_from']), fn($q) => $q->whereDate('date_reported', '>=', $v['date_from']))
            ->when(!empty($v['date_to']), fn($q) => $q->whereDate('date_reported', '<=', $v['date_to']))
            ->orderByDesc('date_reported')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (EmployeeCase $c) {
                $name = function ($e) {
                    if (!$e) return null;
                    return trim($e->last_name . ', ' . $e->first_name . ($e->middle_name ? ' ' . $e->middle_name : ''));
                };
                $respondents = $c->parties->where('role', 'respondent')->map(fn($p) => $name($p->employee))->filter()->values()->all();
                $complainants = $c->parties->where('role', 'complainant')->map(fn($p) => $name($p->employee))->filter()->values()->all();
                $latestSanction = $c->sanctions->sortByDesc('created_at')->first();
                $sanctionType = $latestSanction?->sanction_type ?? 'none';
                $sanctionStatus = $latestSanction?->status ?? null;

                return [
                    'id' => $c->id,
                    'case_no' => $c->case_no,
                    'case_type' => $c->case_type,
                    'date_reported' => optional($c->date_reported)->format('Y-m-d'),
                    'status' => $c->status,
                    'title' => $c->title,
                    'respondents' => $respondents,
                    'complainants' => $complainants,
                    'sanction_type' => $sanctionType,
                    'sanction_status' => $sanctionStatus,
                ];
            });

        // Apply sanction filter after mapping to avoid heavy joins
        if (!empty($v['sanction_type']) && $v['sanction_type'] !== 'all') {
            $cases = $cases->filter(fn($row) => ($row['sanction_type'] ?? 'none') === $v['sanction_type'])->values();
        }

        // Stats
        $stats = [
            'open' => $cases->filter(fn($r) => $r['status'] !== 'closed')->count(),
            'for_hearing' => $cases->filter(fn($r) => $r['status'] === 'for_hearing')->count(),
            'for_decision' => $cases->filter(fn($r) => $r['status'] === 'for_decision')->count(),
            'active_sanctions' => $cases->filter(fn($r) => in_array($r['sanction_status'], ['pending', 'active'], true))->count(),
            'terminated' => $cases->filter(fn($r) => ($r['sanction_type'] ?? '') === 'termination')->count(),
        ];

        return response()->json([
            'data' => $cases->values(),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'date_reported'    => ['nullable', 'date'],
            'incident_date'    => ['nullable', 'date'],
            'location'         => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'remarks'          => ['nullable', 'string'],
            'respondent_ids'   => ['required', 'array', 'min:1'],
            'respondent_ids.*' => ['integer', 'exists:employees,id'],
            'complainant_ids'  => ['nullable', 'array'],
            'complainant_ids.*'=> ['integer', 'exists:employees,id'],
        ]);

        // Auto-generate title from respondent name(s)
        $respondentNames = Employee::whereIn('id', $v['respondent_ids'] ?? [])
            ->orderBy('last_name')
            ->get(['last_name', 'first_name'])
            ->map(fn($e) => trim("{$e->last_name}, {$e->first_name}"))
            ->join(' / ');
        $dateStr = $v['incident_date'] ?? $v['date_reported'] ?? now()->toDateString();
        $title = "Incident Report — {$respondentNames} ({$dateStr})";

        $caseNo = $this->nextCaseNo();
        $case = null;
        DB::transaction(function () use ($v, $caseNo, $title, &$case) {
            $case = EmployeeCase::create([
                'case_no'      => $caseNo,
                'case_type'    => 'incident_report',
                'date_reported'=> $v['date_reported'] ?? now()->toDateString(),
                'incident_date'=> $v['incident_date'] ?? null,
                'location'     => $v['location'] ?? null,
                'title'        => $title,
                'description'  => trim(($v['description'] ?? '') . ($v['remarks'] ? "\n\nRemarks: " . $v['remarks'] : '')),
                'status'       => 'reported',
                'created_by'   => auth()->id(),
            ]);

            foreach ($v['respondent_ids'] ?? [] as $empId) {
                EmployeeCaseParty::create([
                    'case_id' => $case->id,
                    'employee_id' => $empId,
                    'role' => 'respondent',
                ]);
            }
            foreach ($v['complainant_ids'] ?? [] as $empId) {
                EmployeeCaseParty::create([
                    'case_id' => $case->id,
                    'employee_id' => $empId,
                    'role' => 'complainant',
                ]);
            }
        });

        return response()->json(['case_no' => $caseNo, 'id' => $case->id], 201);
    }

    public function show(EmployeeCase $case, \Illuminate\Http\Request $request)
    {
        $case->load([
            'parties.employee',
            'documents',
            'hearings',
            'decisions',
            'sanctions',
        ]);

        if ($request->wantsJson()) {
            $name = fn($e) => $e ? trim($e->last_name . ', ' . $e->first_name . ($e->middle_name ? ' ' . $e->middle_name : '')) : null;
            return response()->json([
                'id'            => $case->id,
                'case_no'       => $case->case_no,
                'case_type'     => $case->case_type,
                'status'        => $case->status,
                'date_reported' => optional($case->date_reported)->format('Y-m-d'),
                'incident_date' => optional($case->incident_date)->format('Y-m-d'),
                'location'      => $case->location,
                'title'         => $case->title,
                'description'   => $case->description,
                'respondents'   => $case->parties->where('role', 'respondent')->map(fn($p) => $name($p->employee))->filter()->values(),
                'complainants'  => $case->parties->where('role', 'complainant')->map(fn($p) => $name($p->employee))->filter()->values(),
                'witnesses'     => $case->parties->where('role', 'witness')->map(fn($p) => $name($p->employee))->filter()->values(),
                'sanctions'     => $case->sanctions->map(fn($s) => [
                    'sanction_type' => $s->sanction_type,
                    'status'        => $s->status,
                    'effective_from'=> optional($s->effective_from)->format('Y-m-d'),
                    'effective_to'  => optional($s->effective_to)->format('Y-m-d'),
                ]),
                'hearings'      => $case->hearings->map(fn($h) => [
                    'hearing_date' => optional($h->hearing_date)->format('Y-m-d'),
                    'location'     => $h->location,
                    'status'       => $h->status,
                    'notes'        => $h->notes,
                ]),
            ]);
        }

        return view('layouts.employee_case_show', ['case' => $case]);
    }

    public function employeeHistory(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        // 1. Cases the employee is a party to
        $cases = EmployeeCase::query()
            ->with(['parties' => fn($q) => $q->where('employee_id', $employeeId), 'sanctions'])
            ->whereHas('parties', fn($p) => $p->where('employee_id', $employeeId))
            ->orderByDesc('date_reported')
            ->orderByDesc('id')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'case_no'       => $c->case_no,
                'title'         => $c->title,
                'status'        => $c->status,
                'date_reported' => optional($c->date_reported)->format('Y-m-d'),
                'role'          => $c->parties->first()?->role ?? 'involved',
            ]);

        // 2. Sanctions directly tied to the employee
        $sanctions = EmployeeCaseSanction::query()
            ->where('employee_id', $employeeId)
            ->with('case:id,case_no,title')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'sanction_type'  => $s->sanction_type,
                'days_suspended' => $s->days_suspended,
                'effective_from' => $s->effective_from?->format('Y-m-d'),
                'effective_to'   => $s->effective_to?->format('Y-m-d'),
                'status'         => $s->status,
                'case_no'        => $s->case?->case_no,
                'remarks'        => $s->remarks,
            ]);

        // 3. Tardiness summary from attendance records
        $tardinessRow = AttendanceRecord::where('employee_id', $employeeId)
            ->where('minutes_late', '>', 0)
            ->selectRaw('COUNT(*) as days_late, SUM(minutes_late) as total_minutes')
            ->first();

        return response()->json([
            'employee' => [
                'name'   => trim($employee->last_name . ', ' . $employee->first_name),
                'emp_no' => $employee->emp_no,
            ],
            'cases'     => $cases,
            'sanctions' => $sanctions,
            'tardiness' => [
                'days_late'     => (int) ($tardinessRow->days_late ?? 0),
                'total_minutes' => (int) ($tardinessRow->total_minutes ?? 0),
            ],
        ]);
    }

    public function advance(Request $request, EmployeeCase $case)
    {
        $v = $request->validate([
            'status'            => ['required', 'string'],
            'hearing_date'      => ['nullable', 'date'],
            'hearing_location'  => ['nullable', 'string', 'max:255'],
            'hearing_notes'     => ['nullable', 'string'],
            'decision_date'     => ['nullable', 'date'],
            'decision_summary'  => ['nullable', 'string'],
            'decision_by'       => ['nullable', 'string', 'max:255'],
            'sanction_type'     => ['nullable', 'string'],
            'sanction_days'     => ['nullable', 'integer', 'min:0'],
            'sanction_from'     => ['nullable', 'date'],
            'sanction_to'       => ['nullable', 'date'],
            'sanction_remarks'  => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($case, $v) {
            $case->update(['status' => $v['status']]);

            if (!empty($v['hearing_date'])) {
                $case->hearings()->create([
                    'hearing_date' => $v['hearing_date'],
                    'location'     => $v['hearing_location'] ?? null,
                    'notes'        => $v['hearing_notes'] ?? null,
                    'status'       => 'scheduled',
                ]);
            }

            if (!empty($v['decision_summary']) || !empty($v['decision_date'])) {
                $case->decisions()->create([
                    'decision_date'    => $v['decision_date'] ?? now()->toDateString(),
                    'decision_summary' => $v['decision_summary'] ?? null,
                    'decision_by'      => $v['decision_by'] ?? null,
                ]);
            }

            if (!empty($v['sanction_type']) && $v['sanction_type'] !== 'none') {
                $respondentIds = $case->parties()->where('role', 'respondent')->pluck('employee_id');
                foreach ($respondentIds as $empId) {
                    EmployeeCaseSanction::create([
                        'case_id'        => $case->id,
                        'employee_id'    => $empId,
                        'sanction_type'  => $v['sanction_type'],
                        'days_suspended' => $v['sanction_days'] ?? null,
                        'effective_from' => $v['sanction_from'] ?? null,
                        'effective_to'   => $v['sanction_to'] ?? null,
                        'status'         => 'active',
                        'remarks'        => $v['sanction_remarks'] ?? null,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true, 'status' => $case->fresh()->status]);
    }

    private function nextCaseNo(): string
    {
        $year = now()->year;
        $prefix = substr((string) $year, -2);
        $latest = EmployeeCase::whereYear('created_at', $year)
            ->orderByDesc('case_no')
            ->value('case_no');

        $seq = 0;
        if ($latest && Str::startsWith($latest, $prefix)) {
            $seq = (int) substr($latest, 3);
        }
        $next = str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
        return "{$prefix}-{$next}";
    }
}
