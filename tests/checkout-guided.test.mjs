import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/checkout-guided.js", import.meta.url), "utf8");
const legacyCheckoutSource = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");

test("the legacy checkout stepper leaves the new guided state machine in sole control", () => {
  assert.match(legacyCheckoutSource, /form\.getAttribute\("data-jluxe-checkout-flow"\) === "guided"/);
});

function bootGuidedCheckout({ hasAddress = false, step = "address", shipping = true, paymentMethods = ["gateway-a"], mobile = false } = {}) {
  const shippingMarkup = shipping
    ? `<section id="jluxe-shipping-section"><input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:1"><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>`
    : "";
  const gateways = paymentMethods
    .map((method) => `<label><input class="payment_method" type="radio" name="payment_method" value="${method}"></label>`)
    .join("");
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="checkout" data-jluxe-checkout-flow="guided">
        <header><h1 data-guided-page-title>نشانی تحویل</h1><p data-guided-page-description></p></header>
        <nav data-guided-stepper><ol>
          <li data-guided-step="address" data-guided-step-state="active"><span>۱</span></li>
          <li data-guided-step="payment" data-guided-step-state="pending"><span>۲</span></li>
        </ol></nav>
        <div data-guided-checkout-root data-guided-step="${step}" data-guided-has-address="${hasAddress ? "1" : "0"}">
          <section data-guided-panel="address">
            <h2 id="jluxe-guided-address-title">اطلاعات ارسال</h2>
            <p data-guided-address-error hidden>فیلدهای الزامی را تکمیل کنید.</p>
            <section data-guided-address-card ${hasAddress ? "" : "hidden"}>
              <strong data-guided-address-recipient></strong><p data-guided-address-text></p><span data-guided-address-phone hidden></span>
              <button type="button" data-guided-open-address>ویرایش</button>
            </section>
            <section data-guided-empty-address ${hasAddress ? "hidden" : ""}>
              <button type="button" data-guided-open-address>افزودن نشانی</button>
            </section>
            <div data-guided-address-scrim hidden aria-hidden="true"></div>
            <div id="customer_details" data-guided-address-editor aria-labelledby="jluxe-guided-address-editor-title" tabindex="-1" ${hasAddress ? "hidden" : ""}>
              <header><h3 id="jluxe-guided-address-editor-title">اطلاعات گیرنده و نشانی</h3><button type="button" data-guided-close-address>بستن</button></header>
              <p class="form-row validate-required" id="billing_first_name_field"><input class="input-text" name="billing_first_name" value="${hasAddress ? "مهسا" : ""}"></p>
              <p class="form-row validate-required" id="billing_last_name_field"><input class="input-text" name="billing_last_name" value="${hasAddress ? "رضایی" : ""}"></p>
              <p class="form-row validate-required" id="billing_phone_field"><input class="input-text" name="billing_phone" value="${hasAddress ? "09120000000" : ""}"></p>
              <p class="form-row validate-required" id="billing_state_field"><select name="billing_state"><option value="">استان</option><option value="THR" ${hasAddress ? "selected" : ""}>تهران</option></select></p>
              <p class="form-row validate-required" id="billing_city_field"><select name="billing_city"><option value="">شهر</option><option value="تهران" ${hasAddress ? "selected" : ""}>تهران</option></select></p>
              <p class="form-row validate-required" id="billing_address_1_field"><input class="input-text" name="billing_address_1" value="${hasAddress ? "خیابان نمونه، پلاک ۱۲" : ""}"></p>
              <button type="button" data-guided-cancel-edit hidden>انصراف</button>
            </div>
            <div data-guided-address-actions ${step === "payment" ? "hidden" : ""}>
              <button type="button" data-guided-next>ادامه</button>
            </div>
          </section>
          <section id="jluxe-payment-column" data-guided-panel="payment" ${step === "address" ? "hidden" : ""}>
            <h2 id="jluxe-guided-payment-title">پرداخت</h2>
            <button type="button" data-guided-edit-address>تغییر نشانی</button>
            <div id="payment">${gateways}</div>
          </section>
        </div>
        <aside>
          <div id="order_review"><div data-jluxe-order-total-value><b>۳۰۰۰ تومان</b></div>${shippingMarkup}</div>
          <div id="jluxe-payment-submit" ${step === "address" ? "hidden" : ""}>
            <strong data-guided-total>۳۰۰۰ تومان</strong>
            <p data-jluxe-payment-error hidden>روش پرداخت را انتخاب کنید.</p>
            <button type="button" data-jluxe-checkout-back hidden>بازگشت</button>
          </div>
        </aside>
      </form>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.scrollTo = () => {};
  window.scrollBy = () => {};
  if (mobile) {
    Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
    Object.defineProperty(window, "innerHeight", { configurable: true, value: 844 });
  }
  window.matchMedia = (query) => ({ matches: mobile && query.includes("767.98px"), media: query });
  const visualViewport = new window.EventTarget();
  visualViewport.height = window.innerHeight;
  visualViewport.offsetTop = 0;
  Object.defineProperty(window, "visualViewport", { configurable: true, value: visualViewport });
  window.JLuxeThemeSettings = { cartCheckout: { copy: {} } };
  const $ = jqueryFactory(window);
  $.expr.pseudos.visible = (element) => {
    for (let node = element; node && node.nodeType === 1; node = node.parentElement) {
      if (node.hidden || node.style.display === "none") return false;
    }
    return true;
  };
  window.jQuery = $;
  window.eval(source);
  return { dom, window, visualViewport, $, form: window.document.querySelector("form.checkout"), document: window.document };
}

