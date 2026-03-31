import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";
import { broadcastAttendanceUpdate } from "./shared/dataSync";

// resources/js/attendance.js
document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  const notifyAttendanceUpdated = () => broadcastAttendanceUpdate();

  // =========================================================
  // DATA: Employees (from DB)
  // =========================================================
  let employees = [];
  let employeesById = new Map();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  async function apiFetch(url, options = {}) {
    const headers = {
      Accept: "application/json",
      ...(options.headers || {}),
    };
    const isFormData = typeof FormData !== "undefined" && options.body instanceof FormData;
    if (options.body && !headers["Content-Type"] && !isFormData) {
      headers["Content-Type"] = "application/json";
    }
    if (csrfToken) headers["X-CSRF-TOKEN"] = csrfToken;

    const res = await fetch(url, { ...options, headers });
    const data = await res.json().catch(() => null);
    if (!res.ok) {
      const msg = data?.message || "Request failed.";
      throw new Error(msg);
    }
    return data;
  }

  function fullName(emp) {
    if (!emp) return "";
    return `${emp.last_name}, ${emp.first_name}${emp.middle_name ? " " + emp.middle_name : ""}`;
  }

  function mapEmployee(emp) {
    return {
      id: emp.id,
      empNo: emp.emp_no,
      name: fullName(emp),
      position: emp.position || "—",
      assignmentType: emp.assignment_type || "",
      areaPlace: emp.area_place || "",
      externalArea: emp.external_area || "",
      employmentType: emp.employment_type || "",
    };
  }

  async function loadEmployees() {
    const rows = await apiFetch("/employees");
    employees = Array.isArray(rows) ? rows.map(mapEmployee) : [];
    employeesById = new Map(employees.map(e => [String(e.id), e]));
  }

  async function loadEmployeeFilters() {
    try {
      const data = await apiFetch("/employees/filters");
      assignmentOptions = Array.isArray(data?.assignments) ? data.assignments : [];
      const ap = data?.area_places;
      areaPlacesGrouped = (ap && typeof ap === "object" && !Array.isArray(ap)) ? ap : {};
      areaPlaceOptions = Array.isArray(areaPlacesGrouped["Field"]) ? areaPlacesGrouped["Field"] : [];
    } catch {
      assignmentOptions = ["Davao", "Tagum", "Field"];
      areaPlacesGrouped = {};
      areaPlaceOptions = [];
    }
  }

  // =========================================================
  // DATA: Attendance Codes (from DB)
  // =========================================================
  const statusFilter = document.getElementById("statusFilter");
  const bulkStatusSelectInline = document.getElementById("bulkStatusSelectInline");
  const f_status = document.getElementById("f_status");

  function buildStatusListFromCodes(codes) {
    const list = [];
    const seen = new Set();
    (codes || []).forEach(c => {
      const desc = String(c.description || c.desc || "").trim();
      if (!desc || seen.has(desc)) return;
      seen.add(desc);
      list.push(desc);
    });
    return list;
  }

  function buildFallbackStatuses() {
    const list = [];
    const seen = new Set();
    Object.values(CODE_MAP).forEach(v => {
      const s = String(v.status || "").trim();
      if (!s || seen.has(s)) return;
      seen.add(s);
      list.push(s);
    });
    return list;
  }

  function setStatusOptions(statuses) {
    if (statusFilter) {
      statusFilter.innerHTML = `<option value="All" selected>All</option>` +
        statuses.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    }
    if (bulkStatusSelectInline) {
      bulkStatusSelectInline.innerHTML = `<option value="">Set status...</option>` +
        statuses.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    }
    if (f_status) {
      f_status.innerHTML = `<option value="">-- Select --</option>` +
        statuses.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    }
  }

  async function loadAttendanceCodes() {
    try {
      const data = await apiFetch("/settings/attendance-codes");
      const statuses = buildStatusListFromCodes(data?.codes || []);
      const merged = statuses.length ? statuses : buildFallbackStatuses();
      if (!merged.includes("RNR")) merged.push("RNR");
      setStatusOptions(merged);
    } catch (err) {
      const fallback = buildFallbackStatuses();
      if (!fallback.includes("RNR")) fallback.push("RNR");
      setStatusOptions(fallback);
    }
  }

  function getEmpById(employeeId) {
    return employeesById.get(String(employeeId)) || null;
  }

  function getEmpByNo(empNo) {
    return employees.find(e => String(e.empNo) === String(empNo)) || null;
  }

  function getEmployeeName(empNo) {
    return getEmpByNo(empNo)?.name || empNo || "";
  }

  function getEmployeePos(empNo) {
    return getEmpByNo(empNo)?.position || "—";
  }

  // =========================================================
  // ✅ ATTENDANCE CODE MAPPING
  // =========================================================
  const CODE_MAP = {
    // Present
    "P":  { status: "Present", isPaid: true,  countsAsPresent: true },
    "PR": { status: "Present", isPaid: true,  countsAsPresent: true },

    // Late
    "L":  { status: "Late",    isPaid: true,  countsAsPresent: true },
    "LT": { status: "Late",    isPaid: true,  countsAsPresent: true },

    // Absent
    "A":  { status: "Absent",  isPaid: false, countsAsPresent: false },
    "AB": { status: "Absent",  isPaid: false, countsAsPresent: false },

    // Unpaid Leave
    "UL":   { status: "Unpaid Leave", isPaid: false, countsAsPresent: false },
    "LWOP": { status: "Unpaid Leave", isPaid: false, countsAsPresent: false },
    "RNR":  { status: "RNR", isPaid: false, countsAsPresent: false },

    // Paid Leave
    "PL":  { status: "Paid Leave", isPaid: true,  countsAsPresent: true },
    "VL":  { status: "Paid Leave", isPaid: true,  countsAsPresent: true },
    "SL":  { status: "Paid Leave", isPaid: true,  countsAsPresent: true },
    "SPL": { status: "Paid Leave", isPaid: true,  countsAsPresent: true },

    // Half-day / Off / Holiday / LOA
    "HD":  { status: "Half-day", isPaid: true, countsAsPresent: true },
    "OFF": { status: "Day Off", isPaid: false, countsAsPresent: false },
    "HOL": { status: "Holiday", isPaid: true, countsAsPresent: true },
    "LOA": { status: "LOA", isPaid: false, countsAsPresent: false },
  };

  function normalizeCode(code) {
    return String(code || "").trim().toUpperCase();
  }

  function mapAttendanceCode(code, fallbackStatus) {
    const c = normalizeCode(code);
    if (!c) {
      const s = String(fallbackStatus || "").trim();
      if (!s) return { status: "Present", isPaid: true, countsAsPresent: true, code: "" };
      if (s === "Absent") return { status: "Absent", isPaid: false, countsAsPresent: false, code: "" };
      if (s === "Leave") return { status: "Leave", isPaid: false, countsAsPresent: false, code: "" };
      if (s === "Unpaid Leave") return { status: "Unpaid Leave", isPaid: false, countsAsPresent: false, code: "" };
      if (s === "RNR") return { status: "RNR", isPaid: false, countsAsPresent: false, code: "" };
      if (s === "Paid Leave") return { status: "Paid Leave", isPaid: true, countsAsPresent: true, code: "" };
      if (s === "Half-day") return { status: "Half-day", isPaid: true, countsAsPresent: true, code: "" };
      if (s === "Day Off") return { status: "Day Off", isPaid: false, countsAsPresent: false, code: "" };
      if (s === "Holiday") return { status: "Holiday", isPaid: true, countsAsPresent: true, code: "" };
      if (s === "LOA") return { status: "LOA", isPaid: false, countsAsPresent: false, code: "" };
      return { status: s, isPaid: true, countsAsPresent: true, code: "" };
    }
    const mapped = CODE_MAP[c];
    if (mapped) return { ...mapped, code: c };
    return { status: fallbackStatus || "", isPaid: null, countsAsPresent: null, code: c };
  }

  // =========================================================
  // ✅ CUTOFF ENGINE
  // =========================================================
  // NOTE: If these elements DON'T exist in your HTML, the code still works.
  // Add these IDs if you want full cutoff UI:
  //   <input id="cutoffMonth" type="month">
  //   <select id="cutoffSelect"><option value="1-15">Cutoff A</option><option value="16-end">Cutoff B</option></select>
  //   <div id="cutoffRangeLabel"></div>
  const cutoffMonthInput = document.getElementById("cutoffMonth"); // type=month
  const cutoffSelect = document.getElementById("cutoffSelect");   // A or B
  const cutoffRangeLabel = document.getElementById("cutoffRangeLabel");

  // Date helpers (local-safe)
  function toYMD(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd}`;
  }
  function parseYMD(s) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s || ""));
    if (!m) return null;
    const y = Number(m[1]), mo = Number(m[2]), da = Number(m[3]);
    if (!y || !mo || !da) return null;
    const d = new Date(y, mo - 1, da);
    if (d.getFullYear() !== y || d.getMonth() !== mo - 1 || d.getDate() !== da) return null;
    return d;
  }
  function parseYM(s) {
    const m = /^(\d{4})-(\d{2})$/.exec(String(s || ""));
    if (!m) return null;
    const y = Number(m[1]), mo = Number(m[2]);
    if (!y || !mo) return null;
    return { y, m: mo };
  }

  let payrollCalendar = null;
  let latestAttendanceDate = "";
  async function loadPayrollCalendarSettings() {
    try {
      payrollCalendar = await apiFetch("/settings/payroll-calendar");
    } catch {
      payrollCalendar = null;
    }
  }

  async function loadLatestAttendanceDate() {
    try {
      const data = await apiFetch("/attendance/records?latest=1");
      latestAttendanceDate = String(data?.date || "");
    } catch {
      latestAttendanceDate = "";
    }
  }

  function resolveCutoffDays(year, month, cutoffType) {
    const lastDay = new Date(year, month, 0).getDate();
    const cal = payrollCalendar || {};
    const isA = cutoffType === "A" || cutoffType === "11-25";
    let from = isA ? Number(cal.cutoff_a_from ?? 11) : Number(cal.cutoff_b_from ?? 26);
    let to = isA ? Number(cal.cutoff_a_to ?? 25) : Number(cal.cutoff_b_to ?? 10);
    if (!Number.isFinite(from) || from <= 0) from = 1;
    if (!Number.isFinite(to) || to <= 0) to = isA ? 25 : lastDay;
    return { from, to, lastDay };
  }

  function getCutoffRange(year, month, cutoffType) {
    const { from, to } = resolveCutoffDays(year, month, cutoffType);
    const start = new Date(year, month - 1, from);
    let end = new Date(year, month - 1, to);
    if (from > to) {
      end = new Date(year, month, to);
    }
    return { start, end };
  }

  function cutoffLabel(cutoffType, year, month) {
    const { from, to } = resolveCutoffDays(year, month, cutoffType);
    return `${from}-${to}`;
  }

  function syncCutoffOptions(selectEl, ym) {
    if (!selectEl || !ym) return;
    const current = selectEl.value || "";
    let optA = selectEl.querySelector('option[value="A"]');
    let optB = selectEl.querySelector('option[value="B"]');

    if (!optA) {
      optA = document.createElement("option");
      optA.value = "A";
      selectEl.appendChild(optA);
    }
    if (!optB) {
      optB = document.createElement("option");
      optB.value = "B";
      selectEl.appendChild(optB);
    }

    optA.textContent = cutoffLabel("A", ym.y, ym.m);
    optB.textContent = cutoffLabel("B", ym.y, ym.m);

    const legacyA = selectEl.querySelector('option[value="11-25"]');
    if (legacyA) legacyA.remove();
    const legacyB = selectEl.querySelector('option[value="26-10"]');
    if (legacyB) legacyB.remove();

    const normalized = current === "11-25" ? "A" : current === "26-10" ? "B" : current;
    if (normalized === "A" || normalized === "B") {
      selectEl.value = normalized;
    }
  }

  function updateCutoffOptionLabels() {
    const ym = parseYM(cutoffMonthInput?.value);
    if (!ym || !cutoffSelect) return;
    syncCutoffOptions(cutoffSelect, ym);
  }

  function updateEmpCutoffOptionLabels() {
    const ym = parseYM(empCutoffMonthInput?.value);
    if (!ym || !empCutoffSelect) return;
    syncCutoffOptions(empCutoffSelect, ym);
  }

  function isDateWithinCutoff(ymd, range) {
    const d = parseYMD(ymd);
    if (!d) return false;
    const t = d.getTime();
    return t >= range.start.getTime() && t <= range.end.getTime();
  }

  function formatRangeLabel(range) {
    return `${toYMD(range.start)} to ${toYMD(range.end)}`;
  }

  function getActiveCutoff() {
    const ym = parseYM(cutoffMonthInput?.value);
    const cutoffType = cutoffSelect?.value || "A";
    if (!ym) return null;
    const range = getCutoffRange(ym.y, ym.m, cutoffType);
    return { year: ym.y, month: ym.m, cutoffType, range };
  }

  function findCutoffForDate(ymd) {
    const d = parseYMD(ymd);
    if (!d) return null;
    const y = d.getFullYear();
    const m = d.getMonth() + 1;
    const prev = new Date(y, d.getMonth() - 1, 1);
    const candidates = [
      { y, m },
      { y: prev.getFullYear(), m: prev.getMonth() + 1 },
    ];

    for (const c of candidates) {
      const rangeA = getCutoffRange(c.y, c.m, "A");
      if (isDateWithinCutoff(ymd, rangeA)) {
        return { year: c.y, month: c.m, cutoffType: "A" };
      }
      const rangeB = getCutoffRange(c.y, c.m, "B");
      if (isDateWithinCutoff(ymd, rangeB)) {
        return { year: c.y, month: c.m, cutoffType: "B" };
      }
    }
    return { year: y, month: m, cutoffType: "A" };
  }

  // =========================================================
  // RECORDS: API mapping
  // =========================================================
  function mapRecordApi(r) {
    const emp = r.employee_id ? getEmpById(r.employee_id) : null;
    const empNo = emp?.empNo || r.emp_no || "";
    const empName = emp?.name || r.emp_name || (empNo ? empNo : "");
    const pos = emp?.position || r.position || "—";
    const assignType = String(r.assignment_type || "").trim() || (emp?.assignmentType || "");
    // Prefer the area saved on the attendance record (import/template), and only fall back
    // to the employee master data if the record has no area.
    const areaFromRecord = String(r.area_place || "").trim();
    const areaFromEmployee = String(emp?.areaPlace || "").trim();
    const areaPlace = areaFromRecord || areaFromEmployee;
    return {
      id: String(r.id),
      employeeId: String(r.employee_id || ""),
      empId: empNo,
      empName: empName,
      position: pos,
      date: r.date || "",
      timeIn: r.clock_in || "",
      timeOut: r.clock_out || "",
      totalHours: 0,
      status: r.status || "",
      assignType: assignType,
      areaPlace: areaPlace,
      minutesLate: Number(r.minutes_late || 0),
      minutesUndertime: Number(r.minutes_undertime || 0),
      notes: "",
    };
  }

  function toApiPayload(record, overrideStatus) {
    return {
      employee_id: Number(record.employeeId),
      date: record.date,
      status: overrideStatus || record.status,
      clock_in: record.timeIn || null,
      clock_out: record.timeOut || null,
      minutes_late: Number(record.minutesLate || 0),
      minutes_undertime: Number(record.minutesUndertime || 0),
    };
  }

	  async function loadRecords(options = {}) {
	    const qs = new URLSearchParams();
    if (!options.ignoreCutoff) {
      if (cutoffMonthInput?.value) qs.set("month", cutoffMonthInput.value);
      if (cutoffSelect?.value) qs.set("cutoff", cutoffSelect.value);
    }
    if (assignmentFilter && assignmentFilter !== "All") {
      qs.set("assignment", assignmentFilter);
      const areaVal = String(areaSubFilter || "").trim();
      if (areaVal && areaVal.toLowerCase() !== "all") qs.set("area", areaVal);
    }
    const dateVal = dateInput?.value || "";
    if (dateVal) qs.set("date", dateVal);
    const statusVal = statusFilter?.value || "All";
    if (statusVal && statusVal !== "All") qs.set("status", statusVal);
    const q = String(searchInput?.value || "").trim();
    if (q) qs.set("q", q);

	    const rows = Number(attRowsSelect?.value || pageState.rows || 20);
	    const page = Number(pageState.page || 1);
	    qs.set("per_page", String(rows));
	    qs.set("page", String(page));
	    qs.set("sort", String(sortState.key || "name"));
	    qs.set("dir", String(sortState.dir || "asc"));

    const url = qs.toString() ? `/attendance/records?${qs.toString()}` : "/attendance/records";
    const res = await apiFetch(url);

    if (Array.isArray(res)) {
      records = res.map(mapRecordApi);
      serverMeta = { page: 1, per_page: records.length, total: records.length, total_pages: 1, stats: null };
      return;
    }

    records = Array.isArray(res?.data) ? res.data.map(mapRecordApi) : [];
    const meta = res?.meta || {};
    serverMeta = {
      page: Number(meta.page || page),
      per_page: Number(meta.per_page || rows),
      total: Number(meta.total || records.length),
      total_pages: Number(meta.total_pages || 1),
      stats: meta.stats || null,
    };
  }

  // =========================================================
  // FILTERS / TABLE ELEMENTS
  // =========================================================
  const dateInput = document.getElementById("dateInput");
  const searchInput = document.getElementById("searchInput");
  const segContainer = document.getElementById("assignmentSeg");
  let segBtns = [];
  let assignmentFilter = "All";
  let areaSubFilter = "";
  let dateTouched = false;
  let openDropdown = null;
  let openDropdownBtn = null;

  let assignmentOptions = [];
  let areaPlaceOptions = [];
  let areaPlacesGrouped = {};

  const statTotal = document.getElementById("statTotal");
  const statTotalBtn = document.getElementById("statTotalBtn");
  const statPresent = document.getElementById("statPresent");
  const statLate = document.getElementById("statLate");
  const statAbsent = document.getElementById("statAbsent");
  const statLeave = document.getElementById("statLeave");

  const tbody = document.getElementById("attTbody");
  const resultsMeta = document.getElementById("resultsMeta");
  const attFooterInfo = document.getElementById("attFooterInfo");
  const attRowsSelect = document.getElementById("attRowsSelect");
  const attFirst = document.getElementById("attFirst");
  const attPrev = document.getElementById("attPrev");
  const attNext = document.getElementById("attNext");
  const attLast = document.getElementById("attLast");
  const attPageInput = document.getElementById("attPageInput");
  const attPageTotal = document.getElementById("attPageTotal");

  const checkAll = document.getElementById("checkAll");
  const bulkBar = document.getElementById("bulkBar");
  const selectedCount = document.getElementById("selectedCount");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkApplyInline = document.getElementById("bulkApplyInline");

  // =========================================================
  // MANUAL ADD/EDIT DRAWER
  // =========================================================
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const openAddBtn = document.getElementById("openAddBtn");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const saveBtn = document.getElementById("saveBtn");

  const drawerTitle = document.getElementById("drawerTitle");
  const drawerSub = document.getElementById("drawerSub");

  const f_employee = document.getElementById("f_employee");
  const f_date = document.getElementById("f_date");
  const f_assignType = document.getElementById("f_assignType");
  const areaWrap = document.getElementById("areaWrap");
  const f_areaPlace = document.getElementById("f_areaPlace");
  const areaPlaceHint = document.getElementById("areaPlaceHint");
  const f_clockIn  = document.getElementById("f_clockIn");
  const f_clockOut = document.getElementById("f_clockOut");
  const f_notes = document.getElementById("f_notes");
  const editingId = document.getElementById("editingId");
  const plBalanceWrap = document.getElementById("plBalanceWrap");
  const plBalanceInfo = document.getElementById("plBalanceInfo");

  let currentPLRemaining = null; // null = not applicable for this employee

  const errEmployee = document.getElementById("errEmployee");
  const errDate = document.getElementById("errDate");
  const errStatus = document.getElementById("errStatus");
  const errAssignType = document.getElementById("errAssignType");
  const errAreaPlace = document.getElementById("errAreaPlace");

  function clearErrors() {
    [errEmployee, errDate, errStatus, errAssignType, errAreaPlace].forEach(el => { if (el) el.textContent = ""; });
  }

  // =========================================================
  // IMPORT / PREVIEW ELEMENTS
  // =========================================================
  const dropzone = document.getElementById("dropzone");
  const importFile = document.getElementById("importFile");
  const clearFileBtn = document.getElementById("clearFileBtn");
  const previewImportBtn = document.getElementById("previewImportBtn");
  const fileNameLabel = document.getElementById("fileNameLabel");
  const dropzoneTitle = document.getElementById("dropzoneTitle");
  const dropzoneFileName = document.getElementById("dropzoneFileName");
  const downloadTemplateBtn = document.getElementById("downloadTemplateBtn");

  const previewDrawer = document.getElementById("previewDrawer");
  const previewOverlay = document.getElementById("previewOverlay");
  const kpiPreviewDrawer = document.getElementById("kpiPreviewDrawer");
  const kpiPreviewOverlay = document.getElementById("kpiPreviewOverlay");
  const kpiPreviewTbody = document.getElementById("kpiPreviewTbody");
  const kpiPreviewTitle = document.getElementById("kpiPreviewTitle");
  const closeKpiPreviewBtn = document.getElementById("closeKpiPreviewBtn");
  const closeKpiPreviewFooter = document.getElementById("closeKpiPreviewFooter");
  const closePreviewBtn = document.getElementById("closePreviewBtn");
  const closePreviewFooter = document.getElementById("closePreviewFooter");

  const sumRows = document.getElementById("sumRows");
  const sumValid = document.getElementById("sumValid");
  const sumErrors = document.getElementById("sumErrors");
  const sumConflicts = document.getElementById("sumConflicts");

  const conflictBar = document.getElementById("conflictBar");
  const overwriteAllBtn = document.getElementById("overwriteAllBtn");
  const skipAllBtn = document.getElementById("skipAllBtn");

  const errorsDetails = document.getElementById("errorsDetails");
  const errorsBadge = document.getElementById("errorsBadge");
  const errorsList = document.getElementById("errorsList");

  const showErrorsOnlyBtn = document.getElementById("showErrorsOnlyBtn");
  const showAllRowsBtn = document.getElementById("showAllRowsBtn");

  const previewTbody = document.getElementById("previewTbody");
  const saveImportBtn = document.getElementById("saveImportBtn");
  const saveMessage = document.getElementById("saveMessage");
  let importFileContent = "";

  // =========================================================
  // EMPLOYEE DRAWER
  // =========================================================
  const empDrawer = document.getElementById("empDrawer");
  const empOverlay = document.getElementById("empOverlay");
  const closeEmpDrawerBtn = document.getElementById("closeEmpDrawerBtn");
  const closeEmpDrawerFooter = document.getElementById("closeEmpDrawerFooter");

  const empDrawerTitle = document.getElementById("empDrawerTitle");
  const empDrawerSub = document.getElementById("empDrawerSub");

  const empTbody = document.getElementById("empTbody");

  const empSumTotal = document.getElementById("empSumTotal");
  const empSumHours = document.getElementById("empSumHours");

  const empCutoffMonthInput = document.getElementById("empCutoffMonth");
  const empCutoffSelect = document.getElementById("empCutoffSelect");
  const empCutoffRangeLabel = document.getElementById("empCutoffRangeLabel");

  let currentEmpId = "";
  let empRecords = [];

  // =========================================================
  // DATA: current cutoff + records
  // =========================================================
  let activeCutoff = null;
  let records = [];
  let serverMeta = {
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 1,
    stats: null,
  };
  // default sorting (hoisted early to avoid TDZ when render runs during init)
  let sortState = { key: "name", dir: "asc" };
  let pageState = { page: 1, rows: 20 };
  let lastFilterSig = "";

  async function loadLatestDateWithinFilters({ month, cutoff, assignment, area } = {}) {
    const qs = new URLSearchParams();
    qs.set("latest", "1");
    if (month) qs.set("month", month);
    if (cutoff) qs.set("cutoff", cutoff);
    if (assignment) qs.set("assignment", assignment);
    if (area) qs.set("area", area);

    try {
      const res = await apiFetch(`/attendance/records?${qs.toString()}`);
      return String(res?.date || "");
    } catch {
      return "";
    }
  }

  async function refreshActiveCutoffAndLoad(options = {}) {
    // If cutoff UI exists => we require it
    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);

    if (hasCutoffUI) {
      if (!payrollCalendar) {
        await loadPayrollCalendarSettings();
      }
      // Ensure the cutoff inputs always have defaults so we don't bail out.
      if (cutoffMonthInput && !cutoffMonthInput.value) {
        const now = new Date();
        cutoffMonthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
      }
      updateCutoffOptionLabels();
      if (cutoffSelect && !cutoffSelect.value) {
        cutoffSelect.value = cutoffSelect.querySelector('option[value="A"]') ? "A" : "11-25";
      }

      const c = getActiveCutoff();
      if (!c) {
        activeCutoff = null;
        records = [];
        render();
        return;
      }
      activeCutoff = c;

      updateCutoffOptionLabels();
      if (cutoffRangeLabel) cutoffRangeLabel.textContent = formatRangeLabel(c.range);

      // Clear stale date filter and constrain to new period before loading
      if (dateInput) {
        dateInput.min = toYMD(c.range.start);
        dateInput.max = toYMD(c.range.end);
        if (!dateTouched) {
          dateInput.value = "";
        } else if (dateInput.value && !isDateWithinCutoff(dateInput.value, c.range)) {
          // If user-selected date is outside the new cutoff window, fall back to auto-select.
          dateInput.value = "";
          dateTouched = false;
        }
      }
      if (f_date) {
        f_date.min = toYMD(c.range.start);
        f_date.max = toYMD(c.range.end);
      }

      // Auto-select the most recent attendance date within the cutoff (server-side)
      if (dateInput && !dateTouched) {
        const latest = await loadLatestDateWithinFilters({
          month: cutoffMonthInput?.value || "",
          cutoff: cutoffSelect?.value || "",
          assignment: assignmentFilter || "All",
          area: areaSubFilter || "",
        });

        const today = toYMD(new Date());
        const preferred = latest || (isDateWithinCutoff(today, c.range) ? today : "");
        dateInput.value = preferred;
      }

      // Load records for the selected (latest) date.
      pageState.page = 1;
      await loadRecords();

      if (
        options.allowLatestFallback &&
        latestAttendanceDate &&
        serverMeta.total === 0 &&
        !isDateWithinCutoff(latestAttendanceDate, c.range)
      ) {
        activeCutoff = null;
        if (cutoffRangeLabel) cutoffRangeLabel.textContent = "—";
        await loadRecords({ ignoreCutoff: true });
        if (dateInput) {
          dateInput.value = latestAttendanceDate;
          dateInput.min = "";
          dateInput.max = "";
        }
      }

      render();
      return;
    }

    // If cutoff UI DOES NOT exist, just load records
    activeCutoff = null;

    // Default to the most recent attendance date that has data so the table isn't empty on load.
    if (dateInput && !dateTouched && !dateInput.value) {
      const latestForFilters = await loadLatestDateWithinFilters({
        assignment: assignmentFilter || "All",
        area: areaSubFilter || "",
      });
      dateInput.value = latestForFilters || latestAttendanceDate || "";
    }

    await loadRecords();
    render();
  }

  // init cutoff defaults (only if cutoff UI exists)
  (async function initCutoffDefaults() {
    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);
    const preloads = [];
    if (hasCutoffUI) {
      preloads.push(loadPayrollCalendarSettings(), loadLatestAttendanceDate());
    }
    preloads.push(loadEmployees(), loadEmployeeFilters(), loadAttendanceCodes());
    await Promise.all(preloads);

    if (hasCutoffUI) {
      const now = new Date();
      const latest = latestAttendanceDate;
      const latestCutoff = latest ? findCutoffForDate(latest) : null;
      const ym = latestCutoff
        ? `${latestCutoff.year}-${String(latestCutoff.month).padStart(2, "0")}`
        : `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
      cutoffMonthInput.value = ym;

      updateCutoffOptionLabels();
      if (cutoffSelect) {
        if (latestCutoff) {
          cutoffSelect.value = latestCutoff.cutoffType;
        } else {
          const day = now.getDate();
          const cutoffBFrom = Number(payrollCalendar?.cutoff_b_from ?? 26);
          const useB = day >= cutoffBFrom;
          if (cutoffSelect.querySelector('option[value="B"]')) {
            cutoffSelect.value = useB ? "B" : "A";
          } else {
            cutoffSelect.value = useB ? "26-10" : "11-25";
          }
        }
      }
    }
    initEmployeeSelect();
    populateAssignTypeOptions();
    populateAreaPlaceOptions();
    buildAssignmentSeg();
    bindSegButtons();
    await refreshActiveCutoffAndLoad({ allowLatestFallback: true });
  })();

  cutoffMonthInput && cutoffMonthInput.addEventListener("change", () => { refreshActiveCutoffAndLoad(); });
  cutoffSelect && cutoffSelect.addEventListener("change", () => { refreshActiveCutoffAndLoad(); });

  // =========================================================
  // HELPERS
  // =========================================================
  function normalize(s) { return String(s || "").toLowerCase(); }

  function escapeHtml(s) {
    return String(s || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function chip(status) {
    if (status === "Present") return `<span class="chip chip--present">Present</span>`;
    if (status === "Late") return `<span class="chip chip--late">Late</span>`;
    if (status === "Absent") return `<span class="chip chip--absent">Absent</span>`;
    if (status === "Unpaid Leave") return `<span class="chip chip--unpaid">Unpaid Leave</span>`;
    if (status === "RNR") return `<span class="chip chip--unpaid">RNR</span>`;
    if (status === "Paid Leave") return `<span class="chip chip--paid">Paid Leave</span>`;
    if (status === "Half-day") return `<span class="chip chip--halfday">Half-day</span>`;
    if (status === "Day Off") return `<span class="chip chip--off">Day Off</span>`;
    if (status === "Holiday") return `<span class="chip chip--holiday">Holiday</span>`;
    if (status === "LOA") return `<span class="chip chip--loa">LOA</span>`;
    return `<span class="chip chip--leave">${escapeHtml(status || "—")}</span>`;
  }

  function assignmentText(r) {
    if (r.areaPlace) return `${r.assignType || "—"} (${r.areaPlace})`;
    return r.assignType || "—";
  }

  function formatHours(h) {
    const n = Number(h);
    if (!isFinite(n) || n <= 0) return "0";
    return n.toFixed(2);
  }

  function fmtTime(t) {
    if (!t) return "—";
    const [hStr, mStr] = String(t).split(":");
    const h = parseInt(hStr, 10);
    const m = mStr || "00";
    const ampm = h >= 12 ? "PM" : "AM";
    const h12  = h % 12 || 12;
    return `${h12}:${m} ${ampm}`;
  }

  function selectedIds() {
    return Array.from(document.querySelectorAll(".rowCheck:checked")).map(cb => cb.dataset.id);
  }

  function updateBulkBar() {
    const ids = selectedIds();
    if (selectedCount) selectedCount.textContent = ids.length;

    const show = ids.length > 0;
    if (bulkBar) {
      bulkBar.style.display = show ? "flex" : "none";
      bulkBar.setAttribute("aria-hidden", show ? "false" : "true");
    }

    if (!show) {
      if (bulkStatusSelectInline) bulkStatusSelectInline.value = "";
      if (bulkApplyInline) bulkApplyInline.disabled = true;
    }
  }

  function syncCheckAll() {
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    const checked = checks.filter(x => x.checked);
    if (!checkAll) return;

    if (checks.length === 0) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
      return;
    }
    checkAll.checked = checked.length === checks.length;
    checkAll.indeterminate = checked.length > 0 && checked.length < checks.length;
  }

  function updateSortIcons() {
    document.querySelectorAll("th.sortable").forEach(th => {
      const k = th.dataset.sort;
      th.classList.remove("is-asc", "is-desc");
      if (k === sortState.key) {
        th.classList.add(sortState.dir === "asc" ? "is-asc" : "is-desc");
      }
    });
  }

  function applySorting(list) {
    const { key, dir } = sortState;
    const mul = dir === "asc" ? 1 : -1;
    return list.slice().sort((a, b) => {
      let av = "";
      let bv = "";
      if (key === "empId") {
        av = normalize(a.empId);
        bv = normalize(b.empId);
      } else if (key === "name") {
        av = normalize(a.empName);
        bv = normalize(b.empName);
      } else if (key === "assignment") {
        av = normalize(assignmentText(a));
        bv = normalize(assignmentText(b));
      } else if (key === "area") {
        av = normalize(a.areaPlace);
        bv = normalize(b.areaPlace);
      }
      return av.localeCompare(bv) * mul;
    });
  }

	  document.querySelectorAll("th.sortable").forEach(th => {
	    th.addEventListener("click", async () => {
	      const key = th.dataset.sort;
	      if (!key) return;
	      if (sortState.key === key) {
	        sortState.dir = sortState.dir === "asc" ? "desc" : "asc";
	      } else {
	        sortState.key = key;
	        sortState.dir = "asc";
	      }
	      updateSortIcons();
	      pageState.page = 1;
	      await loadRecords();
	      render();
	    });
	  });

  updateSortIcons();

  // =========================================================
  // FILTER + RENDER MAIN TABLE
  // =========================================================
  function getFilterSignature() {
    const cutoffSig = activeCutoff
      ? `${toYMD(activeCutoff.range.start)}_${toYMD(activeCutoff.range.end)}`
      : "";
    return [
      cutoffSig,
      dateInput?.value || "",
      statusFilter?.value || "",
      searchInput?.value || "",
      assignmentFilter || "",
      areaSubFilter || "",
      pageState.page || 1,
      pageState.rows || 20,
    ].join("|");
  }

  function render() {
    if (!tbody) return;

    const sig = getFilterSignature();
    if (sig !== lastFilterSig) {
      pageState.page = 1;
      lastFilterSig = sig;
    }

    const filtered = records;
    const sorted = applySorting(filtered);
    const totalPages = Math.max(1, Number(serverMeta.total_pages || 1));

    if (serverMeta.stats) {
      if (statTotal) statTotal.textContent = serverMeta.stats.total ?? 0;
      if (statPresent) statPresent.textContent = serverMeta.stats.present ?? 0;
      if (statLate) statLate.textContent = serverMeta.stats.late ?? 0;
      if (statAbsent) statAbsent.textContent = serverMeta.stats.absent ?? 0;
      if (statLeave) statLeave.textContent = serverMeta.stats.leave ?? 0;
    } else {
      if (statTotal) statTotal.textContent = filtered.length;
      if (statPresent) statPresent.textContent = filtered.filter(r => ["Present", "Half-day"].includes(r.status)).length;
      if (statLate) statLate.textContent = filtered.filter(r => r.status === "Late").length;
      if (statAbsent) statAbsent.textContent = filtered.filter(r => r.status === "Absent").length;
      if (statLeave) {
        const leaveSet = new Set(["Leave", "Unpaid Leave", "RNR", "Paid Leave", "LOA", "Holiday", "Day Off"]);
        statLeave.textContent = filtered.filter(r => leaveSet.has(r.status)).length;
      }
    }

    if (resultsMeta) resultsMeta.textContent = `Showing ${serverMeta.total ?? filtered.length} record(s)`;
    if (attFooterInfo) {
      attFooterInfo.textContent = `Page ${serverMeta.page || pageState.page} of ${totalPages}`;
    }
    if (attPageTotal) attPageTotal.textContent = `/ ${totalPages}`;
    if (attPageInput) attPageInput.value = String(serverMeta.page || pageState.page);
    if (attFirst) attFirst.disabled = (serverMeta.page || pageState.page) <= 1;
    if (attPrev) attPrev.disabled = (serverMeta.page || pageState.page) <= 1;
    if (attNext) attNext.disabled = (serverMeta.page || pageState.page) >= totalPages;
    if (attLast) attLast.disabled = (serverMeta.page || pageState.page) >= totalPages;

    const selected = new Set(selectedIds());
    tbody.innerHTML = "";

    sorted.forEach(r => {
      const tr = document.createElement("tr");
      tr.dataset.empId = r.empId;
      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${r.id}" ${selected.has(r.id) ? "checked" : ""} aria-label="Select record ${r.id}">
        </td>
        <td>${escapeHtml(r.date)}</td>
        <td>${escapeHtml(r.empId || "—")}</td>
        <td>
          <div class="empCell">
            <button class="empLink" type="button" data-emp="${r.empId}">
              ${escapeHtml(r.empName)}
            </button>
          </div>
        </td>
        <td>${escapeHtml(r.assignType || "—")}</td>
        <td>${escapeHtml(r.areaPlace || "—")}</td>
        <td>${fmtTime(r.timeIn)} / ${fmtTime(r.timeOut)}</td>
        <td>${chip(r.status)}</td>
        <td class="col-actions">
          <div class="iconrow">
            <button class="iconbtn" type="button" data-action="edit" data-id="${r.id}" title="Edit">✎</button>
            <button class="iconbtn" type="button" data-action="delete" data-id="${r.id}" title="Delete">🗑</button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll(".rowCheck").forEach(cb => {
      cb.addEventListener("change", () => {
        updateBulkBar();
        syncCheckAll();
      });
    });

    updateBulkBar();
    syncCheckAll();
  }

  // Pagination events
  attRowsSelect && attRowsSelect.addEventListener("change", async () => {
    pageState.page = 1;
    await loadRecords();
    render();
  });
  attFirst && attFirst.addEventListener("click", async () => {
    pageState.page = 1;
    await loadRecords();
    render();
  });
  attPrev && attPrev.addEventListener("click", async () => {
    pageState.page = Math.max(1, (serverMeta.page || pageState.page) - 1);
    await loadRecords();
    render();
  });
  attNext && attNext.addEventListener("click", async () => {
    pageState.page = (serverMeta.page || pageState.page) + 1;
    await loadRecords();
    render();
  });
  attLast && attLast.addEventListener("click", async () => {
    pageState.page = Number.MAX_SAFE_INTEGER;
    await loadRecords();
    render();
  });
  attPageInput && attPageInput.addEventListener("change", async () => {
    const n = Number(attPageInput.value || 1);
    pageState.page = Number.isFinite(n) && n > 0 ? Math.floor(n) : 1;
    await loadRecords();
    render();
  });

  // Table row actions: view employee, edit, delete
  tbody && tbody.addEventListener("click", (e) => {
    const empBtn = e.target.closest("button.empLink");
    if (empBtn) {
      const empId = empBtn.dataset.emp || "";
      if (empId) openEmpDrawer(empId);
      return;
    }

    const actionBtn = e.target.closest("button[data-action]");
    if (actionBtn) {
      const id = actionBtn.dataset.id;
      const action = actionBtn.dataset.action;
      const record = records.find(r => r.id === id);
      if (!record) return;

      if (action === "edit") openDrawer("edit", record);

      if (action === "delete") {
        if (confirm(`Delete attendance record ${record.empId} on ${record.date}?`)) {
          apiFetch(`/attendance/records/${id}`, { method: "DELETE" })
            .then(async () => {
              await loadRecords();
              render();
              notifyAttendanceUpdated();
            })
            .catch(err => alert(err.message || "Failed to delete attendance."));
        }
      }
    }
  });

  // =========================================================
  // INIT EMPLOYEE DROPDOWN
  // =========================================================
  function initEmployeeSelect() {
    if (!f_employee) return;
    f_employee.innerHTML =
      `<option value="">—</option>` +
      employees.map(e => `<option value="${e.id}">${e.name} — ${e.empNo}</option>`).join("");
  }

  // =========================================================
  // TABS + FILTER EVENTS
  // =========================================================
  function closeAllDropdowns() {
    if (!segContainer) return;
    segContainer.querySelectorAll(".seg__dropdown").forEach(dd => {
      dd.classList.remove("is-open");
      dd.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function buildAssignmentSeg() {
    if (!segContainer) return;
    const opts = Array.from(new Set(assignmentOptions.filter(x => String(x || "").trim() !== "")));

    segContainer.innerHTML = "";

    const allBtn = document.createElement("button");
    allBtn.className = "seg__btn seg__btn--emp is-active";
    allBtn.type = "button";
    allBtn.dataset.assign = "All";
    allBtn.textContent = "All";
    segContainer.appendChild(allBtn);

    opts.forEach((label) => {
      const places = Array.isArray(areaPlacesGrouped[label]) ? areaPlacesGrouped[label] : [];
      const wrap = document.createElement("div");
      wrap.className = "seg__btn-wrap";

      const btn = document.createElement("button");
      btn.className = "seg__btn seg__btn--emp";
      btn.type = "button";
      btn.dataset.assign = label;
      btn.textContent = label;
      if (places.length) {
        const chev = document.createElement("span");
        chev.className = "seg__chevron";
        chev.textContent = "▾";
        btn.appendChild(chev);
      }
      wrap.appendChild(btn);

      if (places.length) {
        const dropdown = document.createElement("div");
        dropdown.className = "seg__dropdown";
        dropdown.dataset.group = label;
        dropdown.style.display = "none";
        dropdown.innerHTML = places.map(p =>
          `<button type="button" class="seg__dropdown-item" data-place="${escapeHtml(p)}">${escapeHtml(p)}</button>`
        ).join("");
        wrap.appendChild(dropdown);
      }

      segContainer.appendChild(wrap);
    });

    segBtns = Array.from(segContainer.querySelectorAll(".seg__btn--emp"));
  }

  function populateAssignTypeOptions() {
    if (!f_assignType) return;
    const current = f_assignType.value;
    const opts = Array.from(new Set(assignmentOptions.filter(x => String(x || "").trim() !== "")));
    f_assignType.innerHTML =
      `<option value="">—</option>` +
      opts.map(o => `<option value="${o}">${o}</option>`).join("");
    if (current && opts.includes(current)) {
      f_assignType.value = current;
    }
  }

  function populateAreaPlaceOptions() {
    if (!f_areaPlace) return;
    const current = f_areaPlace.value;
    const opts = Array.from(new Set(areaPlaceOptions.filter(x => String(x || "").trim() !== "")));
    f_areaPlace.innerHTML =
      `<option value="">—</option>` +
      opts.map(o => `<option value="${o}">${o}</option>`).join("");
    if (current && opts.includes(current)) {
      f_areaPlace.value = current;
    }
  }

  function bindSegButtons() {
    if (!segContainer) return;
    const contentScroller = document.querySelector(".content");
    let rafId = 0;
    function positionDropdown(btn, dropdown) {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desiredMin = Math.round(rect.width);
      const maxWidth = Math.min(320, Math.max(200, viewportW - 16));
      const dropdownW = Math.max(desiredMin, maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) {
        left = Math.max(8, viewportW - dropdownW - 8);
      }
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${desiredMin}px`;
      dropdown.style.maxWidth = `${maxWidth}px`;
    }

    function refreshOpenDropdownPosition() {
      if (!openDropdown || !openDropdownBtn) return;
      if (rafId) cancelAnimationFrame(rafId);
      rafId = requestAnimationFrame(() => {
        positionDropdown(openDropdownBtn, openDropdown);
      });
    }

    segBtns.forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const rawAssign = btn.getAttribute("data-assign");
        const group = rawAssign && rawAssign !== "" ? rawAssign : "All";
        const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
        const wasOpen = dropdown && dropdown.style.display === "block";

        const isAlreadyActive = btn.classList.contains("is-active");
        closeAllDropdowns();

        segBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        assignmentFilter = group;
        areaSubFilter = "";
        pageState.page = 1;
        loadRecords().then(render);

        if (dropdown) {
          if (isAlreadyActive && wasOpen) {
            dropdown.classList.remove("is-open");
            dropdown.style.display = "none";
            openDropdown = null;
            openDropdownBtn = null;
            return;
          }
          positionDropdown(btn, dropdown);
          dropdown.style.display = "block";
          dropdown.classList.add("is-open");
          openDropdown = dropdown;
          openDropdownBtn = btn;
        }
      });
    });

    segContainer.querySelectorAll(".seg__dropdown-item").forEach(item => {
      item.addEventListener("click", (e) => {
        e.stopPropagation();
        const place = item.getAttribute("data-place");
        const dropdown = item.closest(".seg__dropdown");
        const group = dropdown?.getAttribute("data-group") || "";

        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");

        assignmentFilter = group || assignmentFilter;
        areaSubFilter = place || "";
        closeAllDropdowns();
        pageState.page = 1;
        loadRecords().then(render);
      });
    });

    document.addEventListener("click", (e) => {
      if (!segContainer.contains(e.target)) closeAllDropdowns();
    }, { capture: true });
    window.addEventListener("resize", refreshOpenDropdownPosition);
    window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
    contentScroller && contentScroller.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  }


  [dateInput, statusFilter].forEach(el => el && el.addEventListener("change", async () => {
    if (el === dateInput) dateTouched = true;
    pageState.page = 1;
    await loadRecords();
    render();
  }));
  searchInput && searchInput.addEventListener("input", async () => {
    pageState.page = 1;
    await loadRecords();
    render();
  });

  // =========================================================
  // CHECKBOX + BULK
  // =========================================================
  checkAll && checkAll.addEventListener("change", () => {
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    checks.forEach(cb => { cb.checked = checkAll.checked; });
    updateBulkBar();
    syncCheckAll();
  });

  bulkDeleteBtn && bulkDeleteBtn.addEventListener("click", async () => {
    const ids = selectedIds();
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} selected record(s)?`)) return;

    try {
      await Promise.all(ids.map(id => apiFetch(`/attendance/records/${id}`, { method: "DELETE" })));
      await loadRecords();
      render();
      notifyAttendanceUpdated();
    } catch (err) {
      alert(err.message || "Failed to delete selected records.");
    }
  });

  bulkStatusSelectInline && bulkStatusSelectInline.addEventListener("change", () => {
    const hasSelection = selectedIds().length > 0;
    const hasStatus = !!bulkStatusSelectInline.value;
    if (bulkApplyInline) bulkApplyInline.disabled = !(hasSelection && hasStatus);
  });

  bulkApplyInline && bulkApplyInline.addEventListener("click", async () => {
    const ids = selectedIds();
    const newStatus = bulkStatusSelectInline.value;
    if (!ids.length || !newStatus) return;

    const updates = records.filter(r => ids.includes(r.id));
    if (newStatus === "RNR") {
      const invalid = updates.filter(r => String(r.assignType || "").trim().toLowerCase() !== "field");
      if (invalid.length) {
        alert("RNR is only allowed for Field employees. Use Day Off for Davao/Tagum.");
        return;
      }
    }
    if (newStatus === "Day Off") {
      const invalid = updates.filter(r => {
        const a = String(r.assignType || "").trim().toLowerCase();
        return !(a === "davao" || a === "tagum");
      });
      if (invalid.length) {
        alert("Day Off is only allowed for Davao/Tagum employees. Use RNR for Field.");
        return;
      }
    }

    try {
      await Promise.all(updates.map(r =>
        apiFetch(`/attendance/records/${r.id}`, {
          method: "PUT",
          body: JSON.stringify(toApiPayload(r, newStatus)),
        })
      ));
      await loadRecords();
      notifyAttendanceUpdated();
    } catch (err) {
      alert(err.message || "Failed to update status.");
    }

    bulkStatusSelectInline.value = "";
    if (bulkApplyInline) bulkApplyInline.disabled = true;
    document.querySelectorAll(".rowCheck:checked").forEach(cb => { cb.checked = false; });
    if (checkAll) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
    }
    updateBulkBar();
    render();
  });

  // =========================================================
  // ✅ MANUAL DRAWER (Add button works)
  // =========================================================
  async function resolveAndPopulateArea() {
    if (!f_areaPlace || !f_employee || !f_date) return;
    const employeeId = f_employee.value;
    const date = f_date.value;
    if (!employeeId || !date) return;

    if (errAreaPlace) errAreaPlace.textContent = "";
    if (areaPlaceHint) areaPlaceHint.style.display = "";
    try {
      const data = await apiFetch(`/attendance/area?employee_id=${encodeURIComponent(employeeId)}&date=${encodeURIComponent(date)}`);
      const resolved = data?.area_place || "";
      if (!resolved) {
        if (errAreaPlace) errAreaPlace.textContent = "No Area Place found for this date. Please select manually.";
        return;
      }
      if (!Array.from(f_areaPlace.options).some(o => o.value === resolved)) {
        const opt = document.createElement("option");
        opt.value = resolved;
        opt.textContent = resolved;
        f_areaPlace.appendChild(opt);
      }
      f_areaPlace.value = resolved;
    } catch {
      if (errAreaPlace) errAreaPlace.textContent = "Unable to resolve Area Place. Please select manually.";
    } finally {
      if (areaPlaceHint) areaPlaceHint.style.display = "none";
    }
  }

  async function fetchPLBalance(empNo, year) {
    currentPLRemaining = null;
    if (plBalanceWrap) plBalanceWrap.style.display = "none";
    try {
      const data = await apiFetch(`/employees/${encodeURIComponent(empNo)}/pl-balance?year=${year}`);
      if (!data.applicable) return;
      currentPLRemaining = data.remaining;
      if (plBalanceInfo) {
        plBalanceInfo.textContent = `Paid Leave: ${data.remaining} of ${data.total} days remaining (${data.year})`;
        plBalanceInfo.style.color = data.remaining <= 0 ? "var(--danger,#c0392b)" : "var(--muted,#666)";
      }
      if (plBalanceWrap) plBalanceWrap.style.display = "";
    } catch { /* silent */ }
  }

  function syncPLBalance() {
    const emp = getEmpById(f_employee?.value);
    if (emp && emp.employmentType.toLowerCase() === "regular" && !!(emp.assignmentType)) {
      const year = f_date?.value ? new Date(f_date.value + "T00:00:00").getFullYear() : new Date().getFullYear();
      fetchPLBalance(emp.empNo, year);
    } else {
      currentPLRemaining = null;
      if (plBalanceWrap) plBalanceWrap.style.display = "none";
    }
  }

  function syncAssignmentFromEmployee() {
    if (!f_employee) return;
    const emp = getEmpById(f_employee.value);
    if (!emp) {
      if (f_assignType) f_assignType.value = "";
      if (f_areaPlace) f_areaPlace.value = "";
      if (areaWrap) areaWrap.hidden = true;
      currentPLRemaining = null;
      if (plBalanceWrap) plBalanceWrap.style.display = "none";
      if (errAreaPlace) errAreaPlace.textContent = "";
      if (f_status) {
        const rnrOpt = Array.from(f_status.options).find(o => o.value === "RNR");
        const dayOffOpt = Array.from(f_status.options).find(o => o.value === "Day Off");
        if (rnrOpt) rnrOpt.disabled = true;
        if (dayOffOpt) dayOffOpt.disabled = true;
      }
      return;
    }
    if (f_assignType) {
      const assignVal = emp.assignmentType || "";
      if (assignVal && !Array.from(f_assignType.options).some(o => o.value === assignVal)) {
        const opt = document.createElement("option");
        opt.value = assignVal;
        opt.textContent = assignVal;
        f_assignType.appendChild(opt);
      }
      f_assignType.value = assignVal;
      f_assignType.disabled = true;
    }
    if (f_areaPlace) {
      if (emp.assignmentType === "Field") {
        f_areaPlace.disabled = false;
        // only auto-resolve if empty (edit mode may already have a snapshot)
        if (!f_areaPlace.value) resolveAndPopulateArea();
      } else {
        f_areaPlace.value = "";
        f_areaPlace.disabled = true;
        if (errAreaPlace) errAreaPlace.textContent = "";
      }
    }
    if (areaWrap) areaWrap.hidden = emp.assignmentType !== "Field";
    if (f_status) {
      const assignLower = String(emp.assignmentType || "").trim().toLowerCase();
      const rnrAllowed = assignLower === "field";
      const dayOffAllowed = assignLower === "davao" || assignLower === "tagum";
      const rnrOpt = Array.from(f_status.options).find(o => o.value === "RNR");
      const dayOffOpt = Array.from(f_status.options).find(o => o.value === "Day Off");
      if (rnrOpt) rnrOpt.disabled = !rnrAllowed;
      if (dayOffOpt) dayOffOpt.disabled = !dayOffAllowed;
      if (!rnrAllowed && f_status.value === "RNR") {
        f_status.value = "";
      }
      if (!dayOffAllowed && f_status.value === "Day Off") {
        f_status.value = "";
      }
    }
    syncPLBalance();
  }

  function openDrawer(mode, record) {
    if (!drawer || !drawerOverlay) return;

    // ✅ if cutoff UI exists, require an active cutoff.
    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);
    if (!activeCutoff && hasCutoffUI) {
      // Try to initialize cutoff on the fly; if still missing, surface a message instead of silently failing.
      refreshActiveCutoffAndLoad();
      if (!activeCutoff) {
        if (errDate) errDate.textContent = "Select a payroll month and cutoff first.";
        return;
      }
    }

      drawer.classList.add("is-open");
      drawer.setAttribute("aria-hidden", "false");
      drawerOverlay.hidden = false;
      document.body.style.overflow = "hidden";
      clearErrors();
      const panel = drawer.querySelector(".drawer__body");
      const resetScroll = () => {
        if (drawer) drawer.scrollTop = 0;
        if (panel) panel.scrollTop = 0;
      };
      resetScroll();
      requestAnimationFrame(resetScroll);

    // clamp date inputs to cutoff when available
    if (activeCutoff && f_date) {
      f_date.min = toYMD(activeCutoff.range.start);
      f_date.max = toYMD(activeCutoff.range.end);
    } else if (f_date) {
      f_date.removeAttribute("min");
      f_date.removeAttribute("max");
    }

    if (mode === "add") {
      drawerTitle.textContent = "Add Attendance";
      drawerSub.textContent = activeCutoff
        ? `Cutoff: ${formatRangeLabel(activeCutoff.range)}`
        : "Fill up the details below.";

      editingId.value = "";

      f_employee.value = "";
      f_date.value = activeCutoff ? toYMD(activeCutoff.range.start) : toYMD(new Date());
      f_status.value = "";
      if (f_assignType) f_assignType.value = "";
      if (f_areaPlace) f_areaPlace.value = "";
      f_notes.value = "";
      if (f_clockIn)  f_clockIn.value  = "";
      if (f_clockOut) f_clockOut.value = "";
      if (areaWrap) areaWrap.hidden = true;
      syncAssignmentFromEmployee();
    } else {
      drawerTitle.textContent = "Edit Attendance";
      drawerSub.textContent = `Record: ${record.empId} • ${record.date}`;
      editingId.value = record.id;

      f_employee.value = record.employeeId || "";
      f_date.value = record.date;
      f_status.value = record.status;
      f_notes.value = record.notes || "";
      if (f_clockIn)  f_clockIn.value  = record.timeIn  || "";
      if (f_clockOut) f_clockOut.value = record.timeOut || "";

      if (f_areaPlace) f_areaPlace.value = "";
      if (record.areaPlace && f_areaPlace) {
        if (!Array.from(f_areaPlace.options).some(o => o.value === record.areaPlace)) {
          const opt = document.createElement("option");
          opt.value = record.areaPlace;
          opt.textContent = record.areaPlace;
          f_areaPlace.appendChild(opt);
        }
        f_areaPlace.value = record.areaPlace;
      }

      syncAssignmentFromEmployee();
      return;

      const emp = getEmpById(record.employeeId || "");
      if (emp?.assignmentType === "Field") {
        if (areaWrap) areaWrap.hidden = false;
        if (f_areaPlace) f_areaPlace.disabled = false;
        if (record.areaPlace) {
          // Populate with the saved snapshot — do NOT re-resolve
          if (!Array.from(f_areaPlace.options).some(o => o.value === record.areaPlace)) {
            const opt = document.createElement("option");
            opt.value = record.areaPlace;
            opt.textContent = record.areaPlace;
            f_areaPlace.appendChild(opt);
          }
          f_areaPlace.value = record.areaPlace;
        } else {
          // No snapshot yet — resolve from history
          resolveAndPopulateArea();
        }
      } else {
        syncAssignmentFromEmployee();
      }
    }
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    if (drawerOverlay) drawerOverlay.hidden = true;
    document.body.style.overflow = "";
    currentPLRemaining = null;
    if (plBalanceWrap) plBalanceWrap.style.display = "none";
  }

  function validateForm() {
    clearErrors();
    let ok = true;

    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);
    if (hasCutoffUI && !activeCutoff) {
      if (errDate) errDate.textContent = "Cutoff is required.";
      return false;
    }

    const employeeId = f_employee.value;
    const date = f_date.value;
    const status = f_status.value;

    if (!employeeId) { if (errEmployee) errEmployee.textContent = "Employee is required."; ok = false; }
    if (!date) { if (errDate) errDate.textContent = "Date is required."; ok = false; }

    // ✅ date must be within cutoff only if cutoff is active
    if (activeCutoff && date && !isDateWithinCutoff(date, activeCutoff.range)) {
      if (errDate) errDate.textContent = `Date must be within cutoff: ${formatRangeLabel(activeCutoff.range)}`;
      ok = false;
    }

    if (!status) { if (errStatus) errStatus.textContent = "Status is required."; ok = false; }
    const emp = getEmpById(employeeId);
    if (emp?.assignmentType === "Field") {
      const area = String(f_areaPlace?.value || "").trim();
      if (!area) {
        if (errAreaPlace) errAreaPlace.textContent = "Area Place is required for Field employees.";
        ok = false;
      }
    }
    if (status === "Paid Leave" && String(emp?.employmentType || "").toLowerCase() !== "regular") {
      if (errStatus) errStatus.textContent = "Paid Leave is only allowed for Regular employees.";
      ok = false;
    }
    const assignLower = String(emp?.assignmentType || "").trim().toLowerCase();
    if (status === "RNR" && assignLower !== "field") {
      if (errStatus) errStatus.textContent = "RNR is only allowed for Field employees. Use Day Off for Davao/Tagum.";
      ok = false;
    }
    if (status === "Day Off" && !(assignLower === "davao" || assignLower === "tagum")) {
      if (errStatus) errStatus.textContent = "Day Off is only allowed for Davao/Tagum employees. Use RNR for Field.";
      ok = false;
    }

    return ok;
  }

  openAddBtn && openAddBtn.addEventListener("click", () => openDrawer("add"));
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);

  f_employee && f_employee.addEventListener("change", () => {
    if (f_areaPlace) f_areaPlace.value = "";
    if (errAreaPlace) errAreaPlace.textContent = "";
    syncAssignmentFromEmployee();
  });

  f_date && f_date.addEventListener("change", () => {
    const emp = getEmpById(f_employee?.value);
    if (emp?.assignmentType === "Field") {
      if (f_areaPlace) f_areaPlace.value = "";
      if (errAreaPlace) errAreaPlace.textContent = "";
      resolveAndPopulateArea();
    }
    syncPLBalance();
  });

  // ✅ Save button now updates table immediately
  saveBtn && saveBtn.addEventListener("click", async () => {
    if (!validateForm()) return;

    if (f_status?.value === "Paid Leave" && currentPLRemaining !== null && currentPLRemaining <= 0) {
      alert("Cannot save: Paid Leave balance is exhausted (0 days remaining).");
      return;
    }

    const id = editingId.value;
    const employeeId = f_employee.value;

    const mapped = mapAttendanceCode("", f_status.value);
    const payload = {
      employee_id: Number(employeeId),
      date: f_date.value,
      status: mapped.status,
      area_place: f_areaPlace?.value || null,
      clock_in:  f_clockIn?.value  || null,
      clock_out: f_clockOut?.value || null,
      minutes_late: 0,
      minutes_undertime: 0,
    };

    try {
      if (!id) {
        await apiFetch("/attendance/records", {
          method: "POST",
          body: JSON.stringify(payload),
        });
      } else {
        await apiFetch(`/attendance/records/${id}`, {
          method: "PUT",
          body: JSON.stringify(payload),
        });
      }
      await loadRecords();
      closeDrawer();
      render();
      notifyAttendanceUpdated();
    } catch (err) {
      alert(err.message || "Failed to save attendance.");
    }
  });

  // =========================================================
  // IMPORT / PREVIEW (DEMO)
  // =========================================================
  let importRows = [];
  let previewMode = "errors";

  function setImportUISelected(file) {
    if (fileNameLabel) fileNameLabel.textContent = file ? file.name : "No file selected.";
    if (clearFileBtn) clearFileBtn.disabled = !file;
    if (previewImportBtn) previewImportBtn.disabled = !file;
    if (dropzoneTitle) dropzoneTitle.hidden = !!file;
    if (dropzoneFileName) {
      dropzoneFileName.hidden = !file;
      dropzoneFileName.textContent = file ? file.name : "";
    }
  }
  function getImportFilters() {
    const month = cutoffMonthInput?.value || "";
    const cutoff = cutoffSelect?.value || "";
    const assignment = assignmentFilter || "All";
    let area = assignment !== "All" ? (areaSubFilter || "") : "";
    if (area === "All") area = "";
    const date = dateInput?.value || "";
    return { month, cutoff, assignment, area, date };
  }

  function openPreview() {
    if (!previewDrawer || !previewOverlay) return;
    previewDrawer.classList.add("is-open");
    previewDrawer.setAttribute("aria-hidden", "false");
    previewOverlay.hidden = false;
    document.body.style.overflow = "hidden";
  }
  function closePreview() {
    if (!previewDrawer || !previewOverlay) return;
    previewDrawer.classList.remove("is-open");
    previewDrawer.setAttribute("aria-hidden", "true");
    previewOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  function assignmentPreviewText(r) {
    if (r.areaPlace) return `${r.assignType || "—"} (${r.areaPlace})`;
    return r.assignType || "—";
  }

  function openKpiPreview() {
    if (!kpiPreviewDrawer || !kpiPreviewOverlay || !kpiPreviewTbody) return;
    kpiPreviewDrawer.classList.add("is-open");
    kpiPreviewDrawer.setAttribute("aria-hidden", "false");
    kpiPreviewOverlay.hidden = false;
    document.body.style.overflow = "hidden";

    const total = serverMeta?.total ?? records.length;
    if (kpiPreviewTitle) {
      kpiPreviewTitle.textContent = `Total Records Preview (${total})`;
    }

    kpiPreviewTbody.innerHTML = "";
    if (!records.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="5" class="muted small">No records to preview.</td>`;
      kpiPreviewTbody.appendChild(tr);
      return;
    }

    const grouped = new Map();
    records.forEach(r => {
      const day = r.date || "—";
      const assign = assignmentPreviewText(r);
      const key = `${day}||${assign}`;
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(r);
    });

    Array.from(grouped.entries())
      .sort((a, b) => {
        const [aDate, aAssign] = a[0].split("||");
        const [bDate, bAssign] = b[0].split("||");
        if (aDate !== bDate) return String(bDate).localeCompare(String(aDate));
        const order = { "G5-Davao": 1, "AYU Household": 2, "Agri-Farm": 3, "Stallion Farm": 4, "Auraland Property": 5, "AURA FORTUNE G5 TRADERS CORPORATION": 6 };
        const ao = order[aAssign] || 99;
        const bo = order[bAssign] || 99;
        if (ao !== bo) return ao - bo;
        return String(aAssign).localeCompare(String(bAssign));
      })
      .forEach(([key, items]) => {
        const [day, assign] = key.split("||");

        const header = document.createElement("tr");
        header.classList.add("kpiPreviewGroup");
        header.innerHTML = `
          <td colspan="5">
            ${escapeHtml(day)} • ${escapeHtml(assign)}
          </td>
        `;
        kpiPreviewTbody.appendChild(header);

        items.forEach(r => {
          const tr = document.createElement("tr");
          const inOut = `${fmtTime(r.timeIn)} / ${fmtTime(r.timeOut)}`;
          tr.innerHTML = `
            <td>${escapeHtml(r.date || "—")}</td>
            <td>${escapeHtml(assignmentPreviewText(r))}</td>
            <td>${escapeHtml(r.empName || "—")}</td>
            <td>${escapeHtml(inOut)}</td>
            <td>${chip(r.status)}</td>
          `;
          kpiPreviewTbody.appendChild(tr);
        });
      });
  }

  function closeKpiPreview() {
    if (!kpiPreviewDrawer || !kpiPreviewOverlay) return;
    kpiPreviewDrawer.classList.remove("is-open");
    kpiPreviewDrawer.setAttribute("aria-hidden", "true");
    kpiPreviewOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  function parseDateOk(yyyy_mm_dd) {
    return /^\d{4}-\d{2}-\d{2}$/.test(yyyy_mm_dd || "");
  }
  function isValidStatus(s) {
    return [
      "Present",
      "Late",
      "Absent",
      "Leave",
      "Unpaid Leave",
      "RNR",
      "Paid Leave",
      "Half-day",
      "Day Off",
      "Holiday",
      "LOA",
    ].includes(s);
  }
  function isValidTime(s) {
    return !s || /^([01]\d|2[0-3]):[0-5]\d$/.test(String(s).trim());
  }
  function isValidNumber(s) {
    if (s === "" || s == null) return true;
    return Number.isFinite(Number(s));
  }

  function calcHours(timeIn, timeOut) {
    if (!timeIn || !timeOut) return 0;
    const m1 = /^(\d{2}):(\d{2})$/.exec(timeIn);
    const m2 = /^(\d{2}):(\d{2})$/.exec(timeOut);
    if (!m1 || !m2) return 0;
    const a = (Number(m1[1]) * 60) + Number(m1[2]);
    const b = (Number(m2[1]) * 60) + Number(m2[2]);
    if (!isFinite(a) || !isFinite(b) || b <= a) return 0;
    return (b - a) / 60;
  }

  function validateImportRow(r) {
    const issues = [];

    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);
    if (hasCutoffUI && !activeCutoff) {
      issues.push(`Row ${r.rowNo}: Cutoff not selected`);
      return issues;
    }

    if (!r.empId) issues.push(`Row ${r.rowNo}: Missing Employee ID`);
    if (r.empId && !getEmpByNo(r.empId)) {
      issues.push(`Row ${r.rowNo}: Employee ID "${r.empId}" not found`);
    }

    if (!r.date || !parseDateOk(r.date)) {
      issues.push(`Row ${r.rowNo}: Invalid or missing Date`);
    } else if (activeCutoff && !isDateWithinCutoff(r.date, activeCutoff.range)) {
      issues.push(`Row ${r.rowNo}: Date ${r.date} is outside cutoff (${formatRangeLabel(activeCutoff.range)})`);
    }

    if (!isValidTime(r.timeIn)) issues.push(`Row ${r.rowNo}: Invalid Clock In (HH:MM)`);
    if (!isValidTime(r.timeOut)) issues.push(`Row ${r.rowNo}: Invalid Clock Out (HH:MM)`);
    if (!isValidNumber(r.minutesLate)) issues.push(`Row ${r.rowNo}: Minutes Late must be a number`);
    if (!isValidNumber(r.minutesUndertime)) issues.push(`Row ${r.rowNo}: Minutes Undertime must be a number`);

    const code = normalizeCode(r.code);
    if (code && !CODE_MAP[code]) {
      issues.push(`Row ${r.rowNo}: Unknown Code "${code}" (add it to CODE_MAP in attendance.js)`);
    }

    const hasCode = !!code;
    const hasStatus = !!String(r.status || "").trim();
    if (!hasCode && !hasStatus) issues.push(`Row ${r.rowNo}: Provide either Code or Status`);
    if (!hasCode && hasStatus && !isValidStatus(r.status)) issues.push(`Row ${r.rowNo}: Invalid status "${r.status}"`);

    const mapped = mapAttendanceCode(r.code, r.status);
    const finalStatus = mapped.status || (r.status || "");
    const emp = r.empId ? getEmpByNo(r.empId) : null;
    const assignLower = String(emp?.assignmentType || "").trim().toLowerCase();
    if (finalStatus === "RNR" && assignLower !== "field") {
      issues.push(`Row ${r.rowNo}: RNR is only allowed for Field employees (use Day Off for Davao/Tagum)`);
    }
    if (finalStatus === "Day Off" && !(assignLower === "davao" || assignLower === "tagum")) {
      issues.push(`Row ${r.rowNo}: Day Off is only allowed for Davao/Tagum employees (use RNR for Field)`);
    }

    return issues;
  }

  function parseCsv(text) {
    const rows = [];
    let cur = "";
    let inQuotes = false;
    const row = [];
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      const next = text[i + 1];
      if (ch === '"' && inQuotes && next === '"') {
        cur += '"';
        i++;
        continue;
      }
      if (ch === '"') {
        inQuotes = !inQuotes;
        continue;
      }
      if (ch === "," && !inQuotes) {
        row.push(cur);
        cur = "";
        continue;
      }
      if ((ch === "\n" || ch === "\r") && !inQuotes) {
        if (ch === "\r" && next === "\n") i++;
        row.push(cur);
        rows.push(row.splice(0));
        cur = "";
        continue;
      }
      cur += ch;
    }
    row.push(cur);
    rows.push(row.splice(0));
    return rows.filter(r => r.some(c => String(c).trim() !== ""));
  }

  function buildParsedRowsFromCsv(text) {
    const rows = parseCsv(text);
    if (!rows.length) return [];
    const header = rows[0].map(h => String(h || "").trim().toLowerCase());
    const idx = (name) => header.indexOf(name);
    const get = (r, name) => {
      const i = idx(name);
      return i >= 0 ? String(r[i] ?? "").trim() : "";
    };

    return rows.slice(1).map((r, i) => ({
      rowNo: i + 2,
      empId: get(r, "emp_no") || get(r, "employee_id") || get(r, "emp_id"),
      date: get(r, "date"),
      timeIn: get(r, "clock_in"),
      timeOut: get(r, "clock_out"),
      minutesLate: get(r, "minutes_late"),
      minutesUndertime: get(r, "minutes_undertime"),
      status: get(r, "status"),
      code: get(r, "code"),
    }));
  }

  function buildImportRows(parsed) {
    return parsed.map(r => {
      const issues = validateImportRow(r);
      const totalHours = calcHours(r.timeIn, r.timeOut);

      const mapped = mapAttendanceCode(r.code, r.status);
      const finalStatus = mapped.status || (r.status || "");
      const emp = getEmpByNo(r.empId);

      return {
        ...r,
        code: normalizeCode(r.code),
        status: finalStatus,
        isPaid: mapped.isPaid,
        countsAsPresent: mapped.countsAsPresent,
        empName: r.empId ? getEmployeeName(r.empId) : "",
        position: r.empId ? getEmployeePos(r.empId) : "",
        assignType: emp?.assignmentType || "",
        areaPlace: emp?.areaPlace || "",
        totalHours,
        isError: issues.length > 0,
        issues,
      };
    });
  }

  function updatePreviewSummary() {
    const rows = importRows.length;
    const errors = importRows.filter(x => x.isError).length;
    const valid = rows - errors;

    if (sumRows) sumRows.textContent = rows;
    if (sumErrors) sumErrors.textContent = errors;
    if (sumValid) sumValid.textContent = valid;
    if (sumConflicts) sumConflicts.textContent = 0;

    if (errorsBadge) errorsBadge.textContent = errors;
    if (errorsList) {
      const msgs = importRows.filter(x => x.isError).flatMap(x => x.issues);
      errorsList.innerHTML = msgs.length
        ? msgs.map(m => `<div class="errorItem">⚠️ ${escapeHtml(m)}</div>`).join("")
        : `<div class="muted small">No errors.</div>`;
    }
    if (errorsDetails) errorsDetails.open = errors > 0;

    if (conflictBar) conflictBar.hidden = true;

    if (!saveImportBtn || !saveMessage) return;
    if (errors > 0 || rows === 0) {
      saveImportBtn.disabled = true;
      saveMessage.textContent = rows === 0 ? "No data to save." : "Fix errors before saving.";
    } else {
      saveImportBtn.disabled = false;
      saveMessage.textContent = activeCutoff
        ? `Ready to save into cutoff: ${formatRangeLabel(activeCutoff.range)}`
        : "Ready to save (no cutoff UI).";
    }
  }

  function renderPreviewTable() {
    if (!previewTbody) return;
    const list = previewMode === "all" ? importRows : importRows.filter(r => r.isError);

    previewTbody.innerHTML = "";
    list.forEach(r => {
      const tr = document.createElement("tr");
      tr.className = r.isError ? "rowError" : "";
      tr.innerHTML = `
        <td>${escapeHtml(r.date || "—")}</td>
        <td>${escapeHtml(r.empName || "—")}</td>
        <td>${escapeHtml(r.assignType || "—")}</td>
        <td>${escapeHtml(r.areaPlace || "—")}</td>
        <td>${fmtTime(r.timeIn)} / ${fmtTime(r.timeOut)}</td>
        <td>${chip(r.status)}</td>
      `;
      previewTbody.appendChild(tr);
    });
  }

  function extractYMDFromFilename(filename) {
    const matches = String(filename || "").match(/\d{4}-\d{2}-\d{2}/g);
    if (!matches) return "";
    for (let i = matches.length - 1; i >= 0; i--) {
      const cand = matches[i];
      if (parseYMD(cand)) return cand;
    }
    return "";
  }

  async function uploadImportFile() {
    const file = importFile?.files?.[0];
    if (!file) {
      alert("Please choose an Excel file.");
      return;
    }

    const f = getImportFilters();

    const fd = new FormData();
    fd.append("assignment", f.assignment);
    fd.append("area", f.area);

    // Only override import date when the user explicitly selected a date,
    // otherwise prefer the date embedded in the template filename to avoid accidental mismatches.
    const dateFromFilename = extractYMDFromFilename(file.name);
    const dateOverride = (dateTouched && f.date) ? f.date : dateFromFilename;
    if (dateOverride) fd.append("date", dateOverride);
    fd.append("file", file);

    try {
      const res = await fetch("/attendance/import", {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
        },
        body: fd,
      });

      const data = await res.json().catch(() => null);
      if (!res.ok) {
        if (res.status === 422 && data?.errors) {
          alert("Import failed:\n\n" + data.errors.slice(0, 30).join("\n"));
          return;
        }
        alert(data?.message || "Import failed. Check server logs.");
        return;
      }

      const importedDates = Array.isArray(data?.dates) ? data.dates.map(String) : [];
      const importedMaxDate = String(data?.max_date || "").trim();
      const importedLabel = importedMaxDate
        ? `Imported successfully. Date: ${importedMaxDate}`
        : "Imported successfully.";
      alert(importedLabel);
      if (importFile) importFile.value = "";
      setImportUISelected(null);
      const nextDate = importedMaxDate || (importedDates.length === 1 ? String(importedDates[0] || "") : "") || f.date || "";
      if (dateInput && nextDate) {
        dateTouched = true;
        dateInput.value = nextDate;
      }
      await loadLatestAttendanceDate();
      await refreshActiveCutoffAndLoad();
      notifyAttendanceUpdated();
    } catch (err) {
      alert(err.message || "Import failed.");
    }
  }

  async function saveImport() {
    const clean = importRows.filter(r => !r.isError);
    try {
      for (const r of clean) {
        const emp = getEmpByNo(r.empId);
        if (!emp) continue;
        await apiFetch("/attendance/records", {
          method: "POST",
          body: JSON.stringify({
            employee_id: Number(emp.id),
            date: r.date,
            status: r.status,
            clock_in: r.timeIn || null,
            clock_out: r.timeOut || null,
            minutes_late: Number(r.minutesLate || 0),
            minutes_undertime: Number(r.minutesUndertime || 0),
          }),
        });
      }
      await loadRecords();
      closePreview();
      render();
      alert("Imported attendance saved.");
      notifyAttendanceUpdated();
    } catch (err) {
      alert(err.message || "Failed to import attendance.");
    }
  }

  // Import file events
  if (importFile) {
    importFile.addEventListener("change", () => {
      const file = importFile.files && importFile.files[0] ? importFile.files[0] : null;
      setImportUISelected(file);
    });
  }

  // Dropzone events
  if (dropzone && importFile) {
    dropzone.addEventListener("click", () => importFile.click());
    dropzone.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        importFile.click();
      }
    });

    ["dragenter", "dragover"].forEach(evt => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add("is-dragover");
      });
    });

    ["dragleave", "drop"].forEach(evt => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove("is-dragover");
      });
    });

    dropzone.addEventListener("drop", (e) => {
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0] ? e.dataTransfer.files[0] : null;
      if (!file) return;

      const dt = new DataTransfer();
      dt.items.add(file);
      importFile.files = dt.files;
      importFile.dispatchEvent(new Event("change"));
    });
  }

  clearFileBtn && clearFileBtn.addEventListener("click", () => {
    if (importFile) importFile.value = "";
    setImportUISelected(null);
  });

  downloadTemplateBtn && downloadTemplateBtn.addEventListener("click", () => {
    const date = dateInput?.value || "";
    if (!date) {
      alert("Please select a date first.");
      return;
    }
    const f = getImportFilters();
    const qs = new URLSearchParams({
      date,
      assignment: f.assignment,
      area: f.area,
    });
    window.location.href = `/attendance/template?${qs.toString()}`;
  });

  function bindPreviewClose() {
    closePreviewBtn && closePreviewBtn.addEventListener("click", closePreview);
    closePreviewFooter && closePreviewFooter.addEventListener("click", closePreview);
    previewOverlay && previewOverlay.addEventListener("click", closePreview);
  }
  bindPreviewClose();

  function bindKpiPreview() {
    statTotalBtn && statTotalBtn.addEventListener("click", openKpiPreview);
    closeKpiPreviewBtn && closeKpiPreviewBtn.addEventListener("click", closeKpiPreview);
    closeKpiPreviewFooter && closeKpiPreviewFooter.addEventListener("click", closeKpiPreview);
    kpiPreviewOverlay && kpiPreviewOverlay.addEventListener("click", closeKpiPreview);
  }
  bindKpiPreview();

  showErrorsOnlyBtn && showErrorsOnlyBtn.addEventListener("click", () => {
    previewMode = "errors";
    showErrorsOnlyBtn.classList.add("is-active");
    showAllRowsBtn && showAllRowsBtn.classList.remove("is-active");
    renderPreviewTable();
  });

  showAllRowsBtn && showAllRowsBtn.addEventListener("click", () => {
    previewMode = "all";
    showAllRowsBtn.classList.add("is-active");
    showErrorsOnlyBtn && showErrorsOnlyBtn.classList.remove("is-active");
    renderPreviewTable();
  });

  previewImportBtn && previewImportBtn.addEventListener("click", () => {
    if (previewImportBtn.disabled) return;
    uploadImportFile();
  });

  // =========================================================
  // EMPLOYEE DRAWER
  // =========================================================
  function toMinutes(t) {
    const m = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(String(t || ""));
    if (!m) return null;
    const hh = Number(m[1]);
    const mm = Number(m[2]);
    const ss = Number(m[3] || 0);
    if (!isFinite(hh) || !isFinite(mm)) return null;
    if (hh < 0 || hh > 23 || mm < 0 || mm > 59 || ss < 0 || ss > 59) return null;
    return hh * 60 + mm + (ss / 60);
  }

  function computeTotalHours(r) {
    if (Number(r.totalHours) > 0) return Number(r.totalHours);
    const a = toMinutes(r.timeIn);
    let b = toMinutes(r.timeOut);
    if (a == null || b == null) return 0;
    if (b <= a) {
      b += 12 * 60;
    }
    if (b <= a) return 0;

    const work1Start = 8 * 60;
    const work1End = 12 * 60;
    const work2Start = 13 * 60;
    const work2End = 17 * 60;

    const overlap = (start, end, winStart, winEnd) => {
      const s = Math.max(start, winStart);
      const e = Math.min(end, winEnd);
      return e > s ? (e - s) : 0;
    };

    const mins = overlap(a, b, work1Start, work1End) + overlap(a, b, work2Start, work2End);
    return mins > 0 ? mins / 60 : 0;
  }

  async function openEmpDrawer(empId) {
    currentEmpId = empId;

    const emp = getEmpByNo(empId);
    const name = emp?.name || getEmployeeName(empId) || empId;
    const pos = emp?.position || getEmployeePos(empId) || "—";

    const list = records
      .filter(r => r.empId === empId)
      .slice()
      .sort((a, b) => String(b.date).localeCompare(String(a.date)));

    const latest = list[0];
    const assign = latest ? assignmentText(latest) : "—";

    if (empDrawerTitle) empDrawerTitle.textContent = name;
    if (empDrawerSub) {
      const employmentType = String(emp?.employmentType || "").trim().toLowerCase();
      const isRegular = employmentType === "regular";
      const external = String(emp?.externalArea || "").trim() || "-";
      const parts = [];
      if (isRegular) {
        parts.push(`External: <strong>${escapeHtml(external)}</strong>`);
        parts.push(`External Position: <strong>${escapeHtml(pos)}</strong>`);
      } else {
        parts.push(`Position: <strong>${escapeHtml(pos)}</strong>`);
      }
      parts.push(`Assignment: <strong>${escapeHtml(assign)}</strong>`);
      empDrawerSub.innerHTML = parts.join(" • ");
    }

    if (empCutoffMonthInput && !empCutoffMonthInput.value) {
      if (cutoffMonthInput?.value) {
        empCutoffMonthInput.value = cutoffMonthInput.value;
      } else {
        const now = new Date();
        empCutoffMonthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
      }
    }
    if (empCutoffSelect && !empCutoffSelect.value) {
      empCutoffSelect.value = cutoffSelect?.value || (empCutoffSelect.querySelector('option[value="A"]') ? "A" : "11-25");
    }
    updateEmpCutoffOptionLabels();

    await loadEmpRecords();
    renderEmpRecords();

    // After loading drawer records, update the subheader based on the latest record in this drawer.
    if (empDrawerSub) {
      const empLatest = empRecords
        .slice()
        .sort((a, b) => String(b.date).localeCompare(String(a.date)))[0];
      const assign = empLatest ? assignmentText(empLatest) : "â€”";

      const employmentType = String(emp?.employmentType || "").trim().toLowerCase();
      const isRegular = employmentType === "regular";
      const external = String(emp?.externalArea || "").trim() || "-";
      const parts = [];
      if (isRegular) {
        parts.push(`External: <strong>${escapeHtml(external)}</strong>`);
        parts.push(`External Position: <strong>${escapeHtml(pos)}</strong>`);
      } else {
        parts.push(`Position: <strong>${escapeHtml(pos)}</strong>`);
      }
      parts.push(`Assignment: <strong>${escapeHtml(assign)}</strong>`);
      empDrawerSub.innerHTML = parts.join(" â€¢ ");
    }

    if (empDrawer) {
      empDrawer.classList.add("is-open");
      empDrawer.setAttribute("aria-hidden", "false");
    }
    if (empOverlay) empOverlay.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function closeEmpDrawer() {
    if (empDrawer) {
      empDrawer.classList.remove("is-open");
      empDrawer.setAttribute("aria-hidden", "true");
    }
    if (empOverlay) empOverlay.hidden = true;
    document.body.style.overflow = "";
    currentEmpId = "";
    empRecords = [];
  }

  closeEmpDrawerBtn && closeEmpDrawerBtn.addEventListener("click", closeEmpDrawer);
  closeEmpDrawerFooter && closeEmpDrawerFooter.addEventListener("click", closeEmpDrawer);
  empOverlay && empOverlay.addEventListener("click", closeEmpDrawer);

  empCutoffMonthInput && empCutoffMonthInput.addEventListener("change", async () => {
    updateEmpCutoffOptionLabels();
    await loadEmpRecords();
    renderEmpRecords();
  });
  empCutoffSelect && empCutoffSelect.addEventListener("change", async () => {
    updateEmpCutoffOptionLabels();
    await loadEmpRecords();
    renderEmpRecords();
  });

  function getEmpActiveCutoff() {
    const ym = parseYM(empCutoffMonthInput?.value);
    const cutoffType = empCutoffSelect?.value || "A";
    if (!ym) return null;
    const range = getCutoffRange(ym.y, ym.m, cutoffType);
    return { year: ym.y, month: ym.m, cutoffType, range };
  }

  async function loadEmpRecords() {
    if (!currentEmpId) return;
    const emp = getEmpByNo(currentEmpId);
    const empId = emp?.id || "";
    const empCutoff = getEmpActiveCutoff();

    const qs = new URLSearchParams();
    if (empId) qs.set("employee_id", empId);
    if (empCutoff) {
      qs.set("month", `${empCutoff.year}-${String(empCutoff.month).padStart(2, "0")}`);
      qs.set("cutoff", empCutoff.cutoffType);
    }
    qs.set("per_page", "200");
    qs.set("page", "1");

    const url = `/attendance/records?${qs.toString()}`;
    const res = await apiFetch(url);
    empRecords = Array.isArray(res?.data) ? res.data.map(mapRecordApi) : [];
  }

  function renderEmpRecords() {
    if (!empTbody || !currentEmpId) return;

    const empCutoff = getEmpActiveCutoff();
    if (empCutoffRangeLabel) {
      empCutoffRangeLabel.textContent = empCutoff ? formatRangeLabel(empCutoff.range) : "—";
    }

    const list = empRecords
      .filter(r => !empCutoff || isDateWithinCutoff(r.date, empCutoff.range))
      .slice()
      .sort((a, b) => String(b.date).localeCompare(String(a.date)));

    empTbody.innerHTML = "";

    let totalHours = 0;

    const noTimeStatuses = new Set([
      "Absent",
      "Leave",
      "Unpaid Leave",
      "RNR",
      "Paid Leave",
      "Day Off",
      "Holiday",
      "LOA",
    ]);

    list.forEach(r => {
      const hrs = computeTotalHours(r);
      totalHours += hrs;

      const statusLabel = String(r.status || "").toUpperCase();
      const showStatus = noTimeStatuses.has(r.status);
      const timeInDisplay = showStatus ? statusLabel : fmtTime(r.timeIn);
      const timeOutDisplay = showStatus ? "—" : fmtTime(r.timeOut);

      const areaDisplay = r.assignType === "Field" ? (r.areaPlace || "—") : (r.assignType || "—");

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.date || "—")}</td>
        <td>${escapeHtml(areaDisplay)}</td>
        <td>${escapeHtml(timeInDisplay)}</td>
        <td>${escapeHtml(timeOutDisplay)}</td>
        <td class="num">${escapeHtml(formatHours(hrs))}</td>
      `;
      empTbody.appendChild(tr);
    });

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="5" class="muted small">No attendance records found.</td>`;
      empTbody.appendChild(tr);
    }

    if (empSumTotal) empSumTotal.textContent = String(list.length);
    if (empSumHours) empSumHours.textContent = formatHours(totalHours);
  }

  // =========================================================
  // FINAL INIT
  // =========================================================
  render();
});
