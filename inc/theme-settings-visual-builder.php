<?php
/**
 * صفحه‌ساز بصریِ اختیاری — یک پیش‌نویسِ مستقل از تنظیماتِ فعالِ صفحهٔ اصلی.
 * این رابط از همان انواع بخش، فیلدها و sanitizerهای بومیِ پوسته استفاده
 * می‌کند؛ فقط دکمهٔ «انتشار» تنظیماتِ فعال را تغییر می‌دهد.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_VISUAL_BUILDER_PAGE = 'jluxe-visual-builder';
const JLUXE_VISUAL_BUILDER_DRAFT_OPTION = 'jluxe_visual_builder_draft';

/** query varهای preview ثبت می‌شوند تا canonical redirect امضای لینک را حذف نکند. */
function jluxe_visual_builder_register_preview_query_vars( array $vars ): array {
	foreach ( array( 'jluxe_builder_preview', 'jvb_nonce', 'jvb_revision' ) as $query_var ) {
		if ( ! in_array( $query_var, $vars, true ) ) {
			$vars[] = $query_var;
		}
	}
	return $vars;
}
add_filter( 'query_vars', 'jluxe_visual_builder_register_preview_query_vars' );

/** حالت تمام‌صفحه فقط روی همین route اعمال می‌شود. */
function jluxe_visual_builder_admin_body_class( string $classes ): string {
	$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( JLUXE_VISUAL_BUILDER_PAGE === $page ) {
		$classes .= ' jluxe-visual-builder-screen';
	}
	return $classes;
}
add_filter( 'admin_body_class', 'jluxe_visual_builder_admin_body_class' );

/** فایل‌های builder فقط روی صفحهٔ مستقل بارگذاری می‌شوند. */
function jluxe_visual_builder_enqueue_assets( string $hook ): void {
	if ( false === strpos( $hook, 'page_' . JLUXE_VISUAL_BUILDER_PAGE ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );

	$script_path = JLUXE_THEME_DIR . '/assets/js/visual-builder.js';
	$style_path  = JLUXE_THEME_DIR . '/assets/css/visual-builder.css';
	wp_enqueue_script(
		'jluxe-visual-builder',
		JLUXE_THEME_URI . '/assets/js/visual-builder.js',
		array( 'jquery', 'jquery-ui-sortable', 'jluxe-settings-admin' ),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0',
		true
	);
	wp_localize_script(
		'jluxe-visual-builder',
		'jluxeVisualBuilder',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'previewNonce' => wp_create_nonce( 'jluxe_visual_builder_live_preview' ),
			'previewAction'=> 'jluxe_visual_builder_preview',
		)
	);
	wp_enqueue_style(
		'jluxe-visual-builder',
		JLUXE_THEME_URI . '/assets/css/visual-builder.css',
		array( 'jluxe-settings-admin' ),
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : '1.0.0'
	);

	if ( function_exists( 'jluxe_generate_color_variables' ) && function_exists( 'jluxe_get_theme_settings' ) ) {
		$settings = jluxe_get_theme_settings();
		$css      = jluxe_generate_color_variables( (array) ( $settings['colors'] ?? array() ) );
		$css      = str_replace( ':root{', '#jluxe-visual-builder{', $css );
		wp_add_inline_style( 'jluxe-visual-builder', $css );
	}
}
add_action( 'admin_enqueue_scripts', 'jluxe_visual_builder_enqueue_assets', 20 );

/**
 * خواندنِ پیش‌نویسِ جداگانه؛ در اولین بازدید فقط یک کپیِ درون‌حافظه‌ای از
 * چیدمانِ فعال ساخته می‌شود و هیچ گزینه‌ای در دیتابیس یا فرانت‌اند تغییر نمی‌کند.
 */
function jluxe_visual_builder_get_homepage(): array {
	$settings = jluxe_get_theme_settings();
	$live     = isset( $settings['homepage'] ) && is_array( $settings['homepage'] ) ? $settings['homepage'] : array();
	$stored   = get_option( JLUXE_VISUAL_BUILDER_DRAFT_OPTION, null );

	if ( ! is_array( $stored ) || ! isset( $stored['homepage'] ) || ! is_array( $stored['homepage'] ) ) {
		return $live;
	}

	return jluxe_sanitize_homepage( $stored['homepage'], $live );
}

