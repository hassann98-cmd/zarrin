<?php
/**
 * ورود با پیامک (OTP) — تنظیمات ادمین + REST endpoints. دقیقاً همون الگوی
 * inc/theme-settings-ai.php: کلید API فقط سمت سرور، و تا provider/کلید واقعاً
 * ست نشده باشه، endpointها خطای صریح «پیکربندی نشده» برمی‌گردونن — هیچ
 * کد پیامکیِ فیک ارسال/تایید نمی‌شه.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_render_sms_page(): void {
	$status = null;

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

			if ( ! empty( $_POST['sms_api_key'] ) ) {
				jluxe_set_sms_api_key( sanitize_text_field( wp_unslash( $_POST['sms_api_key'] ) ) );
			}
			if ( ! empty( $_POST['sms_api_key_clear'] ) ) {
				jluxe_set_sms_api_key( '' );
			}
			$status = 'saved';
		}
	}

	$settings = jluxe_get_fresh_settings();
	$sms      = $settings['sms'];
	$has_key  = '' !== jluxe_get_sms_api_key();

	jluxe_settings_page_shell( 'ورود با پیامک (OTP)', 'jluxe-sms', $status, function () use ( $sms, $has_key ) {
		?>
		<p class="description">صفحه‌ی «ورود و عضویت» (my-account) همیشه با نام‌کاربری/ایمیل کار می‌کنه. تب «شماره موبایل» هم توی همون صفحه نمایش داده می‌شه، ولی تا وقتی این‌جا provider و کلید واقعی ست نکنی، غیرفعاله و پیام «سرویس پیامکی هنوز وصل نشده» نشون می‌ده — هیچ کدی به‌صورت فیک ارسال/تایید نمی‌شه.</p>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<h2>عمومی</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی ورود با پیامک</th>
					<td><label><input type="checkbox" name="sms[enabled]" value="1" <?php checked( $sms['enabled'] ); ?> /> نمایش تب «شماره موبایل» در صفحه‌ی ورود</label>
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
					<td><input type="text" id="jluxe-sms-username" name="sms[username]" value="<?php echo esc_attr( $sms['username'] ); ?>" class="regular-text" dir="ltr" placeholder="فقط برای ملی‌پیامک — نام کاربری پنل" />
						<p class="description">سرویس ارسال ملی‌پیامک (REST کلاسیک) به نام‌کاربری و رمز، هر دو، نیاز داره — رمز رو پایین به‌عنوان «کلید API» وارد کن.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-key">کلید API / رمز عبور</label></th>
					<td>
						<input type="password" id="jluxe-sms-key" name="sms_api_key" value="" class="regular-text" placeholder="<?php echo $has_key ? '•••••••••••••••• (تنظیم شده — برای تغییر، مقدار جدید بنویس)' : 'هنوز تنظیم نشده'; ?>" dir="ltr" autocomplete="off" />
						<p class="description">کاوه‌نگار: توکن API. ملی‌پیامک: مقدار <strong>APIKey جهت استفاده از وب‌سرویس</strong> در پنل ملی‌پیامک.</p>
						<?php if ( $has_key ) : ?>
							<label><input type="checkbox" name="sms_api_key_clear" value="1" onclick="return confirm('کلید API حذف بشه؟ ورود با پیامک تا تنظیم دوباره کار نمی‌کنه.');" /> حذف کلید فعلی</label>
						<?php endif; ?>
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
					'sanitize_callback' => 'sanitize_text_field',
				),
				'code'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_sms_rest_routes' );

/**
 * شماره‌ی موبایل رو به فرمت یکتای ۱۰ رقمیِ بدون صفر/کد کشور نرمال می‌کنه —
 * دقیقاً همون منطق نرمال‌سازی تلفن در inc/order-tracking.php، برای یکسان
 * موندن رفتار بین دو ویژگی.
 */
function jluxe_normalize_phone( string $phone ): string {
	$digits = preg_replace( '/\D/', '', $phone );
	$digits = preg_replace( '/^0098/', '', $digits );
	$digits = preg_replace( '/^98/', '', $digits );
	$digits = ltrim( $digits, '0' );
	return substr( $digits, -10 );
}

function jluxe_handle_otp_request( WP_REST_Request $request ) {
	$settings = jluxe_get_theme_settings()['sms'];
	$api_key  = jluxe_get_sms_api_key();

	if ( ! $settings['enabled'] || '' === $api_key || '' === $settings['provider'] ) {
		return new WP_Error( 'jluxe_sms_disabled', 'سرویس پیامکی هنوز به سایت وصل نشده — فعلاً از تب «نام کاربری» استفاده کن.', array( 'status' => 503 ) );
	}

	$phone = jluxe_normalize_phone( (string) $request->get_param( 'phone' ) );
	if ( 10 !== strlen( $phone ) || '9' !== $phone[0] ) {
		return new WP_Error( 'jluxe_sms_bad_phone', 'شماره موبایل معتبر نیست.', array( 'status' => 400 ) );
	}

	// rate limit: حداکثر ۳ درخواست کد در ۱۰ دقیقه برای هر شماره + هر IP.
	$ip        = jluxe_theme_get_client_ip();
	$rl_phone  = 'jluxe_otp_rl_p_' . md5( $phone );
	$rl_ip     = 'jluxe_otp_rl_i_' . md5( $ip );
	if ( (int) get_transient( $rl_phone ) >= 3 || (int) get_transient( $rl_ip ) >= 10 ) {
		return new WP_Error( 'jluxe_sms_rate_limited', 'تعداد درخواست‌ها زیاده — چند دقیقه دیگه دوباره تلاش کن.', array( 'status' => 429 ) );
	}

	$code = (string) wp_rand( 10000, 99999 );

	$sent = jluxe_send_otp_sms( $settings, $api_key, $phone, $code );
	if ( is_wp_error( $sent ) ) {
		return $sent;
	}

	set_transient( 'jluxe_otp_code_' . md5( $phone ), $code, 2 * MINUTE_IN_SECONDS );
	set_transient( $rl_phone, (int) get_transient( $rl_phone ) + 1, 10 * MINUTE_IN_SECONDS );
	set_transient( $rl_ip, (int) get_transient( $rl_ip ) + 1, 10 * MINUTE_IN_SECONDS );

	return array( 'sent' => true );
}

