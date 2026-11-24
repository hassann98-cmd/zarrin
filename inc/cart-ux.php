<?php
/**
 * تجربه‌ی «افزودن به سبد» — سه تکه: مودال انتخاب سریعِ تنوع (برای محصول
 * متغیر، وقتی روی افزودن سریع در گرید کلیک می‌شه)، و AJAX سبدِ خرید
 * (خلاصه/تغییر تعداد/حذف — برای دراور سبدِ کشویی src/islands/MiniCart.tsx).
 * توست/اسنک‌بار «به سبد اضافه شد» سمت JS (assets/js/woocommerce.js) با
 * گوش‌دادن به رویداد استاندارد added_to_cart خودِ ووکامرس پیاده شده، نیازی
 * به endpoint جدا نداره.
 */

defined( 'ABSPATH' ) || exit;

/**
 * مودال انتخاب سریع تنوع — دقیقاً همون سواچ‌ها + جعبه‌ی خرید صفحه‌ی محصول
 * (jluxe_render_variation_swatches، مشترک با content-single-product.php)
 * ولی جمع‌وجورتر و بدون بقیه‌ی محتوای صفحه، برای بازشدن توی یک مودال از
 * روی گرید محصولات.
 */
function jluxe_ajax_variation_picker(): void {
	if ( ! check_ajax_referer( 'jluxe_cart', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'jluxe_cart_invalid_nonce', 'message' => 'نشست منقضی شده است؛ صفحه را تازه کنید.' ), 403 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : null;

	if ( ! jluxe_product_is_public( $product ) || ! $product->is_type( 'variable' ) ) {
		wp_send_json_error( array( 'message' => 'محصول در دسترس نیست.' ), 404 );
	}

	// قالب‌های single-product/price.php و variation-add-to-cart-button.php
	// (از woocommerce_single_variation/woocommerce_after_single_variation)
	// روی global $product تکیه می‌کنن، نه پارامترِ محلیِ همین تابع — بدونش
	// با «Call to a member function is_purchasable() on null» کرش می‌کنن.
	// wc_setup_product_data() فقط ID یا WP_Post قبول می‌کنه، نه شیءِ
	// WC_Product (وگرنه بی‌سروصدا و بدون ارور چیزی تنظیم نمی‌کنه، چون
	// $post->post_type روی یک WC_Product همیشه خالیه).
	wc_setup_product_data( $product_id );

	$variation_attributes = $product->get_variation_attributes();
	$available_variations  = jluxe_available_variations_for_form( $product );
	$image_id              = $product->get_image_id();

	wp_enqueue_script( 'wc-add-to-cart-variation' );

	ob_start();
	?>
	<form class="variations_form cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo wc_esc_json( wp_json_encode( $available_variations ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
			<p class="rounded-xl bg-muted px-3 py-2.5 text-center text-small font-medium text-text-secondary">این محصول در حال حاضر ناموجود است</p>
		<?php else : ?>
			<?php jluxe_render_variation_swatches( $product, $variation_attributes ); ?>
			<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
			<?php wc_get_template_part( 'single-product/price' ); ?>
			<div class="single_variation_wrap mt-3">
				<?php
				do_action( 'woocommerce_before_single_variation' );
				do_action( 'woocommerce_single_variation' );
				do_action( 'woocommerce_after_single_variation' );
				?>
			</div>
		<?php endif; ?>
	</form>
	<?php
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'  => $html,
			'name'  => $product->get_name(),
			'image' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			'url'   => get_permalink( $product->get_id() ),
		)
	);
}
add_action( 'wp_ajax_jluxe_variation_picker', 'jluxe_ajax_variation_picker' );
add_action( 'wp_ajax_nopriv_jluxe_variation_picker', 'jluxe_ajax_variation_picker' );

/**
 * خلاصه‌ی کامل سبد خرید — src/islands/MiniCart.tsx (دراور کشویی) این
 * شکل رو مصرف می‌کنه. قیمت‌ها مستقیم از wc_price()/get_product_subtotal()
 * میان — فیلترِ jluxe_fa_digits_wc_price (inc/woocommerce.php) خودش هم
 * فارسی‌سازیِ ارقام هم حفاظتِ آیکونِ SVG تومان رو انجام می‌ده، حتی توی
 * admin-ajax.php (که قبلاً چون is_admin()=true حساب می‌شد نادیده گرفته
 * می‌شد — همون چیزی که مجبور می‌کرد این‌جا/inc/search.php دستی
 * jluxe_fa_digits رو دوباره صدا بزنن و آیکون رو خراب کنن).
 */
