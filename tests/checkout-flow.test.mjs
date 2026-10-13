import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

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

function boot({ shippingSection = true, shippingOptions = true, shippingChecked = false, paymentRequired = true, paymentOptions = 1, reduceMotion = false, requiredField = false, locationFields = false, initialState = "", initialCity = null, duplicateLocationControl = false, shippingAddressFields = false, shippingAddressVisible = false } = {}) {
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
    ? `<p class="form-row validate-required" id="billing_state_field"><label for="billing_state">استان</label><select id="billing_state" name="billing_state" class="select2-hidden-accessible" data-jluxe-state-select><option value="">انتخاب استان</option><option value="TE" ${initialState === "TE" ? "selected" : ""}>تهران</option><option value="KZ" ${initialState === "KZ" ? "selected" : ""}>خوزستان</option><option value="2241" ${initialState === "2241" ? "selected" : ""}>کردستان</option><option value="2274" ${initialState === "2274" ? "selected" : ""}>کرمان</option><option value="2352" ${initialState === "2352" ? "selected" : ""}>کرمانشاه</option></select></p>
      <p class="form-row validate-required" id="billing_city_field"><label for="billing_city">شهر</label><select id="billing_city" name="billing_city" class="select2-hidden-accessible" data-jluxe-city-select data-jluxe-state-field="billing_state" ${initialState ? "" : "disabled"}><option value="">ابتدا استان را انتخاب کنید</option>${initialCity ? `<option value="${initialCity.value}" selected>${initialCity.label}</option>` : ""}</select></p>`
    : "";
  const decoyLocationMarkup = duplicateLocationControl
    ? '<div hidden><select data-jluxe-city-select><option value="">Hidden duplicate city</option></select></div>'
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
        ${decoyLocationMarkup}
        <div id="customer_details"><h3 id="jluxe-shipping-heading" tabindex="-1">اطلاعات ارسال</h3>${requiredMarkup}${locationMarkup}${shippingAddressMarkup}</div>
        <input type="hidden" name="jluxe_billing_city_label" data-jluxe-city-label-payload value="">
        <div id="jluxe-payment-column" hidden>
          <div id="payment">
            ${paymentRequired ? paymentRadios : ""}
          </div>
        </div>
        <div id="order_review">${shipping}</div>
        <div id="jluxe-payment-submit" hidden>
          <div class="form-row place-order mt-4">
            <div class="mt-3">
              <label><input id="terms" type="checkbox" name="terms" value="1"> شرایط را می‌پذیرم</label>
            </div>
            <p data-jluxe-payment-error hidden>روش پرداخت را انتخاب کنید.</p>
            <div class="mt-4">
              <button id="place_order" name="woocommerce_checkout_place_order" type="submit">ثبت سفارش</button>
            </div>
            <input type="hidden" name="woocommerce-process-checkout-nonce" value="test-nonce">
          </div>
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/store/checkout/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  const scrolls = [];
  let updatedCheckout;
  const select2Refreshes = [];
  const jquery = jqueryFactory(window);
  const originalOn = jquery.fn.on;
  const originalTrigger = jquery.fn.trigger;
  jquery.fn.on = function (...args) {
    if (this[0] === window.document.body && args[0] === "updated_checkout") updatedCheckout = args[1];
    return originalOn.apply(this, args);
  };
  jquery.fn.trigger = function (event, ...args) {
    if (this[0]?.tagName === "SELECT" && event === "change.select2") select2Refreshes.push(this[0].id);
    return originalTrigger.call(this, event, ...args);
  };
  window.HTMLElement.prototype.scrollIntoView = function (options) {
    scrolls.push({ id: this.id, behavior: options?.behavior, block: options?.block });
  };
  window.matchMedia = (query) => ({ matches: reduceMotion && query.includes("prefers-reduced-motion") });
  window.jluxeWcSettings = {
    cartUrl: "/store/basket/",
    checkoutAddressData: {
      state_aliases: { TE: "تهران", KZ: "خوزستان", THR: "تهران", KRD: "کردستان", KRN: "کرمان", KRH: "کرمانشاه" },
      provinces: [
        { id: 8, code: 23, name: "تهران", cities: ["تهران", "ری", "اسلامشهر"] },
        { id: 13, code: 6, name: "خوزستان", cities: ["اهواز", "آبادان"] },
        { id: 21, code: 20, name: "کردستان", cities: ["آرمرده", "اورامان تخت", "سنندج"] },
        { id: 22, code: 8, name: "کرمان", cities: ["اختیارآباد"] },
        { id: 23, code: "KRH", name: "کرمانشاه", cities: ["اسلام آبادغرب", "بیستون", "جوانرود", "کرمانشاه"] },
      ],
    },
  };
  window.JLuxeThemeSettings = {
    iranCities: {
      KRH: ["کرمانشاه", "اسلام‌آباد غرب", "سنقر", "کنگاور", "پاوه", "هرسین", "صحنه", "سرپل‌ذهاب", "جوانرود"],
    },
  };
  window.jQuery = (target) => {
    const wrapped = collection(target);
    const actual = jquery(target);
    wrapped.on = function (...args) {
      if (target === window.document.body && args[0] === "updated_checkout") updatedCheckout = args[1];
      actual.on(...args);
      return wrapped;
    };
    wrapped.one = function (...args) {
      actual.one(...args);
      return wrapped;
    };
    wrapped.off = function (...args) {
      actual.off(...args);
      return wrapped;
    };
    wrapped.triggerHandler = function (...args) {
      return actual.triggerHandler(...args);
    };
    wrapped.trigger = function (event, args = []) {
      if (target?.tagName === "SELECT" && event === "change.select2") select2Refreshes.push(target.id);
      actual.trigger(event, args);
      return wrapped;
    };
    return wrapped;
  };
  window.eval(checkoutScript);
  return { dom, window, scrolls, select2Refreshes, updatedCheckout: () => updatedCheckout?.() };
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

test("checkout blocks missing shipping rates and toggles the v1.7.58 payment step without changing WooCommerce's real submit path", (t) => {
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
  assert.ok(scrolls.some((entry) => entry.block === "start"), "the original checkout step controls are brought into view");

  document.querySelector("[data-jluxe-checkout-back]").click();
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
  assert.equal(currentStep(document), "shipping");
  assert.ok(scrolls.some((entry) => entry.block === "start"), "returning to shipping scrolls the checkout step controls into view");
});

test("step actions keep WooCommerce's place-order button, terms, and nonce in the checkout form", (t) => {
  const { window } = boot({ shippingChecked: false, paymentOptions: 2 });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");
  const actions = document.querySelector(".jluxe-checkout-step-actions");
  const orderReview = document.getElementById("order_review");
  const paymentSubmit = document.getElementById("jluxe-payment-submit");
  const placeOrder = document.getElementById("place_order");
  const next = document.querySelector("[data-jluxe-checkout-next]");
  const toCart = document.querySelector("[data-jluxe-checkout-to-cart]");
  const back = document.querySelector("[data-jluxe-checkout-back]");

  assert.equal(actions.previousElementSibling, orderReview, "the original step controls sit after the AJAX-updated order-review fragment");
  assert.equal(actions.nextElementSibling, paymentSubmit);
  assert.equal(actions.getAttribute("data-jluxe-checkout-step"), null);
  assert.equal(actions.hidden, false);
  assert.equal(paymentSubmit.hidden, true);
  assert.equal(back.closest("[data-jluxe-checkout-place-order-row]"), null, "the Back control does not wrap or replace the WooCommerce submit control");
  assert.equal(placeOrder.closest("[data-jluxe-checkout-place-order-row]"), null);
  assert.equal(placeOrder.closest(".form-row.place-order"), paymentSubmit.querySelector(".form-row.place-order"));
  assert.equal(placeOrder.type, "submit");
  assert.equal(placeOrder.name, "woocommerce_checkout_place_order");
  assert.equal(form.contains(placeOrder), true);
  assert.equal(paymentSubmit.querySelector("[name=terms]")?.closest("#jluxe-payment-submit"), paymentSubmit);
  assert.equal(paymentSubmit.querySelector("[name=woocommerce-process-checkout-nonce]")?.closest("#jluxe-payment-submit"), paymentSubmit);

  const shippingRate = document.querySelector("input.shipping_method");
  shippingRate.checked = true;
  dispatchChange(window, shippingRate);
  next.click();
  assert.equal(next.hidden, true);
  assert.equal(toCart.hidden, true);
  assert.equal(actions.hidden, false);
  assert.equal(paymentSubmit.hidden, false);
  assert.equal(back.hidden, false);

  back.click();
  assert.equal(actions.hidden, false);
  assert.equal(next.hidden, false);
  assert.equal(toCart.hidden, false);
  assert.equal(paymentSubmit.hidden, true);
  assert.equal(back.hidden, true);
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

test("Select2 jQuery changes populate billing city and keep its chosen value in the real checkout form payload", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");

  assert.equal(city.disabled, true, "a new checkout starts with city disabled until a province is selected");
  state.value = "2352";
  window.jQuery(state).trigger("change"); // Select2 emits its change through jQuery, not a native DOM event.
  assert.equal(city.disabled, false, "the Select2 province event enables the dependent city field");
  assert.ok(Array.from(city.options).some((option) => option.value === "بیستون"));

  city.value = "بیستون";
  window.jQuery(city).trigger("change");
  assert.equal(city.value, "بیستون", "the chosen city is canonicalized on the same jQuery change path");
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  document.querySelector("input.payment_method").checked = true;

  const submit = submitWithoutCreatingAnOrder(window);
  assert.equal(submit.event.defaultPrevented, false, "the valid checkout proceeds through the normal form submit gate");
  const finalFields = new window.FormData(form);
  assert.equal(finalFields.get("billing_state"), "2352");
  assert.equal(finalFields.get("billing_city"), "بیستون", "WooCommerce's successful-controls payload includes the selected billing city");
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

test("Kermanshah's exact option label wins over Kerman's substring for numeric state IDs", (t) => {
  const { window, select2Refreshes } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2352",
    initialCity: { value: "2275", label: "اختیارآباد" },
  });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");

  assert.equal(state.value, "2352");
  assert.equal(state.options[state.selectedIndex].textContent, "کرمانشاه");
  assert.equal(city.dataset.jluxeProvince, "کرمانشاه", "the province resolver must match the full selected label before substring fallback");
  assert.ok(Array.from(city.options).some((option) => option.value === "جوانرود"));
  assert.ok(!Array.from(city.options).some((option) => option.textContent === "اختیارآباد"), "a Kerman city is not retained in the Kermanshah list");
  assert.equal(city.value, "", "a prior city from the wrong province is cleared");
  assert.ok(select2Refreshes.includes("billing_state") && select2Refreshes.includes("billing_city"), "both Select2 labels are refreshed after native options change");

  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "shipping", "the required city cannot be bypassed after the incorrect city is cleared");
  assert.ok(document.getElementById("billing_city_field").classList.contains("woocommerce-invalid-required-field"));

  city.value = "کرمانشاه";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment", "a city from the exact province passes the normal checkout gate");

  document.querySelector("[data-jluxe-checkout-back]").click();
  city.replaceChildren(new window.Option("لطفا شهر خود را انتخاب نمایید", "0"), new window.Option("اختیارآباد", "2275"));
  city.value = "2275";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment", "a non-empty extension value follows the normal WooCommerce submit path");
  assert.equal(city.value, "2275", "the theme does not rewrite a numeric ID unless its label matches the selected province");
  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  dispatchChange(window, gateway);
  const submission = submitWithoutCreatingAnOrder(window);
  assert.equal(submission.event.defaultPrevented, false, "WooCommerce receives the cross-province value for authoritative server validation");
  assert.equal(submission.reachedForm, true);
});

test("zero-valued city placeholders are treated as empty, not as valid selections", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true, initialState: "TE" });
  t.after(() => window.close());
  const { document } = window;
  const city = document.getElementById("billing_city");
  city.replaceChildren(new window.Option("لطفا شهر خود را انتخاب نمایید", "0"), new window.Option("تهران", "تهران"));
  city.value = "0";

  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "shipping");
  assert.ok(document.getElementById("billing_city_field").classList.contains("woocommerce-invalid-required-field"));
  assert.ok(document.getElementById("billing_city_field").querySelector("[data-jluxe-required-field-error]"));
  assert.equal(document.querySelector("[data-jluxe-city-province-error]"), null, "a placeholder is reported as missing, not falsely described as a province mismatch");
});

