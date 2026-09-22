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
					'sanitize_callback' => 'sanitize_user',
				),
				'email'    => array(
					'required'          => true,
					'type'              => 'string',
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
	$ip    = jluxe_theme_get_client_ip();
	$key   = 'jluxe_auth_rl_' . $bucket . '_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return false;
	}
	set_transient( $key, $count + 1, $window_seconds );
	return true;
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
	if ( strlen( $password ) < 6 ) {
		return new WP_Error( 'jluxe_auth_weak_password', 'رمز عبور باید حداقل ۶ کاراکتر باشد.', array( 'status' => 400 ) );
	}

	$user_id = wp_insert_user( array(
		'user_login' => $username,
		'user_email' => $email,
		'user_pass'  => $password,
		'role'       => 'customer',
	) );

	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'jluxe_auth_register_failed', 'ثبت‌نام ناموفق بود.', array( 'status' => 500 ) );
	}

	wp_new_user_notification( $user_id, null, 'user' );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	return array( 'success' => true );
}
