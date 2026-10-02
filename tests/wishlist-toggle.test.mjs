import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(
  new URL("../assets/js/woocommerce.js", import.meta.url),
  "utf8",
);
const marker = "علاقه‌مندی‌ها (localStorage";
const markerIndex = source.indexOf(marker);
const start = source.indexOf("(function () {", markerIndex);
const end = source.indexOf("\n})();", start);
assert.ok(markerIndex >= 0 && start > markerIndex && end > start);
const wishlistScript = source.slice(start, end + "\n})();".length);

test("wishlist controls for the same product stay synchronized across layouts", () => {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <button class="cp3-heart" data-jluxe-wishlist-toggle="314" data-jluxe-wishlist-inactive-label="افزودن به علاقه‌مندی‌ها" data-jluxe-wishlist-active-label="حذف از علاقه‌مندی‌ها" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها"><svg fill="none"></svg></button>
      <button class="jluxe-sticky-cta-heart" data-jluxe-wishlist-toggle="314" data-jluxe-wishlist-inactive-label="افزودن به علاقه‌مندی‌ها" data-jluxe-wishlist-active-label="حذف از علاقه‌مندی‌ها" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها"><svg fill="none"></svg></button>
      <button data-jluxe-wishlist-toggle="999" aria-pressed="false"><svg fill="none"></svg></button>
    </body></html>`,
    { url: "https://shop.test/product/sample/", runScripts: "outside-only" },
  );
  const { document, localStorage } = dom.window;
  localStorage.setItem("jluxe_wishlist", JSON.stringify(["314"]));
  vm.runInContext(wishlistScript, dom.getInternalVMContext());

  const pageHeart = document.querySelector(".cp3-heart");
  const stickyHeart = document.querySelector(".jluxe-sticky-cta-heart");
  const unrelated = document.querySelector('[data-jluxe-wishlist-toggle="999"]');
  const sameProductButtons = [pageHeart, stickyHeart];

  for (const button of sameProductButtons) {
    assert.equal(button.getAttribute("aria-pressed"), "true");
    assert.equal(button.querySelector("svg").getAttribute("fill"), "currentColor");
    assert.equal(button.classList.contains("text-boom-sale"), true);
    assert.equal(button.getAttribute("aria-label"), "حذف از علاقه‌مندی‌ها");
  }
  assert.equal(unrelated.getAttribute("aria-pressed"), "false");

  // Clicking the heart in either the page buy row or the sticky bar updates all copies.
  stickyHeart.querySelector("svg").dispatchEvent(
    new dom.window.MouseEvent("click", { bubbles: true }),
  );
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist")), []);
  for (const button of sameProductButtons) {
    assert.equal(button.getAttribute("aria-pressed"), "false");
    assert.equal(button.querySelector("svg").getAttribute("fill"), "none");
    assert.equal(button.classList.contains("text-boom-sale"), false);
    assert.equal(button.getAttribute("aria-label"), "افزودن به علاقه‌مندی‌ها");
  }

  pageHeart.dispatchEvent(new dom.window.MouseEvent("click", { bubbles: true }));
  assert.deepEqual(JSON.parse(localStorage.getItem("jluxe_wishlist")), ["314"]);
  for (const button of sameProductButtons) {
    assert.equal(button.getAttribute("aria-pressed"), "true");
  }
  assert.equal(unrelated.getAttribute("aria-pressed"), "false");
});
