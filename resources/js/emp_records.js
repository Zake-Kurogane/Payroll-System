document.addEventListener("DOMContentLoaded", () => {
  // ===== Clock =====
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

  // ===== User dropdown =====
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

  // ===== Constants =====
  const AREA_PLACES = ["Laak", "Pantukan", "Maragusan"];

  // ===== Demo data =====
  let employees = [
    {
      empId: "1023",
      first: "Juan",
      last: "Dela Cruz",
      middle: "",
      dept: "Admin",
      position: "Clerk",
      type: "Regular",
      status: "Active",
      payType: "Monthly",
      rate: 20000,
      assignmentType: "Area",
      areaPlace: "Pantukan",
      birthday: "1998-06-15",
      hired: "2023-02-10",
      mobile: "09123456789",
      email: "juan@example.com",
      address: "Tagum City",
      sss: "12-3456789-0",
      ph: "12-345678901-2",
      pagibig: "1234-5678-9012",
      tin: ""
    },
    {
      empId: "1044",
      first: "Maria",
      last: "Santos",
      middle: "",
      dept: "HR",
      position: "Assistant",
      type: "Regular",
      status: "Active",
      payType: "Monthly",
      rate: 24000,
      assignmentType: "Davao",
      areaPlace: "",
      birthday: "1999-11-02",
      hired: "2022-08-01",
      mobile: "09998887777",
      email: "maria@example.com",
      address: "Tagum City",
      sss: "",
      ph: "",
      pagibig: "",
      tin: ""
    },
    {
      empId: "1102",
      first: "Leo",
      last: "Garcia",
      middle: "",
      dept: "IT",
      position: "Staff",
      type: "Contractual",
      status: "Inactive",
      payType: "Monthly",
      rate: 19000,
      assignmentType: "Tagum",
      areaPlace: "",
      birthday: "2000-03-10",
      hired: "2024-01-15",
      mobile: "",
      email: "",
      address: "",
      sss: "",
      ph: "",
      pagibig: "",
      tin: ""
    },
  ];

  // ===== Elements =====
  const tbody = document.getElementById("empTbody");
  const resultsMeta = document.getElementById("resultsMeta");

  // ✅ NEW: top controls (same IDs as HTML)
  const searchInput = document.getElementById("searchInput");
  const deptFilter = document.getElementById("deptFilter");
  const statusFilter = document.getElementById("statusFilter"); // ✅ added

  // import/export
  const exportBtn = document.getElementById("exportBtn");
  const importBtn = document.getElementById("importBtn");
  const importFile = document.getElementById("importFile");

  // table select all
  const selectAll = document.getElementById("selectAll");
  const bulkBar = document.getElementById("bulkBarEmp");
  const selectedCountEmp = document.getElementById("selectedCountEmp");
  const bulkDeleteEmpBtn = document.getElementById("bulkDeleteEmpBtn");
  const bulkAssignSelect = document.getElementById("bulkAssignSelect");
  const bulkAssignApply = document.getElementById("bulkAssignApply");

  // pagination
  const pageSizeEl = document.getElementById("pageSize");
  const pageMetaEl = document.getElementById("pageMeta");
  const pageInputEl = document.getElementById("pageInput");
  const totalPagesEl = document.getElementById("totalPages");
  const firstPageBtn = document.getElementById("firstPage");
  const prevPageBtn = document.getElementById("prevPage");
  const nextPageBtn = document.getElementById("nextPage");
  const lastPageBtn = document.getElementById("lastPage");

  let pageSize = Number(pageSizeEl?.value || 20);
  let currentPage = 1;

  // sorting state
  let sortState = { key: "name", dir: "asc" };

  // drawer (keep your existing drawer wiring if you have it)
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const openAddBtn = document.getElementById("openAddBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const deleteBtn = document.getElementById("deleteBtn");
  const empForm = document.getElementById("empForm");

  const F = (id) => document.getElementById(id);

  // form fields (keep same ids from your form)
  const f_empId = F("f_empId");
  const f_status = F("f_status");
  const f_first = F("f_first");
  const f_last = F("f_last");
  const f_middle = F("f_middle");
  const f_bday = F("f_bday");
  const f_mobile = F("f_mobile");
  const f_email = F("f_email");
  const f_address = F("f_address");
  const f_dept = F("f_dept");
  const f_position = F("f_position");
  const f_type = F("f_type");
  const f_hired = F("f_hired");
  const f_payType = F("f_payType");
  const f_rate = F("f_rate");

  const f_assignmentType = F("f_assignmentType");
  const f_areaPlace = F("f_areaPlace");
  const areaPlaceWrap = document.getElementById("areaPlaceWrap");

  const f_sss = F("f_sss");
  const f_ph = F("f_ph");
  const f_pagibig = F("f_pagibig");
  const f_tin = F("f_tin");

  const f_caEligible = F("f_caEligible");
  const f_caMax = F("f_caMax");

  // state
  let selectedEmpId = null;
  let selectedIds = new Set();

  // ===== Helpers =====
  function fullName(emp) {
    return `${emp.last}, ${emp.first}${emp.middle ? " " + emp.middle : ""}`;
  }

  function money(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return "—";
    return `₱ ${num.toLocaleString()}`;
  }

  function assignmentText(emp) {
    const t = (emp.assignmentType || "").trim();
    if (!t) return "—";
    if (t === "Area") return `Area (${(emp.areaPlace || "").trim() || "—"})`;
    return t;
  }

  function govShort(emp) {
    const parts = [];
    if (emp.sss) parts.push("SSS");
    if (emp.ph) parts.push("PH");
    if (emp.pagibig) parts.push("PAG");
    if (emp.tin) parts.push("TIN");
    return parts.length ? parts.join(" • ") : "—";
  }

  function computeCashAdvance(empType, basicPay) {
    const isRegular = String(empType || "").toLowerCase() === "regular";
    if (!isRegular) return { eligible: "Not eligible", max: "—" };
    const max = Number(basicPay || 0) * 2;
    return { eligible: "✅ Eligible", max: money(max) };
  }

  function populateAreaPlaces(selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    selectEl.innerHTML =
      `<option value="">-- Select area place --</option>` +
      AREA_PLACES.map(p => `<option value="${p}">${p}</option>`).join("");
    if (AREA_PLACES.includes(current)) selectEl.value = current;
  }

  function syncAssignmentUI() {
    if (!f_assignmentType || !f_areaPlace) return;
    populateAreaPlaces(f_areaPlace);

    const type = f_assignmentType.value;
    if (type === "Area") {
      f_areaPlace.disabled = false;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "";
    } else {
      f_areaPlace.value = "";
      f_areaPlace.disabled = true;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "none";
    }
  }

  function syncCashAdvanceFields() {
    if (!f_caEligible || !f_caMax) return;
    const ca = computeCashAdvance(f_type?.value || "", Number(f_rate?.value || 0));
    f_caEligible.value = ca.eligible;
    f_caMax.value = ca.max;
  }

  function updateBulkBar() {
    const count = selectedIds.size;
    if (selectedCountEmp) selectedCountEmp.textContent = String(count);

    const show = count > 0;
    if (bulkBar) {
      bulkBar.style.display = show ? "flex" : "none";
      bulkBar.setAttribute("aria-hidden", show ? "false" : "true");
    }

    if (!show) {
      if (bulkAssignSelect) bulkAssignSelect.value = "";
      if (bulkAssignApply) bulkAssignApply.disabled = true;
    } else if (bulkAssignSelect && bulkAssignApply) {
      bulkAssignApply.disabled = !bulkAssignSelect.value;
    }
  }

  // ===== Sorting =====
  function updateSortIcons() {
    document.querySelectorAll("th.sortable").forEach(th => {
      th.classList.remove("is-asc", "is-desc");
      const k = th.getAttribute("data-sort");
      if (k === sortState.key) {
        th.classList.add(sortState.dir === "asc" ? "is-asc" : "is-desc");
      }
    });
  }

  function applySort(list) {
    const copy = [...list];
    const key = sortState.key;
    const dir = sortState.dir;
    const sign = dir === "asc" ? 1 : -1;

    copy.sort((a, b) => {
      let res = 0;
      if (key === "empId") res = String(a.empId).localeCompare(String(b.empId));
      else if (key === "name") res = fullName(a).localeCompare(fullName(b));
      else if (key === "dept") res = String(a.dept || "").localeCompare(String(b.dept || "")) || fullName(a).localeCompare(fullName(b));
      else if (key === "position") res = String(a.position || "").localeCompare(String(b.position || "")) || fullName(a).localeCompare(fullName(b));
      else if (key === "type") res = String(a.type || "").localeCompare(String(b.type || "")) || fullName(a).localeCompare(fullName(b));
      else if (key === "assignment") res = String(assignmentText(a)).localeCompare(String(assignmentText(b))) || fullName(a).localeCompare(fullName(b));
      else if (key === "rate") res = (Number(a.rate || 0) - Number(b.rate || 0)) || fullName(a).localeCompare(fullName(b));
      else res = fullName(a).localeCompare(fullName(b));
      return res * sign;
    });

    return copy;
  }

  function wireHeaderSorting() {
    document.querySelectorAll("th.sortable").forEach(th => {
      th.addEventListener("click", () => {
        const key = th.getAttribute("data-sort");
        if (!key) return;

        if (sortState.key === key) sortState.dir = sortState.dir === "asc" ? "desc" : "asc";
        else { sortState.key = key; sortState.dir = "asc"; }

        currentPage = 1;
        updateSortIcons();
        render();
      });
    });
  }

  // ✅ Filters/Search (NOW includes Status)
  function applyFilters(list) {
    const q = (searchInput?.value || "").trim().toLowerCase();
    const dept = deptFilter?.value || "All";
    const status = statusFilter?.value || "All";

    return list.filter(emp => {
      const text = `${emp.empId} ${fullName(emp)} ${emp.dept} ${emp.position} ${emp.type} ${emp.status} ${assignmentText(emp)} ${govShort(emp)}`
        .toLowerCase();

      const okQ = !q || text.includes(q);
      const okDept = dept === "All" || emp.dept === dept;
      const okStatus = status === "All" || emp.status === status;

      return okQ && okDept && okStatus;
    });
  }

  // ===== Render (filters + sort + paginate) =====
  function render() {
    if (!tbody) return;

    let list = applyFilters(employees);
    list = applySort(list);

    pageSize = Number(pageSizeEl?.value || pageSize || 20);
    const totalItems = list.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    currentPage = Math.min(Math.max(1, currentPage), totalPages);

    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    const pageList = list.slice(start, end);

    if (resultsMeta) resultsMeta.textContent = `Showing ${pageList.length} of ${totalItems} employee${totalItems === 1 ? "" : "s"}`;

    if (pageMetaEl) pageMetaEl.textContent = `Page ${currentPage} of ${totalPages}`;
    if (totalPagesEl) totalPagesEl.textContent = String(totalPages);
    if (pageInputEl) pageInputEl.value = String(currentPage);

    if (firstPageBtn) firstPageBtn.disabled = currentPage === 1;
    if (prevPageBtn) prevPageBtn.disabled = currentPage === 1;
    if (nextPageBtn) nextPageBtn.disabled = currentPage === totalPages;
    if (lastPageBtn) lastPageBtn.disabled = currentPage === totalPages;

    tbody.innerHTML = "";

    pageList.forEach(emp => {
      const isChecked = selectedIds.has(emp.empId);

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="col-check">
          <input class="empCheck" type="checkbox" data-id="${emp.empId}" ${isChecked ? "checked" : ""} aria-label="Select employee ${emp.empId}">
        </td>
        <td>${emp.empId}</td>
        <td>${fullName(emp)}</td>
        <td>${emp.dept || "—"}</td>
        <td>${emp.position || "—"}</td>
        <td>${emp.type || "—"}</td>
        <td>${assignmentText(emp)}</td>
        <td>${money(emp.rate)} <span class="muted small">(${emp.payType || "—"})</span></td>
        <td>${govShort(emp)}</td>
        <td class="actions">
          <button class="iconbtn" type="button" data-action="edit" data-id="${emp.empId}" title="Edit" aria-label="Edit">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 20h9"></path>
              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
            </svg>
          </button>
          <button class="iconbtn" type="button" data-action="delete" data-id="${emp.empId}" title="Delete" aria-label="Delete">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M3 6h18"></path>
              <path d="M8 6V4h8v2"></path>
              <path d="M19 6l-1 14H6L5 6"></path>
              <path d="M10 11v6"></path>
              <path d="M14 11v6"></path>
            </svg>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    updateSelectAllState(pageList);
    updateBulkBar();
  }

  function updateSelectAllState(visibleList) {
    if (!selectAll) return;
    if (!visibleList.length) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    const checkedCount = visibleList.filter(e => selectedIds.has(e.empId)).length;
    selectAll.checked = checkedCount === visibleList.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleList.length;
  }

  // ===== Events =====
  // ✅ filters
  [searchInput, deptFilter, statusFilter].forEach(el => {
    if (!el) return;
    el.addEventListener(el === searchInput ? "input" : "change", () => {
      currentPage = 1;
      render();
    });
  });

  // pagination
  pageSizeEl && pageSizeEl.addEventListener("change", () => { currentPage = 1; render(); });
  firstPageBtn && firstPageBtn.addEventListener("click", () => { currentPage = 1; render(); });
  prevPageBtn && prevPageBtn.addEventListener("click", () => { currentPage = Math.max(1, currentPage - 1); render(); });
  nextPageBtn && nextPageBtn.addEventListener("click", () => { currentPage = currentPage + 1; render(); });
  lastPageBtn && lastPageBtn.addEventListener("click", () => {
    const total = applyFilters(employees).length;
    const last = Math.max(1, Math.ceil(total / Number(pageSizeEl?.value || 20)));
    currentPage = last;
    render();
  });
  pageInputEl && pageInputEl.addEventListener("change", () => {
    const n = Number(pageInputEl.value || 1);
    if (!Number.isFinite(n)) return;
    currentPage = Math.max(1, n);
    render();
  });

  // select all (current page)
  if (selectAll) {
    selectAll.addEventListener("change", () => {
      const list = applySort(applyFilters(employees));
      const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
      currentPage = Math.min(currentPage, totalPages);

      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;
      const pageList = list.slice(start, end);

      if (selectAll.checked) pageList.forEach(emp => selectedIds.add(emp.empId));
      else pageList.forEach(emp => selectedIds.delete(emp.empId));

      render();
    });
  }

  // table click (checkbox/edit/delete)
  tbody && tbody.addEventListener("click", (e) => {
    const chk = e.target.closest("input.empCheck");
    if (chk) {
      const id = chk.getAttribute("data-id");
      if (chk.checked) selectedIds.add(id);
      else selectedIds.delete(id);
      render();
      return;
    }

    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const id = btn.getAttribute("data-id");
    const action = btn.getAttribute("data-action");
    const emp = employees.find(x => x.empId === id);
    if (!emp) return;

    if (action === "edit") alert(`Edit ${fullName(emp)} (wire to drawer as you already have)`);
    if (action === "delete") {
      if (confirm(`Delete employee ${fullName(emp)} (${emp.empId})?`)) {
        employees = employees.filter(x => x.empId !== id);
        selectedIds.delete(id);
        render();
      }
    }
  });

  // import/export demo
  exportBtn && exportBtn.addEventListener("click", () => alert("Export wired (use your existing exportCSV)"));
  importBtn && importBtn.addEventListener("click", () => importFile?.click());
  importFile && importFile.addEventListener("change", () => {
    const f = importFile.files?.[0];
    if (!f) return;
    alert(`Selected file: ${f.name}`);
    importFile.value = "";
  });

  // Init
  syncAssignmentUI();
  syncCashAdvanceFields();
  wireHeaderSorting();
  updateSortIcons();
  render();
});
