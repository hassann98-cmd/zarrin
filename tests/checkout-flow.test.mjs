import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
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
const paymentSource = fs.readFileSync(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
const themeSettingsSource = fs.readFileSync(new URL("../inc/theme-settings.php", import.meta.url), "utf8");
const settingsAdminSource = fs.readFileSync(new URL("../inc/theme-settings-admin.php", import.meta.url), "utf8");
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

test("shipping cards keep WooCommerce radios/hooks, full labels, and selected-method accordions", () => {
  assert.match(shippingTemplate, /<ul id="shipping_method" class="jluxe-shipping-method-list">/);
  assert.match(shippingTemplate, /<input type="radio" name="shipping_method\[%1\$d\]" data-index="%1\$d" id="%2\$s" value="%3\$s" class="shipping_method sr-only" data-jluxe-shipping-kind="%4\$s" \/>/);
  assert.match(shippingTemplate, /<label for="<\?php echo esc_attr\( \$input_id \); \?>" class="jluxe-shipping-card jluxe-shipping-card--<\?php echo esc_attr\( \$kind \); \?>">/);
  assert.match(shippingTemplate, /jluxe_cart_checkout_shipping_method_settings\( \$kind \)/);
  assert.match(shippingTemplate, /show_shipping_accordions/);
  assert.match(shippingTemplate, /<details class="jluxe-shipping-method-accordion/);
  assert.match(shippingTemplate, /do_action\( 'woocommerce_after_shipping_rate', \$method, \$index \)/);
  assert.doesNotMatch(shippingTemplate, /jluxe-shipping-card__hint/);
  const shippingRadio = shippingTemplate.match(/<input type="radio"[\s\S]*?\/>/)?.[0] || "";
  assert.ok(shippingRadio, "the shipping method is rendered as a real radio");
  assert.doesNotMatch(shippingRadio, /\bchecked\b/i);

  assert.match(storefrontStyles, /\.jluxe-shipping-method-list\s*\{[^}]*flex-direction:\s*column/s);
  assert.match(storefrontStyles, /input\.shipping_method:checked \+ \.jluxe-shipping-card/);
  assert.match(storefrontStyles, /input\.shipping_method:focus-visible \+ \.jluxe-shipping-card/);
  assert.match(storefrontStyles, /input\.shipping_method:checked ~ \.jluxe-shipping-method-accordion/);
  assert.match(storefrontStyles, /jluxe-shipping-card--post[\s\S]*?#eab308/);
  assert.match(storefrontStyles, /jluxe-shipping-card--tipax[\s\S]*?--success/);
  assert.match(storefrontStyles, /jluxe-shipping-card--chapar[\s\S]*?--error/);
  const shippingLabelRule = storefrontStyles.match(/\.jluxe-shipping-card__label\s*\{([^}]+)\}/s)?.[1] || "";
  assert.match(shippingLabelRule, /white-space:\s*normal/);
  assert.match(shippingLabelRule, /overflow-wrap:\s*anywhere/);
  assert.doesNotMatch(shippingLabelRule, /text-overflow:\s*ellipsis/);
  assert.doesNotMatch(storefrontStyles, /jluxe-shipping-card__hint|jluxe-shipping-freight-note/);

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
  for (const carrier of ["post", "tipax", "chapar"]) {
    const block = shippingDefaults.match(new RegExp(`'${carrier}'\\s*=>\\s*array\\(([\\s\\S]*?)\\n\\s*\\),`))?.[1] || "";
    assert.ok(block, `${carrier} has an independent setting block`);
    assert.match(block, /'enabled'\s*=>\s*true/);
    const description = block.match(/'description'\s*=>\s*'([^']+)'/)?.[1] || "";
    assert.match(description, /پس‌کرایه/);
    assert.match(description, /هنگام تحویل/);
    assert.doesNotMatch(description, /پیش.?پرداخت|پرداخت هنگام ثبت سفارش|همراه مبلغ سفارش/);
  }
  assert.match(settingsAdminSource, /'jluxe-cart-checkout'\s*=>\s*array\( 'cart_checkout', 'jluxe_sanitize_cart_checkout' \)/);
  assert.match(settingsSanitizeSource, /function jluxe_sanitize_cart_checkout\(/);
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
  assert.match(storefrontStyles, /jluxe-payment-method-item \.payment_box\s*\{[^}]*width:\s*100%/s);
  assert.doesNotMatch(storefrontStyles, /width:\s*(?:calc\(50%|152px)\s*!important/);
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

test("checkout city prompts use the configurable cart/checkout copy", (t) => {
  const { window, state, city } = bootCity({
    state: "UNKNOWN",
    copy: {
      checkout_city_prompt: "استان را برای فهرست شهرها تعیین کنید",
      checkout_city_placeholder: "شهرستان را انتخاب کنید",
    },
  });
  t.after(() => window.close());
  assert.equal(city.options[0].textContent, "استان را برای فهرست شهرها تعیین کنید");
  state.value = "THR";
  window.jQuery(state).trigger("change");
  assert.equal(city.options[0].textContent, "شهرستان را انتخاب کنید");
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
  assert.equal(scrolls.filter((entry) => entry.target === "window" && entry.top === 0).length, topScrollsBeforePayment + 1, "advancing to payment scrolls the page to its top");

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
