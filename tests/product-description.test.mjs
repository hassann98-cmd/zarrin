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
const r322Css = css.split("/* R322 —")[1]?.split("/* ── R66:")[0] ?? "";
const r326Css = css.split("/* R326 —")[1] ?? "";

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

test("R318 tables use accessible horizontal scrolling, readable stripes, and a distinct first/header row", () => {
  assert.match(r318Css, /\.jluxe-description-table-scroll\s*\{[\s\S]*?overflow-x:\s*auto;/);
  assert.match(r318Css, /-webkit-overflow-scrolling:\s*touch;/, "older iOS gets momentum scrolling");
  assert.match(r318Css, /nth-child\(even\)/, "alternating table rows are visually distinct");
  assert.match(r318Css, /thead > tr > td/, "table headers remain distinct when editors output td rather than th");
  assert.match(r318Css, /tbody:first-child > tr:first-child > td/, "the first row is styled even without a thead element");
  assert.match(woocommerce, /role="region" tabindex="0" aria-label="جدول توضیحات محصول/);
  assert.match(woocommerce, /add_filter\( 'the_content', 'jluxe_wrap_single_product_description_tables', 99 \)/);
  assert.match(woocommerce, /! function_exists\( 'is_product' \) \|\| ! is_product\(\)/, "long-description wrapping is skipped outside product pages");
});

test("R322 description tables shrink to their content, center when compact, and use theme-neutral styling", () => {
  assert.ok(r322Css, "the R322 product-description styles exist");
  const wrapperRule = r322Css.match(/body\.single-product \.jluxe-description-table-scroll\s*\{([^}]*)\}/)?.[1] ?? "";
  assert.match(wrapperRule, /width:\s*-moz-fit-content;/, "older Firefox gets an intrinsic-width fallback");
  assert.match(wrapperRule, /width:\s*fit-content;/, "short tables fit their content instead of filling the whole column");
  assert.match(wrapperRule, /margin:\s*1\.1rem auto;/, "compact tables are centered");
  assert.match(r322Css, /width:\s*auto;[\s\S]*?min-width:\s*0;[\s\S]*?table-layout:\s*auto;/, "short tables use their natural content width");
  assert.ok(woocommerce.includes("$table_depth > 0 || preg_match("), "copied inline formatting is removed only inside product tables");
  assert.ok(woocommerce.includes("preg_replace( '/\\s+style\\s*="), "rendering tables strips copied style attributes while preserving their saved content");
  assert.match(r318Css, /overflow-x:\s*auto;[\s\S]*?-webkit-overflow-scrolling:\s*touch;/, "wide tables retain touch-friendly horizontal scrolling");
  assert.match(r322Css, /background-color:\s*hsl\(var\(--muted\)\);/, "the header uses a soft neutral surface");
  assert.match(r322Css, /border-bottom:\s*2px solid hsl\(var\(--border\)\);/, "the header boundary uses the theme border token");
  assert.doesNotMatch(r322Css, /var\(--primary\)/, "table colors do not depend on the changing primary color");
});

test("R326 product-description text links look like configurable, accessible buttons without styling gallery-image links", () => {
  assert.ok(r326Css, "the R326 product-description link styles exist");
  for (const selector of [
    ".jluxe-product-description a[href]:not([data-jluxe-description-gallery-trigger])",
    ".jluxe-product-description-content a[href]:not([data-jluxe-description-gallery-trigger])",
    "[data-jluxe-short-desc-text] a[href]:not([data-jluxe-description-gallery-trigger])",
    ".jluxe-cp3 .cp3-descbody a[href]:not([data-jluxe-description-gallery-trigger])",
    ".jluxe-cp3 .cp3-short-body a[href]:not([data-jluxe-description-gallery-trigger])",
  ]) {
    assert.ok(r326Css.includes(selector), `the button treatment covers ${selector}`);
  }
  assert.match(r326Css, /display:\s*inline-block;/, "links remain inline with surrounding description text");
  assert.match(r326Css, /border:\s*1px solid var\(--jluxe-description-link-border,/, "the outline color is configurable");
  assert.match(r326Css, /background-color:\s*#F2DDD4;[\s\S]*?background-color:\s*var\(--jluxe-description-link-bg,\s*var\(--jluxe-primary-soft,\s*#F2DDD4\)\)/, "older browsers keep a safe pale fallback while modern themes derive the tint from primary");
  assert.match(r326Css, /color:\s*var\(--jluxe-description-link-text,\s*var\(--jluxe-primary-active,\s*#89452A\)\)/, "the text color is configurable with a primary-derived contrast fallback");
  assert.match(r326Css, /text-decoration:\s*none;/, "the button treatment replaces the old underline");
  assert.match(r326Css, /overflow-wrap:\s*anywhere;/, "long Persian link labels can wrap on narrow screens");
  assert.match(r326Css, /:focus\s*\{[^}]*outline:\s*2px solid/, "keyboard focus remains visible");
});

test("R322 gives all six product-description heading levels a readable hierarchy", () => {
  assert.ok(r322Css, "the R322 typography styles exist");
  for (const level of ["h1", "h2", "h3", "h4", "h5", "h6"]) {
    assert.ok(r322Css.includes(`.jluxe-product-description-content ${level}`), `the long description styles ${level}`);
    assert.ok(r322Css.includes(`[data-jluxe-short-desc-text] ${level}`), `the short description styles ${level}`);
  }
  assert.match(r322Css, /font-size:\s*15px;[\s\S]*?line-height:\s*1\.95;/, "desktop body text is slightly larger with generous line spacing");
  assert.match(r322Css, /@media \(max-width:\s*600px\)[\s\S]*?font-size:\s*14px;/, "mobile text remains sized for narrow screens");
  assert.match(r322Css, /color:\s*hsl\(var\(--foreground\)\)/, "heading text uses the neutral readable foreground token");
});

test("R320 description headings use neutral text and consistent heading-to-paragraph spacing", () => {
  assert.match(r318Css, /margin:\s*1\.35rem 0 0\.55rem;/, "headings have a consistent gap before the following text");
  assert.match(r318Css, /margin:\s*0 0 1rem;/, "paragraphs have a readable, predictable vertical rhythm");
  const headingRule = r318Css.match(/body\.single-product \.jluxe-product-description h2,[\s\S]*?body\.single-product \.jluxe-cp3 \.cp3-short-body h4\s*\{([^}]*)\}/)?.[1] ?? "";
  assert.match(headingRule, /color:\s*hsl\(var\(--foreground\)\)/, "heading text remains neutral and readable");
  assert.doesNotMatch(headingRule, /var\(--primary\)/, "decorative accent colors are not applied to description heading text");
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

test("R320 block and shortcode galleries preserve chosen image size and honor all column settings", () => {
  const r320Css = css.split("/* R320 —")[1]?.split("/* ── R66:")[0] ?? "";
  assert.ok(r320Css, "the R320 gallery stylesheet block exists");
  for (const selector of ["body.single-product", ".jluxe-product-description-content", "[data-jluxe-short-desc-text]", ".wp-block-gallery", ".blocks-gallery-grid", ".gallery > .gallery-item"]) {
    assert.ok(r320Css.includes(selector), `gallery styling covers ${selector} only inside product descriptions`);
  }
  assert.match(r320Css, /box-sizing:\s*border-box;/, "gallery images and captions stay inside their cards");
  assert.match(r320Css, /height:\s*auto;/, "gallery images retain their full intrinsic proportions");
  assert.match(r320Css, /object-fit:\s*contain;/, "gallery images never use a crop fit");
  const imageRule = r320Css.match(/body\.single-product \.jluxe-product-description-content \.wp-block-gallery img,[\s\S]*?\{([^}]*)\}/)?.[1] ?? "";
  assert.match(imageRule, /width:\s*auto;/, "WordPress thumbnail/medium/large/full image dimensions are not stretched to fill a card");
  assert.match(imageRule, /max-width:\s*100%;/, "larger image sizes shrink only when their responsive card requires it");
  assert.doesNotMatch(imageRule, /^\s*width:\s*100%;/m, "the gallery does not replace the editor-selected image size");
  assert.match(r320Css, /\.gallery-caption/);
  assert.match(r320Css, /\.wp-element-caption/);
  assert.match(r320Css, /position:\s*static;/, "block captions stay readable below the image instead of overlaying it");
  for (let columns = 1; columns <= 9; columns += 1) {
    assert.ok(r320Css.includes(`.wp-block-gallery.columns-${columns}`), `Gutenberg ${columns}-column settings are honored on wider screens`);
    assert.ok(r320Css.includes(`.gallery-columns-${columns}`), `shortcode ${columns}-column settings are honored on wider screens`);
  }
  assert.match(r320Css, /@media \(min-width: 601px\) and \(max-width: 900px\)/, "tablet galleries avoid overly narrow high-column layouts");
  assert.match(r320Css, /@media \(max-width: 600px\)/, "gallery cards adapt to narrow mobile screens");
  const mobileGalleryCss = r320Css.split("@media (max-width: 600px)")[1] ?? "";
  assert.match(mobileGalleryCss, /gallery-columns-4[\s\S]*?flex-basis:\s*calc\(50% - 12px\)/, "four-column galleries become readable two-column layouts on phones");
  assert.match(mobileGalleryCss, /gallery-columns-1[\s\S]*?flex-basis:\s*calc\(100% - 12px\)/, "one-column galleries remain one column on phones");

  for (const [index, template] of productTemplates.entries()) {
    assert.match(template, /jluxe-product-description-content/, `template ${index + 1} exposes the shared scoped description class`);
  }
});
