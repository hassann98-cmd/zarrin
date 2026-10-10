<?php
/** Explicit, privacy-conscious back-in-stock subscriptions for WooCommerce products. */
defined( 'ABSPATH' ) || exit;

const JLUXE_STOCK_ALERT_SCHEMA_VERSION = '2';

function jluxe_stock_alerts_table(): string {
	global $wpdb;
	return isset( $wpdb->prefix ) ? $wpdb->prefix . 'jluxe_stock_alerts' : '';
}

/** Install lazily once; themes can be updated without requiring reactivation. */
function jluxe_stock_alerts_maybe_install(): bool {
	if ( JLUXE_STOCK_ALERT_SCHEMA_VERSION === (string) get_option( 'jluxe_stock_alerts_schema_version', '' ) ) {
		return true;
	}
	global $wpdb;
	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
		return false;
	}
	$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
	if ( ! function_exists( 'dbDelta' ) && is_file( $upgrade_file ) ) {
		require_once $upgrade_file;
	}
	if ( ! function_exists( 'dbDelta' ) ) {
		return false;
	}
	$table           = jluxe_stock_alerts_table();
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		product_id bigint(20) unsigned NOT NULL,
		variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
		phone_hash char(64) NOT NULL,
		phone_cipher varchar(160) NOT NULL,
		created_at datetime NOT NULL,
		notification_sent_at datetime DEFAULT NULL,
		manual_notification_sent_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY subscription (product_id,variation_id,phone_hash),
		KEY pending (product_id,variation_id,notification_sent_at),
		KEY manual_pending (product_id,variation_id,manual_notification_sent_at)
	) {$charset_collate};";
	dbDelta( $sql );
	update_option( 'jluxe_stock_alerts_schema_version', JLUXE_STOCK_ALERT_SCHEMA_VERSION, false );
	return true;
}
add_action( 'init', 'jluxe_stock_alerts_maybe_install', 5 );

/** Resolve the independent restock delivery mode; older settings remain pattern-based. */
function jluxe_stock_alert_sms_mode( ?array $sms = null ): string {
	if ( null === $sms ) {
		$sms = function_exists( 'jluxe_get_theme_settings' ) ? ( jluxe_get_theme_settings()['sms'] ?? array() ) : array();
	}
	$mode_value = $sms['stock_alert_mode'] ?? 'pattern';
	$mode       = is_scalar( $mode_value ) ? sanitize_key( (string) $mode_value ) : 'pattern';
	return in_array( $mode, array( 'pattern', 'free_text' ), true ) ? $mode : 'pattern';
}

function jluxe_stock_alert_sms_supported_variables(): array {
	return array( 'mobile', 'phone', 'customer_mobile', 'site_name', 'site_url', 'product_name', 'product_url', 'post_id', 'stock_qty' );
}

function jluxe_stock_alert_sms_message_configuration_valid( string $template ): bool {
	$template = trim( $template );
	if ( '' === $template || ( function_exists( 'jluxe_strlen' ) && jluxe_strlen( $template ) > 1500 ) ) {
		return false;
	}
	preg_match_all( '/\{([A-Za-z][A-Za-z0-9_]*)\}/', $template, $matches );
	return empty( array_diff( array_unique( $matches[1] ?? array() ), jluxe_stock_alert_sms_supported_variables() ) );
}

/** A separate opt-in prevents borrowing an OTP template for product messages. */
function jluxe_stock_alert_sms_available(): bool {
	if ( ! function_exists( 'jluxe_get_theme_settings' ) || ! function_exists( 'jluxe_get_sms_api_key' ) ) {
		return false;
	}
	$sms = jluxe_get_theme_settings()['sms'] ?? array();
	if ( empty( $sms['stock_alert_enabled'] ) || '' === jluxe_get_sms_api_key() ) {
		return false;
	}
	$mode = jluxe_stock_alert_sms_mode( $sms );
	if ( 'kavenegar' === ( $sms['provider'] ?? '' ) ) {
		return 'pattern' === $mode && '' !== trim( (string) ( $sms['stock_alert_template'] ?? '' ) );
	}
	if ( 'melipayamak' !== ( $sms['provider'] ?? '' ) || '' === trim( (string) ( $sms['username'] ?? '' ) ) ) {
		return false;
	}
	if ( 'free_text' === $mode ) {
		return '' !== trim( (string) ( $sms['sender'] ?? '' ) ) && jluxe_stock_alert_sms_message_configuration_valid( (string) ( $sms['stock_alert_message'] ?? '' ) );
	}
	return absint( $sms['stock_alert_body_id'] ?? 0 ) > 0;
}

function jluxe_stock_alert_phone_cipher( string $phone ): string {
	if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) {
		return '';
	}
	$key   = hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	$nonce = random_bytes( 12 );
	$tag   = '';
	$ciphertext = openssl_encrypt( $phone, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
	if ( ! is_string( $ciphertext ) || '' === $ciphertext || 16 !== strlen( $tag ) ) {
		return '';
	}
	return base64_encode( $nonce . $tag . $ciphertext );
}

function jluxe_stock_alert_phone_decipher( string $encoded ): string {
	if ( ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}
	$payload = base64_decode( $encoded, true );
	if ( ! is_string( $payload ) || strlen( $payload ) < 29 ) {
		return '';
	}
	$key        = hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	$nonce      = substr( $payload, 0, 12 );
	$tag        = substr( $payload, 12, 16 );
	$ciphertext = substr( $payload, 28 );
	$phone      = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
	return is_string( $phone ) && preg_match( '/^9[0-9]{9}$/D', $phone ) ? $phone : '';
}

