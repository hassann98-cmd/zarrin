import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

// R38: the sticky mobile add-to-cart bar never creates a parallel form —
// its click must land on WooCommerce's real button (or guide the shopper
// to the variation form), and the bar stays hidden until the real form
// scrolls out of view.
const script = () =>
  fs.readFileSync(new URL("../assets/js/sticky-cta.js", import.meta.url), "utf8");

function buildDom(mode, withVariation = false) {
  const formClass = withVariation ? "cart variations_form" : "cart";
  const variationMarkup = withVariation
    ? `<div data-cp3-pills><button type="button" class="cp3-pill" id="first-variation">قرمز</button></div>`
    : "";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="${formClass}" id="real-form">
        ${variationMarkup}
        <button type="button" class="single_add_to_cart_button">افزودن</button>
      </form>
      <div class="jluxe-sticky-cta" data-jluxe-sticky-cta>
        <div class="jluxe-sticky-cta-info"><span class="jluxe-sticky-cta-name">پ</span><span class="jluxe-sticky-cta-price">۱۰۰</span></div>
        <button type="button" class="jluxe-btn" data-jluxe-sticky-add data-jluxe-sticky-mode="${mode}">خرید</button>
      </div>
    </body></html>`,
    { url: "https://shop.test/store/product/test/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const window = dom.window;
  Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
  window.HTMLElement.prototype.scrollIntoView = function (options) {
    window.__scrolledTo = [this, options && options.behavior];
  };
  return { dom, window };
}

test("R38 sticky CTA stays hidden until the real form leaves the viewport", () => {
  const { window } = buildDom("add");
  // Simulate realistic geometry: the cart form starts below the viewport, then scrolls past it.
  let formBottom = 2000;
  window.HTMLElement.prototype.getBoundingClientRect = function () {
    return { bottom: this.classList.contains("cart") ? formBottom : 0, top: 0, height: 0, width: 0, left: 0, right: 0 };
  };
  window.eval(script());
  assert.equal(window.document.querySelector("[data-jluxe-sticky-cta]").classList.contains("is-visible"), false);
  formBottom = 100;
  window.dispatchEvent(new window.Event("scroll"));
  assert.equal(window.document.querySelector("[data-jluxe-sticky-cta]").classList.contains("is-visible"), true);
  assert.equal(window.document.body.classList.contains("jluxe-has-sticky-cta"), true);
});

test("classic product CTA and mobile navigation switch modes without losing the persistent control", () => {
  const dom = new JSDOM(
    `<!doctype html><html><head><style>
      [data-jluxe-sticky-cta] { display: none; }
      body.jluxe-has-sticky-cta[data-jluxe-sticky-cta-mode="purchase"] [data-jluxe-sticky-cta] { display: flex; }
      body[data-jluxe-sticky-cta-mode="navigation"] [data-jluxe-sticky-cta] { display: none; }
    </style></head><body>
      <form class="cart"><button class="single_add_to_cart_button">افزودن</button></form>
      <nav class="jluxe-mobile-nav" data-jluxe-mobile-nav-dock><a href="/">خانه</a></nav>
      <nav class="jluxe-mobile-nav" data-jluxe-mobile-nav-dock hidden aria-hidden="true"></nav>
      <div id="jluxe-sticky-cta" class="jluxe-sticky-cta" data-jluxe-sticky-cta>
        <button data-jluxe-sticky-add>خرید</button>
      </div>
      <button data-jluxe-sticky-cta-mode-toggle aria-label="نمایش منوی موبایل" aria-pressed="true" hidden>
        <span data-jluxe-sticky-cta-mode-label>منو</span>
      </button>
    </body></html>`,
    { url: "https://shop.test/product/test/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
  Object.defineProperty(window, "innerHeight", { configurable: true, value: 800 });
  const form = window.document.querySelector("form.cart");
  const bar = window.document.querySelector("[data-jluxe-sticky-cta]");
  const nav = window.document.querySelector("[data-jluxe-mobile-nav-dock]");
  const originallyHiddenNav = window.document.querySelectorAll("[data-jluxe-mobile-nav-dock]")[1];
  const toggle = window.document.querySelector("[data-jluxe-sticky-cta-mode-toggle]");
  let formBottom = 700;
  let mode = "navigation";
  form.getBoundingClientRect = () => ({ top: formBottom - 100, bottom: formBottom, height: 100, width: 360, left: 0, right: 360 });
  nav.getBoundingClientRect = () => ({ top: 732, bottom: 800, height: 68, width: 390, left: 0, right: 390 });
  bar.getBoundingClientRect = () => mode === "purchase"
    ? ({ top: 744, bottom: 800, height: 56, width: 390, left: 0, right: 390 })
    : ({ top: 0, bottom: 0, height: 0, width: 0, left: 0, right: 0 });
  Object.defineProperty(bar, "offsetHeight", { configurable: true, get: () => mode === "purchase" ? 56 : 0 });

  const originalSetAttribute = window.document.body.setAttribute.bind(window.document.body);
  const originalRemoveAttribute = window.document.body.removeAttribute.bind(window.document.body);
  window.document.body.setAttribute = (name, value) => {
    if (name === "data-jluxe-sticky-cta-mode") mode = value;
    return originalSetAttribute(name, value);
  };
  window.document.body.removeAttribute = (name) => {
    if (name === "data-jluxe-sticky-cta-mode") mode = "navigation";
    return originalRemoveAttribute(name);
  };

  window.eval(script());
  assert.equal(toggle.hidden, true, "the switch stays out of the way until the sticky purchase bar is needed");
  formBottom = 100;
  window.dispatchEvent(new window.Event("scroll"));
  assert.equal(window.document.body.dataset.jluxeStickyCtaMode, "purchase");
  assert.equal(window.getComputedStyle(bar).display, "flex", "purchase mode keeps the WooCommerce CTA visible");
  assert.equal(nav.hidden, true, "purchase mode temporarily hides the bottom dock without replacing it");
  assert.equal(window.getComputedStyle(nav).display, "none", "purchase mode yields the bottom dock");
  assert.equal(toggle.hidden, false);
  assert.equal(toggle.getAttribute("aria-label"), "نمایش منوی موبایل");
  assert.equal(toggle.getAttribute("aria-pressed"), "true");
  assert.equal(toggle.querySelector("[data-jluxe-sticky-cta-mode-label]").textContent, "منو");
  assert.equal(toggle.style.bottom, "64px", "the persistent switch sits just above the active CTA");
  assert.equal(window.document.body.style.paddingBottom, "120px", "scroll clearance includes the CTA and its switch control");

  toggle.click();
  assert.equal(window.document.body.dataset.jluxeStickyCtaMode, "navigation");
  assert.equal(toggle.getAttribute("aria-label"), "نمایش نوار خرید");
  assert.equal(toggle.getAttribute("aria-pressed"), "false");
  assert.equal(toggle.querySelector("[data-jluxe-sticky-cta-mode-label]").textContent, "خرید");
  assert.equal(toggle.style.bottom, "76px", "the switch follows the mobile dock when navigation is active");
  assert.equal(window.getComputedStyle(bar).display, "none", "navigation mode yields the purchase bar");
  assert.equal(nav.hidden, false, "navigation mode restores the dock's original hidden state");
  assert.equal(originallyHiddenNav.hidden, true, "switching modes preserves another dock's preexisting hidden state");
  assert.equal(window.getComputedStyle(nav).display, "block", "navigation mode restores the bottom dock");
  assert.equal(window.document.body.style.paddingBottom, "142px", "scroll clearance includes the dock and its switch control");

  toggle.click();
  assert.equal(window.document.body.dataset.jluxeStickyCtaMode, "purchase");
  assert.equal(toggle.getAttribute("aria-pressed"), "true");
  formBottom = 2000;
  window.dispatchEvent(new window.Event("scroll"));
  assert.equal(toggle.hidden, true, "the switch hides again when the original product form returns");
  assert.equal(window.document.body.hasAttribute("data-jluxe-sticky-cta-mode"), false);
  assert.equal(window.document.body.classList.contains("jluxe-has-sticky-cta"), false);
});

test("R38 a simple product's sticky CTA clicks the real WooCommerce button", () => {
  const { window } = buildDom("add");
  let realClicks = 0;
  window.document.querySelector(".single_add_to_cart_button").addEventListener("click", () => {
    realClicks += 1;
  });
  window.eval(script());
  window.document.querySelector("[data-jluxe-sticky-add]").click();
  assert.equal(realClicks, 1);
});

test("R121 a variable product's sticky CTA scrolls to and focuses the first visible variation", async () => {
  const { window } = buildDom("scroll", true);
  window.eval(script());
  const button = window.document.querySelector("[data-jluxe-sticky-add]");
  button.click();
  const pills = window.document.querySelector("[data-cp3-pills]");
  assert.deepEqual(window.__scrolledTo, [pills, "smooth"]);
  await new Promise((resolve) => window.setTimeout(resolve, 375));
  assert.equal(window.document.activeElement, window.document.querySelector("#first-variation"));
  assert.equal(window.__submitted === undefined || window.__submitted === false, true);
});

test("R121 a selected variable product's sticky CTA clicks WooCommerce's real add button", () => {
  const { window } = buildDom("add", true);
  let realClicks = 0;
  window.document.querySelector(".single_add_to_cart_button").addEventListener("click", () => {
    realClicks += 1;
  });
  window.eval(script());
  window.document.querySelector("[data-jluxe-sticky-add]").click();
  assert.equal(realClicks, 1);
});

test("R121 a disabled variable add button is never bypassed with form.submit", () => {
  const { window } = buildDom("add", true);
  let submits = 0;
  window.document.querySelector("form.cart").submit = () => { submits += 1; };
  window.document.querySelector(".single_add_to_cart_button").disabled = true;
  window.eval(script());
  window.document.querySelector("[data-jluxe-sticky-add]").click();
  assert.equal(submits, 0);
  assert.equal(window.__scrolledTo[0], window.document.querySelector("[data-cp3-pills]"));
});

test("R38 sticky CTA is inert without its markup", () => {
  const dom = new JSDOM("<!doctype html><html><body><p>صفحهٔ دیگر</p></body></html>", {
    url: "https://shop.test/store/catalog/",
    runScripts: "outside-only",
  });
  assert.doesNotThrow(() => dom.window.eval(script()));
});
