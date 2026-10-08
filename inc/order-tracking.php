<?php
/**
 * Order tracking API 1.3: POST only, private/no-store on every response.
 * An exact order-number/phone match permits a fulfillment summary and carrier
 * tracking details, but never customer identity, products, payment or address.
 * Full personal/order detail also requires the authenticated order owner. Cookie
 * identity is trusted only after WordPress validates the REST nonce.
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------
 * 0) تنظیمات قابل تغییر توسط ادمین
 * ------------------------------------------------------------------ */

/* ---------------------------------------------------------------------
 * 1) ثبت مسیر REST API
 * ------------------------------------------------------------------ */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'jluxe/v1',
			'/order-track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'jluxe_order_track_handler',
				'permission_callback' => '__return_true', // صفحه پیگیری برای عموم است
				'args'                => array(
					'order_number' => array(
						'required'          => true,
						'type'              => 'string',
						'maxLength'         => 100,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'        => array(
						'required'          => true,
						'type'              => 'string',
						'maxLength'         => 64,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
);

/* ---------------------------------------------------------------------
 * 2) هندلر اصلی درخواست
 * ------------------------------------------------------------------ */
function jluxe_order_track_handler( WP_REST_Request $request ) {

	if ( 'POST' !== $request->get_method() ) {
		return jluxe_track_error_response( 'Method not allowed', 405 );
	}
	if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'wc_get_orders' ) || ! class_exists( 'WC_Order' ) ) {
		return jluxe_track_error_response( 'پیگیری سفارش در حال حاضر در دسترس نیست.', 503 );
	}

	if ( ! jluxe_track_rate_limit_ok() ) {
		return jluxe_track_error_response(
			'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً چند دقیقه دیگر تلاش کنید.',
			429
		);
	}

	$order_number_raw = $request->get_param( 'order_number' );
	$phone_raw         = $request->get_param( 'phone' );

	$order_number_input = jluxe_convert_digits_to_en( $order_number_raw );
	$order_number_input = trim( ltrim( trim( $order_number_input ), '#' ) );
	$phone_last10        = jluxe_normalize_phone_last10( $phone_raw );

	if ( '' === $order_number_input || strlen( $phone_last10 ) < 9 ) {
		return jluxe_track_not_found_error();
	}

	$order = jluxe_find_order_by_number( $order_number_input );

	if ( ! $order instanceof WC_Order ) {
		return jluxe_track_not_found_error();
	}

	$order_phone_last10 = jluxe_normalize_phone_last10( $order->get_billing_phone() );

	if ( empty( $order_phone_last10 ) || ! hash_equals( $order_phone_last10, $phone_last10 ) ) {
		return jluxe_track_not_found_error();
	}

	// Cookie identity is trustworthy in REST only after core has checked X-WP-Nonce.
	// A guest order (customer_id=0) never matches an anonymous visitor (user_id=0).
	$is_owner = get_current_user_id() > 0 && (int) $order->get_customer_id() === get_current_user_id();
	$data = array(
		'access'   => $is_owner ? 'owner' : 'status_only',
		'order'    => jluxe_build_order_data( $order, $is_owner ),
		'timeline' => jluxe_build_timeline_data( $order ),
		// The phone + order-number match is the quick-tracking gate. Return only
		// fulfillment data here; customer, products, address and payment remain private.
		'shipping' => jluxe_build_shipping_data( $order ),
	);
	if ( $is_owner ) {
		$data['customer'] = jluxe_build_customer_data( $order );
		$data['items'] = jluxe_build_items_data( $order );
	}

	return new WP_REST_Response(
		array( 'success' => true, 'version' => '1.3', 'data' => $data ),
		200,
		jluxe_private_rest_headers()
	);
}

/* ---------------------------------------------------------------------
 * 3) پیدا کردن سفارش — تفاوت get_id() و get_order_number()
 * ------------------------------------------------------------------ */
function jluxe_find_order_by_number( $input ) {

	$numeric_id = absint( $input );
	if ( $numeric_id > 0 ) {
		$order = wc_get_order( $numeric_id );

		if ( $order instanceof WC_Order ) {
			$display_number = (string) $order->get_order_number();

			if ( $display_number === $input || (string) $order->get_id() === $input ) {
				return $order;
			}
		}
	}

	$meta_keys_to_try = array( '_order_number', '_order_number_formatted', 'order_number' );

	foreach ( $meta_keys_to_try as $meta_key ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => $meta_key,
				'meta_value' => $input,
				'return'     => 'objects',
			)
		);

		if ( ! empty( $orders ) && $orders[0] instanceof WC_Order ) {
			return $orders[0];
		}
	}

	return null;
}