function jluxe_stock_alert_rate_limit_ok( string $bucket, string $identity, int $max, int $window ): bool {
	return function_exists( 'jluxe_security_rate_limit' )
		? jluxe_security_rate_limit( 'stock_alert_' . $bucket, $identity, $max, $window )
		: false;
}

function jluxe_handle_stock_alert_signup(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_send_json_error( array( 'message' => 'درخواست معتبر نیست.' ), 405 );
	}
	if ( ! check_ajax_referer( 'jluxe_stock_alert_signup', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'نشست منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.' ), 403 );
	}
	if ( ! jluxe_stock_alert_sms_available() ) {
		wp_send_json_error( array( 'message' => 'ثبت اعلان پیامکی فعلاً فعال نیست.' ), 503 );
	}

	$raw_phone = isset( $_POST['phone'] ) && is_scalar( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '';
	$phone     = function_exists( 'jluxe_normalize_phone' ) ? jluxe_normalize_phone( $raw_phone ) : '';
	if ( '' === $phone ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر نیست.' ), 400 );
	}

	$ip = function_exists( 'jluxe_theme_get_client_ip' ) ? jluxe_theme_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
	if ( ! jluxe_stock_alert_rate_limit_ok( 'ip', $ip, 10, HOUR_IN_SECONDS ) ||
		! jluxe_stock_alert_rate_limit_ok( 'phone', $phone, 4, HOUR_IN_SECONDS ) ) {
		wp_send_json_error( array( 'message' => 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.' ), 429 );
	}

	$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
	$product      = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! jluxe_product_is_public( $product ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 404 );
	}

	$alert_product = $product;
	if ( $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product_id || ! jluxe_product_is_public( $variation ) ) {
			wp_send_json_error( array( 'message' => 'تنوع انتخاب‌شده معتبر نیست.' ), 400 );
		}
		if ( $variation->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'این تنوع اکنون موجود است؛ می‌توانید آن را به سبد خرید اضافه کنید.' ), 409 );
		}
		$alert_product = $variation;
	} elseif ( $product->is_in_stock() ) {
		wp_send_json_error( array( 'message' => 'این محصول اکنون موجود است؛ می‌توانید آن را به سبد خرید اضافه کنید.' ), 409 );
	}

	if ( ! jluxe_stock_alerts_maybe_install() ) {
		wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست فعلاً در دسترس نیست؛ دوباره تلاش کنید.' ), 503 );
	}
	$phone_cipher = jluxe_stock_alert_phone_cipher( $phone );
	if ( '' === $phone_cipher ) {
		wp_send_json_error( array( 'message' => 'ذخیرهٔ امنِ شمارهٔ موبایل در دسترس نیست.' ), 503 );
	}

	global $wpdb;
	$table      = jluxe_stock_alerts_table();
	$phone_hash = hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) );
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE product_id = %d AND variation_id = %d AND phone_hash = %s LIMIT 1",
		$product_id,
		$variation_id,
		$phone_hash
	) );
	if ( false === $existing ) {
		wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست انجام نشد؛ دوباره تلاش کنید.' ), 503 );
	}

	/*
	 * The table's unique key intentionally keeps one row per product/variation/phone.
	 * Re-arm a previously delivered subscription instead of attempting an INSERT
	 * that the unique key would reject when the same product goes out of stock again.
	 */
	$subscription_data = array(
		'phone_cipher'                 => $phone_cipher,
		'created_at'                   => current_time( 'mysql' ),
		'notification_sent_at'         => null,
		'manual_notification_sent_at'  => null,
	);
	if ( null !== $existing && '' !== (string) $existing ) {
		$updated = $wpdb->update( $table, $subscription_data, array( 'id' => absint( $existing ) ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست انجام نشد؛ دوباره تلاش کنید.' ), 503 );
		}
	} else {
		$inserted = $wpdb->insert(
			$table,
			array_merge(
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'phone_hash'   => $phone_hash,
				),
				$subscription_data
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			// A concurrent first opt-in may win the unique key; re-arm its row too.
			$duplicate = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND variation_id = %d AND phone_hash = %s LIMIT 1",
				$product_id,
				$variation_id,
				$phone_hash
			) );
			if ( false === $duplicate || null === $duplicate || '' === (string) $duplicate ) {
				wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست انجام نشد؛ دوباره تلاش کنید.' ), 503 );
			}
			$updated = $wpdb->update( $table, $subscription_data, array( 'id' => absint( $duplicate ) ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) {
				wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست انجام نشد؛ دوباره تلاش کنید.' ), 503 );
			}
		}
	}

	wp_send_json_success( array( 'message' => 'درخواست شما ثبت شد؛ وقتی محصول موجود شود از طریق پیامک خبرتان می‌کنیم.' ) );
}
add_action( 'wp_ajax_jluxe_stock_alert_signup', 'jluxe_handle_stock_alert_signup' );
add_action( 'wp_ajax_nopriv_jluxe_stock_alert_signup', 'jluxe_handle_stock_alert_signup' );

