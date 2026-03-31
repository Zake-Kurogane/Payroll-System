@php
    $companyName = $company?->company_name ?? 'Company';
    $companySubParts = collect([
        $company?->company_address,
        $company?->company_contact,
        $company?->company_email,
    ])->filter()->values();
    $companySub = $companySubParts->isEmpty()
        ? '-'
        : $companySubParts->map(fn ($v) => e((string) $v))->implode(' &bull; ');

    // Dompdf may not render the Peso sign reliably depending on available fonts.
    $money = fn ($v) => 'PHP ' . number_format((float) $v, 2);
    $list = is_array($payslips ?? null) ? $payslips : [];
@endphp
<!doctype html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Payslip</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 9px;
        }

        .sheet {
            width: 100mm;
            margin: 0 auto;
        }

        .card {
            border: 1px solid #c88ea5;
            border-radius: 8px;
            padding: 3mm;
        }

        .head-table {
            width: 100%;
            border-collapse: collapse;
        }
        .head-left { vertical-align: top; }
        .head-center { vertical-align: top; text-align: center; }
        .head-right { vertical-align: top; text-align: right; }
        .company {
            font-weight: 900;
            font-size: 11px;
            line-height: 1.15;
        }
        .company-sub {
            font-size: 8px;
            color: #374151;
            margin-top: 2px;
        }
        .title {
            font-weight: 900;
            letter-spacing: 1px;
            color: #64122a;
            font-size: 12px;
            padding-top: 2px;
        }
        .meta {
            font-size: 8px;
            line-height: 1.25;
        }
        .meta span { color: #6b7280; }

        .rule {
            border-top: 2px solid #9c1d3c;
            margin: 8px 0;
        }

        .two-col {
            width: 100%;
            border-collapse: collapse;
        }
        .info-wrap {
            border-top: 1px solid #d6b5c2;
            border-bottom: 1px solid #d6b5c2;
            padding: 4px 0;
        }
        .block-title {
            font-weight: 900;
            letter-spacing: 1px;
            color: #64122a;
            font-size: 8px;
            padding: 2px 0 6px;
        }
        .kv {
            width: 100%;
            border-collapse: collapse;
        }
        .kv td { padding: 1px 0; }
        .k { color: #6b7280; width: 44%; }
        .v { text-align: right; font-weight: 800; width: 56%; }

        .section {
            margin-top: 8px;
        }
        .section-title {
            background: #9c1d3c;
            color: #fff;
            font-weight: 900;
            letter-spacing: 1px;
            font-size: 8px;
            padding: 3px 6px;
        }

        .tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        .tbl th, .tbl td {
            border: 1px solid #c88ea5;
            padding: 3px 6px;
        }
        .tbl th {
            background: #f7e8ed;
            font-weight: 900;
        }
        .num { text-align: right; white-space: nowrap; }
        .total-row td {
            background: #f7e8ed;
            font-weight: 900;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .sum-left {
            border: 1px solid #c88ea5;
            padding: 8px 10px;
            vertical-align: top;
            width: 60%;
        }
        .sum-right {
            border: 1px solid #9c1d3c;
            background: #f7e8ed;
            padding: 10px;
            vertical-align: middle;
            text-align: center;
            width: 40%;
        }
        .sum-line { width: 100%; border-collapse: collapse; }
        .sum-line td { padding: 2px 0; }
        .sum-k { font-weight: 800; }
        .sum-v { text-align: right; font-weight: 900; }
        .net-label { font-weight: 900; letter-spacing: 2px; color: #64122a; font-size: 9px; }
        .net-val { font-weight: 900; font-size: 16px; margin-top: 4px; }
    </style>
    @if(!empty($autoprint))
        <script>
            const canClose = !!window.opener;
            const inModal = {{ !empty($inModal) ? 'true' : 'false' }};
            window.addEventListener("load", () => {
                setTimeout(() => window.print(), 300);
            });
            window.addEventListener("afterprint", () => {
                if (inModal) {
                    try {
                        window.parent.postMessage({ type: "payslips:afterprint" }, window.location.origin);
                    } catch {
                        // ignore
                    }
                    return;
                }
                if (canClose) window.close();
            });
            setTimeout(() => {
                if (inModal) {
                    try {
                        window.parent.postMessage({ type: "payslips:afterprint" }, window.location.origin);
                    } catch {
                        // ignore
                    }
                    return;
                }
                if (canClose) window.close();
            }, 20000);
        </script>
    @endif
</head>
<body>
@foreach($list as $p)
    @php
        $adj = collect($p['adjustments'] ?? [])->all();
        $earnAdj = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'earning');
        $dedAdj = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'deduction');

        $branchName = $p['external_area'] ?? $p['area_place'] ?? null;
        $branchName = is_string($branchName) && trim($branchName) !== '' ? trim($branchName) : '-';
        $pm = strtoupper((string) ($p['pay_method'] ?? ''));
        $payMethod = $pm === 'BANK' ? 'Bank' : 'Cash';

        $loanItems = collect($p['loan_items'] ?? [])->filter(fn ($i) => (float) ($i['deducted_amount'] ?? 0) > 0);
        $sumType = function (string $needle) use ($loanItems) {
            return (float) $loanItems
                ->filter(fn ($i) => str_contains(strtolower((string) ($i['loan_type'] ?? '')), $needle))
                ->sum(fn ($i) => (float) ($i['deducted_amount'] ?? 0));
        };
        $sssHousing = $sumType('sss housing');
        $hdmfCalamity = $sumType('calamity');
        $pagibigHousing = (float) $loanItems
            ->filter(fn ($i) => str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'housing')
                && (str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'pagibig') || str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'hdmf')))
            ->sum(fn ($i) => (float) ($i['deducted_amount'] ?? 0));
        $sssLoan = max(0.0, $sumType('sss') - $sssHousing);
        $hdmfLoan = max(0.0, $sumType('hdmf') - $pagibigHousing - $hdmfCalamity);
        $advances = (float) ($p['cash_advance'] ?? 0);
        $charges = (float) ($p['charges_deduction'] ?? 0);
        $shortages = 0.0;
    @endphp

    <div class="sheet" style="{{ $loop->last ? '' : 'page-break-after: always;' }}">
        <div class="card">
            <table class="head-table">
                <tr>
                    <td class="head-left" style="width:44%;">
                        <div class="company">{{ $companyName }}</div>
                        <div class="company-sub">{!! $companySub !!}</div>
                    </td>
                    <td class="head-center" style="width:22%;">
                        <div class="title">PAYSLIP</div>
                    </td>
                    <td class="head-right" style="width:34%;">
                        <div class="meta"><span>No</span> <strong>{{ $p['payslip_no'] ?? '-' }}</strong></div>
                        <div class="meta"><span>Date</span> <strong>{{ $p['generated_at'] ? \Carbon\Carbon::parse($p['generated_at'])->format('m-d-Y') : '-' }}</strong></div>
                    </td>
                </tr>
            </table>

            <div class="rule"></div>

            <div class="info-wrap">
                <table class="two-col">
                    <tr>
                        <td style="width:50%; padding-right:10px; vertical-align:top;">
                            <div class="block-title">EMPLOYEE</div>
                            <table class="kv">
                                <tr><td class="k">Name</td><td class="v">{{ $p['emp_name'] ?? '-' }}</td></tr>
                                <tr><td class="k">Employee ID</td><td class="v">{{ $p['emp_no'] ?? ($p['employee_id'] ?? '-') }}</td></tr>
                                <tr><td class="k">Department</td><td class="v">{{ $p['department'] ?? '-' }}</td></tr>
                                <tr><td class="k">Position</td><td class="v">{{ $p['position'] ?? '-' }}</td></tr>
                                <tr><td class="k">Assignment</td><td class="v">{{ $branchName }}</td></tr>
                            </table>
                        </td>
                        <td style="width:50%; padding-left:10px; vertical-align:top;">
                            <div class="block-title">PAY PERIOD</div>
                            <table class="kv">
                                <tr><td class="k">Payroll Month</td><td class="v">{{ $p['period_month'] ?? '-' }}</td></tr>
                                <tr><td class="k">Cutoff</td><td class="v">{{ $p['cutoff'] ?? '-' }}</td></tr>
                                <tr><td class="k">Period</td><td class="v">
                                    {{ ($p['period_start'] ?? null) ? \Carbon\Carbon::parse($p['period_start'])->format('m/d/Y') : '-' }}
                                    to
                                    {{ ($p['period_end'] ?? null) ? \Carbon\Carbon::parse($p['period_end'])->format('m/d/Y') : '-' }}
                                </td></tr>
                                <tr><td class="k">Pay Date</td><td class="v">{{ $payDate ?? '-' }}</td></tr>
                                <tr><td class="k">Pay Method</td><td class="v">{{ $payMethod }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <div class="section-title">EARNINGS</div>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num" style="width:22%;">Rate</th>
                            <th class="num" style="width:18%;">Hrs/Days</th>
                            <th class="num" style="width:22%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Daily Rate</td>
                            <td class="num">{{ $money($p['daily_rate'] ?? 0) }}</td>
                            <td class="num">{{ number_format((float) ($p['paid_days'] ?? 0), 2) }}</td>
                            <td class="num">{{ $money($p['basic_pay_cutoff'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td>Allowance (cutoff)</td>
                            <td class="num">-</td>
                            <td class="num">-</td>
                            <td class="num">{{ $money($p['allowance_cutoff'] ?? 0) }}</td>
                        </tr>
                        @foreach($earnAdj as $a)
                            <tr>
                                <td>{{ $a['name'] ?? 'Adjustment' }}</td>
                                <td class="num">-</td>
                                <td class="num">-</td>
                                <td class="num">{{ $money($a['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="3">TOTAL GROSS EARNINGS</td>
                            <td class="num">{{ $money($p['gross'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <div class="section-title">DEDUCTIONS</div>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num" style="width:18%;">Rate</th>
                            <th class="num" style="width:22%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Attendance Deductions (Late/UT/Absent)</td><td class="num">-</td><td class="num">{{ $money($p['attendance_deduction'] ?? 0) }}</td></tr>
                        <tr><td>SSS (EE)</td><td class="num">-</td><td class="num">{{ $money($p['sss_ee'] ?? 0) }}</td></tr>
                        <tr><td>PhilHealth (EE)</td><td class="num">-</td><td class="num">{{ $money($p['philhealth_ee'] ?? 0) }}</td></tr>
                        <tr><td>Withholding Tax</td><td class="num">-</td><td class="num">{{ $money($p['tax'] ?? 0) }}</td></tr>
                        <tr><td>HDMF</td><td class="num">-</td><td class="num">{{ $money($p['pagibig_ee'] ?? 0) }}</td></tr>
                        <tr><td>SSS Loan</td><td class="num">-</td><td class="num">{{ $money($sssLoan) }}</td></tr>
                        <tr><td>HDMF Loan</td><td class="num">-</td><td class="num">{{ $money($hdmfLoan) }}</td></tr>
                        <tr><td>PAGIBIG Housing Loan</td><td class="num">-</td><td class="num">{{ $money($pagibigHousing) }}</td></tr>
                        <tr><td>SSS Housing Loan</td><td class="num">-</td><td class="num">{{ $money($sssHousing) }}</td></tr>
                        <tr><td>HDMF Calamity Loan</td><td class="num">-</td><td class="num">{{ $money($hdmfCalamity) }}</td></tr>
                        <tr><td>Advances</td><td class="num">-</td><td class="num">{{ $money($advances) }}</td></tr>
                        <tr><td>Shortages</td><td class="num">-</td><td class="num">{{ $money($shortages) }}</td></tr>
                        <tr><td>Charges</td><td class="num">-</td><td class="num">{{ $money($charges) }}</td></tr>
                        @foreach($dedAdj as $a)
                            <tr><td>{{ $a['name'] ?? 'Adjustment' }}</td><td class="num">-</td><td class="num">{{ $money($a['amount'] ?? 0) }}</td></tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2">Total Deds</td>
                            <td class="num">{{ $money($p['deductions_total'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table class="summary">
                <tr>
                    <td class="sum-left">
                        <table class="sum-line">
                            <tr><td class="sum-k">Gross Pay</td><td class="sum-v">{{ $money($p['gross'] ?? 0) }}</td></tr>
                            <tr><td class="sum-k">Total Deductions</td><td class="sum-v">{{ $money($p['deductions_total'] ?? 0) }}</td></tr>
                        </table>
                    </td>
                    <td style="width:10px;"></td>
                    <td class="sum-right">
                        <div class="net-label">NET PAY</div>
                        <div class="net-val">{{ $money($p['net_pay'] ?? 0) }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endforeach
</body>
</html>
