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
  initCashAdvanceTransactions(toast, apiFetch, document.getElementById("cashAdvanceTxnNotice"));

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
    loanTbody.innerHTML = `<tr><td colspan="12" class="muted small">Loading...</td></tr>`;

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
      loanTbody.innerHTML = `<tr><td colspan="12" class="muted small">Failed to load loans.</td></tr>`;
    }
  }

  function renderLoans() {
    if (!loanTbody) return;
    if (!loans.length) {
      loanTbody.innerHTML = `<tr><td colspan="12" class="muted small">No loans found.</td></tr>`;
      if (loanMeta) loanMeta.textContent = "No loans found.";
      return;
    }
    if (loanMeta) loanMeta.textContent = `Showing ${loans.length} loan(s).`;

    loanTbody.innerHTML = loans.map(l => {
      const payrollStatus = l.auto_deduct ? (l.status === "active" ? "For Payroll Deduction" : "Not Deducting") : "Manual";
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
    if (loanType) loanType.value = "SSS Salary Loan";
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
      loanDetailSummary.innerHTML = `
        <div class="summaryLine"><span>Loan Type</span><strong>${loan.loan_type}</strong></div>
        <div class="summaryLine"><span>Principal</span><strong>${money(loan.principal_amount)}</strong></div>
        <div class="summaryLine"><span>Interest</span><strong>${money(loan.interest_amount)}</strong></div>
        <div class="summaryLine"><span>Total Payable</span><strong>${money(loan.total_payable)}</strong></div>
        <div class="summaryLine"><span>Paid</span><strong>${money(loan.amount_deducted)}</strong></div>
        <div class="summaryLine"><span>Balance</span><strong>${money(loan.balance_remaining)}</strong></div>
        <div class="summaryLine"><span>Status</span><strong>${loan.status}</strong></div>
        <div class="summaryLine"><span>Deduction Setup</span><strong>${loan.deduction_frequency}</strong></div>
      `;
    }

    if (loanHistoryTbody) {
      if (!history.length) {
        loanHistoryTbody.innerHTML = `<tr><td colspan="7" class="muted small">No history yet.</td></tr>`;
      } else {
        loanHistoryTbody.innerHTML = history.map(h => `
          <tr>
            <td>${h.payroll_run_id || "—"}</td>
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

  // Auto-calc total payable
  function updateTotalPayable() {
    const principal = Number(loanPrincipal?.value || 0);
    const interest = Number(loanInterest?.value || 0);
    if (loanTotalPayable) loanTotalPayable.value = (principal + interest).toFixed(2);
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
  assignmentFilter = currentAssignmentFilter();
  if (loanAssignFilter) {
    loanAssignFilter.value = areaSubFilter ? `${assignmentFilter}|${areaSubFilter}` : assignmentFilter;
  }
  wireAssignButtons();
  loadFilters().then(loadLoans).catch(() => loadLoans());
});