/** Variables available to product-stock and manually addressed messages only. */
function jluxe_stock_alert_sms_context( string $phone, ?WC_Product $product = null, string $product_name = '' ): array {
	$phone      = jluxe_normalize_phone( $phone );
	$product_id = $product instanceof WC_Product ? absint( $product->get_id() ) : 0;
	$link_id    = $product instanceof WC_Product && $product->is_type( 'variation' ) && $product->get_parent_id()
		? absint( $product->get_parent_id() )
		: $product_id;
	$name = $product instanceof WC_Product ? sanitize_text_field( (string) $product->get_name() ) : sanitize_text_field( $product_name );
	$url  = $link_id ? esc_url_raw( (string) get_permalink( $link_id ) ) : '';
	$qty  = '';
	if ( $product instanceof WC_Product && method_exists( $product, 'get_stock_quantity' ) ) {
		$quantity = $product->get_stock_quantity();
		$qty      = null === $quantity ? '' : (string) $quantity;
	}

	return array(
		'mobile'          => '' !== $phone ? '0' . $phone : '',
		'phone'           => '' !== $phone ? '0' . $phone : '',
		'customer_mobile' => '' !== $phone ? '0' . $phone : '',
		'site_name'       => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
		'site_url'        => (string) home_url( '/' ),
		'product_name'    => $name,
		'product_url'     => $url,
		'post_id'         => $product_id ? (string) $product_id : '',
		'stock_qty'       => $qty,
		'_has_product'    => $product instanceof WC_Product,
	);
}

/** Expand only stock-alert placeholders; reject order/cart variables without real context. */
function jluxe_stock_alert_render_message( string $template, array $context ) {
	$template = trim( $template );
	if ( '' === $template ) {
		return new WP_Error( 'jluxe_stock_alert_empty_message', 'متن پیامک موجودشدن خالی است.', array( 'status' => 400 ) );
	}
	if ( function_exists( 'jluxe_strlen' ) && jluxe_strlen( $template ) > 1500 ) {
		return new WP_Error( 'jluxe_stock_alert_message_too_long', 'متن پیامک از ۱۵۰۰ نویسه بیشتر است.', array( 'status' => 400 ) );
	}

	$allowed = jluxe_stock_alert_sms_supported_variables();
	preg_match_all( '/\{([A-Za-z][A-Za-z0-9_]*)\}/', $template, $matches );
	$tokens  = array_values( array_unique( $matches[1] ?? array() ) );
	$unknown = array_values( array_diff( $tokens, $allowed ) );
	if ( $unknown ) {
		return new WP_Error( 'jluxe_stock_alert_unknown_variable', 'این متغیرها برای پیام موجودی داده‌ای ندارند: {' . implode( '}, {', $unknown ) . '}.', array( 'status' => 400 ) );
	}

	$has_product = ! empty( $context['_has_product'] );
	foreach ( $tokens as $token ) {
		if ( 'product_name' === $token && '' === (string) ( $context['product_name'] ?? '' ) ) {
			return new WP_Error( 'jluxe_stock_alert_missing_product_context', 'برای متغیر {product_name} نام محصول را وارد کنید یا شناسهٔ محصول را انتخاب کنید.', array( 'status' => 400 ) );
		}
		if ( in_array( $token, array( 'product_url', 'post_id', 'stock_qty' ), true ) && ! $has_product ) {
			return new WP_Error( 'jluxe_stock_alert_missing_product_context', 'برای متغیرهای لینک/شناسه/موجودی، در فرم ارسال به شمارهٔ دلخواه شناسهٔ محصول را وارد کنید.', array( 'status' => 400 ) );
		}
	}

	$replacements = array();
	foreach ( $allowed as $token ) {
		$replacements[ '{' . $token . '}' ] = (string) ( $context[ $token ] ?? '' );
	}
	$message = trim( strtr( $template, $replacements ) );
	if ( '' === $message ) {
		return new WP_Error( 'jluxe_stock_alert_empty_message', 'بعد از جایگزینی متغیرها، متن پیامک خالی است.', array( 'status' => 400 ) );
	}
	if ( function_exists( 'jluxe_strlen' ) && jluxe_strlen( $message ) > 1500 ) {
		return new WP_Error( 'jluxe_stock_alert_message_too_long', 'متن نهایی پیامک از ۱۵۰۰ نویسه بیشتر است.', array( 'status' => 400 ) );
	}
	return $message;
}

