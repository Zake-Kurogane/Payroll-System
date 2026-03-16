<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanHistory;
use App\Models\EmployeeLoanSchedule;
use App\Models\Assignment;
use App\Models\AreaPlace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeLoanController extends Controller
{
    public function page()
    {
        $assignments = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        $assignmentOrder = $assignments->all();
        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);

        $grouped = [];
        foreach ($rows as $row) {
            $parent = $row->parent_assignment ?? 'Field';
            $grouped[$parent][] = $row->label;
        }

        $groupedAreaPlaces = [];
        foreach ($assignmentOrder as $label) {
            if (isset($grouped[$label])) {
                $groupedAreaPlaces[$label] = $grouped[$label];
            }
        }

        return view('layouts.loans', [
            'assignments' => $assignments,
            'groupedAreaPlaces' => $groupedAreaPlaces,
        ]);
    }

    public function list(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('loan_type');
        $status = $request->query('status');
        $rawAssign = $request->query('assignment');
        $assignParts = $rawAssign ? explode('|', $rawAssign, 2) : [];
        $assignment = $assignParts[0] ?? null;
        $areaPlace = $assignParts[1] ?? $request->query('area_place');
        $department = $request->query('department');
        $startMonth = $request->query('start_month');

        $loans = EmployeeLoan::query()
            ->with(['employee'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('employee', function ($qq) use ($q) {
                    $qq->where('emp_no', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$q}%"])
                        ->orWhereRaw("CONCAT(last_name,', ',first_name) LIKE ?", ["%{$q}%"]);
                })->orWhere('loan_no', 'like', "%{$q}%");
            })
            ->when($type && strtolower((string) $type) !== 'all', fn ($query) => $query->where('loan_type', $type))
            ->when($status && strtolower((string) $status) !== 'all', fn ($query) => $query->where('status', $status))
            ->when($assignment && strtolower((string) $assignment) !== 'all', function ($query) use ($assignment) {
                $query->whereHas('employee', fn ($qq) => $qq->where('assignment_type', $assignment));
            })
            ->when($areaPlace, function ($query) use ($areaPlace) {
                $query->whereHas('employee', fn ($qq) => $qq->where('area_place', $areaPlace));
            })
            ->when($department && strtolower((string) $department) !== 'all', function ($query) use ($department) {
                $query->whereHas('employee', fn ($qq) => $qq->where('department', $department));
            })
            ->when($startMonth, fn ($query) => $query->where('start_month', $startMonth))
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeLoan $loan) => $this->formatLoan($loan));

        return response()->json($loans);
    }

    public function index(string $empNo)
    {
        $employee = Employee::where('emp_no', $empNo)->firstOrFail();

        $loans = EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeLoan $loan) => $this->formatLoan($loan));

        return response()->json($loans);
    }

    public function show(EmployeeLoan $loan)
    {
        $loan->load(['employee']);
        $data = $this->formatLoan($loan);
        $data['employee'] = $loan->employee ? [
            'emp_no' => $loan->employee->emp_no,
            'name' => trim($loan->employee->last_name . ', ' . $loan->employee->first_name . ($loan->employee->middle_name ? ' ' . $loan->employee->middle_name : '')),
            'department' => $loan->employee->department,
            'assignment' => $loan->employee->assignment_type,
            'position' => $loan->employee->position,
        ] : null;
        return response()->json($data);
    }

    public function history(EmployeeLoan $loan)
    {
        $rows = EmployeeLoanHistory::query()
            ->where('employee_loan_id', $loan->id)
            ->with(['payrollRun:id,run_code'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'payroll_run_id' => $h->payroll_run_id,
                'run_code' => $h->payrollRun?->run_code,
                'period_month' => $h->period_month,
                'cutoff' => $h->cutoff,
                'scheduled_amount' => (float) $h->scheduled_amount,
                'deducted_amount' => (float) $h->deducted_amount,
                'balance_before' => (float) $h->balance_before,
                'balance_after' => (float) $h->balance_after,
                'status' => $h->status,
                'posted_at' => $h->posted_at,
                'remarks' => $h->remarks,
            ]);

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $validated = $this->validateLoan($request);

        $employee = Employee::where('emp_no', $validated['emp_no'])->firstOrFail();
        $totalPayable = $this->resolveTotalPayable($validated);
        if ($totalPayable < (float) ($validated['principal_amount'] ?? 0)) {
            return response()->json(['message' => 'Total payable cannot be less than principal amount.'], 422);
        }

        $termMonths = (int) ($validated['term_months'] ?? 1);
        $monthly = (float) ($validated['monthly_amortization'] ?? 0);
        if (($validated['deduction_method'] ?? 'fixed_months') === 'fixed_months' && $monthly <= 0 && $termMonths > 0) {
            $monthly = round($totalPayable / $termMonths, 2);
        }

        $loan = EmployeeLoan::create([
            'employee_id' => $employee->id,
            'loan_no' => $this->nextLoanNo(),
            'loan_type' => $validated['loan_type'],
            'lender' => $validated['lender'],
            'monthly_amortization' => $monthly,
            'start_month' => $validated['start_month'],
            'start_cutoff' => $validated['start_cutoff'],
            'term_months' => $termMonths,
            'status' => $validated['status'] ?? 'active',
            'reference_no' => $validated['reference_no'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'principal_amount' => $validated['principal_amount'] ?? 0,
            'interest_amount' => $validated['interest_amount'] ?? 0,
            'total_payable' => $totalPayable,
            'approval_date' => $validated['approval_date'] ?? null,
            'release_date' => $validated['release_date'] ?? null,
            'deduction_frequency' => $validated['deduction_frequency'] ?? 'every_cutoff',
            'deduction_method' => $validated['deduction_method'] ?? 'fixed_months',
            'per_cutoff_amount' => $validated['per_cutoff_amount'] ?? null,
            'amount_deducted' => 0,
            'balance_remaining' => $totalPayable,
            'auto_deduct' => (bool) ($validated['auto_deduct'] ?? true),
            'allow_partial' => (bool) ($validated['allow_partial'] ?? true),
            'carry_forward' => (bool) ($validated['carry_forward'] ?? true),
            'stop_on_zero' => (bool) ($validated['stop_on_zero'] ?? true),
            'priority_order' => (int) ($validated['priority_order'] ?? 100),
            'source' => $validated['source'] ?? null,
            'recovery_type' => $validated['recovery_type'] ?? null,
            'approved_by' => $validated['approved_by'] ?? null,
            'encoded_by' => $validated['encoded_by'] ?? null,
        ]);

        $this->generateSchedules($loan);

        return response()->json($this->formatLoan($loan->fresh()), 201);
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        $validated = $this->validateLoan($request, false);

        $totalPayable = $this->resolveTotalPayable($validated, $loan);
        if ($totalPayable < (float) ($validated['principal_amount'] ?? $loan->principal_amount ?? 0)) {
            return response()->json(['message' => 'Total payable cannot be less than principal amount.'], 422);
        }
        $loan->update([
            'loan_type' => $validated['loan_type'] ?? $loan->loan_type,
            'lender' => $validated['lender'] ?? $loan->lender,
            'monthly_amortization' => $validated['monthly_amortization'] ?? $loan->monthly_amortization,
            'start_month' => $validated['start_month'] ?? $loan->start_month,
            'start_cutoff' => $validated['start_cutoff'] ?? $loan->start_cutoff,
            'term_months' => $validated['term_months'] ?? $loan->term_months,
            'status' => $validated['status'] ?? $loan->status,
            'reference_no' => $validated['reference_no'] ?? $loan->reference_no,
            'notes' => $validated['notes'] ?? $loan->notes,
            'principal_amount' => $validated['principal_amount'] ?? $loan->principal_amount,
            'interest_amount' => $validated['interest_amount'] ?? $loan->interest_amount,
            'total_payable' => $totalPayable,
            'approval_date' => $validated['approval_date'] ?? $loan->approval_date,
            'release_date' => $validated['release_date'] ?? $loan->release_date,
            'deduction_frequency' => $validated['deduction_frequency'] ?? $loan->deduction_frequency,
            'deduction_method' => $validated['deduction_method'] ?? $loan->deduction_method,
            'per_cutoff_amount' => $validated['per_cutoff_amount'] ?? $loan->per_cutoff_amount,
            'auto_deduct' => (bool) ($validated['auto_deduct'] ?? $loan->auto_deduct),
            'allow_partial' => (bool) ($validated['allow_partial'] ?? $loan->allow_partial),
            'carry_forward' => (bool) ($validated['carry_forward'] ?? $loan->carry_forward),
            'stop_on_zero' => (bool) ($validated['stop_on_zero'] ?? $loan->stop_on_zero),
            'priority_order' => (int) ($validated['priority_order'] ?? $loan->priority_order),
            'source' => $validated['source'] ?? $loan->source,
            'recovery_type' => $validated['recovery_type'] ?? $loan->recovery_type,
            'approved_by' => $validated['approved_by'] ?? $loan->approved_by,
            'encoded_by' => $validated['encoded_by'] ?? $loan->encoded_by,
        ]);

        $loan->balance_remaining = max(0.0, (float) $loan->total_payable - (float) $loan->amount_deducted);
        $loan->save();

        $loan->schedules()->where('status', 'scheduled')->update(['status' => 'void']);
        $this->generateSchedules($loan);

        return response()->json($this->formatLoan($loan->fresh()));
    }

    public function pause(EmployeeLoan $loan)
    {
        $loan->update(['status' => 'paused']);
        return response()->json($this->formatLoan($loan->fresh()));
    }

    public function resume(EmployeeLoan $loan)
    {
        $loan->update(['status' => 'active']);
        return response()->json($this->formatLoan($loan->fresh()));
    }

    public function close(EmployeeLoan $loan)
    {
        $loan->update(['status' => 'cancelled']);
        $loan->schedules()->where('status', 'scheduled')->update(['status' => 'void']);
        return response()->json($this->formatLoan($loan->fresh()));
    }

    private function validateLoan(Request $request, bool $requireEmp = true): array
    {
        $rules = [
            'loan_type' => ['required', 'string', 'max:100'],
            'lender' => ['required', 'string', 'max:50'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'total_payable' => ['nullable', 'numeric', 'min:0'],
            'approval_date' => ['nullable', 'date'],
            'release_date' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'start_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'start_cutoff' => ['required', Rule::in(['11-25', '26-10'])],
            'term_months' => ['required_if:deduction_method,fixed_months', 'nullable', 'integer', 'min:1', 'max:240'],
            'monthly_amortization' => ['nullable', 'numeric', 'min:0'],
            'deduction_frequency' => ['nullable', Rule::in(['every_cutoff', 'cutoff1_only', 'cutoff2_only', 'monthly'])],
            'deduction_method' => ['nullable', Rule::in(['fixed_months', 'fixed_amount', 'manual'])],
            'per_cutoff_amount' => ['required_if:deduction_method,fixed_amount', 'nullable', 'numeric', 'min:0.01'],
            'auto_deduct' => ['nullable', 'boolean'],
            'allow_partial' => ['nullable', 'boolean'],
            'carry_forward' => ['nullable', 'boolean'],
            'stop_on_zero' => ['nullable', 'boolean'],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'completed', 'cancelled'])],
            'source' => ['nullable', 'string', 'max:50'],
            'recovery_type' => ['nullable', 'string', 'max:50'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'encoded_by' => ['nullable', 'string', 'max:255'],
        ];

        if ($requireEmp) {
            $rules['emp_no'] = ['required', 'string', 'max:10'];
        }

        return $request->validate($rules);
    }

    private function resolveTotalPayable(array $validated, ?EmployeeLoan $loan = null): float
    {
        $principal = (float) ($validated['principal_amount'] ?? ($loan?->principal_amount ?? 0));
        $interest = (float) ($validated['interest_amount'] ?? ($loan?->interest_amount ?? 0));
        if (isset($validated['total_payable'])) {
            return max(0.0, (float) $validated['total_payable']);
        }
        return max(0.0, $principal + $interest);
    }

    private function generateSchedules(EmployeeLoan $loan): void
    {
        $loan->schedules()->where('status', 'scheduled')->update(['status' => 'void']);
        if (!$loan->auto_deduct || $loan->deduction_method === 'manual' || $loan->status !== 'active') {
            return;
        }

        $frequency = $loan->deduction_frequency ?? 'every_cutoff';
        if ($frequency === 'monthly') $frequency = 'cutoff2_only';

        $remaining = (float) $loan->balance_remaining;
        if ($remaining <= 0) return;

        $startMonth = $loan->start_month;
        $startCutoff = $loan->start_cutoff;

        if ($loan->deduction_method === 'fixed_amount') {
            $per = (float) ($loan->per_cutoff_amount ?? 0);
            if ($per <= 0) return;

            $month = $startMonth;
            $cutoff = $startCutoff;
            $guard = 0;
            while ($remaining > 0 && $guard < 480) {
                $ded = min($per, $remaining);
                EmployeeLoanSchedule::create([
                    'employee_loan_id' => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'due_month' => $month,
                    'due_cutoff' => $cutoff,
                    'amount' => $ded,
                    'status' => 'scheduled',
                ]);
                $remaining = round($remaining - $ded, 2);
                [$month, $cutoff] = $this->nextCutoff($month, $cutoff, $frequency);
                $guard++;
            }
            return;
        }

        $term = max(1, (int) ($loan->term_months ?? 1));
        $monthly = (float) ($loan->monthly_amortization ?? 0);
        if ($monthly <= 0 && $term > 0) {
            $monthly = round($loan->total_payable / $term, 2);
            $loan->monthly_amortization = $monthly;
            $loan->save();
        }

        $splitA = floor(($monthly * 100) / 2) / 100;
        $splitB = round($monthly - $splitA, 2);

        $currentMonth = $startMonth;
        for ($i = 0; $i < $term; $i++) {
            if ($frequency === 'cutoff1_only') {
                EmployeeLoanSchedule::create([
                    'employee_loan_id' => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'due_month' => $currentMonth,
                    'due_cutoff' => '11-25',
                    'amount' => min($monthly, $remaining),
                    'status' => 'scheduled',
                ]);
            } elseif ($frequency === 'cutoff2_only') {
                EmployeeLoanSchedule::create([
                    'employee_loan_id' => $loan->id,
                    'employee_id' => $loan->employee_id,
                    'due_month' => $currentMonth,
                    'due_cutoff' => '26-10',
                    'amount' => min($monthly, $remaining),
                    'status' => 'scheduled',
                ]);
            } else {
                if ($i === 0 && $startCutoff === '26-10') {
                    EmployeeLoanSchedule::create([
                        'employee_loan_id' => $loan->id,
                        'employee_id' => $loan->employee_id,
                        'due_month' => $currentMonth,
                        'due_cutoff' => '26-10',
                        'amount' => min($monthly, $remaining),
                        'status' => 'scheduled',
                    ]);
                } else {
                    EmployeeLoanSchedule::create([
                        'employee_loan_id' => $loan->id,
                        'employee_id' => $loan->employee_id,
                        'due_month' => $currentMonth,
                        'due_cutoff' => '11-25',
                        'amount' => min($splitA, $remaining),
                        'status' => 'scheduled',
                    ]);
                    EmployeeLoanSchedule::create([
                        'employee_loan_id' => $loan->id,
                        'employee_id' => $loan->employee_id,
                        'due_month' => $currentMonth,
                        'due_cutoff' => '26-10',
                        'amount' => min($splitB, $remaining),
                        'status' => 'scheduled',
                    ]);
                }
            }

            $remaining = round($remaining - $monthly, 2);
            $currentMonth = $this->nextMonth($currentMonth);
            if ($remaining <= 0) break;
        }
    }

    private function nextCutoff(string $month, string $cutoff, string $frequency): array
    {
        if ($frequency === 'cutoff1_only') {
            return [$this->nextMonth($month), '11-25'];
        }
        if ($frequency === 'cutoff2_only') {
            return [$this->nextMonth($month), '26-10'];
        }
        if ($cutoff === '11-25') {
            return [$month, '26-10'];
        }
        return [$this->nextMonth($month), '11-25'];
    }

    private function nextMonth(string $month): string
    {
        [$y, $m] = array_map('intval', explode('-', $month));
        $m++;
        if ($m > 12) { $m = 1; $y++; }
        return sprintf('%04d-%02d', $y, $m);
    }

    private function formatLoan(EmployeeLoan $loan): array
    {
        $schedules = $loan->schedules()->get();
        $totalSchedules = (int) $schedules->count();
        $appliedSchedules = (int) $schedules->where('status', 'applied')->count();
        $remaining = (float) ($loan->balance_remaining ?? max(0.0, (float) $loan->total_payable - (float) $loan->amount_deducted));
        $endMonth = $this->addMonths($loan->start_month, max(0, (int) $loan->term_months - 1));
        $emp = $loan->relationLoaded('employee') ? $loan->employee : null;
        $empName = $emp ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : '')) : null;

        return [
            'id' => $loan->id,
            'loan_no' => $loan->loan_no,
            'employee_id' => $loan->employee_id,
            'emp_no' => $emp?->emp_no,
            'employee_name' => $empName,
            'employee_department' => $emp?->department,
            'employee_assignment' => $emp?->assignment_type,
            'loan_type' => $loan->loan_type,
            'lender' => $loan->lender,
            'principal_amount' => (float) $loan->principal_amount,
            'interest_amount' => (float) $loan->interest_amount,
            'total_payable' => (float) $loan->total_payable,
            'monthly_amortization' => (float) $loan->monthly_amortization,
            'start_month' => $loan->start_month,
            'start_cutoff' => $loan->start_cutoff,
            'term_months' => (int) $loan->term_months,
            'status' => $loan->status,
            'reference_no' => $loan->reference_no,
            'notes' => $loan->notes,
            'approval_date' => $loan->approval_date,
            'release_date' => $loan->release_date,
            'end_month' => $endMonth,
            'deduction_frequency' => $loan->deduction_frequency,
            'deduction_method' => $loan->deduction_method,
            'per_cutoff_amount' => (float) ($loan->per_cutoff_amount ?? 0),
            'auto_deduct' => (bool) $loan->auto_deduct,
            'allow_partial' => (bool) $loan->allow_partial,
            'carry_forward' => (bool) $loan->carry_forward,
            'stop_on_zero' => (bool) $loan->stop_on_zero,
            'priority_order' => (int) $loan->priority_order,
            'amount_deducted' => (float) $loan->amount_deducted,
            'balance_remaining' => (float) $remaining,
            'payments_made' => $appliedSchedules,
            'payments_left' => max(0, $totalSchedules - $appliedSchedules),
            'source' => $loan->source,
            'recovery_type' => $loan->recovery_type,
            'approved_by' => $loan->approved_by,
            'encoded_by' => $loan->encoded_by,
        ];
    }

    private function addMonths(string $month, int $months): string
    {
        [$y, $m] = array_map('intval', explode('-', $month));
        $m += $months;
        while ($m > 12) { $m -= 12; $y++; }
        while ($m <= 0) { $m += 12; $y--; }
        return sprintf('%04d-%02d', $y, $m);
    }

    private function nextLoanNo(): string
    {
        $last = EmployeeLoan::query()->orderByDesc('id')->value('loan_no');
        if (preg_match('/LN-(\d+)/', (string) $last, $m)) {
            $num = (int) $m[1] + 1;
        } else {
            $num = EmployeeLoan::query()->max('id') + 1;
        }
        return 'LN-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }
}

