<?php
/**
 * Single product page skeleton.
 * Uses WooCommerce's own hook system (woocommerce_content) instead of copying
 * every core template file — standard, low-maintenance override pattern.
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php
	if ( class_exists( 'WooCommerce' ) ) {
		woocommerce_content();
	} else {
		// موقتی: پیش‌نمایش صفحه‌ی محصول با داده‌ی mock تا در فاز طراحی بصری تأیید بشه.
		// در سایت واقعی (WooCommerce فعال) این شاخه هیچ‌وقت اجرا نمی‌شه.
		?>
		<div data-jluxe-island="product-details-demo"></div>
		<?php
	}
	?>
</main>

<?php
get_footer();
