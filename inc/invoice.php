<?php
/**
 * Private WooCommerce order invoices and their independent print settings.
 * Order details are rendered only for an authorized store manager or the
 * authenticated owner of the order; no public AJAX route is registered.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_INVOICE_SETTINGS_OPTION = 'jluxe_invoice_settings';
const JLUXE_INVOICE_PAGE            = 'jluxe-invoice-builder';

/** Build initial seller details from WooCommerce and the existing theme identity. */
function jluxe_invoice_default_settings(): array {
	$store   = function_exists( 'jluxe_order_label_store_data' ) ? jluxe_order_label_store_data() : array();
	$contact = function_exists( 'jluxe_get_theme_settings' ) ? (array) ( jluxe_get_theme_settings()['contact'] ?? array() ) : array();
	$logo_id = function_exists( 'get_theme_mod' ) ? absint( get_theme_mod( 'custom_logo' ) ) : 0;
	$logo_url = $logo_id && function_exists( 'wp_get_attachment_image_url' )
		? (string) wp_get_attachment_image_url( $logo_id, 'full' )
		: '';
	if ( '' === $logo_url && ! empty( $store['logo'] ) ) {
		$logo_url = (string) $store['logo'];
	}

	return array(
		'company_name'     => (string) ( $store['name'] ?? ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '' ) ),
		'company_phone'    => (string) ( $store['phone'] ?? ( $contact['phone'] ?? '' ) ),
		'company_email'    => (string) get_option( 'admin_email', '' ),
		'company_website'  => (string) home_url( '/' ),
		'company_postcode' => (string) ( $store['postcode'] ?? get_option( 'woocommerce_store_postcode', '' ) ),
		'company_address'  => (string) ( $store['address'] ?? '' ),
		'company_logo_id'  => $logo_id,
		'company_logo_url' => $logo_url,
		'enable_invoice'   => true,
		'show_logo'        => true,
		'auto_print'       => true,
		'default_size'     => 'a5',
		'footer_text'      => 'سپاس از خرید شما',
	);
}

/** Sanitize a partial invoice settings payload while preserving untouched fields. */
function jluxe_invoice_sanitize_settings( $input, array $base = array() ): array {
	$defaults = jluxe_invoice_default_settings();
	$settings = array_merge( $defaults, $base );
	if ( ! is_array( $input ) ) {
		return $settings;
	}

	$text_fields = array( 'company_name', 'company_phone', 'company_postcode' );
	foreach ( $text_fields as $field ) {
		if ( array_key_exists( $field, $input ) ) {
			$value              = is_scalar( $input[ $field ] ) ? (string) $input[ $field ] : '';
			$settings[ $field ] = sanitize_text_field( $value );
		}
	}

	if ( array_key_exists( 'company_address', $input ) ) {
		$value                       = is_scalar( $input['company_address'] ) ? (string) $input['company_address'] : '';
		$settings['company_address'] = sanitize_textarea_field( $value );
	}
	if ( array_key_exists( 'company_email', $input ) ) {
		$email = is_scalar( $input['company_email'] ) ? sanitize_email( (string) $input['company_email'] ) : '';
		$settings['company_email'] = '' === $email || is_email( $email ) ? $email : '';
	}
	if ( array_key_exists( 'company_website', $input ) ) {
		$url = is_scalar( $input['company_website'] ) ? esc_url_raw( trim( (string) $input['company_website'] ) ) : '';
		$scheme = '' !== $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) : '';
		$settings['company_website'] = in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}
	if ( array_key_exists( 'company_logo_id', $input ) ) {
		$settings['company_logo_id'] = is_scalar( $input['company_logo_id'] ) ? absint( $input['company_logo_id'] ) : 0;
	}
	if ( array_key_exists( 'company_logo_url', $input ) ) {
		$url = is_scalar( $input['company_logo_url'] ) ? esc_url_raw( trim( (string) $input['company_logo_url'] ) ) : '';
		$scheme = '' !== $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) : '';
		$settings['company_logo_url'] = in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}
	if ( ! empty( $settings['company_logo_id'] ) && function_exists( 'wp_get_attachment_image_url' ) ) {
		$attachment_url = wp_get_attachment_image_url( (int) $settings['company_logo_id'], 'full' );
		if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
			$settings['company_logo_url'] = esc_url_raw( $attachment_url );
		}
	}
	foreach ( array( 'enable_invoice', 'show_logo', 'auto_print' ) as $flag ) {
		if ( array_key_exists( $flag, $input ) ) {
			$value = $input[ $flag ];
			$settings[ $flag ] = is_scalar( $value ) && ! empty( $value ) && '0' !== (string) $value;
		}
	}
	if ( array_key_exists( 'default_size', $input ) ) {
		$size = is_scalar( $input['default_size'] ) ? sanitize_key( (string) $input['default_size'] ) : '';
		$settings['default_size'] = in_array( $size, array( 'a4', 'a5' ), true ) ? $size : $defaults['default_size'];
	}
	if ( array_key_exists( 'footer_text', $input ) ) {
		$settings['footer_text'] = is_scalar( $input['footer_text'] ) ? sanitize_text_field( (string) $input['footer_text'] ) : '';
	}

	return $settings;
}

function jluxe_invoice_get_settings(): array {
	$saved = get_option( JLUXE_INVOICE_SETTINGS_OPTION, array() );
	return jluxe_invoice_sanitize_settings( is_array( $saved ) ? $saved : array(), jluxe_invoice_default_settings() );
}

/** Save only this module's option; the active theme settings remain untouched. */
function jluxe_invoice_update_settings( array $posted ): array {
	$current = jluxe_invoice_get_settings();
	$clean   = jluxe_invoice_sanitize_settings( $posted, $current );
	update_option( JLUXE_INVOICE_SETTINGS_OPTION, $clean, false );
	return $clean;
}

