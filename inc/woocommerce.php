<?php
/**
 * جفتِ ووکامرسِ سبد/تسویه‌حساب واقعی. طبق تصمیم صریح: از قالب‌های کلاسیک
 * ووکامرس (نه Store API/Blocks headless) استفاده می‌شه — چون سایت مرجع
 * (algetshop.ir/checkout) هم یک چک‌اوت تک‌صفحه‌ای کلاسیکه، و بازسازیِ کامل
 * اعتبارسنجی/درگاه/session با یک لایه‌ی React جدا ریسک باگ‌هایی از جنس
 * «سبد خالی است» رو بیشتر می‌کنه، نه کمتر. استپر بالای صفحه صرفاً یک
 * نشانگر پیشرفتِ بصریه (نه state machine)، چون خودِ چک‌اوت ووکامرس یک
 * صفحه‌ست نه یک ویزارد چندمرحله‌ای.
 */

defined( 'ABSPATH' ) || exit;

/** false asks wc-add-to-cart-variation to resolve a selection over AJAX. */
function jluxe_available_variations_for_form( $product ) {
	$threshold = max( 0, (int) apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product ) );
	return count( $product->get_children() ) <= $threshold ? $product->get_available_variations() : false;
}


/**
 * شمارشگرِ ترتیبِ رندرِ کارتِ محصول در طولِ یک درخواست — برای تشخیصِ اینکه
 * کدوم عکس‌ها واقعاً بالای صفحه‌ن (باید eager/fetchpriority=high باشن) و
 * کدوم‌ها پایین‌ترن (lazy). عمداً یک تابعِ واقعیه، نه یک static در سطحِ
 * بالای خودِ woocommerce/content-product.php — چون wc_get_template_part()
 * اون فایل رو برای هر محصول با include (نه include_once) جدا include
 * می‌کنه، و static در سطحِ فایل (نه داخلِ تابع) بینِ include-های جدا پایدار
 * نمی‌مونه (هر بار از نو صفر می‌شه). static داخلِ یک تابعِ واقعی، برخلافش،
 * بینِ همه‌ی فراخوانی‌های همون تابع در طولِ کلِ درخواست درست پایدار می‌مونه.
 */
function jluxe_next_product_card_index(): int {
	static $i = 0;
	++$i;
	return $i;
}

/** Keep product reviews moderated; never overwrite WooCommerce's enable/disable settings. */
// Review enablement and per-product comment status belong to the store administrator.
add_filter(
	'pre_comment_approved',
	function ( $approved, $commentdata ) {
		if ( 1 === $approved && ! empty( $commentdata['comment_post_ID'] ) && 'product' === get_post_type( $commentdata['comment_post_ID'] ) ) {
			return 0; // منتظرِ تاییدِ مدیر — طبقِ درخواستِ صریحِ کاربر.
		}
		return $approved;
	},
	20,
	2
);

/**
 * آیکونِ SVG تومان (jluxe_toman_icon_svg پایین‌تر، جایگزینِ نمادِ پول) وقتی
 * از داخلِ wc_price() میاد معمولاً با wp_kses_post() سالم‌سازی می‌شه —
 * فهرستِ مجازِ پیش‌فرضِ wp_kses_post شاملِ svg/path نیست، پس بدونِ این فیلتر
 * آیکون حذف می‌شد و فقط جای خالی می‌موند (باگِ واقعی، با تستِ زنده پیدا شد:
 * <span class="woocommerce-Price-currencySymbol"></span> خالی). فقط
 * تگ‌های ساختاریِ SVG (بدون script/event handler) اضافه می‌شن.
 */
function jluxe_allow_svg_in_kses_post( array $tags, string $context ): array {
	if ( 'post' !== $context ) {
		return $tags;
	}
	$tags['svg']  = array(
		'xmlns'       => true,
		'width'       => true,
		'height'      => true,
		'viewbox'     => true,
		'fill'        => true,
		'aria-hidden' => true,
		'style'       => true,
		'class'       => true,
	);
	$tags['path'] = array(
		'd'    => true,
		'fill' => true,
	);
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'jluxe_allow_svg_in_kses_post', 10, 2 );

/** Amounts and filters always use WooCommerce's configured storage currency, with no hidden x10. */
function jluxe_currency_label( string $currency ): string {
	if ( 'IRR' === $currency ) { return 'ریال'; }
	if ( 'IRT' === $currency ) { return 'تومان'; }
	return html_entity_decode( wp_strip_all_tags( get_woocommerce_currency_symbol( $currency ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * تبدیل ارقام لاتین به فارسی — برای جاهایی که خودِ ووکامرس/افزونه‌ی فارسی
 * فیلتر نمی‌کنن (مثل تعداد آیتم سبد که ما مستقیم در قالب چاپ می‌کنیم).
 * افزونه‌ی «ووکامرس فارسی» همین تبدیل رو روی wc_price و قیمت‌ها با
 * تنظیمات خودش (persian_price) انجام می‌ده؛ این فقط مکمل همون رفتاره.
 */
function jluxe_fa_digits( $value ): string {
	return strtr(
		(string) $value,
		array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' )
	);
}

/**
 * باگِ واقعیِ گزارش‌شده («همه‌ی قیمت‌ها یک صفر کم دارن — ۳,۸۸۰,۰۰۰ شده
 * ۳۸۸,۰۰۰») — دقیقاً همون سناریویی که کامنتِ قدیمیِ همین تابع از قبل
 * پیش‌بینی کرده بود: افزونه‌ی «ووکامرس فارسی» (persian-wc) روی این سایت
 * فعاله و خودش قیمت‌ها رو به تومان تبدیل/نمایش می‌ده؛ این تابع هم روی
 * همون خروجیِ ازقبل‌تبدیل‌شده دوباره تقسیم بر ۱۰ می‌کرد (تقسیمِ تکراری).
 * با تستِ زنده تأیید شد: قیمتِ واقعیِ ثبت‌شده در ادمین (مثلاً
 * ۴,۹۵۰,۰۰۰ تومان) دقیقاً ۱۰ برابرِ چیزی بود که مشتری می‌دید. طبقِ
 * توصیه‌ی خودِ این کامنت («این فیلتر باید غیرفعال بشه») حالا کاملاً
 * غیرفعاله — persian-wc به‌تنهایی مسئولِ نمایشِ درستِ تومانه.
 */
function jluxe_toman_price( $price ) {
	if ( '' === $price || null === $price ) {
		return $price;
	}
	return ( (float) $price ) / 10;
}
// add_filter( 'raw_woocommerce_price', 'jluxe_toman_price' ); // عمداً غیرفعال — بالا رو بخون.

/**
 * آیکونِ SVG تومان — به‌جای کلمه‌ی «تومان» به‌عنوانِ «نماد پول» ووکامرس
 * استفاده می‌شه (jluxe_toman_symbol پایین). عمداً بدونِ <defs>/clip-path
 * جدا شده (نسخه‌ی اصلیِ کاربر یک id داشت که چون این SVG ده‌ها بار در یک
 * صفحه تکرار می‌شه — هر جا قیمتی هست — id تکراری در HTML نامعتبر می‌شه)،
 * فقط مسیرهای واقعی نگه داشته شدن. رنگ عمداً currentColor (نه هگزِ ثابتِ
 * نسخه‌ی اصلی #8f9bad) تا خودکار با رنگِ متنِ اطرافش یکی بشه — چه قیمتِ
 * اصلیِ خط‌خورده (رنگِ کم‌رنگ) چه قیمتِ نهاییِ تخفیف‌خورده (رنگِ اصلیِ متن)،
 * بدونِ نیاز به override جدا برای هر context. اندازه با em (نه px ثابت)
 * تا با فونت‌سایزِ قیمت در هر جای سایت (کارتِ محصول/جعبه‌قیمت/سبد/...)
 * هم‌مقیاس بمونه — طبقِ درخواستِ صریحِ کاربر «متناسب با ابعاد قیمت... کمی کوچکتر».
 */
function jluxe_toman_icon_svg(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="0.85em" height="0.7em" viewBox="0 0 22 18" fill="none" aria-hidden="true" style="display:inline-block;vertical-align:-0.05em;margin-inline-end:0.2em">'
		. '<path d="M16.8984 0.750259H14.5224C14.1425 0.750259 13.8346 1.05819 13.8346 1.43805C13.8346 1.8179 14.1425 2.12583 14.5224 2.12583H16.8984C17.2782 2.12583 17.5862 1.8179 17.5862 1.43805C17.5862 1.05819 17.2782 0.750259 16.8984 0.750259Z" fill="currentColor"/>'
		. '<path d="M21.2474 3.81424C21.2265 3.43908 21.164 2.94669 21.0598 2.33706C20.999 1.98275 20.9365 1.65014 20.8722 1.33925C20.8002 0.991882 20.4528 0.776514 20.1106 0.866829C19.7945 0.950197 19.5983 1.26456 19.6625 1.58414C19.7268 1.90372 19.7876 2.2233 19.8484 2.56372C19.9474 3.11603 20.0073 3.54329 20.0281 3.8455C20.049 4.22066 19.9369 4.51245 19.6921 4.72087C19.4472 4.92929 19.0225 5.0335 18.4181 5.0335H6.67273V3.47035C6.67273 2.8034 6.55289 2.21201 6.31321 1.69617C6.07353 1.18033 5.72963 0.776514 5.28153 0.484725C4.83343 0.192937 4.31237 0.0470428 3.71838 0.0470428C3.15564 0.0470428 2.65283 0.198148 2.20993 0.500357C1.76704 0.802566 1.42315 1.2142 1.17825 1.73525C0.93336 2.2563 0.810913 2.83466 0.810913 3.47035C0.810913 4.40824 1.07925 5.13771 1.61594 5.65876C2.15262 6.17981 2.85864 6.44034 3.73401 6.44034H5.42221V6.53412C5.42221 6.78423 5.32321 6.98223 5.12521 7.12812C4.92721 7.27402 4.63543 7.39907 4.24985 7.50328C3.86427 7.60749 3.21817 7.75338 2.31154 7.94096L2.2907 7.9453C1.91641 8.01999 1.67673 8.3882 1.76009 8.76075C1.84086 9.12201 2.19517 9.35214 2.5573 9.28006C2.70493 9.25054 2.8517 9.22101 2.99933 9.19148C3.9789 8.99348 4.7214 8.79548 5.22682 8.59749C5.73224 8.39949 6.09958 8.14157 6.32884 7.82373C6.5581 7.50588 6.67273 7.07602 6.67273 6.53412V6.44034H18.4181C19.0538 6.44034 19.5878 6.31528 20.0203 6.06518C20.4528 5.81507 20.7706 5.48942 20.9738 5.08821C21.177 4.687 21.2682 4.26234 21.2474 3.81424ZM5.45347 5.0335H3.73401C3.14001 5.0335 2.70754 4.91626 2.43659 4.68179C2.16565 4.44732 2.03017 4.0435 2.03017 3.47035C2.03017 2.85551 2.17867 2.36311 2.47567 1.99317C2.77267 1.62322 3.1869 1.43825 3.71838 1.43825C4.29153 1.43825 4.724 1.61801 5.01579 1.97754C5.30758 2.33706 5.45347 2.83466 5.45347 3.47035V5.0335Z" fill="currentColor"/>'
		. '<path d="M6.23507 12.8413C6.23507 12.4097 5.88515 12.0597 5.4535 12.0597C5.02184 12.0597 4.67192 12.4097 4.67192 12.8413C4.67192 13.273 5.02184 13.6229 5.4535 13.6229C5.88515 13.6229 6.23507 13.273 6.23507 12.8413Z" fill="currentColor"/>'
		. '<path d="M20.7724 12.3489C20.5432 11.8123 20.2201 11.3859 19.8033 11.0672C19.3864 10.7493 18.9071 10.5904 18.3652 10.5904C17.6878 10.5904 17.1094 10.8231 16.6301 11.286C16.1507 11.7497 15.7964 12.388 15.5671 13.2009L15.0669 14.9985C15.0148 15.2173 14.9132 15.3815 14.7621 15.4909C14.611 15.6003 14.4104 15.655 14.1603 15.655C13.6913 15.655 13.3553 15.6064 13.152 15.5065C12.9488 15.4075 12.8134 15.233 12.7456 14.9829C12.6779 14.7328 12.644 14.3263 12.644 13.7636L12.6284 9.74629C12.6284 9.3503 12.5607 9.00206 12.4252 8.69898C12.2897 8.39677 12.0761 8.15969 11.7843 7.98775C11.4925 7.8158 11.1226 7.72983 10.6744 7.72983H10.1586C9.70008 7.72983 9.32232 7.8158 9.02532 7.98775C8.72832 8.15969 8.51469 8.39417 8.38443 8.69117C8.25417 8.98817 8.18904 9.33987 8.18904 9.74629L8.20467 14.1075C8.20467 14.7119 8.1213 15.1887 7.95456 15.5378C7.78783 15.8877 7.50646 16.1422 7.11046 16.3037C6.71446 16.4661 6.16215 16.546 5.45352 16.546H5.226C4.57989 16.546 4.04842 16.4114 3.63158 16.1396C3.21474 15.8686 2.90732 15.497 2.70932 15.0219C2.51132 14.5478 2.41232 14.0041 2.41232 13.3884C2.41232 13.1705 2.44619 12.8743 2.48787 12.593C2.54953 12.1744 2.18306 11.8192 1.76622 11.8922C1.49874 11.939 1.29467 12.1544 1.25819 12.4236C1.21477 12.7406 1.19306 13.0619 1.19306 13.3884C1.19306 14.1804 1.34677 14.9264 1.65419 15.6237C1.96161 16.322 2.41753 16.8847 3.02195 17.312C3.62637 17.7401 4.36105 17.9528 5.226 17.9528H5.45352C6.38099 17.9528 7.13912 17.7965 7.72791 17.4839C8.31669 17.1713 8.74917 16.7284 9.02532 16.1552C9.30148 15.5829 9.43435 14.8995 9.42393 14.1075L9.4083 9.74629C9.4083 9.50661 9.4578 9.34595 9.5568 9.26172C9.6558 9.17835 9.8564 9.13666 10.1586 9.13666H10.6744C10.9558 9.13666 11.1486 9.18356 11.2528 9.27735C11.357 9.37114 11.4091 9.52745 11.4091 9.74629L11.4248 13.7636C11.4248 14.4731 11.5055 15.0671 11.6671 15.5456C11.8286 16.025 12.1073 16.3975 12.5033 16.6632C12.8993 16.929 13.4517 17.0618 14.1603 17.0618C14.4625 17.0618 14.7543 16.9941 15.0356 16.8586C15.317 16.724 15.5619 16.5365 15.7703 16.2959L15.8329 16.3272C16.6457 16.744 17.2501 17.0288 17.6461 17.1791C18.0421 17.3302 18.4381 17.4057 18.8341 17.4057C19.2301 17.4057 19.587 17.2946 19.9361 17.0697C20.2852 16.8456 20.5692 16.4861 20.788 15.9911C21.0069 15.497 21.1163 14.8639 21.1163 14.0919C21.1163 13.4666 21.0017 12.8865 20.7724 12.3489ZM19.6313 15.6003C19.4542 15.866 19.1884 15.9989 18.8341 15.9989C18.5632 15.9989 18.2766 15.939 17.9744 15.8191C17.6722 15.7002 17.1667 15.4622 16.4581 15.1079L16.3487 15.0454L16.7551 13.576C16.901 13.0445 17.112 12.6485 17.3882 12.388C17.6643 12.1284 17.99 11.9972 18.3652 11.9972C18.8654 11.9972 19.2457 12.1831 19.5063 12.5522C19.7668 12.9221 19.897 13.4353 19.897 14.0919C19.897 14.8326 19.8085 15.3346 19.6313 15.6003Z" fill="currentColor"/>'
		. '</svg>';
}

/**
 * باگِ واقعیِ گزارش‌شده (پیدا شده با تستِ زنده): توی نتایجِ جستجوی زنده
 * (و هر جای دیگه‌ای که این فیلتر رد نمی‌شد) به‌جای آیکونِ SVG تومان،
 * کلمه‌ی متنیِ «تومان» نشون داده می‌شد و جای عدد/واحد هم برعکس بود. علتِ
 * ریشه‌ای: واحد پولیِ واقعیِ این سایت در تنظیماتِ ووکامرس کدِ سفارشیِ
 * «IRT» است (نه «IRR»ی که این فیلتر قبلاً فقط بهش گوش می‌داد) — پس این
 * شرط هیچ‌وقت true نمی‌شد و به‌جای آیکونِ خنثی (بدونِ جهتِ ذاتی)، همون
 * متنِ فارسیِ خامِ نمادِ پول (که چون یک اسکریپتِ قویاً راست‌به‌چپه، حتی با
 * قانونِ direction:ltr روی .woocommerce-Price-amount هم توسطِ الگوریتمِ
 * bidi مرورگر جابه‌جا می‌شد) به‌عنوانِ fallback برمی‌گشت. الان هر دو کدِ
 * ممکن (IRR برای Rial، IRT برای این سایت) پوشش داده می‌شن.
 */
function jluxe_toman_symbol( string $symbol, string $currency ): string {
	/*
	 * باگِ واقعیِ گزارش‌شده: توی ویرایشِ محصول، اکشنِ گروهیِ «افزودنِ قیمت به
	 * تمامِ متغیرهای بدونِ قیمت» یک prompt/برچسبِ متنی‌محضِ خودِ ووکامرسه
	 * (نه HTML) که نماد پول رو مستقیم داخلِ خودش می‌ذاره — چون این فیلتر
	 * بدونِ قید همه‌جا (حتی صفحه‌ی خودِ پیشخوان) SVG برمی‌گردوند، به‌جایِ
	 * نمادِ ساده، کدِ خامِ SVG به‌صورتِ متن پلاین توی اون پیام نشون داده
	 * می‌شد. راه‌حل: دقیقاً همون قیدِ jluxe_fa_digits_wc_price پایین‌تر —
	 * is_admin() به‌تنهایی کافی نیست (admin-ajax.php هم is_admin()=true
	 * حساب می‌شه، حتی برایِ AJAXِ فرانت‌اندِ خودِ همین تم مثلِ سبدِ کشویی)،
	 * پس فقط رندرِ واقعیِ صفحه‌ی پیشخورد (نه AJAX) رد می‌شه.
	 */
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $symbol;
	}
	if ( 'IRT' === $currency ) {
		return jluxe_toman_icon_svg();
	}
	return $symbol;
}
add_filter( 'woocommerce_currency_symbol', 'jluxe_toman_symbol', 10, 2 );

/**
 * باگِ واقعیِ گزارش‌شده (با تستِ زنده پیدا شد): روی محصولِ متغیری که همه‌ی
 * تنوع‌هاش دقیقاً یک قیمت دارن (مثلاً یک انگشتر که سایزهای مختلفش قیمتِ
 * یکسان دارن)، با کلیک روی سواچ قیمت/تعداد/دکمه‌ی خرید اصلاً نمایان
 * نمی‌شدن. علتِ ریشه‌ای: خودِ ووکامرس (class-wc-product-variable.php:
 * get_available_variation) وقتی همه‌ی تنوع‌ها قیمتِ یکسان دارن،
 * price_html رو عمداً خالی می‌فرسته (فرضش اینه که چون بازه‌ی
 * min-max=قیمتِ ثابته، دیگه نیازی به نمایشِ جداگانه نیست) — ولی طبقِ
 * طراحیِ تأییدشده‌ی خودِ ما (price.php)، قیمت اصلاً تا وقتی found_variation
 * واقعی اتفاق نیفته نشون داده نمی‌شه، پس این pricing خالی باعث می‌شد
 * assets/js/woocommerce.js چیزی برای نمایش نداشته باشه و کلِ جعبه‌ی
 * خرید (قیمت+تعداد+دکمه) مخفی بمونه. فیلترِ رسمیِ خودِ ووکامرس
 * (woocommerce_show_variation_price) دقیقاً برای همین سناریو ساخته شده؛
 * همیشه true برگردوندنش باعث می‌شه price_html همیشه پر باشه، بدونِ
 * دست‌زدن به هیچ منطقِ محاسبه/فرمتِ دیگه‌ای.
 */
add_filter( 'woocommerce_show_variation_price', '__return_true' );

/**
 * دیتای ساختاریافته‌ی Product/WebSite (JSON-LD) — طبقِ درخواستِ صریحِ کاربر
 * («سئوی گوگل و هوش مصنوعی»). خودِ ووکامرس این‌ها رو خودکار می‌سازه، ولی
 * WC_Structured_Data::generate_product_data روی هوکِ
 * woocommerce_single_product_summary قلاب شده و generate_website_data روی
 * woocommerce_before_main_content — این تم چون طراحیِ سه‌ستونه‌ی خودش رو
 * برای صفحه‌ی محصول داره، دیگه این اکشن‌ها رو صدا نمی‌زنه (ببین کامنتِ
 * woocommerce/content-single-product.php: «دیگه استفاده نمی‌شن») — نتیجه:
 * تا الان هیچ Product JSON-LDای (قیمت/موجودی/امتیاز، پیش‌نیازِ Rich
 * Results گوگل برای محصولات) توی صفحه‌ی محصول چاپ نمی‌شد؛ با بررسیِ زنده‌ی
 * خروجیِ HTML (صفر تگِ application/ld+json) تأیید شد. راه‌حل: مستقیم و
 * بدون وابستگی به اون اکشن‌های حذف‌شده صداشون می‌زنیم — output_structured_data
 * (روی wp_footer، دست‌نخورده) هنوز خودش چاپش می‌کنه.
 */
function jluxe_emit_structured_data(): void {
	if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->structured_data ) {
		return;
	}
	/*
	 * طبقِ درخواستِ کاربر («باید کاملاً با Rank Math Pro هماهنگ باشیم»):
	 * چون این تابع generate_product_data رو مستقیم صدا می‌زنه (نه از طریقِ
	 * اکشنِ عادیِ woocommerce_single_product_summary)، اگه پلاگینِ سئوی
	 * دیگه‌ای (Rank Math/Yoast) هم برای همین محصول Product Schema بسازه،
	 * حذف‌کردنِ اون اکشنِ عادی توسطِ اون پلاگین (که معمولاً همینه) هیچ اثری
	 * روی این فراخوانیِ مستقیم نداره — نتیجه‌ش دو تا JSON-LD با
	 * "@type":"Product" همزمان توی <head>، یعنی دقیقاً همون چیزی که Rich
	 * Results گوگل رو گیج می‌کنه. پس اگه یکی از این پلاگین‌ها فعاله، خروجیِ
	 * Product/Website رو به خودش واگذار می‌کنیم؛ RANK_MATH_VERSION هم
	 * نسخه‌ی رایگان هم Pro رو پوشش می‌ده (هر دو همین ثابت رو ست می‌کنن).
	 */
	if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) {
		return;
	}
	/*
	 * باگِ واقعیِ پیدا‌شده در همین تست: generate_product_data() بدونِ آرگومان
	 * از globalِ $product می‌خونه، ولی اون global توسطِ خودِ ووکامرس فقط
	 * ضمنِ the_post() (یعنی داخلِ Loop، بعد از این‌جا) ست می‌شه — پس در
	 * wp_head (قبل از Loop) هنوز خالیه و set_data() به‌خاطرِ نبودِ @type
	 * ساکت رد می‌شد (count=0، با کامنتِ دیباگِ موقت تأیید شد). راه‌حل:
	 * مستقیم wc_get_product(get_queried_object_id()) رو صدا بزن — به
	 * globalِ Loop اصلاً وابسته نیست.
	 */
	if ( is_product() ) {
		$queried_product = wc_get_product( get_queried_object_id() );
		if ( $queried_product instanceof WC_Product ) {
			WC()->structured_data->generate_product_data( $queried_product );
		}
	}
	WC()->structured_data->generate_website_data();
}
add_action( 'wp_head', 'jluxe_emit_structured_data', 4 );

/**
 * SearchAction روی WebSite schema — پیش‌نیازِ «کادرِ جستجوی سایت‌لینک»یِ
 * گوگل (یه فیلدِ جستجوی مستقیم زیرِ نتیجه‌ی سایت توی گوگل). خروجیِ
 * پیش‌فرضِ generate_website_data فقط name/url داره، این فیلتر
 * potentialAction رو اضافه می‌کنه.
 */
function jluxe_add_website_search_action( array $markup ): array {
	$markup['potentialAction'] = array(
		'@type'       => 'SearchAction',
		'target'      => array(
			'@type'       => 'EntryPoint',
			'urlTemplate' => home_url( '/?s={search_term_string}' ),
		),
		'query-input' => 'required name=search_term_string',
	);
	return $markup;
}
add_filter( 'woocommerce_structured_data_website', 'jluxe_add_website_search_action' );

/**
 * پیش‌بارگذاریِ (preload) تصویرِ اصلیِ محصول برای بهبودِ LCP — دقیقاً
 * همون استراتژیِ هیروی صفحه‌ی اصلی.
 *
 * نکته‌ی مهم: سایزِ عکس باید دقیقاً با همون سایزی که خودِ صفحه واقعاً
 * درخواست می‌کنه یکی باشه، وگرنه preload بی‌فایده‌ست (مرورگر بازم باید
 * URL واقعی رو جدا دانلود کنه) — چیدمانِ پیش‌فرض
 * (woocommerce/single-product/product-image.php) از سایزِ
 * 'woocommerce_single' استفاده می‌کنه، ولی چیدمانِ کلاسیک
 * (content-single-product-classic.php) از 'full' — پس این‌جا بر اساسِ
 * تنظیمِ فعلیِ layout، سایزِ درست انتخاب می‌شه.
 */
function jluxe_preload_single_product_lcp_image(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return;
	}

	$image_id = $product->get_image_id();
	if ( ! $image_id ) {
		return;
	}

	$layout = jluxe_get_theme_settings()['product_page']['layout'] ?? 'default';
	$size   = 'classic' === $layout ? 'full' : 'woocommerce_single';

	$url    = wp_get_attachment_image_url( $image_id, $size );
	$srcset = wp_get_attachment_image_srcset( $image_id, $size );

	if ( $url ) {
		printf(
			'<link rel="preload" as="image" href="%1$s" fetchpriority="high"%2$s>' . "\n",
			esc_url( $url ),
			$srcset ? sprintf( ' imagesrcset="%s" imagesizes="(max-width: 1024px) 100vw, 26rem"', esc_attr( $srcset ) ) : ''
		);
	}
}
add_action( 'wp_head', 'jluxe_preload_single_product_lcp_image', 1 );

