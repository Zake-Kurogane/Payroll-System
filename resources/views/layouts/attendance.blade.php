@extends('layouts.app')

@section('title', 'Attendance')

@section('vite')
    @vite(['resources/css/attendance.css', 'resources/js/attendance.js'])
@endsection

@section('content')
    <section class="content">

        <div class="headline headline--withActions">
            <div>
                <h1>ATTENDANCE</h1>
            </div>
        </div>

        <!-- CUTOFF BAR -->
        <section class="card cutoffbar">
            <div class="cutoffbar__left">
                <div class="f">
                    <label>Payroll Month</label>
                    <input id="cutoffMonth" type="month" />
                </div>

                <div class="f">
                    <label>Cutoff</label>
                    <select id="cutoffSelect"></select>
                </div>
                
            </div>
        </section>

        <!-- UPLOAD / IMPORT SECTION -->
        <section class="card importCard">
            <div class="importHead">
                <div></div>
            </div>

            <div class="dropzone" id="dropzone" role="button" tabindex="0" aria-label="Upload attendance file">
                <div class="dropzone__inner">
                    <div class="dropzone__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="dropzone__ico">
                            <path d="M12 3l5 5h-3v6h-4V8H7l5-5z" />
                            <path d="M5 20h14v-4h2v6H3v-6h2v4z" />
                        </svg>
                    </div>

                    <div class="dropzone__text">
                        <div class="dropzone__pill">
                            <div class="dropzone__title" id="dropzoneTitle">
                                <span class="linkLike">Click here</span> to upload your file or drag.
                            </div>
                            <div class="dropzone__sub">Supported format: XLSX (10mb)</div>
                            <div class="dropzone__filename" id="dropzoneFileName" hidden></div>
                        </div>
                    </div>
                </div>

                <input type="file" id="importFile" accept=".xlsx" hidden />
            </div>

            <div class="importFooter">
                <div class="muted small" id="fileNameLabel">No file selected.</div>

                <div class="importFooter__actions">
                    <div class="headline__actions">
                        <button class="btn btn--soft" type="button" id="downloadTemplateBtn">
                            ⬇ Download Template
                        </button>
                    </div>
                    <button class="btn" type="button" id="clearFileBtn" disabled>Clear</button>
                    <button class="btn btn--maroon" type="button" id="previewImportBtn" disabled>Import Excel</button>
                </div>
            </div>
</section>

        <!-- FILTER BAR -->
        <section class="card filterbar">
            <div class="filterbar__left">
                <div class="f">
                    <label>Date</label>
                    <input id="dateInput" type="date" />
                </div>

                <div class="f">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="All" selected>All</option>
                    </select>
                </div>

                <div class="f f--grow">
                    <label>Search</label>
                    <input id="searchInput" type="search" placeholder="Search employee id or name" />
                </div>
            </div>

            <div class="filterbar__right">
                <div class="seg seg--pill" id="assignmentSeg" role="group" aria-label="Assignment filter"></div>
            </div>
        </section>

        <!-- STATS -->
        <section class="stats">
            <button class="stat stat--click" id="statTotalBtn" type="button" aria-label="Preview total records">
                <div class="stat__value" id="statTotal">0</div>
                <div class="stat__label">TOTAL RECORDS</div>
            </button>
            <article class="stat">
                <div class="stat__value" id="statPresent">0</div>
                <div class="stat__label">PRESENT</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="statLate">0</div>
                <div class="stat__label">LATE</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="statAbsent">0</div>
                <div class="stat__label">ABSENT</div>
            </article>
            <article class="stat">
                <div class="stat__value" id="statLeave">0</div>
                <div class="stat__label">LEAVE</div>
            </article>
        </section>

        <!-- TABLE CARD -->
        <section class="card tablecard">
            <div class="tablecard__head">
                <div>
                    <div class="card__title big">Attendance Records</div>
                    <div class="muted small" id="resultsMeta">Showing 0 record(s)</div>
                </div>

                <div class="actionsTop">
                    <div class="bulk" id="bulkBar" aria-hidden="true" style="display:none">
                        <span class="bulk__text"><span id="selectedCount">0</span> selected</span>
                        <button class="btn btn--soft" type="button" id="bulkDeleteBtn">Delete
                            Selected</button>

                        <select id="bulkStatusSelectInline" class="bulkSelect" aria-label="Bulk status">
                            <option value="">Set status...</option>
                        </select>

                        <button class="btn btn--maroon" type="button" id="bulkApplyInline" disabled>Apply</button>
                    </div>

                    <button class="btn btn--soft" type="button" id="openAddBtn">
                        <span class="plus">＋</span> Add
                    </button>
                </div>
            </div>

            <div class="tablewrap">
                <table class="table" aria-label="Attendance table">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input id="checkAll" type="checkbox" aria-label="Select all rows" />
                            </th>
                            <th>Date</th>
                            <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="name">Name <span class="sortIcon" aria-hidden="true"></span>
                            </th>
                            <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="area">Area <span class="sortIcon" aria-hidden="true"></span>
                            </th>
                            <th>Clock In/Out</th>
                            <th>Status</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>

                    <tbody id="attTbody"></tbody>
                </table>
            </div>
            <div class="tableFooter">
                <div class="tableFooter__left" id="attFooterInfo">Page 1 of 1</div>
                <div class="tableFooter__right">
                    <label class="rowsLbl" for="attRowsSelect">Rows:</label>
                    <select class="rowsSelect" id="attRowsSelect">
                        <option>10</option>
                        <option selected>20</option>
                        <option>30</option>
                    </select>
                    <div class="pager">
                        <button class="pagerBtn" type="button" id="attFirst">&#124;&#9664;</button>
                        <button class="pagerBtn" type="button" id="attPrev">&#9664;</button>
                        <div class="pagerMid">
                            <input class="pagerInput" id="attPageInput" type="number" min="1" value="1" />
                            <span id="attPageTotal">/ 1</span>
                        </div>
                        <button class="pagerBtn" type="button" id="attNext">&#9654;</button>
                        <button class="pagerBtn" type="button" id="attLast">&#9654;&#124;</button>
                    </div>
                </div>
            </div>
        </section>

    </section>

