import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const start = source.indexOf("(function () {");
const endToken = "})();";
const end = source.indexOf(endToken, start) + endToken.length;
assert.ok(start >= 0 && end > start, "the delegated quantity-stepper handler is present");
const quantityStepperIife = source.slice(start, end);

function setup() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form class="cart">
        <div class="quantity jluxe-simple-qty-controls">
          <button type="button" data-jluxe-qty-step="decrease" aria-label="کاهش تعداد"><svg><path d="M4 8h8"></path></svg></button>
          <input class="qty" type="number" min="1" max="3" step="1" value="1">
          <button type="button" data-jluxe-qty-step="increase" aria-label="افزایش تعداد"><svg><path d="M8 4v8M4 8h8"></path></svg></button>
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/product/simple/", runScripts: "outside-only" },
  );
  dom.window.eval(quantityStepperIife);
  return dom;
}

test("R144 simple-product plus/minus controls change quantity and honor min/max", () => {
  const { window } = setup();
  const input = window.document.querySelector("input.qty");
  const increasePath = window.document.querySelector('[data-jluxe-qty-step="increase"] path');
  const decreasePath = window.document.querySelector('[data-jluxe-qty-step="decrease"] path');
  let changeEvents = 0;
  input.addEventListener("change", () => { changeEvents += 1; });

  increasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  assert.equal(input.value, "2", "clicking the plus icon increments from the nested SVG target");
  increasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  increasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  assert.equal(input.value, "3", "the maximum quantity is enforced");

  decreasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  decreasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  decreasePath.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  assert.equal(input.value, "1", "the minimum quantity is enforced");
  assert.equal(changeEvents, 4, "change is dispatched only when the value actually changes");
});
