<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Payslips</title>

    @vite(['resources/css/payslips.css', 'resources/js/payslips.js'])
</head>

<body>
    <div class="shell">

        <!-- SIDEBAR -->
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

                <!-- PAGE HEADER -->
                <div class="headline headline--withActions">
                    <div>
                        <h1>PAYSLIPS</h1>
                        <div class="muted small">View, print, and export payslips from processed payroll runs.</div>
                    </div>

                    <div class="headline__actions">
                        <button class="btn btn--soft" id="exportPdfBtn" disabled>Export PDF (Selected / All)</button>
                        <button class="btn btn--soft" id="exportCsvBtn" disabled>Export Excel/CSV</button>
                        <button class="btn btn--maroon" id="printBtn" disabled>Print (Selected / All)</button>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <section class="card filterbar">
                    <div class="filterbar__left">
                        <div class="f">
                            <label>Month</label>
                            <input id="monthInput" type="month" />
                        </div>

                        <div class="f">
                            <label>Cutoff</label>
                            <select id="cutoffInput">
                                <option value="All" selected>All</option>
                                <option value="1-15">1–15</option>
                                <option value="16–End">16–End</option>
                            </select>
                        </div>

                        <div class="f f--grow">
                            <label>Search</label>
                            <input id="searchInput" type="search" placeholder="Search Emp ID / Name" />
                        </div>
                    </div>

                    <div class="filterbar__right">
                        <div class="seg" role="tablist" aria-label="Assignment filter">
                            <button class="seg__btn is-active" type="button" data-assign="All" role="tab"
                                aria-selected="true">All</button>
                            <button class="seg__btn" type="button" data-assign="Tagum" role="tab"
                                aria-selected="false">Tagum</button>
                            <button class="seg__btn" type="button" data-assign="Davao" role="tab"
                                aria-selected="false">Davao</button>
                            <button class="seg__btn" type="button" data-assign="Area" role="tab"
                                aria-selected="false">Area</button>
                        </div>

                        <div class="f f--area" id="areaFilterWrap" hidden>
                            <label>Area Place</label>
                            <select id="areaPlaceFilter">
                                <option value="All" selected>All</option>
                                <option value="Laak">Laak</option>
                                <option value="Pantukan">Pantukan</option>
                                <option value="Maragusan">Maragusan</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- SELECT PAYROLL RUN -->
                <section class="card runCard">
                    <div class="runCard__left">
                        <div class="card__title big">Select Payroll Run</div>

                        <div class="runRow">
                            <div class="f f--grow">
                                <label>Payroll Run</label>
                                <select id="runSelect">
                                    <option value="" selected>— Select a run —</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="runCard__right">
                        <div class="runStats">
                            <div class="miniStat">
                                <div class="miniVal" id="runEmployees">—</div>
                                <div class="miniLbl">Employees</div>
                            </div>
                            <div class="miniStat">
                                <div class="miniVal" id="runTotalNet">—</div>
                                <div class="miniLbl">Total Net</div>
                            </div>
                            <div class="miniStat">
                                <div class="miniVal" id="runProcessedAt">—</div>
                                <div class="miniLbl">Processed</div>
                            </div>
                            <div class="miniStat">
                                <div class="miniVal" id="runProcessedBy">—</div>
                                <div class="miniLbl">Processed by</div>
                            </div>
                        </div>

                        <div class="runActions">
                            <button class="btn btn--maroon" id="releaseAllBtn" disabled>Release All</button>
                        </div>
                    </div>
                </section>

                <!-- TABLE CARD -->
                <section class="card tablecard">
                    <div class="tablecard__head">
                        <div>
                            <div class="card__title big">Payslips List</div>
                            <div class="muted small" id="resultsMeta">Select a run to view payslips.</div>
                        </div>

                        <div class="bulk" id="bulkBar" aria-hidden="true" style="display:none">
                            <span class="bulk__text">Selected: <span id="selectedCount">0</span></span>
                            <button class="btn btn--soft" type="button" id="bulkPdfBtn">Download Selected
                                PDFs</button>
                            <button class="btn btn--soft" type="button" id="bulkPrintBtn">Print Selected</button>
                            <button class="btn btn--soft" type="button" id="bulkReleaseBtn">Mark Released</button>
                            <button class="btn" type="button" id="bulkCancelBtn">Cancel</button>
                        </div>
                    </div>

                    <div class="tablewrap">
                        <table class="table" aria-label="Payslips table">
                            <thead>
                                <tr>
                                    <th class="col-check">
                                        <input id="checkAll" type="checkbox" aria-label="Select all rows"
                                            disabled />
                                    </th>

                                    <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="empName">Employee <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="period">Pay Period <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable num" data-sort="netPay">Net Pay <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="releaseStatus">Release Status <span
                                            class="sortIcon" aria-hidden="true"></span></th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>

                            <tbody id="payslipTbody"></tbody>
                        </table>
                    </div>

                    <div class="tableFooter">
                        <div class="tableFooter__left" id="pageLabel">Page —</div>

                        <div class="tableFooter__right">
                            <label class="toolLabel" for="rowsPerPage">Rows:</label>
                            <select id="rowsPerPage" class="toolSelect">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>

                            <div class="pager">
                                <button class="pagerBtn" type="button" id="firstPage"
                                    aria-label="First page">|◀</button>
                                <button class="pagerBtn" type="button" id="pagePrev"
                                    aria-label="Previous page">◀</button>

                                <div class="pagerMid">
                                    <input id="pageInput" class="pagerInput" type="number" min="1"
                                        value="1" />
                                    <span class="muted small">/</span>
                                    <span id="totalPages" class="small">1</span>
                                </div>

                                <button class="pagerBtn" type="button" id="pageNext"
                                    aria-label="Next page">▶</button>
                                <button class="pagerBtn" type="button" id="lastPage"
                                    aria-label="Last page">▶|</button>
                            </div>
                        </div>
                    </div>

                </section>

            </section>
        </main>
    </div>

    <!-- PAYSLIP PREVIEW OVERLAY -->
    <div class="overlay" id="psOverlay" hidden></div>

    <!-- PAYSLIP PREVIEW DRAWER -->
    <aside class="drawer drawer--wide" id="psDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Payslip Preview</div>
                <div class="drawer__sub muted small" id="psDrawerMeta">—</div>
            </div>

            <div class="drawer__headActions">
                <button class="btn btn--soft" type="button" id="psDownloadBtn">Download PDF</button>
                <button class="btn btn--soft" type="button" id="psPrintBtn">Print</button>
                <button class="btn btn--maroon" type="button" id="psReleaseBtn">Mark Released</button>
                <button class="iconx" type="button" id="psCloseBtn" aria-label="Close payslip preview">✕</button>
            </div>
        </div>

        <div class="drawer__body">
            <div class="payslipPaper paystub" id="psPaper">
                <div class="psHead">
                    <div class="psHeadLeft">
                        <div class="psCompany">Aura Fortune 5G Traders Corporation</div>
                        <div class="psCompanySub">Company Address / Contact</div>
                    </div>
                    <div class="psHeadCenter">PAYSLIP</div>
                    <div class="psHeadRight">
                        <div class="psMetaLine"><span>PAYSLIP No</span><strong id="psNo">PS-—</strong></div>
                        <div class="psMetaLine"><span>Date</span><strong id="psGenerated">—</strong></div>
                    </div>
                </div>

                <div class="psInfoGrid">
                    <div class="psInfoCol">
                        <div class="psInfoTitle">EMPLOYEE</div>
                        <div class="psInfoRow"><span>Name</span><strong id="psEmpName">—</strong></div>
                        <div class="psInfoRow"><span>Employee ID</span><strong id="psEmpId">—</strong></div>
                        <div class="psInfoRow"><span>Department</span><strong id="psDept">—</strong></div>
                        <div class="psInfoRow"><span>Position</span><strong id="psPos">—</strong></div>
                        <div class="psInfoRow"><span>Type</span><strong id="psType">—</strong></div>
                        <div class="psInfoRow"><span>Assignment</span><strong id="psAssign">—</strong></div>
                    </div>

                    <div class="psInfoCol">
                        <div class="psInfoTitle">PAY PERIOD</div>
                        <div class="psInfoRow"><span>Payroll Month</span><strong id="psMonth">—</strong></div>
                        <div class="psInfoRow"><span>Cutoff</span><strong id="psCutoff">—</strong></div>
                        <!-- ✅ Added cutoff date range -->
                        <div class="psInfoRow"><span>Cutoff Dates</span><strong id="psCutoffDates">—</strong></div>

                        <div class="psInfoRow"><span>Pay Date</span><strong id="psPayDate">—</strong></div>
                        <div class="psInfoRow"><span>Payment Method</span><strong id="psPayMethod">—</strong></div>
                        <div class="psInfoRow"><span>Account</span><strong id="psAccount">—</strong></div>
                        <div class="psInfoRow"><span>Status</span><strong id="psStatusBadge">Draft</strong></div>
                    </div>
                </div>

                <div class="psSectionTitle">EARNINGS</div>
                <table class="psTable">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num">Rate</th>
                            <th class="num">Hrs/Days</th>
                            <th class="num">Current</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic / Attendance Pay</td>
                            <td class="num" id="psDailyRate">₱ 0.00</td>
                            <td class="num"><span id="psPresentDays">0</span> / <span id="psLeaveDays">0</span> /
                                <span id="psAbsentDays">0</span>
                            </td>
                            <td class="num" id="psAttendancePay">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>Overtime</td>
                            <td class="num" id="psOtRate">₱ 0.00</td>
                            <td class="num" id="psOtHours">0.00</td>
                            <td class="num" id="psOtPay">₱ 0.00</td>
                        </tr>

                        <!-- ✅ Earnings adjustments injected here -->
                    <tbody id="psEarnAdjRows"></tbody>

                    <tr class="psTotalRow">
                        <td colspan="2">TOTAL GROSS EARNINGS</td>
                        <td class="num" id="psGross" colspan="2">₱ 0.00</td>
                    </tr>
                    </tbody>
                </table>

                <div class="psSectionTitle">DEDUCTIONS</div>
                <table class="psTable">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num">Rate</th>
                            <th class="num">Current</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Attendance Deductions (Late/UT/Absent)</td>
                            <td class="num">—</td>
                            <td class="num" id="psAttDedTotal">₱ 0.00</td>
                        </tr>

                        <tr>
                            <td>SSS (EE)</td>
                            <td class="num">—</td>
                            <td class="num" id="psSssEe">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>PhilHealth (EE)</td>
                            <td class="num">—</td>
                            <td class="num" id="psPhEe">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>Pag-IBIG (EE)</td>
                            <td class="num">—</td>
                            <td class="num" id="psPiEe">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>Withholding Tax</td>
                            <td class="num">—</td>
                            <td class="num" id="psTax">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>Total Statutory + Tax (EE)</td>
                            <td class="num">—</td>
                            <td class="num" id="psStatEeTotal">₱ 0.00</td>
                        </tr>

                        <tr>
                            <td>Cash Advance</td>
                            <td class="num">—</td>
                            <td class="num" id="psCashAdv">₱ 0.00</td>
                        </tr>

                        <tr>
                            <td>Other Deductions (manual/recurring)</td>
                            <td class="num">—</td>
                            <td class="num" id="psOtherDedTotal">₱ 0.00</td>
                        </tr>

                        <!-- ✅ Deductions adjustments injected here -->
                    <tbody id="psDedAdjRows"></tbody>

                    <tr class="psTotalRow">
                        <td colspan="2">TOTAL DEDUCTIONS</td>
                        <td class="num" id="psDedTotal" colspan="2">₱ 0.00</td>
                    </tr>
                    </tbody>
                </table>

                <div class="psSectionTitle">EMPLOYER SHARE</div>
                <table class="psTable">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num">Current</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>SSS (ER)</td>
                            <td class="num" id="psSssEr">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>PhilHealth (ER)</td>
                            <td class="num" id="psPhEr">₱ 0.00</td>
                        </tr>
                        <tr>
                            <td>Pag-IBIG (ER)</td>
                            <td class="num" id="psPiEr">₱ 0.00</td>
                        </tr>
                        <tr class="psTotalRow">
                            <td>Total Employer Share</td>
                            <td class="num" id="psErTotal">₱ 0.00</td>
                        </tr>
                    </tbody>
                </table>

                <div class="psSummaryGrid">
                    <div class="psSummaryBox">
                        <div class="psSummaryLine"><span>Gross Pay</span><strong id="psSumGross">₱ 0.00</strong></div>
                        <div class="psSummaryLine"><span>Total Deductions</span><strong id="psSumDed">₱ 0.00</strong>
                        </div>
                    </div>
                    <div class="psSummaryNet">
                        <div class="psSummaryLabel">NET PAY</div>
                        <div class="psSummaryValue" id="psNet">₱ 0.00</div>
                    </div>
                </div>

                <div class="psNotes">
                    <div class="psNotesLabel">Notes / Remarks</div>
                    <div class="psNotesBox" id="psNotes">Adjust Notes: —</div>
                    <div class="psNotesSmall">Disclaimer: This payslip is system-generated.</div>
                </div>

                <div class="psSignRow">
                    <div class="psSign">
                        <div class="psSignLine"></div>
                        <div class="psSignLabel">Received by / Signature</div>
                    </div>
                    <div class="psSign">
                        <div class="psSignLine"></div>
                        <div class="psSignLabel">Employer Rep (optional)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="drawer__foot">
            <button class="btn" type="button" id="psCloseFooterBtn">Close</button>
        </div>
    </aside>

</body>

</html>
