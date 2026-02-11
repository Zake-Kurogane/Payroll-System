document.addEventListener("DOMContentLoaded", () => {
  // =========================================================
  // CLOCK
  // =========================================================
  const clockEl = document.getElementById("clock");
  const dateEl = document.getElementById("date");
  function pad(n) { return String(n).padStart(2, "0"); }
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
  // DEMO EMPLOYEES (replace with DB later)
  // =========================================================
  const employees = [
    { empId: "1102", name: "Garcia, Leo", department: "Operations", position: "Logistics Aide" },
    { empId: "1044", name: "Santos, Maria", department: "Finance", position: "Payroll Clerk" },
    { empId: "1023", name: "Dela Cruz, Juan", department: "Sales", position: "Field Representative" },
  ];

  function getEmp(empId) { return employees.find(e => e.empId === empId) || null; }
  function getEmployeeName(empId) { return getEmp(empId)?.name || empId; }
  function getEmployeeDept(empId) { return getEmp(empId)?.department || "—"; }
  function getEmployeePos(empId) { return getEmp(empId)?.position || "—"; }

  // =========================================================
  // STORAGE: most recent imported attendance (THIS IS WHAT TABLE DISPLAYS)
  // =========================================================
  const LS_KEY = "attendance_recent_import_records_v1";

  function loadRecentImport() {
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed;
    } catch {
      return [];
    }
  }

  function saveRecentImport(list) {
    try {
      localStorage.setItem(LS_KEY, JSON.stringify(list));
    } catch { /* ignore */ }
  }

  // These are what your main table displays:
  let records = loadRecentImport();

  // If no imported data yet, show empty (or you can add demo)
  if (!records.length) {
    records = [];
  }

  // =========================================================
  // FILTERS / TABLE ELEMENTS
  // =========================================================
  const dateInput = document.getElementById("monthInput"); // now type=date
  const statusFilter = document.getElementById("statusFilter");
  const searchInput = document.getElementById("searchInput");
  const segBtns = Array.from(document.querySelectorAll(".seg__btn"));
  let assignmentFilter = "All";

  const statTotal = document.getElementById("statTotal");
  const statPresent = document.getElementById("statPresent");
  const statLate = document.getElementById("statLate");
  const statAbsent = document.getElementById("statAbsent");
  const statLeave = document.getElementById("statLeave");

  const tbody = document.getElementById("attTbody");
  const resultsMeta = document.getElementById("resultsMeta");

  const checkAll = document.getElementById("checkAll");
  const bulkBar = document.getElementById("bulkBar");
  const selectedCount = document.getElementById("selectedCount");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkStatusSelectInline = document.getElementById("bulkStatusSelectInline");
  const bulkApplyInline = document.getElementById("bulkApplyInline");

  // =========================================================
  // MANUAL ADD/EDIT DRAWER (kept)
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
  const f_status = document.getElementById("f_status");
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

// Summary cards (exist in your HTML)
const empSumTotal = document.getElementById("empSumTotal");
const empSumHours = document.getElementById("empSumHours");
const empSumOT = document.getElementById("empSumOT");
const empSumAbsent = document.getElementById("empSumAbsent");

let currentEmpId = "";

// helper: parse HH:MM -> minutes
function toMinutes(t) {
  const m = /^(\d{2}):(\d{2})$/.exec(t || "");
  if (!m) return null;
  const hh = Number(m[1]);
  const mm = Number(m[2]);
  if (!isFinite(hh) || !isFinite(mm)) return null;
  return hh * 60 + mm;
}

// compute total hours if missing
function computeTotalHours(r) {
  if (Number(r.totalHours) > 0) return Number(r.totalHours);
  const a = toMinutes(r.timeIn);
  const b = toMinutes(r.timeOut);
  if (a == null || b == null || b <= a) return 0;
  return (b - a) / 60;
}

function openEmpDrawer(empId) {
  currentEmpId = empId;

  // Get basic employee info (from your demo list)
  const emp = getEmp(empId);
  const name = emp?.name || getEmployeeName(empId) || empId;
  const dept = emp?.department || getEmployeeDept(empId) || "—";
  const pos = emp?.position || getEmployeePos(empId) || "—";

  // Find latest assignment from records
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

  renderEmpRecords(); // fills the drawer table

  // open UI
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

// Render employee attendance table inside drawer
function renderEmpRecords() {
  if (!empTbody || !currentEmpId) return;

  const list = records
    .filter(r => r.empId === currentEmpId)
    .slice()
    .sort((a, b) => String(b.date).localeCompare(String(a.date)));

  empTbody.innerHTML = "";

  // totals
  let totalHours = 0;
  let totalOT = 0;
  let absentCount = 0;

  list.forEach(r => {
    const hrs = computeTotalHours(r);
    const ot = Number(r.otHours || 0) || 0;

    totalHours += hrs;
    totalOT += ot;
    if (r.status === "Absent") absentCount++;

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(r.date || "—")}</td>
      <td>${escapeHtml(r.timeIn || "—")}</td>
      <td>${escapeHtml(r.timeOut || "—")}</td>
      <td class="num">${escapeHtml(formatHours(hrs))}</td>
      <td class="num">${escapeHtml(formatHours(ot))}</td>
      <td>${chip(r.status)}</td>
    `;
    empTbody.appendChild(tr);
  });

  if (!list.length) {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td colspan="6" class="muted small">No attendance records found.</td>`;
    empTbody.appendChild(tr);
  }

  if (empSumTotal) empSumTotal.textContent = String(list.length);
  if (empSumHours) empSumHours.textContent = formatHours(totalHours);
  if (empSumOT) empSumOT.textContent = formatHours(totalOT);
  if (empSumAbsent) empSumAbsent.textContent = String(absentCount);
}

// =========================================================
// MAIN TABLE: click employee name -> open employee drawer
// =========================================================
tbody && tbody.addEventListener("click", (e) => {
  // ignore checkbox clicks
  if (e.target.closest(".col-check") || e.target.closest("input.rowCheck")) return;

  // ignore action buttons (edit/delete)
  if (e.target.closest(".col-actions") || e.target.closest("button.iconbtn")) return;

  // find the row
  const tr = e.target.closest("tr");
  if (!tr) return;

  // find the employee id from the row (we stored it in the button dataset)
  // best: store empId directly on <tr> when rendering (see next snippet)
  const empId = tr.dataset.empId || "";
  if (empId) openEmpDrawer(empId);
});
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
    return `<span class="chip chip--leave">Leave</span>`;
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

  // sorting state
  let sortState = { key: "name", dir: "asc" };

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
      if (key === "name") {
        av = normalize(a.empName);
        bv = normalize(b.empName);
      } else if (key === "department") {
        av = normalize(a.department);
        bv = normalize(b.department);
      } else if (key === "assignment") {
        av = normalize(assignmentText(a));
        bv = normalize(assignmentText(b));
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
    const dateVal = dateInput?.value || ""; // exact date filter
    const statusVal = statusFilter?.value || "All";
    const q = normalize(searchInput?.value || "");

    return list.filter(r => {
      const okDate = !dateVal || r.date === dateVal;
      const okStatus = statusVal === "All" || r.status === statusVal;
      const okAssign = assignmentFilter === "All" || r.assignType === assignmentFilter;

      const text = normalize(
        `${r.empId} ${r.empName} ${r.department || ""} ${r.position || ""} ${r.date} ${r.timeIn || ""} ${r.timeOut || ""} ${r.status} ${assignmentText(r)}`
      );
      const okSearch = !q || text.includes(q);

      return okDate && okStatus && okAssign && okSearch;
    });
  }

  function render() {
    if (!tbody) return;

    const filtered = applyFilters(records);
    const sorted = applySorting(filtered);

    if (statTotal) statTotal.textContent = filtered.length;
    if (statPresent) statPresent.textContent = filtered.filter(r => r.status === "Present").length;
    if (statLate) statLate.textContent = filtered.filter(r => r.status === "Late").length;
    if (statAbsent) statAbsent.textContent = filtered.filter(r => r.status === "Absent").length;
    if (statLeave) statLeave.textContent = filtered.filter(r => r.status === "Leave").length;

    if (resultsMeta) resultsMeta.textContent = `Showing ${filtered.length} record(s)`;

    const selected = new Set(selectedIds());
    tbody.innerHTML = "";

    sorted.forEach(r => {
  const tr = document.createElement("tr");
  tr.dataset.empId = r.empId;
  tr.innerHTML = `
        <td class="col-check">
          <input class="rowCheck" type="checkbox" data-id="${r.id}" ${selected.has(r.id) ? "checked" : ""} aria-label="Select record ${r.id}">
        </td>
        <td>${escapeHtml(r.date)}</td>
        <td>
          <div class="empCell">
            <button class="empLink" type="button" data-emp="${r.empId}">
              ${escapeHtml(r.empName)}
            </button>
            <div class="empId">${escapeHtml(r.empId || "—")}</div>
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

  // Click employee name -> open employee drawer
  tbody && tbody.addEventListener("click", (e) => {
    const empBtn = e.target.closest("button.empLink");
    if (empBtn) {
      const empId = empBtn.dataset.emp || "";
      if (empId) openEmpDrawer(empId);
      return;
    }
  });

  // =========================================================
  // INIT EMPLOYEE DROPDOWN
  // =========================================================
  function initEmployeeSelect() {
    if (!f_employee) return;
    f_employee.innerHTML =
      `<option value="">—</option>` +
      employees.map(e => `<option value="${e.empId}">${e.name} — ${e.empId}</option>`).join("");
  }
  initEmployeeSelect();

  // =========================================================
  // TABS + FILTER EVENTS
  // =========================================================
  segBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      segBtns.forEach(b => b.classList.remove("is-active"));
      btn.classList.add("is-active");

      assignmentFilter = btn.dataset.assign || "All";
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

  bulkDeleteBtn && bulkDeleteBtn.addEventListener("click", () => {
    const ids = selectedIds();
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} selected record(s)?`)) return;

    records = records.filter(r => !ids.includes(r.id));
    saveRecentImport(records);
    render();
  });

  bulkStatusSelectInline && bulkStatusSelectInline.addEventListener("change", () => {
    const hasSelection = selectedIds().length > 0;
    const hasStatus = !!bulkStatusSelectInline.value;
    if (bulkApplyInline) bulkApplyInline.disabled = !(hasSelection && hasStatus);
  });

  bulkApplyInline && bulkApplyInline.addEventListener("click", () => {
    const ids = selectedIds();
    const newStatus = bulkStatusSelectInline.value;
    if (!ids.length || !newStatus) return;

    records = records.map(r => ids.includes(r.id) ? { ...r, status: newStatus } : r);
    saveRecentImport(records);

    bulkStatusSelectInline.value = "";
    if (bulkApplyInline) bulkApplyInline.disabled = true;
    document.querySelectorAll(".rowCheck:checked").forEach(cb => {
      cb.checked = false;
    });
    if (checkAll) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
    }
    updateBulkBar();
    render();
  });

  // =========================================================
  // MANUAL DRAWER (optional: saves into current imported list)
  // =========================================================
  function openDrawer(mode, record) {
    if (!drawer || !drawerOverlay) return;

    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    drawerOverlay.hidden = false;
    document.body.style.overflow = "hidden";
    clearErrors();

    if (mode === "add") {
      drawerTitle.textContent = "Add Attendance";
      drawerSub.textContent = "Fill up the details below.";
      editingId.value = "";

      f_employee.value = "";
      f_date.value = new Date().toISOString().slice(0, 10);
      f_status.value = "";
      f_assignType.value = "";
      f_areaPlace.value = "";
      f_notes.value = "";
      if (areaWrap) areaWrap.hidden = true;
    } else {
      drawerTitle.textContent = "Edit Attendance";
      drawerSub.textContent = `Record: ${record.empId} • ${record.date}`;
      editingId.value = record.id;

      f_employee.value = record.empId;
      f_date.value = record.date;
      f_status.value = record.status;
      f_assignType.value = record.assignType;
      f_notes.value = record.notes || "";

      if (record.assignType === "Area") {
        if (areaWrap) areaWrap.hidden = false;
        f_areaPlace.value = record.areaPlace || "";
      } else {
        if (areaWrap) areaWrap.hidden = true;
        f_areaPlace.value = "";
      }
    }
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    drawerOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  function validateForm() {
    clearErrors();
    let ok = true;

    const empId = f_employee.value;
    const date = f_date.value;
    const status = f_status.value;
    const assignType = f_assignType.value;
    const area = f_areaPlace.value;

    if (!empId) { errEmployee.textContent = "Employee is required."; ok = false; }
    if (!date) { errDate.textContent = "Date is required."; ok = false; }
    if (!status) { errStatus.textContent = "Status is required."; ok = false; }
    if (!assignType) { errAssignType.textContent = "Assignment Type is required."; ok = false; }

    if (assignType === "Area") {
      if (!area) { errAreaPlace.textContent = "Area Place is required for Area assignment."; ok = false; }
    } else {
      if (area) { errAreaPlace.textContent = "Area Place must be empty for Tagum/Davao."; ok = false; }
    }

    return ok;
  }

  openAddBtn && openAddBtn.addEventListener("click", () => openDrawer("add"));
  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);

  f_assignType && f_assignType.addEventListener("change", () => {
    const type = f_assignType.value;
    if (type === "Area") {
      if (areaWrap) areaWrap.hidden = false;
    } else {
      if (areaWrap) areaWrap.hidden = true;
      if (f_areaPlace) f_areaPlace.value = "";
      if (errAreaPlace) errAreaPlace.textContent = "";
    }
  });

  saveBtn && saveBtn.addEventListener("click", () => {
    if (!validateForm()) return;

    const id = editingId.value;
    const empId = f_employee.value;

    const payload = {
      id: id || `A${Math.random().toString(16).slice(2, 8).toUpperCase()}`,
      empId,
      empName: getEmployeeName(empId),
      department: getEmployeeDept(empId),
      position: getEmployeePos(empId),
      date: f_date.value,
      timeIn: "",
      timeOut: "",
      totalHours: 0,
      otHours: 0,
      status: f_status.value,
      assignType: f_assignType.value,
      areaPlace: f_assignType.value === "Area" ? f_areaPlace.value : "",
      notes: (f_notes.value || "").trim(),
    };

    if (!id) records.unshift(payload);
    else records = records.map(r => r.id === id ? { ...r, ...payload, id } : r);

    saveRecentImport(records);
    closeDrawer();
    render();
  });

  // =========================================================
  // IMPORT / PREVIEW (DEMO parsing)
  // =========================================================
  let importRows = [];
  let previewMode = "errors";

  function setImportUISelected(file) {
    if (fileNameLabel) fileNameLabel.textContent = file ? file.name : "No file selected.";
    if (clearFileBtn) clearFileBtn.disabled = !file;
    if (previewImportBtn) previewImportBtn.disabled = !file;
  }

  function openPreview() {
    previewDrawer.classList.add("is-open");
    previewDrawer.setAttribute("aria-hidden", "false");
    previewOverlay.hidden = false;
    document.body.style.overflow = "hidden";
  }
  function closePreview() {
    previewDrawer.classList.remove("is-open");
    previewDrawer.setAttribute("aria-hidden", "true");
    previewOverlay.hidden = true;
    document.body.style.overflow = "";
  }

  function parseDateOk(yyyy_mm_dd) {
    return /^\d{4}-\d{2}-\d{2}$/.test(yyyy_mm_dd || "");
  }
  function isValidStatus(s) {
    return ["Present", "Late", "Absent", "Leave"].includes(s);
  }
  function isValidAssignType(s) {
    return ["Davao", "Tagum", "Area"].includes(s);
  }
  function isValidAreaPlace(s) {
    return ["Laak", "Pantukan", "Maragusan"].includes(s);
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
    if (!r.empId) issues.push(`Row ${r.rowNo}: Missing Employee ID`);
    if (!r.date || !parseDateOk(r.date)) issues.push(`Row ${r.rowNo}: Invalid or missing Date`);
    if (!isValidStatus(r.status)) issues.push(`Row ${r.rowNo}: Invalid status "${r.status}"`);
    if (!isValidAssignType(r.assignType)) issues.push(`Row ${r.rowNo}: Invalid Assignment Type "${r.assignType}"`);

    if (r.assignType === "Area") {
      if (!r.areaPlace || !isValidAreaPlace(r.areaPlace)) {
        issues.push(`Row ${r.rowNo}: Area Place is required (Laak / Pantukan / Maragusan)`);
      }
    } else if (r.assignType === "Tagum" || r.assignType === "Davao") {
      if (r.areaPlace) issues.push(`Row ${r.rowNo}: Area Place must be empty for ${r.assignType}`);
    }

    return issues;
  }

  // Demo parsed rows (replace with real XLSX parsing later)
  function simulateParsedRows() {
    return [
      { rowNo: 2, empId: "1102", date: "2026-02-09", timeIn: "08:02", timeOut: "17:11", otHours: 0.0, status: "Present", assignType: "Tagum", areaPlace: "" },
      { rowNo: 3, empId: "1044", date: "2026-02-09", timeIn: "08:08", timeOut: "17:00", otHours: 0.5, status: "Late", assignType: "Area", areaPlace: "Laak" },
      { rowNo: 4, empId: "1023", date: "2026-02-09", timeIn: "08:01", timeOut: "17:03", otHours: 1.0, status: "Present", assignType: "Davao", areaPlace: "" },
    ];
  }

  function buildImportRows(parsed) {
    return parsed.map(r => {
      const issues = validateImportRow(r);
      const totalHours = calcHours(r.timeIn, r.timeOut);

      return {
        ...r,
        empName: r.empId ? getEmployeeName(r.empId) : "",
        department: r.empId ? getEmployeeDept(r.empId) : "",
        position: r.empId ? getEmployeePos(r.empId) : "",
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
      saveMessage.textContent = "Ready to save.";
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

  function runPreviewImport() {
    const parsed = simulateParsedRows();
    importRows = buildImportRows(parsed);
    previewMode = "errors";

    showErrorsOnlyBtn && showErrorsOnlyBtn.classList.add("is-active");
    showAllRowsBtn && showAllRowsBtn.classList.remove("is-active");

    updatePreviewSummary();
    renderPreviewTable();
    openPreview();
  }

  function saveImport() {
    const clean = importRows.filter(r => !r.isError).map(r => ({
      id: `A${Math.random().toString(16).slice(2, 8).toUpperCase()}`,
      empId: r.empId,
      empName: r.empName,
      department: r.department,
      position: r.position,
      date: r.date,
      timeIn: r.timeIn || "",
      timeOut: r.timeOut || "",
      totalHours: r.totalHours || 0,
      otHours: Number(r.otHours || 0),
      status: r.status,
      assignType: r.assignType,
      areaPlace: r.assignType === "Area" ? (r.areaPlace || "") : "",
      notes: "",
    }));

    // ✅ MAIN REQUIREMENT: table should show most recently imported excel data
    records = clean;
    saveRecentImport(records);

    closePreview();
    render();
    alert("Imported attendance saved (front-end demo).");
  }

  // Import file events
  if (importFile) {
    importFile.addEventListener("change", () => {
      const file = importFile.files && importFile.files[0] ? importFile.files[0] : null;
      if (!file) return;
      setImportUISelected(file);
      runPreviewImport();
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
    // Export an empty CSV template that matches the required format in the sample image.
    // Only the header row is included (no sample data).
    const csv = [
      "Date,Name,Department,Assignment,Area,Time In,Time Out,OT,Status",
    ].join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "attendance_template.csv";
    a.click();
    URL.revokeObjectURL(url);
  });

  // Preview drawer events
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

  saveImportBtn && saveImportBtn.addEventListener("click", () => {
    if (saveImportBtn.disabled) return;
    saveImport();
  });

  // =========================================================
  // DEFAULT DATE FILTER: set to today (you can change)
  // =========================================================
  if (dateInput) {
    const now = new Date();
    dateInput.value = now.toISOString().slice(0, 10);
  }

  render();
});
