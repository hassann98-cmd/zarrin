<?php
/**
 * R81 + R82 — محافظِ قالب‌های پوسته.
 *
 * ریشهٔ خاموشیِ کاملِ فروشگاه (۲۷ سپتامبر ۲۰۲۶): فایلِ قالبِ کارتِ محصول
 * (`woocommerce/content-product.php`) روی سرور ناقص/بریده بود. PHP هنگامِ
 * include خطای نحوی می‌دهد و چون این فایل فقط وقتی include می‌شود که یک
 * «کارتِ محصول» رندر شود، دقیقاً همان‌جا صفحه می‌مرد: صفحهٔ اصلی بعد از
 * هیرو/دسته‌بندی‌ها، آرشیو فروشگاه بعد از نوارِ فیلتر، و «محصولات مرتبط».
 *
 * R81 این محافظ را ساخت ولی فقط روی فیلترِ `woocommerce_locate_template`
 * سوار کرد — و این یک اشتباهِ واقعی بود: ووکامرس کارتِ محصول را با
 * `wc_get_template_part()` می‌گیرد و آن مسیر **هیچ‌وقت** فیلترِ
 * `woocommerce_locate_template` را صدا نمی‌زند (تنها فیلترش
 * `wc_get_template_part` است). یعنی محافظ دقیقاً روی همان فایلی که سایت را
 * می‌خواباند هیچ‌وقت اجرا نمی‌شد و نصبِ 1.69.1 هم مشکل را حل نکرد.
 *
 * R82 سه چیز را درست می‌کند:
 *   ۱) پوششِ کاملِ مسیرهای واقعیِ include در ووکامرس:
 *      `wc_get_template_part` (کارتِ محصول، قالب‌های تکی) + `wc_get_template`
 *      (مسیرِ کش‌شده؛ چون با آبجکت‌کش، `wc_locate_template` اصلاً صدا زده
 *      نمی‌شود) + همان `woocommerce_locate_template` + `template_include`
 *      برای قالبِ اصلیِ وردپرس. هر چهار مسیر پوشش داده شده‌اند.
 *   ۲) تشخیصِ مستقل از فهرست: اگر `docs/FILES.sha256` نباشد/ناقص باشد،
 *      سالم‌بودنِ فایل با خودِ PHP سنجیده می‌شود (`token_get_all` با
 *      TOKEN_PARSE — بدونِ اجرا). پس محافظ بدونِ فهرست هم کار می‌کند.
 *   ۳) اجرای امن: قالب‌ها از مسیرِ `inc/template-guard-include.php`
 *      اجرا می‌شوند؛ هر خطای زمانِ اجرا (تابعِ تعریف‌نشده، نوعِ نادرست، …)
 *      گرفته و ثبت می‌شود و به‌جای مرگِ صفحه، نسخهٔ جانشین رندر می‌شود.
 *
 * سیاستِ جانشینی (همان منطقِ R81، گسترش‌یافته):
 *   قالبِ سالم            → خودِ قالب اجرا می‌شود (تلاشِ دوم: قالبِ ووکامرس)
 *   قالبِ ناسالم          → کپیِ پشتیبانِ دست‌نخوردهٔ همین پوسته (طراحی حفظ
 *                            می‌شود؛ تلاشِ دوم: قالبِ خودِ ووکامرس)
 *   کپیِ پشتیبان هم تأیید نشد → قالبِ پیش‌فرضِ ووکامرس
 *   هیچ‌کدام               → فایلِ اصلی (رفتارِ معمولِ وردپرس)
 *
 * هر جایگزینی/خطا در اپشنِ `jluxe_template_guard_hits` ثبت و با یک اخطارِ
 * روشن در پیشخوان به مدیر نشان داده می‌شود. این محافظ چیزی را «تعمیر»
 * نمی‌کند و ادعای تعمیر هم ندارد؛ برای تعمیرِ واقعی باید بستهٔ رسمی را
 * کامل نصب کرد (یا فایل‌های ناسالم را بازآپلود کرد).
 */

defined( 'ABSPATH' ) || exit;

/** محلِ نسخه‌های پشتیبان در بستهٔ رسمی. */
const JLUXE_TEMPLATE_FALLBACK_DIR = 'inc/woo-template-fallbacks';

/** گزینهٔ ثبتِ جایگزینی‌ها (فایل => آخرین زمان/حالت مشاهده‌شده). */
const JLUXE_TEMPLATE_GUARD_OPTION = 'jluxe_template_guard_hits';

