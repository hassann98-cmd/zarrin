<?php
/**
 * R81 — محافظِ قالب‌های ووکامرس.
 *
 * ریشهٔ خاموشیِ کاملِ فروشگاه (۲۷ سپتامبر ۲۰۲۶): چند فایلِ قالبِ پوسته
 * (از جمله `woocommerce/content-product.php`) هنگامِ آپلود بریده/ناقص شده
 * بودند. PHP هنگامِ include خطای نحوی می‌دهد و چون این فایل فقط وقتی
 * include می‌شود که یک «کارتِ محصول» رندر شود، دقیقاً همان‌جا صفحه می‌مرد:
 * صفحهٔ اصلی بعد از هیرو/دسته‌بندی‌ها، آرشیو فروشگاه بعد از نوارِ فیلتر، و
 * «محصولات مرتبط» صفحهٔ محصول.
 *
 * این فایل همان حلقه را می‌بندد: هر قالبی که ووکامرس از پوشهٔ پوسته
 * برمی‌دارد، اول با فهرستِ رسمیِ SHA-256 (`docs/FILES.sha256`) سنجیده
 * می‌شود. اگر فایل با بستهٔ رسمی یکی نبود:
 *   ۱) نسخهٔ پشتیبانِ دست‌نخوردهٔ همان قالب داخلِ همین پوسته استفاده می‌شود
 *      (پس طراحیِ اختصاصی هم حفظ می‌شود)،
 *   ۲) و اگر خودِ آن کپی هم سالم/تأییدشده نبود، قالبِ پیش‌فرضِ خودِ
 *      ووکامرس جایگزین می‌شود تا فروشگاه هرگز کامل از کار نیفتد.
 *
 * هر جایگزینی در یک اپشن ثبت و به مدیرِ سایت با یک اخطارِ روشن نشان داده
 * می‌شود (به‌همراهِ فهرستِ فایل‌های ناسالم و لینکِ صفحهٔ تشخیص).
 *
 * این محافظ چیزی را «تعمیر» نمی‌کند و ادعای تعمیر هم ندارد؛ فقط نمی‌گذارد
 * یک فایلِ ناقص کلِ فروشگاه را بخواباند. برای تعمیرِ واقعی، بستهٔ رسمی را
 * دوباره نصب کنید (یا فایل‌های ناسالم را بازآپلود کنید).
 */

defined( 'ABSPATH' ) || exit;

/** محلِ نسخه‌های پشتیبان در بستهٔ رسمی. */
const JLUXE_TEMPLATE_FALLBACK_DIR = 'inc/woo-template-fallbacks';

/** گزینهٔ ثبتِ جایگزینی‌ها (فایل => آخرین زمانِ مشاهده). */
const JLUXE_TEMPLATE_GUARD_OPTION = 'jluxe_template_guard_hits';

/**
 * وضعیتِ یک قالبِ پوسته نسبت به بستهٔ رسمی:
 * `ok` (سالم) / `broken` (با بستهٔ رسمی فرق دارد) / `unguarded` (در فهرست نیست).
 * نتیجه برای هر مسیر در همان درخواست کش می‌شود (هش‌گرفتن تکرار نمی‌شود).
 */
function jluxe_template_guard_state( string $relative ): string {
	static $states = array();
	static $seen_reset = 0;
	$current_reset = (int) ( $GLOBALS['jluxe_template_guard_cache_reset'] ?? 0 );
	if ( $current_reset !== $seen_reset ) {
		$states      = array();
		$seen_reset  = $current_reset;
	}
	if ( isset( $states[ $relative ] ) ) {
		return $states[ $relative ];
	}
	$absolute = JLUXE_THEME_DIR . '/' . $relative;
	$manifest = function_exists( 'jluxe_theme_manifest' ) ? jluxe_theme_manifest() : array();
	if ( ! isset( $manifest[ $relative ] ) || ! is_readable( $absolute ) ) {
		return $states[ $relative ] = 'unguarded';
	}
	return $states[ $relative ] = ( hash_file( 'sha256', $absolute ) === $manifest[ $relative ] ) ? 'ok' : 'broken';
}

