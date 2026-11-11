import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import { JSDOM } from "jsdom";
import {
  AUTO_DISMISS_MS,
  AUTO_DISMISS_SESSION_KEY,
  DISMISS_KEY,
  canShowInstallPrompt,
  recentlyDismissed,
  registerServiceWorker,
  setupInstallPrompt,
  setupPwa,
} from "../src/lib/pwa.js";

const settings = {
  enabled: true,
  sw: "https://shop.test/store/?jluxe_pwa=sw",
  scope: "/store/",
  installPrompt: true,
  suppressPrompt: false,
  name: "جی‌لوکس",
};

function makeWindow({ mobile = true, standalone = false, registrations = [] } = {}) {
  const dom = new JSDOM("<!doctype html><html><body></body></html>", { url: "https://shop.test/store/" });
  const win = dom.window;
  win.matchMedia = (q) => ({
    matches: q.includes("max-width") ? mobile : q.includes("standalone") ? standalone : false,
  });
  const calls = [];
  Object.defineProperty(win, "isSecureContext", { value: true });
  Object.defineProperty(win.navigator, "serviceWorker", {
    value: {
      register: (url, opts) => {
        calls.push(["register", url, opts]);
        return Promise.resolve({});
      },
      getRegistrations: () => Promise.resolve(registrations),
    },
  });
  return { win, calls };
}

function installEvent(win) {
  const event = new win.Event("beforeinstallprompt", { cancelable: true });
  event.prompted = 0;
  event.prompt = () => {
    event.prompted += 1;
  };
  event.userChoice = Promise.resolve({ outcome: "dismissed" });
  return event;
}

test("registers the service worker with scope and updateViaCache:none", async () => {
  const { win, calls } = makeWindow();
  assert.equal(await registerServiceWorker(win, settings), "registered");
  assert.deepEqual(calls[0], ["register", settings.sw, { scope: "/store/", updateViaCache: "none" }]);
});

test("disabled PWA unregisters only this theme's worker", async () => {
  const log = [];
  const reg = (url) => ({ active: { scriptURL: url }, unregister: () => (log.push(url), Promise.resolve(true)) });
  const { win, calls } = makeWindow({
    registrations: [reg("https://shop.test/store/?jluxe_pwa=sw"), reg("https://shop.test/other-sw.js")],
  });
  assert.equal(await registerServiceWorker(win, { enabled: false }), "unregistered");
  assert.equal(calls.length, 0);
  assert.deepEqual(log, ["https://shop.test/store/?jluxe_pwa=sw"]);
});

test("setupPwa waits for load before registering", async () => {
  const { win, calls } = makeWindow();
  Object.defineProperty(win.document, "readyState", { value: "interactive", configurable: true });
  setupPwa(win, settings);
  assert.equal(calls.length, 0);
  win.dispatchEvent(new win.Event("load"));
  await Promise.resolve();
  assert.equal(calls.length, 1);
});

test("install prompt gating: desktop, standalone, suppressed and recently dismissed are excluded", () => {
  assert.equal(canShowInstallPrompt(makeWindow().win, settings), true);
  assert.equal(canShowInstallPrompt(makeWindow({ mobile: false }).win, settings), false);
  assert.equal(canShowInstallPrompt(makeWindow({ standalone: true }).win, settings), false);
  assert.equal(canShowInstallPrompt(makeWindow().win, { ...settings, suppressPrompt: true }), false);
  assert.equal(canShowInstallPrompt(makeWindow().win, { ...settings, installPrompt: false }), false);
  const { win } = makeWindow();
  win.localStorage.setItem(DISMISS_KEY, String(Date.now() - 5 * 864e5));
  assert.equal(recentlyDismissed(win), true);
  assert.equal(canShowInstallPrompt(win, settings), false);
  win.localStorage.setItem(DISMISS_KEY, String(Date.now() - 31 * 864e5));
  assert.equal(canShowInstallPrompt(win, settings), true);
});

