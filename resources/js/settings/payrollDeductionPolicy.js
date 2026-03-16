export function initPayrollDeductionPolicy(toast, apiFetch, noticeEl, onChange) {
  const applyPremiumsNonRegular = document.getElementById("pdApplyPremiumsNonRegular");
  const applyTaxNonRegular = document.getElementById("pdApplyTaxNonRegular");
  const capChargesToNet = document.getElementById("pdCapChargesToNet");
  const carryForwardCharges = document.getElementById("pdCarryForwardCharges");
  const capCashAdvanceToNet = document.getElementById("pdCapCashAdvanceToNet");
  const saveBtn = document.getElementById("payrollDeductionSave");

  if (!saveBtn) return;

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

  async function load() {
    try {
      const row = await apiFetch("/settings/payroll-deduction-policy");
      applyPremiumsNonRegular && (applyPremiumsNonRegular.checked = !!row.apply_premiums_to_non_regular);
      applyTaxNonRegular && (applyTaxNonRegular.checked = !!row.apply_tax_to_non_regular);
      capChargesToNet && (capChargesToNet.checked = row.cap_charges_to_net_pay !== false);
      carryForwardCharges && (carryForwardCharges.checked = row.carry_forward_unpaid_charges !== false);
      capCashAdvanceToNet && (capCashAdvanceToNet.checked = row.cap_cash_advance_to_net_pay !== false);
    } catch (err) {
      toast(err.message || "Failed to load payroll deduction rules.", "error");
    }
  }

  saveBtn.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/payroll-deduction-policy", {
        method: "POST",
        body: JSON.stringify({
          apply_premiums_to_non_regular: !!applyPremiumsNonRegular?.checked,
          apply_tax_to_non_regular: !!applyTaxNonRegular?.checked,
          cap_charges_to_net_pay: !!capChargesToNet?.checked,
          carry_forward_unpaid_charges: !!carryForwardCharges?.checked,
          cap_cash_advance_to_net_pay: !!capCashAdvanceToNet?.checked,
          loans_before_charges: true,
          charges_before_cash_advance: true,
        }),
      });
      if (!showNotice("Deduction rules saved.")) toast("Saved deduction rules.");
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to save deduction rules.", "error");
    }
  });

  load();
}