function jluxe_invoice_page_url( string $tab = 'seller', array $extra = array() ): string {
	$args = array_merge( array( 'page' => JLUXE_INVOICE_PAGE, 'tab' => $tab ), $extra );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function jluxe_invoice_register_menu(): void {
	add_menu_page(
		'فاکتورساز — زرین',
		'فاکتورساز',
		'manage_woocommerce',
		JLUXE_INVOICE_PAGE,
		'jluxe_invoice_render_admin_page',
		'dashicons-media-spreadsheet',
		59
	);
}
add_action( 'admin_menu', 'jluxe_invoice_register_menu', 60 );

function jluxe_invoice_enqueue_admin_assets( string $hook ): void {
	if ( 'toplevel_page_' . JLUXE_INVOICE_PAGE !== $hook ) {
		return;
	}
	if ( function_exists( 'wp_enqueue_media' ) ) {
		wp_enqueue_media();
	}
	$css_path = JLUXE_THEME_DIR . '/assets/css/invoice-builder.css';
	$js_path  = JLUXE_THEME_DIR . '/assets/js/invoice-builder.js';
	wp_enqueue_style(
		'jluxe-invoice-builder',
		JLUXE_THEME_URI . '/assets/css/invoice-builder.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
	);
	wp_enqueue_script(
		'jluxe-invoice-builder',
		JLUXE_THEME_URI . '/assets/js/invoice-builder.js',
		array(),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_invoice_enqueue_admin_assets' );

/** Handle the capability- and nonce-protected seller/settings form. */
function jluxe_invoice_handle_settings_save(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'اجازهٔ مدیریت فاکتور را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'jluxe_invoice_save_settings' );
	$posted = isset( $_POST['invoice_settings'] ) && is_array( $_POST['invoice_settings'] )
		? wp_unslash( $_POST['invoice_settings'] )
		: array();
	jluxe_invoice_update_settings( $posted );
	$tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'seller';
	if ( ! in_array( $tab, array( 'seller', 'design' ), true ) ) {
		$tab = 'seller';
	}
	wp_safe_redirect( jluxe_invoice_page_url( $tab, array( 'saved' => '1' ) ) );
	exit;
}
add_action( 'admin_post_jluxe_invoice_save_settings', 'jluxe_invoice_handle_settings_save' );

function jluxe_invoice_render_toggle( string $name, string $label, string $description, bool $checked ): void {
	?>
	<label class="jib-toggle">
		<input type="hidden" name="invoice_settings[<?php echo esc_attr( $name ); ?>]" value="0">
		<input type="checkbox" name="invoice_settings[<?php echo esc_attr( $name ); ?>]" value="1" <?php checked( $checked ); ?>>
		<span class="jib-toggle-copy"><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $description ); ?></small></span>
		<span class="jib-switch" aria-hidden="true"></span>
	</label>
	<?php
}

function jluxe_invoice_render_text_field( string $name, string $label, string $value, string $type = 'text', string $hint = '' ): void {
	$id = 'jib-' . sanitize_key( $name );
	?>
	<div class="jib-field">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $type ); ?>" name="invoice_settings[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $value ); ?>" autocomplete="off">
		<?php if ( '' !== $hint ) : ?><p><?php echo esc_html( $hint ); ?></p><?php endif; ?>
	</div>
	<?php
}

function jluxe_invoice_render_admin_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'اجازهٔ مدیریت فاکتور را ندارید.', 'jluxe' ) );
	}
	$settings = jluxe_invoice_get_settings();
	$tab      = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'seller';
	if ( ! in_array( $tab, array( 'seller', 'design', 'preview' ), true ) ) {
		$tab = 'seller';
	}
	$saved = isset( $_GET['saved'] ) && '1' === (string) $_GET['saved'];
	$tabs  = array(
		'seller'  => array( 'فروشنده و دکمه‌ها', 'dashicons-store' ),
		'design'  => array( 'چاپ و ظاهر', 'dashicons-art' ),
		'preview' => array( 'پیش‌نمایش', 'dashicons-visibility' ),
	);
	?>
	<div class="wrap" id="jluxe-invoice-builder" dir="rtl">
		<header class="jib-header">
			<div class="jib-heading">
				<span class="jib-mark" aria-hidden="true"><span class="dashicons dashicons-media-spreadsheet"></span></span>
				<div><h1>فاکتورساز</h1><p>اطلاعات فروشنده، اندازهٔ چاپ و پیش‌نمایش فاکتور سفارش‌ها</p></div>
			</div>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span>مدیریت سفارش‌ها</a>
		</header>
		<?php if ( $saved ) : ?><div class="jib-notice" role="status">تنظیمات فاکتور ذخیره شد.</div><?php endif; ?>
		<nav class="jib-tabs" aria-label="بخش‌های فاکتورساز">
			<?php foreach ( $tabs as $key => $item ) : ?>
				<a href="<?php echo esc_url( jluxe_invoice_page_url( $key ) ); ?>" <?php echo $tab === $key ? 'class="is-active" aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( $item[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $item[0] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'seller' === $tab ) : ?>
			<form class="jib-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jluxe_invoice_save_settings">
				<input type="hidden" name="tab" value="seller">
				<?php wp_nonce_field( 'jluxe_invoice_save_settings' ); ?>
				<section class="jib-card">
					<header><div><h2>اطلاعات فروشنده</h2><p>این اطلاعات در بخش «فرستنده»ی فاکتور چاپ می‌شود.</p></div></header>
					<div class="jib-grid">
						<?php jluxe_invoice_render_text_field( 'company_name', 'نام فروشگاه یا شرکت', $settings['company_name'] ); ?>
						<?php jluxe_invoice_render_text_field( 'company_phone', 'شمارهٔ تماس', $settings['company_phone'], 'tel' ); ?>
						<?php jluxe_invoice_render_text_field( 'company_email', 'ایمیل', $settings['company_email'], 'email' ); ?>
						<?php jluxe_invoice_render_text_field( 'company_website', 'وب‌سایت', $settings['company_website'], 'url' ); ?>
						<?php jluxe_invoice_render_text_field( 'company_postcode', 'کد پستی فروشگاه', $settings['company_postcode'] ); ?>
						<div class="jib-field jib-field-wide"><label for="jib-company-address">نشانی فروشگاه</label><textarea id="jib-company-address" name="invoice_settings[company_address]" rows="2"><?php echo esc_textarea( $settings['company_address'] ); ?></textarea></div>
						<div class="jib-field jib-field-wide">
							<label for="jib-company-logo-url">لوگوی فاکتور</label>
							<input type="hidden" name="invoice_settings[company_logo_id]" value="<?php echo esc_attr( (string) $settings['company_logo_id'] ); ?>" data-jib-logo-id>
							<div class="jib-logo-row">
								<div class="jib-logo-preview" data-jib-logo-preview><?php if ( '' !== $settings['company_logo_url'] ) : ?><img src="<?php echo esc_url( $settings['company_logo_url'] ); ?>" alt="پیش‌نمایش لوگو"><?php else : ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php endif; ?></div>
								<div class="jib-logo-actions">
									<input id="jib-company-logo-url" type="url" name="invoice_settings[company_logo_url]" value="<?php echo esc_attr( $settings['company_logo_url'] ); ?>" placeholder="انتخاب از رسانه‌های وردپرس" data-jib-logo-url>
									<div><button class="button" type="button" data-jib-select-logo>انتخاب تصویر</button> <button class="button" type="button" data-jib-remove-logo>حذف لوگو</button></div>
									<p>لوگوی انتخاب‌شده فقط در فاکتور چاپی استفاده می‌شود.</p>
								</div>
							</div>
						</div>
					</div>
				</section>
				<section class="jib-card">
					<header><div><h2>دسترسی به فاکتور فروش</h2><p>این گزینه دکمهٔ چاپ را برای مدیر سفارش و پیوند را برای صاحبِ واردشدهٔ سفارش فعال می‌کند.</p></div></header>
					<?php jluxe_invoice_render_toggle( 'enable_invoice', 'فاکتور فروش', 'افزودن دکمهٔ امنِ چاپ به سفارش‌ها و حساب صاحب سفارش.', (bool) $settings['enable_invoice'] ); ?>
				</section>
				<footer class="jib-form-footer"><span>این تنظیمات در گزینه‌ای جدا از تنظیمات فعال پوسته نگهداری می‌شوند.</span><button class="button button-primary" type="submit">ذخیرهٔ اطلاعات فروشنده</button></footer>
			</form>
		<?php elseif ( 'design' === $tab ) : ?>
			<form class="jib-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jluxe_invoice_save_settings">
				<input type="hidden" name="tab" value="design">
				<?php wp_nonce_field( 'jluxe_invoice_save_settings' ); ?>
				<section class="jib-card">
					<header><div><h2>اندازه و رفتار چاپ</h2><p>پس از بازشدن فاکتور می‌توان اندازه را بین A4 و A5 عوض کرد.</p></div></header>
					<div class="jib-grid">
						<div class="jib-field"><label for="jib-default-size">اندازهٔ پیش‌فرض کاغذ</label><select id="jib-default-size" name="invoice_settings[default_size]"><option value="a5" <?php selected( 'a5', $settings['default_size'] ); ?>>A5</option><option value="a4" <?php selected( 'a4', $settings['default_size'] ); ?>>A4</option></select><p>اندازهٔ آغازین فاکتور؛ کنترل A4/A5 داخل صفحهٔ چاپ هم موجود است.</p></div>
						<div class="jib-field"><label for="jib-footer-text">متن پایانی فاکتور</label><input id="jib-footer-text" type="text" name="invoice_settings[footer_text]" value="<?php echo esc_attr( $settings['footer_text'] ); ?>"><p>برای حذف متن پایانی، این کادر را خالی بگذارید.</p></div>
					</div>
					<?php jluxe_invoice_render_toggle( 'show_logo', 'نمایش لوگو', 'لوگوی فروشگاه در سربرگ فاکتور چاپی دیده شود.', (bool) $settings['show_logo'] ); ?>
					<?php jluxe_invoice_render_toggle( 'auto_print', 'بازکردن پنجرهٔ چاپ', 'پس از بارگذاری فاکتور، پنجرهٔ چاپ مرورگر باز شود؛ افزودن noprint=1 به لینک آن را متوقف می‌کند.', (bool) $settings['auto_print'] ); ?>
				</section>
				<footer class="jib-form-footer"><span>سربرگ و جدول از اطلاعات واقعی سفارش رندر می‌شوند.</span><button class="button button-primary" type="submit">ذخیرهٔ ظاهر فاکتور</button></footer>
			</form>
		<?php else : ?>
			<?php
			$preview_size = isset( $_GET['size'] ) && is_string( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : $settings['default_size'];
			if ( ! in_array( $preview_size, array( 'a4', 'a5' ), true ) ) {
				$preview_size = $settings['default_size'];
			}
			$sample_data = jluxe_invoice_sample_data( $settings );
			ob_start();
			jluxe_invoice_render_document( $sample_data, $preview_size, array(), false );
			$preview_document = (string) ob_get_clean();
			?>
			<section class="jib-card jib-preview-card">
				<header><div><h2>پیش‌نمایش طرح فاکتور</h2><p>این نمونه ساختگی است؛ برای چاپ فاکتور واقعی، از داخل سفارش اقدام کنید.</p></div><div class="jib-preview-sizes"><a class="<?php echo 'a4' === $preview_size ? 'is-active' : ''; ?>" href="<?php echo esc_url( jluxe_invoice_page_url( 'preview', array( 'size' => 'a4' ) ) ); ?>">A4</a><a class="<?php echo 'a5' === $preview_size ? 'is-active' : ''; ?>" href="<?php echo esc_url( jluxe_invoice_page_url( 'preview', array( 'size' => 'a5' ) ) ); ?>">A5</a></div></header>
				<iframe title="پیش‌نمایش نمونهٔ فاکتور" srcdoc="<?php echo esc_attr( $preview_document ); ?>" loading="eager"></iframe>
			</section>
		<?php endif; ?>
	</div>
	<?php
}

