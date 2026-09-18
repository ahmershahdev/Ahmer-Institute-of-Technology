window.addEventListener("load", () => {
  const topButton = document.createElement("button");
  topButton.type = "button";
  topButton.className = "scroll-top-button";
  topButton.setAttribute("aria-label", "Scroll to top");
  topButton.innerHTML = "&#8593;";
  document.body.appendChild(topButton);
  const updateTopButton = () =>
    topButton.classList.toggle("is-visible", window.scrollY > 420);
  window.addEventListener("scroll", updateTopButton, { passive: true });
  topButton.addEventListener("click", () =>
    window.scrollTo({ top: 0, behavior: "smooth" }),
  );
  updateTopButton();

  document.querySelector(".nav-toggle")?.addEventListener("click", () => {
    document.querySelector(".site-nav")?.classList.toggle("open");
  });

  const typing = document.querySelector("[data-typing]");
  if (typing) {
    const words = JSON.parse(typing.dataset.typing);
    let word = 0;
    let index = 0;
    let deleting = false;
    const tick = () => {
      const current = words[word];
      typing.textContent = current.slice(0, index);
      if (!deleting && index === current.length) {
        deleting = true;
        setTimeout(tick, 1500);
        return;
      }
      if (deleting && index === 0) {
        deleting = false;
        word = (word + 1) % words.length;
      }
      index += deleting ? -1 : 1;
      setTimeout(tick, deleting ? 55 : 95);
    };
    tick();
  }

  const canvas = document.querySelector("#admissionsChart");
  if (canvas && window.Chart) {
    new window.Chart(canvas, {
      type: "line",
      data: {
        labels: ["2022", "2023", "2024", "2025", "2026"],
        datasets: [
          {
            label: "Student participation",
            data: [42, 49, 57, 71, 86],
            borderColor: "#f5c861",
            backgroundColor: "rgba(245, 200, 97, .12)",
            borderWidth: 3,
            pointBackgroundColor: "#ef6a50",
            pointBorderColor: "#ef6a50",
            pointRadius: 4,
            fill: true,
            tension: 0.42,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: "#acd0c7" } },
          y: {
            beginAtZero: true,
            grid: { color: "rgba(172, 208, 199, .16)" },
            ticks: { color: "#acd0c7" },
          },
        },
      },
    });
  }

  const contactForm = document.querySelector("form[data-recaptcha-site-key]");
  const recaptchaToken = document.querySelector("#recaptcha-v3-token");
  if (
    contactForm &&
    recaptchaToken &&
    contactForm.dataset.recaptchaSiteKey &&
    window.grecaptcha
  ) {
    contactForm.addEventListener("submit", (event) => {
      if (contactForm.dataset.captchaReady === "true") return;
      event.preventDefault();
      window.grecaptcha.ready(() => {
        window.grecaptcha
          .execute(contactForm.dataset.recaptchaSiteKey, {
            action: "contact_form",
          })
          .then((token) => {
            recaptchaToken.value = token;
            contactForm.dataset.captchaReady = "true";
            contactForm.submit();
          });
      });
    });
  }
});
