import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import postcss from "postcss";

const theme = readFileSync(new URL("../src/styles/sitewide-design.css", import.meta.url), "utf8");
const refinements = readFileSync(new URL("../src/styles/neumorphism-refinements.css", import.meta.url), "utf8");
const css = `${theme}\n${refinements}`;
const prefix = "body.jluxe-design-neumorphism";
const contrast = (foreground, background) => {
  const luminance = (color) => {
    const channels = color.match(/[\da-f]{2}/gi).map((channel) => parseInt(channel, 16) / 255);
    const linear = channels.map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
  };
  const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
  return (values[0] + 0.05) / (values[1] + 0.05);
};

test("neumorphic theme stylesheets parse and new selectors remain opt-in scoped", () => {
  assert.doesNotThrow(() => postcss.parse(css));
  const root = postcss.parse(refinements);
  root.walkRules((rule) => {
    assert.ok(rule.selectors.every((selector) => selector.includes(prefix)), `unscoped selector: ${rule.selector}`);
  });
});

test("neumorphism palette and sale/action foregrounds meet WCAG AA", () => {
  assert.match(theme, /--neu-bg:\s*#EAF5EE/i);
  assert.match(theme, /--neu-accent:\s*#087F75/i);
  assert.doesNotMatch(theme, /#5146A5|#403681|247 40% 46/);
  for (const color of ["#087F75", "#075E56", "#C04F31"]) {
    assert.ok(contrast(color, "#FFFFFF") >= 4.5, `${color} on white has insufficient contrast`);
  }
});

test("theme addresses each requested surface and preserves no-overlap and accessibility conventions", () => {
  for (const selector of [
    ".jluxe-blog-listing__header", ".jluxe-blog-card", ".jluxe-article-card",
    ".jluxe-home-blog-card", ".single-product .summary", ".woocommerce-cart",
    ".woocommerce-checkout", ".woocommerce-account", "#jluxe-footer-root",
  ]) assert.ok(css.includes(selector), `missing theme coverage: ${selector}`);
  assert.ok(refinements.includes("data-jluxe-header-grid"), "header account control is styled");
  assert.match(refinements, /one raised surface per hierarchy/);
  assert.match(refinements, /@media \(prefers-reduced-motion: reduce\)/);
  assert.match(refinements, /@media \(forced-colors: active\)/);
});
