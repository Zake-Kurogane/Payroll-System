<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\CompanySetup;
use App\Models\PayrollRun;
use App\Models\PayrollRunLoanItem;
use App\Models\PayrollRunRow;
use App\Models\PayrollRunRowOverride;
use App\Models\Payslip;
use App\Models\PayslipAdjustment;
use App\Models\PayrollCalendarSetting;
use App\Services\Attendance\AttendanceStatusRuleService;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class PayslipController extends Controller
{
    public function runs()
    {
        $stats = PayrollRunRow::query()
            ->selectRaw('payroll_run_id, COUNT(*) as headcount, SUM(net_pay) as total_net')
            ->groupBy('payroll_run_id')
            ->get()
            ->keyBy('payroll_run_id');

        $runs = PayrollRun::query()
            ->with(['createdBy'])
            ->orderByDesc('id')
            ->get()
            ->map(function (PayrollRun $run) use ($stats) {
                $rowStats = $stats->get($run->id);
                $creatorFirst = trim((string) ($run->createdBy?->first_name ?? ''));
                $creatorLast = trim((string) ($run->createdBy?->last_name ?? ''));
                $creatorFull = trim($creatorFirst . ' ' . $creatorLast);

                return [
                    'id' => $run->id,
                    'run_code' => $run->run_code,
                    'period_month' => $run->period_month,
                    'cutoff' => $run->cutoff,
                    'assignment_filter' => $run->assignment_filter,
                    'status' => $run->status,
                    'created_by_name' => $creatorFull !== '' ? $creatorFull : ($run->createdBy?->name ?? ''),
                    'created_at' => $run->created_at,
                    'locked_at' => $run->locked_at,
                    'released_at' => $run->released_at,
                    'headcount' => (int) ($rowStats->headcount ?? 0),
                    'total_net' => (float) ($rowStats->total_net ?? 0),
                ];
            });

        return response()->json($runs);
    }

    public function index(Request $request, PayrollCalculator $calculator)
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
        $payload = $this->buildPayslipPayload($run, $payslips, $calculator);

        return response()->json($payload);
    }

    public function printView(Request $request, PayrollCalculator $calculator)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'ids' => ['nullable', 'string'],
            'autoprint' => ['nullable', 'boolean'],
            'in_modal' => ['nullable', 'boolean'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);
        $query = Payslip::query()
            ->with(['employee', 'adjustments'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('id');
        if (!empty($validated['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $validated['ids']))));
            if ($ids) {
                $query->whereIn('id', $ids);
            }
        }
        $payslips = $query->get();
        $payload = $this->buildPayslipPayload($run, $payslips, $calculator);
        $company = CompanySetup::query()->first();

        return view('print.payslip_pdf', [
            'company' => $company,
            'run' => $run,
            'payslips' => $payload,
            'autoprint' => (bool) ($validated['autoprint'] ?? false),
            'inModal' => (bool) ($validated['in_modal'] ?? false),
            'payDate' => $this->resolvePayDate($run->period_month, $run->cutoff),
        ]);
    }

    public function exportPdf(Request $request, PayrollCalculator $calculator)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'ids' => ['nullable', 'string'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);
        $query = Payslip::query()
            ->with(['employee', 'adjustments'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('id');

        if (!empty($validated['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $validated['ids']))));
            if ($ids) {
                $query->whereIn('id', $ids);
            }
        }

        $payslips = $query->get();
        $payload = $this->buildPayslipPayload($run, $payslips, $calculator);
        $company = CompanySetup::query()->first();

        if (count($payload) === 0) {
            return response()->json(['message' => 'No payslips found for this run/selection.'], 404);
        }

        $html = view('print.payslip_pdf', [
            'company' => $company,
            'run' => $run,
            'payslips' => $payload,
            'autoprint' => false,
            'inModal' => false,
            'payDate' => $this->resolvePayDate($run->period_month, $run->cutoff),
        ])->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = 'payslips_' . ($run->run_code ?: $run->id) . '.pdf';
        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function export(Request $request, PayrollCalculator $calculator)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'ids' => ['nullable', 'string'],
            'format' => ['nullable', 'in:xlsx,csv'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);
        $query = Payslip::query()
            ->with(['employee', 'adjustments'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('id');
        if (!empty($validated['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $validated['ids']))));
            if ($ids) {
                $query->whereIn('id', $ids);
            }
        }
        $payslips = $query->get();
        $payload = $this->buildPayslipPayload($run, $payslips, $calculator);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'Emp ID',
            'Employee',
            'Assignment',
            'Pay Period',
            'Daily Rate',
            'Paid Days',
            'Basic Pay (cutoff)',
            'Allowance (cutoff)',
            'Attendance Deduction',
            'SSS (EE)',
            'PhilHealth (EE)',
            'Pag-IBIG (EE)',
            'Tax',
            'Cash Advance',
            'Loan Deductions',
            'Total Deductions',
            'Net Pay',
            'Release Status',
            'Delivery Status',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rowIdx = 2;
        foreach ($payload as $p) {
            $sheet->fromArray([
                $p['emp_no'] ?? $p['employee_id'],
                $p['emp_name'],
                trim(
                    ($p['assignment_type'] ?? '') .
                    ($p['area_place'] ? ' - ' . $p['area_place'] : '') .
                    ($p['external_area'] ? ' [Ext: ' . $p['external_area'] . ']' : '')
                ),
                $p['period_month'] . ' (' . ($p['cutoff'] ?? '-') . ')',
                $p['daily_rate'],
                $p['paid_days'],
                $p['basic_pay_cutoff'],
                $p['allowance_cutoff'],
                $p['attendance_deduction'],
                $p['sss_ee'],
                $p['philhealth_ee'],
                $p['pagibig_ee'],
                $p['tax'],
                $p['cash_advance'],
                $p['loan_deduction'],
                $p['deductions_total'],
                $p['net_pay'],
                $p['release_status'],
                $p['delivery_status'],
            ], null, 'A' . $rowIdx);
            $rowIdx++;
        }

        $format = $validated['format'] ?? 'xlsx';
        $filename = 'payslips_' . $run->id . '.' . $format;
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setSheetIndex(0);
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'text/csv',
            ]);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function sendEmail(Request $request, PayrollCalculator $calculator)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'ids' => ['nullable', 'string'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);
        if (!in_array($run->status, ['Locked', 'Released'], true)) {
            return response()->json(['message' => 'Run must be locked or released before sending emails.'], 409);
        }

        $query = Payslip::query()
            ->with(['employee', 'adjustments'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('id');
        if (!empty($validated['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $validated['ids']))));
            if ($ids) {
                $query->whereIn('id', $ids);
            }
        }
        $payslips = $query->get();
        $payload = $this->buildPayslipPayload($run, $payslips, $calculator);
        $company = CompanySetup::query()->first();

        if (count($payload) === 0) {
            return response()->json(['message' => 'No payslips found for this run/selection.'], 404);
        }

        $sent = 0;
        $skipped = [];
        $failed = [];
        foreach ($payload as $p) {
            $email = $p['employee_email'] ?? null;
            if (!$email) {
                $skipped[] = $p['emp_no'] ?? $p['employee_id'];
                continue;
            }
            try {
                Mail::to($email)->send(new \App\Mail\PayslipMail($p, $company, $run));
                $sent++;
                Payslip::query()->where('id', $p['id'])->update([
                    'delivery_status' => 'Sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $failed[] = [
                    'emp_no' => $p['emp_no'] ?? $p['employee_id'],
                    'error' => $e->getMessage(),
                ];
                Payslip::query()->where('id', $p['id'])->update([
                    'delivery_status' => 'Failed',
                ]);
            }
        }

        return response()->json([
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    public function generate(PayrollRun $run)
    {
        if (!in_array($run->status, ['Locked', 'Released'], true)) {
            return response()->json(['message' => 'Run must be locked or released before generating payslips.'], 409);
        }

        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        $empMap = Employee::query()
            ->whereIn('id', $rows->pluck('employee_id')->filter()->values())
            ->get()
            ->keyBy('id');

        /** @var \Illuminate\Support\Collection<int, Payslip> $existing */
        $existing = Payslip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('employee_id', $rows->pluck('employee_id')->filter()->values())
            ->get()
            ->keyBy('employee_id');

        // Pre-fetch all existing payslip numbers for this run to avoid N+1 `exists()` queries.
        // We'll also reserve numbers we generate in-memory during the transaction.
        $usedPayslipNos = array_fill_keys(
            $existing->pluck('payslip_no')->filter()->values()->all(),
            true
        );

        $overrides = PayrollRunRowOverride::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $createdCount = 0;
        $payslipIds = [];
        $adjRows = [];

        DB::transaction(function () use ($rows, $overrides, $run, $empMap, $existing, &$usedPayslipNos, &$createdCount, &$payslipIds, &$adjRows) {
            foreach ($rows as $row) {
                $emp = $empMap->get($row->employee_id);
                $baseNo = 'PS-' . ($run->run_code ?: ('RUN-' . $run->id)) . '-' . ($emp?->emp_no ?: $row->employee_id);
                /** @var Payslip|null $payslip */
                $payslip = $existing->get($row->employee_id);

                if (!$payslip) {
                    $payslipNo = $this->uniquePayslipNo($baseNo, $usedPayslipNos);
                    $payslip = Payslip::create([
                        'payroll_run_id' => $run->id,
                        'employee_id' => $row->employee_id,
                        'payslip_no' => $payslipNo,
                        'generated_at' => now(),
                        'release_status' => 'Draft',
                        'delivery_status' => 'Not Sent',
                    ]);
                    $createdCount++;
                    $existing->put($row->employee_id, $payslip);
                } elseif (!$payslip->generated_at) {
                    $payslip->generated_at = now();
                    $payslip->save();
                }

                $payslipIds[] = $payslip->id;

                $override = $overrides->get($row->employee_id);
                $adjustments = $override?->adjustments ?? [];
                if (is_string($adjustments)) {
                    $adjustments = json_decode($adjustments, true) ?: [];
                }

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
            }

            if ($payslipIds) {
                PayslipAdjustment::query()->whereIn('payslip_id', $payslipIds)->delete();
            }
            if ($adjRows) {
                PayslipAdjustment::query()->insert($adjRows);
            }
        });

        return response()->json([
            'generated' => $createdCount,
            'run_id' => $run->id,
        ]);
    }

    public function releaseSelected(Request $request)
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'payslip_ids' => ['required', 'array', 'min:1'],
            'payslip_ids.*' => ['required', 'integer', 'exists:payslips,id'],
        ]);

        $run = PayrollRun::query()->findOrFail($validated['run_id']);
        if (!in_array($run->status, ['Locked', 'Released'], true)) {
            return response()->json(['message' => 'Run must be locked or released before releasing payslips.'], 409);
        }

        // If run is still locked, release it through payroll release flow first
        // so deductions/balances are posted (CA, loans, charges).
        $this->ensureRunReleasedWithPosting($run);

        $released = Payslip::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('id', $validated['payslip_ids'])
            ->update(['release_status' => 'Released']);

        return response()->json([
            'released' => $released,
        ]);
    }

    public function releaseAll(PayrollRun $run)
    {
        if (!in_array($run->status, ['Locked', 'Released'], true)) {
            return response()->json(['message' => 'Run must be locked or released before releasing payslips.'], 409);
        }

        // Ensure payroll posting is performed when transitioning from Locked -> Released.
        $this->ensureRunReleasedWithPosting($run);

        Payslip::query()
            ->where('payroll_run_id', $run->id)
            ->update(['release_status' => 'Released']);

        $run->refresh();

        return response()->json([
            'run_id' => $run->id,
            'status' => $run->status,
            'released_at' => $run->released_at,
        ]);
    }

    private function ensureRunReleasedWithPosting(PayrollRun $run): void
    {
        if ($run->status !== 'Locked') {
            return;
        }

        // Reuse PayrollRunController::release so posting logic stays in one place.
        app(PayrollRunController::class)->release($run);
        $run->refresh();
    }

    private function uniquePayslipNo(string $base, array &$used): string
    {
        $candidate = $base;
        $i = 1;
        while (isset($used[$candidate])) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        $used[$candidate] = true;
        return $candidate;
    }

    private function buildPayslipPayload(PayrollRun $run, $payslips, PayrollCalculator $calculator): array
    {
        [$start, $end] = $calculator->cutoffRangePublic($run->period_month, $run->cutoff);
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        $workMonSat = (bool) ($calendar->work_mon_sat ?? true);

        $rows = PayrollRunRow::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->keyBy('employee_id');

        $loanItems = PayrollRunLoanItem::query()
            ->with('loan')
            ->where('payroll_run_id', $run->id)
            ->get()
            ->groupBy('employee_id');

        $empIds = $payslips->pluck('employee_id')->filter()->values();
        $attendance = AttendanceRecord::query()
            ->whereIn('employee_id', $empIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        return $payslips->map(function (Payslip $p) use ($rows, $loanItems, $run, $attendance, $start, $end, $workMonSat) {
            $emp = $p->employee;
            $row = $rows->get($p->employee_id);
            $name = $emp
                ? trim($emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . $emp->middle_name : ''))
                : '';

            $dailyRate = $emp && $emp->basic_pay ? ((float) $emp->basic_pay / 26) : 0.0;
            $att = [
                'paid_days' => 0.0,
                'present_days' => 0.0,
                'absent_days' => 0.0,
                'leave_days' => 0.0,
                'minutes_late' => 0,
                'minutes_undertime' => 0,
            ];
            if ($emp) {
                $records = $attendance->get($emp->id, collect());
                $att = $this->attendanceBreakdownInRange($records, $start, $end, $workMonSat, (string) ($emp->assignment_type ?? ''));
            }

            return [
                'id' => $p->id,
                'payslip_no' => $p->payslip_no,
                'payroll_run_id' => $run->id,
                'run_code' => $run->run_code,
                'period_month' => $run->period_month,
                'cutoff' => $run->cutoff,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'release_status' => $p->release_status,
                'delivery_status' => $p->delivery_status,
                'generated_at' => $p->generated_at,
                'employee_id' => $p->employee_id,
                'employee_email' => $emp?->email,
                'emp_no' => $emp?->emp_no,
                'emp_name' => $name,
                'department' => $emp?->department,
                'position' => $emp?->position,
                'employment_type' => $emp?->employment_type,
                'assignment_type' => $emp?->assignment_type,
                'area_place' => $emp?->area_place,
                'external_area' => $emp?->external_area,
                'pay_method' => $row?->pay_method ?? ($emp?->payout_method ?: 'CASH'),
                'bank_name' => $emp?->bank_name,
                'bank_account_number' => $emp?->bank_account_number,
                'daily_rate' => (float) $dailyRate,
                'paid_days' => (float) ($att['paid_days'] ?? 0),
                'present_days' => (float) ($att['present_days'] ?? 0),
                'absent_days' => (float) ($att['absent_days'] ?? 0),
                'leave_days' => (float) ($att['leave_days'] ?? 0),
                'minutes_late' => (int) ($att['minutes_late'] ?? 0),
                'minutes_undertime' => (int) ($att['minutes_undertime'] ?? 0),
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
                'loan_deduction' => (float) ($row?->loan_deduction ?? 0),
                'loan_items' => collect($loanItems->get($p->employee_id, collect()))
                    ->map(function (PayrollRunLoanItem $i) {
                        return [
                            'loan_type' => $i->loan?->loan_type ?? 'Loan',
                            'deducted_amount' => (float) ($i->deducted_amount ?? 0),
                            'status' => (string) ($i->status ?? 'scheduled'),
                        ];
                    })
                    ->values(),
                'loan_details' => EmployeeLoan::query()
                    ->where('employee_id', $p->employee_id)
                    ->orderByDesc('id')
                    ->get()
                    ->map(function (EmployeeLoan $loan) {
                        $perCutoff = $loan->per_cutoff_amount;
                        if (!$perCutoff) {
                            $perCutoff = $loan->deduction_frequency === 'every_cutoff'
                                ? ($loan->monthly_amortization / 2)
                                : $loan->monthly_amortization;
                        }
                        return [
                            'loan_no' => $loan->loan_no,
                            'loan_type' => $loan->loan_type,
                            'per_cutoff' => (float) ($perCutoff ?? 0),
                            'status' => $loan->status,
                        ];
                    })->values(),
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
        })->values()->all();
    }

    private function resolvePayDate(string $periodMonth, string $cutoff): string
    {
        if (!$periodMonth) return '-';
        [$y, $m] = array_map('intval', explode('-', $periodMonth));
        if (!$y || !$m) return '-';
        $calendar = PayrollCalendarSetting::query()->first() ?? PayrollCalendarSetting::create([]);
        $payOnPrev = (bool) ($calendar->pay_on_prev_workday_if_sunday ?? false);
        // Business rule:
        // 1st cutoff = 26-10 (payday 15, next month)
        // 2nd cutoff = 11-25 (payday 30/31 or EOM, same month)
        $isFirst = $cutoff === '26-10';
        $payDay = $isFirst ? ($calendar->pay_date_a ?? 15) : ($calendar->pay_date_b ?? 15);
        // 26-10 cutoff period ends in the next month, so pay date is also next month.
        if ($isFirst) {
            $m++;
            if ($m > 12) { $m = 1; $y++; }
        }
        if (strtoupper((string) $payDay) === 'EOM') {
            $date = Carbon::create($y, $m, 1)->endOfMonth();
        } else {
            $date = Carbon::create($y, $m, (int) $payDay);
        }
        if ($payOnPrev && $date->isSunday()) {
            $date = $date->subDay();
        }
        return $date->format('m-d-Y');
    }

    private function attendanceBreakdownInRange($records, Carbon $start, Carbon $end, bool $workMonSat, string $assignmentType = ''): array
    {
        $map = collect($records)->keyBy(fn ($r) => $r->date?->format('Y-m-d'));
        $workdayRows = [];

        $d = $start->copy();
        while ($d->lte($end)) {
            $dow = (int) $d->dayOfWeekIso; // 1=Mon ... 7=Sun
            $isField = strcasecmp(trim($assignmentType), 'Field') === 0;
            $isWorkday = $isField ? true : ($workMonSat ? $dow <= 6 : $dow <= 5);
            if ($isWorkday) {
                $key = $d->format('Y-m-d');
                $rec = $map->get($key);
                if ($rec) {
                    $workdayRows[] = [
                        'status' => (string) ($rec->status ?? ''),
                        'minutes_late' => (int) ($rec->minutes_late ?? 0),
                        'minutes_undertime' => (int) ($rec->minutes_undertime ?? 0),
                        'ot_hours' => (float) ($rec->ot_hours ?? 0),
                    ];
                }
            }
            $d->addDay();
        }

        $ruleService = new AttendanceStatusRuleService();
        $totals = $ruleService->summarize($workdayRows);

        return [
            'paid_days' => (float) ($totals['paid_days'] ?? 0),
            'present_days' => (float) ($totals['present_days'] ?? 0),
            'absent_days' => (float) ($totals['absent_days'] ?? 0),
            'leave_days' => (float) ($totals['leave_days'] ?? 0),
            'minutes_late' => (int) ($totals['minutes_late'] ?? 0),
            'minutes_undertime' => (int) ($totals['minutes_undertime'] ?? 0),
        ];
    }
}
