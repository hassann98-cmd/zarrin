import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");

function extractDocumentedIife(markerText, endMarker) {
  const marker = source.indexOf(markerText);
  assert.ok(marker >= 0, `source contains ${markerText}`);
  const start = source.lastIndexOf("/**", marker);
  const end = source.indexOf(endMarker, marker);
  assert.ok(start >= 0 && end > marker, `the ${markerText} block can be isolated`);
  return source.slice(start, end);
}

const cityScript = extractDocumentedIife(
  " * شهرستان (billing_city)",
  "/**\n * پیش‌نمایش عکس‌های دیگرِ محصول روی کارت",
);
const checkoutScript = extractDocumentedIife(
  " * گیت‌کردنِ واقعیِ مرحله‌ی «روش پرداخت»",
  "/**\n * اینپوتِ پرشِ صفحه",
);

const cityMap = {
  THR: ["تهران", "ری", "اسلامشهر"],
  KHZ: ["اهواز", "آبادان"],
};

function bootCity({ state = "THR", initialCity = "", map = cityMap } = {}) {
  const cityOption = initialCity
    ? `<option value="${initialCity}" selected>${initialCity}</option>`
    : "";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <p class="form-row validate-required" id="billing_state_field">
        <label for="billing_state">استان</label>
        <select id="billing_state" name="billing_state" aria-required="true">
          <option value="">انتخاب استان</option>
          <option value="THR" ${state === "THR" ? "selected" : ""}>تهران</option>
          <option value="KHZ" ${state === "KHZ" ? "selected" : ""}>خوزستان</option>
          <option value="UNKNOWN" ${state === "UNKNOWN" ? "selected" : ""}>استان دیگر</option>
        </select>
      </p>
      <p class="form-row validate-required" id="billing_city_field">
        <label for="billing_city">شهر</label>
        <select id="billing_city" name="billing_city" aria-required="true">
          <option value="">ابتدا استان را انتخاب کنید</option>${cityOption}
        </select>
      </p>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.JLuxeThemeSettings = { iranCities: map };
  window.jQuery = jqueryFactory(window);
  let cityChangeCount = 0;
  window.document.getElementById("billing_city").addEventListener("change", () => {
    cityChangeCount += 1;
  });
  window.eval(cityScript);
  return {
    dom,
    window,
    cityChanges: () => cityChangeCount,
    state: window.document.getElementById("billing_state"),
    city: window.document.getElementById("billing_city"),
  };
}

