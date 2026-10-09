<?php
/**
 * صفحه اصلی — homepage builder (ادمین) + رندر واقعی روی frontend
 * (front-page.php این‌جا رو صدا می‌زنه). بخش‌های موجودِ واقعی («اسلایدر
 * هیرو» و «گرید محصولات») از همون معماری قبلی (hero-slideshow island +
 * WooCommerce loop) میان — چیزی جعلی/دمو اضافه نشده.
 */

defined( 'ABSPATH' ) || exit;

/**
 * انواع بخش قابل‌افزودن + برچسب فارسی — فقط چیزهایی که واقعاً پیاده‌سازی و
 * به داده‌ی واقعی (WooCommerce/رسانه/متن ادمین) وصل شدن.
 */
function jluxe_homepage_section_types(): array {
	return array(
		'hero'                => 'اسلایدر هیرو',
		'stories'             => 'نوار استوری',
		'product_grid'        => 'گرید محصولات (جدید/تخفیف‌دار/پرفروش/ویژه)',
		'category_grid'       => 'دسته‌بندی‌های محصول',
		'category_showcase'  => 'دسته‌بندی‌های ویژه (جدید)',
		'brand_marquee'      => 'نوار متحرک برندها',
		'banner'              => 'بنر تک',
		'banner_two'          => 'دو بنر',
		'banner_three'        => 'سه بنر',
		'banner_slider'       => 'اسلایدر بنر (چند اسلاید با عنوان)',
		'brick_products'      => 'محصولات پرفروش (چیدمان آجری)',
		'special_products'    => 'محصولات فروش ویژه (کاروسل خودکار)',
		'banner_collage'      => 'کلاژ بنر (۵ اسلات نامتقارن)',
		'recommended_panels'  => 'پنل‌های پیشنهادی (چند دسته کنار هم)',
		'trust'               => 'نشان‌های اعتماد',
		'blog'                => 'آخرین نوشته‌های وبلاگ',
		'text'                => 'متن سفارشی',
		'html'                => 'HTML سفارشی',
		'spacer'              => 'فاصله‌گذار',
	);
}

function jluxe_render_homepage_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-homepage' );
	$settings = jluxe_get_fresh_settings();
	$types    = jluxe_homepage_section_types();

	jluxe_settings_page_shell( 'صفحه اصلی', 'jluxe-homepage', $status, function () use ( $settings, $types ) {
		?>
		<p class="description">بخش‌ها رو با کشیدن (☰) جابه‌جا کن، فعال/غیرفعال کن، یا حذف/تکثیر کن. ترتیب همینی که این‌جا می‌بینی دقیقاً همون چیزیه که روی صفحه‌ی اصلی نمایش داده می‌شه.</p>

		<form method="post" id="jluxe-homepage-form">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<div id="jluxe-hb-sections">
				<?php foreach ( $settings['homepage']['sections'] as $i => $section ) : ?>
					<?php jluxe_render_homepage_section_editor( $i, $section, $types ); ?>
				<?php endforeach; ?>
			</div>

			<p>
				<select id="jluxe-hb-add-type">
					<?php foreach ( $types as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="jluxe-hb-add">+ افزودن بخش</button>
			</p>

			<?php jluxe_settings_submit_button( true, 'homepage' ); ?>
		</form>

		<div id="jluxe-hb-templates" style="display:none">
			<?php
			/*
			 * <template> (نه <script type="text/template">) عمداً — بخش‌های
			 * «هیرو»/«دسته‌بندی‌ها» خودشون یک <script type="text/template"
			 * class="jluxe-repeater-template"> تودرتو (برای ریپیترِ داخلی‌شون:
			 * اسلایدها/آیتم‌های دسته) دارن؛ مرورگر <script> رو تودرتو پارس
			 * نمی‌کنه (محتوای raw-text تا اولین </script> واقعی، صرف‌نظر از
			 * تودرتو بودن) — پس اون </script> داخلی زودتر از موعد کل
			 * <script data-hb-template="hero"> بیرونی رو می‌بست و بقیه‌ی
			 * تمپلیت‌ها (همه‌ی انواعِ بعد از hero) رو هم به‌هم می‌ریخت (باگِ
			 * واقعیِ گزارش‌شده: هیچ نوع سکشنی اضافه نمی‌شد). <template> این
			 * مشکل رو نداره چون محتواش یک DocumentFragmentِ خنثی/جدا از DOM
			 * اصلیه، نه raw-text.
			 */
			?>
			<?php foreach ( $types as $type => $label ) : ?>
				<template data-hb-template="<?php echo esc_attr( $type ); ?>">
					<?php jluxe_render_homepage_section_editor( '__INDEX__', array( 'id' => '', 'type' => $type, 'enabled' => true ), $types, true ); ?>
				</template>
			<?php endforeach; ?>
		</div>
		<?php
	} );
}

