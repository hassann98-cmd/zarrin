<?php
/**
 * جدول مرور سفارش — داخل sidebar چک‌اوت (id="order_review" که در
 * form-checkout.php ساخته شده). خلاصه‌ی اقلام + روش ارسال + جمع کل، همه
 * از WC()->cart واقعی.
 *
 * @see woocommerce/templates/checkout/review-order.php (نسخه‌ی اصلی)
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="flex flex-col gap-3">
	<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>

	<div class="flex flex-col gap-3 border-b border-border pb-3">
		<?php
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$visible  = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

			if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) ) {
				continue;
			}
			?>
			<div class="flex items-start justify-between gap-3 text-small">
				<span class="min-w-0 text-text-secondary">
					<span class="line-clamp-1 text-foreground"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
					<span class="text-caption text-text-muted">
						<?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', sprintf( '× %s', jluxe_fa_digits( $cart_item['quantity'] ) ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
					</span>
				</span>
				<span class="shrink-0 font-medium text-foreground">
					<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
				</span>
			</div>
			<?php
		}
		?>
	</div>

	<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>

	<?php
	/*
	 * طبقِ درخواستِ صریحِ کاربر («این هم جابجاست») همه‌ی ردیف‌های
	 * برچسب:مقدارِ این جمع (نه فقط ردیفِ نهایی) به همون ترتیبِ
	 * برچسب-اول/مقدار-دوم تغییر کردن، برای یکدستی — توی dir="rtl" یعنی
	 * برچسب سمتِ راست، مقدار سمتِ چپ.
	 */
	?>
	<div class="flex items-center justify-between text-small text-text-secondary">
		<span>قیمت کالاها (<?php echo esc_html( jluxe_fa_digits( WC()->cart->get_cart_contents_count() ) ); ?>):</span>
		<span><?php wc_cart_totals_subtotal_html(); ?></span>
	</div>

	<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
		<div class="flex items-center justify-between text-small text-success">
			<span><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
			<span>-<?php wc_cart_totals_coupon_html( $coupon ); ?></span>
		</div>
	<?php endforeach; ?>

	<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
		<?php
		/*
		 * id ثابت — assets/js/woocommerce.js موقعِ رفتن به مرحله‌ی «نحوه
		 * پرداخت» این بخش رو مخفی می‌کنه (طبق درخواست کاربر، مرحله‌ی
		 * پرداخت باید فقط پرداخت رو نشون بده، نه روش ارسال رو هم کنارش).
		 */
		?>
		<div id="jluxe-shipping-section" class="border-t border-border pt-3">
			<p class="mb-3 text-small font-medium text-foreground">روش ارسال را انتخاب کنید</p>
			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
			<?php wc_cart_totals_shipping_html(); ?>
			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
			<p data-jluxe-shipping-error hidden class="mt-2 text-caption text-error">روش ارسال را انتخاب کنید.</p>
		</div>
	<?php endif; ?>

	<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
		<div class="flex items-center justify-between text-small text-text-secondary">
			<span><?php echo esc_html( $fee->name ); ?></span>
			<span><?php wc_cart_totals_fee_html( $fee ); ?></span>
		</div>
	<?php endforeach; ?>

	<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
		<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
			<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
				<div class="flex items-center justify-between text-small text-text-secondary">
					<span><?php echo esc_html( $tax->label ); ?></span>
					<span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="flex items-center justify-between text-small text-text-secondary">
				<span><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
				<span><?php wc_cart_totals_taxes_total_html(); ?></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

	<?php
	/*
	 * طبقِ درخواستِ صریحِ کاربر («مبلغ و قیمتِ قابل‌پرداخت باید جابجا شه»)
	 * برخلافِ بقیه‌ی ردیف‌های جمع (که ترتیبشون مقدار-اول/برچسب-دوم هست)،
	 * این ردیفِ نهایی (پررنگ‌ترین/مهم‌ترینِ کل جمع) برچسب-اول/مقدار-دوم
	 * شد — یعنی توی dir="rtl" برچسب سمتِ راست و مقدار سمتِ چپ می‌شینه.
	 */
	?>
	<div class="flex items-center justify-between border-t border-border pt-3 text-body font-bold text-foreground">
		<span>مبلغ قابل پرداخت:</span>
		<span><?php wc_cart_totals_order_total_html(); ?></span>
	</div>

	<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
</div>