test("banner appears after the delay; «بعداً» and Escape remember the dismissal", async () => {
  const { win } = makeWindow();
  setupInstallPrompt(win, settings, { delay: 5 });
  const event = installEvent(win);
  win.dispatchEvent(event);
  assert.equal(event.defaultPrevented, true);
  assert.equal(win.document.querySelector(".jluxe-pwa-install"), null);
  await new Promise((r) => setTimeout(r, 20));
  const banner = win.document.querySelector(".jluxe-pwa-install");
  assert.ok(banner);
  assert.equal(banner.getAttribute("role"), "region");
  assert.match(banner.textContent, /جی‌لوکس/);
  assert.equal(win.document.activeElement, win.document.body, "the banner never steals focus");
  win.document.dispatchEvent(new win.KeyboardEvent("keydown", { key: "Escape" }));
  assert.equal(win.document.querySelector(".jluxe-pwa-install"), null);
  assert.ok(Number(win.localStorage.getItem(DISMISS_KEY)) > 0);
});

test("install prompt self-dismisses after its timeout, pauses while hovered, and only snoozes this session", async () => {
  const { win } = makeWindow();
  assert.equal(AUTO_DISMISS_MS, 4000, "the default visible time is four seconds");
  setupInstallPrompt(win, settings, { delay: 1, autoDismissAfter: 15 });
  const event = installEvent(win);
  win.dispatchEvent(event);
  await new Promise((resolve) => setTimeout(resolve, 5));
  const banner = win.document.querySelector(".jluxe-pwa-install");
  assert.ok(banner);

  banner.dispatchEvent(new win.Event("pointerenter"));
  await new Promise((resolve) => setTimeout(resolve, 25));
  assert.equal(win.document.querySelector(".jluxe-pwa-install"), banner, "interaction pauses the auto-dismiss timer");

  banner.dispatchEvent(new win.Event("pointerleave"));
  await new Promise((resolve) => setTimeout(resolve, 25));
  assert.equal(win.document.querySelector(".jluxe-pwa-install"), null, "the idle install prompt removes itself after its timeout");
  assert.equal(win.localStorage.getItem(DISMISS_KEY), null, "automatic removal does not pretend the user chose «later» for 30 days");
  assert.equal(win.sessionStorage.getItem(AUTO_DISMISS_SESSION_KEY), "1");
  assert.equal(canShowInstallPrompt(win, settings), false, "the prompt will not reappear on every page during this tab session");
});

test("«نصب» calls the browser prompt once; appinstalled removes the banner", async () => {
  const { win } = makeWindow();
  setupInstallPrompt(win, settings, { delay: 1 });
  const event = installEvent(win);
  win.dispatchEvent(event);
  await new Promise((r) => setTimeout(r, 10));
  win.document.querySelector(".jluxe-pwa-install__primary").click();
  assert.equal(event.prompted, 1);
  assert.equal(win.document.querySelector(".jluxe-pwa-install"), null);

  const second = makeWindow().win;
  setupInstallPrompt(second, settings, { delay: 1 });
  second.dispatchEvent(installEvent(second));
  await new Promise((r) => setTimeout(r, 10));
  second.dispatchEvent(new second.Event("appinstalled"));
  assert.equal(second.document.querySelector(".jluxe-pwa-install"), null);
});

test("on desktop the browser's own install UI is left alone (no preventDefault)", () => {
  const { win } = makeWindow({ mobile: false });
  setupInstallPrompt(win, settings, { delay: 1 });
  const event = installEvent(win);
  win.dispatchEvent(event);
  assert.equal(event.defaultPrevented, false);
});

// ---------------------------------------------------------------- service worker routing
function loadWorker() {
  const listeners = {};
  const config = {
    version: "abc",
    offline: "https://shop.test/store/?jluxe_pwa=offline",
    assetPrefix: "/store/wp-content/themes/zarrin/assets/compiled/assets/",
    bypassPaths: ["/store/basket/", "/store/pay/", "/store/customer-zone/", "/store/wp-admin/", "/store/wp-json/"],
    bypassParams: ["wc-ajax", "add-to-cart", "key", "wc-api"],
  };
  const context = {
    JLUXE_SW: config,
    URL,
    Request: class {
      constructor(url) {
        this.url = url;
      }
    },
    self: {
      location: new URL("https://shop.test/store/?jluxe_pwa=sw"),
      addEventListener: (type, fn) => (listeners[type] = fn),
    },
    caches: {
      store: new Map(),
      async open(name) {
        if (!this.store.has(name)) this.store.set(name, new Map());
        const bucket = this.store.get(name);
        return {
          match: async (req) => bucket.get(typeof req === "string" ? req : req.url),
          put: async (req, res) => bucket.set(req.url, res),
          add: async (req) => bucket.set(req.url, { body: "offline-page", ok: true }),
          keys: async () => [...bucket.keys()].map((url) => ({ url })),
          delete: async (req) => bucket.delete(req.url),
        };
      },
    },
    fetch: (request) =>
      context.online
        ? Promise.resolve({ ok: true, type: "basic", body: "net:" + request.url, clone() { return this; } })
        : Promise.reject(new Error("offline")),
    online: false,
  };
  vm.runInNewContext(fs.readFileSync(new URL("../assets/pwa/sw.js", import.meta.url), "utf8"), context);
  const route = (url, { method = "GET", mode = "no-cors" } = {}) => {
    let handled = false;
    listeners.fetch({
      request: { url, method, mode },
      respondWith: (p) => {
        handled = p;
        Promise.resolve(p).catch(() => {});
      },
    });
    return Boolean(handled);
  };
  const respond = (url, init = {}) => {
    let result;
    listeners.fetch({ request: { url, method: "GET", mode: "no-cors", ...init }, respondWith: (p) => (result = p) });
    return result;
  };
  return { route, respond, listeners, context };
}