/* -------------------------------------------------------------------------
 * صفحهٔ محصول: بردکرامب (دادهٔ ساخت‌یافته) + نوار چسبانِ افزودن به سبد موبایل.
 * هر دو در هر دو چیدمان (default/classic) استفاده می‌شوند.
 * ---------------------------------------------------------------------- */

/**
 * پیش‌بارگذاری دو فونتِ اصلیِ فروشگاه (Regular/Bold) — چون فونت از
 * 1.55.8 روی کل storefront اعمال می‌شود، بدونِ preload اولین رندر با
 * فونتِ جایگزین دیده می‌شد (FOUT محسوس). URLها از خودِ خروجیِ build
 * خوانده می‌شوند (glob روی پوشهٔ compiled) تا با هر hash جدید سازگار بماند.
 */
function jluxe_preload_storefront_fonts( ?string $dir = null, ?string $uri = null ): void {
	if ( jluxe_is_dev() ) {
		return;
	}
	$dir = $dir ?? ( JLUXE_ASSET_DIR . '/assets' );
	$uri = $uri ?? ( JLUXE_ASSET_URI . '/assets' );
	foreach ( array( 'IRANYekanMobileRegular', 'IRANYekanMobileBold' ) as $font ) {
		$files = glob( $dir . '/' . $font . '-*.woff2' );
		if ( ! empty( $files ) ) {
			printf(
				'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
				esc_url( $uri . '/' . rawurlencode( basename( $files[0] ) ) )
			);
		}
	}
}
add_action( 'wp_head', 'jluxe_preload_storefront_fonts', 2 );

/** نوار چسبانِ افزودن به سبد — اسکریپت فقط صفحهٔ محصول. */
function jluxe_enqueue_sticky_cta(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/sticky-cta.js';
	wp_enqueue_script(
		'jluxe-sticky-cta',
		JLUXE_THEME_URI . '/assets/js/sticky-cta.js',
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_sticky_cta' );


/** زنجیرهٔ بردکرامب: خانه → فروشگاه → دستهٔ فعلی و والدهایش (حداکثر ۳ سطح) → محصول. */
function jluxe_breadcrumb_items( $product ): array {
	$items = array(
		array( 'خانه', home_url( '/' ) ),
	);
	if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
		$items[] = array( 'فروشگاه', get_permalink( wc_get_page_id( 'shop' ) ) );
	}
	if ( $product instanceof WC_Product && function_exists( 'wc_get_product_terms' ) ) {
		$terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) );
		$chain = array();
		foreach ( $terms as $term ) {
			if ( ! isset( $term->term_id ) ) {
				continue;
			}
			$walk   = array();
			$cursor = $term;
			$depth  = 0;
			while ( $cursor instanceof WP_Term && $depth < 3 ) {
				array_unshift( $walk, array( $cursor->name, get_term_link( $cursor ) ) );
				if ( ! $cursor->parent ) {
					break;
				}
				$parent = get_term( $cursor->parent, 'product_cat' );
				if ( $parent instanceof WP_Error ) {
					break;
				}
				$cursor = $parent;
				++$depth;
			}
			if ( count( $walk ) > count( $chain ) ) {
				$chain = array_slice( $walk, -3 );
			}
		}
		$items = array_merge( $items, $chain );
	}
	if ( $product instanceof WC_Product ) {
		$items[] = array( (string) $product->get_name(), get_permalink( $product->get_id() ) );
	}
	return $items;
}

/** چاپ BreadcrumbList JSON-LD — فقط داده؛ نمایشِ مرئی همان nav خود قالب‌هاست. */
function jluxe_print_breadcrumb_jsonld( $product ): void {
	$items = jluxe_breadcrumb_items( $product );
	if ( count( $items ) < 2 ) {
		return;
	}
	$elements = array();
	$position = 0;
	foreach ( $items as $item ) {
		$label = trim( (string) $item[0] );
		$url   = (string) $item[1];
		if ( '' === $label || '' === $url ) {
			continue;
		}
		++$position;
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $label,
			'item'     => esc_url_raw( $url ),
		);
	}
	if ( count( $elements ) < 2 ) {
		return;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	) ) . '</script>' . "\n";
}

/**
 * نوار چسبانِ افزودن به سبد — فقط موبایل (CSS بالای 768px مخفی است).
 * رندر سروری دارد تا بدون JS هم قیمت/مسیر دیده شود؛ رفتار کلیک را
 * assets/js/sticky-cta.js اضافه می‌کند (کلیک واقعی دکمهٔ ووکامرس).
 */
function jluxe_render_sticky_add_to_cart( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$in_stock    = $product->is_in_stock();
	$is_variable = $product->is_type( 'variable' );
	$purchasable = $product->is_purchasable();
	$price_html  = jluxe_reference_price_html( $product->get_price_html() );
	if ( $in_stock && $purchasable ) {
		$mode   = $is_variable ? 'scroll' : 'add';
		$label  = $is_variable ? 'انتخاب و خرید' : 'افزودن به سبد';
		$aria   = $is_variable ? 'رفتن به انتخاب تنوع' : 'افزودن به سبد خرید';
		$button = '<button type="button" class="jluxe-btn jluxe-btn-primary" data-jluxe-sticky-add data-jluxe-sticky-mode="' . esc_attr( $mode ) . '" aria-label="' . esc_attr( $aria ) . '">' . jluxe_icon_markup( 'cart', 'size-5' ) . esc_html( $label ) . '</button>';
	} else {
		$button = '<span class="jluxe-sticky-cta-out">ناموجود</span>';
	}
	?>
	<div class="jluxe-sticky-cta" data-jluxe-sticky-cta>
		<div class="jluxe-sticky-cta-info">
			<span class="jluxe-sticky-cta-name"><?php echo esc_html( $product->get_name() ); ?></span>
			<?php if ( '' !== (string) $price_html ) : ?>
				<span class="jluxe-sticky-cta-price"><?php echo jluxe_price_kses( $price_html ); ?></span>
			<?php endif; ?>
		</div>
		<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزای داخلی escape شده‌اند. ?>
	</div>
	<?php
}

/** نسخهٔ برگشت‌دهندهٔ SVG آیکون (برای چاپ داخل رشته‌ها) — از inc/icons.php. */
function jluxe_icon_markup( string $name, string $class = 'size-5' ): string {
	ob_start();
	jluxe_icon( $name, $class );
	return (string) ob_get_clean();
}

/**
 * خطِ سبزِ «سود شما از این خرید» برای محصولِ متغیر — طبقِ درخواستِ صریحِ
 * کاربر («روی محصولِ متغیر نشون داده نمی‌شه»). قبلاً این خط عمداً فقط
 * برای محصولِ ساده رندر می‌شد (globals.css/price.php کامنتِ قدیمی: بازسازیِ
 * عددِ دقیقِ صرفه‌جویی با جداکننده‌ی هزارگان درست در JS بدون منطقِ فرمتِ
 * سمت سرور مطمئن نیست) — اون نگرانی واقعیه، پس راه‌حلش بازسازی در JS
 * نیست؛ خودِ سرور (همین‌جا) با wc_price() دقیقاً همون رشته‌ی فرمت‌شده‌ی
 * سایت (تومان، جداکننده‌ی هزارگان، رقمِ فارسی — از طریقِ فیلترهای
 * wc_price بالاتر در همین فایل) رو برای هر تنوع از قبل می‌سازه و به
 * دیتای واقعیِ ووکامرس اضافه می‌کنه؛ assets/js/woocommerce.js
 * (jluxeBindPriceBox → found_variation) فقط همین HTML آماده رو
 * می‌ذاره، دقیقاً همون الگویی که برای خودِ price_html هم استفاده می‌شه.
 */
function jluxe_variation_saving_html( array $data, $product, $variation ): array {
	$regular = (float) $variation->get_regular_price();
	$sale    = (float) $variation->get_price();
	if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
		$data['jluxe_saving_html'] = wp_kses_post( wc_price( $regular - $sale ) ) . ' سود شما از این خرید';
	}
	/* R55: قیمتِ تنویع که با found_variation جابه‌جا می‌شود هم همان فرمتِ مرجع (عدد + گلیف) — JS آن را دست‌نخورده innerHTML می‌کند. */
	if ( ! empty( $data['price_html'] ) ) {
		$data['price_html'] = jluxe_reference_price_html( (string) $data['price_html'] );
	}
	return $data;
}
add_filter( 'woocommerce_available_variation', 'jluxe_variation_saving_html', 10, 3 );

/**
 * موقعیتِ نماد پول (آیکونِ تومان بعد از عدد، با فاصله — طبق رسم‌الخط فارسی) —
 * مستقیم فیلتر می‌شه (نه فقط از تنظیمات wc-settings→General→Currency
 * position) چون فرم ادمینِ ووکامرس روی این تنظیمات مقاوم بود (ذخیره نمی‌موند)؛
 * این‌جوری صرف‌نظر از مقدار ذخیره‌شده در آپشن، همیشه واقعاً درست نمایش
 * داده می‌شه.
 */
function jluxe_toman_price_format( string $format, string $currency_pos ): string {
	// طبق آخرین درخواستِ صریحِ کاربر (جای عددِ قیمت و آیکونِ svg باید جابه‌جا
	// بشه) — نمادِ پول (%1$s) قبل از عدد (%2$s) میاد؛ همراه با
	// .woocommerce-Price-amount bdi{direction:ltr} پایین‌تر در globals.css
	// (که ترتیبِ HTML رو صرف‌نظر از حدسِ جهتِ خودکارِ bdi حفظ می‌کنه)، این‌جوری
	// همیشه یکسان — آیکون چپ، عدد راست — می‌مونه، در همه‌ی جاهایی که قیمت
	// نشون داده می‌شه (کارتِ محصول، جعبه‌قیمتِ صفحه‌محصول، مودالِ quick-add).
	return '%1$s %2$s';
}
add_filter( 'woocommerce_price_format', 'jluxe_toman_price_format', 10, 2 );

/**
 * جداکننده‌ی هزارگان با ویرگول («۳,۶۴۵,۰۰۰») طبق درخواستِ صریحِ کاربر —
 * مستقیم فیلتر می‌شه (نه فقط تنظیماتِ wc-settings→General→Currency)، هم‌راستا
 * با الگوی toman_price_format بالا (فرم ادمینِ ووکامرس این تنظیمات رو
 * قابل‌اعتماد ذخیره نمی‌کنه).
 */
function jluxe_price_thousand_separator(): string {
	return ',';
}
add_filter( 'wc_get_price_thousand_separator', 'jluxe_price_thousand_separator' );

/**
 * ارقامِ خودِ عددِ قیمت (نه فقط واحدِ پول/جداکننده) تا الان لاتین می‌موندن —
 * baugِ واقعی: jluxe_fa_digits قبلاً فقط روی چند نقطه‌ی دستی (بج درصد، خطِ
 * موجودی، تعدادِ سبد) صدا زده می‌شد، ولی خودِ wc_price() (که همه‌جا — کارتِ
 * محصول، جعبه‌ی قیمتِ صفحه‌ی محصول، سبد، تسویه‌حساب — قیمت رو می‌سازه) از
 * این تابع رد نمی‌شد؛ چون wc_price() با number_format() خامِ PHP کار می‌کنه،
 * نه number_format_i18n() (که پایین‌تر فیلتر شده). با تستِ زنده در کروم پیدا
 * شد («54.000 تومان» به‌جای «۵۴.۰۰۰ تومان»). فیلترِ رسمیِ خودِ ووکامرس
 * (wc_price، روی خروجیِ نهاییِ HTML) دقیق‌ترین جاست — یک‌جا همه‌ی قیمت‌های
 * سایت رو فارسی می‌کنه.
 */
/**
 * باگِ واقعیِ ریشه‌ای (پیدا شده با بررسیِ زنده‌ی خروجیِ wc_price روی سایتِ
 * لوکال): جلوگیریِ jluxe_fa_digits_wc_price پایین از خرابیِ آیکونِ SVGِ
 * تومان به‌تنهایی کافی نبود، چون افزونه‌ی «persian-woocommerce» (نصب‌شده
 * روی سایت، مستقل از این پوسته) خودش هم دقیقاً همون فیلترِ wc_price (و
 * چندتای دیگه) رو با یک persian_number() کاملاً کور (str_replace ساده،
 * بدون هیچ آگاهی از SVG) هوک می‌کنه — و چون پلاگین‌ها قبل از پوسته لود
 * می‌شن، فیلترِ افزونه زودتر (روی رشته‌ی هنوز تمیز) اجرا و آیکون رو خراب
 * می‌کنه؛ فیلترِ محافظت‌شده‌ی خودِ پوسته که بعداً اجرا می‌شه دیگه به رشته‌ی
 * ازقبل‌خراب‌شده می‌رسه و placeholder‌اش با آیکونِ اصلیِ (سالمِ) خودش
 * match نمی‌شه. چون jluxe_fa_digits_wc_price پایین همین کارِ تبدیلِ ارقام
 * رو (با محافظتِ آیکون) کامل انجام می‌ده، دیگه نیازی به نسخه‌ی کورِ افزونه
 * نیست — این‌جا همون فیلترهای افزونه رو (روی همون شیِ singleton خودش،
 * PW()->tools->price، که remove_filter برای callbackِ آبجکتی به همون
 * دقیقاً همون شیء نیاز داره) unhook می‌کنیم. بقیه‌ی رشته‌هایی که این‌ها
 * روشون بودن (get_price_html، cart_item_price و...) همه از همون wc_price()
 * داخلی‌شون برای ساختِ HTML استفاده می‌کنن که فیلترِ محافظت‌شده‌ی پوسته
 * از قبل روش اجرا شده، پس هیچ تبدیلِ ارقامی از دست نمی‌ره.
 */