/* ---------------------------------------------------------------------
 * 4) توابع کمکی امنیتی / نرمال‌سازی
 * ------------------------------------------------------------------ */

function jluxe_convert_digits_to_en( $string ) {
	return jluxe_ascii_digits( (string) $string );
}

function jluxe_convert_digits_to_fa( $string ) {
	$english = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	return str_replace( $english, $persian, (string) $string );
}

function jluxe_normalize_phone_last10( $phone ) {
	return jluxe_normalize_phone( (string) $phone );
}

function jluxe_track_error_response( $message, $status = 404 ) {
	return new WP_REST_Response(
		array(
			'success' => false,
			'message' => $message,
		),
		$status,
		jluxe_private_rest_headers()
	);
}

function jluxe_track_not_found_error() {
	return jluxe_track_error_response( 'اطلاعات سفارش صحیح نیست.', 404 );
}

/**
 * محدودسازی درخواست بر اساس IP: حداکثر ۱۰ درخواست در یک پنجرهٔ زمانی
 * ثابت ۱۰ دقیقه‌ای. از jluxe_theme_get_client_ip() مشترک (theme-settings-ai.php)
 * استفاده می‌کنه تا تابع تکراری تعریف نشه.
 */
function jluxe_track_rate_limit_ok() {
	return jluxe_security_rate_limit( 'order_track', jluxe_theme_get_client_ip(), 10, 10 * MINUTE_IN_SECONDS );
}

/* ---------------------------------------------------------------------
 * 5) تبدیل تاریخ میلادی به شمسی (Jalali)
 * ------------------------------------------------------------------ */
function jluxe_gregorian_timestamp_to_jalali_string( $timestamp, $format = 'Y/m/d H:i' ) {

	if ( function_exists( 'jdate' ) ) {
		return jdate( $format, $timestamp );
	}

	$local_time = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() );
	list( $jy, $jm, $jd ) = jluxe_gregorian_to_jalali(
		(int) $local_time->format( 'Y' ),
		(int) $local_time->format( 'n' ),
		(int) $local_time->format( 'j' )
	);

	$time_part = $local_time->format( 'H:i' );

	$jalali = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );

	if ( false !== strpos( $format, 'H:i' ) ) {
		$jalali .= ' ' . $time_part;
	}

	return $jalali;
}

function jluxe_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_days_in_month = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

	$gy2  = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days = 355666
		+ ( 365 * $gy )
		+ intdiv( $gy2 + 3, 4 )
		- intdiv( $gy2 + 99, 100 )
		+ intdiv( $gy2 + 399, 400 )
		+ $gd
		+ $g_days_in_month[ $gm - 1 ];

	$jy = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;

	$jy += 4 * intdiv( $days, 1461 );
	$days %= 1461;

	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}

	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}

	return array( $jy, $jm, $jd );
}

/* ---------------------------------------------------------------------
 * 6) فرمت قیمت
 * ------------------------------------------------------------------ */
function jluxe_format_price( $raw_amount, string $currency = '' ) {
	$currency = $currency ?: get_woocommerce_currency();
	$unit = jluxe_currency_label( $currency );
	$amount = (float) $raw_amount;
	$formatted = jluxe_convert_digits_to_fa( number_format( $amount, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) );
	return array( 'raw' => $amount, 'formatted' => $formatted . ' ' . $unit, 'unit' => $unit );
}

/* ---------------------------------------------------------------------
 * 7) توابع ساخت بخش‌های مختلف خروجی JSON
 * ------------------------------------------------------------------ */
function jluxe_get_order_display_status_label( WC_Order $order ): string {
	$status = $order->get_status();
	if ( 'processing' === $status && ! empty( jluxe_get_tracking_info( $order )['tracking_code'] ) ) {
		return 'ارسال شده';
	}
	return jluxe_get_status_label( $status );
}

/** Keep customer-facing payment wording concise without changing WooCommerce's stored gateway title. */
function jluxe_get_payment_method_label( WC_Order $order ): string {
	$method_id = strtolower( (string) $order->get_payment_method() );
	$title     = trim( wp_strip_all_tags( (string) $order->get_payment_method_title() ) );
	$compact   = str_replace( array( ' ', "\t", "\n", '‌', '_', '-' ), '', $title );
	if (
		false !== strpos( $method_id, 'card-to-card' ) ||
		false !== strpos( $method_id, 'card_to_card' ) ||
		false !== strpos( $method_id, 'card2card' ) ||
		false !== strpos( (string) $compact, 'کارتبهکارت' )
	) {
		return 'کارت به کارت';
	}
	return '' !== $title ? $title : '—';
}

