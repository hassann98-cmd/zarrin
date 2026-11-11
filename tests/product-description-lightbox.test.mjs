import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const classicTemplate = fs.readFileSync(new URL("../woocommerce/content-single-product-classic.php", import.meta.url), "utf8");
const referenceTemplate = fs.readFileSync(new URL("../woocommerce/content-single-product-reference.php", import.meta.url), "utf8");
const marker = source.indexOf("/* R321 — Product-description gallery links");
assert.ok(marker >= 0, "R321 description gallery initializer is present");
const galleryScript = source.slice(marker).trim();
assert.ok(galleryScript.endsWith("})();"), "description gallery initializer and shared lightbox handler are complete IIFEs");

function setup() {
  const dom = new JSDOM(
    `<!doctype html><html><body style="overflow:auto"><main>
      <div class="cp3-gallery" data-jluxe-gallery data-jluxe-gallery-current="0" data-jluxe-gallery-modal-id="jluxe-product-gallery-modal">
        <button id="main-gallery-opener" type="button" data-jluxe-gallery-open="0">Main product image</button>
      </div>
      <div id="jluxe-product-gallery-modal" class="jluxe-cp3-gallery-modal hidden opacity-0" data-jluxe-gallery-modal
        data-jluxe-gallery-modal-current="0" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
        <button type="button" data-jluxe-gallery-modal-close aria-label="Close main gallery">Close</button>
        <div data-jluxe-gallery-modal-track>
          <img data-jluxe-gallery-modal-image data-src="/main-product-full.jpg" aria-hidden="true" alt="Main product image">
        </div>
      </div>
      <div class="jluxe-product-description-content">
        <figure class="wp-block-gallery columns-2">
          <figure class="wp-block-image">
            <a id="attachment-link" href="https://shop.test/attachment/front-view/">
              <img class="wp-image-101 size-thumbnail" src="/uploads/front-thumb.jpg"
                data-jluxe-full-src="https://shop.test/uploads/front-full.jpg"
                data-jluxe-media-url="https://shop.test/uploads/front-original.jpg"
                data-jluxe-attachment-url="https://shop.test/attachment/front-view/"
                data-jluxe-full-srcset="https://shop.test/uploads/front-800.jpg 800w, https://shop.test/uploads/front-full.jpg 1600w"
                data-jluxe-full-sizes="90vw" alt="نمای جلو">
            </a>
          </figure>
          <figure class="wp-block-image">
            <a id="media-link" href="https://shop.test/uploads/back-original.jpg">
              <img class="wp-image-102 size-medium" src="/uploads/back-medium.jpg"
                data-jluxe-full-src="https://shop.test/uploads/back-full.jpg"
                data-jluxe-media-url="https://shop.test/uploads/back-original.jpg"
                data-jluxe-attachment-url="https://shop.test/attachment/back-view/" alt="نمای پشت">
            </a>
          </figure>
        </figure>
        <div class="gallery gallery-columns-1">
          <a id="short-description-media" href="https://shop.test/uploads/short-description-image.webp">
            <img class="wp-image-103" src="/uploads/short-thumb.webp"
              data-jluxe-full-src="https://shop.test/uploads/short-full.webp"
              data-jluxe-media-url="https://shop.test/uploads/short-description-image.webp" alt="نمای نزدیک">
          </a>
        </div>
        <div class="gallery gallery-columns-1">
          <a id="animated-gif-link" href="https://shop.test/attachment/animated-gif/">
            <img class="wp-image-104" src="/uploads/animated-thumb.gif"
              data-jluxe-full-src="https://shop.test/uploads/animated-resized.gif"
              data-jluxe-media-url="https://shop.test/uploads/animated-original.gif"
              data-jluxe-attachment-url="https://shop.test/attachment/animated-gif/"
              data-jluxe-full-srcset="https://shop.test/uploads/static-first-frame.gif 800w" alt="تصویر متحرک">
          </a>
        </div>
        <p><a id="custom-page-link" href="#related-product"><img src="/uploads/related.jpg" alt="محصول مرتبط"></a></p>
      </div>
    </main></body></html>`,
    {
      url: "https://shop.test/product/gallery/",
      pretendToBeVisual: true,
      runScripts: "outside-only",
    },
  );
  dom.window.eval(galleryScript);
  // The production script runs deferred after the product markup is parsed.
  dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded", { bubbles: true }));
  return dom;
}

