import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/checkout-notices.js", import.meta.url), "utf8");
const classicTemplate = fs.readFileSync(new URL("../woocommerce/checkout/form-checkout.php", import.meta.url), "utf8");
const storefrontStyles = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");

test("the classic checkout offers a replacement coupon control inside its payment step and keeps the native form outside", () => {
  const paymentColumn = classicTemplate.indexOf('<div id="jluxe-payment-column" hidden>');
  const couponWidget = classicTemplate.indexOf("data-classic-checkout-coupon", paymentColumn);
  const nativePayment = classicTemplate.indexOf("woocommerce_checkout_payment()", paymentColumn);
  assert.ok(paymentColumn >= 0);
  assert.ok(couponWidget > paymentColumn && couponWidget < nativePayment, "the replacement coupon UI appears in the payment step");
  assert.match(classicTemplate, /function_exists\( 'wc_coupons_enabled' \) && wc_coupons_enabled\(\)/);
  assert.match(classicTemplate, /data-classic-checkout-coupon-input/);
  assert.match(classicTemplate, /data-classic-checkout-coupon-apply/);
  assert.match(classicTemplate, /data-classic-checkout-coupon-status/);
  assert.match(storefrontStyles, /body\.woocommerce-checkout \.woocommerce-form-coupon-toggle[\s\S]*?display: none;/);
  assert.match(storefrontStyles, /body\.woocommerce-checkout \.jluxe-checkout-suppressed-notice[\s\S]*?display: none;/);
});

