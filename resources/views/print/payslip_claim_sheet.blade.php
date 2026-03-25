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

        .meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 8px;
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
            width: 26%;
        }

        .col-assign {
            width: 18%;
        }

        .col-area {
            width: 18%;
        }

        .col-sign {
            width: 20%;
        }

        tbody td {
            height: 10mm;
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
    @foreach ($pages as $pageIndex => $rows)
        <div class="meta">
            <div>
                <h1 class="title">Payslip Claim Sheet</h1>
                <div class="sub">
                    {{ $company?->company_name ?: 'Company' }}
                </div>
            </div>

            <div class="runbox">
                <div><span class="k">Run:</span> {{ $run->displayLabel() }}</div>
                <div><span class="k">Generated:</span> {{ now()->format('Y-m-d H:i') }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th class="col-emp">Emp ID</th>
                    <th class="col-name">Name</th>
                    <th class="col-assign">Assignment</th>
                    <th class="col-area">Area</th>
                    <th class="col-sign">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $r)
                    <tr>
                        <td class="col-no">{{ ($pageIndex * 25) + $i + 1 }}</td>
                        <td class="col-emp">{{ $r['emp_no'] }}</td>
                        <td class="col-name">{{ $r['name'] }}</td>
                        <td class="col-assign">{{ $r['assignment_type'] ?: '-' }}</td>
                        <td class="col-area">{{ $r['area_place'] ?: '-' }}</td>
                        <td class="col-sign">&nbsp;</td>
                    </tr>
                @endforeach

                @for ($k = count($rows); $k < 25; $k++)
                    <tr>
                        <td class="col-no">&nbsp;</td>
                        <td class="col-emp">&nbsp;</td>
                        <td class="col-name">&nbsp;</td>
                        <td class="col-assign">&nbsp;</td>
                        <td class="col-area">&nbsp;</td>
                        <td class="col-sign">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="footer">
            <div>Note: Employees must sign beside their name upon claiming their payslip.</div>
            <div>Page {{ $pageIndex + 1 }} of {{ count($pages) }}</div>
        </div>

        @if ($pageIndex < count($pages) - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>

