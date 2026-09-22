<?php
/**
 * رابط ادمین پنل تنظیمات JLuxe — منوی wp-admin (۲۰ زیرمنو زیر یک منوی
 * بالایی)، ثبت‌نام صفحات، و dispatcher ذخیره‌سازی عمومی (هر فرم فقط بخش
 * خودش رو می‌فرسته، نه کل تنظیمات — پس ذخیره‌ی «هدر» چیزی از «فوتر» رو
 * پاک نمی‌کنه). رندر واقعی هر صفحه در inc/theme-settings-render.php.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_SETTINGS_MENU_SLUG = 'jluxe-theme-settings';

/**
 * نگاشت slug صفحه → [کلید تنظیمات، تابع sanitize]. صفحاتی که این‌جا
 * نیستن (dashboard, homepage, ai-assistant, import-export, advanced)
 * منطق ذخیره‌ی مخصوص خودشون رو دارن (چون ساختارشون ساده‌ی «یک آرایه‌ی
 * تخت» نیست).
 */
function jluxe_settings_sections_map(): array {
	return array(
		'jluxe-colors'        => array( 'colors', 'jluxe_sanitize_colors' ),
		'jluxe-identity'      => array( 'identity', 'jluxe_sanitize_identity' ),
		'jluxe-urls'          => array( 'urls', 'jluxe_sanitize_urls' ),
		'jluxe-typography'    => array( 'typography', 'jluxe_sanitize_typography' ),
		'jluxe-header'        => array( 'header', 'jluxe_sanitize_header' ),
		'jluxe-header-nav'    => array( 'header_nav', 'jluxe_sanitize_header_nav' ),
		'jluxe-footer'        => array( 'footer', 'jluxe_sanitize_footer' ),
		'jluxe-product-card'  => array( 'product_card', 'jluxe_sanitize_product_card' ),
		'jluxe-product-page'  => array( 'product_page', 'jluxe_sanitize_product_page' ),
		'jluxe-shop'          => array( 'shop', 'jluxe_sanitize_shop' ),
		'jluxe-mobile'        => array( 'mobile', 'jluxe_sanitize_mobile' ),
		'jluxe-contact'       => array( 'contact', 'jluxe_sanitize_contact' ),
		'jluxe-social'        => array( 'social', 'jluxe_sanitize_social' ),
		'jluxe-contact-pages' => array( 'info_pages', 'jluxe_sanitize_info_pages' ),
		'jluxe-seo'           => array( 'seo', 'jluxe_sanitize_seo' ),
		'jluxe-faq'           => array( 'faq', 'jluxe_sanitize_faq' ),
		'jluxe-review-criteria' => array( 'review_criteria', 'jluxe_sanitize_review_criteria' ),
		'jluxe-performance'   => array( 'performance', 'jluxe_sanitize_performance' ),
		'jluxe-custom-code'   => array( 'custom_code', 'jluxe_sanitize_custom_code' ),
		'jluxe-homepage'      => array( 'homepage', 'jluxe_sanitize_homepage' ),
		'jluxe-guide-pages'   => array( 'guide_pages', 'jluxe_sanitize_guide_pages' ),
		'jluxe-payment-account' => array( 'payment_account', 'jluxe_sanitize_payment_account' ),
	);
}

