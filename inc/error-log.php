<?php
/**
 * R81 — ضبطِ خطاهای کشندهٔ PHP.
 *
 * چرا: وقتی یک قالب بریده/ناقص وسطِ رندر خطای نحوی می‌دهد، بازدیدکننده فقط
 * «یک خطای مهم در این وب‌سایت رخ داده است» می‌بیند و مدیر هم چیزی بیشتر
 * نمی‌بیند (وردپرس پیامِ واقعی را فقط در لاگِ سرور می‌نویسد). برای اینکه
 * بدونِ دسترسی به فایلِ لاگ هم بشود فهمید *کدام فایل و کدام خط*، این‌جا
 * آخرین خطاهای کشندهٔ PHP در یک اپشن ذخیره و در «تشخیص سایت» و
 * «سلامتِ سایت» نمایش داده می‌شوند.
 *
 * مرزِ صادقانه: این ابزار پیامِ خطا را «نشان می‌دهد»، نه اینکه خطا را رفع
 * کند؛ خطاهایی که پیش از بارگذاری این فایل رخ دهند هم ثبت نمی‌شوند.
 */

defined( 'ABSPATH' ) || exit;

/** گزینه‌ای که آخرین خطاهای کشنده در آن نگه داشته می‌شوند. */
const JLUXE_FATALS_OPTION = 'jluxe_php_fatals';

/** نوع‌های خطایی که «کشنده» حساب می‌شوند (خطای نحویِ قالب هم E_PARSE است). */
function jluxe_fatal_error_types(): array {
	return array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
}

/** ثبتِ خطای جاریِ PHP (اگر کشنده باشد) در اپشن — در پایانِ هر درخواست. */
function jluxe_capture_php_fatal(): void {
	$error = error_get_last();
	if ( ! is_array( $error ) ) {
		return;
	}
	jluxe_record_php_fatal( $error );
}

/**
 * ثبتِ یک خطا در فهرست (جدا از error_get_last تا مستقل قابلِ آزمون باشد).
 * ورودی همان ساختارِ error_get_last: type/message/file/line.
 */
function jluxe_record_php_fatal( array $error ): bool {
	if ( ! isset( $error['type'] ) ) {
		return false;
	}
	if ( ! in_array( (int) $error['type'], jluxe_fatal_error_types(), true ) ) {
		return false;
	}
	if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
		return false;
	}

	$message = isset( $error['message'] ) ? (string) $error['message'] : '';
	$file    = isset( $error['file'] ) ? str_replace( ABSPATH, '', (string) $error['file'] ) : '';
	$line    = isset( $error['line'] ) ? (int) $error['line'] : 0;

	try {
		$log = get_option( JLUXE_FATALS_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		// خطای تکراری (همان فایل و خط) فقط شمارشش بالا می‌رود تا فهرست پر نشود.
		foreach ( $log as $index => $entry ) {
			if ( ( $entry['file'] ?? '' ) === $file && (int) ( $entry['line'] ?? 0 ) === $line && ( $entry['message'] ?? '' ) === $message ) {
				++$log[ $index ]['count'];
				$log[ $index ]['time'] = time();
				$reordered = array( $log[ $index ] );
				unset( $log[ $index ] );
				$log = array_merge( $reordered, array_values( $log ) );
				update_option( JLUXE_FATALS_OPTION, array_slice( $log, 0, 10 ), false );
				return true;
			}
		}
		array_unshift(
			$log,
			array(
				'time'    => time(),
				'type'    => (int) $error['type'],
				'message' => mb_substr( $message, 0, 400 ),
				'file'    => $file,
				'line'    => $line,
				'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? mb_substr( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), 0, 200 ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'count'   => 1,
			)
		);
		update_option( JLUXE_FATALS_OPTION, array_slice( $log, 0, 10 ), false );
		return true;
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch — در زمانِ خطای کشنده نباید چیزِ دیگری خراب شود.
		return false;
	}
}
register_shutdown_function( 'jluxe_capture_php_fatal' );

