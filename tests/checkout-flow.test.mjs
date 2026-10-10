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
      const nativeSelector = selector.replaceAll(":visible", "").replaceAll(":checkbox", '[type="checkbox"]');
      return collection(list.flatMap((node) => Array.from(node.querySelectorAll(nativeSelector))));
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

function boot({ shippingSection = true, shippingOptions = true, shippingChecked = false, paymentRequired = true, paymentOptions = 1, reduceMotion = false, requiredField = false } = {}) {
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
        <div id="customer_details"><h3 id="jluxe-shipping-heading" tabindex="-1">اطلاعات ارسال</h3>${requiredMarkup}</div>
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
  window.jluxeWcSettings = { cartUrl: "/store/basket/" };
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
  assert.equal(document.activeElement.id, "billing_first_name");
  assert.equal(document.getElementById("jluxe-payment-column").hidden, true);

  document.getElementById("billing_first_name").value = "Example";
  document.querySelector("[data-jluxe-checkout-next]").click();
  assert.equal(currentStep(document), "payment");
  assert.equal(requiredRow.classList.contains("woocommerce-invalid-required-field"), false);
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
