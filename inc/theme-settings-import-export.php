<?php
/**
 * درون‌ریزی/برون‌بری/بازنشانی کامل تنظیمات JLuxe. کلید API دستیار هوش
 * مصنوعی هیچ‌وقت export نمی‌شه (در یک آپشن کاملاً جدا ذخیره‌ست، اصلاً به
 * این کد دسترسی نداره) — طبق الزام امنیتی صریح پروژه.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_handle_export(): void {
	if ( empty( $_POST['jluxe_export'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_export_settings', 'jluxe_export_nonce' );

	$settings = jluxe_get_theme_settings();
	// چیزی به‌جز jluxe_theme_settings صادر نمی‌شه — بدون کاربران/سفارش/محصول/کلید API.
	// R59 (دفاع در عمق): نام کاربری پنل پیامک هم به فایلِ خروجی نمی‌رود؛
	// کلید/رمز از قبل در آپشنِ جدا هستند و اصلاً داخلِ تنظیمات نیستند.
	if ( isset( $settings['sms'] ) && is_array( $settings['sms'] ) ) {
		$settings['sms']['username'] = '';
	}
	$payload = array(
		'jluxe_export_version' => JLUXE_SETTINGS_VERSION,
		'exported_at'           => current_time( 'mysql' ),
		'settings'              => $settings,
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="jluxe-theme-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
	echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_init', 'jluxe_handle_export' );

/**
 * اعتبارسنجی سخت‌گیرانه‌ی JSON ورودی — فقط کلیدهای شناخته‌شده‌ی
 * jluxe_theme_settings_defaults() پذیرفته می‌شن، هرکدوم از همون
 * sanitizerهای واقعی رد می‌شن (دقیقاً مثل ورودی فرم معمولی، نه اعتماد کور
 * به JSON آپلودی).
 */
function jluxe_handle_import(): ?string {
	if ( empty( $_POST['jluxe_import'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_import_settings', 'jluxe_import_nonce' );

	if ( empty( $_FILES['jluxe_import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['jluxe_import_file']['error'] ) {
		return 'import_error';
	}

	$file = $_FILES['jluxe_import_file']['tmp_name'];
	if ( ! is_uploaded_file( $file ) || filesize( $file ) > 2 * MB_IN_BYTES ) {
		return 'import_error';
	}
	$raw = file_get_contents( $file );
	if ( false === $raw || strlen( $raw ) > 2 * MB_IN_BYTES ) {
		return 'import_error';
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
		return 'import_error';
	}

	$version = (int) ( $data['jluxe_export_version'] ?? $data['settings']['version'] ?? 1 );
	if ( $version < 1 || $version > JLUXE_SETTINGS_VERSION ) {
		return 'import_error';
	}
	$incoming = $version < 2 ? jluxe_migrate_settings_v2( $data['settings'] ) : $data['settings'];
	try {
		$clean = jluxe_sanitize_settings_payload( $incoming, jluxe_get_theme_settings() );
	} catch ( Throwable $error ) {
		return 'import_error';
	}
	// One bounded, non-autoloaded backup. API credentials live in separate options and are untouched.
	update_option( 'jluxe_theme_settings_before_import', array(
		'saved_at' => current_time( 'mysql' ),
		'settings' => get_option( JLUXE_SETTINGS_OPTION, array() ),
	), false );

	if ( ! update_option( JLUXE_SETTINGS_OPTION, $clean, false ) && get_option( JLUXE_SETTINGS_OPTION ) !== $clean ) {
		return 'import_error';
	}
	jluxe_get_theme_settings( true );
	update_option( 'jluxe_theme_settings_updated_at', current_time( 'mysql' ), false );
	wp_cache_delete( 'alloptions', 'options' );

	return 'imported';
}

function jluxe_handle_full_reset(): ?string {
	if ( empty( $_POST['jluxe_reset_all'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_reset_all_settings', 'jluxe_reset_nonce' );

	update_option( JLUXE_SETTINGS_OPTION, jluxe_theme_settings_defaults(), false );
	// بازنشانی کامل یعنی کلید API هم پاک بشه — کاربر آگاهانه این دکمه رو زده.
	jluxe_set_ai_api_key( '' );
	update_option( 'jluxe_theme_settings_updated_at', current_time( 'mysql' ), false );
	wp_cache_delete( 'alloptions', 'options' );

	return 'reset';
}

/**
 * صفحه‌ی مستقلِ قبلی — طبق درخواستِ کاربر («سئو/عملکرد/CSS-JS/درون‌ریزی رو
 * یکی کن») حالا محتوای واقعیش داخلِ آکاردئونِ «درون‌ریزی / برون‌بری» در تبِ
 * ترکیبیِ jluxe_render_advanced_page() (theme-settings-render.php) رندر
 * می‌شه؛ این تابع فقط برای سالم‌موندنِ URL/capability قدیمی نگه داشته شده
 * (همون الگویِ jluxe_render_header_nav_page).
 */
function jluxe_render_import_export_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-advanced' ) );
	exit;
}
