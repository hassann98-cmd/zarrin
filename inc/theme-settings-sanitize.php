<?php
/**
 * توابع sanitize — یکی به‌ازای هر بخش از jluxe_theme_settings. هر تابع
 * $posted (از $_POST['section'][...]) و $defaults[section] رو می‌گیره و
 * یک آرایه‌ی تمیز و امن برمی‌گردونه. هیچ‌کدوم مستقیم به دیتابیس نمی‌نویسن —
 * فقط sanitize/validate؛ نوشتن با update_option در theme-settings-admin.php.
 */

defined( 'ABSPATH' ) || exit;

/** All sanitizers accept UNSLASHED values. Only HTTP form handlers call wp_unslash(). */
function jluxe_settings_sanitizers(): array {
	$schema = array();
	foreach ( jluxe_settings_sections_map() as $section ) {
		$schema[ $section[0] ] = $section[1];
	}
	return $schema;
}

/** A missing section in an older backup is preserved, not silently reset. */
function jluxe_sanitize_settings_payload( array $incoming, array $current ): array {
	$defaults = jluxe_theme_settings_defaults();
	$clean = array( 'version' => JLUXE_SETTINGS_VERSION );
	foreach ( jluxe_settings_sanitizers() as $key => $sanitize ) {
		if ( ! array_key_exists( $key, $incoming ) ) {
			$clean[ $key ] = $current[ $key ] ?? $defaults[ $key ];
			continue;
		}
		if ( ! is_array( $incoming[ $key ] ) ) {
			throw new InvalidArgumentException( 'Invalid settings section: ' . $key );
		}
		$clean[ $key ] = $sanitize( $incoming[ $key ], $defaults[ $key ] );
		if ( 'custom_code' === $key && ! current_user_can( 'unfiltered_html' ) && $clean[ $key ] !== ( $current[ $key ] ?? $defaults[ $key ] ) ) {
			throw new InvalidArgumentException( 'Changing custom code requires unfiltered_html.' );
		}
	}
	return $clean;
}


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
	$search_placeholder = isset( $posted['search_placeholder'] ) && is_scalar( $posted['search_placeholder'] )
		? sanitize_text_field( (string) $posted['search_placeholder'] )
		: (string) ( $defaults['search_placeholder'] ?? 'جستجو در فروشگاه' );
	if ( '' === trim( $search_placeholder ) ) {
		$search_placeholder = (string) ( $defaults['search_placeholder'] ?? 'جستجو در فروشگاه' );
	}

	return array(
		'sticky'               => ! empty( $posted['sticky'] ),
		'search_placeholder'   => $search_placeholder,
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
 * اعلانِ بالای سایت — متنِ ساده یا شناسهٔ attachment تصویر، و لینکِ امنِ داخلی
 * یا HTTP(S) ذخیره شود؛ هیچ HTML یا scheme اجرایی از تنظیماتِ ادمین به صفحهٔ عمومی راه پیدا نکند.
 */
function jluxe_sanitize_announcement_bar( array $posted, array $defaults ): array {
	$message_raw   = isset( $posted['message'] ) && is_scalar( $posted['message'] ) ? (string) $posted['message'] : '';
	$link_text_raw = isset( $posted['link_text'] ) && is_scalar( $posted['link_text'] ) ? (string) $posted['link_text'] : '';
	$link_url_raw  = isset( $posted['link_url'] ) && is_scalar( $posted['link_url'] ) ? trim( (string) $posted['link_url'] ) : '';
	$link_url      = '' !== $link_url_raw ? esc_url_raw( $link_url_raw ) : '';
	$has_invalid_url_characters = '' !== $link_url && (
		false !== strpos( $link_url, chr( 39 ) ) ||
		false !== strpos( $link_url, '"' ) ||
		(bool) preg_match( '/[\x00-\x20<>\\\\]/', $link_url )
	);
	$is_relative   = '' !== $link_url && ! $has_invalid_url_characters && (bool) preg_match( '~^(?:/(?!/)[^\s]*|[?#][^\s]*)$~', $link_url );
	$is_absolute   = '' !== $link_url && ! $has_invalid_url_characters && (bool) preg_match( '~^https?://[^/\s]+(?:[/\?#][^\s]*)?$~i', $link_url );
	if ( ! $is_relative && ! $is_absolute ) {
		$link_url = '';
	}

	$background_color = isset( $posted['background_color'] ) && is_scalar( $posted['background_color'] )
		? sanitize_hex_color( (string) $posted['background_color'] )
		: null;
	$text_color = isset( $posted['text_color'] ) && is_scalar( $posted['text_color'] )
		? sanitize_hex_color( (string) $posted['text_color'] )
		: null;

	$display_mode = isset( $posted['display_mode'] ) && is_scalar( $posted['display_mode'] )
		? sanitize_key( (string) $posted['display_mode'] )
		: ( $defaults['display_mode'] ?? 'text' );
	if ( ! in_array( $display_mode, array( 'text', 'image' ), true ) ) {
		$display_mode = 'text';
	}
	$image_id = isset( $posted['image_id'] ) && is_scalar( $posted['image_id'] )
		? absint( $posted['image_id'] )
		: absint( $defaults['image_id'] ?? 0 );
	$image_alt_raw = isset( $posted['image_alt'] ) && is_scalar( $posted['image_alt'] )
		? (string) $posted['image_alt']
		: (string) ( $defaults['image_alt'] ?? '' );

	return array(
		'enabled'          => ! empty( $posted['enabled'] ),
		'sticky'           => ! empty( $posted['sticky'] ),
		'display_mode'     => $display_mode,
		'message'          => sanitize_textarea_field( $message_raw ),
		'image_id'         => $image_id,
		'image_alt'        => sanitize_text_field( $image_alt_raw ),
		'link_text'        => sanitize_text_field( $link_text_raw ),
		'link_url'         => $link_url,
		'dismissible'      => ! empty( $posted['dismissible'] ),
		'background_color' => $background_color ?: $defaults['background_color'],
		'text_color'       => $text_color ?: $defaults['text_color'],
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
				'svg'   => isset( $child['svg'] ) ? jluxe_sanitize_svg_markup( $child['svg'] ) : '',
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
			'svg'      => isset( $item['svg'] ) ? jluxe_sanitize_svg_markup( $item['svg'] ) : '',
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
			'svg'      => isset( $posted['feature_cards'][ $i ]['svg'] ) ? jluxe_sanitize_svg_markup( $posted['feature_cards'][ $i ]['svg'] ) : '',
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
			$decoded = json_decode( $trust_badges_raw, true );
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
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		unset( $allowed_badge_html['script'], $allowed_badge_html['a']['onclick'], $allowed_badge_html['img']['onclick'] );
	}

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
				$img = '<img src="' . esc_url( $image_url ) . '" alt="نماد اعتماد" style="width:100%;height:auto;max-width:100%;object-fit:contain;" />';
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
				'svg'   => isset( $link_in['svg'] ) ? jluxe_sanitize_svg_markup( $link_in['svg'] ) : '',
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
		'brand_description'            => isset( $posted['brand_description'] ) ? sanitize_textarea_field( $posted['brand_description'] ) : $defaults['brand_description'],
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
			$out[ $page_key ]['story_html']       = isset( $row['story_html'] ) ? wp_kses_post( $row['story_html'] ) : ( $default_row['story_html'] ?? '' );
			$out[ $page_key ]['cta_text']         = isset( $row['cta_text'] ) ? sanitize_text_field( $row['cta_text'] ) : ( $default_row['cta_text'] ?? '' );
			$out[ $page_key ]['cta_button_text']  = isset( $row['cta_button_text'] ) ? sanitize_text_field( $row['cta_button_text'] ) : ( $default_row['cta_button_text'] ?? '' );
			$out[ $page_key ]['cta_button_url']   = isset( $row['cta_button_url'] ) ? esc_url_raw( $row['cta_button_url'] ) : ( $default_row['cta_button_url'] ?? '' );
			$out[ $page_key ]['cta_button_color'] = isset( $row['cta_button_color'] ) ? sanitize_hex_color( $row['cta_button_color'] ) ?? '' : ( $default_row['cta_button_color'] ?? '' );
			$out[ $page_key ]['why_html'] = isset( $row['why_html'] ) ? wp_kses_post( $row['why_html'] ) : ( $default_row['why_html'] ?? '' );
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
				'eyebrow'        => isset( $row['eyebrow'] ) ? sanitize_text_field( $row['eyebrow'] ) : ( $default_row['eyebrow'] ?? '' ),
				'title'          => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : ( $default_row['title'] ?? '' ),
				'intro'          => isset( $row['intro'] ) ? sanitize_textarea_field( $row['intro'] ) : ( $default_row['intro'] ?? '' ),
				'button_text'    => isset( $row['button_text'] ) ? sanitize_text_field( $row['button_text'] ) : ( $default_row['button_text'] ?? '' ),
				'button_url'     => isset( $row['button_url'] ) ? esc_url_raw( $row['button_url'] ) : ( $default_row['button_url'] ?? '' ),
				'button_color'   => isset( $row['button_color'] ) ? ( sanitize_hex_color( $row['button_color'] ) ?? '' ) : ( $default_row['button_color'] ?? '' ),
				'body_html'      => isset( $row['body_html'] ) ? wp_kses_post( $row['body_html'] ) : ( $default_row['body_html'] ?? '' ),
				'button_enabled' => ! empty( $row['button_enabled'] ),
				'steps'          => array(),
			);
			$default_steps = isset( $default_row['steps'] ) && is_array( $default_row['steps'] ) ? $default_row['steps'] : array();
			$posted_steps  = isset( $row['steps'] ) && is_array( $row['steps'] ) ? $row['steps'] : array();
			for ( $i = 0; $i < 5; $i++ ) {
				$d = isset( $default_steps[ $i ] ) && is_array( $default_steps[ $i ] ) ? $default_steps[ $i ] : array();
				$r = isset( $posted_steps[ $i ] ) && is_array( $posted_steps[ $i ] ) ? $posted_steps[ $i ] : array();
				$clean['steps'][] = array(
					'title'        => isset( $r['title'] ) ? sanitize_text_field( $r['title'] ) : ( $d['title'] ?? '' ),
					'content'      => isset( $r['content'] ) ? wp_kses_post( $r['content'] ) : ( $d['content'] ?? '' ),
					'callout_type' => isset( $r['callout_type'] ) && in_array( $r['callout_type'], array( '', 'tip', 'note' ), true ) ? $r['callout_type'] : ( $d['callout_type'] ?? '' ),
					'callout_title'=> isset( $r['callout_title'] ) ? sanitize_text_field( $r['callout_title'] ) : ( $d['callout_title'] ?? '' ),
					'callout_text' => isset( $r['callout_text'] ) ? sanitize_textarea_field( $r['callout_text'] ) : ( $d['callout_text'] ?? '' ),
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
				$clean[ $field_key ] = wp_kses_post( $row[ $field_key ] );
			} elseif ( 'button_url' === $field_key ) {
				$clean[ $field_key ] = esc_url_raw( $row[ $field_key ] );
			} elseif ( 'button_color' === $field_key ) {
				$clean[ $field_key ] = sanitize_hex_color( $row[ $field_key ] ) ?? '';
			} elseif ( 'intro' === $field_key || 'title' === $field_key || str_ends_with( $field_key, '_text' ) ) {
				$clean[ $field_key ] = sanitize_textarea_field( $row[ $field_key ] );
			} else {
				$clean[ $field_key ] = sanitize_text_field( $row[ $field_key ] );
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
		'card_number' => isset( $posted['card_number'] ) ? sanitize_text_field( $posted['card_number'] ) : ( $defaults['card_number'] ?? '' ),
		'sheba'       => isset( $posted['sheba'] ) ? sanitize_text_field( $posted['sheba'] ) : ( $defaults['sheba'] ?? '' ),
		'holder_name' => isset( $posted['holder_name'] ) ? sanitize_text_field( $posted['holder_name'] ) : ( $defaults['holder_name'] ?? '' ),
		'bank_name'   => isset( $posted['bank_name'] ) ? sanitize_text_field( $posted['bank_name'] ) : ( $defaults['bank_name'] ?? '' ),
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
			'svg'     => isset( $posted[ $key ]['svg'] ) ? jluxe_sanitize_svg_markup( $posted[ $key ]['svg'] ) : '',
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
 * ترتیبِ درگ‌شده حفظ می‌شود؛ شناسه، عملکرد، آیکون و URL فقط از فهرست‌های امن
 * پذیرفته می‌شوند. آیتم‌های حذف‌شده از POST نیز با مقدارِ پیش‌فرض برمی‌گردند.
 */
function jluxe_sanitize_mobile( array $posted, array $defaults ): array {
	$valid_ids      = wp_list_pluck( $defaults['nav_items'], 'id' );
	$defaults_by_id = array();
	foreach ( $defaults['nav_items'] as $default_item ) {
		$defaults_by_id[ $default_item['id'] ] = $default_item;
	}

	$nav_items        = array();
	$seen_ids         = array();
	$posted_nav_items = isset( $posted['nav_items'] ) && is_array( $posted['nav_items'] ) ? $posted['nav_items'] : array();
	foreach ( $posted_nav_items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$id = isset( $item['id'] ) && is_scalar( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
		if ( ! in_array( $id, $valid_ids, true ) || in_array( $id, $seen_ids, true ) ) {
			continue;
		}
		$seen_ids[] = $id;
		$label      = isset( $item['label'] ) && is_scalar( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
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

	$floating_defaults = $defaults['floating_nav_items'] ?? array();
	$floating_ids      = wp_list_pluck( $floating_defaults, 'id' );
	$floating_by_id    = array();
	foreach ( $floating_defaults as $default_item ) {
		$floating_by_id[ $default_item['id'] ] = $default_item;
	}
	$valid_icons   = array_keys( function_exists( 'jluxe_mobile_nav_icon_options' ) ? jluxe_mobile_nav_icon_options() : ( function_exists( 'jluxe_nav_icon_options' ) ? jluxe_nav_icon_options() : array() ) );
	$valid_actions = array_keys( function_exists( 'jluxe_mobile_nav_action_options' ) ? jluxe_mobile_nav_action_options() : array() );
	$sanitize_url  = static function ( $value ): string {
		$url = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );
		if ( '' === $url || 0 === strpos( $url, '//' ) || preg_match( '/^\s*(?:javascript|data|vbscript):/i', $url ) ) {
			return '';
		}
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		if ( $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
			return '';
		}
		return esc_url_raw( $url );
	};

	$floating_items        = array();
	$floating_seen         = array();
	$posted_floating_items = isset( $posted['floating_nav_items'] ) && is_array( $posted['floating_nav_items'] ) ? $posted['floating_nav_items'] : array();
	foreach ( $posted_floating_items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$id = isset( $item['id'] ) && is_scalar( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
		if ( ! in_array( $id, $floating_ids, true ) || in_array( $id, $floating_seen, true ) ) {
			continue;
		}
		$floating_seen[] = $id;
		$default         = $floating_by_id[ $id ];
		$label           = isset( $item['label'] ) && is_scalar( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
		$icon            = isset( $item['icon'] ) && is_scalar( $item['icon'] ) ? sanitize_key( (string) $item['icon'] ) : $default['icon'];
		$action          = isset( $item['action'] ) && is_scalar( $item['action'] ) ? sanitize_key( (string) $item['action'] ) : $default['action'];
		$url             = $sanitize_url( $item['url'] ?? '' );
		if ( ! in_array( $icon, $valid_icons, true ) ) {
			$icon = $default['icon'];
		}
		if ( ! in_array( $action, $valid_actions, true ) ) {
			$action = $default['action'];
		}
		if ( 'link' === $action && '' === $url ) {
			$action = $default['action'];
		}
		$floating_items[] = array(
			'id'           => $id,
			'label'        => '' !== $label ? $label : $default['label'],
			'icon'         => $icon,
			'action'       => $action,
			'url'          => $url,
			'target_blank' => ! empty( $item['target_blank'] ),
			'enabled'      => ! empty( $item['enabled'] ),
		);
	}
	foreach ( $floating_ids as $id ) {
		if ( ! in_array( $id, $floating_seen, true ) ) {
			$floating_items[] = $floating_by_id[ $id ];
		}
	}

	$style          = isset( $posted['nav_style'] ) && is_array( $posted['nav_style'] ) ? $posted['nav_style'] : array();
	$hex            = static function ( $value, $fallback ) {
		$value = sanitize_hex_color( is_scalar( $value ) ? (string) $value : '' );
		return $value ?: $fallback;
	};
	$style_defaults = $defaults['nav_style'];
	$shadow_raw     = $style['shadow'] ?? $style_defaults['shadow'];
	$radius_raw     = $style['radius'] ?? $style_defaults['radius'];
	$height_raw     = $style['height'] ?? $style_defaults['height'];
	$shadow         = sanitize_key( is_scalar( $shadow_raw ) ? (string) $shadow_raw : $style_defaults['shadow'] );
	$radius         = max( 12, min( 32, absint( is_scalar( $radius_raw ) ? $radius_raw : $style_defaults['radius'] ) ) );
	$height         = max( 60, min( 82, absint( is_scalar( $height_raw ) ? $height_raw : $style_defaults['height'] ) ) );
	$variant        = isset( $posted['nav_variant'] ) && is_scalar( $posted['nav_variant'] ) ? sanitize_key( (string) $posted['nav_variant'] ) : 'classic';

	return array(
		'nav_variant'        => 'floating' === $variant ? 'floating' : 'classic',
		'nav_items'          => $nav_items,
		'floating_nav_items' => $floating_items,
		'nav_style'          => array(
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
		'in_stock_color'   => isset( $posted['in_stock_color'] ) ? ( sanitize_hex_color( $posted['in_stock_color'] ) ?: '' ) : $defaults['in_stock_color'],
	);
}

/** بخش «AI دیدگاه‌ها»: پاسخ خودکار + خلاصهٔ قابل‌ویرایش هر محصول (ai-comments.php). */
function jluxe_sanitize_ai_comments( array $posted, array $defaults ): array {
	$interval = absint( $posted['cron_interval'] ?? $defaults['cron_interval'] );
	if ( ! in_array( $interval, array( 1, 5, 10, 15, 30, 60 ), true ) ) {
		$interval = (int) $defaults['cron_interval'];
	}
	$avatar = isset( $posted['responder_avatar'] ) ? trim( (string) esc_url_raw( $posted['responder_avatar'] ) ) : '';

	return array(
		'enabled'               => ! empty( $posted['enabled'] ),
		'auto_reply_enabled'    => ! empty( $posted['auto_reply_enabled'] ),
		'cron_interval'         => $interval,
		'responder_name'        => jluxe_substr(sanitize_text_field( $posted['responder_name'] ?? '' ), 0, 60),
		// آواتار فقط http(s) مجاز است (esc_url_raw بدون پروتکل‌های عجیب).
		'responder_avatar'      => in_array( wp_parse_url( $avatar, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? esc_url_raw( $avatar ) : '',
		'personality'           => jluxe_substr(sanitize_textarea_field( $posted['personality'] ?? '' ), 0, 1000),
		'store_description'     => jluxe_substr(sanitize_textarea_field( $posted['store_description'] ?? '' ), 0, 1000),
		'max_replies_per_run'   => max( 1, min( 20, absint( $posted['max_replies_per_run'] ?? $defaults['max_replies_per_run'] ) ) ),
		'max_summaries_per_run' => max( 1, min( 10, absint( $posted['max_summaries_per_run'] ?? $defaults['max_summaries_per_run'] ) ) ),
	);
}

function jluxe_sanitize_shop( array $posted, array $defaults ): array {
	return array(
		'products_per_page'          => max( 4, min( 48, absint( $posted['products_per_page'] ?? $defaults['products_per_page'] ) ) ),
		'columns_desktop'            => max( 2, min( 6, absint( $posted['columns_desktop'] ?? $defaults['columns_desktop'] ) ) ),
		'columns_tablet'             => max( 2, min( 4, absint( $posted['columns_tablet'] ?? $defaults['columns_tablet'] ) ) ),
		'columns_mobile'             => max( 1, min( 3, absint( $posted['columns_mobile'] ?? $defaults['columns_mobile'] ) ) ),
		'out_of_stock_last'            => ! empty( $posted['out_of_stock_last'] ),
		'show_account_downloads_tab' => ! empty( $posted['show_account_downloads_tab'] ),
		'mini_cart_show_coupon'        => ! empty( $posted['mini_cart_show_coupon'] ),
		'mini_cart_show_free_shipping' => ! empty( $posted['mini_cart_show_free_shipping'] ),
		'auto_scroll_carousels'        => ! empty( $posted['auto_scroll_carousels'] ),
	);
}

/**
 * «آیتم‌های اضافه خرید» — سخت‌گیرانه: حالت در فهرستِ بسته، شناسه‌های ثابت
 * عددیِ یکتا (سقف ۲۰)، خدمات حداکثر ۸ ردیف با عنوانِ کوتاه، مبلغِ
 * نامنفی و محلِ نمایشِ مجاز. مبلغِ فِی هرگز از کلاینت پذیرفته نمی‌شود؛
 * هنگامِ ثبتِ فِی دوباره از همین تنظیمات ذخیره‌شده خوانده می‌شود.
 */
function jluxe_sanitize_purchase_addons( array $posted, array $defaults ): array {
	$mode = isset( $posted['mode'] ) ? (string) $posted['mode'] : $defaults['mode'];
	if ( ! in_array( $mode, array( 'fixed', 'per_product', 'per_category', 'random' ), true ) ) {
		$mode = $defaults['mode'];
	}

	$fixed_raw = $posted['fixed_ids_csv'] ?? $posted['fixed_ids'] ?? $defaults['fixed_ids'];
	if ( is_string( $fixed_raw ) ) {
		$fixed_raw = preg_split( '/[،,]+/u', $fixed_raw ) ?: array();
	}
	$fixed_ids = array();
	foreach ( (array) $fixed_raw as $id ) {
		$id = absint( $id );
		if ( $id > 0 ) {
			$fixed_ids[] = $id;
		}
	}
	$fixed_ids = array_slice( array_values( array_unique( $fixed_ids ) ), 0, 20 );

	$services = array();
	foreach ( (array) ( $posted['services'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		if ( '' === $title ) {
			continue;
		}
		$amount = (float) str_replace( array( '،', ',' ), '', (string) ( $row['amount'] ?? 0 ) );
		if ( $amount < 0 ) {
			$amount = 0.0;
		}
		$context = (string) ( $row['context'] ?? 'modal' );
		if ( ! in_array( $context, array( 'modal', 'cart', 'both' ), true ) ) {
			$context = 'modal';
		}
		$services[] = array(
			'title'   => jluxe_substr($title, 0, 60),
			'amount'  => min( $amount, 999999999 ),
			'context' => $context,
			'auto'    => ! empty( $row['auto'] ),
		);
		if ( count( $services ) >= 8 ) {
			break;
		}
	}

	$max_products = isset( $posted['max_products'] ) && is_scalar( $posted['max_products'] )
		? absint( $posted['max_products'] )
		: absint( $defaults['max_products'] ?? 4 );
	$text_defaults = array(
		'modal_title'            => 'افزودن به سبد خرید',
		'products_heading'       => 'ممکن است این‌ها را هم لازم داشته باشید',
		'services_heading'       => 'این خدمات را هم اضافه کنید',
		'total_label'            => 'مبلغ قابل پرداخت',
		'confirm_selected_label' => 'افزودن انتخاب‌ها به سبد',
		'confirm_empty_label'    => 'ادامه بدون افزودن',
		'cart_summary_label'     => 'سبد شما',
		'view_cart_label'        => 'مشاهده سبد',
		'continue_label'         => 'ادامه خرید',
	);
	$text_settings = array();
	foreach ( $text_defaults as $key => $fallback ) {
		$default = isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) ? (string) $defaults[ $key ] : $fallback;
		$raw     = isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? (string) $posted[ $key ] : $default;
		$value   = jluxe_substr( sanitize_text_field( $raw ), 0, 100 );
		$text_settings[ $key ] = '' !== $value ? $value : $default;
	}

	return array_merge(
		array(
			'enabled'           => ! empty( $posted['enabled'] ),
			'mode'              => $mode,
			'fixed_ids'         => $fixed_ids,
			'max_products'      => max( 1, min( 4, $max_products ) ),
			'show_main_product' => ! empty( $posted['show_main_product'] ),
			'show_products'     => ! empty( $posted['show_products'] ),
			'show_services'     => ! empty( $posted['show_services'] ),
			'show_cart_summary' => ! empty( $posted['show_cart_summary'] ),
			'show_cart_link'    => ! empty( $posted['show_cart_link'] ),
			'show_continue'     => ! empty( $posted['show_continue'] ),
			'show_total'        => ! empty( $posted['show_total'] ),
			'services'          => $services,
		),
		$text_settings
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
		'discount_color' => isset( $posted['discount_color'] ) ? ( sanitize_hex_color( $posted['discount_color'] ) ?: '' ) : $defaults['discount_color'],
		'savings_color'  => isset( $posted['savings_color'] ) ? ( sanitize_hex_color( $posted['savings_color'] ) ?: '' ) : $defaults['savings_color'],
		'star_color'     => isset( $posted['star_color'] ) ? ( sanitize_hex_color( $posted['star_color'] ) ?: '' ) : $defaults['star_color'],
		'trust_items'    => $trust_items,
	);
}

/**
 * Normalize one verification tag. Only well-formed verification-style meta
 * names are accepted; values are stored as data and escaped when rendered.
 */
function jluxe_normalize_verification_meta_pair( $name, $content ): ?array {
	if ( ! is_scalar( $name ) || ! is_scalar( $content ) ) {
		return null;
	}
	$name    = trim( sanitize_text_field( html_entity_decode( (string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$content = trim( sanitize_text_field( html_entity_decode( (string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$name    = strtolower( $name );

	if ( '' === $content || strlen( $content ) > 512 || ! preg_match( '/^[a-z0-9][a-z0-9._:-]{0,127}$/iD', $name ) ) {
		return null;
	}
	if ( false === strpos( $name, 'verification' ) && false === strpos( $name, 'verify' ) && 'msvalidate.01' !== $name ) {
		return null;
	}

	return array(
		'name'    => $name,
		'content' => $content,
	);
}

/** Parse a single pasted <meta> element, rejecting every attribute but name/content. */
function jluxe_parse_verification_meta_tag( string $tag ): ?array {
	if ( strlen( $tag ) > 2048 || ! preg_match( '/^\s*<meta\b([^>]*)>\s*$/i', $tag, $tag_match ) ) {
		return null;
	}
	$attributes = trim( $tag_match[1] );
	if ( '/' === substr( $attributes, -1 ) ) {
		$attributes = trim( substr( $attributes, 0, -1 ) );
	}

	$attribute_pattern = '/([a-z][a-z0-9:_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i';
	$attribute_count   = preg_match_all( $attribute_pattern, $attributes, $attribute_matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL );
	if ( false === $attribute_count || 0 === $attribute_count ) {
		return null;
	}
	$unparsed = preg_replace( $attribute_pattern, '', $attributes );
	if ( ! is_string( $unparsed ) || '' !== trim( $unparsed ) ) {
		return null;
	}

	$parsed = array();
	foreach ( $attribute_matches as $attribute_match ) {
		$key = strtolower( $attribute_match[1] );
		if ( ! in_array( $key, array( 'name', 'content' ), true ) || array_key_exists( $key, $parsed ) ) {
			return null;
		}
		foreach ( array( 2, 3, 4 ) as $value_index ) {
			if ( isset( $attribute_match[ $value_index ] ) ) {
				$parsed[ $key ] = $attribute_match[ $value_index ];
				break;
			}
		}
	}

	if ( ! isset( $parsed['name'], $parsed['content'] ) ) {
		return null;
	}
	return jluxe_normalize_verification_meta_pair( $parsed['name'], $parsed['content'] );
}

/**
 * Accept pasted verification meta tags, or the structured form used by
 * settings backups. Arbitrary HTML, refresh/http-equiv tags, scripts and
 * non-verification meta names are never kept or emitted.
 */
function jluxe_sanitize_verification_meta_tags( $raw ): array {
	$candidates = array();
	if ( is_string( $raw ) ) {
		if ( strlen( $raw ) > 12288 ) {
			return array();
		}
		preg_match_all( '/<meta\b[^<>]*>/i', $raw, $matches );
		$candidates = $matches[0] ?? array();
	} elseif ( is_array( $raw ) ) {
		if ( isset( $raw['name'], $raw['content'] ) ) {
			$candidates = array( $raw );
		} else {
			$candidates = array_slice( array_values( $raw ), 0, 20 );
		}
	} else {
		return array();
	}

	$clean = array();
	$seen  = array();
	foreach ( $candidates as $candidate ) {
		if ( is_string( $candidate ) ) {
			$pair = jluxe_parse_verification_meta_tag( $candidate );
		} elseif ( is_array( $candidate ) ) {
			$pair = jluxe_normalize_verification_meta_pair( $candidate['name'] ?? null, $candidate['content'] ?? null );
		} else {
			$pair = null;
		}
		if ( ! is_array( $pair ) ) {
			continue;
		}
		$key = $pair['name'] . "\0" . $pair['content'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$clean[]      = $pair;
		if ( count( $clean ) >= 10 ) {
			break;
		}
	}
	return $clean;
}

/** Convert the safe structured setting back to pasteable markup in the admin field. */
function jluxe_verification_meta_tags_text( $tags ): string {
	$lines = array();
	foreach ( jluxe_sanitize_verification_meta_tags( $tags ) as $tag ) {
		$lines[] = '<meta name="' . esc_attr( $tag['name'] ) . '" content="' . esc_attr( $tag['content'] ) . '">';
	}
	return implode( "\n", $lines );
}

function jluxe_sanitize_seo( array $posted, array $defaults ): array {
	return array(
		'default_meta_title'       => isset( $posted['default_meta_title'] ) ? sanitize_text_field( $posted['default_meta_title'] ) : '',
		'default_meta_description' => isset( $posted['default_meta_description'] ) ? sanitize_textarea_field( $posted['default_meta_description'] ) : '',
		'og_image_id'              => isset( $posted['og_image_id'] ) ? absint( $posted['og_image_id'] ) : 0,
		'verification_meta_tags'   => jluxe_sanitize_verification_meta_tags( $posted['verification_meta_tags'] ?? array() ),
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
		'pwa_enabled'               => ! empty( $posted['pwa_enabled'] ),
		'pwa_install_prompt'        => ! empty( $posted['pwa_install_prompt'] ),
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
		'css' => isset( $posted['css'] ) ? wp_strip_all_tags( $posted['css'] ) : '',
		'js'  => isset( $posted['js'] ) ? wp_check_invalid_utf8( $posted['js'] ) : '',
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

	// The form posts lines; JSON exports contain an array. Both are already unslashed.
	$reply_input = $posted['quick_replies'] ?? array();
	$reply_input = is_string( $reply_input ) ? preg_split( '/\r\n|\r|\n/', $reply_input ) : $reply_input;
	$quick_replies = array();
	foreach ( is_array( $reply_input ) ? $reply_input : array() as $line ) {
		$line = sanitize_text_field( $line );
		if ( '' !== $line ) {
			$quick_replies[] = $line;
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
	$daily_limit      = isset( $posted['daily_limit'] ) ? absint( $posted['daily_limit'] ) : ( $defaults['daily_limit'] ?? 100 );

	// R94 — ساعتِ پاسخگویی/آیکون‌ها/دانشِ اختصاصی.
	$time_re      = '/^([01]?\d|2[0-3]):[0-5]\d$/';
	$hours_posted = is_array( $posted['phone_hours'] ?? null ) ? $posted['phone_hours'] : array();
	$hours_def    = $defaults['phone_hours'] ?? array( 'enabled' => false, 'start' => '', 'end' => '', 'closed_days' => array() );
	$start        = trim( (string) ( $hours_posted['start'] ?? $hours_def['start'] ) );
	$end          = trim( (string) ( $hours_posted['end'] ?? $hours_def['end'] ) );
	$closed_days = array();
	if ( array_key_exists( 'open_days_present', $hours_posted ) ) {
		$open_days = array();
		foreach ( (array) ( $hours_posted['open_days'] ?? array() ) as $day ) {
			if ( is_numeric( $day ) && (int) $day >= 0 && (int) $day <= 6 ) {
				$open_days[] = (int) $day;
			}
		}
		$closed_days = array_values( array_diff( range( 0, 6 ), array_unique( $open_days ) ) );
	} else {
		// تنظیماتِ قدیمیِ بسته‌بندی/فرم همچنان از closed_days استفاده می‌کنند.
		foreach ( (array) ( $hours_posted['closed_days'] ?? ( isset( $posted['phone_hours'] ) ? array() : $hours_def['closed_days'] ) ) as $day ) {
			if ( is_numeric( $day ) && (int) $day >= 0 && (int) $day <= 6 ) {
				$closed_days[] = (int) $day;
			}
		}
	}
	$icons        = array();
	$posted_icons = is_array( $posted['contact_icons'] ?? null ) ? $posted['contact_icons'] : array();
	foreach ( array_keys( $defaults['contact_icons'] ?? array() ) as $key ) {
		$icons[ $key ] = isset( $posted_icons[ $key ] ) ? esc_url_raw( trim( (string) $posted_icons[ $key ] ) ) : '';
	}
	$prompt_mode = isset( $posted['system_prompt_mode'] ) ? sanitize_key( $posted['system_prompt_mode'] ) : 'append';
	$breakpoint  = isset( $posted['mobile_breakpoint'] ) ? absint( $posted['mobile_breakpoint'] ) : ( $defaults['mobile_breakpoint'] ?? 820 );
	$phone_tz    = isset( $posted['phone_timezone'] ) ? sanitize_text_field( (string) $posted['phone_timezone'] ) : ( $defaults['phone_timezone'] ?? 'UTC' );
	if ( '' === $phone_tz || ( function_exists( 'timezone_identifiers_list' ) && ! in_array( $phone_tz, timezone_identifiers_list(), true ) ) ) {
		$phone_tz = $defaults['phone_timezone'] ?? 'UTC';
	}

	return array(
		'enabled'          => ! empty( $posted['enabled'] ),
		'name'             => isset( $posted['name'] ) ? sanitize_text_field( $posted['name'] ) : $defaults['name'],
		'welcome_message'  => isset( $posted['welcome_message'] ) ? sanitize_textarea_field( $posted['welcome_message'] ) : $defaults['welcome_message'],
		'avatar_id'        => isset( $posted['avatar_id'] ) ? absint( $posted['avatar_id'] ) : 0,
		'button_id'        => isset( $posted['button_id'] ) ? absint( $posted['button_id'] ) : 0,
		'show_desktop'     => ! empty( $posted['show_desktop'] ),
		'show_mobile'      => ! empty( $posted['show_mobile'] ), // legacy
		'hide_mobile_launcher' => isset( $posted['hide_mobile_launcher'] ) ? ! empty( $posted['hide_mobile_launcher'] ) : (bool) ( $defaults['hide_mobile_launcher'] ?? false ),
		'provider'         => in_array( $provider, array( '', 'openai', 'anthropic', 'gapgpt', 'custom' ), true ) ? $provider : '',
		'base_url'         => isset( $posted['base_url'] ) ? esc_url_raw( trim( (string) $posted['base_url'] ) ) : '',
		'model'            => isset( $posted['model'] ) ? sanitize_text_field( $posted['model'] ) : '',
		'temperature'      => max( 0, min( 2, $temperature ) ),
		'max_tokens'       => max( 50, min( 4000, $max_tokens ) ),
		'reasoning_effort' => in_array( $reasoning_effort, array( 'minimal', 'low', 'medium', 'high' ), true ) ? $reasoning_effort : $defaults['reasoning_effort'],
		'system_prompt'    => isset( $posted['system_prompt'] ) ? jluxe_substr( sanitize_textarea_field( (string) $posted['system_prompt'] ), 0, 8000 ) : '',
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
		'daily_limit'      => max( 0, min( 5000, $daily_limit ) ),
		'log_enabled'      => ! empty( $posted['log_enabled'] ),
		'custom_knowledge'   => isset( $posted['custom_knowledge'] ) ? jluxe_substr( sanitize_textarea_field( (string) $posted['custom_knowledge'] ), 0, 6000 ) : '',
		'system_prompt_mode' => in_array( $prompt_mode, array( 'append', 'replace' ), true ) ? $prompt_mode : 'append',
		'contact_phone'      => isset( $posted['contact_phone'] ) ? preg_replace( '/[^0-9۰-۹+\-\s()]/u', '', sanitize_text_field( (string) $posted['contact_phone'] ) ) : '',
		'phone_hours'        => array(
			'enabled'     => isset( $posted['phone_hours'] ) ? ! empty( $hours_posted['enabled'] ) : (bool) $hours_def['enabled'],
			'start'       => preg_match( $time_re, $start ) ? $start : $hours_def['start'],
			'end'         => preg_match( $time_re, $end ) ? $end : $hours_def['end'],
			'closed_days' => array_values( array_unique( $closed_days ) ),
		),
		'phone_timezone'     => $phone_tz,
		'phone_open_text'    => isset( $posted['phone_open_text'] ) ? sanitize_textarea_field( (string) $posted['phone_open_text'] ) : ( $defaults['phone_open_text'] ?? '' ),
		'phone_closed_text'  => isset( $posted['phone_closed_text'] ) ? sanitize_textarea_field( (string) $posted['phone_closed_text'] ) : ( $defaults['phone_closed_text'] ?? '' ),
		'contact_icons'      => $icons,
		'mobile_breakpoint'  => max( 360, min( 1280, $breakpoint ) ),
	);
}

function jluxe_sanitize_sms( array $posted, array $defaults ): array {
	$provider = isset( $posted['provider'] ) && is_scalar( $posted['provider'] ) ? sanitize_key( (string) $posted['provider'] ) : '';
	$stock_alert_mode = isset( $posted['stock_alert_mode'] ) && is_scalar( $posted['stock_alert_mode'] )
		? sanitize_key( (string) $posted['stock_alert_mode'] )
		: ( $defaults['stock_alert_mode'] ?? 'pattern' );
	$stock_alert_message = isset( $posted['stock_alert_message'] ) && is_scalar( $posted['stock_alert_message'] )
		? jluxe_substr( sanitize_textarea_field( (string) $posted['stock_alert_message'] ), 0, 1500 )
		: ( $defaults['stock_alert_message'] ?? '' );

	return array(
		'enabled'  => ! empty( $posted['enabled'] ),
		'otp_only' => ! empty( $posted['otp_only'] ),
		'provider' => in_array( $provider, array( '', 'kavenegar', 'melipayamak' ), true ) ? $provider : '',
		'username' => isset( $posted['username'] ) ? sanitize_text_field( $posted['username'] ) : $defaults['username'],
		'sender'   => isset( $posted['sender'] ) ? sanitize_text_field( $posted['sender'] ) : $defaults['sender'],
		'template'            => isset( $posted['template'] ) ? sanitize_text_field( $posted['template'] ) : $defaults['template'],
		'body_id'             => isset( $posted['body_id'] ) && '' !== (string) $posted['body_id'] ? (string) absint( $posted['body_id'] ) : ( $defaults['body_id'] ?? '' ),
		'stock_alert_enabled' => ! empty( $posted['stock_alert_enabled'] ),
		'stock_alert_mode' => in_array( $stock_alert_mode, array( 'pattern', 'free_text' ), true ) ? $stock_alert_mode : 'pattern',
		'stock_alert_template' => isset( $posted['stock_alert_template'] ) ? sanitize_text_field( $posted['stock_alert_template'] ) : ( $defaults['stock_alert_template'] ?? '' ),
		'stock_alert_body_id' => isset( $posted['stock_alert_body_id'] ) && '' !== (string) $posted['stock_alert_body_id'] ? (string) absint( $posted['stock_alert_body_id'] ) : ( $defaults['stock_alert_body_id'] ?? '' ),
		'stock_alert_message' => $stock_alert_message,
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
			// R89 — گزینه‌های تازه؛ چک‌باکس‌ها صریحاً false ذخیره می‌شوند (نه «نبودِ کلید» = پیش‌فرض).
			$hero_opt = jluxe_hero_options(
				array_merge(
					$posted,
					array(
						'autoplay'    => ! empty( $posted['autoplay'] ),
						'loop'        => ! empty( $posted['loop'] ),
						'show_arrows' => ! empty( $posted['show_arrows'] ),
						'border'      => ! empty( $posted['border'] ),
					)
				)
			);
			foreach ( array( 'effect', 'speed_ms', 'autoplay', 'loop', 'desktop_height', 'mobile_height', 'radius', 'top_spacing', 'show_arrows', 'indicators', 'border' ) as $hero_key ) {
				$section[ $hero_key ] = $hero_opt[ $hero_key ];
			}
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
					'image_id'        => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'mobile_image_id' => isset( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : 0,
					'category'        => isset( $item['category'] ) ? sanitize_text_field( $item['category'] ) : '',
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
			$section['hide_out_of_stock'] = filter_var( $posted['hide_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN );
			break;

		case 'special_products':
			$special_sort  = isset( $posted['sort'] ) ? sanitize_key( $posted['sort'] ) : 'discount';
			$special_sorts = array( 'discount', 'latest', 'popularity', 'sales', 'price_low', 'price_high', 'rating', 'rand' );

			$section['title']             = isset( $posted['title'] ) ? sanitize_text_field( $posted['title'] ) : 'فروش ویژه';
			$section['count']             = max( 4, min( 30, absint( $posted['count'] ?? 10 ) ) );
			$section['sort']              = in_array( $special_sort, $special_sorts, true ) ? $special_sort : 'discount';
			$section['view_all_link']     = isset( $posted['view_all_link'] ) ? esc_url_raw( $posted['view_all_link'] ) : '';
			$section['hide_out_of_stock'] = filter_var( $posted['hide_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN );
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
					'image_id'        => isset( $bc_slot['image_id'] ) ? absint( $bc_slot['image_id'] ) : 0,
					'mobile_image_id' => isset( $bc_slot['mobile_image_id'] ) ? absint( $bc_slot['mobile_image_id'] ) : 0,
					'link'            => isset( $bc_slot['link'] ) ? esc_url_raw( $bc_slot['link'] ) : '',
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
			// R91 — دکمهٔ «نمایش همه» قابلِ‌ویرایش.
			$view_all_mode = sanitize_key( (string) ( $posted['view_all_mode'] ?? 'categories_page' ) );
			$section['view_all_mode'] = in_array( $view_all_mode, array( 'categories_page', 'shop', 'custom', 'hidden' ), true ) ? $view_all_mode : 'categories_page';
			$section['view_all_text'] = isset( $posted['view_all_text'] ) ? sanitize_text_field( $posted['view_all_text'] ) : '';
			$section['view_all_link'] = isset( $posted['view_all_link'] ) && '' !== trim( (string) $posted['view_all_link'] ) ? esc_url_raw( trim( (string) $posted['view_all_link'] ) ) : '';
			if ( 'category_grid' === $type ) {
				$section['layout'] = 'row';
				$section['icon_svg'] = isset( $posted['icon_svg'] ) ? jluxe_sanitize_svg_markup( (string) $posted['icon_svg'] ) : '';
			}
			// layout: grid (شبکه‌ی معمولی) | row (نوار افقی اسکرول‌شونده با آیکون دایره‌ای، مثل «دسته‌ها» مرجع).
			if ( 'category_grid' !== $type ) {
				$section['layout'] = in_array( $layout, array( 'grid', 'row' ), true ) ? $layout : 'grid';
			}
			$shape_default = 'category_grid' === $type ? 'circle' : 'none';
			$shape = isset( $posted['image_shape'] ) ? sanitize_key( $posted['image_shape'] ) : $shape_default;
			$section['image_shape'] = in_array( $shape, array( 'circle', 'square', 'none' ), true ) ? $shape : $shape_default;
			if ( 'category_grid' === $type ) {
				$alignment = isset( $posted['alignment'] ) ? sanitize_key( $posted['alignment'] ) : 'center';
				$section['alignment'] = in_array( $alignment, array( 'start', 'center', 'end' ), true ) ? $alignment : 'center';
				$cards_alignment = sanitize_key( $posted['cards_alignment'] ?? 'center' );
				$section['cards_alignment'] = in_array( $cards_alignment, array( 'start', 'center', 'end' ), true ) ? $cards_alignment : 'center';
			}
			$mobile_layout_default = 'category_grid' === $type ? 'row' : 'grid';
			$mobile_layout = sanitize_key( $posted['mobile_layout'] ?? $mobile_layout_default );
			$section['mobile_layout'] = in_array( $mobile_layout, array( 'row', 'grid' ), true ) ? $mobile_layout : $mobile_layout_default;
			$section['mobile_columns'] = max( 2, min( 6, absint( $posted['mobile_columns'] ?? 3 ) ) );
			if ( 'category_grid' === $type ) {
				$section['section_radius'] = max( 12, min( 56, absint( $posted['section_radius'] ?? 40 ) ) );
				$section['card_radius'] = max( 0, min( 40, absint( $posted['card_radius'] ?? 20 ) ) );
				$section['image_size'] = max( 48, min( 140, absint( $posted['image_size'] ?? 80 ) ) );
				$image_bg_mode = sanitize_key( $posted['image_bg_mode'] ?? 'color' );
				$section['image_bg_mode'] = in_array( $image_bg_mode, array( 'color', 'transparent' ), true ) ? $image_bg_mode : 'color';
				$section['image_bg_color'] = sanitize_hex_color( $posted['image_bg_color'] ?? '' ) ?: '#FFFFFF';
				$card_shadow = sanitize_key( $posted['card_shadow'] ?? 'soft' );
				$section['card_shadow'] = in_array( $card_shadow, array( 'none', 'soft', 'medium', 'strong' ), true ) ? $card_shadow : 'soft';
				$section['hover_lift'] = array_key_exists( 'hover_lift', $posted ) && '1' === (string) $posted['hover_lift'];
				$section['zoom_enabled'] = array_key_exists( 'zoom_enabled', $posted ) && '1' === (string) $posted['zoom_enabled'];
			} else {
				$frame_mode = sanitize_key( $posted['image_frame_mode'] ?? 'none' );
				$section['image_frame_mode'] = in_array( $frame_mode, array( 'none', 'transparent', 'color' ), true ) ? $frame_mode : 'none';
				$section['image_frame_color'] = sanitize_hex_color( $posted['image_frame_color'] ?? '' ) ?: '#D7D7D7';
				$section['show_category_names'] = ! array_key_exists( 'show_category_names', $posted ) || '1' === (string) $posted['show_category_names'];
				$section['card_text_color'] = sanitize_hex_color( $posted['card_text_color'] ?? '' ) ?: '#303936';
			}
			// دسته‌بندی‌ها دیگه خودکار از همه‌ی ترم‌های ووکامرس pull نمی‌شن
			// (باگ واقعی: با hide_empty=true، دسته‌های تازه‌ساخته‌شده‌ی بدون
			// محصول هیچ‌وقت نشون داده نمی‌شدن — دقیقاً چیزی که گزارش شد).
			// حالا کاملاً دستیه: ادمین از یک لیست واقعی از دسته‌های ووکامرس
			// انتخاب و مرتب می‌کنه؛ term_id نامعتبر/حذف‌شده نادیده گرفته می‌شه.
			$section['items'] = array();
			$posted_items = isset( $posted['items'] ) && is_array( $posted['items'] ) ? array_slice( $posted['items'], 0, 16 ) : array();
			foreach ( $posted_items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( 'category_showcase' === $type && 'image' === sanitize_key( $item['mode'] ?? 'category' ) ) {
					$image_id = isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0;
					if ( ! $image_id ) {
						continue;
					}
					$section['items'][] = array(
						'mode'         => 'image',
						'term_id'      => 0,
						'image_id'     => $image_id,
						'display_name' => isset( $item['display_name'] ) ? sanitize_text_field( $item['display_name'] ) : '',
						'link'         => isset( $item['link'] ) ? esc_url_raw( trim( (string) $item['link'] ) ) : '',
					);
					continue;
				}
				$term_id = isset( $item['term_id'] ) ? absint( $item['term_id'] ) : 0;
				if ( ! $term_id || ! term_exists( $term_id, 'product_cat' ) ) {
					continue;
				}
				$category_item = array(
					'term_id'      => $term_id,
					'image_id'     => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'display_name' => isset( $item['display_name'] ) ? sanitize_text_field( $item['display_name'] ) : '',
				);
				if ( 'category_showcase' === $type ) {
					$category_item['mode'] = 'category';
				}
				$section['items'][] = $category_item;
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
				$image_fit = sanitize_key( $item['image_fit'] ?? 'cover' );
				$section['items'][] = array(
					'image_id'         => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
					'mobile_image_id'  => isset( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : 0,
					'image_fit'        => in_array( $image_fit, array( 'cover', 'contain', 'original' ), true ) ? $image_fit : 'cover',
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
			$section['content'] = isset( $posted['content'] ) ? wp_kses_post( $posted['content'] ) : '';
			break;

		case 'html':
			$section['content'] = isset( $posted['content'] ) ? $posted['content'] : ''; // manage_options only، مثل Custom CSS/JS.
			break;

		case 'spacer':
			$section['height'] = max( 8, min( 200, absint( $posted['height'] ?? 40 ) ) );
			break;
	}

	return $section;
}

function jluxe_sanitize_homepage( array $posted, array $defaults ): array {
	if ( ! isset( $posted['sections'] ) || ! is_array( $posted['sections'] ) ) {
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
