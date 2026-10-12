import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf(" * گیت‌کردنِ واقعیِ مرحله‌ی «روش پرداخت»");
const start = source.indexOf("(function () {", marker);
const end = source.indexOf("\n})();\n\n/**\n * اینپوتِ پرشِ صفحه", start);
assert.ok(marker >= 0 && start > marker && end > start, "checkout state machine can be isolated without executing unrelated storefront code");
const checkoutScript = source.slice(start, end + "\n})();".length);

function collection(nodes) {
  const list = nodes && nodes.nodeType ? [nodes] : Array.from(nodes || []);
  const result = {
    length: list.length,
    each(callback) {
      list.forEach((node, index) => callback.call(node, index, node));
      return this;
    },
    find(selector) {
      const requireVisible = selector.includes(":visible");
      const nativeSelector = selector.replaceAll(":visible", "").replaceAll(":checkbox", '[type="checkbox"]');
      const matches = list.flatMap((node) => Array.from(node.querySelectorAll(nativeSelector)));
      const isVisible = (element) => {
        for (let ancestor = element; ancestor && ancestor.nodeType === 1; ancestor = ancestor.parentElement) {
          if (ancestor.hidden || ancestor.style.display === "none") return false;
        }
        return true;
      };
      return collection(requireVisible ? matches.filter(isVisible) : matches);
    },
    is(selector) {
      const nativeSelector = selector.replaceAll(":checkbox", '[type="checkbox"]');
      return !!list[0]?.matches(nativeSelector);
    },
    val() {
      return list[0]?.value ?? null;
    },
    addClass(names) {
      for (const node of list) node.classList.add(...names.split(/\s+/));
      return this;
    },
    removeClass(names) {
      for (const node of list) node.classList.remove(...names.split(/\s+/));
      return this;
    },
  };
  list.forEach((node, index) => {
    result[index] = node;
  });
  return result;
}

