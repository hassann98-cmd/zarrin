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
			// موقتی: پیش‌نمایش صفحه‌ی شاپ/آرشیو دسته با داده‌ی mock تا در فاز طراحی بصری تأیید بشه.
			// در سایت واقعی (WooCommerce فعال) این شاخه هیچ‌وقت اجرا نمی‌شه.
			?>
			<div data-jluxe-island="shop-archive-demo"></div>
			<?php
		}
		?>
	</div>
</main>

<?php
get_footer();