/** Send via the existing Melipayamak credentials; never reports an ambiguous API response as success. */
function jluxe_stock_alert_send_melipayamak( array $sms, string $api_key, string $phone, string $text, string $mode ) {
	if ( '' === trim( (string) ( $sms['username'] ?? '' ) ) ) {
		return new WP_Error( 'jluxe_stock_alert_missing_username', 'نام کاربری ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
	}
	if ( 'pattern' === $mode && absint( $sms['stock_alert_body_id'] ?? 0 ) < 1 ) {
		return new WP_Error( 'jluxe_stock_alert_missing_body_id', 'Body ID پترن موجودشدن تنظیم نشده است.', array( 'status' => 500 ) );
	}
	if ( 'free_text' === $mode && '' === trim( (string) ( $sms['sender'] ?? '' ) ) ) {
		return new WP_Error( 'jluxe_stock_alert_missing_sender', 'شمارهٔ خط ارسال عادی ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
	}

	$endpoint = 'pattern' === $mode
		? 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber'
		: 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
	$body = array(
		'username' => (string) $sms['username'],
		'password' => $api_key,
		'to'       => '0' . $phone,
		'text'     => $text,
	);
	if ( 'pattern' === $mode ) {
		$body['bodyId'] = absint( $sms['stock_alert_body_id'] );
	} else {
		$body['from'] = trim( (string) $sms['sender'] );
	}
	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8' ),
			'body'    => $body,
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارتباط با وب‌سرویس ملی‌پیامک ناموفق بود.', array( 'status' => 502 ) );
	}
	$http_code = (int) wp_remote_retrieve_response_code( $response );
	$raw_body  = trim( (string) wp_remote_retrieve_body( $response ) );
	$decoded   = json_decode( $raw_body, true );
	$value     = is_array( $decoded ) && isset( $decoded['Value'] )
		? (int) $decoded['Value']
		: ( ctype_digit( $raw_body ) ? (int) $raw_body : 0 );
	if ( $http_code < 200 || $http_code >= 300 || $value <= 0 ) {
		return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ملی‌پیامک ارسال را نپذیرفت؛ مجازبودن خط، اعتبار، شماره و پترن را بررسی کنید.', array( 'status' => 502 ) );
	}
	return true;
}

/** Send one stock notice through its independently configured pattern or message. */
function jluxe_send_stock_alert_sms( string $phone, string $product_name, ?WC_Product $product = null ) {
	if ( ! jluxe_stock_alert_sms_available() ) {
		return new WP_Error( 'jluxe_stock_alert_sms_disabled', 'هشدار موجودی پیامکی پیکربندی نشده است.', array( 'status' => 503 ) );
	}
	$sms     = jluxe_get_theme_settings()['sms'];
	$api_key = jluxe_get_sms_api_key();
	$phone   = jluxe_normalize_phone( $phone );
	$name    = function_exists( 'jluxe_substr' ) ? jluxe_substr( sanitize_text_field( $product_name ), 0, 100 ) : substr( sanitize_text_field( $product_name ), 0, 100 );
	if ( '' === $phone ) {
		return new WP_Error( 'jluxe_stock_alert_bad_payload', 'شمارهٔ مقصد معتبر نیست.', array( 'status' => 400 ) );
	}

	if ( 'kavenegar' === $sms['provider'] ) {
		if ( '' === $name ) {
			return new WP_Error( 'jluxe_stock_alert_bad_payload', 'نام محصول برای پترن کاوه‌نگار لازم است.', array( 'status' => 400 ) );
		}
		$url = sprintf(
			'https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&token=%s&template=%s',
			rawurlencode( $api_key ),
			rawurlencode( '0' . $phone ),
			rawurlencode( $name ),
			rawurlencode( (string) $sms['stock_alert_template'] )
		);
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارسال پیامکِ موجودشدن ناموفق بود.', array( 'status' => 502 ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( (int) wp_remote_retrieve_response_code( $response ) < 200 || (int) wp_remote_retrieve_response_code( $response ) >= 300 || ! is_array( $body ) || 200 !== (int) ( $body['return']['status'] ?? 0 ) ) {
			return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارائه‌دهندهٔ پیامک ارسال را نپذیرفت.', array( 'status' => 502 ) );
		}
		return true;
	}

	$mode = jluxe_stock_alert_sms_mode( $sms );
	if ( 'free_text' === $mode ) {
		$context = jluxe_stock_alert_sms_context( $phone, $product, $name );
		$message = jluxe_stock_alert_render_message( (string) ( $sms['stock_alert_message'] ?? '' ), $context );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		return jluxe_stock_alert_send_melipayamak( $sms, $api_key, $phone, $message, 'free_text' );
	}
	if ( '' === $name ) {
		return new WP_Error( 'jluxe_stock_alert_bad_payload', 'مقدار متغیرِ پترن (نام محصول) لازم است.', array( 'status' => 400 ) );
	}
	return jluxe_stock_alert_send_melipayamak( $sms, $api_key, $phone, $name, 'pattern' );
}

/** Send pending notices once, and leave provider failures retryable on a later stock update. */
function jluxe_dispatch_stock_alerts_for_target( int $product_id, int $variation_id, WC_Product $available_product ): void {
	if ( ! $product_id || ! $available_product->is_in_stock() || ! jluxe_stock_alert_sms_available() || ! jluxe_stock_alerts_maybe_install() ) {
		return;
	}
	global $wpdb;
	$table = jluxe_stock_alerts_table();
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, phone_cipher FROM {$table} WHERE product_id = %d AND variation_id = %d AND notification_sent_at IS NULL ORDER BY id ASC LIMIT 100",
		$product_id,
		$variation_id
	), ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return;
	}
	$name = (string) $available_product->get_name();
	foreach ( $rows as $row ) {
		$phone = jluxe_stock_alert_phone_decipher( (string) ( $row['phone_cipher'] ?? '' ) );
		if ( '' === $phone ) {
			continue;
		}
		if ( true !== jluxe_send_stock_alert_sms( $phone, $name, $available_product ) ) {
			continue;
		}
		$wpdb->update(
			$table,
			array( 'notification_sent_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $row['id'] ?? 0 ), 'notification_sent_at' => null ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}
}

function jluxe_stock_alert_on_stock_change( $product ): void {
	if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
		return;
	}
	if ( $product->is_type( 'variation' ) ) {
		$parent_id = absint( $product->get_parent_id() );
		jluxe_dispatch_stock_alerts_for_target( $parent_id, $product->get_id(), $product );
		$parent = $parent_id && function_exists( 'wc_get_product' ) ? wc_get_product( $parent_id ) : false;
		if ( $parent instanceof WC_Product && $parent->is_in_stock() ) {
			jluxe_dispatch_stock_alerts_for_target( $parent_id, 0, $parent );
		}
		return;
	}
	jluxe_dispatch_stock_alerts_for_target( $product->get_id(), 0, $product );
}
add_action( 'woocommerce_product_set_stock', 'jluxe_stock_alert_on_stock_change', 40, 1 );
add_action( 'woocommerce_variation_set_stock', 'jluxe_stock_alert_on_stock_change', 40, 1 );

function jluxe_stock_alert_on_stock_status_change( $product_id, $stock_status, $product = null ): void {
	if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( absint( $product_id ) );
	}
	if ( $product instanceof WC_Product ) {
		jluxe_stock_alert_on_stock_change( $product );
	}
}
add_action( 'woocommerce_product_set_stock_status', 'jluxe_stock_alert_on_stock_status_change', 40, 3 );
add_action( 'woocommerce_variation_set_stock_status', 'jluxe_stock_alert_on_stock_status_change', 40, 3 );

