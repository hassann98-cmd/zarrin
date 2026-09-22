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

	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		wp_send_json_error();
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
	$available_variations  = $product->get_available_variations();
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
			/*
			 * باگِ واقعیِ ریشه‌ایِ کلِ ماجرایِ «سبد خالیه ولی خطایِ موجودی
			 * می‌ده» (تأییدشده با تستِ زنده روی noghrehmilad.ir): یک آیتمِ
			 * سبد که $cart_item['data'] اش نامعتبره (مثلاً از یک دورِ خیلی
			 * قدیمیِ تست، قبل از راه‌حلِ round 2 که variation_id رو درست
			 * می‌فرستاد) قبلاً این‌جا فقط continue می‌شد — یعنی از نمایشِ
			 * سبد/شمارنده‌ی هدر (که همینجا حساب می‌شه) کاملاً حذف می‌شد،
			 * ولی همچنان توی WC()->cart->get_cart() خامِ خودِ ووکامرس
			 * (که WC_Cart::add_to_cart از موجودیِ مشترکِ سطحِ محصول بر
			 * همین اساس چک می‌کنه) باقی می‌موند. نتیجه: سبد و شمارنده صفر
			 * نشون می‌دادن، ولی افزودنِ هر سایزِ دیگه‌ای از همون محصول با
			 * «قبلاً ۱ عدد در سبد شما موجوده» رد می‌شد — چون از دیدِ خودِ
			 * ووکامرس واقعاً یک چیزی اونجا بود، فقط ما دیدنش رو بهِ کاربر
			 * قایم کرده بودیم. راه‌حل: به‌جایِ صرفاً نادیده‌گرفتنش توی
			 * نمایش، همین‌جا واقعاً از سبد حذفش می‌کنیم تا خودشو درست کنه.
			 */
			$cart->remove_cart_item( $key );
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

function jluxe_ajax_cart(): void {
	check_ajax_referer( 'jluxe_cart', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error();
	}

	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : 'get';

	if ( 'remove' === $op && ! empty( $_POST['key'] ) ) {
		WC()->cart->remove_cart_item( sanitize_text_field( wp_unslash( $_POST['key'] ) ) );
	} elseif ( 'update_qty' === $op && ! empty( $_POST['key'] ) ) {
		$qty = max( 0, (int) ( $_POST['qty'] ?? 1 ) );
		WC()->cart->set_quantity( sanitize_text_field( wp_unslash( $_POST['key'] ) ), $qty, true );
	} elseif ( 'apply_coupon' === $op && ! empty( $_POST['coupon_code'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) );
		wc_clear_notices();
		$applied = WC()->cart->apply_coupon( $code );
		if ( ! $applied ) {
			$errors  = wc_get_notices( 'error' );
			$message = ! empty( $errors ) ? wp_strip_all_tags( $errors[0]['notice'] ) : 'کد تخفیف معتبر نیست.';
			wc_clear_notices();
			wp_send_json_error( array( 'message' => $message ) );
		}
		wc_clear_notices();
	} elseif ( 'remove_coupon' === $op && ! empty( $_POST['coupon_code'] ) ) {
		WC()->cart->remove_coupon( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) );
	} elseif ( 'add' === $op ) {
		jluxe_ajax_cart_add();
	}

	/*
	 * باگِ ریشه‌ایِ واقعیِ کلِ ماجرا («سبد خالیه ولی خطایِ موجودی می‌ده،
	 * بعدِ رفرش معلوم می‌شه واقعاً اضافه/حذف شده»؛ پیدا و تأییدشده با
	 * خوندنِ مستقیمِ سورسِ ووکامرس + تستِ زنده روی جدولِ خامِ
	 * wp_woocommerce_sessions، حتی روی لوکالِ تک‌کاربره بدونِ هیچ کشی):
	 * WC_Cart_Session تنها زمانی سبدِ توی حافظه رو واقعاً رویِ سشن ذخیره
	 * می‌کنه (متدِ set_session) که هوکِ woocommerce_after_calculate_totals
	 * شلیک بشه — و اون فقط داخلِ WC_Cart::calculate_totals() صدا زده
	 * می‌شه. remove_cart_item()/remove_coupon() هیچ‌کدوم خودشون
	 * calculate_totals() رو صدا نمی‌زنن؛ فقط $this->cart_contents رو توی
	 * حافظه (همین درخواست) عوض می‌کنن. یعنی پاسخِ همین درخواست («حذف
	 * شد») درسته، ولی چون set_session() هیچ‌وقت صدا زده نشده، سشنِ واقعیِ
	 * ذخیره‌شده هیچ‌وقت آپدیت نمی‌شه — درخواستِ بعدی (مثلاً افزودنِ یک
	 * سایزِ دیگه) هنوز آیتمِ «حذف‌شده» رو از سشن می‌خونه و چکِ موجودیِ
	 * ووکامرس رو غلط رد می‌کنه. راه‌حل: بعدِ هر عملیاتی که سبد رو تغییر
	 * می‌ده، صریحاً calculate_totals() رو خودمون صدا می‌زنیم تا set_session()
	 * حتماً اجرا بشه و سشن واقعاً به‌روز بمونه.
	 *
	 * نکته‌ی مهم: این‌جا (بعد از jluxe_cart_snapshot، نه قبلش) صدا زده
	 * می‌شه — چون خودِ jluxe_cart_snapshot() هم ممکنه یک آیتمِ خراب/نامعتبر
	 * رو خودش از سبد حذف کنه (خودترمیمیِ توضیح‌داده‌شده در تعریفِ همون
	 * تابع)، حتی برایِ یک op=get ساده. اگه calculate_totals() قبل از
	 * snapshot صدا زده بشه، اون حذفِ خودترمیم هیچ‌وقت ذخیره نمی‌شه.
	 */
	$snapshot = jluxe_cart_snapshot();
	WC()->cart->calculate_totals();

	wp_send_json_success( $snapshot );
}
add_action( 'wp_ajax_jluxe_cart', 'jluxe_ajax_cart' );
add_action( 'wp_ajax_nopriv_jluxe_cart', 'jluxe_ajax_cart' );

