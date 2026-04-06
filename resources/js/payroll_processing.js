import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { formatMoney } from "./shared/format";
import { initSettingsSync } from "./shared/settingsSync";
import { initDataSync, getAttendanceUpdatedAt, getEmployeesUpdatedAt } from "./shared/dataSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();
  let autoRefreshTimer = null;
  let autoRefreshRunning = false;
  function scheduleAutoRefresh(reason) {
    if (!currentRun || isLocked()) return;
    if (autoRefreshTimer) clearTimeout(autoRefreshTimer);
    if (stickyHint && reason) stickyHint.textContent = reason;
    autoRefreshTimer = setTimeout(async () => {
      if (autoRefreshRunning) return;
      autoRefreshRunning = true;
      try {
        await refreshAll();
      } catch {
        if (stickyHint) stickyHint.textContent = "Failed to refresh preview automatically.";
      } finally {
        autoRefreshRunning = false;
      }
    }, 250);
  }

  initDataSync({
    onAttendance: () => scheduleAutoRefresh("Attendance updated. Refreshing preview..."),
    onEmployees: () => scheduleAutoRefresh("Employee data updated. Refreshing preview..."),
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState !== "visible") return;
    if (!currentRun || isLocked()) return;
    if (needsAutoRefresh(currentRun.id)) {
      scheduleAutoRefresh("Detected updates. Refreshing preview...");
    }
  });

  // =========================================================
  // API
  // =========================================================
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        ...(options.headers || {}),
      },
      credentials: "same-origin",
      ...options,
    });
    if (!res.ok) {
      let msg = "Request failed.";
      try {
        const data = await res.json();
        msg = data.message || msg;
      } catch {
        // ignore
      }
      throw new Error(msg);
    }
    if (res.status === 204) return null;
    return res.json();
  }

  // =========================================================
  // ELEMENTS
  // =========================================================
  const monthInput = document.getElementById("monthInput");
  const cutoffSelect = document.getElementById("cutoffSelect");
  const assignmentSeg = document.getElementById("assignmentSeg");
  let segBtns = Array.from(
    document.querySelectorAll("#assignmentSeg .seg__btn[data-assign]")
  );
  const payoutBtns = Array.from(
    document.querySelectorAll(".seg__btn[data-pay]")
  );
  const runTypeBtns = Array.from(document.querySelectorAll("#runTypeSeg .seg__btn[data-run-type]"));
  const assignmentFilterEnabled = !!assignmentSeg;
  const runTypeEnabled = runTypeBtns.length > 0;
  let runType = "Internal"; // "Internal" | "External"
  let assignmentFilter = "All";
  let areaPlaceFilter = ""; // string
  let assignmentOptions = [];
  let areaPlacesGrouped = {};
  let openDropdown = null;
  let openDropdownBtn = null;
  let payoutFilter = "All";
  const searchInput = document.getElementById("searchInput");

  const resultsMeta = document.getElementById("resultsMeta");
  const payTbody = document.getElementById("payTbody");
  const checkAll = document.getElementById("checkAll");

  const resetPreviewBtn = document.getElementById("resetPreviewBtn");
  const computeBtn = document.getElementById("computeBtn");
  const processBtn = document.getElementById("processBtn");
  const payslipBtn = document.getElementById("payslipBtn");
  const stickyHint = document.getElementById("stickyHint");
  const toastEl = document.getElementById("toast");

  const runsTbody = document.getElementById("runsTbody");

  // drawer
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const applyAdjBtn = document.getElementById("applyAdjBtn");
  const pwOverlay = document.getElementById("pwOverlay");
  const pwModal = document.getElementById("pwModal");
  const pwTitle = document.getElementById("pwTitle");
  const pwInput = document.getElementById("pwInput");
  const pwSubmit = document.getElementById("pwSubmit");
  const pwCancel = document.getElementById("pwCancel");

  const adjEmpName = document.getElementById("adjEmpName");
  const adjEmpId = document.getElementById("adjEmpId");
  const adjAssign = document.getElementById("adjAssign");
  const adjStatus = document.getElementById("adjStatus");
  const adjEmpKey = document.getElementById("adjEmpKey");

  const adjCashAdvance = document.getElementById("adjCashAdvance");

  const sumBase = document.getElementById("sumBase");
  const sumOtherEarn = document.getElementById("sumOtherEarn");
  const sumOtherDed = document.getElementById("sumOtherDed");
  const sumNetPreview = document.getElementById("sumNetPreview");

  // run UI
  const newRunBtn = document.getElementById("newRunBtn");
  const lockRunBtn = document.getElementById("lockRunBtn");
  const unlockRunBtn = document.getElementById("unlockRunBtn");
  const releaseRunBtn = document.getElementById("releaseRunBtn");

  const runIdEl = document.getElementById("runId");
  const runPeriodEl = document.getElementById("runPeriod");
  const runStatusEl = document.getElementById("runStatus");
  const runCreatedByEl = document.getElementById("runCreatedBy");
  const runCreatedAtEl = document.getElementById("runCreatedAt");
  const runLockedAtEl = document.getElementById("runLockedAt");
  const runReleasedAtEl = document.getElementById("runReleasedAt");

  const sumHeadcount = document.getElementById("sumHeadcount");
  const sumGross = document.getElementById("sumGross");
  const sumDed = document.getElementById("sumDed");
  const sumNet = document.getElementById("sumNet");
  const sumVariance = document.getElementById("sumVariance");

  // pagination
  let payPageSize = 20;
  let payPage = 1;
  const payPrev = document.getElementById("payPrev");
  const payNext = document.getElementById("payNext");
  const payFirst = document.getElementById("payFirst");
  const payLast = document.getElementById("payLast");
  const payPageInput = document.getElementById("payPageInput");
  const payPageTotal = document.getElementById("payPageTotal");
  const payFooterInfo = document.getElementById("payFooterInfo");
  const payRowsSelect = document.getElementById("payRowsSelect");

  // =========================================================
  // HELPERS
  // =========================================================
  const money = (n) => formatMoney(n);
  const escapeHtml = (s) => String(s || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

  const fmtDT = (d) => {
    if (!d) return "—";
    const dt = (d instanceof Date) ? d : new Date(d);
    return dt.toLocaleString();
  };

  const pad2 = (n) => String(n).padStart(2, "0");

  const settingsVersion = "backend-v1";
  const strictAttendanceBeforeRun = true;

  let payrollCalendar = null;

  const LAST_COMPUTE_PREFIX = "payroll_run_compute_";

  function getLastComputeAt(runId) {
    if (!runId) return 0;
    try {
      return Number(localStorage.getItem(`${LAST_COMPUTE_PREFIX}${runId}`) || 0);
    } catch {
      return 0;
    }
  }

  function setLastComputeAt(runId) {
    if (!runId) return;
    try {
      localStorage.setItem(`${LAST_COMPUTE_PREFIX}${runId}`, String(Date.now()));
    } catch {
      // ignore
    }
  }

  function needsAutoRefresh(runId) {
    const dataStamp = Math.max(getAttendanceUpdatedAt(), getEmployeesUpdatedAt());
    if (!dataStamp) return false;
    const lastCompute = getLastComputeAt(runId);
    return dataStamp > lastCompute;
  }

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
    return { from, to };
  }

  function formatCutoffLabel(monthVal, cutoffVal) {
    if (!monthVal) return cutoffVal === "11-25" ? "11–25" : "26–10";
    const [yStr, mStr] = monthVal.split("-");
    const y = Number(yStr);
    const m = Number(mStr);
    const { from, to } = resolveCutoffDays(y, m, cutoffVal);
    if (from > to) {
      const nextMonth = String(m + 1).padStart(2, "0");
      return `${y}-${mStr}-${String(from).padStart(2, "0")} to ${y}-${nextMonth}-${String(to).padStart(2, "0")}`;
    }
    return `${y}-${mStr}-${String(from).padStart(2, "0")} to ${y}-${mStr}-${String(to).padStart(2, "0")}`;
  }

  function formatCutoffDisplay(monthVal, cutoffVal) {
    if (!monthVal) return cutoffVal;
    const [yStr, mStr] = monthVal.split("-");
    const y = Number(yStr);
    const m = Number(mStr);
    const { from, to } = resolveCutoffDays(y, m, cutoffVal);
    const mm = String(m).padStart(2, "0");
    const fromText = `${mm}-${String(from).padStart(2, "0")}-${yStr}`;
    if (from > to) {
      const nextM = m + 1;
      const nextMm = String(nextM).padStart(2, "0");
      const toText = `${nextMm}-${String(to).padStart(2, "0")}-${yStr}`;
      return `${fromText} to ${toText}`;
    }
    const toText = `${mm}-${String(to).padStart(2, "0")}-${yStr}`;
    return `${fromText} to ${toText}`;
  }

  function updateCutoffOptions() {
    if (!cutoffSelect) return;
    const optA = cutoffSelect.querySelector('option[value="11-25"]');
    const optB = cutoffSelect.querySelector('option[value="26-10"]');
    if (optA) optA.textContent = "11–25";
    if (optB) optB.textContent = "26–10";
  }

  async function checkAttendanceBeforeRunCreate() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "11-25";
    if (!monthVal) {
      return { has_attendance: false, message: "Select month first." };
    }

    return apiFetch(`/payroll-runs/attendance-check?${new URLSearchParams({
      period_month: monthVal,
      cutoff: cutoffVal,
      assignment_filter: assignmentFilterEnabled ? (assignmentFilter || "All") : "All",
      area_place_filter: assignmentFilterEnabled ? (areaPlaceFilter || "") : "",
      run_type: runTypeEnabled ? (runType || "Internal") : "Internal",
    }).toString()}`);
  }

  async function loadFilterOptions() {
    try {
      const data = await apiFetch("/employees/filters");
      assignmentOptions = Array.isArray(data.assignments) ? data.assignments : [];
      const grouped = (data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places))
        ? data.area_places : {};
      areaPlacesGrouped = grouped;

        if (assignmentFilterEnabled) {
          if (!assignmentOptions.length) {
          assignmentOptions = ["Davao", "Tagum", "Field"];
          }
          buildAssignmentSeg();
          bindAssignmentSeg();
          syncSegButtons();
        }
    } catch {
      if (assignmentFilterEnabled) {
        if (!assignmentOptions.length) {
          assignmentOptions = ["Davao", "Tagum", "Field"];
        }
        areaPlacesGrouped = areaPlacesGrouped || {};
        buildAssignmentSeg();
        bindAssignmentSeg();
        syncSegButtons();
      }
    }
  }

  function mapRowFromApi(r) {
    const ov = r.override || {};
    return {
      empId: r.emp_no || String(r.employee_id),
      employeeId: r.employee_id,
      name: r.name,
      dept: r.department || "",
      assign: r.assignment || "",
      areaPlace: r.area_place || "",
      dailyRate: Number(r.daily_rate || 0),
      basicPayMonthly: Number(r.basic_pay_monthly || 0),
      attendanceDeduction: Number(r.attendance_deduction || 0),
      lateMinutes: Number(r.late_minutes || 0),
      undertimeMinutes: Number(r.undertime_minutes || 0),
      lateDeduction: Number(r.late_deduction || 0),
      undertimeDeduction: Number(r.undertime_deduction || 0),
      absentDeduction: Number(r.absent_deduction || 0),
      sss: Number(r.sss_ee || 0),
      ph: Number(r.philhealth_ee || 0),
      pagibig: Number(r.pagibig_ee || 0),
      tax: Number(r.tax || 0),
      sssEr: Number(r.sss_er || 0),
      phEr: Number(r.philhealth_er || 0),
      piEr: Number(r.pagibig_er || 0),
      erShare: Number(r.employer_share_total || 0),
      gross: Number(r.gross || 0),
      deductions: Number(r.deductions_total || 0),
      net: Number(r.net_pay || 0),
      halfBasic: Number(r.basic_pay_cutoff || 0),
      halfAllowance: Number(r.allowance_cutoff || 0),
      present: Number(r.present_days || 0),
      absent: Number(r.absent_days || 0),
      leave: Number(r.leave_days || 0),
      payoutMethod: r.payout_method || r.pay_method || "CASH",
      accountMasked: r.account_masked || r.bank_account_masked || "",
      adjustments: Array.isArray(r.adjustments) ? r.adjustments : (ov.adjustments || []),
      cashAdvance: Number(r.cash_advance || 0),
      chargesDeduction: Number(r.charges_deduction || 0),
      loanDeduction: Number(r.loan_deduction || 0),
      loanItems: Array.isArray(r.loan_items) ? r.loan_items : [],
      status: r.status || "Ready",
    };
  }

  const assignmentLabel = (e) => {
    if (e.areaPlace) return `${e.assignType || "—"} (${e.areaPlace})`;
    return e.assignType || "—";
  };

  // =========================================================
  // RUN STATE (backend-driven)
  // =========================================================
  // status: Draft | Locked | Released
  let currentRun = null;

  // Runs history
  let processedRuns = [];

  // =========================================================
  // PREVIEW + OVERRIDES
  // =========================================================
  let previewRows = [];
  // overrides per employee for this run (sticky even after recompute)
  // shape:
  // { empId: { adjustments:[{type,name,amount}], cashAdvance } }
  let overrides = {};

  // drawer local state
  let adjustmentRows = []; // edits for drawer only (synced into overrides on Apply)

  // =========================================================
  // RUN GUARDS
  // =========================================================
  function askPassword(title = "Enter Password") {
    if (!pwOverlay || !pwModal || !pwInput || !pwSubmit || !pwCancel) {
      const fallback = prompt(title);
      return Promise.resolve(fallback || "");
    }

    return new Promise((resolve) => {
      if (pwTitle) pwTitle.textContent = title;
      pwInput.value = "";

      const cleanup = () => {
        pwModal.classList.remove("is-open");
        pwModal.setAttribute("aria-hidden", "true");
        pwOverlay.setAttribute("hidden", "");
        pwSubmit.removeEventListener("click", onSubmit);
        pwCancel.removeEventListener("click", onCancel);
        pwOverlay.removeEventListener("click", onCancel);
        document.removeEventListener("keydown", onKey);
      };

      const onSubmit = () => {
        const val = pwInput.value || "";
        cleanup();
        resolve(val);
      };
      const onCancel = () => {
        cleanup();
        resolve("");
      };
      const onKey = (e) => {
        if (e.key === "Escape") return onCancel();
        if (e.key === "Enter") return onSubmit();
      };

      pwOverlay.removeAttribute("hidden");
      pwModal.classList.add("is-open");
      pwModal.setAttribute("aria-hidden", "false");
      setTimeout(() => pwInput.focus(), 0);

      pwSubmit.addEventListener("click", onSubmit);
      pwCancel.addEventListener("click", onCancel);
      pwOverlay.addEventListener("click", onCancel);
      document.addEventListener("keydown", onKey);
    });
  }
  function isLocked() {
    return currentRun && (currentRun.status === "Locked" || currentRun.status === "Released");
  }

  function showToast(message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add("is-show");
    setTimeout(() => {
      toastEl.classList.remove("is-show");
    }, 1800);
  }

  function setInputsEnabled(enabled) {
    // filters
    if (monthInput) monthInput.disabled = !enabled;
    if (cutoffSelect) cutoffSelect.disabled = !enabled;
    if (searchInput) searchInput.disabled = !enabled;
    segBtns.forEach(b => b.disabled = !enabled);
    payoutBtns.forEach(b => b.disabled = !enabled);
    runTypeBtns.forEach(b => b.disabled = !enabled);

    // actions
    if (computeBtn) computeBtn.disabled = !enabled;
    if (resetPreviewBtn) resetPreviewBtn.disabled = !enabled;

    // process button becomes lock
    if (processBtn) processBtn.disabled = !enabled;

    // table inputs are handled during renderTable via processedLock
  }

  function syncRunButtons() {
    const locked = isLocked();
    if (lockRunBtn) lockRunBtn.disabled = locked; // can't lock again if locked/released
    if (unlockRunBtn) unlockRunBtn.disabled = !(currentRun && currentRun.status === "Locked"); // released runs stay locked
    if (releaseRunBtn) releaseRunBtn.disabled = !(currentRun && currentRun.status === "Locked");
    if (payslipBtn) payslipBtn.disabled = !(currentRun && (currentRun.status === "Locked" || currentRun.status === "Released"));
  }

  function applyRunUi() {
    if (!currentRun) return;

    if (runIdEl) runIdEl.textContent = currentRun.run_code || currentRun.id;
    if (runPeriodEl) {
      const monthVal = currentRun.period_month || "";
      const cutoffVal = currentRun.cutoff || "11-25";
      runPeriodEl.textContent = formatCutoffDisplay(monthVal, cutoffVal);
    }
    if (runStatusEl) runStatusEl.textContent = currentRun.status;
    if (runCreatedByEl) runCreatedByEl.textContent = currentRun.created_by_name || currentRun.created_by || "—";
    if (runCreatedAtEl) runCreatedAtEl.textContent = fmtDT(currentRun.created_at);
    if (runLockedAtEl) runLockedAtEl.textContent = currentRun.locked_at ? fmtDT(currentRun.locked_at) : "—";
    if (runReleasedAtEl) runReleasedAtEl.textContent = currentRun.released_at ? fmtDT(currentRun.released_at) : "—";

    setInputsEnabled(!isLocked());
    syncRunButtons();
  }

  function computePeriodKey() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "11-25";
    return `${monthVal}|${cutoffVal}`;
  }

  function computePeriodLabel() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "11-25";
    return `${monthVal} (${formatCutoffLabel(monthVal, cutoffVal)})`;
  }

  function prevCutoffKey() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "11-25";

    // simple: previous cutoff within same month if possible
    // if current is 26-10 => previous is 11-25 same month
    // if current is 11-25 => previous is 26-10 previous month (rough)
    if (cutoffVal === "26-10") return `${monthVal}|11-25`;

    // previous month 26-10
    if (!monthVal) return "";
    const [yStr, mStr] = monthVal.split("-");
    const y = Number(yStr);
    const m = Number(mStr);
    let py = y, pm = m - 1;
    if (pm <= 0) { pm = 12; py = y - 1; }
    const prevMonth = `${py}-${String(pm).padStart(2, "0")}`;
    return `${prevMonth}|26-10`;
  }

  function findPrevRunForVariance() {
    const key = prevCutoffKey();
    if (!key) return null;
    // latest run that matches prev cutoff key (Locked or Released)
    return processedRuns.find(r => `${r.period_month}|${r.cutoff}` === key && (r.status === "Locked" || r.status === "Released")) || null;
  }

  // =========================================================
  // FILTERED ROWS
  // =========================================================
  function filteredRows() {
    const q = (searchInput?.value || "").trim().toLowerCase();
    const norm = (v) => String(v || "").trim().toLowerCase();

    return previewRows.filter(r => {
      const payout = (r.payoutMethod || "").toLowerCase() === "bank" ? "Bank" : "Cash";
      const okPayout = payoutFilter === "All" || payout === payoutFilter;
      const text = `${r.empId} ${r.name} ${r.dept} ${r.assign} ${r.areaPlace || ""}`.toLowerCase();
      const okQ = !q || text.includes(q);
      const okAssign = assignmentFilter === "All"
        ? true
        : (String(r.assign || "") === String(assignmentFilter)
          && (!areaPlaceFilter || String(r.areaPlace || "") === String(areaPlaceFilter)));
      return okPayout && okQ && okAssign;
    });
  }

  // =========================================================
  // COMPUTE (backend)
  // =========================================================
  async function computePreview() {
    if (!currentRun) return;
    const payload = await apiFetch(`/payroll-runs/${currentRun.id}/compute`, {
      method: "POST",
      body: JSON.stringify({}),
    });
    previewRows = (payload?.rows || []).map(mapRowFromApi);
    overrides = {};
    previewRows.forEach(r => {
      overrides[r.empId] = {
        otOverrideOn: !!r.otOverrideOn,
        cashAdvance: r.cashAdvance ?? 0,
        adjustments: Array.isArray(r.adjustments) ? r.adjustments : [],
      };
    });
    setLastComputeAt(currentRun.id);
    if (stickyHint) {
      const noAttendance = payload?.meta?.empty_reason === "no_attendance";
      stickyHint.textContent = noAttendance
        ? "No attendance records found for this cutoff yet. Preview is empty."
        : (isLocked()
          ? "Run is locked. Viewing snapshot."
          : "Preview computed. Review rows, then lock the run.");
    }
  }

  // =========================================================
  // SUMMARY + VARIANCE
  // =========================================================
  function computeSummaryFromRows(rows) {
    const sum = (arr, key) => arr.reduce((a, r) => a + Number(r[key] || 0), 0);
    const headcount = rows.length;
    const gross = sum(rows, "gross");
    const deductions = sum(rows, "deductions");
    const net = sum(rows, "net");
    return { headcount, gross, deductions, net };
  }

  function renderRunSummary() {
    if (!currentRun) return;

    const rows = previewRows;

    const s = computeSummaryFromRows(rows);

    if (sumHeadcount) sumHeadcount.textContent = String(s.headcount);
    if (sumGross) sumGross.textContent = money(s.gross);
    if (sumDed) sumDed.textContent = money(s.deductions);
    if (sumNet) sumNet.textContent = money(s.net);

    if (sumVariance) sumVariance.textContent = "—";
  }

  // =========================================================
  // SELECTION HELPERS
  // =========================================================
  const selectedEmpIds = new Set();

  function selectedIds() {
    return Array.from(selectedEmpIds);
  }

  function syncCheckAll() {
    if (!checkAll) return;
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    const checked = checks.filter(c => c.checked);
    if (!checks.length) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
      return;
    }
    checkAll.checked = checked.length === checks.length;
    checkAll.indeterminate = checked.length > 0 && checked.length < checks.length;
  }

  // =========================================================
  // TABLE RENDER
  // =========================================================
  let sortState = { key: "name", dir: "asc" };

  function normalize(v) {
    return String(v || "").toLowerCase();
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
      renderTable();
    });
  });
  updateSortIcons();

  let overrideTimer = null;
  function scheduleOverrideSave(empId) {
    if (overrideTimer) clearTimeout(overrideTimer);
    overrideTimer = setTimeout(async () => {
      const ov = overrides[empId] || {};
      try {
        await saveOverride(empId, {
          otOverrideOn: !!ov.otOverrideOn,
          otOverrideHours: ov.otOverrideHours ?? 0,
          cashAdvance: ov.cashAdvance ?? 0,
          adjustments: ov.adjustments || [],
        });
        renderTable();
        renderRunSummary();
      } catch (err) {
        alert(err.message || "Failed to save override.");
      }
    }, 400);
  }

  function handleTableChange(e) {
    const cb = e.target.closest(".rowCheck");
    if (!cb) return;
    if (cb.checked) selectedEmpIds.add(cb.dataset.id);
    else selectedEmpIds.delete(cb.dataset.id);
    syncCheckAll();
    renderRunSummary();
  }

  function handleTableInput(e) {
    const inp = e.target;
    if (!inp || isLocked()) return;

    if (inp.classList.contains("otIn") || inp.classList.contains("dedIn")) {
      return;
    }
  }

  function handleTableClick(e) {
    const btn = e.target.closest(".adjBtn");
    if (!btn) return;
    openAdjust(btn.dataset.id);
  }

  function renderTable() {
    if (!payTbody) return;

    const locked = isLocked();
    const filtered = filteredRows();
    const totalPages = Math.max(1, Math.ceil(filtered.length / payPageSize));
    payPage = Math.min(payPage, totalPages);
    const start = (payPage - 1) * payPageSize;

    let list = filtered.slice();
    if (sortState.key === "name") {
      const mul = sortState.dir === "asc" ? 1 : -1;
      list.sort((a, b) => normalize(a.name).localeCompare(normalize(b.name)) * mul);
    }
    list = list.slice(start, start + payPageSize);

    if (resultsMeta) resultsMeta.textContent = `Showing ${filteredRows().length} employee(s)`;
    if (payFooterInfo) payFooterInfo.textContent = `Page ${payPage} of ${totalPages}`;
    if (payPageInput) payPageInput.value = payPage;
    if (payPageTotal) payPageTotal.textContent = `/ ${totalPages}`;
    if (payPrev) payPrev.disabled = payPage <= 1;
    if (payNext) payNext.disabled = payPage >= totalPages;

    const selected = new Set(selectedIds());
    payTbody.innerHTML = "";

    list.forEach(r => {
        const tr = document.createElement("tr");
        const monthlyBase = Number(r.basicPayMonthly || 0) > 0
          ? Number(r.basicPayMonthly || 0)
          : (Number(r.dailyRate || 0) * 26);
      const disabled = locked ? "disabled" : "";

      const attText = `${r.present}/${r.absent}/${r.leave}`;
      const deptLine = [r.dept, (r.areaPlace || r.assign)].filter(Boolean).join(" • ");
      const payoutLine = `${r.payoutMethod}${r.payoutMethod === "BANK" ? ` (${r.accountMasked})` : ""}`;

      const statEE = Number(r.sss || 0) + Number(r.ph || 0) + Number(r.pagibig || 0) + Number(r.tax || 0);
      const statER = Number(r.sssEr || 0) + Number(r.phEr || 0) + Number(r.piEr || 0);
      const statERTotal = Number(r.erShare || 0);
      const cashAdvanceDeduction = Number(r.cashAdvance || 0);
      const loansTotal = Number(r.loanDeduction || 0) + cashAdvanceDeduction;

      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${r.empId}" ${selected.has(r.empId) ? "checked" : ""} aria-label="Select ${r.empId}" ${locked ? "disabled" : ""}>
        </td>
        <td>${r.empId}</td>
        <td class="nameCell">
          <div class="nm">${r.name}</div>
          ${deptLine ? `<div class="muted small subinfo">${deptLine}</div>` : ""}
          <div class="muted tiny subinfo">Basic Pay: ${money(monthlyBase)}</div>
          <div class="muted tiny subinfo">Allow (cutoff): ${money(r.halfAllowance)}</div>
          <div class="muted tiny subinfo">Payout: ${payoutLine}</div>
        </td>
        <td>${attText}</td>
        <td class="num">${money(r.dailyRate)}</td>

        <td class="num">
          <details class="dd">
            <summary>Total: ${money(r.attendanceDeduction)}</summary>
            <div class="dd__body">
              <div>Late: ${money(r.lateDeduction || 0)} <span class="muted">(${Number(r.lateMinutes || 0)} min)</span></div>
              <div>Undertime: ${money(r.undertimeDeduction || 0)} <span class="muted">(${Number(r.undertimeMinutes || 0)} min)</span></div>
              ${(Number(r.absentDeduction || 0) > 0) ? `<div>Absent: ${money(r.absentDeduction || 0)}</div>` : ""}
              <div><strong>Total: ${money(r.attendanceDeduction)}</strong></div>
            </div>
          </details>
        </td>

        <td class="num">${r.chargesDeduction > 0
          ? `<details class="dd"><summary>${money(r.chargesDeduction)}</summary><div class="dd__body"><div>This cutoff: ${money(r.chargesDeduction)}</div></div></details>`
          : `<span class="muted">—</span>`
        }</td>
        <td class="num">${loansTotal > 0
          ? `<details class="dd"><summary>${money(loansTotal)}</summary><div class="dd__body">
            ${cashAdvanceDeduction > 0 ? `<div>Cash Advance: ${money(cashAdvanceDeduction)}</div>` : ``}
            ${(r.loanItems || []).map(li => {
              const name = li.loan_type || 'Loan';
              const amt = money(li.deducted_amount || 0);
              const sched = money(li.scheduled_amount || 0);
              const status = li.status || 'scheduled';
              return `<div>${name}: ${amt} <span class=\"muted\">(sched ${sched}, ${status})</span></div>`;
            }).join('') || `<div>Loan schedules: ${money(r.loanDeduction || 0)}</div>`}</div></details>`
          : `<span class="muted">—</span>`
        }</td>

        <td class="num">
          <details class="dd">
            <summary>${money(statEE)}</summary>
            <div class="dd__body">
              <div>SSS: ${money(r.sss)}</div>
              <div>PhilHealth: ${money(r.ph)}</div>
              <div>Pag-IBIG: ${money(r.pagibig)}</div>
              <div>Tax: ${money(r.tax)}</div>
            </div>
          </details>
        </td>

        <td class="num">
          <details class="dd">
            <summary>${money(statERTotal)}</summary>
            <div class="dd__body">
              <div>SSS (ER): ${money(r.sssEr || 0)}</div>
              <div>PhilHealth (ER): ${money(r.phEr || 0)}</div>
              <div>Pag-IBIG (ER): ${money(r.piEr || 0)}</div>
            </div>
          </details>
        </td>

        <td class="num netPayCell"><strong>${money(r.net)}</strong></td>
        <td class="col-actions">
          <button class="iconbtn adjBtn" type="button" data-id="${r.empId}" ${disabled} title="Adjust">⚙</button>
        </td>
      `;

      payTbody.appendChild(tr);
    });

    syncCheckAll();
  }

  if (payTbody) {
    payTbody.addEventListener("change", handleTableChange);
    payTbody.addEventListener("input", handleTableInput);
    payTbody.addEventListener("click", handleTableClick);
  }

  // pagination controls
  payPrev && payPrev.addEventListener("click", () => {
    if (payPage > 1) { payPage--; renderTable(); }
  });
  payNext && payNext.addEventListener("click", () => {
    payPage++; renderTable();
  });
  payFirst && payFirst.addEventListener("click", () => {
    payPage = 1; renderTable();
  });
  payLast && payLast.addEventListener("click", () => {
    payPage = Math.max(1, Math.ceil(previewRows.length / payPageSize));
    renderTable();
  });
  payPageInput && payPageInput.addEventListener("change", () => {
    const val = Math.max(1, Number(payPageInput.value || 1));
    payPage = val;
    renderTable();
  });
  payRowsSelect && payRowsSelect.addEventListener("change", () => {
    payPageSize = Math.max(1, Number(payRowsSelect.value || 20));
    payPage = 1;
    renderTable();
  });

  // =========================================================
  // DRAWER: adjustments list
  // =========================================================
  function renderAdjustments() {
    const container = document.getElementById("adjustmentList");
    if (!container) return;

    container.innerHTML = "";

    adjustmentRows.forEach((row, index) => {
      const div = document.createElement("div");
      div.className = "adjRow";

      div.innerHTML = `
        <div class="adjRow__top">
          <label class="adjLabel">
            <span>Type</span>
            <select data-index="${index}" class="adjType">
              <option value="earning" ${row.type === "earning" ? "selected" : ""}>Earning</option>
              <option value="deduction" ${row.type === "deduction" ? "selected" : ""}>Deduction</option>
            </select>
          </label>

          <label class="adjLabel">
            <span>Name</span>
            <input type="text" placeholder="Name"
              value="${row.name || ""}"
              data-index="${index}" class="adjName" />
          </label>
        </div>

        <div class="adjRow__bottom">
          <label class="adjLabel adjLabel--amount">
            <span>Amount</span>
            <span class="moneyInput">
              <span class="moneyPrefix">₱</span>
              <input type="number" min="0" step="0.01"
                value="${Number(row.amount || 0)}"
                data-index="${index}" class="adjAmount" />
            </span>
          </label>

          <button type="button" data-index="${index}" class="iconbtn delAdjBtn" aria-label="Remove adjustment">🗑</button>
        </div>
      `;

      container.appendChild(div);
    });

    wireAdjustmentEvents();
  }

  function wireAdjustmentEvents() {
    document.querySelectorAll(".adjType").forEach(el => {
      el.addEventListener("change", e => {
        const i = e.target.dataset.index;
        adjustmentRows[i].type = e.target.value;
        updateDrawerSummary();
      });
    });

    document.querySelectorAll(".adjName").forEach(el => {
      el.addEventListener("input", e => {
        const i = e.target.dataset.index;
        adjustmentRows[i].name = e.target.value;
      });
    });

    document.querySelectorAll(".adjAmount").forEach(el => {
      el.addEventListener("input", e => {
        const i = e.target.dataset.index;
        adjustmentRows[i].amount = Number(e.target.value || 0);
        updateDrawerSummary();
      });
    });

    document.querySelectorAll(".delAdjBtn").forEach(el => {
      el.addEventListener("click", e => {
        const i = e.target.dataset.index;
        adjustmentRows.splice(i, 1);
        renderAdjustments();
        updateDrawerSummary();
      });
    });
  }

  document.getElementById("addAdjustmentBtn")?.addEventListener("click", () => {
    if (isLocked()) return;
    adjustmentRows.push({ type: "earning", name: "", amount: 0 });
    renderAdjustments();
    updateDrawerSummary();
  });

  // =========================================================
  // DRAWER OPEN/CLOSE
  // =========================================================
  function openDrawer() {
    if (!drawer || !drawerOverlay) return;
    drawer.classList.add("is-open");
    drawerOverlay.hidden = false;
    drawer.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeDrawer() {
    if (!drawer || !drawerOverlay) return;
    drawer.classList.remove("is-open");
    drawerOverlay.hidden = true;
    drawer.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  // =========================================================
  // DRAWER: open + summary
  // =========================================================
  function openAdjust(empId) {
    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    if (adjEmpName) adjEmpName.textContent = row.name;
    if (adjEmpId) adjEmpId.textContent = row.empId;
    if (adjAssign) adjAssign.textContent = row.assign;
    if (adjStatus) adjStatus.textContent = isLocked() ? "Locked" : "Draft";
    if (adjEmpKey) adjEmpKey.value = row.empId;

    // load overrides into drawer
    const ov = overrides[empId] || {
      cashAdvance: row.cashAdvance ?? 0,
      adjustments: Array.isArray(row.adjustments) ? row.adjustments : [],
    };

    // adjustments list
    adjustmentRows = Array.isArray(ov.adjustments) ? ov.adjustments.map(a => ({ ...a })) : [];
    renderAdjustments();

    // cash advance
    if (adjCashAdvance) {
      adjCashAdvance.value = Number(ov.cashAdvance ?? 0);
      adjCashAdvance.disabled = isLocked();
    }

    // disable add/apply when locked
    if (applyAdjBtn) applyAdjBtn.disabled = isLocked();
    updateDrawerSummary();
    openDrawer();
  }

  function updateDrawerSummary() {
    const empId = adjEmpKey?.value || "";
    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    const earn = adjustmentRows
      .filter(a => a.type === "earning")
      .reduce((a, r) => a + Number(r.amount || 0), 0);

    const ded = adjustmentRows
      .filter(a => a.type === "deduction")
      .reduce((a, r) => a + Number(r.amount || 0), 0);

    const cash = Number(adjCashAdvance?.value || 0);

    const grossPreview = Number(row.halfBasic || 0) + Number(row.halfAllowance || 0) + earn;
      const dedPreview = Number(row.attendanceDeduction || 0)
        + ded
        + cash
        + Number(row.chargesDeduction || 0)
        + Number(row.loanDeduction || 0)
        + Number(row.sss || 0)
        + Number(row.ph || 0)
        + Number(row.pagibig || 0)
        + Number(row.tax || 0);
    const netPreview = grossPreview - dedPreview;

    if (sumBase) sumBase.textContent = money(Number(row.halfBasic || 0) + Number(row.halfAllowance || 0));
    if (sumOtherEarn) sumOtherEarn.textContent = money(earn);
      if (sumOtherDed) sumOtherDed.textContent = money(ded + cash + Number(row.chargesDeduction || 0) + Number(row.loanDeduction || 0));
    if (sumNetPreview) sumNetPreview.textContent = money(netPreview);
  }

  async function saveOverride(empId, payload) {
    if (!currentRun) return;
    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;
    const res = await apiFetch(`/payroll-runs/${currentRun.id}/overrides`, {
      method: "POST",
      body: JSON.stringify({
        employee_id: row.employeeId,
        cash_advance: payload.cashAdvance != null ? Number(payload.cashAdvance || 0) : null,
        adjustments: Array.isArray(payload.adjustments) ? payload.adjustments : [],
      }),
    });

    const mapped = mapRowFromApi(res);
    const idx = previewRows.findIndex(r => r.empId === empId);
    if (idx >= 0) previewRows[idx] = mapped;
    overrides[empId] = {
      cashAdvance: mapped.cashAdvance ?? 0,
      adjustments: Array.isArray(mapped.adjustments) ? mapped.adjustments : [],
    };
  }

  async function applyAdjust() {
    if (isLocked()) return;

    const empId = adjEmpKey?.value || "";
    if (!empId) return;

    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    const cash = Number(adjCashAdvance?.value || 0);

    overrides[empId] = {
      ...(overrides[empId] || {}),
      adjustments: adjustmentRows.map(a => ({
        type: a.type,
        name: (a.name || "").trim(),
        amount: Number(a.amount || 0),
      })),
      cashAdvance: cash,
    };

    try {
      await saveOverride(empId, {
        cashAdvance: cash,
        adjustments: overrides[empId]?.adjustments || [],
      });
      renderTable();
      renderRunSummary();
      closeDrawer();
    } catch (err) {
      alert(err.message || "Failed to save adjustments.");
    }
  }

  // drawer events
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);
  applyAdjBtn && applyAdjBtn.addEventListener("click", applyAdjust);

  adjCashAdvance && adjCashAdvance.addEventListener("input", updateDrawerSummary);

  // =========================================================
  // RUN LIFECYCLE
  // =========================================================
  async function createNewRun() {
    if (strictAttendanceBeforeRun) {
      const check = await checkAttendanceBeforeRunCreate();
      if (!check?.has_attendance) {
        if (stickyHint) stickyHint.textContent = "No attendance records found for this cutoff. Run creation is blocked.";
        alert(check?.message || "No attendance records found for this cutoff. Cannot create payroll run.");
        return false;
      }
    }

    if (currentRun && currentRun.status === "Draft") {
      const ok = confirm("Start a new run? This will reset draft overrides for the current period.");
      if (!ok) return false;
    }

    overrides = {};
    previewRows = [];
    selectedEmpIds.clear();

    const payload = await apiFetch("/payroll-runs", {
      method: "POST",
      body: JSON.stringify({
        period_month: monthInput?.value || "",
        cutoff: cutoffSelect?.value || "11-25",
        run_type: runTypeEnabled ? runType : "Internal",
        assignment_filter: assignmentFilterEnabled ? assignmentFilter : "All",
        area_place_filter: assignmentFilterEnabled ? (areaPlaceFilter || null) : null,
      }),
    });

    currentRun = payload;
    if (stickyHint) stickyHint.textContent = payload?.reused
      ? "Existing run loaded. Compute preview then lock."
      : "New run created (Draft). Compute preview then lock.";
    applyRunUi();

    await computePreview();
    renderTable();
    await renderRuns();
    renderRunSummary();
    return true;
  }

  async function loadLatestDraftForSelection() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "11-25";
    const assign = assignmentFilterEnabled ? (assignmentFilter || "All") : "All";
    const areaFilter = assignmentFilterEnabled ? (areaPlaceFilter || null) : null;

    try {
      const runs = await apiFetch("/payroll-runs");
      const match = (runs || []).find(r =>
        r.status === "Draft" &&
        r.period_month === monthVal &&
        r.cutoff === cutoffVal &&
        (!runTypeEnabled || (r.run_type || "Internal") === runType) &&
        (!assignmentFilterEnabled || (r.assignment_filter || "") === assign) &&
        (!assignmentFilterEnabled || (r.area_place_filter || null) === areaFilter)
      );

      currentRun = match || null;
      previewRows = [];
      overrides = {};
      selectedEmpIds.clear();

      if (!currentRun) {
        if (stickyHint) stickyHint.textContent = "No draft run found. Click New Run to start.";
        applyRunUi();
        renderTable();
        renderRunSummary();
        return;
      }

      applyRunUi();
      if (!isLocked()) {
        if (stickyHint) stickyHint.textContent = "Loading latest attendance and rates...";
        await computePreview();
      } else {
        await loadRowsForCurrent();
      }
      renderTable();
      renderRunSummary();
    } catch (err) {
      currentRun = null;
      if (stickyHint) stickyHint.textContent = "Failed to load existing drafts. Click New Run to start.";
      renderTable();
      renderRunSummary();
    }
  }

  async function lockRun() {
    if (!currentRun) {
      await createNewRun();
      if (!currentRun) return;
    }
    if (isLocked()) return;
    currentRun = await apiFetch(`/payroll-runs/${currentRun.id}/lock`, { method: "POST" });
    if (stickyHint) stickyHint.textContent = "Run locked. Inputs disabled. You can generate payslips or release.";
    applyRunUi();
    await renderRuns();
    renderRunSummary();
    renderTable();
  }

  async function unlockRun() {
    if (!currentRun || !isLocked()) return;
    const reason = prompt("Unlock reason (required):");
    if (!reason || !reason.trim()) {
      alert("Unlock cancelled. Reason is required.");
      return;
    }
    const password = await askPassword("Enter your password to unlock this run:");
    if (!password) {
      alert("Unlock cancelled. Password is required.");
      return;
    }
    currentRun = await apiFetch(`/payroll-runs/${currentRun.id}/unlock`, {
      method: "POST",
      body: JSON.stringify({ password }),
    });
    if (stickyHint) stickyHint.textContent = "Run unlocked (Draft). You can edit and lock again.";
    applyRunUi();
    await computePreview();
    renderTable();
    await renderRuns();
    renderRunSummary();
  }

  async function releaseRun() {
    if (!currentRun || currentRun.status !== "Locked") return;
    const ok = confirm("Release this run? This marks it as released (optional) and keeps it locked.");
    if (!ok) return;
    currentRun = await apiFetch(`/payroll-runs/${currentRun.id}/release`, { method: "POST" });
    if (stickyHint) stickyHint.textContent = "Run released. Still locked and immutable.";
    applyRunUi();
    await renderRuns();
    renderRunSummary();
  }

  newRunBtn && newRunBtn.addEventListener("click", () => createNewRun().catch(err => alert(err.message || "Failed to create run.")));
  lockRunBtn && lockRunBtn.addEventListener("click", () => lockRun().catch(err => alert(err.message || "Failed to lock run.")));
  unlockRunBtn && unlockRunBtn.addEventListener("click", () => unlockRun().catch(err => alert(err.message || "Failed to unlock run.")));
  releaseRunBtn && releaseRunBtn.addEventListener("click", () => releaseRun().catch(err => alert(err.message || "Failed to release run.")));

  // Process button = lock run (matches your UX)
  processBtn && processBtn.addEventListener("click", () => {
    const ok = confirm("Process Payroll = Lock/Finalize this run. Continue?");
    if (!ok) return;
    lockRun().catch(err => alert(err.message || "Failed to lock run."));
  });

  // =========================================================
  // RUNS TABLE
  // =========================================================
  async function renderRuns() {
    if (!runsTbody) return;
    runsTbody.innerHTML = "";

    try {
      processedRuns = await apiFetch("/payroll-runs");
    } catch (err) {
      runsTbody.innerHTML = `<tr><td colspan="6" class="muted small">Failed to load runs.</td></tr>`;
      return;
    }

    if (!processedRuns.length) {
      runsTbody.innerHTML = `<tr><td colspan="6" class="muted small">No processed runs yet.</td></tr>`;
      return;
    }

    processedRuns.forEach(r => {
      const periodLabel = `${r.run_code || r.id} ${r.period_month} (${r.cutoff || formatCutoffLabel(r.period_month, r.cutoff)})`;
      const isLocked = r.status === "Locked";
      const isDraft = r.status === "Draft";
      const periodCell = isLocked
        ? `<button class="linkRun" type="button" data-action="unlock" data-id="${r.id}" title="Unlock and edit">${periodLabel}</button>`
        : isDraft
          ? `<button class="linkRun" type="button" data-action="open" data-id="${r.id}" title="Open draft run">${periodLabel}</button>`
          : `<strong>${periodLabel}</strong>`;
      const actionsCell = isDraft
        ? `<button class="linkDanger" type="button" data-action="delete" data-id="${r.id}" title="Delete draft run">Delete</button>`
        : "";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${periodCell}</td>
        <td class="muted small">${r.display_label || r.assignment_filter || ""}</td>
        <td class="num">${r.headcount ?? 0}</td>
        <td class="num">${money(r.net ?? 0)}</td>
        <td><span class="st st--ok">${r.status}</span></td>
        <td class="num">${actionsCell}</td>
      `;
      runsTbody.appendChild(tr);
    });
  }

  runsTbody && runsTbody.addEventListener("click", async (e) => {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;
    const action = btn.getAttribute("data-action");
    const id = btn.getAttribute("data-id");
    const run = processedRuns.find(r => String(r.id) === String(id));
    if (!run) return;
    if (action === "unlock") {
      if (run.status !== "Locked") return;

      const password = await askPassword("Enter your password to unlock this run:");
      if (!password) return;

      try {
        currentRun = await apiFetch(`/payroll-runs/${run.id}/unlock`, {
          method: "POST",
          body: JSON.stringify({ password }),
        });

        if (monthInput) monthInput.value = currentRun.period_month || "";
        if (cutoffSelect) cutoffSelect.value = currentRun.cutoff || "11-25";
        updateCutoffOptions();

        if (runTypeEnabled) {
          runType = currentRun.run_type || "Internal";
        }
        if (assignmentFilterEnabled) {
          assignmentFilter = currentRun.assignment_filter || "All";
          areaPlaceFilter = currentRun.area_place_filter || null;
        }
        syncRunTypeUI();

        if (stickyHint) stickyHint.textContent = "Run unlocked (Draft). You can edit and lock again.";

        applyRunUi();
        await computePreview();
        renderTable();
        renderRunSummary();
        await renderRuns();
      } catch (err) {
        alert(err.message || "Failed to unlock run.");
      }
      return;
    }

    if (action === "open") {
      if (run.status !== "Draft") return;
      currentRun = run;

      if (monthInput) monthInput.value = currentRun.period_month || "";
      if (cutoffSelect) cutoffSelect.value = currentRun.cutoff || "11-25";
      updateCutoffOptions();

      if (runTypeEnabled) {
        runType = currentRun.run_type || "Internal";
      }
      if (assignmentFilterEnabled) {
        assignmentFilter = currentRun.assignment_filter || "All";
        areaPlaceFilter = currentRun.area_place_filter || null;
      }
      syncRunTypeUI();

      if (stickyHint) stickyHint.textContent = "Draft run loaded. You can edit and lock.";

      applyRunUi();
      await computePreview();
      renderTable();
      renderRunSummary();
      await renderRuns();
      return;
    }

    if (action === "delete") {
      if (run.status !== "Draft") return;
      const ok = confirm(`Delete draft payroll run ${run.run_code || run.id}? This cannot be undone.`);
      if (!ok) return;
      try {
        await apiFetch(`/payroll-runs/${run.id}`, { method: "DELETE" });
        if (currentRun && String(currentRun.id) === String(run.id)) {
          currentRun = null;
          overrides = {};
          previewRows = [];
          selectedEmpIds.clear();
          applyRunUi();
          renderTable();
          renderRunSummary();
        }
        await renderRuns();
        if (stickyHint) stickyHint.textContent = "Draft run deleted.";
      } catch (err) {
        alert(err.message || "Failed to delete run.");
      }
      return;
    }
  });

  // =========================================================
  // UI EVENTS
  // =========================================================
  async function loadRowsForCurrent() {
    if (!currentRun) return;
    const rows = await apiFetch(`/payroll-runs/${currentRun.id}/rows`);
    previewRows = (rows || []).map(mapRowFromApi);
    overrides = {};
    previewRows.forEach(r => {
      overrides[r.empId] = {
        otOverrideOn: !!r.otOverrideOn,
        cashAdvance: r.cashAdvance ?? 0,
        adjustments: Array.isArray(r.adjustments) ? r.adjustments : [],
      };
    });
  }

  async function refreshAll() {
    if (currentRun && currentRun.status === "Draft") {
      const monthVal = monthInput?.value || "";
      const cutoffVal = cutoffSelect?.value || "11-25";
      const mismatch = currentRun.period_month !== monthVal
        || currentRun.cutoff !== cutoffVal;
      if (mismatch) {
        currentRun = null;
        await loadLatestDraftForSelection();
        return;
      }
    }

    if (!currentRun) {
      await loadLatestDraftForSelection();
      return;
    }

    if (isLocked()) {
      if (stickyHint) stickyHint.textContent = "Run is locked. Unlock to change anything.";
      await loadRowsForCurrent();
      applyRunUi();
      await renderRuns();
      renderRunSummary();
      renderTable();
      return;
    }

    // Draft: recompute to reflect latest data
    if (currentRun.status === "Draft") {
      if (stickyHint) stickyHint.textContent = "Loading latest attendance and rates...";
      await computePreview();
      applyRunUi();
      renderTable();
      await renderRuns();
      renderRunSummary();
      return;
    }

    await computePreview();
    applyRunUi();
    renderTable();
    await renderRuns();
    renderRunSummary();
  }

  function closeAllDropdowns() {
    if (!assignmentSeg) return;
    assignmentSeg.querySelectorAll(".seg__dropdown").forEach(dd => {
      dd.classList.remove("is-open");
      dd.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function buildAssignmentSeg() {
    if (!assignmentSeg) return;
    const opts = Array.from(new Set(assignmentOptions.filter(x => String(x || "").trim() !== "")));
    assignmentSeg.innerHTML = "";

    const allBtn = document.createElement("button");
    allBtn.className = "seg__btn seg__btn--pay is-active";
    allBtn.type = "button";
    allBtn.dataset.assign = "All";
    allBtn.textContent = "All";
    assignmentSeg.appendChild(allBtn);

    opts.forEach((label) => {
      const places = Array.isArray(areaPlacesGrouped[label]) ? areaPlacesGrouped[label] : [];
      const wrap = document.createElement("div");
      wrap.className = "seg__btn-wrap";

      const btn = document.createElement("button");
      btn.className = "seg__btn seg__btn--pay";
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

      assignmentSeg.appendChild(wrap);
    });

    segBtns = Array.from(assignmentSeg.querySelectorAll(".seg__btn--pay"));
  }

  function bindAssignmentSeg() {
    if (!assignmentSeg) return;
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
        if (isLocked()) return;
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
        areaPlaceFilter = "";
        payPage = 1;
        renderTable();
        renderRunSummary();
        refreshAll().catch(err => alert(err.message || "Failed to refresh."));

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

    assignmentSeg.querySelectorAll(".seg__dropdown-item").forEach(item => {
      item.addEventListener("click", (e) => {
        if (isLocked()) return;
        e.stopPropagation();
        const place = item.getAttribute("data-place") || "";
        const dropdown = item.closest(".seg__dropdown");
        const group = dropdown?.getAttribute("data-group") || "";

        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");

        assignmentFilter = group || assignmentFilter;
        areaPlaceFilter = place;
        closeAllDropdowns();
        payPage = 1;
        renderTable();
        renderRunSummary();
        refreshAll().catch(err => alert(err.message || "Failed to refresh."));
      });
    });

    document.addEventListener("click", (e) => {
      if (!assignmentSeg.contains(e.target)) closeAllDropdowns();
    }, { capture: true });
    window.addEventListener("resize", refreshOpenDropdownPosition);
    window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
    contentScroller && contentScroller.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  }

  function syncSegButtons() {
    segBtns.forEach(b => {
      const matchAssign = (b.dataset.assign || "") === assignmentFilter;
      const isActive = matchAssign;
      b.classList.toggle("is-active", isActive);
      b.setAttribute("aria-selected", isActive ? "true" : "false");
    });
    if (assignmentSeg) {
      assignmentSeg.querySelectorAll(".seg__dropdown-item").forEach(item => {
        const dropdown = item.closest(".seg__dropdown");
        const group = dropdown?.getAttribute("data-group") || "";
        const isActive = group === assignmentFilter && (item.getAttribute("data-place") || "") === (areaPlaceFilter || "");
        item.classList.toggle("is-active", isActive);
      });
    }
    runTypeBtns.forEach(b => {
      const isActive = (b.dataset.runType || "External") === runType;
      b.classList.toggle("is-active", isActive);
      b.setAttribute("aria-selected", isActive ? "true" : "false");
    });
  }

  function syncRunTypeUI() {
    if (!runTypeEnabled) return;
    const isInternal = runType === "Internal";
    const allBtn = document.getElementById("assignAllBtn");
    if (allBtn) allBtn.style.display = isInternal ? "" : "none";
    // External runs cannot use "All" — reset to Tagum if needed
    if (assignmentFilterEnabled) {
      if (!isInternal && assignmentFilter === "All") {
        const firstAssign = segBtns.find(b => (b.dataset.assign || "") !== "All");
        assignmentFilter = firstAssign?.dataset.assign || "All";
        areaPlaceFilter = "";
      }
    }
    syncSegButtons();
  }

  // filters changes (blocked when locked via disabled attribute, but we guard anyway)
  [monthInput, cutoffSelect].forEach(el => {
    el && el.addEventListener("change", () => {
      updateCutoffOptions();
      refreshAll().catch(err => alert(err.message || "Failed to refresh."));
    });
  });

  searchInput && searchInput.addEventListener("input", () => {
    renderTable();
    renderRunSummary();
  });

  // assignment seg is bound after loadFilterOptions

  runTypeBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      if (isLocked()) return;
      runType = btn.dataset.runType || "External";
      syncRunTypeUI();
      payPage = 1;
      renderTable();
      renderRunSummary();
      refreshAll().catch(err => alert(err.message || "Failed to refresh."));
    });
  });

  payoutBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      if (isLocked()) return;
      payoutBtns.forEach(b => b.classList.remove("is-active"));
      btn.classList.add("is-active");
      payoutBtns.forEach(b => b.setAttribute("aria-selected", b === btn ? "true" : "false"));
      payoutFilter = btn.dataset.pay || "All";
      payPage = 1;
      renderTable();
      renderRunSummary();
    });
  });

  // select all
  checkAll && checkAll.addEventListener("change", () => {
    if (isLocked()) return;
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    checks.forEach(cb => {
      cb.checked = checkAll.checked;
      if (checkAll.checked) selectedEmpIds.add(cb.dataset.id);
      else selectedEmpIds.delete(cb.dataset.id);
    });
    syncCheckAll();
    renderRunSummary();
  });

  computeBtn && computeBtn.addEventListener("click", () => {
    if (isLocked()) return;
    const runPromise = currentRun ? Promise.resolve(true) : createNewRun();
    runPromise
      .then((ok) => {
        if (!ok) return false;
        return computePreview().then(() => true);
      })
      .then((ok) => {
        if (!ok) return;
        renderTable();
        renderRunSummary();
        if (stickyHint) {
          stickyHint.textContent = previewRows.length === 0
            ? "No attendance records found for this cutoff yet. Preview is empty."
            : "Preview refreshed. Check totals then lock.";
        }
      })
      .catch(err => alert(err.message || "Failed to compute preview."));
  });

  resetPreviewBtn && resetPreviewBtn.addEventListener("click", () => {
    if (isLocked()) return;
    const ok = confirm("Reset preview edits (drawer overrides) for this run?");
    if (!ok) return;
    overrides = {};
    computePreview()
      .then(() => {
        renderTable();
        renderRunSummary();
        if (stickyHint) stickyHint.textContent = "Preview overrides reset.";
      })
      .catch(err => alert(err.message || "Failed to reset preview."));
  });

  payslipBtn && payslipBtn.addEventListener("click", () => {
    if (!currentRun) return;
    if (!(currentRun.status === "Locked" || currentRun.status === "Released")) return;
    apiFetch(`/payroll-runs/${currentRun.id}/payslips`, { method: "POST" })
      .then((data) => {
        showToast(data?.message || "Payslips generated for the locked payroll run.");
        const go = confirm("Payslips generated. Do you want to go to the Payslips page now?");
        if (go) {
          const url = payslipBtn?.dataset?.payslipUrl || "/payslip";
          const u = new URL(url, window.location.origin);
          u.searchParams.set("run_id", String(currentRun.id));
          if (currentRun.period_month) u.searchParams.set("month", String(currentRun.period_month));
          window.location.href = u.toString();
        }
      })
      .catch(err => alert(err.message || "Failed to generate payslips."));
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeUserMenu();
      if (drawer && drawer.classList.contains("is-open")) closeDrawer();
    }
  });

  // =========================================================
  // INIT
  // =========================================================
  const now = new Date();
  if (monthInput) monthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;

  // init filters + calendar before first run
  Promise.resolve()
    .then(() => loadPayrollCalendarSettings())
    .then(() => loadFilterOptions())
    .then(() => updateCutoffOptions())
    .then(() => loadLatestDraftForSelection())
    .then(() => renderRuns())
    .catch(err => alert(err.message || "Failed to initialize payroll processing."));
});
