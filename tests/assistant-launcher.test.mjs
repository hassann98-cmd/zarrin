import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile(new URL("../src/islands/AiAssistant.js", import.meta.url), "utf8");

test("R127 product help launcher remains fixed at its configured corner while clearing only real bottom docks", () => {
  assert.match(source, /position: "fixed"/);
  assert.match(source, /const launcherBottom = Math\.max\(Number\(bottom\) \|\| 0, dockClearance\)/);
  assert.match(source, /#bottom-navigation, \.jluxe-mobile-nav, \[data-jluxe-mobile-price-bar\], \[data-jluxe-sticky-cta\]/);
  assert.doesNotMatch(source, /productClearance|productSideOverride|measureProductCollision|clearanceAboveBuyBox/);
});
