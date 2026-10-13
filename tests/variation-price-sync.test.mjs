import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const wooSource = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const priceStart = wooSource.indexOf("function jluxeBindCp3VariationPrice(form) {");
const priceEnd = wooSource.indexOf("\nfunction jluxeSyncVariationAvailability(form)", priceStart);
assert.ok(priceStart >= 0 && priceEnd > priceStart, "the shared CP3 variation-price binder is present");
const priceBinder = wooSource.slice(priceStart, priceEnd);

const templates = [
  ["classic", fs.readFileSync(new URL("../woocommerce/content-single-product-classic.php", import.meta.url), "utf8")],
  ["reference", fs.readFileSync(new URL("../woocommerce/content-single-product-reference.php", import.meta.url), "utf8")],
];

function pillHandler(template, name) {
  const startMarker = "/* قرص‌های تنوع ↔ سلکتِ واقعی ووکامرس */";
  const endMarker = "\n\n\t/* priceBox و found_variation در assets/js/woocommerce.js به‌صورت مشترک bind می‌شوند. */";
  const start = template.indexOf(startMarker);
  const end = template.indexOf(endMarker, start);
  assert.ok(start >= 0 && end > start, `${name} template keeps the CP3 pill-to-select handler`);
  return template.slice(start, end);
}

function markup(variations) {
  return `<!doctype html><html><body>
    <div class="jluxe-cp3">
      <form class="variations_form" data-product_variations='${JSON.stringify(variations)}'>
        <div class="cp3-pills" data-cp3-pills="pa_color">
          <button type="button" class="cp3-pill is-active" data-value="red">قرمز</button>
          <button type="button" class="cp3-pill" data-value="blue">آبی</button>
        </div>
        <div class="variations">
          <select name="attribute_pa_color" data-cp3-select="pa_color">
            <option value="red" selected>قرمز</option>
            <option value="blue">آبی</option>
          </select>
        </div>
        <input class="variation_id" name="variation_id" value="0">
        <div class="cp3-price" data-cp3-price data-cp3-empty-text="برای دیدن قیمت دقیق، یک تنوع را انتخاب کنید." aria-live="polite" aria-atomic="true"><ins>قیمت پیش‌فرضِ سرور</ins></div>
      </form>
    </div>
  </body></html>`;
}

for (const [name, template] of templates) {
  test(`${name} CP3 clicks resolve the real Woo select/variation ID and immediately show the selected sale price`, (t) => {
    const variations = [
      {
        variation_id: 41,
        attributes: { attribute_pa_color: "red" },
        price_html: '<span class="price"><del>۱۰۰٬۰۰۰ تومان</del> <ins>۸۰٬۰۰۰ تومان</ins></span>',
        is_purchasable: true,
        is_in_stock: true,
      },
      {
        variation_id: 42,
        attributes: { attribute_pa_color: "blue" },
        price_html: '<span class="price"><del>۲۰۰٬۰۰۰ تومان</del> <ins>۱۵۰٬۰۰۰ تومان</ins></span>',
        is_purchasable: true,
        is_in_stock: true,
      },
    ];
    const dom = new JSDOM(markup(variations), {
      url: "https://shop.test/product/variable/",
      runScripts: "outside-only",
    });
    t.after(() => dom.window.close());

    const { window } = dom;
    const $ = jquery(window);
    window.jQuery = $;
    window.eval(`${priceBinder}\nwindow.testBindCp3VariationPrice = jluxeBindCp3VariationPrice;`);

    const root = window.document.querySelector(".jluxe-cp3");
    const form = root.querySelector("form.variations_form");
    const select = form.querySelector('select[name="attribute_pa_color"]');
    const variationId = form.querySelector("input.variation_id");
    const priceBox = root.querySelector("[data-cp3-price]");
    const originalDefaultPrice = priceBox.innerHTML;

    window.testBindCp3VariationPrice(form);
    window.eval(`(function () {\nvar root = document.querySelector('.jluxe-cp3');\n${pillHandler(template, name)}\n})();`);

    // WooCommerce emits reset_data during some initialization paths. The
    // server-rendered exact default amount must survive while its selects do.
    $(form).trigger("reset_data");
    assert.equal(priceBox.innerHTML, originalDefaultPrice);
    assert.equal(priceBox.classList.contains("is-placeholder"), false);

    // Model WooCommerce's native change -> variation lookup contract. This
    // ensures the visible CP3 click updates the true select before the real
    // variation event (and variation ID) are consumed by the price listener.
    $(form).on("change.testWoo", 'select[name^="attribute_"]', function () {
      const attributes = Object.fromEntries(
        Array.from(form.querySelectorAll('select[name^="attribute_"]')).map((field) => [field.name, field.value]),
      );
      const variation = variations.find((item) =>
        Object.entries(item.attributes).every(([key, value]) => attributes[key] === value),
      );
      if (!variation) {
        variationId.value = "0";
        $(form).trigger("reset_data");
        return;
      }
      variationId.value = String(variation.variation_id);
      $(form).trigger("found_variation", [variation]);
    });

    // The server-chosen default gets a genuine variation ID from WooCommerce.
    select.dispatchEvent(new window.Event("change", { bubbles: true }));
    assert.equal(variationId.value, "41");
    assert.match(priceBox.innerHTML, /۸۰٬۰۰۰ تومان/);

    form.querySelector('.cp3-pill[data-value="blue"]').click();
    assert.equal(select.value, "blue", "the visible pill updates the native WooCommerce select");
    assert.equal(variationId.value, "42", "WooCommerce resolves the selected variation ID, not merely the pill state");
    assert.equal(form.querySelector('.cp3-pill[data-value="blue"]').classList.contains("is-active"), true);
    assert.equal(form.querySelector('.cp3-pill[data-value="red"]').classList.contains("is-active"), false);
    assert.match(priceBox.innerHTML, /۱۵۰٬۰۰۰ تومان/, "the chosen sale amount is displayed immediately from variation.price_html");
    assert.match(priceBox.innerHTML, /۲۰۰٬۰۰۰ تومان/, "the selected variation's regular price remains available as strike-through");
    assert.equal(priceBox.classList.contains("is-placeholder"), false);

    $(form).trigger("found_variation", [{
      variation_id: 43,
      price_html: '<span class="price">۹۰٬۰۰۰ تومان</span>',
      is_purchasable: true,
      is_in_stock: false,
    }]);
    assert.equal(priceBox.textContent, "ناموجود", "an unavailable variation is not presented with a purchase price");
    assert.equal(priceBox.classList.contains("is-placeholder"), true);

    select.value = "";
    variationId.value = "0";
    $(form).trigger("reset_data");
    assert.equal(priceBox.textContent, "برای دیدن قیمت دقیق، یک تنوع را انتخاب کنید.");
  });
}
