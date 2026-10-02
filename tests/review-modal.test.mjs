import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("R73: پاپ‌آپِ «ثبت دیدگاه»");
const start = source.indexOf("(function () {", marker);
const endMarker = source.indexOf("\n\n/**\n * پاپ‌آپِ «محصولات پیشنهادی»", start);
assert.ok(marker >= 0 && start > marker && endMarker > start, "review modal IIFE is present");
const reviewModalIife = source.slice(start, endMarker).trim();

function setup({ includeForm = true } = {}) {
  const form = includeForm
    ? '<div id="review_form_wrapper"><form id="commentform" action="/wp-comments-post.php"><button type="submit">ارسال</button></form></div>'
    : "";
  const dom = new JSDOM(
    `<!doctype html><html><body><main class="jluxe-cp3" id="product">
      <div class="cp3-reviewtoolbar"><a class="cp3-reviewbtn" href="#review_form_wrapper" data-jluxe-review-modal>ثبت دیدگاه</a></div>
      <div id="comments">${form}</div>
    </main></body></html>`,
    { url: "https://shop.test/product/review/", runScripts: "outside-only" },
  );
  dom.window.eval(reviewModalIife);
  return dom;
}

test("R140 the review button opens the Woo form even without the optional focus utility, then restores it on close", () => {
  const dom = setup();
  const { document, MouseEvent } = dom.window;
  const button = document.querySelector("[data-jluxe-review-modal]");
  const root = document.querySelector(".jluxe-cp3");
  assert.ok(root.classList.contains("jluxe-review-modal-ready"));

  const click = new MouseEvent("click", { bubbles: true, cancelable: true });
  button.dispatchEvent(click);
  assert.equal(click.defaultPrevented, true);
  const modal = document.querySelector(".jluxe-review-modal-backdrop");
  assert.ok(modal, "modal opens instead of throwing when JLuxeStorefrontUtils is absent");
  assert.ok(modal.querySelector(".jluxe-review-modal-body #review_form_wrapper"));
  assert.equal(document.body.style.overflow, "hidden");

  modal.querySelector(".jluxe-review-modal-close").dispatchEvent(new MouseEvent("click", { bubbles: true }));
  assert.equal(document.querySelector(".jluxe-review-modal-backdrop"), null);
  assert.ok(document.querySelector("#comments > #review_form_wrapper"), "borrowed Woo form returns to its original DOM home");
  assert.equal(document.body.style.overflow, "");
  dom.window.close();
});

test("R140 missing Woo review markup gets an inline explanation instead of a silent dead button", () => {
  const dom = setup({ includeForm: false });
  const { document, MouseEvent } = dom.window;
  const button = document.querySelector("[data-jluxe-review-modal]");
  const root = document.querySelector(".jluxe-cp3");
  assert.equal(root.classList.contains("jluxe-review-modal-ready"), false, "a missing form never hides the no-JS fallback");

  button.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
  assert.equal(document.querySelector(".jluxe-review-modal-backdrop"), null);
  assert.match(document.querySelector(".cp3-review-unavailable")?.textContent || "", /فرم ثبت دیدگاه.*در دسترس نیست/);
  dom.window.close();
});