function click(node, window) {
  const event = new window.MouseEvent("click", { bubbles: true, cancelable: true });
  node.dispatchEvent(event);
  return event;
}

test("R321 attachment-page and media-file gallery links open grouped full-size slides without navigating", async () => {
  const dom = setup();
  const { document, KeyboardEvent } = dom.window;
  const attachmentLink = document.getElementById("attachment-link");
  const mediaLink = document.getElementById("media-link");
  const wpGallery = document.querySelector(".wp-block-gallery");
  const galleryModalId = wpGallery.getAttribute("data-jluxe-gallery-modal-id");
  const modal = document.getElementById(galleryModalId);
  assert.ok(modal, "each linked WordPress gallery receives its own dialog");
  assert.equal(modal.parentElement, document.body, "the lightbox is outside product/form stacking contexts");
  assert.equal(modal.getAttribute("role"), "dialog");
  assert.equal(modal.getAttribute("aria-modal"), "true");
  assert.equal(attachmentLink.getAttribute("href"), "https://shop.test/attachment/front-view/", "the stored attachment-page link is not rewritten");
  assert.equal(attachmentLink.getAttribute("aria-haspopup"), "dialog");
  assert.equal(attachmentLink.getAttribute("aria-controls"), galleryModalId);
  assert.equal(attachmentLink.getAttribute("data-jluxe-gallery-open"), "0");
  assert.equal(mediaLink.getAttribute("data-jluxe-gallery-open"), "1");
  assert.equal(document.querySelectorAll("[data-jluxe-gallery-modal]").length, 4, "the main product gallery and three description galleries have separate dialogs");

  attachmentLink.focus();
  const attachmentClick = click(attachmentLink, dom.window);
  assert.equal(attachmentClick.defaultPrevented, true, "the attachment permalink does not navigate to a new page");
  assert.equal(modal.getAttribute("aria-hidden"), "false");
  assert.equal(document.body.style.overflow, "hidden");
  const slides = [...modal.querySelectorAll("[data-jluxe-gallery-modal-image]")];
  assert.equal(slides.length, 2);
  assert.equal(slides[0].getAttribute("src"), "https://shop.test/uploads/front-full.jpg", "the selected slide uses the original full image URL");
  assert.equal(slides[0].getAttribute("srcset"), "https://shop.test/uploads/front-800.jpg 800w, https://shop.test/uploads/front-full.jpg 1600w");
  assert.equal(slides[0].getAttribute("sizes"), "90vw");
  assert.equal(slides[1].hasAttribute("src"), false, "unselected images remain lazy-loaded");
  assert.equal(modal.querySelector("[data-jluxe-gallery-modal-counter]").textContent, "۱ / ۲");
  assert.equal(document.activeElement, modal.querySelector("[data-jluxe-gallery-modal-close]"), "focus moves to the close control");

  click(modal.querySelector("[data-jluxe-gallery-modal-next]"), dom.window);
  assert.equal(modal.getAttribute("data-jluxe-gallery-modal-current"), "1");
  assert.equal(slides[1].getAttribute("src"), "https://shop.test/uploads/back-full.jpg");
  assert.equal(modal.querySelector("[data-jluxe-gallery-modal-counter]").textContent, "۲ / ۲");
  document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
  assert.equal(modal.getAttribute("aria-hidden"), "true", "Escape closes the gallery");
  assert.equal(document.body.style.overflow, "auto", "closing restores the previous page scroll state");
  await new Promise((resolve) => setTimeout(resolve, 260));
  assert.equal(document.activeElement, attachmentLink, "closing returns focus to the thumbnail that opened the dialog");

  const mediaClick = click(mediaLink, dom.window);
  const mediaModal = document.getElementById(mediaLink.closest("[data-jluxe-gallery]").getAttribute("data-jluxe-gallery-modal-id"));
  assert.equal(mediaClick.defaultPrevented, true, "a direct media-file link also stays in the current page");
  assert.equal(mediaModal.getAttribute("aria-hidden"), "false");
  assert.equal(mediaModal.querySelector('[data-jluxe-gallery-modal-image][aria-hidden="false"]').getAttribute("src"), "https://shop.test/uploads/back-full.jpg");
  click(mediaModal, dom.window);
  assert.equal(mediaModal.getAttribute("aria-hidden"), "true", "clicking the backdrop closes the lightbox");

  const separateLink = document.getElementById("short-description-media");
  const separateClick = click(separateLink, dom.window);
  const separateModal = document.getElementById(separateLink.closest("[data-jluxe-gallery]").getAttribute("data-jluxe-gallery-modal-id"));
  assert.equal(separateClick.defaultPrevented, true);
  assert.equal(separateModal.querySelectorAll("[data-jluxe-gallery-modal-image]").length, 1);
  assert.equal(separateModal.querySelector("[data-jluxe-gallery-modal-prev]"), null, "a one-image gallery does not show useless navigation buttons");
  click(separateModal.querySelector("[data-jluxe-gallery-modal-close]"), dom.window);
  const customClick = click(document.getElementById("custom-page-link"), dom.window);
  assert.equal(customClick.defaultPrevented, false, "custom page links in the description keep their normal navigation");

  const gifLink = document.getElementById("animated-gif-link");
  const gifClick = click(gifLink, dom.window);
  const gifModal = document.getElementById(gifLink.closest("[data-jluxe-gallery]").getAttribute("data-jluxe-gallery-modal-id"));
  const gifSlide = gifModal.querySelector('[data-jluxe-gallery-modal-image][aria-hidden="false"]');
  assert.equal(gifClick.defaultPrevented, true, "animated GIF attachment pages also open in place");
  assert.equal(gifSlide.getAttribute("src"), "https://shop.test/uploads/animated-original.gif", "the original GIF, not a resized derivative, is displayed");
  assert.equal(gifSlide.hasAttribute("srcset"), false, "a potentially flattened GIF srcset is never used");
  click(gifModal.querySelector("[data-jluxe-gallery-modal-close]"), dom.window);
  await new Promise((resolve) => setTimeout(resolve, 260));

  document.dispatchEvent(new dom.window.Event("DOMContentLoaded", { bubbles: true }));
  const mainGalleryOpener = document.getElementById("main-gallery-opener");
  const mainGalleryClick = click(mainGalleryOpener, dom.window);
  const mainGalleryModal = document.getElementById("jluxe-product-gallery-modal");
  assert.equal(mainGalleryClick.defaultPrevented, false, "the main product thumbnail remains a button, not a navigable link");
  assert.equal(mainGalleryModal.getAttribute("aria-hidden"), "false", "the main product gallery still resolves its own modal after description dialogs are added");
  click(mainGalleryModal.querySelector("[data-jluxe-gallery-modal-close]"), dom.window);
  await new Promise((resolve) => setTimeout(resolve, 260));
  assert.equal(document.querySelectorAll("[data-jluxe-gallery-modal]").length, 4, "repeated DOM-ready notifications do not duplicate dialogs");
  dom.window.close();
});

