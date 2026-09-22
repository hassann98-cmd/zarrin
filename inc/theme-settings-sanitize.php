<?php
/**
 * توابع sanitize — یکی به‌ازای هر بخش از jluxe_theme_settings. هر تابع
 * $posted (از $_POST['section'][...]) و $defaults[section] رو می‌گیره و
 * یک آرایه‌ی تمیز و امن برمی‌گردونه. هیچ‌کدوم مستقیم به دیتابیس نمی‌نویسن —
 * فقط sanitize/validate؛ نوشتن با update_option در theme-settings-admin.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * پاک‌سازیِ SVG سفارشی — طبق درخواستِ کاربر («برای هر آیتم بشه یک آیکونِ
 * SVG دلخواه تعریف کرد») علاوه‌بر مجموعه‌ی ثابتِ jluxe_nav_icon_options()
 * حالا هر آیتمی (منوی هدر، لینک فوتر، شبکه‌ی اجتماعی) می‌تونه کدِ SVG خامِ
 * خودش رو هم داشته باشه. چون این ورودی از فرمِ ادمین (فقط manage_options)
 * میاد نه کاربر نهایی، ریسکش محدوده؛ با این‌حال با wp_kses و یک whitelistِ
 * استانداردِ تگ/اتریبیوتِ SVG (نه allow_html کامل) پاک‌سازی می‌شه تا
 * <script>/event handler یا تگِ غیرمرتبط قاطی نشه.
 */
function jluxe_sanitize_svg_markup( string $raw ): string {
	$raw = trim( $raw );
	if ( '' === $raw || false === stripos( $raw, '<svg' ) ) {
		return '';
	}
	$allowed = array(
		'svg'      => array(
			'xmlns'       => true,
			'viewbox'     => true,
			'viewBox'     => true,
			'width'       => true,
			'height'      => true,
			'fill'        => true,
			'stroke'      => true,
			'stroke-width' => true,
			'stroke-linecap' => true,
			'stroke-linejoin' => true,
			'class'       => true,
			'aria-hidden' => true,
		),
		/*
		 * fill-rule/clip-rule طبقِ درخواستِ کاربر (آیکون‌های واقعیِ برندِ
		 * تلگرام/واتس‌اپ/روبیکا/بله) اضافه شدن — این آیکون‌ها برای بریدنِ
		 * حفره‌های داخلیِ شکل (مثلاً دایره‌ی توخالیِ وسطِ لوگوی واتس‌اپ) به
		 * fill-rule="evenodd" روی خودِ <path> نیاز دارن؛ بدونِ این دو
		 * attribute، wp_kses همین‌جا حذفشون می‌کرد و شکل به‌جای توخالی، پر
		 * رندر می‌شد (باگِ واقعی که با بررسیِ دقیقِ SVGهای ارسالی پیدا شد).
		 */
		'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true ),
		'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
		'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
		'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
		'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		'g'        => array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true ),
		'defs'     => array(),
		'title'    => array(),
	);
	$clean = wp_kses( $raw, $allowed );
	return false !== stripos( $clean, '<svg' ) ? $clean : '';
}

function jluxe_sanitize_colors( array $posted, array $defaults ): array {
	$out = array();
	foreach ( array_keys( $defaults ) as $key ) {
		$raw       = isset( $posted[ $key ] ) ? sanitize_text_field( $posted[ $key ] ) : '';
		$valid     = sanitize_hex_color( $raw );
		$out[ $key ] = $valid ? $valid : $defaults[ $key ];
	}
	return $out;
}

function jluxe_sanitize_identity( array $posted, array $defaults ): array {
	return array(
		'logo_id'            => isset( $posted['logo_id'] ) ? absint( $posted['logo_id'] ) : $defaults['logo_id'],
		'mobile_logo_id'     => isset( $posted['mobile_logo_id'] ) ? absint( $posted['mobile_logo_id'] ) : $defaults['mobile_logo_id'],
		'favicon_id'         => isset( $posted['favicon_id'] ) ? absint( $posted['favicon_id'] ) : $defaults['favicon_id'],
		'site_name'          => isset( $posted['site_name'] ) ? sanitize_text_field( $posted['site_name'] ) : $defaults['site_name'],
		'short_description'  => isset( $posted['short_description'] ) ? sanitize_textarea_field( $posted['short_description'] ) : $defaults['short_description'],
	);
}

function jluxe_sanitize_urls( array $posted, array $defaults ): array {
	$out = array();
	foreach ( array_keys( $defaults ) as $key ) {
		$value = isset( $posted[ $key ] ) ? sanitize_text_field( trim( (string) $posted[ $key ] ) ) : '';
		$out[ $key ] = ( '' !== $value && preg_match( '#^(?:/|https?://)#i', $value ) ) ? $value : $defaults[ $key ];
	}
	return $out;
}

function jluxe_sanitize_typography( array $posted, array $defaults ): array {
	$base_size = isset( $posted['base_size'] ) ? absint( $posted['base_size'] ) : $defaults['base_size'];
	$weight    = isset( $posted['heading_weight'] ) ? absint( $posted['heading_weight'] ) : $defaults['heading_weight'];
	$line      = isset( $posted['line_height'] ) ? (float) $posted['line_height'] : $defaults['line_height'];

	return array(
		'base_size'      => max( 12, min( 22, $base_size ) ),
		'heading_weight' => in_array( $weight, array( 400, 500, 600, 700, 800, 900 ), true ) ? $weight : $defaults['heading_weight'],
		'line_height'    => max( 1.2, min( 2.2, $line ) ),
	);
}

function jluxe_sanitize_header( array $posted, array $defaults ): array {
	return array(
		'sticky'               => ! empty( $posted['sticky'] ),
		// نمایش/عدم‌نمایش هر آیتم حالا مستقل برای موبایل و دسکتاپه (قبلاً
		// یک تیک مشترک هر دو حالت رو کنترل می‌کرد — درخواست واقعی: بشه مثلاً
		// آیکون سبد رو فقط از نوار پایین موبایل حذف کرد ولی توی دسکتاپ نگه داشت).
		'show_search_desktop'  => ! empty( $posted['show_search_desktop'] ),
		'show_search_mobile'   => ! empty( $posted['show_search_mobile'] ),
		'show_account_desktop' => ! empty( $posted['show_account_desktop'] ),
		'show_account_mobile'  => ! empty( $posted['show_account_mobile'] ),
		'show_cart_desktop'    => ! empty( $posted['show_cart_desktop'] ),
		'show_cart_mobile'     => ! empty( $posted['show_cart_mobile'] ),
	);
}

/**
 * منوی هدر — آیتم‌های سطح اول (بعد از مگامنوی ثابت «دسته‌بندی‌ها») + زیرمنوی
 * کشویی اختیاری برای هرکدوم. تعداد آیتم و تعداد زیرمنو پویاست (نه یک عدد
 * ثابت مثل اسلایدها) چون این یک منوئه، نه اسلات‌های محدود.
 */
