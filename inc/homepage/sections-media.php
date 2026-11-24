<?php
defined( 'ABSPATH' ) || exit;

/** Hero, campaign, story, banner and collage helpers. */

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
		$width_style = 'max-width:1462px;margin:0 auto;';
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
	$shadow   = ! empty( $slot['shadow'] ) ? 'box-shadow:0 8px 24px rgba(15,23,42,.15);' : '';
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
			$color      = ! empty( $layer['color'] ) ? $layer['color'] : '#1F2937';
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

function jluxe_render_homepage_brand_video_campaigns( array $section ): void {
	static $printed = false;
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$section_background = sanitize_hex_color( (string) ( $section['background_color'] ?? '' ) ) ?: '#7E0820';
	$auto_foreground = static function ( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
		$rgb = array_map( static fn( $channel ) => hexdec( $channel ) / 255, str_split( $hex, 2 ) );
		$linear = array_map( static fn( $channel ) => $channel <= .04045 ? $channel / 12.92 : pow( ( $channel + .055 ) / 1.055, 2.4 ), $rgb );
		$luminance = .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
		$light_luminance = .85499; // #ECEDEF
		$dark_luminance = .01444; // #1F1F1F
		$light_contrast = ( max( $luminance, $light_luminance ) + .05 ) / ( min( $luminance, $light_luminance ) + .05 );
		$dark_contrast = ( max( $luminance, $dark_luminance ) + .05 ) / ( min( $luminance, $dark_luminance ) + .05 );
		return $light_contrast >= $dark_contrast ? '#ECEDEF' : '#1F1F1F';
	};
	$campaigns = array();
	foreach ( (array) ( $section['campaigns'] ?? array() ) as $campaign ) {
		if ( ! is_array( $campaign ) ) { continue; }
		$target_type = in_array( $campaign['target_type'] ?? '', array( 'brand', 'category', 'product' ), true ) ? $campaign['target_type'] : 'brand';
		$term = null;
		$primary_product = null;
		if ( 'product' === $target_type ) {
			$primary_product = wc_get_product( (int) ( $campaign['product_id'] ?? 0 ) );
			if ( ! $primary_product || ! $primary_product->is_visible() ) { continue; }
			$target_name = $primary_product->get_name();
		} else {
			$taxonomy = 'category' === $target_type ? 'product_cat' : 'product_brand';
			$term_id = (int) ( $campaign['term_id'] ?? ( $campaign['brand_id'] ?? ( $campaign['category_id'] ?? 0 ) ) );
			$term = get_term( $term_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) { continue; }
			$target_name = $term->name;
		}

		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $campaign['product_ids'] ?? array() ) ) ) ) );
		$products = array();
		if ( $primary_product ) { $products[] = $primary_product; }
		if ( empty( $product_ids ) && $term && in_array( $target_type, array( 'brand', 'category' ), true ) ) {
			$automatic_products = wc_get_products( array(
				'status'       => 'publish',
				'stock_status' => 'instock',
				'limit'        => 8,
				'return'       => 'objects',
				'tax_query'    => array( array(
					'taxonomy' => 'category' === $target_type ? 'product_cat' : 'product_brand',
					'field'    => 'term_id',
					'terms'    => array( (int) $term->term_id ),
				) ),
			) );
			foreach ( array_slice( (array) $automatic_products, 0, 8 ) as $product ) {
				if ( $product && $product->is_visible() ) { $products[] = $product; }
			}
		} else {
			foreach ( array_slice( $product_ids, 0, $primary_product ? 11 : 12 ) as $product_id ) {
				if ( $primary_product && (int) $product_id === $primary_product->get_id() ) { continue; }
				$product = wc_get_product( $product_id );
				if ( $product && $product->is_visible() ) { $products[] = $product; }
			}
		}
		$logo = ! empty( $campaign['logo_id'] ) ? wp_get_attachment_image_url( (int) $campaign['logo_id'], 'thumbnail' ) : '';
		if ( ! $logo && $term ) {
			$term_image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			$logo = $term_image_id ? wp_get_attachment_image_url( $term_image_id, 'thumbnail' ) : '';
		}
		if ( ! $logo && $primary_product && $primary_product->get_image_id() ) {
			$logo = wp_get_attachment_image_url( $primary_product->get_image_id(), 'thumbnail' );
		}
		$media_type = 'image' === ( $campaign['media_type'] ?? '' ) ? 'image' : 'video';
		$media_id = (int) ( $campaign['media_id'] ?? ( $campaign['video_id'] ?? 0 ) );
		$mobile_media_id = (int) ( $campaign['mobile_media_id'] ?? 0 );
		if ( ! $media_id ) {
			$fallback_media_id = $term ? (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) : ( $primary_product ? (int) $primary_product->get_image_id() : 0 );
			if ( $fallback_media_id ) { $media_id = $fallback_media_id; $media_type = 'image'; }
		}
		$desktop_media_url = '';
		$mobile_media_url = '';
		if ( $media_id ) {
			$mime = (string) get_post_mime_type( $media_id );
			$desktop_media_url = 'image' === $media_type && 0 !== strpos( $mime, 'image/gif' ) ? ( wp_get_attachment_image_url( $media_id, 'large' ) ?: wp_get_attachment_url( $media_id ) ) : wp_get_attachment_url( $media_id );
		}
		if ( $mobile_media_id ) {
			$mobile_mime = (string) get_post_mime_type( $mobile_media_id );
			$mobile_media_url = 'image' === $media_type && 0 !== strpos( $mobile_mime, 'image/gif' ) ? ( wp_get_attachment_image_url( $mobile_media_id, 'large' ) ?: wp_get_attachment_url( $mobile_media_id ) ) : wp_get_attachment_url( $mobile_media_id );
		}
		$poster = ! empty( $campaign['poster_id'] ) ? wp_get_attachment_image_url( (int) $campaign['poster_id'], 'large' ) : '';
		$mobile_poster = ! empty( $campaign['mobile_poster_id'] ) ? wp_get_attachment_image_url( (int) $campaign['mobile_poster_id'], 'large' ) : '';
		$target_url = ! empty( $campaign['link'] ) ? $campaign['link'] : ( $primary_product ? $primary_product->get_permalink() : get_term_link( $term ) );
		if ( is_wp_error( $target_url ) ) { $target_url = ''; }
		$bg_color = sanitize_hex_color( (string) ( $campaign['bg_color'] ?? '' ) ) ?: $section_background;
		$campaigns[] = array(
			'title' => $target_name,
			'target_type' => $target_type,
			'products' => $products,
			'logo' => $logo,
			'media_type' => $media_type,
			'media' => $desktop_media_url,
			'mobile_media' => $mobile_media_url,
			'poster' => $poster,
			'mobile_poster' => $mobile_poster,
			'link' => $target_url,
			'bg_color' => $bg_color,
			'fg_color' => $auto_foreground( $bg_color ),
		);
	}
	if ( empty( $campaigns ) ) { return; }
	$id = 'jluxe-brand-campaign-' . sanitize_key( (string) ( $section['id'] ?? wp_unique_id() ) );
	$first_campaign = $campaigns[0];
	$initial_style = '--bvc-background:' . $section_background . ';--bvc-active-bg:' . $first_campaign['bg_color'] . ';--bvc-fg:' . $first_campaign['fg_color'] . ';';
	?>
	<section id="<?php echo esc_attr( $id ); ?>" class="jluxe-home-section jluxe-brand-campaigns" dir="rtl" data-jluxe-brand-campaigns style="<?php echo esc_attr( $initial_style ); ?>" aria-label="<?php echo esc_attr( $section['title'] ?? 'تبلیغات ویدیویی برندها' ); ?>">
		<header class="jluxe-bvc-header">
			<div class="jluxe-bvc-heading"><span class="jluxe-bvc-eyebrow">سفارشی <span aria-hidden="true">•</span></span><h2><?php echo esc_html( $section['title'] ?? 'تبلیغات ویدیویی برندها' ); ?></h2></div>
			<div class="jluxe-bvc-tabs" role="tablist" aria-label="کمپین‌ها">
				<?php foreach ( $campaigns as $index => $campaign ) : ?>
				<div class="jluxe-bvc-tab-item">
					<button type="button" class="jluxe-bvc-tab<?php echo 0 === $index ? ' is-active' : ''; ?>" role="tab" id="<?php echo esc_attr( $id . '-tab-' . $index ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $id . '-panel-' . $index ); ?>" tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>" data-bg-color="<?php echo esc_attr( $campaign['bg_color'] ); ?>" data-fg-color="<?php echo esc_attr( $campaign['fg_color'] ); ?>">
						<?php if ( $campaign['logo'] ) : ?><img src="<?php echo esc_url( $campaign['logo'] ); ?>" alt="" width="32" height="32" loading="lazy" decoding="async" /><?php endif; ?><span><?php echo esc_html( $campaign['title'] ); ?></span><span class="jluxe-bvc-tab-arrow" aria-hidden="true">‹</span>
					</button>
					<?php if ( $campaign['link'] ) : ?><a class="jluxe-bvc-tab-link" href="<?php echo esc_url( $campaign['link'] ); ?>" aria-label="رفتن به صفحهٔ <?php echo esc_attr( $campaign['title'] ); ?>"><span aria-hidden="true">↗</span></a><?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</header>
		<div class="jluxe-bvc-panels">
			<?php foreach ( $campaigns as $index => $campaign ) : ?>
			<div class="jluxe-bvc-panel<?php echo 0 === $index ? ' is-active' : ''; ?>" role="tabpanel" id="<?php echo esc_attr( $id . '-panel-' . $index ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-tab-' . $index ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
				<div class="jluxe-bvc-products-rail relative">
					<ul class="jluxe-bvc-products" data-jluxe-scroller aria-label="محصولات و رسانهٔ <?php echo esc_attr( $campaign['title'] ); ?>">
						<li class="jluxe-bvc-item jluxe-bvc-media-item" data-bvc-media-slide>
							<?php if ( $campaign['link'] ) : ?><a class="jluxe-bvc-media-content" href="<?php echo esc_url( $campaign['link'] ); ?>" aria-label="مشاهدهٔ کمپین <?php echo esc_attr( $campaign['title'] ); ?>"><?php else : ?><div class="jluxe-bvc-media-content"><?php endif; ?>
							<?php if ( 'video' === $campaign['media_type'] && $campaign['media'] ) : ?><video class="jluxe-bvc-video" data-desktop-src="<?php echo esc_url( $campaign['media'] ); ?>"<?php echo $campaign['mobile_media'] ? ' data-mobile-src="' . esc_url( $campaign['mobile_media'] ) . '"' : ''; ?><?php echo $campaign['poster'] ? ' poster="' . esc_url( $campaign['poster'] ) . '" data-desktop-poster="' . esc_url( $campaign['poster'] ) . '"' : ''; ?><?php echo $campaign['mobile_poster'] ? ' data-mobile-poster="' . esc_url( $campaign['mobile_poster'] ) . '"' : ''; ?> muted loop playsinline preload="none" aria-label="<?php echo esc_attr( 'ویدیوی تبلیغاتی ' . $campaign['title'] ); ?>"></video>
							<?php elseif ( 'image' === $campaign['media_type'] && $campaign['media'] ) : ?><picture class="jluxe-bvc-picture"><?php if ( $campaign['mobile_media'] ) : ?><source media="(max-width: 767px)" srcset="<?php echo esc_url( $campaign['mobile_media'] ); ?>" /><?php endif; ?><img class="jluxe-bvc-poster" src="<?php echo esc_url( $campaign['media'] ); ?>" alt="<?php echo esc_attr( 'کمپین ' . $campaign['title'] ); ?>" loading="lazy" decoding="async" /></picture>
							<?php elseif ( $campaign['poster'] ) : ?><img class="jluxe-bvc-poster" src="<?php echo esc_url( $campaign['poster'] ); ?>" alt="<?php echo esc_attr( 'کمپین ' . $campaign['title'] ); ?>" loading="lazy" decoding="async" />
							<?php else : ?><div class="jluxe-bvc-empty-media" aria-hidden="true"><span><?php echo esc_html( $campaign['title'] ); ?></span></div><?php endif; ?>
							<?php if ( $campaign['link'] ) : ?></a><?php else : ?></div><?php endif; ?>
							<?php if ( 'video' === $campaign['media_type'] && $campaign['media'] ) : ?><button type="button" class="jluxe-bvc-mute" data-jluxe-bvc-mute aria-label="فعال‌کردن صدا" aria-pressed="false"><span aria-hidden="true">♪</span></button><?php endif; ?>
						</li>
						<?php foreach ( $campaign['products'] as $product_index => $product ) :
							$discount_percent = 0;
							if ( $product->is_type( 'variable' ) ) {
								$variation_prices = $product->get_variation_prices( true );
								foreach ( (array) ( $variation_prices['regular_price'] ?? array() ) as $variation_id => $regular_price ) {
									$sale_price = (float) ( $variation_prices['sale_price'][ $variation_id ] ?? 0 );
									$regular_price = (float) $regular_price;
									if ( $regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price ) {
										$discount_percent = max( $discount_percent, (int) round( 100 - ( $sale_price / $regular_price * 100 ) ) );
									}
								}
							} else {
								$regular_price = (float) $product->get_regular_price();
								$sale_price = (float) $product->get_sale_price();
								if ( $product->is_on_sale() && $regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price ) {
									$discount_percent = (int) round( 100 - ( $sale_price / $regular_price * 100 ) );
								}
							}
						?>
						<li class="jluxe-bvc-item jluxe-bvc-product-item" style="--bvc-index:<?php echo esc_attr( (string) ( $product_index + 1 ) ); ?>">
							<a class="jluxe-bvc-product" data-jluxe-product-link href="<?php echo esc_url( $product->get_permalink() ); ?>">
								<span class="jluxe-bvc-product-visual"><?php if ( $discount_percent > 0 ) : ?><span class="jluxe-bvc-sale-badge"><?php echo esc_html( jluxe_fa_digits( $discount_percent ) ); ?>٪</span><?php endif; ?><span class="jluxe-bvc-product-image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) ); ?></span></span>
								<span class="jluxe-bvc-product-name"><?php echo esc_html( $product->get_name() ); ?></span>
								<?php if ( $product->get_average_rating() ) : ?><span class="jluxe-bvc-rating" aria-label="<?php echo esc_attr( 'امتیاز ' . $product->get_average_rating() . ' از ۵' ); ?>"><span aria-hidden="true">★</span> <?php echo esc_html( number_format_i18n( (float) $product->get_average_rating(), 1 ) ); ?></span><?php endif; ?>
								<span class="jluxe-bvc-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							</a>
						</li>
						<?php endforeach; ?>
						<?php if ( $campaign['link'] ) : ?><li class="jluxe-bvc-item jluxe-bvc-see-all-item" style="--bvc-index:<?php echo esc_attr( (string) ( count( $campaign['products'] ) + 1 ) ); ?>"><a class="jluxe-bvc-see-all" href="<?php echo esc_url( $campaign['link'] ); ?>"><span class="jluxe-bvc-see-all-icon" aria-hidden="true">‹</span><span><?php echo esc_html( $section['view_all_text'] ?? 'مشاهده همه' ); ?></span></a></li><?php endif; ?>
					</ul>
					<?php jluxe_scroll_arrows(); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php if ( ! $printed ) : $printed = true; ?>
	<style>
	.jluxe-brand-campaigns{--bvc-primary:hsl(var(--primary));box-sizing:border-box;display:flex;flex-direction:column;width:min(calc(100% - 32px),1288px);max-width:none;min-width:0;margin:20px auto 24px;padding:0 0 24px;border:1px solid hsl(var(--border));border-radius:16px;background-color:var(--bvc-active-bg,var(--bvc-background,#7E0820));color:var(--bvc-fg,#ECEDEF);box-shadow:0 2px 8px -2px rgba(0,0,0,.08),0 1px 2px -1px rgba(0,0,0,.06);overflow:hidden;transition:background-color .5s cubic-bezier(.61,1,.88,1),color .5s cubic-bezier(.61,1,.88,1)}
	.jluxe-bvc-header{display:flex;align-items:center;gap:16px;min-width:0;padding:12px 16px 0}
	.jluxe-bvc-heading{display:flex;align-items:center;gap:6px;flex:0 0 auto;min-width:0}
	.jluxe-bvc-header h2{margin:0;overflow:hidden;color:var(--bvc-fg,#ECEDEF);font-size:14px;font-weight:700;line-height:1.5;text-overflow:ellipsis;white-space:nowrap}
	.jluxe-bvc-eyebrow{flex:0 0 auto;color:var(--bvc-fg,#ECEDEF);font-size:11px;font-weight:700;white-space:nowrap}
	.jluxe-bvc-tabs{display:flex;align-items:center;gap:8px;flex:1;min-width:0;overflow-x:auto;overscroll-behavior-inline:contain;padding:2px 0 4px;scrollbar-width:none}
	.jluxe-bvc-tabs::-webkit-scrollbar{display:none}
	.jluxe-bvc-tab-item{display:flex;flex:0 0 auto;align-items:center;gap:4px}
	.jluxe-bvc-tab{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:0;height:40px;padding:4px 12px;border:1px solid color-mix(in srgb,var(--bvc-fg,#ECEDEF) 28%,transparent);border-radius:999px;background:rgba(255,255,255,.14);color:var(--bvc-fg,#ECEDEF);cursor:pointer;font:inherit;font-size:12px;white-space:nowrap;transition:background-color .2s ease,color .2s ease,border-color .2s ease}
	.jluxe-bvc-tab img{display:block;width:32px;height:32px;flex:0 0 32px;border-radius:50%;object-fit:contain;background:#fff}
	.jluxe-bvc-tab.is-active{border-color:rgba(31,31,31,.14);background:#fff;color:#1F1F1F;font-weight:700}
	.jluxe-bvc-tab-arrow{display:none;font-size:18px;line-height:1}
	.jluxe-bvc-tab.is-active .jluxe-bvc-tab-arrow{display:inline-block}
	.jluxe-bvc-tab-link{display:none;place-items:center;width:40px;height:40px;min-width:40px;min-height:40px;border:1px solid color-mix(in srgb,var(--bvc-fg,#ECEDEF) 28%,transparent);border-radius:50%;color:var(--bvc-fg,#ECEDEF);text-decoration:none;font-size:13px}
	.jluxe-bvc-tab-item:has(.jluxe-bvc-tab.is-active) .jluxe-bvc-tab-link{display:grid;border-color:rgba(31,31,31,.14);background:#fff;color:#1F1F1F}
	.jluxe-bvc-panels,.jluxe-bvc-panel{min-width:0}
	.jluxe-bvc-panel[hidden]{display:none!important}
	.jluxe-bvc-products-rail{position:relative;min-width:0;margin-top:12px}
	.jluxe-bvc-products{display:flex;align-items:stretch;gap:12px;width:100%;max-width:100%;min-width:0;margin:0;padding:0 0 4px;overflow-x:auto;overscroll-behavior-inline:contain;list-style:none;scrollbar-width:none;-webkit-overflow-scrolling:touch}
	.jluxe-bvc-products::-webkit-scrollbar{display:none}
	.jluxe-bvc-item{display:flex;flex:0 0 auto;align-items:stretch;height:316px;min-width:0;scroll-snap-align:start}
	.jluxe-bvc-item:first-child{margin-inline-start:24px}
	.jluxe-bvc-item:last-child{margin-inline-end:24px}
	.jluxe-bvc-media-item{position:relative;width:clamp(280px,28vw,340px);overflow:hidden;border-radius:12px;background:#eee}
	.jluxe-bvc-media-content{display:block;width:100%;height:100%;overflow:hidden;border-radius:12px;color:inherit;text-decoration:none}
	.jluxe-bvc-picture,.jluxe-bvc-video,.jluxe-bvc-poster,.jluxe-bvc-empty-media{display:block;width:100%;height:100%}
	.jluxe-bvc-video,.jluxe-bvc-poster{object-fit:cover}
	.jluxe-bvc-empty-media{display:grid;place-items:center;background:linear-gradient(135deg,#fff1f3,#f3f3f5);color:var(--bvc-primary);font-size:22px;font-weight:800}
	.jluxe-bvc-mute{position:absolute;z-index:2;inset-inline-start:12px;bottom:12px;display:grid;place-items:center;width:44px;height:44px;min-width:44px;min-height:44px;border:1px solid rgba(255,255,255,.72);border-radius:50%;background:rgba(31,31,31,.72);color:#fff;cursor:pointer;font-size:18px;backdrop-filter:blur(8px)}
	.jluxe-bvc-product-item,.jluxe-bvc-see-all-item{width:168px}
	.jluxe-bvc-product,.jluxe-bvc-see-all{box-sizing:border-box;display:flex;width:100%;height:100%;flex-direction:column;align-items:stretch;gap:8px;padding:12px;border:1px solid rgba(31,31,31,.06);border-radius:12px;background:#fff;color:#1F1F1F;text-decoration:none;box-shadow:0 1px 4px rgba(0,0,0,.06)}
	.jluxe-bvc-product-visual{position:relative;display:block;flex:0 0 auto}
	.jluxe-bvc-product-image{display:grid;place-items:center;width:100%;aspect-ratio:1/1;overflow:hidden;border-radius:8px;background:#fff}
	.jluxe-bvc-product-image img{display:block;width:100%;height:100%;object-fit:contain}
	.jluxe-bvc-sale-badge{position:absolute;z-index:1;top:6px;inset-inline-end:6px;padding:3px 7px;border-radius:999px;background:var(--bvc-primary);color:#fff;font-size:10px;font-weight:700;line-height:1.4}
	.jluxe-bvc-product-name{display:-webkit-box;min-height:36px;max-height:36px;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;color:#1F1F1F;font-size:11px;line-height:1.65;font-weight:500}
	.jluxe-bvc-rating{color:#5c5c5c;font-size:10px;direction:ltr;text-align:right}
	.jluxe-bvc-rating span{color:#f0a500}
	.jluxe-bvc-price{display:flex;flex:1;align-items:flex-end;justify-content:space-between;gap:3px;color:#1F1F1F;font-size:12px;font-weight:700}
	.jluxe-bvc-price del{color:#5C5C5C;font-size:10px;font-weight:400}
	.jluxe-bvc-price ins{color:#1F1F1F;text-decoration:none}
	.jluxe-bvc-price .woocommerce-Price-currencySymbol{font-size:10px}
	.jluxe-bvc-toman{display:inline-flex;flex:0 0 auto;align-items:center;color:#5c5c5c}
	.jluxe-bvc-see-all{align-items:center;justify-content:center;gap:14px;font-size:13px;font-weight:700}
	.jluxe-bvc-see-all-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:50%;background:#f2f3f5;color:var(--bvc-primary);font-size:28px;line-height:1}
	.jluxe-bvc-products-rail>[data-jluxe-scroll-prev],.jluxe-bvc-products-rail>[data-jluxe-scroll-next]{border-color:#dedfe0;background:rgba(255,255,255,.92);color:#1F1F1F}
	.jluxe-bvc-panel.is-entering .jluxe-bvc-media-content{animation:jluxe-bvc-media-in 380ms cubic-bezier(.61,1,.88,1) both}
	.jluxe-bvc-panel.is-entering .jluxe-bvc-product-item,.jluxe-bvc-panel.is-entering .jluxe-bvc-see-all-item{animation:jluxe-bvc-card-in 380ms cubic-bezier(.61,1,.88,1) both;animation-delay:calc(var(--bvc-index,0)*18ms)}
	@keyframes jluxe-bvc-media-in{from{opacity:0;transform:scale(.975)}to{opacity:1;transform:scale(1)}}
	@keyframes jluxe-bvc-card-in{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:translateX(0)}}
	@media(max-width:767px){.jluxe-brand-campaigns{width:100%;margin:20px auto 24px;padding:0 0 16px;border:0;border-radius:0;box-shadow:none}.jluxe-bvc-header{align-items:center;flex-direction:row;gap:8px;padding:12px 12px 0}.jluxe-bvc-heading{flex:0 0 auto;justify-content:flex-start}.jluxe-bvc-header h2{display:none}.jluxe-bvc-tabs{width:auto;gap:6px}.jluxe-bvc-tab{height:40px;padding:4px 10px;font-size:11px}.jluxe-bvc-tab-link{width:44px;height:44px;min-width:44px;min-height:44px}.jluxe-bvc-products-rail{margin-top:8px}.jluxe-bvc-item{height:292px}.jluxe-bvc-item:first-child{margin-inline-start:16px}.jluxe-bvc-item:last-child{margin-inline-end:16px}.jluxe-bvc-media-item{width:clamp(220px,68vw,260px)}.jluxe-bvc-product-item,.jluxe-bvc-see-all-item{width:148px}.jluxe-bvc-product,.jluxe-bvc-see-all{padding:10px}.jluxe-bvc-products{gap:12px}}
	@media(prefers-reduced-motion:reduce){.jluxe-brand-campaigns,.jluxe-bvc-tab,.jluxe-bvc-panel.is-entering .jluxe-bvc-media-content,.jluxe-bvc-panel.is-entering .jluxe-bvc-product-item,.jluxe-bvc-panel.is-entering .jluxe-bvc-see-all-item{scroll-behavior:auto!important;transition:none!important;animation:none!important;transform:none!important}}
	</style>
	<?php endif;
}

function jluxe_render_homepage_brand_marquee( array $section ): void {
	static $printed = false;
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
						$logo_image = jluxe_get_responsive_attachment_image( (int) $item['image_id'], 'jluxe-uncropped-200', '(max-width: 639px) 78px, 96px' );
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
	<?php if ( ! $printed ) : $printed = true; ?><style>
	.jluxe-brand-strip{box-sizing:border-box;width:min(calc(100% - 32px),1288px);max-width:none;margin:24px auto;padding:0 16px;border:1px solid hsl(var(--border));border-radius:16px;background:hsl(var(--surface));overflow:hidden}.jluxe-brand-strip-inner{box-sizing:border-box;width:100%;height:64px;min-height:64px;display:flex;align-items:center;gap:16px;padding:5px 16px}.jluxe-brand-strip-title{flex:0 0 auto;max-width:120px;font-size:11px;font-weight:800;color:#1F2937;white-space:nowrap}.jluxe-brand-strip-viewport{min-width:0;flex:1;overflow:hidden}.jluxe-brand-marquee-track{display:flex;align-items:center;width:max-content;height:54px;animation:jluxe-brand-marquee var(--jluxe-brand-speed) linear infinite}.jluxe-brand-marquee-pause:hover{animation-play-state:paused}.jluxe-brand-strip-item{box-sizing:border-box;width:126px;min-width:126px;height:54px;display:flex;align-items:center;justify-content:center;padding:0 7px}.jluxe-brand-strip-link{display:flex;align-items:center;justify-content:center;width:100%;height:100%}.jluxe-brand-strip-logo{width:96px;height:40px;display:flex;align-items:center;justify-content:center}.jluxe-brand-strip-logo img{display:block;width:auto!important;height:auto!important;max-width:96px!important;max-height:40px!important;object-fit:contain!important;object-position:50% 50%!important;transition:filter .25s ease,opacity .25s ease}.jluxe-brand-strip-logo img.is-gray{filter:grayscale(1);opacity:.62}.jluxe-brand-strip-link:hover img.is-gray{filter:grayscale(0);opacity:1}@keyframes jluxe-brand-marquee{from{transform:translateX(0)}to{transform:translateX(50%)}}@media(prefers-reduced-motion:reduce){.jluxe-brand-marquee-track{animation:none!important}.jluxe-brand-strip-logo img{transition:none!important}}@media(max-width:767px){.jluxe-brand-strip{width:calc(100% - 24px);padding-inline:0;margin:14px auto;border-radius:12px}.jluxe-brand-strip-inner{height:56px;min-height:56px;padding:4px 9px;gap:9px}.jluxe-brand-strip-title{max-width:76px;font-size:9px}.jluxe-brand-marquee-track{height:48px}.jluxe-brand-strip-item{width:102px;min-width:102px;height:48px;padding:0 5px}.jluxe-brand-strip-logo{width:78px;height:32px}.jluxe-brand-strip-logo img{max-width:78px!important;max-height:32px!important}}
	</style><?php endif; ?>
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
	$max_width    = ( 1462 - 32 - $gap_total ) / $column_count;

	return sprintf(
		'(max-width: 639px) calc(100vw - 24px), (max-width: 1462px) calc(%.4fvw - %.2fpx), %.2fpx',
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
						<div class="jluxe-home-banner__overlay pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
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
	static $viewer_style_printed = false;
	if ( ! $viewer_style_printed ) :
		$viewer_style_printed = true;
		?>
		<style>
		.jluxe-story-viewer{background:rgba(0,0,0,.9)}
		.jluxe-story-progress{top:16px;background:rgba(255,255,255,.3)}
		.jluxe-story-close{top:32px;z-index:2;min-height:40px;background:rgba(255,255,255,.1)}
		.jluxe-story-nav{z-index:2;background:rgba(255,255,255,.16);font-size:24px}
		.jluxe-story-content{padding-top:20px}
		.jluxe-story-cta{bottom:28px;left:50%;white-space:nowrap}
		</style>
	<?php endif; ?>
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
					data-link="<?php echo esc_url( $item['link'] ?? '' ); ?>"
					data-button-text="<?php echo esc_attr( $item['button_text'] ?? '' ); ?>"
					class="flex shrink-0 flex-col items-center gap-1.5"
				>
					<span class="jluxe-story-ring grid size-16 place-items-center rounded-full bg-gradient-to-tr from-primary via-primary to-primary/60 p-[2.5px]">
						<span class="grid size-full place-items-center rounded-full bg-surface p-[2.5px]">
							<img src="<?php echo esc_url( $thumb_url ); ?>" width="64" height="64" alt="<?php echo esc_attr( $item['title'] ?? '' ); ?>" class="size-full rounded-full object-cover" <?php echo jluxe_lazy_attr(); ?> />
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
			<div data-jluxe-story-viewer hidden class="jluxe-story-viewer fixed inset-0 z-[999] p-4">
				<div class="jluxe-story-progress absolute inset-x-4 h-1 overflow-hidden rounded-full" role="progressbar" aria-label="پیشرفت استوری" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-jluxe-story-progress><span class="block h-full w-0 bg-white transition-[width] duration-300" data-jluxe-story-progress-fill></span></div>
				<button type="button" data-jluxe-story-close aria-label="بستن" class="jluxe-story-close absolute end-4 grid size-10 place-items-center rounded-full text-white">
					<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
				<button type="button" data-jluxe-story-prev aria-label="استوری قبلی" class="jluxe-story-nav absolute start-4 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full text-white">›</button>
				<button type="button" data-jluxe-story-next aria-label="استوری بعدی" class="jluxe-story-nav absolute end-4 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full text-white">‹</button>
				<div class="jluxe-story-content flex max-w-full flex-col items-center gap-3">
					<div data-jluxe-story-media></div>
					<span data-jluxe-story-title class="text-small text-white"></span>
					<a data-jluxe-story-cta hidden class="jluxe-story-cta absolute left-1/2 -translate-x-1/2 rounded-full bg-white px-5 py-2 text-small font-bold text-foreground shadow-lg" href="#"></a>
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
	<button type="button" data-jluxe-scroll-prev aria-label="قبلی" class="jluxe-home-scroll-arrow absolute end-1 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-surface/40 text-foreground opacity-0 shadow-lg backdrop-blur-md transition-opacity duration-300 pointer-events-none hover:bg-surface/60">
		<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
	</button>
	<button type="button" data-jluxe-scroll-next aria-label="بعدی" class="jluxe-home-scroll-arrow absolute start-1 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-surface/40 text-foreground opacity-0 shadow-lg backdrop-blur-md transition-opacity duration-300 pointer-events-none hover:bg-surface/60">
		<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
	</button>
	<?php
}

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
			'desktop' => '(min-width: 1486px) 1462px, calc(100vw - 24px)',
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
		. '.jluxe-hero[data-effect="fade"] .jluxe-hero__slide,.jluxe-hero[data-effect="zoom"] .jluxe-hero__slide,.jluxe-hero[data-effect="none"] .jluxe-hero__slide{grid-area:1/1;opacity:0;pointer-events:none;transition:opacity var(--jh-speed) ease,transform var(--jh-speed) ease}'
		. '.jluxe-hero[data-effect="zoom"] .jluxe-hero__slide{transform:scale(1.08)}'
		. '.jluxe-hero[data-effect] .jluxe-hero__slide[data-active="true"]{opacity:1;pointer-events:auto;transform:none;z-index:1;transition-delay:0s}'
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
	$image_sizes = '(max-width: 639px) calc(100vw - 24px), (max-width: 1462px) calc(100vw - 32px), 1430px';
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
						<?php if ( ! empty( $item['title'] ) || ! empty( $item['category'] ) || ! empty( $item['description'] ) ) : ?>
							<span aria-hidden="true" class="absolute inset-0 bg-gradient-to-l from-black/60 via-black/10 to-transparent"></span>
							<div class="absolute inset-y-0 start-6 z-10 flex flex-col justify-center gap-1.5 sm:start-10">
								<?php if ( ! empty( $item['category'] ) ) : ?><span class="text-caption font-medium text-white/80"><?php echo esc_html( $item['category'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['title'] ) ) : ?><span class="text-h2 font-extrabold text-white"><?php echo esc_html( $item['title'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $item['description'] ) ) : ?><span class="max-w-xs text-small text-white/85"><?php echo esc_html( $item['description'] ); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
					</<?php echo esc_html( $tag ); ?>>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $slides ) > 1 ) : ?>
				<button type="button" data-jluxe-bs-prev aria-label="قبلی" class="jluxe-banner-slider-arrow absolute end-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-foreground shadow">
					<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg>
				</button>
				<button type="button" data-jluxe-bs-next aria-label="بعدی" class="jluxe-banner-slider-arrow absolute start-3 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-foreground shadow">
					<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 6l6 6-6 6"/></svg>
				</button>
				<div class="absolute bottom-3 start-1/2 flex -translate-x-1/2 gap-1.5">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<button type="button" data-jluxe-bs-dot data-active="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-label="رفتن به اسلاید <?php echo esc_attr( jluxe_fa_digits( $i + 1 ) ); ?>" aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>" class="h-3 w-5 rounded-full bg-white/50 transition-colors data-[active=true]:bg-white"></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}