test("SW never intercepts cart, checkout, account, admin, REST, stateful queries, POST or cross-origin", () => {
  const { route } = loadWorker();
  const nav = { mode: "navigate" };
  for (const url of [
    "https://shop.test/store/basket/",
    "https://shop.test/store/pay/order-received/12/?key=wc_order_x",
    "https://shop.test/store/customer-zone/orders/",
    "https://shop.test/store/wp-admin/",
    "https://shop.test/store/wp-json/wc/store/v1/cart",
    "https://shop.test/store/?wc-ajax=get_refreshed_fragments",
    "https://shop.test/store/product/x/?add-to-cart=5",
    "https://shop.test/store/?wc-api=zarinpal",
    "https://shop.test/store/wp-admin/admin-ajax.php",
    "https://cdn.other.test/store/app.js",
  ]) {
    assert.equal(route(url, nav), false, url);
  }
  assert.equal(route("https://shop.test/store/", { method: "POST", mode: "navigate" }), false);
});

test("SW handles page navigations (offline fallback) and hashed theme assets only", () => {
  const { route } = loadWorker();
  assert.equal(route("https://shop.test/store/", { mode: "navigate" }), true);
  assert.equal(route("https://shop.test/store/product/kettle/", { mode: "navigate" }), true);
  assert.equal(route("https://shop.test/store/wp-content/themes/zarrin/assets/compiled/assets/main-DE5jfu45.js"), true);
  assert.equal(route("https://shop.test/store/wp-content/uploads/2026/09/kettle.webp"), false, "uploads are not cached");
  assert.equal(route("https://shop.test/store/wp-content/themes/zarrin/style.css?ver=1"), false);
  assert.equal(route("https://shop.test/store/wp-includes/js/jquery/jquery.min.js"), false);
});

test("offline navigation shows the precached offline page; online navigation is never stored", async () => {
  const { respond, listeners, context } = loadWorker();
  let installed;
  listeners.install({ waitUntil: (p) => (installed = p) });
  context.self.skipWaiting = () => {};
  await installed;
  const offline = await respond("https://shop.test/store/shop/", { mode: "navigate" });
  assert.equal(offline.body, "offline-page");

  context.online = true;
  const live = await respond("https://shop.test/store/shop/", { mode: "navigate" });
  assert.equal(live.body, "net:https://shop.test/store/shop/");
  const stored = [...context.caches.store.values()].flatMap((bucket) => [...bucket.keys()]);
  assert.deepEqual(stored, ["https://shop.test/store/?jluxe_pwa=offline"], "no HTML page is ever cached");

  const asset = "https://shop.test/store/wp-content/themes/zarrin/assets/compiled/assets/main-DE5jfu45.js";
  await respond(asset);
  context.online = false;
  assert.equal((await respond(asset)).body, "net:" + asset, "hashed asset served from cache when offline");
});

test("disabling also clears this theme's caches from the page side", async () => {
  const { win } = makeWindow({ registrations: [] });
  const deleted = [];
  win.caches = { keys: async () => ["jluxe-static-a", "jluxe-offline-a", "other-plugin"], delete: async (k) => deleted.push(k) };
  await registerServiceWorker(win, { enabled: false });
  assert.deepEqual(deleted, ["jluxe-static-a", "jluxe-offline-a"]);
});
