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
  assert.match(read("src/styles/storefront.css"), /R404 product cards/);
});

test("R404 inverse section actions stay white-glass and readable over brand bands", () => {
  const products = read("inc/homepage/sections-products.php");
  const css = read("src/styles/route-home.css");
  assert.match(products, /\$inverse \? ' jluxe-home-section-view-all--inverse' : ''/);
  assert.match(products, /class="jluxe-home-section-view-all<\?php echo esc_attr\( \$inverse_class \); \?>/);
  const inverseRule = css.match(/\.jluxe-home-section-heading \.jluxe-home-section-view-all--inverse\s*\{([^}]+)\}/)?.[1] ?? "";
  assert.match(inverseRule, /background:\s*rgb\(255 255 255 \/ .16\)/);
  assert.match(inverseRule, /color:\s*#fff/);
  assert.doesNotMatch(inverseRule, /background:\s*hsl\(var\(--surface\)\)/);
  assert.match(css, /\.jluxe-home-section-heading \.jluxe-home-section-view-all--inverse:hover[\s\S]*background:\s*rgb\(255 255 255 \/ .26\)/);
});

test("R404 renders the supported soft surface wrapper without requiring design_enabled", () => {
  const homepage = read("inc/theme-settings-homepage.php");
  const css = read("src/styles/route-home.css");
  assert.match(homepage, /if \( empty\( \$section\['design_enabled'\] \) \)[\s\S]*'transparent' === \$d\['surface_tone'\][\s\S]*jluxe-home-tone-wrapper/);
  assert.match(homepage, /class="jluxe-designed-section jluxe-home-tone-wrapper" data-surface-tone=/);
  assert.match(css, /\.jluxe-designed-section\[data-surface-tone="soft"\][\s\S]*background:\s*color-mix\(in srgb, hsl\(var\(--background\)\) 93%, hsl\(var\(--foreground\)\) 7%\)/);
});

test("R404 scopes generic order-pay method frames away from custom payment cards", () => {
  const css = read("src/styles/route-cart-checkout.css");
  assert.match(css, /body\.woocommerce-order-pay #payment ul\.payment_methods > li:not\(\.jluxe-payment-method-item\)\s*\{/);
  assert.match(css, /body\.woocommerce-order-pay #payment ul\.payment_methods > li:not\(\.jluxe-payment-method-item\) > label\s*\{/);
});

test("R404 real homepage renderers and product cards expose the requested presentation hooks", () => {
  const product = read("woocommerce/content-product.php");
  const sections = read("inc/homepage/sections-products.php");
  const catalog = read("inc/homepage/sections-catalog.php");
  assert.match(product, /wc_product_class\( 'jluxe-product-card group relative flex flex-col/);
  assert.match(product, /border border-border bg-surface transition-all/);
  assert.doesNotMatch(product, /bg-gradient-to-b from-surface to-muted\/40/);
  assert.match(sections, /class="jluxe-home-special-band"/);
  assert.match(catalog, /class="jluxe-home-trust-card/);
});
