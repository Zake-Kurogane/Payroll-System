@extends('layouts.app')

@section('title', 'Settings')

@section('vite')
    @vite(['resources/css/settings.css', 'resources/js/settings.js'])
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endpush

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>System Settings</h1>
                <p class="muted">Helper text: Above 10,000 uses the cap (2% of 10,000 = 200 each, total 400).
                </p>
            </div>
        </div>

        <!-- Tabs (ORDER AS REQUESTED) -->
        <div class="tabs" role="tablist" aria-label="Settings sections">
            <button type="button" class="tab is-active" data-tab="company" id="tab-company-btn" role="tab"
                aria-controls="tab-company" aria-selected="true" tabindex="0">Company Setup</button>
            <button type="button" class="tab" data-tab="calendar" id="tab-calendar-btn" role="tab"
                aria-controls="tab-calendar" aria-selected="false" tabindex="-1">Payroll Calendar</button>
            <button type="button" class="tab" data-tab="proration" id="tab-proration-btn" role="tab"
                aria-controls="tab-proration" aria-selected="false" tabindex="-1">Salary &amp; Proration Rules</button>
            <button type="button" class="tab" data-tab="timekeeping" id="tab-timekeeping-btn" role="tab"
                aria-controls="tab-timekeeping" aria-selected="false" tabindex="-1">Timekeeping Rules</button>
            <button type="button" class="tab" data-tab="attendance" id="tab-attendance-btn" role="tab"
                aria-controls="tab-attendance" aria-selected="false" tabindex="-1">Attendance Codes</button>
            <button type="button" class="tab" data-tab="ot" id="tab-ot-btn" role="tab" aria-controls="tab-ot"
                aria-selected="false" tabindex="-1">Overtime Rules</button>
            <button type="button" class="tab" data-tab="statutory" id="tab-statutory-btn" role="tab"
                aria-controls="tab-statutory" aria-selected="false" tabindex="-1">Statutory Setup</button>
            <button type="button" class="tab" data-tab="withholdingtax" id="tab-withholdingtax-btn" role="tab"
                aria-controls="tab-withholdingtax" aria-selected="false" tabindex="-1">Withholding Tax (BIR)</button>
            <button type="button" class="tab" data-tab="cashadvance" id="tab-cashadvance-btn" role="tab"
                aria-controls="tab-cashadvance" aria-selected="false" tabindex="-1">Cash Advance</button>
            <button type="button" class="tab" data-tab="users" id="tab-users-btn" role="tab"
                aria-controls="tab-users" aria-selected="false" tabindex="-1">User Accounts</button>
        </div>

        <!-- 1) COMPANY SETUP -->
        <section class="tabPanel" id="tab-company" role="tabpanel" aria-labelledby="tab-company-btn">
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Company Setup</div>
                        <div class="muted small">Company identity used for payslip header, reports, exports
                            later.</div>
                    </div>
                </div>
                <div class="notice notice--success" id="companyNotice" hidden></div>

                <div class="grid2">
                    <div class="field">
                        <label>Company Name</label>
                        <input type="text" id="companyName" placeholder="AURA FORTUNE G5 TRADERS CORPORATION" />
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
        <section class="tabPanel" id="tab-calendar" role="tabpanel" aria-labelledby="tab-calendar-btn" hidden>
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Payroll Calendar</div>
                        <div class="muted small">Defines cutoffs + pay dates (PH fixed schedule).</div>
                    </div>
                </div>
                <div class="notice notice--success" id="calendarNotice" hidden></div>

                <div class="sectionTitle">Pay Dates</div>
                <div class="grid2">
                    <div class="field">
                        <label>Pay Date A</label>
                        <input type="number" id="payDateA" value="15" min="1" max="31" />
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
                        <input type="number" id="cutoffAFrom" value="11" min="1" max="31" />
                    </div>
                    <div class="field">
                        <label>Cutoff A (To day)</label>
                        <input type="number" id="cutoffATo" value="25" min="1" max="31" />
                    </div>

                    <div class="field">
                        <label>Cutoff B (From day)</label>
                        <input type="number" id="cutoffBFrom" value="26" min="1" max="31" />
                        <div class="hint">Cross-month supported</div>
                    </div>
                    <div class="field">
                        <label>Cutoff B (To day)</label>
                        <input type="number" id="cutoffBTo" value="10" min="1" max="31" />
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

        <!-- 4) SALARY & PRORATION RULES -->
        <section class="tabPanel" id="tab-proration" role="tabpanel" aria-labelledby="tab-proration-btn" hidden>
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Salary &amp; Proration Rules</div>
                        <div class="muted small">Defines default salary computation and rounding behavior.
                        </div>
                    </div>
                </div>
                <div class="notice notice--success" id="prorationNotice" hidden></div>

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
        <section class="tabPanel" id="tab-timekeeping" role="tabpanel" aria-labelledby="tab-timekeeping-btn" hidden>
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Timekeeping Rules</div>
                        <div class="muted small">Late/undertime can be rate-based or flat penalty. Computation
                            in backend.</div>
                    </div>
                </div>
                <div class="notice notice--success" id="timekeepingNotice" hidden></div>

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
                            <input type="number" id="undertimePenaltyPerMinute" value="1" min="0"
                                step="0.01" disabled />
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
        <section class="tabPanel" id="tab-attendance" role="tabpanel" aria-labelledby="tab-attendance-btn" hidden>
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
                <div class="notice notice--success" id="attendanceNotice" hidden></div>

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
                <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="codeDrawerTitle">
                    <div class="drawer__head">
                        <div>
                            <div class="drawer__title" id="codeDrawerTitle">Add Attendance Code</div>
                            <div class="muted small">Code must be unique and uppercase.</div>
                        </div>
                        <button class="iconbtn" id="closeCodeDrawer" type="button" aria-label="Close">✕</button>
                    </div>

                    <form class="form" id="codeForm">
                        <div class="grid2">
                            <div class="field">
                                <label>Code</label>
                                <input type="text" id="codeField" maxlength="6" placeholder="PL" required />
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
        <section class="tabPanel" id="tab-ot" role="tabpanel" aria-labelledby="tab-ot-btn" hidden>
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Overtime Rules</div>
                        <div class="muted small">Mode can be flat ₱/hr or rate-based multiplier (future-proof).
                        </div>
                    </div>
                </div>
                <div class="notice notice--success" id="overtimeNotice" hidden></div>

                <div class="sectionTitle">OT Computation Mode</div>
                <div class="grid2">
                    <div class="field field--full">
                        <label>OT Mode</label>
                        <div class="radioRow">
                            <label class="radioPill">
                                <input type="radio" name="otMode" id="otModeFlat" value="flat_rate" checked />
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
                            <input type="number" id="otFlatRate" value="80" min="0" step="0.01" />
                        </div>
                        <div class="hint">₱ / hour</div>
                    </div>

                    <div class="field" id="otMultWrap">
                        <label>OT Multiplier</label>
                        <input type="number" id="otMultiplier" value="1.25" min="0" step="0.01" />
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
        <section class="tabPanel" id="tab-statutory" role="tabpanel" aria-labelledby="tab-statutory-btn" hidden>
            <div class="notice notice--success" id="statutoryNotice" hidden></div>
            <div class="grid2">
                <div class="card">
                    <div class="card__head">
                        <div>
                            <div class="card__title">SSS Contribution Table</div>
                            <div class="muted small">Upload a CSV/XLSX to preview and cache locally.</div>
                        </div>
                    </div>

                    <div class="cardDivider"></div>

                    <div class="importedBar" id="sssImportedWrap" style="display:none;">
                        <div class="importedBar__label">Imported:</div>
                        <div class="importedBar__bar">
                            <div class="importedBar__name" id="sssImportedName"></div>
                            <div class="importedBar__actions">
                                <button class="btn btn--ghost" type="button" id="sssToggleBtn">View</button>
                                <button class="btn btn--ghost" type="button" id="sssClearBtn">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="emptyState" id="sssEmptyState">
                        <div class="emptyState__title">No table loaded</div>
                        <div class="emptyState__text">Upload a CSV/XLSX to preview the SSS table.
                        </div>
                    </div>

                    <div class="tablewrap" id="sssPreviewWrap" style="display:none; margin-top:12px;">
                        <table class="table" style="min-width:760px;">
                            <thead id="sssPreviewHead"></thead>
                            <tbody id="sssPreviewBody"></tbody>
                        </table>
                    </div>
                    <div class="hint" id="sssMeta" style="margin-top:8px; display:none;"></div>

                    <div class="cardDivider"></div>
                    <div class="grid2">
                        <div class="field">
                            <label>Employee share %</label>
                            <input type="number" id="sssEePercent" value="5" min="0" step="0.01" />
                        </div>
                        <div class="field">
                            <label>Employer share %</label>
                            <input type="number" id="sssErPercent" value="10" min="0" step="0.01" />
                        </div>
                        <div class="field field--full">
                            <label>Split rule</label>
                            <select id="sssSplitRule">
                                <option value="monthly" selected>monthly (deduct once per month)</option>
                                <option value="split_cutoffs">split_cutoffs (split 1st + 2nd cutoff)</option>
                                <option value="cutoff1_only">1st cutoff only</option>
                                <option value="cutoff2_only">2nd cutoff only</option>
                            </select>
                        </div>
                    </div>

                    <div class="actionsRow actionsRow--noLine">
                        <div class="spacer"></div>
                        <button class="btn btn--soft" type="button" id="sssImportBtn">Import Table</button>
                        <input type="file" id="sssImportFile" accept=".csv,.xlsx" hidden />
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
                            <input type="number" id="phEePercent" value="5" min="0" step="0.01" />
                        </div>
                        <div class="field">
                            <label>Employer share %</label>
                            <input type="number" id="phErPercent" value="10" min="0" step="0.01" />
                        </div>

                        <div class="field">
                            <label>Monthly min cap</label>
                            <input type="number" id="phMinCap" value="0" min="0" step="0.01" />
                        </div>
                        <div class="field">
                            <label>Monthly max cap</label>
                            <input type="number" id="phMaxCap" value="0" min="0" step="0.01" />
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
                            <div class="card__title">Pag-IBIG Contribution Table</div>
                            <div class="muted small">Monthly salary range rules</div>
                        </div>
                    </div>

                    <div class="grid2">
                        <div class="field">
                            <label>Employee % (<= 1,500)</label>
                                    <input type="number" id="piEePercentLow" value="1" min="0"
                                        step="0.01" />
                        </div>
                        <div class="field">
                            <label>Salary threshold</label>
                            <input type="number" id="piEeThreshold" value="1500" min="0" step="0.01" />
                        </div>

                        <div class="field">
                            <label>Employee % (1,501 - 10,000)</label>
                            <input type="number" id="piEePercent" value="2" min="0" step="0.01" />
                        </div>
                        <div class="field">
                            <label>Employer %</label>
                            <input type="number" id="piErPercent" value="2" min="0" step="0.01" />
                        </div>

                        <div class="field">
                            <label>Monthly cap (above 10,000)</label>
                            <input type="number" id="piCap" value="10000" min="0" step="0.01" />
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
                        Above 10,000 uses the cap (2% of 10,000 = 200 each, total 400).
                    </div>
                </div>
            </div>

            <div class="actionsRow" style="margin-top:12px;">
                <div class="spacer"></div>
                <button class="btn btn--maroon" type="button" id="statSave">Save Statutory Setup</button>
            </div>
        </section>

        <!-- 9) WITHHOLDING TAX (BIR) -->
        <section class="tabPanel" id="tab-withholdingtax" role="tabpanel" aria-labelledby="tab-withholdingtax-btn"
            hidden>
            <div class="notice notice--success" id="withholdingNotice" hidden></div>
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

                        <div class="field field--full">
                            <label>Split rule</label>
                            <select id="wtSplitRule">
                                <option value="monthly" selected>monthly (deduct once per month)</option>
                                <option value="split_cutoffs">split_cutoffs (split 1st + 2nd cutoff)</option>
                                <option value="cutoff1_only">1st cutoff only</option>
                                <option value="cutoff2_only">2nd cutoff only</option>
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
                            <input type="number" id="wtPercent" value="0" min="0" step="0.01" />
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
                        <input type="file" id="wtImportFile" accept=".csv,.xlsx,.xls" hidden />
                    </div>

                    <div class="importedBar" id="wtImportedWrap" style="display:none;">
                        <div class="importedBar__label">Imported:</div>
                        <div class="importedBar__bar">
                            <div class="importedBar__name" id="wtImportedName"></div>
                            <div class="importedBar__actions">
                                <button class="btn btn--ghost" type="button" id="wtToggleBtn">View</button>
                                <button class="btn btn--ghost" type="button" id="wtClearBtn">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="emptyState" id="wtEmptyState">
                        <div class="emptyState__title">No table loaded</div>
                        <div class="emptyState__text">You can seed a default table later or import a CSV/XLSX.
                        </div>
                    </div>

                    <div class="tablewrap" id="wtPreviewWrap" style="display:none; margin-top:12px;">
                        <table class="table" style="min-width:720px;">
                            <thead id="wtPreviewHead">
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
        <section class="tabPanel" id="tab-cashadvance" role="tabpanel" aria-labelledby="tab-cashadvance-btn" hidden>
            <div class="notice notice--success" id="cashAdvanceNotice" hidden></div>
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
                            <label>Regular default term (months)</label>
                            <input type="number" id="caRegularTermMonths" value="3" min="1" />
                        </div>

                        <div class="field">
                            <label>Probationary term (months)</label>
                            <input type="number" id="caProbationaryTermMonths" value="1" min="1" />
                        </div>

                        <div class="field">
                            <label>Trainee term (months)</label>
                            <input type="number" id="caTraineeTermMonths" value="1" min="1" />
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="caTraineeForceFullDeduct" checked />
                                <span>Trainees are forced to full deduction</span>
                            </label>
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="caAllowFullDeduct" checked />
                                <span>Allow “Full deduct” option for regular employees</span>
                            </label>
                        </div>

                        <div class="field">
                            <label>Max payback term (months) <span class="hint">Maximum months allowed to pay back a cash advance</span></label>
                            <input type="number" id="caMaxPaybackMonths" value="6" min="1" />
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
                            <div class="card__title">Payroll Deduction Rules</div>
                            <div class="muted small">Priority, caps, and carry-forward</div>
                        </div>
                    </div>

                    <div class="notice notice--success" id="payrollDeductionNotice" hidden></div>

                    <div class="grid2">
                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="pdApplyPremiumsNonRegular" />
                                <span>Apply premiums/contributions to probationary/trainees</span>
                            </label>
                            <div class="hint">Default: off (no SSS/PH/PI for non-regular).</div>
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="pdApplyTaxNonRegular" />
                                <span>Apply withholding tax to probationary/trainees</span>
                            </label>
                            <div class="hint">Default: off.</div>
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="pdCapChargesToNet" checked />
                                <span>Cap charges/shortages so net pay won’t go negative</span>
                            </label>
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="pdCarryForwardCharges" checked />
                                <span>Carry forward unpaid charges/shortages to next cutoff</span>
                            </label>
                        </div>

                        <div class="field field--full">
                            <label class="rowLabel">
                                <input type="checkbox" id="pdCapCashAdvanceToNet" checked />
                                <span>Cap cash advance so net pay won’t go negative</span>
                            </label>
                        </div>
                    </div>

                    <div class="actionsRow">
                        <div class="spacer"></div>
                        <button class="btn btn--maroon" type="button" id="payrollDeductionSave">Save Deduction
                            Rules</button>
                    </div>
                </div>

                {{-- Employee Cash Advance Entry (Transactions) moved to Loans page --}}
                {{--
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

                    <div class="notice notice--info" id="caActionBanner" hidden></div>

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
                        <button class="iconbtn" id="closeCaDrawer" type="button" aria-label="Close">✕</button>
                    </div>

                    <form class="form" id="caForm">
                        <div class="grid2">
                            <div class="field">
                                <label>Employee</label>
                                <div class="typeahead" id="caEmployeeTypeahead">
                                    <input type="text" id="caEmployeeInput" placeholder="Type to search employee..."
                                        autocomplete="off" required />
                                    <input type="hidden" id="caEmployeeId" />
                                    <div class="typeahead__list" id="caEmployeeList" hidden></div>
                                </div>
                            </div>

                            <div class="field">
                                <label>Amount</label>
                                <div class="moneyInput moneyInput--inline">
                                    <input type="text" id="caAmount" inputmode="decimal" autocomplete="off"
                                        placeholder="&#8369;0.00" required />
                                    <input type="hidden" id="caAmountValue" />
                                </div>
                            </div>

                            <div class="field">
                                <label>Term (months)</label>
                                <input type="number" id="caTerm" min="1" value="3" required />
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

            <!-- View/Edit Drawer -->
            <aside class="drawer" id="caViewDrawer" aria-hidden="true">
                <div class="drawer__overlay" id="caViewDrawerOverlay"></div>
                <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="caViewDrawerTitle">
                    <div class="drawer__head">
                        <div>
                            <div class="drawer__title" id="caViewDrawerTitle">Cash Advance Details</div>
                            <div class="muted small" id="caViewDrawerSubtitle">View cash advance entry.</div>
                        </div>
                        <button class="iconbtn" id="closeCaViewDrawer" type="button" aria-label="Close">✕</button>
                    </div>

                    <form class="form" id="caViewForm">
                        <div class="grid2">
                            <div class="field">
                                <label>Employee</label>
                                <input type="text" id="caViewEmployee" readonly />
                            </div>
                            <div class="field">
                                <label>Amount</label>
                                <input type="text" id="caViewAmount" readonly />
                            </div>
                            <div class="field">
                                <label>Term (months)</label>
                                <input type="number" id="caViewTerm" readonly />
                            </div>
                            <div class="field">
                                <label>Start month</label>
                                <input type="month" id="caViewStart" readonly />
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <input type="text" id="caViewStatus" readonly />
                            </div>
                        </div>

                        <div class="actionsRow">
                            <button class="btn btn--soft" type="button" id="closeCaViewBtn">Close</button>
                        </div>
                    </form>
                </div>
            </aside>
                --}}
        </section>

        <!-- 11) USER ACCOUNTS -->
        <section class="tabPanel" id="tab-users" role="tabpanel" aria-labelledby="tab-users-btn" hidden>
            <div class="card">
                <div class="card__head">
                    <div>
                        <div class="card__title">Create HR Account</div>
                        <div class="muted small">Admin-only. HR accounts can access Employee Records, Attendance,
                            Loans, and Employee Case Management.</div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="notice notice--success" style="margin-bottom:12px;">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="notice notice--danger" style="margin-bottom:12px;">
                        @foreach ($errors->all() as $e)
                            <div>{{ $e }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.users.hr.store') }}">
                    @csrf
                    <div class="grid2">
                        <div class="field">
                            <label>Username</label>
                            <input name="name" value="{{ old('name') }}" required />
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input name="email" type="email" value="{{ old('email') }}" required />
                        </div>
                        <div class="field">
                            <label>First name</label>
                            <input name="first_name" value="{{ old('first_name') }}" />
                        </div>
                        <div class="field">
                            <label>Middle name</label>
                            <input name="middle_name" value="{{ old('middle_name') }}" />
                        </div>
                        <div class="field field--full">
                            <label>Last name</label>
                            <input name="last_name" value="{{ old('last_name') }}" />
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <input name="password" type="password" autocomplete="new-password" required />
                        </div>
                        <div class="field">
                            <label>Confirm password</label>
                            <input name="password_confirmation" type="password" autocomplete="new-password" required />
                        </div>
                    </div>

                    <div class="actionsRow">
                        <div class="spacer"></div>
                        <button class="btn btn--maroon" type="submit">Create HR Account</button>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-top:14px;">
                <div class="card__head">
                    <div>
                        <div class="card__title">HR Accounts</div>
                        <div class="muted small">Accounts with role = HR.</div>
                    </div>
                </div>

                <div class="tablewrap">
                    <table class="table" aria-label="HR accounts table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full name</th>
                                <th>Email</th>
                                <th>Created by</th>
                                <th>Created</th>
                                <th style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hrUsersTbody"></tbody>
                    </table>
                </div>
            </div>

            <aside class="drawer" id="hrUserDrawer" aria-hidden="true">
                <div class="drawer__overlay" id="hrUserDrawerOverlay"></div>
                <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="hrUserDrawerTitle">
                    <div class="drawer__head">
                        <div>
                            <div class="drawer__title" id="hrUserDrawerTitle">Edit HR Account</div>
                            <div class="muted small">Update username, email, and optional password.</div>
                        </div>
                        <button class="iconbtn" id="closeHrUserDrawer" type="button" aria-label="Close">✕</button>
                    </div>

                    <div class="notice notice--success" id="hrUserNotice" hidden></div>
                    <form class="form" id="hrUserForm">
                        <input type="hidden" id="hrUserId" />
                        <div class="grid2">
                            <div class="field">
                                <label>Username</label>
                                <input id="hrUserName" required />
                            </div>
                            <div class="field">
                                <label>Email</label>
                                <input id="hrUserEmail" type="email" required />
                            </div>
                            <div class="field">
                                <label>First name</label>
                                <input id="hrUserFirst" />
                            </div>
                            <div class="field">
                                <label>Middle name</label>
                                <input id="hrUserMiddle" />
                            </div>
                            <div class="field field--full">
                                <label>Last name</label>
                                <input id="hrUserLast" />
                            </div>
                            <div class="field field--full">
                                <label>New password (optional)</label>
                                <input id="hrUserPassword" type="password" autocomplete="new-password"
                                    placeholder="Leave blank to keep current password" />
                            </div>
                        </div>

                        <div class="actionsRow">
                            <button class="btn btn--soft" type="button" id="cancelHrUserBtn">Cancel</button>
                            <div class="spacer"></div>
                            <button class="btn btn--maroon" type="submit">Save changes</button>
                        </div>
                    </form>
                </div>
            </aside>
        </section>

    </section>


@endsection