function jluxe_render_homepage_section_editor( $i, array $section, array $types, bool $is_template = false ): void {
	$type    = $section['type'] ?? 'text';
	$name    = "homepage[sections][{$i}]";
	$label   = $types[ $type ] ?? $type;
	?>
	<div class="jluxe-hb-section" data-type="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" class="jluxe-hb-type-input" name="<?php echo esc_attr( $name ); ?>[type]" value="<?php echo esc_attr( $type ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $section['id'] ?? '' ); ?>" />

		<?php /* بخش‌های موجود بسته شروع می‌شن (جلوگیری از طول زیاد صفحه)؛ بخشِ تازه‌افزوده (کلون‌شده از قالب) باز شروع می‌شه تا بلافاصله قابل تنظیم باشه. */ ?>
		<details class="jluxe-hb-section-details" <?php echo $is_template ? 'open' : ''; ?>>
			<summary class="jluxe-hb-section-head">
				<span class="jluxe-hb-handle dashicons dashicons-menu" title="جابه‌جایی"></span>
				<strong class="jluxe-hb-type-label"><?php echo esc_html( $label ); ?></strong>
				<label class="jluxe-hb-enabled">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $section['enabled'] ) ); ?> /> فعال
				</label>
				<button type="button" class="button-link jluxe-hb-duplicate" title="تکثیر">⧉</button>
				<button type="button" class="button-link jluxe-hb-remove" title="حذف">✕</button>
				<span class="dashicons dashicons-arrow-down-alt2 jluxe-hb-chevron" aria-hidden="true"></span>
			</summary>

		<div class="jluxe-hb-section-body">
			<?php
			switch ( $type ) {
				case 'hero':
					$hero_opt = jluxe_hero_options( $section );
					?>
					<fieldset class="jluxe-hero-admin">
						<legend><strong>حرکت</strong></legend>
						<?php
						jluxe_hb_field_select( $name, 'effect', 'نحوهٔ اسلاید', $hero_opt['effect'], jluxe_hero_effects() );
						jluxe_hb_field_number( $name, 'speed_ms', 'سرعتِ جابه‌جایی (میلی‌ثانیه؛ ۱۵۰ تا ۲۰۰۰)', $hero_opt['speed_ms'], 150, 2000 );
						jluxe_hb_field_checkbox( $name, 'autoplay', 'چرخشِ خودکار', $hero_opt['autoplay'] );
						jluxe_hb_field_number( $name, 'duration_sec', 'مدت نمایش هر اسلاید (ثانیه)', (int) ( $hero_opt['duration_ms'] / 1000 ), 2, 15 );
						jluxe_hb_field_checkbox( $name, 'loop', 'حلقهٔ بی‌پایان (بعد از آخری، اولی — بدونِ برگشتِ طولانی)', $hero_opt['loop'] );
						jluxe_hb_field_checkbox( $name, 'zoom_enabled', 'افکتِ بزرگ‌نماییِ آرامِ تصویر در طولِ نمایش (Ken Burns)', $hero_opt['zoom'] );
						?>
					</fieldset>
					<fieldset class="jluxe-hero-admin">
						<legend><strong>ابعاد و ظاهر</strong></legend>
						<?php
						jluxe_hb_field_select(
							$name,
							'width_mode',
							'عرض بنر',
							$hero_opt['width_mode'],
							array(
								'container' => 'به‌اندازهٔ کانتینر (هم‌عرض با بقیهٔ بخش‌های صفحهٔ اصلی)',
								'full'      => 'تمام صفحه (لبه‌به‌لبه)',
							)
						);
						jluxe_hb_field_number( $name, 'desktop_height', 'ارتفاع در دسکتاپ (px) — نمونهٔ شما: ۳۶۰', $hero_opt['desktop_height'], 200, 720 );
						jluxe_hb_field_number( $name, 'mobile_height', 'ارتفاع در موبایل (px) — نمونهٔ شما: ۳۲۰', $hero_opt['mobile_height'], 160, 640 );
						jluxe_hb_field_select(
							$name,
							'radius',
							'گردیِ گوشه‌ها',
							$hero_opt['radius'],
							array(
								'auto'  => 'خودکار (کانتینر: ۳۲/۲۴px — تمام صفحه: بدونِ گردی)',
								'large' => 'زیاد — ۳۲px دسکتاپ / ۲۴px موبایل (مثلِ نمونه)',
								'small' => 'کم — ۱۶px / ۱۲px',
								'none'  => 'بدونِ گردی',
							)
						);
						jluxe_hb_field_select(
							$name,
							'top_spacing',
							'فاصله از بالا (زیرِ هدر)',
							$hero_opt['top_spacing'],
							array(
								'reference' => 'مثلِ نمونه — ۶۴px دسکتاپ / ۱۶px موبایل',
								'compact'   => 'کم — ۲۴px / ۱۲px',
								'none'      => 'بدونِ فاصله',
							)
						);
						jluxe_hb_field_checkbox( $name, 'border', 'حاشیهٔ نازکِ دورِ بنر', $hero_opt['border'] );
						jluxe_hb_field_checkbox( $name, 'show_arrows', 'فلش‌های قبلی/بعدی (در موبایل همیشه با کشیدنِ انگشت)', $hero_opt['show_arrows'] );
						jluxe_hb_field_select(
							$name,
							'indicators',
							'نشانگرِ اسلایدها',
							$hero_opt['indicators'],
							array(
								'bars' => 'نوارِ پیشرفت',
								'dots' => 'نقطه',
								'none' => 'هیچ',
							)
						);
						?>
					</fieldset>
					<p class="description">
						برای هر اسلاید عکسِ دسکتاپ و موبایل جدا انتخاب کنید. وقتی عکس موبایل ثبت شده، در عرضِ موبایل فقط همان منبع و نسخه‌های کوچک‌ترِ خودش دانلود می‌شود؛ فایل دسکتاپ درخواست نمی‌شود. اگر خالی باشد، srcset نسخهٔ کم‌عرض‌ترِ عکس دسکتاپ را انتخاب می‌کند.
						<br /><br />
						<strong>ابعادِ پیشنهادیِ فایل‌ها:</strong> دسکتاپ <strong>۱۹۲۰ × ۷۰۰</strong> پیکسل؛ موبایل <strong>۹۰۰ × ۱۲۰۰</strong> پیکسل (نسبتِ ۳:۴). این‌ها اندازهٔ فایلِ منبع‌اند؛ ارتفاعِ نمایشی همچنان از فیلدهای دسکتاپ/موبایل بالا کنترل می‌شود. همهٔ اسلایدها را با نسبتِ یکسان بسازید تا هنگامِ جابه‌جایی ارتفاع نپرد.
					</p>
					<div class="jluxe-repeater" data-max="10">
						<div class="jluxe-repeater-list" data-group="items">
							<?php
							foreach ( $section['items'] ?? array() as $hi => $hero_item ) {
								jluxe_render_hero_item_fields( $name, $hi, $hero_item );
							}
							?>
						</div>
						<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_hero_item_fields( $name, 0, array() ); ?></script>
						<button type="button" class="button jluxe-repeater-add">+ افزودن اسلاید</button>
					</div>
					<?php
					break;

				case 'stories':
					echo '<p class="description">هر اسلات یک استوری با بندانگشتی + یک فایل کامل (عکس یا ویدیو) داره. اسلات‌های خالی نمایش داده نمی‌شن — چیزی جعلی/پیش‌فرض نیست.</p>';
					$items = $section['items'] ?? array();
					for ( $s = 0; $s < 8; $s++ ) :
						$item = $items[ $s ] ?? array();
						?>
						<div class="jluxe-hb-banner-item">
							<strong>استوری <?php echo esc_html( jluxe_fa_digits( $s + 1 ) ); ?></strong>
							<?php jluxe_render_media_field( "{$name}[items][{$s}][thumb_id]", (int) ( $item['thumb_id'] ?? 0 ), 'بندانگشتی انتخاب نشده' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'title', 'عنوان', $item['title'] ?? '' ); ?>
							<?php jluxe_hb_field_select( "{$name}[items][{$s}]", 'media_type', 'نوع محتوا', $item['media_type'] ?? 'image', array( 'image' => 'عکس', 'video' => 'ویدیو' ) ); ?>
							<?php jluxe_render_media_field( "{$name}[items][{$s}][media_id]", (int) ( $item['media_id'] ?? 0 ), 'فایل کامل (عکس/ویدیو) انتخاب نشده' ); ?>
						</div>
						<?php
					endfor;
					break;

				case 'product_grid':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? '' );
					jluxe_hb_field_text( $name, 'subtitle', 'زیرعنوان', $section['subtitle'] ?? '' );
					jluxe_hb_field_select(
						$name,
						'query',
						'منبع محصولات',
						$section['query'] ?? 'newest',
						array(
							'newest'     => 'جدیدترین',
							'sale'       => 'تخفیف‌دار',
							'bestseller' => 'پرفروش‌ترین',
							'featured'   => 'ویژه',
						)
					);
					jluxe_hb_field_select( $name, 'layout', 'چیدمان', $section['layout'] ?? 'grid', array( 'grid' => 'شبکه‌ای (ثابت)', 'carousel' => 'کاروسل (اسکرول افقی)' ) );
					jluxe_hb_field_select( $name, 'category', 'فیلتر دسته (اختیاری)', $section['category'] ?? '', array( '' => 'همه دسته‌ها' ) + jluxe_hb_category_options() );
					jluxe_hb_field_select( $name, 'style', 'استایل', $section['style'] ?? 'plain', array( 'plain' => 'ساده', 'highlight' => 'برجسته (پس‌زمینه‌ی رنگ اصلی، مثل «فروش ویژه»)' ) );
					jluxe_hb_field_number( $name, 'count', 'تعداد محصول', $section['count'] ?? 8, 2, 16 );
					jluxe_hb_field_number( $name, 'columns_desktop', 'ستون دسکتاپ', $section['columns_desktop'] ?? 4, 2, 6 );
					jluxe_hb_field_number( $name, 'columns_tablet', 'ستون تبلت', $section['columns_tablet'] ?? 3, 2, 4 );
					jluxe_hb_field_number( $name, 'columns_mobile', 'ستون موبایل', $section['columns_mobile'] ?? 2, 1, 3 );
					break;

				case 'category_grid':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? '' );
					jluxe_render_category_view_all_fields( $name, $section );
					jluxe_hb_field_select( $name, 'cards_alignment', 'تراز گروه کادرهای دسته‌بندی', $section['cards_alignment'] ?? 'center', array( 'start' => 'راست', 'center' => 'وسط', 'end' => 'چپ' ) );
					jluxe_hb_field_select( $name, 'mobile_layout', 'چیدمان دسته‌ها در موبایل', $section['mobile_layout'] ?? 'row', array( 'row' => 'افقی با فلش‌های قبلی/بعدی', 'grid' => 'شبکه‌ای چندستونه' ) );
					jluxe_hb_field_select( $name, 'mobile_columns', 'تعداد کادر در هر ردیف موبایل', $section['mobile_columns'] ?? 3, array( 2 => '۲ کادر', 3 => '۳ کادر', 4 => '۴ کادر', 5 => '۵ کادر', 6 => '۶ کادر' ) );
					$default_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M10 6a4 4 0 1 0-8 0a4 4 0 0 0 8 0Zm0 12a4 4 0 1 0-8 0a4 4 0 0 0 8 0ZM22 6a4 4 0 1 0-8 0a4 4 0 0 0 8 0Zm0 12a4 4 0 1 0-8 0a4 4 0 0 0 8 0Z"></path></svg>';
					$icon_value = ! empty( $section['icon_svg'] ) ? $section['icon_svg'] : $default_icon;
					?>
					<p><label>آیکون عنوان (SVG)<br />
						<textarea name="<?php echo esc_attr( $name ); ?>[icon_svg]" rows="5" class="large-text code" dir="ltr" placeholder="کد SVG آیکون"><?php echo esc_textarea( $icon_value ); ?></textarea>
					</label></p>
					<p class="description">در دسکتاپ دسته‌ها افقی هستند؛ در موبایل می‌توانی کاروسلِ دارای فلش یا شبکهٔ ۲ تا ۶ ستونه را انتخاب کنی. تراز گروه، کادرها را جابه‌جا می‌کند؛ تراز محتوا جداگانه است.</p>
					<?php
					jluxe_hb_field_select( $name, 'image_shape', 'شکل تصویر', $section['image_shape'] ?? 'circle', array( 'circle' => 'دایره‌ای', 'square' => 'مربع با گوشه گرد', 'none' => 'بدون کادر' ) );
					jluxe_hb_field_select( $name, 'alignment', 'تراز تصویر و نام داخل هر کارت', $section['alignment'] ?? 'center', array( 'start' => 'راست', 'center' => 'وسط', 'end' => 'چپ' ) );
					jluxe_hb_field_number( $name, 'section_radius', 'گردی کادر اصلی (px)', $section['section_radius'] ?? 40, 12, 56 );
					jluxe_hb_field_number( $name, 'card_radius', 'گردی کارت دسته (px)', $section['card_radius'] ?? 20, 0, 40 );
					jluxe_hb_field_number( $name, 'image_size', 'اندازه تصویر (px)', $section['image_size'] ?? 80, 64, 140 );
					jluxe_hb_field_select( $name, 'image_bg_mode', 'پس‌زمینه‌ی قاب تصویر', $section['image_bg_mode'] ?? 'transparent', array( 'color' => 'رنگ دلخواه', 'transparent' => 'شفاف' ) );
					jluxe_hb_field_color( $name, 'image_bg_color', 'رنگ پس‌زمینه‌ی قاب تصویر', $section['image_bg_color'] ?? '#FFFFFF' );
					jluxe_hb_field_select( $name, 'card_shadow', 'سایه کارت', $section['card_shadow'] ?? 'soft', array( 'none' => 'بدون سایه', 'soft' => 'نرم', 'medium' => 'متوسط', 'strong' => 'قوی' ) );
					jluxe_hb_field_checkbox( $name, 'hover_lift', 'افکت بالا آمدن کارت هنگام لمس/هاور', array_key_exists( 'hover_lift', $section ) ? ! empty( $section['hover_lift'] ) : true );
					jluxe_hb_field_checkbox( $name, 'zoom_enabled', 'فعال‌سازی افکت بزرگنمایی تصویر', array_key_exists( 'zoom_enabled', $section ) ? ! empty( $section['zoom_enabled'] ) : true );
					?>
					<p class="description">دسته‌بندی‌ها رو خودت از پایین اضافه، مرتب (با کشیدن ☰) و حذف کن — فقط دسته‌های واقعیِ ووکامرس قابل انتخابن. تصویر اختیاریه؛ اگه خالی بمونه، از تصویرِ خودِ دسته (Products → Categories → Thumbnail) استفاده می‌شه.</p>
					<div class="jluxe-repeater" data-max="16">
						<div class="jluxe-repeater-list" data-group="items">
							<?php
							$cat_options = jluxe_hb_category_options();
							foreach ( $section['items'] ?? array() as $ci => $cat_item ) {
								jluxe_render_category_grid_item_fields( $name, $ci, $cat_item, $cat_options );
							}
							?>
						</div>
						<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_category_grid_item_fields( $name, 0, array(), $cat_options ); ?></script>
						<button type="button" class="button jluxe-repeater-add">+ افزودن دسته‌بندی</button>
					</div>
					<?php
					break;

				case 'category_showcase':
					jluxe_hb_field_select( $name, 'layout', 'چیدمان دسکتاپ', $section['layout'] ?? 'grid', array( 'grid' => 'شبکه‌ای', 'row' => 'نوار افقی' ) );
					jluxe_hb_field_select( $name, 'mobile_layout', 'چیدمان موبایل', $section['mobile_layout'] ?? 'grid', array( 'grid' => 'شبکه‌ای', 'row' => 'نوار افقی' ) );
					jluxe_hb_field_select( $name, 'mobile_columns', 'تعداد ستون‌های شبکه در موبایل', (string) ( $section['mobile_columns'] ?? 3 ), array( 2 => '۲ ستون', 3 => '۳ ستون', 4 => '۴ ستون', 5 => '۵ ستون', 6 => '۶ ستون' ) );
					jluxe_hb_field_select( $name, 'show_category_names', 'عنوان زیر تصویر', ( ! array_key_exists( 'show_category_names', $section ) || ! empty( $section['show_category_names'] ) ) ? '1' : '0', array( '1' => 'نمایش عنوان', '0' => 'فقط تصویر' ) );
					jluxe_hb_field_color( $name, 'card_text_color', 'رنگ عنوان زیر تصویر', $section['card_text_color'] ?? '#303936' );
					jluxe_hb_field_select( $name, 'image_shape', 'شکل تصویر', $section['image_shape'] ?? 'none', array( 'none' => 'بدون قاب/فرم', 'square' => 'مربع با گوشه‌های گرد', 'circle' => 'دایره‌ای' ) );
					jluxe_hb_field_select( $name, 'image_frame_mode', 'کادر دور تصویر', $section['image_frame_mode'] ?? 'none', array( 'none' => 'بدون کادر', 'transparent' => 'کادر شفاف', 'color' => 'کادر رنگی' ) );
					?>
					<div class="jluxe-showcase-frame-color" data-showcase-frame-mode="color"<?php echo 'color' === ( $section['image_frame_mode'] ?? 'none' ) ? '' : ' style="display:none" aria-hidden="true"'; ?>>
						<?php jluxe_hb_field_color( $name, 'image_frame_color', 'رنگ کادر تصویر', $section['image_frame_color'] ?? '#D7D7D7' ); ?>
					</div>
					<p class="description">هر آیتم یا یک دستهٔ ووکامرس با پیوند خودکار است، یا یک تصویر دلخواه با لینک مقصد. عنوان تصویر دلخواه اختیاری است. پس‌زمینهٔ سکشن و کارت‌ها همیشه شفاف می‌ماند.</p>
					<div class="jluxe-repeater" data-max="16">
						<div class="jluxe-repeater-list" data-group="items">
							<?php
							$showcase_cat_options = jluxe_hb_category_options();
							foreach ( $section['items'] ?? array() as $ci => $showcase_item ) {
								jluxe_render_category_showcase_item_fields( $name, (int) $ci, $showcase_item, $showcase_cat_options );
							}
							?>
						</div>
						<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_category_showcase_item_fields( $name, 0, array(), $showcase_cat_options ); ?></script>
						<button type="button" class="button jluxe-repeater-add">+ افزودن آیتم</button>
					</div>
					<?php
					break;

				case 'brand_marquee':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? 'برندهای منتخب' );
					jluxe_hb_field_number( $name, 'speed_sec', 'مدت یک دور حرکت (ثانیه)', $section['speed_sec'] ?? 36, 15, 120 );
					jluxe_hb_field_checkbox( $name, 'grayscale', 'نمایش لوگوها به‌صورت خاکستری تا زمان هاور', array_key_exists( 'grayscale', $section ) ? ! empty( $section['grayscale'] ) : true );
					jluxe_hb_field_checkbox( $name, 'pause_hover', 'توقف حرکت هنگام هاور', array_key_exists( 'pause_hover', $section ) ? ! empty( $section['pause_hover'] ) : true );
					?>
					<div class="jluxe-repeater" data-max="24"><div class="jluxe-repeater-list" data-group="items">
					<?php foreach ( $section['items'] ?? array() as $bi => $brand_item ) : ?>
						<div class="jluxe-repeater-item"><div class="jluxe-repeater-item-head"><span class="jluxe-repeater-handle dashicons dashicons-menu"></span><span>برند <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $bi + 1 ) ); ?></span></span><a href="#" class="jluxe-repeater-remove">✕</a></div>
						<?php jluxe_render_media_field( "{$name}[items][{$bi}][image_id]", (int) ( $brand_item['image_id'] ?? 0 ), 'لوگو انتخاب نشده' ); ?>
						<?php jluxe_hb_field_text( "{$name}[items][{$bi}]", 'title', 'نام برند', $brand_item['title'] ?? '' ); ?>
						<?php jluxe_hb_field_text( "{$name}[items][{$bi}]", 'link', 'لینک برند', $brand_item['link'] ?? '' ); ?>
						</div>
					<?php endforeach; ?></div>
					<script type="text/template" class="jluxe-repeater-template"><div class="jluxe-repeater-item"><div class="jluxe-repeater-item-head"><span class="jluxe-repeater-handle dashicons dashicons-menu"></span><span>برند</span><a href="#" class="jluxe-repeater-remove">✕</a></div><div><label>تصویر برند</label><?php jluxe_render_media_field( "{$name}[items][0][image_id]", 0, 'لوگو انتخاب نشده' ); ?></div><?php jluxe_hb_field_text( "{$name}[items][0]", 'title', 'نام برند', '' ); ?><?php jluxe_hb_field_text( "{$name}[items][0]", 'link', 'لینک برند', '' ); ?></div></script>
					<button type="button" class="button jluxe-repeater-add">+ افزودن برند</button></div>
					<?php
					break;

				case 'blog':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? 'از وبلاگ' );
					jluxe_hb_field_number( $name, 'count', 'تعداد پست', $section['count'] ?? 3, 2, 8 );
					echo '<p class="description">از پست‌های واقعی وردپرس (منتشرشده) خونده می‌شه.</p>';
					break;

				case 'banner':
				case 'banner_two':
				case 'banner_three':
					$count = 'banner' === $type ? 1 : ( 'banner_two' === $type ? 2 : 3 );
					if ( 'banner' === $type ) {
						$desktop_dimensions = '۱۹۲۰ × ۶۰۰';
						$mobile_dimensions  = '۹۰۰ × ۱۲۰۰ (۳:۴)';
					} elseif ( 'banner_two' === $type ) {
						$desktop_dimensions = '۹۰۰ × ۵۰۰ برای هر بنر';
						$mobile_dimensions  = '۹۰۰ × ۱۲۰۰ (۳:۴) برای هر بنر';
					} else {
						$desktop_dimensions = '۶۰۰ × ۶۰۰ (مربع)';
						$mobile_dimensions  = '۶۰۰ × ۶۰۰ (مربع)';
					}
					?>
					<p class="description">
						<strong>اندازهٔ پیشنهادی فایل‌ها:</strong> دسکتاپ <?php echo esc_html( $desktop_dimensions ); ?>؛ موبایل <?php echo esc_html( $mobile_dimensions ); ?>.
						نسخهٔ موبایل اختیاری است؛ با ثبت آن، مرورگر در موبایل فقط فایل/مشتقات موبایل را می‌گیرد. بدونِ آن، srcset نسخهٔ کم‌عرضِ تصویر دسکتاپ را انتخاب می‌کند.
						برای بنرهای ۲ و ۳تایی، حالت «نسبت اصلی» اندازهٔ طبیعی هر تصویر را حفظ می‌کند و برش نمی‌دهد.
					</p>
					<?php
					for ( $b = 0; $b < $count; $b++ ) :
						$item = $section['items'][ $b ] ?? array();
						// اگه هنوز هیچ‌وقت با این نسخه ذخیره نشده (کلید overlay اصلاً
						// وجود نداره)، یعنی از نسخه‌ی قبلی میاد که overlay همیشه روشن
						// بود — پیش‌فرض true تا بنرهای موجود ظاهرشون یهو عوض نشه.
						$overlay_default = array_key_exists( 'overlay', $item ) ? ! empty( $item['overlay'] ) : true;
						?>
						<div class="jluxe-hb-banner-item">
							<strong>بنر <?php echo esc_html( jluxe_fa_digits( $b + 1 ) ); ?></strong>
							<label>تصویر دسکتاپ</label>
							<?php jluxe_render_media_field( "{$name}[items][{$b}][image_id]", (int) ( $item['image_id'] ?? 0 ), 'تصویری انتخاب نشده' ); ?>
							<label>نسخهٔ موبایل (اختیاری)</label>
							<?php jluxe_render_media_field( "{$name}[items][{$b}][mobile_image_id]", (int) ( $item['mobile_image_id'] ?? 0 ), 'تصویر موبایل انتخاب نشده' ); ?>
							<?php jluxe_hb_field_select( "{$name}[items][{$b}]", 'image_fit', 'ابعاد و برش تصویر', $item['image_fit'] ?? 'cover', array( 'cover' => 'پرکردن قاب (برش برای حفظ ظاهر فعلی)', 'contain' => 'نمایش کامل داخل قاب، بدون برش', 'original' => 'نسبت اصلی فایل ارسالی، بدون برش' ) ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$b}]", 'title', 'عنوان', $item['title'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$b}]", 'subtitle', 'زیرعنوان', $item['subtitle'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$b}]", 'button', 'متن دکمه', $item['button'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$b}]", 'link', 'لینک', $item['link'] ?? '' ); ?>
							<?php
							jluxe_hb_field_select(
								"{$name}[items][{$b}]",
								'content_position',
								'محل متن/دکمه روی تصویر',
								$item['content_position'] ?? 'bottom-start',
								array(
									'bottom-start'  => 'پایین - راست',
									'bottom-center' => 'پایین - وسط',
									'bottom-end'    => 'پایین - چپ',
									'center'        => 'وسط تصویر',
									'top-start'     => 'بالا - راست',
									'top-end'       => 'بالا - چپ',
								)
							);
							jluxe_hb_field_select(
								"{$name}[items][{$b}]",
								'button_style',
								'مدل دکمه',
								$item['button_style'] ?? 'solid',
								array(
									'solid'         => 'توپر (رنگی)',
									'white'         => 'سفید توپر',
									'outline-white' => 'خط‌دور سفید (شیشه‌ای)',
									'outline-primary' => 'خط‌دور رنگی روی زمینه‌ی سفید',
								)
							);
							jluxe_hb_field_color( "{$name}[items][{$b}]", 'text_color', 'رنگ متن', $item['text_color'] ?? '' );
							jluxe_hb_field_color( "{$name}[items][{$b}]", 'button_color', 'رنگ دکمه', $item['button_color'] ?? '' );
							$zoom_default = array_key_exists( 'zoom_enabled', $item ) ? ! empty( $item['zoom_enabled'] ) : true;
							$shine_default = array_key_exists( 'shine_enabled', $item ) ? ! empty( $item['shine_enabled'] ) : true;
							jluxe_hb_field_checkbox( "{$name}[items][{$b}]", 'overlay', 'هاله‌ی تیره روی تصویر (برای خوانا بودن متن سفید)', $overlay_default );
							jluxe_hb_field_checkbox( "{$name}[items][{$b}]", 'zoom_enabled', 'فعال‌سازی افکت بزرگنمایی نرم تصویر', $zoom_default );
							jluxe_hb_field_checkbox( "{$name}[items][{$b}]", 'shine_enabled', 'فعال‌سازی افکت براق (Shine) روی بنر', $shine_default );
							?>
						</div>
						<?php
					endfor;
					break;

				case 'banner_slider':
					jluxe_hb_field_text( $name, 'title', 'عنوان بخش (اختیاری)', $section['title'] ?? '' );
					echo '<p class="description">ابعاد پیشنهادی هر اسلاید: دسکتاپ <strong>۱۹۲۰ × ۶۰۰</strong>؛ موبایل <strong>۹۰۰ × ۱۲۰۰ (۳:۴)</strong>. نسخهٔ موبایل اختیاری است و با تگ picture جداگانه سرو می‌شود؛ بدون آن، srcset اندازهٔ متناسب را انتخاب می‌کند. اسلات‌های خالی نمایش داده نمی‌شوند. حداقل ۲ اسلاید پر برای فعال‌شدن چرخش خودکار لازم است.</p>';
					$items = $section['items'] ?? array();
					for ( $s = 0; $s < 5; $s++ ) :
						$item = $items[ $s ] ?? array();
						?>
						<div class="jluxe-hb-banner-item">
							<strong>اسلاید <?php echo esc_html( jluxe_fa_digits( $s + 1 ) ); ?></strong>
							<label>تصویر دسکتاپ</label>
							<?php jluxe_render_media_field( "{$name}[items][{$s}][image_id]", (int) ( $item['image_id'] ?? 0 ), 'تصویری انتخاب نشده' ); ?>
							<label>نسخهٔ موبایل (اختیاری)</label>
							<?php jluxe_render_media_field( "{$name}[items][{$s}][mobile_image_id]", (int) ( $item['mobile_image_id'] ?? 0 ), 'تصویر موبایل انتخاب نشده' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'category', 'برچسب دسته (مثل iPhone)', $item['category'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'title', 'عنوان', $item['title'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'description', 'توضیح', $item['description'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'link', 'لینک', $item['link'] ?? '' ); ?>
						</div>
						<?php
					endfor;
					break;

				case 'brick_products':
					$bp_toggle_id = 'brick-src-' . $section['id'];
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? 'پرفروش‌ترین‌ها' );
					?>
					<p>
						<label>منبع محصولات<br />
							<select name="<?php echo esc_attr( $name ); ?>[source]" data-jluxe-toggle-id="<?php echo esc_attr( $bp_toggle_id ); ?>">
								<?php foreach ( array( 'bestsellers' => 'پرفروش‌ترین‌ها (بر اساس فروش واقعی)', 'category' => 'یک دسته‌بندی مشخص', 'brand' => 'یک برند مشخص', 'manual' => 'لیست دستی محصولات' ) as $src_val => $src_label ) : ?>
									<option value="<?php echo esc_attr( $src_val ); ?>"<?php selected( $section['source'] ?? 'bestsellers', $src_val ); ?>><?php echo esc_html( $src_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>

					<div data-jluxe-show-if="<?php echo esc_attr( $bp_toggle_id ); ?>:category">
						<?php jluxe_hb_field_select( $name, 'category', 'دسته‌ی ووکامرس', (string) ( $section['category'] ?? 0 ), jluxe_hb_category_options() ); ?>
					</div>

					<div data-jluxe-show-if="<?php echo esc_attr( $bp_toggle_id ); ?>:brand">
						<?php
						$bp_brand_options = jluxe_hb_brand_options();
						jluxe_hb_field_select( $name, 'brand', 'برند', (string) ( $section['brand'] ?? 0 ), $bp_brand_options );
						if ( count( $bp_brand_options ) < 2 ) :
							?>
							<p class="description">هنوز هیچ برندی ثبت نشده — از Products → Brands یه برند بساز و به محصولات مربوطه اختصاص بده.</p>
						<?php endif; ?>
					</div>

					<div data-jluxe-show-if="<?php echo esc_attr( $bp_toggle_id ); ?>:manual">
						<p>
							<label>لیست محصولات (جستجو و انتخاب)<br />
								<select name="<?php echo esc_attr( $name ); ?>[product_ids][]" multiple="multiple" class="wc-product-search" style="width:100%" data-placeholder="جستجوی محصول…" data-action="woocommerce_json_search_products">
									<?php
									foreach ( $section['product_ids'] ?? array() as $bp_pid ) {
										$bp_product = wc_get_product( $bp_pid );
										if ( $bp_product ) {
											printf( '<option value="%d" selected="selected">%s</option>', $bp_pid, esc_html( $bp_product->get_name() ) );
										}
									}
									?>
								</select>
							</label>
						</p>
						<p class="description">ترتیب انتخاب همون ترتیب نمایشه. اگه بیشتر از ظرفیت گرید (ستون × ردیف) انتخاب کنی، فقط همون تعداد اول نشون داده می‌شه.</p>
					</div>

					<?php jluxe_hb_field_select( $name, 'hide_out_of_stock', 'ناموجودها', ( $section['hide_out_of_stock'] ?? false ) ? '1' : '0', array( '0' => 'نمایش بده', '1' => 'مخفی کن' ) ); ?>

					<p class="description">تعداد محصولِ نمایش‌داده‌شده خودکار از «ستون دسکتاپ × تعداد ردیف» محاسبه می‌شه — همیشه دقیقاً با چیدمان واقعی گرید یکیه.</p>
					<?php
					jluxe_hb_field_number( $name, 'columns_desktop', 'ستون دسکتاپ', $section['columns_desktop'] ?? 4, 2, 6 );
					jluxe_hb_field_number( $name, 'columns_tablet', 'ستون تبلت', $section['columns_tablet'] ?? 3, 2, 4 );
					jluxe_hb_field_number( $name, 'columns_mobile', 'ستون موبایل', $section['columns_mobile'] ?? 2, 1, 3 );
					jluxe_hb_field_number( $name, 'rows', 'تعداد ردیف', $section['rows'] ?? 2, 1, 4 );
					jluxe_hb_field_text( $name, 'view_all_link', 'لینک «نمایش همه» (اختیاری)', $section['view_all_link'] ?? '' );
					break;

				case 'special_products':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? 'فروش ویژه' );
					jluxe_hb_field_number( $name, 'count', 'تعداد محصول', $section['count'] ?? 10, 4, 30 );
					jluxe_hb_field_select(
						$name,
						'sort',
						'ترتیب نمایش',
						$section['sort'] ?? 'discount',
						array(
							'discount'   => 'بیشترین تخفیف',
							'latest'     => 'جدیدترین',
							'popularity' => 'محبوب‌ترین',
							'sales'      => 'پرفروش‌ترین',
							'price_low'  => 'ارزان‌ترین',
							'price_high' => 'گران‌ترین',
							'rating'     => 'بیشترین امتیاز',
							'rand'       => 'تصادفی',
						)
					);
					jluxe_hb_field_text( $name, 'view_all_link', 'لینک دکمه‌ی «نمایش همه» (خالی = صفحه‌ی فروشگاه، فیلترشده روی تخفیف‌دارها)', $section['view_all_link'] ?? '' );
					jluxe_hb_field_select( $name, 'hide_out_of_stock', 'ناموجودها', ( $section['hide_out_of_stock'] ?? true ) ? '1' : '0', array( '1' => 'مخفی کن', '0' => 'نمایش بده' ) );
					echo '<p class="description">فقط محصولاتی که واقعاً تخفیف‌خورده‌ن (get_product_ids_on_sale واقعیِ ووکامرس) نشون داده می‌شن — این بخش خودش به‌صورت خودکار و پیوسته اسکرول می‌شه، با نگه‌داشتنِ ماوس/لمس روش موقتاً می‌ایسته.</p>';
					break;

				case 'banner_collage':
					$bc_layouts        = jluxe_hb_collage_desktop_layouts();
					$bc_mobile_layouts = jluxe_hb_collage_mobile_layouts();
					$bc_desktop_key    = $section['desktop_layout'] ?? 'asymmetric_5';
					if ( ! isset( $bc_layouts[ $bc_desktop_key ] ) ) {
						$bc_desktop_key = 'asymmetric_5';
					}
					$bc_mobile_key = $section['mobile_layout'] ?? 'stacked';
					if ( ! isset( $bc_mobile_layouts[ $bc_mobile_key ] ) ) {
						$bc_mobile_key = 'stacked';
					}

					jluxe_hb_field_select(
						$name,
						'width_mode',
						'حالت عرض کلاژ',
						$section['width_mode'] ?? 'full',
						array(
							'full'      => 'تمام عرض صفحه',
							'boxed1100' => 'محدود به کانتینر (۱۱۰۰px)',
							'boxed1489' => 'محدود به کانتینر (۱۴۸۹px)',
						)
					);
					jluxe_hb_field_number( $name, 'radius', 'گردی گوشه‌های بیرونی کلاژ (px)', $section['radius'] ?? 14, 0, 40 );

					$bc_desktop_options = array();
					foreach ( $bc_layouts as $bc_key => $bc_def ) {
						$bc_desktop_options[ $bc_key ] = $bc_def['label'];
					}
					jluxe_hb_field_select( $name, 'desktop_layout', 'چیدمان — دسکتاپ', $bc_desktop_key, $bc_desktop_options );
					jluxe_hb_field_select( $name, 'mobile_layout', 'چیدمان — موبایل', $bc_mobile_key, $bc_mobile_layouts );
					?>
					<p class="description">چیدمانِ دسکتاپ و موبایل مستقل است. تصویرِ موبایل برای هر اسلات جدا انتخاب می‌شود و فقط در موبایل دانلود می‌شود؛ اگر خالی باشد، srcset نسخهٔ کوچک‌ترِ تصویر دسکتاپ را می‌دهد. برای چیدمانِ «دوتا-دوتا» تصویرِ مربعِ ۶۰۰ × ۶۰۰ و برای «پشت‌سرهم» تصویرِ افقیِ حدودِ ۹۰۰ × ۵۶۰ مناسب است. تصویر دسکتاپ را با نسبتِ کادرِ چیدمانِ انتخاب‌شده آماده کنید. اسلات‌های اضافه ذخیره می‌مانند، حتی اگر فعلاً در چیدمان نمایش داده نشوند.</p>

					<?php
					$bc_current_def = $bc_layouts[ $bc_desktop_key ];
					$bc_areas_css   = '"' . implode( '" "', $bc_current_def['areas'] ) . '"';
					?>
					<div
						class="jluxe-hb-collage-preview"
						data-jluxe-collage-preview
						data-collage-layouts="<?php echo esc_attr( wp_json_encode( $bc_layouts ) ); ?>"
						style="display:grid;grid-template-columns:<?php echo esc_attr( $bc_current_def['columns'] ); ?>;grid-template-rows:<?php echo esc_attr( $bc_current_def['rows'] ); ?>;grid-template-areas:<?php echo esc_attr( $bc_areas_css ); ?>;height:<?php echo esc_attr( $bc_current_def['height'] ); ?>;gap:4px;"
					>
						<?php
						for ( $bc = 0; $bc < 5; $bc++ ) :
							$slot         = $section['slots'][ $bc ] ?? array();
							$bc_slot_area = 's' . ( $bc + 1 );
							$bc_in_use    = ( $bc + 1 ) <= $bc_current_def['slot_count'];
							?>
							<div
								class="jxc-admin-slot"
								data-slot-index="<?php echo (int) $bc; ?>"
								style="grid-area:<?php echo esc_attr( $bc_slot_area ); ?>;<?php echo $bc_in_use ? '' : 'display:none;'; ?>"
							>
								<span class="jxc-admin-slot-num"><?php echo esc_html( jluxe_fa_digits( (string) ( $bc + 1 ) ) ); ?></span>
								<?php jluxe_render_media_field( "{$name}[slots][{$bc}][image_id]", (int) ( $slot['image_id'] ?? 0 ), 'بدون تصویر' ); ?>
							</div>
						<?php endfor; ?>
					</div>

					<?php
					for ( $bc = 0; $bc < 5; $bc++ ) :
						$slot = $section['slots'][ $bc ] ?? array();
						?>
						<div class="jluxe-hb-banner-item">
							<strong><?php echo esc_html( jluxe_hb_collage_slot_label( $bc ) ); ?></strong>
							<label>تصویر موبایل (اختیاری — فقط در موبایل دانلود می‌شود)</label>
							<?php jluxe_render_media_field( "{$name}[slots][{$bc}][mobile_image_id]", (int) ( $slot['mobile_image_id'] ?? 0 ), 'تصویر موبایل انتخاب نشده' ); ?>
							<?php jluxe_hb_field_text( "{$name}[slots][{$bc}]", 'link', 'لینک مقصد بنر (پس‌زمینه)', $slot['link'] ?? '' ); ?>
							<?php jluxe_hb_field_select( "{$name}[slots][{$bc}]", 'image_fit', 'نحوه نمایش عکس در کادر', $slot['image_fit'] ?? 'cover', array( 'cover' => 'پر کردن کامل کادر (ممکنه برش بخوره)', 'contain' => 'نمایش کامل عکس بدون برش' ) ); ?>
							<?php jluxe_hb_field_color( "{$name}[slots][{$bc}]", 'bg', 'رنگ پس‌زمینه (پشتِ عکس/قبل از لود شدنش)', $slot['bg'] ?? '' ); ?>
							<?php jluxe_hb_field_checkbox( "{$name}[slots][{$bc}]", 'shadow', 'سایه زیر این بنر فعال باشه', ! empty( $slot['shadow'] ) ); ?>

							<div style="margin-top:10px;margin-bottom:6px;font-size:12px;font-weight:bold;">نوشته‌های روی این بنر</div>
							<div class="jluxe-repeater" data-max="6">
								<div class="jluxe-repeater-list" data-group="layers">
									<?php
									foreach ( $slot['layers'] ?? array() as $li => $layer ) {
										jluxe_render_banner_collage_layer_fields( "{$name}[slots][{$bc}]", $li, $layer );
									}
									?>
								</div>
								<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_banner_collage_layer_fields( "{$name}[slots][{$bc}]", 0, array() ); ?></script>
								<button type="button" class="button jluxe-repeater-add">+ افزودن نوشته</button>
							</div>
						</div>
						<?php
					endfor;
					break;

				case 'recommended_panels':
					jluxe_hb_field_text( $name, 'title', 'عنوان بخش (اختیاری)', $section['title'] ?? '' );
					echo '<p class="description">هر پنل یک کارت مستقله که کنار هم نشون داده می‌شن (۳ تا در ردیف، در دسکتاپ). محصولات هر پنل از یک دسته‌ی واقعی کشیده می‌شن؛ پنل‌های بدون دسته یا بدون محصول نمایش داده نمی‌شن.</p>';
					$rp_cat_options = jluxe_hb_category_options();
					$panels         = $section['panels'] ?? array();
					for ( $p = 0; $p < 4; $p++ ) :
						$panel = $panels[ $p ] ?? array();
						?>
						<div class="jluxe-hb-banner-item">
							<strong>پنل <?php echo esc_html( jluxe_fa_digits( $p + 1 ) ); ?></strong>
							<?php jluxe_hb_field_text( "{$name}[panels][{$p}]", 'title', 'عنوان پنل (خالی = نام دسته)', $panel['title'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[panels][{$p}]", 'subtitle', 'زیرعنوان', $panel['subtitle'] ?? '' ); ?>
							<p>
								<label>دسته‌ی ووکامرس<br />
									<select name="<?php echo esc_attr( $name ); ?>[panels][<?php echo esc_attr( (string) $p ); ?>][category]" class="jluxe-select2-search">
										<?php foreach ( $rp_cat_options as $cat_id => $cat_label ) : ?>
											<option value="<?php echo esc_attr( (string) $cat_id ); ?>"<?php selected( (int) ( $panel['category'] ?? 0 ), (int) $cat_id ); ?>><?php echo esc_html( $cat_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							</p>
							<?php
							jluxe_hb_field_number( "{$name}[panels][{$p}]", 'count', 'تعداد محصول', $panel['count'] ?? 4, 2, 8 );
							jluxe_hb_field_select(
								"{$name}[panels][{$p}]",
								'sort',
								'ترتیب محصولات',
								$panel['sort'] ?? 'date',
								array(
									'date'       => 'جدیدترین',
									'rating'     => 'محبوب‌ترین (بر اساس امتیاز)',
									'popularity' => 'پرفروش‌ترین (بر اساس فروش)',
									'rand'       => 'تصادفی',
								)
							);
							echo '<p class="description">نامِ دسته خودش لینک توصیفی است؛ فلش فقط راهنمای دیداریِ همان لینک است و متن تکراری ندارد.</p>';
							jluxe_hb_field_text( "{$name}[panels][{$p}]", 'button_link', 'لینک عنوان و فلش (خالی = صفحه‌ی آرشیو دسته)', $panel['button_link'] ?? '' );
							jluxe_hb_field_color( "{$name}[panels][{$p}]", 'button_color', 'رنگ فلش', $panel['button_color'] ?? '' );
							?>
						</div>
						<?php
					endfor;
					break;

				case 'trust':
					echo '<p class="description">از همون کارت‌های مزیت فوتر استفاده می‌کنه — ویرایش در تنظیمات فوتر.</p>';
					jluxe_hb_field_text( $name, 'title', 'عنوان بخش', $section['title'] ?? '' );
					break;

				case 'text':
					jluxe_hb_field_text( $name, 'title', 'عنوان', $section['title'] ?? '' );
					?>
					<p><label>متن</label><br />
						<textarea name="<?php echo esc_attr( $name ); ?>[content]" rows="4" class="large-text"><?php echo esc_textarea( $section['content'] ?? '' ); ?></textarea>
					</p>
					<?php
					break;

				case 'html':
					?>
					<p><label>HTML</label><br />
						<textarea name="<?php echo esc_attr( $name ); ?>[content]" rows="6" class="large-text code" dir="ltr"><?php echo esc_textarea( $section['content'] ?? '' ); ?></textarea>
					</p>
					<?php
					break;

				case 'spacer':
					jluxe_hb_field_number( $name, 'height', 'ارتفاع (px)', $section['height'] ?? 40, 8, 200 );
					break;
			}
			?>
		</div>
		</details>
	</div>
	<?php
}

function jluxe_hb_field_text( string $prefix, string $key, string $label, string $value ): void {
	printf(
		'<p><label>%s<br /><input type="text" name="%s[%s]" value="%s" class="regular-text" /></label></p>',
		esc_html( $label ),
		esc_attr( $prefix ),
		esc_attr( $key ),
		esc_attr( $value )
	);
}

function jluxe_hb_field_number( string $prefix, string $key, string $label, $value, int $min, int $max ): void {
	printf(
		'<p><label>%s <input type="number" name="%s[%s]" value="%s" min="%d" max="%d" class="small-text" /></label></p>',
		esc_html( $label ),
		esc_attr( $prefix ),
		esc_attr( $key ),
		esc_attr( (string) $value ),
		$min,
		$max
	);
}

function jluxe_hb_field_color( string $prefix, string $key, string $label, string $value ): void {
	// نکته‌ی مهم: <input type="color"> همیشه یک مقدار می‌فرسته، حتی وقتی ادمین
	// دست نزده باشه — پس اگه اینجا یک هگزِ ثابت (مثلاً قرمزِ برندِ قدیم) رو
	// به‌عنوانِ fallback بذاریم، همون هگز با اولین ذخیره‌ی فرم (برای هر تغییرِ
	// دیگه‌ای، نه لزوماً همین فیلد) دائمی می‌شه و دیگه هیچ‌وقت با تغییرِ پالتِ
	// رنگیِ سایت هم‌رنگ نمی‌مونه (باگِ واقعیِ گزارش‌شده). به‌جاش رنگِ primary
	// زنده‌ی همین لحظه رو نشون می‌دیم — دستِ‌کم ذخیره‌های جدید با پالتِ فعلی هم‌خوان می‌مونن.
	$fallback = jluxe_get_theme_settings()['colors']['primary'] ?? '#B85C38';
	printf(
		'<p><label>%s<br /><input type="color" name="%s[%s]" value="%s" /></label></p>',
		esc_html( $label ),
		esc_attr( $prefix ),
		esc_attr( $key ),
		esc_attr( $value ?: $fallback )
	);
}

function jluxe_hb_field_checkbox( string $prefix, string $key, string $label, bool $checked ): void {
	printf(
		'<p><label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label></p>',
		esc_attr( $prefix ),
		esc_attr( $key ),
		checked( $checked, true, false ),
		esc_html( $label )
	);
}

function jluxe_hb_field_select( string $prefix, string $key, string $label, string $value, array $options ): void {
	printf( '<p><label>%s<br /><select name="%s[%s]">', esc_html( $label ), esc_attr( $prefix ), esc_attr( $key ) );
	foreach ( $options as $opt_value => $opt_label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $opt_value ), selected( $value, $opt_value, false ), esc_html( $opt_label ) );
	}
	echo '</select></label></p>';
}

/**
 * R91 — دکمهٔ «نمایش همه»ِ بخش‌های دسته‌بندی (قبلاً ثابت و غیرقابلِ‌ویرایش،
 * همیشه به فروشگاه). مقصد/متن/پنهان‌بودن حالا از همین‌جا تعیین می‌شود.
 */
function jluxe_render_category_view_all_fields( string $name, array $section ): void {
	$default_text = 'category_showcase' === ( $section['type'] ?? '' ) ? 'مشاهده همه' : 'نمایش همه';
	jluxe_hb_field_select(
		$name,
		'view_all_mode',
		'دکمهٔ «' . $default_text . '» به کجا برود؟',
		(string) ( $section['view_all_mode'] ?? 'categories_page' ),
		array(
			'categories_page' => 'برگهٔ همه دسته‌بندی‌ها (پیشنهادی)',
			'shop'            => 'صفحهٔ فروشگاه',
			'custom'          => 'لینکِ دلخواه',
			'hidden'          => 'دکمه نمایش داده نشود',
		)
	);
	jluxe_hb_field_text( $name, 'view_all_text', 'متنِ دکمه (خالی = «' . $default_text . '»)', (string) ( $section['view_all_text'] ?? '' ) );
	jluxe_hb_field_text( $name, 'view_all_link', 'لینکِ دلخواه (فقط برای گزینهٔ «لینکِ دلخواه»)', (string) ( $section['view_all_link'] ?? '' ) );
	$settings_url = admin_url( 'admin.php?page=jluxe-categories-page' );
	echo '<p class="description">چیدمان، آیکون‌ها و ترتیبِ دسته‌های برگهٔ «همه دسته‌بندی‌ها» در <a href="' . esc_url( $settings_url ) . '">زرین ← صفحهٔ دسته‌بندی‌ها</a> تنظیم می‌شود.</p>';
}

/**
 * لیست واقعیِ دسته‌های ووکامرس برای select بخش «دسته‌بندی‌های محصول» —
 * hide_empty=false عمداً، چون دقیقاً همون دسته‌های تازه‌ساخته‌شده‌ی بدون
 * محصول باید این‌جا قابل‌انتخاب باشن (ادمینه که تصمیم می‌گیره کدوم دسته
 * رو فیچر کنه، نه اینکه سیستم بر اساس «خالی/پر بودن» خودکار حذفشون کنه).
 */
function jluxe_hb_category_options(): array {
	$options = array( 0 => '— انتخاب دسته —' );
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $options;
	}
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return $options;
	}
	foreach ( $terms as $term ) {
		$options[ $term->term_id ] = $term->name . ' (' . $term->count . ' محصول)';
	}
	return $options;
}

