import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("کنترلِ تعدادِ نوارهای چسبانِ موبایل");
const start = source.indexOf("(function ($) {", marker);
const endToken = "})(window.jQuery);";
const end = source.indexOf(endToken, start) + endToken.length;
assert.ok(marker >= 0 && start >= marker && end > start, "sticky mobile quantity controller is present");
const quantityController = source.slice(start, end);

function buildDom({
  variable = false,
  value = 1,
  min = 1,
  max = 10,
  step = 1,
  selectedId = 0,
  variations = [],
} = {}) {
  const maxAttribute = max === null ? "" : `max="${max}"`;
  const variationFields = variable
    ? `<input type="hidden" class="variation_id" name="variation_id" value="${selectedId}">`
    : "";
  const addButton = variable
    ? `<button type="submit" class="single_add_to_cart_button" ${selectedId > 0 ? "" : "disabled"}>افزودن</button>`
    : `<button type="submit" class="single_add_to_cart_button">افزودن</button>`;
  const formClass = variable ? "cart variations_form" : "cart";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="${formClass}" ${variable ? `data-product_variations='${JSON.stringify(variations)}'` : ""}>
        <div class="quantity"><input class="qty" type="number" name="quantity" value="${value}" min="${min}" ${maxAttribute} step="${step}"></div>
        ${variationFields}${addButton}
      </form>
      <div class="default-sticky">
        <div class="jluxe-mobile-quantity" data-jluxe-mobile-qty-control hidden>
          <button type="button" data-jluxe-mobile-qty-step="decrease" disabled>−</button>
          <output data-jluxe-mobile-qty-value>۱</output>
          <button type="button" data-jluxe-mobile-qty-step="increase" disabled>+</button>
        </div>
      </div>
      <div class="classic-sticky">
        <div class="jluxe-mobile-quantity" data-jluxe-mobile-qty-control hidden>
          <button type="button" data-jluxe-mobile-qty-step="decrease" disabled>−</button>
          <output data-jluxe-mobile-qty-value>۱</output>
          <button type="button" data-jluxe-mobile-qty-step="increase" disabled>+</button>
        </div>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/quantity/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.eval(quantityController);
  return { dom, window, $: window.jQuery };
}

function controls(window) {
  return [...window.document.querySelectorAll("[data-jluxe-mobile-qty-control]")];
}

function valueText(control) {
  return control.querySelector("[data-jluxe-mobile-qty-value]").textContent;
}

function stepButton(control, direction) {
  return control.querySelector(`[data-jluxe-mobile-qty-step="${direction}"]`);
}

test("R146 both sticky layouts mirror the real WooCommerce quantity and respect simple-product bounds", () => {
  const { window } = buildDom({ value: 2, min: 1, max: 3, step: 1 });
  const [defaultBar, classicBar] = controls(window);
  const input = window.document.querySelector("form.cart input.qty");

  assert.equal(defaultBar.hidden, false);
  assert.equal(classicBar.hidden, false);
  assert.equal(valueText(defaultBar), "۲");
  assert.equal(valueText(classicBar), "۲");
  assert.equal(stepButton(defaultBar, "decrease").disabled, false);

  stepButton(defaultBar, "increase").click();
  assert.equal(input.value, "3");
  assert.equal(valueText(defaultBar), "۳");
  assert.equal(valueText(classicBar), "۳");
  assert.equal(stepButton(defaultBar, "increase").disabled, true);

  stepButton(classicBar, "decrease").click();
  assert.equal(input.value, "2");
  assert.equal(valueText(defaultBar), "۲");
  assert.equal(valueText(classicBar), "۲");
});

test("R146 simple-product min, max and decimal step disable and clamp the sticky controls correctly", () => {
  const { window } = buildDom({ value: 1.5, min: 0.5, max: 2, step: 0.5 });
  const [control] = controls(window);
  const input = window.document.querySelector("form.cart input.qty");

  stepButton(control, "increase").click();
  assert.equal(input.value, "2");
  assert.equal(valueText(control), "۲");
  assert.equal(stepButton(control, "increase").disabled, true);

  stepButton(control, "decrease").click();
  assert.equal(input.value, "1.5");
  assert.equal(valueText(control), "۱.۵");
  input.value = "0.25";
  input.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(input.value, "0.5");
  assert.equal(valueText(control), "۰.۵");
  assert.equal(stepButton(control, "decrease").disabled, true);
});

