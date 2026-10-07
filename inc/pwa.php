<?php
/**
 * R88 — PWA امن برای ووکامرس.
 *
 * چه چیزی کش می‌شود و چه چیزی نه (عمداً محافظه‌کارانه):
 *   ✓ فایل‌های هش‌دارِ build پوسته (assets/compiled — JS/CSS/فونت؛ نامشان با هر
 *     build عوض می‌شود، پس کهنه‌شدن ممکن نیست) + یک صفحهٔ آفلاینِ کوچک.
 *   ✗ هیچ HTMLی کش نمی‌شود: ناوبری همیشه از شبکه می‌آید و فقط وقتی شبکه قطع است
 *     صفحهٔ آفلاین نشان داده می‌شود. قیمت، موجودی، سبد و وضعیتِ ورود هرگز کهنه نمی‌شود.
 *   ✗ سبد، تسویه، حساب کاربری، پیگیری سفارش، wp-admin، wp-json، admin-ajax،
 *     wc-ajax، wc-api (بازگشتِ درگاه) و هر درخواستِ غیرِ GET یا از دامنهٔ دیگر
 *     اصلاً از service worker عبور نمی‌کنند (respondWith صدا زده نمی‌شود).
 *
 * خاموش‌کردن از «تنظیمات ← عملکرد» هم ثبت را متوقف می‌کند و هم service workerِ
 * قبلاً نصب‌شده را با یک نسخهٔ «خودحذف‌کن» پاک می‌کند (کش‌ها + unregister).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jluxe_pwa_enabled(): bool {
	return ! empty( jluxe_get_setting( 'performance.pwa_enabled', true ) );
}

function jluxe_pwa_home_path(): string {
	$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return '' === $path ? '/' : trailingslashit( $path );
}

/** آدرسِ نقطه‌های PWA — همه زیرِ ریشهٔ سایت تا scopeِ service worker کلِ سایت باشد. */
function jluxe_pwa_url( string $what ): string {
	return add_query_arg( 'jluxe_pwa', $what, home_url( '/' ) );
}

function jluxe_pwa_site_name(): string {
	$name = (string) jluxe_get_setting( 'identity.site_name', '' );
	if ( '' === trim( $name ) ) {
		$name = (string) get_bloginfo( 'name' );
	}
	return '' === trim( $name ) ? 'فروشگاه' : trim( wp_strip_all_tags( $name ) );
}

/**
 * آیکون‌ها: اول «آیکونِ سایت»ِ خودِ وردپرس (تنظیمات ← عمومی)؛ اگر نبود، آیکونِ
 * همراهِ پوسته (assets/pwa). کروم برای نصب حداقل 192 و 512 می‌خواهد.
 *
 * @return array<int, array<string, string>>
 */
function jluxe_pwa_icons(): array {
	if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
		$icons = array();
		foreach ( array( 192, 512 ) as $size ) {
			$url = (string) get_site_icon_url( $size );
			if ( '' !== $url ) {
				$icons[] = array( 'src' => $url, 'sizes' => $size . 'x' . $size, 'purpose' => 'any' );
			}
		}
		if ( 2 === count( $icons ) ) {
			return $icons;
		}
	}
	$base = JLUXE_THEME_URI . '/assets/pwa/';
	return array(
		array( 'src' => $base . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => $base . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => $base . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
	);
}

/** @return array<string, mixed> */
function jluxe_pwa_manifest(): array {
	$name        = jluxe_pwa_site_name();
	$short       = jluxe_strlen( $name ) > 12 ? jluxe_substr( $name, 0, 12 ) : $name;
	$description = trim( wp_strip_all_tags( (string) jluxe_get_setting( 'identity.short_description', '' ) ) );
	$background  = sanitize_hex_color( (string) jluxe_get_setting( 'colors.background', '#F7F3EE' ) ) ?: '#F7F3EE';
	$theme_color = sanitize_hex_color( (string) apply_filters( 'jluxe_theme_color', '#F7F3EE' ) ) ?: $background;
	$scope       = jluxe_pwa_home_path();

	$manifest = array(
		'id'               => $scope,
		'name'             => $name,
		'short_name'       => $short,
		'lang'             => 'fa',
		'dir'              => 'rtl',
		'start_url'        => home_url( '/' ),
		'scope'            => $scope,
		'display'          => 'standalone',
		'display_override' => array( 'standalone', 'minimal-ui' ),
		'background_color' => $background,
		'theme_color'      => $theme_color,
		'categories'       => array( 'shopping', 'lifestyle' ),
		'icons'            => jluxe_pwa_icons(),
		'shortcuts'        => array(
			array( 'name' => 'سبد خرید', 'url' => jluxe_route_url( 'cart' ) ),
			array( 'name' => 'پیگیری سفارش', 'url' => jluxe_route_url( 'track_order' ) ),
			array( 'name' => 'فروشگاه', 'url' => jluxe_route_url( 'shop' ) ),
		),
	);
	if ( '' !== $description ) {
		$manifest['description'] = $description;
	}
	return (array) apply_filters( 'jluxe_pwa_manifest', $manifest );
}

