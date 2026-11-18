<?php
/**
 * تسویه‌حساب اصلی: کارت ورود پیامکی + انتخاب آدرس ذخیره‌شده.
 *
 * - فرم پیش‌فرض ورود ووکامرس (نام‌کاربری/رمز) از تسویه‌حساب حذف می‌شود.
 * - به‌جای آن، فقط وقتی لاگین اجباری است (تنظیم «ثبت‌نام در تسویه‌حساب
 *   الزامی است» در ووکامرس) و پیامک پوسته فعال است، یک کارت زیبا نمایش
 *   داده می‌شود که به صفحهٔ ورود OTP پوسته لینک می‌دهد و بعد از ورود،
 *   مشتری به همین صفحهٔ تسویه‌حساب برمی‌گردد.
 * - اگر ثبت‌نام الزامی نباشد، هیچ بخش ورودی دیده نمی‌شود (خرید مهمان).
 * - اگر پیامک فعال نباشد، رفتار پیش‌فرض ووکامرس حفظ می‌شود تا مشتری قفل نشود.
 * - برای مشتری لاگین‌کرده با آدرس ذخیره‌شده، کارت‌های انتخاب آدرس نمایش
 *   داده می‌شود؛ انتخاب آدرس قبلی فرم را پر می‌کند و «آدرس جدید» فرم خالی می‌دهد.
 *
 * این ماژول فقط تسویه‌حساب اصلی (classic/guided) را پوشش می‌دهد؛ مسیر DSN
 * موبایل دست نمی‌خورد. محاسبات سبد/حمل/مالیات/درگاه/سابمیت ووکامرس ثابت می‌مانند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا کارت ورود پیامکی باید در تسویه‌حساب نمایش داده شود؟
 *
 * هر سه شرط لازم است: مهمان، لاگین اجباری در ووکامرس، و پیامک فعال.
 */
function jluxe_checkout_login_card_visible(): bool {
	if ( is_user_logged_in() ) {
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
 * آیا فرم پیش‌فرض ورود ووکامرس باید از تسویه‌حساب حذف شود؟
 *
 * وقتی کارت پیامکی نمایش داده می‌شود یا وقتی ثبت‌نام الزامی نیست (خرید
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
	// کارت پیامکی نمایش داده می‌شود → فرم پیش‌فرض حذف.
	if ( jluxe_checkout_login_card_visible() ) {
		return true;
	}
	// ثبت‌نام الزامی نیست → هیچ بخش ورودی دیده نمی‌شود.
	if ( ! WC()->checkout->is_registration_required() ) {
		return true;
	}
	// لاگین اجباری ولی پیامک نیست → fallback پیش‌فرض ووکامرس می‌ماند.
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
 * رندر کارت ورود پیامکی در تسویه‌حساب.
 */
function jluxe_render_checkout_login_card(): void {
	if ( ! jluxe_checkout_login_card_visible() ) {
		return;
	}
	$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
	$login_url = add_query_arg( 'redirect_to', $checkout_url, $account_url );
	?>
	<section class="jluxe-checkout-login" data-jluxe-checkout-login aria-labelledby="jluxe-checkout-login-title">
		<div class="jluxe-checkout-login__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
		</div>
		<div class="jluxe-checkout-login__body">
			<h2 class="jluxe-checkout-login__title" id="jluxe-checkout-login-title">برای تکمیل خرید وارد شوید</h2>
			<p class="jluxe-checkout-login__text">با شمارهٔ موبایل و کد یک‌بارمصرف وارد شوید؛ بعد از ورود به همین صفحه برمی‌گردید و آدرس‌های ذخیره‌شده‌تان آماده است.</p>
		</div>
		<a class="jluxe-checkout-login__button jluxe-checkout-action-button jluxe-checkout-action-button--primary" href="<?php echo esc_url( $login_url ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
			<span>ورود با پیامک</span>
		</a>
	</section>
	<?php
}
add_action( 'woocommerce_before_checkout_form', 'jluxe_render_checkout_login_card', 10 );

/**
 * جایگزینی پیام متنی «باید وارد شوید» با کارت ورود پیامکی در حالت قفل‌شده.
 */
function jluxe_checkout_login_message( string $message ): string {
	if ( jluxe_checkout_login_card_visible() ) {
		return '';
	}
	return $message;
}
add_filter( 'woocommerce_checkout_must_be_logged_in_message', 'jluxe_checkout_login_message' );

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
 * بارگذاری استایل و اسکریپت کارت ورود و انتخاب آدرس در تسویه‌حساب اصلی.
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
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_checkout_login_assets', 23 );

/**
 * رندر انتخاب‌گر آدرس ذخیره‌شده در تسویه‌حساب اصلی.
 *
 * فقط برای مشتری لاگین‌کرده با آدرس ذخیره‌شده نمایش داده می‌شود.
 */
function jluxe_render_address_selector(): void {
	$addresses = jluxe_get_customer_addresses();
	if ( empty( $addresses ) ) {
		return;
	}
	?>
	<section class="jluxe-checkout-addresses" data-jluxe-address-selector aria-labelledby="jluxe-checkout-addresses-title">
		<header class="jluxe-checkout-addresses__heading">
			<h2 class="jluxe-checkout-addresses__title" id="jluxe-checkout-addresses-title">آدرس تحویل</h2>
			<p class="jluxe-checkout-addresses__hint">آدرس ذخیره‌شده را انتخاب کنید یا آدرس جدید ثبت کنید.</p>
		</header>
		<div class="jluxe-checkout-addresses__list" role="radiogroup" aria-label="انتخاب آدرس تحویل">
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
			<label class="jluxe-checkout-addresses__card jluxe-checkout-addresses__card--new" data-jluxe-address-card>
				<input type="radio" name="jluxe_saved_address" value="new" data-jluxe-address-option />
				<span class="jluxe-checkout-addresses__radio" aria-hidden="true"></span>
				<span class="jluxe-checkout-addresses__card-body">
					<span class="jluxe-checkout-addresses__card-label">آدرس جدید</span>
					<span class="jluxe-checkout-addresses__card-line">ثبت آدرس جدید برای این سفارش</span>
				</span>
			</label>
		</div>
	</section>
	<?php
}
