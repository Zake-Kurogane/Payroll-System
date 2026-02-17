export function initClock(options = {}) {
  const {
    clockId = "clock",
    dateId = "date",
    intervalMs = 30000,
  } = options;

  const clockEl = document.getElementById(clockId);
  const dateEl = document.getElementById(dateId);

  if (!clockEl && !dateEl) return;

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function tick() {
    const d = new Date();
    let h = d.getHours();
    const m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12;
    h = h ? h : 12;
    if (clockEl) clockEl.textContent = `${pad(h)}:${pad(m)} ${ampm}`;
    if (dateEl) dateEl.textContent = `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
  }

  tick();
  setInterval(tick, intervalMs);
}

