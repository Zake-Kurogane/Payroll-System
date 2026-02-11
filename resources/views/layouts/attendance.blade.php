<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Attendance</title>

    @vite(['resources/css/attendance.css', 'resources/js/attendance.js'])
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

                <div class="headline headline--withActions">
                    <div>
                        <h1>ATTENDANCE</h1>
                    </div>
                </div>

                <!-- UPLOAD / IMPORT SECTION -->
                <!-- UPLOAD / IMPORT SECTION (Dropzone style) -->
                <section class="card importCard">
                    <div class="importHead">
                        <div>
                        </div>
                    </div>

                    <!-- Dropzone -->
                    <div class="dropzone" id="dropzone" role="button" tabindex="0"
                        aria-label="Upload attendance file">
                        <div class="dropzone__inner">
                            <div class="dropzone__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="dropzone__ico">
                                    <path d="M12 3l5 5h-3v6h-4V8H7l5-5z" />
                                    <path d="M5 20h14v-4h2v6H3v-6h2v4z" />
                                </svg>
                            </div>

                            <div class="dropzone__text">
                                <div class="dropzone__pill">
                                    <div class="dropzone__title">
                                        <span class="linkLike">Click here</span> to upload your file or drag.
                                    </div>
                                    <div class="dropzone__sub">Supported format: CSV, XLSX (10mb each)</div>
                                </div>
                            </div>
                        </div>

                        <input type="file" id="importFile" accept=".csv,.xlsx" hidden />
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
                            <button class="btn btn--maroon" type="button" id="previewImportBtn" disabled
                                style="display:none;">Preview Import</button>
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
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Leave">Leave</option>
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
                            <!-- BULK BAR (INLINE) -->
                            <div class="bulk" id="bulkBar" aria-hidden="true" style="display:none">
                                <span class="bulk__text"><span id="selectedCount">0</span> selected</span>
                                <button class="btn btn--soft" type="button" id="bulkDeleteBtn">Delete
                                    Selected</button>

                                <select id="bulkStatusSelectInline" class="bulkSelect" aria-label="Bulk status">
                                    <option value="">Set status…</option>
                                    <option value="Present">Present</option>
                                    <option value="Late">Late</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Leave">Leave</option>
                                </select>

                                <button class="btn btn--maroon" type="button" id="bulkApplyInline"
                                    disabled>Apply</button>
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
                                    <th class="sortable" data-sort="name">Name <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="department">Department <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th>Area</th>
                                    <th>Clock In/Out</th>
                                    <th>OT</th>
                                    <th>Status</th>
                                    <th class="col-actions">Action</th>
                                </tr>
                            </thead>

                            <tbody id="attTbody">
                                <!-- rows injected by JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="tableFooter">
                        <div class="tableFooter__left" id="attFooterInfo">Page 1 of 1</div>
                        <div class="tableFooter__right">
                            <label class="rowsLbl" for="attRowsSelect">Rows:</label>
                            <select class="rowsSelect" id="attRowsSelect">
                                <option>10</option>
                                <option selected>20</option>
                                <option>50</option>
                            </select>
                            <div class="pager">
                                <button class="pagerBtn" type="button" id="attFirst">&#124;&#9664;</button>
                                <button class="pagerBtn" type="button" id="attPrev">&#9664;</button>
                                <div class="pagerMid">
                                    <input class="pagerInput" id="attPageInput" type="number" min="1"
                                        value="1" />
                                    <span id="attPageTotal">/ 1</span>
                                </div>
                                <button class="pagerBtn" type="button" id="attNext">&#9654;</button>
                                <button class="pagerBtn" type="button" id="attLast">&#9654;&#124;</button>
                            </div>
                        </div>
                    </div>
                </section>

            </section>
        </main>
    </div>

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
                        <option>Present</option>
                        <option>Late</option>
                        <option>Absent</option>
                        <option>Leave</option>
                    </select>
                    <small class="err" id="errStatus"></small>
                </div>

                <div class="field">
                    <label>Assignment Type</label>
                    <select id="f_assignType" required>
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
                <select id="f_areaPlace">
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
            <!-- Summary -->
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

            <!-- Conflicts actions -->
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

            <!-- Errors list -->
            <details class="details" id="errorsDetails">
                <summary>
                    <span>Errors</span>
                    <span class="badge" id="errorsBadge">0</span>
                </summary>
                <div class="detailsBody" id="errorsList">
                    <!-- injected -->
                </div>
            </details>

            <!-- Table header controls -->
            <div class="previewControls">
                <div class="toggleGroup" role="tablist" aria-label="Preview rows filter">
                    <button class="toggleBtn is-active" type="button" id="showErrorsOnlyBtn">Errors only</button>
                    <button class="toggleBtn" type="button" id="showAllRowsBtn">All rows</button>
                </div>

                <div class="muted small" id="previewHint">
                    Default is Errors only (easier to fix).
                </div>
            </div>

            <!-- Preview table -->
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
                    <tbody id="previewTbody">
                        <!-- injected -->
                    </tbody>
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
            <button class="iconx" type="button" id="closeEmpDrawerBtn"
                aria-label="Close employee drawer">✕</button>
        </div>

        <div class="drawer__body">
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
                <div class="sumCard sumCard--danger">
                    <div class="sumVal" id="empSumAbsent">0</div>
                    <div class="sumLbl">Absent</div>
                </div>
            </div>

            <div class="tablewrap tablewrap--preview">
                <table class="table table--preview" aria-label="Employee attendance details table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th class="num">Total Hours</th>
                            <th class="num">OT Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="empTbody">
                        <!-- injected -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="drawer__foot">
            <button class="btn" type="button" id="closeEmpDrawerFooter">Close</button>
        </div>
    </aside>

</body>

</html>
