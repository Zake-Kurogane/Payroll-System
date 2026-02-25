import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";

// resources/js/attendance.js
document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();

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
      department: emp.department || "—",
      position: emp.position || "—",
      assignmentType: emp.assignment_type || "",
      areaPlace: emp.area_place || "",
    };
  }

  async function loadEmployees() {
    const rows = await apiFetch("/employees");
    employees = Array.isArray(rows) ? rows.map(mapEmployee) : [];
    employeesById = new Map(employees.map(e => [String(e.id), e]));
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
      bulkStatusSelectInline.innerHTML = `<option value="">Set statusâ€¦</option>` +
        statuses.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    }
    if (f_status) {
      f_status.innerHTML = `<option value="">â€”</option>` +
        statuses.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join("");
    }
  }

  async function loadAttendanceCodes() {
    try {
      const data = await apiFetch("/settings/attendance-codes");
      const statuses = buildStatusListFromCodes(data?.codes || []);
      setStatusOptions(statuses.length ? statuses : buildFallbackStatuses());
    } catch (err) {
      setStatusOptions(buildFallbackStatuses());
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

  function getEmployeeDept(empNo) {
    return getEmpByNo(empNo)?.department || "—";
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
  const cutoffSelect = document.getElementById("cutoffSelect");   // 11-25 or 26-10
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
  async function loadPayrollCalendarSettings() {
    try {
      payrollCalendar = await apiFetch("/settings/payroll-calendar");
    } catch {
      payrollCalendar = null;
    }
  }

  function resolveCutoffDays(year, month, cutoffType) {
    const lastDay = new Date(year, month, 0).getDate();
    const cal = payrollCalendar || {};
    let from = cutoffType === "11-25" ? Number(cal.cutoff_a_from ?? 11) : Number(cal.cutoff_b_from ?? 26);
    let to = cutoffType === "11-25" ? Number(cal.cutoff_a_to ?? 25) : Number(cal.cutoff_b_to ?? 10);
    if (!Number.isFinite(from) || from <= 0) from = 1;
    if (!Number.isFinite(to) || to <= 0) to = cutoffType === "11-25" ? 25 : lastDay;
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

  function updateCutoffOptionLabels() {
    const ym = parseYM(cutoffMonthInput?.value);
    if (!ym || !cutoffSelect) return;
    const optA = cutoffSelect.querySelector('option[value="11-25"]');
    const optB = cutoffSelect.querySelector('option[value="26-10"]');
    if (optA) optA.textContent = "11–25";
    if (optB) optB.textContent = "26–10";
  }

  function updateEmpCutoffOptionLabels() {
    const ym = parseYM(empCutoffMonthInput?.value);
    if (!ym || !empCutoffSelect) return;
    const optA = empCutoffSelect.querySelector('option[value="11-25"]');
    const optB = empCutoffSelect.querySelector('option[value="26-10"]');
    if (optA) optA.textContent = "11–25";
    if (optB) optB.textContent = "26–10";
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
    const cutoffType = cutoffSelect?.value || "11-25";
    if (!ym) return null;
    const range = getCutoffRange(ym.y, ym.m, cutoffType);
    return { year: ym.y, month: ym.m, cutoffType, range };
  }

  // =========================================================
  // RECORDS: API mapping
  // =========================================================
  function mapRecordApi(r) {
    const emp = r.employee_id ? getEmpById(r.employee_id) : null;
    const empNo = r.emp_no || emp?.empNo || "";
    const empName = r.emp_name || emp?.name || (empNo ? empNo : "");
    const dept = r.department || emp?.department || "—";
    const pos = r.position || emp?.position || "—";
    const assignType = r.assignment_type || emp?.assignmentType || "";
    const areaPlace = r.area_place || emp?.areaPlace || "";
    return {
      id: String(r.id),
      employeeId: String(r.employee_id || ""),
      empId: empNo,
      empName: empName,
      department: dept,
      position: pos,
      date: r.date || "",
      timeIn: r.clock_in || "",
      timeOut: r.clock_out || "",
      totalHours: 0,
      otHours: Number(r.ot_hours || 0),
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
      ot_hours: Number(record.otHours || 0),
    };
  }

  async function loadRecords() {
    const rows = await apiFetch("/attendance/records");
    records = Array.isArray(rows) ? rows.map(mapRecordApi) : [];
  }

  // =========================================================
  // FILTERS / TABLE ELEMENTS
  // =========================================================
  const dateInput = document.getElementById("dateInput");
  const searchInput = document.getElementById("searchInput");
  const segBtns = Array.from(
    document.querySelectorAll(".filterbar__right .seg__btn[data-assign]")
  );
  let assignmentFilter = "All";
  const areaPlaceFilterWrap = document.getElementById("areaPlaceFilterWrap");
  const areaPlaceFilter = document.getElementById("areaPlaceFilter");

  const statTotal = document.getElementById("statTotal");
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
  const f_notes = document.getElementById("f_notes");
  const editingId = document.getElementById("editingId");

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
  const empSumOT = document.getElementById("empSumOT");

  const empCutoffMonthInput = document.getElementById("empCutoffMonth");
  const empCutoffSelect = document.getElementById("empCutoffSelect");
  const empCutoffRangeLabel = document.getElementById("empCutoffRangeLabel");

  let currentEmpId = "";

  // =========================================================
  // DATA: current cutoff + records
  // =========================================================
  let activeCutoff = null;
  let records = [];
  // default sorting (hoisted early to avoid TDZ when render runs during init)
  let sortState = { key: "name", dir: "asc" };
  let pageState = { page: 1, rows: 20 };
  let lastFilterSig = "";

  async function refreshActiveCutoffAndLoad() {
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
      if (cutoffSelect && !cutoffSelect.value) {
        cutoffSelect.value = "11-25";
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

      await loadRecords();

      // date filter defaults
      const today = toYMD(new Date());
      if (dateInput) {
        dateInput.value = isDateWithinCutoff(today, c.range) ? today : "";
        dateInput.min = toYMD(c.range.start);
        dateInput.max = toYMD(c.range.end);
      }

      // manual add date constraints
      if (f_date) {
        f_date.min = toYMD(c.range.start);
        f_date.max = toYMD(c.range.end);
      }

      render();
      return;
    }

    // If cutoff UI DOES NOT exist, just load records
    activeCutoff = null;
    await loadRecords();
    render();
  }

  // init cutoff defaults (only if cutoff UI exists)
  (async function initCutoffDefaults() {
    const hasCutoffUI = !!(cutoffMonthInput && cutoffSelect);
    if (hasCutoffUI) {
      await loadPayrollCalendarSettings();
      const now = new Date();
      const ym = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
      cutoffMonthInput.value = ym;

      const day = now.getDate();
      const cutoffBFrom = Number(payrollCalendar?.cutoff_b_from ?? 26);
      cutoffSelect.value = day >= cutoffBFrom ? "26-10" : "11-25";
      updateCutoffOptionLabels();
    }
    await loadEmployees();
    await loadAttendanceCodes();
    initEmployeeSelect();
    if (areaPlaceFilter) populateAreaFilter(areaPlaceFilter);
    await refreshActiveCutoffAndLoad();
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
    if (status === "Paid Leave") return `<span class="chip chip--paid">Paid Leave</span>`;
    if (status === "Half-day") return `<span class="chip chip--halfday">Half-day</span>`;
    if (status === "Day Off") return `<span class="chip chip--off">Day Off</span>`;
    if (status === "Holiday") return `<span class="chip chip--holiday">Holiday</span>`;
    if (status === "LOA") return `<span class="chip chip--loa">LOA</span>`;
    return `<span class="chip chip--leave">${escapeHtml(status || "—")}</span>`;
  }

  function assignmentText(r) {
    if (r.assignType === "Area") return `Area (${r.areaPlace || "—"})`;
    return r.assignType || "—";
  }

  function formatHours(h) {
    const n = Number(h);
    if (!isFinite(n) || n <= 0) return "0";
    return n.toFixed(2);
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
      } else if (key === "department") {
        av = normalize(a.department);
        bv = normalize(b.department);
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
    th.addEventListener("click", () => {
      const key = th.dataset.sort;
      if (!key) return;
      if (sortState.key === key) {
        sortState.dir = sortState.dir === "asc" ? "desc" : "asc";
      } else {
        sortState.key = key;
        sortState.dir = "asc";
      }
      updateSortIcons();
      render();
    });
  });

  updateSortIcons();

  // =========================================================
  // FILTER + RENDER MAIN TABLE
  // =========================================================
  function applyFilters(list) {
    const dateVal = dateInput?.value || "";
    const statusVal = statusFilter?.value || "All";
    const q = normalize(searchInput?.value || "");
    const areaVal = areaPlaceFilter?.value || "All";

    return list.filter(r => {
      const okDate = !dateVal || r.date === dateVal;
      const okStatus = statusVal === "All" || r.status === statusVal;
      const okAssign = assignmentFilter === "All" || r.assignType === assignmentFilter;
      const okArea =
        assignmentFilter !== "Area" ||
        areaVal === "All" ||
        (r.areaPlace || "") === areaVal;

      const text = normalize(
        `${r.empId} ${r.empName} ${r.department || ""} ${r.position || ""} ${r.date} ${r.timeIn || ""} ${r.timeOut || ""} ${r.status} ${assignmentText(r)} ${r.code || ""}`
      );
      const okSearch = !q || text.includes(q);

      return okDate && okStatus && okAssign && okArea && okSearch;
    });
  }

  function getFilterSignature() {
    return [
      dateInput?.value || "",
      statusFilter?.value || "",
      searchInput?.value || "",
      assignmentFilter || "",
      areaPlaceFilter?.value || "",
    ].join("|");
  }

  function applyPagination(list) {
    const total = list.length;
    const rows = Number(attRowsSelect?.value || pageState.rows || 20);
    pageState.rows = rows;
    const totalPages = Math.max(1, Math.ceil(total / rows));
    pageState.page = Math.min(Math.max(1, pageState.page), totalPages);
    const start = (pageState.page - 1) * rows;
    const end = start + rows;
    return { total, totalPages, slice: list.slice(start, end) };
  }

  function render() {
    if (!tbody) return;

    const sig = getFilterSignature();
    if (sig !== lastFilterSig) {
      pageState.page = 1;
      lastFilterSig = sig;
    }

    const filtered = applyFilters(records);
    const sorted = applySorting(filtered);
    const paged = applyPagination(sorted);

    if (statTotal) statTotal.textContent = filtered.length;
    if (statPresent) statPresent.textContent = filtered.filter(r => ["Present", "Half-day"].includes(r.status)).length;
    if (statLate) statLate.textContent = filtered.filter(r => r.status === "Late").length;
    if (statAbsent) statAbsent.textContent = filtered.filter(r => r.status === "Absent").length;
    if (statLeave) {
      const leaveSet = new Set(["Leave", "Unpaid Leave", "Paid Leave", "LOA", "Holiday", "Day Off"]);
      statLeave.textContent = filtered.filter(r => leaveSet.has(r.status)).length;
    }

    if (resultsMeta) resultsMeta.textContent = `Showing ${filtered.length} record(s)`;
    if (attFooterInfo) {
      attFooterInfo.textContent = `Page ${pageState.page} of ${paged.totalPages}`;
    }
    if (attPageTotal) attPageTotal.textContent = `/ ${paged.totalPages}`;
    if (attPageInput) attPageInput.value = String(pageState.page);
    if (attFirst) attFirst.disabled = pageState.page <= 1;
    if (attPrev) attPrev.disabled = pageState.page <= 1;
    if (attNext) attNext.disabled = pageState.page >= paged.totalPages;
    if (attLast) attLast.disabled = pageState.page >= paged.totalPages;

    const selected = new Set(selectedIds());
    tbody.innerHTML = "";

    paged.slice.forEach(r => {
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
        <td>${escapeHtml(r.department || "—")}</td>
        <td>${escapeHtml(r.assignType || "—")}</td>
        <td>${escapeHtml(r.areaPlace || "—")}</td>
        <td>${escapeHtml((r.timeIn || "—") + " / " + (r.timeOut || "—"))}</td>
        <td>${escapeHtml(formatHours(r.otHours))}</td>
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
  attRowsSelect && attRowsSelect.addEventListener("change", () => {
    pageState.page = 1;
    render();
  });
  attFirst && attFirst.addEventListener("click", () => {
    pageState.page = 1;
    render();
  });
  attPrev && attPrev.addEventListener("click", () => {
    pageState.page = Math.max(1, pageState.page - 1);
    render();
  });
  attNext && attNext.addEventListener("click", () => {
    pageState.page = pageState.page + 1;
    render();
  });
  attLast && attLast.addEventListener("click", () => {
    pageState.page = Number.MAX_SAFE_INTEGER;
    render();
  });
  attPageInput && attPageInput.addEventListener("change", () => {
    const n = Number(attPageInput.value || 1);
    pageState.page = Number.isFinite(n) && n > 0 ? Math.floor(n) : 1;
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
  function populateAreaFilter(selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    const fromEmployees = employees
      .filter(e => e.assignmentType === "Area" && e.areaPlace)
      .map(e => e.areaPlace);
    const unique = Array.from(new Set(fromEmployees));

    selectEl.innerHTML =
      `<option value="All" selected>All</option>` +
      unique.map(p => `<option value="${p}">${p}</option>`).join("");

    if (current && (current === "All" || unique.includes(current))) {
      selectEl.value = current;
    }
  }

  function setAreaFilterVisibility(isArea) {
    if (!areaPlaceFilterWrap) return;
    areaPlaceFilterWrap.hidden = !isArea;
    areaPlaceFilterWrap.style.display = isArea ? "" : "none";
  }

  if (areaPlaceFilter) {
    populateAreaFilter(areaPlaceFilter);
    areaPlaceFilter.addEventListener("change", render);
  }
  setAreaFilterVisibility(assignmentFilter === "Area");

  segBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      segBtns.forEach(b => b.classList.remove("is-active"));
      btn.classList.add("is-active");

      assignmentFilter = btn.dataset.assign || "All";
      if (assignmentFilter === "Area") {
        setAreaFilterVisibility(true);
        if (areaPlaceFilter) populateAreaFilter(areaPlaceFilter);
      } else {
        setAreaFilterVisibility(false);
        if (areaPlaceFilter) areaPlaceFilter.value = "All";
      }
      segBtns.forEach(b => b.setAttribute("aria-selected", b === btn ? "true" : "false"));
      render();
    });
  });

  [dateInput, statusFilter].forEach(el => el && el.addEventListener("change", render));
  searchInput && searchInput.addEventListener("input", render);

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

    try {
      const updates = records.filter(r => ids.includes(r.id));
      await Promise.all(updates.map(r =>
        apiFetch(`/attendance/records/${r.id}`, {
          method: "PUT",
          body: JSON.stringify(toApiPayload(r, newStatus)),
        })
      ));
      await loadRecords();
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
  function syncAssignmentFromEmployee() {
    if (!f_employee) return;
    const emp = getEmpById(f_employee.value);
    if (!emp) {
      if (f_assignType) f_assignType.value = "";
      if (f_areaPlace) f_areaPlace.value = "";
      if (areaWrap) areaWrap.hidden = true;
      return;
    }
    if (f_assignType) {
      f_assignType.value = emp.assignmentType || "";
      f_assignType.disabled = true;
    }
    if (f_areaPlace) {
      const areaVal = emp.areaPlace || "";
      if (areaVal && !Array.from(f_areaPlace.options).some(o => o.value === areaVal)) {
        const opt = document.createElement("option");
        opt.value = areaVal;
        opt.textContent = areaVal;
        f_areaPlace.appendChild(opt);
      }
      f_areaPlace.value = areaVal;
      f_areaPlace.disabled = true;
    }
    if (areaWrap) areaWrap.hidden = emp.assignmentType !== "Area";
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
      syncAssignmentFromEmployee();
    }
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    if (drawerOverlay) drawerOverlay.hidden = true;
    document.body.style.overflow = "";
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

    return ok;
  }

  openAddBtn && openAddBtn.addEventListener("click", () => openDrawer("add"));
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);

  f_employee && f_employee.addEventListener("change", () => {
    syncAssignmentFromEmployee();
  });

  // ✅ Save button now updates table immediately
  saveBtn && saveBtn.addEventListener("click", async () => {
    if (!validateForm()) return;

    const id = editingId.value;
    const employeeId = f_employee.value;

    const mapped = mapAttendanceCode("", f_status.value);
    const payload = {
      employee_id: Number(employeeId),
      date: f_date.value,
      status: mapped.status,
      clock_in: null,
      clock_out: null,
      minutes_late: 0,
      minutes_undertime: 0,
      ot_hours: 0,
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
    const area = assignment === "Area" ? (areaPlaceFilter?.value || "") : "";
    return { month, cutoff, assignment, area };
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
    if (!isValidNumber(r.otHours)) issues.push(`Row ${r.rowNo}: OT Hours must be a number`);

    const code = normalizeCode(r.code);
    if (code && !CODE_MAP[code]) {
      issues.push(`Row ${r.rowNo}: Unknown Code "${code}" (add it to CODE_MAP in attendance.js)`);
    }

    const hasCode = !!code;
    const hasStatus = !!String(r.status || "").trim();
    if (!hasCode && !hasStatus) issues.push(`Row ${r.rowNo}: Provide either Code or Status`);
    if (!hasCode && hasStatus && !isValidStatus(r.status)) issues.push(`Row ${r.rowNo}: Invalid status "${r.status}"`);

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
      otHours: get(r, "ot_hours"),
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
        department: r.empId ? getEmployeeDept(r.empId) : "",
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
        <td>${escapeHtml(r.department || "—")}</td>
        <td>${escapeHtml(r.assignType || "—")}</td>
        <td>${escapeHtml(r.areaPlace || "—")}</td>
        <td>${escapeHtml((r.timeIn || "—") + " / " + (r.timeOut || "—"))}</td>
        <td>${escapeHtml(formatHours(r.otHours))}</td>
        <td>${chip(r.status)}</td>
      `;
      previewTbody.appendChild(tr);
    });
  }

  async function uploadImportFile() {
    const file = importFile?.files?.[0];
    if (!file) {
      alert("Please choose an Excel file.");
      return;
    }

    const f = getImportFilters();
    if (!f.month || !f.cutoff) {
      alert("Please select a payroll month and cutoff.");
      return;
    }

    const fd = new FormData();
    fd.append("month", f.month);
    fd.append("cutoff", f.cutoff);
    fd.append("assignment", f.assignment);
    fd.append("area", f.area);
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

      alert("Imported successfully.");
      if (importFile) importFile.value = "";
      setImportUISelected(null);
      await refreshActiveCutoffAndLoad();
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
            ot_hours: Number(r.otHours || 0),
          }),
        });
      }
      await loadRecords();
      closePreview();
      render();
      alert("Imported attendance saved.");
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
    const f = getImportFilters();
    if (!f.month || !f.cutoff) {
      alert("Please select a payroll month and cutoff.");
      return;
    }
    const qs = new URLSearchParams({
      month: f.month,
      cutoff: f.cutoff,
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

  function openEmpDrawer(empId) {
    currentEmpId = empId;

    const emp = getEmpByNo(empId);
    const name = emp?.name || getEmployeeName(empId) || empId;
    const dept = emp?.department || getEmployeeDept(empId) || "—";
    const pos = emp?.position || getEmployeePos(empId) || "—";

    const list = records
      .filter(r => r.empId === empId)
      .slice()
      .sort((a, b) => String(b.date).localeCompare(String(a.date)));

    const latest = list[0];
    const assign = latest ? assignmentText(latest) : "—";

    if (empDrawerTitle) empDrawerTitle.textContent = name;
    if (empDrawerSub) {
      empDrawerSub.innerHTML =
        `Department: <strong>${escapeHtml(dept)}</strong> • ` +
        `Position: <strong>${escapeHtml(pos)}</strong> • ` +
        `Assignment: <strong>${escapeHtml(assign)}</strong>`;
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
      empCutoffSelect.value = cutoffSelect?.value || "11-25";
    }
    updateEmpCutoffOptionLabels();

    renderEmpRecords();

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
  }

  closeEmpDrawerBtn && closeEmpDrawerBtn.addEventListener("click", closeEmpDrawer);
  closeEmpDrawerFooter && closeEmpDrawerFooter.addEventListener("click", closeEmpDrawer);
  empOverlay && empOverlay.addEventListener("click", closeEmpDrawer);

  empCutoffMonthInput && empCutoffMonthInput.addEventListener("change", () => renderEmpRecords());
  empCutoffSelect && empCutoffSelect.addEventListener("change", () => renderEmpRecords());

  function getEmpActiveCutoff() {
    const ym = parseYM(empCutoffMonthInput?.value);
    const cutoffType = empCutoffSelect?.value || "11-25";
    if (!ym) return null;
    const range = getCutoffRange(ym.y, ym.m, cutoffType);
    return { year: ym.y, month: ym.m, cutoffType, range };
  }

  function renderEmpRecords() {
    if (!empTbody || !currentEmpId) return;

    const empCutoff = getEmpActiveCutoff();
    if (empCutoffRangeLabel) {
      empCutoffRangeLabel.textContent = empCutoff ? formatRangeLabel(empCutoff.range) : "—";
    }

    const list = records
      .filter(r => r.empId === currentEmpId)
      .filter(r => !empCutoff || isDateWithinCutoff(r.date, empCutoff.range))
      .slice()
      .sort((a, b) => String(b.date).localeCompare(String(a.date)));

    empTbody.innerHTML = "";

    let totalHours = 0;
    let totalOT = 0;

    const noTimeStatuses = new Set([
      "Absent",
      "Leave",
      "Unpaid Leave",
      "Paid Leave",
      "Day Off",
      "Holiday",
      "LOA",
    ]);

    list.forEach(r => {
      const hrs = computeTotalHours(r);
      const ot = Number(r.otHours || 0) || 0;

      totalHours += hrs;
      totalOT += ot;

      const statusLabel = String(r.status || "").toUpperCase();
      const showStatus = noTimeStatuses.has(r.status);
      const timeInDisplay = showStatus ? statusLabel : (r.timeIn || "—");
      const timeOutDisplay = showStatus ? "—" : (r.timeOut || "—");

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.date || "—")}</td>
        <td>${escapeHtml(timeInDisplay)}</td>
        <td>${escapeHtml(timeOutDisplay)}</td>
        <td class="num">${escapeHtml(formatHours(ot))}</td>
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
    if (empSumOT) empSumOT.textContent = formatHours(totalOT);
  }

  // =========================================================
  // FINAL INIT
  // =========================================================
  render();
});