/**
 * پاک‌کردنِ کشِ وضعیت (هر درخواست کشِ خودش را دارد؛ این تابع برای
 * «بررسی دوباره» بعد از بازآپلود و برای آزمون‌ها لازم است، تا نتیجهٔ
 * قدیمیِ «خراب» به درخواستِ بعدی سرایت نکند).
 */
function jluxe_template_guard_reset_cache(): void {
	jluxe_template_guard_state_reset();
}

function jluxe_template_guard_state_reset(): void {
	// کشِ داخلیِ jluxe_template_guard_state با ارجاع خالی می‌شود.
	static $reset = null;
	if ( null === $reset ) {
		$reset = true;
	}
	$GLOBALS['jluxe_template_guard_cache_reset'] = (int) ( $GLOBALS['jluxe_template_guard_cache_reset'] ?? 0 ) + 1;
}

/** ثبتِ یک جایگزینی برای نمایش به مدیر (بدونِ نوشتنِ مکرر). */
function jluxe_template_guard_record( string $relative, string $mode ): void {
	$hits = get_option( JLUXE_TEMPLATE_GUARD_OPTION, array() );
	if ( ! is_array( $hits ) ) {
		$hits = array();
	}
	$hits[ $relative ] = array(
		'time' => time(),
		'mode' => $mode,
	);
	if ( count( $hits ) > 30 ) {
		$hits = array_slice( $hits, -30, null, true );
	}
	update_option( JLUXE_TEMPLATE_GUARD_OPTION, $hits, false );
}

/**
 * مسیرِ سالمی که باید جای قالبِ ناسالم استفاده شود:
 * اول نسخهٔ پشتیبانِ تأییدشدهٔ خودِ پوسته، وگرنه قالبِ ووکامرس.
 */
function jluxe_template_guard_replacement( string $relative, string $template_name ): string {
	$manifest = function_exists( 'jluxe_theme_manifest' ) ? jluxe_theme_manifest() : array();
	$spare    = JLUXE_TEMPLATE_FALLBACK_DIR . '/' . $relative;
	$spare_abs = JLUXE_THEME_DIR . '/' . $spare;
	if ( is_readable( $spare_abs ) && isset( $manifest[ $spare ] ) && hash_file( 'sha256', $spare_abs ) === $manifest[ $spare ] ) {
		jluxe_template_guard_record( $relative, 'fallback' );
		return $spare_abs;
	}

	$default = '';
	if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'plugin_path' ) ) {
		$default = trailingslashit( WC()->plugin_path() ) . 'templates/' . $template_name;
	}
	jluxe_template_guard_record( $relative, 'woocommerce' );
	return ( $default && is_readable( $default ) ) ? $default : '';
}

/**
 * فیلترِ رسمیِ خودِ ووکامرس روی محلِ قالب. فقط قالب‌های پوسته بررسی
 * می‌شوند و فقط وقتی فایل با بستهٔ رسمی یکی نیست.
 */
