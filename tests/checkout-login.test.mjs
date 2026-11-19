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

test("province and city display names instead of numeric IDs", () => {
  const php = read("inc/checkout-login.php");
  // اسم استان/شهر هنگام ذخیره نگه داشته و در خط آدرس نمایش داده می‌شود.
  assert.match(php, /state_name/);
  assert.match(php, /city_name/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /buildAddressLine/);
  assert.match(js, /optionTextByValue/);
  assert.match(js, /state_name/);
  assert.match(js, /city_name/);
});

test("saved addresses can be edited and deleted", () => {
  const php = read("inc/checkout-login.php");
  // هر آدرس ذخیره‌شده شناسهٔ پایدار دارد تا قابل ویرایش/حذف باشد.
  assert.match(php, /jluxe_ajax_delete_checkout_address/);
  assert.match(php, /wp_ajax_jluxe_delete_checkout_address/);
  assert.match(php, /data-jluxe-address-edit/);
  assert.match(php, /data-jluxe-address-delete/);
  assert.match(php, /address_id/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /data-jluxe-address-edit/);
  assert.match(js, /data-jluxe-address-delete/);
  assert.match(js, /deleteAddress/);
  assert.match(js, /editingAddressId/);
  assert.match(js, /fillFormForEdit/);
  assert.match(js, /ذخیره تغییرات/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-checkout-addresses__action/);
  assert.match(css, /jluxe-checkout-addresses__action--danger/);
});

test("multiple addresses are stored per customer in a dedicated meta", () => {
  const php = read("inc/checkout-login.php");
  // ووکامرس فقط یک صورتحساب/ارسال نگه می‌دارد؛ لیست چند آدرس در متای اختصاصی است.
  assert.match(php, /jluxe_saved_addresses/);
  assert.match(php, /update_user_meta\( \$user_id, 'jluxe_saved_addresses', \$saved \)/);
  assert.match(php, /get_user_meta\( \$user_id, 'jluxe_saved_addresses', true \)/);
  const js = read("assets/js/checkout-login.js");
  // ajaxUrl مستقیم از کانفیگ استفاده می‌شود؛ فقط پروتکل در صورت ناهمخوانی اصلاح می‌شود.
  assert.match(js, /parsed\.protocol !== window\.location\.protocol/);
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

test("save AJAX handler forwards address_id so edits update in place instead of duplicating", () => {
  const php = read("inc/checkout-login.php");
  // هندلر باید address_id را از POST بخواند و به تابع ذخیره بدهد؛ وگرنه
  // ویرایش همیشه آدرس جدید می‌ساخت و تکراری می‌شد.
  assert.match(php, /'address_id' => isset\( \$_POST\['address_id'\] \)/);
  // تابع ذخیره شناسهٔ آدرس ذخیره‌شده را برمی‌گرداند تا کلاینت همان کارت را
  // انتخاب کند (نه آخرین آیتم لیست).
  assert.match(php, /function jluxe_save_customer_address\( array \$data \): string/);
  assert.match(php, /return \(string\) \( \$new_address\['id'\] \?\? \$address_id \);/);
  assert.match(php, /'saved_id' => \$saved_id/);
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /json\.data\.saved_id/);
  assert.match(js, /findIndex/);
});

test("guest checkout keeps province and city fields in the billing form", () => {
  const billing = read("woocommerce/checkout/form-billing.php");
  // فقط کاربر واردشده استان/شهر را در آکاردئون می‌بیند؛ مهمان باید آن‌ها را
  // در فرم صورتحساب ببیند، وگرنه چک‌اوت مهمان همیشه در اعتبارسنجی می‌خورد.
  assert.match(billing, /is_user_logged_in\(\) && in_array\( \$key, array\( 'billing_state', 'billing_city' \), true \)/);
});

test("initially selected address card is applied to the checkout form on load", () => {
  const js = read("assets/js/checkout-login.js");
  // کارتِ از قبل checked باید روی فرم اعمال شود؛ وگرنه استان/شهر خالی
  // می‌مانند و ثبت سفارش در اعتبارسنجی ووکامرس می‌خورد.
  assert.match(js, /data-jluxe-address-option\]:checked/);
  assert.match(js, /select\(checkedCard\)/);
});

test("opening the new-address accordion clears province and city; closing re-applies the selected card", () => {
  const js = read("assets/js/checkout-login.js");
  // استان/شهر برای آدرس جدید همیشه خالی شروع می‌شوند (هیچ‌وقت کش نیستند).
  assert.match(js, /applyStateCity\("", ""\)/);
  // بستن بدون ذخیره، فرم را به آدرس انتخاب‌شده برمی‌گرداند.
  assert.match(js, /اگر آکاردئون بدون ذخیره بسته شد/);
});

