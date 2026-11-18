import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (rel) => fs.readFileSync(path.join(root, rel), "utf8");

test("checkout login card replaces WooCommerce default form only when OTP login is forced", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_checkout_login_card_visible/);
  assert.match(php, /is_registration_required\(\)/);
  assert.match(php, /jluxe_otp_available\(\)/);
  assert.match(php, /woocommerce_checkout_login_form/);
  assert.match(php, /redirect_to/);
  assert.match(php, /wc_get_page_permalink\( 'myaccount' \)/);
  assert.match(php, /wc_get_checkout_url\(\)/);
  // هرگز به‌عنوان درگاه ووکامرس رجیستر نمی‌شود.
  assert.doesNotMatch(php, /woocommerce_payment_gateways/);
});

test("address selector renders saved addresses with a new-address option", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_render_address_selector/);
  assert.match(php, /WC_Customer/);
  assert.match(php, /get_billing_address_1/);
  assert.match(php, /get_shipping_address_1/);
  assert.match(php, /data-jluxe-address-selector/);
  assert.match(php, /آدرس جدید/);
  assert.match(php, /jluxe_saved_address/);
});

test("address selector is wired into both classic and guided layouts", () => {
  const classic = read("woocommerce/checkout/form-checkout.php");
  const guided = read("woocommerce/checkout/form-checkout-guided.php");
  assert.match(classic, /jluxe_render_address_selector\(\)/);
  assert.match(guided, /jluxe_render_address_selector\(\)/);
  const functions = read("functions.php");
  assert.match(functions, /inc\/checkout-login\.php/);
});

test("checkout login JS fills the form from a saved address and clears it for a new one", () => {
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /data-jluxe-address-selector/);
  assert.match(js, /billing_first_name|_first_name/);
  assert.match(js, /ship-to-different-address-checkbox/);
  assert.match(js, /JSON\.parse/);
  assert.match(js, /is-selected/);
});

test("checkout login CSS matches the theme design tokens", () => {
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /hsl\(var\(--primary\)\)/);
  assert.match(css, /border-radius: 1\.375rem/);
  assert.match(css, /prefers-reduced-motion/);
  assert.match(css, /jluxe-checkout-login__button/);
  assert.match(css, /jluxe-checkout-addresses__card/);
});
