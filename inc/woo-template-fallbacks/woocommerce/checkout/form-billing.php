<?php
/**
 * فرم اطلاعات ارسال. برخلاف تلاش اولیه (nest کردن فیلدها داخل divهای
 * گرید جدا)، این‌جا فیلدها flat می‌مونن و فقط با priority مرتب می‌شن؛
 * چیدمانِ ردیفی (استان|شهر|کدپستی، موبایل|تلفن‌ثابت) با flex-basis روی
 * خودِ هر .form-row (بر اساس id، در globals.css) پیاده می‌شه.
 *
 * دلیل: assets/js/frontend/address-i18n.js خودِ ووکامرس روی هر
 * بارگذاری/تغییر کشور تمام .form-row های داخل این wrapper رو
 * detach().appendTo(wrapper) می‌کنه — یعنی هر تودرتوییِ دستی رو از بین
 * می‌بره. پس چیدمان باید روی سطح خودِ فیلدهای flat پیاده بشه، نه با
 * <div> اضافه دور گروه‌هایی از فیلدها.
 *
 * برای هر فیلد همچنان از woocommerce_form_field() استفاده می‌شه (نه HTML
 * دستی) — چون افزونه‌ی «ووکامرس فارسی» با id های واقعی این فیلدها
 * (billing_state/billing_city) کار می‌کنه.
 *
 * @see woocommerce/templates/checkout/form-billing.php (نسخه‌ی اصلی)
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

$fields = $checkout->get_checkout_fields( 'billing' );
uasort(
	$fields,
	function ( $a, $b ) {
		return ( $a['priority'] ?? 100 ) <=> ( $b['priority'] ?? 100 );
	}
);
?>
<div class="woocommerce-billing-fields">
	<h3 class="mb-4 text-h3 text-foreground">اطلاعات ارسال</h3>

	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="woocommerce-billing-fields__field-wrapper jluxe-billing-grid">
		<?php
		foreach ( $fields as $key => $field ) {
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
		?>
	</div>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<div class="woocommerce-account-fields mt-4">
		<?php if ( ! $checkout->is_registration_required() ) : ?>
			<label class="flex items-center gap-2 text-small text-text-secondary">
				<input class="input-checkbox size-4" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" />
				<span>ایجاد حساب کاربری؟</span>
			</label>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
			<div class="create-account mt-3 flex flex-col gap-4">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $account_field ) : ?>
					<?php woocommerce_form_field( $key, $account_field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
