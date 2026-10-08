import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import { gzipSync } from "node:zlib";
import { setImmediate as nextTurn } from "node:timers/promises";
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { JSDOM } from "jsdom";
import { getSession } from "../src/lib/api.js";
import {
  mountIsland,
  startSmoothScrolling,
  shouldSmoothScroll,
  markLiteDevice,
} from "../src/lib/islands.js";

const base = "https://shop.test/store/";
const originalFetch = globalThis.fetch;
const originalError = console.error;
const originalWarn = console.warn;
let moduleId = 0;
const disposers = [];
afterEach(async () => {
  for (const dispose of disposers.splice(0).reverse()) await dispose();
  globalThis.fetch = originalFetch;
  console.error = originalError;
  console.warn = originalWarn;
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.IS_REACT_ACT_ENVIRONMENT;
});
function settings(win = { location: { href: base } }) {
  globalThis.window = win;
  win.JLuxeThemeSettings = {
    urls: { home: base, cart: base + "basket/", shop: base + "catalog/" },
    rest: {
      root: base + "?rest_route=/jluxe/v1/",
      sessionUrl: base + "wp-admin/admin-ajax.php",
    },
    cart: { ajaxUrl: base + "wp-admin/admin-ajax.php", nonce: "cached-nonce" },
  };
  return win;
}
function dom(markup = '<div id="root"></div>') {
  const instance = new JSDOM(markup, {
    url: base,
    pretendToBeVisual: true,
    runScripts: "outside-only",
  });
  settings(instance.window);
  globalThis.document = instance.window.document;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  disposers.push(() => instance.window.close());
  return instance;
}
const json = (body, status = 200) =>
  new Response(JSON.stringify(body), { status });
const cartData = (itemCount) => ({
  items: [],
  itemCount,
  subtotalHtml: "",
  coupons: [],
  freeShipping: null,
  cartUrl: base + "basket/",
  checkoutUrl: base + "checkout/",
});
const freshCart = () => import(`../src/lib/use-cart.js?case=${++moduleId}`);

test("R22 expired cart nonce refreshes once, then replays only the pre-mutation rejection", async () => {
  settings();
  const { cartRequest } = await freshCart();
  const requests = [];
  globalThis.fetch = async (url, options) => {
    const params = Object.fromEntries(options.body);
    requests.push(params);
    assert.equal(options.cache, "no-store");
    assert.equal(options.credentials, "same-origin");
    if (params.action === "jluxe_session")
      return json({ success: true, data: { cartNonce: "fresh-nonce" } });
    assert.equal(params.action, "jluxe_cart");
    assert.equal(params["add-to-cart"], undefined);
    assert.equal(params.op, "add");
    if (params.nonce === "cached-nonce")
      return json(
        { success: false, data: { code: "jluxe_cart_invalid_nonce" } },
        403,
      );
    assert.equal(params.nonce, "fresh-nonce");
    return json({ success: true, data: cartData(1) });
  };
  const result = await cartRequest("add", {
    product_id: "51",
    quantity: "1",
    action: "ignored",
    op: "remove",
    nonce: "ignored",
    "add-to-cart": "51",
  });
  assert.equal(result.error, undefined);
  assert.equal(result.snapshot.itemCount, 1);
  assert.deepEqual(
    requests.map((entry) => entry.action),
    ["jluxe_cart", "jluxe_session", "jluxe_cart"],
  );
});

test("R22 a second nonce rejection stops, rather than retrying indefinitely", async () => {
  settings();
  const { cartRequest } = await freshCart();
  let calls = 0;
  globalThis.fetch = async (url, options) => {
    calls++;
    return options.body.get("action") === "jluxe_session"
      ? json({ success: true, data: { cartNonce: "fresh-but-invalid" } })
      : json(
          {
            success: false,
            data: { code: "jluxe_cart_invalid_nonce", message: "Expired" },
          },
          403,
        );
  };
  assert.equal(
    (await cartRequest("add", { product_id: "51" })).error,
    "Expired",
  );
  assert.equal(calls, 3);
});

