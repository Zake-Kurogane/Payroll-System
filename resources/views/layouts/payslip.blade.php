@extends('layouts.app')

@section('title', 'Payslips')

@section('vite')
    @vite(['resources/css/payslips.css', 'resources/js/payslips.js'])
@endsection

@section('content')
    <section class="content">

        <!-- PAGE HEADER -->
        <div class="headline headline--withActions">
            <div>
                <h1>PAYSLIPS</h1>
                <div class="muted small">View, print, and export payslips from processed payroll runs.</div>
            </div>

            <div class="headline__actions">
                <button class="btn btn--soft" id="sendEmailBtn" disabled>Send Payslips via Email</button>
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

                <div class="f f--area" id="areaFilterWrap" hidden style="display:none;">
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
                            <option value="" selected>- Select a run -</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="runCard__right">
                <div class="runStats">
                    <div class="miniStat">
                        <div class="miniVal" id="runEmployees">-</div>
                        <div class="miniLbl">Employees</div>
                    </div>
                    <div class="miniStat">
                        <div class="miniVal" id="runTotalNet">-</div>
                        <div class="miniLbl">Total Net</div>
                    </div>
                    <div class="miniStat">
                        <div class="miniVal" id="runProcessedAt">-</div>
                        <div class="miniLbl">Processed</div>
                    </div>
                    <div class="miniStat">
                        <div class="miniVal" id="runProcessedBy">-</div>
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
                                <input id="checkAll" type="checkbox" aria-label="Select all rows" disabled />
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
                            <th class="sortable" data-sort="releaseStatus">Release Status <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="delivery">Delivery <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="payslipTbody"></tbody>
                </table>
            </div>

            <div class="tableFooter">
                <div class="tableFooter__left" id="pageLabel">Page -</div>

                <div class="tableFooter__right">
                    <label class="toolLabel" for="rowsPerPage">Rows:</label>
                    <select id="rowsPerPage" class="toolSelect">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>

                    <div class="pager">
                        <button class="pagerBtn" type="button" id="firstPage"
                            aria-label="First page">&#124;&#9664;</button>
                        <button class="pagerBtn" type="button" id="pagePrev"
                            aria-label="Previous page">&#9664;</button>

                        <div class="pagerMid">
                            <input id="pageInput" class="pagerInput" type="number" min="1" value="1" />
                            <span class="muted small">/</span>
                            <span id="totalPages" class="small">1</span>
                        </div>

                        <button class="pagerBtn" type="button" id="pageNext" aria-label="Next page">&#9654;</button>
                        <button class="pagerBtn" type="button" id="lastPage"
                            aria-label="Last page">&#9654;&#124;</button>
                    </div>
                </div>
            </div>

        </section>

    </section>

@endsection

