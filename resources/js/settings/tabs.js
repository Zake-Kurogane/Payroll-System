export function initTabs() {
  const tabs = Array.from(document.querySelectorAll(".tab[role='tab']"));
  if (!tabs.length) return;

  const panelByTab = new Map();
  tabs.forEach((btn) => {
    const panelId = btn.getAttribute("aria-controls");
    const panel = panelId ? document.getElementById(panelId) : null;
    if (panel) panelByTab.set(btn, panel);
  });

  const STORAGE_KEY = "settings.activeTab";
  const storage = (() => {
    try { return window.sessionStorage; } catch { return null; }
  })();

  function activate(btn, shouldFocus = false) {
    tabs.forEach((b) => {
      const active = b === btn;
      b.classList.toggle("is-active", active);
      b.setAttribute("aria-selected", String(active));
      b.tabIndex = active ? 0 : -1;

      const panel = panelByTab.get(b);
      if (panel) {
        panel.hidden = !active;
        panel.classList.toggle("is-open", active);
      }
    });

    const tabId = btn.getAttribute("data-tab") || btn.id || "";
    if (tabId && storage) {
      try { storage.setItem(STORAGE_KEY, tabId); } catch {}
    }

    if (shouldFocus) btn.focus();
  }

  let initial = null;
  try {
    const ref = document.referrer || "";
    if (/\/login\b|\/logout\b/i.test(ref) && storage) {
      storage.removeItem(STORAGE_KEY);
    }
    const saved = storage ? storage.getItem(STORAGE_KEY) : null;
    if (saved) {
      initial = tabs.find((b) => b.getAttribute("data-tab") === saved || b.id === saved) || null;
    }
  } catch {}
  if (!initial) {
    initial = tabs.find((b) => b.getAttribute("aria-selected") === "true") || tabs[0];
  }
  activate(initial, false);

  tabs.forEach((btn) => {
    btn.addEventListener("click", () => activate(btn, true));
  });

  const tablist = tabs[0].closest("[role='tablist']");
  tablist?.addEventListener("keydown", (e) => {
    const currentIndex = tabs.indexOf(document.activeElement);
    if (currentIndex === -1) return;

    let nextIndex = null;
    if (e.key === "ArrowRight") nextIndex = (currentIndex + 1) % tabs.length;
    if (e.key === "ArrowLeft") nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    if (e.key === "Home") nextIndex = 0;
    if (e.key === "End") nextIndex = tabs.length - 1;

    if (nextIndex === null) return;
    e.preventDefault();
    activate(tabs[nextIndex], true);
  });
}