test("R22 missing refresh nonce does not replay a cart mutation", async () => {
  settings();
  const { cartRequest } = await freshCart();
  let calls = 0;
  globalThis.fetch = async (url, options) => {
    calls++;
    return options.body.get("action") === "jluxe_session"
      ? json({ success: true, data: {} })
      : json(
          { success: false, data: { code: "jluxe_cart_invalid_nonce" } },
          403,
        );
  };
  assert.ok((await cartRequest("remove", { key: "test" })).error);
  assert.equal(calls, 2);
});

test("R22 network, malformed JSON, 5xx and stock rejections are never automatically replayed", async () => {
  for (const failure of [
    () => {
      throw new TypeError("Network failed after an unknown commit");
    },
    () => new Response("not JSON", { status: 200 }),
    () =>
      json({ success: false, data: { message: "Server unavailable" } }, 500),
    () => json({ success: false, data: { message: "Stock limit" } }, 400),
  ]) {
    settings();
    const { cartRequest } = await freshCart();
    let calls = 0;
    globalThis.fetch = async () => {
      calls++;
      return failure();
    };
    assert.ok((await cartRequest("add", { product_id: "51" })).error);
    assert.equal(calls, 1);
    globalThis.fetch = async () => json({ success: true, data: cartData(2) });
    assert.equal(
      (await cartRequest("get")).snapshot.itemCount,
      2,
      "queue still works after a failed mutation",
    );
  }
});

test("R22 simultaneous forced session refreshes share one in-flight request", async () => {
  settings();
  let calls = 0,
    release;
  globalThis.fetch = () => {
    calls++;
    return new Promise((resolve) => {
      release = resolve;
    });
  };
  const first = getSession(true);
  const second = getSession(true);
  const third = getSession();
  assert.equal(first, second);
  assert.equal(second, third);
  assert.equal(calls, 1);
  release(json({ success: true, data: { cartNonce: "shared" } }));
  assert.deepEqual(
    await Promise.all([first, second, third]),
    Array(3).fill({ cartNonce: "shared" }),
  );
});

test("R22 three cart consumers refresh once per event and ignore an older read after a newer snapshot", async () => {
  const instance = dom();
  const { u: useCart } = await freshCart();
  let calls = 0,
    release;
  globalThis.fetch = async () => {
    calls++;
    if (calls === 3)
      return new Promise((resolve) => {
        release = resolve;
      });
    return json({ success: true, data: cartData(calls) });
  };
  function Consumer() {
    const { snapshot } = useCart();
    return React.createElement("output", null, snapshot.itemCount);
  }
  const root = createRoot(document.getElementById("root"));
  disposers.push(() => act(() => root.unmount()));
  await act(async () => {
    root.render(
      React.createElement(
        React.Fragment,
        null,
        ...Array.from({ length: 3 }, (_, i) =>
          React.createElement(Consumer, { key: i }),
        ),
      ),
    );
  });
  assert.equal(calls, 1, "initial read is shared");
  await act(async () => {
    window.dispatchEvent(new window.CustomEvent("jluxe:cart-updated"));
  });
  assert.equal(calls, 2, "one refresh, not one per subscriber");
  await act(async () => {
    window.dispatchEvent(
      new window.CustomEvent("jluxe:cart-updated", {
        detail: { itemCount: 77 },
      }),
    );
  });
  assert.equal(
    calls,
    3,
    "an incomplete snapshot is re-fetched instead of published",
  );
  await act(async () => {
    window.dispatchEvent(
      new window.CustomEvent("jluxe:cart-updated", { detail: cartData(9) }),
    );
  });
  await act(async () => {
    release(json({ success: true, data: cartData(3) }));
  });
  assert.deepEqual(
    [...instance.window.document.querySelectorAll("output")].map(
      (output) => output.textContent,
    ),
    ["9", "9", "9"],
  );
  assert.equal(calls, 3, "complete snapshot does not need a redundant request");
});

