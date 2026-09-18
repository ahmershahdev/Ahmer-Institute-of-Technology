(() => {
  const storageKey = "ait-theme";
  const root = document.documentElement;
  const saved = localStorage.getItem(storageKey);
  root.dataset.theme = saved === "dark" ? "dark" : "light";

  window.aitTheme = {
    toggle() {
      const next = root.dataset.theme === "dark" ? "light" : "dark";
      root.dataset.theme = next;
      localStorage.setItem(storageKey, next);
      document.querySelectorAll("[data-theme-label]").forEach((label) => {
        label.textContent = next === "dark" ? "Light mode" : "Dark mode";
      });
      document.querySelectorAll("[data-theme-icon]").forEach((icon) => {
        icon.textContent = next === "dark" ? "☼" : "◐";
      });
    },
  };

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.addEventListener("click", () => window.aitTheme.toggle());
    });
    document.querySelectorAll("[data-theme-label]").forEach((label) => {
      label.textContent =
        root.dataset.theme === "dark" ? "Light mode" : "Dark mode";
    });
    document.querySelectorAll("[data-theme-icon]").forEach((icon) => {
      icon.textContent = root.dataset.theme === "dark" ? "☼" : "◐";
    });
  });
})();
