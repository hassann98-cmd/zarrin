<?php
/**
 * Order tracking API 1.2: POST only, private/no-store on every response.
 * An order number and phone permit a status summary, not disclosure of the
 * customer's identity, purchases or carrier tracking token. Full detail also
 * requires the WordPress-authenticated owner. Cookie authentication in the
 * browser also requires a valid REST nonce; identity is never taken from input.
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
	);
	if ( $is_owner ) {
		$data['customer'] = jluxe_build_customer_data( $order );
		$data['shipping'] = jluxe_build_shipping_data( $order );
		$data['items'] = jluxe_build_items_data( $order );
	}

	return new WP_REST_Response(
		array( 'success' => true, 'version' => '1.2', 'data' => $data ),
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
function jluxe_build_order_data( WC_Order $order, bool $include_private = false ) {
	$status    = $order->get_status();
	$date_obj  = $order->get_date_created();
	$timestamp = $date_obj ? $date_obj->getTimestamp() : time();

	$data = array(
		'order_number'   => $order->get_order_number(),
		'status'         => $status,
		'status_label'   => jluxe_get_status_label( $status ),
		'created_date'   => array(
			'gregorian' => $date_obj ? $date_obj->date( 'Y-m-d H:i' ) : null,
			'jalali'    => $date_obj ? jluxe_gregorian_timestamp_to_jalali_string( $timestamp ) : null,
		),
	);
	if ( $include_private ) {
		$data['order_id'] = $order->get_id();
		$data['total'] = jluxe_format_price( $order->get_total(), $order->get_currency() );
		$data['payment_method'] = $order->get_payment_method_title();
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

function jluxe_build_shipping_data( WC_Order $order ) {
	$tracking = jluxe_get_tracking_info( $order );

	return array(
		'shipping_method'  => $order->get_shipping_method() ? $order->get_shipping_method() : null,
		'shipping_company' => $tracking['shipping_company'],
		'tracking_code'    => $tracking['tracking_code'],
		'tracking_date'    => $tracking['tracking_date'],
	);
}

function jluxe_get_tracking_info( WC_Order $order ) {

	/*
	 * پنل واقعیِ ادمین برای ثبت کد پیگیری همون متاباکسِ افزونه‌ی jluxe-sms
	 * (inc/sms/class-jsms-order-meta.php) هست که روی _jsms_tracking/_jsms_courier
	 * ذخیره می‌کنه — نه _jluxe_tracking_code. قبلاً این تابع اصلاً این کلیدها رو
	 * چک نمی‌کرد، برای همین کدِ پیگیریِ ذخیره‌شده توسط ادمین هیچ‌وقت توی صفحه‌ی
	 * پیگیریِ سفارشِ مشتری (REST /order-track) نمایش داده نمی‌شد — با تستِ زنده
	 * (ثبتِ کد در ادمین، بعد چکِ همون REST endpoint) پیدا و تأیید شد.
	 */
	$jsms_code = $order->get_meta( '_jsms_tracking' );
	if ( '' !== $jsms_code ) {
		$jsms_courier = $order->get_meta( '_jsms_courier' );
		return array(
			'shipping_company' => ( '' !== $jsms_courier ) ? $jsms_courier : null,
			'tracking_code'    => $jsms_code,
			'tracking_date'    => $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
		);
	}

	$company = $order->get_meta( '_jluxe_shipping_company' );
	$code    = $order->get_meta( '_jluxe_tracking_code' );
	$date    = $order->get_meta( '_jluxe_tracking_date' );

	if ( '' !== $code ) {
		return array(
			'shipping_company' => ( '' !== $company ) ? $company : null,
			'tracking_code'    => $code,
			'tracking_date'    => ( '' !== $date ) ? $date : null,
		);
	}

	$tracking_items = $order->get_meta( '_wc_shipment_tracking_items' );

	if ( is_array( $tracking_items ) && ! empty( $tracking_items ) ) {
		$first = reset( $tracking_items );

		if ( ! empty( $first['tracking_number'] ) ) {
			$provider = ! empty( $first['tracking_provider'] )
				? $first['tracking_provider']
				: ( ! empty( $first['custom_tracking_provider'] ) ? $first['custom_tracking_provider'] : null );

			return array(
				'shipping_company' => $provider,
				'tracking_code'    => $first['tracking_number'],
				'tracking_date'    => ! empty( $first['date_shipped'] ) ? $first['date_shipped'] : null,
			);
		}
	}

	$fallback_code_keys    = array( '_tracking_number', '_tracking_code', '_shipment_tracking_number' );
	$fallback_company_keys = array( '_tracking_company', '_tracking_provider', '_shipping_company' );
	$fallback_date_keys    = array( '_tracking_date', '_date_shipped', '_shipped_date' );

	$found_code = '';
	foreach ( $fallback_code_keys as $meta_key ) {
		$value = $order->get_meta( $meta_key );
		if ( '' !== $value ) {
			$found_code = $value;
			break;
		}
	}

	if ( '' === $found_code ) {
		return array(
			'shipping_company' => null,
			'tracking_code'    => null,
			'tracking_date'    => null,
		);
	}

	$found_company = '';
	foreach ( $fallback_company_keys as $meta_key ) {
		$value = $order->get_meta( $meta_key );
		if ( '' !== $value ) {
			$found_company = $value;
			break;
		}
	}

	$found_date = '';
	foreach ( $fallback_date_keys as $meta_key ) {
		$value = $order->get_meta( $meta_key );
		if ( '' !== $value ) {
			$found_date = $value;
			break;
		}
	}

	return array(
		'shipping_company' => ( '' !== $found_company ) ? $found_company : null,
		'tracking_code'    => $found_code,
		'tracking_date'    => ( '' !== $found_date ) ? $found_date : null,
	);
}

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
		'pending'    => 'در انتظار پرداخت',
		'on-hold'    => 'در انتظار بررسی پرداخت',
		'processing' => 'در حال آماده‌سازی',
		'completed'  => 'تکمیل شده',
		'cancelled'  => 'لغو شده',
		'failed'     => 'پرداخت ناموفق',
		'refunded'   => 'بازگشت وجه',
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

function jluxe_build_timeline_data( WC_Order $order ) {

	$steps = array(
		1 => 'ثبت سفارش',
		2 => 'در انتظار پرداخت',
		3 => 'تایید پرداخت',
		4 => 'در حال آماده‌سازی',
		5 => 'ثبت کد رهگیری',
		6 => 'تکمیل سفارش',
	);

	$status        = $order->get_status();
	$tracking      = jluxe_get_tracking_info( $order );
	$tracking_code = $tracking['tracking_code'];
	$is_cancelled  = in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true );

	$current_step = 1;

	if ( ! $is_cancelled ) {
		switch ( $status ) {
			case 'pending':
			case 'on-hold':
				$current_step = 2;
				break;
			case 'processing':
				$current_step = ( ! empty( $tracking_code ) ) ? 5 : 4;
				break;
			case 'completed':
				$current_step = 6;
				break;
			default:
				// A custom order status is not evidence that payment or delivery occurred.
				$current_step = 1;
		}
	}

	$timeline = array();
	foreach ( $steps as $step_number => $label ) {
		$timeline[] = array(
			'step'   => $step_number,
			'label'  => $label,
			'done'   => ! $is_cancelled && $step_number < $current_step,
			'active' => ! $is_cancelled && $step_number === $current_step,
		);
	}

	return array(
		'is_cancelled' => $is_cancelled,
		'cancel_label' => $is_cancelled ? jluxe_get_status_label( $status ) : null,
		'current_step' => $current_step,
		'steps'        => $timeline,
	);
}