const tracker = fs.readFileSync(
  new URL("../assets/js/order-tracking.js", import.meta.url),
  "utf8",
);
const utils = fs.readFileSync(
  new URL("../assets/js/storefront-utils.js", import.meta.url),
  "utf8",
);
function trackingDom() {
  const instance = dom('<div id="jluxe-track-order-app"></div>');
  const win = instance.window;
  const browserErrors = [];
  win.addEventListener("error", (event) => {
    browserErrors.push(event.error?.message || event.message);
  });
  disposers.push(() =>
    assert.deepEqual(
      browserErrors,
      [],
      "tracking script must not leave uncaught browser errors",
    ),
  );
  win.JLuxeOrderTracking = {
    endpoint: base + "?rest_route=/jluxe/v1/order-track",
    sessionUrl: base + "wp-admin/admin-ajax.php",
    storeUrl: base + "catalog/",
    accountUrl: base + "native-account/",
  };
  win.localStorage.setItem(
    "jluxe_track_last_order_number",
    "old-private-order",
  );
  win.eval(utils);
  win.eval(tracker);
  win.document.dispatchEvent(new win.Event("DOMContentLoaded"));
  return instance;
}
function submitTracking(win) {
  win.document.getElementById("jto-order-number").value = "۵۱";
  win.document.getElementById("jto-phone").value = "۰۹۱۲۰۰۰۰۰۰۰";
  win.document
    .getElementById("jto-search-form")
    .dispatchEvent(
      new win.Event("submit", { bubbles: true, cancelable: true }),
    );
}
function trackingResult(win) {
  return new Promise((resolve, reject) => {
    const observer = new win.MutationObserver(() => {
      if (win.document.querySelector("#jto-back-to-search, #jto-retry-btn")) {
        observer.disconnect();
        clearTimeout(timeout);
        resolve();
      }
    });
    const timeout = setTimeout(() => {
      observer.disconnect();
      reject(new Error("Tracking result not rendered"));
    }, 2000);
    observer.observe(win.document.getElementById("jluxe-track-order-app"), {
      childList: true,
      subtree: true,
    });
  });
}
function trackedOrder(access) {
  return {
    success: true,
    data: {
      access,
      order: {
        order_number: "51",
        status_label: "در حال آماده‌سازی",
        total: { formatted: "Secret total" },
        payment_method: "Secret gateway",
      },
      customer: {
        full_name: "Private Customer <img src=x onerror=alert(1)>",
        address: "Private Address",
        phone: "09120000000",
      },
      shipping: {
        tracking_code: "private-tracking-token",
        shipping_company: "پست",
      },
      items: [
        {
          product_name: "Private Product",
          quantity: 1,
          subtotal: { formatted: "Secret price" },
        },
      ],
    },
  };
}

test("R21 public tracking UI omits extra legacy details and sends phone only in a POST body", async () => {
  const { window: win } = trackingDom();
  const calls = [];
  win.fetch = async (url, options) => {
    calls.push({ url, options });
    return url.includes("admin-ajax.php")
      ? json({ success: true, data: { restNonce: "" } })
      : json(trackedOrder("status_only"));
  };
  assert.equal(win.document.getElementById("jto-order-number").value, "");
  const completed = trackingResult(win);
  submitTracking(win);
  submitTracking(win); // Enter while the submit button is busy cannot issue a second search.
  await completed;
  assert.equal(calls.length, 2);
  const last = calls.at(-1);
  assert.equal(last.options.method, "POST");
  assert.equal(last.options.cache, "no-store");
  assert.equal(
    new URL(last.url).searchParams.get("rest_route"),
    "/jluxe/v1/order-track",
  );
  assert.equal(new URL(last.url).searchParams.has("phone"), false);
  assert.deepEqual(JSON.parse(last.options.body), {
    order_number: "51",
    phone: "9120000000",
  });
  const result = win.document.getElementById("jto-result-area");
  assert.doesNotMatch(
    result.textContent,
    /Private|private-tracking-token|Secret/,
  );
  assert.match(result.textContent, /فقط وضعیت سفارش/);
  assert.equal(result.querySelector("a").href, base + "native-account/");
  assert.equal(win.localStorage.length, 0);
});

test("R21 owner view uses a fresh REST nonce, escapes markup and clears private results on pagehide", async () => {
  const { window: win } = trackingDom();
  let nonce;
  win.fetch = async (url, options) => {
    if (url.includes("admin-ajax.php"))
      return json({ success: true, data: { restNonce: "fresh-owner-nonce" } });
    nonce = options.headers["X-WP-Nonce"];
    return json(trackedOrder("owner"));
  };
  const completed = trackingResult(win);
  submitTracking(win);
  await completed;
  assert.equal(nonce, "fresh-owner-nonce");
  assert.match(
    win.document.getElementById("jto-result-area").textContent,
    /Private Customer|Private Product/,
  );
  assert.equal(
    win.document.querySelector("[onerror]"),
    null,
    "customer markup is displayed as text, never executable HTML",
  );
  win.dispatchEvent(new win.Event("pagehide"));
  assert.doesNotMatch(
    win.document.body.textContent,
    /Private|private-tracking-token|Secret/,
  );
  assert.equal(win.document.getElementById("jto-phone").value, "");
  assert.equal(win.localStorage.length, 0);
});

