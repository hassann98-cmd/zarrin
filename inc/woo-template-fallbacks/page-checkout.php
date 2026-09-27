<?php
/**
 * صفحه‌ی تسویه‌حساب (اسلاگ "checkout").
 * تا این‌جا هیچ قالبی برای این صفحه وجود نداشت، پس وردپرس به page.php عمومی
 * (با هدر/فوتر کامل سایت) سقوط می‌کرد. طبق همون منطق page-cart.php: وقتی
 * ووکامرس فعاله، شورت‌کد واقعی [woocommerce_checkout] رندر می‌شه؛ هدر/فوتر
 * مینیمال چون چک‌اوت باید یک جریان متمرکز خرید باشه، نه صفحه‌ی ناوبری سایت.
 *
 * دقیقاً همون باگی که page-cart.php داشت («شبیه ووکامرسِ خام»، نه تمِ
 * خودمون) این‌جا هم ممکنه پیش بیاد اگه صفحه‌ی «تسویه‌حساب» به‌جای متنِ
 * شورت‌کد از بلاکِ Checkoutِ خودِ ووکامرس استفاده کنه — برای همین این‌جا
 * هم مستقیم شورت‌کد صدا زده می‌شه، نه the_content().
 */

get_header( 'minimal' );
?>

<main id="primary" class="site-main">
	<?php
	if ( class_exists( 'WooCommerce' ) ) {
		if ( have_posts() ) {
			the_post();
		}
		echo do_shortcode( '[woocommerce_checkout]' );
	}
	?>
</main>

<?php
get_footer( 'minimal' );
