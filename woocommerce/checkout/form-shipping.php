<?php
/**
 * بخش «ارسال به آدرس متفاوت» عمداً حذف شده (طبق طراحی تأییدشده فقط یک
 * آدرس وجود داره — همون billing؛ همچنین با فیلتر
 * woocommerce_ship_to_different_address_checkbox_enabled در
 * inc/woocommerce.php غیرفعال شده). این فایل فقط «توضیحات سفارش» رو
 * نگه می‌داره.
 *
 * @see woocommerce/templates/checkout/form-shipping.php (نسخه‌ی اصلی)
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-additional-fields mt-4">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>
		<div class="woocommerce-additional-fields__field-wrapper">
			<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
