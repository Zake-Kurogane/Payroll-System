import { esc } from "./utils";
import { createDrawer } from "./drawer";

export function initCashAdvance(toast) {
  const caTbody = document.getElementById("caTbody");
  const newCaBtn = document.getElementById("newCaBtn");

  const caDrawer = document.getElementById("caDrawer");
  const caDrawerOverlay = document.getElementById("caDrawerOverlay");
  const closeCaDrawer = document.getElementById("closeCaDrawer");
  const cancelCaBtn = document.getElementById("cancelCaBtn");
  const caForm = document.getElementById("caForm");

  if (caDrawer && caDrawer.parentElement !== document.body) {
    document.body.appendChild(caDrawer);
  }

  const drawer = createDrawer(caDrawer, caDrawerOverlay, [closeCaDrawer, cancelCaBtn]);

  let cashAdvances = [
    { id: 1, employee: "Maria Santos", amount: 5000, term: 3, start: "2026-02", method: "salary_deduction", status: "Active" },
  ];

  function peso(n) {
    const v = Number(n);
    if (!Number.isFinite(v)) return "â€”";
    return `₱ ${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function computePerCutoff(amount, termMonths) {
    const cutoffsPerMonth = 2;
    const totalCutoffs = Math.max(1, Number(termMonths) * cutoffsPerMonth);
    return amount / totalCutoffs;
  }

  function renderCa() {
    if (!caTbody) return;
    caTbody.innerHTML = "";

    cashAdvances.forEach((row) => {
      const per = computePerCutoff(row.amount, row.term);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${esc(row.employee)}</td>
        <td>${esc(peso(row.amount))}</td>
        <td>${esc(row.term)} mo</td>
        <td>${esc(row.start)}</td>
        <td>${esc(peso(per))} <span class="muted small">(placeholder)</span></td>
        <td>${esc(row.status)}</td>
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

  function openNewCa() {
    caForm?.reset();
    document.getElementById("caTerm").value = document.getElementById("caDefaultTermMonths")?.value || "3";
    document.getElementById("caMethodTxn").value = document.getElementById("caMethod")?.value || "salary_deduction";
    drawer?.open();
  }

  newCaBtn?.addEventListener("click", openNewCa);

  caForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    const employee = document.getElementById("caEmployee").value;
    const amount = Number(document.getElementById("caAmount").value || 0);
    const term = Number(document.getElementById("caTerm").value || 1);
    const start = document.getElementById("caStartMonth").value;
    const method = document.getElementById("caMethodTxn").value;

    if (!employee || !start || !Number.isFinite(amount) || amount <= 0) {
      toast("Please fill Employee, Amount, Start month.", "error");
      return;
    }

    const id = Math.max(0, ...cashAdvances.map((x) => x.id)) + 1;
    cashAdvances.push({ id, employee, amount, term, start, method, status: "Active" });

    drawer?.close();
    renderCa();
    toast("Cash advance added.");
  });

  caTbody?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-ca]");
    if (!btn) return;
    const act = btn.getAttribute("data-ca");
    const id = Number(btn.getAttribute("data-id"));
    const row = cashAdvances.find((x) => x.id === id);
    if (!row) return;

    if (act === "view") {
      toast(`${row.employee}: ${peso(row.amount)} | ${row.term} mo | ${row.start} | ${row.status}`);
    }

    if (act === "edit") {
      toast("Edit UI: wire a second drawer state if you want. (Kept simple here.)");
    }

    if (act === "close") {
      if (!confirm("Close this cash advance?")) return;
      cashAdvances = cashAdvances.map((x) => (x.id === id ? { ...x, status: "Completed" } : x));
      renderCa();
      toast("Cash advance closed.");
    }
  });

  document.getElementById("caPolicySave")?.addEventListener("click", () => {
    toast("Saved Cash Advance Policy (UI only).");
  });

  renderCa();
}



