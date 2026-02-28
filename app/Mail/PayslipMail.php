<?php

namespace App\Mail;

use App\Models\CompanySetup;
use App\Models\PayrollRun;
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
        $subject = ($this->company?->company_name ?: 'Company') . ' Payslip';
        return $this->subject($subject)
            ->view('emails.payslip');
    }
}
