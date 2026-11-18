<?php
/**
 * پرداخت سریع موبایلی — مسیر مستقل کنار تسویه‌حساب اصلی.
 * این قالب هیچ شورتکد/فرم تسویه‌حساب اصلی را رندر نمی‌کند.
 */

get_header( 'minimal' );
?>
<main id="primary" class="site-main">
	<?php if ( function_exists( 'jluxe_mobile_checkout_enabled' ) && jluxe_mobile_checkout_enabled() ) : ?>
		<?php echo do_shortcode( '[jluxe_mobile_checkout]' ); ?>
	<?php else : ?>
		<section class="mx-auto max-w-xl px-4 py-16 text-center" role="status">
			<h1 class="text-xl font-bold text-foreground">پرداخت سریع موبایلی در دسترس نیست</h1>
			<p class="mt-3 text-sm leading-7 text-text-secondary">این مسیر از پنل زرین ← سبد خرید و تسویه حساب فعال می‌شود. تسویه‌حساب اصلی بدون تغییر در دسترس است.</p>
			<a class="mt-6 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">ادامه به تسویه‌حساب اصلی</a>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer( 'minimal' );
