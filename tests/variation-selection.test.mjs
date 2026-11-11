import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const syncStart = source.indexOf("function jluxeSyncVariationAvailability(form) {");
const syncEnd = source.indexOf("\nfunction jluxeSyncAllVariationForms()", syncStart);
assert.ok(syncStart >= 0 && syncEnd > syncStart, "the variation availability function is isolated for behavior tests");
const syncScript = `${source.slice(syncStart, syncEnd)}\nwindow.testSyncVariationAvailability = jluxeSyncVariationAvailability;`;

function variationFormMarkup() {
  const encodedColor = "pa_%d8%b1%d9%86%d9%af";
  const encodedColorVariation = encodedColor.replace(/%[0-9a-f]{2}/gi, (escape) => escape.toUpperCase());
  const variationData = [
    {
      is_in_stock: true,
      attributes: {
        [`attribute_${encodedColorVariation}`]: "قهوه‌ای",
        attribute_pa_size: "medium",
      },
    },
    {
      is_in_stock: false,
      attributes: {
        [`attribute_${encodedColorVariation}`]: "سفید",
        attribute_pa_size: "large",
      },
    },
    {
      is_in_stock: true,
      attributes: {
        [`attribute_${encodedColorVariation}`]: "زرشکی",
        attribute_pa_size: "medium",
      },
    },
  ];
  return `<div class="jluxe-cp3">
    <form class="variations_form" data-product_variations='${JSON.stringify(variationData)}'>
      <div data-jluxe-variation-group="${encodedColor}">
        <button type="button" data-jluxe-variation-value="قهوه‌ای" data-active title="قهوه‌ای">قهوه‌ای</button>
        <button type="button" data-jluxe-variation-value="سفید" title="سفید">سفید</button>
        <button type="button" data-jluxe-variation-value="زرشکی" title="زرشکی">زرشکی</button>
        <select name="attribute_${encodedColor}" data-cp3-select="${encodedColor}">
          <option value="">انتخاب رنگ</option>
          <option value="قهوه‌ای" selected>قهوه‌ای</option>
          <option value="سفید">سفید</option>
          <option value="زرشکی">زرشکی</option>
        </select>
      </div>
      <div data-cp3-pills="${encodedColor}">
        <button type="button" class="cp3-pill is-active" data-value="قهوه‌ای">قهوه‌ای</button>
        <button type="button" class="cp3-pill" data-value="سفید">سفید</button>
        <button type="button" class="cp3-pill" data-value="زرشکی">زرشکی</button>
      </div>
      <div data-cp3-pills="pa_size">
        <button type="button" class="cp3-pill is-active" data-value="medium">متوسط</button>
        <button type="button" class="cp3-pill" data-value="large">بزرگ</button>
      </div>
      <select name="attribute_pa_size" data-cp3-select="pa_size">
        <option value="">انتخاب اندازه</option>
        <option value="medium" selected>متوسط</option>
        <option value="large">بزرگ</option>
      </select>
      <a class="reset_variations">پاک کردن گزینه‌ها</a>
    </form>
  </div>`;
}

test("classic availability sync handles Persian attribute names, maps each select independently, and preserves the saved default", (t) => {
  const dom = new JSDOM(`<!doctype html><html><body>${variationFormMarkup()}</body></html>`, {
    url: "https://shop.test/product/sample/",
    runScripts: "outside-only",
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.eval(syncScript);

  const form = window.document.querySelector("form.variations_form");
  assert.doesNotThrow(() => window.testSyncVariationAvailability(form), "the classic pills can read the select-local option map without a ReferenceError");

  const colorSelect = form.querySelector('select[name^="attribute_pa_%"]');
  const sizeSelect = form.querySelector('select[name="attribute_pa_size"]');
  assert.equal(colorSelect.value, "قهوه‌ای", "stock synchronization does not replace the manager-saved default");
  assert.equal(sizeSelect.value, "medium", "the remaining default attributes are also left untouched");
  assert.equal(form.querySelector('[data-jluxe-variation-value="قهوه‌ای"]').hasAttribute("data-active"), true);
  assert.equal(form.querySelector('.cp3-pill[data-value="قهوه‌ای"]').disabled, false);
  assert.equal(form.querySelector('.cp3-pill[data-value="سفید"]').disabled, true, "out-of-stock Persian option is disabled in the classic pills");
  assert.equal(form.querySelector('.cp3-pill[data-value="large"]').disabled, true, "each attribute uses its own option map and current selection");
  assert.equal(form.querySelector('.cp3-pill[data-value="سفید"]').getAttribute("aria-disabled"), "true");
});

test("swatch click remains a real user action; reset clears selections without auto-picking the first option", (t) => {
  const dom = new JSDOM(`<!doctype html><html><body>${variationFormMarkup()}</body></html>`, {
    url: "https://shop.test/product/sample/",
    runScripts: "outside-only",
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  const marker = source.indexOf("سواچ‌های pill محصول متغیر");
  const handlerStart = source.indexOf('document.addEventListener("click", function (event) {', marker);
  const resetStart = source.indexOf('\n\tdocument.addEventListener("click", function (event) {\n\t\tif (!event.target.closest(".reset_variations"))', handlerStart);
  const handlerEnd = source.indexOf("\n\n\t/**\n\t * پنل فیلتر", resetStart);
  assert.ok(marker >= 0 && handlerStart > marker && resetStart > handlerStart && handlerEnd > resetStart, "the real swatch and reset listeners are isolated");
  window.eval(`(function () {\n${source.slice(handlerStart, handlerEnd)}\n})();`);

  const form = window.document.querySelector("form.variations_form");
  const colorSelect = form.querySelector('select[name^="attribute_pa_%"]');
  const savedSwatch = form.querySelector('[data-jluxe-variation-value="قهوه‌ای"]');
  const alternateSwatch = form.querySelector('[data-jluxe-variation-value="زرشکی"]');

  assert.equal(colorSelect.value, "قهوه‌ای", "server-rendered default stays selected until the shopper acts");
  assert.equal(alternateSwatch.disabled, false, "a real in-stock alternative remains clickable");
  alternateSwatch.click();
  assert.equal(colorSelect.value, "زرشکی", "the user's click still updates the real WooCommerce select");
  assert.equal(alternateSwatch.hasAttribute("data-active"), true);
  assert.equal(savedSwatch.hasAttribute("data-active"), false);

  // WooCommerce clears the selects before it emits reset_data; our reset listener
  // only clears presentation state and must not synthesize another click.
  colorSelect.value = "";
  form.querySelector('select[name="attribute_pa_size"]').value = "";
  form.querySelector(".reset_variations").dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  assert.equal(colorSelect.value, "", "reset stays empty instead of selecting an arbitrary first value");
  assert.equal(form.querySelectorAll("[data-jluxe-variation-value][data-active]").length, 0);
  assert.doesNotMatch(source, /jluxeTarget|swatch\.click\(\)/, "page initialization contains no synthetic first-variation click");
});