/** متادیتای ذخیره، بدون واردشدن به option اصلی تنظیمات. */
function jluxe_visual_builder_get_draft_metadata(): array {
	$stored = get_option( JLUXE_VISUAL_BUILDER_DRAFT_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
}

function jluxe_visual_builder_store_draft( array $homepage ): void {
	update_option(
		JLUXE_VISUAL_BUILDER_DRAFT_OPTION,
		array(
			'schema'     => 1,
			'homepage'   => $homepage,
			'updated_at' => current_time( 'mysql' ),
		),
		false
	);
}

/** نوع پیش‌نمایش باید در صفحهٔ اصلی و با نشستِ معتبرِ مدیر باز شود. */
function jluxe_visual_builder_preview_request_mode(): string {
	if ( ! isset( $_GET['jluxe_builder_preview'] ) || ! is_string( $_GET['jluxe_builder_preview'] ) ) {
		return '';
	}

	$mode = sanitize_key( wp_unslash( $_GET['jluxe_builder_preview'] ) );
	return in_array( $mode, array( 'draft', 'live' ), true ) ? $mode : '';
}

function jluxe_visual_builder_preview_request_is_authorized(): bool {
	if ( is_admin() || ( function_exists( 'is_front_page' ) && ! is_front_page() ) || '' === jluxe_visual_builder_preview_request_mode() ) {
		return false;
	}
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! isset( $_GET['jvb_nonce'] ) || ! is_string( $_GET['jvb_nonce'] ) ) {
		return false;
	}

	$nonce = sanitize_text_field( wp_unslash( $_GET['jvb_nonce'] ) );
	return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'jluxe_visual_builder_preview' );
}

function jluxe_visual_builder_live_preview_transient_key( int $user_id = 0 ): string {
	$user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
	return 'jluxe_visual_builder_live_' . absint( $user_id );
}

/** دریافتِ تنها دادهٔ مجاز برای preview؛ هیچ option عمومی در این مسیر نوشته نمی‌شود. */
function jluxe_visual_builder_get_authorized_preview_homepage(): ?array {
	if ( ! jluxe_visual_builder_preview_request_is_authorized() ) {
		return null;
	}

	$mode = jluxe_visual_builder_preview_request_mode();
	$data = 'live' === $mode
		? get_transient( jluxe_visual_builder_live_preview_transient_key() )
		: get_option( JLUXE_VISUAL_BUILDER_DRAFT_OPTION, array() );

	if ( ! is_array( $data ) || ! isset( $data['homepage'] ) || ! is_array( $data['homepage'] ) || ! isset( $data['homepage']['sections'] ) || ! is_array( $data['homepage']['sections'] ) ) {
		return null;
	}

	return $data['homepage'];
}

/**
 * جایگزینیِ موقتِ تنظیماتِ صفحهٔ اصلی فقط در درخواستِ مجازِ preview.
 * نسخهٔ تنظیمات را به نسخهٔ جاری می‌بریم تا migration دادهٔ پیش‌نمایش را
 * روی option اصلیِ سایت ننویسد.
 */
function jluxe_visual_builder_filter_frontend_settings_for_preview( $settings ) {
	$homepage = jluxe_visual_builder_get_authorized_preview_homepage();
	if ( null === $homepage ) {
		return $settings;
	}

	$settings            = is_array( $settings ) ? $settings : array();
	$settings['homepage'] = $homepage;
	$settings['version']  = JLUXE_SETTINGS_VERSION;
	return $settings;
}
add_filter( 'option_' . JLUXE_SETTINGS_OPTION, 'jluxe_visual_builder_filter_frontend_settings_for_preview', 10, 1 );

/** پیش‌نمایشِ خصوصی cache نمی‌شود و به موتورهای جست‌وجو معرفی نمی‌شود. */
function jluxe_visual_builder_prepare_preview_response(): void {
	if ( null === jluxe_visual_builder_get_authorized_preview_homepage() ) {
		return;
	}

	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
	}
}
add_action( 'template_redirect', 'jluxe_visual_builder_prepare_preview_response', 0 );

