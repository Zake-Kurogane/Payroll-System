export function initTimekeeping(toast, apiFetch, noticeEl, onChange) {
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
    const latePenaltyWrap = document.getElementById("latePenaltyWrap");
    if (latePenaltyWrap) latePenaltyWrap.style.display = isFlat ? "block" : "none";
    if (lateHint) lateHint.style.display = isFlat ? "none" : "block";
  }

  function syncUndertimeUI() {
    const enabled = !!undertimeEnabled?.checked;
    if (undertimeRuleType) undertimeRuleType.disabled = !enabled;

    const type = undertimeRuleType?.value || "rate_based";
    const isFlat = type === "flat_penalty";

    if (undertimePenaltyPerMinute) undertimePenaltyPerMinute.disabled = !(enabled && isFlat);
    const undertimePenaltyWrap = document.getElementById("undertimePenaltyWrap");
    if (undertimePenaltyWrap) undertimePenaltyWrap.style.display = (enabled && isFlat) ? "block" : "none";
    if (undertimeHint) undertimeHint.style.display = (!enabled || isFlat) ? "none" : "block";
  }

  function syncWorkMins() {
    const v = Number(workMinutesPerDay?.value || 480);
    const safe = Number.isFinite(v) && v > 0 ? v : 480;
    if (otWorkMinsSummary) otWorkMinsSummary.value = String(safe);
    if (salaryWorkMins) salaryWorkMins.value = String(safe);
  }

  function normalizeTime(value) {
    const raw = String(value || "");
    if (!raw) return "";
    // Ensure HH:MM for backend validation (H:i).
    return raw.length >= 5 ? raw.slice(0, 5) : raw;
  }

  lateRuleType?.addEventListener("change", syncLateUI);
  undertimeEnabled?.addEventListener("change", syncUndertimeUI);
  undertimeRuleType?.addEventListener("change", syncUndertimeUI);
  workMinutesPerDay?.addEventListener("input", syncWorkMins);

  const tkReset = document.getElementById("tkReset");
  const tkSave = document.getElementById("tkSave");

  function showNotice(message) {
    if (!noticeEl) {
      noticeEl = document.getElementById("timekeepingNotice");
    }
    if (!noticeEl) return false;
    noticeEl.textContent = message;
    noticeEl.hidden = false;
    clearTimeout(noticeEl._hideTimer);
    noticeEl._hideTimer = setTimeout(() => {
      noticeEl.hidden = true;
    }, 3500);
    return true;
  }

  tkReset?.addEventListener("click", () => {
    document.getElementById("shiftStart").value = "07:30";
    document.getElementById("graceMinutes").value = "0";
    document.getElementById("tkDavaoStart").value = "07:45";
    document.getElementById("tkDavaoEnd").value = "17:00";
    document.getElementById("tkTagumStart").value = "07:30";
    document.getElementById("tkTagumEnd").value = "17:00";
    document.getElementById("tkFieldStart").value = "06:30";
    document.getElementById("tkFieldEnd").value = "17:30";
    document.getElementById("tkMebatasStart").value = "07:00";
    document.getElementById("tkMebatasEnd").value = "19:00";
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

  async function loadTimekeeping() {
    try {
      const row = await apiFetch("/settings/timekeeping-rules");
      document.getElementById("shiftStart") && (document.getElementById("shiftStart").value = normalizeTime(row.shift_start ?? "07:30"));
      document.getElementById("graceMinutes") && (document.getElementById("graceMinutes").value = 0);
      document.getElementById("tkDavaoStart") && (document.getElementById("tkDavaoStart").value = normalizeTime(row.assignment_schedules?.davao?.start ?? "07:45"));
      document.getElementById("tkDavaoEnd") && (document.getElementById("tkDavaoEnd").value = normalizeTime(row.assignment_schedules?.davao?.end ?? "17:00"));
      document.getElementById("tkTagumStart") && (document.getElementById("tkTagumStart").value = normalizeTime(row.assignment_schedules?.tagum?.start ?? "07:30"));
      document.getElementById("tkTagumEnd") && (document.getElementById("tkTagumEnd").value = normalizeTime(row.assignment_schedules?.tagum?.end ?? "17:00"));
      document.getElementById("tkFieldStart") && (document.getElementById("tkFieldStart").value = normalizeTime(row.assignment_schedules?.field?.start ?? "06:30"));
      document.getElementById("tkFieldEnd") && (document.getElementById("tkFieldEnd").value = normalizeTime(row.assignment_schedules?.field?.end ?? "17:30"));
      document.getElementById("tkMebatasStart") && (document.getElementById("tkMebatasStart").value = normalizeTime(row.assignment_schedules?.mebatas?.start ?? "07:00"));
      document.getElementById("tkMebatasEnd") && (document.getElementById("tkMebatasEnd").value = normalizeTime(row.assignment_schedules?.mebatas?.end ?? "19:00"));
      document.getElementById("lateRuleType") && (document.getElementById("lateRuleType").value = row.late_rule_type ?? "rate_based");
      document.getElementById("latePenaltyPerMinute") && (document.getElementById("latePenaltyPerMinute").value = row.late_penalty_per_minute ?? 1);
      document.getElementById("lateRounding") && (document.getElementById("lateRounding").value = row.late_rounding ?? "none");
      document.getElementById("undertimeEnabled") && (document.getElementById("undertimeEnabled").checked = !!row.undertime_enabled);
      document.getElementById("undertimeRuleType") && (document.getElementById("undertimeRuleType").value = row.undertime_rule_type ?? "rate_based");
      document.getElementById("undertimePenaltyPerMinute") && (document.getElementById("undertimePenaltyPerMinute").value = row.undertime_penalty_per_minute ?? 1);
      document.getElementById("workMinutesPerDay") && (document.getElementById("workMinutesPerDay").value = row.work_minutes_per_day ?? 480);
      syncLateUI();
      syncUndertimeUI();
      syncWorkMins();
    } catch (err) {
      toast(err.message || "Failed to load Timekeeping Rules.", "error");
    }
  }

  tkSave?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/timekeeping-rules", {
        method: "POST",
        body: JSON.stringify({
          shift_start: normalizeTime(document.getElementById("shiftStart")?.value || "07:30"),
          grace_minutes: 0,
          late_rule_type: document.getElementById("lateRuleType")?.value || "rate_based",
          late_penalty_per_minute: Number(document.getElementById("latePenaltyPerMinute")?.value || 1),
          late_rounding: document.getElementById("lateRounding")?.value || "none",
          undertime_enabled: !!document.getElementById("undertimeEnabled")?.checked,
          undertime_rule_type: document.getElementById("undertimeRuleType")?.value || "rate_based",
          undertime_penalty_per_minute: Number(document.getElementById("undertimePenaltyPerMinute")?.value || 1),
          work_minutes_per_day: Number(document.getElementById("workMinutesPerDay")?.value || 480),
          assignment_schedules: {
            davao: {
              start: normalizeTime(document.getElementById("tkDavaoStart")?.value || "07:45"),
              end: normalizeTime(document.getElementById("tkDavaoEnd")?.value || "17:00"),
            },
            tagum: {
              start: normalizeTime(document.getElementById("tkTagumStart")?.value || "07:30"),
              end: normalizeTime(document.getElementById("tkTagumEnd")?.value || "17:00"),
            },
            field: {
              start: normalizeTime(document.getElementById("tkFieldStart")?.value || "06:30"),
              end: normalizeTime(document.getElementById("tkFieldEnd")?.value || "17:30"),
            },
            mebatas: {
              start: normalizeTime(document.getElementById("tkMebatasStart")?.value || "07:00"),
              end: normalizeTime(document.getElementById("tkMebatasEnd")?.value || "19:00"),
            },
          },
        }),
      });
      if (!showNotice("Timekeeping rules saved.")) {
        toast("Saved Timekeeping Rules.");
      }
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to save Timekeeping Rules.", "error");
    }
  });

  syncLateUI();
  syncUndertimeUI();
  syncWorkMins();
  loadTimekeeping();
}
