import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(
  new URL("../assets/js/woocommerce.js", import.meta.url),
  "utf8",
);
const marker = source.indexOf("پاپ‌آپِ آلبومِ تصاویرِ محصول");
const start = source.indexOf("(function () {", marker);
const galleryIife = source.slice(start).trim();
assert.ok(marker >= 0 && start > marker && galleryIife.endsWith("})();"));

function setup() {
  const dom = new JSDOM(
    `<!doctype html><html dir="rtl"><body style="overflow:auto"><main>
      <div data-jluxe-gallery data-jluxe-gallery-current="1">
        <button id="open-current" type="button" data-jluxe-gallery-open-current>Open current</button>
        <button id="open-first" type="button" data-jluxe-gallery-open="0">Open first</button>
        <div class="jluxe-cp3-gallery-modal hidden opacity-0" data-jluxe-gallery-modal
          data-jluxe-gallery-modal-current="0" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
          <div><button type="button" data-jluxe-gallery-modal-close>Close</button></div>
          <div class="jluxe-cp3-gallery-modal__track" data-jluxe-gallery-modal-track>
            <img data-jluxe-gallery-modal-image data-src="/large-1.jpg" data-srcset="/medium-1.jpg 600w, /large-1.jpg 1200w" data-sizes="90vw" alt="One" aria-hidden="true">
            <img data-jluxe-gallery-modal-image data-src="/large-2.jpg" data-srcset="/medium-2.jpg 600w, /large-2.jpg 1200w" data-sizes="90vw" alt="Two" aria-hidden="true">
            <img data-jluxe-gallery-modal-image data-src="/large-3.jpg" data-srcset="/medium-3.jpg 600w, /large-3.jpg 1200w" data-sizes="90vw" alt="Three" aria-hidden="true">
          </div>
          <button type="button" data-jluxe-gallery-modal-prev>Previous</button>
          <button type="button" data-jluxe-gallery-modal-next>Next</button>
          <span data-jluxe-gallery-modal-counter></span>
        </div>
      </div>
    </main></body></html>`,
    {
      url: "https://shop.test/product/gallery/",
      pretendToBeVisual: true,
      runScripts: "outside-only",
    },
  );
  dom.window.eval(galleryIife);
  // Exercise the same one-time modal relocation used after parsing in WordPress.
  dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded"));
  return dom;
}

function click(window, selector) {
  const node = window.document.querySelector(selector);
  assert.ok(node, `missing ${selector}`);
  node.dispatchEvent(new window.MouseEvent("click", { bubbles: true, cancelable: true }));
}

function touch(window, target, type, touches, changedTouches = touches) {
  const event = new window.Event(type, { bubbles: true, cancelable: true });
  Object.defineProperties(event, {
    touches: { value: touches },
    changedTouches: { value: changedTouches },
  });
  target.dispatchEvent(event);
  return event;
}

const point = (clientX, clientY, identifier = 0) => ({ clientX, clientY, identifier });

function tap(window, image, x = 100, y = 100) {
  touch(window, image, "touchstart", [point(x, y)]);
  touch(window, image, "touchend", [], [point(x, y)]);
}

test("classic gallery opens the selected slide, lazy-loads it, keeps keyboard/arrow navigation, and restores focus", async () => {
  const dom = setup();
  const { document, KeyboardEvent, MouseEvent } = dom.window;
  const opener = document.getElementById("open-current");
  opener.focus();
  click(dom.window, "#open-current");

  const modal = document.querySelector("[data-jluxe-gallery-modal]");
  const images = [...modal.querySelectorAll("[data-jluxe-gallery-modal-image]")];
  assert.equal(modal.parentElement, document.body, "the modal is moved out of product/form stacking contexts");
  assert.equal(modal.getAttribute("aria-hidden"), "false");
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "1", "the main-image trigger opens the current thumbnail index");
  assert.equal(images[0].hasAttribute("src"), false, "offscreen gallery slides stay unloaded");
  assert.equal(images[1].getAttribute("src"), "/large-2.jpg", "only the selected full image loads on open");
  assert.equal(images[1].getAttribute("srcset"), "/medium-2.jpg 600w, /large-2.jpg 1200w");
  assert.equal(modal.querySelector("[data-jluxe-gallery-modal-close]"), document.activeElement);
  assert.equal(document.body.style.overflow, "hidden");

  document.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true, cancelable: true }));
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "2", "the physical right arrow advances, matching the right-hand next button");
  assert.equal(images[2].getAttribute("aria-hidden"), "false");
  assert.equal(images[2].getAttribute("src"), "/large-3.jpg");

  document.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true, cancelable: true }));
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "1", "physical left-arrow returns to the previous image even on an RTL page");
  document.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true, cancelable: true }));

  click(dom.window, "[data-jluxe-gallery-modal-prev]");
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "1");
  click(dom.window, "[data-jluxe-gallery-modal-next]");
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "2");

  document.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", shiftKey: true, bubbles: true, cancelable: true }));
  assert.equal(document.activeElement, modal.querySelector("[data-jluxe-gallery-modal-next]"), "focus wraps inside the dialog");
  document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
  assert.equal(modal.getAttribute("aria-hidden"), "true");
  assert.equal(document.body.style.overflow, "auto", "the prior page scroll state is restored");
  await new Promise((resolve) => setTimeout(resolve, 260));
  assert.equal(document.activeElement, opener, "closing returns focus to the opening control");

  click(dom.window, "#open-first");
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "0", "thumbnail triggers open their own slide");
  assert.equal(images[0].getAttribute("src"), "/large-1.jpg");
  click(dom.window, "[data-jluxe-gallery-modal-close]");
  dom.window.close();
});

