<?php
defined( 'ABSPATH' ) || exit;

/** Homepage administration UI and form helpers. */



function jluxe_render_homepage_page(): void {
	$status   = jluxe_handle_generic_settings_save( 'jluxe-homepage' );
	$settings = jluxe_get_fresh_settings();
	$types    = jluxe_homepage_section_types();

	jluxe_settings_page_shell( 'صفحه اصلی', 'jluxe-homepage', $status, function () use ( $settings, $types ) {
		?>
		<p class="description">بخش‌ها رو با کشیدن (☰) جابه‌جا کن، فعال/غیرفعال کن، یا حذف/تکثیر کن. ترتیب همینی که این‌جا می‌بینی دقیقاً همون چیزیه که روی صفحه‌ی اصلی نمایش داده می‌شه.</p>

		<form method="post" id="jluxe-homepage-form">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<div class="jluxe-hb-mobile-category-hint-setting">
				<strong>راهنمای حرکت ردیف دسته‌بندی در موبایل</strong>
				<?php jluxe_hb_field_checkbox(
					'homepage',
					'mobile_category_swipe_hint_enabled',
					'نمایش یک‌بارهٔ حرکت کوتاه برای نشان‌دادن امکان کشیدن ردیف',
					! array_key_exists( 'mobile_category_swipe_hint_enabled', $settings['homepage'] ) || ! empty( $settings['homepage']['mobile_category_swipe_hint_enabled'] ),
					true
				); ?>
				<p class="description">این راهنما فقط برای دسته‌بندی‌هایی اجرا می‌شود که چیدمان موبایلشان «نوار افقی» است؛ حرکت با اولین لمس متوقف می‌شود و به تنظیم حرکتِ کاهش‌یافتهٔ دستگاه احترام می‌گذارد.</p>
			</div>

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
	$label   = $types[ $type ] ?? ( 'cta' === $type ? 'دعوت به اقدام' : $type );
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
				case 'container':
					printf( '<input type="hidden" name="%s[children_json]" value="%s" />', esc_attr( $name ), esc_attr( wp_json_encode( $section['children'] ?? array() ) ) );
					$layout = jluxe_builder_container_options( $section );
					jluxe_hb_field_number( $name, 'columns', 'ستون‌های دسکتاپ', $layout['columns'], 1, 6 );
					jluxe_hb_field_text( $name, 'column_ratios', 'نسبت ستون‌ها به ترتیب راست به چپ (با ویرگول انگلیسی؛ مثال 66.7,33.3)', $layout['column_ratios'] );
					jluxe_hb_field_select( $name, 'vertical_align', 'تراز عمودی', $layout['vertical_align'], array( 'start' => 'بالا', 'center' => 'وسط', 'end' => 'پایین', 'stretch' => 'کشیده' ) );
					jluxe_hb_field_select( $name, 'stack_on', 'زیر هم شدن ستون‌ها', $layout['stack_on'], array( 'mobile' => 'موبایل', 'tablet' => 'تبلت و موبایل', 'never' => 'هرگز' ) );
					jluxe_hb_field_number( $name, 'mobile_columns', 'ستون‌های موبایل', $layout['mobile_columns'], 1, 2 );
					jluxe_hb_field_number( $name, 'gap', 'فاصلهٔ بین المان‌ها (px)', $layout['gap'], 0, 100 );
					jluxe_hb_field_number( $name, 'max_width', 'حداکثر عرض کانتینر (px)', $layout['max_width'], 280, 1920 );
					jluxe_hb_field_checkbox( $name, 'reverse_mobile', 'ترتیب معکوس در موبایل (با یک ستون)', $layout['reverse_mobile'] );
					break;
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
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'link', 'لینک دکمهٔ استوری (اختیاری)', $item['link'] ?? '' ); ?>
							<?php jluxe_hb_field_text( "{$name}[items][{$s}]", 'button_text', 'متن دکمه (خالی = مشاهده محصول)', $item['button_text'] ?? '' ); ?>
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
					?>
					<p class="description">منبعِ «ویژه» از ستارهٔ خودِ ووکامرس استفاده می‌کند؛ در صفحهٔ اصلی فقط محصولاتِ منتشرشده و موجود نمایش داده می‌شوند و محصولِ ناموجود از ردیف حذف می‌شود.</p>
					<?php
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
					jluxe_hb_field_number( $name, 'section_radius', 'گردی کادر اصلی (px)', $section['section_radius'] ?? 20, 12, 56 );
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
					jluxe_hb_field_color( $name, 'card_text_color', 'رنگ عنوان زیر تصویر', $section['card_text_color'] ?? '#1F2937' );
					jluxe_hb_field_select( $name, 'image_shape', 'شکل تصویر', $section['image_shape'] ?? 'none', array( 'none' => 'بدون قاب/فرم', 'square' => 'مربع با گوشه‌های گرد', 'circle' => 'دایره‌ای' ) );
					jluxe_hb_field_select( $name, 'image_frame_mode', 'کادر دور تصویر', $section['image_frame_mode'] ?? 'none', array( 'none' => 'بدون کادر', 'transparent' => 'کادر شفاف', 'color' => 'کادر رنگی' ) );
					?>
					<div class="jluxe-showcase-frame-color" data-showcase-frame-mode="color"<?php echo 'color' === ( $section['image_frame_mode'] ?? 'none' ) ? '' : ' style="display:none" aria-hidden="true"'; ?>>
						<?php jluxe_hb_field_color( $name, 'image_frame_color', 'رنگ کادر تصویر', $section['image_frame_color'] ?? '#E5E7EB' ); ?>
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

				case 'brand_video_campaigns':
					jluxe_hb_field_text( $name, 'title', 'عنوان بخش', $section['title'] ?? 'تبلیغات ویدیویی برندها' );
					jluxe_hb_field_text( $name, 'view_all_text', 'متن لینک مشاهده همه', $section['view_all_text'] ?? 'مشاهده همه' );
					jluxe_hb_field_color( $name, 'background_color', 'رنگ پس‌زمینهٔ بخش', (string) ( $section['background_color'] ?? '#7E0820' ) );
					$brand_options = jluxe_hb_brand_options();
					$category_options = jluxe_hb_category_options();
					echo '<p class="description">هر کمپین می‌تواند برای یک برند یا دسته‌بندی باشد. رسانهٔ تصویر می‌تواند عکس معمولی یا GIF باشد؛ برای ویدیو، پوستر اختیاری است. فایل موبایل جداگانه اختیاری است تا در صفحه‌های کوچک نسخهٔ سبک‌تر نمایش داده شود.</p>';
					for ( $ci = 0; $ci < 6; $ci++ ) :
						$campaign = $section['campaigns'][ $ci ] ?? array();
						$target_type = in_array( $campaign['target_type'] ?? '', array( 'brand', 'category', 'product' ), true ) ? $campaign['target_type'] : 'brand';
						$media_type = 'image' === ( $campaign['media_type'] ?? '' ) ? 'image' : 'video';
						$media_id = (int) ( $campaign['media_id'] ?? ( $campaign['video_id'] ?? 0 ) );
						$campaign_bg = sanitize_hex_color( (string) ( $campaign['bg_color'] ?? '' ) ) ?: ( sanitize_hex_color( (string) ( $section['background_color'] ?? '' ) ) ?: '#7E0820' );
						$target_toggle_id = 'bvc-target-' . sanitize_key( (string) ( $section['id'] ?? 'new' ) ) . '-' . $ci;
						$media_toggle_id = 'bvc-media-' . sanitize_key( (string) ( $section['id'] ?? 'new' ) ) . '-' . $ci;
						?>
						<details class="jluxe-hb-campaign-details" <?php echo 0 === $ci ? 'open' : ''; ?>>
							<summary><strong>کمپین <?php echo esc_html( jluxe_fa_digits( $ci + 1 ) ); ?></strong></summary>
						<fieldset class="jluxe-hero-admin jluxe-brand-video-campaign-admin">
							<legend><strong>تنظیمات کمپین</strong></legend>
							<p><label>نوع تبلیغ<br /><select name="<?php echo esc_attr( $name ); ?>[campaigns][<?php echo esc_attr( (string) $ci ); ?>][target_type]" data-jluxe-bvc-target-select="<?php echo esc_attr( $target_toggle_id ); ?>"><option value="brand" <?php selected( $target_type, 'brand' ); ?>>برند</option><option value="category" <?php selected( $target_type, 'category' ); ?>>دسته‌بندی</option><option value="product" <?php selected( $target_type, 'product' ); ?>>محصول</option></select></label></p>
							<div data-jluxe-bvc-target="brand" data-jluxe-bvc-for="<?php echo esc_attr( $target_toggle_id ); ?>"><?php jluxe_hb_field_select( "{$name}[campaigns][{$ci}]", 'brand_id', 'انتخاب برند', (string) ( $campaign['brand_id'] ?? ( 'brand' === $target_type ? ( $campaign['term_id'] ?? 0 ) : 0 ) ), $brand_options ); ?></div>
							<div data-jluxe-bvc-target="category" data-jluxe-bvc-for="<?php echo esc_attr( $target_toggle_id ); ?>"><?php jluxe_hb_field_select( "{$name}[campaigns][{$ci}]", 'category_id', 'انتخاب دسته‌بندی', (string) ( $campaign['category_id'] ?? ( 'category' === $target_type ? ( $campaign['term_id'] ?? 0 ) : 0 ) ), $category_options ); ?></div>
							<div data-jluxe-bvc-target="product" data-jluxe-bvc-for="<?php echo esc_attr( $target_toggle_id ); ?>"><p><label>محصول اصلی کمپین<br /><select name="<?php echo esc_attr( $name ); ?>[campaigns][<?php echo esc_attr( (string) $ci ); ?>][product_id]" class="wc-product-search" style="width:100%" data-placeholder="جستجوی محصول…" data-action="woocommerce_json_search_products"><?php $primary_product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) ( $campaign['product_id'] ?? 0 ) ) : false; if ( $primary_product ) : ?><option value="<?php echo esc_attr( (string) $primary_product->get_id() ); ?>" selected><?php echo esc_html( $primary_product->get_name() ); ?></option><?php endif; ?></select></label></p></div>
							<label>لوگوی کمپین (اختیاری)</label>
							<?php jluxe_render_media_field( "{$name}[campaigns][{$ci}][logo_id]", (int) ( $campaign['logo_id'] ?? 0 ), 'لوگو انتخاب نشده', 'thumbnail', '48px', 'image' ); ?>
							<p><label>نوع رسانه<br /><select name="<?php echo esc_attr( $name ); ?>[campaigns][<?php echo esc_attr( (string) $ci ); ?>][media_type]" data-jluxe-bvc-media-select="<?php echo esc_attr( $media_toggle_id ); ?>"><option value="video" <?php selected( $media_type, 'video' ); ?>>ویدیو</option><option value="image" <?php selected( $media_type, 'image' ); ?>>عکس یا GIF</option></select></label></p>
							<div data-jluxe-bvc-media-for="<?php echo esc_attr( $media_toggle_id ); ?>">
								<label>رسانهٔ دسکتاپ</label>
								<?php jluxe_render_media_field( "{$name}[campaigns][{$ci}][media_id]", $media_id, 'رسانه‌ای انتخاب نشده', 'full', '90px', $media_type ); ?>
								<label>نسخهٔ موبایل (اختیاری)</label>
								<?php jluxe_render_media_field( "{$name}[campaigns][{$ci}][mobile_media_id]", (int) ( $campaign['mobile_media_id'] ?? 0 ), 'از رسانهٔ دسکتاپ استفاده می‌شود', 'full', '70px', $media_type ); ?>
							</div>
							<div data-jluxe-bvc-media-poster="<?php echo esc_attr( $media_toggle_id ); ?>" <?php echo 'video' === $media_type ? '' : 'hidden'; ?>>
								<label>پوستر دسکتاپ ویدیو (اختیاری)</label>
								<?php jluxe_render_media_field( "{$name}[campaigns][{$ci}][poster_id]", (int) ( $campaign['poster_id'] ?? 0 ), 'پوستری انتخاب نشده', 'medium', '60px', 'image' ); ?>
								<label>پوستر موبایل ویدیو (اختیاری)</label>
								<?php jluxe_render_media_field( "{$name}[campaigns][{$ci}][mobile_poster_id]", (int) ( $campaign['mobile_poster_id'] ?? 0 ), 'پوستر موبایل انتخاب نشده', 'medium', '60px', 'image' ); ?>
							</div>
							<?php jluxe_hb_field_text( "{$name}[campaigns][{$ci}]", 'link', 'لینک مقصد (اختیاری)', $campaign['link'] ?? '' ); ?>
							<div class="jluxe-bvc-bg-control"><label>رنگ پس‌زمینهٔ کمپین (اختیاری)<br /><input type="color" value="<?php echo esc_attr( $campaign_bg ); ?>" data-jluxe-bvc-bg-picker aria-label="رنگ پس‌زمینهٔ کمپین" /></label><input type="hidden" name="<?php echo esc_attr( $name ); ?>[campaigns][<?php echo esc_attr( (string) $ci ); ?>][bg_color]" value="<?php echo esc_attr( (string) ( $campaign['bg_color'] ?? '' ) ); ?>" data-jluxe-bvc-bg-value /><button type="button" class="button-link" data-jluxe-bvc-bg-reset data-section-color="<?php echo esc_attr( sanitize_hex_color( (string) ( $section['background_color'] ?? '' ) ) ?: '#7E0820' ); ?>">استفاده از رنگ پس‌زمینهٔ بخش</button></div>
							<p><label>محصولات این کمپین<br />
							<select name="<?php echo esc_attr( $name ); ?>[campaigns][<?php echo esc_attr( (string) $ci ); ?>][product_ids][]" multiple="multiple" class="wc-product-search" style="width:100%" data-placeholder="جستجو و انتخاب محصولات…" data-action="woocommerce_json_search_products">
							<?php foreach ( (array) ( $campaign['product_ids'] ?? array() ) as $product_id ) : $product_obj = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : false; if ( $product_obj ) : ?>
							<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected><?php echo esc_html( $product_obj->get_name() ); ?></option>
							<?php endif; endforeach; ?>
							</select></label></p>
						</fieldset>
						</details>
					<?php endfor;
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
					<p class="description">در حالت «پرفروش‌ترین‌ها» فقط کالاهای دارای فروش ثبت‌شده نمایش داده می‌شوند؛ برای نمایش کالای تازه یا بدون فروش، دسته‌بندی، برند یا فهرست دستی را انتخاب کنید.</p>

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
							'boxed1489' => 'محدود به کانتینر (۱۴۶۲px)',
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
						<details class="jluxe-hb-collage-slot-details" <?php echo 0 === $bc ? 'open' : ''; ?>>
							<summary><strong><?php echo esc_html( jluxe_hb_collage_slot_label( $bc ) ); ?></strong></summary>
						<div class="jluxe-hb-banner-item">
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
						</details>
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

				case 'cta':
					echo '<p class="description">یک بلوکِ فراخوانِ مستقل با تیتر، توضیح و پیوند؛ رنگ‌ها از پالتِ فعالِ سایت پیروی می‌کنند.</p>';
					jluxe_hb_field_text( $name, 'eyebrow', 'برچسبِ کوچک (اختیاری)', (string) ( $section['eyebrow'] ?? '' ) );
					jluxe_hb_field_text( $name, 'title', 'عنوان', (string) ( $section['title'] ?? '' ) );
					?>
					<p><label>توضیح<br />
						<textarea name="<?php echo esc_attr( $name ); ?>[content]" rows="3" class="large-text"><?php echo esc_textarea( $section['content'] ?? '' ); ?></textarea>
					</label></p>
					<?php
					jluxe_hb_field_text( $name, 'button_label', 'متنِ دکمه', (string) ( $section['button_label'] ?? '' ) );
					jluxe_hb_field_text( $name, 'button_url', 'پیوندِ دکمه', (string) ( $section['button_url'] ?? '' ) );
					jluxe_hb_field_select( $name, 'tone', 'ظاهر', (string) ( $section['tone'] ?? 'soft' ), array( 'soft' => 'روشن و ملایم', 'brand' => 'پس‌زمینهٔ رنگ اصلی' ) );
					jluxe_hb_field_select( $name, 'alignment', 'تراز', (string) ( $section['alignment'] ?? 'start' ), array( 'start' => 'راست‌چین', 'center' => 'وسط‌چین' ) );
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

				case 'heading':
					jluxe_hb_field_text( $name, 'title', 'متن تیتر', $section['title'] ?? '' );
					jluxe_hb_field_select( $name, 'tag', 'سطح تیتر', $section['tag'] ?? 'h2', array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ) );
					break;
				case 'image':
					jluxe_render_media_field( $name . '[image_id]', (int) ( $section['image_id'] ?? 0 ), 'انتخاب تصویر از کتابخانهٔ رسانه' );
					jluxe_hb_field_text( $name, 'alt', 'متن جایگزین تصویر', $section['alt'] ?? '' );
					jluxe_hb_field_text( $name, 'url', 'پیوند اختیاری تصویر', $section['url'] ?? '' );
					break;
				case 'button':
					jluxe_hb_field_text( $name, 'title', 'متن دکمه', $section['title'] ?? '' );
					jluxe_hb_field_text( $name, 'url', 'آدرس مقصد', $section['url'] ?? '' );
					break;

				case 'spacer':
					jluxe_hb_field_number( $name, 'height', 'ارتفاع (px)', $section['height'] ?? 40, 8, 200 );
					break;
			}
			?>
			<details class="jvb-design-controls">
				<summary>ظاهر و تنظیمات واکنش‌گرا</summary>
				<?php
				jluxe_hb_field_text( $name, 'editor_label', 'نام دلخواه در ساختار (در سایت نمایش داده نمی‌شود)', $section['editor_label'] ?? '' );
				jluxe_hb_field_checkbox( $name, 'design_enabled', 'اعمال ظاهر سفارشی این بخش', ! empty( $section['design_enabled'] ) );
				$design = is_array( $section['design'] ?? null ) ? $section['design'] : array();
				foreach ( array( 'padding' => 'فاصله داخلی دسکتاپ', 'mobile_padding' => 'فاصله داخلی موبایل', 'margin_top' => 'فاصله بالا', 'margin_bottom' => 'فاصله پایین', 'radius' => 'گردی گوشه‌ها' ) as $key => $title ) {
					jluxe_hb_field_number( $name . '[design]', $key, $title . ' (px)', $design[ $key ] ?? 0, 0, 240 );
				}
				jluxe_hb_field_text( $name . '[design]', 'background', 'رنگ پس‌زمینه (#RRGGBB؛ خالی یعنی شفاف)', $design['background'] ?? '' );
				jluxe_hb_field_text( $name . '[design]', 'text_color', 'رنگ متن (#RRGGBB)', $design['text_color'] ?? '' );
				jluxe_hb_field_text( $name . '[design]', 'border_color', 'رنگ خط دور (#RRGGBB؛ خالی = بدون خط)', $design['border_color'] ?? '' );
				jluxe_hb_field_text( $name . '[design]', 'background_image', 'نشانی تصویر پس‌زمینه (https)', $design['background_image'] ?? '' );
				jluxe_hb_field_text( $name . '[design]', 'anchor', 'شناسه لنگر بدون # (در صفحه یکتا باشد)', $design['anchor'] ?? '' );
				jluxe_hb_field_text( $name . '[design]', 'class_name', 'کلاس‌های CSS', $design['class_name'] ?? '' );
				jluxe_hb_field_select( $name . '[design]', 'shadow', 'سایه', $design['shadow'] ?? 'none', array( 'none' => 'بدون سایه', 'small' => 'کم', 'medium' => 'متوسط', 'large' => 'زیاد' ) );
				jluxe_hb_field_select( $name . '[design]', 'width', 'عرض بخش', $design['width'] ?? 'default', array( 'default' => 'پیش‌فرض', 'boxed' => 'کادر', 'full' => 'تمام عرض' ) );
				jluxe_hb_field_select( $name . '[design]', 'animation', 'حرکت هنگام دیده شدن', $design['animation'] ?? 'none', array( 'none' => 'بدون حرکت', 'fade' => 'محو', 'fade-up' => 'محو از پایین', 'fade-down' => 'محو از بالا', 'slide-right' => 'ورود از راست', 'slide-left' => 'ورود از چپ', 'zoom' => 'بزرگ‌نمایی' ) );
				jluxe_hb_field_number( $name . '[design]', 'animation_duration', 'مدت حرکت (ms)', $design['animation_duration'] ?? 600, 200, 1500 );
				jluxe_hb_field_number( $name . '[design]', 'animation_delay', 'تاخیر حرکت (ms)', $design['animation_delay'] ?? 0, 0, 1500 );
				jluxe_hb_field_checkbox( $name . '[design]', 'animation_repeat', 'تکرار حرکت در هر بار دیده شدن (کاهش حرکت سیستم رعایت می‌شود)', ! empty( $design['animation_repeat'] ) );
				jluxe_hb_field_checkbox( $name . '[design]', 'hide_tablet', 'پنهان در تبلت (۷۶۸ تا ۱۰۲۳)', ! empty( $design['hide_tablet'] ) );
				jluxe_hb_field_select( $name . '[design]', 'align', 'تراز محتوا', $design['align'] ?? 'start', array( 'start' => 'ابتدا', 'center' => 'وسط', 'end' => 'انتها' ) );
				jluxe_hb_field_checkbox( $name . '[design]', 'hide_mobile', 'پنهان در موبایل (کمتر از ۷۶۸)', ! empty( $design['hide_mobile'] ) );
				jluxe_hb_field_checkbox( $name . '[design]', 'hide_desktop', 'پنهان در تبلت و دسکتاپ', ! empty( $design['hide_desktop'] ) );
				?>
			</details>
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
	$fallback = jluxe_get_theme_settings()['colors']['primary'] ?? '#A313B9';
	printf(
		'<p><label>%s<br /><input type="color" name="%s[%s]" value="%s" /></label></p>',
		esc_html( $label ),
		esc_attr( $prefix ),
		esc_attr( $key ),
		esc_attr( $value ?: $fallback )
	);
}

function jluxe_hb_field_checkbox( string $prefix, string $key, string $label, bool $checked, bool $include_hidden_value = false ): void {
	if ( $include_hidden_value ) {
		printf( '<input type="hidden" name="%s[%s]" value="0" />', esc_attr( $prefix ), esc_attr( $key ) );
	}
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
