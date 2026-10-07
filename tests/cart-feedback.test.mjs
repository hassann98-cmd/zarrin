import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("توست/اسنک‌بار «به سبد اضافه شد»");
const start = source.indexOf("(function () {", marker);
const endMarker = source.indexOf("\n})();\n\n/*\n * R73:", start);
assert.ok(marker >= 0 && start > marker && endMarker > start, "the shared cart-feedback module is isolated for regression coverage");
const cartFeedbackScript = source.slice(start, endMarker + "\n})();".length);

function boot() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div data-jluxe-island="mini-cart"></div>
      <h1 data-jluxe-product-title>محصول نمونه</h1>
      <form class="cart"><button id="add" class="single_add_to_cart_button">افزودن</button></form>
      <button data-jluxe-cart-icon-desktop></button>
      <button data-jluxe-mobile-price-bar></button>
    </body></html>`,
    { url: "https://shop.test/product/sample/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.jluxeWcSettings = { cartUrl: "https://shop.test/basket/" };
  window.jluxeMountSuggestedModal = (html) => { window.mountedSuggestions = html; return {}; };
  window.jluxeOpenSuggestedProductsModal = () => { window.openedSuggestions = (window.openedSuggestions || 0) + 1; };
  window.eval(cartFeedbackScript);
  return dom;
}

function addToCart(window, button, enabled = false) {
  const suggestions = '<div data-jluxe-suggested-modal><button data-pa-product="55"><span class="jluxe-pa-name">کیف چرمی</span></button><button data-pa-product="56"><span class="jluxe-pa-name">کمربند</span></button></div>';
  window.jQuery(window.document.body).trigger("added_to_cart", [null, null, window.jQuery(button), { suggested_html: enabled ? suggestions : "" }]);
}

test("successful desktop and mobile adds show the exact status and keep all actions available", (t) => {
  const dom = boot();
  t.after(() => dom.window.close());
  const { window } = dom;
  const button = window.document.getElementById("add");
  let cartDrawerOpens = 0;
  window.addEventListener("jluxe:open-cart", () => cartDrawerOpens++);

  window.innerWidth = 1280;
  addToCart(window, button);
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
  assert.equal(toast.querySelector(".jluxe-toast-suggestions").hidden, true);
  assert.equal(window.openedSuggestions || 0, 0, "explicitly disabled suggestions retain the normal confirmation");

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
  addToCart(window, button);
  toast = window.document.querySelector(".jluxe-toast");
  assert.equal(toast.querySelector(".jluxe-toast-cta-primary").textContent, "مشاهده سبد");
  assert.equal(toast.querySelector(".jluxe-toast-continue").textContent, "ادامه خرید");
  assert.equal(button.classList.contains("jluxe-btn-added-pulse"), true, "mobile retains the short add-to-cart pulse alongside the shared action toast");
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


test("R167 enabled recommendations open automatically on desktop and mobile without a toast covering the sheet", (t) => {
  const dom = boot(); t.after(() => dom.window.close());
  const { window } = dom;
  for (const width of [390, 1280]) {
    window.innerWidth = width;
    const before = window.openedSuggestions || 0;
    addToCart(window, window.document.getElementById("add"), true);
    assert.equal(window.openedSuggestions, before + 1);
    assert.match(window.mountedSuggestions, /data-jluxe-suggested-modal/);
    assert.equal(window.document.querySelector('.jluxe-toast'), null);
  }
});