function jluxe_disable_persian_wc_plugin_digit_filter(): void {
	if ( ! function_exists( 'PW' ) ) {
		return;
	}
	$price_tool = PW()->tools->price ?? null;
	if ( ! $price_tool instanceof PW_Tools_Price ) {
		return;
	}
	$hooks = array(
		'wc_price',
		'woocommerce_get_price_html',
		'woocommerce_cart_item_price',
		'woocommerce_cart_item_subtotal',
		'woocommerce_cart_subtotal',
		'woocommerce_cart_shipping_method_full_label',
		'woocommerce_cart_total',
	);
	foreach ( $hooks as $hook ) {
		remove_filter( $hook, array( $price_tool, 'persian_number' ) );
	}
}
add_action( 'init', 'jluxe_disable_persian_wc_plugin_digit_filter', 20 );

function jluxe_fa_digits_wc_price( string $formatted_price ): string {
	/*
	 * is_admin() به‌تنهایی کافی نیست — وردپرس همیشه توی admin-ajax.php
	 * (صرف‌نظر از این‌که خودِ درخواست واقعاً از فرانت‌اند اومده یا نه، مثلاً
	 * AJAX سبدِ کشویی/جستجوی زنده‌ی همین تم) is_admin()=true حساب می‌کنه.
	 * باگِ واقعیِ گزارش‌شده: چون این شرط قبلاً AJAXِ سبدِ کشویی رو هم بلاک
	 * می‌کرد، inc/cart-ux.php/inc/search.php مجبور بودن دوباره دستی
	 * jluxe_fa_digits() رو مستقیم روی wc_price()/get_price_html() صدا بزنن —
	 * که چون این پاسِ دومِ دستی از حفاظتِ placeholder پایین رد نمی‌شد، آیکونِ
	 * SVG (که پاسِ اولِ همین فیلتر تویِ همون رشته گذاشته بود) رو خراب می‌کرد.
	 * راه‌حل: فقط وقتی واقعاً صفحه‌ی خودِ پیشخوان (نه AJAX) رندر می‌شه رد شو.
	 */
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $formatted_price;
	}
	/*
	 * باگِ واقعیِ دیگه: چون آیکونِ SVG تومان (jluxe_toman_icon_svg) الان
	 * به‌عنوانِ «نمادِ پول» داخلِ همین رشته‌ست، jluxe_fa_digits ارقامِ خودِ
	 * SVG (viewBox، width/height، مختصاتِ path) رو هم فارسی می‌کرد — که
	 * چون فارسی معتبرِ CSS/SVG number syntax نیست، آیکون خراب/نامرئی
	 * می‌شد. با تستِ زنده پیدا شد. راه‌حل: آیکون رو قبل از تبدیل موقتاً با
	 * یک placeholder جایگزین می‌کنیم، ارقامِ بقیه‌ی رشته (خودِ عددِ قیمت) رو
	 * فارسی می‌کنیم، بعد آیکونِ دست‌نخورده رو برمی‌گردونیم.
	 */
	$icon = jluxe_toman_icon_svg();
	if ( '' !== $icon && false !== strpos( $formatted_price, $icon ) ) {
		$placeholder      = '{{JLUXE_TOMAN_ICON}}';
		$formatted_price  = str_replace( $icon, $placeholder, $formatted_price );
		$formatted_price  = jluxe_fa_digits( $formatted_price );
		return str_replace( $placeholder, $icon, $formatted_price );
	}
	return jluxe_fa_digits( $formatted_price );
}
add_filter( 'wc_price', 'jluxe_fa_digits_wc_price' );

/**
 * قراردادِ نمایشِ قیمتِ این پوسته: «عدد، بعد نماد/واحد» — «۱۰۰۰ ریال»،
 * نه «ریال ۱۰۰۰». فرمتِ پیش‌فرضِ هستهٔ ووکامرس «نماد، بعد عدد» است و در
 * متنِ RTL همین باعث می‌شد نماد/واحد اول دیده شود. فقط ترتیبِ همان span
 * استانداردِ هسته جابه‌جا می‌شود؛ رقم‌ها، فاصله‌ها، آیکونِ SVG تومان،
 * <del>/<ins> حراج و بقیهٔ ساختار دست‌نخورده می‌مانند. اگر خروجی از قبل
 * «عدد بعد نماد» باشد، الگو مطابقت نمی‌کند و رشته دست‌نخورده برمی‌گردد
 * (idempotent) — پس با تنظیمِ «موقعیت نماد پول» خودِ ووکامرس هم تداخلی نیست.
 */
function jluxe_reorder_price_amounts( string $price ): string {
	/* تگِ آغازین ممکن است attributeهای اضافه داشته باشد (مثلاً aria-hidden="true"
	 * در خروجیِ نسخه‌های جدیدِ ووکامرس) — الگو نباید به بسته‌بودنِ فوریِ
	 * class="..." وابسته بماند. دو شکلِ واقعیِ wc_price پوشش داده می‌شود:
	 * ① مدرن (ووکامرس 9.10+): <span amount><bdi><span symbol translate="no">﷼</span> ۱۰۰</bdi></span>
	 * ② قدیمی: <span amount><span symbol>﷼</span> ۱۰۰</span>
	 * باگِ واقعی (R54): الگوی قدیمیِ ما هرگز شکلِ ① را نمی‌گرفت — چون
	 * <bdi> و translate="no" بینِ amount span و نماد می‌آمدند — در نتیجه
	 * همه‌جا دوباره «نماد اول» چاپ می‌شد. اگر از قبل «عدد بعد نماد» باشد
	 * هیچ الگویی مچ نمی‌کند و رشته دست‌نخورده برمی‌گردد (idempotent). */
	$reordered = preg_replace(
		'/(<span class="woocommerce-Price-amount amount"[^>]*>\s*<bdi>\s*)(<span class="woocommerce-Price-currencySymbol"[^>]*>.*?<\/span>)(\s*)(.*?)(\s*<\/bdi>)/s',
		'$1$4$3$2$5',
		$price
	);
	if ( ! is_string( $reordered ) ) {
		$reordered = $price;
	}
	$reordered = preg_replace(
		'/(<span class="woocommerce-Price-amount amount"[^>]*>\s*)(<span class="woocommerce-Price-currencySymbol"[^>]*>.*?<\/span>)(\s*)(.*?)(\s*<\/span>)/s',
		'$1$4$3$2$5',
		$reordered
	);
	if ( ! is_string( $reordered ) ) {
		$reordered = $price;
	}
	/* متنِ screen-reader (بازهٔ قیمت و قدیم/جدید) بدونِ span نماد است:
	 * «محدوده قیمت: ﷼ ۱۰۰,۰۰۰ تا ...» — بازچینیِ متنیِ «عدد سپس واحد». */
	$reordered = preg_replace_callback(
		'/(<span class="screen-reader-text">)(.*?)(<\/span>)/s',
		static function ( array $m ): string {
			$t = preg_replace( '/(﷼|ریال|تومان)\s*([۰-۹0-9][۰-۹0-9,،\.]*)/u', '$2 $1', $m[2] );
			return is_string( $t ) ? $m[1] . $t . $m[3] : $m[0];
		},
		$reordered
	);
	return is_string( $reordered ) ? $reordered : $price;
}

function jluxe_amount_first_wc_price( string $price ): string {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $price;
	}
	return jluxe_reorder_price_amounts( $price );
}
add_filter( 'wc_price', 'jluxe_amount_first_wc_price', 20 );

/**
 * فرصتِ دوم — روی خروجیِ نهاییِ get_price_html (فیلترِ رسمیِ ووکامرس).
 * باگِ واقعیِ رویِ سایتِ کاربر: با وجودِ فیلترِ wc_price، خروجیِ برخی
 * قیمت‌ها (مثل بازهٔ محصولِ متغیر که افزونه‌ها/کش‌های افزونه‌ای دوباره
 * می‌سازند یا اولویت‌های جابه‌جا شده‌اند) هنوز «نماد اول» بود. این فیلترِ
 * دیرهنگام (بعد از همهٔ افزونه‌ها) همان بازچینیِ idempotent را روی HTML
 * نهایی اجرا می‌کند؛ چون الگو فقط «نماداول» را می‌گیرد، هیچ‌وقت دوباره‌کاری
 * نمی‌شود و متنِ screen-reader-text (بدونِ span نماد) دست‌نخورده می‌ماند.
 */
function jluxe_amount_first_price_html( string $price_html ): string {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $price_html;
	}
	return jluxe_reorder_price_amounts( $price_html );
}
add_filter( 'woocommerce_get_price_html', 'jluxe_amount_first_price_html', 99 );
add_filter( 'woocommerce_variation_price_html', 'jluxe_amount_first_price_html', 99 );

/** گلیفِ SVG تومان — دقیقاً همان مارک‌آپ مرجعی که کاربر معیار قرار داد (viewBox 22x18، fill ثابت). */
function jluxe_toman_glyph_svg(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="16" viewBox="0 0 22 18" fill="none" aria-hidden="true"><g clip-path="url(#jluxe-toman-clip)"><path d="M16.8984 0.750259H14.5224C14.1425 0.750259 13.8346 1.05819 13.8346 1.43805C13.8346 1.8179 14.1425 2.12583 14.5224 2.12583H16.8984C17.2782 2.12583 17.5862 1.8179 17.5862 1.43805C17.5862 1.05819 17.2782 0.750259 16.8984 0.750259Z" fill="#8f9bad"/><path d="M21.2474 3.81424C21.2265 3.43908 21.164 2.94669 21.0598 2.33706C20.999 1.98275 20.9365 1.65014 20.8722 1.33925C20.8002 0.991882 20.4528 0.776514 20.1106 0.866829C19.7945 0.950197 19.5983 1.26456 19.6625 1.58414C19.7268 1.90372 19.7876 2.2233 19.8484 2.56372C19.9474 3.11603 20.0073 3.54329 20.0281 3.8455C20.049 4.22066 19.9369 4.51245 19.6921 4.72087C19.4472 4.92929 19.0225 5.0335 18.4181 5.0335H6.67273V3.47035C6.67273 2.8034 6.55289 2.21201 6.31321 1.69617C6.07353 1.18033 5.72963 0.776514 5.28153 0.484725C4.83343 0.192937 4.31237 0.0470428 3.71838 0.0470428C3.15564 0.0470428 2.65283 0.198148 2.20993 0.500357C1.76704 0.802566 1.42315 1.2142 1.17825 1.73525C0.93336 2.2563 0.810913 2.83466 0.810913 3.47035C0.810913 4.40824 1.07925 5.13771 1.61594 5.65876C2.15262 6.17981 2.85864 6.44034 3.73401 6.44034H5.42221V6.53412C5.42221 6.78423 5.32321 6.98223 5.12521 7.12812C4.92721 7.27402 4.63543 7.39907 4.24985 7.50328C3.86427 7.60749 3.21817 7.75338 2.31154 7.94096L2.2907 7.9453C1.91641 8.01999 1.67673 8.3882 1.76009 8.76075C1.84086 9.12201 2.19517 9.35214 2.5573 9.28006C2.70493 9.25054 2.8517 9.22101 2.99933 9.19148C3.9789 8.99348 4.7214 8.79548 5.22682 8.59749C5.73224 8.39949 6.09958 8.14157 6.32884 7.82373C6.5581 7.50588 6.67273 7.07602 6.67273 6.53412V6.44034H18.4181C19.0538 6.44034 19.5878 6.31528 20.0203 6.06518C20.4528 5.81507 20.7706 5.48942 20.9738 5.08821C21.177 4.687 21.2682 4.26234 21.2474 3.81424ZM5.45347 5.0335H3.73401C3.14001 5.0335 2.70754 4.91626 2.43659 4.68179C2.16565 4.44732 2.03017 4.0435 2.03017 3.47035C2.03017 2.85551 2.17867 2.36311 2.47567 1.99317C2.77267 1.62322 3.1869 1.43825 3.71838 1.43825C4.29153 1.43825 4.724 1.61801 5.01579 1.97754C5.30758 2.33706 5.45347 2.83466 5.45347 3.47035V5.0335Z" fill="#8f9bad"/><path d="M6.23507 12.8413C6.23507 12.4097 5.88515 12.0597 5.4535 12.0597C5.02184 12.0597 4.67192 12.4097 4.67192 12.8413C6.23507 13.273 5.02184 13.6229 5.4535 13.6229C5.88515 13.6229 6.23507 13.273 6.23507 12.8413Z" fill="#8f9bad"/><path d="M20.7724 12.3489C20.5432 11.8123 20.2201 11.3859 19.8033 11.0672C19.3864 10.7493 18.9071 10.5904 18.3652 10.5904C17.6878 10.5904 17.1094 10.8231 16.6301 11.286C16.1507 11.7497 15.7964 12.388 15.5671 13.2009L15.0669 14.9985C15.0148 15.2173 14.9132 15.3815 14.7621 15.4909C14.611 15.6003 14.4104 15.655 14.1603 15.655C13.6913 15.655 13.3553 15.6064 13.152 15.5065C12.9488 15.4075 12.8134 15.233 12.7456 14.9829C12.6779 14.7328 12.644 14.3263 12.644 13.7636L12.6284 9.74629C12.6284 9.3503 12.5607 9.00206 12.4252 8.69898C12.2897 8.39677 12.0761 8.15969 11.7843 7.98775C11.4925 7.8158 11.1226 7.72983 10.6744 7.72983H10.1586C9.70008 7.72983 9.32232 7.8158 9.02532 7.98775C8.72832 8.15969 8.51469 8.39417 8.38443 8.69117C8.25417 8.98817 8.18904 9.33987 8.18904 9.74629L8.20467 14.1075C8.20467 14.7119 8.1213 15.1887 7.95456 15.5378C7.78783 15.8877 7.50646 16.1422 7.11046 16.3037C6.71446 16.4661 6.16215 16.546 5.45352 16.546H5.226C4.57989 16.546 4.04842 16.4114 3.63158 16.1396C3.21474 15.8686 2.90732 15.497 2.70932 15.0219C2.51132 14.5478 2.41232 14.0041 2.41232 13.3884C2.41232 13.1705 2.44619 12.8743 2.48787 12.593C2.54953 12.1744 2.18306 11.8192 1.76622 11.8922C1.49874 11.939 1.29467 12.1544 1.25819 12.4236C1.21477 12.7406 1.19306 13.0619 1.19306 13.3884C1.19306 14.1804 1.34677 14.9264 1.65419 15.6237C1.96161 16.322 2.41753 16.8847 3.02195 17.312C3.62637 17.7401 4.36105 17.9528 5.226 17.9528H5.45352C6.38099 17.9528 7.13912 17.7965 7.72791 17.4839C8.31669 17.1713 8.74917 16.7284 9.02532 16.1552C9.30148 15.5829 9.43435 14.8995 9.42393 14.1075L9.4083 9.74629C9.4083 9.50661 9.4578 9.34595 9.5568 9.26172C9.6558 9.17835 9.8564 9.13666 10.1586 9.13666H10.6744C10.9558 9.13666 11.1486 9.18356 11.2528 9.27735C11.357 9.37114 11.4091 9.52745 11.4091 9.74629L11.4248 13.7636C11.4248 14.4731 11.5055 15.0671 11.6671 15.5456C11.8286 16.025 12.1073 16.3975 12.5033 16.6632C12.8993 16.929 13.4517 17.0618 14.1603 17.0618C14.4625 17.0618 14.7543 16.9941 15.0356 16.8586C15.317 16.724 15.5619 16.5365 15.7703 16.2959L15.8329 16.3272C16.6457 16.744 17.2501 17.0288 17.6461 17.1791C18.0421 17.3302 18.4381 17.4057 18.8341 17.4057C19.2301 17.4057 19.587 17.2946 19.9361 17.0697C20.2852 16.8456 20.5692 16.4861 20.788 15.9911C21.0069 15.497 21.1163 14.8639 21.1163 14.0919C21.1163 13.4666 21.0017 12.8865 20.7724 12.3489ZM19.6313 15.6003C19.4542 15.866 19.1884 15.9989 18.8341 15.9989C18.5632 15.9989 18.2766 15.939 17.9744 15.8191C17.6722 15.7002 17.1667 15.4622 16.4581 15.1079L16.3487 15.0454L16.7551 13.576C16.901 13.0445 17.112 12.6485 17.3882 12.388C17.6643 12.1284 17.99 11.9972 18.3652 11.9972C18.8654 11.9972 19.2457 12.1831 19.5063 12.5522C19.7668 12.9221 19.897 13.4353 19.897 14.0919C19.897 14.8326 19.8085 15.3346 19.6313 15.6003Z" fill="#8f9bad"/></g><defs><clipPath id="jluxe-toman-clip"><rect width="20.4391" height="17.9059" fill="white" transform="translate(0.810913 0.0470428)"/></clipPath></defs></svg>';
}

/**
 * فرمتِ مرجعِ قیمت (R55 — معیارِ DOMِ فرستاده‌شدهٔ کاربر):
 * قیمتِ قدیمی فقط عددِ خط‌خورده (بدونِ نماد)، قیمتِ جدید/مقدارها با گلیفِ
 * SVG تومان بعد از عدد با شفافیتِ ۷۵٪ — «عدد سپس واحد». idempotent:
 * گلیفِ جاگذاری‌شده دیگر الگوی نماد را ندارد.
 */
function jluxe_reference_price_html( string $price_html ): string {
	$html = jluxe_reorder_price_amounts( $price_html );
	/* داخلِ <del>: نماد حذف می‌شود — مرجع: <span class="line-through ...">۲,۵۸۱,۰۰۰</span> */
	$stripped = preg_replace(
		'/(<del[^>]*>.*?)\s*<span class="woocommerce-Price-currencySymbol"[^>]*>.*?<\/span>(.*?<\/del>)/s',
		'$1$2',
		$html
	);
	$html = is_string( $stripped ) ? $stripped : $html;
	/* بقیهٔ نمادها (قیمتِ جدید/ins/مقدارهای بازه) → گلیفِ تومان */
	$glyph = '<span class="jluxe-toman-glyph">' . jluxe_toman_glyph_svg() . '</span>';
	$replaced = preg_replace(
		'/<span class="woocommerce-Price-currencySymbol"[^>]*>.*?<\/span>/s',
		$glyph,
		$html
	);
	return is_string( $replaced ) ? $replaced : $html;
}

/** kses با اجازهٔ svg برای گلیفِ داخلِ قیمت — برخلافِ wp_kses_post که svg را می‌کند پاک. */
function jluxe_price_kses( string $html ): string {
	$allowed = wp_kses_allowed_html( 'post' );
	$allowed['svg'] = array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'aria-hidden' => true, 'id' => true, 'clip-path' => true );
	$allowed['path'] = array( 'id' => true, 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true );
	$allowed['g'] = array( 'id' => true, 'fill' => true, 'clip-path' => true );
	$allowed['defs'] = array();
	$allowed['clippath'] = array( 'id' => true );
	$allowed['rect'] = array( 'width' => true, 'height' => true, 'fill' => true, 'transform' => true );
	return wp_kses( $html, $allowed );
}

/**
 * نگاشتِ نام‌های رایج فارسیِ رنگ به کدِ HEX — برای نمایش سواچ رنگی واقعی
 * (دایره‌ی رنگی) به‌جای پیل متنی، دقیقاً مثل مرجع (Boom). فقط برای
 * ویژگی‌هایی که واقعاً «رنگ» هستن استفاده می‌شه؛ اگر نام رنگ در این
 * فهرست نبود، به همون پیل متنیِ امن قبلی برمی‌گرده (چیزی حدس زده نمی‌شه).
 */
