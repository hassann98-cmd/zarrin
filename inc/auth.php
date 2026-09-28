<?php
/**
 * =====================================================================
 *  JLuxe – ورود و ثبت‌نام واقعی (نام‌کاربری/ایمیل + رمز عبور)
 * ---------------------------------------------------------------------
 *  پشتوانه‌ی صفحه‌ی my-account وقتی کاربر لاگین نیست (page-my-account.php).
 *  از توابع اصلی خودِ وردپرس استفاده می‌کنه (wp_signon/wp_insert_user) —
 *  حساب واقعی ساخته می‌شه، نه شبیه‌سازی. رمز عبور هیچ‌وقت لاگ/ذخیره‌ی خام
 *  نمی‌شه (wp_insert_user خودش هش می‌کنه).
 *
 *  Endpoints:
 *   POST /wp-json/jluxe/v1/auth/login    { login, password }
 *   POST /wp-json/jluxe/v1/auth/register { username, email, password }
 * =====================================================================
 */

defined( 'ABSPATH' ) || exit;

function jluxe_register_auth_rest_routes(): void {
	register_rest_route(
		'jluxe/v1',
		'/auth/login',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_auth_login',
			'permission_callback' => '__return_true',
			'args'                => array(
				'login'    => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'password' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	register_rest_route(
		'jluxe/v1',
		'/auth/register',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_auth_register',
			'permission_callback' => '__return_true',
			'args'                => array(
				'username' => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_user',
				),
				'email'    => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_email',
				),
				'password' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_auth_rest_routes' );

/**
 * rate limit مشترک بین لاگین/ثبت‌نام — بر اساس IP، برای جلوگیری از brute
 * force روی رمز عبور یا اسپم ثبت‌نام روی endpoint عمومی بدون nonce.
 */
function jluxe_auth_rate_limit_ok( string $bucket, int $max, int $window_seconds ): bool {
	return jluxe_security_rate_limit( 'auth_' . $bucket, jluxe_theme_get_client_ip(), $max, $window_seconds );
}

function jluxe_handle_auth_login( WP_REST_Request $request ) {
	if ( ! jluxe_auth_rate_limit_ok( 'login', 10, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'jluxe_auth_rate_limited', 'تعداد تلاش‌ها زیاده — چند دقیقه دیگه دوباره تلاش کن.', array( 'status' => 429 ) );
	}

	$login    = (string) $request->get_param( 'login' );
	$password = (string) $request->get_param( 'password' );

	$user = wp_signon( array(
		'user_login'    => $login,
		'user_password' => $password,
		'remember'      => true,
	), is_ssl() );

	if ( is_wp_error( $user ) ) {
		return new WP_Error( 'jluxe_auth_login_failed', 'نام کاربری/ایمیل یا رمز عبور اشتباه است.', array( 'status' => 401 ) );
	}

	return array( 'success' => true );
}

function jluxe_handle_auth_register( WP_REST_Request $request ) {
	if ( ! jluxe_registration_enabled() ) {
		return new WP_Error( 'jluxe_registration_disabled', 'ثبت‌نام در حال حاضر غیرفعال است.', array( 'status' => 403 ) );
	}
	if ( ! jluxe_auth_rate_limit_ok( 'register', 5, HOUR_IN_SECONDS ) ) {
		return new WP_Error( 'jluxe_auth_rate_limited', 'تعداد تلاش‌ها زیاده — بعداً دوباره تلاش کن.', array( 'status' => 429 ) );
	}

	$username = (string) $request->get_param( 'username' );
	$email    = (string) $request->get_param( 'email' );
	$password = (string) $request->get_param( 'password' );

	if ( ! validate_username( $username ) || strlen( $username ) < 3 ) {
		return new WP_Error( 'jluxe_auth_bad_username', 'نام کاربری معتبر نیست (فقط حروف انگلیسی/عدد، حداقل ۳ کاراکتر).', array( 'status' => 400 ) );
	}
	if ( username_exists( $username ) ) {
		return new WP_Error( 'jluxe_auth_username_taken', 'این نام کاربری قبلاً استفاده شده است.', array( 'status' => 409 ) );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'jluxe_auth_bad_email', 'ایمیل معتبر نیست.', array( 'status' => 400 ) );
	}
	if ( email_exists( $email ) ) {
		return new WP_Error( 'jluxe_auth_email_taken', 'حسابی با این ایمیل قبلاً ثبت شده است.', array( 'status' => 409 ) );
	}
	if ( jluxe_strlen($password) < 12 ) {
		return new WP_Error( 'jluxe_auth_weak_password', 'رمز عبور باید حداقل ۱۲ کاراکتر باشد.', array( 'status' => 400 ) );
	}

	if ( function_exists( 'wc_create_new_customer' ) ) {
		// Honor WooCommerce registration validation and customer-created integrations.
		$user_id = wc_create_new_customer( $email, $username, $password );
	} else {
		$errors = apply_filters( 'registration_errors', new WP_Error(), $username, $email );
		if ( $errors->has_errors() ) {
			return $errors;
		}
		$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'role' => 'subscriber' ) );
	}

	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'jluxe_auth_register_failed', 'ثبت‌نام ناموفق بود.', array( 'status' => 500 ) );
	}

	if ( ! function_exists( 'wc_create_new_customer' ) ) {
		wp_new_user_notification( $user_id, null, 'user' );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	$user = get_userdata( $user_id );
	do_action( 'wp_login', $user->user_login, $user );

	return array( 'success' => true );
}