function jluxe_build_order_data( WC_Order $order, bool $include_private = false ) {
	$status    = $order->get_status();
	$date_obj  = $order->get_date_created();
	$timestamp = $date_obj ? $date_obj->getTimestamp() : time();

	$data = array(
		'order_number'   => $order->get_order_number(),
		'status'         => $status,
		'status_label'   => jluxe_get_order_display_status_label( $order ),
		'created_date'   => array(
			'gregorian' => $date_obj ? $date_obj->date( 'Y-m-d H:i' ) : null,
			'jalali'    => $date_obj ? jluxe_gregorian_timestamp_to_jalali_string( $timestamp ) : null,
		),
	);
	if ( $include_private ) {
		$data['order_id'] = $order->get_id();
		$data['total'] = jluxe_format_price( $order->get_total(), $order->get_currency() );
		$data['payment_method'] = jluxe_get_payment_method_label( $order );
	}
	return $data;
}

function jluxe_build_customer_data( WC_Order $order ) {
	$address_parts = array_filter(
		array(
			$order->get_billing_state(),
			$order->get_billing_city(),
			$order->get_billing_address_1(),
			$order->get_billing_address_2(),
		)
	);

	return array(
		'first_name' => $order->get_billing_first_name(),
		'last_name'  => $order->get_billing_last_name(),
		'full_name'  => trim( $order->get_formatted_billing_full_name() ),
		'phone'      => $order->get_billing_phone(),
		'address'    => implode( '، ', $address_parts ),
	);
}

function jluxe_tracking_carriers(): array {
	return array(
		'post'   => 'پست',
		'tipax'  => 'تیپاکس',
		'chapar' => 'چاپار',
		'other'  => 'سایر',
	);
}

function jluxe_tracking_carrier_key( $company ): string {
	$company = trim( (string) $company );
	if ( '' === $company ) {
		return '';
	}
	$lower = strtolower( $company );
	if ( false !== strpos( $company, 'پست' ) || false !== strpos( $lower, 'post' ) ) {
		return 'post';
	}
	if ( false !== strpos( $company, 'تیپاکس' ) || false !== strpos( $lower, 'tipax' ) ) {
		return 'tipax';
	}
	if ( false !== strpos( $company, 'چاپار' ) || false !== strpos( $lower, 'chapar' ) ) {
		return 'chapar';
	}
	return 'other';
}

/** Return only known official carrier URLs; the postal portal still requires its human CAPTCHA. */
function jluxe_get_tracking_url( $company, $tracking_code ): string {
	$code = trim( preg_replace( '/\s+/u', '', jluxe_convert_digits_to_en( (string) $tracking_code ) ) );
	if ( '' === $code ) {
		return '';
	}
	$encoded_code = rawurlencode( $code );
	switch ( jluxe_tracking_carrier_key( $company ) ) {
		case 'post':
			// The official portal requires manual entry and CAPTCHA; no public prefill parameter is documented.
			return 'https://tracking.post.ir/';
		case 'tipax':
			// The official page requires manual entry; no supported prefill parameter is documented.
			return 'https://tipaxco.com/tracking';
		case 'chapar':
			// Chapar's official route accepts the bill number as its path segment.
			return 'https://www.chaparnet.com/track/' . $encoded_code;
		default:
			return '';
	}
}

function jluxe_build_shipping_data( WC_Order $order ): array {
	$tracking = jluxe_get_tracking_info( $order );
	$method   = trim( (string) $order->get_shipping_method() );
	$company  = ! empty( $tracking['shipping_company'] ) ? trim( (string) $tracking['shipping_company'] ) : $method;
	$code     = (string) ( $tracking['tracking_code'] ?? '' );
	$carrier  = jluxe_tracking_carrier_key( $company );

	return array(
		'shipping_method'       => '' !== $method ? $method : null,
		'shipping_company'      => '' !== $company ? $company : null,
		'tracking_code'         => '' !== $code ? $code : null,
		'tracking_date'         => $tracking['tracking_date'] ?? null,
		'shipping_note'         => $tracking['shipping_note'] ?? null,
		'tracking_url'          => jluxe_get_tracking_url( $company, $code ),
		'requires_captcha'      => 'post' === $carrier && '' !== $code,
	);
}