function jluxe_cart_snapshot(): array {
	$cart  = WC()->cart;
	$items = array();

	foreach ( $cart->get_cart() as $key => $cart_item ) {
		$product = $cart_item['data'];
		if ( ! $product ) {
			// A read-only snapshot must not mutate a valid/custom WooCommerce session.
			continue;
		}
		$image_id   = $product->get_image_id();
		$variation  = array();
		if ( ! empty( $cart_item['variation'] ) ) {
			foreach ( $cart_item['variation'] as $attr_key => $attr_value ) {
				if ( '' === $attr_value ) {
					continue;
				}
				$taxonomy = str_replace( 'attribute_', '', $attr_key );
				if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
					// کلیدِ ویژگیِ ذخیره‌شده در سبد از نامِ فیلدِ فرمِ HTML میاد که
					// sanitize_title() برای برچسب‌های غیرلاتین (فارسی) اون رو
					// درصد-انکود می‌کنه (مثلاً pa_%d8%b1%d9%86%da%af) — ولی
					// taxonomy واقعیِ ثبت‌شده در وردپرس دیکود‌شده‌ست (pa_رنگ).
					// بدونِ urldecode، get_term_by چون taxonomy رو پیدا نمی‌کنه
					// همیشه false برمی‌گردوند و اسلاگِ خامِ درصد-انکود‌شده به‌جای
					// نامِ واقعیِ رنگ نمایش داده می‌شد.
					$term        = get_term_by( 'slug', $attr_value, urldecode( $taxonomy ) );
					$variation[] = $term ? $term->name : $attr_value;
				} else {
					$variation[] = $attr_value;
				}
			}
		}

		$line_total = $cart->get_product_subtotal( $product, $cart_item['quantity'] );
		$items[] = array(
			'key'           => $key,
			'productId'     => $cart_item['product_id'],
			'name'          => $product->get_name(),
			'url'           => get_permalink( $cart_item['product_id'] ),
			'image'         => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			'variation'     => implode( '، ', $variation ),
			'qty'           => $cart_item['quantity'],
			// get_product_subtotal() برخلاف get_subtotal() یک HTML آماده (با
			// <span class="woocommerce-Price-amount">) برمی‌گردونه نه عدد خام —
			// دوباره پیچیدنش توی wc_price() رشته‌ی HTML رو به عدد صفر تبدیل
			// می‌کنه (باگ واقعی که باعث می‌شد قیمت هر ردیف «۰ تومان» نشون داده بشه).
			// jluxe_fa_digits() دستیِ دورش عمداً حذف شد — get_product_subtotal()
			// خودش داخلاً wc_price() صدا می‌زنه که دیگه (بعد از رفعِ is_admin در
			// jluxe_fa_digits_wc_price) توی AJAX هم ارقام رو خودش فارسی می‌کنه؛
			// پیچیدنِ دوباره‌ش آیکونِ SVG تومان رو خراب می‌کرد (باگِ واقعیِ
			// گزارش‌شده در سبدِ کشویی).
			// Match the cart-page template so price extensions remain visible in
			// both the classic WooCommerce form and the fast cart-page response.
			'lineTotalHtml' => apply_filters( 'woocommerce_cart_item_subtotal', $line_total, $cart_item, $key ),
			'maxQty'        => $product->get_max_purchase_quantity() > 0 ? $product->get_max_purchase_quantity() : 99,
		);
	}

	$coupons = array();
	foreach ( $cart->get_applied_coupons() as $code ) {
		$coupons[] = array(
			'code'         => $code,
			'discountHtml' => wc_price( $cart->get_coupon_discount_amount( $code, $cart->display_prices_including_tax() ) ),
		);
	}

	$share_cart_enabled = ! empty( jluxe_get_theme_settings()['cart_checkout']['show_share_cart'] );
	$share_cart_url = $share_cart_enabled && ! $cart->is_empty() ? jluxe_share_cart_url() : '';
	$gift_wrap = function_exists( 'jluxe_get_session_gift_wrap' ) ? jluxe_get_session_gift_wrap() : array( 'enabled' => false, 'note' => '' );
	return array(
		'items'         => $items,
		'itemCount'     => $cart->get_cart_contents_count(),
		'shareCartEnabled' => $share_cart_enabled,
		'shareCartUrl'  => $share_cart_url,
		'giftWrap'      => array( 'enabled' => (bool) $gift_wrap['enabled'], 'note' => (string) $gift_wrap['note'] ),
		'subtotalHtml'  => wc_price( $cart->get_subtotal() ),
		'coupons'       => $coupons,
		'discountHtml'  => $cart->get_discount_total() > 0 ? wc_price( $cart->get_discount_total() ) : null,
		'freeShipping'  => jluxe_get_free_shipping_progress(),
		'cartUrl'       => wc_get_cart_url(),
		'checkoutUrl'   => wc_get_checkout_url(),
	);
}

