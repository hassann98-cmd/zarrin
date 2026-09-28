import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import {
  normalizeDigits,
  normalizePhone,
  buildFilterUrl,
} from "../src/lib/input.js";
import {
  restUrl,
  siteUrl,
  siteLink,
  getSession,
  restRequest,
  dedupeGet,
  resetDedupeGet,
} from "../src/lib/api.js";

const root = "https://shop.test/store/";
function settings() {
  globalThis.window = {
    location: { href: root + "customer-zone/" },
    JLuxeThemeSettings: {
      urls: {
        home: root,
        shop: root + "catalog/",
        dashboard: root + "members/",
        lost_password: root + "customer-zone/lost-password/",
      },
      rest: {
        root: root + "wp-json/jluxe/v1/",
        sessionUrl: root + "wp-admin/admin-ajax.php",
      },
      auth: { isLoggedIn: false },
    },
  };
}
settings();

test("R12 Persian/Arabic digits and strict mobile normalization match PHP", () => {
  assert.equal(normalizeDigits("کد ۱۲۳۴۵۶ / ١٢٣٤٥٦"), "کد 123456 / 123456");
  for (const phone of [
    "09120000000",
    "۰۹۱۲۰۰۰۰۰۰۰",
    "٠٩١٢٠٠٠٠٠٠٠",
    "+98 (912) 000-0000",
    "00989120000000",
    "989120000000",
    "9120000000",
  ]) {
    assert.equal(normalizePhone(phone), "9120000000");
  }
  for (const phone of [
    "abc09120000000",
    "12309120000000",
    "009909120000000",
    "9120000000extra",
    "۱۲۳۴",
    "02112345678",
  ])
    assert.equal(normalizePhone(phone), "");
});

test("R09 REST resolver supports a subdirectory and pretty permalinks", () => {
  assert.equal(
    restUrl("/auth/login", root + "wp-json/jluxe/v1/"),
    root + "wp-json/jluxe/v1/auth/login",
  );
});

test("R09 REST resolver preserves plain rest_route URLs", () => {
  const url = new URL(
    restUrl("assistant/ticket", root + "?rest_route=/jluxe/v1/&lang=fa"),
  );
  assert.equal(url.pathname, "/store/");
  assert.equal(
    url.searchParams.get("rest_route"),
    "/jluxe/v1/assistant/ticket",
  );
  assert.equal(url.searchParams.get("lang"), "fa");
});

test("R09 configured account links and site-relative navigation retain installation path", () => {
  settings();
  assert.equal(siteUrl("dashboard"), root + "members/");
  assert.equal(
    siteLink("/guide/?section=payment"),
    root + "guide/?section=payment",
  );
  assert.equal(siteLink("/store/guide/"), root + "guide/");
  assert.equal(
    siteLink("https://other.test/guide/"),
    "https://other.test/guide/",
  );
  assert.equal(siteLink("#reviews"), "#reviews");
});

test("R87 raw route slugs follow the addresses configured in theme settings", () => {
  settings();
  window.JLuxeThemeSettings.urls.track_order = root + "order-status/";
  assert.equal(siteLink("/track-order/"), root + "order-status/");
  assert.equal(siteLink("/track-order"), root + "order-status/");
  assert.equal(siteLink("/store/track-order/"), root + "order-status/");
  assert.equal(siteLink("/shop/"), root + "catalog/");
  assert.equal(siteLink("/%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87/"), root + "catalog/");
  // Query strings, deeper paths and unknown slugs keep the old behaviour.
  assert.equal(siteLink("/track-order/?id=5"), root + "track-order/?id=5");
  assert.equal(siteLink("/shop/rings/"), root + "shop/rings/");
  assert.equal(siteLink("/faq/"), root + "faq/");
  // No configured address: fall back to the installation-relative path.
  delete window.JLuxeThemeSettings.urls.track_order;
  assert.equal(siteLink("/track-order/"), root + "track-order/");
  settings();
});

test("R17 category AND brand AND other filters are retained while pagination resets", () => {
  const url = new URL(
    buildFilterUrl(
      root + "catalog/",
      root + "catalog/page/4/?orderby=price&on_sale=1&filter_color=red&paged=4",
      {
        product_cat: "rings",
        product_brand: "zarrin",
        min_price: "1000000",
        max_price: "2000000",
        filter_stock: "instock",
      },
    ),
  );
  assert.equal(url.pathname, "/store/catalog/");
  for (const [key, value] of Object.entries({
    product_cat: "rings",
    product_brand: "zarrin",
    min_price: "1000000",
    max_price: "2000000",
    filter_stock: "instock",
    on_sale: "1",
    orderby: "price",
    filter_color: "red",
  }))
    assert.equal(url.searchParams.get(key), value);
  assert.equal(url.searchParams.has("paged"), false);
});