test("WooCommerce billing-city checkout errors reopen the hidden shipping step, but gateway errors stay on payment", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true, initialState: "2352" });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");
  const city = document.getElementById("billing_city");
  city.value = "کرمانشاه";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  assert.equal(document.getElementById("customer_details").style.display, "none");

  form.insertAdjacentHTML("afterbegin", '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div role="alert"><ul class="woocommerce-error"><li>پرداخت از طریق درگاه ناموفق بود.</li></ul></div></div>');
  window.jQuery(document.body).trigger("checkout_error");
  assert.equal(currentStep(document), "payment", "an unrelated gateway error does not move the customer away from payment");

  form.querySelector(".woocommerce-NoticeGroup-checkout").remove();
  city.value = "";
  form.insertAdjacentHTML("afterbegin", '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div role="alert"><ul class="woocommerce-error"><li>لطفا <b>شهر صورتحساب</b> خود را انتخاب نمایید.</li></ul></div></div>');
  window.jQuery(document.body).trigger("checkout_error");

  assert.equal(currentStep(document), "shipping", "a WooCommerce address validation response returns to the step containing its field");
  assert.equal(document.getElementById("customer_details").style.display, "");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);
  assert.ok(form.querySelector(".woocommerce-NoticeGroup-checkout .woocommerce-error"), "the original WooCommerce notice remains visible");
  const cityRow = document.getElementById("billing_city_field");
  assert.ok(cityRow.classList.contains("woocommerce-invalid-required-field"));
  assert.equal(cityRow.querySelector("[data-jluxe-required-field-error]"), null, "the theme does not duplicate WooCommerce's explicit required-city notice inline");
  assert.equal(document.activeElement.id, "billing_city");

  city.value = "کرمانشاه";
  dispatchChange(window, city);
  assert.equal(cityRow.querySelector("[data-jluxe-required-field-error]"), null, "the server error recovery does not create a second inline required warning");
  assert.equal(cityRow.classList.contains("woocommerce-invalid-required-field"), false, "a valid city selection clears WooCommerce's recovered required state");
  assert.equal(cityRow.classList.contains("woocommerce-invalid"), false);
  assert.equal(city.hasAttribute("aria-invalid"), false);
});

