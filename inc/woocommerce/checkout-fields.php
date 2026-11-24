<?php
defined( 'ABSPATH' ) || exit;

/** Cart, checkout, account addresses, validation and checkout assets. */

/** Read configurable cart/checkout copy while preserving an intentionally blank value. */
function jluxe_cart_checkout_copy( string $key, string $fallback = '' ): string {
	$value = jluxe_get_setting( 'cart_checkout.copy.' . $key, null );
	return null === $value || ! is_scalar( $value ) ? $fallback : (string) $value;
}

/** Required controls keep a readable label even if an administrator clears their text field. */
function jluxe_cart_checkout_required_copy( string $key, string $fallback ): string {
	$value = trim( jluxe_cart_checkout_copy( $key, $fallback ) );
	return '' !== $value ? $value : $fallback;
}

/** Boolean presentation switches never touch cart, address, shipping, tax, or payment calculations. */
function jluxe_cart_checkout_option( string $key, bool $fallback = false ): bool {
	return (bool) jluxe_get_setting( 'cart_checkout.' . $key, $fallback );
}

/** Gift-wrap labels, price, and note permissions always come from sanitized server settings. */
function jluxe_get_gift_wrap_config(): array {
	$settings = jluxe_get_theme_settings()['cart_checkout'] ?? array();
	$label = is_scalar( $settings['gift_wrap_label'] ?? null ) ? (string) $settings['gift_wrap_label'] : 'بسته‌بندی هدیه ویژه';
	$fee = is_numeric( $settings['gift_wrap_fee'] ?? null ) ? (float) $settings['gift_wrap_fee'] : 0.0;
	$description = is_scalar( $settings['gift_wrap_description'] ?? null ) ? (string) $settings['gift_wrap_description'] : '';
	return array(
		'enabled' => ! empty( $settings['gift_wrap_enabled'] ),
		'label' => jluxe_substr( sanitize_text_field( $label ), 0, 80 ),
		'fee' => max( 0, $fee ),
		'description' => jluxe_substr( sanitize_text_field( $description ), 0, 240 ),
		'note_enabled' => ! empty( $settings['gift_wrap_note_enabled'] ),
	);
}

/** Return the session state in a small, sanitized, stable shape. */
function jluxe_get_session_gift_wrap(): array {
	$wc = function_exists( 'WC' ) ? WC() : null;
	if ( ! is_object( $wc ) || ! isset( $wc->session ) || ! is_object( $wc->session ) || ! method_exists( $wc->session, 'get' ) ) { return array( 'enabled' => false, 'note' => '' ); }
	$stored = $wc->session->get( 'jluxe_gift_wrap', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$config = jluxe_get_gift_wrap_config();
	$enabled = $config['enabled'] && ! empty( $stored['enabled'] );
	$note = $enabled && $config['note_enabled'] && is_scalar( $stored['note'] ?? null )
		? jluxe_substr( sanitize_textarea_field( (string) $stored['note'] ), 0, 200 )
		: '';
	return array( 'enabled' => $enabled, 'note' => $note );
}

/** Store only the selected state and note; never accept a fee or label from a request. */
function jluxe_set_session_gift_wrap( bool $enabled, string $note = '' ): array {
	$config = jluxe_get_gift_wrap_config();
	$enabled = $enabled && $config['enabled'];
	$note = $enabled && $config['note_enabled'] ? jluxe_substr( sanitize_textarea_field( $note ), 0, 200 ) : '';
	$state = array( 'enabled' => $enabled, 'note' => $note );
	$wc = function_exists( 'WC' ) ? WC() : null;
	if ( is_object( $wc ) && isset( $wc->session ) && is_object( $wc->session ) && method_exists( $wc->session, 'set' ) ) { $wc->session->set( 'jluxe_gift_wrap', $state ); }
	return $state;
}

/** Read native checkout fields or WooCommerce's serialized update_order_review payload. */
function jluxe_sync_gift_wrap_request( $raw_post_data = null ): ?array {
	$data = wp_unslash( $_POST );
	if ( is_string( $raw_post_data ) && '' !== $raw_post_data ) { parse_str( wp_unslash( $raw_post_data ), $data ); }
	if ( ! is_array( $data ) || ( ! array_key_exists( 'jluxe_gift_wrap_present', $data ) && ! array_key_exists( 'jluxe_gift_wrap_note', $data ) && ! array_key_exists( 'jluxe_gift_wrap', $data ) ) ) { return null; }
	$config = jluxe_get_gift_wrap_config();
	$enabled = $config['enabled'] && isset( $data['jluxe_gift_wrap'] ) && is_scalar( $data['jluxe_gift_wrap'] ) && '1' === (string) $data['jluxe_gift_wrap'];
	$note = isset( $data['jluxe_gift_wrap_note'] ) && is_scalar( $data['jluxe_gift_wrap_note'] ) ? (string) $data['jluxe_gift_wrap_note'] : '';
	return jluxe_set_session_gift_wrap( $enabled, $note );
}

/** Render the same gift option on cart and checkout without adding a nested form. */
function jluxe_render_gift_wrap_box(): void {
	$config = jluxe_get_gift_wrap_config();
	if ( ! $config['enabled'] || ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) ) { return; }
	$state = jluxe_get_session_gift_wrap();
	$price = $config['fee'] > 0 ? wp_kses_post( wc_price( $config['fee'] ) ) : 'رایگان';
	$id = 'jluxe-gift-wrap-enabled';
	?>
	<section class="jluxe-gift-wrap-card" data-jluxe-gift-wrap aria-labelledby="jluxe-gift-wrap-title">
		<input type="hidden" name="jluxe_gift_wrap_present" value="1" />
		<label class="jluxe-gift-wrap-card__choice" for="<?php echo esc_attr( $id ); ?>">
			<input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="jluxe_gift_wrap" value="1" data-jluxe-gift-enabled <?php checked( $state['enabled'] ); ?> />
			<span class="jluxe-gift-wrap-card__copy"><strong id="jluxe-gift-wrap-title"><?php echo esc_html( $config['label'] ); ?></strong><span><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is WooCommerce-formatted safe markup. ?></span></span>
		</label>
		<?php if ( '' !== $config['description'] ) : ?><p class="jluxe-gift-wrap-card__description"><?php echo esc_html( $config['description'] ); ?></p><?php endif; ?>
		<?php if ( $config['note_enabled'] ) : ?>
			<div class="jluxe-gift-wrap-card__note" data-jluxe-gift-note-wrap>
				<label for="jluxe-gift-wrap-note">متن کارت یادداشت <span>(اختیاری)</span></label>
				<textarea id="jluxe-gift-wrap-note" name="jluxe_gift_wrap_note" maxlength="200" rows="3" data-jluxe-gift-note aria-describedby="jluxe-gift-wrap-count"><?php echo esc_textarea( $state['note'] ); ?></textarea>
				<span id="jluxe-gift-wrap-count" class="jluxe-gift-wrap-card__count" data-jluxe-gift-count aria-live="polite"><?php echo esc_html( jluxe_fa_digits( jluxe_strlen( $state['note'] ) ) ); ?> / ۲۰۰</span>
			</div>
		<?php endif; ?>
		<p class="screen-reader-text" data-jluxe-gift-status aria-live="polite"></p>
	</section>
	<?php
}

/** Add the configured, non-taxable gift fee; client requests cannot set its amount or label. */
function jluxe_apply_gift_wrap_fee(): void {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
	$wc = function_exists( 'WC' ) ? WC() : null;
	if ( ! is_object( $wc ) || ! isset( $wc->cart, $wc->session ) || ! is_object( $wc->cart ) || ! is_object( $wc->session ) || ! method_exists( $wc->cart, 'is_empty' ) || $wc->cart->is_empty() ) { return; }
	$config = jluxe_get_gift_wrap_config();
	$state = jluxe_get_session_gift_wrap();
	if ( $config['enabled'] && $state['enabled'] && $config['fee'] > 0 ) { WC()->cart->add_fee( $config['label'], $config['fee'], false ); }
}

function jluxe_clear_gift_wrap_session(): void {
	$wc = function_exists( 'WC' ) ? WC() : null;
	if ( is_object( $wc ) && isset( $wc->session ) && is_object( $wc->session ) && method_exists( $wc->session, '__unset' ) ) { $wc->session->__unset( 'jluxe_gift_wrap' ); }
}

/** Persist gift metadata only after a validated checkout; fees and labels remain settings-owned. */
function jluxe_checkout_store_gift_wrap( $order, array $data = array() ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) { return; }
	jluxe_sync_gift_wrap_request();
	$state = jluxe_get_session_gift_wrap();
	if ( ! $state['enabled'] ) { return; }
	$order->update_meta_data( '_jluxe_gift_wrap', 'yes' );
	if ( '' !== $state['note'] ) { $order->update_meta_data( '_jluxe_gift_wrap_note', $state['note'] ); }
	elseif ( method_exists( $order, 'delete_meta_data' ) ) { $order->delete_meta_data( '_jluxe_gift_wrap_note' ); }
}

function jluxe_render_order_gift_wrap_details( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) || 'yes' !== $order->get_meta( '_jluxe_gift_wrap', true ) ) { return; }
	$note = sanitize_textarea_field( (string) $order->get_meta( '_jluxe_gift_wrap_note', true ) );
	echo '<section class="jluxe-order-gift-wrap"><h2>بسته‌بندی هدیه</h2><p><strong>بسته‌بندی هدیه:</strong> بله</p>';
	if ( '' !== $note ) { echo '<div><strong>متن کارت یادداشت:</strong><p>' . nl2br( esc_html( $note ) ) . '</p></div>'; }
	echo '</section>';
}

function jluxe_email_order_gift_wrap_details( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) || 'yes' !== $order->get_meta( '_jluxe_gift_wrap', true ) ) { return; }
	$note = sanitize_textarea_field( (string) $order->get_meta( '_jluxe_gift_wrap_note', true ) );
	if ( $plain_text ) { echo "\nبسته‌بندی هدیه: بله\n" . ( '' !== $note ? "متن کارت: {$note}\n" : '' ); return; }
	jluxe_render_order_gift_wrap_details( $order );
}

function jluxe_admin_order_gift_wrap_details( $order ): void { jluxe_render_order_gift_wrap_details( $order ); }