/** بخش‌های اضافهٔ جدید را فقط در صفحه‌ساز مستقل عرضه می‌کنیم. */
function jluxe_visual_builder_section_types(): array {
	$types = jluxe_homepage_section_types();
	$types['cta'] = 'دعوت به اقدام';
	return $types;
}

/** گروه‌بندیِ سادهٔ کتابخانه بر اساس انواع واقعیِ صفحه‌سازِ موجود. */
function jluxe_visual_builder_section_group( string $type ): string {
	if ( in_array( $type, array( 'hero', 'stories', 'banner', 'banner_two', 'banner_three', 'banner_slider', 'banner_collage', 'brand_marquee' ), true ) ) {
		return 'بنر و رسانه';
	}
	if ( in_array( $type, array( 'product_grid', 'category_grid', 'category_showcase', 'brick_products', 'special_products', 'recommended_panels', 'trust', 'blog' ), true ) ) {
		return 'محصول و دسته‌بندی';
	}
	return 'محتوای تکمیلی';
}

function jluxe_visual_builder_section_icon( string $type ): string {
	$icons = array(
		'hero'               => 'format-image',
		'stories'            => 'format-gallery',
		'product_grid'       => 'grid-view',
		'category_grid'      => 'category',
		'category_showcase'  => 'images-alt2',
		'brand_marquee'      => 'slides',
		'banner'             => 'cover-image',
		'banner_two'         => 'columns',
		'banner_three'       => 'columns',
		'banner_slider'      => 'images-alt',
		'brick_products'     => 'screenoptions',
		'special_products'   => 'awards',
		'banner_collage'     => 'layout',
		'recommended_panels' => 'screenoptions',
		'trust'              => 'yes-alt',
		'blog'               => 'admin-post',
		'text'               => 'editor-paragraph',
		'html'               => 'editor-code',
		'spacer'             => 'minus',
		'cta'                 => 'megaphone',
	);
	return $icons[ $type ] ?? 'screenoptions';
}

/**
 * دادهٔ فرم را با sanitizer بومی صفحهٔ اصلی پاک‌سازی می‌کند، اما در مسیرِ
 * ذخیرهٔ پیش‌نویس فقط option مستقل را می‌نویسد.
 */
function jluxe_visual_builder_sanitize_homepage_data( array $posted ): array {
	// نبودنِ ردیف‌ها وقتی کاربر همهٔ بخش‌ها را پاک کرده، با یک فرمِ خالی
	// اشتباه نشود؛ sentinel داخل فرم می‌گوید این آرایه عمداً خالی است.
	if ( ! isset( $posted['sections'] ) && isset( $posted['sections_present'] ) ) {
		$posted['sections'] = array();
	}
	unset( $posted['sections_present'] );

	if ( isset( $posted['sections'] ) && is_array( $posted['sections'] ) ) {
		$allowed_types = jluxe_visual_builder_section_types();
		$posted['sections'] = array_values(
			array_filter(
				$posted['sections'],
				static function ( $section ) use ( $allowed_types ): bool {
					if ( ! is_array( $section ) ) {
						return false;
					}
					$type = isset( $section['type'] ) ? sanitize_key( (string) $section['type'] ) : '';
					return array_key_exists( $type, $allowed_types );
				}
			)
		);
	}

	$settings = jluxe_get_theme_settings();
	$defaults = isset( $settings['homepage'] ) && is_array( $settings['homepage'] ) ? $settings['homepage'] : array();
	return jluxe_sanitize_homepage( $posted, $defaults );
}

function jluxe_visual_builder_sanitize_posted_homepage(): array {
	if ( ! isset( $_POST['homepage'] ) || ! is_array( $_POST['homepage'] ) ) {
		wp_die( esc_html__( 'دادهٔ صفحه‌ساز معتبر نیست.', 'jluxe' ), '', array( 'response' => 400 ) );
	}

	$posted = wp_unslash( $_POST['homepage'] );
	if ( ! is_array( $posted ) ) {
		wp_die( esc_html__( 'دادهٔ صفحه‌ساز معتبر نیست.', 'jluxe' ), '', array( 'response' => 400 ) );
	}

	return jluxe_visual_builder_sanitize_homepage_data( $posted );
}

/**
 * پیش‌نمایش زنده فقط در transient کوتاه‌عمرِ مدیر ذخیره می‌شود؛ option اصلی و
 * پیش‌نویسِ ذخیره‌شده با تایپ‌کردن یا افزودن یک جزء بازنویسی نمی‌شوند.
 */
