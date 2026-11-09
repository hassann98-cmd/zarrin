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

function setup({ includeForm = true, action = "/wp-comments-post.php" } = {}) {
  const form = includeForm
    ? `<div id="review_form_wrapper"><form id="commentform" action="${action}"><button type="submit">ارسال</button></form></div>`
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

test("R150 same-origin popup submission posts the Woo form and shows the moderation confirmation", async () => {
  const dom = setup();
  const { document, MouseEvent, Event, FormData } = dom.window;
  let request;
  dom.window.fetch = (url, options) => {
    request = { url, options };
    return Promise.resolve({ type: "opaqueredirect", status: 0 });
  };

  document.querySelector("[data-jluxe-review-modal]").dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
  const form = document.querySelector(".jluxe-review-modal form#commentform");
  const submitEvent = new Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(submitEvent);

  assert.equal(submitEvent.defaultPrevented, true, "same-origin form stays inside the modal while it posts");
  assert.equal(request.url, "/wp-comments-post.php");
  assert.equal(request.options.method, "POST");
  assert.equal(request.options.credentials, "same-origin");
  assert.equal(request.options.redirect, "manual");
  assert.ok(request.options.body instanceof FormData, "the real WooCommerce fields are submitted");

  await new Promise((resolve) => setImmediate(resolve));
  assert.match(document.querySelector(".jluxe-review-modal-success")?.textContent || "", /پس از بررسی و تأیید مدیر/);
  dom.window.close();
});

test("R150 cross-origin WordPress review actions keep native form submission so login cookies are not lost", () => {
  const dom = setup({ action: "https://account.shop.test/wp-comments-post.php" });
  const { document, MouseEvent, Event } = dom.window;
  dom.window.fetch = () => assert.fail("cross-origin review POST must use the browser's native form path");

  document.querySelector("[data-jluxe-review-modal]").dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
  const form = document.querySelector(".jluxe-review-modal form#commentform");
  const submitEvent = new Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(submitEvent);

  assert.equal(submitEvent.defaultPrevented, false, "native top-level form submission preserves the configured site origin and auth cookies");
  dom.window.close();
});

test("R150 WooCommerce comment validation errors stay visible in the review popup", async () => {
  const dom = setup();
  const { document, MouseEvent, Event } = dom.window;
  dom.window.fetch = () => Promise.resolve({
    type: "basic",
    status: 403,
    ok: false,
    text: () => Promise.resolve('<div class="wp-die-message">برای ثبت دیدگاه وارد شوید.</div>'),
  });

  document.querySelector("[data-jluxe-review-modal]").dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
  const form = document.querySelector(".jluxe-review-modal form#commentform");
  form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
  await new Promise((resolve) => setImmediate(resolve));

  assert.match(document.querySelector(".jluxe-review-modal-error")?.textContent || "", /وارد شوید/);
  assert.equal(form.querySelector('[type="submit"]').disabled, false, "the submit button is available for a corrected retry");
  dom.window.close();
});
