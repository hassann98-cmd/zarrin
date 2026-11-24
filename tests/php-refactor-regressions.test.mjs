import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";
import { readWooCommerceSource } from "./helpers/theme-source.mjs";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");

test("refactored modules retain the ABSPATH guard and stable loader order", () => {
  const modules = [
    "inc/homepage/admin-editor.php",
    "inc/homepage/sections-products.php",
    "inc/homepage/sections-media.php",
    "inc/homepage/sections-catalog.php",
    "inc/homepage/hooks.php",
    "inc/woocommerce/pricing-toman.php",
    "inc/woocommerce/product-single.php",
    "inc/woocommerce/checkout-fields.php",
    "inc/woocommerce/catalog-shop.php",
    "inc/woocommerce/admin-product-meta.php",
    "inc/woocommerce/hooks.php",
  ];
  for (const file of modules) {
    assert.match(read(file), /^<\?php\s*defined\( 'ABSPATH' \) \|\| exit;/, file);
  }
  assert.deepEqual(
    [...read("inc/woocommerce.php").matchAll(/require_once __DIR__ \. '([^']+)'/g)].map((match) => match[1]),
    [
      "/woocommerce/pricing-toman.php",
      "/woocommerce/product-single.php",
      "/woocommerce/checkout-fields.php",
      "/woocommerce/catalog-shop.php",
      "/woocommerce/admin-product-meta.php",
      "/woocommerce/hooks.php",
    ],
  );
  const homepageLoader = read("inc/theme-settings-homepage.php");
  assert.doesNotMatch(homepageLoader, /is_admin\s*\(/);
  assert.doesNotMatch(homepageLoader, /function jluxe_render_homepage_page\(/);
  assert.match(homepageLoader, /require_once __DIR__ \. '\/homepage\/admin-editor\.php'/);
  assert.equal([...homepageLoader.matchAll(/^function /gm)].length, 8, "the compatibility loader retains its eight core functions");
  assert.match(read("inc/homepage/admin-editor.php"), /function jluxe_render_homepage_page\(/);
  assert.match(read("inc/homepage/sections-media.php"), /function jluxe_hb_collage_mobile_grid_css\(/);
  assert.match(read("inc/homepage/sections-media.php"), /function jluxe_hero_options\(/);
});

test("Iran city data is loaded from the dedicated JSON resource", () => {
  const checkout = read("inc/woocommerce/checkout-fields.php");
  assert.match(checkout, /dirname\(\s*__DIR__\s*\)\s*\.\s*'\/data\/iran-cities\.json'/);
  assert.match(checkout, /json_decode\(\s*\$json\s*,\s*true\s*\)/);
  const cities = JSON.parse(read("inc/data/iran-cities.json"));
  assert.ok(Array.isArray(cities["تهران"]) && cities["تهران"].length > 0);
  assert.ok(cities["تهران"].includes("آبسرد"));
});

test("custom code styles run after checkout login and builder animation styles", () => {
  const themeSettings = read("inc/theme-settings.php");
  const checkoutLogin = read("inc/checkout-login.php");
  const homepageHooks = read("inc/homepage/hooks.php");
  assert.match(checkoutLogin, /add_action\(\s*'wp_enqueue_scripts'\s*,\s*'jluxe_enqueue_checkout_login_assets'\s*,\s*23\s*\)/);
  assert.match(homepageHooks, /add_action\(\s*'wp_enqueue_scripts'\s*,\s*'jluxe_enqueue_builder_animations'\s*,\s*35\s*\)/);
  assert.match(themeSettings, /add_action\(\s*'wp_enqueue_scripts'\s*,\s*'jluxe_output_custom_code'\s*,\s*40\s*\)/);
  assert.match(readWooCommerceSource(), /function jluxe_enqueue_guided_checkout_assets\(/);
});
