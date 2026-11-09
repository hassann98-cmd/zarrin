import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/checkout-guided.js", import.meta.url), "utf8");
const stylesheet = fs.readFileSync(new URL("../assets/css/checkout-guided.css", import.meta.url), "utf8");
const legacyCheckoutSource = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");

test("the legacy multi-step checkout leaves the one-page guided layout in sole control", () => {
  assert.match(legacyCheckoutSource, /form\.getAttribute\("data-jluxe-checkout-flow"\) === "guided"/);
});

function bootGuidedCheckout({ shipping = true, paymentMethods = ["gateway-a"], mobile = false } = {}) {
  const shippingMarkup = shipping
    ? `<section id="jluxe-shipping-section"><input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:1"><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>`
    : "";
  const gateways = paymentMethods
    .map((method) => `<label><input class="payment_method" type="radio" name="payment_method" value="${method}"></label>`)
    .join("");
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form id="jluxe-guided-checkout-form" class="checkout" data-jluxe-checkout-flow="guided">
        <header><h1>تکمیل خرید</h1></header>
        <div data-guided-checkout-root>
          <section data-guided-address-section>
            <h2>اطلاعات گیرنده</h2>
            <div id="customer_details">
              <p class="form-row validate-required" id="billing_first_name_field"><input class="input-text" name="billing_first_name"></p>
              <p class="form-row validate-required" id="billing_last_name_field"><input class="input-text" name="billing_last_name"></p>
              <p class="form-row validate-required" id="billing_phone_field"><input class="input-text" name="billing_phone"></p>
              <p class="form-row validate-required" id="billing_state_field"><select name="billing_state"><option value="">استان</option><option value="THR">تهران</option></select></p>
              <p class="form-row validate-required" id="billing_city_field"><select name="billing_city"><option value="">شهر</option><option value="تهران">تهران</option></select></p>
              <p class="form-row validate-required" id="billing_address_1_field"><input class="input-text" name="billing_address_1"></p>
            </div>
          </section>
          <section class="jluxe-guided-checkout__order">
            <div id="order_review"><div data-jluxe-order-total-value><b>۳۰۰۰ تومان</b></div>${shippingMarkup}</div>
          </section>
          <section data-guided-payment-section><div id="payment">${gateways}</div></section>
          <div id="jluxe-payment-submit">
            <p data-jluxe-payment-error hidden>روش پرداخت را انتخاب کنید.</p>
            <div class="jluxe-checkout-place-order-row"><button id="place_order" type="submit">ثبت سفارش</button></div>
          </div>
        </div>
        <div class="jluxe-guided-checkout__mobile-action" data-guided-mobile-action hidden>
          <strong data-guided-total>۳۰۰۰ تومان</strong>
          <button type="button" data-guided-continue><span data-guided-continue-label>ادامه</span></button>
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.scrollTo = () => {};
  const scrolls = [];
  window.scrollBy = (position) => scrolls.push(position);
  window.requestAnimationFrame = (callback) => callback();
  if (mobile) {
    Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
    Object.defineProperty(window, "innerHeight", { configurable: true, value: 844 });
  }
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
  const document = window.document;
  const form = document.querySelector("form.checkout");
  const payment = document.getElementById("payment");
  let paymentIsOnScreen = false;
  payment.getBoundingClientRect = () => paymentIsOnScreen
    ? { top: 200, bottom: 680, left: 0, right: 390, width: 390, height: 480 }
    : { top: 1000, bottom: 1480, left: 0, right: 390, width: 390, height: 480 };
  const scrollTargets = [];
  for (const element of document.querySelectorAll("#order_review, #payment, .form-row")) {
    element.scrollIntoView = () => scrollTargets.push(element);
  }
  window.eval(source);
  return { dom, window, visualViewport, $, form, document, scrolls, scrollTargets, setPaymentVisible: (value) => { paymentIsOnScreen = value; } };
}

function fillRequiredAddress(document) {
  document.querySelector('[name="billing_first_name"]').value = "مهسا";
  document.querySelector('[name="billing_last_name"]').value = "رضایی";
  document.querySelector('[name="billing_phone"]').value = "09120000000";
  document.querySelector('[name="billing_state"]').value = "THR";
  document.querySelector('[name="billing_city"]').value = "تهران";
  document.querySelector('[name="billing_address_1"]').value = "خیابان نمونه، پلاک ۱۲";
}

test("guided renders as one uninterrupted field-to-summary-to-payment flow with a short mobile action", (t) => {
  const { dom, document, form } = bootGuidedCheckout();
  t.after(() => dom.window.close());

  assert.equal(document.querySelector("[data-guided-stepper]"), null);
  assert.equal(document.querySelectorAll("[data-guided-panel]").length, 0);
  assert.equal(document.querySelector("[data-guided-address-scrim]"), null);
  assert.equal(document.getElementById("customer_details").hidden, false, "WooCommerce fields remain continuously visible");
  assert.ok(document.getElementById("order_review"));
  assert.ok(document.getElementById("payment"));
  assert.equal(form.classList.contains("jluxe-guided-checkout--enhanced"), true);

  const action = document.querySelector("[data-guided-mobile-action]");
  assert.equal(action.hidden, false);
  assert.equal(action.parentElement, document.body, "the fixed action is moved outside transformed theme wrappers");
  assert.equal(action.querySelector("[data-guided-continue-label]").textContent, "ادامه");
  assert.equal(action.querySelector("[data-guided-continue-label]").textContent.includes("مرور سفارش"), false);
  assert.equal(document.body.classList.contains("jluxe-guided-checkout-active"), true);
});