function jluxe_checkout_export_gift_wrap( array $personal_data, $order ): array {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) || 'yes' !== $order->get_meta( '_jluxe_gift_wrap', true ) ) { return $personal_data; }
	$personal_data[] = array( 'name' => 'Gift wrapping', 'value' => 'Yes' );
	$note = sanitize_textarea_field( (string) $order->get_meta( '_jluxe_gift_wrap_note', true ) );
	if ( '' !== $note ) { $personal_data[] = array( 'name' => 'Gift card note', 'value' => $note ); }
	return $personal_data;
}

function jluxe_checkout_erase_gift_wrap( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'delete_meta_data' ) ) { return; }
	$order->delete_meta_data( '_jluxe_gift_wrap' );
	$order->delete_meta_data( '_jluxe_gift_wrap_note' );
	if ( method_exists( $order, 'save_meta_data' ) ) { $order->save_meta_data(); }
}

/** Return the allow-listed checkout layout; missing or corrupted settings preserve the released classic flow. */
function jluxe_checkout_layout(): string {
	$layout = jluxe_get_setting( 'cart_checkout.checkout_layout', 'classic' );
	$layout = is_scalar( $layout ) ? sanitize_key( (string) $layout ) : 'classic';
	return in_array( $layout, array( 'classic', 'guided' ), true ) ? $layout : 'classic';
}

/**
 * Keep WooCommerce's bound-to-native coupon form available for the guided
 * payment-step proxy, but omit the default pre-checkout placement. The guided
 * template renders that native form outside the checkout form to avoid nested
 * forms; the classic checkout keeps WooCommerce's original placement intact.
 */
function jluxe_defer_guided_checkout_coupon_form(): void {
	if ( 'guided' !== jluxe_checkout_layout() ) {
		return;
	}
	if ( function_exists( 'is_checkout' ) && ! is_checkout() ) {
		return;
	}
	remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
}

/** Validate a single decimal coordinate and normalize it to six places. */
function jluxe_checkout_map_coordinate( $value, float $minimum, float $maximum ): ?string {
	if ( ! is_scalar( $value ) ) {
		return null;
	}
	$value = trim( (string) $value );
	if ( '' === $value || strlen( $value ) > 24 || ! preg_match( '/^-?(?:[0-9]+(?:\\.[0-9]+)?|\\.[0-9]+)$/D', $value ) ) {
		return null;
	}
	$number = (float) $value;
	if ( ! is_finite( $number ) || $number < $minimum || $number > $maximum ) {
		return null;
	}
	$number = round( $number, 6 );
	if ( 0.0 === $number ) {
		$number = 0.0;
	}
	return number_format( $number, 6, '.', '' );
}

/** Coordinates are optional, separate from WooCommerce's text address, and never used in shipping calculations. */
function jluxe_checkout_map_coordinates_from_request(): ?array {
	if ( 'guided' !== jluxe_checkout_layout() || ! jluxe_cart_checkout_option( 'checkout_map_enabled', true ) ) {
		return null;
	}
	if ( ! isset( $_POST['jluxe_delivery_latitude'], $_POST['jluxe_delivery_longitude'] ) ) {
		return null;
	}
	$latitude = is_scalar( $_POST['jluxe_delivery_latitude'] ) ? wp_unslash( (string) $_POST['jluxe_delivery_latitude'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Optional map metadata is validated and stored only after WooCommerce validates its checkout nonce.
	$longitude = is_scalar( $_POST['jluxe_delivery_longitude'] ) ? wp_unslash( (string) $_POST['jluxe_delivery_longitude'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
	$latitude = jluxe_checkout_map_coordinate( $latitude, -90.0, 90.0 );
	$longitude = jluxe_checkout_map_coordinate( $longitude, -180.0, 180.0 );
	if ( null === $latitude || null === $longitude ) {
		return null;
	}
	return array( 'latitude' => $latitude, 'longitude' => $longitude );
}

/** Save only validated map coordinates as private order metadata; the address/rates remain WooCommerce-owned. */
function jluxe_checkout_store_map_coordinates( $order, array $data = array() ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
		return;
	}
	$coordinates = jluxe_checkout_map_coordinates_from_request();
	if ( null !== $coordinates ) {
		$order->update_meta_data(
			'_jluxe_delivery_coordinates',
			array(
				'latitude'  => (float) $coordinates['latitude'],
				'longitude' => (float) $coordinates['longitude'],
			)
		);
	}
}

/** Show the optional delivery pin to store staff in the order admin. */
function jluxe_admin_order_delivery_map_location( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return;
	}
	$coordinates = $order->get_meta( '_jluxe_delivery_coordinates', true );
	if ( ! is_array( $coordinates ) ) {
		return;
	}
	$latitude = jluxe_checkout_map_coordinate( $coordinates['latitude'] ?? null, -90.0, 90.0 );
	$longitude = jluxe_checkout_map_coordinate( $coordinates['longitude'] ?? null, -180.0, 180.0 );
	if ( null === $latitude || null === $longitude ) {
		return;
	}
	$url = add_query_arg(
		array( 'mlat' => $latitude, 'mlon' => $longitude, 'zoom' => 16 ),
		'https://www.openstreetmap.org/'
	);
	echo '<p class="jluxe-order-map-location"><strong>' . esc_html__( 'موقعیت انتخاب‌شده برای تحویل:', 'jluxe' ) . '</strong> <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $latitude . ', ' . $longitude ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped values are assembled into fixed HTML.
}

/** Include the optional map pin in WooCommerce's personal-data export. */
function jluxe_checkout_export_delivery_map_location( array $personal_data, $order ): array {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return $personal_data;
	}
	$coordinates = $order->get_meta( '_jluxe_delivery_coordinates', true );
	if ( ! is_array( $coordinates ) ) {
		return $personal_data;
	}
	$latitude = jluxe_checkout_map_coordinate( $coordinates['latitude'] ?? null, -90.0, 90.0 );
	$longitude = jluxe_checkout_map_coordinate( $coordinates['longitude'] ?? null, -180.0, 180.0 );
	if ( null !== $latitude && null !== $longitude ) {
		$personal_data[] = array(
			'name'  => __( 'Delivery map coordinates', 'jluxe' ),
			'value' => $latitude . ', ' . $longitude,
		);
	}
	return $personal_data;
}

/** Remove the optional map pin when an order's personal data is erased. */
function jluxe_checkout_erase_delivery_map_location( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'delete_meta_data' ) ) {
		return;
	}
	$order->delete_meta_data( '_jluxe_delivery_coordinates' );
	if ( method_exists( $order, 'save_meta_data' ) ) {
		$order->save_meta_data();
	}
}

/** Read a presentation color only after validating it as a six/three-digit hex value. */
function jluxe_cart_checkout_color( string $key, string $fallback ): string {
	$value = jluxe_get_setting( 'cart_checkout.' . $key, $fallback );
	$color = sanitize_hex_color( is_scalar( $value ) ? (string) $value : $fallback );
	return is_string( $color ) && '' !== $color ? $color : $fallback;
}

/** Configurable explanatory copy and presentation for a carrier; never calculates or changes a rate. */
function jluxe_cart_checkout_shipping_method_settings( string $kind ): array {
	if ( ! in_array( $kind, array( 'post', 'tipax', 'chapar' ), true ) ) {
		return array(
			'enabled' => false,
			'summary' => '',
			'description' => '',
			'note' => '',
			'icon_background_color' => '#F1F5F9',
			'icon_text_color' => '#475569',
			'card_background_color' => '#FFFFFF',
			'card_border_color' => '#F5D1FA',
			'selected_card_background_color' => '#FAE8FD',
			'selected_card_border_color' => '#A313B9',
			'note_background_color' => '#FFFFFF',
			'note_text_color' => '#1F2937',
			'details_background_color' => '#FFFFFF',
			'details_text_color' => '#475569',
		);
	}

	$settings = jluxe_get_setting( 'cart_checkout.shipping_methods.' . $kind, array() );
	$all_defaults = jluxe_theme_settings_defaults();
	$defaults = $all_defaults['cart_checkout']['shipping_methods'][ $kind ] ?? array();
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$read_text = static function ( string $key ) use ( $settings, $defaults ): string {
		$value = $settings[ $key ] ?? $defaults[ $key ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	};
	$read_color = static function ( string $key ) use ( $settings, $defaults ): string {
		$fallback = isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) ? (string) $defaults[ $key ] : '#FFFFFF';
		$value = $settings[ $key ] ?? $fallback;
		$color = sanitize_hex_color( is_scalar( $value ) ? (string) $value : $fallback );
		return is_string( $color ) && '' !== $color ? $color : $fallback;
	};

	return array(
		'enabled'                  => ! empty( $settings['enabled'] ),
		'summary'                  => $read_text( 'summary' ),
		'description'              => $read_text( 'description' ),
		'note'                     => $read_text( 'note' ),
		'icon_background_color'    => $read_color( 'icon_background_color' ),
		'icon_text_color'          => $read_color( 'icon_text_color' ),
		'card_background_color'    => $read_color( 'card_background_color' ),
		'card_border_color'        => $read_color( 'card_border_color' ),
		'selected_card_background_color' => $read_color( 'selected_card_background_color' ),
		'selected_card_border_color' => $read_color( 'selected_card_border_color' ),
		'note_background_color'    => $read_color( 'note_background_color' ),
		'note_text_color'          => $read_color( 'note_text_color' ),
		'details_background_color' => $read_color( 'details_background_color' ),
		'details_text_color'       => $read_color( 'details_text_color' ),
	);
}

/** Override only the empty-cart copy when its explicit presentation switch is enabled. */
function jluxe_cart_empty_message( string $message ): string {
	if ( ! jluxe_cart_checkout_option( 'cart_empty_message_enabled', false ) ) {
		return $message;
	}
	return esc_html( jluxe_cart_checkout_copy( 'cart_empty_message', '' ) );
}

/**
 * دکمه‌ی «پرداخت» (ثبت نهاییِ سفارش) + شرایط/nonce — طبقِ درخواستِ صریحِ
 * بعدیِ کاربر («دکمه‌ی پرداخت رو ببر زیرِ بازگشت، سمتِ چپ») این بخش دیگه
 * زیرِ گریدِ درگاه‌ها (woocommerce/checkout/payment.php) نیست؛ در سایدبارِ
 * باریک، بعدِ #order_review و دکمه‌ی «بازگشت»ِ ساخته‌شده‌ی
 * assets/js/woocommerce.js صدا زده می‌شه (form-checkout.php).
 *
 * قبلاً این محتوا بخشی از woocommerce_checkout_payment() بود؛ چون دیگه
 * اون تابع رو صدا نمی‌زنیم (به remove_action بالا مراجعه بشه)، این‌جا
 * مستقل خودمون $order_button_text رو با همون فیلترِ رسمیِ ووکامرس
 * می‌سازیم — منطق/nonce/دکمه دقیقاً همونیه که خودِ ووکامرس تولید می‌کرد،
 * فقط جای رندرش عوض شده.
 */
