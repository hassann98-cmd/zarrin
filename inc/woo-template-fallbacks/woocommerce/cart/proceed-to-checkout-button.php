<?php
/**
 * دکمه‌ی «ادامه»ی سبد خرید — تمپلیت اورراید صریح. باگِ واقعیِ گزارش‌شده:
 * با وجودِ فیلترِ woocommerce_proceed_to_checkout_button_text (که قبلاً
 * در inc/woocommerce.php اضافه شده بود) هنوز متنِ «اقدام به پرداخت»
 * نشون داده می‌شد. با بررسیِ زنده‌ی خودِ تمپلیتِ اصلیِ ووکامرس
 * (woocommerce/templates/cart/proceed-to-checkout-button.php نسخه‌ی
 * ۷.۰.۱) مشخص شد این تمپلیت اصلاً از apply_filters('woocommerce_proceed_to_checkout_button_text', ...)
 * استفاده نمی‌کنه — فقط esc_html_e('Proceed to checkout', 'woocommerce')
 * صداست، یعنی اون فیلتر در این نسخه اصلاً وجود نداره (فیلتر بی‌اثر
 * بود، نه اشتباه). متنِ «اقدام به پرداخت» از ترجمه‌ی رشته‌ی همین
 * gettext توسطِ افزونه‌ی «ووکامرس فارسی» میاد، نه از یک فیلترِ قابل
 * override. تنها راهِ واقعیِ تغییرِ این متن، همینِ اورراید تمپلیت است —
 * دقیقاً همون مکانیزمِ رسمیِ ووکامرس برای سفارشی‌سازیِ تمپلیت‌ها (نه
 * دست‌کاریِ فایل‌های افزونه/هسته).
 *
 * @see woocommerce/templates/cart/proceed-to-checkout-button.php (نسخه‌ی اصلی)
 *
 * برچسبِ «ثبت سفارش» طبقِ مرجعِ تصویریِ جدیدِ کاربر برای مرحله‌ی «بررسیِ
 * سبدِ خرید» (قبلاً «ادامه» بود).
 */

defined( 'ABSPATH' ) || exit;
?>
<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="checkout-button button alt wc-forward">ثبت سفارش</a>
