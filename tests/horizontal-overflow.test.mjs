import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);
const [homepageSource, storefrontStyles, classicProductSource] = await Promise.all([
  readFile(new URL("inc/theme-settings-homepage.php", root), "utf8"),
  readFile(new URL("src/styles/storefront.css", root), "utf8"),
  readFile(new URL("woocommerce/content-single-product-classic.php", root), "utf8"),
]);

test("R123 keeps homepage carousel arrows inside the viewport without disabling its inner scroll", () => {
  assert.match(homepageSource, /\.jluxe-category-grid-ref\{box-sizing:border-box;width:calc\(100% - 48px\);/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-scroll\{[^}]*overflow-x:auto/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\.is-prev\{left:-24px\}/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\.is-next\{right:-24px\}/);
  assert.match(homepageSource, /@media\(max-width:767px\)\{\.jluxe-category-grid-ref\{[^}]*width:100%/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\{display:none\}/);
});

test("R176 clips page-level horizontal overflow while component carousels keep their own scrolling", () => {
  assert.match(storefrontStyles, /html,\s*body\s*\{\s*max-width:\s*100%;\s*\}/);
  assert.match(storefrontStyles, /html\s*\{\s*overflow-x:\s*clip;\s*\}/);
  assert.match(storefrontStyles, /@supports not \(overflow-x:\s*clip\)\s*\{\s*html\s*\{\s*overflow-x:\s*hidden;/);
  assert.match(storefrontStyles, /\.overflow-x-auto\s*\{\s*overflow-x:\s*auto;\s*\}/);
});

test("R176 classic product columns reflow before their fixed desktop widths can overflow", () => {
  assert.match(
    classicProductSource,
    /@media\(min-width:768px\) and \(max-width:1199\.98px\)\{\.jluxe-cp3 \.cp3-grid\{flex-wrap:wrap\}\.jluxe-cp3 \.cp3-gallery\{flex:0 0 42%;width:42%\}\.jluxe-cp3 \.cp3-info\{flex:1 1 58%;min-width:0\}\.jluxe-cp3 \.cp3-side\{position:static;top:auto;width:100%;flex:0 0 100%;margin-top:20px;padding-inline:0;align-self:auto\}\}/,
  );
});

test("R176 modal navigation stays physically LTR, removes the hint, and styles selection by border only", () => {
  assert.match(classicProductSource, /\.jluxe-cp3-gallery-modal__stage\{display:flex;flex-direction:row;direction:ltr;/);
  const previous = classicProductSource.indexOf("data-jluxe-gallery-modal-prev");
  const track = classicProductSource.indexOf("data-jluxe-gallery-modal-track", previous);
  const next = classicProductSource.indexOf("data-jluxe-gallery-modal-next", track);
  assert.ok(previous >= 0 && previous < track && track < next, "DOM order is previous, image, next under the explicit LTR stage");
  assert.doesNotMatch(classicProductSource, /jluxe-cp3-gallery-modal__hint|برای بزرگ‌نمایی دو بار بزنید/);

  const activePillRule = classicProductSource.match(/\.jluxe-cp3 \.cp3-pill\.is-active\{[^}]*\}/)?.[0];
  assert.equal(activePillRule, ".jluxe-cp3 .cp3-pill.is-active{border-color:hsl(var(--primary))}");
});