function jluxe_persian_color_to_hex( string $name ): ?string {
	static $map = null;
	static $en_map = null;
	if ( null === $map ) {
		$map = array(
			'سفید' => '#FFFFFF', 'سفید صدفی' => '#F5F0E8', 'شیری' => '#F6F1E7', 'استخوانی' => '#EFEAE0',
			'مشکی' => '#18181B', 'سیاه' => '#18181B', 'مشکی مات' => '#1C1C1E',
			'قرمز' => '#ED1A45', 'قرمز تیره' => '#B91C1C', 'قرمز روشن' => '#F87171', 'زرشکی' => '#9F1239', 'سرخابی' => '#DB2777', 'گلبهی' => '#FDA4AF', 'هلویی' => '#FDBA96',
			'آبی' => '#1D4ED8', 'ابی' => '#1D4ED8', 'آبی تیره' => '#1E3A8A', 'آبی سیر' => '#1E3A8A', 'آبی روشن' => '#93C5FD', 'آبی آسمانی' => '#7DD3FC', 'سرمه ای' => '#1E293B',
			'سبز' => '#16A34A', 'سبز تیره' => '#166534', 'سبز روشن' => '#86EFAC', 'زیتونی' => '#6B8E23', 'پسته ای' => '#93C572', 'فیروزه ای' => '#2DD4BF', 'نیلی' => '#312E81', 'لاجوردی' => '#2563EB',
			'زرد' => '#EAB308', 'زرد روشن' => '#FDE047', 'زرد تیره' => '#CA8A04', 'خردلی' => '#A16207', 'طلایی' => '#D4AF37', 'طلایی روشن' => '#E9C46A', 'طلایی تیره' => '#B8860B',
			'نارنجی' => '#F97316', 'نارنجی روشن' => '#FDBA74', 'نارنجی سوخته' => '#C2410C', 'مسی' => '#B87333',
			'صورتی' => '#EC4899', 'صورتی روشن' => '#F9A8D4', 'صورتی تیره' => '#BE185D',
			'بنفش' => '#7C3AED', 'بنفش تیره' => '#5B21B6', 'بنفش روشن' => '#C4B5FD', 'یاسی' => '#A78BFA',
			'قهوه ای' => '#78350F', 'قهوه ای روشن' => '#A16207', 'قهوه ای تیره' => '#451A03', 'شکلاتی' => '#5C3317', 'کالباسی' => '#8A3324',
			'طوسی' => '#9CA3AF', 'طوسی تیره' => '#4B5563', 'طوسی روشن' => '#D1D5DB', 'خاکستری' => '#9CA3AF', 'خاکستری تیره' => '#4B5563', 'خاکستری روشن' => '#D1D5DB', 'دودی' => '#6B7280',
			'نقره ای' => '#C0C0C0', 'نقره ای تیره' => '#94A3B8',
			'کرم' => '#E9DFC7', 'کرم روشن' => '#F5EEDC', 'کرم تیره' => '#D9C9A8', 'بژ' => '#E9DFC7', 'بژ روشن' => '#F5EEDC', 'بژ تیره' => '#CBB896', 'پنبه ای' => '#F2EFE6',
		);
		$en_map = array(
			'white' => '#FFFFFF', 'ivory' => '#F6F1E7', 'cream' => '#E9DFC7', 'beige' => '#E9DFC7', 'black' => '#18181B', 'charcoal' => '#374151',
			'red' => '#ED1A45', 'dark red' => '#B91C1C', 'light red' => '#F87171', 'maroon' => '#9F1239', 'burgundy' => '#9F1239', 'wine' => '#9F1239', 'hot pink' => '#DB2777', 'pink' => '#EC4899', 'light pink' => '#F9A8D4', 'dark pink' => '#BE185D', 'rose' => '#FDA4AF', 'peach' => '#FDBA96', 'coral' => '#F97355',
			'blue' => '#1D4ED8', 'dark blue' => '#1E3A8A', 'light blue' => '#93C5FD', 'sky blue' => '#7DD3FC', 'navy' => '#1E293B',
			'green' => '#16A34A', 'dark green' => '#166534', 'light green' => '#86EFAC', 'olive' => '#6B8E23', 'mint' => '#93C572', 'turquoise' => '#2DD4BF', 'teal' => '#0D9488', 'indigo' => '#312E81', 'violet' => '#A78BFA',
			'yellow' => '#EAB308', 'light yellow' => '#FDE047', 'dark yellow' => '#CA8A04', 'mustard' => '#A16207', 'khaki' => '#A16207', 'gold' => '#D4AF37', 'golden' => '#D4AF37',
			'orange' => '#F97316', 'light orange' => '#FDBA74', 'burnt orange' => '#C2410C', 'copper' => '#B87333',
			'purple' => '#7C3AED', 'dark purple' => '#5B21B6', 'light purple' => '#C4B5FD', 'lavender' => '#C4B5FD',
			'brown' => '#78350F', 'light brown' => '#A16207', 'dark brown' => '#451A03', 'chocolate' => '#5C3317', 'tan' => '#D2B48C',
			'gray' => '#9CA3AF', 'grey' => '#9CA3AF', 'dark gray' => '#4B5563', 'dark grey' => '#4B5563', 'light gray' => '#D1D5DB', 'light grey' => '#D1D5DB', 'silver' => '#C0C0C0', 'smoke' => '#6B7280',
		);
	}
	/* R65: نرمال‌سازیِ کلید — نیم‌فاصله/فاصلهٔ یونیکد → فاصلهٔ ساده و فشردن؛
	وگرنه «قهوه ای» با ZWNJِ متفاوت از نقشه جا می‌ماند. */
	$key = trim( (string) preg_replace( '/[\x{200B}-\x{200F}\x{00A0}]/u', ' ', $name ) );
	$key = preg_replace( '/\s+/u', ' ', $key );
	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}
	/* گام دوم: نامکِ انگلیسی («dark-grey») — خطِ تیره → فاصله، حروف کوچک. */
	$en = strtolower( (string) preg_replace( '/[-_]+/', ' ', trim( $name ) ) );
	return $en_map[ $en ] ?? null;
}

/**
 * آیا این ویژگی از نوع «رنگ»ه؟ بر اساس نام ویژگی (نه مقداردهی حدسی).
 */
function jluxe_is_color_attribute( string $attribute_name ): bool {
	$label = wc_attribute_label( $attribute_name );
	return false !== mb_strpos( $label, 'رنگ' ) || false !== stripos( $attribute_name, 'color' );
}

/**
 * سواچ‌های انتخاب تنوع (رنگ/سایز/...) — از content-single-product.php
 * استخراج شده تا هم صفحه‌ی محصول هم پاپ‌آپ انتخاب سریع تنوع (quick-add
 * روی محصول متغیر در گرید، inc/woocommerce.php: jluxe_ajax_variation_picker)
 * دقیقاً یک منطق رندر داشته باشن، نه دو کپیِ جدا که ممکنه از هم جدا بیفتن.
 */
/**
 * R74: انتخابِ پیش‌فرضِ تنوع — همان «مقادیرِ پیش‌فرضِ فرم» خودِ ووکامرس
 * (ویرایش محصول ← داده‌های محصول ← ویژگی‌ها ← پیش‌فرض‌ها) که کاربر خواسته
 * «همیشه یک تنوع از قبل انتخاب شده باشد». ترکیبی که به تنوعِ ناموجود
 * می‌رسد عمداً پیش‌فرض نمی‌شود — صفحه با حالتِ خالیِ «انتخاب کنید» می‌آید
 * نه با یک تنوعِ از قبل بسته (دکمهٔ خریدِ غیرفعال اولین برداشتِ بد است).
 */
function jluxe_default_variation_pick( WC_Product $product ): array {
	$defaults = $product->get_default_attributes();
	if ( empty( $defaults ) || ! is_array( $defaults ) ) {
		return array();
	}
	foreach ( $product->get_available_variations() as $variation ) {
		if ( ! is_array( $variation ) || empty( $variation['is_in_stock'] ) ) { continue; }
		$attrs = is_array( $variation['attributes'] ?? null ) ? $variation['attributes'] : array();
		$match = true;
		foreach ( $defaults as $name => $value ) {
			$value = (string) $value;
			$key   = 'attribute_' . $name;
			if ( ! array_key_exists( $key, $attrs ) ) { $match = false; break; }
			$own = (string) $attrs[ $key ];
			if ( '' !== $own && $own !== $value && rawurldecode( $own ) !== rawurldecode( $value ) ) { $match = false; break; }
		}
		if ( $match ) { return array_map( 'strval', $defaults ); }
	}
	return array();
}

function jluxe_render_variation_swatches( WC_Product $product, array $variation_attributes ): void {
	/*
	 * باگِ واقعیِ گزارش‌شده («انتخابِ سایزِ X، ولی درخواستِ واقعی برایِ سایزِ
	 * دیگه‌ای می‌رفت»): این سواچ‌ها از رویِ کل فهرستِ ترم‌هایِ ویژگی رندر
	 * می‌شدن (get_variation_attributes که مقادیرِ خامِ attribute رو
	 * برمی‌گردونه)، نه فقط اونایی که واقعاً یک Variation برایِ این محصول
	 * دارن. مثلاً یه محصول با ویژگیِ «سایز»ِ ۴۸ تا ۷۶ (۲۹ مقدار)، ولی فقط
	 * ۱۵ تنوعِ واقعی (۴۸ تا ۶۲) ساخته‌شده — ۱۴تایِ باقی سواچِ کاملاً «واقعی»
	 * نشون داده می‌شدن (۶۳ تا ۷۶) بدونِ اینکه هیچ Variationـی پشتشون باشه.
	 * با تستِ زنده تأیید شد: کلیک‌کردنِ یکی از این سواچ‌هایِ بدونِ Variation
	 * باعث می‌شد اسکریپتِ خودِ ووکامرس (wc-add-to-cart-variation.js) هیچ
	 * found_variationی پیدا نکنه و select زیرین رو عوض‌شده نگه داره ولی
	 * hidden inputِ variation_id همون مقدارِ آخرین انتخابِ معتبرِ قبلی رو
	 * حفظ می‌کرد — یعنی کاربر فکر می‌کرد سایزِ جدیدی انتخاب کرده، ولی
	 * درخواستِ افزودن‌به‌سبد برایِ همون سایزِ قبلی می‌رفت. راه‌حل: سواچ‌ها
	 * رو از اول فقط از رویِ مقادیریِ که واقعاً توی get_available_variations()
	 * (یعنی Variationِ واقعاً موجود) دیده می‌شن می‌سازیم — دقیقاً همون منبعِ
	 * دیتایی که خودِ فرم (data-product_variations) و found_variation ازش
	 * استفاده می‌کنن، پس دیگه هیچ‌وقت از هم جدا نمی‌افتن.
	 */
	$jluxe_valid_options = array();
	$jluxe_stocky_options = array();
	$jluxe_has_wildcard   = array();
	/* R74: پیش‌فرضِ ووکامرس برای «همیشه انتخاب‌شده بودنِ» یک تنوع. */
	$jluxe_defaults = jluxe_default_variation_pick( $product );
	$_jluxe_same_pick = static function ( $option, $pick ): bool {
		$option = (string) $option;
		$pick   = (string) $pick;
		return '' !== $pick && ( $option === $pick || rawurldecode( $option ) === rawurldecode( $pick ) );
	};
	foreach ( $product->get_available_variations() as $jluxe_variation ) {
		/* R65: تنوعِ ناموجود نباید قابلِ انتخاب باشد — جدا از «معتبر»،
		مجموعهٔ «موجود» (is_in_stock) هم ساخته می‌شود؛ گزینه‌ای که فقط در
		تنوعِ ناموجود حاضر است، disabled رندر می‌شود (نه حذف، تا کاربر
		بداند چنین گزینه‌ای وجود دارد اما فعلاً خریدنی نیست). */
		$jluxe_in_stock = ! empty( $jluxe_variation['is_in_stock'] );
		foreach ( $jluxe_variation['attributes'] as $jluxe_attr_key => $jluxe_attr_value ) {
			$jluxe_attr_name = str_replace( 'attribute_', '', $jluxe_attr_key );
			if ( '' === $jluxe_attr_value ) {
				// مقدارِ خالی یعنی «هر مقداری» — این Variation با هر گزینه‌ای
				// از این ویژگی مچ می‌شه، پس فیلترکردن برایِ این ویژگی معنی
				// نداره (همه‌ی گزینه‌ها واقعاً معتبرن).
				$jluxe_has_wildcard[ $jluxe_attr_name ] = true;
				continue;
			}
			$jluxe_valid_options[ $jluxe_attr_name ][ $jluxe_attr_value ] = true;
			if ( $jluxe_in_stock ) {
				$jluxe_stocky_options[ $jluxe_attr_name ][ $jluxe_attr_value ] = true;
			}
		}
	}

	foreach ( $variation_attributes as $attr_name => $attr_options ) {
		$attr_label       = wc_attribute_label( $attr_name );
		$select_id        = sanitize_title( $attr_name );
		$is_color_attr    = jluxe_is_color_attribute( $attr_name );
		$is_taxonomy_attr = 0 === strpos( $attr_name, 'pa_' );
		$jluxe_default_pick = isset( $jluxe_defaults[ $attr_name ] ) ? (string) $jluxe_defaults[ $attr_name ] : '';

		if ( empty( $jluxe_has_wildcard[ $attr_name ] ) && ! empty( $jluxe_valid_options[ $attr_name ] ) ) {
			$attr_options = array_values( array_filter( $attr_options, fn( $o ) => isset( $jluxe_valid_options[ $attr_name ][ $o ] ) ) );
		}
		?>
		<div class="mt-4" data-jluxe-variation-group="<?php echo esc_attr( $select_id ); ?>">
			<?php if ( $is_color_attr ) : ?>
				<p class="text-[12.5px] font-medium text-foreground" data-jluxe-variation-label>
					انتخاب <?php echo esc_html( $attr_label ); ?>: <span data-jluxe-variation-selected></span>
				</p>
			<?php else : ?>
				<p class="text-[12.5px] font-medium text-foreground"><?php echo esc_html( $attr_label ); ?></p>
			<?php endif; ?>
			<div class="mt-1.5 flex flex-wrap items-center gap-2" data-jluxe-variation-swatches>
				<?php foreach ( $attr_options as $option ) :
					if ( $is_taxonomy_attr ) {
						$term         = get_term_by( 'slug', $option, $attr_name );
						$option_label = $term ? $term->name : $option;
					} else {
						$option_label = apply_filters( 'woocommerce_variation_option_name', $option, null, $attr_name, $product );
					}
					$jluxe_swatch = jluxe_resolve_variation_swatch( $attr_name, (string) $option, $option_label, $is_color_attr );
					/* R65/R74: وضعیتِ ناموجودیِ واقعیِ هر گزینه از همان منبعِ «موجود» —
					این دو متغیر قبلاً هرگز تعریف نشده بودند (نالِ بی‌صدا → سواچِ
					ناموجودِ رندرشدهٔ سرور هرگز disable نمی‌شد و فقط JS جبران
					می‌کرد؛ در فرم‌های بالای آستانه که سینک early-return می‌کند،
					سواچ کاملاً آزاد دیده می‌شد). */
					$jluxe_oos = ( empty( $jluxe_stocky_options[ $attr_name ]['*'] ) && ! empty( $jluxe_valid_options[ $attr_name ] ) && empty( $jluxe_stocky_options[ $attr_name ][ $option ] ) );
					$jluxe_oos_attrs = $jluxe_oos ? ' disabled="disabled" aria-disabled="true" title="' . esc_attr( (string) $option_label . ' (ناموجود)' ) . '"' : '';
					?>
					<?php if ( $jluxe_swatch && 'color' === $jluxe_swatch['type'] ) : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="relative grid size-9 shrink-0 place-items-center rounded-full outline-none transition-transform<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<span class="size-7 rounded-full border border-black/10 shadow-inner" style="background:<?php echo esc_attr( $jluxe_swatch['value'] ); ?>"></span>
							<span data-jluxe-variation-ring class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-primary ring-offset-2 ring-offset-surface opacity-0"></span>
						</button>
					<?php elseif ( $jluxe_swatch && 'image' === $jluxe_swatch['type'] ) : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="relative grid size-9 shrink-0 place-items-center rounded-full outline-none transition-transform<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<span class="size-7 overflow-hidden rounded-full border border-black/10 shadow-inner">
								<img src="<?php echo esc_url( $jluxe_swatch['value'] ); ?>" alt="" class="size-full object-cover" />
							</span>
							<span data-jluxe-variation-ring class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-primary ring-offset-2 ring-offset-surface opacity-0"></span>
						</button>
					<?php else : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="rounded-full border border-border px-3.5 py-1.5 text-[12.5px] font-medium text-text-secondary transition-colors hover:border-primary/50 data-[active]:border-primary data-[active]:bg-primary/5 data-[active]:text-primary<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<?php echo esc_html( $option_label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<div class="variations sr-only">
				<?php
				wc_dropdown_variation_attribute_options(
					array(
						'options'   => $attr_options,
						'attribute' => $attr_name,
						'product'   => $product,
						'selected'  => $jluxe_default_pick,
					)
				);
				?>
			</div>
		</div>
	<?php }
}

/**
 * ردیف ستاره‌ی امتیاز با پرشدنِ اعشاری واقعی (مثلاً ۴.۲۷ از ۵ → ستاره‌ی
 * پنجم حدود ۲۷٪ پر می‌شه)، نه رند‌شده به نزدیک‌ترین عدد صحیح — دقیقاً
 * همون رفتاری که ووکامرس/مرجع (Boom) نشون می‌ده. با یک لایه‌ی «۵ ستاره‌ی
 * خالی» زیرین + یک لایه‌ی «۵ ستاره‌ی پر» روییِ clip‌شده با عرض درصدی پیاده
 * شده — بدون نیاز به SVG جدا برای هر حالت نیم‌پر.
 */
function jluxe_boom_star_row( float $rating, string $size_class = 'size-[15px]' ): string {
	$pct = max( 0, min( 100, ( $rating / 5 ) * 100 ) );
	$star_path = 'm12 3.6 2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.85 6.8 19.6l1-5.75L3.6 9.75l5.8-.85Z';
	$star_svg  = sprintf(
		'<svg class="%1$s shrink-0" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="%2$s"></path></svg>',
		esc_attr( $size_class ),
		$star_path
	);

	return sprintf(
		'<span class="relative inline-flex" aria-hidden="true"><span class="flex text-border">%1$s</span><span class="absolute inset-0 flex overflow-hidden text-boom-star" style="width:%2$s%%">%1$s</span></span>',
		str_repeat( $star_svg, 5 ),
		esc_attr( (string) round( $pct, 1 ) )
	);
}