function jluxe_tracking_info( WC_Order $order, $company, $code, $date ): array {
	$note = sanitize_textarea_field( (string) $order->get_meta( '_jluxe_shipping_note' ) );
	return array(
		'shipping_company' => '' !== (string) $company ? (string) $company : null,
		'tracking_code'    => '' !== (string) $code ? trim( jluxe_convert_digits_to_en( (string) $code ) ) : null,
		'tracking_date'    => '' !== (string) $date ? (string) $date : null,
		'shipping_note'    => '' !== $note ? $note : null,
	);
}

function jluxe_get_tracking_info( WC_Order $order ): array {
	// An explicit admin save (including clearing a previous code) wins over
	// legacy/plugin metadata, so a stale provider token cannot reappear.
	if ( 'yes' === $order->get_meta( '_jluxe_tracking_configured' ) ) {
		return jluxe_tracking_info(
			$order,
			$order->get_meta( '_jluxe_shipping_company' ),
			$order->get_meta( '_jluxe_tracking_code' ),
			$order->get_meta( '_jluxe_tracking_date' )
		);
	}

	// Keep reading the established Ronagh SMS metadata for existing orders.
	$jsms_code = $order->get_meta( '_jsms_tracking' );
	if ( '' !== (string) $jsms_code ) {
		$jsms_courier = $order->get_meta( '_jsms_courier' );
		$date         = $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : '';
		return jluxe_tracking_info( $order, $jsms_courier, $jsms_code, $date );
	}

	$company = $order->get_meta( '_jluxe_shipping_company' );
	$code    = $order->get_meta( '_jluxe_tracking_code' );
	$date    = $order->get_meta( '_jluxe_tracking_date' );
	if ( '' !== (string) $code ) {
		return jluxe_tracking_info( $order, $company, $code, $date );
	}

	$tracking_items = $order->get_meta( '_wc_shipment_tracking_items' );
	if ( is_array( $tracking_items ) && ! empty( $tracking_items ) ) {
		$first = reset( $tracking_items );
		if ( ! empty( $first['tracking_number'] ) ) {
			$provider = ! empty( $first['tracking_provider'] )
				? $first['tracking_provider']
				: ( ! empty( $first['custom_tracking_provider'] ) ? $first['custom_tracking_provider'] : null );
			return jluxe_tracking_info( $order, $provider, $first['tracking_number'], $first['date_shipped'] ?? '' );
		}
	}

	$fallback_code_keys    = array( '_tracking_number', '_tracking_code', '_shipment_tracking_number' );
	$fallback_company_keys = array( '_tracking_company', '_tracking_provider', '_shipping_company' );
	$fallback_date_keys    = array( '_tracking_date', '_date_shipped', '_shipped_date' );
	$found_code            = '';
	foreach ( $fallback_code_keys as $meta_key ) {
		$value = $order->get_meta( $meta_key );
		if ( '' !== (string) $value ) {
			$found_code = $value;
			break;
		}
	}
	$found_company = '';
	$found_date    = '';
	if ( '' !== (string) $found_code ) {
		foreach ( $fallback_company_keys as $meta_key ) {
			$value = $order->get_meta( $meta_key );
			if ( '' !== (string) $value ) {
				$found_company = $value;
				break;
			}
		}
		foreach ( $fallback_date_keys as $meta_key ) {
			$value = $order->get_meta( $meta_key );
			if ( '' !== (string) $value ) {
				$found_date = $value;
				break;
			}
		}
	}
	return jluxe_tracking_info( $order, $found_company, $found_code, $found_date );
}

