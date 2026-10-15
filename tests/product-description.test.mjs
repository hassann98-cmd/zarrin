import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const woocommerce = fs.readFileSync(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
const productTemplates = [
  "../woocommerce/content-single-product.php",
  "../woocommerce/content-single-product-classic.php",
  "../woocommerce/content-single-product-reference.php",
].map((file) => fs.readFileSync(new URL(file, import.meta.url), "utf8"));
const r318Css = css.split("/* R318 —")[1]?.split("/* ── R66:")[0] ?? "";

test("R318 description typography and media styling is limited to single product layouts", () => {
  assert.ok(r318Css, "the R318 scoped stylesheet block exists");
  for (const selector of [
    "body.single-product .jluxe-product-description",
    "body.single-product [data-jluxe-short-desc-text]",
    "body.single-product .jluxe-cp3 .cp3-descbody",
    "body.single-product .jluxe-cp3 .cp3-short-body",
  ]) {
    assert.ok(r318Css.includes(selector), `the product-only styles cover ${selector}`);
  }
  assert.match(r318Css, /border-radius:\s*16px;/, "product description images get rounded corners without being cropped");
  assert.match(r318Css, /max-width:\s*100%;[\s\S]*?height:\s*auto;/, "images stay within their content column");
});

test("R318 tables use accessible horizontal scrolling and readable striped rows", () => {
  assert.match(r318Css, /\.jluxe-description-table-scroll\s*\{[\s\S]*?overflow-x:\s*auto;/);
  assert.match(r318Css, /-webkit-overflow-scrolling:\s*touch;/, "older iOS gets momentum scrolling");
  assert.match(r318Css, /nth-child\(even\)/, "alternating table rows are visually distinct");
  assert.match(r318Css, /thead th[\s\S]*?background:\s*hsl\(var\(--primary\)\)/, "table headings use the theme's high-contrast primary color");
  assert.match(woocommerce, /role="region" tabindex="0" aria-label="جدول توضیحات محصول/);
  assert.match(woocommerce, /add_filter\( 'the_content', 'jluxe_wrap_single_product_description_tables', 99 \)/);
  assert.match(woocommerce, /! function_exists\( 'is_product' \) \|\| ! is_product\(\)/, "long-description wrapping is skipped outside product pages");
});

test("R318 short descriptions get the same responsive table wrapper in all three templates", () => {
  assert.equal(productTemplates.length, 3);
  for (const [index, template] of productTemplates.entries()) {
    assert.match(
      template,
      /jluxe_wrap_product_description_tables\(\s*wp_kses_post\(\s*wpautop\(/,
      `product template ${index + 1} wraps sanitized short-description tables`,
    );
  }
});

test("R319 WordPress block and shortcode galleries use polished cards with visible captions", () => {
  const r319Css = css.split("/* R319 —")[1]?.split("/* ── R66:")[0] ?? "";
  assert.ok(r319Css, "the R319 gallery stylesheet block exists");
  for (const selector of ["body.single-product", ".jluxe-product-description-content", ".wp-block-gallery", ".blocks-gallery-grid", ".gallery > .gallery-item"]) {
    assert.ok(r319Css.includes(selector), `gallery styling covers ${selector} in product descriptions only`);
  }
  assert.match(r319Css, /box-sizing:\s*border-box;/, "gallery images and captions stay inside their cards");
  assert.match(r319Css, /height:\s*auto;/, "gallery images retain their full intrinsic proportions");
  assert.match(r319Css, /object-fit:\s*contain;/, "gallery images use the non-cropped contain fit");
  assert.match(r319Css, /\.gallery-caption/);
  assert.match(r319Css, /\.wp-element-caption/);
  assert.match(r319Css, /position:\s*static;/, "block captions stay readable below the image instead of overlaying it");
  assert.match(r319Css, /@media \(max-width: 600px\)/, "gallery cards adapt to narrow mobile screens");
  const mobileGalleryCss = r319Css.split("@media (max-width: 600px)")[1] ?? "";
  assert.match(mobileGalleryCss, /gallery-columns-4[\s\S]*?flex-basis:\s*calc\(50% - 12px\)/, "four-column galleries become readable two-column layouts on phones");

  for (const [index, template] of productTemplates.entries()) {
    assert.match(template, /jluxe-product-description-content/, `template ${index + 1} exposes the shared scoped description class`);
  }
});
