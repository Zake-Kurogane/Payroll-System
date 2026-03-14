@extends('layouts.app')

@section('title', 'Employee Case Management')

@section('vite')
    @vite(['resources/css/emp_records.css', 'resources/js/employee_cases.js'])
@endsection

@section('content')
    <style>
        .statsRow--solid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        .stat--solid {
            background: var(--maroon);
            color: #fff;
            border-radius: 16px;
            padding: 14px 12px;
            text-align: center;
            box-shadow: 0 6px 12px rgba(156, 29, 60, 0.22);
        }
        .stat--solid .stat__value {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.1;
        }
        .stat--solid .stat__label {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.6px;
            margin-top: 2px;
        }

        /* Drawer — matches attendance style */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.35);
            backdrop-filter: blur(2px);
            z-index: 980;
        }
        .overlay[hidden] { display: none; }

        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            bottom: auto;
            width: min(520px, 92vw);
            height: 100vh;
            background: #fff;
            border-left: 1px solid var(--line);
            z-index: 990;
            transform: translateX(100%);
            /* visibility delays on close so slide-out animation plays fully */
            visibility: hidden;
            transition: transform 0.22s ease, visibility 0s linear 0.22s;
            display: flex;
            flex-direction: column;
            pointer-events: none;
        }
        .drawer--wide {
            width: min(720px, 96vw);
        }
        .drawer.is-open {
            transform: translateX(0);
            visibility: visible;
            pointer-events: auto;
            /* visibility becomes visible immediately on open */
            transition: transform 0.22s ease, visibility 0s linear 0s;
        }

        /* Drawer internals */
        .drawer__head {
            padding: 16px 16px 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            flex-shrink: 0;
        }
        .drawer__title {
            font-weight: 1000;
            color: var(--maroon);
            font-size: 18px;
        }
        .drawer__sub {
            margin-top: 2px;
            font-size: 12px;
            color: var(--muted);
        }
        .drawer__body {
            padding: 14px 16px;
            overflow-y: auto;
            display: grid;
            gap: 12px;
            flex: 1;
            min-height: 0;
        }
        .drawer__foot {
            margin-top: auto;
            padding: 12px 16px 16px;
            border-top: 1px solid var(--line);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-shrink: 0;
        }
        .iconx {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            font-weight: 1000;
        }
        .sectionTitle {
            font-weight: 900;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: var(--maroon);
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* New Case form helpers */
        .nc-hint {
            font-size: 11px;
            color: var(--muted);
            margin: 0;
        }
        .nc-opt {
            font-weight: 400;
            font-size: 11px;
            color: var(--muted);
        }
        /* Witness tag input */
        .nc-addRow {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .nc-addRow input {
            flex: 1;
        }
        .nc-addBtn {
            padding: 0 12px;
            height: 42px;
            font-size: 18px;
            line-height: 1;
            flex-shrink: 0;
        }
        .nc-tagWrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-height: 0;
        }
        .nc-tagWrap:not(:empty) {
            margin-bottom: 6px;
        }
        .nc-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(156, 29, 60, 0.08);
            color: var(--maroon);
            border-radius: 20px;
            padding: 3px 10px 3px 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .nc-tag__remove {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--maroon);
            font-size: 14px;
            line-height: 1;
            padding: 0;
        }
        /* View case drawer */
        .vc-section { display: grid; gap: 6px; }
        .vc-row { display: flex; gap: 8px; align-items: baseline; font-size: 13px; }
        .vc-label { font-weight: 800; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; min-width: 110px; flex-shrink: 0; }
        .vc-val { color: #1a1a1a; }
        .vc-list { display: flex; flex-direction: column; gap: 4px; }
        .vc-chip { display: inline-flex; align-items: center; gap: 5px; background: rgba(156,29,60,0.08); color: var(--maroon); border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 700; }
        .vc-chip--neutral { background: rgba(0,0,0,0.06); color: #444; }
        .vc-empty { font-size: 12px; color: var(--muted); }
        .vc-desc { font-size: 13px; line-height: 1.6; white-space: pre-wrap; background: rgba(0,0,0,0.03); border-radius: 10px; padding: 10px 12px; }
        .vc-sanction { display: flex; gap: 6px; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--line); }
        .vc-sanction:last-child { border-bottom: none; }
        /* Stage badge colours */
        .badge--reported    { color: #1d4ed8; background: rgba(59,130,246,0.10); border-color: rgba(59,130,246,0.28); }
        .badge--nte         { color: #92400e; background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.30); }
        .badge--hearing     { color: #5b21b6; background: rgba(139,92,246,0.10); border-color: rgba(139,92,246,0.28); }
        .badge--decision    { color: #854d0e; background: rgba(234,179,8,0.12);  border-color: rgba(234,179,8,0.30);  }
        .badge--decided     { color: #166534; background: rgba(34,197,94,0.10);  border-color: rgba(34,197,94,0.28);  }
        .badge--closed      { color: #374151; background: rgba(107,114,128,0.10);border-color: rgba(107,114,128,0.25);}

        /* Eye icon button */
        .btn--icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--line); background: #fff; cursor: pointer; color: var(--maroon); }
        .btn--icon:hover { background: rgba(156,29,60,0.07); }
        .btn--icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* Employee search autocomplete */
        .nc-search { position: relative; }
        .nc-search input[type="text"] { width: 100%; }
        .nc-suggestions {
            position: fixed;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.10);
            z-index: 2000;
            margin: 0;
            padding: 4px 0;
            list-style: none;
            max-height: 220px;
            overflow-y: auto;
        }
        .nc-sugg-item {
            padding: 8px 14px;
            font-size: 13px;
            cursor: pointer;
        }
        .nc-sugg-item:hover, .nc-sugg-item.is-active {
            background: rgba(156,29,60,0.07);
            color: var(--maroon);
        }
        .nc-sugg-empty {
            padding: 8px 14px;
            font-size: 12px;
            color: var(--muted);
        }
        .grid2__full { grid-column: 1 / -1; }

        /* Dropzone */
        .nc-dropzone {
            border: 2px dashed var(--line);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.15s;
        }
        .nc-dropzone.drag-over {
            border-color: var(--maroon);
            background: rgba(156, 29, 60, 0.04);
        }
        .nc-dropzone__inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 20px 16px;
            cursor: pointer;
        }
        .nc-dropzone__icon { font-size: 24px; }
        .nc-dropzone__label {
            font-weight: 800;
            font-size: 13px;
            color: var(--maroon);
        }
        .nc-dropzone__hint {
            font-size: 11px;
            color: var(--muted);
        }
        .nc-fileList {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .nc-fileList:not(:empty) {
            border-top: 1px solid var(--line);
        }
        .nc-fileItem {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            border-bottom: 1px solid var(--line);
        }
        .nc-fileItem:last-child { border-bottom: none; }
        .nc-fileItem__remove {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            font-size: 14px;
            padding: 0 2px;
        }
        .nc-fileItem__remove:hover { color: var(--maroon); }
    </style>
    <section class="content">
        <div class="headline">
            <div>
                <h1>EMPLOYEE CASE MANAGEMENT</h1>
                <p class="muted">Track incident reports, NTEs, hearings, decisions, sanctions, and tardiness summaries.</p>
            </div>
            <div class="headline__actions">
                <button class="btn btn--maroon" type="button" id="newCaseBtn">+ NEW CASE</button>
                <button class="btn btn--soft" type="button" id="exportCasesBtn">EXPORT CASES</button>
                <button class="btn btn--soft" type="button" id="uploadDocBtn">UPLOAD DOCUMENT</button>
                <button class="btn btn--soft" type="button" id="historyBtn">VIEW EMPLOYEE HISTORY</button>
            </div>
        </div>

        <input type="file" id="uploadDocInput" accept=".pdf,.doc,.docx" hidden />

        <section class="card filtersCard" id="filtersCard">
            <div class="filtersRow">
                <div class="field field--grow">
                    <label class="field__label">Search</label>
                    <input id="searchInput" class="field__control" type="search" placeholder="Employee name, ID, or case no." />
                </div>
                <div class="field">
                    <label class="field__label">Stage</label>
                    <select id="statusFilter" class="field__control">
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">Sanction</label>
                    <select id="sanctionFilter" class="field__control">
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">Month</label>
                    <input id="monthFilter" class="field__control" type="month" />
                </div>
            </div>
        </section>

        <section class="card statsCard" id="summaryCards">
            <div class="statsRow statsRow--solid">
                <div class="stat stat--pill stat--solid">
                    <div class="stat__value" id="statTotal">0</div>
                    <div class="stat__label">TOTAL CASES</div>
                </div>
                <div class="stat stat--pill stat--solid">
                    <div class="stat__value" id="statOpen">0</div>
                    <div class="stat__label">OPEN CASES</div>
                </div>
                <div class="stat stat--pill stat--solid">
                    <div class="stat__value" id="statHearing">0</div>
                    <div class="stat__label">FOR HEARING</div>
                </div>
                <div class="stat stat--pill stat--solid">
                    <div class="stat__value" id="statDecision">0</div>
                    <div class="stat__label">FOR DECISION</div>
                </div>
                <div class="stat stat--pill stat--solid">
                    <div class="stat__value" id="statSanctions">0</div>
                    <div class="stat__label">ACTIVE SANCTIONS</div>
                </div>
            </div>
        </section>

        <section class="card tablecard">
            <div class="tablecard__head">
                <div>
                    <div class="card__title big">Cases</div>
                    <div class="muted small" id="resultsMeta">Showing 0 case(s)</div>
                </div>
            </div>

            <div class="tablewrap">
                <table class="table" id="caseTable">
                    <thead>
                        <tr>
                            <th>Case No</th>
                            <th>Date Reported</th>
                            <th>Respondent(s)</th>
                            <th>Complainant(s)</th>
                            <th>Type</th>
                            <th>Stage</th>
                            <th>Sanction</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="caseTbody">
                        <tr>
                            <td colspan="8" class="muted small">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <div class="toast" id="toast" aria-live="polite" aria-atomic="true"></div>

    <!-- New Case Drawer -->
    <div class="overlay" id="newCaseOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="newCaseModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">New Case</div>
                <div class="drawer__sub">Create a new incident or spot report case.</div>
            </div>
            <button class="iconx" type="button" data-close-modal="newCaseModal">✕</button>
        </div>
        <form id="newCaseForm" class="drawer__body" autocomplete="off">

            {{-- 1. Case Information --}}
            <div class="sectionTitle">1. Case Information</div>
            <p class="nc-hint">Basic details about the incident.</p>
            <div class="grid2">
                <div class="field">
                    <label>Incident Date *</label>
                    <input type="date" id="nc_incidentDate" required />
                    <small class="nc-hint">When the incident happened.</small>
                </div>
                <div class="field">
                    <label>Date Reported *</label>
                    <input type="date" id="nc_dateReported" required />
                    <small class="nc-hint">When HR received the report.</small>
                </div>
                <div class="field grid2__full">
                    <label>Assignment / Location <span class="nc-opt">(optional)</span></label>
                    <select id="nc_location">
                        <option value="">— Select location —</option>
                    </select>
                    <small class="nc-hint">Where the incident occurred.</small>
                </div>
            </div>

            {{-- 2. Parties Involved --}}
            <div class="sectionTitle">2. Parties Involved</div>
            <div class="field">
                <label>Respondent (Employee) *</label>
                <small class="nc-hint">The employee being reported.</small>
                <div class="nc-search">
                    <input type="text" id="nc_respondentText" placeholder="Type name or ID…" autocomplete="off" />
                    <input type="hidden" id="nc_respondentId" />
                    <ul class="nc-suggestions" id="nc_respondentSugg" hidden></ul>
                </div>
            </div>
            <div class="field">
                <label>Complainant *</label>
                <small class="nc-hint">The person who reported the incident.</small>
                <div class="nc-search">
                    <input type="text" id="nc_complainantText" placeholder="Type name or ID…" autocomplete="off" />
                    <input type="hidden" id="nc_complainantId" />
                    <ul class="nc-suggestions" id="nc_complainantSugg" hidden></ul>
                </div>
            </div>
            <div class="field">
                <label>Witnesses <span class="nc-opt">(optional)</span></label>
                <small class="nc-hint">People who saw the incident.</small>
                <div id="nc_witnessWrap" class="nc-tagWrap"></div>
                <div class="nc-addRow">
                    <input type="text" id="nc_witnessInput" placeholder="Type a name and press Enter or +" autocomplete="off" />
                    <button class="btn btn--soft nc-addBtn" type="button" id="nc_addWitnessBtn">+</button>
                </div>
            </div>

            {{-- 3. Incident Details --}}
            <div class="sectionTitle">3. Incident Details</div>
            <div class="field">
                <label>Incident Summary *</label>
                <textarea id="nc_summary" rows="4" placeholder="Brief description of the incident." required></textarea>
            </div>
            <div class="field">
                <label>Remarks <span class="nc-opt">(optional)</span></label>
                <textarea id="nc_remarks" rows="2" placeholder="Additional notes from HR."></textarea>
            </div>

            {{-- 4. Documents --}}
            <div class="sectionTitle">4. Documents</div>
            <p class="nc-hint">Upload the initial incident report and supporting documents.</p>
            <div class="field">
                <label>Upload Documents <span class="nc-opt">(optional)</span></label>
                <div class="nc-dropzone" id="nc_dropzone">
                    <input type="file" id="nc_files" accept=".pdf,.jpg,.jpeg,.png" multiple hidden />
                    <div class="nc-dropzone__inner" id="nc_dropzoneTrigger">
                        <span class="nc-dropzone__icon">📎</span>
                        <span class="nc-dropzone__label">Select Files</span>
                        <span class="nc-dropzone__hint">PDF, JPG, PNG · Multiple files allowed</span>
                    </div>
                    <ul class="nc-fileList" id="nc_fileList"></ul>
                </div>
            </div>

        </form>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="newCaseModal">Cancel</button>
            <button class="btn btn--soft" type="button" id="saveDraftBtn">Save Draft</button>
            <button class="btn btn--maroon" type="button" id="createCaseBtn">Create Case</button>
        </div>
    </aside>

    <!-- Upload Document Drawer -->
    <div class="overlay" id="uploadDocOverlay" hidden></div>
    <aside class="drawer" id="uploadDocModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Upload Document</div>
                <div class="drawer__sub muted small">Attach documents to an existing case.</div>
            </div>
            <button class="iconx" type="button" data-close-modal="uploadDocModal">✕</button>
        </div>
        <form id="uploadDocForm" class="drawer__body" autocomplete="off">
            <div class="field">
                <label>Select Case</label>
                <select id="ud_caseSelect"></select>
            </div>
            <div class="field">
                <label>Document Type</label>
                <select id="ud_docType">
                    <option value="incident_report">Incident Report</option>
                    <option value="spot_report">Spot Report</option>
                    <option value="nte_complainant">NTE — Complainant</option>
                    <option value="nte_respondent">NTE — Respondent</option>
                    <option value="hearing">Hearing Record</option>
                    <option value="decision">Director Decision</option>
                    <option value="sanction_letter">Sanction Letter</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="field">
                <label>Upload File</label>
                <input type="file" id="ud_file" accept=".pdf,.doc,.docx" />
            </div>
            <div class="field">
                <label>Notes (optional)</label>
                <textarea id="ud_notes" rows="2"></textarea>
            </div>
        </form>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="uploadDocModal">Cancel</button>
            <button class="btn btn--maroon" type="button" id="uploadDocSubmit">Upload</button>
        </div>
    </aside>

    <!-- Export Drawer -->
    <div class="overlay" id="exportOverlay" hidden></div>
    <aside class="drawer" id="exportModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Export Cases</div>
                <div class="drawer__sub muted small">Generate a report for HR records.</div>
            </div>
            <button class="iconx" type="button" data-close-modal="exportModal">✕</button>
        </div>
        <form id="exportForm" class="drawer__body" autocomplete="off">
            <div class="grid2">
                <div class="field">
                    <label>From Date</label>
                    <input type="date" id="ex_from" />
                </div>
                <div class="field">
                    <label>To Date</label>
                    <input type="date" id="ex_to" />
                </div>
                <div class="field">
                    <label>Case Type</label>
                    <select id="ex_caseType">
                        <option value="all">All</option>
                        <option value="incident_report">Incident Report</option>
                        <option value="spot_report">Spot Report</option>
                    </select>
                </div>
                <div class="field">
                    <label>Case Status</label>
                    <select id="ex_status">
                        <option value="all">All</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="field">
                    <label>Sanction Type</label>
                    <select id="ex_sanction">
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="field">
                    <label>Export Format</label>
                    <select id="ex_format">
                        <option value="csv">CSV</option>
                        <option value="excel">Excel (soon)</option>
                        <option value="pdf">PDF (soon)</option>
                    </select>
                </div>
            </div>
        </form>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="exportModal">Cancel</button>
            <button class="btn btn--maroon" type="button" id="exportSubmit">Export</button>
        </div>
    </aside>

    <!-- Employee History Drawer -->
    <div class="overlay" id="historyOverlay" hidden></div>
    <aside class="drawer" id="historyModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title">Employee History</div>
                <div class="drawer__sub muted small">Search cases involving a specific employee.</div>
            </div>
            <button class="iconx" type="button" data-close-modal="historyModal">✕</button>
        </div>
        <div class="drawer__body" autocomplete="off">
            <div class="field">
                <label>Search Employee</label>
                <div class="nc-search">
                    <input type="text" id="hist_search" placeholder="Name or Employee ID" autocomplete="off" />
                    <input type="hidden" id="hist_empId" />
                    <ul id="hist_sugg" class="nc-suggestions" hidden></ul>
                </div>
            </div>
            <div id="historyResults" hidden>
                <div class="sectionTitle" style="margin-top:4px;">Tardiness Summary</div>
                <div id="histTardiness"></div>

                <div class="sectionTitle">Sanctions</div>
                <div id="histSanctions"></div>

                <div class="sectionTitle">Case History</div>
                <table class="table table--preview" style="width:100%;">
                    <thead>
                        <tr><th>Case No.</th><th>Title</th><th>Role</th><th>Stage</th><th>Date</th></tr>
                    </thead>
                    <tbody id="historyResultsTbody"></tbody>
                </table>
            </div>
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="historyModal">Cancel</button>
            <button class="btn btn--maroon" type="button" id="historySubmit">Search</button>
        </div>
    </aside>

    <!-- Manage Case Drawer -->
    <div class="overlay" id="manageCaseOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="manageCaseModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="mc_caseNo">Manage Case</div>
                <div class="drawer__sub" id="mc_title">—</div>
            </div>
            <button class="iconx" type="button" data-close-modal="manageCaseModal">✕</button>
        </div>
        <div class="drawer__body" id="manageCaseBody">
            {{-- Content injected by JS --}}
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="manageCaseModal">Cancel</button>
            <button class="btn btn--maroon" type="button" id="advanceCaseBtn">Save &amp; Advance</button>
        </div>
    </aside>

    <!-- View Case Drawer -->
    <div class="overlay" id="viewCaseOverlay" hidden></div>
    <aside class="drawer drawer--wide" id="viewCaseModal" aria-hidden="true">
        <div class="drawer__head">
            <div>
                <div class="drawer__title" id="vc_caseNo">Case —</div>
                <div class="drawer__sub" id="vc_title">—</div>
            </div>
            <button class="iconx" type="button" data-close-modal="viewCaseModal">✕</button>
        </div>
        <div class="drawer__body" id="viewCaseBody">
            <div id="vc_loading" class="muted small">Loading…</div>
        </div>
        <div class="drawer__foot">
            <button class="btn" type="button" data-close-modal="viewCaseModal">Close</button>
        </div>
    </aside>

@endsection