/**
 * طبقِ درخواستِ صریحِ کاربر («روش‌های پرداخت رو تو یک کادرِ جدا سمتِ راستِ
 * صفحه») بخشِ پرداخت (woocommerce_checkout_payment()) دیگه داخلِ سایدبارِ
 * باریکِ #order_review رندر نمی‌شه — این‌جا از هوکِ پیش‌فرضِ خودِ ووکامرس
 * (WC()->includes/wc-template-hooks.php: woocommerce_checkout_order_review
 * → woocommerce_checkout_payment، priority ۲۰) جدا می‌شه؛ به‌جاش
 * form-checkout.php خودش این تابع رو مستقیماً در یک ستونِ عریضِ جدا صدا
 * می‌زنه (jluxe-payment-column).
 *
 * این کار امنه چون AJAX واقعیِ ووکامرس (assets/frontend/checkout.js →
 * update_order_review) برای پرداخت یک fragment کاملاً جدا و مستقل از
 * #order_review داره — با سلکتورِ کلاس '.woocommerce-checkout-payment'
 * (نه یک id تو در توی #order_review)، پس هر جای صفحه که این کلاس باشه
 * درست رفرش می‌شه (بررسیِ زنده‌ی class-wc-ajax.php: خطِ فرگمنت‌ها). فقط
 * چیزی که باید حفظ بشه واقعاً وجودِ خودِ فیلد/دکمه‌ها داخلِ <form
 * class="checkout"> ئه، نه لزوماً تو در توییِ خاصی — که هنوز برقراره.
 */
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

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

		<p data-jluxe-payment-error hidden class="mb-2 text-caption text-error">روش پرداخت را انتخاب کنید.</p>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<div class="mt-4">
			<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="flex h-12 w-full items-center justify-center rounded-lg bg-primary px-5 text-button font-normal text-primary-foreground transition-colors hover:bg-primary-hover" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>
		</div>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
	<?php
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
	$steps = array(
		'cart'     => 'بررسی سبد خرید',
		'shipping' => 'اطلاعات ارسال',
		'payment'  => 'نحوه پرداخت',
		'done'     => 'پایان خرید',
	);
	$ids   = array_keys( $steps );
	$active_index = array_search( $active_id, $ids, true );
	if ( false === $active_index ) {
		$active_index = 0;
	}
	?>
	<nav aria-label="مراحل خرید" class="jluxe-checkout-stepper mx-auto flex max-w-[1296px] items-center justify-center gap-1 px-4 py-6 sm:gap-3">
		<?php foreach ( $ids as $i => $id ) : ?>
			<?php if ( $i > 0 ) : ?>
				<span class="h-px w-3 bg-border sm:w-16" aria-hidden="true"></span>
			<?php endif; ?>
			<div class="flex w-12 flex-col items-center gap-1.5 sm:w-auto" data-jluxe-step="<?php echo esc_attr( $id ); ?>">
				<?php
				// آخرین مرحله («پایان خرید») فقط از صفحه‌ی thankyou.php صدا زده
				// می‌شه، یعنی همیشه با یک سفارشِ واقعاً ثبت‌شده — پس وقتی این
				// مرحله «فعال»ه، در واقع کامل‌شده‌ست، نه در حال انجام؛ باید مثل
				// بقیه‌ی مراحلِ done دایره‌ی سبز/تیک بگیره، نه استایلِ «فعلی».
				$is_last   = $i === count( $ids ) - 1;
				$is_active = $i === $active_index;
				$is_done   = $i < $active_index || ( $is_active && $is_last );
				$is_active = $is_active && ! $is_done;
				?>
				<div data-jluxe-step-circle data-jluxe-step-number="<?php echo esc_attr( jluxe_fa_digits( (string) ( $i + 1 ) ) ); ?>" class="flex size-9 shrink-0 items-center justify-center rounded-full border-2 sm:size-11 <?php echo $is_done ? 'border-success bg-success text-white' : ( $is_active ? 'border-primary text-primary' : 'border-border text-text-muted' ); ?>">
					<?php if ( $is_done ) : ?>
						<svg class="size-4 sm:size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
					<?php else : ?>
						<span class="text-caption font-bold"><?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></span>
					<?php endif; ?>
				</div>
				<span data-jluxe-step-label class="text-center text-[10px] leading-tight sm:whitespace-nowrap sm:text-caption <?php echo $is_active ? 'font-medium text-foreground' : 'text-text-muted'; ?>">
					<?php echo esc_html( $steps[ $id ] ); ?>
				</span>
			</div>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * شهرستان‌های واقعیِ هر استان — کلیدها دقیقاً همون کدهای واقعیِ
 * WC()->countries->get_states('IR') (از woocommerce/i18n/states.php)ن،
 * چون billing_city باید بر اساس مقدار واقعیِ billing_state فیلتر بشه.
 * فهرست شهرستان‌های اصلی/شناخته‌شده‌ی هر استانه (نه لزوماً همه‌ی
 * بخش‌های اداری) — برای انتخابِ آدرس در چک‌اوت کافیه.
 */
function jluxe_iran_cities(): array {
	return array(
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

	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name']['priority'] = 5;
	}
	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_last_name']['priority'] = 6;
	}

	if ( isset( $fields['billing']['billing_email'] ) ) {
		// طبقِ مرجعِ تصویریِ جدیدِ کاربر ایمیل «اختیاری»ه؛ افزونه‌ی «ووکامرس
		// فارسی» به‌صورتِ پیش‌فرض این فیلد رو الزامی می‌کرد (باگِ واقعیِ
		// پیداشده با تستِ زنده: برچسبِ «آدرس ایمیل» بدونِ برچسبِ «(اختیاری)»
		// و ستاره‌ی الزامی نشون داده می‌شد).
		$fields['billing']['billing_email']['priority'] = 16;
		$fields['billing']['billing_email']['required'] = false;
	}

	// استان/شهر/کدپستی — یک ردیفِ ۳ستونه در دسکتاپ (طبق طراحی تأییدشده).
	// چون assets/js/frontend/address-i18n.js ووکامرس همه‌ی .form-row رو
	// روی هر بارگذاری/تغییر کشور به‌صورت flat و بر اساس priority دوباره
	// می‌چینه (rows.detach().appendTo(wrapper)) — نمی‌شه این‌ها رو داخل یک
	// <div> جدا nest کرد، چون همون اسکریپت اون تو در تو بودن رو از بین
	// می‌بره. راه‌حل: فیلدها flat می‌مونن، priority پشت‌سرهم می‌گیرن، و
	// چیدمانِ سه‌ستونه/دوستونه با flex-basis روی خودِ .form-row (بر اساس id)
	// در globals.css پیاده می‌شه، نه با تودرتو کردن DOM.
	if ( isset( $fields['billing']['billing_state'] ) ) {
		$fields['billing']['billing_state']['priority'] = 25;
	}
	if ( isset( $fields['billing']['billing_city'] ) ) {
		// شهرستان یک select واقعیه (نه متن آزاد) که بر اساس استانِ انتخابی
		// با assets/js/woocommerce.js پر می‌شه — چون گزینه‌ها به مقدارِ
		// billing_state وابسته‌ان، فقط گزینه‌ی مقدارِ فعلی (هنگام ویرایش
		// آدرس ذخیره‌شده) این‌جا سمت سرور اضافه می‌شه، بقیه سمت کلاینته.
		$current_city                                    = WC()->checkout()->get_value( 'billing_city' );
		$fields['billing']['billing_city']['priority']    = 26;
		$fields['billing']['billing_city']['type']        = 'select';
		$fields['billing']['billing_city']['options']     = $current_city
			? array( '' => 'انتخاب شهرستان', $current_city => $current_city )
			: array( '' => 'ابتدا استان را انتخاب کنید' );
	}
	if ( isset( $fields['billing']['billing_postcode'] ) ) {
		$fields['billing']['billing_postcode']['priority']    = 27;
		$fields['billing']['billing_postcode']['placeholder'] = 'درج کد پستی برای ارسال با پست الزامی است';
	}

	if ( isset( $fields['billing']['billing_phone'] ) ) {
		// موبایل الزامیه (کدِ رهگیریِ پیامکی/پیگیریِ سفارش به همین شماره
		// ارسال می‌شه) — طبقِ مرجعِ تصویریِ جدید و منطقِ واقعیِ کسب‌وکار،
		// برخلافِ پیش‌فرضِ اختیاریِ افزونه‌ی «ووکامرس فارسی».
		$fields['billing']['billing_phone']['label']    = 'موبایل';
		$fields['billing']['billing_phone']['priority'] = 15;
		$fields['billing']['billing_phone']['required'] = true;
	}

	// تلفن ثابت — فیلد سفارشی، ووکامرس/افزونه‌ی فارسی این رو ندارن. priority
	// بعد از آدرس (50) طبقِ ترتیبِ مرجعِ تصویریِ جدید (... آدرس، بعد تلفنِ
	// ثابت/توضیحات).
	$fields['billing']['billing_landline'] = array(
		'label'        => 'تلفن ثابت',
		'required'     => false,
		'priority'     => 55,
		'type'         => 'tel',
		'autocomplete' => 'tel',
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
add_filter( 'woocommerce_form_field', 'jluxe_hide_field_wrapper', 10, 2 );
add_filter( 'woocommerce_checkout_fields', 'jluxe_billing_fields', 20 );

/**
 * برچسب/جای‌گزین «توضیحات سفارش» مطابق طراحی تأییدشده.
 */
function jluxe_order_notes_field( array $fields ): array {
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = 'توضیحات سفارش';
		$fields['order']['order_comments']['placeholder']  = 'توضیحی که نیاز است در رابطه با سفارش بیان کنید';
		$fields['order']['order_comments']['class']        = array( 'form-row-wide' );
	}
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'jluxe_order_notes_field', 20 );

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
add_filter( 'woocommerce_admin_billing_fields', 'jluxe_admin_billing_fields' );

/**
 * فرم «ارسال به آدرس متفاوت» در این چک‌اوت وجود نداره — طبق طراحی
 * تأییدشده فقط یک آدرس (همون billing) وجود داره.
 */
add_filter( 'woocommerce_ship_to_different_address_checkbox_enabled', '__return_false' );

/**
 * متن دکمه‌ی «پرداخت» (نهاییِ چک‌اوت) — طبق درخواست صریح کاربر، به‌جای
 * متن پیش‌فرض/ترجمه‌شده‌ی ووکامرس دقیقاً «پرداخت» چاپ می‌شه. این فیلتر
 * واقعاً روی woocommerce/checkout/payment.php اثر داره (اون تمپلیت
 * $order_button_text رو از apply_filters('woocommerce_order_button_text', ...)
 * می‌گیره) — priority بالا (۹۹۹۹) چون افزونه‌ی «ووکامرس فارسی» هم همین
 * فیلتر رو با متنِ ترجمه‌شده‌ی خودش هوک می‌کنه؛ تستِ زنده تأیید کرد این‌جا
 * priority بالاتر لازمه تا فیلترِ پوسته آخرین چیزیه که اجرا می‌شه.
 *
 * دکمه‌ی «ادامه»ی سبدِ خرید برخلافِ این، از یک فیلتر استفاده نمی‌کنه —
 * تمپلیتِ اصلیِ ووکامرس (proceed-to-checkout-button.php) اصلاً
 * apply_filters نداره، فقط esc_html_e ثابت. برای اون یکی به‌جای
 * فیلتر از یک اورراید واقعیِ تمپلیت استفاده شده:
 * woocommerce/cart/proceed-to-checkout-button.php.
 */
add_filter( 'woocommerce_order_button_text', fn() => 'پرداخت', 9999 );

/**
 * استایل مینیمال سبد/چک‌اوت با استایل کلی سایت هماهنگه (فونت/رنگ از
 * dist/assets/main-*.css که همه‌جا لود می‌شه)، پس فقط یک فایل کوچیک برای
 * رفتار JS (دکمه‌های +/- تعداد) اضافه می‌کنیم — بدون دست زدن به AJAX واقعی
 * ووکامرس (assets/js/frontend/cart.js خودش روی change شدن input.qty گوش
 * می‌ده و سبد رو sync می‌کنه).
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
	// هر جا ممکنه کارت محصول باشه (شاپ/دسته/برچسب/جستجو/صفحه اصلی با گرید
	// محصول) هم به هاور/لمس‌طولانیِ تامبنیل گالری کارت نیاز داره، نه فقط
	// صفحه‌ی تکی محصول/سبد/چک‌اوت — پس این اسکریپت سراسری لود می‌شه (فایل
	// کوچیکه و با .closest() امن نوشته شده، جایی که چیزی نباشه کاری نمی‌کنه).
	$jluxe_wc_js_path = JLUXE_THEME_DIR . '/assets/js/woocommerce.js';
	wp_enqueue_script(
		'jluxe-woocommerce',
		JLUXE_THEME_URI . '/assets/js/woocommerce.js',
		array( 'jquery', 'jluxe-storefront-utils' ),
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
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_woocommerce_assets', 20 );

/**
 * صفحه‌ی محصول (woocommerce/content-single-product.php) توضیحات/مشخصات/
 * دیدگاه‌ها رو خودش به‌صورت بخش‌های جدا (نه تب‌باکس) رندر می‌کنه، پس
 * تب‌باکس پیش‌فرض ووکامرس (که همون محتوا رو دوباره تکرار می‌کرد) و
 * upsell (که در طراحی تأییدشده وجود نداره) حذف می‌شن. محصولات مرتبط
 * (woocommerce_output_related_products) و پرسش‌وپاسخ واقعی
 * (jluxe_render_qa_section در inc/qa.php) دست‌نخورده می‌مونن.
 */
function jluxe_remove_default_product_tabs(): void {
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
}
add_action( 'init', 'jluxe_remove_default_product_tabs' );

/**
 * درخت دسته‌بندی‌های مگامنو — src/islands/MegaMenu.tsx قبلاً یک دیتاست
 * هاردکد از سایتِ دیگه‌ای (jluxe.ir، دامنه‌ی کاملاً جدا) استفاده می‌کرد که
 * لینک‌هاش عملاً مشتری رو از این سایت خارج می‌کرد — یک باگ واقعی، نه صرفاً
 * دیتای دمو. این تابع به‌جاش از دسته‌های واقعیِ product_cat همین سایت
 * (سطح اول = ستون راست، سطح دوم = گروه‌های ستون اصلی، سطح سوم = آیتم‌های
 * زیر هر گروه) یک درخت می‌سازه.
 *
 * @return array<int, array{id: string, label: string, url: string, count: int, groups: array}>
 */
function jluxe_get_mega_menu_categories(): array {
	$cache_key = 'jluxe_mega_menu_tree_v1';
	/* R62: دو لایهٔ کش — Object Cache (با Redis واقعی می‌ماند؛ بدونِ آن فقط
	حافظهٔ همین درخواست است) و بعد Transient (fallback برای نصب‌های بدونِ
	object-cache.php). خروجی در هر دو لایه «آرایهٔ» دیتاست است، نه HTML. */
	$cached = wp_cache_get( $cache_key, 'jluxe' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$cached = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		wp_cache_set( $cache_key, $cached, 'jluxe', HOUR_IN_SECONDS );
		return $cached;
	}

	$default_cat_id = (int) get_option( 'default_product_cat' );

	$top_terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'exclude'    => $default_cat_id ? array( $default_cat_id ) : array(),
		)
	);
	if ( is_wp_error( $top_terms ) ) {
		return array();
	}

	$categories = array();
	foreach ( $top_terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term->term_id,
				'hide_empty' => true,
			)
		);
		$children = is_wp_error( $children ) ? array() : $children;

		$groups = array();
		foreach ( $children as $child ) {
			$child_link = get_term_link( $child );
			if ( is_wp_error( $child_link ) ) {
				continue;
			}

			$grandchildren = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $child->term_id,
					'hide_empty' => true,
				)
			);
			$grandchildren = is_wp_error( $grandchildren ) ? array() : $grandchildren;

			$items = array();
			foreach ( $grandchildren as $grandchild ) {
				$grandchild_link = get_term_link( $grandchild );
				if ( is_wp_error( $grandchild_link ) ) {
					continue;
				}
				$items[] = array(
					'label' => $grandchild->name,
					'url'   => $grandchild_link,
				);
			}

			$groups[] = array(
				'title' => $child->name,
				'url'   => $child_link,
				'items' => $items,
			);
		}

		$categories[] = array(
			'id'     => (string) $term->term_id,
			'label'  => $term->name,
			'url'    => $link,
			'count'  => (int) $term->count,
			'groups' => $groups,
		);
	}

	set_transient( $cache_key, $categories, DAY_IN_SECONDS );
	wp_cache_set( $cache_key, $categories, 'jluxe', HOUR_IN_SECONDS );
	return $categories;
}

/**
 * پاک‌سازی کش درخت مگامنو پس از تغییر دسته‌های محصول — هر دو لایه
 * (Object Cache و Transient) با هم.
 */