function bootCheckout({
  shippingSection = true,
  shippingRates = ["flat_rate:1"],
  shippingChecked = false,
  paymentMethods = ["bacs"],
  requiredCity = false,
} = {}) {
  const step = (id, label, state) => {
    const circleClass = state === "active" ? "border-primary text-primary" : "border-border text-text-muted";
    const labelClass = state === "active" ? "font-medium text-foreground" : "text-text-muted";
    return `<div data-jluxe-step="${id}">
      <div data-jluxe-step-circle data-jluxe-step-number="۱" class="${circleClass}"></div>
      <span data-jluxe-step-label class="${labelClass}">${label}</span>
    </div>`;
  };
  const shippingMarkup = shippingSection
    ? `<section id="jluxe-shipping-section">
        ${shippingRates.map((rate, index) => `<input class="shipping_method" type="radio" name="shipping_method[${index}]" value="${rate}" ${shippingChecked && index === 0 ? "checked" : ""}>`).join("")}
        <p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p>
      </section>`
    : "";
  const paymentMarkup = paymentMethods
    .map((method) => `<input class="payment_method" type="radio" name="payment_method" value="${method}">`)
    .join("");
  const cityMarkup = requiredCity
    ? `<p class="form-row validate-required" id="billing_city_field">
        <label for="billing_city">شهر</label>
        <select id="billing_city" name="billing_city" aria-required="true">
          <option value="">ابتدا استان را انتخاب کنید</option>
          <option value="تهران">تهران</option>
        </select>
      </p>`
    : "";

  const dom = new JSDOM(
    `<!doctype html><html><body>
      <nav class="jluxe-checkout-stepper" aria-label="مراحل خرید">
        ${step("cart", "سبد خرید", "pending")}
        ${step("shipping", "اطلاعات ارسال", "active")}
        ${step("payment", "روش پرداخت", "pending")}
        ${step("done", "پایان خرید", "pending")}
      </nav>
      <form class="checkout woocommerce-checkout">
        <div id="customer_details"><h3>اطلاعات ارسال</h3>${cityMarkup}</div>
        <div id="jluxe-payment-column" hidden>
          <div id="payment">
            ${paymentMarkup}
            <p data-jluxe-payment-error hidden>روش پرداخت را انتخاب کنید.</p>
          </div>
        </div>
        <div id="order_review">${shippingMarkup}</div>
        <div id="jluxe-payment-submit" hidden>
          <div class="form-row place-order mt-4">
            <label><input id="terms" type="checkbox" name="terms" value="1"> شرایط را می‌پذیرم</label>
            <button id="place_order" name="woocommerce_checkout_place_order" type="submit">ثبت سفارش</button>
            <input type="hidden" name="woocommerce-process-checkout-nonce" value="test-nonce">
          </div>
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  const $ = jqueryFactory(window);
  $.expr.pseudos.visible = (element) => {
    for (let node = element; node && node.nodeType === 1; node = node.parentElement) {
      if (node.hidden || node.style.display === "none") return false;
    }
    return true;
  };
  window.jQuery = $;
  window.jluxeWcSettings = { cartUrl: "/basket/" };
  const scrolls = [];
  window.HTMLElement.prototype.scrollIntoView = function (options = {}) {
    scrolls.push({ id: this.id, behavior: options.behavior, block: options.block });
  };
  // Keep the 1.7.58 post-AJAX gateway cleanup deterministic; tests dispatch
  // events but never perform an order, network request, or asynchronous timer.
  window.setTimeout = () => 1;
  window.eval(checkoutScript);
  return { dom, window, scrolls, $ };
}

function activeStep(document) {
  const steps = Array.from(document.querySelectorAll(".jluxe-checkout-stepper [data-jluxe-step]"));
  return steps.find((step) => step.querySelector("[data-jluxe-step-circle].border-primary"))?.dataset.jluxeStep;
}

function dispatchSubmit(window) {
  const form = window.document.querySelector("form.checkout");
  let reachedForm = false;
  form.addEventListener("submit", () => {
    reachedForm = true;
  });
  const event = new window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(event);
  return { event, reachedForm };
}

test("the historical province-keyed selector populates cities and clears them on a Select2 province change", (t) => {
  const { window, state, city, cityChanges } = bootCity({ state: "THR", initialCity: "تهران" });
  t.after(() => window.close());

  assert.equal(city.value, "تهران", "an already-saved city that exists in the selected province remains selected");
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "تهران", "ری", "اسلامشهر"]);
  assert.equal(city.options[0].textContent, "انتخاب شهرستان");

  state.value = "KHZ";
  window.jQuery(state).trigger("change");
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "اهواز", "آبادان"]);
  assert.equal(city.value, "", "changing province clears the prior city instead of carrying it across provinces");
  assert.equal(city.options[0].textContent, "انتخاب شهرستان");
  assert.equal(cityChanges(), 1, "the city select emits a real change event for WooCommerce listeners");

  state.value = "UNKNOWN";
  window.jQuery(state).trigger("change");
  assert.deepEqual(Array.from(city.options, (option) => option.value), [""]);
  assert.equal(city.options[0].textContent, "ابتدا استان را انتخاب کنید");
  assert.equal(city.value, "");
});

test("an empty required city stays empty; no hidden label or Select2 text is used as a substitute", (t) => {
  const { window, city } = bootCity({ state: "THR" });
  t.after(() => window.close());

  assert.equal(city.value, "");
  assert.equal(city.selectedIndex, 0);
  assert.equal(city.getAttribute("aria-required"), "true");
  assert.ok(city.closest(".validate-required"), "the actual city control remains in WooCommerce's required field row");
  assert.equal(window.document.querySelector('[name="jluxe_billing_city_label"]'), null);
  assert.equal(city.hasAttribute("data-jluxe-city-select"), false);
  assert.equal(city.options[0].textContent, "انتخاب شهرستان", "placeholder text is not submitted as a city value");
});

test("checkout keeps required-city and explicit-shipping checks before the 1.7.58 payment step", (t) => {
  const { window, scrolls, $ } = bootCheckout({ requiredCity: true, shippingRates: ["flat_rate:1"] });
  t.after(() => window.close());
  const { document } = window;
  const city = document.getElementById("billing_city");
  const shippingError = document.querySelector("[data-jluxe-shipping-error]");
  const next = document.querySelector("[data-jluxe-checkout-next]");

  next.click();
  assert.equal(activeStep(document), "shipping");
  assert.ok(document.getElementById("billing_city_field").classList.contains("woocommerce-invalid-required-field"));
  assert.equal(document.activeElement, city, "the actual empty city input is focused, not populated from its label");
  assert.equal(shippingError.hidden, true, "address validation runs before the shipping-method check");

  city.value = "تهران";
  next.click();
  assert.equal(activeStep(document), "shipping");
  assert.equal(shippingError.hidden, false);
  assert.equal(shippingError.textContent, "روش ارسال را انتخاب کنید.");

  const rate = document.querySelector("input.shipping_method");
  rate.checked = true;
  rate.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(shippingError.hidden, true);
  next.click();
  assert.equal(activeStep(document), "payment");
  assert.equal(document.getElementById("customer_details").style.display, "none");
  assert.equal(document.getElementById("jluxe-shipping-section").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, false);
  assert.ok(scrolls.some((entry) => entry.block === "start"));

  // The payment/shipping DOM lives in WooCommerce fragments. The historical
  // state machine reapplies visibility after updated_checkout without replacing
  // the native form validation or its notices.
  document.getElementById("order_review").insertAdjacentHTML(
    "afterbegin",
    '<section id="jluxe-shipping-section"><input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:1"><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>',
  );
  $(document.body).trigger("updated_checkout");
  assert.equal(document.getElementById("jluxe-shipping-section").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);

  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(activeStep(document), "shipping");
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, true);
});

test("no rendered shipping rate follows the v1.7.58 client contract instead of inventing a custom server error", (t) => {
  const { window } = bootCheckout({ shippingRates: [] });
  t.after(() => window.close());
  const { document } = window;

  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(activeStep(document), "payment", "the historical client guard treats a missing radio list as no explicit selection to check");
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, true);
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, true);
});

test("a gateway must be selected explicitly; with no gateway radios WooCommerce receives the normal submit", (t) => {
  const { window } = bootCheckout({ shippingRates: ["flat_rate:1"], shippingChecked: true, paymentMethods: ["bacs"] });
  t.after(() => window.close());
  const { document } = window;
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(activeStep(document), "payment");

  const blocked = dispatchSubmit(window);
  assert.equal(blocked.event.defaultPrevented, true);
  assert.equal(blocked.reachedForm, false);
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, false);

  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  gateway.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, true);
  const allowed = dispatchSubmit(window);
  assert.equal(allowed.event.defaultPrevented, false);
  assert.equal(allowed.reachedForm, true, "the valid gateway proceeds through the real WooCommerce form event chain");

  const { window: noGatewayWindow } = bootCheckout({
    shippingRates: ["flat_rate:1"],
    shippingChecked: true,
    paymentMethods: [],
  });
  t.after(() => noGatewayWindow.close());
  noGatewayWindow.document.querySelector("[data-jluxe-checkout-next]").click();
  const nativeHandling = dispatchSubmit(noGatewayWindow);
  assert.equal(nativeHandling.event.defaultPrevented, false);
  assert.equal(nativeHandling.reachedForm, true, "zero-payment/unavailable-gateway cases are left to WooCommerce's normal validation");
  assert.equal(noGatewayWindow.document.querySelector("[data-jluxe-payment-error]").hidden, true);
});

test("WooCommerce checkout notices are not removed or replaced by the restored step flow", (t) => {
  const { window, $ } = bootCheckout({ shippingRates: ["flat_rate:1"], shippingChecked: true, paymentMethods: ["bacs"] });
  t.after(() => window.close());
  const { document } = window;
  document.querySelector("[data-jluxe-checkout-next]").click();
  document.querySelector("input.payment_method").checked = true;

  const form = document.querySelector("form.checkout");
  form.insertAdjacentHTML(
    "afterbegin",
    '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error"><li>billing_city is a required field</li></ul></div>',
  );
  $(document.body).trigger("checkout_error");
  assert.match(form.querySelector(".woocommerce-NoticeGroup-checkout .woocommerce-error").textContent, /billing_city is a required field/);
  assert.equal(form.querySelector(".woocommerce-NoticeGroup-checkout").hidden, false);
  assert.equal(form.querySelector('[name="woocommerce-process-checkout-nonce"]').value, "test-nonce");
  assert.ok(form.querySelector('[name="woocommerce_checkout_place_order"]'));
});