/** Plain printable text; never pass arbitrary markup from an order into the invoice. */
function jluxe_invoice_plain_text( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$text = (string) $value;
	$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
	return trim( $text );
}

function jluxe_invoice_order_field( WC_Order $order, string $type, string $field ): string {
	$method = 'get_' . $type . '_' . $field;
	return method_exists( $order, $method ) ? jluxe_invoice_plain_text( $order->{$method}() ) : '';
}

/** Read recipient details from shipping first, then use billing as a safe fallback. */
function jluxe_invoice_buyer_data( WC_Order $order ): array {
	$has_shipping = false;
	foreach ( array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $field ) {
		if ( '' !== jluxe_invoice_order_field( $order, 'shipping', $field ) ) {
			$has_shipping = true;
			break;
		}
	}
	$type = $has_shipping ? 'shipping' : 'billing';
	$first_name = jluxe_invoice_order_field( $order, $type, 'first_name' );
	$last_name  = jluxe_invoice_order_field( $order, $type, 'last_name' );
	if ( '' === trim( $first_name . ' ' . $last_name ) ) {
		$first_name = jluxe_invoice_order_field( $order, 'billing', 'first_name' );
		$last_name  = jluxe_invoice_order_field( $order, 'billing', 'last_name' );
	}
	$country = jluxe_invoice_order_field( $order, $type, 'country' );
	$state   = jluxe_invoice_order_field( $order, $type, 'state' );
	if ( function_exists( 'jluxe_order_label_localized_state' ) ) {
		$state = jluxe_order_label_localized_state( $country, $state );
	}
	$address_parts = array_filter(
		array(
			jluxe_invoice_order_field( $order, $type, 'address_1' ),
			jluxe_invoice_order_field( $order, $type, 'address_2' ),
		),
		static function ( $part ) { return '' !== $part; }
	);
	$phone = 'shipping' === $type ? jluxe_invoice_order_field( $order, 'shipping', 'phone' ) : '';
	if ( '' === $phone ) {
		$phone = jluxe_invoice_order_field( $order, 'billing', 'phone' );
	}
	return array(
		'name'     => trim( $first_name . ' ' . $last_name ),
		'address'  => implode( '، ', $address_parts ),
		'state'    => $state,
		'city'     => jluxe_invoice_order_field( $order, $type, 'city' ),
		'postcode' => jluxe_invoice_order_field( $order, $type, 'postcode' ),
		'phone'    => $phone,
	);
}

