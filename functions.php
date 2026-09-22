<?php
/**
 * JLuxe theme bootstrap.
 */

defined( 'ABSPATH' ) || exit;

define( 'JLUXE_THEME_DIR', get_template_directory() );
define( 'JLUXE_THEME_URI', get_template_directory_uri() );
define( 'JLUXE_VITE_DEV_SERVER', 'http://localhost:5173' );

/**
 * Theme supports.
 */
function jluxe_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی', 'jluxe' ),
			'footer'  => __( 'منوی فوتر', 'jluxe' ),
		)
	);

	/*
	 * اخطارِ واقعیِ PageSpeed («Improve image delivery» — کارت‌های محصول
	 * ۶۰۰×۶۰۰ دانلود می‌شن ولی فقط ۲۲۱×۲۲۱ نمایش داده می‌شن، ~۴۰۵ کیلوبایت
	 * تلف‌شده): علتش اینه که تویِ این سایت woocommerce_thumbnail (توی
	 * وردپرس ← ووکامرس ← تنظیمات ← محصولات ← نمایش) رویِ ۶۰۰×۶۰۰ ست شده،
	 * و srcset ای که wp_get_attachment_image_srcset() می‌سازه فقط از بینِ
	 * سایزهای واقعاً ثبت‌شده با همون نسبتِ تصویر انتخاب می‌کنه — چون هیچ
	 * سایزِ کوچیک‌ترِ ۱:۱ ثبت نشده بود، گزینه‌ی کوچیک‌تری برای مرورگر
	 * وجود نداشت.
	 *
	 * این سایزِ جدید کاملاً افزودنیه — به woocommerce_thumbnail دست
	 * نمی‌زنه (همونی که قبلاً یک‌بار به‌خاطرِ تغییرش رگرسیونِ واقعی پیش
	 * اومده بود، طبقِ کامنتِ woocommerce/content-product.php)، فقط یک
	 * گزینه‌ی کوچیک‌ترِ اضافه به srcset میده تا مرورگر خودش (بر اساسِ
	 * sizes="...220px") انتخابِ درست‌تری داشته باشه.
	 *
	 * نکته‌ی مهم: این سایز فقط برای عکس‌هایی که از الان به بعد آپلود
	 * می‌شن خودکار می‌سازه؛ برای عکس‌های محصولِ موجود، یک‌بار از پیشخوان
	 * ← ابزارها یک regenerate thumbnails لازمه (یا پلاگینِ Regenerate
	 * Thumbnails) تا همین سایزِ کوچیک براشون هم ساخته بشه.
	 */
	add_image_size( 'jluxe-product-thumb-sm', 320, 320, true );
}
add_action( 'after_setup_theme', 'jluxe_setup' );

function jluxe_enqueue_theme_stylesheet(): void {
	$path = get_stylesheet_directory() . '/style.css';
	wp_enqueue_style(
		'jluxe-theme-style',
		get_stylesheet_uri(),
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : null
	);
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_theme_stylesheet', 25 );

function jluxe_enable_checkout_select2_wheel(): void {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	$script = <<<'JS'
(function () {
	function enableSelect2Wheel() {
		document.querySelectorAll('.select2-results__options').forEach(function (list) {
			if (list.dataset.jluxeWheelBound) return;
			list.dataset.jluxeWheelBound = '1';
			list.style.maxHeight = 'min(18rem, 45vh)';
			list.style.overflowY = 'auto';
			list.addEventListener('wheel', function (event) {
				if (Math.abs(event.deltaY) > 0) event.stopPropagation();
			}, { passive: true });
		});
	}
	document.addEventListener('click', enableSelect2Wheel, true);
	document.addEventListener('focusin', enableSelect2Wheel, true);
	new MutationObserver(enableSelect2Wheel).observe(document.body, { childList: true, subtree: true });
	enableSelect2Wheel();
}());
JS;
	wp_add_inline_script( 'jluxe-main', $script, 'after' );
}
add_action( 'wp_enqueue_scripts', 'jluxe_enable_checkout_select2_wheel', 30 );

/**
 * URL واحدِ فروشگاه — نامکِ صفحه‌ی فروشگاه ممکن است فارسی یا سفارشی باشد؛
 * هیچ لینک frontend نباید مسیرِ hardcode شده‌ی /shop/ داشته باشد.
 */
function jluxe_shop_url(): string {
	return home_url( '/shop/' );
}

function jluxe_shop_canonical_url( $canonical ) {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return jluxe_shop_url();
	}
	return $canonical;
}
add_filter( 'rank_math/frontend/canonical', 'jluxe_shop_canonical_url', 20 );

/**
 * سازگاری با لینک‌های قدیمیِ /shop/ و /فروشگاه/ — هر دو به برگه‌ی فروشگاه
 * واقعیِ ووکامرس rewrite می‌شوند تا لینک‌های ذخیره‌شده یا خارجی 404 ندهند.
 */
