import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import test from "node:test";

const template = await readFile(new URL("../woocommerce/content-single-product-reference.php", import.meta.url), "utf8");
const scriptStart = template.indexOf("<script>\n( function () {");
const scriptEnd = template.indexOf("</script>", scriptStart);
assert.ok(scriptStart >= 0 && scriptEnd > scriptStart, "reference product inline behavior is present");
const referenceScript = template.slice(scriptStart + "<script>".length, scriptEnd);

function setup() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="jluxe-cp3">
        <div class="cp3-gallery" data-cp3-gallery data-jluxe-gallery data-jluxe-gallery-current="0">
          <button type="button" data-cp3-zoom-box data-jluxe-gallery-open-current>
            <img data-cp3-zoom-img src="/image-0.jpg" alt="Product image">
          </button>
          <div class="cp3-thumbs">
            <button type="button" class="cp3-thumb is-active" data-cp3-thumb data-jluxe-gallery-open="0" data-full="/image-0-full.jpg" aria-pressed="true"></button>
            <button type="button" class="cp3-thumb" data-cp3-thumb data-jluxe-gallery-open="1" data-full="/image-1-full.jpg" aria-pressed="false"></button>
            <button type="button" class="cp3-thumb" data-cp3-thumb data-jluxe-gallery-open="2" data-full="/image-2-full.jpg" aria-pressed="false"></button>
          </div>
          <div class="reference-mobile-gallery-dots">
            <button type="button" class="reference-mobile-gallery-dot is-active" data-cp3-thumb data-reference-gallery-index="0" aria-pressed="true"></button>
            <button type="button" class="reference-mobile-gallery-dot" data-cp3-thumb data-reference-gallery-index="1" aria-pressed="false"></button>
            <button type="button" class="reference-mobile-gallery-dot" data-cp3-thumb data-reference-gallery-index="2" aria-pressed="false"></button>
          </div>
        </div>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/reference/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  window.eval(referenceScript);
  const gallery = window.document.querySelector("[data-cp3-gallery]");
  const zoomBox = window.document.querySelector("[data-cp3-zoom-box]");
  const image = window.document.querySelector("[data-cp3-zoom-img]");
  const thumbs = [...window.document.querySelectorAll(".cp3-thumb")];
  const dots = [...window.document.querySelectorAll(".reference-mobile-gallery-dot")];
  const touch = (type, x, y) => {
    const event = new window.Event(type, { bubbles: true, cancelable: true });
    Object.defineProperty(event, type === "touchstart" ? "touches" : "changedTouches", {
      value: [{ clientX: x, clientY: y }],
    });
    return event;
  };
  return { dom, window, gallery, zoomBox, image, thumbs, dots, touch };
}

test("reference gallery swipe changes the displayed image, dot state, and modal's current index", () => {
  const page = setup();
  page.zoomBox.dispatchEvent(page.touch("touchstart", 240, 30));
  page.zoomBox.dispatchEvent(page.touch("touchend", 170, 36));

  assert.equal(page.image.getAttribute("src"), "/image-1-full.jpg");
  assert.equal(page.gallery.getAttribute("data-jluxe-gallery-current"), "1");
  assert.equal(page.thumbs[1].classList.contains("is-active"), true);
  assert.equal(page.dots[1].classList.contains("is-active"), true);
  assert.equal(page.dots[1].getAttribute("aria-pressed"), "true");

  page.zoomBox.dispatchEvent(page.touch("touchstart", 30, 30));
  page.zoomBox.dispatchEvent(page.touch("touchend", 120, 33));
  assert.equal(page.image.getAttribute("src"), "/image-0-full.jpg", "a swipe to the right returns to the previous image");
  assert.equal(page.gallery.getAttribute("data-jluxe-gallery-current"), "0");
  page.dom.window.close();
});

test("vertical movement remains page scrolling and a swipe does not accidentally open the zoom modal", async () => {
  const page = setup();
  let modalOpenClicks = 0;
  page.window.document.addEventListener("click", (event) => {
    if (event.target.closest("[data-jluxe-gallery-open-current]")) modalOpenClicks += 1;
  });

  page.zoomBox.dispatchEvent(page.touch("touchstart", 220, 20));
  page.zoomBox.dispatchEvent(page.touch("touchend", 150, 100));
  assert.equal(page.gallery.getAttribute("data-jluxe-gallery-current"), "0", "a diagonal/vertical gesture does not change slides");

  page.zoomBox.dispatchEvent(page.touch("touchstart", 220, 20));
  page.zoomBox.dispatchEvent(page.touch("touchend", 150, 25));
  assert.equal(page.gallery.getAttribute("data-jluxe-gallery-current"), "1");
  page.zoomBox.click();
  assert.equal(modalOpenClicks, 0, "the synthetic click after a swipe is suppressed");

  await new Promise((resolve) => setTimeout(resolve, 475));
  page.zoomBox.click();
  assert.equal(modalOpenClicks, 1, "a normal tap still opens the image viewer");
  page.dom.window.close();
});

test("mobile gallery gestures preserve vertical panning while handling horizontal swipes", () => {
  assert.match(template, /touch-action:pan-y pinch-zoom/);
  assert.match(template, /zoomBox\.addEventListener\( 'touchstart'/);
  assert.match(template, /zoomBox\.addEventListener\( 'touchend'/);
  assert.match(template, /gallery\.setAttribute\( 'data-jluxe-gallery-current'/);
});