/**
 * لیست واقعیِ برندها (تاکسونومیِ product_brand، ثبت‌شده در inc/woocommerce.php)
 * برای select منبعِ «بر اساس برند» در بخش «محصولات پرفروش». مثل دسته‌ها،
 * hide_empty=false — برند تازه‌ساخته‌شده‌ی بدون محصول هم قابل‌انتخابه.
 */
function jluxe_hb_brand_options(): array {
	$options = array( 0 => '— انتخاب برند —' );
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return $options;
	}
	$terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return $options;
	}
	foreach ( $terms as $term ) {
		$options[ $term->term_id ] = $term->name . ' (' . $term->count . ' محصول)';
	}
	return $options;
}

/**
 * یک ردیفِ repeater برای بخش «دسته‌بندی‌های محصول» — هم برای رندر ردیف‌های
 * موجود (SSR loop) و هم برای <script class="jluxe-repeater-template"> که
 * JS موقع کلیک «افزودن» کپی می‌کنه؛ برای همین یک تابع مشترکه، نه کد تکراری.
 */
function jluxe_render_category_grid_item_fields( string $name, int $index, array $item, array $cat_options ): void {
	?>
	<div class="jluxe-repeater-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>دسته‌بندی <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $index + 1 ) ); ?></span></span>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<div class="jluxe-repeater-row">
			<?php jluxe_hb_field_select( "{$name}[items][{$index}]", 'term_id', 'دسته‌ی ووکامرس', (string) ( $item['term_id'] ?? 0 ), $cat_options ); ?>
			<?php jluxe_hb_field_text( "{$name}[items][{$index}]", 'display_name', 'نام نمایشی (اختیاری)', (string) ( $item['display_name'] ?? '' ) ); ?>
			<div>
				<label>تصویر (اختیاری — خالی = تصویر خودِ دسته)</label>
				<?php jluxe_render_media_field( "{$name}[items][{$index}][image_id]", (int) ( $item['image_id'] ?? 0 ), 'از تصویر خودِ دسته استفاده می‌شه' ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * یک ردیف برای «دسته‌بندی‌های ویژه»: یا دستهٔ محصول با لینک خودکار،
 * یا تصویر دلخواه با لینک اختیاری. فرم عمداً فقط کنترل‌های لازم را نشان می‌دهد.
 */
function jluxe_render_category_showcase_item_fields( string $name, int $index, array $item, array $cat_options ): void {
	$prefix = "{$name}[items][{$index}]";
	$mode   = 'image' === ( $item['mode'] ?? '' ) ? 'image' : 'category';
	?>
	<div class="jluxe-repeater-item jluxe-category-showcase-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>آیتم <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $index + 1 ) ); ?></span></span>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<?php jluxe_hb_field_select( $prefix, 'mode', 'محتوای آیتم', $mode, array( 'category' => 'دسته‌بندی محصول', 'image' => 'تصویر دلخواه + لینک' ) ); ?>
		<div data-showcase-mode="category"<?php echo 'category' === $mode ? '' : ' style="display:none" aria-hidden="true"'; ?>>
			<?php jluxe_hb_field_select( $prefix, 'term_id', 'دستهٔ محصول', (string) ( $item['term_id'] ?? 0 ), $cat_options ); ?>
		</div>
		<div>
			<label>تصویر (برای تصویر دلخواه لازم؛ برای دسته اختیاری)</label>
			<?php jluxe_render_media_field( "{$prefix}[image_id]", (int) ( $item['image_id'] ?? 0 ), 'برای تصویر دلخواه، تصویری انتخاب کنید', 'medium', '90px', 'image' ); ?>
		</div>
		<?php jluxe_hb_field_text( $prefix, 'display_name', 'عنوان زیر تصویر (اختیاری)', (string) ( $item['display_name'] ?? '' ) ); ?>
		<div data-showcase-mode="image"<?php echo 'image' === $mode ? '' : ' style="display:none" aria-hidden="true"'; ?>>
			<?php jluxe_hb_field_text( $prefix, 'link', 'لینک مقصد (اختیاری)', (string) ( $item['link'] ?? '' ) ); ?>
		</div>
	</div>
	<?php
}

/**
 * یک ردیفِ repeater برای اسلاید هیرو — عکس دسکتاپ (اجباری) + عکس موبایل
 * (اختیاری، جدا) + نوشته/دکمه/محل/رنگ/لینک. هم برای ردیف‌های موجود (SSR
 * loop) و هم برای <script class="jluxe-repeater-template">.
 */
function jluxe_render_hero_item_fields( string $name, int $index, array $item ): void {
	?>
	<div class="jluxe-repeater-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>اسلاید <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $index + 1 ) ); ?></span></span>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<div class="jluxe-repeater-row">
			<div>
				<label>عکس دسکتاپ (اجباری)</label>
				<?php jluxe_render_media_field( "{$name}[items][{$index}][image_id]", (int) ( $item['image_id'] ?? 0 ), 'تصویری انتخاب نشده' ); ?>
			</div>
			<div>
				<label>عکس موبایل (اختیاری — پیشنهاد: ۹۰۰ × ۱۲۰۰؛ در موبایل فقط همین تصویر دانلود می‌شود)</label>
				<?php jluxe_render_media_field( "{$name}[items][{$index}][mobile_image_id]", (int) ( $item['mobile_image_id'] ?? 0 ), 'از عکس دسکتاپ استفاده می‌شه' ); ?>
			</div>
		</div>
		<?php jluxe_hb_field_text( "{$name}[items][{$index}]", 'title', 'عنوان (اختیاری)', $item['title'] ?? '' ); ?>
		<?php jluxe_hb_field_text( "{$name}[items][{$index}]", 'subtitle', 'زیرعنوان (اختیاری)', $item['subtitle'] ?? '' ); ?>
		<?php jluxe_hb_field_select( "{$name}[items][{$index}]", 'content_position', 'محل نوشته/دکمه روی اسلاید', $item['content_position'] ?? 'start', array( 'start' => 'راست', 'center' => 'وسط', 'end' => 'چپ' ) ); ?>
		<?php jluxe_hb_field_text( "{$name}[items][{$index}]", 'button', 'متن دکمه (اختیاری)', $item['button'] ?? '' ); ?>
		<?php jluxe_hb_field_color( "{$name}[items][{$index}]", 'button_color', 'رنگ دکمه', $item['button_color'] ?? '' ); ?>
		<?php jluxe_hb_field_text( "{$name}[items][{$index}]", 'link', 'لینک (اختیاری)', $item['link'] ?? '' ); ?>
	</div>
	<?php
}

/**
 * تعریفِ فرمت‌های چیدمانِ «کلاژ بنر» برای دسکتاپ — هرکدوم یک CSS Grid با
 * تعدادِ اسلات/ستون/ردیف/مساحتِ مخصوصِ خودشه. هم پیش‌نمایشِ ادمین
 * (data-jluxe-collage-preview پایین‌تر، با کلاسِ jxc-admin-{key}) و هم
 * رندرِ واقعیِ فرانت‌اند (jluxe_render_homepage_banner_collage) از همین
 * تعریف‌ها استفاده می‌کنن — عمداً یک منبعِ واحد، تا شکلِ ادمین و سایت
 * هیچ‌وقت با هم فرق نکنن.
 */
function jluxe_hb_collage_desktop_layouts(): array {
	return array(
		'asymmetric_5' => array(
			'label'      => 'نامتقارن (۵ اسلات) — ۲ مربعی + ۲ افقی + ۱ عمودی کناری',
			'slot_count' => 5,
			'columns'    => '1fr 1fr 1fr 1.3fr',
			'rows'       => '1fr 1fr',
			'areas'      => array( 's1 s2 s2 s5', 's3 s3 s4 s5' ),
			'height'     => '440px',
		),
		'grid_2'       => array(
			'label'      => '۲ ستون مساوی',
			'slot_count' => 2,
			'columns'    => '1fr 1fr',
			'rows'       => '1fr',
			'areas'      => array( 's1 s2' ),
			'height'     => '280px',
		),
		'grid_3'       => array(
			'label'      => '۳ ستون مساوی',
			'slot_count' => 3,
			'columns'    => '1fr 1fr 1fr',
			'rows'       => '1fr',
			'areas'      => array( 's1 s2 s3' ),
			'height'     => '260px',
		),
		'grid_4'       => array(
			'label'      => 'شبکه‌ی ۲×۲',
			'slot_count' => 4,
			'columns'    => '1fr 1fr',
			'rows'       => '1fr 1fr',
			'areas'      => array( 's1 s2', 's3 s4' ),
			'height'     => '420px',
		),
		'hero_split'   => array(
			'label'      => '۱ بزرگ + ۲ کوچکِ کناری',
			'slot_count' => 3,
			'columns'    => '2fr 1fr',
			'rows'       => '1fr 1fr',
			'areas'      => array( 's1 s2', 's1 s3' ),
			'height'     => '380px',
		),
		'stack_3'      => array(
			'label'      => '۱ بزرگِ بالا + ۲ کوچکِ پایین',
			'slot_count' => 3,
			'columns'    => '1fr 1fr',
			'rows'       => '1.6fr 1fr',
			'areas'      => array( 's1 s1', 's2 s3' ),
			'height'     => '420px',
		),
	);
}

/**
 * فرمت‌های چیدمانِ موبایل — عمداً مستقل از فرمتِ دسکتاپ (طبقِ درخواستِ
 * کاربر) و ساده‌تر، چون عرضِ موبایل برای چیدمان‌های چندستونیِ پیچیده جا
 * نداره. تعدادِ اسلاتِ واقعاً نمایش‌داده‌شده از خودِ desktop_layout میاد
 * (jluxe_render_homepage_banner_collage) — این‌جا فقط الگوی چیدمانه.
 */
function jluxe_hb_collage_mobile_layouts(): array {
	return array(
		'stacked' => 'همه زیرِ هم (تمام‌عرض)',
		'grid_2'  => 'دوتا-دوتا (شبکه‌ی دو‌ستونی)',
	);
}

/**
 * grid-template-columns/areas واقعیِ حالتِ موبایل — بسته به تعدادِ اسلاتِ
 * چیدمانِ دسکتاپِ انتخاب‌شده (که ممکنه ۲ تا ۵ باشه) دینامیک ساخته می‌شه؛
 * برای grid_2، اگه تعداد فرد باشه، آخرین اسلات کل عرض رو می‌گیره (بهتر از
 * نصفه‌کاره ولش کردن).
 */
function jluxe_hb_collage_mobile_grid_css( string $mobile_layout, int $slot_count ): array {
	if ( 'grid_2' === $mobile_layout ) {
		$areas = array();
		for ( $i = 0; $i < $slot_count; $i += 2 ) {
			$left  = 's' . ( $i + 1 );
			$right = ( $i + 1 < $slot_count ) ? 's' . ( $i + 2 ) : $left;
			$areas[] = "{$left} {$right}";
		}
		return array( 'columns' => '1fr 1fr', 'areas' => $areas );
	}

	$areas = array();
	for ( $i = 1; $i <= $slot_count; $i++ ) {
		$areas[] = 's' . $i;
	}
	return array( 'columns' => '1fr', 'areas' => $areas );
}

/**
 * برچسبِ فارسیِ هر یک از ۵ اسلاتِ ثابتِ «کلاژ بنر» — مطابق موقعیتِ واقعیشون
 * توی چیدمان (jluxe_render_homepage_banner_collage پایین‌تر): دو مربعیِ
 * کوچک، دو افقیِ بزرگ (همه داخلِ گریدِ اصلی)، و یک عمودیِ کناری.
 */
function jluxe_hb_collage_slot_label( int $index ): string {
	$labels = array(
		0 => 'بنر ۱ (مربعی کوچک)',
		1 => 'بنر ۲ (افقی بزرگ)',
		2 => 'بنر ۳ (افقی بزرگ)',
		3 => 'بنر ۴ (مربعی کوچک)',
		4 => 'بنر ۵ (عمودی کناری)',
	);
	return $labels[ $index ] ?? ( 'بنر ' . jluxe_fa_digits( (string) ( $index + 1 ) ) );
}

/**
 * یک ردیفِ repeater برای «نوشته‌ی روی عکس» در اسلاتِ کلاژ بنر — هم برای
 * ردیف‌های موجود (SSR loop) و هم برای <script class="jluxe-repeater-template">.
 * موقعیت/اندازه‌ی فونت برای دسکتاپ و موبایل کاملاً جدا ذخیره می‌شه، دقیقاً
 * طبق طرحِ مرجعی که کاربر فرستاد («این دو تا کاملاً مستقل از هم ذخیره میشن»).
 */
function jluxe_render_banner_collage_layer_fields( string $prefix, int $index, array $layer ): void {
	?>
	<div class="jluxe-repeater-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>نوشته <span class="jluxe-repeater-index"><?php echo esc_html( jluxe_fa_digits( $index + 1 ) ); ?></span></span>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<?php jluxe_hb_field_text( "{$prefix}[layers][{$index}]", 'text', 'متن (خالی بذاری، نمایش داده نمی‌شه)', $layer['text'] ?? '' ); ?>
		<?php jluxe_hb_field_text( "{$prefix}[layers][{$index}]", 'link', 'لینک مخصوص این نوشته (اختیاری)', $layer['link'] ?? '' ); ?>
		<div class="jluxe-repeater-row">
			<?php jluxe_hb_field_color( "{$prefix}[layers][{$index}]", 'color', 'رنگ نوشته', $layer['color'] ?? '' ); ?>
			<div>
				<?php jluxe_hb_field_checkbox( "{$prefix}[layers][{$index}]", 'bold', 'بولد', ! array_key_exists( 'bold', $layer ) || ! empty( $layer['bold'] ) ); ?>
				<?php jluxe_hb_field_checkbox( "{$prefix}[layers][{$index}]", 'as_button', 'حالت کپسول (پس‌زمینه‌ی سفید)', ! empty( $layer['as_button'] ) ); ?>
				<?php jluxe_hb_field_checkbox( "{$prefix}[layers][{$index}]", 'nowrap', 'بدون شکستن به دو خط', ! array_key_exists( 'nowrap', $layer ) || ! empty( $layer['nowrap'] ) ); ?>
				<?php jluxe_hb_field_checkbox( "{$prefix}[layers][{$index}]", 'hide_mobile', 'حذف کامل توی موبایل', ! empty( $layer['hide_mobile'] ) ); ?>
			</div>
		</div>
		<div class="jluxe-repeater-row">
			<div>
				<strong style="display:block;font-size:11px;margin-bottom:4px;">موقعیت/اندازه — دسکتاپ</strong>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'desktop_x', 'X (٪ از راست)', $layer['desktop_x'] ?? 50, 0, 100 ); ?>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'desktop_y', 'Y (٪ از بالا)', $layer['desktop_y'] ?? 82, 0, 100 ); ?>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'desktop_font_size', 'اندازه فونت (px)', $layer['desktop_font_size'] ?? 20, 10, 48 ); ?>
			</div>
			<div>
				<strong style="display:block;font-size:11px;margin-bottom:4px;">موقعیت/اندازه — موبایل</strong>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'mobile_x', 'X (٪ از راست)', $layer['mobile_x'] ?? 50, 0, 100 ); ?>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'mobile_y', 'Y (٪ از بالا)', $layer['mobile_y'] ?? 82, 0, 100 ); ?>
				<?php jluxe_hb_field_number( "{$prefix}[layers][{$index}]", 'mobile_font_size', 'اندازه فونت (px)', $layer['mobile_font_size'] ?? 18, 10, 48 ); ?>
			</div>
		</div>
	</div>
	<?php
}

// =====================================================================
// رندر واقعی روی frontend — front-page.php این تابع رو صدا می‌زنه.
// =====================================================================
function jluxe_render_homepage_sections(): void {
	$settings = jluxe_get_theme_settings();
	?>
	<style id="jluxe-homepage-system-style">
	.jluxe-home-section{box-sizing:border-box;width:calc(100% - 32px);max-width:var(--jluxe-container-max,1320px);margin:24px auto;padding-left:0;padding-right:0;}
	.jluxe-home-section h2{color:hsl(var(--foreground));}
	.jluxe-home-section .jluxe-home-card,.jluxe-home-section .jluxe-home-panel{transition:transform .24s cubic-bezier(.2,.7,.2,1),box-shadow .24s ease,border-color .24s ease;}
	.jluxe-home-section .jluxe-home-card:hover,.jluxe-home-section .jluxe-home-panel:hover{transform:translateY(-2px);}
	.jluxe-home-section .jluxe-home-media{transition:transform .42s cubic-bezier(.2,.7,.2,1),filter .25s ease;}
	.jluxe-home-section .jluxe-home-card:hover .jluxe-home-media{transform:scale(1.03);}
	@media(max-width:639px){.jluxe-home-section{width:calc(100% - 24px);margin:20px auto;}}
	@media(prefers-reduced-motion:reduce){.jluxe-home-section .jluxe-home-card,.jluxe-home-section .jluxe-home-panel,.jluxe-home-section .jluxe-home-media{transition:none!important;transform:none!important;}}
	</style>
	<?php
	foreach ( $settings['homepage']['sections'] as $section ) {
		if ( empty( $section['enabled'] ) ) {
			continue;
		}
		jluxe_render_homepage_section_frontend( $section );
	}
	if ( function_exists( 'jluxe_render_recent_products_panel' ) ) {
		jluxe_render_recent_products_panel( 'home' );
	}
}

/**
 * Load homepage behaviour only when an enabled section actually needs it.
 * Static/default homepages should not pay for the carousel/story scripts.
 */
function jluxe_enqueue_homepage_assets(): void {
	if ( ! is_front_page() ) {
		return;
	}

	$sections = jluxe_get_theme_settings()['homepage']['sections'] ?? array();
	if ( ! is_array( $sections ) ) {
		return;
	}

	$needs_homepage_script = false;
	$needs_hero_slider     = false;
	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) || empty( $section['enabled'] ) ) {
			continue;
		}

		$type = $section['type'] ?? '';
		if ( 'hero' === $type ) {
			$slides = array_filter(
				(array) ( $section['items'] ?? array() ),
				static fn( $item ): bool => is_array( $item ) && ! empty( $item['image_id'] )
			);
			$needs_hero_slider = $needs_hero_slider || count( $slides ) > 1;
		}

		if ( $needs_homepage_script ) {
			continue;
		}

		switch ( $type ) {
			case 'stories':
				$needs_homepage_script = (bool) array_filter(
					(array) ( $section['items'] ?? array() ),
					static fn( $item ): bool => is_array( $item ) && ! empty( $item['thumb_id'] ) && ! empty( $item['media_id'] )
				);
				break;

			case 'banner_slider':
				$slides = array_filter(
					(array) ( $section['items'] ?? array() ),
					static fn( $item ): bool => is_array( $item ) && ! empty( $item['image_id'] )
				);
				$needs_homepage_script = count( $slides ) > 1;
				break;

			case 'product_grid':
				$needs_homepage_script = 'carousel' === ( $section['layout'] ?? 'grid' );
				break;

			case 'special_products':
				$needs_homepage_script = true;
				break;

			case 'category_grid':
			case 'category_showcase':
				$needs_homepage_script = ! empty( $section['items'] );
				break;

			case 'recommended_panels':
				$needs_homepage_script = ! empty( $section['panels'] );
				break;

			case 'html':
				// Custom markup may use the carousel/story data attributes.
				$needs_homepage_script = ! empty( $section['content'] );
				break;
		}
	}

	if ( $needs_homepage_script ) {
		$path = JLUXE_THEME_DIR . '/assets/js/homepage.js';
		wp_enqueue_script(
			'jluxe-homepage',
			JLUXE_THEME_URI . '/assets/js/homepage.js',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : null,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	if ( $needs_hero_slider ) {
		// R89 — standalone hero driver; omit it for a single static slide.
		$hero_path = JLUXE_THEME_DIR . '/assets/js/hero-slider.js';
		wp_enqueue_script(
			'jluxe-hero-slider',
			JLUXE_THEME_URI . '/assets/js/hero-slider.js',
			array(),
			file_exists( $hero_path ) ? (string) filemtime( $hero_path ) : null,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_homepage_assets' );

function jluxe_render_homepage_section_frontend( array $section ): void {
	switch ( $section['type'] ?? '' ) {
		case 'hero':
			jluxe_render_homepage_hero( $section );
			break;

		case 'stories':
			jluxe_render_homepage_stories( $section );
			break;

		case 'product_grid':
			jluxe_render_homepage_product_grid( $section );
			break;

		case 'category_grid':
			jluxe_render_homepage_category_grid( $section );
			break;

		case 'category_showcase':
			jluxe_render_homepage_category_showcase( $section );
			break;

		case 'banner':
		case 'banner_two':
		case 'banner_three':
			jluxe_render_homepage_banners( $section );
			break;

		case 'banner_slider':
			jluxe_render_homepage_banner_slider( $section );
			break;

		case 'brick_products':
			jluxe_render_homepage_brick_products( $section );
			break;

		case 'special_products':
			jluxe_render_homepage_special_products( $section );
			break;

		case 'banner_collage':
			jluxe_render_homepage_banner_collage( $section );
			break;

		case 'recommended_panels':
			jluxe_render_homepage_recommended_panels( $section );
			break;

		case 'brand_marquee':
			jluxe_render_homepage_brand_marquee( $section );
			break;

		case 'trust':
			jluxe_render_homepage_trust( $section );
			break;

		case 'blog':
			jluxe_render_homepage_blog( $section );
			break;

		case 'text':
			if ( empty( $section['content'] ) ) {
				return;
			}
			echo '<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-8">';
			if ( ! empty( $section['title'] ) ) {
				echo '<h2 class="mb-3 text-h2 text-foreground">' . esc_html( $section['title'] ) . '</h2>';
			}
			echo '<div class="jluxe-product-description text-body text-text-secondary">' . wp_kses_post( $section['content'] ) . '</div>';
			echo '</section>';
			break;

		case 'html':
			// فقط manage_options می‌تونه این رو بنویسه (theme-settings-sanitize.php) —
			// دقیقاً هم‌سطح اعتماد Custom CSS/JS خودِ پنل.
			if ( ! empty( $section['content'] ) ) {
				echo '<section class="jluxe-custom-html">' . $section['content'] . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			break;

		case 'spacer':
			printf( '<div style="height:%dpx" aria-hidden="true"></div>', (int) ( $section['height'] ?? 40 ) );
			break;
	}
}

function jluxe_render_homepage_product_grid( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$args = array(
		'status'   => 'publish',
		'limit'    => (int) ( $section['count'] ?? 8 ),
		'orderby'  => 'date',
		'order'    => 'DESC',
	);

	switch ( $section['query'] ?? 'newest' ) {
		case 'sale':
			$args['include'] = wc_get_product_ids_on_sale();
			if ( empty( $args['include'] ) ) {
				return;
			}
			break;
		case 'bestseller':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['order']    = 'DESC';
			break;
		case 'featured':
			$args['featured'] = true;
			break;
	}

	if ( ! empty( $section['category'] ) ) {
		$args['category'] = array( sanitize_title( $section['category'] ) );
	}

	$products = wc_get_products( $args );
	if ( empty( $products ) ) {
		return;
	}

	$cols_d    = (int) ( $section['columns_desktop'] ?? 4 );
	$cols_t    = (int) ( $section['columns_tablet'] ?? 3 );
	$cols_m    = (int) ( $section['columns_mobile'] ?? 2 );
	$layout    = $section['layout'] ?? 'grid';
	$highlight = 'highlight' === ( $section['style'] ?? 'plain' );
	$is_carousel = 'carousel' === $layout;

	// عمداً تعداد ستون رو به تعداد واقعیِ محصولات محدود نمی‌کنیم: با
	// repeat(N,1fr) وقتی محصولات کمتر از N تا باشن، همون N تراک ساخته می‌شه
	// و آیتم‌های موجود فقط سلول‌های خودشون (1/N عرض) رو می‌گیرن — نه کل
	// عرض؛ محدودکردنِ N به تعداد محصولات (مثلاً با ۱ محصول → ۱ ستون) باعث
	// می‌شد همون یک کارت کل عرضِ کانتینر رو بگیره و غول‌پیکر بشه (باگ واقعی).
	$cols_d = max( 1, $cols_d );
	$cols_t = max( 1, $cols_t );
	$cols_m = max( 1, $cols_m );
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<div class="<?php echo $highlight ? 'rounded-2xl p-3 sm:p-4' : ''; ?>" <?php echo $highlight ? 'style="background:linear-gradient(hsl(var(--primary)) 0%, hsl(var(--primary-hover)) 45%, hsl(var(--primary-active)) 100%)"' : ''; ?>>
			<?php if ( ! empty( $section['title'] ) ) : ?>
				<div class="mb-3 flex items-center justify-between gap-2">
					<div>
						<h2 class="text-h2 <?php echo $highlight ? 'text-white' : 'text-foreground'; ?>"><?php echo esc_html( $section['title'] ); ?></h2>
						<?php if ( ! empty( $section['subtitle'] ) ) : ?>
							<p class="mt-1 text-small <?php echo $highlight ? 'text-white/80' : 'text-text-muted'; ?>"><?php echo esc_html( $section['subtitle'] ); ?></p>
						<?php endif; ?>
					</div>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="shrink-0 rounded-lg <?php echo $highlight ? 'bg-white text-primary' : 'border border-border text-foreground'; ?> px-3 py-1.5 text-caption font-medium">مشاهده همه</a>
				</div>
			<?php endif; ?>

			<?php if ( $is_carousel ) : ?>
				<div class="relative">
					<div data-jluxe-scroller<?php echo jluxe_carousel_autoscroll_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="jluxe-scroll-x jluxe-hide-scrollbar flex gap-4 overflow-x-auto scroll-smooth pb-2">
						<?php
						foreach ( $products as $product ) {
							$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
							/*
						 * lg (دسکتاپ) طبقِ اندازه‌گیریِ زنده از صفحه‌ی فروشگاه به w-[300px]
						 * عریض شده تا کارتِ محصولِ کروسلِ صفحه‌ی اصلی هم‌اندازه‌ی کارتِ
						 * صفحه‌ی فروشگاه (grid ستون‌بندیِ ۴تایی، ~304px در 1440px) بشه —
						 * طبقِ درخواستِ صریحِ کاربر. موبایل (w-[160px]) و تبلت
						 * (sm:w-[190px]) دست‌نخورده مونده، چون کاربر گفته اونا از قبل
						 * درستن.
						 */
						echo '<div class="w-[160px] shrink-0 sm:w-[190px] lg:w-[300px]">';
							wc_get_template_part( 'content', 'product' );
							echo '</div>';
						}
						wp_reset_postdata();
						?>
					</div>
					<?php jluxe_scroll_arrows(); ?>
				</div>
			<?php else : ?>
				<div class="jluxe-pg-<?php echo esc_attr( $section['id'] ); ?> grid gap-4" style="grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_m ); ?>,1fr)">
					<style>
						@media(min-width:640px){.jluxe-pg-<?php echo esc_attr( $section['id'] ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_t ); ?>,1fr)!important}}
						@media(min-width:1024px){.jluxe-pg-<?php echo esc_attr( $section['id'] ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_d ); ?>,1fr)!important}}
					</style>
					<?php
					foreach ( $products as $product ) {
						$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						wc_get_template_part( 'content', 'product' );
					}
					wp_reset_postdata();
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * کاروسلِ «محصولات فروش ویژه» — همون کارتِ واقعیِ محصولِ سایت
 * (content-product.php، همون‌جوری که در برچ فروشگاه/محصولاتِ مرتبط استفاده
 * می‌شه)، فقط منبعش محصولاتِ واقعاً تخفیف‌خورده‌ست
 * (wc_get_product_ids_on_sale واقعیِ ووکامرس، نه یک لیستِ دستی). ساختارِ
 * اسکرولر دقیقاً همونِ data-jluxe-scroller مشترکه (drag با ماوس + فلش‌ها
 * از assets/js/homepage.js خودکار روش کار می‌کنن)؛ data-jluxe-autoscroll
 * فقط پخشِ خودکار/مداومِ اضافه‌ای رو فعال می‌کنه که با نگه‌داشتنِ ماوس/لمس
 * موقتاً می‌ایسته.
 */
function jluxe_sale_grid_query_args( array $args, array $query_vars ): array {
	if ( ! empty( $query_vars['jluxe_sale_grid'] ) ) {
		$args['has_password'] = false;
		$args['jluxe_discount_order'] = ! empty( $query_vars['jluxe_discount_order'] );
		$args['jluxe_sale_price_order'] = $query_vars['jluxe_sale_price_order'] ?? '';
		$args['jluxe_discount_instock'] = 'instock' === ( $query_vars['stock_status'] ?? '' );
	}
	return $args;
}
add_filter( 'woocommerce_product_data_store_cpt_get_products_query', 'jluxe_sale_grid_query_args', 10, 2 );

/** Standard WooCommerce CPT/lookup store: highest active simple/variation discount first. */
function jluxe_discount_orderby( string $orderby, $query ): string {
	global $wpdb;
	$price_order = $query->get( 'jluxe_sale_price_order' );
	if ( in_array( $price_order, array( 'low', 'high' ), true ) ) {
		$field = 'low' === $price_order ? 'min_price' : 'max_price';
		$order = 'low' === $price_order ? 'ASC' : 'DESC';
		return "(SELECT $field FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = {$wpdb->posts}.ID) $order, {$wpdb->posts}.ID DESC";
	}
	if ( ! $query->get( 'jluxe_discount_order' ) ) { return $orderby; }
	$stock_clause = $query->get( 'jluxe_discount_instock' ) ? " AND jluxe_lookup.stock_status = 'instock'" : '';
	// Only identifiers from wpdb and literal clauses are interpolated. No request values enter SQL.
	return "COALESCE((SELECT MAX(100 - 100 * jluxe_lookup.min_price / NULLIF(CAST(jluxe_regular.meta_value AS DECIMAL(20,6)), 0))
		FROM {$wpdb->posts} AS jluxe_sale
		INNER JOIN {$wpdb->wc_product_meta_lookup} AS jluxe_lookup ON jluxe_lookup.product_id = jluxe_sale.ID AND jluxe_lookup.onsale = 1
		INNER JOIN {$wpdb->postmeta} AS jluxe_regular ON jluxe_regular.post_id = jluxe_sale.ID AND jluxe_regular.meta_key = '_regular_price'
		WHERE (jluxe_sale.ID = {$wpdb->posts}.ID OR jluxe_sale.post_parent = {$wpdb->posts}.ID)
		AND jluxe_sale.post_status = 'publish' AND CAST(jluxe_regular.meta_value AS DECIMAL(20,6)) > 0
		$stock_clause), 0) DESC, {$wpdb->posts}.ID DESC";
}
add_filter( 'posts_orderby', 'jluxe_discount_orderby', 50, 2 );

