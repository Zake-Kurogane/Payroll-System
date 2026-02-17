export function createDrawer(drawer, overlay, closeButtons = []) {
  if (!drawer) return null;

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

  return { open, close };
}