test("R21 a late private response cannot repopulate a page cleared for browser history", async () => {
  const { window: win } = trackingDom();
  let release, started;
  const received = new Promise((resolve) => {
    started = resolve;
  });
  win.fetch = async (url) => {
    if (url.includes("admin-ajax.php"))
      return json({ success: true, data: { restNonce: "fresh" } });
    started();
    return new Promise((resolve) => {
      release = resolve;
    });
  };
  submitTracking(win);
  await received;
  win.dispatchEvent(new win.Event("pagehide"));
  release(json(trackedOrder("owner")));
  await nextTurn();
  assert.equal(win.document.getElementById("jto-result-area").textContent, "");
});

test("R23 a React render crash retains the server's native login fallback", async () => {
  dom(
    '<div id="root" data-jluxe-island="auth-page"><a href="/store/wp-login.php">ورود استاندارد</a></div>',
  );
  const element = document.getElementById("root");
  let errors = 0,
    dispose;
  console.error = () => {};
  document.addEventListener("jluxe:load-error", () => errors++);
  function Broken() {
    throw new Error("fixture render failure");
  }
  await act(async () => {
    dispose = await mountIsland(element, () =>
      Promise.resolve({ default: Broken }),
    );
  });
  disposers.push(() => act(dispose));
  assert.equal(errors, 1);
  assert.equal(
    element.querySelector('[role="alert"] a').href,
    base + "wp-login.php",
  );
  assert.match(element.textContent, /بارگذاری این بخش ممکن نشد/);
});

test("R23 a failed lazy import keeps a working native cart link", async () => {
  dom('<div id="root" data-jluxe-island="mini-cart"></div>');
  console.error = () => {};
  const element = document.getElementById("root");
  await mountIsland(element, () =>
    Promise.reject(new Error("missing deployment chunk")),
  );
  assert.equal(
    element.querySelector('[role="alert"] a').href,
    base + "basket/",
  );
});

test("R23 unsupported animation APIs do not block islands, and reduced motion avoids Lenis", async () => {
  let constructed = 0;
  console.warn = () => {};
  class BrokenLenis {
    constructor() {
      constructed++;
      throw new Error("ResizeObserver unavailable");
    }
  }
  const desktop = (overrides = {}) => ({
    matchMedia: (q) => ({
      matches: q.includes("reduced-motion")
        ? Boolean(overrides.reduced)
        : !overrides.touch,
    }),
    navigator: { connection: { saveData: Boolean(overrides.saveData) } },
    addEventListener() {},
  });
  assert.equal(await startSmoothScrolling(desktop(), BrokenLenis), null);
  assert.equal(constructed, 1, "a desktop tries Lenis and survives its failure");
  await startSmoothScrolling(desktop({ reduced: true }), BrokenLenis);
  await startSmoothScrolling({}, BrokenLenis);
  assert.equal(constructed, 1);
});

test("R88 touch devices and Save-Data never download or start Lenis", async () => {
  const win = (touch, saveData = false, reduced = false) => ({
    matchMedia: (q) => ({
      matches: q.includes("reduced-motion") ? reduced : !touch,
    }),
    navigator: { connection: { saveData } },
    listeners: [],
    addEventListener(type, fn) {
      this.listeners.push(type);
    },
  });
  let loads = 0;
  class FakeLenis {
    constructor(opts) {
      this.opts = opts;
    }
    destroy() {}
  }
  const loader = () => {
    loads++;
    return Promise.resolve(FakeLenis);
  };
  assert.equal(shouldSmoothScroll(win(true)), false);
  assert.equal(await startSmoothScrolling(win(true), loader), null);
  assert.equal(await startSmoothScrolling(win(false, true), loader), null);
  assert.equal(await startSmoothScrolling(win(false, false, true), loader), null);
  assert.equal(loads, 0, "the lazy chunk is never requested on touch / Save-Data / reduced motion");
  const desktop = win(false);
  const lenis = await startSmoothScrolling(desktop, loader);
  assert.equal(loads, 1);
  assert.ok(lenis instanceof FakeLenis);
  assert.deepEqual(lenis.opts, { autoRaf: true });
  assert.deepEqual(desktop.listeners, ["pagehide"], "destroyed on pagehide");
  const failing = await startSmoothScrolling(win(false), () =>
    Promise.reject(new Error("chunk failed")),
  );
  assert.equal(failing, null, "a failed chunk keeps native scrolling");
});