/** اجراکنندهٔ امن (trampoline) که خطاهای زمانِ اجرا را هم می‌گیرد. */
const JLUXE_TEMPLATE_GUARD_LOADER = 'inc/template-guard-include.php';

/* -------------------------------------------------------------------------
 * تشخیصِ سالم‌بودن
 * ---------------------------------------------------------------------- */

/** کشِ کوتاه‌مدتِ درون‌درخواستی؛ با jluxe_template_guard_reset_cache() پاک می‌شود. */
function jluxe_template_guard_memo( string $kind, string $key, $value = null ) {
	$reset = (int) ( $GLOBALS['jluxe_template_guard_cache_reset'] ?? 0 );
	if ( (int) ( $GLOBALS['jluxe_template_guard_memo_reset'] ?? -1 ) !== $reset ) {
		$GLOBALS['jluxe_template_guard_memo']       = array();
		$GLOBALS['jluxe_template_guard_memo_reset'] = $reset;
	}
	if ( null === $value ) {
		return $GLOBALS['jluxe_template_guard_memo'][ $kind ][ $key ] ?? null;
	}
	$GLOBALS['jluxe_template_guard_memo'][ $kind ][ $key ] = $value;
	return $value;
}

/**
 * فهرستِ رسمیِ بسته (path => sha256).
 * اگر ابزارِ تشخیصِ سایت در دسترس نباشد (فایلِ ناقص)، خودش فهرست را می‌خواند
 * تا محافظ هیچ‌وقت به فایلِ دیگری وابسته نباشد.
 */
function jluxe_template_guard_manifest(): array {
	static $manifest = null;
	if ( is_array( $manifest ) ) {
		return $manifest;
	}
	if ( function_exists( 'jluxe_theme_manifest' ) ) {
		return $manifest = (array) jluxe_theme_manifest();
	}
	$manifest = array();
	$file     = JLUXE_THEME_DIR . '/docs/FILES.sha256';
	if ( ! is_readable( $file ) ) {
		return $manifest;
	}
	foreach ( preg_split( '/\r\n|\n|\r/', (string) file_get_contents( $file ) ) as $line ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( preg_match( '/^([0-9a-f]{64}) {2}(.+)$/', trim( $line ), $m ) ) {
			$manifest[ $m[2] ] = $m[1];
		}
	}
	return $manifest;
}

/**
 * آیا فایل از نظر نحوی سالم است؟ (بدونِ اجرا و بدونِ نیاز به فهرست)
 * یک فایلِ بریده/ناقص تقریباً همیشه `ParseError` می‌دهد و همین معیار، محافظ
 * را از وابستگی به `docs/FILES.sha256` آزاد می‌کند.
 */
function jluxe_template_guard_parses( string $absolute ): bool {
	$cached = jluxe_template_guard_memo( 'parses', $absolute );
	if ( null !== $cached ) {
		return (bool) $cached;
	}
	$source = is_readable( $absolute ) ? file_get_contents( $absolute ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_string( $source ) ) {
		return (bool) jluxe_template_guard_memo( 'parses', $absolute, true ); // ناتوانی در خواندن = عدمِ مداخله
	}
	try {
		token_get_all( $source, TOKEN_PARSE );
	} catch ( \Throwable $e ) {
		return (bool) jluxe_template_guard_memo( 'parses', $absolute, false );
	}
	return (bool) jluxe_template_guard_memo( 'parses', $absolute, true );
}

/** آیا این فایل (نسبت به بستهٔ رسمی) قابلِ اعتماد است؟ */
function jluxe_template_guard_verify( string $relative ): bool {
	$absolute = JLUXE_THEME_DIR . '/' . $relative;
	if ( ! is_readable( $absolute ) ) {
		return false;
	}
	$manifest = jluxe_template_guard_manifest();
	if ( isset( $manifest[ $relative ] ) ) {
		return hash_file( 'sha256', $absolute ) === $manifest[ $relative ];
	}
	return jluxe_template_guard_parses( $absolute );
}

/** وضعیتِ یک قالبِ پوسته: `ok` (قابلِ اعتماد) یا `broken` (ناسالم). */
function jluxe_template_guard_state( string $relative ): string {
	$cached = jluxe_template_guard_memo( 'state', $relative );
	if ( null !== $cached ) {
		return (string) $cached;
	}
	return (string) jluxe_template_guard_memo( 'state', $relative, jluxe_template_guard_verify( $relative ) ? 'ok' : 'broken' );
}

