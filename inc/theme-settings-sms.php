<?php
/**
 * ورود با پیامک (OTP) — تنظیمات ادمین + REST endpoints. دقیقاً همون الگوی
 * inc/theme-settings-ai.php: کلید API فقط سمت سرور، و تا provider/کلید واقعاً
 * ست نشده باشه، endpointها خطای صریح «پیکربندی نشده» برمی‌گردونن — هیچ
 * کد پیامکیِ فیک ارسال/تایید نمی‌شه.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_render_sms_page(): void {
	$status   = null;
	$warnings = array();

	if ( isset( $_POST['jluxe_settings_nonce'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
		if ( ! empty( $_POST['jluxe_reset_section'] ) ) {
			jluxe_update_settings_section( 'sms', jluxe_theme_settings_defaults()['sms'] );
			jluxe_set_sms_api_key( '' );
			$status = 'reset';
		} else {
			$defaults = jluxe_theme_settings_defaults();
			$posted   = wp_unslash( $_POST['sms'] ?? array() );
			$clean    = jluxe_sanitize_sms( $posted, $defaults['sms'] );
			jluxe_update_settings_section( 'sms', $clean );

			// R90 — همان محافظتِ کلیدِ AI (inc/theme-settings-secrets.php).
			$warnings[] = jluxe_apply_posted_secret( 'sms_api_key', jluxe_get_sms_api_key(), 'jluxe_set_sms_api_key', 'کلید API / رمز عبورِ پیامک', false );
			$status = 'saved';
		}
	}

	$settings = jluxe_get_fresh_settings();
	$sms      = $settings['sms'];
	$sms_key  = jluxe_get_sms_api_key();
	$has_key  = '' !== $sms_key;
	$key_hint = jluxe_secret_hint( $sms_key );
	unset( $sms_key );

	jluxe_settings_page_shell( 'ورود با پیامک (OTP)', 'jluxe-sms', $status, function () use ( $sms, $has_key, $key_hint, $warnings ) {
		?>
		<p class="description">صفحه‌ی «ورود و عضویت» (my-account) همیشه با نام‌کاربری/ایمیل کار می‌کنه. تب «شماره موبایل» هم توی همون صفحه نمایش داده می‌شه، ولی تا وقتی این‌جا provider و کلید واقعی ست نکنی، غیرفعاله و پیام «سرویس پیامکی هنوز وصل نشده» نشون می‌ده — هیچ کدی به‌صورت فیک ارسال/تایید نمی‌شه.</p>
		<?php jluxe_render_secret_warnings( $warnings ); ?>
		<form method="post" autocomplete="off">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<h2>عمومی</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی ورود با پیامک</th>
					<td><label><input type="checkbox" name="sms[enabled]" value="1" <?php checked( $sms['enabled'] ); ?> /> نمایش تب «شماره موبایل» در صفحه‌ی ورود</label>
					</td>
				</tr>
				<tr>
					<th scope="row">ورود فقط با رمز پیامکی</th>
					<td>
						<label><input type="checkbox" name="sms[otp_only]" value="1" <?php checked( ! empty( $sms['otp_only'] ) ); ?> /> مشتری فقط با رمز پیامکی وارد شود — بدونِ نام‌کاربری/رمز عبور</label>
						<p class="description">در این حالت تبِ «نام کاربری» از صفحهٔ ورود حذف می‌شود؛ حسابِ جدید هم به‌صورت خودکار با همان شماره ساخته می‌شود. مدیران همیشه از <code>wp-login.php</code> وارد می‌شوند و این مسیر باز می‌ماند.</p>
						<?php if ( $sms['enabled'] && ( ! $has_key || '' === $sms['provider'] ) ) : ?>
							<p class="description" style="color:#b32d2e">فعاله ولی provider/کلید تنظیم نشده — تب تا زمان تکمیل تنظیمات پایین غیرفعال می‌مونه.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2>اتصال به سرویس پیامکی</h2>
			<p class="description">کلید API فقط سمت سرور ذخیره می‌شه (آپشن جدا، غیر از بقیه‌ی تنظیمات) و هیچ‌وقت به مرورگر/HTML/برون‌بری فرستاده نمی‌شه.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">ارائه‌دهنده</th>
					<td>
						<select name="sms[provider]">
							<option value="">— انتخاب کن —</option>
							<option value="kavenegar" <?php selected( $sms['provider'], 'kavenegar' ); ?>>کاوه‌نگار (Kavenegar)</option>
							<option value="melipayamak" <?php selected( $sms['provider'], 'melipayamak' ); ?>>ملی‌پیامک (Melipayamak)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-username">نام کاربری</label></th>
					<td><input type="text" id="jluxe-sms-username" name="sms[username]" value="<?php echo esc_attr( $sms['username'] ); ?>" class="regular-text" dir="ltr" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other" placeholder="فقط برای ملی‌پیامک — نام کاربری پنل" />
						<p class="description">سرویس ارسال ملی‌پیامک (REST کلاسیک) به نام‌کاربری و رمز، هر دو، نیاز داره — رمز رو پایین به‌عنوان «کلید API» وارد کن.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-key">کلید API / رمز عبور</label></th>
					<td>
						<?php
						jluxe_render_secret_field(
							array(
								'id'            => 'jluxe-sms-key',
								'name'          => 'sms_api_key',
								'has_key'       => $has_key,
								'hint'          => $key_hint,
								'confirm_clear' => 'کلید API حذف بشه؟ ورود با پیامک تا تنظیم دوباره کار نمی‌کنه.',
							)
						);
						?>
						<p class="description">کاوه‌نگار: توکن API. ملی‌پیامک: مقدار <strong>APIKey جهت استفاده از وب‌سرویس</strong> در پنل ملی‌پیامک.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-sender">شماره خط ارسال</label></th>
					<td><input type="text" id="jluxe-sms-sender" name="sms[sender]" value="<?php echo esc_attr( $sms['sender'] ); ?>" class="regular-text" dir="ltr" placeholder="فقط برای ملی‌پیامک — شماره خط اختصاصی" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-template">نام الگو (Template)</label></th>
					<td><input type="text" id="jluxe-sms-template" name="sms[template]" value="<?php echo esc_attr( $sms['template'] ); ?>" class="regular-text" dir="ltr" placeholder="فقط برای کاوه‌نگار — نام الگوی Verify Lookup در پنل" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-body-id">کد متن / Body ID پترن</label></th>
					<td>
						<input type="text" id="jluxe-sms-body-id" name="sms[body_id]" value="<?php echo esc_attr( $sms['body_id'] ?? '' ); ?>" class="regular-text" dir="ltr" inputmode="numeric" placeholder="مثلاً 532701" />
						<p class="description">برای ملی‌پیامک: کد متنِ وب‌سرویس خدماتی/پترن. این مقدار از پنل شما خوانده می‌شود و در کد هاردکد نشده است.</p>
					</td>
				</tr>
			</table>

			<?php jluxe_settings_submit_button( true, 'sms' ); ?>
		</form>
		<?php
	} );
}

/* =====================================================================
 * REST endpoints — ورود/عضویت واقعی (username یا email + password) همیشه
 * فعاله؛ OTP فقط وقتی provider/کلید واقعی ست شده باشه.
 * ===================================================================== */