/** Render only the fragment needed after a cart-page mutation, not a full page. */
function jluxe_cart_page_ajax_fragment(): array {
	if ( ! function_exists( 'wc_get_template' ) ) {
		return array();
	}

	if ( WC()->cart->is_empty() ) {
		ob_start();
		wc_get_template( 'cart/cart-empty.php' );
		return array(
			'isEmpty'   => true,
			'emptyHtml' => (string) ob_get_clean(),
		);
	}

	ob_start();
	wc_get_template( 'cart/cart-totals.php' );
	return array(
		'isEmpty'    => false,
		'totalsHtml' => (string) ob_get_clean(),
	);
}

/**
 * پیشرفتِ ارسالِ رایگان برای نوارِ مینی‌کارت — هیچ عددی اینجا هاردکد نیست،
 * همه از تنظیماتِ واقعیِ روشِ حمل‌ونقلِ «ارسال رایگان» (WooCommerce → حمل‌ونقل
 * → منطقه → روش) خونده می‌شه. اگه هیچ منطقه‌ای روشِ ارسالِ رایگانِ فعال با
 * شرطِ «حداقل مبلغ سفارش» نداشته باشه، null برمی‌گرده و کامپوننت اصلاً چیزی
 * نشون نمی‌ده — طبقِ فلسفه‌ی کلیِ پروژه («چیزی که واقعی نیست ادعا نکن»).
 * منطقه‌ی واقعاً مرتبط با WC_Shipping_Zones::get_zone_matching_package()
 * پیدا می‌شه — دقیقاً همون منطقی که خودِ ووکامرس برای محاسبه‌ی هزینه‌ی
 * واقعیِ ارسال استفاده می‌کنه، نه یک حدسِ جدا.
 */
function jluxe_get_free_shipping_progress(): ?array {
	if ( empty( jluxe_get_theme_settings()['shop']['mini_cart_show_free_shipping'] ) ) {
		return null;
	}
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return null;
	}

	$packages = WC()->cart->get_shipping_packages();
	$package  = ! empty( $packages ) ? reset( $packages ) : array();
	$zone     = WC_Shipping_Zones::get_zone_matching_package( $package );

	if ( ! $zone ) {
		return null;
	}

	$free_method = null;
	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		if ( 'free_shipping' === $method->id ) {
			$free_method = $method;
			break;
		}
	}

	if ( ! $free_method ) {
		return null;
	}

	// requires: '' (همیشه فعال، بدون شرط)، 'min_amount'، 'coupon'، 'either'، 'both'.
	// فقط حالت‌هایی که واقعاً به «مبلغِ سفارش» ربط دارن برای نوارِ پیشرفت معنی دارن.
	$requires = $free_method->get_option( 'requires' );
	if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
		return null;
	}

	$threshold = (float) $free_method->get_option( 'min_amount', 0 );
	if ( $threshold <= 0 ) {
		return null;
	}

	// همون رقمِ «جمع کل» که خودِ مینی‌کارت نشون می‌ده — نه یک محاسبه‌ی جداگانه
	// که ممکنه (به‌خاطرِ نمایشِ مالیات) با عددِ رویِ صفحه یکی نباشه.
	$current   = (float) WC()->cart->get_subtotal() - (float) WC()->cart->get_discount_total();
	$current   = max( 0, $current );
	$remaining = max( 0, $threshold - $current );
	$achieved  = $remaining <= 0;

	return array(
		'achieved'        => $achieved,
		'remaining'       => $remaining,
		'threshold'       => $threshold,
		'remainingHtml'   => wc_price( $remaining ),
		'progressPercent' => min( 100, (int) round( ( $current / $threshold ) * 100 ) ),
	);
}

