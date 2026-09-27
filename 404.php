<?php
/**
 * 404 حرفه‌ای — پاسخ واقعی 404 حفظ می‌شود؛ مسیر بازگشت روشن، جست‌وجو و
 * محصولاتِ پیشنهادی. همهٔ لینک‌ها ≥۴۸px هدف لمسی (استاندارد موبایل).
 */
get_header();
$jluxe_404_shop = function_exists( 'jluxe_shop_url' ) ? jluxe_shop_url() : home_url( '/' );
?>
<main id="primary" class="site-main mx-auto max-w-[1296px] px-4 py-10 md:py-16" dir="rtl">
	<section class="jluxe-404 rounded-2xl border border-border bg-surface p-6 md:p-12 text-center" aria-labelledby="jluxe-404-title">
		<div class="jluxe-404-badge mx-auto mb-5" aria-hidden="true">
			<?php
			if ( function_exists( 'jluxe_icon' ) ) {
				jluxe_icon( 'compass', 'size-10' );
			}
			?>
		</div>
		<p class="jluxe-404-code text-display" aria-hidden="true">۴۰۴</p>
		<h1 id="jluxe-404-title" class="text-h1 mt-1 mb-3">این صفحه پیدا نشد</h1>
		<p class="text-body text-text-secondary mx-auto mb-6 max-w-[520px]">
			آدرسی که باز کرده‌اید وجود ندارد یا جابه‌جا شده است. از دکمهٔ زیر به فروشگاه برگردید یا همان‌جا دنبال محصول بگردید.
		</p>

		<div class="jluxe-404-actions mx-auto mb-8 flex flex-wrap items-center justify-center gap-3">
			<a class="jluxe-btn jluxe-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php jluxe_icon( 'home', 'size-5' ); ?>بازگشت به سایت
			</a>
			<a class="jluxe-btn jluxe-btn-secondary" href="<?php echo esc_url( $jluxe_404_shop ); ?>">
				<?php jluxe_icon( 'cart', 'size-5' ); ?>رفتن به فروشگاه
			</a>
		</div>

		<div class="jluxe-404-search mx-auto max-w-[440px]">
			<?php get_search_form(); ?>
		</div>
	</section>

	<?php
	// محصولاتِ پیشنهادی — فقط فروشگاهِ واقعیِ فعال (بدون ادعای Woo اگر نیست).
	if ( function_exists( 'wc_get_products' ) ) :
		$jluxe_404_products = wc_get_products( array(
			'status'   => 'publish',
			'limit'    => 4,
			'orderby'  => 'date',
			'order'    => 'DESC',
		) );
		if ( ! empty( $jluxe_404_products ) ) :
			?>
			<section class="jluxe-404-products mt-10" aria-labelledby="jluxe-404-products-title">
				<h2 id="jluxe-404-products-title" class="text-h3 mb-4">شاید این‌ها به کارتان بیاید</h2>
				<div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
					<?php foreach ( $jluxe_404_products as $jluxe_404_product ) : ?>
						<?php
						$jluxe_404_id   = method_exists( $jluxe_404_product, 'get_id' ) ? $jluxe_404_product->get_id() : 0;
						$jluxe_404_link = $jluxe_404_id ? get_permalink( $jluxe_404_id ) : '';
						if ( ! $jluxe_404_link ) {
							continue;
						}
						?>
						<a class="jluxe-404-card group rounded-xl border border-border bg-surface p-3 text-center" href="<?php echo esc_url( $jluxe_404_link ); ?>">
							<?php echo wp_kses_post( $jluxe_404_product->get_image( 'woocommerce_thumbnail' ) ); ?>
							<span class="jluxe-404-card-title mt-2 block text-caption text-text-secondary"><?php echo esc_html( $jluxe_404_product->get_name() ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</main>
<?php
get_footer();
