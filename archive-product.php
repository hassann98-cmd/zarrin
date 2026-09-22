<?php
/**
 * WooCommerce product archive/shop page skeleton.
 * Uses WooCommerce's own hook system (woocommerce_content) instead of copying
 * every core template file — standard, low-maintenance override pattern.
 */

get_header();
?>

<main id="primary" class="site-main">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-6">
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