/** Report WooCommerce validation notices without leaking markup or claiming false success. */
function jluxe_cart_error( string $fallback, int $status = 400 ): void {
	$errors = wc_get_notices( 'error' );
	$message = ! empty( $errors ) ? wp_strip_all_tags( $errors[0]['notice'] ) : $fallback;
	wc_clear_notices();
	wp_send_json_error( array( 'message' => $message ), $status );
}

function jluxe_cart_post_string( string $key, string $default = '' ): string {
	return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : $default;
}

/** Serialize only public product/variation IDs and bounded quantities; never cart/customer data or prices. */
function jluxe_share_cart_encoded(): string {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) { return ''; }
	$parts = array();
	foreach ( array_slice( WC()->cart->get_cart(), 0, 20, true ) as $item ) {
		$product_id = absint( $item['product_id'] ?? 0 );
		$variation_id = absint( $item['variation_id'] ?? 0 );
		$quantity = max( 1, min( 99, absint( $item['quantity'] ?? 1 ) ) );
		if ( ! $product_id ) { continue; }
		$parts[] = $product_id . ( $variation_id ? 'v' . $variation_id : '' ) . ':' . $quantity;
	}
	return implode( ',', $parts );
}

function jluxe_share_cart_url(): string {
	if ( ! jluxe_cart_checkout_option( 'show_share_cart', true ) || ! function_exists( 'wc_get_cart_url' ) ) { return ''; }
	$encoded = jluxe_share_cart_encoded();
	return '' !== $encoded ? (string) add_query_arg( 'jluxe_share_cart', $encoded, wc_get_cart_url() ) : '';
}

/** Parse a bounded, canonical share payload; malformed input is rejected as a whole. */
function jluxe_parse_share_cart_payload( string $encoded ): ?array {
	if ( '' === $encoded || strlen( $encoded ) > 600 ) { return null; }
	$tokens = explode( ',', $encoded );
	if ( count( $tokens ) > 20 ) { return null; }
	$items = array();
	$seen = array();
	foreach ( $tokens as $token ) {
		if ( ! preg_match( '/^(\\d+)(?:v(\\d+))?:(\\d+)$/D', $token, $matches ) ) { return null; }
		$product_id = absint( $matches[1] );
		$variation_id = ! empty( $matches[2] ) ? absint( $matches[2] ) : 0;
		$quantity = absint( $matches[3] );
		if ( ! $product_id || ! $quantity || $quantity > 99 ) { return null; }
		$key = $product_id . ':' . $variation_id;
		if ( isset( $seen[ $key ] ) ) { return null; }
		$seen[ $key ] = true;
		$items[] = array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'quantity' => $quantity );
	}
	return $items;
}

/** Add each still-needed valid item without ever clearing the recipient's current cart. */
function jluxe_import_share_cart( string $encoded ): array {
	$items = jluxe_parse_share_cart_payload( $encoded );
	if ( null === $items ) { return array( 'added' => 0, 'unavailable' => 0, 'invalid' => true ); }
	$added = 0;
	$unavailable = 0;
	$already_present = 0;
	$cart = WC()->cart;
	foreach ( $items as $entry ) {
		$product = wc_get_product( $entry['product_id'] );
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) || 'publish' !== $product->get_status() || ! jluxe_product_is_public( $product ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) { ++$unavailable; continue; }
		$variation = null;
		$variation_attributes = array();
		if ( $entry['variation_id'] ) {
			$variation = wc_get_product( $entry['variation_id'] );
			if ( ! $product->is_type( 'variable' ) || ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product->get_id() || 'publish' !== $variation->get_status() || ! jluxe_product_is_public( $variation ) || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) { ++$unavailable; continue; }
			$variation_attributes = $variation->get_variation_attributes();
			if ( ! is_array( $variation_attributes ) ) { ++$unavailable; continue; }
		} elseif ( $product->is_type( 'variable' ) ) { ++$unavailable; continue; }
		$existing_quantity = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( absint( $cart_item['product_id'] ?? 0 ) === $entry['product_id'] && absint( $cart_item['variation_id'] ?? 0 ) === $entry['variation_id'] ) { $existing_quantity += absint( $cart_item['quantity'] ?? 0 ); }
		}
		if ( $existing_quantity >= $entry['quantity'] ) { ++$already_present; continue; }
		$result = $cart->add_to_cart( $entry['product_id'], $entry['quantity'], $entry['variation_id'], $variation_attributes );
		if ( false === $result || '' === $result ) { ++$unavailable; continue; }
		++$added;
	}
	return array( 'added' => $added, 'unavailable' => $unavailable, 'already_present' => $already_present, 'invalid' => false );
}

