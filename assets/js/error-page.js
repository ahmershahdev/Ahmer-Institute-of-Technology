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

if (document.body.classList.contains("is-offline")) {
  window.addEventListener("online", () => window.location.reload());
}
