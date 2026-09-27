<?php
/**
 * R84 — «بررسیِ سازگاری»: پیدا کردنِ دقیقاً همان چیزی که سایت را خواباند.
 *
 * چه اتفاقی افتاده بود: در نسخهٔ 1.68.0 همراهِ اصلاح‌های سرعت، دو تابعِ
 * عمومیِ پوسته (`jluxe_render_product_stock_line` و
 * `jluxe_product_discount_percent`) حذف شده بودند. خودِ پوسته دیگر آن‌ها را
 * صدا نمی‌زد، ولی فایلِ کهنه‌ای که روی سرور باقی مانده بود هنوز صدا می‌زد؛
 * نتیجه: «تابعِ تعریف‌نشده» وسطِ رندرِ کارتِ محصول و «خطای مهم» در فروشگاه.
 *
 * از 1.69.0 خودِ آن دو تابع برگشتند و از 1.69.2 محافظِ قالب هم چنین خطایی
 * را می‌گیرد. این فایل لایهٔ سوم است: **قبل از وقوع** خبر می‌دهد. با
 * `token_get_all` (بدونِ اجرای هیچ کدی) همهٔ فایل‌های PHP پوسته خوانده
 * می‌شوند و:
 *   ۱) هر فراخوانیِ `jluxe_*()` که هیچ‌جا در پوسته تعریف نشده گزارش می‌شود
 *      (همان الگوی خرابیِ 1.68.0),
 *   ۲) فایل‌هایی که روی سرور هستند ولی در بستهٔ رسمی نیستند (باقی‌ماندهٔ
 *      نسخه‌های قدیمی یا فایلِ دستی‌ساز) — منبعِ اصلیِ همین خرابی‌ها.
 *
 * مرزِ صادقانه: این ابزار فقط «کدِ پوسته» را می‌سنجد. اگر افزونه‌ای تابعی
 * را صدا بزند که وجود ندارد، این‌جا دیده نمی‌شود؛ آن حالت را محافظِ قالب
 * در لحظهٔ رندر می‌گیرد و در «آخرین خطاهای کشندهٔ PHP» ثبت می‌کند.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_COMPAT_SCAN_TRANSIENT = 'jluxe_compat_scan';
const JLUXE_COMPAT_SCAN_LIMIT     = 900;

/**
 * فهرستِ فایل‌های PHP پوسته (پیمایشِ سادهٔ بازگشتی؛ عمداً بدونِ SPL تا در
 * همهٔ نسخه‌های PHP و روی همهٔ هاست‌ها یکسان کار کند). پوشه‌های حجیم/بی‌ربط
 * مثل node_modules، .git و artifacts کنار گذاشته می‌شوند.
 */
function jluxe_compat_scan_files( string $dir = '' ): array {
	$dir = '' !== $dir ? $dir : ( defined( 'JLUXE_THEME_DIR' ) ? JLUXE_THEME_DIR : '' );
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return array();
	}
	// پوشه‌های ابزاری/کش هرگز جزو بستهٔ پوسته نیستند (و فایلِ کهنهٔ پوسته هم
	// داخلِ آن‌ها پنهان نمی‌شود) — پس اسکن و «فایلِ اضافه» هر دو نادیده می‌گیرند.
	$skip  = array( 'node_modules', '.git', '.github', 'artifacts', 'dist', 'vendor', '.cache', '.arena', 'coverage' );
	$files = array();
	$walk  = array( rtrim( $dir, '/' ) );
	while ( ! empty( $walk ) && count( $files ) < JLUXE_COMPAT_SCAN_LIMIT ) {
		$current = array_pop( $walk );
		$entries = @scandir( $current ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $entries ) ) {
			continue;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $current . '/' . $entry;
			if ( is_dir( $path ) ) {
				if ( ! in_array( $entry, $skip, true ) ) {
					$walk[] = $path;
				}
				continue;
			}
			if ( 'php' !== strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}
			if ( filesize( $path ) > 512 * 1024 ) { // فایلِ غول‌پیکر = احتمالاً build است، نه کدِ پوسته.
				continue;
			}
			$files[] = $path;
		}
	}
	sort( $files );
	return $files;
}

/** مسیرِ نسبی نسبت به پوشهٔ پوسته (برای گزارش‌های خوانا). */
function jluxe_compat_relative( string $path ): string {
	$dir = defined( 'JLUXE_THEME_DIR' ) ? rtrim( JLUXE_THEME_DIR, '/' ) : '';
	return '' !== $dir ? ltrim( str_replace( $dir, '', $path ), '/' ) : $path;
}

/**
 * اسکنِ کامل با `token_get_all`: چه توابعی تعریف شده‌اند و چه فراخوانی‌هایی
 * تعریفِ متناظر ندارند. نتیجه ۶ ساعت کش می‌شود (هزینه‌اش: خواندنِ ~۱۳۰ فایل
 * فقط یک بار).
 */
