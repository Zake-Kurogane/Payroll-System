// resources/js/settings.js
document.addEventListener("DOMContentLoaded", () => {
  // ===== Clock =====
  const clockEl = document.getElementById("clock");
  const dateEl = document.getElementById("date");

  function pad(n){ return String(n).padStart(2,"0"); }
  function tick(){
    const d = new Date();
    let h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12; h = h ? h : 12;
    if (clockEl) clockEl.textContent = `${pad(h)}:${pad(m)} ${ampm}`;
    if (dateEl) dateEl.textContent = `${d.getMonth()+1}/${d.getDate()}/${d.getFullYear()}`;
  }
  tick();
  setInterval(tick, 1000);

  // ===== Admin dropdown =====
  const userMenuBtn = document.getElementById("userMenuBtn");
  const userMenu = document.getElementById("userMenu");

  function closeUserMenu(){
    if (!userMenuBtn || !userMenu) return;
    userMenu.classList.remove("is-open");
    userMenuBtn.setAttribute("aria-expanded","false");
  }
  function toggleUserMenu(){
    if (!userMenuBtn || !userMenu) return;
    const isOpen = userMenu.classList.contains("is-open");
    userMenu.classList.toggle("is-open", !isOpen);
    userMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }
  if (userMenuBtn && userMenu){
    userMenuBtn.addEventListener("click",(e)=>{ e.stopPropagation(); toggleUserMenu(); });
    document.addEventListener("click",(e)=>{ if (!userMenu.contains(e.target)) closeUserMenu(); });
    document.addEventListener("keydown",(e)=>{ if (e.key==="Escape") closeUserMenu(); });
  }

  // ===== Tabs =====
  const tabButtons = document.querySelectorAll(".tab");
  const panels = {
    company: document.getElementById("tab-company"),
    calendar: document.getElementById("tab-calendar"),
    paygroups: document.getElementById("tab-paygroups"),
    proration: document.getElementById("tab-proration"),
    timekeeping: document.getElementById("tab-timekeeping"),
    attendance: document.getElementById("tab-attendance"),
    ot: document.getElementById("tab-ot"),
    statutory: document.getElementById("tab-statutory"),
    withholdingtax: document.getElementById("tab-withholdingtax"),
    cashadvance: document.getElementById("tab-cashadvance"),
  };

  function openTab(key){
    tabButtons.forEach(b => b.classList.toggle("is-active", b.dataset.tab === key));
    Object.values(panels).forEach(p => p && p.classList.remove("is-open"));
    if (panels[key]) panels[key].classList.add("is-open");
  }

  tabButtons.forEach(btn => {
    btn.addEventListener("click", () => openTab(btn.dataset.tab));
  });

  // ===== Company Setup (UI-only) =====
  document.getElementById("companySave")?.addEventListener("click", () => {
    alert("Saved Company Setup (UI only).");
  });

  // ===== Payroll Calendar (UI-only) =====
  document.getElementById("calendarSave")?.addEventListener("click", () => {
    alert("Saved Payroll Calendar (UI only).");
  });

  // ===== Pay Groups (3 fixed rows) =====
  const pgTbody = document.getElementById("pgTbody");

  let payGroups = [
    { name:"Tagum", freq:"Semi-monthly", calendar:"Payroll Calendar", shift:"", otMode:"" },
    { name:"Davao", freq:"Semi-monthly", calendar:"Payroll Calendar", shift:"", otMode:"" },
    { name:"Area",  freq:"Semi-monthly", calendar:"Payroll Calendar", shift:"", otMode:"" },
  ];

  function renderPayGroups(){
    if (!pgTbody) return;
    pgTbody.innerHTML = "";
    payGroups.forEach((g, idx) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><b>${g.name}</b></td>
        <td>${g.freq}</td>
        <td>${g.calendar}</td>
        <td><input type="time" data-pg="shift" data-idx="${idx}" value="${g.shift || ""}" /></td>
        <td>
          <select data-pg="otMode" data-idx="${idx}">
            <option value="" ${!g.otMode ? "selected":""}>—</option>
            <option value="flat_rate" ${g.otMode==="flat_rate"?"selected":""}>flat_rate</option>
            <option value="rate_based" ${g.otMode==="rate_based"?"selected":""}>rate_based</option>
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
    alert("Saved Pay Groups (UI only).");
  });

  // ===== Salary & Proration (UI-only) =====
  document.getElementById("prorationSave")?.addEventListener("click", () => {
    alert("Saved Salary & Proration Rules (UI only).");
  });

  // ===== Timekeeping dynamic fields =====
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

  function syncLateUI(){
    const type = lateRuleType?.value || "rate_based";
    const isFlat = type === "flat_penalty";
    if (latePenaltyPerMinute) latePenaltyPerMinute.disabled = !isFlat;
    if (lateHint) lateHint.style.display = isFlat ? "none" : "block";
  }

  function syncUndertimeUI(){
    const enabled = !!undertimeEnabled?.checked;
    if (undertimeRuleType) undertimeRuleType.disabled = !enabled;

    const type = undertimeRuleType?.value || "rate_based";
    const isFlat = type === "flat_penalty";

    if (undertimePenaltyPerMinute) undertimePenaltyPerMinute.disabled = !(enabled && isFlat);
    if (undertimeHint) undertimeHint.style.display = (!enabled || isFlat) ? "none" : "block";
  }

  function syncWorkMins(){
    const v = Number(workMinutesPerDay?.value || 480);
    const safe = (Number.isFinite(v) && v > 0) ? v : 480;
    if (otWorkMinsSummary) otWorkMinsSummary.value = String(safe);
    if (salaryWorkMins) salaryWorkMins.value = String(safe);
  }

  lateRuleType?.addEventListener("change", syncLateUI);
  undertimeEnabled?.addEventListener("change", syncUndertimeUI);
  undertimeRuleType?.addEventListener("change", syncUndertimeUI);
  workMinutesPerDay?.addEventListener("input", syncWorkMins);

  // Reset/Save demo
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
    alert("Reset to default (UI).");
  });

  tkSave?.addEventListener("click", () => {
    alert("Saved Timekeeping Rules (UI only). Backend can store these in DB.");
  });

  // ===== Attendance Codes (CRUD demo) =====
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
    { code:"P",   desc:"Present",      present:true,  paid:true,  deduct:false, notes:"" },
    { code:"PL",  desc:"Paid Leave",    present:true,  paid:true,  deduct:false, notes:"" },
    { code:"UL",  desc:"Unpaid Leave",  present:false, paid:false, deduct:true,  notes:"" },
    { code:"A",   desc:"Absent",       present:false, paid:false, deduct:true,  notes:"Default no log" },
    { code:"HD",  desc:"Half-day",     present:true,  paid:true,  deduct:true,  notes:"Depends later" },
    { code:"OFF", desc:"Rest Day",     present:false, paid:false, deduct:false, notes:"Default Sunday" },
  ];

  // --- PORTAL drawers to <body> so overlay covers top nav (match Attendance) ---
  [codeDrawer].forEach(d => {
    if (d && d.parentElement !== document.body) document.body.appendChild(d);
  });

  function openDrawer(el){
  if (!el) return;
  el.classList.add("is-open");
  el.setAttribute("aria-hidden","false");
  document.body.classList.add("drawer-open");
}

