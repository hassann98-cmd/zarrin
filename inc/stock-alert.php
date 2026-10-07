<?php
/** Explicit, privacy-conscious back-in-stock subscriptions for WooCommerce products. */
defined( 'ABSPATH' ) || exit;

const JLUXE_STOCK_ALERT_SCHEMA_VERSION = '1';

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
		PRIMARY KEY  (id),
		UNIQUE KEY subscription (product_id,variation_id,phone_hash),
		KEY pending (product_id,variation_id,notification_sent_at)
	) {$charset_collate};";
	dbDelta( $sql );
	update_option( 'jluxe_stock_alerts_schema_version', JLUXE_STOCK_ALERT_SCHEMA_VERSION, false );
	return true;
}
add_action( 'init', 'jluxe_stock_alerts_maybe_install', 5 );

/** A separate opt-in prevents borrowing an OTP template for product messages. */
function jluxe_stock_alert_sms_available(): bool {
	if ( ! function_exists( 'jluxe_get_theme_settings' ) || ! function_exists( 'jluxe_get_sms_api_key' ) ) {
		return false;
	}
	$sms = jluxe_get_theme_settings()['sms'] ?? array();
	if ( empty( $sms['stock_alert_enabled'] ) || '' === jluxe_get_sms_api_key() ) {
		return false;
	}
	if ( 'kavenegar' === ( $sms['provider'] ?? '' ) ) {
		return '' !== trim( (string) ( $sms['stock_alert_template'] ?? '' ) );
	}
	if ( 'melipayamak' === ( $sms['provider'] ?? '' ) ) {
		return '' !== trim( (string) ( $sms['username'] ?? '' ) ) && absint( $sms['stock_alert_body_id'] ?? 0 ) > 0;
	}
	return false;
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
	} elseif ( jluxe_product_has_available_offer( $product ) ) {
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
		'phone_cipher'         => $phone_cipher,
		'created_at'           => current_time( 'mysql' ),
		'notification_sent_at' => null,
	);
	if ( null !== $existing && '' !== (string) $existing ) {
		$updated = $wpdb->update( $table, $subscription_data, array( 'id' => absint( $existing ) ), array( '%s', '%s', '%s' ), array( '%d' ) );
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
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
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
			$updated = $wpdb->update( $table, $subscription_data, array( 'id' => absint( $duplicate ) ), array( '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) {
				wp_send_json_error( array( 'message' => 'ذخیرهٔ درخواست انجام نشد؛ دوباره تلاش کنید.' ), 503 );
			}
		}
	}

	wp_send_json_success( array( 'message' => 'درخواست شما ثبت شد؛ وقتی محصول موجود شود از طریق پیامک خبرتان می‌کنیم.' ) );
}
add_action( 'wp_ajax_jluxe_stock_alert_signup', 'jluxe_handle_stock_alert_signup' );
add_action( 'wp_ajax_nopriv_jluxe_stock_alert_signup', 'jluxe_handle_stock_alert_signup' );

/** Send only through a separately configured, single-variable transactional template. */
function jluxe_send_stock_alert_sms( string $phone, string $product_name ) {
	if ( ! jluxe_stock_alert_sms_available() ) {
		return new WP_Error( 'jluxe_stock_alert_sms_disabled', 'هشدار موجودی پیامکی پیکربندی نشده است.', array( 'status' => 503 ) );
	}
	$sms     = jluxe_get_theme_settings()['sms'];
	$api_key = jluxe_get_sms_api_key();
	$phone   = jluxe_normalize_phone( $phone );
	$name    = function_exists( 'jluxe_substr' ) ? jluxe_substr( sanitize_text_field( $product_name ), 0, 100 ) : substr( sanitize_text_field( $product_name ), 0, 100 );
	if ( '' === $phone || '' === $name ) {
		return new WP_Error( 'jluxe_stock_alert_bad_payload', 'اطلاعات اعلان معتبر نیست.', array( 'status' => 400 ) );
	}

	if ( 'kavenegar' === $sms['provider'] ) {
		$url = sprintf(
			'https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&token=%s&template=%s',
			rawurlencode( $api_key ),
			rawurlencode( '0' . $phone ),
			rawurlencode( $name ),
			rawurlencode( (string) $sms['stock_alert_template'] )
		);
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارسال پیامکِ موجودشدن ناموفق بود.', array( 'status' => 502 ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( (int) wp_remote_retrieve_response_code( $response ) < 200 || (int) wp_remote_retrieve_response_code( $response ) >= 300 || ! is_array( $body ) || 200 !== (int) ( $body['return']['status'] ?? 0 ) ) {
			return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارائه‌دهندهٔ پیامک ارسال را نپذیرفت.', array( 'status' => 502 ) );
		}
		return true;
	}

	$body_id = absint( $sms['stock_alert_body_id'] ?? 0 );
	$response = wp_remote_post(
		'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8' ),
			'body'    => array(
				'username' => (string) $sms['username'],
				'password' => $api_key,
				'to'       => '0' . $phone,
				'text'     => $name,
				'bodyId'   => $body_id,
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارسال پیامکِ موجودشدن ناموفق بود.', array( 'status' => 502 ) );
	}
	$http_code = (int) wp_remote_retrieve_response_code( $response );
	$body      = json_decode( wp_remote_retrieve_body( $response ), true );
	$value     = is_array( $body ) && isset( $body['Value'] ) ? (int) $body['Value'] : ( ctype_digit( trim( (string) wp_remote_retrieve_body( $response ) ) ) ? (int) trim( (string) wp_remote_retrieve_body( $response ) ) : 0 );
	if ( $http_code < 200 || $http_code >= 300 || $value <= 0 ) {
		return new WP_Error( 'jluxe_stock_alert_sms_upstream', 'ارائه‌دهندهٔ پیامک ارسال را نپذیرفت.', array( 'status' => 502 ) );
	}
	return true;
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
		if ( true !== jluxe_send_stock_alert_sms( $phone, $name ) ) {
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

/** Server-rendered opt-in trigger sits beside the real purchase controls. */
function jluxe_render_stock_alert_trigger( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$is_variable = $product->is_type( 'variable' );
	$visible     = ! jluxe_product_has_available_offer( $product );
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
				<p class="jluxe-stock-alert-dialog__consent">با ثبت شماره، فقط برای اطلاع از موجودشدن این محصول پیامک دریافت می‌کنید. شمارهٔ شما به این درخواست متصل و به‌صورت رمزگذاری‌شده نگهداری می‌شود.</p>
				<p class="jluxe-stock-alert-dialog__status" data-jluxe-stock-alert-status role="status" aria-live="polite" aria-atomic="true" tabindex="-1"></p>
				<button type="submit" class="jluxe-stock-alert-dialog__submit">ثبت درخواست</button>
			</form>
		</div>
	</dialog>
	<?php
}
