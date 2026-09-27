<?php
/**
 * صفحه‌ی سبد خرید — بازطراحی‌شده مطابق ظاهر تأییدشده‌ی CartCheckout.tsx،
 * ولی کاملاً روی داده‌ی واقعی WC()->cart. تمام هوک‌ها/اکشن‌های اصلی قالب
 * ووکامرس حفظ شدن (سازگاری با افزونه‌ها/اسکریپت AJAX سبد).
 *
 * ترتیب DOM عمدیه: محصول (فرزند اول) → جمع قیمت (دوم) → تعداد (سوم). در
 * dir="rtl" با CSS Grid معمولی (نه row-reverse)، فرزند اول سمت راست
 * می‌شینه — یعنی دقیقاً «محصول → جمع قیمت → تعداد» از راست به چپ، بدون
 * جابه‌جایی دستی بعدی.
 *
 * @see woocommerce/templates/cart/cart.php (نسخه‌ی اصلی)
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

jluxe_render_checkout_stepper( 'cart' );
?>

<div class="mx-auto w-full max-w-[1296px] px-4 py-6">
	<h1 class="mb-6 text-h2 text-foreground">سبد خرید</h1>

	<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
		<div>
			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<div class="overflow-hidden rounded-2xl border border-border">
					<div class="hidden items-center gap-4 border-b border-border bg-muted/60 p-4 text-label text-text-secondary sm:grid sm:grid-cols-[1fr_8rem_8rem]">
						<span>محصول</span>
						<span class="text-center">جمع قیمت</span>
						<span class="text-center">تعداد</span>
					</div>

					<div class="divide-y divide-border">
						<?php do_action( 'woocommerce_before_cart_contents' ); ?>

						<?php
						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
							$visible    = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

							if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) ) {
								continue;
							}

							$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
							$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
							$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'thumbnail' ), $cart_item, $cart_item_key );

							if ( $_product->is_sold_individually() ) {
								$min_quantity = 1;
								$max_quantity = 1;
							} else {
								$min_quantity = 0;
								$max_quantity = $_product->get_max_purchase_quantity();
							}
							?>
							<div class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?> flex flex-col gap-4 p-4 sm:grid sm:grid-cols-[1fr_8rem_8rem] sm:items-center sm:gap-4">

								<div class="flex items-center gap-3" data-title="محصول">
									<?php
									echo apply_filters( // phpcs:ignore
										'woocommerce_cart_item_remove_link',
										sprintf(
											'<a role="button" href="%s" class="jluxe-remove-item flex size-8 shrink-0 items-center justify-center rounded-full text-text-muted transition-colors hover:bg-error/10 hover:text-error sm:order-last" aria-label="%s" data-product_id="%s" data-product_sku="%s"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg></a>',
											esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
											esc_attr( sprintf( 'حذف %s از سبد خرید', wp_strip_all_tags( $product_name ) ) ),
											esc_attr( $product_id ),
											esc_attr( $_product->get_sku() )
										),
										$cart_item_key
									);
									?>
									<a href="<?php echo esc_url( $product_permalink ); ?>" class="size-16 shrink-0 overflow-hidden rounded-xl border border-border">
										<?php echo $thumbnail; // phpcs:ignore ?>
									</a>
									<div class="min-w-0">
										<a href="<?php echo esc_url( $product_permalink ); ?>" class="line-clamp-2 text-small font-medium text-foreground hover:text-primary">
											<?php echo wp_kses_post( $product_name ); ?>
										</a>
										<div class="mt-1 text-caption text-text-muted"><?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore ?></div>
										<div class="mt-1 text-small font-medium text-foreground sm:hidden">
											<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
										</div>
									</div>
								</div>

								<div class="hidden text-center text-small font-medium text-foreground sm:block" data-title="جمع قیمت">
									<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
								</div>

								<div class="flex justify-start sm:justify-self-start" data-title="تعداد">
									<?php
									echo apply_filters( // phpcs:ignore
										'woocommerce_cart_item_quantity',
										woocommerce_quantity_input(
											array(
												'input_name'   => "cart[{$cart_item_key}][qty]",
												'input_value'  => $cart_item['quantity'],
												'max_value'    => $max_quantity,
												'min_value'    => $min_quantity,
												'product_name' => $product_name,
											),
											$_product,
											false
										),
										$cart_item_key,
										$cart_item
									);
									?>
								</div>
							</div>
							<?php
						}
						?>

						<?php do_action( 'woocommerce_cart_contents' ); ?>
					</div>
				</div>

				<div class="mt-4 flex flex-wrap items-center gap-3">
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="flex min-w-0 flex-1 items-center gap-2">
							<label for="coupon_code" class="screen-reader-text">کد تخفیف</label>
							<input type="text" name="coupon_code" id="coupon_code" value="" placeholder="کد تخفیف" class="h-11 min-w-0 flex-1 rounded-lg border border-border px-3 text-small text-foreground placeholder:text-text-muted" />
							<button type="submit" class="h-11 shrink-0 rounded-lg border border-border px-4 text-button text-foreground transition-colors hover:bg-muted" name="apply_coupon" value="اعمال تخفیف">اعمال تخفیف</button>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php endif; ?>

					<button type="submit" class="sr-only" name="update_cart" value="بروزرسانی سبد خرید">بروزرسانی سبد خرید</button>
					<?php do_action( 'woocommerce_cart_actions' ); ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</div>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>
		</div>

		<div class="flex h-fit flex-col gap-3 rounded-2xl border border-border p-5 lg:sticky lg:top-24">
			<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
			<?php
				/**
				 * @hooked woocommerce_cross_sell_display
				 * @hooked woocommerce_cart_totals - 10
				 */
				do_action( 'woocommerce_cart_collaterals' );
			?>
		</div>
	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
