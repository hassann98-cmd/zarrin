<?php
/**
 * Private, printable A6 shipping label for a WooCommerce order.
 *
 * The label is rendered only from the authenticated WooCommerce admin, uses
 * configured store/contact details, and prints the customer's checkout note
 * (never private order notes or payment details).
 */

defined( 'ABSPATH' ) || exit;

/** Return a plain, trimmed value suitable for a printed label. */
function jluxe_order_label_plain_text( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$text = (string) $value;
	$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
	return trim( $text );
}

/** Read one address component through the WooCommerce order data API. */
function jluxe_order_label_address_value( WC_Order $order, string $type, string $field ): string {
	$method = 'get_' . $type . '_' . $field;
	if ( ! method_exists( $order, $method ) ) {
		return '';
	}
	return jluxe_order_label_plain_text( $order->{$method}() );
}

/** Resolve a state/province code with WooCommerce's localized country data. */
function jluxe_order_label_localized_state( string $country, string $state ): string {
	if ( '' === $country || '' === $state || ! function_exists( 'WC' ) ) {
		return $state;
	}
	$woocommerce = WC();
	if ( ! is_object( $woocommerce ) || ! isset( $woocommerce->countries ) || ! is_object( $woocommerce->countries ) || ! method_exists( $woocommerce->countries, 'get_states' ) ) {
		return $state;
	}
	$states = $woocommerce->countries->get_states( $country );
	return is_array( $states ) && isset( $states[ $state ] ) ? jluxe_order_label_plain_text( $states[ $state ] ) : $state;
}

/** Collect the actual store details configured in WooCommerce/the theme. */
function jluxe_order_label_store_data(): array {
	$settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
	$contact  = isset( $settings['contact'] ) && is_array( $settings['contact'] ) ? $settings['contact'] : array();

	$address_parts = array_filter(
		array_map(
			'jluxe_order_label_plain_text',
			array(
				get_option( 'woocommerce_store_address', '' ),
				get_option( 'woocommerce_store_address_2', '' ),
				get_option( 'woocommerce_store_city', '' ),
			)
		),
		static function ( $part ) {
			return '' !== $part;
		}
	);
	$address = implode( '، ', $address_parts );
	if ( '' === $address ) {
		$address = jluxe_order_label_plain_text( $contact['address'] ?? '' );
	}

	$phones = array_filter(
		array_unique(
			array_map(
				'jluxe_order_label_plain_text',
				array( $contact['phone'] ?? '', $contact['phone_secondary'] ?? '' )
			)
		),
		static function ( $phone ) {
			return '' !== $phone;
		}
	);

	return array(
		'name'     => function_exists( 'get_bloginfo' ) ? jluxe_order_label_plain_text( get_bloginfo( 'name' ) ) : '',
		'logo'     => function_exists( 'jluxe_get_logo_url' ) ? (string) jluxe_get_logo_url() : '',
		'address'  => $address,
		'postcode' => jluxe_order_label_plain_text( get_option( 'woocommerce_store_postcode', '' ) ),
		'phone'    => implode( ' — ', $phones ),
	);
}

/**
 * Prepare only the fields needed on the postage label.
 * A populated shipping destination always wins; billing is a safe fallback
 * when the order has no shipping address (e.g. virtual/no-shipping orders).
 */
