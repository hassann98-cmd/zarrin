<?php
/**
 * Homepage skeleton.
 * Server-rendered shell only — hero/featured-products content lands in a later phase.
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php /* Keep a semantic, screen-reader-visible H1; the visual hero does not replace the page heading. */ ?>
	<h1 class="sr-only"><?php bloginfo( 'name' ); ?></h1>
	<p class="sr-only"><?php bloginfo( 'description' ); ?></p>

	<?php
	// بخش‌های واقعیِ صفحه‌ی اصلی، هرکدوم طبق ترتیب/تنظیمات ذخیره‌شده در
	// JLuxe Theme → صفحه اصلی (wp-admin). داده‌ی محصولات واقعاً از
	// WooCommerce میاد، نه mock.
	jluxe_render_homepage_sections();
	?>
</main>

<?php
get_footer();
