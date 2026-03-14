import { esc } from "./utils";
import { createDrawer } from "./drawer";

export function initCashAdvancePolicy(toast, apiFetch, noticeEl, onChange) {
  const caEnabled = document.getElementById("caEnabled");
  const caMethod = document.getElementById("caMethod");
  const caDefaultTermMonths = document.getElementById("caDefaultTermMonths");
  const caMaxPaybackMonths = document.getElementById("caMaxPaybackMonths");
  const caDeductTiming = document.getElementById("caDeductTiming");
  const caPriority = document.getElementById("caPriority");
  const caDeductionMethod = document.getElementById("caDeductionMethod");
  const caPolicySave = document.getElementById("caPolicySave");

  if (!caPolicySave && !caEnabled && !caMethod) return;

  function showNotice(message) {
    if (!noticeEl) return false;
    noticeEl.textContent = message;
    noticeEl.hidden = false;
    clearTimeout(noticeEl._hideTimer);
    noticeEl._hideTimer = setTimeout(() => {
      noticeEl.hidden = true;
    }, 3500);
    return true;
  }

  async function loadCashAdvancePolicy() {
    try {
      const row = await apiFetch("/settings/cash-advance-policy");
      caEnabled && (caEnabled.checked = !!row.enabled);
      caMethod && (caMethod.value = row.default_method ?? "salary_deduction");
      caDefaultTermMonths && (caDefaultTermMonths.value = row.default_term_months ?? 3);
      caMaxPaybackMonths && (caMaxPaybackMonths.value = row.max_payback_months ?? 6);
      caDeductTiming && (caDeductTiming.value = row.deduct_timing ?? "split");
      caPriority && (caPriority.value = row.priority ?? 1);
      caDeductionMethod && (caDeductionMethod.value = row.deduction_method ?? "equal_amortization");
    } catch (err) {
      toast(err.message || "Failed to load Cash Advance Policy.", "error");
    }
  }

  caPolicySave?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/cash-advance-policy", {
        method: "POST",
        body: JSON.stringify({
          enabled: !!caEnabled?.checked,
          default_method: caMethod?.value || "salary_deduction",
          default_term_months: Number(caDefaultTermMonths?.value || 3),
          max_payback_months: Number(caMaxPaybackMonths?.value || 6),
          deduct_timing: caDeductTiming?.value || "split",
          priority: Number(caPriority?.value || 1),
          deduction_method: caDeductionMethod?.value || "equal_amortization",
        }),
      });
      if (!showNotice("Cash advance policy saved.")) toast("Saved Cash Advance Policy.");
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to save Cash Advance Policy.", "error");
    }
  });

  loadCashAdvancePolicy();
}