function jluxe_register_shop_rewrite(): void {
	$shop_page_id = absint( get_option( 'woocommerce_shop_page_id' ) );
	if ( ! $shop_page_id ) {
		return;
	}
	add_rewrite_rule( '^shop/?$', 'index.php?page_id=' . $shop_page_id, 'top' );
	add_rewrite_rule( '^فروشگاه/?$', 'index.php?page_id=' . $shop_page_id, 'top' );
}
add_action( 'init', 'jluxe_register_shop_rewrite', 1 );

function jluxe_flush_shop_rewrite_once(): void {
	if ( get_option( 'jluxe_shop_rewrite_version' ) === '1.48.4' ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'jluxe_shop_rewrite_version', '1.48.4', false );
}
add_action( 'init', 'jluxe_flush_shop_rewrite_once', 99 );

function jluxe_redirect_legacy_persian_shop_url(): void {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$path = rawurldecode( rawurldecode( $path ) );
	if ( 'فروشگاه' !== trim( $path, '/' ) ) {
		return;
	}
	wp_safe_redirect( jluxe_shop_url(), 301 );
	exit;
}
add_action( 'template_redirect', 'jluxe_redirect_legacy_persian_shop_url', 0 );

/**
 * Whether we're running against the Vite dev server (HMR) instead of a production build.
 * Toggle only by defining JLUXE_DEV=true in wp-config.php; WP_DEBUG alone never enables the Vite dev server.
 */
function jluxe_is_dev(): bool {
	return defined( 'JLUXE_DEV' ) && (bool) JLUXE_DEV;
}

/**
 * Read the Vite build manifest (production only).
 */
function jluxe_vite_manifest(): array {
	static $manifest = null;

	if ( null !== $manifest ) {
		return $manifest;
	}

	$path = JLUXE_THEME_DIR . '/dist/.vite/manifest.json';

	if ( ! file_exists( $path ) ) {
		$manifest = array();
		return $manifest;
	}

	$contents = file_get_contents( $path );
	$manifest = json_decode( $contents, true );

	if ( ! is_array( $manifest ) ) {
		$manifest = array();
	}

	return $manifest;
}

/**
 * پیش‌بارگذاریِ فونتِ متنِ اصلی — طبقِ اولویتِ کاربر («Regular/Medium فقط،
 * نه همه‌ی وزن‌ها»). فقط همون دو وزنی که واقعاً برای متنِ عمومی/بالای صفحه
 * استفاده می‌شن (Regular=۴۰۰ پیش‌فرضِ body، Medium=۵۰۰ رایج‌ترین وزنِ UI —
 * ۳۶ جا در کد استفاده شده) پیش‌بارگذاری می‌شن؛ Bold/ExtraBold/Black چون
 * معمولاً روی عنوان/بج‌های کمتر-حیاتی‌ترن، دست‌نخورده و به‌روالِ عادیِ
 * font-display:swap می‌مونن — پیش‌بارگذاریِ همه‌ی ۶ وزن یعنی رقابت روی
 * پهنای‌باندِ همون بایت‌هایی که برای FCP/LCP واقعاً حیاتی‌ترن (HTML/CSS/
 * عکسِ LCP)، دقیقاً برعکسِ هدف.
 *
 * از منیفستِ خودِ Vite می‌خونه (نه یک نامِ فایلِ هاردکد) چون هش‌ها هر بیلد
 * عوض می‌شن؛ در حالتِ dev (که این منیفست اصلاً وجود نداره) کاملاً غیرفعاله.
 */
function jluxe_preload_body_fonts(): void {
	if ( jluxe_is_dev() ) {
		return;
	}
	$manifest = jluxe_vite_manifest();
	/*
	 * .woff2 نه .ttf — این دو وزن ساب‌ست شدن (فقط گلیف‌هایِ واقعاً
	 * استفاده‌شده: الفبا/ارقامِ فارسی + لاتین + علائمِ رایج) و به‌جایِ
	 * فایلِ اصلیِ ttf (~۵۹کیلوبایت) به woff2 (~۱۶کیلوبایت، ~۷۳٪ کوچیک‌تر)
	 * تبدیل شدن — همون فایلی که globals.css هم به‌عنوانِ اولویتِ اول
	 * declare می‌کنه (src بامرتبه: woff2 اول، ttf fallback بعدش)، پس این
	 * پرلود دقیقاً همون بایتی رو می‌گیره که مرورگر واقعاً دانلود می‌کنه.
	 */
	$weights = array(
		'src/assets/fonts/IRANYekanMobileRegular.woff2',
		'src/assets/fonts/IRANYekanMobileMedium.woff2',
	);
	foreach ( $weights as $src_path ) {
		if ( empty( $manifest[ $src_path ]['file'] ) ) {
			continue;
		}
		printf(
			'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
			esc_url( JLUXE_THEME_URI . '/dist/' . $manifest[ $src_path ]['file'] )
		);
	}
}
add_action( 'wp_head', 'jluxe_preload_body_fonts', 2 );