/**
 * پاک‌کردنِ کشِ وضعیت (هر درخواست کشِ خودش را دارد؛ این تابع برای «بررسی
 * دوباره» بعد از بازآپلود و برای آزمون‌ها لازم است، تا نتیجهٔ قدیمیِ «خراب»
 * به درخواستِ بعدی سرایت نکند).
 */
function jluxe_template_guard_reset_cache(): void {
	$GLOBALS['jluxe_template_guard_cache_reset'] = (int) ( $GLOBALS['jluxe_template_guard_cache_reset'] ?? 0 ) + 1;
}

/** ثبتِ یک جایگزینی/خطا برای نمایش به مدیر (بدونِ نوشتنِ مکرر). */
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
 * R82 — خطای واقعیِ یک قالبِ خراب را ثبت می‌کند (پیام/فایل/خط) تا در
 * «تشخیص سایت ← آخرین خطاهای کشندهٔ PHP» دیده شود، نه فقط در لاگِ سرور.
 * R83: همان پیام یک بار در هر درخواست به لاگِ خودِ سرور هم می‌رود تا اگر
 * پیشخوان در دسترس نبود، از cPanel/لاگ هاست هم قابلِ خواندن باشد.
 */
function jluxe_template_guard_caught( string $key, \Throwable $error, string $file ): void {
	$logged = (array) ( $GLOBALS['jluxe_template_guard_logged'] ?? array() );
	if ( ! in_array( $key, $logged, true ) ) {
		$logged[] = $key;
		$GLOBALS['jluxe_template_guard_logged'] = $logged;
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf( 'JLUXE template error [%s] %s: %s in %s:%d', $key, get_class( $error ), $error->getMessage(), $file, $error->getLine() )
		);
	}
	jluxe_template_guard_record( $key, 'error' );
	if ( function_exists( 'jluxe_record_php_fatal' ) ) {
		jluxe_record_php_fatal(
			array(
				'type'    => E_ERROR,
				'message' => 'قالبِ پوسته وسطِ رندر خطا داد (' . get_class( $error ) . '): ' . $error->getMessage(),
				'file'    => $file,
				'line'    => (int) $error->getLine(),
			)
		);
	}
}

/**
 * R83 — خطاهای ثبت‌شده‌ای که از رندرِ قالب‌ها آمده‌اند (برای نمایشِ فوری در
 * اخطارِ پیشخوان و در گزارشِ آمادهٔ کپی). پیامِ واقعیِ PHP همان چیزی است که
 * تعیین می‌کند کدام خطِ کد مشکل دارد.
 */
function jluxe_template_guard_recent_errors( int $limit = 3 ): array {
	if ( ! function_exists( 'jluxe_recent_php_fatals' ) ) {
		return array();
	}
	$found = array();
	foreach ( jluxe_recent_php_fatals() as $entry ) {
		if ( false === strpos( (string) ( $entry['message'] ?? '' ), 'قالبِ پوسته وسطِ رندر خطا داد' ) ) {
			continue;
		}
		$found[] = $entry;
		if ( count( $found ) >= $limit ) {
			break;
		}
	}
	return $found;
}

/* -------------------------------------------------------------------------
 * انتخابِ مسیرِ سالم
 * ---------------------------------------------------------------------- */

/** مسیرِ نسبیِ یک قالبِ پوسته، فقط اگر در دامنهٔ محافظ باشد؛ وگرنه ''. */
function jluxe_template_guard_relative( string $absolute ): string {
	$theme_root = trailingslashit( JLUXE_THEME_DIR );
	if ( 0 !== strpos( $absolute, $theme_root ) ) {
		return '';
	}
	$relative = ltrim( substr( $absolute, strlen( $theme_root ) ), '/' );
	if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
		return '';
	}
	if ( 0 !== strpos( $relative, 'woocommerce/' ) && false !== strpos( $relative, '/' ) ) {
		return ''; // قالب‌های ریشهٔ پوسته یا قالب‌های ووکامرسیِ پوسته — نه فایل‌های inc/.
	}
	return $relative;
}

