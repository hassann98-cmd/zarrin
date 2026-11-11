import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(
  new URL("../assets/js/storefront-personalization.js", import.meta.url),
  "utf8",
);

function response(payload, ok = true) {
  return { ok, json: async () => payload };
}

function boot({ html = "", settings = {}, storage = {}, fetch = async () => response({}), url = "https://shop.test/product/sample/" } = {}) {
  const dom = new JSDOM(`<!doctype html><html><body>${html}</body></html>`, {
    url,
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  const { window } = dom;
  window.jluxePersonalizationSettings = settings;
  window.fetch = fetch;
  for (const [key, value] of Object.entries(storage)) {
    window.localStorage.setItem(key, JSON.stringify(value));
  }
  vm.runInContext(source, dom.getInternalVMContext());
  return dom;
}

const wishlistMarkup = `
  <button class="cp3-wishline" data-jluxe-wishlist-toggle="314" data-jluxe-wishlist-inactive-label="افزودن به علاقه‌مندی‌ها" data-jluxe-wishlist-active-label="حذف از علاقه‌مندی‌ها" aria-pressed="false"><svg fill="none"></svg><span>علاقه‌مندی‌ها</span></button>
  <div class="cp3-fabs">
    <button class="cp3-fab cp3-heart" data-jluxe-wishlist-toggle="314" data-jluxe-wishlist-inactive-label="افزودن به علاقه‌مندی‌ها" data-jluxe-wishlist-active-label="حذف از علاقه‌مندی‌ها" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها"><svg fill="none"></svg></button>
    <button class="cp3-fab" data-jluxe-share aria-label="اشتراک‌گذاری"><svg fill="none"></svg></button>
  </div>
  <button data-jluxe-wishlist-toggle="999" data-jluxe-wishlist-inactive-label="افزودن به علاقه‌مندی‌ها" data-jluxe-wishlist-active-label="حذف از علاقه‌مندی‌ها" aria-pressed="false"><svg fill="none"></svg></button>
`;

const tick = () => new Promise((resolve) => setTimeout(resolve, 20));

test("guest wishlist remains local, updates every heart, and preserves non-wishlist actions", async (t) => {
  const dom = boot({
    html: wishlistMarkup,
    storage: { jluxe_wishlist: ["314"] },
  });
  t.after(() => dom.window.close());
  const { document, localStorage } = dom.window;
  const sameProductButtons = [...document.querySelectorAll('[data-jluxe-wishlist-toggle="314"]')];
  const pageHeart = document.querySelector(".cp3-fabs .cp3-heart");
  const wishline = document.querySelector(".cp3-wishline");
  const unrelated = document.querySelector('[data-jluxe-wishlist-toggle="999"]');
  const shareButton = document.querySelector("[data-jluxe-share]");

  assert.equal(sameProductButtons.length, 2);
  assert.equal(pageHeart.closest(".cp3-fabs").querySelectorAll("[data-jluxe-wishlist-toggle]").length, 1);
  for (const button of sameProductButtons) {
    assert.equal(button.getAttribute("aria-pressed"), "true");
    assert.equal(button.querySelector("svg").getAttribute("fill"), "currentColor");
    assert.equal(button.classList.contains("text-boom-sale"), true);
  }
  assert.equal(pageHeart.getAttribute("aria-label"), "حذف از علاقه‌مندی‌ها");
  assert.equal(unrelated.getAttribute("aria-pressed"), "false");
  assert.equal(shareButton.getAttribute("aria-pressed"), null);
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist_guest")), ["314"]);

  pageHeart.querySelector("svg").dispatchEvent(new dom.window.MouseEvent("click", { bubbles: true }));
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist")), []);
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist_guest")), []);
  for (const button of sameProductButtons) {
    assert.equal(button.getAttribute("aria-pressed"), "false");
    assert.equal(button.querySelector("svg").getAttribute("fill"), "none");
    assert.equal(button.classList.contains("text-boom-sale"), false);
  }

  wishline.querySelector("span").dispatchEvent(new dom.window.MouseEvent("click", { bubbles: true }));
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist")), ["314"]);
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist_guest")), ["314"]);
  for (const button of sameProductButtons) assert.equal(button.getAttribute("aria-pressed"), "true");
});

test("login merges the legacy guest localStorage list with the account wishlist and persists the union", async (t) => {
  const calls = [];
  const dom = boot({
    html: wishlistMarkup,
    settings: { isLoggedIn: true, sessionUrl: "/admin-ajax.php", wishlistUrl: "/wp-json/jluxe/v1/wishlist" },
    storage: { jluxe_wishlist: ["303"], jluxe_wishlist_guest: ["101", "202"] },
    fetch: async (url, options = {}) => {
      if (String(url).includes("admin-ajax.php")) {
        calls.push({ url, action: options.body.get("action") });
        return response({ success: true, data: { restNonce: "private-nonce", auth: { isLoggedIn: true, userId: 42 } } });
      }
      if (options.method === "GET") {
        calls.push({ url, method: "GET", nonce: options.headers["X-WP-Nonce"] });
        return response({ ids: ["202", "404"] });
      }
      const body = JSON.parse(options.body);
      calls.push({ url, method: "PUT", ids: body.ids, nonce: options.headers["X-WP-Nonce"] });
      return response({ ids: body.ids });
    },
  });
  t.after(() => dom.window.close());
  await tick();

  const { localStorage } = dom.window;
  assert.deepEqual(calls.map((call) => call.method || call.action), ["jluxe_session", "GET", "PUT"]);
  assert.deepEqual(calls[2].ids, [202, 404, 101, 303], "server items and both guest-cache keys are merged without duplicates");
  assert.equal(calls[1].nonce, "private-nonce");
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist")), ["202", "404", "101", "303"]);
  assert.equal(localStorage.getItem("jluxe_wishlist_user"), "42");
  assert.equal(localStorage.getItem("jluxe_wishlist_guest"), null, "guest data is cleared only after account synchronization succeeds");
  assert.equal(localStorage.getItem("jluxe_wishlist_pending_user"), null);
});

test("a click made while the login merge is in flight is included in the account write", async (t) => {
  let releaseGet;
  let putIds = null;
  const dom = boot({
    html: wishlistMarkup,
    settings: { isLoggedIn: true, sessionUrl: "/admin-ajax.php", wishlistUrl: "/wishlist" },
    storage: { jluxe_wishlist: ["303"], jluxe_wishlist_guest: ["101"] },
    fetch: async (url, options = {}) => {
      if (String(url).includes("admin-ajax.php")) {
        return response({ success: true, data: { restNonce: "nonce", auth: { isLoggedIn: true, userId: 42 } } });
      }
      if (options.method === "GET") return new Promise((resolve) => { releaseGet = resolve; });
      putIds = JSON.parse(options.body).ids;
      return response({ ids: putIds });
    },
  });
  t.after(() => dom.window.close());
  await tick();
  const newHeart = dom.window.document.querySelector('[data-jluxe-wishlist-toggle="999"]');
  newHeart.dispatchEvent(new dom.window.MouseEvent("click", { bubbles: true }));
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_wishlist")), ["303", "999"]);
  releaseGet(response({ ids: ["202"] }));
  await tick();
  assert.deepEqual(putIds, [303, 999, 101]);
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_wishlist")), ["303", "999", "101"]);
});

test("a different signed-in account never renders or inherits another account's cached wishlist", async (t) => {
  let releaseGet;
  let putIds = null;
  const dom = boot({
    html: wishlistMarkup,
    settings: { isLoggedIn: true, sessionUrl: "/admin-ajax.php", wishlistUrl: "/wishlist" },
    storage: { jluxe_wishlist: ["999"], jluxe_wishlist_user: "8", jluxe_wishlist_guest: ["101"] },
    fetch: async (url, options = {}) => {
      if (String(url).includes("admin-ajax.php")) {
        return response({ success: true, data: { restNonce: "nonce", auth: { isLoggedIn: true, userId: 9 } } });
      }
      if (options.method === "GET") return new Promise((resolve) => { releaseGet = resolve; });
      putIds = JSON.parse(options.body).ids;
      return response({ ids: putIds });
    },
  });
  t.after(() => dom.window.close());
  const otherAccountHeart = dom.window.document.querySelector('[data-jluxe-wishlist-toggle="999"]');
  assert.equal(otherAccountHeart.getAttribute("aria-pressed"), "false", "the old account state is hidden during private identity confirmation");
  assert.equal(otherAccountHeart.getAttribute("aria-disabled"), "true");
  otherAccountHeart.dispatchEvent(new dom.window.MouseEvent("click", { bubbles: true }));
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_wishlist")), ["999"], "a stale cache cannot be changed into guest/login data");

  await tick();
  releaseGet(response({ ids: ["9"] }));
  await tick();
  assert.deepEqual(putIds, [9, 101], "only the current account and explicit guest items are merged");
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_wishlist")), ["9", "101"]);
  assert.equal(otherAccountHeart.getAttribute("aria-disabled"), null);
});
