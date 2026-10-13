<?php
/**
 * کارت‌های روش ارسال در چک‌اوت. رادیوهای واقعی ووکامرس حفظ شده‌اند تا
 * checkout.js و هوک‌های حمل‌ونقل همچنان کار کنند؛ انتخاب مشتری با رنگ کارت
 * نشان داده می‌شود و هیچ نرخ/روشی از پیش انتخاب نمی‌شود.
 *
 * جزئیات هر روش از تنظیمات پوسته می‌آیند؛ انتخاب نرخ و محاسبهٔ هزینه همچنان
 * کاملاً در اختیار خود ووکامرس است.
 *
 * @see woocommerce/templates/cart/cart-shipping.php (نسخهٔ اصلی)
 * @version 8.8.0
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';
?>
<div class="jluxe-shipping-methods">
	<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) { ?>
		<ul id="shipping_method" class="jluxe-shipping-method-list">
			<?php foreach ( $available_methods as $method ) : ?>
				<li class="jluxe-shipping-method-item">
					<?php
					$input_id = 'shipping_method_' . $index . '_' . sanitize_title( $method->id );
					$presentation = function_exists( 'jluxe_shipping_method_presentation' )
						? jluxe_shipping_method_presentation( $method )
						: array( 'kind' => 'other' );
					$kind = $presentation['kind'] ?? 'other';
					$method_copy = function_exists( 'jluxe_cart_checkout_shipping_method_settings' )
						? jluxe_cart_checkout_shipping_method_settings( $kind )
						: array( 'enabled' => false, 'summary' => '', 'description' => '' );
					$show_method_details = jluxe_cart_checkout_option( 'show_shipping_accordions', true )
						&& ! empty( $method_copy['enabled'] )
						&& '' !== trim( (string) $method_copy['summary'] )
						&& '' !== trim( (string) $method_copy['description'] );
					printf(
						'<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="%2$s" value="%3$s" class="shipping_method sr-only" data-jluxe-shipping-kind="%4$s" />',
						$index,
						esc_attr( $input_id ),
						esc_attr( $method->id ),
						esc_attr( $presentation['kind'] )
					);
					?>
						<label for="<?php echo esc_attr( $input_id ); ?>" class="jluxe-shipping-card jluxe-shipping-card--<?php echo esc_attr( $kind ); ?>">
							<span class="jluxe-shipping-card__details">
								<span class="jluxe-shipping-card__label"><?php echo wc_cart_totals_shipping_method_label( $method ); // phpcs:ignore ?></span>
								<?php if ( isset( $method_copy['note'] ) && is_scalar( $method_copy['note'] ) && '' !== trim( (string) $method_copy['note'] ) ) : ?>
									<span class="jluxe-shipping-note jluxe-shipping-note--<?php echo esc_attr( $kind ); ?>" style="--jluxe-shipping-note-bg: <?php echo esc_attr( $method_copy['note_background_color'] ); ?>; --jluxe-shipping-note-color: <?php echo esc_attr( $method_copy['note_text_color'] ); ?>;"><?php echo esc_html( trim( (string) $method_copy['note'] ) ); ?></span>
								<?php endif; ?>
							</span>
						</label>
						<?php if ( $show_method_details ) : ?>
							<details class="jluxe-shipping-method-accordion jluxe-shipping-method-accordion--<?php echo esc_attr( $kind ); ?>" style="--jluxe-shipping-details-bg: <?php echo esc_attr( $method_copy['details_background_color'] ); ?>; --jluxe-shipping-details-color: <?php echo esc_attr( $method_copy['details_text_color'] ); ?>;" open>
							<summary>
								<span><?php echo esc_html( $method_copy['summary'] ); ?></span>
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</summary>
							<div class="jluxe-shipping-method-accordion__content"><?php echo nl2br( esc_html( $method_copy['description'] ) ); ?></div>
						</details>
					<?php endif; ?>
					<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( is_cart() ) { ?>
			<p class="mt-2 text-caption text-text-muted">
				<?php
				if ( $formatted_destination ) {
					printf( esc_html__( 'Shipping to %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' );
					$calculator_text = esc_html__( 'Change address', 'woocommerce' );
				} else {
					echo wp_kses_post( apply_filters( 'woocommerce_shipping_estimate_html', __( 'Shipping options will be updated during checkout.', 'woocommerce' ) ) );
				}
				?>
			</p>
		<?php } ?>
	<?php } elseif ( ! $has_calculated_shipping || ! $formatted_destination ) {
		if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) {
			$cart_shipping_notice = apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', jluxe_cart_checkout_copy( 'cart_shipping_notice_text', '' ) );
			if ( jluxe_cart_checkout_option( 'cart_shipping_notice_enabled', false ) && is_scalar( $cart_shipping_notice ) && '' !== trim( wp_strip_all_tags( (string) $cart_shipping_notice ) ) ) {
				echo '<p class="text-small text-text-muted">' . nl2br( esc_html( $cart_shipping_notice ) ) . '</p>';
			}
		} else {
			echo '<p class="text-small text-text-muted">' . wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'woocommerce' ) ) ) . '</p>';
		}
	} elseif ( ! is_cart() ) {
		$no_shipping_message = apply_filters(
			'woocommerce_no_shipping_available_html',
			jluxe_cart_checkout_copy( 'checkout_no_shipping_options', 'برای این نشانی، روش ارسالی در دسترس نیست. آدرس را بررسی کنید یا با پشتیبانی تماس بگیرید.' )
		);
		if ( is_scalar( $no_shipping_message ) && '' !== trim( wp_strip_all_tags( (string) $no_shipping_message ) ) ) {
			echo '<p class="text-small text-text-muted">' . wp_kses_post( (string) $no_shipping_message ) . '</p>';
		}
	} else {
		echo '<p class="text-small text-text-muted">' . wp_kses_post(
			apply_filters(
				'woocommerce_cart_no_shipping_available_html',
				sprintf( esc_html__( 'No shipping options were found for %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' ),
				$formatted_destination
			)
		) . '</p>';
		$calculator_text = esc_html__( 'Enter a different address', 'woocommerce' );
	}
	?>
	<?php if ( $show_package_details ) : ?>
		<p class="mt-1 text-caption text-text-muted"><?php echo esc_html( $package_details ); ?></p>
	<?php endif; ?>

	<?php if ( $show_shipping_calculator ) : ?>
		<?php woocommerce_shipping_calculator( $calculator_text ); ?>
	<?php endif; ?>
</div>
