import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  // Chart (Net + Ded)
  const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"];
  const net =  [340, 350, 345, 352, 348, 355]; // K
  const ded =  [280, 285, 282, 288, 286, 289]; // K

  const chart = document.getElementById("chart");
  const labels = document.getElementById("chartLabels");

  if (chart && labels) {
    labels.innerHTML = "";
    months.forEach(m => {
      const x = document.createElement("div");
      x.textContent = m;
      labels.appendChild(x);
    });

    const max = Math.max(...net, ...ded);
    chart.innerHTML = "";

    for (let i = 0; i < months.length; i++) {
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

  // Summary meta line updates
  const monthSel = document.getElementById("month");
  const cutoffSel = document.getElementById("cutoff");
  const deptSel = document.getElementById("dept");
  const placeSel = document.getElementById("place");
  const meta = document.getElementById("summaryMeta");

  function updateMeta(){
    if (!meta) return;
    const cutoffText = cutoffSel?.options[cutoffSel.selectedIndex]?.text ?? "1–15";
    meta.textContent = `Month=${monthSel?.value ?? "Jan 2026"} | Cutoff=${cutoffText} | Dept=${deptSel?.value ?? "All"} | Place=${placeSel?.value ?? "All"}`;
  }

  [monthSel, cutoffSel, deptSel, placeSel].forEach(el => el && el.addEventListener("change", updateMeta));
  updateMeta();

  // Global search filters table rows
  const globalSearch = document.getElementById("globalSearch");
  const tbody = document.getElementById("tableBody");

  function filterTable(){
    if (!globalSearch || !tbody) return;
    const q = globalSearch.value.trim().toLowerCase();
    Array.from(tbody.querySelectorAll("tr")).forEach(tr => {
      tr.style.display = tr.innerText.toLowerCase().includes(q) ? "" : "none";
    });
  }

  globalSearch && globalSearch.addEventListener("input", filterTable);

});
