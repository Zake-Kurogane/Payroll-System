import { initClock } from "./settings/clock";
import { initTabs } from "./settings/tabs";
import { initAttendanceCodes } from "./settings/attendanceCodes";
import { initCashAdvance } from "./settings/cashAdvance";
import { initTimekeeping } from "./settings/timekeeping";
import { toast } from "./settings/toast";
import { esc } from "./settings/utils";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initTabs();
  initTimekeeping(toast);
  initCashAdvance(toast);
  initUserMenuDropdown();
  initProfileDrawer();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  async function apiFetch(url, options = {}) {
    const headers = {
      Accept: "application/json",
      ...(options.headers || {}),
    };
    const isFormData = typeof FormData !== "undefined" && options.body instanceof FormData;
    if (options.body && !headers["Content-Type"] && !isFormData) {
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

  // ===== Company Setup (UI-only) =====
  document.getElementById("companySave")?.addEventListener("click", () => {
    toast("Saved Company Setup (UI only).");
  });

  // ===== Payroll Calendar (UI-only) =====
  const payDateA = document.getElementById("payDateA");
  const payDateB = document.getElementById("payDateB");
  const cutoffAFrom = document.getElementById("cutoffAFrom");
  const cutoffATo = document.getElementById("cutoffATo");
  const cutoffBFrom = document.getElementById("cutoffBFrom");
  const cutoffBTo = document.getElementById("cutoffBTo");
  const workMonSat = document.getElementById("workMonSat");
  const payOnPrevWorkdayIfSunday = document.getElementById("payOnPrevWorkdayIfSunday");

  async function loadPayrollCalendar() {
    try {
      const row = await apiFetch("/settings/payroll-calendar");
      if (payDateA) payDateA.value = row.pay_date_a ?? 15;
      if (payDateB) payDateB.value = row.pay_date_b ?? "EOM";
      if (cutoffAFrom) cutoffAFrom.value = row.cutoff_a_from ?? 11;
      if (cutoffATo) cutoffATo.value = row.cutoff_a_to ?? 25;
      if (cutoffBFrom) cutoffBFrom.value = row.cutoff_b_from ?? 26;
      if (cutoffBTo) cutoffBTo.value = row.cutoff_b_to ?? 10;
      if (workMonSat) workMonSat.checked = !!row.work_mon_sat;
      if (payOnPrevWorkdayIfSunday) payOnPrevWorkdayIfSunday.checked = !!row.pay_on_prev_workday_if_sunday;
    } catch (err) {
      toast(err.message || "Failed to load Payroll Calendar.", "error");
    }
  }

  document.getElementById("calendarSave")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/payroll-calendar", {
        method: "POST",
        body: JSON.stringify({
          pay_date_a: Number(payDateA?.value || 15),
          pay_date_b: String(payDateB?.value || "EOM"),
          cutoff_a_from: Number(cutoffAFrom?.value || 11),
          cutoff_a_to: Number(cutoffATo?.value || 25),
          cutoff_b_from: Number(cutoffBFrom?.value || 26),
          cutoff_b_to: Number(cutoffBTo?.value || 10),
          work_mon_sat: !!workMonSat?.checked,
          pay_on_prev_workday_if_sunday: !!payOnPrevWorkdayIfSunday?.checked,
        }),
      });
      toast("Saved Payroll Calendar.");
    } catch (err) {
      toast(err.message || "Failed to save Payroll Calendar.", "error");
    }
  });

  // ===== Pay Groups (3 fixed rows) =====
  const pgTbody = document.getElementById("pgTbody");

  let payGroups = [];

  async function loadPayGroups() {
    try {
      const rows = await apiFetch("/settings/pay-groups");
      payGroups = Array.isArray(rows) ? rows.map(r => ({
        name: r.name,
        freq: r.pay_frequency,
        calendar: r.calendar,
        shift: r.default_shift_start || "",
        otMode: r.default_ot_mode || "",
      })) : [];
      renderPayGroups();
    } catch (err) {
      toast(err.message || "Failed to load Pay Groups.", "error");
    }
  }

  function renderPayGroups() {
    if (!pgTbody) return;
    pgTbody.innerHTML = "";
    payGroups.forEach((g, idx) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><b>${esc(g.name)}</b></td>
        <td>${esc(g.freq)}</td>
        <td>${esc(g.calendar)}</td>
        <td><input type="time" data-pg="shift" data-idx="${idx}" value="${esc(g.shift || "")}" /></td>
        <td>
          <select data-pg="otMode" data-idx="${idx}">
            <option value="" ${!g.otMode ? "selected" : ""}>-- Select --</option>
            <option value="flat_rate" ${g.otMode === "flat_rate" ? "selected" : ""}>flat_rate</option>
            <option value="rate_based" ${g.otMode === "rate_based" ? "selected" : ""}>rate_based</option>
          </select>
        </td>
      `;
      pgTbody.appendChild(tr);
    });
  }

  pgTbody?.addEventListener("change", (e) => {
    const el = e.target;
    const idx = Number(el.getAttribute("data-idx"));
    const type = el.getAttribute("data-pg");
    if (!Number.isFinite(idx) || !payGroups[idx]) return;

    if (type === "shift") payGroups[idx].shift = el.value;
    if (type === "otMode") payGroups[idx].otMode = el.value;
  });

  document.getElementById("pgSave")?.addEventListener("click", () => {
    apiFetch("/settings/pay-groups", {
      method: "POST",
      body: JSON.stringify({
        groups: payGroups.map(g => ({
          name: g.name,
          pay_frequency: g.freq,
          calendar: g.calendar,
          default_shift_start: g.shift || null,
          default_ot_mode: g.otMode || null,
        })),
      }),
    })
      .then(() => toast("Saved Pay Groups."))
      .catch((err) => toast(err.message || "Failed to save Pay Groups.", "error"));
  });

  // ===== Salary & Proration (UI-only) =====
  document.getElementById("prorationSave")?.addEventListener("click", () => {
    toast("Saved Salary & Proration Rules (UI only).");
  });

  // ===== OT dynamic fields =====
  const otModeFlat = document.getElementById("otModeFlat");
  const otModeRate = document.getElementById("otModeRate");
  const otFlatWrap = document.getElementById("otFlatWrap");
  const otMultWrap = document.getElementById("otMultWrap");

  function syncOtUI() {
    const mode = otModeRate?.checked ? "rate_based" : "flat_rate";
    if (otFlatWrap) otFlatWrap.style.display = mode === "flat_rate" ? "block" : "none";
    if (otMultWrap) otMultWrap.style.display = mode === "rate_based" ? "block" : "none";
  }
  otModeFlat?.addEventListener("change", syncOtUI);
  otModeRate?.addEventListener("change", syncOtUI);

  async function loadOvertimeRules() {
    try {
      const row = await apiFetch("/settings/overtime-rules");
      if (row.ot_mode === "rate_based") {
        otModeRate && (otModeRate.checked = true);
      } else {
        otModeFlat && (otModeFlat.checked = true);
      }
      document.getElementById("otRequireApproval") && (document.getElementById("otRequireApproval").checked = !!row.require_approval);
      document.getElementById("otFlatRate") && (document.getElementById("otFlatRate").value = row.flat_rate ?? 80);
      document.getElementById("otMultiplier") && (document.getElementById("otMultiplier").value = row.multiplier ?? 1.25);
      document.getElementById("otRounding") && (document.getElementById("otRounding").value = row.rounding ?? "none");
      syncOtUI();
    } catch (err) {
      toast(err.message || "Failed to load Overtime Rules.", "error");
    }
  }

  document.getElementById("otSave")?.addEventListener("click", () => {
    const otFlatRate = Number(document.getElementById("otFlatRate")?.value || 0);
    const otMode = document.getElementById("otModeRate")?.checked ? "rate_based" : "flat_rate";
    const otMultiplier = Number(document.getElementById("otMultiplier")?.value || 1.25);
    const otRequireApproval = !!document.getElementById("otRequireApproval")?.checked;
    const otRounding = String(document.getElementById("otRounding")?.value || "none");

    apiFetch("/settings/overtime-rules", {
      method: "POST",
      body: JSON.stringify({
        ot_mode: otMode,
        require_approval: otRequireApproval,
        flat_rate: otFlatRate,
        multiplier: otMultiplier,
        rounding: otRounding,
      }),
    })
      .then(() => toast("Saved Overtime Rules."))
      .catch((err) => toast(err.message || "Failed to save Overtime Rules.", "error"));
  });

  // ===== Statutory =====
  const sssImportBtn = document.getElementById("sssImportBtn");
  const sssImportFile = document.getElementById("sssImportFile");
  sssImportBtn?.addEventListener("click", () => sssImportFile?.click());
  sssImportFile?.addEventListener("change", () => {
    const f = sssImportFile.files?.[0];
    if (!f) return;
    toast(`Selected file: ${f.name} (Import placeholder only).`);
    sssImportFile.value = "";
  });

  document.getElementById("statSave")?.addEventListener("click", () => {
    const current = JSON.parse(localStorage.getItem("payroll.settings") || "{}");
    const settings = {
      ...current,
      phSplitRule: document.getElementById("phSplitRule")?.value || current.phSplitRule || "monthly",
      piSplitRule: document.getElementById("piSplitRule")?.value || current.piSplitRule || "monthly",
    };
    localStorage.setItem("payroll.settings", JSON.stringify(settings));
    toast("Saved Statutory Setup.");
  });

  // ===== Withholding Tax dynamic UI + placeholder preview =====
  const wtMethod = document.getElementById("wtMethod");
  const wtFixedWrap = document.getElementById("wtFixedWrap");
  const wtPercentWrap = document.getElementById("wtPercentWrap");

  function syncWtUI() {
    const m = wtMethod?.value || "table";
    if (wtFixedWrap) wtFixedWrap.style.display = m === "manual_fixed" ? "block" : "none";
    if (wtPercentWrap) wtPercentWrap.style.display = m === "manual_percent" ? "block" : "none";
  }
  wtMethod?.addEventListener("change", syncWtUI);

  const wtImportBtn = document.getElementById("wtImportBtn");
  const wtImportFile = document.getElementById("wtImportFile");
  wtImportBtn?.addEventListener("click", () => wtImportFile?.click());
  wtImportFile?.addEventListener("change", () => {
    const f = wtImportFile.files?.[0];
    if (!f) return;
    toast(`Selected file: ${f.name} (Import placeholder only).`);
    wtImportFile.value = "";
  });

  const wtSeedBtn = document.getElementById("wtSeedBtn");
  const wtEmptyState = document.getElementById("wtEmptyState");
  const wtPreviewWrap = document.getElementById("wtPreviewWrap");
  const wtPreviewBody = document.getElementById("wtPreviewBody");

  wtSeedBtn?.addEventListener("click", () => {
    const demo = [
      { from: 0, to: 10000, base: 0, excess: 0 },
      { from: 10000, to: 20000, base: 500, excess: 10 },
      { from: 20000, to: 999999, base: 1500, excess: 15 },
    ];

    if (wtPreviewBody) {
      wtPreviewBody.innerHTML = "";
      demo.forEach((r) => {
        const tr = document.createElement("tr");
        const cells = [
          r.from.toLocaleString(),
          r.to.toLocaleString(),
          `₱ ${r.base.toLocaleString()}`,
          `${r.excess}%`,
        ];
        cells.forEach((text) => {
          const td = document.createElement("td");
          td.textContent = text;
          tr.appendChild(td);
        });
        wtPreviewBody.appendChild(tr);
      });
    }

    if (wtEmptyState) wtEmptyState.style.display = "none";
    if (wtPreviewWrap) wtPreviewWrap.style.display = "block";
    toast("Default withholding tax table preview loaded (UI only).");
  });

  document.getElementById("wtSavePolicy")?.addEventListener("click", () => {
    toast("Saved Withholding Tax Policy (UI only).");
  });

  document.getElementById("wtSaveAll")?.addEventListener("click", () => {
    toast("Saved Withholding Tax Setup (UI only).");
  });

  // ===== Init =====
  syncOtUI();
  syncWtUI();
  loadPayrollCalendar();
  loadPayGroups();
  loadOvertimeRules();
  initAttendanceCodes(toast, apiFetch);
});
