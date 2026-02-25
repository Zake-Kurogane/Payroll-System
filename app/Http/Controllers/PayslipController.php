<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayrollRunRowOverride;
use App\Models\Payslip;
use App\Models\PayslipAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    public function runs()
    {
        $runs = PayrollRun::query()
            ->with(['createdBy'])
            ->orderByDesc('id')
            ->get()
            ->map(function (PayrollRun $run) {
                $rows = PayrollRunRow::query()->where('payroll_run_id', $run->id);
                return [
                    'id' => $run->id,
                    'run_code' => $run->run_code,
                    'period_month' => $run->period_month,
                    'cutoff' => $run->cutoff,
                    'assignment_filter' => $run->assignment_filter,
                    'status' => $run->status,
                    'created_by_name' => $run->createdBy?->name,
                    'created_at' => $run->created_at,
                    'locked_at' => $run->locked_at,
                    'released_at' => $run->released_at,
                    'headcount' => $rows->count(),
                    'total_net' => (float) $rows->sum('net_pay'),
                ];
            });

        return response()->json($runs);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);

        $payslips = Payslip::query()
            ->with(['employee', 'adjustments'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('id')
            ->get();

        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $payload = $payslips->map(function (Payslip $p) use ($rows, $run) {
            $emp = $p->employee;
            $row = $rows->get($p->employee_id);
            $name = $emp
                ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                : '';

            return [
                'id' => $p->id,
                'payslip_no' => $p->payslip_no,
                'payroll_run_id' => $run->id,
                'period_month' => $run->period_month,
                'cutoff' => $run->cutoff,
                'release_status' => $p->release_status,
                'delivery_status' => $p->delivery_status,
                'generated_at' => $p->generated_at,
                'employee_id' => $p->employee_id,
                'emp_no' => $emp?->emp_no,
                'emp_name' => $name,
                'department' => $emp?->department,
                'position' => $emp?->position,
                'employment_type' => $emp?->employment_type,
                'assignment_type' => $emp?->assignment_type,
                'area_place' => $emp?->area_place,
                'pay_method' => $row?->pay_method ?? ($emp?->payout_method ?: 'CASH'),
                'bank_name' => $emp?->bank_name,
                'bank_account_number' => $emp?->bank_account_number,
                'basic_pay_cutoff' => (float) ($row?->basic_pay_cutoff ?? 0),
                'allowance_cutoff' => (float) ($row?->allowance_cutoff ?? 0),
                'ot_hours' => (float) ($row?->ot_hours ?? 0),
                'ot_pay' => (float) ($row?->ot_pay ?? 0),
                'gross' => (float) ($row?->gross ?? 0),
                'attendance_deduction' => (float) ($row?->attendance_deduction ?? 0),
                'sss_ee' => (float) ($row?->sss_ee ?? 0),
                'philhealth_ee' => (float) ($row?->philhealth_ee ?? 0),
                'pagibig_ee' => (float) ($row?->pagibig_ee ?? 0),
                'tax' => (float) ($row?->tax ?? 0),
                'cash_advance' => (float) ($row?->cash_advance ?? 0),
                'deductions_total' => (float) ($row?->deductions_total ?? 0),
                'net_pay' => (float) ($row?->net_pay ?? 0),
                'sss_er' => (float) ($row?->sss_er ?? 0),
                'philhealth_er' => (float) ($row?->philhealth_er ?? 0),
                'pagibig_er' => (float) ($row?->pagibig_er ?? 0),
                'employer_share_total' => (float) ($row?->employer_share_total ?? 0),
                'adjustments' => $p->adjustments->map(function (PayslipAdjustment $a) {
                    return [
                        'type' => $a->type,
                        'name' => $a->name,
                        'amount' => (float) $a->amount,
                    ];
                })->values(),
            ];
        });

        return response()->json($payload);
    }

    public function generate(PayrollRun $run)
    {
        if (!in_array($run->status, ['Locked', 'Released'], true)) {
            return response()->json(['message' => 'Run must be locked or released before generating payslips.'], 409);
        }

        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $createdCount = 0;

        DB::transaction(function () use ($rows, $overrides, $run, &$createdCount) {
            foreach ($rows as $row) {
                $emp = Employee::query()->find($row->employee_id);
                $baseNo = 'PS-' . ($run->run_code ?: ('RUN-' . $run->id)) . '-' . ($emp?->emp_no ?: $row->employee_id);
                $payslip = Payslip::query()->where('payroll_run_id', $run->id)
                    ->where('employee_id', $row->employee_id)
                    ->first();

                if (!$payslip) {
                    $payslipNo = $this->uniquePayslipNo($baseNo);
                    $payslip = Payslip::create([
                        'payroll_run_id' => $run->id,
                        'employee_id' => $row->employee_id,
                        'payslip_no' => $payslipNo,
                        'generated_at' => now(),
                        'release_status' => 'Draft',
                        'delivery_status' => 'Not Sent',
                    ]);
                    $createdCount++;
                } elseif (!$payslip->generated_at) {
                    $payslip->generated_at = now();
                    $payslip->save();
                }

                $override = $overrides->get($row->employee_id);
                $adjustments = $override?->adjustments ?? [];
                if (is_string($adjustments)) {
                    $adjustments = json_decode($adjustments, true) ?: [];
                }

                PayslipAdjustment::query()->where('payslip_id', $payslip->id)->delete();
                $adjRows = [];
                foreach ($adjustments as $adj) {
                    $adjRows[] = [
                        'payslip_id' => $payslip->id,
                        'type' => ($adj['type'] ?? 'earning') === 'deduction' ? 'deduction' : 'earning',
                        'name' => (string) ($adj['name'] ?? 'Adjustment'),
                        'amount' => (float) ($adj['amount'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($adjRows) {
                    PayslipAdjustment::query()->insert($adjRows);
                }
            }
        });

        return response()->json([
            'generated' => $createdCount,
            'run_id' => $run->id,
        ]);
    }

    private function uniquePayslipNo(string $base): string
    {
        $candidate = $base;
        $i = 1;
        while (Payslip::query()->where('payslip_no', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}