/**
 * مسیرهایی که service worker هرگز لمس نمی‌کند. مسیرِ ریشهٔ سایت عمداً حذف
 * می‌شود: اگر مثلاً ووکامرس غیرفعال باشد و آدرسِ سبد به خانه برگردد، نباید کلِ
 * سایت از PWA بیرون بیفتد.
 *
 * @return string[]
 */
function jluxe_pwa_bypass_paths(): array {
	$home  = jluxe_pwa_home_path();
	$paths = array();
	foreach ( array( 'cart', 'checkout', 'login', 'dashboard', 'orders', 'track_order', 'lost_password' ) as $key ) {
		$path = (string) wp_parse_url( jluxe_route_url( $key ), PHP_URL_PATH );
		if ( '' !== $path && trailingslashit( $path ) !== $home ) {
			$paths[] = trailingslashit( $path );
		}
	}
	foreach ( array( 'wp-admin/', 'wp-login.php', 'wp-json/', 'wp-cron.php', 'xmlrpc.php', 'wp-signup.php', 'wp-activate.php' ) as $core ) {
		$paths[] = $home . $core;
	}
	return array_values( array_unique( (array) apply_filters( 'jluxe_pwa_bypass_paths', $paths ) ) );
}

/** پارامترهایی که یعنی «این درخواست حالت دارد» — هرگز از service worker رد نمی‌شوند. */
function jluxe_pwa_bypass_params(): array {
	return array( 'wc-ajax', 'wc-api', 'add-to-cart', 'remove_item', 'undo_item', 'removed_item', 'order_again', 'pay_for_order', 'key', 'order-received', 'preview', 'preview_id', 'customize_changeset_uuid', 'rest_route', 'jluxe_pwa', 'nonce', '_wpnonce', 'action' );
}

function jluxe_pwa_version(): string {
	$manifest_file = JLUXE_ASSET_DIR . '/manifest.json';
	$seed          = is_file( $manifest_file ) ? (string) md5_file( $manifest_file ) : 'dev';
	return substr( md5( $seed . '|' . wp_json_encode( jluxe_pwa_bypass_paths() ) . '|' . jluxe_pwa_offline_html() ), 0, 12 );
}

function jluxe_pwa_service_worker_js(): string {
	if ( ! jluxe_pwa_enabled() ) {
		// «خودحذف‌کن»: هر نسخهٔ قبلاً نصب‌شده را پاک و از ثبت خارج می‌کند.
		return "/* JLUXE PWA disabled — removing the previous service worker. */\n"
			. "self.addEventListener('install',()=>self.skipWaiting());\n"
			. "self.addEventListener('activate',(e)=>e.waitUntil(caches.keys().then((k)=>Promise.all(k.filter((n)=>n.indexOf('jluxe-')===0).map((n)=>caches.delete(n)))).then(()=>self.registration.unregister())));\n";
	}
	$asset_path = (string) wp_parse_url( JLUXE_ASSET_URI . '/assets/', PHP_URL_PATH );
	$config     = array(
		'version'      => jluxe_pwa_version(),
		'offline'      => jluxe_pwa_url( 'offline' ),
		'assetPrefix'  => $asset_path,
		'bypassPaths'  => jluxe_pwa_bypass_paths(),
		'bypassParams' => jluxe_pwa_bypass_params(),
	);
	$js = (string) file_get_contents( JLUXE_THEME_DIR . '/assets/pwa/sw.js' );
	return '/* JLUXE service worker — generated by inc/pwa.php (R88). */' . "\n"
		. 'const JLUXE_SW = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ";\n" . $js;
}