function jluxe_render_homepage_special_products( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$sale_ids = wc_get_product_ids_on_sale();
	if ( empty( $sale_ids ) ) {
		return;
	}

	$count             = max( 4, min( 30, (int) ( $section['count'] ?? 10 ) ) );
	$sort              = $section['sort'] ?? 'discount';
	$hide_out_of_stock = ! empty( $section['hide_out_of_stock'] );

	$args = array(
		'status'  => 'publish',
		'include' => $sale_ids,
		'limit'   => $count,
		'visibility' => 'catalog',
		'jluxe_sale_grid' => true,
	);
	if ( $hide_out_of_stock ) {
		$args['stock_status'] = 'instock';
	}

	switch ( $sort ) {
		case 'latest':
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		case 'popularity':
		case 'sales':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['order']    = 'DESC';
			break;
		case 'price_low':
			$args['jluxe_sale_price_order'] = 'low';
			$args['orderby'] = 'date';
			$args['order']   = 'ASC';
			break;
		case 'price_high':
			$args['jluxe_sale_price_order'] = 'high';
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		case 'rating':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['order']    = 'DESC';
			break;
		case 'rand':
			$args['orderby'] = 'rand';
			break;
		case 'discount':
		default:
			$args['jluxe_discount_order'] = true;
			$args['orderby'] = 'date';
			break;
	}

	// The database sorts BEFORE LIMIT; never instantiate every sale product just to slice it.
	$products = array_values( array_filter( wc_get_products( $args ), 'jluxe_product_is_public' ) );
	if ( empty( $products ) ) {
		return;
	}

	$view_all_link = ! empty( $section['view_all_link'] )
		? $section['view_all_link']
		: add_query_arg( 'on_sale', '1', wc_get_page_permalink( 'shop' ) );
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<div class="mb-3 flex items-center justify-between gap-2">
			<h2 class="text-h2 text-foreground"><?php echo esc_html( $section['title'] ?? 'فروش ویژه' ); ?></h2>
			<a href="<?php echo esc_url( $view_all_link ); ?>" class="shrink-0 rounded-lg border border-border px-3 py-1.5 text-caption font-medium text-foreground">مشاهده همه</a>
		</div>
		<div class="relative">
			<div data-jluxe-scroller data-jluxe-autoscroll class="jluxe-scroll-x jluxe-hide-scrollbar flex gap-4 overflow-x-auto scroll-smooth pb-2">
				<?php
				foreach ( $products as $product ) {
					$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					/*
						 * lg (دسکتاپ) طبقِ اندازه‌گیریِ زنده از صفحه‌ی فروشگاه به w-[300px]
						 * عریض شده تا کارتِ محصولِ کروسلِ صفحه‌ی اصلی هم‌اندازه‌ی کارتِ
						 * صفحه‌ی فروشگاه (grid ستون‌بندیِ ۴تایی، ~304px در 1440px) بشه —
						 * طبقِ درخواستِ صریحِ کاربر. موبایل (w-[160px]) و تبلت
						 * (sm:w-[190px]) دست‌نخورده مونده، چون کاربر گفته اونا از قبل
						 * درستن.
						 */
						echo '<div class="w-[160px] shrink-0 sm:w-[190px] lg:w-[300px]">';
					wc_get_template_part( 'content', 'product' );
					echo '</div>';
				}
				wp_reset_postdata();
				?>
			</div>
			<?php jluxe_scroll_arrows(); ?>
		</div>
	</section>
	<?php
}

/**
 * درصدِ تخفیفِ واقعیِ یک محصول (ساده یا متغیر — برای متغیر، بازه‌ی
 * min/max قیمت رو در نظر می‌گیره)، برای مرتب‌سازیِ «بیشترین تخفیف» در
 * jluxe_render_homepage_special_products بالا.
 */
function jluxe_product_discount_percent( WC_Product $product ): float {
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();
	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return 0.0;
	}
	return round( 100 - ( $sale / $regular ) * 100, 2 );
}

/**
 * «کلاژ بنر» — چند فرمتِ چیدمانِ قابل‌انتخاب (jluxe_hb_collage_desktop_layouts)
 * که هرکدوم دقیقاً یک CSS Grid با ستون/ردیف/مساحتِ خودشه؛ چیدمانِ موبایل
 * کاملاً مستقل از دسکتاپه (jluxe_hb_collage_mobile_layouts + محاسبه‌ی
 * grid موبایل با jluxe_hb_collage_mobile_grid_css بر اساسِ تعدادِ واقعیِ
 * اسلاتِ چیدمانِ دسکتاپِ انتخاب‌شده). موقعیت/اندازه‌ی نوشته‌های روی هر
 * بنر همچنان کاملاً جدا برای دسکتاپ/موبایل ذخیره می‌شه. ریسپانسیو شدن با
 * CSS Container Query (نه media query) چون شکستنِ چیدمان باید بر اساسِ
 * عرضِ خودِ کلاژ باشه، نه عرضِ کل صفحه.
 */
function jluxe_render_homepage_banner_collage( array $section ): void {
	$slots = $section['slots'] ?? array();
	$has_any_image = false;
	foreach ( $slots as $slot ) {
		if ( ! empty( $slot['image_id'] ) ) {
			$has_any_image = true;
			break;
		}
	}
	if ( ! $has_any_image ) {
		return;
	}

	$desktop_layouts = jluxe_hb_collage_desktop_layouts();
	$desktop_key     = $section['desktop_layout'] ?? 'asymmetric_5';
	if ( ! isset( $desktop_layouts[ $desktop_key ] ) ) {
		$desktop_key = 'asymmetric_5';
	}
	$desktop_def = $desktop_layouts[ $desktop_key ];
	$slot_count  = $desktop_def['slot_count'];

	$mobile_layouts = jluxe_hb_collage_mobile_layouts();
	$mobile_key     = $section['mobile_layout'] ?? 'stacked';
	if ( ! isset( $mobile_layouts[ $mobile_key ] ) ) {
		$mobile_key = 'stacked';
	}
	$mobile_grid        = jluxe_hb_collage_mobile_grid_css( $mobile_key, $slot_count );
	$mobile_slot_aspect = 'grid_2' === $mobile_key ? '1/1' : '16/10';

	$id            = 'jxc-' . preg_replace( '/[^a-z0-9]/', '', (string) ( $section['id'] ?? '' ) );
	$radius        = max( 0, min( 40, (int) ( $section['radius'] ?? 14 ) ) );
	$radius_mobile = (int) round( $radius * 0.7 );

	$width_style = 'width:100%;';
	if ( 'boxed1100' === ( $section['width_mode'] ?? 'full' ) ) {
		$width_style = 'max-width:1100px;margin:0 auto;';
	} elseif ( 'boxed1489' === ( $section['width_mode'] ?? 'full' ) ) {
		$width_style = 'max-width:1489px;margin:0 auto;';
	}

	$mobile_css        = '';
	$hide_mobile_used  = false;
	for ( $i = 0; $i < $slot_count; $i++ ) {
		$slot = $slots[ $i ] ?? array();
		foreach ( $slot['layers'] ?? array() as $li => $layer ) {
			if ( empty( $layer['text'] ) ) {
				continue;
			}
			if ( ! empty( $layer['hide_mobile'] ) ) {
				$hide_mobile_used = true;
			}
			$mobile_css .= sprintf(
				'.%1$s-txt%2$d-%3$d{left:%4$d%%!important;top:%5$d%%!important;font-size:%6$dpx!important;}',
				esc_attr( $id ),
				$i,
				(int) $li,
				max( 0, min( 100, (int) ( $layer['mobile_x'] ?? 50 ) ) ),
				max( 0, min( 100, (int) ( $layer['mobile_y'] ?? 82 ) ) ),
				max( 10, min( 48, (int) ( $layer['mobile_font_size'] ?? 18 ) ) )
			);
		}
	}

	$desktop_areas_css = '"' . implode( '" "', $desktop_def['areas'] ) . '"';
	$mobile_areas_css  = '"' . implode( '" "', $mobile_grid['areas'] ) . '"';
	?>
	<?php
	/*
	 * توجهِ مهم: مقادیرِ columns/rows/areas/height/width_style/mobile_slot_aspect
	 * پایین‌تر عمداً esc_html() نمی‌شن — همیشه از آرایه‌ی هاردکدِ خودِ
	 * jluxe_hb_collage_desktop_layouts()/jluxe_hb_collage_mobile_layouts()
	 * (نه از ورودیِ آزادِ ادمین) میان، پس متنِ کاملاً قابل‌اعتمادن. باگِ
	 * واقعیِ گزارش‌شده («کلاژ کلاً نشون نمی‌ده»): esc_html() نقل‌قول‌های
	 * grid-template-areas (مثلاً "s1 s2") رو به &quot;s1 s2&quot; تبدیل
	 * می‌کرد — که به‌عنوان CSS خام (نه HTML) بی‌معنی و نامعتبره، پس کل
	 * grid-template-areas عملاً نادیده گرفته می‌شد و چیدمان کلاً می‌شکست.
	 */
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<style>
			.<?php echo esc_html( $id ); ?>-ctx{container-type:inline-size;<?php echo $width_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>}
			.<?php echo esc_html( $id ); ?>-wrap{
				display:grid;
				grid-template-columns:<?php echo $desktop_def['columns']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				grid-template-rows:<?php echo $desktop_def['rows']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				grid-template-areas:<?php echo $desktop_areas_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				height:<?php echo $desktop_def['height']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				border-radius:<?php echo (int) $radius; ?>px;
				overflow:hidden;
			}
			.<?php echo esc_html( $id ); ?>-slot{position:relative;}
			@container (max-width:640px){
				.<?php echo esc_html( $id ); ?>-wrap{
					grid-template-columns:<?php echo $mobile_grid['columns']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
					grid-template-rows:none;
					grid-template-areas:<?php echo $mobile_areas_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
					height:auto;
					border-radius:<?php echo (int) $radius_mobile; ?>px;
				}
				.<?php echo esc_html( $id ); ?>-slot{aspect-ratio:<?php echo $mobile_slot_aspect; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;}
				<?php if ( $hide_mobile_used ) : ?>
				.<?php echo esc_html( $id ); ?>-hide-mobile{display:none!important;}
				<?php endif; ?>
				<?php echo $mobile_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- مقادیرِ داخلش بالاتر با absint/min/max ساخته شدن. ?>
			}
		</style>
		<div class="<?php echo esc_html( $id ); ?>-ctx"><div class="<?php echo esc_html( $id ); ?>-wrap">
			<?php for ( $i = 0; $i < $slot_count; $i++ ) : ?>
				<?php
				$mobile_slot_is_half = 'grid_2' === $mobile_key && !( 1 === ( $slot_count % 2 ) && $i === $slot_count - 1 );
				$slot_mobile_sizes = $mobile_slot_is_half ? 'calc((100vw - 24px) / 2)' : 'calc(100vw - 24px)';
				?>
				<div class="<?php echo esc_html( $id ); ?>-slot" style="grid-area:s<?php echo (int) ( $i + 1 ); ?>">
					<?php jluxe_render_collage_slot( $slots[ $i ] ?? array(), $id, $i, $slot_mobile_sizes ); ?>
				</div>
			<?php endfor; ?>
		</div></div>
	</section>
	<?php
}

/**
 * محتوایِ داخلِ یک سلولِ کلاژ بنر: پس‌زمینه (عکس/رنگ، با یا بدون لینک) +
 * لایه‌های نوشته‌ی روش. کلاسِ jxc-imgN برای override زوم/برشِ عکس در
 * موبایل استفاده می‌شد در ابزارِ مرجع؛ چون این نسخه زوم/موقعیتِ جداگانه
 * برای هر دستگاه نداره (بالاتر توضیح داده شد)، اینجا فقط object-fit
 * ثابت اعمال می‌شه.
 */
function jluxe_render_collage_slot( array $slot, string $id, int $slot_index, string $mobile_sizes = 'calc(100vw - 24px)' ): void {
	$image_id = (int) ( $slot['image_id'] ?? 0 );
	$desktop_sizes = '(max-width: 639px) ' . $mobile_sizes . ', 50vw';
	$image = $image_id ? jluxe_get_responsive_attachment_image( $image_id, 'jluxe-uncropped-1280', $desktop_sizes ) : array( 'src' => '', 'srcset' => '', 'sizes' => $desktop_sizes );
	$mobile_id = ! empty( $slot['mobile_image_id'] ) ? absint( $slot['mobile_image_id'] ) : 0;
	$mobile_image = $mobile_id && $mobile_id !== $image_id
		? jluxe_get_responsive_attachment_image( $mobile_id, 'jluxe-uncropped-960', $mobile_sizes )
		: $image;
	$has_mobile_image = $mobile_id && $mobile_id !== $image_id && ! empty( $mobile_image['src'] );
	if ( ! $has_mobile_image ) {
		$mobile_image = $image;
	}
	$img_url  = $image['src'];
	$fit      = 'contain' === ( $slot['image_fit'] ?? 'cover' ) ? 'contain' : 'cover';
	$bg       = ! empty( $slot['bg'] ) ? $slot['bg'] : 'hsl(var(--muted))';
	$shadow   = ! empty( $slot['shadow'] ) ? 'box-shadow:0 8px 24px rgba(0,0,0,.15);' : '';
	$link     = ! empty( $slot['link'] ) ? esc_url( jluxe_resolve_site_link( (string) $slot['link'] ) ) : '';
	?>
	<div style="position:relative;width:100%;height:100%;background:<?php echo esc_attr( $bg ); ?>;overflow:hidden;<?php echo esc_attr( $shadow ); ?>">
		<?php if ( $link ) : ?>
			<a href="<?php echo $link; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>" style="display:block;width:100%;height:100%;text-decoration:none;position:absolute;inset:0;">
					<?php if ( $img_url ) : ?>
						<picture style="display:block;width:100%;height:100%;">
							<?php jluxe_homepage_mobile_picture_source( $mobile_image, (bool) $has_mobile_image, $mobile_sizes ); ?>
							<img src="<?php echo esc_url( $img_url ); ?>"<?php echo jluxe_responsive_image_attributes( $image ); ?> alt="" style="width:100%;height:100%;object-fit:<?php echo esc_attr( $fit ); ?>;display:block;" <?php echo jluxe_lazy_attr(); ?> />
						</picture>
					<?php endif; ?>
			</a>
			<?php elseif ( $img_url ) : ?>
				<div style="position:absolute;inset:0;">
					<picture style="display:block;width:100%;height:100%;">
						<?php jluxe_homepage_mobile_picture_source( $mobile_image, (bool) $has_mobile_image, $mobile_sizes ); ?>
						<img src="<?php echo esc_url( $img_url ); ?>"<?php echo jluxe_responsive_image_attributes( $image ); ?> alt="" style="width:100%;height:100%;object-fit:<?php echo esc_attr( $fit ); ?>;display:block;" <?php echo jluxe_lazy_attr(); ?> />
					</picture>
				</div>
		<?php endif; ?>

		<?php foreach ( $slot['layers'] ?? array() as $li => $layer ) :
			if ( empty( $layer['text'] ) ) {
				continue;
			}
			$weight    = array_key_exists( 'bold', $layer ) && empty( $layer['bold'] ) ? 'normal' : 'bold';
			$nowrap    = array_key_exists( 'nowrap', $layer ) && empty( $layer['nowrap'] );
			$wrap_style = $nowrap ? 'white-space:normal;max-width:85%;' : 'white-space:nowrap;';
			$btn_style  = ! empty( $layer['as_button'] ) ? 'background:#fff;padding:6px 16px;border-radius:20px;' : '';
			$color      = ! empty( $layer['color'] ) ? $layer['color'] : '#2c2a26';
			$x          = max( 0, min( 100, (int) ( $layer['desktop_x'] ?? 50 ) ) );
			$y          = max( 0, min( 100, (int) ( $layer['desktop_y'] ?? 82 ) ) );
			$fs         = max( 10, min( 48, (int) ( $layer['desktop_font_size'] ?? 20 ) ) );
			$style      = "position:absolute;left:{$x}%;top:{$y}%;transform:translate(-50%,-50%);display:inline-block;font-size:{$fs}px;font-weight:{$weight};color:{$color};line-height:1.5;{$wrap_style}{$btn_style}";
			$css_class  = esc_attr( $id ) . '-txt' . $slot_index . '-' . (int) $li . ( ! empty( $layer['hide_mobile'] ) ? ' ' . esc_attr( $id ) . '-hide-mobile' : '' );
			$has_link   = ! empty( $layer['link'] );
			$tag        = $has_link ? 'a' : 'div';
			?>
			<<?php echo esc_html( $tag ); ?><?php echo $has_link ? ' href="' . esc_url( jluxe_resolve_site_link( (string) $layer['link'] ) ) . '"' : ''; ?> class="<?php echo esc_attr( $css_class ); ?>" style="<?php echo esc_attr( $style . ( $has_link ? 'text-decoration:none;' : '' ) ); ?>"><?php echo esc_html( $layer['text'] ); ?></<?php echo esc_html( $tag ); ?>>
		<?php endforeach; ?>
	</div>
	<?php
}


/**
 * بخش‌های ذخیره‌شدهٔ قدیمی، فیلدهایِ جدیدتر (مثل image_shape/alignment)
 * را ندارند؛ خواندنِ دوبارهٔ کلید بعد از گاردِ ?? باعثِ Warning می‌شد
 * (باگِ واقعی در صفحهٔ اصلی). یک‌بار با default می‌خوانیم و همان مقدار
 * اعتبارسنجی‌شده را برمی‌گردانیم — بدون هیچ Warning در E_ALL.
 */
function jluxe_homepage_section_choice( array $section, string $key, array $allowed, string $fallback ): string {
	$value = (string) ( $section[ $key ] ?? '' );
	return in_array( $value, $allowed, true ) ? $value : $fallback;
}

