document.addEventListener("DOMContentLoaded", () => {
  // =========================================================
  // CLOCK
  // =========================================================
  const clockEl = document.getElementById("clock");
  const dateEl = document.getElementById("date");
  const pad = (n) => String(n).padStart(2, "0");

  function tick() {
    const d = new Date();
    let h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12; h = h ? h : 12;
    if (clockEl) clockEl.textContent = `${pad(h)}:${pad(m)} ${ampm}`;
    if (dateEl) dateEl.textContent = `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
  }
  tick();
  setInterval(tick, 1000);

  // =========================================================
  // USER DROPDOWN
  // =========================================================
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
    userMenuBtn.addEventListener("click", (e) => { e.stopPropagation(); toggleUserMenu(); });
    document.addEventListener("click", (e) => { if (!userMenu.contains(e.target)) closeUserMenu(); });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeUserMenu(); });
  }

  // =========================================================
  // DEMO DATA
  // =========================================================
  const employees = [
    {
      empId: "1023", name: "Dela Cruz, Juan", dept: "Admin", type: "Regular",
      assignType: "Tagum", areaPlace: "",
      monthlyRate: 20000, allowances: 0,
      gov: { sss: 500, ph: 300, pagibig: 200 },
      hasGovIds: true,
      cashAdvanceEligible: true, // regular
    },
    {
      empId: "1044", name: "Santos, Maria", dept: "HR", type: "Regular",
      assignType: "Area", areaPlace: "Laak",
      monthlyRate: 24000, allowances: 1500,
      gov: { sss: 600, ph: 350, pagibig: 200 },
      hasGovIds: true,
      cashAdvanceEligible: true,
    },
    {
      empId: "1102", name: "Garcia, Leo", dept: "IT", type: "Contractual",
      assignType: "Davao", areaPlace: "",
      monthlyRate: 19000, allowances: 0,
      gov: { sss: 0, ph: 0, pagibig: 0 },
      hasGovIds: false,
      cashAdvanceEligible: false,
    },
    {
      empId: "1201", name: "Reyes, Ana", dept: "Operations", type: "Regular",
      assignType: "Area", areaPlace: "Pantukan",
      monthlyRate: 22000, allowances: 800,
      gov: { sss: 550, ph: 320, pagibig: 200 },
      hasGovIds: true,
      cashAdvanceEligible: true,
    },
  ];

  // Daily attendance records (demo)
  // status: Present | Absent | Leave
  // assignType/areaPlace included so validation can check consistency.
  const attendance = [
    { empId: "1023", date: "2026-01-02", status: "Present", assignType: "Tagum", areaPlace: "" },
    { empId: "1023", date: "2026-01-03", status: "Absent", assignType: "Tagum", areaPlace: "" },

    { empId: "1044", date: "2026-01-02", status: "Present", assignType: "Area", areaPlace: "Laak" },
    { empId: "1044", date: "2026-01-03", status: "Leave", assignType: "Area", areaPlace: "Laak" },

    { empId: "1102", date: "2026-01-03", status: "Absent", assignType: "Davao", areaPlace: "" },

    // Ana has no attendance for early cutoff -> shows missing
    { empId: "1201", date: "2026-01-18", status: "Present", assignType: "Area", areaPlace: "Pantukan" },
  ];

  // In-memory "payroll runs processed" demo list
  let processedRuns = [
    {
      id: "R1",
      period: "2026-01 (1–15)",
      filters: "All / All / All",
      count: 2,
      totalNet: 18250,
      status: "Processed",
    },
  ];

  // Per-run computed preview state
  // keyed by empId for current filters
  let previewRows = [];
  let processedLock = false; // becomes true after Process Payroll

  // =========================================================
  // ELEMENTS
  // =========================================================
  const monthInput = document.getElementById("monthInput");
  const cutoffSelect = document.getElementById("cutoffSelect");
  const segBtns = Array.from(document.querySelectorAll(".seg__btn"));
  let assignmentFilter = "All";
  const assignSelect = document.getElementById("assignSelect");
  const areaPlaceWrap = document.getElementById("areaPlaceWrap");
  const areaPlaceSelect = document.getElementById("areaPlaceSelect");
  const deptSelect = document.getElementById("deptSelect");
  const typeSelect = document.getElementById("typeSelect");
  const searchInput = document.getElementById("searchInput");

  const chipsRow = document.getElementById("chipsRow");
  const viewMissingBtn = document.getElementById("viewMissingBtn");
  const goAttendanceBtn = document.getElementById("goAttendanceBtn");
  const missingBox = document.getElementById("missingBox");
  const missingList = document.getElementById("missingList");

  const otRateInput = document.getElementById("otRateInput");
  const workDaysSelect = document.getElementById("workDaysSelect");
  const includeAllowances = document.getElementById("includeAllowances");
  const govHint = document.getElementById("govHint");
  const totGovRow = document.getElementById("totGovRow");

  const totSelCount = document.getElementById("totSelCount");
  const totAllCount = document.getElementById("totAllCount");
  const totBasic = document.getElementById("totBasic");
  const totOT = document.getElementById("totOT");
  const totDed = document.getElementById("totDed");
  const totGov = document.getElementById("totGov");
  const totNet = document.getElementById("totNet");

  const resultsMeta = document.getElementById("resultsMeta");
  const payTbody = document.getElementById("payTbody");
  const checkAll = document.getElementById("checkAll");

  const resetPreviewBtn = document.getElementById("resetPreviewBtn");
  const computeBtn = document.getElementById("computeBtn");
  const processBtn = document.getElementById("processBtn");
  const payslipBtn = document.getElementById("payslipBtn");
  const stickyHint = document.getElementById("stickyHint");

  const runsTbody = document.getElementById("runsTbody");

  // Adjust drawer
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const applyAdjBtn = document.getElementById("applyAdjBtn");

  const summaryOverlay = document.getElementById("summaryOverlay");
  const summaryModal = document.getElementById("summaryModal");
  const closeSummaryBtn = document.getElementById("closeSummaryBtn");
  const closeSummaryFooter = document.getElementById("closeSummaryFooter");
  const sumEmpCount = document.getElementById("sumEmpCount");
  const sumReadyCount = document.getElementById("sumReadyCount");
  const sumIssueCount = document.getElementById("sumIssueCount");
  const sumBasicPay = document.getElementById("sumBasicPay");
  const sumOtPay = document.getElementById("sumOtPay");
  const sumDeductions = document.getElementById("sumDeductions");
  const sumNetPay = document.getElementById("sumNetPay");

  const adjEmpName = document.getElementById("adjEmpName");
  const adjEmpId = document.getElementById("adjEmpId");
  const adjAssign = document.getElementById("adjAssign");
  const adjOtHours = document.getElementById("adjOtHours");
  const adjOneDed = document.getElementById("adjOneDed");
  const adjCashAdv = document.getElementById("adjCashAdv");
  const adjCashHint = document.getElementById("adjCashHint");
  const adjNotes = document.getElementById("adjNotes");
  const adjEmpKey = document.getElementById("adjEmpKey");

  // =========================================================
  // HELPERS
  // =========================================================
  const money = (n) => {
    const num = Number(n || 0);
    return `₱ ${num.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
  };

  const withinMonth = (yyyy_mm_dd, monthVal) => {
    if (!monthVal) return true;
    return String(yyyy_mm_dd || "").startsWith(monthVal);
  };

  const cutoffRange = (monthVal, cutoffVal) => {
    // monthVal "2026-01"
    if (!monthVal) return { start: "", end: "" };
    const [y, m] = monthVal.split("-");
    const start = cutoffVal === "1-15" ? `${y}-${m}-01` : `${y}-${m}-16`;
    const end = cutoffVal === "1-15" ? `${y}-${m}-15` : `${y}-${m}-31`; // demo end
    return { start, end };
  };

  const inRange = (date, start, end) => {
    if (!date || !start || !end) return false;
    return date >= start && date <= end;
  };

  const assignmentLabel = (e) => {
    if (e.assignType === "Area") return `Area (${e.areaPlace || "—"})`;
    return e.assignType || "—";
  };

  const isSecondCutoff = () => (cutoffSelect?.value === "16-end");

  function toggleGovColumns() {
    const show = isSecondCutoff();
    const govCols = document.querySelectorAll(".govCol");
    govCols.forEach(th => th.style.display = show ? "" : "none");
    if (totGovRow) totGovRow.style.display = show ? "" : "none";
    if (govHint) govHint.textContent = show ? "Applied on this cutoff (16–End)." : "Only applied on cutoff 16–End.";
  }

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

  function openSummary() {
    if (!summaryModal || !summaryOverlay) return;
    summaryModal.classList.add("is-open");
    summaryModal.setAttribute("aria-hidden", "false");
    summaryOverlay.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function closeSummary() {
    if (!summaryModal || !summaryOverlay) return;
    summaryModal.classList.remove("is-open");
    summaryModal.setAttribute("aria-hidden", "true");
    summaryOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  function showComputeSummary() {
    const rows = previewRows || [];
    const sum = (arr, fn) => arr.reduce((a, r) => a + Number(fn(r) || 0), 0);

    const readyCount = rows.filter(r => r.status === "Ready").length;
    const issueCount = rows.length - readyCount;
    const totalBasic = sum(rows, r => r.halfBasic);
    const totalOtPay = sum(rows, r => r.otPay);
    const totalNet = sum(rows, r => r.net);
    const totalDed = (totalBasic + totalOtPay) - totalNet;

    if (sumEmpCount) sumEmpCount.textContent = String(rows.length);
    if (sumReadyCount) sumReadyCount.textContent = String(readyCount);
    if (sumIssueCount) sumIssueCount.textContent = String(issueCount);
    if (sumBasicPay) sumBasicPay.textContent = money(totalBasic);
    if (sumOtPay) sumOtPay.textContent = money(totalOtPay);
    if (sumDeductions) sumDeductions.textContent = money(totalDed);
    if (sumNetPay) sumNetPay.textContent = money(totalNet);

    openSummary();
  }

  function selectedIds() {
    return Array.from(document.querySelectorAll(".rowCheck:checked")).map(cb => cb.dataset.id);
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
  // FILTERED EMPLOYEES
  // =========================================================
  function filteredEmployees() {
    const monthVal = monthInput?.value || "";
    const q = (searchInput?.value || "").trim().toLowerCase();
    const dept = deptSelect?.value || "All";
    const type = typeSelect?.value || "All";
    const assign = assignmentFilter || assignSelect?.value || "All";
    const areaPlace = areaPlaceSelect?.value || "All";

    // month is used for attendance checks, but employees still show even if no attendance
    return employees.filter(e => {
      const okDept = dept === "All" || e.dept === dept;
      const okType = type === "All" || e.type === type;

      const okAssign =
        assign === "All" ||
        (assign === "Area"
          ? (e.assignType === "Area" && (areaPlace === "All" || e.areaPlace === areaPlace))
          : e.assignType === assign);

      const text = `${e.empId} ${e.name} ${e.dept} ${e.assignType} ${e.areaPlace || ""}`.toLowerCase();
      const okQ = !q || text.includes(q);

      // monthVal not used to filter employees, only payroll period
      return okDept && okType && okAssign && okQ;
    });
  }

  // =========================================================
  // ATTENDANCE SUMMARY (for the cutoff window)
  // =========================================================
  function attendanceSummary(empId) {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "1-15";
    const { start, end } = cutoffRange(monthVal, cutoffVal);

    const rows = attendance.filter(a =>
      a.empId === empId &&
      withinMonth(a.date, monthVal) &&
      inRange(a.date, start, end)
    );

    const counts = { Present: 0, Absent: 0, Leave: 0 };
    rows.forEach(r => { counts[r.status] = (counts[r.status] || 0) + 1; });

    return { rows, counts, hasAny: rows.length > 0 };
  }

  // =========================================================
  // VALIDATION PANEL (chips + missing list)
  // =========================================================
  function buildValidation() {
    const list = filteredEmployees();
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "1-15";
    const second = isSecondCutoff();

    const missingAttendance = [];
    const missingBasePay = [];
    const missingGovInfo = [];

    list.forEach(e => {
      const att = attendanceSummary(e.empId);
      if (!att.hasAny) missingAttendance.push(e);

      if (!e.monthlyRate || Number(e.monthlyRate) <= 0) missingBasePay.push(e);

      if (second && !e.hasGovIds && e.type === "Regular") {
        // contractual can be ignored in demo; adjust as needed
        missingGovInfo.push(e);
      }
    });

    const attendanceOk = missingAttendance.length === 0;
    const baseOk = missingBasePay.length === 0;
    const govOk = !second || (missingGovInfo.length === 0);

    const chips = [
      attendanceOk
        ? { type: "ok", text: "Attendance imported for this cutoff" }
        : { type: "warn", text: `Missing attendance for ${missingAttendance.length} employee(s)` },

      baseOk
        ? { type: "ok", text: "Basic pay configured" }
        : { type: "warn", text: `Missing basic pay for ${missingBasePay.length} employee(s)` },
    ];

    if (second) {
      chips.push(
        govOk
          ? { type: "ok", text: "Gov contributions ready (2nd cutoff)" }
          : { type: "warn", text: `Missing gov info for ${missingGovInfo.length} employee(s)` }
      );
    } else {
      chips.push({ type: "muted", text: "Gov contributions are 0 (1st cutoff)" });
    }

    if (chipsRow) {
      chipsRow.innerHTML = chips.map(c => {
        const cls = c.type === "ok" ? "chipOk" : (c.type === "warn" ? "chipWarn" : "chipMuted");
        const icon = c.type === "ok" ? "✅" : (c.type === "warn" ? "⚠️" : "ℹ️");
        return `<div class="vchip ${cls}">${icon} <span>${c.text}</span></div>`;
      }).join("");
    }

    // missing list content (used in "View Missing Data")
    const items = [];
    if (!attendanceOk) items.push(...missingAttendance.map(e => `⚠ ${e.empId} — ${e.name}: Missing attendance in cutoff`));
    if (!baseOk) items.push(...missingBasePay.map(e => `⚠ ${e.empId} — ${e.name}: Missing basic pay`));
    if (second && !govOk) items.push(...missingGovInfo.map(e => `⚠ ${e.empId} — ${e.name}: Missing gov IDs/info`));

    return {
      attendanceOk, baseOk, govOk,
      missingItems: items,
      blockingErrors: items.length > 0, // used for processing confirmation
      missingAttendanceCount: missingAttendance.length,
    };
  }

  function renderMissingBox(items) {
    if (!missingBox || !missingList) return;
    if (!items.length) {
      missingBox.hidden = false;
      missingList.innerHTML = `<div class="muted small">No missing data.</div>`;
      return;
    }
    missingBox.hidden = false;
    missingList.innerHTML = items.map(t => `<div class="missingItem">${t}</div>`).join("");
  }

  // =========================================================
  // COMPUTE PREVIEW (core calculations)
  // =========================================================
  function computeForEmployee(e) {
    const settings = {
      otRate: Number(otRateInput?.value || 0),
      workDays: Number(workDaysSelect?.value || 26),
      includeAllow: !!includeAllowances?.checked,
      secondCutoff: isSecondCutoff(),
    };

    const halfBasic = Number(e.monthlyRate || 0) / 2;
    const dailyRate = settings.workDays > 0 ? (Number(e.monthlyRate || 0) / settings.workDays) : 0;

    const att = attendanceSummary(e.empId);
    const present = att.counts.Present || 0;
    const absent = att.counts.Absent || 0;
    const leave = att.counts.Leave || 0;

    // default editable fields
    const existing = previewRows.find(r => r.empId === e.empId);
    const otHours = existing ? Number(existing.otHours || 0) : 0;
    const otherDed = existing ? Number(existing.otherDed || 0) : 0;
    const cashAdvDed = existing ? Number(existing.cashAdvDed || 0) : 0;
    const notes = existing ? (existing.notes || "") : "";

    // Pay effects
    const allowance = settings.includeAllow ? Number(e.allowances || 0) / 2 : 0;
    const lateDeduction = Number(e.lateDeduction || 20); // placeholder per-period late fee
    const undertimeDeduction = Number(e.undertimeDeduction || 400); // placeholder per-period undertime fee
    const absentDeduction = lateDeduction + undertimeDeduction; // shown in dropdown
    const otPay = otHours * settings.otRate;

    // gov contributions only 2nd cutoff
    const sss = settings.secondCutoff ? Number(e.gov?.sss || 0) : 0;
    const ph = settings.secondCutoff ? Number(e.gov?.ph || 0) : 0;
    const pagibig = settings.secondCutoff ? Number(e.gov?.pagibig || 0) : 0;
    const tax = 0; // tax omitted per request
    const erShare = settings.secondCutoff ? Number(e.gov?.er || 0) : 0;

    const totalDed = absentDeduction + otherDed + cashAdvDed + sss + ph + pagibig + tax;
    const payCutoff = halfBasic + allowance - absentDeduction; // base pay after attendance
    const net = payCutoff + otPay - (otherDed + cashAdvDed + sss + ph + pagibig + tax);

    // status label
    let status = "Ready";
    if (!att.hasAny) status = "Missing Attendance";
    if (!e.monthlyRate || Number(e.monthlyRate) <= 0) status = "Missing Basic Pay";
    if (settings.secondCutoff && e.type === "Regular" && !e.hasGovIds) status = "Missing Gov Info";

    return {
      empId: e.empId,
      name: e.name,
      dept: e.dept,
      assign: assignmentLabel(e),

      halfBasic,
      dailyRate,
      allowance,
      absentDeduction,
      payCutoff,
      present, absent, leave,

      otHours,
      otPay,

      otherDed,
      cashAdvDed,
      notes,

      sss, ph, pagibig, tax, erShare,
      net,

      status,
      isBlocking: status !== "Ready",
      type: e.type,
      cashAdvanceEligible: !!e.cashAdvanceEligible,
      maxCashAdvance: Number(e.monthlyRate || 0) * 2,
    };
  }

  function computePreview() {
    const list = filteredEmployees();
    previewRows = list.map(e => computeForEmployee(e));
    processedLock = false; // if you change filters/settings, it becomes "draft" again
    if (payslipBtn) payslipBtn.disabled = true;
    if (stickyHint) stickyHint.textContent = "Preview computed. Review rows, then Process Payroll.";
  }

  // =========================================================
  // RENDER PREVIEW TABLE
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

  function renderTable() {
    if (!payTbody) return;

    const totalPages = Math.max(1, Math.ceil(previewRows.length / payPageSize));
    payPage = Math.min(payPage, totalPages);
    const start = (payPage - 1) * payPageSize;
    let list = previewRows.slice();
    if (sortState.key === "name") {
      const mul = sortState.dir === "asc" ? 1 : -1;
      list.sort((a, b) => normalize(a.name).localeCompare(normalize(b.name)) * mul);
    }
    list = list.slice(start, start + payPageSize);

    if (resultsMeta) resultsMeta.textContent = `Showing ${previewRows.length} employee(s)`;
    if (payFooterInfo) payFooterInfo.textContent = `Page ${payPage} of ${totalPages}`;
    if (payPageInput) payPageInput.value = payPage;
    if (payPageTotal) payPageTotal.textContent = `/ ${totalPages}`;
    if (payPrev) payPrev.disabled = payPage <= 1;
    if (payNext) payNext.disabled = payPage >= totalPages;

    const selected = new Set(selectedIds());
    payTbody.innerHTML = "";

    list.forEach(r => {
      const tr = document.createElement("tr");
      const disabled = processedLock ? "disabled" : "";

      const attText = `${r.present}/${r.absent}/${r.leave}`;
      const statusChip = r.status === "Ready"
        ? `<span class="st st--ok">Ready</span>`
        : `<span class="st st--warn">${r.status}</span>`;

      const statEE = Number(r.sss || 0) + Number(r.ph || 0) + Number(r.pagibig || 0); // tax removed
      const statER = Number(r.erShare || 0);

      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${r.empId}" ${selected.has(r.empId) ? "checked" : ""} aria-label="Select ${r.empId}" ${processedLock ? "disabled" : ""}>
        </td>
        <td>${r.empId}</td>
        <td class="nameCell">
          <div class="nm">${r.name}</div>
          <div class="muted small">${r.dept} • ${r.assign}</div>
        </td>
        <td>${attText}</td>
        <td class="num">${money(r.dailyRate)}</td>

        <td class="num">
          <input class="miniIn otIn" type="number" min="0" step="0.25" value="${Number(r.otHours || 0)}" data-id="${r.empId}" ${disabled}>
        </td>
        <td class="num">${money(r.otPay)}</td>

        <td class="num">
          <details class="dd">
            <summary>Total: ${money(r.absentDeduction)}</summary>
            <div class="dd__body">
              <div>Total: ${money(r.absentDeduction)}</div>
              <div>Late: ${money(r.lateDeduction || 0)}</div>
              <div>Undertime: ${money(r.undertimeDeduction || 0)}</div>
            </div>
          </details>
        </td>

        <td class="num">
          <input class="miniIn dedIn" type="number" min="0" step="0.01" value="${Number(r.otherDed || 0)}" data-id="${r.empId}" ${disabled}>
        </td>

        <td class="num">
          <details class="dd">
            <summary>${money(statEE)}</summary>
            <div class="dd__body">
              <div>SSS: ${money(r.sss)}</div>
              <div>PhilHealth: ${money(r.ph)}</div>
              <div>Pag-IBIG: ${money(r.pagibig)}</div>
            </div>
          </details>
        </td>

        <td class="num">
          <details class="dd">
            <summary>${money(statER)}</summary>
            <div class="dd__body">
              <div>${money(statER)}</div>
            </div>
          </details>
        </td>

        <td class="num"><strong>${money(r.net)}</strong></td>
        <td class="col-actions">
          <button class="iconbtn adjBtn" type="button" data-id="${r.empId}" ${disabled} title="Adjust">⚙</button>
        </td>
      `;

      payTbody.appendChild(tr);
    });

    // wire checkbox changes
    payTbody.querySelectorAll(".rowCheck").forEach(cb => {
      cb.addEventListener("change", () => {
        syncCheckAll();
        renderTotals(); // update selected totals
      });
    });

    // wire OT & Ded inputs
    payTbody.querySelectorAll(".otIn").forEach(inp => {
      inp.addEventListener("input", () => {
        const id = inp.dataset.id;
        const val = Number(inp.value || 0);
        previewRows = previewRows.map(r => r.empId === id ? { ...r, otHours: val } : r);
        // recompute derived values for that row
        previewRows = previewRows.map(r => r.empId === id ? computeForEmployee(employees.find(e => e.empId === id)) : r);
        renderTable();
        renderTotals();
      });
    });

    payTbody.querySelectorAll(".dedIn").forEach(inp => {
      inp.addEventListener("input", () => {
        const id = inp.dataset.id;
        const val = Number(inp.value || 0);
        previewRows = previewRows.map(r => r.empId === id ? { ...r, otherDed: val } : r);
        previewRows = previewRows.map(r => r.empId === id ? computeForEmployee(employees.find(e => e.empId === id)) : r);
        renderTable();
        renderTotals();
      });
    });

    // wire adjust buttons
    payTbody.querySelectorAll(".adjBtn").forEach(btn => {
      btn.addEventListener("click", () => openAdjust(btn.dataset.id));
    });

    // check all state
    syncCheckAll();
  }

  // pagination controls (bind once)
  payPrev && payPrev.addEventListener("click", () => {
    if (payPage > 1) {
      payPage--;
      renderTable();
    }
  });
  payNext && payNext.addEventListener("click", () => {
    payPage++;
    renderTable();
  });
  payFirst && payFirst.addEventListener("click", () => {
    payPage = 1;
    renderTable();
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
  // TOTALS (Selected vs All)
  // =========================================================
  function renderTotals() {
    const ids = new Set(selectedIds());
    const all = previewRows;

    const selected = all.filter(r => ids.has(r.empId));
    const scope = selected.length ? selected : []; // may be empty

    const sum = (arr, key) => arr.reduce((a, r) => a + Number(r[key] || 0), 0);

    const allCount = all.length;
    const selCount = selected.length;

    if (totAllCount) totAllCount.textContent = allCount;
    if (totSelCount) totSelCount.textContent = selCount;

    // Use selected totals if any selected, else show "all totals" (admin-friendly)
    const baseArr = selCount ? selected : all;

    if (totBasic) totBasic.textContent = money(sum(baseArr, "halfBasic"));
    if (totOT) totOT.textContent = money(sum(baseArr, "otPay"));

    // Deductions: (otherDed + cashAdvDed + absent ded + gov) is embedded in net calc;
    // Here we approximate: (halfBasic + allowance + otPay) - net
    const totalGross = baseArr.reduce((a, r) => {
      const includeAllow = !!includeAllowances?.checked;
      const allowHalf = includeAllow ? (Number(employees.find(e => e.empId === r.empId)?.allowances || 0) / 2) : 0;
      return a + Number(r.halfBasic || 0) + allowHalf + Number(r.otPay || 0);
    }, 0);

    const totalNet = sum(baseArr, "net");
    const totalDed = totalGross - totalNet;

    if (totDed) totDed.textContent = money(totalDed);
    if (totNet) totNet.textContent = money(totalNet);

    if (totGov) {
      const govTotal = baseArr.reduce((a, r) => a + Number(r.sss || 0) + Number(r.ph || 0) + Number(r.pagibig || 0), 0);
      totGov.textContent = money(govTotal);
    }
  }

  // =========================================================
  // ADJUST DRAWER
  // =========================================================
  function openAdjust(empId) {
    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    if (adjEmpName) adjEmpName.textContent = row.name;
    if (adjEmpId) adjEmpId.textContent = row.empId;
    if (adjAssign) adjAssign.textContent = row.assign;

    if (adjOtHours) adjOtHours.value = Number(row.otHours || 0);
    if (adjOneDed) adjOneDed.value = Number(row.otherDed || 0);
    if (adjCashAdv) adjCashAdv.value = Number(row.cashAdvDed || 0);
    if (adjNotes) adjNotes.value = row.notes || "";
    if (adjEmpKey) adjEmpKey.value = row.empId;

    // cash advance eligibility messaging
    if (adjCashHint) {
      if (!row.cashAdvanceEligible) {
        adjCashHint.textContent = "Not eligible for cash advance deduction (not Regular).";
      } else {
        adjCashHint.textContent = `Eligible. Max Cash Advance: ${money(row.maxCashAdvance)} (read-only in employee record).`;
      }
    }
    if (adjCashAdv) adjCashAdv.disabled = !row.cashAdvanceEligible;

    openDrawer();
  }

  function applyAdjust() {
    const empId = adjEmpKey?.value || "";
    if (!empId) return;

    const otH = Number(adjOtHours?.value || 0);
    const oneDed = Number(adjOneDed?.value || 0);
    const cash = Number(adjCashAdv?.value || 0);
    const notes = (adjNotes?.value || "").trim();

    previewRows = previewRows.map(r => r.empId === empId
      ? { ...r, otHours: otH, otherDed: oneDed, cashAdvDed: cash, notes }
      : r
    );

    // recompute derived values from base employee data with overrides
    const emp = employees.find(e => e.empId === empId);
    previewRows = previewRows.map(r => r.empId === empId ? computeForEmployee(emp) : r);

    closeDrawer();
    renderTable();
    renderTotals();
  }

  // =========================================================
  // PROCESS PAYROLL (LOCK)
  // =========================================================
  function processPayroll() {
    const ids = selectedIds();
    const target = ids.length ? previewRows.filter(r => ids.includes(r.empId)) : previewRows;

    if (!target.length) {
      alert("No employees to process.");
      return;
    }

    // warnings checks
    const val = buildValidation();
    const negatives = target.filter(r => Number(r.net || 0) < 0);

    let confirmMsg = `Process payroll for ${target.length} employee(s)?\n\nThis will lock the results for this run.`;
    if (val.blockingErrors) confirmMsg += `\n\n⚠ Missing data exists. You may want to fix first.`;
    if (negatives.length) confirmMsg += `\n\n⚠ ${negatives.length} employee(s) have negative net pay.`;

    if (!confirm(confirmMsg)) return;

    // lock
    processedLock = true;
    if (stickyHint) stickyHint.textContent = "Processed and locked. You can now generate payslips.";
    if (payslipBtn) payslipBtn.disabled = false;

    // add to processed runs list
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value === "1-15" ? "1–15" : "16–End";
    const assign = assignmentFilter || assignSelect?.value || "All";
    const dept = deptSelect?.value || "All";
    const area = (assign === "Area" ? (areaPlaceSelect?.value || "All") : "—");

    const totalNet = target.reduce((a, r) => a + Number(r.net || 0), 0);

    processedRuns.unshift({
      id: `R${Math.random().toString(16).slice(2, 7).toUpperCase()}`,
      period: `${monthVal} (${cutoffVal})`,
      filters: `${assign}${assign === "Area" ? `(${area})` : ""} / ${dept} / ${typeSelect?.value || "All"}`,
      count: target.length,
      totalNet,
      status: "Processed",
    });

    renderRuns();
    renderTable(); // disables inputs/buttons
  }

  // =========================================================
  // RUNS TABLE
  // =========================================================
  function renderRuns() {
    if (!runsTbody) return;
    runsTbody.innerHTML = "";

    if (!processedRuns.length) {
      runsTbody.innerHTML = `<tr><td colspan="5" class="muted small">No processed runs yet.</td></tr>`;
      return;
    }

    processedRuns.forEach(r => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><strong>${r.period}</strong></td>
        <td class="muted small">${r.filters}</td>
        <td class="num">${r.count}</td>
        <td class="num">${money(r.totalNet)}</td>
        <td><span class="st st--ok">${r.status}</span></td>
      `;
      runsTbody.appendChild(tr);
    });
  }

  // =========================================================
  // UI EVENTS
  // =========================================================
  function refreshAll() {
    // area dropdown visibility
    const assign = assignmentFilter || assignSelect?.value || "All";
    if (areaPlaceWrap) areaPlaceWrap.hidden = assign !== "Area";

    // chips
    const val = buildValidation();
    if (missingBox && !missingBox.hidden) renderMissingBox(val.missingItems);

    // compute + render
    computePreview();
    renderTable();
    renderTotals();
    renderRuns();

    // process button state
    if (processBtn) processBtn.disabled = previewRows.length === 0;
  }

  // Filter changes => recompute preview immediately (admin-friendly)
  [monthInput, cutoffSelect].forEach(el => {
    el && el.addEventListener("change", () => refreshAll());
  });
  searchInput && searchInput.addEventListener("input", () => refreshAll());

  segBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      segBtns.forEach(b => b.classList.remove("is-active"));
      btn.classList.add("is-active");
      segBtns.forEach(b => b.setAttribute("aria-selected", b === btn ? "true" : "false"));
      assignmentFilter = btn.dataset.assign || "All";
      refreshAll();
    });
  });

  // settings changes => recompute
  [otRateInput, workDaysSelect, includeAllowances].forEach(el => {
    el && el.addEventListener("change", () => refreshAll());
    el && el.addEventListener("input", () => refreshAll());
  });

  // select all
  checkAll && checkAll.addEventListener("change", () => {
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    checks.forEach(cb => { cb.checked = checkAll.checked; });
    syncCheckAll();
    renderTotals();
  });

  // buttons
  computeBtn && computeBtn.addEventListener("click", () => {
    const val = buildValidation();
    computePreview();
    renderTable();
    renderTotals();
    if (stickyHint) stickyHint.textContent = val.blockingErrors
      ? "Preview computed, but missing data exists (see Data Readiness)."
      : "Preview computed. Review rows, then Process Payroll.";
    showComputeSummary();
  });

  resetPreviewBtn && resetPreviewBtn.addEventListener("click", () => {
    if (!confirm("Reset preview edits (OT hours, deductions) for the current view?")) return;
    previewRows = [];
    processedLock = false;
    if (payslipBtn) payslipBtn.disabled = true;
    refreshAll();
  });

  processBtn && processBtn.addEventListener("click", processPayroll);

  payslipBtn && payslipBtn.addEventListener("click", () => {
    if (!processedLock) return;
    alert("Generate Payslips (demo). Next step: create payslip view/pdf per employee.");
  });

  goAttendanceBtn && goAttendanceBtn.addEventListener("click", () => {
    // If you have a named route 'attendance', use that.
    window.location.href = "/attendance";
  });

  viewMissingBtn && viewMissingBtn.addEventListener("click", () => {
    const val = buildValidation();
    renderMissingBox(val.missingItems);
  });

  // drawer events
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);
  applyAdjBtn && applyAdjBtn.addEventListener("click", applyAdjust);
  closeSummaryBtn && closeSummaryBtn.addEventListener("click", closeSummary);
  closeSummaryFooter && closeSummaryFooter.addEventListener("click", closeSummary);
  summaryOverlay && summaryOverlay.addEventListener("click", closeSummary);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeUserMenu();
      if (drawer && drawer.classList.contains("is-open")) closeDrawer();
      if (summaryModal && summaryModal.classList.contains("is-open")) closeSummary();
    }
  });

  // =========================================================
  // INIT
  // =========================================================
  const now = new Date();
  if (monthInput) monthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  toggleGovColumns();
  renderRuns();
  refreshAll();
});