/** کپیِ پشتیبانِ تأییدشدهٔ همین پوسته (یا '' اگر نباشد/تأیید نشود). */
function jluxe_template_guard_spare( string $relative ): string {
	$spare = JLUXE_TEMPLATE_FALLBACK_DIR . '/' . $relative;
	$abs   = JLUXE_THEME_DIR . '/' . $spare;
	if ( $relative === $spare || ! is_readable( $abs ) ) {
		return '';
	}
	return jluxe_template_guard_verify( $spare ) ? $abs : '';
}

/** قالبِ پیش‌فرضِ خودِ ووکامرس برای همان نام (یا '' اگر نباشد). */
function jluxe_template_guard_default( string $template_name ): string {
	if ( '' === $template_name || ! function_exists( 'WC' ) || ! WC() || ! method_exists( WC(), 'plugin_path' ) ) {
		return '';
	}
	$default = trailingslashit( WC()->plugin_path() ) . 'templates/' . ltrim( $template_name, '/' );
	return is_readable( $default ) ? $default : '';
}

/**
 * زنجیرهٔ اجرا برای یک قالب: `first` همان چیزی است که اجرا می‌شود و `spare`
 * تلاشِ دوم است (اگر `first` وسطِ رندر خطا بدهد). `mode` هم برای ثبت است.
 */
function jluxe_template_guard_chain( string $absolute, string $template_name ): array {
	$relative = jluxe_template_guard_relative( $absolute );
	if ( '' === $relative ) {
		return array( 'first' => $absolute, 'spare' => '', 'mode' => '' );
	}
	$default = jluxe_template_guard_default( $template_name );
	$spare   = jluxe_template_guard_spare( $relative );
	if ( 'ok' === jluxe_template_guard_state( $relative ) ) {
		return array( 'first' => $absolute, 'spare' => $default, 'mode' => '' );
	}
	if ( '' !== $spare ) {
		return array( 'first' => $spare, 'spare' => $default, 'mode' => 'fallback' );
	}
	if ( '' !== $default ) {
		return array( 'first' => $default, 'spare' => '', 'mode' => 'woocommerce' );
	}
	return array( 'first' => $absolute, 'spare' => '', 'mode' => '' ); // رفتارِ معمولِ وردپرس
}

/** مسیرِ جایگزینِ ساده (بدونِ اجراکنندهٔ امن) — برای مسیرهایی که include فوری نیست. */
function jluxe_template_guard_safe( string $absolute, string $template_name ): string {
	$chain = jluxe_template_guard_chain( $absolute, $template_name );
	if ( '' !== $chain['mode'] && $chain['first'] !== $absolute ) {
		jluxe_template_guard_record( jluxe_template_guard_relative( $absolute ), $chain['mode'] );
	}
	return $chain['first'];
}

/**
 * مسیرِ امنِ اجرا: قالب از `inc/template-guard-include.php` اجرا می‌شود تا
 * خطاهای زمانِ اجرا هم صفحه را نخوابانند. اگر خودِ اجراکننده ناقص/غیرقابلِ
 * اعتماد باشد، به مسیرِ ساده برمی‌گردیم (محافظ هیچ‌وقت خودش ریسک نمی‌سازد).
 */
function jluxe_template_guard_safe_loaded( string $absolute, string $template_name ): string {
	$chain = jluxe_template_guard_chain( $absolute, $template_name );
	if ( '' !== $chain['mode'] && $chain['first'] !== $absolute ) {
		jluxe_template_guard_record( jluxe_template_guard_relative( $absolute ), $chain['mode'] );
	}
	$loader = JLUXE_THEME_DIR . '/' . JLUXE_TEMPLATE_GUARD_LOADER;
	if ( ! is_readable( $loader ) || ! jluxe_template_guard_parses( $loader ) ) {
		return $chain['first'];
	}
	$GLOBALS['jluxe_template_guard_slot'] = array(
		'first' => $chain['first'],
		'spare' => ( $chain['spare'] === $chain['first'] ) ? '' : $chain['spare'],
		'key'   => jluxe_template_guard_relative( $absolute ),
		't'     => microtime( true ),
	);
	return $loader;
}

/* -------------------------------------------------------------------------
 * فیلترهای واقعیِ include
 * ---------------------------------------------------------------------- */

/** آیا محافظ روشن است؟ (قابلِ خاموش‌کردن با فیلتر) */
function jluxe_template_guard_on(): bool {
	return (bool) apply_filters( 'jluxe_template_guard_enabled', true );
}

