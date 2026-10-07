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
	check_ajax_referer( 'jluxe_cart', 'nonce' );

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

		$items[] = array(
			'key'           => $key,
			'productId'     => $cart_item['product_id'],
			'name'          => $product->get_name(),
			'url'           => get_permalink( $cart_item['product_id'] ),
			'image'         => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			'variation'     => implode( '، ', $variation ),
			'qty'           => (int) $cart_item['quantity'],
			// get_product_subtotal() برخلاف get_subtotal() یک HTML آماده (با
			// <span class="woocommerce-Price-amount">) برمی‌گردونه نه عدد خام —
			// دوباره پیچیدنش توی wc_price() رشته‌ی HTML رو به عدد صفر تبدیل
			// می‌کنه (باگ واقعی که باعث می‌شد قیمت هر ردیف «۰ تومان» نشون داده بشه).
			// jluxe_fa_digits() دستیِ دورش عمداً حذف شد — get_product_subtotal()
			// خودش داخلاً wc_price() صدا می‌زنه که دیگه (بعد از رفعِ is_admin در
			// jluxe_fa_digits_wc_price) توی AJAX هم ارقام رو خودش فارسی می‌کنه؛
			// پیچیدنِ دوباره‌ش آیکونِ SVG تومان رو خراب می‌کرد (باگِ واقعیِ
			// گزارش‌شده در سبدِ کشویی).
			'lineTotalHtml' => $cart->get_product_subtotal( $product, $cart_item['quantity'] ),
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

	return array(
		'items'         => $items,
		'itemCount'     => $cart->get_cart_contents_count(),
		'subtotalHtml'  => wc_price( $cart->get_subtotal() ),
		'coupons'       => $coupons,
		'discountHtml'  => $cart->get_discount_total() > 0 ? wc_price( $cart->get_discount_total() ) : null,
		'freeShipping'  => jluxe_get_free_shipping_progress(),
		'cartUrl'       => wc_get_cart_url(),
		'checkoutUrl'   => wc_get_checkout_url(),
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
			if ( false === $cart->set_quantity( $key, $qty, true ) ) {
				jluxe_cart_error( 'تغییر تعداد انجام نشد.' );
			}
			$stock = jluxe_cart_stock_check();
			if ( true !== $stock ) {
				// Roll back only this request's write; the pre-update quantity was validated above.
				if ( null !== $previous_quantity ) {
					$cart->set_quantity( $key, $previous_quantity, true );
				}
				jluxe_cart_error( $stock, 409 );
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
	// Let WooCommerce own session persistence; compute the snapshot after totals are current.
	$cart->calculate_totals();
	$snapshot = jluxe_cart_snapshot();
	if ( 'add' === $op || ( 'get' === $op && '1' === jluxe_cart_post_string( 'include_suggestions' ) ) ) {
		/* R61 — مودالِ «اضافه خرید» به‌جای اتکا به مارک‌آپِ از پیشِ لودشده،
		 * HTML تازه‌اش را در همین پاسخ می‌گیرد: وضعیتِ موجودی/قابل‌خریدِ
		 * پیشنهادها بعد از افزودنِ واقعی محاسبه می‌شود و کلاینتِ موفقِ
		 * «افزودن به سبد» همیشه همان نسخهٔ معتبرِ لحظهٔ افزودن را نشان می‌دهد. */
		$added_product = 'add' === $op ? wc_get_product( absint( jluxe_cart_post_string( 'product_id' ) ) ) : null;
		if ( $added_product && $added_product->is_type( 'variation' ) ) { $added_product = wc_get_product( $added_product->get_parent_id() ); }
		$modal_product = $added_product;
		$context_id    = absint( jluxe_cart_post_string( 'pa_context_id' ) );
		if ( $context_id ) {
			$context_product = wc_get_product( $context_id );
			if ( function_exists( 'jluxe_product_is_public' ) && jluxe_product_is_public( $context_product ) ) {
				$modal_product = $context_product;
			}
		}
		$snapshot['suggested_html'] = '';
		if ( jluxe_product_is_public( $modal_product ) && function_exists( 'jluxe_suggested_modal_html_for' ) ) {
			$snapshot['suggested_html'] = jluxe_suggested_modal_html_for( $modal_product );
		}
	}
	wp_send_json_success( $snapshot );
}
add_action( 'wp_ajax_jluxe_cart', 'jluxe_ajax_cart' );
add_action( 'wp_ajax_nopriv_jluxe_cart', 'jluxe_ajax_cart' );

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
	$variations = array();
	foreach ( $_POST as $key => $value ) {
		if ( 0 === strpos( (string) $key, 'attribute_' ) && is_scalar( $value ) ) {
			$variations[ sanitize_title( wp_unslash( $key ) ) ] = wc_clean( wp_unslash( $value ) );
		}
	}
	wc_clear_notices();
	$valid = $variation_id
		? apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations )
		: apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	if ( ! $valid ) {
		// Extension veto: WooCommerce's own add_to_cart must not run at all.
		jluxe_cart_error( 'محصول به سبد خرید اضافه نشد.' );
	}
	$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
	if ( ! $added ) {
		jluxe_cart_error( 'محصول به سبد خرید اضافه نشد.' );
	}
	$stock = jluxe_cart_stock_check();
	if ( true !== $stock ) {
		// Undo only our own just-added line; never touch the customer's other items.
		if ( method_exists( WC()->cart, 'remove_cart_item' ) ) {
			WC()->cart->remove_cart_item( $added );
		}
		jluxe_cart_error( $stock, 409 );
	}
	wc_clear_notices();
	do_action( 'woocommerce_ajax_added_to_cart', $product_id );
}
