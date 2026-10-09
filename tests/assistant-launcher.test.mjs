import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile(new URL("../src/islands/AiAssistant.js", import.meta.url), "utf8");
const styles = await readFile(new URL("../src/styles/storefront.css", import.meta.url), "utf8");

test("R127 product help launcher remains fixed at its configured corner while clearing only real bottom docks", () => {
  assert.match(source, /position: "fixed"/);
  assert.match(source, /const launcherBottom = Math\.max\(Number\(bottom\) \|\| 0, dockClearance\)/);
  assert.match(source, /#bottom-navigation, \.jluxe-mobile-nav, \[data-jluxe-mobile-price-bar\], \[data-jluxe-sticky-cta\]/);
  assert.doesNotMatch(source, /productClearance|productSideOverride|measureProductCollision|clearanceAboveBuyBox/);
});

test("assistant mobile sheet tracks the keyboard, keeps prompts readable, and uses muted exit states", () => {
  assert.match(source, /const visualViewport = window\.visualViewport/);
  assert.match(source, /--aia-visual-viewport-height/);
  assert.match(source, /if \(!open \|\| closing \|\| isMobile\) return undefined/);
  assert.match(source, /role: "group"/);
  assert.match(source, /assistant\.primaryColor \|\| DEFAULT_ASSISTANT_ACCENT/);
  assert.match(styles, /\.jluxe-ai-quick-replies\s*\{\s*display: grid;/);
  assert.match(styles, /grid-template-columns: repeat\(auto-fit, minmax\(min\(100%, 156px\), 1fr\)\)/);
  assert.match(styles, /\.jluxe-ai-quick-reply\s*\{[^}]*white-space: normal/s);
  assert.match(styles, /\.jluxe-ai-hours-notice\.is-closed\s*\{[^}]*#76563b/s);
  assert.match(styles, /\.jluxe-ai-error\s*\{[^}]*#76563b/s);
  assert.match(styles, /@keyframes jluxe-ai-window-out/);
  assert.match(styles, /@keyframes jluxe-ai-backdrop-out/);
  assert.match(styles, /@media \(prefers-reduced-motion: reduce\)/);
});
