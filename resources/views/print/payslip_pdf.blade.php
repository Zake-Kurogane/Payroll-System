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

    $peso = "\u{20B1} ";
    $money = fn ($v) => $peso . number_format((float) $v, 2);
    $list = is_array($payslips ?? null) ? $payslips : [];
    $pages = array_chunk($list, 4);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Payslip</title>
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 9px;
            line-height: 1.15;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #fff;
        }

        .print-page {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-rows: repeat(2, calc((279mm - 4mm) / 2));
            gap: 4mm;
            height: 279mm;
            page-break-after: always;
            break-after: page;
            page-break-inside: avoid;
            break-inside: avoid;
            align-content: stretch;
        }
        .print-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .print-card {
            height: calc((279mm - 4mm) / 2);
            break-inside: avoid;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .paystub {
            background: #fff;
            border: 1px solid #c88ea5;
            border-radius: 6px;
            padding: 7px;
            height: calc(100% - 4px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .psHeadTable { width: 100%; border-collapse: collapse; }
        .psHeadLeft  { vertical-align: top; width: 40%; }
        .psHeadCenter { vertical-align: top; text-align: center; width: 20%; }
        .psHeadRight { vertical-align: top; text-align: right; width: 40%; }
        .psCompany    { font-weight: 800; font-size: 9px; line-height: 1.15; }
        .psCompanySub { font-size: 7px; color: #4b5563; margin-top: 1px; }
        .psTitle      { font-weight: 800; font-size: 12px; letter-spacing: 1px; color: #64122a; }
        .psMeta       { font-size: 7px; line-height: 1.3; }
        .psMeta span  { color: #6b7280; }
        .psRule       { border-top: 1px solid #9c1d3c; margin: 5px 0; }

        .psInfoTable { width: 100%; border-collapse: collapse; border-top: 1px solid #d6b5c2; border-bottom: 1px solid #d6b5c2; }
        .psInfoCol   { vertical-align: top; width: 50%; padding: 4px 4px 4px 0; }
        .psInfoCol + .psInfoCol { padding-left: 4px; padding-right: 0; border-left: 1px solid #d6b5c2; }
        .psInfoTitle { font-weight: 800; font-size: 7px; color: #64122a; letter-spacing: 0.5px; margin-bottom: 2px; }
        .psInfoRows  { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .psInfoRows td { padding: 1px 0; font-size: 7px; vertical-align: top; }
        .psInfoRows .lbl { color: #6b7280; width: 44%; }
        .psInfoRows .val {
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .psSectionTitle {
            margin-top: 4px;
            background: #9c1d3c;
            color: #fff;
            font-size: 7px;
            letter-spacing: 0.5px;
            padding: 2px 4px;
            font-weight: 700;
        }

        .psTable { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7px; margin-top: 2px; }
        .psTable th, .psTable td { border: 1px solid #c88ea5; padding: 3px 5px; vertical-align: top; }
        .psTable th { background: #f7e8ed; font-weight: 700; }
        .psTable .num { text-align: right; white-space: nowrap; }
        .psTable th:first-child,
        .psTable td:first-child {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .psTotalRow td { font-weight: 800; background: #f7e8ed; }
        .psTable--earnings { margin-bottom: 4px; }
        .psDedSection {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            padding-bottom: 3px;
        }
        .psTable--deductions {
            margin-bottom: 0;
            height: 100%;
        }

        .psSumTable  { width: 100%; border-collapse: collapse; margin-top: auto; }
        .psSumBox    { border: 1px solid #c88ea5; padding: 4px 6px; vertical-align: middle; }
        .psSumLines  { width: 100%; border-collapse: collapse; }
        .psSumLines td { padding: 1px 0; font-size: 7px; font-weight: 700; }
        .psSumLines .sv { text-align: right; }
        .psSumNet    { border: 1px solid #9c1d3c; background: #f7e8ed; text-align: center; vertical-align: middle; padding: 4px; width: 37%; }
        .psSumLabel  { font-size: 7px; letter-spacing: 1px; color: #64122a; font-weight: 700; }
        .psSumValue  { font-size: 12px; font-weight: 800; margin-top: 2px; }

        .psSignTable { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .psSignTable td { width: 50%; vertical-align: top; padding: 0 4px 0 0; }
        .psSignTable td + td { padding: 0 0 0 4px; }
        .psSignLine  { border-top: 1px solid #6b7280; margin-top: 12px; margin-bottom: 2px; }
        .psSignLbl   { font-size: 6px; color: #6b7280; }
    </style>
    @if(!empty($autoprint))
        <script>
            const canClose = !!window.opener;
            let didPrint = false;
            const tryPrint = () => {
                if (didPrint) return;
                didPrint = true;
                try {
                    window.focus();
                    window.print();
                } catch {}
            };
            window.addEventListener("load", () => {
                setTimeout(tryPrint, 200);
            });
            window.addEventListener("afterprint", () => {
                if (canClose) window.close();
            });
            setTimeout(() => {
                if (canClose) window.close();
            }, 20000);
        </script>
    @endif
</head>
<body>
@foreach($pages as $page)
    <div class="print-page">
        @foreach($page as $p)
            @php
                $adj          = collect($p['adjustments'] ?? [])->all();
                $earnAdj      = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'earning');
                $dedAdj       = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'deduction');

                $branchName   = $p['external_area'] ?? $p['area_place'] ?? null;
                $branchName   = is_string($branchName) && trim($branchName) !== '' ? trim($branchName) : '-';
                $pm           = strtoupper((string) ($p['pay_method'] ?? ''));
                $payMethod    = $pm === 'BANK' ? 'Bank' : 'Cash';

                $loanItems    = collect($p['loan_items'] ?? [])->filter(fn ($i) => (float) ($i['deducted_amount'] ?? 0) > 0);
                $sumType      = function (string $needle) use ($loanItems) {
                    return (float) $loanItems
                        ->filter(fn ($i) => str_contains(strtolower((string) ($i['loan_type'] ?? '')), $needle))
                        ->sum(fn ($i) => (float) ($i['deducted_amount'] ?? 0));
                };
                $sssHousing    = $sumType('sss housing');
                $hdmfCalamity  = $sumType('calamity');
                $pagibigHousing = (float) $loanItems
                    ->filter(fn ($i) => str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'housing')
                        && (str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'pagibig') || str_contains(strtolower((string) ($i['loan_type'] ?? '')), 'hdmf')))
                    ->sum(fn ($i) => (float) ($i['deducted_amount'] ?? 0));
                $sssLoan       = max(0.0, $sumType('sss') - $sssHousing);
                $hdmfLoan      = max(0.0, $sumType('hdmf') - $pagibigHousing - $hdmfCalamity);
                $advances      = (float) ($p['cash_advance'] ?? 0);
                $charges       = (float) ($p['charges_deduction'] ?? 0);
                $shortages     = 0.0;
            @endphp

            <div class="print-card">
                <div class="paystub">
                    <table class="psHeadTable">
                        <tr>
                            <td class="psHeadLeft">
                                <div class="psCompany">{{ $companyName }}</div>
                                <div class="psCompanySub">{!! $companySub !!}</div>
                            </td>
                            <td class="psHeadCenter">
                                <div class="psTitle">PAYSLIP</div>
                            </td>
                            <td class="psHeadRight">
                                <div class="psMeta"><span>No</span> &nbsp; <strong>{{ $p['payslip_no'] ?? '-' }}</strong></div>
                                <div class="psMeta"><span>Date</span> &nbsp; <strong>{{ $p['generated_at'] ? \Carbon\Carbon::parse($p['generated_at'])->format('m-d-Y') : '-' }}</strong></div>
                            </td>
                        </tr>
                    </table>
                    <div class="psRule"></div>

                    <table class="psInfoTable">
                        <tr>
                            <td class="psInfoCol">
                                <div class="psInfoTitle">EMPLOYEE</div>
                                <table class="psInfoRows">
                                    <tr><td class="lbl">Name</td><td class="val">{{ $p['emp_name'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Employee ID</td><td class="val">{{ $p['emp_no'] ?? ($p['employee_id'] ?? '-') }}</td></tr>
                                    <tr><td class="lbl">Department</td><td class="val">{{ $p['department'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Position</td><td class="val">{{ $p['position'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Type</td><td class="val">{{ $p['employment_type'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Assignment</td><td class="val">{{ $branchName }}</td></tr>
                                </table>
                            </td>
                            <td class="psInfoCol">
                                <div class="psInfoTitle">PAY PERIOD</div>
                                <table class="psInfoRows">
                                    <tr><td class="lbl">Payroll Month</td><td class="val">{{ $p['period_month'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Cutoff</td><td class="val">{{ $p['cutoff'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Period</td><td class="val">
                                        {{ ($p['period_start'] ?? null) ? \Carbon\Carbon::parse($p['period_start'])->format('m/d/Y') : '-' }}
                                        to
                                        {{ ($p['period_end'] ?? null) ? \Carbon\Carbon::parse($p['period_end'])->format('m/d/Y') : '-' }}
                                    </td></tr>
                                    <tr><td class="lbl">Pay Date</td><td class="val">{{ $payDate ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Pay Method</td><td class="val">{{ $payMethod }}</td></tr>
                                    @if($pm === 'BANK')
                                    <tr><td class="lbl">Bank</td><td class="val">{{ $p['bank_name'] ?? '-' }}</td></tr>
                                    <tr><td class="lbl">Account</td><td class="val">{{ $p['bank_account_number'] ?? '-' }}</td></tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div class="psSectionTitle">EARNINGS</div>
                    <table class="psTable psTable--earnings">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="num" style="width:20%;">Rate</th>
                                <th class="num" style="width:16%;">Hrs/Days</th>
                                <th class="num" style="width:20%;">Current</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Daily Rate</td>
                                <td class="num">{!! $money($p['daily_rate'] ?? 0) !!}</td>
                                <td class="num">{{ number_format((float) ($p['paid_days'] ?? 0), 2) }}</td>
                                <td class="num">{!! $money($p['basic_pay_cutoff'] ?? 0) !!}</td>
                            </tr>
                            <tr>
                                <td>Allowance (cutoff portion)</td>
                                <td class="num">-</td>
                                <td class="num">-</td>
                                <td class="num">{!! $money($p['allowance_cutoff'] ?? 0) !!}</td>
                            </tr>
                            @foreach($earnAdj as $a)
                                <tr>
                                    <td>{{ $a['name'] ?? 'Adjustment' }}</td>
                                    <td class="num">-</td>
                                    <td class="num">-</td>
                                    <td class="num">{!! $money($a['amount'] ?? 0) !!}</td>
                                </tr>
                            @endforeach
                            <tr class="psTotalRow">
                                <td colspan="3">Total Gross Earnings</td>
                                <td class="num">{!! $money($p['gross'] ?? 0) !!}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="psDedSection">
                    <div class="psSectionTitle">DEDUCTIONS</div>
                    <table class="psTable psTable--deductions">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="num" style="width:16%;">Rate</th>
                                <th class="num" style="width:20%;">Current</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Attendance Deductions (Late/UT/Absent)</td><td class="num">-</td><td class="num">{!! $money($p['attendance_deduction'] ?? 0) !!}</td></tr>
                            <tr><td>SSS (EE)</td><td class="num">-</td><td class="num">{!! $money($p['sss_ee'] ?? 0) !!}</td></tr>
                            <tr><td>PhilHealth (EE)</td><td class="num">-</td><td class="num">{!! $money($p['philhealth_ee'] ?? 0) !!}</td></tr>
                            <tr><td>Withholding Tax</td><td class="num">-</td><td class="num">{!! $money($p['tax'] ?? 0) !!}</td></tr>
                            <tr><td>HDMF</td><td class="num">-</td><td class="num">{!! $money($p['pagibig_ee'] ?? 0) !!}</td></tr>
                            <tr><td>SSS Loan</td><td class="num">-</td><td class="num">{!! $money($sssLoan) !!}</td></tr>
                            <tr><td>HDMF Loan</td><td class="num">-</td><td class="num">{!! $money($hdmfLoan) !!}</td></tr>
                            <tr><td>PAGIBIG Housing Loan</td><td class="num">-</td><td class="num">{!! $money($pagibigHousing) !!}</td></tr>
                            <tr><td>SSS Housing Loan</td><td class="num">-</td><td class="num">{!! $money($sssHousing) !!}</td></tr>
                            <tr><td>HDMF Calamity Loan</td><td class="num">-</td><td class="num">{!! $money($hdmfCalamity) !!}</td></tr>
                            <tr><td>Advances</td><td class="num">-</td><td class="num">{!! $money($advances) !!}</td></tr>
                            <tr><td>Shortages</td><td class="num">-</td><td class="num">{!! $money($shortages) !!}</td></tr>
                            <tr><td>Charges</td><td class="num">-</td><td class="num">{!! $money($charges) !!}</td></tr>
                            @foreach($dedAdj as $a)
                                <tr><td>{{ $a['name'] ?? 'Adjustment' }}</td><td class="num">-</td><td class="num">{!! $money($a['amount'] ?? 0) !!}</td></tr>
                            @endforeach
                            <tr class="psTotalRow">
                                <td colspan="2">Total Deductions</td>
                                <td class="num">{!! $money($p['deductions_total'] ?? 0) !!}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <table class="psSumTable">
                        <tr>
                            <td class="psSumBox">
                                <table class="psSumLines">
                                    <tr><td>Gross Pay</td><td class="sv">{!! $money($p['gross'] ?? 0) !!}</td></tr>
                                    <tr><td>Total Deductions</td><td class="sv">{!! $money($p['deductions_total'] ?? 0) !!}</td></tr>
                                </table>
                            </td>
                            <td class="psSumNet">
                                <div class="psSumLabel">NET PAY</div>
                                <div class="psSumValue">{!! $money($p['net_pay'] ?? 0) !!}</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
