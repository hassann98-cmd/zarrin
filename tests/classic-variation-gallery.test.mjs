import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const start = source.indexOf("function jluxeBindClassicVariationGallery(form) {");
const marker = "\n}\n\n/**\n * ردیفِ «تعداد»ی صفحه‌ی محصولِ متغیر";
const end = source.indexOf(marker, start) + 2;
assert.ok(start >= 0 && end > start, "classic variation gallery binder is present");
const galleryBinder = source.slice(start, end);

function setup() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="jluxe-cp3">
        <form class="variations_form">
          <div data-cp3-gallery data-jluxe-gallery-current="0">
            <button type="button" data-cp3-zoom-box>
              <img class="is-loaded" data-cp3-zoom-img src="/parent-large.jpg" srcset="/parent-small.jpg 400w, /parent-large.jpg 900w" sizes="80vw" width="900" height="700" alt="Parent product">
            </button>
            <div class="cp3-thumbs">
              <button type="button" class="cp3-thumb is-active" data-cp3-thumb data-jluxe-gallery-open="0" data-full="/parent-full.jpg" aria-pressed="true"><img src="/parent-thumb.jpg" srcset="/parent-thumb-small.jpg 48w, /parent-thumb.jpg 96w" sizes="96px" alt=""></button>
              <button type="button" class="cp3-thumb" data-cp3-thumb data-jluxe-gallery-open="1" data-full="/gallery-full.jpg" aria-pressed="false"><img src="/gallery-thumb.jpg" alt=""></button>
            </div>
            <div class="reference-mobile-gallery-dots">
              <button type="button" class="reference-mobile-gallery-dot is-active" data-cp3-thumb data-reference-gallery-index="0" aria-pressed="true"></button>
              <button type="button" class="reference-mobile-gallery-dot" data-cp3-thumb data-reference-gallery-index="1" aria-pressed="false"></button>
            </div>
          </div>
          <div data-jluxe-gallery-modal>
            <img data-jluxe-gallery-modal-image src="/parent-modal.jpg" srcset="/parent-modal-small.jpg 600w, /parent-modal.jpg 1200w" sizes="90vw" data-src="/parent-modal.jpg" data-srcset="/parent-modal-small.jpg 600w, /parent-modal.jpg 1200w" data-sizes="90vw" alt="Parent product">
          </div>
        </form>
      </div>
      <div class="product">
        <form id="default-form" class="variations_form">
          <div class="woocommerce-product-gallery"><img id="native-product-image" src="/native-parent.jpg"></div>
        </form>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/variable/", runScripts: "outside-only" },
  );
  const { window } = dom;
  window.jQuery = jquery(window);
  window.eval(galleryBinder);
  const form = window.document.querySelector(".jluxe-cp3 .variations_form");
  window.jluxeBindClassicVariationGallery(form);
  return { dom, window, form, $: window.jQuery };
}

