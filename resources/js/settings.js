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
  initAttendanceCodes(toast);
  initCashAdvance(toast);
  initUserMenuDropdown();
  initProfileDrawer();

  // ===== Company Setup (UI-only) =====
  document.getElementById("companySave")?.addEventListener("click", () => {
    toast("Saved Company Setup (UI only).");
  });

  // ===== Payroll Calendar (UI-only) =====
  document.getElementById("calendarSave")?.addEventListener("click", () => {
    toast("Saved Payroll Calendar (UI only).");
  });

  // ===== Pay Groups (3 fixed rows) =====
  const pgTbody = document.getElementById("pgTbody");

  let payGroups = [
    { name: "Tagum", freq: "Semi-monthly", calendar: "Payroll Calendar", shift: "", otMode: "" },
    { name: "Davao", freq: "Semi-monthly", calendar: "Payroll Calendar", shift: "", otMode: "" },
    { name: "Area", freq: "Semi-monthly", calendar: "Payroll Calendar", shift: "", otMode: "" },
  ];

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
            <option value="" ${!g.otMode ? "selected" : ""}>â€” Select â€”</option>
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
    toast("Saved Pay Groups (UI only).");
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

  document.getElementById("otSave")?.addEventListener("click", () => {
    const otFlatRate = Number(document.getElementById("otFlatRate")?.value || 0);
    const otMode = document.getElementById("otModeRate")?.checked ? "rate_based" : "flat_rate";
    const otMultiplier = Number(document.getElementById("otMultiplier")?.value || 1.25);

    const settings = {
      otRate: otMode === "flat_rate" ? otFlatRate : 120,
      otMode,
      otMultiplier,
      workDays: 26,
      phSplitRule: document.getElementById("phSplitRule")?.value || "monthly",
      piSplitRule: document.getElementById("piSplitRule")?.value || "monthly",
    };

    localStorage.setItem("payroll.settings", JSON.stringify(settings));
    toast("Saved Overtime Rules.");
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
  renderPayGroups();
});
