import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";
import { broadcastEmployeeUpdate } from "./shared/dataSync";
import { initSelect2 } from "./shared/select2";

document.addEventListener("DOMContentLoaded", async () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();
  const notifyEmployeeUpdated = () => broadcastEmployeeUpdate();

  // ===== Constants =====
  // Grouped area places: { Davao: [...], Tagum: [...], Field: [...] }
  let areaPlaces = (window.__areaPlaces && typeof window.__areaPlaces === 'object' && !Array.isArray(window.__areaPlaces))
    ? window.__areaPlaces
    : {};

  let positionsCatalog = Array.isArray(window.__positions) ? window.__positions : [];
  let externalPositionsCatalog = Array.isArray(window.__externalPositions) ? window.__externalPositions : [];

  const CAN_VIEW_COMP = window.__canViewCompensation !== undefined ? !!window.__canViewCompensation : true;

  // ===== Payroll Required Field Rules =====
  const PAYROLL_REQUIRED = {
    requirePayGroup: false,
    requireAssignment: true,
    requireBasicPay: true,
    requireGovIds: true, // requires ALL gov ids below
    govRequiredFields: ["sss", "ph", "pagibig", "tin"], // adjust if needed
  };
  if (!CAN_VIEW_COMP) {
    PAYROLL_REQUIRED.requireBasicPay = false;
  }

  // ===== API helpers =====
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  const serverRendered = window.__serverRender === true;
  if (serverRendered) {
    const navEntries = performance.getEntriesByType && performance.getEntriesByType("navigation");
    const navType = navEntries && navEntries.length ? navEntries[0].type : "";
    if ((navType === "reload" || navType === "reload_navigation") && window.location.search) {
      window.location.href = window.location.pathname;
    }
  }

  const AUTO_REFRESH_MS = 2000;
  let isAutoRefreshing = false;
  let sortLocked = false;
  let lastHeartbeat = null;

  async function apiFetch(url, options = {}) {
    const headers = {
      Accept: "application/json",
      ...(options.headers || {}),
    };
    if (options.body && !headers["Content-Type"]) {
      headers["Content-Type"] = "application/json";
    }
    if (csrfToken) headers["X-CSRF-TOKEN"] = csrfToken;

    const res = await fetch(url, { ...options, headers });
    const data = await res.json().catch(() => null);
    if (!res.ok) {
      const msg = data?.message || "Request failed.";
      throw new Error(msg);
    }
    return data;
  }

  function fromApi(emp) {
    return {
      empId: emp.emp_no,
      first: emp.first_name,
      last: emp.last_name,
      middle: emp.middle_name || "",
      dept: emp.department || "",
      basedLocation: emp.based_location || "",
      position: emp.position || "",
      positionIds: Array.isArray(emp.positions) ? emp.positions.map(p => Number(p.id)).filter(Number.isFinite) : [],
      type: emp.employment_type || "",
      status: emp.status || emp.employment_status?.label || "Active",
      statusId: emp.employment_status_id || "",
      payType: emp.pay_type || "",
      rate: Number(emp.basic_pay || 0),
      allowance: Number(emp.allowance || 0),
      payGroup: "",

      assignmentType: emp.assignment_type || "",
      areaPlace: emp.area_place || "",
      externalArea: emp.external_area || "",
      externalPositionId: emp.external_position_id || emp.external_position?.id || emp.externalPosition?.id || "",
      birthday: emp.birthday || "",
      hired: emp.date_hired || "",
      mobile: emp.mobile || "",
      email: emp.email || "",
      address: emp.address || "",
      addrProvince: emp.address_province || "",
      addrCity: emp.address_city || "",
      addrBarangay: emp.address_barangay || "",
      addrStreet: emp.address_street || "",
      bankName: emp.bank_name || "",
      accountName: emp.bank_account_name || "",
      accountNumber: emp.bank_account_number || "",
      sss: emp.sss || "",
      ph: emp.philhealth || "",
      pagibig: emp.pagibig || "",
      tin: emp.tin || "",
    };
  }

  function toApi(data) {
    const payload = {
      emp_no: data.empId,
      first_name: data.first,
      middle_name: data.middle || null,
      last_name: data.last,
      status: data.status || null,
      employment_status_id: data.statusId || null,
      birthday: data.birthday || null,
      mobile: data.mobile || null,
      address: [data.addrStreet, data.addrBarangay, data.addrCity, data.addrProvince].filter(Boolean).join(", ") || null,
      address_province: data.addrProvince || null,
      address_city: data.addrCity || null,
      address_barangay: data.addrBarangay || null,
      address_street: data.addrStreet || null,
      email: data.email || null,
      department: data.dept || null,
      based_location: data.basedLocation || null,
      position_ids: data.positionIds || [],
      employment_type: data.type || null,
      pay_type: data.payType || null,
      date_hired: data.hired || null,
      assignment_type: data.assignmentType || null,
      area_place: data.areaPlace || null,
      external_area: data.externalArea || null,
      external_position_id: data.externalPositionId ? Number(data.externalPositionId) : null,
      bank_name: data.bankName || null,
      bank_account_name: data.accountName || null,
      bank_account_number: data.accountNumber || null,
      payout_method: (data.accountNumber || "").trim() ? "BANK" : "CASH",
      sss: data.sss || null,
      philhealth: data.ph || null,
      pagibig: data.pagibig || null,
      tin: data.tin || null,
    };
    if (CAN_VIEW_COMP) {
      payload.basic_pay = data.rate || 0;
      payload.allowance = data.allowance || 0;
    }
    return payload;
  }

  async function loadEmployees(params = {}) {
    const qs = new URLSearchParams(params);
    const url = qs.toString() ? `/employees?${qs.toString()}` : "/employees";
    const rows = await apiFetch(url);
    employees = Array.isArray(rows) ? rows.map(fromApi) : [];
  }

  let employees = [];

  function normalize(value) {
    return String(value || "").toLowerCase().replace(/\s+/g, " ").trim();
  }

  function statusMatches(emp, filterVal) {
    if (!filterVal || filterVal === "All") return true;
    const raw = String(filterVal);
    if (String(emp.statusId || "") === raw) return true;
    const label = statusLabelMap.get(raw);
    if (label) return normalize(emp.status) === normalize(label);
    return normalize(emp.status) === normalize(raw);
  }

  function currentAssignmentFilter() {
    if (!assignSeg) return assignmentFilter || "All";
    const active = assignSeg.querySelector(".seg__btn--emp.is-active");
    const raw = active ? active.getAttribute("data-assign") : "";
    return raw && raw !== "" ? raw : "All";
  }

  // ===== Elements =====
  const tbody = document.getElementById("empTbody");
  const resultsMeta = document.getElementById("resultsMeta");

  // top controls
  const searchInput = document.getElementById("searchInput");
  const deptFilter = document.getElementById("deptFilter");
  const statusFilter = document.getElementById("statusFilter");
  const assignSeg = document.getElementById("assignSeg");
  let assignBtns = [];
  const areaPlaceFilterWrap = document.getElementById("areaPlaceFilterWrap"); // may be null
  const areaPlaceFilter = document.getElementById("areaPlaceFilter"); // may be null
  let areaSubFilter = ""; // selected area_place sub-option
  let openDropdown = null;
  let openDropdownBtn = null;

  // import/export
  const exportBtn = document.getElementById("exportBtn");
  const importDisciplineBtn = document.getElementById("importDisciplineBtn");
  const disciplineFileInput = document.getElementById("disciplineFileInput");


  // table select all
  const selectAll = document.getElementById("selectAll");
  const bulkBar = document.getElementById("bulkBarEmp");
  const selectedCountEmp = document.getElementById("selectedCountEmp");
  const bulkDeleteEmpBtn = document.getElementById("bulkDeleteEmpBtn");
  const bulkAssignSelect = document.getElementById("bulkAssignSelect");
  const bulkAreaPlaceSelect = document.getElementById("bulkAreaPlaceSelect");
  const bulkAssignApply = document.getElementById("bulkAssignApply");

  // pagination
  const pageSizeEl = document.getElementById("pageSize") || document.getElementById("rowsPerPage");
  const pageMetaEl = document.getElementById("pageMeta") || document.querySelector(".tableFooter__left");
  const pageInputEl = document.getElementById("pageInput");
  const totalPagesEl = document.getElementById("totalPages") || document.getElementById("pageTotal");
  const firstPageBtn = document.getElementById("firstPage") || document.querySelector('.pagerBtn[aria-label="First page"]');
  const prevPageBtn = document.getElementById("prevPage") || document.querySelector('.pagerBtn[aria-label="Previous page"]');
  const nextPageBtn = document.getElementById("nextPage") || document.querySelector('.pagerBtn[aria-label="Next page"]');
  const lastPageBtn = document.getElementById("lastPage") || document.querySelector('.pagerBtn[aria-label="Last page"]');

  let pageSize = Number(pageSizeEl?.value || 20);
  let currentPage = 1;

  // sorting state
  let sortState = { key: "name", dir: "asc" };

  // drawer controls
  const drawer = document.getElementById("drawer");
  const drawerOverlay = document.getElementById("drawerOverlay");
  const closeDrawerBtn = document.getElementById("closeDrawerBtn");
  const openAddBtn = document.getElementById("openAddBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const deleteBtn = document.getElementById("deleteBtn");
  const saveBtn = document.getElementById("saveBtn");
  const empForm = document.getElementById("empForm");
  const drawerTitleEl = document.getElementById("drawerTitle");
  const drawerSubEl = document.getElementById("drawerSubtitle");
  const toastEl = document.getElementById("toast");

  const F = (id) => document.getElementById(id);

  // form fields
  const f_empId = F("f_empId");
  const f_status = F("f_status");
  const f_first = F("f_first");
  const f_last = F("f_last");
  const f_middle = F("f_middle");
  const f_bday = F("f_bday");
  const f_mobile = F("f_mobile");
  const f_email = F("f_email");
  const f_addrProvince = F("f_addrProvince");
  const f_addrCity = F("f_addrCity");
  const f_addrBarangay = F("f_addrBarangay");
  const f_addrStreet = F("f_addrStreet");
  const f_dept = F("f_dept");
  const f_basedLocation = F("f_basedLocation");

  // inline validation errors (rendered under fields in the drawer)
  const errEmpId = F("errEmpId");
  const errFirst = F("errFirst");
  const errLast = F("errLast");
  const errPositions = F("errPositions");
  const errExternalArea = F("errExternalArea");
  const errExternalPosition = F("errExternalPosition");
  const errRate = F("errRate");
  const errAllowance = F("errAllowance");
  const errBankName = F("errBankName");
  const errAccountName = F("errAccountName");
  const errAccountNumber = F("errAccountNumber");

  // Positions dropdown checklist
  const posDd = document.getElementById("posDd");
  const posDdBtn = document.getElementById("posDdBtn");
  const posDdPanel = document.getElementById("posDdPanel");
  const posSearch = document.getElementById("posSearch");
  const posDdList = document.getElementById("posDdList");

  const f_type = F("f_type");
  const f_hired = F("f_hired");
  const f_payType = F("f_payType");
  const f_rate = F("f_rate");
  const f_allowance = F("f_allowance");
  const f_totalSalary = F("f_totalSalary");

  const f_assignmentType = F("f_assignmentType");
  const f_areaPlace = F("f_areaPlace");
  const areaPlaceWrap = document.getElementById("areaPlaceWrap");
  const f_externalArea = F("f_externalArea");
  const externalAreaWrap = document.getElementById("externalAreaWrap");
  const f_externalPosition = F("f_externalPosition");
  const externalPositionWrap = document.getElementById("externalPositionWrap");
  const historyDrawer = document.getElementById("historyDrawer");
  const historyDrawerOverlay = document.getElementById("historyDrawerOverlay");
  const closeHistoryDrawerBtn = document.getElementById("closeHistoryDrawerBtn");
  const historyDrawerTitleEl = document.getElementById("historyDrawerTitle");
  const historyDrawerSubEl = document.getElementById("historyDrawerSubtitle");
  const historyDrawerExternalEl = document.getElementById("historyDrawerExternal");
  const areaHistoryList = document.getElementById("areaHistoryList");
  const historySearch = document.getElementById("historySearch");
  let allHistoryRanges = [];

  const plInfoWrap = document.getElementById("plInfoWrap");
  const f_plCount = document.getElementById("f_plCount");
  const f_plBadge = document.getElementById("f_plBadge");

  const f_sss = F("f_sss");
  const f_ph = F("f_ph");
  const f_pagibig = F("f_pagibig");
  const f_tin = F("f_tin");

  const f_bankName = F("f_bankName");
  const f_accountName = F("f_accountName");
  const f_accountNumber = F("f_accountNumber");
  const f_payoutMethod = F("f_payoutMethod");

  const f_caEligible = F("f_caEligible");

  // discipline / tardiness
  const tardyMonthEl = document.getElementById("tardyMonth");
  const tardyYearEl = document.getElementById("tardyYear");
  const tardyTotalEl = document.getElementById("tardyTotal");
  const tardyLateDaysEl = document.getElementById("tardyLateDays");
  const disciplineTbody = document.getElementById("disciplineTbody");

  // state
  let selectedEmpId = null;
  let selectedIds = new Set();
  let assignmentFilter = "All";
  let filterStatusId = "";
  let filterDept = "";
  let statusLabelMap = new Map();

  // ===== Drawer helpers =====
  function hidePLInfo() {
    if (plInfoWrap) plInfoWrap.style.display = "none";
    if (f_plCount) f_plCount.textContent = "";
    if (f_plBadge) {
      f_plBadge.textContent = "";
      f_plBadge.classList.remove("pl-pill--low");
    }
  }

  function fieldWrap(el) {
    if (!el) return null;
    return el.closest ? (el.closest(".field") || null) : null;
  }

  const VALIDATION_FIELDS = [
    [f_empId, errEmpId],
    [f_first, errFirst],
    [f_last, errLast],
    [posDd, errPositions],
    [f_externalArea, errExternalArea],
    [f_externalPosition, errExternalPosition],
    [f_rate, errRate],
    [f_allowance, errAllowance],
    [f_bankName, errBankName],
    [f_accountName, errAccountName],
    [f_accountNumber, errAccountNumber],
  ];

  function clearFormErrors() {
    VALIDATION_FIELDS.forEach(([el, errEl]) => {
      const w = fieldWrap(el) || el;
      if (w?.classList) w.classList.remove("is-invalid");
      if (errEl) errEl.textContent = "";
    });
  }

  function setFieldError(el, errEl, message) {
    const w = fieldWrap(el) || el;
    if (w?.classList) w.classList.add("is-invalid");
    if (errEl) errEl.textContent = String(message || "");
  }

  function clearFieldError(el, errEl) {
    const w = fieldWrap(el) || el;
    if (w?.classList) w.classList.remove("is-invalid");
    if (errEl) errEl.textContent = "";
  }

  function focusFirstError() {
    const first = document.querySelector(".drawer .field.is-invalid");
    if (!first) return;
    const focusable = first.querySelector("input, select, textarea, button, [tabindex]");
    if (focusable && typeof focusable.focus === "function") focusable.focus();
  }

  // Clear validation errors as user fixes inputs
  [
    [f_first, errFirst, "input"],
    [f_last, errLast, "input"],
    [f_externalArea, errExternalArea, "change"],
    [f_externalPosition, errExternalPosition, "change"],
    [f_rate, errRate, "input"],
    [f_allowance, errAllowance, "input"],
    [f_bankName, errBankName, "input"],
    [f_accountName, errAccountName, "input"],
    [f_accountNumber, errAccountNumber, "input"],
  ].forEach(([el, errEl, ev]) => {
    if (!el) return;
    el.addEventListener(ev, () => clearFieldError(el, errEl));
  });

  function openDrawer(title, subtitle) {
    if (!drawer) return;
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    if (drawerOverlay) drawerOverlay.removeAttribute("hidden");
    if (drawerTitleEl) drawerTitleEl.textContent = title;
    if (drawerSubEl) drawerSubEl.textContent = subtitle;
    if (deleteBtn) deleteBtn.style.display = selectedEmpId ? "inline-flex" : "none";
    const form = drawer.querySelector(".form");
    if (form) form.scrollTop = 0;
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    if (drawerOverlay) drawerOverlay.setAttribute("hidden", "");
    selectedEmpId = null;
  }

  function openHistoryDrawer(emp) {
    if (!historyDrawer) return;
    if (historyDrawerTitleEl) historyDrawerTitleEl.textContent = "Area Assignment History";
    if (historyDrawerSubEl) historyDrawerSubEl.textContent = `${fullName(emp)} — ${emp.empId}`;
    if (historyDrawerExternalEl) {
      const ext = emp.externalArea || "";
      historyDrawerExternalEl.textContent = ext ? `External — ${ext}` : "";
      historyDrawerExternalEl.style.display = ext ? "" : "none";
    }
    historyDrawer.classList.add("is-open");
    historyDrawer.setAttribute("aria-hidden", "false");
    if (historyDrawerOverlay) historyDrawerOverlay.removeAttribute("hidden");
  }

  function closeHistoryDrawer() {
    if (!historyDrawer) return;
    historyDrawer.classList.remove("is-open");
    historyDrawer.setAttribute("aria-hidden", "true");
    if (historyDrawerOverlay) historyDrawerOverlay.setAttribute("hidden", "");
  }

  function showToast(message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add("is-show");
    setTimeout(() => {
      toastEl.classList.remove("is-show");
    }, 1600);
  }

  function flushPendingToast() {
    const msg = sessionStorage.getItem("emp_records_toast");
    if (msg) {
      sessionStorage.removeItem("emp_records_toast");
      showToast(msg);
    }
  }

  function onlyDigits(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function formatWithGroups(value, groups) {
    const digits = onlyDigits(value);
    if (!digits) return "";
    const parts = [];
    let idx = 0;
    for (const size of groups) {
      if (idx >= digits.length) break;
      parts.push(digits.slice(idx, idx + size));
      idx += size;
    }
    if (idx < digits.length) parts.push(digits.slice(idx));
    return parts.join("-");
  }

  function formatTIN(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 12), [3, 3, 3, 3]);
  }

  function formatSSS(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 10), [2, 7, 1]);
  }

  function formatPhilHealth(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 12), [2, 9, 1]);
  }

  function formatPagibig(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 12), [4, 4, 4]);
  }

  function formatMinutes(value) {
    const n = Math.max(0, Number(value || 0));
    if (!Number.isFinite(n)) return "0m";
    const hours = Math.floor(n / 60);
    const mins = Math.round(n % 60);
    if (hours <= 0) return `${mins}m`;
    return `${hours}h ${String(mins).padStart(2, "0")}m`;
  }

  function resetDisciplineUI() {
    if (tardyMonthEl) tardyMonthEl.textContent = "—";
    if (tardyYearEl) tardyYearEl.textContent = "—";
    if (tardyTotalEl) tardyTotalEl.textContent = "—";
    if (tardyLateDaysEl) tardyLateDaysEl.textContent = "— late days";
    if (disciplineTbody) {
      disciplineTbody.innerHTML = `<tr><td colspan="4" class="muted small">No records yet.</td></tr>`;
    }
  }

  // ===== Philippine Address (PSGC) =====
  const PSGC = "https://psgc.gitlab.io/api";
  let _psgcProvinces = null;
  const _psgcCityCache = {};
  const _psgcBrgyCache = {};

  async function psgcProvinces() {
    if (_psgcProvinces) return _psgcProvinces;
    const res = await fetch(`${PSGC}/provinces/`);
    const data = await res.json();
    data.sort((a, b) => a.name.localeCompare(b.name));
    _psgcProvinces = data;
    return data;
  }

  async function psgcCities(provinceCode) {
    if (_psgcCityCache[provinceCode]) return _psgcCityCache[provinceCode];
    const res = await fetch(`${PSGC}/provinces/${provinceCode}/cities-municipalities/`);
    const data = await res.json();
    data.sort((a, b) => a.name.localeCompare(b.name));
    _psgcCityCache[provinceCode] = data;
    return data;
  }

  async function psgcBarangays(cityCode) {
    if (_psgcBrgyCache[cityCode]) return _psgcBrgyCache[cityCode];
    const res = await fetch(`${PSGC}/cities-municipalities/${cityCode}/barangays/`);
    const data = await res.json();
    data.sort((a, b) => a.name.localeCompare(b.name));
    _psgcBrgyCache[cityCode] = data;
    return data;
  }

  function fillSelect(el, items, selectedName) {
    while (el.options.length > 1) el.remove(1);
    items.forEach(item => el.add(new Option(item.name, item.code)));
    if (selectedName) {
      const match = Array.from(el.options).find(o => o.text === selectedName);
      if (match) el.value = match.value;
    }
  }

  function resetAddressDropdowns() {
    if (f_addrProvince) { f_addrProvince.value = ""; while (f_addrProvince.options.length > 1) f_addrProvince.remove(1); }
    if (f_addrCity) { f_addrCity.innerHTML = '<option value="">— Select City / Municipality —</option>'; f_addrCity.disabled = true; }
    if (f_addrBarangay) { f_addrBarangay.innerHTML = '<option value="">— Select Barangay —</option>'; f_addrBarangay.disabled = true; }
    if (f_addrStreet) f_addrStreet.value = "";
  }

  async function initAddressDropdowns(province = "", city = "", barangay = "") {
    try {
      const provinces = await psgcProvinces();
      if (f_addrProvince && f_addrProvince.options.length <= 1) {
        fillSelect(f_addrProvince, provinces, province);
      } else if (f_addrProvince && province) {
        const match = Array.from(f_addrProvince.options).find(o => o.text === province);
        if (match) f_addrProvince.value = match.value;
      } else if (f_addrProvince) {
        f_addrProvince.value = "";
      }

      if (f_addrCity) { f_addrCity.innerHTML = '<option value="">— Select City / Municipality —</option>'; f_addrCity.disabled = true; }
      if (f_addrBarangay) { f_addrBarangay.innerHTML = '<option value="">— Select Barangay —</option>'; f_addrBarangay.disabled = true; }

      const provinceCode = f_addrProvince?.value;
      if (!provinceCode) {
        if (f_addrCity) { f_addrCity.innerHTML = '<option value="">— Select City / Municipality —</option>'; f_addrCity.disabled = true; }
        if (f_addrBarangay) { f_addrBarangay.innerHTML = '<option value="">— Select Barangay —</option>'; f_addrBarangay.disabled = true; }
        return;
      }

      const cities = await psgcCities(provinceCode);
      if (f_addrCity) { fillSelect(f_addrCity, cities, city); f_addrCity.disabled = false; }

      const cityCode = f_addrCity?.value;
      if (!cityCode) return;

      const barangays = await psgcBarangays(cityCode);
      if (f_addrBarangay) { fillSelect(f_addrBarangay, barangays, barangay); f_addrBarangay.disabled = false; }
    } catch (e) {
      // Network error — address dropdowns remain empty, user can still save without address
    }
  }

  function formatMobile(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 11), [4, 3, 4]);
  }

  function formatAccount(value) {
    const digits = onlyDigits(value).slice(0, 20);
    return digits.replace(/(.{4})/g, "$1-").replace(/-$/, "");
  }

  function normalizePosName(name) {
    return String(name || "").toLowerCase().replace(/\s+/g, " ").trim();
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function filterPositionsList(qRaw) {
    if (!posDdList) return;
    const q = normalizePosName(qRaw);
    const items = Array.from(posDdList.querySelectorAll(".ddcheck__item"));
    let visible = 0;
    items.forEach((el) => {
      const name = el.getAttribute("data-name") || "";
      const ok = !q || name.includes(q);
      el.style.display = ok ? "" : "none";
      if (ok) visible += 1;
    });

    const empty = posDdList.querySelector(".ddcheck__empty");
    if (empty) {
      empty.style.display = visible ? "none" : "";
      empty.textContent = q ? "No matching positions." : empty.textContent;
    }
  }

  function renderPositionsDropdown() {
    if (!posDdList) return;
    const items = (positionsCatalog || [])
      .filter(p => p && p.id && p.name)
      .slice()
      .sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: "base" }));
    if (!items.length) {
      posDdList.innerHTML = `<div class="ddcheck__empty">No positions found. If this is your first time, run <code>php artisan migrate</code>.</div>`;
      return;
    }
    posDdList.innerHTML = items.map((p) => {
      const id = Number(p.id);
      const name = String(p.name);
      return `
        <label class="ddcheck__item" for="pos_${id}" data-name="${escapeHtml(normalizePosName(name))}">
          <input type="checkbox" id="pos_${id}" value="${id}" />
          <span>${escapeHtml(name)}</span>
        </label>
      `;
    }).join("");

    // placeholder for "no matches" state
    posDdList.insertAdjacentHTML("beforeend", `<div class="ddcheck__empty" style="display:none">No matching positions.</div>`);
    filterPositionsList(posSearch?.value || "");
  }

  function selectedPositionIds() {
    if (!posDdList) return [];
    const ids = Array.from(posDdList.querySelectorAll('input[type="checkbox"]:checked'))
      .map((el) => Number(el.value))
      .filter(Number.isFinite);
    return Array.from(new Set(ids));
  }

  function selectedPositionNamesByIds(ids) {
    const map = new Map((positionsCatalog || []).map((p) => [Number(p.id), String(p.name || "")]));
    return (ids || []).map((id) => map.get(Number(id)) || "").filter(Boolean);
  }

  function updatePositionsButton(ids) {
    if (!posDdBtn) return;
    const names = selectedPositionNamesByIds(ids);
    if (!names.length) {
      posDdBtn.textContent = "Select position(s)";
      return;
    }
    const label = names.join(", ");
    posDdBtn.textContent = label.length > 45 ? `${names.length} selected` : label;
  }

  function setSelectedPositionIds(ids) {
    if (!posDdList) return;
    const wanted = new Set((ids || []).map((v) => Number(v)).filter(Number.isFinite));
    Array.from(posDdList.querySelectorAll('input[type="checkbox"]')).forEach((el) => {
      el.checked = wanted.has(Number(el.value));
    });
    updatePositionsButton(Array.from(wanted));
  }

  function inferPositionIdsFromText(text) {
    const raw = String(text || "").trim();
    if (!raw) return [];

    const nameToId = new Map();
    (positionsCatalog || []).forEach((p) => {
      const id = Number(p?.id);
      const name = normalizePosName(p?.name);
      if (!Number.isFinite(id) || !name) return;
      nameToId.set(name, id);
    });

    const parts = raw.split(",").map((s) => normalizePosName(s)).filter(Boolean);
    const ids = parts.map((n) => nameToId.get(n)).filter((v) => Number.isFinite(v));
    if (ids.length) return Array.from(new Set(ids));

    const single = nameToId.get(normalizePosName(raw));
    return Number.isFinite(single) ? [single] : [];
  }

  function clearForm() {
    empForm?.reset();
    clearFormErrors();
    if (f_assignmentType) f_assignmentType.value = "G5-Davao";
    if (f_areaPlace) f_areaPlace.value = "";
    if (f_dept) f_dept.value = "";
    if (f_basedLocation) f_basedLocation.value = "";
    if (f_externalArea) f_externalArea.value = "";
    if (f_externalPosition) f_externalPosition.value = "";
    setSelectedPositionIds([]);
    resetAddressDropdowns();
    if (f_status && f_status.options.length) f_status.value = f_status.options[0].value;
    hidePLInfo();
    resetDisciplineUI();
    syncAssignmentUI();
    syncCashAdvanceFields();
    syncSalaryFields();
    syncPayoutFields();
  }

  function fillForm(emp) {
    if (!emp) return;
    clearFormErrors();
    hidePLInfo();
    f_empId && (f_empId.value = emp.empId || "");
    if (f_status) {
      if (emp.statusId) {
        f_status.value = String(emp.statusId);
      } else {
        const match = Array.from(f_status.options).find(opt => opt.text === (emp.status || ""));
        if (match) f_status.value = match.value;
      }
    }
    f_first && (f_first.value = emp.first || "");
    f_last && (f_last.value = emp.last || "");
    f_middle && (f_middle.value = emp.middle || "");
    f_bday && (f_bday.value = emp.birthday || "");
    f_mobile && (f_mobile.value = formatMobile(emp.mobile || ""));
    f_email && (f_email.value = emp.email || "");
    if (f_addrStreet) f_addrStreet.value = emp.addrStreet || "";
    initAddressDropdowns(emp.addrProvince || "", emp.addrCity || "", emp.addrBarangay || "");
    if (f_dept) {
      const dept = String(emp.dept || "");
      if (dept && !Array.from(f_dept.options).some(o => o.value === dept)) {
        const opt = document.createElement("option");
        opt.value = dept;
        opt.textContent = dept;
        f_dept.appendChild(opt);
      }
      f_dept.value = dept;
    }
    if (f_basedLocation) {
      const loc = String(emp.basedLocation || "");
      if (loc && !Array.from(f_basedLocation.options).some(o => o.value === loc)) {
        const opt = document.createElement("option");
        opt.value = loc;
        opt.textContent = loc;
        f_basedLocation.appendChild(opt);
      }
      f_basedLocation.value = loc;
    }

    const ids = (emp.positionIds && emp.positionIds.length)
      ? emp.positionIds
      : inferPositionIdsFromText(emp.position || "");
    setSelectedPositionIds(ids);

    f_type && (f_type.value = emp.type || "");
    f_hired && (f_hired.value = emp.hired || "");
    f_payType && (f_payType.value = emp.payType || "");
    f_rate && (f_rate.value = emp.rate ?? "");
    f_allowance && (f_allowance.value = emp.allowance ?? 0);

    if (f_assignmentType) {
      const assign = String(emp.assignmentType || "");
      if (assign && !Array.from(f_assignmentType.options).some(o => o.value === assign)) {
        const opt = document.createElement("option");
        opt.value = assign;
        opt.textContent = assign;
        f_assignmentType.appendChild(opt);
      }
      f_assignmentType.value = assign || f_assignmentType.value || "G5-Davao";
    }
    if (f_areaPlace) setSelectValuePreserveOption(f_areaPlace, emp.areaPlace || "");
    if (f_externalArea) f_externalArea.value = emp.externalArea || "";
    if (f_externalPosition) f_externalPosition.value = String(emp.externalPositionId || "");

    f_sss && (f_sss.value = formatSSS(emp.sss || ""));
    f_ph && (f_ph.value = formatPhilHealth(emp.ph || ""));
    f_pagibig && (f_pagibig.value = formatPagibig(emp.pagibig || ""));
    f_tin && (f_tin.value = formatTIN(emp.tin || ""));
    f_bankName && (f_bankName.value = emp.bankName || "");
    f_accountName && (f_accountName.value = emp.accountName || "");
    f_accountNumber && (f_accountNumber.value = formatAccount(emp.accountNumber || ""));

    syncAssignmentUI();
    syncCashAdvanceFields();
    syncSalaryFields();
    syncPayoutFields();
    loadTardiness(emp.empId);
    loadDiscipline(emp.empId);

    // If Field employee has an empty area_place (but history exists), auto-resolve latest from history.
    if (
      String(emp.assignmentType || "") === "Field" &&
      f_areaPlace &&
      !String(f_areaPlace.value || "").trim()
    ) {
      const thisEmp = String(emp.empId || "");
      resolveLatestAreaPlace(thisEmp)
        .then((area) => {
          if (!area) return;
          if (String(selectedEmpId || "") !== thisEmp) return;
          if (String(f_assignmentType?.value || "") !== "Field") return;
          if (String(f_areaPlace.value || "").trim()) return;
          setSelectValuePreserveOption(f_areaPlace, area);
          syncAssignmentUI();
        })
        .catch(() => { /* ignore */ });
    }
  }

  function collectForm() {
    const statusOption = f_status ? f_status.options[f_status.selectedIndex] : null;
    const statusLabel = statusOption ? statusOption.text.trim() : "";
    return {
      empId: f_empId?.value?.trim(),
      status: statusLabel,
      statusId: f_status?.value?.trim(),
      first: f_first?.value?.trim(),
      last: f_last?.value?.trim(),
      middle: f_middle?.value?.trim(),
      dept: f_dept?.value?.trim(),
      basedLocation: f_basedLocation?.value?.trim(),
      birthday: f_bday?.value || "",
      mobile: onlyDigits(f_mobile?.value),
      email: f_email?.value?.trim(),
      addrProvince: f_addrProvince?.value ? (f_addrProvince.options[f_addrProvince.selectedIndex]?.text || "") : "",
      addrCity: f_addrCity?.value ? (f_addrCity.options[f_addrCity.selectedIndex]?.text || "") : "",
      addrBarangay: f_addrBarangay?.value ? (f_addrBarangay.options[f_addrBarangay.selectedIndex]?.text || "") : "",
      addrStreet: f_addrStreet?.value?.trim() || "",
      positionIds: selectedPositionIds(),
      type: f_type?.value?.trim(),
      hired: f_hired?.value || "",
      payType: f_payType?.value?.trim(),
      rate: Number(f_rate?.value || 0),
      allowance: Number(f_allowance?.value || 0),

      assignmentType: f_assignmentType?.value?.trim(),
      areaPlace: f_areaPlace?.value?.trim(),
      externalArea: f_externalArea?.value?.trim(),
      externalPositionId: f_externalPosition?.value ? Number(f_externalPosition.value) : null,

      sss: onlyDigits(f_sss?.value),
      ph: onlyDigits(f_ph?.value),
      pagibig: onlyDigits(f_pagibig?.value),
      tin: onlyDigits(f_tin?.value),

      bankName: f_bankName?.value?.trim(),
      accountName: f_accountName?.value?.trim(),
      accountNumber: onlyDigits(f_accountNumber?.value),
    };
  }

  // ===== Helpers =====
  function fullName(emp) {
    return `${emp.last}, ${emp.first}${emp.middle ? " " + emp.middle : ""}`;
  }
  function money(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return "&#8369;";
    return `&#8369; ${num.toLocaleString()}`;
  }
  function moneyText(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return "₱";
    return `₱ ${num.toLocaleString()}`;
  }
  function salaryTotal(emp) {
    return Number(emp.rate || 0) + Number(emp.allowance || 0);
  }
  function maskAccount(acct) {
    const raw = String(acct || "").replace(/\s+/g, "");
    if (!raw) return "";
    return `****${raw.slice(-4)}`;
  }
  function payoutMethod(emp) {
    return (emp.accountNumber || "").trim() ? "BANK" : "CASH";
  }
  function assignmentText(emp) {
    const t = (emp.assignmentType || "").trim();
    if (!t) return "-";
    const place = (emp.areaPlace || "").trim();
    return place ? `${t} (${place})` : `${t}`;
  }
  function govShort(emp) {
    const parts = [];
    if (emp.sss) parts.push("SSS");
    if (emp.ph) parts.push("PH");
    if (emp.pagibig) parts.push("PAG");
    if (emp.tin) parts.push("TIN");
    return parts.length ? parts.join(", ") : "-";
  }
  function computeCashAdvance(empType) {
    const isRegular = String(empType || "").toLowerCase() === "regular";
    return { eligible: isRegular ? "Eligible" : "Not eligible" };
  }

  function exportEmployeesToXlsx(list, filename) {
    if (!list.length) return;
    const xlsx = window.XLSX;
    if (!xlsx) {
      alert("XLSX library not loaded. Please refresh the page.");
      return;
    }
    const header = [
      "Employee ID",
      "Status",
      "First Name",
      "Last Name",
      "Middle Name",
      "Birthday",
      "Mobile",
      "Email",
      "Province",
      "City / Municipality",
      "Barangay",
      "Street / House No.",
      "Department",
      "Position",
      "Employment Type",
      "Date Hired",
      "Pay Type",
      "Basic Pay",
      "Allowance",
      "Total Salary",
      "Assignment Type",
      "Area Place",
      "External Area",
      "Cash Advance Eligible",
      "SSS",
      "PhilHealth",
      "Pag-IBIG",
      "TIN",
      "Bank Name",
      "Bank Account Name",
      "Bank Account Number",
      "Payout Method",
    ];
    const rows = list.map(emp => {
      const ca = computeCashAdvance(emp.type);
      return [
        emp.empId,
        emp.status || "",
        emp.first,
        emp.last,
        emp.middle || "",
        emp.birthday || "",
        emp.mobile || "",
        emp.email || "",
        emp.addrProvince || "",
        emp.addrCity || "",
        emp.addrBarangay || "",
        emp.addrStreet || "",
        emp.dept || "",
        emp.position || "",
        emp.type || "",
        emp.hired || "",
        emp.payType || "",
        Number(emp.rate || 0),
        Number(emp.allowance || 0),
        salaryTotal(emp),
        emp.assignmentType || "",
        emp.areaPlace || "",
        emp.externalArea || "",
        ca.eligible || "",
        emp.sss || "",
        emp.ph || "",
        emp.pagibig || "",
        emp.tin || "",
        emp.bankName || "",
        emp.accountName || "",
        emp.accountNumber || "",
        payoutMethod(emp),
      ];
    });

    const ws = xlsx.utils.aoa_to_sheet([header, ...rows]);
    const wb = xlsx.utils.book_new();
    xlsx.utils.book_append_sheet(wb, ws, "Employees");
    xlsx.writeFile(wb, filename, { bookType: "xlsx" });
  }


  function populateAreaPlaces(selectEl, assignmentGroup) {
    if (!selectEl) return;
    const group = assignmentGroup || f_assignmentType?.value || "";
    const places = (areaPlaces[group] || [])
      .map(p => String(p || "").trim())
      .filter(Boolean)
      .slice()
      .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));
    const current = String(selectEl.value || "").trim();
    selectEl.innerHTML =
      `<option value="">-- Select --</option>` +
      places.map(p => `<option value="${p}">${p}</option>`).join("");

    if (current) {
      const has = Array.from(selectEl.options).some(o => String(o.value) === current);
      if (!has) {
        const opt = document.createElement("option");
        opt.value = current;
        opt.textContent = current;
        selectEl.appendChild(opt);
      }
      selectEl.value = current;
    }
  }

  function setSelectValuePreserveOption(selectEl, value) {
    if (!selectEl) return;
    const v = String(value || "").trim();
    if (!v) {
      selectEl.value = "";
      return;
    }
    const has = Array.from(selectEl.options).some(o => String(o.value) === v);
    if (!has) {
      const opt = document.createElement("option");
      opt.value = v;
      opt.textContent = v;
      selectEl.appendChild(opt);
    }
    selectEl.value = v;
  }

  async function resolveLatestAreaPlace(empNo) {
    const rows = await apiFetch(`/employees/${encodeURIComponent(empNo)}/area-history`);
    const first = Array.isArray(rows) ? rows[0] : null;
    return String(first?.area_place || "").trim();
  }

  function populateExternalAreaPlaces(selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    const preferredOrder = ["Davao", "Tagum", "Field"];
    const keys = [
      ...preferredOrder.filter(k => Object.prototype.hasOwnProperty.call(areaPlaces, k)),
      ...Object.keys(areaPlaces).filter(k => !preferredOrder.includes(k)),
    ];
    const seen = new Set();
    const places = [];
    keys.forEach((k) => {
      (areaPlaces[k] || []).forEach((p) => {
        const label = String(p || "").trim();
        if (!label) return;
        const key = label.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        places.push(label);
      });
    });

    selectEl.innerHTML =
      `<option value="">-- Select --</option>` +
      places.map(p => `<option value="${p}">${p}</option>`).join("");
    if (places.includes(current)) selectEl.value = current;
  }

  function populateExternalPositions(selectEl) {
    if (!selectEl) return;
    const current = String(selectEl.value || "");
    const items = (externalPositionsCatalog || [])
      .filter(p => p && p.id && p.name)
      .slice()
      .sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: "base" }));
    if (!items.length) {
      selectEl.innerHTML = `<option value="">-- Select external position --</option>` +
        `<option value="" disabled>(No external positions found — run php artisan migrate)</option>`;
      return;
    }
    selectEl.innerHTML =
      `<option value="">-- Select external position --</option>` +
      items.map(p => `<option value="${Number(p.id)}">${escapeHtml(String(p.name))}</option>`).join("");
    if (items.some(p => String(p.id) === current)) {
      selectEl.value = current;
    }
  }

  function populateAreaFilterPlaces(selectEl) {
    if (!selectEl) return;
    // Filter panel shows only Field area places (for the Area Place filter dropdown)
    const places = areaPlaces["Field"] || [];
    const current = selectEl.value;
    selectEl.innerHTML =
      `<option value="All" selected>All</option>` +
      places.map(p => `<option value="${p}">${p}</option>`).join("");
    if (current && (current === "All" || places.includes(current))) {
      selectEl.value = current;
    }
  }

  function syncAssignmentUI() {
    if (!f_assignmentType || !f_areaPlace) return;
    const type = f_assignmentType.value;
    const places = Array.isArray(areaPlaces[type]) ? areaPlaces[type] : [];
    const currentVal = String(f_areaPlace.value || "").trim();
    const showAreaPlace = (!!type && places.length > 0) || !!currentVal;
    if (showAreaPlace) {
      populateAreaPlaces(f_areaPlace, type);
      f_areaPlace.disabled = false;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "";
    } else {
      f_areaPlace.disabled = true;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "none";
    }
    syncExternalAreaUI();
  }

  function syncExternalAreaUI() {
    const isRegular = String(f_type?.value || "").toLowerCase() === "regular";
    const show = isRegular;
    if (f_externalArea) {
      if (show) {
        populateExternalAreaPlaces(f_externalArea);
        f_externalArea.disabled = false;
      } else {
        f_externalArea.value = "";
        f_externalArea.disabled = true;
      }
    }
    if (externalAreaWrap) externalAreaWrap.style.display = show ? "" : "none";

    if (f_externalPosition) {
      if (show) {
        populateExternalPositions(f_externalPosition);
        f_externalPosition.disabled = false;
      } else {
        f_externalPosition.value = "";
        f_externalPosition.disabled = true;
      }
    }
    if (externalPositionWrap) externalPositionWrap.style.display = show ? "" : "none";
  }

  async function fetchAndShowPLBalance(empNo) {
    if (plInfoWrap) plInfoWrap.style.display = "none";
    try {
      const data = await apiFetch(`/employees/${encodeURIComponent(empNo)}/pl-balance`);
      if (!data.applicable) return;
      if (f_plCount) f_plCount.textContent = `${data.remaining}/${data.total}`;
      if (f_plBadge) {
        f_plBadge.textContent = `${data.year} PL ${data.remaining}/${data.total}`;
        f_plBadge.classList.toggle("pl-pill--low", data.remaining <= 0);
      }
      if (plInfoWrap) plInfoWrap.style.display = "";
    } catch { /* silent */ }
  }

  function toLocalDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function fmtShortDate(isoStr) {
    const [y, m, d] = isoStr.split("-");
    return `${m}-${d}-${y.slice(2)}`;
  }

  function dayBefore(isoStr) {
    const d = new Date(isoStr + "T00:00:00");
    d.setDate(d.getDate() - 1);
    return toLocalDateStr(d);
  }

  function renderHistoryRows() {
    if (!areaHistoryList) return;
    const q = (historySearch ? historySearch.value : "").trim().toLowerCase();
    const filtered = q
      ? allHistoryRanges.filter(r =>
          r.area.toLowerCase().includes(q) || r.period.toLowerCase().includes(q)
        )
      : allHistoryRanges;
    if (!filtered.length) {
      areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">No results found.</td></tr>';
      return;
    }
    areaHistoryList.innerHTML = filtered.map(r =>
      `<tr>
        <td>${r.period}</td>
        <td style="font-weight:600;">${r.area}</td>
      </tr>`
    ).join('');
  }

  async function loadAreaHistory(empNo) {
    if (!areaHistoryList) return;
    allHistoryRanges = [];
    if (historySearch) historySearch.value = "";
    areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">Loading...</td></tr>';
    try {
      const rows = await apiFetch(`/employees/${encodeURIComponent(empNo)}/area-history`);
      if (!rows.length) {
        areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">No history yet.</td></tr>';
        return;
      }

      const asc = [...rows].sort((a, b) => a.effective_date.localeCompare(b.effective_date));
      const todayStr = toLocalDateStr(new Date());

      const ranges = asc.map((entry, i) => {
        const start = entry.effective_date;
        const end = i < asc.length - 1 ? dayBefore(asc[i + 1].effective_date) : todayStr;
        const period = start === end
          ? fmtShortDate(start)
          : `${fmtShortDate(start)} to ${fmtShortDate(end)}`;
        return { period, area: entry.area_place };
      });

      allHistoryRanges = ranges.reverse(); // most recent first
      renderHistoryRows();
    } catch {
      areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">Failed to load history.</td></tr>';
    }
  }

  function syncCashAdvanceFields() {
    if (!f_caEligible) return;
    const ca = computeCashAdvance(f_type?.value || "");
    f_caEligible.value = ca.eligible;
  }

  function syncSalaryFields() {
    if (!f_totalSalary) return;
    const total = Number(f_rate?.value || 0) + Number(f_allowance?.value || 0);
    f_totalSalary.value = moneyText(total);
  }

  function syncPayoutFields() {
    if (!f_payoutMethod) return;
    const hasAcct = (f_accountNumber?.value || "").trim().length > 0;
    f_payoutMethod.value = hasAcct ? "BANK" : "CASH";
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
      if (bulkAreaPlaceSelect) {
        bulkAreaPlaceSelect.value = "";
        bulkAreaPlaceSelect.style.display = "none";
      }
      if (bulkAssignApply) bulkAssignApply.disabled = true;
    } else if (bulkAssignSelect && bulkAssignApply) {
      bulkAssignApply.disabled = !bulkAssignSelect.value;
    }
  }

  // ===== Payroll Required Helpers =====
  function getPayrollMissing(emp) {
    const missing = [];

    if (PAYROLL_REQUIRED.requireBasicPay) {
      const r = Number(emp.rate || 0);
      if (!Number.isFinite(r) || r <= 0) missing.push("Basic Pay");
    }

    if (PAYROLL_REQUIRED.requireAssignment) {
      const t = (emp.assignmentType || "").trim();
      if (!t) missing.push("Assignment");
      if (t) {
        const needsAreaPlace = (areaPlaces[t] || []).length > 0;
        const ap = (emp.areaPlace || "").trim();
        if (needsAreaPlace && !ap) missing.push("Area Place");
        const isRegular = String(emp.type || "").toLowerCase() === "regular";
        if (isRegular && !(emp.externalArea || "").trim()) missing.push("External Area");
        if (isRegular && !emp.externalPositionId) missing.push("External Position");
      }
    }

    if (PAYROLL_REQUIRED.requireGovIds) {
      const req = PAYROLL_REQUIRED.govRequiredFields || [];
      const missingGov = req.filter(k => !(emp[k] || "").trim());
      if (missingGov.length) missing.push("Gov IDs");
    }

    return missing;
  }

  function isPayrollEligible(emp) {
    return getPayrollMissing(emp).length === 0;
  }

  function payrollBadgeHTML(emp) {
    const missing = getPayrollMissing(emp);

    if (!missing.length) {
      return `<span class="badge badge--ok">&#10003; Complete</span>`;
    }

    const badges = missing.map(m => {
      const bad = (m === "Gov IDs" || m === "Basic Pay");
      return `<span class="badge ${bad ? "badge--bad" : "badge--warn"}">&#9888; Missing ${m}</span>`;
    }).join("");

    return `<div class="badges">${badges}</div>`;
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
    let key = sortState.key;
    const dir = sortState.dir;
    const sign = dir === "asc" ? 1 : -1;

    if (!CAN_VIEW_COMP && (key === "basicPay" || key === "allowance" || key === "salary")) {
      key = "name";
    }

    copy.sort((a, b) => {
      let res = 0;
      if (key === "empId") res = String(a.empId).localeCompare(String(b.empId));
      else if (key === "name") res = fullName(a).localeCompare(fullName(b));
      else if (key === "dept") res = String(a.dept || "").localeCompare(String(b.dept || "")) || fullName(a).localeCompare(fullName(b));
      else if (key === "position") res = String(a.position || "").localeCompare(String(b.position || "")) || fullName(a).localeCompare(fullName(b));
      else if (key === "type") res = String(a.type || "").localeCompare(String(b.type || "")) || fullName(a).localeCompare(fullName(b));
        else if (key === "assignment") res = String(assignmentText(a)).localeCompare(String(assignmentText(b))) || fullName(a).localeCompare(fullName(b));
        else if (key === "basicPay") res = (Number(a.rate || 0) - Number(b.rate || 0)) || fullName(a).localeCompare(fullName(b));
        else if (key === "allowance") res = (Number(a.allowance || 0) - Number(b.allowance || 0)) || fullName(a).localeCompare(fullName(b));
      else if (key === "salary") res = (salaryTotal(a) - salaryTotal(b)) || fullName(a).localeCompare(fullName(b));
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

  // Client-side filters/search
  function applyFilters(list) {
    const q = normalize(searchInput?.value || "");
    const deptVal = deptFilter?.value || "";
    const statusVal = statusFilter?.value || "";
    const assignVal = assignmentFilter || "All";
    const areaVal = areaSubFilter || areaPlaceFilter?.value || "";

    return list.filter(emp => {
      const okDept = !deptVal || deptVal === "All" || (emp.dept || "") === deptVal;
      const okStatus = statusMatches(emp, statusVal);
      const okAssign = assignVal === "All" || !assignVal || (emp.assignmentType || "") === assignVal;
      const okArea = !areaVal || areaVal === "All" || (emp.areaPlace || "") === areaVal;

      const text = normalize(
        `${emp.empId} ${fullName(emp)} ${emp.dept || ""} ${emp.position || ""} ${emp.type || ""} ${emp.status || ""} ${assignmentText(emp)}`
      );
      const okSearch = !q || text.includes(q);

      return okDept && okStatus && okAssign && okArea && okSearch;
    });
  }

  function setPagerDisabled(el, disabled) {
    if (!el) return;
    if ("disabled" in el) el.disabled = disabled;
    el.classList.toggle("is-disabled", disabled);
    if (disabled) el.setAttribute("aria-disabled", "true");
    else el.removeAttribute("aria-disabled");
  }

  // ===== Render =====
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
    if (totalPagesEl) {
      totalPagesEl.textContent = totalPagesEl.id === "pageTotal" ? `/ ${totalPages}` : String(totalPages);
    }
    if (pageInputEl) pageInputEl.value = String(currentPage);

    setPagerDisabled(firstPageBtn, currentPage === 1);
    setPagerDisabled(prevPageBtn, currentPage === 1);
    setPagerDisabled(nextPageBtn, currentPage === totalPages);
    setPagerDisabled(lastPageBtn, currentPage === totalPages);

    tbody.innerHTML = "";

    pageList.forEach(emp => {
      const isChecked = selectedIds.has(emp.empId);

      const tr = document.createElement("tr");
      if (isChecked) tr.classList.add("is-selected");

      const compCols = CAN_VIEW_COMP ? `
          <td class="col-basicpay"><div class="salaryVal">${money(emp.rate || 0)}</div></td>
          <td><div class="salaryVal">${money(emp.allowance || 0)}</div></td>
        <td class="col-salary">
          <div class="salaryVal">${money(salaryTotal(emp))}</div>
        </td>
      ` : "";

      tr.innerHTML = `
        <td class="col-check">
          <input class="empCheck" type="checkbox" data-id="${emp.empId}" ${isChecked ? "checked" : ""} aria-label="Select employee ${emp.empId}">
        </td>
        <td>${emp.empId}</td>
        <td>${fullName(emp)}</td>
        <td>${escapeHtml(emp.dept || "-")}</td>
        <td>${emp.position || "-"}</td>
          <td>${assignmentText(emp)}</td>
        ${compCols}

        <!-- ? NEW -->
        <td>${payrollBadgeHTML(emp)}</td>
        <td class="actions">
          <button class="iconbtn" type="button" data-action="edit" data-id="${emp.empId}" title="Edit" aria-label="Edit">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 20h9"></path>
              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
            </svg>
          </button>
          ${emp.assignmentType === "Field" ? `<button class="iconbtn" type="button" data-action="history" data-id="${emp.empId}" title="Field History" aria-label="Field History">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <circle cx="12" cy="12" r="9"></circle>
              <path d="M12 7v5l3 3"></path>
            </svg>
          </button>` : ""}
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
  async function reloadEmployees() {
    await loadEmployees();
    render();
  }

  function shouldSkipAutoRefresh() {
    if (document.hidden) return true;
    if (drawer?.classList.contains("is-open")) return true;
    if (historyDrawer?.classList.contains("is-open")) return true;
    const active = document.activeElement;
    if (active && ["INPUT", "TEXTAREA", "SELECT"].includes(active.tagName)) return true;
    if (selectedIds.size > 0) return true;
    return false;
  }

  async function runAutoRefresh() {
    if (isAutoRefreshing || shouldSkipAutoRefresh()) return;
    isAutoRefreshing = true;
    try {
      const hb = await apiFetch("/employees/heartbeat");
      const next = hb ? `${hb.max_updated_at || ""}|${hb.total || 0}` : null;
      if (!lastHeartbeat) {
        lastHeartbeat = next;
        return;
      }
      if (next && next !== lastHeartbeat) {
        lastHeartbeat = next;
        await reloadEmployees();
      }
    } finally {
      isAutoRefreshing = false;
    }
  }

  [searchInput, deptFilter, statusFilter].forEach(el => {
    if (!el) return;
    const eventName = el === searchInput ? "input" : "change";
    el.addEventListener(eventName, () => {
      currentPage = 1;
      render();
    });
  });

  if (bulkAssignSelect && bulkAssignApply) {
    bulkAssignSelect.addEventListener("change", () => {
      const assignment = bulkAssignSelect.value;
      const places = areaPlaces[assignment] || [];
      const needsArea = places.length > 0;
      if (bulkAreaPlaceSelect) {
        if (needsArea) {
          populateAreaPlaces(bulkAreaPlaceSelect, assignment);
          bulkAreaPlaceSelect.style.display = "";
        } else {
          bulkAreaPlaceSelect.value = "";
          bulkAreaPlaceSelect.style.display = "none";
        }
      }
      bulkAssignApply.disabled = !assignment || (needsArea && !bulkAreaPlaceSelect?.value);
    });
  }

  bulkAreaPlaceSelect && bulkAreaPlaceSelect.addEventListener("change", () => {
    const assignment = bulkAssignSelect?.value || "";
    const needsArea = (areaPlaces[assignment] || []).length > 0;
    if (bulkAssignApply) {
      bulkAssignApply.disabled = !assignment || (needsArea && !bulkAreaPlaceSelect.value);
    }
  });

  bulkAssignApply && bulkAssignApply.addEventListener("click", async () => {
    const assignment = bulkAssignSelect?.value || "";
    if (!assignment || selectedIds.size === 0) return;

    const ids = Array.from(selectedIds);
    const needsArea = (areaPlaces[assignment] || []).length > 0;
    const areaPlace = needsArea ? (bulkAreaPlaceSelect?.value || "") : "";
    if (needsArea && !areaPlace) {
      alert("Please select an Area Place.");
      return;
    }
    try {
      await apiFetch("/employees/bulk-assign", {
        method: "POST",
        body: JSON.stringify({ ids, assignment, area_place: areaPlace || null }),
      });

        employees.forEach(emp => {
          if (selectedIds.has(emp.empId)) {
            emp.assignmentType = assignment;
            emp.areaPlace = areaPlace || "";
          }
        });

      selectedIds.clear();
      if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      }

      render();
      showToast("Assignment updated.");
      notifyEmployeeUpdated();
    } catch (err) {
      alert(err.message || "Failed to apply assignment.");
    }
  });

  const filterForm = document.querySelector(".filtersCard");
  if (filterForm) {
    filterForm.addEventListener("submit", (e) => e.preventDefault());
  }
  if (searchInput) {
    searchInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
      }
    });
    searchInput.addEventListener("search", () => {
      if (!searchInput.value) {
        currentPage = 1;
        render();
      }
    });
  }


  if (areaPlaceFilter) {
    populateAreaFilterPlaces(areaPlaceFilter);
    areaPlaceFilter.addEventListener("change", () => {
      currentPage = 1;
      render();
    });
  }

  function closeAllDropdowns() {
    if (!assignSeg) return;
    assignSeg.querySelectorAll(".seg__dropdown").forEach(d => {
      d.classList.remove("is-open");
      d.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function wireAssignButtons() {
    if (!assignSeg) return;
    assignBtns = Array.from(assignSeg.querySelectorAll(".seg__btn--emp"));
    const contentScroller = document.querySelector(".content");
    let rafId = 0;
    function positionDropdown(btn, dropdown) {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desiredMin = Math.round(rect.width);
      const maxWidth = Math.min(320, Math.max(200, viewportW - 16));
      const dropdownW = Math.max(desiredMin, maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) {
        left = Math.max(8, viewportW - dropdownW - 8);
      }
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${desiredMin}px`;
      dropdown.style.maxWidth = `${maxWidth}px`;
    }

    function refreshOpenDropdownPosition() {
      if (!openDropdown || !openDropdownBtn) return;
      if (rafId) cancelAnimationFrame(rafId);
      rafId = requestAnimationFrame(() => {
        positionDropdown(openDropdownBtn, openDropdown);
      });
    }

    assignBtns.forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const rawAssign = btn.getAttribute("data-assign");
        const group = rawAssign && rawAssign !== "" ? rawAssign : "All";
      const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
      const wasOpen = dropdown && dropdown.style.display === "block";

        // If clicking the already-active group btn, toggle dropdown open/close
        const isAlreadyActive = btn.classList.contains("is-active");
        closeAllDropdowns();

        // Activate this button
        assignBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        assignmentFilter = group;
        areaSubFilter = "";
        currentPage = 1;
        render();

      // Show its dropdown (toggle off if it was already active+open)
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

    // Wire dropdown items
    assignSeg.querySelectorAll(".seg__dropdown-item").forEach(item => {
      item.addEventListener("click", (e) => {
        e.stopPropagation();
        const place = item.getAttribute("data-place");
        const dropdown = item.closest(".seg__dropdown");
        // Mark active item
        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");
        areaSubFilter = place || "";
        closeAllDropdowns();
        currentPage = 1;
        render();
      });
    });

  // Close dropdowns on outside click
  document.addEventListener("click", (e) => {
    if (!assignSeg.contains(e.target)) closeAllDropdowns();
  }, { capture: true });

  // Keep dropdown aligned while scrolling/resizing
  window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  window.addEventListener("resize", refreshOpenDropdownPosition);
  contentScroller && contentScroller.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
}

  // pagination
  pageSizeEl && pageSizeEl.addEventListener("change", () => { currentPage = 1; render(); });
  firstPageBtn && firstPageBtn.addEventListener("click", (e) => {
    e.preventDefault();
    currentPage = 1;
    render();
  });
  prevPageBtn && prevPageBtn.addEventListener("click", (e) => {
    e.preventDefault();
    currentPage = Math.max(1, currentPage - 1);
    render();
  });
  nextPageBtn && nextPageBtn.addEventListener("click", (e) => {
    e.preventDefault();
    currentPage = currentPage + 1;
    render();
  });
  lastPageBtn && lastPageBtn.addEventListener("click", (e) => {
    e.preventDefault();
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
  tbody && tbody.addEventListener("click", async (e) => {
    const chk = e.target.closest("input.empCheck");
    if (chk) {
      const id = chk.getAttribute("data-id");
      if (chk.checked) selectedIds.add(id);
      else selectedIds.delete(id);
      const tr = chk.closest("tr");
      if (tr) tr.classList.toggle("is-selected", chk.checked);
      render();
      return;
    }

    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const id = btn.getAttribute("data-id");
    const action = btn.getAttribute("data-action");
    const emp = employees.find(x => x.empId === id);
    if (!emp) return;

    if (action === "edit") {
      selectedEmpId = id;
      fillForm(emp);
      openDrawer("Edit Employee", `${fullName(emp)} - ${emp.empId}`);
      if (String(emp.type || "").toLowerCase() === "regular" && !!(emp.assignmentType)) {
        fetchAndShowPLBalance(emp.empId);
      }
      loadCharges(id);
    }
    if (action === "history") {
      if (areaHistoryList) areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">Loading...</td></tr>';
      openHistoryDrawer(emp);
      loadAreaHistory(emp.empId);
    }
    if (action === "delete") {
      if (!confirm(`Delete employee ${fullName(emp)} (${emp.empId})?`)) return;
      try {
        await apiFetch(`/employees/${encodeURIComponent(id)}`, { method: "DELETE" });
        await loadEmployees();
        selectedIds.delete(id);
        render();
      } catch (err) {
        alert(err.message || "Failed to delete employee.");
      }
    }
  });

  // import discipline records
  importDisciplineBtn && importDisciplineBtn.addEventListener("click", () => {
    disciplineFileInput?.click();
  });

  disciplineFileInput && disciplineFileInput.addEventListener("change", async () => {
    const file = disciplineFileInput.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append("file", file);
    try {
      const res = await fetch("/employees/discipline-import", {
        method: "POST",
        headers: csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {},
        body: fd,
      });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        const msg = data?.message || "Import failed.";
        const errs = Array.isArray(data?.errors) ? `\n- ${data.errors.join("\n- ")}` : "";
        alert(`${msg}${errs}`);
      } else {
        showToast(data?.message || "Imported successfully.");
        if (selectedEmpId) {
          await loadDiscipline(selectedEmpId);
        }
      }
    } catch (err) {
      alert(err.message || "Import failed.");
    } finally {
      disciplineFileInput.value = "";
    }
  });

  // export
  exportBtn && exportBtn.addEventListener("click", () => {
    if (selectedIds.size === 0) {
      alert("Please select at least one employee to export.");
      return;
    }
    const selected = employees.filter(emp => selectedIds.has(emp.empId));
    if (!selected.length) {
      alert("No selected employees found.");
      return;
    }
    const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
    exportEmployeesToXlsx(selected, `employees_${ts}.xlsx`);
  });

  // drawer events
  openAddBtn && openAddBtn.addEventListener("click", async () => {
    selectedEmpId = null;
    clearForm();
    openDrawer("Add Employee", "Fill in the details then click Save.");
    // UX: focus first name immediately so user can start typing
    setTimeout(() => f_first?.focus(), 0);
    try {
      const res = await apiFetch("/employees/next-id");
      if (f_empId) f_empId.value = res.next_id;
    } catch (e) { /* user can still see it's empty */ }
    initAddressDropdowns();
  });

  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);
  closeHistoryDrawerBtn && closeHistoryDrawerBtn.addEventListener("click", closeHistoryDrawer);
  historyDrawerOverlay && historyDrawerOverlay.addEventListener("click", closeHistoryDrawer);
  historySearch && historySearch.addEventListener("input", renderHistoryRows);
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    closeDrawer();
    closeHistoryDrawer();
  });

  // live cash advance preview
  f_type && f_type.addEventListener("change", () => {
    syncCashAdvanceFields();
    syncExternalAreaUI();

    // PL allowance applies only to Regular employees.
    const isRegular = String(f_type?.value || "").toLowerCase() === "regular";
    if (!isRegular) {
      hidePLInfo();
    } else if (selectedEmpId && !!(f_assignmentType?.value)) {
      fetchAndShowPLBalance(selectedEmpId);
    }
  });
  f_rate && f_rate.addEventListener("input", () => {
    syncCashAdvanceFields();
    syncSalaryFields();
  });
  f_allowance && f_allowance.addEventListener("input", syncSalaryFields);
  f_accountNumber && f_accountNumber.addEventListener("input", syncPayoutFields);
  f_assignmentType && f_assignmentType.addEventListener("change", () => {
    // If user changes assignment, clear stale area place then re-sync options.
    if (f_areaPlace) f_areaPlace.value = "";
    syncAssignmentUI();
  });

  // Address cascade listeners
  f_addrProvince && f_addrProvince.addEventListener("change", async () => {
    if (f_addrCity) { f_addrCity.innerHTML = '<option value="">— Select City / Municipality —</option>'; f_addrCity.disabled = true; }
    if (f_addrBarangay) { f_addrBarangay.innerHTML = '<option value="">— Select Barangay —</option>'; f_addrBarangay.disabled = true; }
    const code = f_addrProvince.value;
    if (!code) return;
    try {
      const cities = await psgcCities(code);
      if (f_addrCity) { fillSelect(f_addrCity, cities, ""); f_addrCity.disabled = false; }
    } catch (e) {}
  });
  f_addrCity && f_addrCity.addEventListener("change", async () => {
    if (f_addrBarangay) { f_addrBarangay.innerHTML = '<option value="">— Select Barangay —</option>'; f_addrBarangay.disabled = true; }
    const code = f_addrCity.value;
    if (!code) return;
    try {
      const barangays = await psgcBarangays(code);
      if (f_addrBarangay) { fillSelect(f_addrBarangay, barangays, ""); f_addrBarangay.disabled = false; }
    } catch (e) {}
  });
  f_mobile && f_mobile.addEventListener("input", () => {
    const next = formatMobile(f_mobile.value);
    if (f_mobile.value !== next) f_mobile.value = next;
  });
  f_sss && f_sss.addEventListener("input", () => {
    const next = formatSSS(f_sss.value);
    if (f_sss.value !== next) f_sss.value = next;
  });
  f_ph && f_ph.addEventListener("input", () => {
    const next = formatPhilHealth(f_ph.value);
    if (f_ph.value !== next) f_ph.value = next;
  });
  f_pagibig && f_pagibig.addEventListener("input", () => {
    const next = formatPagibig(f_pagibig.value);
    if (f_pagibig.value !== next) f_pagibig.value = next;
  });
  f_tin && f_tin.addEventListener("input", () => {
    const next = formatTIN(f_tin.value);
    if (f_tin.value !== next) f_tin.value = next;
  });
  f_accountNumber && f_accountNumber.addEventListener("input", () => {
    const next = formatAccount(f_accountNumber.value);
    if (f_accountNumber.value !== next) f_accountNumber.value = next;
  });

  function capWords(value) {
    const v = String(value || "");
    if (!v) return v;
    return v.replace(/(^|[\s\-'])([a-z])/g, (m, p1, p2) => `${p1}${String(p2).toUpperCase()}`);
  }

  function capFirst(value) {
    const v = String(value || "");
    if (!v) return v;
    return v.charAt(0).toUpperCase() + v.slice(1);
  }

  [f_first, f_last, f_middle].forEach((el) => {
    if (!el) return;
    el.addEventListener("input", () => {
      const start = el.selectionStart;
      const end = el.selectionEnd;
      const next = capWords(el.value);
      if (el.value !== next) {
        el.value = next;
        if (typeof start === "number" && typeof end === "number") {
          el.setSelectionRange(start, end);
        }
      }
    });
  });

  // keep previous lightweight capitalization for other fields
  [f_addrStreet, f_bankName, f_accountName].forEach((el) => {
    if (!el) return;
    el.addEventListener("input", () => {
      const next = capFirst(el.value);
      if (el.value !== next) el.value = next;
    });
  });

  deleteBtn && deleteBtn.addEventListener("click", async () => {
    if (!selectedEmpId) return;
    const emp = employees.find(e => e.empId === selectedEmpId);
    if (!emp) return;
    if (!confirm(`Delete employee ${fullName(emp)} (${emp.empId})?`)) return;
    try {
      await apiFetch(`/employees/${encodeURIComponent(selectedEmpId)}`, { method: "DELETE" });
      await loadEmployees();
      selectedIds.delete(selectedEmpId);
      closeDrawer();
      render();
      notifyEmployeeUpdated();
    } catch (err) {
      alert(err.message || "Failed to delete employee.");
    }
  });

  empForm && empForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = collectForm();
    clearFormErrors();

    // required fields
    if (!/^\d{4}$/.test(String(data.empId || ""))) {
      setFieldError(f_empId, errEmpId, "Employee ID must be exactly 4 digits.");
    }
    if (!data.empId || !data.first || !data.last || !(data.positionIds || []).length) {
      if (!data.empId) setFieldError(f_empId, errEmpId, "Employee ID is required.");
      if (!data.first) setFieldError(f_first, errFirst, "First name is required.");
      if (!data.last) setFieldError(f_last, errLast, "Last name is required.");
      if (!(data.positionIds || []).length) setFieldError(posDd, errPositions, "At least one position is required.");
    }
    const isRegular = String(data.type || "").toLowerCase() === "regular";
    if (isRegular) {
      if (!data.externalArea) {
        setFieldError(f_externalArea, errExternalArea, "External Area is required for Regular employees.");
      }
      if (!data.externalPositionId) {
        setFieldError(f_externalPosition, errExternalPosition, "External Position is required for Regular employees.");
      }
    }
    if (CAN_VIEW_COMP) {
      if (!Number.isFinite(data.rate) || data.rate < 0) {
        setFieldError(f_rate, errRate, "Basic Pay must be a valid number.");
      }
      if (!Number.isFinite(data.allowance) || data.allowance < 0) {
        setFieldError(f_allowance, errAllowance, "Allowance must be a valid number.");
      }
    }
    if (data.accountNumber) {
      if (!data.bankName || !data.accountName) {
        if (!data.bankName) setFieldError(f_bankName, errBankName, "Bank Name is required when Account Number is provided.");
        if (!data.accountName) setFieldError(f_accountName, errAccountName, "Account Name is required when Account Number is provided.");
      }
    }

    const hasErrors = !!document.querySelector(".drawer .field.is-invalid");
    if (hasErrors) {
      focusFirstError();
      return;
    }

    const exists = employees.some(emp => emp.empId === data.empId);

    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.dataset.prevText = saveBtn.textContent || "Save";
      saveBtn.textContent = "Saving...";
    }

    try {
      let saved = null;
      if (selectedEmpId) {
        saved = await apiFetch(`/employees/${encodeURIComponent(selectedEmpId)}`, {
          method: "PUT",
          body: JSON.stringify(toApi(data)),
        });
      } else if (exists) {
        if (!confirm("Employee ID already exists. Overwrite?")) return;
        saved = await apiFetch(`/employees/${encodeURIComponent(data.empId)}`, {
          method: "PUT",
          body: JSON.stringify(toApi(data)),
        });
      } else {
        saved = await apiFetch("/employees", {
          method: "POST",
          body: JSON.stringify(toApi(data)),
        });
      }

      const successMsg = selectedEmpId ? "Employee updated successfully." : "Employee added successfully.";
      closeDrawer();
      showToast(successMsg);

      if (saved && typeof saved === "object" && saved.emp_no) {
        const mapped = fromApi(saved);
        const idx = employees.findIndex(e => String(e.empId) === String(mapped.empId));
        if (idx >= 0) employees[idx] = mapped;
        else employees.unshift(mapped);
      } else {
        // fallback (older endpoints)
        await loadEmployees();
      }

      render();
      notifyEmployeeUpdated();
    } catch (err) {
      alert(err.message || "Failed to save employee.");
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = saveBtn.dataset.prevText || "Save";
        delete saveBtn.dataset.prevText;
      }
    }
  });

  // Init
  syncAssignmentUI();
  syncCashAdvanceFields();
  syncSalaryFields();
  syncPayoutFields();

  // Positions dropdown checklist
  if ((!positionsCatalog || !positionsCatalog.length) && Array.isArray(window.__positions)) {
    positionsCatalog = window.__positions;
  }
  if ((!externalPositionsCatalog || !externalPositionsCatalog.length) && Array.isArray(window.__externalPositions)) {
    externalPositionsCatalog = window.__externalPositions;
  }
  renderPositionsDropdown();
  updatePositionsButton(selectedPositionIds());
  populateExternalPositions(f_externalPosition);
  syncExternalAreaUI();
  function closePosDd() {
    if (!posDdPanel || !posDdBtn) return;
    posDdPanel.setAttribute("hidden", "");
    posDdBtn.setAttribute("aria-expanded", "false");
  }
  function togglePosDd() {
    if (!posDdPanel || !posDdBtn) return;
    const isOpen = !posDdPanel.hasAttribute("hidden");
    if (isOpen) closePosDd();
    else {
      posDdPanel.removeAttribute("hidden");
      posDdBtn.setAttribute("aria-expanded", "true");
      posSearch && setTimeout(() => posSearch.focus(), 0);
    }
  }
  posDdBtn && posDdBtn.addEventListener("click", (e) => {
    e.preventDefault();
    togglePosDd();
  });
  posSearch && posSearch.addEventListener("input", () => {
    filterPositionsList(posSearch.value);
  });
  posDdList && posDdList.addEventListener("change", () => {
    const ids = selectedPositionIds();
    updatePositionsButton(ids);
    if (ids.length) clearFieldError(posDd, errPositions);
    if (posSearch && posSearch.value) {
      posSearch.value = "";
      filterPositionsList("");
      posSearch.focus();
    }
  });
  document.addEventListener("click", (e) => {
    if (!posDd || !posDdPanel) return;
    if (posDd.contains(e.target)) return;
    closePosDd();
  });

  assignmentFilter = currentAssignmentFilter();
  if (areaPlaceFilterWrap) {
    areaPlaceFilterWrap.style.display = "none";
  }
  wireHeaderSorting();
  updateSortIcons();
  async function loadFilters() {
    try {
      const data = await apiFetch("/employees/filters");

      if (Array.isArray(data.positions)) {
        const prev = selectedPositionIds();
        const q = posSearch?.value || "";
        positionsCatalog = data.positions;
        renderPositionsDropdown();
        setSelectedPositionIds(prev);
        if (posSearch) posSearch.value = q;
        filterPositionsList(q);
      }
      if (Array.isArray(data.external_positions)) {
        externalPositionsCatalog = data.external_positions;
        populateExternalPositions(f_externalPosition);
      }
      if (deptFilter) {
        const depts = (data.departments || [])
          .slice()
          .sort((a, b) => String(a).localeCompare(String(b), undefined, { sensitivity: "base" }));
        deptFilter.innerHTML = `<option value="">All</option>` +
          depts.map(d => `<option value="${d}">${d}</option>`).join("");
      }
      if (f_dept) {
        const current = String(f_dept.value || "");
        const items = (Array.isArray(data.departments) ? data.departments : [])
          .slice()
          .sort((a, b) => String(a).localeCompare(String(b), undefined, { sensitivity: "base" }));
        f_dept.innerHTML = `<option value="">-- Select --</option>` +
          items.map(d => `<option value="${d}">${d}</option>`).join("");
        if (current && items.includes(current)) {
          f_dept.value = current;
        } else if (current) {
          const opt = document.createElement("option");
          opt.value = current;
          opt.textContent = current;
          f_dept.appendChild(opt);
          f_dept.value = current;
        }
      }
      if (f_basedLocation) {
        const current = String(f_basedLocation.value || "");
        const items = (Array.isArray(data.based_locations) ? data.based_locations : [])
          .slice()
          .sort((a, b) => String(a).localeCompare(String(b), undefined, { sensitivity: "base" }));
        f_basedLocation.innerHTML = `<option value="">-- Select --</option>` +
          items.map(d => `<option value="${d}">${d}</option>`).join("");
        if (current && items.includes(current)) {
          f_basedLocation.value = current;
        } else if (current) {
          const opt = document.createElement("option");
          opt.value = current;
          opt.textContent = current;
          f_basedLocation.appendChild(opt);
          f_basedLocation.value = current;
        }
      }
      if (statusFilter) {
        statusFilter.innerHTML = `<option value="">All</option>` +
          (data.statuses || []).map(s => `<option value="${s.id}">${s.label}</option>`).join("");
      }
      if (f_status) {
        f_status.innerHTML = (data.statuses || []).map(s => `<option value="${s.id}">${s.label}</option>`).join("");
      }
      statusLabelMap = new Map((data.statuses || []).map(s => [String(s.id), s.label]));
      if (f_type && (data.employment_types || []).length) {
        const types = (data.employment_types || [])
          .slice()
          .sort((a, b) => String(a).localeCompare(String(b), undefined, { sensitivity: "base" }));
        f_type.innerHTML = `<option value="">-- Select --</option>` +
          types.map(t => `<option value="${t}">${t}</option>`).join("");
      }
      if (data.area_places && typeof data.area_places === 'object' && !Array.isArray(data.area_places)) {
        areaPlaces = data.area_places;
        // Rebuild assignment segment from API data to ensure dropdowns are populated
        if (assignSeg) {
          const activeAssign = currentAssignmentFilter() || "All";
          const activeArea = areaSubFilter || "";
          assignSeg.innerHTML = "";

          const allBtn = document.createElement("button");
          allBtn.type = "button";
          allBtn.className = `seg__btn seg__btn--emp${activeAssign === "All" ? " is-active" : ""}`;
          allBtn.setAttribute("data-assign", "");
          allBtn.textContent = "All";
          assignSeg.appendChild(allBtn);

          (data.assignments || []).forEach((label) => {
            const places = areaPlaces[label] || [];
            const wrap = document.createElement("div");
            wrap.className = "seg__btn-wrap";

            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = `seg__btn seg__btn--emp${activeAssign === label ? " is-active" : ""}`;
            btn.setAttribute("data-assign", label);
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
              dropdown.setAttribute("data-group", label);
              dropdown.style.display = "none";
              places.forEach((p) => {
                const item = document.createElement("button");
                item.type = "button";
                item.className = `seg__dropdown-item${activeArea === p ? " is-active" : ""}`;
                item.setAttribute("data-place", p);
                item.textContent = p;
                dropdown.appendChild(item);
              });
              wrap.appendChild(dropdown);
            }

            assignSeg.appendChild(wrap);
          });
        }
      }
      wireAssignButtons();
      // If the drawer is open, refresh Area Place options now that filters are loaded.
      if (drawer?.classList?.contains("is-open")) {
        syncAssignmentUI();
      }
    } catch (err) {
      console.error(err);
    }
  }

  try {
    if (!serverRendered) {
      await loadFilters();
      // Ensure filter selects are select2-initialized after options are populated
      const filterCard = document.querySelector(".filtersCard");
      if (filterCard) initSelect2(filterCard);
    } else {
      const seed = Array.isArray(window.__serverEmployees) ? window.__serverEmployees : [];
      employees = seed.map(fromApi);
      wireAssignButtons();
      assignmentFilter = currentAssignmentFilter();
    }
    // Always refresh filters from API (server-rendered dropdowns can be stale/empty)
    if (serverRendered) {
      await loadFilters();
      const filterCard = document.querySelector(".filtersCard");
      if (filterCard) initSelect2(filterCard);
    }

    try {
      await loadEmployees();
    } catch (err) {
      if (!serverRendered) throw err;
      console.warn("Failed to load full employee list. Using server seed.", err);
    }
  } catch (err) {
    console.error(err);
    alert(err.message || "Failed to load employees.");
  }
  render();

  flushPendingToast();

  if (AUTO_REFRESH_MS > 0) {
    setInterval(runAutoRefresh, AUTO_REFRESH_MS);
  }

  // ===== Discipline & Tardiness =====
  async function loadTardiness(empId) {
    if (!empId) return;
    try {
      const data = await apiFetch(`/employees/${encodeURIComponent(empId)}/tardiness`);
      if (tardyMonthEl) tardyMonthEl.textContent = formatMinutes(data.month_minutes);
      if (tardyYearEl) tardyYearEl.textContent = formatMinutes(data.year_minutes);
      if (tardyTotalEl) tardyTotalEl.textContent = formatMinutes(data.total_minutes);
      if (tardyLateDaysEl) tardyLateDaysEl.textContent = `${data.late_days ?? 0} late days`;
    } catch (err) {
      resetDisciplineUI();
    }
  }

  async function loadDiscipline(empId) {
    if (!disciplineTbody || !empId) return;
    disciplineTbody.innerHTML = `<tr><td colspan="4" class="muted small">Loading...</td></tr>`;
    try {
      const rows = await apiFetch(`/employees/${encodeURIComponent(empId)}/discipline-records`);
      if (!rows || !rows.length) {
        disciplineTbody.innerHTML = `<tr><td colspan="4" class="muted small">No records yet.</td></tr>`;
        return;
      }
      const label = (t) => {
        if (t === "memo") return "Memo";
        if (t === "sanction") return "Sanction";
        if (t === "nte") return "NTE";
        return t || "—";
      };
      disciplineTbody.innerHTML = rows.map(r => `
        <tr>
          <td>${label(r.type)}</td>
          <td>${r.issued_at || "—"}</td>
          <td>${r.reference || "—"}</td>
          <td class="muted small">${r.remarks || "—"}</td>
        </tr>
      `).join("");
    } catch (err) {
      disciplineTbody.innerHTML = `<tr><td colspan="4" class="muted small">Failed to load.</td></tr>`;
    }
  }

  // ===== Charges / Shortages =====
  const chargesTbody = document.getElementById("chargesTbody");
  const addChargeBtn = document.getElementById("addChargeBtn");
  const cancelChargeBtn = document.getElementById("cancelChargeBtn");
  const saveChargeBtn = document.getElementById("saveChargeBtn");
  const chargeFormWrap = document.getElementById("chargeFormWrap");
  const cf_type = document.getElementById("cf_type");
  const cf_amount = document.getElementById("cf_amount");
  const cf_description = document.getElementById("cf_description");
  const cf_planType = document.getElementById("cf_planType");
  const cf_installmentWrap = document.getElementById("cf_installmentWrap");
  const cf_installmentCount = document.getElementById("cf_installmentCount");
  const cf_startMonth = document.getElementById("cf_startMonth");
  const cf_startCutoff = document.getElementById("cf_startCutoff");

  // Default start month to current month
  if (cf_startMonth) {
    const now = new Date();
    cf_startMonth.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  }

  cf_planType && cf_planType.addEventListener("change", () => {
    if (cf_installmentWrap) {
      cf_installmentWrap.style.display = cf_planType.value === "installment" ? "" : "none";
    }
  });

  addChargeBtn && addChargeBtn.addEventListener("click", () => {
    if (chargeFormWrap) chargeFormWrap.style.display = "";
    if (addChargeBtn) addChargeBtn.style.display = "none";
  });

  cancelChargeBtn && cancelChargeBtn.addEventListener("click", () => {
    if (chargeFormWrap) chargeFormWrap.style.display = "none";
    if (addChargeBtn) addChargeBtn.style.display = "";
    clearChargeForm();
  });

  saveChargeBtn && saveChargeBtn.addEventListener("click", async () => {
    if (!selectedEmpId) return;
    const amount = parseFloat(cf_amount?.value || 0);
    if (!amount || amount <= 0) { alert("Please enter a valid amount."); return; }
    if (cf_planType?.value === "installment" && !(parseInt(cf_installmentCount?.value || 0) >= 2)) {
      alert("Please enter at least 2 cutoffs for installment."); return;
    }
    try {
      await apiFetch(`/employees/${encodeURIComponent(selectedEmpId)}/deduction-cases`, {
        method: "POST",
        body: JSON.stringify({
          type: cf_type?.value || "shortage",
          description: cf_description?.value || null,
          amount_total: amount,
          plan_type: cf_planType?.value || "one_time",
          installment_count: cf_planType?.value === "installment" ? parseInt(cf_installmentCount?.value || 0) : null,
          start_month: cf_startMonth?.value || "",
          start_cutoff: cf_startCutoff?.value || "11-25",
        }),
      });
      clearChargeForm();
      if (chargeFormWrap) chargeFormWrap.style.display = "none";
      if (addChargeBtn) addChargeBtn.style.display = "";
      await loadCharges(selectedEmpId);
    } catch (err) {
      alert(err.message || "Failed to save charge.");
    }
  });

  function clearChargeForm() {
    if (cf_type) cf_type.value = "shortage";
    if (cf_amount) cf_amount.value = "";
    if (cf_description) cf_description.value = "";
    if (cf_planType) cf_planType.value = "one_time";
    if (cf_installmentWrap) cf_installmentWrap.style.display = "none";
    if (cf_installmentCount) cf_installmentCount.value = "";
    const now = new Date();
    if (cf_startMonth) cf_startMonth.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    if (cf_startCutoff) cf_startCutoff.value = "11-25";
  }

  async function loadCharges(empId) {
    if (!chargesTbody) return;
    chargesTbody.innerHTML = `<tr><td colspan="7" class="muted small">Loading...</td></tr>`;
    try {
      const cases = await apiFetch(`/employees/${encodeURIComponent(empId)}/deduction-cases`);
      renderCharges(cases, empId);
    } catch {
      chargesTbody.innerHTML = `<tr><td colspan="7" class="muted small">Failed to load.</td></tr>`;
    }
  }

  function renderCharges(cases, empId) {
    if (!chargesTbody) return;
    if (!cases || !cases.length) {
      chargesTbody.innerHTML = `<tr><td colspan="7" class="muted small">No charges or shortages.</td></tr>`;
      return;
    }
    const fmtMoney = (n) => "₱" + Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const planLabel = (c) => c.plan_type === "installment" ? `Installment (${c.installment_count} cutoffs)` : "One-time";
    chargesTbody.innerHTML = cases.map(c => `
      <tr>
        <td>${c.type.charAt(0).toUpperCase() + c.type.slice(1)}</td>
        <td class="muted small">${c.description || "—"}</td>
        <td class="num">${fmtMoney(c.amount_total)}</td>
        <td class="num">${fmtMoney(c.amount_remaining)}</td>
        <td class="small">${planLabel(c)}</td>
        <td><span class="badge ${c.status === "active" ? "badge--ok" : "badge--warn"}">${c.status}</span></td>
        <td>
          ${c.status === "active" ? `<button class="iconbtn" type="button" data-charge-close="${c.id}" title="Close">✕</button>` : ""}
        </td>
      </tr>
    `).join("");

    // Bind close buttons
    chargesTbody.querySelectorAll("[data-charge-close]").forEach(btn => {
      btn.addEventListener("click", async () => {
        const caseId = btn.getAttribute("data-charge-close");
        if (!confirm("Close this charge/shortage? Future scheduled lines will be voided.")) return;
        try {
          await apiFetch(`/employees/${encodeURIComponent(empId)}/deduction-cases/${caseId}/close`, { method: "POST" });
          await loadCharges(empId);
        } catch (err) {
          alert(err.message || "Failed to close case.");
        }
      });
    });
  }

  // ===== Expose helpers (optional) =====
  // You can reuse these in payroll processing by copying these functions there
  window.__payrollRequired = { getPayrollMissing, isPayrollEligible };
});
