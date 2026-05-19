export function createDrawer(drawer, overlay, closeButtons = []) {
  if (!drawer) return null;

  function triggerPrimarySubmit() {
    const form = drawer.querySelector("form");
    if (form) {
      if (typeof form.requestSubmit === "function") form.requestSubmit();
      else form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
      return;
    }
    const submitBtn = drawer.querySelector('button[type="submit"], input[type="submit"]');
    if (submitBtn && !submitBtn.disabled) submitBtn.click();
  }

  function open() {
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("drawer-open");
  }

  function close() {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    document.body.classList.remove("drawer-open");
  }

  if (overlay) overlay.addEventListener("click", close);
  closeButtons.forEach((btn) => btn?.addEventListener("click", close));
  drawer.addEventListener("keydown", (e) => {
    if (e.key !== "Enter") return;
    const target = e.target;
    if (target && (target.tagName === "TEXTAREA" || target.isContentEditable)) return;
    if (!drawer.classList.contains("is-open")) return;
    e.preventDefault();
    triggerPrimarySubmit();
  });

  return { open, close };
}