test("classic gallery double-tap, pinch, touch drag, swipe, and mouse drag gestures work", () => {
  const dom = setup();
  const { document, MouseEvent } = dom.window;
  click(dom.window, "#open-first");
  const modal = document.querySelector("[data-jluxe-gallery-modal]");
  const track = modal.querySelector("[data-jluxe-gallery-modal-track]");
  const image = track.querySelector('[data-jluxe-gallery-modal-image][aria-hidden="false"]');
  Object.defineProperties(image, {
    offsetWidth: { configurable: true, value: 400 },
    offsetHeight: { configurable: true, value: 300 },
  });
  track.getBoundingClientRect = () => ({ left: 0, top: 0, width: 400, height: 300, right: 400, bottom: 300 });

  tap(dom.window, image);
  tap(dom.window, image);
  assert.equal(image.getAttribute("data-jluxe-zoom-scale"), "2.5", "double-tap zooms in");
  tap(dom.window, image);
  tap(dom.window, image);
  assert.equal(image.getAttribute("data-jluxe-zoom-scale"), "1", "double-tap toggles zoom back out");

  touch(dom.window, image, "touchstart", [point(100, 100, 1), point(200, 100, 2)]);
  const pinchMove = touch(dom.window, image, "touchmove", [point(50, 100, 1), point(250, 100, 2)]);
  assert.equal(pinchMove.defaultPrevented, true, "pinch suppresses browser page zoom while manipulating the image");
  assert.equal(image.getAttribute("data-jluxe-zoom-scale"), "2");

  touch(dom.window, image, "touchend", [point(250, 100, 2)], [point(50, 100, 1)]);
  touch(dom.window, image, "touchmove", [point(600, 500, 2)]);
  assert.equal(Number(image.getAttribute("data-jluxe-zoom-x")), 200, "touch panning clamps to the image's horizontal bounds");
  assert.equal(Number(image.getAttribute("data-jluxe-zoom-y")), 150, "touch panning clamps to the image's vertical bounds");
  touch(dom.window, image, "touchend", [], [point(600, 500, 2)]);

  image.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true, button: 0, clientX: 100, clientY: 100 }));
  document.dispatchEvent(new MouseEvent("mousemove", { bubbles: true, clientX: 0, clientY: 0 }));
  assert.equal(Number(image.getAttribute("data-jluxe-zoom-x")), 100, "mouse drag pans a zoomed image");
  assert.equal(Number(image.getAttribute("data-jluxe-zoom-y")), 50);
  document.dispatchEvent(new MouseEvent("mouseup", { bubbles: true }));

  // Double-tap to reset before checking the normal 1x slide-swipe behavior.
  tap(dom.window, image);
  tap(dom.window, image);
  assert.equal(image.getAttribute("data-jluxe-zoom-scale"), "1");
  touch(dom.window, image, "touchstart", [point(200, 120)]);
  touch(dom.window, image, "touchmove", [point(100, 120)]);
  touch(dom.window, image, "touchend", [], [point(100, 120)]);
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "1", "left swipe advances to the next slide");
  assert.equal(modal.querySelector('[data-jluxe-gallery-modal-image][aria-hidden="false"]').getAttribute("src"), "/large-2.jpg");
  dom.window.close();
});


test("R169 modal arrows have explicit physical left/right placement and no visible instruction below the image", () => {
  const template = fs.readFileSync(new URL("../woocommerce/content-single-product-classic.php", import.meta.url), "utf8");
  const stage = template.match(/\.jluxe-cp3-gallery-modal__stage\{([^}]+)\}/)?.[1];
  assert.match(stage, /direction:ltr/);
  const start = template.indexOf('<div class="jluxe-cp3-gallery-modal__stage">');
  const end = template.indexOf('<!-- اطلاعات -->', start);
  const markup = template.slice(start, end);
  assert.ok(markup.indexOf('data-jluxe-gallery-modal-prev') < markup.indexOf('data-jluxe-gallery-modal-track'));
  assert.ok(markup.indexOf('data-jluxe-gallery-modal-track') < markup.indexOf('data-jluxe-gallery-modal-next'));
  assert.match(markup, /data-jluxe-gallery-modal-prev[^>]*aria-label="تصویر قبلی"[^>]*>[\s\S]*?d="m14 6-6 6 6 6"/);
  assert.match(markup, /data-jluxe-gallery-modal-next[^>]*aria-label="تصویر بعدی"[^>]*>[\s\S]*?d="m10 6 6 6-6 6"/);
  assert.doesNotMatch(template, /gallery-modal__hint|برای بزرگ‌نمایی دو بار بزنید/);
});

test("R169 selecting a classic variation changes only its border, not fill, label color or weight", () => {
  const template = fs.readFileSync(new URL("../woocommerce/content-single-product-classic.php", import.meta.url), "utf8");
  const active = template.match(/\.jluxe-cp3 \.cp3-pill\.is-active\{([^}]+)\}/)?.[1];
  assert.match(active, /^border-color:hsl\(var\(--primary\)\);?$/);
  const normal = template.match(/\.jluxe-cp3 \.cp3-pill\{([^}]+)\}/)?.[1];
  assert.match(normal, /background:transparent/);
  assert.match(normal, /color:hsl\(var\(--foreground\)\)/);
  assert.match(template, /\.cp3-swatch-color/);
  assert.match(template, /\.cp3-pill\[aria-disabled="true"\]/);
});