@section('body_end')
    <!-- PAYSLIP PREVIEW OVERLAY -->
    <div class="overlay" id="psOverlay" hidden></div>

    <!-- PAYSLIP PREVIEW DRAWER -->
    <aside class="drawer drawer--wide" id="psDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Payslip Preview</div>
                <div class="drawer__sub muted small" id="psDrawerMeta">-</div>
            </div>

            <div class="drawer__headActions">
                <button class="btn btn--soft" type="button" id="psDownloadBtn">Download PDF</button>
                <button class="btn btn--soft" type="button" id="psPrintBtn">Print</button>
                <button class="btn btn--maroon" type="button" id="psReleaseBtn">Mark Released</button>
            </div>
        </div>

        <div class="drawer__body">
            <div class="payslipPaper paystub" id="psPaper">
                <div class="psHead">
                    <div class="psHeadLeft">
                        <div class="psCompany" id="psCompanyName">-</div>
                        <div class="psCompanySub" id="psCompanySub">-</div>
                    </div>
                    <div class="psHeadCenter">PAYSLIP</div>
                    <div class="psHeadRight">
                        <div class="psMetaLine"><span>PAYSLIP No</span><strong id="psNo">-</strong></div>
                        <div class="psMetaLine"><span>Date</span><strong id="psGenerated">-</strong></div>
                    </div>
                </div>

                <div class="psInfoGrid">
                    <div class="psInfoCol">
                        <div class="psInfoTitle">EMPLOYEE</div>
                        <div class="psInfoRow"><span>Name</span><strong id="psEmpName">-</strong></div>
                        <div class="psInfoRow"><span>Employee ID</span><strong id="psEmpId">-</strong></div>
                        <div class="psInfoRow"><span>Department</span><strong id="psDept">-</strong></div>
                        <div class="psInfoRow"><span>Position</span><strong id="psPos">-</strong></div>
                        <div class="psInfoRow"><span>Type</span><strong id="psType">-</strong></div>
                        <div class="psInfoRow"><span>Assignment</span><strong id="psAssign">-</strong></div>
                    </div>

                    <div class="psInfoCol">
                        <div class="psInfoTitle">PAY PERIOD</div>
                        <div class="psInfoRow"><span>Payroll Month</span><strong id="psMonth">-</strong></div>
                        <div class="psInfoRow"><span>Cutoff</span><strong id="psCutoff">-</strong></div>
                        <div class="psInfoRow"><span>Pay Date</span><strong id="psPayDate">-</strong></div>
                        <div class="psInfoRow"><span>Pay Method</span><strong id="psPayMethod">-</strong></div>
                        <div class="psInfoRow" id="psBankRow"><span>Bank</span><strong id="psBank">-</strong></div>
                        <div class="psInfoRow" id="psAccountRow"><span>Account</span><strong id="psAccount">-</strong>
                        </div>
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
                            <td>Daily Rate</td>
                            <td class="num" id="psDailyRate">&#8369; 0.00</td>
                            <td class="num" id="psDailyDays">0.00</td>
                            <td class="num" id="psBasicPay">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>Allowance (cutoff portion)</td>
                            <td class="num">-</td>
                            <td class="num">-</td>
                            <td class="num" id="psAllowancePay">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>Overtime</td>
                            <td class="num" id="psOtRate">&#8369; 0.00</td>
                            <td class="num" id="psOtHours">0.00</td>
                            <td class="num" id="psOtPay">&#8369; 0.00</td>
                        </tr>

                        <!-- - Earnings adjustments injected here -->
                    <tbody id="psEarnAdjRows"></tbody>

                    <tr class="psTotalRow">
                        <td colspan="2">TOTAL GROSS EARNINGS</td>
                        <td class="num" id="psGross" colspan="2">&#8369; 0.00</td>
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
                            <td class="num">-</td>
                            <td class="num" id="psAttDedTotal">&#8369; 0.00</td>
                        </tr>

                        <tr>
                            <td>SSS (EE)</td>
                            <td class="num">-</td>
                            <td class="num" id="psSssEe">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>PhilHealth (EE)</td>
                            <td class="num">-</td>
                            <td class="num" id="psPhEe">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>Pag-IBIG (EE)</td>
                            <td class="num">-</td>
                            <td class="num" id="psPiEe">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>Withholding Tax</td>
                            <td class="num">-</td>
                            <td class="num" id="psTax">&#8369; 0.00</td>
                        </tr>
                        <tr>
                            <td>Total Statutory + Tax (EE)</td>
                            <td class="num">-</td>
                            <td class="num" id="psStatEeTotal">&#8369; 0.00</td>
                        </tr>

                        <tr>
                            <td>Cash Advance</td>
                            <td class="num">-</td>
                            <td class="num" id="psCashAdv">&#8369; 0.00</td>
                        </tr>

                        <tr>
                            <td>Other Deductions (manual/recurring)</td>
                            <td class="num">-</td>
                            <td class="num" id="psOtherDedTotal">&#8369; 0.00</td>
                        </tr>

                        <!-- - Deductions adjustments injected here -->
                    <tbody id="psDedAdjRows"></tbody>

                    <tr class="psTotalRow">
                        <td colspan="2">TOTAL DEDUCTIONS</td>
                        <td class="num" id="psDedTotal" colspan="2">&#8369; 0.00</td>
                    </tr>
                    </tbody>
                </table>

                <div class="psSummaryGrid">
                    <div class="psSummaryBox">
                        <div class="psSummaryLine"><span>Gross Pay</span><strong id="psSumGross">&#8369; 0.00</strong>
                        </div>
                        <div class="psSummaryLine"><span>Total Deductions</span><strong id="psSumDed">&#8369;
                                0.00</strong>
                        </div>
                    </div>
                    <div class="psSummaryNet">
                        <div class="psSummaryLabel">NET PAY</div>
                        <div class="psSummaryValue" id="psNet">&#8369; 0.00</div>
                    </div>
                </div>

                <div class="psNotes">
                    <div class="psNotesLabel">Notes / Remarks</div>
                    <div class="psNotesBox" id="psNotes">Adjust Notes: -</div>
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
@endsection