function jluxe_register_settings_menu(): void {
	add_menu_page(
		'زرین',
		'زرین',
		'manage_options',
		JLUXE_SETTINGS_MENU_SLUG,
		'jluxe_render_dashboard_page',
		'dashicons-store',
		58
	);

	$submenus = array(
		JLUXE_SETTINGS_MENU_SLUG => array( 'داشبورد', 'jluxe_render_dashboard_page' ),
		'jluxe-homepage'          => array( 'صفحه اصلی', 'jluxe_render_homepage_page' ),
		'jluxe-identity'          => array( 'هویت سایت', 'jluxe_render_identity_page' ),
		'jluxe-urls'              => array( 'آدرس‌های ورود و کاربر', 'jluxe_render_urls_page' ),
		'jluxe-header'            => array( 'هدر', 'jluxe_render_header_page' ),
		'jluxe-header-nav'        => array( 'منوی هدر', 'jluxe_render_header_nav_page' ),
		'jluxe-footer'            => array( 'فوتر', 'jluxe_render_footer_page' ),
		'jluxe-colors'            => array( 'رنگ و ظاهر', 'jluxe_render_colors_page' ),
		'jluxe-typography'        => array( 'تایپوگرافی', 'jluxe_render_typography_page' ),
		'jluxe-colors-typography' => array( 'رنگ و تایپوگرافی', 'jluxe_render_colors_typography_page' ),
		'jluxe-product-card'      => array( 'کارت محصول', 'jluxe_render_product_card_page' ),
		'jluxe-product-page'      => array( 'صفحه محصول', 'jluxe_render_product_page_page' ),
		'jluxe-product'           => array( 'محصول', 'jluxe_render_product_page' ),
		'jluxe-shop'              => array( 'فروشگاه و دسته‌بندی', 'jluxe_render_shop_page' ),
		'jluxe-ai-assistant'      => array( 'دستیار هوش مصنوعی', 'jluxe_render_ai_assistant_page' ),
		'jluxe-ai-tickets'        => array( 'گزارش‌های دستیار', 'jluxe_render_ai_tickets_page' ),
		'jluxe-sms'               => array( 'ورود با پیامک (OTP)', 'jluxe_render_sms_page' ),
		'jluxe-mobile'            => array( 'موبایل', 'jluxe_render_mobile_page' ),
		'jluxe-contact'           => array( 'تماس و شبکه اجتماعی', 'jluxe_render_contact_page' ),
		'jluxe-mobile-contact'    => array( 'موبایل و تماس', 'jluxe_render_mobile_contact_page' ),
		'jluxe-contact-pages'     => array( 'تماس و صفحات', 'jluxe_render_contact_pages_page' ),
		'jluxe-guide-pages'       => array( 'صفحات راهنما', 'jluxe_render_guide_pages_page' ),
		'jluxe-faq'               => array( 'سوالات متداول', 'jluxe_render_faq_page' ),
		'jluxe-review-criteria'   => array( 'معیارهای امتیاز دیدگاه', 'jluxe_render_review_criteria_page' ),
		'jluxe-seo'               => array( 'سئو', 'jluxe_render_seo_page' ),
		'jluxe-performance'       => array( 'عملکرد', 'jluxe_render_performance_page' ),
		'jluxe-custom-code'       => array( 'CSS/JS سفارشی', 'jluxe_render_custom_code_page' ),
		'jluxe-import-export'     => array( 'درون‌ریزی / برون‌بری', 'jluxe_render_import_export_page' ),
		'jluxe-advanced'          => array( 'تنظیمات', 'jluxe_render_advanced_page' ),
	);

	foreach ( $submenus as $slug => [ $label, $callback ] ) {
		add_submenu_page(
			JLUXE_SETTINGS_MENU_SLUG,
			$label . ' — JLuxe',
			$label,
			'manage_options',
			$slug,
			$callback
		);
	}

	// social یک صفحه‌ی مستقل نداره — داخل «تماس و شبکه اجتماعی» ادغام شده،
	// ولی sanitize map بالاتر بهش نیاز داره پس این‌جا به‌عنوان submenu ثبت
	// نمی‌شه (فقط جزئی از صفحه‌ی contact رندر می‌شه).
}
add_action( 'admin_menu', 'jluxe_register_settings_menu' );

/**
 * لیستِ طویلِ زیرمنو زیرِ «JLuxe Theme» توی سایدبارِ پیشخوان — طبق درخواستِ
 * صریحِ کاربر مخفی می‌شه؛ فقط خودِ آیتمِ بالایی (داشبورد) دیده می‌شه.
 *
 * باگِ واقعیِ پیداشده: نسخه‌ی قبلیِ این تابع remove_submenu_page() صدا
 * می‌زد — که کارش فقط «حذفِ ردیف از منو» نیست، رکوردِ صفحه رو کلاً از
 * $submenu پاک می‌کنه؛ و چون خودِ admin.php برای تشخیصِ «این کاربر اجازه‌ی
 * بازکردنِ page=X رو داره یا نه» دقیقاً همین آرایه‌ی $submenu رو می‌گرده،
 * وقتی ردیف حذف بشه ووردپرس نمی‌تونه capability لازم رو پیدا کنه و با خطای
 * واقعیِ «اجازه‌ی دسترسی ندارید» صفحه رو کلاً می‌بنده — دقیقاً همون چیزی
 * که با تست زنده در کروم روی تبِ «هدر» بازتولید شد. remove_submenu_page
 * فقط برای صفحاتی درسته که واقعاً می‌خوایم غیرقابل‌دسترس بشن، نه صفحاتی که
 * فقط نمی‌خوایم توی سایدبار لیست بشن ولی هنوز باید از تبِ بالای صفحه قابل
 * بازشدن باشن. راه‌حلِ درست: خودِ ثبتِ صفحات (add_submenu_page) دست‌نخورده
 * می‌مونه (پس $submenu/capability سالمه)، فقط ردیف‌های اضافیِ سایدبار با
 * CSS مخفی می‌شن.
 */
