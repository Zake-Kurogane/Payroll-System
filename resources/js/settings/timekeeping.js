export function initTimekeeping(toast) {
  const lateRuleType = document.getElementById("lateRuleType");
  const latePenaltyPerMinute = document.getElementById("latePenaltyPerMinute");
  const lateHint = document.getElementById("lateHint");

  const undertimeEnabled = document.getElementById("undertimeEnabled");
  const undertimeRuleType = document.getElementById("undertimeRuleType");
  const undertimePenaltyPerMinute = document.getElementById("undertimePenaltyPerMinute");
  const undertimeHint = document.getElementById("undertimeHint");

  const workMinutesPerDay = document.getElementById("workMinutesPerDay");
  const otWorkMinsSummary = document.getElementById("otWorkMinsSummary");
  const salaryWorkMins = document.getElementById("salaryWorkMins");

  function syncLateUI() {
    const type = lateRuleType?.value || "rate_based";
    const isFlat = type === "flat_penalty";
    if (latePenaltyPerMinute) latePenaltyPerMinute.disabled = !isFlat;
    if (lateHint) lateHint.style.display = isFlat ? "none" : "block";
  }

  function syncUndertimeUI() {
    const enabled = !!undertimeEnabled?.checked;
    if (undertimeRuleType) undertimeRuleType.disabled = !enabled;

    const type = undertimeRuleType?.value || "rate_based";
    const isFlat = type === "flat_penalty";

    if (undertimePenaltyPerMinute) undertimePenaltyPerMinute.disabled = !(enabled && isFlat);
    if (undertimeHint) undertimeHint.style.display = (!enabled || isFlat) ? "none" : "block";
  }

  function syncWorkMins() {
    const v = Number(workMinutesPerDay?.value || 480);
    const safe = Number.isFinite(v) && v > 0 ? v : 480;
    if (otWorkMinsSummary) otWorkMinsSummary.value = String(safe);
    if (salaryWorkMins) salaryWorkMins.value = String(safe);
  }

  lateRuleType?.addEventListener("change", syncLateUI);
  undertimeEnabled?.addEventListener("change", syncUndertimeUI);
  undertimeRuleType?.addEventListener("change", syncUndertimeUI);
  workMinutesPerDay?.addEventListener("input", syncWorkMins);

  const tkReset = document.getElementById("tkReset");
  const tkSave = document.getElementById("tkSave");

  tkReset?.addEventListener("click", () => {
    document.getElementById("shiftStart").value = "07:30";
    document.getElementById("graceMinutes").value = "5";
    document.getElementById("lateRuleType").value = "rate_based";
    document.getElementById("latePenaltyPerMinute").value = "1";
    document.getElementById("lateRounding").value = "none";
    document.getElementById("undertimeEnabled").checked = false;
    document.getElementById("undertimeRuleType").value = "rate_based";
    document.getElementById("undertimePenaltyPerMinute").value = "1";
    document.getElementById("workMinutesPerDay").value = "480";
    syncLateUI();
    syncUndertimeUI();
    syncWorkMins();
    toast("Reset to default (UI).");
  });

  tkSave?.addEventListener("click", () => {
    toast("Saved Timekeeping Rules (UI only). Backend can store these in DB.");
  });

  syncLateUI();
  syncUndertimeUI();
  syncWorkMins();
}