test("R17 explicitly clearing a filter does not restore its previous value", () => {
  const url = new URL(
    buildFilterUrl(
      root + "?post_type=product",
      root + "?post_type=product&on_sale=1",
      { product_cat: "", on_sale: "" },
    ),
  );
  assert.equal(url.searchParams.get("post_type"), "product");
  assert.equal(url.searchParams.has("on_sale"), false);
  assert.equal(url.searchParams.has("product_cat"), false);
});

// Run the ACTUAL production submit callback, not a duplicate implementation of its arithmetic.
const legacy = fs.readFileSync(
  new URL("../assets/js/woocommerce.js", import.meta.url),
  "utf8",
);
function submitFilters(fields) {
  const marker =
    'jluxeFilterForm.addEventListener("submit", function (event) {';
  const start = legacy.indexOf(marker);
  const end = legacy.indexOf("\n\t\t});", start);
  assert.ok(start >= 0 && end > start, "production filter callback exists");
  const body = legacy.slice(start + marker.length, end);
  const window = {
    JLuxeThemeSettings: { shopUrl: root + "catalog/" },
    location: { href: root + "catalog/page/3/?orderby=price" },
  };
  const form = {
    querySelector: (selector) =>
      fields[selector.match(/name="([^"]+)"/)[1]] ?? null,
  };
  vm.runInNewContext(`(function(event){${body}})({preventDefault(){}})`, {
    window,
    jluxeFilterForm: form,
    JLuxeStorefrontUtils: { normalizeDigits, buildFilterUrl },
  });
  return window.location.href;
}
const input = (value) => ({
  value,
  validity: "",
  setCustomValidity(message) {
    this.validity = message;
  },
  reportValidity() {},
});

test("R07 production price submit keeps native IRT/IRR amounts, including Persian input", () => {
  const url = new URL(
    submitFilters({
      min_price: input("۱٬۰۰۰٬۰۰۰"),
      max_price: input("2000000"),
      filter_cat: { value: "rings" },
      filter_brand: { value: "zarrin" },
      filter_stock: { checked: true },
      on_sale: { checked: true },
    }),
  );
  assert.equal(url.searchParams.get("min_price"), "1000000");
  assert.equal(url.searchParams.get("max_price"), "2000000");
  assert.equal(url.searchParams.get("product_cat"), "rings");
  assert.equal(url.searchParams.get("product_brand"), "zarrin");
  assert.equal(url.searchParams.get("on_sale"), "1");
});

test("R07 reversed price range is rejected rather than navigated", () => {
  const maximum = input("100");
  const url = submitFilters({ min_price: input("200"), max_price: maximum });
  assert.equal(url, root + "catalog/page/3/?orderby=price");
  assert.notEqual(maximum.validity, "");
});

test("R11 cookie identity is refreshed even when page HTML said guest", async () => {
  settings();
  const calls = [];
  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      json: async () =>
        url.endsWith("admin-ajax.php")
          ? {
              success: true,
              data: {
                restNonce: "fresh-user-nonce",
                auth: { isLoggedIn: true },
              },
            }
          : { reply: "ok" },
    };
  };
  await getSession(true);
  assert.deepEqual(await restRequest("assistant", { messages: [] }), {
    reply: "ok",
  });
  assert.equal(calls.length, 2);
  assert.equal(calls[0].options.cache, "no-store");
  assert.equal(calls[1].options.headers["X-WP-Nonce"], "fresh-user-nonce");
  assert.equal(calls[1].options.credentials, "same-origin");
});

test("R11 guests send no fake authenticated nonce", async () => {
  const calls = [];
  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      json: async () =>
        url.endsWith("admin-ajax.php")
          ? {
              success: true,
              data: { restNonce: "", auth: { isLoggedIn: false } },
            }
          : { reply: "guest" },
    };
  };
  await getSession(true);
  await restRequest("assistant", {});
  assert.equal(calls.at(-1).options.headers["X-WP-Nonce"], undefined);
});

test("R11 stale REST nonce is refreshed and retried only once", async () => {
  let sessions = 0,
    requests = 0;
  const nonces = [];
  globalThis.fetch = async (url, options) => {
    if (url.endsWith("admin-ajax.php"))
      return {
        ok: true,
        json: async () => ({
          success: true,
          data: { restNonce: `nonce-${++sessions}` },
        }),
      };
    requests++;
    nonces.push(options.headers["X-WP-Nonce"]);
    return {
      ok: requests > 1,
      json: async () =>
        requests > 1 ? { reply: "ok" } : { code: "rest_cookie_invalid_nonce" },
    };
  };
  await getSession(true);
  await restRequest("assistant", {});
  assert.equal(sessions, 2);
  assert.equal(requests, 2);
  assert.deepEqual(nonces, ["nonce-1", "nonce-2"]);
});

