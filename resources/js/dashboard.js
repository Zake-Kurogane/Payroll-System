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
  const assignSeg         = document.getElementById("assignSeg");

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
    let from = isA ? Number(cal.cutoff_a_from ?? 11) : Number(cal.cutoff_b_from ?? 26);
    let to   = isA ? Number(cal.cutoff_a_to   ?? 25) : Number(cal.cutoff_b_to   ?? 10);
    if (!Number.isFinite(from) || from <= 0) from = isA ? 11 : 26;
    if (!Number.isFinite(to)   || to   <= 0) to   = isA ? 25 : lastDay;
    return { from, to };
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


  // ── Load assignments from DB → build seg ─────────────────
  let openDropdown = null;
  let openDropdownBtn = null;
  let assignBtns = [];

  function closeAllDropdowns() {
    if (!assignSeg) return;
    assignSeg.querySelectorAll(".seg__dropdown").forEach(d => {
      d.classList.remove("is-open");
      d.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function wireAssignButtons() {
    if (!assignSeg) return;
    assignBtns = Array.from(assignSeg.querySelectorAll(".seg__btn--emp"));

    const contentScroller = document.querySelector(".content");
    let rafId = 0;

    function positionDropdown(btn, dropdown) {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desiredMin = Math.round(rect.width);
      const maxWidth = Math.min(320, Math.max(200, viewportW - 16));
      const dropdownW = Math.max(desiredMin, maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) {
        left = Math.max(8, viewportW - dropdownW - 8);
      }
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${desiredMin}px`;
      dropdown.style.maxWidth = `${maxWidth}px`;
    }

    function refreshOpenDropdownPosition() {
      if (!openDropdown || !openDropdownBtn) return;
      if (rafId) cancelAnimationFrame(rafId);
      rafId = requestAnimationFrame(() => {
        positionDropdown(openDropdownBtn, openDropdown);
      });
    }

    assignBtns.forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const rawAssign = btn.getAttribute("data-assign");
        const group = rawAssign && rawAssign !== "" ? rawAssign : "All";

        const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
        const wasOpen = dropdown && dropdown.style.display === "block";
        const isAlreadyActive = btn.classList.contains("is-active");

        closeAllDropdowns();

        assignBtns.forEach(b => b.classList.remove("is-active"));
        btn.classList.add("is-active");

        if (assignSeg) {
          assignSeg.dataset.assign = group;
          assignSeg.dataset.place = "";
        }

        if (dropdown) {
          if (isAlreadyActive && wasOpen) return;
          positionDropdown(btn, dropdown);
          dropdown.style.display = "block";
          dropdown.classList.add("is-open");
          openDropdown = dropdown;
          openDropdownBtn = btn;
        }
      });
    });

    assignSeg.querySelectorAll(".seg__dropdown-item").forEach(item => {
      item.addEventListener("click", (e) => {
        e.stopPropagation();
        const place = item.getAttribute("data-place") || "";
        const dropdown = item.closest(".seg__dropdown");
        dropdown?.querySelectorAll(".seg__dropdown-item").forEach(i => i.classList.remove("is-active"));
        item.classList.add("is-active");
        if (assignSeg) assignSeg.dataset.place = place;
        closeAllDropdowns();
      });
    });

    document.addEventListener("click", (e) => {
      if (!assignSeg.contains(e.target)) closeAllDropdowns();
    }, { capture: true });

    window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
    window.addEventListener("resize", refreshOpenDropdownPosition);
    contentScroller && contentScroller.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  }

  try {
    const data = await fetch("/employees/filters").then(r => r.json());
    const assignments = Array.isArray(data.assignments) ? data.assignments : [];
    const areaPlaces = (data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places))
      ? data.area_places
      : {};

    if (assignSeg) {
      assignSeg.innerHTML = "";

      const allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.className = "seg__btn seg__btn--emp is-active";
      allBtn.setAttribute("data-assign", "");
      allBtn.textContent = "All";
      assignSeg.appendChild(allBtn);

      function mergedPlacesForMultiSite() {
        const seen = new Set();
        const out = [];
        Object.values(areaPlaces || {}).forEach((arr) => {
          if (!Array.isArray(arr)) return;
          arr.forEach((p) => {
            const v = String(p || "").trim();
            if (!v) return;
            const k = v.toLowerCase();
            if (seen.has(k)) return;
            seen.add(k);
            out.push(v);
          });
        });
        return out;
      }

      assignments.forEach((label) => {
        const places = label === "Multi-Site (Roving)"
          ? mergedPlacesForMultiSite()
          : (Array.isArray(areaPlaces[label]) ? areaPlaces[label] : []);
        const wrap = document.createElement("div");
        wrap.className = "seg__btn-wrap";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "seg__btn seg__btn--emp";
        btn.setAttribute("data-assign", label);
        btn.textContent = label;
        if (places.length) {
          const chev = document.createElement("span");
          chev.className = "seg__chevron";
          chev.textContent = "▾";
          btn.appendChild(chev);
        }
        wrap.appendChild(btn);

        const dropdown = document.createElement("div");
        dropdown.className = "seg__dropdown";
        dropdown.setAttribute("data-group", label);
        dropdown.style.display = "none";
        places.forEach((p) => {
          const item = document.createElement("button");
          item.type = "button";
          item.className = "seg__dropdown-item";
          item.setAttribute("data-place", p);
          item.textContent = p;
          dropdown.appendChild(item);
        });
        wrap.appendChild(dropdown);

        assignSeg.appendChild(wrap);
      });
    }
  } catch {
    // Fallback: still show assignment options even if filters endpoint fails.
    if (assignSeg) {
      const assignments = ["Davao", "Tagum", "Field", "Multi-Site (Roving)"];
      assignSeg.innerHTML = "";

      const allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.className = "seg__btn seg__btn--emp is-active";
      allBtn.setAttribute("data-assign", "");
      allBtn.textContent = "All";
      assignSeg.appendChild(allBtn);

      assignments.forEach((label) => {
        const wrap = document.createElement("div");
        wrap.className = "seg__btn-wrap";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "seg__btn seg__btn--emp";
        btn.setAttribute("data-assign", label);
        btn.textContent = label;
        wrap.appendChild(btn);

        assignSeg.appendChild(wrap);
      });
    }
  }
  wireAssignButtons();

  // ── Default month to current ─────────────────────────────
  if (cutoffMonthInput && !cutoffMonthInput.value) {
    const now = new Date();
    cutoffMonthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  }
  syncCutoffOptions();

  cutoffMonthInput?.addEventListener("change", () => { syncCutoffOptions(); });

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
});