test("the mobile Continue CTA validates the real fields, keeps them visible, then scrolls to live order and shipping totals", (t) => {
  const { dom, document, $, form, scrollTargets, setPaymentVisible } = bootGuidedCheckout({ mobile: true });
  t.after(() => dom.window.close());
  const action = document.querySelector("[data-guided-mobile-action]");
  const button = action.querySelector("[data-guided-continue]");

  button.click();
  assert.ok(document.getElementById("billing_first_name_field").classList.contains("woocommerce-invalid-required-field"));
  assert.equal(document.getElementById("customer_details").hidden, false);
  assert.equal(scrollTargets.at(-1), document.getElementById("billing_first_name_field"), "invalid required fields are brought into view");
  assert.equal(button.textContent.trim(), "ادامه");

  fillRequiredAddress(document);
  button.click();
  assert.equal(document.getElementById("customer_details").hidden, false, "the form never collapses into a saved-address card");
  assert.equal(scrollTargets.at(-1), document.getElementById("order_review"), "Continue scrolls to the order summary, not an intermediate step");
  assert.equal(button.textContent.trim(), "ادامه");

  document.querySelector("#order_review [data-jluxe-order-total-value]").innerHTML = "<b>۴۵۰۰ تومان</b>";
  $(document.body).trigger("updated_checkout");
  assert.equal(action.querySelector("[data-guided-total]").textContent, "۴۵۰۰ تومان", "the sticky total follows WooCommerce's recalculated order-review fragment");

  setPaymentVisible(true);
  document.defaultView.dispatchEvent(new document.defaultView.Event("scroll"));
  assert.equal(button.getAttribute("aria-label"), "ثبت سفارش", "the concise CTA changes only when the payment section enters view");
});

test("the sticky proxy validates shipping and gateway choices, then clicks WooCommerce's real place-order button", (t) => {
  const { dom, document, form, setPaymentVisible } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  const proxy = document.querySelector("[data-guided-continue]");
  let submitted = false;
  form.addEventListener("submit", (event) => {
    submitted = true;
    event.preventDefault();
  });
  fillRequiredAddress(document);
  setPaymentVisible(true);
  document.defaultView.dispatchEvent(new document.defaultView.Event("scroll"));

  proxy.click();
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, false);
  assert.equal(submitted, false);

  const shipping = document.querySelector("input.shipping_method");
  shipping.checked = true;
  shipping.dispatchEvent(new document.defaultView.Event("change", { bubbles: true }));
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, true);

  proxy.click();
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, false);
  assert.equal(submitted, false);

  document.querySelector("input.payment_method").checked = true;
  document.querySelector("input.payment_method").dispatchEvent(new document.defaultView.Event("change", { bubbles: true }));
  proxy.click();
  assert.equal(submitted, true, "successful validation delegates to the native WooCommerce #place_order control");
});

test("text-only checkout works without shipping or payment radios, and keyboard viewport keeps the active field clear of the sticky bar", (t) => {
  const { dom, window, visualViewport, document, form, scrolls } = bootGuidedCheckout({ shipping: false, paymentMethods: [], mobile: true });
  t.after(() => dom.window.close());
  const field = document.querySelector('[name="billing_address_1"]');
  const proxy = document.querySelector("[data-guided-continue]");
  let submitted = false;
  form.addEventListener("submit", (event) => {
    submitted = true;
    event.preventDefault();
  });

  fillRequiredAddress(document);
  visualViewport.height = 420;
  field.getBoundingClientRect = () => ({ top: 760, bottom: 804, left: 0, right: 320, width: 320, height: 44 });
  field.focus();
  assert.equal(document.documentElement.style.getPropertyValue("--jluxe-keyboard-inset"), "424px");
  assert.equal(form.classList.contains("jluxe-guided-checkout--keyboard-open"), true);
  assert.ok(scrolls.length > 0, "the active input is scrolled above the visual viewport's keyboard boundary");
  assert.match(stylesheet, /position:\s*fixed/);
  assert.match(stylesheet, /inset-block-end:\s*var\(--jluxe-keyboard-inset/);

  proxy.click();
  assert.equal(submitted, false, "Continue first moves to the order summary when payment is not yet in view");
  assert.equal(document.querySelector("#order_review").hidden, false);
});

test("guided code does not clear WooCommerce payment radios and still honors native one-gateway selection", () => {
  assert.doesNotMatch(source, /clearAutomaticGatewaySelection|payment_method[^\n]+checked\s*=\s*false/);
  assert.match(source, /placeOrder\.click\(\)/);
  assert.match(source, /data-jluxe-checkout-flow="guided"/);
});
