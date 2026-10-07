import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const pricePlaceholder = "";
const stockPrompt = "انتخاب گزینه برای بررسی موجودی";
const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("سینکِ قیمتِ نوارِ چسبانِ موبایل");
const start = source.indexOf("(function ($) {", marker);
const endToken = "})(window.jQuery);";
const end = source.indexOf(endToken, start) + endToken.length;
assert.ok(marker >= 0 && start >= marker && end > start, "variation sticky price IIFE is present");
const variationStickyPriceIife = source.slice(start, end);

function setup({ selectedId = "", variations = [] } = {}) {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="variations_form" data-product_variations='${JSON.stringify(variations)}'><input class="variation_id" value="${selectedId}"></form>
      <span data-jluxe-mobile-bar-price data-jluxe-price-placeholder="${pricePlaceholder}" aria-live="polite">${pricePlaceholder}</span>
      <span data-jluxe-sticky-variation-price data-jluxe-price-placeholder="${pricePlaceholder}" aria-live="polite">${pricePlaceholder}</span>
      <span data-jluxe-mobile-bar-stock data-jluxe-stock-state="choose" data-jluxe-stock-placeholder="${stockPrompt}" data-jluxe-stock-placeholder-state="choose" aria-live="polite" aria-atomic="true">${stockPrompt}</span>
      <span data-jluxe-sticky-stock-status data-jluxe-stock-state="choose" data-jluxe-stock-placeholder="${stockPrompt}" data-jluxe-stock-placeholder-state="choose" aria-live="polite" aria-atomic="true">${stockPrompt}</span>
      <button data-jluxe-sticky-add data-jluxe-sticky-mode="scroll" aria-label="رفتن به انتخاب تنوع"><span data-jluxe-sticky-label>انتخاب گزینه‌ها</span></button>
      <button data-jluxe-mobile-bar-add data-jluxe-mobile-bar-mode="scroll" aria-label="رفتن به انتخاب تنوع"><span data-jluxe-mobile-bar-add-label>انتخاب گزینه‌ها</span></button>
    </body></html>`,
    { url: "https://shop.test/product/variable/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.eval(variationStickyPriceIife);
  return { dom, window, $: window.jQuery };
}

test("R136 variable mobile price slots start blank and show price only after valid selection", () => {
  const { window, $ } = setup();
  const form = window.document.querySelector("form.variations_form");
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-price]").textContent.trim(), pricePlaceholder);
  assert.equal(window.document.querySelector("[data-jluxe-sticky-variation-price]").textContent.trim(), pricePlaceholder);

  $(form).trigger("found_variation", [{
    variation_id: 41,
    price_html: "<span class=\"price\">۱۲۰ تومان</span>",
    is_purchasable: true,
    is_in_stock: true,
  }]);

  assert.match(window.document.querySelector("[data-jluxe-mobile-bar-price]").innerHTML, /۱۲۰ تومان/);
  assert.match(window.document.querySelector("[data-jluxe-sticky-variation-price]").innerHTML, /۱۲۰ تومان/);
  for (const selector of ["[data-jluxe-mobile-bar-stock]", "[data-jluxe-sticky-stock-status]"]) {
    const status = window.document.querySelector(selector);
    assert.equal(status.textContent, "", "ordinary in-stock status is intentionally omitted from the sticky bars");
    assert.equal(status.dataset.jluxeStockState, "in-stock");
  }
  assert.equal(window.document.querySelector("[data-jluxe-sticky-add]").dataset.jluxeStickyMode, "add");
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-add]").dataset.jluxeMobileBarMode, "add");
  assert.equal(window.document.querySelector("[data-jluxe-sticky-label]").textContent, "افزودن به سبد");
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-add-label]").textContent, "افزودن به سبد");
});

test("R136 clearing a variation keeps the price blank, restores stock guidance, and blocks unavailable add", () => {
  const { window, $ } = setup();
  const form = window.document.querySelector("form.variations_form");
  $(form).trigger("found_variation", [{
    variation_id: 41,
    price_html: "<span>۱۲۰ تومان</span>",
    is_purchasable: true,
    is_in_stock: true,
  }]);
  $(form).trigger("reset_data");

  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-price]").textContent, pricePlaceholder);
  assert.equal(window.document.querySelector("[data-jluxe-sticky-variation-price]").textContent, pricePlaceholder);
  for (const selector of ["[data-jluxe-mobile-bar-stock]", "[data-jluxe-sticky-stock-status]"]) {
    const status = window.document.querySelector(selector);
    assert.equal(status.textContent, stockPrompt);
    assert.equal(status.dataset.jluxeStockState, "choose");
  }
  assert.equal(window.document.querySelector("[data-jluxe-sticky-add]").dataset.jluxeStickyMode, "scroll");
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-add]").dataset.jluxeMobileBarMode, "scroll");

  $(form).trigger("found_variation", [{
    variation_id: 42,
    price_html: "<span>ناموجود</span>",
    is_purchasable: true,
    is_in_stock: false,
  }]);
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-price]").textContent, pricePlaceholder);
  assert.equal(window.document.querySelector("[data-jluxe-sticky-add]").dataset.jluxeStickyMode, "scroll");
  for (const selector of ["[data-jluxe-mobile-bar-stock]", "[data-jluxe-sticky-stock-status]"]) {
    const status = window.document.querySelector(selector);
    assert.equal(status.textContent, "ناموجود");
    assert.equal(status.dataset.jluxeStockState, "out-of-stock");
  }
});

test("R133 an in-stock but non-purchasable variation is not presented as available", () => {
  const { window, $ } = setup();
  const form = window.document.querySelector("form.variations_form");
  $(form).trigger("found_variation", [{
    variation_id: 43,
    price_html: "<span>۱۲۰ تومان</span>",
    is_purchasable: false,
    is_in_stock: true,
  }]);

  for (const selector of ["[data-jluxe-mobile-bar-stock]", "[data-jluxe-sticky-stock-status]"]) {
    const status = window.document.querySelector(selector);
    assert.equal(status.textContent, "در حال حاضر قابل خرید نیست");
    assert.equal(status.dataset.jluxeStockState, "unavailable");
  }
  assert.equal(window.document.querySelector("[data-jluxe-mobile-bar-price]").textContent, pricePlaceholder);
  assert.equal(window.document.querySelector("[data-jluxe-sticky-add]").dataset.jluxeStickyMode, "scroll");
});

test("R133 a preselected variation initializes stock status from WooCommerce variation data", () => {
  const { window } = setup({
    selectedId: "44",
    variations: [{ variation_id: 44, is_purchasable: true, is_in_stock: false }],
  });

  for (const selector of ["[data-jluxe-mobile-bar-stock]", "[data-jluxe-sticky-stock-status]"]) {
    const status = window.document.querySelector(selector);
    assert.equal(status.textContent, "ناموجود");
    assert.equal(status.dataset.jluxeStockState, "out-of-stock");
  }
  assert.equal(window.document.querySelector("[data-jluxe-sticky-add]").dataset.jluxeStickyMode, "scroll");
});
