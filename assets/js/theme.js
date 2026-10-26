(() => {
  const storageKey = "ait-theme";
  const root = document.documentElement;
  let saved = null;
  try {
    saved = localStorage.getItem(storageKey);
  } catch (error) {
    saved = null;
  }
  const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
  root.dataset.theme = saved === "dark" || (saved === null && prefersDark) ? "dark" : "light";

  const paint = () => {
    const dark = root.dataset.theme === "dark";
    document.querySelectorAll("[data-theme-label]").forEach((label) => {
      label.textContent = dark ? "Light mode" : "Dark mode";
    });
    document.querySelectorAll("[data-theme-icon]").forEach((icon) => {
      icon.textContent = dark ? "☼" : "◐";
    });
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.setAttribute("aria-pressed", dark ? "true" : "false");
    });
  };

  window.aitTheme = {
    toggle() {
      root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
      try {
        localStorage.setItem(storageKey, root.dataset.theme);
      } catch (error) {
        /* private mode: the choice simply is not remembered */
      }
      paint();
    },
  };

  // Parses a fetched page for the few elements a form needs (alerts, bot check).
  // <style>/<script> are dropped first: Chrome applies this page's CSP to parsed
  // documents and hides nonces from them, which would log false violations.
  window.aitParseHtml = (html) =>
    new DOMParser().parseFromString(String(html).replace(/<(style|script)\b[\s\S]*?<\/\1>/gi, ""), "text/html");

  // Forms that submit with fetch() must pick up the bot check the server sends
  // back: a new arithmetic question, a fresh signed timestamp, or a challenge that
  // only appears after repeated failed sign-ins.
  window.aitSyncBotCheck = (form, responseDoc) => {
    if (!form || !responseDoc) return;
    const selector = ".ait-hp, input[name=\"form_ts\"], label.ait-captcha, input[name=\"recaptcha_v3_token\"], .g-recaptcha";
    form.querySelectorAll(selector).forEach((node) => node.remove());
    const source = (form.id && responseDoc.getElementById(form.id)) || responseDoc.querySelector("form");
    if (!source) return;
    const anchor = form.querySelector("[type=\"submit\"]");
    source.querySelectorAll(selector).forEach((node) => {
      const copy = document.importNode(node, true);
      if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(copy, anchor);
      else form.appendChild(copy);
    });
  };

  // Behaviour that used to live in inline on* attributes. The CSP forbids those
  // (script-src-attr 'none'), so every portal page gets them from here instead.
  document.addEventListener("submit", (event) => {
    const form = event.target;
    const message = form instanceof HTMLFormElement ? form.dataset.confirm : null;
    const submitter = event.submitter;
    const buttonMessage = submitter && submitter.dataset ? submitter.dataset.confirm : null;
    const prompt = buttonMessage || message;
    if (prompt && !window.confirm(prompt)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener("change", (event) => {
    const field = event.target;
    if (field && field.matches && field.matches("[data-autosubmit]") && field.form) {
      if (typeof field.form.requestSubmit === "function") field.form.requestSubmit();
      else field.form.submit();
    }
  });

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest ? event.target.closest("[data-print]") : null;
    if (trigger) {
      event.preventDefault();
      window.print();
    }
  });

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.addEventListener("click", () => window.aitTheme.toggle());
    });
    paint();
  });
})();