test("R321 classic/reference main galleries keep their own modal target alongside description dialogs", () => {
  for (const [name, template] of [["classic", classicTemplate], ["reference", referenceTemplate]]) {
    assert.match(template, /class="cp3-gallery"[^>]*data-jluxe-gallery-modal-id="jluxe-product-gallery-modal"/, `${name} main gallery declares its modal target`);
    assert.match(template, /id="jluxe-product-gallery-modal" class="jluxe-cp3-gallery-modal[^"]*" data-jluxe-gallery-modal/, `${name} main modal keeps the matching id`);
  }
});

test("R321 description lightbox has mobile-sized controls, an old-browser viewport fallback, and reduced-motion styling", () => {
  const start = css.indexOf("/* R321 — lightbox گالری‌های پیوندشده");
  const end = css.indexOf("/* ── R66:", start);
  assert.ok(start >= 0 && end > start, "the R321 modal styles are present");
  const rules = css.slice(start, end);
  assert.match(rules, /body\.single-product \.jluxe-description-gallery-modal\s*\{[^}]*position:\s*fixed;/);
  assert.match(rules, /width:\s*44px;[\s\S]*?height:\s*44px;/, "close and navigation targets meet mobile touch-size guidance");
  assert.match(rules, /height:\s*82vh;[\s\S]*?@media \(max-width:\s*767px\)[\s\S]*?height:\s*78vh;/, "the viewport sizing has a vh fallback for older mobile Safari");
  assert.match(rules, /@media \(prefers-reduced-motion:\s*reduce\)/);
  assert.match(rules, /:focus\s*\{[^}]*outline:/, "keyboard users receive a visible focus indicator");
});