/** Add shipment data fields to the WooCommerce order editor (legacy and HPOS screens). */
function jluxe_render_order_tracking_admin_fields( $order ): void {
	if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
		return;
	}

	$tracking       = jluxe_get_tracking_info( $order );
	$existing_carrier = ! empty( $tracking['shipping_company'] ) ? $tracking['shipping_company'] : $order->get_shipping_method();
	$carrier_key    = jluxe_tracking_carrier_key( $existing_carrier );
	$custom_carrier = 'other' === $carrier_key ? (string) $existing_carrier : '';
	$carriers       = jluxe_tracking_carriers();
	?>
	<div class="order_data_column" style="clear:both;width:100%;padding-top:12px;">
		<h4><?php esc_html_e( 'ارسال و پیگیری مشتری', 'zarrin' ); ?></h4>
		<p class="form-field form-field-wide">
			<label for="jluxe_shipping_company"><?php esc_html_e( 'شرکت حمل‌ونقل', 'zarrin' ); ?></label>
			<select id="jluxe_shipping_company" name="jluxe_shipping_company" class="wc-enhanced-select" style="width:100%;">
				<option value=""><?php esc_html_e( 'انتخاب روش ارسال', 'zarrin' ); ?></option>
				<?php foreach ( $carriers as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $carrier_key, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="form-field form-field-wide">
			<label for="jluxe_shipping_company_custom"><?php esc_html_e( 'نام شرکت دیگر (در صورت انتخاب «سایر»)', 'zarrin' ); ?></label>
			<input type="text" id="jluxe_shipping_company_custom" name="jluxe_shipping_company_custom" value="<?php echo esc_attr( $custom_carrier ); ?>" maxlength="100" />
		</p>
		<p class="form-field form-field-wide">
			<label for="jluxe_tracking_code"><?php esc_html_e( 'کد پیگیری مرسوله', 'zarrin' ); ?></label>
			<input type="text" id="jluxe_tracking_code" name="jluxe_tracking_code" value="<?php echo esc_attr( $tracking['tracking_code'] ?? '' ); ?>" maxlength="100" dir="ltr" inputmode="text" autocomplete="off" />
		</p>
		<p class="form-field form-field-wide">
			<label for="jluxe_shipping_note"><?php esc_html_e( 'یادداشت ارسال برای مشتری', 'zarrin' ); ?></label>
			<textarea id="jluxe_shipping_note" name="jluxe_shipping_note" rows="3" maxlength="1000"><?php echo esc_textarea( $tracking['shipping_note'] ?? '' ); ?></textarea>
		</p>
		<p class="form-field form-field-wide description" style="margin-top:0;">
			<?php esc_html_e( 'روش ارسال، کد و این یادداشت در حساب کاربری و پیگیری سریع سفارش نمایش داده می‌شود. سامانهٔ پست کپچا را خودش درخواست می‌کند و باید توسط مشتری تکمیل شود.', 'zarrin' ); ?>
		</p>
		<input type="hidden" name="jluxe_shipping_tracking_present" value="1" />
		<?php wp_nonce_field( 'jluxe_save_order_tracking_' . $order->get_id(), 'jluxe_shipping_tracking_nonce' ); ?>
	</div>
	<?php
}