/** Turn one WooCommerce order into the small, display-only invoice model. */
function jluxe_invoice_order_data( WC_Order $order ): array {
	$settings = jluxe_invoice_get_settings();
	$items = array();
	$fees  = array();
	$sum_subtotals = 0.0;
	$sum_totals    = 0.0;
	$fee_total     = 0.0;
	$order_items = method_exists( $order, 'get_items' ) ? $order->get_items( 'line_item' ) : array();
	foreach ( is_array( $order_items ) ? $order_items : array() as $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_name' ) || ! method_exists( $item, 'get_quantity' ) ) {
			continue;
		}
		$quantity = (float) $item->get_quantity();
		if ( $quantity <= 0 ) {
			$quantity = 1;
		}
		$line_subtotal = method_exists( $item, 'get_subtotal' ) ? max( 0, (float) $item->get_subtotal() ) : 0;
		$line_total    = method_exists( $item, 'get_total' ) ? max( 0, (float) $item->get_total() ) : $line_subtotal;
		$sum_subtotals += $line_subtotal;
		$sum_totals    += $line_total;
		$items[] = array(
			'name'       => jluxe_invoice_plain_text( $item->get_name() ),
			'quantity'   => $quantity,
			'unit_price' => $line_subtotal / $quantity,
			'final_unit' => $line_total / $quantity,
			'discount'   => max( 0, $line_subtotal - $line_total ) / $quantity,
			'total'      => $line_total,
		);
	}
	$order_fee_items = method_exists( $order, 'get_items' ) ? $order->get_items( 'fee' ) : array();
	foreach ( is_array( $order_fee_items ) ? $order_fee_items : array() as $fee_item ) {
		if ( ! is_object( $fee_item ) || ! method_exists( $fee_item, 'get_name' ) || ! method_exists( $fee_item, 'get_total' ) ) {
			continue;
		}
		$fee_amount = (float) $fee_item->get_total();
		$fee_total += $fee_amount;
		$fees[] = array(
			'name'  => jluxe_invoice_plain_text( $fee_item->get_name() ),
			'total' => $fee_amount,
		);
	}
	$subtotal = method_exists( $order, 'get_subtotal' ) ? (float) $order->get_subtotal() : $sum_subtotals;
	if ( $subtotal <= 0 && $sum_subtotals > 0 ) {
		$subtotal = $sum_subtotals;
	}
	$discount = method_exists( $order, 'get_discount_total' ) ? max( 0, (float) $order->get_discount_total() ) : max( 0, $subtotal - $sum_totals );
	$shipping = method_exists( $order, 'get_shipping_total' ) ? max( 0, (float) $order->get_shipping_total() ) : 0;
	$tax      = method_exists( $order, 'get_total_tax' ) ? max( 0, (float) $order->get_total_tax() ) : 0;
	$total    = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : max( 0, $subtotal - $discount + $fee_total + $shipping + $tax );
	$date     = method_exists( $order, 'get_date_created' ) ? $order->get_date_created() : null;
	$date_label = '';
	if ( is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
		$date_label = function_exists( 'jluxe_gregorian_timestamp_to_jalali_string' )
			? (string) jluxe_gregorian_timestamp_to_jalali_string( $date->getTimestamp(), 'Y/m/d' )
			: ( function_exists( 'wp_date' ) ? (string) wp_date( 'Y/m/d', $date->getTimestamp() ) : date( 'Y/m/d', $date->getTimestamp() ) );
	}
	return array(
		'order_number' => jluxe_invoice_plain_text( $order->get_order_number() ),
		'date'         => jluxe_invoice_plain_text( $date_label ),
		'currency'     => method_exists( $order, 'get_currency' ) ? jluxe_invoice_plain_text( $order->get_currency() ) : '',
		'seller'       => $settings,
		'buyer'        => jluxe_invoice_buyer_data( $order ),
		'items'        => $items,
		'fees'         => $fees,
		'totals'       => array(
			'products'  => $subtotal,
			'net'       => $sum_totals,
			'fees'      => $fee_total,
			'shipping'  => $shipping,
			'discount'  => $discount,
			'tax'       => $tax,
			'order'     => $total,
		),
	);
}

function jluxe_invoice_money_html( $amount, string $currency = '' ): string {
	$amount = is_numeric( $amount ) ? (float) $amount : 0;
	if ( function_exists( 'wc_price' ) ) {
		$html = '' !== $currency ? wc_price( $amount, array( 'currency' => $currency ) ) : wc_price( $amount );
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : (string) $html;
	}
	return esc_html( number_format_i18n( $amount ) );
}

function jluxe_invoice_number_text( $value ): string {
	$text = is_scalar( $value ) ? (string) $value : '';
	return function_exists( 'jluxe_fa_digits' ) ? (string) jluxe_fa_digits( $text ) : $text;
}

function jluxe_invoice_admin_can_print( int $order_id ): bool {
	return $order_id > 0 && (
		current_user_can( 'manage_woocommerce' ) ||
		current_user_can( 'edit_shop_orders' ) ||
		current_user_can( 'edit_shop_order', $order_id )
	);
}

function jluxe_invoice_customer_owns_order( $order ): bool {
	return is_object( $order ) && method_exists( $order, 'get_customer_id' ) &&
		get_current_user_id() > 0 && (int) $order->get_customer_id() === (int) get_current_user_id();
}

