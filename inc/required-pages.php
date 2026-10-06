<?php
/** Optional, administrator-triggered setup. Never publish new pages during a frontend request. */

defined( 'ABSPATH' ) || exit;

/**
 * نگاشتِ اسلاگ → عنوانِ فارسیِ برگه. عمداً «سبد خرید»/«تسویه‌حساب»/
 * «حساب کاربری» (page-cart.php/page-checkout.php/page-my-account.php)
 * این‌جا نیستن — این سه، برگه‌های خودِ ووکامرسن (با فعال‌سازیِ ووکامرس
 * خودکار ساخته می‌شن؛ ساختنِ دوباره‌شون این‌جا فقط یک برگه‌ی تکراری با
 * اسلاگِ «-2» می‌ساخت که هیچ‌وقت با تمپلیتِ درست match نمی‌شد).
 */
function jluxe_required_pages_map(): array {
	return array(
		'about-us'                     => 'درباره ما',
		'contact-us'                   => 'تماس با ما',
		'faq'                          => 'سوالات متداول',
		'jluxe-help-center'            => 'مرکز راهنمایی',
		'payment-guide'                => 'روش‌ها و راهنمای پرداخت سفارشات',
		'returns-and-exchanges'        => 'رویه‌ی شرایط مرجوعی و تعویض کالا',
		'shipping-and-order-tracking'  => 'روش‌های ارسال و راهنمای پیگیری سفارشات',
		'shopping-guide'               => 'راهنمای گام‌به‌گام خرید',
		'track-order'                  => 'پیگیری سفارش',
	);
}


/** Create only missing drafts; existing content and publication status remain untouched. */
function jluxe_ensure_required_pages(): bool {
	if ( ! current_user_can( 'manage_options' ) ) { return false; }
	$success = true;
	foreach ( jluxe_required_pages_map() as $slug => $title ) {
		if ( get_page_by_path( $slug, OBJECT, 'page' ) instanceof WP_Post ) { continue; }
		$result = wp_insert_post( array(
			'post_type' => 'page', 'post_status' => 'draft',
			'post_title' => $title, 'post_name' => $slug, 'post_content' => '',
		), true );
		if ( ! $result || is_wp_error( $result ) ) { $success = false; }
	}
	return $success;
}

const JLUXE_REQUIRED_PAGES_VERSION = '1.1.0';

function jluxe_required_pages_notice(): void {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'jluxe_required_pages_version' ) === JLUXE_REQUIRED_PAGES_VERSION ) { return; }
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=jluxe_create_required_pages' ), 'jluxe_create_required_pages' );
	printf( '<div class="notice notice-info"><p>راه‌اندازی اختیاری زرین: برگه‌های راهنما و پیگیریِ ناموجود را به‌صورت پیش‌نویس بسازید، سپس محتوای آن‌ها را بازبینی و منتشر کنید. برگه‌های موجود تغییر نمی‌کنند. <a href="%s">ایجاد پیش‌نویس‌ها / تأیید برگه‌های موجود</a></p></div>', esc_url( $url ) );
}
add_action( 'admin_notices', 'jluxe_required_pages_notice' );

function jluxe_create_required_pages_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.' ); }
	check_admin_referer( 'jluxe_create_required_pages' );
	$success = jluxe_ensure_required_pages();
	if ( $success ) { update_option( 'jluxe_required_pages_version', JLUXE_REQUIRED_PAGES_VERSION, false ); }
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'page', 'jluxe_pages_setup' => $success ? 'success' : 'error' ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_jluxe_create_required_pages', 'jluxe_create_required_pages_action' );
