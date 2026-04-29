export function initUserMenuDropdown(options = {}) {
  const {
    buttonId = "userMenuBtn",
    menuId = "userMenu",
  } = options;

  const userMenuBtn = document.getElementById(buttonId);
  const userMenu = document.getElementById(menuId);
  const notifMenuBtn = document.getElementById("notifMenuBtn");
  const notifMenu = document.getElementById("notifMenu");
  const notifBadge = notifMenuBtn?.querySelector(".notif-btn__badge") || null;
  const readStorageKey = "topbar_read_notifications_v1";

  if (!userMenuBtn || !userMenu) return;

  function closeUserMenu() {
    userMenu.classList.remove("is-open");
    userMenuBtn.setAttribute("aria-expanded", "false");
  }

  function closeNotifMenu() {
    if (!notifMenuBtn || !notifMenu) return;
    notifMenu.classList.remove("is-open");
    notifMenuBtn.setAttribute("aria-expanded", "false");
  }

  function toggleUserMenu() {
    const isOpen = userMenu.classList.contains("is-open");
    if (!isOpen) closeNotifMenu();
    userMenu.classList.toggle("is-open", !isOpen);
    userMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }

  function toggleNotifMenu() {
    if (!notifMenuBtn || !notifMenu) return;
    const isOpen = notifMenu.classList.contains("is-open");
    if (!isOpen) closeUserMenu();
    notifMenu.classList.toggle("is-open", !isOpen);
    notifMenuBtn.setAttribute("aria-expanded", String(!isOpen));
  }

  userMenuBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    toggleUserMenu();
  });

  if (notifMenuBtn && notifMenu) {
    let readMap = {};
    try {
      readMap = JSON.parse(window.localStorage.getItem(readStorageKey) || "{}") || {};
    } catch (_) {
      readMap = {};
    }

    const persistReadMap = () => {
      window.localStorage.setItem(readStorageKey, JSON.stringify(readMap));
    };

    const refreshUnreadBadge = () => {
      if (!notifBadge) return;
      const unreadCount = notifMenu.querySelectorAll(".notif-dropdown__item--active[data-notif-key]").length;
      notifBadge.textContent = String(unreadCount);
      notifBadge.style.display = unreadCount > 0 ? "inline-flex" : "none";
    };

    notifMenu.querySelectorAll(".notif-dropdown__item[data-notif-key]").forEach((item) => {
      const key = item.getAttribute("data-notif-key");
      if (key && readMap[key]) {
        item.classList.remove("notif-dropdown__item--active");
      }
    });
    refreshUnreadBadge();

    notifMenuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleNotifMenu();
    });

    notifMenu.querySelectorAll(".notif-dropdown__item--active").forEach((item) => {
      item.addEventListener("click", () => {
        const key = item.getAttribute("data-notif-key");
        if (key) {
          readMap[key] = true;
          persistReadMap();
        }
        item.classList.remove("notif-dropdown__item--active");
        refreshUnreadBadge();
      });
    });
  }

  document.addEventListener("click", (e) => {
    if (!userMenu.contains(e.target) && e.target !== userMenuBtn) closeUserMenu();
    if (notifMenu && notifMenuBtn && !notifMenu.contains(e.target) && e.target !== notifMenuBtn) {
      closeNotifMenu();
    }
  });

  if (!window.__userMenuEscHandlerAdded) {
    window.__userMenuEscHandlerAdded = true;
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeUserMenu();
        closeNotifMenu();
      }
    });
  }
}
