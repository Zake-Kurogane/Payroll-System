import { esc } from "./utils";
import { createDrawer } from "./drawer";

export function initCashAdvancePolicy(toast, apiFetch, noticeEl, onChange) {
  const caEnabled = document.getElementById("caEnabled");
  const caMethod = document.getElementById("caMethod");
  const caDefaultTermMonths = document.getElementById("caDefaultTermMonths");
  const caRegularTermMonths = document.getElementById("caRegularTermMonths");
  const caProbationaryTermMonths = document.getElementById("caProbationaryTermMonths");
  const caTraineeTermMonths = document.getElementById("caTraineeTermMonths");
  const caTraineeForceFullDeduct = document.getElementById("caTraineeForceFullDeduct");
  const caAllowFullDeduct = document.getElementById("caAllowFullDeduct");
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
      caRegularTermMonths && (caRegularTermMonths.value = row.regular_default_term_months ?? row.default_term_months ?? 3);
      caProbationaryTermMonths && (caProbationaryTermMonths.value = row.probationary_term_months ?? 1);
      caTraineeTermMonths && (caTraineeTermMonths.value = row.trainee_term_months ?? 1);
      caTraineeForceFullDeduct && (caTraineeForceFullDeduct.checked = row.trainee_force_full_deduct !== false);
      caAllowFullDeduct && (caAllowFullDeduct.checked = row.allow_full_deduct !== false);
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
          regular_default_term_months: Number(caRegularTermMonths?.value || caDefaultTermMonths?.value || 3),
          probationary_term_months: Number(caProbationaryTermMonths?.value || 1),
          trainee_term_months: Number(caTraineeTermMonths?.value || 1),
          trainee_force_full_deduct: !!caTraineeForceFullDeduct?.checked,
          allow_full_deduct: !!caAllowFullDeduct?.checked,
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
  const caViewId = document.getElementById("caViewId");
  const caViewEmployee = document.getElementById("caViewEmployee");
  const caViewAmount = document.getElementById("caViewAmount");
  const caViewAmountValue = document.getElementById("caViewAmountValue");
  const caViewTerm = document.getElementById("caViewTerm");
  const caViewStart = document.getElementById("caViewStart");
  const caViewStatus = document.getElementById("caViewStatus");
  const caViewPerCutoffPreviewEl = document.getElementById("caViewPerCutoffPreview");
  const caViewCutoffMetaEl = document.getElementById("caViewCutoffMeta");
  const caViewFullDeductEl = document.getElementById("caViewFullDeduct");
  const caViewMethodTxn = document.getElementById("caViewMethodTxn");
  const caViewForm = document.getElementById("caViewForm");
  const saveCaViewBtn = document.getElementById("saveCaViewBtn");

  const caActionBanner = document.getElementById("caActionBanner");
  const caEmployeeInput = document.getElementById("caEmployeeInput");
  const caEmployeeId = document.getElementById("caEmployeeId");
  const caEmployeeList = document.getElementById("caEmployeeList");

  if (!caTbody && !newCaBtn && !caDrawer && !caViewDrawer) return;

  const PHP_SIGN = "\u20B1";
  const EM_DASH = "\u2014";

  function pesoSafe(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return EM_DASH;
    return `${PHP_SIGN} ${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function formatPesoInputSafe(raw) {
    const cleaned = String(raw || "").replace(/[^0-9.]/g, "");
    if (!cleaned) return { display: "", value: 0 };
    const parts = cleaned.split(".");
    const intPart = parts[0] || "0";
    const decPart = parts[1] ? parts[1].slice(0, 2) : "";
    const num = Number(`${intPart}.${decPart || ""}`);
    const formattedInt = Number(intPart || 0).toLocaleString();
    const formatted = decPart.length ? `${formattedInt}.${decPart}` : formattedInt;
    return { display: `${PHP_SIGN}${formatted}`, value: Number.isFinite(num) ? num : 0 };
  }

  if (caDrawer && caDrawer.parentElement !== document.body) document.body.appendChild(caDrawer);
  if (caViewDrawer && caViewDrawer.parentElement !== document.body) document.body.appendChild(caViewDrawer);

  const drawer = createDrawer(caDrawer, caDrawerOverlay, [closeCaDrawer, cancelCaBtn]);
  const viewDrawer = createDrawer(caViewDrawer, caViewDrawerOverlay, [closeCaViewDrawer, closeCaViewBtn]);

  let cashAdvances = [];
  let employees = [];
  let employeeLabelToId = new Map();
  let defaultTermMonths = 3;
  let defaultMethod = "salary_deduction";
  let regularTermMonths = 3;
  let probationaryTermMonths = 1;
  let traineeTermMonths = 1;
  let traineeForceFullDeduct = true;
  let allowFullDeduct = true;

  const caTermEl = document.getElementById("caTerm");
  const caPerCutoffPreviewEl = document.getElementById("caPerCutoffPreview");
  const caCutoffMetaEl = document.getElementById("caCutoffMeta");

  let addForcedFullDeduct = false;
  let activeEditRow = null;

  function normalizeEmploymentType(raw) {
    const v = String(raw || "").trim().toLowerCase();
    if (v === "trainee" || v === "trainees") return "trainees";
    if (v === "probationary" || v === "probi") return "probationary";
    if (v.includes("non") && v.includes("regular")) return "probationary";
    return "regular";
  }

  function getEmployeeById(id) {
    return employees.find((e) => Number(e.id) === Number(id)) || null;
  }

  function applyRulesForEmployeeId(id) {
    if (!caTermEl) return;
    const emp = getEmployeeById(id);
    const et = normalizeEmploymentType(emp?.employment_type);

    if (et === "trainees") {
      addForcedFullDeduct = !!traineeForceFullDeduct;
      caTermEl.value = addForcedFullDeduct ? "1" : String(traineeTermMonths || 1);
      caTermEl.disabled = addForcedFullDeduct;
      updatePerCutoffPreviewSafe();
      return;
    }

    if (et === "probationary") {
      caTermEl.value = String(probationaryTermMonths || 1);
      addForcedFullDeduct = false;
      caTermEl.disabled = false;
      updatePerCutoffPreviewSafe();
      return;
    }

    // regular
    addForcedFullDeduct = false;
    caTermEl.value = String(regularTermMonths || defaultTermMonths || 3);
    caTermEl.disabled = false;
    updatePerCutoffPreviewSafe();
  }

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
    activeEditRow = row;
    if (caViewTitle) caViewTitle.textContent = mode === "edit" ? "Edit Cash Advance" : "Cash Advance Details";
    if (caViewSubtitle) caViewSubtitle.textContent = mode === "edit" ? "Edit cash advance entry." : "View cash advance entry.";
    if (caViewId) caViewId.value = String(row.id || "");
    if (caViewEmployee) caViewEmployee.value = row.employee_name || "";
    const amt = Number(row.amount || 0);
    if (caViewAmount) caViewAmount.value = amt > 0 ? `â‚±${amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : "";
    if (caViewAmountValue) caViewAmountValue.value = amt > 0 ? String(amt) : "";
    if (caViewAmount) caViewAmount.value = amt > 0 ? pesoSafe(amt) : "";
    if (caViewTerm) caViewTerm.value = row.term_months || "";
    if (caViewStart) caViewStart.value = String(row.start_month || "").slice(0, 7);
    if (caViewStatus) caViewStatus.value = row.status || "";
    if (caViewMethodTxn) caViewMethodTxn.value = row.method || "salary_deduction";
    if (caViewFullDeductEl) caViewFullDeductEl.checked = !!row.full_deduct;

    const editable = mode === "edit";
    if (caViewAmount) caViewAmount.readOnly = true;
    if (caViewTerm) caViewTerm.disabled = true;
    if (caViewStart) caViewStart.disabled = true;
    if (caViewMethodTxn) caViewMethodTxn.disabled = !editable;
    if (caViewFullDeductEl) caViewFullDeductEl.disabled = !editable;
    if (saveCaViewBtn) saveCaViewBtn.hidden = !editable;

    updateEditPerCutoffPreviewSafe();
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
    const { display, value } = formatPesoInputSafe(el.value);
    el.value = display;
    hidden.value = value ? String(value) : "";
    const len = el.value.length;
    el.setSelectionRange(len, len);
    updatePerCutoffPreviewSafe();
  }

  function getAmountValue() {
    const hidden = document.getElementById("caAmountValue");
    if (hidden && hidden.value) return Number(hidden.value);
    const el = document.getElementById("caAmount");
    const { value } = formatPesoInputSafe(el?.value || "");
    return value;
  }

  function updatePerCutoffPreviewSafe() {
    if (!caPerCutoffPreviewEl) return;

    const amount = getAmountValue();
    const termMonths = Math.max(1, Number(caTermEl?.value || 1));
    const fullDeduct = !!addForcedFullDeduct;

    if (!Number.isFinite(amount) || amount <= 0) {
      caPerCutoffPreviewEl.value = "";
      if (caCutoffMetaEl) {
        caCutoffMetaEl.textContent = fullDeduct
          ? "Trainee: next cutoff will attempt to deduct the full amount."
          : "Auto-calculated from Amount / (Term x 2 cutoffs).";
      }
      return;
    }

    if (fullDeduct) {
      caPerCutoffPreviewEl.value = pesoSafe(amount);
      if (caCutoffMetaEl) caCutoffMetaEl.textContent = "Trainee: next cutoff will attempt to deduct the full amount (remainder carries forward).";
      return;
    }

    const cutoffs = termMonths * 2;
    const per = amount / cutoffs;
    caPerCutoffPreviewEl.value = pesoSafe(per);
    if (caCutoffMetaEl) caCutoffMetaEl.textContent = `Auto-calculated: ${pesoSafe(amount)} / (${termMonths} mo x 2 = ${cutoffs} cutoffs) = ${pesoSafe(per)} per cutoff.`;
  }

  function updatePerCutoffPreview() {
    return updatePerCutoffPreviewSafe();

    const amount = getAmountValue();
    const termMonths = Math.max(1, Number(caTermEl?.value || 1));
    const fullDeduct = !!addForcedFullDeduct;

    if (!Number.isFinite(amount) || amount <= 0) {
      caPerCutoffPreviewEl.value = "";
      if (caCutoffMetaEl) {
        caCutoffMetaEl.textContent = fullDeduct
          ? "Trainee: next cutoff will attempt to deduct the full amount."
          : "Auto-calculated from Amount ÷ (Term × 2 cutoffs).";
      }
      return;
    }

    if (fullDeduct) {
      caPerCutoffPreviewEl.value = peso(amount);
      if (caCutoffMetaEl) caCutoffMetaEl.textContent = "Trainee: next cutoff will attempt to deduct the full amount (remainder carries forward).";
      return;
    }

    const cutoffs = termMonths * 2;
    const per = amount / cutoffs;
    caPerCutoffPreviewEl.value = peso(per);
    if (caCutoffMetaEl) caCutoffMetaEl.textContent = `Auto-calculated: ${peso(amount)} ÷ (${termMonths} mo × 2 = ${cutoffs} cutoffs) = ${peso(per)} per cutoff.`;
  }

  function renderCaSafe() {
    if (!caTbody) return;
    caTbody.innerHTML = "";
    if (!cashAdvances.length) {
      caTbody.innerHTML = `<tr><td colspan="8" class="muted">No cash advance entries yet.</td></tr>`;
      return;
    }

    cashAdvances.forEach((row) => {
      const per = Number(row.per_cutoff_deduction ?? 0);
      const remaining = Number(row.balance_remaining ?? (Number(row.amount ?? 0) - Number(row.amount_deducted ?? 0)));
      const statusRaw = String(row.status || "").trim();
      const statusKey = statusRaw.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "default";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${esc(row.employee_name || EM_DASH)}</td>
        <td>${esc(pesoSafe(row.amount))}</td>
        <td>${esc(pesoSafe(remaining))}</td>
        <td>${esc(row.term_months)} mo</td>
        <td>${esc(String(row.start_month || "").slice(0, 7))}</td>
        <td>${esc(pesoSafe(per))}</td>
        <td><span class="statusChip statusChip--${esc(statusKey)}">${esc(statusRaw || EM_DASH)}</span></td>
        <td>
          <div class="miniRow">
            <button class="miniBtn" data-ca="edit" data-id="${row.id}" aria-label="Edit">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-ca="delete" data-id="${row.id}" aria-label="Delete">
              <svg class="miniBtn__icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 16h10l1-16"/></svg>
            </button>
          </div>
        </td>
      `;
      caTbody.appendChild(tr);
    });
  }

  function renderCa() {
    return renderCaSafe();
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
            <button class="miniBtn" data-ca="edit" data-id="${row.id}" aria-label="Edit">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-ca="delete" data-id="${row.id}" aria-label="Delete">
              <svg class="miniBtn__icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 16h10l1-16"/></svg>
            </button>
          </div>
        </td>
      `;
      caTbody.appendChild(tr);
    });
  }

  function openNewCa() {
    caForm?.reset();
    caTermEl && (caTermEl.value = String(regularTermMonths || defaultTermMonths || 3));
    document.getElementById("caMethodTxn") && (document.getElementById("caMethodTxn").value = defaultMethod || "salary_deduction");
    addForcedFullDeduct = false;
    if (caTermEl) caTermEl.disabled = false;
    if (caEmployeeInput) caEmployeeInput.value = "";
    if (caEmployeeId) caEmployeeId.value = "";
    if (caEmployeeList) { caEmployeeList.innerHTML = ""; caEmployeeList.hidden = true; }
    updatePerCutoffPreviewSafe();
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

    if (act === "edit") openViewDrawer(row, "edit");

    if (act === "delete") {
      if (!confirm("Delete this cash advance entry?")) return;
      apiFetch(`/settings/cash-advances/${id}`, { method: "DELETE" })
        .then(() => loadCashAdvances())
        .then(() => {
          if (!showNotice("Cash advance deleted.")) toast("Cash advance deleted.");
          if (typeof onChange === "function") onChange();
        })
        .catch((err) => toast(err.message || "Failed to delete cash advance.", "error"));
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
    applyRulesForEmployeeId(Number(caEmployeeId.value || 0));
  });

  caTermEl?.addEventListener("input", updatePerCutoffPreviewSafe);
  caTermEl?.addEventListener("change", updatePerCutoffPreviewSafe);

  function syncPesoInput(inputEl, hiddenEl) {
    if (!inputEl || !hiddenEl) return;
    const { display, value } = formatPesoInputSafe(inputEl.value);
    inputEl.value = display;
    hiddenEl.value = value ? String(value) : "";
    const len = inputEl.value.length;
    inputEl.setSelectionRange(len, len);
  }

  function getHiddenAmountValue(hiddenEl, inputEl) {
    if (hiddenEl && hiddenEl.value) return Number(hiddenEl.value);
    const { value } = formatPesoInputSafe(inputEl?.value || "");
    return value;
  }

  function updateEditPerCutoffPreviewSafe() {
    if (!activeEditRow || !caViewPerCutoffPreviewEl) return;
    const amount = getHiddenAmountValue(caViewAmountValue, caViewAmount);
    const termMonths = Math.max(1, Number(caViewTerm?.value || 1));
    const fullDeduct = !!caViewFullDeductEl?.checked;
    const deducted = Number(activeEditRow.amount_deducted ?? 0);
    const remaining = Math.max(0, Number.isFinite(amount) ? (amount - deducted) : 0);

    if (!Number.isFinite(amount) || amount <= 0) {
      caViewPerCutoffPreviewEl.value = "";
      return;
    }

    if (fullDeduct) {
      caViewPerCutoffPreviewEl.value = pesoSafe(remaining);
      if (caViewCutoffMetaEl) caViewCutoffMetaEl.textContent = "Full deduct: next cutoff will attempt to deduct the remaining balance (remainder carries forward).";
      return;
    }

    const cutoffs = termMonths * 2;
    const per = remaining / Math.max(1, cutoffs);
    caViewPerCutoffPreviewEl.value = pesoSafe(per);
    if (caViewCutoffMetaEl) caViewCutoffMetaEl.textContent = `Auto-calculated: remaining ${pesoSafe(remaining)} / (${termMonths} mo x 2 = ${cutoffs} cutoffs) = ${pesoSafe(per)} per cutoff.`;
  }

  function updateEditPerCutoffPreview() {
    return updateEditPerCutoffPreviewSafe();
    const amount = getHiddenAmountValue(caViewAmountValue, caViewAmount);
    const termMonths = Math.max(1, Number(caViewTerm?.value || 1));
    const fullDeduct = !!caViewFullDeductEl?.checked;
    const deducted = Number(activeEditRow.amount_deducted ?? 0);
    const remaining = Math.max(0, Number.isFinite(amount) ? (amount - deducted) : 0);

    if (!Number.isFinite(amount) || amount <= 0) {
      caViewPerCutoffPreviewEl.value = "";
      return;
    }

    if (fullDeduct) {
      caViewPerCutoffPreviewEl.value = peso(remaining);
      if (caViewCutoffMetaEl) caViewCutoffMetaEl.textContent = "Full deduct: next cutoff will attempt to deduct the remaining balance (remainder carries forward).";
      return;
    }

    const cutoffs = termMonths * 2;
    const per = remaining / Math.max(1, cutoffs);
    caViewPerCutoffPreviewEl.value = peso(per);
    if (caViewCutoffMetaEl) caViewCutoffMetaEl.textContent = `Auto-calculated: remaining ${peso(remaining)} Ã· (${termMonths} mo Ã— 2 = ${cutoffs} cutoffs) = ${peso(per)} per cutoff.`;
  }

  caViewAmount?.addEventListener("input", () => {
    syncPesoInput(caViewAmount, caViewAmountValue);
    updateEditPerCutoffPreviewSafe();
  });
  caViewAmount?.addEventListener("blur", () => {
    syncPesoInput(caViewAmount, caViewAmountValue);
    updateEditPerCutoffPreviewSafe();
  });
  caViewTerm?.addEventListener("input", updateEditPerCutoffPreviewSafe);
  caViewTerm?.addEventListener("change", updateEditPerCutoffPreviewSafe);
  caViewFullDeductEl?.addEventListener("change", () => {
    updateEditPerCutoffPreviewSafe();
  });

  caViewForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const id = Number(caViewId?.value || 0);
    if (!id) return;

    const amount = getHiddenAmountValue(caViewAmountValue, caViewAmount);
    const term = Math.max(1, Number(caViewTerm?.value || 1));
    const start = caViewStart?.value;
    const method = caViewMethodTxn?.value || "salary_deduction";
    const fullDeduct = !!caViewFullDeductEl?.checked;

    if (!start || !Number.isFinite(amount) || amount <= 0) {
      toast("Please fill Amount and Start month.", "error");
      return;
    }

    if (saveCaViewBtn) saveCaViewBtn.disabled = true;
    try {
      await apiFetch(`/settings/cash-advances/${id}`, {
        method: "PATCH",
        body: JSON.stringify({
          amount,
          term_months: term,
          start_month: start,
          method,
          full_deduct: fullDeduct,
        }),
      });
      await loadCashAdvances();
      viewDrawer?.close();
      if (!showNotice("Cash advance updated.")) toast("Cash advance updated.");
      showBanner("Cash advance entry updated.");
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to update cash advance.", "error");
    } finally {
      if (saveCaViewBtn) saveCaViewBtn.disabled = false;
    }
  });

  async function loadPolicyDefaults() {
    try {
      const row = await apiFetch("/settings/cash-advance-policy");
      defaultMethod = row.default_method ?? "salary_deduction";
      defaultTermMonths = row.default_term_months ?? 3;
      regularTermMonths = row.regular_default_term_months ?? defaultTermMonths ?? 3;
      probationaryTermMonths = row.probationary_term_months ?? 1;
      traineeTermMonths = row.trainee_term_months ?? 1;
      traineeForceFullDeduct = row.trainee_force_full_deduct !== false;
      allowFullDeduct = row.allow_full_deduct !== false;
    } catch {
      // optional
    }
  }

  async function loadCashAdvances() {
    try {
      const rows = await apiFetch("/settings/cash-advances");
      cashAdvances = Array.isArray(rows) ? rows : [];
      renderCaSafe();
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