function jluxe_invoice_user_can_print_order( $order ): bool {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) || ! jluxe_invoice_get_settings()['enable_invoice'] ) {
		return false;
	}
	$order_id = absint( $order->get_id() );
	return jluxe_invoice_admin_can_print( $order_id ) || jluxe_invoice_customer_owns_order( $order );
}

/** Build a user-bound print URL; customers can only receive it for their own order. */
function jluxe_invoice_print_url( int $order_id, string $size = '' ): string {
	$settings = jluxe_invoice_get_settings();
	if ( $order_id < 1 || empty( $settings['enable_invoice'] ) ) {
		return '';
	}
	$size = in_array( $size, array( 'a4', 'a5' ), true ) ? $size : $settings['default_size'];
	return add_query_arg(
		array(
			'action'   => 'jluxe_print_invoice',
			'order_id' => $order_id,
			'type'     => 'invoice',
			'size'     => $size,
			'_wpnonce' => wp_create_nonce( 'jluxe_print_invoice_' . $order_id ),
		),
		admin_url( 'admin-post.php' )
	);
}

function jluxe_invoice_customer_url( $order ): string {
	if ( ! jluxe_invoice_customer_owns_order( $order ) || ! jluxe_invoice_get_settings()['enable_invoice'] ) {
		return '';
	}
	return jluxe_invoice_print_url( absint( $order->get_id() ) );
}

function jluxe_invoice_render_admin_order_button( $order ): void {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
		return;
	}
	$order_id = absint( $order->get_id() );
	if ( ! jluxe_invoice_get_settings()['enable_invoice'] || ! jluxe_invoice_admin_can_print( $order_id ) ) {
		return;
	}
	$url = jluxe_invoice_print_url( $order_id );
	?>
	<p class="jluxe-invoice-admin-action"><a class="button button-secondary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>چاپ فاکتور</a></p>
	<?php
}

function jluxe_invoice_add_order_list_action( array $actions, $order ): array {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
		return $actions;
	}
	$order_id = absint( $order->get_id() );
	if ( ! jluxe_invoice_get_settings()['enable_invoice'] || ! jluxe_invoice_admin_can_print( $order_id ) ) {
		return $actions;
	}
	$actions['jluxe-invoice'] = array(
		'url'    => jluxe_invoice_print_url( $order_id ),
		'name'   => __( 'چاپ فاکتور', 'jluxe' ),
		'action' => 'jluxe-invoice',
	);
	return $actions;
}

function jluxe_invoice_add_customer_order_action( array $actions, $order ): array {
	$url = jluxe_invoice_customer_url( $order );
	if ( '' !== $url ) {
		$actions['jluxe-invoice'] = array(
			'url'        => $url,
			'name'       => __( 'چاپ فاکتور', 'jluxe' ),
			'aria-label' => __( 'چاپ فاکتور این سفارش', 'jluxe' ),
		);
	}
	return $actions;
}

function jluxe_invoice_order_action_icon(): void {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! is_object( $screen ) || ! in_array( (string) ( $screen->id ?? '' ), array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
		return;
	}
	echo '<style>.wc-action-button-jluxe-invoice::after{font-family:dashicons;content:"\f495"}</style>';
}

function jluxe_invoice_print_request_allowed( WC_Order $order ): bool {
	return jluxe_invoice_get_settings()['enable_invoice'] && jluxe_invoice_user_can_print_order( $order );
}

