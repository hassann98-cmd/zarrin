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

test("province and city are the real WooCommerce fields inside the accordion", () => {
  const php = read("inc/checkout-login.php");
  // فیلدهای واقعی ووکامرس در آکاردئون رندر می‌شوند (نه سلکت جدا).
  assert.match(php, /woocommerce_form_field\( 'billing_state'/);
  assert.match(php, /woocommerce_form_field\( 'billing_city'/);
  assert.match(php, /get_checkout_fields\( 'billing' \)/);
  // سلکت‌های جدا و پیکر سفارشی حذف شده‌اند.
  assert.doesNotMatch(php, /jluxe-new-state/);
  assert.doesNotMatch(php, /jluxe-new-city/);
  assert.doesNotMatch(php, /data-jluxe-picker/);
  assert.doesNotMatch(php, /jluxe-checkout-picker/);
  assert.match(php, /data-jluxe-identity-field/);
  assert.match(php, /jluxe_phone/);
  // فرم صورتحساب استان/شهر را تکرار نمی‌کند.
  const billing = read("woocommerce/checkout/form-billing.php");
  assert.match(billing, /billing_state/);
  assert.match(billing, /billing_city/);
  assert.match(billing, /continue/);
  const js = read("assets/js/checkout-login.js");
  // دیگر نیازی به همگام‌سازی/کپی نیست؛ فیلدها واقعی‌اند و cascade را تم انجام می‌دهد.
  assert.match(js, /billing_state/);
  assert.match(js, /billing_city/);
  assert.match(js, /data-jluxe-identity-field/);
  assert.doesNotMatch(js, /syncStateOptionsFromBilling/);
  assert.doesNotMatch(js, /mirrorBillingCities/);
  assert.doesNotMatch(js, /MutationObserver/);
  assert.doesNotMatch(js, /buildPicker/);
  assert.doesNotMatch(js, /data-jluxe-picker/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-checkout-addresses__fields \.form-row/);
  assert.match(css, /select2-container/);
  assert.doesNotMatch(css, /jluxe-checkout-picker/);
});

test("accordion collects landline (not email) and billing section is hidden for logged-in users", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /تلفن ثابت/);
  assert.match(php, /data-jluxe-new-field="landline"/);
  assert.match(php, /billing_landline/);
  assert.doesNotMatch(php, /jluxe-new-address-email/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /"landline"/);
  assert.match(js, /billing_landline/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /woocommerce-billing-fields/);
  assert.match(css, /logged-in/);
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

test("multiple addresses are stored per customer in a dedicated meta", () => {
  const php = read("inc/checkout-login.php");
  // ووکامرس فقط یک صورتحساب/ارسال نگه می‌دارد؛ لیست چند آدرس در متای اختصاصی است.
  assert.match(php, /jluxe_saved_addresses/);
  assert.match(php, /update_user_meta\( \$user_id, 'jluxe_saved_addresses', \$saved \)/);
  assert.match(php, /get_user_meta\( \$user_id, 'jluxe_saved_addresses', true \)/);
  const js = read("assets/js/checkout-login.js");
  // ajaxUrl هم‌مبدأ با صفحه می‌شود تا mixed-content باعث بلاک fetch نشود.
  assert.match(js, /window\.location\.origin/);
  assert.match(js, /parsed\.protocol = window\.location\.protocol/);
  // اگر fetch در سطح شبکه رد شد، XMLHttpRequest به‌عنوان fallback تلاش می‌کند.
  assert.match(js, /XMLHttpRequest/);
  assert.match(js, /tryXhr/);
});

test("save AJAX verifies nonce with a JSON error and JS surfaces server messages", () => {
  const php = read("inc/checkout-login.php");
  // به‌جای check_ajax_referer (که با «-1» می‌میرد)، خطای JSON واضح برمی‌گردد.
  assert.match(php, /wp_verify_nonce\( \$nonce, 'jluxe_checkout_address' \)/);
  assert.match(php, /نشست شما منقضی شده است/);
  // nonce هم در HTML رندرشده هست تا وابستگی به localize نباشد.
  assert.match(php, /data-jluxe-address-nonce/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /data-jluxe-address-nonce/);
  // پاسخ غیر-JSON دیگر به‌اشتباه «قطع ارتباط» گزارش نمی‌شود.
  assert.match(js, /res\.text\(\)/);
  assert.match(js, /خطای سرور/);
});

test("address list container always renders so a newly saved address is added and selectable", () => {
  const php = read("inc/checkout-login.php");
  // لیست آدرس‌ها نباید پشت شرط «آدرس قبلی وجود دارد» پنهان بماند؛ وگرنه کاربر
  // بار اول بعد از ذخیره، آدرس جدید را در لیست نمی‌بیند.
  assert.doesNotMatch(php, /if \( ! empty\( \$addresses \) \) :\s*\n\s*<div class="jluxe-checkout-addresses__list"/);
  assert.match(php, /data-jluxe-address-list/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-checkout-addresses__list:empty/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /ensureList/);
  assert.match(js, /appendAddress/);
  // انتخاب آدرس ذخیره‌شده استان/شهر را مثل انتخاب واقعی کاربر اعمال می‌کند.
  assert.match(js, /applyStateCity/);
  assert.match(js, /select2:select/);
});

test("checkout login CSS matches the theme design tokens", () => {
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /hsl\(var\(--primary\)\)/);
  assert.match(css, /border-radius: 1\.375rem/);
  assert.match(css, /prefers-reduced-motion/);
  assert.match(css, /jluxe-checkout-addresses__card/);
  assert.match(css, /jluxe-checkout-addresses__form/);
});
