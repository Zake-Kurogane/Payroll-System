import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { formatMoney } from "./shared/format";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();
  // =========================================================
  // HELPERS
  // =========================================================
  const $ = (id) => document.getElementById(id);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));
  const normalize = (s) => String(s || "").toLowerCase().trim();

  const peso = (n) => formatMoney(n);

  const escapeHtml = (s) =>
    String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const pad = (n) => String(n).padStart(2, "0");
  const todayISO = () => {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  };

  const normalizeDateTimeInput = (value) => {
    if (!value) return "";
    let s = String(value).trim();
    // Normalize "YYYY-MM-DD HH:mm:ss" -> ISO-ish
    s = s.replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})(.*)$/, "$1T$2$3");
    // Trim microseconds to milliseconds for broader browser compatibility
    s = s.replace(/(\.\d{3})\d+(?=Z?$)/, "$1");
    return s;
  };

  const formatDateTime12h = (value) => {
    if (!value) return "-";
    const raw = String(value).trim();
    const d = new Date(normalizeDateTimeInput(raw));
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    });
  };

  const sumAmounts = (arr) =>
    (Array.isArray(arr) ? arr : []).reduce((a, r) => a + Number(r?.amount || 0), 0);

  const maskAccount = (num) => {
    const raw = String(num || "");
    if (!raw) return "";
    const last4 = raw.slice(-4);
    return raw.length > 4 ? `****${last4}` : raw;
  };
  const normalizeCutoffValue = (value) => {
    const v = String(value || "").trim().toLowerCase();
    if (v === "a" || v === "16-end" || v === "26-10") return "26-10";
    if (v === "b" || v === "1-15" || v === "11-25") return "11-25";
    return "";
  };

  function mapPayslipFromApi(p) {
    const adjustments = Array.isArray(p.adjustments) ? p.adjustments : [];
    const earnAdj = adjustments.filter(a => a.type === "earning");
    const dedAdj = adjustments.filter(a => a.type === "deduction");
    return {
      id: String(p.id),
      runId: String(p.payroll_run_id || ""),
      runCode: p.run_code || "",
      empId: p.emp_no || String(p.employee_id || ""),
      empName: p.emp_name || "",
      department: p.department || "",
      position: p.position || "",
      empType: p.employment_type || "",
      assignmentType: p.assignment_type || "",
      areaPlace: p.area_place || "",
      periodMonth: p.period_month || "",
      cutoff: normalizeCutoffValue(p.cutoff) || String(p.cutoff || ""),
      periodStart: p.period_start || "",
      periodEnd: p.period_end || "",
      netPay: Number(p.net_pay || 0),
      releaseStatus: p.release_status || "Draft",
      payslipNo: p.payslip_no || "",
      generatedDate: p.generated_at ? String(p.generated_at).slice(0, 10) : "",
      payDate: p.pay_date || "-",
      email: p.employee_email || "",
      bankName: p.bank_name || "",
      accountNumber: p.bank_account_number || "",
      payMethod: p.pay_method || "CASH",
      basicPay: Number(p.basic_pay_cutoff || 0),
      allowancePay: Number(p.allowance_cutoff || 0),
      dailyRate: Number(p.daily_rate || 0),
      paidDays: Number(p.paid_days || 0),
      presentDays: Number(p.present_days || 0),
      leaveDays: Number(p.leave_days || 0),
      absentDays: Number(p.absent_days || 0),
      minutesLate: Number(p.minutes_late || 0),
      minutesUndertime: Number(p.minutes_undertime || 0),
      earningsAdjustments: earnAdj.map(a => ({ name: a.name, amount: Number(a.amount || 0) })),
      deductionAdjustments: dedAdj.map(a => ({ name: a.name, amount: Number(a.amount || 0) })),
      attendanceDeductionTotal: Number(p.attendance_deduction || 0),
      chargesDeduction: Number(p.charges_deduction || 0),
      sssEe: Number(p.sss_ee || 0),
      philhealthEe: Number(p.philhealth_ee || 0),
        pagibigEe: Number(p.pagibig_ee || 0),
        withholdingTax: Number(p.tax || 0),
        otherDeductionTotal: 0,
        cashAdvance: Number(p.cash_advance || 0),
        loanDeduction: Number(p.loan_deduction || 0),
      loanItems: Array.isArray(p.loan_items) ? p.loan_items.map(i => ({
        loanType: i.loan_type || "Loan",
        deductedAmount: Number(i.deducted_amount || 0),
        status: i.status || "",
      })) : [],
      loanDetails: Array.isArray(p.loan_details) ? p.loan_details.map(l => ({
        loanNo: l.loan_no || "",
        loanType: l.loan_type || "",
        perCutoff: Number(l.per_cutoff || 0),
        status: l.status || "",
      })) : [],
        sssEr: Number(p.sss_er || 0),
      philhealthEr: Number(p.philhealth_er || 0),
      pagibigEr: Number(p.pagibig_er || 0),
      gross: Number(p.gross || 0),
      deductionsTotal: Number(p.deductions_total || 0),
      notes: "-",
      deliveryStatus: p.delivery_status || "Not Sent",
    };
  }

  function safeText(id, value) {
    const el = $(id);
    if (el) el.textContent = value;
  }

  function fmtShortDate(iso) {
    const raw = String(iso || "").slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return "-";
    const [y, m, d] = raw.split("-");
    return `${m}/${d}/${y}`;
  }

  function minsToHM(mins) {
    const m = Math.max(0, Math.trunc(Number(mins || 0)));
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return `${h}h ${mm}m`;
  }

  function sumLoanDeducted(p, predicate) {
    const list = Array.isArray(p?.loanItems) ? p.loanItems : [];
    return list.reduce((a, it) => {
      const amt = Number(it?.deductedAmount || 0);
      if (amt <= 0) return a;
      const t = String(it?.loanType || "").toLowerCase();
      return predicate(t) ? a + amt : a;
    }, 0);
  }

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

  let payrollCalendar = null;
  async function loadPayrollCalendarSettings() {
    try {
      payrollCalendar = await apiFetch("/settings/payroll-calendar");
    } catch {
      payrollCalendar = null;
    }
  }

  let companySetup = null;
  async function loadCompanySetup() {
    try {
      companySetup = await apiFetch("/settings/company-setup");
    } catch {
      companySetup = null;
    }
  }

  function resolveCutoffDays(year, month, cutoffType) {
    const lastDay = new Date(year, month, 0).getDate();
    const cal = payrollCalendar || {};
    let from = cutoffType === "11-25"
      ? Number(cal.cutoff_a_from ?? 11)
      : Number(cal.cutoff_b_from ?? 26);
    let to = cutoffType === "11-25"
      ? Number(cal.cutoff_a_to ?? 25)
      : Number(cal.cutoff_b_to ?? 10);
    if (!Number.isFinite(from) || from <= 0) from = 1;
    if (!Number.isFinite(to) || to <= 0) to = cutoffType === "11-25" ? 25 : lastDay;
    return { from, to };
  }

  function resolvePayDate(periodMonth, cutoffType) {
    if (!periodMonth) return "-";
    const [yStr, mStr] = periodMonth.split("-");
    let y = Number(yStr);
    let m = Number(mStr);
    if (!y || !m) return "-";
    const cal = payrollCalendar || {};
    const payOnPrev = !!cal.pay_on_prev_workday_if_sunday;
    // Business rule:
    // 1st cutoff = 26-10 (payday 15, next month)
    // 2nd cutoff = 11-25 (payday 30/31 or EOM, same month)
    const isFirst = cutoffType === "26-10";
    const payDay = isFirst ? (cal.pay_date_a ?? 15) : (cal.pay_date_b ?? "EOM");

    if (isFirst) {
      m += 1;
      if (m > 12) {
        m = 1;
        y += 1;
      }
    }

    let date;
    if (String(payDay).toUpperCase() === "EOM") {
      date = new Date(y, m, 0);
    } else {
      const dayNum = Number(payDay) || 1;
      date = new Date(y, m - 1, dayNum);
    }
    if (payOnPrev && date.getDay() === 0) {
      date.setDate(date.getDate() - 1);
    }
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const dd = String(date.getDate()).padStart(2, "0");
    return `${mm}-${dd}-${date.getFullYear()}`;
  }

  function formatCutoffLabel(monthVal, cutoffVal) {
    if (!monthVal) return cutoffVal === "11-25" ? "11-25" : "26-10";
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

  function cutoffDates(periodMonth, cutoffVal) {
    if (!periodMonth) return { from: "-", to: "-" };
    const [yStr, mStr] = String(periodMonth).split("-");
    const y = Number(yStr);
    const m = Number(mStr);
    const { from, to } = resolveCutoffDays(y, m, cutoffVal);
    const mm = String(m).padStart(2, "0");
    const fromText = `${mm}-${String(from).padStart(2, "0")}-${yStr}`;
    if (from > to) {
      const nextM = m + 1;
      const nextMm = String(nextM).padStart(2, "0");
      return { from: fromText, to: `${nextMm}-${String(to).padStart(2, "0")}-${yStr}` };
    }
    return { from: fromText, to: `${mm}-${String(to).padStart(2, "0")}-${yStr}` };
  }

  // =========================================================
  // ELEMENTS (MATCH YOUR HTML)
  // =========================================================
  const monthInput = $("monthInput");
  const cutoffInput = $("cutoffInput");
  const searchInput = $("searchInput");
  const assignmentSeg = $("assignSeg");
  let segBtns = [];
  let openDropdown = null;
  let openDropdownBtn = null;
  let assignDropdownEventsBound = false;

  const runDisplay = $("runDisplay");
  const runEmployees = $("runEmployees");
  const runTotalNet = $("runTotalNet");
  const runProcessedAt = $("runProcessedAt");
  const runProcessedBy = $("runProcessedBy");
  const releaseAllBtn = $("releaseAllBtn");

  const exportPdfBtn = $("exportPdfBtn");
  const exportCsvBtn = $("exportCsvBtn");
  const printBtn = $("printBtn");
  const sendEmailBtn = $("sendEmailBtn");

  const tbody = $("payslipTbody");
  const resultsMeta = $("resultsMeta");

  const bulkBar = $("bulkBar");
  const selectedCount = $("selectedCount");
  const bulkPdfBtn = $("bulkPdfBtn");
  const bulkPrintBtn = $("bulkPrintBtn");
  const bulkReleaseBtn = $("bulkReleaseBtn");
  const bulkCancelBtn = $("bulkCancelBtn");

  const checkAll = $("checkAll");

  const rowsPerPage = $("rowsPerPage");
  const pagePrev = $("pagePrev");
  const pageNext = $("pageNext");
  const firstPage = $("firstPage");
  const lastPage = $("lastPage");
  const pageInput = $("pageInput");
  const totalPages = $("totalPages");
  const pageLabel = $("pageLabel");

  const psDrawer = $("psDrawer");
  const psOverlay = $("psOverlay");
  const psCloseBtn = $("psCloseBtn");
  const psCloseFooterBtn = $("psCloseFooterBtn");
  const psPrintBtn = $("psPrintBtn");
  const psDownloadBtn = $("psDownloadBtn");
  const psReleaseBtn = $("psReleaseBtn");
  const psDrawerMeta = $("psDrawerMeta");

  const printOverlay = $("printOverlay");
  const printModal = $("printModal");
  const printFrame = $("printFrame");
  const printCloseBtn = $("printCloseBtn");

  // new: adjustment containers in preview
  const psEarnAdjRows = $("psEarnAdjRows");
  const psDedAdjRows = $("psDedAdjRows");

  // =========================================================
  // DATA (backend-driven)
  // =========================================================
  let runs = [];
  let allRuns = [];
  let payslips = [];

  // =========================================================
  // STATE
  // =========================================================
  let selectedRunId = "";
  let assignmentFilter = "All";
  let areaSubFilter = "";
  let areaPlaces = {};
  let sortKey = "empName";
  let sortDir = "asc";

  let page = 1;
  let perPage = rowsPerPage ? Number(rowsPerPage.value || 20) : 20;
  let filterLoadingCount = 0;

  const selectedIdsSet = new Set();

  // =========================================================
  // PRINT MODAL
  // =========================================================
  function closePrintModal() {
    if (!printModal || !printOverlay || !printFrame) return;
    printModal.hidden = true;
    printModal.setAttribute("aria-hidden", "true");
    printOverlay.hidden = true;
    printFrame.src = "about:blank";
    const drawerOpen = !!(psDrawer && psDrawer.classList.contains("is-open"));
    document.body.style.overflow = drawerOpen ? "hidden" : "";
  }

  function openPrintModal(url) {
    if (!printModal || !printOverlay || !printFrame) return;
    printFrame.src = url;
    printOverlay.hidden = false;
    printModal.hidden = false;
    printModal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  printCloseBtn && printCloseBtn.addEventListener("click", closePrintModal);
  printOverlay && printOverlay.addEventListener("click", closePrintModal);

  window.addEventListener("message", (e) => {
    if (e.origin !== window.location.origin) return;
    if (e.data && e.data.type === "payslips:afterprint") {
      closePrintModal();
    }
  });

  // =========================================================
  // INIT DEFAULTS
  // =========================================================
  if (monthInput && !monthInput.value) {
    const d = new Date();
    monthInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
  }
  if (cutoffInput && !cutoffInput.value) {
    cutoffInput.value = "All";
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

  // =========================================================
  // ENABLE/DISABLE TOP ACTIONS
  // =========================================================
  function setTopActionsEnabled(enabled) {
    [exportPdfBtn, exportCsvBtn, printBtn, releaseAllBtn, sendEmailBtn].forEach((btn) => {
      if (btn) btn.disabled = !enabled;
    });
    if (checkAll) checkAll.disabled = !enabled;
  }
  setTopActionsEnabled(false);

  function setFilterLoading(isLoading) {
    if (isLoading) filterLoadingCount += 1;
    else filterLoadingCount = Math.max(0, filterLoadingCount - 1);
    const active = filterLoadingCount > 0;
    if (runDisplay) runDisplay.classList.toggle("is-loading", active);
    if (active && resultsMeta) resultsMeta.textContent = "Applying filters...";
  }

  function runDisplayLabel(run) {
    if (!run) return "No payroll run found for the selected month/cutoff.";
    const cutoffLabel = run.cutoff || formatCutoffLabel(run.period_month, run.cutoff);
    const runCode = run.run_code || run.id;
    return `${runCode} ${run.period_month} (${cutoffLabel}) - ${run.assignment_filter || "All"} - ${run.status} - ${run.headcount || 0} employees`;
  }

  function setRunDisplay(run) {
    if (!runDisplay) return;
    runDisplay.textContent = runDisplayLabel(run);
  }

  function pickDefaultRun(allRuns) {
    const list = Array.isArray(allRuns) ? allRuns : [];
    if (!list.length) return null;
    const withData = list.filter((r) => Number(r.headcount || 0) > 0);
    const source = withData.length ? withData : list;
    return source[0] || null; // already ordered desc by backend
  }

  function setRunUI(run) {
    if (!run) {
      safeText("runEmployees", "-");
      safeText("runTotalNet", "-");
      safeText("runProcessedAt", "-");
      safeText("runProcessedBy", "-");
      setRunDisplay(null);
      setTopActionsEnabled(false);
      return;
    }

    setRunDisplay(run);
    safeText("runEmployees", String(run.headcount ?? 0));
    safeText("runTotalNet", peso(run.total_net ?? 0));
    safeText("runProcessedAt", run.locked_at ? formatDateTime12h(run.locked_at) : "-");
    safeText("runProcessedBy", run.created_by_name || "-");
    setTopActionsEnabled(true);
    if (sendEmailBtn) {
      const ok = run.status === "Locked" || run.status === "Released";
      sendEmailBtn.disabled = !ok;
    }
    if (releaseAllBtn) {
      releaseAllBtn.disabled = run.status !== "Locked";
    }
  }

  async function loadRuns(forceRunId = "") {
    try {
      const data = await apiFetch("/payslips/runs");
      allRuns = Array.isArray(data)
        ? data.filter((r) => r && (r.status === "Locked" || r.status === "Released"))
        : [];

      let effectiveMonth = monthInput?.value || "";
      let effectiveCutoff = cutoffInput?.value || "All";

      if (forceRunId) {
        const forced = allRuns.find((r) => String(r.id) === String(forceRunId)) || null;
        if (forced && forced.period_month && monthInput) {
          monthInput.value = forced.period_month;
          effectiveMonth = forced.period_month;
        }
        const forcedCutoff = normalizeCutoffValue(forced?.cutoff);
        if (forcedCutoff && cutoffInput) {
          cutoffInput.value = forcedCutoff;
          effectiveCutoff = forcedCutoff;
        }
      }

      runs = allRuns.filter((r) => {
        const runCutoff = normalizeCutoffValue(r.cutoff) || r.cutoff;
        const okMonth = !effectiveMonth || r.period_month === effectiveMonth;
        const okCutoff = effectiveCutoff === "All" || runCutoff === effectiveCutoff;
        return okMonth && okCutoff;
      });

      return;
    } catch {
      allRuns = [];
      runs = [];
      return;
    }
  }

  function clearSelections() {
    selectedIdsSet.clear();
    $$("input.rowCheck").forEach((cb) => (cb.checked = false));
    if (checkAll) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
    }
  }

  async function syncRunFromFilters({ forceRunId = "", fallbackOnEmpty = false } = {}) {
    setFilterLoading(true);
    try {
      await loadRuns(forceRunId);

      let run = null;
      if (forceRunId) {
        run = runs.find((r) => String(r.id) === String(forceRunId)) || null;
      }
      if (!run && selectedRunId) {
        run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      }
      if (!run) {
        run = pickDefaultRun(runs);
      }

      // Initial-load fallback: if current month/cutoff has no run,
      // show the latest available run and sync filters to it.
      if (!run && fallbackOnEmpty) {
        run = pickDefaultRun(allRuns);
        if (run) {
          if (monthInput) monthInput.value = run.period_month || monthInput.value;
          if (cutoffInput) cutoffInput.value = normalizeCutoffValue(run.cutoff) || cutoffInput.value || "All";
          runs = allRuns.filter((r) => {
            const runCutoff = normalizeCutoffValue(r.cutoff) || r.cutoff;
            return r.period_month === (run.period_month || "") && runCutoff === (normalizeCutoffValue(run.cutoff) || "");
          });
        }
      }

      selectedRunId = run ? String(run.id) : "";
      clearSelections();
      page = 1;
      setRunUI(run);

      if (!run) {
        payslips = [];
        render();
        return;
      }

      if (monthInput) monthInput.value = run.period_month || monthInput.value;
      if (cutoffInput) cutoffInput.value = normalizeCutoffValue(run.cutoff) || cutoffInput.value || "All";
      await loadPayslips(selectedRunId);
    } finally {
      setFilterLoading(false);
    }
  }

  async function loadFilterOptions() {
    try {
      const data = await apiFetch("/employees/filters");
      const assignments = Array.isArray(data.assignments) ? data.assignments : [];
      const grouped = (data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places))
        ? data.area_places
        : {};
      areaPlaces = grouped;

      if (assignmentSeg) {
        assignmentSeg.innerHTML = "";

        const allBtn = document.createElement("button");
        allBtn.className = "seg__btn seg__btn--emp is-active";
        allBtn.type = "button";
        allBtn.dataset.assign = "All";
        allBtn.textContent = "All";
        assignmentSeg.appendChild(allBtn);

        assignments.forEach((label) => {
          const places = Array.isArray(areaPlaces[label]) ? areaPlaces[label] : [];
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

          assignmentSeg.appendChild(wrap);
        });

        segBtns = Array.from(assignmentSeg.querySelectorAll(".seg__btn--emp"));
        bindAssignmentButtons();
      }
    } catch {
      // Fallback: still render assignment filter options if filters endpoint fails.
      const assignments = ["Davao", "Tagum", "Field"];
      areaPlaces = {};

      if (assignmentSeg) {
        assignmentSeg.innerHTML = "";

        const allBtn = document.createElement("button");
        allBtn.className = "seg__btn seg__btn--emp is-active";
        allBtn.type = "button";
        allBtn.dataset.assign = "All";
        allBtn.textContent = "All";
        assignmentSeg.appendChild(allBtn);

        assignments.forEach((label) => {
          const wrap = document.createElement("div");
          wrap.className = "seg__btn-wrap";

          const btn = document.createElement("button");
          btn.className = "seg__btn seg__btn--emp";
          btn.type = "button";
          btn.dataset.assign = label;
          btn.textContent = label;
          wrap.appendChild(btn);

          assignmentSeg.appendChild(wrap);
        });

        segBtns = Array.from(assignmentSeg.querySelectorAll(".seg__btn--emp"));
        bindAssignmentButtons();
      }
    }
  }

  async function loadPayslips(runId) {
    if (!runId) {
      payslips = [];
      render();
      return;
    }
    try {
      const data = await apiFetch(`/payslips?run_id=${encodeURIComponent(runId)}`);
      payslips = Array.isArray(data) ? data.map(mapPayslipFromApi) : [];
      render();
    } catch (err) {
      payslips = [];
      render();
      alert(err.message || "Failed to load payslips.");
    }
  }

  setRunUI(null);

  // =========================================================
  // SORTING + FILTERING
  // =========================================================
  function assignmentText(p) {
    if (p.areaPlace) return `${p.assignmentType || "-"} (${p.areaPlace})`;
    return p.assignmentType || "-";
  }

  function getSortValue(p, key) {
    if (key === "empId") return String(p.empId || "");
    if (key === "empName") return String(p.empName || "");
    if (key === "assignment") return assignmentText(p);
    if (key === "period") return `${p.periodMonth || ""} ${p.cutoff || ""}`;
    if (key === "netPay") return Number(p.netPay || 0);
    if (key === "releaseStatus") return String(p.releaseStatus || "");
    if (key === "delivery") return String(p.deliveryStatus || "");
    return String(p[key] || "");
  }

  function compare(a, b, key) {
    const va = getSortValue(a, key);
    const vb = getSortValue(b, key);
    if (typeof va === "number" && typeof vb === "number") return va - vb;
    return String(va).localeCompare(String(vb));
  }

  function bindHeaderSorting() {
    const ths = $$("th[data-sort]");
    ths.forEach((th) => {
      th.style.cursor = "pointer";
      th.addEventListener("click", () => {
        const key = th.dataset.sort;
        if (!key) return;

        if (sortKey === key) sortDir = sortDir === "asc" ? "desc" : "asc";
        else {
          sortKey = key;
          sortDir = "asc";
        }

        page = 1;
        updateSortIcons();
        render();
      });
    });
    updateSortIcons();
  }

  function updateSortIcons() {
    const ths = $$("th[data-sort]");
    ths.forEach((th) => {
      const key = th.dataset.sort;
      th.classList.remove("is-asc", "is-desc");
      if (key === sortKey) th.classList.add(sortDir === "asc" ? "is-asc" : "is-desc");
    });
  }

  function applyFilters(list) {
    const q = normalize(searchInput?.value || "");
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffInput?.value || "All";
    return list
      .filter((p) => !selectedRunId || p.runId === selectedRunId)
      .filter((p) => !monthVal || p.periodMonth === monthVal)
      .filter((p) => cutoffVal === "All" || normalizeCutoffValue(p.cutoff) === cutoffVal)
      .filter((p) => assignmentFilter === "All" || p.assignmentType === assignmentFilter)
      .filter((p) => {
        if (!areaSubFilter) return true;
        return (p.areaPlace || "") === areaSubFilter;
      })
      .filter((p) => {
        if (!q) return true;
        const text = normalize(
          `${p.empId} ${p.empName} ${p.department || ""} ${p.position || ""} ${assignmentText(p)} ${p.periodMonth} ${p.cutoff} ${p.releaseStatus} ${p.netPay}`
        );
        return text.includes(q);
      });
  }

  function applySorting(list) {
    const sorted = list.slice().sort((a, b) => compare(a, b, sortKey));
    if (sortDir === "desc") sorted.reverse();
    return sorted;
  }

  function paginate(list) {
    perPage = rowsPerPage ? Number(rowsPerPage.value || 20) : perPage;
    const total = list.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    page = Math.min(Math.max(1, page), pages);

    const start = (page - 1) * perPage;
    const end = start + perPage;
    return { pageItems: list.slice(start, end), total, pages };
  }

  // =========================================================
  // TABLE + SELECTION
  // =========================================================
  function badge(status) {
    const s = String(status || "Draft");
    const key = normalize(s);
    let cls = "badge";
    if (key === "released") cls += " badge--success";
    else if (key === "draft") cls += " badge--warn";
    else cls += " badge--info";
    return `<span class="${cls}">${escapeHtml(s)}</span>`;
  }
  function deliveryBadge(status) {
    const s = String(status || "Not sent");
    const key = normalize(s);
    let cls = "badge";
    if (key === "sent") cls += " badge--success";
    else if (key === "queued") cls += " badge--warn";
    else if (key === "not sent") cls += " badge--danger";
    else cls += " badge--info";
    return `<span class="${cls}">${escapeHtml(s)}</span>`;
  }

  function selectedIds() {
    return Array.from(selectedIdsSet);
  }

  function syncCheckAll() {
    if (!checkAll) return;
    const checks = $$("input.rowCheck");
    const checked = checks.filter((x) => x.checked);

    if (!checks.length) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
      return;
    }
    checkAll.checked = checked.length === checks.length;
    checkAll.indeterminate = checked.length > 0 && checked.length < checks.length;
  }

  function updateBulkBar() {
    if (!bulkBar || !selectedCount) return;
    const n = selectedIds().length;
    selectedCount.textContent = String(n);
    const show = n > 0;
    bulkBar.style.display = show ? "flex" : "none";
    bulkBar.setAttribute("aria-hidden", show ? "false" : "true");
  }

  function render() {
    if (!tbody) return;

    const filtered = applyFilters(payslips);
    const sorted = applySorting(filtered);
    const { pageItems, total, pages } = paginate(sorted);

    if (resultsMeta) {
      resultsMeta.textContent = selectedRunId
        ? `Showing ${pageItems.length} of ${total} payslip(s)`
        : `Select a run to view payslips.`;
    }

    if (pageLabel) pageLabel.textContent = `Page ${page} of ${pages}`;
    if (totalPages) totalPages.textContent = String(pages);
    if (pageInput) pageInput.value = String(page);

    if (pagePrev) pagePrev.disabled = page <= 1;
    if (pageNext) pageNext.disabled = page >= pages;
    if (firstPage) firstPage.disabled = page <= 1;
    if (lastPage) lastPage.disabled = page >= pages;

    tbody.innerHTML = "";
    if (!selectedRunId) {
      if (checkAll) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      }
      updateBulkBar();
      return;
    }

    const selected = new Set(selectedIdsSet);

    pageItems.forEach((p) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${escapeHtml(p.id)}"
            ${selected.has(p.id) ? "checked" : ""} aria-label="Select ${escapeHtml(p.id)}">
        </td>
        <td>${escapeHtml(p.empId)}</td>
        <td>${escapeHtml(p.empName)}</td>
        <td>${escapeHtml(assignmentText(p))}</td>
        <td>${escapeHtml(`${p.periodMonth} (${p.cutoff || formatCutoffLabel(p.periodMonth, p.cutoff)})`)}</td>
        <td class="num netPayCell"><strong>${escapeHtml(peso(p.netPay))}</strong></td>
        <td>${badge(p.releaseStatus)}</td>
        <td>${deliveryBadge(p.deliveryStatus)}</td>
        <td class="col-actions">
          <div class="iconrow">
            <button class="iconbtn" type="button" data-action="view" data-id="${escapeHtml(p.id)}" title="View" aria-label="View payslip">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
            <button class="iconbtn" type="button" data-action="pdf" data-id="${escapeHtml(p.id)}" title="Download PDF" aria-label="Download PDF">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="8" y1="13" x2="16" y2="13"></line>
                <line x1="8" y1="17" x2="16" y2="17"></line>
              </svg>
            </button>
            <button class="iconbtn" type="button" data-action="print" data-id="${escapeHtml(p.id)}" title="Print" aria-label="Print payslip">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    $$("input.rowCheck").forEach((cb) => {
      cb.addEventListener("change", () => {
        if (cb.checked) selectedIdsSet.add(cb.dataset.id);
        else selectedIdsSet.delete(cb.dataset.id);
        updateBulkBar();
        syncCheckAll();
      });
    });

    updateBulkBar();
    syncCheckAll();
  }

  // =========================================================
  // PREVIEW DRAWER (- Correctness: adjustments + cutoff dates)
  // =========================================================
  function findPayslip(id) {
    return payslips.find((p) => p.id === id) || null;
  }

  function set(id, value) {
    const el = $(id);
    if (el) el.textContent = value ?? "-";
  }
  function setMoney(id, value) {
    const el = $(id);
    if (!el) return;
    const num = Number(value);
    const safe = Number.isFinite(num) ? num : 0;
    el.innerHTML = `&#8369; ${safe.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function renderAdjustmentRows(containerEl, items, mode) {

    if (!containerEl) return;
    containerEl.innerHTML = "";

    const list = Array.isArray(items) ? items.filter(x => Number(x?.amount || 0) !== 0) : [];
    if (!list.length) return;

    if (mode === "earning") {
      list.forEach((a) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(a.name || "Adjustment (Earning)")}</td>
          <td class="num">-</td>
          <td class="num">-</td>
          <td class="num">${escapeHtml(peso(a.amount))}</td>
        `;
        containerEl.appendChild(tr);
      });
      return;
    }

    // deduction (3 columns)
    list.forEach((a) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(a.name || "Adjustment (Deduction)")}</td>
        <td class="num">-</td>
        <td class="num">${escapeHtml(peso(a.amount))}</td>
      `;
      containerEl.appendChild(tr);
    });
  }

  function openPayslipDrawer(p) {
    if (!psDrawer || !psOverlay) return;

    if (companySetup) {
      const name = companySetup.company_name || "-";
      const addr = companySetup.company_address || "";
      const contact = companySetup.company_contact || "";
      const email = companySetup.company_email || "";
      const parts = [addr, contact, email].filter((v) => String(v || "").trim());
      const sub = parts.length ? parts.join(" • ") : "-";
      const companyNameEl = $("psCompanyName");
      const companySubEl = $("psCompanySub");
      if (companyNameEl) companyNameEl.textContent = name;
      if (companySubEl) companySubEl.textContent = sub;
    }

    if (psDrawerMeta) {
      const runLabel = p.runCode ? `${p.runCode} ` : "";
      const cutoffLabel = p.cutoff || formatCutoffLabel(p.periodMonth, p.cutoff);
      psDrawerMeta.textContent = `Employee: ${p.empName} (${p.empId})  -  Run: ${runLabel}${p.periodMonth} (${cutoffLabel})  -  Status: ${p.releaseStatus}`;
    }

    // header
    const runPrefix = p.runCode ? `${p.runCode}-` : "";
    set("psNo", p.payslipNo || `PS-${runPrefix}${p.id}`);
    set("psGenerated", p.generatedDate || todayISO());

    // employee
    set("psEmpName", p.empName);
    set("psEmpId", p.empId);
    set("psDept", p.department || "-");
    set("psPos", p.position || "-");
    set("psType", p.empType || "-");
    set("psAssign", assignmentText(p));

    // pay period
    set("psMonth", p.periodMonth || "-");
    set("psCutoff", p.cutoff === "11-25" ? "11-25" : p.cutoff === "26-10" ? "26-10" : "-");
    const periodLabel = (p.periodStart && p.periodEnd) ? `${fmtShortDate(p.periodStart)} to ${fmtShortDate(p.periodEnd)}` : "-";
    set("psPeriod", periodLabel);

    set("psPayDate", resolvePayDate(p.periodMonth, p.cutoff));
    const payMethod = String(p.payMethod || "").toUpperCase();
    const hasBank = payMethod === "BANK" || !!(p.accountNumber || "").trim();
    set("psPayMethod", payMethod === "BANK" ? "Bank" : "Cash");
    const bankRow = $("psBankRow");
    const acctRow = $("psAccountRow");
    if (bankRow) bankRow.style.display = hasBank ? "" : "none";
    if (acctRow) acctRow.style.display = hasBank ? "" : "none";
    set("psBank", hasBank ? (p.bankName || "-") : "-");
    set("psAccount", hasBank ? `****${String(p.accountNumber).slice(-4)}` : "-");

    const statusBadge = $("psStatusBadge");
    if (statusBadge) statusBadge.textContent = p.releaseStatus || "Draft";

    // earnings base
    setMoney("psDailyRate", p.dailyRate);
    set("psDailyDays", Number(p.paidDays || 0).toFixed(2));
    setMoney("psBasicPay", p.basicPay);
    setMoney("psAllowancePay", p.allowancePay);

    // - adjustments (earnings + deductions)
    const earnAdj = Array.isArray(p.earningsAdjustments) ? p.earningsAdjustments : [];
    const dedAdj = Array.isArray(p.deductionAdjustments) ? p.deductionAdjustments : [];
    renderAdjustmentRows(psEarnAdjRows, earnAdj, "earning");
    renderAdjustmentRows(psDedAdjRows, dedAdj, "deduction");

    // deductions base
    const sssEe = Number(p.sssEe || 0);
    const phEe = Number(p.philhealthEe || 0);
    const piEe = Number(p.pagibigEe || 0);
    const tax = Number(p.withholdingTax || 0);

    const attendanceDed = Number(p.attendanceDeductionTotal || 0);
    const cashAdv = Number(p.cashAdvance || 0);
    const chargesDed = Number(p.chargesDeduction || 0);
    const otherDed = Number(p.otherDeductionTotal || 0);

    const sssHousingLoan = sumLoanDeducted(p, (t) => t.includes("sss housing"));
    const hdmfCalamityLoan = sumLoanDeducted(p, (t) => t.includes("calamity"));
    const pagibigHousingLoan = sumLoanDeducted(p, (t) => t.includes("housing") && (t.includes("pagibig") || t.includes("hdmf")));
    const sssLoan = Math.max(0, sumLoanDeducted(p, (t) => t.includes("sss")) - sssHousingLoan);
    const hdmfLoan = Math.max(0, sumLoanDeducted(p, (t) => t.includes("hdmf")) - pagibigHousingLoan - hdmfCalamityLoan);
    const loanDed = sssLoan + hdmfLoan + pagibigHousingLoan + sssHousingLoan + hdmfCalamityLoan;

    const statutoryEeTotal = sssEe + phEe + piEe + tax;
    setMoney("psAttDedTotal", attendanceDed);
    setMoney("psSssEe", sssEe);
    setMoney("psPhEe", phEe);
    setMoney("psPiEe", piEe);
    setMoney("psTax", tax);

    setMoney("psSssLoan", sssLoan);
    setMoney("psHdmfLoan", hdmfLoan);
    setMoney("psPagibigHousingLoan", pagibigHousingLoan);
    setMoney("psSssHousingLoan", sssHousingLoan);
    setMoney("psHdmfCalamityLoan", hdmfCalamityLoan);
    setMoney("psAdvances", cashAdv);
    setMoney("psShortages", 0);
    setMoney("psCharges", chargesDed);

    // - totals (computed to ensure correctness)
    const totalEarnAdj = sumAmounts(earnAdj);
    const totalDedAdj = sumAmounts(dedAdj);

    const baseGross = Number(p.basicPay || 0) + Number(p.allowancePay || 0) + totalEarnAdj;
    const baseDed = attendanceDed + statutoryEeTotal + loanDed + cashAdv + chargesDed + otherDed + totalDedAdj;
    const computedNet = baseGross - baseDed;

    setMoney("psGross", baseGross);
    setMoney("psDedTotal", baseDed);

    // summary
    setMoney("psSumGross", baseGross);
    setMoney("psSumDed", baseDed);

    // net: show computed (to match breakdown)
    setMoney("psNet", computedNet);

    const notes = $("psNotes");
    if (notes) notes.textContent = `Adjust Notes: ${p.notes || "-"}`;

    // open UI
    psDrawer.classList.add("is-open");
    psDrawer.setAttribute("aria-hidden", "false");
    psOverlay.hidden = false;
    document.body.style.overflow = "hidden";

    // actions
    if (psPrintBtn) {
      psPrintBtn.onclick = () => {
        const qs = new URLSearchParams({
          run_id: String(p.runId || ""),
          ids: String(p.id || ""),
          autoprint: "1",
          in_modal: "1",
        });
        openPrintModal(`/payslips/print?${qs.toString()}`);
      };
    }

    if (psDownloadBtn) psDownloadBtn.onclick = () => alert("PDF download: connect to backend PDF generation later.");

    if (psReleaseBtn) {
      const run = runs.find((r) => String(r.id) === String(p.runId)) || null;
      const canRelease = run && (run.status === "Locked" || run.status === "Released");
      psReleaseBtn.disabled = p.releaseStatus === "Released" || !canRelease;
      psReleaseBtn.onclick = async () => {
        if (!canRelease) {
          alert("Run must be Locked or Released to release payslips.");
          return;
        }
        try {
          await apiFetch("/payslips/release", {
            method: "POST",
            body: JSON.stringify({
              run_id: Number(p.runId),
              payslip_ids: [Number(p.id)],
            }),
          });
          p.releaseStatus = "Released";
          render();

          const sb = $("psStatusBadge");
          if (sb) sb.textContent = "Released";
          if (psDrawerMeta) {
            const runLabel = p.runCode ? `${p.runCode} ` : "";
            const cutoffLabel = p.cutoff || formatCutoffLabel(p.periodMonth, p.cutoff);
            psDrawerMeta.textContent = `Employee: ${p.empName} (${p.empId})  -  Run: ${runLabel}${p.periodMonth} (${cutoffLabel})  -  Status: Released`;
          }
          psReleaseBtn.disabled = true;
        } catch (err) {
          alert(err.message || "Failed to release payslip.");
        }
      };
    }
  }

  function closePayslipDrawer() {
    if (!psDrawer || !psOverlay) return;
    psDrawer.classList.remove("is-open");
    psDrawer.setAttribute("aria-hidden", "true");
    psOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  psCloseBtn && psCloseBtn.addEventListener("click", closePayslipDrawer);
  psCloseFooterBtn && psCloseFooterBtn.addEventListener("click", closePayslipDrawer);
  psOverlay && psOverlay.addEventListener("click", closePayslipDrawer);

  // =========================================================
  // TABLE ACTIONS (delegated)
  // =========================================================
  tbody &&
    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;

      const id = btn.dataset.id || "";
      const p = findPayslip(id);
      if (!p) return;

      const action = btn.dataset.action;
      if (action === "view") return openPayslipDrawer(p);
      if (action === "print") {
        const qs = new URLSearchParams({
          run_id: String(p.runId || selectedRunId || ""),
          ids: String(p.id || ""),
          autoprint: "1",
          in_modal: "1",
        });
        openPrintModal(`/payslips/print?${qs.toString()}`);
        return;
      }
      if (action === "pdf") return alert("Row PDF download: connect to backend later.");
    });

  // =========================================================
  // SEGMENT FILTER
  // =========================================================
  function bindAssignmentButtons() {
    if (!assignmentSeg) return;

    function positionDropdown(btn, dropdown) {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desired = Math.round(rect.width);
      const maxWidth = Math.min(360, viewportW - 16);
      const dropdownW = Math.min(Math.max(desired, 240), maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) {
        left = Math.max(8, viewportW - dropdownW - 8);
      }
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${dropdownW}px`;
      dropdown.style.maxWidth = `${dropdownW}px`;
    }

    function refreshOpenDropdownPosition() {
      if (!openDropdown || !openDropdownBtn) return;
      positionDropdown(openDropdownBtn, openDropdown);
    }

    segBtns.forEach((btn) => {
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
        page = 1;
        render();

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
        e.stopPropagation();
        const place = item.getAttribute("data-place");
        const dropdown = item.closest(".seg__dropdown");
        const group = dropdown?.getAttribute("data-group") || "";

        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");

        assignmentFilter = group || assignmentFilter;
        areaSubFilter = place || "";
        closeAllDropdowns();
        page = 1;
        render();
      });
    });

    if (!assignDropdownEventsBound) {
      document.addEventListener("click", (e) => {
        if (!assignmentSeg.contains(e.target)) closeAllDropdowns();
      }, { capture: true });
      window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
      window.addEventListener("resize", refreshOpenDropdownPosition);
      assignDropdownEventsBound = true;
    }
  }

  // filters
  monthInput && monthInput.addEventListener("change", async () => {
    selectedRunId = "";
    await syncRunFromFilters();
  });
  cutoffInput && cutoffInput.addEventListener("change", async () => {
    selectedRunId = "";
    await syncRunFromFilters();
  });
  searchInput && searchInput.addEventListener("input", () => { page = 1; render(); });
  // area place selection handled by dropdowns

  // check all + bulk
  checkAll &&
    checkAll.addEventListener("change", () => {
      $$("input.rowCheck").forEach((cb) => {
        cb.checked = checkAll.checked;
        if (checkAll.checked) selectedIdsSet.add(cb.dataset.id);
        else selectedIdsSet.delete(cb.dataset.id);
      });
      updateBulkBar();
      syncCheckAll();
    });

  bulkCancelBtn &&
    bulkCancelBtn.addEventListener("click", () => {
      selectedIdsSet.clear();
      $$("input.rowCheck").forEach((cb) => (cb.checked = false));
      if (checkAll) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      }
      updateBulkBar();
      syncCheckAll();
    });

  bulkReleaseBtn &&
    bulkReleaseBtn.addEventListener("click", async () => {
      const ids = selectedIds();
      if (!ids.length) return;
      const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      if (!run || (run.status !== "Locked" && run.status !== "Released")) {
        alert("Run must be Locked or Released to release payslips.");
        return;
      }
      try {
        await apiFetch("/payslips/release", {
          method: "POST",
          body: JSON.stringify({
            run_id: Number(selectedRunId),
            payslip_ids: ids.map((id) => Number(id)),
          }),
        });
        ids.forEach((id) => {
          const p = findPayslip(id);
          if (p) p.releaseStatus = "Released";
        });
        render();
      } catch (err) {
        alert(err.message || "Failed to release selected payslips.");
      }
    });

  bulkPdfBtn &&
    bulkPdfBtn.addEventListener("click", () => {
      const ids = selectedIds();
      if (!ids.length) return alert("Select at least 1 payslip.");
      alert("Download Selected PDFs: connect to backend later.");
    });

  bulkPrintBtn &&
    bulkPrintBtn.addEventListener("click", () => {
      const ids = selectedIds();
      if (!ids.length) return alert("Select at least 1 payslip.");
      const qs = new URLSearchParams({
        run_id: selectedRunId,
        ids: ids.join(","),
        autoprint: "1",
        in_modal: "1",
      });
      openPrintModal(`/payslips/print?${qs.toString()}`);
    });

  // pagination
  rowsPerPage &&
    rowsPerPage.addEventListener("change", () => {
      perPage = Number(rowsPerPage.value || 20);
      page = 1;
      render();
    });

  firstPage && firstPage.addEventListener("click", () => { page = 1; render(); });
  lastPage &&
    lastPage.addEventListener("click", () => {
      const list = applySorting(applyFilters(payslips));
      const pages = Math.max(1, Math.ceil(list.length / perPage));
      page = pages;
      render();
    });
  pagePrev && pagePrev.addEventListener("click", () => { page = Math.max(1, page - 1); render(); });
  pageNext && pageNext.addEventListener("click", () => { page = page + 1; render(); });
  pageInput &&
    pageInput.addEventListener("change", () => {
      const v = Number(pageInput.value || 1);
      page = isFinite(v) ? v : 1;
      render();
    });

  // top actions
  function exportCsv(list, filename) {
    const headers = ["Emp ID", "Employee", "Assignment", "Pay Period", "Net Pay", "Status", "Delivery"];
    const rows = list.map((p) => [
      p.empId,
      p.empName,
      assignmentText(p),
      `${p.periodMonth} (${p.cutoff || formatCutoffLabel(p.periodMonth, p.cutoff)})`,
      Number(p.netPay || 0).toFixed(2),
      p.releaseStatus,
      p.deliveryStatus || "",
    ]);

    const csv = [
      headers.join(","),
      ...rows.map((r) => r.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")),
    ].join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportCsvBtn &&
    exportCsvBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const ids = selectedIds();
      const format = "xlsx";
      const qs = new URLSearchParams({
        run_id: selectedRunId,
        format,
      });
      if (ids.length) qs.set("ids", ids.join(","));
      window.location.href = `/payslips/export?${qs.toString()}`;
    });

  exportPdfBtn &&
    exportPdfBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const ids = selectedIds();
      const qs = new URLSearchParams({
        run_id: selectedRunId,
      });
      if (ids.length) qs.set("ids", ids.join(","));
      window.location.href = `/payslips/export-pdf?${qs.toString()}`;
    });

  printBtn &&
    printBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const ids = selectedIds();
      const qs = new URLSearchParams({
        run_id: selectedRunId,
        autoprint: "1",
        in_modal: "1",
      });
      if (ids.length) qs.set("ids", ids.join(","));
      openPrintModal(`/payslips/print?${qs.toString()}`);
    });

  releaseAllBtn &&
    releaseAllBtn.addEventListener("click", async () => {
      if (!selectedRunId) return;
      const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      if (!run || run.status !== "Locked") {
        alert("Run must be Locked to release all payslips.");
        return;
      }
      const ok = confirm("Release all payslips for this run? This will lock the run as Released and cannot be undone.");
      if (!ok) return;
      try {
        const res = await apiFetch(`/payslips/runs/${encodeURIComponent(selectedRunId)}/release-all`, {
          method: "POST",
        });
        const list = payslips.filter((p) => p.runId === selectedRunId);
        list.forEach((p) => (p.releaseStatus = "Released"));
        run.status = res?.status || "Released";
        run.released_at = res?.released_at || new Date().toISOString();
        render();
        setRunUI(run);
        alert("All payslips released. Run is now Released and cannot be unlocked.");
      } catch (err) {
        alert(err.message || "Failed to release all payslips.");
      }
    });

  sendEmailBtn &&
    sendEmailBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      if (!run || (run.status !== "Locked" && run.status !== "Released")) {
        alert("Email sending is available after the run is Locked/Released.");
        return;
      }
      const ids = selectedIds();
      const payload = { run_id: Number(selectedRunId) };
      const prevText = sendEmailBtn?.textContent || "";
      if (sendEmailBtn) {
        sendEmailBtn.disabled = true;
        sendEmailBtn.textContent = "Sending…";
      }
      apiFetch("/payslips/send-email", {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          ...(ids.length ? { ids: ids.join(",") } : {}),
        }),
      })
        .then((res) => {
          const skipped = Array.isArray(res?.skipped) ? res.skipped : [];
          const failed = Array.isArray(res?.failed) ? res.failed : [];
          const failedEmpNos = new Set(failed.map((f) => String(f?.emp_no ?? "")));
          const scoped = ids.length
            ? payslips.filter((p) => ids.includes(p.id))
            : payslips.filter((p) => p.runId === selectedRunId);
          scoped.forEach((p) => {
            if (failedEmpNos.has(String(p.empId))) {
              p.deliveryStatus = "Failed";
              return;
            }
            if (!skipped.includes(p.empId)) p.deliveryStatus = "Sent";
          });
          render();
          let msg = `Sent: ${res?.sent || 0}.`;
          if (skipped.length) msg += ` Skipped (no email): ${skipped.length}.`;
          if (failed.length) {
            msg += ` Failed: ${failed.length}.`;
            const first = failed[0];
            if (first?.error) msg += `\nFirst error (${first.emp_no || "emp"}): ${first.error}`;
          }
          alert(msg);
        })
        .catch((err) => alert(err.message || "Failed to send emails."))
        .finally(() => {
          if (sendEmailBtn) {
            sendEmailBtn.textContent = prevText || "Send Payslips via Email";
            const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
            sendEmailBtn.disabled = !run || (run.status !== "Locked" && run.status !== "Released");
          }
        });
    });

  // init
  bindHeaderSorting();

  const params = new URLSearchParams(window.location.search);
  const initialRunId = params.get("run_id") || "";
  const initialMonth = params.get("month") || params.get("period_month") || "";
  const initialCutoff = normalizeCutoffValue(params.get("cutoff") || "");
  if (initialMonth && monthInput && /^\d{4}-\d{2}$/.test(initialMonth)) {
    monthInput.value = initialMonth;
  }
  if (initialCutoff && cutoffInput) {
    cutoffInput.value = initialCutoff;
  }

  Promise.resolve()
    .then(() => loadPayrollCalendarSettings())
    .then(() => loadCompanySetup())
    .then(() => loadFilterOptions())
    .then(() => syncRunFromFilters({
      forceRunId: initialRunId,
      fallbackOnEmpty: !initialRunId && !initialMonth && !initialCutoff,
    }))
    .catch((err) => alert(err.message || "Failed to initialize payslips."));
});
