import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

  const assignSeg = document.getElementById("assignSeg");
  const assignInput = document.getElementById("assignmentInput");
  const areaPlaceInput = document.getElementById("areaPlaceInput");
  if (assignSeg && assignInput && areaPlaceInput) {
    const activeAssign = (assignSeg.dataset.activeAssign || assignInput.value || "").trim();
    const activePlace = (assignSeg.dataset.activePlace || areaPlaceInput.value || "").trim();
    let openDropdown = null;
    let openDropdownBtn = null;
    let assignBtns = [];
    const contentScroller = document.querySelector(".content");

    const apiFetch = async (url) => {
      const res = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
      if (!res.ok) throw new Error("Request failed.");
      return res.json();
    };
    const escSel = (v) => String(v).replace(/\\/g, "\\\\").replace(/"/g, '\\"');

    const closeAllDropdowns = () => {
      assignSeg.querySelectorAll(".seg__dropdown").forEach((d) => {
        d.classList.remove("is-open");
        d.style.display = "none";
      });
      openDropdown = null;
      openDropdownBtn = null;
    };

    const submitFilters = () => {
      const form = assignInput.closest("form");
      form && form.submit();
    };

    const positionDropdown = (btn, dropdown) => {
      if (!btn || !dropdown) return;
      const rect = btn.getBoundingClientRect();
      const viewportW = window.innerWidth || document.documentElement.clientWidth || 0;
      const desiredMin = Math.round(rect.width);
      const maxWidth = Math.min(320, Math.max(200, viewportW - 16));
      const dropdownW = Math.max(desiredMin, maxWidth);
      let left = Math.round(rect.left);
      if (left + dropdownW > viewportW - 8) left = Math.max(8, viewportW - dropdownW - 8);
      const top = Math.round(rect.bottom + 8);
      dropdown.style.left = `${left}px`;
      dropdown.style.top = `${top}px`;
      dropdown.style.minWidth = `${desiredMin}px`;
      dropdown.style.maxWidth = `${maxWidth}px`;
    };

    const refreshOpenDropdownPosition = () => {
      if (!openDropdown || !openDropdownBtn) return;
      positionDropdown(openDropdownBtn, openDropdown);
    };

    const wireAssignButtons = () => {
      assignBtns = Array.from(assignSeg.querySelectorAll(".seg__btn--emp"));

      assignBtns.forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          const rawAssign = btn.getAttribute("data-assign");
          const group = rawAssign && rawAssign !== "" ? rawAssign : "All";

          const dropdown = btn.closest(".seg__btn-wrap")?.querySelector(".seg__dropdown");
          const wasOpen = dropdown && dropdown.style.display === "block";
          const alreadyActive = btn.classList.contains("is-active");

          closeAllDropdowns();
          assignBtns.forEach((b) => b.classList.remove("is-active"));
          btn.classList.add("is-active");

          assignInput.value = group === "All" ? "" : group;
          areaPlaceInput.value = "";

          if (dropdown) {
            if (alreadyActive && wasOpen) {
              submitFilters();
              return;
            }
            positionDropdown(btn, dropdown);
            dropdown.style.display = "block";
            dropdown.classList.add("is-open");
            openDropdown = dropdown;
            openDropdownBtn = btn;
            return;
          }

          submitFilters();
        });
      });

      assignSeg.querySelectorAll(".seg__dropdown-item").forEach((item) => {
        item.addEventListener("click", (e) => {
          e.stopPropagation();
          const place = item.getAttribute("data-place") || "";
          const dropdown = item.closest(".seg__dropdown");
          const group = dropdown?.getAttribute("data-group") || "";
          dropdown?.querySelectorAll(".seg__dropdown-item").forEach((i) => i.classList.remove("is-active"));
          item.classList.add("is-active");
          assignInput.value = group;
          areaPlaceInput.value = place;
          closeAllDropdowns();
          submitFilters();
        });
      });
    };

    const applyInitialActiveState = () => {
      const allBtn = assignSeg.querySelector('.seg__btn--emp[data-assign=""]');
      assignBtns.forEach((b) => b.classList.remove("is-active"));

      if (!activeAssign) {
        allBtn?.classList.add("is-active");
        assignInput.value = "";
        areaPlaceInput.value = "";
        return;
      }

      const selectedBtn = assignSeg.querySelector(`.seg__btn--emp[data-assign="${escSel(activeAssign)}"]`);
      if (!selectedBtn) {
        allBtn?.classList.add("is-active");
        assignInput.value = "";
        areaPlaceInput.value = "";
        return;
      }

      selectedBtn.classList.add("is-active");
      assignInput.value = activeAssign;
      areaPlaceInput.value = activePlace || "";

      if (activePlace) {
        const ddItem = assignSeg.querySelector(`.seg__dropdown[data-group="${escSel(activeAssign)}"] .seg__dropdown-item[data-place="${escSel(activePlace)}"]`);
        ddItem?.classList.add("is-active");
      }
    };

    const buildFallbackAssignments = () => {
      const assignments = ["Davao", "Tagum", "Field"];
      assignSeg.innerHTML = "";

      const allBtn = document.createElement("button");
      allBtn.type = "button";
      allBtn.className = "seg__btn seg__btn--emp";
      allBtn.setAttribute("data-assign", "");
      allBtn.textContent = "All";
      assignSeg.appendChild(allBtn);

      assignments.forEach((label) => {
        const wrap = document.createElement("div");
        wrap.className = "seg__btn-wrap";
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "seg__btn seg__btn--emp";
        btn.setAttribute("data-assign", label);
        btn.textContent = label;
        wrap.appendChild(btn);
        assignSeg.appendChild(wrap);
      });
    };

    const buildAssignmentFilters = async () => {
      try {
        const data = await apiFetch("/employees/filters");
        const assignments = Array.isArray(data.assignments) ? data.assignments : [];
        const areaPlaces = data.area_places && typeof data.area_places === "object" && !Array.isArray(data.area_places)
          ? data.area_places
          : {};

        assignSeg.innerHTML = "";

        const allBtn = document.createElement("button");
        allBtn.type = "button";
        allBtn.className = "seg__btn seg__btn--emp";
        allBtn.setAttribute("data-assign", "");
        allBtn.textContent = "All";
        assignSeg.appendChild(allBtn);

        assignments.forEach((label) => {
          const places = Array.isArray(areaPlaces[label]) ? areaPlaces[label] : [];
          const wrap = document.createElement("div");
          wrap.className = "seg__btn-wrap";

          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "seg__btn seg__btn--emp";
          btn.setAttribute("data-assign", label);
          btn.textContent = label;
          if (places.length) {
            const chev = document.createElement("span");
            chev.className = "seg__chevron";
            chev.textContent = "▾";
            btn.appendChild(chev);
          }
          wrap.appendChild(btn);

          if (places.length) {
            const dropdown = document.createElement("div");
            dropdown.className = "seg__dropdown";
            dropdown.setAttribute("data-group", label);
            dropdown.style.display = "none";
            places.forEach((place) => {
              const item = document.createElement("button");
              item.type = "button";
              item.className = "seg__dropdown-item";
              item.setAttribute("data-place", place);
              item.textContent = place;
              dropdown.appendChild(item);
            });
            wrap.appendChild(dropdown);
          }

          assignSeg.appendChild(wrap);
        });
      } catch {
        buildFallbackAssignments();
      }
    };

    buildAssignmentFilters().finally(() => {
      wireAssignButtons();
      applyInitialActiveState();
    });

    document.addEventListener("click", (e) => {
      if (!assignSeg.contains(e.target)) closeAllDropdowns();
    }, { capture: true });
    window.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
    window.addEventListener("resize", refreshOpenDropdownPosition);
    contentScroller?.addEventListener("scroll", refreshOpenDropdownPosition, { passive: true });
  }

  const printBtn = document.getElementById("printClaimSheetBtn");
  if (printBtn) {
    printBtn.addEventListener("click", async () => {
      const url = printBtn.getAttribute("data-url");
      if (!url) return;

      const overlay = document.getElementById("claimPrintOverlay");
      const modal = document.getElementById("claimPrintModal");
      const frame = document.getElementById("claimPrintFrame");
      const closeBtn = document.getElementById("claimPrintCloseBtn");
      const modalPrintBtn = document.getElementById("claimPrintBtn");
      const loading = document.getElementById("claimPrintLoading");
      if (!overlay || !modal || !frame || !closeBtn || !modalPrintBtn || !loading) return;

      const tryPrint = () => {
        try {
          frame.contentWindow?.focus?.();
          frame.contentWindow?.print?.();
        } catch {
          // Best-effort; some browsers/PDF viewers block programmatic printing.
        }
      };

      const onKeyDown = (e) => {
        if (e.key === "Escape") close();
      };

      let objectUrl = "";
      const close = () => {
        overlay.hidden = true;
        modal.hidden = true;
        modal.setAttribute("aria-hidden", "true");
        frame.hidden = true;
        loading.hidden = false;
        modalPrintBtn.disabled = true;
        frame.onload = null;
        frame.src = "about:blank";
        document.removeEventListener("keydown", onKeyDown);
        try {
          if (objectUrl) URL.revokeObjectURL(objectUrl);
        } catch {
          // ignore
        }
        objectUrl = "";
      };

      overlay.hidden = false;
      modal.hidden = false;
      modal.setAttribute("aria-hidden", "false");
      modalPrintBtn.disabled = true;
      frame.hidden = true;
      loading.hidden = false;

      overlay.onclick = close;
      closeBtn.onclick = close;
      document.addEventListener("keydown", onKeyDown);
      modalPrintBtn.onclick = tryPrint;

      const prevText = printBtn.textContent;
      printBtn.disabled = true;
      printBtn.textContent = "Preparing...";

      try {
        const res = await fetch(url, { credentials: "same-origin" });
        if (!res.ok) throw new Error(`Failed to load PDF (${res.status})`);

        const blob = await res.blob();

        // Some Laravel download responses send application/octet-stream even for PDFs.
        // Sniff the header so we can still print without forcing a download.
        let pdfBlob = blob;
        const declaredType = (blob.type || "").toLowerCase();
        if (!declaredType.includes("pdf")) {
          const head = await blob.slice(0, 5).text();
          if (head !== "%PDF-") throw new Error("Not a PDF response");
          pdfBlob = new Blob([blob], { type: "application/pdf" });
        }
        objectUrl = URL.createObjectURL(pdfBlob);

        frame.onload = () => {
          loading.hidden = true;
          frame.hidden = false;
          modalPrintBtn.disabled = false;

          // Attempt auto-print, but keep the visible preview so the user can print manually if blocked.
          setTimeout(tryPrint, 350);
          setTimeout(tryPrint, 1400);
        };
        frame.src = objectUrl;
      } catch (err) {
        close();
        alert("Unable to print the claim sheet. Please try again.");
      } finally {
        printBtn.disabled = false;
        printBtn.textContent = prevText;
      }
    });
  }
});
