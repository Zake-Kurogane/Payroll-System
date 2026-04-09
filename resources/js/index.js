import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", async () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  // ── DOM refs ────────────────────────────────────────────
  const cutoffMonthInput  = document.getElementById("cutoffMonth");
  const cutoffSelect      = document.getElementById("cutoffSelect");
  const cutoffRangeLabel  = document.getElementById("cutoffRangeLabel");
  const assignmentSeg     = document.getElementById("assignmentSeg");

  // ── Helpers ─────────────────────────────────────────────
  function parseYM(val) {
    if (!val) return null;
    const [y, m] = val.split("-").map(Number);
    return Number.isFinite(y) && Number.isFinite(m) ? { y, m } : null;
  }

  function resolveCutoffDays(year, month, cutoffType, cal) {
    cal = cal || {};
    const lastDay = new Date(year, month, 0).getDate();
    const isA = cutoffType === "A";
    // Canonical mapping: A = 26-10, B = 11-25
    let from = isA ? Number(cal.cutoff_b_from ?? 26) : Number(cal.cutoff_a_from ?? 11);
    let to   = isA ? Number(cal.cutoff_b_to   ?? 10) : Number(cal.cutoff_a_to   ?? 25);
    if (!Number.isFinite(from) || from <= 0) from = isA ? 26 : 11;
    if (!Number.isFinite(to)   || to   <= 0) to   = isA ? 10 : lastDay;
    return { from, to };
  }

  function getCutoffRange(year, month, cutoffType, cal) {
    const { from, to } = resolveCutoffDays(year, month, cutoffType, cal);
    const start = new Date(year, month - 1, from);
    const end   = from > to ? new Date(year, month, to) : new Date(year, month - 1, to);
    return { start, end };
  }

  function formatRangeLabel(range) {
    const opts = { month: "short", day: "numeric", year: "numeric" };
    return `${range.start.toLocaleDateString("en-PH", opts)} – ${range.end.toLocaleDateString("en-PH", opts)}`;
  }

  // ── Load payroll calendar from DB ────────────────────────
  let payrollCalendar = null;
  try {
    payrollCalendar = await fetch("/settings/payroll-calendar").then(r => r.json());
  } catch { payrollCalendar = null; }

  // ── Populate cutoff select ───────────────────────────────
  function syncCutoffOptions() {
    if (!cutoffSelect) return;
    const ym = parseYM(cutoffMonthInput?.value);
    const prev = cutoffSelect.value;

    cutoffSelect.innerHTML = "";

    const all = document.createElement("option");
    all.value = "all"; all.textContent = "All";
    cutoffSelect.appendChild(all);

    if (ym) {
      const { from: aFrom, to: aTo } = resolveCutoffDays(ym.y, ym.m, "A", payrollCalendar);
      const { from: bFrom, to: bTo } = resolveCutoffDays(ym.y, ym.m, "B", payrollCalendar);

      const optA = document.createElement("option");
      optA.value = "A"; optA.textContent = `${aFrom}–${aTo}`;
      cutoffSelect.appendChild(optA);

      const optB = document.createElement("option");
      optB.value = "B"; optB.textContent = `${bFrom}–${bTo}`;
      cutoffSelect.appendChild(optB);
    }

    if ([...cutoffSelect.options].some(o => o.value === prev)) {
      cutoffSelect.value = prev;
    }
  }

  // ── Update cutoff range label ────────────────────────────
  function updateRangeLabel() {
    if (!cutoffRangeLabel) return;
    const ym = parseYM(cutoffMonthInput?.value);
    const ct = cutoffSelect?.value;
    if (!ym || !ct || ct === "all") { cutoffRangeLabel.textContent = "—"; return; }
    const range = getCutoffRange(ym.y, ym.m, ct, payrollCalendar);
    cutoffRangeLabel.textContent = formatRangeLabel(range);
  }

  // ── Load assignments from DB → build seg ─────────────────
  let openDropdown = null, openDropdownBtn = null;

  function closeAllDropdowns() {
    document.querySelectorAll(".seg__dropdown.is-open").forEach(d => {
      d.classList.remove("is-open");
      d.style.display = "none";
    });
    openDropdown = null; openDropdownBtn = null;
  }

  document.addEventListener("click", closeAllDropdowns);

  function positionDropdown(btn, dropdown) {
    const rect = btn.getBoundingClientRect();
    const vw = window.innerWidth;
    const w = Math.max(Math.round(rect.width), 200);
    let left = Math.round(rect.left);
    if (left + w > vw - 8) left = Math.max(8, vw - w - 8);
    dropdown.style.left = `${left}px`;
    dropdown.style.top  = `${Math.round(rect.bottom + 8)}px`;
    dropdown.style.minWidth = `${w}px`;
  }

  try {
    const data = await fetch("/employees/filters").then(r => r.json());
    const assignments   = data.assignments  || [];
    const areaGrouped   = data.area_places  || {};

    if (assignmentSeg) {
      assignmentSeg.innerHTML = "";

      // All button
      const allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.className = "seg__btn is-active";
      allBtn.dataset.assign = "All";
      allBtn.textContent = "All";
      assignmentSeg.appendChild(allBtn);

      // One button per assignment, with sub-place dropdown
      assignments.forEach(a => {
        const places = Array.isArray(areaGrouped[a.name]) ? areaGrouped[a.name] : [];
        const wrap = document.createElement("div");
        wrap.className = "seg__btn-wrap";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "seg__btn";
        btn.dataset.assign = a.name;
        btn.textContent = a.name;
        if (places.length) {
          const chev = document.createElement("span");
          chev.className = "seg__chevron";
          chev.textContent = "▾";
          btn.appendChild(chev);
        }
        wrap.appendChild(btn);

        if (places.length) {
          const dd = document.createElement("div");
          dd.className = "seg__dropdown";
          dd.dataset.group = a.name;
          dd.style.display = "none";
          dd.innerHTML = places.map(p =>
            `<button type="button" class="seg__dropdown-item" data-place="${p}">${p}</button>`
          ).join("");
          wrap.appendChild(dd);

          dd.querySelectorAll(".seg__dropdown-item").forEach(item => {
            item.addEventListener("click", e => {
              e.stopPropagation();
              dd.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
              item.classList.add("is-active");
              closeAllDropdowns();
            });
          });
        }

        assignmentSeg.appendChild(wrap);
      });

      // Button click logic
      assignmentSeg.querySelectorAll(".seg__btn").forEach(btn => {
        btn.addEventListener("click", e => {
          e.stopPropagation();
          const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
          const wasOpen  = dropdown && dropdown.style.display === "block";
          const wasActive = btn.classList.contains("is-active");
          closeAllDropdowns();
          assignmentSeg.querySelectorAll(".seg__btn").forEach(b => b.classList.remove("is-active"));
          btn.classList.add("is-active");
          if (dropdown) {
            if (wasActive && wasOpen) return;
            positionDropdown(btn, dropdown);
            dropdown.style.display = "block";
            dropdown.classList.add("is-open");
            openDropdown = dropdown; openDropdownBtn = btn;
          }
        });
      });
    }
  } catch { /* silent */ }

  // ── Default month to current ─────────────────────────────
  if (cutoffMonthInput && !cutoffMonthInput.value) {
    const now = new Date();
    cutoffMonthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  }
  syncCutoffOptions();
  updateRangeLabel();

  cutoffMonthInput?.addEventListener("change", () => { syncCutoffOptions(); updateRangeLabel(); });
  cutoffSelect?.addEventListener("change", updateRangeLabel);

  // ── Chart (static placeholder) ───────────────────────────
  const chartMonths = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
  const net = [340, 350, 345, 352, 348, 355];
  const ded = [280, 285, 282, 288, 286, 289];

  const chart = document.getElementById("chart");
  const labelsEl = document.getElementById("chartLabels");

  if (chart && labelsEl) {
    labelsEl.innerHTML = "";
    chartMonths.forEach(m => {
      const x = document.createElement("div");
      x.textContent = m;
      labelsEl.appendChild(x);
    });

    const max = Math.max(...net, ...ded);
    chart.innerHTML = "";

    for (let i = 0; i < chartMonths.length; i++) {
      const wrap = document.createElement("div");
      wrap.className = "barWrap";

      const bNet = document.createElement("div");
      bNet.className = "barNet";
      bNet.style.height = `${(net[i] / max) * 100}%`;

      const bDed = document.createElement("div");
      bDed.className = "barDed";
      bDed.style.height = `${(ded[i] / max) * 100}%`;

      wrap.appendChild(bNet);
      wrap.appendChild(bDed);
      chart.appendChild(wrap);
    }
  }

  // ── Global search ────────────────────────────────────────
  const globalSearch = document.getElementById("globalSearch");
  const tbody = document.getElementById("tableBody");

  globalSearch?.addEventListener("input", () => {
    if (!tbody) return;
    const q = globalSearch.value.trim().toLowerCase();
    Array.from(tbody.querySelectorAll("tr")).forEach(tr => {
      tr.style.display = tr.innerText.toLowerCase().includes(q) ? "" : "none";
    });
  });
});
