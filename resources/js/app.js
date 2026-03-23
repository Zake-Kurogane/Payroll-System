import "./bootstrap";

import $ from "jquery";
import "select2/dist/css/select2.css";
import "../css/select2_overrides.css";
import { initSelect2, initSelect2Auto } from "./shared/select2";

window.$ = $;
window.jQuery = $;

async function bootSelect2() {
  try {
    await import("select2");
  } catch {
    return;
  }
  initSelect2(document);
  initSelect2Auto();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => { void bootSelect2(); }, { once: true });
} else {
  void bootSelect2();
}
