<?php
/**
 * رندر صفحات ادمین JLuxe. هر تابع jluxe_render_{page}_page() یک زیرمنو
 * رو رندر می‌کنه؛ منطق ذخیره (اگر ساده باشه) از jluxe_handle_generic_settings_save()
 * میاد، صفحات پیچیده‌تر (homepage/ai-assistant/import-export) handler
 * خودشون رو در فایل‌های جدا دارن.
 */

defined( 'ABSPATH' ) || exit;

/**
 * پوسته‌ی مشترک همه‌ی صفحات — تب فعال منو، نوتیس ذخیره، عنوان.
 */
function jluxe_settings_page_shell( string $title, string $current_slug, ?string $status, callable $render_form ): void {
	?>
	<div class="wrap jluxe-settings" dir="rtl">
		<h1><span class="dashicons dashicons-store"></span> <?php echo esc_html( $title ); ?></h1>

		<?php if ( 'saved' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p>✓ تنظیمات با موفقیت ذخیره شد.</p></div>
		<?php elseif ( 'reset' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p>✓ این بخش به حالت پیش‌فرض بازگشت.</p></div>
		<?php elseif ( 'preset_applied' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p>✓ پریست اعمال شد — رنگ‌ها و تایپوگرافی آپدیت شدن.</p></div>
		<?php elseif ( 'error' === $status ) : ?>
			<div class="notice notice-error is-dismissible"><p>✕ ذخیره انجام نشد (nonce نامعتبر — صفحه رو رفرش کن و دوباره تلاش کن).</p></div>
		<?php endif; ?>

		<div class="jluxe-settings-layout">
			<nav class="jluxe-settings-nav">
				<?php jluxe_render_settings_nav( $current_slug ); ?>
			</nav>
			<div class="jluxe-settings-content">
				<?php $render_form(); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * نوارِ تبِ افقیِ بالای هر صفحه‌ی تنظیمات — قبلاً یک نوار کناریِ عمودی بود که
 * کنارِ لیستِ طویلِ زیرمنوهای خودِ وردپرس تکراری به نظر می‌رسید؛ طبق درخواستِ
 * صریحِ کاربر («لیست دراز از منوی پیشخوان حذف شه، تنظیمات بصورت تب») حالا
 * تنها راهِ ناوبری بینِ بخش‌هاست — لیستِ زیرمنوهای وردپرس با
 * jluxe_hide_settings_submenus() (پایین همین فایل) از سایدبارِ پیشخوان مخفی
 * می‌شه، فقط یک آیتم («JLuxe Theme») می‌مونه. هر تب هنوز یک آدرسِ واقعیِ
 * admin.php?page=X مستقله (routeing/nonce/ذخیره‌سازیِ هر بخش دست‌نخورده)،
 * فقط الان به‌جای سایدبار به‌صورت افقی و با اسکرولِ لمسی نمایش داده می‌شه.
 */
function jluxe_render_settings_nav( string $current_slug ): void {
	$groups = array(
		'شروع و هویت' => array(
			array( JLUXE_SETTINGS_MENU_SLUG, 'داشبورد', 'dashboard' ),
			array( 'jluxe-identity', 'هویت سایت', 'id' ),
			array( 'jluxe-homepage', 'صفحه اصلی', 'admin-home' ),
		),
		'ظاهر و برند' => array(
			array( 'jluxe-header', 'هدر و منوی هدر', 'align-center' ),
			array( 'jluxe-footer', 'فوتر', 'align-left' ),
			array( 'jluxe-colors-typography', 'رنگ و تایپوگرافی', 'admin-customizer' ),
			array( 'jluxe-mobile-contact', 'موبایل و تماس', 'smartphone' ),
		),
		'فروشگاه' => array(
			array( 'jluxe-product', 'محصول', 'products' ),
			array( 'jluxe-product-card', 'کارت محصول', 'screenoptions' ),
			array( 'jluxe-product-page', 'صفحه محصول', 'admin-page' ),
			array( 'jluxe-shop', 'فروشگاه و دسته‌بندی', 'store' ),
		),
		'محتوا و ارتباط' => array(
			array( 'jluxe-contact-pages', 'تماس و صفحات', 'admin-users' ),
			array( 'jluxe-guide-pages', 'صفحات راهنما', 'welcome-learn-more' ),
			array( 'jluxe-faq', 'سوالات متداول', 'editor-help' ),
			array( 'jluxe-review-criteria', 'معیارهای دیدگاه', 'star-filled' ),
		),
		'ابزار و فنی' => array(
			array( 'jluxe-sms', 'ورود با پیامک', 'smartphone' ),
			array( 'jluxe-ai-assistant', 'دستیار هوش مصنوعی', 'admin-site-alt3' ),
			array( 'jluxe-ai-tickets', 'گزارش‌های دستیار', 'feedback' ),
			array( 'jluxe-seo', 'سئو', 'search' ),
			array( 'jluxe-performance', 'عملکرد', 'performance' ),
			array( 'jluxe-custom-code', 'CSS/JS سفارشی', 'editor-code' ),
			array( 'jluxe-import-export', 'درون‌ریزی / برون‌بری', 'database-export' ),
			array( 'jluxe-advanced', 'تنظیمات پیشرفته', 'admin-tools' ),
		),
	);
	foreach ( $groups as $group_label => $items ) {
		$group_active = false;
		foreach ( $items as $item ) { if ( $item[0] === $current_slug ) { $group_active = true; break; } }
		?>
		<div class="jluxe-settings-nav-group<?php echo $group_active ? ' is-active' : ''; ?>">
			<button type="button" class="jluxe-settings-nav-group-toggle" aria-expanded="<?php echo $group_active ? 'true' : 'false'; ?>">
				<span><?php echo esc_html( $group_label ); ?></span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
			<div class="jluxe-settings-nav-group-items">
				<?php foreach ( $items as $item ) : [ $slug, $label, $icon ] = $item; $url = admin_url( 'admin.php?page=' . $slug ); $active = $slug === $current_slug ? ' jluxe-settings-nav-item-active' : ''; ?>
					<a href="<?php echo esc_url( $url ); ?>" class="jluxe-settings-nav-item<?php echo esc_attr( $active ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $label ); ?></span></a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
function jluxe_settings_submit_button( bool $with_reset = true, string $reset_key = '' ): void {
	?>
	<p class="submit">
		<button type="submit" class="button button-primary">ذخیره تغییرات</button>
		<?php if ( $with_reset ) : ?>
			<button type="submit" name="jluxe_reset_section" value="<?php echo esc_attr( $reset_key ); ?>" class="button jluxe-reset-section" onclick="return confirm('این بخش به حالت پیش‌فرض برگرده؟');">بازنشانی این بخش</button>
		<?php endif; ?>
	</p>
	<?php
}

/** فیلد آپلود رسانه (لوگو/فاویکون/تصویر) — یک المان مینیمال، جاوااسکریپت مشترک در theme-settings-admin.js */
function jluxe_render_media_field( string $name, int $current_id, string $empty_label ): void {
	$field_id = 'jluxe-media-' . sanitize_key( $name );
	?>
	<div class="jluxe-media-field">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $current_id ); ?>" />
		<div class="jluxe-media-preview">
			<?php if ( $current_id && wp_get_attachment_image_url( $current_id, 'medium' ) ) : ?>
				<img src="<?php echo esc_url( wp_get_attachment_image_url( $current_id, 'medium' ) ); ?>" alt="" style="max-height:60px;" />
			<?php else : ?>
				<span class="description"><?php echo esc_html( $empty_label ); ?></span>
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button jluxe-media-select" data-target="<?php echo esc_attr( $field_id ); ?>" data-preview=".jluxe-media-field .jluxe-media-preview">انتخاب تصویر</button>
			<button type="button" class="button jluxe-media-remove" data-target="<?php echo esc_attr( $field_id ); ?>" data-empty-label="<?php echo esc_attr( $empty_label ); ?>">حذف</button>
		</p>
	</div>
	<?php
}

// =====================================================================
// داشبورد
// =====================================================================
function jluxe_render_dashboard_page(): void {
	$settings = jluxe_get_theme_settings();
	$wc_active = class_exists( 'WooCommerce' );
	$ai_ready  = $settings['ai_assistant']['enabled'] && '' !== jluxe_get_ai_api_key();

	jluxe_settings_page_shell( 'داشبورد زرین', JLUXE_SETTINGS_MENU_SLUG, null, function () use ( $settings, $wc_active, $ai_ready ) {
		$statuses = array(
			array( 'وردپرس', true, get_bloginfo( 'version' ) ),
			array( 'ووکامرس', $wc_active, $wc_active ? WC()->version : 'غیرفعال' ),
			array( 'لوگو', (bool) $settings['identity']['logo_id'], $settings['identity']['logo_id'] ? 'سفارشی' : 'لوگوی سایت/فقط متن' ),
			array( 'فاویکون', (bool) $settings['identity']['favicon_id'], $settings['identity']['favicon_id'] ? 'سفارشی' : 'آیکون سایت/بدون فاویکون' ),
			array( 'فونت IRANYekan', true, 'self-hosted، فعال' ),
			array( 'دستیار هوش مصنوعی', $ai_ready, $ai_ready ? 'فعال' : ( $settings['ai_assistant']['enabled'] ? 'فعال ولی کلید API تنظیم نشده' : 'غیرفعال' ) ),
			array( 'بخش‌های صفحه اصلی', count( array_filter( $settings['homepage']['sections'], fn( $s ) => $s['enabled'] ) ) > 0, count( array_filter( $settings['homepage']['sections'], fn( $s ) => $s['enabled'] ) ) . ' بخش فعال' ),
		);
		?>
		<div class="jluxe-dashboard-grid">
			<?php foreach ( $statuses as [ $label, $ok, $detail ] ) : ?>
				<div class="jluxe-status-card <?php echo $ok ? 'is-ok' : 'is-warn'; ?>">
					<span class="dashicons dashicons-<?php echo $ok ? 'yes-alt' : 'warning'; ?>"></span>
					<div>
						<strong><?php echo esc_html( $label ); ?></strong>
						<p><?php echo esc_html( $detail ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! $settings['ai_assistant']['enabled'] ) : ?>
			<div class="notice notice-warning inline"><p>دستیار هوش مصنوعی غیرفعاله. از <a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-ai-assistant' ) ); ?>">این‌جا</a> فعالش کن.</p></div>
		<?php endif; ?>
		<?php if ( ! $settings['identity']['logo_id'] ) : ?>
			<div class="notice notice-info inline"><p>لوگوی سفارشی تنظیم نشده — از asset پیش‌فرض تم استفاده می‌شه. برای تغییر به <a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-identity' ) ); ?>">هویت سایت</a> برو (فعلاً داخل صفحه‌ی هدر).</p></div>
		<?php endif; ?>
		<?php if ( ! $wc_active ) : ?>
			<div class="notice notice-error inline"><p>ووکامرس نصب/فعال نیست — بخش زیادی از سایت (فروشگاه، سبد، تسویه‌حساب) بدون اون کار نمی‌کنه.</p></div>
		<?php endif; ?>

		<h2>دسترسی سریع</h2>
		<p>
			<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">مشاهده‌ی سایت</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-advanced' ) ); ?>">برون‌بری تنظیمات</a>
			<?php if ( $wc_active ) : ?>
				<a class="button" href="<?php echo esc_url( wc_get_cart_url() ); ?>" target="_blank">مشاهده‌ی سبد خرید</a>
				<a class="button" href="<?php echo esc_url( wc_get_checkout_url() ); ?>" target="_blank">مشاهده‌ی تسویه‌حساب</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>">تنظیمات ووکامرس</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>">روش‌های ارسال</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>">درگاه‌های پرداخت</a>
			<?php endif; ?>
		</p>
		<p class="description">روش‌های ارسال، درگاه‌های پرداخت، مالیات و صفحات فروشگاه/سبد/تسویه‌حساب از تنظیمات خودِ ووکامرس خونده می‌شن — این پنل جایگزینشون نمی‌شه، فقط ظاهرشون رو استایل می‌ده.</p>
		<?php
	} );
}

// =====================================================================
// هویت سایت + هدر (یک صفحه — لوگو/فاویکون بخشی از هدرن منطقاً)
// =====================================================================
function jluxe_render_header_page(): void {
	// طبق درخواستِ کاربر «هدر» و «منوی هدر» یک تب شدن — دو فرمِ مستقل زیرِ
	// هم، هرکدوم بخشِ خودشو ذخیره می‌کنن (جزئیات: jluxe_handle_header_combined_save).
	$status   = jluxe_handle_header_combined_save();
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'هدر', 'jluxe-header', $status, function () use ( $settings ) {
		?>
		<p class="description">لوگو/فاویکون/نام سایت به تب مجزای <a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-identity' ) ); ?>">هویت سایت</a> منتقل شدن (چون روی هدر، فوتر، صفحه‌ی ورود و favicon مرورگر یکسان اثر می‌ذارن — نه فقط هدر).</p>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<h2>رفتار هدر</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">هدر چسبان</th>
					<td><label><input type="checkbox" name="header[sticky]" value="1" <?php checked( $settings['header']['sticky'] ); ?> /> هنگام اسکرول بالای صفحه بمونه</label></td>
				</tr>
				<tr>
					<th scope="row">نمایش جستجو</th>
					<td>
						<label><input type="checkbox" name="header[show_search_desktop]" value="1" <?php checked( $settings['header']['show_search_desktop'] ?? true ); ?> /> دسکتاپ</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="header[show_search_mobile]" value="1" <?php checked( $settings['header']['show_search_mobile'] ?? true ); ?> /> موبایل</label>
					</td>
				</tr>
				<tr>
					<th scope="row">نمایش حساب کاربری</th>
					<td>
						<label><input type="checkbox" name="header[show_account_desktop]" value="1" <?php checked( $settings['header']['show_account_desktop'] ?? true ); ?> /> دسکتاپ</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="header[show_account_mobile]" value="1" <?php checked( $settings['header']['show_account_mobile'] ?? true ); ?> /> موبایل</label>
					</td>
				</tr>
				<tr>
					<th scope="row">نمایش سبد خرید</th>
					<td>
						<label><input type="checkbox" name="header[show_cart_desktop]" value="1" <?php checked( $settings['header']['show_cart_desktop'] ?? true ); ?> /> دسکتاپ</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="header[show_cart_mobile]" value="1" <?php checked( $settings['header']['show_cart_mobile'] ?? true ); ?> /> موبایل</label>
					</td>
				</tr>
			</table>
			<p class="description">این‌ها مربوط به ردیفِ بالای هدر هستن. نمایش/ترتیب آیتم‌های نوار پایین موبایل جدا و در تب <a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-mobile-contact' ) ); ?>">موبایل و تماس</a> کنترل می‌شه.</p>
			<p class="description">تغییر رنگ/بک‌گراند/سایه‌ی هدر از تب <a href="<?php echo esc_url( admin_url( 'admin.php?page=jluxe-colors-typography' ) ); ?>">رنگ و تایپوگرافی</a> کنترل می‌شه (توکن‌های مشترک سراسر سایت).</p>

			<?php jluxe_settings_submit_button( false ); ?>
		</form>

		<hr style="margin:28px 0;" />

		<h2>منوی هدر</h2>
		<?php jluxe_render_header_nav_form_fields( $settings ); ?>
		<?php
	} );
}

/**
 * محتوای فرمِ «منوی هدر» — از jluxe_render_header_nav_page() جدا شده تا هم
 * از تبِ «هدر» (بالا) و هم مستقیم قابلِ رندر باشه.
 */
function jluxe_render_header_nav_form_fields( array $settings ): void {
	$items = $settings['header_nav']['items'] ?? array();
	$icons = jluxe_nav_icon_options();
	?>
	<p class="description">مگامنوی «دسته‌بندی‌ها» همیشه اولین آیتمه و از دسته‌های واقعی ووکامرس ساخته می‌شه — این‌جا قابل ویرایش نیست. آیتم‌های زیر بعد از اون، به همون ترتیب نمایش داده می‌شن. لینک رو می‌تونی مسیر داخلی (مثلاً <code>/shop/</code>) یا آدرس کامل بدی. اگه برای یک آیتم زیرمنو تعریف کنی، خودِ آیتم هم قابل کلیکه هم روش هاور/فوکوس یک منوی کشویی باز می‌شه.</p>
	<form method="post">
		<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

		<?php for ( $i = 0; $i < 8; $i++ ) : $item = $items[ $i ] ?? array(); ?>
			<div class="jluxe-hb-banner-item">
				<strong>آیتم <?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></strong>
				<p>
					<label>عنوان<br />
						<input type="text" name="header_nav[items][<?php echo $i; ?>][label]" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" class="regular-text" />
					</label>
				</p>
				<p>
					<label>لینک<br />
						<input type="text" name="header_nav[items][<?php echo $i; ?>][url]" value="<?php echo esc_attr( $item['url'] ?? '' ); ?>" class="regular-text" dir="ltr" placeholder="/shop/" />
					</label>
				</p>
				<p>
					<label>آیکون<br />
						<select name="header_nav[items][<?php echo $i; ?>][icon]">
							<?php foreach ( $icons as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $item['icon'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label>یا کدِ SVG سفارشی (اختیاری — اگه پر بشه، به‌جای آیکونِ بالا استفاده می‌شه)<br />
						<textarea name="header_nav[items][<?php echo $i; ?>][svg]" rows="2" class="large-text code" dir="ltr" placeholder="&lt;svg viewBox=&quot;0 0 24 24&quot;&gt;...&lt;/svg&gt;"><?php echo esc_textarea( $item['svg'] ?? '' ); ?></textarea>
					</label>
				</p>
				<input type="hidden" name="header_nav[items][<?php echo $i; ?>][id]" value="<?php echo esc_attr( $item['id'] ?? '' ); ?>" />

				<p class="description">زیرمنو (اختیاری — هر زیرمنوی بدون عنوان نادیده گرفته می‌شه):</p>
				<?php for ( $c = 0; $c < 5; $c++ ) : $child = $item['children'][ $c ] ?? array(); ?>
					<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
						<input type="text" name="header_nav[items][<?php echo $i; ?>][children][<?php echo $c; ?>][label]" value="<?php echo esc_attr( $child['label'] ?? '' ); ?>" placeholder="عنوان زیرمنو <?php echo esc_attr( jluxe_fa_digits( $c + 1 ) ); ?>" class="regular-text" />
						<input type="text" name="header_nav[items][<?php echo $i; ?>][children][<?php echo $c; ?>][url]" value="<?php echo esc_attr( $child['url'] ?? '' ); ?>" placeholder="/لینک/" class="regular-text" dir="ltr" />
						<select name="header_nav[items][<?php echo $i; ?>][children][<?php echo $c; ?>][icon]">
							<?php foreach ( $icons as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $child['icon'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="header_nav[items][<?php echo $i; ?>][children][<?php echo $c; ?>][svg]" value="<?php echo esc_attr( $child['svg'] ?? '' ); ?>" placeholder="کدِ SVG سفارشی (اختیاری)" class="regular-text code" dir="ltr" />
					</div>
				<?php endfor; ?>
			</div>
		<?php endfor; ?>

		<?php jluxe_settings_submit_button( false ); ?>
	</form>
	<?php
}

// =====================================================================
// هویت سایت — لوگو/فاویکون/نام سایت (روی هدر، فوتر، صفحه‌ی ورود، و
// favicon مرورگر یکسان اثر می‌ذاره؛ برای همین جدا از «هدر» شد).
// از map موجود ('jluxe-identity' → 'identity'/jluxe_sanitize_identity که
// از قبل در jluxe_settings_sections_map() تعریف شده بود) استفاده می‌کنه —
// option یا sanitizer جدید نساخته شد.
// =====================================================================
function jluxe_render_identity_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-identity' );
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'هویت سایت', 'jluxe-identity', $status, function () use ( $settings ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">لوگو (دسکتاپ)</th>
					<td><?php jluxe_render_media_field( 'identity[logo_id]', (int) $settings['identity']['logo_id'], 'اگه چیزی آپلود نکنی، لوگوی پیش‌فرضِ خودِ سایت (Customize ← هویت سایت) استفاده می‌شه؛ اگه اونم ست نشده باشه، فقط اسمِ سایت به‌صورتِ متن نشون داده می‌شه.' ); ?></td>
				</tr>
				<tr>
					<th scope="row">لوگو (موبایل)</th>
					<td><?php jluxe_render_media_field( 'identity[mobile_logo_id]', (int) $settings['identity']['mobile_logo_id'], 'اختیاریه — از همون لوگوی دسکتاپِ بالا استفاده می‌شه.' ); ?></td>
				</tr>
				<tr>
					<th scope="row">فاویکون</th>
					<td><?php jluxe_render_media_field( 'identity[favicon_id]', (int) $settings['identity']['favicon_id'], 'اگه چیزی آپلود نکنی، آیکونِ سایتِ خودِ وردپرس (Settings ← General ← Site Icon) استفاده می‌شه.' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-site-name">نام سایت</label></th>
					<td><input type="text" id="jluxe-site-name" name="identity[site_name]" value="<?php echo esc_attr( $settings['identity']['site_name'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-short-desc">توضیح کوتاه سایت</label></th>
					<td><input type="text" id="jluxe-short-desc" name="identity[short_description]" value="<?php echo esc_attr( $settings['identity']['short_description'] ); ?>" class="large-text" /></td>
				</tr>
			</table>
			<?php jluxe_settings_submit_button( true, 'identity' ); ?>
		</form>
		<?php
	} );
}

function jluxe_render_urls_page(): void {
	$status = jluxe_handle_generic_settings_save( 'jluxe-urls' );
	$settings = jluxe_get_fresh_settings();
	$urls = $settings['urls'] ?? array();
	jluxe_settings_page_shell( 'آدرس‌های ورود و کاربر', 'jluxe-urls', $status, function () use ( $urls ) {
		$fields = array(
			'login' => array( 'آدرس صفحه ورود به سایت', '/sign', 'آدرس صفحه ورود به سایت (پیش‌فرض: /sign)' ),
			'dashboard' => array( 'آدرس صفحه داشبورد پنل کاربری', '/account', 'آدرس صفحه داشبورد پنل کاربری (پیش‌فرض: /account)' ),
			'orders' => array( 'آدرس صفحه سفارش‌های کاربر', '/account?tab=orders', 'آدرس صفحه سفارش‌های کاربر (پیش‌فرض: /account?tab=orders)' ),
			'track_order' => array( 'آدرس صفحه پیگیری سفارش', '/track-order', 'آدرس صفحه پیگیری سفارش (پیش‌فرض: /track-order)' ),
			'thankyou_orders' => array( 'آدرس مشاهده سفارش‌ها در صفحه تشکر از خرید', '/account?tab=orders', 'آدرس مشاهده سفارش‌ها در صفحه تشکر از خرید (پیش‌فرض: /account?tab=orders)' ),
		); ?>
		<form method="post"><?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?><table class="form-table" role="presentation">
		<?php foreach ( $fields as $key => $field ) : ?><tr><th scope="row"><label for="jluxe-url-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th><td><div class="custom-url-field"><input type="text" id="jluxe-url-<?php echo esc_attr( $key ); ?>" name="urls[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $urls[ $key ] ?? $field[1] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $field[1] ); ?>" dir="ltr"><p class="description"><?php echo esc_html( $field[2] ); ?></p></div></td></tr><?php endforeach; ?>
		</table><?php jluxe_settings_submit_button( true, 'urls' ); ?></form><?php
	} );
}

/**
 * یک آیتم HTML برای «نمادهای سایت» در فوتر — هر آیتم می‌تواند هر کد HTML
 * مجازِ دلخواهی داشته باشد (مثلاً enamad، ساماندهی، ترب، زرین‌پال و ...).
 */
function jluxe_render_trust_badge_item_fields( int $index, array $badge ): void {
	$name = "footer[trust_badges][{$index}]";
	$html = isset( $badge['html'] ) ? (string) $badge['html'] : '';
	$link = isset( $badge['link'] ) ? (string) $badge['link'] : '';

	// سازگاری با داده‌های قدیمی image_id/link.
	if ( '' === trim( $html ) && ! empty( $badge['image_id'] ) ) {
		$image_url = wp_get_attachment_image_url( (int) $badge['image_id'], 'full' );
		if ( $image_url ) {
			$img = '<img src="' . esc_url( $image_url ) . '" alt="نماد سایت" style="width:100%;height:auto;max-width:100%;object-fit:contain;" />';
			$html = ! empty( $badge['link'] ) ? '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( $badge['link'] ) . '">' . $img . '</a>' : $img;
		}
	}
	?>
	<div class="custom-list-item jluxe-site-badge-item" data-id="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
		<div class="jluxe-site-badge-field">
			<label>لینک اعتبارسنجی / مقصد (اختیاری)</label>
			<input type="url" name="footer[trust_badges][][link]" class="field-link regular-text" dir="ltr" placeholder="https://trustseal.enamad.ir/?id=..." value="<?php echo esc_attr( $link ); ?>" />
		</div>
		<div class="jluxe-site-badge-field">
			<label>کد HTML نماد (اختیاری)</label>
			<textarea name="footer[trust_badges][][html]" placeholder="کد HTML اینماد، ساماندهی، ترب، زرین‌پال و ..." class="field-html" rows="7" dir="ltr"><?php echo esc_textarea( $html ); ?></textarea>
		</div>
		<button type="button" class="button jluxe-site-badge-remove">حذف</button>
	</div>
	<?php
}

/**
 * یک لینکِ داخلِ یک ستونِ فوتر — repeater تودرتو (هر ستون خودش یک لیستِ
 * پویای لینک داره)؛ data-group="links" در هر سه ستون یکسانه چون
 * renumberRepeater فقط داخل نزدیک‌ترین .jluxe-repeater-list اسکوپ می‌شه
 * (نه سراسری)، پس تداخلی بین ستون‌ها پیش نمیاد.
 */
function jluxe_render_footer_link_item_fields( string $col_name, int $index, array $link, array $icons ): void {
	$name = "{$col_name}[links][{$index}]";
	?>
	<div class="jluxe-repeater-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>لینک <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $index + 1 ) ); ?></span></span>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<div class="jluxe-repeater-row" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $link['label'] ?? '' ); ?>" class="regular-text" placeholder="عنوان لینک" />
			<input type="text" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $link['url'] ?? '' ); ?>" class="regular-text" dir="ltr" placeholder="/لینک/" />
			<select name="<?php echo esc_attr( $name ); ?>[icon]">
				<?php foreach ( $icons as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $link['icon'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="<?php echo esc_attr( $name ); ?>[svg]" value="<?php echo esc_attr( $link['svg'] ?? '' ); ?>" class="regular-text code" dir="ltr" placeholder="یا کدِ SVG سفارشی (اختیاری)" />
		</div>
	</div>
	<?php
}

// =====================================================================
// فوتر
// =====================================================================
function jluxe_render_footer_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-footer' );
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'فوتر', 'jluxe-footer', $status, function () use ( $settings ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">نمایش فوتر</th>
					<td><label><input type="checkbox" name="footer[enabled]" value="1" <?php checked( $settings['footer']['enabled'] ); ?> /> فوتر در سایت نمایش داده بشه</label></td>
				</tr>
				<tr>
					<th scope="row">نوار رنگیِ تزئینی زیر فوتر</th>
					<td>
						<label><input type="checkbox" name="footer[show_gradient_strip]" value="1" <?php checked( $settings['footer']['show_gradient_strip'] ); ?> /> یک نوار گرادیانِ نازک (بر پایه‌ی رنگ اصلی/ثانویه) زیر فوتر نشون بده</label>
						<p class="description">پیش‌فرض خاموشه — این المان قبلاً در زرین وجود نداشت.</p>
					</td>
				</tr>
			</table>

			<h3>پس‌زمینه‌ی فوتر</h3>
			<?php
			$jluxe_bg          = $settings['footer']['background'];
			$jluxe_bg_presets  = array( '#1E293B', '#27272A', '#0F3D3E', '#3B0764', '#4C0519', '#334155', '#3F2A1D', '#E7DFD3' );
			// چند طیفِ گرادیانِ آماده — طبقِ درخواستِ کاربر («چنت طیفِ مناسب
			// برای فوتر به‌صورتِ گرادیانت»). هرکدوم دو رنگه (ساده‌ترین و
			// خوانا‌ترین حالتِ گرادیانِ پس‌زمینه)؛ کلیک روی هرکدوم همزمان
			// gradient_colors[0]/[1] رو پر می‌کنه و mode رو خودکار رو
			// «گرادیانت» می‌ذاره (assets/js/theme-settings-admin.js).
			$jluxe_gradient_presets = array(
				array( 'label' => 'آبی نیمه‌شب', 'colors' => array( '#0F172A', '#1E3A5F' ) ),
				array( 'label' => 'بنفش رویایی', 'colors' => array( '#2D1B4E', '#6B21A8' ) ),
				array( 'label' => 'زمردیِ تیره', 'colors' => array( '#052E16', '#0F3D2E' ) ),
				array( 'label' => 'طلاییِ گرم', 'colors' => array( '#451A03', '#92400E' ) ),
				array( 'label' => 'خاکستریِ شیک', 'colors' => array( '#18181B', '#3F3F46' ) ),
				array( 'label' => 'یاقوتیِ تیره', 'colors' => array( '#4C0519', '#881337' ) ),
			);
			$jluxe_bg_dirs     = array(
				'to top'          => 'از پایین به بالا',
				'to bottom'       => 'از بالا به پایین',
				'to left'         => 'از راست به چپ',
				'to right'        => 'از چپ به راست',
				'to top left'     => 'مورب — پایین‌راست به بالا‌چپ',
				'to top right'    => 'مورب — پایین‌چپ به بالا‌راست',
				'to bottom left'  => 'مورب — بالا‌راست به پایین‌چپ',
				'to bottom right' => 'مورب — بالا‌چپ به پایین‌راست',
			);
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">نوع پس‌زمینه</th>
					<td>
						<?php
						/*
						 * <select> عمداً به‌جای رادیو — applyConditionalFields()
						 * (assets/js/theme-settings-admin.js) روی هر
						 * [data-jluxe-toggle-id] مقدارِ .val() رو می‌خونه؛ روی
						 * یک select این دقیقاً گزینه‌ی انتخاب‌شده‌ست، ولی روی
						 * چند <input type="radio"> که همه data-jluxe-toggle-id
						 * یکسان دارن، .val() برای هرکدوم فقط valueِ ثابتِ خودشه
						 * (نه وضعیتِ checked)، پس نمایش/مخفیِ ردیف‌های
						 * پایین همیشه براساسِ آخرین رادیوی پردازش‌شده تعیین
						 * می‌شد، نه انتخابِ واقعیِ کاربر (باگِ واقعیِ گزارش‌شده:
						 * «رنگ ثابت» رو انتخاب می‌کنم ولی فیلدِ رنگ ظاهر
						 * نمی‌شه).
						 */
						?>
						<select id="jluxe-footer-bg-mode" name="footer[background][mode]" data-jluxe-toggle-id="jluxe-footer-bg">
							<option value="default" <?php selected( $jluxe_bg['mode'], 'default' ); ?>>پیش‌فرض (بدون رنگ اضافه)</option>
							<option value="solid" <?php selected( $jluxe_bg['mode'], 'solid' ); ?>>رنگ ثابت</option>
							<option value="gradient" <?php selected( $jluxe_bg['mode'], 'gradient' ); ?>>گرادیانت</option>
						</select>
					</td>
				</tr>
				<tr data-jluxe-show-if="jluxe-footer-bg:solid">
					<th scope="row">رنگ</th>
					<td>
						<input type="text" name="footer[background][solid_color]" value="<?php echo esc_attr( $jluxe_bg['solid_color'] ); ?>" class="jluxe-color-field" />
						<div class="jluxe-color-presets">
							<?php foreach ( $jluxe_bg_presets as $jluxe_preset ) : ?>
								<button type="button" class="jluxe-color-preset-swatch" data-jluxe-fill-color="<?php echo esc_attr( $jluxe_preset ); ?>" style="background:<?php echo esc_attr( $jluxe_preset ); ?>" title="<?php echo esc_attr( $jluxe_preset ); ?>"></button>
							<?php endforeach; ?>
						</div>
					</td>
				</tr>
				<tr data-jluxe-show-if="jluxe-footer-bg:gradient">
					<th scope="row">جهت گرادیانت</th>
					<td>
						<select name="footer[background][gradient_direction]">
							<?php foreach ( $jluxe_bg_dirs as $jluxe_dir_val => $jluxe_dir_label ) : ?>
								<option value="<?php echo esc_attr( $jluxe_dir_val ); ?>" <?php selected( $jluxe_bg['gradient_direction'], $jluxe_dir_val ); ?>><?php echo esc_html( $jluxe_dir_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr data-jluxe-show-if="jluxe-footer-bg:gradient">
					<th scope="row">رنگ‌های گرادیانت</th>
					<td>
						<p class="description">حداقل ۲، حداکثر ۴ رنگ — خونه‌ی خالی نادیده گرفته می‌شه.</p>
						<p class="description" style="margin-top:10px;margin-bottom:4px;">طیف‌های آماده — با یک کلیک رنگ ۱ و ۲ رو پر می‌کنه:</p>
						<div class="jluxe-gradient-presets" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
							<?php foreach ( $jluxe_gradient_presets as $jluxe_gp ) : ?>
								<button
									type="button"
									class="jluxe-gradient-preset-swatch"
									data-jluxe-fill-gradient="<?php echo esc_attr( implode( ',', $jluxe_gp['colors'] ) ); ?>"
									style="background:linear-gradient(135deg, <?php echo esc_attr( implode( ', ', $jluxe_gp['colors'] ) ); ?>);width:60px;height:34px;border-radius:8px;border:1px solid #dcdcde;cursor:pointer;"
									title="<?php echo esc_attr( $jluxe_gp['label'] ); ?>"
								></button>
							<?php endforeach; ?>
						</div>
						<?php for ( $jluxe_gc_i = 0; $jluxe_gc_i < 4; $jluxe_gc_i++ ) : ?>
							<div style="margin-bottom:8px;">
								<input type="text" name="footer[background][gradient_colors][<?php echo esc_attr( $jluxe_gc_i ); ?>]" value="<?php echo esc_attr( $jluxe_bg['gradient_colors'][ $jluxe_gc_i ] ?? '' ); ?>" class="jluxe-color-field" placeholder="رنگ <?php echo esc_html( jluxe_fa_digits( $jluxe_gc_i + 1 ) ); ?><?php echo $jluxe_gc_i < 2 ? '' : ' (اختیاری)'; ?>" />
								<div class="jluxe-color-presets">
									<?php foreach ( $jluxe_bg_presets as $jluxe_preset ) : ?>
										<button type="button" class="jluxe-color-preset-swatch" data-jluxe-fill-color="<?php echo esc_attr( $jluxe_preset ); ?>" style="background:<?php echo esc_attr( $jluxe_preset ); ?>" title="<?php echo esc_attr( $jluxe_preset ); ?>"></button>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endfor; ?>
					</td>
				</tr>
			</table>

			<h3>رنگ متن و لینک‌های فوتر</h3>
			<p class="description">خالی = همون رنگ‌های پیش‌فرضِ فعلیِ تم (بدون تغییر).</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">رنگ متن فوتر</th>
					<td>
						<input type="text" name="footer[text_color]" value="<?php echo esc_attr( $settings['footer']['text_color'] ); ?>" class="jluxe-color-field" />
						<p class="description">عنوان کارت‌های مزیت، توضیح کوتاه، ساعات پشتیبانی، عنوان ستون‌های لینک، کپی‌رایت.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">رنگ لینک‌ها</th>
					<td><input type="text" name="footer[link_color]" value="<?php echo esc_attr( $settings['footer']['link_color'] ); ?>" class="jluxe-color-field" /></td>
				</tr>
				<tr>
					<th scope="row">رنگ لینک‌ها (هاور/انتخاب)</th>
					<td><input type="text" name="footer[link_hover_color]" value="<?php echo esc_attr( $settings['footer']['link_hover_color'] ); ?>" class="jluxe-color-field" /></td>
				</tr>
			</table>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="jluxe-support-hours">ساعات پشتیبانی</label></th>
					<td><input type="text" id="jluxe-support-hours" name="footer[support_hours]" value="<?php echo esc_attr( $settings['footer']['support_hours'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-support-text">متن پشتیبانی متنی</label></th>
					<td>
						<input type="text" id="jluxe-support-text" name="footer[support_text]" value="<?php echo esc_attr( $settings['footer']['support_text'] ?? '' ); ?>" class="large-text" placeholder="مثلاً پشتیبانی متنی ۲۴ ساعته: اینستاگرام، تلگرام، واتس‌اپ، روبیکا، بله" />
						<p class="description">این متن در خط «پشتیبانی متنی» فوتر نمایش داده می‌شود.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-copyright">متن کپی‌رایت</label></th>
					<td><input type="text" id="jluxe-copyright" name="footer[copyright]" value="<?php echo esc_attr( $settings['footer']['copyright'] ); ?>" class="regular-text" placeholder="خالی = پیش‌فرض (© سال جاری + نام سایت)" /></td>
				</tr>
			</table>

			<h3>کارت‌های مزیت (نشان‌های اعتماد فوتر)</h3>
			<p class="description">
				<label for="jluxe-feature-cards-icon-color">رنگِ اختصاصیِ آیکون‌ها:</label>
				<input type="text" id="jluxe-feature-cards-icon-color" name="footer[feature_cards_icon_color]" value="<?php echo esc_attr( $settings['footer']['feature_cards_icon_color'] ); ?>" class="jluxe-color-field" />
				— خالی یعنی از رنگِ اصلیِ سایت (Primary) پیروی می‌کنه؛ اگه اینجا رنگی انتخاب کنی، فقط همین ۴ آیکون بدونِ توجه به پالتِ کلی همون رنگ می‌مونن.
			</p>
			<?php
			// آیکونِ هر کارتِ مزیت قبلاً کلاً از کد میومد (غیرقابل‌انتخاب) —
			// حالا از همون مجموعه‌ی مشترکِ منو + یک کلیدِ اضافیِ مخصوصِ فوتر
			// (badge-percent، برای سازگاری با دیتای پیش‌فرضِ «بهترین قیمت»)
			// قابل‌انتخابه.
			$jluxe_feature_icons = jluxe_nav_icon_options();
			unset( $jluxe_feature_icons[''] );
			$jluxe_feature_icons['badge-percent'] = 'برچسبِ درصد';
			?>
			<table class="form-table" role="presentation">
				<?php foreach ( $settings['footer']['feature_cards'] as $i => $card ) : ?>
					<tr>
						<th scope="row">مزیت <?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></th>
						<td>
							<label><input type="checkbox" name="footer[feature_cards][<?php echo esc_attr( $i ); ?>][enabled]" value="1" <?php checked( $card['enabled'] ); ?> /> فعال</label>
							<br /><br />
							<input type="text" name="footer[feature_cards][<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $card['title'] ); ?>" class="regular-text" placeholder="عنوان" />
							<input type="text" name="footer[feature_cards][<?php echo esc_attr( $i ); ?>][subtitle]" value="<?php echo esc_attr( $card['subtitle'] ); ?>" class="regular-text" placeholder="توضیح کوتاه" />
							<select name="footer[feature_cards][<?php echo esc_attr( $i ); ?>][icon]">
								<?php foreach ( $jluxe_feature_icons as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $card['icon'], $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="footer[feature_cards][<?php echo esc_attr( $i ); ?>][svg]" value="<?php echo esc_attr( $card['svg'] ?? '' ); ?>" class="regular-text code" dir="ltr" placeholder="یا کدِ SVG سفارشی (اختیاری)" />
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="description">
				<label for="jluxe-feature-cards-mobile-columns">تعداد ستون در موبایل:</label>
				<select id="jluxe-feature-cards-mobile-columns" name="footer[feature_cards_mobile_columns]">
					<option value="1" <?php selected( $settings['footer']['feature_cards_mobile_columns'], 1 ); ?>>۱ ستونه (هر مزیت یک ردیف کامل)</option>
					<option value="2" <?php selected( $settings['footer']['feature_cards_mobile_columns'], 2 ); ?>>۲ ستونه</option>
				</select>
				— در دسکتاپ همیشه هر ۴ مزیت در یک ردیف نمایش داده می‌شن.
			</p>

			<h3>ستون‌های لینک فوتر</h3>
			<p class="description">حداکثر ۳ ستون؛ عنوان خالی یعنی اون ستون نمایش داده نمی‌شه. داخل هر ستون هر تعداد لینک که بخواید اضافه/حذف کنید.</p>
			<?php
			$jluxe_link_columns = $settings['footer']['link_columns'] ?? array();
			$jluxe_footer_icons = jluxe_nav_icon_options();
			for ( $jluxe_col_i = 0; $jluxe_col_i < 3; $jluxe_col_i++ ) :
				$jluxe_col      = $jluxe_link_columns[ $jluxe_col_i ] ?? array();
				$jluxe_col_name = "footer[link_columns][{$jluxe_col_i}]";
				?>
				<div style="border:1px solid #dcdcde;border-radius:6px;padding:12px;margin-bottom:12px;">
					<p>
						<label>عنوان ستون <?php echo esc_html( jluxe_fa_digits( $jluxe_col_i + 1 ) ); ?><br />
							<input type="text" name="<?php echo esc_attr( $jluxe_col_name ); ?>[title]" value="<?php echo esc_attr( $jluxe_col['title'] ?? '' ); ?>" class="regular-text" placeholder="مثلاً راهنما" />
						</label>
					</p>
					<div class="jluxe-repeater" data-max="8">
						<div class="jluxe-repeater-list" data-group="links">
							<?php foreach ( $jluxe_col['links'] ?? array() as $jluxe_link_i => $jluxe_link ) : ?>
								<?php jluxe_render_footer_link_item_fields( $jluxe_col_name, $jluxe_link_i, $jluxe_link, $jluxe_footer_icons ); ?>
							<?php endforeach; ?>
						</div>
						<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_footer_link_item_fields( $jluxe_col_name, 0, array(), $jluxe_footer_icons ); ?></script>
						<button type="button" class="button jluxe-repeater-add">+ افزودن لینک</button>
					</div>
				</div>
			<?php endfor; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">عنوانِ بخشِ نمادها</th>
					<td>
						<input type="text" name="footer[trust_badges_title]" class="regular-text" value="<?php echo esc_attr( $settings['footer']['trust_badges_title'] ?? 'نمادهای سایت' ); ?>" />
						<p class="description">متنی که بالای نمادهای سایت (اینماد، ساماندهی و ...) در فوتر نشون داده می‌شه.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">نمادهای سایت (HTML)</th>
					<td>
						<div class="jluxe-site-badges-editor" data-jluxe-site-badges>
							<div class="jluxe-site-badges-items">
								<?php
								$jluxe_badge_rows = ! empty( $settings['footer']['trust_badges'] ) ? $settings['footer']['trust_badges'] : array( array() );
								foreach ( $jluxe_badge_rows as $i => $badge ) {
									jluxe_render_trust_badge_item_fields( (int) $i, is_array( $badge ) ? $badge : array() );
								}
								?>
							</div>
							<div class="jluxe-site-badges-actions">
								<button type="button" class="button button-primary jluxe-site-badge-add">افزودن آیتم جدید</button>
							</div>
							<template class="jluxe-site-badge-template"><?php jluxe_render_trust_badge_item_fields( 0, array() ); ?></template>
						</div>
						<textarea name="footer[trust_badges_json]" class="jluxe-site-badges-json" hidden><?php echo esc_textarea( wp_json_encode( array_values( array_map( static function ( $badge ) { return array( 'html' => isset( $badge['html'] ) ? (string) $badge['html'] : '', 'link' => isset( $badge['link'] ) ? (string) $badge['link'] : '' ); }, $settings['footer']['trust_badges'] ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
						<p class="description">لینک اعتبارسنجی را جداگانه وارد کن یا کد HTML رسمی نماد را دقیقاً از سرویس مربوطه قرار بده. برای اینماد، کد رسمی شامل <code>&lt;script src="https://trustseal.enamad.ir/..."&gt;</code> را وارد کن.</p>
					</td>
				</tr>
			</table>
			<?php jluxe_settings_submit_button( true, 'footer' ); ?>
		</form>
		<?php
	} );
}

// =====================================================================
// رنگ و تایپوگرافی — طبق درخواستِ کاربر («رنگ و ظاهر + تایپوگرافی رو یکی
// کن») حالا دو فرمِ مستقل (رنگ‌ها، تایپوگرافی — هرکدوم توی آکاردئونِ خودش)
// روی یک صفحه‌ان؛ منطقِ ذخیره‌سازیِ هرکدوم دست‌نخورده مونده. صفحاتِ قدیمی
// (jluxe-colors/jluxe-typography) طبقِ همون الگوی jluxe-header-nav فقط
// ریدایرکت می‌کنن.
// =====================================================================
function jluxe_render_colors_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-colors-typography' ) );
	exit;
}

function jluxe_render_typography_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-colors-typography' ) );
	exit;
}

function jluxe_render_colors_typography_page(): void {
	$status   = jluxe_handle_colors_typography_combined_save();
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'رنگ و تایپوگرافی', 'jluxe-colors-typography', $status, function () use ( $settings ) {
		$color_labels = array(
			'primary'    => array( 'رنگ اصلی', 'دکمه‌های اصلی، افزودن به سبد، ثبت سفارش.' ),
			'secondary'  => array( 'رنگ ثانویه', 'لینک‌ها و عناصر کم‌اهمیت‌تر.' ),
			'background' => array( 'پس‌زمینه', 'پس‌زمینه‌ی کلی صفحات.' ),
			'success'    => array( 'رنگ موفقیت', 'وضعیت موجود/تکمیل‌شده.' ),
			'accent'     => array( 'رنگ تأکیدی', 'بج‌های ویژه/برچسب.' ),
		);
		$presets = jluxe_get_design_presets();
		?>

		<details class="jluxe-hb-section-details" open>
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-art"></span>
				<strong class="jluxe-hb-type-label">پریست‌های آماده</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<p class="description">هرکدوم رو بزنی، رنگ‌ها و تایپوگرافی یکجا با مقادیرِ همون پریست جایگزین می‌شن — دقیقاً همون دو فرمِ پایین رو پر می‌کنه، پس بعدش هم می‌تونی دستی ریزه‌کاری کنی. هیچ بخشِ دیگه‌ای (لوگو، متن، تصاویر) دست‌نمی‌خوره.</p>
				<div class="jluxe-preset-grid">
					<?php foreach ( $presets as $id => $preset ) : ?>
						<form method="post" class="jluxe-preset-card">
							<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
							<input type="hidden" name="jluxe_apply_preset" value="1" />
							<input type="hidden" name="jluxe_preset_id" value="<?php echo esc_attr( $id ); ?>" />
							<div class="jluxe-preset-swatches" style="background:<?php echo esc_attr( $preset['colors']['background'] ); ?>">
								<?php foreach ( array( 'primary', 'secondary', 'accent', 'success' ) as $key ) : ?>
									<span class="jluxe-preset-dot" style="background:<?php echo esc_attr( $preset['colors'][ $key ] ); ?>"></span>
								<?php endforeach; ?>
							</div>
							<strong class="jluxe-preset-label"><?php echo esc_html( $preset['label'] ); ?></strong>
							<p class="description jluxe-preset-desc"><?php echo esc_html( $preset['description'] ); ?></p>
							<button type="submit" class="button button-secondary">اعمال این پریست</button>
						</form>
					<?php endforeach; ?>
				</div>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-admin-customizer"></span>
				<strong class="jluxe-hb-type-label">رنگ و ظاهر</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">این ۵ رنگ کل سیستم رنگی سایت رو کنترل می‌کنن؛ حالت‌های hover/active و رنگ متن روی هرکدوم خودکار (بر اساس محاسبه‌ی واقعی کنتراست WCAG) تولید می‌شن. تغییرات روی هدر، فوتر، دکمه‌ها، صفحه‌ی محصول، سبد و تسویه‌حساب یکجا اعمال می‌شه (توکن مشترک).</p>
					<table class="form-table" role="presentation">
						<?php foreach ( $color_labels as $key => [ $label, $desc ] ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<input type="text" name="colors[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings['colors'][ $key ] ); ?>" class="jluxe-color-field" />
									<p class="description"><?php echo esc_html( $desc ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php jluxe_settings_submit_button( true, 'colors' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-editor-textcolor"></span>
				<strong class="jluxe-hb-type-label">تایپوگرافی</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">فونت خودِ سایت (IRANYekan، self-hosted در <code>src/assets/fonts</code>) قابل تغییر نیست — این تنظیمات فقط اندازه/وزن رو کنترل می‌کنن.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-base-size">سایز پایه‌ی متن (px)</label></th>
							<td><input type="number" id="jluxe-base-size" name="typography[base_size]" value="<?php echo esc_attr( $settings['typography']['base_size'] ); ?>" min="12" max="22" class="small-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-heading-weight">وزن عنوان‌ها</label></th>
							<td>
								<select id="jluxe-heading-weight" name="typography[heading_weight]">
									<?php foreach ( array( 400, 500, 600, 700, 800, 900 ) as $w ) : ?>
										<option value="<?php echo esc_attr( $w ); ?>" <?php selected( (int) $settings['typography']['heading_weight'], $w ); ?>><?php echo esc_html( $w ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-line-height">line-height متن بدنه</label></th>
							<td><input type="number" step="0.05" id="jluxe-line-height" name="typography[line_height]" value="<?php echo esc_attr( $settings['typography']['line_height'] ); ?>" min="1.2" max="2.2" class="small-text" /></td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( true, 'typography' ); ?>
				</form>
			</div>
		</details>
		<?php
	} );
}

// =====================================================================
// محصول (کارت محصول + صفحه محصول) — طبق درخواستِ کاربر یکی شدن؛ منطقِ
// ذخیره‌سازیِ هرکدوم دست‌نخورده مونده، فقط الان دو آکاردئونِ زیرِهم روی یک
// صفحه‌ان. صفحاتِ قدیمی (jluxe-product-card/jluxe-product-page) فقط
// ریدایرکت می‌کنن.
// =====================================================================
function jluxe_render_product_card_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-product' ) );
	exit;
}

function jluxe_render_product_page_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-product' ) );
	exit;
}

function jluxe_render_product_page(): void {
	$status   = jluxe_handle_product_combined_save();
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'محصول', 'jluxe-product', $status, function () use ( $settings ) {
		$pc = $settings['product_card'];
		$pp = $settings['product_page'];
		?>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-id-alt"></span>
				<strong class="jluxe-hb-type-label">کارت محصول</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">نسبت تصویر</th>
							<td>
								<label><input type="radio" name="product_card[image_ratio]" value="square" <?php checked( $pc['image_ratio'], 'square' ); ?> /> مربع (۱:۱)</label>
								&nbsp;&nbsp;
								<label><input type="radio" name="product_card[image_ratio]" value="classic" <?php checked( $pc['image_ratio'], 'classic' ); ?> /> عمودی (۳:۴)</label>
								&nbsp;&nbsp;
								<label><input type="radio" name="product_card[image_ratio]" value="portrait" <?php checked( $pc['image_ratio'], 'portrait' ); ?> /> عمودی (۴:۵)</label>
							</td>
						</tr>
						<tr>
							<th scope="row">گردی گوشه‌ها</th>
							<td>
								<select name="product_card[radius]">
									<option value="sm" <?php selected( $pc['radius'], 'sm' ); ?>>کم</option>
									<option value="md" <?php selected( $pc['radius'], 'md' ); ?>>متوسط</option>
									<option value="lg" <?php selected( $pc['radius'], 'lg' ); ?>>زیاد</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">گوشه‌ی خودِ عکس</th>
							<td>
								<label><input type="radio" name="product_card[image_corners]" value="rounded" <?php checked( $pc['image_corners'] ?? 'rounded', 'rounded' ); ?> /> گرد (طبقِ «گردی گوشه‌ها» بالا)</label>
								&nbsp;&nbsp;
								<label><input type="radio" name="product_card[image_corners]" value="sharp" <?php checked( $pc['image_corners'] ?? 'rounded', 'sharp' ); ?> /> تیز</label>
								<p class="description">مستقل از گردیِ کلیِ کارت — فقط گوشه‌های خودِ عکس رو کنترل می‌کنه.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">نمایش امتیاز</th>
							<td><label><input type="checkbox" name="product_card[show_rating]" value="1" <?php checked( $pc['show_rating'] ); ?> /></label></td>
						</tr>
						<tr>
							<th scope="row">نمایش بج تخفیف</th>
							<td><label><input type="checkbox" name="product_card[show_sale_badge]" value="1" <?php checked( $pc['show_sale_badge'] ); ?> /></label></td>
						</tr>
						<tr>
							<th scope="row">نمایش بج ناموجود</th>
							<td><label><input type="checkbox" name="product_card[show_stock_badge]" value="1" <?php checked( $pc['show_stock_badge'] ); ?> /></label></td>
						</tr>
						<tr>
							<th scope="row">رنگ قیمت</th>
							<td>
								<input type="hidden" name="product_card[price_color]" id="jluxe-price-color-value" value="<?php echo esc_attr( $pc['price_color'] ); ?>" />
								<input type="color" id="jluxe-price-color-picker" value="<?php echo esc_attr( $pc['price_color'] ?: '#18181b' ); ?>" />
								<label><input type="checkbox" id="jluxe-price-color-default" <?php checked( '' === $pc['price_color'] ); ?> /> استفاده از رنگ پیش‌فرض متن</label>
								<p class="description">اگه «رنگ پیش‌فرض» تیک بخوره، قیمت با رنگ استاندارد متن سایت نمایش داده می‌شه؛ در غیر این صورت همیشه با رنگ انتخاب‌شده.</p>
								<script>
								(function(){
									var hidden = document.getElementById('jluxe-price-color-value');
									var picker = document.getElementById('jluxe-price-color-picker');
									var cb = document.getElementById('jluxe-price-color-default');
									function sync(){
										picker.disabled = cb.checked;
										hidden.value = cb.checked ? '' : picker.value;
									}
									cb.addEventListener('change', sync);
									picker.addEventListener('input', sync);
									sync();
								})();
								</script>
							</td>
						</tr>
						<tr>
							<th scope="row">رنگ نقطه‌ی «موجود است»</th>
							<td>
								<input type="text" name="product_card[in_stock_color]" value="<?php echo esc_attr( $pc['in_stock_color'] ); ?>" class="jluxe-color-field" />
								<p class="description">نقطه‌ی کوچکِ سبز روی عکس، وقتی محصول تخفیف یا بج ناموجود نداره.</p>
							</td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( true, 'product_card' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-products"></span>
				<strong class="jluxe-hb-type-label">صفحه محصول</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">گالری، قیمت، انتخاب تنوع، افزودن به سبد و توضیحات همیشه واقعی و متصل به ووکامرسن (<code>woocommerce/content-single-product.php</code>) — این‌جا فقط چند تنظیم نمایشی کنترل می‌شه.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">چیدمانِ صفحه‌ی محصول</th>
							<td>
								<label><input type="radio" name="product_page[layout]" value="default" <?php checked( $pp['layout'] ?? 'default', 'default' ); ?> /> پیش‌فرض (سه‌ستونه، گالریِ بزرگ)</label>
								<br />
								<label><input type="radio" name="product_page[layout]" value="classic" <?php checked( $pp['layout'] ?? 'default', 'classic' ); ?> /> کلاسیک (تصویر با ذره‌بین + جعبه‌ی خریدِ کناری)</label>
								<p class="description">هردو چیدمان کاملاً به سبد/قیمت/تنوعِ واقعیِ ووکامرس وصلن — فقط ظاهر فرق می‌کنه.</p>
							</td>
						</tr>
					</table>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">نمایش محصولات مرتبط</th>
							<td><label><input type="checkbox" name="product_page[show_related]" value="1" <?php checked( $pp['show_related'] ); ?> /></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-related-count">تعداد محصولات مرتبط</label></th>
							<td><input type="number" id="jluxe-related-count" name="product_page[related_count]" value="<?php echo esc_attr( $pp['related_count'] ); ?>" min="2" max="8" class="small-text" /></td>
						</tr>
					</table>

					<h3>رنگ‌های صفحه‌ی محصول</h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">رنگ بج/درصد تخفیف</th>
							<td><input type="text" name="product_page[discount_color]" value="<?php echo esc_attr( $pp['discount_color'] ); ?>" class="jluxe-color-field" /></td>
						</tr>
						<tr>
							<th scope="row">رنگ خط «سود شما از این خرید» و موجودی</th>
							<td><input type="text" name="product_page[savings_color]" value="<?php echo esc_attr( $pp['savings_color'] ); ?>" class="jluxe-color-field" /></td>
						</tr>
						<tr>
							<th scope="row">رنگ ستاره‌ی امتیاز</th>
							<td><input type="text" name="product_page[star_color]" value="<?php echo esc_attr( $pp['star_color'] ); ?>" class="jluxe-color-field" /></td>
						</tr>
					</table>

					<h3>ردیف‌های اعتماد (زیرِ جعبه‌ی خرید)</h3>
					<p class="description">عنوان/توضیح هر ردیف قابل‌ویرایش و هرکدوم جدا قابل‌فعال/غیرفعال‌سازیه؛ آیکونشون ثابته.</p>
					<table class="form-table" role="presentation">
						<?php $jluxe_trust_items = $pp['trust_items'] ?? array(); ?>
						<?php foreach ( $jluxe_trust_items as $jluxe_ti_i => $jluxe_ti ) : ?>
							<tr>
								<th scope="row">ردیف <?php echo esc_html( jluxe_fa_digits( $jluxe_ti_i + 1 ) ); ?></th>
								<td>
									<label><input type="checkbox" name="product_page[trust_items][<?php echo esc_attr( $jluxe_ti_i ); ?>][enabled]" value="1" <?php checked( $jluxe_ti['enabled'] ?? true ); ?> /> فعال</label>
									<br /><br />
									<input type="text" name="product_page[trust_items][<?php echo esc_attr( $jluxe_ti_i ); ?>][title]" value="<?php echo esc_attr( $jluxe_ti['title'] ?? '' ); ?>" class="regular-text" placeholder="عنوان" />
									<input type="text" name="product_page[trust_items][<?php echo esc_attr( $jluxe_ti_i ); ?>][subtitle]" value="<?php echo esc_attr( $jluxe_ti['subtitle'] ?? '' ); ?>" class="regular-text" placeholder="توضیح کوتاه" />
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php jluxe_settings_submit_button( true, 'product_page' ); ?>
				</form>
			</div>
		</details>
		<?php
	} );
}

// =====================================================================
// فروشگاه و دسته‌بندی
// =====================================================================
function jluxe_render_shop_page(): void {
	$icon_status = jluxe_handle_category_icons_save();
	$status   = jluxe_handle_generic_settings_save( 'jluxe-shop' );
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'فروشگاه و دسته‌بندی', 'jluxe-shop', $icon_status ?: $status, function () use ( $settings ) {
		$shop = $settings['shop'];
		?>
		<h2>آیکون دسته‌بندی‌ها</h2>
		<p class="description">برای تمام دسته‌بندی‌های واقعی ووکامرس آیکون اختصاصی تعیین کنید. SVG سفارشی بر آیکون آماده اولویت دارد و در مگامنو دسکتاپ و دراور موبایل نمایش داده می‌شود.</p>
		<?php $jluxe_cat_terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) ); ?>
		<?php if ( ! is_wp_error( $jluxe_cat_terms ) ) : ?>
		<form method="post">
		<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
		<table class="widefat striped"><thead><tr><th>دسته‌بندی</th><th>آیکون آماده</th><th>SVG سفارشی</th></tr></thead><tbody><?php foreach ( $jluxe_cat_terms as $term ) : $ci=get_term_meta($term->term_id,'_jluxe_category_icon',true); $csvg=get_term_meta($term->term_id,'_jluxe_category_icon_svg',true); ?><tr><td><strong><?php echo esc_html($term->name); ?></strong><br><small><?php echo esc_html($term->count); ?> محصول</small></td><td><select name="category_icons[<?php echo esc_attr($term->term_id); ?>][icon]"><?php foreach(jluxe_nav_icon_options() as $val=>$label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected($ci,$val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td><td><textarea name="category_icons[<?php echo esc_attr($term->term_id); ?>][svg]" rows="2" class="large-text code" dir="ltr" placeholder="&lt;svg ...&gt;...&lt;/svg&gt;"><?php echo esc_textarea($csvg); ?></textarea></td></tr><?php endforeach; ?></tbody></table><p class="submit"><button type="submit" class="button button-primary">ذخیره آیکون‌های دسته‌بندی</button></p></form>
		<?php endif; ?>

		<form method="post">
		<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<p class="description">این تنظیمات مستقیماً روی صفحه‌ی فروشگاه و آرشیو دسته‌بندی (<code>archive-product.php</code> → قلاب‌های واقعیِ <code>loop_shop_per_page</code> / <code>loop_shop_columns</code> خودِ ووکامرس) اثر می‌ذارن؛ کوئری موازی یا محصول جعلی این‌جا نیست.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="jluxe-shop-per-page">تعداد محصول در هر صفحه</label></th>
					<td><input type="number" id="jluxe-shop-per-page" name="shop[products_per_page]" value="<?php echo esc_attr( $shop['products_per_page'] ); ?>" min="4" max="48" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-cols-desktop">تعداد ستون — دسکتاپ</label></th>
					<td><input type="number" id="jluxe-cols-desktop" name="shop[columns_desktop]" value="<?php echo esc_attr( $shop['columns_desktop'] ); ?>" min="2" max="6" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-cols-tablet">تعداد ستون — تبلت</label></th>
					<td><input type="number" id="jluxe-cols-tablet" name="shop[columns_tablet]" value="<?php echo esc_attr( $shop['columns_tablet'] ); ?>" min="2" max="4" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-cols-mobile">تعداد ستون — موبایل</label></th>
					<td><input type="number" id="jluxe-cols-mobile" name="shop[columns_mobile]" value="<?php echo esc_attr( $shop['columns_mobile'] ); ?>" min="1" max="3" class="small-text" /></td>
				</tr>
			</table>
			<p class="description">سایدبار/فیلتر/مرتب‌سازی فعلاً در قالب آرشیوِ فعلی (<code>woocommerce_content()</code> پیش‌فرض) وجود ندارن — اضافه‌کردنشون نیاز به override تمپلیت جداگانه داره؛ برای جلوگیری از fake کنترل، این‌جا نمایش داده نمی‌شن.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">تبِ «دانلودها» در حساب کاربری</th>
					<td>
						<label>
							<input type="checkbox" name="shop[show_account_downloads_tab]" value="1" <?php checked( $shop['show_account_downloads_tab'] ); ?> />
							نمایش تبِ «دانلودها» توی سایدبارِ حساب کاربری
						</label>
						<p class="description">اگر محصولِ دانلودی نمی‌فروشید، خاموش‌کردنِ این گزینه یک تبِ همیشه‌خالی رو از سایدبار حذف می‌کنه.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">حرکتِ خودکارِ کاروسل‌ها</th>
					<td>
						<label>
							<input type="checkbox" name="shop[auto_scroll_carousels]" value="1" <?php checked( $shop['auto_scroll_carousels'] ); ?> />
							کاروسل‌های ردیفیِ محصول (جدیدترین، پیشنهادی، دسته‌بندی‌ها...) خودشون کند و ملایم اسکرول کنن
						</label>
						<p class="description">وقتی خاموشه، هر کاروسل فقط با کشیدن/کلیکِ فلش حرکت می‌کنه (رفتارِ پیش‌فرض). بخشِ «فروش ویژه» جدا از این تنظیمه و همیشه خودکار می‌مونه.</p>
					</td>
				</tr>
			</table>

			<h2>سبد خرید کشویی (مینی‌کارت)</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فیلد کد تخفیف</th>
					<td>
						<label>
							<input type="checkbox" name="shop[mini_cart_show_coupon]" value="1" <?php checked( $shop['mini_cart_show_coupon'] ); ?> />
							نمایش فیلد «کد تخفیف» در سبد خرید کشویی
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">نوار ارسال رایگان</th>
					<td>
						<label>
							<input type="checkbox" name="shop[mini_cart_show_free_shipping]" value="1" <?php checked( $shop['mini_cart_show_free_shipping'] ); ?> />
							نمایش نوار پیشرفتِ ارسال رایگان در سبد خرید کشویی
						</label>
						<p class="description">این نوار حتی وقتی این گزینه فعاله، فقط زمانی واقعاً نمایش داده می‌شه که یک روشِ «ارسال رایگان» با شرطِ حداقل مبلغ سفارش، روی یکی از مناطقِ حمل‌ونقل (ووکامرس ← تنظیمات ← حمل‌ونقل) تنظیم شده باشه — آستانه‌ی مبلغ همیشه از همون‌جا خونده می‌شه، نه یک عددِ جدا.</p>
					</td>
				</tr>
			</table>
			<?php jluxe_settings_submit_button( true, 'shop' ); ?>
		</form>
		<?php
	} );
}

// =====================================================================
// سبد و تسویه‌حساب — صفحه‌ی چک‌اوت/تسویه‌حساب فقط توضیح وضعیت داره، بدون
// کنترل جعلی (چون طبق قانون صریح پروژه اجازه‌ی دست‌زدن به منطق واقعیِ
// WC()->cart/سشن/درگاه نداریم؛ ظاهرشون کاملاً از توکن‌های رنگ سراسری میاد).
// سبدِ کشویی (مینی‌کارت) استثناست — دو سوییچِ واقعی (فیلدِ کوپن، نوارِ
// ارسالِ رایگان) بالاتر در همین صفحه (jluxe-shop) اضافه شدن، چون این‌ها
// صرفاً نمایش/عدم‌نمایشِ یک بخشِ UI هستن، نه دست‌کاریِ منطقِ محاسباتیِ WC.
// =====================================================================
// موبایل و تماس (موبایل + تماس و شبکه‌های اجتماعی) — طبق درخواستِ کاربر
// یکی شدن؛ منطقِ ذخیره‌سازیِ هرکدوم دست‌نخورده مونده (جز رفعِ یک باگِ واقعی
// در ذخیره‌ی social — توضیح در jluxe_handle_mobile_contact_combined_save،
// theme-settings-admin.php). صفحاتِ قدیمی (jluxe-mobile/jluxe-contact) فقط
// ریدایرکت می‌کنن.
// =====================================================================
function jluxe_render_mobile_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-mobile-contact' ) );
	exit;
}

function jluxe_render_contact_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-mobile-contact' ) );
	exit;
}

function jluxe_render_mobile_contact_page(): void {
	$status   = jluxe_handle_mobile_contact_combined_save();
	$settings = jluxe_get_fresh_settings();
	$icons    = jluxe_nav_icon_options();

	// نقش/لینکِ واقعیِ هر تب در MobileNav.tsx ثابته (بعضی‌ها رفتار خاص
	// دارن، مثلاً «سبد»/«دسته‌بندی‌ها» دراور کشویی باز می‌کنن نه ناوبریِ
	// معمولی) — پس id قابل تغییر نیست، فقط برای نمایشِ اسمِ واقعیِ هر ردیف
	// این‌جا استفاده می‌شه.
	$id_names = array(
		'track'   => 'پیگیری سفارش (لینک به صفحه‌ی پیگیری سریع)',
		'shop'    => 'دسته‌بندی‌ها (باز کردن دراورِ دسته‌بندی)',
		'home'    => 'خانه',
		'account' => 'حساب کاربری (ورود/حساب بسته به وضعیتِ لاگین)',
		'cart'    => 'سبد خرید (باز کردن دراورِ سبد)',
	);

	jluxe_settings_page_shell( 'موبایل و تماس', 'jluxe-mobile-contact', $status, function () use ( $settings, $icons, $id_names ) {
		$social_labels = array(
			'instagram' => 'اینستاگرام',
			'telegram'  => 'تلگرام',
			'whatsapp'  => 'واتس‌اپ',
			'rubika'    => 'روبیکا',
			'bale'      => 'بله',
		);
		?>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-menu-alt3"></span>
				<strong class="jluxe-hb-type-label">موبایل</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">نوار پایین موبایل (<code>MobileNav.tsx</code>) — همیشه همین ۵ تبِ واقعی‌ان (چون هرکدوم رفتار واقعیِ خودشون رو دارن)، ولی می‌تونی با درگ‌کردنِ دستگیره ترتیب‌شون رو عوض کنی، برچسب/آیکون هرکدوم رو تغییر بدی، یا هرکدوم رو غیرفعال کنی.</p>
					<div class="jluxe-repeater" data-sortable="true">
						<div class="jluxe-repeater-list" data-group="nav_items">
							<?php foreach ( $settings['mobile']['nav_items'] as $i => $item ) : ?>
								<div class="jluxe-repeater-item">
									<div class="jluxe-repeater-item-head">
										<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
										<span><?php echo esc_html( $id_names[ $item['id'] ] ?? $item['id'] ); ?></span>
									</div>
									<input type="hidden" name="mobile[nav_items][<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $item['id'] ); ?>" />
									<div class="jluxe-repeater-row" style="display:flex;gap:8px;align-items:center;">
										<label>برچسب<br />
											<input type="text" name="mobile[nav_items][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" class="regular-text" />
										</label>
										<label>آیکون<br />
											<select name="mobile[nav_items][<?php echo esc_attr( $i ); ?>][icon]">
												<?php foreach ( $icons as $val => $label ) : ?>
													<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $item['icon'], $val ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<label><input type="checkbox" name="mobile[nav_items][<?php echo esc_attr( $i ); ?>][enabled]" value="1" <?php checked( $item['enabled'] ); ?> /> نمایش داده بشه</label>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<h3>استایل نوار موبایل</h3>
					<p class="description">ظاهر نوار پایین موبایل را بدون دستکاری کد تنظیم کن. رنگ آیکون‌ها، حالت فعال، سایه، گردی، ارتفاع، شفافیت و افکت ورود از همین بخش کنترل می‌شود.</p>
					<?php $mstyle = $settings['mobile']['nav_style'] ?? array(); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">رنگ‌ها</th>
							<td>
								<label>پس‌زمینه <input type="color" name="mobile[nav_style][background]" value="<?php echo esc_attr( $mstyle['background'] ?? '#ffffff' ); ?>"></label>
								<label style="margin-inline-start:18px;">رنگ فعال <input type="color" name="mobile[nav_style][active_color]" value="<?php echo esc_attr( $mstyle['active_color'] ?? '#087A68' ); ?>"></label>
								<label style="margin-inline-start:18px;">رنگ آیکون <input type="color" name="mobile[nav_style][icon_color]" value="<?php echo esc_attr( $mstyle['icon_color'] ?? '#667085' ); ?>"></label>
								<label style="margin-inline-start:18px;">پس‌زمینه آیکون <input type="color" name="mobile[nav_style][icon_bg]" value="<?php echo esc_attr( $mstyle['icon_bg'] ?? '#f5f7f8' ); ?>"></label>
								<label style="margin-inline-start:18px;">پس‌زمینه فعال <input type="color" name="mobile[nav_style][active_bg]" value="<?php echo esc_attr( $mstyle['active_bg'] ?? '#e8f4f1' ); ?>"></label>
							</td>
						</tr>
						<tr>
							<th scope="row">سایه</th>
							<td><select name="mobile[nav_style][shadow]">
								<?php foreach ( array( 'none'=>'بدون سایه', 'soft'=>'نرم', 'medium'=>'متوسط', 'strong'=>'قوی' ) as $v => $label ) : ?>
									<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $mstyle['shadow'] ?? 'medium', $v ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></td>
						</tr>
						<tr>
							<th scope="row">ابعاد و افکت</th>
							<td>
								<label>گردی <input type="number" min="12" max="32" name="mobile[nav_style][radius]" value="<?php echo esc_attr( $mstyle['radius'] ?? '22' ); ?>" style="width:80px;"> px</label>
								<label style="margin-inline-start:18px;">ارتفاع <input type="number" min="60" max="82" name="mobile[nav_style][height]" value="<?php echo esc_attr( $mstyle['height'] ?? '68' ); ?>" style="width:80px;"> px</label>
								<label style="margin-inline-start:18px;"><input type="checkbox" name="mobile[nav_style][blur]" value="1" <?php checked( ! empty( $mstyle['blur'] ) ); ?>> شیشه‌ای/Blur</label>
								<label style="margin-inline-start:18px;"><input type="checkbox" name="mobile[nav_style][animate]" value="1" <?php checked( ! empty( $mstyle['animate'] ) ); ?>> افکت نرم آیکون‌ها</label>
							</td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( true, 'mobile' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-phone"></span>
				<strong class="jluxe-hb-type-label">تماس و شبکه‌های اجتماعی</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

					<h3>اطلاعات تماس</h3>
					<p class="description">اگر فیلدی خالی بمونه، در سایت نمایش داده نمی‌شه. همینا در صفحه‌ی «تماس با ما» (contact-us) هم نشون داده می‌شن. ساعاتِ پاسخگویی رو از تبِ «فوتر» ← «ساعات پشتیبانی» تنظیم کن (همون فیلد، هم فوتر هم این صفحه رو پوشش می‌ده).</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-phone">تلفن</label></th>
							<td><input type="text" id="jluxe-phone" name="contact[phone]" value="<?php echo esc_attr( $settings['contact']['phone'] ); ?>" class="regular-text" dir="ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-phone-secondary">تلفنِ دوم (اختیاری)</label></th>
							<td><input type="text" id="jluxe-phone-secondary" name="contact[phone_secondary]" value="<?php echo esc_attr( $settings['contact']['phone_secondary'] ?? '' ); ?>" class="regular-text" dir="ltr" placeholder="خالی = فقط یک شماره نشون داده می‌شه" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-email">ایمیل</label></th>
							<td><input type="email" id="jluxe-email" name="contact[email]" value="<?php echo esc_attr( $settings['contact']['email'] ); ?>" class="regular-text" dir="ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-address">آدرس</label></th>
							<td><textarea id="jluxe-address" name="contact[address]" rows="3" class="large-text"><?php echo esc_textarea( $settings['contact']['address'] ); ?></textarea></td>
						</tr>
					</table>

					<h3>اطلاعیه‌ی داشبورد حساب کاربری</h3>
					<p class="description">اگر متن خالی باشه، این بخش در داشبورد «حساب کاربری» نمایش داده نمی‌شه.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-dashboard-announcement">متن اطلاعیه</label></th>
							<td><input type="text" id="jluxe-dashboard-announcement" name="contact[dashboard_announcement]" value="<?php echo esc_attr( $settings['contact']['dashboard_announcement'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-dashboard-announcement-link">لینک اطلاعیه (اختیاری)</label></th>
							<td><input type="url" id="jluxe-dashboard-announcement-link" name="contact[dashboard_announcement_link]" value="<?php echo esc_attr( $settings['contact']['dashboard_announcement_link'] ); ?>" class="regular-text" dir="ltr" placeholder="https://" /></td>
						</tr>
					</table>

					<h3>شبکه‌های اجتماعی</h3>
					<table class="form-table" role="presentation">
						<?php foreach ( $social_labels as $key => $label ) : $row = $settings['social'][ $key ]; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<label><input type="checkbox" name="social[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $row['enabled'] ); ?> /> فعال</label>
									<br /><br />
									<input type="url" name="social[<?php echo esc_attr( $key ); ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" class="regular-text" placeholder="https://" dir="ltr" />
									<br /><br />
									<label>آیکونِ SVG سفارشی (اختیاری — مخصوصاً برای واتس‌اپ/روبیکا/بله که آیکونِ اختصاصی ندارن)<br />
										<textarea name="social[<?php echo esc_attr( $key ); ?>][svg]" rows="2" class="large-text code" dir="ltr" placeholder="&lt;svg viewBox=&quot;0 0 24 24&quot;&gt;...&lt;/svg&gt;"><?php echo esc_textarea( $row['svg'] ?? '' ); ?></textarea>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<?php jluxe_settings_submit_button( false ); ?>
				</form>
			</div>
		</details>
		<?php
	} );
}

/**
 * صفحه‌ی «تماس و صفحات» — یک محلِ واحد برای همه‌چیزِ مرتبط با اطلاعاتِ
 * تماس و صفحاتِ «تماس با ما»/«درباره ما» (طبقِ درخواستِ صریحِ کاربر).
 * فیلدهای contact/social همون بخش‌های موجودن (اینجا هم قابل‌ویرایشن، جای
 * دیگه هم هست — منبعِ داده یکیه)؛ ساعاتِ پاسخگویی و توضیحِ کوتاهِ سایت با
 * merge روی مقدارِ فعلیِ فوتر/هویت ذخیره می‌شن (جزئیاتش را در
 * jluxe_handle_contact_pages_combined_save ببین) تا چیزِ دیگه‌ای پاک نشه.
 */
function jluxe_render_contact_pages_page(): void {
	$status   = jluxe_handle_contact_pages_combined_save();
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'تماس و صفحات', 'jluxe-contact-pages', $status, function () use ( $settings ) {
		$social_labels = array(
			'instagram' => 'اینستاگرام',
			'telegram'  => 'تلگرام',
			'whatsapp'  => 'واتس‌اپ',
			'rubika'    => 'روبیکا',
			'bale'      => 'بله',
		);
		?>
		<p class="description">همه‌چیزِ مرتبط با اطلاعاتِ تماس و صفحاتِ «تماس با ما»/«درباره ما» — یک‌جا. هر بخش، فرمِ مستقلِ خودش رو داره؛ ذخیره‌ی یکی روی بقیه اثر نمی‌ذاره.</p>

		<details class="jluxe-hb-section-details" open>
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-phone"></span>
				<strong class="jluxe-hb-type-label">شماره‌تلفن‌ها و آدرس</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">اگر فیلدی خالی بمونه، در سایت نمایش داده نمی‌شه.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-cp-phone">تلفن</label></th>
							<td><input type="text" id="jluxe-cp-phone" name="contact[phone]" value="<?php echo esc_attr( $settings['contact']['phone'] ); ?>" class="regular-text" dir="ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-cp-phone2">تلفنِ دوم (اختیاری)</label></th>
							<td><input type="text" id="jluxe-cp-phone2" name="contact[phone_secondary]" value="<?php echo esc_attr( $settings['contact']['phone_secondary'] ?? '' ); ?>" class="regular-text" dir="ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-cp-email">ایمیل</label></th>
							<td><input type="email" id="jluxe-cp-email" name="contact[email]" value="<?php echo esc_attr( $settings['contact']['email'] ); ?>" class="regular-text" dir="ltr" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="jluxe-cp-address">آدرس</label></th>
							<td><textarea id="jluxe-cp-address" name="contact[address]" rows="3" class="large-text"><?php echo esc_textarea( $settings['contact']['address'] ); ?></textarea></td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( false ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-clock"></span>
				<strong class="jluxe-hb-type-label">ساعات پاسخگویی</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">همین متن هم در فوتر سایت و هم در صفحه‌ی «تماس با ما» نشون داده می‌شه.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-cp-hours">ساعات پاسخگویی</label></th>
							<td><input type="text" id="jluxe-cp-hours" name="jluxe_support_hours_field" value="<?php echo esc_attr( $settings['footer']['support_hours'] ); ?>" class="regular-text" /></td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( false ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-share"></span>
				<strong class="jluxe-hb-type-label">شبکه‌های اجتماعی</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">هر شبکه‌ای که «فعال» نباشه، اصلاً در سایت (فوتر و صفحه‌ی تماس با ما) نمایش داده نمی‌شه.</p>
					<table class="form-table" role="presentation">
						<?php foreach ( $social_labels as $key => $label ) : $row = $settings['social'][ $key ]; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<label><input type="checkbox" name="social[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $row['enabled'] ); ?> /> فعال</label>
									<br /><br />
									<input type="url" name="social[<?php echo esc_attr( $key ); ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" class="regular-text" placeholder="https://" dir="ltr" />
									<br /><br />
									<label>آیکونِ SVG سفارشی (اختیاری)<br />
										<textarea name="social[<?php echo esc_attr( $key ); ?>][svg]" rows="2" class="large-text code" dir="ltr" placeholder="&lt;svg viewBox=&quot;0 0 24 24&quot;&gt;...&lt;/svg&gt;"><?php echo esc_textarea( $row['svg'] ?? '' ); ?></textarea>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php jluxe_settings_submit_button( false ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-info"></span>
				<strong class="jluxe-hb-type-label">توضیح کوتاه سایت</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">توضیحِ عمومیِ سایت — در عنوان/توضیحِ صفحاتی که مقدارِ اختصاصیِ خودشون رو ندارن استفاده می‌شه.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-cp-desc">توضیح کوتاه</label></th>
							<td><input type="text" id="jluxe-cp-desc" name="jluxe_site_description_field" value="<?php echo esc_attr( $settings['identity']['short_description'] ); ?>" class="large-text" /></td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( false ); ?>
				</form>
			</div>
		</details>

		<?php
		$info_pages_labels = array(
			'contact' => 'صفحه‌ی «تماس با ما»',
			'about'   => 'صفحه‌ی «درباره ما»',
		);
		?>
		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-media-text"></span>
				<strong class="jluxe-hb-type-label">متنِ صفحاتِ تماس/درباره</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<?php foreach ( $info_pages_labels as $page_key => $page_label ) : $row = $settings['info_pages'][ $page_key ]; ?>
						<h3><?php echo esc_html( $page_label ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="jluxe-cp-<?php echo esc_attr( $page_key ); ?>-title">عنوان</label></th>
								<td><input type="text" id="jluxe-cp-<?php echo esc_attr( $page_key ); ?>-title" name="info_pages[<?php echo esc_attr( $page_key ); ?>][intro_title]" value="<?php echo esc_attr( $row['intro_title'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="jluxe-cp-<?php echo esc_attr( $page_key ); ?>-text">متنِ توضیح</label></th>
								<td><textarea id="jluxe-cp-<?php echo esc_attr( $page_key ); ?>-text" name="info_pages[<?php echo esc_attr( $page_key ); ?>][intro_text]" rows="3" class="large-text"><?php echo esc_textarea( $row['intro_text'] ); ?></textarea></td>
							</tr>
							<?php if ( 'contact' === $page_key ) : ?>
								<tr><th>عنوان کارت تلفن</th><td><input type="text" name="info_pages[contact][phone_title]" value="<?php echo esc_attr( $row['phone_title'] ?? 'شماره‌ی تماس' ); ?>" class="regular-text" /></td></tr>
								<tr><th>عنوان کارت ساعات</th><td><input type="text" name="info_pages[contact][hours_title]" value="<?php echo esc_attr( $row['hours_title'] ?? 'ساعات پاسخگویی' ); ?>" class="regular-text" /></td></tr>
								<tr><th>عنوان کارت شبکه‌های اجتماعی</th><td><input type="text" name="info_pages[contact][social_title]" value="<?php echo esc_attr( $row['social_title'] ?? 'شبکه‌های اجتماعی' ); ?>" class="regular-text" /></td></tr>
							<?php endif; ?>
							<tr>
								<th scope="row">لوگو بالای صفحه</th>
								<td><label><input type="checkbox" name="info_pages[<?php echo esc_attr( $page_key ); ?>][show_logo]" value="1" <?php checked( ! empty( $row['show_logo'] ) ); ?> /> نمایشِ لوگوی سایت بالای این صفحه</label></td>
							</tr>
							<?php if ( 'about' === $page_key ) : ?>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-story">بخشِ «داستانِ ما»</label></th>
									<td>
										<?php
										wp_editor(
											$row['story_html'] ?? '',
											'jluxe-cp-about-story',
											array(
												'textarea_name' => 'info_pages[about][story_html]',
												'textarea_rows' => 8,
												'media_buttons' => false,
												'teeny'         => true,
											)
										);
										?>
										<p class="description">این HTML عیناً در صفحه‌ی «درباره ما» جایگزینِ بخشِ «داستانِ ما» می‌شه — قابلِ خزشِ کاملِ گوگل، چون سمتِ سرور رندر می‌شه.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-why">بخشِ «چرا ما»</label></th>
									<td>
										<?php wp_editor( $row['why_html'] ?? '', 'jluxe-cp-about-why', array( 'textarea_name' => 'info_pages[about][why_html]', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true ) ); ?>
										<p class="description">این ویرایشگر محتوای کاملِ بخشِ «چرا ما» را کنترل می‌کند.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-cta-text">متنِ دعوت‌به‌اقدام (CTA)</label></th>
									<td><input type="text" id="jluxe-cp-about-cta-text" name="info_pages[about][cta_text]" value="<?php echo esc_attr( $row['cta_text'] ?? '' ); ?>" class="large-text" /></td>
								</tr>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-cta-btn-text">متنِ دکمه‌ی CTA</label></th>
									<td><input type="text" id="jluxe-cp-about-cta-btn-text" name="info_pages[about][cta_button_text]" value="<?php echo esc_attr( $row['cta_button_text'] ?? '' ); ?>" class="regular-text" /></td>
								</tr>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-cta-btn-url">لینکِ دکمه‌ی CTA</label></th>
									<td><input type="text" id="jluxe-cp-about-cta-btn-url" name="info_pages[about][cta_button_url]" value="<?php echo esc_attr( $row['cta_button_url'] ?? '' ); ?>" class="regular-text" dir="ltr" /></td>
								</tr>
								<tr>
									<th scope="row"><label for="jluxe-cp-about-cta-btn-color">رنگِ دکمه‌ی CTA</label></th>
									<td><input type="text" id="jluxe-cp-about-cta-btn-color" name="info_pages[about][cta_button_color]" value="<?php echo esc_attr( $row['cta_button_color'] ?? '' ); ?>" class="jluxe-color-field" data-default-color="" /></td>
								</tr>
							<?php endif; ?>
						</table>
					<?php endforeach; ?>
					<?php jluxe_settings_submit_button( true, 'info_pages' ); ?>
				</form>
			</div>
		</details>
		<?php
	} );
}

/**
 * صفحه‌ی «صفحات راهنما» — طبقِ درخواستِ صریحِ کاربر («یه تب اضافه کن برای
 * همه‌ی این تنظیمات ... حتی رنگِ دکمه‌ها هم باید بشه تغییر داد»): عنوان/متنِ
 * مقدماتی/دکمه (و رنگِ دکمه) برای هر ۵ صفحه‌ی راهنما، به‌علاوهٔ اطلاعاتِ
 * حسابِ کارت‌به‌کارت. توجه: برخلافِ الگوی «چند فرمِ مستقل» در تماس/صفحات،
 * این‌جا همه‌ی ۵ صفحه‌ی راهنما در یک فرمِ واحد پست می‌شن — چون
 * jluxe_sanitize_guide_pages یک بخشِ (section) واحده و اگه صفحه‌ای در پست
 * نباشه، مقدارش با defaultِ کدنویسی‌شده (نه مقدارِ ذخیره‌شدهٔ فعلی) جایگزین
 * می‌شه؛ فرمِ جدا برای هر صفحه یعنی ذخیره‌ی یکی، بقیه رو ریست می‌کرد.
 * آکاردئون‌ها این‌جا فقط برای نظمِ بصریه، نه فرمِ مستقل.
 */
function jluxe_render_guide_pages_page(): void {
	$status   = jluxe_handle_guide_pages_combined_save();
	$settings = jluxe_get_fresh_settings();
	$guide    = $settings['guide_pages']['shopping_guide'] ?? array();
	$steps    = isset( $guide['steps'] ) && is_array( $guide['steps'] ) ? $guide['steps'] : array();

	jluxe_settings_page_shell( 'راهنمای خرید و صفحات راهنما', 'jluxe-guide-pages', $status, function () use ( $settings, $guide, $steps ) {
		?>
		<div class="jluxe-guide-admin-intro">
			<h2>راهنمای خرید</h2>
			<p>این بخش منبع اصلی محتوای صفحهٔ «راهنمای گام‌به‌گام خرید» است. تغییرات عنوان، مقدمه، تمام مراحل، باکس‌های نکته و دکمهٔ پایانی مستقیماً در صفحه اعمال می‌شود.</p>
			<p><strong>{site_name}</strong> در عنوان و متن‌ها با نام سایت جایگزین می‌شود.</p>
		</div>

		<form method="post" class="jluxe-guide-editor-form">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<input type="hidden" name="_jluxe_guide_editor" value="1" />

			<details class="jluxe-hb-section-details" open>
				<summary class="jluxe-hb-section-head"><span class="dashicons dashicons-welcome-learn-more"></span><strong class="jluxe-hb-type-label">محتوای اصلی صفحه</strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
				<div class="jluxe-hb-section-body">
					<table class="form-table" role="presentation">
						<tr><th><label for="jluxe-gp-eyebrow">متن بالای عنوان</label></th><td><input class="large-text" id="jluxe-gp-eyebrow" name="guide_pages[shopping_guide][eyebrow]" value="<?php echo esc_attr( $guide['eyebrow'] ?? '' ); ?>" /></td></tr>
						<tr><th><label for="jluxe-gp-title">عنوان اصلی</label></th><td><input class="large-text" id="jluxe-gp-title" name="guide_pages[shopping_guide][title]" value="<?php echo esc_attr( $guide['title'] ?? '' ); ?>" /></td></tr>
						<tr><th><label for="jluxe-gp-intro">مقدمه</label></th><td><textarea class="large-text" id="jluxe-gp-intro" name="guide_pages[shopping_guide][intro]" rows="4"><?php echo esc_textarea( $guide['intro'] ?? '' ); ?></textarea></td></tr>
					</table>
				</div>
			</details>

			<?php for ( $i = 0; $i < 5; $i++ ) : $step = $steps[ $i ] ?? array(); $n = $i + 1; ?>
			<details class="jluxe-hb-section-details" <?php echo 0 === $i ? 'open' : ''; ?>>
				<summary class="jluxe-hb-section-head"><span class="jluxe-guide-step-badge"><?php echo esc_html( $n ); ?></span><strong class="jluxe-hb-type-label">مرحله <?php echo esc_html( $n ); ?> — <?php echo esc_html( $step['title'] ?? '' ); ?></strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
				<div class="jluxe-hb-section-body">
					<table class="form-table" role="presentation">
						<tr><th><label for="jluxe-gp-step-<?php echo esc_attr( $i ); ?>-title">عنوان مرحله</label></th><td><input class="large-text" id="jluxe-gp-step-<?php echo esc_attr( $i ); ?>-title" name="guide_pages[shopping_guide][steps][<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $step['title'] ?? '' ); ?>" /></td></tr>
					</table>
					<?php
					wp_editor(
						$step['content'] ?? '',
						'jluxe_gp_step_' . $i . '_content',
						array(
							'textarea_name' => 'guide_pages[shopping_guide][steps][' . $i . '][content]',
							'textarea_rows' => 8,
							'quicktags'     => true,
							'media_buttons'=> false,
							'tinymce'      => array( 'directionality' => 'rtl', 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo' ),
						)
					);
					?>
					<div class="jluxe-guide-callout-editor">
						<h4>باکس نکته (اختیاری)</h4>
						<div class="jluxe-guide-inline-grid">
							<label>نوع باکس<select name="guide_pages[shopping_guide][steps][<?php echo esc_attr( $i ); ?>][callout_type]"><option value="" <?php selected( $step['callout_type'] ?? '', '' ); ?>>بدون باکس</option><option value="tip" <?php selected( $step['callout_type'] ?? '', 'tip' ); ?>>نکته</option><option value="note" <?php selected( $step['callout_type'] ?? '', 'note' ); ?>>توجه</option></select></label>
							<label>عنوان باکس<input type="text" name="guide_pages[shopping_guide][steps][<?php echo esc_attr( $i ); ?>][callout_title]" value="<?php echo esc_attr( $step['callout_title'] ?? '' ); ?>" /></label>
						</div>
						<label>متن باکس<textarea rows="3" name="guide_pages[shopping_guide][steps][<?php echo esc_attr( $i ); ?>][callout_text]"><?php echo esc_textarea( $step['callout_text'] ?? '' ); ?></textarea></label>
					</div>
				</div>
			</details>
			<?php endfor; ?>

			<details class="jluxe-hb-section-details" open>
				<summary class="jluxe-hb-section-head"><span class="dashicons dashicons-admin-links"></span><strong class="jluxe-hb-type-label">دکمهٔ پایانی</strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
				<div class="jluxe-hb-section-body">
					<table class="form-table" role="presentation">
						<tr><th>نمایش دکمه</th><td><label><input type="checkbox" name="guide_pages[shopping_guide][button_enabled]" value="1" <?php checked( ! empty( $guide['button_enabled'] ) ); ?> /> نمایش دکمهٔ پایانی</label></td></tr>
						<tr><th><label>متن دکمه</label></th><td><input class="regular-text" name="guide_pages[shopping_guide][button_text]" value="<?php echo esc_attr( $guide['button_text'] ?? '' ); ?>" /></td></tr>
						<tr><th><label>لینک دکمه</label></th><td><input class="regular-text" dir="ltr" name="guide_pages[shopping_guide][button_url]" value="<?php echo esc_attr( $guide['button_url'] ?? '' ); ?>" /></td></tr>
						<tr><th><label>رنگ دکمه</label></th><td><input class="jluxe-color-field" name="guide_pages[shopping_guide][button_color]" value="<?php echo esc_attr( $guide['button_color'] ?? '' ); ?>" data-default-color="" /></td></tr>
					</table>
				</div>
			</details>

			<?php foreach ( array( 'payment_guide' => 'روش‌ها و راهنمای پرداخت سفارشات', 'shipping_tracking' => 'روش‌های ارسال و راهنمای پیگیری سفارشات', 'returns_exchanges' => 'رویهٔ شرایط مرجوعی و تعویض کالا' ) as $page_key => $page_label ) : $page_row = $settings['guide_pages'][ $page_key ] ?? array(); ?>
			<details class="jluxe-hb-section-details">
				<summary class="jluxe-hb-section-head"><span class="dashicons dashicons-media-document"></span><strong class="jluxe-hb-type-label"><?php echo esc_html( $page_label ); ?></strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
				<div class="jluxe-hb-section-body">
					<table class="form-table" role="presentation"><tr><th>عنوان صفحه</th><td><input class="large-text" name="guide_pages[<?php echo esc_attr( $page_key ); ?>][title]" value="<?php echo esc_attr( $page_row['title'] ?? '' ); ?>"></td></tr><tr><th>مقدمه</th><td><textarea class="large-text" rows="3" name="guide_pages[<?php echo esc_attr( $page_key ); ?>][intro]"><?php echo esc_textarea( $page_row['intro'] ?? '' ); ?></textarea></td></tr></table>
					<?php wp_editor( $page_row['body_html'] ?? '', 'jluxe_gp_body_' . $page_key, array( 'textarea_name' => 'guide_pages[' . $page_key . '][body_html]', 'textarea_rows' => 18, 'media_buttons' => false, 'teeny' => false, 'tinymce' => array( 'directionality' => 'rtl' ) ) ); ?>
					<p class="description">توکن‌های قابل استفاده: <code>{site_name}</code> و در پرداخت <code>{card_number}</code>، <code>{sheba}</code>، <code>{holder_name}</code>، <code>{bank_name}</code>. برای مرجوعی نیز توکن‌های <code>{deny_title}</code>، <code>{deny_item_1_label}</code>، <code>{deny_item_1_text}</code>، <code>{deny_item_2_label}</code>، <code>{deny_item_2_text}</code>، <code>{tip_label}</code> و <code>{tip_text}</code> فعال هستند.</p>
				</div>
			</details>
			<?php endforeach; ?>

			<details class="jluxe-hb-section-details">
				<summary class="jluxe-hb-section-head"><span class="dashicons dashicons-location-alt"></span><strong class="jluxe-hb-type-label">پیگیری سریع سفارش</strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
				<div class="jluxe-hb-section-body"><table class="form-table" role="presentation"><tr><th>عنوان فرم</th><td><input class="large-text" name="guide_pages[track_order][form_title]" value="<?php echo esc_attr( $settings['guide_pages']['track_order']['form_title'] ?? 'پیگیری سفارش' ); ?>"></td></tr><tr><th>توضیح فرم</th><td><textarea class="large-text" rows="3" name="guide_pages[track_order][form_note]"><?php echo esc_textarea( $settings['guide_pages']['track_order']['form_note'] ?? '' ); ?></textarea></td></tr><tr><th>عنوان صفحه</th><td><input class="large-text" name="guide_pages[track_order][title]" value="<?php echo esc_attr( $settings['guide_pages']['track_order']['title'] ?? '' ); ?>"></td></tr><tr><th>مقدمه</th><td><textarea class="large-text" rows="3" name="guide_pages[track_order][intro]"><?php echo esc_textarea( $settings['guide_pages']['track_order']['intro'] ?? '' ); ?></textarea></td></tr></table></div>
			</details>



			<?php jluxe_settings_submit_button( true, 'guide_pages' ); ?>
		</form>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head"><span class="dashicons dashicons-money-alt"></span><strong class="jluxe-hb-type-label">اطلاعات حساب کارت‌به‌کارت</strong><span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron"></span></summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<?php $pa = $settings['payment_account'] ?? array(); ?>
					<table class="form-table" role="presentation">
						<tr><th>شماره کارت</th><td><input class="regular-text" dir="ltr" name="payment_account[card_number]" value="<?php echo esc_attr( $pa['card_number'] ?? '' ); ?>" /></td></tr>
						<tr><th>شماره شبا</th><td><input class="regular-text" dir="ltr" name="payment_account[sheba]" value="<?php echo esc_attr( $pa['sheba'] ?? '' ); ?>" /></td></tr>
						<tr><th>نام صاحب حساب</th><td><input class="regular-text" name="payment_account[holder_name]" value="<?php echo esc_attr( $pa['holder_name'] ?? '' ); ?>" /></td></tr>
						<tr><th>نام بانک</th><td><input class="regular-text" name="payment_account[bank_name]" value="<?php echo esc_attr( $pa['bank_name'] ?? '' ); ?>" /></td></tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary">ذخیره اطلاعات کارت‌به‌کارت</button><button type="submit" name="jluxe_reset_section" value="payment_account" class="button jluxe-reset-section" onclick="return confirm('این بخش به حالت پیش‌فرض برگرده؟');">بازنشانی</button></p>
				</form>
			</div>
		</details>
		<?php
	} );
}

function jluxe_render_header_nav_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-header' ) );
	exit;
}

// =====================================================================
// سوالات متداول
// =====================================================================
function jluxe_render_faq_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-faq' );
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'سوالات متداول', 'jluxe-faq', $status, function () use ( $settings ) {
		$items = $settings['faq']['items'];
		?>
		<p class="description">این سوال‌ها دقیقاً همون‌هایی هستن که در صفحه‌ی <code><?php echo esc_html( home_url( '/faq/' ) ); ?></code> نمایش داده می‌شن. برای حذف یک مورد تیک «حذف این سوال» رو بزنید و ذخیره کنید؛ برای افزودن، ردیف‌های خالیِ پایین صفحه رو پر کنید و ذخیره کنید — بعد از ذخیره، ۳ ردیف خالیِ تازه برای افزودن بیشتر ظاهر می‌شه.</p>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php
				$i = 0;
				foreach ( $items as $item ) :
					?>
					<tr>
						<th scope="row">سوال <?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></th>
						<td>
							<input type="text" name="faq[items][<?php echo esc_attr( $i ); ?>][question]" value="<?php echo esc_attr( $item['question'] ); ?>" class="large-text" placeholder="متن سوال" />
							<br /><br />
							<textarea name="faq[items][<?php echo esc_attr( $i ); ?>][answer]" rows="3" class="large-text" placeholder="پاسخ"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
							<p><label><input type="checkbox" name="faq[items][<?php echo esc_attr( $i ); ?>][remove]" value="1" /> حذف این سوال</label></p>
						</td>
					</tr>
					<?php
					$i++;
				endforeach;
				for ( $e = 0; $e < 3; $e++, $i++ ) :
					?>
					<tr>
						<th scope="row">سوال جدید</th>
						<td>
							<input type="text" name="faq[items][<?php echo esc_attr( $i ); ?>][question]" value="" class="large-text" placeholder="متن سوال" />
							<br /><br />
							<textarea name="faq[items][<?php echo esc_attr( $i ); ?>][answer]" rows="3" class="large-text" placeholder="پاسخ"></textarea>
						</td>
					</tr>
				<?php endfor; ?>
			</table>
			<?php jluxe_settings_submit_button( true, 'faq' ); ?>
		</form>
		<?php
	} );
}

/**
 * معیارهای امتیازِ دیدگاه — پورتِ فیچرِ پوسته‌ی قبلی. هر ردیف یک کلیدِ
 * انگلیسیِ یکتا (فیلدِ فرمِ دیدگاه/متایِ کامنت) + یک برچسبِ فارسیِ نمایشی.
 * دقیقاً همون الگویِ jluxe-faq (ردیف‌های موجود + تیکِ حذف + ۳ ردیفِ خالیِ
 * افزودن) — بدونِ نیاز به هیچ JSِ جدید.
 */
function jluxe_render_review_criteria_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-review-criteria' );
	$settings = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'معیارهای امتیاز دیدگاه', 'jluxe-review-criteria', $status, function () use ( $settings ) {
		$items = $settings['review_criteria']['items'];
		?>
		<p class="description">
			معیارهایی که مشتری هنگامِ ثبتِ دیدگاه، به‌جز امتیازِ کلی، بهشون هم امتیاز می‌ده (مثلاً «کیفیت ساخت»). میانگینِ هر معیار زیرِ بخشِ دیدگاه‌های صفحه‌ی محصول نمایش داده می‌شه.
			«کلید» فقط برایِ ذخیره‌سازیِ داخلیه (انگلیسی/یکتا، اگه خالی بذاری خودکار از رویِ برچسب ساخته می‌شه)؛ «برچسب» همون متنیه که مشتری می‌بینه.
			برایِ حذف یک معیار، تیکِ «حذف این معیار» رو بزن و ذخیره کن.
		</p>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php
				$i = 0;
				foreach ( $items as $item ) :
					?>
					<tr>
						<th scope="row">معیار <?php echo esc_html( jluxe_fa_digits( $i + 1 ) ); ?></th>
						<td>
							<input type="text" name="review_criteria[items][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" class="regular-text" placeholder="مثلاً: کیفیت ساخت" />
							<input type="text" dir="ltr" name="review_criteria[items][<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $item['key'] ); ?>" class="regular-text" placeholder="quality" style="margin-inline-start:8px;" />
							<p><label><input type="checkbox" name="review_criteria[items][<?php echo esc_attr( $i ); ?>][remove]" value="1" /> حذف این معیار</label></p>
						</td>
					</tr>
					<?php
					$i++;
				endforeach;
				for ( $e = 0; $e < 3; $e++, $i++ ) :
					?>
					<tr>
						<th scope="row">معیار جدید</th>
						<td>
							<input type="text" name="review_criteria[items][<?php echo esc_attr( $i ); ?>][label]" value="" class="regular-text" placeholder="مثلاً: بسته‌بندی" />
							<input type="text" dir="ltr" name="review_criteria[items][<?php echo esc_attr( $i ); ?>][key]" value="" class="regular-text" placeholder="packaging (اختیاری)" style="margin-inline-start:8px;" />
						</td>
					</tr>
				<?php endfor; ?>
			</table>
			<?php jluxe_settings_submit_button( true, 'review_criteria' ); ?>
		</form>
		<?php
	} );
}

// =====================================================================
// سئو / عملکرد / CSS/JS سفارشی / درون‌ریزی-برون‌بری — چهار تبِ قبلاً جدا
// طبق درخواستِ کاربر («یکی‌شون کن، توی منوهای کشویی زیر هم») حالا یک تبِ
// واحدِ «تنظیمات»ان؛ هر بخش دقیقاً همون فرم/nonce/sanitize/handler قبلیِ
// خودش رو داره (بدونِ تغییر در منطقِ ذخیره‌سازی)، فقط به‌جای ۴ صفحه‌ی جدا
// حالا ۴ آکاردئونِ (<details>) زیرِهم روی یک صفحه‌ان. صفحاتِ قدیمی
// (jluxe-seo/jluxe-performance/jluxe-custom-code/jluxe-import-export) طبقِ
// همون الگویِ jluxe-header-nav (پایین‌تر در همین فایل) عمداً هنوز به‌عنوان
// submenu ثبت‌شده می‌مونن (برای سالم‌موندنِ capability resolution — نکته‌ی
// remove_submenu_page در بالای فایل) ولی رندرشون فقط یک ریدایرکت به تبِ
// جدیده.
// =====================================================================
function jluxe_render_seo_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-advanced' ) );
	exit;
}

function jluxe_render_performance_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-advanced' ) );
	exit;
}

function jluxe_render_custom_code_page(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-advanced' ) );
	exit;
}

function jluxe_render_advanced_page(): void {
	$import_status      = jluxe_handle_import();
	$full_reset_status  = jluxe_handle_full_reset();
	$saved_status       = jluxe_handle_advanced_combined_save();
	$settings           = jluxe_get_fresh_settings();

	jluxe_settings_page_shell( 'تنظیمات', 'jluxe-advanced', $saved_status, function () use ( $settings, $import_status, $full_reset_status ) {
		if ( 'imported' === $import_status ) {
			echo '<div class="notice notice-success is-dismissible"><p>✓ تنظیمات با موفقیت درون‌ریزی شد.</p></div>';
		} elseif ( 'import_error' === $import_status ) {
			echo '<div class="notice notice-error is-dismissible"><p>✕ فایل نامعتبره یا خراب — درون‌ریزی انجام نشد.</p></div>';
		}
		if ( 'reset' === $full_reset_status ) {
			echo '<div class="notice notice-success is-dismissible"><p>✓ همه‌ی تنظیمات زرین (و کلید API دستیار هوش مصنوعی) به حالت پیش‌فرض بازگشت.</p></div>';
		}

		$seo   = $settings['seo'];
		$perf  = $settings['performance'];
		$code  = $settings['custom_code'];
		global $wp_version;
		$theme = wp_get_theme();
		?>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-search"></span>
				<strong class="jluxe-hb-type-label">سئو</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">اگر پلاگین سئوی جدایی (مثل Rank Math/Yoast) بعداً نصب بشه، این تنظیمات به‌عنوان fallback فقط برای صفحاتی اجرا می‌شن که اون پلاگین متا تعریف نکرده.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="jluxe-meta-desc">توضیحات متای پیش‌فرض</label></th>
							<td><textarea id="jluxe-meta-desc" name="seo[default_meta_description]" rows="3" class="large-text"><?php echo esc_textarea( $seo['default_meta_description'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row">تصویر اشتراک‌گذاری (og:image)</th>
							<td><?php jluxe_render_media_field( 'seo[og_image_id]', (int) $seo['og_image_id'], 'تنظیم نشده.' ); ?></td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( true, 'seo' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-performance"></span>
				<strong class="jluxe-hb-type-label">عملکرد</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<h3>وضعیت واقعی سیستم</h3>
				<table class="form-table" role="presentation">
					<tr><th scope="row">نسخه‌ی وردپرس</th><td><?php echo esc_html( $wp_version ); ?></td></tr>
					<tr><th scope="row">نسخه‌ی PHP</th><td><?php echo esc_html( phpversion() ); ?></td></tr>
					<tr><th scope="row">نسخه‌ی ووکامرس</th><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'ووکامرس فعال نیست' ); ?></td></tr>
					<tr><th scope="row">نسخه‌ی پوسته‌ی زرین</th><td><?php echo esc_html( $theme->get( 'Version' ) ?: '—' ); ?></td></tr>
					<tr><th scope="row">حالت دیباگ (WP_DEBUG)</th><td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color:#b32d2e">روشن</span>' : '<span style="color:#008a20">خاموش</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
					<tr><th scope="row">کش آبجکت خارجی (Redis/Memcached)</th><td><?php echo wp_using_ext_object_cache() ? 'فعال' : 'غیرفعال (کش پیش‌فرض وردپرس)'; ?></td></tr>
					<tr><th scope="row">افزونه‌ی کش صفحه (LiteSpeed و مشابه)</th><td><?php echo defined( 'LSCWP_V' ) ? 'LiteSpeed Cache فعال' : 'شناسایی نشد'; ?></td></tr>
					<tr><th scope="row">حداکثر آپلود (PHP)</th><td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td></tr>
					<tr><th scope="row">memory_limit (PHP)</th><td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td></tr>
					<tr><th scope="row">max_execution_time (PHP)</th><td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?> ثانیه</td></tr>
					<tr><th scope="row">WP-Cron</th><td><?php echo ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? '<span style="color:#b32d2e">غیرفعال (DISABLE_WP_CRON)</span>' : 'فعال (پیش‌فرض وردپرس)'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
					<tr><th scope="row">نسخه‌ی تنظیمات پوسته</th><td><?php echo esc_html( (string) JLUXE_SETTINGS_VERSION ); ?></td></tr>
					<tr><th scope="row">حالت توسعه (JLUXE_DEV)</th><td><?php echo function_exists( 'jluxe_is_dev' ) && jluxe_is_dev() ? 'فعال (Vite dev server)' : 'غیرفعال (dist/ واقعی)'; ?></td></tr>
					<tr><th scope="row">آخرین به‌روزرسانی تنظیمات</th><td><?php echo esc_html( get_option( 'jluxe_theme_settings_updated_at', '—' ) ); ?></td></tr>
				</table>
				<p class="description">این بخش فقط تشخیصیه — چیزی رو خودکار تغییر نمی‌ده (مثلاً تنظیمات LiteSpeed/کش دست‌نخورده می‌مونه).</p>

				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<h3>تنظیمات</h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">غیرفعال‌سازی اسکریپت ایموجی وردپرس</th>
							<td><label><input type="checkbox" name="performance[disable_emojis]" value="1" <?php checked( $perf['disable_emojis'] ); ?> /> کاهش یک درخواست JS/CSS اضافه در هر صفحه</label></td>
						</tr>
						<tr>
							<th scope="row">Lazy-load تصاویر</th>
							<td><label><input type="checkbox" name="performance[lazy_load_images]" value="1" <?php checked( $perf['lazy_load_images'] ); ?> /> (رفتار پیش‌فرض خودِ وردپرس؛ فقط این‌جا قابل خاموش‌کردنه)</label></td>
						</tr>
						<tr>
							<th scope="row">به‌تعویق‌انداختنِ اسکریپت‌های افزونه‌های دیگر</th>
							<td>
								<label><input type="checkbox" name="performance[defer_third_party_scripts]" value="1" <?php checked( $perf['defer_third_party_scripts'] ?? false ); ?> /> اسکریپت‌های افزونه‌های دیگه (مثلِ Code Snippets) رو defer کن</label>
								<p class="description">
									طبقِ گزارشِ PageSpeed، jQuery و استایل‌های ووکامرس از قبل defer شدن (بالاتر توضیح داده شده)، ولی چند اسکریپتِ کوچیکِ دیگه (با اسم‌های هش‌مانند، معمولاً خروجیِ افزونه‌ی Code Snippets) هنوز مسدودکننده‌ان. این گزینه همون تکنیکِ defer رو — فقط برای اسکریپت‌هایی که از خودِ هسته‌ی وردپرس یا این پوسته نیستن — فعال می‌کنه.
									<strong>قبل از فعال‌کردن روی سایتِ اصلی، حتماً اول امتحانش کن</strong> — اگه یکی از اون اسکریپت‌ها فرض کرده باشه بلافاصله (نه بعدِ پارسِ کاملِ صفحه) اجرا می‌شه، ممکنه اون قابلیتِ خاص از کار بیفته؛ در اون صورت همین‌جا خاموشش کن.
								</p>
							</td>
						</tr>
					</table>
					<?php jluxe_settings_submit_button( true, 'performance' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-editor-code"></span>
				<strong class="jluxe-hb-type-label">CSS/JS سفارشی</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<form method="post">
					<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
					<p class="description">این کد مستقیم روی هر صفحه‌ی سایت لود می‌شه — فقط برای ادمین‌های مطمئن (<code>manage_options</code>).</p>
					<h3>CSS سفارشی</h3>
					<textarea name="custom_code[css]" rows="10" class="large-text code" dir="ltr"><?php echo esc_textarea( $code['css'] ); ?></textarea>
					<h3>JavaScript سفارشی</h3>
					<textarea name="custom_code[js]" rows="10" class="large-text code" dir="ltr"><?php echo esc_textarea( $code['js'] ); ?></textarea>
					<?php jluxe_settings_submit_button( true, 'custom_code' ); ?>
				</form>
			</div>
		</details>

		<details class="jluxe-hb-section-details">
			<summary class="jluxe-hb-section-head">
				<span class="dashicons dashicons-migrate"></span>
				<strong class="jluxe-hb-type-label">درون‌ریزی / برون‌بری</strong>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>
			<div class="jluxe-hb-section-body">
				<h3>برون‌بری (Export)</h3>
				<p class="description">فقط تنظیمات ظاهری/پیکربندی زرین صادر می‌شه — هیچ کاربر، سفارش، محصول یا کلید API صادر نمی‌شه.</p>
				<form method="post">
					<?php wp_nonce_field( 'jluxe_export_settings', 'jluxe_export_nonce' ); ?>
					<button type="submit" name="jluxe_export" value="1" class="button button-primary">دانلود فایل تنظیمات (JSON)</button>
				</form>

				<hr />

				<h3>درون‌ریزی (Import)</h3>
				<p class="description">فقط فایل JSON خروجی همین پنل رو آپلود کن. هر مقدار وارد شده دقیقاً از همون قوانین اعتبارسنجی فرم‌های معمولی رد می‌شه.</p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'jluxe_import_settings', 'jluxe_import_nonce' ); ?>
					<input type="file" name="jluxe_import_file" accept="application/json" required />
					<button type="submit" name="jluxe_import" value="1" class="button" onclick="return confirm('همه‌ی تنظیمات فعلی با محتوای این فایل جایگزین می‌شن. ادامه بدم؟');">درون‌ریزی</button>
				</form>

				<hr />

				<h3>بازنشانی کامل</h3>
				<p class="description">همه‌ی تنظیمات زرین (رنگ‌ها، هدر، فوتر، صفحه اصلی، دستیار هوش مصنوعی و کلید API آن) به حالت پیش‌فرض کارخانه برمی‌گردن. <strong>هیچ محصول، سفارش، کاربر، صفحه یا رسانه‌ای حذف نمی‌شه.</strong></p>
				<form method="post">
					<?php wp_nonce_field( 'jluxe_reset_all_settings', 'jluxe_reset_nonce' ); ?>
					<button type="submit" name="jluxe_reset_all" value="1" class="button jluxe-reset-all" onclick="return confirm('همه‌ی تنظیمات زرین به حالت پیش‌فرض برگردن؟ این کار غیرقابل بازگشته.');">بازنشانی همه‌ی تنظیمات زرین</button>
				</form>
			</div>
		</details>
		<?php
	} );
}

