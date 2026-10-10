import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const read = (path) => fs.readFileSync(new URL(path, import.meta.url), "utf8");

test("suggested-product modal retains selections, retries only failed adds, and exposes API errors in the dialog", async (t) => {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <button id="open-modal">Open suggestions</button>
      <div class="jluxe-pa fixed inset-0 hidden" data-jluxe-suggested-modal aria-hidden="true" data-pa-context="500" data-pa-main="100">
        <div class="jluxe-pa-backdrop" data-jluxe-suggested-close></div>
        <section class="jluxe-pa-sheet" role="dialog" aria-modal="true" aria-label="Suggestions">
          <button id="close-modal" type="button" data-jluxe-suggested-close>Close</button>
          <div class="jluxe-pa-body">
            <button class="jluxe-pa-row is-selected" data-pa-product="501" data-pa-amount="50" aria-pressed="true"><span class="jluxe-pa-name">First offer</span></button>
            <button class="jluxe-pa-row is-selected" data-pa-product="502" data-pa-amount="25" aria-pressed="true"><span class="jluxe-pa-name">Second offer</span></button>
            <button class="jluxe-pa-row is-selected" data-pa-service="s0" data-pa-amount="10" aria-pressed="true"><span class="jluxe-pa-name">Service</span></button>
          </div>
          <strong data-pa-total></strong>
          <button type="button" data-pa-confirm data-pa-confirm-selected="Add selected" data-pa-confirm-empty="Continue without extras"><span data-pa-confirm-label></span></button>
        </section>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/main/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  t.after(() => dom.window.close());

  const { window } = dom;
  window.JLuxeThemeSettings = { cart: { ajaxUrl: "https://shop.test/cart-ajax", nonce: "nonce-test" } };
  window.eval(read("../assets/js/storefront-utils.js"));

  const requests = [];
  let failSecondOffer = true;
  window.fetch = async (_url, options = {}) => {
    const body = options.body;
    const request = {
      op: body.get("op"),
      productId: body.get("product_id"),
      serviceKeys: body.get("pa_services"),
    };
    requests.push(request);
    if (request.op === "add" && request.productId === "502" && failSecondOffer) {
      return { json: async () => ({ success: false, data: { message: "این پیشنهاد فعلاً در دسترس نیست." } }) };
    }
    return { json: async () => ({ success: true, data: { cartCount: requests.length } }) };
  };
  window.jluxeCartPost = (url, body) => window.fetch(url, { method: "POST", body, credentials: "same-origin" }).then((response) => response.json());

  const source = read("../assets/js/woocommerce.js");
  const marker = source.indexOf("پاپ‌آپِ «محصولات پیشنهادی»");
  const start = source.indexOf("(function () {", marker);
  const endMarker = source.indexOf("\n})();\n\n/**\n * افزودن به سبد در فرمِ صفحه", start);
  assert.ok(marker >= 0 && start > marker && endMarker > start, "the suggested-modal module is isolated from unrelated WooCommerce handlers");
  window.eval(source.slice(start, endMarker + "\n})();".length));

  const modal = window.document.querySelector("[data-jluxe-suggested-modal]");
  const product501 = modal.querySelector('[data-pa-product="501"]');
  const product502 = modal.querySelector('[data-pa-product="502"]');
  const service = modal.querySelector('[data-pa-service="s0"]');
  const confirm = modal.querySelector("[data-pa-confirm]");
  const trigger = window.document.getElementById("open-modal");
  const close = window.document.getElementById("close-modal");

  assert.equal(modal.querySelector("[data-pa-total]").textContent, "۱۸۵", "server-selected product and service rows contribute to the initial Persian-digit total");
  trigger.focus();
  window.jluxeOpenSuggestedProductsModal();
  assert.equal(modal.getAttribute("aria-hidden"), "false");
  assert.equal(window.document.body.style.overflow, "hidden");
  assert.ok(modal.querySelector(".jluxe-pa-sheet").contains(window.document.activeElement), "opening suggestions moves focus into the dialog");
  close.click();
  assert.equal(modal.getAttribute("aria-hidden"), "true");
  assert.equal(window.document.body.style.overflow, "");
  assert.equal(window.document.activeElement, trigger, "closing suggestions restores focus to the opener");
  assert.equal(product501.getAttribute("aria-pressed"), "true", "closing does not discard product choices");
  assert.equal(service.getAttribute("aria-pressed"), "true", "closing does not discard service choices");

  window.jluxeOpenSuggestedProductsModal();
  product502.click();
  assert.equal(product502.getAttribute("aria-pressed"), "false");
  assert.equal(modal.querySelector("[data-pa-total]").textContent, "۱۶۰", "selection toggles recalculate the total");
  product502.click();
  assert.equal(modal.querySelector("[data-pa-total]").textContent, "۱۸۵");

  confirm.click();
  await new Promise((resolve) => setTimeout(resolve, 15));
  assert.deepEqual(requests, [
    { op: "add", productId: "501", serviceKeys: null },
    { op: "add", productId: "502", serviceKeys: null },
    { op: "pa_services", productId: null, serviceKeys: "s0" },
  ], "selected products and services use the cart endpoint sequentially");
  assert.equal(modal.getAttribute("aria-hidden"), "false", "a partial API failure keeps the suggestion dialog open");
  assert.equal(confirm.disabled, false, "the confirm control becomes retryable after a failure");
  assert.equal(product501.getAttribute("aria-pressed"), "false", "an already-added product is deselected to prevent duplicate retry");
  assert.equal(product502.getAttribute("aria-pressed"), "true", "the failed product remains selected for retry");
  assert.equal(service.getAttribute("aria-pressed"), "true", "the selected service choice remains available after a partial failure");
  assert.equal(modal.querySelector("[data-pa-total]").textContent, "۱۳۵", "the total reflects only the still-pending product plus selected service");
  assert.match(modal.querySelector("[data-pa-error]").textContent, /این پیشنهاد فعلاً در دسترس نیست/);
  assert.equal(window.document.querySelector(".jluxe-toast"), null, "the error is not hidden behind the modal by the lower toast layer");

  close.click();
  assert.equal(product502.getAttribute("aria-pressed"), "true", "closing after an error preserves retryable choices");
  window.jluxeOpenSuggestedProductsModal();
  assert.match(modal.querySelector("[data-pa-error]").textContent, /دسترس نیست/);
  failSecondOffer = false;
  confirm.click();
  await new Promise((resolve) => setTimeout(resolve, 15));
  assert.equal(requests.filter((request) => request.op === "add").map((request) => request.productId).join(","), "501,502,502", "retry does not add the previously successful product twice");
  assert.equal(modal.getAttribute("aria-hidden"), "true", "a fully successful retry closes the sheet");
  assert.equal(window.document.body.style.overflow, "");
});

