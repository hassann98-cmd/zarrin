import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { JSDOM } from "jsdom";
import jqueryFactory from "jquery";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const quantityStart = source.indexOf("(function () {");
const quantityEnd = source.indexOf("\n})();", quantityStart);
assert.ok(quantityStart >= 0 && quantityEnd > quantityStart, "the quantity stepper module can be isolated");
const quantityScript = source.slice(quantityStart, quantityEnd + "\n})();".length);

const cartMarker = source.indexOf("Fast cart-page mutations.");
const cartStart = source.indexOf("(function () {", cartMarker);
const cartEnd = source.indexOf("\n})();", cartStart);
assert.ok(cartMarker >= 0 && cartStart > cartMarker && cartEnd > cartStart, "the cart AJAX module can be isolated");
const cartScript = source.slice(cartStart, cartEnd + "\n})();".length);

function cartRow(key, quantity, total) {
  return `<div class="woocommerce-cart-form__cart-item cart_item" data-jluxe-cart-item-key="${key}">
    <div data-jluxe-cart-item-total>${total}</div>
    <div class="quantity jluxe-qty">
      <button type="button" data-jluxe-qty-step="decrease">−</button>
      <span data-jluxe-qty-fa-wrap><span data-jluxe-qty-fa aria-hidden="true">${quantity}</span><input class="qty jluxe-qty-fa-input" type="number" min="0" max="9" step="1" value="${quantity}"></span>
      <button type="button" data-jluxe-qty-step="increase">+</button>
    </div>
    <a href="/?remove_item=${key}" data-jluxe-cart-remove role="button">حذف</a>
  </div>`;
}

