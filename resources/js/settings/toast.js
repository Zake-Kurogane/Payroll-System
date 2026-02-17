const TOAST_CONTAINER_ID = "toastContainer";

function ensureContainer() {
  let el = document.getElementById(TOAST_CONTAINER_ID);
  if (el) return el;
  el = document.createElement("div");
  el.id = TOAST_CONTAINER_ID;
  el.className = "toast-container";
  document.body.appendChild(el);
  return el;
}

export function toast(message, type = "info") {
  const container = ensureContainer();
  const toastEl = document.createElement("div");
  toastEl.className = `toast toast--${type}`;
  toastEl.setAttribute("role", "status");
  toastEl.setAttribute("aria-live", "polite");
  toastEl.textContent = message;
  container.appendChild(toastEl);

  requestAnimationFrame(() => {
    toastEl.classList.add("is-visible");
  });

  const ttl = type === "error" ? 4200 : 3000;
  setTimeout(() => {
    toastEl.classList.remove("is-visible");
    setTimeout(() => toastEl.remove(), 250);
  }, ttl);
}

