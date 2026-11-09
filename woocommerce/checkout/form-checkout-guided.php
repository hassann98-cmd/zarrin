<?php
/**
 * Two-step guided checkout. WooCommerce remains responsible for its native
 * customer fields, validation, shipping rates, order fragments, gateways,
 * terms, nonce, and order submission; the classic layout remains unchanged.
 *
 * @see woocommerce/checkout/form-checkout.php
 */

defined( 'ABSPATH' ) || exit;

$guided_map_enabled = jluxe_cart_checkout_option( 'checkout_map_enabled', true );
$guided_coordinates = $guided_map_enabled ? jluxe_checkout_map_coordinates_from_request() : null;
?>
<div class="mx-auto w-full max-w-[1180px] px-3 py-4 md:px-4 md:py-6 jluxe-guided-checkout-shell">
	<noscript><p class="woocommerce-error" role="alert"><?php echo esc_html( jluxe_cart_checkout_copy( 'checkout_no_js_notice', 'برای تکمیل خرید، جاوااسکریپت مرورگر باید فعال باشد.' ) ); ?> <a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html( jluxe_cart_checkout_required_copy( 'checkout_no_js_back', 'بازگشت به سبد خرید' ) ); ?></a></p></noscript>

	<form id="jluxe-guided-checkout-form" name="checkout" method="post" class="checkout woocommerce-checkout jluxe-guided-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" data-jluxe-checkout-flow="guided" data-guided-current-step="shipping">
		<header class="jluxe-guided-checkout__page-heading">
			<h1>تکمیل خرید</h1>
			<p>ابتدا اطلاعات ارسال را تکمیل کنید؛ در مرحلهٔ بعد، سفارش و روش پرداخت را بررسی کنید.</p>
		</header>

		<nav class="jluxe-guided-checkout__stepper" data-guided-stepper data-guided-current-step="shipping" aria-label="مراحل تکمیل خرید">
			<ol class="jluxe-guided-checkout__stepper-list">
				<li data-guided-step-item="shipping" data-guided-step-state="current">
					<button type="button" class="jluxe-guided-checkout__step-button" data-guided-step-target="shipping" aria-current="step" aria-controls="jluxe-guided-shipping-step" aria-label="مرحلهٔ ۱ از ۲: اطلاعات ارسال">
						<span class="jluxe-guided-checkout__step-marker" data-guided-step-marker aria-hidden="true">۱</span>
						<span class="jluxe-guided-checkout__step-label" data-guided-step-label>اطلاعات ارسال</span>
					</button>
				</li>
				<li data-guided-step-item="payment" data-guided-step-state="upcoming">
					<button type="button" class="jluxe-guided-checkout__step-button" data-guided-step-target="payment" aria-disabled="true" aria-controls="jluxe-guided-payment-step" aria-label="مرحلهٔ ۲ از ۲: شیوهٔ پرداخت" disabled>
						<span class="jluxe-guided-checkout__step-marker" data-guided-step-marker aria-hidden="true">۲</span>
						<span class="jluxe-guided-checkout__step-label" data-guided-step-label>شیوهٔ پرداخت</span>
					</button>
				</li>
			</ol>
		</nav>
		<p class="screen-reader-text" data-guided-step-status role="status" aria-live="polite" aria-atomic="true">مرحلهٔ ۱ از ۲: اطلاعات ارسال</p>

		<div class="jluxe-guided-checkout__content" data-guided-checkout-root>
			<section id="jluxe-guided-shipping-step" class="jluxe-guided-checkout__panel" data-guided-panel="shipping" aria-labelledby="jluxe-guided-shipping-title">
				<header class="jluxe-guided-checkout__panel-heading">
					<h2 id="jluxe-guided-shipping-title" tabindex="-1">اطلاعات ارسال</h2>
					<p>نشانی نوشتاری را وارد کنید. انتخاب موقعیت روی نقشه اختیاری است.</p>
				</header>
				<p class="jluxe-guided-checkout__step-error" data-guided-shipping-error role="alert" hidden>لطفاً فیلدهای الزامی اطلاعات ارسال را کامل و بررسی کنید.</p>

				<?php if ( $checkout->get_checkout_fields() ) : ?>
					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
					<div id="customer_details" class="jluxe-guided-checkout__customer-details">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>

					<?php if ( $guided_map_enabled ) : ?>
						<section class="jluxe-guided-map-picker" data-jluxe-map-picker data-center-lat="35.6892" data-center-lng="51.3890" aria-labelledby="jluxe-guided-map-title">
							<div class="jluxe-guided-map-picker__heading">
								<h3 id="jluxe-guided-map-title"><span class="jluxe-guided-map-picker__title-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed local SVG. ?></span>موقعیت روی نقشه <span class="jluxe-guided-map-picker__optional">اختیاری</span></h3>
								<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-open aria-expanded="false"><span data-map-open-label>انتخاب روی نقشه</span></button>
							</div>
							<div class="jluxe-guided-map-picker__body" data-map-body hidden>
								<p class="jluxe-guided-map-picker__note">انتخاب نقشه اختیاری است و جای نشانی نوشتاری را نمی‌گیرد.</p>
								<div class="jluxe-guided-map-picker__search" role="search">
									<label for="jluxe-guided-map-search">جست‌وجوی شهر یا محله</label>
									<div class="jluxe-guided-map-picker__search-row">
										<input id="jluxe-guided-map-search" type="search" autocomplete="off" maxlength="100" placeholder="نام شهر یا محله" data-map-search-input />
										<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-search-button>جست‌وجو</button>
									</div>
									<ul class="jluxe-guided-map-picker__results" data-map-results hidden></ul>
								</div>
								<div class="jluxe-guided-map-picker__tools">
									<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-geolocate>استفاده از موقعیت فعلی</button>
									<button type="button" class="jluxe-guided-checkout__text-button" data-map-clear hidden>پاک‌کردن موقعیت</button>
								</div>
								<div class="jluxe-guided-map-picker__canvas" data-map-canvas role="region" aria-label="نقشهٔ انتخاب موقعیت"></div>
								<p class="jluxe-guided-map-picker__status" data-map-status role="status" aria-live="polite">برای انتخاب، روی نقشه نقطه‌ای را لمس کنید.</p>
								<p class="jluxe-guided-map-picker__attribution">نقشه از OpenStreetMap بارگیری می‌شود؛ برای حریم خصوصی فقط شهر یا محله را جست‌وجو کنید.</p>
							</div>
						</section>
					<?php endif; ?>
					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
				<?php endif; ?>

				<div class="jluxe-guided-checkout__step-actions jluxe-guided-checkout__step-actions--shipping">
					<span class="jluxe-guided-checkout__step-hint">مرحلهٔ ۱ از ۲</span>
					<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" data-guided-next>ادامه به شیوهٔ پرداخت</button>
				</div>
			</section>

			<section id="jluxe-guided-payment-step" class="jluxe-guided-checkout__panel" data-guided-panel="payment" aria-labelledby="jluxe-guided-payment-title" hidden>
				<header class="jluxe-guided-checkout__panel-heading">
					<h2 id="jluxe-guided-payment-title" tabindex="-1">شیوهٔ پرداخت</h2>
					<p>اطلاعات ارسال، اقلام و مبلغ نهایی را بررسی و سپس روش پرداخت را انتخاب کنید.</p>
				</header>

				<section class="jluxe-guided-checkout__shipping-summary" data-guided-shipping-summary aria-labelledby="jluxe-guided-shipping-summary-title">
					<div class="jluxe-guided-checkout__shipping-summary-heading">
						<h3 id="jluxe-guided-shipping-summary-title">اطلاعات ارسال</h3>
						<button type="button" class="jluxe-guided-checkout__text-button" data-guided-edit-shipping>ویرایش</button>
					</div>
					<p class="jluxe-guided-checkout__shipping-summary-name" data-guided-shipping-name>نام گیرنده</p>
					<p class="jluxe-guided-checkout__shipping-summary-company" data-guided-shipping-company hidden></p>
					<p class="jluxe-guided-checkout__shipping-summary-address" data-guided-shipping-address>نشانی پس از تکمیل اطلاعات نمایش داده می‌شود.</p>
					<p class="jluxe-guided-checkout__shipping-summary-phone" data-guided-shipping-phone hidden></p>
				</section>

				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<?php $guided_summary_heading = jluxe_cart_checkout_copy( 'checkout_summary_heading', 'سفارش شما' ); ?>
				<?php if ( 'خلاصه سفارش' === trim( $guided_summary_heading ) ) : ?>
					<?php $guided_summary_heading = 'سفارش شما'; ?>
				<?php endif; ?>
				<section class="jluxe-guided-checkout__order" aria-labelledby="jluxe-guided-order-title">
					<h3 id="jluxe-guided-order-title" class="jluxe-guided-checkout__section-title"><?php echo esc_html( $guided_summary_heading ); ?></h3>
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					<div id="order_review" class="woocommerce-checkout-review-order jluxe-guided-checkout__order-review">
						<?php do_action( 'woocommerce_checkout_order_review' ); ?>
					</div>
					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</section>

				<section class="jluxe-guided-checkout__payment" data-guided-payment-section aria-labelledby="jluxe-guided-payment-methods-title">
					<h3 id="jluxe-guided-payment-methods-title" class="jluxe-guided-checkout__section-title">انتخاب روش پرداخت</h3>
					<?php woocommerce_checkout_payment(); ?>
				</section>

				<div id="jluxe-payment-submit" class="jluxe-guided-checkout__submit">
					<?php jluxe_render_payment_submit(); ?>
				</div>

				<div class="jluxe-guided-checkout__step-actions jluxe-guided-checkout__step-actions--payment">
					<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-guided-back>بازگشت به اطلاعات ارسال</button>
				</div>
			</section>
		</div>

		<?php if ( $guided_map_enabled ) : ?>
			<input type="hidden" name="jluxe_delivery_latitude" data-map-latitude value="<?php echo esc_attr( $guided_coordinates['latitude'] ?? '' ); ?>" />
			<input type="hidden" name="jluxe_delivery_longitude" data-map-longitude value="<?php echo esc_attr( $guided_coordinates['longitude'] ?? '' ); ?>" />
		<?php endif; ?>

		<div class="jluxe-guided-checkout__mobile-action" data-guided-mobile-action data-guided-mobile-step="shipping" hidden role="group" aria-label="اقدام خرید">
			<div class="jluxe-guided-checkout__mobile-total" aria-live="polite">
				<span>مبلغ سفارش</span>
				<strong data-guided-total><?php wc_cart_totals_order_total_html(); ?></strong>
			</div>
			<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary jluxe-guided-checkout__mobile-back" data-guided-back hidden>بازگشت</button>
			<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" data-guided-continue>
				<span data-guided-continue-label>ادامه به پرداخت</span>
				<span aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'arrow-left', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed local SVG. ?></span>
			</button>
		</div>
	</form>
</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