function jluxe_render_payment_submit(): void {
	if ( ! WC()->cart ) {
		return;
	}
	$order_button_text = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
	?>
	<div class="form-row place-order mt-4">
		<noscript>
			<?php
			printf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ), '<em>', '</em>' );
			?>
			<br/><button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<div class="mt-3">
			<?php wc_get_template( 'checkout/terms.php' ); ?>
		</div>

		<?php $payment_error = jluxe_cart_checkout_copy( 'checkout_payment_error', 'روش پرداخت را انتخاب کنید.' ); ?>
		<?php if ( '' !== trim( $payment_error ) ) : ?>
			<p data-jluxe-payment-error hidden class="mb-2 text-caption text-error"><?php echo esc_html( $payment_error ); ?></p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<div class="jluxe-checkout-place-order-row jluxe-checkout-action-row">
			<button type="button" data-jluxe-checkout-back hidden class="jluxe-checkout-action-button jluxe-checkout-action-button--secondary"><?php echo esc_html( jluxe_cart_checkout_required_copy( 'checkout_back_button', 'بازگشت' ) ); ?></button>
			<div class="jluxe-checkout-place-order-slot">
				<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="jluxe-checkout-action-button jluxe-checkout-action-button--primary" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
	<?php
}

/** Presentation-only classification for shipping-rate cards; never changes the WooCommerce rate or its cost. */
function jluxe_shipping_method_presentation( $method ): array {
	$label = '';
	if ( is_object( $method ) ) {
		if ( method_exists( $method, 'get_label' ) ) {
			$label = (string) $method->get_label();
		} elseif ( isset( $method->label ) ) {
			$label = (string) $method->label;
		}
	}

	$identifiers = array( $label );
	if ( is_scalar( $method ) ) {
		$identifiers[] = (string) $method;
	}
	foreach ( array( 'id', 'method_id', 'instance_id' ) as $property ) {
		if ( is_object( $method ) && isset( $method->{$property} ) && is_scalar( $method->{$property} ) ) {
			$identifiers[] = (string) $method->{$property};
		}
	}
	if ( is_object( $method ) && method_exists( $method, 'get_id' ) ) {
		$identifiers[] = (string) $method->get_id();
	}
	$identity = implode( ' ', $identifiers );

	if ( preg_match( '/تیپاکس|tipax/iu', $identity ) ) {
		return array( 'kind' => 'tipax' );
	}
	if ( preg_match( '/چاپار|chapar/iu', $identity ) ) {
		return array( 'kind' => 'chapar' );
	}
	if ( preg_match( '/پست|پیشتاز|post|pishtaz/iu', $identity ) ) {
		return array( 'kind' => 'post' );
	}

	return array( 'kind' => 'other' );
}

/**
 * نمایش استپر بالای صفحه‌ی سبد/تسویه‌حساب — نشانگر بصریِ مرحله‌ی جاری.
 *
 * خودِ چک‌اوتِ ووکامرس یک صفحه‌ست (فرمِ آدرس + روشِ ارسال + روشِ پرداخت
 * همه با هم رندر می‌شن)، ولی طبقِ درخواستِ کاربر باید واقعاً حسِ ۲ مرحله‌ی
 * جدا (ابتدا اطلاعاتِ ارسال، بعد از زدنِ «ادامه» فقط وقتی به روشِ پرداخت
 * برسه) بده — نه اینکه پرداخت از همون اول کنارِ فرمِ ارسال دیده بشه (باگِ
 * واقعیِ گزارش‌شده). چون یک state machineِ واقعی روی سمتِ سرور وجود نداره
 * (این تابع یک‌بار، با یک active_id ثابت، رندر می‌شه)، assets/js/woocommerce.js
 * بعداً همینِ استپر رو (با همین data-jluxe-step هایی که این‌جا چاپ می‌شن)
 * وقتی کاربر دکمه‌ی «ادامه» رو می‌زنه، به‌صورتِ زنده از «shipping» به
 * «payment» سوییچ می‌کنه — بدونِ رفرشِ صفحه یا رندرِ دوباره‌ی سمتِ سرور.
 */