function jluxe_render_homepage_category_showcase( array $section ): void {
	$is_woocommerce_active = class_exists( 'WooCommerce' );
	$desktop_layout = jluxe_homepage_section_choice( $section, 'layout', array( 'grid', 'row' ), 'grid' );
	$mobile_layout = jluxe_homepage_section_choice( $section, 'mobile_layout', array( 'grid', 'row' ), 'grid' );
	$mobile_columns = max( 2, min( 6, (int) ( $section['mobile_columns'] ?? 3 ) ) );
	$mobile_gap_total = 8 * ( $mobile_columns - 1 );
	$mobile_card_width = 'calc((100% - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ')';
	$mobile_image_size = 'min(96px,max(48px,calc((100vw - 32px - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ' - 16px)))';
	$image_size = 'jluxe-uncropped-1280';
	$image_sizes = '(max-width: 639px) ' . $mobile_image_size . ', 120px';
	$rows = array();

	foreach ( $section['items'] ?? array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$mode = 'image' === ( $item['mode'] ?? '' ) ? 'image' : 'category';
		$term = null;
		$term_id = (int) ( $item['term_id'] ?? 0 );
		$image_id = (int) ( $item['image_id'] ?? 0 );
		$display_name = trim( (string) ( $item['display_name'] ?? '' ) );
		$link = '';

		if ( 'category' === $mode ) {
			if ( ! $is_woocommerce_active || ! $term_id ) {
				continue;
			}
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			if ( ! $image_id ) {
				$image_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) || ! $link ) {
				continue;
			}
			$display_name = $display_name ?: (string) $term->name;
		} else {
			if ( ! $image_id ) {
				continue;
			}
			$link = isset( $item['link'] ) ? trim( (string) $item['link'] ) : '';
		}

		$image_data = $image_id ? jluxe_get_responsive_attachment_image( $image_id, $image_size, $image_sizes ) : array();
		$img_url = ! empty( $image_data['src'] ) ? $image_data['src'] : '';
		if ( '' === $img_url && 'category' === $mode && function_exists( 'wc_placeholder_img_src' ) ) {
			$img_url = wc_placeholder_img_src( 'thumbnail' );
		}
		if ( '' === $img_url ) {
			continue;
		}
		$rows[] = array(
			'link'       => $link,
			'img_url'    => $img_url,
			'img_srcset' => (string) ( $image_data['srcset'] ?? '' ),
			'img_sizes'  => (string) ( $image_data['sizes'] ?? $image_sizes ),
			'img_width'  => (int) ( $image_data['width'] ?? 0 ) ?: 120,
			'img_height' => (int) ( $image_data['height'] ?? 0 ) ?: 120,
			'display_name' => $display_name,
		);
	}

	if ( empty( $rows ) ) {
		return;
	}

	$shape = jluxe_homepage_section_choice( $section, 'image_shape', array( 'circle', 'square', 'none' ), 'none' );
	$frame_mode = jluxe_homepage_section_choice( $section, 'image_frame_mode', array( 'none', 'transparent', 'color' ), 'none' );
	$frame_width = 'none' === $frame_mode ? '0px' : '1px';
	$frame_color = 'color' === $frame_mode ? ( sanitize_hex_color( $section['image_frame_color'] ?? '' ) ?: '#D7D7D7' ) : 'transparent';
	$image_radius = 'circle' === $shape ? '9999px' : ( 'square' === $shape ? '14px' : '0px' );
	$text_color = sanitize_hex_color( $section['card_text_color'] ?? '' ) ?: '#303936';
	$show_names = ! array_key_exists( 'show_category_names', $section ) || ! empty( $section['show_category_names'] );
	$grid_class = 'row' === $desktop_layout ? 'jluxe-category-showcase-row' : 'jluxe-category-showcase-grid';
	$show_arrows = 'row' === $desktop_layout || 'row' === $mobile_layout;
	$container_style = '--jluxe-showcase-image-radius:' . $image_radius . ';--jluxe-showcase-image-frame-width:' . $frame_width . ';--jluxe-showcase-image-frame-color:' . $frame_color . ';--jluxe-showcase-card-text:' . $text_color . ';--jluxe-mobile-columns:' . $mobile_columns . ';--jluxe-mobile-card-width:' . $mobile_card_width . ';--jluxe-mobile-image-size:' . $mobile_image_size . ';';
	?>
	<section class="jluxe-home-section jluxe-category-showcase jluxe-category-showcase-v2<?php echo $show_names ? '' : ' jluxe-category-showcase-image-only'; ?>" style="<?php echo esc_attr( $container_style ); ?>" dir="rtl" aria-label="دسته‌بندی‌های ویژه">
		<div class="jluxe-category-showcase-scroll-wrap" data-mobile-layout="<?php echo esc_attr( $mobile_layout ); ?>">
			<div class="<?php echo esc_attr( $grid_class ); ?>" data-jluxe-scroller>
				<?php foreach ( $rows as $row ) : ?>
					<?php if ( $row['link'] ) : ?>
						<a href="<?php echo esc_url( $row['link'] ); ?>" class="jluxe-category-showcase-card"<?php echo $row['display_name'] ? ' aria-label="' . esc_attr( $row['display_name'] ) . '"' : ' aria-label="تصویر"'; ?>>
					<?php else : ?>
						<div class="jluxe-category-showcase-card">
					<?php endif; ?>
						<span class="jluxe-category-showcase-image"><img loading="lazy" decoding="async" width="<?php echo esc_attr( $row['img_width'] ); ?>" height="<?php echo esc_attr( $row['img_height'] ); ?>" src="<?php echo esc_url( $row['img_url'] ); ?>"<?php if ( $row['img_srcset'] ) : ?> srcset="<?php echo esc_attr( $row['img_srcset'] ); ?>" sizes="<?php echo esc_attr( $row['img_sizes'] ); ?>"<?php endif; ?> alt="" aria-hidden="true" /></span>
						<?php if ( $show_names && $row['display_name'] ) : ?><span class="jluxe-category-showcase-name"><?php echo esc_html( $row['display_name'] ); ?></span><?php endif; ?>
					<?php if ( $row['link'] ) : ?>
						</a>
					<?php else : ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<?php if ( $show_arrows ) jluxe_scroll_arrows(); ?>
		</div>
	</section>
	<style>
	.jluxe-category-showcase-v2{box-sizing:border-box;position:relative;isolation:isolate;width:calc(100% - 32px);max-width:var(--jluxe-container-max,1320px);margin:24px auto 32px;padding:0;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;color:inherit}
	.jluxe-category-showcase-scroll-wrap{position:relative;min-width:0}
	.jluxe-category-showcase-grid{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:center;gap:16px}
	.jluxe-category-showcase-row{display:flex;flex-wrap:nowrap;align-items:flex-start;justify-content:flex-start;gap:12px;overflow-x:auto;overflow-y:hidden;padding:2px 42px 8px;scroll-behavior:smooth;scrollbar-width:none}
	.jluxe-category-showcase-row::-webkit-scrollbar{display:none}.jluxe-category-showcase-row.is-centered{justify-content:center}.jluxe-category-showcase-row.is-overflowing{justify-content:flex-start}
	.jluxe-category-showcase-card{box-sizing:border-box;display:flex!important;flex:0 0 152px;flex-direction:column;align-items:center;justify-content:flex-start;width:152px;min-width:0;padding:0;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:inherit!important;text-align:center!important;text-decoration:none!important;overflow:visible}
	.jluxe-category-showcase-image{box-sizing:border-box;display:flex!important;flex:0 0 120px;align-items:center;justify-content:center;width:120px!important;height:120px!important;overflow:hidden;border:var(--jluxe-showcase-image-frame-width,0px) solid var(--jluxe-showcase-image-frame-color,transparent);border-radius:var(--jluxe-showcase-image-radius,0px)!important;background:transparent!important;padding:0!important}
	.jluxe-category-showcase-image img{display:block!important;flex:0 0 auto;width:100%!important;height:100%!important;max-width:100%;max-height:100%;margin:0!important;padding:0!important;border:0!important;border-radius:inherit!important;object-fit:contain!important;box-shadow:none!important}
	.jluxe-category-showcase-name{display:block!important;width:100%;margin-top:8px;padding:0 3px;font-size:13px!important;line-height:1.65!important;font-weight:600!important;color:var(--jluxe-showcase-card-text,#303936)!important;white-space:normal;overflow-wrap:anywhere}
	.jluxe-category-showcase-scroll-wrap>[data-jluxe-scroll-prev],.jluxe-category-showcase-scroll-wrap>[data-jluxe-scroll-next]{background:transparent!important;border-color:transparent!important;box-shadow:none!important;color:inherit!important}
	@media(max-width:639px){.jluxe-category-showcase-v2{width:calc(100% - 16px);margin:20px auto 26px}.jluxe-category-showcase-grid{gap:12px 8px}.jluxe-category-showcase-row{gap:8px;padding:2px 36px 8px}.jluxe-category-showcase-card{flex-basis:var(--jluxe-mobile-card-width);width:var(--jluxe-mobile-card-width);min-width:0}.jluxe-category-showcase-image{flex-basis:var(--jluxe-mobile-image-size);width:var(--jluxe-mobile-image-size)!important;height:var(--jluxe-mobile-image-size)!important}.jluxe-category-showcase-name{margin-top:6px;font-size:12px!important}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-grid,.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-row{flex-wrap:nowrap;justify-content:flex-start;overflow-x:auto;overflow-y:hidden;padding:2px 36px 8px}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] [data-jluxe-scroller].is-centered{justify-content:center}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] [data-jluxe-scroller].is-overflowing{justify-content:flex-start}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-card{flex:0 0 calc(var(--jluxe-mobile-image-size) + 16px);width:calc(var(--jluxe-mobile-image-size) + 16px);min-width:calc(var(--jluxe-mobile-image-size) + 16px)}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-grid,.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-row{display:flex;flex-wrap:wrap;justify-content:center;gap:12px 8px;overflow:visible;padding:2px 0 8px}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-card{flex:0 0 var(--jluxe-mobile-card-width);width:var(--jluxe-mobile-card-width);min-width:0}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"]>[data-jluxe-scroll-prev],.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"]>[data-jluxe-scroll-next]{display:none}}
	@media(prefers-reduced-motion:reduce){.jluxe-category-showcase-row{scroll-behavior:auto}}
	</style>
	<?php
}

