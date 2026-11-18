<?php
/**
 * پرداخت سریع موبایلی — مسیر مستقل کنار تسویه‌حساب اصلی.
 *
 * این ماژول جایگزین روش موبایلی قبلی است و هیچ تغییری در تسویه‌حساب
 * اصلی (classic/guided)، درگاه‌ها، nonce یا submit استاندارد ووکامرس
 * ایجاد نمی‌کند. جریان آن مستقل است: انتخاب/ثبت آدرس با نقشه، انتخاب
 * روش ارسال واقعی ووکامرس، خلاصه سفارش، انتخاب از بین درگاه‌های فعال
 * ووکامرس و ساخت سفارش pending + ریدایرکت به همان درگاه.
 *
 * OTP در این مسیر قابل تنظیم است (cart_checkout.mobile_checkout_otp_enabled).
 * اگر فعال باشد ولی پیامک تنظیم نشده باشد، مسیر fail-closed است و
 * سفارش ساخته نمی‌شود؛ هیچ کد فیک تأیید نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_MOBILE_CHECKOUT_META = 'jluxe_mobile_addresses';
const JLUXE_MOBILE_CHECKOUT_SELECTED = 'jluxe_mobile_selected_address';
const JLUXE_MOBILE_CHECKOUT_VIA = 'jluxe-mobile-checkout';
const JLUXE_MOBILE_CHECKOUT_LAST_ORDER = 'jluxe_mobile_last_order';
const JLUXE_MOBILE_CHECKOUT_OTP_SESSION = 'jluxe_mobile_checkout_otp';
const JLUXE_MOBILE_CHECKOUT_MAP_KEY_OPTION = 'jluxe_mobile_checkout_map_key';
const JLUXE_MOBILE_CHECKOUT_SERVICE_KEY_OPTION = 'jluxe_mobile_checkout_service_key';

function jluxe_mobile_checkout_enabled(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}
	return (bool) jluxe_get_setting( 'cart_checkout.mobile_checkout_enabled', false );
}

function jluxe_mobile_checkout_otp_enabled(): bool {
	return (bool) jluxe_get_setting( 'cart_checkout.mobile_checkout_otp_enabled', false );
}

function jluxe_mobile_checkout_map_enabled(): bool {
	return (bool) jluxe_get_setting( 'cart_checkout.mobile_checkout_map_enabled', true );
}

function jluxe_mobile_checkout_url(): string {
	return home_url( '/mobile-checkout/' );
}

function jluxe_mobile_checkout_get_map_key(): string {
	return trim( (string) get_option( JLUXE_MOBILE_CHECKOUT_MAP_KEY_OPTION, '' ) );
}

function jluxe_mobile_checkout_set_map_key( string $key ): void {
	update_option( JLUXE_MOBILE_CHECKOUT_MAP_KEY_OPTION, trim( $key ), false );
}

function jluxe_mobile_checkout_get_service_key(): string {
	return trim( (string) get_option( JLUXE_MOBILE_CHECKOUT_SERVICE_KEY_OPTION, '' ) );
}

function jluxe_mobile_checkout_set_service_key( string $key ): void {
	update_option( JLUXE_MOBILE_CHECKOUT_SERVICE_KEY_OPTION, trim( $key ), false );
}

/** استان‌های ایران با کد ووکامرس و نام فارسی برای نمایش در فرم موبایلی. */
function jluxe_mobile_checkout_provinces(): array {
	return array(
		'THR' => 'تهران',
		'ABZ' => 'البرز',
		'EAZ' => 'آذربایجان شرقی',
		'WAZ' => 'آذربایجان غربی',
		'ADL' => 'اردبیل',
		'ESF' => 'اصفهان',
		'ILM' => 'ایلام',
		'BHR' => 'بوشهر',
		'CHB' => 'چهارمحال و بختیاری',
		'KHZ' => 'خوزستان',
		'FRS' => 'فارس',
		'GZN' => 'قزوین',
		'GIL' => 'گیلان',
		'GLS' => 'گلستان',
		'LRS' => 'لرستان',
		'MKZ' => 'مرکزی',
		'MZN' => 'مازندران',
		'HRZ' => 'هرمزگان',
		'HDN' => 'همدان',
		'YZD' => 'یزد',
		'KRH' => 'کرمانشاه',
		'KRN' => 'کرمان',
		'KRD' => 'کردستان',
		'KBD' => 'کهگیلویه و بویراحمد',
		'SKH' => 'خراسان جنوبی',
		'RKH' => 'خراسان رضوی',
		'NKH' => 'خراسان شمالی',
		'SMN' => 'سمنان',
		'SBN' => 'سیستان و بلوچستان',
		'ZJN' => 'زنجان',
		'QHM' => 'قم',
	);
}

function jluxe_mobile_checkout_norm( string $value ): string {
	$value = str_replace( array( 'ي', 'ك', 'ـ' ), array( 'ی', 'ک', '' ), $value );
	$value = preg_replace( '/\s+/u', ' ', $value );
	return trim( is_string( $value ) ? $value : '' );
}

