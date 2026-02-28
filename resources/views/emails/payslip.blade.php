@php
    $p = $payslip ?? [];
    $companyName = $company?->company_name ?? 'Company';
    $period = ($p['period_month'] ?? '-') . ' (' . ($p['cutoff'] ?? '-') . ')';
    $money = fn ($v) => '₱ ' . number_format((float) $v, 2);
@endphp
<div style="font-family: Arial, Helvetica, sans-serif; color: #111; line-height: 1.4;">
    <h2 style="margin:0 0 6px 0;">{{ $companyName }} Payslip</h2>
    <div style="margin:0 0 10px 0; color:#555;">
        Pay Period: {{ $period }}
    </div>

    <div style="margin-bottom:10px;">
        <strong>Employee:</strong> {{ $p['emp_name'] ?? '-' }} ({{ $p['emp_no'] ?? ($p['employee_id'] ?? '-') }})
    </div>

    <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
        <tr>
            <td style="padding:6px 0;">Daily Rate</td>
            <td style="padding:6px 0; text-align:right;">{{ $money($p['daily_rate'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;">Paid Days</td>
            <td style="padding:6px 0; text-align:right;">{{ number_format((float) ($p['paid_days'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;">Basic Pay (cutoff)</td>
            <td style="padding:6px 0; text-align:right;">{{ $money($p['basic_pay_cutoff'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;">Allowance (cutoff)</td>
            <td style="padding:6px 0; text-align:right;">{{ $money($p['allowance_cutoff'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;">OT Pay</td>
            <td style="padding:6px 0; text-align:right;">{{ $money($p['ot_pay'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;"><strong>Gross</strong></td>
            <td style="padding:6px 0; text-align:right;"><strong>{{ $money($p['gross'] ?? 0) }}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 0;">Total Deductions</td>
            <td style="padding:6px 0; text-align:right;">{{ $money($p['deductions_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;"><strong>Net Pay</strong></td>
            <td style="padding:6px 0; text-align:right;"><strong>{{ $money($p['net_pay'] ?? 0) }}</strong></td>
        </tr>
    </table>

    <div style="margin-top:10px; color:#777; font-size:12px;">
        This is a system-generated payslip email.
    </div>
</div>
