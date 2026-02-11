const form = document.getElementById("loginForm");
const username = document.getElementById("username");
const password = document.getElementById("password");

const userError = document.getElementById("userError");
const passError = document.getElementById("passError");
const statusEl = document.getElementById("status");

function setError(el, msg) {
  if (!el) return;
  el.textContent = msg;
}

function clearErrors() {
  setError(userError, "");
  setError(passError, "");
  if (statusEl) statusEl.textContent = "";
}

form.addEventListener("submit", (e) => {
  clearErrors();

  const u = username.value.trim();
  const p = password.value.trim();

  let ok = true;

  if (!u) {
    setError(userError, "Username is required.");
    ok = false;
  }
  if (!p) {
    setError(passError, "Password is required.");
    ok = false;
  }

  // ✅ Only prevent submit when invalid
  if (!ok) {
    e.preventDefault();
    return;
  }

  // ✅ Allow normal Laravel POST + redirect
  // Optional: show a message while submitting
  if (statusEl) statusEl.textContent = "Logging in...";
});
