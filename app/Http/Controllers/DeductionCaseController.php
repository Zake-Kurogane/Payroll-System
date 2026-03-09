<?php

namespace App\Http\Controllers;

use App\Models\DeductionCase;
use App\Models\DeductionSchedule;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeductionCaseController extends Controller
{
    public function index(string $emp_no)
    {
        $employee = Employee::where('emp_no', $emp_no)->firstOrFail();

        $cases = DeductionCase::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeductionCase $c) => $this->formatCase($c));

        return response()->json($cases);
    }

    public function store(Request $request, string $emp_no)
    {
        $employee = Employee::where('emp_no', $emp_no)->firstOrFail();

        $validated = $request->validate([
            'type'              => ['required', Rule::in(['shortage', 'charge'])],
            'description'       => ['nullable', 'string', 'max:500'],
            'amount_total'      => ['required', 'numeric', 'min:0.01'],
            'plan_type'         => ['required', Rule::in(['one_time', 'installment'])],
            'installment_count' => ['required_if:plan_type,installment', 'nullable', 'integer', 'min:2', 'max:24'],
            'start_month'       => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'start_cutoff'      => ['required', Rule::in(['11-25', '26-10'])],
        ]);

        $case = DeductionCase::create([
            'employee_id'       => $employee->id,
            'type'              => $validated['type'],
            'description'       => $validated['description'] ?? null,
            'amount_total'      => $validated['amount_total'],
            'plan_type'         => $validated['plan_type'],
            'installment_count' => $validated['plan_type'] === 'installment' ? (int) $validated['installment_count'] : null,
            'start_month'       => $validated['start_month'],
            'start_cutoff'      => $validated['start_cutoff'],
            'status'            => 'active',
        ]);

        $this->generateSchedules($case);

        return response()->json($this->formatCase($case->fresh()), 201);
    }

    public function update(Request $request, string $emp_no, DeductionCase $case)
    {
        $employee = Employee::where('emp_no', $emp_no)->firstOrFail();
        abort_if($case->employee_id !== $employee->id, 403);

        $validated = $request->validate([
            'plan_type'         => ['required', Rule::in(['one_time', 'installment'])],
            'installment_count' => ['required_if:plan_type,installment', 'nullable', 'integer', 'min:2', 'max:24'],
            'start_month'       => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'start_cutoff'      => ['required', Rule::in(['11-25', '26-10'])],
        ]);

        // Void all future (not yet applied) schedule lines
        $case->schedules()->where('status', 'scheduled')->update(['status' => 'void']);

        $case->update([
            'plan_type'         => $validated['plan_type'],
            'installment_count' => $validated['plan_type'] === 'installment' ? (int) $validated['installment_count'] : null,
            'start_month'       => $validated['start_month'],
            'start_cutoff'      => $validated['start_cutoff'],
        ]);

        // Recalculate remaining amount (total minus already applied)
        $appliedTotal = $case->schedules()->where('status', 'applied')->sum('amount');
        $remaining = max(0, (float) $case->amount_total - $appliedTotal);

        // Regenerate schedules for the remaining balance
        if ($remaining > 0) {
            $this->generateSchedules($case, $remaining);
        }

        return response()->json($this->formatCase($case->fresh()));
    }

    public function close(string $emp_no, DeductionCase $case)
    {
        $employee = Employee::where('emp_no', $emp_no)->firstOrFail();
        abort_if($case->employee_id !== $employee->id, 403);

        // Void all future scheduled lines
        $case->schedules()->where('status', 'scheduled')->update(['status' => 'void']);
        $case->update(['status' => 'closed']);

        return response()->json($this->formatCase($case->fresh()));
    }

    public function schedules(string $emp_no, DeductionCase $case)
    {
        $employee = Employee::where('emp_no', $emp_no)->firstOrFail();
        abort_if($case->employee_id !== $employee->id, 403);

        $schedules = $case->schedules()->orderBy('due_month')->orderBy('due_cutoff')->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'due_month'  => $s->due_month,
                'due_cutoff' => $s->due_cutoff,
                'amount'     => (float) $s->amount,
                'status'     => $s->status,
                'applied_at' => $s->applied_at,
            ]);

        return response()->json($schedules);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function generateSchedules(DeductionCase $case, ?float $overrideAmount = null): void
    {
        $total = $overrideAmount ?? (float) $case->amount_total;
        $month = $case->start_month;
        $cutoff = $case->start_cutoff;

        if ($case->plan_type === 'one_time') {
            DeductionSchedule::create([
                'deduction_case_id' => $case->id,
                'employee_id'       => $case->employee_id,
                'due_month'         => $month,
                'due_cutoff'        => $cutoff,
                'amount'            => $total,
                'status'            => 'scheduled',
            ]);
            return;
        }

        // Installment: split evenly, remainder goes to last
        $count = max(1, (int) $case->installment_count);
        $each = floor(($total / $count) * 100) / 100;
        $remainder = round($total - ($each * ($count - 1)), 2);

        for ($i = 0; $i < $count; $i++) {
            $amount = ($i === $count - 1) ? $remainder : $each;
            DeductionSchedule::create([
                'deduction_case_id' => $case->id,
                'employee_id'       => $case->employee_id,
                'due_month'         => $month,
                'due_cutoff'        => $cutoff,
                'amount'            => $amount,
                'status'            => 'scheduled',
            ]);
            [$month, $cutoff] = $this->nextCutoff($month, $cutoff);
        }
    }

    /**
     * Returns the next [month, cutoff] after the given one.
     * 11-25 → same month 26-10
     * 26-10 → next month 11-25
     */
    private function nextCutoff(string $month, string $cutoff): array
    {
        if ($cutoff === '11-25') {
            return [$month, '26-10'];
        }
        // advance one month
        [$y, $m] = array_map('intval', explode('-', $month));
        $m++;
        if ($m > 12) { $m = 1; $y++; }
        return [sprintf('%04d-%02d', $y, $m), '11-25'];
    }

    private function formatCase(DeductionCase $case): array
    {
        $applied  = $case->schedules()->where('status', 'applied')->sum('amount');
        $remaining = max(0, (float) $case->amount_total - (float) $applied);

        return [
            'id'                => $case->id,
            'type'              => $case->type,
            'description'       => $case->description,
            'amount_total'      => (float) $case->amount_total,
            'amount_applied'    => (float) $applied,
            'amount_remaining'  => round($remaining, 2),
            'plan_type'         => $case->plan_type,
            'installment_count' => $case->installment_count,
            'start_month'       => $case->start_month,
            'start_cutoff'      => $case->start_cutoff,
            'status'            => $case->status,
            'created_at'        => $case->created_at,
        ];
    }
}