function jluxe_template_guard_locate( $template, $template_name, $template_path ) {
	if ( ! apply_filters( 'jluxe_template_guard_enabled', true ) ) {
		return $template;
	}
	if ( ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	$theme_root = trailingslashit( JLUXE_THEME_DIR );
	if ( 0 !== strpos( $template, $theme_root ) ) {
		return $template;
	}
	$relative = ltrim( substr( $template, strlen( $theme_root ) ), '/' );
	if ( 0 !== strpos( $relative, 'woocommerce/' ) ) {
		return $template;
	}
	if ( 'broken' !== jluxe_template_guard_state( $relative ) ) {
		return $template;
	}
	$replacement = jluxe_template_guard_replacement( $relative, (string) $template_name );
	return '' !== $replacement ? $replacement : $template;
}
add_filter( 'woocommerce_locate_template', 'jluxe_template_guard_locate', 99, 3 );

/**
 * همان محافظ برای قالبِ اصلیِ خودِ وردپرس (front-page.php، single.php،
 * archive-product.php، ...) — این‌ها را وردپرس با `template_include` انتخاب
 * می‌کند و اگر یکی‌شان ناقص آپلود شده باشد، همان صفحه (و فقط همان صفحه)
 * با خطای نحوی می‌افتد. مسیرِ سالم در همان آینهٔ کپی‌ها هست.
 */
function jluxe_template_guard_include( $template ) {
	if ( ! apply_filters( 'jluxe_template_guard_enabled', true ) ) {
		return $template;
	}
	if ( ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	$theme_root = trailingslashit( JLUXE_THEME_DIR );
	if ( 0 !== strpos( $template, $theme_root ) ) {
		return $template;
	}
	$relative = ltrim( substr( $template, strlen( $theme_root ) ), '/' );
	if ( false !== strpos( $relative, '/' ) ) {
		return $template; // فقط قالب‌های ریشهٔ پوسته؛ بقیه مسیرها جای دیگری پوشش داده شده‌اند.
	}
	if ( 'broken' !== jluxe_template_guard_state( $relative ) ) {
		return $template;
	}
	$replacement = jluxe_template_guard_replacement( $relative, $relative );
	return '' !== $replacement ? $replacement : $template;
}
add_filter( 'template_include', 'jluxe_template_guard_include', 99 );

/**
 * اخطارِ پیشخوان: کدام قالب‌ها ناسالم‌اند و کدام مسیر جایگزین شده است.
 * عمداً «قابل‌ردکردن» نیست — تا وقتی فایل‌ها بازآپلود نشوند این وضعیت
 * باقی است و مدیری که وارد پیشخوان می‌شود باید بداند.
 */
function jluxe_template_guard_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$hits = get_option( JLUXE_TEMPLATE_GUARD_OPTION, array() );
	if ( ! is_array( $hits ) || empty( $hits ) ) {
		return;
	}
	$rows = array();
	foreach ( $hits as $relative => $hit ) {
		$rows[] = sprintf(
			'<li><code dir="ltr">%s</code> — %s</li>',
			esc_html( (string) $relative ),
			'fallback' === ( $hit['mode'] ?? '' )
				? 'جایگزین‌شده با نسخهٔ پشتیبانِ سالمِ همین پوسته'
				: 'جایگزین‌شده با قالبِ پیش‌فرضِ ووکامرس (نسخهٔ پشتیبانِ پوسته هم تأیید نشد)'
		);
	}
	printf(
		'<div class="notice notice-error"><p><strong>پوستهٔ زرین: فایل‌های قالب با بستهٔ رسمی یکسان نیستند.</strong> این معمولاً یعنی آپلود/نصبِ پوسته کامل نشده و فایل‌ها بریده‌اند — همان چیزی که می‌تواند کلِ حلقهٔ محصولات را از کار بیندازد. برای اینکه فروشگاه بخوابد، این فایل‌ها موقتاً کنار گذاشته شده‌اند:</p><ul style="list-style:disc;margin-inline-start:20px">%s</ul><p><a class="button button-primary" href="%s">بررسی و فهرستِ کاملِ فایل‌های ناسالم</a> — بستهٔ رسمی را دوباره و کامل نصب کنید.</p></div>',
		implode( '', $rows ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — هر ردیف بالا esc شده است.
		esc_url( admin_url( 'themes.php?page=jluxe-site-diagnosis' ) )
	);
}
add_action( 'admin_notices', 'jluxe_template_guard_notice' );

/** پاک‌کردنِ فهرستِ جایگزینی‌ها (بعد از بازآپلودِ موفق). */
function jluxe_template_guard_clear_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی نیست.', 'jluxe' ) );
	}
	check_admin_referer( 'jluxe_template_guard_clear' );
	delete_option( JLUXE_TEMPLATE_GUARD_OPTION );
	wp_safe_redirect( admin_url( 'themes.php?page=jluxe-site-diagnosis&jluxe_guard=cleared' ) );
	exit;
}
add_action( 'admin_post_jluxe_template_guard_clear', 'jluxe_template_guard_clear_action' );
