<?php
/**
 * صفحه‌ی حساب کاربری (اسلاگ my-account — همون صفحه‌ی واقعیِ WooCommerce).
 * وقتی کاربر لاگینه، دقیقاً رفتار پیش‌فرض ووکامرس (داشبورد/سفارش‌ها/آدرس‌ها)
 * دست‌نخورده می‌مونه. وقتی لاگین نیست، به‌جاش یک صفحه‌ی تمام‌صفحه‌ی
 * ورود/ثبت‌نام (بدون هدر/فوتر سایت، طبق طرح مرجع) نشون داده می‌شه که به
 * REST واقعی (inc/auth.php، inc/theme-settings-sms.php) وصله.
 */

if ( is_user_logged_in() ) {
	get_header();
	?>
	<main id="primary" class="site-main">
		<?php
		/*
		 * برخلاف archive-product.php/single-product.php که واقعاً باید
		 * woocommerce_content() صدا بزنن، صفحه‌ی «حساب کاربری» یک Page
		 * معمولیه که محتواش شورت‌کد [woocommerce_my_account] هست — پس باید
		 * از همون Loop استاندارد وردپرس (the_content()) رد بشه تا شورت‌کد
		 * واقعاً اجرا و داشبورد/سفارش‌ها/آدرس‌ها رندر بشن؛ woocommerce_content()
		 * برای این صفحه هیچ ربطی نداره و باگ واقعی (نمایش اشتباه Shop) می‌سازه.
		 */
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>
	<?php
	get_footer();
	return;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'ورود و عضویت', 'jluxe' ); ?> — <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'jluxe-auth-page' ); ?>>
<?php wp_body_open(); ?>
	<div data-jluxe-island="auth-page"></div>
<?php wp_footer(); ?>
</body>
</html>
