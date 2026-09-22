<?php
/**
 * جمع سبد خرید — کنار داخل کارتِ sidebar که در cart.php ساخته شده (بدون
 * border/padding اضافه چون قالب بیرونی خودش کارته).
 *
 * @see woocommerce/templates/cart/cart-totals.php (نسخه‌ی اصلی)
 * @version 2.3.6
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?> flex flex-col gap-3">

	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<?php
	/*
	 * طبقِ درخواستِ صریحِ کاربر («این هم جابجاست») همه‌ی ردیف‌های
	 * برچسب:مقدارِ این جمع (نه فقط ردیفِ نهایی) به همون ترتیبِ
	 * برچسب-اول/مقدار-دوم تغییر کردن، برای یکدستی — توی dir="rtl" یعنی
	 * برچسب سمتِ راست، مقدار سمتِ چپ.
	 */
	?>
	<div class="flex items-center justify-between text-small text-text-secondary">
		<span>قیمت کالاها (<?php echo esc_html( jluxe_fa_digits( WC()->cart->get_cart_contents_count() ) ); ?>):</span>
		<span><?php wc_cart_totals_subtotal_html(); ?></span>
	</div>

	<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
		<div class="flex items-center justify-between text-small text-success">
			<span><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
			<span>-<?php wc_cart_totals_coupon_html( $coupon ); ?></span>
		</div>
	<?php endforeach; ?>

	<?php
	/*
	 * روش ارسال دیگه توی سبد خرید انتخاب نمی‌شه — طبق درخواست صریح کاربر
	 * («تو این صفحه نباید روش ارسال رو داشته باشیم») این مرحله فقط بررسی
	 * سبد خریده؛ انتخاب واقعیِ روش ارسال به مرحله‌ی بعد (اطلاعات ارسال در
	 * چک‌اوت، woocommerce/checkout/review-order.php) منتقل شده. اگر سبد
	 * نیاز به ارسال داره، فقط یک یادآوریِ متنی نشون می‌دیم؛ هیچ
	 * calculator/رادیویی این‌جا رندر نمی‌شه.
	 */
	if ( WC()->cart->needs_shipping() ) :
		?>
		<p class="border-t border-border pt-3 text-caption text-text-muted">هزینه‌ی ارسال در مرحله‌ی «اطلاعات ارسال» بر اساس آدرس شما محاسبه و نمایش داده می‌شود.</p>
	<?php endif; ?>

	<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
		<div class="flex items-center justify-between text-small text-text-secondary">
			<span><?php echo esc_html( $fee->name ); ?></span>
			<span><?php wc_cart_totals_fee_html( $fee ); ?></span>
		</div>
	<?php endforeach; ?>

	<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
		<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
			<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
				<div class="flex items-center justify-between text-small text-text-secondary">
					<span><?php echo esc_html( $tax->label ); ?></span>
					<span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="flex items-center justify-between text-small text-text-secondary">
				<span><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
				<span><?php wc_cart_totals_taxes_total_html(); ?></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

	<?php
	/*
	 * طبقِ درخواستِ صریحِ کاربر («مبلغ و قیمتِ قابل‌پرداخت باید جابجا شه»)
	 * برخلافِ بقیه‌ی ردیف‌های جمع (که ترتیبشون مقدار-اول/برچسب-دوم هست)،
	 * این ردیفِ نهایی (پررنگ‌ترین/مهم‌ترینِ کل جمع) برچسب-اول/مقدار-دوم
	 * شد — یعنی توی dir="rtl" برچسب سمتِ راست و مقدار سمتِ چپ می‌شینه.
	 */
	?>
	<div class="flex items-center justify-between border-t border-border pt-3 text-body font-bold text-foreground">
		<span>مبلغ قابل پرداخت:</span>
		<span><?php wc_cart_totals_order_total_html(); ?></span>
	</div>

	<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

	<div class="jluxe-cart-actions sticky bottom-0 z-10 -mx-5 -mb-5 flex flex-col gap-2.5 border-t border-border bg-surface p-4 sm:static sm:z-auto sm:m-0 sm:border-0 sm:p-0">
		<div class="jluxe-checkout-btn-wrap w-full">
			<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
		</div>
		<?php
		/*
		 * طبق درخواست صریح کاربر («بازگشت به فروشگاه که باید بره صفحه
		 * اصلی») این دکمه به صفحه‌ی اصلی سایت می‌ره، نه صفحه‌ی فروشگاه.
		 */
		?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex h-12 w-full items-center justify-center gap-1.5 rounded-lg border border-border px-5 text-button font-normal text-foreground transition-colors hover:bg-muted">
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
			بازگشت به فروشگاه
		</a>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
