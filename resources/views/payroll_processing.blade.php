<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Payroll Processing</title>

    @vite(['resources/css/payroll_processing.css', 'resources/js/payroll_processing.js'])
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
                            <a href="{{ route('settings') }}" class="user-dropdown__item" role="menuitem">Settings</a>

                            <div class="user-dropdown__divider" aria-hidden="true"></div>
                            <a href="{{ url('/login') }}" class="user-dropdown__item" role="menuitem">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTENT -->
            <section class="content">

                <div class="headline">
                    <div>
                        <h1>PAYROLL PROCESSING</h1>
                        <div class="muted small" id="metaLine">Select a period, compute preview, then lock (finalize).
                        </div>
                    </div>
                </div>

                <!-- FILTERS -->
                <section class="card filters">
                    <div class="filters__grid">
                        <div class="filters__left">
                            <div class="f">
                                <label>Month</label>
                                <input id="monthInput" type="month" />
                            </div>

                            <div class="f">
                                <label>Cutoff</label>
                                <select id="cutoffSelect">
                                    <option value="1-15">1–15</option>
                                    <option value="16-end">16–End</option>
                                </select>
                            </div>

                            <div class="f f--grow">
                                <label>Search</label>
                                <input id="searchInput" type="search" placeholder="Search employee id or name" />
                            </div>
                        </div>

                        <div class="filters__right">
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
                            <div class="field field--area" id="areaPlaceFilterWrap" hidden style="display:none;">
                                <label>Area Place</label>
                                <select id="areaPlaceFilter">
                                    <option value="All" selected>All</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- NEW: RUN STATUS + SUMMARY (SANITY CHECK) -->
                <section class="card">
                    <div class="tablecard__head">
                        <div>
                            <div class="card__title big">Payroll Run</div>
                            <div class="muted small">Status, lock, and totals sanity check.</div>
                        </div>
                        <div class="tableActions" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button class="btn btn--soft" type="button" id="newRunBtn">New Run</button>
                            <button class="btn btn--maroon" type="button" id="lockRunBtn">Lock Run</button>
                            <button class="btn" type="button" id="unlockRunBtn" disabled>Unlock</button>
                            <button class="btn" type="button" id="releaseRunBtn" disabled>Release
                                (Optional)</button>
                        </div>
                    </div>

                    <div class="gridRow" style="grid-template-columns: 1fr 1fr;">
                        <div class="card" style="box-shadow:none; border:1px solid var(--line);">
                            <div class="card__title">Run Metadata</div>
                            <div class="mini" style="margin-top:12px;">
                                <div class="mini__k">Run ID</div>
                                <div class="mini__v" id="runId">—</div>

                                <div class="mini__k">Period</div>
                                <div class="mini__v" id="runPeriod">—</div>

                                <div class="mini__k">Status</div>
                                <div class="mini__v" id="runStatus">Draft</div>

                                <div class="mini__k">Created By</div>
                                <div class="mini__v" id="runCreatedBy">ADMIN</div>

                                <div class="mini__k">Created At</div>
                                <div class="mini__v" id="runCreatedAt">—</div>

                                <div class="mini__k">Locked At</div>
                                <div class="mini__v" id="runLockedAt">—</div>

                                <div class="mini__k">Released At</div>
                                <div class="mini__v" id="runReleasedAt">—</div>
                            </div>
                        </div>

                        <div class="card" style="box-shadow:none; border:1px solid var(--line);">
                            <div class="card__title">Run Summary + Variance</div>
                            <div class="summaryLines" style="margin-top:12px;">
                                <div class="summaryLine">
                                    <span>Headcount</span>
                                    <strong id="sumHeadcount">0</strong>
                                </div>
                                <div class="summaryLine">
                                    <span>Total Gross</span>
                                    <strong id="sumGross">₱ 0</strong>
                                </div>
                                <div class="summaryLine">
                                    <span>Total Deductions</span>
                                    <strong id="sumDed">₱ 0</strong>
                                </div>
                                <div class="summaryLine summaryLine--total">
                                    <span>Total Net</span>
                                    <strong id="sumNet">₱ 0</strong>
                                </div>
                                <div class="summaryLine">
                                    <span>Variance vs Previous Cutoff</span>
                                    <strong id="sumVariance">—</strong>
                                </div>
                            </div>
                            <div class="muted small" style="margin-top:10px;">
                                Tip: Lock after totals look correct. Unlock requires a reason.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- PREVIEW TABLE -->
                <section class="card tablecard">
                    <div class="tablecard__head">
                        <div>
                            <div class="card__title big">Employee Payroll Preview</div>
                            <div class="muted small" id="resultsMeta">Showing 0 employee(s)</div>
                        </div>

                        <div class="tableActions">
                            <button class="btn btn--soft" type="button" id="resetPreviewBtn">Reset Preview</button>
                        </div>
                    </div>

                    <div class="tablewrap">
                        <table class="table" aria-label="Payroll preview table">
                            <thead>
                                <tr>
                                    <th class="col-check">
                                        <input id="checkAll" type="checkbox" aria-label="Select all rows" />
                                    </th>
                                    <th>Emp ID</th>
                                    <th class="sortable" data-sort="name">Name <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th>Attendance (P/A/L)</th>
                                    <th class="num">Daily Rate</th>
                                    <th class="num">OT Hours</th>
                                    <th class="num">OT Pay</th>
                                    <th class="num">Attendance Deduction</th>
                                    <th class="num">Other Ded</th>
                                    <th class="num">Statutory + Tax (EE)</th>
                                    <th class="num">Employer Share (ER)</th>
                                    <th class="num">Net Pay</th>
                                    <th class="col-actions">Action</th>
                                </tr>
                            </thead>

                            <tbody id="payTbody">
                                <!-- rows injected by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="tableFooter">
                        <div class="tableFooter__left" id="payFooterInfo">Page 1 of 1</div>
                        <div class="tableFooter__right">
                            <label class="rowsLbl" for="payRowsSelect">Rows:</label>
                            <select class="rowsSelect" id="payRowsSelect">
                                <option>10</option>
                                <option selected>20</option>
                                <option>50</option>
                            </select>
                            <div class="pager">
                                <button class="pagerBtn" type="button" id="payFirst">&#124;&#9664;</button>
                                <button class="pagerBtn" type="button" id="payPrev">&#9664;</button>
                                <div class="pagerMid">
                                    <input class="pagerInput" id="payPageInput" type="number" min="1"
                                        value="1" />
                                    <span id="payPageTotal">/ 1</span>
                                </div>
                                <button class="pagerBtn" type="button" id="payNext">&#9654;</button>
                                <button class="pagerBtn" type="button" id="payLast">&#9654;&#124;</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- PROCESSED RUNS -->
                <section class="card runs">
                    <div class="runs__head">
                        <div>
                            <div class="card__title">Processed Payroll Runs</div>
                            <div class="muted small">Helps avoid duplicate processing.</div>
                        </div>
                    </div>

                    <div class="runs__tablewrap">
                        <table class="runsTable" aria-label="Processed runs table">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Filters</th>
                                    <th class="num">Employees</th>
                                    <th class="num">Total Net</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="runsTbody">
                                <!-- injected by JS -->
                            </tbody>
                        </table>
                    </div>
                </section>

            </section>

            <!-- STICKY FOOTER ACTIONS -->
            <footer class="sticky">
                <div class="sticky__left">
                    <div class="muted small" id="stickyHint">Compute preview before locking.</div>
                </div>
                <div class="sticky__right">
                    <button class="btn btn--soft" type="button" id="computeBtn">Compute / Refresh Preview</button>
                    <button class="btn btn--maroon" type="button" id="processBtn">Process Payroll (Lock)</button>
                    <button class="btn" type="button" id="payslipBtn" disabled>Generate Payslips</button>
                </div>
            </footer>

        </main>
    </div>

    <!-- DRAWER OVERLAY -->
    <div class="overlay" id="drawerOverlay" hidden></div>

    <!-- ADJUST DRAWER -->
    <aside class="drawer" id="drawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Adjust Payroll</div>
                <div class="drawer__sub muted small" id="drawerSub">
                    Override & one-time adjustments for this cutoff.
                </div>
            </div>
            <button class="iconx" type="button" id="closeDrawerBtn">✕</button>
        </div>

        <div class="drawer__body">

            <div class="mini">
                <div class="mini__k">Employee</div>
                <div class="mini__v" id="adjEmpName">—</div>

                <div class="mini__k">Emp ID</div>
                <div class="mini__v" id="adjEmpId">—</div>

                <div class="mini__k">Assignment</div>
                <div class="mini__v" id="adjAssign">—</div>

                <div class="mini__k">Status</div>
                <div class="mini__v" id="adjStatus">Draft</div>
            </div>

            <div class="sectionTitle">Overtime</div>

            <div class="field">
                <label>Computed OT Hours</label>
                <input id="adjComputedOt" type="number" readonly />
            </div>

            <div class="field field--switch">
                <label>Override OT?</label>
                <label class="switch">
                    <input type="checkbox" id="adjOverrideToggle" />
                    <span class="switch__ui"></span>
                </label>
            </div>

            <div class="field">
                <label>OT Hours (Override)</label>
                <input id="adjOtHours" type="number" min="0" step="0.25" disabled />
            </div>

            <div class="field">
                <label>OT Amount Preview</label>
                <input id="adjOtAmountPreview" type="text" readonly />
            </div>

            <div class="sectionTitle">Adjustments (One-time)</div>

            <div id="adjustmentList"></div>

            <button class="btn btn--soft" type="button" id="addAdjustmentBtn">
                + Add Adjustment
            </button>

            <div class="sectionTitle">Cash Advance</div>

            <div class="field">
                <label>Manual Cash Advance Deduction</label>
                <input id="adjCashAdvance" type="number" min="0" step="0.01" />
            </div>

            <div class="sectionTitle">Summary Preview</div>

            <div class="summaryLines">
                <div class="summaryLine">
                    <span>Base Pay</span>
                    <strong id="sumBase">₱ 0</strong>
                </div>
                <div class="summaryLine">
                    <span>OT</span>
                    <strong id="sumOt">₱ 0</strong>
                </div>
                <div class="summaryLine">
                    <span>Other Earnings</span>
                    <strong id="sumOtherEarn">₱ 0</strong>
                </div>
                <div class="summaryLine">
                    <span>Other Deductions</span>
                    <strong id="sumOtherDed">₱ 0</strong>
                </div>
                <div class="summaryLine summaryLine--total">
                    <span>Net Pay Preview</span>
                    <strong id="sumNetPreview">₱ 0</strong>
                </div>
            </div>

            <input type="hidden" id="adjEmpKey" />
        </div>

        <div class="drawer__foot">
            <button class="btn" id="cancelBtn">Cancel</button>
            <button class="btn btn--maroon" id="applyAdjBtn">
                Apply Adjustments
            </button>
        </div>
    </aside>

</body>

</html>