function jluxe_handle_share_cart_link(): void {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! isset( $_GET['jluxe_share_cart'] ) ) { return; }
	$raw = is_scalar( $_GET['jluxe_share_cart'] ) ? (string) wp_unslash( $_GET['jluxe_share_cart'] ) : '';
	$result = jluxe_import_share_cart( $raw );
	if ( ! empty( $result['invalid'] ) ) {
		wc_add_notice( 'لینک سبد خرید معتبر نیست.', 'error' );
	} else {
		if ( $result['added'] > 0 ) { wc_add_notice( jluxe_fa_digits( $result['added'] ) . ' کالا از سبد اشتراکی به سبد خرید شما اضافه شد.', 'success' ); }
		if ( $result['unavailable'] > 0 ) { wc_add_notice( 'برخی اقلام به دلیل اتمام موجودی یا در دسترس نبودن اضافه نشدند.', 'notice' ); }
		if ( 0 === $result['added'] && 0 === $result['unavailable'] && $result['already_present'] > 0 ) { wc_add_notice( 'اقلام این سبد از قبل در سبد خرید شما هستند.', 'notice' ); }
	}
	wp_safe_redirect( remove_query_arg( 'jluxe_share_cart' ) );
	exit;
}
add_action( 'template_redirect', 'jluxe_handle_share_cart_link', 5 );

