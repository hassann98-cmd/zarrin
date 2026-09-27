<?php
/**
 * R82 — اجراکنندهٔ امنِ قالبِ پوسته (trampoline).
 *
 * چرا این فایل لازم شد: روی سایتِ فعال ثابت شد فایلِ قالبِ کارتِ محصول
 * (`woocommerce/content-product.php`) ناقص/بریده است و هر صفحه‌ای که یک کارت
 * محصول رندر می‌کند وسطِ کار با «خطای مهم» می‌میرد. محافظِ قالب (فایلِ
 * `inc/template-guard.php`) فایلِ خراب را کنار می‌گذارد، ولی همیشه یک حالتِ
 * باقی‌مانده وجود دارد: خودِ فایل سالم باشد و خطا از کدِ زمانِ اجرا بیاید
 * (تابعی که به‌خاطرِ ناقص‌بودن یک فایلِ دیگر تعریف نشده، نوعِ اشتباهِ داده،
 * …). در آن حالت هیچ فیلتری نمی‌تواند جلوی مرگِ صفحه را بگیرد، چون خطا
 * وسطِ `include` رخ می‌دهد.
 *
 * ووکامرس/وردپرس قالب را با `include`/`require` اجرا می‌کنند. از PHP 7 به
 * بعد خطای نحویِ فایلِ includeشده به‌صورت `ParseError` و خطاهای زمانِ اجرا
 * به‌صورت `Error` «قابلِ گرفتن» هستند. پس این فایل کوچک جای خودِ قالب
 * include می‌شود، قالبِ واقعی را داخل try/catch اجرا می‌کند و در صورتِ خطا:
 *   ۱) خطای واقعی (پیام/فایل/خط) را ثبت می‌کند تا در «تشخیص سایت» دیده شود،
 *   ۲) اگر نسخهٔ جانشینِ سالمی باشد (کپیِ پشتیبانِ پوسته یا قالبِ خودِ
 *      ووکامرس)، همان را رندر می‌کند تا کارتِ محصول نمایش داده شود،
 *   ۳) و در هر حالت صفحه زنده می‌ماند — دیگر «یک فایلِ خراب = فروشگاهِ
 *      خوابیده» معنی ندارد.
 *
 * نکتهٔ فنی: چون این فایل با `include` از داخلِ همان اسکوپی اجرا می‌شود که
 * قالب را include می‌کرد، متغیرهای محلیِ قالب (مثل `$args` که خودِ
 * `wc_get_template()` با extract ساخته، یا `global $product`) دست‌نخورده
 * به قالب می‌رسند.
 */

defined( 'ABSPATH' ) || exit;

$jluxe_guard_slot = isset( $GLOBALS['jluxe_template_guard_slot'] ) ? (array) $GLOBALS['jluxe_template_guard_slot'] : array();
// اسلات فقط یک‌بار مصرف می‌شود (و فقط اگر تازه باشد) تا یک اسلاتِ باقی‌ماندهٔ
// از کار افتاده هرگز قالبِ دیگری را با دادهٔ اشتباه رندر نکند.
unset( $GLOBALS['jluxe_template_guard_slot'] );

$jluxe_guard_age = isset( $jluxe_guard_slot['t'] ) ? ( microtime( true ) - (float) $jluxe_guard_slot['t'] ) : 99.0;
if ( ! isset( $jluxe_guard_slot['first'] ) || $jluxe_guard_age > 5.0 ) {
	return;
}

$jluxe_guard_first = (string) $jluxe_guard_slot['first'];
$jluxe_guard_spare = isset( $jluxe_guard_slot['spare'] ) ? (string) $jluxe_guard_slot['spare'] : '';
$jluxe_guard_key   = isset( $jluxe_guard_slot['key'] ) ? (string) $jluxe_guard_slot['key'] : '';

if ( '' === $jluxe_guard_first || ! is_readable( $jluxe_guard_first ) ) {
	return;
}

try {
	include $jluxe_guard_first;
} catch ( \Throwable $jluxe_guard_error ) {
	jluxe_template_guard_caught( $jluxe_guard_key, $jluxe_guard_error, $jluxe_guard_first );
	if ( '' !== $jluxe_guard_spare && $jluxe_guard_spare !== $jluxe_guard_first && is_readable( $jluxe_guard_spare ) ) {
		try {
			include $jluxe_guard_spare;
		} catch ( \Throwable $jluxe_guard_error2 ) {
			jluxe_template_guard_caught( $jluxe_guard_key . ' (جانشین)', $jluxe_guard_error2, $jluxe_guard_spare );
		}
	}
}
