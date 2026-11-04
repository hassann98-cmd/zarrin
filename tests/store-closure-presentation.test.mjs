import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);
const [closure, announcement, styles, adminStyles] = await Promise.all([
  readFile(new URL("inc/store-closure.php", root), "utf8"),
  readFile(new URL("inc/announcement-bar.php", root), "utf8"),
  readFile(new URL("src/styles/storefront.css", root), "utf8"),
  readFile(new URL("assets/css/theme-settings-admin.css", root), "utf8"),
]);

test("R356 store closure exposes an independent, validated background-color picker", () => {
  assert.match(closure, /name="store_closure_background_color"/);
  assert.match(closure, /sanitize_hex_color\(\s*\(string\) \$stored\['background_color'\]\s*\)/);
  assert.match(closure, /background_color'\s*=>\s*\$background_color/);
  assert.match(closure, /_jluxe_store_closure_active/);
  assert.match(adminStyles, /\.jluxe-store-closure-field input\[type="color"\]/);
});

test("R356 closure banner renders the store-and-clock SVG only for the closure state", () => {
  assert.match(announcement, /\$is_store_closure\s*=\s*! empty\( \$settings\['_jluxe_store_closure_active'\] \)/);
  assert.match(announcement, /jluxe-announcement-bar--store-closure/);
  assert.match(announcement, /class="jluxe-announcement-bar__closure-icon" aria-hidden="true"/);
  assert.match(announcement, /<circle cx="34" cy="34" r="9\.5"/);
  assert.match(announcement, /تعطیلی موقت فروشگاه؛/);
});

test("R356 icon blinks with a CSS-only fallback and stops for reduced-motion preferences", () => {
  assert.match(styles, /@-webkit-keyframes jluxe-store-closure-icon-blink/);
  assert.match(styles, /@keyframes jluxe-store-closure-icon-blink/);
  assert.match(styles, /jluxe-announcement-bar--store-closure \.jluxe-announcement-bar__closure-icon\s*\{[^}]*animation: jluxe-store-closure-icon-blink 1\.2s ease-in-out infinite/s);
  assert.match(styles, /@media \(max-width: 767\.98px\)[\s\S]*?\.jluxe-announcement-bar__closure-icon\s*\{[^}]*width: 30px/s);
  assert.match(styles, /@media \(prefers-reduced-motion: reduce\)\s*\{[\s\S]*?\.jluxe-announcement-bar--store-closure \.jluxe-announcement-bar__closure-icon\s*\{[^}]*animation: none/s);
});