function jluxe_handle_otp_verify( WP_REST_Request $request ) {
	$settings = jluxe_get_theme_settings()['sms'];
	if ( ! $settings['enabled'] || '' === jluxe_get_sms_api_key() || '' === $settings['provider'] ) {
		return new WP_Error( 'jluxe_sms_disabled', 'سرویس پیامکی هنوز به سایت وصل نشده.', array( 'status' => 503 ) );
	}

	$phone = jluxe_normalize_phone( (string) $request->get_param( 'phone' ) );
	$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );

	$key      = 'jluxe_otp_code_' . md5( $phone );
	$expected = get_transient( $key );

	if ( false === $expected || ! hash_equals( (string) $expected, $code ) ) {
		return new WP_Error( 'jluxe_sms_bad_code', 'کد وارد شده اشتباه یا منقضی‌شده است.', array( 'status' => 400 ) );
	}

	delete_transient( $key );

	/*
	 * باگِ واقعیِ کشف‌شده حینِ ممیزیِ امنیتی (دقیقاً همون کلاسِ باگی که تویِ
	 * پوسته‌ی قبلی/Boom هم بود): این‌جا قبلاً فقط متای jluxe_phone چک می‌شد —
	 * متایی که فقط توسطِ خودِ همین تابع، فقط موقعِ ساختِ حسابِ جدیدِ OTP، ست
	 * می‌شه. یعنی مشتری‌ای که قبلاً با ایمیل/رمز ثبت‌نام کرده (inc/auth.php)
	 * و بعد در چک‌اوت شماره‌ی موبایلش رو به‌عنوانِ billing_phone (متای
	 * استانداردِ خودِ ووکامرس) ثبت کرده، اگه بعداً بخواد با همون شماره از
	 * تبِ «ورود با پیامک» وارد بشه، اصلاً پیدا نمی‌شد — یک حسابِ کاملاً جدید و
	 * جدا (با ایمیلِ ساختگی) براش ساخته می‌شد، بدونِ دسترسی به سفارش‌های
	 * قبلی‌اش (دقیقاً مشکلِ «مالکیتِ سفارش» که در order-pay هم می‌تونست خودش
	 * رو نشون بده). الان علاوه‌بر jluxe_phone، billing_phone هم (با چند
	 * فرمتِ محتملِ ذخیره‌شدن: با/بدونِ صفرِ ابتدایی، با/بدونِ کدِ کشور) چک
	 * می‌شه؛ اگه پیدا شد، jluxe_phone هم روی همون حسابِ موجود ست می‌شه تا
	 * دفعه‌ی بعد مستقیم از مسیرِ سریع‌تر پیدا بشه.
	 */
	$billing_phone_candidates = array_unique( array( $phone, '0' . $phone, '98' . $phone, '+98' . $phone, '0098' . $phone ) );

	$users = get_users( array(
		'meta_query' => array(
			'relation' => 'OR',
			array( 'key' => 'jluxe_phone', 'value' => $phone, 'compare' => '=' ),
			array( 'key' => 'billing_phone', 'value' => $billing_phone_candidates, 'compare' => 'IN' ),
		),
		'number' => 1,
		'fields' => 'ID',
	) );

	if ( ! empty( $users ) ) {
		$user_id = (int) $users[0];
		// بک‌فیل — اگه از مسیرِ billing_phone پیدا شده (نه jluxe_phone)، دفعه‌ی
		// بعد مستقیم از مسیرِ سریع‌تر پیدا بشه.
		if ( '' === (string) get_user_meta( $user_id, 'jluxe_phone', true ) ) {
			update_user_meta( $user_id, 'jluxe_phone', $phone );
		}
	} else {
		$username = 'user_' . $phone;
		$suffix   = 0;
		while ( username_exists( $suffix ? $username . $suffix : $username ) ) {
			$suffix++;
		}
		$final_username = $suffix ? $username . $suffix : $username;

		$user_id = wp_insert_user( array(
			'user_login' => $final_username,
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => $final_username . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'role'       => 'customer',
		) );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'jluxe_sms_user_create_failed', 'ساخت حساب کاربری ناموفق بود.', array( 'status' => 500 ) );
		}

		update_user_meta( $user_id, 'jluxe_phone', $phone );
		update_user_meta( $user_id, 'billing_phone', $phone );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	return array( 'success' => true );
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
		if ( $code_http < 200 || $code_http >= 300 ) {
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