test("R09/R11 public login uses the generated endpoint without a private session request", async () => {
  const calls = [];
  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    return { ok: true, json: async () => ({ success: true }) };
  };
  await restRequest(
    "auth/login",
    { login: "example", password: "not-real" },
    { authenticated: false },
  );
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, root + "wp-json/jluxe/v1/auth/login");
  assert.equal(calls[0].options.headers["X-WP-Nonce"], undefined);
});

test("R05 drawer mutations are serialized and expose server failures", async () => {
  settings();
  window.JLuxeThemeSettings.cart = {
    ajaxUrl: root + "wp-admin/admin-ajax.php",
    nonce: "cart-nonce",
  };
  const { cartRequest } = await import("../src/lib/use-cart.js");
  let active = 0,
    maximum = 0;
  const sequence = [];
  globalThis.fetch = async (url, options) => {
    active++;
    maximum = Math.max(maximum, active);
    sequence.push(options.body.get("op"));
    await new Promise((resolve) => setTimeout(resolve, 5));
    active--;
    return {
      ok: false,
      json: async () => ({ success: false, data: { message: "Stock limit" } }),
    };
  };
  const results = await Promise.all([
    cartRequest("update_qty", { key: "one", qty: "2" }),
    cartRequest("remove", { key: "two" }),
  ]);
  assert.equal(maximum, 1);
  assert.deepEqual(sequence, ["update_qty", "remove"]);
  assert.ok(results.every((result) => result.error === "Stock limit"));
});

test("Payment selection survives WooCommerce checkout fragment updates", () => {
  const start = legacy.indexOf("\tvar jluxeManualPaymentMethod = null;");
  const end = legacy.indexOf("\n\tapplyStepVisibility();", start);
  assert.ok(start >= 0 && end > start);
  let radio = { value: "bank", checked: true };
  let onChange;
  const context = {
    document: {
      addEventListener(type, callback) {
        if (type === "change") onChange = callback;
      },
      getElementById() {
        return { querySelectorAll: () => [radio], querySelector: () => null };
      },
    },
    window: {
      jQuery: (target) => ({
        trigger: () => {
          target.checked = true;
        },
      }),
    },
  };
  vm.runInNewContext(legacy.slice(start, end), context);
  context.clearAutoSelectedPaymentMethod();
  assert.equal(
    radio.checked,
    false,
    "initial automatic gateway choice still clears",
  );
  radio.checked = true;
  radio.matches = () => true;
  onChange({ isTrusted: true, target: radio });
  radio = { value: "bank", checked: true };
  context.clearAutoSelectedPaymentMethod();
  assert.equal(
    radio.checked,
    true,
    "explicit selection is not cleared after fragment replacement",
  );
  radio = { value: "bank", checked: false };
  context.clearAutoSelectedPaymentMethod();
  assert.equal(radio.checked, true, "explicit choice can also be restored");
});

test("dedupeGet shares one fetch between concurrent same-key callers and caches within ttl", async () => {
  resetDedupeGet();
  let calls = 0;
  const fetcher = async () => {
    calls += 1;
    return { items: ["a", "b"] };
  };
  const [r1, r2, r3] = await Promise.all([
    dedupeGet("home:data", fetcher),
    dedupeGet("home:data", fetcher),
    dedupeGet("home:data", fetcher),
  ]);
  assert.equal(calls, 1, "concurrent same-key callers share a single fetch");
  assert.deepEqual(r1, r2);
  assert.deepEqual(r3, { items: ["a", "b"] });

  assert.deepEqual(await dedupeGet("home:data", fetcher), {
    items: ["a", "b"],
  });
  assert.equal(calls, 1, "resolved value is cached within ttl");

  const other = await dedupeGet("other:key", async () => {
    calls += 1;
    return { ok: true };
  });
  assert.deepEqual(other, { ok: true });
  assert.equal(calls, 2, "a different key triggers its own fetch");

  resetDedupeGet();
  await dedupeGet("home:data", fetcher);
  assert.equal(calls, 3, "cache reset forces a fresh fetch");
});

test("dedupeGet does not cache rejections", async () => {
  resetDedupeGet();
  let calls = 0;
  const failing = async () => {
    calls += 1;
    throw new Error("network");
  };
  await assert.rejects(dedupeGet("flaky", failing));
  await assert.rejects(dedupeGet("flaky", failing));
  assert.equal(calls, 2, "failed requests are retried, never cached");
});
