(() => {
  // Offline fallback: a service worker that only ever serves errors/offline.html.
  const script = document.currentScript;
  if ("serviceWorker" in navigator && script && window.isSecureContext) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register(new URL("../../sw.js", script.src)).catch(() => {});
    });
  }

  const reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const initNav = () => {
    const toggle = document.querySelector(".nav-toggle");
    const nav = document.querySelector(".site-nav");
    if (!toggle || !nav) return;
    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Close navigation" : "Open navigation");
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && nav.classList.contains("open")) {
        nav.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.focus();
      }
    });
  };

  const initScrollTop = () => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "scroll-top-button";
    button.setAttribute("aria-label", "Back to top");
    button.textContent = "↑";
    document.body.appendChild(button);
    const update = () => button.classList.toggle("is-visible", window.scrollY > 420);
    window.addEventListener("scroll", update, { passive: true });
    button.addEventListener("click", () => window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" }));
    update();
  };

  const initTyping = () => {
    const typing = document.querySelector("[data-typing]");
    if (!typing || reduceMotion) return;
    let words;
    try {
      words = JSON.parse(typing.dataset.typing);
    } catch (error) {
      return;
    }
    let word = 0;
    let index = words[0].length;
    let deleting = true;
    const tick = () => {
      const current = words[word];
      typing.textContent = current.slice(0, index);
      if (!deleting && index === current.length) {
        deleting = true;
        window.setTimeout(tick, 1800);
        return;
      }
      if (deleting && index === 0) {
        deleting = false;
        word = (word + 1) % words.length;
      }
      index += deleting ? -1 : 1;
      window.setTimeout(tick, deleting ? 55 : 95);
    };
    window.setTimeout(tick, 2200);
  };

  const initChart = () => {
    const canvas = document.querySelector("#admissionsChart");
    if (!canvas || !window.Chart) return;
    const styles = getComputedStyle(document.documentElement);
    const ink = styles.getPropertyValue("--muted").trim() || "#59716f";
    new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: ["2022", "2023", "2024", "2025", "2026"],
        datasets: [{
          label: "Applications received",
          data: [1840, 2210, 2675, 3320, 4105],
          backgroundColor: ["#5ed0c1", "#5ed0c1", "#5ed0c1", "#5ed0c1", "#ef6a50"],
          borderRadius: 6,
          maxBarThickness: 46,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: reduceMotion ? false : { duration: 700 },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: ink } },
          y: { beginAtZero: true, grid: { color: "rgba(127, 160, 160, .18)" }, ticks: { color: ink } },
        },
      },
    });
  };

  // reCAPTCHA v3: fetch a token for the form's action just before it submits.
  const initRecaptcha = () => {
    document.querySelectorAll("input[data-recaptcha-key]").forEach((field) => {
      const form = field.form;
      if (!form) return;
      form.addEventListener("submit", (event) => {
        if (form.dataset.captchaReady === "true" || !window.grecaptcha) return;
        event.preventDefault();
        window.grecaptcha.ready(() => {
          window.grecaptcha
            .execute(field.dataset.recaptchaKey, { action: field.dataset.recaptchaAction })
            .then((token) => {
              field.value = token;
              form.dataset.captchaReady = "true";
              form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });
      });
    });
  };

  const initReveal = () => {
    const items = document.querySelectorAll(".reveal");
    if (!items.length || reduceMotion || !("IntersectionObserver" in window)) {
      items.forEach((item) => item.classList.add("is-visible"));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: "0px 0px -40px 0px" });
    items.forEach((item) => observer.observe(item));
  };

  const start = () => {
    initNav();
    initScrollTop();
    initTyping();
    initRecaptcha();
    initReveal();
  };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
  window.addEventListener("load", initChart);
})();