test("numeric city IDs retain their option label and are enabled/canonicalized for WooCommerce form submission", (t) => {
  const { window } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2274",
    initialCity: { value: "2275", label: "اختیارآباد" },
  });
  t.after(() => window.close());
  const { document } = window;
  const city = document.getElementById("billing_city");
  assert.equal(city.value, "اختیارآباد", "checkout initialization preserves an ID-backed selection by its visible label");
  assert.equal(city.disabled, false);

  // Simulate a city/province plugin restoring its numeric-valued select after
  // theme initialization and leaving that native select disabled.
  city.replaceChildren(new window.Option("شهر را انتخاب کنید", ""), new window.Option("اختیارآباد", "2275"));
  city.value = "2275";
  city.disabled = true;
  document.querySelector("[data-jluxe-checkout-next]").click();

  assert.equal(currentStep(document), "payment", "a valid selected label is canonicalized before the shipping step passes");
  assert.equal(city.value, "اختیارآباد");
  assert.equal(city.disabled, false, "WooCommerce does not omit the required city control from its POST");
  assert.equal(new window.FormData(document.querySelector("form.checkout")).get("billing_city"), "اختیارآباد");
});

test("numeric province IDs resolve the v1.7.58 city map, with canonical additions retained", (t) => {
  const { window } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2352",
    initialCity: { value: "2356", label: "بیستون" },
  });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  assert.equal(state.value, "2352", "the address extension's numeric province value remains intact");

  const cityValues = Array.from(city.options).map((option) => option.value);
  assert.equal(cityValues[1], "کرمانشاه", "the original v1.7.58 map order is the primary city list");
  assert.equal(cityValues[2], "اسلام‌آباد غرب");
  assert.ok(cityValues.includes("بیستون"), "canonical cities absent from the historical map remain available");
  assert.equal(city.value, "بیستون", "the existing numeric city resolves through its canonical label");

  city.value = "بیستون";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment", "a city in the selected province passes the required gate");
  assert.equal(new window.FormData(document.querySelector("form.checkout")).get("billing_city"), "بیستون");
});

