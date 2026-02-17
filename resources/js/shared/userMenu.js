export function initUserMenuDropdown(options = {}) {
  const {
    buttonId = "userMenuBtn",
    menuId = "userMenu",
  } = options;

  const userMenuBtn = document.getElementById(buttonId);
  const userMenu = document.getElementById(menuId);

  if (!userMenuBtn || !userMenu) return;

  function closeUserMenu() {
    userMenu.classList.remove("is-open");
    userMenuBtn.setAttribute("aria-expanded", "false");
  }

  function toggleUserMenu() {
    const isOpen = userMenu.classList.contains("is-open");
    userMenu.classList.toggle("is-open", !isOpen);
    userMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }

  userMenuBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    toggleUserMenu();
  });

  document.addEventListener("click", (e) => {
    if (!userMenu.contains(e.target) && e.target !== userMenuBtn) closeUserMenu();
  });

  if (!window.__userMenuEscHandlerAdded) {
    window.__userMenuEscHandlerAdded = true;
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeUserMenu();
    });
  }
}
