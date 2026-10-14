import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { runInNewContext } from "node:vm";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const cartTemplate = fs.readFileSync(new URL("../woocommerce/cart/cart.php", import.meta.url), "utf8");
const cartTotalsTemplate = fs.readFileSync(new URL("../woocommerce/cart/cart-totals.php", import.meta.url), "utf8");
const checkoutTemplate = fs.readFileSync(new URL("../woocommerce/checkout/form-checkout.php", import.meta.url), "utf8");
const reviewOrderTemplate = fs.readFileSync(new URL("../woocommerce/checkout/review-order.php", import.meta.url), "utf8");
const paymentTemplate = fs.readFileSync(new URL("../woocommerce/checkout/payment.php", import.meta.url), "utf8");
const paymentMethodTemplate = fs.readFileSync(new URL("../woocommerce/checkout/payment-method.php", import.meta.url), "utf8");
const shippingTemplate = fs.readFileSync(new URL("../woocommerce/cart/cart-shipping.php", import.meta.url), "utf8");
const paginationTemplate = fs.readFileSync(new URL("../woocommerce/loop/pagination.php", import.meta.url), "utf8");
const paymentSource = fs.readFileSync(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
const themeSettingsSource = fs.readFileSync(new URL("../inc/theme-settings.php", import.meta.url), "utf8");
const settingsAdminSource = fs.readFileSync(new URL("../inc/theme-settings-admin.php", import.meta.url), "utf8");
const settingsRenderSource = fs.readFileSync(new URL("../inc/theme-settings-render.php", import.meta.url), "utf8");
const settingsSanitizeSource = fs.readFileSync(new URL("../inc/theme-settings-sanitize.php", import.meta.url), "utf8");
const storefrontStyles = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");

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
const postcodeRequirementScript = extractDocumentedIife(
  " * R307 — کد پستی فقط برای نرخِ انتخاب‌شدهٔ ادارهٔ پست",
  "/**\n * R289 — ارقامِ فارسی/عربی",
);
const checkoutDigitsScript = extractDocumentedIife(
  " * R289 — ارقامِ فارسی/عربی را پیش از validation",
  "/**\n * گیت‌کردنِ واقعیِ مرحله‌ی «روش پرداخت»",
);
const paginationScript = extractDocumentedIife(
  " * اینپوتِ پرشِ صفحه در پاژینیشنِ آرشیوِ محصولات",
  "/**\n * پاپ‌آپِ آلبومِ تصاویرِ محصول",
);

const cityMap = {
  THR: ["تهران", "ری", "اسلامشهر"],
  KHZ: ["اهواز", "آبادان"],
};

function bootCity({ state = "THR", initialCity = "", map = cityMap, copy = {} } = {}) {
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
  window.JLuxeThemeSettings = { iranCities: map, cartCheckout: { copy } };
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

function bootAccountCities({
  billingState = "THR",
  billingCity = "تهران",
  shippingState = "KHZ",
  shippingCity = "اهواز",
} = {}) {
  const addressMarkup = (prefix, state, city) => `
    <p id="${prefix}_state_field"><select id="${prefix}_state" name="${prefix}_state">
      <option value="THR" ${state === "THR" ? "selected" : ""}>تهران</option>
      <option value="KHZ" ${state === "KHZ" ? "selected" : ""}>خوزستان</option>
    </select></p>
    <p id="${prefix}_city_field"><select id="${prefix}_city" name="${prefix}_city">
      <option value="">انتخاب شهرستان</option>
      <option value="${city}" selected>${city}</option>
    </select></p>`;
  const dom = new JSDOM(
    `<!doctype html><html><body class="woocommerce-account woocommerce-edit-address">
      <form class="woocommerce-address-fields">
        ${addressMarkup("billing", billingState, billingCity)}
        ${addressMarkup("shipping", shippingState, shippingCity)}
      </form>
    </body></html>`,
    { url: "https://shop.test/my-account/edit-address/billing/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.JLuxeThemeSettings = { iranCities: cityMap, cartCheckout: { copy: {} } };
  window.jQuery = jqueryFactory(window);
  window.eval(cityScript);
  return {
    window,
    billingState: window.document.getElementById("billing_state"),
    billingCity: window.document.getElementById("billing_city"),
    shippingState: window.document.getElementById("shipping_state"),
    shippingCity: window.document.getElementById("shipping_city"),
  };
}

function bootCheckout({
  shippingSection = true,
  shippingRates = ["flat_rate:1"],
  shippingChecked = false,
  shippingKinds = [],
  paymentMethods = ["bacs"],
  requiredCity = false,
  showStepper = true,
} = {}) {
  const step = (id, label, state) => {
    const circleClass = state === "done"
      ? "border-success bg-success text-white"
      : state === "active"
        ? "border-primary text-primary"
        : "border-border text-text-muted";
    const labelClass = state === "active" ? "font-medium text-foreground" : "text-text-muted";
    const current = state === "active" ? ' aria-current="step"' : "";
    return `<li class="jluxe-checkout-step" data-jluxe-step="${id}" data-jluxe-step-state="${state}"${current}>
      <span data-jluxe-step-circle data-jluxe-step-number="۱" class="jluxe-checkout-step__circle ${circleClass}"></span>
      <span data-jluxe-step-label class="jluxe-checkout-step__label ${labelClass}">${label}</span>
    </li>`;
  };
  const shippingMarkup = shippingSection
    ? `<section id="jluxe-shipping-section">
        ${shippingRates.map((rate, index) => `<input class="shipping_method" type="radio" data-jluxe-shipping-kind="${shippingKinds[index] || "other"}" name="shipping_method[${index}]" value="${rate}" ${shippingChecked && index === 0 ? "checked" : ""}><label class="jluxe-shipping-card" for="shipping-${index}">روش ارسال ${index + 1}</label><details class="jluxe-shipping-method-accordion"><summary>توضیحات روش ارسال</summary><div>کرایه پس‌کرایه است.</div></details>`).join("")}
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
      ${showStepper ? `<nav class="jluxe-checkout-stepper" aria-label="مراحل خرید">
        <ol class="jluxe-checkout-stepper__list">
          ${step("cart", "سبد خرید", "done")}
          ${step("shipping", "اطلاعات ارسال", "active")}
          ${step("payment", "نحوه پرداخت", "pending")}
          ${step("done", "پایان خرید", "pending")}
        </ol>
      </nav>` : ""}
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
            <div class="jluxe-checkout-place-order-row jluxe-checkout-action-row">
              <button type="button" data-jluxe-checkout-back hidden class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary">بازگشت</button>
              <div class="jluxe-checkout-place-order-slot">
                <button id="place_order" name="woocommerce_checkout_place_order" type="submit" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary">ثبت سفارش</button>
              </div>
            </div>
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
  window.JLuxeThemeSettings = {
    cartCheckout: {
      copy: {
        checkout_next_button: "رفتن به مرحله پرداخت",
        checkout_back_to_cart_button: "بازگشت به سبد",
      },
    },
  };
  const scrolls = [];
  window.HTMLElement.prototype.scrollIntoView = function (options = {}) {
    scrolls.push({ target: "element", id: this.id, behavior: options.behavior, block: options.block });
  };
  window.scrollTo = (options, y) => {
    const position = typeof options === "object" ? options : { left: options, top: y };
    scrolls.push({ target: "window", top: position.top, behavior: position.behavior || "auto" });
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

test("cart product removal is ordered to the left in the RTL mobile product row", () => {
  const removeLink = cartTemplate.match(/<a role="button" href="%s" class="([^"]*jluxe-remove-item[^"]*)"/);
  assert.ok(removeLink, "the WooCommerce remove link remains in the product row");
  assert.ok(removeLink[1].split(/\s+/).includes("order-last"));
  assert.match(cartTemplate, /class="flex items-center gap-3" data-title="<\?php echo esc_attr\( jluxe_cart_checkout_copy\( 'cart_column_product', 'محصول' \) \); \?>"/);
  assert.match(storefrontStyles, /body\.woocommerce-cart \.jluxe-remove-item\s*\{\s*order:\s*9999;/);
  assert.match(storefrontStyles, /html\s*\{\s*direction:\s*rtl;/);
});

test("cart and checkout action rows share responsive RTL buttons with return on the right", () => {
  const actionRow = storefrontStyles.match(/\.jluxe-checkout-action-row\s*\{([^}]+)\}/)?.[1] || "";
  assert.match(actionRow, /display:\s*flex/);
  assert.match(actionRow, /flex-direction:\s*row/);
  assert.match(actionRow, /direction:\s*rtl/);
  assert.match(storefrontStyles, /\.jluxe-checkout-action-row\s*>\s*\.jluxe-checkout-action-button,[\s\S]*?flex:\s*1 1 0%/);

  const mobileStart = storefrontStyles.indexOf(
    "@media (max-width: 767.98px) {\n  body.woocommerce-checkout .jluxe-checkout-layout",
  );
  const reducedMotionStart = storefrontStyles.indexOf("@media (prefers-reduced-motion: reduce)", mobileStart);
  const checkoutMobile = storefrontStyles.slice(mobileStart, reducedMotionStart);
  assert.ok(mobileStart >= 0 && reducedMotionStart > mobileStart);
  assert.match(checkoutMobile, /\.jluxe-checkout-place-order-row\s*\{[\s\S]*?display:\s*flex/);
  assert.doesNotMatch(checkoutMobile, /grid-template-columns:\s*minmax\(0,\s*1\.25fr\)/);
  assert.match(storefrontStyles, /body\.woocommerce-cart \.cart_totals \.jluxe-cart-actions \.jluxe-checkout-btn-wrap \.checkout-button \{/);

  const cartActions = cartTotalsTemplate.match(/<div class="jluxe-cart-actions jluxe-checkout-action-row">([\s\S]*?)<\/div>\s*<\?php do_action\( 'woocommerce_after_cart_totals' \);/i)?.[1] || "";
  assert.ok(cartActions, "the cart has one shared action row");
  assert.ok(cartActions.indexOf("jluxe-checkout-action-button--secondary") < cartActions.indexOf("do_action( 'woocommerce_proceed_to_checkout' )"));
  assert.match(cartActions, /<a href=.*?jluxe-checkout-action-button--secondary/);

  const paymentRow = paymentSource.slice(paymentSource.indexOf("<div class=\"jluxe-checkout-place-order-row jluxe-checkout-action-row\">"));
  assert.ok(paymentRow.indexOf("data-jluxe-checkout-back") < paymentRow.indexOf("jluxe-checkout-place-order-slot"));
  assert.ok(paymentRow.indexOf("jluxe-checkout-place-order-slot") < paymentRow.indexOf('name="woocommerce_checkout_place_order"'));
  assert.match(checkoutTemplate, /class="jluxe-checkout-layout grid/);
  assert.match(checkoutTemplate, /class="jluxe-checkout-panel"/);
  assert.match(checkoutTemplate, /class="jluxe-checkout-summary/);
});

test("R289 checkout phone/postcode autofill stays natively visible while submitted digits normalize", () => {
  const dom = new JSDOM(
    `<!doctype html><html lang="fa"><body>
      <form class="checkout">
        <p><span class="woocommerce-input-wrapper"><input id="billing_postcode" name="billing_postcode"></span></p>
        <p><span class="woocommerce-input-wrapper"><input id="billing_phone" name="billing_phone" type="tel"></span></p>
        <input id="billing_email" name="billing_email">
      </form>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  const form = window.document.querySelector("form.checkout");
  window.jQuery = jqueryFactory(window);
  window.eval(checkoutDigitsScript);

  const postcode = window.document.getElementById("billing_postcode");
  const phone = window.document.getElementById("billing_phone");
  const email = window.document.getElementById("billing_email");
  const postcodeWrapper = postcode.closest(".woocommerce-input-wrapper");
  const phoneWrapper = phone.closest(".woocommerce-input-wrapper");

  postcode.value = "۱۲۳٤٥٦٧٨٩٠";
  postcode.setSelectionRange(4, 4);
  postcode.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(postcode.value, "1234567890", "Persian and Arabic-Indic postal digits normalize immediately");
  assert.equal(postcode.selectionStart, 4, "normalizing digits does not move the caret");
  assert.equal(postcodeWrapper.querySelector(".jluxe-numeric-digit-display"), null, "the real input is the only visible value; no tiny duplicate is placed below it");
  assert.equal(postcode.hasAttribute("data-jluxe-ascii-digit-input"), false, "the real input is never made transparent");

  phone.value = "٠٩١٢٣٤٥٦٧٨٩";
  phone.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(phone.value, "09123456789", "Arabic-Indic phone digits normalize immediately");
  assert.equal(phoneWrapper.querySelector(".jluxe-numeric-digit-display"), null);

  email.value = "١٢٣";
  email.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(email.value, "١٢٣", "unrelated checkout fields are not modified");

  // Browser/keyboard autofill can set .value without dispatching input; the native control remains visible.
  phone.value = "+٩٨ (٩١٢) ٠٠٠-٠٠٠٠";
  assert.equal(phone.value, "+٩٨ (٩١٢) ٠٠٠-٠٠٠٠");
  assert.equal(phoneWrapper.querySelector(".jluxe-numeric-digit-display"), null);
  assert.doesNotMatch(storefrontStyles, /jluxe-numeric-digit-display|color:\s*transparent\s*!important|text-fill-color:\s*transparent/);

  form.addEventListener("submit", (event) => event.preventDefault());
  form.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  assert.equal(phone.value, "+98 (912) 000-0000", "submit normalizes autofilled values even when no input event fired");
  assert.equal(phoneWrapper.querySelector(".jluxe-numeric-digit-display"), null);

  const dynamicWrapper = window.document.createElement("span");
  dynamicWrapper.className = "woocommerce-input-wrapper";
  const dynamicPostcode = window.document.createElement("input");
  dynamicPostcode.name = "shipping_postcode";
  dynamicPostcode.value = "۴۵۶";
  dynamicWrapper.append(dynamicPostcode);
  form.append(dynamicWrapper);
  window.jQuery(window.document.body).trigger("updated_checkout");
  assert.equal(dynamicPostcode.value, "456", "fields replaced during a WooCommerce checkout refresh are normalized too");
  assert.equal(dynamicWrapper.querySelector(".jluxe-numeric-digit-display"), null);
  dom.window.close();
});

test("R307 postcode becomes required only for a selected Post rate and follows the destination address", () => {
  const dom = new JSDOM(
    `<!doctype html><html lang="fa"><body>
      <form class="checkout">
        <section id="jluxe-shipping-section">
          <input id="post-rate" class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:14" data-jluxe-shipping-kind="post">
          <input id="tipax-rate" class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate:19" data-jluxe-shipping-kind="tipax">
        </section>
        <label><input id="ship-to-different-address-checkbox" type="checkbox" name="ship_to_different_address" value="1"> ارسال به آدرس دیگر</label>
        <p class="form-row validate-optional" id="billing_postcode_field">
          <label for="billing_postcode">کد پستی <span class="optional"> (اختیاری)</span></label>
          <input id="billing_postcode" name="billing_postcode" autocomplete="postal-code">
        </p>
        <p class="form-row validate-optional" id="shipping_postcode_field">
          <label for="shipping_postcode">کد پستی <span class="optional"> (اختیاری)</span></label>
          <input id="shipping_postcode" name="shipping_postcode" autocomplete="postal-code">
        </p>
      </form>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  const form = window.document.querySelector("form.checkout");
  const postRate = window.document.getElementById("post-rate");
  const tipaxRate = window.document.getElementById("tipax-rate");
  const differentAddress = window.document.getElementById("ship-to-different-address-checkbox");
  let billingPostcode = window.document.getElementById("billing_postcode");
  let shippingPostcode = window.document.getElementById("shipping_postcode");
  window.jQuery = jqueryFactory(window);
  window.eval(postcodeRequirementScript);

  assert.equal(billingPostcode.required, false, "no selected Post rate leaves billing postcode optional");
  assert.equal(shippingPostcode.required, false, "shipping postcode also stays optional by default");

  postRate.checked = true;
  postRate.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(billingPostcode.required, true, "the billing destination postcode is required for Post");
  assert.equal(billingPostcode.getAttribute("aria-required"), "true");
  assert.ok(billingPostcode.closest(".form-row").classList.contains("validate-required"));
  assert.ok(billingPostcode.closest(".form-row").querySelector("label .required"), "the label visibly reflects the conditional requirement");
  assert.equal(shippingPostcode.required, false, "the unused shipping-address postcode is not required");

  differentAddress.checked = true;
  differentAddress.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(billingPostcode.required, false, "billing is optional when a separate shipping destination is enabled");
  assert.equal(shippingPostcode.required, true, "the active shipping destination postcode is required for Post");

  tipaxRate.checked = true;
  tipaxRate.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(billingPostcode.required, false, "Tipax does not require a postcode");
  assert.equal(shippingPostcode.required, false, "Tipax accepts an empty shipping postcode");
  assert.ok(shippingPostcode.closest(".form-row").querySelector("label .optional"));
  assert.equal(shippingPostcode.hasAttribute("aria-required"), false);

  postRate.checked = true;
  tipaxRate.checked = false;
  differentAddress.checked = false;
  window.jQuery(window.document.body).trigger("updated_checkout");
  const replacement = window.document.createElement("p");
  replacement.className = "form-row validate-optional";
  replacement.id = "billing_postcode_field";
  replacement.innerHTML = '<label for="billing_postcode">کد پستی <span class="optional"> (اختیاری)</span></label><input id="billing_postcode" name="billing_postcode">';
  form.querySelector("#billing_postcode_field").replaceWith(replacement);
  billingPostcode = replacement.querySelector("input");
  window.jQuery(window.document.body).trigger("updated_checkout");
  assert.equal(billingPostcode.required, true, "the rule is reapplied after WooCommerce replaces checkout fields");
  assert.ok(replacement.classList.contains("validate-required"));
  dom.window.close();
});

test("WooCommerce pagination expands base/format pairs and the page-jump input uses the final pattern", () => {
  assert.match(paginationTemplate, /\$format\s*=\s*isset\( \$format \)/);
  assert.match(paginationTemplate, /str_replace\( '%_%', \$format, \$base \)/, "the WooCommerce %_% base slot is expanded with its matching format");
  assert.match(paginationTemplate, /str_replace\( '%#%', \(string\) \( \$current \+ 1 \), \$base \)/, "next-page links replace only the page number placeholder");
  assert.match(paginationTemplate, /data-jluxe-pagination-base="<\?php echo esc_url\( \$base \); \?>"/);
  assert.match(source, /window\.location\.href = base\.replace\("%#%", String\(n\)\)/, "the jump input replaces the final page-number placeholder");

  function submitPageJump(base, value, total = "3") {
    const listeners = {};
    const nav = {
      getAttribute(name) {
        if (name === "data-jluxe-pagination-base") return base;
        if (name === "data-jluxe-pagination-total") return total;
        return null;
      },
    };
    const input = {
      value,
      closest(selector) {
        if (selector === "[data-jluxe-pagination-input]") return this;
        if (selector === ".jluxe-shop-pagination") return nav;
        return null;
      },
    };
    const window = { location: { href: "" } };
    const document = { addEventListener: (type, callback) => { listeners[type] = callback; } };
    runInNewContext(paginationScript, { document, window });
    const event = { key: "Enter", target: input, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
    listeners.keydown(event);
    assert.equal(event.defaultPrevented, true, "Enter is handled by the page jump");
    return window.location.href;
  }

  assert.equal(
    submitPageJump("https://shop.test/store/category/gold/page/%#%/", "۳"),
    "https://shop.test/store/category/gold/page/3/",
    "Persian digits work with a completed pretty-permalink pattern",
  );
  assert.equal(
    submitPageJump("https://shop.test/store/category/gold/?paged=%#%", "۲"),
    "https://shop.test/store/category/gold/?paged=2",
    "query-style page-jump patterns remain valid",
  );
});

test("shipping cards are responsive and customizable without changing real WooCommerce rates", () => {
  assert.match(shippingTemplate, /<ul id="shipping_method" class="jluxe-shipping-method-list">/);
  assert.match(shippingTemplate, /<input type="radio" name="shipping_method\[%1\$d\]" data-index="%1\$d" id="%2\$s" value="%3\$s" class="shipping_method sr-only" data-jluxe-shipping-kind="%4\$s" \/>/);
  assert.match(shippingTemplate, /<label for="<\?php echo esc_attr\( \$input_id \); \?>" class="jluxe-shipping-card jluxe-shipping-card--<\?php echo esc_attr\( \$kind \); \?>[\s\S]*?style=/);
  assert.match(shippingTemplate, /jluxe-shipping-card--no-note/);
  assert.match(shippingTemplate, /jluxe_cart_checkout_shipping_method_settings\( \$kind \)/);
  assert.match(shippingTemplate, /wc_cart_totals_shipping_method_label\( \$method \)/, "WooCommerce continues to render each real rate label and amount");
  assert.match(shippingTemplate, /--jluxe-shipping-card-bg:/);
  assert.match(shippingTemplate, /--jluxe-shipping-card-border:/);
  assert.match(shippingTemplate, /--jluxe-shipping-card-selected-bg:/);
  assert.match(shippingTemplate, /--jluxe-shipping-card-selected-border:/);
  assert.match(shippingTemplate, /--jluxe-shipping-note-bg/);
  assert.ok(
    shippingTemplate.indexOf("wc_cart_totals_shipping_method_label( $method )") < shippingTemplate.indexOf("if ( $has_method_note )"),
    "the configurable badge is rendered separately after the WooCommerce rate name and amount",
  );
  assert.match(shippingTemplate, /class="jluxe-shipping-note jluxe-shipping-note--/);
  assert.match(shippingTemplate, /jluxe-shipping-details-bg/);
  assert.match(shippingTemplate, /show_shipping_accordions/);
  assert.match(shippingTemplate, /<details class="jluxe-shipping-method-accordion[^"]*"[\s\S]*?\bopen>/, "only a selected rate's accordion is displayed below its card");
  assert.match(shippingTemplate, /do_action\( 'woocommerce_after_shipping_rate', \$method, \$index \)/);
  assert.doesNotMatch(shippingTemplate, /jluxe-shipping-card__check|jluxe-shipping-check/);
  const shippingRadio = shippingTemplate.match(/<input type="radio"[\s\S]*?\/>/)?.[0] || "";
  assert.ok(shippingRadio, "the shipping method is still a real WooCommerce radio");
  assert.doesNotMatch(shippingRadio, /\bchecked\b/i, "the template does not preselect a carrier");

  const listRule = storefrontStyles.match(/\.jluxe-shipping-method-list\s*\{([^}]+)\}/s)?.[1] || "";
  const itemRule = storefrontStyles.match(/\.jluxe-shipping-method-item\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingCardRule = storefrontStyles.match(/\.jluxe-shipping-card\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingCardDetailsRule = storefrontStyles.match(/\.jluxe-shipping-card__details\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingLabelRule = storefrontStyles.match(/\.jluxe-shipping-card__label\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingAmountRule = storefrontStyles.match(/\.jluxe-shipping-card__label \.woocommerce-Price-amount\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingNoteRule = storefrontStyles.match(/\.jluxe-shipping-note\s*\{([^}]+)\}/s)?.[1] || "";
  const checkoutSummaryShippingRule = storefrontStyles.match(/body\.woocommerce-checkout \.jluxe-checkout-summary \.jluxe-shipping-card:not\(\.jluxe-shipping-card--no-note\)\s*\{([^}]+)\}/s)?.[1] || "";
  const checkoutSummaryNoteRule = storefrontStyles.match(/body\.woocommerce-checkout \.jluxe-checkout-summary \.jluxe-shipping-note\s*\{([^}]+)\}/s)?.[1] || "";
  const shippingDetailsRule = storefrontStyles.match(/\.jluxe-shipping-method-accordion\s*\{([^}]+)\}/s)?.[1] || "";
  const selectedRule = storefrontStyles.match(/input\.shipping_method:checked \+ \.jluxe-shipping-card,\s*\.jluxe-shipping-card--selected\s*\{([^}]+)\}/s)?.[1] || "";
  assert.match(listRule, /display:\s*grid/);
  assert.match(itemRule, /display:\s*grid/);
  assert.match(shippingCardRule, /display:\s*grid/);
  assert.match(shippingCardRule, /grid-template-areas:\s*"note details"/);
  assert.match(shippingCardRule, /grid-template-columns:\s*minmax\(0, 1\.15fr\) minmax\(0, 1\.35fr\)/, "wide cards reserve more space for the note without removing the rate details");
  assert.match(shippingCardRule, /direction:\s*ltr/);
  assert.match(shippingCardRule, /border:\s*2px solid var\(--jluxe-shipping-card-border/);
  assert.match(shippingCardRule, /border-radius:\s*var\(--jluxe-shipping-card-radius, 10px\)/);
  assert.match(shippingCardRule, /background-color:\s*var\(--jluxe-shipping-card-bg/);
  assert.match(shippingCardDetailsRule, /grid-area:\s*details/);
  assert.match(shippingCardDetailsRule, /direction:\s*rtl/);
  assert.match(shippingLabelRule, /overflow-wrap:\s*anywhere/);
  assert.match(shippingAmountRule, /white-space:\s*nowrap/);
  assert.match(shippingNoteRule, /grid-area:\s*note/);
  assert.match(shippingNoteRule, /justify-self:\s*start/);
  assert.match(shippingNoteRule, /min-width:\s*min\(9\.25rem, 100%\)/, "the note pill has a larger intrinsic minimum width");
  assert.match(shippingNoteRule, /width:\s*max-content/);
  assert.match(shippingNoteRule, /text-align:\s*center/);
  assert.match(shippingNoteRule, /white-space:\s*normal/, "long shipping notes wrap instead of being clipped with an ellipsis");
  assert.doesNotMatch(shippingNoteRule, /text-overflow:\s*ellipsis/);
  assert.match(checkoutSummaryShippingRule, /grid-template-areas:\s*"details" "note"/, "the narrow desktop checkout summary gives the note its own full-width row");
  assert.match(checkoutSummaryNoteRule, /width:\s*max-content/);
  assert.match(checkoutSummaryNoteRule, /max-width:\s*100%/);
  assert.match(selectedRule, /border-color:\s*var\(--jluxe-shipping-card-selected-border, #2563EB\)/);
  assert.match(selectedRule, /background-color:\s*var\(--jluxe-shipping-card-selected-bg, #EFF6FF\)/);
  assert.match(selectedRule, /box-shadow:/);
  assert.doesNotMatch(storefrontStyles, /input\.shipping_method:checked \+ \.jluxe-shipping-card--(?:post|tipax|chapar)/, "carrier selection colors come from the saved settings, not fixed CSS");
  assert.match(storefrontStyles, /@media \(max-width: 639\.98px\)[\s\S]*?grid-template-areas:\s*"details" "note"/);
  assert.match(storefrontStyles, /@media \(max-width: 359\.98px\)[\s\S]*?\.jluxe-shipping-note\s*\{/);
  assert.match(storefrontStyles, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.jluxe-shipping-card/);
  assert.match(storefrontStyles, /input\.shipping_method:checked ~ \.jluxe-shipping-method-accordion/);
  assert.match(shippingDetailsRule, /background:\s*var\(--jluxe-shipping-details-bg/);
  assert.match(shippingDetailsRule, /color:\s*var\(--jluxe-shipping-details-color/);

  assert.match(paymentSource, /function jluxe_shipping_method_presentation\( \$method \): array/);
  assert.doesNotMatch(reviewOrderTemplate, /jluxe-shipping-freight-note|data-jluxe-shipping-note/);
  assert.doesNotMatch(source, /updateShippingFreightNotice|shippingNoticeCopy|data-jluxe-shipping-note/);
  assert.doesNotMatch(cartTotalsTemplate, /هزینه.{0,20}ارسال.{0,40}بر اساس آدرس/);
});

test("all carrier explanations are editable and state postpaid delivery without checkout prepayment claims", () => {
  const start = themeSettingsSource.indexOf("'shipping_methods' => array(");
  const end = themeSettingsSource.indexOf("\n\t\t),\n\t\t// «آیتم‌های اضافه خرید»", start);
  assert.ok(start >= 0 && end > start, "shipping-method defaults are present in the new settings section");
  const shippingDefaults = themeSettingsSource.slice(start, end);
  const expectedCarrierNotes = {
    post: { note: ["اقتصادی", "#FECB02"], card: ["#FFFFFF", "#E2E8F0", "#EFF6FF", "#2563EB"] },
    tipax: { note: ["سریع‌تر، هزینه بالا", "#33936e"], card: ["#FFFFFF", "#E2E8F0", "#EFF6FF", "#2563EB"] },
    chapar: { note: ["مناسب مرسوله‌های حجیم", "#d71925"], card: ["#FFFFFF", "#E2E8F0", "#EFF6FF", "#2563EB"] },
  };
  for (const carrier of ["post", "tipax", "chapar"]) {
    const block = shippingDefaults.match(new RegExp(`'${carrier}'\\s*=>\\s*array\\(([\\s\\S]*?)\\n\\s*\\),`))?.[1] || "";
    assert.ok(block, `${carrier} has an independent setting block`);
    assert.match(block, /'enabled'\s*=>\s*true/);
    const description = block.match(/'description'\s*=>\s*'([^']+)'/)?.[1] || "";
    assert.match(description, /پس‌کرایه/);
    assert.match(description, /هنگام تحویل/);
    assert.doesNotMatch(description, /پیش.?پرداخت|پرداخت هنگام ثبت سفارش|همراه مبلغ سفارش/);
    assert.match(block, new RegExp(`'note'\\s*=>\\s*'${expectedCarrierNotes[carrier].note[0]}'`));
    assert.match(block, new RegExp(`'note_background_color'\\s*=>\\s*'${expectedCarrierNotes[carrier].note[1]}'`));
    for (const [field, index] of [
      ["card_background_color", 0],
      ["card_border_color", 1],
      ["selected_card_background_color", 2],
      ["selected_card_border_color", 3],
    ]) {
      assert.match(block, new RegExp(`'${field}'\\s*=>\\s*'${expectedCarrierNotes[carrier].card[index]}'`));
    }
    assert.match(block, /'note_text_color'\s*=>/);
    assert.match(block, /'details_background_color'\s*=>/);
    assert.match(block, /'details_text_color'\s*=>/);
  }
  assert.match(settingsAdminSource, /'jluxe-cart-checkout'\s*=>\s*array\( 'cart_checkout', 'jluxe_sanitize_cart_checkout' \)/);
  assert.match(settingsSanitizeSource, /function jluxe_sanitize_cart_checkout\(/);
  assert.match(settingsRenderSource, /jluxe_render_cart_checkout_color_field/);
  assert.match(themeSettingsSource, /'shipping_card_radius'\s*=>\s*10/);
  assert.match(settingsRenderSource, /array\( 10, 12, 16, 18, 22, 26 \)/);
  assert.match(settingsSanitizeSource, /array\( 10, 12, 16, 18, 22, 26 \)/);
  assert.match(settingsRenderSource, /cart_checkout\[shipping_card_radius\]/);
  assert.match(settingsSanitizeSource, /'shipping_card_radius'\s*=>\s*\$shipping_card_radius/);
  for (const field of ["card_background_color", "card_border_color", "selected_card_background_color", "selected_card_border_color", "note_background_color", "note_text_color", "details_background_color", "details_text_color"]) {
    assert.ok(settingsRenderSource.includes(`[${field}]`), `${field} is available to customize per carrier`);
    assert.ok(settingsSanitizeSource.includes(`'${field}'`), `${field} has an independent sanitizer mapping`);
  }
  for (const field of ["checkout_payment_warning_background", "checkout_payment_warning_text_color", "payment_card_background", "payment_card_selected_background"]) {
    assert.ok(settingsRenderSource.includes(`cart_checkout[${field}]`), `${field} is editable in the cart/checkout settings`);
    assert.ok(settingsSanitizeSource.includes(`'${field}'`), `${field} has a sanitizer mapping`);
  }
  assert.match(themeSettingsSource, /jluxe_array_merge_deep\( jluxe_theme_settings_defaults\(\), \$stored \)/);
  assert.doesNotMatch(reviewOrderTemplate, /پیش.?پرداخت|پرداخت هنگام ثبت سفارش/);
  assert.doesNotMatch(paymentTemplate, /پست پیشتاز.*پرداخت هنگام ثبت سفارش/);
});

test("payment gateways render as full-width horizontal cards in one responsive column", () => {
  assert.match(paymentTemplate, /<ul class="wc_payment_methods payment_methods methods jluxe-payment-method-list"/);
  assert.doesNotMatch(paymentTemplate, /flex-wrap|grid-template-columns/);
  assert.match(paymentMethodTemplate, /name="payment_method" value="<\?php echo esc_attr\( \$gateway->id \); \?>"/);
  assert.match(paymentMethodTemplate, /\$gateway->get_icon\(\)/);
  assert.match(paymentMethodTemplate, /\$gateway->get_title\(\)/);
  assert.match(paymentMethodTemplate, /\$gateway->payment_fields\(\)/);
  assert.match(paymentMethodTemplate, /--jluxe-payment-card-background/);
  assert.match(paymentMethodTemplate, /--jluxe-payment-card-selected-background/);

  const listRule = storefrontStyles.match(/#payment ul[^\{]*jluxe-payment-method-list[^\{]*\{([^}]+)\}/s)?.[1] || "";
  const itemRule = storefrontStyles.match(/#payment ul[^\{]*li\.jluxe-payment-method-item\s*\{([^}]+)\}/s)?.[1] || "";
  const cardRule = storefrontStyles.match(/#payment ul[^\{]*li label\.jluxe-payment-card\s*\{([^}]+)\}/s)?.[1] || "";
  assert.match(listRule, /display:\s*flex\s*!important/);
  assert.match(listRule, /flex-direction:\s*column\s*!important/);
  assert.match(itemRule, /width:\s*100%\s*!important/);
  assert.match(itemRule, /flex:\s*0 0 auto\s*!important/);
  assert.match(cardRule, /flex-direction:\s*row\s*!important/);
  assert.match(cardRule, /min-height:\s*4\.5rem/);
  assert.match(cardRule, /text-align:\s*start/);
  assert.match(cardRule, /background-color:\s*var\(--jluxe-payment-card-background/);
  assert.match(storefrontStyles, /#payment ul\.payment_methods li input\.payment_method:checked \+ label\.jluxe-payment-card\s*\{[^}]*--jluxe-payment-card-selected-background/s);
  assert.match(storefrontStyles, /jluxe-payment-method-item \.payment_box\s*\{[^}]*width:\s*100%/s);
  assert.doesNotMatch(storefrontStyles, /width:\s*(?:calc\(50%|152px)\s*!important/);
});

test("VPN warning background/text and payment-card colors come from validated checkout settings", () => {
  assert.match(paymentTemplate, /jluxe_cart_checkout_color\( 'checkout_payment_warning_background'/);
  assert.match(paymentTemplate, /jluxe_cart_checkout_color\( 'checkout_payment_warning_text_color'/);
  assert.match(paymentTemplate, /--jluxe-checkout-vpn-background/);
  assert.match(paymentTemplate, /--jluxe-checkout-vpn-text-color/);
  const warningRule = storefrontStyles.match(/body\.woocommerce-checkout \.jluxe-checkout-vpn-note\s*\{([^}]+)\}/s)?.[1] || "";
  assert.match(warningRule, /background:\s*var\(--jluxe-checkout-vpn-background/);
  assert.match(warningRule, /color:\s*var\(--jluxe-checkout-vpn-text-color/);
  assert.match(settingsRenderSource, /پس‌زمینهٔ هشدار خاموش‌کردن VPN/);
  assert.match(settingsRenderSource, /رنگ متن هشدار VPN/);
  assert.match(settingsRenderSource, /پس‌زمینهٔ عادی کارت درگاه‌ها/);
  assert.match(settingsRenderSource, /پس‌زمینهٔ کارت درگاه انتخاب‌شده/);
});

test("postcode guidance has strong readable contrast and wraps inside narrow checkout fields", () => {
  const rule = storefrontStyles.match(/body\.woocommerce-checkout :is\(#billing_postcode_field, #shipping_postcode_field\) \.description\s*\{([^}]+)\}/s)?.[1] || "";
  assert.match(rule, /color:\s*hsl\(var\(--info\)\)/);
  assert.match(rule, /background:\s*hsl\(var\(--info\)\s*\/\s*0\.08\)/);
  assert.match(rule, /opacity:\s*1/);
  assert.match(rule, /font-size:\s*0\.75rem/);
  assert.match(rule, /width:\s*100%/);
  assert.match(rule, /overflow-wrap:\s*anywhere/);

  const hslToken = (name) => {
    const line = storefrontStyles.split(/\r?\n/).find((entry) => entry.includes(`--${name}:`));
    const match = line?.match(/--[^:]+:\s*(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)%\s+(\d+(?:\.\d+)?)%/);
    assert.ok(match, `the ${name} HSL token exists`);
    return match.slice(1).map(Number);
  };
  const toRgb = ([hue, saturation, lightness]) => {
    const h = hue / 360;
    const s = saturation / 100;
    const l = lightness / 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs((h * 6) % 2 - 1));
    const m = l - c / 2;
    const channels = [[c, x, 0], [x, c, 0], [0, c, x], [0, x, c], [x, 0, c], [c, 0, x]][Math.floor(h * 6) % 6];
    return channels.map((channel) => channel + m);
  };
  const info = toRgb(hslToken("info"));
  const surface = toRgb(hslToken("surface"));
  const tintedSurface = info.map((channel, index) => channel * 0.08 + surface[index] * 0.92);
  const luminance = (rgb) => rgb
    .map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
    .reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);
  const contrast = (Math.max(luminance(info), luminance(tintedSurface)) + 0.05) /
    (Math.min(luminance(info), luminance(tintedSurface)) + 0.05);
  assert.ok(contrast >= 4.5, `default info text contrast is ${contrast.toFixed(2)}:1 (WCAG AA requires 4.5:1)`);
});

test("cart and checkout retain their stacked-mobile and two-column desktop layouts", () => {
  assert.ok(cartTemplate.includes("grid gap-6 lg:grid-cols-[1fr_320px]"));
  assert.ok(cartTemplate.includes("flex flex-col gap-4 p-4 sm:grid sm:grid-cols-[1fr_8rem_8rem]"));
  assert.ok(checkoutTemplate.includes("jluxe-checkout-layout grid gap-6 lg:grid-cols-[1fr_320px]"));
  assert.match(storefrontStyles, /@media \(max-width: 767\.98px\)[\s\S]*body\.woocommerce-checkout \.jluxe-checkout-layout/);
  assert.match(storefrontStyles, /@media \(min-width: 1024px\)[\s\S]*body\.woocommerce-checkout \.jluxe-checkout-layout\s*\{[^}]*grid-template-columns:/);
  assert.match(storefrontStyles, /@media \(max-width: 767\.98px\)[\s\S]*body\.woocommerce-cart \.jluxe-cart-actions\s*\{[^}]*position:\s*fixed/s);
});

test("the four checkout stages stay evenly spaced in RTL on mobile and desktop", () => {
  const listRule = storefrontStyles.match(/\.jluxe-checkout-stepper__list\s*\{([^}]+)\}/)?.[1] || "";
  assert.match(listRule, /grid-template-columns:\s*repeat\(4,\s*minmax\(0,\s*1fr\)\)/);
  assert.match(listRule, /direction:\s*rtl/);
  assert.match(storefrontStyles, /\.jluxe-checkout-step:not\(:last-child\)::after\s*\{[^}]*top:\s*calc\(var\(--jluxe-step-circle-size\)\s*\/\s*2\)/s);
  assert.match(storefrontStyles, /\.jluxe-checkout-step\[data-jluxe-step-state="done"\]:not\(:last-child\)::after/);
  const stepperStyles = storefrontStyles.slice(storefrontStyles.indexOf("/* Four checkout stages"));
  assert.match(stepperStyles, /@media \(min-width: 640px\)[\s\S]*?\.jluxe-checkout-step__label\s*\{[^}]*white-space:\s*nowrap;/);
});

test("the historical province-keyed selector populates cities and clears stale choices on a Select2 province change", (t) => {
  const { window, state, city, cityChanges } = bootCity({ state: "THR", initialCity: "تهران" });
  t.after(() => window.close());

  assert.equal(city.value, "تهران", "an already-saved city that exists in the selected province remains selected");
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "تهران", "ری", "اسلامشهر"]);
  assert.equal(city.options[0].textContent, "انتخاب شهرستان");

  state.value = "KHZ";
  window.jQuery(state).trigger("change");
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "اهواز", "آبادان"]);
  assert.equal(city.value, "", "changing province clears a city that is not valid for the new province");
  assert.equal(city.options[0].textContent, "انتخاب شهرستان");
  assert.equal(cityChanges(), 1, "the city select emits a real change event for WooCommerce listeners");

  state.value = "UNKNOWN";
  window.jQuery(state).trigger("change");
  assert.deepEqual(Array.from(city.options, (option) => option.value), [""]);
  assert.equal(city.options[0].textContent, "فهرست شهرستان‌های این استان در دسترس نیست");
  assert.equal(city.value, "");
});

test("R305 Android/browser address autofill refreshes the province city list even without a change event", async (t) => {
  const { window, state, city, cityChanges } = bootCity({ state: "THR" });
  t.after(() => window.close());

  state.focus();
  state.value = "KHZ"; // Simulate autofill changing a select.value silently.
  await new Promise((resolve) => window.setTimeout(resolve, 150));

  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "اهواز", "آبادان"]);
  assert.equal(city.value, "", "the dependent city selection is left ready for the customer to choose");
  assert.equal(city.options[0].textContent, "انتخاب شهرستان");
  assert.equal(cityChanges(), 1, "WooCommerce receives one city change after silent province autofill");
});

test("R305 the province list is rechecked if public city settings become available just after initialization", async (t) => {
  const { window, city } = bootCity({ state: "THR", map: {} });
  t.after(() => window.close());

  window.JLuxeThemeSettings.iranCities = cityMap;
  await new Promise((resolve) => window.setTimeout(resolve, 150));
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "تهران", "ری", "اسلامشهر"]);
});

test("account billing and shipping address forms both load and refresh their own province-scoped city selectors", (t) => {
  const {
    window,
    billingState,
    billingCity,
    shippingState,
    shippingCity,
  } = bootAccountCities();
  t.after(() => window.close());

  assert.equal(billingCity.value, "تهران");
  assert.deepEqual(Array.from(billingCity.options, (option) => option.value), ["", "تهران", "ری", "اسلامشهر"]);
  assert.equal(shippingCity.value, "اهواز");
  assert.deepEqual(Array.from(shippingCity.options, (option) => option.value), ["", "اهواز", "آبادان"]);

  billingState.value = "KHZ";
  window.jQuery(billingState).trigger("change");
  assert.deepEqual(Array.from(billingCity.options, (option) => option.value), ["", "اهواز", "آبادان"]);
  assert.equal(billingCity.value, "", "changing billing province clears its previous city");

  shippingState.value = "THR";
  window.jQuery(shippingState).trigger("change");
  assert.deepEqual(Array.from(shippingCity.options, (option) => option.value), ["", "تهران", "ری", "اسلامشهر"]);
  assert.equal(shippingCity.value, "", "shipping province changes do not reuse a billing selection");
});

test("checkout does not bind or rewrite a separate shipping-city selector", (t) => {
  const dom = new JSDOM(
    `<!doctype html><html><body class="woocommerce-checkout">
      <select id="shipping_state"><option value="THR" selected>تهران</option><option value="KHZ">خوزستان</option></select>
      <select id="shipping_city"><option value="">انتخاب شهر</option><option value="Shipping custom city" selected>Shipping custom city</option></select>
    </body></html>`,
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.JLuxeThemeSettings = { iranCities: cityMap, cartCheckout: { copy: {} } };
  window.jQuery = jqueryFactory(window);
  window.eval(cityScript);
  t.after(() => window.close());

  const state = window.document.getElementById("shipping_state");
  const city = window.document.getElementById("shipping_city");
  state.value = "KHZ";
  window.jQuery(state).trigger("change");
  assert.equal(city.value, "Shipping custom city");
  assert.deepEqual(Array.from(city.options, (option) => option.value), ["", "Shipping custom city"]);
});

test("checkout city prompts distinguish no selected province from an unavailable province list", (t) => {
  const { window, state, city } = bootCity({
    state: "",
    copy: {
      checkout_city_prompt: "استان را برای فهرست شهرها تعیین کنید",
      checkout_city_placeholder: "شهرستان را انتخاب کنید",
      checkout_city_unavailable: "فهرست این شهرستان‌ها آماده نیست",
    },
  });
  t.after(() => window.close());
  assert.equal(city.options[0].textContent, "استان را برای فهرست شهرها تعیین کنید");
  state.value = "THR";
  window.jQuery(state).trigger("change");
  assert.equal(city.options[0].textContent, "شهرستان را انتخاب کنید");
  state.value = "UNKNOWN";
  window.jQuery(state).trigger("change");
  assert.equal(city.options[0].textContent, "فهرست این شهرستان‌ها آماده نیست");
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
  let checkoutRefreshes = 0;
  $(document.body).on("update_checkout", () => checkoutRefreshes++);
  const city = document.getElementById("billing_city");
  const shippingError = document.querySelector("[data-jluxe-shipping-error]");
  const next = document.querySelector("[data-jluxe-checkout-next]");
  const actions = document.querySelector(".jluxe-checkout-step-actions");
  const paymentBack = document.querySelector("#jluxe-payment-submit [data-jluxe-checkout-back]");
  const cartBack = document.querySelector("[data-jluxe-checkout-to-cart]");
  const cartStep = document.querySelector('[data-jluxe-step="cart"]');
  const shippingStep = document.querySelector('[data-jluxe-step="shipping"]');
  const paymentStep = document.querySelector('[data-jluxe-step="payment"]');

  assert.equal(cartStep.dataset.jluxeStepState, "done");
  assert.equal(shippingStep.dataset.jluxeStepState, "active");
  assert.equal(shippingStep.getAttribute("aria-current"), "step");
  assert.equal(paymentStep.dataset.jluxeStepState, "pending");
  assert.equal(actions.hidden, false, "the shipping action bar starts visible");
  assert.equal(paymentBack.hidden, true, "the payment-stage back action starts hidden");
  assert.equal(cartBack.getAttribute("href"), "/basket/");
  assert.equal(cartBack.textContent, "بازگشت به سبد");
  assert.equal(next.textContent, "رفتن به مرحله پرداخت");
  assert.ok(actions.children[0].matches("[data-jluxe-checkout-to-cart]"));
  assert.ok(actions.children[1].matches("[data-jluxe-checkout-next]"));

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
  const topScrollsBeforePayment = scrolls.filter((entry) => entry.target === "window" && entry.top === 0).length;
  next.click();
  assert.equal(activeStep(document), "payment");
  assert.equal(shippingStep.dataset.jluxeStepState, "done");
  assert.equal(shippingStep.hasAttribute("aria-current"), false);
  assert.equal(paymentStep.dataset.jluxeStepState, "active");
  assert.equal(paymentStep.getAttribute("aria-current"), "step");
  assert.equal(document.getElementById("customer_details").style.display, "none");
  assert.equal(document.getElementById("jluxe-shipping-section").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, false);
  assert.equal(actions.hidden, true, "the empty shipping action bar is removed while payment is active");
  assert.equal(paymentBack.hidden, false, "the payment-stage back action appears beside place order");
  assert.equal(checkoutRefreshes, 0, "changing the local checkout step does not wait for or duplicate WooCommerce recalculation");
  assert.equal(scrolls.filter((entry) => entry.target === "window" && entry.top === 0).length, topScrollsBeforePayment + 1, "advancing to payment scrolls the page to its top");
  assert.equal(scrolls.filter((entry) => entry.target === "window").at(-1).behavior, "auto", "the stage transition does not add a long smooth-scroll delay");

  // The payment/shipping DOM lives in WooCommerce fragments. The historical
  // state machine reapplies visibility after updated_checkout without replacing
  // the native form validation or its notices.
  document.getElementById("jluxe-shipping-section").remove();
  document.getElementById("order_review").insertAdjacentHTML(
    "afterbegin",
    '<section id="jluxe-shipping-section"><input class="shipping_method" type="radio" data-jluxe-shipping-kind="tipax" name="shipping_method[0]" value="tipax:1" checked><label class="jluxe-shipping-card">تیپاکس</label><details class="jluxe-shipping-method-accordion"><summary>جزئیات ارسال</summary><div>کرایه پس‌کرایه است.</div></details><p data-jluxe-shipping-error hidden>روش ارسال را انتخاب کنید.</p></section>',
  );
  $(document.body).trigger("updated_checkout");
  assert.equal(document.getElementById("jluxe-shipping-section").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(document.querySelector(".jluxe-shipping-method-accordion").textContent.trim(), "جزئیات ارسالکرایه پس‌کرایه است.", "shipping descriptions remain inside the replacement WooCommerce fragment");
  assert.equal(document.querySelector("[data-jluxe-shipping-freight-note]"), null, "no separate freight notice is required after a checkout fragment refresh");

  const topScrollsBeforeShipping = scrolls.filter((entry) => entry.target === "window" && entry.top === 0).length;
  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(activeStep(document), "shipping");
  assert.equal(shippingStep.dataset.jluxeStepState, "active");
  assert.equal(shippingStep.getAttribute("aria-current"), "step");
  assert.equal(paymentStep.dataset.jluxeStepState, "pending");
  assert.equal(paymentStep.hasAttribute("aria-current"), false);
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, true);
  assert.equal(actions.hidden, false, "returning to shipping restores the shipping-stage action bar");
  assert.equal(paymentBack.hidden, true, "the payment-stage back action hides again after returning");
  assert.equal(scrolls.filter((entry) => entry.target === "window" && entry.top === 0).length, topScrollsBeforeShipping + 1, "returning to shipping also scrolls the page to its top");
});

test("hiding the visual checkout stepper leaves the real shipping-to-payment flow working", (t) => {
  const { window } = bootCheckout({ showStepper: false, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  assert.equal(document.querySelector(".jluxe-checkout-stepper"), null);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(document.getElementById("customer_details").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(document.getElementById("jluxe-payment-submit").hidden, false);
  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
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