function jluxe_pwa_offline_html(): string {
	$name       = esc_html( jluxe_pwa_site_name() );
	$background = sanitize_hex_color( (string) jluxe_get_setting( 'colors.background', '#F7F3EE' ) ) ?: '#F7F3EE';
	$primary    = sanitize_hex_color( (string) jluxe_get_setting( 'colors.primary', '#B05232' ) ) ?: '#B05232';
	return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>اتصال برقرار نیست — ' . $name . '</title><style>'
		. 'html,body{height:100%;margin:0}body{display:grid;place-items:center;background:' . $background . ';color:#252321;font-family:IRANYekan,Vazirmatn,Tahoma,system-ui,sans-serif;text-align:center;padding:24px;box-sizing:border-box}'
		. 'main{max-width:340px}svg{width:56px;height:56px;color:' . $primary . '}h1{font-size:20px;margin:16px 0 8px}p{font-size:14px;line-height:1.9;color:#5f5a55;margin:0 0 20px}'
		. 'button{font:inherit;font-weight:700;border:0;border-radius:14px;padding:12px 28px;background:' . $primary . ';color:#fff;cursor:pointer}button:focus-visible{outline:3px solid #252321;outline-offset:3px}'
		. '</style></head><body><main>'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h.01"/><path d="M8.5 16.43a5 5 0 0 1 7 0"/><path d="M5 12.86a10 10 0 0 1 5.17-2.69"/><path d="M19 12.86a10 10 0 0 0-2-1.43"/><path d="M2 8.82a15 15 0 0 1 4.18-2.65"/><path d="M22 8.82a15 15 0 0 0-11.29-3.76"/><path d="m2 2 20 20"/></svg>'
		. '<h1>اتصال اینترنت برقرار نیست</h1><p>' . $name . ' بدونِ اینترنت باز نمی‌شود تا قیمت و موجودیِ نادرست نبینید. اتصال را بررسی کنید و دوباره تلاش کنید.</p>'
		. '<button type="button" onclick="location.reload()">تلاش دوباره</button></main>'
		. '<script>addEventListener("online",function(){location.reload()})</script></body></html>';
}

/** پاسخ به ?jluxe_pwa=manifest|sw|offline — پیش از اجرای کوئریِ اصلیِ وردپرس. */
function jluxe_pwa_serve(): void {
	$what = isset( $_GET['jluxe_pwa'] ) ? sanitize_key( wp_unslash( $_GET['jluxe_pwa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === $what ) {
		return;
	}
	jluxe_pwa_send( $what );
	exit;
}
add_action( 'wp_loaded', 'jluxe_pwa_serve', 1 );

/** بدنه و هدرهای هر نقطه؛ جدا از exit تا قابلِ تست باشد. */
function jluxe_pwa_send( string $what ): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // LiteSpeed/WP Super Cache: نسخهٔ کهنهٔ service worker هرگز کش نشود.
	}
	$send = static function ( string $name, string $value ): void {
		if ( ! headers_sent() ) {
			header( $name . ': ' . $value );
		}
		$GLOBALS['jluxe_pwa_headers'][ $name ] = $value;
	};
	$send( 'Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0' );
	$send( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	$send( 'X-Robots-Tag', 'noindex' );
	$send( 'X-Content-Type-Options', 'nosniff' );

	if ( 'sw' === $what ) {
		$send( 'Content-Type', 'application/javascript; charset=utf-8' );
		$send( 'Service-Worker-Allowed', jluxe_pwa_home_path() );
		echo jluxe_pwa_service_worker_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript body, config JSON-encoded.
		return;
	}
	if ( ! jluxe_pwa_enabled() || ! in_array( $what, array( 'manifest', 'offline' ), true ) ) {
		status_header( 404 );
		$send( 'Content-Type', 'text/plain; charset=utf-8' );
		echo 'Not found';
		return;
	}
	if ( 'manifest' === $what ) {
		$send( 'Content-Type', 'application/manifest+json; charset=utf-8' );
		echo wp_json_encode( jluxe_pwa_manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}
	$send( 'Content-Type', 'text/html; charset=utf-8' );
	echo jluxe_pwa_offline_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
}

function jluxe_pwa_head(): void {
	if ( ! jluxe_pwa_enabled() ) {
		return;
	}
	$name = jluxe_pwa_site_name();
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( jluxe_pwa_url( 'manifest' ) ) );
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
	printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( jluxe_strlen( $name ) > 12 ? jluxe_substr( $name, 0, 12 ) : $name ) );
	// وردپرس خودش برای «آیکونِ سایت» apple-touch-icon چاپ می‌کند؛ فقط در نبودش.
	if ( ! ( function_exists( 'has_site_icon' ) && has_site_icon() ) ) {
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( JLUXE_THEME_URI . '/assets/pwa/apple-touch-icon.png' ) );
	}
}
add_action( 'wp_head', 'jluxe_pwa_head', 3 );

/** تنظیماتِ عمومیِ PWA برای src/lib/pwa.js (بدونِ هیچ دادهٔ حساسی). */
function jluxe_pwa_public_settings(): array {
	$sensitive = ( function_exists( 'is_cart' ) && is_cart() )
		|| ( function_exists( 'is_checkout' ) && is_checkout() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() )
		|| ( function_exists( 'is_product' ) && is_product() );
	return array(
		'enabled'        => jluxe_pwa_enabled(),
		'sw'             => jluxe_pwa_url( 'sw' ),
		'scope'          => jluxe_pwa_home_path(),
		'installPrompt'  => jluxe_pwa_enabled() && ! empty( jluxe_get_setting( 'performance.pwa_install_prompt', true ) ),
		'suppressPrompt' => $sensitive,
		'name'           => jluxe_pwa_site_name(),
	);
}
