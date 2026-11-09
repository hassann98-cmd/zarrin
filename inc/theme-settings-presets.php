<?php
/**
 * پریست‌های آماده‌ی طراحی — طبقِ درخواستِ کاربر («Settings Panel 2.0 →
 * پریست‌های طراحی آماده»). پریست‌ها رنگ‌ها و تایپوگرافی را ست می‌کنند؛
 * Bubblegum Pink علاوه‌بر آن، shadeهای شناخته‌شدهٔ پالت را در تنظیماتِ
 * رنگیِ بخش‌های دیگر هم هماهنگ می‌کند و رنگ‌های سفارشی/معنایی را نگه می‌دارد.
 *
 * مقادیر همیشه از این آرایه‌ی ثابتِ سمتِ سرور خونده می‌شن، نه از چیزی که
 * کلاینت می‌فرسته — یعنی درخواستِ POST فقط یک شناسه (preset_id) می‌فرسته،
 * نه رنگ‌های خام؛ دقیقاً همون سطحِ اعتمادی که بقیه‌ی فرم‌های تنظیمات دارن.
 *
 * همه‌ی رنگ‌های primary/secondary/success/accent با محاسبه‌ی واقعیِ کنتراستِ
 * WCAG در برابرِ متنِ سفید بررسی شدن (حداقل ۴.۵:۱، یعنی حتی سخت‌گیرانه‌تر از
 * پیش‌فرضِ فعلیِ خودِ تم که secondary/success‌اش این آستانه رو رد نمی‌کنن) —
 * روی دکمه‌های اصلی/بج‌ها با متنِ سفید همیشه خوانا می‌مونن.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_get_design_presets(): array {
	return array(
		'jewelry_luxe' => array(
			'label' => 'جواهرفروشی لوکس',
			'description' => 'شرابی عمیق، شامپاینی و ایوری؛ مناسب فروشگاه طلا و جواهر با ظاهر Premium.',
			'colors' => array(
				'primary' => '#7A2438', 'secondary' => '#24313A', 'background' => '#FBF8F3', 'success' => '#26734D', 'accent' => '#C8A45D',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.62),
		),
		'home_warm' => array(
			'label' => 'لوازم خانه گرم و مدرن',
			'description' => 'تراکوتای کنترل‌شده، سبز زیتونی و کرم روشن؛ مناسب جهیزیه و لوازم آشپزخانه.',
			'colors' => array(
				'primary' => '#B6533C', 'secondary' => '#355C55', 'background' => '#FAF7F1', 'success' => '#2D7A55', 'accent' => '#C69A4B',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.65),
		),
		'digikala_clean' => array(
			'label' => 'فروشگاهی شفاف و مینیمال',
			'description' => 'سفید، آبی عمیق و قرمز پرقدرت؛ مناسب فروشگاه عمومی و حس سریع و مدرن شبیه فروشگاه‌های بزرگ.',
			'colors' => array(
				'primary' => '#D92D3F', 'secondary' => '#155E9B', 'background' => '#FFFFFF', 'success' => '#16834A', 'accent' => '#F59E0B',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'minimal_black_gold' => array(
			'label' => 'مینیمال مشکی و طلایی',
			'description' => 'زمینه روشن، مشکی خنثی و طلایی محدود؛ تمیز و بسیار مناسب برندهای پریمیوم.',
			'colors' => array(
				'primary' => '#222222', 'secondary' => '#4B5563', 'background' => '#FAFAF8', 'success' => '#287A52', 'accent' => '#B58A3A',
			),
			'typography' => array('base_size' => 15, 'heading_weight' => 700, 'line_height' => 1.58),
		),
		'joyful_coral_teal' => array(
			'label' => 'شاد و پرانرژی',
			'description' => 'مرجانی و فیروزه‌ای با زمینه بسیار روشن؛ مناسب فروشگاه جوان، شاد و پرتحرک.',
			'colors' => array(
				'primary' => '#E23B45', 'secondary' => '#087F7A', 'background' => '#F7FCFB', 'success' => '#168A50', 'accent' => '#F2A93B',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.65),
		),
		/*
		 * R57 — درخواستِ صریحِ کاربر: «در ظاهر و برند این رنگ هم اضافه کن» —
		 * هویتِ رنگیِ مرجعِ jluxe.ir که کاربر از DOM آن سایت فرستاد:
		 * style attribute مرجع: --color-primary: #ed1a45؛ --color-secondary: #101113
		 * و زمینهٔ پرتکرارِ همان DOM: bg-[#f7f8fa]. سبز/کهربایی از پالتِ موجودِ
		 * خود پوسته (#00A049 سودِ خرید، #F5A623 ستارهٔ امتیاز) تا هماهنگ بماند.
		 * مسیرِ اعمال مثل بقیهٔ پریست‌ها از sanitizerهای همیشگی می‌گذرد.
		 */
		'jluxe_brand_red' => array(
			'label' => 'قرمز برند مرجع (جهیزیه لوکس)',
			'description' => 'قرمز پرقدرتِ #ED1A45 با ثانویهٔ مشکی‌فامِ #101113 و زمینهٔ روشنِ #F7F8FA — دقیقاً هویتِ رنگیِ مرجعِ jluxe.ir.',
			'colors' => array(
				'primary' => '#ED1A45', 'secondary' => '#101113', 'background' => '#F7F8FA', 'success' => '#00A049', 'accent' => '#F5A623',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'emerald_modern' => array(
			'label' => 'زمردی مدرن',
			'description' => 'سبز زمردی، سرمه‌ای و طلایی ملایم؛ متفاوت، باوقار و مناسب برندهای خانه و جواهر.',
			'colors' => array(
				'primary' => '#087A68', 'secondary' => '#243746', 'background' => '#F6FAF8', 'success' => '#19734C', 'accent' => '#B9974F',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.62),
		),
		'electric_indigo' => array(
			'label' => 'نیلی الکتریکی (افزوده‌شده)',
			'description' => 'پالت تازهٔ Electric Indigo به‌عنوان گزینه‌ای مستقل؛ هفت پریست قبلی با رنگ‌های اصلی خود باقی می‌مانند.',
			'colors' => array(
				'primary' => '#5100FF', 'secondary' => '#300099', 'background' => '#EEE5FF', 'success' => '#16865A', 'accent' => '#7433FF',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'legacy_default' => array(
			'label' => 'پالت پیش‌فرض پیشین زرین',
			'description' => 'رنگ‌های پایهٔ پوسته پیش از Electric Indigo؛ گزینه‌ای جداگانه و قابل‌اعمال، بدون تغییر خودکارِ تنظیمات ذخیره‌شده.',
			'colors' => array(
				'primary' => '#B85C38', 'secondary' => '#355C5A', 'background' => '#F7F3EE', 'success' => '#16865A', 'accent' => '#C89B3C',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'royal_orchid' => array(
			'label' => 'رویال ارکید (Royal Orchid)',
			'description' => 'طیف دقیق Royal Orchid؛ رنگ اصلی از shade 600 انتخاب شده تا متن سفیدِ دکمه‌ها کنتراست WCAG AA داشته باشد. هر ۵ رنگ پس از اعمال قابل‌ویرایش است.',
			'colors' => array(
				'primary' => '#A313B9', 'secondary' => '#510A5C', 'background' => '#FAE8FD', 'success' => '#16865A', 'accent' => '#7A0F8A',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'dodger_blue' => array(
			'label' => 'آبی داجر (Dodger Blue)',
			'description' => 'طیف دقیق Dodger Blue؛ پس‌زمینهٔ shade 50 با متن اصلیِ 13.08:1 و متن کمکیِ 6.75:1، دکمهٔ shade 600 با متن سفیدِ 4.81:1 و hover/active تیره‌تر. مرز و focus ring هم shade 600 هستند (4.28:1 روی زمینه و 4.81:1 روی کارت سفید). رنگ سبزِ موفقیت معنای مستقل خود را حفظ می‌کند.',
			'colors' => array(
				'primary' => '#0074CC', 'secondary' => '#003A66', 'background' => '#E5F4FF', 'success' => '#16865A', 'accent' => '#005799',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'bubblegum_pink' => array(
			'label' => 'صورتی آدامسی (Bubblegum Pink)',
			'description' => 'طیف دقیق Bubblegum Pink در سراسر سایت؛ دکمهٔ اصلی از shade 600 انتخاب شده تا متن سفید کنتراست 5.81:1 داشته باشد. shadeهای شناخته‌شدهٔ منو، موبایل، تسویه‌حساب، فوتر و بخش‌های دیگر هم هماهنگ می‌شوند؛ رنگ سبزِ موفقیت و رنگ‌های سفارشی حفظ می‌شوند.',
			'colors' => array(
				'primary' => '#CC0033', 'secondary' => '#660019', 'background' => '#FFE5EC', 'success' => '#16865A', 'accent' => '#990026',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
			'palette' => 'bubblegum_pink',
		),
	);
}

/** Remap exact bundled palette shades while keeping announcement-bar colors independent. */
function jluxe_remap_design_palette_values( array $settings, array $target_palette ): array {
	$source_palettes = array(
		jluxe_electric_indigo_palette(),
		jluxe_royal_orchid_palette(),
		jluxe_dodger_blue_palette(),
		$target_palette,
	);
	$color_map = array();
	foreach ( $source_palettes as $source_palette ) {
		foreach ( $source_palette as $shade => $source_hex ) {
			if ( isset( $target_palette[ $shade ] ) ) {
				$color_map[ strtoupper( $source_hex ) ] = $target_palette[ $shade ];
			}
		}
	}

	$walk = static function ( $value, bool $is_root = false ) use ( &$walk, $color_map ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$value[ $key ] = $is_root && 'announcement_bar' === $key ? $child : $walk( $child );
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			$normalized = strtoupper( trim( $value ) );
			return $color_map[ $normalized ] ?? $value;
		}
		return $value;
	};

	return $walk( $settings, true );
}

/**
 * اعمالِ یک پریست — دقیقاً از همون jluxe_update_settings_section و همون
 * sanitizerهای رنگ/تایپوگرافیِ موجود عبور می‌کنه (نه نوشتنِ مستقیم)، پس
 * حتی اگه مقادیرِ ثابتِ بالا هم روزی خراب/دستکاری بشن، خروجیِ نهایی هنوز
 * به همون قوانینِ اعتبارسنجیِ همیشگی (hex معتبر، بازه‌ی مجازِ فونت) پایبنده.
 */
function jluxe_handle_apply_preset(): ?string {
	if ( empty( $_POST['jluxe_apply_preset'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	if ( ! isset( $_POST['jluxe_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
		return 'error';
	}

	$preset_id = isset( $_POST['jluxe_preset_id'] ) ? sanitize_key( wp_unslash( $_POST['jluxe_preset_id'] ) ) : '';
	$presets   = jluxe_get_design_presets();
	if ( ! isset( $presets[ $preset_id ] ) ) {
		return 'error';
	}

	$preset   = $presets[ $preset_id ];
	$defaults = jluxe_theme_settings_defaults();

	$clean_colors     = jluxe_sanitize_colors( $preset['colors'], $defaults['colors'] );
	$clean_typography = jluxe_sanitize_typography( $preset['typography'], $defaults['typography'] );

	jluxe_update_settings_section( 'colors', $clean_colors );
	jluxe_update_settings_section( 'typography', $clean_typography );

	if ( 'bubblegum_pink' === ( $preset['palette'] ?? '' ) && function_exists( 'jluxe_bubblegum_pink_palette' ) ) {
		$current_settings = jluxe_get_theme_settings( true );
		$palette_settings = jluxe_remap_design_palette_values( $current_settings, jluxe_bubblegum_pink_palette() );
		// The main form values were already sanitized above; retain their casing and exact submitted defaults.
		$palette_settings['colors'] = $current_settings['colors'];
		foreach ( $palette_settings as $section_key => $section_value ) {
			if (
				'version' !== $section_key &&
				is_array( $section_value ) &&
				isset( $current_settings[ $section_key ] ) &&
				$section_value !== $current_settings[ $section_key ]
			) {
				jluxe_update_settings_section( (string) $section_key, $section_value );
			}
		}
	}

	return 'preset_applied';
}
