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
	$address_parts = array_filter(
		array(
			jluxe_order_label_address_value( $order, $address_type, 'address_1' ),
			jluxe_order_label_address_value( $order, $address_type, 'address_2' ),
			jluxe_order_label_address_value( $order, $address_type, 'city' ),
			$state,
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

/** Minimal A5-landscape print document with the A6 label in the left half. */
function jluxe_render_order_label_document( WC_Order $order ): void {
	$data      = jluxe_order_label_data( $order );
	$store     = $data['store'];
	$recipient = $data['recipient'];
	$font_uri  = function_exists( 'get_template_directory_uri' ) ? trailingslashit( get_template_directory_uri() ) . 'src/assets/fonts/' : '';
	$logo_alt  = $store['name'];
	?>
	<!doctype html>
	<html lang="fa-IR" dir="rtl">
	<head>
		<meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow,noarchive">
		<title><?php echo esc_html( sprintf( 'برچسب پستی سفارش %s', $data['order_number'] ) ); ?></title>
		<style>
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileRegular.woff2' ); ?>') format('woff2');font-weight:400;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileMedium.woff2' ); ?>') format('woff2');font-weight:500;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeLabel;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileBold.woff2' ); ?>') format('woff2');font-weight:700;font-style:normal;font-display:swap}
			*{box-sizing:border-box}
			html{direction:rtl;background:#ececec}
			body{min-height:100vh;margin:0;padding:8mm;display:flex;flex-direction:column;align-items:center;gap:4mm;background:#ececec;color:#111;font-family:JLuxeLabel,Tahoma,sans-serif;text-align:right;-webkit-print-color-adjust:exact;print-color-adjust:exact}
			.jluxe-label-toolbar{display:flex;align-items:center;gap:4mm;font-size:12px;color:#333}
			.jluxe-label-toolbar button{min-height:44px;padding:0 18px;border:0;border-radius:8px;background:#171717;color:#fff;font:inherit;cursor:pointer}
			.jluxe-label-sheet{width:210mm;height:148mm;flex:0 0 auto;display:flex;direction:ltr;align-items:flex-start;justify-content:flex-start;background:#fff}
			.jluxe-shipping-label{width:99mm;height:140mm;flex:0 0 auto;margin:4mm 3mm;overflow:hidden;padding:2.5mm;border:.65mm solid #171717;border-radius:5mm;background:#fff;display:flex;flex-direction:column}
			.jluxe-label-sender{height:44mm;min-height:44mm;display:flex;direction:rtl;align-items:stretch}
			.jluxe-label-logo{width:28mm;flex:0 0 28mm;display:flex;align-items:center;justify-content:center;padding-inline:1.5mm;border-inline-end:.45mm solid #666;text-align:center}
			.jluxe-label-logo img{display:block;max-width:24mm;max-height:30mm;width:auto;height:auto;object-fit:contain}
			.jluxe-label-logo-fallback{font-size:3.2mm;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-sender-info{min-width:0;flex:1;padding-inline-start:2.5mm;display:flex;flex-direction:column;align-items:stretch;gap:1.4mm}
			.jluxe-label-order-number{margin:0 0 1mm;font-size:4mm;font-weight:700;line-height:1.35;text-align:right}
			.jluxe-label-line{margin:0;font-size:2.65mm;font-weight:400;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-line strong{font-weight:700}
			.jluxe-label-ltr{direction:ltr;unicode-bidi:isolate;display:inline-block;white-space:nowrap}
			.jluxe-label-recipient{min-height:0;flex:1;margin-top:2mm;padding:4mm 3.5mm;border:.55mm solid #171717;border-radius:19mm;text-align:center;display:flex;flex-direction:column;align-items:center;overflow:hidden}
			.jluxe-label-recipient-title{flex:0 0 auto;margin:0 auto 2mm;padding:1mm 3.5mm;border-radius:999px;background:#050505;color:#fff;font-size:3.5mm;font-weight:700;line-height:1.55}
			.jluxe-label-recipient-name{max-width:100%;margin:0 0 2.5mm;font-size:3.2mm;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-recipient-address{max-width:100%;margin:0 0 2mm;font-size:2.8mm;font-weight:400;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-recipient-line{max-width:100%;margin:0 0 1.7mm;font-size:2.65mm;line-height:1.5;overflow-wrap:anywhere}
			.jluxe-label-recipient-line strong{font-weight:500;color:#555}
			.jluxe-label-note{width:100%;margin:2mm 0 0;padding-top:2mm;border-top:.3mm solid #b5b5b5;text-align:right;font-size:2.55mm;line-height:1.55;overflow-wrap:anywhere;white-space:pre-wrap}
			.jluxe-label-note-title{display:block;margin-bottom:.8mm;font-weight:700}
			@page{size:210mm 148mm;margin:0}
			@media (max-width:600px){body{padding:4mm}.jluxe-label-toolbar{flex-wrap:wrap;justify-content:center;text-align:center}}
			@media print{html,body{width:210mm;height:148mm;min-height:0;margin:0;padding:0;background:#fff}body{display:block}.jluxe-label-toolbar{display:none}.jluxe-label-sheet{width:210mm;height:148mm}.jluxe-shipping-label{width:99mm;height:140mm;margin:4mm 3mm;border-radius:5mm;box-shadow:none}}
		</style>
	</head>
	<body>
		<div class="jluxe-label-toolbar" aria-label="ابزار چاپ">
			<button type="button" onclick="window.print()">چاپ برچسب</button>
			<span>برگهٔ A5 افقی؛ برچسب در نیمهٔ چپ (نصف A5)</span>
		</div>
		<div class="jluxe-label-sheet">
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
			<section class="jluxe-label-recipient" aria-labelledby="jluxe-label-recipient-heading">
				<h1 id="jluxe-label-recipient-heading" class="jluxe-label-recipient-title">اطلاعات گیرنده</h1>
				<?php if ( '' !== $recipient['name'] ) : ?>
					<p class="jluxe-label-recipient-name"><?php echo esc_html( $recipient['name'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $recipient['address'] ) : ?>
					<p class="jluxe-label-recipient-address"><?php echo esc_html( $recipient['address'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $recipient['postcode'] ) : ?>
					<p class="jluxe-label-recipient-line"><strong>کد پستی:</strong> <bdi class="jluxe-label-ltr"><?php echo esc_html( $recipient['postcode'] ); ?></bdi></p>
				<?php endif; ?>
				<?php if ( '' !== $recipient['phone'] ) : ?>
					<p class="jluxe-label-recipient-line"><strong>شماره تماس:</strong> <bdi class="jluxe-label-ltr"><?php echo esc_html( $recipient['phone'] ); ?></bdi></p>
				<?php endif; ?>
				<?php if ( '' !== $recipient['note'] ) : ?>
					<p class="jluxe-label-note"><span class="jluxe-label-note-title">توضیحات سفارش</span><?php echo nl2br( esc_html( $recipient['note'] ), false ); ?></p>
				<?php endif; ?>
			</section>
		</main>
		</div>
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

add_action( 'admin_post_jluxe_print_order_shipping_label', 'jluxe_handle_order_label_print' );
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