function fillRequiredAddress(document) {
  document.querySelector('[name="billing_first_name"]').value = "مهسا";
  document.querySelector('[name="billing_last_name"]').value = "رضایی";
  document.querySelector('[name="billing_phone"]').value = "09120000000";
  document.querySelector('[name="billing_state"]').value = "THR";
  document.querySelector('[name="billing_city"]').value = "تهران";
  document.querySelector('[name="billing_address_1"]').value = "خیابان نمونه، پلاک ۱۲";
}

function dispatchSubmit(window, form) {
  const event = new window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(event);
  return event;
}

test("guided checkout opens the address form, validates real fields, then recalculates and advances to WooCommerce rates/payment", (t) => {
  const { dom, window, $, document, form } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  let checkoutUpdates = 0;
  $(document.body).on("update_checkout.test", () => checkoutUpdates++);

  document.querySelector("[data-guided-next]").click();
  assert.equal(document.getElementById("customer_details").hidden, false, "an empty-address CTA opens the native WooCommerce fields");
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);

  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector("[data-guided-address-error]").hidden, false, "required WooCommerce rows are validated before moving on");
  assert.ok(document.getElementById("billing_first_name_field").classList.contains("woocommerce-invalid-required-field"));
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);

  fillRequiredAddress(document);
  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector('[data-guided-panel="address"]').hidden, true);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, false);
  assert.equal(document.querySelector("#jluxe-shipping-section").hidden, false, "real shipping choices are revealed only at order review");
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, false);
  assert.equal(document.querySelector("[data-jluxe-checkout-back]").hidden, false, "the payment stage exposes its real back-to-address action");
  assert.equal(document.getElementById("customer_details").hidden, true, "the actual checkout field group collapses after address confirmation");
  assert.equal(document.querySelector("[data-guided-step=address]").getAttribute("data-guided-step-state"), "done");
  assert.equal(document.querySelector("[data-guided-step=payment]").getAttribute("aria-current"), "step");
  assert.equal(document.querySelector("[data-guided-address-text]").textContent, "تهران، تهران، خیابان نمونه، پلاک ۱۲");
  assert.equal(checkoutUpdates, 1, "the native WooCommerce update_checkout event recalculates shipping/totals");

  // WooCommerce replaces order-review fragments after the address/rate changes.
  document.getElementById("order_review").innerHTML = '<div data-jluxe-order-total-value><b>۴۵۰۰ تومان</b></div><section id="jluxe-shipping-section"><input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:2"><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>';
  $(document.body).trigger("updated_checkout");
  assert.equal(document.getElementById("jluxe-shipping-section").hidden, false);
  assert.equal(document.querySelector("[data-guided-total]").textContent, "۴۵۰۰ تومان", "the sticky total follows WooCommerce's live order-review fragment");
});

