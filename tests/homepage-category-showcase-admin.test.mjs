import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";
import jquery from "jquery";

const source = await readFile(
  new URL("../assets/js/theme-settings-admin.js", import.meta.url),
  "utf8",
);

test("category showcase admin toggles category/image fields and optional frame color", async () => {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <section class="jluxe-hb-section" data-type="category_showcase">
        <select name="homepage[sections][0][image_frame_mode]">
          <option value="none" selected>none</option><option value="color">color</option>
        </select>
        <div data-showcase-frame-mode="color">frame color</div>
        <div class="jluxe-category-showcase-item">
          <select name="homepage[sections][0][items][0][mode]">
            <option value="category" selected>category</option><option value="image">image</option>
          </select>
          <div data-showcase-mode="category">category selector</div>
          <div data-showcase-mode="image">custom link</div>
        </div>
      </section>
    </body></html>`,
    { url: "https://shop.test/wp-admin/", pretendToBeVisual: true, runScripts: "outside-only" },
  );

  const { window } = dom;
  const $ = jquery(window);
  $.fn.wpColorPicker = function () { return this; };
  $.fn.sortable = function () { return this; };
  window.jQuery = $;
  window.$ = $;
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  await new Promise((resolve) => window.setTimeout(resolve, 0));

  const category = window.document.querySelector('[data-showcase-mode="category"]');
  const image = window.document.querySelector('[data-showcase-mode="image"]');
  const frameColor = window.document.querySelector('[data-showcase-frame-mode="color"]');
  const modeSelect = window.document.querySelector('select[name$="[mode]"]');
  const frameSelect = window.document.querySelector('select[name$="[image_frame_mode]"]');

  assert.notEqual(category.style.display, "none");
  assert.equal(image.style.display, "none");
  assert.equal(frameColor.style.display, "none");

  modeSelect.value = "image";
  modeSelect.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(category.style.display, "none");
  assert.notEqual(image.style.display, "none");

  frameSelect.value = "color";
  frameSelect.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.notEqual(frameColor.style.display, "none");

  dom.window.close();
});
