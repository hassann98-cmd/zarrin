<?php
/** New document-driven editor. Legacy form layout is not used by this screen. */
defined( 'ABSPATH' ) || exit;

function jluxe_builder2_revision(): string {
	return hash( 'sha256', wp_json_encode( get_option( JLUXE_VISUAL_BUILDER_DRAFT_OPTION, array() ) ) );
}

function jluxe_builder2_enqueue(): void {
	wp_enqueue_media();
	$base = JLUXE_THEME_URI . '/assets/';
	foreach ( array( 'builder-tree', 'visual-builder-v2' ) as $file ) {
		wp_enqueue_script( 'jluxe-' . $file, $base . 'js/' . $file . '.js', 'builder-tree' === $file ? array() : array( 'jluxe-builder-tree', 'jluxe-settings-admin' ), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/' . $file . '.js' ), true );
	}
	wp_enqueue_style( 'jluxe-builder2', $base . 'css/visual-builder-v2.css', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/css/visual-builder-v2.css' ) );
	wp_localize_script( 'jluxe-visual-builder-v2', 'jluxeBuilder2', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'jluxe_builder2' ),
		'homepage' => jluxe_visual_builder_get_homepage(), 'types' => jluxe_visual_builder_section_types(),
		'revision' => jluxe_builder2_revision(), 'previewOrigin' => wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' ),
	) );
}

function jluxe_render_visual_builder_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) ); }
	?>
	<div id="jluxe-builder2" dir="rtl">
		<header class="jb-topbar">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-homepage' ) ); ?>" class="jb-exit">خروج از ویرایشگر</a>
			<strong>سازندهٔ صفحهٔ اصلی <small>ساختار درختی</small></strong>
			<div class="jb-devices" role="group" aria-label="اندازه پیش‌نمایش">
				<button type="button" data-jb-device="desktop" aria-pressed="true">دسکتاپ</button><button type="button" data-jb-device="tablet" aria-pressed="false">تبلت</button><button type="button" data-jb-device="mobile" aria-pressed="false">موبایل</button>
			</div>
			<button type="button" data-jb-action="undo" aria-label="واگرد">واگرد</button><button type="button" data-jb-action="redo" aria-label="ازنو">ازنو</button>
			<button type="button" data-jb-action="save">ذخیرهٔ پیش‌نویس</button><button type="button" class="jb-primary" data-jb-action="publish">انتشار در سایت</button>
		</header>
		<div class="jb-status" role="status" aria-live="polite" data-jb-status>در حال آماده‌سازی…</div>
		<div class="jb-workspace">
			<aside class="jb-sidebar" aria-label="المان‌ها و تنظیمات">
				<nav class="jb-tabs" aria-label="پنل ویرایشگر"><button type="button" data-jb-tab="library" aria-pressed="true">المان‌ها</button><button type="button" data-jb-tab="inspector" aria-pressed="false">تنظیمات المان</button><button type="button" data-jb-tab="layers" aria-pressed="false">لایه‌ها</button></nav>
				<section data-jb-panel="library"><label class="jb-search">جست‌وجوی المان<input type="search" data-jb-search placeholder="تیتر، تصویر، محصول…" /></label><p>برای افزودن داخل کانتینر، ابتدا آن را انتخاب کنید. انتخاب سایر المان‌ها، درج بعد از همان المان را انجام می‌دهد.</p><button type="button" data-jb-action="root">درج در انتهای صفحه</button><div class="jb-library" data-jb-library></div></section>
				<section data-jb-panel="inspector" hidden><h2 data-jb-selected>المانی انتخاب نشده</h2><div class="jb-actions" data-jb-node-actions><button type="button" data-jb-action="parent">والد</button><button type="button" data-jb-action="up">بالا</button><button type="button" data-jb-action="down">پایین</button><button type="button" data-jb-action="duplicate">تکثیر</button><button type="button" data-jb-action="remove">حذف</button><button type="button" data-jb-tab="library">افزودن المان</button></div><form data-jb-inspector aria-label="مشخصات المان انتخاب‌شده"></form></section>
				<section data-jb-panel="layers" hidden><p>المان را روی نام کانتینر رها کنید تا داخل آن قرار بگیرد؛ روی المان‌های دیگر، پیش از آن درج می‌شود. برای صفحه‌کلید از ابزار انتقال زیر استفاده کنید.</p><div data-jb-layers></div><label>انتقال انتخاب به کانتینر<select data-jb-destination></select></label><button type="button" data-jb-action="move">انتقال به انتهای مقصد</button></section>
				<footer class="jb-document-tools"><button type="button" data-jb-action="export">خروجی پشتیبان JSON</button><label>ورود چیدمان<input type="file" data-jb-import accept="application/json,.json" /></label><button type="button" data-jb-action="preview">تلاش مجدد پیش‌نمایش</button><a data-jb-open target="_blank" rel="noopener" hidden>بازکردن پیش‌نمایش در تب جدید</a></footer>
			</aside>
			<main class="jb-stage" aria-label="بوم واقعی سایت"><div class="jb-stage-caption">پیش‌نمایش خصوصی — تا «انتشار» سایت تغییر نمی‌کند</div><div class="jb-frame-wrap" data-jb-viewport="desktop"><iframe title="پیش‌نمایش صفحهٔ اصلی" data-jb-frame></iframe></div><div class="jb-empty" data-jb-empty hidden>صفحه خالی است؛ از پنل المان‌ها شروع کنید.</div><p class="jb-preview-status" data-jb-preview-status role="status"></p></main>
		</div>
	</div>
	<?php
}

