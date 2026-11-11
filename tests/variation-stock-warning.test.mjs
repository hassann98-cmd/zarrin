import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const start = source.indexOf("function jluxeBindCp3StockWarning(form) {");
const end = source.indexOf("\n/**\n * R65: unavailable variations are never selectable in any UI.", start);
assert.ok(start >= 0 && end > start, "the shared CP3 low-stock binder is present");
const stockBinder = source.slice(start, end);

const templates = [
  ["classic", fs.readFileSync(new URL("../woocommerce/content-single-product-classic.php", import.meta.url), "utf8")],
  ["reference", fs.readFileSync(new URL("../woocommerce/content-single-product-reference.php", import.meta.url), "utf8")],
];

function setup({ initialQuantity = "4", initiallyVisible = true, initialColor = "red" } = {}) {
  const hidden = initiallyVisible ? "" : " hidden=\"hidden\"";
  const count = initiallyVisible ? "۴" : "";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="jluxe-cp3">
        <form class="variations_form" data-cp3-low-stock-initial-quantity="${initialQuantity}">
          <select name="attribute_pa_color"><option value=""${initialColor === "" ? " selected" : ""}>انتخاب رنگ</option><option value="red"${initialColor === "red" ? " selected" : ""}>قرمز</option><option value="blue"${initialColor === "blue" ? " selected" : ""}>آبی</option></select>
          <div data-cp3-low-stock${hidden}><span data-cp3-stock-quantity>${count}</span></div>
          <div data-cp3-low-stock${hidden}><span data-cp3-stock-quantity>${count}</span></div>
        </form>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/variable/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.eval(`${stockBinder}\nwindow.testBindCp3StockWarning = jluxeBindCp3StockWarning;`);
  const form = window.document.querySelector("form.variations_form");
  window.testBindCp3StockWarning(form);
  return { dom, window, $: window.jQuery, form };
}

function assertQuantity(window, quantity, visible = true, message = "") {
  for (const slot of window.document.querySelectorAll("[data-cp3-low-stock]")) {
    assert.equal(slot.hidden, !visible, message || "all desktop/mobile stock warnings share the same visibility");
    assert.equal(slot.style.display, visible ? "" : "none");
    assert.equal(slot.querySelector("[data-cp3-stock-quantity]").textContent, visible ? quantity : "");
  }
}

test("CP3 low-stock warnings follow the selected variation's exact managed stock", (t) => {
  const { dom, window, $, form } = setup();
  t.after(() => dom.window.close());

  assertQuantity(window, "۴", true);
  $(form).trigger("reset_data");
  assertQuantity(window, "۴", true, "WooCommerce initialization preserves the server-rendered default variation quantity");

  $(form).trigger("found_variation", [{
    variation_id: 41,
    is_in_stock: true,
    is_purchasable: true,
    jluxe_stock_quantity: 1,
  }]);
  assertQuantity(window, "۱");

  $(form).trigger("found_variation", [{
    variation_id: 42,
    is_in_stock: true,
    is_purchasable: true,
    jluxe_stock_quantity: 11,
  }]);
  assertQuantity(window, "", false);

  $(form).trigger("found_variation", [{
    variation_id: 43,
    is_in_stock: true,
    is_purchasable: true,
  }]);
  assertQuantity(window, "", false, "unmanaged stock does not invent a remaining-quantity warning");
});

test("clearing a variable product selection hides the exact-count warning", (t) => {
  const { dom, window, $, form } = setup();
  t.after(() => dom.window.close());

  $(form).trigger("found_variation", [{
    variation_id: 41,
    is_in_stock: true,
    is_purchasable: true,
    jluxe_stock_quantity: 1,
  }]);
  assertQuantity(window, "۱");

  form.querySelector('select[name="attribute_pa_color"]').value = "";
  $(form).trigger("reset_data");
  assertQuantity(window, "", false);
});

test("a variable product with no initial selection starts without a parent-level count", (t) => {
  const { dom, window, $, form } = setup({ initialQuantity: "", initiallyVisible: false, initialColor: "" });
  t.after(() => dom.window.close());

  $(form).trigger("reset_data");
  assertQuantity(window, "", false);
});

test("both product layouts read low stock from the selected variation and provide synchronized warning slots", () => {
  for (const [name, template] of templates) {
    assert.match(template, /jluxe_product_stock_quantity_for_display\( \$product, \$jluxe_is_variable \? \$cp3_defaults : null \)/, `${name} uses the selected/default variation's stock source`);
    assert.match(template, /data-cp3-low-stock/);
    assert.match(template, /data-cp3-stock-quantity/);
    assert.match(template, /data-cp3-low-stock-initial-quantity=/);
  }
  assert.match(source, /jluxeBindCp3StockWarning\(form\)/, "the shared binder is attached to WooCommerce variation forms");
});
