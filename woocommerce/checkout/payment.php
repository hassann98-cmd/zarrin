<?php
/**
 * بخش پرداخت — درگاه‌های واقعیِ فعالِ ووکامرس (WC_Payment_Gateways، نه
 * لیست ثابت). طبق درخواست صریح کاربر، مرحله‌ی «نحوه پرداخت» دقیقاً ۲
 * دکمه داره: «پرداخت» (ثبت سفارش) و «بازگشت» — بازگشت باید به مرحله‌ی
 * قبل (اطلاعات ارسال) برگرده، نه مستقیم به سبد خرید؛ پس دیگه لینک
 * «بازگشت به سبد خرید»ی که تمپلیت اصلی ووکامرس این‌جا داشت رندر نمی‌شه —
 * دکمه‌ی «بازگشت»ِ واقعی توسط assets/js/woocommerce.js (که این مرحله رو
 * نمایش/مخفی می‌کنه) بیرون از #payment ساخته می‌شه.
 *
 * @see woocommerce/templates/checkout/payment.php (نسخه‌ی اصلی)
 * @version 10.9.0
 *
 * طبقِ درخواستِ صریحِ کاربر («روش‌های پرداخت توی یک کادرِ جدا سمتِ راستِ
 * صفحه، و ریسپانسیو موبایل») این‌جا دیگه داخلِ سایدبارِ باریکِ خلاصه‌سفارش
 * نیست — یک ستونِ عریضِ جدا (form-checkout.php: #jluxe-payment-column) که
 * جای فرمِ آدرس رو موقعِ مرحله‌ی «نحوه پرداخت» می‌گیره. کارتِ بیرونی
 * (border/padding/rounded) رو خودِ #jluxe-payment-column می‌ده، پس این‌جا
 * فقط محتوا (بدونِ کارتِ تودرتوی اضافه). به inc/woocommerce.php →
 * remove_action(...woocommerce_checkout_payment...) مراجعه بشه.
 *
 * طبقِ درخواستِ صریحِ بعدیِ کاربر («دکمه‌ی پرداخت رو ببر زیرِ بازگشت، سمتِ
 * چپ») بخشِ «ثبت سفارش» (شرایط/دکمه/nonce) دیگه این‌جا نیست — رفته توی
 * سایدبارِ باریکِ کنارِ «بازگشت» (jluxe_render_payment_submit() در
 * inc/woocommerce.php، صدا زده‌شده از form-checkout.php). این‌جا فقط
 * انتخابِ درگاه می‌مونه. توضیحِ ایمنیِ AJAX (چرا این جدایی خرابش نمی‌کنه)
 * در همون تابع هست.
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<?php $jluxe_payment_required = WC()->cart && WC()->cart->needs_payment(); ?>
<div id="payment" class="woocommerce-checkout-payment" data-jluxe-payment-required="<?php echo $jluxe_payment_required ? '1' : '0'; ?>" aria-labelledby="jluxe-payment-heading">
	<h2 id="jluxe-payment-heading" tabindex="-1" class="mb-3 text-small font-medium text-foreground"><?php echo $jluxe_payment_required ? 'روش پرداخت' : 'ثبت سفارش'; ?></h2>
	<?php if ( $jluxe_payment_required ) : ?>
		<?php
		/*
		 * هشدارِ VPN طبقِ مرجعِ تصویریِ جدیدِ کاربر — درگاه‌های پرداختِ
		 * ایرانی معمولاً با فیلترشکنِ فعال درست کار نمی‌کنن؛ این فقط یک
		 * یادآوریِ متنیه، به هیچ منطقِ واقعیِ ووکامرس دست نمی‌زنه.
		 */
		?>
		<div class="jluxe-checkout-vpn-note" role="note">
			<span class="jluxe-checkout-vpn-note__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 20 6v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6l8-3Z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
			</span>
			<span class="jluxe-checkout-vpn-note__copy">
				<strong>نکتهٔ پیش از پرداخت</strong>
				<span>اگر از فیلترشکن (VPN) استفاده می‌کنید، پیش از ورود به درگاه پرداخت آن را خاموش کنید.</span>
			</span>
		</div>

		<?php
		/*
		 * چیدمانِ کارت‌های درگاه به مرجعِ سالمِ v1.7.29 برمی‌گردد: کارت‌ها
		 * داخلِ flex-wrap می‌چینند. نشانِ data-jluxe-payment-required و
		 * پیام/عنوانِ سفارشِ بدون پرداخت عمداً حفظ شده‌اند؛ گیتِ checkout جدید
		 * برای تمایزِ «سفارش رایگان» از «درگاهِ پرداختِ در دسترس نیست» به آن نیاز دارد.
		 */
		?>
		<ul class="wc_payment_methods payment_methods methods flex flex-wrap gap-3" aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li>';
				wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ), 'notice' );
				echo '</li>';
			}
			?>
		</ul>
	<?php else : ?>
		<p role="status" class="text-small text-text-secondary">برای این سفارش، نیازی به انتخاب روش پرداخت نیست.</p>
	<?php endif; ?>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
