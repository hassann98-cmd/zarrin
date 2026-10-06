import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);
const [skeletonPhp, functionsPhp, css] = await Promise.all([
  readFile(new URL("inc/page-skeleton.php", root), "utf8"),
  readFile(new URL("functions.php", root), "utf8"),
  readFile(new URL("src/styles/storefront.css", root), "utf8"),
]);

test("R127 adds page-specific skeletons for home, product, listings, and articles", () => {
  assert.match(skeletonPhp, /'home'\s*=>\s*'home'/);
  assert.match(skeletonPhp, /'product'\s*=>\s*'product'/);
  assert.match(skeletonPhp, /'blog'\s*=>\s*'listing'/);
  assert.match(skeletonPhp, /'singular'\s*=>\s*'article'/);
  assert.match(skeletonPhp, /add_action\( 'wp_body_open', 'jluxe_render_page_skeleton', 0 \)/);
  assert.match(functionsPhp, /require_once JLUXE_THEME_DIR \. '\/inc\/page-skeleton\.php';/);
});

test("R127 skeleton is delayed, non-interactive, and disappears at HTML-ready", () => {
  assert.match(skeletonPhp, /setTimeout\([\s\S]*?180/);
  assert.match(skeletonPhp, /document\.readyState !== "loading"[\s\S]*?finish\(\)/);
  assert.match(skeletonPhp, /window\.addEventListener\("load", finish/);
  assert.match(skeletonPhp, /aria-hidden="true"/);
  assert.match(skeletonPhp, /aria-hidden="true" hidden/);
  assert.match(css, /html\.jluxe-page-skeleton-active \.jluxe-page-skeleton\[hidden\]\s*\{\s*display: block/);
  assert.match(css, /\.jluxe-page-skeleton\s*\{[\s\S]*?pointer-events: none/);
});

test("R127 skeleton shimmer respects reduced motion and does not replace image loading shimmer", () => {
  assert.match(css, /@keyframes jluxe-page-skeleton-shimmer/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.jluxe-page-skeleton__block\s*\{\s*animation: none/);
  assert.match(css, /img:not\(\.jluxe-img-loaded\):not\(\[data-jluxe-no-skeleton\]\)/);
});

test("R160 skeleton follows classic/modern product grids and reserves space for home/history rows", () => {
  assert.match(skeletonPhp, /'product-classic'/);
  assert.match(skeletonPhp, /in_array\(\s*\$layout, array\( 'product', 'product-classic' \)/);
  assert.match(skeletonPhp, /jluxe-page-skeleton__product--classic/);
  assert.match(skeletonPhp, /jluxe-page-skeleton__buybox/);
  assert.match(skeletonPhp, /jluxe-page-skeleton__categories/);
  assert.match(skeletonPhp, /jluxe-page-skeleton__cards--horizontal/);
  assert.match(css, /grid-template-columns: 26rem minmax\(0, 1fr\) 19rem/);
  assert.match(css, /\.jluxe-page-skeleton__product--classic\s*\{\s*grid-template-columns: minmax\(0, 460px\) minmax\(0, 1fr\)/);
  assert.match(css, /--jluxe-container-max:\s*1320px/);
  assert.match(css, /--jluxe-radius-sm:\s*8px[\s\S]*--jluxe-radius-md:\s*12px[\s\S]*--jluxe-radius-lg:\s*16px[\s\S]*--jluxe-radius-xl:\s*24px/);
  assert.match(css, /@media \(max-width: 767\.98px\)[\s\S]*?\.jluxe-page-skeleton__content \{ padding: 18px 12px 28px/);
});