function jluxe_visual_builder_handle_live_preview(): void {
	$user_id = (int) get_current_user_id();
	$nonce   = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( $user_id < 1 || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ ساخت پیش‌نمایش را ندارید.' ), 403 );
	}
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'jluxe_visual_builder_live_preview' ) ) {
		wp_send_json_error( array( 'message' => 'نشست صفحه‌ساز منقضی شده است؛ صفحه را تازه‌سازی کنید.' ), 403 );
	}
	if ( ! isset( $_POST['homepage'] ) || ! is_array( $_POST['homepage'] ) ) {
		wp_send_json_error( array( 'message' => 'دادهٔ صفحه‌ساز معتبر نیست.' ), 400 );
	}

	$posted   = wp_unslash( $_POST['homepage'] );
	$homepage = jluxe_visual_builder_sanitize_homepage_data( $posted );
	set_transient(
		jluxe_visual_builder_live_preview_transient_key( $user_id ),
		array( 'homepage' => $homepage ),
		15 * MINUTE_IN_SECONDS
	);

	$preview_url = add_query_arg(
		array(
			'jluxe_builder_preview' => 'live',
			'jvb_nonce'             => wp_create_nonce( 'jluxe_visual_builder_preview' ),
			'jvb_revision'          => uniqid( '', false ),
		),
		home_url( '/' )
	);
	wp_send_json_success( array( 'url' => esc_url( $preview_url ) ) );
}
add_action( 'wp_ajax_jluxe_visual_builder_preview', 'jluxe_visual_builder_handle_live_preview' );

function jluxe_visual_builder_redirect( string $status ): void {
	$url = add_query_arg(
		'jluxe_builder_status',
		sanitize_key( $status ),
		admin_url( 'admin.php?page=' . JLUXE_VISUAL_BUILDER_PAGE )
	);
	wp_safe_redirect( $url );
	exit;
}

function jluxe_visual_builder_handle_save(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ انجام این کار را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'jluxe_visual_builder_save', 'jluxe_visual_builder_save_nonce' );
	jluxe_visual_builder_store_draft( jluxe_visual_builder_sanitize_posted_homepage() );
	jluxe_visual_builder_redirect( 'saved' );
}
add_action( 'admin_post_jluxe_visual_builder_save', 'jluxe_visual_builder_handle_save' );

function jluxe_visual_builder_handle_publish(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ انجام این کار را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'jluxe_visual_builder_publish', 'jluxe_visual_builder_publish_nonce' );

	$homepage = jluxe_visual_builder_sanitize_posted_homepage();
	jluxe_update_settings_section( 'homepage', $homepage );
	jluxe_visual_builder_store_draft( $homepage );
	jluxe_visual_builder_redirect( 'published' );
}
add_action( 'admin_post_jluxe_visual_builder_publish', 'jluxe_visual_builder_handle_publish' );