function jluxe_clear_mega_menu_cache( $term_id = 0, $taxonomy = '' ): void {
	if ( 'product_cat' === $taxonomy ) {
		delete_transient( 'jluxe_mega_menu_tree_v1' );
		wp_cache_delete( 'jluxe_mega_menu_tree_v1', 'jluxe' );
	}
}
add_action( 'created_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );
add_action( 'edited_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );
add_action( 'delete_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );
add_action( 'clean_term_cache', 'jluxe_clear_mega_menu_cache', 10, 2 );

/**
 * تاکسونومیِ «برند» — این فروشگاه از هیچ افزونه‌ی برندی استفاده نمی‌کنه،
 * ولی بخش «محصولات پرفروش» در صفحه‌ی اصلی به یک منبعِ واقعیِ «بر اساس
 * برند» نیاز داره (inc/theme-settings-homepage.php →
 * jluxe_render_homepage_brick_products). بدون این تاکسونومی، گزینه‌ی برند
 * صرفاً یک UI تزئینیِ بی‌اثر می‌بود؛ با ثبت واقعیِ product_brand، هم روی
 * صفحه‌ی ویرایش محصول (متاباکس استاندارد وردپرس) قابل تخصیصه، هم توی
 * چیدمانِ آجری واقعاً فیلتر می‌کنه.
 */
function jluxe_register_product_brand_taxonomy(): void {
	register_taxonomy(
		'product_brand',
		'product',
		array(
			'label'             => 'برند',
			'labels'            => array(
				'name'          => 'برندها',
				'singular_name' => 'برند',
				'search_items'  => 'جستجوی برند',
				'all_items'     => 'همه‌ی برندها',
				'edit_item'     => 'ویرایش برند',
				'add_new_item'  => 'افزودن برند جدید',
				'new_item_name' => 'نام برند جدید',
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'brand' ),
		)
	);
}
add_action( 'init', 'jluxe_register_product_brand_taxonomy' );

/**
 * چند رشته‌ی انگلیسیِ باقی‌مونده از خودِ ووکامرس که هنوز ترجمه‌ی فارسی‌شون
 * روی translate.wordpress.org به‌صورت «fuzzy» علامت خورده (پس در فایل .mo
 * دانلودی نهایی حذف می‌شن، حتی با اینکه ترجمه‌ی درست توی .po وجود داره) —
 * مثال واقعی: "Kurdistan (کردستان)" در لیست استان‌های ایران (فرم آدرس
 * تسویه‌حساب). به‌جای وابستگی به تکمیل‌شدن ترجمه‌ی بالادستی، همین‌جا اورراید می‌شه.
 */
function jluxe_fix_untranslated_woocommerce_strings( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) {
		return $translated;
	}
	if ( 'Kurdistan (کردستان)' === $text ) {
		return 'کردستان';
	}
	return $translated;
}
add_filter( 'gettext', 'jluxe_fix_untranslated_woocommerce_strings', 10, 3 );

/**
 * ارقام صفحه‌بندی (paginate_links — مثل آرشیو فروشگاه) از داخلِ خودِ
 * number_format_i18n() ساخته می‌شن (نه چیزی که با فیلتر paginate_links
 * قابل دسترسی باشه؛ اون فیلتر فقط روی URL کار می‌کنه، نه متنِ نمایشی).
 * فیلترِ درستْ همینه — همه‌جای دیگه‌ی سایت (قیمت/امتیاز/تعداد و...) با
 * jluxe_fa_digits فارسی می‌شه، این‌جا هم برای هماهنگی. فقط سمتِ فرانت،
 * چون جدول‌های ادمین وردپرس ممکنه رقمِ لاتین رو با JS پردازش کنن.
 */
function jluxe_fa_digits_number_format( string $formatted ): string {
	return is_admin() ? $formatted : jluxe_fa_digits( $formatted );
}
add_filter( 'number_format_i18n', 'jluxe_fa_digits_number_format' );

/**
 * محصولات مرتبط (woocommerce/single-product/related.php) — تنظیمات
 * product_page.show_related/related_count از قبل توی ادمین ذخیره می‌شدن
 * ولی هیچ‌جا واقعاً خونده نمی‌شدن (باگ واقعی، نه چیز جدید). این‌جا به
 * قلاب‌های رسمی خودِ ووکامرس وصل می‌شن.
 */
/**
 * نوتیس‌های قدیمیِ خودِ ووکامرس (wc_print_notices، از هوکِ رسمیِ
 * woocommerce_before_single_product) بالای صفحه‌ی محصول ظاهر می‌شن —
 * چه سبز (موفقیت) چه قرمز (خطا، مثلاً «لطفاً گزینه‌های محصول را انتخاب
 * کنید» برای محصولِ متغیر بدون variation معتبر) — که با سیستمِ toastِ
 * سفارشیِ ما (assets/js/woocommerce.js: showToast) قاطی/تکراری می‌شه
 * (باگِ واقعیِ گزارش‌شده). فقط روی صفحه‌ی محصول این هوکِ خاص حذف می‌شه —
 * سبد/تسویه‌حساب/فروشگاه که toast ندارن دست‌نخورده می‌مونن، چون اون‌جا
 * نمایشِ نوتیسِ سرور واقعاً لازمه.
 */
function jluxe_remove_single_product_notices(): void {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
	}
}
add_action( 'wp', 'jluxe_remove_single_product_notices' );

function jluxe_maybe_disable_related_products(): void {
	if ( ! jluxe_get_setting( 'product_page.show_related', true ) ) {
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
	}
}
add_action( 'wp', 'jluxe_maybe_disable_related_products' );

function jluxe_related_products_args( array $args ): array {
	$count            = max( 2, min( 8, (int) jluxe_get_setting( 'product_page.related_count', 4 ) ) );
	$args['posts_per_page'] = $count;
	$args['columns']        = min( 4, $count );
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'jluxe_related_products_args' );

/**
 * R76: منبعِ «محصولات مرتبط» زیرِ محصول — سه حالت (پنل زرین ← فروشگاه):
 *  - category (پیش‌فرض): فقط کالای همان دسته. باگِ پیش‌فرضِ ووکامرس (دسته +
 *    تگ باهم) باعث می‌شد تگ‌های مشترکِ تصادفی، کالای نامرتبط بیاورد —
 *    گزارشِ واقعی کاربر: زیرِ کالای «کالای خواب»، ستِ بهداشتی نمایش می‌داد.
 *  - brand: فقط کالای همان برند — برند از تاکسونومیِ product_brand خودِ وو
 *    (اگر فعاله) وگرنه از ویژگیِ محصولی که برچسب/نامش «برند/brand» باشد.
 *  - manual: انتخابِ دستی — فیلدِ رسمیِ «فروش بالاسری (Upsells)» ویرایش محصول.
 * زنجیرهٔ fallback: manual/brand خالی → category. خروجی همیشه publish +
 * visible و بدونِ خودِ محصول؛ ترتیبِ قطعیِ جدیدترین (قابلِ کش، برخلافِ
 * پیشنهادِ «اضافه خرید» که طبقِ درخواستِ کاربر رندوم است). ناموجودها حذف
 * نمی‌شوند (فیلترِ سراسریِ «ناموجود در انتها» مرتبشان می‌کند).
 */
function jluxe_product_brand_term_ids( int $product_id ): array {
	if ( taxonomy_exists( 'product_brand' ) ) {
		$terms = get_the_terms( $product_id, 'product_brand' );
		$out = array();
		foreach ( (array) $terms as $term ) {
			$term_id = is_object( $term ) ? ( $term->term_id ?? 0 ) : (int) $term;
			if ( $term_id > 0 ) { $out[] = $term_id; }
		}
		return $out;
	}
	foreach ( (array) wc_get_attribute_taxonomies() as $taxonomy ) {
		$label = (string) ( $taxonomy->attribute_label ?? '' );
		$name  = (string) ( $taxonomy->attribute_name ?? '' );
		if ( '' === $name ) { continue; }
		if ( false !== mb_stripos( $label, 'برند' ) || false !== stripos( $name, 'brand' ) ) {
			$terms = wc_get_product_terms( $product_id, 'pa_' . $name, array( 'fields' => 'ids' ) );
			$out = array();
			foreach ( (array) $terms as $term ) {
				$term_id = is_object( $term ) ? ( $term->term_id ?? 0 ) : (int) $term;
				if ( $term_id > 0 ) { $out[] = $term_id; }
			}
			if ( ! empty( $out ) ) { return $out; }
		}
	}
	return array();
}

function jluxe_related_products_for( int $product_id, int $limit ): array {
	$limit    = max( 2, min( 8, $limit ) );
	$mode     = (string) jluxe_get_setting( 'shop.related_mode', 'category' );
	if ( ! in_array( $mode, array( 'category', 'brand', 'manual' ), true ) ) {
		$mode = 'category';
	}
	static $memo = array();
	$key = $product_id . '|' . $mode . '|' . $limit;
	if ( isset( $memo[ $key ] ) ) { return $memo[ $key ]; }

	$pick = static function ( array $candidates ) use ( $product_id, $limit ): array {
		$out = array();
		foreach ( $candidates as $candidate ) {
			$pid = $candidate instanceof WC_Product ? $candidate->get_id() : (int) $candidate;
			if ( ! $pid || $pid === $product_id ) { continue; }
			$product = wc_get_product( $pid );
			if ( ! $product || 'publish' !== $product->get_status() || 'visible' !== $product->get_catalog_visibility() ) { continue; }
			$out[] = $pid;
			if ( count( $out ) >= $limit ) { break; }
		}
		return $out;
	};
	$query = static function ( array $extra ): array {
		$GLOBALS['product_query_args'] = null;
		return wc_get_products( array_merge( array(
			'status'     => 'publish',
			'visibility' => 'visible',
			'exclude'    => array(),
			'orderby'    => 'date',
			'order'      => 'DESC',
			'limit'      => 20,
			'return'     => 'objects',
		), $extra ) );
	};
	$by_category = static function () use ( $product_id, $query ): array {
		$category_ids = array();
		$product = wc_get_product( $product_id );
		if ( $product ) { $category_ids = (array) $product->get_category_ids(); }
		if ( empty( $category_ids ) ) { return array(); }
		return $query( array( 'exclude' => array( $product_id ), 'category' => array_map( 'jluxe_term_id_to_slug_product_cat', $category_ids ) ) );
	};

	$ids = array();
	if ( 'manual' === $mode ) {
		$product = wc_get_product( $product_id );
		$ids     = $product ? $pick( (array) $product->get_upsell_ids() ) : array();
		if ( empty( $ids ) ) { $ids = $pick( $by_category() ); }
	} elseif ( 'brand' === $mode ) {
		$brand_ids = jluxe_product_brand_term_ids( $product_id );
		if ( ! empty( $brand_ids ) ) {
			$candidates = $query( array( 'exclude' => array( $product_id ) ) );
			$with_brand = array();
			foreach ( $candidates as $candidate ) {
				if ( array_intersect( $brand_ids, jluxe_product_brand_term_ids( $candidate->get_id() ) ) ) {
					$with_brand[] = $candidate;
				}
			}
			$ids = $pick( $with_brand );
		}
		if ( empty( $ids ) ) { $ids = $pick( $by_category() ); }
	} else {
		$ids = $pick( $by_category() );
	}
	$memo[ $key ] = $ids;
	return $ids;
}

function jluxe_filtered_related_products( $related_posts, $product_id, $args = array() ) {
	if ( ! function_exists( 'wc_get_product' ) ) { return $related_posts; }
	$limit = max( 2, min( 8, (int) ( $args['posts_per_page'] ?? 4 ) ) );
	$ids   = jluxe_related_products_for( (int) $product_id, $limit );
	return ! empty( $ids ) ? $ids : $related_posts;
}
add_filter( 'woocommerce_related_products', 'jluxe_filtered_related_products', 10, 3 );

/**
 * محصولاتِ پیشنهادیِ پاپ‌آپِ بعدِ افزودن به سبد (صفحه‌ی تکیِ محصول).
 *
 * R72 — بازبینیِ کاملِ شرط‌ها. حالت‌ها (پنل زرین ← اضافه خرید):
 *  - fixed: فقط شناسه‌های انتخاب‌شدهٔ سراسریِ مدیر (محد به خودِ همین فهرست می‌ماند).
 *  - per_product (پیش‌فرض): اول Cross-sells واقعیِ خودِ ووکامرس (ویرایش محصول ←
 *    داده‌های محصول ← محصولات پیوسته — یعنی انتخابِ مدیر برای همان محصول)، بعد
 *    پرکردنِ اسلات‌های خالی با محصولاتِ موجودِ همان دسته، بعد کلِ فروشگاه.
 *  - per_category: همیشه فقط محصولاتِ موجودِ همان دسته.
 *  - random: اتفاقی بین کالاهای موجودِ کلِ فروشگاه.
 *
 * «موجود» (R72) دیگر فقط وضعیتِ والدِ محصول متغیر نیست: is_in_stock() روی
 * WC_Product_Variable فقط متای _stock_status والد را می‌خواند — محصولی که
 * همهٔ تنوع‌هایش ناموجودند ولی والدش «موجود» ثبت شده، قبلاً پیشنهاد می‌شد و
 * مودالِ تنوعِ آن همهٔ سواچ‌هایش بسته بود. حالا برای محصول متغیر باید حداقلِ
 * یک تنوعِ purchasable و در-انبارِ واقعی وجود داشته باشد (کشِ استاتیک در
 * طولِ یک درخواست). کوئری‌هایِ تصادفی هم «visibility=visible» می‌خواهند
 * (کالای مخفی از کاتالوگ پیشنهاد نمی‌شود) و «orderby=rand» — خروجیِ این
 * تابع داخلِ پاسخِ POST افزودن‌به‌سبد رندر می‌شود (غیرقابلِ کشِ صفحه)، پس
 * رندومِ به‌روز مشکلی برای کش ندارد (تصمیمِ قطعیِ R61 با درخواستِ صریحِ
 * کاربر در R72 به رندومِ بینِ کالاهای موجود تغییر کرد). هر گزینه‌ای بعد از
 * کوئری دوباره با همین شرطِ سخت‌گیرانه فیلتر می‌شود (استخرِ بزرگ‌تر از limit
 * کشیده می‌شود تا فیلتر بعد از limit اسلات هدر ندهد — باگِ واقعیِ نسخهٔ قبل).
 */
function jluxe_suggested_is_available( $product ): bool {
	if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
		return false;
	}
	static $cache = array();
	$id = $product->get_id();
	if ( array_key_exists( $id, $cache ) ) {
		return (bool) $cache[ $id ];
	}
	if ( $product->is_type( 'variable' ) ) {
		$available = false;
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation && $variation->is_purchasable() && $variation->is_in_stock() ) {
				$available = true;
				break;
			}
		}
	} else {
		$available = $product->is_in_stock();
	}
	$cache[ $id ] = $available;
	return $available;
}

/**
 * استخرِ اتفاقیِ کالاهای موجود — برایِ پرکردنِ اسلات‌های پیشنهاد.
 * stock_status=instock در کوئری فقط وضعیتِ والد را می‌بیند؛ به همین دلیل
 * استخر کمی بزرگ‌تر از نیاز کشیده می‌شود و هر گزینه دوباره با
 * jluxe_suggested_is_available (بررسیِ تنوعِ واقعی) فیلتر می‌شود.
 */
function jluxe_suggested_random_pool( array $exclude_ids, array $category_ids = array(), int $pool_size = 12 ): array {
	$args = array(
		'status'       => 'publish',
		'visibility'   => 'visible',
		'exclude'      => array_map( 'absint', $exclude_ids ),
		'stock_status' => 'instock',
		'orderby'      => 'rand',
		'limit'        => max( 1, $pool_size ),
		'return'       => 'objects',
	);
	if ( ! empty( $category_ids ) ) {
		$args['category'] = array_map( 'jluxe_term_id_to_slug_product_cat', $category_ids );
	}
	$found = wc_get_products( $args );
	$out   = array();
	foreach ( (array) $found as $candidate ) {
		if ( jluxe_suggested_is_available( $candidate ) ) {
			$out[] = $candidate;
		}
	}
	return $out;
}

function jluxe_get_suggested_products_for_cart( WC_Product $product, int $limit = 3 ): array {
	$limit = max( 1, $limit );
	$mode  = (string) jluxe_get_setting( 'purchase_addons.mode', 'per_product' );
	if ( ! in_array( $mode, array( 'fixed', 'per_product', 'per_category', 'random' ), true ) ) {
		$mode = 'per_product';
	}

	$seen = array( $product->get_id() => true );
	$out  = array();
	$take = static function ( array $candidates ) use ( &$out, &$seen, $limit ): bool {
		foreach ( $candidates as $candidate ) {
			$pid = $candidate instanceof WC_Product ? $candidate->get_id() : 0;
			if ( ! $pid || isset( $seen[ $pid ] ) || ! jluxe_suggested_is_available( $candidate ) ) {
				continue;
			}
			$seen[ $pid ] = true;
			$out[]        = $candidate;
			if ( count( $out ) >= $limit ) {
				return true;
			}
		}
		return false;
	};

	if ( 'fixed' === $mode ) {
		// حالتِ ثابت: فقط و فقط فهرستِ مدیر — اسلاتِ خالی با جایگزین پر نمی‌شود.
		$picked = array();
		foreach ( (array) jluxe_get_setting( 'purchase_addons.fixed_ids', array() ) as $fixed_id ) {
			$fixed = wc_get_product( absint( $fixed_id ) );
			if ( $fixed ) {
				$picked[] = $fixed;
			}
		}
		$take( $picked );
		return $out;
	}

	if ( 'random' === $mode ) {
		// R72: حالتِ اتفاقی — بینِ کالاهای موجودِ کلِ فروشگاه.
		$take( jluxe_suggested_random_pool( array_keys( $seen ), array(), $limit * 4 ) );
		return $out;
	}

	if ( 'per_product' === $mode ) {
		// ⓪ (R77) انتخابِ صریحِ مدیر در باکسِ «محصولات پیشنهادی» ویرایش محصول —
		// مقدم بر Cross-sells.
		// ① فیلدِ رسمیِ Cross-sells ووکامرس. اگر برای این محصول هر کدام
		// انتخاب شده باشد، «همان» نهایی است — گزینه‌های از-دست-رفته
		// (ناموجود/مخفی) حذف می‌شوند ولی با پیشنهادِ تصادفی رقیق نمی‌شوند؛
		// fallback فقط وقتی است که هیچ‌کدام انتخاب نشده باشد.
		$explicit = array();
		foreach ( (array) get_post_meta( $product->get_id(), '_jluxe_suggested_ids', true ) as $suggested_id ) {
			$suggested_product = wc_get_product( absint( $suggested_id ) );
			if ( $suggested_product ) {
				$explicit[] = $suggested_product;
			}
		}
		$cross = array();
		foreach ( (array) $product->get_cross_sell_ids() as $cross_id ) {
			$cross_product = wc_get_product( absint( $cross_id ) );
			if ( $cross_product ) {
				$cross[] = $cross_product;
			}
		}
		// انتخابِ صریحِ متاباکس «نهایی» است (قاعدهٔ R72: رقیق‌شدن ممنوع)؛
		// فقط اگر هیچ‌کدام از آن‌ها موجود نبود، Cross-sells جایگزین می‌شود.
		if ( ! empty( $explicit ) ) {
			$take( $explicit );
			if ( ! empty( $out ) ) { return $out; }
		}
		if ( ! empty( $cross ) ) {
			$take( $cross );
			return $out;
		}
	}

	// ② (per_product بدونِ cross-sell) همان دسته ③ (per_product) کلِ
	//    فروشگاه / (per_category) فقط همان دسته.
	$category_ids = $product->get_category_ids();
	if ( ! empty( $category_ids ) ) {
		if ( $take( jluxe_suggested_random_pool( array_keys( $seen ), $category_ids, $limit * 4 ) ) ) {
			return $out;
		}
	}
	if ( 'per_product' === $mode ) {
		$take( jluxe_suggested_random_pool( array_keys( $seen ), array(), $limit * 4 ) );
	}
	return $out;
}

/**
 * wc_get_products()['category'] آرگومان اسلاگِ ترم می‌خواد، نه ID —
 * get_category_ids() ولی ID برمی‌گردونه؛ این تبدیلِ کوچیک همون‌جا لازمه.
 */
function jluxe_term_id_to_slug_product_cat( int $term_id ): string {
	$term = get_term( $term_id, 'product_cat' );
	return ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
}

/**
 * خدماتِ «اضافه خرید» از تنظیمات — با کلیدِ پایدارِ s0..s7 (ایندکس) تا
 * کلاینت فقط کلید را بفرستد و مبلغ/عنوان همیشه از سرور خوانده شود.
 */
function jluxe_pa_services(): array {
	$out = array();
	foreach ( (array) jluxe_get_setting( 'purchase_addons.services', array() ) as $i => $service ) {
		if ( ! is_array( $service ) || '' === (string) ( $service['title'] ?? '' ) ) {
			continue;
		}
		$out[ 's' . $i ] = array(
			'title'   => (string) $service['title'],
			'amount'  => (float) ( $service['amount'] ?? 0 ),
			'context' => in_array( (string) ( $service['context'] ?? 'modal' ), array( 'modal', 'cart', 'both' ), true ) ? (string) $service['context'] : 'modal',
			'auto'    => ! empty( $service['auto'] ),
		);
	}
	return $out;
}

/**
 * فِیِ خدماتِ انتخاب‌شده — کلاینت فقط «کلید» خدمات را POST می‌کند؛ مبلغ و
 * عنوان در همین‌جا از تنظیماتِ ذخیره‌شده خوانده می‌شود (بدونِ اعتماد به
 * POST). فِی در session سبد ثبت می‌شود و روی هر محاسبهٔ totals از هوکِ
 * رسمیِ ووکامرس اعمال می‌گردد؛ خالی‌شدنِ سبد، session را هم پاک می‌کند.
 */
function jluxe_pa_session_service_keys(): array {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return array();
	}
	$stored = WC()->session->get( 'jluxe_pa_services' );
	return is_array( $stored ) ? $stored : array();
}

function jluxe_pa_apply_service_fees(): void {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
		return;
	}
	$services = jluxe_pa_services();
	foreach ( jluxe_pa_session_service_keys() as $key ) {
		if ( isset( $services[ (string) $key ] ) ) {
			$service = $services[ (string) $key ];
			WC()->cart->add_fee( $service['title'], $service['amount'], false );
		}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'jluxe_pa_apply_service_fees' );

function jluxe_pa_clear_service_fees(): void {
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'jluxe_pa_services', array() );
	}
}
add_action( 'woocommerce_cart_emptied', 'jluxe_pa_clear_service_fees' );

/**
 * پاپ‌آپِ «اضافه خرید» — بعدِ کلیکِ موفقِ افزودن به سبد روی دکمه‌ی
 * اصلیِ صفحه‌ی تکی محصول با JS باز می‌شود (assets/js/woocommerce.js).
 * مطابقِ نمونهٔ مرجع: سربرگِ «افزودن به سبد خرید»، کارتِ خلاصهٔ محصول با
 * قیمتِ خط‌خورده/درصدِ تخفیف، بخشِ «این محصولات را هم اضافه کنید» با
 * ردیف‌های انتخابی (چک‌باکس)، خدماتِ قابل‌انتخاب، و پایین‌برگِ «مبلغ قابل
 * پرداخت» + دکمهٔ تأیید. انتخاب‌ها سمتِ مرورگر جمع می‌شوند و تأیید، فقط
 * کلیدِ خدمات + شناسهٔ محصولات را به endpointهای واقعی می‌فرستد.
 */
