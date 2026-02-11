<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Reports</title>

    @vite(['resources/css/report.css', 'resources/js/report.js'])
</head>

<body>
    <div class="shell">
        <aside class="side">
            <div class="brand">
                <div class="brand__mark">
                    <img class="brand__logo" src="/image/logo.png" alt="Aura Fortune G5 Traders Corporation logo" />
                </div>
                <div class="brand__text">
                    <div class="brand__title">AURA FORTUNE G5</div>
                    <div class="brand__sub">TRADERS CORPORATION</div>
                </div>
            </div>

            <nav class="menu">
                <a class="menu__item {{ request()->routeIs('index') ? 'is-active' : '' }}" href="{{ route('index') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7v-10h8v10h-8z" />
                        </svg>
                    </span>
                    <span>DASHBOARD</span>
                </a>

                <a class="menu__item {{ request()->routeIs('employee.records') ? 'is-active' : '' }}"
                    href="{{ route('employee.records') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path
                                d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.94 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z" />
                        </svg>
                    </span>
                    <span>EMPLOYEE<br />RECORDS</span>
                </a>

                <a class="menu__item {{ request()->routeIs('attendance') ? 'is-active' : '' }}"
                    href="{{ route('attendance') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path
                                d="M7 2h10v2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V2zm0 6h10V6H7v2zm0 4h6v2H7v-2zm0 4h8v2H7v-2z" />
                        </svg>
                    </span>
                    <span>ATTENDANCE</span>
                </a>

                <a class="menu__item {{ request()->routeIs('payroll.processing') ? 'is-active' : '' }}"
                    href="{{ route('payroll.processing') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path d="M4 4h10a2 2 0 0 1 2 2v2h4v12H6a2 2 0 0 1-2-2V4zm2 2v14h12V10h-4V6H6z" />
                            <path d="M9 12h6v2H9v-2zm0 4h6v2H9v-2z" />
                        </svg>
                    </span>
                    <span>PAYROLL<br />PROCESSING</span>
                </a>

                <a class="menu__item {{ request()->routeIs('payslip') ? 'is-active' : '' }}"
                    href="{{ route('payslip') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1v5h5" />
                            <path d="M7 12h10v2H7v-2zm0 4h10v2H7v-2z" />
                        </svg>
                    </span>
                    <span>PAYSLIP</span>
                </a>

                <a class="menu__item {{ request()->routeIs('report') ? 'is-active' : '' }}"
                    href="{{ route('report') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path d="M4 19h16v2H2V3h2v16z" />
                            <path d="M7 17V9h3v8H7zm5 0V5h3v12h-3zm5 0v-6h3v6h-3z" />
                        </svg>
                    </span>
                    <span>REPORT</span>
                </a>
            </nav>

            <div class="side__footer">
                <div class="time" id="clock">--:-- --</div>
                <div class="date" id="date">--/--/----</div>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
            <!-- TOP BAR -->
            <header class="top">
                <div>
                    <div class="top__title">WELCOME</div>
                    <div class="top__sub">ADMIN</div>
                </div>

                <div class="top__right">
                    <div class="user-menu">
                        <button class="pill-user" type="button" id="userMenuBtn" aria-haspopup="true"
                            aria-expanded="false">
                            <span class="pill-user__name">ADMIN</span>
                            <span class="pill-user__avatar" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="ico">
                                    <path
                                        d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                                </svg>
                            </span>
                        </button>

                        <div class="user-dropdown" id="userMenu" role="menu" aria-labelledby="userMenuBtn">
                            <a href="#" class="user-dropdown__item" role="menuitem">Edit Profile</a>
                            <a href="{{ route('logout') }}" class="user-dropdown__item" role="menuitem">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTENT -->
            <section class="content">
                <div class="headline headline--withActions">
                    <div>
                        <h1>REPORTS</h1>
                        <div class="muted small" id="reportTitle">Select a run to generate a report.</div>
                    </div>

                    <div class="headline__actions">
                        <button class="btn btn--soft" type="button" id="viewRunBtn" disabled>View Payroll Run</button>
                        <button class="btn btn--soft" type="button" id="exportCsvBtn" disabled>Export CSV</button>
                        <button class="btn btn--soft" type="button" id="downloadPdfBtn" disabled>Download PDF</button>
                        <button class="btn btn--maroon" type="button" id="printBtn" disabled>Print</button>
                    </div>
                </div>

                <!-- CONTROLS -->
                <section class="card filterbar">
                    <!-- ROW 1: Month | Cutoff | Search | Department -->
                    <div class="filterbar__row">
                        <div class="f">
                            <label>Month</label>
                            <input id="monthInput" type="month" />
                        </div>

                        <div class="f">
                            <label>Cutoff</label>
                            <select id="cutoffSelect">
                                <option value="All" selected>Both</option>
                                <option value="1–15">1–15</option>
                                <option value="16–End">16–End</option>
                            </select>
                        </div>

                        <div class="f f--grow">
                            <label>Search</label>
                            <input id="searchInput" type="search" placeholder="Search employee id or name" />
                        </div>

                        <div class="f">
                            <label>Department</label>
                            <select id="deptSelect">
                                <option value="All" selected>All</option>
                                <option>Operations</option>
                                <option>Finance</option>
                                <option>Sales</option>
                            </select>
                        </div>
                    </div>

                    <!-- ROW 2: Payroll Run | Status | Assignment Seg + Area Place -->
                    <div class="filterbar__row filterbar__row--bottom">
                        <div class="f f--grow">
                            <label>Payroll Run</label>
                            <select id="runSelect"></select>
                        </div>

                        <div class="f">
                            <label>Status</label>
                            <select id="statusSelect">
                                <option value="All" selected>All</option>
                                <option>Processed</option>
                                <option>Released</option>
                            </select>
                        </div>

                        <div class="filterbar__right">
                            <div class="seg" role="tablist" aria-label="Assignment filter">
                                <button class="seg__btn is-active" type="button" data-assign="All"
                                    aria-selected="true">All</button>
                                <button class="seg__btn" type="button" data-assign="Tagum"
                                    aria-selected="false">Tagum</button>
                                <button class="seg__btn" type="button" data-assign="Davao"
                                    aria-selected="false">Davao</button>
                                <button class="seg__btn" type="button" data-assign="Area"
                                    aria-selected="false">Area</button>
                            </div>

                            <div class="f" id="areaPlaceWrap" style="display:none; margin-left: 10px;">
                                <label>Area Place</label>
                                <select id="areaPlaceSelect">
                                    <option value="All" selected>All</option>
                                    <option>Laak</option>
                                    <option>Pantukan</option>
                                    <option>Maragusan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RUN SUMMARY CARD -->
                <section class="card runCard" id="runCard">
                    <div class="runCard__left">
                        <div class="runCard__title">Selected Payroll Run</div>
                    </div>

                    <div class="runCard__right">
                        <div class="runStats">
                            <!-- Badge cell (same row as others) -->
                            <div class="runStat runStat--badge">
                                <div class="runBadge" id="runBadge">—</div>
                                <div class="runMetaUnder muted small" id="runMeta">2026-01 (16–End) • Assignment:
                                    All</div>
                            </div>

                            <div class="runStat">
                                <div class="runStat__k">Employees</div>
                                <div class="runStat__v" id="runEmployees">—</div>
                            </div>

                            <div class="runStat">
                                <div class="runStat__k">Total Net</div>
                                <div class="runStat__v" id="runTotalNet">—</div>
                            </div>

                            <div class="runStat">
                                <div class="runStat__k">Processed</div>
                                <div class="runStat__v" id="runProcessedAt">—</div>
                            </div>

                            <div class="runStat">
                                <div class="runStat__k">By</div>
                                <div class="runStat__v" id="runProcessedBy">—</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- KPI SUMMARY -->
                <section class="stats">
                    <article class="stat">
                        <div class="stat__value" id="kpiEmployees">0</div>
                        <div class="stat__label">EMPLOYEES PAID</div>
                    </article>
                    <article class="stat">
                        <div class="stat__value" id="kpiGross">₱ 0.00</div>
                        <div class="stat__label">TOTAL GROSS</div>
                    </article>
                    <article class="stat">
                        <div class="stat__value" id="kpiDed">₱ 0.00</div>
                        <div class="stat__label">TOTAL DEDUCTIONS (EE)</div>
                    </article>
                    <article class="stat">
                        <div class="stat__value" id="kpiNet">₱ 0.00</div>
                        <div class="stat__label">TOTAL NET</div>
                    </article>
                    <article class="stat">
                        <div class="stat__value" id="kpiER">₱ 0.00</div>
                        <div class="stat__label">TOTAL EMPLOYER SHARE (ER)</div>
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
                            <button class="tabBtn is-active" type="button" data-tab="register"
                                aria-selected="true">Payroll Register</button>
                            <button class="tabBtn" type="button" data-tab="breakdown"
                                aria-selected="false">Deductions
                                & Contributions</button>
                            <button class="tabBtn" type="button" data-tab="remit" aria-selected="false">Gov
                                Remittance</button>
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
                                        <th>Department</th>
                                        <th>Attendance (P/A/L)</th>
                                        <th class="num sortable" data-sort="dailyRate">Daily Rate <span
                                                class="sortIcon" aria-hidden="true"></span></th>
                                        <th class="num sortable" data-sort="attendancePay">Attendance Pay <span
                                                class="sortIcon" aria-hidden="true"></span></th>
                                        <th class="num sortable" data-sort="otHours">OT Hours <span class="sortIcon"
                                                aria-hidden="true"></span></th>
                                        <th class="num sortable" data-sort="otPay">OT Pay <span class="sortIcon"
                                                aria-hidden="true"></span>
                                        </th>
                                        <th class="num sortable" data-sort="deductionsEe">Deductions (EE) <span
                                                class="sortIcon" aria-hidden="true"></span></th>
                                        <th class="num sortable" data-sort="employerShare">Employer Share (ER) <span
                                                class="sortIcon" aria-hidden="true"></span></th>
                                        <th class="num sortable" data-sort="gross">Gross <span class="sortIcon"
                                                aria-hidden="true"></span>
                                        </th>
                                        <th class="num sortable" data-sort="netPay">Net <span class="sortIcon"
                                                aria-hidden="true"></span>
                                        </th>
                                        <th class="sortable" data-sort="payslipStatus">Payslip Status <span
                                                class="sortIcon" aria-hidden="true"></span>
                                        </th>
                                        <th class="col-actions">Action</th>
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
        </main>
    </div>
</body>

</html>