/** برچسبِ خوانا برای نوعِ خطا. */
function jluxe_fatal_type_label( int $type ): string {
	$labels = array(
		E_ERROR             => 'خطای کشنده (E_ERROR)',
		E_PARSE             => 'خطای نحوی (E_PARSE)',
		E_CORE_ERROR        => 'خطای هستهٔ PHP',
		E_COMPILE_ERROR     => 'خطای کامپایل (E_COMPILE_ERROR)',
		E_USER_ERROR        => 'خطای کشندهٔ کد',
		E_RECOVERABLE_ERROR => 'خطای بازیافت‌پذیر',
	);
	return $labels[ $type ] ?? ( 'نوع ' . $type );
}

/** خطاهای کشندهٔ ثبت‌شده (تازه‌ترین اول). */
function jluxe_recent_php_fatals(): array {
	$log = get_option( JLUXE_FATALS_OPTION, array() );
	return is_array( $log ) ? $log : array();
}

/** پاک‌کردنِ فهرست (با کلیکِ مدیر، admin-post + nonce). */
function jluxe_clear_php_fatals_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی نیست.', 'jluxe' ) );
	}
	check_admin_referer( 'jluxe_clear_php_fatals' );
	delete_option( JLUXE_FATALS_OPTION );
	wp_safe_redirect( admin_url( 'themes.php?page=jluxe-site-diagnosis&jluxe_fatals=cleared' ) );
	exit;
}
add_action( 'admin_post_jluxe_clear_php_fatals', 'jluxe_clear_php_fatals_action' );

/** آزمونِ «سلامتِ سایت»: خطای کشندهٔ تازه یعنی سایت واقعاً مشکل دارد. */
function jluxe_site_health_recent_fatals(): array {
	$log     = jluxe_recent_php_fatals();
	$recent  = array_filter(
		$log,
		static fn( $entry ) => ( time() - (int) ( $entry['time'] ?? 0 ) ) < DAY_IN_SECONDS
	);
	$badge   = array( 'label' => 'پوستهٔ زرین', 'color' => 'blue' );
	$example = '';
	if ( ! empty( $recent ) ) {
		$first   = reset( $recent );
		$example = sprintf(
			'<p><code dir="ltr">%s</code> — %s:%s</p>',
			esc_html( mb_substr( (string) ( $first['message'] ?? '' ), 0, 200 ) ),
			esc_html( (string) ( $first['file'] ?? '' ) ),
			esc_html( jluxe_fa_digits( (string) ( $first['line'] ?? 0 ) ) )
		);
	}
	if ( empty( $recent ) ) {
		return array(
			'label'       => 'خطای کشندهٔ PHP تازه‌ای ثبت نشده است',
			'status'      => 'good',
			'badge'       => $badge,
			'description' => '<p>در ۲۴ ساعتِ گذشته هیچ خطای کشنده‌ای در پوسته ثبت نشد.</p>',
			'test'        => 'jluxe_recent_fatals',
		);
	}
	return array(
		'label'       => sprintf( '%s خطای کشندهٔ PHP در ۲۴ ساعتِ گذشته', jluxe_fa_digits( (string) count( $recent ) ) ),
		'status'      => 'critical',
		'badge'       => $badge,
		'description' => '<p>این خطاها یعنی بخشی از صفحه‌ها وسطِ رندر متوقف شده‌اند. پیامِ زیر دقیقاً می‌گوید کدام فایل و کدام خط:</p>' . $example . '<p>اگر فایل زیرِ <code dir="ltr">wp-content/themes/zarrin</code> است، یعنی همان فایل روی سرور ناقص/قدیمی است؛ بستهٔ رسمی را دوباره نصب کنید (پوسته از فهرستِ SHA-256 برای تشخیص استفاده می‌کند).</p>',
		'test'        => 'jluxe_recent_fatals',
	);
}
add_filter(
	'site_status_tests',
	static function ( array $tests ): array {
		$tests['direct']['jluxe_recent_fatals'] = array(
			'label' => 'خطاهای کشندهٔ پوستهٔ زرین',
			'test'  => 'jluxe_site_health_recent_fatals',
		);
		return $tests;
	}
);
