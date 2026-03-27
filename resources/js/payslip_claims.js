import { initClock } from "./shared/clock";
import { initUserMenuDropdown } from "./shared/userMenu";
import { initProfileDrawer } from "./shared/profileDrawer";
import { initSettingsSync } from "./shared/settingsSync";

document.addEventListener("DOMContentLoaded", () => {
  initClock();
  initUserMenuDropdown();
  initProfileDrawer();
  initSettingsSync();

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
