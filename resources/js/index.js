document.addEventListener("DOMContentLoaded", () => {
  // Clock
  const clockEl = document.getElementById("clock");
  const dateEl = document.getElementById("date");

  function pad(n){ return String(n).padStart(2, "0"); }
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

  // User menu dropdown
  const userMenuBtn = document.getElementById("userMenuBtn");
  const userMenu = document.getElementById("userMenu");

  function closeUserMenu(){
    if (!userMenuBtn || !userMenu) return;
    userMenu.classList.remove("is-open");
    userMenuBtn.setAttribute("aria-expanded", "false");
  }

  function toggleUserMenu(){
    if (!userMenuBtn || !userMenu) return;
    const isOpen = userMenu.classList.contains("is-open");
    userMenu.classList.toggle("is-open", !isOpen);
    userMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }

  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleUserMenu();
    });

    document.addEventListener("click", (e) => {
      if (!userMenu.contains(e.target)) closeUserMenu();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeUserMenu();
    });
  }
});