function jluxe_suggested_modal_html_for( WC_Product $product ): string {
	// کلیدِ سراسریِ «فعال‌سازی آیتم‌های اضافه خرید» (پنل زرین ← اضافه خرید).
	if ( ! jluxe_get_setting( 'purchase_addons.enabled', false ) ) {
		return '';
	}
	// چک‌باکسِ «پاپ‌آپ محصولات پیشنهادی» توی تبِ عمومیِ ویرایشِ همین محصول
	// (jluxe_render_suggested_modal_toggle_field) — پیش‌فرض روشنه، فقط
	// مقدارِ صریحِ 'no' خاموشش می‌کند.
	if ( 'no' === get_post_meta( $product->get_id(), '_jluxe_suggested_modal_enabled', true ) ) {
		return '';
	}

	$suggested = jluxe_get_suggested_products_for_cart( $product, 3 );
	$services  = jluxe_pa_services();
	$services  = array_filter( $services, fn( $s ) => in_array( $s['context'], array( 'modal', 'both' ), true ) );
	if ( empty( $suggested ) && empty( $services ) ) {
		return '';
	}

	/* خلاصهٔ قیمتِ محصولِ اصلی: در فروش (ساده) خط‌خورده + درصد + قیمتِ نهایی */
	$main_amount   = (float) $product->get_price( 'edit' );
	$main_regular  = (float) $product->get_regular_price( 'edit' );
	$main_discount = 0;
	if ( $main_regular > 0 && $main_amount > 0 && $main_amount < $main_regular ) {
		$main_discount = (int) round( ( $main_regular - $main_amount ) * 100 / $main_regular );
	}
	$main_price_html = $product->is_on_sale() && $main_discount > 0
		? '<del>' . wp_kses_post( wc_price( $main_regular ) ) . '</del><ins>' . wp_kses_post( wc_price( $main_amount ) ) . '</ins>'
		: wp_kses_post( $product->get_price_html() );

	$unit    = jluxe_currency_label( get_woocommerce_currency() );
	$total   = $main_amount > 0 ? $main_amount : 0;
	ob_start();
	?>
	<div class="jluxe-pa fixed inset-0 hidden" data-jluxe-suggested-modal aria-hidden="true" data-pa-unit="<?php echo esc_attr( $unit ); ?>" data-pa-main="<?php echo esc_attr( (string) $total ); ?>">
		<div class="jluxe-pa-backdrop" data-jluxe-suggested-close></div>
		<div class="jluxe-pa-sheet" role="dialog" aria-modal="true" aria-label="افزودن به سبد خرید">
			<header class="jluxe-pa-head">
				<span class="jluxe-pa-head-ic" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 8.5a2 2 0 0 1-2 1.5H8.6a2 2 0 0 1-2-1.6L4.6 3.7A1 1 0 0 0 3.6 3H2"/><circle cx="9.5" cy="20" r="1.5"/><circle cx="17.5" cy="20" r="1.5"/></svg>
				</span>
				<span class="jluxe-pa-head-title">افزودن به سبد خرید</span>
				<button type="button" data-jluxe-suggested-close aria-label="بستن" class="jluxe-pa-close">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</header>

			<div class="jluxe-pa-body">
				<div class="jluxe-pa-main">
					<span class="jluxe-pa-thumb">
						<?php $jluxe_pa_thumb = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ); ?>
						<?php if ( $jluxe_pa_thumb ) : ?>
							<img src="<?php echo esc_url( $jluxe_pa_thumb ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
						<?php endif; ?>
					</span>
					<span class="jluxe-pa-maininfo">
						<span class="jluxe-pa-mainname"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="jluxe-pa-mainprice">
							<?php if ( $main_discount > 0 ) : ?>
								<del><?php echo wp_kses_post( wc_price( $main_regular ) ); ?></del>
								<span class="jluxe-pa-badge"><?php echo esc_html( jluxe_fa_digits( $main_discount ) ); ?>٪</span>
							<?php endif; ?>
							<span class="jluxe-pa-now"><?php echo $main_price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</span>
					</span>
				</div>

				<?php if ( ! empty( $services ) ) : ?>
					<div class="jluxe-pa-secthead">
						<span>این خدمات را هم اضافه کنید</span>
						<small>اختیاری</small>
					</div>
					<div class="jluxe-pa-rows">
						<?php foreach ( $services as $jluxe_pa_key => $jluxe_pa_service ) : ?>
							<button type="button" class="jluxe-pa-row<?php echo $jluxe_pa_service['auto'] ? ' is-selected' : ''; ?>" data-pa-service="<?php echo esc_attr( $jluxe_pa_key ); ?>" data-pa-amount="<?php echo esc_attr( (string) $jluxe_pa_service['amount'] ); ?>" aria-pressed="<?php echo $jluxe_pa_service['auto'] ? 'true' : 'false'; ?>">
								<span class="jluxe-pa-rowinfo">
									<span class="jluxe-pa-name"><?php echo esc_html( $jluxe_pa_service['title'] ); ?></span>
									<span class="jluxe-pa-price"><?php echo wp_kses_post( wc_price( $jluxe_pa_service['amount'] ) ); ?></span>
								</span>
								<span class="jluxe-pa-check" aria-hidden="true">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $suggested ) ) : ?>
					<div class="jluxe-pa-secthead">
						<span>این محصولات را هم اضافه کنید</span>
						<small>اختیاری</small>
					</div>
					<div class="jluxe-pa-rows">
						<?php foreach ( $suggested as $sp ) :
							$sp_simple   = ! $sp->is_type( 'variable' ) && ! $sp->is_type( 'grouped' ) && ! $sp->is_type( 'external' );
							$sp_amount   = (float) $sp->get_price( 'edit' );
							$sp_thumb    = wp_get_attachment_image_url( $sp->get_image_id(), 'thumbnail' );
								?>
							<?php if ( $sp_simple && $sp_amount > 0 ) : ?>
								<button type="button" class="jluxe-pa-row" data-pa-product="<?php echo esc_attr( (string) $sp->get_id() ); ?>" data-pa-amount="<?php echo esc_attr( (string) $sp_amount ); ?>" aria-pressed="false">
									<span class="jluxe-pa-thumb">
										<?php if ( $sp_thumb ) : ?>
											<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
										<?php endif; ?>
									</span>
									<span class="jluxe-pa-rowinfo">
										<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
										<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
									</span>
									<span class="jluxe-pa-check" aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
									</span>
								</button>
							<?php else : ?>
								<?php if ( $sp->is_type( 'variable' ) ) : ?>
								<button type="button" class="jluxe-pa-row jluxe-pa-rowlink" data-jluxe-quick-variant="<?php echo esc_attr( (string) $sp->get_id() ); ?>">
									<span class="jluxe-pa-thumb">
										<?php if ( $sp_thumb ) : ?>
											<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
										<?php endif; ?>
									</span>
									<span class="jluxe-pa-rowinfo">
										<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
										<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
									</span>
									<span class="jluxe-pa-goto">انتخاب گزینه‌ها</span>
																</button>
								<?php else : ?>
								<a class="jluxe-pa-row jluxe-pa-rowlink" href="<?php echo esc_url( $sp->get_permalink() ); ?>">
									<span class="jluxe-pa-thumb">
										<?php if ( $sp_thumb ) : ?>
											<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
										<?php endif; ?>
									</span>
									<span class="jluxe-pa-rowinfo">
										<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
										<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
									</span>
									<span class="jluxe-pa-goto">انتخاب گزینه‌ها</span>
								</a>
								<?php endif; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

				<footer class="jluxe-pa-foot">
			<?php
			/* R64: فوترِ دو ردیفی — ردیفِ بالا: خلاصهٔ سبدِ واقعی + [مشاهده سبد]
			[ادامه خرید]؛ ردیفِ پایین: مبلغِ قابل پرداخت + دکمهٔ تأیید.
			موبایل: دکمهٔ تأیید تمام‌عرض. اعداد همیشه فارسی (سرور + JS). */
			$cart_count = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
			$cart_total = function_exists( 'WC' ) && WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
			$cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
			?>
			<div class="jluxe-pa-foot-top">
				<?php if ( $cart_count > 0 ) : ?>
					<span class="jluxe-pa-cartinfo">سبد شما: <?php echo esc_html( jluxe_fa_digits( $cart_count ) ); ?> کالا · <?php echo esc_html( jluxe_fa_digits( number_format( $cart_total, 0, '.', ',' ) ) ); ?> <?php echo esc_html( $unit ); ?></span>
				<?php endif; ?>
				<span class="jluxe-pa-foot-links">
					<?php if ( '' !== $cart_url ) : ?>
						<a class="jluxe-pa-viewcart" href="<?php echo esc_url( $cart_url ); ?>">مشاهده سبد</a>
					<?php endif; ?>
					<button type="button" class="jluxe-pa-continue" data-jluxe-suggested-close>ادامه خرید</button>
				</span>
			</div>
			<div class="jluxe-pa-foot-main">
				<span class="jluxe-pa-totalwrap">
					<small>مبلغ قابل پرداخت</small>
					<span class="jluxe-pa-totalrow">
						<strong class="jluxe-pa-total" data-pa-total><?php echo esc_html( jluxe_fa_digits( number_format( (float) $total, 0, '.', ',' ) ) ); ?></strong>
						<small><?php echo esc_html( $unit ); ?></small>
					</span>
				</span>
				<button type="button" class="jluxe-pa-confirm" data-pa-confirm>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
					<span>تأیید و افزودن به سبد</span>
				</button>
			</div>
			</footer>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** چاپِ مودال در تمپلیت‌ها — API قدیمی حفظ شد (R61: builder جدا شد تا endpoint هم از همان HTML استفاده کند). */
function jluxe_render_suggested_products_modal( WC_Product $product ): void {
	$html = jluxe_suggested_modal_html_for( $product );
	if ( '' !== $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزای داخلی escape شده‌اند.
	}
}

/**
 * تنظیمات «فروشگاه و دسته‌بندی» (jluxe_get_theme_settings()['shop']) —
 * تعداد محصول در صفحه و تعداد ستون از قلاب‌های واقعی خودِ ووکامرس رد می‌شن،
 * نه یک shortcode/کوئری موازی.
 */
function jluxe_shop_per_page( $per_page ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName, Squiz.Commenting.FunctionComment
	return (int) jluxe_get_setting( 'shop.products_per_page', $per_page );
}

/**
 * R64: تیکِ «ناموجودها همیشه انتهای لیست» (پنل زرین ← فروشگاه).
 *
 * یک فیلترِ مرکزیِ posts_clauses برای «همهٔ» کوئری‌های محصولِ فرانت —
 * آرشیو/دسته/جستجوی محصول، بخش‌های صفحهٔ اصلی، محصولاتِ مرتبط و پیشنهادی
 * (همه سرانجام WP_Query با post_type=product می‌شوند، شاملِ مسیرِ
 * wc_get_products خودِ ووکامرس). ناموجودها حذف نمی‌شوند؛ فقط بعدِ
 * موجودها می‌نشینند و ترتیبِ اصلی (تاریخ/محبوبیت/post__in) داخلِ هر گروه
 * حفظ می‌شود. ادمین (و AJAX ادمین) مستثناست؛ مقدارِ گمشدهٔ
 * _stock_status «موجود» فرض می‌شود تا رفتارِ محتاطانه باشد.
 */
function jluxe_out_of_stock_last_clauses( array $clauses, WP_Query $query ): array {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $clauses;
	}
	if ( ! jluxe_get_setting( 'shop.out_of_stock_last', true ) ) {
		return $clauses;
	}
	$post_type = $query->get( 'post_type' );
	if ( 'product' !== $post_type && ! ( is_array( $post_type ) && array( 'product' ) === $post_type ) ) {
		return $clauses;
	}
	global $wpdb;
	if ( false === strpos( (string) $clauses['join'], ' jluxe_oos ' ) ) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS jluxe_oos ON ({$wpdb->posts}.ID = jluxe_oos.post_id AND jluxe_oos.meta_key = '_stock_status')";
	}
	$base = trim( (string) $clauses['orderby'] );
	if ( '' === $base ) {
		$base = "{$wpdb->posts}.post_date DESC";
	}
	$clauses['orderby'] = "CASE WHEN COALESCE( jluxe_oos.meta_value, 'instock' ) = 'outofstock' THEN 1 ELSE 0 END ASC, " . $base;
	return $clauses;
}
add_filter( 'posts_clauses', 'jluxe_out_of_stock_last_clauses', 10, 2 );

/**
 * R69: پاراگرافِ «اطلاعات شخصی شما برای پردازش سفارش…» در صفحهٔ پرداخت
 * حذف شد (درخواستِ صریحِ کاربر) — فقط برای type=checkout؛ متن‌های ثبت‌نام
 * دست‌نخورده می‌مانند. اگر ادمین بعداً خواست برگردد: این فیلتر را بردارید.
 */
function jluxe_hide_checkout_privacy_text( string $text, string $type ): string {
	return 'checkout' === $type ? '' : $text;
}
add_filter( 'woocommerce_get_privacy_policy_text', 'jluxe_hide_checkout_privacy_text', 10, 2 );
add_filter( 'loop_shop_per_page', 'jluxe_shop_per_page', 20 );

function jluxe_shop_columns( $columns ) { // phpcs:ignore Squiz.Commenting.FunctionComment
	return (int) jluxe_get_setting( 'shop.columns_desktop', $columns );
}
add_filter( 'loop_shop_columns', 'jluxe_shop_columns' );

/**
 * چیدمانِ گرید واقعیِ کارت‌های محصول — کاملاً مستقل از CSS داخلیِ خودِ
 * ووکامرس. باگِ واقعی‌ای که پیدا شد: content-product.php این تم به‌جای
 * <li> (چیزی که سلکتور رسمی ووکامرس یعنی «.products li.product» انتظارش
 * رو داره) یک <div> با wc_product_class() می‌سازه؛ در نتیجه هیچ CSS
 * float/grid ای از woocommerce.css روی کارت‌ها اعمال نمی‌شه و همه‌ی
 * کارت‌ها تکی و تمام‌عرض (block پیش‌فرض مرورگر) زیر هم می‌افتن. راه‌حل:
 * یک گرید CSS واقعی و مستقل روی خودِ «.products» تعریف می‌کنیم که به تگِ
 * فرزند (li یا div) وابسته نیست — چون در Grid Formatting Context، فرزندان
 * مستقیم به‌صورت خودکار «آیتم گرید» می‌شن و float/width قدیمی‌شون بی‌اثر
 * می‌شه. همه‌جا چاپ می‌شه (نه فقط شاپ) چون همین کلاس «.products» توی
 * محصولات مرتبط/upsell/cross-sell هم استفاده می‌شه.
 */
function jluxe_output_shop_columns_css(): void {
	$desktop = max( 2, min( 6, (int) jluxe_get_setting( 'shop.columns_desktop', 4 ) ) );
	$tablet  = max( 2, min( 4, (int) jluxe_get_setting( 'shop.columns_tablet', 3 ) ) );
	$mobile  = max( 1, min( 3, (int) jluxe_get_setting( 'shop.columns_mobile', 2 ) ) );
	printf(
		'<style id="jluxe-shop-columns">
			ul.products{display:grid!important;grid-template-columns:repeat(%3$d,minmax(0,1fr))!important;gap:1rem!important;list-style:none!important;margin:0!important;padding:0!important}
			ul.products li.product,ul.products>.product{float:none!important;width:auto!important;margin:0!important}
			@media (min-width:600px){ ul.products{grid-template-columns:repeat(%2$d,minmax(0,1fr))!important} }
			@media (min-width:1024px){ ul.products{grid-template-columns:repeat(%1$d,minmax(0,1fr))!important} }
		</style>',
		$desktop,
		$tablet,
		$mobile
	);
}
add_action( 'wp_head', 'jluxe_output_shop_columns_css', 30 );

/**
 * رنگ‌های صفحه‌ی محصول (تنظیمات → صفحه محصول) — بج تخفیف/خط سود/ستاره‌ی
 * امتیاز قبلاً فقط با متغیرهای CSS ثابتِ src/styles/single-product-fallback.css
 * (--boom-sale/--boom-success/--boom-star) قابل تغییر بودن. این‌جا همون
 * متغیرها رو override می‌کنیم — چون کارت محصول/دیدگاه‌ها هم از همین
 * کلاس‌ها (bg-boom-sale، text-boom-success، text-boom-star) استفاده
 * می‌کنن، بدون نیاز به تغییرِ هر تمپلیت این تنظیم همه‌جا اعمال می‌شه.
 */
function jluxe_output_product_page_colors_css(): void {
	$pp = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['product_page'] : array();
	printf(
		'<style id="jluxe-product-page-colors">:root{--boom-sale:%1$s;--boom-success:%2$s;--boom-star:%3$s;}</style>',
		esc_attr( $pp['discount_color'] ?? '#ef4056' ),
		esc_attr( $pp['savings_color'] ?? '#00a049' ),
		esc_attr( $pp['star_color'] ?? '#f7b731' )
	);
}
add_action( 'wp_head', 'jluxe_output_product_page_colors_css', 30 );

/**
 * نوار بالای گرید محصولات («نمایش X از Y نتیجه» + مرتب‌سازی). پیش‌فرضِ
 * ووکامرس این دو هوک رو جدا و تمام‌عرض زیر هم چاپ می‌کنه (فاصله‌ی خالی
 * زیاد در دسکتاپ). این‌جا هر دو رو حذف و در یک ردیف flex کنارِ هم دوباره
 * چاپ می‌کنیم؛ ارقامِ «نمایش X از Y» هم به فارسی تبدیل می‌شن چون خودِ
 * ووکامرس فقط متن رو ترجمه می‌کنه، رقم‌ها رو نه (طبق jluxe_fa_digits بالا).
 */
function jluxe_shop_toolbar_hooks(): void {
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	add_action( 'woocommerce_before_shop_loop', 'jluxe_render_shop_toolbar', 25 );
}
add_action( 'wp', 'jluxe_shop_toolbar_hooks' );

/*
 * R80: این تابع در 1.68.0 «کدِ مرده» تشخیص داده و حذف شد، ولی روی سایتِ
 * فعال معلوم شد فایلِ قالبِ کارتِ محصول (و شاید اسنیپت/افزونه‌ای) نسخهٔ
 * قدیمی‌تری دارد که همین تابع را صدا می‌زند → حذفش یعنی «خطای مهم» در هر
 * حلقهٔ محصول. سیاستِ تازه: توابعِ عمومیِ پوسته حتی اگر داخلِ خودِ پوسته
 * مصرف نداشته باشند، حذف نمی‌شوند — فقط back-compat می‌مانند. (تستِ R80
 * هر دو را به‌عنوان قراردادِ سازگاری قفل می‌کند.)
 */
/**
 * [سازگاریِ عقب‌رو — از 1.68.0 حذف شد و در 1.69.0 برگشت]
 * خط تعداد موجودیِ واقعی (نه عدد ساختگی) — فقط وقتی محصول/تنوع واقعاً
 * موجودیِ عددی رو مدیریت می‌کنه (managing_stock) چیزی نشون داده می‌شه؛
 * وگرنه (فقط وضعیت موجود/ناموجودِ ساده) خط خالی برمی‌گرده. زیر ۵ عدد
 * رنگ هشدار می‌گیره.
 */
function jluxe_render_product_stock_line( $product ): string {
	if ( ! $product || ! $product->managing_stock() ) {
		return '';
	}
	$qty = $product->get_stock_quantity();
	if ( null === $qty ) {
		return '';
	}
	$low  = $qty <= 5;
	$icon = '<svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>';
	$text = $low
		? sprintf( 'فقط %s عدد در انبار باقی مانده!', jluxe_fa_digits( (string) $qty ) )
		: sprintf( '%s عدد در انبار موجود است', jluxe_fa_digits( (string) $qty ) );

	return sprintf(
		'<p class="mt-1.5 flex items-center gap-1 text-[11px] font-medium %1$s">%2$s%3$s</p>',
		$low ? 'jluxe-text-danger' : 'text-text-muted',
		$icon,
		esc_html( $text )
	);
}

/**
 * فیلترِ «فقط کالاهای موجود» — برخلاف min_price/max_price که خودِ ووکامرس
 * از GET param می‌خونه، وضعیتِ موجودی همچین پارامتری نداره؛ این‌جا با
 * ?filter_stock=instock دستی اضافه‌ش می‌کنیم.
 */
