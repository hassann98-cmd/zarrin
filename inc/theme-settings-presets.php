<?php
/**
 * پریست‌های آماده‌ی طراحی — طبقِ درخواستِ کاربر («Settings Panel 2.0 →
 * پریست‌های طراحی آماده»). هر پریست فقط دو بخشِ از قبل موجود رو ست می‌کنه
 * (رنگ‌ها + تایپوگرافی) — هیچ ساختارِ داده‌ی جدیدی اضافه نمی‌شه، فقط یک
 * میان‌بر برای پرکردنِ همون دو فرمی که از قبل هم دستی قابل‌ویرایش بودن.
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
	$palette = jluxe_electric_indigo_palette();
	return array(
		'electric_indigo' => array(
			'label' => 'نیلی الکتریکی (پیش‌فرض زرین)',
			'description' => 'طیفِ یکپارچهٔ Electric Indigo از بنفشِ بسیار روشن تا نیلیِ عمیق، هماهنگ با رنگ‌های پیش‌فرض پوسته.',
			'colors' => array(
				'primary' => $palette['500'], 'secondary' => $palette['700'], 'background' => $palette['50'], 'success' => $palette['600'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'jewelry_luxe' => array(
			'label' => 'جواهرفروشی لوکس — نیلی عمیق',
			'description' => 'ترکیب نیلیِ عمیق و سطوح روشن از طیف Electric Indigo؛ مناسب فروشگاه طلا و جواهر با ظاهر Premium.',
			'colors' => array(
				'primary' => $palette['500'], 'secondary' => $palette['900'], 'background' => $palette['50'], 'success' => $palette['600'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.62),
		),
		'home_warm' => array(
			'label' => 'لوازم خانه — نیلی روشن',
			'description' => 'سطوح روشن و سایه‌های هماهنگ Electric Indigo؛ مناسب جهیزیه و لوازم آشپزخانه.',
			'colors' => array(
				'primary' => $palette['400'], 'secondary' => $palette['800'], 'background' => $palette['50'], 'success' => $palette['600'], 'accent' => $palette['500'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.65),
		),
		'digikala_clean' => array(
			'label' => 'فروشگاهی شفاف و مینیمال — نیلی',
			'description' => 'سفید و طیف Electric Indigo با تأکید روشن و مدرن؛ مناسب فروشگاه عمومی.',
			'colors' => array(
				'primary' => $palette['500'], 'secondary' => $palette['700'], 'background' => '#FFFFFF', 'success' => $palette['600'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'minimal_black_gold' => array(
			'label' => 'مینیمال نیلی و روشن',
			'description' => 'زمینهٔ روشن با نیلیِ عمیق و تأکیدهای Electric Indigo؛ تمیز و مناسب برندهای پریمیوم.',
			'colors' => array(
				'primary' => $palette['800'], 'secondary' => $palette['700'], 'background' => '#FFFFFF', 'success' => $palette['600'], 'accent' => $palette['500'],
			),
			'typography' => array('base_size' => 15, 'heading_weight' => 700, 'line_height' => 1.58),
		),
		'joyful_coral_teal' => array(
			'label' => 'شاد و پرانرژی — نیلی روشن',
			'description' => 'ترکیب روشن از رنگ‌های Electric Indigo؛ مناسب فروشگاه جوان، شاد و پرتحرک.',
			'colors' => array(
				'primary' => $palette['500'], 'secondary' => $palette['400'], 'background' => $palette['50'], 'success' => $palette['700'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.65),
		),
		/*
		 * شناسهٔ قدیمیِ preset برای سازگاریِ ذخیره‌شده حفظ شده؛ رنگ‌هایش
		 * اکنون از همان Electric Indigo موردِ استفادهٔ بقیهٔ پوسته هستند.
		 */
		'jluxe_brand_red' => array(
			'label' => 'برند زرین — Electric Indigo',
			'description' => 'طیف Electric Indigo با زمینهٔ روشن و رنگ‌های تأکیدیِ هماهنگ با پوسته.',
			'colors' => array(
				'primary' => $palette['500'], 'secondary' => $palette['900'], 'background' => $palette['50'], 'success' => $palette['600'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.6),
		),
		'emerald_modern' => array(
			'label' => 'نیلی مدرن',
			'description' => 'نیلیِ عمیق و تأکیدهای Electric Indigo؛ متفاوت و باوقار برای برندهای خانه و جواهر.',
			'colors' => array(
				'primary' => $palette['600'], 'secondary' => $palette['900'], 'background' => $palette['50'], 'success' => $palette['700'], 'accent' => $palette['400'],
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.62),
		),
	);
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

	return 'preset_applied';
}