function jluxe_sanitize_header_nav( array $posted, array $defaults ): array {
	$items_in = $posted['items'] ?? array();
	$items    = array();
	foreach ( $items_in as $item ) {
		$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
		if ( '' === $label ) {
			continue; // آیتم بدون عنوان یعنی خالی — رد شو.
		}
		$children = array();
		foreach ( $item['children'] ?? array() as $child ) {
			$child_label = isset( $child['label'] ) ? sanitize_text_field( $child['label'] ) : '';
			if ( '' === $child_label ) {
				continue;
			}
			$children[] = array(
				'label' => $child_label,
				'url'   => isset( $child['url'] ) ? sanitize_text_field( $child['url'] ) : '',
				'icon'  => isset( $child['icon'] ) ? sanitize_key( $child['icon'] ) : '',
				'svg'   => isset( $child['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $child['svg'] ) ) : '',
			);
		}
		// نوع خودکار از روی وجود زیرمنو تعیین می‌شه — نه یک فیلد جدا که می‌تونه
		// با محتوای واقعی ناهماهنگ بشه.
		$items[] = array(
			'id'       => isset( $item['id'] ) && $item['id'] ? sanitize_key( $item['id'] ) : 'nav-' . wp_generate_password( 6, false ),
			'type'     => ! empty( $children ) ? 'dropdown' : 'link',
			'label'    => $label,
			'url'      => isset( $item['url'] ) ? sanitize_text_field( $item['url'] ) : '',
			'icon'     => isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : '',
			'svg'      => isset( $item['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $item['svg'] ) ) : '',
			'children' => $children,
		);
	}
	return array( 'items' => $items );
}

function jluxe_sanitize_footer( array $posted, array $defaults ): array {
	// آیکونِ هر کارتِ مزیت حالا واقعاً انتخابیه، ولی فقط از یک whitelistِ
	// ثابت (نه SVG/کلیدِ دلخواه) — همون مجموعه‌ی مشترکِ منو + badge-percent.
	$jluxe_valid_feature_icons               = array_keys( function_exists( 'jluxe_nav_icon_options' ) ? jluxe_nav_icon_options() : array() );
	$jluxe_valid_feature_icons[]             = 'badge-percent';

	$feature_cards = array();
	foreach ( $defaults['feature_cards'] as $i => $default_card ) {
		$posted_icon = isset( $posted['feature_cards'][ $i ]['icon'] ) ? sanitize_key( $posted['feature_cards'][ $i ]['icon'] ) : '';
		$feature_cards[] = array(
			'enabled'  => ! empty( $posted['feature_cards'][ $i ]['enabled'] ),
			'icon'     => in_array( $posted_icon, $jluxe_valid_feature_icons, true ) ? $posted_icon : $default_card['icon'],
			'svg'      => isset( $posted['feature_cards'][ $i ]['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $posted['feature_cards'][ $i ]['svg'] ) ) : '',
			'title'    => isset( $posted['feature_cards'][ $i ]['title'] ) ? sanitize_text_field( $posted['feature_cards'][ $i ]['title'] ) : $default_card['title'],
			'subtitle' => isset( $posted['feature_cards'][ $i ]['subtitle'] ) ? sanitize_text_field( $posted['feature_cards'][ $i ]['subtitle'] ) : $default_card['subtitle'],
		);
	}

	// نمادهای سایت به‌صورت HTML آزادِ کنترل‌شده ذخیره می‌شن تا هر تعداد
	// سرویس (اینماد، ساماندهی، ترب، زرین‌پال و ...) قابل تعریف باشه.
	//
	// اولویت با آرایه‌ی خامِ فیلدهای واقعی (name="footer[trust_badges][][...]")
	// است، نه با JSON — چون فیلدِ مخفیِ JSON همیشه یه مقداری داره (حتی وقتی
	// جاوااسکریپت به هر دلیلی اصلاً اجرا نشده باشه، پیش‌فرضش رشته‌ی "[]"ه که
	// "غیرخالی" حساب می‌شه)، پس اگه JSON رو اول چک کنیم، حتی وقتی سینک انجام
	// نشده هیچ‌وقت به فالبکِ آرایه‌ی واقعی نمی‌رسیم و چیزی که کاربر تازه تایپ
	// کرده نادیده گرفته می‌شه. آرایه‌ی خام همیشه دقیقاً همون چیزیه که توی
	// فرم واقعاً وجود داره، صرف‌نظر از این‌که جاوااسکریپت کار کرده یا نه.
	$trust_badges_data = array();
	if ( isset( $posted['trust_badges'] ) && is_array( $posted['trust_badges'] ) ) {
		$has_content = false;
		foreach ( $posted['trust_badges'] as $jluxe_tb_row ) {
			if ( is_array( $jluxe_tb_row ) && ( '' !== trim( (string) ( $jluxe_tb_row['html'] ?? '' ) ) || '' !== trim( (string) ( $jluxe_tb_row['link'] ?? '' ) ) ) ) {
				$has_content = true;
				break;
			}
		}
		if ( $has_content ) {
			$trust_badges_data = $posted['trust_badges'];
		}
	}
	if ( empty( $trust_badges_data ) ) {
		$trust_badges_raw = $posted['trust_badges_json'] ?? '';
		if ( is_string( $trust_badges_raw ) && '' !== trim( $trust_badges_raw ) ) {
			$decoded = json_decode( wp_unslash( $trust_badges_raw ), true );
			if ( is_array( $decoded ) ) {
				$trust_badges_data = $decoded;
			}
		}
	}

	$allowed_badge_html = array(
		'a'   => array(
			'href' => true, 'target' => true, 'rel' => true, 'title' => true,
			'referrerpolicy' => true, 'class' => true, 'id' => true, 'style' => true,
			'onclick' => true,
		),
		'img' => array(
			'src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true,
			'class' => true, 'id' => true, 'style' => true, 'loading' => true,
			'referrerpolicy' => true, 'code' => true, 'onclick' => true,
		),
		'div' => array( 'class' => true, 'id' => true, 'style' => true ),
		'span' => array( 'class' => true, 'id' => true, 'style' => true ),
		'p' => array( 'class' => true, 'id' => true, 'style' => true ),
		'br' => array(),
		/*
		 * باگِ واقعیِ گزارش‌شده («فقط قسمت اینمادش کار نمی‌کنه»): کدِ رسمیِ
		 * اینماد یک تگِ <script src="https://trustseal.enamad.ir/..."> است،
		 * نه فقط یک <img> ثابت — طبق دستورالعملِ مرکز توسعه تجارت الکترونیک
		 * (enamad.ir)، استفاده از تصویرِ ثابت به‌جایِ کدِ واقعی می‌تونه باعثِ
		 * تعلیقِ نمادِ کسب‌وکار بشه، پس این تفاوت صرفاً بصری نیست. قبلاً
		 * 'script' اصلاً توی allowlist نبود، پس wp_kses کاملاً حذفش می‌کرد.
		 * محدود به src خارجی (بدون جاوااسکریپتِ inline) — کافیه چون کدِ
		 * واقعیِ اینماد دقیقاً همین شکله؛ ریسکِ XSS هم چون این فیلد فقط از
		 * پنل ادمین (نه ورودیِ کاربرِ سایت) پر می‌شه، در همون سطحِ اعتماد به
		 * ادمینه که وردپرس خودش برای «کد سفارشی» همیشه فرض می‌کنه.
		 */
		'script' => array(
			'src' => true, 'async' => true, 'defer' => true, 'id' => true,
			'referrerpolicy' => true, 'crossorigin' => true, 'type' => true,
		),
	);
	$trust_badges = array();
	foreach ( $trust_badges_data as $badge ) {
		if ( ! is_array( $badge ) ) {
			continue;
		}
		$html = isset( $badge['html'] ) ? trim( (string) $badge['html'] ) : '';
		$link = isset( $badge['link'] ) ? trim( (string) $badge['link'] ) : '';
		$link = $link && wp_http_validate_url( $link ) ? esc_url_raw( $link ) : '';

		// سازگاری با ساختار قدیمی image_id/link.
		if ( '' === $html && ! empty( $badge['image_id'] ) ) {
			$image_url = wp_get_attachment_image_url( absint( $badge['image_id'] ), 'full' );
			if ( $image_url ) {
				$img = '<img src="' . esc_url( $image_url ) . '" alt="نماد سایت" style="width:100%;height:auto;max-width:100%;object-fit:contain;" />';
				$html = ! empty( $badge['link'] ) ? '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( $badge['link'] ) . '">' . $img . '</a>' : $img;
			}
		}

		$html = wp_kses( $html, $allowed_badge_html );

		// اگر فقط لینک اعتبارسنجی وارد شده باشد، آن را به یک لینک قابل‌نمایش تبدیل کن؛
		// بنابراین کاربر برای ثبت لینک اینماد مجبور به ساخت HTML نیست.
		if ( '' === trim( $html ) && '' !== $link ) {
			$html = '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( $link ) . '">نماد اعتماد</a>';
		}

		if ( '' !== trim( wp_strip_all_tags( $html ) ) || false !== stripos( $html, '<img' ) || false !== stripos( $html, '<script' ) ) {
			$trust_badges[] = array( 'html' => $html, 'link' => $link );
		}
	}

	$mobile_columns = isset( $posted['feature_cards_mobile_columns'] ) ? absint( $posted['feature_cards_mobile_columns'] ) : $defaults['feature_cards_mobile_columns'];
	$mobile_columns = max( 1, min( 2, $mobile_columns ) ); // فقط ۱ یا ۲ ستون منطقیه؛ بیشتر از اون تو موبایل خیلی فشرده می‌شه.

	// ستون‌های لینک فوتر — حداکثر ۳ ستون؛ عنوان خالی یعنی اون ستون نادیده
	// گرفته می‌شه. داخل هر ستون هر تعداد لینک (لینک بدون عنوان = رد شو).
	$link_columns = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$col_in = $posted['link_columns'][ $i ] ?? array();
		$title  = isset( $col_in['title'] ) ? sanitize_text_field( $col_in['title'] ) : '';
		if ( '' === $title ) {
			continue;
		}
		$links = array();
		foreach ( $col_in['links'] ?? array() as $link_in ) {
			$link_label = isset( $link_in['label'] ) ? sanitize_text_field( $link_in['label'] ) : '';
			if ( '' === $link_label ) {
				continue;
			}
			$links[] = array(
				'label' => $link_label,
				'url'   => isset( $link_in['url'] ) ? sanitize_text_field( $link_in['url'] ) : '',
				'icon'  => isset( $link_in['icon'] ) ? sanitize_key( $link_in['icon'] ) : '',
				'svg'   => isset( $link_in['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $link_in['svg'] ) ) : '',
			);
		}
		$link_columns[] = array(
			'title' => $title,
			'links' => array_slice( $links, 0, 8 ),
		);
	}

	// پس‌زمینه‌ی فوتر — mode نامعتبر یا رنگِ نامعتبر بی‌سروصدا به پیش‌فرض
	// برمی‌گرده (نه خطا)، دقیقاً هم‌راستا با بقیه‌ی فیلدهای رنگیِ این فایل.
	$jluxe_valid_bg_modes      = array( 'default', 'solid', 'gradient' );
	$jluxe_valid_bg_directions = array( 'to top', 'to bottom', 'to left', 'to right', 'to top left', 'to top right', 'to bottom left', 'to bottom right' );
	$jluxe_posted_bg           = $posted['background'] ?? array();
	$jluxe_bg_mode             = isset( $jluxe_posted_bg['mode'] ) ? sanitize_key( str_replace( '_', '-', (string) $jluxe_posted_bg['mode'] ) ) : $defaults['background']['mode'];
	if ( ! in_array( $jluxe_bg_mode, $jluxe_valid_bg_modes, true ) ) {
		$jluxe_bg_mode = $defaults['background']['mode'];
	}
	$jluxe_bg_direction = isset( $jluxe_posted_bg['gradient_direction'] ) ? sanitize_text_field( $jluxe_posted_bg['gradient_direction'] ) : $defaults['background']['gradient_direction'];
	if ( ! in_array( $jluxe_bg_direction, $jluxe_valid_bg_directions, true ) ) {
		$jluxe_bg_direction = $defaults['background']['gradient_direction'];
	}
	$jluxe_bg_gradient_colors = array();
	for ( $jluxe_bg_i = 0; $jluxe_bg_i < 4; $jluxe_bg_i++ ) {
		$jluxe_posted_color         = isset( $jluxe_posted_bg['gradient_colors'][ $jluxe_bg_i ] ) ? sanitize_hex_color( $jluxe_posted_bg['gradient_colors'][ $jluxe_bg_i ] ) : '';
		$jluxe_bg_gradient_colors[] = $jluxe_posted_color ?: '';
	}
	$jluxe_background = array(
		'mode'               => $jluxe_bg_mode,
		'solid_color'        => isset( $jluxe_posted_bg['solid_color'] ) ? ( sanitize_hex_color( $jluxe_posted_bg['solid_color'] ) ?: '' ) : $defaults['background']['solid_color'],
		'gradient_direction' => $jluxe_bg_direction,
		'gradient_colors'    => $jluxe_bg_gradient_colors,
	);

	return array(
		'enabled'                      => ! empty( $posted['enabled'] ),
		'show_gradient_strip'          => ! empty( $posted['show_gradient_strip'] ),
		'background'                   => $jluxe_background,
		'text_color'                   => isset( $posted['text_color'] ) ? ( sanitize_hex_color( $posted['text_color'] ) ?: '' ) : $defaults['text_color'],
		'link_color'                   => isset( $posted['link_color'] ) ? ( sanitize_hex_color( $posted['link_color'] ) ?: '' ) : $defaults['link_color'],
		'link_hover_color'             => isset( $posted['link_hover_color'] ) ? ( sanitize_hex_color( $posted['link_hover_color'] ) ?: '' ) : $defaults['link_hover_color'],
		'support_hours'                => isset( $posted['support_hours'] ) ? sanitize_text_field( $posted['support_hours'] ) : $defaults['support_hours'],
		'support_text'                 => isset( $posted['support_text'] ) ? sanitize_text_field( $posted['support_text'] ) : $defaults['support_text'],
		'feature_cards'                => $feature_cards,
		'feature_cards_mobile_columns' => $mobile_columns,
		'feature_cards_icon_color'     => isset( $posted['feature_cards_icon_color'] ) ? ( sanitize_hex_color( $posted['feature_cards_icon_color'] ) ?: '' ) : $defaults['feature_cards_icon_color'],
		'copyright'                    => isset( $posted['copyright'] ) ? sanitize_text_field( $posted['copyright'] ) : $defaults['copyright'],
		'link_columns'                 => $link_columns,
		'trust_badges'                 => $trust_badges,
		'trust_badges_title'           => isset( $posted['trust_badges_title'] ) ? sanitize_text_field( $posted['trust_badges_title'] ) : $defaults['trust_badges_title'],
	);
}

/**
 * متنِ صفحاتِ «تماس با ما»/«درباره ما» — هر دو زیرآرایه یک ساختارِ یکسان
 * دارن، برای همین یک لوپ به‌جای تکرارِ کد.
 */
function jluxe_sanitize_info_pages( array $posted, array $defaults ): array {
	$out = array();
	foreach ( array( 'contact', 'about' ) as $page_key ) {
		$row               = $posted[ $page_key ] ?? array();
		$default_row       = $defaults[ $page_key ] ?? array();
		$out[ $page_key ] = array(
			'intro_title' => isset( $row['intro_title'] ) ? sanitize_text_field( $row['intro_title'] ) : ( $default_row['intro_title'] ?? '' ),
			'intro_text'  => isset( $row['intro_text'] ) ? sanitize_textarea_field( $row['intro_text'] ) : ( $default_row['intro_text'] ?? '' ),
			'show_logo'   => ! empty( $row['show_logo'] ),
			'phone_title' => isset( $row['phone_title'] ) ? sanitize_text_field( $row['phone_title'] ) : ( $default_row['phone_title'] ?? 'شماره‌ی تماس' ),
			'hours_title' => isset( $row['hours_title'] ) ? sanitize_text_field( $row['hours_title'] ) : ( $default_row['hours_title'] ?? 'ساعات پاسخگویی' ),
			'social_title' => isset( $row['social_title'] ) ? sanitize_text_field( $row['social_title'] ) : ( $default_row['social_title'] ?? 'شبکه‌های اجتماعی' ),
		);
		/*
		 * فقط برای «درباره ما»: بخشِ «داستانِ ما» + «چرا ما» + CTA — طبقِ
		 * درخواستِ صریحِ کاربر کاملاً از تنظیمات قابلِ ادیت شد. wp_kses_post
		 * (نه sanitize_textarea_field) چون این‌جا HTMLِ واقعی (h2/p/span)
		 * از wp_editor میاد، نه متنِ خام.
		 */
		if ( 'about' === $page_key ) {
			$out[ $page_key ]['story_html']       = isset( $row['story_html'] ) ? wp_kses_post( wp_unslash( $row['story_html'] ) ) : ( $default_row['story_html'] ?? '' );
			$out[ $page_key ]['cta_text']         = isset( $row['cta_text'] ) ? sanitize_text_field( $row['cta_text'] ) : ( $default_row['cta_text'] ?? '' );
			$out[ $page_key ]['cta_button_text']  = isset( $row['cta_button_text'] ) ? sanitize_text_field( $row['cta_button_text'] ) : ( $default_row['cta_button_text'] ?? '' );
			$out[ $page_key ]['cta_button_url']   = isset( $row['cta_button_url'] ) ? esc_url_raw( $row['cta_button_url'] ) : ( $default_row['cta_button_url'] ?? '' );
			$out[ $page_key ]['cta_button_color'] = isset( $row['cta_button_color'] ) ? sanitize_hex_color( $row['cta_button_color'] ) ?? '' : ( $default_row['cta_button_color'] ?? '' );
			$out[ $page_key ]['why_html'] = isset( $row['why_html'] ) ? wp_kses_post( wp_unslash( $row['why_html'] ) ) : ( $default_row['why_html'] ?? '' );
		}
	}
	return $out;
}

/**
 * محتوای صفحاتِ راهنما (jluxe-guide-pages) — هر صفحه یک title/intro (متنِ
 * ساده) و اگه صفحه واقعاً بخشِ اصلیِ HTML هم داشته باشه (content)، اون هم
 * با wp_kses_post سالم‌سازی می‌شه؛ نه sanitize_textarea_field، چون
 * wp_editor واقعاً HTML تولید می‌کنه (h2/p/strong/ul و...).
 */
function jluxe_sanitize_guide_pages( array $posted, array $defaults ): array {
	$out = array();
	foreach ( array_keys( $defaults ) as $page_key ) {
		$row         = isset( $posted[ $page_key ] ) && is_array( $posted[ $page_key ] ) ? $posted[ $page_key ] : array();
		$default_row = isset( $defaults[ $page_key ] ) && is_array( $defaults[ $page_key ] ) ? $defaults[ $page_key ] : array();

		// راهنمای خرید یک محتوای کامل و چندبخشی است؛ هر جزء صفحه مستقیماً
		// از همین تنظیمات خوانده می‌شود و HTML امنِ ادیتور حفظ می‌گردد.
		if ( 'shopping_guide' === $page_key ) {
			$clean = array(
				'eyebrow'        => isset( $row['eyebrow'] ) ? sanitize_text_field( wp_unslash( $row['eyebrow'] ) ) : ( $default_row['eyebrow'] ?? '' ),
				'title'          => isset( $row['title'] ) ? sanitize_text_field( wp_unslash( $row['title'] ) ) : ( $default_row['title'] ?? '' ),
				'intro'          => isset( $row['intro'] ) ? sanitize_textarea_field( wp_unslash( $row['intro'] ) ) : ( $default_row['intro'] ?? '' ),
				'button_text'    => isset( $row['button_text'] ) ? sanitize_text_field( wp_unslash( $row['button_text'] ) ) : ( $default_row['button_text'] ?? '' ),
				'button_url'     => isset( $row['button_url'] ) ? esc_url_raw( $row['button_url'] ) : ( $default_row['button_url'] ?? '' ),
				'button_color'   => isset( $row['button_color'] ) ? ( sanitize_hex_color( $row['button_color'] ) ?? '' ) : ( $default_row['button_color'] ?? '' ),
				'body_html'      => isset( $row['body_html'] ) ? wp_kses_post( wp_unslash( $row['body_html'] ) ) : ( $default_row['body_html'] ?? '' ),
				'button_enabled' => ! empty( $row['button_enabled'] ),
				'steps'          => array(),
			);
			$default_steps = isset( $default_row['steps'] ) && is_array( $default_row['steps'] ) ? $default_row['steps'] : array();
			$posted_steps  = isset( $row['steps'] ) && is_array( $row['steps'] ) ? $row['steps'] : array();
			for ( $i = 0; $i < 5; $i++ ) {
				$d = isset( $default_steps[ $i ] ) && is_array( $default_steps[ $i ] ) ? $default_steps[ $i ] : array();
				$r = isset( $posted_steps[ $i ] ) && is_array( $posted_steps[ $i ] ) ? $posted_steps[ $i ] : array();
				$clean['steps'][] = array(
					'title'        => isset( $r['title'] ) ? sanitize_text_field( wp_unslash( $r['title'] ) ) : ( $d['title'] ?? '' ),
					'content'      => isset( $r['content'] ) ? wp_kses_post( wp_unslash( $r['content'] ) ) : ( $d['content'] ?? '' ),
					'callout_type' => isset( $r['callout_type'] ) && in_array( $r['callout_type'], array( '', 'tip', 'note' ), true ) ? $r['callout_type'] : ( $d['callout_type'] ?? '' ),
					'callout_title'=> isset( $r['callout_title'] ) ? sanitize_text_field( wp_unslash( $r['callout_title'] ) ) : ( $d['callout_title'] ?? '' ),
					'callout_text' => isset( $r['callout_text'] ) ? sanitize_textarea_field( wp_unslash( $r['callout_text'] ) ) : ( $d['callout_text'] ?? '' ),
				);
			}
			$out[ $page_key ] = $clean;
			continue;
		}

		$clean = array();
		foreach ( $default_row as $field_key => $default_value ) {
			if ( ! isset( $row[ $field_key ] ) ) {
				$clean[ $field_key ] = $default_value;
				continue;
			}
			if ( 'content' === $field_key ) {
				$clean[ $field_key ] = wp_kses_post( wp_unslash( $row[ $field_key ] ) );
			} elseif ( 'button_url' === $field_key ) {
				$clean[ $field_key ] = esc_url_raw( $row[ $field_key ] );
			} elseif ( 'button_color' === $field_key ) {
				$clean[ $field_key ] = sanitize_hex_color( $row[ $field_key ] ) ?? '';
			} elseif ( 'intro' === $field_key || 'title' === $field_key || str_ends_with( $field_key, '_text' ) ) {
				$clean[ $field_key ] = sanitize_textarea_field( wp_unslash( $row[ $field_key ] ) );
			} else {
				$clean[ $field_key ] = sanitize_text_field( wp_unslash( $row[ $field_key ] ) );
			}
		}
		$out[ $page_key ] = $clean;
	}
	return $out;
}

/**
 * اطلاعاتِ حسابِ کارت‌به‌کارت — طبقِ درخواستِ صریحِ کاربر. هیچ اعتبارسنجیِ
 * الگوریتمیِ خاصی (چک‌سامِ شبا و...) عمداً انجام نمی‌شه؛ فقط sanitize_text_field
 * معمولی — چون فرمتِ دقیقِ نمایش (خط‌تیره‌دار/بدون خط‌تیره) به‌عهده‌ی خودِ
 * ادمینه، این‌جا فقط جلوی تگ/اسکریپتِ ناخواسته گرفته می‌شه.
 */
function jluxe_sanitize_payment_account( array $posted, array $defaults ): array {
	return array(
		'card_number' => isset( $posted['card_number'] ) ? sanitize_text_field( wp_unslash( $posted['card_number'] ) ) : ( $defaults['card_number'] ?? '' ),
		'sheba'       => isset( $posted['sheba'] ) ? sanitize_text_field( wp_unslash( $posted['sheba'] ) ) : ( $defaults['sheba'] ?? '' ),
		'holder_name' => isset( $posted['holder_name'] ) ? sanitize_text_field( wp_unslash( $posted['holder_name'] ) ) : ( $defaults['holder_name'] ?? '' ),
		'bank_name'   => isset( $posted['bank_name'] ) ? sanitize_text_field( wp_unslash( $posted['bank_name'] ) ) : ( $defaults['bank_name'] ?? '' ),
	);
}

function jluxe_sanitize_social( array $posted, array $defaults ): array {
	$out = array();
	foreach ( array_keys( $defaults ) as $key ) {
		$out[ $key ] = array(
			'enabled' => ! empty( $posted[ $key ]['enabled'] ),
			'url'     => isset( $posted[ $key ]['url'] ) ? esc_url_raw( $posted[ $key ]['url'] ) : '',
			// آیکونِ SVG سفارشیِ هر شبکه — طبق درخواستِ کاربر، به‌جای همیشه
			// افتادن روی آیکونِ عمومیِ Send برای واتس‌اپ/روبیکا/بله (که
			// برندِ اختصاصی در lucide ندارن) قابل تعریفه.
			'svg'     => isset( $posted[ $key ]['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $posted[ $key ]['svg'] ) ) : '',
		);
	}
	return $out;
}

function jluxe_sanitize_contact( array $posted, array $defaults ): array {
	return array(
		'phone'                        => isset( $posted['phone'] ) ? sanitize_text_field( $posted['phone'] ) : '',
		'phone_secondary'              => isset( $posted['phone_secondary'] ) ? sanitize_text_field( $posted['phone_secondary'] ) : '',
		'email'                        => isset( $posted['email'] ) ? sanitize_email( $posted['email'] ) : '',
		'address'                      => isset( $posted['address'] ) ? sanitize_textarea_field( $posted['address'] ) : '',
		'dashboard_announcement'      => isset( $posted['dashboard_announcement'] ) ? sanitize_text_field( $posted['dashboard_announcement'] ) : '',
		'dashboard_announcement_link' => isset( $posted['dashboard_announcement_link'] ) ? esc_url_raw( $posted['dashboard_announcement_link'] ) : '',
	);
}

/**
 * ترتیبِ آرایه‌ی ورودی (posted) حفظ می‌شه — چون همون چیزیه که با درگ‌ودراپ
 * در ادمین جابه‌جا شده. id فقط از یک whitelist ثابت (همون ۵ آیتمِ واقعی
 * که در MobileNav.tsx رفتار/لینکِ خودشون رو دارن) پذیرفته می‌شه؛ هر id
 * ناشناس یا تکراری رد می‌شه، و اگه آیتمی کلاً جا بمونه (مثلاً فرم دستکاری
 * شده) از پیش‌فرض همون id اضافه می‌شه تا هیچ‌وقت یکی از ۵ تب گم نشه.
 */
function jluxe_sanitize_mobile( array $posted, array $defaults ): array {
	$valid_ids     = wp_list_pluck( $defaults['nav_items'], 'id' );
	$defaults_by_id = array();
	foreach ( $defaults['nav_items'] as $default_item ) {
		$defaults_by_id[ $default_item['id'] ] = $default_item;
	}

	$nav_items = array();
	$seen_ids  = array();
	foreach ( $posted['nav_items'] ?? array() as $item ) {
		$id = isset( $item['id'] ) ? sanitize_key( $item['id'] ) : '';
		if ( ! in_array( $id, $valid_ids, true ) || in_array( $id, $seen_ids, true ) ) {
			continue;
		}
		$seen_ids[] = $id;
		$label      = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
		$nav_items[] = array(
			'id'      => $id,
			'label'   => '' !== $label ? $label : $defaults_by_id[ $id ]['label'],
			'icon'    => isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : $defaults_by_id[ $id ]['icon'],
			'enabled' => ! empty( $item['enabled'] ),
		);
	}

	foreach ( $valid_ids as $id ) {
		if ( ! in_array( $id, $seen_ids, true ) ) {
			$nav_items[] = $defaults_by_id[ $id ];
		}
	}

	$style = $posted['nav_style'] ?? array();
	$hex = static function ( $value, $fallback ) {
		$value = sanitize_hex_color( (string) $value );
		return $value ?: $fallback;
	};
	$style_defaults = $defaults['nav_style'];
	$shadow = sanitize_key( $style['shadow'] ?? $style_defaults['shadow'] );
	$radius = max( 12, min( 32, absint( $style['radius'] ?? $style_defaults['radius'] ) ) );
	$height = max( 60, min( 82, absint( $style['height'] ?? $style_defaults['height'] ) ) );

	return array(
		'nav_items' => $nav_items,
		'nav_style' => array(
			'background'   => $hex( $style['background'] ?? '', $style_defaults['background'] ),
			'active_color' => $hex( $style['active_color'] ?? '', $style_defaults['active_color'] ),
			'icon_color'   => $hex( $style['icon_color'] ?? '', $style_defaults['icon_color'] ),
			'icon_bg'      => $hex( $style['icon_bg'] ?? '', $style_defaults['icon_bg'] ),
			'active_bg'    => $hex( $style['active_bg'] ?? '', $style_defaults['active_bg'] ),
			'shadow'       => in_array( $shadow, array( 'none', 'soft', 'medium', 'strong' ), true ) ? $shadow : $style_defaults['shadow'],
			'radius'       => (string) $radius,
			'height'       => (string) $height,
			'blur'         => ! empty( $style['blur'] ),
			'animate'      => ! empty( $style['animate'] ),
		),
	);
}

function jluxe_sanitize_product_card( array $posted, array $defaults ): array {
	$ratio   = isset( $posted['image_ratio'] ) ? sanitize_key( $posted['image_ratio'] ) : $defaults['image_ratio'];
	$radius  = isset( $posted['radius'] ) ? sanitize_key( $posted['radius'] ) : $defaults['radius'];
	$corners = isset( $posted['image_corners'] ) ? sanitize_key( $posted['image_corners'] ) : $defaults['image_corners'];

	return array(
		'image_ratio'      => in_array( $ratio, array( 'square', 'classic', 'portrait' ), true ) ? $ratio : $defaults['image_ratio'],
		'radius'           => in_array( $radius, array( 'sm', 'md', 'lg' ), true ) ? $radius : $defaults['radius'],
		'image_corners'    => in_array( $corners, array( 'rounded', 'sharp' ), true ) ? $corners : $defaults['image_corners'],
		'show_rating'      => ! empty( $posted['show_rating'] ),
		'show_sale_badge'  => ! empty( $posted['show_sale_badge'] ),
		'show_stock_badge' => ! empty( $posted['show_stock_badge'] ),
		'price_color'      => isset( $posted['price_color'] ) ? ( sanitize_hex_color( $posted['price_color'] ) ?: '' ) : '',
		'in_stock_color'   => isset( $posted['in_stock_color'] ) ? ( sanitize_hex_color( $posted['in_stock_color'] ) ?: $defaults['in_stock_color'] ) : $defaults['in_stock_color'],
	);
}

function jluxe_sanitize_shop( array $posted, array $defaults ): array {
	return array(
		'products_per_page'          => max( 4, min( 48, absint( $posted['products_per_page'] ?? $defaults['products_per_page'] ) ) ),
		'columns_desktop'            => max( 2, min( 6, absint( $posted['columns_desktop'] ?? $defaults['columns_desktop'] ) ) ),
		'columns_tablet'             => max( 2, min( 4, absint( $posted['columns_tablet'] ?? $defaults['columns_tablet'] ) ) ),
		'columns_mobile'             => max( 1, min( 3, absint( $posted['columns_mobile'] ?? $defaults['columns_mobile'] ) ) ),
		'show_account_downloads_tab' => ! empty( $posted['show_account_downloads_tab'] ),
		'mini_cart_show_coupon'        => ! empty( $posted['mini_cart_show_coupon'] ),
		'mini_cart_show_free_shipping' => ! empty( $posted['mini_cart_show_free_shipping'] ),
		'auto_scroll_carousels'        => ! empty( $posted['auto_scroll_carousels'] ),
	);
}

function jluxe_sanitize_product_page( array $posted, array $defaults ): array {
	$count = isset( $posted['related_count'] ) ? absint( $posted['related_count'] ) : $defaults['related_count'];

	// تعداد ثابت (۳ ردیف) مثل feature_cards — عنوان/توضیح/فعال‌بودنِ هرکدوم
	// قابل‌ویرایشه، آیکون ثابت می‌مونه (توی content-single-product.php
	// بر اساسِ ایندکس رندر می‌شه، نه از تنظیمات).
	$trust_items = array();
	foreach ( $defaults['trust_items'] as $i => $default_item ) {
		$trust_items[] = array(
			'enabled'  => ! empty( $posted['trust_items'][ $i ]['enabled'] ),
			'title'    => isset( $posted['trust_items'][ $i ]['title'] ) ? sanitize_text_field( $posted['trust_items'][ $i ]['title'] ) : $default_item['title'],
			'subtitle' => isset( $posted['trust_items'][ $i ]['subtitle'] ) ? sanitize_text_field( $posted['trust_items'][ $i ]['subtitle'] ) : $default_item['subtitle'],
		);
	}

	$layout = isset( $posted['layout'] ) ? sanitize_key( $posted['layout'] ) : $defaults['layout'];

	return array(
		'layout'         => in_array( $layout, array( 'default', 'classic' ), true ) ? $layout : $defaults['layout'],
		'show_related'   => ! empty( $posted['show_related'] ),
		'related_count'  => max( 2, min( 8, $count ) ),
		'discount_color' => isset( $posted['discount_color'] ) ? ( sanitize_hex_color( $posted['discount_color'] ) ?: $defaults['discount_color'] ) : $defaults['discount_color'],
		'savings_color'  => isset( $posted['savings_color'] ) ? ( sanitize_hex_color( $posted['savings_color'] ) ?: $defaults['savings_color'] ) : $defaults['savings_color'],
		'star_color'     => isset( $posted['star_color'] ) ? ( sanitize_hex_color( $posted['star_color'] ) ?: $defaults['star_color'] ) : $defaults['star_color'],
		'trust_items'    => $trust_items,
	);
}

function jluxe_sanitize_seo( array $posted, array $defaults ): array {
	return array(
		'default_meta_title'       => isset( $posted['default_meta_title'] ) ? sanitize_text_field( $posted['default_meta_title'] ) : '',
		'default_meta_description' => isset( $posted['default_meta_description'] ) ? sanitize_textarea_field( $posted['default_meta_description'] ) : '',
		'og_image_id'              => isset( $posted['og_image_id'] ) ? absint( $posted['og_image_id'] ) : 0,
	);
}

/**
 * سؤالات متداول — طول متغیر (نه آرایه‌ی ثابت مثل feature_cards)، چون تعداد
 * سؤال واقعیِ هر فروشگاه از قبل معلوم نیست. سطرِ بدون متنِ سؤال یا علامت‌خورده
 * برای حذف، ذخیره نمی‌شه.
 */
function jluxe_sanitize_faq( array $posted, array $defaults ): array {
	$items = array();
	if ( ! empty( $posted['items'] ) && is_array( $posted['items'] ) ) {
		foreach ( $posted['items'] as $raw_item ) {
			$question = isset( $raw_item['question'] ) ? sanitize_text_field( $raw_item['question'] ) : '';
			if ( '' === $question || ! empty( $raw_item['remove'] ) ) {
				continue;
			}
			$items[] = array(
				'question' => $question,
				'answer'   => isset( $raw_item['answer'] ) ? sanitize_textarea_field( $raw_item['answer'] ) : '',
			);
		}
	}
	return array( 'items' => array_slice( $items, 0, 30 ) ); // سقف منطقی، جلوگیری از داده‌ی بی‌حدواندازه.
}

function jluxe_sanitize_review_criteria( array $posted, array $defaults ): array {
	$items = array();
	$used_keys = array();
	if ( ! empty( $posted['items'] ) && is_array( $posted['items'] ) ) {
		foreach ( $posted['items'] as $raw_item ) {
			$label = isset( $raw_item['label'] ) ? sanitize_text_field( $raw_item['label'] ) : '';
			$key   = isset( $raw_item['key'] ) ? sanitize_key( $raw_item['key'] ) : '';
			if ( '' === $key && '' !== $label ) {
				// ادمین کلید رو خالی گذاشته ولی برچسب رو پر کرده — یک کلیدِ
				// معتبر از رویِ خودِ برچسب می‌سازیم تا مجبور نباشه انگلیسی
				// تایپ کنه.
				$key = sanitize_key( sanitize_title( $label ) );
			}
			if ( '' === $key || '' === $label || ! empty( $raw_item['remove'] ) ) {
				continue;
			}
			if ( isset( $used_keys[ $key ] ) ) {
				continue; // کلیدِ تکراری — نگه‌داشتنِ اولین مورد، نادیده‌گرفتنِ بقیه.
			}
			$used_keys[ $key ] = true;
			$items[]            = array(
				'key'   => $key,
				'label' => $label,
			);
		}
	}
	return array( 'items' => array_slice( $items, 0, 10 ) ); // سقف منطقی.
}

function jluxe_sanitize_performance( array $posted, array $defaults ): array {
	return array(
		'disable_emojis'   => ! empty( $posted['disable_emojis'] ),
		'lazy_load_images' => ! empty( $posted['lazy_load_images'] ),
		'defer_third_party_scripts' => ! empty( $posted['defer_third_party_scripts'] ),
	);
}

/**
 * CSS: بدون تگ HTML (wp_strip_all_tags جلوی تزریق <script> رو داخل CSS
 * می‌گیره). JS: فقط manage_options می‌تونه اینجا بنویسه (چک دسترسی سطح
 * بالاتر، در فراخوان)، ولی همچنان null byte/کنترل‌کاراکترهای خطرناک پاک
 * می‌شن؛ محتوای JS خودش هرچی باشه اجرا می‌شه چون قصد استفاده همینه (کاربر
 * ادمین مورد اعتماد، دقیقاً مثل ویرایشگر Additional CSS خودِ وردپرس).
 */
function jluxe_sanitize_custom_code( array $posted, array $defaults ): array {
	return array(
		'css' => isset( $posted['css'] ) ? wp_strip_all_tags( wp_unslash( $posted['css'] ) ) : '',
		'js'  => isset( $posted['js'] ) ? wp_check_invalid_utf8( wp_unslash( $posted['js'] ) ) : '',
	);
}

function jluxe_sanitize_ai_assistant( array $posted, array $defaults ): array {
	$knowledge = array();
	foreach ( array_keys( $defaults['knowledge'] ) as $key ) {
		$knowledge[ $key ] = ! empty( $posted['knowledge'][ $key ] );
	}

	$tools = array();
	foreach ( array_keys( $defaults['tools'] ) as $key ) {
		$tools[ $key ] = ! empty( $posted['tools'][ $key ] );
	}

	$widgets = array();
	foreach ( array_keys( $defaults['widgets'] ) as $key ) {
		$widgets[ $key ] = ! empty( $posted['widgets'][ $key ] );
	}

	// یک آیتم به‌ازای هر خط غیرخالی از textarea «پیشنهادهای آماده».
	$quick_replies = array();
	if ( isset( $posted['quick_replies'] ) && is_string( $posted['quick_replies'] ) ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $posted['quick_replies'] ) as $line ) {
			$line = sanitize_text_field( $line );
			if ( '' !== $line ) {
				$quick_replies[] = $line;
			}
		}
	}

	$temperature      = isset( $posted['temperature'] ) ? (float) $posted['temperature'] : $defaults['temperature'];
	$max_tokens       = isset( $posted['max_tokens'] ) ? absint( $posted['max_tokens'] ) : $defaults['max_tokens'];
	$width            = isset( $posted['window_width'] ) ? absint( $posted['window_width'] ) : $defaults['window_width'];
	$radius           = isset( $posted['border_radius'] ) ? absint( $posted['border_radius'] ) : $defaults['border_radius'];
	$position         = isset( $posted['position'] ) ? sanitize_key( $posted['position'] ) : $defaults['position'];
	$provider         = isset( $posted['provider'] ) ? sanitize_key( $posted['provider'] ) : '';
	$reasoning_effort = isset( $posted['reasoning_effort'] ) ? sanitize_key( $posted['reasoning_effort'] ) : $defaults['reasoning_effort'];
	$rate_limit       = isset( $posted['rate_limit'] ) ? absint( $posted['rate_limit'] ) : $defaults['rate_limit'];

	return array(
		'enabled'          => ! empty( $posted['enabled'] ),
		'name'             => isset( $posted['name'] ) ? sanitize_text_field( $posted['name'] ) : $defaults['name'],
		'welcome_message'  => isset( $posted['welcome_message'] ) ? sanitize_textarea_field( $posted['welcome_message'] ) : $defaults['welcome_message'],
		'avatar_id'        => isset( $posted['avatar_id'] ) ? absint( $posted['avatar_id'] ) : 0,
		'button_id'        => isset( $posted['button_id'] ) ? absint( $posted['button_id'] ) : 0,
		'show_desktop'     => ! empty( $posted['show_desktop'] ),
		'show_mobile'      => ! empty( $posted['show_mobile'] ),
		'provider'         => in_array( $provider, array( '', 'openai', 'anthropic', 'gapgpt', 'custom' ), true ) ? $provider : '',
		'base_url'         => isset( $posted['base_url'] ) ? esc_url_raw( trim( (string) $posted['base_url'] ) ) : '',
		'model'            => isset( $posted['model'] ) ? sanitize_text_field( $posted['model'] ) : '',
		'temperature'      => max( 0, min( 2, $temperature ) ),
		'max_tokens'       => max( 50, min( 4000, $max_tokens ) ),
		'reasoning_effort' => in_array( $reasoning_effort, array( 'minimal', 'low', 'medium', 'high' ), true ) ? $reasoning_effort : $defaults['reasoning_effort'],
		'system_prompt'    => isset( $posted['system_prompt'] ) ? sanitize_textarea_field( $posted['system_prompt'] ) : '',
		'review_summary_enabled'   => ! empty( $posted['review_summary_enabled'] ),
		'review_summary_min_count' => isset( $posted['review_summary_min_count'] ) ? max( 1, min( 50, absint( $posted['review_summary_min_count'] ) ) ) : $defaults['review_summary_min_count'],
		'knowledge'        => $knowledge,
		'tools'            => $tools,
		'widgets'          => $widgets,
		'quick_replies'    => $quick_replies,
		'primary_color'    => isset( $posted['primary_color'] ) ? ( sanitize_hex_color( $posted['primary_color'] ) ?: '' ) : '',
		'text_color'       => isset( $posted['text_color'] ) ? ( sanitize_hex_color( $posted['text_color'] ) ?: '' ) : '',
		'bg_user_color'    => isset( $posted['bg_user_color'] ) ? ( sanitize_hex_color( $posted['bg_user_color'] ) ?: '' ) : '',
		'bg_bot_color'     => isset( $posted['bg_bot_color'] ) ? ( sanitize_hex_color( $posted['bg_bot_color'] ) ?: '' ) : '',
		'negative_color'   => isset( $posted['negative_color'] ) ? ( sanitize_hex_color( $posted['negative_color'] ) ?: '' ) : '',
		'position'         => in_array( $position, array( 'start', 'end' ), true ) ? $position : $defaults['position'],
		'offset_bottom_desktop' => isset( $posted['offset_bottom_desktop'] ) ? max( 0, min( 400, absint( $posted['offset_bottom_desktop'] ) ) ) : $defaults['offset_bottom_desktop'],
		'offset_side_desktop'   => isset( $posted['offset_side_desktop'] ) ? max( 0, min( 400, absint( $posted['offset_side_desktop'] ) ) ) : $defaults['offset_side_desktop'],
		'offset_bottom_mobile'  => isset( $posted['offset_bottom_mobile'] ) ? max( 0, min( 400, absint( $posted['offset_bottom_mobile'] ) ) ) : $defaults['offset_bottom_mobile'],
		'offset_side_mobile'    => isset( $posted['offset_side_mobile'] ) ? max( 0, min( 400, absint( $posted['offset_side_mobile'] ) ) ) : $defaults['offset_side_mobile'],
		'window_width'     => max( 280, min( 520, $width ) ),
		'border_radius'    => max( 0, min( 32, $radius ) ),
		'handoff_whatsapp' => isset( $posted['handoff_whatsapp'] ) ? esc_url_raw( trim( (string) $posted['handoff_whatsapp'] ) ) : '',
		'handoff_telegram' => isset( $posted['handoff_telegram'] ) ? esc_url_raw( trim( (string) $posted['handoff_telegram'] ) ) : '',
		'handoff_form_url' => isset( $posted['handoff_form_url'] ) ? esc_url_raw( trim( (string) $posted['handoff_form_url'] ) ) : '',
		'rate_limit'       => max( 0, min( 120, $rate_limit ) ),
		'log_enabled'      => ! empty( $posted['log_enabled'] ),
	);
}

function jluxe_sanitize_sms( array $posted, array $defaults ): array {
	$provider = isset( $posted['provider'] ) ? sanitize_key( $posted['provider'] ) : '';

	return array(
		'enabled'  => ! empty( $posted['enabled'] ),
		'provider' => in_array( $provider, array( '', 'kavenegar', 'melipayamak' ), true ) ? $provider : '',
		'username' => isset( $posted['username'] ) ? sanitize_text_field( $posted['username'] ) : $defaults['username'],
		'sender'   => isset( $posted['sender'] ) ? sanitize_text_field( $posted['sender'] ) : $defaults['sender'],
		'template' => isset( $posted['template'] ) ? sanitize_text_field( $posted['template'] ) : $defaults['template'],
		'body_id'  => isset( $posted['body_id'] ) ? (string) absint( $posted['body_id'] ) : ( $defaults['body_id'] ?? '' ),
	);
}

/**
 * هر ردیف homepage.sections یک 'type' داره که تعیین می‌کنه کدوم فیلدها
 * معتبرن؛ فیلدهای مشترک (id/type/enabled) همیشه sanitize می‌شن، بقیه بر
 * اساس نوع.
 */
function jluxe_sanitize_homepage_section( array $posted ): array {
	$type = isset( $posted['type'] ) ? sanitize_key( $posted['type'] ) : 'text';
	$id   = isset( $posted['id'] ) ? sanitize_key( $posted['id'] ) : '';
	if ( '' === $id ) {
		$id = 'sec-' . substr( md5( wp_generate_uuid4() ), 0, 12 );
	}

	$section = array(
		'id'      => $id,
		'type'    => $type,
		'enabled' => ! empty( $posted['enabled'] ),
	);

	switch ( $type ) {
		case 'hero':
			$section['duration_sec'] = max( 2, min( 15, absint( $posted['duration_sec'] ?? 5 ) ) );
			$section['zoom_enabled'] = array_key_exists( 'zoom_enabled', $posted ) && '1' === (string) $posted['zoom_enabled'];
			$width_mode              = isset( $posted['width_mode'] ) ? sanitize_key( $posted['width_mode'] ) : 'full';
			$section['width_mode']   = in_array( $width_mode, array( 'full', 'container' ), true ) ? $width_mode : 'full';
			// دیگه ۶ اسلات ثابت نیست — لیست پویاست (افزودن/حذف واقعی از ادمین)،
			// چون سایز عکس دسکتاپ و موبایل حالا جدا ذخیره می‌شه (mobile_image_id)
			// و تعداد اسلاید واقعی سلیقه‌ی ادمینه، نه یک عدد هاردکد.
			$section['items'] = array();
			foreach ( $posted['items'] ?? array() as $item ) {
				$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
				if ( ! $image_id ) {
					continue; // اسلایدی که حتی عکس دسکتاپ هم نداره، خالیه — نگه‌داشتنش فایده نداره.
				}
				$position = isset( $item['content_position'] ) ? sanitize_key( $item['content_position'] ) : 'start';
				$section['items'][] = array(
					'image_id'         => $image_id,
					// خالی = روی موبایل هم از همون عکس دسکتاپ استفاده می‌شه
					// (object-contain، بدون کراپ) — دقیقاً fallback فعلی، پس
					// اسلایدهای قدیمیِ تک‌عکسی خراب نمی‌شن.
					'mobile_image_id'  => isset( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : 0,
					'title'            => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
					'subtitle'         => isset( $item['subtitle'] ) ? sanitize_text_field( $item['subtitle'] ) : '',
					'button'           => isset( $item['button'] ) ? sanitize_text_field( $item['button'] ) : '',
					'button_color'     => isset( $item['button_color'] ) ? ( sanitize_hex_color( $item['button_color'] ) ?: '' ) : '',
					'content_position' => in_array( $position, array( 'start', 'center', 'end' ), true ) ? $position : 'start',
					'link'             => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '',
				);
			}
			break;

		case 'stories':
			$section['items'] = array();
			for ( $i = 0; $i < 8; $i++ ) {
				$item = $posted['items'][ $i ] ?? array();
				$media_type = isset( $item['media_type'] ) ? sanitize_key( $item['media_type'] ) : 'image';
				$section['items'][] = array(
					'thumb_id'   => isset( $item['thumb_id'] ) ? absint( $item['thumb_id'] ) : 0,
					'media_id'   => isset( $item['media_id'] ) ? absint( $item['media_id'] ) : 0,
					'title'      => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
					'media_type' => in_array( $media_type, array( 'image', 'video' ), true ) ? $media_type : 'image',
				);
			}
			break;

		case 'banner_slider':
			$section['title'] = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			$section['items'] = array();
			for ( $i = 0; $i < 5; $i++ ) {
				$item = $posted['items'][ $i ] ?? array();
				$section['items'][] = array(
					'image_id'    => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'category'    => isset( $item['category'] ) ? sanitize_text_field( $item['category'] ) : '',
					'title'       => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
					'description' => isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '',
					'link'        => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '',
				);
			}
			break;

		case 'brick_products':
			$source  = isset( $posted['source'] ) ? sanitize_key( $posted['source'] ) : 'bestsellers';
			$sources = array( 'bestsellers', 'category', 'brand', 'manual' );

			$section['title']    = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : 'پرفروش‌ترین‌ها';
			$section['view_all_link'] = isset( $posted['view_all_link'] ) ? esc_url_raw( $posted['view_all_link'] ) : '';
			$section['source']   = in_array( $source, $sources, true ) ? $source : 'bestsellers';
			$section['category'] = isset( $posted['category'] ) ? absint( $posted['category'] ) : 0;
			$section['brand']    = isset( $posted['brand'] ) ? absint( $posted['brand'] ) : 0;

			// لیست دستی: فقط ID محصولاتی که واقعاً وجود دارن نگه داشته می‌شن
			// (مثلاً بعد از حذف یک محصول، ID یتیمش خودش از تنظیمات پاک می‌شه).
			$section['product_ids'] = array();
			foreach ( $posted['product_ids'] ?? array() as $pid ) {
				$pid = absint( $pid );
				if ( $pid && 'product' === get_post_type( $pid ) ) {
					$section['product_ids'][] = $pid;
				}
			}

			// ستون/ردیف به‌جای «تعداد محصول» دستی — این‌جوری تعداد همیشه
			// دقیقاً با چیدمان واقعیِ گرید مطابقت داره (باگ گزارش‌شده: قبلاً
			// «تعداد محصول» یک عدد جدا از چیدمان بود و می‌تونست هم‌خونی نداشته باشه).
			$section['columns_desktop']   = max( 2, min( 6, absint( $posted['columns_desktop'] ?? 4 ) ) );
			$section['columns_tablet']    = max( 2, min( 4, absint( $posted['columns_tablet'] ?? 3 ) ) );
			$section['columns_mobile']    = max( 1, min( 3, absint( $posted['columns_mobile'] ?? 2 ) ) );
			$section['rows']              = max( 1, min( 4, absint( $posted['rows'] ?? 2 ) ) );
			$section['hide_out_of_stock'] = ! empty( $posted['hide_out_of_stock'] ) && '1' === $posted['hide_out_of_stock'];
			break;

		case 'special_products':
			$special_sort  = isset( $posted['sort'] ) ? sanitize_key( $posted['sort'] ) : 'discount';
			$special_sorts = array( 'discount', 'latest', 'popularity', 'sales', 'price_low', 'price_high', 'rating', 'rand' );

			$section['title']             = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : 'فروش ویژه';
			$section['count']             = max( 4, min( 30, absint( $posted['count'] ?? 10 ) ) );
			$section['sort']              = in_array( $special_sort, $special_sorts, true ) ? $special_sort : 'discount';
			$section['view_all_link']     = isset( $posted['view_all_link'] ) ? esc_url_raw( $posted['view_all_link'] ) : '';
			$section['hide_out_of_stock'] = ! empty( $posted['hide_out_of_stock'] ) && '1' === $posted['hide_out_of_stock'];
			break;

		case 'banner_collage':
			$bc_width_mode  = isset( $posted['width_mode'] ) ? sanitize_key( $posted['width_mode'] ) : 'full';
			$bc_width_modes = array( 'full', 'boxed1100', 'boxed1489' );

			$bc_desktop_layout = isset( $posted['desktop_layout'] ) ? sanitize_key( $posted['desktop_layout'] ) : 'asymmetric_5';
			$bc_mobile_layout  = isset( $posted['mobile_layout'] ) ? sanitize_key( $posted['mobile_layout'] ) : 'stacked';

			$section['width_mode']     = in_array( $bc_width_mode, $bc_width_modes, true ) ? $bc_width_mode : 'full';
			$section['radius']         = max( 0, min( 40, absint( $posted['radius'] ?? 14 ) ) );
			$section['desktop_layout'] = array_key_exists( $bc_desktop_layout, jluxe_hb_collage_desktop_layouts() ) ? $bc_desktop_layout : 'asymmetric_5';
			$section['mobile_layout']  = array_key_exists( $bc_mobile_layout, jluxe_hb_collage_mobile_layouts() ) ? $bc_mobile_layout : 'stacked';
			$section['slots']          = array();

			for ( $bc_i = 0; $bc_i < 5; $bc_i++ ) {
				$bc_slot   = $posted['slots'][ $bc_i ] ?? array();
				$bc_fit    = isset( $bc_slot['image_fit'] ) ? sanitize_key( $bc_slot['image_fit'] ) : 'cover';
				$bc_layers = array();

				foreach ( $bc_slot['layers'] ?? array() as $bc_layer ) {
					if ( empty( $bc_layer['text'] ) && empty( $bc_layer['link'] ) ) {
						continue; // نوشته‌ی کاملاً خالی، نگه‌داشتنش فایده نداره.
					}
					$bc_layers[] = array(
						'text'              => isset( $bc_layer['text'] ) ? sanitize_text_field( $bc_layer['text'] ) : '',
						'link'              => isset( $bc_layer['link'] ) ? esc_url_raw( $bc_layer['link'] ) : '',
						'color'             => isset( $bc_layer['color'] ) ? ( sanitize_hex_color( $bc_layer['color'] ) ?: '' ) : '',
						'bold'              => ! empty( $bc_layer['bold'] ),
						'nowrap'            => ! empty( $bc_layer['nowrap'] ),
						'as_button'         => ! empty( $bc_layer['as_button'] ),
						'hide_mobile'       => ! empty( $bc_layer['hide_mobile'] ),
						'desktop_x'         => max( 0, min( 100, absint( $bc_layer['desktop_x'] ?? 50 ) ) ),
						'desktop_y'         => max( 0, min( 100, absint( $bc_layer['desktop_y'] ?? 82 ) ) ),
						'desktop_font_size' => max( 10, min( 48, absint( $bc_layer['desktop_font_size'] ?? 20 ) ) ),
						'mobile_x'          => max( 0, min( 100, absint( $bc_layer['mobile_x'] ?? 50 ) ) ),
						'mobile_y'          => max( 0, min( 100, absint( $bc_layer['mobile_y'] ?? 82 ) ) ),
						'mobile_font_size'  => max( 10, min( 48, absint( $bc_layer['mobile_font_size'] ?? 18 ) ) ),
					);
				}

				$section['slots'][] = array(
					'image_id'  => isset( $bc_slot['image_id'] ) ? absint( $bc_slot['image_id'] ) : 0,
					'link'      => isset( $bc_slot['link'] ) ? esc_url_raw( $bc_slot['link'] ) : '',
					'image_fit' => in_array( $bc_fit, array( 'cover', 'contain' ), true ) ? $bc_fit : 'cover',
					'bg'        => isset( $bc_slot['bg'] ) ? ( sanitize_hex_color( $bc_slot['bg'] ) ?: '' ) : '',
					'shadow'    => ! empty( $bc_slot['shadow'] ),
					'layers'    => $bc_layers,
				);
			}
			break;

		case 'recommended_panels':
			$rp_sorts          = array( 'date', 'rating', 'popularity', 'rand' );
			$section['title']  = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			$section['panels'] = array();
			for ( $i = 0; $i < 4; $i++ ) {
				$panel = $posted['panels'][ $i ] ?? array();
				if ( empty( $panel['category'] ) ) {
					continue;
				}
				$rp_sort = isset( $panel['sort'] ) ? sanitize_key( $panel['sort'] ) : 'date';
				$section['panels'][] = array(
					'title'        => isset( $panel['title'] ) ? sanitize_text_field( $panel['title'] ) : '',
					'subtitle'     => isset( $panel['subtitle'] ) ? sanitize_text_field( $panel['subtitle'] ) : '',
					'category'     => absint( $panel['category'] ),
					'count'        => max( 2, min( 8, absint( $panel['count'] ?? 4 ) ) ),
					'sort'         => in_array( $rp_sort, $rp_sorts, true ) ? $rp_sort : 'date',
					'button_text'  => isset( $panel['button_text'] ) ? sanitize_text_field( $panel['button_text'] ) : 'بیشتر',
					'button_link'  => isset( $panel['button_link'] ) ? esc_url_raw( $panel['button_link'] ) : '',
					'button_color' => isset( $panel['button_color'] ) ? ( sanitize_hex_color( $panel['button_color'] ) ?: '' ) : '',
				);
			}
			break;

		case 'product_grid':
			$query  = isset( $posted['query'] ) ? sanitize_key( $posted['query'] ) : 'newest';
			$layout = isset( $posted['layout'] ) ? sanitize_key( $posted['layout'] ) : 'grid';
			$style  = isset( $posted['style'] ) ? sanitize_key( $posted['style'] ) : 'plain';
			$section['title']           = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			$section['subtitle']        = isset( $posted['subtitle'] ) ? sanitize_text_field( $posted['subtitle'] ) : '';
			$section['query']           = in_array( $query, array( 'newest', 'sale', 'bestseller', 'featured' ), true ) ? $query : 'newest';
			$section['category']        = isset( $posted['category'] ) ? sanitize_text_field( $posted['category'] ) : '';
			$section['count']           = max( 2, min( 16, absint( $posted['count'] ?? 8 ) ) );
			$section['columns_desktop'] = max( 2, min( 6, absint( $posted['columns_desktop'] ?? 4 ) ) );
			$section['columns_tablet']  = max( 2, min( 4, absint( $posted['columns_tablet'] ?? 3 ) ) );
			$section['columns_mobile']  = max( 1, min( 3, absint( $posted['columns_mobile'] ?? 2 ) ) );
			// layout: grid (چیدمان معمولی) | carousel (اسکرول افقی، مثل «فروش ویژه» مرجع).
			$section['layout'] = in_array( $layout, array( 'grid', 'carousel' ), true ) ? $layout : 'grid';
			// style: plain | highlight (پس‌زمینه‌ی گرادیانِ رنگ اصلی + عنوان سفید، برای برجسته‌کردن یک ردیف — مثل «فروش ویژه»).
			$section['style'] = in_array( $style, array( 'plain', 'highlight' ), true ) ? $style : 'plain';
			break;

		case 'category_grid':
		case 'category_showcase':
			$layout = isset( $posted['layout'] ) ? sanitize_key( $posted['layout'] ) : 'grid';
			$section['title']  = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			if ( 'category_grid' === $type ) {
				$section['layout'] = 'row';
				$section['icon_svg'] = isset( $posted['icon_svg'] ) ? jluxe_sanitize_svg_markup( (string) $posted['icon_svg'] ) : '';
			}
			// layout: grid (شبکه‌ی معمولی) | row (نوار افقی اسکرول‌شونده با آیکون دایره‌ای، مثل «دسته‌ها» مرجع).
			if ( 'category_grid' !== $type ) {
				$section['layout'] = in_array( $layout, array( 'grid', 'row' ), true ) ? $layout : 'grid';
			}
			$shape = isset( $posted['image_shape'] ) ? sanitize_key( $posted['image_shape'] ) : 'circle';
			$section['image_shape'] = in_array( $shape, array( 'circle', 'square', 'none' ), true ) ? $shape : 'circle';
			$alignment = isset( $posted['alignment'] ) ? sanitize_key( $posted['alignment'] ) : 'center';
			$section['alignment'] = in_array( $alignment, array( 'start', 'center', 'end' ), true ) ? $alignment : 'center';
			$section['section_radius'] = max( 12, min( 56, absint( $posted['section_radius'] ?? 40 ) ) );
			$section['card_radius'] = max( 0, min( 40, absint( $posted['card_radius'] ?? 20 ) ) );
			$section['image_size'] = max( 48, min( 140, absint( $posted['image_size'] ?? ( 'category_grid' === $type ? 80 : 146 ) ) ) );
			$image_bg_mode = sanitize_key( $posted['image_bg_mode'] ?? 'color' );
			$section['image_bg_mode'] = in_array( $image_bg_mode, array( 'color', 'transparent' ), true ) ? $image_bg_mode : 'color';
			$section['image_bg_color'] = sanitize_hex_color( $posted['image_bg_color'] ?? '' ) ?: '#FFFFFF';
			if ( 'category_showcase' === $type ) {
				$section_bg_mode = sanitize_key( $posted['section_bg_mode'] ?? 'color' );
				$section['section_bg_mode'] = in_array( $section_bg_mode, array( 'color', 'transparent' ), true ) ? $section_bg_mode : 'color';
				$section['section_bg_color'] = sanitize_hex_color( $posted['section_bg_color'] ?? '' ) ?: '#F7F7F5';
			}
			$card_shadow = sanitize_key( $posted['card_shadow'] ?? 'soft' );
			$section['card_shadow'] = in_array( $card_shadow, array( 'none', 'soft', 'medium', 'strong' ), true ) ? $card_shadow : 'soft';
			$section['hover_lift'] = array_key_exists( 'hover_lift', $posted ) && '1' === (string) $posted['hover_lift'];
			$section['zoom_enabled'] = array_key_exists( 'zoom_enabled', $posted ) && '1' === (string) $posted['zoom_enabled'];
			// دسته‌بندی‌ها دیگه خودکار از همه‌ی ترم‌های ووکامرس pull نمی‌شن
			// (باگ واقعی: با hide_empty=true، دسته‌های تازه‌ساخته‌شده‌ی بدون
			// محصول هیچ‌وقت نشون داده نمی‌شدن — دقیقاً چیزی که گزارش شد).
			// حالا کاملاً دستیه: ادمین از یک لیست واقعی از دسته‌های ووکامرس
			// انتخاب و مرتب می‌کنه؛ term_id نامعتبر/حذف‌شده نادیده گرفته می‌شه.
			$section['items'] = array();
			foreach ( $posted['items'] ?? array() as $item ) {
				$term_id = isset( $item['term_id'] ) ? absint( $item['term_id'] ) : 0;
				if ( ! $term_id || ! term_exists( $term_id, 'product_cat' ) ) {
					continue;
				}
				$section['items'][] = array(
					'term_id'      => $term_id,
					'image_id'     => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'display_name' => isset( $item['display_name'] ) ? sanitize_text_field( $item['display_name'] ) : '',
				);
			}
			break;

		case 'brand_marquee':
			$section['title'] = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : 'برندهای منتخب';
			$section['speed_sec'] = max( 15, min( 120, absint( $posted['speed_sec'] ?? 36 ) ) );
			$section['grayscale'] = array_key_exists( 'grayscale', $posted ) && '1' === (string) $posted['grayscale'];
			$section['pause_hover'] = array_key_exists( 'pause_hover', $posted ) && '1' === (string) $posted['pause_hover'];
			$section['items'] = array();
			foreach ( $posted['items'] ?? array() as $item ) {
				$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
				if ( ! $image_id ) continue;
				$section['items'][] = array( 'image_id' => $image_id, 'title' => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '', 'link' => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '' );
			}
			break;

		case 'blog':
			$section['title'] = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : 'از وبلاگ';
			$section['count'] = max( 2, min( 8, absint( $posted['count'] ?? 3 ) ) );
			break;

		case 'banner':
		case 'banner_two':
		case 'banner_three':
			$count            = 'banner' === $type ? 1 : ( 'banner_two' === $type ? 2 : 3 );
			$positions        = array( 'bottom-start', 'bottom-center', 'bottom-end', 'center', 'top-start', 'top-end' );
			$button_styles    = array( 'solid', 'white', 'outline-white', 'outline-primary' );
			$section['items'] = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$item     = $posted['items'][ $i ] ?? array();
				$position = isset( $item['content_position'] ) ? sanitize_key( $item['content_position'] ) : 'bottom-start';
				$b_style  = isset( $item['button_style'] ) ? sanitize_key( $item['button_style'] ) : 'solid';
				$section['items'][] = array(
					'image_id'         => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'title'            => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
					'subtitle'         => isset( $item['subtitle'] ) ? sanitize_text_field( $item['subtitle'] ) : '',
					'button'           => isset( $item['button'] ) ? sanitize_text_field( $item['button'] ) : '',
					'link'             => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '',
					// پیش‌فرض overlay=روشن تا رفتار قبلیِ سایت‌های موجود عوض نشه.
					'overlay'          => ! empty( $item['overlay'] ),
					'zoom_enabled'     => array_key_exists( 'zoom_enabled', $item ) ? ! empty( $item['zoom_enabled'] ) : true,
					'shine_enabled'    => array_key_exists( 'shine_enabled', $item ) ? ! empty( $item['shine_enabled'] ) : true,
					'content_position' => in_array( $position, $positions, true ) ? $position : 'bottom-start',
					'button_style'     => in_array( $b_style, $button_styles, true ) ? $b_style : 'solid',
					'text_color'       => isset( $item['text_color'] ) ? ( sanitize_hex_color( $item['text_color'] ) ?: '' ) : '',
					'button_color'     => isset( $item['button_color'] ) ? ( sanitize_hex_color( $item['button_color'] ) ?: '' ) : '',
				);
			}
			break;

		case 'trust':
			$section['title'] = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			break;

		case 'text':
			$section['title']   = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : '';
			$section['content'] = isset( $posted['content'] ) ? wp_kses_post( wp_unslash( $posted['content'] ) ) : '';
			break;

		case 'html':
			$section['content'] = isset( $posted['content'] ) ? wp_unslash( $posted['content'] ) : ''; // manage_options only، مثل Custom CSS/JS.
			break;

		case 'spacer':
			$section['height'] = max( 8, min( 200, absint( $posted['height'] ?? 40 ) ) );
			break;
	}

	return $section;
}

function jluxe_sanitize_homepage( array $posted, array $defaults ): array {
	if ( empty( $posted['sections'] ) || ! is_array( $posted['sections'] ) ) {
		return $defaults;
	}
	$sections = array();
	foreach ( $posted['sections'] as $raw_section ) {
		if ( is_array( $raw_section ) ) {
			$sections[] = jluxe_sanitize_homepage_section( $raw_section );
		}
	}
	return array( 'sections' => $sections );
}