test("stale Select2 text cannot substitute for an empty native required city field", (t) => {
  const { window } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2352",
    initialCity: { value: "2356", label: "بیستون" },
  });
  t.after(() => window.close());
  const { document } = window;
  const city = document.getElementById("billing_city");
  const renderedSelect2 = document.createElement("span");
  renderedSelect2.className = "select2-container";
  renderedSelect2.innerHTML = '<span class="selection"><span class="select2-selection"><span class="select2-selection__rendered" title="بیستون">بیستون</span></span></span>';
  city.insertAdjacentElement("afterend", renderedSelect2);
  city.value = "";
  city.disabled = true;
  document.querySelector("[data-jluxe-checkout-next]").click();

  assert.equal(currentStep(document), "shipping", "the required gate checks the native field instead of stale widget text");
  assert.equal(city.value, "");
  assert.equal(new window.FormData(document.querySelector("form.checkout")).get("billing_city"), null);
});

test("WooCommerce gateway hooks remain untouched and a missing native city stays missing", (t) => {
  const { window } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2352",
    initialCity: { value: "2356", label: "بیستون" },
  });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  const gateway = document.querySelector("input.payment_method");
  gateway.value = "WC_Pec_Gateway";
  gateway.checked = true;
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  assert.equal(document.querySelector("[data-jluxe-city-label-payload]").value, "بیستون");

  let genericGatewayHookRan = false;
  let gatewayHookRan = false;
  window.jQuery(form).on("checkout_place_order", () => {
    genericGatewayHookRan = true;
  });
  window.jQuery(form).on("checkout_place_order_WC_Pec_Gateway", () => {
    gatewayHookRan = true;
    city.value = "";
    city.disabled = true;
  });

  const submit = submitWithoutCreatingAnOrder(window);
  assert.equal(submit.event.defaultPrevented, false, "the theme's pre-submit checks leave a valid checkout to WooCommerce");
  const $form = window.jQuery(form);
  assert.notEqual($form.triggerHandler("checkout_place_order"), false);
  assert.notEqual($form.triggerHandler("checkout_place_order_WC_Pec_Gateway"), false);
  assert.equal(genericGatewayHookRan, true);
  assert.equal(gatewayHookRan, true, "the real gateway-specific WooCommerce hook runs without a theme wrapper");
  assert.equal(state.value, "2352");

  const finalFields = new window.FormData(form);
  assert.equal(finalFields.get("billing_city"), null, "a gateway may omit the disabled native control");
  assert.equal(finalFields.get("jluxe_billing_city_label"), "بیستون", "the hidden label is only a hint and never replaces the missing city control");
});

