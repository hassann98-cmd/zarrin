<?php
/**
 * سبد خالی — با پیام واقعی ووکامرس (wc_empty_cart_message) به‌جای رشته‌ی
 * ثابت، چون افزونه‌ها می‌تونن این پیام رو فیلتر کنن.
 *
 * @see woocommerce/templates/cart/cart-empty.php (نسخه‌ی اصلی)
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

jluxe_render_checkout_stepper( 'cart' );
?>

<div class="mx-auto w-full max-w-[600px] px-4 py-16 text-center" data-jluxe-cart-page-content>
	<div class="flex flex-col items-center gap-4 rounded-2xl border border-border py-16">
		<svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" class="text-text-muted" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
		<?php $empty_title = jluxe_cart_checkout_copy( 'cart_empty_title', 'سبد خرید شما خالی است' ); ?>
		<?php if ( '' !== trim( $empty_title ) ) : ?>
			<h1 class="text-h2 text-foreground"><?php echo esc_html( $empty_title ); ?></h1>
		<?php endif; ?>
		<div class="text-body text-text-secondary">
			<?php do_action( 'woocommerce_cart_is_empty' ); ?>
		</div>
		<?php if ( wc_get_page_id( 'shop' ) > 0 && jluxe_cart_checkout_option( 'cart_empty_return_enabled', true ) && '' !== trim( jluxe_cart_checkout_copy( 'cart_empty_return', 'بازگشت به فروشگاه' ) ) ) : ?>
			<a class="mt-2 jluxe-checkout-action-button jluxe-checkout-action-button--primary" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', jluxe_shop_url() ) ); ?>">
				<?php echo esc_html( jluxe_cart_checkout_required_copy( 'cart_empty_return', 'بازگشت به فروشگاه' ) ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
