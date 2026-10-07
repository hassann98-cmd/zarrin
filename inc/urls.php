<?php
/** Server-generated routes; custom account links are optional, never hard-coded in JavaScript. */
defined( 'ABSPATH' ) || exit;

/** Resolve a site-relative navigation URL without losing or doubling a subdirectory prefix. */
/**
 * مقصدِ واقعیِ «بلاگ» — نگاشتِ R56 که حالا (R59) مشترک شد: برگهٔ نوشته‌ها →
 * برگهٔ منتشرشدهٔ blog → خانه (بایگانیِ پیش‌فرضِ نوشته‌ها) — هرگز ۴۰۴.
 * مصرف‌کنندگان: resolverِ لینک‌های منو، مسیرِ راهنمای single.php و لینک‌های
 * تنظیماتِ صفحهٔ اصلی.
 */
function jluxe_blog_url(): string {
	$posts_pid = (int) get_option( 'page_for_posts' );
	if ( $posts_pid && 'publish' === get_post_status( $posts_pid ) ) { return (string) get_permalink( $posts_pid ); }
	$blog_page = get_page_by_path( 'blog', OBJECT, 'page' );
	if ( $blog_page && 'publish' === $blog_page->post_status ) { return (string) get_permalink( $blog_page ); }
	return home_url( '/' );
}

function jluxe_resolve_site_link( string $url ): string {
	if ( '' === $url || '/' !== $url[0] || 0 === strpos( $url, '//' ) ) { return $url; }
	$home = home_url( '/' );
	$home_path = (string) wp_parse_url( $home, PHP_URL_PATH );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( '/' !== $home_path && 0 === strpos( $path . '/', rtrim( $home_path, '/' ) . '/' ) ) {
		$origin = wp_parse_url( $home );
		return $origin['scheme'] . '://' . $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' ) . $url;
	}
	/*
	 * R55/R56 — باگِ واقعیِ گزارش‌شده («لینک‌های منوی هدر ارور ۴۰۴ می‌دهند»):
	 * آیتم‌های منو اسلاگِ خام ذخیره می‌کنند (/shop/، /blog/، /faq/ و…) و
	 * این‌جا فقط قدرمطلق می‌شد، بدونِ هیچ اعتبارسنجیِ مقصد. سه شکلِ ۴۰۴:
	 * ① «بلاگ» در وردپرس اصلاً /blog/ پیش‌فرض ندارد (بسته به show_on_front
	 * بایگانیِ نوشته‌ها همان خانه است یا برگهٔ نوشته‌ها)؛
	 * ② «فروشگاه» اگر اسلاگِ برگهٔ فروشگاه عوض شده باشد؛
	 * ③ برگه‌های راهنما وقتی permalink واقعی‌شان با اسلاگِ ذخیره‌شده فرق دارد.
	 * نگاشتِ آگاهانه: فروشگاه/پیگیری از مسیرِ رسمیِ خودشان (jluxe_route_url)،
	 * بلاگ از برگهٔ نوشته‌ها → برگهٔ منتشرشدهٔ blog → خانه (هرگز ۴۰۴)، و هر
	 * اسلاگِ تکیِ دیگر اگر برگهٔ منتشرشده‌ای با همان اسلاگ هست به permalink
	 * واقعی‌اش. صفحاتِ «پیش‌نویس» عمداً ریدایرکت نمی‌شوند — انتشارشان بخشی از
	 * جریانِ راه‌اندازیِ ادمین است (notice برگه‌های موردنیاز).
	 */
	$slug = trim( rawurldecode( $path ), '/' );
	if ( '' !== $slug && false === strpos( $slug, '/' ) ) {
		if ( preg_match( '/^(shop|فروشگاه)$/u', $slug ) ) {
			$shop_url = jluxe_route_url( 'shop' );
			if ( '' !== $shop_url ) { return $shop_url; }
		}
		if ( preg_match( '/^(blog|وبلاگ)$/u', $slug ) ) {
			return jluxe_blog_url();
		}
		if ( 'track-order' === $slug ) {
			$track_url = jluxe_route_url( 'track_order' );
			if ( '' !== $track_url ) { return $track_url; }
		}
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && 'publish' === $page->post_status ) { return (string) get_permalink( $page ); }
	}
	return home_url( $url );
}


function jluxe_route_url( string $key ): string {
	$home = home_url( '/' );
	$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
	$orders = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : $account;
	$defaults = array(
		'home' => $home,
		'shop' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : $home,
		'login' => $account,
		'dashboard' => $account,
		'orders' => $orders,
		'cart' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $home,
		'checkout' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $home,
		'track_order' => home_url( '/track-order/' ),
		'thankyou_orders' => is_user_logged_in() ? $orders : home_url( '/track-order/' ),
		'lost_password' => function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url(),
	);
	$settings = jluxe_get_theme_settings()['urls'];
	$custom = isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ? trim( $settings[ $key ] ) : '';
	if ( '' === $custom ) {
		if ( 'thankyou_orders' === $key ) {
			return jluxe_route_url( is_user_logged_in() ? 'orders' : 'track_order' );
		}
		return $defaults[ $key ] ?? $home;
	}
	$custom = jluxe_resolve_site_link( $custom );
	$url = esc_url_raw( $custom, array( 'http', 'https' ) );
	return $url ?: ( $defaults[ $key ] ?? $home );
}

function jluxe_public_urls(): array {
	$out = array();
	foreach ( array( 'home', 'shop', 'login', 'dashboard', 'orders', 'cart', 'checkout', 'track_order', 'thankyou_orders', 'lost_password' ) as $key ) {
		$out[ $key ] = jluxe_route_url( $key );
	}
	return $out;
}