function closeDrawer(el){
  if (!el) return;
  el.classList.remove("is-open");
  el.setAttribute("aria-hidden","true");
  document.body.classList.remove("drawer-open");
}

  function renderCodes(){
    if (!codesTbody) return;
    codesTbody.innerHTML = "";

    codes.forEach(row => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${row.code}</td>
        <td>${row.desc}</td>
        <td><input type="checkbox" ${row.present ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.paid ? "checked" : ""} disabled></td>
        <td><input type="checkbox" ${row.deduct ? "checked" : ""} disabled></td>
        <td>${row.notes || "—"}</td>
        <td>
          <div class="miniRow">
            <button class="miniBtn" data-act="edit" data-code="${row.code}" aria-label="Edit">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-act="del" data-code="${row.code}" aria-label="Delete">
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

  function fillCodeDefaults(){
    if (!defaultNoLogCode || !defaultSundayCode) return;

    const makeOpts = (sel, chosen) => {
      sel.innerHTML = "";
      codes.forEach(c => {
        const opt = document.createElement("option");
        opt.value = c.code;
        opt.textContent = `${c.code} — ${c.desc}`;
        sel.appendChild(opt);
      });
      if (chosen) sel.value = chosen;
    };

    makeOpts(defaultNoLogCode, codes.some(c=>c.code==="A") ? "A" : (codes[0]?.code || ""));
    makeOpts(defaultSundayCode, codes.some(c=>c.code==="OFF") ? "OFF" : (codes[0]?.code || ""));
  }

  function openAddCode(){
    editCodeKey = null;
    codeDrawerTitle.textContent = "Add Attendance Code";
    codeForm.reset();
    openDrawer(codeDrawer);
  }

  function openEditCode(code){
    const row = codes.find(x => x.code === code);
    if (!row) return;
    editCodeKey = code;
    codeDrawerTitle.textContent = `Edit Code: ${code}`;

    codeField.value = row.code;
    descField.value = row.desc;
    notesField.value = row.notes || "";
    presentField.checked = !!row.present;
    paidField.checked = !!row.paid;
    deductField.checked = !!row.deduct;

    openDrawer(codeDrawer);
  }

  addCodeBtn?.addEventListener("click", openAddCode);
  closeCodeDrawer?.addEventListener("click", () => closeDrawer(codeDrawer));
  codeDrawerOverlay?.addEventListener("click", () => closeDrawer(codeDrawer));
  cancelCodeBtn?.addEventListener("click", () => closeDrawer(codeDrawer));

  codesTbody?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const act = btn.getAttribute("data-act");
    const code = btn.getAttribute("data-code");

    if (act === "edit") openEditCode(code);
    if (act === "del"){
      if (!confirm(`Delete code ${code}?`)) return;
      codes = codes.filter(x => x.code !== code);
      renderCodes();
      fillCodeDefaults();
    }
  });

  codeForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    const code = String(codeField.value || "").trim().toUpperCase();
    const desc = String(descField.value || "").trim();
    const notes = String(notesField.value || "").trim();

    if (!code || !desc){
      alert("Code + Description are required.");
      return;
    }

    const exists = codes.some(x => x.code === code);
    if (!editCodeKey && exists){
      alert("Code already exists.");
      return;
    }
    if (editCodeKey && code !== editCodeKey && exists){
      alert("Another row already uses this code.");
      return;
    }

    const payload = {
      code,
      desc,
      notes,
      present: !!presentField.checked,
      paid: !!paidField.checked,
      deduct: !!deductField.checked
    };

    if (!editCodeKey){
      codes.push(payload);
    } else {
      codes = codes.map(x => x.code === editCodeKey ? payload : x);
    }

    closeDrawer(codeDrawer);
    renderCodes();
    fillCodeDefaults();
  });

  // ===== OT dynamic fields =====
  const otModeFlat = document.getElementById("otModeFlat");
  const otModeRate = document.getElementById("otModeRate");
  const otFlatWrap = document.getElementById("otFlatWrap");
  const otMultWrap = document.getElementById("otMultWrap");

  function syncOtUI(){
    const mode = (otModeRate?.checked) ? "rate_based" : "flat_rate";
    if (otFlatWrap) otFlatWrap.style.display = mode === "flat_rate" ? "block" : "none";
    if (otMultWrap) otMultWrap.style.display = mode === "rate_based" ? "block" : "none";
  }
  otModeFlat?.addEventListener("change", syncOtUI);
  otModeRate?.addEventListener("change", syncOtUI);

  document.getElementById("otSave")?.addEventListener("click", () => {
    alert("Saved Overtime Rules (UI only).");
  });

  // ===== Statutory =====
  const sssImportBtn = document.getElementById("sssImportBtn");
  const sssImportFile = document.getElementById("sssImportFile");
  sssImportBtn?.addEventListener("click", () => sssImportFile?.click());
  sssImportFile?.addEventListener("change", () => {
    const f = sssImportFile.files?.[0];
    if (!f) return;
    alert(`Selected file: ${f.name}\n\n(Import placeholder only.)`);
    sssImportFile.value = "";
  });

  document.getElementById("statSave")?.addEventListener("click", () => {
    alert("Saved Statutory Setup (UI only).");
  });

  // ===== Withholding Tax dynamic UI + placeholder preview =====
  const wtMethod = document.getElementById("wtMethod");
  const wtFixedWrap = document.getElementById("wtFixedWrap");
  const wtPercentWrap = document.getElementById("wtPercentWrap");

  function syncWtUI(){
    const m = wtMethod?.value || "table";
    if (wtFixedWrap) wtFixedWrap.style.display = (m === "manual_fixed") ? "block" : "none";
    if (wtPercentWrap) wtPercentWrap.style.display = (m === "manual_percent") ? "block" : "none";
  }
  wtMethod?.addEventListener("change", syncWtUI);

  const wtImportBtn = document.getElementById("wtImportBtn");
  const wtImportFile = document.getElementById("wtImportFile");
  wtImportBtn?.addEventListener("click", () => wtImportFile?.click());
  wtImportFile?.addEventListener("change", () => {
    const f = wtImportFile.files?.[0];
    if (!f) return;
    alert(`Selected file: ${f.name}\n\n(Import placeholder only.)`);
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

    if (wtPreviewBody){
      wtPreviewBody.innerHTML = "";
      demo.forEach(r => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${r.from.toLocaleString()}</td>
          <td>${r.to.toLocaleString()}</td>
          <td>₱ ${r.base.toLocaleString()}</td>
          <td>${r.excess}%</td>
        `;
        wtPreviewBody.appendChild(tr);
      });
    }

    if (wtEmptyState) wtEmptyState.style.display = "none";
    if (wtPreviewWrap) wtPreviewWrap.style.display = "block";
    alert("Default withholding tax table preview loaded (UI only).");
  });

  document.getElementById("wtSavePolicy")?.addEventListener("click", () => {
    alert("Saved Withholding Tax Policy (UI only).");
  });

  document.getElementById("wtSaveAll")?.addEventListener("click", () => {
    alert("Saved Withholding Tax Setup (UI only).");
  });

  // ===== Cash Advance Transactions =====
  const caTbody = document.getElementById("caTbody");
  const newCaBtn = document.getElementById("newCaBtn");

  const caDrawer = document.getElementById("caDrawer");
  const caDrawerOverlay = document.getElementById("caDrawerOverlay");
  const closeCaDrawer = document.getElementById("closeCaDrawer");
  const cancelCaBtn = document.getElementById("cancelCaBtn");
  const caForm = document.getElementById("caForm");

  // ensure cash advance drawer is also portaled to body for full-screen blur
  [caDrawer].forEach(d => {
    if (d && d.parentElement !== document.body) document.body.appendChild(d);
  });

  let cashAdvances = [
    { id:1, employee:"Maria Santos", amount:5000, term:3, start:"2026-02", method:"salary_deduction", status:"Active" },
  ];

  function peso(n){
    const v = Number(n);
    if (!Number.isFinite(v)) return "—";
    return `₱ ${v.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2})}`;
  }

  function computePerCutoff(amount, termMonths){
    const totalCutoffs = Math.max(1, Number(termMonths) * 2);
    return amount / totalCutoffs;
  }

  function renderCa(){
    if (!caTbody) return;
    caTbody.innerHTML = "";

    cashAdvances.forEach(row => {
      const per = computePerCutoff(row.amount, row.term);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${row.employee}</td>
        <td>${peso(row.amount)}</td>
        <td>${row.term} mo</td>
        <td>${row.start}</td>
        <td>${peso(per)} <span class="muted small">(placeholder)</span></td>
        <td>${row.status}</td>
        <td>
          <div class="miniRow">
            <button class="miniBtn" data-ca="view" data-id="${row.id}" aria-label="View">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
            <button class="miniBtn" data-ca="edit" data-id="${row.id}" aria-label="Edit">
              <svg class="miniBtn__icon" viewBox="0 0 24 24">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
              </svg>
            </button>
            <button class="miniBtn miniBtn--danger" data-ca="close" data-id="${row.id}" aria-label="Close">
              <svg class="miniBtn__icon" viewBox="0 0 24 24"><path d="m7 7 10 10"/><path d="m17 7-10 10"/></svg>
            </button>
          </div>
        </td>
      `;
      caTbody.appendChild(tr);
    });
  }

  function openNewCa(){
    caForm.reset();
    document.getElementById("caTerm").value = document.getElementById("caDefaultTermMonths")?.value || "3";
    document.getElementById("caMethodTxn").value = document.getElementById("caMethod")?.value || "salary_deduction";
    openDrawer(caDrawer);
  }

  newCaBtn?.addEventListener("click", openNewCa);
  closeCaDrawer?.addEventListener("click", () => closeDrawer(caDrawer));
  caDrawerOverlay?.addEventListener("click", () => closeDrawer(caDrawer));
  cancelCaBtn?.addEventListener("click", () => closeDrawer(caDrawer));

  caForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    const employee = document.getElementById("caEmployee").value;
    const amount = Number(document.getElementById("caAmount").value || 0);
    const term = Number(document.getElementById("caTerm").value || 1);
    const start = document.getElementById("caStartMonth").value;
    const method = document.getElementById("caMethodTxn").value;

    if (!employee || !start || !Number.isFinite(amount) || amount <= 0){
      alert("Please fill Employee, Amount, Start month.");
      return;
    }

    const id = Math.max(0, ...cashAdvances.map(x => x.id)) + 1;
    cashAdvances.push({ id, employee, amount, term, start, method, status:"Active" });

    closeDrawer(caDrawer);
    renderCa();
  });

  caTbody?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-ca]");
    if (!btn) return;
    const act = btn.getAttribute("data-ca");
    const id = Number(btn.getAttribute("data-id"));
    const row = cashAdvances.find(x => x.id === id);
    if (!row) return;

    if (act === "view"){
      alert(`View:\n${row.employee}\n${peso(row.amount)}\nTerm: ${row.term} mo\nStart: ${row.start}\nStatus: ${row.status}`);
    }

    if (act === "edit"){
      alert("Edit UI: wire a second drawer state if you want. (Kept simple here.)");
    }

    if (act === "close"){
      if (!confirm("Close this cash advance?")) return;
      cashAdvances = cashAdvances.map(x => x.id === id ? { ...x, status:"Completed" } : x);
      renderCa();
    }
  });

  document.getElementById("caPolicySave")?.addEventListener("click", () => {
    alert("Saved Cash Advance Policy (UI only).");
  });

  // ===== Init =====
  openTab("company");
  syncLateUI();
  syncUndertimeUI();
  syncWorkMins();
  syncOtUI();
  syncWtUI();

  renderPayGroups();
  renderCodes();
  fillCodeDefaults();
  renderCa();
});
