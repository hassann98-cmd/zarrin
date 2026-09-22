<?php
/**
 * یک روش پرداخت — کارت سبز/تیک سبز. input[type=radio] واقعی رو نگه
 * می‌داریم (فقط بصری مخفی) چون checkout.js ووکامرس دقیقاً روی change شدن
 * همین input (name="payment_method") سوییچ می‌کنه و payment_box مربوط به
 * درگاه (فیلدهای اضافه‌ی هر درگاه، مثل توضیحات کارت‌به‌کارت) رو نمایش/
 * مخفی می‌کنه.
 *
 * طبق درخواست صریح کاربر («روش پرداخت هم نباید انتخاب‌شده باشه، مشتری
 * باید خودش انتخاب کنه») برخلاف تمپلیت اصلی ووکامرس، اینجا هیچ‌وقت
 * $gateway->chosen رو برای pre-check کردنِ رادیو استفاده نمی‌کنیم — فقط
 * وضعیت بصری عوض شده؛ منطق سرور/محاسبه‌ی ووکامرس دست‌نخورده می‌مونه.
 *
 * @see woocommerce/templates/checkout/payment-method.php (نسخه‌ی اصلی)
 * @version 3.5.0
 *
 * چیدمانِ کارتی (آیکن بالا، عنوان پایین، نشانِ تیک روی گوشه‌ی کارت) طبقِ
 * مرجعِ تصویریِ جدیدِ کاربر — قبلاً یک ردیفِ افقیِ کاملِ عرض بود.
 * .wc_payment_methods حالا در checkout/payment.php یک flex-wrap ئه (نه
 * grid با ستونِ ثابت، چون تعدادِ درگاه‌های واقعی ممکنه فقط ۱-۲تا باشه)،
 * پس خودِ <li> باید عرضِ ثابتِ خودش رو داشته باشه تا کارت‌ها بی‌قواره/
 * کشیده نشن.
 *
 * باگِ واقعیِ گزارش‌شده («انتخابش می‌کنم مستطیل می‌شه»): عرضِ ثابتِ بالا
 * قبلاً با کلاسِ Tailwind خامِ w-[152px] رو خودِ <li> بود، ولی خودِ
 * ووکامرس یک قانونِ پراختصاصیتِ #payment ul.payment_methods li داره
 * (woocommerce.css) که با اضافه‌شدنِ payment_box (توضیحاتِ درگاه، موقعِ
 * انتخاب) عرض رو به کلِ container می‌کشوند. عرضِ ثابت حالا با !important
 * در globals.css (کلاسِ jluxe-payment-method-item) اِعمال می‌شه — همون
 * الگویی که کل این فایل برای دورزدنِ استایل‌های پیش‌فرضِ ووکامرس استفاده
 * می‌کنه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="wc_payment_method jluxe-payment-method-item payment_method_<?php echo esc_attr( $gateway->id ); ?>">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio payment_method sr-only" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

	<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="jluxe-payment-card relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl p-4 text-center transition-colors hover:bg-muted">
		<span class="jluxe-payment-card-icon flex h-8 items-center justify-center">
			<?php echo $gateway->get_icon(); /* phpcs:ignore */ ?>
		</span>
		<span class="min-w-0 text-small font-medium text-foreground"><?php echo $gateway->get_title(); /* phpcs:ignore */ ?></span>
		<span class="jluxe-check-off absolute end-2.5 top-2.5 block size-5 rounded-full border-2 border-border bg-surface"></span>
		<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="jluxe-check-on absolute end-2.5 top-2.5 hidden rounded-full bg-surface text-success" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
	</label>
	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?> mt-2 rounded-xl bg-muted/60 p-4 text-small text-text-secondary" style="display:none;">
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