/** Keep the admin list and send actions on the existing SMS settings tab. */
function jluxe_stock_alert_admin_page_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => 'jluxe-sms' ), $args ), admin_url( 'admin.php' ) );
}

function jluxe_stock_alert_admin_result_for_error( $error ): string {
	if ( ! is_wp_error( $error ) ) {
		return 'failed';
	}
	switch ( $error->get_error_code() ) {
		case 'jluxe_stock_alert_sms_disabled':
		case 'jluxe_stock_alert_missing_username':
		case 'jluxe_stock_alert_missing_body_id':
		case 'jluxe_stock_alert_missing_sender':
			return 'not_ready';
		case 'jluxe_stock_alert_bad_payload':
		case 'jluxe_stock_alert_empty_message':
		case 'jluxe_stock_alert_message_too_long':
		case 'jluxe_stock_alert_unknown_variable':
		case 'jluxe_stock_alert_missing_product_context':
			return 'invalid_message';
		case 'jluxe_stock_alert_rate_limited':
			return 'rate_limited';
		case 'jluxe_stock_alert_subscription_not_found':
		case 'jluxe_stock_alert_product_not_found':
			return 'not_found';
		default:
			return 'failed';
	}
}

function jluxe_stock_alert_admin_notice(): void {
	$result = isset( $_GET['jluxe_stock_alert_result'] ) && is_scalar( $_GET['jluxe_stock_alert_result'] )
		? sanitize_key( wp_unslash( (string) $_GET['jluxe_stock_alert_result'] ) )
		: '';
	$messages = array(
		'manual_sent'    => array( 'success', 'درخواست ارسال دستی توسط سرویس پیامکی پذیرفته شد؛ وضعیت ارسال خودکار جداگانه حفظ شد.' ),
		'custom_sent'    => array( 'success', 'درخواست ارسال به شمارهٔ واردشده پذیرفته شد؛ شماره برای این ارسال ذخیره نشد.' ),
		'not_ready'      => array( 'error', 'ارسال آماده نیست؛ فعال‌بودن اعلان، نام کاربری/کلید و تنظیمات روش انتخاب‌شده را بررسی کنید.' ),
		'invalid_message'=> array( 'error', 'ارسال انجام نشد؛ متن، متغیرهای پشتیبانی‌شده یا اطلاعات محصول را بررسی کنید.' ),
		'rate_limited'   => array( 'error', 'برای جلوگیری از ارسال تکراری، ارسال به این شماره موقتاً محدود شده است.' ),
		'not_found'      => array( 'error', 'درخواست یا محصول موردنظر پیدا نشد؛ پیامکی ارسال نشد.' ),
		'invalid_nonce'  => array( 'error', 'درخواست امن نبود یا منقضی شده است؛ صفحه را تازه کنید.' ),
		'failed'         => array( 'error', 'ملی‌پیامک ارسال را تأیید نکرد؛ تنظیمات خط/پترن، اعتبار و پاسخ سرویس را بررسی کنید.' ),
	);
	if ( ! isset( $messages[ $result ] ) ) {
		return;
	}
	[ $class, $message ] = $messages[ $result ];
	?>
	<div class="notice notice-<?php echo esc_attr( $class ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php
}

function jluxe_stock_alert_admin_rows( int $page = 1, int $per_page = 25 ): array {
	if ( ! jluxe_stock_alerts_maybe_install() ) {
		return array( 'rows' => array(), 'total' => 0, 'error' => true );
	}
	global $wpdb;
	$table   = jluxe_stock_alerts_table();
	$page    = max( 1, $page );
	$per_page = max( 1, min( 100, $per_page ) );
	$offset  = ( $page - 1 ) * $per_page;
	$total   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id > %d", 0 ) );
	$rows    = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, product_id, variation_id, phone_cipher, created_at, notification_sent_at, manual_notification_sent_at FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	), ARRAY_A );
	if ( false === $total || ! is_array( $rows ) ) {
		return array( 'rows' => array(), 'total' => 0, 'error' => true );
	}
	return array( 'rows' => $rows, 'total' => absint( $total ), 'error' => false );
}

function jluxe_stock_alert_subscription_row( int $subscription_id ) {
	if ( $subscription_id < 1 || ! jluxe_stock_alerts_maybe_install() ) {
		return null;
	}
	global $wpdb;
	$table = jluxe_stock_alerts_table();
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, product_id, variation_id, phone_cipher, created_at, notification_sent_at, manual_notification_sent_at FROM {$table} WHERE id = %d LIMIT 1",
		$subscription_id
	), ARRAY_A );
	return is_array( $rows ) && isset( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : null;
}

