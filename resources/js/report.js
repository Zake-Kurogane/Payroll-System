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
  const runSelect = $("runSelect");
  const deptSelect = $("deptSelect");
  const statusSelect = $("statusSelect");

  const assignmentSeg = $("assignSeg");
  let segBtns = [];

  let areaPlaces = {};
  let areaSubFilter = "";

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
  const externalGrossTbody = $("externalGrossTbody");
  const externalPayslipsTbody = $("externalPayslipsTbody");
  const companyPayslipsTbody = $("companyPayslipsTbody");
  const overallTbody = $("overallTbody");
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
  function closeAllDropdowns() {
    if (!assignmentSeg) return;
    assignmentSeg.querySelectorAll(".seg__dropdown").forEach(dd => {
      dd.style.display = "none";
    });
  }

  // =========================================================
  // RUN SELECT INIT
  // =========================================================
  function initRunSelect() {
    if (!runSelect) return;
    runSelect.innerHTML =
      `<option value="">— Select a payroll run —</option>` +
      RUNS.filter(runMatchesFilters).map((r) => {
        const label = `${r.month} (${r.cutoffLabel}) • ${r.displayLabel} • ${r.status} • ${r.employees} employees`;
        return `<option value="${escapeHtml(r.id)}">${escapeHtml(label)}</option>`;
      }).join("");
  }

  function mapRun(run) {
    const assignmentText = run.assignment_filter === "Field" && run.area_place_filter
      ? `Field (${run.area_place_filter})`
      : (run.assignment_filter || "—");

    return {
      id: String(run.id),
      month: run.period_month,
      cutoff: run.cutoff,
      cutoffLabel: run.cutoff || "—",
      assignment: assignmentText,
      status: run.status || "—",
      employees: Number(run.headcount || 0),
      totalNet: Number(run.net || 0),
      processedAt: run.locked_at || run.created_at || "—",
      processedBy: run.created_by_name || "—",
      payslipsGeneratedAt: run.payslips_generated_at || "—",
      releasedAt: run.released_at || "—",
      runType: run.run_type || "External",
      displayLabel: run.display_label || `${run.run_type || "External"} · ${assignmentText}`,
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
      otHours: Number(row.ot_hours || 0),
      otPay: Number(row.ot_pay || 0),
      attendanceDeduction: Number(row.attendance_deduction || 0),
      chargesDeduction: charges,
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
      payslipStatus: row.payslip_status || "—",
    };
  }

  async function loadRuns() {
    try {
      const data = await apiFetch("/payroll-runs");
      RUNS = Array.isArray(data) ? data.map(mapRun) : [];
    } catch {
      RUNS = [];
    }
    initRunSelect();
    renderAudit();
  }

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
    if (r.areaPlace) return `${r.assignmentType || "—"} (${r.areaPlace})`;
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
    return list
      .filter((r) => (!selectedRunId ? true : r.runId === selectedRunId))
      .filter((r) => (!monthVal ? true : getRun(r.runId)?.month === monthVal))
      .filter((r) => (cutoffVal === "All" ? true : getRun(r.runId)?.cutoff === cutoffVal))
      .filter((r) => (deptVal === "All" ? true : (r.department || "") === deptVal))
      .filter((r) => (assignmentFilter === "All" ? true : r.assignmentType === assignmentFilter))
      .filter((r) => {
        if (!areaSubFilter) return true;
        return (r.areaPlace || "") === areaSubFilter;
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
        <td>${escapeHtml(`${r.month} (${r.cutoffLabel})`)}</td>
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
      const key = String(keyFn(item) ?? "—");
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
    return assign || "—";
  }

  function renderExternalGross(rows) {
    if (!externalGrossTbody) return;

    const list = rows
      .filter((r) => (r.externalArea || "").trim())
      .slice()
      .sort((a, b) => {
        const ea = String(a.externalArea || "").localeCompare(String(b.externalArea || ""));
        if (ea !== 0) return ea;
        return String(a.empName || "").localeCompare(String(b.empName || ""));
      });

    externalGrossTbody.innerHTML = "";

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="2" class="muted small">No rows found.</td>`;
      externalGrossTbody.appendChild(tr);
      return;
    }

    const groups = groupBy(list, (r) => (r.externalArea || "—").trim() || "—");
    Array.from(groups.keys()).sort().forEach((external) => {
      const items = groups.get(external) || [];
      appendGroupHeader(externalGrossTbody, external, 2);

      let totalGross = 0;
      items.forEach((r) => {
        totalGross += Number(r.gross || 0);
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${escapeHtml(r.empName)}</td>
          <td class="num">${escapeHtml(peso(r.gross))}</td>
        `;
        externalGrossTbody.appendChild(tr);
      });

      const tr = document.createElement("tr");
      tr.className = "row-total";
      tr.innerHTML = `
        <td><strong>Total Gross</strong></td>
        <td class="num"><strong>${escapeHtml(peso(totalGross))}</strong></td>
      `;
      externalGrossTbody.appendChild(tr);
    });
  }

  function renderExternalPayslips(rows) {
    if (!externalPayslipsTbody) return;

    const list = rows
      .filter((r) => (r.externalArea || "").trim())
      .slice()
      .sort((a, b) => {
        const ea = String(a.externalArea || "").localeCompare(String(b.externalArea || ""));
        if (ea !== 0) return ea;
        return String(a.empName || "").localeCompare(String(b.empName || ""));
      });

    externalPayslipsTbody.innerHTML = "";

    if (!list.length) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="6" class="muted small">No rows found.</td>`;
      externalPayslipsTbody.appendChild(tr);
      return;
    }

    const groups = groupBy(list, (r) => (r.externalArea || "—").trim() || "—");
    Array.from(groups.keys()).sort().forEach((external) => {
      const items = groups.get(external) || [];
      appendGroupHeader(externalPayslipsTbody, external, 6);

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
        externalPayslipsTbody.appendChild(tr);
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
      externalPayslipsTbody.appendChild(tr);
    });
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
    const shortages = rows.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0);

    overallTbody.innerHTML = "";

    const metrics = [
      ["Total Gross", gross],
      ["Total Deductions (EE)", deductions],
      ["Total Net Pay", net],
      ["Statutory Deductions (EE)", statutory],
      ["Withholding Tax", tax],
      ["Charges", charges],
      ["Shortages", shortages],
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
    renderExternalGross(sorted);
    renderExternalPayslips(sorted);
    renderCompanyPayslips(sorted);
    renderOverall(sorted);
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

    ["register", "breakdown", "remit", "externalGross", "externalPayslips", "companyPayslips", "overall", "audit", "issues"].forEach((k) => {
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
      initRunSelect();
      renderAll();
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
        chev.textContent = "▾";
        btn.appendChild(chev);
      }
      wrap.appendChild(btn);

      const dropdown = document.createElement("div");
      dropdown.className = "seg__dropdown";
      dropdown.dataset.group = label;
      dropdown.style.display = "none";
      dropdown.innerHTML = places.map(p =>
        `<button type="button" class="seg__dropdown-item" data-place="${escapeHtml(p)}">${escapeHtml(p)}</button>`
      ).join("");
      wrap.appendChild(dropdown);

      assignmentSeg.appendChild(wrap);
    });

    segBtns = Array.from(assignmentSeg.querySelectorAll(".seg__btn--emp"));
    bindAssignmentButtons();
  }

  function bindAssignmentButtons() {
    if (!assignmentSeg) return;
    segBtns.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const rawAssign = btn.getAttribute("data-assign");
        const group = rawAssign && rawAssign !== "" ? rawAssign : "All";
        const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");

        const isAlreadyActive = btn.classList.contains("is-active");
        closeAllDropdowns();

        segBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        assignmentFilter = group;
        areaSubFilter = "";
        renderAll();

        if (dropdown && !isAlreadyActive) {
          dropdown.style.display = "block";
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

    document.addEventListener("click", (e) => {
      if (!assignmentSeg.contains(e.target)) closeAllDropdowns();
    }, { capture: true });
  }

  // Run selector
  runSelect && runSelect.addEventListener("change", () => {
    selectedRunId = runSelect.value || "";
    const run = selectedRunId ? getRun(selectedRunId) : null;
    setRunUI(run);

    REGISTER = [];
    renderAll();

    if (!selectedRunId) return;
    setTopActionsEnabled(false);
    apiFetch(`/payroll-runs/${encodeURIComponent(selectedRunId)}/rows`)
      .then((rows) => {
        REGISTER = Array.isArray(rows) ? rows.map(mapRow) : [];
      })
      .catch(() => {
        REGISTER = [];
      })
      .finally(() => {
        setTopActionsEnabled(true);
        renderAll();
      });
  });

  // =========================================================
  // TOP ACTIONS
  // =========================================================
  function exportCsv(rows, filename) {
    const quote = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;

    if (activeTab === "audit") {
      const list = RUNS.filter(runMatchesFilters);
      const headers = ["Run ID", "Period", "Status", "Employees", "Total Net", "Processed At", "Processed By", "Payslips Generated", "Released At"];
      const csv = [
        headers.join(","),
        ...list.map((r) =>
          [
            r.id,
            `${r.month} (${r.cutoffLabel})`,
            r.status,
            r.employees,
            Number(r.totalNet || 0).toFixed(2),
            r.processedAt,
            r.processedBy,
            r.payslipsGeneratedAt || "",
            r.releasedAt || "",
          ].map(quote).join(",")
        ),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "externalGross") {
      const list = rows
        .filter((r) => (r.externalArea || "").trim())
        .slice()
        .sort((a, b) => {
          const ea = String(a.externalArea || "").localeCompare(String(b.externalArea || ""));
          if (ea !== 0) return ea;
          return String(a.empName || "").localeCompare(String(b.empName || ""));
        });

      const headers = ["External", "Name", "Gross Pay"];
      const csv = [
        headers.join(","),
        ...list.map((r) =>
          [
            r.externalArea || "",
            r.empName,
            Number(r.gross || 0).toFixed(2),
          ].map(quote).join(",")
        ),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "externalPayslips") {
      const list = rows
        .filter((r) => (r.externalArea || "").trim())
        .slice()
        .sort((a, b) => {
          const ea = String(a.externalArea || "").localeCompare(String(b.externalArea || ""));
          if (ea !== 0) return ea;
          return String(a.empName || "").localeCompare(String(b.empName || ""));
        });

      const headers = ["External", "Name", "Gross Pay", "Deductions", "Shortages", "Charges", "Net Pay"];
      const csv = [
        headers.join(","),
        ...list.map((r) =>
          [
            r.externalArea || "",
            r.empName,
            Number(r.gross || 0).toFixed(2),
            Number(r.deductionsEe || 0).toFixed(2),
            Number(r.attendanceDeduction || 0).toFixed(2),
            Number(r.chargesDeduction || 0).toFixed(2),
            Number(r.netPay || 0).toFixed(2),
          ].map(quote).join(",")
        ),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "companyPayslips") {
      const list = rows
        .slice()
        .sort((a, b) => {
          const ca = companyLabel(a).localeCompare(companyLabel(b));
          if (ca !== 0) return ca;
          return String(a.empName || "").localeCompare(String(b.empName || ""));
        });

      const headers = ["Company", "Name", "Gross Pay", "Deductions", "Shortages", "Charges", "Net Pay"];
      const csv = [
        headers.join(","),
        ...list.map((r) =>
          [
            companyLabel(r),
            r.empName,
            Number(r.gross || 0).toFixed(2),
            Number(r.deductionsEe || 0).toFixed(2),
            Number(r.attendanceDeduction || 0).toFixed(2),
            Number(r.chargesDeduction || 0).toFixed(2),
            Number(r.netPay || 0).toFixed(2),
          ].map(quote).join(",")
        ),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "overall") {
      const gross = rows.reduce((a, r) => a + Number(r.gross || 0), 0);
      const deductions = rows.reduce((a, r) => a + Number(r.deductionsEe || 0), 0);
      const net = rows.reduce((a, r) => a + Number(r.netPay || 0), 0);
      const tax = rows.reduce((a, r) => a + Number(r.tax || 0), 0);
      const statutory = rows.reduce(
        (a, r) => a + Number(r.sssEe || 0) + Number(r.philhealthEe || 0) + Number(r.pagibigEe || 0),
        0
      );
      const charges = rows.reduce((a, r) => a + Number(r.chargesDeduction || 0), 0);
      const shortages = rows.reduce((a, r) => a + Number(r.attendanceDeduction || 0), 0);

      const totals = [
        ["Total Gross", gross],
        ["Total Deductions (EE)", deductions],
        ["Total Net Pay", net],
        ["Statutory Deductions (EE)", statutory],
        ["Withholding Tax", tax],
        ["Charges", charges],
        ["Shortages", shortages],
      ];
      const csv = [
        ["Metric", "Amount"].join(","),
        ...totals.map(([k, v]) => [k, Number(v || 0).toFixed(2)].map(quote).join(",")),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "breakdown") {
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
      const csv = [
        ["Item", "Amount"].join(","),
        ...totals.map(([k, v]) => [k, Number(v || 0).toFixed(2)].map(quote).join(",")),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "remit") {
      const headers = ["Emp ID", "Employee", "SSS (EE)", "SSS (ER)", "PhilHealth (EE)", "PhilHealth (ER)", "Pag-IBIG (EE)", "Pag-IBIG (ER)", "Tax"];
      const csv = [
        headers.join(","),
        ...rows.map((r) =>
          [
            r.empId,
            r.empName,
            Number(r.sssEe || 0).toFixed(2),
            Number(r.sssEr || 0).toFixed(2),
            Number(r.philhealthEe || 0).toFixed(2),
            Number(r.philhealthEr || 0).toFixed(2),
            Number(r.pagibigEe || 0).toFixed(2),
            Number(r.pagibigEr || 0).toFixed(2),
            Number(r.tax || 0).toFixed(2),
          ].map(quote).join(",")
        ),
      ].join("\n");
      downloadCsv(csv, filename);
      return;
    }

    if (activeTab === "issues") {
      const list = computeIssues(rows);
      const headers = ["Emp ID", "Employee", "Issue", "Severity"];
      const csv = [
        headers.join(","),
        ...list.map((r) => [r.empId, r.empName, r.issue, r.severity].map(quote).join(",")),
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
        ].map(quote).join(",")
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
        buildAssignmentSeg(["Davao", "Tagum", "Field", "Multi-Site(Roving)"]);
      });
  }

  // =========================================================
  // First render (no run selected)
  // =========================================================
  setRunUI(null);
  setActiveTab("register");
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
