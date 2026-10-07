<?php
/**
 * صفحه‌ی حساب کاربری (اسلاگ my-account — همون صفحه‌ی واقعیِ WooCommerce).
 * وقتی کاربر لاگینه، دقیقاً رفتار پیش‌فرض ووکامرس (داشبورد/سفارش‌ها/آدرس‌ها)
 * دست‌نخورده می‌مونه. وقتی لاگین نیست، به‌جاش یک صفحه‌ی تمام‌صفحه‌ی
 * ورود/ثبت‌نام (بدون هدر/فوتر سایت، طبق طرح مرجع) نشون داده می‌شه که به
 * REST واقعی (inc/auth.php، inc/theme-settings-sms.php) وصله.
 */

$jluxe_recovery = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' );
if ( is_user_logged_in() || $jluxe_recovery ) {
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
		if ( $jluxe_recovery ) {
			// R70: بازطراحیِ «فراموشی رمز» — قالبِ کارتیِ هم‌زبان با صفحهٔ ورود؛
			// فرم‌های واقعی ووکامرس (درخواستِ ریست با کلید/nonce معتبر) داخلش
			// رندر می‌شوند و کلِ اعتبارسنجی/ریست با خودِ وو است.
			?>
			<div class="jluxe-recover" dir="rtl">
				<div class="jluxe-recover-card">
					<a class="jluxe-auth-back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span aria-hidden="true">›</span> بازگشت به فروشگاه</a>
					<div class="jluxe-recover-brand" aria-hidden="true">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					</div>
					<h1>بازیابی رمز عبور</h1>
					<p class="jluxe-recover-sub">شمارهٔ موبایل یا نام‌کاربری حساب خود را وارد کنید تا لینکِ بازیابی برایتان ارسال شود.</p>
					<?php echo do_shortcode( '[woocommerce_my_account]' ); ?>
				</div>
			</div>
			<?php
		} else {
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
		}
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
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'jluxe-auth-page' ); ?>>
<?php wp_body_open(); ?>
	<?php $jluxe_otp_only = function_exists( 'jluxe_otp_available' ) && jluxe_otp_available() && ! empty( ( function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['sms'] : array() )['otp_only'] ); ?>
<div data-jluxe-island="auth-page"<?php echo $jluxe_otp_only ? ' data-otp-only="1"' : ''; ?>><p class="jluxe-auth-fallback">در حال بارگذاری فرم ورود… <a href="<?php echo esc_url( wp_login_url() ); ?>">ورود با فرم استاندارد</a></p></div>
<?php wp_footer(); ?>
</body>
</html>
