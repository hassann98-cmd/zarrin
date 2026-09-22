<?php
/**
 * فرم تسویه‌حساب — چیدمان دو ستونه (فرم اصلی راست، خلاصه‌ی سفارش چپ در
 * دسکتاپ، طبق dir="rtl"). همه‌ی اکشن‌های اصلی ووکامرس حفظ شدن؛ فقط
 * div-بندی/چیدمان تغییر کرده.
 *
 * @see woocommerce/templates/checkout/form-checkout.php (نسخه‌ی اصلی)
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

jluxe_render_checkout_stepper( 'shipping' );
?>

<div class="mx-auto w-full max-w-[1296px] px-4 py-6">
	<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

		<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
			<div>
				<?php if ( $checkout->get_checkout_fields() ) : ?>
					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

					<div id="customer_details">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>

					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
				<?php endif; ?>

				<?php
				/*
				 * طبقِ درخواستِ صریحِ کاربر («روش‌های پرداخت رو تو یک کادرِ
				 * جدا سمتِ راستِ صفحه») روشِ پرداخت دیگه داخلِ سایدبارِ باریکِ
				 * خلاصه‌سفارش نیست — همین ستونِ عریض (جایی که فرمِ آدرس بود)
				 * توسطِ assets/js/woocommerce.js وقتی مرحله عوض می‌شه، بین
				 * #customer_details و این جعبه سوییچ می‌شه (هر دو‌شون از قبل
				 * دیزاینِ استپ‌شو رو دارن). #payment از قبل کلاسِ
				 * woocommerce-checkout-payment رو داره — همون کلاسی که خودِ
				 * ووکامرس برای فرگمنتِ AJAX زنده استفاده می‌کنه، پس امنه که
				 * دیگه تو در توی #order_review نباشه (به
				 * inc/woocommerce.php → remove_action(...woocommerce_checkout_payment...)
				 * مراجعه بشه).
				 */
				?>
				<div id="jluxe-payment-column" class="rounded-2xl border border-border p-5" hidden>
					<?php woocommerce_checkout_payment(); ?>
				</div>
			</div>

			<div class="flex h-fit flex-col gap-3 rounded-2xl border border-border p-5 lg:sticky lg:top-24">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

				<p class="text-small font-medium text-foreground">خلاصه سفارش</p>

				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

				<?php
				/*
				 * طبقِ درخواستِ صریحِ کاربر («دکمه‌ی پرداخت رو ببر زیرِ
				 * بازگشت، سمتِ چپ») این‌جا بلافاصله بعدِ #order_review
				 * می‌شینه — assets/js/woocommerce.js دکمه‌های «نهایی‌سازی
				 * سفارش»/«بازگشت» رو با insertAdjacentElement('afterend', ...)
				 * روی همین #order_review اضافه می‌کنه، یعنی بینِ این دو
				 * قرار می‌گیرن؛ نتیجه‌ی نهاییِ DOM دقیقاً: خلاصه‌سفارش →
				 * (نهایی‌سازی سفارش/بازگشت) → پرداخت.
				 */
				?>
				<div id="jluxe-payment-submit" hidden>
					<?php jluxe_render_payment_submit(); ?>
				</div>
			</div>
		</div>
	</form>
</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