/**
 * فیلترِ رسمیِ ووکامرس روی محلِ قالب (`wc_locate_template`) — برای
 * `wc_get_template()`؛ در مسیرِ کش‌شده اجرا نمی‌شود، پس تنها پوشش نیست.
 */
function jluxe_template_guard_locate( $template, $template_name, $template_path ) {
	if ( ! jluxe_template_guard_on() || ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	return jluxe_template_guard_safe( $template, (string) $template_name );
}

/**
 * R82 — تنها فیلتری که `wc_get_template_part()` صدا می‌زند و کارتِ محصول
 * (`content-product.php`) از همین مسیر می‌آید. عدمِ پوششِ همین فیلتر در
 * 1.69.1 باعث شد محافظ روی سایتِ خراب هیچ‌وقت اجرا نشود.
 */
function jluxe_template_guard_part( $template, $slug, $name ) {
	if ( ! jluxe_template_guard_on() || ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	$template_name = (string) $slug . ( '' !== (string) $name ? '-' . (string) $name : '' ) . '.php';
	return jluxe_template_guard_safe_loaded( $template, $template_name );
}

/**
 * R82 — فیلترِ نهاییِ `wc_get_template()`؛ حتی وقتی نتیجهٔ locate از کشِ
 * آبجکت آمده و `woocommerce_locate_template` اجرا نشده، این فیلتر می‌رسد.
 */
function jluxe_template_guard_get_template( $template, $template_name, $args, $template_path, $default_path ) {
	if ( ! jluxe_template_guard_on() || ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	return jluxe_template_guard_safe( $template, (string) $template_name );
}

/**
 * همان محافظ برای قالبِ اصلیِ خودِ وردپرس (front-page.php، single.php،
 * archive-product.php، ...) — این‌ها را وردپرس با `template_include` انتخاب
 * می‌کند و اگر یکی‌شان ناقص آپلود شده باشد، همان صفحه (و فقط همان صفحه)
 * می‌افتد. مسیرِ سالم در همان آینهٔ کپی‌ها هست.
 */
function jluxe_template_guard_include( $template ) {
	if ( ! jluxe_template_guard_on() || ! is_string( $template ) || '' === $template ) {
		return $template;
	}
	$relative = jluxe_template_guard_relative( $template );
	if ( '' === $relative || false !== strpos( $relative, '/' ) ) {
		return $template; // فقط قالب‌های ریشهٔ پوسته.
	}
	return jluxe_template_guard_safe_loaded( $template, $relative );
}

add_filter( 'wc_get_template_part', 'jluxe_template_guard_part', 99, 3 );
add_filter( 'wc_get_template', 'jluxe_template_guard_get_template', 99, 5 );
add_filter( 'woocommerce_locate_template', 'jluxe_template_guard_locate', 99, 3 );
add_filter( 'template_include', 'jluxe_template_guard_include', 99 );

/* -------------------------------------------------------------------------
 * اخطارِ پیشخوان
 * ---------------------------------------------------------------------- */

/**
 * اخطارِ پیشخوان: کدام قالب‌ها ناسالم‌اند و کدام مسیر جایگزین شده است.
 * عمداً «قابلِ‌ردکردن» نیست — تا وقتی فایل‌ها بازآپلود نشوند این وضعیت
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
	$labels = array(
		'fallback'    => 'جایگزین‌شده با نسخهٔ پشتیبانِ سالمِ همین پوسته',
		'woocommerce' => 'جایگزین‌شده با قالبِ پیش‌فرضِ ووکامرس (نسخهٔ پشتیبانِ پوسته هم تأیید نشد)',
		'error'       => 'وسطِ رندر خطای PHP داد؛ اجراکنندهٔ امن آن را گرفت و ادامه داد',
	);
	$rows      = array();
	$has_error = false;
	$has_swap  = false;
	foreach ( $hits as $relative => $hit ) {
		$mode = (string) ( $hit['mode'] ?? '' );
		if ( 'error' === $mode ) {
			$has_error = true;
		} else {
			$has_swap = true;
		}
		$rows[] = sprintf(
			'<li><code dir="ltr">%s</code> — %s</li>',
			esc_html( (string) $relative ),
			esc_html( $labels[ $mode ] ?? 'مسیرِ جایگزین به‌کار رفت' )
		);
	}
	// R83: پیامِ واقعیِ PHP هم همان‌جا نشان داده می‌شود تا برای تشخیصِ خطِ
	// دقیقِ کد لازم نباشد مدیر صفحهٔ دیگری را باز کند.
	$details = '';
	$errors  = jluxe_template_guard_recent_errors( 3 );
	if ( ! empty( $errors ) ) {
		$rows_error = array();
		foreach ( $errors as $entry ) {
			$rows_error[] = sprintf(
				'<li><code dir="ltr">%s</code> — <code dir="ltr">%s:%s</code></li>',
				esc_html( mb_substr( (string) ( $entry['message'] ?? '' ), 0, 300 ) ),
				esc_html( (string) ( $entry['file'] ?? '' ) ),
				esc_html( (string) ( $entry['line'] ?? 0 ) )
			);
		}
		$details = '<p><strong>پیامِ واقعیِ خطای PHP (فایل:خط):</strong></p><ul style="list-style:disc;margin-inline-start:20px">' . implode( '', $rows_error ) . '</ul>';
	}
	/*
	 * R84 — نشانه‌های «فایلِ کهنه»: اگر گزارشِ کش‌شدهٔ سلامتِ فایل‌ها فایلِ
	 * ناشناسی روی سرور دیده باشد، یا اسکنِ سازگاری فراخوانیِ تابعِ
	 * تعریف‌نشده پیدا کرده باشد، همان‌جا گفته می‌شود — چون این دقیقاً همان
	 * علتی است که در نسخهٔ 1.68.0 فروشگاه را خواباند و ریشه‌اش جای دیگری
	 * (فایلِ باقی‌مانده روی سرور) است، نه در نسخهٔ نصب‌شده.
	 */
	$hints = '';
	if ( function_exists( 'jluxe_theme_integrity_cached' ) ) {
		$cached_files = jluxe_theme_integrity_cached();
		$extra_php    = (array) ( $cached_files['extra_php'] ?? array() );
		if ( ! empty( $extra_php ) ) {
			$hints .= sprintf(
				'<li>فایل‌هایی روی سرور هستند که جزو بستهٔ رسمی نیستند (احتمالاً باقی‌ماندهٔ نسخهٔ قدیمی): <code dir="ltr">%s</code></li>',
				esc_html( implode( ', ', array_slice( $extra_php, 0, 3 ) ) )
			);
		}
	}
	if ( function_exists( 'jluxe_compat_scan_cached' ) ) {
		$cached_scan = jluxe_compat_scan_cached();
		$missing_fn  = (array) ( $cached_scan['missing'] ?? array() );
		if ( ! empty( $missing_fn ) ) {
			$hints .= sprintf(
				'<li>فراخوانیِ تابعی که در پوسته وجود ندارد: <code dir="ltr">%s()</code> — این‌ها هم با نصبِ کاملِ بستهٔ رسمی از بین می‌روند.</li>',
				esc_html( implode( '(), ', array_slice( array_keys( $missing_fn ), 0, 3 ) ) )
			);
		}
	}
	if ( '' !== $hints ) {
		$details .= '<p><strong>نشانه‌های «فایلِ کهنه» روی سرور:</strong></p><ul style="list-style:disc;margin-inline-start:20px">' . $hints . '</ul>';
	}
	$headline = ( $has_error && ! $has_swap )
		? 'پوستهٔ زرین: یک قالبِ پوسته وسطِ رندر خطای PHP داد. خطا گرفته شد و فروشگاه نخوابید، ولی این خطا باید رفع شود.'
		: 'پوستهٔ زرین: فایل‌های قالب با بستهٔ رسمی یکسان نیستند. این معمولاً یعنی آپلود/نصبِ پوسته کامل نشده و فایل‌ها بریده‌اند — همان چیزی که می‌تواند کلِ حلقهٔ محصولات را از کار بیندازد. برای اینکه فروشگاه نخوابد، این فایل‌ها موقتاً کنار گذاشته شده‌اند:';
	printf(
		'<div class="notice notice-error"><p><strong>%s</strong></p><ul style="list-style:disc;margin-inline-start:20px">%s</ul>%s<p><a class="button button-primary" href="%s">بررسی و فهرستِ کاملِ فایل‌های ناسالم</a> — بستهٔ رسمی را دوباره و کامل نصب کنید.</p></div>',
		esc_html( $headline ),
		implode( '', $rows ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — هر ردیف بالا esc شده است.
		$details, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — هر ردیف بالا esc شده است.
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
