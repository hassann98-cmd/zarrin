<?php
/**
 * R91 — برگهٔ «همه دسته‌بندی‌ها» (نامکِ product-categories، یا هر برگه‌ای که
 * در «زرین ← صفحهٔ دسته‌بندی‌ها» انتخاب شده — inc/categories-page.php).
 * خروجی کاملاً سمتِ سرور؛ بدونِ جاوااسکریپت.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main">
	<?php jluxe_render_categories_page(); ?>
</main>
<?php
get_footer();
