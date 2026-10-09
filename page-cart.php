<?php
/**
 * صفحه‌ی سبد خرید و تسویه‌حساب (اسلاگ "cart").
 * وقتی ووکامرس فعاله، همون صفحه‌ی واقعی‌ای که ووکامرس با شورت‌کد
 * [woocommerce_cart] ساخته رندر می‌شه (بدون دست‌کاری منطق واقعی سبد).
 *
 * باگِ واقعیِ گزارش‌شده («این صفحه هیچ تمی نداره، کاملاً شبیه ووکامرسه»):
 * قبلاً این‌جا از the_content() استفاده می‌شد که هرچی توی خودِ محتوای
 * صفحه‌ی «سبد خرید» (پیشخوان ← صفحات) ذخیره شده رو چاپ می‌کنه. اگه اون
 * صفحه به‌جایِ متنِ شورت‌کد [woocommerce_cart]، از بلاکِ جدیدِ Cart
 * (که خودِ ووکامرس این روزها به‌صورتِ پیش‌فرض برای صفحه‌ی سبد خرید
 * می‌سازه) استفاده کنه، the_content() رابط‌کاربریِ خودِ همون بلاک رو
 * نشون می‌ده — که کاملاً مستقل از woocommerce/cart/cart.php ماست و
 * هیچ استایلی از این تم نمی‌گیره؛ دقیقاً همون «شبیه ووکامرسِ خام» که
 * گزارش شده. با صدازدنِ مستقیمِ شورت‌کد، صرف‌نظر از این‌که توی خودِ
 * صفحه چی ذخیره شده، همیشه از قالبِ اختصاصیِ خودمون استفاده می‌شه.
 */

// هدر/فوتر مینیمال — طبق رفتار معمول صفحه‌ی سبد خرید/تسویه‌حساب (بدون
// ناوبری سایت، بدون فوتر)، عیناً مثل algetshop.ir/checkout.
get_header( 'minimal' );
?>

<main id="primary" class="site-main">
	<?php
	if ( class_exists( 'WooCommerce' ) ) {
		if ( have_posts() ) {
			the_post();
		}
		echo do_shortcode( '[woocommerce_cart]' );
	} else {
		?>
		<section class="mx-auto max-w-xl px-4 py-16 text-center" role="status">
			<h1 class="text-xl font-bold text-foreground">سبد خرید در دسترس نیست</h1>
			<p class="mt-3 text-sm leading-7 text-text-secondary">برای فعال‌شدن سبد خرید و تسویه‌حساب، افزونهٔ ووکامرس باید نصب و فعال باشد. هیچ سفارش یا پرداختی از این صفحه انجام نمی‌شود.</p>
			<a class="mt-6 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
		</section>
		<?php
	}
	?>
</main>

<?php
get_footer( 'minimal' );
