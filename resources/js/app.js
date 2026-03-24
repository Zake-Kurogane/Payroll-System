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

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => { bootSelect2(); }, { once: true });
} else {
  bootSelect2();
}