/**
 * افزودنِ واقعی به سبد — جایگزینِ endpoint خامِ ووکامرس (wc-ajax=add_to_cart)
 * که assets/js/woocommerce.js قبلاً برای فرمِ صفحه‌ی تکیِ محصول استفاده
 * می‌کرد. باگِ واقعیِ گزارش‌شده (روی سایتِ زنده‌ی دیگه‌ای که همین تمِ زرین رو
 * داره، noghrehmilad.ir — و همین کدِ دقیقاً یکسان توی این ریپازیتوری هم بود،
 * پس jluxe.ir هم موقعِ لانچ همین باگ رو می‌گرفت): برای محصولِ متغیر، فرم
 * variation_id رو جدا از product_id می‌فرسته، ولی WC_AJAX::add_to_cart
 * سمتِ سرور فقط $_POST['product_id'] رو می‌خونه و با ۲ آرگومان
 * ($product_id, $quantity) صدا می‌زنه WC()->cart->add_to_cart() رو — یعنی
 * variation_id همیشه ۰ فرض می‌شه مگر اینکه صریحاً بشه product_id. تلاشِ
 * قبلی (ست‌کردنِ product_id = variation_id، با این استدلال که خودِ
 * WC_Cart::add_to_cart «تشخیص می‌ده» چون نوعِ پستش product_variation-ه)
 * تقریباً درسته — واقعاً هم آیتم درست به سبد اضافه می‌شه — ولی
 * WC_AJAX::add_to_cart بعد از فراخوانی، $product_status = get_post_status
 * ($product_id) رو هنوز روی همون product_id اصلی (که تنوعه، نه والد) چک
 * می‌کنه و شرطِ موفقیت رو fail می‌کنه؛ نتیجه دقیقاً همون چیزی که کاربر دید:
 * پاسخِ AJAX {error:true} (پس توستِ «لطفاً گزینه‌های محصول را انتخاب کنید»
 * نشون داده می‌شه) با این‌حال آیتم واقعاً توی سبد نشسته (session واقعاً
 * mutate شده) — فقط با رفتن به صفحه‌ی دیگه معلوم می‌شه.
 *
 * راه‌حلِ درست: یک endpoint خودمون که product_id (والد) و variation_id رو
 * جدا جدا می‌گیره و دقیقاً همون امضای ۴تاییِ استانداردِ خودِ ووکامرس رو صدا
 * می‌زنه (WC_Form_Handler::add_to_cart_action غیرِ AJAX هم دقیقاً همین
 * امضا رو استفاده می‌کنه) — بدونِ حدس‌زدن/سوءاستفاده از تشخیصِ خودکارِ
 * نوعِ پست.
 */
