@php
    $companyName = $company?->company_name ?? 'Company';
    $companySub = collect([
        $company?->company_address,
        $company?->company_contact,
        $company?->company_email,
    ])->filter()->implode(' • ');
    $companySub = $companySub ?: '-';
    $money = fn ($v) => '₱ ' . number_format((float) $v, 2);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Payslips Print</title>
    <style>
        @page { size: A4; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }

        .print-page {
            display: grid;
            grid-template-columns: 100mm 100mm;
            grid-template-rows: 140mm 140mm;
            gap: 2mm;
            page-break-after: always;
            justify-content: center;
            align-content: start;
        }
        .print-page:last-child { page-break-after: auto; }
        .print-card {
            width: 100mm;
            height: 140mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .paystub {
            background: #fff;
            border: 1px solid #c88ea5;
            border-radius: 8px;
            padding: 3mm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
        }
        .paystub .psHead {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: start;
            gap: 6px;
            border-bottom: 2px solid #9c1d3c;
            padding-bottom: 4px;
        }
        .paystub .psHeadLeft { text-align: left; }
        .paystub .psHeadCenter {
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 1px;
            color: #64122a;
        }
        .paystub .psHeadRight { text-align: right; font-size: 8px; }
        .paystub .psCompany { font-weight: 800; font-size: 11px; }
        .paystub .psCompanySub { font-size: 8px; color: #4b5563; }
        .paystub .psMetaLine { display: flex; justify-content: flex-end; gap: 6px; }
        .paystub .psMetaLine span { color: #6b7280; }

        .paystub .psInfoGrid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 6px;
            padding: 4px 0;
            border-top: 1px solid #d6b5c2;
            border-bottom: 1px solid #d6b5c2;
        }
        .paystub .psInfoTitle {
            font-weight: 800;
            font-size: 8px;
            color: #64122a;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .paystub .psInfoRow {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-size: 9px;
            padding: 1px 0;
        }
        .paystub .psInfoRow span { color: #6b7280; }

        .paystub .psSectionTitle {
            margin-top: 1px;
            background: #9c1d3c;
            color: #fff;
            font-size: 8px;
            letter-spacing: 1px;
            padding: 2px 4px;
            font-weight: 700;
        }
        .paystub .psTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-top: 2px;
        }
        .paystub .psTable th,
        .paystub .psTable td {
            border: 1px solid #c88ea5;
            padding: 2px 4px;
            vertical-align: top;
        }
        .paystub .psTable th {
            background: #f7e8ed;
            font-weight: 700;
        }
        .paystub .psTable .num { text-align: right; white-space: nowrap; }
        .paystub .psTotalRow td {
            font-weight: 800;
            background: #f7e8ed;
        }

        .paystub .psSummaryGrid {
            margin-top: 6px;
            display: grid;
            grid-template-columns: 1fr 140px;
            gap: 6px;
            align-items: stretch;
        }
        .paystub .psSummaryBox {
            border: 1px solid #c88ea5;
            padding: 5px;
            font-size: 9px;
        }
        .paystub .psSummaryLine {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            padding: 1px 0;
        }
        .paystub .psSummaryNet {
            border: 1px solid #9c1d3c;
            background: #f7e8ed;
            display: grid;
            place-items: center;
            text-align: center;
            padding: 6px;
        }
        .paystub .psSummaryLabel {
            font-size: 8px;
            letter-spacing: 1px;
            color: #64122a;
            font-weight: 700;
        }
        .paystub .psSummaryValue {
            font-size: 12px;
            font-weight: 800;
        }
    </style>
    @if($autoprint)
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
            // Fallback: if afterprint doesn't fire, close after 20s if possible.
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
@foreach($pages as $page)
    <div class="print-page">
        @foreach($page as $p)
            @php
                $adj = collect($p['adjustments'] ?? [])->all();
                $earnAdj = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'earning');
                $dedAdj = array_filter($adj, fn ($a) => ($a['type'] ?? '') === 'deduction');
                $statEE = (float) ($p['sss_ee'] ?? 0) + (float) ($p['philhealth_ee'] ?? 0) + (float) ($p['pagibig_ee'] ?? 0) + (float) ($p['tax'] ?? 0);
            @endphp
            <div class="print-card">
                <div class="paystub">
                    <div class="psHead">
                        <div class="psHeadLeft">
                            <div class="psCompany">{{ $companyName }}</div>
                            <div class="psCompanySub">{{ $companySub }}</div>
                        </div>
                        <div class="psHeadCenter">PAYSLIP</div>
                        <div class="psHeadRight">
                            <div class="psMetaLine"><span>No</span><strong>{{ $p['payslip_no'] ?? '-' }}</strong></div>
                            <div class="psMetaLine"><span>Date</span><strong>{{ $p['generated_at'] ? \Carbon\Carbon::parse($p['generated_at'])->format('m-d-Y') : '-' }}</strong></div>
                        </div>
                    </div>

                    <div class="psInfoGrid">
                        <div class="psInfoCol">
                            <div class="psInfoTitle">EMPLOYEE</div>
                            <div class="psInfoRow"><span>Name</span><strong>{{ $p['emp_name'] ?? '-' }}</strong></div>
                            <div class="psInfoRow"><span>Employee ID</span><strong>{{ $p['emp_no'] ?? ($p['employee_id'] ?? '-') }}</strong></div>
                            <div class="psInfoRow"><span>Department</span><strong>{{ $p['department'] ?? '-' }}</strong></div>
                            <div class="psInfoRow"><span>Position</span><strong>{{ $p['position'] ?? '-' }}</strong></div>
                            @php
                                $branchName = $p['external_area'] ?? $p['area_place'] ?? null;
                                $branchName = is_string($branchName) && trim($branchName) !== '' ? trim($branchName) : '-';
                            @endphp
                            <div class="psInfoRow"><span>Assignment</span><strong>{{ $branchName }}</strong></div>
                        </div>
                        <div class="psInfoCol">
                            <div class="psInfoTitle">PAY PERIOD</div>
                            <div class="psInfoRow"><span>Payroll Month</span><strong>{{ $p['period_month'] ?? '-' }}</strong></div>
                            <div class="psInfoRow"><span>Cutoff</span><strong>{{ $p['cutoff'] ?? '-' }}</strong></div>
                            <div class="psInfoRow"><span>Period</span><strong>
                                {{ ($p['period_start'] ?? null) ? \Carbon\Carbon::parse($p['period_start'])->format('m/d/Y') : '-' }}
                                to
                                {{ ($p['period_end'] ?? null) ? \Carbon\Carbon::parse($p['period_end'])->format('m/d/Y') : '-' }}
                            </strong></div>
                            <div class="psInfoRow"><span>Pay Date</span><strong>{{ $payDate }}</strong></div>
                            @php
                                $pm = strtoupper((string) ($p['pay_method'] ?? ''));
                                $payMethod = $pm === 'BANK' ? 'Bank' : 'Cash';
                            @endphp
                            <div class="psInfoRow"><span>Pay Method</span><strong>{{ $payMethod }}</strong></div>
                        </div>
                    </div>

                    <div class="psSectionTitle">EARNINGS</div>
                    <table class="psTable">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="num">Rate</th>
                                <th class="num">Hrs/Days</th>
                                <th class="num">Amount</th>
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
                            <tr class="psTotalRow">
                                <td colspan="3">TOTAL GROSS EARNINGS</td>
                                <td class="num">{{ $money($p['gross'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="psSectionTitle">DEDUCTIONS</div>
                    <table class="psTable">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="num">Rate</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Attendance Deductions (Late/UT/Absent)</td>
                                <td class="num">-</td>
                                <td class="num">{{ $money($p['attendance_deduction'] ?? 0) }}</td>
                            </tr>
                            <tr><td>SSS (EE)</td><td class="num">-</td><td class="num">{{ $money($p['sss_ee'] ?? 0) }}</td></tr>
                            <tr><td>PhilHealth (EE)</td><td class="num">-</td><td class="num">{{ $money($p['philhealth_ee'] ?? 0) }}</td></tr>
                            <tr><td>Withholding Tax</td><td class="num">-</td><td class="num">{{ $money($p['tax'] ?? 0) }}</td></tr>
                            @php
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
                                <tr>
                                    <td>{{ $a['name'] ?? 'Adjustment' }}</td>
                                    <td class="num">-</td>
                                    <td class="num">{{ $money($a['amount'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                            <tr class="psTotalRow">
                                <td colspan="2">Total Deds</td>
                                <td class="num">{{ $money($p['deductions_total'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="psSummaryGrid">
                        <div class="psSummaryBox">
                            <div class="psSummaryLine"><span>Gross Pay</span><strong>{{ $money($p['gross'] ?? 0) }}</strong></div>
                            <div class="psSummaryLine"><span>Total Deductions</span><strong>{{ $money($p['deductions_total'] ?? 0) }}</strong></div>
                        </div>
                        <div class="psSummaryNet">
                            <div class="psSummaryLabel">NET PAY</div>
                            <div class="psSummaryValue">{{ $money($p['net_pay'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
