import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { formatMoney } from "./shared/format";
import { initCashAdvanceTransactions } from "./settings/cashAdvance";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const money = (n) => formatMoney(n);
  const esc = (value) => String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");

  function prettyLabel(value) {
    const raw = String(value ?? "").trim();
    if (!raw) return "—";
    const map = {
      every_cutoff: "Every cutoff",
      cutoff1_only: "1st cutoff only",
      cutoff2_only: "2nd cutoff only",
      monthly: "Monthly",
    };
    if (map[raw]) return map[raw];
    return raw
      .replace(/_/g, " ")
      .replace(/\s+/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase());
  }

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

  const toast = (message) => {
    if (message) alert(message);
  };

  // Lazy-init Cash Advance so it only loads/fetches when the Cash Advance tab is opened.
  const cashAdvanceTxnNotice = document.getElementById("cashAdvanceTxnNotice");
  let cashAdvanceInited = false;
  function initCashAdvanceIfNeeded() {
    if (cashAdvanceInited) return;
    cashAdvanceInited = true;
    initCashAdvanceTransactions(toast, apiFetch, cashAdvanceTxnNotice);
  }

  // Elements
  const loanSearch = document.getElementById("loanSearch");
  const loanTypeFilter = document.getElementById("loanTypeFilter");
  const loanStatusFilter = document.getElementById("loanStatusFilter");
  const loanStartFilter = document.getElementById("loanStartFilter");
  const loanAssignFilter = document.getElementById("loanAssignFilter");
  const loanAssignSeg = document.getElementById("loanAssignSeg");
  const loanDeptFilter = document.getElementById("loanDeptFilter");
  const loanMeta = document.getElementById("loanMeta");
  const loanTbody = document.getElementById("loanTbody");

  // Tabs (Agency loans / Cash advance / Charges / Shortages)
  const loansTabBtns = Array.from(document.querySelectorAll(".loansTab"));
  const loansPanels = Array.from(document.querySelectorAll(".loansTabPanel"));

  // Deduction Cases (Charges / Shortages)
  const dcTitle = document.getElementById("dcTitle");
  const dcFormTitle = document.getElementById("dcFormTitle");
  const dcNewBtn = document.getElementById("dcNewBtn");
  const dcCancelBtn = document.getElementById("dcCancelBtn");
  const dcDrawer = document.getElementById("dcDrawer");
  const dcDrawerOverlay = document.getElementById("dcDrawerOverlay");
  const closeDcDrawer = document.getElementById("closeDcDrawer");
  const dcFormEmployeeInput = document.getElementById("dcFormEmployeeInput");
  const dcFormEmployeeEmpNo = document.getElementById("dcFormEmployeeEmpNo");
  const dcFormEmployeeList = document.getElementById("dcFormEmployeeList");
  const dcNotice = document.getElementById("dcNotice");
  const dcEmployeeInput = document.getElementById("dcEmployeeInput");
  const dcEmployeeEmpNo = document.getElementById("dcEmployeeEmpNo");
  const dcEmployeeList = document.getElementById("dcEmployeeList");
  const dcTbody = document.getElementById("dcTbody");
  const dcForm = document.getElementById("dcForm");
  const dcDescription = document.getElementById("dcDescription");
  const dcAmountTotal = document.getElementById("dcAmountTotal");
  const dcPlanType = document.getElementById("dcPlanType");
  const dcInstallmentWrap = document.getElementById("dcInstallmentWrap");
  const dcInstallmentCount = document.getElementById("dcInstallmentCount");
  const dcStartMonth = document.getElementById("dcStartMonth");
  const dcStartCutoff = document.getElementById("dcStartCutoff");

  const openAddLoanBtn = document.getElementById("openAddLoanBtn");
  const loanDrawer = document.getElementById("loanDrawer");
  const loanDrawerOverlay = document.getElementById("loanDrawerOverlay");
  const closeLoanDrawer = document.getElementById("closeLoanDrawer");
  const cancelLoanBtn = document.getElementById("cancelLoanBtn");
  const saveLoanBtn = document.getElementById("saveLoanBtn");
  const loanDrawerTitle = document.getElementById("loanDrawerTitle");

  const loanDetailDrawer = document.getElementById("loanDetailDrawer");
  const loanDetailOverlay = document.getElementById("loanDetailOverlay");
  const closeLoanDetail = document.getElementById("closeLoanDetail");
  const closeLoanDetailFooter = document.getElementById("closeLoanDetailFooter");
  const loanDetailSummary = document.getElementById("loanDetailSummary");
  const loanHistoryTbody = document.getElementById("loanHistoryTbody");
  const loanDetailTitle = document.getElementById("loanDetailTitle");

  // Drawer fields
  const loanEmpSearch = document.getElementById("loanEmpSearch");
  const loanEmpSuggest = document.getElementById("loanEmpSuggest");
  const loanEmpNo = document.getElementById("loanEmpNo");
  const loanEmpName = document.getElementById("loanEmpName");
  const loanEmpAssign = document.getElementById("loanEmpAssign");
  const loanEmpDept = document.getElementById("loanEmpDept");
  const loanEmpPos = document.getElementById("loanEmpPos");

  const loanType = document.getElementById("loanType");
  const loanLender = document.getElementById("loanLender");
  const loanRef = document.getElementById("loanRef");
  const loanApprovalDate = document.getElementById("loanApprovalDate");
  const loanReleaseDate = document.getElementById("loanReleaseDate");
  const loanPrincipal = document.getElementById("loanPrincipal");
  const loanInterest = document.getElementById("loanInterest");
  const loanTotalPayable = document.getElementById("loanTotalPayable");
  const loanNotes = document.getElementById("loanNotes");

  const loanStartMonth = document.getElementById("loanStartMonth");
  const loanStartCutoff = document.getElementById("loanStartCutoff");
  const loanFrequency = document.getElementById("loanFrequency");
  const loanMethod = document.getElementById("loanMethod");
  const loanMonthly = document.getElementById("loanMonthly");
  const loanPerCutoff = document.getElementById("loanPerCutoff");
  const loanTerm = document.getElementById("loanTerm");

  const loanAutoDeduct = document.getElementById("loanAutoDeduct");
  const loanAllowPartial = document.getElementById("loanAllowPartial");
  const loanCarryForward = document.getElementById("loanCarryForward");
  const loanStopOnZero = document.getElementById("loanStopOnZero");
  const loanPriority = document.getElementById("loanPriority");
  const loanStatus = document.getElementById("loanStatus");
  const loanSource = document.getElementById("loanSource");
  const loanRecovery = document.getElementById("loanRecovery");
  const loanApprovedBy = document.getElementById("loanApprovedBy");
  const loanEncodedBy = document.getElementById("loanEncodedBy");
  const currentUserName = (window.__currentUserName || "").trim();

  let loans = [];
  let assignmentFilter = "All";
  let areaPlaces = (window.__areaPlaces && typeof window.__areaPlaces === "object" && !Array.isArray(window.__areaPlaces))
    ? window.__areaPlaces
    : {};
  let areaSubFilter = "";
  let editingLoanId = null;
  let openDropdown = null;
  let openDropdownBtn = null;
  let assignDropdownEventsBound = false;

  // Tab state
  let activeLoansPanel = "agency";
  let deductionType = "charge"; // charge | shortage
  let deductionCases = [];

  function flashNotice(message) {
    if (!dcNotice) return;
    if (!message) {
      dcNotice.hidden = true;
      dcNotice.textContent = "";
      return;
    }
    dcNotice.textContent = message;
    dcNotice.hidden = false;
    window.clearTimeout(dcNotice.__t);
    dcNotice.__t = window.setTimeout(() => {
      dcNotice.hidden = true;
    }, 2400);
  }

  function setLoansTab(panel, opts = {}) {
    if (!panel) return;
    activeLoansPanel = panel;
    if (panel === "deductions" && opts.deductionType) {
      deductionType = opts.deductionType;
      try { localStorage.setItem("loans.deductionType", deductionType); } catch { /* ignore */ }
    }

    loansTabBtns.forEach((b) => {
      const tab = b.getAttribute("data-loans-tab") || "";
      const type = b.getAttribute("data-deduction-type") || "";
      const isActive = tab === panel && (panel !== "deductions" || type === deductionType);
      b.classList.toggle("is-active", isActive);
    });

    loansPanels.forEach((p) => {
      const pName = p.getAttribute("data-loans-panel") || "";
      p.hidden = pName !== panel;
    });

    if (panel === "deductions") {
      const title = deductionType === "shortage" ? "Shortages" : "Charges";
      const singular = deductionType === "shortage" ? "Shortage" : "Charge";
      if (dcTitle) dcTitle.textContent = title;
      if (dcFormTitle) dcFormTitle.textContent = `New ${singular}`;
      if (dcNewBtn) dcNewBtn.textContent = `+ New ${singular}`;
      hideDcForm();
      renderDeductionCases();
    }

    if (panel === "cash") initCashAdvanceIfNeeded();

    try { localStorage.setItem("loans.activeTab", panel); } catch { /* ignore */ }
  }

  function initLoansTabs() {
    if (!loansTabBtns.length || !loansPanels.length) return;

    let savedPanel = "agency";
    let savedType = "charge";
    try {
      savedPanel = localStorage.getItem("loans.activeTab") || savedPanel;
      savedType = localStorage.getItem("loans.deductionType") || savedType;
    } catch {
      // ignore
    }
    if (savedType !== "charge" && savedType !== "shortage") savedType = "charge";
    if (!["agency", "cash", "deductions"].includes(savedPanel)) savedPanel = "agency";

    setLoansTab(savedPanel, { deductionType: savedType });

    loansTabBtns.forEach((b) => {
      b.addEventListener("click", () => {
        const tab = b.getAttribute("data-loans-tab") || "agency";
        const type = b.getAttribute("data-deduction-type") || "";
        setLoansTab(tab, type ? { deductionType: type } : {});
      });
    });
  }

  function renderDeductionCases() {
    if (!dcTbody) return;
    const empNo = String(dcEmployeeEmpNo?.value || "").trim();
    if (!empNo) {
      dcTbody.innerHTML = `<tr><td colspan="7" class="muted small">Select an employee to view ${deductionType === "shortage" ? "shortages" : "charges"}.</td></tr>`;
      return;
    }

    const list = (deductionCases || []).filter((c) => String(c?.type || "") === deductionType);
    if (!list.length) {
      dcTbody.innerHTML = `<tr><td colspan="7" class="muted small">No ${deductionType === "shortage" ? "shortages" : "charges"} found.</td></tr>`;
      return;
    }

    const safe = (s) => String(s || "").replace(/[&<>"']/g, (m) => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m]));

    dcTbody.innerHTML = list.map((c) => {
      const plan = c.plan_type === "installment"
        ? `Installment${c.installment_count ? ` (${Number(c.installment_count)})` : ""}`
        : "One-time";
      const start = `${c.start_month || ""} ${c.start_cutoff || ""}`.trim();
      const desc = c.description ? safe(c.description) : `<span class="muted small">(no description)</span>`;
      const canClose = String(c.status || "") === "active";
      return `
        <tr>
          <td>${desc}</td>
          <td class="num">${money(Number(c.amount_total || 0))}</td>
          <td class="num">${money(Number(c.amount_remaining || 0))}</td>
          <td>${safe(plan)}</td>
          <td>${safe(start)}</td>
          <td>${safe(c.status || "")}</td>
          <td class="num">
            ${canClose ? `<button type="button" class="miniBtn miniBtn--danger" data-dc-close="${c.id}" title="Close">✕</button>` : ""}
          </td>
        </tr>
      `;
    }).join("");
  }

  function showDcForm() {
    showDrawer(dcDrawer, dcDrawerOverlay);

    // Prefill drawer employee from currently selected employee (if any)
    const currentEmpNo = String(dcEmployeeEmpNo?.value || "").trim();
    const currentLabel = String(dcEmployeeInput?.value || "").trim();
    if (currentEmpNo && dcFormEmployeeEmpNo && !String(dcFormEmployeeEmpNo.value || "").trim()) {
      dcFormEmployeeEmpNo.value = currentEmpNo;
      if (dcFormEmployeeInput) dcFormEmployeeInput.value = currentLabel;
    }

    // Start on the employee field for quicker encoding
    if (dcFormEmployeeInput) {
      dcFormEmployeeInput.focus();
      const v = dcFormEmployeeInput.value || "";
      try { dcFormEmployeeInput.setSelectionRange(v.length, v.length); } catch { /* ignore */ }
      return;
    }
    if (dcAmountTotal) dcAmountTotal.focus();
  }

  function hideDcForm() {
    hideDrawer(dcDrawer, dcDrawerOverlay);
  }

  async function loadDeductionCases() {
    const empNo = String(dcEmployeeEmpNo?.value || "").trim();
    if (!empNo || !dcTbody) return;
    dcTbody.innerHTML = `<tr><td colspan="7" class="muted small">Loading...</td></tr>`;
    try {
      deductionCases = await apiFetch(`/employees/${encodeURIComponent(empNo)}/deduction-cases`);
    } catch {
      deductionCases = [];
    }
    renderDeductionCases();
  }

  async function createDeductionCase() {
    const empNo = String(dcFormEmployeeEmpNo?.value || dcEmployeeEmpNo?.value || "").trim();
    if (!empNo) {
      alert("Select an employee first.");
      return;
    }

    const planType = String(dcPlanType?.value || "installment");
    const payload = {
      type: deductionType,
      description: String(dcDescription?.value || "").trim() || null,
      amount_total: Number(dcAmountTotal?.value || 0),
      plan_type: planType,
      installment_count: planType === "installment" ? Number(dcInstallmentCount?.value || 0) : null,
      start_month: String(dcStartMonth?.value || "").trim(),
      start_cutoff: String(dcStartCutoff?.value || "").trim(),
    };

    try {
      await apiFetch(`/employees/${encodeURIComponent(empNo)}/deduction-cases`, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      flashNotice(`${deductionType === "shortage" ? "Shortage" : "Charge"} saved.`);
      if (dcAmountTotal) dcAmountTotal.value = "";
      if (dcDescription) dcDescription.value = "";
      hideDcForm();
      await loadDeductionCases();
    } catch (err) {
      alert(err.message || "Failed to save.");
    }
  }

  async function closeDeductionCase(caseId) {
    const empNo = String(dcEmployeeEmpNo?.value || "").trim();
    if (!empNo || !caseId) return;
    const ok = confirm("Close this item? Future scheduled lines will be voided.");
    if (!ok) return;

    try {
      await apiFetch(`/employees/${encodeURIComponent(empNo)}/deduction-cases/${encodeURIComponent(caseId)}/close`, {
        method: "POST",
      });
      flashNotice("Closed.");
      await loadDeductionCases();
    } catch (err) {
      alert(err.message || "Failed to close.");
    }
  }

  function currentAssignmentFilter() {
    if (!loanAssignSeg) return assignmentFilter || "All";
    const active = loanAssignSeg.querySelector(".seg__btn--emp.is-active");
    const raw = active ? active.getAttribute("data-assign") : "";
    return raw && raw !== "" ? raw : "All";
  }

  function showDrawer(drawer, overlay) {
    if (!drawer) return;
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    if (overlay) overlay.removeAttribute("hidden");
  }

  function hideDrawer(drawer, overlay) {
    if (!drawer) return;
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    if (overlay) overlay.setAttribute("hidden", "");
  }

  function clearSuggest(el) {
    if (!el) return;
    el.innerHTML = "";
    el.hidden = true;
  }

  async function loadFilters() {
    try {
      const data = await apiFetch("/employees/filters");
      if (data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places)) {
        areaPlaces = data.area_places;
      }
      if (loanAssignSeg && data.assignments) {
        const activeAssign = currentAssignmentFilter() || "All";
        const activeArea = areaSubFilter || "";
        loanAssignSeg.innerHTML = "";

        const allBtn = document.createElement("button");
        allBtn.type = "button";
        allBtn.className = `seg__btn seg__btn--emp${activeAssign === "All" ? " is-active" : ""}`;
        allBtn.setAttribute("data-assign", "");
        allBtn.textContent = "All";
        loanAssignSeg.appendChild(allBtn);

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

          loanAssignSeg.appendChild(wrap);
        });
      }
      assignmentFilter = currentAssignmentFilter();
      if (loanAssignFilter) loanAssignFilter.value = areaSubFilter ? `${assignmentFilter}|${areaSubFilter}` : assignmentFilter;
      wireAssignButtons();
      if (loanDeptFilter && data.departments) {
        loanDeptFilter.innerHTML = `<option value="All">All</option>` + data.departments.map(d => `<option value="${d}">${d}</option>`).join("");
      }
    } catch {
      wireAssignButtons();
    }
  }

  async function loadLoans() {
    if (!loanTbody) return;
      loanTbody.innerHTML = `<tr><td colspan="13" class="muted small">Loading...</td></tr>`;

    const assignmentValue = areaSubFilter ? `${assignmentFilter}|${areaSubFilter}` : assignmentFilter;
    const params = new URLSearchParams({
      q: loanSearch?.value || "",
      loan_type: loanTypeFilter?.value || "All",
      status: loanStatusFilter?.value || "All",
      assignment: assignmentValue || loanAssignFilter?.value || "All",
      department: loanDeptFilter?.value || "All",
      start_month: loanStartFilter?.value || "",
    });

    try {
      loans = await apiFetch(`/loans/list?${params.toString()}`);
      renderLoans();
    } catch (err) {
      loanTbody.innerHTML = `<tr><td colspan="13" class="muted small">Failed to load loans.</td></tr>`;
    }
  }

  function renderLoans() {
      if (!loanTbody) return;
      if (!loans.length) {
      loanTbody.innerHTML = `<tr><td colspan="13" class="muted small">No loans found.</td></tr>`;
      if (loanMeta) loanMeta.textContent = "No loans found.";
      return;
    }
    if (loanMeta) loanMeta.textContent = `Showing ${loans.length} loan(s).`;

    loanTbody.innerHTML = loans.map(l => {
      const payrollStatus = l.auto_deduct ? "Enabled" : "Disabled";
      const statusBadge = l.status === "active" ? "badge--ok" : l.status === "paused" ? "badge--warn" : "badge--muted";
      const perCutoff = l.per_cutoff_amount && l.per_cutoff_amount > 0 ? l.per_cutoff_amount : (l.deduction_frequency === "every_cutoff" ? (l.monthly_amortization / 2) : l.monthly_amortization);
      const actions = [
        `<button class="iconbtn" type="button" data-action="view" data-id="${l.id}" title="View" aria-label="View">👁</button>`,
        `<button class="iconbtn" type="button" data-action="edit" data-id="${l.id}" title="Edit" aria-label="Edit">✎</button>`,
        l.status === "active" ? `<button class="iconbtn" type="button" data-action="pause" data-id="${l.id}" title="Pause" aria-label="Pause">⏸</button>` : "",
        l.status === "paused" ? `<button class="iconbtn" type="button" data-action="resume" data-id="${l.id}" title="Resume" aria-label="Resume">▶</button>` : "",
        (l.status !== "completed" && l.status !== "cancelled") ? `<button class="iconbtn" type="button" data-action="close" data-id="${l.id}" title="Close" aria-label="Close">✕</button>` : "",
      ].filter(Boolean).join("");
      return `
        <tr>
          <td>${l.loan_no || "—"}</td>
          <td>${l.employee_name || "—"}</td>
          <td>${l.lender || "—"}</td>
          <td>${l.loan_type}</td>
          <td class="num">${money(l.principal_amount)}</td>
          <td class="num">${money(l.total_payable)}</td>
          <td class="num">${money(perCutoff || 0)}</td>
          <td class="num">${money(l.balance_remaining)}</td>
          <td>${l.start_month} (${l.start_cutoff})</td>
          <td>${l.end_month || "—"}</td>
          <td><span class="badge ${statusBadge}">${l.status}</span></td>
          <td>${payrollStatus}</td>
          <td class="actions-cell">${actions}</td>
        </tr>
      `;
    }).join("");
  }

  function closeAllDropdowns() {
    if (!loanAssignSeg) return;
    loanAssignSeg.querySelectorAll(".seg__dropdown").forEach(d => {
      d.classList.remove("is-open");
      d.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function wireAssignButtons() {
    if (!loanAssignSeg) return;
    const assignBtns = Array.from(loanAssignSeg.querySelectorAll(".seg__btn--emp"));
    const contentScroller = document.querySelector(".content");
    let rafId = 0;

    function positionDropdown(btn, dropdown) {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desired = Math.round(rect.width);
      const maxWidth = Math.min(360, viewportW - 16);
      const dropdownW = Math.min(Math.max(desired, 240), maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) {
        left = Math.max(8, viewportW - dropdownW - 8);
      }
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${dropdownW}px`;
      dropdown.style.maxWidth = `${dropdownW}px`;
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

        const isAlreadyActive = btn.classList.contains("is-active");
        closeAllDropdowns();

        assignBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        assignmentFilter = group;
        areaSubFilter = "";
        if (loanAssignFilter) loanAssignFilter.value = assignmentFilter;
        loadLoans();

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

    loanAssignSeg.querySelectorAll(".seg__dropdown-item").forEach(item => {
      item.addEventListener("click", (e) => {
        e.stopPropagation();
        const place = item.getAttribute("data-place") || "";
        const dropdown = item.closest(".seg__dropdown");
        const parent = dropdown?.getAttribute("data-group") || assignmentFilter || "All";

        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");

        assignmentFilter = parent;
        areaSubFilter = place;
        assignBtns.forEach(b => b.classList.toggle("is-active", b.getAttribute("data-assign") === parent));
        const combined = areaSubFilter ? `${assignmentFilter}|${areaSubFilter}` : assignmentFilter;
        if (loanAssignFilter) loanAssignFilter.value = combined;
        closeAllDropdowns();
        loadLoans();
      });
    });

    if (!assignDropdownEventsBound) {
      document.addEventListener("click", (e) => {
        if (!loanAssignSeg.contains(e.target)) closeAllDropdowns();
      }, { capture: true });

      window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
      window.addEventListener("resize", refreshOpenDropdownPosition);
      contentScroller && contentScroller.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
      assignDropdownEventsBound = true;
    }
  }

  function resetLoanForm() {
    editingLoanId = null;
    if (loanDrawerTitle) loanDrawerTitle.textContent = "Add Loan";
    [loanEmpSearch, loanEmpNo, loanEmpName, loanEmpAssign, loanEmpDept, loanEmpPos].forEach(el => el && (el.value = ""));
    [loanRef, loanApprovalDate, loanReleaseDate, loanNotes, loanPrincipal, loanInterest, loanTotalPayable, loanMonthly, loanPerCutoff, loanTerm, loanPriority, loanApprovedBy, loanEncodedBy].forEach(el => el && (el.value = ""));
    if (loanType) loanType.value = loanType.querySelector("option")?.value || "";
    if (loanLender) loanLender.value = "SSS";
    if (loanStartCutoff) loanStartCutoff.value = "11-25";
    if (loanFrequency) loanFrequency.value = "every_cutoff";
    if (loanMethod) loanMethod.value = "fixed_months";
    if (loanStatus) loanStatus.value = "draft";
    if (loanSource) loanSource.value = "Agency";
    if (loanRecovery) loanRecovery.value = "Payroll deduction";
    if (loanAutoDeduct) loanAutoDeduct.checked = true;
    if (loanAllowPartial) loanAllowPartial.checked = true;
    if (loanCarryForward) loanCarryForward.checked = true;
    if (loanStopOnZero) loanStopOnZero.checked = true;
    if (loanEncodedBy) loanEncodedBy.value = currentUserName;

    if (loanStartMonth) {
      const now = new Date();
      loanStartMonth.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    }
  }

  async function openEditLoan(id) {
    const loan = await apiFetch(`/loans/${id}`);
    editingLoanId = id;
    if (loanDrawerTitle) loanDrawerTitle.textContent = "Edit Loan";

    if (loanEmpNo) loanEmpNo.value = loan.employee?.emp_no || "";
    if (loanEmpName) loanEmpName.value = loan.employee?.name || "";
    if (loanEmpAssign) loanEmpAssign.value = loan.employee?.assignment || "";
    if (loanEmpDept) loanEmpDept.value = loan.employee?.department || "";
    if (loanEmpPos) loanEmpPos.value = loan.employee?.position || "";

    loanType.value = loan.loan_type || "";
    loanLender.value = loan.lender || "";
    loanRef.value = loan.reference_no || "";
    loanApprovalDate.value = loan.approval_date || "";
    loanReleaseDate.value = loan.release_date || "";
    loanPrincipal.value = loan.principal_amount || 0;
    loanInterest.value = loan.interest_amount || 0;
    loanTotalPayable.value = loan.total_payable || 0;
    loanNotes.value = loan.notes || "";

    loanStartMonth.value = loan.start_month || "";
    loanStartCutoff.value = loan.start_cutoff || "11-25";
    loanFrequency.value = loan.deduction_frequency || "every_cutoff";
    loanMethod.value = loan.deduction_method || "fixed_months";
    loanMonthly.value = loan.monthly_amortization || 0;
    loanPerCutoff.value = loan.per_cutoff_amount || 0;
    loanTerm.value = loan.term_months || 1;

    loanAutoDeduct.checked = !!loan.auto_deduct;
    loanAllowPartial.checked = !!loan.allow_partial;
    loanCarryForward.checked = !!loan.carry_forward;
    loanStopOnZero.checked = !!loan.stop_on_zero;
    loanPriority.value = loan.priority_order || 100;
    loanStatus.value = loan.status || "draft";
    loanSource.value = loan.source || "Agency";
    loanRecovery.value = loan.recovery_type || "Payroll deduction";
    loanApprovedBy.value = loan.approved_by || "";
    loanEncodedBy.value = loan.encoded_by || "";

    showDrawer(loanDrawer, loanDrawerOverlay);
  }

  async function saveLoan() {
    const payload = {
      emp_no: loanEmpNo?.value || "",
      loan_type: loanType?.value || "",
      lender: loanLender?.value || "",
      reference_no: loanRef?.value || null,
      approval_date: loanApprovalDate?.value || null,
      release_date: loanReleaseDate?.value || null,
      principal_amount: Number(loanPrincipal?.value || 0),
      interest_amount: Number(loanInterest?.value || 0),
      total_payable: Number(loanTotalPayable?.value || 0),
      notes: loanNotes?.value || null,
      start_month: loanStartMonth?.value || "",
      start_cutoff: loanStartCutoff?.value || "11-25",
      deduction_frequency: loanFrequency?.value || "every_cutoff",
      deduction_method: loanMethod?.value || "fixed_months",
      monthly_amortization: Number(loanMonthly?.value || 0),
      per_cutoff_amount: Number(loanPerCutoff?.value || 0) || null,
      term_months: Number(loanTerm?.value || 1),
      auto_deduct: !!loanAutoDeduct?.checked,
      allow_partial: !!loanAllowPartial?.checked,
      carry_forward: !!loanCarryForward?.checked,
      stop_on_zero: !!loanStopOnZero?.checked,
      priority_order: Number(loanPriority?.value || 100),
      status: loanStatus?.value || "draft",
      source: loanSource?.value || null,
      recovery_type: loanRecovery?.value || null,
      approved_by: loanApprovedBy?.value || null,
      encoded_by: loanEncodedBy?.value || null,
    };

    if (!payload.emp_no && !editingLoanId) {
      alert("Select an employee first.");
      return;
    }

    if (editingLoanId) {
      await apiFetch(`/loans/${editingLoanId}`, {
        method: "PUT",
        body: JSON.stringify(payload),
      });
    } else {
      await apiFetch("/loans", {
        method: "POST",
        body: JSON.stringify(payload),
      });
    }

    hideDrawer(loanDrawer, loanDrawerOverlay);
    await loadLoans();
  }

  async function openDetail(id) {
    const loan = await apiFetch(`/loans/${id}`);
    const history = await apiFetch(`/loans/${id}/history`);

    if (loanDetailTitle) loanDetailTitle.textContent = `${loan.loan_no || "Loan"} — ${loan.employee?.name || ""}`;
    if (loanDetailSummary) {
      const deductionLabel = prettyLabel(loan.deduction_frequency);
      const statusLabel = prettyLabel(loan.status);
      loanDetailSummary.innerHTML = `
        <table class="summaryTable" aria-label="Loan summary">
          <tbody>
            <tr>
              <th scope="row">Loan Type</th>
              <td>${esc(loan.loan_type || "—")}</td>
              <th scope="row">Principal</th>
              <td class="num">${esc(money(loan.principal_amount))}</td>
            </tr>
            <tr>
              <th scope="row">Interest</th>
              <td class="num">${esc(money(loan.interest_amount))}</td>
              <th scope="row">Total Payable</th>
              <td class="num">${esc(money(loan.total_payable))}</td>
            </tr>
            <tr>
              <th scope="row">Paid</th>
              <td class="num">${esc(money(loan.amount_deducted))}</td>
              <th scope="row">Balance</th>
              <td class="num">${esc(money(loan.balance_remaining))}</td>
            </tr>
            <tr>
              <th scope="row">Status</th>
              <td>${esc(statusLabel)}</td>
              <th scope="row">Frequency</th>
              <td>${esc(deductionLabel)}</td>
            </tr>
          </tbody>
        </table>
      `;
    }

    if (loanHistoryTbody) {
      if (!history.length) {
        loanHistoryTbody.innerHTML = `<tr><td colspan="7" class="muted small">No history yet.</td></tr>`;
      } else {
        loanHistoryTbody.innerHTML = history.map(h => `
          <tr>
            <td>${h.run_code || h.payroll_run_id || "—"}</td>
            <td>${h.period_month || "—"} ${h.cutoff ? `(${h.cutoff})` : ""}</td>
            <td class="num">${money(h.scheduled_amount)}</td>
            <td class="num">${money(h.deducted_amount)}</td>
            <td class="num">${money(h.balance_after)}</td>
            <td>${h.status}</td>
            <td>${h.posted_at ? new Date(h.posted_at).toLocaleString() : "—"}</td>
          </tr>
        `).join("");
      }
    }

    showDrawer(loanDetailDrawer, loanDetailOverlay);
  }

  async function changeLoanStatus(id, action) {
    await apiFetch(`/loans/${id}/${action}`, { method: "POST" });
    await loadLoans();
  }

  // Event bindings
  [loanSearch, loanTypeFilter, loanStatusFilter, loanStartFilter, loanAssignFilter, loanDeptFilter].forEach(el => {
    el && el.addEventListener("input", () => loadLoans());
    el && el.addEventListener("change", () => loadLoans());
  });

  openAddLoanBtn && openAddLoanBtn.addEventListener("click", () => {
    resetLoanForm();
    showDrawer(loanDrawer, loanDrawerOverlay);
  });

  closeLoanDrawer && closeLoanDrawer.addEventListener("click", () => hideDrawer(loanDrawer, loanDrawerOverlay));
  cancelLoanBtn && cancelLoanBtn.addEventListener("click", () => hideDrawer(loanDrawer, loanDrawerOverlay));
  loanDrawerOverlay && loanDrawerOverlay.addEventListener("click", () => hideDrawer(loanDrawer, loanDrawerOverlay));

  closeLoanDetail && closeLoanDetail.addEventListener("click", () => hideDrawer(loanDetailDrawer, loanDetailOverlay));
  closeLoanDetailFooter && closeLoanDetailFooter.addEventListener("click", () => hideDrawer(loanDetailDrawer, loanDetailOverlay));
  loanDetailOverlay && loanDetailOverlay.addEventListener("click", () => hideDrawer(loanDetailDrawer, loanDetailOverlay));

  saveLoanBtn && saveLoanBtn.addEventListener("click", () => {
    saveLoan().catch(err => alert(err.message || "Failed to save loan."));
  });

  loanTbody && loanTbody.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;
    const action = btn.getAttribute("data-action");
    const id = btn.getAttribute("data-id");
    if (!action || !id) return;

    if (action === "view") {
      openDetail(id).catch(err => alert(err.message || "Failed to load details."));
      return;
    }
    if (action === "edit") {
      openEditLoan(id).catch(err => alert(err.message || "Failed to load loan."));
      return;
    }
    if (action === "pause" || action === "resume" || action === "close") {
      const ok = confirm(`Confirm ${action} loan?`);
      if (!ok) return;
      changeLoanStatus(id, action).catch(err => alert(err.message || "Failed to update loan."));
    }
  });

  // Employee search for add/edit
  loanEmpSearch && loanEmpSearch.addEventListener("input", async () => {
    const q = String(loanEmpSearch.value || "").trim();
    if (q.length < 2) {
      clearSuggest(loanEmpSuggest);
      return;
    }
    try {
      const rows = await apiFetch(`/employees/suggest?q=${encodeURIComponent(q)}`);
      if (!loanEmpSuggest) return;
      if (!rows.length) {
        clearSuggest(loanEmpSuggest);
        return;
      }
      loanEmpSuggest.innerHTML = rows.map(r => `<button type="button" class="suggest__item" data-emp="${r.emp_no}">${r.label}</button>`).join("");
      loanEmpSuggest.hidden = false;
    } catch {
      clearSuggest(loanEmpSuggest);
    }
  });

  loanEmpSuggest && loanEmpSuggest.addEventListener("click", async (e) => {
    const btn = e.target.closest(".suggest__item");
    if (!btn) return;
    const empNo = btn.getAttribute("data-emp") || "";
    clearSuggest(loanEmpSuggest);
    if (loanEmpSearch) loanEmpSearch.value = btn.textContent || "";
    if (loanEmpNo) loanEmpNo.value = empNo;

    try {
      const emps = await apiFetch(`/employees?q=${encodeURIComponent(empNo)}`);
      const emp = Array.isArray(emps) ? emps.find(e => String(e.emp_no) === String(empNo)) : null;
      if (emp) {
        if (loanEmpName) loanEmpName.value = `${emp.last_name}, ${emp.first_name}${emp.middle_name ? ` ${emp.middle_name}` : ""}`;
        if (loanEmpAssign) loanEmpAssign.value = emp.assignment_type || "";
        if (loanEmpDept) loanEmpDept.value = emp.department || "";
        if (loanEmpPos) loanEmpPos.value = emp.position || "";
      }
    } catch {
      // ignore
    }
  });

  document.addEventListener("click", (e) => {
    if (loanEmpSuggest && loanEmpSearch && !loanEmpSuggest.contains(e.target) && !loanEmpSearch.contains(e.target)) {
      clearSuggest(loanEmpSuggest);
    }
  });

  // Deduction cases: employee typeahead + actions
  function safeText(s) {
    return String(s || "").replace(/[&<>"']/g, (m) => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m]));
  }

  function clearDcTypeahead() {
    if (!dcEmployeeList) return;
    dcEmployeeList.hidden = true;
    dcEmployeeList.innerHTML = "";
  }

  function clearDcFormTypeahead() {
    if (!dcFormEmployeeList) return;
    dcFormEmployeeList.hidden = true;
    dcFormEmployeeList.innerHTML = "";
  }

  dcEmployeeInput && dcEmployeeInput.addEventListener("input", async () => {
    const q = String(dcEmployeeInput.value || "").trim();
    if (!dcEmployeeList) return;
    if (q.length < 2) {
      clearDcTypeahead();
      return;
    }
    try {
      const rows = await apiFetch(`/employees/suggest?q=${encodeURIComponent(q)}`);
      if (!Array.isArray(rows) || !rows.length) {
        clearDcTypeahead();
        return;
      }
      dcEmployeeList.innerHTML = rows
        .map((r) => `<button type="button" class="typeahead__item" data-emp="${r.emp_no}">${r.label}</button>`)
        .join("");
      dcEmployeeList.hidden = false;
    } catch {
      clearDcTypeahead();
    }
  });

  dcEmployeeList && dcEmployeeList.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-emp]");
    if (!btn) return;
    const empNo = btn.getAttribute("data-emp") || "";
    if (dcEmployeeEmpNo) dcEmployeeEmpNo.value = empNo;
    if (dcEmployeeInput) dcEmployeeInput.value = btn.textContent || "";
    clearDcTypeahead();
    await loadDeductionCases();
  });

  document.addEventListener("click", (e) => {
    if (dcEmployeeList && dcEmployeeInput && !dcEmployeeList.contains(e.target) && !dcEmployeeInput.contains(e.target)) {
      clearDcTypeahead();
    }
  });

  // Drawer employee typeahead (used when creating new charge/shortage)
  dcFormEmployeeInput && dcFormEmployeeInput.addEventListener("input", async () => {
    const q = String(dcFormEmployeeInput.value || "").trim();
    if (!dcFormEmployeeList) return;
    if (q.length < 2) {
      clearDcFormTypeahead();
      return;
    }
    try {
      const rows = await apiFetch(`/employees/suggest?q=${encodeURIComponent(q)}`);
      if (!Array.isArray(rows) || !rows.length) {
        clearDcFormTypeahead();
        return;
      }
      dcFormEmployeeList.innerHTML = rows
        .map((r) => `<button type="button" class="typeahead__item" data-emp="${safeText(r.emp_no)}">${safeText(r.label)}</button>`)
        .join("");
      dcFormEmployeeList.hidden = false;
    } catch {
      clearDcFormTypeahead();
    }
  });

  dcFormEmployeeList && dcFormEmployeeList.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-emp]");
    if (!btn) return;
    const empNo = btn.getAttribute("data-emp") || "";
    const label = btn.textContent || "";
    if (dcFormEmployeeEmpNo) dcFormEmployeeEmpNo.value = empNo;
    if (dcFormEmployeeInput) dcFormEmployeeInput.value = label;
    clearDcFormTypeahead();

    // Sync the selected employee to the list panel (so the table updates)
    if (dcEmployeeEmpNo) dcEmployeeEmpNo.value = empNo;
    if (dcEmployeeInput) dcEmployeeInput.value = label;
    await loadDeductionCases();
  });

  document.addEventListener("click", (e) => {
    if (dcFormEmployeeList && dcFormEmployeeInput && !dcFormEmployeeList.contains(e.target) && !dcFormEmployeeInput.contains(e.target)) {
      clearDcFormTypeahead();
    }
  });

  dcPlanType && dcPlanType.addEventListener("change", () => {
    const planType = String(dcPlanType.value || "installment");
    const isInstallment = planType === "installment";
    if (dcInstallmentWrap) dcInstallmentWrap.style.display = isInstallment ? "" : "none";
    if (dcInstallmentCount) dcInstallmentCount.required = isInstallment;
  });

  dcNewBtn && dcNewBtn.addEventListener("click", () => {
    showDcForm();
  });

  dcCancelBtn && dcCancelBtn.addEventListener("click", () => {
    hideDcForm();
  });

  closeDcDrawer && closeDcDrawer.addEventListener("click", () => {
    hideDcForm();
  });

  dcDrawerOverlay && dcDrawerOverlay.addEventListener("click", () => {
    hideDcForm();
  });

  dcForm && dcForm.addEventListener("submit", (e) => {
    e.preventDefault();
    createDeductionCase();
  });

  dcTbody && dcTbody.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-dc-close]");
    if (!btn) return;
    closeDeductionCase(btn.getAttribute("data-dc-close") || "");
  });

  // Auto-calc total payable
  function updateTotalPayable() {
    const principal = Number(loanPrincipal?.value || 0);
    const interest = Number(loanInterest?.value || 0);
    if (loanTotalPayable) loanTotalPayable.value = (principal + interest).toFixed(2);
    updateMonthlyFromTotal();
  }

  loanPrincipal && loanPrincipal.addEventListener("input", updateTotalPayable);
  loanInterest && loanInterest.addEventListener("input", updateTotalPayable);

  function updateMonthlyFromTotal() {
    if (loanMethod?.value !== "fixed_months") return;
    const total = Number(loanTotalPayable?.value || 0);
    const term = Number(loanTerm?.value || 0);
    if (loanMonthly && total > 0 && term > 0) {
      loanMonthly.value = (total / term).toFixed(2);
    }
  }

  loanTotalPayable && loanTotalPayable.addEventListener("input", updateMonthlyFromTotal);
  loanTerm && loanTerm.addEventListener("input", updateMonthlyFromTotal);
  loanMethod && loanMethod.addEventListener("change", updateMonthlyFromTotal);

  // Init
  initLoansTabs();
  if (dcStartMonth && !dcStartMonth.value) {
    const d = new Date();
    dcStartMonth.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
  }
  if (dcPlanType) dcPlanType.dispatchEvent(new Event("change"));
  renderDeductionCases();

  assignmentFilter = currentAssignmentFilter();
  if (loanAssignFilter) {
    loanAssignFilter.value = areaSubFilter ? `${assignmentFilter}|${areaSubFilter}` : assignmentFilter;
  }
  wireAssignButtons();
  loadFilters().then(loadLoans).catch(() => loadLoans());
});