/** Secure admin-post endpoint for the invoice HTML/print document. */
function jluxe_invoice_handle_print(): void {
	$order_id = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
	if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
		wp_die( esc_html__( 'سفارش پیدا نشد.', 'jluxe' ), '', array( 'response' => 404 ) );
	}
	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) {
		wp_die( esc_html__( 'سفارش پیدا نشد.', 'jluxe' ), '', array( 'response' => 404 ) );
	}
	if ( ! jluxe_invoice_print_request_allowed( $order ) ) {
		wp_die( esc_html__( 'اجازهٔ مشاهدهٔ این فاکتور را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'jluxe_print_invoice_' . $order_id );
	$type = isset( $_GET['type'] ) && is_string( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'invoice';
	if ( 'invoice' !== $type ) {
		wp_die( esc_html__( 'نوع فاکتور معتبر نیست.', 'jluxe' ), '', array( 'response' => 400 ) );
	}
	$size = isset( $_GET['size'] ) && is_string( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : jluxe_invoice_get_settings()['default_size'];
	if ( ! in_array( $size, array( 'a4', 'a5' ), true ) ) {
		wp_die( esc_html__( 'اندازهٔ کاغذ معتبر نیست.', 'jluxe' ), '', array( 'response' => 400 ) );
	}
	nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	$size_urls = array(
		'a4' => add_query_arg( 'noprint', '1', jluxe_invoice_print_url( $order_id, 'a4' ) ),
		'a5' => add_query_arg( 'noprint', '1', jluxe_invoice_print_url( $order_id, 'a5' ) ),
	);
	$settings = jluxe_invoice_get_settings();
	$auto_print = ! empty( $settings['auto_print'] ) && ! isset( $_GET['noprint'] );
	jluxe_invoice_render_document( jluxe_invoice_order_data( $order ), $size, $size_urls, $auto_print );
	exit;
}

/** Register public-facing links only through authenticated owner/order filters. */
add_action( 'admin_post_jluxe_print_invoice', 'jluxe_invoice_handle_print' );
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_invoice_render_admin_order_button', 40, 1 );
add_filter( 'woocommerce_admin_order_actions', 'jluxe_invoice_add_order_list_action', 40, 2 );
add_filter( 'woocommerce_my_account_my_orders_actions', 'jluxe_invoice_add_customer_order_action', 40, 2 );
add_action( 'admin_head', 'jluxe_invoice_order_action_icon', 40 );

/** Sample data shown only in the local admin preview iframe. */
function jluxe_invoice_sample_data( array $settings ): array {
	return array(
		'order_number' => 'نمونه',
		'date'         => function_exists( 'jluxe_gregorian_timestamp_to_jalali_string' ) ? jluxe_gregorian_timestamp_to_jalali_string( time(), 'Y/m/d' ) : '',
		'currency'     => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
		'seller'       => $settings,
		'buyer'        => array(
			'name'     => 'نام مشتری نمونه',
			'address'  => 'نشانی نمونه برای پیش‌نمایش',
			'state'    => 'استان نمونه',
			'city'     => 'شهر نمونه',
			'postcode' => '۱۲۳۴۵۶۷۸۹۰',
			'phone'    => '09120000000',
		),
		'items'        => array(
			array( 'name' => 'محصول نمونهٔ فروشگاه', 'quantity' => 1, 'unit_price' => 2651325, 'final_unit' => 2651325, 'discount' => 0, 'total' => 2651325 ),
		),
		'fees'         => array(),
		'totals'       => array( 'products' => 2651325, 'net' => 2651325, 'fees' => 0, 'shipping' => 0, 'discount' => 0, 'tax' => 0, 'order' => 2651325 ),
	);
}

/** Render a standalone RTL invoice that uses the requested A4/A5 print sizes. */
function jluxe_invoice_render_document( array $data, string $size, array $size_urls = array(), bool $auto_print = false ): void {
	$size   = in_array( $size, array( 'a4', 'a5' ), true ) ? $size : 'a5';
	$letter = strtoupper( $size );
	$seller  = isset( $data['seller'] ) && is_array( $data['seller'] ) ? $data['seller'] : array();
	$buyer   = isset( $data['buyer'] ) && is_array( $data['buyer'] ) ? $data['buyer'] : array();
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	$fees    = isset( $data['fees'] ) && is_array( $data['fees'] ) ? $data['fees'] : array();
	$totals  = isset( $data['totals'] ) && is_array( $data['totals'] ) ? $data['totals'] : array();
	$currency = isset( $data['currency'] ) && is_string( $data['currency'] ) ? $data['currency'] : '';
	$logo_url = ! empty( $seller['show_logo'] ) && ! empty( $seller['company_logo_url'] ) ? (string) $seller['company_logo_url'] : '';
	$company_name = jluxe_invoice_plain_text( $seller['company_name'] ?? '' );
	$company_address = jluxe_invoice_plain_text( $seller['company_address'] ?? '' );
	$company_postcode = jluxe_invoice_plain_text( $seller['company_postcode'] ?? '' );
	$company_phone = jluxe_invoice_plain_text( $seller['company_phone'] ?? '' );
	$company_email = jluxe_invoice_plain_text( $seller['company_email'] ?? '' );
	$company_website = jluxe_invoice_plain_text( $seller['company_website'] ?? '' );
	$buyer_name = jluxe_invoice_plain_text( $buyer['name'] ?? '' );
	$buyer_address = jluxe_invoice_plain_text( $buyer['address'] ?? '' );
	$buyer_state = jluxe_invoice_plain_text( $buyer['state'] ?? '' );
	$buyer_city = jluxe_invoice_plain_text( $buyer['city'] ?? '' );
	$buyer_postcode = jluxe_invoice_plain_text( $buyer['postcode'] ?? '' );
	$buyer_phone = jluxe_invoice_plain_text( $buyer['phone'] ?? '' );
	$order_number = jluxe_invoice_plain_text( $data['order_number'] ?? '' );
	$date = jluxe_invoice_plain_text( $data['date'] ?? '' );
	$footer_text = jluxe_invoice_plain_text( $seller['footer_text'] ?? '' );
	$font_uri = function_exists( 'get_template_directory_uri' ) ? trailingslashit( get_template_directory_uri() ) . 'src/assets/fonts/' : '';
	$print_script_url = function_exists( 'get_template_directory_uri' ) ? trailingslashit( get_template_directory_uri() ) . 'assets/js/invoice-print.js' : '';
	?>
	<!doctype html>
	<html lang="fa-IR" dir="rtl">
	<head>
		<meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow,noarchive">
		<title><?php echo esc_html( 'فاکتور فروش ' . $order_number ); ?></title>
		<style>
			@font-face{font-family:JLuxeInvoice;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileRegular.woff2' ); ?>') format('woff2');font-weight:400;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeInvoice;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileMedium.woff2' ); ?>') format('woff2');font-weight:500;font-style:normal;font-display:swap}
			@font-face{font-family:JLuxeInvoice;src:url('<?php echo esc_url( $font_uri . 'IRANYekanMobileBold.woff2' ); ?>') format('woff2');font-weight:700;font-style:normal;font-display:swap}
			*{box-sizing:border-box}
			html,body{margin:0;min-height:100%;direction:rtl}
			body{padding:18px;background:#f3f4f6;color:#1F2937;font-family:JLuxeInvoice,Tahoma,Arial,sans-serif;font-size:12px;line-height:1.8;-webkit-print-color-adjust:exact;print-color-adjust:exact}
			@page{size:<?php echo esc_html( $letter ); ?> portrait;margin:8mm}
			.size-selector{width:100%;max-width:210mm;margin:0 auto 12px;display:flex;align-items:center;gap:8px}.invoice-a5 .size-selector{max-width:148mm}
			.size-selector a,.size-selector span,.invoice-print-button{display:inline-flex;min-height:34px;align-items:center;justify-content:center;padding:0 13px;border:1px solid #d8dee8;border-radius:7px;background:#fff;color:#334155;text-decoration:none;font:inherit;cursor:pointer}
			.size-selector .active{border-color:#A313B9;background:#FAE8FD;color:#7A0F8A;font-weight:700}
			.invoice-print-button{margin-inline-start:auto;border-color:#A313B9;background:#A313B9;color:#fff;font-weight:700}
			.invoice-container{width:210mm;min-height:285mm;margin:0 auto;padding:9mm;border:1px solid #e5e7eb;border-radius:9px;background:#fff;box-shadow:0 8px 28px rgba(15,23,42,.08)}
			.invoice-a5 .invoice-container{width:148mm;min-height:198mm;padding:6mm}
			.invoice-top-row{display:grid;grid-template-columns:1.25fr .9fr;gap:8px;margin-bottom:12px}
			.top-buyer,.top-sender{min-width:0;padding:10px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}
			.sender-header-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px;padding-bottom:8px;border-bottom:1px solid #e5e7eb}
			.sender-logo-name{min-width:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700}
			.sender-logo-name img{display:block;width:auto;height:auto;max-width:48px;max-height:48px;object-fit:contain}
			.invoice-meta{flex:0 0 auto;text-align:left;font-size:10px;line-height:1.8}
			.meta-row strong{font-weight:700}
			.party-label{display:inline-flex;margin-bottom:5px;padding:2px 9px;border-radius:999px;background:#FAE8FD;color:#7A0F8A;font-size:10px;font-weight:700}
			.party-info{font-size:10px;line-height:1.9;overflow-wrap:anywhere}
			.party-info p{margin:0 0 3px}
			.party-info strong{color:#475569}
			.invoice-top-row .top-sender{align-self:stretch}
			.products-table-wrapper{width:100%;overflow:visible;border:1px solid #e5e7eb;border-radius:7px}
			.products-table{width:100%;border-collapse:collapse;font-size:10px;line-height:1.65}
			.products-table th,.products-table td{padding:6px 5px;border-bottom:1px solid #e5e7eb;text-align:center;vertical-align:top}
			.products-table thead th{background:#FAE8FD;color:#1F2937;font-weight:700}
			.products-table tbody tr:last-child td{border-bottom:0}
			.products-table .row-number-cell{width:7%;white-space:nowrap}
			.products-table .product-name-cell{width:31%;text-align:right;overflow-wrap:anywhere}
			.products-table .total-row{background:#fafafa;font-weight:700}
			.products-table .total-row td{vertical-align:middle}
			.price-cell{white-space:nowrap}
			.buyer-financial{display:none;margin-top:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:7px}
			.buyer-financial-item{display:flex;justify-content:space-between;gap:10px;padding:2px 0;font-size:10px}
			.bf-label{color:#475569}
			.bf-value{font-weight:700;text-align:left}
			.summary-section{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}
			.summary-box{padding:8px 9px;border:1px solid #e5e7eb;border-radius:7px;background:#fff}
			.summary-label{color:#475569;font-size:9px}
			.summary-value{margin-top:2px;font-size:11px;font-weight:700;overflow-wrap:anywhere}
			.summary-box.total{border-color:#A313B9;background:#FAE8FD}
			.invoice-footer{margin-top:14px;padding-top:8px;border-top:1px solid #e5e7eb;color:#475569;text-align:center;font-size:10px}
			.invoice-a5 .invoice-container{font-size:10px}
			.invoice-a5 .invoice-top-row{gap:6px}
			.invoice-a5 .top-buyer,.invoice-a5 .top-sender{padding:7px}
			.invoice-a5 .products-table{font-size:8px}
			.invoice-a5 .products-table th,.invoice-a5 .products-table td{padding:4px 3px}
			.invoice-a5 .buyer-financial{display:block}
			.invoice-a5 .summary-section{display:none}
			.invoice-a5 .invoice-footer{margin-top:8px}
			@media(max-width:650px){body{padding:10px}.invoice-container{max-width:100%;overflow-x:auto}.invoice-top-row{grid-template-columns:1fr}.invoice-meta{font-size:9px}.products-table{min-width:620px}.size-selector{flex-wrap:wrap}}
			@media print{html,body{min-height:0;background:#fff}body{padding:0}.size-selector{display:none}.invoice-container{width:auto!important;min-height:0!important;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.invoice-a5 .invoice-container{padding:0}.top-buyer,.top-sender,.summary-box,.buyer-financial{break-inside:avoid;page-break-inside:avoid}.products-table thead{display:table-header-group}.products-table tr{break-inside:avoid;page-break-inside:avoid}.invoice-footer{break-inside:avoid;page-break-inside:avoid}.invoice-print-button{display:none}}
		</style>
	</head>
	<body class="invoice-<?php echo esc_attr( $size ); ?>" data-auto-print="<?php echo $auto_print ? '1' : '0'; ?>">
		<nav class="size-selector" aria-label="انتخاب اندازهٔ کاغذ">
			<?php foreach ( array( 'a4' => 'A4', 'a5' => 'A5' ) as $key => $label ) : ?>
				<?php if ( ! empty( $size_urls[ $key ] ) ) : ?><a href="<?php echo esc_url( $size_urls[ $key ] ); ?>" class="<?php echo $size === $key ? 'active' : ''; ?>"><?php echo esc_html( $label ); ?></a><?php else : ?><span class="<?php echo $size === $key ? 'active' : ''; ?>"><?php echo esc_html( $label ); ?></span><?php endif; ?>
			<?php endforeach; ?>
			<button class="invoice-print-button" type="button" data-invoice-print>چاپ فاکتور</button>
		</nav>
		<main class="invoice-container" aria-label="فاکتور فروش شماره <?php echo esc_attr( $order_number ); ?>">
			<div class="invoice-top-row">
				<section class="top-buyer">
					<div class="sender-header-row">
						<div class="sender-logo-name">
							<?php if ( '' !== $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>"><?php endif; ?>
							<?php if ( '' !== $company_name ) : ?><span class="company-name-text"><?php echo esc_html( $company_name ); ?></span><?php endif; ?>
						</div>
						<div class="invoice-meta"><div class="meta-row">فاکتور فروش شماره <strong><?php echo esc_html( jluxe_invoice_number_text( $order_number ) ); ?></strong></div><div class="meta-row">تاریخ: <strong><?php echo esc_html( jluxe_invoice_number_text( $date ) ); ?></strong></div></div>
					</div>
					<div class="party-label">گیرنده</div>
					<div class="party-info">
						<p><?php if ( '' !== $buyer_name ) : ?><strong>نام:</strong> <?php echo esc_html( $buyer_name ); ?><?php endif; ?><?php if ( '' !== $buyer_name && '' !== $buyer_address ) : ?> | <?php endif; ?><?php if ( '' !== $buyer_address ) : ?><strong>آدرس:</strong> <?php echo esc_html( $buyer_address ); ?><?php endif; ?></p>
						<p><?php if ( '' !== $buyer_state ) : ?><strong>استان:</strong> <?php echo esc_html( $buyer_state ); ?><?php endif; ?><?php if ( '' !== $buyer_state && '' !== $buyer_city ) : ?>، <?php endif; ?><?php if ( '' !== $buyer_city ) : ?><strong>شهر:</strong> <?php echo esc_html( $buyer_city ); ?><?php endif; ?><?php if ( '' !== $buyer_postcode ) : ?> | <strong>کد پستی:</strong> <bdi dir="ltr"><?php echo esc_html( $buyer_postcode ); ?></bdi><?php endif; ?><?php if ( '' !== $buyer_phone ) : ?> | <strong>تلفن:</strong> <bdi dir="ltr"><?php echo esc_html( $buyer_phone ); ?></bdi><?php endif; ?></p>
					</div>
				</section>
				<section class="top-sender">
					<div class="party-label">فرستنده</div>
					<div class="party-info">
						<p><?php if ( '' !== $company_name ) : ?><strong>نام:</strong> <?php echo esc_html( $company_name ); ?><?php endif; ?><?php if ( '' !== $company_address ) : ?> | <strong>آدرس:</strong> <?php echo esc_html( $company_address ); ?><?php endif; ?></p>
						<p><?php if ( '' !== $company_postcode ) : ?><strong>کد پستی:</strong> <bdi dir="ltr"><?php echo esc_html( $company_postcode ); ?></bdi><?php endif; ?><?php if ( '' !== $company_phone ) : ?> | <strong>تلفن:</strong> <bdi dir="ltr"><?php echo esc_html( $company_phone ); ?></bdi><?php endif; ?><?php if ( '' !== $company_email ) : ?> | <strong>ایمیل:</strong> <bdi dir="ltr"><?php echo esc_html( $company_email ); ?></bdi><?php endif; ?><?php if ( '' !== $company_website ) : ?> | <strong>وب‌سایت:</strong> <bdi dir="ltr"><?php echo esc_html( $company_website ); ?></bdi><?php endif; ?></p>
					</div>
				</section>
			</div>

			<div class="products-table-wrapper">
				<table class="products-table">
					<thead><tr><th class="row-number-cell">ردیف</th><th class="product-name-cell">نام کالا</th><th>قیمت</th><th>تعداد</th><th>قیمت نهایی</th><th>تخفیف</th><th>مجموع</th></tr></thead>
					<tbody>
						<?php $products_net = 0.0; foreach ( $items as $index => $item ) : if ( ! is_array( $item ) ) { continue; } $quantity = (float) ( $item['quantity'] ?? 1 ); $discount = (float) ( $item['discount'] ?? 0 ); $line_total = (float) ( $item['total'] ?? 0 ); $products_net += $line_total; ?>
							<tr><td class="row-number-cell"><?php echo esc_html( jluxe_invoice_number_text( (string) ( $index + 1 ) ) ); ?></td><td class="product-name-cell"><?php echo esc_html( jluxe_invoice_plain_text( $item['name'] ?? '' ) ); ?></td><td class="price-cell"><?php echo jluxe_invoice_money_html( $item['unit_price'] ?? 0, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></td><td><?php echo esc_html( jluxe_invoice_number_text( rtrim( rtrim( number_format( $quantity, 3, '.', '' ), '0' ), '.' ) ) ); ?></td><td class="price-cell"><?php echo jluxe_invoice_money_html( $item['final_unit'] ?? 0, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></td><td class="price-cell"><?php echo $discount > 0 ? jluxe_invoice_money_html( $discount, $currency ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></td><td class="price-cell"><?php echo jluxe_invoice_money_html( $line_total, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></td></tr>
						<?php endforeach; ?>
						<tr class="total-row"><td colspan="6">مجموع محصولات</td><td class="price-cell"><?php echo jluxe_invoice_money_html( $products_net, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="buyer-financial">
				<div class="buyer-financial-item"><span class="bf-label">مجموع محصولات:</span><span class="bf-value"><?php echo jluxe_invoice_money_html( $totals['products'] ?? $products_net, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div>
				<div class="buyer-financial-item"><span class="bf-label">هزینهٔ ارسال:</span><span class="bf-value"><?php echo (float) ( $totals['shipping'] ?? 0 ) > 0 ? jluxe_invoice_money_html( $totals['shipping'], $currency ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div>
				<?php foreach ( $fees as $fee ) : if ( ! is_array( $fee ) || 0.0 === (float) ( $fee['total'] ?? 0 ) ) { continue; } $fee_name = jluxe_invoice_plain_text( $fee['name'] ?? '' ); ?>
					<div class="buyer-financial-item"><span class="bf-label"><?php echo esc_html( '' !== $fee_name ? $fee_name : 'هزینهٔ جانبی' ); ?>:</span><span class="bf-value"><?php echo jluxe_invoice_money_html( $fee['total'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div>
				<?php endforeach; ?>
				<?php if ( (float) ( $totals['discount'] ?? 0 ) > 0 ) : ?><div class="buyer-financial-item"><span class="bf-label">تخفیف:</span><span class="bf-value"><?php echo jluxe_invoice_money_html( $totals['discount'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div><?php endif; ?>
				<?php if ( (float) ( $totals['tax'] ?? 0 ) > 0 ) : ?><div class="buyer-financial-item"><span class="bf-label">مالیات:</span><span class="bf-value"><?php echo jluxe_invoice_money_html( $totals['tax'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div><?php endif; ?>
				<div class="buyer-financial-item"><span class="bf-label">جمع کل:</span><span class="bf-value"><?php echo jluxe_invoice_money_html( $totals['order'] ?? 0, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></span></div>
			</div>
			<div class="summary-section">
				<div class="summary-box"><div class="summary-label">مجموع محصولات</div><div class="summary-value"><?php echo jluxe_invoice_money_html( $totals['products'] ?? $products_net, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div>
				<div class="summary-box"><div class="summary-label">هزینهٔ ارسال</div><div class="summary-value"><?php echo (float) ( $totals['shipping'] ?? 0 ) > 0 ? jluxe_invoice_money_html( $totals['shipping'], $currency ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div>
				<?php if ( 0.0 !== (float) ( $totals['fees'] ?? 0 ) ) : ?><div class="summary-box"><div class="summary-label">هزینه‌های جانبی</div><div class="summary-value"><?php echo jluxe_invoice_money_html( $totals['fees'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div><?php endif; ?>
				<?php if ( (float) ( $totals['discount'] ?? 0 ) > 0 ) : ?><div class="summary-box"><div class="summary-label">تخفیف</div><div class="summary-value"><?php echo jluxe_invoice_money_html( $totals['discount'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div><?php endif; ?>
				<?php if ( (float) ( $totals['tax'] ?? 0 ) > 0 ) : ?><div class="summary-box"><div class="summary-label">مالیات</div><div class="summary-value"><?php echo jluxe_invoice_money_html( $totals['tax'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div><?php endif; ?>
				<div class="summary-box total"><div class="summary-label">مجموع سفارش</div><div class="summary-value"><?php echo jluxe_invoice_money_html( $totals['order'] ?? 0, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() is sanitized with wp_kses_post. ?></div></div>
			</div>
			<?php if ( '' !== $footer_text ) : ?><footer class="invoice-footer"><?php echo esc_html( $footer_text ); ?></footer><?php endif; ?>
		</main>
		<?php if ( '' !== $print_script_url ) : ?><script src="<?php echo esc_url( $print_script_url ); ?>" defer></script><?php endif; ?>
	</body>
	</html>
	<?php
}
