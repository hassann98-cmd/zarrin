<?php
/**
 * فیلدهای «کلید مخفی» پنل (کلید API دستیار هوش مصنوعی، کلید/رمز پیامک).
 *
 * R90 — مشکلِ گزارش‌شده: «هر بار یک تنظیمِ AI را عوض می‌کنم انگار کلید
 * پاک می‌شه؛ باید تیکِ حذف بزنم، ذخیره، کلید رو دوباره بذارم، ذخیره».
 * منطقِ ذخیرهٔ سرور درست بود (فیلدِ خالی = «تغییر نده»)؛ مشکل این بود که
 * فیلد یک <input type="password"> همیشه-فعال بود و مرورگر/مدیرِ رمز
 * (Chrome، Firefox، Samsung Pass، Bitwarden، LastPass…) آن را «فرمِ ورود»
 * می‌دید: autocomplete="off" روی فیلدِ رمز عمداً نادیده گرفته می‌شه، پس
 * رمزِ ورودِ وردپرس (که برای همین دامنه ذخیره شده) بی‌صدا توی «کلید API»
 * پُر می‌شد و با هر «ذخیره تغییرات» جای کلیدِ واقعی می‌نشست.
 *
 * راه‌حل در دو لایه:
 *  ۱) رندر: وقتی کلید ذخیره شده، فیلدِ ورودی اصلاً وجود ندارد که پُر شود —
 *     فقط «✓ ذخیره شده (…۴ کاراکترِ آخر)» + دکمهٔ «تغییر کلید». فیلد تا
 *     کلیکِ آن دکمه disabled و مخفی است (فیلدِ disabled نه autofill می‌شه
 *     نه ارسال). وقتی هم فعال است autocomplete="new-password" + readonly
 *     تا فوکوس + data-*ignore مدیرانِ رمز دارد.
 *  ۲) سرور: اگر باز هم مقدارِ ارسالی دقیقاً رمزِ ورودِ همین کاربرِ ادمین
 *     باشد (wp_check_password)، ذخیره نمی‌شود، کلیدِ قبلی می‌ماند و هشدار
 *     نمایش داده می‌شود.
 */

defined( 'ABSPATH' ) || exit;

/**
 * مقدارِ ارسالیِ یک فیلدِ کلید را تفسیر می‌کند (بدون نوشتن در دیتابیس).
 *
 * @return array{action:string,value:string} action یکی از:
 *   'keep' (چیزی تایپ نشده/همان کلیدِ فعلی)، 'set'، 'clear'،
 *   'rejected_login_password' (مرورگر رمزِ ورودِ وردپرس را پُر کرده بود).
 */
function jluxe_read_posted_secret( string $field, string $current, bool $strip_spaces = true ): array {
	if ( ! empty( $_POST[ $field . '_clear' ] ) ) {
		return array( 'action' => 'clear', 'value' => '' );
	}
	$raw = isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '';
	$value = sanitize_text_field( $raw );
	if ( $strip_spaces ) {
		// کلیدهای API هیچ‌وقت فاصله ندارند؛ کپی از پنلِ سرویس اغلب یک فاصلهٔ
		// اضافه (حتی وسطِ کلید، هنگامِ کپیِ دوتکه) با خودش میاره.
		$value = preg_replace( '/\s+/u', '', $value );
		$value = is_string( $value ) ? $value : '';
	}
	if ( '' === $value || $value === $current ) {
		return array( 'action' => 'keep', 'value' => $current );
	}
	if ( jluxe_secret_is_current_login_password( $value ) ) {
		return array( 'action' => 'rejected_login_password', 'value' => $current );
	}
	return array( 'action' => 'set', 'value' => $value );
}

/** آیا این رشته همان رمزِ ورودِ وردپرسِ کاربرِ فعلی است؟ (نشانهٔ autofill) */
function jluxe_secret_is_current_login_password( string $value ): bool {
	if ( '' === $value || ! function_exists( 'wp_get_current_user' ) || ! function_exists( 'wp_check_password' ) ) {
		return false;
	}
	$user = wp_get_current_user();
	if ( ! $user || empty( $user->ID ) || empty( $user->user_pass ) ) {
		return false;
	}
	return (bool) wp_check_password( $value, (string) $user->user_pass, (int) $user->ID );
}

/**
 * نتیجهٔ jluxe_read_posted_secret را با setter اعمال می‌کند و در صورتِ
 * لزوم متنِ هشدار برمی‌گرداند ('' یعنی بدونِ هشدار).
 */
