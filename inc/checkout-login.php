<?php
/**
 * تسویه‌حساب اصلی: ریدایرکت به ورود پیامکی + مدیریت آدرس در تسویه‌حساب.
 *
 * جریان:
 * ۱. مشتری مهمان که «ثبت سفارش» را می‌زند و لاگین اجباری است (تنظیم
 *    «ثبت‌نام در تسویه‌حساب الزامی است» در ووکامرس) و پیامک پوسته فعال
 *    است، مستقیم به صفحهٔ ورود OTP پوسته هدایت می‌شود؛ بعد از ورود به
 *    همین صفحهٔ تسویه‌حساب برمی‌گردد. هیچ کارت/دکمهٔ ورودی در تسویه‌حساب
 *    نمایش داده نمی‌شود و فرم پیش‌فرض ورود ووکامرس هم حذف است.
 * ۲. اگر ثبت‌نام الزامی نباشد، خرید مهمان بدون هیچ بخش ورودی انجام می‌شود.
 * ۳. اگر پیامک فعال نباشد، رفتار پیش‌فرض ووکامرس حفظ می‌شود.
 * ۴. مشتری لاگین‌کرده: نام/نام خانوادگی و تلفن (از OTP) در بالای بخش آدرس،
 *    لیست آدرس‌های ذخیره‌شده به‌صورت کارت انتخابی، و آکاردئون «آدرس جدید»
 *    (استان، شهر، آدرس، کدپستی، ایمیل) با دکمهٔ ذخیره در همان صفحه.
 *    ذخیرهٔ آدرس، متای حساب مشتری را به‌روز می‌کند و آدرس جدید به لیست
 *    اضافه و انتخاب می‌شود.
 *
 * این ماژول فقط تسویه‌حساب اصلی (classic/guided) را پوشش می‌دهد؛ مسیر DSN
 * موبایل دست نمی‌خورد. محاسبات سبد/حمل/مالیات/درگاه/سابمیت ووکامرس ثابت می‌مانند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا مشتری مهمان باید مستقیم به صفحهٔ ورود پیامکی هدایت شود؟
 *
 * هر سه شرط لازم است: مهمان، لاگین اجباری در ووکامرس، و پیامک فعال.
 * فقط در صفحهٔ تسویه‌حساب (نه صفحهٔ تشکر) اعمال می‌شود.
 */
function jluxe_checkout_login_redirect_needed(): bool {
	if ( is_user_logged_in() ) {
		return false;
	}
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return false;
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return false;
	}
	// پرداخت سفارش موجود (order-pay) نباید مهمان را به ورود بفرستد؛
	// مشتری در حال پرداخت سفارشِ خودش است، نه شروع خرید جدید.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
		return false;
	}
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->checkout ) {
		return false;
	}
	// سبد خالی: ووکامرس خودش به سبد برمی‌گرداند؛ ورودِ بیهوده لازم نیست.
	if ( WC()->cart && WC()->cart->is_empty() ) {
		return false;
	}
	if ( ! WC()->checkout->is_registration_required() ) {
		return false;
	}
	if ( ! function_exists( 'jluxe_otp_available' ) || ! jluxe_otp_available() ) {
		return false;
	}
	return true;
}

/**
 * ریدایرکت مستقیم مهمان (با لاگین اجباری) به صفحهٔ ورود OTP پوسته.
 */
function jluxe_checkout_redirect_to_otp_login(): void {
	if ( ! jluxe_checkout_login_redirect_needed() ) {
		return;
	}
	$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
	$login_url = add_query_arg( 'redirect_to', $checkout_url, $account_url );
	wp_safe_redirect( $login_url );
	exit;
}
add_action( 'template_redirect', 'jluxe_checkout_redirect_to_otp_login', 5 );

/**
 * آیا فرم پیش‌فرض ورود ووکامرس باید از تسویه‌حساب حذف شود؟
 *
 * وقتی ریدایرکت به ورود پیامکی انجام می‌شود یا ثبت‌نام الزامی نیست (خرید
 * مهمان بدون بخش ورود)، فرم پیش‌فرض حذف می‌شود. فقط وقتی پیامک فعال نیست
 * و لاگین اجباری است، فرم پیش‌فرض به‌عنوان fallback می‌ماند.
 */
function jluxe_checkout_hide_default_login_form(): bool {
	if ( is_user_logged_in() ) {
		return false;
	}
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->checkout ) {
		return false;
	}
	if ( jluxe_checkout_login_redirect_needed() ) {
		return true;
	}
	if ( ! WC()->checkout->is_registration_required() ) {
		return true;
	}
	return false;
}

/**
 * حذف فرم پیش‌فرض ورود ووکامرس از تسویه‌حساب (در صورت نیاز).
 */
function jluxe_checkout_remove_default_login_form(): void {
	if ( jluxe_checkout_hide_default_login_form() ) {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
	}
}
add_action( 'wp', 'jluxe_checkout_remove_default_login_form', 20 );

