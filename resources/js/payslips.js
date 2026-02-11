document.addEventListener("DOMContentLoaded", () => {

  // =========================================================
  // HELPERS
  // =========================================================
  const $ = (id) => document.getElementById(id);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));
  const normalize = (s) => String(s || "").toLowerCase().trim();

  const peso = (n) => {
    const num = Number(n || 0);
    if (!isFinite(num)) return "₱ 0.00";
    return "₱ " + num.toFixed(2);
  };

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

    // =========================================================
  // SIDEBAR CLOCK (footer time/date)
  // Needs: <div id="clock"></div> and <div id="date"></div>
  // =========================================================
  const clockEl = $("clock");
  const dateEl = $("date");

  function tick() {
    const d = new Date();
    let h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12;
    h = h ? h : 12;

    if (clockEl) clockEl.textContent = `${pad(h)}:${pad(m)} ${ampm}`;
    if (dateEl) dateEl.textContent = `${pad(d.getMonth() + 1)}/${pad(d.getDate())}/${d.getFullYear()}`;
  }
  tick();
  setInterval(tick, 1000);

  // =========================================================
  // USER DROPDOWN (top right ADMIN menu)
  // Needs: #userMenuBtn and #userMenu
  // =========================================================
  const userMenuBtn = $("userMenuBtn");
  const userMenu = $("userMenu");

  function closeUserMenu() {
    if (!userMenuBtn || !userMenu) return;
    userMenu.classList.remove("is-open");
    userMenuBtn.setAttribute("aria-expanded", "false");
  }

  function toggleUserMenu() {
    if (!userMenuBtn || !userMenu) return;
    const isOpen = userMenu.classList.contains("is-open");
    userMenu.classList.toggle("is-open", !isOpen);
    userMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }

  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleUserMenu();
    });

    document.addEventListener("click", (e) => {
      if (!userMenu.contains(e.target) && e.target !== userMenuBtn) closeUserMenu();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeUserMenu();
    });
  }

  function safeText(id, value) {
    const el = $(id);
    if (el) el.textContent = value;
  }

  function safeHTML(id, value) {
    const el = $(id);
    if (el) el.innerHTML = value;
  }

  const monthInput = $("monthInput");
  const cutoffSelect = $("cutoffSelect");
  const searchInput = $("searchInput");
  const segBtns = $$(".seg__btn");
  const areaWrap = $("areaWrap");
  const areaPlaceFilter = $("areaPlaceFilter");

  // Run selector
  const runSelect = $("runSelect");
  const runBadge = $("runBadge");
  const runEmployees = $("runEmployees");
  const runTotalNet = $("runTotalNet");
  const runProcessedAt = $("runProcessedAt");
  const runProcessedBy = $("runProcessedBy");

  const openRunSummaryBtn = $("openRunSummaryBtn");
  const releaseAllBtn = $("releaseAllBtn");

  // Actions (disabled until run selected)
  const exportPdfSelectedBtn = $("exportPdfSelectedBtn");
  const exportPdfAllBtn = $("exportPdfAllBtn");
  const exportCsvBtn = $("exportCsvBtn");
  const printSelectedBtn = $("printSelectedBtn");
  const printAllBtn = $("printAllBtn");

  // Table
  const tbody = $("payslipTbody");
  const resultsMeta = $("resultsMeta");

  // Selection / bulk bar (optional)
  const checkAll = $("checkAll");
  const bulkBar = $("bulkBar");
  const selectedCount = $("selectedCount");
  const bulkPrintBtn = $("bulkPrintBtn");
  const bulkMarkReleasedBtn = $("bulkMarkReleasedBtn");
  const bulkDownloadBtn = $("bulkDownloadBtn");
  const bulkClearBtn = $("bulkClearBtn");

  // Pagination
  const rowsPerPage = $("rowsPerPage");
  const pagePrev = $("pagePrev");
  const pageNext = $("pageNext");
  const pageLabel = $("pageLabel");
  const firstPage = $("firstPage");
  const lastPage = $("lastPage");
  const pageInput = $("pageInput");
  const totalPages = $("totalPages");

  // Payslip Preview Drawer elements (from the previous snippet)
  const psDrawer = $("psDrawer");
  const psOverlay = $("psOverlay");
  const psCloseBtn = $("psCloseBtn");
  const psCloseFooterBtn = $("psCloseFooterBtn");
  const psPrintBtn = $("psPrintBtn");
  const psDownloadBtn = $("psDownloadBtn");
  const psReleaseBtn = $("psReleaseBtn");
  const psDrawerMeta = $("psDrawerMeta");


  const RUNS = [
    {
      id: "RUN-2026-01-16E-ALL",
      month: "2026-01",
      cutoff: "16–End",
      assignment: "All",
      status: "Processed", // Processed / Released
      employees: 32,
      totalNet: 512345.67,
      processedAt: "2026-01-31 17:20",
      processedBy: "Admin",
    },
    {
      id: "RUN-2026-02-115-TAGUM",
      month: "2026-02",
      cutoff: "1–15",
      assignment: "Tagum",
      status: "Released",
      employees: 18,
      totalNet: 221900.0,
      processedAt: "2026-02-15 18:05",
      processedBy: "Admin",
    },
  ];

  // Each payslip belongs to a run
  const PAYSLIPS = [
    {
      id: "PS-0001",
      runId: "RUN-2026-01-16E-ALL",
      empId: "1023",
      empName: "Dela Cruz, Juan",
      department: "Sales",
      position: "Field Representative",
      empType: "Regular",
      assignmentType: "Tagum", // Tagum/Davao/Area
      areaPlace: "",
      periodMonth: "2026-01",
      cutoff: "16–End",
      netPay: 7997.5,
      releaseStatus: "Draft", // Draft/Released/Voided

      // preview fields (demo)
      payslipNo: "PS-2026-01-00123",
      generatedDate: "2026-02-10",
      payDate: "—",
      payMethod: "Bank",
      accountMasked: "****7890",

      dailyRate: 610,
      presentDays: 12,
      leaveDays: 0,
      absentDays: 1,
      attendancePay: 7320,
      otHours: 4.5,
      otRate: 95,
      otPay: 427.5,
      riceAllowance: 500,
      transportAllowance: 300,
      allowanceTotal: 800,
      gross: 8547.5,

      lateDeduction: 0,
      undertimeDeduction: 0,
      unpaidLeaveDeduction: 0,
      attendanceDeductionTotal: 0,

      sssEe: 300,
      philhealthEe: 150,
      pagibigEe: 100,
      withholdingTax: 0,
      statutoryEeTotal: 550,

      cashAdvance: 0,
      oneTimeDeduction: 0,
      loans: 0,
      otherDeductionTotal: 0,

      deductionTotal: 550,

      sssEr: 650,
      philhealthEr: 300,
      pagibigEr: 200,
      employerShareTotal: 1150,

      notes: "—",
    },
    {
      id: "PS-0002",
      runId: "RUN-2026-01-16E-ALL",
      empId: "1044",
      empName: "Santos, Maria",
      department: "Finance",
      position: "Payroll Clerk",
      empType: "Regular",
      assignmentType: "Area",
      areaPlace: "Laak",
      periodMonth: "2026-01",
      cutoff: "16–End",
      netPay: 9050,
      releaseStatus: "Released",

      payslipNo: "PS-2026-01-00124",
      generatedDate: "2026-02-10",
      payDate: "—",
      payMethod: "Cash",
      accountMasked: "—",

      dailyRate: 700,
      presentDays: 12,
      leaveDays: 0,
      absentDays: 0,
      attendancePay: 8400,
      otHours: 2,
      otRate: 105,
      otPay: 210,
      riceAllowance: 300,
      transportAllowance: 0,
      allowanceTotal: 300,
      gross: 8910,

      lateDeduction: 0,
      undertimeDeduction: 0,
      unpaidLeaveDeduction: 0,
      attendanceDeductionTotal: 0,

      sssEe: 320,
      philhealthEe: 160,
      pagibigEe: 100,
      withholdingTax: 0,
      statutoryEeTotal: 580,

      cashAdvance: 0,
      oneTimeDeduction: 0,
      loans: 0,
      otherDeductionTotal: 0,

      deductionTotal: 580,

      sssEr: 680,
      philhealthEr: 320,
      pagibigEr: 200,
      employerShareTotal: 1200,

      notes: "—",
    },
    {
      id: "PS-0101",
      runId: "RUN-2026-02-115-TAGUM",
      empId: "1102",
      empName: "Garcia, Leo",
      department: "Operations",
      position: "Logistics Aide",
      empType: "Contractual",
      assignmentType: "Tagum",
      areaPlace: "",
      periodMonth: "2026-02",
      cutoff: "1–15",
      netPay: 6200,
      releaseStatus: "Released",

      payslipNo: "PS-2026-02-00088",
      generatedDate: "2026-02-15",
      payDate: "—",
      payMethod: "Bank",
      accountMasked: "****1234",

      dailyRate: 500,
      presentDays: 10,
      leaveDays: 0,
      absentDays: 0,
      attendancePay: 5000,
      otHours: 1,
      otRate: 80,
      otPay: 80,
      riceAllowance: 200,
      transportAllowance: 0,
      allowanceTotal: 200,
      gross: 5280,

      lateDeduction: 0,
      undertimeDeduction: 0,
      unpaidLeaveDeduction: 0,
      attendanceDeductionTotal: 0,

      sssEe: 220,
      philhealthEe: 120,
      pagibigEe: 80,
      withholdingTax: 0,
      statutoryEeTotal: 420,

      cashAdvance: 0,
      oneTimeDeduction: 0,
      loans: 0,
      otherDeductionTotal: 0,

      deductionTotal: 420,

      sssEr: 450,
      philhealthEr: 240,
      pagibigEr: 160,
      employerShareTotal: 850,

      notes: "—",
    },
  ];

  // =========================================================
  // STATE
  // =========================================================
  let selectedRunId = "";
  let assignmentFilter = "All";
  let sortKey = "empName";
  let sortDir = "asc"; // asc/desc

  let page = 1;
  let perPage = rowsPerPage ? Number(rowsPerPage.value || 20) : 20;

  // =========================================================
  // INIT FILTER DEFAULTS
  // =========================================================
  if (monthInput && !monthInput.value) {
    // default current month
    const d = new Date();
    monthInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
  }
  if (cutoffSelect && !cutoffSelect.value) cutoffSelect.value = "All";
  if (areaWrap) areaWrap.style.display = "none";

  // =========================================================
  // RUN SELECTOR
  // =========================================================
  function initRunSelect() {
    if (!runSelect) return;
    runSelect.innerHTML =
      `<option value="">— Select a payroll run —</option>` +
      RUNS.map((r) => {
        const label = `${r.month} (${r.cutoff}) • ${r.assignment} • ${r.status} • ${r.employees} employees`;
        return `<option value="${escapeHtml(r.id)}">${escapeHtml(label)}</option>`;
      }).join("");
  }

  function setRunUI(run) {
    if (!run) {
      safeText("runBadge", "—");
      safeText("runEmployees", "—");
      safeText("runTotalNet", "—");
      safeText("runProcessedAt", "—");
      safeText("runProcessedBy", "—");
      setTopActionsEnabled(false);
      return;
    }

    safeText("runBadge", run.status);
    safeText("runEmployees", String(run.employees));
    safeText("runTotalNet", peso(run.totalNet));
    safeText("runProcessedAt", run.processedAt);
    safeText("runProcessedBy", run.processedBy);
    setTopActionsEnabled(true);
  }

  function setTopActionsEnabled(enabled) {
    [
      exportPdfSelectedBtn,
      exportPdfAllBtn,
      exportCsvBtn,
      printSelectedBtn,
      printAllBtn,
      openRunSummaryBtn,
      releaseAllBtn,
    ].forEach((btn) => {
      if (!btn) return;
      btn.disabled = !enabled;
    });
  }

  initRunSelect();
  setRunUI(null);

  // =========================================================
  // SORTING (DataTables-ish)
  // Expect your <th> to have data-sort="empId" etc
  // Keys supported: empId, empName, assignment, period, netPay, status
  // =========================================================
  function getSortValue(p, key) {
    if (key === "empId") return String(p.empId || "");
    if (key === "empName") return String(p.empName || "");
    if (key === "assignment") return assignmentText(p);
    if (key === "period") return `${p.periodMonth || ""} ${p.cutoff || ""}`;
    if (key === "netPay") return Number(p.netPay || 0);
    if (key === "status") return String(p.releaseStatus || "");
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
      const icon = th.querySelector(".sortIcon");
      if (!icon) return;

      if (key !== sortKey) icon.textContent = "↕";
      else icon.textContent = sortDir === "asc" ? "↑" : "↓";
    });
  }

  // =========================================================
  // FILTERS + DATA PIPE
  // =========================================================
  function assignmentText(p) {
    if (p.assignmentType === "Area") return `Area (${p.areaPlace || "—"})`;
    return p.assignmentType || "—";
  }

  function applyFilters(list) {
    const q = normalize(searchInput?.value || "");
    const monthVal = monthInput?.value || ""; // YYYY-MM
    const cutoffVal = cutoffSelect?.value || "All";

    const areaPlaceVal = areaPlaceFilter?.value || "All";

    return list
      .filter((p) => !selectedRunId || p.runId === selectedRunId)
      .filter((p) => !monthVal || p.periodMonth === monthVal)
      .filter((p) => cutoffVal === "All" || p.cutoff === cutoffVal)
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
  // TABLE RENDER
  // =========================================================
  function badge(status) {
    const s = String(status || "Draft");
    // You can style these via CSS if you want (badge--released etc)
    return `<span class="badge">${escapeHtml(s)}</span>`;
  }

  function selectedIds() {
    return $$("input.rowCheck:checked").map((cb) => cb.dataset.id);
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

    const filtered = applyFilters(PAYSLIPS);
    const sorted = applySorting(filtered);
    const { pageItems, total, pages } = paginate(sorted);

    if (resultsMeta) resultsMeta.textContent = `Showing ${pageItems.length} of ${total} payslip(s)`;

    if (pageLabel) pageLabel.textContent = `Page ${page} of ${pages}`;
    if (totalPages) totalPages.textContent = String(pages);
    if (pageInput) pageInput.value = String(page);
    if (pagePrev) pagePrev.disabled = page <= 1;
    if (pageNext) pageNext.disabled = page >= pages;
    if (firstPage) firstPage.disabled = page <= 1;
    if (lastPage) lastPage.disabled = page >= pages;

    // preserve selection
    const selected = new Set(selectedIds());

    tbody.innerHTML = "";
    pageItems.forEach((p) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${escapeHtml(p.id)}" ${selected.has(p.id) ? "checked" : ""} aria-label="Select ${escapeHtml(p.id)}">
        </td>
        <td>${escapeHtml(p.empId)}</td>
        <td>${escapeHtml(p.empName)}</td>
        <td>${escapeHtml(assignmentText(p))}</td>
        <td>${escapeHtml(`${p.periodMonth} (${p.cutoff})`)}</td>
        <td class="num"><strong>${escapeHtml(peso(p.netPay))}</strong></td>
        <td>${badge(p.releaseStatus)}</td>
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

    // checkbox events
    $$("input.rowCheck").forEach((cb) => {
      cb.addEventListener("change", () => {
        updateBulkBar();
        syncCheckAll();
      });
    });

    updateBulkBar();
    syncCheckAll();
  }

  // =========================================================
  // PREVIEW DRAWER (LOW-FI DETAILED PAYSLIP)
  // =========================================================
  function openPayslipDrawer(p) {
    if (!psDrawer || !psOverlay) return;

    // meta line on drawer head
    if (psDrawerMeta) {
      psDrawerMeta.textContent =
        `Employee: ${p.empName} (${p.empId})  •  Period: ${p.periodMonth} (${p.cutoff})  •  Status: ${p.releaseStatus}`;
    }

    // Fill payslip fields
    set("psNo", p.payslipNo || `PS-${p.id}`);
    set("psGenerated", p.generatedDate || todayISO());

    set("psEmpName", p.empName);
    set("psEmpId", p.empId);
    set("psDept", p.department || "—");
    set("psPos", p.position || "—");
    set("psType", p.empType || "—");
    set("psAssign", assignmentText(p));

    set("psMonth", p.periodMonth || "—");
    set("psCutoff", p.cutoff || "—");
    set("psPayDate", p.payDate || "—");
    set("psPayMethod", p.payMethod || "—");
    set("psAccount", p.accountMasked || "—");

    const statusBadge = $("psStatusBadge");
    if (statusBadge) statusBadge.textContent = p.releaseStatus || "Draft";

    // Earnings
    setMoney("psDailyRate", p.dailyRate);
    set("psPresentDays", String(p.presentDays ?? 0));
    set("psLeaveDays", String(p.leaveDays ?? 0));
    set("psAbsentDays", String(p.absentDays ?? 0));
    setMoney("psAttendancePay", p.attendancePay);

    set("psOtHours", Number(p.otHours || 0).toFixed(2));
    setMoney("psOtRate", p.otRate);
    setMoney("psOtPay", p.otPay);

    setMoney("psRice", p.riceAllowance);
    setMoney("psTransport", p.transportAllowance);
    setMoney("psAllowTotal", p.allowanceTotal);

    setMoney("psGross", p.gross);

    // Deductions
    setMoney("psAttDedTotal", p.attendanceDeductionTotal);
    setMoney("psLateDed", p.lateDeduction);
    setMoney("psUnderDed", p.undertimeDeduction);
    setMoney("psUnpaidLeaveDed", p.unpaidLeaveDeduction);

    setMoney("psStatEeTotal", p.statutoryEeTotal);
    setMoney("psSssEe", p.sssEe);
    setMoney("psPhEe", p.philhealthEe);
    setMoney("psPiEe", p.pagibigEe);
    setMoney("psTax", p.withholdingTax);

    setMoney("psOtherDedTotal", p.otherDeductionTotal);
    setMoney("psCashAdv", p.cashAdvance);
    setMoney("psOneTimeDed", p.oneTimeDeduction);
    setMoney("psLoans", p.loans);

    setMoney("psDedTotal", p.deductionTotal);

    // Employer Share
    setMoney("psErTotal", p.employerShareTotal);
    setMoney("psSssEr", p.sssEr);
    setMoney("psPhEr", p.philhealthEr);
    setMoney("psPiEr", p.pagibigEr);

    // Summary
    setMoney("psSumGross", p.gross);
    setMoney("psSumDed", p.deductionTotal);
    setMoney("psNet", p.netPay);

    const notes = $("psNotes");
    if (notes) notes.textContent = `Adjust Notes: ${p.notes || "—"}`;

    // collapse breakdowns on open (screen)
    $$(".psBreakdown").forEach((b) => b.setAttribute("hidden", ""));

    psDrawer.classList.add("is-open");
    psDrawer.setAttribute("aria-hidden", "false");
    psOverlay.hidden = false;
    document.body.style.overflow = "hidden";

    // hook buttons for this current payslip
    if (psPrintBtn) {
      psPrintBtn.onclick = () => {
        // show breakdowns when printing
        $$(".psBreakdown").forEach((b) => b.removeAttribute("hidden"));
        window.print();
      };
    }

    if (psDownloadBtn) {
      psDownloadBtn.onclick = () => {
        alert("PDF download: connect to backend PDF generation later.");
      };
    }

    if (psReleaseBtn) {
      psReleaseBtn.disabled = p.releaseStatus === "Released";
      psReleaseBtn.onclick = () => {
        p.releaseStatus = "Released";
        render();
        // refresh status in drawer
        const sb = $("psStatusBadge");
        if (sb) sb.textContent = "Released";
        if (psDrawerMeta) {
          psDrawerMeta.textContent =
            `Employee: ${p.empName} (${p.empId})  •  Period: ${p.periodMonth} (${p.cutoff})  •  Status: Released`;
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

  function set(id, value) {
    const el = $(id);
    if (el) el.textContent = value ?? "—";
  }
  function setMoney(id, value) {
    set(id, peso(value));
  }

  psCloseBtn && psCloseBtn.addEventListener("click", closePayslipDrawer);
  psCloseFooterBtn && psCloseFooterBtn.addEventListener("click", closePayslipDrawer);
  psOverlay && psOverlay.addEventListener("click", closePayslipDrawer);

  // Breakdown toggles inside preview (▾ breakdown)
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-toggle]");
    if (!btn) return;
    const key = btn.dataset.toggle;
    const box = document.querySelector(`.psBreakdown[data-breakdown="${key}"]`);
    if (!box) return;
    const hidden = box.hasAttribute("hidden");
    if (hidden) box.removeAttribute("hidden");
    else box.setAttribute("hidden", "");
  });

  // =========================================================
  // TABLE ACTIONS + ROW CLICK
  // =========================================================
  function findPayslip(id) {
    return PAYSLIPS.find((p) => p.id === id) || null;
  }

  // View / PDF / Print buttons (delegated)
  tbody && tbody.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const id = btn.dataset.id || "";
    const p = findPayslip(id);
    if (!p) return;

    const action = btn.dataset.action;
    if (action === "view") {
      openPayslipDrawer(p);
      return;
    }
    if (action === "print") {
      openPayslipDrawer(p);
      // print after opening (small delay so layout is ready)
      setTimeout(() => {
        $$(".psBreakdown").forEach((b) => b.removeAttribute("hidden"));
        window.print();
      }, 50);
      return;
    }
    if (action === "pdf") {
      alert("Row PDF download: connect to backend later.");
      return;
    }
  });

  // =========================================================
  // SEGMENT FILTER (Assignment)
  // =========================================================
  segBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      segBtns.forEach((b) => b.classList.remove("is-active"));
      btn.classList.add("is-active");

      assignmentFilter = btn.dataset.assign || "All";

      // show area place dropdown only if Area
      if (areaWrap) areaWrap.style.display = assignmentFilter === "Area" ? "" : "none";

      page = 1;
      render();
    });
  });

  // =========================================================
  // FILTER EVENTS
  // =========================================================
  monthInput && monthInput.addEventListener("change", () => { page = 1; render(); });
  cutoffSelect && cutoffSelect.addEventListener("change", () => { page = 1; render(); });
  searchInput && searchInput.addEventListener("input", () => { page = 1; render(); });

  areaPlaceFilter && areaPlaceFilter.addEventListener("change", () => { page = 1; render(); });

  // =========================================================
  // RUN SELECT CHANGE
  // =========================================================
  runSelect && runSelect.addEventListener("change", () => {
    selectedRunId = runSelect.value || "";
    const run = RUNS.find((r) => r.id === selectedRunId) || null;
    setRunUI(run);

    // reset selection
    if (checkAll) checkAll.checked = false;
    page = 1;
    render();
  });

  // =========================================================
  // CHECKBOX + BULK
  // =========================================================
  checkAll && checkAll.addEventListener("change", () => {
    $$("input.rowCheck").forEach((cb) => (cb.checked = checkAll.checked));
    updateBulkBar();
    syncCheckAll();
  });

  bulkClearBtn && bulkClearBtn.addEventListener("click", () => {
    $$("input.rowCheck").forEach((cb) => (cb.checked = false));
    if (checkAll) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
    }
    updateBulkBar();
  });

  bulkMarkReleasedBtn && bulkMarkReleasedBtn.addEventListener("click", () => {
    const ids = selectedIds();
    if (!ids.length) return;
    ids.forEach((id) => {
      const p = findPayslip(id);
      if (p) p.releaseStatus = "Released";
    });
    render();
  });

  bulkPrintBtn && bulkPrintBtn.addEventListener("click", () => {
    const ids = selectedIds();
    if (!ids.length) return;
    const p = findPayslip(ids[0]);
    if (!p) return;
    openPayslipDrawer(p);
    setTimeout(() => {
      $$(".psBreakdown").forEach((b) => b.removeAttribute("hidden"));
      window.print();
    }, 50);
  });

  bulkDownloadBtn && bulkDownloadBtn.addEventListener("click", () => {
    const ids = selectedIds();
    if (!ids.length) return;
    alert("Bulk PDF download: connect to backend later.");
  });

  // =========================================================
  // PAGINATION
  // =========================================================
  rowsPerPage && rowsPerPage.addEventListener("change", () => {
    perPage = Number(rowsPerPage.value || 20);
    page = 1;
    render();
  });

  pagePrev && pagePrev.addEventListener("click", () => {
    page = Math.max(1, page - 1);
    render();
  });

  pageNext && pageNext.addEventListener("click", () => {
    page = page + 1;
    render();
  });

  // =========================================================
  // TOP ACTIONS
  // =========================================================
  function exportCsv(list, filename) {
    const headers = ["Emp ID", "Employee", "Assignment", "Pay Period", "Net Pay", "Status"];
    const rows = list.map((p) => [
      p.empId,
      p.empName,
      assignmentText(p),
      `${p.periodMonth} (${p.cutoff})`,
      Number(p.netPay || 0).toFixed(2),
      p.releaseStatus,
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

  exportCsvBtn && exportCsvBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const list = applySorting(applyFilters(PAYSLIPS)); // current filtered list
    exportCsv(list, `payslips_${selectedRunId}.csv`);
  });

  exportPdfAllBtn && exportPdfAllBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    alert("Export ALL PDFs: connect backend later.");
  });

  exportPdfSelectedBtn && exportPdfSelectedBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const ids = selectedIds();
    if (!ids.length) return alert("Select at least 1 payslip.");
    alert("Export SELECTED PDFs: connect backend later.");
  });

  printAllBtn && printAllBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const list = applySorting(applyFilters(PAYSLIPS));
    if (!list.length) return;

    // Print first payslip (true batch print needs server-side PDF merge or print loop)
    openPayslipDrawer(list[0]);
    setTimeout(() => {
      $$(".psBreakdown").forEach((b) => b.removeAttribute("hidden"));
      window.print();
    }, 50);
  });

  printSelectedBtn && printSelectedBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const ids = selectedIds();
    if (!ids.length) return alert("Select at least 1 payslip.");
    const p = findPayslip(ids[0]);
    if (!p) return;
    openPayslipDrawer(p);
    setTimeout(() => {
      $$(".psBreakdown").forEach((b) => b.removeAttribute("hidden"));
      window.print();
    }, 50);
  });

  releaseAllBtn && releaseAllBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const list = PAYSLIPS.filter((p) => p.runId === selectedRunId);
    list.forEach((p) => (p.releaseStatus = "Released"));
    render();
    alert("All payslips in this run marked as Released (demo).");
  });

  openRunSummaryBtn && openRunSummaryBtn.addEventListener("click", () => {
    if (!selectedRunId) return;
    const run = RUNS.find((r) => r.id === selectedRunId);
    if (!run) return;
    alert(
      `Run Summary

${run.month} (${run.cutoff})
Assignment: ${run.assignment}
Status: ${run.status}
Employees: ${run.employees}
Total Net: ${peso(run.totalNet)}
Processed: ${run.processedAt}
By: ${run.processedBy}`
    );
  });

  // =========================================================
  // INIT SORT ICONS + FIRST RENDER
  // =========================================================
  bindHeaderSorting();
  render();
});
