import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const php = fs.readFileSync(new URL("../inc/mobile-checkout.php", import.meta.url), "utf8");
const js = fs.readFileSync(new URL("../assets/js/mobile-checkout.js", import.meta.url), "utf8");
const css = fs.readFileSync(new URL("../assets/css/mobile-checkout.css", import.meta.url), "utf8");
const page = fs.readFileSync(new URL("../page-mobile-checkout.php", import.meta.url), "utf8");
const cartButton = fs.readFileSync(new URL("../woocommerce/cart/proceed-to-checkout-button.php", import.meta.url), "utf8");
const payment = fs.readFileSync(new URL("../woocommerce/checkout/payment.php", import.meta.url), "utf8");
const classicForm = fs.readFileSync(new URL("../woocommerce/checkout/form-checkout.php", import.meta.url), "utf8");

test("mobile checkout is an independent flow beside the main checkout", () => {
  assert.match(php, /add_shortcode\( 'jluxe_mobile_checkout'/);
  assert.match(php, /woocommerce_proceed_to_checkout/);
  assert.match(php, /jluxe_mobile_checkout_cart_button/);
  assert.match(page, /\[jluxe_mobile_checkout\]/);
  assert.doesNotMatch(payment, /jluxe_mobile_checkout/);
  assert.doesNotMatch(classicForm, /jluxe_mobile_checkout/);
  assert.match(cartButton, /wc_get_checkout_url\(\)/);
});

test("mobile checkout reads live WooCommerce gateways and never registers a main gateway", () => {
  assert.match(php, /get_available_payment_gateways\(\)/);
  assert.doesNotMatch(php, /woocommerce_payment_gateways/);
  assert.match(js, /ajax\("gateways"\)/);
  assert.match(js, /ajax\("pay"/);
});

test("mobile checkout OTP is configurable and fails closed without SMS", () => {
  assert.match(php, /mobile_checkout_otp_enabled/);
  assert.match(php, /jluxe_otp_available\(\)/);
  assert.match(php, /تأیید موبایل فعال است ولی پیامک تنظیم نشده/);
  assert.match(js, /otp_request/);
  assert.match(js, /otp_verify/);
});

test("mobile checkout uses real shipping, map, and order creation", () => {
  assert.match(php, /get_packages\(\)/);
  assert.match(php, /wc_create_order/);
  assert.match(php, /process_payment/);
  assert.match(php, /jluxe-mobile-checkout/);
  assert.match(js, /jluxe-mobile-checkout__sheet-ov/);
  assert.match(css, /\.jluxe-mobile-checkout__sheet-ov/);
  assert.match(css, /prefers-reduced-motion/);
});