/**
 * دریافت آدرس‌های ذخیره‌شدهٔ مشتری (صورتحساب + ارسال) از ووکامرس.
 *
 * @return array<int, array{type: string, label: string, address: array}>
 */
function jluxe_get_customer_addresses(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}
	$user_id = get_current_user_id();
	$out = array();

	// خواندن مستقیم از متای کاربر (مطمئن‌تر از کش WC_Customer بعد از ورود OTP).
	$meta = function ( $key ) use ( $user_id ) {
		return (string) get_user_meta( $user_id, $key, true );
	};

	// آدرس‌های ذخیره‌شده در لیست اختصاصی (چند آدرس برای یک شماره).
	$saved_addresses = get_user_meta( $user_id, 'jluxe_saved_addresses', true );
	if ( is_array( $saved_addresses ) ) {
		$needs_id_save = false;
		$seen_keys = array();
		$cleaned = array();
		foreach ( $saved_addresses as $idx => $saved ) {
			if ( ! is_array( $saved ) || ! jluxe_address_has_content( $saved ) ) {
				continue;
			}
			// آدرس‌های قدیمی بدون شناسه، شناسه می‌گیرند تا قابل ویرایش/حذف باشند.
			if ( empty( $saved['id'] ) ) {
				$saved['id'] = 'a' . substr( md5( uniqid( '', true ) . $idx ), 0, 12 );
				$needs_id_save = true;
			}
			// dedupe: آدرس تکراری (آدرس دقیق + کدپستی + شهر + استان) فقط یک‌بار نمایش داده می‌شود.
			$key_parts = array();
			foreach ( array( 'address_1', 'postcode', 'city', 'state' ) as $k ) {
				$key_parts[] = strtolower( trim( preg_replace( '/\s+/u', ' ', (string) ( $saved[ $k ] ?? '' ) ) ) );
			}
			$dedupe_key = implode( '|', $key_parts );
			if ( isset( $seen_keys[ $dedupe_key ] ) ) {
				$needs_id_save = true; // تکراری هست؛ ذخیره‌سازی پاک می‌شود.
				continue;
			}
			$seen_keys[ $dedupe_key ] = true;
			$cleaned[] = $saved;
			$out[] = array(
				'type'    => 'billing',
				'label'   => 'آدرس ذخیره‌شده',
				'address' => $saved,
			);
		}
		if ( $needs_id_save ) {
			update_user_meta( $user_id, 'jluxe_saved_addresses', $cleaned );
		}
	}

	$billing = array(
		'first_name' => $meta( 'billing_first_name' ),
		'last_name'  => $meta( 'billing_last_name' ),
		'company'    => $meta( 'billing_company' ),
		'address_1'  => $meta( 'billing_address_1' ),
		'address_2'  => $meta( 'billing_address_2' ),
		'city'       => $meta( 'billing_city' ),
		'state'      => $meta( 'billing_state' ),
		'postcode'   => $meta( 'billing_postcode' ),
		'country'    => $meta( 'billing_country' ) ?: 'IR',
		'phone'      => $meta( 'billing_phone' ),
		'landline'   => $meta( 'billing_landline' ),
	);
	// آدرس صورتحساب فقط اگر در لیست اختصاصی نباشد اضافه می‌شود.
	$billing_is_duplicate = false;
	foreach ( $out as $existing ) {
		if ( jluxe_addresses_equal( $existing['address'], $billing ) ) {
			$billing_is_duplicate = true;
			break;
		}
	}
	if ( jluxe_address_has_content( $billing ) && ! $billing_is_duplicate ) {
		$billing['id'] = 'billing';
		$out[] = array(
			'type'    => 'billing',
			'label'   => 'آدرس صورتحساب',
			'address' => $billing,
		);
	}

	$shipping = array(
		'first_name' => $meta( 'shipping_first_name' ),
		'last_name'  => $meta( 'shipping_last_name' ),
		'company'    => $meta( 'shipping_company' ),
		'address_1'  => $meta( 'shipping_address_1' ),
		'address_2'  => $meta( 'shipping_address_2' ),
		'city'       => $meta( 'shipping_city' ),
		'state'      => $meta( 'shipping_state' ),
		'postcode'   => $meta( 'shipping_postcode' ),
		'country'    => $meta( 'shipping_country' ) ?: 'IR',
	);
	$shipping_is_duplicate = jluxe_addresses_equal( $billing, $shipping );
	if ( ! $shipping_is_duplicate ) {
		foreach ( $out as $existing ) {
			if ( jluxe_addresses_equal( $existing['address'], $shipping ) ) {
				$shipping_is_duplicate = true;
				break;
			}
		}
	}
	if ( jluxe_address_has_content( $shipping ) && ! $shipping_is_duplicate ) {
		$shipping['id'] = 'shipping';
		$out[] = array(
			'type'    => 'shipping',
			'label'   => 'آدرس ارسال',
			'address' => $shipping,
		);
	}

	// آدرس‌های ذخیره‌شده در مسیر DSN موبایل (dsn_addresses) هم در لیست می‌آیند.
	$dsn_addresses = get_user_meta( $user_id, 'dsn_addresses', true );
	if ( is_array( $dsn_addresses ) ) {
		foreach ( $dsn_addresses as $dsn ) {
			if ( ! is_array( $dsn ) ) {
				continue;
			}
			$converted = array(
				'first_name' => (string) ( $dsn['first_name'] ?? '' ),
				'last_name'  => (string) ( $dsn['last_name'] ?? '' ),
				'company'    => '',
				'address_1'  => (string) ( $dsn['address'] ?? '' ),
				'address_2'  => '',
				'city'       => (string) ( $dsn['city'] ?? '' ),
				'state'      => (string) ( $dsn['province'] ?? '' ),
				'postcode'   => (string) ( $dsn['postcode'] ?? '' ),
				'country'    => 'IR',
				'phone'      => (string) ( $dsn['phone'] ?? '' ),
				'landline'   => '',
			);
			if ( ! jluxe_address_has_content( $converted ) ) {
				continue;
			}
			$duplicate = false;
			foreach ( $out as $existing ) {
				if ( jluxe_addresses_equal( $existing['address'], $converted ) ) {
					$duplicate = true;
					break;
				}
			}
			if ( ! $duplicate ) {
				// شناسهٔ پایدار بر اساس محتوای آدرس (برای ویرایش/حذف).
				$converted['id'] = 'dsn-' . substr( md5( json_encode( $converted ) ), 0, 12 );
				$out[] = array(
					'type'    => 'billing',
					'label'   => 'آدرس ذخیره‌شده',
					'address' => $converted,
				);
			}
		}
	}

	return $out;
}

