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

<div class="mx-auto w-full max-w-[600px] px-4 py-16 text-center">
	<div class="flex flex-col items-center gap-4 rounded-2xl border border-border py-16">
		<svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" class="text-text-muted" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
		<h1 class="text-h2 text-foreground">سبد خرید شما خالی است</h1>
		<div class="text-body text-text-secondary">
			<?php do_action( 'woocommerce_cart_is_empty' ); ?>
		</div>
		<?php if ( wc_get_page_id( 'shop' ) > 0 ) : ?>
			<a class="mt-2 flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-button text-primary-foreground transition-colors hover:bg-primary-hover" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', jluxe_shop_url() ) ); ?>">
				بازگشت به فروشگاه
			</a>
		<?php endif; ?>
	</div>
</div>