function jluxe_ajax_cart_add(): void {
	$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
	$quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;

	if ( ! $product_id || $quantity <= 0 ) {
		wp_send_json_error( array( 'message' => 'محصول نامعتبر است.' ) );
	}

	// فیلدهای attribute_* دقیقاً همون‌هایی‌ان که خودِ فرمِ ووکامرس
	// (variation-add-to-cart-button.php/select های ویژگی) می‌سازه — کلید و
	// مقدار همین‌جوری خام (بدونِ اسلش/HTML خطرناک) عیناً نگه داشته می‌شن،
	// چون jluxe_cart_snapshot() بعداً همین کلیدها رو با پیشوندِ attribute_
	// از cart_item['variation'] می‌خونه (باید دقیقاً هم‌شکل بمونه).
	$variation_attributes = array();
	foreach ( $_POST as $post_key => $post_value ) {
		if ( 0 === strpos( $post_key, 'attribute_' ) ) {
			$variation_attributes[ $post_key ] = sanitize_text_field( wp_unslash( $post_value ) );
		}
	}

	/*
	 * باگِ ریشه‌ایِ واقعیِ کلِ ماجرا (با تستِ سنگین، خطبه‌خط، مقایسه‌ی
	 * مستقیمِ همین یک درخواست تأییدشده): WC()->cart->get_cart() گاهی یک
	 * آیتمِ کاملاً حذف‌شده (توسطِ یک درخواستِ درست‌وحسابی‌ی قبلی، که
	 * calculate_totals/set_session ش هم درست اجرا شده) رو هنوز نشون
	 * می‌ده — درحالی‌که یک کوئریِ خامِ $wpdb، توی همین دقیقاً همون
	 * درخواست، رویِ جدولِ wp_woocommerce_sessions می‌بینه که واقعاً خالیه.
	 * یعنی خودِ bootstrap اولیه‌ی ووکامرس (روی wp_loaded، قبل از اینکه
	 * کدِ ما اصلاً اجرا بشه) گاهی یک نسخه‌ی قدیمی رو خونده — این دیگه به
	 * کدِ ما، به کشِ آبجکت (که رویِ این سرورها اصلاً فعال نیست)، به
	 * OPcache، یا به وضعیتِ لاگین ربطی نداره. راه‌حل: به‌جایِ اعتماد به
	 * WC()->cart->get_cart()، خودِ جدولِ خام رو منبعِ حقیقت می‌گیریم — اگه
	 * فرقی با حافظه داشت، دستی آیتمِ فانتومِ همین variation رو از حافظه
	 * حذف می‌کنیم تا add_to_cart() یک بارِ دیگه، این‌بار درست، امتحان کنه.
	 */
	global $wpdb;
	$fresh_customer_id = WC()->session ? WC()->session->get_customer_id() : '';
	$fresh_raw          = $fresh_customer_id ? $wpdb->get_var( $wpdb->prepare( "SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s", $fresh_customer_id ) ) : null;
	$fresh_session_data = $fresh_raw ? (array) maybe_unserialize( $fresh_raw ) : array();
	$fresh_cart         = isset( $fresh_session_data['cart'] ) ? (array) maybe_unserialize( $fresh_session_data['cart'] ) : array();

	foreach ( WC()->cart->get_cart() as $stale_key => $stale_item ) {
		$still_in_db = false;
		foreach ( $fresh_cart as $fresh_item ) {
			if ( (int) $fresh_item['product_id'] === (int) $stale_item['product_id'] && (int) $fresh_item['variation_id'] === (int) $stale_item['variation_id'] ) {
				$still_in_db = true;
				break;
			}
		}
		if ( ! $still_in_db ) {
			WC()->cart->remove_cart_item( $stale_key );
		}
	}

	// کلیدهایِ سبد قبل از فراخوانی — برایِ فال‌بکِ دفاعیِ زیر لازمه.
	$cart_keys_before = array_keys( WC()->cart->get_cart() );

	wc_clear_notices();
	$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attributes );

	/*
	 * باگِ واقعیِ گزارش‌شده و با تستِ زنده (روی noghrehmilad.ir) تأییدشده:
	 * وقتی محصول موجودی‌اش در سطحِ کلِ محصول مدیریت می‌شه (نه هر تنوع
	 * جداگانه)، چکِ «قبلاً X در سبدِ شما هست» (بخشِ managing_stock توی
	 * WC_Cart::add_to_cart) گاهی استثنا پرت می‌کنه و add_to_cart() مقدارِ
	 * false برمی‌گردونه — ولی با این‌حال آیتم واقعاً به cart_contents سشن
	 * اضافه می‌شه (با یک درخواستِ تنها، بدونِ ارسالِ دوباره، با ابزارِ شبکه
	 * تأیید شد: پاسخ success:false بود ولی بلافاصله بعدش سبد واقعاً همون
	 * تنوع رو داشت). یعنی add_to_cart() به‌جایِ گزارشِ راستِ نتیجه، دروغ
	 * می‌گه. به‌جایِ اینکه به این return value اعتماد کنیم، مستقیم چک
	 * می‌کنیم آیا یک کلیدِ جدید واقعاً به سبد اضافه شده — اگه شده، محصول
	 * واقعاً مالِ مشتریه، پس بی‌خودی بهش خطا نشون نمی‌دیم.
	 */
	if ( ! $cart_item_key ) {
		$cart_keys_after = array_keys( WC()->cart->get_cart() );
		$new_keys        = array_diff( $cart_keys_after, $cart_keys_before );

		if ( ! empty( $new_keys ) ) {
			wc_clear_notices();
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );
			return;
		}

		$errors  = wc_get_notices( 'error' );
		$message = ! empty( $errors ) ? wp_strip_all_tags( $errors[0]['notice'] ) : 'محصول به سبد خرید اضافه نشد.';
		wc_clear_notices();
		wp_send_json_error( array( 'message' => $message ) );
	}
	wc_clear_notices();

	do_action( 'woocommerce_ajax_added_to_cart', $product_id );
}
