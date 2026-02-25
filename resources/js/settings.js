import { initClock } from "./settings/clock";
import { initTabs } from "./settings/tabs";
import { initAttendanceCodes } from "./settings/attendanceCodes";
import { initCashAdvance } from "./settings/cashAdvance";
import { initTimekeeping } from "./settings/timekeeping";
import { toast } from "./settings/toast";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initTabs();
  initUserMenuDropdown();
  initProfileDrawer();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  async function apiFetch(url, options = {}) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(options.headers || {}),
    };
    const isFormData = typeof FormData !== "undefined" && options.body instanceof FormData;
    if (options.body && !headers["Content-Type"] && !isFormData) {
      headers["Content-Type"] = "application/json";
    }
    if (csrfToken) headers["X-CSRF-TOKEN"] = csrfToken;

    const res = await fetch(url, { ...options, headers, credentials: "include" });
    if (res.status === 419) {
      throw new Error("Session expired. Please refresh the page and try again.");
    }
    const data = await res.json().catch(() => null);
    if (!res.ok) {
      const msg = data?.message || "Request failed.";
      throw new Error(msg);
    }
    return data;
  }

  function showNotice(el, message) {
    if (!el) return false;
    el.textContent = message;
    el.hidden = false;
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(() => {
      el.hidden = true;
    }, 3500);
    return true;
  }

  initTimekeeping(toast, apiFetch, document.getElementById("timekeepingNotice"));
  initCashAdvance(toast, apiFetch, document.getElementById("cashAdvanceNotice"));

  // ===== Company Setup =====
  const companyName = document.getElementById("companyName");
  const companyTin = document.getElementById("companyTin");
  const companyAddress = document.getElementById("companyAddress");
  const companyRdo = document.getElementById("companyRdo");
  const companyContact = document.getElementById("companyContact");
  const companyEmail = document.getElementById("companyEmail");
  const payrollFrequency = document.getElementById("payrollFrequency");
  const currency = document.getElementById("currency");
  const companyNotice = document.getElementById("companyNotice");

  async function loadCompanySetup() {
    try {
      const row = await apiFetch("/settings/company-setup");
      if (companyName) companyName.value = row.company_name ?? "";
      if (companyTin) companyTin.value = row.company_tin ?? "";
      if (companyAddress) companyAddress.value = row.company_address ?? "";
      if (companyRdo) companyRdo.value = row.company_rdo ?? "";
      if (companyContact) companyContact.value = row.company_contact ?? "";
      if (companyEmail) companyEmail.value = row.company_email ?? "";
      if (payrollFrequency) payrollFrequency.value = row.payroll_frequency ?? "semi_monthly";
      if (currency) currency.value = row.currency ?? "PHP";
    } catch (err) {
      toast(err.message || "Failed to load Company Setup.", "error");
    }
  }

  document.getElementById("companySave")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/company-setup", {
        method: "POST",
        body: JSON.stringify({
          company_name: companyName?.value || "",
          company_tin: companyTin?.value || "",
          company_address: companyAddress?.value || "",
          company_rdo: companyRdo?.value || "",
          company_contact: companyContact?.value || "",
          company_email: companyEmail?.value || "",
          payroll_frequency: payrollFrequency?.value || "semi_monthly",
          currency: currency?.value || "PHP",
        }),
      });
      if (!showNotice(companyNotice, "Company setup saved.")) {
        toast("Saved Company Setup.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Company Setup.", "error");
    }
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
  const calendarNotice = document.getElementById("calendarNotice");

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
      if (!showNotice(calendarNotice, "Payroll calendar saved.")) {
        toast("Saved Payroll Calendar.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Payroll Calendar.", "error");
    }
  });

  // ===== Salary & Proration (UI-only) =====
  const salaryMode = document.getElementById("salaryMode");
  const salaryWorkMins = document.getElementById("salaryWorkMins");
  const minutesRounding = document.getElementById("minutesRounding");
  const moneyRounding = document.getElementById("moneyRounding");
  const prorationNotice = document.getElementById("prorationNotice");

  async function loadSalaryProration() {
    try {
      const row = await apiFetch("/settings/salary-proration");
      if (salaryMode) salaryMode.value = row.salary_mode ?? "prorate_workdays";
      if (salaryWorkMins) salaryWorkMins.value = row.work_minutes_per_day ?? 480;
      if (minutesRounding) minutesRounding.value = row.minutes_rounding ?? "per_minute";
      if (moneyRounding) moneyRounding.value = row.money_rounding ?? "2_decimals";
    } catch (err) {
      toast(err.message || "Failed to load Salary & Proration Rules.", "error");
    }
  }

  document.getElementById("prorationSave")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/salary-proration", {
        method: "POST",
        body: JSON.stringify({
          salary_mode: salaryMode?.value || "prorate_workdays",
          work_minutes_per_day: Number(salaryWorkMins?.value || 480),
          minutes_rounding: minutesRounding?.value || "per_minute",
          money_rounding: moneyRounding?.value || "2_decimals",
        }),
      });
      if (!showNotice(prorationNotice, "Salary & proration rules saved.")) {
        toast("Saved Salary & Proration Rules.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Salary & Proration Rules.", "error");
    }
  });

  // ===== OT dynamic fields =====
  const otModeFlat = document.getElementById("otModeFlat");
  const otModeRate = document.getElementById("otModeRate");
  const otFlatWrap = document.getElementById("otFlatWrap");
  const otMultWrap = document.getElementById("otMultWrap");
  const overtimeNotice = document.getElementById("overtimeNotice");

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
      .then(() => {
        if (!showNotice(overtimeNotice, "Overtime rules saved.")) {
          toast("Saved Overtime Rules.");
        }
      })
      .catch((err) => toast(err.message || "Failed to save Overtime Rules.", "error"));
  });

  // ===== Statutory =====
  const sssImportBtn = document.getElementById("sssImportBtn");
  const sssImportFile = document.getElementById("sssImportFile");
  const sssEmptyState = document.getElementById("sssEmptyState");
  const sssPreviewWrap = document.getElementById("sssPreviewWrap");
  const sssPreviewHead = document.getElementById("sssPreviewHead");
  const sssPreviewBody = document.getElementById("sssPreviewBody");
  const sssMeta = document.getElementById("sssMeta");
  const sssClearBtn = document.getElementById("sssClearBtn");
  const sssToggleBtn = document.getElementById("sssToggleBtn");
  const sssImportedWrap = document.getElementById("sssImportedWrap");
  const sssImportedName = document.getElementById("sssImportedName");

  function parseCsvRow(line) {
    const out = [];
    let cur = "";
    let inQuotes = false;
    for (let i = 0; i < line.length; i += 1) {
      const ch = line[i];
      if (ch === "\"") {
        const next = line[i + 1];
        if (inQuotes && next === "\"") {
          cur += "\"";
          i += 1;
        } else {
          inQuotes = !inQuotes;
        }
      } else if (ch === "," && !inQuotes) {
        out.push(cur);
        cur = "";
      } else {
        cur += ch;
      }
    }
    out.push(cur);
    return out;
  }

  function parseCsv(text) {
    const lines = String(text || "")
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n")
      .split("\n");
    const rows = [];
    for (const line of lines) {
      if (!line.trim()) continue;
      rows.push(parseCsvRow(line));
    }
    return rows;
  }

  function normalizeTable(rawRows) {
    const cleaned = (rawRows || [])
      .map((row) => (row || []).map((cell) => String(cell ?? "").trim()))
      .filter((row) => row.some((cell) => cell !== ""));
    if (!cleaned.length) return null;
    const maxCols = Math.max(...cleaned.map((r) => r.length));
    let header = cleaned[0].map((h, i) => h || `Column ${i + 1}`);
    if (header.length < maxCols) {
      header = header.concat(Array.from({ length: maxCols - header.length }, (_, i) => `Column ${header.length + i + 1}`));
    }
    const rows = cleaned.slice(1).map((r) => {
      const row = r.slice(0, header.length);
      if (row.length < header.length) {
        row.push(...Array.from({ length: header.length - row.length }, () => ""));
      }
      return row;
    });
    return { header, rows };
  }

  async function parseTabularFile(file) {
    const name = file?.name || "Import";
    const ext = name.split(".").pop()?.toLowerCase();
    let rows = [];
    if (ext === "xlsx") {
      const xlsx = window.XLSX;
      if (!xlsx) throw new Error("XLSX library not loaded. Please refresh the page.");
      const data = await file.arrayBuffer();
      const wb = xlsx.read(data, { type: "array" });
      const sheetName = wb.SheetNames?.[0];
      const ws = sheetName ? wb.Sheets[sheetName] : null;
      if (!ws) throw new Error("No sheet found in XLSX.");
      rows = xlsx.utils.sheet_to_json(ws, { header: 1, blankrows: false, defval: "" });
    } else if (ext === "csv") {
      const text = await file.text();
      rows = parseCsv(text);
    } else {
      throw new Error("Unsupported file type. Please use CSV or XLSX.");
    }
    const table = normalizeTable(rows);
    if (!table) throw new Error("No rows found in file.");
    return {
      header: table.header,
      rows: table.rows,
      sourceName: name,
      importedAt: Date.now(),
    };
  }

  function renderSssTable(payload) {
    if (!payload || !sssPreviewHead || !sssPreviewBody || !sssPreviewWrap) return;
    const { header, rows, sourceName, importedAt } = payload;
    sssPreviewHead.innerHTML = "";
    sssPreviewBody.innerHTML = "";
    const headRow = document.createElement("tr");
    header.forEach((h) => {
      const th = document.createElement("th");
      th.textContent = h || "";
      headRow.appendChild(th);
    });
    sssPreviewHead.appendChild(headRow);

    const maxPreview = 200;
    rows.slice(0, maxPreview).forEach((r) => {
      const tr = document.createElement("tr");
      header.forEach((_, idx) => {
        const td = document.createElement("td");
        td.textContent = r[idx] ?? "";
        tr.appendChild(td);
      });
      sssPreviewBody.appendChild(tr);
    });

    if (sssEmptyState) sssEmptyState.style.display = "none";
    sssPreviewWrap.style.display = "none";
    if (sssMeta) {
      const stamp = importedAt ? new Date(importedAt).toLocaleString() : "";
      const extra = rows.length > maxPreview ? ` • showing first ${maxPreview}` : "";
      sssMeta.textContent = `${sourceName || "Imported file"} • ${rows.length} rows, ${header.length} columns${extra}${stamp ? ` • ${stamp}` : ""}`;
      sssMeta.style.display = "none";
    }
    if (sssImportedWrap) sssImportedWrap.style.display = "grid";
    if (sssImportedName) sssImportedName.textContent = sourceName || "Imported file";
    if (sssToggleBtn) {
      sssToggleBtn.textContent = "View";
      sssToggleBtn.style.display = "inline-flex";
    }
    if (sssClearBtn) sssClearBtn.style.display = "inline-flex";
  }

  function clearSssTable() {
    if (sssPreviewHead) sssPreviewHead.innerHTML = "";
    if (sssPreviewBody) sssPreviewBody.innerHTML = "";
    if (sssPreviewWrap) sssPreviewWrap.style.display = "none";
    if (sssMeta) sssMeta.style.display = "none";
    if (sssEmptyState) sssEmptyState.style.display = "block";
    if (sssClearBtn) sssClearBtn.style.display = "none";
    if (sssToggleBtn) sssToggleBtn.style.display = "none";
    if (sssImportedWrap) sssImportedWrap.style.display = "none";
    if (sssImportFile) sssImportFile.value = "";
  }

  sssImportBtn?.addEventListener("click", () => sssImportFile?.click());
  sssImportFile?.addEventListener("change", async () => {
    const f = sssImportFile.files?.[0];
    if (!f) return;
    try {
      const payload = await parseTabularFile(f);
      await apiFetch("/settings/statutory-setup", {
        method: "POST",
        body: JSON.stringify({ sss_table: payload }),
      });
      const saved = await apiFetch("/settings/statutory-setup");
      if (!saved?.sss_table) {
        throw new Error("Saved response missing sss_table. Please refresh and try again.");
      }
      renderSssTable(saved.sss_table);
      toast(`Imported and saved: ${f.name}`);
    } catch (err) {
      toast(err.message || "Failed to import SSS table.", "error");
    } finally {
      sssImportFile.value = "";
    }
  });
  sssClearBtn?.addEventListener("click", () => {
    apiFetch("/settings/statutory-setup", {
      method: "POST",
      body: JSON.stringify({ sss_table: null }),
    })
      .then(() => {
        clearSssTable();
        toast("SSS table cleared.");
      })
      .catch((err) => toast(err.message || "Failed to clear SSS table.", "error"));
  });
  sssToggleBtn?.addEventListener("click", () => {
    if (!sssPreviewWrap) return;
    const isHidden = sssPreviewWrap.style.display === "none";
    sssPreviewWrap.style.display = isHidden ? "block" : "none";
    if (sssMeta) sssMeta.style.display = isHidden ? "block" : "none";
    if (sssToggleBtn) sssToggleBtn.textContent = isHidden ? "Hide" : "View";
  });

  async function loadStatutorySetup() {
    try {
      const row = await apiFetch("/settings/statutory-setup");
      if (row?.sss_table) {
        renderSssTable(row.sss_table);
      } else {
        clearSssTable();
      }
      document.getElementById("sssSplitRule") && (document.getElementById("sssSplitRule").value = row.sss_split_rule ?? "monthly");
      document.getElementById("phEePercent") && (document.getElementById("phEePercent").value = row.ph_ee_percent ?? 2.5);
      document.getElementById("phErPercent") && (document.getElementById("phErPercent").value = row.ph_er_percent ?? 2.5);
      document.getElementById("phMinCap") && (document.getElementById("phMinCap").value = row.ph_min_cap ?? 0);
      document.getElementById("phMaxCap") && (document.getElementById("phMaxCap").value = row.ph_max_cap ?? 0);
      document.getElementById("phSplitRule") && (document.getElementById("phSplitRule").value = row.ph_split_rule ?? "monthly");
      document.getElementById("piEePercentLow") && (document.getElementById("piEePercentLow").value = row.pi_ee_percent_low ?? 1);
      document.getElementById("piEeThreshold") && (document.getElementById("piEeThreshold").value = row.pi_ee_threshold ?? 1500);
      document.getElementById("piEePercent") && (document.getElementById("piEePercent").value = row.pi_ee_percent ?? 2);
      document.getElementById("piErPercent") && (document.getElementById("piErPercent").value = row.pi_er_percent ?? 2);
      document.getElementById("piCap") && (document.getElementById("piCap").value = row.pi_cap ?? 5000);
      document.getElementById("piSplitRule") && (document.getElementById("piSplitRule").value = row.pi_split_rule ?? "monthly");
    } catch (err) {
      toast(err.message || "Failed to load Statutory Setup.", "error");
    }
  }

  document.getElementById("statSave")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/statutory-setup", {
        method: "POST",
        body: JSON.stringify({
          sss_split_rule: document.getElementById("sssSplitRule")?.value || "monthly",
          ph_ee_percent: Number(document.getElementById("phEePercent")?.value || 0),
          ph_er_percent: Number(document.getElementById("phErPercent")?.value || 0),
          ph_min_cap: Number(document.getElementById("phMinCap")?.value || 0),
          ph_max_cap: Number(document.getElementById("phMaxCap")?.value || 0),
          ph_split_rule: document.getElementById("phSplitRule")?.value || "monthly",
          pi_ee_percent_low: Number(document.getElementById("piEePercentLow")?.value || 0),
          pi_ee_threshold: Number(document.getElementById("piEeThreshold")?.value || 0),
          pi_ee_percent: Number(document.getElementById("piEePercent")?.value || 0),
          pi_er_percent: Number(document.getElementById("piErPercent")?.value || 0),
          pi_cap: Number(document.getElementById("piCap")?.value || 0),
          pi_split_rule: document.getElementById("piSplitRule")?.value || "monthly",
        }),
      });
      if (!showNotice(document.getElementById("statutoryNotice"), "Statutory setup saved.")) {
        toast("Saved Statutory Setup.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Statutory Setup.", "error");
    }
  });

  // ===== Withholding Tax dynamic UI + placeholder preview =====
  const wtMethod = document.getElementById("wtMethod");
  const wtFixedWrap = document.getElementById("wtFixedWrap");
  const wtPercentWrap = document.getElementById("wtPercentWrap");
  const withholdingNotice = document.getElementById("withholdingNotice");

  function syncWtUI() {
    const m = wtMethod?.value || "table";
    if (wtFixedWrap) wtFixedWrap.style.display = m === "manual_fixed" ? "block" : "none";
    if (wtPercentWrap) wtPercentWrap.style.display = m === "manual_percent" ? "block" : "none";
  }
  wtMethod?.addEventListener("change", syncWtUI);

  const wtImportBtn = document.getElementById("wtImportBtn");
  const wtImportFile = document.getElementById("wtImportFile");
  const wtImportedWrap = document.getElementById("wtImportedWrap");
  const wtImportedName = document.getElementById("wtImportedName");
  const wtToggleBtn = document.getElementById("wtToggleBtn");
  const wtClearBtn = document.getElementById("wtClearBtn");
  let wtTableSourceName = "";
  wtImportBtn?.addEventListener("click", () => wtImportFile?.click());

  const wtEmptyState = document.getElementById("wtEmptyState");
  const wtPreviewWrap = document.getElementById("wtPreviewWrap");
  const wtPreviewHead = document.getElementById("wtPreviewHead");
  const wtPreviewBody = document.getElementById("wtPreviewBody");

  function renderWtTable(payload) {
    if (!payload || !wtPreviewHead || !wtPreviewBody || !wtPreviewWrap) return;
    const { header, rows, sourceName } = payload;
    wtPreviewHead.innerHTML = "";
    wtPreviewBody.innerHTML = "";

    const headRow = document.createElement("tr");
    header.forEach((h) => {
      const th = document.createElement("th");
      th.textContent = h || "";
      headRow.appendChild(th);
    });
    wtPreviewHead.appendChild(headRow);

    const maxPreview = 200;
    rows.slice(0, maxPreview).forEach((r) => {
      const tr = document.createElement("tr");
      header.forEach((_, idx) => {
        const td = document.createElement("td");
        td.textContent = r[idx] ?? "";
        tr.appendChild(td);
      });
      wtPreviewBody.appendChild(tr);
    });

    if (wtEmptyState) wtEmptyState.style.display = "none";
    wtPreviewWrap.style.display = "none";
    if (wtImportedWrap) wtImportedWrap.style.display = "grid";
    if (wtImportedName) wtImportedName.textContent = sourceName || "Imported file";
    if (wtToggleBtn) {
      wtToggleBtn.textContent = "View";
      wtToggleBtn.style.display = "inline-flex";
    }
    if (wtClearBtn) wtClearBtn.style.display = "inline-flex";
  }

  function clearWtTable() {
    if (wtPreviewHead) wtPreviewHead.innerHTML = "";
    if (wtPreviewBody) wtPreviewBody.innerHTML = "";
    if (wtPreviewWrap) wtPreviewWrap.style.display = "none";
    if (wtEmptyState) wtEmptyState.style.display = "block";
    if (wtImportedWrap) wtImportedWrap.style.display = "none";
    if (wtToggleBtn) wtToggleBtn.style.display = "none";
    if (wtClearBtn) wtClearBtn.style.display = "none";
    if (wtImportFile) wtImportFile.value = "";
  }

  function parseNumber(raw) {
    if (raw == null) return null;
    const cleaned = String(raw).replace(/[₱,%\s]/g, "").replace(/,/g, "");
    if (!cleaned) return null;
    const num = Number(cleaned);
    return Number.isFinite(num) ? num : null;
  }

  function normalizeFrequency(raw) {
    const v = String(raw || "").trim().toLowerCase();
    if (!v) return null;
    if (v.includes("semi")) return "semi_monthly";
    if (v.includes("weekly")) return "weekly";
    if (v.includes("daily")) return "daily";
    if (v.includes("monthly")) return "monthly";
    return v.replace(/\s+/g, "_");
  }

  function parseRange(text) {
    const t = String(text || "").toLowerCase();
    const nums = String(text || "").match(/[\d,.]+/g) || [];
    const values = nums.map((n) => parseNumber(n)).filter((n) => n != null);
    if (!values.length) return { from: null, to: null };
    if (t.includes("and below") || t.includes("below")) {
      return { from: 0, to: values[0] };
    }
    if (t.includes("and above") || t.includes("above")) {
      return { from: values[0], to: null };
    }
    if (values.length >= 2) {
      return { from: values[0], to: values[1] };
    }
    return { from: values[0], to: null };
  }

  function parsePrescribed(text) {
    const t = String(text || "");
    if (!t.trim()) return { base: 0, percent: 0 };
    const base = parseNumber(t) ?? 0;
    const pctMatch = t.match(/(\d+(?:\.\d+)?)\s*%/);
    const pct = pctMatch ? Number(pctMatch[1]) : 0;
    return { base, percent: Number.isFinite(pct) ? pct : 0 };
  }

  function normalizeHeader(h) {
    return String(h || "").toLowerCase().replace(/[^a-z0-9]/g, "");
  }

  function findHeaderIndex(headers, keys) {
    for (let i = 0; i < headers.length; i += 1) {
      if (keys.includes(headers[i])) return i;
    }
    return -1;
  }

  function mapWtBrackets(payload) {
    const headerNorm = (payload?.header || []).map(normalizeHeader);
    const freqIdx = findHeaderIndex(headerNorm, ["payfrequency", "frequency", "payfreq"]);
    const bracketIdx = findHeaderIndex(headerNorm, ["bracket", "bracketno", "no"]);
    const rangeIdx = findHeaderIndex(headerNorm, ["compensationrange", "range", "compensation"]);
    const presIdx = findHeaderIndex(headerNorm, ["prescribedwithholdingtax", "prescribedtax", "withholdingtax", "tax"]);
    const fromIdx = findHeaderIndex(headerNorm, ["from", "bracketfrom", "rangefrom", "compfrom", "taxablefrom", "over"]);
    const toIdx = findHeaderIndex(headerNorm, ["to", "bracketto", "rangeto", "compto", "taxableto", "notover", "upto", "up"]);
    const baseIdx = findHeaderIndex(headerNorm, ["base", "basetax", "fixedtax"]);
    const excessIdx = findHeaderIndex(headerNorm, ["excesspercent", "excess", "rate", "percent", "excessrate"]);
    const idx = {
      from: fromIdx >= 0 ? fromIdx : 0,
      to: toIdx >= 0 ? toIdx : 1,
      base: baseIdx >= 0 ? baseIdx : 2,
      excess: excessIdx >= 0 ? excessIdx : 3,
    };

    const rows = payload?.rows || [];
    const brackets = [];
    rows.forEach((r) => {
      const payFrequency = freqIdx >= 0 ? normalizeFrequency(r[freqIdx]) : null;
      const bracketNo = bracketIdx >= 0 ? parseNumber(r[bracketIdx]) : null;
      const compRange = rangeIdx >= 0 ? String(r[rangeIdx] ?? "") : null;
      const prescribed = presIdx >= 0 ? String(r[presIdx] ?? "") : null;

      const rangeParsed = compRange ? parseRange(compRange) : { from: parseNumber(r[idx.from]), to: parseNumber(r[idx.to]) };
      const prescribedParsed = prescribed ? parsePrescribed(prescribed) : { base: parseNumber(r[idx.base]), percent: parseNumber(r[idx.excess]) };

      const bracketFrom = rangeParsed.from;
      const bracketTo = rangeParsed.to;
      const baseTax = prescribedParsed.base;
      const excessPercent = prescribedParsed.percent;
      if (bracketFrom == null || baseTax == null || excessPercent == null) return;
      brackets.push({
        pay_frequency: payFrequency,
        bracket_no: bracketNo != null ? Math.trunc(bracketNo) : null,
        compensation_range: compRange,
        prescribed_withholding_tax: prescribed,
        bracket_from: bracketFrom,
        bracket_to: bracketTo == null ? null : bracketTo,
        base_tax: baseTax,
        excess_percent: excessPercent,
      });
    });
    return brackets;
  }

  wtImportFile?.addEventListener("change", async () => {
    const f = wtImportFile.files?.[0];
    if (!f) return;
    try {
      const payload = await parseTabularFile(f);
      const brackets = mapWtBrackets(payload);
      if (!brackets.length) {
        throw new Error("No valid rows found for withholding tax brackets.");
      }
      await apiFetch("/settings/withholding-tax-brackets", {
        method: "POST",
        body: JSON.stringify({ brackets, source_name: f.name }),
      });
      renderWtTable(payload);
      if (wtImportedWrap) wtImportedWrap.style.display = "grid";
      if (wtImportedName) wtImportedName.textContent = f.name;
      wtTableSourceName = f.name;
      toast(`Imported and saved: ${f.name}`);
    } catch (err) {
      toast(err.message || "Failed to import Withholding Tax table.", "error");
    } finally {
      wtImportFile.value = "";
    }
  });
  wtClearBtn?.addEventListener("click", () => {
    apiFetch("/settings/withholding-tax-brackets", {
      method: "POST",
      body: JSON.stringify({ brackets: [] }),
    })
      .then(() => {
        clearWtTable();
        wtTableSourceName = "";
        toast("Withholding tax table cleared.");
      })
      .catch((err) => toast(err.message || "Failed to clear Withholding Tax table.", "error"));
  });
  wtToggleBtn?.addEventListener("click", () => {
    if (!wtPreviewWrap) return;
    const isHidden = wtPreviewWrap.style.display === "none";
    wtPreviewWrap.style.display = isHidden ? "block" : "none";
    if (wtToggleBtn) wtToggleBtn.textContent = isHidden ? "Hide" : "View";
  });

  async function loadWithholdingTaxPolicy() {
    try {
      const row = await apiFetch("/settings/withholding-tax-policy");
      document.getElementById("wtEnabled") && (document.getElementById("wtEnabled").checked = !!row.enabled);
      wtMethod && (wtMethod.value = row.method ?? "table");
      document.getElementById("wtPayFrequency") && (document.getElementById("wtPayFrequency").value = row.pay_frequency ?? "semi_monthly");
      document.getElementById("wtBasisSSS") && (document.getElementById("wtBasisSSS").checked = !!row.basis_sss);
      document.getElementById("wtBasisPH") && (document.getElementById("wtBasisPH").checked = !!row.basis_ph);
      document.getElementById("wtBasisPI") && (document.getElementById("wtBasisPI").checked = !!row.basis_pi);
      document.getElementById("wtTiming") && (document.getElementById("wtTiming").value = row.timing ?? "monthly");
      document.getElementById("wtSplitRule") && (document.getElementById("wtSplitRule").value = row.split_rule ?? "monthly");
      document.getElementById("wtFixedAmount") && (document.getElementById("wtFixedAmount").value = row.fixed_amount ?? 0);
      document.getElementById("wtPercent") && (document.getElementById("wtPercent").value = row.percent ?? 0);
      wtTableSourceName = row.wt_table_source || "";
      syncWtUI();
    } catch (err) {
      toast(err.message || "Failed to load Withholding Tax Policy.", "error");
    }
  }

  async function loadWithholdingTaxBrackets() {
    try {
      const rows = await apiFetch("/settings/withholding-tax-brackets");
      if (!Array.isArray(rows) || !rows.length) return;
      if (wtPreviewBody) {
        wtPreviewBody.innerHTML = "";
        rows.forEach((r) => {
          const tr = document.createElement("tr");
          const cells = [
            Number(r.bracket_from || 0).toLocaleString(),
            r.bracket_to == null ? "" : Number(r.bracket_to).toLocaleString(),
            `₱ ${Number(r.base_tax || 0).toLocaleString()}`,
            `${Number(r.excess_percent || 0)}%`,
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
      if (wtPreviewWrap) wtPreviewWrap.style.display = "none";
      if (wtImportedWrap) wtImportedWrap.style.display = "grid";
      if (wtImportedName) wtImportedName.textContent = wtTableSourceName || "Imported table";
      if (wtToggleBtn) {
        wtToggleBtn.textContent = "View";
        wtToggleBtn.style.display = "inline-flex";
      }
      if (wtClearBtn) wtClearBtn.style.display = "inline-flex";
    } catch (err) {
      toast(err.message || "Failed to load Withholding Tax Table.", "error");
    }
  }

  // seed removed

  document.getElementById("wtSavePolicy")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/withholding-tax-policy", {
        method: "POST",
        body: JSON.stringify({
          enabled: !!document.getElementById("wtEnabled")?.checked,
          method: wtMethod?.value || "table",
          pay_frequency: document.getElementById("wtPayFrequency")?.value || "semi_monthly",
          basis_sss: !!document.getElementById("wtBasisSSS")?.checked,
          basis_ph: !!document.getElementById("wtBasisPH")?.checked,
          basis_pi: !!document.getElementById("wtBasisPI")?.checked,
          timing: document.getElementById("wtTiming")?.value || "monthly",
          split_rule: document.getElementById("wtSplitRule")?.value || "monthly",
          fixed_amount: Number(document.getElementById("wtFixedAmount")?.value || 0),
          percent: Number(document.getElementById("wtPercent")?.value || 0),
        }),
      });
      if (!showNotice(withholdingNotice, "Withholding tax policy saved.")) {
        toast("Saved Withholding Tax Policy.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Withholding Tax Policy.", "error");
    }
  });

  document.getElementById("wtSaveAll")?.addEventListener("click", async () => {
    try {
      await apiFetch("/settings/withholding-tax-policy", {
        method: "POST",
        body: JSON.stringify({
          enabled: !!document.getElementById("wtEnabled")?.checked,
          method: wtMethod?.value || "table",
          pay_frequency: document.getElementById("wtPayFrequency")?.value || "semi_monthly",
          basis_sss: !!document.getElementById("wtBasisSSS")?.checked,
          basis_ph: !!document.getElementById("wtBasisPH")?.checked,
          basis_pi: !!document.getElementById("wtBasisPI")?.checked,
          timing: document.getElementById("wtTiming")?.value || "monthly",
          split_rule: document.getElementById("wtSplitRule")?.value || "monthly",
          fixed_amount: Number(document.getElementById("wtFixedAmount")?.value || 0),
          percent: Number(document.getElementById("wtPercent")?.value || 0),
        }),
      });
      if (!showNotice(withholdingNotice, "Withholding tax setup saved.")) {
        toast("Saved Withholding Tax Setup.");
      }
    } catch (err) {
      toast(err.message || "Failed to save Withholding Tax Setup.", "error");
    }
  });

  // ===== Init =====
  syncOtUI();
  syncWtUI();
  loadSalaryProration();
  loadStatutorySetup();
  loadWithholdingTaxPolicy();
  loadWithholdingTaxBrackets();
  loadCompanySetup();
  loadPayrollCalendar();
  loadOvertimeRules();
  initAttendanceCodes(toast, apiFetch, document.getElementById("attendanceNotice"));
});
