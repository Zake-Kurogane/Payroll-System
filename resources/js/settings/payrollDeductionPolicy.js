export function initPayrollDeductionPolicy(toast, apiFetch, noticeEl, onChange) {
  const applyPremiumsNonRegular = document.getElementById("pdApplyPremiumsNonRegular");
  const applyTaxNonRegular = document.getElementById("pdApplyTaxNonRegular");
  const capChargesToNet = document.getElementById("pdCapChargesToNet");
  const carryForwardCharges = document.getElementById("pdCarryForwardCharges");
  const capCashAdvanceToNet = document.getElementById("pdCapCashAdvanceToNet");
  const fieldDailyDivisor = document.getElementById("pdFieldDailyDivisor");
  const nonFieldDailyDivisor = document.getElementById("pdNonFieldDailyDivisor");
  const fieldUnpaidStatuses = document.getElementById("pdFieldUnpaidStatuses");
  const fieldUnpaidAbsentLeave = document.getElementById("pdFieldUnpaidAbsentLeave");
  const fieldAbsentDeductionExempt = document.getElementById("pdFieldAbsentDeductionExempt");
  const externalRunsAllowAllAssignments = document.getElementById("pdExternalRunsAllowAllAssignments");
  const splitByRunTypeWhenAssignmentSpecific = document.getElementById("pdSplitEmployeesByRunTypeWhenAssignmentSpecific");
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
      fieldDailyDivisor && (fieldDailyDivisor.value = Number(row.field_daily_divisor || 30));
      nonFieldDailyDivisor && (nonFieldDailyDivisor.value = Number(row.non_field_daily_divisor || 26));
      fieldUnpaidStatuses && (fieldUnpaidStatuses.value = Array.isArray(row.field_unpaid_statuses) ? row.field_unpaid_statuses.join(", ") : "RNR");
      fieldUnpaidAbsentLeave && (fieldUnpaidAbsentLeave.checked = row.field_unpaid_absent_and_leave !== false);
      fieldAbsentDeductionExempt && (fieldAbsentDeductionExempt.checked = row.field_absent_deduction_exempt !== false);
      externalRunsAllowAllAssignments && (externalRunsAllowAllAssignments.checked = !!row.external_runs_allow_all_assignments);
      splitByRunTypeWhenAssignmentSpecific && (splitByRunTypeWhenAssignmentSpecific.checked = row.split_employees_by_run_type_when_assignment_specific !== false);
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
          field_daily_divisor: Number(fieldDailyDivisor?.value || 30),
          non_field_daily_divisor: Number(nonFieldDailyDivisor?.value || 26),
          field_unpaid_statuses: String(fieldUnpaidStatuses?.value || "RNR").split(",").map((s) => s.trim()).filter(Boolean),
          field_unpaid_absent_and_leave: !!fieldUnpaidAbsentLeave?.checked,
          field_absent_deduction_exempt: !!fieldAbsentDeductionExempt?.checked,
          external_runs_allow_all_assignments: !!externalRunsAllowAllAssignments?.checked,
          split_employees_by_run_type_when_assignment_specific: !!splitByRunTypeWhenAssignmentSpecific?.checked,
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
