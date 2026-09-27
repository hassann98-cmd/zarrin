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

function buildDom(mode) {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="cart" id="real-form">
        <button type="submit" class="single_add_to_cart_button">افزودن</button>
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

test("R38 a variable product's sticky CTA guides the shopper to the variation form", () => {
  const { window } = buildDom("scroll");
  window.eval(script());
  const button = window.document.querySelector("[data-jluxe-sticky-add]");
  button.click();
  assert.deepEqual(window.__scrolledTo, [window.document.querySelector("form.cart"), "smooth"]);
  assert.equal(window.__submitted === undefined || window.__submitted === false, true);
});

test("R38 sticky CTA is inert without its markup", () => {
  const dom = new JSDOM("<!doctype html><html><body><p>صفحهٔ دیگر</p></body></html>", {
    url: "https://shop.test/store/catalog/",
    runScripts: "outside-only",
  });
  assert.doesNotThrow(() => dom.window.eval(script()));
});
