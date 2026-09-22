<?php
/**
 * روش‌های ارسال — کارت سبز/تیک سبز به‌جای دایره‌ی رادیوی خام. توسط هم
 * سبد خرید و هم چک‌اوت استفاده می‌شه (wc_cart_totals_shipping_html در هر
 * دو همین قالب رو صدا می‌زنه).
 *
 * input[type=radio] واقعی رو نگه می‌داریم (فقط بصری مخفی، sr-only) چون
 * assets/js/frontend/cart.js و checkout.js ووکامرس روی change شدن همین
 * inputها گوش می‌دن؛ استایل کارت با CSS ساده (input:checked + label) روی
 * label اعمال می‌شه، نه با ری‌ایمپلمنت جاوااسکریپت.
 *
 * از این‌جا به بعد این تمپلیت فقط توی چک‌اوت صدا زده می‌شه (نه توی سبد
 * خرید — طبق درخواست کاربر، انتخاب روش ارسال از صفحه‌ی سبد حذف شده،
 * woocommerce/cart/cart-totals.php دیگه wc_cart_totals_shipping_html()
 * رو صدا نمی‌زنه). طبق درخواست صریح («روش ارسال نباید از پیش تعیین شده
 * باشه، حتماً باید خود مشتری انتخاب کنه») حتی وقتی فقط یک روش ارسال
 * وجود داره، به‌صورت رادیوی واقعاً کلیک‌پذیر و از ابتدا تیک‌نخورده رندر
 * می‌شه — نه input مخفیِ همیشه-انتخاب‌شده‌ی قبلی. assets/js/woocommerce.js
 * قبل از رفتن به مرحله‌ی «نحوه پرداخت» چک می‌کنه حتماً یکی از این
 * رادیوها checked باشه.
 *
 * @see woocommerce/templates/cart/cart-shipping.php (نسخه‌ی اصلی)
 * @version 8.8.0
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';
?>
<div class="jluxe-shipping-methods">
	<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
		<ul id="shipping_method" class="flex flex-col gap-2">
			<?php foreach ( $available_methods as $method ) : ?>
				<li>
					<?php
					$input_id = 'shipping_method_' . $index . '_' . sanitize_title( $method->id );
					printf(
						'<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="%2$s" value="%3$s" class="shipping_method sr-only" />',
						$index,
						esc_attr( $input_id ),
						esc_attr( $method->id )
					);
					?>
					<label for="<?php echo esc_attr( $input_id ); ?>" class="jluxe-shipping-card flex cursor-pointer items-center justify-between gap-2 rounded-2xl p-3 text-start transition-colors hover:bg-muted">
						<span class="text-small font-medium text-foreground"><?php echo wc_cart_totals_shipping_method_label( $method ); // phpcs:ignore ?></span>
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="jluxe-check-on hidden shrink-0 text-success" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
						<span class="jluxe-check-off block size-5 shrink-0 rounded-full border-2 border-border"></span>
					</label>
					<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( is_cart() ) : ?>
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
		<?php endif; ?>
		<?php
	elseif ( ! $has_calculated_shipping || ! $formatted_destination ) :
		if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) {
			echo '<p class="text-small text-text-muted">' . wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'woocommerce' ) ) ) . '</p>';
		} else {
			echo '<p class="text-small text-text-muted">' . wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'woocommerce' ) ) ) . '</p>';
		}
	elseif ( ! is_cart() ) :
		echo '<p class="text-small text-text-muted">' . wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) ) . '</p>';
	else :
		echo '<p class="text-small text-text-muted">' . wp_kses_post(
			apply_filters(
				'woocommerce_cart_no_shipping_available_html',
				sprintf( esc_html__( 'No shipping options were found for %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' ),
				$formatted_destination
			)
		) . '</p>';
		$calculator_text = esc_html__( 'Enter a different address', 'woocommerce' );
	endif;
	?>

	<?php if ( $show_package_details ) : ?>
		<p class="mt-1 text-caption text-text-muted"><?php echo esc_html( $package_details ); ?></p>
	<?php endif; ?>

	<?php if ( $show_shipping_calculator ) : ?>
		<?php woocommerce_shipping_calculator( $calculator_text ); ?>
	<?php endif; ?>
</div>