test("Persian and Arabic digits are converted before the address is saved", () => {
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /toLatinDigits/);
  assert.match(js, /۰۱۲۳۴۵۶۷۸۹/);
  assert.match(js, /٠١٢٣٤٥٦٧٨٩/);
});

test("classic checkout hides the address selector on the payment step", () => {
  const js = read("assets/js/woocommerce.js");
  // بخش «آدرس تحویل» بیرونِ #customer_details است؛ باید صراحتاً toggle شود.
  assert.match(js, /data-jluxe-address-selector/);
  assert.match(js, /addressSelector\.style\.display = showShipping \? "" : "none"/);
});

test("order-pay endpoint is excluded from the OTP login redirect", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /is_wc_endpoint_url\( 'order-pay' \)/);
});

test("checkout address localize carries no dead provinces/cities payload", () => {
  const php = read("inc/checkout-login.php");
  // استان/شهر همان فیلدهای واقعی ووکامرس‌اند؛ localize کردن دوبارهٔ دادهٔ
  // شهرها فقط payload مرده در هر بارگذاری چک‌اوت بود.
  assert.match(php, /JLUXE_CHECKOUT_ADDRESS/);
  assert.doesNotMatch(php, /'provinces'\s*=>/);
  assert.doesNotMatch(php, /'cities'\s*=>/);
  const js = read("assets/js/checkout-login.js");
  assert.doesNotMatch(js, /config\.provinces/);
  assert.doesNotMatch(js, /config\.cities/);
});

test("editing a billing/shipping meta address skips the saved-list write", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /if \( ! \$is_meta_address \) \{\s*\n\s*update_user_meta\( \$user_id, 'jluxe_saved_addresses', \$saved \);\s*\n\s*\}/);
});

test("identity fields are validated before proceeding in both layouts", () => {
  const guided = read("assets/js/checkout-guided.js");
  assert.match(guided, /data-jluxe-identity-field/);
  assert.match(guided, /jluxe-identity-invalid/);
  const classic = read("assets/js/woocommerce.js");
  assert.match(classic, /data-jluxe-identity-field/);
  assert.match(classic, /jluxe-identity-invalid/);
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-identity-invalid/);
});

test("checkout buttons have press, hover-lift, disabled and loading-spinner effects", () => {
  const css = read("src/styles/storefront.css");
  // فشردگی روی active و لیفت روی hover.
  assert.match(css, /\.jluxe-checkout-action-button:active:not\(:disabled\)/);
  assert.match(css, /translateY\(-1px\)/);
  // حالت disabled.
  assert.match(css, /\.jluxe-checkout-action-button:disabled/);
  // اسپینرِ بارگذاری برای دکمه‌های AJAX.
  assert.match(css, /\.jluxe-checkout-action-button\[aria-busy="true"\]::after/);
  assert.match(css, /jluxe-button-spin/);
  // افکتِ فشردگی روی کارت‌های پرداخت/ارسال.
  assert.match(css, /label\.jluxe-payment-card:active/);
  // دکمهٔ ذخیرهٔ آدرس هنگام AJAX اسپینر می‌گیرد.
  const js = read("assets/js/checkout-login.js");
  assert.match(js, /aria-busy/);
  assert.match(js, /is-loading/);
  // دکمه‌های ویرایش/حذف کارت آدرس هم افکت دارند.
  const loginCss = read("assets/css/checkout-login.css");
  assert.match(loginCss, /jluxe-checkout-addresses__action:active/);
  assert.match(loginCss, /jluxe-checkout-addresses__action:focus-visible/);
});

test("address edit/delete buttons are icon-only and delete resets an open edit first", () => {
  const php = read("inc/checkout-login.php");
  // متن «ویرایش»/«حذف» حذف شده؛ فقط آیکون + aria-label/title می‌ماند.
  assert.doesNotMatch(php, /<span>ویرایش<\/span>/);
  assert.doesNotMatch(php, /<span>حذف<\/span>/);
  assert.match(php, /title="ویرایش آدرس"/);
  assert.match(php, /title="حذف آدرس"/);
  const js = read("assets/js/checkout-login.js");
  assert.doesNotMatch(js, /editBtn\.textContent = "ویرایش"/);
  assert.doesNotMatch(js, /deleteBtn\.textContent = "حذف"/);
  // حذف هنگام باز بودن ویرایش: اول state ویرایش ریست می‌شود تا حذف تمیز انجام شود.
  assert.match(js, /اگر ویرایشِ آدرس دیگری باز است/);
  assert.match(js, /editingAddressId = null;/);
  // جلوگیری از فعال‌شدنِ label هنگام کلیک روی دکمه‌ها.
  assert.match(js, /addEventListener\("mousedown"/);
  const css = read("assets/css/checkout-login.css");
  // دکمه‌ها مربعی/آیکونی‌اند.
  assert.match(css, /jluxe-checkout-addresses__action \{[\s\S]*?width: 2\.25rem/);
});
