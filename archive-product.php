<?php
/**
 * WooCommerce product archive/shop page skeleton.
 * Uses WooCommerce's own hook system (woocommerce_content) instead of copying
 * every core template file — standard, low-maintenance override pattern.
 */

get_header();
global $wp_rewrite;
$jluxe_pagination_base = isset( $wp_rewrite->pagination_base ) ? (string) $wp_rewrite->pagination_base : 'page';
?>

<main id="primary" class="site-main" data-jluxe-soft-nav="catalog" data-jluxe-pagination-base="<?php echo esc_attr( $jluxe_pagination_base ); ?>">
	<div class="mx-auto w-full max-w-[1320px] px-3 md:px-4 py-6">
		<?php
		if ( class_exists( 'WooCommerce' ) ) {
			woocommerce_content();
		} else {
			?>
			<section class="mx-auto max-w-xl px-4 py-16 text-center" role="status">
				<h1 class="text-xl font-bold text-foreground">فروشگاه در دسترس نیست</h1>
				<p class="mt-3 text-sm leading-7 text-text-secondary">برای نمایش محصولات و دسته‌بندی‌های واقعی، افزونهٔ ووکامرس باید نصب و فعال باشد.</p>
				<a class="mt-6 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
			</section>
			<?php
		}
		?>
	</div>
</main>

<?php
get_footer();
