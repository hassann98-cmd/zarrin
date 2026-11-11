/*
 * JLUXE service worker (R88). JLUXE_SW is prepended by inc/pwa.php:
 *   { version, offline, assetPrefix, bypassPaths[], bypassParams[] }
 *
 * Rules — deliberately conservative for a WooCommerce store:
 *  - HTML is NEVER cached. Navigations go to the network; only when the network
 *    fails is the small offline page shown. Prices, stock, cart and login state
 *    therefore can never be stale.
 *  - Only hashed build files under assetPrefix are cached (cache-first). Their
 *    names change on every build, so a cached copy is always the right copy.
 *  - Non-GET, cross-origin, cart/checkout/account/admin/REST and any request with
 *    a stateful query parameter are not touched at all (no respondWith).
 */
/* global JLUXE_SW */
const PREFIX = "jluxe-";
const STATIC_CACHE = PREFIX + "static-" + JLUXE_SW.version;
const OFFLINE_CACHE = PREFIX + "offline-" + JLUXE_SW.version;
const STATIC_LIMIT = 80;

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(OFFLINE_CACHE)
      .then((cache) => cache.add(new Request(JLUXE_SW.offline, { cache: "reload" })))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.indexOf(PREFIX) === 0 && key !== STATIC_CACHE && key !== OFFLINE_CACHE)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

function isBypassed(url) {
  for (const path of JLUXE_SW.bypassPaths) {
    if (url.pathname === path || url.pathname.indexOf(path) === 0 || url.pathname + "/" === path) {
      return true;
    }
  }
  for (const param of JLUXE_SW.bypassParams) {
    if (url.searchParams.has(param)) {
      return true;
    }
  }
  return /\/admin-ajax\.php$/.test(url.pathname);
}

async function trimStaticCache() {
  const cache = await caches.open(STATIC_CACHE);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - STATIC_LIMIT; i += 1) {
    await cache.delete(keys[i]);
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(STATIC_CACHE);
  const hit = await cache.match(request);
  if (hit) {
    return hit;
  }
  const response = await fetch(request);
  if (response && response.ok && response.type === "basic") {
    await cache.put(request, response.clone());
    trimStaticCache();
  }
  return response;
}

async function networkWithOfflineFallback(event) {
  try {
    return await fetch(event.request);
  } catch (error) {
    const cache = await caches.open(OFFLINE_CACHE);
    const offline = await cache.match(JLUXE_SW.offline);
    if (offline) {
      return offline;
    }
    throw error;
  }
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") {
    return;
  }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || isBypassed(url)) {
    return;
  }
  if (request.mode === "navigate") {
    event.respondWith(networkWithOfflineFallback(event));
    return;
  }
  if (url.pathname.indexOf(JLUXE_SW.assetPrefix) === 0 && !url.search) {
    event.respondWith(cacheFirst(request));
  }
});

self.addEventListener("message", (event) => {
  if (event.data === "jluxe-pwa-skip-waiting") {
    self.skipWaiting();
  }
});