function jluxe_hide_settings_submenus_css(): void {
	?>
	<style>
		#toplevel_page_jluxe-theme-settings .wp-submenu li:not(:first-child) {
			display: none;
		}
		/*
		 * طبقِ درخواستِ صریحِ بعدیِ کاربر («این ۵تا رو از تبِ تنظیمات خارج
		 * کن و بیارشون زیرِ منوی زرین») این ۵ صفحه دیگه فقط از تبِ افقیِ
		 * بالای صفحه (jluxe_render_settings_nav در theme-settings-render.php
		 * — همون‌جا هم حذف شدن) در دسترس نیستن؛ توی سایدبارِ پیشخوانِ
		 * وردپرس هم واقعاً دیده/کلیک‌پذیرن، نه فقط تبِ بالای صفحه. بقیه‌ی
		 * صفحات (هدر، فوتر، رنگ و...) طبقِ همون تصمیمِ قبلی فقط از تب در
		 * دسترسن، توی سایدبار مخفی می‌مونن.
		 */
		#toplevel_page_jluxe-theme-settings .wp-submenu li:has(> a[href*="page=jluxe-ai-assistant"]),
		#toplevel_page_jluxe-theme-settings .wp-submenu li:has(> a[href*="page=jluxe-sms"]),
		#toplevel_page_jluxe-theme-settings .wp-submenu li:has(> a[href*="page=jluxe-mobile-contact"]),
		#toplevel_page_jluxe-theme-settings .wp-submenu li:has(> a[href*="page=jluxe-faq"]),
		#toplevel_page_jluxe-theme-settings .wp-submenu li:has(> a[href*="page=jluxe-advanced"]) {
			display: list-item;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'jluxe_hide_settings_submenus_css' );

