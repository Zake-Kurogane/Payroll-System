<?php

namespace App\Mail;

use App\Models\CompanySetup;
use App\Models\PayrollRun;
use App\Models\PayrollCalendarSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $payslip;
    public ?CompanySetup $company;
    public ?PayrollRun $run;

    public function __construct(array $payslip, ?CompanySetup $company, ?PayrollRun $run)
    {
        $this->payslip = $payslip;
        $this->company = $company;
        $this->run = $run;
    }

    public function build()
    {
        $period = (string) ($this->payslip['period_month'] ?? ($this->run?->period_month ?? '-'));
        $cutoff = (string) ($this->payslip['cutoff'] ?? ($this->run?->cutoff ?? '-'));
        $subject = 'Payslip for ' . trim($period . ' (' . $cutoff . ')');

        $fromAddress = (string) (config('mail.from.address') ?? '');
        $fromName = "AURA FORTUNE G5 TRADERS CORPORATION \u{2014}PAYSLIP.";

        $attachmentName = 'Payslip_' . ($this->payslip['emp_no'] ?? ($this->payslip['employee_id'] ?? 'EMP')) . '_' . $period . '_' . $cutoff . '.pdf';
        $pdf = $this->renderPayslipPdf();

        $mail = $this->subject($subject)
            ->view('emails.payslip')
            ->attachData($pdf, $attachmentName, ['mime' => 'application/pdf']);

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName);
        } else {
            $mail->from('no-reply@example.com', $fromName);
        }

        return $mail;
    }

    private function renderPayslipPdf(): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('PDF generator not installed. Please install dompdf/dompdf via Composer.');
        }

        $periodMonth = (string) ($this->payslip['period_month'] ?? ($this->run?->period_month ?? ''));
        $cutoff = (string) ($this->payslip['cutoff'] ?? ($this->run?->cutoff ?? ''));
        $payDate = $this->resolvePayDate($periodMonth, $cutoff);

        $html = view('print.payslip_pdf', [
            'company' => $this->company,
            'run' => $this->run,
            'payslips' => [$this->payslip],
            'payDate' => $payDate,
            'autoprint' => false,
            'inModal' => false,
        ])->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }

    private function resolvePayDate(string $periodMonth, string $cutoff): string
    {
        if (!$periodMonth) return '-';
        $parts = explode('-', $periodMonth);
        if (count($parts) !== 2) return '-';
        [$y, $m] = array_map('intval', $parts);
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
}