test("an explicit WooCommerce gateway veto remains authoritative without a theme hook", (t) => {
  const { window } = boot({
    locationFields: true,
    shippingChecked: true,
    initialState: "2352",
    initialCity: { value: "2356", label: "بیستون" },
  });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");
  const city = document.getElementById("billing_city");
  const gateway = document.querySelector("input.payment_method");
  gateway.value = "WC_Pec_Gateway";
  gateway.checked = true;
  document.querySelector("[data-jluxe-checkout-next]").click();

  window.jQuery(form).on("checkout_place_order_WC_Pec_Gateway", () => {
    city.value = "";
    city.disabled = true;
    return false;
  });
  const submit = submitWithoutCreatingAnOrder(window);
  assert.equal(submit.event.defaultPrevented, false, "the theme leaves WooCommerce's gateway event chain intact");
  const $form = window.jQuery(form);
  assert.notEqual($form.triggerHandler("checkout_place_order"), false);
  assert.equal($form.triggerHandler("checkout_place_order_WC_Pec_Gateway"), false, "WooCommerce receives the gateway's explicit checkout veto");
  assert.equal(currentStep(document), "payment");
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
  assert.equal(currentStep(document), "payment", "the stepper does not replace WooCommerce's province/city server validation");
  assert.equal(city.value, "2275", "a cross-province numeric city ID is not normalized to a false city label");
  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  dispatchChange(window, gateway);
  const submission = submitWithoutCreatingAnOrder(window);
  assert.equal(submission.event.defaultPrevented, false);
  assert.equal(submission.reachedForm, true, "the cross-province ID reaches the real WooCommerce form for server validation");
});