function jluxe_settings_enqueue_admin_assets( string $hook ): void {
	if ( false === strpos( $hook, 'jluxe-' ) && false === strpos( $hook, 'page_jluxe' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	// انتخاب‌گرِ محصولِ واقعیِ خودِ ووکامرس (select2 + جستجوی AJAX روی
	// woocommerce_json_search_products) — برای انتخاب دستیِ محصول در بخش
	// «محصولات پرفروش». اسکریپت/استایلش از قبل توسط خودِ ووکامرس register
	// می‌شه، این‌جا فقط روی صفحات تنظیمات تم enqueue می‌شه.
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
	// CSS اصلی فرانت‌اند را داخل wp-admin لود نمی‌کنیم؛ Tailwind reset آن روی * و body و فرم‌ها اعمال می‌شود و Dashicons و چیدمان پیشخوان را به‌هم می‌ریزد.
	// فونت IRANYekan به‌صورت محلی در theme-settings-admin.css تعریف شده است.
	$js_path  = JLUXE_THEME_DIR . '/assets/js/theme-settings-admin.js';
	$css_path = JLUXE_THEME_DIR . '/assets/css/theme-settings-admin.css';
	wp_enqueue_script(
		'jluxe-settings-admin',
		JLUXE_THEME_URI . '/assets/js/theme-settings-admin.js',
		array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '2.0.0',
		true
	);
	wp_enqueue_style(
		'jluxe-settings-admin',
		JLUXE_THEME_URI . '/assets/css/theme-settings-admin.css',
		array(),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '2.0.0'
	);
	// nonce تست‌اتصالِ دستیار هوش مصنوعی (inc/theme-settings-ai.php) — فقط
	// همین یک مقدار لازمه؛ ajaxurl خودش توسط وردپرس در همه‌ی صفحات ادمین
	// به‌صورت گلوبال موجوده، نیازی به localize کردنش نیست.
	wp_localize_script(
		'jluxe-settings-admin',
		'jluxeSettingsAdmin',
		array( 'aiTestNonce' => wp_create_nonce( 'jluxe_ai_test_connection' ) )
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_settings_enqueue_admin_assets' );

/**
 * dispatcher عمومی ذخیره — برای صفحاتی که در jluxe_settings_sections_map()
 * تعریف شدن. صفحات با منطق خاص (homepage/ai-assistant/import-export)
 * handler جدای خودشون رو دارن (در render.php/homepage.php/ai.php) و این
 * تابع رو صدا نمی‌زنن.
 *
 * @return string|null وضعیت: 'saved' | 'error' | null (فرمی پست نشده).
 */
function jluxe_handle_generic_settings_save( string $page_slug ): ?string {
	if ( ! isset( $_POST['jluxe_settings_nonce'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
		return 'error';
	}

	$map = jluxe_settings_sections_map();
	if ( ! isset( $map[ $page_slug ] ) ) {
		return null;
	}
	[ $section_key, $sanitize_fn ] = $map[ $page_slug ];
	$defaults = jluxe_theme_settings_defaults();

	if ( ! empty( $_POST['jluxe_reset_section'] ) ) {
		jluxe_update_settings_section( $section_key, $defaults[ $section_key ] );
		return 'reset';
	}

	$posted = wp_unslash( $_POST[ $section_key ] ?? array() );
	$clean  = call_user_func( $sanitize_fn, $posted, $defaults[ $section_key ] );

	jluxe_update_settings_section( $section_key, $clean );

	return 'saved';
}

/**
 * تبِ «هدر» طبق درخواستِ کاربر («هدر و منوی هدر رو یکی کن») حالا دو فرمِ
 * مستقل (رفتار هدر + منوی هدر) روی یک صفحه داره. هر دو همچنان از همون
 * jluxe_handle_generic_settings_save/جدول jluxe_settings_sections_map
 * استفاده می‌کنن (نه منطق جدید) — این تابع فقط بر اساس اینکه کدوم فرم
 * واقعاً پست شده (کلیدِ سطحِ اولِ $_POST، «header» یا «header_nav») به
 * handler درستش می‌فرسته، چون هر دو فرم به همین صفحه (jluxe-header) پست
 * می‌شن و بدونِ این تشخیص، همیشه فقط بخشِ «header» ذخیره می‌شد.
 */
function jluxe_handle_header_combined_save(): ?string {
	if ( isset( $_POST['header_nav'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-header-nav' );
	}
	if ( isset( $_POST['header'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-header' );
	}
	return null;
}

/**
 * تبِ «تنظیمات» طبق درخواستِ کاربر («سئو + عملکرد + CSS/JS سفارشی +
 * درون‌ریزی/برون‌بری رو یکی کن») حالا سه فرمِ مستقل (سئو، عملکرد، CSS/JS
 * سفارشی — هرکدوم توی آکاردئونِ خودش) روی یک صفحه داره؛ همون الگوی
 * jluxe_handle_header_combined_save بالا: بر اساس اینکه کدوم کلیدِ سطحِ
 * اولِ $_POST واقعاً پست شده تشخیص می‌ده کدوم فرم ذخیره شده، بدونِ اینکه
 * منطقِ ذخیره‌سازیِ خودِ هر بخش (nonce/sanitize/reset) تغییر کنه. بخشِ
 * درون‌ریزی/برون‌بری چون از handler کاملاً جدای خودش (jluxe_handle_import/
 * jluxe_handle_full_reset، در theme-settings-import-export.php) استفاده
 * می‌کنه و nonce/فیلدِ POST متفاوتی داره، نیازی به این dispatcher نداره —
 * مستقیم توی jluxe_render_advanced_page() صدا زده می‌شه.
 */
function jluxe_handle_advanced_combined_save(): ?string {
	if ( isset( $_POST['seo'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-seo' );
	}
	if ( isset( $_POST['performance'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-performance' );
	}
	if ( isset( $_POST['custom_code'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-custom-code' );
	}
	return null;
}

/**
 * تبِ «رنگ و تایپوگرافی» — همون الگوی dispatcher بالا برای دو فرمِ مستقلِ
 * «رنگ‌ها» و «تایپوگرافی» روی یک صفحه.
 */
function jluxe_handle_colors_typography_combined_save(): ?string {
	if ( isset( $_POST['jluxe_apply_preset'] ) ) {
		return jluxe_handle_apply_preset();
	}
	if ( isset( $_POST['colors'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-colors' );
	}
	if ( isset( $_POST['typography'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-typography' );
	}
	return null;
}

/**
 * تبِ «محصول» — همون الگو برای «کارت محصول» و «صفحه محصول».
 */
function jluxe_handle_product_combined_save(): ?string {
	if ( isset( $_POST['product_card'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-product-card' );
	}
	if ( isset( $_POST['product_page'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-product-page' );
	}
	return null;
}

/**
 * تبِ «موبایل و تماس» — فرمِ «موبایل» یک بخشِ ساده‌ست (mobile[...]) ولی فرمِ
 * «تماس و شبکه‌های اجتماعی» یک فرمِ واحده که هم‌زمان contact[...] و
 * social[...] رو با هم پست می‌کنه (یک submit، دو بخشِ تنظیمات). باگِ واقعیِ
 * نسخه‌ی قبلی: چون jluxe_handle_generic_settings_save('jluxe-contact')
 * همیشه یک مقدارِ غیر-null برمی‌گردوند (تا وقتی nonce حاضر باشه، که همیشه
 * هست)، شرطِ «فقط اگه contact نال بود social رو ذخیره کن» هیچ‌وقت درست
 * نمی‌شد — یعنی مقادیرِ شبکه‌های اجتماعی پست می‌شدن ولی هیچ‌وقت ذخیره
 * نمی‌شدن. این‌جا هر دو بخش، وقتی هرکدوم از contact/social واقعاً پست شده
 * باشه، صریحاً و مستقل از هم ذخیره می‌شن.
 */
function jluxe_handle_mobile_contact_combined_save(): ?string {
	if ( isset( $_POST['mobile'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-mobile' );
	}
	if ( isset( $_POST['contact'] ) || isset( $_POST['social'] ) ) {
		$status = isset( $_POST['contact'] ) ? jluxe_handle_generic_settings_save( 'jluxe-contact' ) : null;
		if ( isset( $_POST['social'] ) && current_user_can( 'manage_options' ) && isset( $_POST['jluxe_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
			$defaults = jluxe_theme_settings_defaults();
			jluxe_update_settings_section( 'social', jluxe_sanitize_social( wp_unslash( $_POST['social'] ), $defaults['social'] ) );
			$status = $status ?? 'saved';
		}
		return $status;
	}
	return null;
}

/**
 * صفحه‌ی «تماس و صفحات» — طبقِ درخواستِ کاربر («شماره‌تلفن‌ها، روش‌های
 * تماس، ساعتِ پاسخگویی، توضیحاتِ سایت، توضیحاتِ صفحه‌ی تماس/درباره، شبکه‌های
 * اجتماعی، لوگوی بالای این دو صفحه رو همه توی یک تبِ پوسته بذار») یک
 * محلِ واحد برای همه‌ی این تنظیماته. اکثرشون (contact/social) از قبل یک
 * بخشِ خودشون دارن، فقط این‌جا هم در دسترسن؛ ولی ساعاتِ پاسخگویی
 * (footer.support_hours) و توضیحِ کوتاهِ سایت (identity.short_description)
 * توی یک آرایه‌ی بزرگ‌تر (فوتر/هویت) هستن — اگه فرمِ این صفحه مستقیم
 * jluxe_update_settings_section('footer', [...]) صدا بزنه، همه‌ی بقیه‌ی
 * تنظیماتِ فوتر (کارت‌های مزیت، ستون‌های لینک، نمادِ اعتماد) پاک می‌شن؛
 * برای همین این دو فیلد جدا، با merge روی مقدارِ *فعلیِ* همون بخش، ذخیره
 * می‌شن — نه با جایگزینیِ کاملِ بخش.
 */
function jluxe_handle_contact_pages_combined_save(): ?string {
	if ( isset( $_POST['contact'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-contact' );
	}
	if ( isset( $_POST['social'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-social' );
	}
	if ( isset( $_POST['info_pages'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-contact-pages' );
	}
	if ( isset( $_POST['jluxe_support_hours_field'] ) && isset( $_POST['jluxe_settings_nonce'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
			return 'error';
		}
		$current                    = jluxe_get_theme_settings()['footer'];
		$current['support_hours']  = sanitize_text_field( wp_unslash( $_POST['jluxe_support_hours_field'] ) );
		jluxe_update_settings_section( 'footer', $current );
		return 'saved';
	}
	if ( isset( $_POST['jluxe_site_description_field'] ) && isset( $_POST['jluxe_settings_nonce'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
			return 'error';
		}
		$current                     = jluxe_get_theme_settings()['identity'];
		$current['short_description'] = sanitize_text_field( wp_unslash( $_POST['jluxe_site_description_field'] ) );
		jluxe_update_settings_section( 'identity', $current );
		return 'saved';
	}
	return null;
}

/**
 * صفحه‌ی «صفحات راهنما» — دو فرمِ مستقل («متنِ صفحاتِ راهنما» و «اطلاعاتِ
 * حسابِ کارت‌به‌کارت») روی یک صفحه، همون الگوی سایرِ combined-save handlerها.
 */
function jluxe_handle_category_icons_save(): ?string {
	if ( ! isset( $_POST['category_icons'], $_POST['jluxe_settings_nonce'] ) ) return null;
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.' );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) return 'error';
	foreach ( (array) $_POST['category_icons'] as $term_id => $row ) {
		$term_id = absint( $term_id );
		if ( ! $term_id || ! term_exists( $term_id, 'product_cat' ) ) continue;
		update_term_meta( $term_id, '_jluxe_category_icon', isset( $row['icon'] ) ? sanitize_key( $row['icon'] ) : '' );
		update_term_meta( $term_id, '_jluxe_category_icon_svg', isset( $row['svg'] ) ? jluxe_sanitize_svg_markup( wp_unslash( $row['svg'] ) ) : '' );
	}
	return 'saved';
}


function jluxe_handle_guide_pages_combined_save(): ?string {
	if ( isset( $_POST['guide_pages'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-guide-pages' );
	}
	if ( isset( $_POST['payment_account'] ) ) {
		return jluxe_handle_generic_settings_save( 'jluxe-payment-account' );
	}
	return null;
}

/**
 * فقط یک بخش از تنظیمات رو آپدیت می‌کنه — بقیه‌ی بخش‌ها دست‌نخورده می‌مونن.
 * منبع واحد حقیقت برای «ذخیره‌ی جزئی» که همه‌ی handlerهای صفحات ازش
 * استفاده می‌کنن.
 */
function jluxe_update_settings_section( string $section_key, array $section_value ): void {
	$stored                  = get_option( JLUXE_SETTINGS_OPTION, array() );
	$stored                  = is_array( $stored ) ? $stored : array();
	$stored['version']       = JLUXE_SETTINGS_VERSION;
	$stored[ $section_key ]  = $section_value;
	update_option( JLUXE_SETTINGS_OPTION, $stored, false );
	update_option( 'jluxe_theme_settings_updated_at', current_time( 'mysql' ), false );
	wp_cache_delete( 'alloptions', 'options' );

	if ( 'homepage' === $section_key && function_exists( 'jluxe_sync_homepage_banner_exclusions' ) ) {
		jluxe_sync_homepage_banner_exclusions( $section_value['sections'] ?? array() );
	}
}

/**
 * صفحه‌ی جاری (بر اساس $_GET['page']) تنظیمات تازه‌خوانی‌شده رو برمی‌گردونه —
 * برای نمایش درست فرم بلافاصله بعد از ذخیره (نه نسخه‌ی کش‌شده‌ی قبل از ذخیره).
 */
function jluxe_get_fresh_settings(): array {
	wp_cache_delete( 'alloptions', 'options' );
	return jluxe_array_merge_deep( jluxe_theme_settings_defaults(), get_option( JLUXE_SETTINGS_OPTION, array() ) );
}

require_once __DIR__ . '/theme-settings-render.php';
require_once __DIR__ . '/theme-settings-import-export.php';
