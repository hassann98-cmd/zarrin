<?php
/**
 * R86 — لایهٔ سازگاریِ رشته‌ای (بدونِ وابستگیِ اجباری به mbstring).
 *
 * چرا: پوسته در چند جا برای متنِ فارسی از `mb_strlen`/`mb_substr`/`mb_strpos`
 * استفاده می‌کند. اگر هاست `mbstring` را خاموش داشته باشد، این‌ها «تابعِ
 * تعریف‌نشده» می‌شوند و همان لحظه صفحه با خطای مهم می‌میرد — یعنی یک وابستگیِ
 * runtime که در فهرستِ نیازمندی‌ها هم اعلام نشده بود.
 *
 * حالا همهٔ کدِ پوسته از همین سه هلپر استفاده می‌کند:
 *   • jluxe_strlen()  → تعداد کاراکتر (نه بایت)
 *   • jluxe_substr()  → برش بر اساس کاراکتر
 *   • jluxe_strpos()  → موقعیتِ کاراکتری (int|false)، مثلِ mb_strpos
 * اگر mbstring باشد، همان mb_* اجرا می‌شود؛ اگر نباشد، معادلِ UTF-8 با
 * `preg_*` و فلگِ /u اجرا می‌شود (پس متنِ فارسی نه می‌شکند و نه بایت‌شماری
 * می‌شود). تستِ خودکار هم بررسی می‌کند که هیچ فایلی در پوسته مستقیم `mb_*`
 * صدا نزند.
 */

defined( 'ABSPATH' ) || exit;

/** آیا mbstring در دسترس است؟ (فقط برای گزارش/تست) */
function jluxe_mb_available(): bool {
	return function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' );
}

/** تعداد کاراکترهای یک رشته (UTF-8)، با یا بدونِ mbstring. */
function jluxe_strlen( string $value ): int {
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) \mb_strlen( $value, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- همین لایه جایگزینِ آن است.
	}
	// شمارشِ کاراکترها بدونِ mbstring؛ رشتهٔ نامعتبر هم بایت‌به‌بایت شمرده می‌شود.
	$count = preg_match_all( '/./us', $value );
	return false === $count ? strlen( $value ) : (int) $count;
}

/** برشِ رشته بر اساس کاراکتر (UTF-8)، با یا بدونِ mbstring. */
function jluxe_substr( string $value, int $start, ?int $length = null ): string {
	if ( function_exists( 'mb_substr' ) ) {
		return null === $length
			? (string) \mb_substr( $value, $start, null, 'UTF-8' ) // phpcs:ignore WordPress.WP.AlternativeFunctions
			: (string) \mb_substr( $value, $start, $length, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$matches = preg_match_all( '/./us', $value, $found );
	if ( false === $matches || $matches !== count( $found[0] ) ) {
		return null === $length ? substr( $value, $start ) : substr( $value, $start, $length );
	}
	$slice = null === $length ? array_slice( $found[0], $start ) : array_slice( $found[0], $start, $length );
	return implode( '', $slice );
}

/** موقعیتِ کاراکتریِ یک زیررشته (int) یا false — مثلِ mb_strpos. */
function jluxe_strpos( string $haystack, string $needle, int $offset = 0 ) {
	if ( function_exists( 'mb_strpos' ) ) {
		return \mb_strpos( $haystack, $needle, $offset, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$byte_pos = strpos( $haystack, $needle, $offset );
	if ( false === $byte_pos ) {
		return false;
	}
	$chars = preg_match_all( '/./us', substr( $haystack, 0, $byte_pos ) );
	return false === $chars ? $byte_pos : (int) $chars;
}
