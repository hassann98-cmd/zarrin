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
	$webotp_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

	jluxe_settings_page_shell( 'ورود با پیامک (OTP)', 'jluxe-sms', $status, function () use ( $sms, $has_key, $key_hint, $warnings, $webotp_host ) {
		?>
		<p class="description">در صفحهٔ «ورود و عضویت»، کدِ درستِ پیامکی هم ورود را انجام می‌دهد و هم برای شمارهٔ تازه یک حساب مشتریِ کمینه می‌سازد؛ اگر شماره در billing phone یک حساب موجود باشد، همان حساب پس از تأیید OTP به شماره پیوند می‌خورد. این مسیر مستقل از فعال‌بودن ثبت‌نامِ نام‌کاربری/رمز در WooCommerce است و نام/نشانی را می‌توان هنگام تسویه‌حساب گرفت. تب موبایل تا تنظیم provider و کلید واقعی غیرفعال می‌ماند و هیچ کدی به‌صورت فیک ارسال یا تأیید نمی‌شود. برای WebOTP در Chrome/Android، صفحه باید با HTTPS و روی همان میزبان (host)ی باز باشد که در پیامک می‌آید؛ تفاوتِ دامنه، زیردامنه یا www مهم است. متن نهایی پیامک باید در یک خط جداگانه، دقیقاً با این فرمت تمام شود و هیچ متنی پس از آن نیاید: <code dir="ltr">@<?php echo esc_html( $webotp_host ); ?> #123456</code> (عدد با کد واقعی جایگزین می‌شود؛ بدونِ https://، مسیر یا پورت). در حالت پترن، افزونه کد را به‌عنوان متغیر می‌فرستد و قالبِ تأییدشده متن نهایی را می‌سازد؛ در حالت خط اختصاصی، متن OTP و این خط پایانی را خود پوسته می‌سازد. پس از ارسال آزمایشی، متن واقعی پیامک را بررسی کنید؛ اگر خط پایانی در SMS نباشد، WebOTP کار نمی‌کند؛ ورود دستی همیشه ممکن است و نمایشِ پیشنهادِ سیستم‌عامل به دستگاه و قالبِ پیامک بستگی دارد.</p>
		<?php if ( 'melipayamak' === ( $sms['provider'] ?? '' ) && 'dedicated' === ( $sms['otp_mode'] ?? 'pattern' ) ) : ?>
			<p class="description">حالت خط اختصاصی از API ارسال عادی ملی‌پیامک استفاده می‌کند؛ شمارهٔ خط باید اجازهٔ ارسال عادی داشته باشد. متن OTP و footer میزبان به‌صورت خودکار ساخته می‌شود و Body ID پترن در این حالت استفاده نمی‌شود. نمونهٔ پیام خروجی:</p>
			<pre dir="auto" style="white-space:pre-wrap;max-width:760px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde">مشتری عزیز کد تأیید شما 123456 می‌باشد.

@<?php echo esc_html( $webotp_host ); ?> #123456</pre>
		<?php elseif ( 'melipayamak' === ( $sms['provider'] ?? '' ) ) : ?>
			<p class="description">حالت پترن، روش پیش‌فرض و فعلی است. برای ملی‌پیامک یک پترن جدید بسازید و برای تأیید ارسال کنید. پشتیبانی ملی‌پیامک تأیید کرده همان متغیر <code>{0}</code> می‌تواند دو بار در پترن بیاید؛ این افزونه هم کد را فقط یک بار می‌فرستد، پس نیازی به تغییر وب‌سرویس یا ارسال دو مقدار نیست. پس از تأیید، Body ID جدید را در فیلد «کد متن / Body ID پترن» وارد کنید. نمونهٔ متن پترن:</p>
			<pre dir="auto" style="white-space:pre-wrap;max-width:760px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde">مشتری عزیز کد تأیید شما {0} می‌باشد.

@<?php echo esc_html( $webotp_host ); ?> #{0}</pre>
		<?php endif; ?>
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
						<p class="description">در این حالت تبِ «نام کاربری» از صفحهٔ ورود حذف می‌شود. با تأیید کد، حساب مشتریِ دارای همین billing phone خودکار پیوند می‌خورد یا ـ اگر حسابی نباشد ـ حساب کمینه با همان شماره ساخته می‌شود؛ این ثبت‌نام پیامکی مستقل از تنظیم ثبت‌نام عادی WooCommerce است و اطلاعات خرید در تسویه‌حساب گرفته می‌شود. مدیران همیشه از <code>wp-login.php</code> وارد می‌شوند و این مسیر باز می‌ماند.</p>
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
					<th scope="row"><label for="jluxe-sms-otp-mode">روش ارسال OTP در ملی‌پیامک</label></th>
					<td>
						<select id="jluxe-sms-otp-mode" name="sms[otp_mode]">
							<option value="pattern" <?php selected( $sms['otp_mode'] ?? 'pattern', 'pattern' ); ?>>پترن خدماتی / Body ID (پیش‌فرض و روش فعلی)</option>
							<option value="dedicated" <?php selected( $sms['otp_mode'] ?? 'pattern', 'dedicated' ); ?>>خط اختصاصی / متن آزاد</option>
						</select>
						<p class="description">این انتخاب فقط برای ملی‌پیامک است؛ کاوه‌نگار همچنان از Verify Lookup خودش استفاده می‌کند. در حالت خط اختصاصی، شمارهٔ خطِ تنظیم‌شده در ادامه باید مجاز به ارسال عادی از وب‌سرویس باشد.</p>
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
					<td>
						<input type="text" id="jluxe-sms-sender" name="sms[sender]" value="<?php echo esc_attr( $sms['sender'] ); ?>" class="regular-text" dir="ltr" placeholder="فقط برای ملی‌پیامک — شماره خط اختصاصی" />
						<p class="description">در حالت خط اختصاصی OTP، این شماره در پارامتر <code>from</code> فرستاده می‌شود؛ در حالت پترن استفاده نمی‌شود.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-template">نام الگو (Template)</label></th>
					<td><input type="text" id="jluxe-sms-template" name="sms[template]" value="<?php echo esc_attr( $sms['template'] ); ?>" class="regular-text" dir="ltr" placeholder="فقط برای کاوه‌نگار — نام الگوی Verify Lookup در پنل" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-sms-body-id">کد متن / Body ID پترن</label></th>
					<td>
						<input type="text" id="jluxe-sms-body-id" name="sms[body_id]" value="<?php echo esc_attr( $sms['body_id'] ?? '' ); ?>" class="regular-text" dir="ltr" inputmode="numeric" placeholder="مثلاً 532701" />
						<p class="description">برای OTP ملی‌پیامک فقط در حالت پترن استفاده می‌شود؛ در حالت خط اختصاصی نادیده گرفته می‌شود. مقدار از پنل شما خوانده می‌شود و هاردکد نشده است.</p>
					</td>
				</tr>
			</table>

			<h2>اعلانِ موجودشدن محصول</h2>
			<p class="description">این بخش از ورود پیامکی جداست. درخواست‌ها با شمارهٔ رمزگذاری‌شده ذخیره می‌شوند؛ متن/پترن انتخابی برای ارسال خودکارِ هنگام موجودشدن و ارسال دستی استفاده می‌شود. ارسال دستی همان لحظه و مستقل است و پیام خودکارِ بعدی را لغو نمی‌کند؛ اگر محصول هنوز ناموجود است، متن را متناسب با آن بنویسید. از خط خدماتی اشتراکی فقط در چارچوب مجوزها و الگوهای پنل ملی‌پیامک استفاده کنید.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی اعلان موجودی</th>
					<td><label><input type="checkbox" name="sms[stock_alert_enabled]" value="1" <?php checked( ! empty( $sms['stock_alert_enabled'] ) ); ?> /> پذیرش درخواست و ارسال خودکار پیامک هنگام موجودشدن</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-stock-alert-mode">روش ارسال ملی‌پیامک</label></th>
					<td>
						<select id="jluxe-stock-alert-mode" name="sms[stock_alert_mode]">
							<option value="pattern" <?php selected( $sms['stock_alert_mode'] ?? 'pattern', 'pattern' ); ?>>پترن خدماتیِ تأییدشده (Body ID)</option>
							<option value="free_text" <?php selected( $sms['stock_alert_mode'] ?? 'pattern', 'free_text' ); ?>>متن آزاد از خط ارسالِ تنظیم‌شده</option>
						</select>
						<p class="description">ارسال متن آزاد فقط برای ملی‌پیامک و در صورتی کار می‌کند که خط و حساب شما اجازهٔ ارسال عادی از وب‌سرویس را داشته باشند. اگر خط اشتراکی شما فقط پترن می‌پذیرد، «پترن خدماتی» را انتخاب کنید.</p>
						<?php if ( 'free_text' === ( $sms['stock_alert_mode'] ?? 'pattern' ) && 'melipayamak' !== ( $sms['provider'] ?? '' ) ) : ?>
							<p class="description" style="color:#b32d2e">متن آزاد اعلان موجودی فقط با ملی‌پیامک پشتیبانی می‌شود؛ کاوه‌نگار به الگوی Verify Lookup نیاز دارد.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-stock-alert-template">الگوی کاوه‌نگار</label></th>
					<td><input type="text" id="jluxe-stock-alert-template" name="sms[stock_alert_template]" value="<?php echo esc_attr( $sms['stock_alert_template'] ?? '' ); ?>" class="regular-text" dir="ltr" placeholder="نام الگوی Verify Lookup" /><p class="description">کاوه‌نگار همیشه از الگوی تأییدشده با نام محصول استفاده می‌کند.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-stock-alert-body-id">Body ID ملی‌پیامک</label></th>
					<td><input type="text" id="jluxe-stock-alert-body-id" name="sms[stock_alert_body_id]" value="<?php echo esc_attr( $sms['stock_alert_body_id'] ?? '' ); ?>" class="regular-text" dir="ltr" inputmode="numeric" placeholder="شناسهٔ پترن خدماتی" /><p class="description">برای حالت پترن: یک متغیرِ نام محصول در متن تأییدشده تعریف کنید.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-stock-alert-message">متن پیامک سفارشی</label></th>
					<td>
						<textarea id="jluxe-stock-alert-message" name="sms[stock_alert_message]" rows="6" maxlength="1500" class="large-text code" dir="auto" placeholder="مثال: سلام؛ {product_name} دوباره موجود شد: {product_url}"><?php echo esc_textarea( $sms['stock_alert_message'] ?? '' ); ?></textarea>
						<p class="description">در حالت «متن آزاد»، متن فارسی، فهرست، خط جدید و شکلک قابل استفاده است. حداکثر ۱۵۰۰ نویسه. متغیرهای این بخش: <code>{mobile}</code>، <code>{phone}</code>، <code>{customer_mobile}</code>، <code>{site_name}</code>، <code>{site_url}</code>، <code>{product_name}</code>، <code>{product_url}</code>، <code>{post_id}</code>، <code>{stock_qty}</code>. برای شمارهٔ دلخواه، متغیرهای محصول فقط با واردکردن شناسهٔ محصول پر می‌شوند؛ متغیرهای سفارش/سبد خرید در این سناریو پشتیبانی نمی‌شوند.</p>
						<p class="description">متن آزاد از وب‌سرویس ارسال عادی ملی‌پیامک و «شماره خط ارسال» بالاتر استفاده می‌کند؛ هزینه/محدودیت و مجازبودن ارسال را در پنل خود بررسی کنید. تنظیم OTP و Body ID این حالت را فعال نمی‌کند.</p>
						<?php if ( 'free_text' === ( $sms['stock_alert_mode'] ?? 'pattern' ) && ! jluxe_stock_alert_sms_message_configuration_valid( (string) ( $sms['stock_alert_message'] ?? '' ) ) ) : ?>
							<p class="description" style="color:#b32d2e">متن خالی، بیش از ۱۵۰۰ نویسه، یا دارای متغیر خارج از فهرست بالا است؛ تا اصلاح متن، ثبت/ارسال اعلان فعال نمی‌شود.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php jluxe_settings_submit_button( true, 'sms' ); ?>
		</form>
		<?php jluxe_render_stock_alert_admin_management(); ?>
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
					'minLength'         => 1,
					'maxLength'         => 64,
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
					'minLength'         => 1,
					'maxLength'         => 64,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'code'  => array(
					'required'          => true,
					'type'              => 'string',
					'minLength'         => 1,
					'maxLength'         => 64,
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
			'phone' => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'sanitize_text_field' ),
			'code' => array( 'required' => true, 'type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'sanitize_text_field' ),
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
	$phone_raw = $request->get_param( 'phone' );
	$phone = is_string( $phone_raw ) && jluxe_strlen( $phone_raw ) <= 64 ? jluxe_normalize_phone( $phone_raw ) : '';
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
	$phone_raw = $request->get_param( 'phone' );
	$code_raw  = $request->get_param( 'code' );
	$phone = is_string( $phone_raw ) && jluxe_strlen( $phone_raw ) <= 64 ? jluxe_normalize_phone( $phone_raw ) : '';
	$code  = is_string( $code_raw ) && jluxe_strlen( $code_raw ) <= 64 ? trim( jluxe_ascii_digits( $code_raw ) ) : '';
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
			// Billing metadata alone is not proof. A successful OTP is; use it to
			// link exactly one eligible account without asking for its password.
			$candidates = array( $phone, '0' . $phone, '98' . $phone, '+98' . $phone, '0098' . $phone );
			foreach ( array( '۰۱۲۳۴۵۶۷۸۹', '٠١٢٣٤٥٦٧٨٩' ) as $digits ) {
				$map = array_combine( str_split( '0123456789' ), preg_split( '//u', $digits, -1, PREG_SPLIT_NO_EMPTY ) );
				foreach ( array_slice( $candidates, 0, 5 ) as $candidate ) {
					$candidates[] = strtr( $candidate, $map );
				}
			}
			$billing_accounts = get_users( array( 'meta_query' => array( array( 'key' => 'billing_phone', 'value' => $candidates, 'compare' => 'IN' ) ), 'number' => 2, 'fields' => 'ID' ) );
			if ( count( $billing_accounts ) > 1 ) {
				return new WP_Error( 'jluxe_sms_ambiguous_phone', 'این شماره برای چند حساب ثبت شده و اتصال خودکار امن نیست؛ با پشتیبانی تماس بگیرید.', array( 'status' => 409 ) );
			}
			if ( $billing_accounts ) {
				$user_id = (int) $billing_accounts[0];
				$billing_user = get_userdata( $user_id );
				if ( ! jluxe_otp_user_allowed( $billing_user ) ) {
					return new WP_Error( 'jluxe_sms_account_restricted', 'برای این حساب از ورود با رمز استفاده کنید.', array( 'status' => 403 ) );
				}
				$linked_phone = trim( (string) get_user_meta( $user_id, 'jluxe_phone', true ) );
				if ( '' !== $linked_phone && jluxe_normalize_phone( $linked_phone ) !== $phone ) {
					return new WP_Error( 'jluxe_sms_ambiguous_phone', 'این شماره از قبل به حساب دیگری متصل است؛ با پشتیبانی تماس بگیرید.', array( 'status' => 409 ) );
				}
				if ( $linked_phone !== $phone ) {
					update_user_meta( $user_id, 'jluxe_phone', $phone );
					if ( (string) get_user_meta( $user_id, 'jluxe_phone', true ) !== $phone ) {
						return new WP_Error( 'jluxe_sms_storage', 'ذخیرهٔ شماره انجام نشد؛ دوباره تلاش کنید.', array( 'status' => 503 ) );
					}
				}
			} else {
				// SMS-verified signup is intentionally independent of the ordinary
				// WooCommerce username/password registration setting. The verified
				// phone is the only initial identity; checkout can collect customer data.
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
		 * حالت پیش‌فرض pattern از BaseServiceNumber و Body ID فعلی استفاده
		 * می‌کند؛ حالت اختیاری dedicated از API متن آزاد SendSMS و خط تنظیم‌شده.
		 * در هر دو حالت کد OTP یکی است و منطق احراز هویت/چالش اینجا تغییر نمی‌کند.
		 */
		if ( '' === trim( (string) ( $settings['username'] ?? '' ) ) ) {
			return new WP_Error( 'jluxe_sms_missing_username', 'نام کاربریِ ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
		}

		$is_dedicated = 'dedicated' === ( $settings['otp_mode'] ?? 'pattern' );
		$endpoint     = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
		$request_body = array(
			'username' => $settings['username'],
			'password' => $api_key,
			'to'       => '0' . $phone,
		);

		if ( $is_dedicated ) {
			$sender = trim( (string) ( $settings['sender'] ?? '' ) );
			if ( '' === $sender ) {
				return new WP_Error( 'jluxe_sms_missing_sender', 'شمارهٔ خط اختصاصی ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
			}

			$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
			if ( '' === $host ) {
				return new WP_Error( 'jluxe_sms_missing_host', 'میزبان سایت برای ساخت پیامک WebOTP پیدا نشد.', array( 'status' => 500 ) );
			}

			$endpoint              = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
			$request_body['from']  = $sender;
			$request_body['text']  = 'مشتری عزیز کد تأیید شما ' . $code . " می‌باشد.\n\n@" . $host . ' #' . $code;
		} else {
			$body_id = isset( $settings['body_id'] ) ? absint( $settings['body_id'] ) : 0;
			if ( ! $body_id ) {
				return new WP_Error( 'jluxe_sms_missing_body_id', 'کد متن / Body ID پترن ملی‌پیامک تنظیم نشده است.', array( 'status' => 500 ) );
			}

			/* در حالت پترن، مقدار ارسالی فقط خود کد است تا قالب تأییدشده بسازد. */
			$request_body['text']   = $code;
			$request_body['bodyId'] = $body_id;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
				),
				'body'    => $request_body,
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
		 * REST ملی‌پیامک معمولاً پاسخ را به‌صورت Value/StrRetStatus برمی‌گرداند.
		 * هر دو حالت JSON و متن خام را برای تشخیص خطا پوشش می‌دهیم، اما موفقیت
		 * را فقط وقتی اعلام می‌کنیم که Value عددیِ مثبت باشد.
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
