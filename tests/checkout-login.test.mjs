import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (rel) => fs.readFileSync(path.join(root, rel), "utf8");

test("checkout redirects forced-login guests directly to the theme OTP page", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_checkout_login_redirect_needed/);
  assert.match(php, /template_redirect/);
  assert.match(php, /is_registration_required\(\)/);
  assert.match(php, /jluxe_otp_available\(\)/);
  assert.match(php, /redirect_to/);
  assert.match(php, /wc_get_page_permalink\( 'myaccount' \)/);
  assert.match(php, /wc_get_checkout_url\(\)/);
  // هیچ کارت/دکمهٔ ورودی در تسویه‌حساب نیست.
  assert.doesNotMatch(php, /jluxe_render_checkout_login_card/);
  assert.doesNotMatch(php, /woocommerce_payment_gateways/);
});

test("saved address preview first, new-address accordion below", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_ajax_save_checkout_address/);
  assert.match(php, /wp_ajax_jluxe_save_checkout_address/);
  assert.match(php, /jluxe_save_customer_address/);
  assert.match(php, /update_user_meta/);
  assert.match(php, /data-jluxe-address-toggle/);
  assert.match(php, /data-jluxe-address-close/);
  assert.match(php, /data-jluxe-address-save/);
  assert.match(php, /وارد کردن آدرس جدید/);
  assert.match(php, /افزودن آدرس جدید/);
  assert.match(php, /ذخیره آدرس/);
  // متن راهنما حذف شده.
  assert.doesNotMatch(php, /مشخصات شما از حساب/);
  // لیست آدرس‌های قبلی قبل از فرم آدرس جدید رندر می‌شود.
  const listIdx = php.indexOf("data-jluxe-address-list");
  const formIdx = php.indexOf("data-jluxe-address-form");
  assert.ok(listIdx !== -1 && formIdx !== -1 && listIdx < formIdx, "saved list renders before the new-address form");
});

test("province and city are searchable pickers; identity is pre-filled and editable", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_checkout_provinces/);
  assert.match(php, /jluxe_iran_cities/);
  assert.match(php, /data-jluxe-picker/);
  assert.match(php, /data-jluxe-picker-search/);
  assert.match(php, /data-jluxe-identity-field/);
  assert.match(php, /jluxe_phone/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /buildPicker/);
  assert.match(js, /normalizeFa/);
  assert.match(js, /data-jluxe-identity-field/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-checkout-picker__panel/);
  assert.match(css, /jluxe-checkout-picker__search/);
});

test("address selector renders saved addresses and identity from OTP", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /jluxe_render_address_selector/);
  assert.match(php, /billing_address_1/);
  assert.match(php, /shipping_address_1/);
  assert.match(php, /dsn_addresses/);
  assert.match(php, /jluxe_phone/);
  assert.match(php, /data-jluxe-address-selector/);
});

test("address selector is wired into both classic and guided layouts", () => {
  const classic = read("woocommerce/checkout/form-checkout.php");
  const guided = read("woocommerce/checkout/form-checkout-guided.php");
  assert.match(classic, /jluxe_render_address_selector\(\)/);
  assert.match(guided, /jluxe_render_address_selector\(\)/);
  const functions = read("functions.php");
  assert.match(functions, /inc\/checkout-login\.php/);
});

test("checkout login JS fills the form, toggles the accordion, and saves via AJAX", () => {
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /data-jluxe-address-selector/);
  assert.match(js, /data-jluxe-address-toggle/);
  assert.match(js, /data-jluxe-address-close/);
  assert.match(js, /jluxe_save_checkout_address/);
  assert.match(js, /ship-to-different-address-checkbox/);
  assert.match(js, /JSON\.parse/);
  assert.match(js, /is-selected/);
});

test("checkout login CSS matches the theme design tokens", () => {
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /hsl\(var\(--primary\)\)/);
  assert.match(css, /border-radius: 1\.375rem/);
  assert.match(css, /prefers-reduced-motion/);
  assert.match(css, /jluxe-checkout-addresses__card/);
  assert.match(css, /jluxe-checkout-addresses__form/);
});

test("picker panel respects the hidden attribute (display:flex must not override it)", () => {
  const css = read("assets/css/checkout-login.css");
  // پنل display:flex دارد؛ بدون قاعدهٔ [hidden]، خاصیت hidden مرورگر خنثی
  // می‌شود و پنل همیشه باز می‌ماند (بسته نمی‌شود و دکمهٔ شهر را می‌پوشاند).
  assert.match(css, /jluxe-checkout-picker__panel\[hidden\]\s*\{\s*display:\s*none/);
});
