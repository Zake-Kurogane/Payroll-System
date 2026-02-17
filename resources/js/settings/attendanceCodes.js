import { esc } from "./utils";
import { createDrawer } from "./drawer";

export function initAttendanceCodes(toast) {
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
  const codeDrawerTitle = document.getElementById("codeDrawerTitle");

  const defaultNoLogCode = document.getElementById("defaultNoLogCode");
  const defaultSundayCode = document.getElementById("defaultSundayCode");

  let editCodeKey = null;

  let codes = [
    { code: "P", desc: "Present", present: true, paid: true, deduct: false, notes: "" },
    { code: "PL", desc: "Paid Leave", present: true, paid: true, deduct: false, notes: "" },
    { code: "UL", desc: "Unpaid Leave", present: false, paid: false, deduct: true, notes: "" },
    { code: "A", desc: "Absent", present: false, paid: false, deduct: true, notes: "Default no log" },
    { code: "HD", desc: "Half-day", present: true, paid: true, deduct: true, notes: "Depends later" },
    { code: "OFF", desc: "Rest Day", present: false, paid: false, deduct: false, notes: "Default Sunday" },
  ];

  if (codeDrawer && codeDrawer.parentElement !== document.body) {
    document.body.appendChild(codeDrawer);
  }

  const drawer = createDrawer(codeDrawer, codeDrawerOverlay, [closeCodeDrawer, cancelCodeBtn]);

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
        <td>${esc(row.notes || "—")}</td>
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
        opt.textContent = `${c.code} — ${c.desc}`;
        sel.appendChild(opt);
      });
      if (chosen) sel.value = chosen;
    };

    makeOpts(defaultNoLogCode, codes.some((c) => c.code === "A") ? "A" : (codes[0]?.code || ""));
    makeOpts(defaultSundayCode, codes.some((c) => c.code === "OFF") ? "OFF" : (codes[0]?.code || ""));
  }

  function openAddCode() {
    editCodeKey = null;
    if (codeDrawerTitle) codeDrawerTitle.textContent = "Add Attendance Code";
    codeForm?.reset();
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
      toast("Code deleted.");
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
    };

    if (!editCodeKey) {
      codes.push(payload);
    } else {
      codes = codes.map((x) => (x.code === editCodeKey ? payload : x));
    }

    drawer?.close();
    renderCodes();
    fillCodeDefaults();
    toast("Saved attendance code.");
  });

  renderCodes();
  fillCodeDefaults();
}

