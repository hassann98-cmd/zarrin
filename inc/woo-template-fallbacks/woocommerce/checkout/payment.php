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
<div id="payment" class="woocommerce-checkout-payment">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<?php
		/*
		 * هشدارِ VPN طبقِ مرجعِ تصویریِ جدیدِ کاربر — درگاه‌های پرداختِ
		 * ایرانی معمولاً با فیلترشکنِ فعال درست کار نمی‌کنن؛ این فقط یک
		 * یادآوریِ متنیه، به هیچ منطقِ واقعیِ ووکامرس دست نمی‌زنه.
		 */
		?>
		<div class="mb-4 flex items-start gap-2 rounded-xl bg-warning/15 p-4 text-small text-foreground">
			<span class="mt-0.5 shrink-0 text-warning">⚠</span>
			<span>در صورتی که از فیلترشکن (VPN) استفاده می‌کنید، قبل از ورود به درگاه پرداخت آن را خاموش کنید.</span>
		</div>

		<p class="mb-3 text-small font-medium text-foreground">روش پرداخت</p>
		<?php
		/*
		 * طبقِ درخواستِ صریحِ کاربر («درگاه‌های قابل‌انتخاب رو مرتب‌تر
		 * بچین») از grid با تعدادِ ستونِ ثابت به flex-wrap با اندازه‌ی
		 * ثابتِ هر کارت تغییر کرد — چون تعدادِ درگاه‌های واقعاً فعال ممکنه
		 * فقط ۱ یا ۲ تا باشه (نه همیشه ۳-۴تا)، و grid با ستون‌بندیِ ثابت
		 * کارتِ تنها رو کشیده/بی‌قواره نشون می‌داد. حالا هر کارت عرضِ
		 * مشخصِ خودش رو داره و می‌چینه، خالی نمی‌مونه.
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
	<?php endif; ?>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
