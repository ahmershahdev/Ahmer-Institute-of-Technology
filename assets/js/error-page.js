// Buttons on the error and offline pages (no inline handlers: the CSP forbids them).
document.addEventListener("click", (event) => {
  const reload = event.target.closest("[data-reload]");
  if (reload) {
    event.preventDefault();
    window.location.reload();
    return;
  }
  const back = event.target.closest("[data-back]");
  if (back && window.history.length > 1) {
    event.preventDefault();
    window.history.back();
  }
});

// The offline page re-checks the connection and returns on its own.
if (document.body.classList.contains("is-offline")) {
  window.addEventListener("online", () => window.location.reload());
}