/**
 * اخطارِ واقعیِ PageSpeed (از اولین بررسیِ این پروژه، هنوز حل‌نشده مونده
 * بود): چهار وزنِ سنگین (Bold=700, ExtraBold=800, Black=900, ExtraBlack=950)
 * توی باندلِ کامپایل‌شده/قفل‌شده فقط نسخه‌ی .ttf دارن (~59KB خام برای هرکدوم)
 * — نه woff2، برخلافِ Regular/Medium که این تبدیل رو در سطحِ خودِ سورس
 * (globals.css، قبل از build) قبلاً گرفتن. چون نمی‌تونیم اون باندلِ قفل‌شده
 * رو دوباره کامپایل کنیم، این‌جا نسخه‌ی woff2 رو (با fonttools، از همون
 * فایل‌های ttf موجود ساخته شده) به‌عنوانِ یک @font-face جدید (با همون
 * family/weight/style) دیرتر در <head> اعلام می‌کنیم — طبقِ قواعدِ استانداردِ
 * CSS، آخرین @font-face با همون family/weight/style برنده‌ست، پس مرورگرهای
 * امروزی (تقریباً همه) دیگه سراغِ ttf سنگین نمی‌رن، فقط اگه مرورگری واقعاً
 * woff2 رو نشناسه (خیلی نادر) به همون ttf اصلی برمی‌گرده.
 *
 * گزارشِ PageSpeed خودش تأیید می‌کنه این وزن‌ها واقعاً روی صفحه استفاده
 * می‌شن (Bold=700 در ۱۲ جای CSS، پرکاربردترینِ وزن‌های غیرِ ۴۰۰/۵۰۰) —
 * پس preload نمی‌کنیم (دقیقاً طبقِ همون منطقِ jluxe_preload_body_fonts:
 * این‌ها برای متنِ عمومیِ بالای صفحه نیستن)، فقط سایزِ خودِ دانلود رو کم
 * می‌کنیم، هر وقت که واقعاً لازم بشه.
 */
function jluxe_override_heavy_font_weights_as_woff2(): void {
	if ( jluxe_is_dev() ) {
		return;
	}
	$fonts_uri = JLUXE_THEME_URI . '/assets/fonts/';
	$weights   = array(
		700 => array( 'woff2' => 'IRANYekanMobileBold-BBCE2d6Y.woff2', 'ttf' => 'IRANYekanMobileBold-BBCE2d6Y.ttf' ),
		800 => array( 'woff2' => 'IRANYekanMobileExtraBold-CkTerGWQ.woff2', 'ttf' => 'IRANYekanMobileExtraBold-CkTerGWQ.ttf' ),
		900 => array( 'woff2' => 'IRANYekanMobileBlack-CcgaB4Xg.woff2', 'ttf' => 'IRANYekanMobileBlack-CcgaB4Xg.ttf' ),
		950 => array( 'woff2' => 'IRANYekanMobileExtraBlack-DqMfFYeF.woff2', 'ttf' => 'IRANYekanMobileExtraBlack-DqMfFYeF.ttf' ),
	);
	// خودِ فایلِ ttf اصلی هنوز از dist/assets میاد (تغییری نکرده) — فقط
	// به‌عنوانِ fallback نگه داشته می‌شه، دقیقاً هم‌اسمِ همونی که globals.css
	// خودش declare کرده بود.
	$dist_uri = JLUXE_THEME_URI . '/dist/assets/';
	echo "<style>\n";
	foreach ( $weights as $weight => $files ) {
		printf(
			"@font-face{font-family:IRANYekan;src:url(%s) format('woff2'),url(%s) format('truetype');font-weight:%d;font-style:normal;font-display:swap}\n",
			esc_url( $fonts_uri . $files['woff2'] ),
			esc_url( $dist_uri . $files['ttf'] ),
			(int) $weight
		);
	}
	echo "</style>\n";
}
add_action( 'wp_head', 'jluxe_override_heavy_font_weights_as_woff2', 20 );

