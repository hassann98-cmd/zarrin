import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);
const homepageSource = await readFile(new URL("inc/theme-settings-homepage.php", root), "utf8");

test("R123 keeps homepage carousel arrows inside the viewport without disabling its inner scroll", () => {
  assert.match(homepageSource, /\.jluxe-category-grid-ref\{box-sizing:border-box;width:calc\(100% - 48px\);/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-scroll\{[^}]*overflow-x:auto/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\.is-prev\{left:-24px\}/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\.is-next\{right:-24px\}/);
  assert.match(homepageSource, /@media\(max-width:767px\)\{\.jluxe-category-grid-ref\{[^}]*width:100%/);
  assert.match(homepageSource, /\.jluxe-category-grid-ref-arrow\{display:none\}/);
});
