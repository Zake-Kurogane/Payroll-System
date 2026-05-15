import { initClock } from "./settings/clock";
import { initTabs } from "./settings/tabs";
import { initAttendanceCodes } from "./settings/attendanceCodes";
import { initCashAdvancePolicy } from "./settings/cashAdvance";
import { initPayrollDeductionPolicy } from "./settings/payrollDeductionPolicy";
import { initTimekeeping } from "./settings/timekeeping";
import { toast } from "./settings/toast";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { broadcastSettingsUpdate } from "./shared/settingsSync";

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

  const notifySettingsUpdated = () => broadcastSettingsUpdate();
  initTimekeeping(toast, apiFetch, document.getElementById("timekeepingNotice"), notifySettingsUpdated);
  initAttendanceCodes(toast, apiFetch, document.getElementById("attendanceNotice"), notifySettingsUpdated);
  initCashAdvancePolicy(toast, apiFetch, document.getElementById("cashAdvanceNotice"), notifySettingsUpdated);
  initPayrollDeductionPolicy(toast, apiFetch, document.getElementById("payrollDeductionNotice"), notifySettingsUpdated);

  // ===== Accounts list (admin-only UI) =====
  const hrUsersTbody = document.getElementById("hrUsersTbody");
  const hrUserDrawer = document.getElementById("hrUserDrawer");
  const hrUserDrawerOverlay = document.getElementById("hrUserDrawerOverlay");
  const closeHrUserDrawer = document.getElementById("closeHrUserDrawer");
  const cancelHrUserBtn = document.getElementById("cancelHrUserBtn");
  const hrUserForm = document.getElementById("hrUserForm");
  const hrUserNotice = document.getElementById("hrUserNotice");

  const hrUserId = document.getElementById("hrUserId");
  const hrUserName = document.getElementById("hrUserName");
  const hrUserEmail = document.getElementById("hrUserEmail");
  const hrUserFirst = document.getElementById("hrUserFirst");
  const hrUserMiddle = document.getElementById("hrUserMiddle");
  const hrUserLast = document.getElementById("hrUserLast");
  const hrUserPassword = document.getElementById("hrUserPassword");

  let hrUsersCache = [];

  function openHrUserDrawer(user) {
    if (!hrUserDrawer || !user) return;
    hrUserId && (hrUserId.value = String(user.id || ""));
    hrUserName && (hrUserName.value = user.username || "");
    hrUserEmail && (hrUserEmail.value = user.email || "");
    hrUserFirst && (hrUserFirst.value = user.first_name || "");
    hrUserMiddle && (hrUserMiddle.value = user.middle_name || "");
    hrUserLast && (hrUserLast.value = user.last_name || "");
    hrUserPassword && (hrUserPassword.value = "");
    hrUserNotice && (hrUserNotice.hidden = true);
    hrUserDrawer.classList.add("is-open");
    hrUserDrawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("drawer-open");
  }

  function closeHrUserDrawerUI() {
    if (!hrUserDrawer) return;
    hrUserDrawer.classList.remove("is-open");
    hrUserDrawer.setAttribute("aria-hidden", "true");
    document.body.classList.remove("drawer-open");
  }

  closeHrUserDrawer?.addEventListener("click", closeHrUserDrawerUI);
  cancelHrUserBtn?.addEventListener("click", closeHrUserDrawerUI);
  hrUserDrawerOverlay?.addEventListener("click", closeHrUserDrawerUI);

  function showHrUserNotice(message) {
    if (!hrUserNotice) return;
    hrUserNotice.textContent = message;
    hrUserNotice.hidden = false;
    clearTimeout(hrUserNotice._hideTimer);
    hrUserNotice._hideTimer = setTimeout(() => { hrUserNotice.hidden = true; }, 2500);
  }

  async function loadHrUsers() {
    if (!hrUsersTbody) return;
    hrUsersTbody.innerHTML = `<tr><td colspan="6" class="muted">Loading...</td></tr>`;
    try {
      const rows = await apiFetch("/admin/users");
      const list = Array.isArray(rows) ? rows : [];
      hrUsersCache = list;
      if (!list.length) {
        hrUsersTbody.innerHTML = `<tr><td colspan="6" class="muted">No accounts yet.</td></tr>`;
        return;
      }
      hrUsersTbody.innerHTML = list.map((u) => {
        const created = u.created_at ? new Date(u.created_at).toLocaleString() : "—";
        const rawCreatedBy = String(u.created_by || "").replace(/\uFFFD/g, "").trim();
        const createdBy = rawCreatedBy || "-";
        return `
          <tr>
            <td>${String(u.username || "—")}</td>
            <td>${String(u.full_name || "—")}</td>
            <td>${String(u.email || "—")}</td>
            <td>${createdBy}</td>
            <td>${created}</td>
            <td>
              <div class="tableActionsInline">
                <button type="button" class="iconbtn" data-hr-edit="${u.id}" title="Edit" aria-label="Edit">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 20h9" />
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5z" />
                  </svg>
                </button>
                <button type="button" class="iconbtn" data-hr-delete="${u.id}" title="Delete" aria-label="Delete">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                    <path d="M10 11v6" />
                    <path d="M14 11v6" />
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join("");
    } catch (err) {
      hrUsersTbody.innerHTML = `<tr><td colspan="6" class="muted">Failed to load.</td></tr>`;
      toast(err.message || "Failed to load accounts.", "error");
    }
  }
  loadHrUsers();

  hrUsersTbody?.addEventListener("click", (e) => {
    const editBtn = e.target.closest("button[data-hr-edit]");
    if (editBtn) {
      const id = Number(editBtn.getAttribute("data-hr-edit"));
      const user = hrUsersCache.find((x) => Number(x.id) === id);
      openHrUserDrawer(user);
      return;
    }

    const deleteBtn = e.target.closest("button[data-hr-delete]");
    if (!deleteBtn) return;
    const id = Number(deleteBtn.getAttribute("data-hr-delete"));
    const user = hrUsersCache.find((x) => Number(x.id) === id);
    const label = user?.username || `#${id}`;
    if (!window.confirm(`Delete account "${label}"? This cannot be undone.`)) return;

    (async () => {
      try {
        await apiFetch(`/admin/users/${id}`, { method: "DELETE" });
        await loadHrUsers();
        toast("Account deleted.");
      } catch (err) {
        toast(err.message || "Failed to delete account.", "error");
      }
    })();
  });

  hrUserForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const id = Number(hrUserId?.value || 0);
    if (!id) return;

    const payload = {
      name: hrUserName?.value || "",
      email: hrUserEmail?.value || "",
      first_name: hrUserFirst?.value || "",
      middle_name: hrUserMiddle?.value || "",
      last_name: hrUserLast?.value || "",
    };
    const pw = (hrUserPassword?.value || "").trim();
    if (pw) payload.password = pw;

    try {
      await apiFetch(`/admin/users/${id}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
      });
      showHrUserNotice("Saved.");
      await loadHrUsers();
      closeHrUserDrawerUI();
    } catch (err) {
      toast(err.message || "Failed to save account.", "error");
    }
  });

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
      notifySettingsUpdated();
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
      notifySettingsUpdated();
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
      notifySettingsUpdated();
    } catch (err) {
      toast(err.message || "Failed to save Salary & Proration Rules.", "error");
    }
  });
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
    const cleaned = String(raw).replace(/[?,%\s]/g, "").replace(/,/g, "");
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
    const baseMatch = t.match(/[\d,.]+/);
    const base = baseMatch ? (parseNumber(baseMatch[0]) ?? 0) : 0;
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

  function mapWtBracketsCrossTab(payload) {
    const header = payload?.header || [];
    const rows = payload?.rows || [];
    const allRows = [header, ...rows].map((r) => (Array.isArray(r) ? r : []));

    function isFreqLabel(cell) {
      const v = String(cell || "").trim().toLowerCase();
      if (!v) return false;
      return v.includes("daily") || v.includes("weekly") || v.includes("semi") || v.includes("monthly");
    }

    function isRangeRow(cell) {
      const v = String(cell || "").trim().toLowerCase();
      return v.includes("compensation") && v.includes("range");
    }

    function isPrescribedRow(cell) {
      const v = String(cell || "").trim().toLowerCase();
      return v.includes("prescribed") && v.includes("withholding");
    }

    const brackets = [];
    for (let i = 0; i < allRows.length; i += 1) {
      const r = allRows[i];
      const label = r?.[0];
      if (!isFreqLabel(label)) continue;
      const payFrequency = normalizeFrequency(label);
      if (!payFrequency) continue;

      const rangeRow = allRows[i + 1] || [];
      const presRow = allRows[i + 2] || [];
      if (!isRangeRow(rangeRow[0]) || !isPrescribedRow(presRow[0])) continue;

      for (let col = 1; col < r.length; col += 1) {
        const bracketNo = parseNumber(r[col]);
        if (bracketNo == null) continue;
        const compRange = String(rangeRow[col] ?? "");
        const prescribed = String(presRow[col] ?? "");
        if (!compRange.trim()) continue;

        const rangeParsed = parseRange(compRange);
        const prescribedParsed = parsePrescribed(prescribed);
        if (rangeParsed.from == null) continue;

        brackets.push({
          pay_frequency: payFrequency,
          bracket_no: Math.trunc(bracketNo),
          compensation_range: compRange,
          prescribed_withholding_tax: prescribed,
          bracket_from: rangeParsed.from,
          bracket_to: rangeParsed.to == null ? null : rangeParsed.to,
          base_tax: prescribedParsed.base ?? 0,
          excess_percent: prescribedParsed.percent ?? 0,
        });
      }
    }

    return brackets;
  }

  function mapWtBracketsAuto(payload) {
    const headerNorm = (payload?.header || []).map(normalizeHeader);
    const looksTabular =
      headerNorm.includes("payfrequency") ||
      headerNorm.includes("compensationrange") ||
      headerNorm.includes("prescribedwithholdingtax") ||
      headerNorm.includes("bracketfrom");

    const primary = looksTabular ? mapWtBrackets(payload) : mapWtBracketsCrossTab(payload);
    if (primary.length) return primary;
    const fallback = looksTabular ? mapWtBracketsCrossTab(payload) : mapWtBrackets(payload);
    return fallback;
  }

  wtImportFile?.addEventListener("change", async () => {
    const f = wtImportFile.files?.[0];
    if (!f) return;
    try {
      const payload = await parseTabularFile(f);
      const brackets = mapWtBracketsAuto(payload);
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
      notifySettingsUpdated();
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
            `? ${Number(r.base_tax || 0).toLocaleString()}`,
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
      notifySettingsUpdated();
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
      notifySettingsUpdated();
    } catch (err) {
      toast(err.message || "Failed to save Withholding Tax Setup.", "error");
    }
  });

  // ===== Init =====
  syncWtUI();
  loadSalaryProration();
  loadStatutorySetup();
  loadWithholdingTaxPolicy();
  loadWithholdingTaxBrackets();
  loadCompanySetup();
  loadPayrollCalendar();
});





