// Service worker: shows a branded offline page when a navigation fails.
// It never caches portal pages or form posts; only the offline page and the
// few static files it needs are stored.
const VERSION = "ait-offline-v3";
const OFFLINE_URL = "errors/offline.html";
const PRECACHE = [
  OFFLINE_URL,
  "assets/css/public.css",
  "assets/css/bootstrap-icons.min.css",
  "assets/css/fonts/bootstrap-icons.woff2",
  "assets/js/theme.js",
  "assets/js/error-page.js",
  "assets/images/logo/ait-logo-240.webp",
  "assets/images/illustrations/library.svg",
  "assets/images/favicon/ait.ico",
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  // Portals and anything authenticated go straight to the network, untouched:
  // a service-worker fetch can drop the SameSite=Strict session cookie after a form post.
  const path = new URL(request.url).pathname.replace(new URL(self.registration.scope).pathname, "");
  if (/^(admin|teachers|staff|student|errors\/404)|^(dashboard|student-dashboard|login|log-in|student-login|registration|register|logout)\b|\.php$/.test(path) && path !== OFFLINE_URL) {
    return;
  }

  if (request.mode === "navigate") {
    // Network first, always: pages are never served from this cache.
    // On failure, redirect to the offline page's own URL so its relative asset paths resolve.
    const offline = new URL(OFFLINE_URL, self.registration.scope).href;
    event.respondWith(
      fetch(request).catch(() => (request.url === offline ? caches.match(OFFLINE_URL) : Response.redirect(offline, 302))),
    );
    return;
  }

  // The offline page's own assets (versioned URLs ignored) when the network is gone.
  const url = new URL(request.url);
  if (url.origin === self.location.origin) {
    event.respondWith(fetch(request).catch(() => caches.match(request, { ignoreSearch: true })));
  }
});
