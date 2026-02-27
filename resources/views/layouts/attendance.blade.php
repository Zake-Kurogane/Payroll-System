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

        <!-- ✅ CUTOFF BAR (NEW) -->
        <section class="card cutoffbar">
            <div class="cutoffbar__left">
                <div class="f">
                    <label>Payroll Month</label>
                    <input id="cutoffMonth" type="month" />
                </div>

                <div class="f">
                    <label>Cutoff</label>
                    <select id="cutoffSelect">
                        <option value="11-25">11–25</option>
                        <option value="26-10">26–10</option>
                    </select>
                </div>

                <div class="cutoffInfo">
                    <div class="muted small">Cutoff Range</div>
                    <div class="cutoffRange" id="cutoffRangeLabel">—</div>
                    <div class="muted tiny" id="cutoffHint">Attendance dates must fall inside this range.
                    </div>
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

            <div class="muted small importNote">
                Tip: Use codes like <strong>P</strong>, <strong>L</strong>, <strong>A</strong>,
                <strong>UL</strong>, <strong>PL</strong>, <strong>HD</strong>, <strong>OFF</strong>,
                <strong>HOL</strong>, <strong>LOA</strong>.
                Codes are mapped automatically.
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
                <div class="f f--area" id="areaPlaceFilterWrap" hidden style="display:none;">
                    <label>Area Place</label>
                    <select id="areaPlaceFilter">
                        <option value="All" selected>All</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- STATS -->
        <section class="stats">
            <article class="stat">
                <div class="stat__value" id="statTotal">0</div>
                <div class="stat__label">TOTAL RECORDS</div>
            </article>
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
                            <th class="sortable" data-sort="department">Department <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="area">Area <span class="sortIcon" aria-hidden="true"></span>
                            </th>
                            <th>Clock In/Out</th>
                            <th>OT</th>
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
                <div class="drawer__sub muted small" id="drawerSub">Fill up the details below.</div>
            </div>

            <button class="iconx" type="button" id="closeDrawerBtn" aria-label="Close drawer">✕</button>
        </div>

        <form id="attForm" class="drawer__body" autocomplete="off" novalidate>
            <div class="grid2">
                <div class="field">
                    <label>Employee</label>
                    <select id="f_employee" required></select>
                    <small class="err" id="errEmployee"></small>
                </div>

                <div class="field">
                    <label>Date</label>
                    <input id="f_date" type="date" required />
                    <small class="err" id="errDate"></small>
                </div>
            </div>

            <div class="grid2">
                <div class="field">
                    <label>Status</label>
                    <select id="f_status" required>
                        <option value="">—</option>
                    </select>
                    <small class="err" id="errStatus"></small>
                </div>

                <div class="field">
                    <label>Assignment Type</label>
                    <select id="f_assignType" required disabled>
                        <option value="">—</option>
                        <option>Tagum</option>
                        <option>Davao</option>
                        <option>Area</option>
                    </select>
                    <small class="err" id="errAssignType"></small>
                </div>
            </div>

            <div class="field" id="areaWrap" hidden>
                <label>Area Place</label>
                <select id="f_areaPlace" disabled>
                    <option value="">—</option>
                    <option>Laak</option>
                    <option>Pantukan</option>
                    <option>Maragusan</option>
                </select>
                <small class="err" id="errAreaPlace"></small>
            </div>

            <div class="field">
                <label>Notes (optional)</label>
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

        <div class="drawer__body">
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

            <div class="tablewrap tablewrap--preview">
                <table class="table table--preview" aria-label="Preview import table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Assignment</th>
                            <th>Area</th>
                            <th>Clock In/Out</th>
                            <th>OT</th>
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
                    <select id="empCutoffSelect">
                        <option value="11-25">11–25</option>
                        <option value="26-10">26–10</option>
                    </select>
                </div>
                <div class="cutoffInfo cutoffInfo--mini" id="empCutoffInfo" aria-live="polite">
                    <div class="tiny muted">Range</div>
                    <div class="cutoffRange" id="empCutoffRangeLabel">—</div>
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
                <div class="sumCard sumCard--warn">
                    <div class="sumVal" id="empSumOT">0</div>
                    <div class="sumLbl">Total OT Hours</div>
                </div>
            </div>

            <div class="tablewrap tablewrap--preview">
                <table class="table table--preview" aria-label="Employee attendance details table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th class="num">OT Hours</th>
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