function jluxe_render_checkout_stepper( string $active_id ): void {
	if ( ! jluxe_cart_checkout_option( 'show_stepper', true ) ) {
		return;
	}
	$steps = array(
		'cart'     => jluxe_cart_checkout_copy( 'checkout_step_cart', 'بررسی سبد خرید' ),
		'shipping' => jluxe_cart_checkout_copy( 'checkout_step_shipping', 'اطلاعات ارسال' ),
		'payment'  => jluxe_cart_checkout_copy( 'checkout_step_payment', 'نحوه پرداخت' ),
		'done'     => jluxe_cart_checkout_copy( 'checkout_step_done', 'پایان خرید' ),
	);
	$ids   = array_keys( $steps );
	$active_index = array_search( $active_id, $ids, true );
	if ( false === $active_index ) {
		$active_index = 0;
	}
	?>
	<nav aria-label="مراحل خرید" class="jluxe-checkout-stepper">
		<ol class="jluxe-checkout-stepper__list">
			<?php foreach ( $ids as $i => $id ) : ?>
				<?php
				// آخرین مرحله («پایان خرید») فقط از صفحه‌ی thankyou.php صدا زده
				// می‌شه، یعنی همیشه با یک سفارشِ واقعاً ثبت‌شده — پس وقتی این
				// مرحله «فعال»ه، در واقع کامل‌شده‌ست، نه در حال انجام؛ باید مثل
				// بقیه‌ی مراحلِ done دایره‌ی سبز/تیک بگیره، نه استایلِ «فعلی».
				$is_last    = $i === count( $ids ) - 1;
				$is_active  = $i === $active_index;
				$is_done    = $i < $active_index || ( $is_active && $is_last );
				$is_active  = $is_active && ! $is_done;
				$step_state = $is_done ? 'done' : ( $is_active ? 'active' : 'pending' );
				?>
				<li class="jluxe-checkout-step" data-jluxe-step="<?php echo esc_attr( $id ); ?>" data-jluxe-step-state="<?php echo esc_attr( $step_state ); ?>"<?php echo $is_active ? ' aria-current="step"' : ''; ?>>
					<span data-jluxe-step-circle data-jluxe-step-number="<?php echo esc_attr( jluxe_fa_digits( (string) ( $i + 1 ) ) ); ?>" class="jluxe-checkout-step__circle border-2 <?php echo $is_done ? 'border-success bg-success text-white' : ( $is_active ? 'border-primary text-primary' : 'border-border text-text-muted' ); ?>" aria-hidden="true">
						<?php if ( $is_done ) : ?>
							<svg class="size-4 sm:size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
						<?php else : ?>
							<span class="text-caption font-bold"><?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( '' !== trim( $steps[ $id ] ) ) : ?>
						<span data-jluxe-step-label class="jluxe-checkout-step__label <?php echo $is_active ? 'font-medium text-foreground' : 'text-text-muted'; ?>">
							<?php echo esc_html( $steps[ $id ] ); ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/**
 * Comparison-only normalization for Persian city names. The original label and
 * stored WooCommerce value are never rewritten; this only matches ZWNJ/space,
 * hyphen, Arabic Yeh/Kaf and Tatweel variants.
 */
function jluxe_iran_city_name_key( $city ): string {
	if ( ! is_scalar( $city ) ) {
		return '';
	}
	$city = str_replace( array( 'ي', 'ك', 'ـ' ), array( 'ی', 'ک', '' ), (string) $city );
	$city = preg_replace( '/[\s\p{Z}\x{002D}\x{200C}\x{200D}\x{2060}\x{FEFF}\x{2010}-\x{2015}\x{2212}]+/u', '', $city );
	return is_string( $city ) ? trim( $city ) : '';
}

/**
 * WooCommerce IR-state city map. The original theme values remain first as a
 * fallback and to preserve legacy labels; the shared, province-name keyed data
 * in inc/data/iran-cities.json is mapped to WooCommerce's canonical state codes.
 */
function jluxe_iran_cities(): array {
	static $cities = null;
	if ( null !== $cities ) {
		return $cities;
	}

	$cities = array(
		'THR' => array( 'تهران', 'ری', 'شمیرانات', 'اسلامشهر', 'پاکدشت', 'ورامین', 'شهریار', 'رباط‌کریم', 'پیشوا', 'فیروزکوه', 'دماوند', 'پردیس', 'ملارد', 'قدس' ),
		'ABZ' => array( 'کرج', 'نظرآباد', 'ساوجبلاغ', 'فردیس', 'اشتهارد', 'طالقان' ),
		'EAZ' => array( 'تبریز', 'مراغه', 'میانه', 'مرند', 'اهر', 'بناب', 'سراب', 'شبستر', 'هریس', 'بستان‌آباد', 'ملکان', 'آذرشهر', 'اسکو' ),
		'WAZ' => array( 'ارومیه', 'خوی', 'مهاباد', 'میاندوآب', 'بوکان', 'سلماس', 'پیرانشهر', 'نقده', 'ماکو', 'اشنویه', 'سردشت' ),
		'ADL' => array( 'اردبیل', 'پارس‌آباد', 'مشگین‌شهر', 'خلخال', 'گرمی', 'نمین', 'نیر', 'بیله‌سوار' ),
		'ESF' => array( 'اصفهان', 'کاشان', 'نجف‌آباد', 'خمینی‌شهر', 'شاهین‌شهر', 'نطنز', 'گلپایگان', 'فریدن', 'زرین‌شهر', 'مبارکه', 'شهرضا', 'آران و بیدگل', 'نائین', 'اردستان', 'سمیرم' ),
		'ILM' => array( 'ایلام', 'دهلران', 'آبدانان', 'ایوان', 'دره‌شهر', 'مهران', 'چرداول' ),
		'BHR' => array( 'بوشهر', 'برازجان', 'گناوه', 'دیر', 'کنگان', 'دیلم', 'تنگستان' ),
		'CHB' => array( 'شهرکرد', 'بروجن', 'فارسان', 'لردگان', 'اردل' ),
		'KHZ' => array( 'اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'اندیمشک', 'بهبهان', 'ماهشهر', 'شوشتر', 'شوش', 'ایذه', 'رامهرمز', 'مسجدسلیمان', 'هویزه', 'باغ‌ملک' ),
		'FRS' => array( 'شیراز', 'مرودشت', 'کازرون', 'جهرم', 'فسا', 'لار', 'داراب', 'آباده', 'اقلید', 'لامرد', 'فیروزآباد', 'استهبان', 'نی‌ریز', 'ممسنی' ),
		'GZN' => array( 'قزوین', 'البرز', 'آبیک', 'بوئین‌زهرا', 'تاکستان' ),
		'GIL' => array( 'رشت', 'بندرانزلی', 'لاهیجان', 'لنگرود', 'آستارا', 'تالش', 'رودسر', 'صومعه‌سرا', 'فومن', 'رودبار', 'آستانه اشرفیه' ),
		'GLS' => array( 'گرگان', 'گنبدکاووس', 'علی‌آباد کتول', 'آق‌قلا', 'کردکوی', 'بندرترکمن', 'مینودشت', 'کلاله' ),
		'LRS' => array( 'خرم‌آباد', 'بروجرد', 'دورود', 'الیگودرز', 'کوهدشت', 'ازنا', 'پلدختر', 'نورآباد' ),
		'MKZ' => array( 'اراک', 'ساوه', 'خمین', 'محلات', 'دلیجان', 'شازند', 'تفرش' ),
		'MZN' => array( 'ساری', 'بابل', 'آمل', 'قائم‌شهر', 'بابلسر', 'نور', 'نوشهر', 'چالوس', 'رامسر', 'تنکابن', 'بهشهر', 'نکا', 'جویبار' ),
		'HRZ' => array( 'بندرعباس', 'میناب', 'بندرلنگه', 'قشم', 'رودان', 'بستک', 'حاجی‌آباد' ),
		'HDN' => array( 'همدان', 'ملایر', 'نهاوند', 'تویسرکان', 'اسدآباد', 'بهار', 'کبودراهنگ', 'رزن' ),
		'YZD' => array( 'یزد', 'میبد', 'اردکان', 'بافق', 'تفت', 'ابرکوه', 'مهریز' ),
		'KRH' => array( 'کرمانشاه', 'اسلام‌آباد غرب', 'سنقر', 'کنگاور', 'پاوه', 'هرسین', 'صحنه', 'سرپل‌ذهاب', 'جوانرود' ),
		'KRN' => array( 'کرمان', 'رفسنجان', 'سیرجان', 'جیرفت', 'بم', 'زرند', 'کهنوج', 'بردسیر', 'شهربابک' ),
		'KRD' => array( 'سنندج', 'سقز', 'بانه', 'مریوان', 'بیجار', 'قروه', 'کامیاران', 'دیواندره' ),
		'KBD' => array( 'یاسوج', 'گچساران', 'دهدشت', 'دوگنبدان' ),
		'SKH' => array( 'بیرجند', 'قائنات', 'نهبندان', 'طبس', 'سربیشه', 'فردوس' ),
		'RKH' => array( 'مشهد', 'نیشابور', 'سبزوار', 'تربت‌حیدریه', 'کاشمر', 'قوچان', 'تربت‌جام', 'چناران', 'گناباد', 'فریمان', 'سرخس', 'تایباد' ),
		'NKH' => array( 'بجنورد', 'شیروان', 'اسفراین', 'جاجرم', 'مانه و سملقان' ),
		'SMN' => array( 'سمنان', 'شاهرود', 'دامغان', 'گرمسار', 'مهدی‌شهر' ),
		'SBN' => array( 'زاهدان', 'زابل', 'چابهار', 'ایرانشهر', 'سراوان', 'خاش', 'کنارک', 'نیک‌شهر' ),
		'ZJN' => array( 'زنجان', 'ابهر', 'خدابنده', 'خرمدره', 'ماه‌نشان' ),
		'QHM' => array( 'قم' ),
	);

	$province_by_state = array(
		'THR' => 'تهران',
		'ABZ' => 'البرز',
		'EAZ' => 'آذربایجان شرقی',
		'WAZ' => 'آذربایجان غربی',
		'ADL' => 'اردبیل',
		'ESF' => 'اصفهان',
		'ILM' => 'ایلام',
		'BHR' => 'بوشهر',
		'CHB' => 'چهارمحال و بختیاری',
		'KHZ' => 'خوزستان',
		'FRS' => 'فارس',
		'GZN' => 'قزوین',
		'GIL' => 'گیلان',
		'GLS' => 'گلستان',
		'LRS' => 'لرستان',
		'MKZ' => 'مرکزی',
		'MZN' => 'مازندران',
		'HRZ' => 'هرمزگان',
		'HDN' => 'همدان',
		'YZD' => 'یزد',
		'KRH' => 'کرمانشاه',
		'KRN' => 'کرمان',
		'KRD' => 'کردستان',
		'KBD' => 'کهگیلویه و بویراحمد',
		'SKH' => 'خراسان جنوبی',
		'RKH' => 'خراسان رضوی',
		'NKH' => 'خراسان شمالی',
		'SMN' => 'سمنان',
		'SBN' => 'سیستان و بلوچستان',
		'ZJN' => 'زنجان',
		'QHM' => 'قم',
	);
	$data_path = dirname( __DIR__ ) . '/data/iran-cities.json';
	if ( ! is_readable( $data_path ) ) {
		return $cities;
	}
	$json       = file_get_contents( $data_path );
	$additional = is_string( $json ) ? json_decode( $json, true ) : null;
	if ( ! is_array( $additional ) ) {
		return $cities;
	}

	foreach ( $cities as $state => $legacy_cities ) {
		$seen   = array();
		$merged = array();
		$province  = $province_by_state[ $state ] ?? '';
		$new_cities = '' !== $province && isset( $additional[ $province ] ) && is_array( $additional[ $province ] )
			? $additional[ $province ]
			: array();
		foreach ( array_merge( $legacy_cities, $new_cities ) as $city ) {
			if ( ! is_string( $city ) ) {
				continue;
			}
			$city = trim( $city );
			$key  = jluxe_iran_city_name_key( $city );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$merged[]     = $city;
		}
		$cities[ $state ] = $merged;
	}

	return $cities;
}

/**
 * Reject only a listed Iranian city paired with a different known province.
 * Unknown/custom/legacy city values remain valid so rural and customer-entered
 * addresses do not get blocked by an incomplete administrative dataset.
 */
function jluxe_validate_checkout_iran_city_province( $data, $errors ): void {
	if ( ! is_array( $data ) || ! is_object( $errors ) || ! method_exists( $errors, 'add' ) ) {
		return;
	}

	static $known_city_states = null;
	if ( null === $known_city_states ) {
		$known_city_states = array();
		foreach ( jluxe_iran_cities() as $state => $state_cities ) {
			foreach ( $state_cities as $city ) {
				$key = jluxe_iran_city_name_key( $city );
				if ( '' !== $key ) {
					$known_city_states[ $key ][ $state ] = true;
				}
			}
		}
	}

	foreach ( array( 'billing', 'shipping' ) as $address_type ) {
		$country_key = $address_type . '_country';
		$state_key   = $address_type . '_state';
		$city_key    = $address_type . '_city';
		$country     = isset( $data[ $country_key ] ) && is_scalar( $data[ $country_key ] )
			? strtoupper( trim( (string) $data[ $country_key ] ) )
			: '';
		if ( 'IR' !== $country ) {
			continue;
		}
		$state = isset( $data[ $state_key ] ) && is_scalar( $data[ $state_key ] )
			? strtoupper( trim( (string) $data[ $state_key ] ) )
			: '';
		$city = isset( $data[ $city_key ] ) && is_scalar( $data[ $city_key ] )
			? sanitize_text_field( (string) $data[ $city_key ] )
			: '';
		$city_name_key = jluxe_iran_city_name_key( $city );
		if ( '' === $state || '' === $city_name_key || ! isset( $known_city_states[ $city_name_key ] ) ) {
			continue;
		}
		if ( isset( $known_city_states[ $city_name_key ][ $state ] ) ) {
			continue;
		}

		$errors->add(
			'jluxe_' . $address_type . '_city_province_mismatch',
			'شهر انتخاب‌شده با استان انتخابی هم‌خوانی ندارد. لطفاً شهر استان را انتخاب کنید.',
			array( 'id' => $city_key )
		);
	}
}

/**
 * Never revive billing province/city from a saved checkout session or browser cache.
 * Explicitly posted checkout values remain available when WooCommerce redisplays errors.
 */
function jluxe_checkout_clear_cached_location_values( $value, string $input ) {
	if ( ! in_array( $input, array( 'billing_state', 'billing_city' ), true ) ) {
		return $value;
	}
	if ( function_exists( 'is_checkout' ) && ! is_checkout() ) {
		return $value;
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return $value;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return $value;
	}
	if ( isset( $_POST ) && is_array( $_POST ) && array_key_exists( $input, $_POST ) ) {
		return $value;
	}
	return '';
}

/**
 * نام/نام‌خانوادگی طبقِ مرجعِ تصویریِ جدیدِ کاربر («هر مرحله باید مطابق
 * تصاویر باشه») دوباره دو فیلدِ جدا شدن — نسخه‌ی قبلی این‌جا این دو رو در
 * یک فیلدِ نمایشیِ «نام و نام‌خانوادگی» ادغام کرده بود (طبقِ یک طراحیِ
 * تأییدشده‌ی قبلی‌تر)، ولی مرجعِ تازه‌ی کاربر صراحتاً «نام» و
 * «نام‌خانوادگی» رو دو فیلدِ کنارِ هم نشون می‌ده، پس ادغام/تقسیمِ مصنوعی
 * (jluxe_split_full_name) حذف شد و فیلدهای واقعیِ خودِ ووکامرس مستقیماً
 * استفاده می‌شن.
 */
function jluxe_billing_fields( array $fields ): array {
	if ( ! isset( $fields['billing'] ) ) {
		return $fields;
	}

	$field_labels = array(
		'billing_first_name' => array( 'checkout_first_name_label', 'نام' ),
		'billing_last_name'  => array( 'checkout_last_name_label', 'نام خانوادگی' ),
		'billing_state'      => array( 'checkout_province_label', 'استان' ),
		'billing_city'       => array( 'checkout_city_label', 'شهر' ),
		'billing_phone'      => array( 'checkout_phone_label', 'موبایل' ),
		'billing_email'      => array( 'checkout_email_label', 'آدرس ایمیل' ),
		'billing_postcode'   => array( 'checkout_postcode_label', 'کد پستی' ),
		'billing_address_1'  => array( 'checkout_address_label', 'آدرس' ),
	);
	foreach ( $field_labels as $field_key => $label ) {
		if ( isset( $fields['billing'][ $field_key ] ) ) {
			$fields['billing'][ $field_key ]['label'] = jluxe_cart_checkout_required_copy( $label[0], $label[1] );
		}
	}

	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name']['priority'] = 5;
	}
	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_last_name']['priority'] = 6;
	}

	// ایمیل از checkout حذف شده (درخواست کاربر) — کد پستی جای آن قرار می‌گیرد.
	unset( $fields['billing']['billing_email'] );

	// استان/شهر/کدپستی — یک ردیفِ ۳ستونه در دسکتاپ (طبق طراحی تأییدشده).
	// چون assets/js/frontend/address-i18n.js ووکامرس همه‌ی .form-row رو
	// روی هر بارگذاری/تغییر کشور به‌صورت flat و بر اساس priority دوباره
	// می‌چینه (rows.detach().appendTo(wrapper)) — نمی‌شه این‌ها رو داخل یک
	// <div> جدا nest کرد، چون همون اسکریپت اون تو در تو بودن رو از بین
	// می‌بره. راه‌حل: فیلدها flat می‌مونن، priority پشت‌سرهم می‌گیرن، و
	// چیدمانِ سه‌ستونه/دوستونه با flex-basis روی خودِ .form-row (بر اساس id)
	// در globals.css پیاده می‌شه، نه با تودرتو کردن DOM.
	if ( isset( $fields['billing']['billing_state'] ) ) {
		$fields['billing']['billing_state']['priority']    = 25;
		$fields['billing']['billing_state']['required']    = true;
		$fields['billing']['billing_state']['autocomplete'] = 'off';
	}
	if ( isset( $fields['billing']['billing_city'] ) ) {
		// استان و شهرِ checkout از آدرسِ ذخیره‌شده یا Autofill انتخاب نمی‌شوند؛
		// فقط مقدارِ ارسالیِ همین تلاشِ checkout (برای نگه‌داشتن فرمِ خطادار)
		// به‌صورت موقت روی سرور نگه داشته می‌شود. فهرست شهرها سمتِ کلاینت ساخته می‌شود.
		$current_city = isset( $_POST['billing_city'] ) && is_scalar( $_POST['billing_city'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['billing_city'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to refill the checkout after WooCommerce validation.
			: '';
		$fields['billing']['billing_city']['priority']     = 26;
		$fields['billing']['billing_city']['required']     = true;
		$fields['billing']['billing_city']['autocomplete'] = 'off';
		$fields['billing']['billing_city']['type']         = 'select';
		$city_placeholder = jluxe_cart_checkout_required_copy( 'checkout_city_placeholder', 'انتخاب شهرستان' );
		$city_prompt      = jluxe_cart_checkout_required_copy( 'checkout_city_prompt', 'ابتدا استان را انتخاب کنید' );
		$fields['billing']['billing_city']['options']      = $current_city
			? array( '' => $city_placeholder, $current_city => $current_city )
			: array( '' => $city_prompt );
	}
	$preserve_posted_checkout_location = isset( $_POST ) && is_array( $_POST ) && (
		array_key_exists( 'billing_state', $_POST ) || array_key_exists( 'billing_city', $_POST )
	);
	if ( $preserve_posted_checkout_location ) {
		foreach ( array( 'billing_state', 'billing_city' ) as $location_key ) {
			if ( ! isset( $fields['billing'][ $location_key ] ) ) {
				continue;
			}
			$location_attributes = isset( $fields['billing'][ $location_key ]['custom_attributes'] ) && is_array( $fields['billing'][ $location_key ]['custom_attributes'] )
				? $fields['billing'][ $location_key ]['custom_attributes']
				: array();
			$fields['billing'][ $location_key ]['custom_attributes'] = array_merge(
				$location_attributes,
				array( 'data-jluxe-preserve-checkout-location' => '1' )
			);
		}
	}
	if ( isset( $fields['billing']['billing_postcode'] ) ) {
		// کد پستی جای ایمیل (priority 16) و تمام‌عرض قرار می‌گیرد.
		$fields['billing']['billing_postcode']['priority']    = 16;
		$fields['billing']['billing_postcode']['required']    = false;
		$fields['billing']['billing_postcode']['placeholder'] = '';
		$fields['billing']['billing_postcode']['class']       = array( 'form-row-wide' );
	}

	if ( isset( $fields['billing']['billing_phone'] ) ) {
		// موبایل در تنظیمات پیش‌فرض الزامی است؛ فیلترِ نهاییِ checkout در
		// priority 999 همین وضعیت را طبقِ checkbox مدیر قابل خاموش‌کردن می‌کند.
		$fields['billing']['billing_phone']['label']    = jluxe_cart_checkout_required_copy( 'checkout_phone_label', 'موبایل' );
		$fields['billing']['billing_phone']['priority'] = 15;
		$fields['billing']['billing_phone']['required'] = true;
		$phone_attributes = isset( $fields['billing']['billing_phone']['custom_attributes'] ) && is_array( $fields['billing']['billing_phone']['custom_attributes'] )
			? $fields['billing']['billing_phone']['custom_attributes']
			: array();
		$fields['billing']['billing_phone']['custom_attributes'] = array_merge(
			$phone_attributes,
			array(
				'inputmode' => 'tel',
				'dir'       => 'ltr',
				'lang'      => 'fa',
			)
		);
	}

	// تلفن ثابت — فیلد سفارشی، ووکامرس/افزونه‌ی فارسی این رو ندارن. priority
	// بعد از آدرس (50) طبقِ ترتیبِ مرجعِ تصویریِ جدید (... آدرس، بعد تلفنِ
	// ثابت/توضیحات).
	$fields['billing']['billing_landline'] = array(
		'label'        => jluxe_cart_checkout_required_copy( 'checkout_landline_label', 'تلفن ثابت' ),
		'required'     => false,
		'priority'     => 55,
		'type'              => 'tel',
		'autocomplete'      => 'tel',
		'custom_attributes' => array(
			'inputmode' => 'tel',
			'dir'       => 'ltr',
			'lang'      => 'fa',
		),
	);

	// فروشگاه فقط داخل ایران ارسال می‌کنه، پس فیلد کشور از دید مشتری
	// حذف می‌شه (مقدارش همیشه IR باقی می‌مونه، برای منطق ارسال/مالیات
	// واقعی ووکامرس لازمه، فقط دیگه دیده نمی‌شه).
	if ( isset( $fields['billing']['billing_country'] ) ) {
		$fields['billing']['billing_country']['type']    = 'hidden';
		$fields['billing']['billing_country']['default'] = 'IR';
	}

	if ( isset( $fields['billing']['billing_address_1'] ) ) {
		$fields['billing']['billing_address_1']['priority'] = 50;
		$fields['billing']['billing_address_1']['class']    = array( 'form-row-wide' );
	}
	// «آدرس» طبق طراحی تأییدشده یک فیلد تکه؛ خط دومِ آدرس (اختیاری) حذف می‌شه.
	if ( isset( $fields['billing']['billing_address_2'] ) ) {
		$fields['billing']['billing_address_2']['type'] = 'hidden';
	}

	// «نام شرکت» طبقِ درخواستِ صریحِ کاربر حذف شد — این یه فروشگاهِ
	// خرده‌فروشیِ B2C است، فیلدِ نام شرکت کاربردی نداره (مثلِ address_2،
	// hidden می‌شه نه واقعاً از آرایه حذف، چون get_value()/سایرِ منطقِ
	// خودِ ووکامرس رو این کلید حساب می‌کنه؛ hidden با مقدارِ پیش‌فرضِ خالی
	// دقیقاً همون اثرِ حذف رو داره، بدونِ ریسکِ notice/undefined-index).
	if ( isset( $fields['billing']['billing_company'] ) ) {
		$fields['billing']['billing_company']['type'] = 'hidden';
	}

	return $fields;
}

/** Limit the account-address presentation changes to WooCommerce's edit-address endpoint. */
function jluxe_is_account_address_endpoint(): bool {
	return function_exists( 'is_account_page' ) && is_account_page()
		&& function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' );
}

/** Preserve WooCommerce field classes while making account address rows stack predictably. */
function jluxe_account_address_field_add_class( array $field, string $class ): array {
	$classes = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
	$classes = array_values( array_filter( $classes, 'is_string' ) );
	if ( ! in_array( $class, $classes, true ) ) {
		$classes[] = $class;
	}
	$field['class'] = $classes;
	return $field;
}

/**
 * Arrange only the account address editor; checkout fields, validation, country values,
 * and WooCommerce's shipping/payment calculations remain on their existing paths.
 */
function jluxe_prepare_account_address_fields( array $fields, string $country, string $prefix ): array {
	if ( ! jluxe_is_account_address_endpoint() || ! in_array( $prefix, array( 'billing_', 'shipping_' ), true ) ) {
		return $fields;
	}

	$country = strtoupper( trim( $country ) );
	$ordered_priorities = array(
		$prefix . 'state'      => 40,
		$prefix . 'city'       => 50,
		$prefix . 'address_1'  => 60,
		$prefix . 'postcode'   => 70,
		$prefix . 'phone'      => 80,
		$prefix . 'email'      => 90,
		$prefix . 'country'    => 100,
		$prefix . 'address_2'  => 110,
	);
	foreach ( $ordered_priorities as $key => $priority ) {
		if ( isset( $fields[ $key ] ) ) {
			$fields[ $key ]['priority'] = $priority;
		}
	}

	foreach ( array( 'state', 'city', 'address_1', 'postcode', 'phone' ) as $suffix ) {
		$key = $prefix . $suffix;
		if ( ! isset( $fields[ $key ] ) ) {
			continue;
		}
		$classes = isset( $fields[ $key ]['class'] ) && is_array( $fields[ $key ]['class'] )
			? $fields[ $key ]['class']
			: array();
		$classes = array_values( array_diff( $classes, array( 'form-row-first', 'form-row-last' ) ) );
		if ( ! in_array( 'form-row-wide', $classes, true ) ) {
			$classes[] = 'form-row-wide';
		}
		$fields[ $key ]['class'] = $classes;
	}

	$country_key = $prefix . 'country';
	if ( 'IR' === $country && isset( $fields[ $country_key ] ) ) {
		$fields[ $country_key ] = jluxe_account_address_field_add_class( $fields[ $country_key ], 'jluxe-account-address-field-hidden' );
	}
	$address_2_key = $prefix . 'address_2';
	if ( isset( $fields[ $address_2_key ] ) ) {
		$fields[ $address_2_key ] = jluxe_account_address_field_add_class( $fields[ $address_2_key ], 'jluxe-account-address-field-hidden' );
	}

	$city_key  = $prefix . 'city';
	$state_key = $prefix . 'state';
	if ( 'IR' !== $country || ! isset( $fields[ $city_key ] ) ) {
		return $fields;
	}

	$cities_by_state = jluxe_iran_cities();
	$state_value     = get_user_meta( get_current_user_id(), $state_key, true );
	if ( array_key_exists( $state_key, $_POST ) && is_scalar( $_POST[ $state_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to refill the account form after WooCommerce validation.
		$state_value = wp_unslash( (string) $_POST[ $state_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	$state_value = is_scalar( $state_value ) ? strtoupper( sanitize_text_field( (string) $state_value ) ) : '';

	$current_city = get_user_meta( get_current_user_id(), $city_key, true );
	if ( array_key_exists( $city_key, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to refill the account form after WooCommerce validation.
		$posted_city = $_POST[ $city_key ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$current_city = is_scalar( $posted_city ) ? wp_unslash( (string) $posted_city ) : '';
	}
	$current_city = is_scalar( $current_city ) ? sanitize_text_field( (string) $current_city ) : '';

	$state_cities = isset( $cities_by_state[ $state_value ] ) && is_array( $cities_by_state[ $state_value ] )
		? $cities_by_state[ $state_value ]
		: array();
	$placeholder = $state_cities
		? jluxe_cart_checkout_required_copy( 'checkout_city_placeholder', 'انتخاب شهرستان' )
		: jluxe_cart_checkout_required_copy( 'checkout_city_prompt', 'ابتدا استان را انتخاب کنید' );
	$options = array( '' => $placeholder );
	foreach ( $state_cities as $city ) {
		if ( is_string( $city ) && '' !== trim( $city ) ) {
			$options[ $city ] = $city;
		}
	}
	if ( '' !== $current_city && ! array_key_exists( $current_city, $options ) ) {
		// Keep a real legacy/custom value visible until the customer chooses a listed city.
		$options[ $current_city ] = $current_city;
	}

	$fields[ $city_key ]['type']    = 'select';
	$fields[ $city_key ]['options'] = $options;
	return $fields;
}

function jluxe_account_billing_fields( array $fields, string $country = '' ): array {
	return jluxe_prepare_account_address_fields( $fields, $country, 'billing_' );
}

function jluxe_account_shipping_fields( array $fields, string $country = '' ): array {
	return jluxe_prepare_account_address_fields( $fields, $country, 'shipping_' );
}

/** Keep a valid stored account country, or submit WooCommerce's valid base/fallback country. */
function jluxe_account_edit_address_country_value( $value, string $key, string $load_address ) {
	if ( ! jluxe_is_account_address_endpoint() || ! in_array( $key, array( 'billing_country', 'shipping_country' ), true ) ) {
		return $value;
	}
	$woocommerce = function_exists( 'WC' ) ? WC() : null;
	if ( ! is_object( $woocommerce ) || ! isset( $woocommerce->countries ) || ! is_object( $woocommerce->countries ) || ! method_exists( $woocommerce->countries, 'get_base_country' ) ) {
		return $value;
	}

	$countries = $woocommerce->countries;
	$allowed   = 'shipping' === $load_address && method_exists( $countries, 'get_shipping_countries' )
		? $countries->get_shipping_countries()
		: ( method_exists( $countries, 'get_allowed_countries' ) ? $countries->get_allowed_countries() : array() );
	$current   = is_scalar( $value ) ? strtoupper( trim( (string) $value ) ) : '';
	if ( '' !== $current && ( ! is_array( $allowed ) || array_key_exists( $current, $allowed ) ) ) {
		return $current;
	}

	$base_country = $countries->get_base_country();
	if ( is_array( $allowed ) && array_key_exists( $base_country, $allowed ) ) {
		return $base_country;
	}
	if ( is_array( $allowed ) && ! empty( $allowed ) ) {
		return (string) array_key_first( $allowed );
	}
	return $current ?: $base_country;
}

/**
 * وضعیتِ اجباری‌بودنِ نام، موبایل و آدرس را در checkout طبقِ تنظیمات مدیر
 * برای billing/shipping اعمال می‌کند؛ استان و شهر همیشه اجباری می‌مانند.
 * کد پستی فقط با روشن‌بودن گزینه و انتخاب نرخِ ادارهٔ پست در UI/اعتبارسنجی
 * سمتِ سرور لازم است؛ راهنما فقط به‌صورت placeholder می‌آید و شرح جداگانه ندارد.
 * کدِ خالیِ تیپاکس/چاپار پذیرفته می‌شود و اعتبارسنجی مقدارِ واردشده (از جمله
 * افزونهٔ ووکامرس فارسی) دست‌نخورده می‌ماند.
 */
function jluxe_optional_checkout_postcodes( array $fields ): array {
	$postcode_required_for_post = jluxe_cart_checkout_option( 'require_postcode_for_post', true );
	$postcode_placeholder = $postcode_required_for_post
		? 'برای ارسال پستی، کد پستی الزامی است'
		: 'کد پستی (اختیاری)';
	$field_requirements = array(
		'first_name' => 'require_first_name',
		'last_name'  => 'require_last_name',
		'phone'      => 'require_phone',
		'address_1'  => 'require_address',
	);
	foreach ( array( 'billing', 'shipping' ) as $address_type ) {
		foreach ( $field_requirements as $field_suffix => $setting_key ) {
			$field_key = $address_type . '_' . $field_suffix;
			if ( isset( $fields[ $address_type ][ $field_key ] ) ) {
				$fields[ $address_type ][ $field_key ]['required'] = jluxe_cart_checkout_option( $setting_key, true );
			}
		}
		if ( isset( $fields[ $address_type ][ $address_type . '_state' ] ) ) {
			$fields[ $address_type ][ $address_type . '_state' ]['required'] = true;
		}
		if ( isset( $fields[ $address_type ][ $address_type . '_city' ] ) ) {
			$fields[ $address_type ][ $address_type . '_city' ]['required'] = true;
		}
		$phone_key = $address_type . '_phone';
		if ( isset( $fields[ $address_type ][ $phone_key ] ) ) {
			$fields[ $address_type ][ $phone_key ]['autocomplete'] = 'tel';
			$phone_attributes = isset( $fields[ $address_type ][ $phone_key ]['custom_attributes'] ) && is_array( $fields[ $address_type ][ $phone_key ]['custom_attributes'] )
				? $fields[ $address_type ][ $phone_key ]['custom_attributes']
				: array();
			$fields[ $address_type ][ $phone_key ]['custom_attributes'] = array_merge(
				$phone_attributes,
				array(
					'inputmode' => 'tel',
					'dir'       => 'ltr',
					'lang'      => 'fa',
				)
			);
		}
		$key = $address_type . '_postcode';
		if ( ! isset( $fields[ $address_type ][ $key ] ) ) {
			continue;
		}
		$fields[ $address_type ][ $key ]['autocomplete'] = 'postal-code';
		$fields[ $address_type ][ $key ]['required']    = false;
		$fields[ $address_type ][ $key ]['placeholder'] = $postcode_placeholder;
		$custom_attributes = isset( $fields[ $address_type ][ $key ]['custom_attributes'] ) && is_array( $fields[ $address_type ][ $key ]['custom_attributes'] )
			? $fields[ $address_type ][ $key ]['custom_attributes']
			: array();
		$fields[ $address_type ][ $key ]['custom_attributes'] = array_merge(
			$custom_attributes,
			array(
				'inputmode' => 'numeric',
				'dir'       => 'ltr',
				'lang'      => 'fa',
			)
		);
		unset( $fields[ $address_type ][ $key ]['description'] );
		$fields[ $address_type ][ $key ]['desc_tip'] = false;
	}
	return $fields;
}

/** Check the selected rate in each live WooCommerce package, not its cost or checkout styling. */
function jluxe_checkout_shipping_methods_require_postcode( array $data ): bool {
	if ( ! jluxe_cart_checkout_option( 'require_postcode_for_post', true ) ) {
		return false;
	}
	$selected_methods = $data['shipping_method'] ?? array();
	if ( is_scalar( $selected_methods ) ) {
		$selected_methods = array( 0 => $selected_methods );
	}
	if ( ! is_array( $selected_methods ) || ! $selected_methods ) {
		return false;
	}

	$packages = array();
	if ( function_exists( 'WC' ) ) {
		$woocommerce = WC();
		if ( is_object( $woocommerce ) && method_exists( $woocommerce, 'shipping' ) ) {
			$shipping = $woocommerce->shipping();
			if ( is_object( $shipping ) && method_exists( $shipping, 'get_packages' ) ) {
				$packages = (array) $shipping->get_packages();
			}
		}
	}

	foreach ( $selected_methods as $package_index => $selected_rate_id ) {
		if ( ! is_scalar( $selected_rate_id ) ) {
			continue;
		}
		$selected_rate_id = trim( (string) $selected_rate_id );
		if ( '' === $selected_rate_id ) {
			continue;
		}

		$method = $selected_rate_id;
		if (
			isset( $packages[ $package_index ]['rates'] ) &&
			is_array( $packages[ $package_index ]['rates'] ) &&
			isset( $packages[ $package_index ]['rates'][ $selected_rate_id ] )
		) {
			$method = $packages[ $package_index ]['rates'][ $selected_rate_id ];
		}

		$presentation = jluxe_shipping_method_presentation( $method );
		if ( 'post' === ( $presentation['kind'] ?? '' ) ) {
			return true;
		}
	}

	return false;
}

/** Require only the destination postcode when a selected live rate is classified as Post. */
function jluxe_validate_required_checkout_postcode_for_post_shipping( array $data, $errors ): void {
	if ( ! is_object( $errors ) || ! method_exists( $errors, 'add' ) ) {
		return;
	}
	if ( ! jluxe_checkout_shipping_methods_require_postcode( $data ) ) {
		return;
	}

	$postcode_key = ! empty( $data['ship_to_different_address'] ) ? 'shipping_postcode' : 'billing_postcode';
	$postcode     = $data[ $postcode_key ] ?? '';
	if ( is_scalar( $postcode ) && '' !== trim( (string) $postcode ) ) {
		return;
	}

	$errors->add(
		'jluxe_postcode_required_for_post_shipping',
		'برای ارسال با پست، کد پستی مقصد را وارد کنید.',
		array( 'id' => $postcode_key )
	);
}

/** Normalize checkout postal/contact digits before WooCommerce validation and order/customer persistence. */
function jluxe_normalize_checkout_numeric_fields( array $data ): array {
	foreach ( array( 'billing_postcode', 'shipping_postcode', 'billing_phone', 'shipping_phone', 'billing_landline' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
			$data[ $key ] = jluxe_ascii_digits( (string) $data[ $key ] );
		}
	}
	return $data;
}

/**
 * باگِ واقعیِ گزارش‌شده (پیدا شده با تستِ زنده): تنظیم کردنِ 'type' => 'hidden'
 * روی یک فیلد فقط خودِ <input> رو مخفی می‌کنه (که input[type=hidden] در
 * مرورگر ذاتاً نامرئیه)، ولی <p class="form-row"> اطرافش با <label>ی که
 * توش هست همچنان کاملاً دیده می‌شه — یعنی سه فیلد billing_company/
 * billing_country/billing_address_2 با وجودِ hidden‌شدنِ input، لیبلِ
 * خالی‌شون (مثلاً «نام شرکت (اختیاری)» بدونِ هیچ فیلدِ واقعی زیرش)
 * همچنان روی صفحه‌ی چک‌اوت نشون داده می‌شد.
 *
 * تلاشِ اولیه (فیلترِ woocommerce_form_field_$key) اشتباه بود — با خوندنِ
 * زنده‌ی خودِ سورسِ ووکامرس (wc-template-functions.php) مشخص شد فیلترِ
 * واقعی‌ای که woocommerce_form_field() صدا می‌زنه بر اساسِ TYPE فیلده، نه
 * KEY اون: apply_filters('woocommerce_form_field_' . $args['type'], ...)
 * — یعنی برای این سه فیلد چیزی به‌اسمِ woocommerce_form_field_hidden
 * صدا زده می‌شه، نه woocommerce_form_field_billing_company. برای این‌که
 * فقط همین سه فیلدِ مشخص مخفی بشن (نه هر فیلدِ hidden دیگه‌ای که ووکامرس/
 * افزونه‌ها ممکنه بسازن)، از فیلترِ عمومیِ woocommerce_form_field (بدونِ
 * پسوند) استفاده شده که $key رو هم در اختیار می‌ذاره.
 */
function jluxe_hide_field_wrapper( string $field_html, string $key ): string {
	if ( ! in_array( $key, array( 'billing_company', 'billing_country', 'billing_address_2' ), true ) ) {
		return $field_html;
	}
	return preg_replace( '/^<p class="form-row/', '<p style="display:none" class="form-row', $field_html, 1 );
}

/**
 * برچسب/جای‌گزین «توضیحات سفارش» مطابق طراحی تأییدشده.
 */
function jluxe_order_notes_field( array $fields ): array {
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = jluxe_cart_checkout_required_copy( 'checkout_order_notes_label', 'توضیحات سفارش' );
		$fields['order']['order_comments']['placeholder']  = jluxe_cart_checkout_copy( 'checkout_order_notes_placeholder', 'توضیحی که نیاز است در رابطه با سفارش بیان کنید' );
		$fields['order']['order_comments']['class']        = array( 'form-row-wide' );
	}
	return $fields;
}

/**
 * تلفن ثابت چون از طریق فیلتر woocommerce_billing_fields اضافه شده،
 * ووکامرس خودکار به‌عنوان _billing_landline متای سفارش ذخیره‌اش می‌کنه؛
 * این فقط برای نمایشش کنار بقیه‌ی فیلدهای billing در صفحه‌ی ویرایش سفارش
 * ادمینه.
 */
function jluxe_admin_billing_fields( array $fields ): array {
	$fields['landline'] = array( 'label' => 'تلفن ثابت' );
	return $fields;
}

/**
 * استایل مینیمال سبد/چک‌اوت با استایل کلی سایت هماهنگه (فونت/رنگ از
 * assets/compiled/assets/main-*.css که همه‌جا لود می‌شه)، پس رفتارِ سبد
 * (دکمه‌های +/-، حذف و همگام‌سازی fragmentها) در bundle cart/checkout باقی
 * می‌ماند؛ درخواست‌ها با اعتبارسنجیِ endpoint/cart خود WooCommerce انجام
 * می‌شوند و submit استاندارد هم برای fallback حفظ می‌شود.
 */
function jluxe_enqueue_woocommerce_assets(): void {
	if ( ! function_exists( 'is_cart' ) ) {
		return;
	}
	/* R62: enqueue سیستماتیک — فقط بافت‌هایی که برنامهٔ asset (inc/assets.php)
	گروهِ theme_woo_ux را روشن کرده؛ بلاگ/404/آرشیوِ غیرمحصولی دیگر این
	فایل (و وابستگی jquery آن) را بار نمی‌کنند. */
	if ( empty( jluxe_asset_plan()['theme_woo_ux'] ) ) {
		return;
	}
	// هستهٔ مشترکِ Woo (درگاه cart امن، کارت‌ها، مودال تنوع سریع و مینی‌سبد)
	// در صفحه‌هایی می‌آید که ممکن است UI محصول داشته باشند. تعاملات سنگین‌تر
	// محصول و checkout در bundleهای جدا و شرطی پایین‌تر قرار دارند.
	$jluxe_wc_js_path = JLUXE_THEME_DIR . '/assets/js/woocommerce.js';
	$jluxe_wc_js_url  = JLUXE_THEME_URI . '/assets/js/woocommerce.js';
	$jluxe_wc_min_path = JLUXE_ASSET_DIR . '/woocommerce.min.js';
	if ( ! jluxe_is_dev() && is_file( $jluxe_wc_min_path ) ) {
		$jluxe_wc_js_path = $jluxe_wc_min_path;
		$jluxe_wc_js_url  = JLUXE_ASSET_URI . '/woocommerce.min.js';
	}
	$jluxe_wc_dependencies = array( 'jquery', 'jluxe-storefront-utils' );
	if ( function_exists( 'jluxe_soft_navigation_kind' ) && 'catalog' === jluxe_soft_navigation_kind() ) {
		// The archive helper must execute before filter/sort listeners bind.
		$jluxe_wc_dependencies[] = 'jluxe-soft-navigation';
	}
	wp_enqueue_script(
		'jluxe-woocommerce',
		$jluxe_wc_js_url,
		$jluxe_wc_dependencies,
		file_exists( $jluxe_wc_js_path ) ? (string) filemtime( $jluxe_wc_js_path ) : '1.0.0',
		/*
		 * اخطارِ Lighthouse («Render-blocking requests»، jquery/jquery-migrate):
		 * تا وقتی این اسکریپت (که به jquery وابسته‌ست) به‌صورتِ ساده/غیر-defer
		 * ثبت بود، خودِ jquery-core هم نمی‌تونست defer بشه — وردپرس یک وابستگی
		 * رو defer نمی‌کنه اگه یک اسکریپتِ غیر-defer بهش وابسته باشه (تضمینِ
		 * ترتیبِ اجرا). با فرمِ آرایه‌ایِ strategy=>defer (ویژگیِ رسمیِ خودِ
		 * وردپرس از ۶.۳، همون چیزی که خودِ ووکامرس برای اسکریپت‌های خودش
		 * استفاده می‌کنه)، این اسکریپت هم مسدودکننده نیست هم دیگه مانعِ
		 * defer-شدنِ jquery-core نمی‌شه. چون از قبل هم in_footer=true بود
		 * (پایینِ صفحه، بعدِ کلِ محتوا)، از نظرِ زمانِ اجرا تغییری نمی‌کنه —
		 * فقط دانلودش زودتر (موازی با پارس‌شدنِ HTML) شروع می‌شه.
		 */
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	// آدرس واقعیِ صفحه‌ی سبد خرید — قدم‌های چک‌اوت (assets/js/woocommerce.js)
	// دکمه‌ی «بازگشت به سبد خرید» رو با همین می‌سازن؛ قبلاً این آدرس از یک
	// لینکِ داخلِ #payment خونده می‌شد که دیگه رندر نمی‌شه، پس مستقیم از
	// wc_get_cart_url() localize می‌شه.
	wp_localize_script( 'jluxe-woocommerce', 'jluxeWcSettings', array( 'cartUrl' => wc_get_cart_url() ) );

	// مودال انتخاب سریعِ تنوع (inc/cart-ux.php: jluxe_ajax_variation_picker)
	// می‌تونه از هر جایی که کارتِ محصولِ متغیر هست (شاپ/دسته/صفحه اصلی) باز
	// بشه، پس این اسکریپت باید همه‌جا از قبل لود شده باشه — نه فقط صفحه‌ی
	// تکیِ محصول — وگرنه با تزریقِ HTML مودال، jQuery(form).wc_variation_form
	// هنوز تعریف‌نشده و انتخاب سواچ هیچ اتفاقی نمی‌ندازه.
	wp_enqueue_script( 'wc-add-to-cart-variation' );

	// جزئیاتِ فرم محصول و نوار/آکاردئون خرید، فقط روی صفحهٔ محصول اجرا شوند.
	if ( ! jluxe_is_dev() && function_exists( 'is_product' ) && is_product() ) {
		$product_path = JLUXE_ASSET_DIR . '/woocommerce.product.min.js';
		$product_url  = JLUXE_ASSET_URI . '/woocommerce.product.min.js';
		if ( ! is_file( $product_path ) ) {
			return;
		}
		wp_enqueue_script(
			'jluxe-woocommerce-product',
			$product_url,
			array( 'jluxe-woocommerce', 'wc-add-to-cart-variation' ),
			(string) filemtime( $product_path ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	// تغییراتِ سبد و فرمِ checkout در همان دو صفحه لازم‌اند، نه Home/archive.
	if ( ! jluxe_is_dev() && ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) ) {
		$cart_checkout_path = JLUXE_ASSET_DIR . '/woocommerce.cart-checkout.min.js';
		$cart_checkout_url  = JLUXE_ASSET_URI . '/woocommerce.cart-checkout.min.js';
		if ( ! is_file( $cart_checkout_path ) ) {
			return;
		}
		wp_enqueue_script(
			'jluxe-woocommerce-cart-checkout',
			$cart_checkout_url,
			array( 'jluxe-woocommerce' ),
			(string) filemtime( $cart_checkout_path ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}

/** Load the guided checkout state machine only when that optional template is active. */
function jluxe_enqueue_guided_checkout_assets(): void {
	// order-pay (پرداختِ سفارشِ موجود/بازگشت از درگاه) فرمِ پیش‌فرضِ ووکامرس
	// (pay_for_order) را رندر می‌کند، نه قالبِ guided؛ assetهای guided این‌جا
	// بی‌اثر و فقط سربارند. is_checkout() روی order-pay هم true است، پس صراحتاً
	// مستثنی می‌شود (مثلِ order-received).
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) || 'guided' !== jluxe_checkout_layout() ) {
		return;
	}
	if ( function_exists( 'jluxe_asset_plan' ) && empty( jluxe_asset_plan()['theme_woo_ux'] ) ) {
		return;
	}
	$style_path = JLUXE_THEME_DIR . '/assets/css/checkout-guided.css';
	if ( is_file( $style_path ) ) {
		wp_enqueue_style(
			'jluxe-guided-checkout',
			JLUXE_THEME_URI . '/assets/css/checkout-guided.css',
			array(),
			(string) filemtime( $style_path )
		);
	}
	$path = JLUXE_THEME_DIR . '/assets/js/checkout-guided.js';
	if ( ! is_file( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'jluxe-guided-checkout',
		JLUXE_THEME_URI . '/assets/js/checkout-guided.js',
		array( 'jquery', 'wc-checkout', 'jluxe-woocommerce' ),
		(string) filemtime( $path ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}

/** Load notice suppression and the classic payment-step coupon proxy on checkout layouts. */
function jluxe_enqueue_checkout_notice_assets(): void {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/checkout-notices.js';
	if ( ! is_file( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'jluxe-checkout-notices',
		JLUXE_THEME_URI . '/assets/js/checkout-notices.js',
		array( 'jquery' ),
		(string) filemtime( $path ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}

/**
 * R69: پاراگرافِ «اطلاعات شخصی شما برای پردازش سفارش…» در صفحهٔ پرداخت
 * حذف شد (درخواستِ صریحِ کاربر) — فقط برای type=checkout؛ متن‌های ثبت‌نام
 * دست‌نخورده می‌مانند. اگر ادمین بعداً خواست برگردد: این فیلتر را بردارید.
 */
function jluxe_hide_checkout_privacy_text( string $text, string $type ): string {
	return 'checkout' === $type ? '' : $text;
}

/**
 * حذفِ تبِ «دانلودها» از سایدبار حساب کاربری وقتی ادمین خاموشش کرده باشه
 * (تنظیمات → فروشگاه). خودِ فیلترِ رسمیِ ووکامرس استفاده می‌شه، نه فقط
 * حذف از تمپلیتِ سفارشیِ navigation.php، تا رفتار همه‌جای سایت یکسان بمونه.
 */
function jluxe_maybe_remove_account_downloads_tab( array $items ): array {
	if ( ! jluxe_get_setting( 'shop.show_account_downloads_tab', true ) ) {
		unset( $items['downloads'] );
	}
	return $items;
}

/** Hide saved payment methods from account navigation without touching checkout gateways or order payments. */
function jluxe_hide_account_payment_methods_menu_item( array $items ): array {
	unset( $items['payment-methods'] );
	return $items;
}

/**
 * جلوگیری از نشتِ نوتیسِ «X به سبد خرید شما اضافه شد» به صفحه‌های نامربوط
 * (باگِ واقعیِ گزارش‌شده — دوبار: هم توی آرشیوِ دسته‌بندی، هم توی حساب
 * کاربری). ریشه‌ی واقعی: هر add-to-cart موفق (چه با کلیکِ عادی، چه AJAX)
 * سمتِ سرورِ ووکامرس همیشه wc_add_to_cart_message() رو صدا می‌زنه که یک
 * نوتیس تویِ سشن می‌ذاره؛ خودِ ما توی هر AJAX add-to-cart قبلاً یک
 * toast/پالس/انیمیشنِ سفارشی نشون می‌دیم (assets/js/woocommerce.js) و به
 * اون نوتیسِ سمتِ سرور اصلاً نیازی نداریم. ولی چون هیچ صفحه‌ای فوراً
 * wc_print_notices() صدا نمی‌زنه، این نوتیس تویِ سشن می‌مونه تا هر صفحه‌ی
 * بعدی که واقعاً نوتیس‌ها رو چاپ می‌کنه (آرشیو/حساب کاربری) — یعنی
 * روی صفحه‌ای کاملاً بی‌ربط ظاهر می‌شه. با خالی‌کردنِ متنِ پیام فقط برای
 * درخواست‌های AJAX (wp_doing_ajax())، خودِ wc_add_to_cart_message() هیچ‌وقت
 * notice رو اضافه نمی‌کنه — نه اینکه بعداً حذفش کنیم؛ درخواست‌های غیر-AJAX
 * (اگر جایی JS خاموش/از کار افتاده باشه) دست‌نخورده می‌مونن، چون اونجا
 * واقعاً به این نوتیس نیاز داریم.
 */
function jluxe_suppress_ajax_add_to_cart_notice( $message ) {
	if ( wp_doing_ajax() ) {
		return '';
	}
	return $message;
}

/**
 * باگِ واقعیِ گزارش‌شده («این پیغام خیلی زشته»): با تغییرِ تعداد در صفحه‌ی
 * سبد، خودِ ووکامرس (WC_Form_Handler::update_cart_action) یک نوتیسِ خامِ
 * «Cart updated.» با wc_add_notice() اضافه می‌کنه — کلاسِ .woocommerce-message
 * خودِ ووکامرسه، هیچ استایلِ Tailwindِ ما روش اعمال نشده، وسطِ صفحه‌ای که
 * کاملاً بازطراحی شده عجیب دیده می‌شه. چون صفحه‌ی سبدِ ما همین الان (بدونِ
 * این نوتیس هم) مبلغ/تعدادِ به‌روزشده رو فوراً نشون می‌ده، این پیام کاملاً
 * تکراریه — دقیقاً همون استدلالِ jluxe_suppress_ajax_add_to_cart_notice
 * بالا (که برای toastِ افزودن‌به‌سبد هم همین کار انجام شده)، این‌جا هم با
 * فیلترِ رسمیِ خودِ ووکامرس (woocommerce_add_message، برای نوتیس‌های
 * موفقیت) فقط همین یک متنِ مشخص خاموش می‌شه؛ بقیه‌ی نوتیس‌های موفقیت
 * (مثلاً «کوپن با موفقیت اعمال شد») دست‌نخورده می‌مونن.
 */
function jluxe_suppress_cart_updated_notice( $message ) {
	if ( __( 'Cart updated.', 'woocommerce' ) === $message ) {
		return '';
	}
	return $message;
}

/**
 * جعبه‌ی خامِ «"X" پاک شده. بازگردانی؟» — همون باگِ گزارش‌شده («این پیغامم
 * زشته») ولی این‌بار برای حذفِ آیتم از صفحه‌ی سبد (WC_Cart::remove_cart_item،
 * وقتی لینکِ حذفِ خودِ ردیفِ سبد با ?remove_item= کلیک می‌شه — یک ناوبریِ
 * کاملِ صفحه‌ست، نه AJAX ما، پس با فیلترِ jluxe_suppress_ajax_add_to_cart_notice
 * بالا هم گرفته نمی‌شه). چون متنِ پیام هر بار شاملِ نامِ محصوله (نمی‌شه مثلِ
 * «Cart updated.» عیناً مقایسه‌ش کرد)، این‌جا با ردِ پایِ ثابتِ خودِ این پیام
 * (لینکِ class="restore-item" که فقط همین یک پیام داره) شناسایی می‌شه.
 * همون استدلالِ نوتیسِ «Cart updated.»: صفحه‌ی سبد همین الان (با حذفِ خودِ
 * ردیف از DOM) نتیجه رو نشون داده، این جعبه‌ی خامِ بی‌استایل کاملاً تکراریه.
 */
function jluxe_suppress_cart_item_removed_notice( $message ) {
	if ( is_string( $message ) && false !== strpos( $message, 'restore-item' ) ) {
		return '';
	}
	return $message;
}

/**
 * جعبه‌ی خامِ «سبد خرید شما در حال حاضر خالی است» — طبقِ درخواستِ کاربر
 * («این کادر رو زیبا‌تر کن»). این متن از خودِ wc_empty_cart_message()ِ
 * ووکامرس میاد (do_action('woocommerce_cart_is_empty') در
 * woocommerce/cart/cart-empty.php)، با کلاسِ خامِ .woocommerce-info —
 * کاملاً تکراریِ همون عنوانِ استایل‌شده‌ی «سبد خرید شما خالی است» که همین
 * تمپلیت خودش بالاترش چاپ کرده. به‌جایِ remove_action کاملِ خودِ اکشن
 * (که ریسکِ حذفِ محتوایِ افزونه‌های دیگه‌ای که به همین اکشن قلاب زدن رو
 * داره)، فقط همین یک callbackِ خامِ خودِ ووکامرس حذف می‌شه؛ نقطه‌ی اکشن
 * برای هر افزونه‌ی دیگه دست‌نخورده می‌مونه. مثلِ بقیه‌ی remove_action های
 * همین فایل، داخلِ هوکِ 'wp' صدا زده می‌شه (نه مستقیم موقعِ لودشدنِ فایل)
 * تا مطمئن باشیم خودِ ووکامرس قبلش هوکش رو ثبت کرده.
 */
function jluxe_remove_empty_cart_default_message(): void {
	remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
}

/** Preserve Woo's state/nonce/extension-filtered actions; never invent a pay URL. */
function jluxe_render_customer_order_actions( $order ): void {
	if ( ! $order instanceof WC_Order || get_current_user_id() < 1 ||
		(int) $order->get_customer_id() !== get_current_user_id() ) { return; }
	$actions = wc_get_account_orders_actions( $order );
	if ( empty( $actions ) ) { return; }
	echo '<div class="flex flex-wrap gap-2 px-4 pb-4" data-jluxe-order-actions>';
	foreach ( $actions as $key => $action ) {
		if ( empty( $action['url'] ) || empty( $action['name'] ) ) { continue; }
		$action_class = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $key );
		$action_label = $action['aria-label'] ?? sprintf( '%s — #%s', wp_strip_all_tags( $action['name'] ), $order->get_order_number() );
		echo '<a class="' . esc_attr( $action_class ) . ' inline-flex h-11 items-center justify-center rounded-xl border border-border px-4 py-2 text-caption font-bold text-primary" href="' . esc_url( $action['url'] ) . '" aria-label="' . esc_attr( $action_label ) . '">' . esc_html( $action['name'] ) . '</a>';
	}
	echo '</div>';
}
