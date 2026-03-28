<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payslip Claim Sheet</title>
    <style>
        @page {
            margin: 14mm 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        /* Use table layout for reliable PDF rendering (Dompdf flex can be inconsistent). */
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
        .metaLeft {
            width: 62%;
        }
        .metaRight {
            width: 38%;
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }

        .sub {
            margin: 2px 0 0;
            font-size: 11px;
            color: #444;
        }

        .runbox {
            text-align: right;
            font-size: 11px;
            margin-top: 0;
        }

        .runbox .k {
            color: #444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1.4px solid #111;
            padding: 4px 6px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
        }

        .col-no {
            width: 6%;
            text-align: center;
        }

        .col-emp {
            width: 12%;
        }

        .col-name {
            width: 25%;
        }

        .col-assign {
            width: 17%;
        }

        .col-area {
            width: 18%;
        }

        .col-sign {
            width: 14%;
        }

        .col-rec {
            width: 8%;
            text-align: center;
            white-space: nowrap;
            font-size: 10px;
            padding: 2px 2px;
        }

        tbody td {
            height: 10mm;
        }

        .receivedBox {
            width: 8mm;
            height: 8mm;
            border: 1.4px solid #111;
            margin: 0 auto;
        }

        .receivedBox--claimed {
            background: #111;
        }

        .page-break {
            page-break-after: always;
        }

        .footer {
            margin-top: 6px;
            font-size: 10px;
            color: #444;
            display: flex;
            justify-content: space-between;
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
                    <div class="sub">
                        {{ $company?->company_name ?: 'Company' }}
                    </div>
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
                    <th class="col-assign">Assignment</th>
                    <th class="col-area">Area</th>
                    <th class="col-sign">Signature</th>
                    <th class="col-rec">Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $r)
                    <tr>
                        <td class="col-no">{{ $r['no'] ?? '' }}</td>
                        <td class="col-emp">{{ $r['emp_no'] }}</td>
                        <td class="col-name">{{ $r['name'] }}</td>
                        <td class="col-assign">{{ $r['assignment_type'] ?: '-' }}</td>
                        <td class="col-area">{{ $r['area_place'] ?: '-' }}</td>
                        <td class="col-sign">&nbsp;</td>
                        <td class="col-rec">
                            @php($claimed = !empty($r['claimed_at']))
                            <div class="receivedBox {{ $claimed ? 'receivedBox--claimed' : '' }}"></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <div>Note: Employees must sign and shade the Received box upon claiming their payslip.</div>
            <div>Page {{ $pageIndex + 1 }} of {{ count($pages) }}</div>
        </div>

        @if ($pageIndex < count($pages) - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>
