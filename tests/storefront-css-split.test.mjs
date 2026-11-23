import assert from "node:assert/strict";
import fs from "node:fs/promises";
import path from "node:path";
import test from "node:test";

const root = process.cwd();
const manifest = JSON.parse(
  await fs.readFile(path.join(root, "assets/compiled/manifest.json"), "utf8"),
);

async function compiledCss(entry) {
  const file = manifest[entry]?.file;
  assert.ok(file, `Missing CSS entry in manifest: ${entry}`);
  return fs.readFile(path.join(root, "assets/compiled", file), "utf8");
}

async function mainCss() {
  const files = manifest["src/main.js"]?.css ?? [];
  assert.ok(files.length, "main.js must retain its shared stylesheet");
  return (
    await Promise.all(
      files.map((file) => fs.readFile(path.join(root, "assets/compiled", file), "utf8")),
    )
  ).join("\n");
}

test("route and design styles are standalone manifest CSS entries; AI CSS stays on the lazy island", async () => {
  for (const entry of [
    "src/styles/design-variants.css",
    "src/styles/neumorphism-refinements.css",
    "src/styles/route-product.css",
    "src/styles/route-cart-checkout.css",
    "src/styles/route-account-auth.css",
  ]) {
    assert.equal(manifest[entry]?.isEntry, true, `${entry} should be a standalone CSS entry`);
    assert.ok(await compiledCss(entry));
  }

  const assistantCssFiles = manifest["src/islands/AiAssistant.js"]?.css ?? [];
  assert.ok(assistantCssFiles.length, "AI assistant CSS must follow its dynamic island chunk");
  const assistantCss = (
    await Promise.all(
      assistantCssFiles.map((file) => fs.readFile(path.join(root, "assets/compiled", file), "utf8")),
    )
  ).join("\n");
  assert.match(assistantCss, /\.jluxe-ai-root/);
  assert.doesNotMatch(await mainCss(), /\.jluxe-ai-root/);
});

test("route styles are kept out of the shared stylesheet while the homepage and product cards remain styled", async () => {
  const shared = await mainCss();
  const product = await compiledCss("src/styles/route-product.css");
  const commerce = await compiledCss("src/styles/route-cart-checkout.css");
  const account = await compiledCss("src/styles/route-account-auth.css");

  for (const selector of [
    ".jluxe-home-blog-card",
    ".jluxe-home-section",
    ".jluxe-product-card-title",
    ".jluxe-product-card-current-price",
    ".jluxe-product-card-cta",
  ]) {
    assert.ok(shared.includes(selector), `Shared main CSS lost required homepage/card selector ${selector}`);
  }
  assert.match(product, /body\.single-product/);
  assert.match(product, /\.jluxe-sticky-cta/);
  assert.match(commerce, /body\.woocommerce-checkout/);
  assert.match(commerce, /body\.woocommerce-cart/);
  assert.match(account, /body\.woocommerce-account/);
  assert.doesNotMatch(shared, /body\.single-product/);
  assert.doesNotMatch(shared, /body\.woocommerce-(?:cart|checkout|account)/);
});

test("source only loads visual variants and route CSS in context, and preserves the Vite development path", async () => {
  const [main, authNavigation, aiIsland] = await Promise.all([
    fs.readFile(path.join(root, "src/main.js"), "utf8"),
    fs.readFile(path.join(root, "src/lib/auth-navigation.js"), "utf8"),
    fs.readFile(path.join(root, "src/islands/AiAssistant.js"), "utf8"),
  ]);
  assert.doesNotMatch(main, /import\s+["']\.\/styles\/(?:design-variants|neumorphism-refinements)\.css["']/);
  assert.match(main, /import\.meta\.env\.DEV/);
  assert.match(main, /assetConfig\.context === "product"/);
  assert.match(main, /assetConfig\.context === "cart" \|\| assetConfig\.context === "checkout"/);
  assert.match(authNavigation, /import\("\.\.\/styles\/route-account-auth\.css"\)/);
  assert.match(aiIsland, /import\s+["']\.\.\/styles\/ai-assistant\.css["']/);
});
