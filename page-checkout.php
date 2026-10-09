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
	} else {
		?>
		<section class="mx-auto max-w-xl px-4 py-16 text-center" role="status">
			<h1 class="text-xl font-bold text-foreground">تسویه‌حساب در دسترس نیست</h1>
			<p class="mt-3 text-sm leading-7 text-text-secondary">برای فعال‌شدنِ ثبت سفارش و پرداخت، افزونهٔ ووکامرس باید نصب و فعال باشد.</p>
			<a class="mt-6 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
		</section>
		<?php
	}
	?>
</main>

<?php
get_footer( 'minimal' );
