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
 * رنگِ روی هر سطحِ رنگی با jluxe_auto_foreground به‌صورتِ پویا سفید یا تیره
 * انتخاب می‌کند؛ متنِ سفید برای هر رنگی فرض نمی‌شود. primaryهایی که به‌شکلِ متن
 * روی زمینهٔ روشن هم مصرف می‌شوند جداگانه با نسبتِ WCAG 2.1 AA بررسی شده‌اند.
 * دو قرمزِ مرجانی/برندِ قدیمی برای این کاربرد کمی تیره‌تر شده‌اند؛ hue و هویت
 * حفظ شده، ولی متن/لینکِ اصلی حالا حداقل 4.5:1 کنتراست دارد.
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
			'description' => 'مرجانیِ عمیق‌تر و فیروزه‌ای با زمینهٔ بسیار روشن؛ قرمزِ اصلی برای خوانایی متن به shade دسترس‌پذیر #D4323D رسیده است.',
			'colors' => array(
				'primary' => '#D4323D', 'secondary' => '#087F7A', 'background' => '#F7FCFB', 'success' => '#147A47', 'accent' => '#F2A93B',
			),
			'typography' => array('base_size' => 16, 'heading_weight' => 700, 'line_height' => 1.65),
		),
		/*
		 * R57 — درخواستِ صریحِ کاربر: «در ظاهر و برند این رنگ هم اضافه کن» —
		 * هویتِ رنگیِ مرجعِ jluxe.ir که کاربر از DOM آن سایت فرستاد:
		 * مرجعِ jluxe.ir: --color-primary: #ed1a45؛ --color-secondary: #101113؛
		 * زمینهٔ پرتکرار: #f7f8fa. خودِ #ED1A45 روی این زمینه به‌عنوان متن فقط
		 * 4.09:1 کنتراست داشت؛ برای خواناییِ لینک/متن primary، رنگِ اعمالی به
		 * shadeِ hue-preservingِ #D9163E (بیش از 4.7:1) تنظیم شده است. کدِ مرجع
		 * در توضیح حفظ می‌شود؛ سبز/کهربایی از پالت موجود می‌آیند.
		 */
		'jluxe_brand_red' => array(
			'label' => 'قرمز برند مرجع (جهیزیه لوکس)',
			'description' => 'قرمزِ برندِ مرجع #ED1A45 با shade دسترس‌پذیر #D9163E، ثانویهٔ #101113 و زمینهٔ #F7F8FA؛ هویت حفظ شده و کنتراست متن از AA می‌گذرد.',
			'colors' => array(
				'primary' => '#D9163E', 'secondary' => '#101113', 'background' => '#F7F8FA', 'success' => '#00A049', 'accent' => '#F5A623',
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
