<?php
/**
 * فرم افزودن به سبد — محصول ساده. ساختار واقعی فرم ووکامرس حفظ شده
 * (input[name=quantity], name=add-to-cart) تا assets/js/frontend/add-to-cart.js
 * خودِ ووکامرس بدون تغییر کار کنه؛ فقط ظاهرش با استپر/دکمه‌ی سفارشی
 * (همون الگوی data-jluxe-qty-step که در سبد خرید هم استفاده شده) بازطراحی شده.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

$is_in_stock = $product->is_in_stock();
?>
<?php if ( ! $is_in_stock ) : ?>
	<p class="mt-3 rounded-xl bg-muted px-3 py-2.5 text-center text-small font-medium text-text-secondary">این محصول در حال حاضر ناموجود است</p>
<?php else : ?>
	<?php
	do_action( 'woocommerce_before_add_to_cart_form' );
	?>
	<form class="cart mt-3" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
		<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

		<?php
		$jluxe_qty_min   = apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product );
		$jluxe_qty_max   = apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product );
		$jluxe_qty_fixed = $jluxe_qty_min > 0 && $jluxe_qty_min === $jluxe_qty_max;
		?>
		<div class="flex items-center justify-between gap-2 rounded-xl bg-surface px-2 py-1.5">
			<span class="ps-1 text-[12px] text-text-muted">تعداد</span>
			<div class="quantity flex items-center gap-1">
				<?php if ( ! $jluxe_qty_fixed ) : ?>
					<button type="button" data-jluxe-qty-step="decrease" aria-label="کاهش تعداد" class="grid size-7 place-items-center rounded-lg text-text-secondary transition-all hover:bg-muted active:scale-90 active:bg-muted">
						<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
					</button>
				<?php endif; ?>
				<?php
				woocommerce_quantity_input(
					array(
						'min_value'   => $jluxe_qty_min,
						'max_value'   => $jluxe_qty_max,
						'input_value' => 1,
						'classes'     => array( 'qty', 'w-7', 'border-0', 'bg-transparent', 'p-0', 'text-center', 'text-[13px]', 'font-bold', 'tabular-nums', 'text-foreground', 'focus:outline-none', 'focus:ring-0' ),
						'jluxe_bare'  => true,
					)
				);
				?>
				<?php if ( ! $jluxe_qty_fixed ) : ?>
					<button type="button" data-jluxe-qty-step="increase" aria-label="افزایش تعداد" class="grid size-7 place-items-center rounded-lg text-text-secondary transition-all hover:bg-muted active:scale-90 active:bg-muted">
						<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>

		<?php
		/*
		 * کلاسِ single_add_to_cart_button قبلاً روی این دکمه نبود — فقط
		 * دکمه‌ی محصولِ متغیر (variation-add-to-cart-button.php) این کلاسِ
		 * استانداردِ خودِ ووکامرس رو داشت. تا الان مشکلی نداشت چون هیچ کدِ
		 * دیگه‌ای بهش وابسته نبود، ولی وقتی نوارِ چسبانِ موبایلِ صفحه‌ی
		 * محصول (content-single-product.php، دکمه‌ی data-jluxe-mobile-bar-add)
		 * ساخته شد، دقیقاً با همین سلکتور (form.cart .single_add_to_cart_button)
		 * دنبالِ دکمه‌ی واقعی می‌گرده — برای محصولِ ساده هیچ‌وقت پیدا نمی‌کرد،
		 * پس کلیک روش هیچ اتفاقی نمی‌افتاد (باگِ واقعیِ گزارش‌شده: «توی
		 * موبایل دکمه اصلاً کار نمی‌کنه»، فقط برای محصولِ ساده).
		 */
		?>
		<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button group/cta mt-3 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-[14px] font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.97]">
			<svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
			افزودن به سبد خرید
		</button>
	</form>
	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
<?php endif; ?>
