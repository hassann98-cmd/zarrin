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
