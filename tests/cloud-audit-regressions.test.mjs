import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (relativePath) =>
  fs.readFileSync(path.join(root, relativePath), "utf8");

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(entryPath) : [entryPath];
  });
}

test("R-AUDIT 404 copy and search widths are explicit in shipped storefront CSS", () => {
  const template = read("404.php");
  const css = read("src/styles/storefront.css");

  assert.match(template, /class="jluxe-404-copy\b/);
  assert.match(template, /class="jluxe-404-search\b/);
  assert.doesNotMatch(template, /max-w-\[(?:520|440)px\]/);
  assert.match(css, /\.jluxe-404-copy\s*\{[^}]*max-width:\s*520px\s*;/s);
  assert.match(css, /\.jluxe-404-search\s*\{[^}]*width:\s*min\(100%,\s*440px\)\s*;/s);
});

test("R-AUDIT mobile and desktop header search results both have explicit viewport caps", () => {
  const header = read("src/islands/Header.js");
  const css = read("src/styles/storefront.css");

  assert.match(header, /jluxe-header-search-results--desktop/);
  assert.match(header, /jluxe-header-search-results--mobile/);
  assert.doesNotMatch(header, /max-h-\[60vh\]|max-h-\[70vh\]/);
  assert.match(
    css,
    /\.jluxe-header-search-results--desktop\s*\{\s*max-height:\s*70vh\s*;/,
  );
  assert.match(
    css,
    /\.jluxe-header-search-results--mobile\s*\{\s*max-height:\s*60vh\s*;/,
  );
});

test("R-AUDIT React product cards are confined to mock demos; WooCommerce uses configured PHP cards", () => {
  const srcRoot = path.join(root, "src");
  const consumers = walk(srcRoot)
    .filter((file) => /\.jsx?$/.test(file))
    .filter((file) => /from\s+["'][^"']*components\/product-card\.js["']/.test(read(path.relative(root, file))))
    .map((file) => path.relative(root, file).split(path.sep).join("/"))
    .sort();

  assert.deepEqual(consumers, [
    "src/islands/ProductDetails.js",
    "src/islands/ShopArchive.js",
  ]);

  const entry = read("src/main.js");
  assert.match(entry, /"product-details-demo"\s*:\s*\(\)\s*=>\s*import\("\.\/islands\/ProductDetails\.js"\)/);
  assert.match(entry, /"shop-archive-demo"\s*:\s*\(\)\s*=>\s*import\("\.\/islands\/ShopArchive\.js"\)/);

  for (const [templatePath, demoIsland] of [
    ["single-product.php", "product-details-demo"],
    ["archive-product.php", "shop-archive-demo"],
  ]) {
    const template = read(templatePath);
    assert.match(
      template,
      new RegExp(
        `if\\s*\\(\\s*class_exists\\(\\s*'WooCommerce'\\s*\\)\\s*\\)\\s*\\{\\s*woocommerce_content\\(\\);\\s*\\}\\s*else\\s*\\{[\\s\\S]*data-jluxe-island="${demoIsland}"`,
      ),
    );
  }

  const phpCard = read("woocommerce/content-product.php");
  assert.match(phpCard, /jluxe_get_theme_settings\(\)\s*\[\s*'product_card'\s*\]/);
  assert.match(phpCard, /\$jluxe_pc\s*\[\s*'image_ratio'\s*\]/);
  assert.match(phpCard, /\$jluxe_pc\s*\[\s*'radius'\s*\]/);
  assert.match(phpCard, /\$jluxe_pc\s*\[\s*'image_corners'\s*\]/);
});

test("R-AUDIT classic product layout shares the standard desktop horizontal padding", () => {
  const standard = read("woocommerce/content-single-product.php");
  const classic = read("woocommerce/content-single-product-classic.php");

  assert.match(standard, /px-3 md:px-4 py-6/);
  assert.match(classic, /px-3 md:px-4 py-2/);
  assert.doesNotMatch(classic, /md:px-5/);
});