export function initCashAdvanceTransactions(toast, apiFetch, noticeEl, onChange) {
  const caTbody = document.getElementById("caTbody");
  const newCaBtn = document.getElementById("newCaBtn");

  const caDrawer = document.getElementById("caDrawer");
  const caDrawerOverlay = document.getElementById("caDrawerOverlay");
  const closeCaDrawer = document.getElementById("closeCaDrawer");
  const cancelCaBtn = document.getElementById("cancelCaBtn");
  const caForm = document.getElementById("caForm");

  const caViewDrawer = document.getElementById("caViewDrawer");
  const caViewDrawerOverlay = document.getElementById("caViewDrawerOverlay");
  const closeCaViewDrawer = document.getElementById("closeCaViewDrawer");
  const closeCaViewBtn = document.getElementById("closeCaViewBtn");

  const caViewTitle = document.getElementById("caViewDrawerTitle");
  const caViewSubtitle = document.getElementById("caViewDrawerSubtitle");
  const caViewEmployee = document.getElementById("caViewEmployee");
  const caViewAmount = document.getElementById("caViewAmount");
  const caViewTerm = document.getElementById("caViewTerm");
  const caViewStart = document.getElementById("caViewStart");
  const caViewStatus = document.getElementById("caViewStatus");

  const caActionBanner = document.getElementById("caActionBanner");
  const caEmployeeInput = document.getElementById("caEmployeeInput");
  const caEmployeeId = document.getElementById("caEmployeeId");
  const caEmployeeList = document.getElementById("caEmployeeList");

  if (!caTbody && !newCaBtn && !caDrawer && !caViewDrawer) return;

  if (caDrawer && caDrawer.parentElement !== document.body) document.body.appendChild(caDrawer);
  if (caViewDrawer && caViewDrawer.parentElement !== document.body) document.body.appendChild(caViewDrawer);

  const drawer = createDrawer(caDrawer, caDrawerOverlay, [closeCaDrawer, cancelCaBtn]);
  const viewDrawer = createDrawer(caViewDrawer, caViewDrawerOverlay, [closeCaViewDrawer, closeCaViewBtn]);

  let cashAdvances = [];
  let employees = [];
  let employeeLabelToId = new Map();
  let defaultTermMonths = 3;
  let defaultMethod = "salary_deduction";

  function showNotice(message) {
    if (!noticeEl) return false;
    noticeEl.textContent = message;
    noticeEl.hidden = false;
    clearTimeout(noticeEl._hideTimer);
    noticeEl._hideTimer = setTimeout(() => {
      noticeEl.hidden = true;
    }, 3500);
    return true;
  }

  function showBanner(message) {
    if (!caActionBanner) return false;
    caActionBanner.textContent = message;
    caActionBanner.hidden = false;
    clearTimeout(caActionBanner._hideTimer);
    caActionBanner._hideTimer = setTimeout(() => {
      caActionBanner.hidden = true;
    }, 4000);
    return true;
  }

  function peso(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return `₱ ${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function openViewDrawer(row, mode) {
    if (!row) return;
    if (caViewTitle) caViewTitle.textContent = mode === "edit" ? "Edit Cash Advance" : "Cash Advance Details";
    if (caViewSubtitle) caViewSubtitle.textContent = mode === "edit" ? "Edit cash advance entry (UI only)." : "View cash advance entry.";
    if (caViewEmployee) caViewEmployee.value = row.employee_name || "";
    if (caViewAmount) caViewAmount.value = peso(row.amount || 0);
    if (caViewTerm) caViewTerm.value = row.term_months || "";
    if (caViewStart) caViewStart.value = String(row.start_month || "").slice(0, 7);
    if (caViewStatus) caViewStatus.value = row.status || "";
    viewDrawer?.open();
  }

  function formatPesoInput(raw) {
    const cleaned = String(raw || "").replace(/[^0-9.]/g, "");
    if (!cleaned) return { display: "", value: 0 };
    const parts = cleaned.split(".");
    const intPart = parts[0] || "0";
    const decPart = parts[1] ? parts[1].slice(0, 2) : "";
    const num = Number(`${intPart}.${decPart || ""}`);
    const formattedInt = Number(intPart || 0).toLocaleString();
    const formatted = decPart.length ? `${formattedInt}.${decPart}` : formattedInt;
    return { display: `₱${formatted}`, value: Number.isFinite(num) ? num : 0 };
  }

  function syncAmountInput() {
    const el = document.getElementById("caAmount");
    const hidden = document.getElementById("caAmountValue");
    if (!el || !hidden) return;
    const { display, value } = formatPesoInput(el.value);
    el.value = display;
    hidden.value = value ? String(value) : "";
    const len = el.value.length;
    el.setSelectionRange(len, len);
  }

  function getAmountValue() {
    const hidden = document.getElementById("caAmountValue");
    if (hidden && hidden.value) return Number(hidden.value);
    const el = document.getElementById("caAmount");
    const { value } = formatPesoInput(el?.value || "");
    return value;
  }

  function renderCa() {
    if (!caTbody) return;
    caTbody.innerHTML = "";
    if (!cashAdvances.length) {
      caTbody.innerHTML = `<tr><td colspan="7" class="muted">No cash advance entries yet.</td></tr>`;
      return;
    }

    cashAdvances.forEach((row) => {
      const per = Number(row.per_cutoff_deduction ?? 0);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${esc(row.employee_name || "—")}</td>
        <td>${esc(peso(row.amount))}</td>
        <td>${esc(row.term_months)} mo</td>
        <td>${esc(String(row.start_month || "").slice(0, 7))}</td>
        <td>${esc(peso(per))}</td>
        <td>${esc(row.status)}</td>
        <td>
          <div class="miniRow">
            <button class="miniBtn" data-ca="edit" data-id="${row.id}" aria-label="View">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-ca="close" data-id="${row.id}" aria-label="Close">
              <svg class="miniBtn__icon" viewBox="0 0 24 24"><path d="m7 7 10 10"/><path d="m17 7-10 10"/></svg>
            </button>
          </div>
        </td>
      `;
      caTbody.appendChild(tr);
    });
  }

  function openNewCa() {
    caForm?.reset();
    document.getElementById("caTerm") && (document.getElementById("caTerm").value = String(defaultTermMonths || 3));
    document.getElementById("caMethodTxn") && (document.getElementById("caMethodTxn").value = defaultMethod || "salary_deduction");
    if (caEmployeeInput) caEmployeeInput.value = "";
    if (caEmployeeId) caEmployeeId.value = "";
    if (caEmployeeList) { caEmployeeList.innerHTML = ""; caEmployeeList.hidden = true; }
    drawer?.open();
  }

  newCaBtn?.addEventListener("click", openNewCa);
  document.getElementById("caAmount")?.addEventListener("input", syncAmountInput);
  document.getElementById("caAmount")?.addEventListener("blur", syncAmountInput);

  caForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const resolvedId = Number(caEmployeeId?.value || 0);
    const amount = getAmountValue();
    const term = Number(document.getElementById("caTerm")?.value || 1);
    const start = document.getElementById("caStartMonth")?.value;
    const method = document.getElementById("caMethodTxn")?.value;

    if (!resolvedId || !start || !Number.isFinite(amount) || amount <= 0) {
      toast("Please fill Employee, Amount, Start month.", "error");
      return;
    }

    try {
      await apiFetch("/settings/cash-advances", {
        method: "POST",
        body: JSON.stringify({
          employee_id: resolvedId,
          amount,
          term_months: term,
          start_month: start,
          method,
        }),
      });
      await loadCashAdvances();
      drawer?.close();
      if (!showNotice("Cash advance added.")) toast("Cash advance added.");
      showBanner("Cash advance entry saved.");
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to add cash advance.", "error");
    }
  });

  caTbody?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-ca]");
    if (!btn) return;
    const act = btn.getAttribute("data-ca");
    const id = Number(btn.getAttribute("data-id"));
    const row = cashAdvances.find((x) => x.id === id);
    if (!row) return;

    if (act === "edit") openViewDrawer(row, "view");

    if (act === "close") {
      if (!confirm("Close this cash advance?")) return;
      apiFetch(`/settings/cash-advances/${id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: "Completed" }),
      })
        .then(() => loadCashAdvances())
        .then(() => {
          if (!showNotice("Cash advance closed.")) toast("Cash advance closed.");
          if (typeof onChange === "function") onChange();
        })
        .catch((err) => toast(err.message || "Failed to close cash advance.", "error"));
    }
  });

  async function loadEmployees() {
    try {
      const rows = await apiFetch("/employees");
      employees = Array.isArray(rows) ? rows : [];
      if (caEmployeeInput && caEmployeeInput.value.trim()) {
        renderEmployeeOptions(caEmployeeInput.value);
      } else if (caEmployeeList) {
        caEmployeeList.innerHTML = "";
        caEmployeeList.hidden = true;
      }
    } catch (err) {
      toast(err.message || "Failed to load employees.", "error");
    }
  }

  function buildEmployeeLabel(e) {
    const name = `${e.last_name || ""}, ${e.first_name || ""}${e.middle_name ? " " + e.middle_name : ""}`.trim();
    const empNo = e.emp_no || e.emp_id || e.empId || "";
    return `${empNo} ${name}`.trim();
  }

  function syncEmployeeIdFromInput() {
    if (!caEmployeeInput || !caEmployeeId) return;
    const label = caEmployeeInput.value.trim();
    const id = employeeLabelToId.get(label);
    caEmployeeId.value = id ? String(id) : "";
  }

  function renderEmployeeOptions(term) {
    if (!caEmployeeList) return;
    const q = String(term || "").trim().toLowerCase();
    if (!q) {
      caEmployeeList.innerHTML = "";
      caEmployeeList.hidden = true;
      return;
    }
    const filtered = employees.filter((e) => buildEmployeeLabel(e).toLowerCase().includes(q));

    employeeLabelToId = new Map();
    if (!filtered.length) {
      caEmployeeList.innerHTML = `<div class="typeahead__empty">No matches found</div>`;
      caEmployeeList.hidden = false;
      return;
    }

    caEmployeeList.innerHTML = filtered.map((e) => {
      const label = buildEmployeeLabel(e);
      employeeLabelToId.set(label, e.id);
      return `<div class="typeahead__item" data-id="${e.id}" data-label="${label}">${label}</div>`;
    }).join("");
    caEmployeeList.hidden = false;
  }

  caEmployeeInput?.addEventListener("input", (e) => {
    renderEmployeeOptions(e.target.value);
    syncEmployeeIdFromInput();
  });
  caEmployeeInput?.addEventListener("focus", (e) => {
    renderEmployeeOptions(e.target.value);
  });
  caEmployeeInput?.addEventListener("blur", () => {
    setTimeout(() => {
      if (caEmployeeList) caEmployeeList.hidden = true;
    }, 120);
  });
  caEmployeeInput?.addEventListener("change", syncEmployeeIdFromInput);
  caEmployeeList?.addEventListener("mousedown", (e) => {
    const item = e.target.closest(".typeahead__item");
    if (!item || !caEmployeeInput || !caEmployeeId) return;
    caEmployeeInput.value = item.dataset.label || "";
    caEmployeeId.value = item.dataset.id || "";
    caEmployeeList.hidden = true;
  });

  async function loadPolicyDefaults() {
    try {
      const row = await apiFetch("/settings/cash-advance-policy");
      defaultMethod = row.default_method ?? "salary_deduction";
      defaultTermMonths = row.default_term_months ?? 3;
    } catch {
      // optional
    }
  }

  async function loadCashAdvances() {
    try {
      const rows = await apiFetch("/settings/cash-advances");
      cashAdvances = Array.isArray(rows) ? rows : [];
      renderCa();
    } catch (err) {
      toast(err.message || "Failed to load Cash Advances.", "error");
    }
  }

  loadEmployees();
  loadPolicyDefaults();
  loadCashAdvances();
}

export function initCashAdvance(toast, apiFetch, noticeEl, onChange) {
  initCashAdvancePolicy(toast, apiFetch, noticeEl, onChange);
  initCashAdvanceTransactions(toast, apiFetch, noticeEl, onChange);
}

