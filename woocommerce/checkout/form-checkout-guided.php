<?php
/**
 * Optional, mobile-first checkout flow. The classic template remains the default;
 * this view only rearranges native WooCommerce fields, order-review fragments,
 * shipping rates, gateways and submit controls.
 *
 * @see woocommerce/checkout/form-checkout.php
 */

defined( 'ABSPATH' ) || exit;

$guided_values = array();
foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_state', 'billing_city', 'billing_address_1', 'billing_postcode' ) as $guided_key ) {
	$guided_value = $checkout->get_value( $guided_key );
	$guided_values[ $guided_key ] = is_scalar( $guided_value ) ? trim( (string) $guided_value ) : '';
}
$guided_state_label = $guided_values['billing_state'];
if ( function_exists( 'WC' ) && WC()->countries ) {
	$guided_states = WC()->countries->get_states( 'IR' );
	if ( is_array( $guided_states ) && isset( $guided_states[ $guided_values['billing_state'] ] ) ) {
		$guided_state_label = (string) $guided_states[ $guided_values['billing_state'] ];
	}
}
$guided_recipient = trim( $guided_values['billing_first_name'] . ' ' . $guided_values['billing_last_name'] );
$guided_city_line = implode( '، ', array_filter( array( $guided_state_label, $guided_values['billing_city'] ) ) );
$guided_has_address = '' !== $guided_values['billing_state'] && '' !== $guided_values['billing_city'];
$guided_has_errors = function_exists( 'wc_notice_count' ) && wc_notice_count( 'error' ) > 0;
$guided_form_open = $guided_has_errors;
$guided_map_enabled = jluxe_cart_checkout_option( 'checkout_map_enabled', true );
$guided_coordinates = $guided_map_enabled ? jluxe_checkout_map_coordinates_from_request() : null;
$guided_initial_step = isset( $_POST['woocommerce_checkout_place_order'] ) || isset( $_POST['woocommerce_checkout_update_totals'] ) ? 'payment' : 'address'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only selects the visible panel; WooCommerce still validates the checkout nonce before order creation.
?>
<div class="mx-auto w-full max-w-[1180px] px-3 py-4 md:px-4 md:py-6">
	<noscript><p class="woocommerce-error" role="alert"><?php echo esc_html( jluxe_cart_checkout_copy( 'checkout_no_js_notice', 'برای تکمیل خرید، جاوااسکریپت مرورگر باید فعال باشد.' ) ); ?> <a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html( jluxe_cart_checkout_required_copy( 'checkout_no_js_back', 'بازگشت به سبد خرید' ) ); ?></a></p></noscript>

	<form name="checkout" method="post" class="checkout woocommerce-checkout jluxe-guided-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" data-jluxe-checkout-flow="guided" data-jluxe-guided-initial-step="<?php echo esc_attr( $guided_initial_step ); ?>">
		<?php if ( jluxe_cart_checkout_option( 'show_stepper', true ) ) : ?>
			<nav class="jluxe-guided-checkout__stepper" aria-label="مراحل خرید" data-guided-stepper>
				<ol>
					<li data-guided-step="address" data-guided-step-state="<?php echo 'payment' === $guided_initial_step ? 'done' : 'active'; ?>"<?php echo 'address' === $guided_initial_step ? ' aria-current="step"' : ''; ?>>
						<span class="jluxe-guided-checkout__step-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'truck', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>
						<span data-guided-step-label>نشانی و ارسال</span>
					</li>
					<li data-guided-step="payment" data-guided-step-state="<?php echo 'payment' === $guided_initial_step ? 'active' : 'pending'; ?>"<?php echo 'payment' === $guided_initial_step ? ' aria-current="step"' : ''; ?>>
						<span class="jluxe-guided-checkout__step-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'credit-card', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>
						<span data-guided-step-label>مرور سفارش و پرداخت</span>
					</li>
				</ol>
			</nav>
		<?php endif; ?>

		<header class="jluxe-guided-checkout__heading">
			<p class="jluxe-guided-checkout__eyebrow">تکمیل خرید</p>
			<h1 data-guided-page-title><?php echo 'payment' === $guided_initial_step ? 'مرور سفارش و پرداخت' : 'نشانی تحویل'; ?></h1>
			<p data-guided-page-description><?php echo 'payment' === $guided_initial_step ? 'روش ارسال، جمع سفارش و درگاه پرداخت را بررسی کنید.' : 'اطلاعات گیرنده و نشانی نوشتاری را وارد کنید؛ هزینهٔ ارسال از روی همین اطلاعات در WooCommerce محاسبه می‌شود.'; ?></p>
		</header>

		<div class="jluxe-guided-checkout__grid" data-guided-checkout-root data-guided-step="<?php echo esc_attr( $guided_initial_step ); ?>" data-guided-has-address="<?php echo $guided_has_address ? '1' : '0'; ?>">
			<div class="jluxe-guided-checkout__main">
				<section class="jluxe-guided-checkout__stage" data-guided-panel="address" aria-labelledby="jluxe-guided-address-title"<?php echo 'payment' === $guided_initial_step ? ' hidden' : ''; ?>>
					<h2 id="jluxe-guided-address-title" class="jluxe-guided-checkout__stage-title"><span class="jluxe-guided-checkout__heading-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'truck', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>اطلاعات ارسال</h2>
					<p class="jluxe-guided-checkout__address-error" data-guided-address-error role="alert" hidden>فیلدهای الزامی را تکمیل کنید تا به مرحلهٔ بعد بروید.</p>

					<section class="jluxe-guided-checkout__address-card" data-guided-address-card<?php echo $guided_has_address && ! $guided_has_errors ? '' : ' hidden'; ?> aria-label="نشانی گیرنده">
						<div class="jluxe-guided-checkout__address-card-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map-pin', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></div>
						<div class="jluxe-guided-checkout__address-card-copy">
							<strong data-guided-address-recipient><?php echo esc_html( $guided_recipient ?: $guided_values['billing_phone'] ); ?></strong>
							<p data-guided-address-text><?php echo esc_html( implode( '، ', array_filter( array( $guided_city_line, $guided_values['billing_address_1'], $guided_values['billing_postcode'] ) ) ) ); ?></p>
							<?php if ( $guided_recipient && $guided_values['billing_phone'] ) : ?><span data-guided-address-phone><?php echo esc_html( $guided_values['billing_phone'] ); ?></span><?php else : ?><span data-guided-address-phone hidden></span><?php endif; ?>
						</div>
						<button class="jluxe-guided-checkout__text-button" type="button" data-guided-open-address><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'edit', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>ویرایش نشانی</button>
					</section>

					<section class="jluxe-guided-checkout__empty-address" data-guided-empty-address<?php echo ! $guided_has_address && ! $guided_has_errors ? '' : ' hidden'; ?> aria-live="polite">
						<span class="jluxe-guided-checkout__empty-address-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map-pin', 'size-7' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>
						<strong>هنوز نشانی‌ای ثبت نشده است</strong>
						<p>برای محاسبهٔ هزینه و روش‌های ارسال، نشانی تحویل را اضافه کنید.</p>
						<button class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" type="button" data-guided-open-address>افزودن نشانی</button>
					</section>

					<?php if ( $checkout->get_checkout_fields() ) : ?>
						<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
						<div class="jluxe-guided-checkout__address-scrim" data-guided-address-scrim hidden aria-hidden="true"></div>
						<div id="customer_details" class="jluxe-guided-checkout__customer-details" data-guided-address-editor aria-labelledby="jluxe-guided-address-editor-title" tabindex="-1"<?php echo $guided_form_open ? '' : ' hidden'; ?>>
							<header class="jluxe-guided-checkout__editor-heading">
								<span class="jluxe-guided-checkout__editor-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map-pin', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>
								<div class="jluxe-guided-checkout__editor-heading-copy">
									<p>نشانی تحویل</p>
									<h3 id="jluxe-guided-address-editor-title">اطلاعات گیرنده و نشانی</h3>
									<span>نشانی نوشتاری برای محاسبهٔ دقیق ارسال لازم است.</span>
								</div>
								<button class="jluxe-guided-checkout__editor-close" type="button" data-guided-close-address aria-label="بستن فرم نشانی"><?php echo jluxe_icon_svg_markup( 'close', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></button>
							</header>
							<?php do_action( 'woocommerce_checkout_billing' ); ?>
							<?php do_action( 'woocommerce_checkout_shipping' ); ?>

							<?php if ( $guided_map_enabled ) : ?>
								<section class="jluxe-guided-map-picker" data-jluxe-map-picker data-center-lat="35.6892" data-center-lng="51.3890" aria-labelledby="jluxe-guided-map-title">
									<div class="jluxe-guided-map-picker__heading">
										<div>
											<h3 id="jluxe-guided-map-title"><span class="jluxe-guided-map-picker__title-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span><span>انتخاب موقعیت روی نقشه</span><span class="jluxe-guided-map-picker__optional">اختیاری</span></h3>
											<p>برای راهنمایی پیک، موقعیت را مشخص کنید. این انتخاب جای نشانی متنی را نمی‌گیرد.</p>
										</div>
										<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-open aria-expanded="false"><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map-pin', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span><span data-map-open-label>بازکردن نقشه</span></button>
									</div>
									<div class="jluxe-guided-map-picker__body" data-map-body hidden>
										<div class="jluxe-guided-map-picker__search" role="search">
											<label for="jluxe-guided-map-search">جست‌وجوی شهر یا محله</label>
											<div class="jluxe-guided-map-picker__search-row">
												<input id="jluxe-guided-map-search" type="search" autocomplete="off" maxlength="100" placeholder="نام شهر یا محله؛ نشانی دقیق را وارد نکنید" data-map-search-input />
												<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-search-button><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'search', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>جست‌وجو</button>
											</div>
											<ul class="jluxe-guided-map-picker__results" data-map-results hidden></ul>
										</div>
										<div class="jluxe-guided-map-picker__tools">
											<button type="button" class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" data-map-geolocate><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'locate', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>استفاده از موقعیت فعلی</button>
											<button type="button" class="jluxe-guided-checkout__text-button" data-map-clear hidden><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'close', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>پاک‌کردن موقعیت</button>
										</div>
										<div class="jluxe-guided-map-picker__canvas" data-map-canvas role="region" aria-label="نقشهٔ انتخاب موقعیت"></div>
										<p class="jluxe-guided-map-picker__status" data-map-status role="status" aria-live="polite">برای انتخاب موقعیت روی نقشه کلیک کنید یا نشانگر را جابه‌جا کنید.</p>
										<p class="jluxe-guided-map-picker__attribution">با بازکردن نقشه، مرورگر به OpenStreetMap وصل می‌شود. جست‌وجو فقط عبارت تایپ‌شده را به Nominatim عمومی می‌فرستد؛ برای حریم خصوصی، شهر یا محله را جست‌وجو کنید و نشانی دقیق را در فیلدهای متنی بنویسید.</p>
									</div>
								</section>
							<?php endif; ?>

						</div>
						<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
					<?php endif; ?>

					<div class="jluxe-guided-checkout__address-actions" data-guided-address-actions<?php echo 'payment' === $guided_initial_step ? ' hidden' : ''; ?>>
						<a class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'cart', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span><?php echo esc_html( jluxe_cart_checkout_required_copy( 'checkout_back_to_cart_button', 'بازگشت به سبد خرید' ) ); ?></a>
						<button class="jluxe-guided-checkout__text-button" type="button" data-guided-cancel-edit hidden><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'close', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>انصراف</button>
						<button class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" type="button" data-guided-next><span>ادامه به مرور سفارش</span><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'arrow-left', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span></button>
					</div>
				</section>

				<section id="jluxe-payment-column" class="jluxe-guided-checkout__payment-stage" data-guided-panel="payment" aria-labelledby="jluxe-guided-payment-title"<?php echo 'address' === $guided_initial_step ? ' hidden' : ''; ?>>
					<div class="jluxe-guided-checkout__payment-heading">
						<div>
							<h2 id="jluxe-guided-payment-title" class="jluxe-guided-checkout__stage-title"><span class="jluxe-guided-checkout__heading-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'credit-card', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>مرور سفارش و پرداخت</h2>
							<p>روش ارسال و درگاه پرداخت را از گزینه‌های واقعی فروشگاه انتخاب کنید.</p>
						</div>
						<button class="jluxe-guided-checkout__text-button" type="button" data-guided-edit-address>تغییر نشانی</button>
					</div>
					<div class="jluxe-guided-checkout__address-preview" data-guided-address-preview>
						<span class="jluxe-guided-checkout__address-preview-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'map-pin', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span>
						<div><strong data-guided-preview-recipient><?php echo esc_html( $guided_recipient ?: $guided_values['billing_phone'] ); ?></strong>
						<p data-guided-preview-text><?php echo esc_html( implode( '، ', array_filter( array( $guided_city_line, $guided_values['billing_address_1'], $guided_values['billing_postcode'] ) ) ) ); ?></p></div>
					</div>
					<?php woocommerce_checkout_payment(); ?>
				</section>
			</div>

			<aside class="jluxe-guided-checkout__summary" aria-label="خلاصه سفارش">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<?php $guided_summary_heading = jluxe_cart_checkout_copy( 'checkout_summary_heading', 'خلاصه سفارش' ); ?>
				<?php if ( '' !== trim( $guided_summary_heading ) ) : ?>
					<h2 class="jluxe-guided-checkout__summary-title"><span class="jluxe-guided-checkout__heading-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'package', 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span><?php echo esc_html( $guided_summary_heading ); ?></h2>
				<?php endif; ?>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
				<div id="order_review" class="woocommerce-checkout-review-order jluxe-guided-checkout__order-review">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

				<div id="jluxe-payment-submit" class="jluxe-guided-checkout__place-order"<?php echo 'address' === $guided_initial_step ? ' hidden' : ''; ?>>
					<div class="jluxe-guided-checkout__sticky-total" aria-live="polite">
						<span class="jluxe-guided-checkout__total-label"><span class="jluxe-guided-checkout__button-icon" aria-hidden="true"><?php echo jluxe_icon_svg_markup( 'wallet', 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from the theme's fixed SVG icon paths. ?></span><?php echo esc_html( jluxe_cart_checkout_required_copy( 'cart_total_label', 'مبلغ قابل پرداخت' ) ); ?></span>
						<strong data-guided-total><?php wc_cart_totals_order_total_html(); ?></strong>
					</div>
					<?php jluxe_render_payment_submit(); ?>
				</div>
			</aside>
		</div>

		<?php if ( $guided_map_enabled ) : ?>
			<input type="hidden" name="jluxe_delivery_latitude" data-map-latitude value="<?php echo esc_attr( $guided_coordinates['latitude'] ?? '' ); ?>" />
			<input type="hidden" name="jluxe_delivery_longitude" data-map-longitude value="<?php echo esc_attr( $guided_coordinates['longitude'] ?? '' ); ?>" />
		<?php endif; ?>
	</form>
</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
