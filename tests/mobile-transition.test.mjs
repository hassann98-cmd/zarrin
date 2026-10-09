import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const css = await readFile(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const transitionBlock = css.slice(css.lastIndexOf("/* R184: tiny, CSS-only"));

test("R184 cross-page motion is CSS-only, subtle, mobile-scoped, and disabled for reduced motion", () => {
  assert.match(
    transitionBlock,
    /@media\s*\(max-width:\s*767\.98px\)\s*and\s*\(prefers-reduced-motion:\s*no-preference\)/,
  );
  assert.match(transitionBlock, /@view-transition\s*\{\s*navigation:\s*auto;/);
  assert.match(transitionBlock, /::view-transition-old\(root\)/);
  assert.match(transitionBlock, /::view-transition-new\(root\)/);
  assert.match(transitionBlock, /jluxe-mobile-page-in\s+150ms/);
  assert.match(transitionBlock, /jluxe-mobile-page-out\s+110ms/);
  assert.match(transitionBlock, /\.jluxe-soft-nav-enter\s*\{\s*animation:\s*jluxe-mobile-soft-nav-in\s+150ms/);
  assert.match(transitionBlock, /translateY\(3px\)/);
  assert.doesNotMatch(transitionBlock, /<script|document\.startViewTransition/);
});