/** Render a compact, accessible share action in the cart totals. */
function jluxe_render_share_cart_button(): void {
	$url = jluxe_share_cart_url();
	if ( '' === $url ) { return; }
	echo '<button type="button" class="jluxe-share-cart-button" data-jluxe-share-cart data-share-url="' . esc_url( $url ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/></svg><span data-share-label aria-live="polite">ارسال لیست سبد برای دیگران</span></button>';
}

/** Reject malformed/negative/infinite amounts rather than silently turning them into another action. */
function jluxe_cart_quantity( string $raw ) {
	$raw = trim( jluxe_ascii_digits( $raw ) );
	if ( ! preg_match( '/^[0-9]+(?:\.[0-9]+)?$/D', $raw ) || ! is_finite( (float) $raw ) ) {
		return null;
	}
	$quantity = wc_stock_amount( $raw );
	return is_numeric( $quantity ) && is_finite( (float) $quantity ) && $quantity >= 0 ? $quantity : null;
}

/**
 * Native variable forms contain an add-to-cart field. On admin-ajax requests,
 * Woo's wp_loaded form handler can otherwise act BEFORE our nonce/validation,
 * then add the same item again in jluxe_ajax_cart. Isolate this custom protocol
 * only; leave standard form submissions, cart links and other AJAX actions alone.
 */
function jluxe_isolate_cart_ajax_request(): void {
	if ( wp_doing_ajax() && 'jluxe_cart' === ( $_REQUEST['action'] ?? '' ) ) {
		unset( $_GET['add-to-cart'], $_POST['add-to-cart'], $_REQUEST['add-to-cart'] );
	}
}
add_action( 'wp_loaded', 'jluxe_isolate_cart_ajax_request', 1 );

/**
 * وضعیتِ واقعیِ WooCommerce 11: add_to_cart و set_quantity پس از تغییر، موجودی
 * را دوباره بررسی نمی‌کنند؛ اعتبارسنجی رسمی همین check_cart_item_stock است
 * (شمارش دوبارهٔ اقلام + سهامِ رزروشدهٔ سفارش‌های در انتظار). اگر این بررسی
 * بعد از جهش شکست بخورد، فقط نوشتنِ خودِ همین درخواست پس گرفته می‌شود و
 * پاسخ، خطای صریح است — نه موفقیتِ جعلی و نه حذفِ بقیهٔ اقلامِ کاربر.
 *
 * WC 11 status: add_to_cart/set_quantity themselves do not re-check stock;
 * this public API is their post-mutation validator. Cart-replacing plugins
 * without this method keep the previous behaviour instead of failing.
 */
function jluxe_cart_stock_check() {
	$cart = WC()->cart;
	if ( ! method_exists( $cart, 'check_cart_item_stock' ) ) {
		return true;
	}
	$result = $cart->check_cart_item_stock();
	if ( is_wp_error( $result ) ) {
		return 'تغییر سبد ثبت نشد؛ موجودی کافی نیست. لطفاً سبد را به‌روزرسانی و دوباره تلاش کنید.';
	}
	return false === $result
		? 'تغییر سبد ثبت نشد؛ موجودی کافی نیست. لطفاً سبد را به‌روزرسانی و دوباره تلاش کنید.'
		: true;
}

function jluxe_ajax_cart(): void {
	nocache_headers();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_send_json_error( array( 'message' => 'Method not allowed' ), 405 );
	}
	// A distinct pre-mutation rejection lets the client safely refresh a cached nonce once.
	if ( ! check_ajax_referer( 'jluxe_cart', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'jluxe_cart_invalid_nonce', 'message' => 'نشست منقضی شده است؛ صفحه را تازه کنید.' ), 403 );
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'سبد خرید در دسترس نیست.' ), 503 );
	}
	$op = sanitize_key( jluxe_cart_post_string( 'op', 'get' ) );
	$cart = WC()->cart;
	// WooCommerce restores the saved totals during wp_loaded and recalculates
	// them there when its session checks require it. A read-only `get` should
	// reuse those totals, not rerun shipping/tax/fee hooks on every MiniCart read.
	$cart_totals_are_current = 'get' === $op;
	wc_clear_notices();

	if ( in_array( $op, array( 'remove', 'update_qty' ), true ) ) {
		$key = sanitize_text_field( jluxe_cart_post_string( 'key' ) );
		$item = $cart->get_cart_item( $key );
		if ( ! $item || empty( $item['data'] ) ) {
			jluxe_cart_error( 'این قلم در سبد خرید وجود ندارد.', 404 );
		}
		if ( 'remove' === $op ) {
			if ( ! $cart->remove_cart_item( $key ) ) {
				jluxe_cart_error( 'حذف محصول انجام نشد.' );
			}
		} else {
			$qty = jluxe_cart_quantity( jluxe_cart_post_string( 'qty' ) );
			if ( null === $qty ) {
				jluxe_cart_error( 'تعداد معتبر نیست.' );
			}
			$qty = apply_filters( 'woocommerce_stock_amount_cart_item', $qty, $key );
			$product = $item['data'];
			$valid = apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $qty );
			if ( ! $valid || ! is_numeric( $qty ) || ! is_finite( (float) $qty ) || $qty < 0 ) {
				jluxe_cart_error( 'تغییر تعداد مجاز نیست.' );
			}
			if ( $qty > 0 ) {
				$max = $product->get_max_purchase_quantity();
				if ( ! $product->is_purchasable() || ! $product->is_in_stock() || ( $product->is_sold_individually() && $qty > 1 ) || ( $max >= 0 && $qty > $max ) ) {
					jluxe_cart_error( 'تعداد درخواستی قابل خرید نیست.' );
				}
				// Include sibling variations and other cart lines sharing the same stock owner.
				$quantities = $cart->get_cart_item_quantities();
				$stock_id = $product->get_stock_managed_by_id();
				$desired = ( $quantities[ $stock_id ] ?? $item['quantity'] ) - $item['quantity'] + $qty;
				if ( ! $product->has_enough_stock( $desired ) ) {
					jluxe_cart_error( 'موجودی کافی برای این تعداد وجود ندارد.' );
				}
			}
			$previous_quantity = isset( $item['quantity'] ) ? $item['quantity'] : null;
			// Defer tax/shipping/fee work until the shared response path below.
			// WooCommerce's set_quantity(..., false) safely supports this; a zero
			// quantity removes the line and is also followed by one final totals pass.
			if ( false === $cart->set_quantity( $key, $qty, false ) ) {
				jluxe_cart_error( 'تغییر تعداد انجام نشد.' );
			}
			$cart_totals_are_current = false;
			// Removing a line cannot create an out-of-stock cart. For increases or
			// positive quantity changes retain the full WooCommerce post-mutation
			// check (including shared stock owners and reserved stock), exactly once.
			if ( $qty > 0 ) {
				$stock = jluxe_cart_stock_check();
				if ( true !== $stock ) {
					// Roll back only this request's write and persist matching totals.
					if ( null !== $previous_quantity ) {
						$cart->set_quantity( $key, $previous_quantity, false );
						$cart->calculate_totals();
					}
					jluxe_cart_error( $stock, 409 );
				}
			}
		}
	} elseif ( 'apply_coupon' === $op ) {
		$code = wc_format_coupon_code( jluxe_cart_post_string( 'coupon_code' ) );
		if ( '' === $code || ! $cart->apply_coupon( $code ) ) {
			jluxe_cart_error( 'کد تخفیف معتبر نیست.' );
		}
	} elseif ( 'remove_coupon' === $op ) {
		if ( ! $cart->remove_coupon( wc_format_coupon_code( jluxe_cart_post_string( 'coupon_code' ) ) ) ) {
			jluxe_cart_error( 'کد تخفیف در سبد نیست.' );
		}
	} elseif ( 'add' === $op ) {
		jluxe_ajax_cart_add();
	} elseif ( 'gift_wrap' === $op ) {
		$config = jluxe_get_gift_wrap_config();
		if ( ! $config['enabled'] ) { jluxe_cart_error( 'بسته‌بندی هدیه در دسترس نیست.', 403 ); }
		$enabled = '1' === jluxe_cart_post_string( 'enabled' );
		$note = jluxe_cart_post_string( 'note' );
		jluxe_set_session_gift_wrap( $enabled, $note );
		$cart_totals_are_current = false;
	} elseif ( 'pa_services' === $op ) {
		/* کلیدهای خدماتِ انتخابیِ مودالِ «اضافه خرید» — فقط کلید پذیرفته
		 * می‌شود؛ مبلغ/عنوان هنگامِ اعمالِ فِی از تنظیماتِ سرور خوانده
		 * می‌شود (jluxe_pa_apply_service_fees). خدماتِ فقط-سبدی از مودال
		 * نمی‌آیند، پس کلیدشان این‌جا پذیرفته نمی‌شود. */
		$services = jluxe_pa_services();
		$valid = array();
		foreach ( $services as $pa_key => $pa_service ) {
			if ( in_array( $pa_service['context'], array( 'modal', 'both' ), true ) ) {
				$valid[] = $pa_key;
			}
		}
		$chosen_raw = $_POST['pa_services'] ?? array();
		$chosen = is_array( $chosen_raw ) ? $chosen_raw : explode( ',', (string) $chosen_raw );
		$stored = array();
		// The modal owns only modal/both services. Keep cart-only fees intact.
		foreach ( jluxe_pa_session_service_keys() as $existing_key ) {
			if ( isset( $services[ (string) $existing_key ] ) && 'cart' === $services[ (string) $existing_key ]['context'] ) {
				$stored[] = (string) $existing_key;
			}
		}
		foreach ( $chosen as $pa_key ) {
			if ( is_string( $pa_key ) && in_array( $pa_key, $valid, true ) ) {
				$stored[] = $pa_key;
			}
		}
		$stored = array_values( array_unique( $stored ) );
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'jluxe_pa_services', $stored );
		}
	} elseif ( 'get' !== $op ) {
		jluxe_cart_error( 'عملیات معتبر نیست.' );
	}
	wc_clear_notices();
	// Recalculate after writes unless WooCommerce/set_quantity already did it.
	if ( ! $cart_totals_are_current ) {
		$cart->calculate_totals();
	}
	$snapshot = jluxe_cart_snapshot();
	if ( '1' === jluxe_cart_post_string( 'cart_page' ) && in_array( $op, array( 'remove', 'update_qty', 'gift_wrap' ), true ) ) {
		$snapshot['cartPage'] = jluxe_cart_page_ajax_fragment();
	}
	if ( in_array( $op, array( 'add', 'get' ), true ) ) {
		/* A successful add already has a fresh snapshot. Native WooCommerce
		 * loop adds request op=get with product_id, which returns that same
		 * snapshot plus fresh suggestions without running add_to_cart again. */
		$modal_product = wc_get_product( absint( jluxe_cart_post_string( 'product_id' ) ) );
		if ( $modal_product instanceof WC_Product && $modal_product->is_type( 'variation' ) ) {
			$modal_product = wc_get_product( $modal_product->get_parent_id() );
		}
		$context_id = absint( jluxe_cart_post_string( 'pa_context_id' ) );
		if ( $context_id ) {
			$context_product = wc_get_product( $context_id );
			if ( $context_product instanceof WC_Product && $context_product->is_type( 'variation' ) ) {
				$context_product = wc_get_product( $context_product->get_parent_id() );
			}
			if ( $context_product instanceof WC_Product && function_exists( 'jluxe_product_is_public' ) && jluxe_product_is_public( $context_product ) ) {
				$modal_product = $context_product;
			}
		}
		if ( $modal_product instanceof WC_Product && function_exists( 'jluxe_product_is_public' ) && jluxe_product_is_public( $modal_product ) && function_exists( 'jluxe_suggested_modal_html_for' ) ) {
			$snapshot['suggested_html'] = jluxe_suggested_modal_html_for( $modal_product );
		}
	}
	wp_send_json_success( $snapshot );
}
add_action( 'wp_ajax_jluxe_cart', 'jluxe_ajax_cart' );
add_action( 'wp_ajax_nopriv_jluxe_cart', 'jluxe_ajax_cart' );