/**
 * Critical CSS — طبقِ اولویتِ کاربر («Performance واقعی»)، نسخه‌ی امنِ این
 * کار، نه بازنویسیِ کاملِ معماری. یک بازنویسیِ کاملِ CSS به‌ازایِ هر نوع
 * صفحه (چیزی که در ریویوِ اولیه پیشنهاد شده بود: global-critical.css +
 * product.css + shop.css + …) یعنی باید مشخص بشه دقیقاً کدوم کلاس‌های
 * Tailwind روی کدوم تمپلیت لازمن — بدونِ ابزارِ استخراجِ خودکار (مثلاً
 * critical/Puppeteer)، این کار با دست‌وچشم روی این حجم از کلاس‌ها واقعاً
 * پرریسکه: اگه یک کلاس از قلم بیفته، همون عنصر روی همون صفحه بی‌استایل
 * می‌مونه — یک باگِ خاموش که فقط با تستِ تک‌تکِ تمپلیت‌ها پیدا می‌شه.
 *
 * راه‌حلِ امن‌تر (همینه که پیاده شده): به‌جایِ deferکردنِ main.css (که
 * می‌تونست یک فلشِ کاملاً بی‌استایل بسازه، چون Tailwind همه‌جای صفحه‌ست)،
 * فقط همون زیرمجموعه‌ی کوچیک و ۱۰۰٪ مطمئنِ CSSِ لازم برای اولین رنگ‌آمیزیِ
 * صفحه (توکن‌های رنگ، جهتِ RTL، فونتِ پایه‌ی body) مستقیم inline می‌شه —
 * main.css هنوز عادی و بدونِ تأخیر لود می‌شه، این فقط یک لایه‌ی زودتر روش
 * اضافه می‌کنه. صفر ریسکِ «کلاسِ گم‌شده» چون هیچ کلاسِ Tailwindی این‌جا
 * کپی نشده، فقط CSSِ دستی‌نوشته‌ی خودِ globals.css (که ثابته، نه از کلاسِ
 * پویا استخراج‌شده).
 *
 * نکته‌ی مهم: primary/secondary/accent/success/background توسطِ ادمین
 * قابل‌تغییرن (رنگ و تایپوگرافی → رنگ‌ها) — به‌جایِ هاردکدکردنِ مقادیرِ
 * پیش‌فرض (که برای سایتی با رنگِ سفارشی یک فلشِ کوتاهِ رنگِ اشتباه می‌ساخت)،
 * از همون jluxe_generate_color_variables() که خودِ override واقعی هم ازش
 * استفاده می‌کنه صدا زده می‌شه — یعنی این‌جا هم همیشه رنگِ واقعیِ همون
 * سایته، نه پیش‌فرضِ تم.
 */
/**
 * پیش‌بارگذاری تصویر اصلی محصول برای کاهش تأخیر LCP در صفحات محصول.
 */
function jluxe_preload_single_product_lcp_image(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_queried_object_id() ) : false;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$image_id = (int) $product->get_image_id();
	if ( ! $image_id ) {
		return;
	}
	$image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
	$srcset    = wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' );
	if ( ! $image_url ) {
		return;
	}
	$attributes = sprintf( ' href="%s"', esc_url( $image_url ) );
	if ( $srcset ) {
		$attributes .= sprintf( ' imagesrcset="%s"', esc_attr( $srcset ) );
	}
	printf( '<link rel="preload" as="image"%s fetchpriority="high">' . "\n", $attributes );
}
add_action( 'wp_head', 'jluxe_preload_single_product_lcp_image', 1 );