/** رندر صفحهٔ کامل؛ فیلدهای ویرایش از رندررِ بومیِ هر ۲۰ نوع بخش می‌آیند. */
function jluxe_render_visual_builder_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ مشاهدهٔ این صفحه را ندارید.', 'jluxe' ), '', array( 'response' => 403 ) );
	}

	$homepage = jluxe_visual_builder_get_homepage();
	$sections = isset( $homepage['sections'] ) && is_array( $homepage['sections'] ) ? $homepage['sections'] : array();
	$types    = jluxe_visual_builder_section_types();
	$metadata = jluxe_visual_builder_get_draft_metadata();
	$status   = isset( $_GET['jluxe_builder_status'] ) && is_string( $_GET['jluxe_builder_status'] ) ? sanitize_key( wp_unslash( $_GET['jluxe_builder_status'] ) ) : '';
	$has_saved_draft = ! empty( $metadata['updated_at'] ) && isset( $metadata['homepage']['sections'] ) && is_array( $metadata['homepage']['sections'] );
	$preview_url     = home_url( '/' );
	if ( $has_saved_draft ) {
		$preview_url = add_query_arg(
			array(
				'jluxe_builder_preview' => 'draft',
				'jvb_nonce'             => wp_create_nonce( 'jluxe_visual_builder_preview' ),
			),
			$preview_url
		);
	}
	$exit_url = admin_url( 'admin.php?page=jluxe-homepage' );
	$groups      = array( 'بنر و رسانه' => array(), 'محصول و دسته‌بندی' => array(), 'محتوای تکمیلی' => array() );

	foreach ( $types as $type => $label ) {
		$group = jluxe_visual_builder_section_group( $type );
		$groups[ $group ][ $type ] = $label;
	}
	?>
	<div class="wrap jluxe-settings jluxe-visual-builder" id="jluxe-visual-builder" dir="rtl" data-jluxe-settings-app data-preview-kind="<?php echo $has_saved_draft ? 'draft' : 'live'; ?>" data-preview-url="<?php echo esc_url( $preview_url ); ?>">
		<form id="jluxe-visual-builder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			wp_nonce_field( 'jluxe_visual_builder_save', 'jluxe_visual_builder_save_nonce' );
			wp_nonce_field( 'jluxe_visual_builder_publish', 'jluxe_visual_builder_publish_nonce' );
			?>
			<input type="hidden" name="homepage[sections_present]" value="1" />

			<header class="jvb-topbar">
				<div class="jvb-brand">
					<a class="jvb-back" href="<?php echo esc_url( $exit_url ); ?>" aria-label="بازگشت به صفحه‌ساز فعلی">
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						<span>بازگشت به تنظیمات صفحهٔ اصلی</span>
					</a>
					<div class="jvb-title-wrap">
						<span class="jvb-mark" aria-hidden="true">ز</span>
						<div>
							<h1>صفحه‌ساز بصری</h1>
							<p data-jvb-section-count>پیش‌نویس مستقل · <?php echo esc_html( count( $sections ) ); ?> بخش</p>
						</div>
					</div>
				</div>

				<div class="jvb-toolbar" aria-label="ابزار پیش‌نمایش">
					<div class="jvb-device-switch" role="group" aria-label="اندازهٔ نمایش">
						<button type="button" data-jvb-viewport="desktop" aria-pressed="true" title="نمایش دسکتاپ"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="jvb-device-label">دسکتاپ</span></button>
						<button type="button" data-jvb-viewport="tablet" aria-pressed="false" title="نمایش تبلت، ۸۲۰ پیکسل"><span class="dashicons dashicons-tablet" aria-hidden="true"></span><span class="jvb-device-label">تبلت</span></button>
						<button type="button" data-jvb-viewport="mobile" aria-pressed="false" title="نمایش موبایل، ۳۹۰ پیکسل"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="jvb-device-label">موبایل</span></button>
					</div>
					<div class="jvb-history-controls" role="group" aria-label="واگرد و ازنو">
						<button type="button" data-jvb-undo disabled aria-label="واگرد (Ctrl+Z)" title="واگرد (Ctrl+Z)"><span class="dashicons dashicons-undo" aria-hidden="true"></span></button>
						<button type="button" data-jvb-redo disabled aria-label="ازنو (Ctrl+Shift+Z)" title="ازنو (Ctrl+Shift+Z)"><span class="dashicons dashicons-redo" aria-hidden="true"></span></button>
					</div>
					<button type="button" class="jvb-preview-toggle" data-jvb-preview-toggle aria-pressed="false" title="بزرگ‌نماییِ پیش‌نمایش">
						<span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span><span data-jvb-preview-label>تمرکز روی پیش‌نمایش</span>
					</button>
				</div>

				<div class="jvb-actions">
					<span class="jvb-save-state" data-jvb-save-state role="status" aria-live="polite"><?php echo ! empty( $metadata['updated_at'] ) ? 'پیش‌نویس ذخیره‌شده' : 'پیش‌نویس تازه'; ?></span>
					<button type="submit" class="jvb-save-button" name="action" value="jluxe_visual_builder_save">
						<span class="dashicons dashicons-cloud-saved" aria-hidden="true"></span>ذخیرهٔ پیش‌نویس
					</button>
					<button type="submit" class="jvb-publish-button" name="action" value="jluxe_visual_builder_publish" data-jvb-publish>
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>انتشار
					</button>
				</div>
			</header>

			<?php if ( 'saved' === $status ) : ?>
				<div class="jvb-notice jvb-notice--success" role="status">پیش‌نویس صفحه‌ساز ذخیره شد؛ نسخهٔ منتشرشده تغییری نکرد.</div>
			<?php elseif ( 'published' === $status ) : ?>
				<div class="jvb-notice jvb-notice--published" role="status">چیدمان صفحهٔ اصلی منتشر شد و اکنون نسخهٔ فعال سایت است.</div>
			<?php endif; ?>

			<div class="jvb-workspace">
				<aside class="jvb-library" aria-label="پنل‌های صفحه‌ساز">
					<nav class="jvb-tabs" role="tablist" aria-label="پنل صفحه‌ساز" aria-orientation="vertical">
						<button type="button" role="tab" id="jvb-tab-components" aria-controls="jvb-panel-components" aria-selected="true" data-jvb-tab="components"><span class="dashicons dashicons-screenoptions" aria-hidden="true"></span><span>المان‌ها</span></button>
						<button type="button" role="tab" id="jvb-tab-settings" aria-controls="jvb-panel-settings" aria-selected="false" data-jvb-tab="settings"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span>تنظیمات</span></button>
						<button type="button" role="tab" id="jvb-tab-structure" aria-controls="jvb-panel-structure" aria-selected="false" data-jvb-tab="structure"><span class="dashicons dashicons-screenoptions" aria-hidden="true"></span><span>ساختار</span></button>
						<button type="button" role="tab" id="jvb-tab-history" aria-controls="jvb-panel-history" aria-selected="false" data-jvb-tab="history"><span class="dashicons dashicons-backup" aria-hidden="true"></span><span>تاریخچه</span></button>
						<button type="button" role="tab" id="jvb-tab-page" aria-controls="jvb-panel-page" aria-selected="false" data-jvb-tab="page"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span>برگه</span></button>
					</nav>
					<div class="jvb-library-content">
						<div class="jvb-library-heading">
							<div><span class="jvb-eyebrow">ابزارهای بومی تم</span><h2 data-jvb-panel-title>المان‌های صفحه</h2></div>
							<span class="jvb-library-count" data-jvb-panel-count><?php echo esc_html( count( $types ) ); ?></span>
						</div>

					<div id="jvb-panel-components" class="jvb-tab-panel" role="tabpanel" aria-labelledby="jvb-tab-components" data-jvb-panel="components">
						<label class="screen-reader-text" for="jvb-component-search">جست‌وجوی اجزای صفحه</label>
						<div class="jvb-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="jvb-component-search" data-jvb-search placeholder="جست‌وجوی بخش‌ها…" autocomplete="off" /></div>
						<div class="jvb-component-groups" data-jvb-components>
							<?php foreach ( $groups as $group_name => $group_types ) : ?>
								<?php if ( empty( $group_types ) ) { continue; } ?>
								<section class="jvb-component-group" data-jvb-group>
									<h3><?php echo esc_html( $group_name ); ?></h3>
									<div class="jvb-component-grid">
										<?php foreach ( $group_types as $type => $label ) : ?>
											<button type="button" class="jvb-component" draggable="true" data-jvb-add-section data-type="<?php echo esc_attr( $type ); ?>" data-search-label="<?php echo esc_attr( $label . ' ' . $group_name ); ?>" title="برای افزودن کلیک کنید یا به بوم بکشید">
												<span class="jvb-component-icon dashicons dashicons-<?php echo esc_attr( jluxe_visual_builder_section_icon( $type ) ); ?>" aria-hidden="true"></span>
												<span><?php echo esc_html( $label ); ?></span>
												<span class="dashicons dashicons-plus-alt2 jvb-component-add-icon" aria-hidden="true"></span>
											</button>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
							<p class="jvb-empty-search" data-jvb-empty-search hidden>بخشی با این نام پیدا نشد.</p>
						</div>
						<p class="jvb-library-tip"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>برای جابه‌جایی، دستگیرهٔ کنار هر بخش را بکشید. تنظیمات جزئی همان بخش با کلیک باز می‌شود.</p>
					</div>

					<div id="jvb-panel-settings" class="jvb-tab-panel" role="tabpanel" aria-labelledby="jvb-tab-settings" data-jvb-panel="settings" hidden>
						<div class="jvb-canvas" data-jvb-canvas aria-label="تنظیمات صفحه و بخش‌ها">
							<div class="jvb-canvas-intro"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span><div><strong data-jvb-selection-title>تنظیمات صفحهٔ اصلی</strong><p>انتخاب یک بخش در پیش‌نمایش یا ساختار، تنظیمات بومی همان بخش را اینجا باز می‌کند.</p></div></div>
							<div class="jvb-global-setting">
								<strong>تنظیمات کلی صفحه</strong>
								<?php jluxe_hb_field_checkbox( 'homepage', 'mobile_category_swipe_hint_enabled', 'نمایش یک‌بارهٔ راهنمای حرکت ردیف دسته‌بندی در موبایل', ! array_key_exists( 'mobile_category_swipe_hint_enabled', $homepage ) || ! empty( $homepage['mobile_category_swipe_hint_enabled'] ), true ); ?>
								<p>این راهنما فقط برای چیدمان‌های نوار افقی اجرا می‌شود؛ با اولین لمس متوقف می‌شود و تنظیم حرکتِ کاهش‌یافتهٔ دستگاه را رعایت می‌کند.</p>
							</div>
							<div id="jluxe-hb-sections">
								<?php foreach ( $sections as $index => $section ) : ?>
									<?php if ( is_array( $section ) ) { jluxe_render_homepage_section_editor( $index, $section, $types ); } ?>
								<?php endforeach; ?>
							</div>
							<div class="jvb-drop-hint" data-jvb-drop-hint <?php echo empty( $sections ) ? '' : 'hidden'; ?>><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><strong>صفحه خالی است</strong><span>از کتابخانه یک بخش را روی پیش‌نمایش بکشید یا روی آن کلیک کنید.</span></div>
							<div class="jvb-canvas-footnote"><span class="dashicons dashicons-info" aria-hidden="true"></span>این پیش‌نمایش از رندر واقعیِ پوسته و داده‌های فروشگاه استفاده می‌کند؛ ذخیره یا انتشار فقط با دکمه‌های بالا انجام می‌شود.</div>
						</div>
					</div>

					<div id="jvb-panel-structure" class="jvb-tab-panel" role="tabpanel" aria-labelledby="jvb-tab-structure" data-jvb-panel="structure" hidden>
						<div class="jvb-outline-heading"><strong>ترتیب فعلی</strong><span data-jvb-outline-count><?php echo esc_html( count( $sections ) ); ?></span></div>
						<ol class="jvb-outline" data-jvb-outline>
							<?php foreach ( $sections as $index => $section ) : ?>
								<?php $section_type = is_array( $section ) ? (string) ( $section['type'] ?? '' ) : ''; ?>
								<li><button type="button" data-jvb-outline-target="<?php echo esc_attr( (string) $index ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( jluxe_visual_builder_section_icon( $section_type ) ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $types[ $section_type ] ?? $section_type ); ?></span><span class="jvb-outline-state"><?php echo ! empty( $section['enabled'] ) ? 'فعال' : 'خاموش'; ?></span></button></li>
							<?php endforeach; ?>
						</ol>
						<p class="jvb-outline-empty" data-jvb-outline-empty <?php echo empty( $sections ) ? '' : 'hidden'; ?>>هنوز بخشی به پیش‌نویس اضافه نشده است.</p>
					</div>

					<div id="jvb-panel-history" class="jvb-tab-panel" role="tabpanel" aria-labelledby="jvb-tab-history" data-jvb-panel="history" hidden>
						<div class="jvb-history-heading"><div><strong>تغییرهای این نشست</strong><span>ذخیره‌شده در مرورگر، تا پیش از خروج از صفحه</span></div><span class="jvb-history-count" data-jvb-history-count>۱</span></div>
						<p class="jvb-history-empty" data-jvb-history-empty>با شروع ویرایش، واگردها و نسخه‌های قبلی اینجا نمایش داده می‌شوند.</p>
						<ol class="jvb-history-list" data-jvb-history-list aria-label="فهرست تاریخچهٔ صفحه"></ol>
						<p class="jvb-history-hint"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>برای واگرد و ازنو از دکمه‌های نوار بالا یا میانبرهای Ctrl+Z و Ctrl+Shift+Z استفاده کنید. تغییرها فقط در پیش‌نویس مستقل باقی می‌مانند.</p>
					</div>

					<div id="jvb-panel-page" class="jvb-tab-panel" role="tabpanel" aria-labelledby="jvb-tab-page" data-jvb-panel="page" hidden>
						<div class="jvb-page-card">
							<div class="jvb-page-card-heading"><span class="dashicons dashicons-admin-home" aria-hidden="true"></span><div><strong>صفحهٔ اصلی</strong><span>تنها برگهٔ قابل ویرایش در این صفحه‌ساز</span></div></div>
							<div class="jvb-page-badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span>پیش‌نویس مستقل و خصوصی</div>
							<p>ذخیره‌کردن این صفحه، نسخهٔ فعال سایت را تغییر نمی‌دهد. فقط با انتخاب صریح «انتشار» چیدمان فعلی صفحهٔ اصلی جایگزین می‌شود.</p>
							<div class="jvb-page-metadata"><strong>آخرین ذخیره</strong><span><?php echo ! empty( $metadata['updated_at'] ) ? esc_html( (string) $metadata['updated_at'] ) : 'هنوز ذخیره نشده'; ?></span></div>
							<a class="jvb-page-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span>مشاهدهٔ نسخهٔ منتشرشده</a>
							<a class="jvb-page-link" href="<?php echo esc_url( $exit_url ); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>بازگشت به تنظیمات بومی صفحهٔ اصلی</a>
						</div>
					</div>
				</div>
				</aside>

				<main class="jvb-stage" aria-label="بوم صفحه‌ساز">
					<div class="jvb-stage-toolbar">
						<div><strong data-jvb-stage-title>پیش‌نمایش زندهٔ صفحهٔ اصلی</strong><span data-jvb-stage-subtitle>تغییراتِ ذخیره‌نشده فقط در این بومِ خصوصی نمایش داده می‌شوند</span></div>
						<a class="jvb-open-site" data-jvb-open-preview href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span>بازکردن همین پیش‌نمایش در زبانهٔ تازه</a>
					</div>

					<div class="jvb-canvas-wrap" data-jvb-canvas-wrap data-viewport="desktop">
						<div class="jvb-live-preview" data-jvb-live-preview>
							<div class="jvb-preview-disclaimer"><span class="dashicons dashicons-lock" aria-hidden="true"></span><span data-jvb-preview-disclaimer-text>پیش‌نمایش خصوصی و زنده است؛ بازدیدکنندگان تا زمان انتشار همان صفحهٔ فعلی را می‌بینند.</span></div>
							<div class="jvb-preview-frame-shell" data-jvb-preview-frame-shell>
								<iframe title="پیش‌نمایش زندهٔ صفحهٔ اصلی" data-jvb-preview-frame data-src="<?php echo esc_url( $preview_url ); ?>" src="<?php echo esc_url( $preview_url ); ?>" loading="eager"></iframe>
								<div class="jvb-preview-loading" data-jvb-preview-loading role="status" hidden>در حال به‌روزرسانی پیش‌نمایش زنده…</div>
								<div class="jvb-preview-drop-overlay" data-jvb-preview-drop-overlay hidden><span class="dashicons dashicons-move" aria-hidden="true"></span><strong>برای افزودن به انتهای صفحه رها کنید</strong></div>
							</div>
							<div class="jvb-preview-status" data-jvb-preview-status role="status" aria-live="polite">در حال آماده‌سازی بوم زنده…</div>
						</div>
					</div>
				</main>
			</div>

			<div class="jvb-legacy-controls" aria-hidden="true">
				<select id="jluxe-hb-add-type" tabindex="-1">
					<?php foreach ( $types as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select>
				<button type="button" id="jluxe-hb-add" tabindex="-1">افزودن</button>
			</div>

			<div id="jluxe-hb-templates" class="jvb-templates" hidden>
				<?php foreach ( $types as $type => $label ) : ?>
					<template data-hb-template="<?php echo esc_attr( $type ); ?>"><?php jluxe_render_homepage_section_editor( '__INDEX__', array( 'id' => '', 'type' => $type, 'enabled' => true ), $types, true ); ?></template>
				<?php endforeach; ?>
			</div>
		</form>
	</div>
	<?php
}