test("the mobile address sheet tracks the visual viewport and moves the checkout above the soft keyboard", (t) => {
  const { dom, window, visualViewport, document, form } = bootGuidedCheckout({ mobile: true });
  t.after(() => dom.window.close());

  document.querySelector("[data-guided-open-address]").click();
  const editor = document.getElementById("customer_details");
  const scrim = document.querySelector("[data-guided-address-scrim]");
  assert.equal(editor.getAttribute("role"), "dialog");
  assert.equal(editor.getAttribute("aria-modal"), "true");
  assert.equal(scrim.hidden, false, "the address editor opens as a focused mobile sheet");
  assert.equal(form.classList.contains("jluxe-guided-checkout--address-editing"), true);

  visualViewport.height = 420;
  visualViewport.dispatchEvent(new window.Event("resize"));
  assert.equal(document.documentElement.style.getPropertyValue("--jluxe-visual-viewport-height"), "420px");
  assert.equal(document.documentElement.style.getPropertyValue("--jluxe-keyboard-inset"), "424px");
  assert.equal(form.classList.contains("jluxe-guided-checkout--keyboard-open"), true, "the sticky action bar is signaled to sit directly above the keyboard");

  document.querySelector("[data-guided-close-address]").click();
  assert.equal(editor.hidden, true);
  assert.equal(editor.hasAttribute("aria-modal"), false);
  assert.equal(scrim.hidden, true);
  assert.equal(form.classList.contains("jluxe-guided-checkout--address-editing"), false);
});

test("guided final submission requires real shipping and gateway selections, but lets WooCommerce handle a valid submit", (t) => {
  const { dom, window, document, form } = bootGuidedCheckout({ hasAddress: true, step: "payment" });
  t.after(() => dom.window.close());
  let reachedWooCommerce = false;
  form.addEventListener("submit", () => { reachedWooCommerce = true; });

  const missingShipping = dispatchSubmit(window, form);
  assert.equal(missingShipping.defaultPrevented, true);
  assert.equal(reachedWooCommerce, false);
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, false);

  const shipping = document.querySelector("input.shipping_method");
  shipping.checked = true;
  shipping.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, true);

  const missingGateway = dispatchSubmit(window, form);
  assert.equal(missingGateway.defaultPrevented, true);
  assert.equal(reachedWooCommerce, false);
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, false);

  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  gateway.dispatchEvent(new window.Event("change", { bubbles: true }));
  const validSubmit = dispatchSubmit(window, form);
  assert.equal(validSubmit.defaultPrevented, false);
  assert.equal(reachedWooCommerce, true, "a valid checkout reaches WooCommerce's normal order-submit handler");
});

test("guided checkout returns to the address step and leaves text-only orders usable without map coordinates", (t) => {
  const { dom, window, document } = bootGuidedCheckout({ hasAddress: true, step: "payment", shipping: false, paymentMethods: [] });
  t.after(() => dom.window.close());

  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(document.querySelector('[data-guided-panel="address"]').hidden, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, true);
  assert.equal(document.querySelector("[data-guided-step=address]").getAttribute("aria-current"), "step");

  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, false);
  const submit = dispatchSubmit(window, document.querySelector("form.checkout"));
  assert.equal(submit.defaultPrevented, false, "virtual/no-payment orders are not blocked by nonexistent radio groups");
});
