import assert from "node:assert/strict";
import fs from "node:fs";
import { test } from "node:test";

const read = (file) => fs.readFileSync(new URL(file, import.meta.url), "utf8");

test("MiniCart free-shipping progress is calculated from the matching WooCommerce zone and exposed in the cart snapshot", () => {
  const php = read("../inc/cart-ux.php");
  const miniCart = read("../src/islands/MiniCart.js");
  assert.match(php, /'freeShipping'\s*=>\s*jluxe_get_free_shipping_progress\(\)/);
  assert.match(php, /WC_Shipping_Zones::get_zone_matching_package\(\s*\$package\s*\)/);
  assert.match(php, /'free_shipping' === \$method->id/);
  assert.match(php, /'remaining'\s*=>\s*\$remaining/);
  assert.match(php, /'threshold'\s*=>\s*\$threshold/);
  assert.match(php, /'remainingHtml'\s*=>\s*wc_price\(\s*\$remaining\s*\)/);
  assert.match(miniCart, /s\.freeShipping\.remainingHtml/);
  assert.match(miniCart, /فقط /);
  assert.match(miniCart, /تا ارسال رایگان!/);
  assert.match(miniCart, /s\.freeShipping\.achieved \? "text-success"/);
  assert.match(miniCart, /s\.freeShipping\.achieved \? "bg-success"/);
});

test("MiniCart keeps the free-shipping meter mounted during an in-flight quantity mutation and haptics both steppers", () => {
  const miniCart = read("../src/islands/MiniCart.js");
  assert.match(miniCart, /s\.items\.length > 0 && s\.freeShipping && \(!d \|\| quantityPending\)/);
  assert.equal((miniCart.match(/window\.JLuxeStorefrontUtils\?\.haptic\?\.\(\)/g) || []).length, 3);
});
