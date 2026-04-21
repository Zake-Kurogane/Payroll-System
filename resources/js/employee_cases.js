import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  const tbody = document.getElementById("caseTbody");
  const resultsMeta = document.getElementById("resultsMeta");
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const sanctionFilter = document.getElementById("sanctionFilter");
  const monthFilter = document.getElementById("monthFilter");
  const toastEl = document.getElementById("toast");

  const statOpen = document.getElementById("statOpen");
  const statHearing = document.getElementById("statHearing");
  const statDecision = document.getElementById("statDecision");
  const statSanctions = document.getElementById("statSanctions");
  const statTerminated = document.getElementById("statTerminated");
  const statTotal = document.getElementById("statTotal");

  const historyBtn = document.getElementById("historyBtn");
  const uploadDocBtn = document.getElementById("uploadDocBtn");
  const exportCasesBtn = document.getElementById("exportCasesBtn");
  const newCaseBtn = document.getElementById("newCaseBtn");
  const uploadDocInput = document.getElementById("uploadDocInput");

  const newCaseModal = document.getElementById("newCaseModal");
  const newCaseOverlay = document.getElementById("newCaseOverlay");
  const uploadDocModal = document.getElementById("uploadDocModal");
  const uploadDocOverlay = document.getElementById("uploadDocOverlay");
  const exportModal = document.getElementById("exportModal");
  const exportOverlay = document.getElementById("exportOverlay");
  const historyModal = document.getElementById("historyModal");
  const historyOverlay = document.getElementById("historyOverlay");
  const ud_caseSelect = document.getElementById("ud_caseSelect");
  const exportSubmit = document.getElementById("exportSubmit");
  const uploadDocSubmit = document.getElementById("uploadDocSubmit");
  const historySubmit = document.getElementById("historySubmit");
  const saveDraftBtn = document.getElementById("saveDraftBtn");
  const createCaseBtn = document.getElementById("createCaseBtn");

  let casesCache = [];
  let stageLabels = {};   // value → label, populated from DB
  let sanctionLabels = {}; // value → label, populated from DB

  function showToast(message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add("is-show");
    setTimeout(() => toastEl.classList.remove("is-show"), 1600);
  }

  function fmtDate(d) {
    if (!d) return "—";
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
  }

  const STAGE_BADGE_CLASS = {
    reported:    "badge--reported",
    nte_issued:  "badge--nte",
    for_hearing: "badge--hearing",
    decided:     "badge--decided",
    closed:      "badge--closed",
  };

  function badge(status) {
    const label = stageLabels[status] || status || "—";
    const cls   = STAGE_BADGE_CLASS[status] || "";
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function openModal(modal, overlay) {
    if (!modal || !overlay) return;
    closeAllModals();
    modal.setAttribute("aria-hidden", "false");
    modal.classList.add("is-open");
    overlay.removeAttribute("hidden");
  }

  function closeModal(modal, overlay) {
    if (!modal || !overlay) return;
    modal.setAttribute("aria-hidden", "true");
    modal.classList.remove("is-open");
    overlay.setAttribute("hidden", "");
  }

  const viewCaseModal = document.getElementById("viewCaseModal");
  const viewCaseOverlay = document.getElementById("viewCaseOverlay");
  const manageCaseModal   = document.getElementById("manageCaseModal");
  const manageCaseOverlay = document.getElementById("manageCaseOverlay");
  const manageCaseBody    = document.getElementById("manageCaseBody");
  const advanceCaseBtn    = document.getElementById("advanceCaseBtn");
  let   manageCaseId      = null;

  function closeAllModals() {
    [newCaseModal, uploadDocModal, exportModal, historyModal, viewCaseModal, manageCaseModal].forEach((m) => m && m.classList.remove("is-open"));
    [newCaseOverlay, uploadDocOverlay, exportOverlay, historyOverlay, viewCaseOverlay, manageCaseOverlay].forEach((o) => o && o.setAttribute("hidden", ""));
  }

  function render(rows) {
    if (!tbody) return;
    if (!rows || !rows.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="muted small">No cases found.</td></tr>`;
      resultsMeta && (resultsMeta.textContent = "Showing 0 case(s)");
      return;
    }
    tbody.innerHTML = rows
      .map(
        (r) => `
      <tr>
        <td>${r.case_no}</td>
        <td>${fmtDate(r.date_reported)}</td>
        <td>${(r.respondents || []).join(", ") || "—"}</td>
        <td>${(r.complainants || []).join(", ") || "—"}</td>
        <td>${r.case_type === "spot_report" ? "Spot Report" : "Incident Report"}</td>
        <td>${badge(r.status)}</td>
        <td>${r.sanction_type && r.sanction_type !== "none" ? (sanctionLabels[r.sanction_type] || r.sanction_type.replace(/_/g, " ")) : "—"}</td>
        <td>${r.sanction_status ? r.sanction_status : r.status === "closed" ? "Closed" : "Open"}</td>
        <td style="display:flex;gap:6px;">
          <button class="btn--icon" data-view="${r.id}" type="button" title="View case"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/></svg></button>
          <button class="btn--icon" data-manage="${r.id}" data-status="${r.status}" type="button" title="Manage case"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        </td>
      </tr>`
      )
      .join("");
    resultsMeta && (resultsMeta.textContent = `Showing ${rows.length} case(s)`);
  }

  tbody && tbody.addEventListener("click", (e) => {
    const viewBtn = e.target.closest("[data-view]");
    if (viewBtn) { openCaseDrawer(viewBtn.dataset.view); return; }
    const manageBtn = e.target.closest("[data-manage]");
    if (manageBtn) openManageDrawer(manageBtn.dataset.manage, manageBtn.dataset.status);
  });

  async function load() {
    const params = new URLSearchParams();
    if (searchInput?.value) params.set("search", searchInput.value.trim());
    params.set("status", statusFilter?.value || "all");
    params.set("sanction_type", sanctionFilter?.value || "all");
    if (monthFilter?.value) params.set("month", monthFilter.value);

    try {
      const res = await fetch(`/employee-cases/list?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      const payload = await res.json();
      const rows = Array.isArray(payload.data) ? payload.data : [];
      casesCache = rows;
      render(rows);
      updateStats(rows, payload.stats || {});
      populateCaseSelect(rows);
    } catch (err) {
      render([]);
      showToast("Failed to load cases.");
    }
  }

  function updateStats(rows, stats) {
    const list = Array.isArray(rows) ? rows : [];
    const count = (status) => list.filter((r) => r.status === status).length;
    statTotal && (statTotal.textContent = list.length);
    statOpen && (statOpen.textContent = stats.open ?? list.filter((r) => r.status && r.status !== "closed").length);
    statHearing && (statHearing.textContent = stats.for_hearing ?? count("for_hearing"));
    statDecision && (statDecision.textContent = stats.for_decision ?? count("for_decision"));
    statSanctions && (statSanctions.textContent = stats.active_sanctions ?? 0);
    statTerminated && (statTerminated.textContent = stats.terminated ?? 0);
  }

  function populateCaseSelect(rows) {
    if (!ud_caseSelect) return;
    ud_caseSelect.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "Select a case";
    ud_caseSelect.appendChild(opt);
    rows.forEach((r) => {
      const o = document.createElement("option");
      o.value = r.id;
      o.textContent = `${r.case_no} — ${(r.respondents || []).join(", ") || "Respondent"}`;
      ud_caseSelect.appendChild(o);
    });
  }

  [searchInput, statusFilter, sanctionFilter, monthFilter].forEach((el) => {
    el && el.addEventListener("change", load);
  });

  // Keep month filter empty by default so list shows all records unless user narrows it.
  if (monthFilter) monthFilter.value = "";
  window.loadCases = load;

  // Populate Stage and Sanction selects from DB
  async function loadFilters() {
    try {
      const res = await fetch("/employee-cases/filters", { headers: { Accept: "application/json" } });
      const data = await res.json();

      if (Array.isArray(data.stages)) {
        data.stages.forEach(({ value, label }) => {
          stageLabels[value] = label;
          if (statusFilter) {
            const opt = document.createElement("option");
            opt.value = value;
            opt.textContent = label;
            statusFilter.appendChild(opt);
          }
        });
      }

      if (Array.isArray(data.sanctions)) {
        const exSanction = document.getElementById("ex_sanction");
        data.sanctions.forEach(({ value, label }) => {
          sanctionLabels[value] = label;
          [sanctionFilter, exSanction].forEach((sel) => {
            if (!sel) return;
            const opt = document.createElement("option");
            opt.value = value;
            opt.textContent = label;
            sel.appendChild(opt);
          });
        });
      }

      // Populate location select with optgroups from area_places
      const locationSel = document.getElementById("nc_location");
      if (locationSel && data.area_places && typeof data.area_places === "object") {
        Object.entries(data.area_places).forEach(([group, places]) => {
          const grp = document.createElement("optgroup");
          grp.label = group;
          places.forEach((place) => {
            const opt = document.createElement("option");
            opt.value = place;
            opt.textContent = place;
            grp.appendChild(opt);
          });
          locationSel.appendChild(grp);
        });
      }
    } catch (_) {
      // filters still work with just "All" fallback
    }
  }

  loadFilters().then(() => load());

  // --- View Case Drawer ---
  function fmtDateShort(d) {
    if (!d) return "—";
    const dt = new Date(d + "T00:00:00");
    return dt.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
  }

  function chips(arr, neutral = false) {
    if (!arr || !arr.length) return `<span class="vc-empty">—</span>`;
    return arr.map((v) => `<span class="vc-chip${neutral ? " vc-chip--neutral" : ""}">${v}</span>`).join(" ");
  }

  function renderCaseDrawer(c) {
    const stageLabel = stageLabels[c.status] || c.status || "—";
    const sanctionLabel = (c.sanctions && c.sanctions.length)
      ? (sanctionLabels[c.sanctions[0].sanction_type] || c.sanctions[0].sanction_type?.replace(/_/g, " ") || "—")
      : "None";

    const sanctionsHtml = (c.sanctions && c.sanctions.length)
      ? c.sanctions.map((s) => `
          <div class="vc-sanction">
            <span class="vc-chip">${sanctionLabels[s.sanction_type] || s.sanction_type?.replace(/_/g, " ") || "—"}</span>
            <span class="vc-empty">${s.effective_from ? fmtDateShort(s.effective_from) + (s.effective_to ? " → " + fmtDateShort(s.effective_to) : "") : ""}</span>
            <span class="vc-chip vc-chip--neutral" style="margin-left:auto">${s.status || "—"}</span>
          </div>`).join("")
      : `<span class="vc-empty">No sanctions recorded.</span>`;

    const hearingsHtml = (c.hearings && c.hearings.length)
      ? c.hearings.map((h) => `
          <div class="vc-sanction">
            <span class="vc-val">${fmtDateShort(h.hearing_date)}</span>
            ${h.location ? `<span class="vc-empty">· ${h.location}</span>` : ""}
            <span class="vc-chip vc-chip--neutral" style="margin-left:auto">${h.status || "—"}</span>
          </div>`).join("")
      : `<span class="vc-empty">No hearings recorded.</span>`;

    document.getElementById("vc_caseNo").textContent = `Case ${c.case_no}`;
    document.getElementById("vc_title").textContent = c.title || "—";

    document.getElementById("viewCaseBody").innerHTML = `
      <div class="sectionTitle">Case Information</div>
      <div class="vc-section">
        <div class="vc-row"><span class="vc-label">Stage</span><span class="vc-val">${stageLabel}</span></div>
        <div class="vc-row"><span class="vc-label">Sanction</span><span class="vc-val">${sanctionLabel}</span></div>
        <div class="vc-row"><span class="vc-label">Incident Date</span><span class="vc-val">${fmtDateShort(c.incident_date)}</span></div>
        <div class="vc-row"><span class="vc-label">Date Reported</span><span class="vc-val">${fmtDateShort(c.date_reported)}</span></div>
        ${c.location ? `<div class="vc-row"><span class="vc-label">Location</span><span class="vc-val">${c.location}</span></div>` : ""}
      </div>

      <div class="sectionTitle">Parties Involved</div>
      <div class="vc-section">
        <div class="vc-row"><span class="vc-label">Respondent(s)</span><span class="vc-list">${chips(c.respondents)}</span></div>
        <div class="vc-row"><span class="vc-label">Complainant(s)</span><span class="vc-list">${chips(c.complainants, true)}</span></div>
        ${c.witnesses && c.witnesses.length ? `<div class="vc-row"><span class="vc-label">Witnesses</span><span class="vc-list">${chips(c.witnesses, true)}</span></div>` : ""}
      </div>

      ${c.description ? `
      <div class="sectionTitle">Incident Details</div>
      <div class="vc-desc">${c.description}</div>` : ""}

      <div class="sectionTitle">Sanctions</div>
      ${sanctionsHtml}

      <div class="sectionTitle">Hearings</div>
      ${hearingsHtml}
    `;
  }

  async function openCaseDrawer(id) {
    if (!viewCaseModal || !viewCaseOverlay) return;
    document.getElementById("vc_caseNo").textContent = "Case —";
    document.getElementById("vc_title").textContent = "Loading…";
    document.getElementById("viewCaseBody").innerHTML = `<div class="muted small">Loading…</div>`;
    openModal(viewCaseModal, viewCaseOverlay);
    try {
      const res = await fetch(`/employee-cases/${id}`, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error();
      renderCaseDrawer(await res.json());
    } catch (_) {
      document.getElementById("viewCaseBody").innerHTML = `<div class="muted small">Failed to load case.</div>`;
    }
  }

  const STAGE_NEXT = {
    reported:     'nte_issued',
    nte_issued:   'for_hearing',
    for_hearing:  'decided',
    for_decision: 'decided',   // legacy — goes straight to sanction
    decided:      'closed',
  };

  const STAGE_LABEL_MAP = {
    reported:    'Reported',
    nte_issued:  'NTE Issued',
    for_hearing: 'For Hearing',
    decided:     'Sanction',
    closed:      'Closed',
  };

  function stageFields(nextStage) {
    if (nextStage === 'nte_issued') return `
      <div class="sectionTitle">NTE Details</div>
      <p class="muted small" style="margin:0 0 8px;">Record the Notice to Explain issued to the respondent.</p>
      <div class="grid2">
        <div class="field"><label>NTE Date</label><input type="date" id="mc_hearingDate" /></div>
        <div class="field"><label>Issued By / Notes</label><input type="text" id="mc_hearingNotes" placeholder="e.g. HR Manager" /></div>
      </div>`;
    if (nextStage === 'for_hearing') return `
      <div class="sectionTitle">Hearing Details</div>
      <p class="muted small" style="margin:0 0 8px;">Schedule the hearing where the respondent explains to the director.</p>
      <div class="grid2">
        <div class="field"><label>Hearing Date</label><input type="date" id="mc_hearingDate" /></div>
        <div class="field"><label>Location</label><input type="text" id="mc_hearingLocation" placeholder="e.g. Director's Office" /></div>
      </div>
      <div class="field"><label>Notes</label><textarea id="mc_hearingNotes" rows="2" placeholder="Additional notes about the hearing…"></textarea></div>`;
    if (nextStage === 'decided') return `
      <div class="sectionTitle">Sanction</div>
      <p class="muted small" style="margin:0 0 8px;">Apply a sanction to all respondents in this case.</p>
      <div class="field"><label>Sanction Type *</label><select id="mc_sanctionType"><option value="">— Select —</option></select></div>
      <div class="field" id="mc_daysWrap" style="display:none"><label>Days Suspended</label><input type="number" id="mc_sanctionDays" min="1" /></div>
      <div id="mc_dateRangeWrap" style="display:none">
        <div class="grid2">
          <div class="field"><label>Effective From</label><input type="date" id="mc_sanctionFrom" /></div>
          <div class="field"><label>Effective To</label><input type="date" id="mc_sanctionTo" /></div>
        </div>
      </div>
      <div class="field" id="mc_dateSingleWrap" style="display:none">
        <label>Effective Date</label>
        <input type="date" id="mc_sanctionEffective" />
      </div>
      <div class="field"><label>Remarks</label><textarea id="mc_sanctionRemarks" rows="2" placeholder="Additional notes…"></textarea></div>`;
    if (nextStage === 'closed') return `
      <div class="sectionTitle">Close Case</div>
      <p class="muted small" style="margin:0 0 8px;">This will mark the case as closed. No further changes can be made.</p>`;
    return '';
  }

  async function openManageDrawer(caseId, currentStatus) {
    manageCaseId = caseId;
    manageCaseBody.innerHTML = `<p class="muted small">Loading…</p>`;
    advanceCaseBtn.hidden = true;
    openModal(manageCaseModal, manageCaseOverlay);

    let c = null;
    try {
      c = await fetch(`/employee-cases/${caseId}`, { headers: { Accept: "application/json" } }).then(r => r.json());
    } catch { manageCaseBody.innerHTML = `<p class="muted small">Failed to load case.</p>`; return; }

    const status = c.status || currentStatus;
    const nextStage = STAGE_NEXT[status];
    const nextLabel = nextStage ? (stageLabels[nextStage] || STAGE_LABEL_MAP[nextStage] || nextStage) : null;
    const currLabel = stageLabels[status] || STAGE_LABEL_MAP[status] || status;

    document.getElementById("mc_caseNo").textContent = `Case ${c.case_no || caseId}`;
    document.getElementById("mc_title").textContent  = `${currLabel}${nextLabel ? " → " + nextLabel : " (Closed)"}`;

    const respondents  = (c.respondents  || []).join(", ") || "—";
    const complainants = (c.complainants || []).join(", ") || "—";

    const caseInfoHtml = `
      <div class="sectionTitle">Case Information</div>
      <div class="vc-row"><span class="vc-label">Respondent(s)</span><span class="vc-val">${respondents}</span></div>
      <div class="vc-row"><span class="vc-label">Complainant(s)</span><span class="vc-val">${complainants}</span></div>
      <div class="vc-row"><span class="vc-label">Incident Date</span><span class="vc-val">${fmtDate(c.incident_date)}</span></div>
      <div class="vc-row"><span class="vc-label">Location</span><span class="vc-val">${c.location || "—"}</span></div>
      ${c.description ? `<div class="vc-row" style="flex-direction:column;gap:4px;"><span class="vc-label">Description</span><span class="vc-desc">${c.description}</span></div>` : ""}`;

    if (!nextStage) {
      manageCaseBody.innerHTML = caseInfoHtml + `
        <div class="sectionTitle" style="margin-top:8px;">Status</div>
        <p class="muted small">This case is <strong>Closed</strong>. No further actions available.</p>`;
      advanceCaseBtn.hidden = true;
    } else {
      manageCaseBody.innerHTML = caseInfoHtml + `
        <div class="sectionTitle" style="margin-top:8px;">Stage Progression</div>
        <div class="field">
          <label>Advance to Stage</label>
          <select id="mc_nextStage"><option value="${nextStage}">${nextLabel}</option></select>
        </div>
        ${stageFields(nextStage)}`;
      advanceCaseBtn.hidden = false;
      advanceCaseBtn.dataset.nextStage = nextStage;

      if (nextStage === 'decided') {
        const sel = document.getElementById("mc_sanctionType");
        Object.entries(sanctionLabels).forEach(([val, lbl]) => {
          const o = document.createElement("option");
          o.value = val; o.textContent = lbl;
          sel.appendChild(o);
        });
        sel.addEventListener("change", () => {
          const v = sel.value;
          const isSuspension = v === "suspension";
          const isSingle     = v === "resignation" || v === "termination";
          document.getElementById("mc_daysWrap").style.display       = isSuspension ? "" : "none";
          document.getElementById("mc_dateRangeWrap").style.display  = isSuspension ? "" : "none";
          document.getElementById("mc_dateSingleWrap").style.display = isSingle     ? "" : "none";
        });
      }
    }
  }

  advanceCaseBtn && advanceCaseBtn.addEventListener("click", async () => {
    if (!manageCaseId) return;
    const nextStage = advanceCaseBtn.dataset.nextStage;
    const payload = { status: nextStage };

    // Collect stage-specific fields
    const g = (id) => document.getElementById(id)?.value?.trim() || null;
    if (nextStage === "nte_issued") {
      payload.hearing_date  = g("mc_hearingDate");
      payload.hearing_notes = g("mc_hearingNotes");
    }
    if (nextStage === "for_hearing") {
      payload.hearing_date     = g("mc_hearingDate");
      payload.hearing_location = g("mc_hearingLocation");
      payload.hearing_notes    = g("mc_hearingNotes");
    }
    if (nextStage === "decided") {
      const sType = g("mc_sanctionType");
      payload.sanction_type    = sType;
      payload.sanction_remarks = g("mc_sanctionRemarks");
      if (sType === "suspension") {
        payload.sanction_days = g("mc_sanctionDays");
        payload.sanction_from = g("mc_sanctionFrom");
        payload.sanction_to   = g("mc_sanctionTo");
      } else if (sType === "resignation" || sType === "termination") {
        payload.sanction_from = g("mc_sanctionEffective");
      }
    }

    advanceCaseBtn.disabled = true;
    advanceCaseBtn.textContent = "Saving…";
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      const res = await fetch(`/employee-cases/${manageCaseId}/advance`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error(await res.text());
      showToast("Case advanced successfully.");
      closeModal(manageCaseModal, manageCaseOverlay);
      load(); // refresh table
    } catch (err) {
      showToast("Failed to save: " + err.message);
    } finally {
      advanceCaseBtn.disabled = false;
      advanceCaseBtn.textContent = "Save & Advance";
    }
  });

  // Modal open/close
  const modalPairs = [
    [newCaseBtn, newCaseModal, newCaseOverlay],
    [uploadDocBtn, uploadDocModal, uploadDocOverlay],
    [exportCasesBtn, exportModal, exportOverlay],
    [historyBtn, historyModal, historyOverlay],
  ];

  modalPairs.forEach(([btn, modal, overlay]) => {
    if (btn && modal && overlay) {
      btn.addEventListener("click", () => openModal(modal, overlay));
      overlay.addEventListener("click", () => closeModal(modal, overlay));
    }
  });

  // View-case drawer opens from table row action; still close when clicking outside (overlay).
  if (viewCaseModal && viewCaseOverlay) {
    viewCaseOverlay.addEventListener("click", () => closeModal(viewCaseModal, viewCaseOverlay));
  }

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    const id = btn.getAttribute("data-close-modal");
    btn.addEventListener("click", () => {
      const modal = document.getElementById(id);
      const overlay = document.getElementById(id.replace("Modal", "Overlay"));
      closeModal(modal, overlay);
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeAllModals();
    }
  });

  uploadDocInput &&
    uploadDocInput.addEventListener("change", () => {
      if (!uploadDocInput.files || !uploadDocInput.files.length) return;
      showToast(`Selected ${uploadDocInput.files[0].name}. Ready to upload.`);
    });

  uploadDocSubmit &&
    uploadDocSubmit.addEventListener("click", () => {
      // TODO: wire to API
      closeModal(uploadDocModal, uploadDocOverlay);
      if (uploadDocInput) uploadDocInput.value = "";
    });

  exportSubmit &&
    exportSubmit.addEventListener("click", async () => {
      try {
        const from = document.getElementById("ex_from")?.value || "";
        const to = document.getElementById("ex_to")?.value || "";
        const ct = document.getElementById("ex_caseType")?.value || "all";
        const st = document.getElementById("ex_status")?.value || "all";
        const sc = document.getElementById("ex_sanction")?.value || "all";
        const fmt = document.getElementById("ex_format")?.value || "csv";

        const params = new URLSearchParams();
        if (from) params.set("date_from", from);
        if (to) params.set("date_to", to);
        params.set("case_type", ct);
        params.set("status", st === "open" ? "" : st);
        params.set("sanction_type", sc);

        const res = await fetch(`/employee-cases/list?${params.toString()}`, { headers: { Accept: "application/json" } });
        const payload = await res.json();
        const rows = Array.isArray(payload.data) ? payload.data : [];
        if (!rows.length) {
          showToast("No cases to export.");
          return;
        }
        const header = [
          "Case No",
          "Date Reported",
          "Incident Date",
          "Respondent",
          "Complainant",
          "Case Type",
          "Stage",
          "Decision",
          "Sanction",
          "Status",
        ];
        const csv = [
          header.join(","),
          ...rows.map((r) =>
            [
              r.case_no,
              r.date_reported || "",
              r.incident_date || "",
              (r.respondents || []).join("; "),
              (r.complainants || []).join("; "),
              r.case_type,
              r.status,
              r.decision || "",
              r.sanction_type || "",
              r.status === "closed" ? "Closed" : "Open",
            ]
              .map((v) => `"${String(v ?? "").replace(/"/g, '""')}"`)
              .join(",")
          ),
        ].join("\n");
        const blob = new Blob([csv], { type: "text/csv" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `employee_cases_${new Date().toISOString().slice(0, 10)}.${fmt === "csv" ? "csv" : "csv"}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        showToast("Cases exported.");
        closeModal(exportModal, exportOverlay);
      } catch (err) {
        showToast("Export failed.");
      }
    });

  // --- Employee History drawer ---
  const historyResultsEl    = document.getElementById("historyResults");
  const historyResultsTbody = document.getElementById("historyResultsTbody");
  const histTardiness       = document.getElementById("histTardiness");
  const histSanctions       = document.getElementById("histSanctions");

  setupEmpSearch("hist_search", "hist_empId", "hist_sugg");

  // Reset results when the drawer opens
  historyBtn && historyBtn.addEventListener("click", () => {
    const hSearch = document.getElementById("hist_search");
    const hId = document.getElementById("hist_empId");
    if (hSearch) hSearch.value = "";
    if (hId) hId.value = "";
    if (historyResultsEl) historyResultsEl.setAttribute("hidden", "");
    if (historyResultsTbody) historyResultsTbody.innerHTML = "";
    if (histTardiness) histTardiness.innerHTML = "";
    if (histSanctions) histSanctions.innerHTML = "";
  });

  historySubmit &&
    historySubmit.addEventListener("click", async () => {
      const empId = document.getElementById("hist_empId")?.value;
      if (!empId) { showToast("Please select an employee first."); return; }
      historySubmit.disabled = true;
      historySubmit.textContent = "Searching…";
      try {
        const data = await fetch(`/employee-cases/emp-history/${empId}`, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        }).then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); });

        if (historyResultsEl) historyResultsEl.removeAttribute("hidden");

        // --- Tardiness ---
        if (histTardiness) {
          const t = data.tardiness ?? {};
          const hrs = Math.floor((t.total_minutes ?? 0) / 60);
          const mins = (t.total_minutes ?? 0) % 60;
          histTardiness.innerHTML = `
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">
              <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 18px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:var(--maroon);">${t.days_late ?? 0}</div>
                <div style="font-size:11px;color:var(--muted);">Days Late</div>
              </div>
              <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 18px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:var(--maroon);">${hrs}h ${mins}m</div>
                <div style="font-size:11px;color:var(--muted);">Total Late Time</div>
              </div>
            </div>`;
        }

        // --- Sanctions ---
        if (histSanctions) {
          const sanctions = data.sanctions ?? [];
          if (!sanctions.length) {
            histSanctions.innerHTML = '<p class="muted small" style="margin:4px 0 10px;">No sanctions on record.</p>';
          } else {
            histSanctions.innerHTML = sanctions.map((s) => `
              <div style="border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-bottom:8px;font-size:13px;">
                <div style="font-weight:700;color:var(--maroon);">${(sanctionLabels[s.sanction_type] || s.sanction_type || "—").replace(/_/g," ")}</div>
                <div class="muted small">${s.case_no ? "Case: " + s.case_no : ""}${s.effective_from ? " · " + fmtDate(s.effective_from) : ""}${s.days_suspended ? " · " + s.days_suspended + " day(s) suspension" : ""}</div>
                ${s.remarks ? `<div style="margin-top:4px;color:#374151;">${s.remarks}</div>` : ""}
              </div>`).join("");
          }
        }

        // --- Cases ---
        if (historyResultsTbody) {
          const rows = data.cases ?? [];
          if (!rows.length) {
            historyResultsTbody.innerHTML = '<tr><td colspan="5" class="muted small" style="text-align:center;padding:12px;">No cases found.</td></tr>';
          } else {
            historyResultsTbody.innerHTML = rows.map((r) => `
              <tr style="cursor:pointer;" data-case-id="${r.id}">
                <td style="white-space:nowrap;">${r.case_no ?? "—"}</td>
                <td>${r.title ?? "—"}</td>
                <td style="text-transform:capitalize;">${r.role ?? "—"}</td>
                <td>${badge(r.status)}</td>
                <td style="white-space:nowrap;">${fmtDate(r.date_reported)}</td>
              </tr>`).join("");
            historyResultsTbody.querySelectorAll("tr[data-case-id]").forEach((tr) => {
              tr.addEventListener("click", () => {
                closeModal(historyModal, historyOverlay);
                openCaseDrawer(tr.dataset.caseId);
              });
            });
          }
        }
      } catch (err) {
        showToast("Failed to load history: " + err.message);
      } finally {
        historySubmit.disabled = false;
        historySubmit.textContent = "Search";
      }
    });

  // --- Employee search autocomplete ---
  function setupEmpSearch(textId, hiddenId, suggId) {
    const textEl = document.getElementById(textId);
    const hiddenEl = document.getElementById(hiddenId);
    const suggEl = document.getElementById(suggId);
    if (!textEl || !hiddenEl || !suggEl) return;

    // Move suggestions out of any drawer (which has CSS transform, breaking position:fixed)
    document.body.appendChild(suggEl);

    let debounce = null;
    let activeIdx = -1;

    function positionSugg() {
      const rect = textEl.getBoundingClientRect();
      suggEl.style.top    = (rect.bottom + 2) + "px";
      suggEl.style.left   = rect.left + "px";
      suggEl.style.width  = rect.width + "px";
    }

    function showSugg(items) {
      suggEl.innerHTML = "";
      activeIdx = -1;
      if (!items.length) {
        const li = document.createElement("li");
        li.className = "nc-sugg-empty";
        li.textContent = "No employees found.";
        suggEl.appendChild(li);
      } else {
        items.forEach((emp) => {
          const li = document.createElement("li");
          li.className = "nc-sugg-item";
          li.textContent = emp.label;
          li.addEventListener("mousedown", (e) => {
            e.preventDefault();
            textEl.value = emp.name;
            hiddenEl.value = emp.id;
            hiddenSugg();
          });
          suggEl.appendChild(li);
        });
      }
      positionSugg();
      suggEl.removeAttribute("hidden");
    }

    function hiddenSugg() {
      suggEl.setAttribute("hidden", "");
      activeIdx = -1;
    }

    function updateActive(dir) {
      const items = suggEl.querySelectorAll(".nc-sugg-item");
      if (!items.length) return;
      items[activeIdx]?.classList.remove("is-active");
      activeIdx = (activeIdx + dir + items.length) % items.length;
      items[activeIdx]?.classList.add("is-active");
      items[activeIdx]?.scrollIntoView({ block: "nearest" });
    }

    textEl.addEventListener("input", () => {
      hiddenEl.value = "";
      const q = textEl.value.trim();
      if (q.length < 2) { hiddenSugg(); return; }
      clearTimeout(debounce);
      debounce = setTimeout(async () => {
        try {
          const res = await fetch(`/employees/suggest?q=${encodeURIComponent(q)}`, { headers: { Accept: "application/json" } });
          showSugg(await res.json());
        } catch (_) { hiddenSugg(); }
      }, 250);
    });

    textEl.addEventListener("keydown", (e) => {
      if (suggEl.hasAttribute("hidden")) return;
      if (e.key === "ArrowDown") { e.preventDefault(); updateActive(1); }
      else if (e.key === "ArrowUp") { e.preventDefault(); updateActive(-1); }
      else if (e.key === "Enter") {
        e.preventDefault();
        const active = suggEl.querySelector(".nc-sugg-item.is-active");
        if (active) active.dispatchEvent(new MouseEvent("mousedown"));
      } else if (e.key === "Escape") { hiddenSugg(); }
    });

    textEl.addEventListener("blur", () => setTimeout(hiddenSugg, 150));
  }

  setupEmpSearch("nc_respondentText", "nc_respondentId", "nc_respondentSugg");
  setupEmpSearch("nc_complainantText", "nc_complainantId", "nc_complainantSugg");

  // --- Witness tag input ---
  const witnessWrap = document.getElementById("nc_witnessWrap");
  const witnessInput = document.getElementById("nc_witnessInput");
  const addWitnessBtn = document.getElementById("nc_addWitnessBtn");

  function addWitnessTag(name) {
    const val = name.trim();
    if (!val || !witnessWrap) return;
    const tag = document.createElement("span");
    tag.className = "nc-tag";
    tag.innerHTML = `${val}<button class="nc-tag__remove" type="button" aria-label="Remove">×</button>`;
    tag.querySelector(".nc-tag__remove").addEventListener("click", () => tag.remove());
    witnessWrap.appendChild(tag);
    if (witnessInput) witnessInput.value = "";
  }

  addWitnessBtn &&
    addWitnessBtn.addEventListener("click", () => addWitnessTag(witnessInput?.value || ""));

  witnessInput &&
    witnessInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        addWitnessTag(witnessInput.value);
      }
    });

  // --- Dropzone file selector ---
  const dropzone = document.getElementById("nc_dropzone");
  const fileInput = document.getElementById("nc_files");
  const fileList = document.getElementById("nc_fileList");
  const dropzoneTrigger = document.getElementById("nc_dropzoneTrigger");

  let selectedFiles = [];

  function renderFileList() {
    if (!fileList) return;
    fileList.innerHTML = "";
    selectedFiles.forEach((file, i) => {
      const li = document.createElement("li");
      li.className = "nc-fileItem";
      li.innerHTML = `<span>${file.name}</span><button class="nc-fileItem__remove" type="button" aria-label="Remove">×</button>`;
      li.querySelector(".nc-fileItem__remove").addEventListener("click", () => {
        selectedFiles.splice(i, 1);
        renderFileList();
      });
      fileList.appendChild(li);
    });
  }

  function addFiles(files) {
    Array.from(files).forEach((f) => {
      if (!selectedFiles.find((x) => x.name === f.name && x.size === f.size)) {
        selectedFiles.push(f);
      }
    });
    renderFileList();
  }

  dropzoneTrigger && dropzoneTrigger.addEventListener("click", () => fileInput && fileInput.click());

  fileInput &&
    fileInput.addEventListener("change", () => {
      if (fileInput.files) addFiles(fileInput.files);
      fileInput.value = "";
    });

  if (dropzone) {
    dropzone.addEventListener("dragover", (e) => {
      e.preventDefault();
      dropzone.classList.add("drag-over");
    });
    dropzone.addEventListener("dragleave", () => dropzone.classList.remove("drag-over"));
    dropzone.addEventListener("drop", (e) => {
      e.preventDefault();
      dropzone.classList.remove("drag-over");
      if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
    });
  }

  saveDraftBtn &&
    saveDraftBtn.addEventListener("click", () => {
      closeModal(newCaseModal, newCaseOverlay);
    });

  createCaseBtn &&
    createCaseBtn.addEventListener("click", async () => {
      const respondentId = document.getElementById("nc_respondentId")?.value;
      const complainantId = document.getElementById("nc_complainantId")?.value;
      const incidentDate = document.getElementById("nc_incidentDate")?.value;
      const dateReported = document.getElementById("nc_dateReported")?.value;

      if (!respondentId) {
        showToast("Please select a respondent employee.");
        return;
      }
      if (!incidentDate) {
        showToast("Please enter the incident date.");
        return;
      }
      if (!dateReported) {
        showToast("Please enter the date reported.");
        return;
      }

      const body = {
        incident_date:   incidentDate,
        date_reported:   dateReported,
        location:        document.getElementById("nc_location")?.value || null,
        description:     document.getElementById("nc_summary")?.value?.trim() || null,
        remarks:         document.getElementById("nc_remarks")?.value?.trim() || null,
        respondent_ids:  [parseInt(respondentId, 10)],
        complainant_ids: complainantId ? [parseInt(complainantId, 10)] : [],
      };

      createCaseBtn.disabled = true;
      createCaseBtn.textContent = "Saving…";

      try {
        const res = await fetch("/employee-cases", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
          },
          body: JSON.stringify(body),
        });

        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          const msg = err?.message || Object.values(err?.errors || {}).flat().join(" ") || "Failed to create case.";
          showToast(msg);
          return;
        }

        // Reset form
        document.getElementById("newCaseForm")?.reset();
        document.getElementById("nc_respondentId") && (document.getElementById("nc_respondentId").value = "");
        document.getElementById("nc_complainantId") && (document.getElementById("nc_complainantId").value = "");
        document.getElementById("nc_witnessWrap") && (document.getElementById("nc_witnessWrap").innerHTML = "");
        document.getElementById("nc_fileList") && (document.getElementById("nc_fileList").innerHTML = "");
        selectedFiles = [];

        closeModal(newCaseModal, newCaseOverlay);
        showToast("Case created successfully.");
        load();
      } catch (_) {
        showToast("Network error. Please try again.");
      } finally {
        createCaseBtn.disabled = false;
        createCaseBtn.textContent = "Create Case";
      }
    });
});
