import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const root = new URL("../", import.meta.url);
const [script, styles, classicTemplate, defaultTemplate] = await Promise.all([
  readFile(new URL("assets/js/sticky-cta.js", root), "utf8"),
  readFile(new URL("src/styles/storefront.css", root), "utf8"),
  readFile(new URL("woocommerce/content-single-product-classic.php", root), "utf8"),
  readFile(new URL("woocommerce/content-single-product.php", root), "utf8"),
]);

function createLayout(t, desktop = true) {
  const dom = new JSDOM(
    `<!doctype html><html dir="rtl"><body>
      <div data-jluxe-product-buybox><div data-jluxe-buybox-surface>Buy box</div></div>
      <div class="jluxe-product-detail-layout" data-jluxe-buybox-range>
        <div data-jluxe-product-detail-main><nav data-cp3-nav style="position:sticky;top:96px;margin-top:32px"></nav></div>
        <div data-jluxe-buybox-rail aria-hidden="true"></div>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/item/", runScripts: "outside-only", pretendToBeVisual: true },
  );
  t.after(() => dom.window.close());

  const { window } = dom;
  const state = { top: 500, bottom: 1800 };
  const range = window.document.querySelector("[data-jluxe-buybox-range]");
  const rail = range.querySelector("[data-jluxe-buybox-rail]");
  const holder = window.document.querySelector("[data-jluxe-product-buybox]");
  const surface = holder.querySelector("[data-jluxe-buybox-surface]");
  const rect = (left, top, right, bottom) => ({
    left,
    top,
    right,
    bottom,
    width: right - left,
    height: bottom - top,
  });

  range.getBoundingClientRect = () => rect(0, state.top, 1200, state.bottom);
  rail.getBoundingClientRect = () => rect(20, state.top, 320, state.bottom);
  holder.getBoundingClientRect = () => rect(20, -500, 320, -150);
  surface.getBoundingClientRect = () => rect(20, -500, 300, -180);
  Object.defineProperty(holder, "offsetHeight", { configurable: true, get: () => 350 });
  Object.defineProperty(surface, "offsetWidth", { configurable: true, get: () => 280 });
  Object.defineProperty(surface, "scrollHeight", { configurable: true, get: () => 320 });

  const queuedFrames = [];
  window.requestAnimationFrame = (callback) => {
    queuedFrames.push(callback);
    return queuedFrames.length;
  };
  window.matchMedia = (query) => ({
    matches: desktop && query === "(min-width: 1200px)",
    addEventListener() {},
    addListener() {},
  });
  const flushFrames = () => {
    while (queuedFrames.length) queuedFrames.shift()();
  };

  window.eval(script);
  flushFrames();
  return { window, state, range, holder, surface, flushFrames };
}

test("R211 both product templates reserve a side rail and keep the real buy form in its original DOM", () => {
  for (const template of [classicTemplate, defaultTemplate]) {
    assert.match(template, /data-jluxe-product-buybox/);
    assert.match(template, /data-jluxe-buybox-surface/);
    assert.match(template, /data-jluxe-buybox-range/);
    assert.match(template, /data-jluxe-buybox-rail/);
  }

  assert.match(styles, /grid-template-columns:\s*minmax\(0, 1fr\) minmax\(280px, 25%\)/);
  assert.match(styles, /\.jluxe-product-detail-layout\[data-jluxe-buybox-sticky-ready\].*\.jluxe-product-section-nav\s*\{\s*margin-inline: 0/s);
  assert.match(styles, /\[data-jluxe-buybox-surface\]\.is-pinned-to-details\s*\{\s*position: fixed;/);
});

test("R211 buy surface pins beside details, follows the section boundary, then restores its original position", (t) => {
  const { window, state, range, holder, surface, flushFrames } = createLayout(t);
  assert.equal(range.hasAttribute("data-jluxe-buybox-sticky-ready"), true, "desktop enables the reserved reading column");
  assert.equal(surface.classList.contains("is-pinned-to-details"), false, "the original product summary remains in normal flow before details arrive");

  state.top = 50;
  state.bottom = 1500;
  window.dispatchEvent(new window.Event("scroll"));
  flushFrames();

  assert.equal(surface.classList.contains("is-pinned-to-details"), true);
  assert.equal(surface.style.top, "104px", "the buy panel clears the sticky section navigation/header stack");
  assert.equal(surface.style.left, "20px", "the panel aligns to the lower reading layout's reserved rail");
  assert.equal(surface.style.width, "280px", "the original buy panel does not overflow the rail");
  assert.equal(holder.style.minHeight, "350px", "the original product summary keeps its reserved layout height");

  state.top = -1000;
  state.bottom = 300;
  window.dispatchEvent(new window.Event("scroll"));
  flushFrames();
  assert.equal(surface.style.top, "-20px", "the panel moves with the end of the description/review range instead of covering related products");

  state.bottom = -1;
  window.dispatchEvent(new window.Event("scroll"));
  flushFrames();
  assert.equal(surface.classList.contains("is-pinned-to-details"), false);
  assert.equal(surface.style.top, "", "all temporary fixed-position styles are removed at the range boundary");
  assert.equal(holder.style.minHeight, "", "the original holder styles are restored after the sticky range");
});

test("R211 extended buy box is disabled below the desktop breakpoint", (t) => {
  const { range, surface } = createLayout(t, false);
  assert.equal(range.hasAttribute("data-jluxe-buybox-sticky-ready"), false);
  assert.equal(surface.classList.contains("is-pinned-to-details"), false);
});
