import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const wooSource = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = wooSource.indexOf("Keep the localized choice beside every variation attribute title.");
const start = wooSource.indexOf("(function () {", marker);
const end = wooSource.indexOf("\n})();", start);
assert.ok(marker >= 0 && start > marker && end > start, "the shared variation-label listener is present and isolated");
const labelScript = wooSource.slice(start, end + "\n})();".length);

const templateFiles = [
  ["classic", new URL("../woocommerce/content-single-product-classic.php", import.meta.url)],
  ["reference", new URL("../woocommerce/content-single-product-reference.php", import.meta.url)],
];

test("variation labels show localized option text for every attribute and never expose internal slugs", (t) => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <form class="variations_form">
      <div data-jluxe-variation-group="pa_rang">
        <p>انتخاب رنگ: <span data-jluxe-variation-selected aria-live="polite" aria-atomic="true">wrong initial text</span></p>
        <select name="attribute_pa_rang">
          <option value="">انتخاب رنگ:</option>
          <option value="brown" selected>قهوه‌ای</option>
          <option value="light-teal">آبی فیروزه‌ای روشن</option>
        </select>
      </div>
      <div data-jluxe-variation-group="pa_size">
        <p class="cp3-varlabel">انتخاب سایز: <span data-jluxe-variation-selected aria-live="polite" aria-atomic="true">wrong initial text</span></p>
        <div class="cp3-pills" data-cp3-pills="pa_size">
          <button type="button" class="cp3-pill" data-value="small">کوچک</button>
          <button type="button" class="cp3-pill is-active" data-value="medium">متوسط</button>
        </div>
        <select name="attribute_pa_size" data-cp3-select="pa_size">
          <option value="">انتخاب سایز:</option>
          <option value="small">کوچک</option>
          <option value="medium" selected>متوسط</option>
        </select>
      </div>
    </form>
  </body></html>`, { runScripts: "outside-only" });
  t.after(() => dom.window.close());
  const { window } = dom;
  window.eval(labelScript);

  const colorSelect = window.document.querySelector('select[name="attribute_pa_rang"]');
  const sizeSelect = window.document.querySelector('select[name="attribute_pa_size"]');
  const colorLabel = window.document.querySelector('[data-jluxe-variation-group="pa_rang"] [data-jluxe-variation-selected]');
  const sizeLabel = window.document.querySelector('[data-jluxe-variation-group="pa_size"] [data-jluxe-variation-selected]');

  assert.equal(colorLabel.textContent, "قهوه‌ای", "initial selection uses the Persian option text, not its value");
  assert.equal(sizeLabel.textContent, "متوسط", "the other selected attribute is initialized independently");

  colorSelect.value = "light-teal";
  colorSelect.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(colorLabel.textContent, "آبی فیروزه‌ای روشن");
  assert.equal(colorSelect.name, "attribute_pa_rang", "the WooCommerce form key is unchanged");
  assert.equal(colorSelect.value, "light-teal", "the internal option value remains the original slug");

  sizeSelect.value = "small";
  sizeSelect.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(sizeLabel.textContent, "کوچک", "a CP3 select change updates only its own Persian display label");
  sizeSelect.value = "";
  sizeSelect.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.equal(sizeLabel.textContent, "", "clearing an attribute removes its stale selected value");
});

test("both CP3 product layouts render a selected-value slot without changing variation keys", () => {
  for (const [name, url] of templateFiles) {
    const template = fs.readFileSync(url, "utf8");
    assert.match(template, /data-jluxe-variation-group="<\?php echo esc_attr\( \$cp3_attr_slug \); \?>"/, `${name} groups each option label with its own select`);
    assert.match(template, /data-jluxe-variation-selected aria-live="polite" aria-atomic="true"/, `${name} displays the accessible localized selection beside the attribute title`);
    assert.match(template, /name="attribute_<\?php echo esc_attr\( \$cp3_attr_slug \); \?>"/, `${name} preserves WooCommerce's existing attribute form key`);
    assert.match(template, /data-cp3-select="<\?php echo esc_attr\( \$cp3_attr_slug \); \?>"/, `${name} keeps the existing CP3 select mapping`);
    assert.match(template, /\.cp3-pill\.is-active\{[^}]*box-shadow:/, `${name} gives the selected pill a subtle shadow`);
  }

  const wooPhp = fs.readFileSync(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
  assert.match(wooPhp, /data-jluxe-variation-selected aria-live="polite" aria-atomic="true"/, "the default swatch layout includes the same selected-value slot for every attribute");
  assert.match(wooPhp, /<\?php if \( \$is_color_attr \) : \?>انتخاب <\?php endif; \?>/, "the existing color title stays intact while non-color titles retain their original wording");
});

test("default variation swatches and selected-value labels use restrained theme-primary styling", () => {
  const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
  assert.match(css, /\[data-jluxe-variation-value\]\[data-active\]\s*\{[^}]*box-shadow:\s*0 0 0 1px hsl\(var\(--primary\) \/ 0\.18\)/s);
  assert.match(css, /\[data-jluxe-variation-selected\]\s*\{[^}]*color:\s*var\(--jluxe-variation-label-color, hsl\(var\(--primary\)\)\);[^}]*font-weight:\s*700;/s);
  const settings = fs.readFileSync(new URL("../inc/theme-settings-render.php", import.meta.url), "utf8");
  assert.match(settings, /name="product_page\[variation_label_color\]"/);
});