function jluxe_register_sms_rest_routes(): void {
	register_rest_route(
		'jluxe/v1',
		'/auth/otp-request',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_otp_request',
			'permission_callback' => '__return_true',
			'args'                => array(
				'phone' => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
	register_rest_route(
		'jluxe/v1',
		'/auth/otp-verify',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_otp_verify',
			'permission_callback' => '__return_true',
			'args'                => array(
				'phone' => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'code'  => array(
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
	register_rest_route( 'jluxe/v1', '/auth/otp-link', array(
		'methods' => 'POST',
		'callback' => 'jluxe_handle_otp_link',
		'permission_callback' => function () { return is_user_logged_in(); },
		'args' => array(
			'phone' => array( 'required' => true, 'type' => 'string', 'validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'sanitize_text_field' ),
			'code' => array( 'required' => true, 'type' => 'string', 'validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'sanitize_text_field' ),
		),
	) );
}
add_action( 'rest_api_init', 'jluxe_register_sms_rest_routes' );

/** OTP records contain a keyed hash, expiry and attempt budget, never the code itself. */
function jluxe_otp_key( string $phone ): string {
	return 'jluxe_otp_v2_' . hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) );
}

function jluxe_otp_hash( string $phone, string $code, string $intent, int $user_id ): string {
	return hash_hmac( 'sha256', $phone . '|' . $intent . '|' . $user_id . '|' . $code, wp_salt( 'auth' ) );
}

function jluxe_expire_otp_challenge( string $key ): void {
	$record = get_option( $key );
	if ( is_array( $record ) && (int) $record['expires'] <= time() ) {
		jluxe_consume_option( $key, $record );
	}
}
add_action( 'jluxe_expire_otp_challenge', 'jluxe_expire_otp_challenge' );

function jluxe_otp_limit_error(): WP_Error {
	return new WP_Error( 'jluxe_sms_rate_limited', 'تعداد تلاش‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.', array( 'status' => 429 ) );
}

function jluxe_otp_available(): bool {
	// A customer on one blog may be staff on another; network-wide OTP authorization is not implemented.
	if ( function_exists( 'is_multisite' ) && is_multisite() ) { return false; }
	$settings = jluxe_get_theme_settings()['sms'];
	return ! empty( $settings['enabled'] ) && '' !== jluxe_get_sms_api_key() && in_array( $settings['provider'], array( 'kavenegar', 'melipayamak' ), true );
}

function jluxe_handle_otp_request( WP_REST_Request $request ) {
	if ( ! jluxe_otp_available() ) {
		return new WP_Error( 'jluxe_sms_disabled', 'ورود پیامکی فعال نیست؛ از نام کاربری و رمز استفاده کنید.', array( 'status' => 503 ) );
	}
	$phone = jluxe_normalize_phone( (string) $request->get_param( 'phone' ) );
	if ( '' === $phone ) {
		return new WP_Error( 'jluxe_sms_bad_phone', 'شماره موبایل معتبر نیست.', array( 'status' => 400 ) );
	}
	$intent = 'link' === $request->get_param( 'intent' ) ? 'link' : 'login';
	$user_id = 'link' === $intent ? get_current_user_id() : 0;
	if ( 'link' === $intent && ! jluxe_otp_user_allowed( get_userdata( $user_id ) ) ) {
		return new WP_Error( 'jluxe_sms_link_forbidden', 'ابتدا با رمز وارد حساب مشتری شوید.', array( 'status' => 403 ) );
	}

	// Reserve the budget BEFORE contacting the paid provider, including failed sends.
	if ( ! jluxe_security_rate_limit( 'otp_send_ip', jluxe_theme_get_client_ip(), 10, 10 * MINUTE_IN_SECONDS ) ||
		! jluxe_security_rate_limit( 'otp_send_phone', $phone, 3, 10 * MINUTE_IN_SECONDS ) ) {
		return jluxe_otp_limit_error();
	}
	$lock = jluxe_security_lock( 'otp:' . $phone );
	if ( ! $lock ) {
		return jluxe_otp_limit_error();
	}
	try {
		$code = (string) wp_rand( 100000, 999999 );
		$key = jluxe_otp_key( $phone );
		$record = array(
			'hash' => jluxe_otp_hash( $phone, $code, $intent, $user_id ),
			'expires' => time() + 2 * MINUTE_IN_SECONDS,
			'attempts' => 0,
			'intent' => $intent,
			'user_id' => $user_id,
		);
		if ( ! update_option( $key, $record, false ) ) {
			return new WP_Error( 'jluxe_sms_storage', 'امکان ایجاد کد وجود ندارد؛ دوباره تلاش کنید.', array( 'status' => 503 ) );
		}
		wp_schedule_single_event( $record['expires'] + 1, 'jluxe_expire_otp_challenge', array( $key ) );
		$sent = jluxe_send_otp_sms( jluxe_get_theme_settings()['sms'], jluxe_get_sms_api_key(), $phone, $code );
		if ( is_wp_error( $sent ) ) {
			jluxe_consume_option( $key, $record );
			return $sent;
		}
		return array( 'sent' => true, 'expiresIn' => 120, 'codeLength' => 6 );
	} finally {
		jluxe_security_unlock( $lock );
	}
}

function jluxe_handle_otp_verify( WP_REST_Request $request ) {
	return jluxe_verify_otp( $request, false );
}

function jluxe_handle_otp_link( WP_REST_Request $request ) {
	return jluxe_verify_otp( $request, true );
}

function jluxe_verify_otp( WP_REST_Request $request, bool $link ) {
	if ( ! jluxe_otp_available() ) {
		return new WP_Error( 'jluxe_sms_disabled', 'ورود پیامکی فعال نیست.', array( 'status' => 503 ) );
	}
	if ( ! jluxe_security_rate_limit( 'otp_verify_ip', jluxe_theme_get_client_ip(), 30, 10 * MINUTE_IN_SECONDS ) ) {
		return jluxe_otp_limit_error();
	}
	$phone = jluxe_normalize_phone( (string) $request->get_param( 'phone' ) );
	$code = trim( jluxe_ascii_digits( (string) $request->get_param( 'code' ) ) );
	if ( '' === $phone ) {
		return new WP_Error( 'jluxe_sms_bad_phone', 'شماره موبایل معتبر نیست.', array( 'status' => 400 ) );
	}
	if ( ! jluxe_security_rate_limit( 'otp_verify_phone', $phone, 10, 10 * MINUTE_IN_SECONDS ) ) {
		return jluxe_otp_limit_error();
	}
	$lock = jluxe_security_lock( 'otp:' . $phone );
	if ( ! $lock ) {
		return jluxe_otp_limit_error();
	}
	try {
		$key = jluxe_otp_key( $phone );
		$record = get_option( $key );
		$intent = $link ? 'link' : 'login';
		$user_id = $link ? get_current_user_id() : 0;
		if ( $link && ! jluxe_otp_user_allowed( get_userdata( $user_id ) ) ) {
			return new WP_Error( 'jluxe_sms_link_forbidden', 'ابتدا با رمز وارد حساب مشتری شوید.', array( 'status' => 403 ) );
		}
		$bad_code = new WP_Error( 'jluxe_sms_bad_code', 'کد وارد شده اشتباه یا منقضی شده است؛ کد جدید بگیرید.', array( 'status' => 400 ) );
		if ( ! is_array( $record ) || (int) $record['expires'] <= time() ) {
			jluxe_expire_otp_challenge( $key );
			return $bad_code;
		}
		if ( (int) $record['attempts'] >= 5 ) {
			jluxe_consume_option( $key, $record );
			return jluxe_otp_limit_error();
		}
		if ( ! preg_match( '/^[0-9]{6}$/D', $code ) || $record['intent'] !== $intent || (int) $record['user_id'] !== $user_id ||
			! hash_equals( $record['hash'], jluxe_otp_hash( $phone, $code, $intent, $user_id ) ) ) {
			++$record['attempts'];
			if ( $record['attempts'] >= 5 ) {
				// Read the original record for conditional deletion, not the incremented copy.
				$original = $record;
				--$original['attempts'];
				jluxe_consume_option( $key, $original );
				return jluxe_otp_limit_error();
			}
			if ( ! update_option( $key, $record, false ) ) {
				return jluxe_otp_limit_error();
			}
			return $bad_code;
		}
		// No two requests can authenticate with this record, even if a mutex lease expires.
		if ( ! jluxe_consume_option( $key, $record ) ) {
			return $bad_code;
		}

		$users = get_users( array( 'meta_key' => 'jluxe_phone', 'meta_value' => $phone, 'number' => 2, 'fields' => 'ID' ) );
		if ( count( $users ) > 1 || ( $link && $users && (int) $users[0] !== $user_id ) ) {
			return new WP_Error( 'jluxe_sms_ambiguous_phone', 'این شماره قابل اتصال خودکار نیست؛ با رمز وارد شوید یا با پشتیبانی تماس بگیرید.', array( 'status' => 409 ) );
		}
		if ( $link ) {
			update_user_meta( $user_id, 'jluxe_phone', $phone );
			if ( (string) get_user_meta( $user_id, 'jluxe_phone', true ) !== $phone ) {
				return new WP_Error( 'jluxe_sms_storage', 'ذخیرهٔ شماره انجام نشد؛ دوباره تلاش کنید.', array( 'status' => 503 ) );
			}
			return array( 'success' => true );
		}
		if ( $users ) {
			$user_id = (int) $users[0];
		} else {
			// Billing metadata is editable and is NOT evidence of account ownership.
			$candidates = array( $phone, '0' . $phone, '98' . $phone, '+98' . $phone, '0098' . $phone );
			foreach ( array( '۰۱۲۳۴۵۶۷۸۹', '٠١٢٣٤٥٦٧٨٩' ) as $digits ) {
				$map = array_combine( str_split( '0123456789' ), preg_split( '//u', $digits, -1, PREG_SPLIT_NO_EMPTY ) );
				foreach ( array_slice( $candidates, 0, 5 ) as $candidate ) {
					$candidates[] = strtr( $candidate, $map );
				}
			}
			$billing_accounts = get_users( array( 'meta_query' => array( array( 'key' => 'billing_phone', 'value' => $candidates, 'compare' => 'IN' ) ), 'number' => 1, 'fields' => 'ID' ) );
			if ( $billing_accounts ) {
				return new WP_Error( 'jluxe_sms_link_required', 'برای اولین ورود پیامکی، با رمز وارد حساب شوید و شماره را در «جزئیات حساب» تأیید کنید.', array( 'status' => 409 ) );
			}
			if ( ! jluxe_registration_enabled() ) {
				return new WP_Error( 'jluxe_registration_disabled', 'ثبت‌نام در حال حاضر غیرفعال است.', array( 'status' => 403 ) );
			}
			$user_id = wp_insert_user( array(
				'user_login' => 'customer_' . strtolower( wp_generate_password( 16, false, false ) ),
				'user_pass' => wp_generate_password( 32 ),
				// No fabricated email that could send a password reset to an unrelated mailbox.
				'user_email' => '',
				'display_name' => 'مشتری',
				'role' => get_role( 'customer' ) ? 'customer' : 'subscriber',
				'meta_input' => array( 'jluxe_phone' => $phone, 'billing_phone' => '0' . $phone ),
			) );
			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'jluxe_sms_user_create_failed', 'ساخت حساب ناموفق بود.', array( 'status' => 500 ) );
			}
			if ( (string) get_user_meta( $user_id, 'jluxe_phone', true ) !== $phone ) {
				return new WP_Error( 'jluxe_sms_storage', 'ذخیرهٔ شماره انجام نشد؛ با پشتیبانی تماس بگیرید.', array( 'status' => 503 ) );
			}
		}
		$user = get_userdata( $user_id );
		if ( ! jluxe_otp_user_allowed( $user ) ) {
			return new WP_Error( 'jluxe_sms_account_restricted', 'برای این حساب از ورود با رمز استفاده کنید.', array( 'status' => 403 ) );
		}
		if ( (string) get_user_meta( $user_id, 'jluxe_phone', true ) !== $phone ) {
			return new WP_Error( 'jluxe_sms_phone_changed', 'شمارهٔ حساب تغییر کرده است؛ دوباره وارد شوید.', array( 'status' => 409 ) );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		return array( 'success' => true );
	} finally {
		jluxe_security_unlock( $lock );
	}
}

/**
 * ارسال واقعی کد از طریق provider انتخاب‌شده. اگر provider ناشناخته باشه یا
 * فراخوانی fail کنه، خطای صریح برمی‌گرده — نه موفقیت جعلی.
 */
function jluxe_send_otp_sms( array $settings, string $api_key, string $phone, string $code ) {
	if ( 'kavenegar' === $settings['provider'] ) {
		$url = sprintf(
			'https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&token=%s&template=%s',
			rawurlencode( $api_key ),
			rawurlencode( '0' . $phone ),
			rawurlencode( $code ),
			rawurlencode( $settings['template'] ?: 'verify' )
		);
		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jluxe_sms_upstream', 'ارسال پیامک ناموفق بود.', array( 'status' => 502 ) );
		}
		$code_http = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code_http < 200 || $code_http >= 300 || ! is_array( $body ) || 200 !== (int) ( $body['return']['status'] ?? 0 ) ) {
			return new WP_Error( 'jluxe_sms_upstream', 'ارسال پیامک ناموفق بود.', array( 'status' => 502 ) );
		}
		return true;
	}

	if ( 'melipayamak' === $settings['provider'] ) {
		/*
		 * ملی‌پیامک: مسیر وب‌سرویس خدماتی اشتراکی/پترن.
		 * این همان BaseServiceNumber است که در WP SMS و SDK رسمی ملی‌پیامک
		 * استفاده می‌شود. برخلاف SendSMS/SendSMS، خط تبلیغاتیِ فرستنده را
		 * درگیر نمی‌کند.
		 *
		 * قرارداد API:
		 * username = نام کاربری
		 * password = APIKey/credential وب‌سرویس
		 * text     = مقادیر متغیرهای پترن، به ترتیب و با ;
		 * to       = شماره 09xxxxxxxx
		 * bodyId   = کد متن پترن
		 */
		if ( '' === $settings['username'] ) {
			return new WP_Error( 'jluxe_sms_missing_username', 'نام کاربریِ ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
		}

		$body_id = isset( $settings['body_id'] ) ? absint( $settings['body_id'] ) : 0;
		if ( ! $body_id ) {
			return new WP_Error( 'jluxe_sms_missing_body_id', 'کد متن / Body ID پترن ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
		}

		/*
		 * پترن OTP باید یک متغیر داشته باشد؛ مقدار ارسالی فقط خود کد است.
		 * این باعث می‌شود متن نهایی توسط الگوی تأییدشده‌ی پنل ساخته شود.
		 */
		$response = wp_remote_post(
			'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
				),
				'body'    => array(
					'username' => $settings['username'],
					'password' => $api_key,
					'to'       => '0' . $phone,
					'text'     => $code,
					'bodyId'   => $body_id,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'jluxe_sms_upstream',
				'ارتباط با وب‌سرویس ملی‌پیامک ناموفق بود: ' . $response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$body      = json_decode( $raw_body, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error(
				'jluxe_sms_upstream',
				'وب‌سرویس ملی‌پیامک پاسخ HTTP نامعتبر داد.',
				array( 'status' => 502 )
			);
		}

		/*
		 * BaseServiceNumber در کتابخانه‌های ملی‌پیامک/WP SMS پاسخ را معمولاً
		 * به‌صورت Value/StrRetStatus برمی‌گرداند. هر دو حالت JSON و متن خام
		 * را برای تشخیص خطا پوشش می‌دهیم، اما موفقیت را فقط وقتی اعلام می‌کنیم
		 * که Value عددیِ مثبت باشد.
		 */
		if ( is_array( $body ) && array_key_exists( 'Value', $body ) ) {
			$value = (int) $body['Value'];
			if ( $value <= 0 ) {
				$status_text = isset( $body['StrRetStatus'] ) ? sanitize_text_field( (string) $body['StrRetStatus'] ) : '';
				$message     = 'ارسال OTP توسط ملی‌پیامک ناموفق بود';
				if ( '' !== $status_text ) {
					$message .= ': ' . $status_text;
				}
				$message .= ' (کد: ' . $value . ')';

				return new WP_Error( 'jluxe_sms_upstream', $message, array( 'status' => 502 ) );
			}
			return true;
		}

		/*
		 * بعضی پاسخ‌های قدیمی ممکن است JSON نباشند. در این حالت فقط پاسخ
		 * کاملاً عددیِ مثبت را موفق در نظر می‌گیریم؛ پاسخ مبهم موفقیت تلقی
		 * نمی‌شود.
		 */
		$raw_trimmed = trim( (string) $raw_body );
		if ( ctype_digit( $raw_trimmed ) && (int) $raw_trimmed > 0 ) {
			return true;
		}

		return new WP_Error(
			'jluxe_sms_upstream',
			'پاسخ وب‌سرویس ملی‌پیامک قابل تشخیص نیست؛ ارسال به‌عنوان موفق ثبت نشد.',
			array( 'status' => 502 )
		);
	}

	return new WP_Error( 'jluxe_sms_unknown_provider', 'ارائه‌دهنده‌ی پیامک نامعتبر است.', array( 'status' => 500 ) );
}