/** One manual row action; manual state never suppresses the separate auto-restock delivery. */
function jluxe_stock_alert_send_subscription_manually( int $subscription_id ) {
	if ( ! jluxe_stock_alert_sms_available() ) {
		return new WP_Error( 'jluxe_stock_alert_sms_disabled', 'اعلان پیامکی موجودی آماده نیست.', array( 'status' => 503 ) );
	}
	$row = jluxe_stock_alert_subscription_row( $subscription_id );
	if ( ! is_array( $row ) ) {
		return new WP_Error( 'jluxe_stock_alert_subscription_not_found', 'درخواست پیدا نشد.', array( 'status' => 404 ) );
	}
	$phone = jluxe_stock_alert_phone_decipher( (string) ( $row['phone_cipher'] ?? '' ) );
	if ( '' === $phone ) {
		return new WP_Error( 'jluxe_stock_alert_bad_payload', 'شمارهٔ ذخیره‌شده قابل رمزگشایی نیست.', array( 'status' => 400 ) );
	}
	if ( ! jluxe_stock_alert_admin_send_rate_limit( $phone ) ) {
		return new WP_Error( 'jluxe_stock_alert_rate_limited', 'ارسال به این شماره موقتاً محدود شده است.', array( 'status' => 429 ) );
	}
	$product_id   = absint( $row['product_id'] ?? 0 );
	$variation_id = absint( $row['variation_id'] ?? 0 );
	$product      = $variation_id && function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : false;
	if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $product_id );
	}
	if ( ! $product instanceof WC_Product ) {
		return new WP_Error( 'jluxe_stock_alert_product_not_found', 'محصول درخواست پیدا نشد.', array( 'status' => 404 ) );
	}
	if ( ! function_exists( 'jluxe_security_lock' ) || ! function_exists( 'jluxe_security_unlock' ) ) {
		return new WP_Error( 'jluxe_stock_alert_storage', 'قفل امن ارسال در دسترس نیست.', array( 'status' => 503 ) );
	}
	$lock = jluxe_security_lock( 'stock-alert-manual:' . $subscription_id );
	if ( ! $lock ) {
		return new WP_Error( 'jluxe_stock_alert_rate_limited', 'ارسال دیگری برای این درخواست در حال انجام است.', array( 'status' => 429 ) );
	}
	try {
		$result = jluxe_send_stock_alert_sms( $phone, (string) $product->get_name(), $product );
		if ( true !== $result ) {
			return $result;
		}
		global $wpdb;
		$updated = $wpdb->update(
			jluxe_stock_alerts_table(),
			array( 'manual_notification_sent_at' => current_time( 'mysql' ) ),
			array( 'id' => $subscription_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return new WP_Error( 'jluxe_stock_alert_storage', 'ارسال انجام شد اما ثبت وضعیت ارسال ممکن نشد.', array( 'status' => 503 ) );
		}
		return true;
	} finally {
		jluxe_security_unlock( $lock );
	}
}

function jluxe_stock_alert_admin_send_rate_limit( string $phone ): bool {
	if ( ! function_exists( 'jluxe_security_rate_limit' ) ) {
		return false;
	}
	$identity = (string) get_current_user_id() . ':' . jluxe_normalize_phone( $phone );
	return jluxe_security_rate_limit( 'stock_alert_admin_send', $identity, 5, HOUR_IN_SECONDS );
}

function jluxe_stock_alert_send_to_custom_phone( string $phone, string $product_name = '', ?WC_Product $product = null ) {
	if ( ! jluxe_stock_alert_sms_available() ) {
		return new WP_Error( 'jluxe_stock_alert_sms_disabled', 'اعلان پیامکی موجودی آماده نیست.', array( 'status' => 503 ) );
	}
	$phone = jluxe_normalize_phone( $phone );
	if ( '' === $phone ) {
		return new WP_Error( 'jluxe_stock_alert_bad_payload', 'شمارهٔ مقصد معتبر نیست.', array( 'status' => 400 ) );
	}
	if ( ! jluxe_stock_alert_admin_send_rate_limit( $phone ) ) {
		return new WP_Error( 'jluxe_stock_alert_rate_limited', 'ارسال به این شماره موقتاً محدود شده است.', array( 'status' => 429 ) );
	}
	return jluxe_send_stock_alert_sms( $phone, $product_name, $product );
}

function jluxe_stock_alert_admin_redirect( string $result ): void {
	wp_safe_redirect( jluxe_stock_alert_admin_page_url( array( 'jluxe_stock_alert_result' => sanitize_key( $result ) ) ) );
	exit;
}

function jluxe_handle_admin_stock_alert_manual_send(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازهٔ انجام این عملیات را ندارید.' );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'روش درخواست معتبر نیست.' );
	}
	$id    = isset( $_POST['subscription_id'] ) && is_scalar( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
	$nonce = isset( $_POST['_wpnonce'] ) && is_scalar( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! $id || ! wp_verify_nonce( $nonce, 'jluxe_stock_alert_manual_' . $id ) ) {
		jluxe_stock_alert_admin_redirect( 'invalid_nonce' );
	}
	$result = jluxe_stock_alert_send_subscription_manually( $id );
	jluxe_stock_alert_admin_redirect( true === $result ? 'manual_sent' : jluxe_stock_alert_admin_result_for_error( $result ) );
}
add_action( 'admin_post_jluxe_stock_alert_manual_send', 'jluxe_handle_admin_stock_alert_manual_send' );

function jluxe_handle_admin_stock_alert_custom_send(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازهٔ انجام این عملیات را ندارید.' );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'روش درخواست معتبر نیست.' );
	}
	$nonce = isset( $_POST['_wpnonce'] ) && is_scalar( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jluxe_stock_alert_custom_send' ) ) {
		jluxe_stock_alert_admin_redirect( 'invalid_nonce' );
	}
	$phone_raw   = isset( $_POST['phone'] ) && is_scalar( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '';
	$product_id  = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$product_name = isset( $_POST['product_name'] ) && is_scalar( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
	$product     = null;
	if ( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof WC_Product ) {
			jluxe_stock_alert_admin_redirect( 'not_found' );
		}
		$product_name = (string) $product->get_name();
	}
	$result = jluxe_stock_alert_send_to_custom_phone( $phone_raw, $product_name, $product instanceof WC_Product ? $product : null );
	jluxe_stock_alert_admin_redirect( true === $result ? 'custom_sent' : jluxe_stock_alert_admin_result_for_error( $result ) );
}
add_action( 'admin_post_jluxe_stock_alert_custom_send', 'jluxe_handle_admin_stock_alert_custom_send' );

