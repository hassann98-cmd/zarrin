import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { JSDOM } from "jsdom";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = (rel) => fs.readFileSync(path.join(root, rel), "utf8");
const exists = (rel) => fs.existsSync(path.join(root, rel));

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
  // 1.7.359: برچسب فیلد «تلفن» است (نه «تلفن ثابت») ولی نام فیلد landline می‌ماند.
  assert.match(php, /<label for="jluxe-new-address-landline">تلفن<\/label>/);
  assert.doesNotMatch(php, /<label for="jluxe-new-address-landline">تلفن ثابت<\/label>/);
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
  const guided = exists("woocommerce/checkout/form-checkout-guided.php") ? read("woocommerce/checkout/form-checkout-guided.php") : "";
  assert.match(classic, /jluxe_render_address_selector\(\)/);
  if (guided) assert.match(guided, /jluxe_render_address_selector\(\)/);
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
  // 1.7.358: ship-to-different همیشه غیرفعال است، پس انتخاب آدرس «shipping»
  // هم باید روی billing اعمال شود تا روی سفارش اثر بگذارد.
  assert.match(js, /value === "shipping"\) \{[\s\S]*?applyAddress\(address, "shipping"\);\s*applyAddress\(address, "billing"\)/, "shipping address selection also applies to billing since ship-to-different is disabled");
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
  // 1.7.359: وقتی فقط یک آدرس وجود دارد، حذف نیازمند دو کلیک است.
  assert.match(js, /cards\(\)\.length <= 1/, "single-address delete requires two clicks");
  assert.match(js, /jluxeConfirming/, "first click arms the delete button");
  assert.match(js, /is-confirming/, "confirming state is styled");
  const css = read("assets/css/checkout-login.css");
  assert.match(css, /jluxe-checkout-addresses__action/);
  assert.match(css, /jluxe-checkout-addresses__action--danger/);
  assert.match(css, /is-confirming/, "confirming delete button has a distinct style");
});

test("new-address accordion always starts with empty province and city", () => {
  const js = read("assets/js/checkout-login.js");
  // 1.7.359: clearNewForm استان/شهر را هم خالی می‌کند تا کاربر مجبور باشد
  // اول استان را انتخاب کند و شهر بعد از آن لود شود (هیچ‌وقت کش نیست).
  assert.match(js, /function clearNewForm\(\) \{[\s\S]*?applyStateCity\("", ""\)/, "clearNewForm clears province and city");
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
  const guided = exists("assets/js/checkout-guided.js") ? read("assets/js/checkout-guided.js") : "";
  if (guided) { assert.match(guided, /data-jluxe-identity-field/); assert.match(guided, /jluxe-identity-invalid/); }
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

test("first-time users with no saved address get the new-address accordion auto-opened", () => {
  const js = read("assets/js/checkout-login.js");
  // اگر کارتی انتخاب نشده (لیست خالی)، آکاردئون باز می‌شود تا استان/شهر
  // همان‌جا پر شود و در submit خالی نماند.
  assert.match(js, /کاربر بار اول \(هیچ آدرس ذخیره‌شده‌ای نیست\)/);
  assert.match(js, /setFormOpen\(true\)/);
});

test("province and city are validated before proceeding to payment in both layouts", () => {
  const classic = read("assets/js/woocommerce.js");
  assert.match(classic, /استان\/شهر \(billing_state\/billing_city\) در آکاردئونِ آدرس‌اند/);
  assert.match(classic, /getElementById\("billing_state"\)/);
  assert.match(classic, /getElementById\("billing_city"\)/);
  const guided = exists("assets/js/checkout-guided.js") ? read("assets/js/checkout-guided.js") : "";
  if (guided) { assert.match(guided, /استان\/شهر در آکاردئونِ آدرس‌اند/); assert.match(guided, /getElementById\("billing_state"\)/); }
});

test("OTP login redirect is skipped when the cart is empty", () => {
  const php = read("inc/checkout-login.php");
  assert.match(php, /WC\(\)->cart && WC\(\)->cart->is_empty\(\)/);
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

test("legacy numeric state/city IDs are omitted from the address line instead of printing raw", () => {
  const js = read("assets/js/checkout-login.js");
  // isNumericId helper exists and is used for both state and city fallbacks.
  assert.match(js, /function isNumericId\(value\)/);
  assert.match(js, /else if \(address\.state && !isNumericId\(address\.state\)\)/);
  assert.match(js, /else if \(address\.city && !isNumericId\(address\.city\)\)/);
  // Raw numeric IDs must never be pushed into the display parts.
  assert.doesNotMatch(js, /else if \(address\.state\) parts\.push\(String\(address\.state\)\)/);
  assert.doesNotMatch(js, /else if \(address\.city\) parts\.push\(String\(address\.city\)\)/);
});

test("checkout address changes are replayed once after the user pauses, including the province-to-city cascade", async (t) => {
  const js = read("assets/js/checkout-login.js");
  const dom = new JSDOM(
    '<form class="checkout"><input name="billing_address_1"><select name="billing_state"><option value="">—</option><option value="THR">تهران</option></select><select name="billing_city"><option value="">—</option><option value="city">شهر</option></select></form>',
    { url: "https://shop.test/checkout/", runScripts: "outside-only" },
  );
  t.after(() => dom.window.close());
  const { window } = dom;
  const { document } = window;
  window.eval(js);
  document.dispatchEvent(new window.Event("DOMContentLoaded"));

  const form = document.querySelector("form.checkout");
  const address = form.querySelector('[name="billing_address_1"]');
  const state = form.querySelector('[name="billing_state"]');
  const city = form.querySelector('[name="billing_city"]');
  const changes = [];
  form.addEventListener("change", event => changes.push(event.target.name));
  state.addEventListener("change", () => {
    if (state.value === "THR" && !city.value) {
      city.value = "city";
      city.dispatchEvent(new window.Event("change", { bubbles: true }));
    }
  });

  address.value = "خیابان اول";
  address.dispatchEvent(new window.Event("input", { bubbles: true }));
  address.value = "خیابان دوم";
  address.dispatchEvent(new window.Event("input", { bubbles: true }));
  await new Promise(resolve => window.setTimeout(resolve, 100));
  assert.deepEqual(changes, [], "typing does not start checkout refreshes before the quiet period");
  await new Promise(resolve => window.setTimeout(resolve, 200));
  assert.deepEqual(changes, ["billing_address_1"], "the latest address value is delivered in one trailing change");

  state.value = "THR";
  state.dispatchEvent(new window.Event("change", { bubbles: true }));
  assert.deepEqual(changes, ["billing_address_1"], "province and city changes are deferred together");
  await new Promise(resolve => window.setTimeout(resolve, 280));
  assert.deepEqual(changes, ["billing_address_1", "billing_city", "billing_state"], "the delayed province event lets the city cascade complete in the same replay turn without losing either field change");
});
