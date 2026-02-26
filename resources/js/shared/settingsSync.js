const SETTINGS_UPDATE_KEY = "settings_updated_at";

export function broadcastSettingsUpdate() {
  try {
    localStorage.setItem(SETTINGS_UPDATE_KEY, String(Date.now()));
  } catch {
    // ignore
  }
}

export function initSettingsSync(onUpdate) {
  const handler = () => {
    if (typeof onUpdate === "function") onUpdate();
    else window.location.reload();
  };
  window.addEventListener("storage", (e) => {
    if (e.key === SETTINGS_UPDATE_KEY) handler();
  });
}
