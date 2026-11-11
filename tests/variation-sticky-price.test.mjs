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
const storefrontCss = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const themeCss = fs.readFileSync(new URL("../style.css", import.meta.url), "utf8");

function cssBlock(stylesheet, selector, occurrence = "first") {
  const startAt = occurrence === "last" ? stylesheet.lastIndexOf(selector) : stylesheet.indexOf(selector);
  assert.notEqual(startAt, -1, `stylesheet contains the ${selector} rule`);
  const openAt = stylesheet.indexOf("{", startAt);
  assert.notEqual(openAt, -1, `${selector} rule opens`);
  let depth = 0;
  for (let index = openAt; index < stylesheet.length; index += 1) {
    if (stylesheet[index] === "{") depth += 1;
    if (stylesheet[index] === "}") depth -= 1;
    if (depth === 0) return stylesheet.slice(openAt + 1, index);
  }
  assert.fail(`${selector} rule closes`);
}

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

test("R306 discounted sticky bars preserve WooCommerce regular/current price markup on variation changes", () => {
  const { window, $ } = setup();
  const form = window.document.querySelector("form.variations_form");
  const saleHtml = '<span class="price"><del><span class="woocommerce-Price-amount amount">۲۱۰٬۰۰۰ تومان</span></del> <ins><span class="woocommerce-Price-amount amount">۱۷۰٬۰۰۰ تومان</span></ins></span>';

  $(form).trigger("found_variation", [{
    variation_id: 45,
    price_html: saleHtml,
    is_purchasable: true,
    is_in_stock: true,
  }]);

  for (const selector of ["[data-jluxe-mobile-bar-price]", "[data-jluxe-sticky-variation-price]"]) {
    const slot = window.document.querySelector(selector);
    assert.equal(slot.querySelector("del").textContent, "۲۱۰٬۰۰۰ تومان", "the old price remains a separate WooCommerce del element");
    assert.equal(slot.querySelector("ins").textContent, "۱۷۰٬۰۰۰ تومان", "the selected sale price remains a separate WooCommerce ins element");
  }
});

test("R306 sticky sale-price CSS stacks a small muted struck old price above a bold current price", () => {
  const mobileLayout = cssBlock(themeCss, "[data-jluxe-mobile-price-bar] [data-jluxe-mobile-bar-price]");
  const mobilePriceWrapper = cssBlock(themeCss, "[data-jluxe-mobile-price-bar] [data-jluxe-mobile-bar-price] .price");
  const mobileOldPrice = cssBlock(themeCss, "[data-jluxe-mobile-price-bar] [data-jluxe-mobile-bar-price] del");
  const mobileCurrentPrice = cssBlock(themeCss, "[data-jluxe-mobile-price-bar] [data-jluxe-mobile-bar-price] ins");
  const sharedPriceLayout = cssBlock(storefrontCss, ".jluxe-mobile-price-card [data-jluxe-mobile-bar-price],");
  const sharedOldPrice = cssBlock(storefrontCss, ".jluxe-mobile-price-card [data-jluxe-mobile-bar-price] del,");
  const sharedCurrentPrice = cssBlock(storefrontCss, ".jluxe-mobile-price-card [data-jluxe-mobile-bar-price] ins,");

  assert.match(mobileLayout, /flex-direction:\s*column/);
  assert.match(mobileLayout, /align-items:\s*flex-start/);
  assert.match(mobilePriceWrapper, /display:\s*flex/);
  assert.match(mobilePriceWrapper, /flex-direction:\s*column/);
  assert.match(mobileOldPrice, /order:\s*0/);
  assert.match(mobileOldPrice, /text-decoration:\s*line-through\s*!important/);
  assert.match(mobileOldPrice, /font-size:\s*11px\s*!important/);
  assert.match(mobileCurrentPrice, /order:\s*1/);
  assert.match(mobileCurrentPrice, /font-size:\s*17px\s*!important/);
  assert.match(mobileCurrentPrice, /font-weight:\s*900\s*!important/);

  assert.match(storefrontCss, /\.jluxe-sticky-cta \.jluxe-sticky-cta-price\s*\{/);
  assert.match(sharedPriceLayout, /flex-direction:\s*column/);
  assert.match(storefrontCss, /\.jluxe-sticky-cta \.jluxe-sticky-cta-price del/);
  assert.match(sharedOldPrice, /text-decoration:\s*line-through/);
  assert.match(storefrontCss, /\.jluxe-sticky-cta \.jluxe-sticky-cta-price ins/);
  assert.match(sharedCurrentPrice, /font-size:\s*0\.96rem/);
  assert.match(sharedCurrentPrice, /font-weight:\s*900/);
});

test("R306 narrow sticky bars reclaim width with smaller quantity and CTA controls, hiding tiny-screen cart icons", () => {
  const storefrontCompact = cssBlock(storefrontCss, "@media (max-width: 380px)", "last");
  const storefrontTiny = cssBlock(storefrontCss, "@media (max-width: 360px)", "last");
  const criticalCompact = cssBlock(themeCss, "@media (max-width: 380px)", "last");
  const criticalTiny = cssBlock(themeCss, "@media (max-width: 360px)", "last");

  assert.match(storefrontCompact, /\.jluxe-mobile-quantity-step\s*\{[^}]*width:\s*1\.625rem;[^}]*min-width:\s*1\.625rem;/s);
  assert.match(storefrontCompact, /\.jluxe-mobile-quantity-value\s*\{[^}]*min-width:\s*1\.25rem;/s);
  assert.match(storefrontCompact, /\.jluxe-sticky-cta \.jluxe-btn\s*\{[^}]*padding-right:\s*0\.5rem;[^}]*padding-left:\s*0\.5rem;[^}]*font-size:\s*0\.6875rem;/s);
  assert.match(storefrontTiny, /\.jluxe-sticky-cta \.jluxe-btn > svg\s*\{\s*display:\s*none;/);

  assert.match(criticalCompact, /\.jluxe-mobile-price-card\s*\{[^}]*gap:\s*4px;[^}]*padding-right:\s*8px;[^}]*padding-left:\s*8px;/s);
  assert.match(criticalCompact, /\[data-jluxe-mobile-bar-add\]\s*\{[^}]*min-width:\s*0;[^}]*padding-right:\s*8px;[^}]*padding-left:\s*8px;/s);
  assert.match(criticalTiny, /\[data-jluxe-mobile-bar-add\] > svg\s*\{\s*display:\s*none;/);
});