test("R146 variable quantity stays hidden until WooCommerce resolves a purchasable variation, then uses its min and stock max", () => {
  const { window, $ } = buildDom({ variable: true, value: 2, min: 1, max: 10, selectedId: 0 });
  const form = window.document.querySelector("form.variations_form");
  const input = form.querySelector("input.qty");
  const [defaultBar, classicBar] = controls(window);

  assert.equal(defaultBar.hidden, true);
  assert.equal(classicBar.hidden, true);

  form.querySelector("input.variation_id").value = "47";
  form.querySelector(".single_add_to_cart_button").disabled = false;
  $(form).trigger("found_variation", [{
    variation_id: 47,
    is_purchasable: true,
    is_in_stock: true,
    min_qty: 3,
    max_qty: 4,
    is_sold_individually: "no",
  }]);

  assert.equal(defaultBar.hidden, false);
  assert.equal(classicBar.hidden, false);
  assert.equal(input.value, "3", "the real WooCommerce input is raised to the selected variation's minimum");
  assert.equal(valueText(defaultBar), "۳");
  assert.equal(stepButton(defaultBar, "decrease").disabled, true);

  stepButton(classicBar, "increase").click();
  assert.equal(input.value, "4");
  assert.equal(valueText(defaultBar), "۴");
  assert.equal(stepButton(classicBar, "increase").disabled, true);

  input.value = "8";
  input.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(input.value, "4", "manual changes are clamped to the selected variation's stock max");
  assert.equal(valueText(defaultBar), "۴");
});

test("R146 a sold-individually variation is fixed at one and reset hides the proxy", () => {
  const { window, $ } = buildDom({ variable: true, value: 5, min: 1, max: 10 });
  const form = window.document.querySelector("form.variations_form");
  const input = form.querySelector("input.qty");
  const [control] = controls(window);

  form.querySelector("input.variation_id").value = "48";
  form.querySelector(".single_add_to_cart_button").disabled = false;
  $(form).trigger("found_variation", [{
    variation_id: 48,
    is_purchasable: true,
    is_in_stock: true,
    min_qty: 1,
    max_qty: 8,
    is_sold_individually: "yes",
  }]);

  assert.equal(control.hidden, false);
  assert.equal(input.value, "1");
  assert.equal(valueText(control), "۱");
  assert.equal(stepButton(control, "decrease").disabled, true);
  assert.equal(stepButton(control, "increase").disabled, true);

  form.querySelector("input.variation_id").value = "0";
  form.querySelector(".single_add_to_cart_button").disabled = true;
  $(form).trigger("reset_data");
  assert.equal(control.hidden, true);
});

test("R149 a preselected variation with stock max two keeps the requested quantity at one", () => {
  const { window } = buildDom({
    variable: true,
    value: 1,
    min: 1,
    max: 2,
    selectedId: 51,
    variations: [{
      variation_id: 51,
      is_purchasable: true,
      is_in_stock: true,
      min_qty: 1,
      max_qty: 2,
      is_sold_individually: "no",
    }],
  });
  const form = window.document.querySelector("form.variations_form");
  const [control] = controls(window);
  const input = form.querySelector("input.qty");

  assert.equal(control.hidden, false);
  assert.equal(input.value, "1", "stock max is a ceiling, not the starting/requested quantity");
  assert.equal(valueText(control), "۱");
  assert.equal(stepButton(control, "decrease").disabled, true);
  assert.equal(stepButton(control, "increase").disabled, false);
  assert.equal(new window.FormData(form).get("quantity"), "1", "the real WooCommerce form submits one unit, not the stock count");

  stepButton(control, "increase").click();
  assert.equal(input.value, "2", "two units are requested only after the customer explicitly increases the quantity");
  assert.equal(valueText(control), "۲");
  assert.equal(stepButton(control, "increase").disabled, true);
  assert.equal(new window.FormData(form).get("quantity"), "2");

  stepButton(control, "decrease").click();
  assert.equal(input.value, "1");
  assert.equal(new window.FormData(form).get("quantity"), "1");
});

test("R146 unavailable variations never expose sticky quantity controls", () => {
  const { window, $ } = buildDom({ variable: true, value: 1 });
  const form = window.document.querySelector("form.variations_form");
  const [control] = controls(window);
  form.querySelector("input.variation_id").value = "49";
  form.querySelector(".single_add_to_cart_button").disabled = true;
  $(form).trigger("found_variation", [{
    variation_id: 49,
    is_purchasable: true,
    is_in_stock: false,
    min_qty: 1,
    max_qty: 0,
  }]);
  assert.equal(control.hidden, true);
});