/** The SMS settings tab also contains the private request list and manual send forms. */
function jluxe_render_stock_alert_admin_management(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<hr />
	<h2>درخواست‌ها و ارسال دستی پیامکِ موجودی</h2>
	<?php jluxe_stock_alert_admin_notice(); ?>
	<?php if ( ! jluxe_stock_alert_sms_available() ) : ?>
		<div class="notice notice-warning inline"><p>ارسال/ثبت درخواست فعلاً آماده نیست. provider، کلید پیامک، فعال‌سازی اعلان و روش انتخاب‌شده را کامل کنید؛ ورود OTP به‌تنهایی کافی نیست.</p></div>
	<?php endif; ?>
	<h3>ارسال به شمارهٔ دلخواه</h3>
	<p class="description">شمارهٔ مقصد باید موبایل معتبر ایران باشد. از همین متن یا پترنِ بخش بالا استفاده می‌شود. شماره برای این ارسال جداگانه ذخیره نمی‌شود. در حالت پترن، مقدار متغیرِ الگو را وارد کنید؛ در حالت متن آزاد، شناسهٔ محصول اختیاری است و برای متغیرهای محصول به کار می‌رود. برای جلوگیری از ارسال ناخواسته، ارسال دستی به هر شماره حداکثر ۵ بار در ساعت مجاز است.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<input type="hidden" name="action" value="jluxe_stock_alert_custom_send" />
		<?php wp_nonce_field( 'jluxe_stock_alert_custom_send' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="jluxe-stock-alert-custom-phone">شمارهٔ مقصد</label></th><td><input id="jluxe-stock-alert-custom-phone" name="phone" type="tel" inputmode="tel" autocomplete="off" maxlength="64" dir="ltr" class="regular-text" required /></td></tr>
			<tr><th scope="row"><label for="jluxe-stock-alert-custom-product-id">شناسهٔ محصول (اختیاری)</label></th><td><input id="jluxe-stock-alert-custom-product-id" name="product_id" type="number" min="1" step="1" inputmode="numeric" class="small-text" /><p class="description">با ورود شناسه، نام، لینک و موجودی واقعی محصول در متغیرهای پیام قرار می‌گیرد.</p></td></tr>
			<tr><th scope="row"><label for="jluxe-stock-alert-custom-product-name">نام محصول / مقدار متغیر پترن</label></th><td><input id="jluxe-stock-alert-custom-product-name" name="product_name" type="text" maxlength="100" class="regular-text" /><p class="description">برای یک متغیر {product_name} در متن آزاد یا متغیرِ تک‌مقداریِ پترن؛ اگر شناسهٔ محصول وارد شود، نام واقعی محصول جایگزین می‌شود.</p></td></tr>
		</table>
		<p><button type="submit" class="button button-primary"<?php disabled( ! jluxe_stock_alert_sms_available() ); ?> onclick="return confirm('پیامک از طریق سرویس پیامکی ارسال شود؟');">ارسال پیامک</button></p>
	</form>

	<h3>فهرست درخواست‌های ثبت‌شده</h3>
	<?php
	$page = isset( $_GET['stock_page'] ) && is_scalar( $_GET['stock_page'] ) ? max( 1, absint( wp_unslash( $_GET['stock_page'] ) ) ) : 1;
	$data = jluxe_stock_alert_admin_rows( $page, 25 );
	if ( ! empty( $data['error'] ) ) :
		?>
		<div class="notice notice-error inline"><p>خواندن فهرست درخواست‌ها ممکن نشد؛ جدول اعلان موجودی را بررسی کنید.</p></div>
		<?php
		return;
	endif;
	if ( empty( $data['rows'] ) ) :
		?>
		<p class="description">هنوز درخواستی ثبت نشده است.</p>
		<?php
		return;
	endif;
	?>
	<table class="widefat striped">
		<thead><tr><th>محصول</th><th>شماره موبایل</th><th>تاریخ درخواست</th><th>ارسال خودکار</th><th>ارسال دستی</th><th>عملیات</th></tr></thead>
		<tbody>
		<?php foreach ( $data['rows'] as $row ) :
			$id           = absint( $row['id'] ?? 0 );
			$product_id   = absint( $row['product_id'] ?? 0 );
			$variation_id = absint( $row['variation_id'] ?? 0 );
			$product      = $variation_id && function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : false;
			if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
			}
			$product_name = $product instanceof WC_Product ? (string) $product->get_name() : 'محصول #' . $product_id;
			$product_url  = $product_id ? get_permalink( $product_id ) : '';
			$phone        = jluxe_stock_alert_phone_decipher( (string) ( $row['phone_cipher'] ?? '' ) );
			$manual_sent  = (string) ( $row['manual_notification_sent_at'] ?? '' );
			?>
			<tr>
				<td>
					<?php if ( '' !== (string) $product_url ) : ?><a href="<?php echo esc_url( $product_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $product_name ); ?></a><?php else : ?><?php echo esc_html( $product_name ); ?><?php endif; ?>
					<?php if ( $variation_id ) : ?><br /><small>شناسهٔ تنوع: <?php echo esc_html( (string) $variation_id ); ?></small><?php endif; ?>
				</td>
				<td dir="ltr"><?php echo '' !== $phone ? esc_html( '0' . $phone ) : '—'; ?></td>
				<td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
				<td><?php echo ! empty( $row['notification_sent_at'] ) ? 'پذیرفته‌شده: ' . esc_html( (string) $row['notification_sent_at'] ) : 'منتظر موجودی'; ?></td>
				<td><?php echo '' !== $manual_sent ? 'پذیرفته‌شده: ' . esc_html( $manual_sent ) : 'ارسال نشده'; ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="jluxe_stock_alert_manual_send" />
						<input type="hidden" name="subscription_id" value="<?php echo esc_attr( (string) $id ); ?>" />
						<?php wp_nonce_field( 'jluxe_stock_alert_manual_' . $id ); ?>
						<button type="submit" class="button"<?php disabled( ! jluxe_stock_alert_sms_available() ); ?> onclick="return confirm('<?php echo '' !== $manual_sent ? 'ارسال مجدد پیامک دستی؟' : 'پیامک دستی برای این شماره ارسال شود؟'; ?>');"><?php echo '' !== $manual_sent ? 'ارسال دوباره' : 'ارسال پیامک'; ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	$total_pages = max( 1, (int) ceil( $data['total'] / 25 ) );
	if ( $total_pages > 1 ) :
		?>
		<p class="tablenav-pages">
			<span class="displaying-num"><?php echo esc_html( (string) $data['total'] ); ?> درخواست</span>
			<?php if ( $page > 1 ) : ?><a class="button" href="<?php echo esc_url( jluxe_stock_alert_admin_page_url( array( 'stock_page' => $page - 1 ) ) ); ?>">درخواست‌های جدیدتر</a><?php endif; ?>
			<?php if ( $page < $total_pages ) : ?><a class="button" href="<?php echo esc_url( jluxe_stock_alert_admin_page_url( array( 'stock_page' => $page + 1 ) ) ); ?>">درخواست‌های قدیمی‌تر</a><?php endif; ?>
		</p>
		<?php
	endif;
}