function boot({ shippingSection = true, shippingOptions = true, shippingChecked = false, paymentRequired = true, paymentOptions = 1, reduceMotion = false, requiredField = false, locationFields = false, shippingAddressFields = false, shippingAddressVisible = false } = {}) {
  const step = (id, label, current = false) =>
    `<li data-jluxe-step="${id}" ${current ? 'aria-current="step"' : ""}>
      <span data-jluxe-step-circle data-jluxe-step-number="۱" class="border-border text-text-muted"></span>
      <span data-jluxe-step-label>${label}</span>
    </li>`;
  const shipping = !shippingSection
    ? ""
    : `<section id="jluxe-shipping-section">
        ${shippingOptions ? `<input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate" ${shippingChecked ? "checked" : ""}>` : ""}
        <p data-jluxe-shipping-error role="alert" hidden>روش ارسال را انتخاب کنید.</p>
      </section>`;
  const paymentRadios = Array.from({ length: paymentOptions }, (_, index) =>
    `<input class="payment_method" type="radio" name="payment_method" value="gateway-${index + 1}">`,
  ).join("");
  const requiredMarkup = requiredField
    ? '<p class="form-row validate-required" id="billing_first_name_field"><input class="input-text" id="billing_first_name" type="text" value=""></p>'
    : "";
  const locationMarkup = locationFields
    ? `<p class="form-row validate-required" id="billing_state_field"><label for="billing_state">استان</label><select id="billing_state" data-jluxe-state-select><option value="">انتخاب استان</option><option value="TE">تهران</option><option value="KZ">خوزستان</option><option value="2241">کردستان</option><option value="2274">کرمان</option></select></p>
      <p class="form-row validate-required" id="billing_city_field"><label for="billing_city">شهر</label><select id="billing_city" data-jluxe-city-select data-jluxe-state-field="billing_state" disabled><option value="">ابتدا استان را انتخاب کنید</option></select></p>`
    : "";
  const shippingAddressMarkup = shippingAddressFields
    ? `<div id="shipping_address" class="shipping_address" ${shippingAddressVisible ? "" : 'style="display: none;"'}>
        <p class="form-row validate-required" id="shipping_first_name_field"><input class="input-text" id="shipping_first_name" type="text" value=""></p>
      </div>`
    : "";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <nav class="jluxe-checkout-stepper">
        <ol>
          ${step("cart", "بررسی سبد")}
          ${step("shipping", "اطلاعات ارسال", true)}
          ${step("payment", "روش پرداخت")}
          ${step("done", "پایان خرید")}
        </ol>
      </nav>
      <form class="checkout">
        <div id="customer_details"><h3 id="jluxe-shipping-heading" tabindex="-1">اطلاعات ارسال</h3>${requiredMarkup}${locationMarkup}${shippingAddressMarkup}</div>
        <div id="jluxe-payment-column" hidden>
          <div id="payment" data-jluxe-payment-required="${paymentRequired ? "1" : "0"}">
            <h2 id="jluxe-payment-heading" tabindex="-1">روش پرداخت</h2>
            ${paymentRadios}
          </div>
        </div>
        <div id="order_review">${shipping}</div>
        <div id="jluxe-payment-submit" hidden>
          <p data-jluxe-payment-error role="alert" hidden>روش پرداخت را انتخاب کنید.</p>
          <button id="place_order" type="submit">ثبت سفارش</button>
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/store/checkout/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  const scrolls = [];
  let updatedCheckout;
  window.HTMLElement.prototype.scrollIntoView = function (options) {
    scrolls.push({ id: this.id, behavior: options?.behavior, block: options?.block });
  };
  window.matchMedia = (query) => ({ matches: reduceMotion && query.includes("prefers-reduced-motion") });
  window.jluxeWcSettings = {
    cartUrl: "/store/basket/",
    checkoutAddressData: {
      state_aliases: { TE: "تهران", KZ: "خوزستان", THR: "تهران", KRD: "کردستان" },
      provinces: [
        { id: 8, code: 23, name: "تهران", cities: ["تهران", "ری", "اسلامشهر"] },
        { id: 13, code: 6, name: "خوزستان", cities: ["اهواز", "آبادان"] },
        { id: 21, code: 20, name: "کردستان", cities: ["آرمرده", "اورامان تخت", "سنندج"] },
        { id: 22, code: 8, name: "کرمان", cities: ["اختیارآباد"] },
      ],
    },
  };
  window.jQuery = (target) => {
    const wrapped = collection(target);
    wrapped.on = function (event, callback) {
      if (target === window.document.body && event === "updated_checkout") updatedCheckout = callback;
      return wrapped;
    };
    wrapped.trigger = function () {
      return wrapped;
    };
    return wrapped;
  };
  window.eval(checkoutScript);
  return { dom, window, scrolls, updatedCheckout: () => updatedCheckout?.() };
}

function currentStep(document) {
  return document.querySelector('.jluxe-checkout-stepper [aria-current="step"]')?.getAttribute("data-jluxe-step");
}

function submitWithoutCreatingAnOrder(window) {
  let reachedForm = false;
  const form = window.document.querySelector("form.checkout");
  form.addEventListener("submit", () => {
    reachedForm = true;
  });
  const event = new window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(event);
  return { event, reachedForm };
}

function dispatchChange(window, target) {
  target.dispatchEvent(new window.Event("change", { bubbles: true }));
}

test("checkout blocks missing shipping rates, refreshes the explanation, and focuses the revealed payment step", (t) => {
  const { dom, window, scrolls, updatedCheckout } = boot({ shippingOptions: false, reduceMotion: true });
  t.after(() => window.close());
  const { document } = window;
  const next = document.querySelector("[data-jluxe-checkout-next]");
  const shippingError = document.querySelector("[data-jluxe-shipping-error]");

  next.click();
  assert.equal(shippingError.hidden, false);
  assert.match(shippingError.textContent, /روش ارسالی.*در دسترس نیست/);
  assert.equal(currentStep(document), "shipping");

  const shippingSection = document.getElementById("jluxe-shipping-section");
  shippingSection.insertAdjacentHTML("afterbegin", '<input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate">');
  updatedCheckout();
  assert.equal(shippingError.hidden, false);
  assert.equal(shippingError.textContent, "روش ارسال را انتخاب کنید.");

  const rate = shippingSection.querySelector("input.shipping_method");
  rate.checked = true;
  rate.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(shippingError.hidden, true);
  next.click();

  assert.equal(document.getElementById("customer_details").style.display, "none");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(currentStep(document), "payment");
  assert.equal(document.activeElement.id, "jluxe-payment-heading");
  assert.ok(scrolls.some((entry) => entry.id === "jluxe-payment-heading" && entry.behavior === "auto"));

  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
  assert.equal(currentStep(document), "shipping");
  assert.equal(document.activeElement.id, "jluxe-shipping-heading");
});

test("checkout fragment refresh does not auto-select a shipping rate", (t) => {
  const { window, updatedCheckout } = boot({ shippingChecked: false });
  t.after(() => window.close());
  const { document } = window;

  updatedCheckout();
  assert.equal(document.querySelector("input.shipping_method").checked, false);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "shipping");
  assert.equal(document.querySelector("[data-jluxe-shipping-error]").hidden, false);
});

test("an explicitly selected shipping rate survives WooCommerce fragment replacement and reaches final submit", (t) => {
  const { window, updatedCheckout } = boot({ shippingChecked: false, paymentOptions: 2 });
  t.after(() => window.close());
  const { document } = window;

  const selectedRate = document.querySelector("input.shipping_method");
  selectedRate.checked = true;
  dispatchChange(window, selectedRate);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");

  // Core WooCommerce replaces the order-review fragment with fresh unchecked radios.
  document.getElementById("jluxe-shipping-section").innerHTML =
    '<input class="shipping_method" type="radio" name="shipping_method[0]" value="flat_rate"><p data-jluxe-shipping-error role="alert" hidden>روش ارسال را انتخاب کنید.</p>';
  updatedCheckout();
  assert.equal(document.querySelector("input.shipping_method").checked, true);

  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  dispatchChange(window, gateway);
  const submission = submitWithoutCreatingAnOrder(window);
  assert.equal(submission.event.defaultPrevented, false);
  assert.equal(submission.reachedForm, true, "the retained rate passes preflight and reaches the real form submit event");
});

test("virtual-only orders can advance without a shipping section", (t) => {
  const { dom, window } = boot({ shippingSection: false });
  t.after(() => window.close());
  window.document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(window.document), "payment");
  assert.equal(window.document.getElementById("jluxe-payment-column").hidden, false);
  assert.equal(window.document.querySelector("[data-jluxe-shipping-error]"), null);
});

test("required address fields block progression and return keyboard focus to the invalid field", (t) => {
  const { dom, window } = boot({ requiredField: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  document.querySelector("[data-jluxe-checkout-next]").click();

  const requiredRow = document.getElementById("billing_first_name_field");
  assert.equal(currentStep(document), "shipping");
  assert.ok(requiredRow.classList.contains("woocommerce-invalid-required-field"));
  assert.ok(requiredRow.querySelector("[data-jluxe-required-field-error]")?.textContent.includes("فیلد الزامی"), "the blocked step explains that the required field must be completed");
  assert.equal(document.activeElement.id, "billing_first_name");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);

  document.getElementById("billing_first_name").value = "Example";
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  assert.equal(requiredRow.classList.contains("woocommerce-invalid-required-field"), false);
  assert.equal(requiredRow.querySelector("[data-jluxe-required-field-error]"), null, "the warning disappears once the required value is present");
});

test("province selection loads only its sorted city options and blocks checkout until a city is chosen", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");

  assert.equal(city.disabled, true);
  const provinceNames = Array.from(state.options).filter((option) => option.value).map((option) => option.textContent);
  assert.deepEqual(
    provinceNames,
    provinceNames.slice().sort(new Intl.Collator("fa", { sensitivity: "base" }).compare),
    "province labels are presented in Persian alphabetical order",
  );

  state.value = "TE";
  state.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(city.disabled, false);
  assert.deepEqual(
    Array.from(city.options).slice(1).map((option) => option.value),
    ["اسلامشهر", "تهران", "ری"],
    "selecting Tehran loads only its alphabetized counties/cities",
  );
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "shipping");
  const cityRow = document.getElementById("billing_city_field");
  assert.ok(cityRow.classList.contains("woocommerce-invalid-required-field"));
  assert.ok(cityRow.querySelector("[data-jluxe-required-field-error]")?.textContent.includes("شهر"), "an empty required city selection shows a direct warning");

  city.value = "تهران";
  city.dispatchEvent(new window.Event("change", { bubbles: true }));
  state.value = "KZ";
  state.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(city.value, "", "changing province clears the previous city selection");
  assert.deepEqual(
    Array.from(city.options).slice(1).map((option) => option.value),
    ["آبادان", "اهواز"],
    "the city list is replaced with only the new province options",
  );
  city.value = "اهواز";
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
});

test("a province label or prefixed WooCommerce state code resolves to the same local city list", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");

  state.add(new window.Option("تهران", "23"));
  state.value = "23";
  dispatchChange(window, state);
  assert.deepEqual(Array.from(city.options).slice(1).map((option) => option.value), ["اسلامشهر", "تهران", "ری"]);
  city.value = "تهران";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment", "an extension-specific state code is resolved from the actual checkout option label");

  state.add(new window.Option("Tehran", "IR-THR"));
  state.value = "IR-THR";
  dispatchChange(window, state);
  assert.deepEqual(Array.from(city.options).slice(1).map((option) => option.value), ["اسلامشهر", "تهران", "ری"], "IR-THR is normalized through the shared state alias");
});

test("numeric WooCommerce city IDs validate by their visible city label, without allowing another province's city", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  state.value = "2241";
  dispatchChange(window, state);

  city.replaceChildren(new window.Option("لطفا شهر خود را انتخاب نمایید", "0"), new window.Option("آرمرده", "2242"));
  city.value = "2242";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment", "a numeric city ID is accepted through the visible label from the selected province");

  document.querySelector("[data-jluxe-checkout-back]").click();
  city.replaceChildren(new window.Option("لطفا شهر خود را انتخاب نمایید", "0"), new window.Option("اختیارآباد", "2275"));
  city.value = "2275";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "shipping", "a numeric ID whose city label belongs to Kerman is rejected for Kurdistan");
  assert.ok(document.querySelector("[data-jluxe-city-province-error]")?.textContent.includes("هم‌خوانی ندارد"));
});

test("a city outside the selected province blocks the next step with a clear warning and can be corrected", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  state.value = "TE";
  dispatchChange(window, state);

  city.add(new window.Option("اهواز", "اهواز"));
  city.value = "اهواز";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();

  const cityRow = document.getElementById("billing_city_field");
  const warning = cityRow.querySelector("[data-jluxe-city-province-error]");
  assert.equal(currentStep(document), "shipping", "mismatched city cannot reveal the payment stage");
  assert.ok(warning?.textContent.includes("شهر انتخاب‌شده با استان هم‌خوانی ندارد"), "an actionable warning is shown beside the city field");
  assert.equal(document.activeElement.id, "billing_city");
  assert.ok(cityRow.classList.contains("woocommerce-invalid"));

  city.value = "تهران";
  dispatchChange(window, city);
  assert.equal(cityRow.querySelector("[data-jluxe-city-province-error]"), null, "the warning clears as soon as a valid city is selected");
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
});

test("final submit returns to shipping when the city no longer belongs to the selected province", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  state.value = "TE";
  dispatchChange(window, state);
  city.value = "تهران";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");

  state.value = "KZ";
  dispatchChange(window, state);
  city.add(new window.Option("تهران", "تهران"));
  city.value = "تهران";
  dispatchChange(window, city);
  const result = submitWithoutCreatingAnOrder(window);
  assert.equal(result.event.defaultPrevented, true);
  assert.equal(result.reachedForm, false);
  assert.equal(currentStep(document), "shipping");
  assert.ok(document.querySelector("[data-jluxe-city-province-error]")?.textContent.includes("هم‌خوانی ندارد"));
});

test("final validation ignores WooCommerce's hidden ship-to-billing fields but still validates an active shipping address", (t) => {
  const sameAddress = boot({ requiredField: true, shippingChecked: true, shippingAddressFields: true });
  const differentAddress = boot({ requiredField: true, shippingChecked: true, shippingAddressFields: true, shippingAddressVisible: true });
  t.after(() => sameAddress.window.close());
  t.after(() => differentAddress.window.close());

  const { document: sameAddressDocument } = sameAddress.window;
  sameAddressDocument.getElementById("billing_first_name").value = "Valid billing name";
  assert.equal(sameAddressDocument.getElementById("shipping_address").style.display, "none");
  sameAddressDocument.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(sameAddressDocument), "payment");
  const sameAddressGateway = sameAddressDocument.querySelector("input.payment_method");
  sameAddressGateway.checked = true;
  const allowed = submitWithoutCreatingAnOrder(sameAddress.window);
  assert.equal(allowed.event.defaultPrevented, false, "empty shipping fields are ignored when WooCommerce is shipping to the valid billing address");
  assert.equal(allowed.reachedForm, true, "valid billing data and a selected gateway reach the checkout form submit event");

  const { document: differentAddressDocument } = differentAddress.window;
  differentAddressDocument.getElementById("billing_first_name").value = "Valid billing name";
  differentAddressDocument.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(differentAddressDocument), "shipping");
  assert.ok(differentAddressDocument.getElementById("shipping_first_name_field").classList.contains("woocommerce-invalid-required-field"), "visible required shipping fields remain enforced when shipping to a different address");
  assert.equal(differentAddressDocument.activeElement.id, "shipping_first_name");

  differentAddressDocument.getElementById("shipping_first_name").value = "Valid recipient";
  differentAddressDocument.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(differentAddressDocument), "payment");
  differentAddressDocument.querySelector("input.payment_method").checked = true;
  const differentAddressSubmit = submitWithoutCreatingAnOrder(differentAddress.window);
  assert.equal(differentAddressSubmit.event.defaultPrevented, false);
  assert.equal(differentAddressSubmit.reachedForm, true);
});

test("form submission cannot bypass required address and shipping stages, including after payment is shown", (t) => {
  const { window } = boot({ requiredField: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;

  const blockedOnShipping = submitWithoutCreatingAnOrder(window);
  assert.equal(blockedOnShipping.event.defaultPrevented, true);
  assert.equal(blockedOnShipping.reachedForm, false);
  assert.equal(currentStep(document), "shipping");
  assert.equal(document.activeElement.id, "billing_first_name");

  document.getElementById("billing_first_name").value = "Example";
  const advancedByEnter = submitWithoutCreatingAnOrder(window);
  assert.equal(advancedByEnter.event.defaultPrevented, true);
  assert.equal(advancedByEnter.reachedForm, false, "Enter advances through the validated step button, not WooCommerce checkout submit");
  assert.equal(currentStep(document), "payment");

  document.getElementById("billing_first_name").value = "";
  const blockedOnPayment = submitWithoutCreatingAnOrder(window);
  assert.equal(blockedOnPayment.event.defaultPrevented, true);
  assert.equal(blockedOnPayment.reachedForm, false);
  assert.equal(currentStep(document), "shipping", "hidden required address fields are rechecked before final submit");
  assert.ok(document.getElementById("billing_first_name_field").classList.contains("woocommerce-invalid-required-field"));
});

test("checkout never submits a payment-required order without a gateway, but allows a zero-payment order", (t) => {
  const unavailable = boot({ shippingChecked: true, paymentOptions: 0, paymentRequired: true });
  t.after(() => unavailable.window.close());
  unavailable.window.document.querySelector("[data-jluxe-checkout-next]").click();
  const blocked = submitWithoutCreatingAnOrder(unavailable.window);
  assert.equal(blocked.event.defaultPrevented, true);
  assert.equal(blocked.reachedForm, false, "the validation capture listener prevents WooCommerce submission");
  const paymentError = unavailable.window.document.querySelector("[data-jluxe-payment-error]");
  assert.equal(paymentError.hidden, false);
  assert.equal(paymentError.textContent, "درگاه پرداختی برای این سفارش در دسترس نیست.");

  const freeOrder = boot({ shippingChecked: true, paymentOptions: 1, paymentRequired: false });
  t.after(() => freeOrder.window.close());
  freeOrder.window.document.querySelector("[data-jluxe-checkout-next]").click();
  const allowed = submitWithoutCreatingAnOrder(freeOrder.window);
  assert.equal(allowed.event.defaultPrevented, false);
  assert.equal(allowed.reachedForm, true, "a no-payment order is not blocked when WooCommerce exposes no gateway");
});

test("a payment gateway must be chosen explicitly before the checkout submit event can continue", (t) => {
  const { dom, window } = boot({ shippingChecked: true, paymentOptions: 1, paymentRequired: true });
  t.after(() => window.close());
  window.document.querySelector("[data-jluxe-checkout-next]").click();

  const blocked = submitWithoutCreatingAnOrder(window);
  assert.equal(blocked.event.defaultPrevented, true);
  assert.equal(blocked.reachedForm, false);
  assert.equal(window.document.querySelector("[data-jluxe-payment-error]").textContent, "روش پرداخت را انتخاب کنید.");

  const gateway = window.document.querySelector("input.payment_method");
  gateway.checked = true;
  gateway.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(window.document.querySelector("[data-jluxe-payment-error]").hidden, true);
  const allowed = submitWithoutCreatingAnOrder(window);
  assert.equal(allowed.event.defaultPrevented, false);
  assert.equal(allowed.reachedForm, true, "this dispatch is a DOM-only assertion; no WooCommerce or network request runs");
});