test("classic variation selection updates the main image, first thumbnail, and full-screen slide, then restores them", () => {
  const { dom, window, form, $ } = setup();
  const gallery = window.document.querySelector("[data-cp3-gallery]");
  const main = gallery.querySelector("[data-cp3-zoom-img]");
  const thumbs = [...gallery.querySelectorAll(".cp3-thumb[data-cp3-thumb]")];
  const mobileDots = [...gallery.querySelectorAll(".reference-mobile-gallery-dot")];
  const firstThumbImage = thumbs[0].querySelector("img");
  const modalImage = window.document.querySelector("[data-jluxe-gallery-modal-image]");
  const variation = {
    image: {
      src: "/variation-large.jpg",
      src_w: 1200,
      src_h: 900,
      srcset: "/variation-medium.jpg 768w, /variation-large.jpg 1200w",
      sizes: "(max-width: 767px) 100vw, 50vw",
      full_src: "/variation-full.jpg",
      gallery_thumbnail_src: "/variation-thumb.jpg",
      thumb_src: "/variation-thumb-small.jpg",
      alt: "Selected variation image",
    },
  };

  $(form).trigger("found_variation", [variation]);

  assert.equal(main.getAttribute("src"), "/variation-large.jpg");
  assert.equal(main.getAttribute("srcset"), variation.image.srcset);
  assert.equal(main.getAttribute("sizes"), variation.image.sizes);
  assert.equal(main.getAttribute("width"), "1200");
  assert.equal(main.getAttribute("height"), "900");
  assert.equal(main.getAttribute("alt"), "Selected variation image");
  assert.equal(thumbs[0].getAttribute("data-full"), "/variation-full.jpg");
  assert.equal(firstThumbImage.getAttribute("src"), "/variation-thumb.jpg");
  assert.equal(firstThumbImage.hasAttribute("srcset"), false, "the parent thumbnail srcset cannot override the variation image");
  assert.equal(thumbs[0].classList.contains("is-active"), true);
  assert.equal(thumbs[1].classList.contains("is-active"), false);
  assert.equal(thumbs[0].getAttribute("aria-pressed"), "true");
  assert.equal(thumbs[1].getAttribute("aria-pressed"), "false");
  assert.equal(mobileDots[0].classList.contains("is-active"), true, "the first mobile dot mirrors the gallery index");
  assert.equal(mobileDots[1].classList.contains("is-active"), false);
  assert.equal(mobileDots[0].getAttribute("aria-pressed"), "true");
  assert.equal(mobileDots[1].getAttribute("aria-pressed"), "false");
  assert.equal(gallery.getAttribute("data-jluxe-gallery-current"), "0");
  assert.equal(modalImage.getAttribute("data-src"), "/variation-full.jpg");
  assert.equal(modalImage.getAttribute("data-srcset"), variation.image.srcset);
  assert.equal(modalImage.getAttribute("data-sizes"), variation.image.sizes);
  assert.equal(modalImage.getAttribute("src"), "/variation-full.jpg", "an already-loaded modal image changes immediately");
  assert.equal(modalImage.getAttribute("srcset"), variation.image.srcset);
  assert.equal(modalImage.getAttribute("alt"), "Selected variation image");

  $(form).trigger("reset_data");

  assert.equal(main.getAttribute("src"), "/parent-large.jpg");
  assert.equal(main.getAttribute("srcset"), "/parent-small.jpg 400w, /parent-large.jpg 900w");
  assert.equal(main.getAttribute("sizes"), "80vw");
  assert.equal(main.getAttribute("width"), "900");
  assert.equal(main.getAttribute("height"), "700");
  assert.equal(main.getAttribute("alt"), "Parent product");
  assert.equal(thumbs[0].getAttribute("data-full"), "/parent-full.jpg");
  assert.equal(firstThumbImage.getAttribute("src"), "/parent-thumb.jpg");
  assert.equal(firstThumbImage.getAttribute("srcset"), "/parent-thumb-small.jpg 48w, /parent-thumb.jpg 96w");
  assert.equal(modalImage.getAttribute("data-src"), "/parent-modal.jpg");
  assert.equal(modalImage.getAttribute("data-srcset"), "/parent-modal-small.jpg 600w, /parent-modal.jpg 1200w");
  assert.equal(modalImage.getAttribute("src"), "/parent-modal.jpg");
  assert.equal(modalImage.getAttribute("srcset"), "/parent-modal-small.jpg 600w, /parent-modal.jpg 1200w");
  assert.equal(gallery.getAttribute("data-jluxe-gallery-current"), "0");
  assert.equal(thumbs[0].classList.contains("is-active"), true);
  assert.equal(thumbs[1].classList.contains("is-active"), false);
  assert.equal(mobileDots[0].classList.contains("is-active"), true);
  assert.equal(mobileDots[1].classList.contains("is-active"), false);

  dom.window.close();
});

test("the classic-gallery binder leaves WooCommerce's native/default product gallery alone", () => {
  const { dom, window, $, form } = setup();
  const defaultForm = window.document.getElementById("default-form");
  window.jluxeBindClassicVariationGallery(defaultForm);
  $(defaultForm).trigger("found_variation", [{ image: { src: "/must-not-touch-default.jpg" } }]);
  assert.equal(window.document.getElementById("native-product-image").getAttribute("src"), "/native-parent.jpg");
  assert.equal(form.classList.contains("variations_form"), true);
  dom.window.close();
});
