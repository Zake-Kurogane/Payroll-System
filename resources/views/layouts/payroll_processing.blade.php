@extends('layouts.app')

@section('title', 'Payroll Processing')

@section('vite')
    @vite(['resources/css/payroll_processing.css', 'resources/js/payroll_processing.js'])
@endsection

@section('content')
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
                        <div class="filters__topRow">
                        <div class="filters__left">
                            <div class="f">
                                <label>Month</label>
                                <input id="monthInput" type="month" />
                            </div>

                            <div class="f">
                                <label>Cutoff</label>
                                <select id="cutoffSelect">
                                    <option value="11-25">11–25</option>
                                    <option value="26-10">26–10</option>
                                </select>
                            </div>

                            <div class="f f--grow">
                                <label>Search</label>
                                <input id="searchInput" type="search" placeholder="Search employee id or name" />
                            </div>
                        </div>

                        <div class="filters__right"></div>
                        </div>
                        <div class="filters__payoutRow">
                            <div class="filters__payoutRowInner">
                                <div class="seg seg--pill" role="group" aria-label="Assignment filter" id="assignmentSeg"></div>
                                <div class="seg" role="tablist" aria-label="Payout filter">
                                    <button class="seg__btn is-active" type="button" data-pay="All"
                                        aria-selected="true">All</button>
                                    <button class="seg__btn" type="button" data-pay="Cash"
                                        aria-selected="false">Cash</button>
                                    <button class="seg__btn" type="button" data-pay="Bank"
                                        aria-selected="false">Bank</button>
                                </div>
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
                                    <th class="num">Attendance Deduction</th>
                                    <th class="num">Charges</th>
                                    <th class="num">Loans</th>
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
                                    <th></th>
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
                    <button class="btn" type="button" id="payslipBtn" data-payslip-url="{{ route('payslip') }}" disabled>
                        Generate Payslips
                    </button>
                </div>
    </footer>
@endsection

@section('body_end')
    <div class="toast" id="toast" aria-live="polite" aria-atomic="true"></div>

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

    <!-- PASSWORD MODAL -->
    <div class="overlay" id="pwOverlay" hidden></div>
    <div class="pwModal" id="pwModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pwTitle">
        <div class="pwModal__head">
            <div class="pwModal__title" id="pwTitle">Enter Password</div>
        </div>
        <div class="pwModal__body">
            <div class="field">
                <label for="pwInput">Password</label>
                <input id="pwInput" type="password" autocomplete="current-password" />
            </div>
        </div>
        <div class="pwModal__foot">
            <button class="btn" type="button" id="pwCancel">Cancel</button>
            <button class="btn btn--maroon" type="button" id="pwSubmit">Unlock</button>
        </div>
    </div>
@endsection
