import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("فیدبکِ افزودن به سبد جایگزینِ نوتیس استاندارد ووکامرس است.");
const start = source.indexOf("(function () {", marker);
const endMarker = source.indexOf("\n})();\n\n/*\n * R73:", start);
assert.ok(marker >= 0 && start > marker && endMarker > start, "the shared cart-feedback module is isolated for regression coverage");
const cartFeedbackScript = source.slice(start, endMarker + "\n})();".length);

function boot() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div data-jluxe-island="mini-cart"></div>
      <h1 data-jluxe-product-title>محصول نمونه</h1>
      <form class="cart"><button id="add" class="single_add_to_cart_button" data-product_id="321">افزودن</button></form>
      <button data-jluxe-cart-icon-desktop></button>
      <button data-jluxe-mobile-price-bar></button>
    </body></html>`,
    { url: "https://shop.test/product/sample/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.jluxeWcSettings = { cartUrl: "https://shop.test/basket/" };
  window.JLuxeThemeSettings = {
    cart: { ajaxUrl: "https://shop.test/cart-ajax", nonce: "nonce-test" },
    rest: { sessionUrl: "https://shop.test/session" },
  };
  window.jluxeMountSuggestedModal = (html) => {
    window.document.querySelectorAll("[data-jluxe-suggested-modal]").forEach((old) => old.remove());
    window.mountedSuggestions = html;
    const fresh = window.document.createElement("div");
    fresh.setAttribute("data-jluxe-suggested-modal", "");
    window.document.body.appendChild(fresh);
    return fresh;
  };
  window.jluxeOpenSuggestedProductsModal = () => { window.openedSuggestions = (window.openedSuggestions || 0) + 1; };
  window.eval(cartFeedbackScript);
  return dom;
}

function addToCart(window, button, snapshot = { suggested_html: '<div data-jluxe-suggested-modal><button data-pa-product="55"><span class="jluxe-pa-name">کیف چرمی</span></button><button data-pa-product="56"><span class="jluxe-pa-name">کمربند</span></button></div>' }) {
  window.jQuery(window.document.body).trigger("added_to_cart", [null, null, window.jQuery(button), snapshot]);
}

test("desktop adds keep the toast; mobile adds keep recommendations without covering the cart navigation", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  let cartDrawerOpens = 0;
  window.addEventListener("jluxe:open-cart", () => cartDrawerOpens++);

  window.innerWidth = 1280;
  addToCart(window, button);
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  let toast = window.document.querySelector(".jluxe-toast");
  assert.equal(toast.getAttribute("role"), "status");
  assert.equal(toast.getAttribute("aria-live"), "polite");
  assert.equal(toast.querySelector(".jluxe-toast-msg").textContent, "✓ به سبد اضافه شد");
  assert.equal(toast.querySelector(".jluxe-toast-product").textContent, "محصول نمونه");
  assert.equal(toast.querySelector(".jluxe-toast-cta-primary").textContent, "مشاهده سبد");
  assert.equal(toast.querySelector(".jluxe-toast-cta-primary").getAttribute("href"), "https://shop.test/basket/");
  assert.equal(toast.querySelector(".jluxe-toast-continue").textContent, "ادامه خرید");
  assert.equal(toast.querySelector(".jluxe-toast-cta-primary").hidden, false);
  assert.equal(toast.querySelector(".jluxe-toast-continue").hidden, false);
  assert.match(toast.querySelector(".jluxe-toast-suggestions").textContent, /ممکن است این‌ها را هم لازم داشته باشید: کیف چرمی، کمربند/);
  assert.equal(window.openedSuggestions || 0, 1, "a successful add auto-opens the just-fetched recommendation modal");
  assert.match(window.mountedSuggestions, /data-jluxe-suggested-modal/);

  toast.querySelector(".jluxe-toast-suggestions-cta").click();
  assert.equal(window.openedSuggestions, 2, "the recommendation button remains available after the automatic open");
  assert.equal(toast.classList.contains("jluxe-toast-visible"), false);

  addToCart(window, button);
  toast = window.document.querySelector(".jluxe-toast");
  toast.querySelector(".jluxe-toast-cta-primary").click();
  assert.equal(cartDrawerOpens, 1, "view cart preserves the existing mini-cart drawer behavior when its island is mounted");
  assert.equal(toast.classList.contains("jluxe-toast-visible"), false);

  addToCart(window, button);
  toast = window.document.querySelector(".jluxe-toast");
  button.blur();
  toast.querySelector(".jluxe-toast-continue").click();
  assert.equal(window.document.activeElement, button, "continue shopping dismisses feedback and returns focus to the add control");

  window.innerWidth = 390;
  const opensBeforeMobileAdd = window.openedSuggestions || 0;
  addToCart(window, button);
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  toast = window.document.querySelector(".jluxe-toast");
  assert.equal(toast, null, "mobile success feedback does not create the dark toast that blocks the bottom navigation");
  assert.ok(window.openedSuggestions >= opensBeforeMobileAdd + 1, "mobile successful adds still auto-open the fresh recommendation modal");
  assert.equal(button.classList.contains("jluxe-btn-added-pulse"), true, "mobile retains the short add-to-cart pulse instead of the toast");
  window.dispatchEvent(new window.CustomEvent("jluxe:open-cart"));
  assert.equal(cartDrawerOpens, 2, "the cart drawer event remains available after a mobile add without the blocking toast");
});

test("native WooCommerce add performs one read-only cart request and opens only the current server suggestions", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  const requests = [];
  let receivedSnapshot;
  window.addEventListener("jluxe:cart-updated", (event) => { receivedSnapshot = event.detail; });
  window.jluxeCartPost = (url, body) => {
    requests.push({ url, op: body.get("op"), productId: body.get("product_id") });
    return Promise.resolve({
      success: true,
      data: { itemCount: 1, items: [], suggested_html: '<div data-jluxe-suggested-modal><p>fresh offers</p></div>' },
    });
  };

  window.jQuery(window.document.body).trigger("added_to_cart", [{}, "cart-hash", window.jQuery(button)]);
  assert.deepEqual(requests, [{ url: "https://shop.test/cart-ajax", op: "get", productId: "321" }]);
  await new Promise((resolve) => window.setTimeout(resolve, 5));

  assert.equal(requests.length, 1, "the standard Woo add is not replayed; exactly one snapshot read is made");
  assert.equal(requests[0].op, "get");
  assert.equal(receivedSnapshot.itemCount, 1, "the fresh cart snapshot is dispatched to the cart UI");
  assert.match(window.mountedSuggestions, /fresh offers/);
  assert.equal(window.openedSuggestions, 1, "fresh server suggestions open after the native add succeeds");
});

test("an older native-add snapshot cannot replace or reopen a newer successful add", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  const resolveRequests = [];
  let receivedSnapshot;
  window.addEventListener("jluxe:cart-updated", (event) => { receivedSnapshot = event.detail; });
  window.jluxeCartPost = () => new Promise((resolve) => resolveRequests.push(resolve));

  window.jQuery(window.document.body).trigger("added_to_cart", [{}, "cart-hash-1", window.jQuery(button)]);
  window.jQuery(window.document.body).trigger("added_to_cart", [{}, "cart-hash-2", window.jQuery(button)]);
  assert.equal(resolveRequests.length, 2);

  resolveRequests[1]({ success: true, data: { itemCount: 2, items: [], suggested_html: '<div data-jluxe-suggested-modal>latest offers</div>' } });
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  assert.equal(receivedSnapshot.itemCount, 2);
  assert.match(window.mountedSuggestions, /latest offers/);
  assert.equal(window.openedSuggestions, 1);

  resolveRequests[0]({ success: true, data: { itemCount: 1, items: [], suggested_html: '<div data-jluxe-suggested-modal>stale offers</div>' } });
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  assert.equal(receivedSnapshot.itemCount, 2, "a delayed earlier snapshot cannot roll back the latest cart state");
  assert.match(window.mountedSuggestions, /latest offers/, "stale suggestions cannot replace the newer server markup");
  assert.equal(window.openedSuggestions, 1, "only the latest successful add opens the recommendation modal");
});

test("empty server suggestions remove stale recommendations and do not open a modal", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  window.document.body.insertAdjacentHTML("beforeend", '<div data-jluxe-suggested-modal>stale offers</div>');
  window.jluxeCartPost = () => Promise.resolve({ success: true, data: { itemCount: 1, items: [], suggested_html: "" } });

  window.jQuery(window.document.body).trigger("added_to_cart", [{}, "cart-hash", window.jQuery(button)]);
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  assert.equal(window.document.querySelector("[data-jluxe-suggested-modal]"), null, "disabled/no-result suggestions cannot leave stale markup visible");
  assert.equal(window.openedSuggestions || 0, 0, "no modal opens when the server returns no active suggestions");
});

test("a failed native snapshot refresh clears stale offers without opening a modal", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  window.document.body.insertAdjacentHTML("beforeend", '<div data-jluxe-suggested-modal>stale offers</div>');
  window.jluxeCartPost = () => Promise.reject(new Error("network unavailable"));

  window.jQuery(window.document.body).trigger("added_to_cart", [{}, "cart-hash", window.jQuery(button)]);
  await new Promise((resolve) => window.setTimeout(resolve, 5));
  assert.equal(window.document.querySelector("[data-jluxe-suggested-modal]"), null);
  assert.equal(window.openedSuggestions || 0, 0, "stale markup is never presented as the current product's recommendations");
});

test("adding from inside the suggestion modal does not fetch or reopen a duplicate modal", (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  window.document.body.insertAdjacentHTML("beforeend", '<div data-jluxe-suggested-modal><button id="offer" data-product_id="55">Add offer</button></div>');
  const button = window.document.getElementById("offer");
  let networkCalls = 0;
  window.jluxeCartPost = () => { networkCalls++; return Promise.resolve({ success: true, data: {} }); };
  const oldModal = window.document.querySelector("[data-jluxe-suggested-modal]");

  addToCart(window, button, { itemCount: 2, suggested_html: '<div data-jluxe-suggested-modal>new offers</div>' });
  assert.equal(networkCalls, 0, "the add endpoint response is used without a redundant get request");
  assert.equal(window.document.querySelector("[data-jluxe-suggested-modal]"), oldModal, "the active recommendation modal remains in place instead of duplicating itself");
  assert.equal(window.openedSuggestions || 0, 0, "success inside recommendations does not trigger another auto-open");
});

test("a rejected product add restores its button and never mounts or opens recommendations", async (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const form = window.document.querySelector("form.cart");
  form.dataset.product_id = "321";
  form.innerHTML = '<input type="hidden" name="add-to-cart" value="321"><button id="add" name="add-to-cart" value="321" class="single_add_to_cart_button">افزودن</button>';
  const marker = source.indexOf("افزودن به سبد در فرمِ صفحه‌ی تکیِ محصول");
  const start = source.indexOf("(function () {", marker);
  const end = source.indexOf("\n})();\n\n/**\n * مودالِ انتخاب سریعِ تنوع", start);
  assert.ok(marker >= 0 && start > marker && end > start, "the real product-form submit handler is isolated");
  window.eval(source.slice(start, end + "\n})();".length));

  let requests = 0;
  let addedEvents = 0;
  window.jluxeCartPost = () => {
    requests++;
    return Promise.resolve({ success: false, data: { message: "موجودی کافی نیست." } });
  };
  window.jQuery(window.document.body).on("added_to_cart.testFailure", () => addedEvents++);
  window.innerWidth = 390;
  form.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  await new Promise((resolve) => window.setTimeout(resolve, 5));

  assert.equal(requests, 1);
  assert.equal(addedEvents, 0, "a failed add never announces the success event consumed by the modal opener");
  assert.equal(window.openedSuggestions || 0, 0);
  assert.equal(window.mountedSuggestions, undefined);
  assert.equal(window.document.querySelector("[data-jluxe-suggested-modal]"), null);
  assert.equal(window.document.getElementById("add").disabled, false, "the failure path restores the add control for retry");
  assert.match(window.document.querySelector(".jluxe-toast-error").textContent, /موجودی کافی نیست/);
});

test("feedback CSS provides safe-area mobile sizing, 44px actions, and the shared radius tokens", () => {
  const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
  const root = css.slice(0, css.indexOf(".text-boom-star"));
  assert.match(root, /--jluxe-container-max:\s*1320px/);
  assert.match(root, /--jluxe-radius-sm:\s*8px/);
  assert.match(root, /--jluxe-radius-md:\s*12px/);
  assert.match(root, /--jluxe-radius-lg:\s*16px/);
  assert.match(root, /--jluxe-radius-xl:\s*24px/);
  assert.match(css, /\.jluxe-toast\s*\{[\s\S]*?bottom: max\(12px, env\(safe-area-inset-bottom\)\)/);
  assert.match(css, /min-height: 44px/);
  assert.match(css, /border-radius: var\(--jluxe-radius-xl, 24px\)/);
});
