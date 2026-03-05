import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";
import { broadcastEmployeeUpdate } from "./shared/dataSync";

document.addEventListener("DOMContentLoaded", async () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();
  const notifyEmployeeUpdated = () => broadcastEmployeeUpdate();

  // ===== Constants =====
  let areaPlaces = Array.isArray(window.__areaPlaces) ? window.__areaPlaces : [];

  // ===== Payroll Required Field Rules =====
  const PAYROLL_REQUIRED = {
    requirePayGroup: false,
    requireAssignment: true,
    requireBasicPay: true,
    requireGovIds: true, // requires ALL gov ids below
    govRequiredFields: ["sss", "ph", "pagibig", "tin"], // adjust if needed
  };

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
      position: emp.position || "",
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
      birthday: emp.birthday || "",
      hired: emp.date_hired || "",
      mobile: emp.mobile || "",
      email: emp.email || "",
      address: emp.address || "",
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
    return {
      emp_no: data.empId,
      first_name: data.first,
      middle_name: data.middle || null,
      last_name: data.last,
      status: data.status || null,
      employment_status_id: data.statusId || null,
      birthday: data.birthday || null,
      mobile: data.mobile || null,
      address: data.address || null,
      email: data.email || null,
      department: data.dept,
      position: data.position,
      employment_type: data.type || null,
      pay_type: data.payType || null,
      date_hired: data.hired || null,
      assignment_type: data.assignmentType || null,
      area_place: data.areaPlace || null,
      external_area: data.externalArea || null,
      basic_pay: data.rate || 0,
      allowance: data.allowance || 0,
      bank_name: data.bankName || null,
      bank_account_name: data.accountName || null,
      bank_account_number: data.accountNumber || null,
      payout_method: (data.accountNumber || "").trim() ? "BANK" : "CASH",
      sss: data.sss || null,
      philhealth: data.ph || null,
      pagibig: data.pagibig || null,
      tin: data.tin || null,
    };
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
  const areaPlaceFilterWrap = document.getElementById("areaPlaceFilterWrap");
  const areaPlaceFilter = document.getElementById("areaPlaceFilter");

  // import/export
  const exportBtn = document.getElementById("exportBtn");


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
  const f_address = F("f_address");
  const f_dept = F("f_dept");
  const f_position = F("f_position");
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
  const historyDrawer = document.getElementById("historyDrawer");
  const historyDrawerOverlay = document.getElementById("historyDrawerOverlay");
  const closeHistoryDrawerBtn = document.getElementById("closeHistoryDrawerBtn");
  const historyDrawerTitleEl = document.getElementById("historyDrawerTitle");
  const historyDrawerSubEl = document.getElementById("historyDrawerSubtitle");
  const historyDrawerExternalEl = document.getElementById("historyDrawerExternal");
  const areaHistoryList = document.getElementById("areaHistoryList");

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

  // state
  let selectedEmpId = null;
  let selectedIds = new Set();
  let assignmentFilter = "All";
  let filterStatusId = "";
  let filterDept = "";
  let statusLabelMap = new Map();

  // ===== Drawer helpers =====
  function openDrawer(title, subtitle) {
    if (!drawer) return;
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    if (drawerOverlay) drawerOverlay.removeAttribute("hidden");
    if (drawerTitleEl) drawerTitleEl.textContent = title;
    if (drawerSubEl) drawerSubEl.textContent = subtitle;
    if (deleteBtn) deleteBtn.style.display = selectedEmpId ? "inline-flex" : "none";
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

  function formatMobile(value) {
    return formatWithGroups(onlyDigits(value).slice(0, 11), [4, 3, 4]);
  }

  function formatAccount(value) {
    const digits = onlyDigits(value).slice(0, 20);
    return digits.replace(/(.{4})/g, "$1-").replace(/-$/, "");
  }

  function clearForm() {
    empForm?.reset();
    if (f_assignmentType) f_assignmentType.value = "Davao";
    if (f_areaPlace) f_areaPlace.value = "";
    if (f_externalArea) f_externalArea.value = "";
    if (f_status && f_status.options.length) f_status.value = f_status.options[0].value;
    if (plInfoWrap) plInfoWrap.style.display = "none";
    syncAssignmentUI();
    syncCashAdvanceFields();
    syncSalaryFields();
    syncPayoutFields();
  }

  function fillForm(emp) {
    if (!emp) return;
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
    f_address && (f_address.value = emp.address || "");
    f_dept && (f_dept.value = emp.dept || "");
    f_position && (f_position.value = emp.position || "");
    f_type && (f_type.value = emp.type || "");
    f_hired && (f_hired.value = emp.hired || "");
    f_payType && (f_payType.value = emp.payType || "");
    f_rate && (f_rate.value = emp.rate ?? "");
    f_allowance && (f_allowance.value = emp.allowance ?? 0);

    f_assignmentType && (f_assignmentType.value = emp.assignmentType || "Davao");
    f_areaPlace && (f_areaPlace.value = emp.areaPlace || "");
    if (f_externalArea) f_externalArea.value = emp.externalArea || "";

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
      birthday: f_bday?.value || "",
      mobile: onlyDigits(f_mobile?.value),
      email: f_email?.value?.trim(),
      address: f_address?.value?.trim(),
      dept: f_dept?.value?.trim(),
      position: f_position?.value?.trim(),
      type: f_type?.value?.trim(),
      hired: f_hired?.value || "",
      payType: f_payType?.value?.trim(),
      rate: Number(f_rate?.value || 0),
      allowance: Number(f_allowance?.value || 0),

      assignmentType: f_assignmentType?.value?.trim(),
      areaPlace: f_areaPlace?.value?.trim(),
      externalArea: f_externalArea?.value?.trim(),

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
    if (t === "Area") {
      const place = (emp.areaPlace || "").trim() || "-";
      const isRegular = String(emp.type || "").toLowerCase() === "regular";
      const ext = isRegular && (emp.externalArea || "").trim()
        ? ` — Ext: ${emp.externalArea.trim()}` : "";
      return `Area (${place})${ext}`;
    }
    return t;
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
      "Address",
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
        emp.address || "",
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


  function populateAreaPlaces(selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    selectEl.innerHTML =
      `<option value="">-- Select area place --</option>` +
      areaPlaces.map(p => `<option value="${p}">${p}</option>`).join("");
    if (areaPlaces.includes(current)) selectEl.value = current;
  }

  function populateAreaFilterPlaces(selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    selectEl.innerHTML =
      `<option value="All" selected>All</option>` +
      areaPlaces.map(p => `<option value="${p}">${p}</option>`).join("");
    if (current && (current === "All" || areaPlaces.includes(current))) {
      selectEl.value = current;
    }
  }

  function syncAssignmentUI() {
    if (!f_assignmentType || !f_areaPlace) return;
    populateAreaPlaces(f_areaPlace);

    const type = f_assignmentType.value;
    const isArea = type === "Area";
    if (isArea) {
      f_areaPlace.disabled = false;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "";
    } else {
      f_areaPlace.value = "";
      f_areaPlace.disabled = true;
      if (areaPlaceWrap) areaPlaceWrap.style.display = "none";
    }
    syncExternalAreaUI();
  }

  function syncExternalAreaUI() {
    if (!f_assignmentType) return;
    const isArea = f_assignmentType.value === "Area";
    const isRegular = String(f_type?.value || "").toLowerCase() === "regular";
    const show = isArea && isRegular;
    if (f_externalArea) {
      if (show) {
        populateAreaPlaces(f_externalArea);
        f_externalArea.disabled = false;
      } else {
        f_externalArea.value = "";
        f_externalArea.disabled = true;
      }
    }
    if (externalAreaWrap) externalAreaWrap.style.display = show ? "" : "none";
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

  async function loadAreaHistory(empNo) {
    if (!areaHistoryList) return;
    areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">Loading...</td></tr>';
    try {
      const rows = await apiFetch(`/employees/${encodeURIComponent(empNo)}/area-history`);
      if (!rows.length) {
        areaHistoryList.innerHTML = '<tr><td colspan="2" class="muted small">No history yet.</td></tr>';
        return;
      }

      // Sort ASC to compute date ranges
      const asc = [...rows].sort((a, b) => a.effective_date.localeCompare(b.effective_date));
      const todayStr = toLocalDateStr(new Date());

      // Each entry's period: from its effective_date to the day before the next change (or today)
      const ranges = asc.map((entry, i) => {
        const start = entry.effective_date;
        const end = i < asc.length - 1 ? dayBefore(asc[i + 1].effective_date) : todayStr;
        const period = start === end
          ? fmtShortDate(start)
          : `${fmtShortDate(start)} to ${fmtShortDate(end)}`;
        return { period, area: entry.area_place };
      });

      ranges.reverse(); // most recent first
      areaHistoryList.innerHTML = ranges.map(r =>
        `<tr>
          <td>${r.period}</td>
          <td style="font-weight:600;">${r.area}</td>
        </tr>`
      ).join('');
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
      if (t === "Area") {
        const ap = (emp.areaPlace || "").trim();
        if (!ap) missing.push("Area Place");
        const isRegular = String(emp.type || "").toLowerCase() === "regular";
        if (isRegular && !(emp.externalArea || "").trim()) missing.push("External Area");
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
    const areaVal = areaPlaceFilter?.value || "";

    return list.filter(emp => {
      const okDept = !deptVal || deptVal === "All" || (emp.dept || "") === deptVal;
      const okStatus = statusMatches(emp, statusVal);
      const okAssign = assignVal === "All" || !assignVal || (emp.assignmentType || "") === assignVal;
      const okArea =
        assignVal !== "Area" ||
        !areaVal ||
        areaVal === "All" ||
        (emp.areaPlace || "") === areaVal;

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
      tr.innerHTML = `
        <td class="col-check">
          <input class="empCheck" type="checkbox" data-id="${emp.empId}" ${isChecked ? "checked" : ""} aria-label="Select employee ${emp.empId}">
        </td>
        <td>${emp.empId}</td>
        <td>${fullName(emp)}</td>
        <td>${emp.dept || "-"}</td>
        <td>${assignmentText(emp)}</td>
        <td>
          <div class="salaryVal">${money(salaryTotal(emp))}</div>
        </td>

        <!-- ? NEW -->
        <td>${payrollBadgeHTML(emp)}</td>
        <td class="actions">
          <button class="iconbtn" type="button" data-action="edit" data-id="${emp.empId}" title="Edit" aria-label="Edit">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 20h9"></path>
              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
            </svg>
          </button>
          ${emp.assignmentType === "Area" ? `<button class="iconbtn" type="button" data-action="history" data-id="${emp.empId}" title="Area History" aria-label="Area History">
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
      const isArea = bulkAssignSelect.value === "Area";
      if (bulkAreaPlaceSelect) {
        if (isArea) {
          populateAreaPlaces(bulkAreaPlaceSelect);
          bulkAreaPlaceSelect.style.display = "";
        } else {
          bulkAreaPlaceSelect.value = "";
          bulkAreaPlaceSelect.style.display = "none";
        }
      }
      bulkAssignApply.disabled = !bulkAssignSelect.value || (isArea && !bulkAreaPlaceSelect?.value);
    });
  }

  bulkAreaPlaceSelect && bulkAreaPlaceSelect.addEventListener("change", () => {
    const isArea = bulkAssignSelect?.value === "Area";
    if (bulkAssignApply) {
      bulkAssignApply.disabled = !bulkAssignSelect?.value || (isArea && !bulkAreaPlaceSelect.value);
    }
  });

  bulkAssignApply && bulkAssignApply.addEventListener("click", async () => {
    const assignment = bulkAssignSelect?.value || "";
    if (!assignment || selectedIds.size === 0) return;

    const ids = Array.from(selectedIds);
    const areaPlace = assignment === "Area" ? (bulkAreaPlaceSelect?.value || "") : "";
    if (assignment === "Area" && !areaPlace) {
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
          emp.areaPlace = assignment === "Area" ? areaPlace : "";
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

  function wireAssignButtons() {
    assignBtns = assignSeg ? Array.from(assignSeg.querySelectorAll(".seg__btn--emp")) : [];
    if (!assignBtns.length) return;
    assignBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        assignBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        const rawAssign = btn.getAttribute("data-assign");
        assignmentFilter = rawAssign && rawAssign !== "" ? rawAssign : "All";
        const assignInput = document.getElementById("assignmentInput");
        if (assignInput) assignInput.value = assignmentFilter === "All" ? "" : assignmentFilter;

        if (assignmentFilter === "Area") {
          if (areaPlaceFilterWrap) areaPlaceFilterWrap.style.display = "";
          if (areaPlaceFilter) {
            populateAreaFilterPlaces(areaPlaceFilter);
          }
        } else {
          if (areaPlaceFilterWrap) areaPlaceFilterWrap.style.display = "none";
          if (areaPlaceFilter) areaPlaceFilter.value = "All";
        }

        currentPage = 1;
        render();
      });
    });
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
      if (String(emp.type || "").toLowerCase() === "regular" && ["Tagum", "Davao"].includes(emp.assignmentType)) {
        fetchAndShowPLBalance(emp.empId);
      }
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
  openAddBtn && openAddBtn.addEventListener("click", () => {
    selectedEmpId = null;
    clearForm();
    openDrawer("Add Employee", "Fill in the details then click Save.");
  });

  closeDrawerBtn && closeDrawerBtn.addEventListener("click", closeDrawer);
  cancelBtn && cancelBtn.addEventListener("click", closeDrawer);
  drawerOverlay && drawerOverlay.addEventListener("click", closeDrawer);
  closeHistoryDrawerBtn && closeHistoryDrawerBtn.addEventListener("click", closeHistoryDrawer);
  historyDrawerOverlay && historyDrawerOverlay.addEventListener("click", closeHistoryDrawer);
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    closeDrawer();
    closeHistoryDrawer();
  });

  // live cash advance preview
  f_type && f_type.addEventListener("change", () => {
    syncCashAdvanceFields();
    syncExternalAreaUI();
  });
  f_rate && f_rate.addEventListener("input", () => {
    syncCashAdvanceFields();
    syncSalaryFields();
  });
  f_allowance && f_allowance.addEventListener("input", syncSalaryFields);
  f_accountNumber && f_accountNumber.addEventListener("input", syncPayoutFields);
  f_assignmentType && f_assignmentType.addEventListener("change", syncAssignmentUI);
  f_empId && f_empId.addEventListener("input", () => {
    const digits = String(f_empId.value || "").replace(/\D/g, "").slice(0, 4);
    if (f_empId.value !== digits) {
      f_empId.value = digits;
    }
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

  function capFirst(value) {
    const v = String(value || "");
    if (!v) return v;
    return v.charAt(0).toUpperCase() + v.slice(1);
  }

  const capInputs = [
    f_first,
    f_last,
    f_middle,
    f_dept,
    f_position,
    f_address,
    f_bankName,
    f_accountName,
  ];
  capInputs.forEach((el) => {
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

    // required fields
    if (!/^\d{4}$/.test(String(data.empId || ""))) {
      alert("Employee ID must be exactly 4 digits.");
      return;
    }
    if (!data.empId || !data.first || !data.last || !data.dept || !data.position) {
      alert("Please fill required fields (Employee ID, First, Last, Department, Position).");
      return;
    }
    if (!Number.isFinite(data.rate) || data.rate < 0) {
      alert("Basic Pay must be a valid number.");
      return;
    }
    if (!Number.isFinite(data.allowance) || data.allowance < 0) {
      alert("Allowance must be a valid number.");
      return;
    }
    if (data.accountNumber) {
      if (!data.bankName || !data.accountName) {
        alert("Bank Name and Account Name are required when Account Number is provided.");
        return;
      }
    }

    const exists = employees.some(emp => emp.empId === data.empId);

    try {
      if (selectedEmpId) {
        await apiFetch(`/employees/${encodeURIComponent(selectedEmpId)}`, {
          method: "PUT",
          body: JSON.stringify(toApi(data)),
        });
      } else if (exists) {
        if (!confirm("Employee ID already exists. Overwrite?")) return;
        await apiFetch(`/employees/${encodeURIComponent(data.empId)}`, {
          method: "PUT",
          body: JSON.stringify(toApi(data)),
        });
      } else {
        await apiFetch("/employees", {
          method: "POST",
          body: JSON.stringify(toApi(data)),
        });
      }

      const successMsg = selectedEmpId ? "Employee updated successfully." : "Employee added successfully.";
      closeDrawer();
      showToast(successMsg);
      await loadEmployees();
      render();
      notifyEmployeeUpdated();
    } catch (err) {
      alert(err.message || "Failed to save employee.");
    }
  });

  // Init
  syncAssignmentUI();
  syncCashAdvanceFields();
  syncSalaryFields();
  syncPayoutFields();
  assignmentFilter = currentAssignmentFilter();
  if (areaPlaceFilterWrap) {
    areaPlaceFilterWrap.style.display = assignmentFilter === "Area" ? "" : "none";
  }
  wireHeaderSorting();
  updateSortIcons();
  async function loadFilters() {
    try {
      const data = await apiFetch("/employees/filters");
      if (deptFilter) {
        deptFilter.innerHTML = `<option value="">All</option>` +
          (data.departments || []).map(d => `<option value="${d}">${d}</option>`).join("");
      }
      if (statusFilter) {
        statusFilter.innerHTML = `<option value="">All</option>` +
          (data.statuses || []).map(s => `<option value="${s.id}">${s.label}</option>`).join("");
      }
      if (f_status) {
        f_status.innerHTML = (data.statuses || []).map(s => `<option value="${s.id}">${s.label}</option>`).join("");
      }
      statusLabelMap = new Map((data.statuses || []).map(s => [String(s.id), s.label]));
      if (assignSeg) {
        const assignments = (data.assignments || []).length ? data.assignments : ["Tagum", "Davao", "Area"];
        assignSeg.innerHTML =
          `<button type="button" class="seg__btn seg__btn--emp is-active" data-assign="All">All</button>` +
          assignments.map(a => `<button type="button" class="seg__btn seg__btn--emp" data-assign="${a}">${a}</button>`).join("");
        assignmentFilter = "All";
        wireAssignButtons();
      }
      if (Array.isArray(data.area_places)) {
        areaPlaces = data.area_places;
      }
    } catch (err) {
      console.error(err);
    }
  }

  try {
    if (!serverRendered) {
      await loadFilters();
    } else {
      const seed = Array.isArray(window.__serverEmployees) ? window.__serverEmployees : [];
      employees = seed.map(fromApi);
      wireAssignButtons();
      assignmentFilter = currentAssignmentFilter();
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

  // ===== Expose helpers (optional) =====
  // You can reuse these in payroll processing by copying these functions there
  window.__payrollRequired = { getPayrollMissing, isPayrollEligible };
});
