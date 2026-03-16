import { createDrawer } from "../settings/drawer";

export function initProfileDrawer() {
  const editProfileBtn = document.getElementById("editProfileBtn");
  const profileDrawer = document.getElementById("profileDrawer");
  if (!profileDrawer) return;

  const profileDrawerOverlay = document.getElementById("profileDrawerOverlay");
  const closeProfileDrawer = document.getElementById("closeProfileDrawer");
  const cancelProfileBtn = document.getElementById("cancelProfileBtn");
  const pfFirstName = document.getElementById("pfFirstName");
  const pfMiddleName = document.getElementById("pfMiddleName");
  const pfLastName = document.getElementById("pfLastName");
  const pfUsername = document.getElementById("pfUsername");
  const pfEmail = document.getElementById("pfEmail");
  const pfRole = document.getElementById("pfRole");

  const pfCurrentUsername = document.getElementById("pfCurrentUsername");
  const pfNewUsername = document.getElementById("pfNewUsername");
  const pfConfirmUsername = document.getElementById("pfConfirmUsername");

  const pfCurrentPassword = document.getElementById("pfCurrentPassword");
  const pfNewPassword = document.getElementById("pfNewPassword");
  const pfConfirmPassword = document.getElementById("pfConfirmPassword");

  const drawer = createDrawer(profileDrawer, profileDrawerOverlay, [closeProfileDrawer, cancelProfileBtn]);

  function closeUserMenu() {
    const userMenu = document.getElementById("userMenu");
    const userMenuBtn = document.getElementById("userMenuBtn");
    if (userMenu) userMenu.classList.remove("is-open");
    if (userMenuBtn) userMenuBtn.setAttribute("aria-expanded", "false");
  }

  async function loadProfileToDrawer() {
    try {
      // Avoid re-fetching when fields already have values (e.g., after validation errors).
      if (pfFirstName?.value || pfLastName?.value || pfUsername?.value || pfEmail?.value) return;

      const res = await fetch("/profile/me", {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!res.ok) return;
      const u = await res.json();

      if (pfFirstName) pfFirstName.value = u.first_name || "";
      if (pfMiddleName) pfMiddleName.value = u.middle_name || "";
      if (pfLastName) pfLastName.value = u.last_name || "";
      if (pfUsername) pfUsername.value = u.name || "";
      if (pfEmail) pfEmail.value = u.email || "";
      if (pfCurrentUsername) pfCurrentUsername.value = u.name || "";
    } catch {
      // silently ignore
    }
  }

  function openProfileDrawer() {
    closeUserMenu();

    // clear passwords + errors
    if (pfCurrentPassword) pfCurrentPassword.value = "";
    if (pfNewPassword) pfNewPassword.value = "";
    if (pfConfirmPassword) pfConfirmPassword.value = "";
    if (pfNewUsername) pfNewUsername.value = "";
    if (pfConfirmUsername) pfConfirmUsername.value = "";

    loadProfileToDrawer();

    drawer?.open();
  }

  editProfileBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    openProfileDrawer();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && profileDrawer.classList.contains("is-open")) {
      drawer?.close();
    }
  });

  // Form submits to backend; no client-side interception.
}
