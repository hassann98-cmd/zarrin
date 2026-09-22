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
		'emerald_modern' => array(
			'label' => 'زمردی مدرن',
			'description' => 'سبز زمردی، سرمه‌ای و طلایی ملایم؛ متفاوت، باوقار و مناسب برندهای خانه و جواهر.',
			'colors' => array(
				'primary' => '#087A68', 'secondary' => '#243746', 'background' => '#F6FAF8', 'success' => '#19734C', 'accent' => '#B9974F',
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
