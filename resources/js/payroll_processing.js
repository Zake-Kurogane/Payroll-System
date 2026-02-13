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
  // DEMO DATA (replace later with backend)
  // =========================================================
  const employees = [
    {
      empId: "1023", name: "Dela Cruz, Juan", dept: "Admin", type: "Regular",
      assignType: "Tagum", areaPlace: "",
      monthlyRate: 20000, allowances: 0,
      gov: { sss: 500, ph: 300, pagibig: 200 },
      hasGovIds: true,
      cashAdvanceEligible: true,
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

  const attendance = [
    { empId: "1023", date: "2026-01-02", status: "Present", assignType: "Tagum", areaPlace: "" },
    { empId: "1023", date: "2026-01-03", status: "Absent", assignType: "Tagum", areaPlace: "" },

    { empId: "1044", date: "2026-01-02", status: "Present", assignType: "Area", areaPlace: "Laak" },
    { empId: "1044", date: "2026-01-03", status: "Leave", assignType: "Area", areaPlace: "Laak" },

    { empId: "1102", date: "2026-01-03", status: "Absent", assignType: "Davao", areaPlace: "" },

    { empId: "1201", date: "2026-01-18", status: "Present", assignType: "Area", areaPlace: "Pantukan" },
  ];

  // =========================================================
  // ELEMENTS
  // =========================================================
  const monthInput = document.getElementById("monthInput");
  const cutoffSelect = document.getElementById("cutoffSelect");
  const segBtns = Array.from(document.querySelectorAll(".seg__btn"));
  let assignmentFilter = "All";
  const searchInput = document.getElementById("searchInput");

  const resultsMeta = document.getElementById("resultsMeta");
  const payTbody = document.getElementById("payTbody");
  const checkAll = document.getElementById("checkAll");

  const resetPreviewBtn = document.getElementById("resetPreviewBtn");
  const computeBtn = document.getElementById("computeBtn");
  const processBtn = document.getElementById("processBtn");
  const payslipBtn = document.getElementById("payslipBtn");
  const stickyHint = document.getElementById("stickyHint");

  const runsTbody = document.getElementById("runsTbody");

  // drawer
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const applyAdjBtn = document.getElementById("applyAdjBtn");

  const adjEmpName = document.getElementById("adjEmpName");
  const adjEmpId = document.getElementById("adjEmpId");
  const adjAssign = document.getElementById("adjAssign");
  const adjStatus = document.getElementById("adjStatus");
  const adjEmpKey = document.getElementById("adjEmpKey");

  const adjComputedOt = document.getElementById("adjComputedOt");
  const adjOverrideToggle = document.getElementById("adjOverrideToggle");
  const adjOtHours = document.getElementById("adjOtHours");
  const adjOtAmountPreview = document.getElementById("adjOtAmountPreview");
  const adjCashAdvance = document.getElementById("adjCashAdvance");

  const sumBase = document.getElementById("sumBase");
  const sumOt = document.getElementById("sumOt");
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
  const money = (n) => {
    const num = Number(n || 0);
    return `₱ ${num.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
  };

  const fmtDT = (d) => {
    if (!d) return "—";
    const dt = (d instanceof Date) ? d : new Date(d);
    return dt.toLocaleString();
  };

  const makeRunId = () => `RUN-${Math.random().toString(16).slice(2, 8).toUpperCase()}`;

  const cutoffRange = (monthVal, cutoffVal) => {
    if (!monthVal) return { start: "", end: "" };
    const [y, m] = monthVal.split("-");
    const start = cutoffVal === "1-15" ? `${y}-${m}-01` : `${y}-${m}-16`;
    const end = cutoffVal === "1-15" ? `${y}-${m}-15` : `${y}-${m}-31`;
    return { start, end };
  };

  const inRange = (date, start, end) => {
    if (!date || !start || !end) return false;
    return date >= start && date <= end;
  };

  const withinMonth = (yyyy_mm_dd, monthVal) => {
    if (!monthVal) return true;
    return String(yyyy_mm_dd || "").startsWith(monthVal);
  };

  const assignmentLabel = (e) => {
    if (e.assignType === "Area") return `Area (${e.areaPlace || "—"})`;
    return e.assignType || "—";
  };

  // =========================================================
  // RUN STATE (Clean architecture: currentRun + snapshots)
  // =========================================================
  // status: Draft | Locked | Released
  let currentRun = null;

  // Runs history
  let processedRuns = [
    {
      id: "RUN-DEMO1",
      periodKey: "2026-01|1-15",
      periodLabel: "2026-01 (1–15)",
      assignment: "All",
      createdBy: "ADMIN",
      createdAt: new Date("2026-01-16T10:00:00"),
      lockedAt: new Date("2026-01-16T11:00:00"),
      releasedAt: null,
      status: "Locked",
      headcount: 2,
      gross: 20000,
      deductions: 1750,
      net: 18250,
      snapshotRows: [],
      unlockReason: null,
    },
  ];

  // =========================================================
  // PREVIEW + OVERRIDES
  // =========================================================
  let previewRows = [];
  // overrides per employee for this run (sticky even after recompute)
  // shape:
  // { empId: { otHours, otherDed, otOverrideOn, otOverrideHours, adjustments:[{type,name,amount}], cashAdvance } }
  let overrides = {};

  // drawer local state
  let adjustmentRows = []; // edits for drawer only (synced into overrides on Apply)

  // =========================================================
  // RUN GUARDS
  // =========================================================
  function isLocked() {
    return currentRun && (currentRun.status === "Locked" || currentRun.status === "Released");
  }

  function setInputsEnabled(enabled) {
    // filters
    if (monthInput) monthInput.disabled = !enabled;
    if (cutoffSelect) cutoffSelect.disabled = !enabled;
    if (searchInput) searchInput.disabled = !enabled;
    segBtns.forEach(b => b.disabled = !enabled);

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
    if (unlockRunBtn) unlockRunBtn.disabled = !locked; // unlock only if locked/released
    if (releaseRunBtn) releaseRunBtn.disabled = !(currentRun && currentRun.status === "Locked");
    if (payslipBtn) payslipBtn.disabled = !(currentRun && (currentRun.status === "Locked" || currentRun.status === "Released"));
  }

  function applyRunUi() {
    if (!currentRun) return;

    if (runIdEl) runIdEl.textContent = currentRun.id;
    if (runPeriodEl) runPeriodEl.textContent = currentRun.periodLabel;
    if (runStatusEl) runStatusEl.textContent = currentRun.status;
    if (runCreatedByEl) runCreatedByEl.textContent = currentRun.createdBy || "ADMIN";
    if (runCreatedAtEl) runCreatedAtEl.textContent = fmtDT(currentRun.createdAt);
    if (runLockedAtEl) runLockedAtEl.textContent = currentRun.lockedAt ? fmtDT(currentRun.lockedAt) : "—";
    if (runReleasedAtEl) runReleasedAtEl.textContent = currentRun.releasedAt ? fmtDT(currentRun.releasedAt) : "—";

    setInputsEnabled(!isLocked());
    syncRunButtons();
  }

  function computePeriodKey() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "1-15";
    return `${monthVal}|${cutoffVal}`;
  }

  function computePeriodLabel() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value === "1-15" ? "1–15" : "16–End";
    return `${monthVal} (${cutoffVal})`;
  }

  function prevCutoffKey() {
    const monthVal = monthInput?.value || "";
    const cutoffVal = cutoffSelect?.value || "1-15";

    // simple: previous cutoff within same month if possible
    // if current is 16-end => previous is 1-15 same month
    // if current is 1-15 => previous is 16-end previous month (rough)
    if (cutoffVal === "16-end") return `${monthVal}|1-15`;

    // previous month 16-end
    if (!monthVal) return "";
    const [yStr, mStr] = monthVal.split("-");
    const y = Number(yStr);
    const m = Number(mStr);
    let py = y, pm = m - 1;
    if (pm <= 0) { pm = 12; py = y - 1; }
    const prevMonth = `${py}-${String(pm).padStart(2, "0")}`;
    return `${prevMonth}|16-end`;
  }

  function findPrevRunForVariance() {
    const key = prevCutoffKey();
    if (!key) return null;
    // latest run that matches prev cutoff key (Locked or Released)
    return processedRuns.find(r => r.periodKey === key && (r.status === "Locked" || r.status === "Released")) || null;
  }

  // =========================================================
  // ATTENDANCE SUMMARY
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
  // FILTERED EMPLOYEES
  // =========================================================
  function filteredEmployees() {
    const q = (searchInput?.value || "").trim().toLowerCase();
    const assign = assignmentFilter || "All";

    return employees.filter(e => {
      const okAssign = assign === "All" ? true : e.assignType === assign;
      const text = `${e.empId} ${e.name} ${e.dept} ${e.assignType} ${e.areaPlace || ""}`.toLowerCase();
      const okQ = !q || text.includes(q);
      return okAssign && okQ;
    });
  }

  // =========================================================
  // COMPUTE (base + apply overrides)
  // =========================================================
  function computeForEmployee(e) {
    // settings (demo)
    const otRate = 120; // replace later from settings page
    const workDays = 26;

    const halfBasic = Number(e.monthlyRate || 0) / 2;
    const dailyRate = workDays > 0 ? (Number(e.monthlyRate || 0) / workDays) : 0;

    const att = attendanceSummary(e.empId);
    const present = att.counts.Present || 0;
    const absent = att.counts.Absent || 0;
    const leave = att.counts.Leave || 0;

    // base computed OT hours (demo: 0 unless set in table override)
    const ov = overrides[e.empId] || {};
    const otHours = Number(ov.otHours ?? 0);
    const otherDed = Number(ov.otherDed ?? 0);

    const otOverrideOn = !!ov.otOverrideOn;
    const otOverrideHours = Number(ov.otOverrideHours ?? otHours);

    const finalOtHours = otOverrideOn ? otOverrideHours : otHours;
    const otPay = finalOtHours * otRate;

    const adjustments = Array.isArray(ov.adjustments) ? ov.adjustments : [];
    const earnAdj = adjustments.filter(a => a.type === "earning").reduce((a, r) => a + Number(r.amount || 0), 0);
    const dedAdj = adjustments.filter(a => a.type === "deduction").reduce((a, r) => a + Number(r.amount || 0), 0);

    const cashAdvance = Number(ov.cashAdvance ?? 0);

    // placeholder attendance deductions
    const lateDeduction = 20;
    const undertimeDeduction = 400;
    const absentDeduction = lateDeduction + undertimeDeduction;

    // gov only on 2nd cutoff (demo)
    const secondCutoff = (cutoffSelect?.value === "16-end");
    const sss = secondCutoff ? Number(e.gov?.sss || 0) : 0;
    const ph = secondCutoff ? Number(e.gov?.ph || 0) : 0;
    const pagibig = secondCutoff ? Number(e.gov?.pagibig || 0) : 0;
    const erShare = 0;

    const gross = halfBasic + otPay + earnAdj;
    const deductions = absentDeduction + otherDed + dedAdj + cashAdvance + sss + ph + pagibig;
    const net = gross - deductions;

    let status = "Ready";
    if (!att.hasAny) status = "Missing Attendance";
    if (!e.monthlyRate || Number(e.monthlyRate) <= 0) status = "Missing Basic Pay";

    return {
      empId: e.empId,
      name: e.name,
      dept: e.dept,
      assign: assignmentLabel(e),

      halfBasic,
      dailyRate,

      present, absent, leave,

      otRate,
      otHours, // base editable
      otOverrideOn,
      otOverrideHours,
      finalOtHours,
      otPay,

      otherDed,
      cashAdvance,
      adjustments,

      earnAdj,
      dedAdj,

      absentDeduction,
      sss, ph, pagibig, erShare,

      gross,
      deductions,
      net,

      status,
      cashAdvanceEligible: !!e.cashAdvanceEligible,
      maxCashAdvance: Number(e.monthlyRate || 0) * 2,
    };
  }

  function computePreview() {
    const list = filteredEmployees();
    previewRows = list.map(e => computeForEmployee(e));

    if (stickyHint) stickyHint.textContent = isLocked()
      ? "Run is locked. Viewing snapshot."
      : "Preview computed. Review rows, then lock the run.";
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

    const rows = (isLocked() && Array.isArray(currentRun.snapshotRows) && currentRun.snapshotRows.length)
      ? currentRun.snapshotRows
      : previewRows;

    const s = computeSummaryFromRows(rows);

    if (sumHeadcount) sumHeadcount.textContent = String(s.headcount);
    if (sumGross) sumGross.textContent = money(s.gross);
    if (sumDed) sumDed.textContent = money(s.deductions);
    if (sumNet) sumNet.textContent = money(s.net);

    const prev = findPrevRunForVariance();
    if (!prev) {
      if (sumVariance) sumVariance.textContent = "—";
      return;
    }

    const diff = Number(s.net || 0) - Number(prev.net || 0);
    const sign = diff > 0 ? "+" : "";
    if (sumVariance) sumVariance.textContent = `${sign}${money(diff)} vs ${prev.periodLabel}`;
  }

  // =========================================================
  // SELECTION HELPERS
  // =========================================================
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

  function renderTable() {
    if (!payTbody) return;

    const locked = isLocked();
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
      const disabled = locked ? "disabled" : "";

      const attText = `${r.present}/${r.absent}/${r.leave}`;

      const statEE = Number(r.sss || 0) + Number(r.ph || 0) + Number(r.pagibig || 0);
      const statER = Number(r.erShare || 0);

      tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${r.empId}" ${selected.has(r.empId) ? "checked" : ""} aria-label="Select ${r.empId}" ${locked ? "disabled" : ""}>
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
              <div>Late: ${money(20)}</div>
              <div>Undertime: ${money(400)}</div>
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
        renderRunSummary();
      });
    });

    // wire OT inputs
    payTbody.querySelectorAll(".otIn").forEach(inp => {
      inp.addEventListener("input", () => {
        if (isLocked()) return;
        const id = inp.dataset.id;
        const val = Number(inp.value || 0);
        overrides[id] = { ...(overrides[id] || {}), otHours: val };
        computePreview();
        renderTable();
        renderRunSummary();
      });
    });

    // wire otherDed inputs
    payTbody.querySelectorAll(".dedIn").forEach(inp => {
      inp.addEventListener("input", () => {
        if (isLocked()) return;
        const id = inp.dataset.id;
        const val = Number(inp.value || 0);
        overrides[id] = { ...(overrides[id] || {}), otherDed: val };
        computePreview();
        renderTable();
        renderRunSummary();
      });
    });

    // adjust drawer buttons
    payTbody.querySelectorAll(".adjBtn").forEach(btn => {
      btn.addEventListener("click", () => openAdjust(btn.dataset.id));
    });

    syncCheckAll();
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
      div.className = "grid2";
      div.style.marginBottom = "8px";

      div.innerHTML = `
        <select data-index="${index}" class="adjType">
          <option value="earning" ${row.type === "earning" ? "selected" : ""}>Earning</option>
          <option value="deduction" ${row.type === "deduction" ? "selected" : ""}>Deduction</option>
        </select>

        <input type="text" placeholder="Name"
          value="${row.name || ""}"
          data-index="${index}" class="adjName" />

        <input type="number" min="0" step="0.01"
          value="${Number(row.amount || 0)}"
          data-index="${index}" class="adjAmount" />

        <button type="button" data-index="${index}" class="iconbtn delAdjBtn">🗑</button>
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

    // computed OT is the current row OT (base)
    if (adjComputedOt) adjComputedOt.value = Number(row.otHours || 0);

    // load overrides into drawer
    const ov = overrides[empId] || {};
    const overrideOn = !!ov.otOverrideOn;
    const overrideHours = Number(ov.otOverrideHours ?? row.otHours ?? 0);

    if (adjOverrideToggle) adjOverrideToggle.checked = overrideOn;

    if (adjOtHours) {
      adjOtHours.disabled = !overrideOn || isLocked();
      adjOtHours.value = overrideHours;
    }

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
    if (adjOverrideToggle) adjOverrideToggle.disabled = isLocked();

    updateDrawerSummary();
    openDrawer();
  }

  function updateDrawerSummary() {
    const empId = adjEmpKey?.value || "";
    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    const otRate = Number(row.otRate || 0);
    const overrideOn = !!adjOverrideToggle?.checked;

    const typedOverrideHours = Number(adjOtHours?.value || 0);
    const finalOtHours = overrideOn ? typedOverrideHours : Number(row.otHours || 0);
    const finalOtAmount = finalOtHours * otRate;

    if (adjOtAmountPreview) adjOtAmountPreview.value = money(finalOtAmount);

    const earn = adjustmentRows
      .filter(a => a.type === "earning")
      .reduce((a, r) => a + Number(r.amount || 0), 0);

    const ded = adjustmentRows
      .filter(a => a.type === "deduction")
      .reduce((a, r) => a + Number(r.amount || 0), 0);

    const cash = Number(adjCashAdvance?.value || 0);

    const grossPreview = Number(row.halfBasic || 0) + finalOtAmount + earn;
    const dedPreview = Number(row.absentDeduction || 0) + Number(row.otherDed || 0) + ded + cash + Number(row.sss || 0) + Number(row.ph || 0) + Number(row.pagibig || 0);
    const netPreview = grossPreview - dedPreview;

    if (sumBase) sumBase.textContent = money(row.halfBasic);
    if (sumOt) sumOt.textContent = money(finalOtAmount);
    if (sumOtherEarn) sumOtherEarn.textContent = money(earn);
    if (sumOtherDed) sumOtherDed.textContent = money(ded + cash);
    if (sumNetPreview) sumNetPreview.textContent = money(netPreview);
  }

  function applyAdjust() {
    if (isLocked()) return;

    const empId = adjEmpKey?.value || "";
    if (!empId) return;

    const row = previewRows.find(r => r.empId === empId);
    if (!row) return;

    const overrideOn = !!adjOverrideToggle?.checked;
    const overrideHours = Number(adjOtHours?.value || 0);

    const cash = Number(adjCashAdvance?.value || 0);

    overrides[empId] = {
      ...(overrides[empId] || {}),
      otOverrideOn: overrideOn,
      otOverrideHours: overrideHours,
      adjustments: adjustmentRows.map(a => ({
        type: a.type,
        name: (a.name || "").trim(),
        amount: Number(a.amount || 0),
      })),
      cashAdvance: cash,
    };

    computePreview();
    renderTable();
    renderRunSummary();
    closeDrawer();
  }

  // drawer events
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);
  applyAdjBtn && applyAdjBtn.addEventListener("click", applyAdjust);

  // override toggle handling (THIS is what enables typing)
  adjOverrideToggle && adjOverrideToggle.addEventListener("change", e => {
    const enabled = !!e.target.checked;
    if (adjOtHours) adjOtHours.disabled = !enabled || isLocked();
    updateDrawerSummary();
  });

  adjOtHours && adjOtHours.addEventListener("input", updateDrawerSummary);
  adjCashAdvance && adjCashAdvance.addEventListener("input", updateDrawerSummary);

  // =========================================================
  // RUN LIFECYCLE
  // =========================================================
  function createNewRun() {
    // If current is locked, allow new run. If current is draft with edits, confirm.
    if (currentRun && currentRun.status === "Draft") {
      const ok = confirm("Start a new run? This will reset draft overrides for the current period.");
      if (!ok) return;
    }

    overrides = {};
    previewRows = [];

    const now = new Date();
    currentRun = {
      id: makeRunId(),
      periodKey: computePeriodKey(),
      periodLabel: computePeriodLabel(),
      assignment: assignmentFilter || "All",
      createdBy: "ADMIN",
      createdAt: now,
      lockedAt: null,
      releasedAt: null,
      status: "Draft",
      snapshotRows: [],
    };

    if (stickyHint) stickyHint.textContent = "New run created (Draft). Compute preview then lock.";
    applyRunUi();

    computePreview();
    renderTable();
    renderRuns();
    renderRunSummary();
  }

  function lockRun() {
    if (!currentRun) createNewRun();
    if (!currentRun) return;
    if (isLocked()) return;

    // lock = finalize snapshot
    const rowsSnapshot = previewRows.map(r => ({ ...r })); // immutable snapshot

    const s = computeSummaryFromRows(rowsSnapshot);
    currentRun.snapshotRows = rowsSnapshot;
    currentRun.lockedAt = new Date();
    currentRun.status = "Locked";

    // store in history
    processedRuns.unshift({
      id: currentRun.id,
      periodKey: currentRun.periodKey,
      periodLabel: currentRun.periodLabel,
      assignment: currentRun.assignment,
      createdBy: currentRun.createdBy,
      createdAt: currentRun.createdAt,
      lockedAt: currentRun.lockedAt,
      releasedAt: null,
      status: "Locked",
      headcount: s.headcount,
      gross: s.gross,
      deductions: s.deductions,
      net: s.net,
      snapshotRows: rowsSnapshot,
      unlockReason: null,
    });

    if (stickyHint) stickyHint.textContent = "Run locked. Inputs disabled. You can generate payslips or release.";
    applyRunUi();
    renderRuns();
    renderRunSummary();
    renderTable(); // disables row inputs via isLocked()
  }

  function unlockRun() {
    if (!currentRun) return;
    if (!isLocked()) return;

    const reason = prompt("Unlock reason (required):");
    if (!reason || !reason.trim()) {
      alert("Unlock cancelled. Reason is required.");
      return;
    }

    // mark latest stored run unlocked (audit)
    const stored = processedRuns.find(r => r.id === currentRun.id);
    if (stored) {
      stored.status = "Unlocked (Draft)";
      stored.unlockReason = reason.trim();
    }

    currentRun.status = "Draft";
    currentRun.lockedAt = null;
    currentRun.releasedAt = null;
    currentRun.snapshotRows = [];

    if (stickyHint) stickyHint.textContent = "Run unlocked (Draft). You can edit and lock again.";
    applyRunUi();

    computePreview();
    renderTable();
    renderRuns();
    renderRunSummary();
  }

  function releaseRun() {
    if (!currentRun) return;
    if (currentRun.status !== "Locked") return;

    const ok = confirm("Release this run? This marks it as released (optional) and keeps it locked.");
    if (!ok) return;

    currentRun.status = "Released";
    currentRun.releasedAt = new Date();

    const stored = processedRuns.find(r => r.id === currentRun.id);
    if (stored) {
      stored.status = "Released";
      stored.releasedAt = currentRun.releasedAt;
    }

    if (stickyHint) stickyHint.textContent = "Run released. Still locked and immutable.";
    applyRunUi();
    renderRuns();
    renderRunSummary();
  }

  newRunBtn && newRunBtn.addEventListener("click", createNewRun);
  lockRunBtn && lockRunBtn.addEventListener("click", lockRun);
  unlockRunBtn && unlockRunBtn.addEventListener("click", unlockRun);
  releaseRunBtn && releaseRunBtn.addEventListener("click", releaseRun);

  // Process button = lock run (matches your UX)
  processBtn && processBtn.addEventListener("click", () => {
    const ok = confirm("Process Payroll = Lock/Finalize this run. Continue?");
    if (!ok) return;
    lockRun();
  });

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
        <td><strong>${r.periodLabel}</strong></td>
        <td class="muted small">${r.assignment || "All"}</td>
        <td class="num">${r.headcount ?? r.count ?? 0}</td>
        <td class="num">${money(r.net ?? r.totalNet ?? 0)}</td>
        <td><span class="st st--ok">${r.status}</span></td>
      `;
      runsTbody.appendChild(tr);
    });
  }

  // =========================================================
  // UI EVENTS
  // =========================================================
  function refreshAll() {
    if (!currentRun) {
      // create default draft run automatically
      createNewRun();
      return;
    }

    if (isLocked()) {
      // locked: do not recompute or change view state
      if (stickyHint) stickyHint.textContent = "Run is locked. Unlock to change anything.";
      applyRunUi();
      renderRuns();
      renderRunSummary();
      renderTable();
      return;
    }

    // draft: recompute
    currentRun.periodKey = computePeriodKey();
    currentRun.periodLabel = computePeriodLabel();
    currentRun.assignment = assignmentFilter || "All";

    applyRunUi();
    computePreview();
    renderTable();
    renderRuns();
    renderRunSummary();
  }

  // filters changes (blocked when locked via disabled attribute, but we guard anyway)
  [monthInput, cutoffSelect].forEach(el => {
    el && el.addEventListener("change", () => refreshAll());
  });

  searchInput && searchInput.addEventListener("input", () => refreshAll());

  segBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      if (isLocked()) return;
      segBtns.forEach(b => b.classList.remove("is-active"));
      btn.classList.add("is-active");
      segBtns.forEach(b => b.setAttribute("aria-selected", b === btn ? "true" : "false"));
      assignmentFilter = btn.dataset.assign || "All";
      refreshAll();
    });
  });

  // select all
  checkAll && checkAll.addEventListener("change", () => {
    if (isLocked()) return;
    const checks = Array.from(document.querySelectorAll(".rowCheck"));
    checks.forEach(cb => { cb.checked = checkAll.checked; });
    syncCheckAll();
    renderRunSummary();
  });

  computeBtn && computeBtn.addEventListener("click", () => {
    if (isLocked()) return;
    computePreview();
    renderTable();
    renderRunSummary();
    if (stickyHint) stickyHint.textContent = "Preview refreshed. Check totals then lock.";
  });

  resetPreviewBtn && resetPreviewBtn.addEventListener("click", () => {
    if (isLocked()) return;
    const ok = confirm("Reset preview edits (OT hours, other deductions, drawer overrides) for this run?");
    if (!ok) return;
    overrides = {};
    computePreview();
    renderTable();
    renderRunSummary();
    if (stickyHint) stickyHint.textContent = "Preview overrides reset.";
  });

  payslipBtn && payslipBtn.addEventListener("click", () => {
    if (!currentRun) return;
    if (!(currentRun.status === "Locked" || currentRun.status === "Released")) return;
    alert("Generate Payslips (demo). Next step: generate PDF per employee from currentRun.snapshotRows.");
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

  // create initial run
  createNewRun();
});
