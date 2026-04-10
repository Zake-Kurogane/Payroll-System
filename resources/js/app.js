import "./bootstrap";

import $ from "jquery";
import "select2/dist/css/select2.css";
import "select2";
import "../css/select2_overrides.css";
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

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    bootSelect2();
    initAutoHideAlerts(document);
  }, { once: true });
} else {
  bootSelect2();
  initAutoHideAlerts(document);
}
