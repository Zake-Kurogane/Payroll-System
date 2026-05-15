import { esc } from "./utils";
import { createDrawer } from "./drawer";

export function initAttendanceCodes(toast, apiFetch, noticeEl, onChange) {
  const codesTbody = document.getElementById("codesTbody");
  const addCodeBtn = document.getElementById("addCodeBtn");

  const codeDrawer = document.getElementById("codeDrawer");
  const codeDrawerOverlay = document.getElementById("codeDrawerOverlay");
  const closeCodeDrawer = document.getElementById("closeCodeDrawer");
  const cancelCodeBtn = document.getElementById("cancelCodeBtn");
  const codeForm = document.getElementById("codeForm");

  const codeField = document.getElementById("codeField");
  const descField = document.getElementById("descField");
  const notesField = document.getElementById("notesField");
  const presentField = document.getElementById("presentField");
  const paidField = document.getElementById("paidField");
  const deductField = document.getElementById("deductField");
  const noTimeField = document.getElementById("noTimeField");
  const timeTrackedField = document.getElementById("timeTrackedField");
  const codeDrawerTitle = document.getElementById("codeDrawerTitle");

  const defaultNoLogCode = document.getElementById("defaultNoLogCode");
  const defaultSundayCode = document.getElementById("defaultSundayCode");
  const paidLeaveCapDays = document.getElementById("paidLeaveCapDays");

  let editCodeKey = null;

  let codes = [];
  let defaults = {
    default_no_log_code: "",
    default_sunday_code: "",
    paid_leave_cap_days: 5,
    template_codes: [],
    no_time_statuses: [],
    time_tracked_statuses: [],
  };

  if (codeDrawer && codeDrawer.parentElement !== document.body) {
    document.body.appendChild(codeDrawer);
  }

  const drawer = createDrawer(codeDrawer, codeDrawerOverlay, [closeCodeDrawer, cancelCodeBtn]);

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

  function renderCodes() {
    if (!codesTbody) return;
    codesTbody.innerHTML = "";

    codes.forEach((row) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${esc(row.code)}</td>
        <td>${esc(row.desc)}</td>
        <td><input type="checkbox" ${row.present ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.paid ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.deduct ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.noTime ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.timeTracked ? "checked" : ""} disabled></td>
        <td>${esc(row.notes || "-")}</td>
        <td>
          <div class="miniRow">
            <button class="miniBtn" data-act="edit" data-code="${esc(row.code)}" aria-label="Edit">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-act="del" data-code="${esc(row.code)}" aria-label="Delete">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
              </svg>
            </button>
          </div>
        </td>
      `;
      codesTbody.appendChild(tr);
    });
  }

  function fillCodeDefaults() {
    if (!defaultNoLogCode || !defaultSundayCode) return;

    const makeOpts = (sel, chosen) => {
      sel.innerHTML = "";
      codes.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.code;
        opt.textContent = `${c.code} - ${c.desc}`;
        sel.appendChild(opt);
      });
      if (chosen) sel.value = chosen;
    };

    makeOpts(defaultNoLogCode, defaults.default_no_log_code || (codes[0]?.code || ""));
    makeOpts(defaultSundayCode, defaults.default_sunday_code || (codes[0]?.code || ""));
  }

  function fillPolicySelectors() {
    if (paidLeaveCapDays) {
      paidLeaveCapDays.value = Number(defaults.paid_leave_cap_days || 5);
    }
  }

  async function loadCodes() {
    if (!apiFetch) return;
    try {
      const data = await apiFetch("/settings/attendance-codes");
      const noTimeStatuses = Array.isArray(data?.defaults?.no_time_statuses) ? data.defaults.no_time_statuses : [];
      const timeTrackedStatuses = Array.isArray(data?.defaults?.time_tracked_statuses)
        ? data.defaults.time_tracked_statuses
        : [];

      codes = Array.isArray(data?.codes)
        ? data.codes.map((c) => ({
            code: c.code,
            desc: c.description,
            present: !!c.counts_as_present,
            paid: !!c.counts_as_paid,
            deduct: !!c.affects_deductions,
            notes: c.notes || "",
            noTime: noTimeStatuses.includes(c.description),
            timeTracked: timeTrackedStatuses.includes(c.description),
          }))
        : [];

      defaults = {
        default_no_log_code: data?.defaults?.default_no_log_code || "",
        default_sunday_code: data?.defaults?.default_sunday_code || "",
        paid_leave_cap_days: Number(data?.defaults?.paid_leave_cap_days || 5),
        template_codes: Array.isArray(data?.defaults?.template_codes) ? data.defaults.template_codes : [],
        no_time_statuses: noTimeStatuses,
        time_tracked_statuses: timeTrackedStatuses,
      };
      renderCodes();
      fillCodeDefaults();
      fillPolicySelectors();
    } catch (err) {
      toast(err.message || "Failed to load attendance codes.", "error");
    }
  }

  async function saveCodes() {
    if (!apiFetch) return;
    try {
      await apiFetch("/settings/attendance-codes", {
        method: "POST",
        body: JSON.stringify({
          codes: codes.map((c) => ({
            code: c.code,
            description: c.desc,
            counts_as_present: !!c.present,
            counts_as_paid: !!c.paid,
            affects_deductions: !!c.deduct,
            notes: c.notes || "",
          })),
          defaults: {
            default_no_log_code: defaultNoLogCode?.value || "",
            default_sunday_code: defaultSundayCode?.value || "",
            paid_leave_cap_days: Number(paidLeaveCapDays?.value || 5),
            template_codes: codes.map((c) => c.code).filter(Boolean),
            no_time_statuses: codes.map((c) => (c.noTime ? c.desc : "")).filter(Boolean),
            time_tracked_statuses: codes.map((c) => (c.timeTracked ? c.desc : "")).filter(Boolean),
          },
        }),
      });
      showNotice("Attendance codes saved.");
      if (typeof onChange === "function") onChange();
    } catch (err) {
      toast(err.message || "Failed to save attendance codes.", "error");
    }
  }

  function openAddCode() {
    editCodeKey = null;
    if (codeDrawerTitle) codeDrawerTitle.textContent = "Add Attendance Code";
    codeForm?.reset();
    if (noTimeField) noTimeField.checked = false;
    if (timeTrackedField) timeTrackedField.checked = false;
    drawer?.open();
  }

  function openEditCode(code) {
    const row = codes.find((x) => x.code === code);
    if (!row) return;
    editCodeKey = code;
    if (codeDrawerTitle) codeDrawerTitle.textContent = `Edit Code: ${code}`;

    codeField.value = row.code;
    descField.value = row.desc;
    notesField.value = row.notes || "";
    presentField.checked = !!row.present;
    paidField.checked = !!row.paid;
    deductField.checked = !!row.deduct;
    if (noTimeField) noTimeField.checked = !!row.noTime;
    if (timeTrackedField) timeTrackedField.checked = !!row.timeTracked;

    drawer?.open();
  }

  addCodeBtn?.addEventListener("click", openAddCode);

  codesTbody?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const act = btn.getAttribute("data-act");
    const code = btn.getAttribute("data-code");

    if (act === "edit") openEditCode(code);
    if (act === "del") {
      if (!confirm(`Delete code ${code}?`)) return;
      codes = codes.filter((x) => x.code !== code);
      renderCodes();
      fillCodeDefaults();
      fillPolicySelectors();
      saveCodes();
      if (!showNotice("Attendance code deleted.")) {
        toast("Code deleted.");
      }
      if (typeof onChange === "function") onChange();
    }
  });

  codeForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    const code = String(codeField.value || "").trim().toUpperCase();
    const desc = String(descField.value || "").trim();
    const notes = String(notesField.value || "").trim();

    if (!code || !desc) {
      toast("Code + Description are required.", "error");
      return;
    }
    if (code === "UL" || code === "LWOP" || desc.toLowerCase() === "unpaid leave") {
      toast('Unpaid Leave is treated the same as Absent. Please use code "A" (Absent).', "error");
      return;
    }

    const exists = codes.some((x) => x.code === code);
    if (!editCodeKey && exists) {
      toast("Code already exists.", "error");
      return;
    }
    if (editCodeKey && code !== editCodeKey && exists) {
      toast("Another row already uses this code.", "error");
      return;
    }

    const payload = {
      code,
      desc,
      notes,
      present: !!presentField.checked,
      paid: !!paidField.checked,
      deduct: !!deductField.checked,
      noTime: !!noTimeField?.checked,
      timeTracked: !!timeTrackedField?.checked,
    };

    if (!editCodeKey) {
      codes.push(payload);
    } else {
      codes = codes.map((x) => (x.code === editCodeKey ? payload : x));
    }

    drawer?.close();
    renderCodes();
    fillCodeDefaults();
    saveCodes();
    if (!showNotice("Attendance code saved.")) {
      toast("Saved attendance code.");
    }
    if (typeof onChange === "function") onChange();
  });

  defaultNoLogCode?.addEventListener("change", () => saveCodes());
  defaultSundayCode?.addEventListener("change", () => saveCodes());
  paidLeaveCapDays?.addEventListener("change", () => saveCodes());

  renderCodes();
  fillCodeDefaults();
  fillPolicySelectors();
  loadCodes();
}
