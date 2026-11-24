<?php
defined( 'ABSPATH' ) || exit;

/** Toman currency, price formatting, Persian digits and SVG support. */

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
	$tags['use'] = array(
		'href' => true,
	);
	return $tags;
}

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

/** A compact reference for the single frontend sprite; AJAX/REST/admin output stays self-contained. */
function jluxe_toman_display_icon_svg( string $class = '', string $width = '0.85em', string $height = '0.7em' ): string {
	if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return jluxe_toman_icon_svg();
	}
	$class_attribute = '' !== trim( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';
	return '<svg xmlns="http://www.w3.org/2000/svg" width="' . esc_attr( $width ) . '" height="' . esc_attr( $height ) . '" viewBox="0 0 22 18" fill="none" aria-hidden="true" style="display:inline-block;vertical-align:-0.05em;margin-inline-end:0.2em"' . $class_attribute . '><use href="#jlx-toman-icon"></use></svg>';
}

/** Print the reusable money glyph once; all normal frontend prices can reference it. */
function jluxe_output_toman_icon_sprite(): void {
	if ( is_admin() ) {
		return;
	}
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	$icon     = jluxe_toman_icon_svg();
	$open_end = strpos( $icon, '>' );
	$paths    = false === $open_end ? '' : substr( $icon, $open_end + 1, -6 );
	if ( '' === $paths ) {
		return;
	}
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden"><symbol id="jlx-toman-icon" viewBox="0 0 22 18">' . $paths . '</symbol></svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG paths from the theme.
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
		return jluxe_toman_display_icon_svg();
	}
	return $symbol;
}

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

/**
 * جداکننده‌ی هزارگان با ویرگول («۳,۶۴۵,۰۰۰») طبق درخواستِ صریحِ کاربر —
 * مستقیم فیلتر می‌شه (نه فقط تنظیماتِ wc-settings→General→Currency)، هم‌راستا
 * با الگوی toman_price_format بالا (فرم ادمینِ ووکامرس این تنظیمات رو
 * قابل‌اعتماد ذخیره نمی‌کنه).
 */
function jluxe_price_thousand_separator(): string {
	return ',';
}

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
	$icon = jluxe_toman_display_icon_svg();
	if ( '' !== $icon && false !== strpos( $formatted_price, $icon ) ) {
		$placeholder      = '{{JLUXE_TOMAN_ICON}}';
		$formatted_price  = str_replace( $icon, $placeholder, $formatted_price );
		$formatted_price  = jluxe_fa_digits( $formatted_price );
		return str_replace( $placeholder, $icon, $formatted_price );
	}
	return jluxe_fa_digits( $formatted_price );
}

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

/** گلیفِ SVG تومان — دقیقاً همان مارک‌آپ مرجعی که کاربر معیار قرار داد (viewBox 22x18، fill ثابت). */
function jluxe_toman_glyph_svg(): string {
	return jluxe_toman_display_icon_svg();
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
	$allowed['svg'] = array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'aria-hidden' => true, 'id' => true, 'clip-path' => true, 'style' => true, 'class' => true );
	$allowed['use'] = array( 'href' => true );
	$allowed['path'] = array( 'id' => true, 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true );
	$allowed['g'] = array( 'id' => true, 'fill' => true, 'clip-path' => true );
	$allowed['defs'] = array();
	$allowed['clippath'] = array( 'id' => true );
	$allowed['rect'] = array( 'width' => true, 'height' => true, 'fill' => true, 'transform' => true );
	return wp_kses( $html, $allowed );
}

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
