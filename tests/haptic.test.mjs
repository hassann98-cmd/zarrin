import assert from "node:assert/strict";
import fs from "node:fs";
import { fileURLToPath } from "node:url";
import { JSDOM } from "jsdom";
import test from "node:test";

const root = new URL("..", import.meta.url);
const read = (file) => fs.readFileSync(new URL(file, root), "utf8");

test("shared haptic helper vibrates briefly and safely tolerates unsupported or throwing browsers", () => {
  const dom = new JSDOM("<!doctype html><html><body></body></html>", { runScripts: "outside-only" });
  const { window } = dom;
  const calls = [];
  Object.defineProperty(window.navigator, "vibrate", { configurable: true, value: (duration) => calls.push(duration) });
  window.eval(read("assets/js/storefront-utils.js"));
  window.JLuxeStorefrontUtils.haptic();
  assert.deepEqual(calls, [12]);
  Object.defineProperty(window.navigator, "vibrate", { configurable: true, value: () => { throw new Error("unsupported"); } });
  assert.doesNotThrow(() => window.JLuxeStorefrontUtils.haptic());
  Object.defineProperty(window.navigator, "vibrate", { configurable: true, value: undefined });
  assert.doesNotThrow(() => window.JLuxeStorefrontUtils.haptic());
  dom.window.close();
});

test("mobile purchase switch, quantity stepper, gallery long-press and nav chips use haptic feedback", () => {
  const utils = read("assets/js/storefront-utils.js");
  const woo = read("assets/js/woocommerce.js");
  const navPress = read("src/lib/use-nav-press.js");
  const floatingNav = read("src/islands/FloatingMobileNav.js");
  assert.match(utils, /function jluxeHaptic\(\)[\s\S]*navigator\.vibrate\(12\)/);
  assert.match(utils, /haptic:\s*jluxeHaptic/);
  assert.match(woo, /function jluxeHaptic\(\)[\s\S]*window\.JLuxeStorefrontUtils\.haptic\(\)/);
  assert.match(woo, /var button = event\.target\.closest\("\[data-jluxe-qty-step\]"\);[\s\S]*?jluxeHaptic\(\);/);
  assert.match(woo, /var button = event\.target\.closest\("\[data-jluxe-mobile-qty-step\]"\);[\s\S]*?jluxeHaptic\(\);/);
  assert.match(woo, /toggleBtn\.addEventListener\("click", function \(\) \{[\s\S]*?jluxeHaptic\(\);/);
  assert.match(woo, /pressTimer = window\.setTimeout\(function \(\) \{[\s\S]*?jluxeHaptic\(\);[\s\S]*?\}, 480\)/);
  assert.match(navPress, /function jluxeHaptic\(\)[\s\S]*navigator\.vibrate\(12\)/);
  assert.match(navPress, /if \(event\.pointerType !== "mouse"\) jluxeHaptic\(\)/);
  assert.match(floatingNav, /\.\.\.pressProps\(item\.id\)/);
});
