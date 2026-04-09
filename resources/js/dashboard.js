import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", async () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  const cutoffMonthInput = document.getElementById("cutoffMonth");
  const cutoffSelect = document.getElementById("cutoffSelect");
  const assignSeg = document.getElementById("assignSeg");

  const kpiEmployees = document.getElementById("kpiEmployees");
  const kpiGross = document.getElementById("kpiGross");
  const kpiDed = document.getElementById("kpiDed");
  const kpiNet = document.getElementById("kpiNet");
  const kpiUnclaimed = document.getElementById("kpiUnclaimed");
  const kpiEmployeesCard = kpiEmployees?.closest(".kpi");
  const kpiGrossCard = kpiGross?.closest(".kpi");
  const kpiDedCard = kpiDed?.closest(".kpi");
  const kpiNetCard = kpiNet?.closest(".kpi");
  const kpiUnclaimedCard = kpiUnclaimed?.closest(".kpi");
  const recentActivityList = document.getElementById("recentActivityList");
  const kpiGrossLink = document.getElementById("kpiGrossLink");
  const kpiDedLink = document.getElementById("kpiDedLink");
  const kpiNetLink = document.getElementById("kpiNetLink");
  const chart = document.getElementById("chart");
  const chartLabels = document.getElementById("chartLabels");
  const yAxis = document.querySelector(".yaxis");
  const trendByCutoffBtn = document.getElementById("trendByCutoffBtn");
  const trendMonthlyBtn = document.getElementById("trendMonthlyBtn");
  const unclaimedLink = document.querySelector('a[aria-label="View payslip claims"]');

  const peso = (value) =>
    `\u20B1 ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  const compactNumber = (value) => {
    const n = Number(value || 0);
    if (n >= 1_000_000) return `${Math.round((n / 1_000_000) * 10) / 10}M`;
    if (n >= 1_000) return `${Math.round((n / 1_000) * 10) / 10}K`;
    return String(Math.round(n));
  };

  const compactCurrency = (value) => `\u20B1 ${compactNumber(value)}`;
  const numberText = (value) => Number(value || 0).toLocaleString();

  function parseYM(value) {
    if (!value) return null;
    const [y, m] = String(value).split("-").map(Number);
    if (!Number.isFinite(y) || !Number.isFinite(m)) return null;
    return { y, m };
  }

  function resolveCutoffDays(year, month, cutoff, calendar) {
    const cal = calendar || {};
    const lastDay = new Date(year, month, 0).getDate();
    const cutoffA = cutoff === "11-25";
    let from = cutoffA ? Number(cal.cutoff_a_from ?? 11) : Number(cal.cutoff_b_from ?? 26);
    let to = cutoffA ? Number(cal.cutoff_a_to ?? 25) : Number(cal.cutoff_b_to ?? 10);
    if (!Number.isFinite(from) || from <= 0) from = cutoffA ? 11 : 26;
    if (!Number.isFinite(to) || to <= 0) to = cutoffA ? 25 : lastDay;
    return { from, to };
  }

  async function apiFetch(url) {
    const res = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
    if (!res.ok) throw new Error("Request failed.");
    return res.json();
  }

  let payrollCalendar = null;
  try {
    payrollCalendar = await apiFetch("/settings/payroll-calendar");
  } catch {
    payrollCalendar = null;
  }

  function syncCutoffOptions() {
    if (!cutoffSelect) return;

    const ym = parseYM(cutoffMonthInput?.value || "");
    const previous = cutoffSelect.value || "all";
    cutoffSelect.innerHTML = "";

    const optAll = document.createElement("option");
    optAll.value = "all";
    optAll.textContent = "All";
    cutoffSelect.appendChild(optAll);

    if (ym) {
      const a = resolveCutoffDays(ym.y, ym.m, "11-25", payrollCalendar);
      const b = resolveCutoffDays(ym.y, ym.m, "26-10", payrollCalendar);

      const optA = document.createElement("option");
      optA.value = "11-25";
      optA.textContent = `${a.from}-${a.to}`;
      cutoffSelect.appendChild(optA);

      const optB = document.createElement("option");
      optB.value = "26-10";
      optB.textContent = `${b.from}-${b.to}`;
      cutoffSelect.appendChild(optB);
    }

    const canKeep = Array.from(cutoffSelect.options).some((o) => o.value === previous);
    cutoffSelect.value = canKeep ? previous : "all";
  }

  let openDropdown = null;
  let openDropdownBtn = null;
  let assignBtns = [];

  function closeAllDropdowns() {
    if (!assignSeg) return;
    assignSeg.querySelectorAll(".seg__dropdown").forEach((d) => {
      d.classList.remove("is-open");
      d.style.display = "none";
    });
    openDropdown = null;
    openDropdownBtn = null;
  }

  function getCurrentFilters() {
    const assignment = (assignSeg?.dataset.assign || "All").trim() || "All";
    const place = (assignSeg?.dataset.place || "").trim();
    return {
      month: cutoffMonthInput?.value || "",
      cutoff: cutoffSelect?.value || "all",
      assignment,
      place,
    };
  }

  let lastTrendData = null;
  let trendMode = "by_cutoff";

  function setTooltip(el, text) {
    if (!el) return;
    if (!text) {
      el.removeAttribute("title");
      return;
    }
    el.setAttribute("title", text);
  }

  function buildBreakdownTooltip(breakdown, metric, label, isMoney = false) {
    if (!Array.isArray(breakdown) || breakdown.length === 0) return "";
    const lines = breakdown.map((item) => {
      const value = Number(item?.[metric] || 0);
      const valueText = isMoney ? peso(value) : numberText(value);
      return `${item.assignment}: ${valueText}`;
    });
    return `${label} by assignment\n${lines.join("\n")}`;
  }

  function setTrendMode(nextMode) {
    trendMode = nextMode === "monthly_total" ? "monthly_total" : "by_cutoff";
    trendByCutoffBtn?.classList.toggle("is-active", trendMode === "by_cutoff");
    trendMonthlyBtn?.classList.toggle("is-active", trendMode === "monthly_total");
    if (lastTrendData) renderTrend(lastTrendData);
  }

  function formatActivityDate(value) {
    if (!value) return "";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return "";
    return d.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    });
  }

  function renderRecentActivity(list) {
    if (!recentActivityList) return;
    const items = Array.isArray(list) ? list : [];
    if (!items.length) {
      recentActivityList.innerHTML = `
        <div class="todo__item">
          <div class="todo__name">No recent activity.</div>
        </div>
      `;
      return;
    }

    recentActivityList.innerHTML = items.map((a) => {
      const when = formatActivityDate(a?.occurred_at);
      const actor = `${a?.actor_name || "Unknown"}${a?.actor_role ? ` (${a.actor_role})` : ""}`;
      const desc = a?.description || "";
      return `
        <div class="todo__item">
          <div class="todo__name">${a?.title || "Activity"}</div>
          <div class="todo__meta">${actor}${when ? ` • ${when}` : ""}</div>
          ${desc ? `<div class="todo__sub">${desc}</div>` : ""}
        </div>
      `;
    }).join("");
  }

  function renderTrend(trend) {
    if (!chart || !chartLabels) return;

    const months = Array.isArray(trend?.months) ? trend.months : [];
    const hasMonths = months.length > 0;
    const labels = hasMonths
      ? months.map((m) => m?.label || "")
      : (Array.isArray(trend?.labels) ? trend.labels : []);
    lastTrendData = trend || {};

    const scaleValues = [];
    if (hasMonths) {
      if (trendMode === "monthly_total") {
        months.forEach((m) => {
          scaleValues.push(Number(m?.total?.net || 0));
          scaleValues.push(Number(m?.total?.deductions || 0));
        });
      } else {
        months.forEach((m) => {
          scaleValues.push(Number(m?.cutoffs?.["26-10"]?.net || 0));
          scaleValues.push(Number(m?.cutoffs?.["26-10"]?.deductions || 0));
          scaleValues.push(Number(m?.cutoffs?.["11-25"]?.net || 0));
          scaleValues.push(Number(m?.cutoffs?.["11-25"]?.deductions || 0));
        });
      }
    } else {
      const net = Array.isArray(trend?.net) ? trend.net.map((v) => Number(v || 0)) : [];
      const deductions = Array.isArray(trend?.deductions) ? trend.deductions.map((v) => Number(v || 0)) : [];
      scaleValues.push(...net, ...deductions);
    }

    const rawMax = Math.max(1, ...scaleValues);

    chart.innerHTML = "";
    chartLabels.innerHTML = "";
    chart.classList.remove("chart--line");
    chart.classList.add("chart--bar");

    labels.forEach((label) => {
      const item = document.createElement("div");
      item.textContent = label || "";
      chartLabels.appendChild(item);
    });

    if (!labels.length) return;

    // Build a 4-label axis with 3 equal intervals:
    // [max, 2/3 max, 1/3 max, 0] so the bottom is always zero.
    const roughStep = Math.max(1, rawMax / 3);
    const pow = 10 ** Math.floor(Math.log10(roughStep));
    const stepBase = [1, 2, 2.5, 5, 10].find((m) => roughStep <= m * pow) || 10;
    const step = stepBase * pow;
    const scaleMax = Math.max(step * 3, 1);

    const width = Math.max(320, chart.clientWidth || 320);
    const height = Math.max(180, chart.clientHeight || 180);
    const padX = 18;
    const padTop = 12;
    const padBottom = 10;
    const usableH = Math.max(1, height - padTop - padBottom);
    const usableW = Math.max(1, width - padX * 2);

    const svgNS = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(svgNS, "svg");
    svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
    svg.setAttribute("class", "trendSvg");
    svg.setAttribute("aria-label", "Payroll trend bar graph");

    const tooltip = document.createElement("div");
    tooltip.className = "trendTooltip";
    chart.appendChild(tooltip);

    const groupW = usableW / labels.length;
    const gap = Math.min(10, groupW * 0.14);
    const barW = Math.max(8, Math.min(24, (groupW - gap - 10) / 2));
    const toY = (v) => padTop + usableH - ((Number(v || 0) / scaleMax) * usableH);

    const showTip = (evt, month, series, value) => {
      tooltip.innerHTML = `${month}<br>${series}: ${peso(value)}`;
      tooltip.style.display = "block";
      const rect = chart.getBoundingClientRect();
      const x = evt.clientX - rect.left;
      const y = evt.clientY - rect.top;

      const tooltipRect = tooltip.getBoundingClientRect();
      const margin = 8;

      let left = Math.min(rect.width - margin, Math.max(margin, x));
      let top = y - 10;
      const wouldOverflowTop = top - tooltipRect.height < margin;
      if (wouldOverflowTop) {
        top = y + 18;
      }
      const maxTop = rect.height - margin;
      top = Math.min(maxTop, Math.max(margin, top));

      if (left + (tooltipRect.width / 2) > rect.width - margin) {
        left = rect.width - margin - (tooltipRect.width / 2);
      }
      if (left - (tooltipRect.width / 2) < margin) {
        left = margin + (tooltipRect.width / 2);
      }

      tooltip.style.left = `${left}px`;
      tooltip.style.top = `${top}px`;
    };
    const hideTip = () => { tooltip.style.display = "none"; };

    labels.forEach((month, idx) => {
      const center = padX + (groupW * idx) + (groupW / 2);

      if (!hasMonths || trendMode === "monthly_total") {
        const netVal = hasMonths
          ? Number(months[idx]?.total?.net || 0)
          : Number((trend?.net || [])[idx] || 0);
        const dedVal = hasMonths
          ? Number(months[idx]?.total?.deductions || 0)
          : Number((trend?.deductions || [])[idx] || 0);

        const netY = toY(netVal);
        const netH = Math.max(0, (padTop + usableH) - netY);
        const netRect = document.createElementNS(svgNS, "rect");
        netRect.setAttribute("x", String(center - gap / 2 - barW));
        netRect.setAttribute("y", String(netY));
        netRect.setAttribute("width", String(barW));
        netRect.setAttribute("height", String(netH));
        netRect.setAttribute("rx", "4");
        netRect.setAttribute("class", "trendBar trendBar--net");
        netRect.addEventListener("mousemove", (evt) => showTip(evt, month, "Net Pay", netVal));
        netRect.addEventListener("mouseleave", hideTip);
        svg.appendChild(netRect);

        const dedY = toY(dedVal);
        const dedH = Math.max(0, (padTop + usableH) - dedY);
        const dedRect = document.createElementNS(svgNS, "rect");
        dedRect.setAttribute("x", String(center + gap / 2));
        dedRect.setAttribute("y", String(dedY));
        dedRect.setAttribute("width", String(barW));
        dedRect.setAttribute("height", String(dedH));
        dedRect.setAttribute("rx", "4");
        dedRect.setAttribute("class", "trendBar trendBar--ded");
        dedRect.addEventListener("mousemove", (evt) => showTip(evt, month, "Deduction", dedVal));
        dedRect.addEventListener("mouseleave", hideTip);
        svg.appendChild(dedRect);
        return;
      }

      const monthData = months[idx] || {};
      const c1 = monthData?.cutoffs?.["26-10"] || {};
      const c2 = monthData?.cutoffs?.["11-25"] || {};

      const clusterGap = Math.min(10, groupW * 0.12);
      const innerGap = Math.min(4, groupW * 0.04);
      const barW2 = Math.max(6, Math.min(16, (groupW - clusterGap - innerGap * 3) / 4));
      const leftStart = center - ((barW2 * 4 + innerGap * 3 + clusterGap) / 2);
      const c1NetX = leftStart;
      const c1DedX = c1NetX + barW2 + innerGap;
      const c2NetX = c1DedX + barW2 + clusterGap;
      const c2DedX = c2NetX + barW2 + innerGap;

      const makeTip = (cutoffObj, series, value) => {
        const label = cutoffObj?.label || "Cutoff";
        const range = cutoffObj?.range_label ? ` • ${cutoffObj.range_label}` : "";
        const payday = cutoffObj?.payday_label ? ` • Payday ${cutoffObj.payday_label}` : "";
        return `${month} • ${label}${range}${payday}<br>${series}: ${peso(value)}`;
      };

      const draw = (x, value, cssClass, tipHtml) => {
        const y = toY(value);
        const h = Math.max(0, (padTop + usableH) - y);
        const rect = document.createElementNS(svgNS, "rect");
        rect.setAttribute("x", String(x));
        rect.setAttribute("y", String(y));
        rect.setAttribute("width", String(barW2));
        rect.setAttribute("height", String(h));
        rect.setAttribute("rx", "3");
        rect.setAttribute("class", cssClass);
        rect.addEventListener("mousemove", (evt) => {
          tooltip.innerHTML = tipHtml;
          tooltip.style.display = "block";
          const rectBox = chart.getBoundingClientRect();
          const xPos = evt.clientX - rectBox.left;
          const yPos = evt.clientY - rectBox.top;
          tooltip.style.left = `${xPos}px`;
          tooltip.style.top = `${Math.max(8, yPos - 10)}px`;
        });
        rect.addEventListener("mouseleave", hideTip);
        svg.appendChild(rect);
      };

      draw(c1NetX, Number(c1?.net || 0), "trendBar trendBar--net", makeTip(c1, "Net Pay", Number(c1?.net || 0)));
      draw(c1DedX, Number(c1?.deductions || 0), "trendBar trendBar--ded", makeTip(c1, "Deduction", Number(c1?.deductions || 0)));
      draw(c2NetX, Number(c2?.net || 0), "trendBar trendBar--net trendBar--cutoff2", makeTip(c2, "Net Pay", Number(c2?.net || 0)));
      draw(c2DedX, Number(c2?.deductions || 0), "trendBar trendBar--ded trendBar--cutoff2", makeTip(c2, "Deduction", Number(c2?.deductions || 0)));
    });

    chart.appendChild(svg);

    if (yAxis) {
      const ticks = Array.from(yAxis.querySelectorAll("span"));
      const values = [scaleMax, scaleMax * (2 / 3), scaleMax * (1 / 3), 0];
      ticks.forEach((tick, idx) => {
        tick.textContent = compactCurrency(values[idx] || 0);
      });
    }
  }

  function applySummary(summary) {
    const k = summary?.kpis || {};

    if (kpiEmployees) kpiEmployees.textContent = Number(k.employees || 0).toLocaleString();
    if (kpiGross) kpiGross.textContent = peso(k.gross || 0);
    if (kpiDed) kpiDed.textContent = peso(k.deductions || 0);
    if (kpiNet) kpiNet.textContent = peso(k.net || 0);
    if (kpiUnclaimed) kpiUnclaimed.textContent = Number(k.unclaimed_payslips || 0).toLocaleString();

    renderRecentActivity(summary?.recent_activity || []);

    renderTrend(summary?.trend || {});

    const breakdown = Array.isArray(k.assignment_breakdown) ? k.assignment_breakdown : [];
    setTooltip(kpiEmployeesCard, buildBreakdownTooltip(breakdown, "employees", "Employees"));
    setTooltip(kpiGrossCard, buildBreakdownTooltip(breakdown, "gross", "Gross", true));
    setTooltip(kpiDedCard, buildBreakdownTooltip(breakdown, "deductions", "Deductions", true));
    setTooltip(kpiNetCard, buildBreakdownTooltip(breakdown, "net", "Net Pay", true));
    setTooltip(kpiUnclaimedCard, buildBreakdownTooltip(breakdown, "unclaimed_payslips", "Unclaimed Payslips"));

    const f = summary?.filters || {};
    const reportQs = new URLSearchParams();
    if (f.month) reportQs.set("month", f.month);
    if (f.cutoff) reportQs.set("cutoff", f.cutoff);
    if (f.assignment) reportQs.set("assignment", f.assignment);
    if (f.place) reportQs.set("place", f.place);
    if (summary?.latest_run?.id) reportQs.set("run_id", String(summary.latest_run.id));

    const reportUrl = `/report${reportQs.toString() ? `?${reportQs.toString()}` : ""}`;
    if (kpiGrossLink) kpiGrossLink.href = reportUrl;
    if (kpiDedLink) kpiDedLink.href = reportUrl;
    if (kpiNetLink) kpiNetLink.href = reportUrl;

    if (unclaimedLink) {
      const runId = summary?.latest_run?.id;
      unclaimedLink.href = runId ? `/payslip-claims?run_id=${encodeURIComponent(runId)}` : "/payslip-claims";
    }
  }

  let activeRequest = 0;
  async function refreshDashboard({ adoptServerFilters = false } = {}) {
    const requestId = ++activeRequest;
    const filters = getCurrentFilters();
    const qs = new URLSearchParams();
    if (filters.month) qs.set("month", filters.month);
    if (filters.cutoff) qs.set("cutoff", filters.cutoff);
    if (filters.assignment && filters.assignment !== "All") qs.set("assignment", filters.assignment);
    if (filters.place) qs.set("place", filters.place);

    try {
      const summary = await apiFetch(`/dashboard/summary?${qs.toString()}`);
      if (requestId !== activeRequest) return;

      if (adoptServerFilters && summary?.filters) {
        if (cutoffMonthInput && summary.filters.month) {
          cutoffMonthInput.value = summary.filters.month;
        }
        syncCutoffOptions();
        if (cutoffSelect && summary.filters.cutoff) {
          const wanted = summary.filters.cutoff;
          if (Array.from(cutoffSelect.options).some((o) => o.value === wanted)) {
            cutoffSelect.value = wanted;
          }
        }
      }

      applySummary(summary);
    } catch {
      applySummary({
        kpis: { employees: 0, gross: 0, deductions: 0, net: 0, unclaimed_payslips: 0 },
        recent_activity: [],
        trend: { labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"], net: [0, 0, 0, 0, 0, 0], deductions: [0, 0, 0, 0, 0, 0] },
      });
    }
  }

  function wireAssignButtons(onFilterChange) {
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
      if (left + dropdownW > viewportW - 8) left = Math.max(8, viewportW - dropdownW - 8);
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${desiredMin}px`;
      dropdown.style.maxWidth = `${maxWidth}px`;
    }

    function refreshOpenDropdownPosition() {
      if (!openDropdown || !openDropdownBtn) return;
      if (rafId) cancelAnimationFrame(rafId);
      rafId = requestAnimationFrame(() => positionDropdown(openDropdownBtn, openDropdown));
    }

    assignBtns.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const rawAssign = btn.getAttribute("data-assign");
        const group = rawAssign && rawAssign !== "" ? rawAssign : "All";

        const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
        const wasOpen = dropdown && dropdown.style.display === "block";
        const alreadyActive = btn.classList.contains("is-active");

        closeAllDropdowns();
        assignBtns.forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");

        assignSeg.dataset.assign = group;
        assignSeg.dataset.place = "";

        onFilterChange();

        if (dropdown) {
          if (alreadyActive && wasOpen) return;
          positionDropdown(btn, dropdown);
          dropdown.style.display = "block";
          dropdown.classList.add("is-open");
          openDropdown = dropdown;
          openDropdownBtn = btn;
        }
      });
    });

    assignSeg.querySelectorAll(".seg__dropdown-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        e.stopPropagation();
        const place = item.getAttribute("data-place") || "";
        const dropdown = item.closest(".seg__dropdown");
        dropdown?.querySelectorAll(".seg__dropdown-item").forEach((i) => i.classList.remove("is-active"));
        item.classList.add("is-active");
        assignSeg.dataset.place = place;
        closeAllDropdowns();
        onFilterChange();
      });
    });

    document.addEventListener("click", (e) => {
      if (!assignSeg.contains(e.target)) closeAllDropdowns();
    }, { capture: true });

    window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
    window.addEventListener("resize", refreshOpenDropdownPosition);
    contentScroller?.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  }

  function buildFallbackAssignments() {
    if (!assignSeg) return;
    const assignments = ["Davao", "Tagum", "Field"];
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

  async function buildAssignmentFilters() {
    if (!assignSeg) return;
    try {
      const data = await apiFetch("/employees/filters");
      const assignments = Array.isArray(data.assignments) ? data.assignments : [];
      const areaPlaces = data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places)
        ? data.area_places
        : {};

      assignSeg.innerHTML = "";

      const allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.className = "seg__btn seg__btn--emp is-active";
      allBtn.setAttribute("data-assign", "");
      allBtn.textContent = "All";
      assignSeg.appendChild(allBtn);

      assignments.forEach((label) => {
        const places = Array.isArray(areaPlaces[label]) ? areaPlaces[label] : [];
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

        if (places.length) {
          const dropdown = document.createElement("div");
          dropdown.className = "seg__dropdown";
          dropdown.setAttribute("data-group", label);
          dropdown.style.display = "none";
          places.forEach((place) => {
            const item = document.createElement("button");
            item.type = "button";
            item.className = "seg__dropdown-item";
            item.setAttribute("data-place", place);
            item.textContent = place;
            dropdown.appendChild(item);
          });
          wrap.appendChild(dropdown);
        }

        assignSeg.appendChild(wrap);
      });
    } catch {
      buildFallbackAssignments();
    }
  }

  if (assignSeg) {
    assignSeg.dataset.assign = "All";
    assignSeg.dataset.place = "";
  }

  syncCutoffOptions();
  await buildAssignmentFilters();

  trendByCutoffBtn?.addEventListener("click", () => setTrendMode("by_cutoff"));
  trendMonthlyBtn?.addEventListener("click", () => setTrendMode("monthly_total"));

  const onFilterChange = () => { refreshDashboard(); };
  wireAssignButtons(onFilterChange);

  cutoffMonthInput?.addEventListener("change", () => {
    syncCutoffOptions();
    refreshDashboard();
  });

  cutoffSelect?.addEventListener("change", () => {
    refreshDashboard();
  });

  window.addEventListener("resize", () => {
    if (lastTrendData) renderTrend(lastTrendData);
  });

  await refreshDashboard({ adoptServerFilters: true });
});