function jluxe_order_label_data( WC_Order $order ): array {
	$shipping_fields = array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
	$has_shipping    = false;
	foreach ( $shipping_fields as $field ) {
		if ( '' !== jluxe_order_label_address_value( $order, 'shipping', $field ) ) {
			$has_shipping = true;
			break;
		}
	}
	$address_type = $has_shipping ? 'shipping' : 'billing';

	$first_name = jluxe_order_label_address_value( $order, 'shipping', 'first_name' );
	$last_name  = jluxe_order_label_address_value( $order, 'shipping', 'last_name' );
	if ( '' === trim( $first_name . ' ' . $last_name ) ) {
		$first_name = jluxe_order_label_address_value( $order, 'billing', 'first_name' );
		$last_name  = jluxe_order_label_address_value( $order, 'billing', 'last_name' );
	}
	$name = trim( $first_name . ' ' . $last_name );

	$country = jluxe_order_label_address_value( $order, $address_type, 'country' );
	$state   = jluxe_order_label_localized_state( $country, jluxe_order_label_address_value( $order, $address_type, 'state' ) );
	$city    = jluxe_order_label_address_value( $order, $address_type, 'city' );
	$address_parts = array_filter(
		array(
			jluxe_order_label_address_value( $order, $address_type, 'address_1' ),
			jluxe_order_label_address_value( $order, $address_type, 'address_2' ),
		),
		static function ( $part ) {
			return '' !== $part;
		}
	);

	$phone = method_exists( $order, 'get_shipping_phone' )
		? jluxe_order_label_plain_text( $order->get_shipping_phone() )
		: '';
	if ( '' === $phone ) {
		$phone = jluxe_order_label_address_value( $order, 'billing', 'phone' );
	}

	$customer_note = method_exists( $order, 'get_customer_note' )
		? ( function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( (string) $order->get_customer_note() ) : jluxe_order_label_plain_text( $order->get_customer_note() ) )
		: '';

	return array(
		'order_number' => jluxe_order_label_plain_text( $order->get_order_number() ),
		'store'        => jluxe_order_label_store_data(),
		'recipient'    => array(
			'name'     => $name,
			'state'    => $state,
			'city'     => $city,
			'address'  => implode( '، ', $address_parts ),
			'postcode' => jluxe_order_label_address_value( $order, $address_type, 'postcode' ),
			'phone'    => $phone,
			'note'     => trim( (string) $customer_note ),
		),
	);
}

/** Whether the current administrator may view personal order details. */
function jluxe_order_label_user_can_print( int $order_id ): bool {
	return $order_id > 0 && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_order', $order_id ) );
}

/** Build the nonce-protected private print URL for one order. */
function jluxe_order_label_admin_url( int $order_id ): string {
	if ( $order_id < 1 ) {
		return '';
	}
	return add_query_arg(
		array(
			'action'   => 'jluxe_print_order_shipping_label',
			'order_id' => $order_id,
			'_wpnonce' => wp_create_nonce( 'jluxe_print_order_shipping_label_' . $order_id ),
		),
		admin_url( 'admin-post.php' )
	);
}

/** Add a print button beside the shipping address in the order editor. */
function jluxe_render_order_label_admin_button( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
		return;
	}
	$order_id = absint( $order->get_id() );
	if ( ! jluxe_order_label_user_can_print( $order_id ) ) {
		return;
	}
	$url = jluxe_order_label_admin_url( $order_id );
	?>
	<p class="jluxe-order-label-admin-action">
		<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="dashicons dashicons-printer" aria-hidden="true"></span>
			<?php echo esc_html__( 'چاپ برچسب پستی (A6)', 'jluxe' ); ?>
		</a>
	</p>
	<?php
}

/** Add a printer action to each order row in both WooCommerce order lists. */
function jluxe_add_order_label_list_action( array $actions, $order ): array {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
		return $actions;
	}
	$order_id = absint( $order->get_id() );
	if ( ! jluxe_order_label_user_can_print( $order_id ) ) {
		return $actions;
	}
	$actions['jluxe-shipping-label'] = array(
		'url'    => jluxe_order_label_admin_url( $order_id ),
		'name'   => __( 'چاپ برچسب پستی (A6)', 'jluxe' ),
		'action' => 'jluxe-shipping-label',
	);
	return $actions;
}

