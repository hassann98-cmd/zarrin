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
    "src/styles/variant-canva-home.css",
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
  const [main, authNavigation, aiIsland, authIsland] = await Promise.all([
    fs.readFile(path.join(root, "src/main.js"), "utf8"),
    fs.readFile(path.join(root, "src/lib/auth-navigation.js"), "utf8"),
    fs.readFile(path.join(root, "src/islands/AiAssistant.js"), "utf8"),
    fs.readFile(path.join(root, "src/islands/AuthPage.jsx"), "utf8"),
  ]);
  assert.doesNotMatch(main, /import\s+["']\.\/styles\/(?:design-variants|neumorphism-refinements)\.css["']/);
  assert.match(main, /import\.meta\.env\.DEV/);
  assert.match(main, /assetConfig\.context === "product"/);
  assert.match(main, /assetConfig\.context === "cart" \|\| assetConfig\.context === "checkout"/);
  assert.match(authNavigation, /import\("\.\.\/islands\/AuthPage\.jsx"\)/);
  assert.doesNotMatch(authNavigation, /import\("\.\.\/styles\/route-account-auth\.css"\)/);
  assert.match(authIsland, /^import\s+.*auth-page\.css/);
  assert.match(aiIsland, /import\s+["']\.\.\/styles\/ai-assistant\.css["']/);
});


test("AuthPage CSS is emitted as a dynamic-import dependency and enqueued from its manifest entry", async () => {
  const auth = manifest["src/islands/AuthPage.jsx"];
  assert.ok(auth?.css?.length, "AuthPage must own non-empty CSS in the manifest");
  const cssFile = auth.css[0];
  const mainFile = manifest["src/main.js"]?.file;
  assert.ok(mainFile, "main bundle must be present");
  const bundle = await fs.readFile(path.join(root, "assets/compiled", mainFile), "utf8");
  assert.match(bundle, /__vite__mapDeps/);
  assert.ok(bundle.includes(path.basename(cssFile)), "main bundle dependency map must contain AuthPage CSS");
  assert.match(bundle, /import\(`(?:\.\.\/|\.\/)?AuthPage-[^`]+\.js`\),__vite__mapDeps\(\[[^\]]+\]\)/);
  const authCss = await fs.readFile(path.join(root, "assets/compiled", cssFile), "utf8");
  assert.match(authCss, /\.jluxe-auth-screen/);
});

test("mobile price bar leaves the page clickable and only its active card accepts pointer input", async () => {
  const [product, storefront] = await Promise.all([
    fs.readFile(path.join(root, "src/styles/route-product.css"), "utf8"),
    fs.readFile(path.join(root, "src/styles/storefront.css"), "utf8"),
  ]);
  assert.match(product, /\.jluxe-mobile-price-bar\s*\{\s*pointer-events:\s*none/);
  assert.match(product, /\.jluxe-mobile-price-card\s*\{\s*pointer-events:\s*auto/);
  assert.match(product, /data-jluxe-mobile-bar-mode="inactive"\] \.jluxe-mobile-price-card\s*\{\s*pointer-events:\s*none/);
  assert.doesNotMatch(storefront, /\.jluxe-mobile-bar-toggle/);
});
