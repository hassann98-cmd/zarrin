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