test("variable recommendations preserve and retry pending sheet choices after a successful variation", async (t) => {
  const html = `<!doctype html><html><body><button id="open-modal">Open</button>${suggestedMarkup(true)}</body></html>`;
  const dom = new JSDOM(html, {
    url: "https://shop.test/product/main/",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.JLuxeThemeSettings = { cart: { ajaxUrl: "https://shop.test/cart-ajax", nonce: "nonce-test" } };
  window.eval(read("../assets/js/storefront-utils.js"));

  let failPickerLoad = true;
  let failVariationAdd = true;
  let failSimpleAdd = true;
  const requests = [];
  const cartUpdates = [];
  window.addEventListener("jluxe:cart-updated", (event) => cartUpdates.push(event.detail));
  const refreshedMarkup = suggestedMarkup(false).replace('data-pa-main="100"', 'data-pa-main="140"');
  const variationForm = `<form class="variations_form" data-product_id="700">
    <input type="hidden" name="add-to-cart" value="700">
    <input type="hidden" name="variation_id" value="701">
    <input type="hidden" name="attribute_pa_size" value="medium">
    <button type="button" class="single_add_to_cart_button">Add variation</button>
  </form>`;
  window.fetch = async (_url, options = {}) => {
    const body = options.body;
    const request = {
      action: body.get("action"),
      op: body.get("op"),
      productId: body.get("product_id"),
      variationId: body.get("variation_id"),
      contextId: body.get("pa_context_id"),
      serviceKeys: body.get("pa_services"),
      quantity: body.get("quantity"),
    };
    requests.push(request);
    if (request.action === "jluxe_variation_picker") {
      if (failPickerLoad) return { json: async () => ({ success: false, data: {} }) };
      return {
        json: async () => ({
          success: true,
          data: { image: "/variation.webp", url: "/product/variation/", name: "Variable offer", html: variationForm },
        }),
      };
    }
    if (request.op === "add" && request.productId === "700") {
      if (failVariationAdd) return { json: async () => ({ success: false, data: { message: "تنوع انتخاب‌شده موجود نیست." } }) };
      return { json: async () => ({ success: true, data: { itemCount: 2, suggested_html: refreshedMarkup } }) };
    }
    if (request.op === "add" && request.productId === "501") {
      if (failSimpleAdd) return { json: async () => ({ success: false, data: { message: "پیشنهاد ساده فعلاً موجود نیست." } }) };
      return { json: async () => ({ success: true, data: { itemCount: 3, suggested_html: suggestedMarkup(false).replace('data-pa-main="100"', 'data-pa-main="190"') } }) };
    }
    if (request.op === "pa_services") {
      return { json: async () => ({ success: true, data: { itemCount: 3 } }) };
    }
    throw new Error(`Unexpected modal request: ${JSON.stringify(request)}`);
  };
  window.jluxeCartPost = (url, body) => window.fetch(url, { method: "POST", body, credentials: "same-origin" }).then((response) => response.json());

  const source = read("../assets/js/woocommerce.js");
  const suggestionsMarker = source.indexOf("پاپ‌آپِ «محصولات پیشنهادی»");
  const suggestionsStart = source.indexOf("(function () {", suggestionsMarker);
  const suggestionsEnd = source.indexOf("\n})();\n\n/**\n * افزودن به سبد در فرمِ صفحه", suggestionsStart);
  assert.ok(suggestionsStart > suggestionsMarker && suggestionsEnd > suggestionsStart);
  window.eval(source.slice(suggestionsStart, suggestionsEnd + "\n})();".length));

  const pickerMarker = source.indexOf("مودالِ انتخاب سریعِ تنوع");
  const pickerStart = source.indexOf("(function () {", pickerMarker);
  const pickerEnd = source.indexOf("\n})();\n\n/**\n * توضیحاتِ کوتاهِ محصول", pickerStart);
  assert.ok(pickerStart > pickerMarker && pickerEnd > pickerStart, "the quick-variation handler is isolated from unrelated product handlers");
  window.eval(source.slice(pickerStart, pickerEnd + "\n})();".length));

  let modal = window.document.querySelector("[data-jluxe-suggested-modal]");
  const trigger = window.document.getElementById("open-modal");
  window.jluxeOpenSuggestedProductsModal();
  const clickVariableOffer = () => modal.querySelector("[data-jluxe-quick-variant]").click();
  const closePicker = () => window.document.querySelector(".jluxe-variant-modal-close").click();

  clickVariableOffer();
  await new Promise((resolve) => setTimeout(resolve, 10));
  let picker = window.document.querySelector(".jluxe-variant-modal-backdrop");
  assert.ok(picker, "a variable offer opens the quick-variation picker");
  assert.equal(modal.classList.contains("hidden"), true, "the suggestion sheet closes before the picker backdrop appears");
  assert.equal(window.document.querySelectorAll(".jluxe-variant-modal-backdrop:not(.hidden)").length, 1, "there is one active full-screen overlay, not stacked backdrops");
  assert.match(picker.querySelector(".jluxe-variant-modal-error").textContent, /مشکلی پیش آمد/);
  closePicker();
  assert.equal(window.document.querySelector(".jluxe-variant-modal-backdrop"), null, "closing a picker load error removes its overlay");
  assert.equal(modal.getAttribute("aria-hidden"), "false", "closing a failed picker restores the suggestion sheet");
  assert.equal(window.document.body.style.overflow, "hidden", "the reopened sheet retains the scroll lock");
  assert.equal(modal.querySelector('[data-pa-product="501"]').getAttribute("aria-pressed"), "true");
  assert.equal(modal.querySelector('[data-pa-service="s0"]').getAttribute("aria-pressed"), "true");

  failPickerLoad = false;
  clickVariableOffer();
  await new Promise((resolve) => setTimeout(resolve, 10));
  picker = window.document.querySelector(".jluxe-variant-modal-backdrop");
  assert.ok(picker.querySelector("form.variations_form"), "the loaded modal contains the real variable-product form");
  const form = picker.querySelector("form.variations_form");
  form.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  await new Promise((resolve) => setTimeout(resolve, 10));
  assert.match(picker.querySelector(".jluxe-variant-modal-error").textContent, /تنوع انتخاب‌شده موجود نیست/);
  assert.equal(window.document.querySelector(".jluxe-variant-modal-backdrop"), picker, "an add failure stays inside the picker and remains retryable");
  const failedRequest = requests.find((request) => request.op === "add");
  assert.deepEqual(failedRequest, {
    action: "jluxe_cart",
    op: "add",
    productId: "700",
    variationId: "701",
    contextId: "500",
    serviceKeys: null,
    quantity: null,
  }, "the picker submits parent id, selected variation, and original recommendation context separately");
  closePicker();
  assert.equal(modal.getAttribute("aria-hidden"), "false", "cancel after a variation-add error returns to suggestions");
  assert.equal(modal.querySelector('[data-pa-product="501"]').getAttribute("aria-pressed"), "true");
  assert.equal(modal.querySelector('[data-pa-service="s0"]').getAttribute("aria-pressed"), "true");

  failVariationAdd = false;
  failSimpleAdd = true;
  clickVariableOffer();
  await new Promise((resolve) => setTimeout(resolve, 10));
  picker = window.document.querySelector(".jluxe-variant-modal-backdrop");
  const successfulVariationForm = picker.querySelector("form.variations_form");
  successfulVariationForm.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  successfulVariationForm.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  await new Promise((resolve) => setTimeout(resolve, 30));
  assert.equal(window.document.querySelector(".jluxe-variant-modal-backdrop"), null, "successful variation add closes the quick picker");
  assert.equal(modal.getAttribute("aria-hidden"), "false", "the sheet returns while the previously checked offers are committed");
  assert.equal(window.document.body.style.overflow, "hidden", "the retryable sheet keeps the scroll lock");
  assert.equal(modal.querySelector('[data-pa-product="501"]').getAttribute("aria-pressed"), "true", "a failed simple offer remains selected after the variation itself succeeds");
  assert.equal(modal.querySelector('[data-pa-service="s0"]').getAttribute("aria-pressed"), "true", "the selected service remains available after a partial add");
  assert.equal(modal.querySelector('[data-jluxe-quick-variant="700"]'), null, "the just-added variable recommendation is removed to prevent an accidental duplicate");
  assert.equal(modal.querySelector("[data-pa-confirm]").disabled, false, "the failed fixed offer can be retried");
  assert.match(modal.querySelector("[data-pa-error]").textContent, /پیشنهاد ساده فعلاً موجود نیست/);
  assert.equal(modal.querySelector("[data-pa-total]").textContent, "۲۰۰", "the sheet total follows the server cart snapshot and still includes pending choices");
  assert.deepEqual(requests.slice(-2).map(({ op, productId, contextId, serviceKeys }) => ({ op, productId, contextId, serviceKeys })), [
    { op: "add", productId: "501", contextId: "500", serviceKeys: null },
    { op: "pa_services", productId: null, contextId: "500", serviceKeys: "s0" },
  ], "the selected fixed offer and service are committed after the variable add, with the original suggestion context");
  assert.equal(requests.filter((request) => request.op === "add" && request.productId === "700").length, 2, "the failed attempt and success contain no duplicate in-flight variation add");
  assert.equal(cartUpdates[0].itemCount, 2, "the successful variation refreshes the mini-cart immediately");

  failSimpleAdd = false;
  modal.querySelector("[data-pa-confirm]").click();
  await new Promise((resolve) => setTimeout(resolve, 30));
  assert.equal(window.document.querySelector("[data-jluxe-suggested-modal]"), null, "a successful retry removes the completed recommendation sheet");
  assert.equal(window.jluxeOpenSuggestedProductsModal, null, "the removed sheet cannot be reopened from its stale binding");
  assert.equal(window.document.body.style.overflow, "");
  assert.equal(requests.filter((request) => request.op === "add").map((request) => request.productId).join(","), "700,700,501,501", "retry adds only the failed simple product and never repeats either confirmed success");
  assert.deepEqual(requests.slice(-2).map((request) => request.op), ["add", "pa_services"], "retry preserves the service selection without repeating the variable add");
  assert.ok(cartUpdates.length >= 4, "every successful step publishes a current cart snapshot");
  assert.equal(trigger.isConnected, true);
});

test("an ambiguous network failure warns before leaving a selected offer retryable", async (t) => {
  const dom = new JSDOM(
    `<!doctype html><html><body><button id="open-modal">Open</button>${suggestedMarkup(true)}</body></html>`,
    { url: "https://shop.test/product/main/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  t.after(() => dom.window.close());
  const { window } = dom;
  window.JLuxeThemeSettings = { cart: { ajaxUrl: "https://shop.test/cart-ajax", nonce: "nonce-test" } };
  window.eval(read("../assets/js/storefront-utils.js"));

  let requests = 0;
  window.fetch = async (_url, options = {}) => {
    requests++;
    assert.equal(options.body.get("op"), "add");
    throw new Error("simulated connection loss after request dispatch");
  };
  window.jluxeCartPost = (url, body) => window.fetch(url, { method: "POST", body, credentials: "same-origin" }).then((response) => response.json());

  const source = read("../assets/js/woocommerce.js");
  const marker = source.indexOf("پاپ‌آپِ «محصولات پیشنهادی»");
  const start = source.indexOf("(function () {", marker);
  const end = source.indexOf("\n})();\n\n/**\n * افزودن به سبد در فرمِ صفحه", start);
  assert.ok(marker >= 0 && start > marker && end > start);
  window.eval(source.slice(start, end + "\n})();".length));

  const modal = window.document.querySelector("[data-jluxe-suggested-modal]");
  window.jluxeOpenSuggestedProductsModal();
  modal.querySelector("[data-pa-confirm]").click();
  await new Promise((resolve) => setTimeout(resolve, 20));

  assert.equal(requests, 1, "a failed transport is not automatically replayed as an add");
  assert.equal(modal.getAttribute("aria-hidden"), "false", "the dialog stays open so the customer can inspect the outcome");
  assert.equal(modal.querySelector('[data-pa-product="501"]').getAttribute("aria-pressed"), "true", "the uncertain offer remains visible rather than being marked as a confirmed success");
  assert.equal(modal.querySelector("[data-pa-confirm]").disabled, false, "a deliberate retry remains possible after the warning");
  assert.match(modal.querySelector("[data-pa-error]").textContent, /ممکن است به سبد اضافه شده باشد/);
  assert.match(modal.querySelector("[data-pa-error]").textContent, /پیش از تلاشِ دوباره، سبد را بررسی کنید/);
});

test("the variation picker keeps an uncertain add visible and blocks in-flight duplicate submits", async (t) => {
  const dom = new JSDOM(
    `<!doctype html><html><body><button id="open-modal">Open</button>${suggestedMarkup(true)}</body></html>`,
    { url: "https://shop.test/product/main/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  t.after(() => dom.window.close());
  const { window } = dom;
  window.JLuxeThemeSettings = { cart: { ajaxUrl: "https://shop.test/cart-ajax", nonce: "nonce-test" } };
  window.eval(read("../assets/js/storefront-utils.js"));

  let addRequests = 0;
  const variationForm = `<form class="variations_form" data-product_id="700">
    <input type="hidden" name="add-to-cart" value="700">
    <input type="hidden" name="variation_id" value="701">
    <input type="hidden" name="attribute_pa_size" value="medium">
    <button type="button" class="single_add_to_cart_button">Add variation</button>
  </form>`;
  window.fetch = async (_url, options = {}) => {
    const body = options.body;
    if (body.get("action") === "jluxe_variation_picker") {
      return { json: async () => ({ success: true, data: { image: "/variation.webp", url: "/product/variation/", name: "Variable offer", html: variationForm } }) };
    }
    if (body.get("op") === "add") {
      addRequests++;
      throw new Error("simulated connection loss after request dispatch");
    }
    throw new Error(`Unexpected picker request: ${body.get("action")}/${body.get("op")}`);
  };
  window.jluxeCartPost = (url, body) => window.fetch(url, { method: "POST", body, credentials: "same-origin" }).then((response) => response.json());

  const source = read("../assets/js/woocommerce.js");
  const suggestionsMarker = source.indexOf("پاپ‌آپِ «محصولات پیشنهادی»");
  const suggestionsStart = source.indexOf("(function () {", suggestionsMarker);
  const suggestionsEnd = source.indexOf("\n})();\n\n/**\n * افزودن به سبد در فرمِ صفحه", suggestionsStart);
  assert.ok(suggestionsStart > suggestionsMarker && suggestionsEnd > suggestionsStart);
  window.eval(source.slice(suggestionsStart, suggestionsEnd + "\n})();".length));
  const pickerMarker = source.indexOf("مودالِ انتخاب سریعِ تنوع");
  const pickerStart = source.indexOf("(function () {", pickerMarker);
  const pickerEnd = source.indexOf("\n})();\n\n/**\n * توضیحاتِ کوتاهِ محصول", pickerStart);
  assert.ok(pickerStart > pickerMarker && pickerEnd > pickerStart);
  window.eval(source.slice(pickerStart, pickerEnd + "\n})();".length));

  const modal = window.document.querySelector("[data-jluxe-suggested-modal]");
  window.jluxeOpenSuggestedProductsModal();
  modal.querySelector("[data-jluxe-quick-variant]").click();
  await new Promise((resolve) => setTimeout(resolve, 10));
  const picker = window.document.querySelector(".jluxe-variant-modal-backdrop");
  const form = picker.querySelector("form.variations_form");
  assert.ok(form, "the quick picker loaded before attempting the add");
  form.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  form.dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
  await new Promise((resolve) => setTimeout(resolve, 20));

  assert.equal(addRequests, 1, "a fast double submit sends only one variation mutation");
  assert.equal(window.document.querySelector(".jluxe-variant-modal-backdrop"), picker, "an ambiguous response does not close the picker as if the add failed definitively");
  assert.match(picker.querySelector(".jluxe-variant-modal-error").textContent, /ممکن است به سبد اضافه شده باشد/);
  assert.equal(form.querySelector(".single_add_to_cart_button").disabled, false, "the form remains actionable after the warning");
  assert.equal(modal.querySelector('[data-pa-product="501"]').getAttribute("aria-pressed"), "true", "the underlying selected recommendation remains intact");
  assert.equal(window.document.body.style.overflow, "hidden", "the active dialog retains the page scroll lock");

  picker.querySelector(".jluxe-variant-modal-close").click();
  assert.equal(modal.getAttribute("aria-hidden"), "false", "cancel returns to suggestions without losing the uncertain add warning");
  assert.match(modal.querySelector("[data-pa-error]").textContent, /ممکن است به سبد اضافه شده باشد/);
  modal.querySelector("[data-jluxe-quick-variant]").click();
  await new Promise((resolve) => setTimeout(resolve, 10));
  const reopenedPicker = window.document.querySelector(".jluxe-variant-modal-backdrop");
  assert.match(reopenedPicker.querySelector(".jluxe-variant-modal-error").textContent, /پیش از تلاشِ دوباره، سبد را بررسی کنید/);
  assert.equal(addRequests, 1, "closing and reopening the picker never retries the ambiguous add automatically");
});

function suggestedMarkup(selected) {
  const productPressed = selected ? "true" : "false";
  const servicePressed = selected ? "true" : "false";
  return `<div class="jluxe-pa fixed inset-0 hidden" data-jluxe-suggested-modal aria-hidden="true" data-pa-context="500" data-pa-main="100">
    <div class="jluxe-pa-backdrop" data-jluxe-suggested-close></div>
    <section class="jluxe-pa-sheet" role="dialog" aria-modal="true" aria-label="Suggestions">
      <button type="button" data-jluxe-suggested-close>Close</button>
      <div class="jluxe-pa-body">
        <button class="jluxe-pa-row ${selected ? "is-selected" : ""}" data-pa-product="501" data-pa-amount="50" aria-pressed="${productPressed}"><span class="jluxe-pa-name">Cross-sell</span></button>
        <button class="jluxe-pa-row ${selected ? "is-selected" : ""}" data-pa-service="s0" data-pa-amount="10" aria-pressed="${servicePressed}"><span class="jluxe-pa-name">Warranty</span></button>
        <button type="button" class="jluxe-pa-row" data-jluxe-quick-variant="700">Choose variable option</button>
      </div>
      <strong data-pa-total></strong>
      <button type="button" data-pa-confirm data-pa-confirm-selected="Add selected" data-pa-confirm-empty="Continue"><span data-pa-confirm-label></span></button>
    </section>
  </div>`;
}