test("a mismatched city reaches WooCommerce for authoritative province/city validation", (t) => {
  const { window } = boot({ locationFields: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const state = document.getElementById("billing_state");
  const city = document.getElementById("billing_city");
  state.value = "TE";
  dispatchChange(window, state);

  // Simulate an extension injecting a foreign city option. The theme does not
  // hide WooCommerce's validation or convert the mismatched value to Tehran.
  city.add(new window.Option("اهواز", "اهواز"));
  city.value = "اهواز";
  dispatchChange(window, city);
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  assert.equal(city.value, "اهواز");
  assert.equal(new window.FormData(document.querySelector("form.checkout")).get("billing_city"), "اهواز");

  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  dispatchChange(window, gateway);
  const submission = submitWithoutCreatingAnOrder(window);
  assert.equal(submission.event.defaultPrevented, false);
  assert.equal(submission.reachedForm, true, "WooCommerce's normal validation/gateway event chain receives the posted city");
});

test("a WooCommerce mismatch response returns to the city field without changing the submitted value", (t) => {
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
  const gateway = document.querySelector("input.payment_method");
  gateway.checked = true;
  dispatchChange(window, gateway);
  const result = submitWithoutCreatingAnOrder(window);
  assert.equal(result.event.defaultPrevented, false);
  assert.equal(result.reachedForm, true, "the selected gateway and city reach WooCommerce's real submit path");

  const form = document.querySelector("form.checkout");
  form.insertAdjacentHTML("afterbegin", '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div role="alert"><ul class="woocommerce-error"><li>شهر انتخاب‌شده با استان هم‌خوانی ندارد.</li></ul></div></div>');
  window.jQuery(document.body).trigger("checkout_error");
  assert.equal(currentStep(document), "shipping");
  assert.equal(city.value, "تهران", "the theme does not fabricate, erase, or silently substitute the server-rejected value");
  assert.ok(form.querySelector(".woocommerce-NoticeGroup-checkout .woocommerce-error"), "the original WooCommerce validation notice remains visible");
  assert.equal(document.querySelector("[data-jluxe-city-province-error]"), null, "the theme does not duplicate or conceal the server validation");
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

test("WooCommerce remains responsible for final required-field validation on its real submit path", (t) => {
  const { window } = boot({ requiredField: true, shippingChecked: true });
  t.after(() => window.close());
  const { document } = window;
  const form = document.querySelector("form.checkout");

  const invalidAddressSubmit = submitWithoutCreatingAnOrder(window);
  assert.equal(invalidAddressSubmit.event.defaultPrevented, false);
  assert.equal(invalidAddressSubmit.reachedForm, true, "the theme does not swallow WooCommerce's server-side required-field validation");
  assert.equal(currentStep(document), "shipping");

  document.getElementById("billing_first_name").value = "Example";
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  document.getElementById("billing_first_name").value = "";
  document.querySelector("input.payment_method").checked = true;
  const finalSubmit = submitWithoutCreatingAnOrder(window);
  assert.equal(finalSubmit.event.defaultPrevented, false);
  assert.equal(finalSubmit.reachedForm, true, "the selected gateway follows the normal WooCommerce checkout event chain");

  form.insertAdjacentHTML("afterbegin", '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error"><li>billing_first_name is a required field</li></ul></div>');
  window.jQuery(document.body).trigger("checkout_error");
  assert.equal(currentStep(document), "shipping", "a native WooCommerce address error reveals the matching checkout stage");
  assert.ok(form.querySelector(".woocommerce-NoticeGroup-checkout .woocommerce-error"), "the original WooCommerce validation notice remains visible");
});

test("orders without available gateway radios continue through WooCommerce's native payment validation", (t) => {
  const unavailable = boot({ shippingChecked: true, paymentOptions: 0, paymentRequired: true });
  t.after(() => unavailable.window.close());
  unavailable.window.document.querySelector("[data-jluxe-checkout-next]").click();
  const reachesWooCommerce = submitWithoutCreatingAnOrder(unavailable.window);
  assert.equal(reachesWooCommerce.event.defaultPrevented, false);
  assert.equal(reachesWooCommerce.reachedForm, true, "WooCommerce, not a theme preflight, decides whether a gateway is required or available");
  assert.equal(unavailable.window.document.querySelector("[data-jluxe-payment-error]").hidden, true, "the theme does not mask the native WooCommerce payment error");

  const freeOrder = boot({ shippingChecked: true, paymentOptions: 1, paymentRequired: false });
  t.after(() => freeOrder.window.close());
  freeOrder.window.document.querySelector("[data-jluxe-checkout-next]").click();
  const allowed = submitWithoutCreatingAnOrder(freeOrder.window);
  assert.equal(allowed.event.defaultPrevented, false);
  assert.equal(allowed.reachedForm, true, "a zero-payment order with no gateway radios uses the same real checkout submit path");
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
