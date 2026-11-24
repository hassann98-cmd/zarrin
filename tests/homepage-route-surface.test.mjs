import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const read = (path) => fs.readFileSync(new URL(`../${path}`, import.meta.url), "utf8");

test("R403 homepage CSS is a standalone route entry and only enqueued for home", () => {
  const vite = read("vite.config.js");
  const php = read("functions.php");
  const main = read("src/main.js");
  const checker = read("scripts/check-assets.mjs");
  assert.match(vite, /"route-home":\s*"src\/styles\/route-home\.css"/);
  assert.match(php, /'home'\s*=>\s*array\( 'src\/styles\/route-home\.css', 'jluxe-route-home' \)/);
  assert.ok(php.includes("$route_styles[ $context ][0]"));
  assert.match(main, /assetConfig\.context === "home"[\s\S]*import\("\.\/styles\/route-home\.css"\)/);
  assert.match(checker, /"src\/styles\/route-home\.css"/);
  assert.doesNotMatch(read("src/styles/storefront.css"), /jluxe-home-section-heading/);
});

test("R403 homepage surfaces use constrained tone tokens and the route stylesheet", () => {
  const css = read("src/styles/route-home.css");
  const sanitize = read("inc/theme-settings-sanitize.php");
  const editor = read("inc/homepage/admin-editor.php");
  const renderer = read("inc/theme-settings-homepage.php");
  assert.ok(sanitize.includes("$raw['surface_tone'] ?? '', array( 'transparent', 'soft', 'card', 'tinted' ), true"));
  assert.ok(sanitize.includes("? $raw['surface_tone'] : 'transparent'"));
  assert.match(editor, /'surface_tone'.*'شفاف'.*'ملایم'.*'کارت برجسته'.*'رنگی لطیف'/);
  assert.match(renderer, /data-surface-tone=/);
  for (const tone of ["transparent", "soft", "card", "tinted"]) assert.ok(css.includes(`data-surface-tone="${tone}"`));
});

test("R403 adds three fixed luxury presets and keeps product action markup untouched", () => {
  const presets = read("inc/theme-settings-presets.php");
  for (const id of ["atelier_noir", "pearl_sage", "royal_sapphire"]) assert.ok(presets.includes(`'${id}' => array(`));
  const product = read("woocommerce/content-product.php");
  assert.match(product, /ajax_add_to_cart add_to_cart_button/);
  assert.match(product, /product_type_.*variable/);
  assert.match(read("src/styles/storefront.css"), /R403 product-card refinements/);
});