function jluxe_render_homepage_category_grid( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$rows = array();
	foreach ( $section['items'] ?? array() as $item ) {
		$term_id = (int) ( $item['term_id'] ?? 0 );
		if ( ! $term_id ) {
			continue;
		}
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		$image_id = (int) ( $item['image_id'] ?? 0 );
		if ( ! $image_id ) {
			$image_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
		}
		$img_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
		$img_srcset = $image_id ? wp_get_attachment_image_srcset( $image_id, 'thumbnail' ) : '';
		$display    = trim( (string) ( $item['display_name'] ?? '' ) );
		$rows[]     = array(
			'term'         => $term,
			'img_url'      => $img_url,
			'img_srcset'   => $img_srcset,
			'display_name' => $display ?: $term->name,
		);
	}
	if ( empty( $rows ) ) {
		return;
	}

	$title       = trim( (string) ( $section['title'] ?? '' ) ) ?: 'دسته‌بندی‌های محبوب';
	// این نسخه از کامپوننت مرجع همیشه یک ردیف افقی است؛ layout قدیمی عمداً نادیده گرفته می‌شود.
	$layout      = 'row';
	$shape       = jluxe_homepage_section_choice( $section, 'image_shape', array( 'circle', 'square', 'none' ), 'none' );
	$alignment   = jluxe_homepage_section_choice( $section, 'alignment', array( 'start', 'center', 'end' ), 'center' );
	$section_rad = max( 0, min( 56, (int) ( $section['section_radius'] ?? 40 ) ) );
	$card_rad    = max( 0, min( 40, (int) ( $section['card_radius'] ?? 20 ) ) );
	$image_size  = (int) ( $section['image_size'] ?? 80 );
	// 146px was the legacy value for the old category component; keep the
	// reference component at 80px unless the administrator explicitly chose another size.
	if ( 146 === $image_size ) {
		$image_size = 80;
	}
	$image_size = max( 48, min( 140, $image_size ) );
	$image_bg   = 'transparent' === ( $section['image_bg_mode'] ?? 'transparent' ) ? 'transparent' : ( sanitize_hex_color( $section['image_bg_color'] ?? '' ) ?: '#FFFFFF' );
	$shadow_map = array(
		'none'   => 'none',
		'soft'   => '0 4px 16px rgba(16,24,40,.06)',
		'medium' => '0 8px 24px rgba(16,24,40,.09)',
		'strong' => '0 14px 34px rgba(16,24,40,.13)',
	);
	$shadow      = $shadow_map[ $section['card_shadow'] ?? 'soft' ] ?? $shadow_map['soft'];
	$lift        = array_key_exists( 'hover_lift', $section ) ? ! empty( $section['hover_lift'] ) : false;
	$zoom        = array_key_exists( 'zoom_enabled', $section ) ? ! empty( $section['zoom_enabled'] ) : false;
	$align_css   = 'start' === $alignment ? 'flex-start' : ( 'end' === $alignment ? 'flex-end' : 'center' );
	$text_css    = 'start' === $alignment ? 'right' : ( 'end' === $alignment ? 'left' : 'center' );
	$cards_alignment = jluxe_homepage_section_choice( $section, 'cards_alignment', array( 'start', 'center', 'end' ), 'center' );
	$cards_justify = 'start' === $cards_alignment ? 'flex-start' : ( 'end' === $cards_alignment ? 'flex-end' : 'center' );
	$mobile_layout = jluxe_homepage_section_choice( $section, 'mobile_layout', array( 'row', 'grid' ), 'row' );
	$mobile_columns = max( 2, min( 6, (int) ( $section['mobile_columns'] ?? 3 ) ) );
	$mobile_gap_total = 8 * ( $mobile_columns - 1 );
	$mobile_card_width = 'calc((100% - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ')';
	$mobile_image_size = 'min(var(--jluxe-cat-image-size),calc((100vw - 32px - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ' - 16px),72px)';
	$img_radius  = 'circle' === $shape ? '9999px' : ( 'square' === $shape ? $card_rad . 'px' : '0px' );
	$card_width  = max( 120, min( 180, $image_size + 66 ) );
	$card_height = max( 116, min( 180, $image_size + 66 ) );
	$style       = sprintf(
		'--jluxe-cat-section-radius:%dpx;--jluxe-cat-card-radius:%dpx;--jluxe-cat-image-size:%dpx;--jluxe-cat-image-bg:%s;--jluxe-cat-image-radius:%s;--jluxe-cat-shadow:%s;--jluxe-cat-align:%s;--jluxe-cat-text-align:%s;--jluxe-cat-card-width:%dpx;--jluxe-cat-card-height:%dpx;--jluxe-cat-cards-justify:%s;--jluxe-cat-mobile-card-width:%s;--jluxe-cat-mobile-image-size:%s;',
		$section_rad,
		$card_rad,
		$image_size,
		$image_bg,
		$img_radius,
		$shadow,
		$align_css,
		$text_css,
		$card_width,
		$card_height,
		$cards_justify,
		$mobile_card_width,
		$mobile_image_size
	);
	$view_all = jluxe_category_section_view_all( $section );
	?>
	<section class="jluxe-category-grid-ref jluxe-home-section" style="<?php echo esc_attr( $style ); ?>" dir="rtl" aria-label="<?php echo esc_attr( $title ); ?>">
		<div class="jluxe-category-grid-ref-head">
			<div class="jluxe-category-grid-ref-heading">
				<div class="jluxe-category-grid-ref-icon" aria-hidden="true">
					<?php
					$default_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M10 6a4 4 0 1 0-8 0a4 4 0 0 0 8 0Zm0 12a4 4 0 1 0-8 0a4 4 0 0 0 8 0ZM22 6a4 4 0 1 0-8 0a4 4 0 0 0 8 0Zm0 12a4 4 0 1 0-8 0a4 4 0 0 0 8 0Z"></path></svg>';
					$icon_svg = ! empty( $section['icon_svg'] ) ? jluxe_sanitize_svg_markup( (string) $section['icon_svg'] ) : '';
					if ( '' === $icon_svg ) { $icon_svg = $default_icon; }
					echo $icon_svg;
					?>
				</div>
				<div class="jluxe-category-grid-ref-title-wrap">
					<h2><?php echo esc_html( $title ); ?></h2>
					<?php if ( $view_all ) : ?><a href="<?php echo esc_url( $view_all['url'] ); ?>" class="jluxe-category-grid-ref-all" aria-label="<?php echo esc_attr( $view_all['label'] ); ?>"><?php echo esc_html( $view_all['text'] ); ?></a><?php endif; ?>
				</div>
			</div>
		</div>
		<div class="jluxe-category-grid-ref-carousel relative" data-mobile-layout="<?php echo esc_attr( $mobile_layout ); ?>">
			<button type="button" class="jluxe-category-grid-ref-arrow is-prev" data-jluxe-scroll-prev aria-label="اسکرول به چپ"><svg xmlns="http://www.w3.org/2000/svg" width="26" viewBox="0 0 24 24"><path fill="currentColor" d="M14 17.308L8.692 12L14 6.692l.708.708l-4.6 4.6l4.6 4.6z"></path></svg></button>
			<div class="jluxe-category-grid-ref-scroll <?php echo 'grid' === $layout ? 'is-grid' : 'is-row'; ?>" data-jluxe-scroller>
				<?php foreach ( $rows as $row ) : ?>
					<a href="<?php echo esc_url( get_term_link( $row['term'] ) ); ?>" class="jluxe-category-grid-ref-item<?php echo $lift ? ' is-lift' : ''; ?>" aria-label="<?php echo esc_attr( 'مشاهده دسته‌بندی ' . $row['display_name'] ); ?>" title="<?php echo esc_attr( $row['display_name'] ); ?>">
						<span class="jluxe-home-card jluxe-category-grid-ref-card">
							<span class="jluxe-category-grid-ref-image"><img class="<?php echo $zoom ? 'is-zoom' : ''; ?>" src="<?php echo esc_url( $row['img_url'] ); ?>"<?php if ( $row['img_srcset'] ) : ?> srcset="<?php echo esc_attr( $row['img_srcset'] ); ?>" sizes="<?php echo esc_attr( '(max-width: 767px) ' . min( $image_size, 60 ) . 'px, ' . $image_size . 'px' ); ?>"<?php endif; ?> alt="" aria-hidden="true" <?php echo jluxe_lazy_attr(); ?> /></span>
							<span class="jluxe-category-grid-ref-name"><?php echo esc_html( $row['display_name'] ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
			<button type="button" class="jluxe-category-grid-ref-arrow is-next" data-jluxe-scroll-next aria-label="اسکرول به راست"><svg class="is-rotated" xmlns="http://www.w3.org/2000/svg" width="26" viewBox="0 0 24 24"><path fill="currentColor" d="M14 17.308L8.692 12L14 6.692l.708.708l-4.6 4.6l4.6 4.6z"></path></svg></button>
		</div>
	</section>
	<style>
	/* Keep the category group centered when it fits; JS switches overflow rows back to a safe scroll start. */
	.jluxe-category-grid-ref{box-sizing:border-box;width:calc(100% - 48px);max-width:var(--jluxe-container-max,1320px);margin:24px auto;background:transparent;border:1px solid #e6e6e8;border-radius:var(--jluxe-cat-section-radius,40px);color:hsl(var(--foreground));padding:40px 0 0;display:flex;flex-direction:column;justify-content:center;overflow:visible}
	.jluxe-category-grid-ref-head{padding:0 40px 0 0}.jluxe-category-grid-ref-heading{display:flex;align-items:center;justify-content:flex-start;gap:16px;color:hsl(var(--foreground))}.jluxe-category-grid-ref-icon{display:flex;align-items:center;justify-content:center;flex:0 0 48px;width:48px;height:48px;background:#fff;border:1px solid #e6e6e8;border-radius:12px;color:hsl(var(--primary));font-size:22px;line-height:1}.jluxe-category-grid-ref-title-wrap{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:4px}.jluxe-category-grid-ref-title-wrap h2{margin:0 0 0 32px;font-size:30px;line-height:1.25;letter-spacing:-.065em;font-weight:800;color:hsl(var(--foreground))}.jluxe-category-grid-ref-all{font-size:12px;line-height:1.5;color:hsl(var(--text-muted));text-decoration:none;letter-spacing:-.02em}
	.jluxe-category-grid-ref-carousel{position:relative;display:flex;align-items:center;justify-content:center;min-width:0;width:100%;overflow:visible}.jluxe-category-grid-ref-scroll{display:flex!important;flex-direction:row;align-items:center;justify-content:flex-start;gap:12px;width:100%;max-width:100%;min-width:0;overflow-x:auto;overflow-y:visible;scrollbar-width:none;scroll-behavior:smooth;padding:40px 4px 40px 16px}.jluxe-category-grid-ref-scroll.is-centered{justify-content:var(--jluxe-cat-cards-justify)}.jluxe-category-grid-ref-scroll.is-overflowing{justify-content:flex-start}.jluxe-category-grid-ref-scroll::-webkit-scrollbar{display:none}.jluxe-category-grid-ref-scroll.is-grid{display:flex!important;flex-wrap:nowrap;overflow-x:auto;overflow-y:visible;padding:40px 4px 40px 16px}.jluxe-category-grid-ref-item{display:block;flex:0 0 var(--jluxe-cat-card-width);width:var(--jluxe-cat-card-width);text-decoration:none;color:inherit;cursor:pointer}.jluxe-category-grid-ref-scroll.is-grid .jluxe-category-grid-ref-item{width:var(--jluxe-cat-card-width);min-width:var(--jluxe-cat-card-width);flex:0 0 var(--jluxe-cat-card-width)}.jluxe-category-grid-ref-card{box-sizing:border-box;display:flex;flex-direction:column;gap:14px;align-items:var(--jluxe-cat-align);justify-content:center;width:var(--jluxe-cat-card-width);height:var(--jluxe-cat-card-height);background:#fff;border:1px solid #e6e6e8;border-radius:var(--jluxe-cat-card-radius);overflow:visible;box-shadow:var(--jluxe-cat-shadow);transition:transform .24s cubic-bezier(.2,.7,.2,1),border-color .2s ease,box-shadow .24s ease}.jluxe-category-grid-ref-item.is-lift:hover .jluxe-category-grid-ref-card{transform:translateY(-3px);border-color:rgba(181,138,58,.28)}.jluxe-category-grid-ref-image{display:flex;align-items:center;justify-content:center;width:var(--jluxe-cat-image-size);height:var(--jluxe-cat-image-size);flex:0 0 var(--jluxe-cat-image-size);overflow:hidden;background:var(--jluxe-cat-image-bg);border-radius:var(--jluxe-cat-image-radius)}.jluxe-category-grid-ref-image img{display:block;width:100%;height:100%;object-fit:contain;border:0!important;outline:0!important;box-shadow:none!important;border-radius:inherit;transition:transform .42s cubic-bezier(.2,.7,.2,1);transform-origin:center}.jluxe-category-grid-ref-image img.is-zoom{transform:scale(1)}.jluxe-category-grid-ref-item:hover .jluxe-category-grid-ref-image img.is-zoom{transform:scale(1.045)}.jluxe-category-grid-ref-name{display:block;width:calc(100% - 16px);font-size:13px;line-height:1.55;text-align:var(--jluxe-cat-text-align);color:hsl(var(--foreground));white-space:normal;overflow-wrap:anywhere}
	.jluxe-category-grid-ref-arrow{display:flex;position:absolute;top:50%;z-index:10;width:38px;height:76px;align-items:center;justify-content:center;transform:translateY(-50%);padding:0 8px;border:0;border-radius:12px;background:rgba(255,255,255,.88);color:hsl(var(--foreground));box-shadow:0 5px 18px rgba(0,0,0,.08);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);cursor:pointer;transition:background .2s ease,opacity .2s ease}.jluxe-category-grid-ref-arrow:hover{background:#fff}.jluxe-category-grid-ref-arrow.is-prev{left:-24px}.jluxe-category-grid-ref-arrow.is-next{right:-24px}.jluxe-category-grid-ref-arrow .is-rotated{transform:rotate(180deg)}
	@media(max-width:767px){.jluxe-category-grid-ref{max-width:none;margin:20px 0;width:100%;border-left:0;border-right:0;border-radius:0;padding-top:32px}.jluxe-category-grid-ref-head{padding:0 12px}.jluxe-category-grid-ref-heading{gap:12px}.jluxe-category-grid-ref-title-wrap h2{font-size:18px;margin-left:0;letter-spacing:-.04em}.jluxe-category-grid-ref-all{font-size:11px}.jluxe-category-grid-ref-scroll{gap:8px;padding:32px 40px}.jluxe-category-grid-ref-item{flex-basis:120px;width:120px}.jluxe-category-grid-ref-card{width:120px;height:116px;gap:10px;border-radius:var(--jluxe-cat-card-radius,20px)}.jluxe-category-grid-ref-image{width:min(var(--jluxe-cat-image-size),60px);height:min(var(--jluxe-cat-image-size),60px);flex-basis:min(var(--jluxe-cat-image-size),60px)}.jluxe-category-grid-ref-name{font-size:12px;width:calc(100% - 10px)}.jluxe-category-grid-ref-arrow{top:50%;width:34px;height:48px;border-radius:12px}.jluxe-category-grid-ref-arrow.is-prev{left:4px}.jluxe-category-grid-ref-arrow.is-next{right:4px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-scroll{display:flex!important;flex-wrap:wrap;justify-content:var(--jluxe-cat-cards-justify);gap:8px;overflow:visible;padding:12px 6px 20px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-item,.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-scroll.is-grid .jluxe-category-grid-ref-item{flex:0 0 var(--jluxe-cat-mobile-card-width);width:var(--jluxe-cat-mobile-card-width);min-width:0}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-card{width:100%;height:auto;min-height:100px;gap:8px;padding:8px 4px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-image{width:var(--jluxe-cat-mobile-image-size);height:var(--jluxe-cat-mobile-image-size);min-width:0;flex-basis:var(--jluxe-cat-mobile-image-size)}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-name{width:100%;font-size:clamp(10px,3vw,12px);line-height:1.45}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-arrow{display:none}}
	@media(prefers-reduced-motion:reduce){.jluxe-category-grid-ref-card,.jluxe-category-grid-ref-image img{transition:none!important}}
	</style>
	<?php
}

function jluxe_render_homepage_brand_marquee( array $section ): void {
	$items = array_values( array_filter( $section['items'] ?? array(), fn( $i ) => ! empty( $i['image_id'] ) ) );
	if ( empty( $items ) ) return;
	$duration = max( 15, min( 120, (int) ( $section['speed_sec'] ?? 36 ) ) );
	$pause = ! empty( $section['pause_hover'] );
	$gray = array_key_exists( 'grayscale', $section ) ? ! empty( $section['grayscale'] ) : true;
	$classes = 'jluxe-brand-marquee-track' . ( $pause ? ' jluxe-brand-marquee-pause' : '' );
	?>
	<section class="jluxe-home-section jluxe-brand-strip" dir="rtl" aria-label="<?php echo esc_attr( $section['title'] ?? 'برندهای منتخب' ); ?>">
		<div class="jluxe-brand-strip-inner">
			<?php if ( ! empty( $section['title'] ) ) : ?><span class="jluxe-brand-strip-title"><?php echo esc_html( $section['title'] ); ?></span><?php endif; ?>
			<div class="jluxe-brand-strip-viewport">
				<div class="<?php echo esc_attr( $classes ); ?>" style="--jluxe-brand-speed:<?php echo esc_attr( $duration ); ?>s;">
					<?php for ( $copy = 0; $copy < 2; $copy++ ) foreach ( $items as $item ) :
						$logo_image = jluxe_get_responsive_attachment_image( (int) $item['image_id'], 'jluxe-uncropped-320', '(max-width: 639px) 78px, 96px' );
						if ( empty( $logo_image['src'] ) ) {
							continue;
						}
						$url = ! empty( $item['link'] ) ? $item['link'] : '';
					?>
						<div class="jluxe-brand-strip-item">
							<?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" class="jluxe-brand-strip-link" aria-label="<?php echo esc_attr( $item['title'] ?? 'برند' ); ?>"><?php endif; ?>
								<span class="jluxe-brand-strip-logo"><img src="<?php echo esc_url( $logo_image['src'] ); ?>"<?php echo jluxe_responsive_image_attributes( $logo_image ); ?> alt="<?php echo esc_attr( $item['title'] ?? '' ); ?>" loading="lazy" class="<?php echo $gray ? 'is-gray' : ''; ?>" /></span>
							<?php if ( $url ) : ?></a><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<style>
	.jluxe-brand-strip{box-sizing:border-box;width:calc(100% - 32px);max-width:var(--jluxe-container-max,1320px);margin:24px auto;padding:0;border:1px solid rgba(28,35,32,.07);border-radius:16px;background:rgba(255,255,255,.72);overflow:hidden}.jluxe-brand-strip-inner{box-sizing:border-box;width:100%;height:64px;min-height:64px;display:flex;align-items:center;gap:16px;padding:5px 16px}.jluxe-brand-strip-title{flex:0 0 auto;max-width:120px;font-size:11px;font-weight:800;color:#6b726e;white-space:nowrap}.jluxe-brand-strip-viewport{min-width:0;flex:1;overflow:hidden}.jluxe-brand-marquee-track{display:flex;align-items:center;width:max-content;height:54px;animation:jluxe-brand-marquee var(--jluxe-brand-speed) linear infinite}.jluxe-brand-marquee-pause:hover{animation-play-state:paused}.jluxe-brand-strip-item{box-sizing:border-box;width:126px;min-width:126px;height:54px;display:flex;align-items:center;justify-content:center;padding:0 7px}.jluxe-brand-strip-link{display:flex;align-items:center;justify-content:center;width:100%;height:100%}.jluxe-brand-strip-logo{width:96px;height:40px;display:flex;align-items:center;justify-content:center}.jluxe-brand-strip-logo img{display:block;width:auto!important;height:auto!important;max-width:96px!important;max-height:40px!important;object-fit:contain!important;object-position:50% 50%!important;transition:filter .25s ease,opacity .25s ease}.jluxe-brand-strip-logo img.is-gray{filter:grayscale(1);opacity:.62}.jluxe-brand-strip-link:hover img.is-gray{filter:grayscale(0);opacity:1}@keyframes jluxe-brand-marquee{from{transform:translateX(0)}to{transform:translateX(50%)}}@media(prefers-reduced-motion:reduce){.jluxe-brand-marquee-track{animation:none!important}.jluxe-brand-strip-logo img{transition:none!important}}@media(max-width:639px){.jluxe-brand-strip{width:calc(100% - 16px);margin:14px auto;border-radius:12px}.jluxe-brand-strip-inner{height:56px;min-height:56px;padding:4px 9px;gap:9px}.jluxe-brand-strip-title{max-width:76px;font-size:9px}.jluxe-brand-marquee-track{height:48px}.jluxe-brand-strip-item{width:102px;min-width:102px;height:48px;padding:0 5px}.jluxe-brand-strip-logo{width:78px;height:32px}.jluxe-brand-strip-logo img{max-width:78px!important;max-height:32px!important}}
	</style>
	<?php
}

function jluxe_render_homepage_blog( array $section ): void {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => (int) ( $section['count'] ?? 3 ),
		)
	);
	if ( empty( $posts ) ) {
		return; // بلاگی نیست — چیزی جعلی نشون داده نمی‌شه.
	}
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-8">
		<?php if ( ! empty( $section['title'] ) ) : ?>
			<h2 class="mb-4 text-h2 text-foreground"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php endif; ?>
		<div class="jluxe-home-blog-viewport" role="region" aria-label="نوشته‌های وبلاگ">
			<div class="jluxe-home-blog-scroller grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $posts as $p ) : ?>
				<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" class="jluxe-home-card jluxe-home-blog-card group flex flex-col overflow-hidden rounded-xl border border-border bg-surface transition-shadow hover:shadow-md">
					<?php if ( has_post_thumbnail( $p ) ) : ?>
						<span class="block aspect-video overflow-hidden bg-muted">
							<?php echo get_the_post_thumbnail( $p, 'medium_large', array( 'class' => 'size-full object-cover transition-transform duration-300 group-hover:scale-105', 'loading' => 'lazy' ) ); ?>
						</span>
					<?php endif; ?>
					<div class="flex flex-1 flex-col gap-1.5 p-4">
						<span class="text-caption text-text-muted"><?php echo esc_html( get_the_date( '', $p ) ); ?></span>
						<span class="line-clamp-2 text-body font-bold text-foreground group-hover:text-primary"><?php echo esc_html( get_the_title( $p ) ); ?></span>
						<p class="line-clamp-2 text-small text-text-secondary"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $p ) ) ); ?></p>
					</div>
				</a>
			<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * محل متن/دکمه روی بنر — کلاس‌های flex برای یک کانتینر flex-col مطلق
 * (items-* محور افقی/عرضی رو کنترل می‌کنه، justify-* محور عمودی/طولی رو،
 * چون جهت flex-col‌ه). همون واژگان start/center/end که در اسلایدر هیرو
 * استفاده شده، این‌جا هم به‌کار می‌ره تا رفتار RTL یکسان و قابل‌پیش‌بینی
 * بمونه (start = راست، end = چپ، چون سایت RTL‌ه).
 */
function jluxe_banner_position_classes( string $position ): string {
	switch ( $position ) {
		case 'bottom-center':
			return 'items-center justify-end text-center';
		case 'bottom-end':
			return 'items-end justify-end text-end';
		case 'center':
			return 'items-center justify-center text-center';
		case 'top-start':
			return 'items-start justify-start text-start';
		case 'top-end':
			return 'items-end justify-start text-end';
		default: // bottom-start
			return 'items-start justify-end text-start';
	}
}

/**
 * مدل‌های آماده‌ی دکمه («مشاهده»/«خرید»…) — چهار پیش‌فرض که ادمین از یک
 * select انتخاب می‌کنه، به‌جای این‌که هر بار رنگ/بوردر رو دستی بسازه.
 * button_color (اگه ست شده باشه) روی رنگ پایه‌ی همون مدل override می‌شه:
 * برای مدل‌های توپر روی پس‌زمینه، برای مدل‌های خط‌دور روی متن/بوردر.
 */
function jluxe_banner_button_classes( string $style ): string {
	switch ( $style ) {
		case 'white':
			return 'bg-white text-foreground shadow-md';
		case 'outline-white':
			return 'border-2 border-white bg-black/10 text-white backdrop-blur-sm';
		case 'outline-primary':
			return 'border-2 border-primary bg-white/90 text-primary shadow-md';
		default: // solid
			return 'bg-primary text-white shadow-md';
	}
}

function jluxe_banner_button_style_attr( string $style, string $button_color ): string {
	if ( empty( $button_color ) ) {
		return '';
	}
	if ( in_array( $style, array( 'outline-white', 'outline-primary' ), true ) ) {
		return ' style="border-color:' . esc_attr( $button_color ) . ';color:' . esc_attr( $button_color ) . '"';
	}
	return ' style="background-color:' . esc_attr( $button_color ) . '"';
}

function jluxe_home_banner_grid_image_sizes( int $column_count ): string {
	$column_count = max( 1, $column_count );
	$gap_total    = 16 * ( $column_count - 1 );
	$vw_fraction  = 100 / $column_count;
	$vw_offset    = ( 32 + $gap_total ) / $column_count;
	$max_width    = ( 1320 - 32 - $gap_total ) / $column_count;

	return sprintf(
		'(max-width: 639px) calc(100vw - 24px), (max-width: 1320px) calc(%.4fvw - %.2fpx), %.2fpx',
		$vw_fraction,
		$vw_offset,
		$max_width
	);
}

/** Emit an art-directed mobile source before the responsive desktop img fallback. */
function jluxe_homepage_mobile_picture_source( array $mobile_image, bool $has_mobile, string $mobile_sizes = 'calc(100vw - 24px)' ): void {
	if ( ! $has_mobile || empty( $mobile_image['src'] ) ) {
		return;
	}
	$srcset = ! empty( $mobile_image['srcset'] ) ? $mobile_image['srcset'] : $mobile_image['src'];
	printf(
		'<source media="(max-width: 639px)" srcset="%s" sizes="%s">',
		esc_attr( $srcset ),
		esc_attr( $mobile_sizes )
	);
}

/** A safe width/height aspect token for responsive section CSS. */
function jluxe_homepage_image_aspect( array $image, string $fallback ): string {
	$width  = absint( $image['width'] ?? 0 );
	$height = absint( $image['height'] ?? 0 );
	return $width && $height ? $width . '/' . $height : $fallback;
}

function jluxe_render_homepage_banners( array $section ): void {
	$items = array_values( array_filter( $section['items'] ?? array(), fn( $i ) => ! empty( $i['image_id'] ) ) );
	if ( empty( $items ) ) {
		return;
	}
	$cols = count( $items ) > 1 ? 'sm:grid-cols-' . count( $items ) : '';
	$sizes = jluxe_home_banner_grid_image_sizes( count( $items ) );
	$banner_type = $section['type'] ?? ( 1 === count( $items ) ? 'banner' : ( 2 === count( $items ) ? 'banner_two' : 'banner_three' ) );
	$desktop_size = array( 'banner' => 'jluxe-uncropped-1280', 'banner_two' => 'jluxe-uncropped-960', 'banner_three' => 'jluxe-uncropped-640' )[ $banner_type ] ?? 'jluxe-uncropped-1280';
	$default_desktop_ratio = array( 'banner' => '16/5', 'banner_two' => '9/5', 'banner_three' => '1/1' )[ $banner_type ] ?? '';
	$mobile_sizes = 'calc(100vw - 24px)';
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<div class="grid grid-cols-1 gap-4 <?php echo esc_attr( $cols ); ?>">
			<?php foreach ( $items as $item ) :
					/* Reserve the exact layout ratio; mobile art direction has its own frame ratio. */
					$desktop_image = jluxe_get_responsive_attachment_image( (int) $item['image_id'], $desktop_size, $sizes );
					if ( empty( $desktop_image['src'] ) ) {
						continue;
					}
					$mobile_id = ! empty( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : 0;
					$mobile_image = $mobile_id && $mobile_id !== (int) $item['image_id']
						? jluxe_get_responsive_attachment_image( $mobile_id, 'jluxe-uncropped-960', $mobile_sizes )
						: $desktop_image;
					$has_mobile_image = $mobile_id && $mobile_id !== (int) $item['image_id'] && ! empty( $mobile_image['src'] );
					if ( ! $has_mobile_image ) {
						$mobile_image = $desktop_image;
					}
					$image_fit = jluxe_homepage_section_choice( $item, 'image_fit', array( 'cover', 'contain', 'original' ), 'cover' );
					$item_desktop_ratio = $default_desktop_ratio ?: jluxe_homepage_image_aspect( $desktop_image, '16/9' );
					$item_mobile_ratio = 'banner_three' === $banner_type
						? '1/1'
						: ( $has_mobile_image ? jluxe_homepage_image_aspect( $mobile_image, $item_desktop_ratio ) : $item_desktop_ratio );
					if ( 'original' === $image_fit ) {
						$item_desktop_ratio = jluxe_homepage_image_aspect( $desktop_image, $item_desktop_ratio );
						$item_mobile_ratio = $has_mobile_image ? jluxe_homepage_image_aspect( $mobile_image, $item_desktop_ratio ) : $item_desktop_ratio;
					}
					$img_ratio_style = '--jluxe-home-banner-aspect:' . $item_desktop_ratio . ';--jluxe-home-banner-mobile-aspect:' . $item_mobile_ratio . ';';
					$img_url = $desktop_image['src'];
				$tag    = ! empty( $item['link'] ) ? 'a' : 'div';
				$href   = ! empty( $item['link'] ) ? ' href="' . esc_url( jluxe_resolve_site_link( (string) $item['link'] ) ) . '"' : '';
				$has_overlay = array_key_exists( 'overlay', $item ) ? ! empty( $item['overlay'] ) : true;
				$zoom_enabled = array_key_exists( 'zoom_enabled', $item ) ? ! empty( $item['zoom_enabled'] ) : true;
				$shine_enabled = array_key_exists( 'shine_enabled', $item ) ? ! empty( $item['shine_enabled'] ) : true;
				$pos_class   = jluxe_banner_position_classes( $item['content_position'] ?? 'bottom-start' );
				$btn_style   = $item['button_style'] ?? 'solid';
				$text_style  = ! empty( $item['text_color'] ) ? ' style="color:' . esc_attr( $item['text_color'] ) . '"' : '';
				// وقتی رنگ متن دستی انتخاب نشده، پیش‌فرض سفیده (چون معمولاً روی
				// عکس/هاله‌ی تیره می‌شینه) مگر overlay خاموش باشه که سفید روی
				// عکسِ روشن ممکنه ناخوانا بشه — در اون حالت رنگ پیش‌فرض متن اصلی سایته.
				$text_color_class = empty( $item['text_color'] ) ? ( $has_overlay ? 'text-white' : 'text-foreground' ) : '';
				?>
					<<?php echo esc_html( $tag ) . $href; ?> class="jluxe-home-card jluxe-home-banner group relative block overflow-hidden rounded-2xl<?php echo $shine_enabled ? ' jluxe-banner-shine' : ''; ?>" style="<?php echo esc_attr( $img_ratio_style ); ?>">
						<picture class="absolute inset-0 block size-full">
							<?php jluxe_homepage_mobile_picture_source( $mobile_image, (bool) $has_mobile_image, $mobile_sizes ); ?>
							<img src="<?php echo esc_url( $img_url ); ?>"<?php echo jluxe_responsive_image_attributes( $desktop_image ); ?> alt="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="jluxe-home-media size-full <?php echo 'cover' === $image_fit ? 'object-cover' : 'object-contain'; ?> transition-transform duration-500<?php echo $zoom_enabled ? ' group-hover:scale-105' : ''; ?>" <?php echo jluxe_lazy_attr(); ?> />
						</picture>
					<?php if ( $has_overlay ) : ?>
						<div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
					<?php endif; ?>
					<?php if ( ! empty( $item['title'] ) || ! empty( $item['subtitle'] ) || ! empty( $item['button'] ) ) : ?>
						<div class="absolute inset-0 flex flex-col gap-1.5 p-4 sm:p-6 <?php echo esc_attr( $pos_class ); ?>">
							<div class="flex max-w-[85%] flex-col gap-1 <?php echo esc_attr( $text_color_class ); ?>"<?php echo $text_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php if ( ! empty( $item['title'] ) ) : ?><span class="text-h3 font-bold"><?php echo esc_html( $item['title'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['subtitle'] ) ) : ?><span class="text-small"><?php echo esc_html( $item['subtitle'] ); ?></span><?php endif; ?>
							</div>
							<?php if ( ! empty( $item['button'] ) ) : ?>
								<span class="mt-1 inline-flex w-fit whitespace-nowrap rounded-lg px-4 py-2 text-button font-bold transition-transform group-hover:scale-105 <?php echo esc_attr( jluxe_banner_button_classes( $btn_style ) ); ?>"<?php echo jluxe_banner_button_style_attr( $btn_style, $item['button_color'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $item['button'] ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</<?php echo esc_html( $tag ); ?>>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

function jluxe_render_homepage_stories( array $section ): void {
	$items = array_values(
		array_filter(
			$section['items'] ?? array(),
			fn( $i ) => ! empty( $i['thumb_id'] ) && ! empty( $i['media_id'] )
		)
	);
	if ( empty( $items ) ) {
		return;
	}
	$uid = 'stories-' . esc_attr( $section['id'] );
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-5">
		<div class="jluxe-scroll-x jluxe-hide-scrollbar flex gap-4 overflow-x-auto scroll-smooth pb-1" data-jluxe-stories id="<?php echo esc_attr( $uid ); ?>">
			<?php foreach ( $items as $item ) :
				$thumb_url = wp_get_attachment_image_url( (int) $item['thumb_id'], 'thumbnail' );
				$media_url = wp_get_attachment_image_url( (int) $item['media_id'], 'large' );
				$media_type = ( $item['media_type'] ?? 'image' ) === 'video' ? 'video' : 'image';
				if ( 'video' === $media_type ) {
					$media_url = wp_get_attachment_url( (int) $item['media_id'] );
				}
				if ( ! $thumb_url || ! $media_url ) {
					continue;
				}
				?>
				<button
					type="button"
					data-jluxe-story-avatar
					data-src="<?php echo esc_url( $media_url ); ?>"
					data-media-type="<?php echo esc_attr( $media_type ); ?>"
					data-title="<?php echo esc_attr( $item['title'] ?? '' ); ?>"
					class="flex shrink-0 flex-col items-center gap-1.5"
				>
					<span class="grid size-16 place-items-center rounded-full bg-gradient-to-tr from-primary via-primary to-primary/60 p-[2.5px]">
						<span class="grid size-full place-items-center rounded-full bg-surface p-[2.5px]">
							<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="size-full rounded-full object-cover" <?php echo jluxe_lazy_attr(); ?> />
						</span>
					</span>
					<?php if ( ! empty( $item['title'] ) ) : ?>
						<span class="max-w-[72px] truncate text-caption font-medium text-foreground"><?php echo esc_html( $item['title'] ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>

			<?php
			/*
			 * عمداً بدون grid/place-items-center توی کلاس پیش‌فرض: چون [hidden]
			 * (که attribute هست، نه کلاس) از نظر cascade هم‌رتبه‌ی هر کلاس Tailwind
			 * دیگه‌ست، و کلاس authored مثل .grid روی [hidden]{display:none} پیشی
			 * می‌گیره (UA rule همیشه از author rule هم‌رتبه می‌بازه) — یعنی این
			 * مودال با وجود hidden واقعاً نامرئی نمی‌شد و همیشه روی صفحه باز
			 * می‌موند. حالا grid/place-items-center فقط موقع باز شدن (توسط JS در
			 * homepage.js) اضافه می‌شن، نه از اول.
			 */
			?>
			<div data-jluxe-story-viewer hidden class="fixed inset-0 z-[999] bg-black/85 p-4">
				<button type="button" data-jluxe-story-close aria-label="بستن" class="absolute end-4 top-4 grid size-10 place-items-center rounded-full bg-white/10 text-white">
					<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
				<div class="flex max-w-full flex-col items-center gap-3">
					<div data-jluxe-story-media></div>
					<span data-jluxe-story-title class="text-small text-white"></span>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/**
 * طبقِ درخواستِ صریحِ کاربر — یک سوییچِ سراسری (تنظیمات ← فروشگاه)، مصرف‌شده
 * در هر جایی که یک data-jluxe-scroller رندر می‌شه: اگه روشن باشه، همون
 * attributeِ data-jluxe-autoscroll که initAutoScroll (assets/js/homepage.js)
 * از قبل برایِ «فروش ویژه» می‌شناسه رو برمی‌گردونه، وگرنه رشته‌ی خالی.
 * «فروش ویژه» عمداً از این سوییچ مستقل مونده (همیشه خودکاره، چون رفتارِ
 * pause/rewindِ اختصاصیِ خودش رو داره)، نه اینکه دوبار همون attribute رو ست کنیم.
 */
function jluxe_carousel_autoscroll_attr(): string {
	return ! empty( jluxe_get_theme_settings()['shop']['auto_scroll_carousels'] ) ? ' data-jluxe-autoscroll' : '';
}

/**
 * دو دکمه‌ی قبلی/بعدی برای هر کاروسل native-scroll (.jluxe-scroll-x —
 * گرید محصولات با چیدمان «کاروسل»، نوار دسته‌بندی، استوری). باگ واقعی که
 * پیدا شد: بعد از مخفی‌کردن اسکرول‌بار پیش‌فرض مرورگر (برای ظاهر تمیزتر)،
 * هیچ راه دیگه‌ای برای اسکرول با ماوس معمولی (بدون تاچ‌پد/لمس) نمونده بود —
 * یعنی کاروسل عملاً «تکون نمی‌خورد». این تابع رو باید داخل یک والدِ
 * position:relative صدا بزنی که یک فرزندِ data-jluxe-scroller=""‌دار داشته
 * باشه — دکمه‌ها با JS (assets/js/homepage.js) نزدیک‌ترین data-jluxe-scroller
 * رو پیدا و اسکرول می‌کنن (جهت RTL-aware)؛ چون بر اساس نزدیکیِ DOM کار
 * می‌کنه نه id، چند کاروسل هم‌زمان توی یک صفحه با هم تداخل ندارن. طبقِ
 * درخواستِ صریحِ کاربر الان موبایل هم نشون داده می‌شن (قبلاً فقط sm+ بود).
 */
function jluxe_scroll_arrows(): void {
	/*
	 * طبقِ درخواستِ صریحِ کاربر: این دو دکمه دیگه فقط sm+ نیستن (موبایل هم
	 * می‌خواد ببینتشون)، استایلِ «شیشه‌ای» (bg-surface/40 + backdrop-blur)
	 * گرفتن، و به‌جایِ همیشه‌دیده‌بودن، JS (initScrollArrows در
	 * assets/js/homepage.js) با گوش‌دادن به رویدادِ scroll خودِ اسکرولر،
	 * فقط سمتی که واقعاً چیزی برایِ اسکرول‌کردن داره رو نشون می‌ده (کلاسِ
	 * پایه opacity-0 pointer-events-none — یعنی اول هر دو مخفی‌ان تا JS
	 * وضعیتِ درست رو تشخیص بده، بدونِ یک لحظه «فلاش» نمایشِ اشتباه).
	 */
	?>
	<button type="button" data-jluxe-scroll-prev aria-label="قبلی" class="absolute end-1 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-surface/40 text-foreground opacity-0 shadow-lg backdrop-blur-md transition-opacity duration-300 pointer-events-none hover:bg-surface/60">
		<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
	</button>
	<button type="button" data-jluxe-scroll-next aria-label="بعدی" class="absolute start-1 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-surface/40 text-foreground opacity-0 shadow-lg backdrop-blur-md transition-opacity duration-300 pointer-events-none hover:bg-surface/60">
		<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
	</button>
	<?php
}

/**
 * اسلایدر هیرو (بالای صفحه‌ی اصلی) — اسلات‌های خالی نمایش داده نمی‌شن؛ اگه
 * هیچ اسلایدی پر نشده باشه، اصلاً چیزی رندر نمی‌شه (نه گرادیان جایگزین).
 */
/**
 * R89 — گزینه‌های نرمال‌شدهٔ اسلایدرِ هیرو (یک منبعِ واحد برای فرمِ ادمین،
 * sanitizer، preload و رندرِ فرانت). مقدارِ پیش‌فرض‌ها دقیقاً ابعادِ نمونه‌ای
 * است که صاحبِ سایت فرستاد: کارتِ گرد با ارتفاعِ ۳۶۰px دسکتاپ / ۳۲۰px موبایل،
 * گردیِ ۳۲px / ۲۴px، فاصلهٔ بالای ۶۴px / ۱۶px، حرکتِ اسلاید افقی.
 *
 * بخش‌های ذخیره‌شدهٔ قدیمی (که فقط duration_sec/zoom_enabled/width_mode
 * داشتند) بدونِ هیچ مهاجرتی همین پیش‌فرض‌ها را می‌گیرند؛ width_modeِ
 * ذخیره‌شده (پیش‌فرضِ قدیمی full) دست نمی‌خورد.
 *
 * @return array<string, mixed>
 */
function jluxe_hero_effects(): array {
	return array(
		'slide'    => 'اسلاید افقی (مثل Swiper — پیش‌فرض)',
		'fade'     => 'محو شدن (Fade)',
		'zoom'     => 'محو + بزرگ‌نمایی (Zoom)',
		'vertical' => 'اسلاید عمودی',
		'none'     => 'بدونِ انیمیشن (جابه‌جاییِ فوری)',
	);
}

function jluxe_hero_options( array $section ): array {
	$pick = static function ( $value, array $allowed, string $default ): string {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	};
	$int = static function ( $value, int $min, int $max, int $default ): int {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}
		return max( $min, min( $max, (int) $value ) );
	};
	$width_mode = $pick( $section['width_mode'] ?? 'full', array( 'full', 'container' ), 'full' );
	$radius     = $pick( $section['radius'] ?? 'auto', array( 'auto', 'large', 'small', 'none' ), 'auto' );
	if ( 'auto' === $radius ) {
		$radius_px = 'container' === $width_mode ? array( 24, 32 ) : array( 0, 0 );
	} else {
		$radius_px = array(
			'large' => array( 24, 32 ),
			'small' => array( 12, 16 ),
			'none'  => array( 0, 0 ),
		)[ $radius ];
	}
	$spacing    = $pick( $section['top_spacing'] ?? 'reference', array( 'reference', 'compact', 'none' ), 'reference' );
	$spacing_px = array(
		'reference' => array( 16, 64 ),
		'compact'   => array( 12, 24 ),
		'none'      => array( 0, 0 ),
	)[ $spacing ];

	return array(
		'effect'         => $pick( $section['effect'] ?? 'slide', array_keys( jluxe_hero_effects() ), 'slide' ),
		'speed_ms'       => $int( $section['speed_ms'] ?? 500, 150, 2000, 500 ),
		'duration_ms'    => $int( $section['duration_sec'] ?? 5, 2, 15, 5 ) * 1000,
		'autoplay'       => array_key_exists( 'autoplay', $section ) ? ! empty( $section['autoplay'] ) : true,
		'loop'           => array_key_exists( 'loop', $section ) ? ! empty( $section['loop'] ) : true,
		'zoom'           => array_key_exists( 'zoom_enabled', $section ) ? ! empty( $section['zoom_enabled'] ) : true,
		'width_mode'     => $width_mode,
		'desktop_height' => $int( $section['desktop_height'] ?? 360, 200, 720, 360 ),
		'mobile_height'  => $int( $section['mobile_height'] ?? 320, 160, 640, 320 ),
		'radius'         => $radius,
		'radius_mobile'  => $radius_px[0],
		'radius_desktop' => $radius_px[1],
		'top_spacing'    => $spacing,
		'top_mobile'     => $spacing_px[0],
		'top_desktop'    => $spacing_px[1],
		'show_arrows'    => array_key_exists( 'show_arrows', $section ) ? ! empty( $section['show_arrows'] ) : true,
		'indicators'     => $pick( $section['indicators'] ?? 'bars', array( 'bars', 'dots', 'none' ), 'bars' ),
		'border'         => array_key_exists( 'border', $section ) ? ! empty( $section['border'] ) : true,
	);
}

/** sizes دقیق برای srcset — هم‌راستا بین <picture> و preload. */
function jluxe_hero_image_sizes( array $opt ): array {
	if ( 'container' === $opt['width_mode'] ) {
		return array(
			'mobile'  => 'calc(100vw - 24px)',
			'desktop' => '(min-width: 2560px) 2304px, (min-width: 2072px) 2048px, (min-width: 1920px) calc(100vw - 24px), (min-width: 1784px) 1760px, calc(100vw - 24px)',
		);
	}
	return array( 'mobile' => '100vw', 'desktop' => '100vw' );
}

/**
 * Resolve the same viable hero-slide set for both HTML output and LCP preload.
 * Missing desktop attachments are skipped; a missing mobile override falls
 * back to the desktop image and its responsive candidates.
 */
function jluxe_resolve_homepage_hero_slides( array $section, array $sizes ): array {
	$items = array_values( array_filter( $section['items'] ?? array(), fn( $item ) => ! empty( $item['image_id'] ) ) );
	$slides = array();

	foreach ( $items as $item ) {
		$desktop_id = absint( $item['image_id'] );
		$desktop    = jluxe_get_responsive_attachment_image( $desktop_id, 'large', $sizes['desktop'] ?? '100vw' );
		if ( empty( $desktop['src'] ) ) {
			continue;
		}

		$mobile_id = ! empty( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : $desktop_id;
		$mobile    = $mobile_id !== $desktop_id
			? jluxe_get_responsive_attachment_image( $mobile_id, 'large', $sizes['mobile'] ?? '100vw' )
			: $desktop;
		$has_mobile = $mobile_id !== $desktop_id && ! empty( $mobile['src'] );
		if ( ! $has_mobile ) {
			$mobile = $desktop;
		}
		$mobile['sizes'] = $sizes['mobile'] ?? '100vw';

		$slides[] = array(
			'item'           => $item,
			'desktop'        => $desktop,
			'mobile'         => $mobile,
			'desktop_url'    => $desktop['src'],
			'desktop_srcset' => $desktop['srcset'],
			'mobile_url'     => $mobile['src'],
			'mobile_srcset'  => $mobile['srcset'],
			'has_mobile'     => $has_mobile,
		);
	}

	return $slides;
}

/**
 * پیش‌بارگذاریِ عکسِ اسلایدِ اولِ هیرو (کاندیدِ اصلیِ LCP). دو نسخه با media
 * جدا تا فقط یکی دانلود شود؛ srcset/sizes دقیقاً همانِ <picture> پایین.
 */
function jluxe_preload_homepage_hero_lcp_image(): void {
	if ( ! is_front_page() ) {
		return;
	}
	$settings = jluxe_get_theme_settings();
	$hero     = null;
	foreach ( $settings['homepage']['sections'] ?? array() as $section ) {
		if ( ! empty( $section['enabled'] ) && 'hero' === ( $section['type'] ?? '' ) ) {
			$hero = $section;
			break;
		}
	}
	if ( ! $hero ) {
		return;
	}

	$sizes  = jluxe_hero_image_sizes( jluxe_hero_options( $hero ) );
	$slides = jluxe_resolve_homepage_hero_slides( $hero, $sizes );
	if ( empty( $slides ) ) {
		return;
	}
	$first  = $slides[0];
	$mobile = $first['mobile'];
	$desktop = $first['desktop'];

	if ( ! empty( $mobile['src'] ) ) {
		printf(
			'<link rel="preload" as="image" href="%1$s" media="(max-width: 767px)"%2$s fetchpriority="high">' . "\n",
			esc_url( $mobile['src'] ),
			! empty( $mobile['srcset'] ) ? sprintf( ' imagesrcset="%s" imagesizes="%s"', esc_attr( $mobile['srcset'] ), esc_attr( $mobile['sizes'] ) ) : ''
		);
	}
	if ( ! empty( $desktop['src'] ) ) {
		printf(
			'<link rel="preload" as="image" href="%1$s" media="(min-width: 768px)"%2$s fetchpriority="high">' . "\n",
			esc_url( $desktop['src'] ),
			! empty( $desktop['srcset'] ) ? sprintf( ' imagesrcset="%s" imagesizes="%s"', esc_attr( $desktop['srcset'] ), esc_attr( $desktop['sizes'] ) ) : ''
		);
	}
}
add_action( 'wp_head', 'jluxe_preload_homepage_hero_lcp_image', 1 );

/** CSSِ هیرو — یک‌بار و inline (بخشی از مسیرِ بحرانیِ LCP؛ درخواستِ اضافه ندارد). */
function jluxe_hero_css(): string {
	return '.jluxe-hero{--jh-gap:16px;position:relative;box-sizing:border-box;width:100%;margin:var(--jh-mt-m) auto 0}'
		. '.jluxe-hero--container{width:calc(100% - 24px);max-width:var(--jluxe-container-max,1320px)}'
		. '.jluxe-hero--flush{--jh-gap:0px}.jluxe-hero--flush .jluxe-hero__card{border-inline-width:0}'
		. '.jluxe-hero__viewport{overflow:hidden;border-radius:var(--jh-r-m)}'
		. '.jluxe-hero__track{display:flex;gap:var(--jh-gap);transition:transform var(--jh-speed) cubic-bezier(.25,.8,.25,1)}'
		. '.jluxe-hero__track.is-dragging,.jluxe-hero__track.is-jumping{transition:none}'
		. '.jluxe-hero__slide{flex:0 0 100%;min-width:0}'
		. '.jluxe-hero__card{position:relative;display:block;box-sizing:border-box;height:var(--jh-h-m);overflow:hidden;border-radius:var(--jh-r-m);background:hsl(var(--muted));isolation:isolate;-webkit-user-select:none;user-select:none;-webkit-tap-highlight-color:transparent}'
		. '.jluxe-hero--border .jluxe-hero__card{border:1px solid hsl(var(--border))}'
		. '.jluxe-hero__card picture,.jluxe-hero__img{display:block;width:100%;height:100%}'
		. '.jluxe-hero__img{object-fit:cover;-webkit-user-drag:none}'
		. '@media (max-width:767px){.jluxe-hero[data-jluxe-mobile-center="true"]{left:var(--jluxe-hero-mobile-shift,0px)}.jluxe-hero__img.is-contain{object-fit:contain}}'
		. '.jluxe-hero[data-zoom="1"] .jluxe-hero__img{transform:scale(1);transition:transform 900ms cubic-bezier(.2,.7,.2,1)}'
		. '.jluxe-hero[data-zoom="1"] .jluxe-hero__slide[data-active="true"] .jluxe-hero__img{transform:scale(1.045);transition-duration:var(--jh-dur);transition-timing-function:linear}'
		// fade / zoom: اسلایدها روی هم (grid) — بدونِ JS هم فقط اسلایدِ فعال دیده می‌شود.
		. '.jluxe-hero[data-effect="fade"] .jluxe-hero__track,.jluxe-hero[data-effect="zoom"] .jluxe-hero__track,.jluxe-hero[data-effect="none"] .jluxe-hero__track{display:grid;transform:none!important}'
		. '.jluxe-hero[data-effect="fade"] .jluxe-hero__slide,.jluxe-hero[data-effect="zoom"] .jluxe-hero__slide,.jluxe-hero[data-effect="none"] .jluxe-hero__slide{grid-area:1/1;opacity:0;visibility:hidden;transition:opacity var(--jh-speed) ease,transform var(--jh-speed) ease,visibility 0s linear var(--jh-speed)}'
		. '.jluxe-hero[data-effect="zoom"] .jluxe-hero__slide{transform:scale(1.08)}'
		. '.jluxe-hero[data-effect] .jluxe-hero__slide[data-active="true"]{opacity:1;visibility:visible;transform:none;z-index:1;transition-delay:0s}'
		. '.jluxe-hero[data-effect="none"] .jluxe-hero__slide{transition:none}'
		// عمودی
		. '.jluxe-hero[data-effect="vertical"] .jluxe-hero__track{flex-direction:column;height:var(--jh-h-m)}'
		. '.jluxe-hero[data-effect="vertical"] .jluxe-hero__slide{flex:0 0 100%;min-height:0}'
		// فلش‌ها — فقط تبلت/دسکتاپ؛ موبایل با کشیدنِ انگشت.
		. '.jluxe-hero__arrow{position:absolute;top:50%;z-index:3;display:none;align-items:center;justify-content:center;width:44px;height:44px;margin-top:-22px;padding:0;border:0;border-radius:999px;background:hsl(var(--surface)/.92);color:hsl(var(--foreground));box-shadow:0 4px 14px rgb(0 0 0/.14);cursor:pointer}'
		. '.jluxe-hero__arrow svg{width:20px;height:20px}'
		. '.jluxe-hero__arrow--prev{inset-inline-start:16px}.jluxe-hero__arrow--next{inset-inline-end:16px}'
		. '.jluxe-hero__arrow:focus-visible,.jluxe-hero__dot:focus-visible,.jluxe-hero__card:focus-visible{outline:3px solid hsl(var(--primary));outline-offset:2px}'
		// نشانگرها — دکمهٔ ۲۴px (هدفِ لمسیِ WCAG 2.2) با نمایشِ کوچک.
		. '.jluxe-hero__dots{position:absolute;inset-inline:16px;bottom:8px;z-index:3;display:flex;justify-content:center;gap:2px}'
		. '.jluxe-hero__dot{position:relative;display:block;width:24px;height:24px;min-height:0;padding:0;border:0;background:none;cursor:pointer}'
		. '.jluxe-hero__dot::before{content:"";position:absolute;top:50%;inset-inline:8px;height:8px;margin-top:-4px;border-radius:999px;background:rgb(255 255 255/.6);box-shadow:0 0 0 1px rgb(0 0 0/.08)}'
		. '.jluxe-hero__dot[aria-current="true"]{width:34px}.jluxe-hero__dot[aria-current="true"]::before{background:#fff}'
		. '.jluxe-hero__dots--bars{inset-inline:24px;gap:6px}'
		. '.jluxe-hero__dots--bars .jluxe-hero__dot,.jluxe-hero__dots--bars .jluxe-hero__dot[aria-current="true"]{flex:1;max-width:120px;width:auto;height:20px}'
		. '.jluxe-hero__dots--bars .jluxe-hero__dot::before{inset-inline:0;height:4px;margin-top:-2px;background:rgb(255 255 255/.4)}'
		. '.jluxe-hero__fill{position:absolute;top:50%;left:0;height:4px;margin-top:-2px;width:0;border-radius:999px;background:#fff}'
		. '[dir="rtl"] .jluxe-hero__fill{left:auto;right:0}'
		. '@media (min-width:768px){.jluxe-hero{margin-top:var(--jh-mt-d)}.jluxe-hero__viewport,.jluxe-hero__card{border-radius:var(--jh-r-d)}.jluxe-hero__card{height:var(--jh-h-d)}.jluxe-hero[data-effect="vertical"] .jluxe-hero__track{height:var(--jh-h-d)}.jluxe-hero__arrow{display:flex}}'
		. '@media (hover:hover) and (pointer:fine){.jluxe-hero__arrow:hover{background:hsl(var(--surface))}.jluxe-hero__track{cursor:grab}.jluxe-hero__track.is-dragging{cursor:grabbing}}'
		. '@media (prefers-reduced-motion:reduce){.jluxe-hero__track,.jluxe-hero .jluxe-hero__slide,.jluxe-hero .jluxe-hero__img{transition:none!important}.jluxe-hero[data-zoom="1"] .jluxe-hero__slide[data-active="true"] .jluxe-hero__img{transform:none}}'
		. 'html.jluxe-lite .jluxe-hero[data-zoom="1"] .jluxe-hero__slide[data-active="true"] .jluxe-hero__img{transform:none}';
}

function jluxe_render_homepage_hero( array $section ): void {
	$opt    = jluxe_hero_options( $section );
	$sizes  = jluxe_hero_image_sizes( $opt );
	$slides = jluxe_resolve_homepage_hero_slides( $section, $sizes );
	if ( empty( $slides ) ) {
		return;
	}
	$count = count( $slides );

	$classes = array( 'jluxe-hero', 'jluxe-hero--' . $opt['width_mode'] );
	if ( $opt['border'] ) {
		$classes[] = 'jluxe-hero--border';
	}
	if ( 0 === $opt['radius_desktop'] && 'full' === $opt['width_mode'] ) {
		$classes[] = 'jluxe-hero--flush'; // تمام‌صفحهٔ بی‌گوشه: بدونِ فاصلهٔ سفید بینِ اسلایدها.
	}
	$style = sprintf(
		'--jh-h-m:%dpx;--jh-h-d:%dpx;--jh-r-m:%dpx;--jh-r-d:%dpx;--jh-mt-m:%dpx;--jh-mt-d:%dpx;--jh-speed:%dms;--jh-dur:%dms',
		$opt['mobile_height'],
		$opt['desktop_height'],
		$opt['radius_mobile'],
		$opt['radius_desktop'],
		$opt['top_mobile'],
		$opt['top_desktop'],
		'none' === $opt['effect'] ? 0 : $opt['speed_ms'],
		$opt['duration_ms']
	);

	static $css_printed = false;
	if ( ! $css_printed ) {
		$css_printed = true;
		echo '<style id="jluxe-hero-style">' . jluxe_hero_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
	}
	?>
	<section
		class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		style="<?php echo esc_attr( $style ); ?>"
		data-jluxe-hero-slider
		data-effect="<?php echo esc_attr( $opt['effect'] ); ?>"
		data-speed="<?php echo esc_attr( (string) $opt['speed_ms'] ); ?>"
		data-autoplay="<?php echo $opt['autoplay'] && $count > 1 ? '1' : '0'; ?>"
		data-autoplay-ms="<?php echo esc_attr( (string) $opt['duration_ms'] ); ?>"
		data-loop="<?php echo $opt['loop'] ? '1' : '0'; ?>"
		data-zoom="<?php echo $opt['zoom'] ? '1' : '0'; ?>"
		aria-roledescription="carousel"
		aria-label="بنرهای تبلیغاتی"
	>
		<div class="jluxe-hero__viewport">
			<div class="jluxe-hero__track" data-jluxe-hero-track aria-live="<?php echo $opt['autoplay'] ? 'off' : 'polite'; ?>">
			<?php
			foreach ( $slides as $i => $slide ) :
				$item = $slide['item'];
				$tag  = ! empty( $item['link'] ) ? 'a' : 'div';
				$href = ! empty( $item['link'] ) ? ' href="' . esc_url( jluxe_resolve_site_link( (string) $item['link'] ) ) . '"' : '';
				switch ( $item['content_position'] ?? 'start' ) {
					case 'center':
						$pos_class = 'inset-0 items-center justify-center px-6 text-center';
						break;
					case 'end':
						$pos_class = 'inset-y-0 end-6 sm:end-10 items-end justify-center text-end';
						break;
					default:
						$pos_class = 'inset-y-0 start-6 sm:start-10 items-start justify-center text-start';
				}
				$button_style = ! empty( $item['button_color'] ) ? ' style="background-color:' . esc_attr( $item['button_color'] ) . '"' : '';
				$alt          = (string) ( $item['title'] ?? '' );
				$first        = 0 === $i;
				/* translators: 1: slide number, 2: slide count */
				$label = sprintf( '%1$s از %2$s', jluxe_fa_digits( $i + 1 ), jluxe_fa_digits( $count ) );
				?>
				<div class="jluxe-hero__slide" data-jluxe-hero-slide data-active="<?php echo $first ? 'true' : 'false'; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( $label ); ?>"<?php echo $first ? '' : ' aria-hidden="true" inert'; ?>>
					<<?php echo esc_html( $tag ) . $href; ?> class="jluxe-hero__card"<?php echo 'a' === $tag && '' === $alt ? ' aria-label="' . esc_attr( 'بنر ' . $label ) . '"' : ''; ?>>
						<picture>
							<source media="(min-width: 768px)" srcset="<?php echo esc_attr( $slide['desktop_srcset'] ?: $slide['desktop_url'] ); ?>" sizes="<?php echo esc_attr( $sizes['desktop'] ); ?>">
							<img
								class="jluxe-hero__img<?php echo $slide['has_mobile'] ? '' : ' is-contain'; ?>"
								src="<?php echo esc_url( $slide['mobile_url'] ); ?>"
								<?php if ( $slide['mobile_srcset'] ) : ?>srcset="<?php echo esc_attr( $slide['mobile_srcset'] ); ?>" sizes="<?php echo esc_attr( $sizes['mobile'] ); ?>"<?php endif; ?>
								alt="<?php echo esc_attr( $alt ); ?>"
								draggable="false"
								decoding="<?php echo $first ? 'sync' : 'async'; ?>"
								loading="<?php echo $first ? 'eager' : 'lazy'; ?>"
								fetchpriority="<?php echo $first ? 'high' : 'low'; ?>"
								<?php echo $first ? 'data-no-lazy="1"' : ''; ?>
							/>
						</picture>
						<?php if ( ! empty( $item['title'] ) || ! empty( $item['subtitle'] ) || ! empty( $item['button'] ) ) : ?>
							<div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
							<div class="absolute flex flex-col gap-1.5 <?php echo esc_attr( $pos_class ); ?>">
								<?php if ( ! empty( $item['title'] ) ) : ?><span class="text-h2 font-extrabold text-white"><?php echo esc_html( $item['title'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['subtitle'] ) ) : ?><span class="max-w-xs text-small text-white/85"><?php echo esc_html( $item['subtitle'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['button'] ) ) : ?><span class="mt-2 rounded-lg bg-primary px-4 py-2 text-button font-bold text-primary-foreground"<?php echo $button_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>><?php echo esc_html( $item['button'] ); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
					</<?php echo esc_html( $tag ); ?>>
				</div>
			<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $count > 1 ) : ?>
			<?php if ( $opt['show_arrows'] ) : ?>
				<button type="button" class="jluxe-hero__arrow jluxe-hero__arrow--prev" data-jluxe-hero-prev aria-label="اسلاید قبلی">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
				</button>
				<button type="button" class="jluxe-hero__arrow jluxe-hero__arrow--next" data-jluxe-hero-next aria-label="اسلاید بعدی">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
				</button>
			<?php endif; ?>
			<?php if ( 'none' !== $opt['indicators'] ) : ?>
				<div class="jluxe-hero__dots jluxe-hero__dots--<?php echo esc_attr( $opt['indicators'] ); ?>">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<button type="button" class="jluxe-hero__dot" data-jluxe-hero-dot="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( 'رفتن به اسلاید ' . jluxe_fa_digits( $i + 1 ) ); ?>" aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>"><?php echo 'bars' === $opt['indicators'] ? '<span class="jluxe-hero__fill" data-jluxe-hero-fill></span>' : ''; ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
}

function jluxe_render_homepage_banner_slider( array $section ): void {
	$items = array_values( array_filter( $section['items'] ?? array(), fn( $i ) => ! empty( $i['image_id'] ) ) );
	if ( empty( $items ) ) {
		return;
	}
	$slides = array();
	$image_sizes = '(max-width: 639px) calc(100vw - 24px), (max-width: 1320px) calc(100vw - 32px), 1288px';
	$mobile_sizes = 'calc(100vw - 24px)';
	foreach ( $items as $item ) {
		$desktop_image = jluxe_get_responsive_attachment_image( (int) $item['image_id'], 'jluxe-uncropped-1280', $image_sizes );
		if ( empty( $desktop_image['src'] ) ) {
			continue;
		}
		$mobile_id = ! empty( $item['mobile_image_id'] ) ? absint( $item['mobile_image_id'] ) : 0;
		$mobile_image = $mobile_id && $mobile_id !== (int) $item['image_id']
			? jluxe_get_responsive_attachment_image( $mobile_id, 'jluxe-uncropped-960', $mobile_sizes )
			: $desktop_image;
		$has_mobile_image = $mobile_id && $mobile_id !== (int) $item['image_id'] && ! empty( $mobile_image['src'] );
		if ( ! $has_mobile_image ) {
			$mobile_image = $desktop_image;
		}
		$slides[] = array(
			'item' => $item,
			'desktop' => $desktop_image,
			'mobile' => $mobile_image,
			'has_mobile' => (bool) $has_mobile_image,
		);
	}
	if ( empty( $slides ) ) {
		return;
	}
	$frame_desktop_aspect = '16/5';
	$frame_mobile_aspect = ! empty( $slides[0]['has_mobile'] )
		? jluxe_homepage_image_aspect( $slides[0]['mobile'], '3/4' )
		: $frame_desktop_aspect;
	$frame_style = '--jluxe-banner-slider-aspect-desktop:' . $frame_desktop_aspect . ';--jluxe-banner-slider-aspect-mobile:' . $frame_mobile_aspect . ';';
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<?php if ( ! empty( $section['title'] ) ) : ?>
			<h2 class="mb-4 text-h2 text-foreground"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php endif; ?>
			<div class="relative overflow-hidden rounded-2xl" data-jluxe-banner-slider>
				<div class="jluxe-home-banner-slider-frame relative w-full" style="<?php echo esc_attr( $frame_style ); ?>">
					<?php foreach ( $slides as $i => $slide ) :
						$item = $slide['item'];
						$desktop_image = $slide['desktop'];
						$mobile_image = $slide['mobile'];
						$tag = ! empty( $item['link'] ) ? 'a' : 'div';
					$href   = ! empty( $item['link'] ) ? ' href="' . esc_url( jluxe_resolve_site_link( (string) $item['link'] ) ) . '"' : '';
					?>
						<<?php echo esc_html( $tag ) . $href; ?> data-jluxe-bs-slide class="absolute inset-0 transition-opacity duration-700" style="opacity:<?php echo 0 === $i ? '1' : '0'; ?>">
							<picture class="block size-full">
								<?php jluxe_homepage_mobile_picture_source( $mobile_image, (bool) $slide['has_mobile'], $mobile_sizes ); ?>
								<img src="<?php echo esc_url( $desktop_image['src'] ); ?>"<?php echo jluxe_responsive_image_attributes( $desktop_image ); ?> alt="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="size-full object-cover" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"<?php echo 0 === $i ? ' data-no-lazy="1"' : ''; ?> />
							</picture>
						<?php if ( ! empty( $item['title'] ) || ! empty( $item['category'] ) ) : ?>
							<div class="absolute inset-y-0 start-6 flex flex-col justify-center gap-1.5 sm:start-10">
								<?php if ( ! empty( $item['category'] ) ) : ?><span class="text-caption font-medium text-white/80"><?php echo esc_html( $item['category'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['title'] ) ) : ?><span class="text-h2 font-extrabold text-white"><?php echo esc_html( $item['title'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['description'] ) ) : ?><span class="max-w-xs text-small text-white/85"><?php echo esc_html( $item['description'] ); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
					</<?php echo esc_html( $tag ); ?>>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $slides ) > 1 ) : ?>
				<button type="button" data-jluxe-bs-prev aria-label="قبلی" class="absolute end-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-foreground shadow">
					<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg>
				</button>
				<button type="button" data-jluxe-bs-next aria-label="بعدی" class="absolute start-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-foreground shadow">
					<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg>
				</button>
				<div class="absolute bottom-3 start-1/2 flex -translate-x-1/2 gap-1.5">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<span data-jluxe-bs-dot data-active="<?php echo 0 === $i ? 'true' : 'false'; ?>" class="h-1.5 w-5 rounded-full bg-white/50 transition-colors data-[active=true]:bg-white"></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

function jluxe_render_homepage_brick_products( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$cols_d = max( 2, min( 6, (int) ( $section['columns_desktop'] ?? 4 ) ) );
	$cols_t = max( 2, min( 4, (int) ( $section['columns_tablet'] ?? 3 ) ) );
	$cols_m = max( 1, min( 3, (int) ( $section['columns_mobile'] ?? 2 ) ) );
	$rows   = max( 1, min( 4, (int) ( $section['rows'] ?? 2 ) ) );
	// تعداد محصول همیشه از چیدمانِ واقعی محاسبه می‌شه (ستون دسکتاپ × ردیف)
	// نه یک عدد دستیِ جدا — باگ قبلی دقیقاً همین بود: «تعداد محصول» می‌تونست
	// با گرید واقعی هم‌خونی نداشته باشه.
	$count = $cols_d * $rows;

	$source = $section['source'] ?? 'bestsellers';
	$args   = array(
		'status' => 'publish',
		'limit'  => $count,
	);
	if ( ! empty( $section['hide_out_of_stock'] ) ) {
		$args['stock_status'] = 'instock';
	}

	switch ( $source ) {
		case 'category':
			if ( empty( $section['category'] ) ) {
				return;
			}
			$term = get_term( (int) $section['category'], 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}
			$args['category'] = array( $term->slug );
			$args['orderby']  = 'date';
			$args['order']    = 'DESC';
			$products         = wc_get_products( $args );
			break;

		case 'brand':
			if ( empty( $section['brand'] ) || ! taxonomy_exists( 'product_brand' ) ) {
				return;
			}
			$term = get_term( (int) $section['brand'], 'product_brand' );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_brand',
					'field'    => 'term_id',
					'terms'    => (int) $section['brand'],
				),
			);
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			$products        = wc_get_products( $args );
			break;

		case 'manual':
			$ids = array_values( array_filter( array_map( 'absint', $section['product_ids'] ?? array() ) ) );
			if ( empty( $ids ) ) {
				return;
			}
			// همون ترتیبِ انتخاب‌شده در ادمین حفظ می‌شه (post__in به‌ترتیب include
			// مرتب می‌کنه، نه به‌ترتیب تاریخ/ID).
			$args['include'] = array_slice( $ids, 0, $count );
			$args['orderby'] = 'include';
			$products        = wc_get_products( $args );
			break;

		default: // bestsellers — 'popularity' (نه meta_value_num دستی) چون
			// خودِ ووکامرس این orderby رو با LEFT JOIN مدیریت می‌کنه؛ محصولی
			// که هنوز هیچ فروشی نداشته (متای total_sales اصلاً وجود نداره)
			// هم در نتیجه می‌مونه (با ۰ فروش، آخر لیست). باگ واقعیِ گزارش‌شده
			// («این بخش الان کار نمی‌کنه») دقیقاً همین بود: نسخه‌ی قبلی مستقیم
			// meta_key/meta_value_num می‌داد که توی یک فروشگاهِ تازه (بدون
			// هیچ سفارشِ ثبت‌شده‌ای، پس بدون هیچ total_sales meta ای) کوئری
			// عملاً هیچ محصولی برنمی‌گردوند — از دید ادمین یعنی «بخش خاموشه».
			$args['orderby'] = 'popularity';
			$products        = wc_get_products( $args );
	}

	if ( empty( $products ) ) {
		return;
	}
	$grid_class = 'jluxe-bp-' . $section['id'];

	// لینکِ «نمایش همه» — طبقِ منبعِ انتخاب‌شده، به آرشیوِ واقعیِ همون
	// دسته/برند می‌ره (نه همیشه صفحه‌ی کلیِ فروشگاه)، مگر اینکه کاربر خودش
	// لینکِ دستی وارد کرده باشه.
	$view_all = trim( (string) ( $section['view_all_link'] ?? '' ) );
	if ( '' === $view_all ) {
		$view_all = wc_get_page_permalink( 'shop' );
		if ( 'category' === $source && ! empty( $section['category'] ) ) {
			$vt = get_term( (int) $section['category'], 'product_cat' );
			if ( $vt && ! is_wp_error( $vt ) ) {
				$view_all = get_term_link( $vt );
			}
		} elseif ( 'brand' === $source && ! empty( $section['brand'] ) ) {
			$vt = get_term( (int) $section['brand'], 'product_brand' );
			if ( $vt && ! is_wp_error( $vt ) ) {
				$view_all = get_term_link( $vt );
			}
		}
	}
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<?php if ( ! empty( $section['title'] ) ) : ?>
			<div class="mb-4 flex items-center justify-between">
				<h2 class="text-h2 text-foreground"><?php echo esc_html( $section['title'] ); ?></h2>
				<a href="<?php echo esc_url( $view_all ); ?>" class="shrink-0 text-caption font-medium text-text-muted transition-colors hover:text-primary">نمایش همه</a>
			</div>
		<?php endif; ?>
		<?php
		/*
		 * طبقِ درخواستِ صریحِ کاربر («چیدمانِ آجری») — به‌جایِ کارتِ کاملِ
		 * محصول (content-product.php، با تصویرِ بزرگ/بج/ستاره)، یک ردیفِ
		 * جمع‌وجورِ افقی (تصویرِ کوچیک + نام + قیمت) توی یک گریدِ خط‌جداشده
		 * (بوردر به‌جایِ gap، دقیقاً مثلِ مرجعِ تصویریِ کاربر). خطوطِ جداکننده
		 * با nth-child خودِ CSS محاسبه می‌شن (نه PHP) چون تعدادِ ستون بینِ
		 * موبایل/تبلت/دسکتاپ فرق داره و این تنها راهیه که هر سه حالت رو
		 * بدونِ سه‌بار رندرِ جداگانه پوشش می‌ده.
		 *
		 * نکته‌ی مهم: به‌جایِ nth-last-child(-n+cols) از nth-child(n+start)
		 * استفاده شده — چون وقتی تعدادِ محصول بر تعدادِ ستونِ اون بریک‌پوینت
		 * بخش‌پذیر نباشه (مثلاً ۸ محصول روی ۳ ستونِ تبلت)، آخرین ردیف ناقصه و
		 * «N آیتمِ آخر» دیگه دقیقاً معادلِ «ردیفِ آخر» نیست؛ nth-last-child
		 * باعث می‌شد یک آیتم از ردیفِ ماقبلِ آخر هم border-bottom‌ش غلط صفر بشه.
		 */
		$total           = count( $products );
		$last_row_start  = static function ( int $cols ) use ( $total ): int {
			$in_last_row = $total % $cols;
			if ( 0 === $in_last_row ) {
				$in_last_row = $cols;
			}
			return $total - $in_last_row + 1;
		};
		$row_start_m = $last_row_start( $cols_m );
		$row_start_t = $last_row_start( $cols_t );
		$row_start_d = $last_row_start( $cols_d );
		?>
		<style>
			@media(max-width:639px){
				.<?php echo esc_attr( $grid_class ); ?>>*:nth-child(n+<?php echo esc_attr( (string) $row_start_m ); ?>){border-bottom-width:0}
				.<?php echo esc_attr( $grid_class ); ?>>*:not(:nth-child(<?php echo esc_attr( (string) $cols_m ); ?>n)){border-inline-end-width:1px}
			}
			@media(min-width:640px) and (max-width:1023px){
				.<?php echo esc_attr( $grid_class ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_t ); ?>,minmax(0,1fr))!important}
				.<?php echo esc_attr( $grid_class ); ?>>*:nth-child(n+<?php echo esc_attr( (string) $row_start_t ); ?>){border-bottom-width:0!important}
				.<?php echo esc_attr( $grid_class ); ?>>*:not(:nth-child(<?php echo esc_attr( (string) $cols_t ); ?>n)){border-inline-end-width:1px}
			}
			@media(min-width:1024px){
				.<?php echo esc_attr( $grid_class ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_d ); ?>,minmax(0,1fr))!important}
				.<?php echo esc_attr( $grid_class ); ?>>*:nth-child(n+<?php echo esc_attr( (string) $row_start_d ); ?>){border-bottom-width:0!important}
				.<?php echo esc_attr( $grid_class ); ?>>*:not(:nth-child(<?php echo esc_attr( (string) $cols_d ); ?>n)){border-inline-end-width:1px!important}
			}
			.<?php echo esc_attr( $grid_class ); ?>>*{border-color:hsl(var(--border))}
		</style>
		<div class="<?php echo esc_attr( $grid_class ); ?> grid grid-cols-<?php echo esc_attr( (string) $cols_m ); ?> overflow-hidden rounded-2xl border border-border bg-surface [&>*]:border-b [&>*]:border-border [&>*:last-child]:border-b-0">
			<?php foreach ( $products as $product ) : ?>
				<?php jluxe_render_homepage_brick_card( $product ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * کارتِ جمع‌وجورِ افقیِ «چیدمانِ آجری» — تصویرِ کوچیکِ مربعی + نام (دو خط) +
 * قیمت، داخلِ یک گریدِ خط‌جداشده (نه فاصله‌دار). طبقِ مرجعِ تصویریِ صریحِ
 * کاربر؛ عمداً بدونِ بج/ستاره/سواچ نگه داشته شده چون خودِ مرجع همینه —
 * این کارت برایِ نمایشِ فشرده و پرشمار طراحی شده، نه ویترینِ کاملِ محصول.
 */
function jluxe_render_homepage_brick_card( WC_Product $product ): void {
	$image_id     = $product->get_image_id();
	$image_url    = $image_id ? ( wp_get_attachment_image_url( $image_id, 'large' ) ?: wp_get_attachment_image_url( $image_id, 'full' ) ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	$image_srcset = $image_id ? wp_get_attachment_image_srcset( $image_id, 'large' ) : '';

	$is_variable   = $product->is_type( 'variable' );
	$regular_price = (float) $product->get_regular_price();
	$sale_price    = (float) $product->get_sale_price();
	$has_discount  = ! $is_variable && $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;
	$price         = $is_variable ? $product->get_variation_price( 'min' ) : ( $has_discount ? $sale_price : $regular_price );
	?>
	<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="group flex flex-col justify-between p-4 transition-colors hover:bg-muted/40">
		<div class="flex items-center gap-3">
			<div class="flex size-[65px] shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted/40">
				<img
					data-jluxe-card-main-img
					src="<?php echo esc_url( $image_url ); ?>"
					<?php if ( $image_srcset ) : ?>srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="65px"<?php endif; ?>
					alt="<?php echo esc_attr( $product->get_name() ); ?>"
					loading="lazy"
					data-jluxe-no-skeleton
					class="size-full object-contain transition-transform duration-300 group-hover:scale-105"
				/>
			</div>
			<h4 class="line-clamp-2 min-w-0 flex-1 pt-1 text-[12.5px] leading-5 text-text-secondary sm:text-[13px]"><?php echo esc_html( $product->get_name() ); ?></h4>
		</div>
		<div class="mt-2 flex items-center justify-end gap-1 text-foreground">
			<span class="text-[13px] font-bold"><?php echo esc_html( jluxe_fa_digits( number_format( (float) $price, 0 ) ) ); ?></span>
			<svg class="shrink-0 text-text-muted" width="16" height="13" viewBox="0 0 22 18" fill="none" aria-hidden="true"><path fill="currentColor" d="M16.898.75h-2.376a.688.688 0 0 0 0 1.376h2.376a.688.688 0 0 0 0-1.376ZM21.247 3.814c-.021-.375-.083-.868-.187-1.477a26 26 0 0 0-.187-1.005.658.658 0 0 0-.762-.465.663.663 0 0 0-.487.72c.064.32.125.639.186.98.099.552.159.98.18 1.282.02.375-.092.667-.337.876-.245.208-.67.312-1.274.312H6.673V3.47c0-.667-.12-1.258-.36-1.774a2.63 2.63 0 0 0-1.032-1.211A2.71 2.71 0 0 0 3.718.047c-.563 0-1.066.151-1.508.453S1.423.802 1.178 1.323c-.245.522-.367 1.1-.367 1.737 0 .938.268 1.667.805 2.188.537.521 1.243.782 2.118.782h1.688v.094c0 .25-.099.448-.297.594-.198.146-.49.271-.876.375-.386.104-1.032.25-1.938.438l-.021.004a.688.688 0 1 0 .28.76c.148-.03.295-.06.443-.089.98-.198 1.722-.396 2.228-.594.505-.198.873-.456 1.102-.774.229-.318.344-.748.344-1.29v-.094h11.745c.636 0 1.17-.125 1.602-.375.433-.25.75-.576.954-.977a2.05 2.05 0 0 0 .275-1.008ZM5.453 5.033H3.734c-.594 0-1.026-.117-1.297-.352-.271-.234-.407-.638-.407-1.211 0-.615.149-1.107.446-1.477.297-.37.711-.555 1.243-.555.573 0 1.005.18 1.297.539.292.36.437.857.437 1.494v2.562Z"/><path fill="currentColor" d="M6.235 12.841a.781.781 0 1 0-1.563 0 .781.781 0 0 0 1.563 0ZM20.772 12.35c-.229-.537-.552-.963-.969-1.282a2.36 2.36 0 0 0-1.437-.477c-.678 0-1.256.233-1.735.696-.48.463-.834 1.102-1.063 1.914l-.5 1.797a.63.63 0 0 1-.198.293.62.62 0 0 1-.402.146c-.469 0-.805-.049-1.008-.148-.203-.099-.339-.274-.407-.524a4.5 4.5 0 0 1-.104-.72l-.016-4.017c0-.396-.068-.744-.203-1.048a1.62 1.62 0 0 0-.66-.71 1.98 1.98 0 0 0-.95-.26h-.516c-.458 0-.836.089-1.133.261a1.62 1.62 0 0 0-.664.703 2.4 2.4 0 0 0-.203.997l.016 4.36c0 .605-.084 1.082-.25 1.43a1.42 1.42 0 0 1-.936.766c-.396.163-.948.242-1.655.242h-.227c-.646 0-1.178-.134-1.594-.406a2.35 2.35 0 0 1-.923-1.117 4.1 4.1 0 0 1-.293-1.535c0-.218.034-.514.076-.796a.71.71 0 0 0-.62-.73.71.71 0 0 0-.796.53 6.5 6.5 0 0 0-.075.83c0 .792.154 1.538.461 2.235a3.5 3.5 0 0 0 1.396 1.688c.604.428 1.339.641 2.203.641h.227c.928 0 1.686-.156 2.275-.469a2.72 2.72 0 0 0 1.302-1.328 4.6 4.6 0 0 0 .396-2.047l-.015-4.362c0-.239.049-.4.148-.484.099-.084.3-.126.602-.126h.516c.281 0 .474.047.578.14.104.094.156.25.156.469l.016 4.017c0 .71.08 1.304.242 1.782a2.16 2.16 0 0 0 .84 1.117c.397.266.949.399 1.657.399.303 0 .594-.068.876-.203.281-.135.526-.322.734-.563l.063.032c.812.416 1.417.702 1.813.852a2.9 2.9 0 0 0 1.187.226c.396 0 .753-.111 1.102-.336.35-.224.634-.583.853-1.078.219-.495.328-1.128.328-1.9 0-.626-.115-1.206-.344-1.743Zm-1.14 3.25c-.178.267-.443.4-.798.4-.27 0-.557-.06-.86-.18-.302-.12-.807-.357-1.516-.712l-.11-.062.407-1.47c.146-.531.357-.927.633-1.187.276-.26.601-.39.977-.39.5 0 .88.185 1.14.554.261.37.392.883.392 1.54 0 .74-.089 1.242-.266 1.507Z"/></svg>
		</div>
	</a>
	<?php
}

/**
 * پنل‌های پیشنهادی — بازطراحی‌شده طبق تصویر مرجع کاربر: به‌جای تب (فقط
 * یک پنل هم‌زمان دیده می‌شد)، حالا همه‌ی پنل‌ها کنار هم (۳ تا در ردیف در
 * دسکتاپ) با یک هدر (عنوان دسته + دکمه‌ی «بیشتر») و گرید ۲ستونه‌ی محصول
 * زیرش نشون داده می‌شن.
 *
 * طبقِ درخواستِ صریحِ بعدیِ کاربر («باید توی یک خط باشن، بشه با دست
 * جابجاشون کرد، نه اینکه برن زیر هم») گریدِ wrap-شونده‌ی قبلی (grid-cols-1
 * sm:grid-cols-2 lg:grid-cols-3 — که با بیش از ۳/۲/۱ پنل به ردیفِ بعدی
 * می‌رفت) به همون کاروسلِ افقیِ استاندارد سایت تبدیل شد
 * (data-jluxe-scroller + .jluxe-scroll-x؛ همون زیرساختِ درگ‌با‌ماوس/فلش‌ها/
 * لمس که برای کاروسلِ محصولات هم استفاده می‌شه — jluxe_scroll_arrows() +
 * assets/js/homepage.js). عرضِ هر پنل عمداً طوری انتخاب شده که در دسکتاپ
 * دقیقاً ۳ پنلِ کامل + کمی از پنلِ چهارم (لبه‌ی بریده + هاله‌ی نرم) دیده
 * بشه، در موبایل هم یک پنلِ کامل + پیش‌نمایشِ پنلِ بعدی.
 */
function jluxe_render_homepage_recommended_panels( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$sort_map = array(
		'date'       => array( 'orderby' => 'date', 'order' => 'DESC' ),
		'rating'     => array( 'orderby' => 'rating', 'order' => 'DESC' ),
		// 'popularity' مثل brick_products — روی total_sales با LEFT JOIN
		// کار می‌کنه، پس محصولِ بدون فروش هم از نتیجه حذف نمی‌شه.
		'popularity' => array( 'orderby' => 'popularity', 'order' => 'DESC' ),
		'rand'       => array( 'orderby' => 'rand' ),
	);

	$panels = array();
	foreach ( $section['panels'] ?? array() as $panel ) {
		if ( empty( $panel['category'] ) ) {
			continue;
		}
		$term = get_term( (int) $panel['category'], 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		$sort_args = $sort_map[ $panel['sort'] ?? 'date' ] ?? $sort_map['date'];
		$products  = wc_get_products(
			array_merge(
				array(
					'status'   => 'publish',
					'limit'    => max( 2, min( 8, (int) ( $panel['count'] ?? 4 ) ) ),
					'category' => array( $term->slug ),
				),
				$sort_args
			)
		);
		if ( empty( $products ) ) {
			continue;
		}
		if ( empty( $panel['title'] ) ) {
			$panel['title'] = $term->name;
		}
		$panel['products'] = $products;
		$panel['term']      = $term;
		$panels[]           = $panel;
	}
	if ( empty( $panels ) ) {
		return;
	}
	$panel_scroller_class = count( $panels ) > 1 ? ' jluxe-recommended-panels-scroller' : '';
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<?php if ( ! empty( $section['title'] ) ) : ?>
			<h2 class="mb-4 text-h2 text-foreground"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php endif; ?>
		<?php
		/*
		 * R111 — زیرِ 640px، یک پنلِ کامل به‌اضافهٔ 52px از پنلِ بعدی
		 * دیده می‌شود تا وجودِ اسلاید بعد معلوم باشد؛ اندازه از عرضِ همین
		 * قاب و gap-4 محاسبه شده و فقط وقتی بیش از یک پنل داریم اعمال می‌شود.
		 * Snap/drag/touch و فلش‌های موجود باقی می‌مانند. در دسکتاپ (lg+)
		 * پنل‌ها مثل قبل هم‌عرض و بدونِ کاروسل‌اند.
		 */
		?>
		<div class="relative">
			<div data-jluxe-scroller class="jluxe-scroll-x jluxe-hide-scrollbar flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2 lg:overflow-visible lg:snap-none<?php echo esc_attr( $panel_scroller_class ); ?>">
				<?php foreach ( $panels as $panel ) :
				$term_link   = get_term_link( $panel['term'] );
				$button_link = ! empty( $panel['button_link'] ) ? $panel['button_link'] : ( is_wp_error( $term_link ) ? '' : $term_link );
				$arrow_style = ! empty( $panel['button_color'] ) ? ' style="background-color:' . esc_attr( $panel['button_color'] ) . '"' : '';
				?>
				<div class="jluxe-home-panel snap-start shrink-0 basis-full rounded-3xl bg-surface p-4 shadow-[0_2px_18px_rgba(0,0,0,0.05)] lg:basis-0 lg:flex-1">
					<div class="mb-3">
						<div class="min-w-0 text-right">
							<h3 class="min-w-0 text-base font-extrabold text-foreground sm:text-lg">
								<?php if ( $button_link ) : ?>
									<a href="<?php echo esc_url( $button_link ); ?>" aria-label="<?php echo esc_attr( sprintf( 'مشاهده‌ی محصولاتِ دسته‌ی %s', $panel['title'] ) ); ?>" class="jluxe-home-panel__category-link">
										<span class="jluxe-home-panel__category-title"><?php echo esc_html( $panel['title'] ); ?></span>
										<span class="jluxe-home-panel__category-arrow" aria-hidden="true"<?php echo $arrow_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
											<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M19 12H5m7 7-7-7 7-7"></path></svg>
										</span>
									</a>
								<?php else : ?>
									<?php echo esc_html( $panel['title'] ); ?>
								<?php endif; ?>
							</h3>
							<p class="mt-1 truncate text-[12px] text-text-muted sm:text-[13px]"><?php echo esc_html( ! empty( $panel['subtitle'] ) ? $panel['subtitle'] : 'محصولات پیشنهادی از دسته' ); ?></p>
						</div>
					</div>
					<?php
					/*
					 * طبقِ درخواستِ صریحِ کاربر («دقیقاً همین طراحیِ jluxe.ir» + یک
					 * مرجعِ HTML/کلاسِ کاملِ Tailwind پیست شد) — این دیگه کارتِ
					 * استانداردِ content-product.php نیست (اون برای آرشیوِ فروشگاه/
					 * محصولاتِ مرتبط باقی می‌مونه، دست‌نخورده). یک کارتِ کاملاً
					 * جداگونه‌ی مخصوصِ همین بخش: گریدِ ۲ستونه بدونِ gap، با
					 * جداکننده‌ی خط بینِ سلول‌ها (به‌جایِ فاصله)، بجِ تخفیف، سواچِ
					 * رنگ (اگه محصول متغیره)، آیکونِ تکیِ امتیاز + تعدادِ دیدگاه،
					 * و قیمت با آیکونِ تومان — jluxe_render_homepage_recommended_card
					 * پایین‌ترِ همین فایل.
					 */
					$rp_total      = count( $panel['products'] );
					$rp_total_rows = (int) ceil( $rp_total / 2 );
					?>
					<div class="grid grid-cols-2">
						<?php foreach ( $panel['products'] as $rp_i => $rp_product ) :
							$rp_row        = intdiv( $rp_i, 2 );
							$rp_col        = $rp_i % 2;
							$rp_is_last_row = ( $rp_row === $rp_total_rows - 1 );
							$rp_is_last_col = ( 1 === $rp_col ) || ( $rp_i === $rp_total - 1 );
							jluxe_render_homepage_recommended_card( $rp_product, ! $rp_is_last_col, ! $rp_is_last_row );
						endforeach;
						?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
			<?php jluxe_scroll_arrows(); ?>
		</div>
	</section>
	<?php
}

/**
 * یک کارتِ محصول برایِ بخشِ «پنل‌های پیشنهادی» — دقیقاً مطابقِ طراحیِ
 * ارسالیِ کاربر (رفرنسِ واقعیِ jluxe.ir): گریدِ ۲ستونه با جداکننده‌ی خط
 * (نه gap)، بجِ تخفیف بالا-راست، سواچِ رنگ بالا-چپ (فقط محصولِ متغیر با
 * ویژگیِ رنگ)، تصویر بدون تغییرِ رنگ و با object-contain تا کلِ محصول دیده بشه (نه object-cover)،
 * عنوانِ تک‌خط، و ردیفِ امتیاز/قیمت.
 */
function jluxe_render_homepage_recommended_card( WC_Product $product, bool $border_end, bool $border_bottom ): void {
	$image_id     = $product->get_image_id();
	$image_url    = $image_id ? ( wp_get_attachment_image_url( $image_id, 'large' ) ?: wp_get_attachment_image_url( $image_id, 'full' ) ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	$image_srcset = $image_id ? wp_get_attachment_image_srcset( $image_id, 'large' ) : '';

	$is_variable   = $product->is_type( 'variable' );
	$regular_price = (float) $product->get_regular_price();
	$sale_price    = (float) $product->get_sale_price();
	$has_discount  = ! $is_variable && $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;
	$discount_pct  = $has_discount ? (int) round( 100 - ( $sale_price / $regular_price ) * 100 ) : 0;
	$price         = $is_variable ? $product->get_variation_price( 'min' ) : ( $has_discount ? $sale_price : $regular_price );
	$review_count  = (int) $product->get_review_count();

	// سواچ‌های ویژگی رنگ (حداکثر ۴) از همان resolver مشترکِ صفحه‌ی محصول،
	// quick-pick و مشخصات فنی؛ برای محصول ساده هم از termهایِ اختصاص‌داده‌شده
	// خوانده می‌شوند، نه این‌که فقط محصول متغیر را پوشش بدهیم.
	$swatches = function_exists( 'jluxe_product_color_swatch_options' )
		? array_slice( jluxe_product_color_swatch_options( $product ), 0, 4 )
		: array();

	$border_classes = array( 'border-border' );
	if ( $border_end ) {
		$border_classes[] = 'border-e';
	}
	if ( $border_bottom ) {
		$border_classes[] = 'border-b';
	}
	?>
	<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="group relative flex flex-col p-3 sm:p-4 <?php echo esc_attr( implode( ' ', $border_classes ) ); ?>">
		<div class="relative h-6">
			<?php if ( $has_discount ) : ?>
				<span dir="ltr" class="absolute end-0 top-0 inline-flex w-fit items-center gap-0.5 rounded-lg bg-primary px-2 py-1 text-[11px] font-bold text-primary-foreground">
					<span><?php echo esc_html( jluxe_fa_digits( $discount_pct ) ); ?></span><span>٪</span>
				</span>
			<?php endif; ?>
			<?php if ( ! empty( $swatches ) ) : ?>
				<div class="absolute start-0 top-0 z-10 flex flex-col items-center gap-1" role="group" aria-label="<?php echo esc_attr( 'رنگ‌های ' . $product->get_name() ); ?>">
					<?php foreach ( $swatches as $rp_swatch ) : ?>
						<?php if ( 'color' === $rp_swatch['type'] ) : ?>
							<span class="h-2.5 w-2.5 rounded-full border border-border" role="img" title="<?php echo esc_attr( $rp_swatch['label'] ); ?>" aria-label="<?php echo esc_attr( $rp_swatch['label'] ); ?>" style="background:<?php echo esc_attr( $rp_swatch['value'] ); ?>"></span>
						<?php elseif ( 'image' === $rp_swatch['type'] ) : ?>
							<span class="h-2.5 w-2.5 overflow-hidden rounded-full border border-border" role="img" title="<?php echo esc_attr( $rp_swatch['label'] ); ?>" aria-label="<?php echo esc_attr( $rp_swatch['label'] ); ?>"><img src="<?php echo esc_url( $rp_swatch['value'] ); ?>" alt="" loading="lazy" /></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="my-2 flex h-24 items-center justify-center sm:h-28">
			<img
				src="<?php echo esc_url( $image_url ); ?>"
				<?php if ( $image_srcset ) : ?>srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="(max-width:639px) 42vw, (max-width:1023px) 28vw, 240px"<?php endif; ?>
				alt="<?php echo esc_attr( $product->get_name() ); ?>"
				loading="lazy"
				data-jluxe-no-skeleton
				class="max-h-full max-w-full rounded-[15px] object-contain transition-transform duration-300 group-hover:scale-105"
			/>
		</div>

		<h3 class="line-clamp-1 text-right text-[12px] leading-6 text-text-secondary sm:text-[13px]"><?php echo esc_html( $product->get_name() ); ?></h3>

		<div class="mt-2 flex items-center justify-between">
			<div class="flex items-center gap-1">
				<svg class="size-[15px] shrink-0 text-boom-star" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m7.625 6.4l2.8-3.625q.3-.4.713-.587T12 2t.863.188t.712.587l2.8 3.625l4.25 1.425q.65.2 1.025.738t.375 1.187q0 .3-.088.6t-.287.575l-2.75 3.9l.1 4.1q.025.875-.575 1.475t-1.4.6q-.05 0-.55-.075L12 19.675l-4.475 1.25q-.125.05-.275.063T6.975 21q-.8 0-1.4-.6T5 18.925l.1-4.125l-2.725-3.875q-.2-.275-.288-.575T2 9.75q0-.625.363-1.162t1.012-.763z"/></svg>
				<span class="text-[13px] font-medium text-text-secondary"><?php echo esc_html( jluxe_fa_digits( (string) $review_count ) ); ?></span>
			</div>
			<div class="flex items-center gap-1 text-foreground">
				<span class="text-[14px] font-bold sm:text-[15px]"><?php echo esc_html( jluxe_fa_digits( number_format( (float) $price, 0 ) ) ); ?></span>
				<svg class="shrink-0 text-text-muted" width="18" height="15" viewBox="0 0 22 18" fill="none" aria-hidden="true"><path fill="currentColor" d="M16.898.75h-2.376a.688.688 0 0 0 0 1.376h2.376a.688.688 0 0 0 0-1.376ZM21.247 3.814c-.021-.375-.083-.868-.187-1.477a26 26 0 0 0-.187-1.005.658.658 0 0 0-.762-.465.663.663 0 0 0-.487.72c.064.32.125.639.186.98.099.552.159.98.18 1.282.02.375-.092.667-.337.876-.245.208-.67.312-1.274.312H6.673V3.47c0-.667-.12-1.258-.36-1.774a2.63 2.63 0 0 0-1.032-1.211A2.71 2.71 0 0 0 3.718.047c-.563 0-1.066.151-1.508.453S1.423.802 1.178 1.323c-.245.522-.367 1.1-.367 1.737 0 .938.268 1.667.805 2.188.537.521 1.243.782 2.118.782h1.688v.094c0 .25-.099.448-.297.594-.198.146-.49.271-.876.375-.386.104-1.032.25-1.938.438l-.021.004a.688.688 0 1 0 .28.76c.148-.03.295-.06.443-.089.98-.198 1.722-.396 2.228-.594.505-.198.873-.456 1.102-.774.229-.318.344-.748.344-1.29v-.094h11.745c.636 0 1.17-.125 1.602-.375.433-.25.75-.576.954-.977a2.05 2.05 0 0 0 .275-1.008ZM5.453 5.033H3.734c-.594 0-1.026-.117-1.297-.352-.271-.234-.407-.638-.407-1.211 0-.615.149-1.107.446-1.477.297-.37.711-.555 1.243-.555.573 0 1.005.18 1.297.539.292.36.437.857.437 1.494v2.562Z"/><path fill="currentColor" d="M6.235 12.841a.781.781 0 1 0-1.563 0 .781.781 0 0 0 1.563 0ZM20.772 12.35c-.229-.537-.552-.963-.969-1.282a2.36 2.36 0 0 0-1.437-.477c-.678 0-1.256.233-1.735.696-.48.463-.834 1.102-1.063 1.914l-.5 1.797a.63.63 0 0 1-.198.293.62.62 0 0 1-.402.146c-.469 0-.805-.049-1.008-.148-.203-.099-.339-.274-.407-.524a4.5 4.5 0 0 1-.104-.72l-.016-4.017c0-.396-.068-.744-.203-1.048a1.62 1.62 0 0 0-.66-.71 1.98 1.98 0 0 0-.95-.26h-.516c-.458 0-.836.089-1.133.261a1.62 1.62 0 0 0-.664.703 2.4 2.4 0 0 0-.203.997l.016 4.36c0 .605-.084 1.082-.25 1.43a1.42 1.42 0 0 1-.936.766c-.396.163-.948.242-1.655.242h-.227c-.646 0-1.178-.134-1.594-.406a2.35 2.35 0 0 1-.923-1.117 4.1 4.1 0 0 1-.293-1.535c0-.218.034-.514.076-.796a.71.71 0 0 0-.62-.73.71.71 0 0 0-.796.53 6.5 6.5 0 0 0-.075.83c0 .792.154 1.538.461 2.235a3.5 3.5 0 0 0 1.396 1.688c.604.428 1.339.641 2.203.641h.227c.928 0 1.686-.156 2.275-.469a2.72 2.72 0 0 0 1.302-1.328 4.6 4.6 0 0 0 .396-2.047l-.015-4.362c0-.239.049-.4.148-.484.099-.084.3-.126.602-.126h.516c.281 0 .474.047.578.14.104.094.156.25.156.469l.016 4.017c0 .71.08 1.304.242 1.782a2.16 2.16 0 0 0 .84 1.117c.397.266.949.399 1.657.399.303 0 .594-.068.876-.203.281-.135.526-.322.734-.563l.063.032c.812.416 1.417.702 1.813.852a2.9 2.9 0 0 0 1.187.226c.396 0 .753-.111 1.102-.336.35-.224.634-.583.853-1.078.219-.495.328-1.128.328-1.9 0-.626-.115-1.206-.344-1.743Zm-1.14 3.25c-.178.267-.443.4-.798.4-.27 0-.557-.06-.86-.18-.302-.12-.807-.357-1.516-.712l-.11-.062.407-1.47c.146-.531.357-.927.633-1.187.276-.26.601-.39.977-.39.5 0 .88.185 1.14.554.261.37.392.883.392 1.54 0 .74-.089 1.242-.266 1.507Z"/></svg>
			</div>
		</div>
	</a>
	<?php
}

function jluxe_render_homepage_trust( array $section ): void {
	$cards = jluxe_get_theme_settings()['footer']['feature_cards'];
	$cards = array_filter( $cards, fn( $c ) => ! empty( $c['enabled'] ) );
	if ( empty( $cards ) ) {
		return;
	}
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-8">
		<?php if ( ! empty( $section['title'] ) ) : ?>
			<h2 class="mb-4 text-h2 text-foreground"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php endif; ?>
		<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
			<?php foreach ( $cards as $card ) : ?>
				<div class="flex flex-col items-center gap-1.5 rounded-xl border border-border bg-surface p-4 text-center">
					<strong class="text-small text-foreground"><?php echo esc_html( $card['title'] ); ?></strong>
					<span class="text-caption text-text-muted"><?php echo esc_html( $card['subtitle'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
