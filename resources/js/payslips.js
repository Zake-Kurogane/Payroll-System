import { initClock } from "./shared/clock";
import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { formatMoney } from "./shared/format";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
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

  const sumAmounts = (arr) =>
    (Array.isArray(arr) ? arr : []).reduce((a, r) => a + Number(r?.amount || 0), 0);

  const maskAccount = (num) => {
    const raw = String(num || "");
    if (!raw) return "";
    const last4 = raw.slice(-4);
    return raw.length > 4 ? `****${last4}` : raw;
  };

  function mapPayslipFromApi(p) {
    const adjustments = Array.isArray(p.adjustments) ? p.adjustments : [];
    const earnAdj = adjustments.filter(a => a.type === "earning");
    const dedAdj = adjustments.filter(a => a.type === "deduction");
    const otHours = Number(p.ot_hours || 0);
    const otPay = Number(p.ot_pay || 0);
    const otRate = otHours > 0 ? (otPay / otHours) : 0;
    return {
      id: String(p.id),
      runId: String(p.payroll_run_id || ""),
      empId: p.emp_no || String(p.employee_id || ""),
      empName: p.emp_name || "",
      department: p.department || "",
      position: p.position || "",
      empType: p.employment_type || "",
      assignmentType: p.assignment_type || "",
      areaPlace: p.area_place || "",
      periodMonth: p.period_month || "",
      cutoff: p.cutoff || "",
      netPay: Number(p.net_pay || 0),
      releaseStatus: p.release_status || "Draft",
      payslipNo: p.payslip_no || "",
      generatedDate: p.generated_at ? String(p.generated_at).slice(0, 10) : "",
      payDate: p.pay_date || "—",
      bankName: p.bank_name || "",
      accountNumber: p.bank_account_number || "",
      payMethod: p.pay_method || "CASH",
      basicPay: Number(p.basic_pay_cutoff || 0),
      allowancePay: Number(p.allowance_cutoff || 0),
      otHours,
      otRate,
      otPay,
      earningsAdjustments: earnAdj.map(a => ({ name: a.name, amount: Number(a.amount || 0) })),
      deductionAdjustments: dedAdj.map(a => ({ name: a.name, amount: Number(a.amount || 0) })),
      attendanceDeductionTotal: Number(p.attendance_deduction || 0),
      sssEe: Number(p.sss_ee || 0),
      philhealthEe: Number(p.philhealth_ee || 0),
      pagibigEe: Number(p.pagibig_ee || 0),
      withholdingTax: Number(p.tax || 0),
      otherDeductionTotal: 0,
      cashAdvance: Number(p.cash_advance || 0),
      sssEr: Number(p.sss_er || 0),
      philhealthEr: Number(p.philhealth_er || 0),
      pagibigEr: Number(p.pagibig_er || 0),
      gross: Number(p.gross || 0),
      deductionsTotal: Number(p.deductions_total || 0),
      notes: "—",
      deliveryStatus: p.delivery_status || "Not Sent",
      email: "",
    };
  }

  function safeText(id, value) {
    const el = $(id);
    if (el) el.textContent = value;
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

  function cutoffDates(periodMonth, cutoffVal) {
    if (!periodMonth) return { from: "—", to: "—" };
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
  const assignmentSeg = document.querySelector(".filterbar__right .seg[aria-label='Assignment filter']");
  let segBtns = $$(".filterbar__right .seg__btn[data-assign]");
  const areaFilterWrap = $("areaFilterWrap");
  const areaPlaceFilter = $("areaPlaceFilter");

  const runSelect = $("runSelect");
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

  // new: adjustment containers in preview
  const psEarnAdjRows = $("psEarnAdjRows");
  const psDedAdjRows = $("psDedAdjRows");

  // =========================================================
  // DATA (backend-driven)
  // =========================================================
  let runs = [];
  let payslips = [];

  // =========================================================
  // STATE
  // =========================================================
  let selectedRunId = "";
  let assignmentFilter = "All";
  let sortKey = "empName";
  let sortDir = "asc";

  let page = 1;
  let perPage = rowsPerPage ? Number(rowsPerPage.value || 20) : 20;

  const selectedIdsSet = new Set();

  // =========================================================
  // INIT DEFAULTS
  // =========================================================
  if (monthInput && !monthInput.value) {
    const d = new Date();
    monthInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
  }
  function setAreaFilterVisibility(isArea) {
    if (!areaFilterWrap) return;
    areaFilterWrap.hidden = !isArea;
    areaFilterWrap.style.display = isArea ? "" : "none";
  }
  setAreaFilterVisibility(false);

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

  // =========================================================
  // RUN SELECTOR
  // =========================================================
  function initRunSelect() {
    if (!runSelect) return;
    runSelect.innerHTML =
      `<option value="" selected>— Select a run —</option>` +
      runs.map((r) => {
        const cutoffLabel = formatCutoffLabel(r.period_month, r.cutoff);
        const label = `${r.period_month} (${cutoffLabel}) • ${r.assignment_filter || "All"} • ${r.status} • ${r.headcount || 0} employees`;
        return `<option value="${escapeHtml(String(r.id))}">${escapeHtml(label)}</option>`;
      }).join("");
  }

  function setRunUI(run) {
    if (!run) {
      safeText("runEmployees", "—");
      safeText("runTotalNet", "—");
      safeText("runProcessedAt", "—");
      safeText("runProcessedBy", "—");
      setTopActionsEnabled(false);
      return;
    }

    safeText("runEmployees", String(run.headcount ?? 0));
    safeText("runTotalNet", peso(run.total_net ?? 0));
    safeText("runProcessedAt", run.locked_at ? new Date(run.locked_at).toLocaleString() : "—");
    safeText("runProcessedBy", run.created_by_name || "—");
    setTopActionsEnabled(true);
    if (sendEmailBtn) {
      const ok = run.status === "Locked" || run.status === "Released";
      sendEmailBtn.disabled = !ok;
    }
  }

  async function loadRuns() {
    try {
      const data = await apiFetch("/payslips/runs");
      runs = Array.isArray(data) ? data.filter(r => r.status === "Locked" || r.status === "Released") : [];
      initRunSelect();
    } catch {
      runs = [];
      initRunSelect();
    }
  }

  async function loadFilterOptions() {
    try {
      const data = await apiFetch("/employees/filters");
      const assignments = Array.isArray(data.assignments) ? data.assignments : [];
      const areaPlaces = Array.isArray(data.area_places) ? data.area_places : [];

      if (assignmentSeg) {
        assignmentSeg.innerHTML = "";
        const allBtn = document.createElement("button");
        allBtn.className = "seg__btn is-active";
        allBtn.type = "button";
        allBtn.dataset.assign = "All";
        allBtn.setAttribute("aria-selected", "true");
        allBtn.textContent = "All";
        assignmentSeg.appendChild(allBtn);

        assignments.forEach((label) => {
          const btn = document.createElement("button");
          btn.className = "seg__btn";
          btn.type = "button";
          btn.dataset.assign = label;
          btn.setAttribute("aria-selected", "false");
          btn.textContent = label;
          assignmentSeg.appendChild(btn);
        });

        segBtns = Array.from(assignmentSeg.querySelectorAll(".seg__btn[data-assign]"));
        bindAssignmentButtons();
      }

      if (areaPlaceFilter) {
        areaPlaceFilter.innerHTML = "";
        const allOpt = document.createElement("option");
        allOpt.value = "All";
        allOpt.textContent = "All";
        allOpt.selected = true;
        areaPlaceFilter.appendChild(allOpt);
        areaPlaces.forEach((label) => {
          const opt = document.createElement("option");
          opt.value = label;
          opt.textContent = label;
          areaPlaceFilter.appendChild(opt);
        });
      }
    } catch {
      // keep defaults
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

  initRunSelect();
  setRunUI(null);

  // =========================================================
  // SORTING + FILTERING
  // =========================================================
  function assignmentText(p) {
    if (p.assignmentType === "Area") return `Area (${p.areaPlace || "—"})`;
    return p.assignmentType || "—";
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
    const areaPlaceVal = areaPlaceFilter?.value || "All";

    return list
      .filter((p) => !selectedRunId || p.runId === selectedRunId)
      .filter((p) => !monthVal || p.periodMonth === monthVal)
      .filter((p) => assignmentFilter === "All" || p.assignmentType === assignmentFilter)
      .filter((p) => {
        if (assignmentFilter !== "Area") return true;
        if (areaPlaceVal === "All") return true;
        return (p.areaPlace || "") === areaPlaceVal;
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
    return `<span class="badge">${escapeHtml(s)}</span>`;
  }
  function deliveryBadge(status) {
    const s = String(status || "Not sent");
    return `<span class="badge">${escapeHtml(s)}</span>`;
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
        <td>${escapeHtml(`${p.periodMonth} (${formatCutoffLabel(p.periodMonth, p.cutoff)})`)}</td>
        <td class="num"><strong>${escapeHtml(peso(p.netPay))}</strong></td>
        <td>${badge(p.releaseStatus)}</td>
        <td>${deliveryBadge(p.deliveryStatus)}</td>
        <td class="col-actions">
          <div class="iconrow">
            <button class="iconbtn" type="button" data-action="view" data-id="${escapeHtml(p.id)}" title="View">👁</button>
            <button class="iconbtn" type="button" data-action="pdf" data-id="${escapeHtml(p.id)}" title="Download PDF">⬇</button>
            <button class="iconbtn" type="button" data-action="print" data-id="${escapeHtml(p.id)}" title="Print">🖨</button>
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
  // PREVIEW DRAWER (✅ Correctness: adjustments + cutoff dates)
  // =========================================================
  function findPayslip(id) {
    return payslips.find((p) => p.id === id) || null;
  }

  function set(id, value) {
    const el = $(id);
    if (el) el.textContent = value ?? "—";
  }
  function setMoney(id, value) {
    set(id, peso(value));
  }

  function renderAdjustmentRows(containerEl, items, mode) {
    // mode: "earning" uses 4 columns table (EARNINGS), "deduction" uses 3 columns table (DEDUCTIONS)
    if (!containerEl) return;
    containerEl.innerHTML = "";

    const list = Array.isArray(items) ? items.filter(x => Number(x?.amount || 0) !== 0) : [];
    if (!list.length) return;

    if (mode === "earning") {
      list.forEach((a) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(a.name || "Adjustment (Earning)")}</td>
          <td class="num">—</td>
          <td class="num">—</td>
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
        <td class="num">—</td>
        <td class="num">${escapeHtml(peso(a.amount))}</td>
      `;
      containerEl.appendChild(tr);
    });
  }

  function openPayslipDrawer(p) {
    if (!psDrawer || !psOverlay) return;

    if (psDrawerMeta) {
      psDrawerMeta.textContent = `Employee: ${p.empName} (${p.empId})  •  Period: ${p.periodMonth} (${formatCutoffLabel(p.periodMonth, p.cutoff)})  •  Status: ${p.releaseStatus}`;
    }

    // header
    set("psNo", p.payslipNo || `PS-${p.id}`);
    set("psGenerated", p.generatedDate || todayISO());

    // employee
    set("psEmpName", p.empName);
    set("psEmpId", p.empId);
    set("psDept", p.department || "—");
    set("psPos", p.position || "—");
    set("psType", p.empType || "—");
    set("psAssign", assignmentText(p));

    // pay period
    set("psMonth", p.periodMonth || "—");
    set("psCutoff", p.cutoff === "11-25" ? "11–25" : p.cutoff === "26-10" ? "26–10" : "—");

    // ✅ cutoff dates
    const cd = cutoffDates(p.periodMonth, p.cutoff);
    set("psCutoffDates", cd.from === "—" ? "—" : `${cd.from} to ${cd.to}`);

    set("psPayDate", p.payDate || "—");
    const payMethod = String(p.payMethod || "").toUpperCase();
    const hasBank = payMethod === "BANK" || !!(p.accountNumber || "").trim();
    set("psPayMethod", payMethod === "BANK" ? "Bank" : "Cash");
    const bankRow = $("psBankRow");
    const acctRow = $("psAccountRow");
    if (bankRow) bankRow.style.display = hasBank ? "" : "none";
    if (acctRow) acctRow.style.display = hasBank ? "" : "none";
    set("psBank", hasBank ? (p.bankName || "—") : "—");
    set("psAccount", hasBank ? `****${String(p.accountNumber).slice(-4)}` : "—");

    const statusBadge = $("psStatusBadge");
    if (statusBadge) statusBadge.textContent = p.releaseStatus || "Draft";

    // earnings base
    setMoney("psBasicPay", p.basicPay);
    setMoney("psAllowancePay", p.allowancePay);

    set("psOtHours", Number(p.otHours || 0).toFixed(2));
    setMoney("psOtRate", p.otRate);
    setMoney("psOtPay", p.otPay);

    // ✅ adjustments (earnings + deductions)
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
    const otherDed = Number(p.otherDeductionTotal || 0);

    const statutoryEeTotal = sssEe + phEe + piEe + tax;
    setMoney("psAttDedTotal", attendanceDed);
    setMoney("psSssEe", sssEe);
    setMoney("psPhEe", phEe);
    setMoney("psPiEe", piEe);
    setMoney("psTax", tax);
    setMoney("psStatEeTotal", statutoryEeTotal);

    setMoney("psCashAdv", cashAdv);
    setMoney("psOtherDedTotal", otherDed);

    // employer share
    const sssEr = Number(p.sssEr || 0);
    const phEr = Number(p.philhealthEr || 0);
    const piEr = Number(p.pagibigEr || 0);
    const erTotal = sssEr + phEr + piEr;

    setMoney("psSssEr", sssEr);
    setMoney("psPhEr", phEr);
    setMoney("psPiEr", piEr);
    setMoney("psErTotal", erTotal);

    // ✅ totals (computed to ensure correctness)
    const totalEarnAdj = sumAmounts(earnAdj);
    const totalDedAdj = sumAmounts(dedAdj);

    const baseGross = Number(p.basicPay || 0) + Number(p.allowancePay || 0) + Number(p.otPay || 0) + totalEarnAdj;
    const baseDed = attendanceDed + statutoryEeTotal + cashAdv + otherDed + totalDedAdj;
    const computedNet = baseGross - baseDed;

    setMoney("psGross", baseGross);
    setMoney("psDedTotal", baseDed);

    // summary
    setMoney("psSumGross", baseGross);
    setMoney("psSumDed", baseDed);

    // net: show computed (to match breakdown)
    setMoney("psNet", computedNet);

    const notes = $("psNotes");
    if (notes) notes.textContent = `Adjust Notes: ${p.notes || "—"}`;

    // open UI
    psDrawer.classList.add("is-open");
    psDrawer.setAttribute("aria-hidden", "false");
    psOverlay.hidden = false;
    document.body.style.overflow = "hidden";

    // actions
    if (psPrintBtn) psPrintBtn.onclick = () => window.print();

    if (psDownloadBtn) psDownloadBtn.onclick = () => alert("PDF download: connect to backend PDF generation later.");

    if (psReleaseBtn) {
      psReleaseBtn.disabled = p.releaseStatus === "Released";
      psReleaseBtn.onclick = () => {
        p.releaseStatus = "Released";
        render();

        const sb = $("psStatusBadge");
        if (sb) sb.textContent = "Released";
        if (psDrawerMeta) {
          psDrawerMeta.textContent = `Employee: ${p.empName} (${p.empId})  •  Period: ${p.periodMonth} (${formatCutoffLabel(p.periodMonth, p.cutoff)})  •  Status: Released`;
        }
        psReleaseBtn.disabled = true;
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
        openPayslipDrawer(p);
        setTimeout(() => window.print(), 50);
        return;
      }
      if (action === "pdf") return alert("Row PDF download: connect to backend later.");
    });

  // =========================================================
  // SEGMENT FILTER
  // =========================================================
  function bindAssignmentButtons() {
    segBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        segBtns.forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        assignmentFilter = btn.dataset.assign || "All";
        setAreaFilterVisibility(assignmentFilter === "Area");
        page = 1;
        render();
      });
    });
  }
  bindAssignmentButtons();

  // filters
  monthInput && monthInput.addEventListener("change", () => { page = 1; render(); });
  searchInput && searchInput.addEventListener("input", () => { page = 1; render(); });
  areaPlaceFilter && areaPlaceFilter.addEventListener("change", () => { page = 1; render(); });

  // run change
  runSelect &&
    runSelect.addEventListener("change", () => {
      selectedRunId = runSelect.value || "";
      const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      setRunUI(run);

      selectedIdsSet.clear();
      $$("input.rowCheck").forEach((cb) => (cb.checked = false));
      if (checkAll) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      }

      if (run) {
        if (monthInput) monthInput.value = run.period_month || "";
      }

      page = 1;
      loadPayslips(selectedRunId);
    });

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
    bulkReleaseBtn.addEventListener("click", () => {
      const ids = selectedIds();
      if (!ids.length) return;
      ids.forEach((id) => {
        const p = findPayslip(id);
        if (p) p.releaseStatus = "Released";
      });
      render();
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
      const p = findPayslip(ids[0]);
      if (!p) return;
      openPayslipDrawer(p);
      setTimeout(() => window.print(), 50);
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
      `${p.periodMonth} (${formatCutoffLabel(p.periodMonth, p.cutoff)})`,
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
      const list = applySorting(applyFilters(payslips));
      exportCsv(list, `payslips_${selectedRunId}.csv`);
    });

  exportPdfBtn &&
    exportPdfBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const ids = selectedIds();
      alert(ids.length ? "Export PDF (Selected): connect backend later." : "Export PDF (All): connect backend later.");
    });

  printBtn &&
    printBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const ids = selectedIds();
      const list = applySorting(applyFilters(payslips));
      const p = ids.length ? findPayslip(ids[0]) : list[0];
      if (!p) return;
      openPayslipDrawer(p);
      setTimeout(() => window.print(), 50);
    });

  releaseAllBtn &&
    releaseAllBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const list = payslips.filter((p) => p.runId === selectedRunId);
      list.forEach((p) => (p.releaseStatus = "Released"));
      render();
      alert("All payslips in this run marked as Released (demo).");
    });

  sendEmailBtn &&
    sendEmailBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      const run = runs.find((r) => String(r.id) === String(selectedRunId)) || null;
      if (!run || (run.status !== "Locked" && run.status !== "Released")) {
        alert("Email sending is available after the run is Locked/Released.");
        return;
      }

      const list = payslips.filter((p) => p.runId === selectedRunId);
      const skipped = [];
      list.forEach((p) => {
        if (!p.email) {
          skipped.push(p.empId);
          return;
        }
        p.deliveryStatus = "Queued";
      });
      render();

      if (skipped.length) {
        alert(`Skipped: no email (${skipped.length})`);
      }

      setTimeout(() => {
        list.forEach((p) => {
          if (p.email) p.deliveryStatus = "Sent";
        });
        render();
        alert("Payslips queued and sent (demo).");
      }, 600);
    });

  // init
  bindHeaderSorting();

  const params = new URLSearchParams(window.location.search);
  const initialRunId = params.get("run_id") || "";

  Promise.resolve()
    .then(() => loadPayrollCalendarSettings())
    .then(() => loadFilterOptions())
    .then(() => loadRuns())
    .then(() => {
      if (initialRunId && runSelect) {
        selectedRunId = initialRunId;
        runSelect.value = initialRunId;
        const run = runs.find((r) => String(r.id) === String(initialRunId)) || null;
        setRunUI(run);
        if (run && monthInput) monthInput.value = run.period_month || "";
        return loadPayslips(initialRunId);
      }
      render();
    })
    .catch((err) => alert(err.message || "Failed to initialize payslips."));
});