function jluxe_compat_scan( bool $refresh = false ): array {
	static $result = null;
	if ( is_array( $result ) && ! $refresh ) {
		return $result;
	}
	if ( ! function_exists( 'token_get_all' ) ) {
		return array(
			'available'  => false,
			'files'      => 0,
			'defined'    => 0,
			'missing'    => array(),
			'guarded'    => array(),
			'extra_php'  => array(),
			'at'         => time(),
		);
	}
	if ( ! $refresh && function_exists( 'get_transient' ) ) {
		$cached = get_transient( JLUXE_COMPAT_SCAN_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['at'] ) ) {
			$result = $cached;
			return $result;
		}
	}
	$files   = jluxe_compat_scan_files();
	$defined = array();
	$calls   = array(); // name => [ ['file:line', offset] ]
	$guards  = array(); // name => [ ['offset', 'negated'] ]  از function_exists(...)
	foreach ( $files as $file ) {
		$code = (string) @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $code ) {
			continue;
		}
		$tokens = @token_get_all( $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $tokens ) ) {
			continue;
		}
		$relative = jluxe_compat_relative( $file );
		$count    = count( $tokens );
		$offset   = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				$offset += strlen( (string) $token );
				continue;
			}
			$text         = (string) $token[1];
			$token_offset = $offset;
			$offset      += strlen( $text );
			if ( T_STRING !== $token[0] ) {
				continue;
			}
			/**
			 * توکنِ معنادارِ بعدی/قبلی — کامنت و فاصله نادیده گرفته می‌شود.
			 */
			$significant = static function ( int $from, int $step ) use ( $tokens, $count ) {
				for ( $j = $from; $j >= 0 && $j < $count; $j += $step ) {
					$t = $tokens[ $j ];
					if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					return $t;
				}
				return null;
			};
			// `function_exists( 'name' )` — برای تشخیصِ فراخوانیِ محافظت‌شده.
			if ( 'function_exists' === $text ) {
				$open = $significant( $i + 1, 1 );
				if ( ! is_string( $open ) || '(' !== $open ) {
					continue;
				}
				$arg = $significant( $i + 2, 1 );
				if ( ! is_array( $arg ) || T_CONSTANT_ENCAPSED_STRING !== $arg[0] ) {
					continue;
				}
				$guard_name = trim( (string) $arg[1], "'\"" );
				if ( 0 !== strpos( $guard_name, 'jluxe_' ) ) {
					continue;
				}
				$before  = $significant( $i - 1, -1 );
				$negated = is_string( $before ) && '!' === $before;
				$guards[ $guard_name ][] = array( 'offset' => $token_offset, 'negated' => $negated );
				continue;
			}
			if ( 0 !== strpos( $text, 'jluxe_' ) ) {
				continue;
			}
			$name = $text;
			// توکنِ معنادارِ قبلی: اگر `function` باشد، این «تعریف» است نه فراخوانی.
			$prev = $significant( $i - 1, -1 );
			if ( is_array( $prev ) && T_FUNCTION === $prev[0] ) {
				$defined[ $name ] = true;
				continue;
			}
			// متدِ شیء/کلاس (`->` و `::`) کارِ خودِ آن کلاس است، نه تابعِ پوسته.
			if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW ), true ) ) {
				continue;
			}
			// فقط فراخوانی: توکنِ بعدیِ معنادار باید «(» باشد.
			$next = $significant( $i + 1, 1 );
			if ( ! is_string( $next ) || '(' !== $next ) {
				continue;
			}
			$line           = (int) ( $token[2] ?? 0 );
			$calls[ $name ][] = array( $relative . ':' . $line, $token_offset );
		}
	}
	$missing = array();
	$guarded = array();
	foreach ( $calls as $name => $places ) {
		if ( isset( $defined[ $name ] ) ) {
			continue;
		}
		foreach ( $places as $place ) {
			[ $where, $call_offset ] = $place;
			$protected          = false;
			$called_when_missing = false;
			foreach ( $guards[ $name ] ?? array() as $guard ) {
				if ( $guard['offset'] >= $call_offset ) {
					continue;
				}
				if ( $guard['negated'] ) {
					$called_when_missing = true; // `if ( ! function_exists( 'x' ) ) { x(); }`
				} else {
					$protected = true; // `if ( function_exists( 'x' ) ) { x(); }`
				}
			}
			if ( $called_when_missing || ! $protected ) {
				$missing[ $name ][] = $where;
			} else {
				$guarded[ $name ][] = $where;
			}
		}
	}
	$missing = array_map( 'array_values', array_map( 'array_unique', $missing ) );
	$guarded = array_map( 'array_values', array_map( 'array_unique', $guarded ) );
	ksort( $missing );
	ksort( $guarded );
	$result = array(
		'available' => true,
		'files'     => count( $files ),
		'defined'   => count( $defined ),
		'missing'   => $missing,
		'guarded'   => $guarded, // فراخوانیِ محافظت‌شده با function_exists: خطرِ خطای مهم ندارد.
		'extra_php' => array(), // با گزارشِ سلامتِ فایل‌ها پر می‌شود (نیاز به فهرستِ رسمی دارد).
		'at'        => time(),
	);
	if ( function_exists( 'set_transient' ) ) {
		set_transient( JLUXE_COMPAT_SCAN_TRANSIENT, $result, 6 * HOUR_IN_SECONDS );
	}
	return $result;
}

/**
 * فقط مقدارِ کش‌شده — در اخطارِ پیشخوان استفاده می‌شود تا بازکردنِ هر صفحهٔ
 * مدیریت، اسکنِ تازه راه نیندازد. اگر هنوز اسکن نشده باشد، چیزی نمی‌گوید.
 */
function jluxe_compat_scan_cached(): array {
	if ( ! function_exists( 'get_transient' ) ) {
		return array();
	}
	$cached = get_transient( JLUXE_COMPAT_SCAN_TRANSIENT );
	return is_array( $cached ) ? $cached : array();
}
