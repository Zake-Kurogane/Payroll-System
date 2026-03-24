import $ from "jquery";

function resolveJqueryWithSelect2() {
  const jq = window.jQuery || window.$ || $;
  if (!jq || !jq.fn || typeof jq.fn.select2 !== "function") return null;
  return jq;
}

function isSelectEligible(selectEl) {
  if (!selectEl || selectEl.nodeName !== "SELECT") return false;
  if (selectEl.hasAttribute("data-no-select2")) return false;
  if (selectEl.classList.contains("select2-hidden-accessible")) return false;
  if (selectEl.disabled) return false;
  return true;
}

function resolveDropdownParent(selectEl) {
  return (
    selectEl.closest(".drawer.is-open") ||
    selectEl.closest(".ca-drawer.is-open .drawer__panel") ||
    selectEl.closest(".drawer__panel") ||
    document.body
  );
}

function resolvePlaceholder(selectEl) {
  const explicit = selectEl.getAttribute("data-placeholder");
  if (explicit) return explicit;
  const emptyOpt = selectEl.querySelector('option[value=""]');
  if (emptyOpt) return emptyOpt.textContent || "";
  return null;
}

function shouldAllowClear(selectEl) {
  return !!selectEl.querySelector('option[value=""]');
}

function minSearchFor(selectEl) {
  const optionCount = selectEl.querySelectorAll("option").length;
  return optionCount > 8 ? 0 : Infinity;
}

export function initSelect2(root = document) {
  const jq = resolveJqueryWithSelect2();
  if (!jq) return;

  const selects = Array.from(root.querySelectorAll("select"));
  selects.forEach((selectEl) => {
    if (!isSelectEligible(selectEl)) return;

    const placeholder = resolvePlaceholder(selectEl);
    const dropdownParent = resolveDropdownParent(selectEl);
    const opts = {
      width: "100%",
      theme: "default",
      dropdownParent: $(dropdownParent),
      dropdownCssClass: "seg-s2-dropdown",
      selectionCssClass: "seg-s2-selection",
      allowClear: shouldAllowClear(selectEl),
      minimumResultsForSearch: minSearchFor(selectEl),
    };
    if (placeholder) opts.placeholder = placeholder;

    jq(selectEl).select2(opts);
  });
}

export function initSelect2Auto() {
  const jq = resolveJqueryWithSelect2();
  if (!jq) return;

  const ensureInitAndOpen = (e) => {
    const selectEl = e.target?.closest?.("select");
    if (!isSelectEligible(selectEl)) return;

    // Upgrade on first interaction, even if it was previously hidden.
    initSelect2(selectEl.parentElement || document);

    if (selectEl.classList.contains("select2-hidden-accessible")) {
      e.preventDefault();
      try {
        jq(selectEl).select2("open");
      } catch {
        // ignore
      }
    }
  };

  // Use capture so we get the event before the native dropdown opens.
  document.addEventListener("mousedown", ensureInitAndOpen, true);
  document.addEventListener("touchstart", ensureInitAndOpen, true);

  const mo = new MutationObserver((mutations) => {
    for (const m of mutations) {
      if (m.type === "childList") {
        m.addedNodes.forEach((n) => {
          if (n.nodeType !== 1) return;
          if (n.nodeName === "SELECT") initSelect2(n.parentElement || document);
          else initSelect2(n);
        });
      } else if (m.type === "attributes") {
        const el = m.target;
        if (el && el.nodeType === 1) {
          // If the target itself is a select, search within its parent
          const root = el.nodeName === "SELECT" ? (el.parentElement || document) : el;
          initSelect2(root);
        }
      }
    }
  });

  mo.observe(document.body, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ["hidden", "class", "style", "disabled", "data-no-select2"],
  });
}
