import "./bootstrap";

import $ from "jquery";
import "select2/dist/css/select2.css";
import "select2";
import "../css/select2_overrides.css";
import "../css/mobile_layout.css";
import { initSelect2, initSelect2Auto } from "./shared/select2";

window.$ = $;
window.jQuery = $;

function bootSelect2() {
  initSelect2(document);
  initSelect2Auto();
}

function initAutoHideAlerts(root = document) {
  const alerts = root.querySelectorAll(".js-autoHideAlert");
  alerts.forEach((el) => {
    if (el.dataset.autohideBound === "1") return;
    el.dataset.autohideBound = "1";
    const ms = Number(el.getAttribute("data-hide-ms") || 3000);
    window.setTimeout(() => {
      el.style.transition = "opacity 220ms ease, transform 220ms ease";
      el.style.opacity = "0";
      el.style.transform = "translateY(-4px)";
      window.setTimeout(() => {
        el.remove();
      }, 240);
    }, Number.isFinite(ms) ? ms : 3000);
  });
}

function initMobileNav(root = document) {
  const menuBtn = root.getElementById("mobileMenuBtn");
  const closeBtn = root.getElementById("mobileNavClose");
  const overlay = root.getElementById("mobileNavOverlay");
  const sideNav = root.getElementById("sideNav");

  if (!sideNav || !overlay) return;

  const isMobile = () => window.matchMedia("(max-width: 900px)").matches;

  const openNav = () => {
    if (!isMobile()) return;
    document.body.classList.add("mobile-nav-open");
    menuBtn?.setAttribute("aria-expanded", "true");
    sideNav.setAttribute("aria-hidden", "false");
  };

  const closeNav = () => {
    document.body.classList.remove("mobile-nav-open");
    menuBtn?.setAttribute("aria-expanded", "false");
    sideNav.setAttribute("aria-hidden", isMobile() ? "true" : "false");
  };

  menuBtn?.addEventListener("click", openNav);
  closeBtn?.addEventListener("click", closeNav);
  overlay?.addEventListener("click", closeNav);

  sideNav.querySelectorAll("a.menu__item, .submenu .menu__item").forEach((link) => {
    link.addEventListener("click", () => {
      if (isMobile()) closeNav();
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeNav();
  });

  const mq = window.matchMedia("(min-width: 901px)");
  const syncDesktopState = () => {
    if (mq.matches) {
      closeNav();
      sideNav.setAttribute("aria-hidden", "false");
    } else {
      sideNav.setAttribute("aria-hidden", document.body.classList.contains("mobile-nav-open") ? "false" : "true");
    }
  };
  if (typeof mq.addEventListener === "function") {
    mq.addEventListener("change", syncDesktopState);
  } else if (typeof mq.addListener === "function") {
    mq.addListener(syncDesktopState);
  }
  syncDesktopState();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    bootSelect2();
    initAutoHideAlerts(document);
    initMobileNav(document);
  }, { once: true });
} else {
  bootSelect2();
  initAutoHideAlerts(document);
  initMobileNav(document);
}
