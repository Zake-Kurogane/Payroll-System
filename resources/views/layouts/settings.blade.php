{{-- resources/views/settings.blade.php --}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Settings</title>

    @vite(['resources/css/settings.css', 'resources/js/settings.js'])
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
            <header class="top">
                <div>
                    <div class="top__title">SETTINGS</div>
                    <div class="top__sub">System Configuration</div>
                </div>

                <div class="top__right">
                    <div class="user-menu">
                        <button class="pill-user" type="button" id="userMenuBtn" aria-haspopup="true"
                            aria-expanded="false">
                            <span class="pill-user__name">ADMIN</span>
                            <span class="pill-user__avatar" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                                    <path
                                        d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                                </svg>
                            </span>
                        </button>

                        <div class="user-dropdown" id="userMenu" role="menu" aria-labelledby="userMenuBtn">
                            <a href="#" class="user-dropdown__item" role="menuitem">Edit Profile</a>
                            <div class="user-dropdown__divider" aria-hidden="true"></div>
                            <a href="{{ url('/logout') }}" class="user-dropdown__item user-dropdown__item--danger"
                                role="menuitem">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="headline">
                    <div>
                        <h1>System Settings</h1>
                        <p class="muted">Helper text: Actual computation happens in backend based on these settings.
                        </p>
                    </div>
                </div>

                <!-- Tabs (ORDER AS REQUESTED) -->
                <div class="tabs">
                    <button type="button" class="tab is-active" data-tab="company">Company Setup</button>
                    <button type="button" class="tab" data-tab="calendar">Payroll Calendar</button>
                    <button type="button" class="tab" data-tab="paygroups">Pay Groups</button>
                    <button type="button" class="tab" data-tab="proration">Salary &amp; Proration Rules</button>
                    <button type="button" class="tab" data-tab="timekeeping">Timekeeping Rules</button>
                    <button type="button" class="tab" data-tab="attendance">Attendance Codes</button>
                    <button type="button" class="tab" data-tab="ot">Overtime Rules</button>
                    <button type="button" class="tab" data-tab="statutory">Statutory Setup</button>
                    <button type="button" class="tab" data-tab="withholdingtax">Withholding Tax (BIR)</button>
                    <button type="button" class="tab" data-tab="cashadvance">Cash Advance</button>
                </div>

                <!-- 1) COMPANY SETUP -->
                <section class="tabPanel" id="tab-company">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Company Setup</div>
                                <div class="muted small">Company identity used for payslip header, reports, exports
                                    later.</div>
                            </div>
                        </div>

                        <div class="grid2">
                            <div class="field">
                                <label>Company Name</label>
                                <input type="text" id="companyName"
                                    placeholder="AURA FORTUNE G5 TRADERS CORPORATION" />
                            </div>

                            <div class="field">
                                <label>Company TIN</label>
                                <input type="text" id="companyTin" placeholder="000-000-000-000" />
                            </div>

                            <div class="field field--full">
                                <label>Company Address</label>
                                <input type="text" id="companyAddress" placeholder="Full address..." />
                            </div>

                            <div class="field">
                                <label>RDO</label>
                                <input type="text" id="companyRdo" placeholder="RDO No. / Name" />
                            </div>

                            <div class="field">
                                <label>Contact No. (optional)</label>
                                <input type="text" id="companyContact" placeholder="+63..." />
                            </div>

                            <div class="field">
                                <label>Email (optional)</label>
                                <input type="email" id="companyEmail" placeholder="company@email.com" />
                            </div>

                            <div class="field">
                                <label>Payroll Frequency</label>
                                <select id="payrollFrequency">
                                    <option value="semi_monthly" selected>Semi-monthly</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Currency</label>
                                <select id="currency">
                                    <option value="PHP" selected>PHP (₱)</option>
                                </select>
                            </div>
                        </div>

                        <div class="actionsRow">
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="companySave">Save Company
                                Setup</button>
                        </div>
                    </div>
                </section>

                <!-- 2) PAYROLL CALENDAR -->
                <section class="tabPanel" id="tab-calendar">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Payroll Calendar</div>
                                <div class="muted small">Defines cutoffs + pay dates (PH fixed schedule).</div>
                            </div>
                        </div>

                        <div class="sectionTitle">Pay Dates</div>
                        <div class="grid2">
                            <div class="field">
                                <label>Pay Date A</label>
                                <input type="number" id="payDateA" value="15" min="1"
                                    max="31" />
                                <div class="hint">Usually 15</div>
                            </div>

                            <div class="field">
                                <label>Pay Date B</label>
                                <select id="payDateB">
                                    <option value="EOM" selected>End-of-month</option>
                                    <option value="30">30</option>
                                    <option value="31">31</option>
                                </select>
                                <div class="hint">End-of-month = last day of month</div>
                            </div>
                        </div>

                        <div class="sectionTitle">Cutoff Windows</div>
                        <div class="grid2">
                            <div class="field">
                                <label>Cutoff A (From day)</label>
                                <input type="number" id="cutoffAFrom" value="11" min="1"
                                    max="31" />
                            </div>
                            <div class="field">
                                <label>Cutoff A (To day)</label>
                                <input type="number" id="cutoffATo" value="25" min="1"
                                    max="31" />
                            </div>

                            <div class="field">
                                <label>Cutoff B (From day)</label>
                                <input type="number" id="cutoffBFrom" value="26" min="1"
                                    max="31" />
                                <div class="hint">Cross-month supported</div>
                            </div>
                            <div class="field">
                                <label>Cutoff B (To day)</label>
                                <input type="number" id="cutoffBTo" value="10" min="1"
                                    max="31" />
                            </div>
                        </div>

                        <div class="sectionTitle">Workdays Rule</div>
                        <div class="grid2">
                            <div class="field field--full">
                                <label class="rowLabel">
                                    <input type="checkbox" id="workMonSat" checked />
                                    <span>Workdays: Monday–Saturday</span>
                                </label>
                                <div class="hint">Sunday excluded ✅</div>
                            </div>

                            <div class="field field--full">
                                <label class="rowLabel">
                                    <input type="checkbox" id="payOnPrevWorkdayIfSunday" />
                                    <span>If pay date falls on Sunday: pay on previous working day</span>
                                </label>
                            </div>
                        </div>

                        <div class="actionsRow">
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="calendarSave">Save Payroll
                                Calendar</button>
                        </div>
                    </div>
                </section>

                <!-- 3) PAY GROUPS -->
                <section class="tabPanel" id="tab-paygroups">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Pay Groups</div>
                                <div class="muted small">Keep payroll clean by assignment; minimal overrides only if
                                    needed.</div>
                            </div>
                        </div>

                        <div class="tablewrap">
                            <table class="table" style="min-width: 920px;">
                                <thead>
                                    <tr>
                                        <th>Pay Group</th>
                                        <th>Pay Frequency</th>
                                        <th>Calendar</th>
                                        <th>Default Shift Start (optional)</th>
                                        <th>Default OT Mode (optional)</th>
                                    </tr>
                                </thead>
                                <tbody id="pgTbody"></tbody>
                            </table>
                        </div>

                        <div class="hint" style="margin-top: 10px;">
                            Employee Records should store: <b>Pay Group = Tagum / Davao / Area</b> (dropdown).
                        </div>

                        <div class="actionsRow">
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="pgSave">Save Pay Groups</button>
                        </div>
                    </div>
                </section>

                <!-- 4) SALARY & PRORATION RULES -->
                <section class="tabPanel" id="tab-proration">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Salary &amp; Proration Rules</div>
                                <div class="muted small">Defines default salary computation and rounding behavior.
                                </div>
                            </div>
                        </div>

                        <div class="sectionTitle">Salary Computation Mode</div>
                        <div class="grid2">
                            <div class="field field--full">
                                <label>Mode</label>
                                <select id="salaryMode">
                                    <option value="prorate_workdays" selected>Prorated by payable workdays in cutoff
                                        (recommended)</option>
                                    <option value="fixed_50_50">Fixed split 50/50</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Work Minutes per Day</label>
                                <input type="number" id="salaryWorkMins" value="480" readonly />
                                <div class="hint">Synced from Timekeeping Rules</div>
                            </div>

                            <div class="field">
                                <label>Minutes rounding</label>
                                <select id="minutesRounding">
                                    <option value="per_minute" selected>per minute</option>
                                    <option value="nearest_15">nearest 15</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Money rounding</label>
                                <select id="moneyRounding">
                                    <option value="2_decimals" selected>2 decimals</option>
                                    <option value="nearest_peso">nearest peso</option>
                                </select>
                            </div>
                        </div>

                        <div class="actionsRow">
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="prorationSave">Save Salary &amp;
                                Proration</button>
                        </div>
                    </div>
                </section>

                <!-- 5) TIMEKEEPING RULES -->
                <section class="tabPanel" id="tab-timekeeping">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Timekeeping Rules</div>
                                <div class="muted small">Late/undertime can be rate-based or flat penalty. Computation
                                    in backend.</div>
                            </div>
                        </div>

                        <div class="sectionTitle">Shift &amp; Grace</div>
                        <div class="grid2">
                            <div class="field">
                                <label>Shift Start Time</label>
                                <input type="time" id="shiftStart" value="07:30" />
                            </div>

                            <div class="field">
                                <label>Grace Minutes</label>
                                <input type="number" id="graceMinutes" value="5" min="0" />
                            </div>
                        </div>

                        <div class="sectionTitle">Late Rule</div>
                        <div class="grid2">
                            <div class="field">
                                <label>Late Rule Type</label>
                                <select id="lateRuleType">
                                    <option value="rate_based" selected>rate_based (uses daily rate)</option>
                                    <option value="flat_penalty">flat_penalty (₱ per minute)</option>
                                </select>
                            </div>

                            <div class="field" id="latePenaltyWrap">
                                <label>Late Penalty Per Minute</label>
                                <div class="moneyInput">
                                    <span class="moneyPrefix">₱</span>
                                    <input type="number" id="latePenaltyPerMinute" value="1" min="0"
                                        step="0.01" />
                                </div>
                                <div class="hint" id="lateHint">Late deduction uses daily rate / work minutes per
                                    day.</div>
                            </div>

                            <div class="field">
                                <label>Late rounding</label>
                                <select id="lateRounding">
                                    <option value="none" selected>none</option>
                                    <option value="nearest_5">nearest 5 mins</option>
                                    <option value="nearest_15">nearest 15 mins</option>
                                </select>
                            </div>
                        </div>

                        <div class="sectionTitle">Undertime Rule (optional)</div>
                        <div class="grid2">
                            <div class="field">
                                <label class="rowLabel">
                                    <input type="checkbox" id="undertimeEnabled" />
                                    <span>Enable Undertime Deduction</span>
                                </label>
                            </div>

                            <div class="field">
                                <label>Undertime Type</label>
                                <select id="undertimeRuleType" disabled>
                                    <option value="rate_based" selected>rate_based</option>
                                    <option value="flat_penalty">flat_penalty</option>
                                </select>
                            </div>

                            <div class="field" id="undertimePenaltyWrap">
                                <label>Undertime Penalty Per Minute</label>
                                <div class="moneyInput">
                                    <span class="moneyPrefix">₱</span>
                                    <input type="number" id="undertimePenaltyPerMinute" value="1"
                                        min="0" step="0.01" disabled />
                                </div>
                                <div class="hint" id="undertimeHint">Undertime deduction uses daily rate / work
                                    minutes per day.</div>
                            </div>

                            <div class="field">
                                <label>Work Minutes per Day</label>
                                <input type="number" id="workMinutesPerDay" value="480" min="1" />
                                <div class="hint">Used for rate-based computations.</div>
                            </div>
                        </div>

                        <div class="actionsRow">
                            <button class="btn btn--soft" type="button" id="tkReset">Reset to Default</button>
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="tkSave">Save Timekeeping
                                Rules</button>
                        </div>
                    </div>
                </section>

                <!-- 6) ATTENDANCE CODES -->
                <section class="tabPanel" id="tab-attendance">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Attendance Code Mapping</div>
                                <div class="muted small">Payroll logic depends on these codes.</div>
                            </div>
                            <div>
                                <button class="btn btn--maroon" type="button" id="addCodeBtn">+ Add Code</button>
                            </div>
                        </div>

                        <div class="grid2" style="margin-bottom:12px;">
                            <div class="field">
                                <label>Default code for no log</label>
                                <select id="defaultNoLogCode"></select>
                                <div class="hint">Example: A</div>
                            </div>

                            <div class="field">
                                <label>Default code for Sunday</label>
                                <select id="defaultSundayCode"></select>
                                <div class="hint">Example: OFF</div>
                            </div>
                        </div>

                        <div class="tablewrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Description</th>
                                        <th>Counts as Present?</th>
                                        <th>Counts as Paid?</th>
                                        <th>Affects Deductions?</th>
                                        <th>Notes</th>
                                        <th style="width:140px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="codesTbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Drawer -->
                    <aside class="drawer" id="codeDrawer" aria-hidden="true">
                        <div class="drawer__overlay" id="codeDrawerOverlay"></div>
                        <div class="drawer__panel" role="dialog" aria-modal="true"
                            aria-labelledby="codeDrawerTitle">
                            <div class="drawer__head">
                                <div>
                                    <div class="drawer__title" id="codeDrawerTitle">Add Attendance Code</div>
                                    <div class="muted small">Code must be unique and uppercase.</div>
                                </div>
                                <button class="iconbtn" id="closeCodeDrawer" type="button"
                                    aria-label="Close">✕</button>
                            </div>

                            <form class="form" id="codeForm">
                                <div class="grid2">
                                    <div class="field">
                                        <label>Code</label>
                                        <input type="text" id="codeField" maxlength="6" placeholder="PL"
                                            required />
                                    </div>
                                    <div class="field">
                                        <label>Description</label>
                                        <input type="text" id="descField" placeholder="Paid Leave" required />
                                    </div>
                                    <div class="field field--full">
                                        <label>Notes (optional)</label>
                                        <input type="text" id="notesField" placeholder="Any notes..." />
                                    </div>

                                    <div class="field">
                                        <label class="rowLabel">
                                            <input type="checkbox" id="presentField" />
                                            <span>Counts as Present</span>
                                        </label>
                                    </div>

                                    <div class="field">
                                        <label class="rowLabel">
                                            <input type="checkbox" id="paidField" />
                                            <span>Counts as Paid</span>
                                        </label>
                                    </div>

                                    <div class="field">
                                        <label class="rowLabel">
                                            <input type="checkbox" id="deductField" />
                                            <span>Affects Deductions</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="actionsRow">
                                    <button class="btn btn--soft" type="button" id="cancelCodeBtn">Cancel</button>
                                    <div class="spacer"></div>
                                    <button class="btn btn--maroon" type="submit" id="saveCodeBtn">Save</button>
                                </div>
                            </form>
                        </div>
                    </aside>
                </section>

                <!-- 7) OVERTIME RULES -->
                <section class="tabPanel" id="tab-ot">
                    <div class="card">
                        <div class="card__head">
                            <div>
                                <div class="card__title">Overtime Rules</div>
                                <div class="muted small">Mode can be flat ₱/hr or rate-based multiplier (future-proof).
                                </div>
                            </div>
                        </div>

                        <div class="sectionTitle">OT Computation Mode</div>
                        <div class="grid2">
                            <div class="field field--full">
                                <label>OT Mode</label>
                                <div class="radioRow">
                                    <label class="radioPill">
                                        <input type="radio" name="otMode" id="otModeFlat" value="flat_rate"
                                            checked />
                                        <span>flat_rate (₱80/hr)</span>
                                    </label>
                                    <label class="radioPill">
                                        <input type="radio" name="otMode" id="otModeRate" value="rate_based" />
                                        <span>rate_based (daily rate × multiplier)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="field field--full">
                                <label class="rowLabel">
                                    <input type="checkbox" id="otRequireApproval" />
                                    <span>Require approval?</span>
                                </label>
                                <div class="hint">Optional (default OFF)</div>
                            </div>

                            <div class="field" id="otFlatWrap">
                                <label>Flat OT Rate</label>
                                <div class="moneyInput">
                                    <span class="moneyPrefix">₱</span>
                                    <input type="number" id="otFlatRate" value="80" min="0"
                                        step="0.01" />
                                </div>
                                <div class="hint">₱ / hour</div>
                            </div>

                            <div class="field" id="otMultWrap">
                                <label>OT Multiplier</label>
                                <input type="number" id="otMultiplier" value="1.25" min="0"
                                    step="0.01" />
                                <div class="hint">Only used when rate_based.</div>
                            </div>

                            <div class="field">
                                <label>Work Minutes per Day</label>
                                <input type="number" id="otWorkMinsSummary" value="480" readonly />
                                <div class="hint">Pulled from Timekeeping Rules.</div>
                            </div>

                            <div class="field">
                                <label>Rounding Option</label>
                                <select id="otRounding">
                                    <option value="none" selected>none</option>
                                    <option value="nearest_15">nearest 15 mins</option>
                                    <option value="nearest_30">nearest 30 mins</option>
                                    <option value="nearest_60">nearest 60 mins</option>
                                    <option value="down_15">always round down to 15 mins</option>
                                    <option value="up_15">always round up to 15 mins</option>
                                </select>
                            </div>
                        </div>

                        <div class="actionsRow">
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="button" id="otSave">Save Overtime
                                Rules</button>
                        </div>
                    </div>
                </section>

                <!-- 8) STATUTORY SETUP -->
                <section class="tabPanel" id="tab-statutory">
                    <div class="grid2">
                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">SSS Contribution Table</div>
                                    <div class="muted small">Not implemented yet — placeholder for future</div>
                                </div>
                            </div>

                            <div class="actionsRow">
                                <button class="btn btn--soft" type="button" id="sssImportBtn">Import Table</button>
                                <input type="file" id="sssImportFile" accept=".csv,.xlsx" hidden />
                            </div>

                            <div class="emptyState">
                                <div class="emptyState__title">No table loaded</div>
                                <div class="emptyState__text">Upload a CSV/XLSX later when backend import is ready.
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">PhilHealth</div>
                                    <div class="muted small">Percent + caps + split</div>
                                </div>
                            </div>

                            <div class="grid2">
                                <div class="field">
                                    <label>Employee share %</label>
                                    <input type="number" id="phEePercent" value="2.5" min="0"
                                        step="0.01" />
                                </div>
                                <div class="field">
                                    <label>Employer share %</label>
                                    <input type="number" id="phErPercent" value="2.5" min="0"
                                        step="0.01" />
                                </div>

                                <div class="field">
                                    <label>Monthly min cap</label>
                                    <input type="number" id="phMinCap" value="0" min="0"
                                        step="0.01" />
                                </div>
                                <div class="field">
                                    <label>Monthly max cap</label>
                                    <input type="number" id="phMaxCap" value="0" min="0"
                                        step="0.01" />
                                </div>

                                <div class="field field--full">
                                    <label>Split rule</label>
                                    <select id="phSplitRule">
                                        <option value="monthly" selected>monthly (deduct once per month)</option>
                                        <option value="split_cutoffs">split_cutoffs (split 1st + 2nd cutoff)</option>
                                        <option value="cutoff1_only">1st cutoff only</option>
                                        <option value="cutoff2_only">2nd cutoff only</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">Pag-IBIG</div>
                                    <div class="muted small">Percent + cap</div>
                                </div>
                            </div>

                            <div class="grid2">
                                <div class="field">
                                    <label>Employee %</label>
                                    <input type="number" id="piEePercent" value="2" min="0"
                                        step="0.01" />
                                </div>
                                <div class="field">
                                    <label>Employer %</label>
                                    <input type="number" id="piErPercent" value="2" min="0"
                                        step="0.01" />
                                </div>

                                <div class="field">
                                    <label>Monthly cap</label>
                                    <input type="number" id="piCap" value="100" min="0"
                                        step="0.01" />
                                </div>

                                <div class="field">
                                    <label>Split rule</label>
                                    <select id="piSplitRule">
                                        <option value="monthly" selected>monthly</option>
                                        <option value="split_cutoffs">split_cutoffs</option>
                                        <option value="cutoff1_only">1st cutoff only</option>
                                        <option value="cutoff2_only">2nd cutoff only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="hint" style="margin-top:10px;">
                                Actual computation happens in backend based on these settings.
                            </div>
                        </div>
                    </div>

                    <div class="actionsRow" style="margin-top:12px;">
                        <div class="spacer"></div>
                        <button class="btn btn--maroon" type="button" id="statSave">Save Statutory Setup</button>
                    </div>
                </section>

                <!-- 9) WITHHOLDING TAX (BIR) -->
                <section class="tabPanel" id="tab-withholdingtax">
                    <div class="grid2">
                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">Withholding Tax Policy</div>
                                    <div class="muted small">UI-only setup. Backend computes actual tax.</div>
                                </div>
                            </div>

                            <div class="grid2">
                                <div class="field field--full">
                                    <label class="rowLabel">
                                        <input type="checkbox" id="wtEnabled" checked />
                                        <span>Enable Withholding Tax</span>
                                    </label>
                                </div>

                                <div class="field">
                                    <label>Tax method</label>
                                    <select id="wtMethod">
                                        <option value="table" selected>Table-based (BIR bracket)</option>
                                        <option value="manual_fixed">Manual fixed amount</option>
                                        <option value="manual_percent">Manual percent</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Pay frequency</label>
                                    <select id="wtPayFrequency">
                                        <option value="semi_monthly" selected>Semi-monthly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="weekly">Weekly</option>
                                    </select>
                                </div>

                                <div class="field field--full">
                                    <label>Tax basis (taxable = gross minus selected)</label>
                                    <div class="grid2">
                                        <label class="rowLabel"><input type="checkbox" id="wtBasisSSS" checked />
                                            <span>SSS</span></label>
                                        <label class="rowLabel"><input type="checkbox" id="wtBasisPH" checked />
                                            <span>PhilHealth</span></label>
                                        <label class="rowLabel"><input type="checkbox" id="wtBasisPI" checked />
                                            <span>Pag-IBIG</span></label>
                                    </div>
                                </div>

                                <div class="field field--full">
                                    <label>Computation timing</label>
                                    <select id="wtTiming">
                                        <option value="monthly" selected>Monthly (recommended)</option>
                                        <option value="per_pay_period">Per pay period</option>
                                    </select>
                                </div>

                                <div class="field" id="wtFixedWrap">
                                    <label>Fixed tax amount</label>
                                    <div class="moneyInput">
                                        <span class="moneyPrefix">₱</span>
                                        <input type="number" id="wtFixedAmount" value="0" min="0"
                                            step="0.01" />
                                    </div>
                                    <div class="hint">Used only when method = manual_fixed</div>
                                </div>

                                <div class="field" id="wtPercentWrap">
                                    <label>Tax percent</label>
                                    <input type="number" id="wtPercent" value="0" min="0"
                                        step="0.01" />
                                    <div class="hint">Used only when method = manual_percent</div>
                                </div>

                                <div class="field field--full">
                                    <div class="hint">
                                        Recommended: <b>Table-based</b> and store tax table in DB (seed default table,
                                        allow import later).
                                    </div>
                                </div>
                            </div>

                            <div class="actionsRow">
                                <div class="spacer"></div>
                                <button class="btn btn--maroon" type="button" id="wtSavePolicy">Save Withholding Tax
                                    Policy</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">Withholding Tax Table</div>
                                    <div class="muted small">Placeholder: seed default later or import CSV/XLSX.</div>
                                </div>
                            </div>

                            <div class="actionsRow">
                                <button class="btn btn--soft" type="button" id="wtImportBtn">Import Table</button>
                                <input type="file" id="wtImportFile" accept=".csv,.xlsx" hidden />
                                <div class="spacer"></div>
                                <button class="btn" type="button" id="wtSeedBtn">Use Default (Seed)</button>
                            </div>

                            <div class="emptyState" id="wtEmptyState">
                                <div class="emptyState__title">No table loaded</div>
                                <div class="emptyState__text">You can seed a default table later or import a CSV/XLSX.
                                </div>
                            </div>

                            <div class="tablewrap" id="wtPreviewWrap" style="display:none; margin-top:12px;">
                                <table class="table" style="min-width:720px;">
                                    <thead>
                                        <tr>
                                            <th>Bracket From</th>
                                            <th>Bracket To</th>
                                            <th>Base Tax</th>
                                            <th>Excess %</th>
                                        </tr>
                                    </thead>
                                    <tbody id="wtPreviewBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="actionsRow" style="margin-top:12px;">
                        <div class="spacer"></div>
                        <button class="btn btn--maroon" type="button" id="wtSaveAll">Save Withholding Tax
                            Setup</button>
                    </div>
                </section>

                <!-- 10) CASH ADVANCE -->
                <section class="tabPanel" id="tab-cashadvance">
                    <div class="grid2">
                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">Cash Advance Policy</div>
                                    <div class="muted small">Settings</div>
                                </div>
                            </div>

                            <div class="grid2">
                                <div class="field field--full">
                                    <label class="rowLabel">
                                        <input type="checkbox" id="caEnabled" checked />
                                        <span>Allowed?</span>
                                    </label>
                                </div>

                                <div class="field">
                                    <label>Default method</label>
                                    <select id="caMethod">
                                        <option value="salary_deduction" selected>Salary deduction</option>
                                        <option value="manual_payment">Manual payment</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Default term (months)</label>
                                    <input type="number" id="caDefaultTermMonths" value="3" min="1" />
                                </div>

                                <div class="field">
                                    <label>Deduction timing</label>
                                    <select id="caDeductTiming">
                                        <option value="cutoff1">1st cutoff only</option>
                                        <option value="cutoff2">2nd cutoff only</option>
                                        <option value="split" selected>split both cutoffs</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Priority</label>
                                    <input type="number" id="caPriority" value="1" min="1" />
                                </div>

                                <div class="field field--full">
                                    <label>Deduction method</label>
                                    <select id="caDeductionMethod">
                                        <option value="equal_amortization" selected>Equal amortization per cutoff ✅
                                        </option>
                                        <option value="manual_per_cutoff">Manual amount per cutoff (later)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="actionsRow">
                                <div class="spacer"></div>
                                <button class="btn btn--maroon" type="button" id="caPolicySave">Save Cash Advance
                                    Policy</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card__head">
                                <div>
                                    <div class="card__title">Employee Cash Advance Entry</div>
                                    <div class="muted small">Transactions</div>
                                </div>
                                <div>
                                    <button class="btn btn--maroon" type="button" id="newCaBtn">+ New Cash
                                        Advance</button>
                                </div>
                            </div>

                            <div class="tablewrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Amount</th>
                                            <th>Term</th>
                                            <th>Start payroll month</th>
                                            <th>Per-cutoff deduction</th>
                                            <th>Status</th>
                                            <th style="width:160px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="caTbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Drawer -->
                    <aside class="drawer" id="caDrawer" aria-hidden="true">
                        <div class="drawer__overlay" id="caDrawerOverlay"></div>
                        <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="caDrawerTitle">
                            <div class="drawer__head">
                                <div>
                                    <div class="drawer__title" id="caDrawerTitle">New Cash Advance</div>
                                    <div class="muted small">Delete/Close should ask confirmation.</div>
                                </div>
                                <button class="iconbtn" id="closeCaDrawer" type="button"
                                    aria-label="Close">✕</button>
                            </div>

                            <form class="form" id="caForm">
                                <div class="grid2">
                                    <div class="field">
                                        <label>Employee</label>
                                        <select id="caEmployee" required>
                                            <option value="" selected disabled>Select employee...</option>
                                            <option value="Juan Dela Cruz">Juan Dela Cruz</option>
                                            <option value="Maria Santos">Maria Santos</option>
                                            <option value="Leo Garcia">Leo Garcia</option>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label>Amount</label>
                                        <div class="moneyInput">
                                            <span class="moneyPrefix">₱</span>
                                            <input type="number" id="caAmount" min="0" step="0.01"
                                                required />
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label>Term (months)</label>
                                        <input type="number" id="caTerm" min="1" value="3"
                                            required />
                                    </div>

                                    <div class="field">
                                        <label>Start month</label>
                                        <input type="month" id="caStartMonth" required />
                                    </div>

                                    <div class="field field--full">
                                        <label>Method</label>
                                        <select id="caMethodTxn" required>
                                            <option value="salary_deduction" selected>Salary deduction</option>
                                            <option value="manual_payment">Manual payment</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="actionsRow">
                                    <button class="btn btn--soft" type="button" id="cancelCaBtn">Cancel</button>
                                    <div class="spacer"></div>
                                    <button class="btn btn--maroon" type="submit">Save</button>
                                </div>
                            </form>
                        </div>
                    </aside>
                </section>

            </section>
        </main>
    </div>
</body>

</html>