function jluxe_mobile_checkout_state_code_from_name( string $name ): string {
	$normalized = jluxe_mobile_checkout_norm( preg_replace( '/^استان\s+/u', '', $name ) );
	if ( '' === $normalized ) {
		return '';
	}
	foreach ( jluxe_mobile_checkout_provinces() as $code => $province_name ) {
		if ( jluxe_mobile_checkout_norm( $province_name ) === $normalized ) {
			return $code;
		}
	}
	// Nominatim معمولاً نام استان را انگلیسی برمی‌گرداند.
	$english = array(
		'tehran' => 'THR', 'alborz' => 'ABZ', 'east azerbaijan' => 'EAZ', 'west azerbaijan' => 'WAZ',
		'ardabil' => 'ADL', 'isfahan' => 'ESF', 'ilam' => 'ILM', 'bushehr' => 'BHR',
		'chaharmahal and bakhtiari' => 'CHB', 'khuzestan' => 'KHZ', 'fars' => 'FRS', 'qazvin' => 'GZN',
		'gilan' => 'GIL', 'golestan' => 'GLS', 'lorestan' => 'LRS', 'markazi' => 'MKZ',
		'mazandaran' => 'MZN', 'hormozgan' => 'HRZ', 'hamadan' => 'HDN', 'yazd' => 'YZD',
		'kermanshah' => 'KRH', 'kerman' => 'KRN', 'kurdistan' => 'KRD', 'kohgiluyeh and boyer-ahmad' => 'KBD',
		'south khorasan' => 'SKH', 'razavi khorasan' => 'RKH', 'north khorasan' => 'NKH', 'semnan' => 'SMN',
		'sistan and baluchestan' => 'SBN', 'zanjan' => 'ZJN', 'qom' => 'QHM',
	);
	$key = strtolower( trim( $normalized ) );
	return $english[ $key ] ?? '';
}

function jluxe_mobile_checkout_in_bbox( float $lat, float $lng ): bool {
	return $lat >= 24.0 && $lat <= 40.5 && $lng >= 43.5 && $lng <= 64.0;
}

/* ---------- storage ---------- */

function jluxe_mobile_checkout_guard( bool $check_nonce = true ): void {
	if ( ! function_exists( 'WC' ) || ! WC() ) {
		wp_send_json_error( array( 'message' => 'ووکامرس فعال نیست.' ) );
	}
	if ( $check_nonce ) {
		check_ajax_referer( 'jluxe_mobile_checkout', 'nonce' );
	}
	if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
		wc_load_cart();
	}
	if ( ! is_user_logged_in() && WC()->session ) {
		WC()->session->set_customer_session_cookie( true );
	}
}

function jluxe_mobile_checkout_addresses(): array {
	if ( is_user_logged_in() ) {
		$addresses = get_user_meta( get_current_user_id(), JLUXE_MOBILE_CHECKOUT_META, true );
	} elseif ( WC()->session ) {
		$addresses = WC()->session->get( JLUXE_MOBILE_CHECKOUT_META );
	} else {
		$addresses = array();
	}
	return is_array( $addresses ) ? array_values( $addresses ) : array();
}

function jluxe_mobile_checkout_put_addresses( array $list ): void {
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), JLUXE_MOBILE_CHECKOUT_META, $list );
	} elseif ( WC()->session ) {
		WC()->session->set( JLUXE_MOBILE_CHECKOUT_META, $list );
	}
}

function jluxe_mobile_checkout_put_selected( string $id ): void {
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), JLUXE_MOBILE_CHECKOUT_SELECTED, $id );
	} elseif ( WC()->session ) {
		WC()->session->set( JLUXE_MOBILE_CHECKOUT_SELECTED, $id );
	}
}

function jluxe_mobile_checkout_state(): array {
	$list = jluxe_mobile_checkout_addresses();
	if ( is_user_logged_in() ) {
		$selected = (string) get_user_meta( get_current_user_id(), JLUXE_MOBILE_CHECKOUT_SELECTED, true );
	} elseif ( WC()->session ) {
		$selected = (string) WC()->session->get( JLUXE_MOBILE_CHECKOUT_SELECTED );
	} else {
		$selected = '';
	}
	$ids = array();
	foreach ( $list as $address ) {
		if ( ! empty( $address['id'] ) ) {
			$ids[] = (string) $address['id'];
		}
	}
	if ( ! in_array( $selected, $ids, true ) ) {
		$selected = '';
	}
	return array( 'addresses' => $list, 'selected' => $selected );
}

function jluxe_mobile_checkout_selected(): ?array {
	$state = jluxe_mobile_checkout_state();
	foreach ( $state['addresses'] as $address ) {
		if ( (string) ( $address['id'] ?? '' ) === (string) $state['selected'] ) {
			return $address;
		}
	}
	return null;
}

function jluxe_mobile_checkout_ajax_state(): void {
	jluxe_mobile_checkout_guard( false );
	$state = jluxe_mobile_checkout_state();
	$state['nonce'] = wp_create_nonce( 'jluxe_mobile_checkout' );
	$state['otpEnabled'] = jluxe_mobile_checkout_otp_enabled();
	$state['otpAvailable'] = function_exists( 'jluxe_otp_available' ) ? jluxe_otp_available() : false;
	$state['mapEnabled'] = jluxe_mobile_checkout_map_enabled();
	wp_send_json_success( $state );
}

/* ---------- OTP (جدا از ورود با پیامک؛ فقط مالکیت موبایل برای همین مسیر) ---------- */

function jluxe_mobile_checkout_otp_key( string $phone10 ): string {
	return 'jluxe_mobile_otp_' . hash_hmac( 'sha256', $phone10, wp_salt( 'auth' ) );
}

function jluxe_mobile_checkout_otp_hash( string $phone10, string $code, int $user_id ): string {
	return hash_hmac( 'sha256', $phone10 . '|mobile_checkout|' . $user_id . '|' . $code, wp_salt( 'auth' ) );
}

function jluxe_mobile_checkout_otp_verified_phone(): string {
	if ( ! WC()->session ) {
		return '';
	}
	$record = WC()->session->get( JLUXE_MOBILE_CHECKOUT_OTP_SESSION );
	if ( ! is_array( $record ) || (int) ( $record['expires'] ?? 0 ) <= time() ) {
		return '';
	}
	return (string) ( $record['phone'] ?? '' );
}