function jluxe_output_critical_css(): void {
	if ( jluxe_is_dev() ) {
		return;
	}
	$manifest = jluxe_vite_manifest();
	echo '<style id="jluxe-critical-css">';
	echo jluxe_generate_color_variables( jluxe_get_theme_settings()['colors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجیِ خودِ این تابع با esc_attr روی مقادیر امنه.
	// توکن‌های ثابت (غیرِقابل‌تنظیم از پنل) — عیناً از globals.css:root کپی شده.
	echo ':root{--foreground:225 6% 13%;--surface:0 0% 100%;--surface-foreground:225 6% 13%;--surface-elevated:270 16% 93%;--text-secondary:240 5% 43%;--text-muted:240 5% 58%;--muted:270 16% 93%;--muted-foreground:240 5% 43%;--border:264 15% 87%;--warning:38 92% 42%;--warning-foreground:225 6% 13%;--error:0 72% 45%;--error-foreground:0 0% 100%;--radius:0.5rem}';
	echo 'html{direction:rtl}body{font-family:"IRANYekan","Tahoma",sans-serif;text-align:right;background-color:hsl(var(--background));color:hsl(var(--foreground))}';
	foreach ( array(
		'400' => 'src/assets/fonts/IRANYekanMobileRegular.woff2',
		'500' => 'src/assets/fonts/IRANYekanMobileMedium.woff2',
	) as $weight => $src_path ) {
		if ( empty( $manifest[ $src_path ]['file'] ) ) {
			continue;
		}
		printf(
			'@font-face{font-family:"IRANYekan";src:url(%s) format("woff2");font-weight:%s;font-style:normal;font-display:swap}',
			esc_url( JLUXE_THEME_URI . '/dist/' . $manifest[ $src_path ]['file'] ),
			esc_attr( $weight )
		);
	}
	echo '</style>' . "\n";
}
add_action( 'wp_head', 'jluxe_output_critical_css', 3 );

/**
 * Enqueue the theme's JS/CSS entry, dev server in dev mode, hashed dist assets in prod.
 */
function jluxe_enqueue_assets() {
	$entry = 'src/main.tsx';

	if ( jluxe_is_dev() ) {
		wp_enqueue_script( 'jluxe-vite-client', JLUXE_VITE_DEV_SERVER . '/@vite/client', array(), null, false );
		wp_enqueue_script( 'jluxe-main', JLUXE_VITE_DEV_SERVER . '/' . $entry, array(), null, true );
		return;
	}

	$manifest = jluxe_vite_manifest();

	if ( empty( $manifest[ $entry ] ) ) {
		return;
	}

	$entry_data = $manifest[ $entry ];

	if ( ! empty( $entry_data['css'] ) ) {
		foreach ( $entry_data['css'] as $i => $css_file ) {
			wp_enqueue_style( 'jluxe-main-' . $i, JLUXE_THEME_URI . '/dist/' . $css_file, array(), null );
		}
	}

	wp_enqueue_script( 'jluxe-main', JLUXE_THEME_URI . '/dist/' . $entry_data['file'], array(), null, true );
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_assets' );

/**
 * اسکلتون/شیمرِ لودینگِ عکس — سراسریِ کاملِ سایت (نه فقط ووکامرس)، چون
 * قرار نیست فقط روی کارتِ محصول باشه؛ هر <img> جایی از سایت (وبلاگ،
 * صفحات ثابت، هرجا) باید تا لود شدنش این افکت رو داشته باشه. برای همین
 * این‌جا (نه inc/woocommerce.php) enqueue می‌شه — بدونِ هیچ شرطی، جدا از
 * ووکامرس فعال بودن یا نوعِ صفحه.
 */
function jluxe_enqueue_img_skeleton(): void {
	$path = JLUXE_THEME_DIR . '/assets/js/img-skeleton.js';
	wp_enqueue_script(
		'jluxe-img-skeleton',
		JLUXE_THEME_URI . '/assets/js/img-skeleton.js',
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		true // فوتر عمداً — حالتِ «شیمر» پیش‌فرضِ خودِ CSS (img:not(.jluxe-img-loaded)) بدونِ نیاز به هیچ کلاسی از JS از همون اول برقراره؛ کارِ این اسکریپت فقط حذفِ شیمر بعدِ لودِ واقعیه، پس نیازی به بلاک‌کردنِ رندرِ صفحه در <head> نیست.
	);
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_img_skeleton' );

/**
 * فاوآیکون — اولویت با آپلودِ ادمین/آیکونِ سایتِ خودِ وردپرس
 * (jluxe_get_favicon_url در inc/theme-settings.php). اگه هیچ‌کدوم ست
 * نشده باشه (نصبِ تازه، هیچ تنظیمی هنوز انجام نشده)، دیگه به‌صورتِ ثابت
 * فاویکونِ برندِ جهیزیه لوکس تحمیل نمی‌شه — چیزی چاپ نمی‌شه، مرورگر
 * خودش fallback پیش‌فرض رو نشون می‌ده.
 */
function jluxe_favicon() {
	$url = function_exists( 'jluxe_get_favicon_url' ) ? jluxe_get_favicon_url() : '';
	if ( ! $url ) {
		return;
	}
	printf(
		'<link rel="icon" href="%1$s" sizes="any"><link rel="shortcut icon" href="%1$s">',
		esc_url( $url )
	);
}
add_action( 'wp_head', 'jluxe_favicon', 1 );

/**
 * Vite's client script and entry module must load as type="module".
 */
function jluxe_module_script_attrs( $tag, $handle ) {
	if ( in_array( $handle, array( 'jluxe-vite-client', 'jluxe-main' ), true ) ) {
		$tag = str_replace( ' src=', ' type="module" src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'jluxe_module_script_attrs', 10, 2 );

/**
 * اخطارِ واقعیِ Lighthouse («Render-blocking requests»، ~۱۶۵۰ میلی‌ثانیه فقط
 * برای jquery+jquery-migrate): این‌ها هنوز به‌صورتِ اسکریپتِ ساده/مسدودکننده
 * در <head> چاپ می‌شن.
 *
 * روشِ اول (جابه‌جاییِ group به فوتر) امتحان و رد شد — با تستِ زنده معلوم
 * شد اثر نداره: ووکامرس خودش wc-jquery-blockui (که به jquery وابسته‌ست) رو
 * با گروهِ ۰ (head) ثبت می‌کنه، و WP_Scripts::all_deps خودش وابستگیِ jquery
 * رو دوباره به گروهِ ۰ برمی‌گردونه تا وابستگیِ یک اسکریپتِ head همیشه زودتر
 * از خودش لود بشه — نتیجه: jquery همچنان مسدودکننده می‌موند.
 *
 * راه‌حلِ درست (تست‌شده): به‌جای جابه‌جاییِ group (موقعیتِ چاپ)، خودِ
 * strategy را defer می‌کنیم — یک ویژگیِ رسمیِ خودِ وردپرس از نسخه‌ی ۶.۳
 * («Script Loading Strategies») که مستقل از head/footer عمل می‌کنه: صفتِ
 * HTML «defer» به تگِ <script> اضافه می‌شه، پس مرورگر بدونِ مسدودکردنِ
 * رندر دانلودش می‌کنه و بعدِ پارس‌شدنِ کاملِ HTML (ولی هنوز به‌ترتیبِ درست)
 * اجراش می‌کنه — دقیقاً همون چیزی که خودِ ووکامرس برای اسکریپت‌های خودش
 * (wc-jquery-blockui و…) از قبل استفاده می‌کنه؛ این‌جا فقط همون رفتار رو
 * برای jquery/jquery-migrate/underscore/wp-util هم فعال می‌کنیم. با
 * زنده‌تست تأیید شد: افزودن‌به‌سبدِ AJAX، تغییرِ تنوع/متغیر، و مینی‌کارت
 * بدونِ مشکل کار می‌کنن (چون defer ترتیبِ اجرا رو حفظ می‌کنه، فقط دیرتر).
 */
function jluxe_defer_core_scripts_to_footer( $scripts ) {
	if ( is_admin() || empty( $scripts->registered ) ) {
		return;
	}
	foreach ( array( 'jquery-core', 'jquery-migrate', 'underscore', 'wp-util' ) as $handle ) {
		if ( isset( $scripts->registered[ $handle ] ) ) {
			$scripts->add_data( $handle, 'strategy', 'defer' );
		}
	}
}
add_action( 'wp_default_scripts', 'jluxe_defer_core_scripts_to_footer' );

/**
 * اخطارِ واقعیِ Lighthouse («Render-blocking requests»، ~۱۷۷۰ میلی‌ثانیه):
 * چهار استایلِ خودِ ووکامرس (general/layout/smallscreen + wc-blocks) هنوز
 * به‌صورتِ <link rel="stylesheet"> معمولی و مسدودکننده لود می‌شن. برخلافِ
 * jquery (که فیلترِ strategy داشت)، خودِ ووکامرس برای CSS همچین فیلتری
 * نداره؛ برای همین این‌جا با فیلترِ رسمیِ style_loader_tag، فقط همین ۴
 * handleِ مشخص رو به تکنیکِ استانداردِ «media-swap» (همون کاری که پلاگین‌های
 * معروفِ بهینه‌سازی مثلِ Autoptimize/WP Rocket انجام می‌دن) تبدیل می‌کنیم:
 * media="print" باعث می‌شه مرورگر رندر رو منتظرش نذاره ولی بازم دانلودش
 * کنه، و onload خودش media رو به all برمی‌گردونه تا استایل کامل اعمال بشه —
 * محتوا/رفتار/ظاهرِ نهایی هیچ تغییری نمی‌کنه، فقط لحظه‌ی اعمال‌شدنش کمی
 * دیرتره؛ به‌جای remove/dequeue کامل (که ریسکِ شکستنِ چیزهایی مثلِ فونتِ
 * ستاره‌ی امتیاز یا overlayِ blockUI رو داره)، چیزی حذف نمی‌شه. تگِ
 * <noscript> هم fallback برای حالتِ بدونِ JS رو تضمین می‌کنه.
 */
function jluxe_defer_woocommerce_styles( $tag, $handle ) {
	/*
	 * باگِ واقعیِ دوم (بعدِ آپلود، با اندازه‌گیریِ واقعیِ network بعدِ فونت‌ساب‌ست
	 * پیدا شد): چون سایت RTLه، خودِ ووکامرس این چهار استایل رو با هندلِ
	 * پسوند-دار «-rtl» رجیستر/enqueue می‌کنه (woocommerce-general-rtl،
	 * woocommerce-layout-rtl، woocommerce-smallscreen-rtl، wc-blocks-style-rtl)
	 * نه هندلِ ساده — یعنی این فیلتر از همون اول (حتی قبل از باگِ اولِ media)
	 * هیچ‌وقت واقعاً اجرا نشده بود، چون in_array هیچ‌کدوم از این چهار هندلِ
	 * واقعی رو پیدا نمی‌کرد. الان هم نسخه‌ی ساده و هم نسخه‌ی -rtl چک می‌شن —
	 * برای سایت‌های LTR هم بی‌ضرره.
	 */
	$deferred_handles = array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'wc-blocks-style',
		'woocommerce-general-rtl',
		'woocommerce-layout-rtl',
		'woocommerce-smallscreen-rtl',
		'wc-blocks-style-rtl',
	);
	if ( ! in_array( $handle, $deferred_handles, true ) ) {
		return $tag;
	}
	/*
	 * باگِ واقعیِ نسخه‌ی اول (قبل از هر آپلودی پیدا و رفع شد): این‌جا فرض
	 * شده بود media همیشه 'all'ه، ولی woocommerce-smallscreen خودش از قبل
	 * media='only screen and (max-width: 768px)' داره — یعنی دقیقاً همون
	 * حالتی که موبایل (تنها هدفِ واقعیِ این استایل) رو تحتِ تأثیر می‌ذاره.
	 * regexِ قبلی این مقدار رو پیدا نمی‌کرد و یک attributeِ media تکراری
	 * اضافه می‌کرد (HTML نامعتبر، و onload هیچ‌وقت اجرا نمی‌شد). الان مقدارِ
	 * واقعیِ media هرچی که باشه استخراج و به‌جای هاردکد 'all'، همون توی
	 * onload برگردونده می‌شه.
	 */
	if ( ! preg_match( "/media=(['\"])([^'\"]*)\\1/", $tag, $m ) ) {
		return $tag;
	}
	$original_media = esc_attr( $m[2] );
	$deferred_tag   = preg_replace( "/media=(['\"])[^'\"]*\\1/", 'media="print" onload="this.media=\'' . $original_media . '\'"', $tag, 1 );
	return $deferred_tag . '<noscript>' . $tag . '</noscript>';
}
add_filter( 'style_loader_tag', 'jluxe_defer_woocommerce_styles', 10, 2 );

/**
 * اخطارِ واقعیِ Lighthouse («Render-blocking requests») — نسخه‌ی جدیدترِ
 * گزارش: jQuery و استایل‌های ووکامرس (بالاتر) از قبل درست‌ان، ولی چند
 * اسکریپتِ کوچیکِ دیگه با اسمِ هش‌مانند (…js/4260948….js?ver=b3216 و…)
 * هنوز مسدودکننده‌ان. این الگو (نامِ فایلِ هش‌شده + ?ver=هشِ کوتاه) دقیقاً
 * شبیهِ خروجیِ افزونه‌ی Code Snippets (هر قطعه‌کد به‌عنوانِ یک فایلِ جدا با
 * هشِ محتوا به‌عنوانِ نسخه) است — یعنی این‌ها بخشی از این پوسته نیستن، پس
 * نمی‌تونیم مستقیم فایل‌هاشون رو ویرایش کنیم؛ فقط می‌تونیم نحوه‌ی
 * لودشدنشون رو (مثلِ همون کاری که برای jQuery/ووکامرس بالاتر کردیم)
 * defer کنیم.
 *
 * چون handle این‌ها (برخلافِ jquery-core/ووکامرس) از قبل مشخص نیست و
 * می‌تونه هر بار عوض بشه، به‌جای فهرستِ ثابتِ handle، هر اسکریپتی که نه از
 * wp-includes (هسته) و نه از خودِ این پوسته (JLUXE_THEME_URI) باشه رو
 * defer می‌کنیم — یعنی دقیقاً «اسکریپت‌های افزونه‌های دیگه».
 *
 * عمداً پشتِ یک تنظیمِ خاموش-به‌طورِ-پیش‌فرض (performance.defer_third_party_scripts)
 * گذاشته شده، نه همیشه فعال — چون برخلافِ jQuery/ووکامرس که از قبل زنده‌
 * تست شدن، این‌جا نمی‌دونیم کدوم قطعه‌کد ممکنه فرضِ اجرای فوری/هم‌زمان
 * داشته باشه؛ ادمین خودش از پیشخوان ← تنظیماتِ پوسته ← عملکرد فعالش
 * می‌کنه و اگه چیزی به‌هم ریخت، از همون‌جا خاموشش می‌کنه.
 */
function jluxe_defer_third_party_scripts( $tag, $handle, $src ) {
	if ( is_admin() || ! $src ) {
		return $tag;
	}
	$perf = jluxe_get_theme_settings()['performance'] ?? array();
	if ( empty( $perf['defer_third_party_scripts'] ) ) {
		return $tag;
	}
	// از قبل defer/async/module هست — کاری نداریم.
	if ( preg_match( '/\s(defer|async)(=|\s|>)/', $tag ) || false !== strpos( $tag, 'type="module"' ) ) {
		return $tag;
	}
	// هسته‌ی خودِ وردپرس (jquery و…) از قبل با روشِ رسمیِ strategy مدیریت می‌شه.
	if ( false !== strpos( $src, '/wp-includes/' ) ) {
		return $tag;
	}
	// اسکریپت‌های خودِ این پوسته دست‌نخورده می‌مونن — چون یا از قبل type="module"ان
	// (jluxe_module_script_attrs)، یا (مثلِ homepage.js/img-skeleton.js) به ترتیبِ
	// اجرای هم‌زمانِ فعلی نیاز دارن.
	if ( false !== strpos( $src, get_template_directory_uri() ) ) {
		return $tag;
	}
	return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'jluxe_defer_third_party_scripts', 10, 3 );

require_once JLUXE_THEME_DIR . '/inc/qa.php';
require_once JLUXE_THEME_DIR . '/inc/woocommerce.php';
require_once JLUXE_THEME_DIR . '/inc/product-faq.php';
require_once JLUXE_THEME_DIR . '/inc/ai-tickets.php';
require_once JLUXE_THEME_DIR . '/inc/attribute-swatches.php';
require_once JLUXE_THEME_DIR . '/inc/theme-settings.php';
require_once JLUXE_THEME_DIR . '/inc/order-tracking.php';
require_once JLUXE_THEME_DIR . '/inc/required-pages.php';
/*
 * افزونه‌ی «ویرایشگر/بهینه‌سازِ تصویر» (inc/image-optimizer.php + inc/image-optimizer/)
 * طبقِ درخواستِ صریحِ کاربر کاملاً از پوسته حذف شد — داشت تصاویرِ محصولات
 * رو بدونِ اینکه محلِ تنظیماتش مشخص/قابل‌کنترل باشه کراپ می‌کرد. اگه این
 * روی سایتِ زنده جداگانه هم به‌عنوانِ یک پلاگینِ واقعی (نه بخشی از این
 * پوسته) نصب/فعال شده باشه، باید از پیشخوان ← افزونه‌ها هم غیرفعال/حذف
 * بشه — این حذف فقط نسخه‌ی داخلِ خودِ پوسته رو برمی‌داره.
 */
/*
 * افزونه‌ی «پیامکِ رونق» (inc/sms.php + inc/sms/ — ملی‌پیامک/کاوه‌نگار +
 * ای‌بازار پست، سبد رها‌شده، اتوماسیون، کیف‌پول و...) طبقِ درخواستِ صریحِ
 * کاربر کاملاً از پوسته حذف شد تا به‌جاش پلاگینِ مستقلِ «رونق» رو خودش نصب
 * کنه — قبلاً هم‌زمان همون کدِ ادغام‌شده در تم + پلاگینِ جداگانه («ای‌بازار»
 * و «ارسالِ کد رهگیریِ رونق») هر دو در ووکامرس دیده می‌شدن، یعنی تداخل/
 * تکرار واقعی بود. هیچ فایلِ دیگه‌ای از تم به کلاس‌های JSMS_* یا jsms_get()
 * وابسته نبود (بررسی‌شده) — inc/order-tracking.php فقط کلیدهای متای
 * _jsms_tracking/_jsms_courier رو (که همچنان توسطِ پلاگینِ مستقلِ رونق نوشته
 * می‌شن، چون کدش همونه) به‌عنوانِ fallback می‌خونه، پس صفحه‌ی پیگیریِ سفارش
 * بدونِ تغییر کار می‌کنه. سیستمِ «ورود با پیامک (OTP)» (inc/theme-settings-sms.php)
 * کاملاً جداست و دست‌نخورده موند.
 */
require_once JLUXE_THEME_DIR . '/inc/search.php';
require_once JLUXE_THEME_DIR . '/inc/cart-ux.php';

/**
 * تضمینِ اینکه صفحه‌ی «ورود» همیشه همون UI اختصاصیِ خودمون
 * (page-my-account.php → جزیره‌ی auth-page، وصل به سیستمِ پیامکی) باشه،
 * نه فرمِ پیش‌فرضِ ووکامرس.
 *
 * باگِ واقعیِ گزارش‌شده («لاگینِ اختصاصیِ ما گم شده، لاگینِ ووکامرس رو
 * می‌بینم»): page-my-account.php فقط وقتی اجرا می‌شه که اسلاگِ صفحه‌ی
 * وردپرس دقیقاً «my-account» باشه (تطبیقِ استانداردِ خودِ وردپرس بر اساسِ
 * اسلاگ). اگه صفحه‌ی «حساب کاربری» ووکامرس (پیشخوان ← ووکامرس ← تنظیمات
 * ← پیشرفته ← صفحات) هر زمانی روی یه صفحه‌ی دیگه (با اسلاگِ متفاوت) ست
 * شده باشه — که خیلی راحت با یه ویزاردِ نصبِ افزونه یا تغییرِ دستی پیش
 * میاد — page-my-account.php دیگه هیچ‌وقت اجرا نمی‌شه، و به‌جاش همون
 * فرم/تمپلیتِ پیش‌فرضِ ووکامرس (woocommerce/myaccount/my-account.php)
 * نشون داده می‌شه، که دقیقاً همون چیزیه که گزارش شد.
 *
 * راه‌حل: به‌جای تکیه‌کردن به اسلاگ، از is_account_page() خودِ ووکامرس
 * استفاده می‌کنیم — این تابع همیشه، صرف‌نظر از اسلاگ/شناسه‌ی واقعیِ
 * صفحه، درست تشخیص می‌ده که «این همون صفحه‌ی حساب کاربریِ واقعیه یا نه».
 */
function jluxe_force_my_account_template( string $template ): string {
	if ( function_exists( 'is_account_page' ) && is_account_page() && ! is_admin() ) {
		$custom = JLUXE_THEME_DIR . '/page-my-account.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', 'jluxe_force_my_account_template', 99 );