function jluxe_apply_posted_secret( string $field, string $current, callable $setter, string $label, bool $strip_spaces = true ): string {
	$result = jluxe_read_posted_secret( $field, $current, $strip_spaces );
	if ( 'set' === $result['action'] || 'clear' === $result['action'] ) {
		call_user_func( $setter, $result['value'] );
	}
	if ( 'rejected_login_password' === $result['action'] ) {
		return sprintf( 'مقدارِ فیلدِ «%s» همان رمزِ ورودِ وردپرسِ شما بود — تقریباً همیشه یعنی مرورگر/مدیرِ رمز آن را خودکار پُر کرده. ذخیره نشد و کلیدِ قبلی دست‌نخورده ماند. (اگر عمداً همین رمز را برای آن سرویس هم گذاشته‌اید، رمزِ سرویس را عوض کنید؛ رمزِ مشترک ناامن است.)', $label );
	}
	return '';
}

/** «…abcd» برای نمایشِ اینکه کدام کلید ذخیره است (بدونِ افشای کلید). */
function jluxe_secret_hint( string $secret ): string {
	if ( jluxe_strlen( $secret ) < 12 ) {
		return '';
	}
	return '…' . jluxe_substr( $secret, -4 );
}

/**
 * رندرِ فیلدِ کلید. $args: id, name, has_key, hint, confirm_clear, placeholder.
 */
function jluxe_render_secret_field( array $args ): void {
	$id      = (string) ( $args['id'] ?? '' );
	$name    = (string) ( $args['name'] ?? '' );
	$has_key = ! empty( $args['has_key'] );
	$hint    = (string) ( $args['hint'] ?? '' );
	$confirm = (string) ( $args['confirm_clear'] ?? 'کلید حذف بشه؟' );
	$ph      = (string) ( $args['placeholder'] ?? 'کلید را این‌جا بچسبان' );
	// ویژگی‌هایی که Chrome/Firefox/Safari و مدیرانِ رمزِ رایج را از پُرکردنِ
	// خودکارِ این فیلد با رمزِ ورودِ سایت بازمی‌دارند.
	$guard = 'autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" readonly onfocus="this.removeAttribute(\'readonly\')"';
	?>
	<div class="jluxe-secret" data-jluxe-secret>
		<?php if ( $has_key ) : ?>
			<p class="jluxe-secret__status">
				<span class="jluxe-secret__saved">✓ کلید ذخیره شده<?php echo '' !== $hint ? ' — <code dir="ltr">' . esc_html( $hint ) . '</code>' : ''; ?></span>
				<button type="button" class="button" data-jluxe-secret-edit aria-controls="<?php echo esc_attr( $id ); ?>-box" aria-expanded="false">تغییر کلید</button>
			</p>
			<div class="jluxe-secret__box" id="<?php echo esc_attr( $id ); ?>-box" hidden>
				<input type="password" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="" class="regular-text" dir="ltr" placeholder="کلیدِ جدید را بچسبان" disabled <?php echo $guard; // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت. ?> />
				<button type="button" class="button-link" data-jluxe-secret-cancel>انصراف</button>
			</div>
			<p class="description">تا روی «تغییر کلید» نزنی، ذخیرهٔ بقیهٔ تنظیمات به کلید دست نمی‌زند.</p>
			<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>_clear" value="1" onclick="return !this.checked || confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);" /> حذف کلید فعلی</label>
		<?php else : ?>
			<input type="password" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="" class="regular-text" dir="ltr" placeholder="<?php echo esc_attr( $ph ); ?>" <?php echo $guard; // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت. ?> />
			<p class="description">هنوز تنظیم نشده.</p>
		<?php endif; ?>
	</div>
	<?php
	static $script_printed = false;
	if ( $has_key && ! $script_printed ) {
		$script_printed = true;
		?>
		<script>
		document.addEventListener('click',function(e){var t=e.target;if(!t||!t.closest)return;var edit=t.closest('[data-jluxe-secret-edit]'),cancel=t.closest('[data-jluxe-secret-cancel]');if(!edit&&!cancel)return;var w=t.closest('[data-jluxe-secret]');if(!w)return;var box=w.querySelector('.jluxe-secret__box'),input=box&&box.querySelector('input'),btn=w.querySelector('[data-jluxe-secret-edit]');if(!box||!input)return;e.preventDefault();if(edit){box.hidden=false;input.disabled=false;input.value='';btn.setAttribute('aria-expanded','true');input.focus();}else{input.value='';input.disabled=true;box.hidden=true;btn.setAttribute('aria-expanded','false');btn.focus();}});
		</script>
		<?php
	}
}

/** اعلانِ هشدارهای ذخیره (مثلاً رد شدنِ رمزِ autofill‌شده) بالای فرم. */
function jluxe_render_secret_warnings( array $warnings ): void {
	foreach ( array_filter( $warnings ) as $warning ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( (string) $warning ) . '</p></div>';
	}
}