function jluxe_filter_stock_query( WP_Query $query ): void {
	$is_product_listing = $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) );
	if ( is_admin() || ! $query->is_main_query() || ! $is_product_listing ) {
		return;
	}
	if ( ! isset( $_GET['filter_stock'] ) || 'instock' !== $_GET['filter_stock'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$meta_query   = $query->get( 'meta_query' ) ?: array();
	$meta_query[] = array(
		'key'   => '_stock_status',
		'value' => 'instock',
	);
	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'jluxe_filter_stock_query' );

function jluxe_render_shop_toolbar(): void {
	ob_start();
	if ( function_exists( 'woocommerce_result_count' ) ) {
		woocommerce_result_count();
	}
	$result_count = jluxe_fa_digits( ob_get_clean() );

	ob_start();
	if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
		woocommerce_catalog_ordering();
	}
	$ordering = ob_get_clean();

	// وضعیت فعلیِ فیلترها — برای این‌که پنل موقع بازشدن دقیقاً همون چیزی رو
	// نشون بده که الان واقعاً اعمال شده (نه همیشه خالی).
	$current_cat_id   = 0;
	$current_brand_id = 0;
	$queried          = get_queried_object();
	if ( $queried instanceof WP_Term ) {
		if ( 'product_cat' === $queried->taxonomy ) {
			$current_cat_id = $queried->term_id;
		} elseif ( 'product_brand' === $queried->taxonomy ) {
			$current_brand_id = $queried->term_id;
		}
	}
	foreach ( array( 'product_cat', 'product_brand' ) as $taxonomy ) {
		if ( isset( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) && taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ), $taxonomy );
			if ( $term ) {
				if ( 'product_cat' === $taxonomy ) { $current_cat_id = $term->term_id; }
				else { $current_brand_id = $term->term_id; }
			}
		}
	}
	$current_min_price = isset( $_GET['min_price'] ) && is_scalar( $_GET['min_price'] ) ? wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) : '';
	$current_max_price = isset( $_GET['max_price'] ) && is_scalar( $_GET['max_price'] ) ? wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) : '';
	$current_on_sale = isset( $_GET['on_sale'] ) && '1' === $_GET['on_sale'];

	$current_in_stock  = isset( $_GET['filter_stock'] ) && 'instock' === $_GET['filter_stock']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$cat_terms   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	$cat_terms   = is_wp_error( $cat_terms ) ? array() : $cat_terms;
	$brand_terms = taxonomy_exists( 'product_brand' ) ? get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => true ) ) : array();
	$brand_terms = is_wp_error( $brand_terms ) ? array() : $brand_terms;
	$has_filters = $current_cat_id || $current_brand_id || $current_min_price || $current_max_price || $current_in_stock || $current_on_sale;
	?>
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
		<button type="button" data-jluxe-filter-toggle class="relative flex h-11 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-caption font-medium text-foreground transition-all hover:bg-muted active:scale-95">
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
			فیلتر محصولات
			<?php if ( $has_filters ) : ?><span class="absolute -end-1 -top-1 size-2.5 rounded-full bg-primary" aria-hidden="true"></span><?php endif; ?>
		</button>
		<div class="flex items-center gap-3">
			<div class="text-caption text-text-muted"><?php echo $result_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="jluxe-shop-sort relative inline-flex items-center">
				<?php echo $ordering; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<svg class="pointer-events-none absolute end-3 size-3.5 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
			</div>
		</div>
	</div>

	<?php
	/*
	 * پنل فیلتر — یک پاپ‌آپ/مودال واقعی (نه یک جعبه‌ی درازِ inline بالای
	 * گرید). چون jluxe_render_shop_toolbar بیرونِ هدر رندر می‌شه (نه
	 * داخلش)، مشکلِ containing-block ناشیِ از backdrop-filter هدر این‌جا
	 * مطرح نیست — پس نیازی به React portal نداره، یک fixed ساده کافیه.
	 */
	?>
	<?php /* flex/justify-start عمداً استاتیک نیست، JS با باز/بستن اضافه/حذفش می‌کنه — چون Tailwind این کلاس‌ها رو در لایه‌ی utilities تولید می‌کنه که همیشه روی [hidden] (لایه‌ی base) برنده می‌شه، یعنی اگه استاتیک باشن panel با وجود hidden=true همیشه display:flex می‌مونه (باگ واقعی: پاپ‌آپ نه باز می‌شد نه بسته). */ ?>
	<div data-jluxe-filter-panel hidden class="fixed inset-0 z-50">
		<div data-jluxe-filter-close class="jluxe-filter-backdrop absolute inset-0 bg-foreground/50 backdrop-blur-[1px]" aria-hidden="true"></div>
		<div data-jluxe-filter-drawer class="jluxe-filter-drawer relative flex h-full w-full max-w-sm flex-col bg-surface shadow-2xl">
			<div class="flex items-center justify-between border-b border-border p-4">
				<button type="button" data-jluxe-filter-close aria-label="بستن" class="grid size-9 place-items-center rounded-lg text-text-muted transition-all hover:bg-muted active:scale-90 active:bg-muted">
					<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
				<h2 class="flex items-center gap-2 text-body font-bold text-foreground">
					فیلترها
					<svg class="size-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
				</h2>
			</div>

			<form data-jluxe-filter-form class="flex flex-1 flex-col overflow-hidden">
				<div class="flex-1 overflow-y-auto px-4">
					<?php if ( $has_filters ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="mt-3 inline-block text-caption font-medium text-primary hover:text-primary-hover">حذف فیلترها</a>
					<?php endif; ?>

					<details class="group border-b border-border py-3.5" open>
						<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
							محدوده قیمت (<?php echo esc_html( jluxe_currency_label( get_woocommerce_currency() ) ); ?>)
							<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</summary>
						<div class="mt-3 flex items-center gap-2">
							<input type="text" inputmode="decimal" aria-label="حداقل قیمت" name="min_price" value="<?php echo esc_attr( $current_min_price ); ?>" placeholder="حداقل" class="h-11 w-full min-w-0 rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none" />
							<span class="shrink-0 text-text-muted">تا</span>
							<input type="text" inputmode="decimal" aria-label="حداکثر قیمت" name="max_price" value="<?php echo esc_attr( $current_max_price ); ?>" placeholder="حداکثر" class="h-11 w-full min-w-0 rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none" />
						</div>
					</details>

					<details class="group border-b border-border py-3.5" <?php echo $current_cat_id ? 'open' : ''; ?>>
						<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
							دسته‌بندی
							<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</summary>
						<select aria-label="دسته‌بندی محصول" name="filter_cat" class="mt-3 h-11 w-full rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none">
							<option value="">همه‌ی دسته‌ها</option>
							<?php foreach ( $cat_terms as $term ) :
								$term_link = get_term_link( $term );
								if ( is_wp_error( $term_link ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" data-url="<?php echo esc_url( $term_link ); ?>" <?php selected( $current_cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</details>

					<?php if ( ! empty( $brand_terms ) ) : ?>
						<details class="group border-b border-border py-3.5" <?php echo $current_brand_id ? 'open' : ''; ?>>
							<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
								برند
								<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
							</summary>
							<select aria-label="برند محصول" name="filter_brand" class="mt-3 h-11 w-full rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none">
								<option value="">همه‌ی برندها</option>
								<?php foreach ( $brand_terms as $term ) :
									$term_link = get_term_link( $term );
									if ( is_wp_error( $term_link ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $term->slug ); ?>" data-url="<?php echo esc_url( $term_link ); ?>" <?php selected( $current_brand_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</details>
					<?php endif; ?>

					<div class="py-3.5">
						<label class="flex h-11 cursor-pointer items-center justify-between rounded-lg border border-border px-3 text-small">
							فقط محصولات تخفیف‌دار
							<input type="checkbox" name="on_sale" value="1" <?php checked( $current_on_sale ); ?> class="size-4 accent-primary" />
						</label>
					</div>
					<div class="py-3.5">
						<label class="flex h-11 cursor-pointer items-center justify-between rounded-lg border border-border px-3 text-small text-foreground transition-colors hover:border-primary/40">
							فقط کالاهای موجود
							<input type="checkbox" name="filter_stock" value="instock" <?php checked( $current_in_stock ); ?> class="size-4 accent-primary" />
						</label>
					</div>
				</div>

				<div class="border-t border-border p-4">
					<button type="submit" class="flex h-12 w-full items-center justify-center rounded-xl bg-primary text-button font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.98]">اعمال فیلترها</button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

/**
 * دو بج‌ِ اعتماد زیرِ عنوانِ صفحه‌ی محصول («گارانتی اصالت کالا» و «کالای
 * دارای ضمانت») — قبلاً «گارانتی اصالت کالا» برای همه‌ی محصولات به‌صورت
 * ثابت نمایش داده می‌شد (باگِ واقعیِ گزارش‌شده: باید فقط برای محصولاتی که
 * واقعاً همچین ضمانتی دارن قابل‌انتخاب باشه، نه ثابت روی همه). این‌جا دو
 * چک‌باکس توی تبِ «عمومی»ِ ویرایشِ محصولِ ووکامرس اضافه می‌شه؛ خروجی توی
 * woocommerce/content-single-product.php با jluxe_get_product_trust_badges()
 * خونده می‌شه.
 */
function jluxe_render_product_badge_fields(): void {
	global $post;

	echo '<div class="options_group">';

	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_badge_authenticity',
			'value'       => get_post_meta( $post->ID, '_jluxe_badge_authenticity', true ),
			'label'       => 'گارانتی اصالت کالا',
			'description' => 'روی صفحه‌ی محصول، زیرِ عنوان، یک بج سبز «گارانتی اصالت کالا» نمایش داده می‌شه.',
		)
	);

	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_badge_warranty',
			'value'       => get_post_meta( $post->ID, '_jluxe_badge_warranty', true ),
			'label'       => 'کالای دارای ضمانت',
			'description' => 'روی صفحه‌ی محصول، زیرِ عنوان، یک بج «کالای دارای ضمانت» نمایش داده می‌شه.',
		)
	);

	echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'jluxe_render_product_badge_fields' );

/**
 * ذخیره‌ی دو چک‌باکسِ بالا. woocommerce_wp_checkbox() خودش مقدار رو
 * ('yes'/'no') می‌خونه؛ چون چک‌باکس‌های خاموش اصلاً توی $_POST نمیان،
 * نبودشون یعنی 'no'.
 */
function jluxe_save_product_badge_fields( int $post_id ): void {
	update_post_meta( $post_id, '_jluxe_badge_authenticity', isset( $_POST['_jluxe_badge_authenticity'] ) ? 'yes' : 'no' );
	update_post_meta( $post_id, '_jluxe_badge_warranty', isset( $_POST['_jluxe_badge_warranty'] ) ? 'yes' : 'no' );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_badge_fields' );

/**
 * چک‌باکسِ روشن/خاموش‌کردنِ دستیِ پاپ‌آپِ «محصولات پیشنهادی» برایِ هر
 * محصول جداگونه — طبقِ درخواستِ صریحِ کاربر. برخلافِ دو بجِ بالا، این‌جا
 * پیش‌فرض (وقتی متا هنوز اصلاً ذخیره نشده، یعنی محصولاتِ موجود قبل از این
 * قابلیت) باید «روشن» باشه — وگرنه همون لحظه‌ی آپلود، پاپ‌آپ برایِ همه‌ی
 * محصولاتِ موجود خاموش می‌شد (رگرسیون). برایِ همین مقدارِ چک‌باکس صریح
 * محاسبه می‌شه: فقط وقتی متا دقیقاً 'no'ه غیرفعال نشون داده می‌شه.
 */
function jluxe_render_suggested_modal_toggle_field(): void {
	global $post;

	$stored  = get_post_meta( $post->ID, '_jluxe_suggested_modal_enabled', true );
	$checked = 'no' !== $stored ? 'yes' : '';

	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_suggested_modal_enabled',
			'value'       => $checked,
			'label'       => 'پاپ‌آپ محصولات پیشنهادی',
			'description' => 'بعدِ افزودنِ این محصول به سبد خرید (از صفحه‌ی تکیِ محصول)، پاپ‌آپِ «شاید این‌ها رو هم بپسندید» نشون داده بشه. اگه محصولاتِ پیشنهادیِ واقعی (کراس‌سل یا هم‌دسته) نداشته باشه، پاپ‌آپ به‌هرحال نمایش داده نمی‌شه.',
		)
	);
	echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'jluxe_render_suggested_modal_toggle_field' );

function jluxe_save_suggested_modal_toggle_field( int $post_id ): void {
	update_post_meta( $post_id, '_jluxe_suggested_modal_enabled', isset( $_POST['_jluxe_suggested_modal_enabled'] ) ? 'yes' : 'no' );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_suggested_modal_toggle_field' );

/**
 * R77 (گزارشِ کاربر: «هیچ بخشی برای انتخابِ محصولِ پیشنهادی در ویرایش
 * محصول نیست»): متاباکسِ اختصاصیِ «محصولات پیشنهادی (اضافه خرید)» —
 * انتخابِ دستیِ پیشنهادهای پاپ‌آپِ «شاید این‌ها را هم بپسندید» برایِ همین
 * محصول، با جستجوی استانداردِ خودِ ووکامرس (wc-product-search) و بدونِ
 * وابستگی به فیلدِ پنهانِ «فروش متقاطع». سوییچِ روشن/خاموشیِ پاپ‌آپ هم
 * همین‌جا تکرار شده (همان متا) تا همه‌چیز یک‌جا دیده شود.
 */
function jluxe_add_suggested_products_metabox(): void {
	add_meta_box(
		'jluxe-suggested-products',
		'محصولات پیشنهادی (اضافه خرید)',
		'jluxe_render_suggested_products_metabox',
		'product',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'jluxe_add_suggested_products_metabox' );

function jluxe_render_suggested_products_metabox(): void {
	global $post;
	if ( ! $post || 'product' !== ( $post->post_type ?? '' ) ) { return; }
	$ids   = array_map( 'absint', (array) get_post_meta( $post->ID, '_jluxe_suggested_ids', true ) );
	$value = array();
	foreach ( $ids as $pid ) {
		$title = get_the_title( $pid );
		$value[] = array( 'id' => $pid, 'text' => '' !== (string) $title ? (string) $title : ( '#' . $pid ) );
	}
	?>
	<div style="padding:12px">
		<p style="margin:0 0 10px;line-height:1.9;font-size:12px;color:#646970">
			محصولاتِ پاپ‌آپِ «شاید این‌ها را هم بپسندید» (بعدِ افزودن به سبد) را برای همین محصول انتخاب کنید. خالی بگذارید: «فروش متقاطع» و بعدِ آن هم‌دسته‌ها پیشنهاد می‌شوند.
		</p>
		<label class="screen-reader-text" for="_jluxe_suggested_ids">محصولات پیشنهادی</label>
		<input type="hidden" id="_jluxe_suggested_ids" name="_jluxe_suggested_ids" class="wc-product-search" data-multiple="true" data-action="woocommerce_json_search_products" data-placeholder="جستجوی محصول…" data-allow_clear="true" value="<?php echo esc_attr( (string) wp_json_encode( $value ) ); ?>" style="width:100%" />
		<label style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:12px">
			<input type="checkbox" name="_jluxe_suggested_modal_enabled" value="1" <?php checked( 'no' !== get_post_meta( $post->ID, '_jluxe_suggested_modal_enabled', true ) ); ?> />
			نمایشِ پاپ‌آپ برای این محصول
		</label>
		<p style="margin:8px 0 0;font-size:11px;color:#8c8f94">در حالتِ «ثابت»ِ اضافه‌خرید، فهرستِ سراسری به‌کار می‌رود و این انتخاب نادیده گرفته می‌شود.</p>
	</div>
	<?php
}

function jluxe_save_suggested_products_metabox( int $post_id ): void {
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$raw     = isset( $_POST['_jluxe_suggested_ids'] ) ? (string) wp_unslash( $_POST['_jluxe_suggested_ids'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ووکامرس خودش nonce ذخیرهٔ محصول را وارسی می‌کند.
	$decoded = json_decode( $raw, true );
	$ids     = array();
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $row ) {
			$pid = is_array( $row ) ? absint( $row['id'] ?? 0 ) : absint( $row );
			if ( $pid > 0 ) { $ids[] = $pid; }
		}
	}
	$ids = array_slice( array_values( array_unique( $ids ) ), 0, 12 );
	if ( empty( $ids ) ) {
		delete_post_meta( $post_id, '_jluxe_suggested_ids' );
		return;
	}
	update_post_meta( $post_id, '_jluxe_suggested_ids', $ids );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_suggested_products_metabox' );

/**
 * بج‌های واقعاً فعالِ یک محصول، آماده برای رندر — woocommerce/
 * content-single-product.php این رو صدا می‌زنه به‌جای چک‌کردن get_post_meta
 * دوبار با استایل‌های تکراری.
 *
 * @return array<int, array{label: string, text_class: string, bg_class: string}>
 */
function jluxe_get_product_trust_badges( int $product_id ): array {
	$badges = array();

	if ( 'yes' === get_post_meta( $product_id, '_jluxe_badge_authenticity', true ) ) {
		$badges[] = array(
			'label'      => 'گارانتی اصالت کالا',
			'text_class' => 'text-boom-success',
			'bg_class'   => 'bg-boom-success-soft',
		);
	}

	if ( 'yes' === get_post_meta( $product_id, '_jluxe_badge_warranty', true ) ) {
		$badges[] = array(
			'label'      => 'کالای دارای ضمانت',
			'text_class' => 'text-boom-info',
			'bg_class'   => 'bg-boom-info-soft',
		);
	}

	return $badges;
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
add_filter( 'woocommerce_account_menu_items', 'jluxe_maybe_remove_account_downloads_tab' );

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
add_filter( 'wc_add_to_cart_message_html', 'jluxe_suppress_ajax_add_to_cart_notice' );

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
add_filter( 'woocommerce_add_message', 'jluxe_suppress_cart_updated_notice' );

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
add_filter( 'woocommerce_add_message', 'jluxe_suppress_cart_item_removed_notice' );

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
add_action( 'wp', 'jluxe_remove_empty_cart_default_message' );

/**
 * معیارهای امتیازِ دیدگاه — پورتِ فیچرِ پوسته‌ی قبلی (طبقِ درخواستِ صریحِ
 * کاربر). داده‌ها روی خودِ کامنت (به‌عنوانِ متایِ کامنت، نه یک جدولِ جدا)
 * ذخیره می‌شن — همون الگویِ خودِ ووکامرس برایِ متایِ rating.
 */

/**
 * ذخیره‌سازیِ امتیازهای هر معیار موقعِ ثبتِ دیدگاه — روی همون هوکِ
 * comment_post که خودِ ووکامرس هم برایِ ذخیره‌ی rating اصلی استفاده می‌کنه؛
 * فقط برای دیدگاه‌های واقعیِ محصول (comment_type='review'، توسطِ ووکامرس
 * موقعِ ارسالِ فرمِ روی صفحه‌ی محصول ست می‌شه).
 */
function jluxe_save_review_criteria_ratings( int $comment_id, $comment_approved ): void {
	if ( empty( $_POST['jluxe_review_criteria'] ) || ! is_array( $_POST['jluxe_review_criteria'] ) ) {
		return;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'review' !== $comment->comment_type ) {
		return;
	}

	$criteria = jluxe_get_theme_settings()['review_criteria']['items'];
	$valid_keys = wp_list_pluck( $criteria, 'key' );

	$clean = array();
	foreach ( wp_unslash( $_POST['jluxe_review_criteria'] ) as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$key = sanitize_key( $key );
		if ( ! in_array( $key, $valid_keys, true ) ) {
			continue; // فقط معیارهایِ واقعاً تعریف‌شده — جلوگیری از تزریقِ کلیدِ دلخواه.
		}
		$value = is_scalar( $value ) ? trim( jluxe_ascii_digits( (string) $value ) ) : '';
		$rating = (int) $value;
		if ( ! preg_match( '/^[1-5]$/D', $value ) ) {
			continue;
		}
		$clean[ $key ] = $rating;
	}

	if ( ! empty( $clean ) ) {
		update_comment_meta( $comment_id, '_jluxe_review_criteria', $clean );
	}
}
add_action( 'comment_post', 'jluxe_save_review_criteria_ratings', 10, 2 );

/**
 * ستاره‌های ورودی برایِ هر معیار — یک ردیف رادیو با ترفندِ CSS خالص
 * (چیدمانِ معکوس + سیبلینگِ :checked~label، بدونِ نیاز به هیچ جاوااسکریپت)
 * که در globals.css پیاده شده (.jluxe-review-criteria-stars).
 */
function jluxe_render_review_criteria_inputs(): string {
	$criteria = jluxe_get_theme_settings()['review_criteria']['items'];
	if ( empty( $criteria ) ) {
		return '';
	}

	$html = '<div class="jluxe-review-criteria-fields">';
	foreach ( $criteria as $item ) {
		$field_id = 'jluxe-crit-' . esc_attr( $item['key'] );
		$html    .= '<fieldset class="jluxe-review-criteria-row">';
		$html    .= '<legend class="jluxe-review-criteria-label">' . esc_html( $item['label'] ) . '</legend>';
		$html    .= '<div class="jluxe-review-criteria-stars" dir="ltr">';
		for ( $i = 5; $i >= 1; $i-- ) {
			$input_id = $field_id . '-' . $i;
			$html    .= '<input type="radio" id="' . esc_attr( $input_id ) . '" name="jluxe_review_criteria[' . esc_attr( $item['key'] ) . ']" value="' . esc_attr( (string) $i ) . '" />';
			$html    .= '<label for="' . esc_attr( $input_id ) . '" aria-label="' . esc_attr( (string) $i ) . ' از ۵">★</label>';
		}
		$html .= '</div></fieldset>';
	}
	$html .= '</div>';

	return $html;
}




/** Apply the sale flag to the main WooCommerce product query, not unrelated queries. */
function jluxe_filter_sale_query( $query ): void {
	if ( is_admin() || ! isset( $_GET['on_sale'] ) || '1' !== $_GET['on_sale'] ) {
		return;
	}
	$sale_ids = array_map( 'absint', wc_get_product_ids_on_sale() );
	$existing = $query->get( 'post__in' );
	$ids = $existing ? array_values( array_intersect( (array) $existing, $sale_ids ) ) : $sale_ids;
	$query->set( 'post__in', $ids ?: array( 0 ) );
}
add_action( 'woocommerce_product_query', 'jluxe_filter_sale_query' );