function bootCart({ rows = [cartRow("line-a", 1, "10")], onRequest } = {}) {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="woocommerce" id="woocommerce-root">
        <nav class="jluxe-checkout-stepper" aria-label="مراحل خرید"><ol><li>سبد</li></ol></nav>
        <div class="woocommerce-notices-wrapper"><p>notice</p></div>
        <div data-jluxe-cart-page-content>
          <form class="woocommerce-cart-form"><div class="woocommerce-cart-form__contents">${rows.join("")}</div>
            <button type="submit" name="update_cart" id="update-cart">بروزرسانی</button>
          </form>
          <div class="cart_totals"><span data-cart-total>10</span></div>
        </div>
      </div>
    </body></html>`,
    { url: "https://shop.test/cart/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  const { window } = dom;
  window.jQuery = jqueryFactory(window);
  window.JLuxeThemeSettings = {
    cart: { ajaxUrl: "https://shop.test/wp-admin/admin-ajax.php", nonce: "nonce-cart" },
  };
  const calls = [];
  window.jluxeCartPost = (url, body, settings) => {
    const call = { url, fields: Object.fromEntries(body.entries()), settings };
    calls.push(call);
    return onRequest ? onRequest(call, calls.length - 1) : Promise.resolve({ success: false, data: { message: "No test response" } });
  };
  let updateButtonClicks = 0;
  window.document.getElementById("update-cart").addEventListener("click", () => updateButtonClicks++);
  window.eval(quantityScript + "\n" + cartScript);
  return { dom, window, calls, updateButtonClicks: () => updateButtonClicks };
}

function quantitySnapshot({ items, isEmpty = false, totalsHtml = '<div class="cart_totals"><span data-cart-total>30</span></div>', emptyHtml = '<nav class="jluxe-checkout-stepper">step</nav><div data-jluxe-cart-page-content><p class="empty-state">سبد خالی</p></div>', itemCount = 3 }) {
  return {
    items,
    itemCount,
    subtotalHtml: "<b>subtotal</b>",
    cartUrl: "https://shop.test/cart/",
    checkoutUrl: "https://shop.test/checkout/",
    cartPage: isEmpty ? { isEmpty: true, emptyHtml } : { isEmpty: false, totalsHtml },
  };
}

const wait = (window, milliseconds = 230) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

test("rapid quantity taps coalesce into one secure cart request and render WooCommerce fragments", async (t) => {
  let snapshot;
  const { dom, window, calls, updateButtonClicks } = bootCart({
    onRequest: () => Promise.resolve({ success: true, data: snapshot }),
  });
  t.after(() => window.close());
  const { document } = window;
  let published = null;
  let totalsEvents = 0;
  let fragmentRefreshEvents = 0;
  window.addEventListener("jluxe:cart-updated", (event) => { published = event.detail; });
  window.jQuery(document.body).on("updated_cart_totals", () => totalsEvents++);
  window.jQuery(document.body).on("updated_wc_div", () => fragmentRefreshEvents++);

  const row = document.querySelector('[data-jluxe-cart-item-key="line-a"]');
  row.querySelector('[data-jluxe-qty-step="increase"]').click();
  await wait(window, 35);
  row.querySelector('[data-jluxe-qty-step="increase"]').click();
  assert.equal(row.querySelector("input.qty").value, "3", "the number responds immediately while the request is coalesced");
  assert.equal(calls.length, 0, "the debounce window avoids a request for each tap");
  assert.equal(updateButtonClicks(), 0, "the hidden full-cart form submit is no longer used when the endpoint is available");

  snapshot = quantitySnapshot({
    items: [{ key: "line-a", qty: 3, lineTotalHtml: "<strong>30</strong>" }],
    itemCount: 3,
  });
  await wait(window, 220);

  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, "https://shop.test/wp-admin/admin-ajax.php");
  assert.deepEqual(calls[0].fields, {
    action: "jluxe_cart",
    nonce: "nonce-cart",
    op: "update_qty",
    key: "line-a",
    cart_page: "1",
    qty: "3",
  });
  assert.equal(row.querySelector("input.qty").value, "3");
  assert.equal(row.querySelector("[data-jluxe-qty-fa]").textContent, "۳");
  assert.equal(row.querySelectorAll("[data-jluxe-cart-item-total]")[0].innerHTML, "<strong>30</strong>");
  assert.equal(document.querySelector(".cart_totals [data-cart-total]").textContent, "30");
  assert.equal(row.hasAttribute("aria-busy"), false);
  assert.equal(published.itemCount, 3);
  assert.equal(Object.hasOwn(published, "cartPage"), false, "server-rendered HTML fragments are not copied into React cart state");
  assert.equal(totalsEvents, 1);
  assert.equal(fragmentRefreshEvents, 0, "the default cart path avoids Woo's extra cart-fragments AJAX refresh");
});

test("remove cancels an unsent quantity update, prevents navigation and refreshes remaining line totals", async (t) => {
  const snapshot = quantitySnapshot({
    items: [{ key: "line-b", qty: 1, lineTotalHtml: "<b>9</b>" }],
    itemCount: 1,
    totalsHtml: '<div class="cart_totals"><span data-cart-total>9</span></div>',
    emptyHtml: "",
  });
  const { dom, window, calls } = bootCart({
    rows: [cartRow("line-a", 1, "10"), cartRow("line-b", 1, "9")],
    onRequest: () => Promise.resolve({ success: true, data: snapshot }),
  });
  t.after(() => window.close());
  const { document } = window;
  const rowA = document.querySelector('[data-jluxe-cart-item-key="line-a"]');
  rowA.querySelector('[data-jluxe-qty-step="increase"]').click();
  const remove = rowA.querySelector("[data-jluxe-cart-remove]");
  const click = new window.MouseEvent("click", { bubbles: true, cancelable: true });
  remove.dispatchEvent(click);
  assert.equal(click.defaultPrevented, true, "the valid WooCommerce remove URL remains a no-JS fallback, not the AJAX path");
  await wait(window);

  assert.equal(calls.length, 1, "the pending quantity change is cancelled rather than racing the removal");
  assert.equal(calls[0].fields.op, "remove");
  assert.equal(calls[0].fields.key, "line-a");
  assert.equal(document.querySelector('[data-jluxe-cart-item-key="line-a"]'), null);
  assert.equal(document.querySelector('[data-jluxe-cart-item-key="line-b"] [data-jluxe-cart-item-total]').innerHTML, "<b>9</b>");
  assert.equal(document.querySelector(".cart_totals [data-cart-total]").textContent, "9");
  assert.equal(document.querySelector(".jluxe-checkout-stepper").textContent.trim(), "سبد");
});

test("removing the last item swaps in the server's empty-cart content without duplicating the stepper", async (t) => {
  const snapshot = quantitySnapshot({
    items: [],
    isEmpty: true,
    itemCount: 0,
    emptyHtml: '<nav class="jluxe-checkout-stepper">duplicate</nav><div class="mx-auto w-full max-w-[600px] px-4 py-16 text-center" data-jluxe-cart-page-content><p class="empty-state">سبد خالی</p></div>',
  });
  const { dom, window, calls } = bootCart({ onRequest: () => Promise.resolve({ success: true, data: snapshot }) });
  t.after(() => window.close());
  const { document } = window;
  const notice = document.querySelector(".woocommerce-notices-wrapper");
  const remove = document.querySelector("[data-jluxe-cart-remove]");
  remove.click();
  await wait(window);

  assert.equal(calls[0].fields.op, "remove");
  assert.equal(document.querySelectorAll(".jluxe-checkout-stepper").length, 1);
  assert.equal(document.querySelector("[data-jluxe-cart-page-content] .empty-state").textContent, "سبد خالی");
  assert.equal(document.querySelector("[data-jluxe-cart-page-content]").classList.contains("max-w-[600px]"), true, "the actual empty-template wrapper replaces the populated-cart layout");
  assert.equal(document.querySelector("form.woocommerce-cart-form"), null);
  assert.equal(document.querySelector(".cart_totals"), null);
  assert.equal(document.querySelector(".woocommerce-notices-wrapper"), notice, "existing Woo notices survive the fragment swap");
});

test("a WooCommerce validation error restores the last confirmed quantity and remains accessible", async (t) => {
  const { dom, window, calls } = bootCart({
    rows: [cartRow("line-a", 2, "20")],
    onRequest: () => Promise.resolve({ success: false, data: { message: "موجودی کافی نیست." } }),
  });
  t.after(() => window.close());
  const { document } = window;
  const input = document.querySelector("input.qty");
  input.value = "9";
  input.dispatchEvent(new window.Event("change", { bubbles: true }));
  await wait(window);

  assert.equal(calls.length, 1);
  assert.equal(calls[0].fields.op, "update_qty");
  assert.equal(calls[0].fields.qty, "9");
  assert.equal(input.value, "2");
  assert.equal(document.querySelector("[data-jluxe-qty-fa]").textContent, "۲");
  assert.equal(document.querySelector("[data-jluxe-cart-ajax-message]").textContent, "موجودی کافی نیست.");
  assert.equal(document.querySelector("[data-jluxe-cart-ajax-message]").getAttribute("role"), "alert");
  assert.ok(document.querySelector("form.woocommerce-cart-form"), "rejected quantity changes do not hide the real cart form");
});

test("rejected removal restores the confirmed quantity when it cancels a pending edit", async (t) => {
  const { dom, window, calls } = bootCart({
    rows: [cartRow("line-a", 1, "10")],
    onRequest: () => Promise.resolve({ success: false, data: { message: "حذف انجام نشد." } }),
  });
  t.after(() => window.close());
  const { document } = window;
  const row = document.querySelector('[data-jluxe-cart-item-key="line-a"]');
  row.querySelector('[data-jluxe-qty-step="increase"]').click();
  row.querySelector("[data-jluxe-cart-remove]").click();
  await wait(window);

  assert.equal(calls.length, 1);
  assert.equal(calls[0].fields.op, "remove");
  assert.equal(row.querySelector("input.qty").value, "1");
  assert.equal(row.hasAttribute("data-jluxe-cart-removing"), false);
  assert.equal(row.hasAttribute("data-jluxe-cart-pending"), false);
  assert.equal(document.querySelector("[data-jluxe-cart-ajax-message]").textContent, "حذف انجام نشد.");
});
