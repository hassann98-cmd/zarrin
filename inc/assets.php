<?php
/**
 * مدیریتِ سیستماتیکِ asset ها — یک نقطهٔ تصمیم برای «این صفحه به چه چیزی نیاز دارد».
 *
 * R62 (نقشهٔ راه فاز ۱): به‌جای enqueue/dequeue های پراکنده، بافتِ صفحه یک بار
 * با jluxe_page_context() مشخص می‌شود، برنامهٔ asset ها با jluxe_asset_plan()
 * از روی همان بافت ساخته و با فیلترِ jluxe_asset_plan قابل تغییر است، و
 * jluxe_apply_asset_plan() دیر (اولویت ۱۰۰) اجرا می‌شود و هر چیزی را که در
 * برنامه نباشد از صف حذف می‌کند.
 *
 * مرزِ تأیید: این منطق سمتِ سرور است و با مجموعهٔ ایزولهٔ tests/php/run.php
 * راستی‌آزمایی می‌شود؛ رفتارِ واقعیِ مرورگر/فروشگاهِ زنده در این محیط
 * آزمون نیست (WooCommerce نصب نیست).
 */

defined( 'ABSPATH' ) || exit;

/**
 * بافتِ فعلیِ صفحه — یکی از:
 * home | product | cart | checkout | account | shop | search | 404 | blog |
 * singular | archive | generic
 *
 * ترتیبِ شرط‌ها مهم است: صفحاتِ ووکامرس (سبد/چک‌اوت/حساب) در وردپرس «صفحه»
 * هستند و is_singular() را هم true می‌دهند؛ پس قبل از آن‌ها چک می‌شوند.
 * همهٔ conditional ها با function_exists گارد شده‌اند تا بدونِ ووکامرس هم
 * (مثل محیطِ آزمون) هیچ فیتالی رخ ندهد.
 */
function jluxe_page_context(): string {
	if ( function_exists( 'is_front_page' ) && is_front_page() ) {
		return 'home';
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		return 'product';
	}
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return 'cart';
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return 'checkout';
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return 'account';
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return 'shop';
	}
	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		return 'shop';
	}
	// آرشیوِ تاکسونومی‌های محصول (مثل product_brand) هم سطحِ محصول است.
	if ( function_exists( 'is_tax' ) && is_tax() ) {
		return 'shop';
	}
	if ( function_exists( 'is_search' ) && is_search() ) {
		return 'search';
	}
	if ( function_exists( 'is_404' ) && is_404() ) {
		return '404';
	}
	if ( function_exists( 'is_home' ) && ( is_home() || is_category() || is_tag() || is_date() ) ) {
		return 'blog';
	}
	if ( function_exists( 'is_singular' ) && is_singular() ) {
		return 'singular';
	}
	if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive() ) {
		return 'archive';
	}

	return 'generic';
}

/**
 * برنامهٔ پیش‌فرضِ asset ها برای یک بافت.
 *
 * - theme_woo_ux: اسکریپت‌های UX خودِ تم (woocommerce.js + انتخابگرِ تنویع) —
 *   هر جا که کارت محصول/فرم افزودن/سبد ممکن است حاضر باشد. صفحاتِ ثابت
 *   (singular) عمداً روشن می‌مانند چون شورت‌کد/بخشِ محصول ممکن است در آن‌ها
 *   رندر شود؛ بهینه‌سازیِ بیش از این بدون دیدنِ سایتِ زنده ریسک شکستن دارد.
 * - woo_core_scripts / woo_css: asset های خودِ ووکامرس — فقط در بافت‌های
 *   «لاغر» (بلاگ، 404، آرشیوِ غیرمحصولی، generic) حذف می‌شوند؛ آنجا هیچ
 *   markup محصولی از قالب رندر نمی‌شود.
 * - cart_fragments: همیشه خاموش — مینی‌کارتِ تم (src/islands/MiniCart.js و
 *   use-cart.js) از REST خودش و initialCount سراسریِ localize شده تغذیه
 *   می‌شود؛ هیچ قالبی ویجتِ mini-cart وو را رندر نمی‌کند، پس
 *   wc-cart-fragments (یک درخواست AJAX + نوشتن session در هر بازدید) صرفاً
 *   هدررفت است. با فیلترِ jluxe_asset_plan قابل بازگشت است.
 * - jquery_migrate: در بافت‌های لاغر حذف می‌شود (ووکامرسِ مدرن به آن نیاز
 *   ندارد؛ افزونه‌های قدیمی می‌توانند با فیلتر برگردانند).
 *
 * @return array<string, mixed>
 */