/** Normalize bulk-selected order IDs without accepting nested or malformed input. */
function jluxe_order_label_normalize_order_ids( $values ): array {
	if ( ! is_array( $values ) ) {
		return array();
	}
	$ids = array();
	foreach ( $values as $value ) {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return array();
		}
		$id = absint( $value );
		if ( $id < 1 ) {
			return array();
		}
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/** Build a nonce-bound print URL for the selected orders. */
function jluxe_order_label_bulk_print_url( array $order_ids ): string {
	$ids = jluxe_order_label_normalize_order_ids( $order_ids );
	if ( empty( $ids ) || count( $ids ) > 100 ) {
		return '';
	}
	foreach ( $ids as $order_id ) {
		if ( ! jluxe_order_label_user_can_print( $order_id ) ) {
			return '';
		}
	}
	$ids_string = implode( ',', $ids );
	return add_query_arg(
		array(
			'action'    => 'jluxe_print_order_shipping_labels',
			'order_ids' => $ids_string,
			'_wpnonce'  => wp_create_nonce( 'jluxe_print_order_shipping_labels_' . $ids_string ),
		),
		admin_url( 'admin-post.php' )
	);
}

/** Add the group-print action to the legacy WooCommerce order list. */
function jluxe_order_label_add_bulk_action( array $actions ): array {
	$actions['jluxe_print_shipping_labels'] = __( 'چاپ برچسب سفارش‌های انتخاب‌شده (A6)', 'jluxe' );
	return $actions;
}

/** Route a valid bulk selection to the private multi-label print document. */
function jluxe_order_label_handle_bulk_action( string $redirect_to, string $action, $order_ids ): string {
	if ( 'jluxe_print_shipping_labels' !== $action ) {
		return $redirect_to;
	}
	$ids = jluxe_order_label_normalize_order_ids( $order_ids );
	if ( empty( $ids ) || count( $ids ) > 100 ) {
		return add_query_arg( 'jluxe_order_label_bulk_error', empty( $ids ) ? 'empty' : 'limit', $redirect_to );
	}
	$url = jluxe_order_label_bulk_print_url( $ids );
	if ( '' === $url ) {
		return add_query_arg( 'jluxe_order_label_bulk_error', 'permission', $redirect_to );
	}
	return $url;
}

/** Explain why a bulk print action could not be started. */
function jluxe_order_label_bulk_admin_notice(): void {
	if ( ! isset( $_GET['jluxe_order_label_bulk_error'] ) || ! is_scalar( $_GET['jluxe_order_label_bulk_error'] ) ) {
		return;
	}
	$error = sanitize_key( wp_unslash( $_GET['jluxe_order_label_bulk_error'] ) );
	$messages = array(
		'empty'      => 'برای چاپ گروهی، دست‌کم یک سفارش را انتخاب کنید.',
		'limit'      => 'در هر نوبت حداکثر ۱۰۰ سفارش را انتخاب کنید.',
		'permission' => 'برای چاپ همهٔ سفارش‌های انتخاب‌شده مجوز کافی ندارید.',
	);
	if ( isset( $messages[ $error ] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $messages[ $error ] ) . '</p></div>';
	}
}

/** Inject the custom action into HPOS's bulk selectors (its list table bypasses core bulk-action filters). */
function jluxe_order_label_hpos_bulk_action_script(): string {
	$label = wp_json_encode( __( 'چاپ برچسب سفارش‌های انتخاب‌شده (A6)', 'jluxe' ) );
	return '(function(){var label=' . $label . ';document.querySelectorAll("select[id^=bulk-action-selector-]").forEach(function(select){if(select.querySelector("option[value=jluxe_print_shipping_labels]")){return;}var option=document.createElement("option");option.value="jluxe_print_shipping_labels";option.textContent=label;select.appendChild(option);});})();';
}

/** Print one label in the established single-order flow. */
function jluxe_render_order_label_document( WC_Order $order ): void {
	jluxe_render_order_labels_document( array( $order ) );
}

/** Render one A5 landscape sheet per pair of orders, with two A6 labels side by side. */
function jluxe_render_order_labels_document( array $orders ): void {
	$labels = array();
	foreach ( $orders as $order ) {
		if ( $order instanceof WC_Order ) {
			$labels[] = jluxe_order_label_data( $order );
		}
	}
	if ( empty( $labels ) ) {
		return;
	}
	$font_uri = function_exists( 'get_template_directory_uri' ) ? trailingslashit( get_template_directory_uri() ) . 'src/assets/fonts/' : '';
	$label_count = count( $labels );
	$toolbar_hint = 1 === $label_count
		? 'برگهٔ A5 افقی؛ برچسب در نیمهٔ چپ (نصف A5)'
		: 'برگهٔ A5 افقی؛ در هر برگه دو برچسب کنار هم (نصف A5)';
	?>
	<!doctype html>
	<html lang="fa-IR" dir="rtl">
	<head>
		<meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow,noarchive">
		<title><?php echo esc_html( sprintf( 'چاپ برچسب پستی %d سفارش', $label_count ) ); ?></title>
		<style>
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileRegular.woff2' ); ?>') format('woff2');font-weight:400;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileMedium.woff2' ); ?>') format('woff2');font-weight:500;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileBold.woff2' ); ?>') format('woff2');font-weight:700;font-style:normal;font-display:swap}
			*{box-sizing:border-box}
			html{direction:rtl;background:#ececec}
			body{min-height:100vh;margin:0;padding:8mm;display:flex;flex-direction:column;align-items:center;gap:4mm;background:#ececec;color:#111;font-family:JLuxeLabel,Tahoma,sans-serif;text-align:right;-webkit-print-color-adjust:exact;print-color-adjust:exact}
			.jluxe-label-toolbar{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:4mm;font-size:12px;color:#333}
			.jluxe-label-toolbar button{min-height:44px;padding:0 18px;border:0;border-radius:8px;background:#171717;color:#fff;font:inherit;cursor:pointer}
			.jluxe-label-sheet{width:210mm;height:148mm;flex:0 0 auto;display:flex;direction:ltr;align-items:flex-start;justify-content:flex-start;background:#fff;break-after:page;page-break-after:always;break-inside:avoid;page-break-inside:avoid}
			.jluxe-label-sheet:last-of-type{break-after:auto;page-break-after:auto}
			.jluxe-shipping-label{width:99mm;height:140mm;flex:0 0 99mm;margin:4mm 3mm;overflow:visible;padding:2.5mm;border:.65mm solid #171717;border-radius:5mm;background:#fff;display:flex;flex-direction:column;direction:rtl;text-align:right}
			.jluxe-label-sender{height:44mm;min-height:44mm;display:flex;direction:rtl;align-items:stretch}
			.jluxe-label-logo{width:28mm;flex:0 0 28mm;display:flex;align-items:center;justify-content:center;padding-inline:1.5mm;border-inline-end:.45mm solid #666;text-align:center}
			.jluxe-label-logo img{display:block;max-width:24mm;max-height:30mm;width:auto;height:auto;object-fit:contain}
			.jluxe-label-logo-fallback{font-size:3.2mm;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-sender-info{min-width:0;flex:1;padding-inline-start:2.5mm;display:flex;flex-direction:column;align-items:stretch;gap:1.4mm}
			.jluxe-label-order-number{margin:0 0 1mm;font-size:4mm;font-weight:700;line-height:1.35;text-align:right}
			.jluxe-label-line{margin:0;font-size:2.65mm;font-weight:400;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-line strong{font-weight:700}
			.jluxe-label-ltr{direction:ltr;unicode-bidi:isolate;display:inline-block;white-space:nowrap}
			.jluxe-label-recipient{min-height:0;flex:1;margin-top:2mm;padding:3.5mm 3mm;border:.55mm solid #171717;border-radius:14mm;text-align:right;display:flex;flex-direction:column;align-items:stretch;overflow:visible}
			.jluxe-label-recipient-title{flex:0 0 auto;align-self:center;margin:0 auto 2mm;padding:1mm 3.5mm;border-radius:999px;background:#050505;color:#fff;font-size:3.5mm;font-weight:700;line-height:1.55;text-align:center}
			.jluxe-label-recipient-name{width:100%;margin:0 0 1.2mm;font-size:3.7mm;font-weight:700;line-height:1.5;text-align:right;overflow-wrap:anywhere}
			.jluxe-label-recipient-location{width:100%;margin:0 0 1.2mm;font-size:3.2mm;font-weight:700;line-height:1.5;text-align:right;overflow-wrap:anywhere}
			.jluxe-label-recipient-address{width:100%;margin:0 0 1.4mm;font-size:4mm;font-weight:500;line-height:1.5;text-align:right;overflow-wrap:anywhere}
			.jluxe-label-recipient-line{width:100%;margin:0 0 1.4mm;font-size:3.1mm;line-height:1.5;text-align:right;direction:rtl;display:flex;align-items:center;justify-content:flex-start;gap:1.2mm;overflow-wrap:anywhere}
			.jluxe-label-recipient-line strong{font-weight:700;color:#333}
			.jluxe-label-recipient-icon{width:4mm;height:4mm;flex:0 0 4mm;display:inline-flex;align-items:center;justify-content:center;color:#333}
			.jluxe-label-recipient-icon svg{display:block;width:100%;height:100%;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
			.jluxe-label-note{width:100%;margin:1.2mm 0 1.8mm;padding:1.5mm 2mm;border:.3mm solid #777;border-radius:2mm;background:#f7f7f7;text-align:right;font-size:2.55mm;line-height:1.4;overflow-wrap:anywhere;white-space:pre-wrap}
			.jluxe-label-note-title{display:block;margin-bottom:.7mm;font-weight:700}
			@page{size:210mm 148mm;margin:0}
			@media (max-width:600px){body{padding:4mm}.jluxe-label-toolbar{flex-wrap:wrap;justify-content:center;text-align:center}.jluxe-label-sheet{transform-origin:top center;transform:scale(.72);margin-bottom:-40mm}}
			@media print{html,body{width:210mm;min-height:0;margin:0;padding:0;background:#fff}body{display:block}.jluxe-label-toolbar{display:none}.jluxe-label-sheet{width:210mm;height:148mm;margin:0;transform:none}.jluxe-shipping-label{width:99mm;height:140mm;margin:4mm 3mm;border-radius:5mm;box-shadow:none}}
		</style>
	</head>
	<body>
		<div class="jluxe-label-toolbar" aria-label="ابزار چاپ">
			<button type="button" onclick="window.print()">چاپ <?php echo esc_html( $label_count ); ?> برچسب</button>
			<span><?php echo esc_html( $toolbar_hint ); ?></span>
		</div>
		<?php foreach ( array_chunk( $labels, 2 ) as $page_index => $page_labels ) : ?>
			<div class="jluxe-label-sheet" aria-label="برگهٔ <?php echo esc_attr( $page_index + 1 ); ?>">
				<?php foreach ( $page_labels as $card_index => $data ) : ?>
					<?php jluxe_render_order_label_card( $data, ( $page_index * 2 ) + $card_index + 1 ); ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<script>
			window.addEventListener('load', function () {
				var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
				Promise.resolve(fontsReady).then(function () {
					window.setTimeout(function () { window.print(); }, 250);
				});
			});
		</script>
	</body>
	</html>
	<?php
}

/** Render one order card, using right-to-left Persian text and theme icons/fonts. */
function jluxe_render_order_label_card( array $data, int $card_index ): void {
	$store     = $data['store'];
	$recipient = $data['recipient'];
	$logo_alt  = $store['name'];
	$heading_id = 'jluxe-label-recipient-heading-' . $card_index;
	?>
	<main class="jluxe-shipping-label" role="document" aria-label="برچسب پستی سفارش <?php echo esc_attr( $data['order_number'] ); ?>">
		<header class="jluxe-label-sender">
			<div class="jluxe-label-logo">
				<?php if ( '' !== $store['logo'] ) : ?>
					<img src="<?php echo esc_url( $store['logo'] ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>">
				<?php elseif ( '' !== $logo_alt ) : ?>
					<span class="jluxe-label-logo-fallback"><?php echo esc_html( $logo_alt ); ?></span>
				<?php endif; ?>
			</div>
			<div class="jluxe-label-sender-info">
				<p class="jluxe-label-order-number">سفارش <bdi class="jluxe-label-ltr">#<?php echo esc_html( $data['order_number'] ); ?></bdi></p>
				<?php if ( '' !== $store['address'] ) : ?>
					<p class="jluxe-label-line"><strong>آدرس:</strong> <?php echo esc_html( $store['address'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $store['postcode'] ) : ?>
					<p class="jluxe-label-line"><strong>کد پستی:</strong> <bdi class="jluxe-label-ltr"><?php echo esc_html( $store['postcode'] ); ?></bdi></p>
				<?php endif; ?>
				<?php if ( '' !== $store['phone'] ) : ?>
					<p class="jluxe-label-line"><strong>شماره تماس:</strong> <bdi class="jluxe-label-ltr"><?php echo esc_html( $store['phone'] ); ?></bdi></p>
				<?php endif; ?>
			</div>
		</header>
		<section class="jluxe-label-recipient" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<h1 id="<?php echo esc_attr( $heading_id ); ?>" class="jluxe-label-recipient-title">اطلاعات گیرنده</h1>
			<?php if ( '' !== $recipient['name'] ) : ?>
				<p class="jluxe-label-recipient-name"><?php echo esc_html( $recipient['name'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $recipient['state'] || '' !== $recipient['city'] ) : ?>
				<p class="jluxe-label-recipient-location">
					<?php if ( '' !== $recipient['state'] ) : ?><span class="jluxe-label-province"><?php echo esc_html( $recipient['state'] ); ?></span><?php endif; ?>
					<?php if ( '' !== $recipient['state'] && '' !== $recipient['city'] ) : ?><span class="jluxe-label-location-separator" aria-hidden="true"> — </span><?php endif; ?>
					<?php if ( '' !== $recipient['city'] ) : ?><span class="jluxe-label-city"><?php echo esc_html( $recipient['city'] ); ?></span><?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $recipient['address'] ) : ?>
				<p class="jluxe-label-recipient-address"><?php echo esc_html( $recipient['address'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $recipient['note'] ) : ?>
				<p class="jluxe-label-note"><span class="jluxe-label-note-title">توضیحات سفارش</span><?php echo nl2br( esc_html( $recipient['note'] ), false ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $recipient['postcode'] ) : ?>
				<p class="jluxe-label-recipient-line">
					<span class="jluxe-label-recipient-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg></span>
					<strong>کد پستی:</strong><bdi class="jluxe-label-ltr"><?php echo esc_html( $recipient['postcode'] ); ?></bdi>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $recipient['phone'] ) : ?>
				<p class="jluxe-label-recipient-line">
					<span class="jluxe-label-recipient-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5.5 3.8h3.2l2 4.8-2.1 1.5a15 15 0 0 0 5.3 5.3l1.5-2.1 4.8 2v3.2a1.7 1.7 0 0 1-1.8 1.7A15.8 15.8 0 0 1 3.8 5.6a1.7 1.7 0 0 1 1.7-1.8z"></path></svg></span>
					<strong>تلفن:</strong><bdi class="jluxe-label-ltr"><?php echo esc_html( $recipient['phone'] ); ?></bdi>
				</p>
			<?php endif; ?>
		</section>
	</main>
	<?php
}

/** Handle the private, nonce-protected print request. */
function jluxe_handle_order_label_print(): void {
	$order_id = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
	if ( ! $order_id || ! jluxe_order_label_user_can_print( $order_id ) ) {
		wp_die( esc_html__( 'اجازهٔ مشاهدهٔ این سفارش را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'jluxe_print_order_shipping_label_' . $order_id );

	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order instanceof WC_Order ) {
		wp_die( esc_html__( 'سفارش پیدا نشد.', 'jluxe' ), '', array( 'response' => 404 ) );
	}

	nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	jluxe_render_order_label_document( $order );
	exit;
}

/** Handle a bulk print request without trusting IDs supplied in the URL. */
function jluxe_handle_order_labels_print(): void {
	$raw_ids = isset( $_GET['order_ids'] ) && is_scalar( $_GET['order_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ids'] ) ) : '';
	if ( '' === $raw_ids || ! preg_match( '/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $raw_ids ) ) {
		wp_die( esc_html__( 'فهرست سفارش‌ها برای چاپ معتبر نیست.', 'jluxe' ), '', array( 'response' => 400 ) );
	}
	$ids = jluxe_order_label_normalize_order_ids( explode( ',', $raw_ids ) );
	if ( empty( $ids ) || count( $ids ) > 100 || implode( ',', $ids ) !== $raw_ids ) {
		wp_die( esc_html__( 'فهرست سفارش‌ها برای چاپ معتبر نیست یا بیش از ۱۰۰ سفارش دارد.', 'jluxe' ), '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'jluxe_print_order_shipping_labels_' . $raw_ids );

	$orders = array();
	foreach ( $ids as $order_id ) {
		if ( ! jluxe_order_label_user_can_print( $order_id ) ) {
			wp_die( esc_html__( 'برای چاپ یکی از سفارش‌های انتخاب‌شده مجوز ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'یکی از سفارش‌های انتخاب‌شده پیدا نشد.', 'jluxe' ), '', array( 'response' => 404 ) );
		}
		$orders[] = $order;
	}

	nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	jluxe_render_order_labels_document( $orders );
	exit;
}

/** Add the action to legacy WooCommerce order bulk menus. */
add_filter( 'bulk_actions-edit-shop_order', 'jluxe_order_label_add_bulk_action', 20 );
add_filter( 'handle_bulk_actions-edit-shop_order', 'jluxe_order_label_handle_bulk_action', 20, 3 );
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'jluxe_order_label_handle_bulk_action', 20, 3 );
add_action( 'admin_post_jluxe_print_order_shipping_label', 'jluxe_handle_order_label_print' );
add_action( 'admin_post_jluxe_print_order_shipping_labels', 'jluxe_handle_order_labels_print' );
add_action( 'admin_notices', 'jluxe_order_label_bulk_admin_notice' );
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_render_order_label_admin_button', 30, 1 );
add_filter( 'woocommerce_admin_order_actions', 'jluxe_add_order_label_list_action', 30, 2 );

/** Add a printer glyph to the custom action in WooCommerce's order list. */
function jluxe_order_label_admin_action_icon(): void {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! is_object( $screen ) || ! in_array( (string) ( $screen->id ?? '' ), array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
		return;
	}
	echo '<style>.wc-action-button-jluxe-shipping-label::after{font-family:dashicons;content:"\f193"}</style>';
}
add_action( 'admin_head', 'jluxe_order_label_admin_action_icon', 30 );

/** Add the bulk option to HPOS selectors, whose WooCommerce list table skips core bulk-action filters. */
function jluxe_order_label_hpos_bulk_action_footer(): void {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! is_object( $screen ) ) {
		return;
	}
	$screen_ids = array( 'woocommerce_page_wc-orders' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screen_ids[] = wc_get_page_screen_id( 'shop_order' );
		$screen_ids[] = wc_get_page_screen_id( 'shop-order' );
	}
	if ( ! in_array( (string) ( $screen->id ?? '' ), array_filter( $screen_ids, 'is_string' ), true ) ) {
		return;
	}
	echo '<script id="jluxe-order-label-bulk-action">' . jluxe_order_label_hpos_bulk_action_script() . '</script>';
}
add_action( 'admin_footer', 'jluxe_order_label_hpos_bulk_action_footer', 99 );