/**
 * Parse only the variable attributes declared by the parent product, following
 * WooCommerce's native handler. Taxonomy slugs can contain percent-encoded
 * UTF-8 (for example Persian terms); wc_clean() strips %xx sequences, so only
 * text attributes should pass through wc_clean() and entity decoding.
 */
function jluxe_cart_variation_attributes_from_request( WC_Product $product ): array {
	$variations = array();
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
			continue;
		}
		$attribute_key = 'attribute_' . sanitize_title( $attribute->get_name() );
		if ( ! isset( $_POST[ $attribute_key ] ) || ! is_scalar( $_POST[ $attribute_key ] ) ) {
			continue;
		}
		$value = (string) wp_unslash( $_POST[ $attribute_key ] );
		if ( $attribute->is_taxonomy() ) {
			$variations[ $attribute_key ] = sanitize_title( $value );
		} else {
			$variations[ $attribute_key ] = html_entity_decode( wc_clean( $value ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}
	}
	return $variations;
}

function jluxe_ajax_cart_add(): void {
	$product_id = absint( jluxe_cart_post_string( 'product_id' ) );
	$variation_id = absint( jluxe_cart_post_string( 'variation_id' ) );
	$quantity = jluxe_cart_quantity( jluxe_cart_post_string( 'quantity', '1' ) );
	$product = wc_get_product( $product_id );
	if ( $product && $product->is_type( 'variation' ) ) {
		$variation_id = $product_id;
		$product_id = $product->get_parent_id();
		$product = wc_get_product( $product_id );
	}
	if ( ! $product || 'publish' !== $product->get_status() || post_password_required( $product_id ) || null === $quantity || $quantity <= 0 ) {
		jluxe_cart_error( 'محصول یا تعداد معتبر نیست.' );
	}
	$selected = $variation_id ? wc_get_product( $variation_id ) : $product;
	if ( ! $selected || ( $variation_id && ( ! $selected->is_type( 'variation' ) || $selected->get_parent_id() !== $product_id || 'publish' !== $selected->get_status() ) ) || ( $product->is_type( 'variable' ) && ! $variation_id ) ) {
		jluxe_cart_error( 'لطفاً گزینه‌های معتبر محصول را انتخاب کنید.' );
	}
	if ( $selected->is_sold_individually() && $quantity > 1 ) {
		jluxe_cart_error( 'این محصول فقط به صورت تکی قابل خرید است.' );
	}
	$variations = jluxe_cart_variation_attributes_from_request( $product );
	wc_clear_notices();
	$valid = $variation_id
		? apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations )
		: apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	if ( ! $valid ) {
		// Extension veto: WooCommerce's own add_to_cart must not run at all.
		jluxe_cart_error( 'محصول به سبد خرید اضافه نشد.' );
	}
	// add_to_cart() can merge into an existing matching line. Keep its prior
	// quantity so a post-mutation stock failure rolls back only this request's
	// increment instead of deleting the customer's pre-existing cart item.
	$cart_before = WC()->cart->get_cart();
	$added       = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
	if ( ! $added ) {
		jluxe_cart_error( 'محصول به سبد خرید اضافه نشد.' );
	}
	$stock = jluxe_cart_stock_check();
	if ( true !== $stock ) {
		if ( isset( $cart_before[ $added ] ) ) {
			WC()->cart->set_quantity( $added, $cart_before[ $added ]['quantity'], true );
		} elseif ( method_exists( WC()->cart, 'remove_cart_item' ) ) {
			WC()->cart->remove_cart_item( $added );
		}
		jluxe_cart_error( $stock, 409 );
	}
	wc_clear_notices();
	do_action( 'woocommerce_ajax_added_to_cart', $product_id );
}
