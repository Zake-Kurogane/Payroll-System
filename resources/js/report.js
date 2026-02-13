document.addEventListener("DOMContentLoaded", () => {
  // =========================================================
  // CLOCK + USER MENU (same pattern as your pages)
  // =========================================================
  const clockEl = document.getElementById("clock");
  const dateEl = document.getElementById("date");
  const pad2 = (n) => String(n).padStart(2, "0");

  function tick() {
    const d = new Date();
    let h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12;
    h = h ? h : 12;
    if (clockEl) clockEl.textContent = `${pad2(h)}:${pad2(m)} ${ampm}`;
    if (dateEl) dateEl.textContent = `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
  }
  tick();
  setInterval(tick, 1000);

  const userMenuBtn = document.getElementById("userMenuBtn");
  const userMenu = document.getElementById("userMenu");
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
      if (!userMenu.contains(e.target)) closeUserMenu();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeUserMenu();
    });
  }

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

  // =========================================================
  // DEMO RUNS + DEMO REGISTER ROWS (replace with backend later)
  // Each register row belongs to a run.
  // =========================================================
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
      payslipsGeneratedAt: "2026-02-01 09:10",
      releasedAt: "—",
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
      payslipsGeneratedAt: "2026-02-15 18:10",
      releasedAt: "2026-02-15 18:20",
    },
  ];

  const REGISTER = [
    // run 1
    {
      runId: "RUN-2026-01-16E-ALL",
      empId: "1023",
      empName: "Dela Cruz, Juan",
      department: "Sales",
      empType: "Regular",
      assignmentType: "Tagum",
      areaPlace: "",
      presentDays: 12,
      absentDays: 1,
      leaveDays: 0,
      dailyRate: 610,
      attendancePay: 7320,
      otHours: 4.5,
      otPay: 427.5,
      // deductions breakdown
      attendanceDeduction: 0,
      otherDeductions: 0,
      sssEe: 300,
      philhealthEe: 150,
      pagibigEe: 100,
      tax: 0,
      sssEr: 650,
      philhealthEr: 300,
      pagibigEr: 200,
      gross: 8547.5,
      deductionsEe: 550,
      employerShare: 1150,
      netPay: 7997.5,
      payslipStatus: "Generated", // Not generated / Generated / Finalized
    },
    {
      runId: "RUN-2026-01-16E-ALL",
      empId: "1044",
      empName: "Santos, Maria",
      department: "Finance",
      empType: "Regular",
      assignmentType: "Area",
      areaPlace: "Laak",
      presentDays: 12,
      absentDays: 0,
      leaveDays: 0,
      dailyRate: 700,
      attendancePay: 8400,
      otHours: 2,
      otPay: 210,
      attendanceDeduction: 0,
      otherDeductions: 0,
      sssEe: 320,
      philhealthEe: 160,
      pagibigEe: 100,
      tax: 0,
      sssEr: 680,
      philhealthEr: 320,
      pagibigEr: 200,
      gross: 8910,
      deductionsEe: 580,
      employerShare: 1200,
      netPay: 8330,
      payslipStatus: "Generated",
    },

    // run 2
    {
      runId: "RUN-2026-02-115-TAGUM",
      empId: "1102",
      empName: "Garcia, Leo",
      department: "Operations",
      empType: "Contractual",
      assignmentType: "Tagum",
      areaPlace: "",
      presentDays: 10,
      absentDays: 0,
      leaveDays: 0,
      dailyRate: 500,
      attendancePay: 5000,
      otHours: 1,
      otPay: 80,
      attendanceDeduction: 0,
      otherDeductions: 0,
      sssEe: 220,
      philhealthEe: 120,
      pagibigEe: 80,
      tax: 0,
      sssEr: 450,
      philhealthEr: 240,
      pagibigEr: 160,
      gross: 5280,
      deductionsEe: 420,
      employerShare: 850,
      netPay: 4860,
      payslipStatus: "Finalized",
    },
    {
      runId: "RUN-2026-02-115-TAGUM",
      empId: "1103",
      empName: "Perez, Ana",
      department: "Operations",
      empType: "Contractual",
      assignmentType: "Tagum",
      areaPlace: "",
      presentDays: 0,
      absentDays: 0,
      leaveDays: 0,
      dailyRate: 520,
      attendancePay: 0,
      otHours: 0,
      otPay: 0,
      attendanceDeduction: 0,
      otherDeductions: 0,
      sssEe: 0,
      philhealthEe: 0,
      pagibigEe: 0,
      tax: 0,
      sssEr: 0,
      philhealthEr: 0,
      pagibigEr: 0,
      gross: 0,
      deductionsEe: 0,
      employerShare: 0,
      netPay: 0,
      payslipStatus: "Not generated",
    },
  ];

  // =========================================================
  // ELEMENTS
  // =========================================================
  const monthInput = $("monthInput");
  const cutoffSelect = $("cutoffSelect");
  const searchInput = $("searchInput");
  const runSelect = $("runSelect");
  const deptSelect = $("deptSelect");
  const statusSelect = $("statusSelect");

  const segBtns = $$(".seg__btn");
  const areaPlaceWrap = $("areaPlaceWrap");
  const areaPlaceSelect = $("areaPlaceSelect");

  const viewRunBtn = $("viewRunBtn");
  const exportCsvBtn = $("exportCsvBtn");
  const downloadPdfBtn = $("downloadPdfBtn");
  const printBtn = $("printBtn");

  const reportTitle = $("reportTitle");
  const runMeta = $("runMeta");
  const runBadge = $("runBadge");
  const runEmployees = $("runEmployees");
  const runTotalNet = $("runTotalNet");
  const runProcessedAt = $("runProcessedAt");
  const runProcessedBy = $("runProcessedBy");

  const kpiEmployees = $("kpiEmployees");
  const kpiGross = $("kpiGross");
  const kpiDed = $("kpiDed");
  const kpiNet = $("kpiNet");
  const kpiER = $("kpiER");

  const tabBtns = $$(".tabBtn");
  const regTbody = $("regTbody");
  const bdTbody = $("bdTbody");
  const remitTbody = $("remitTbody");
  const auditTbody = $("auditTbody");
  const issuesTbody = $("issuesTbody");

  const resultsMeta = $("resultsMeta");

  // =========================================================
  // STATE
  // =========================================================
  let selectedRunId = "";
  let assignmentFilter = "All";
  let sortKey = "empName";
  let sortDir = "asc"; // asc/desc
  let activeTab = "register";

  // =========================================================
  // DEFAULTS
  // =========================================================
  if (monthInput && !monthInput.value) {
    const d = new Date();
    monthInput.value = `${d.getFullYear()}-${pad2(d.getMonth() + 1)}`;
  }
  if (cutoffSelect && !cutoffSelect.value) cutoffSelect.value = "All";
  if (areaPlaceWrap) areaPlaceWrap.style.display = "none";

  // =========================================================
  // RUN SELECT INIT
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
  initRunSelect();

  function setTopActionsEnabled(enabled) {
    [viewRunBtn, exportCsvBtn, downloadPdfBtn, printBtn].forEach((b) => {
      if (!b) return;
      b.disabled = !enabled;
    });
  }
  setTopActionsEnabled(false);

  // =========================================================
  // PIPELINE: FILTERS
  // =========================================================
  function assignmentText(r) {
    if (r.assignmentType === "Area") return `Area (${r.areaPlace || "—"})`;
    return r.assignmentType || "—";
  }

  function runMatchesFilters(run) {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "All";
    const statusVal = statusSelect?.value || "All";

    const okMonth = !monthVal || run.month === monthVal;
    const okCutoff = cutoffVal === "All" || run.cutoff === cutoffVal;
    const okStatus = statusVal === "All" || run.status === statusVal;
    return okMonth && okCutoff && okStatus;
  }

  function applyFilters(list) {
    const q = normalize(searchInput?.value || "");
    const deptVal = deptSelect?.value || "All";
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "All";
    const areaPlaceVal = areaPlaceSelect?.value || "All";

    return list
      .filter((r) => (!selectedRunId ? true : r.runId === selectedRunId))
      .filter((r) => (!monthVal ? true : getRun(r.runId)?.month === monthVal))
      .filter((r) => (cutoffVal === "All" ? true : getRun(r.runId)?.cutoff === cutoffVal))
      .filter((r) => (deptVal === "All" ? true : (r.department || "") === deptVal))
      .filter((r) => (assignmentFilter === "All" ? true : r.assignmentType === assignmentFilter))
      .filter((r) => {
        if (assignmentFilter !== "Area") return true;
        if (areaPlaceVal === "All") return true;
        return (r.areaPlace || "") === areaPlaceVal;
      })
      .filter((r) => {
        if (!q) return true;
        const text = normalize(`${r.empId} ${r.empName} ${r.department || ""} ${r.empType || ""} ${assignmentText(r)}`);
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
    if (key === "otHours") return Number(r.otHours || 0);
    if (key === "otPay") return Number(r.otPay || 0);
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
      if (runMeta) runMeta.textContent = "—";
      if (runBadge) runBadge.textContent = "—";
      if (runEmployees) runEmployees.textContent = "—";
      if (runTotalNet) runTotalNet.textContent = "—";
      if (runProcessedAt) runProcessedAt.textContent = "—";
      if (runProcessedBy) runProcessedBy.textContent = "—";
      if (reportTitle) reportTitle.textContent = "Select a run to generate a report.";
      setTopActionsEnabled(false);
      return;
    }

    if (runMeta) runMeta.textContent = `${run.month} (${run.cutoff}) • Assignment: ${run.assignment}`;
    if (runBadge) runBadge.textContent = run.status;
    if (runEmployees) runEmployees.textContent = String(run.employees);
    if (runTotalNet) runTotalNet.textContent = peso(run.totalNet);
    if (runProcessedAt) runProcessedAt.textContent = run.processedAt;
    if (runProcessedBy) runProcessedBy.textContent = run.processedBy;

    if (reportTitle) {
      reportTitle.textContent = `Payroll Reports — ${run.month} (${run.cutoff}) — ${run.assignment}`;
    }
    setTopActionsEnabled(true);
  }

  // =========================================================
  // KPI + TAB RENDERS
  // =========================================================
  function renderKPIs(rows) {
    const employeesPaid = rows.filter((r) => Number(r.netPay || 0) > 0).length;
    const gross = rows.reduce((a, r) => a + Number(r.gross || 0), 0);
    const ded = rows.reduce((a, r) => a + Number(r.deductionsEe || 0), 0);
    const net = rows.reduce((a, r) => a + Number(r.netPay || 0), 0);
    const er = rows.reduce((a, r) => a + Number(r.employerShare || 0), 0);

    if (kpiEmployees) kpiEmployees.textContent = String(employeesPaid);
    if (kpiGross) kpiGross.textContent = peso(gross);
    if (kpiDed) kpiDed.textContent = peso(ded);
    if (kpiNet) kpiNet.textContent = peso(net);
    if (kpiER) kpiER.textContent = peso(er);
  }

  function renderRegister(rows) {
    if (!regTbody) return;
    regTbody.innerHTML = "";
    if (resultsMeta) resultsMeta.textContent = `Showing ${rows.length} employee(s)`;

    rows.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.empId)}</td>
        <td>${escapeHtml(r.empName)}</td>
        <td>${escapeHtml(assignmentText(r))}</td>
        <td>${escapeHtml(r.department || "—")}</td>
        <td>${escapeHtml(`${r.presentDays}/${r.absentDays}/${r.leaveDays}`)}</td>
        <td class="num">${escapeHtml(peso(r.dailyRate))}</td>
        <td class="num">${escapeHtml(peso(r.attendancePay))}</td>
        <td class="num">${escapeHtml(Number(r.otHours || 0).toFixed(2))}</td>
        <td class="num">${escapeHtml(peso(r.otPay))}</td>
        <td class="num">${escapeHtml(peso(r.deductionsEe))}</td>
        <td class="num">${escapeHtml(peso(r.employerShare))}</td>
        <td class="num">${escapeHtml(peso(r.gross))}</td>
        <td class="num"><strong>${escapeHtml(peso(r.netPay))}</strong></td>
        <td>${escapeHtml(r.payslipStatus || "—")}</td>
        <td class="col-actions">
          <div class="iconrow">
            <button class="iconbtn" type="button" data-action="viewPayslip" data-emp="${escapeHtml(r.empId)}" title="View Payslip">👁</button>
            <button class="iconbtn" type="button" data-action="printPayslip" data-emp="${escapeHtml(r.empId)}" title="Print Payslip">🖨</button>
            <button class="iconbtn" type="button" data-action="downloadPayslip" data-emp="${escapeHtml(r.empId)}" title="Download Payslip">⬇</button>
          </div>
        </td>
      `;
      regTbody.appendChild(tr);
    });

    if (!rows.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="15" class="muted small">No rows found.</td>`;
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
    rows.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.empId)}</td>
        <td>${escapeHtml(r.empName)}</td>
        <td class="num">${escapeHtml(peso(r.sssEe))}</td>
        <td class="num">${escapeHtml(peso(r.sssEr))}</td>
        <td class="num">${escapeHtml(peso(r.philhealthEe))}</td>
        <td class="num">${escapeHtml(peso(r.philhealthEr))}</td>
        <td class="num">${escapeHtml(peso(r.pagibigEe))}</td>
        <td class="num">${escapeHtml(peso(r.pagibigEr))}</td>
        <td class="num">${escapeHtml(peso(r.tax))}</td>
      `;
      remitTbody.appendChild(tr);
    });

    if (!rows.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="9" class="muted small">No rows found.</td>`;
      remitTbody.appendChild(tr);
    }
  }

  function renderAudit() {
    if (!auditTbody) return;
    const list = RUNS.filter(runMatchesFilters);

    auditTbody.innerHTML = "";
    list.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(`${r.month} (${r.cutoff})`)}</td>
        <td>${escapeHtml(r.status)}</td>
        <td class="num">${escapeHtml(String(r.employees))}</td>
        <td class="num"><strong>${escapeHtml(peso(r.totalNet))}</strong></td>
        <td>${escapeHtml(r.processedAt)}</td>
        <td>${escapeHtml(r.processedBy)}</td>
        <td>${escapeHtml(r.payslipsGeneratedAt || "—")}</td>
        <td>${escapeHtml(r.releasedAt || "—")}</td>
      `;
      auditTbody.appendChild(tr);
    });

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="9" class="muted small">No runs found.</td>`;
      auditTbody.appendChild(tr);
    }
  }

  function renderIssues(rows) {
    if (!issuesTbody) return;

    const issues = [];
    rows.forEach((r) => {
      if (Number(r.netPay || 0) < 0) {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Negative net pay", severity: "High" });
      }
      if ((r.presentDays || 0) === 0 && (r.absentDays || 0) === 0 && (r.leaveDays || 0) === 0) {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Missing attendance", severity: "High" });
      }
      if (getRun(r.runId)?.cutoff === "16–End" && (Number(r.sssEe || 0) + Number(r.philhealthEe || 0) + Number(r.pagibigEe || 0)) === 0) {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Missing gov contributions (2nd cutoff)", severity: "Medium" });
      }
      if (r.payslipStatus === "Not generated") {
        issues.push({ empId: r.empId, empName: r.empName, issue: "Payslip not generated", severity: "Low" });
      }
    });

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
    renderAudit();
    renderIssues(sorted);
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

    ["register", "breakdown", "remit", "audit", "issues"].forEach((k) => {
      const pane = $(`tab-${k}`);
      if (!pane) return;
      pane.hidden = k !== tab;
    });
  }

  tabBtns.forEach((b) => {
    b.addEventListener("click", () => setActiveTab(b.dataset.tab || "register"));
  });

  // =========================================================
  // FILTER EVENTS
  // =========================================================
  [monthInput, cutoffSelect, deptSelect, statusSelect].forEach((el) => {
    el && el.addEventListener("change", () => {
      // If status/month/cutoff changed, run selector should still work;
      // we just re-render with new filters.
      renderAll();
    });
  });

  searchInput && searchInput.addEventListener("input", renderAll);

  // Assignment segmented
  segBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      segBtns.forEach((b) => b.classList.remove("is-active"));
      btn.classList.add("is-active");

      assignmentFilter = btn.dataset.assign || "All";
      segBtns.forEach((b) => b.setAttribute("aria-selected", b === btn ? "true" : "false"));

      if (areaPlaceWrap) areaPlaceWrap.style.display = assignmentFilter === "Area" ? "" : "none";
      renderAll();
    });
  });

  areaPlaceSelect && areaPlaceSelect.addEventListener("change", renderAll);

  // Run selector
  runSelect && runSelect.addEventListener("change", () => {
    selectedRunId = runSelect.value || "";
    const run = selectedRunId ? getRun(selectedRunId) : null;
    setRunUI(run);
    renderAll();
  });

  // =========================================================
  // TOP ACTIONS
  // =========================================================
  function exportCsv(rows, filename) {
    // Export current tab context (basic)
    if (activeTab === "audit") {
      const list = RUNS.filter(runMatchesFilters);
      const headers = ["Run ID", "Period", "Status", "Employees", "Total Net", "Processed At", "Processed By", "Payslips Generated", "Released At"];
      const csv = [
        headers.join(","),
        ...list.map((r) =>
          [
            r.id,
            `${r.month} (${r.cutoff})`,
            r.status,
            r.employees,
            Number(r.totalNet || 0).toFixed(2),
            r.processedAt,
            r.processedBy,
            r.payslipsGeneratedAt || "",
            r.releasedAt || "",
          ]
            .map((v) => `"${String(v).replaceAll('"', '""')}"`)
            .join(",")
        ),
      ].join("\n");

      downloadCsv(csv, filename);
      return;
    }

    // Default: register export
    const headers = [
      "Emp ID",
      "Employee",
      "Assignment",
      "Department",
      "Present",
      "Absent",
      "Leave",
      "Daily Rate",
      "Attendance Pay",
      "OT Hours",
      "OT Pay",
      "Deductions (EE)",
      "Employer Share (ER)",
      "Gross",
      "Net",
      "Payslip Status",
    ];

    const csv = [
      headers.join(","),
      ...rows.map((r) =>
        [
          r.empId,
          r.empName,
          assignmentText(r),
          r.department || "",
          r.presentDays || 0,
          r.absentDays || 0,
          r.leaveDays || 0,
          Number(r.dailyRate || 0).toFixed(2),
          Number(r.attendancePay || 0).toFixed(2),
          Number(r.otHours || 0).toFixed(2),
          Number(r.otPay || 0).toFixed(2),
          Number(r.deductionsEe || 0).toFixed(2),
          Number(r.employerShare || 0).toFixed(2),
          Number(r.gross || 0).toFixed(2),
          Number(r.netPay || 0).toFixed(2),
          r.payslipStatus || "",
        ]
          .map((v) => `"${String(v).replaceAll('"', '""')}"`)
          .join(",")
      ),
    ].join("\n");

    downloadCsv(csv, filename);
  }

  function downloadCsv(csv, filename) {
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
      const rows = applySorting(applyFilters(REGISTER));
      exportCsv(rows, `report_${selectedRunId}_${activeTab}.csv`);
    });

  printBtn &&
    printBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      window.print();
    });

  downloadPdfBtn &&
    downloadPdfBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      alert("Download PDF: connect backend PDF generator later.");
    });

  viewRunBtn &&
    viewRunBtn.addEventListener("click", () => {
      if (!selectedRunId) return;
      alert("View Payroll Run: link this button to Payroll Processing run page later.");
    });

  // =========================================================
  // Table row actions (demo)
  // =========================================================
  regTbody &&
    regTbody.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const emp = btn.dataset.emp;
      if (!emp) return;

      if (action === "viewPayslip") alert(`View payslip for ${emp} (demo)`);
      if (action === "printPayslip") alert(`Print payslip for ${emp} (demo)`);
      if (action === "downloadPayslip") alert(`Download payslip for ${emp} (demo)`);
    });

  // =========================================================
  // First render (no run selected)
  // =========================================================
  setRunUI(null);
  setActiveTab("register");
  renderAll();
});