/** One JSON field avoids PHP max_input_vars silently truncating repeaters. */
function jluxe_builder2_parse_document( $raw ): ?array {
	if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) { return null; }
	$data = json_decode( $raw, true, 32 );
	if ( ! is_array( $data ) || ! is_array( $data['sections'] ?? null ) || ! jluxe_builder_validate_tree( $data['sections'] ) ) { return null; }
	$clean = jluxe_visual_builder_sanitize_homepage_data( $data );
	return jluxe_builder_validate_tree( $clean['sections'] ) ? $clean : null;
}

function jluxe_builder2_ajax(): void {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( is_string( $_POST['nonce'] ?? null ) ? wp_unslash( $_POST['nonce'] ) : '', 'jluxe_builder2' ) ) {
		wp_send_json_error( array( 'message' => 'نشست معتبر نیست یا اجازهٔ ویرایش ندارید؛ صفحه را تازه کنید.' ), 403 ); return;
	}
	$op = is_string( $_POST['operation'] ?? null ) ? $_POST['operation'] : '';
	if ( ! in_array( $op, array( 'inspect', 'validate', 'preview', 'save', 'publish' ), true ) ) { wp_send_json_error( array( 'message' => 'عملیات ناشناخته است.' ), 400 ); return; }
	$doc = jluxe_builder2_parse_document( isset( $_POST['document'] ) ? wp_unslash( $_POST['document'] ) : null );
	if ( null === $doc ) { wp_send_json_error( array( 'message' => 'ساختار معتبر نیست؛ شناسه یکتا، حداکثر ۱۵۰ المان و عمق ۶ رعایت شود.' ), 400 ); return; }
	if ( 'inspect' === $op ) {
		$node = $doc['sections'][0] ?? null;
		if ( ! $node ) { wp_send_json_error( array( 'message' => 'المان انتخاب نشده است.' ), 400 ); return; }
		ob_start(); jluxe_render_homepage_section_editor( 0, $node, jluxe_visual_builder_section_types() ); $html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html ) ); return;
	}
	if ( 'validate' === $op ) { wp_send_json_success( array( 'homepage' => $doc ) ); return; }
	if ( 'preview' === $op ) {
		$revision = wp_generate_uuid4();
		set_transient( jluxe_visual_builder_live_preview_transient_key( get_current_user_id(), $revision ), array( 'revision' => $revision, 'homepage' => $doc ), 15 * MINUTE_IN_SECONDS );
		$url = add_query_arg( array( 'jluxe_builder_preview' => 'live', 'jvb_nonce' => wp_create_nonce( 'jluxe_visual_builder_preview' ), 'jvb_revision' => $revision ), home_url( '/' ) );
		wp_send_json_success( array( 'url' => esc_url_raw( $url ) ) ); return;
	}
	$base = is_string( $_POST['revision'] ?? null ) ? $_POST['revision'] : '';
	if ( ! hash_equals( jluxe_builder2_revision(), $base ) ) { wp_send_json_error( array( 'message' => 'پیش‌نویس در تب یا توسط مدیر دیگر تغییر کرده؛ ابتدا خروجی پشتیبان بگیرید و صفحه را تازه کنید.' ), 409 ); return; }
	if ( 'publish' === $op ) { jluxe_update_settings_section( 'homepage', $doc ); }
	jluxe_visual_builder_store_draft( $doc );
	wp_send_json_success( array( 'homepage' => $doc, 'revision' => jluxe_builder2_revision() ) );
}
add_action( 'wp_ajax_jluxe_builder2', 'jluxe_builder2_ajax' );

function jluxe_builder2_preview_bridge(): void {
	if ( ! jluxe_visual_builder_preview_request_is_authorized() ) { return; }
	if ( function_exists( 'show_admin_bar' ) ) { show_admin_bar( false ); }
	wp_enqueue_script( 'jluxe-builder-preview', JLUXE_THEME_URI . '/assets/js/builder-preview.js', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/builder-preview.js' ), false );
	$admin = admin_url();
	$origin = wp_parse_url( $admin, PHP_URL_SCHEME ) . '://' . wp_parse_url( $admin, PHP_URL_HOST ) . ( wp_parse_url( $admin, PHP_URL_PORT ) ? ':' . wp_parse_url( $admin, PHP_URL_PORT ) : '' );
	wp_localize_script( 'jluxe-builder-preview', 'jluxeBuilderPreview', array( 'parentOrigin' => $origin ) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_builder2_preview_bridge', 30 );
