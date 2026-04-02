<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Payslip Claim Sheet</title>
    <style>
        @page {
            margin: 14mm 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }

        .metaTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        .metaTable td {
            border: 0 !important;
            padding: 0;
            vertical-align: top;
        }
        .metaLeft  { width: 62%; }
        .metaRight { width: 38%; }

        .title   { font-size: 15px; font-weight: 700; margin: 0; }
        .sub     { margin: 2px 0 0; font-size: 10px; color: #444; }
        .runbox  { text-align: right; font-size: 10px; margin-top: 0; }
        .runbox .k { color: #444; }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1.4px solid #111;
            padding: 3px 4px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
            font-size: 9px;
        }

        /* Column widths — must match ClaimSheetScanner column % positions:
           QR  57–68 %  (start = 5+10+26+16 = 57 %)
           Sig 68–90 %
           □   90–97 %
           Date 97-100% */
        .col-no   { width: 5%;  text-align: center; }
        .col-emp  { width: 10%; }
        .col-name { width: 26%; }
        .col-area { width: 16%; }
        .col-qr   { width: 12%; text-align: center; padding: 2px; }
        .col-sign { width: 22%; }
        .col-rec  { width: 7%;  text-align: center; font-size: 9px; padding: 2px; }
        .col-date { width: 2%;  font-size: 8px; padding: 2px; }

        tbody td  { height: 17mm; }

        .qr-img { width: 14mm; height: 14mm; display: block; margin: 0 auto; }
        .qr-token {
            display: block;
            text-align: center;
            font-size: 6px;
            color: #777;
            margin-top: 1px;
            letter-spacing: 0.5px;
        }

        .receivedBox {
            width: 4mm;
            height: 4mm;
            border: 1.4px solid #111;
            margin: 0 auto;
        }

        .page-break { page-break-after: always; }

        .footer {
            margin-top: 6px;
            font-size: 9px;
            color: #444;
        }
        .footer-inner {
            display: table;
            width: 100%;
        }
        .footer-inner > div {
            display: table-cell;
        }
        .footer-inner > div:last-child {
            text-align: right;
        }
    </style>
</head>

<body>
    @foreach ($pages as $pageIndex => $page)
        @php($rows = $page['rows'] ?? [])
        <table class="metaTable">
            <tr>
                <td class="metaLeft">
                    <h1 class="title">Payslip Claim Sheet</h1>
                    <div class="sub">{{ $company?->company_name ?: 'Company' }}</div>
                </td>
                <td class="metaRight">
                    <div class="runbox">
                        <div>
                            <span class="k">Run:</span>
                            {{ $run->run_code ?: ('RUN-' . $run->id) }}
                            {{ $run->period_month ? (' • ' . $run->period_month) : '' }}
                            {{ $run->cutoff ? (' (' . $run->cutoff . ')') : '' }}
                            {{ ' • ' . $run->displayLabel() }}
                        </div>
                        <div><span class="k">Area:</span> {{ $page['area'] ?? '—' }}</div>
                        @if (!empty($page['area_page']) && !empty($page['area_pages']))
                            <div><span class="k">Area Page:</span> {{ $page['area_page'] }} of {{ $page['area_pages'] }}</div>
                        @endif
                        <div><span class="k">Generated:</span> {{ now()->format('Y-m-d H:i') }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th class="col-emp">Emp ID</th>
                    <th class="col-name">Name</th>
                    <th class="col-area">Area</th>
                    <th class="col-qr">QR</th>
                    <th class="col-sign">Signature</th>
                    <th class="col-rec">&#9744; Rec.</th>
                    <th class="col-date">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td class="col-no">{{ $r['no'] ?? '' }}</td>
                        <td class="col-emp">{{ $r['emp_no'] }}</td>
                        <td class="col-name">{{ $r['name'] }}</td>
                        <td class="col-area">{{ $r['area_place'] ?: '-' }}</td>
                        <td class="col-qr">
                            @if (!empty($r['qr_data_uri']))
                                <img class="qr-img" src="{{ $r['qr_data_uri'] }}" alt="QR" />
                                <span class="qr-token">{{ $r['token'] ?? '' }}</span>
                            @endif
                        </td>
                        <td class="col-sign">&nbsp;</td>
                        <td class="col-rec">
                            <div class="receivedBox"></div>
                        </td>
                        <td class="col-date">&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-inner">
                <div>Note: Employee must sign and shade the Received (&#9744;) box upon claiming their payslip.</div>
                <div>Page {{ $pageIndex + 1 }} of {{ count($pages) }}</div>
            </div>
        </div>

        @if ($pageIndex < count($pages) - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>