/**
 * آیا آدرس محتوای معناداری دارد؟
 */
function jluxe_address_has_content( array $address ): bool {
	foreach ( array( 'address_1', 'city', 'postcode' ) as $key ) {
		if ( ! empty( trim( (string) ( $address[ $key ] ?? '' ) ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * آیا دو آدرس یکسان‌اند؟ (برای جلوگیری از نمایش تکراری)
 */
function jluxe_addresses_equal( array $a, array $b ): bool {
	foreach ( array( 'address_1', 'city', 'state', 'postcode', 'country' ) as $key ) {
		if ( (string) ( $a[ $key ] ?? '' ) !== (string) ( $b[ $key ] ?? '' ) ) {
			return false;
		}
	}
	return true;
}

/**
 * متن یک‌خطی آدرس برای نمایش در کارت.
 */
/**
 * نام استان از روی کد (روش استاندارد ووکامرس: WC_Countries).
 * برای کدهای ISO کار می‌کند؛ برای ID های عددی، None برمی‌گرداند.
 */
function jluxe_get_state_name_from_code( string $code ): string {
	$code = trim( $code );
	if ( '' === $code || ! function_exists( 'WC_Countries' ) ) {
		return '';
	}
	$countries = new WC_Countries();
	$states = $countries->get_states( 'IR' );
	if ( is_array( $states ) && isset( $states[ $code ] ) ) {
		return (string) $states[ $code ];
	}
	return '';
}

function jluxe_format_address_line( array $address ): string {
	$parts = array();
	// استان/شهر با اسم نمایش داده می‌شوند (مقدارها ممکن است ID عددی یا کد ISO
	// باشند)؛ اولویت با اسم ذخیره‌شده، بعد نگاشت استاندارد ووکامرس.
	// ID عددیِ حل‌نشده (آدرس قدیمی) نمایش داده نمی‌شود — «ایلام» بهتر از
	// «ایلام، 1973» است؛ اسم شهر پس از انتخاب/ویرایش آدرس حل می‌شود.
	$is_numeric_id = static function ( $value ): bool {
		return 1 === preg_match( '/^\d+$/', trim( (string) $value ) );
	};
	$state_display = trim( (string) ( $address['state_name'] ?? '' ) );
	if ( '' === $state_display ) {
		$state_display = jluxe_get_state_name_from_code( (string) ( $address['state'] ?? '' ) );
	}
	if ( '' === $state_display && ! $is_numeric_id( $address['state'] ?? '' ) ) {
		$state_display = trim( (string) ( $address['state'] ?? '' ) );
	}
	if ( '' !== $state_display ) {
		$parts[] = $state_display;
	}
	$city_display = trim( (string) ( $address['city_name'] ?? '' ) );
	if ( '' === $city_display && ! $is_numeric_id( $address['city'] ?? '' ) ) {
		$city_display = trim( (string) ( $address['city'] ?? '' ) );
	}
	if ( '' !== $city_display ) {
		$parts[] = $city_display;
	}
	foreach ( array( 'address_1', 'address_2' ) as $key ) {
		$value = trim( (string) ( $address[ $key ] ?? '' ) );
		if ( '' !== $value ) {
			$parts[] = $value;
		}
	}
	$line = implode( '، ', $parts );
	$postcode = trim( (string) ( $address['postcode'] ?? '' ) );
	if ( '' !== $postcode ) {
		$line .= ( '' !== $line ? ' — کدپستی ' : 'کدپستی ' ) . $postcode;
	}
	return $line;
}

/**
 * ذخیرهٔ آدرس جدید در متای حساب مشتری (صورتحساب + ارسال).
 *
 * @param array $data فیلدهای آدرس: first_name, last_name, state, city,
 *                    address_1, postcode, landline, address_id (اختیاری)
 * @return string شناسهٔ آدرس ذخیره‌شده ('billing'/'shipping' برای متای
 *                ووکامرس، شناسهٔ واقعی برای لیست اختصاصی) یا '' در صورت خطا
 */
function jluxe_save_customer_address( array $data ): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$user_id = get_current_user_id();

	// ووکامرس فقط یک آدرس صورتحساب و یک آدرس ارسال نگه می‌دارد؛ برای اینکه
	// کاربر بتواند چند آدرس ذخیره کند، لیست آدرس‌ها در متای اختصاصی هم نگه داشته می‌شود.
	$saved = get_user_meta( $user_id, 'jluxe_saved_addresses', true );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$new_address = array(
		'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
		'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
		'company'    => '',
		'address_1'  => sanitize_text_field( $data['address_1'] ?? '' ),
		'address_2'  => '',
		'city'       => sanitize_text_field( $data['city'] ?? '' ),
		'state'      => sanitize_text_field( $data['state'] ?? '' ),
		'state_name' => sanitize_text_field( $data['state_name'] ?? '' ),
		'city_name'  => sanitize_text_field( $data['city_name'] ?? '' ),
		'postcode'   => sanitize_text_field( $data['postcode'] ?? '' ),
		'country'    => 'IR',
		'phone'      => ! empty( $data['phone'] ) ? preg_replace( '/\D/', '', sanitize_text_field( $data['phone'] ) ) : '',
		'landline'   => ! empty( $data['landline'] ) ? preg_replace( '/\D/', '', sanitize_text_field( $data['landline'] ) ) : '',
	);
	// شناسهٔ پایدار برای ویرایش/حذف.
	$address_id = isset( $data['address_id'] ) ? sanitize_text_field( $data['address_id'] ) : '';
	// ویرایش آدرس صورتحساب/ارسال: فقط متای مربوطه به‌روز می‌شود (بخش پایین‌تر)،
	// لیست اختصاصی دست نمی‌خورد.
	$is_meta_address = in_array( $address_id, array( 'billing', 'shipping' ), true );
	$updated = $is_meta_address;
	if ( '' !== $address_id && ! $is_meta_address ) {
		foreach ( $saved as $idx => $existing ) {
			if ( is_array( $existing ) && ( $existing['id'] ?? '' ) === $address_id ) {
				$new_address['id'] = $address_id;
				$saved[ $idx ] = $new_address;
				$updated = true;
				break;
			}
		}
	}
	if ( ! $updated ) {
		// dedupe با کلید نرمال‌شده (آدرس دقیق + کدپستی + شهر + استان) تا آدرس
		// تکراری اضافه نشود؛ اگر تکراری بود، همان آدرس قبلی به‌روز می‌شود.
		$norm = function ( $addr ) {
			$parts = array();
			foreach ( array( 'address_1', 'postcode', 'city', 'state' ) as $k ) {
				$parts[] = strtolower( trim( preg_replace( '/\s+/u', ' ', (string) ( $addr[ $k ] ?? '' ) ) ) );
			}
			return implode( '|', $parts );
		};
		$new_key = $norm( $new_address );
		$exists = false;
		foreach ( $saved as $idx => $existing ) {
			if ( is_array( $existing ) && $norm( $existing ) === $new_key ) {
				// آدرس تکراری: همان رکورد قبلی به‌روز می‌شود (شناسه حفظ می‌شود).
				$new_address['id'] = $existing['id'] ?? ( 'a' . substr( md5( uniqid( '', true ) ), 0, 12 ) );
				$saved[ $idx ] = $new_address;
				$exists = true;
				break;
			}
		}
		if ( ! $exists ) {
			$new_address['id'] = 'a' . substr( md5( uniqid( '', true ) ), 0, 12 );
			$saved[] = $new_address;
		}
	}
	// ویرایش آدرس صورتحساب/ارسال فقط متای ووکامرس را تغییر می‌دهد؛ لیست
	// اختصاصی دست نمی‌خورد و write اضافی روی دیتابیس انجام نمی‌شود.
	if ( ! $is_meta_address ) {
		update_user_meta( $user_id, 'jluxe_saved_addresses', $saved );
	}

	$fields = array(
		'billing_first_name'  => sanitize_text_field( $data['first_name'] ?? '' ),
		'billing_last_name'   => sanitize_text_field( $data['last_name'] ?? '' ),
		'billing_state'       => sanitize_text_field( $data['state'] ?? '' ),
		'billing_city'        => sanitize_text_field( $data['city'] ?? '' ),
		'billing_address_1'   => sanitize_text_field( $data['address_1'] ?? '' ),
		'billing_postcode'    => sanitize_text_field( $data['postcode'] ?? '' ),
		'billing_country'     => 'IR',
		'shipping_first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
		'shipping_last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
		'shipping_state'      => sanitize_text_field( $data['state'] ?? '' ),
		'shipping_city'       => sanitize_text_field( $data['city'] ?? '' ),
		'shipping_address_1'  => sanitize_text_field( $data['address_1'] ?? '' ),
		'shipping_postcode'   => sanitize_text_field( $data['postcode'] ?? '' ),
		'shipping_country'    => 'IR',
	);
	if ( ! empty( $data['landline'] ) ) {
		$fields['billing_landline'] = preg_replace( '/\D/', '', sanitize_text_field( $data['landline'] ) );
	}
	if ( ! empty( $data['phone'] ) ) {
		$fields['billing_phone'] = preg_replace( '/\D/', '', sanitize_text_field( $data['phone'] ) );
	}
	foreach ( $fields as $key => $value ) {
		update_user_meta( $user_id, $key, $value );
	}
	// به‌روزرسانی کش مشتری ووکامرس تا فرم تسویه‌حساب مقادیر جدید را بگیرد.
	if ( function_exists( 'WC_Customer' ) ) {
		$customer = new WC_Customer( $user_id, true );
		$customer->save();
	}
	// شناسهٔ آدرس ذخیره‌شده برگردانده می‌شود تا کلاینت دقیقاً همان کارت را
	// انتخاب کند (نه آخرین آیتم لیست که ممکن است آدرس دیگری باشد).
	return (string) ( $new_address['id'] ?? $address_id );
}

/**
 * AJAX: ذخیرهٔ آدرس جدید در تسویه‌حساب و برگرداندن لیست به‌روز آدرس‌ها.
 */
function jluxe_ajax_save_checkout_address(): void {
	// بررسی دستی nonce تا به‌جای پاسخ متنی «-1»، خطای JSON واضح برگردد.
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jluxe_checkout_address' ) ) {
		wp_send_json_error( array( 'message' => 'نشست شما منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.' ) );
	}
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'ابتدا وارد شوید.' ) );
	}
	$data = array(
		'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
		'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
		'phone'      => isset( $_POST['phone'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '',
		'state'      => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
		'city'       => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
		'state_name' => isset( $_POST['state_name'] ) ? sanitize_text_field( wp_unslash( $_POST['state_name'] ) ) : '',
		'city_name'  => isset( $_POST['city_name'] ) ? sanitize_text_field( wp_unslash( $_POST['city_name'] ) ) : '',
		'address_1'  => isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '',
		'postcode'   => isset( $_POST['postcode'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : '',
		'landline'   => isset( $_POST['landline'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['landline'] ) ) ) : '',
		// شناسهٔ آدرس در حال ویرایش (خالی = آدرس جدید). بدون این، ویرایش
		// همیشه به‌عنوان آدرس جدید اضافه می‌شد و تکراری می‌ساخت.
		'address_id' => isset( $_POST['address_id'] ) ? sanitize_text_field( wp_unslash( $_POST['address_id'] ) ) : '',
	);
	foreach ( array( 'first_name', 'last_name', 'state', 'city', 'address_1' ) as $required ) {
		if ( '' === trim( (string) $data[ $required ] ) ) {
			wp_send_json_error( array( 'message' => 'لطفاً همهٔ فیلدهای آدرس را تکمیل کنید.' ) );
		}
	}
	// کدپستی فقط برای ارسال با روش پست الزامی است؛ در ذخیرهٔ آدرس اختیاری می‌ماند
	// و فرم تسویه‌حساب ووکامرس آن را بر اساس روش ارسال انتخاب‌شده اجباری می‌کند.
	if ( '' !== (string) $data['postcode'] && ! preg_match( '/^\d{10}$/', (string) $data['postcode'] ) ) {
		wp_send_json_error( array( 'message' => 'کدپستی باید ۱۰ رقم باشد.' ) );
	}
	if ( '' !== (string) $data['landline'] && ! preg_match( '/^\d{10,11}$/', (string) $data['landline'] ) ) {
		wp_send_json_error( array( 'message' => 'تلفن معتبر نیست.' ) );
	}
	$saved_id = jluxe_save_customer_address( $data );
	if ( '' === $saved_id ) {
		wp_send_json_error( array( 'message' => 'ذخیرهٔ آدرس ناموفق بود.' ) );
	}
	$addresses = jluxe_get_customer_addresses();
	$formatted = array();
	foreach ( $addresses as $entry ) {
		$formatted[] = array(
			'type'    => $entry['type'],
			'label'   => $entry['label'],
			'line'    => jluxe_format_address_line( $entry['address'] ),
			'phone'   => $entry['address']['phone'] ?? '',
			'address' => $entry['address'],
		);
	}
	wp_send_json_success( array( 'addresses' => $formatted, 'saved_id' => $saved_id ) );
}
add_action( 'wp_ajax_jluxe_save_checkout_address', 'jluxe_ajax_save_checkout_address' );

/**
 * AJAX: حذف یک آدرس ذخیره‌شده و برگرداندن لیست به‌روز آدرس‌ها.
 */
function jluxe_ajax_delete_checkout_address(): void {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jluxe_checkout_address' ) ) {
		wp_send_json_error( array( 'message' => 'نشست شما منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.' ) );
	}
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'ابتدا وارد شوید.' ) );
	}
	$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( wp_unslash( $_POST['address_id'] ) ) : '';
	$user_id = get_current_user_id();

	if ( 'billing' === $address_id ) {
		// حذف آدرس صورتحساب: پاک شدن فیلدهای صورتحساب از متای کاربر.
		foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_phone', 'billing_landline' ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
	} elseif ( 'shipping' === $address_id ) {
		// حذف آدرس ارسال: پاک شدن فیلدهای ارسال از متای کاربر.
		foreach ( array( 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country' ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
	} elseif ( 0 === strpos( $address_id, 'dsn-' ) ) {
		// حذف آدرس DSN: حذف از متای dsn_addresses بر اساس شناسهٔ محتوایی.
		$dsn_addresses = get_user_meta( $user_id, 'dsn_addresses', true );
		if ( is_array( $dsn_addresses ) ) {
			$remaining = array();
			foreach ( $dsn_addresses as $dsn ) {
				if ( ! is_array( $dsn ) ) {
					$remaining[] = $dsn;
					continue;
				}
				$converted = array(
					'first_name' => (string) ( $dsn['first_name'] ?? '' ),
					'last_name'  => (string) ( $dsn['last_name'] ?? '' ),
					'company'    => '',
					'address_1'  => (string) ( $dsn['address'] ?? '' ),
					'address_2'  => '',
					'city'       => (string) ( $dsn['city'] ?? '' ),
					'state'      => (string) ( $dsn['province'] ?? '' ),
					'postcode'   => (string) ( $dsn['postcode'] ?? '' ),
					'country'    => 'IR',
					'phone'      => (string) ( $dsn['phone'] ?? '' ),
					'landline'   => '',
				);
				$dsn_id = 'dsn-' . substr( md5( json_encode( $converted ) ), 0, 12 );
				if ( $dsn_id === $address_id ) {
					continue;
				}
				$remaining[] = $dsn;
			}
			update_user_meta( $user_id, 'dsn_addresses', $remaining );
		}
	} else {
		// حذف آدرس ذخیره‌شده از لیست اختصاصی.
		$saved = get_user_meta( $user_id, 'jluxe_saved_addresses', true );
		if ( is_array( $saved ) && '' !== $address_id ) {
			$remaining = array();
			foreach ( $saved as $existing ) {
				if ( is_array( $existing ) && ( $existing['id'] ?? '' ) === $address_id ) {
					continue;
				}
				$remaining[] = $existing;
			}
			update_user_meta( $user_id, 'jluxe_saved_addresses', $remaining );
		}
	}
	$addresses = jluxe_get_customer_addresses();
	$formatted = array();
	foreach ( $addresses as $entry ) {
		$formatted[] = array(
			'type'    => $entry['type'],
			'label'   => $entry['label'],
			'line'    => jluxe_format_address_line( $entry['address'] ),
			'phone'   => $entry['address']['phone'] ?? '',
			'address' => $entry['address'],
		);
	}
	wp_send_json_success( array( 'addresses' => $formatted ) );
}
add_action( 'wp_ajax_jluxe_delete_checkout_address', 'jluxe_ajax_delete_checkout_address' );

/**
 * نام استان‌های ایران (کد → نام) برای لیست انتخاب استان.
 *
 * @return array<string, string>
 */
function jluxe_checkout_provinces(): array {
	if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
		$states = WC()->countries->get_states( 'IR' );
		if ( is_array( $states ) && ! empty( $states ) ) {
			return $states;
		}
	}
	// Fallback: نام‌های فارسی استاندارد.
	return array(
		'THR' => 'تهران', 'ABZ' => 'البرز', 'EAZ' => 'آذربایجان شرقی',
		'WAZ' => 'آذربایجان غربی', 'ADL' => 'اردبیل', 'ESF' => 'اصفهان',
		'ILM' => 'ایلام', 'BHR' => 'بوشهر', 'CHB' => 'چهارمحال و بختیاری',
		'KHZ' => 'خوزستان', 'FRS' => 'فارس', 'GZN' => 'قزوین',
		'GIL' => 'گیلان', 'GLS' => 'گلستان', 'LRS' => 'لرستان',
		'MKZ' => 'مرکزی', 'MZN' => 'مازندران', 'HRZ' => 'هرمزگان',
		'HDN' => 'همدان', 'YZD' => 'یزد', 'KRH' => 'کرمانشاه',
		'KRN' => 'کرمان', 'KRD' => 'کردستان', 'KBD' => 'کهگیلویه و بویراحمد',
		'SKH' => 'خراسان جنوبی', 'RKH' => 'خراسان رضوی', 'NKH' => 'خراسان شمالی',
		'SMN' => 'سمنان', 'SBN' => 'سیستان و بلوچستان', 'ZJN' => 'زنجان',
		'QHM' => 'قم',
	);
}