/** Save the custom shipment fields with order APIs so HPOS and post storage both work. */
function jluxe_save_order_tracking_admin_fields( $order_id, $order = null ): void {
	if ( empty( $_POST['jluxe_shipping_tracking_present'] ) ) {
		return;
	}
	$order_id = absint( $order_id );
	$nonce    = isset( $_POST['jluxe_shipping_tracking_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jluxe_shipping_tracking_nonce'] ) ) : '';
	if (
		! $order_id ||
		'' === $nonce ||
		! wp_verify_nonce( $nonce, 'jluxe_save_order_tracking_' . $order_id ) ||
		( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'edit_shop_orders' ) )
	) {
		return;
	}
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$carriers    = jluxe_tracking_carriers();
	$carrier_key = isset( $_POST['jluxe_shipping_company'] ) ? sanitize_key( wp_unslash( $_POST['jluxe_shipping_company'] ) ) : '';
	$company     = '';
	if ( 'other' === $carrier_key ) {
		$company = isset( $_POST['jluxe_shipping_company_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['jluxe_shipping_company_custom'] ) ) : '';
	} elseif ( isset( $carriers[ $carrier_key ] ) ) {
		$company = $carriers[ $carrier_key ];
	}
	$company = jluxe_substr( $company, 0, 100 );

	$raw_code = isset( $_POST['jluxe_tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['jluxe_tracking_code'] ) ) : '';
	$code     = jluxe_substr( trim( preg_replace( '/\s+/u', '', jluxe_convert_digits_to_en( $raw_code ) ) ), 0, 100 );
	$note     = jluxe_substr( isset( $_POST['jluxe_shipping_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['jluxe_shipping_note'] ) ) : '', 0, 1000 );
	$old_code = (string) $order->get_meta( '_jluxe_tracking_code' );

	$order->update_meta_data( '_jluxe_tracking_configured', 'yes' );
	$order->update_meta_data( '_jluxe_shipping_company', $company );
	if ( '' !== $code ) {
		$order->update_meta_data( '_jluxe_tracking_code', $code );
		if ( $code !== $old_code || ! $order->get_meta( '_jluxe_tracking_date' ) ) {
			$order->update_meta_data( '_jluxe_tracking_date', current_time( 'mysql', true ) );
		}
	} else {
		$order->delete_meta_data( '_jluxe_tracking_code' );
		$order->delete_meta_data( '_jluxe_tracking_date' );
	}
	if ( '' !== $note ) {
		$order->update_meta_data( '_jluxe_shipping_note', $note );
	} else {
		$order->delete_meta_data( '_jluxe_shipping_note' );
	}
	$order->save();
}

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_render_order_tracking_admin_fields', 20, 1 );
add_action( 'woocommerce_process_shop_order_meta', 'jluxe_save_order_tracking_admin_fields', 45, 2 );

function jluxe_build_items_data( WC_Order $order ) {
	$items = array();

	foreach ( $order->get_items() as $item_id => $item ) {

		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}

		$product = $item->get_product();

		$attributes = array();
		foreach ( $item->get_formatted_meta_data() as $meta ) {
			$attributes[] = array(
				'name'  => wp_strip_all_tags( $meta->display_key ),
				'value' => wp_strip_all_tags( $meta->display_value ),
			);
		}

		$thumbnail_url = wc_placeholder_img_src( 'thumbnail' );
		$full_url      = wc_placeholder_img_src( 'full' );

		if ( $product ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$thumb_src = wp_get_attachment_image_url( $image_id, 'thumbnail' );
				$full_src  = wp_get_attachment_image_url( $image_id, 'full' );

				if ( $thumb_src ) {
					$thumbnail_url = $thumb_src;
				}
				if ( $full_src ) {
					$full_url = $full_src;
				}
			}
		}

		$unit_price = $order->get_item_total( $item, false, false );
		$line_total = $order->get_line_total( $item, false, false );

		$items[] = array(
			'id'           => $item_id,
			'product_id'   => $product ? $product->get_id() : 0,
			'product_name' => $item->get_name(),
			'image'        => array(
				'thumbnail' => $thumbnail_url,
				'full'      => $full_url,
			),
			'quantity'     => (int) $item->get_quantity(),
			'price'        => jluxe_format_price( $unit_price, $order->get_currency() ),
			'subtotal'     => jluxe_format_price( $line_total, $order->get_currency() ),
			'sku'          => $product ? $product->get_sku() : null,
			'attributes'   => $attributes,
		);
	}

	return $items;
}

function jluxe_get_status_label( $status ) {
	$map = array(
		'pending'    => 'منتظر پرداخت',
		'on-hold'    => 'در انتظار تأیید پرداخت',
		'processing' => 'در حال آماده‌سازی',
		'completed'  => 'تکمیل شده',
		'cancelled'  => 'لغو شده',
		'failed'     => 'پرداخت ناموفق',
		'refunded'   => 'بازگشت وجه',
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

/** A compact 4-stage customer timeline derived only from WooCommerce status plus a saved carrier code. */
function jluxe_build_timeline_data( WC_Order $order ): array {
	$steps = array(
		1 => 'پرداخت',
		2 => 'آماده‌سازی',
		3 => 'ارسال',
		4 => 'تکمیل',
	);

	$status       = $order->get_status();
	$tracking     = jluxe_get_tracking_info( $order );
	$is_cancelled = in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true );
	$current_step = 0;

	if ( ! $is_cancelled ) {
		switch ( $status ) {
			case 'pending':
			case 'on-hold':
				$current_step = 1;
				break;
			case 'processing':
				$current_step = ! empty( $tracking['tracking_code'] ) ? 3 : 2;
				break;
			case 'completed':
				$current_step = 4;
				break;
			default:
				// Unknown/custom statuses are not guessed to mean paid, shipped or complete.
				$current_step = 0;
		}
	}

	$timeline = array();
	foreach ( $steps as $step_number => $label ) {
		$timeline[] = array(
			'step'   => $step_number,
			'label'  => $label,
			'done'   => ! $is_cancelled && $current_step > 0 && $step_number < $current_step,
			'active' => ! $is_cancelled && $current_step > 0 && $step_number === $current_step,
		);
	}

	return array(
		'is_cancelled' => $is_cancelled,
		'cancel_label' => $is_cancelled ? jluxe_get_status_label( $status ) : null,
		'current_step' => $current_step,
		'step_count'   => count( $steps ),
		'steps'        => $timeline,
	);
}