function jluxe_default_asset_plan( string $context ): array {
	$has_product_ui = in_array(
		$context,
		array( 'home', 'product', 'cart', 'checkout', 'account', 'shop', 'search', 'singular' ),
		true
	);
	$is_lean = in_array( $context, array( 'blog', '404', 'archive', 'generic' ), true );

	return array(
		'context'          => $context,
		'theme_woo_ux'     => $has_product_ui,
		'woo_core_scripts' => ! $is_lean,
		'woo_css'          => ! $is_lean,
		'cart_fragments'   => false, // مینی‌کارتِ تم REST-based است (توضیح بالا).
		'jquery_migrate'   => ! $is_lean,
	);
}

/**
 * برنامهٔ asset های صفحهٔ فعلی — قابل فیلتر از افزونه/چایلدتم:
 * add_filter( 'jluxe_asset_plan', function ( $plan, $context ) { ... } , 10, 2 );
 *
 * @return array<string, mixed>
 */
function jluxe_asset_plan(): array {
	$context = jluxe_page_context();
	$plan    = jluxe_default_asset_plan( $context );

	/**
	 * تغییرِ برنامهٔ asset هر صفحه — هر کلیدی که false شود در
	 * jluxe_apply_asset_plan() از صف حذف می‌شود.
	 */
	return apply_filters( 'jluxe_asset_plan', $plan, $context ); // phpcs:ignore WordPress.NamingConventions.ValidHookName
}

/**
 * اجرای برنامه — اولویتِ ۱۰۰ یعنی بعد از همهٔ enqueue های تم و ووکامرس،
 * پیش از چاپ صف (wp_enqueue_scripts → رندرِ قالب).
 */
function jluxe_apply_asset_plan(): void {
	$plan = jluxe_asset_plan();

	$dequeue_scripts = static function ( array $handles ): void {
		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
		}
	};
	$dequeue_styles = static function ( array $handles ): void {
		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
		}
	};

	if ( empty( $plan['theme_woo_ux'] ) ) {
		$dequeue_scripts( array( 'jluxe-woocommerce', 'wc-add-to-cart-variation' ) );
	}

	if ( empty( $plan['cart_fragments'] ) ) {
		// مینی‌کارتِ تم REST-based است؛ fragments فقط AJAX/session اضافه می‌سازد.
		$dequeue_scripts( array( 'wc-cart-fragments' ) );
	}

	if ( empty( $plan['woo_core_scripts'] ) ) {
		$dequeue_scripts(
			array(
				'wc-add-to-cart',
				'wc-add-to-cart-variation',
				'wc-single-product',
				'wc-cart',
				'wc-checkout',
				'wc-cart-fragments',
				'wc-price-format',
				'wc-address-i18n',
				'select2',
				'selectWoo',
				'jquery-blockui',
				'prettyPhoto',
				'prettyPhoto-init',
				'zoom',
			)
		);
	}

	if ( empty( $plan['woo_css'] ) ) {
		$dequeue_styles(
			array(
				'woocommerce-general',
				'woocommerce-layout',
				'woocommerce-smallscreen',
				'woocommerce-inline',
				'wc-blocks-style',
				'wc-blocks-checkout-style',
			)
		);
	}

	if ( empty( $plan['jquery_migrate'] ) ) {
		$dequeue_scripts( array( 'jquery-migrate' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'jluxe_apply_asset_plan', 100 );
