<?php
/**
 * Homepage skeleton.
 * Server-rendered shell only — hero/featured-products content lands in a later phase.
 */

get_header();
?>

<main id="primary" class="site-main">
	<!-- H1 صفحه‌ی اصلی همچنان برای سئو لازمه؛ چون اسلایدشو جایگزین بصری‌شه، فقط از نظر بینایی مخفیه (sr-only) نه حذف. -->
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
