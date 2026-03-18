@php
    $p = $payslip ?? [];
    $period = ($p['period_month'] ?? '-') . ' (' . ($p['cutoff'] ?? '-') . ')';
    $dateRange =
        (($p['period_start'] ?? null) ? \Carbon\Carbon::parse($p['period_start'])->format('m/d/Y') : '-') .
        ' to ' .
        (($p['period_end'] ?? null) ? \Carbon\Carbon::parse($p['period_end'])->format('m/d/Y') : '-');
@endphp

<div style="font-family: Arial, Helvetica, sans-serif; color: #111; line-height: 1.55; font-size: 14px;">
    <div style="margin:0 0 12px 0;">Greetings!</div>

    <div style="margin:0 0 12px 0;">
        Please find attached the payslip for the period of <strong>{{ $dateRange }}</strong>.
        This document provides a detailed breakdown of earnings, deductions, and net pay.
    </div>

    <div style="margin:0 0 12px 0;">
        Kindly review the attached payslip for your reference. For any questions or clarifications, please contact the Human Resources Department.
    </div>

    <div style="margin:0 0 12px 0;">Thank you.</div>

    <div style="margin-top:18px;">
        Best regards,<br>
        <strong>Jucinda Yap Borre</strong><br>
        Payroll Officer
    </div>
</div>
