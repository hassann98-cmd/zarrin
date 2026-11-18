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
	if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->checkout ) {
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
	if ( ! is_user_logged_in() || ! function_exists( 'WC_Customer' ) ) {
		return array();
	}
	$customer = new WC_Customer( get_current_user_id(), true );
	$out = array();

	$billing = array(
		'first_name' => $customer->get_billing_first_name(),
		'last_name'  => $customer->get_billing_last_name(),
		'company'    => $customer->get_billing_company(),
		'address_1'  => $customer->get_billing_address_1(),
		'address_2'  => $customer->get_billing_address_2(),
		'city'       => $customer->get_billing_city(),
		'state'      => $customer->get_billing_state(),
		'postcode'   => $customer->get_billing_postcode(),
		'country'    => $customer->get_billing_country(),
		'phone'      => $customer->get_billing_phone(),
		'email'      => $customer->get_billing_email(),
	);
	if ( jluxe_address_has_content( $billing ) ) {
		$out[] = array(
			'type'    => 'billing',
			'label'   => 'آدرس صورتحساب',
			'address' => $billing,
		);
	}

	$shipping = array(
		'first_name' => $customer->get_shipping_first_name(),
		'last_name'  => $customer->get_shipping_last_name(),
		'company'    => $customer->get_shipping_company(),
		'address_1'  => $customer->get_shipping_address_1(),
		'address_2'  => $customer->get_shipping_address_2(),
		'city'       => $customer->get_shipping_city(),
		'state'      => $customer->get_shipping_state(),
		'postcode'   => $customer->get_shipping_postcode(),
		'country'    => $customer->get_shipping_country(),
	);
	if ( jluxe_address_has_content( $shipping ) && ! jluxe_addresses_equal( $billing, $shipping ) ) {
		$out[] = array(
			'type'    => 'shipping',
			'label'   => 'آدرس ارسال',
			'address' => $shipping,
		);
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
function jluxe_format_address_line( array $address ): string {
	$parts = array();
	foreach ( array( 'state', 'city', 'address_1', 'address_2' ) as $key ) {
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
 *                    address_1, postcode, email
 * @return bool موفقیت ذخیره
 */
function jluxe_save_customer_address( array $data ): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user_id = get_current_user_id();
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
	if ( ! empty( $data['email'] ) ) {
		$fields['billing_email'] = sanitize_email( $data['email'] );
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
	return true;
}

/**
 * AJAX: ذخیرهٔ آدرس جدید در تسویه‌حساب و برگرداندن لیست به‌روز آدرس‌ها.
 */
function jluxe_ajax_save_checkout_address(): void {
	check_ajax_referer( 'jluxe_checkout_address', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'ابتدا وارد شوید.' ) );
	}
	$data = array(
		'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
		'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
		'phone'      => isset( $_POST['phone'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '',
		'state'      => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
		'city'       => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
		'address_1'  => isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '',
		'postcode'   => isset( $_POST['postcode'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : '',
		'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
	);
	foreach ( array( 'first_name', 'last_name', 'state', 'city', 'address_1', 'postcode' ) as $required ) {
		if ( '' === trim( (string) $data[ $required ] ) ) {
			wp_send_json_error( array( 'message' => 'لطفاً همهٔ فیلدهای آدرس را تکمیل کنید.' ) );
		}
	}
	if ( ! preg_match( '/^\d{10}$/', (string) $data['postcode'] ) ) {
		wp_send_json_error( array( 'message' => 'کدپستی باید ۱۰ رقم باشد.' ) );
	}
	if ( '' !== $data['email'] && ! is_email( $data['email'] ) ) {
		wp_send_json_error( array( 'message' => 'ایمیل معتبر نیست.' ) );
	}
	if ( ! jluxe_save_customer_address( $data ) ) {
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
	wp_send_json_success( array( 'addresses' => $formatted ) );
}
add_action( 'wp_ajax_jluxe_save_checkout_address', 'jluxe_ajax_save_checkout_address' );

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
	$cities = function_exists( 'jluxe_iran_cities' ) ? jluxe_iran_cities() : array();
	wp_localize_script( 'jluxe-checkout-login', 'JLUXE_CHECKOUT_ADDRESS', array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'jluxe_checkout_address' ),
		'provinces'  => jluxe_checkout_provinces(),
		'cities'     => $cities,
	) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_checkout_login_assets', 23 );

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
	<section class="jluxe-checkout-addresses" data-jluxe-address-selector aria-labelledby="jluxe-checkout-addresses-title">
		<header class="jluxe-checkout-addresses__heading">
			<h2 class="jluxe-checkout-addresses__title" id="jluxe-checkout-addresses-title">آدرس تحویل</h2>
			<p class="jluxe-checkout-addresses__hint">مشخصات شما از حساب‌تان خوانده شده؛ در صورت نیاز می‌توانید ویرایش کنید.</p>
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

		<?php if ( ! empty( $addresses ) ) : ?>
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
						<span class="jluxe-checkout-addresses__data" data-jluxe-address-data hidden><?php echo esc_attr( wp_json_encode( $entry['address'] ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<button type="button" class="jluxe-checkout-addresses__add jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-jluxe-address-toggle aria-expanded="false">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			<span>افزودن آدرس جدید</span>
		</button>

		<div class="jluxe-checkout-addresses__form" data-jluxe-address-form hidden>
			<div class="jluxe-checkout-addresses__fields">
				<div class="jluxe-checkout-addresses__field">
					<label id="jluxe-new-address-state-label">استان</label>
					<div class="jluxe-checkout-picker" data-jluxe-picker="state">
						<button type="button" class="jluxe-checkout-picker__button" data-jluxe-picker-button aria-haspopup="listbox" aria-expanded="false" aria-labelledby="jluxe-new-address-state-label">
							<span data-jluxe-picker-value>انتخاب استان</span>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
						<div class="jluxe-checkout-picker__panel" data-jluxe-picker-panel hidden>
							<div class="jluxe-checkout-picker__search">
								<input type="search" placeholder="جست‌وجوی استان…" autocomplete="off" data-jluxe-picker-search aria-label="جست‌وجوی استان" />
							</div>
							<ul class="jluxe-checkout-picker__list" role="listbox" data-jluxe-picker-list></ul>
						</div>
					</div>
					<input type="hidden" data-jluxe-new-field="state" value="" />
				</div>
				<div class="jluxe-checkout-addresses__field">
					<label id="jluxe-new-address-city-label">شهر</label>
					<div class="jluxe-checkout-picker" data-jluxe-picker="city">
						<button type="button" class="jluxe-checkout-picker__button" data-jluxe-picker-button aria-haspopup="listbox" aria-expanded="false" aria-labelledby="jluxe-new-address-city-label" disabled>
							<span data-jluxe-picker-value>ابتدا استان را انتخاب کنید</span>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
						<div class="jluxe-checkout-picker__panel" data-jluxe-picker-panel hidden>
							<div class="jluxe-checkout-picker__search">
								<input type="search" placeholder="جست‌وجوی شهر…" autocomplete="off" data-jluxe-picker-search aria-label="جست‌وجوی شهر" />
							</div>
							<ul class="jluxe-checkout-picker__list" role="listbox" data-jluxe-picker-list></ul>
						</div>
					</div>
					<input type="hidden" data-jluxe-new-field="city" value="" />
				</div>
				<div class="jluxe-checkout-addresses__field jluxe-checkout-addresses__field--wide">
					<label for="jluxe-new-address-1">آدرس دقیق</label>
					<textarea id="jluxe-new-address-1" rows="2" data-jluxe-new-field="address_1" autocomplete="street-address"></textarea>
				</div>
				<div class="jluxe-checkout-addresses__field">
					<label for="jluxe-new-address-postcode">کدپستی</label>
					<input type="text" id="jluxe-new-address-postcode" inputmode="numeric" maxlength="10" dir="ltr" data-jluxe-new-field="postcode" autocomplete="postal-code" />
				</div>
				<div class="jluxe-checkout-addresses__field">
					<label for="jluxe-new-address-email">ایمیل</label>
					<input type="email" id="jluxe-new-address-email" dir="ltr" data-jluxe-new-field="email" autocomplete="email" />
				</div>
			</div>
			<p class="jluxe-checkout-addresses__status" data-jluxe-address-status role="status" aria-live="polite" hidden></p>
			<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" data-jluxe-address-save>ذخیره آدرس</button>
		</div>
	</section>
	<?php
}