/**
 * بارگذاری استایل و اسکریپت مدیریت آدرس در تسویه‌حساب اصلی.
 */
function jluxe_enqueue_checkout_login_assets(): void {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return;
	}
	$style_path = JLUXE_THEME_DIR . '/assets/css/checkout-login.css';
	if ( is_file( $style_path ) ) {
		wp_enqueue_style(
			'jluxe-checkout-login',
			JLUXE_THEME_URI . '/assets/css/checkout-login.css',
			array(),
			(string) filemtime( $style_path )
		);
	}
	$path = JLUXE_THEME_DIR . '/assets/js/checkout-login.js';
	if ( is_file( $path ) ) {
		wp_enqueue_script(
			'jluxe-checkout-login',
			JLUXE_THEME_URI . '/assets/js/checkout-login.js',
			array(),
			(string) filemtime( $path ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
	// فقط ajaxUrl و nonce لازم است؛ استان/شهر همان فیلدهای واقعی ووکامرس‌اند
	// و دادهٔ آن‌ها (jluxe_iran_cities) از مسیر دیگری (JLuxeThemeSettings)
	// به JS تم می‌رسد. localize کردن دوبارهٔ آن‌ها فقط payload مرده بود.
	wp_localize_script( 'jluxe-checkout-login', 'JLUXE_CHECKOUT_ADDRESS', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'jluxe_checkout_address' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_checkout_login_assets', 23 );

/** Render the postcode opt-out shortcut directly after a WooCommerce postcode field. */
function jluxe_render_unknown_postcode_checkbox( string $target_id ): string {
	$target_id = sanitize_html_class( $target_id );
	if ( '' === $target_id ) {
		return '';
	}

	return '<label class="jluxe-unknown-postcode" for="jluxe-unknown-postcode-' . esc_attr( $target_id ) . '"><input id="jluxe-unknown-postcode-' . esc_attr( $target_id ) . '" type="checkbox" class="jluxe-unknown-postcode-checkbox" data-jluxe-postcode-target="' . esc_attr( $target_id ) . '"><span>کد پستی خود را نمی‌دانم</span></label>';
}

/** Append the opt-out directly after either the billing or shipping postcode field. */
function jluxe_add_unknown_postcode_checkbox( string $field_html, string $key ): string {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return $field_html;
	}
	if ( ! in_array( $key, array( 'billing_postcode', 'shipping_postcode' ), true ) ) {
		return $field_html;
	}

	return $field_html . jluxe_render_unknown_postcode_checkbox( $key );
}
add_filter( 'woocommerce_form_field', 'jluxe_add_unknown_postcode_checkbox', 20, 2 );

/**
 * رندر بخش آدرس در تسویه‌حساب اصلی: نام/نام خانوادگی + تلفن (از OTP) +
 * لیست آدرس‌های ذخیره‌شده + آکاردئون آدرس جدید.
 *
 * فقط برای مشتری لاگین‌کرده نمایش داده می‌شود.
 */
function jluxe_render_address_selector(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$addresses = jluxe_get_customer_addresses();
	$user = wp_get_current_user();
	$phone = get_user_meta( get_current_user_id(), 'jluxe_phone', true );
	if ( '' === $phone ) {
		$phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
	}
	$first_name = get_user_meta( get_current_user_id(), 'billing_first_name', true );
	$last_name  = get_user_meta( get_current_user_id(), 'billing_last_name', true );
	if ( '' === $first_name && $user ) {
		$first_name = $user->first_name;
	}
	if ( '' === $last_name && $user ) {
		$last_name = $user->last_name;
	}
	?>
	<section class="jluxe-checkout-addresses" data-jluxe-address-selector aria-labelledby="jluxe-checkout-addresses-title" data-jluxe-address-nonce="<?php echo esc_attr( wp_create_nonce( 'jluxe_checkout_address' ) ); ?>">
		<header class="jluxe-checkout-addresses__heading">
			<h2 class="jluxe-checkout-addresses__title" id="jluxe-checkout-addresses-title">آدرس تحویل</h2>
		</header>
		<div class="jluxe-checkout-addresses__identity">
			<div class="jluxe-checkout-addresses__field">
				<label for="jluxe-identity-first-name">نام</label>
				<input type="text" id="jluxe-identity-first-name" value="<?php echo esc_attr( $first_name ); ?>" autocomplete="given-name" data-jluxe-identity-field="first_name" />
			</div>
			<div class="jluxe-checkout-addresses__field">
				<label for="jluxe-identity-last-name">نام خانوادگی</label>
				<input type="text" id="jluxe-identity-last-name" value="<?php echo esc_attr( $last_name ); ?>" autocomplete="family-name" data-jluxe-identity-field="last_name" />
			</div>
			<div class="jluxe-checkout-addresses__field">
				<label for="jluxe-identity-phone">تلفن تحویل‌گیرنده</label>
				<input type="tel" id="jluxe-identity-phone" value="<?php echo esc_attr( $phone ); ?>" dir="ltr" autocomplete="tel" data-jluxe-identity-field="phone" />
			</div>
		</div>

		<?php // لیست همیشه رندر می‌شود (حتی خالی) تا بعد از ذخیرهٔ آدرس جدید،
			// آدرس بلافاصله به لیست اضافه و قابل انتخاب شود. ?>
			<div class="jluxe-checkout-addresses__list" role="radiogroup" aria-label="انتخاب آدرس تحویل" data-jluxe-address-list>
				<?php foreach ( $addresses as $index => $entry ) : ?>
					<label class="jluxe-checkout-addresses__card<?php echo 0 === $index ? ' is-selected' : ''; ?>" data-jluxe-address-card>
						<input type="radio" name="jluxe_saved_address" value="<?php echo esc_attr( $entry['type'] ); ?>"<?php checked( 0 === $index ); ?> data-jluxe-address-option />
						<span class="jluxe-checkout-addresses__radio" aria-hidden="true"></span>
						<span class="jluxe-checkout-addresses__card-body">
							<span class="jluxe-checkout-addresses__card-label"><?php echo esc_html( $entry['label'] ); ?></span>
							<span class="jluxe-checkout-addresses__card-line"><?php echo esc_html( jluxe_format_address_line( $entry['address'] ) ); ?></span>
							<?php if ( ! empty( $entry['address']['phone'] ) ) : ?>
								<span class="jluxe-checkout-addresses__card-phone" dir="ltr"><?php echo esc_html( $entry['address']['phone'] ); ?></span>
							<?php endif; ?>
						</span>
						<span class="jluxe-checkout-addresses__actions">
							<button type="button" class="jluxe-checkout-addresses__action" data-jluxe-address-edit aria-label="ویرایش آدرس" title="ویرایش آدرس">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
							</button>
							<button type="button" class="jluxe-checkout-addresses__action jluxe-checkout-addresses__action--danger" data-jluxe-address-delete aria-label="حذف آدرس" title="حذف آدرس">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
							</button>
						</span>
						<span class="jluxe-checkout-addresses__data" data-jluxe-address-data hidden><?php echo esc_attr( wp_json_encode( $entry['address'] ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

		<button type="button" class="jluxe-checkout-addresses__add jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-jluxe-address-toggle aria-expanded="false">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			<span>وارد کردن آدرس جدید</span>
		</button>

		<div class="jluxe-checkout-addresses__form" data-jluxe-address-form>
			<div class="jluxe-checkout-addresses__form-header">
				<h3 class="jluxe-checkout-addresses__form-title">افزودن آدرس جدید</h3>
				<button type="button" class="jluxe-checkout-addresses__close" data-jluxe-address-close aria-label="بستن">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</div>
			<div class="jluxe-checkout-addresses__fields">
				<?php
				// استان/شهر همان فیلدهای واقعی ووکامرس (billing_state/billing_city)
				// هستند تا مکانیزم لود شهر (تم/پلاگین) دقیقاً روی همان‌ها کار کند.
				$jluxe_checkout = function_exists( 'WC' ) && WC() ? WC()->checkout : null;
				$jluxe_billing_fields = $jluxe_checkout ? $jluxe_checkout->get_checkout_fields( 'billing' ) : array();
				if ( isset( $jluxe_billing_fields['billing_state'] ) ) :
					woocommerce_form_field( 'billing_state', $jluxe_billing_fields['billing_state'], $jluxe_checkout->get_value( 'billing_state' ) );
				endif;
				if ( isset( $jluxe_billing_fields['billing_city'] ) ) :
					woocommerce_form_field( 'billing_city', $jluxe_billing_fields['billing_city'], $jluxe_checkout->get_value( 'billing_city' ) );
				endif;
				?>
				<div class="jluxe-checkout-addresses__field jluxe-checkout-addresses__field--wide">
					<label for="jluxe-new-address-1">آدرس دقیق</label>
					<textarea id="jluxe-new-address-1" rows="2" data-jluxe-new-field="address_1" autocomplete="street-address"></textarea>
				</div>
				<div class="jluxe-checkout-addresses__field">
					<label for="jluxe-new-address-postcode">کدپستی <span class="jluxe-checkout-addresses__optional">(برای ارسال پستی الزامی)</span></label>
					<input type="text" id="jluxe-new-address-postcode" inputmode="numeric" maxlength="10" dir="ltr" data-jluxe-new-field="postcode" autocomplete="postal-code" />
					<?php echo jluxe_render_unknown_postcode_checkbox( 'jluxe-new-address-postcode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="jluxe-checkout-addresses__field">
					<label for="jluxe-new-address-landline">تلفن</label>
					<input type="tel" id="jluxe-new-address-landline" dir="ltr" data-jluxe-new-field="landline" autocomplete="tel" />
				</div>
			</div>
			<p class="jluxe-checkout-addresses__status" data-jluxe-address-status role="status" aria-live="polite" hidden></p>
			<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" data-jluxe-address-save>ذخیره آدرس</button>
		</div>
	</section>
	<?php
}