test("R88 Lenis is no longer in the main bundle — it is a lazy chunk", () => {
  const main = fs.readFileSync(new URL("../src/main.js", import.meta.url), "utf8");
  assert.ok(!/^import\s+Lenis/m.test(main), "no static Lenis import in main.js");
  assert.match(main, /import\("lenis"\)/);
  const manifest = JSON.parse(
    fs.readFileSync(new URL("../assets/compiled/manifest.json", import.meta.url), "utf8"),
  );
  const entry = manifest["src/main.js"];
  assert.ok(entry.dynamicImports?.some((k) => /lenis/.test(k)), "lenis is a dynamic import of the entry");
});

test("R169 the shared storefront closure has 11 JS files under the gzip budget while route islands stay lazy", () => {
  const root = new URL("../assets/compiled/", import.meta.url);
  const manifest = JSON.parse(fs.readFileSync(new URL("manifest.json", root), "utf8"));
  const entry = manifest["src/main.js"];
  const initialIslands = [
    "src/islands/Header.js",
    "src/islands/MegaMenu.js",
    "src/islands/MiniCart.js",
    "src/islands/CategoryDrawer.js",
    "src/islands/MobileNav.js",
    "src/islands/AiAssistant.js",
  ];
  const routeIslands = [
    "src/islands/Footer.js",
    "src/islands/ProductDetails.js",
    "src/islands/ShopArchive.js",
    "src/islands/CartCheckout.js",
    "src/islands/AuthPage.jsx",
    "src/islands/CategoriesBrowser.js",
  ];
  const visit = (key, seen) => {
    if (seen.has(key)) return;
    assert.ok(manifest[key], `manifest entry exists for ${key}`);
    seen.add(key);
    for (const dependency of manifest[key].imports ?? []) visit(dependency, seen);
  };
  const closure = new Set();
  visit("src/main.js", closure);
  for (const key of initialIslands) visit(key, closure);

  const jsFiles = [...closure]
    .map((key) => manifest[key]?.file)
    .filter((file) => file?.endsWith(".js"));
  assert.equal(jsFiles.length, 11, `initial home closure: ${jsFiles.join(", ")}`);
  const gzipBytes = jsFiles.reduce(
    (sum, file) => sum + gzipSync(fs.readFileSync(new URL(file, root))).length,
    0,
  );
  assert.ok(gzipBytes >= 100_000 && gzipBytes <= 100_751, `gzip closure is ${gzipBytes} bytes`);

  const staticClosure = new Set();
  visit("src/main.js", staticClosure);
  for (const key of routeIslands) {
    assert.ok(entry.dynamicImports?.includes(key), `${key} remains a dynamic island entry`);
    assert.ok(!closure.has(key), `${key} is outside the initial home closure`);
  }
  assert.ok(staticClosure.has("src/main.js"));
  assert.ok(routeIslands.every((key) => !staticClosure.has(key)));
});