/** Server-rendered opt-in trigger sits beside the real purchase controls. */
function jluxe_render_stock_alert_trigger( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$is_variable = $product->is_type( 'variable' );
	$visible     = ! $product->is_in_stock();
	if ( ! $visible && ! $is_variable ) {
		return;
	}
	$product_id = $product->get_id();
	?>
	<div class="jluxe-stock-alert-trigger" data-jluxe-stock-alert-trigger data-jluxe-stock-alert-default-visible="<?php echo $visible ? 'true' : 'false'; ?>" data-jluxe-product-id="<?php echo esc_attr( (string) $product_id ); ?>"<?php echo $visible ? '' : ' hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="jluxe-stock-alert-trigger__message">این محصول فعلاً موجود نیست</p>
		<button type="button" class="jluxe-stock-alert-trigger__button" data-jluxe-stock-alert-open aria-haspopup="dialog" aria-controls="jluxe-stock-alert-dialog-<?php echo esc_attr( (string) $product_id ); ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-variation-id="0">وقتی موجود شد خبرم کن</button>
	</div>
	<?php
}

/** A single form, outside the WooCommerce cart form, is reused for parent/variation alerts. */
function jluxe_render_stock_alert_dialog( $product ): void {
	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
		return;
	}
	$product_id = $product->get_id();
	$dialog_id  = 'jluxe-stock-alert-dialog-' . $product_id;
	?>
		<dialog class="jluxe-stock-alert-dialog" data-jluxe-stock-alert-dialog id="<?php echo esc_attr( $dialog_id ); ?>" tabindex="-1" aria-modal="true" aria-labelledby="<?php echo esc_attr( $dialog_id . '-title' ); ?>" aria-describedby="<?php echo esc_attr( $dialog_id . '-description' ); ?>">
		<div class="jluxe-stock-alert-dialog__panel">
			<button type="button" class="jluxe-stock-alert-dialog__close" data-jluxe-stock-alert-close aria-label="بستن پنجره">×</button>
			<h2 id="<?php echo esc_attr( $dialog_id . '-title' ); ?>">وقتی موجود شد خبرم کن</h2>
			<p id="<?php echo esc_attr( $dialog_id . '-description' ); ?>" class="jluxe-stock-alert-dialog__description">برای دریافت یک پیامک دربارهٔ موجودشدنِ «<?php echo esc_html( $product->get_name() ); ?>»، شماره موبایل‌تان را وارد کنید.</p>
			<form data-jluxe-stock-alert-form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
				<input type="hidden" name="action" value="jluxe_stock_alert_signup" />
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'jluxe_stock_alert_signup' ) ); ?>" />
				<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />
				<input type="hidden" name="variation_id" value="0" data-jluxe-stock-alert-variation />
				<label for="<?php echo esc_attr( $dialog_id . '-phone' ); ?>">شماره موبایل</label>
				<input id="<?php echo esc_attr( $dialog_id . '-phone' ); ?>" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="64" dir="ltr" placeholder="۰۹۱۲۱۲۳۴۵۶۷" required />
				<p class="jluxe-stock-alert-dialog__consent">با ثبت شماره، پیامک‌های مربوط به همین درخواست را دریافت می‌کنید: پیامک خودکار هنگام موجودشدن یا در صورت نیاز ارسال دستیِ مدیر فروشگاه. شماره به این درخواست متصل و رمزگذاری‌شده نگهداری می‌شود.</p>
				<p class="jluxe-stock-alert-dialog__status" data-jluxe-stock-alert-status role="status" aria-live="polite" aria-atomic="true" tabindex="-1"></p>
				<button type="submit" class="jluxe-stock-alert-dialog__submit">ثبت درخواست</button>
			</form>
		</div>
	</dialog>
	<?php
}