function bootClassicCheckout() {
  const dom = new JSDOM(
    `<!doctype html><html><body class="woocommerce-checkout">
      <div class="woocommerce">
        <div class="woocommerce-info" role="status" data-test-cashback>
          با ثبت این سفارش، مبلغ <span>۶۹,۷۸۰</span> به عنوان بازپرداخت (کش‌بک) به کیف پول شما واریز خواهد شد.
        </div>
        <div class="woocommerce-form-coupon-toggle"><div class="woocommerce-info" role="status">کد تخفیف دارید؟ برای نوشتن کد اینجا کلیک کنید</div></div>
        <div class="woocommerce-info" role="status" data-test-other-notice>اعلان عادی ووکامرس</div>
        <nav class="jluxe-checkout-stepper"><ol><li aria-current="step" data-jluxe-step="shipping">اطلاعات ارسال</li><li data-jluxe-step="payment">پرداخت</li></ol></nav>
        <form class="checkout woocommerce-checkout">
          <div id="customer_details">اطلاعات ارسال</div>
          <div id="jluxe-payment-column" hidden>
            <section class="jluxe-checkout-coupon" data-classic-checkout-coupon>
              <label for="jluxe-classic-checkout-coupon-code">کد تخفیف</label>
              <input id="jluxe-classic-checkout-coupon-code" data-classic-checkout-coupon-input aria-describedby="jluxe-classic-checkout-coupon-status">
              <button type="button" data-classic-checkout-coupon-apply>اعمال کد</button>
              <p id="jluxe-classic-checkout-coupon-status" data-classic-checkout-coupon-status role="status" aria-live="polite" hidden></p>
            </section>
            <div id="payment" style="display:none">روش پرداخت</div>
          </div>
        </form>
        <form class="checkout_coupon" style="display:none"><input name="coupon_code"><button type="submit">اعمال کد</button></form>
      </div>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  const $ = jqueryFactory(window);
  window.jQuery = $;
  const document = window.document;
  let nativeSubmissions = 0;
  $(document.querySelector("form.checkout_coupon")).on("submit.test", (event) => {
    event.preventDefault();
    nativeSubmissions += 1;
  });
  window.eval(source);
  return { dom, window, document, $, nativeSubmissions: () => nativeSubmissions };
}

test("classic checkout hides only the cashback notice and default coupon prompt, including after WooCommerce refreshes", (t) => {
  const { dom, document, $ } = bootClassicCheckout();
  t.after(() => dom.window.close());

  const cashback = document.querySelector("[data-test-cashback]");
  const toggle = document.querySelector(".woocommerce-form-coupon-toggle");
  const unrelated = document.querySelector("[data-test-other-notice]");
  assert.equal(cashback.hidden, true);
  assert.equal(cashback.getAttribute("aria-hidden"), "true");
  assert.equal(toggle.hidden, true);
  assert.equal(toggle.getAttribute("aria-hidden"), "true");
  assert.equal(unrelated.hidden, false, "unrelated notices stay visible");
  assert.equal(document.querySelector(".jluxe-checkout-stepper [aria-current=step]").textContent, "اطلاعات ارسال");
  assert.equal(document.querySelector("#jluxe-payment-column").hidden, true, "notice handling leaves the classic step flow unchanged");

  const refreshedCashback = document.createElement("div");
  refreshedCashback.className = "woocommerce-info";
  refreshedCashback.textContent = "بازپرداخت کش‌بک به کیف پول";
  document.querySelector(".woocommerce").append(refreshedCashback);
  const refreshedToggle = document.createElement("div");
  refreshedToggle.className = "woocommerce-form-coupon-toggle";
  refreshedToggle.textContent = "کد تخفیف دارید؟";
  document.querySelector(".woocommerce").append(refreshedToggle);
  $(document.body).trigger("updated_checkout");
  assert.equal(refreshedCashback.hidden, true);
  assert.equal(refreshedCashback.getAttribute("aria-hidden"), "true");
  assert.equal(refreshedToggle.hidden, true);
  assert.equal(refreshedToggle.getAttribute("aria-hidden"), "true");
});

test("classic payment-step coupon control proxies the native WooCommerce form and reports results accessibly", (t) => {
  const { dom, window, document, $, nativeSubmissions } = bootClassicCheckout();
  t.after(() => dom.window.close());

  const couponInput = document.querySelector("[data-classic-checkout-coupon-input]");
  const applyButton = document.querySelector("[data-classic-checkout-coupon-apply]");
  const status = document.querySelector("[data-classic-checkout-coupon-status]");
  const nativeInput = document.querySelector('form.checkout_coupon input[name="coupon_code"]');

  couponInput.value = "SAVE10";
  applyButton.click();
  assert.equal(nativeInput.value, "SAVE10");
  assert.equal(nativeSubmissions(), 1, "the standard WooCommerce coupon submit handler is used");
  assert.equal(couponInput.disabled, true);
  assert.equal(applyButton.getAttribute("aria-busy"), "true");
  assert.equal(status.textContent, "در حال بررسی کد تخفیف…");
  $(document.body).trigger("applied_coupon_in_checkout", ["save10"]);
  assert.equal(status.textContent, "کد تخفیف با موفقیت اعمال شد.");
  assert.equal(status.hidden, false);
  assert.equal(couponInput.value, "");
  assert.equal(couponInput.disabled, false);
  assert.equal(applyButton.disabled, false);
  assert.equal(applyButton.hasAttribute("aria-busy"), false);

  couponInput.value = "INVALID";
  applyButton.click();
  $(document.body).trigger("errored_coupon_in_checkout", ["invalid"]);
  $(document.body).trigger("applied_coupon_in_checkout", ["INVALID"]);
  assert.equal(status.textContent, "این کد تخفیف قابل اعمال نیست؛ کد را بررسی کنید.");
  assert.equal(status.hidden, false);
  assert.equal(couponInput.value, "INVALID", "a rejected code stays available for correction");
  assert.equal(couponInput.disabled, false);

  couponInput.value = "";
  applyButton.click();
  assert.equal(status.textContent, "کد تخفیف را وارد کنید.");
  assert.equal(couponInput.disabled, false);
  assert.equal(nativeSubmissions(), 2);
});