/** Read-only identity bootstrap; never place a customer's name/email in cacheable HTML. */
function jluxe_ajax_session(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_send_json_error( array( 'message' => 'Method not allowed' ), 405 );
	}
	nocache_headers();
	$user = wp_get_current_user();
	wp_send_json_success( array(
		'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'cartNonce' => wp_create_nonce( 'jluxe_cart' ),
		'auth' => array(
			'isLoggedIn' => is_user_logged_in(),
			'displayName' => is_user_logged_in() ? $user->display_name : '',
			'email' => is_user_logged_in() ? $user->user_email : '',
		),
	) );
}
add_action( 'wp_ajax_jluxe_session', 'jluxe_ajax_session' );
add_action( 'wp_ajax_nopriv_jluxe_session', 'jluxe_ajax_session' );

/** Cache plugins/CDNs must also bypass logged-in cookies and account/cart/checkout URLs. */
function jluxe_protect_personal_pages_from_cache(): void {
	if ( is_user_logged_in() || ( function_exists( 'is_account_page' ) && ( is_account_page() || is_cart() || is_checkout() ) ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}
}
add_action( 'template_redirect', 'jluxe_protect_personal_pages_from_cache', 0 );

/** Linking requires BOTH an authenticated customer session (REST nonce) and a phone OTP. */
function jluxe_render_phone_link_form(): void {
	if ( ! jluxe_otp_available() || ! jluxe_otp_user_allowed( wp_get_current_user() ) ) {
		return;
	}
	wp_enqueue_script( 'jluxe-phone-link', JLUXE_THEME_URI . '/assets/js/phone-link.js', array( 'jluxe-storefront-utils' ), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/phone-link.js' ), true );
	?>
	<form class="jluxe-phone-link rounded-xl border border-border p-5 mt-6" data-jluxe-phone-link
		data-request-url="<?php echo esc_url( rest_url( 'jluxe/v1/auth/otp-request' ) ); ?>"
		data-confirm-url="<?php echo esc_url( rest_url( 'jluxe/v1/auth/otp-link' ) ); ?>"
		data-session-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<h2 class="text-h3 font-bold mb-3">تأیید شماره برای ورود پیامکی</h2>
		<p class="text-small mb-3">شمارهٔ صورتحساب به‌تنهایی شناسهٔ ورود نیست. شماره‌ای را که مالک آن هستید تأیید کنید.</p>
		<p><label for="jluxe-link-phone">شماره موبایل</label><input id="jluxe-link-phone" name="phone" type="tel" autocomplete="tel" required dir="ltr" value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'jluxe_phone', true ) ); ?>" /></p>
		<button type="button" class="button" data-send-code>ارسال کد تأیید</button>
		<p><label for="jluxe-link-code">کد ۶ رقمی</label><input id="jluxe-link-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required dir="ltr" /></p>
		<button type="submit" class="button">تأیید و اتصال شماره</button>
		<p data-link-status role="status" aria-live="polite"></p>
	</form>
	<?php
}
add_action( 'woocommerce_after_edit_account_form', 'jluxe_render_phone_link_form' );
