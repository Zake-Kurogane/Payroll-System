@extends('layouts.app')

@section('title', 'Reports')

@section('vite')
    @vite(['resources/css/report.css', 'resources/js/report.js'])
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endsection

@section('content')
    <section class="content">
        <div class="headline headline--withActions">
            <div>
                <h1>REPORTS</h1>
                <div class="muted small" id="reportTitle">Select a run to generate a report.</div>
            </div>

            <div class="headline__actions">
                <button class="btn btn--soft" type="button" id="exportCsvBtn" disabled>Export Excel</button>
                <button class="btn btn--maroon" type="button" id="printBtn" disabled>Print</button>
            </div>
        </div>

        <!-- CONTROLS -->
        <section class="card filterbar">
            <!-- ROW 1: Month | Cutoff | Search -->
            <div class="filterbar__row">
                <div class="f">
                    <label>Month</label>
                    <input id="monthInput" type="month" />
                </div>

                <div class="f">
                    <label>Cutoff</label>
                    <select id="cutoffSelect">
                        <option value="All" selected>Both</option>
                        <option value="11-25">11-25</option>
                        <option value="26-10">26-10</option>
                    </select>
                </div>

                <div class="f f--grow">
                    <label>Search</label>
                    <input id="searchInput" type="search" placeholder="Search employee id or name" />
                </div>
            </div>

            <!-- ROW 2: Payroll Run | Assignment Seg + Area Place -->
            <div class="filterbar__row filterbar__row--bottom">
                <div class="f f--grow">
                    <label>Matched Payroll Run</label>
                    <div id="runDisplay">No payroll run selected.</div>
                </div>

                <div class="filterbar__right">
                    <div class="seg seg--pill" id="assignSeg" role="group" aria-label="Assignment filter"></div>
                </div>
            </div>
        </section>

        <!-- KPI SUMMARY -->
        <section class="stats">
            <article class="stat">
                <div class="stat__value" id="kpiGross">â‚± 0.00</div>
                <div class="stat__label">TOTAL GROSS</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="kpiDed">â‚± 0.00</div>
                <div class="stat__label">TOTAL DEDUCTIONS (EE)</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="kpiNet">â‚± 0.00</div>
                <div class="stat__label">TOTAL NET</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="kpiER">â‚± 0.00</div>
                <div class="stat__label">TOTAL EMPLOYER SHARE (ER)</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="kpiAtmNetGross">â‚± 0.00</div>
                <div class="stat__label">ATM NET GROSS</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="kpiNonAtmNetGross">â‚± 0.00</div>
                <div class="stat__label">NON ATM NET GROSS</div>
            </article>
        </section>

        <!-- TABS -->
        <section class="card tabsCard">
            <div class="tabsBar">
                <div class="tabsMeta">
                    <div class="card__title big">Payroll Register</div>
                    <div class="muted small" id="resultsMeta">Showing 0 employee(s)</div>
                </div>
                <div class="tabs" role="tablist" aria-label="Reports tabs">
                    <button class="tabBtn is-active" type="button" data-tab="register" aria-selected="true">Payroll
                        Register</button>
                    <button class="tabBtn" type="button" data-tab="breakdown" aria-selected="false">Deductions
                        & Contributions</button>
                    <button class="tabBtn" type="button" data-tab="remit" aria-selected="false">Gov
                        Remittance</button>
                    <button class="tabBtn" type="button" data-tab="fieldAreas" aria-selected="false">Field
                        Areas</button>
                    <button class="tabBtn" type="button" data-tab="companyPayslips" aria-selected="false">Company
                        Payslips</button>
                    <button class="tabBtn" type="button" data-tab="overall" aria-selected="false">Overall
                        Summary</button>
                    <button class="tabBtn" type="button" data-tab="audit" aria-selected="false">Payslip
                        Release
                        Log</button>
                    <button class="tabBtn" type="button" data-tab="issues" aria-selected="false">Exceptions
                        /
                        Issues</button>
                </div>
            </div>

            <!-- TAB: Payroll Register -->
            <div class="tabPane" id="tab-register">

                <div class="tablewrap">
                    <table class="table" aria-label="Payroll register table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="sortable" data-sort="empName">Employee <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th>Assignment</th>
                                <th><span class="thPrintWrap">Attendance<span class="printOnlyBr"><br></span>(P/A/L)</span></th>
                                <th class="num sortable" data-sort="dailyRate">Daily Rate <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="num sortable" data-sort="attendancePay"><span class="thPrintWrap">Attendance<span class="printOnlyBr"><br></span>Pay</span> <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="num sortable" data-sort="deductionsEe"><span class="thPrintWrap">Total<span class="printOnlyBr"><br></span>Deductions</span> <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="num sortable" data-sort="gross">Gross <span class="sortIcon"
                                        aria-hidden="true"></span>
                                </th>
                                <th class="num sortable" data-sort="netPay">Net <span class="sortIcon"
                                        aria-hidden="true"></span>
                                </th>
                                <th class="sortable" data-sort="payslipStatus">Payslip Status <span class="sortIcon"
                                        aria-hidden="true"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="regTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Deductions & Contributions -->
            <div class="tabPane" id="tab-breakdown" hidden>
                <div class="card__title big">Deductions & Contributions Breakdown</div>
                <div class="muted small">Totals based on current filters/run.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Breakdown totals table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="bdTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Gov Remittance -->
            <div class="tabPane" id="tab-remit" hidden>
                <div class="card__title big">Government Remittance (EE/ER)</div>
                <div class="muted small">Export-friendly sheet view.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Government remittance table">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Employee</th>
                                <th class="num">SSS (EE)</th>
                                <th class="num">SSS (ER)</th>
                                <th class="num">PhilHealth (EE)</th>
                                <th class="num">PhilHealth (ER)</th>
                                <th class="num">Pag-IBIG (EE)</th>
                                <th class="num">Pag-IBIG (ER)</th>
                                <th class="num">Tax</th>
                            </tr>
                        </thead>
                        <tbody id="remitTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Field Area Allocations -->
            <div class="tabPane" id="tab-fieldAreas" hidden>
                <div class="card__title big">Field Area Allocations</div>
                <div class="muted small">Splits basic pay only by area place based on paid attendance dates within the selected run period (allowance excluded).</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Field area allocations table">
                        <tbody id="fieldAreasTbody"></tbody>
                    </table>
                </div>

                <div class="card__title" style="margin-top:14px;">Area Totals</div>
                <div class="tablewrap mt12">
                    <table class="table" aria-label="Field area totals table">
                        <thead>
                            <tr>
                                <th>Area Place</th>
                                <th class="num">Paid Units</th>
                                <th class="num">Allocated Amount</th>
                            </tr>
                        </thead>
                        <tbody id="fieldAreasTotalsTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Company Payslips -->
            <div class="tabPane" id="tab-companyPayslips" hidden>
                <div class="card__title big">Payslips by Company</div>
                <div class="muted small">Grouped by current company/assignment for all employees.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Company payslips table">
                        <tbody id="companyPayslipsTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Overall Summary -->
            <div class="tabPane" id="tab-overall" hidden>
                <div class="card__title big">Overall Payslip Summary</div>
                <div class="muted small">Totals for the selected run and current filters.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Overall summary table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="overallTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Payslip Release Log -->
            <div class="tabPane" id="tab-audit" hidden>
                <div class="card__title big">Payslip Release Log (Audit)</div>
                <div class="muted small">What happened when.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Audit log table">
                        <thead>
                            <tr>
                                <th>Run ID</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="num">Employees</th>
                                <th class="num">Total Net</th>
                                <th>Processed At</th>
                                <th>Processed By</th>
                                <th>Payslips Generated</th>
                                <th>Released At</th>
                            </tr>
                        </thead>
                        <tbody id="auditTbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Exceptions -->
            <div class="tabPane" id="tab-issues" hidden>
                <div class="card__title big">Exceptions / Issues</div>
                <div class="muted small">Quick flags to fix before releasing.</div>

                <div class="tablewrap mt12">
                    <table class="table" aria-label="Issues table">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Employee</th>
                                <th>Issue</th>
                                <th>Severity</th>
                            </tr>
                        </thead>
                        <tbody id="issuesTbody"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>

@endsection
