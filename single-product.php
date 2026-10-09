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
		?>
		<section class="mx-auto max-w-xl px-4 py-16 text-center" role="status">
			<h1 class="text-xl font-bold text-foreground">صفحهٔ محصول در دسترس نیست</h1>
			<p class="mt-3 text-sm leading-7 text-text-secondary">برای نمایش اطلاعات واقعیِ محصول، افزونهٔ ووکامرس باید نصب و فعال باشد.</p>
			<a class="mt-6 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
		</section>
		<?php
	}
	?>
</main>

<?php
get_footer();
