@extends('layouts.app')

@section('title', 'Employee Loans')

@section('vite')
    @vite(['resources/css/payroll_processing.css', 'resources/css/loans.css', 'resources/js/loans.js'])
@endsection

@section('content')
@php
    $assignments = $assignments ?? collect();
    $groupedAreaPlaces = $groupedAreaPlaces ?? [];
@endphp
<script>
    window.__assignments = @json($assignments);
    window.__areaPlaces = @json($groupedAreaPlaces);
</script>
<section class="content">
    <div class="headline headline--withActions">
        <div>
            <h1>EMPLOYEE LOANS</h1>
            <div class="muted small">Track approved loans, deduction setup, and balances across payroll runs.</div>
        </div>
        <div class="headline__actions">
            <button class="btn btn--maroon" id="openAddLoanBtn" type="button">Add Loan</button>
        </div>
    </div>

    <section class="card filterbar">
        <div class="filterbar__left">
            <div class="f f--search">
                <label>Search</label>
                <input id="loanSearch" type="search" placeholder="Loan no, employee name, or ID" />
            </div>
            <div class="f f--status">
                <label>Status</label>
                <select id="loanStatusFilter">
                    <option value="All">All</option>
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="f f--startmonth">
                <label>Start Month</label>
                <input id="loanStartFilter" type="month" />
            </div>
            <div class="f f--assign-group">
                <label>Assignment</label>
                <div class="seg seg--pill" id="loanAssignSeg" role="group" aria-label="Filter by assignment">
                    <button type="button" class="seg__btn seg__btn--emp is-active" data-assign="">All</button>
                    @foreach ($assignments as $a)
                        <div class="seg__btn-wrap">
                            <button type="button" class="seg__btn seg__btn--emp" data-assign="{{ $a }}">
                                {{ $a }}
                                @if (!empty($groupedAreaPlaces[$a] ?? []))
                                    <span class="seg__chevron">▾</span>
                                @endif
                            </button>
                            @if (!empty($groupedAreaPlaces[$a] ?? []))
                                <div class="seg__dropdown" data-group="{{ $a }}" style="display:none;">
                                    @foreach ($groupedAreaPlaces[$a] as $ap)
                                        <button type="button" class="seg__dropdown-item"
                                            data-place="{{ $ap }}">{{ $ap }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <input type="hidden" id="loanAssignFilter" value="All" />
            </div>
            <div class="f f--loantype">
                <label>Loan Type</label>
                <select id="loanTypeFilter">
                    <option value="All">All</option>
                    <option value="SSS Salary Loan">SSS Salary Loan</option>
                    <option value="SSS Calamity Loan">SSS Calamity Loan</option>
                    <option value="SSS Housing Loan">SSS Housing Loan</option>
                    <option value="HDMF MPL">HDMF MPL</option>
                    <option value="HDMF Calamity Loan">HDMF Calamity Loan</option>
                    <option value="HDMF Housing Loan">HDMF Housing Loan</option>
                    <option value="Company Loan">Company Loan</option>
                    <option value="Cash Advance">Cash Advance</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
    </section>

    <section class="card tablecard">
        <div class="tablecard__head">
            <div>
                <div class="card__title big">Loans List</div>
                <div class="muted small" id="loanMeta">Loading loans...</div>
            </div>
        </div>
        <div class="tablewrap">
            <table class="table" aria-label="Loans table">
                <thead>
                    <tr>
                        <th>Loan No.</th>
                        <th>Employee</th>
                        <th>Loan Type</th>
                        <th class="num">Principal</th>
                        <th class="num">Total Payable</th>
                        <th class="num">Per Cutoff</th>
                        <th class="num">Balance Remaining</th>
                        <th>Start</th>
                        <th>End (Est.)</th>
                        <th>Status</th>
                        <th>Payroll Deduction</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="loanTbody"></tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Employee Cash Advance Entry</div>
                <div class="muted small">Transactions</div>
            </div>
            <div>
                <button class="btn btn--maroon" type="button" id="newCaBtn">+ New Cash Advance</button>
            </div>
        </div>

        <div class="notice notice--success" id="cashAdvanceTxnNotice" hidden></div>
        <div class="notice notice--info" id="caActionBanner" hidden></div>

        <div class="tablewrap">
            <table class="table" aria-label="Cash advances table">
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
    </section>
</section>
@endsection

@section('body_end')
    <div class="toast" id="toast" aria-live="polite" aria-atomic="true"></div>

    <div class="overlay" id="loanDrawerOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="loanDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="loanDrawerTitle">Add Loan</div>
                <div class="drawer__sub muted small" id="loanDrawerSub">Encode the approved loan details.</div>
            </div>
            <button class="iconx" type="button" id="closeLoanDrawer">✕</button>
        </div>
        <div class="drawer__body">
            <div class="sectionTitle">Employee</div>
            <div class="grid2">
                <div class="field field--full">
                    <label>Employee Search</label>
                    <input id="loanEmpSearch" type="search" placeholder="Search by ID or name" autocomplete="off" />
                    <div id="loanEmpSuggest" class="suggest" hidden></div>
                </div>
                <div class="field">
                    <label>Employee ID</label>
                    <input id="loanEmpNo" type="text" readonly />
                </div>
                <div class="field field--full">
                    <label>Employee Name</label>
                    <input id="loanEmpName" type="text" readonly />
                </div>
                <div class="field">
                    <label>Assignment</label>
                    <input id="loanEmpAssign" type="text" readonly />
                </div>
                <div class="field">
                    <label>Department</label>
                    <input id="loanEmpDept" type="text" readonly />
                </div>
                <div class="field">
                    <label>Position</label>
                    <input id="loanEmpPos" type="text" readonly />
                </div>
            </div>

            <div class="sectionTitle">Loan Info</div>
            <div class="grid2">
                <div class="field">
                    <label>Loan Type</label>
                    <select id="loanType">
                        <option value="SSS Salary Loan">SSS Salary Loan</option>
                        <option value="SSS Calamity Loan">SSS Calamity Loan</option>
                        <option value="SSS Housing Loan">SSS Housing Loan</option>
                        <option value="HDMF MPL">HDMF MPL</option>
                        <option value="HDMF Calamity Loan">HDMF Calamity Loan</option>
                        <option value="HDMF Housing Loan">HDMF Housing Loan</option>
                        <option value="Company Loan">Company Loan</option>
                        <option value="Cash Advance">Cash Advance</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="field">
                    <label>Lender</label>
                    <select id="loanLender">
                        <option value="SSS">SSS</option>
                        <option value="HDMF">HDMF</option>
                        <option value="Company">Company</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="field">
                    <label>Reference No.</label>
                    <input id="loanRef" type="text" />
                </div>
                <div class="field">
                    <label>Approval Date</label>
                    <input id="loanApprovalDate" type="date" />
                </div>
                <div class="field">
                    <label>Release Date</label>
                    <input id="loanReleaseDate" type="date" />
                </div>
                <div class="field">
                    <label>Principal Amount</label>
                    <input id="loanPrincipal" type="number" min="0" step="0.01" />
                </div>
                <div class="field">
                    <label>Interest Amount</label>
                    <input id="loanInterest" type="number" min="0" step="0.01" />
                </div>
                <div class="field">
                    <label>Total Payable</label>
                    <input id="loanTotalPayable" type="number" min="0" step="0.01" />
                </div>
                <div class="field field--full">
                    <label>Notes / Remarks</label>
                    <input id="loanNotes" type="text" />
                </div>
            </div>

            <div class="sectionTitle">Deduction Setup</div>
            <div class="grid2">
                <div class="field">
                    <label>Deduction Start Month</label>
                    <input id="loanStartMonth" type="month" />
                </div>
                <div class="field">
                    <label>Start Cutoff</label>
                    <select id="loanStartCutoff">
                        <option value="11-25">11–25</option>
                        <option value="26-10">26–10</option>
                    </select>
                </div>
                <div class="field">
                    <label>Deduction Frequency</label>
                    <select id="loanFrequency">
                        <option value="every_cutoff">Every cutoff</option>
                        <option value="cutoff1_only">1st cutoff only</option>
                        <option value="cutoff2_only">2nd cutoff only</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="field">
                    <label>Deduction Method</label>
                    <select id="loanMethod">
                        <option value="fixed_months">Fixed number of months</option>
                        <option value="fixed_amount">Fixed amount</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="field">
                    <label>Monthly Amortization</label>
                    <input id="loanMonthly" type="number" min="0" step="0.01" />
                </div>
                <div class="field">
                    <label>Per Cutoff Amount</label>
                    <input id="loanPerCutoff" type="number" min="0" step="0.01" />
                </div>
                <div class="field">
                    <label>Number of Months</label>
                    <input id="loanTerm" type="number" min="1" step="1" />
                </div>
            </div>

            <div class="sectionTitle">Control Settings</div>
            <div class="grid2">
                <div class="field field--switch">
                    <label>Auto-deduct in payroll?</label>
                    <label class="switch">
                        <input type="checkbox" id="loanAutoDeduct" checked />
                        <span class="switch__ui"></span>
                    </label>
                </div>
                <div class="field field--switch">
                    <label>Allow partial deduction?</label>
                    <label class="switch">
                        <input type="checkbox" id="loanAllowPartial" checked />
                        <span class="switch__ui"></span>
                    </label>
                </div>
                <div class="field field--switch">
                    <label>Carry forward unpaid?</label>
                    <label class="switch">
                        <input type="checkbox" id="loanCarryForward" checked />
                        <span class="switch__ui"></span>
                    </label>
                </div>
                <div class="field field--switch">
                    <label>Stop when balance reaches zero?</label>
                    <label class="switch">
                        <input type="checkbox" id="loanStopOnZero" checked />
                        <span class="switch__ui"></span>
                    </label>
                </div>
                <div class="field">
                    <label>Priority Order</label>
                    <input id="loanPriority" type="number" min="1" step="1" value="100" />
                </div>
                <div class="field">
                    <label>Status</label>
                    <select id="loanStatus">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="field">
                    <label>Source</label>
                    <select id="loanSource">
                        <option value="Agency">Agency</option>
                        <option value="Company">Company</option>
                        <option value="Government">Government</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="field">
                    <label>Recovery Type</label>
                    <select id="loanRecovery">
                        <option value="Payroll deduction">Payroll deduction</option>
                        <option value="Manual payment">Manual payment</option>
                        <option value="Mixed">Mixed</option>
                    </select>
                </div>
                <div class="field">
                    <label>Approved By</label>
                    <input id="loanApprovedBy" type="text" />
                </div>
                <div class="field">
                    <label>Encoded By</label>
                    <input id="loanEncodedBy" type="text" />
                </div>
            </div>
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" id="cancelLoanBtn">Cancel</button>
            <button class="btn btn--maroon" type="button" id="saveLoanBtn">Save Loan</button>
        </div>
    </aside>

    <div class="overlay" id="loanDetailOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="loanDetailDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="loanDetailTitle">Loan Details</div>
                <div class="drawer__sub muted small" id="loanDetailSub">Loan summary and deduction history.</div>
            </div>
            <button class="iconx" type="button" id="closeLoanDetail">✕</button>
        </div>
        <div class="drawer__body">
            <div class="sectionTitle">Summary</div>
            <div class="summaryLines" id="loanDetailSummary"></div>

            <div class="sectionTitle">Deduction History</div>
            <div class="tablewrap">
                <table class="table" aria-label="Loan history">
                    <thead>
                        <tr>
                            <th>Payroll Run</th>
                            <th>Period</th>
                            <th class="num">Scheduled</th>
                            <th class="num">Deducted</th>
                            <th class="num">Balance After</th>
                            <th>Status</th>
                            <th>Posted At</th>
                        </tr>
                    </thead>
                    <tbody id="loanHistoryTbody"></tbody>
                </table>
            </div>
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" id="closeLoanDetailFooter">Close</button>
        </div>
    </aside>

    <!-- Cash Advance Drawer -->
    <aside class="drawer" id="caDrawer" aria-hidden="true">
        <div class="drawer__overlay" id="caDrawerOverlay"></div>
        <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="caDrawerTitle">
            <div class="drawer__head">
                <div>
                    <div class="drawer__title" id="caDrawerTitle">New Cash Advance</div>
                    <div class="muted small">Create a cash advance entry.</div>
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

    <!-- Cash Advance View Drawer -->
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
@endsection