function jluxe_mobile_checkout_ajax_otp_request(): void {
	jluxe_mobile_checkout_guard();
	if ( ! jluxe_mobile_checkout_otp_enabled() ) {
		wp_send_json_error( array( 'message' => 'تأیید موبایل در این مسیر فعال نیست.' ) );
	}
	if ( ! function_exists( 'jluxe_otp_available' ) || ! jluxe_otp_available() ) {
		wp_send_json_error( array( 'message' => 'تأیید موبایل فعال است ولی پیامک تنظیم نشده است؛ با پشتیبانی تماس بگیرید.' ) );
	}
	$phone_raw = isset( $_POST['phone'] ) && is_scalar( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '';
	$phone10 = jluxe_normalize_phone( $phone_raw );
	if ( '' === $phone10 ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر نیست.' ) );
	}
	if ( ! jluxe_security_rate_limit( 'mobile_otp_send_ip', jluxe_theme_get_client_ip(), 10, 10 * MINUTE_IN_SECONDS ) ||
		! jluxe_security_rate_limit( 'mobile_otp_send_phone', $phone10, 3, 10 * MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
	}
	$lock = jluxe_security_lock( 'mobile_otp:' . $phone10 );
	if ( ! $lock ) {
		wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
	}
	try {
		$code = (string) wp_rand( 100000, 999999 );
		$key = jluxe_mobile_checkout_otp_key( $phone10 );
		$record = array(
			'hash' => jluxe_mobile_checkout_otp_hash( $phone10, $code, get_current_user_id() ),
			'expires' => time() + 2 * MINUTE_IN_SECONDS,
			'attempts' => 0,
			'user_id' => get_current_user_id(),
		);
		if ( ! update_option( $key, $record, false ) ) {
			wp_send_json_error( array( 'message' => 'امکان ایجاد کد وجود ندارد؛ دوباره تلاش کنید.' ) );
		}
		$sent = jluxe_send_otp_sms( jluxe_get_theme_settings()['sms'], jluxe_get_sms_api_key(), $phone10, $code );
		if ( is_wp_error( $sent ) ) {
			jluxe_consume_option( $key, $record );
			wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
		}
		wp_send_json_success( array( 'sent' => true, 'expiresIn' => 120 ) );
	} finally {
		jluxe_security_unlock( $lock );
	}
}

function jluxe_mobile_checkout_ajax_otp_verify(): void {
	jluxe_mobile_checkout_guard();
	if ( ! jluxe_mobile_checkout_otp_enabled() ) {
		wp_send_json_error( array( 'message' => 'تأیید موبایل در این مسیر فعال نیست.' ) );
	}
	$phone_raw = isset( $_POST['phone'] ) && is_scalar( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '';
	$code_raw = isset( $_POST['code'] ) && is_scalar( $_POST['code'] ) ? (string) wp_unslash( $_POST['code'] ) : '';
	$phone10 = jluxe_normalize_phone( $phone_raw );
	$code = trim( jluxe_ascii_digits( $code_raw ) );
	if ( '' === $phone10 ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر نیست.' ) );
	}
	if ( ! jluxe_security_rate_limit( 'mobile_otp_verify_phone', $phone10, 10, 10 * MINUTE_IN_SECONDS ) ) {
		wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
	}
	$lock = jluxe_security_lock( 'mobile_otp:' . $phone10 );
	if ( ! $lock ) {
		wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
	}
	try {
		$key = jluxe_mobile_checkout_otp_key( $phone10 );
		$record = get_option( $key );
		$bad = array( 'message' => 'کد وارد شده اشتباه یا منقضی شده است؛ کد جدید بگیرید.' );
		if ( ! is_array( $record ) || (int) $record['expires'] <= time() ) {
			wp_send_json_error( $bad );
		}
		if ( (int) $record['attempts'] >= 5 ) {
			jluxe_consume_option( $key, $record );
			wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
		}
		if ( ! preg_match( '/^[0-9]{6}$/D', $code ) || (int) $record['user_id'] !== get_current_user_id() ||
			! hash_equals( (string) $record['hash'], jluxe_mobile_checkout_otp_hash( $phone10, $code, get_current_user_id() ) ) ) {
			++$record['attempts'];
			if ( $record['attempts'] >= 5 ) {
				$original = $record;
				--$original['attempts'];
				jluxe_consume_option( $key, $original );
				wp_send_json_error( array( 'message' => 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.' ) );
			}
			update_option( $key, $record, false );
			wp_send_json_error( $bad );
		}
		if ( ! jluxe_consume_option( $key, $record ) ) {
			wp_send_json_error( $bad );
		}
		if ( WC()->session ) {
			WC()->session->set( JLUXE_MOBILE_CHECKOUT_OTP_SESSION, array( 'phone' => $phone10, 'expires' => time() + 30 * MINUTE_IN_SECONDS ) );
		}
		wp_send_json_success( array( 'verified' => true ) );
	} finally {
		jluxe_security_unlock( $lock );
	}
}

/* ---------- CRUD ---------- */

function jluxe_mobile_checkout_ajax_save(): void {
	jluxe_mobile_checkout_guard();
	$raw = isset( $_POST['data'] ) && is_scalar( $_POST['data'] ) ? json_decode( wp_unslash( (string) $_POST['data'] ), true ) : null;
	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'message' => 'داده نامعتبر است.' ) );
	}
	$text = static function ( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
	};
	$phone10 = jluxe_normalize_phone( $text( 'phone' ) );
	$address = array(
		'id' => $text( 'id' ),
		'first_name' => $text( 'first_name' ),
		'last_name' => $text( 'last_name' ),
		'phone' => '' !== $phone10 ? '0' . $phone10 : '',
		'email' => isset( $raw['email'] ) && is_scalar( $raw['email'] ) ? sanitize_email( (string) $raw['email'] ) : '',
		'lat' => $text( 'lat' ),
		'lng' => $text( 'lng' ),
		'map_address' => $text( 'map_address' ),
		'state' => strtoupper( $text( 'state' ) ),
		'city' => $text( 'city' ),
		'address' => isset( $raw['address'] ) && is_scalar( $raw['address'] ) ? sanitize_textarea_field( (string) $raw['address'] ) : '',
		'plaque' => $text( 'plaque' ),
		'postcode' => preg_replace( '/\D/', '', jluxe_ascii_digits( $text( 'postcode' ) ) ),
	);
	foreach ( array( 'first_name', 'last_name', 'state', 'city', 'address', 'plaque', 'postcode' ) as $required ) {
		if ( '' === $address[ $required ] ) {
			wp_send_json_error( array( 'message' => 'لطفاً همه فیلدها را تکمیل کنید.' ) );
		}
	}
	if ( '' === $address['phone'] ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر نیست (مثال: 09123456789).' ) );
	}
	if ( ! is_user_logged_in() && '' === $address['email'] ) {
		wp_send_json_error( array( 'message' => 'آدرس ایمیل برای خرید مهمان الزامی است.' ) );
	}
	if ( '' !== $address['email'] && ! is_email( $address['email'] ) ) {
		wp_send_json_error( array( 'message' => 'آدرس ایمیل معتبر نیست.' ) );
	}
	if ( ! preg_match( '/^\d{10}$/D', $address['postcode'] ) ) {
		wp_send_json_error( array( 'message' => 'کدپستی باید ۱۰ رقم باشد.' ) );
	}
	if ( '' !== $address['lat'] && ! jluxe_mobile_checkout_in_bbox( (float) $address['lat'], (float) $address['lng'] ) ) {
		wp_send_json_error( array( 'message' => 'موقعیت انتخاب‌شده خارج از ایران است.' ) );
	}
	if ( ! array_key_exists( $address['state'], jluxe_mobile_checkout_provinces() ) ) {
		wp_send_json_error( array( 'message' => 'استان نامعتبر است.' ) );
	}
	// شهر شناخته‌شده‌ای که به استان دیگری تعلق دارد رد می‌شود؛ نام ناشناخته مسدود نمی‌شود.
	if ( function_exists( 'jluxe_validate_checkout_iran_city_province' ) ) {
		$errors = new WP_Error();
		jluxe_validate_checkout_iran_city_province(
			array( 'billing_country' => 'IR', 'billing_state' => $address['state'], 'billing_city' => $address['city'] ),
			$errors
		);
		if ( $errors->has_errors() ) {
			wp_send_json_error( array( 'message' => 'شهر انتخاب‌شده با استان انتخابی هم‌خوانی ندارد.' ) );
		}
	}

	$list = jluxe_mobile_checkout_addresses();
	$found = false;
	foreach ( $list as $index => $old ) {
		if ( '' !== $address['id'] && (string) ( $old['id'] ?? '' ) === $address['id'] ) {
			$list[ $index ] = $address;
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		$address['id'] = wp_generate_uuid4();
		$list[] = $address;
	}
	jluxe_mobile_checkout_put_addresses( $list );
	jluxe_mobile_checkout_put_selected( $address['id'] );
	// فقط تغییر شماره تأیید قبلی را باطل می‌کند؛ همان شماره پس از ذخیره معتبر می‌ماند.
	if ( WC()->session && jluxe_mobile_checkout_otp_verified_phone() !== $phone10 ) {
		WC()->session->set( JLUXE_MOBILE_CHECKOUT_OTP_SESSION, null );
	}
	wp_send_json_success( jluxe_mobile_checkout_state() );
}

function jluxe_mobile_checkout_ajax_delete(): void {
	jluxe_mobile_checkout_guard();
	$id = isset( $_POST['id'] ) && is_scalar( $_POST['id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['id'] ) ) : '';
	$list = array_values( array_filter( jluxe_mobile_checkout_addresses(), static function ( $item ) use ( $id ) {
		return (string) ( $item['id'] ?? '' ) !== $id;
	} ) );
	jluxe_mobile_checkout_put_addresses( $list );
	$state = jluxe_mobile_checkout_state();
	if ( $state['selected'] === $id ) {
		jluxe_mobile_checkout_put_selected( '' );
	}
	wp_send_json_success( jluxe_mobile_checkout_state() );
}

function jluxe_mobile_checkout_ajax_select(): void {
	jluxe_mobile_checkout_guard();
	$id = isset( $_POST['id'] ) && is_scalar( $_POST['id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['id'] ) ) : '';
	jluxe_mobile_checkout_put_selected( $id );
	wp_send_json_success( jluxe_mobile_checkout_state() );
}

/* ---------- geocode ---------- */

function jluxe_mobile_checkout_nominatim( float $lat, float $lng ): array {
	$response = wp_remote_get(
		add_query_arg(
			array( 'format' => 'jsonv2', 'accept-language' => 'fa', 'zoom' => 18, 'addressdetails' => 1, 'lat' => $lat, 'lon' => $lng ),
			'https://nominatim.openstreetmap.org/reverse'
		),
		array( 'timeout' => 8, 'user-agent' => 'JLuxe-Mobile-Checkout/1.0 (' . home_url() . ')' )
	);
	return is_wp_error( $response ) ? array() : (array) json_decode( wp_remote_retrieve_body( $response ), true );
}

function jluxe_mobile_checkout_ajax_geocode(): void {
	jluxe_mobile_checkout_guard();
	$lat = isset( $_POST['lat'] ) && is_scalar( $_POST['lat'] ) ? (float) $_POST['lat'] : 0.0;
	$lng = isset( $_POST['lng'] ) && is_scalar( $_POST['lng'] ) ? (float) $_POST['lng'] : 0.0;
	$outside = 'موقعیت انتخاب‌شده خارج از ایران است. لطفاً نقطه‌ای داخل ایران انتخاب کنید.';
	if ( ! $lat || ! $lng ) {
		wp_send_json_error( array( 'message' => 'مختصات نامعتبر است.' ) );
	}
	if ( ! jluxe_mobile_checkout_in_bbox( $lat, $lng ) ) {
		wp_send_json_error( array( 'code' => 'outside', 'message' => $outside ) );
	}
	$cache_key = 'jluxe_mobile_geo_' . md5( round( $lat, 5 ) . ',' . round( $lng, 5 ) );
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		wp_send_json_success( $cached );
	}
	$fields = array( 'state' => '', 'city' => '', 'street' => '', 'alley' => '', 'plaque' => '', 'postcode' => '' );
	$service_key = jluxe_mobile_checkout_get_service_key();
	$neshan = array();
	if ( '' !== $service_key ) {
		$response = wp_remote_get(
			add_query_arg( array( 'lat' => $lat, 'lng' => $lng ), 'https://api.neshan.org/v5/reverse' ),
			array( 'timeout' => 8, 'headers' => array( 'Api-Key' => $service_key ) )
		);
		$neshan = is_wp_error( $response ) ? array() : (array) json_decode( wp_remote_retrieve_body( $response ), true );
	}
	if ( ! empty( $neshan['formatted_address'] ) || ! empty( $neshan['state'] ) ) {
		$fields['state'] = jluxe_mobile_checkout_state_code_from_name( (string) ( $neshan['state'] ?? '' ) );
		$fields['city'] = jluxe_mobile_checkout_norm( (string) ( $neshan['city'] ?? ( $neshan['county'] ?? '' ) ) );
		$fields['street'] = jluxe_mobile_checkout_norm( (string) ( $neshan['route_name'] ?? '' ) );
		foreach ( preg_split( '/[،,]/u', (string) ( $neshan['formatted_address'] ?? '' ) ) as $segment ) {
			$segment = jluxe_mobile_checkout_norm( (string) $segment );
			if ( '' === $segment ) {
				continue;
			}
			if ( ! $fields['street'] && preg_match( '/^(خیابان|بلوار|بزرگراه|اتوبان|جاده|میدان)/u', $segment ) ) {
				$fields['street'] = $segment;
			} elseif ( ! $fields['alley'] && preg_match( '/^(کوچه|بن\s?بست|پاساژ)/u', $segment ) ) {
				$fields['alley'] = $segment;
			} elseif ( ! $fields['plaque'] && preg_match( '/^پلاک\s*(.+)$/u', $segment, $matches ) ) {
				$fields['plaque'] = $matches[1];
			} elseif ( ! $fields['postcode'] && preg_match( '/(\d{10})/', jluxe_ascii_digits( $segment ), $matches ) ) {
				$fields['postcode'] = $matches[1];
			}
		}
	}
	if ( '' === $fields['state'] ) {
		$nominatim = jluxe_mobile_checkout_nominatim( $lat, $lng );
		$addr = (array) ( $nominatim['address'] ?? array() );
		$country_code = strtolower( (string) ( $addr['country_code'] ?? '' ) );
		if ( '' !== $country_code && 'ir' !== $country_code ) {
			wp_send_json_error( array( 'code' => 'outside', 'message' => $outside ) );
		}
		if ( $addr ) {
			$fields['state'] = jluxe_mobile_checkout_state_code_from_name( (string) ( $addr['state'] ?? '' ) );
			if ( '' === $fields['city'] ) {
				$fields['city'] = jluxe_mobile_checkout_norm( (string) ( $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? '' ) );
			}
			if ( '' === $fields['street'] ) {
				$fields['street'] = jluxe_mobile_checkout_norm( (string) ( $addr['road'] ?? '' ) );
			}
			if ( '' === $fields['plaque'] ) {
				$fields['plaque'] = jluxe_mobile_checkout_norm( (string) ( $addr['house_number'] ?? '' ) );
			}
			if ( '' === $fields['postcode'] ) {
				$fields['postcode'] = preg_replace( '/\D/', '', jluxe_ascii_digits( (string) ( $addr['postcode'] ?? '' ) ) );
			}
		}
	}
	$province_name = '' !== $fields['state'] ? ( jluxe_mobile_checkout_provinces()[ $fields['state'] ] ?? '' ) : '';
	$fields['text'] = implode( '، ', array_filter( array(
		$province_name,
		$fields['city'],
		$fields['street'],
		$fields['alley'],
		'' !== $fields['plaque'] ? 'پلاک ' . $fields['plaque'] : '',
		'' !== $fields['postcode'] ? 'کدپستی ' . $fields['postcode'] : '',
	) ) );
	if ( '' !== $fields['text'] ) {
		set_transient( $cache_key, $fields, DAY_IN_SECONDS );
	}
	wp_send_json_success( $fields );
}

/* ---------- shipping / summary / gateways ---------- */

function jluxe_mobile_checkout_set_customer_location( array $address ): void {
	if ( ! WC()->customer ) {
		return;
	}
	if ( method_exists( WC()->customer, 'set_location' ) ) {
		WC()->customer->set_location( 'IR', $address['state'], $address['postcode'] );
	}
	if ( method_exists( WC()->customer, 'set_shipping_location' ) ) {
		WC()->customer->set_shipping_location( 'IR', $address['state'], $address['postcode'] );
	}
	if ( method_exists( WC()->customer, 'set_billing_location' ) ) {
		WC()->customer->set_billing_location( 'IR', $address['state'], $address['postcode'] );
	}
}

function jluxe_mobile_checkout_packages( array $address ): array {
	jluxe_mobile_checkout_set_customer_location( $address );
	if ( ! WC()->shipping ) {
		return array();
	}
	return (array) WC()->shipping()->get_packages();
}

function jluxe_mobile_checkout_ajax_shipping(): void {
	jluxe_mobile_checkout_guard();
	$address = jluxe_mobile_checkout_selected();
	if ( ! $address ) {
		wp_send_json_error( array( 'message' => 'آدرسی انتخاب نشده است.' ) );
	}
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		wp_send_json_error( array( 'message' => 'سبد خرید خالی است.' ) );
	}
	$packages = jluxe_mobile_checkout_packages( $address );
	$out = array();
	foreach ( $packages as $index => $package ) {
		$rates = array();
		foreach ( (array) ( $package['rates'] ?? array() ) as $rate_id => $rate ) {
			$rates[] = array(
				'id' => (string) $rate_id,
				'label' => method_exists( $rate, 'get_label' ) ? wp_strip_all_tags( (string) $rate->get_label() ) : (string) $rate_id,
				'cost' => method_exists( $rate, 'get_cost' ) ? wc_price( (float) $rate->get_cost() ) : '',
			);
		}
		$out[] = array( 'index' => (int) $index, 'rates' => $rates );
	}
	if ( empty( $out ) ) {
		wp_send_json_error( array( 'message' => 'روش ارسالی برای این نشانی در دسترس نیست.' ) );
	}
	wp_send_json_success( $out );
}

function jluxe_mobile_checkout_apply_shipping( array $shipping ): void {
	if ( WC()->session ) {
		WC()->session->set( 'chosen_shipping_methods', array_values( $shipping ) );
	}
}

function jluxe_mobile_checkout_ajax_summary(): void {
	jluxe_mobile_checkout_guard();
	$address = jluxe_mobile_checkout_selected();
	if ( ! $address ) {
		wp_send_json_error( array( 'message' => 'آدرسی انتخاب نشده است.' ) );
	}
	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() ) {
		wp_send_json_error( array( 'message' => 'سبد خرید خالی است.' ) );
	}
	$shipping = isset( $_POST['shipping'] ) && is_scalar( $_POST['shipping'] ) ? json_decode( wp_unslash( (string) $_POST['shipping'] ), true ) : array();
	if ( ! is_array( $shipping ) ) {
		$shipping = array();
	}
	jluxe_mobile_checkout_apply_shipping( $shipping );
	$cart->calculate_totals();
	$items = array();
	foreach ( $cart->get_cart() as $item ) {
		$items[] = array(
			'name' => wp_strip_all_tags( $item['data']->get_name() ),
			'qty' => (int) $item['quantity'],
			'total' => wc_price( $item['line_total'] + $item['line_tax'] ),
		);
	}
	$rows = array( array( 'جمع جزء', $cart->get_cart_subtotal() ) );
	if ( $cart->get_discount_total() ) {
		$rows[] = array( 'تخفیف', '-' . wc_price( $cart->get_discount_total() ) );
	}
	if ( $cart->needs_shipping() ) {
		$rows[] = array( 'حمل و نقل', $cart->get_cart_shipping_total() );
	}
	$rows[] = array( 'مبلغ قابل پرداخت', $cart->get_total() );
	wp_send_json_success( array( 'items' => $items, 'rows' => $rows, 'address' => $address ) );
}

function jluxe_mobile_checkout_ajax_gateways(): void {
	jluxe_mobile_checkout_guard();
	$out = array();
	foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gateway ) {
		$out[] = array(
			'id' => (string) $id,
			'title' => wp_strip_all_tags( $gateway->get_title() ),
			'desc' => wp_strip_all_tags( $gateway->get_description() ),
			'icon' => $gateway->get_icon(),
		);
	}
	if ( empty( $out ) ) {
		wp_send_json_error( array( 'message' => 'درگاه پرداختی فعال نیست.' ) );
	}
	wp_send_json_success( $out );
}

/* ---------- pay ---------- */

function jluxe_mobile_checkout_ajax_pay(): void {
	jluxe_mobile_checkout_guard();
	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$gateway_id = isset( $_POST['gateway'] ) && is_scalar( $_POST['gateway'] ) ? sanitize_key( (string) wp_unslash( $_POST['gateway'] ) ) : '';
	$address = jluxe_mobile_checkout_selected();
	if ( ! isset( $gateways[ $gateway_id ] ) ) {
		wp_send_json_error( array( 'message' => 'درگاه نامعتبر است.' ) );
	}
	if ( ! $address ) {
		wp_send_json_error( array( 'message' => 'آدرسی انتخاب نشده است.' ) );
	}
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		wp_send_json_error( array( 'message' => 'سبد خرید خالی است.' ) );
	}
	if ( jluxe_mobile_checkout_otp_enabled() ) {
		$phone10 = jluxe_normalize_phone( (string) ( $address['phone'] ?? '' ) );
		if ( '' === $phone10 || jluxe_mobile_checkout_otp_verified_phone() !== $phone10 ) {
			wp_send_json_error( array( 'message' => 'تأیید موبایل لازم است؛ ابتدا کد تأیید را وارد کنید.' ) );
		}
	}
	$shipping = isset( $_POST['shipping'] ) && is_scalar( $_POST['shipping'] ) ? json_decode( wp_unslash( (string) $_POST['shipping'] ), true ) : array();
	if ( ! is_array( $shipping ) ) {
		$shipping = array();
	}

	try {
		$cart = WC()->cart;
		$cart->calculate_totals();
		$user_id = get_current_user_id();
		$user = $user_id ? get_userdata( $user_id ) : null;
		$phone = (string) ( $address['phone'] ?? '' );
		$email = ! empty( $address['email'] ) ? $address['email'] : ( $user ? $user->user_email : '' );

		$previous = WC()->session ? absint( WC()->session->get( JLUXE_MOBILE_CHECKOUT_LAST_ORDER ) ) : 0;
		if ( $previous && ( $previous_order = wc_get_order( $previous ) ) && $previous_order->get_created_via() === JLUXE_MOBILE_CHECKOUT_VIA && $previous_order->has_status( 'pending' ) ) {
			$previous_order->update_status( 'cancelled', 'جایگزین سفارش جدید شد.' );
		}

		$order = wc_create_order( array( 'customer_id' => $user_id, 'status' => 'pending', 'created_via' => JLUXE_MOBILE_CHECKOUT_VIA ) );
		foreach ( $cart->get_cart() as $item ) {
			$order->add_product( $item['data'], $item['quantity'], array(
				'variation_id' => (int) ( $item['variation_id'] ?? 0 ),
				'variation' => (array) ( $item['variation'] ?? array() ),
				'totals' => array(
					'subtotal' => $item['line_subtotal'],
					'subtotal_tax' => $item['line_subtotal_tax'],
					'total' => $item['line_total'],
					'tax' => $item['line_tax'],
					'tax_data' => $item['line_tax_data'],
				),
			) );
		}
		jluxe_mobile_checkout_set_customer_location( $address );
		jluxe_mobile_checkout_apply_shipping( $shipping );
		foreach ( WC()->shipping()->get_packages() as $index => $package ) {
			$chosen = $shipping[ $index ] ?? '';
			if ( '' !== $chosen && isset( $package['rates'][ $chosen ] ) ) {
				$shipping_item = new WC_Order_Item_Shipping();
				$shipping_item->set_shipping_rate( $package['rates'][ $chosen ] );
				$order->add_item( $shipping_item );
			}
		}
		foreach ( $cart->get_applied_coupons() as $code ) {
			$order->apply_coupon( $code );
		}

		$province_name = jluxe_mobile_checkout_provinces()[ $address['state'] ] ?? $address['state'];
		$base = array(
			'first_name' => $address['first_name'],
			'last_name' => $address['last_name'],
			'address_1' => trim( $province_name . '، ' . $address['city'] . '، ' . $address['address'] . '، پلاک ' . $address['plaque'] ),
			'city' => $address['city'],
			'state' => $address['state'],
			'postcode' => $address['postcode'],
			'country' => 'IR',
		);
		$order->set_address( $base + array( 'email' => $email, 'phone' => $phone ), 'billing' );
		$order->set_address( $base, 'shipping' );
		$order->update_meta_data( '_jluxe_mobile_lat', $address['lat'] );
		$order->update_meta_data( '_jluxe_mobile_lng', $address['lng'] );
		$order->update_meta_data( '_jluxe_mobile_plaque', $address['plaque'] );
		$order->update_meta_data( '_jluxe_mobile_map_address', $address['map_address'] );
		$order->set_payment_method( $gateways[ $gateway_id ] );
		$order->calculate_totals();
		$order->save();
		if ( WC()->session ) {
			WC()->session->set( JLUXE_MOBILE_CHECKOUT_LAST_ORDER, $order->get_id() );
		}

		$result = $gateways[ $gateway_id ]->process_payment( $order->get_id() );
		if ( is_array( $result ) && 'success' === ( $result['result'] ?? '' ) ) {
			$created = wc_get_order( $order->get_id() );
			if ( $created && ! $created->has_status( array( 'pending', 'failed', 'cancelled' ) ) ) {
				$cart->empty_cart();
			}
			wp_send_json_success( array( 'redirect' => $result['redirect'] ?? '' ) );
		}
		wp_send_json_error( array( 'message' => 'اتصال به درگاه ناموفق بود.' ) );
	} catch ( Exception $exception ) {
		wp_send_json_error( array( 'message' => $exception->getMessage() ) );
	}
}

function jluxe_mobile_checkout_maybe_empty_cart( $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( $order && $order->get_created_via() === JLUXE_MOBILE_CHECKOUT_VIA && ( $order->is_paid() || $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) && WC()->cart ) {
		WC()->cart->empty_cart();
	}
}
add_action( 'woocommerce_thankyou', 'jluxe_mobile_checkout_maybe_empty_cart', 10, 1 );

/* ---------- assets / shortcode / route ---------- */

function jluxe_mobile_checkout_register_assets(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$map_key = jluxe_mobile_checkout_get_map_key();
	if ( '' !== $map_key ) {
		wp_register_style( 'jluxe-leaflet', 'https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.css', array(), null );
		wp_register_script( 'jluxe-leaflet', 'https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.js', array(), null, true );
	} else {
		wp_register_style( 'jluxe-leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4' );
		wp_register_script( 'jluxe-leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array(), '1.9.4', true );
	}
	$css_path = JLUXE_THEME_DIR . '/assets/css/mobile-checkout.css';
	$js_path = JLUXE_THEME_DIR . '/assets/js/mobile-checkout.js';
	wp_register_style( 'jluxe-mobile-checkout', JLUXE_THEME_URI . '/assets/css/mobile-checkout.css', array( 'jluxe-leaflet' ), file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0' );
	wp_register_script( 'jluxe-mobile-checkout', JLUXE_THEME_URI . '/assets/js/mobile-checkout.js', array( 'jluxe-leaflet' ), file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0', true );
	wp_localize_script( 'jluxe-mobile-checkout', 'JLuxeMobileCheckout', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'provinces' => jluxe_mobile_checkout_provinces(),
		'cities' => function_exists( 'jluxe_iran_cities' ) ? jluxe_iran_cities() : array(),
		'otpEnabled' => jluxe_mobile_checkout_otp_enabled(),
		'mapEnabled' => jluxe_mobile_checkout_map_enabled(),
		'mapKey' => $map_key,
		'tiles' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		'bbox' => array( 24.0, 43.5, 40.5, 64.0 ),
	) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_mobile_checkout_register_assets', 5 );

function jluxe_mobile_checkout_config(): array {
	return array(
		'title' => jluxe_cart_checkout_required_copy( 'mobile_checkout_title', 'پرداخت سریع موبایلی' ),
		'map' => array(
			'lat' => (float) jluxe_get_setting( 'cart_checkout.mobile_checkout_map_lat', 35.6892 ),
			'lng' => (float) jluxe_get_setting( 'cart_checkout.mobile_checkout_map_lng', 51.3890 ),
			'zoom' => (int) jluxe_get_setting( 'cart_checkout.mobile_checkout_map_zoom', 12 ),
			'type' => (string) jluxe_get_setting( 'cart_checkout.mobile_checkout_map_type', 'standard-day' ),
		),
	);
}

function jluxe_mobile_checkout_root_html(): string {
	$config = jluxe_mobile_checkout_config();
	return '<div class="jluxe-mobile-checkout" dir="rtl" data-jluxe-mobile-checkout data-config="' . esc_attr( wp_json_encode( $config ) ) . '">'
		. '<div class="jluxe-mobile-checkout__view"><div class="jluxe-mobile-checkout__card"><div class="jluxe-mobile-checkout__loader" role="status" aria-live="polite"><span class="jluxe-mobile-checkout__spin" aria-hidden="true"></span><div>لطفاً منتظر بمانید</div></div></div></div>'
		. '<div class="jluxe-mobile-checkout__layer"></div></div>';
}

function jluxe_mobile_checkout_shortcode(): string {
	if ( ! jluxe_mobile_checkout_enabled() ) {
		return '';
	}
	wp_enqueue_style( 'jluxe-mobile-checkout' );
	wp_enqueue_script( 'jluxe-mobile-checkout' );
	return jluxe_mobile_checkout_root_html();
}
add_shortcode( 'jluxe_mobile_checkout', 'jluxe_mobile_checkout_shortcode' );

function jluxe_mobile_checkout_register_query_vars( array $vars ): array {
	if ( ! in_array( 'jluxe_mobile_checkout', $vars, true ) ) {
		$vars[] = 'jluxe_mobile_checkout';
	}
	return $vars;
}
add_filter( 'query_vars', 'jluxe_mobile_checkout_register_query_vars' );

function jluxe_mobile_checkout_register_rewrite(): void {
	add_rewrite_rule( '^mobile-checkout/?$', 'index.php?jluxe_mobile_checkout=1', 'top' );
}
add_action( 'init', 'jluxe_mobile_checkout_register_rewrite', 1 );

function jluxe_mobile_checkout_flush_rewrite_once(): void {
	if ( get_option( 'jluxe_mobile_checkout_rewrite_version' ) === '1.0.0' ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'jluxe_mobile_checkout_rewrite_version', '1.0.0', false );
}
add_action( 'init', 'jluxe_mobile_checkout_flush_rewrite_once', 99 );

function jluxe_mobile_checkout_template_include( string $template ): string {
	if ( ! get_query_var( 'jluxe_mobile_checkout' ) ) {
		return $template;
	}
	$file = JLUXE_THEME_DIR . '/page-mobile-checkout.php';
	return file_exists( $file ) ? $file : $template;
}
add_filter( 'template_include', 'jluxe_mobile_checkout_template_include', 20 );

/* ---------- cart entry (کنار دکمه اصلی؛ بدون تغییر دکمه اصلی) ---------- */

function jluxe_mobile_checkout_cart_button(): void {
	if ( ! jluxe_mobile_checkout_enabled() || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	$label = jluxe_cart_checkout_required_copy( 'mobile_checkout_button_label', 'پرداخت سریع موبایلی' );
	echo '<a href="' . esc_url( jluxe_mobile_checkout_url() ) . '" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary jluxe-mobile-checkout-cart-button">' . esc_html( $label ) . '</a>';
}
add_action( 'woocommerce_proceed_to_checkout', 'jluxe_mobile_checkout_cart_button', 20 );

/* ---------- order admin / privacy ---------- */

function jluxe_mobile_checkout_admin_order_location( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return;
	}
	$lat = $order->get_meta( '_jluxe_mobile_lat', true );
	$lng = $order->get_meta( '_jluxe_mobile_lng', true );
	if ( '' === (string) $lat || '' === (string) $lng ) {
		return;
	}
	$url = add_query_arg( array( 'mlat' => $lat, 'mlon' => $lng, 'zoom' => 16 ), 'https://www.openstreetmap.org/' );
	echo '<p class="jluxe-order-map-location"><strong>' . esc_html__( 'موقعیت پرداخت سریع موبایلی:', 'jluxe' ) . '</strong> <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $lat . ', ' . $lng ) . '</a></p>';
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_mobile_checkout_admin_order_location', 10, 1 );

function jluxe_mobile_checkout_export_personal_data( array $personal_data, $order ): array {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return $personal_data;
	}
	foreach ( array( '_jluxe_mobile_lat' => 'Mobile checkout latitude', '_jluxe_mobile_lng' => 'Mobile checkout longitude', '_jluxe_mobile_map_address' => 'Mobile checkout map address' ) as $key => $label ) {
		$value = $order->get_meta( $key, true );
		if ( '' !== (string) $value ) {
			$personal_data[] = array( 'name' => $label, 'value' => (string) $value );
		}
	}
	return $personal_data;
}
add_filter( 'woocommerce_privacy_export_order_personal_data', 'jluxe_mobile_checkout_export_personal_data', 10, 2 );

function jluxe_mobile_checkout_erase_personal_data( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'delete_meta_data' ) ) {
		return;
	}
	foreach ( array( '_jluxe_mobile_lat', '_jluxe_mobile_lng', '_jluxe_mobile_plaque', '_jluxe_mobile_map_address' ) as $key ) {
		$order->delete_meta_data( $key );
	}
	if ( method_exists( $order, 'save_meta_data' ) ) {
		$order->save_meta_data();
	}
}
add_action( 'woocommerce_privacy_remove_order_personal_data', 'jluxe_mobile_checkout_erase_personal_data', 10, 1 );

/* ---------- AJAX registration ---------- */

function jluxe_mobile_checkout_register_ajax(): void {
	foreach ( array( 'state', 'save', 'delete', 'select', 'shipping', 'summary', 'gateways', 'pay', 'geocode', 'otp_request', 'otp_verify' ) as $action ) {
		add_action( 'wp_ajax_jluxe_mobile_' . $action, 'jluxe_mobile_checkout_ajax_' . $action );
		add_action( 'wp_ajax_nopriv_jluxe_mobile_' . $action, 'jluxe_mobile_checkout_ajax_' . $action );
	}
}
add_action( 'wp_loaded', 'jluxe_mobile_checkout_register_ajax', 20 );
