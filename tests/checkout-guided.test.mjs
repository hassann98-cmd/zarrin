import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";
import { deferCheckoutMapLoad } from "../src/lib/checkout-map-loader.js";

const source = fs.readFileSync(new URL("../assets/js/checkout-guided.js", import.meta.url), "utf8");
const mainSource = fs.readFileSync(new URL("../src/main.js", import.meta.url), "utf8");
const mapLoaderSource = fs.readFileSync(new URL("../src/lib/checkout-map-loader.js", import.meta.url), "utf8");
const mapModuleSource = fs.readFileSync(new URL("../src/lib/checkout-map.js", import.meta.url), "utf8");
const stylesheet = fs.readFileSync(new URL("../assets/css/checkout-guided.css", import.meta.url), "utf8");
const legacyCheckoutSource = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const template = fs.readFileSync(new URL("../woocommerce/checkout/form-checkout-guided.php", import.meta.url), "utf8");

test("the classic checkout's older step controller deliberately leaves guided checkout alone", () => {
  assert.match(legacyCheckoutSource, /form\.getAttribute\("data-jluxe-checkout-flow"\) === "guided"/);
});

function bootGuidedCheckout({ shipping = true, paymentMethods = ["gateway-a"], preselectedPayment = false, mobile = false } = {}) {
  const shippingMarkup = shipping
    ? `<section id="jluxe-shipping-section"><input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:1"><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>`
    : "";
  const gateways = paymentMethods
    .map((method, index) => `<label><input class="payment_method" type="radio" name="payment_method" value="${method}" ${preselectedPayment && index === 0 ? "checked" : ""}></label>`)
    .join("");
  const dom = new JSDOM(
    `<!doctype html><html><body class="woocommerce-checkout">
      <div class="jluxe-guided-checkout__before-form-notices" data-guided-before-form-notices aria-hidden="false">
        <div class="woocommerce-info" role="status" data-test-cashback>با ثبت سفارش، مبلغ بازپرداخت (کش‌بک) به کیف پول شما واریز خواهد شد.</div>
        <div class="woocommerce-form-coupon-toggle"><div class="woocommerce-info" role="status">کد تخفیف دارید؟ برای نوشتن کد اینجا کلیک کنید</div></div>
        <div class="woocommerce-info" role="status" data-test-other-notice>اطلاعیهٔ دیگر WooCommerce</div>
      </div>
      <form id="jluxe-guided-checkout-form" class="checkout" data-jluxe-checkout-flow="guided" data-guided-current-step="shipping">
        <header><h1>تکمیل خرید</h1></header>
        <nav data-guided-stepper data-guided-current-step="shipping">
          <ol>
            <li data-guided-step-item="shipping" data-guided-step-state="current"><button type="button" data-guided-step-target="shipping" aria-current="step" aria-controls="jluxe-guided-shipping-step"><span data-guided-step-marker>۱</span>اطلاعات ارسال</button></li>
            <li data-guided-step-item="payment" data-guided-step-state="upcoming"><button type="button" data-guided-step-target="payment" aria-disabled="true" aria-controls="jluxe-guided-payment-step" disabled><span data-guided-step-marker>۲</span>شیوهٔ پرداخت</button></li>
          </ol>
        </nav>
        <p data-guided-step-status role="status" aria-live="polite">مرحلهٔ ۱ از ۲: اطلاعات ارسال</p>
        <div data-guided-checkout-root>
          <section id="jluxe-guided-shipping-step" data-guided-panel="shipping" aria-labelledby="jluxe-guided-shipping-title">
            <h2 id="jluxe-guided-shipping-title" tabindex="-1">اطلاعات ارسال</h2>
            <p data-guided-shipping-error role="alert" hidden>فیلدهای الزامی را کامل کنید.</p>
            <div id="customer_details">
              <input type="checkbox" name="ship_to_different_address" value="1">
              <input name="shipping_first_name">
              <input name="shipping_last_name">
              <input name="shipping_company">
              <input name="shipping_address_1">
              <input name="shipping_address_2">
              <input name="shipping_city">
              <input name="shipping_state">
              <input name="shipping_postcode">
              <input name="shipping_country">
              <input name="shipping_phone">
              <p class="form-row validate-required" id="billing_first_name_field"><label>نام</label><span class="woocommerce-input-wrapper"><input class="input-text" id="billing_first_name" name="billing_first_name"></span></p>
              <p class="form-row validate-required" id="billing_last_name_field"><label>نام خانوادگی</label><span class="woocommerce-input-wrapper"><input class="input-text" id="billing_last_name" name="billing_last_name"></span></p>
              <p class="form-row validate-required" id="billing_phone_field"><label>شماره موبایل</label><span class="woocommerce-input-wrapper"><input class="input-text" id="billing_phone" name="billing_phone"></span></p>
              <p class="form-row validate-required" id="billing_state_field"><label>استان</label><span class="woocommerce-input-wrapper"><select id="billing_state" name="billing_state"><option value="">استان</option><option value="THR">تهران</option></select></span></p>
              <p class="form-row validate-required" id="billing_city_field"><label>شهر</label><span class="woocommerce-input-wrapper"><select id="billing_city" name="billing_city"><option value="">شهر</option><option value="تهران">تهران</option></select></span></p>
              <p class="form-row validate-required" id="billing_address_1_field"><label>نشانی</label><span class="woocommerce-input-wrapper"><input class="input-text" id="billing_address_1" name="billing_address_1"></span></p>
              <p class="form-row" id="billing_company_field"><label>شرکت</label><span class="woocommerce-input-wrapper"><input class="input-text" id="billing_company" name="billing_company"></span></p>
              <p class="form-row" id="billing_email_field"><label>ایمیل</label><span class="woocommerce-input-wrapper"><input class="input-text" type="email" id="billing_email" name="billing_email"></span></p>
            </div>
            <button type="button" data-guided-next>ادامه به شیوهٔ پرداخت</button>
          </section>
          <section id="jluxe-guided-payment-step" data-guided-panel="payment" aria-labelledby="jluxe-guided-payment-title" hidden>
            <h2 id="jluxe-guided-payment-title" tabindex="-1">شیوهٔ پرداخت</h2>
            <section data-guided-shipping-summary>
              <p data-guided-shipping-name>نام گیرنده</p>
              <p data-guided-shipping-company hidden></p>
              <p data-guided-shipping-address>نشانی</p>
              <p data-guided-shipping-phone hidden></p>
              <button type="button" data-guided-edit-shipping>ویرایش</button>
            </section>
            <section class="jluxe-guided-checkout__order"><div id="order_review"><div data-jluxe-order-total-value><b>۳۰۰۰ تومان</b></div>${shippingMarkup}</div></section>
            <section data-guided-coupon aria-labelledby="coupon-title">
              <h3 id="coupon-title">کد تخفیف</h3>
              <label for="coupon-code">کد تخفیف</label>
              <input id="coupon-code" data-guided-coupon-input>
              <button type="button" data-guided-coupon-apply>اعمال کد</button>
              <p data-guided-coupon-status role="status" aria-live="polite" hidden></p>
            </section>
            <section data-guided-payment-section><div id="payment">${gateways}</div></section>
            <div id="jluxe-payment-submit">
              <p data-jluxe-payment-error hidden>روش پرداخت را انتخاب کنید.</p>
              <div class="jluxe-checkout-place-order-row"><button id="place_order" type="submit">ثبت سفارش</button></div>
            </div>
            <button type="button" data-guided-back>بازگشت به اطلاعات ارسال</button>
          </section>
        </div>
        <div class="jluxe-guided-checkout__mobile-action" data-guided-mobile-action data-guided-mobile-step="shipping" hidden>
          <strong data-guided-total>۳۰۰۰ تومان</strong>
          <button type="button" data-guided-back hidden>بازگشت</button>
          <button type="button" data-guided-continue><span data-guided-continue-label>ادامه به پرداخت</span></button>
        </div>
      </form>
      <div data-guided-coupon-transport hidden>
        <form class="checkout_coupon">
          <input name="coupon_code">
          <button type="submit">Apply coupon</button>
        </form>
      </div>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.scrollTo = () => {};
  const scrolls = [];
  const scrollTargets = [];
  window.scrollBy = (position) => scrolls.push(position);
  window.requestAnimationFrame = (callback) => callback();
  window.matchMedia = () => ({ matches: false });
  window.HTMLElement.prototype.scrollIntoView = function scrollIntoView() { scrollTargets.push(this); };
  if (mobile) {
    Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
    Object.defineProperty(window, "innerHeight", { configurable: true, value: 844 });
  }
  const visualViewport = new window.EventTarget();
  visualViewport.height = window.innerHeight;
  visualViewport.offsetTop = 0;
  Object.defineProperty(window, "visualViewport", { configurable: true, value: visualViewport });
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
  let checkoutUpdates = 0;
  let couponSubmissions = 0;
  $(document.body).on("update_checkout.test", () => { checkoutUpdates += 1; });
  $(document.querySelector("form.checkout_coupon")).on("submit.testWooCoupon", (event) => {
    event.preventDefault();
    couponSubmissions += 1;
  });
  window.eval(source);
  return { dom, window, visualViewport, $, form, document, scrolls, scrollTargets, checkoutUpdates: () => checkoutUpdates, couponSubmissions: () => couponSubmissions };
}

function fillRequiredAddress(document) {
  document.querySelector('[name="billing_first_name"]').value = "مهسا";
  document.querySelector('[name="billing_last_name"]').value = "رضایی";
  document.querySelector('[name="billing_phone"]').value = "09120000000";
  document.querySelector('[name="billing_state"]').value = "THR";
  document.querySelector('[name="billing_city"]').value = "تهران";
  document.querySelector('[name="billing_address_1"]').value = "خیابان نمونه، پلاک ۱۲";
}

test("guided starts at shipping and exposes a two-step progress indicator with a mobile Continue action", (t) => {
  const { dom, document, form } = bootGuidedCheckout();
  t.after(() => dom.window.close());

  const shipping = document.querySelector('[data-guided-panel="shipping"]');
  const payment = document.querySelector('[data-guided-panel="payment"]');
  const steps = document.querySelectorAll("[data-guided-step-item]");
  const action = document.querySelector("[data-guided-mobile-action]");
  assert.equal(steps.length, 2);
  assert.equal(shipping.hidden, false);
  assert.equal(payment.hidden, true);
  assert.equal(form.getAttribute("data-guided-current-step"), "shipping");
  assert.equal(document.querySelector('[data-guided-step-target="shipping"]').getAttribute("aria-current"), "step");
  assert.equal(document.querySelector('[data-guided-step-target="payment"]').disabled, true, "payment cannot be selected before the required address fields are complete");
  assert.match(document.querySelector("[data-guided-step-status]").textContent, /مرحلهٔ ۱ از ۲/);
  assert.equal(form.classList.contains("jluxe-guided-checkout--enhanced"), true);
  assert.equal(action.hidden, false);
  assert.equal(action.parentElement, document.body, "the fixed mobile action is outside transformed theme wrappers");
  assert.equal(action.querySelector("[data-guided-continue-label]").textContent, "ادامه به پرداخت");
  assert.equal(action.querySelector("[data-guided-back]").hidden, true);
  assert.equal(document.body.classList.contains("jluxe-guided-checkout-active"), true);
  assert.match(stylesheet, /\.jluxe-guided-checkout__stepper-list/);
  assert.match(stylesheet, /data-guided-current-step="payment"/);
});

test("Continue validates required shipping fields, announces errors, and focuses the first invalid field", (t) => {
  const { dom, document } = bootGuidedCheckout({ mobile: true });
  t.after(() => dom.window.close());

  document.querySelector("[data-guided-next]").click();
  const firstNameRow = document.getElementById("billing_first_name_field");
  const firstName = document.getElementById("billing_first_name");
  assert.equal(document.querySelector('[data-guided-panel="shipping"]').hidden, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);
  assert.equal(document.querySelector("[data-guided-shipping-error]").hidden, false);
  assert.ok(firstNameRow.classList.contains("woocommerce-invalid-required-field"));
  assert.equal(firstName.getAttribute("aria-invalid"), "true");
  assert.ok(firstName.getAttribute("aria-describedby").includes("jluxe-guided-field-error-"));
  assert.equal(firstNameRow.querySelector("[data-guided-field-error]").textContent, "تکمیل این فیلد الزامی است.");
  assert.equal(document.activeElement, firstName, "focus moves to the first required field instead of losing the user's place");
});

test("the next step shows an editable address summary, keeps form values, and asks WooCommerce to recalculate totals", (t) => {
  const { dom, document, $, form, checkoutUpdates } = bootGuidedCheckout({ mobile: true });
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  document.getElementById("billing_company").value = "شرکت نمونه";

  document.querySelector("[data-guided-next]").click();
  const shipping = document.querySelector('[data-guided-panel="shipping"]');
  const payment = document.querySelector('[data-guided-panel="payment"]');
  assert.equal(shipping.hidden, true);
  assert.equal(payment.hidden, false);
  assert.equal(document.getElementById("billing_first_name").value, "مهسا", "switching panels does not remove or reset WooCommerce fields");
  assert.equal(document.querySelector("[data-guided-shipping-name]").textContent, "مهسا رضایی");
  assert.equal(document.querySelector("[data-guided-shipping-company]").textContent, "شرکت نمونه");
  assert.equal(document.querySelector("[data-guided-shipping-address]").textContent, "خیابان نمونه، پلاک ۱۲، تهران، تهران");
  assert.equal(document.querySelector("[data-guided-shipping-phone]").textContent, "شماره تماس: 09120000000");
  assert.equal(document.querySelector('[data-guided-step-target="shipping"]').getAttribute("aria-current"), null);
  assert.equal(document.querySelector('[data-guided-step-target="payment"]').getAttribute("aria-current"), "step");
  assert.equal(document.querySelector('[data-guided-step-target="payment"]').disabled, false);
  assert.match(document.querySelector("[data-guided-step-status]").textContent, /مرحلهٔ ۲ از ۲/);
  assert.equal(checkoutUpdates(), 1, "WooCommerce's native update_checkout event recalculates address-dependent shipping and totals");

  const action = document.querySelector("[data-guided-mobile-action]");
  assert.equal(action.getAttribute("data-guided-mobile-step"), "payment");
  assert.equal(action.querySelector("[data-guided-continue-label]").textContent, "ثبت سفارش");
  assert.equal(action.querySelector("[data-guided-back]").hidden, false);

  document.querySelector("#order_review [data-jluxe-order-total-value]").innerHTML = "<b>۴۵۰۰ تومان</b>";
  $(document.body).trigger("updated_checkout");
  assert.equal(action.querySelector("[data-guided-total]").textContent, "۴۵۰۰ تومان", "the sticky total follows WooCommerce's refreshed order-review fragment");
  assert.equal(form.getAttribute("data-guided-current-step"), "payment");
});

test("guided checkout suppresses the cashback announcement and default coupon toggle while preserving other notices and the payment coupon UI", (t) => {
  const { dom, document, $ } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  const notices = document.querySelector("[data-guided-before-form-notices]");
  const cashback = document.querySelector("[data-test-cashback]");
  const couponToggle = document.querySelector(".woocommerce-form-coupon-toggle");
  assert.equal(notices.hidden, false, "the pre-checkout hook wrapper remains available");
  assert.equal(cashback.hidden, true, "the wallet cashback notice is suppressed on guided checkout");
  assert.equal(cashback.getAttribute("aria-hidden"), "true");
  assert.equal(couponToggle.hidden, true, "WooCommerce's duplicate default coupon toggle is suppressed");
  assert.equal(couponToggle.getAttribute("aria-hidden"), "true");
  assert.equal(document.querySelector("[data-test-other-notice]").hidden, false, "unrelated WooCommerce notices remain visible");

  const refreshedCashback = document.createElement("div");
  refreshedCashback.className = "woocommerce-info";
  refreshedCashback.textContent = "مبلغ بازپرداخت (کش‌بک) به کیف پول شما واریز خواهد شد.";
  notices.append(refreshedCashback);
  const refreshedCouponToggle = document.createElement("div");
  refreshedCouponToggle.className = "woocommerce-form-coupon-toggle";
  refreshedCouponToggle.textContent = "کد تخفیف دارید؟";
  notices.append(refreshedCouponToggle);
  $(document.body).trigger("updated_checkout");
  assert.equal(refreshedCashback.hidden, true, "notices inserted by a WooCommerce checkout refresh are also suppressed");
  assert.equal(refreshedCashback.getAttribute("aria-hidden"), "true");
  assert.equal(refreshedCouponToggle.hidden, true);
  assert.equal(refreshedCouponToggle.getAttribute("aria-hidden"), "true");

  document.querySelector("[data-guided-next]").click();
  assert.equal(notices.hidden, true, "the pre-checkout wrapper still follows the two-step transition");
  assert.equal(document.querySelector('[data-guided-panel="payment"]').contains(document.querySelector("[data-guided-coupon]")), true, "the guided coupon UI remains in the payment step");

  document.querySelector('[data-guided-panel="payment"] [data-guided-back]').click();
  assert.equal(notices.hidden, false);
  assert.equal(cashback.hidden, true, "returning to shipping does not restore the unwanted cashback notice");
  assert.equal(couponToggle.hidden, true, "returning to shipping does not restore the duplicate coupon toggle");
});

test("a separate shipping address prefers shipping_phone in the payment-step summary", (t) => {
  const { dom, document } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  document.querySelector('[name="ship_to_different_address"]').checked = true;
  document.querySelector('[name="shipping_first_name"]').value = "آرمان";
  document.querySelector('[name="shipping_last_name"]').value = "کریمی";
  document.querySelector('[name="shipping_address_1"]').value = "خیابان ارسال، پلاک ۴";
  document.querySelector('[name="shipping_city"]').value = "کرج";
  document.querySelector('[name="shipping_phone"]').value = "09123334444";

  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector("[data-guided-shipping-name]").textContent, "آرمان کریمی");
  assert.equal(document.querySelector("[data-guided-shipping-address]").textContent, "خیابان ارسال، پلاک ۴، کرج");
  assert.equal(document.querySelector("[data-guided-shipping-phone]").textContent, "شماره تماس: 09123334444");
});

test("a separate shipping address falls back to billing_phone when shipping_phone is blank or missing", (t) => {
  for (const phoneState of ["blank", "missing"]) {
    const { dom, document } = bootGuidedCheckout();
    t.after(() => dom.window.close());
    fillRequiredAddress(document);
    document.querySelector('[name="ship_to_different_address"]').checked = true;
    document.querySelector('[name="shipping_first_name"]').value = "مریم";
    document.querySelector('[name="shipping_last_name"]').value = "احمدی";
    if (phoneState === "missing") document.querySelector('[name="shipping_phone"]').remove();

    document.querySelector("[data-guided-next]").click();
    assert.equal(document.querySelector("[data-guided-shipping-phone]").textContent, "شماره تماس: 09120000000", `${phoneState} shipping phone uses the billing fallback`);
  }
});

test("Back and the completed stepper return to shipping without discarding entered values", (t) => {
  const { dom, document } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  document.querySelector("[data-guided-next]").click();

  document.querySelector('[data-guided-panel="payment"] [data-guided-back]').click();
  assert.equal(document.querySelector('[data-guided-panel="shipping"]').hidden, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);
  assert.equal(document.getElementById("billing_address_1").value, "خیابان نمونه، پلاک ۱۲");
  assert.equal(document.querySelector('[data-guided-step-target="shipping"]').getAttribute("aria-current"), "step");
  assert.equal(document.querySelector('[data-guided-step-target="payment"]').disabled, true);

  document.getElementById("billing_city").value = "تهران";
  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, false);
  assert.equal(document.querySelector("[data-guided-shipping-name]").textContent, "مهسا رضایی");

  document.querySelector('[data-guided-step-target="shipping"]').click();
  assert.equal(document.querySelector('[data-guided-panel="shipping"]').hidden, false, "the completed first step remains an accessible way back");
});

test("payment requires real shipping and gateway selections, then delegates to WooCommerce's real place-order control", (t) => {
  const { dom, document, form } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  document.querySelector("[data-guided-next]").click();
  let submitted = false;
  form.addEventListener("submit", (event) => {
    submitted = true;
    event.preventDefault();
  });
  const proxy = document.querySelector("[data-guided-continue]");

  proxy.click();
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, false);
  assert.equal(submitted, false);

  const shipping = document.querySelector("input.shipping_method");
  shipping.checked = true;
  shipping.dispatchEvent(new dom.window.Event("change", { bubbles: true }));
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, true);

  proxy.click();
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, false);
  assert.equal(submitted, false);

  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  gateway.dispatchEvent(new dom.window.Event("change", { bubbles: true }));
  proxy.click();
  assert.equal(submitted, true, "valid choices click WooCommerce's actual #place_order button");
});

test("the guided coupon control proxies WooCommerce's native form and announces success and failure accessibly", (t) => {
  const { dom, document, $, form, couponSubmissions } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  const input = document.querySelector("[data-guided-coupon-input]");
  const button = document.querySelector("[data-guided-coupon-apply]");
  const status = document.querySelector("[data-guided-coupon-status]");
  const nativeForm = document.querySelector("form.checkout_coupon");
  const nativeInput = nativeForm.querySelector('[name="coupon_code"]');
  assert.equal(form.contains(input), true, "the accessible coupon entry is part of the guided checkout form");
  assert.equal(form.contains(nativeForm), false, "the native WooCommerce coupon form remains outside checkout so forms are never nested");
  assert.equal(document.querySelector("[data-guided-coupon-transport]").hidden, true);

  document.querySelector("[data-guided-coupon-apply]").click();
  assert.equal(status.hidden, false);
  assert.equal(status.getAttribute("data-status"), "error");
  assert.equal(couponSubmissions(), 0, "an empty code does not submit");

  input.value = "SAVE10";
  button.click();
  assert.equal(nativeInput.value, "SAVE10");
  assert.equal(couponSubmissions(), 1, "the proxy submits the standard WooCommerce coupon form");
  assert.equal(button.disabled, true);
  assert.match(status.textContent, /در حال بررسی/);
  $(document.body).trigger("errored_coupon_in_checkout", ["SAVE10", "<p>invalid coupon</p>"]);
  $(document.body).trigger("applied_coupon_in_checkout", ["SAVE10"]);
  assert.equal(status.getAttribute("data-status"), "error");
  assert.match(status.textContent, /قابل اعمال نیست/);
  assert.equal(button.disabled, false);

  input.value = "SAVE10";
  const enter = new dom.window.KeyboardEvent("keydown", { key: "Enter", bubbles: true, cancelable: true });
  input.dispatchEvent(enter);
  assert.equal(enter.defaultPrevented, true, "Enter applies the coupon without submitting the checkout form");
  assert.equal(couponSubmissions(), 2);
  $(document.body).trigger("applied_coupon_in_checkout", ["SAVE10"]);
  assert.equal(status.getAttribute("data-status"), "success");
  assert.match(status.textContent, /با موفقیت اعمال شد/);
  assert.equal(input.value, "");
  assert.equal(button.disabled, false);
});

test("direct form submit from shipping cannot bypass required fields or prematurely place an order", (t) => {
  const { dom, document, form, checkoutUpdates } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  let submitted = false;
  form.addEventListener("submit", (event) => {
    submitted = true;
    event.preventDefault();
  });

  const incomplete = new dom.window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(incomplete);
  assert.equal(incomplete.defaultPrevented, true);
  assert.equal(submitted, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);

  fillRequiredAddress(document);
  const complete = new dom.window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(complete);
  assert.equal(complete.defaultPrevented, true, "the shipping-stage Enter key is intercepted before WooCommerce's order handler");
  assert.equal(submitted, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, false);
  assert.equal(checkoutUpdates(), 1);
});

test("server-side checkout validation takes the customer back to the shipping panel and its invalid control", async (t) => {
  const { dom, document, $, form } = bootGuidedCheckout();
  t.after(() => dom.window.close());
  fillRequiredAddress(document);
  document.querySelector("[data-guided-next]").click();
  const phoneRow = document.getElementById("billing_phone_field");
  phoneRow.classList.add("woocommerce-invalid");
  $(document.body).trigger("checkout_error");
  await new Promise((resolve) => dom.window.setTimeout(resolve, 10));

  assert.equal(document.querySelector('[data-guided-panel="shipping"]').hidden, false);
  assert.equal(document.querySelector('[data-guided-panel="payment"]').hidden, true);
  assert.equal(document.activeElement, document.getElementById("billing_phone"));
  assert.equal(document.querySelector("[data-guided-shipping-error]").hidden, false);
});

test("optional shipping/payment sections and WooCommerce's existing gateway choice are not overridden", (t) => {
  const { dom, document, form } = bootGuidedCheckout({ shipping: false, paymentMethods: ["gateway-a"], preselectedPayment: true });
  t.after(() => dom.window.close());
  form.addEventListener("submit", (event) => event.preventDefault());
  fillRequiredAddress(document);
  document.querySelector("[data-guided-next]").click();
  assert.equal(document.querySelector("[data-jluxe-shipping-error]"), null);
  document.querySelector("[data-guided-continue]").click();
  assert.equal(document.querySelector("[data-jluxe-payment-error]").hidden, true, "a WooCommerce-selected gateway remains a valid native selection");
  assert.doesNotMatch(source, /clearAutomaticGatewaySelection|payment_method[^\n]+checked\s*=\s*false/);
});

test("the active field stays visible above the mobile keyboard and the stepper respects reduced-motion CSS", (t) => {
  const { dom, window, visualViewport, document, form, scrolls } = bootGuidedCheckout({ mobile: true });
  t.after(() => dom.window.close());
  const field = document.querySelector('[name="billing_address_1"]');
  visualViewport.height = 420;
  field.getBoundingClientRect = () => ({ top: 760, bottom: 804, left: 0, right: 320, width: 320, height: 44 });
  field.focus();
  assert.equal(document.documentElement.style.getPropertyValue("--jluxe-keyboard-inset"), "424px");
  assert.equal(form.classList.contains("jluxe-guided-checkout--keyboard-open"), true);
  assert.ok(scrolls.length > 0, "the active input is scrolled above the visual viewport's keyboard boundary");
  assert.match(stylesheet, /position:\s*fixed/);
  assert.match(stylesheet, /inset-block-end:\s*var\(--jluxe-keyboard-inset/);
  assert.match(stylesheet, /prefers-reduced-motion:\s*reduce/);
});

test("the Leaflet chunk loads only on the map-open action, and its first click opens the map", async (t) => {
  const dom = new JSDOM('<section data-jluxe-map-picker><button type="button" data-map-open aria-expanded="false">انتخاب روی نقشه</button></section>');
  t.after(() => dom.window.close());
  const picker = dom.window.document.querySelector("[data-jluxe-map-picker]");
  const button = picker.querySelector("[data-map-open]");
  let loads = 0;
  let clicks = 0;
  let mapOpens = 0;
  let finishImport;
  button.addEventListener("click", () => { clicks += 1; });
  deferCheckoutMapLoad(picker, () => {
    loads += 1;
    return new Promise((resolve) => {
      finishImport = () => {
        button.addEventListener("click", () => { mapOpens += 1; button.setAttribute("aria-expanded", "true"); });
        resolve();
      };
    });
  });
  assert.equal(loads, 0, "initialization alone does not fetch the map chunk");
  button.click();
  assert.equal(loads, 1, "the explicit open action starts the lazy import");
  assert.equal(button.getAttribute("aria-expanded"), "false");
  finishImport();
  await Promise.resolve();
  assert.equal(clicks, 2, "the first physical click and its replay are both dispatched");
  assert.equal(mapOpens, 1, "replaying the first click opens the map exactly once");
  assert.equal(button.getAttribute("aria-expanded"), "true");
  button.click();
  assert.equal(loads, 1, "subsequent open/close clicks reuse the imported chunk");
  assert.equal(mapOpens, 2);
});

test("the map loader permits retry after a failed chunk request", async (t) => {
  const dom = new JSDOM('<section data-jluxe-map-picker><button type="button" data-map-open>انتخاب روی نقشه</button></section>');
  t.after(() => dom.window.close());
  const picker = dom.window.document.querySelector("[data-jluxe-map-picker]");
  const button = picker.querySelector("[data-map-open]");
  let loads = 0;
  deferCheckoutMapLoad(picker, () => {
    loads += 1;
    if (loads === 1) return Promise.reject(new Error("network"));
    return Promise.resolve().then(() => button.addEventListener("click", () => button.setAttribute("aria-expanded", "true")));
  });
  button.click();
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.equal(loads, 1);
  button.click();
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.equal(loads, 2);
  assert.equal(button.getAttribute("aria-expanded"), "true");
});

test("the main entry point defers the map chunk and retains the accessible, optional OSM map", () => {
  assert.match(mainSource, /deferCheckoutMapLoad\(picker\)/);
  assert.doesNotMatch(mainSource, /import\(["']\.\/lib\/checkout-map\.js["']\)/);
  assert.match(mapLoaderSource, /import\("\.\/checkout-map\.js"\)/);
  assert.match(template, /data-map-open aria-expanded="false"/);
  assert.match(mapModuleSource, /tile\.openstreetmap\.org/);
  assert.match(mapModuleSource, /nominatim\.openstreetmap\.org/);
  assert.match(template, /نشانی نوشتاری را وارد کنید/);
});

test("guided checkout keeps the optional manual address and map separate and delegates all order mutations", () => {
  assert.match(template, /data-guided-panel="shipping"[\s\S]*?woocommerce_checkout_billing/);
  assert.match(template, /data-guided-panel="payment"[\s\S]*?woocommerce_checkout_order_review[\s\S]*?woocommerce_checkout_payment\(\)/);
  assert.match(template, /data-jluxe-map-picker/);
  assert.match(template, /انتخاب موقعیت روی نقشه/);
  assert.match(template, /نشانی نوشتاری را وارد کنید/);
  assert.match(template, /name="jluxe_delivery_latitude"/);
  assert.match(source, /\$\(body\)\.trigger\("update_checkout"\)/);
  assert.match(source, /placeOrder\.click\(\)/);
  assert.match(source, /checkout_error\.jluxeGuided/);
});
