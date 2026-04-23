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
  const pad2 = (n) => String(n).padStart(2, "0");
  const escapeHtml = (s) =>
    String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const firstAndLastName = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "-";
    const cleaned = raw.replace(/\([^)]*\)/g, " ").replace(/\s+/g, " ").trim();
    const parts = cleaned.split(" ").filter(Boolean);
    if (parts.length <= 1) return cleaned;
    return `${parts[0]} ${parts[parts.length - 1]}`;
  };

  const normalizeDateTimeInput = (value) => {
    if (!value) return "";
    let s = String(value).trim();
    s = s.replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})(.*)$/, "$1T$2$3");
    s = s.replace(/(\.\d{3})\d+(?=Z?$)/, "$1");
    return s;
  };

  const formatDateTime12h = (value) => {
    if (!value || value === "-") return "-";
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

  const query = new URLSearchParams(window.location.search);
  const initialMonthParam = query.get("month") || "";
  const normalizeCutoffValue = (value) => {
    const v = String(value || "").trim().toLowerCase();
    if (v === "a" || v === "16-end" || v === "26-10") return "26-10";
    if (v === "b" || v === "1-15" || v === "11-25") return "11-25";
    return "";
  };
  const normalizeCutoffParam = (value) => {
    const normalized = normalizeCutoffValue(value);
    if (normalized) return normalized;
    return "All";
  };
  const initialCutoffParam = normalizeCutoffParam(query.get("cutoff"));
  const initialAssignmentParam = (query.get("assignment") || "").trim();
  const initialPlaceParam = (query.get("place") || "").trim();
  const initialRunIdParam = (query.get("run_id") || "").trim();

  // =========================================================
  // DATA (loaded from backend)
  // =========================================================
  let RUNS = [];
  let REGISTER = [];

  // =========================================================
  // ELEMENTS
  // =========================================================
  const monthInput = $("monthInput");
  const cutoffSelect = $("cutoffSelect");
  const searchInput = $("searchInput");
  const runDisplay = $("runDisplay");

  const assignmentSeg = $("assignSeg");
  let segBtns = [];

  let areaPlaces = {};
  let areaSubFilter = "";
  let openDropdown = null;
  let openDropdownBtn = null;
  let assignDropdownEventsBound = false;

  const exportCsvBtn = $("exportCsvBtn");
  const downloadPdfBtn = $("downloadPdfBtn");
  const printBtn = $("printBtn");

  const reportTitle = $("reportTitle");
  const runBadge = $("runBadge");
  const runEmployees = $("runEmployees");
  const runTotalNet = $("runTotalNet");
  const runProcessedAt = $("runProcessedAt");
  const runProcessedBy = $("runProcessedBy");

  const kpiGross = $("kpiGross");
  const kpiDed = $("kpiDed");
  const kpiNet = $("kpiNet");
  const kpiER = $("kpiER");
  const kpiAtmNetGross = $("kpiAtmNetGross");
  const kpiNonAtmNetGross = $("kpiNonAtmNetGross");

  const tabBtns = $$(".tabBtn");
  const allRegTbody = $("allRegTbody");
  const allBdTbody = $("allBdTbody");
  const allRemitTbody = $("allRemitTbody");
  const allSssTbody = $("allSssTbody");
  const allPhilhealthTbody = $("allPhilhealthTbody");
  const allPagibigTbody = $("allPagibigTbody");
  const allFieldAreasTbody = $("allFieldAreasTbody");
  const allFieldAreasTotalsTbody = $("allFieldAreasTotalsTbody");
  const allCompanyPayslipsTbody = $("allCompanyPayslipsTbody");
  const allOverallTbody = $("allOverallTbody");
  const allAuditTbody = $("allAuditTbody");
  const allIssuesTbody = $("allIssuesTbody");
  const regTbody = $("regTbody");
  const bdTbody = $("bdTbody");
  const remitTbody = $("remitTbody");
  const sssTbody = $("sssTbody");
  const philhealthTbody = $("philhealthTbody");
  const pagibigTbody = $("pagibigTbody");
  const fieldAreasTbody = $("fieldAreasTbody");
  const fieldAreasTotalsTbody = $("fieldAreasTotalsTbody");
  const companyPayslipsTbody = $("companyPayslipsTbody");
  const overallTbody = $("overallTbody");
  const auditTbody = $("auditTbody");
  const issuesTbody = $("issuesTbody");

  const resultsMeta = $("resultsMeta");
  const registerPane = $("tab-register");
  const registerTableWrap = registerPane?.querySelector(".tablewrap") || null;
  const contentScroller = document.querySelector(".content");

  // =========================================================
  // STATE
  // =========================================================
  let selectedRunId = "";
  let assignmentFilter = "All";
  let sortKey = "empName";
  let sortDir = "asc"; // asc/desc
  let activeTab = "all";
  let FIELD_AREA_ALLOC = null;
  let floatingRegisterScroll = null;
  let floatingRegisterInner = null;
  let syncingRegisterScroll = false;
  let initialAssignmentApplied = false;
  let filterLoadingCount = 0;

  // =========================================================
  // DEFAULTS
  // =========================================================
  if (monthInput) {
    if (/^\d{4}-\d{2}$/.test(initialMonthParam)) {
      monthInput.value = initialMonthParam;
    } else if (!monthInput.value) {
      const d = new Date();
      monthInput.value = `${d.getFullYear()}-${pad2(d.getMonth() + 1)}`;
    }
  }
  if (cutoffSelect) {
    cutoffSelect.value = initialCutoffParam || cutoffSelect.value || "All";
    if (!cutoffSelect.value) cutoffSelect.value = "All";
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

  function initFloatingRegisterScrollbar() {
    if (!registerTableWrap || floatingRegisterScroll) return;

    const bar = document.createElement("div");
    bar.className = "table-scroll-float";
    bar.setAttribute("aria-hidden", "true");
    const inner = document.createElement("div");
    inner.className = "table-scroll-float__inner";
    bar.appendChild(inner);
    document.body.appendChild(bar);

    floatingRegisterScroll = bar;
    floatingRegisterInner = inner;

    registerTableWrap.addEventListener("scroll", () => {
      if (!floatingRegisterScroll || syncingRegisterScroll) return;
      syncingRegisterScroll = true;
      floatingRegisterScroll.scrollLeft = registerTableWrap.scrollLeft;
      syncingRegisterScroll = false;
    });

    floatingRegisterScroll.addEventListener("scroll", () => {
      if (!registerTableWrap || syncingRegisterScroll) return;
      syncingRegisterScroll = true;
      registerTableWrap.scrollLeft = floatingRegisterScroll.scrollLeft;
      syncingRegisterScroll = false;
    });
  }

  function refreshFloatingRegisterScrollbar() {
    if (!registerTableWrap || !floatingRegisterScroll || !floatingRegisterInner) return;

    const rect = registerTableWrap.getBoundingClientRect();
    const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
    const hasOverflow = registerTableWrap.scrollWidth > registerTableWrap.clientWidth + 1;
    const isVisible = rect.top < viewportH && rect.bottom > 0;
    const nativeScrollbarVisible = rect.bottom <= (viewportH - 4);
    const shouldShow = activeTab === "register" && hasOverflow && isVisible && !nativeScrollbarVisible;

    if (!shouldShow) {
      floatingRegisterScroll.style.display = "none";
      return;
    }

    floatingRegisterScroll.style.display = "block";
    floatingRegisterScroll.style.left = `${Math.max(8, rect.left)}px`;
    floatingRegisterScroll.style.width = `${Math.max(120, rect.width)}px`;
    floatingRegisterInner.style.width = `${registerTableWrap.scrollWidth}px`;
    floatingRegisterScroll.scrollLeft = registerTableWrap.scrollLeft;
  }

  // =========================================================
  // RUN SELECT INIT
  // =========================================================
  function pickDefaultRunId(runs) {
    if (!runs.length) return "";

    const hasReportRows = (r) => Number(r.employees || 0) > 0;
    const candidates = runs.filter(hasReportRows);
    const source = candidates.length ? candidates : runs;

    // Prefer most recent run by released/locked/created timestamp, then by id.
    const toTs = (r) => {
      const raw = r.releasedAtRaw || r.lockedAtRaw || r.createdAtRaw || "";
      const t = raw ? Date.parse(normalizeDateTimeInput(raw)) : NaN;
      return Number.isFinite(t) ? t : 0;
    };

    const sorted = source
      .slice()
      .sort((a, b) => {
        const dt = toTs(b) - toTs(a);
        if (dt !== 0) return dt;
        return (Number(b.id) || 0) - (Number(a.id) || 0);
      });
    return sorted[0]?.id || "";
  }

  function runDisplayLabel(run) {
    if (!run) return "No released payroll run found for the selected filters.";
    return `${run.runCode} - ${run.month} (${run.cutoffLabel}) - ${run.displayLabel} - ${run.status} - ${run.employees} employees`;
  }

  function setRunDisplay(run) {
    if (!runDisplay) return;
    runDisplay.textContent = runDisplayLabel(run);
  }

  function mapRun(run) {
    const assignmentText = run.assignment_filter === "Field" && run.area_place_filter
      ? `Field (${run.area_place_filter})`
      : (run.assignment_filter || "-");

    return {
      id: String(run.id),
      runCode: run.run_code || String(run.id),
      month: run.period_month,
      cutoff: normalizeCutoffValue(run.cutoff) || String(run.cutoff || ""),
      cutoffLabel: normalizeCutoffValue(run.cutoff) || run.cutoff || "-",
      assignment: assignmentText,
      status: run.status || "-",
      employees: Number(run.headcount || 0),
      totalNet: Number(run.net || 0),
      processedAt: formatDateTime12h(run.locked_at || run.created_at || "-"),
      processedBy: firstAndLastName(run.created_by_name || ""),
      payslipsGeneratedAt: formatDateTime12h(run.payslips_generated_at || "-"),
      releasedAt: formatDateTime12h(run.released_at || "-"),
      createdAtRaw: run.created_at || "",
      lockedAtRaw: run.locked_at || "",
      releasedAtRaw: run.released_at || "",
      runType: run.run_type || "External",
      displayLabel: run.display_label || `${run.run_type || "External"} - ${assignmentText}`,
    };
  }

  function mapRow(row) {
    const basic = Number(row.basic_pay_cutoff || 0);
    const allowance = Number(row.allowance_cutoff || 0);
    const charges = Number(row.charges_deduction || 0);
    const loanDeduction = Number(row.loan_deduction || 0);
    const cashAdvance = Number(row.cash_advance || 0);

    return {
      runId: selectedRunId,
      empId: row.emp_no || String(row.employee_id || ""),
      empName: row.name || "",
      department: row.department || "",
      empType: row.employment_type || "",
      assignmentType: row.assignment || "",
      areaPlace: row.area_place || "",
      externalArea: row.external_area || "",
      presentDays: Number(row.present_days || 0),
      absentDays: Number(row.absent_days || 0),
      leaveDays: Number(row.leave_days || 0),
      dailyRate: Number(row.daily_rate || 0),
      attendancePay: basic + allowance,
      attendanceDeduction: Number(row.attendance_deduction || 0),
      chargesDeduction: charges,
      loanDeduction: loanDeduction,
      cashAdvanceDeduction: cashAdvance,
      otherDeductions: charges + loanDeduction + cashAdvance,
      sssEe: Number(row.sss_ee || 0),
      philhealthEe: Number(row.philhealth_ee || 0),
      pagibigEe: Number(row.pagibig_ee || 0),
      tax: Number(row.tax || 0),
      sssEr: Number(row.sss_er || 0),
      philhealthEr: Number(row.philhealth_er || 0),
      pagibigEr: Number(row.pagibig_er || 0),
      gross: Number(row.gross || 0),
      deductionsEe: Number(row.deductions_total || 0),
      employerShare: Number(row.employer_share_total || 0),
      netPay: Number(row.net_pay || 0),
      payMethod: row.pay_method || row.payout_method || "",
      payslipStatus: row.payslip_status || "-",
    };
  }

  async function loadRuns() {
    try {
      const data = await apiFetch("/payroll-runs");
      RUNS = Array.isArray(data) ? data.map(mapRun) : [];
    } catch {
      RUNS = [];
    }
    await syncRunFromFilters({
      preferredRunId: initialRunIdParam,
      fallbackOnEmpty: !initialRunIdParam && !initialMonthParam && initialCutoffParam === "All",
    });
    renderAudit();
  }

  function setTopActionsEnabled(enabled) {
    [exportCsvBtn, downloadPdfBtn, printBtn].forEach((b) => {
      if (!b) return;
      b.disabled = !enabled;
    });
  }
  setTopActionsEnabled(false);

  function setFilterLoading(isLoading) {
    if (isLoading) filterLoadingCount += 1;
    else filterLoadingCount = Math.max(0, filterLoadingCount - 1);
    const active = filterLoadingCount > 0;
    if (runDisplay) runDisplay.classList.toggle("is-loading", active);
    if (active && resultsMeta) resultsMeta.textContent = "Applying filters...";
  }

  // =========================================================
  // PIPELINE: FILTERS
  // =========================================================
  function assignmentText(r) {
    if (r.areaPlace) return `${r.assignmentType || "-"} (${r.areaPlace})`;
    return r.assignmentType || "-";
  }

  function isReleasedRun(run) {
    return normalize(run?.status || "") === "released";
  }

  function runMatchesFilters(run) {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "All";
    const runCutoff = normalizeCutoffValue(run.cutoff) || run.cutoff;

    const okMonth = !monthVal || run.month === monthVal;
    const okCutoff = cutoffVal === "All" || runCutoff === cutoffVal;
    return okMonth && okCutoff && isReleasedRun(run);
  }

  function applyFilters(list) {
    const q = normalize(searchInput?.value || "");
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "All";
    return list
      .filter((r) => (!selectedRunId ? true : r.runId === selectedRunId))
      .filter((r) => (!monthVal ? true : getRun(r.runId)?.month === monthVal))
      .filter((r) => (cutoffVal === "All" ? true : getRun(r.runId)?.cutoff === cutoffVal))
      .filter((r) => (assignmentFilter === "All" ? true : r.assignmentType === assignmentFilter))
      .filter((r) => {
        if (!areaSubFilter) return true;
        return (r.areaPlace || "") === areaSubFilter;
      })
      .filter((r) => {
        if (!q) return true;
        const text = normalize(`${r.empId} ${r.empName} ${r.empType || ""} ${assignmentText(r)}`);
        return text.includes(q);
      });
  }

  // =========================================================
  // SORTING
  // =========================================================
  function getSortValue(r, key) {
    if (key === "empId") return String(r.empId || "");
    if (key === "empName") return String(r.empName || "");
    if (key === "dailyRate") return Number(r.dailyRate || 0);
    if (key === "attendancePay") return Number(r.attendancePay || 0);
    if (key === "deductionsEe") return Number(r.deductionsEe || 0);
    if (key === "employerShare") return Number(r.employerShare || 0);
    if (key === "gross") return Number(r.gross || 0);
    if (key === "netPay") return Number(r.netPay || 0);
    if (key === "payslipStatus") return String(r.payslipStatus || "");
    return String(r[key] || "");
  }

  function compare(a, b, key) {
    const va = getSortValue(a, key);
    const vb = getSortValue(b, key);
    if (typeof va === "number" && typeof vb === "number") return va - vb;
    return String(va).localeCompare(String(vb));
  }

  function applySorting(list) {
    const sorted = list.slice().sort((a, b) => compare(a, b, sortKey));
    if (sortDir === "desc") sorted.reverse();
    return sorted;
  }

  function bindHeaderSorting() {
    const ths = $$(`#tab-register th[data-sort]`);
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
        updateSortIcons();
        renderAll();
      });
    });
    updateSortIcons();
  }

  function updateSortIcons() {
    const ths = $$(`#tab-register th[data-sort]`);
    ths.forEach((th) => {
      const key = th.dataset.sort;
      th.classList.remove("is-asc", "is-desc");
      const icon = th.querySelector(".sortIcon");
      if (icon) icon.textContent = "";
      if (key === sortKey) {
        th.classList.add(sortDir === "asc" ? "is-asc" : "is-desc");
      }
    });
  }
  bindHeaderSorting();

  // =========================================================
  // RUN UI
  // =========================================================
  function getRun(runId) {
    return RUNS.find((r) => r.id === runId) || null;
  }

  function setRunUI(run) {
    if (!run) {
      setRunDisplay(null);
      if (runBadge) runBadge.textContent = "-";
      if (runEmployees) runEmployees.textContent = "-";
      if (runTotalNet) runTotalNet.textContent = "-";
      if (runProcessedAt) runProcessedAt.textContent = "-";
      if (runProcessedBy) runProcessedBy.textContent = "-";
      if (reportTitle) reportTitle.textContent = "Select a run to generate a report.";
      setTopActionsEnabled(false);
      return;
    }

    setRunDisplay(run);
    if (runBadge) runBadge.textContent = run.status;
    if (runEmployees) runEmployees.textContent = String(run.employees);
    if (runTotalNet) runTotalNet.textContent = peso(run.totalNet);
    if (runProcessedAt) runProcessedAt.textContent = run.processedAt;
    if (runProcessedBy) runProcessedBy.textContent = run.processedBy;

    if (reportTitle) {
      reportTitle.textContent = `Payroll Reports - ${run.runCode} - ${run.month} (${run.cutoff}) - ${run.assignment}`;
    }
    setTopActionsEnabled(true);
  }

  // =========================================================
  // KPI + TAB RENDERS
  // =========================================================
  function renderKPIs(rows) {
    const gross = rows.reduce((a, r) => a + Number(r.gross || 0), 0);
    const ded = rows.reduce((a, r) => a + Number(r.deductionsEe || 0), 0);
    const net = rows.reduce((a, r) => a + Number(r.netPay || 0), 0);
    const er = rows.reduce((a, r) => a + Number(r.employerShare || 0), 0);
    const isAtm = (r) => {
      const method = normalize(r.payMethod || "");
      return method === "bank" || method === "atm";
    };
    const atmNetGross = rows
      .filter((r) => isAtm(r))
      .reduce((a, r) => a + Number(r.netPay || 0), 0);
    const nonAtmNetGross = rows
      .filter((r) => !isAtm(r))
      .reduce((a, r) => a + Number(r.netPay || 0), 0);

    if (kpiGross) kpiGross.textContent = peso(gross);
    if (kpiDed) kpiDed.textContent = peso(ded);
    if (kpiNet) kpiNet.textContent = peso(net);
    if (kpiER) kpiER.textContent = peso(er);
    if (kpiAtmNetGross) kpiAtmNetGross.textContent = peso(atmNetGross);
    if (kpiNonAtmNetGross) kpiNonAtmNetGross.textContent = peso(nonAtmNetGross);
  }
  function renderRegister(rows) {
    if (!regTbody) return;
    const renderPayslipStatusChip = (status) => {
      const value = String(status || "Unclaimed").trim().toLowerCase() === "claimed" ? "Claimed" : "Unclaimed";
      const modifier = value.toLowerCase();
      return `<span class="statusChip statusChip--${modifier}">${value}</span>`;
    };
    regTbody.innerHTML = "";
    if (resultsMeta) resultsMeta.textContent = `Showing ${rows.length} employee(s)`;

    rows.forEach((r) => {
      const tr = document.createElement("tr");
      const dedBreakdown = [
        ["Attendance", Number(r.attendanceDeduction || 0)],
        ["SSS (EE)", Number(r.sssEe || 0)],
        ["PhilHealth (EE)", Number(r.philhealthEe || 0)],
        ["Pag-IBIG (EE)", Number(r.pagibigEe || 0)],
        ["Tax", Number(r.tax || 0)],
        ["Charges", Number(r.chargesDeduction || 0)],
        ["Loans", Number(r.loanDeduction || 0)],
        ["Cash Advance", Number(r.cashAdvanceDeduction || 0)],
      ];
      tr.innerHTML = `
        <td>${escapeHtml(r.empId)}</td>
        <td>${escapeHtml(r.empName)}</td>
        <td>${escapeHtml(assignmentText(r))}</td>
                <td>${escapeHtml(`${r.presentDays}/${r.absentDays}/${r.leaveDays}`)}</td>
        <td class="num">${escapeHtml(peso(r.dailyRate))}</td>
        <td class="num">${escapeHtml(peso(r.attendancePay))}</td>
        <td class="num">
          <details class="dedDetails">
            <summary>
              <span class="dedDetails__sum">${escapeHtml(peso(r.deductionsEe))}</span>
              <span class="dedDetails__chev" aria-hidden="true"></span>
            </summary>
            <div class="dedDetails__body">
              ${dedBreakdown
                .filter(([, amt]) => Number(amt || 0) > 0)
                .map(([label, amt]) => `<div><span>${escapeHtml(label)}</span><span>${escapeHtml(peso(amt))}</span></div>`)
                .join("") || `<div><span>Breakdown</span><span>${escapeHtml(peso(0))}</span></div>`}
            </div>
          </details>
        </td>
        <td class="num">${escapeHtml(peso(r.gross))}</td>
        <td class="num">${escapeHtml(peso(r.netPay))}</td>
        <td>${renderPayslipStatusChip(r.payslipStatus)}</td>
      `;
      regTbody.appendChild(tr);
    });

    if (!rows.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="10" class="muted small">No rows found.</td>`;
      regTbody.appendChild(tr);
    }

    if (resultsMeta) resultsMeta.textContent = `Showing ${rows.length} employee(s)`;
  }

  function renderBreakdown(rows) {
    if (!bdTbody) return;

    const totals = [
      ["Attendance Deductions", rows.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0)],
      ["Other Deductions", rows.reduce((a, r) => a + Number(r.otherDeductions || 0), 0)],
      ["SSS (EE)", rows.reduce((a, r) => a + Number(r.sssEe || 0), 0)],
      ["PhilHealth (EE)", rows.reduce((a, r) => a + Number(r.philhealthEe || 0), 0)],
      ["Pag-IBIG (EE)", rows.reduce((a, r) => a + Number(r.pagibigEe || 0), 0)],
      ["Withholding Tax", rows.reduce((a, r) => a + Number(r.tax || 0), 0)],
      ["SSS (ER)", rows.reduce((a, r) => a + Number(r.sssEr || 0), 0)],
      ["PhilHealth (ER)", rows.reduce((a, r) => a + Number(r.philhealthEr || 0), 0)],
      ["Pag-IBIG (ER)", rows.reduce((a, r) => a + Number(r.pagibigEr || 0), 0)],
    ];

    bdTbody.innerHTML = totals
      .map(([k, v]) => `<tr><td>${escapeHtml(k)}</td><td class="num"><strong>${escapeHtml(peso(v))}</strong></td></tr>`)
      .join("");
  }

  function renderRemittance(rows) {
    if (!remitTbody) return;

    remitTbody.innerHTML = "";

    const externalRows = rows
      .filter((r) => String(r.externalArea || "").trim() !== "")
      .slice()
      .sort((a, b) => {
        const companyCmp = String(a.externalArea || "").localeCompare(String(b.externalArea || ""));
        if (companyCmp !== 0) return companyCmp;
        return String(a.empName || "").localeCompare(String(b.empName || ""));
      });

    if (!externalRows.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">No external company rows found.</td>`;
      remitTbody.appendChild(tr);
      return;
    }

    const byCompany = groupBy(externalRows, (r) => String(r.externalArea || "").trim());
    Array.from(byCompany.keys()).sort((a, b) => a.localeCompare(b)).forEach((company) => {
      appendGroupHeader(remitTbody, `External Company: ${company}`, 6);
      const items = byCompany.get(company) || [];
      items.forEach((r) => {
        const sssEe = Number(r.sssEe || 0);
        const philhealthEe = Number(r.philhealthEe || 0);
        const pagibigEe = Number(r.pagibigEe || 0);
        const totalEe = Number(r.deductionsEe || 0);
        const totalEr = Number(r.employerShare || 0);
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(r.empName)}</td>
          <td class="num">${escapeHtml(peso(sssEe))}</td>
          <td class="num">${escapeHtml(peso(philhealthEe))}</td>
          <td class="num">${escapeHtml(peso(pagibigEe))}</td>
          <td class="num"><strong>${escapeHtml(peso(totalEe))}</strong></td>
          <td class="num"><strong>${escapeHtml(peso(totalEr))}</strong></td>
        `;
        remitTbody.appendChild(tr);
      });
    });
  }

  function renderAudit() {
    if (!auditTbody) return;
    const list = RUNS.filter(runMatchesFilters);

    auditTbody.innerHTML = "";
    list.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(`${r.month} (${r.cutoffLabel})`)}</td>
        <td>${escapeHtml(r.status)}</td>
        <td class="num">${escapeHtml(String(r.employees))}</td>
        <td class="num"><strong>${escapeHtml(peso(r.totalNet))}</strong></td>
        <td>${escapeHtml(r.processedAt)}</td>
        <td>${escapeHtml(r.processedBy)}</td>
        <td>${escapeHtml(r.payslipsGeneratedAt || "-")}</td>
        <td>${escapeHtml(r.releasedAt || "-")}</td>
      `;
      auditTbody.appendChild(tr);
    });

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="9" class="muted small">No runs found.</td>`;
      auditTbody.appendChild(tr);
    }
  }

  
  
  
  function computeIssues(rows) {
    const issues = [];
    rows.forEach((r) => {
      if (Number(r.netPay || 0) < 0) {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Negative net pay", severity: "High" });
      }
      if (r.payslipStatus === "Not generated") {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Payslip not generated", severity: "Low" });
      }
    });
    return issues;
  }

  function groupBy(list, keyFn) {
    const map = new Map();
    list.forEach((item) => {
      const key = String(keyFn(item) ?? "-");
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(item);
    });
    return map;
  }

  function appendGroupHeader(tbody, label, colspan) {
    const tr = document.createElement("tr");
    tr.className = "row-group-header";
    tr.innerHTML = `<td colspan="${colspan}"><strong>${escapeHtml(label)}</strong></td>`;
    tbody.appendChild(tr);
  }

  function companyLabel(r) {
    const area = String(r.areaPlace || "").trim();
    if (area) return area;
    const assign = String(r.assignmentType || "").trim();
    return assign || "-";
  }

  function externalCompanyLabel(r) {
    const external = String(r.externalArea || "").trim();
    if (external) return external;
    return companyLabel(r);
  }

  const PREMIUM_CONFIG = {
    sss: { label: "SSS", eeKey: "sssEe", erKey: "sssEr" },
    philhealth: { label: "PhilHealth", eeKey: "philhealthEe", erKey: "philhealthEr" },
    pagibig: { label: "Pag-IBIG", eeKey: "pagibigEe", erKey: "pagibigEr" },
  };

  function buildPremiumRows(rows, kind) {
    const cfg = PREMIUM_CONFIG[kind];
    if (!cfg) return [];
    return rows
      .map((r) => ({
        company: externalCompanyLabel(r),
        empId: r.empId,
        empName: r.empName,
        ee: Number(r[cfg.eeKey] || 0),
        er: Number(r[cfg.erKey] || 0),
      }))
      .filter((r) => r.ee !== 0 || r.er !== 0)
      .map((r) => ({ ...r, total: r.ee + r.er }))
      .sort((a, b) => {
        const c = a.company.localeCompare(b.company);
        if (c !== 0) return c;
        return String(a.empName || "").localeCompare(String(b.empName || ""));
      });
  }

  function buildAllRows(rows) {
    const out = [];
    rows.forEach((r) => {
      out.push({
        reportType: "Payroll Register",
        company: externalCompanyLabel(r),
        empId: r.empId,
        empName: r.empName,
        ee: Number(r.deductionsEe || 0),
        er: Number(r.employerShare || 0),
        gross: Number(r.gross || 0),
        net: Number(r.netPay || 0),
      });

      ["sss", "philhealth", "pagibig"].forEach((kind) => {
        const cfg = PREMIUM_CONFIG[kind];
        const ee = Number(r[cfg.eeKey] || 0);
        const er = Number(r[cfg.erKey] || 0);
        if (ee === 0 && er === 0) return;
        out.push({
          reportType: cfg.label,
          company: externalCompanyLabel(r),
          empId: r.empId,
          empName: r.empName,
          ee,
          er,
          gross: 0,
          net: 0,
        });
      });
    });

    const order = new Map([
      ["Payroll Register", 0],
      ["SSS", 1],
      ["PhilHealth", 2],
      ["Pag-IBIG", 3],
    ]);

    return out.sort((a, b) => {
      const c = a.company.localeCompare(b.company);
      if (c !== 0) return c;
      const n = String(a.empName || "").localeCompare(String(b.empName || ""));
      if (n !== 0) return n;
      return (order.get(a.reportType) ?? 99) - (order.get(b.reportType) ?? 99);
    });
  }

  function renderAllCombined() {
    const copyRows = (src, dst) => {
      if (!src || !dst) return;
      dst.innerHTML = src.innerHTML;
    };

    copyRows(regTbody, allRegTbody);
    copyRows(bdTbody, allBdTbody);
    copyRows(remitTbody, allRemitTbody);
    copyRows(sssTbody, allSssTbody);
    copyRows(philhealthTbody, allPhilhealthTbody);
    copyRows(pagibigTbody, allPagibigTbody);
    copyRows(fieldAreasTbody, allFieldAreasTbody);
    copyRows(fieldAreasTotalsTbody, allFieldAreasTotalsTbody);
    copyRows(companyPayslipsTbody, allCompanyPayslipsTbody);
    copyRows(overallTbody, allOverallTbody);
    copyRows(auditTbody, allAuditTbody);
    copyRows(issuesTbody, allIssuesTbody);
  }

  function renderPremiumTable(tbody, rows, kind) {
    if (!tbody) return;
    const cfg = PREMIUM_CONFIG[kind];
    const list = buildPremiumRows(rows, kind);
    tbody.innerHTML = "";

    list.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.company)}</td>
        <td>${escapeHtml(r.empName)}</td>
        <td class="num">${escapeHtml(peso(r.ee))}</td>
        <td class="num">${escapeHtml(peso(r.er))}</td>
        <td class="num"><strong>${escapeHtml(peso(r.total))}</strong></td>
      `;
      tbody.appendChild(tr);
    });

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="5" class="muted small">No ${escapeHtml(cfg.label)} rows found.</td>`;
      tbody.appendChild(tr);
    }
  }

  function renderSss(rows) {
    renderPremiumTable(sssTbody, rows, "sss");
  }

  function renderPhilHealth(rows) {
    renderPremiumTable(philhealthTbody, rows, "philhealth");
  }

  function renderPagibig(rows) {
    renderPremiumTable(pagibigTbody, rows, "pagibig");
  }
  function renderCompanyPayslips(rows) {
    if (!companyPayslipsTbody) return;

    const list = rows
      .slice()
      .sort((a, b) => {
        const ca = companyLabel(a).localeCompare(companyLabel(b));
        if (ca !== 0) return ca;
        return String(a.empName || "").localeCompare(String(b.empName || ""));
      });

    companyPayslipsTbody.innerHTML = "";

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">No rows found.</td>`;
      companyPayslipsTbody.appendChild(tr);
      return;
    }

    const groups = groupBy(list, (r) => companyLabel(r));
    Array.from(groups.keys()).sort().forEach((company) => {
      const items = groups.get(company) || [];
      appendGroupHeader(companyPayslipsTbody, company, 6);
      {
        const tr = document.createElement("tr");
        tr.className = "row-group-columns";
        tr.innerHTML = `
          <td>Name</td>
          <td class="num">Gross Pay</td>
          <td class="num">Deductions</td>
          <td class="num">Attendance Deduction</td>
          <td class="num">Charges</td>
          <td class="num">Net Pay</td>
        `;
        companyPayslipsTbody.appendChild(tr);
      }

      let totalGross = 0;
      let totalDed = 0;
      let totalShort = 0;
      let totalCharges = 0;
      let totalNet = 0;

      items.forEach((r) => {
        const gross = Number(r.gross || 0);
        const ded = Number(r.deductionsEe || 0);
        const shortages = Number(r.attendanceDeduction || 0);
        const charges = Number(r.chargesDeduction || 0);
        const net = Number(r.netPay || 0);

        totalGross += gross;
        totalDed += ded;
        totalShort += shortages;
        totalCharges += charges;
        totalNet += net;

        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(r.empName)}</td>
          <td class="num">${escapeHtml(peso(gross))}</td>
          <td class="num">${escapeHtml(peso(ded))}</td>
          <td class="num">${escapeHtml(peso(shortages))}</td>
          <td class="num">${escapeHtml(peso(charges))}</td>
          <td class="num"><strong>${escapeHtml(peso(net))}</strong></td>
        `;
        companyPayslipsTbody.appendChild(tr);
      });

      const tr = document.createElement("tr");
      tr.className = "row-total";
      tr.innerHTML = `
        <td><strong>TOTAL</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalGross))}</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalDed))}</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalShort))}</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalCharges))}</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalNet))}</strong></td>
      `;
      companyPayslipsTbody.appendChild(tr);
    });
  }

  function renderFieldAreaAllocations(payload) {
    if (!fieldAreasTbody || !fieldAreasTotalsTbody) return;

    fieldAreasTbody.innerHTML = "";
    fieldAreasTotalsTbody.innerHTML = "";

    if (!selectedRunId) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">Select a payroll run to view allocations.</td>`;
      fieldAreasTbody.appendChild(tr);
      return;
    }

    if (!payload) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">Loading allocations...</td>`;
      fieldAreasTbody.appendChild(tr);
      return;
    }

    const areas = Array.isArray(payload?.areas) ? payload.areas : [];
    if (!areas.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">No Field area allocations found for this run (or no paid attendance days).</td>`;
      fieldAreasTbody.appendChild(tr);
    } else {
      areas.forEach((g) => {
        appendGroupHeader(fieldAreasTbody, g.area_place || "-", 6);

        const head = document.createElement("tr");
        head.className = "row-group-columns";
        head.innerHTML = `
          <td>ID</td>
          <td>Name</td>
          <td>Daily Rate</td>
          <td>No. of Days</td>
          <td>Dates</td>
          <td>Allocated Amount</td>
        `;
        fieldAreasTbody.appendChild(head);

        const rows = Array.isArray(g.rows) ? g.rows : [];
        rows.forEach((r) => {
          const dates = Array.isArray(r.date_ranges) ? r.date_ranges.join(", ") : "";
          const days = Number(r.paid_units || 0);
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${escapeHtml(r.emp_no || "-")}</td>
            <td>${escapeHtml(r.name || "-")}</td>
            <td>${escapeHtml(peso(r.daily_rate || 0))}</td>
            <td>${escapeHtml(String(days))}</td>
            <td>${escapeHtml(dates || "-")}</td>
            <td><strong>${escapeHtml(peso(r.allocated_amount || 0))}</strong></td>
          `;
          fieldAreasTbody.appendChild(tr);
        });

        const tr = document.createElement("tr");
        tr.className = "row-total";
        tr.innerHTML = `
          <td><strong>TOTAL</strong></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td><strong>${escapeHtml(peso(g.total_allocated_amount || 0))}</strong></td>
        `;
        fieldAreasTbody.appendChild(tr);
      });
    }

    const totals = Array.isArray(payload?.area_totals) ? payload.area_totals : [];
    if (!totals.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="3" class="muted small">No area totals.</td>`;
      fieldAreasTotalsTbody.appendChild(tr);
      return;
    }

    totals.forEach((t) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(t.area_place || "-")}</td>
        <td class="num">${escapeHtml(String(Number(t.paid_units || 0)))}</td>
        <td class="num"><strong>${escapeHtml(peso(t.amount || 0))}</strong></td>
      `;
      fieldAreasTotalsTbody.appendChild(tr);
    });
  }

  function renderOverall(rows) {
    if (!overallTbody) return;

    const gross = rows.reduce((a, r) => a + Number(r.gross || 0), 0);
    const deductions = rows.reduce((a, r) => a + Number(r.deductionsEe || 0), 0);
    const net = rows.reduce((a, r) => a + Number(r.netPay || 0), 0);
    const tax = rows.reduce((a, r) => a + Number(r.tax || 0), 0);

    const statutory = rows.reduce(
      (a, r) => a + Number(r.sssEe || 0) + Number(r.philhealthEe || 0) + Number(r.pagibigEe || 0),
      0
    );
    const charges = rows.reduce((a, r) => a + Number(r.chargesDeduction || 0), 0);
    const attendanceDeduction = rows.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0);

    overallTbody.innerHTML = "";

    const metrics = [
      ["Total Gross", gross],
      ["Total Deductions (EE)", deductions],
      ["Total Net Pay", net],
      ["Statutory Deductions (EE)", statutory],
      ["Withholding Tax", tax],
      ["Charges", charges],
      ["Attendance Deduction", attendanceDeduction],
    ];

    metrics.forEach(([label, value]) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(label)}</td>
        <td class="num"><strong>${escapeHtml(peso(value))}</strong></td>
      `;
      overallTbody.appendChild(tr);
    });
  }

  function renderIssues(rows) {
    if (!issuesTbody) return;

    const issues = computeIssues(rows);

    issuesTbody.innerHTML = "";
    issues.forEach((x) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(x.empId)}</td>
        <td>${escapeHtml(x.empName)}</td>
        <td>${escapeHtml(x.issue)}</td>
        <td>${escapeHtml(x.severity)}</td>
      `;
      issuesTbody.appendChild(tr);
    });

    if (!issues.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="4" class="muted small">No issues found.</td>`;
      issuesTbody.appendChild(tr);
    }
  }
  // =========================================================
  // MAIN RENDER
  // =========================================================
  function renderAll() {
    // If no run selected, show empty based on filters (but KPIs can still show)
    const filtered = applyFilters(REGISTER);
    const sorted = applySorting(filtered);

    renderKPIs(sorted);
    renderRegister(sorted);
    renderBreakdown(sorted);
    renderRemittance(sorted);
    renderSss(sorted);
    renderPhilHealth(sorted);
    renderPagibig(sorted);
    renderFieldAreaAllocations(FIELD_AREA_ALLOC);
    renderCompanyPayslips(sorted);
    renderOverall(sorted);
    renderAudit();
    renderIssues(sorted);
    renderAllCombined();
    refreshFloatingRegisterScrollbar();
  }

  // =========================================================
  // TABS
  // =========================================================
  function setActiveTab(tab) {
    activeTab = tab;

    tabBtns.forEach((b) => {
      const isActive = b.dataset.tab === tab;
      b.classList.toggle("is-active", isActive);
      b.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    ["all", "register", "breakdown", "remit", "sss", "philhealth", "pagibig", "fieldAreas", "companyPayslips", "overall", "audit", "issues"].forEach((k) => {
      const pane = $(`tab-${k}`);
      if (!pane) return;
      pane.hidden = k !== tab;
    });

    refreshFloatingRegisterScrollbar();
  }

  tabBtns.forEach((b) => {
    b.addEventListener("click", () => setActiveTab(b.dataset.tab || "all"));
  });

  // =========================================================
  // FILTER EVENTS
  // =========================================================
  [monthInput, cutoffSelect].forEach((el) => {
    el && el.addEventListener("change", async () => {
      await syncRunFromFilters();
    });
  });

  searchInput && searchInput.addEventListener("input", renderAll);

  function buildAssignmentSeg(assignments) {
    if (!assignmentSeg) return;
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
        chev.textContent = "\u25BE";
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
    applyInitialAssignmentFromQuery();
  }

  function applyInitialAssignmentFromQuery() {
    if (initialAssignmentApplied) return;
    if (!assignmentSeg || !segBtns.length) return;
    if (!initialAssignmentParam && !initialPlaceParam) {
      initialAssignmentApplied = true;
      return;
    }

    const desiredAssign = initialAssignmentParam || "All";
    const btn = segBtns.find((b) => (b.dataset.assign || "All") === desiredAssign);
    if (!btn) {
      initialAssignmentApplied = true;
      return;
    }

    btn.click();

    if (initialPlaceParam && desiredAssign !== "All") {
      const item = Array.from(assignmentSeg.querySelectorAll(".seg__dropdown-item"))
        .find((el) => {
          const place = el.getAttribute("data-place") || "";
          const group = el.closest(".seg__dropdown")?.getAttribute("data-group") || "";
          return place === initialPlaceParam && group === desiredAssign;
        });
      item && item.click();
    }

    initialAssignmentApplied = true;
  }

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
        renderAll();

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
        renderAll();
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

  async function loadRunData(runId) {
    if (!runId) return;
    setTopActionsEnabled(false);

    const pRows = apiFetch(`/payroll-runs/${encodeURIComponent(runId)}/rows`)
      .then((rows) => {
        REGISTER = Array.isArray(rows) ? rows.map(mapRow) : [];
      })
      .catch(() => {
        REGISTER = [];
      });

    const pAlloc = apiFetch(`/payroll-runs/${encodeURIComponent(runId)}/field-area-allocations`)
      .then((payload) => {
        FIELD_AREA_ALLOC = payload || { employees: [], area_totals: [] };
      })
      .catch(() => {
        FIELD_AREA_ALLOC = { employees: [], area_totals: [] };
      });

    await Promise.allSettled([pRows, pAlloc]);
    setTopActionsEnabled(true);
    renderAll();
  }

  async function syncRunFromFilters({ preferredRunId = "", fallbackOnEmpty = false } = {}) {
    setFilterLoading(true);
    try {
      const list = RUNS.filter(runMatchesFilters);
      let run = null;
      if (preferredRunId) {
        run = list.find((r) => r.id === String(preferredRunId)) || null;
      }
      if (!run && selectedRunId) {
        run = list.find((r) => r.id === selectedRunId) || null;
      }
      if (!run) {
        const nextId = pickDefaultRunId(list);
        run = list.find((r) => r.id === nextId) || null;
      }

      if (!run && fallbackOnEmpty) {
        const releasedRuns = RUNS.filter(isReleasedRun);
        const fallbackId = pickDefaultRunId(releasedRuns);
        const fallback = releasedRuns.find((r) => r.id === fallbackId) || null;
        if (fallback) {
          if (monthInput) monthInput.value = fallback.month || monthInput.value;
          if (cutoffSelect) cutoffSelect.value = fallback.cutoff || cutoffSelect.value || "All";
          run = fallback;
        }
      }

      selectedRunId = run ? run.id : "";
      setRunUI(run);
      REGISTER = [];
      FIELD_AREA_ALLOC = null;
      renderAll();

      if (!run) {
        setTopActionsEnabled(false);
        return;
      }
      await loadRunData(selectedRunId);
    } finally {
      setFilterLoading(false);
    }
  }

  // =========================================================
  // TOP ACTIONS
  // =========================================================
  function exportCsv(rows, filename) {
    const xlsx = window.XLSX;
    if (!xlsx) {
      alert("XLSX library not loaded. Please refresh the page.");
      return;
    }

    const aoaForRegister = (list) => {
      const headers = [
        "Emp ID", "Employee", "Assignment", "Present", "Absent", "Leave", "Daily Rate", "Attendance Pay", "Total Deductions", "Gross", "Net", "Payslip Status",
      ];
      const body = list.map((r) => [
        r.empId,
        r.empName,
        assignmentText(r),
        r.presentDays || 0,
        r.absentDays || 0,
        r.leaveDays || 0,
        Number(r.dailyRate || 0),
        Number(r.attendancePay || 0),
        Number(r.deductionsEe || 0),
        Number(r.gross || 0),
        Number(r.netPay || 0),
        r.payslipStatus || "",
      ]);
      return [headers, ...body];
    };

    const aoaForBreakdown = (list) => {
      const totals = [
        ["Attendance Deductions", list.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0)],
        ["Other Deductions", list.reduce((a, r) => a + Number(r.otherDeductions || 0), 0)],
        ["SSS (EE)", list.reduce((a, r) => a + Number(r.sssEe || 0), 0)],
        ["PhilHealth (EE)", list.reduce((a, r) => a + Number(r.philhealthEe || 0), 0)],
        ["Pag-IBIG (EE)", list.reduce((a, r) => a + Number(r.pagibigEe || 0), 0)],
        ["Withholding Tax", list.reduce((a, r) => a + Number(r.tax || 0), 0)],
        ["SSS (ER)", list.reduce((a, r) => a + Number(r.sssEr || 0), 0)],
        ["PhilHealth (ER)", list.reduce((a, r) => a + Number(r.philhealthEr || 0), 0)],
        ["Pag-IBIG (ER)", list.reduce((a, r) => a + Number(r.pagibigEr || 0), 0)],
      ];
      return [["Item", "Amount"], ...totals];
    };

    const aoaForRemit = (list) => {
      const headers = ["Employee", "SSS", "PhilHealth", "Pag-IBIG", "Total EE", "Total ER"];
      const body = list.map((r) => [
        r.empName,
        Number(r.sssEe || 0),
        Number(r.philhealthEe || 0),
        Number(r.pagibigEe || 0),
        Number(r.deductionsEe || 0),
        Number(r.employerShare || 0),
      ]);
      return [headers, ...body];
    };

    const aoaForPremium = (list, kind) => {
      const rowsPremium = buildPremiumRows(list, kind);
      const headers = ["External Company", "Employee", "EE", "ER", "Total"];
      const body = rowsPremium.map((r) => [r.company, r.empName, r.ee, r.er, r.total]);
      return [headers, ...body];
    };

    const aoaForOverall = (list) => {
      const gross = list.reduce((a, r) => a + Number(r.gross || 0), 0);
      const deductions = list.reduce((a, r) => a + Number(r.deductionsEe || 0), 0);
      const net = list.reduce((a, r) => a + Number(r.netPay || 0), 0);
      const tax = list.reduce((a, r) => a + Number(r.tax || 0), 0);
      const statutory = list.reduce((a, r) => a + Number(r.sssEe || 0) + Number(r.philhealthEe || 0) + Number(r.pagibigEe || 0), 0);
      const charges = list.reduce((a, r) => a + Number(r.chargesDeduction || 0), 0);
      const attendanceDeduction = list.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0);
      return [["Metric", "Amount"],
        ["Total Gross", gross],
        ["Total Deductions (EE)", deductions],
        ["Total Net Pay", net],
        ["Statutory Deductions (EE)", statutory],
        ["Withholding Tax", tax],
        ["Charges", charges],
        ["Attendance Deduction", attendanceDeduction],
      ];
    };

    const aoaForIssues = (list) => {
      const issues = computeIssues(list);
      return [["Emp ID", "Employee", "Issue", "Severity"], ...issues.map((r) => [r.empId, r.empName, r.issue, r.severity])];
    };

    const aoaForCompanyPayslips = (list) => {
      const headers = ["Company", "Name", "Gross Pay", "Deductions", "Attendance Deduction", "Charges", "Net Pay"];
      const body = list
        .slice()
        .sort((a, b) => {
          const ca = companyLabel(a).localeCompare(companyLabel(b));
          if (ca !== 0) return ca;
          return String(a.empName || "").localeCompare(String(b.empName || ""));
        })
        .map((r) => [
          companyLabel(r),
          r.empName,
          Number(r.gross || 0),
          Number(r.deductionsEe || 0),
          Number(r.attendanceDeduction || 0),
          Number(r.chargesDeduction || 0),
          Number(r.netPay || 0),
        ]);
      return [headers, ...body];
    };

    const aoaForAudit = () => {
      const list = RUNS.filter(runMatchesFilters);
      const headers = ["Run ID", "Period", "Status", "Employees", "Total Net", "Processed At", "Processed By", "Payslips Generated", "Released At"];
      const body = list.map((r) => [
        r.id,
        `${r.month} (${r.cutoffLabel})`,
        r.status,
        r.employees,
        Number(r.totalNet || 0),
        r.processedAt,
        r.processedBy,
        r.payslipsGeneratedAt || "",
        r.releasedAt || "",
      ]);
      return [headers, ...body];
    };

    const aoaForAll = (list) => {
      const sorted = list.slice().sort((a, b) => {
        const n = String(a.empName || "").localeCompare(String(b.empName || ""));
        if (n !== 0) return n;
        return String(a.empId || "").localeCompare(String(b.empId || ""));
      });

      const payrollHeader = ["Emp ID", "Employee", "Gross", "EE", "Net"];
      const payrollBody = sorted.map((r) => [
        r.empId,
        r.empName,
        Number(r.gross || 0),
        Number(r.deductionsEe || 0),
        Number(r.netPay || 0),
      ]);

      const premiumHeader = ["Employee", "SSS", "PhilHealth", "Pag-IBIG", "Total EE", "Total ER"];
      const premiumBody = sorted.map((r) => [
        r.empName,
        Number(r.sssEe || 0),
        Number(r.philhealthEe || 0),
        Number(r.pagibigEe || 0),
        Number(r.deductionsEe || 0),
        Number(r.employerShare || 0),
      ]);

      return [
        ["Tab 1: Payroll Register"],
        payrollHeader,
        ...payrollBody,
        [],
        ["Tab 2: Government Premiums"],
        premiumHeader,
        ...premiumBody,
      ];
    };

    const appendSheet = (wb, name, aoa) => {
      const sheetName = String(name || "Sheet").slice(0, 31);
      const ws = xlsx.utils.aoa_to_sheet(aoa);
      xlsx.utils.book_append_sheet(wb, ws, sheetName);
    };

    const wb = xlsx.utils.book_new();

    if (activeTab === "all") {
      appendSheet(wb, "All", aoaForAll(rows));
      appendSheet(wb, "Payroll Register", aoaForRegister(rows));
      appendSheet(wb, "Deductions", aoaForBreakdown(rows));
      appendSheet(wb, "Government Premiums", aoaForRemit(rows));
      appendSheet(wb, "SSS", aoaForPremium(rows, "sss"));
      appendSheet(wb, "PhilHealth", aoaForPremium(rows, "philhealth"));
      appendSheet(wb, "Pag-IBIG", aoaForPremium(rows, "pagibig"));
      appendSheet(wb, "Company Payslips", aoaForCompanyPayslips(rows));
      appendSheet(wb, "Overall", aoaForOverall(rows));
      appendSheet(wb, "Issues", aoaForIssues(rows));
      appendSheet(wb, "Audit", aoaForAudit());
    } else if (activeTab === "audit") {
      appendSheet(wb, "Audit", aoaForAudit());
    } else if (activeTab === "companyPayslips") {
      appendSheet(wb, "Company Payslips", aoaForCompanyPayslips(rows));
    } else if (activeTab === "overall") {
      appendSheet(wb, "Overall", aoaForOverall(rows));
    } else if (activeTab === "breakdown") {
      appendSheet(wb, "Deductions", aoaForBreakdown(rows));
    } else if (activeTab === "remit") {
      appendSheet(wb, "Government Premiums", aoaForRemit(rows));
    } else if (activeTab === "sss") {
      appendSheet(wb, "SSS", aoaForPremium(rows, "sss"));
    } else if (activeTab === "philhealth") {
      appendSheet(wb, "PhilHealth", aoaForPremium(rows, "philhealth"));
    } else if (activeTab === "pagibig") {
      appendSheet(wb, "Pag-IBIG", aoaForPremium(rows, "pagibig"));
    } else if (activeTab === "issues") {
      appendSheet(wb, "Issues", aoaForIssues(rows));
    } else {
      appendSheet(wb, "Payroll Register", aoaForRegister(rows));
    }

    const outName = String(filename || "report.xlsx").replace(/\.csv$/i, ".xlsx");
    xlsx.writeFile(wb, outName, { bookType: "xlsx" });
  }

  function downloadCsv(csv, filename) {
    // Backward-compatible shim. Current implementation writes XLSX directly.
    const xlsx = window.XLSX;
    if (!xlsx) {
      alert("XLSX library not loaded. Please refresh the page.");
      return;
    }
    const wb = xlsx.read(csv, { type: "string" });
    const outName = String(filename || "report.xlsx").replace(/\.csv$/i, ".xlsx");
    xlsx.writeFile(wb, outName, { bookType: "xlsx" });
  }

  exportCsvBtn &&
    exportCsvBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const rows = applySorting(applyFilters(REGISTER));
      exportCsv(rows, `report_${selectedRunId}_${activeTab}.xlsx`);
    });

  printBtn &&
    printBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const targetPane = $(`tab-${activeTab}`) || $("tab-register");
      if (targetPane) targetPane.classList.add("print-target");

      const finishPrint = () => {
        window.removeEventListener("afterprint", finishPrint);
        if (targetPane) targetPane.classList.remove("print-target");
      };

      window.addEventListener("afterprint", finishPrint);
      window.print();
    });

  downloadPdfBtn &&
    downloadPdfBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const targetPane = $(`tab-${activeTab}`) || $("tab-register");
      if (targetPane) targetPane.classList.add("print-target");

      const originalTitle = document.title;
      const run = getRun(selectedRunId);
      const safeTab = String(activeTab || "register").replace(/[^a-z0-9_-]/gi, "_");
      document.title = `report_${run?.runCode || selectedRunId}_${safeTab}`;

      const finishPrint = () => {
        window.removeEventListener("afterprint", finishPrint);
        if (targetPane) targetPane.classList.remove("print-target");
        document.title = originalTitle;
      };

      window.addEventListener("afterprint", finishPrint);
      window.print();
    });

  function loadFilterOptions() {
    return apiFetch("/employees/filters")
      .then((data) => {
        const assignments = Array.isArray(data.assignments) ? data.assignments : [];
        areaPlaces = (data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places))
          ? data.area_places
          : {};
        buildAssignmentSeg(assignments);
      })
      .catch(() => {
        areaPlaces = {};
        buildAssignmentSeg(["Davao", "Tagum", "Field"]);
      });
  }

  // =========================================================
  // First render (no run selected)
  // =========================================================
  initFloatingRegisterScrollbar();
  window.addEventListener("resize", refreshFloatingRegisterScrollbar);
  window.addEventListener("scroll", refreshFloatingRegisterScrollbar, { passive: true });
  contentScroller && contentScroller.addEventListener("scroll", refreshFloatingRegisterScrollbar, { passive: true });
  setRunUI(null);
  setActiveTab("all");
  loadFilterOptions()
    .finally(() => loadRuns())
    .finally(renderAll);
});






  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  async function apiFetch(url) {
    const res = await fetch(url, {
      headers: {
        Accept: "application/json",
        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
      },
      credentials: "same-origin",
    });
    if (!res.ok) throw new Error("Request failed.");
    return res.json();
  }