@endsection

@section('body_end')
    <!-- DRAWER OVERLAY -->
    <div class="overlay" id="drawerOverlay" hidden></div>

    <!-- MANUAL ADD/EDIT DRAWER -->
    <aside class="drawer" id="drawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="drawerTitle">Add Attendance</div>
                <div class="drawer__sub" id="drawerSub">Fill in the attendance details below.</div>
            </div>
            <button class="iconx" type="button" id="closeDrawerBtn" aria-label="Close drawer">✕</button>
        </div>

        <form id="attForm" class="drawer__body" autocomplete="off" novalidate>

            <div class="sectionTitle">Attendance Details</div>
            <div class="grid2">
                <div class="field">
                    <label>Employee *</label>
                    <small class="att-hint">Select the employee for this record.</small>
                    <select id="f_employee" required></select>
                    <small class="err" id="errEmployee"></small>
                </div>

                <div class="field">
                    <label>Date *</label>
                    <small class="att-hint">Date the attendance applies to.</small>
                    <input id="f_date" type="date" required />
                    <small class="err" id="errDate"></small>
                </div>
            </div>

            <div class="grid2">
                <div class="field">
                    <label>Status *</label>
                    <small class="att-hint">Attendance code (e.g. P, L, A).</small>
                    <select id="f_status" required>
                        <option value="">—</option>
                    </select>
                    <small class="err" id="errStatus"></small>
                </div>

                <div class="field">
                    <label>Assignment Type</label>
                    <small class="att-hint">Auto-filled from employee profile.</small>
                    <select id="f_assignType" required disabled>
                        <option value="">—</option>
                    </select>
                    <small class="err" id="errAssignType"></small>
                </div>
            </div>

            <div id="plBalanceWrap" style="display:none;margin-top:-4px;margin-bottom:4px;">
                <small id="plBalanceInfo" style="font-weight:500;"></small>
            </div>

            <div class="field" id="areaWrap" hidden>
                <label>Area Place</label>
                <small class="att-hint">Required for Field employees.</small>
                <select id="f_areaPlace">
                    <option value="">—</option>
                </select>
                <small id="areaPlaceHint" style="display:none;color:var(--muted,#888);">Resolving area...</small>
                <small class="err" id="errAreaPlace"></small>
            </div>

            <div class="sectionTitle">Time</div>
            <div class="grid2">
                <div class="field">
                    <label>Clock In <span
                            style="font-weight:400;font-size:11px;color:var(--muted)">(optional)</span></label>
                    <input id="f_clockIn" type="time" step="60" />
                </div>
                <div class="field">
                    <label>Clock Out <span
                            style="font-weight:400;font-size:11px;color:var(--muted)">(optional)</span></label>
                    <input id="f_clockOut" type="time" step="60" />
                </div>
            </div>

            <div class="sectionTitle">Notes</div>
            <div class="field">
                <label>Notes <span style="font-weight:400;font-size:11px;color:var(--muted)">(optional)</span></label>
                <small class="att-hint">Any additional remarks for this record.</small>
                <textarea id="f_notes" rows="3" placeholder="Add short note…"></textarea>
            </div>

            <input type="hidden" id="editingId" value="" />
        </form>

        <div class="drawer__foot">
            <button class="btn" type="button" id="cancelBtn">Cancel</button>
            <button class="btn btn--maroon" type="button" id="saveBtn">Save</button>
        </div>
    </aside>

    <!-- PREVIEW IMPORT DRAWER -->
    <div class="overlay" id="previewOverlay" hidden></div>

    <aside class="drawer drawer--wide" id="previewDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Preview Import</div>
                <div class="drawer__sub muted small" id="previewSub">Check errors and conflicts before saving.</div>
            </div>

            <button class="iconx" type="button" id="closePreviewBtn" aria-label="Close preview">✕</button>
        </div>

        <div class="drawer__body kpiPreviewDrawerBody">
            <div class="previewSummary">
                <div class="sumCard">
                    <div class="sumVal" id="sumRows">0</div>
                    <div class="sumLbl">Rows detected</div>
                </div>
                <div class="sumCard">
                    <div class="sumVal" id="sumValid">0</div>
                    <div class="sumLbl">Valid rows</div>
                </div>
                <div class="sumCard sumCard--danger">
                    <div class="sumVal" id="sumErrors">0</div>
                    <div class="sumLbl">Errors</div>
                </div>
                <div class="sumCard sumCard--warn">
                    <div class="sumVal" id="sumConflicts">0</div>
                    <div class="sumLbl">Conflicts</div>
                </div>
            </div>

            <div class="conflictBar" id="conflictBar" hidden>
                <div class="muted small">
                    Conflicts detected (same Employee ID + Date exists).
                    Choose what to do before saving.
                </div>
                <div class="conflictActions">
                    <button class="btn btn--soft" type="button" id="overwriteAllBtn">Overwrite all</button>
                    <button class="btn btn--soft" type="button" id="skipAllBtn">Skip all</button>
                </div>
            </div>

            <details class="details" id="errorsDetails">
                <summary>
                    <span>Errors</span>
                    <span class="badge" id="errorsBadge">0</span>
                </summary>
                <div class="detailsBody" id="errorsList"></div>
            </details>

            <div class="previewControls">
                <div class="toggleGroup" role="tablist" aria-label="Preview rows filter">
                    <button class="toggleBtn is-active" type="button" id="showErrorsOnlyBtn">Errors only</button>
                    <button class="toggleBtn" type="button" id="showAllRowsBtn">All rows</button>
                </div>

                <div class="muted small" id="previewHint">
                    Default is Errors only (easier to fix).
                </div>
            </div>

            <div class="tablewrap tablewrap--preview kpiPreviewTablewrap">
                <table class="table table--preview" aria-label="Preview import table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Assignment</th>
                            <th>Area</th>
                            <th>Clock In/Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="previewTbody"></tbody>
                </table>
            </div>

            <div class="saveBlock" id="saveBlock">
                <div class="muted small" id="saveMessage">—</div>
            </div>
        </div>

        <div class="drawer__foot">
            <button class="btn" type="button" id="closePreviewFooter">Close</button>
            <button class="btn btn--maroon" type="button" id="saveImportBtn" disabled>Save</button>
        </div>
    </aside>

    <!-- KPI QUICK PREVIEW -->
    <div class="overlay" id="kpiPreviewOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="kpiPreviewDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="kpiPreviewTitle">Total Records Preview</div>
                <div class="drawer__sub muted small">Assignment, name, and in/out only.</div>
            </div>
            <button class="iconx" type="button" id="closeKpiPreviewBtn" aria-label="Close preview">✕</button>
        </div>
        <div class="drawer__body kpiPreviewDrawerBody">
            <div class="tablewrap tablewrap--preview kpiPreviewTablewrap">
                <table class="table table--preview" aria-label="Total records preview table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Assignment</th>
                            <th>Name</th>
                            <th>In/Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="kpiPreviewTbody"></tbody>
                </table>
            </div>
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" id="closeKpiPreviewFooter">Close</button>
        </div>
    </aside>

    <!-- EMPLOYEE DETAILS OVERLAY -->
    <div class="overlay" id="empOverlay" hidden></div>

    <!-- EMPLOYEE ATTENDANCE DETAILS DRAWER -->
    <aside class="drawer drawer--wide" id="empDrawer" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="empDrawerTitle">Employee Attendance</div>
                <div class="drawer__sub muted small" id="empDrawerSub">—</div>
            </div>
            <button class="iconx" type="button" id="closeEmpDrawerBtn" aria-label="Close employee drawer">✕</button>
        </div>

        <div class="drawer__body">
            <div class="drawerFilters">
                <div class="f">
                    <label>Month</label>
                    <input id="empCutoffMonth" type="month">
                </div>
                <div class="f">
                    <label>Cut-off</label>
                    <select id="empCutoffSelect"></select>
                </div>
            </div>

            <div class="previewSummary">
                <div class="sumCard">
                    <div class="sumVal" id="empSumTotal">0</div>
                    <div class="sumLbl">Total Records</div>
                </div>
                <div class="sumCard">
                    <div class="sumVal" id="empSumHours">0</div>
                    <div class="sumLbl">Total Hours</div>
                </div>
            </div>

            <div class="tablewrap tablewrap--preview">
                <table class="table table--preview" aria-label="Employee attendance details table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Area</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th class="num">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody id="empTbody"></tbody>
                </table>
            </div>
        </div>

        <div class="drawer__foot">
            <button class="btn" type="button" id="closeEmpDrawerFooter">Close</button>
        </div>
    </aside>


@endsection