// Run both ACTUAL classic form-preparation blocks, not a duplicate implementation.
test("R26 classic product and picker requests strip the native auto-add flag without altering fallback forms", () => {
  const instance = dom(
    '<form class="cart" data-product_id="51"><input name="add-to-cart" value="51"><input name="variation_id" value="52"><input name="attribute_color" value="red"><input name="engraving" value="custom text"><input type="number" name="quantity" value="1" min="1" max="2"><button value="51">Add</button></form>',
  );
  const form = instance.window.document.querySelector("form");
  const source = fs.readFileSync(
    new URL("../assets/js/woocommerce.js", import.meta.url),
    "utf8",
  );
  const positions = [
    ...source.matchAll(/var formData = new FormData\(form\);/g),
  ].map((match) => match.index);
  assert.equal(positions.length, 2);
  const cart = { nonce: "fresh", ajaxUrl: base + "wp-admin/admin-ajax.php" };
  for (const start of positions) {
    const end = source.indexOf("\n\t\tfetch(", start);
    assert.ok(end > start);
    const result = vm.runInNewContext(
      `(function(){ ${source.slice(start, end)} return formData; })()`,
      {
        FormData: instance.window.FormData,
        form,
        cart,
        cartCfg: cart,
        button: form.querySelector("button"),
        addButton: form.querySelector("button"),
        pickerFromSuggested: false,
        pickerSuggestedContext: "",
      },
    );
    assert.equal(result.has("add-to-cart"), false);
    assert.equal(result.get("action"), "jluxe_cart");
    assert.equal(result.get("product_id"), "51");
    assert.equal(result.get("variation_id"), "52");
    assert.equal(result.get("attribute_color"), "red");
    assert.equal(result.get("engraving"), "custom text");
    assert.equal(result.get("quantity"), "1", "the AJAX protocol submits the real requested quantity, not the stock maximum");
  }
  const pickerStart = positions[1];
  const pickerEnd = source.indexOf("\n\t\tfetch(", pickerStart);
  const suggestedFormData = vm.runInNewContext(
    `(function(){ ${source.slice(pickerStart, pickerEnd)} return formData; })()`,
    {
      FormData: instance.window.FormData,
      form,
      cart,
      addButton: form.querySelector("button"),
      pickerFromSuggested: true,
      pickerSuggestedContext: "500",
    },
  );
  assert.equal(suggestedFormData.get("pa_context_id"), "500", "a suggested variation carries its original modal context for a server refresh");
  assert.equal(suggestedFormData.get("quantity"), "1", "the picker also preserves the actual quantity field");
  assert.equal(
    form.querySelector('[name="add-to-cart"]').value,
    "51",
    "the native/no-JS form is unchanged",
  );
});

test("R88 low-memory or Save-Data devices get html.jluxe-lite; others do not", () => {
  const make = (navigator) => {
    const d = new JSDOM("<!doctype html><html><body></body></html>");
    disposers.push(() => d.window.close());
    Object.defineProperty(d.window, "navigator", { value: navigator });
    return d.window;
  };
  for (const [nav, expected] of [
    [{ deviceMemory: 2 }, true],
    [{ deviceMemory: 1 }, true],
    [{ deviceMemory: 4 }, false],
    [{ connection: { saveData: true }, deviceMemory: 8 }, true],
    [{}, false],
  ]) {
    const win = make(nav);
    assert.equal(markLiteDevice(win), expected, JSON.stringify(nav));
    assert.equal(win.document.documentElement.classList.contains("jluxe-lite"), expected);
  }
  assert.equal(markLiteDevice({}), false, "never throws without a document");
});

test("R88 CSS budget: blur is capped on touch, removed in lite mode, and the dead !container utility is gone", () => {
  const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
  assert.ok(!css.includes(".\\!container"), "Tailwind picked up `if (!container)` from JS — dead rules removed");
  assert.match(css, /@media \(hover: none\) and \(pointer: coarse\) \{\s*\.backdrop-blur-xl,\s*\.backdrop-blur-md \{\s*--tw-backdrop-blur: blur\(8px\);/);
  assert.match(css, /html\.jluxe-lite \.backdrop-blur-xl,[\s\S]*?backdrop-filter: none;/);
  assert.match(css, /@media \(prefers-reduced-transparency: reduce\)/);
  const important = (css.match(/!important/g) || []).length;
  assert.ok(important <= 83, `!important budget: ${important} (was 89 in the source before R88); new ones need a reason`);
  const style = fs.readFileSync(new URL("../style.css", import.meta.url), "utf8");
  assert.ok(!/jluxe-mobile-price-card \{[^}]*backdrop-filter/.test(style), "no blur behind the 96%-opaque sticky price card");
  const header = fs.readFileSync(new URL("../header.php", import.meta.url), "utf8");
  assert.match(header, /jluxe-header-bar border-b/);
});
