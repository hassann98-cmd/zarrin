<?php
defined( 'ABSPATH' ) || exit;

/** Category and content renderers. */

/** A small, semantic call-to-action section that uses the active theme color tokens. */
function jluxe_render_homepage_cta( array $section ): void {
	$title        = isset( $section['title'] ) && is_scalar( $section['title'] ) ? trim( (string) $section['title'] ) : '';
	$eyebrow      = isset( $section['eyebrow'] ) && is_scalar( $section['eyebrow'] ) ? trim( (string) $section['eyebrow'] ) : '';
	$content      = isset( $section['content'] ) && is_scalar( $section['content'] ) ? trim( (string) $section['content'] ) : '';
	$button_label = isset( $section['button_label'] ) && is_scalar( $section['button_label'] ) ? trim( (string) $section['button_label'] ) : '';
	$button_url   = isset( $section['button_url'] ) && is_scalar( $section['button_url'] ) ? trim( (string) $section['button_url'] ) : '';
	$tone         = isset( $section['tone'] ) && 'brand' === $section['tone'] ? 'brand' : 'soft';
	$alignment    = isset( $section['alignment'] ) && 'center' === $section['alignment'] ? 'center' : 'start';

	if ( '' === $title && '' === $eyebrow && '' === $content && ( '' === $button_label || '' === $button_url ) ) {
		return;
	}

	printf(
		'<section class="jluxe-home-section jluxe-home-cta jluxe-home-cta--%1$s jluxe-home-cta--align-%2$s" aria-label="%3$s"><div class="jluxe-home-cta__panel"><div class="jluxe-home-cta__copy">',
		esc_attr( $tone ),
		esc_attr( $alignment ),
		esc_attr( '' !== $title ? $title : 'دعوت به اقدام' )
	);

	if ( '' !== $eyebrow ) {
		echo '<p class="jluxe-home-cta__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== $title ) {
		echo '<h2 class="jluxe-home-cta__title">' . esc_html( $title ) . '</h2>';
	}
	if ( '' !== $content ) {
		echo '<p class="jluxe-home-cta__description">' . nl2br( esc_html( $content ) ) . '</p>';
	}
	echo '</div>';

	if ( '' !== $button_label && '' !== $button_url ) {
		echo '<a class="jluxe-home-cta__button" href="' . esc_url( $button_url ) . '">' . esc_html( $button_label ) . '</a>';
	}
	echo '</div></section>';
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
	static $printed = false;
	$is_woocommerce_active = class_exists( 'WooCommerce' );
	$desktop_layout = jluxe_homepage_section_choice( $section, 'layout', array( 'grid', 'row' ), 'grid' );
	$mobile_layout = jluxe_homepage_section_choice( $section, 'mobile_layout', array( 'grid', 'row' ), 'grid' );
	$swipe_hint_attr = 'row' === $mobile_layout && jluxe_homepage_mobile_category_hint_enabled()
		? ' data-jluxe-category-swipe-hint'
		: '';
	$mobile_columns = max( 2, min( 6, (int) ( $section['mobile_columns'] ?? 3 ) ) );
	$mobile_gap_total = 8 * ( $mobile_columns - 1 );
	$mobile_card_width = 'calc((100% - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ')';
	$mobile_image_size = 'min(96px,max(48px,calc((100vw - 32px - ' . $mobile_gap_total . 'px) / ' . $mobile_columns . ' - 16px)))';
	$image_size = 'jluxe-uncropped-200';
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
	$frame_color = 'color' === $frame_mode ? ( sanitize_hex_color( $section['image_frame_color'] ?? '' ) ?: 'hsl(var(--border))' ) : 'transparent';
	$image_radius = 'circle' === $shape ? '9999px' : ( 'square' === $shape ? '14px' : '0px' );
	$text_color = sanitize_hex_color( $section['card_text_color'] ?? '' ) ?: '#1F2937';
	$show_names = ! array_key_exists( 'show_category_names', $section ) || ! empty( $section['show_category_names'] );
	$grid_class = 'row' === $desktop_layout ? 'jluxe-category-showcase-row' : 'jluxe-category-showcase-grid';
	$show_arrows = 'row' === $desktop_layout || 'row' === $mobile_layout;
	$container_style = '--jluxe-showcase-image-radius:' . $image_radius . ';--jluxe-showcase-image-frame-width:' . $frame_width . ';--jluxe-showcase-image-frame-color:' . $frame_color . ';--jluxe-showcase-card-text:' . $text_color . ';--jluxe-mobile-columns:' . $mobile_columns . ';--jluxe-mobile-card-width:' . $mobile_card_width . ';--jluxe-mobile-image-size:' . $mobile_image_size . ';';
	?>
	<section class="jluxe-home-section jluxe-category-showcase jluxe-category-showcase-v2<?php echo $show_names ? '' : ' jluxe-category-showcase-image-only'; ?>" style="<?php echo esc_attr( $container_style ); ?>" dir="rtl" aria-label="دسته‌بندی‌های ویژه">
		<div class="jluxe-category-showcase-scroll-wrap" data-mobile-layout="<?php echo esc_attr( $mobile_layout ); ?>">
			<div class="<?php echo esc_attr( $grid_class ); ?>" data-jluxe-scroller<?php echo $swipe_hint_attr; ?>>
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
	<?php if ( ! $printed ) : $printed = true; ?><style>
	.jluxe-category-showcase-v2{box-sizing:border-box;position:relative;isolation:isolate;width:100%;max-width:var(--jluxe-container-max,1320px);margin:24px auto 32px;padding:0 16px;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;color:inherit}
	.jluxe-category-showcase-scroll-wrap{position:relative;min-width:0}
	.jluxe-category-showcase-grid{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:center;gap:16px}
	.jluxe-category-showcase-row{display:flex;flex-wrap:nowrap;align-items:flex-start;justify-content:flex-start;gap:12px;overflow-x:auto;overflow-y:hidden;padding:2px 42px 8px;scroll-behavior:smooth;scrollbar-width:none}
	.jluxe-category-showcase-row::-webkit-scrollbar{display:none}.jluxe-category-showcase-row.is-centered{justify-content:center}.jluxe-category-showcase-row.is-overflowing{justify-content:flex-start}
	.jluxe-category-showcase-card{box-sizing:border-box;display:flex!important;flex:0 0 152px;flex-direction:column;align-items:center;justify-content:flex-start;width:152px;min-width:0;padding:0;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:inherit!important;text-align:center!important;text-decoration:none!important;overflow:visible}
	.jluxe-category-showcase-image{box-sizing:border-box;display:flex!important;flex:0 0 120px;align-items:center;justify-content:center;width:120px!important;height:120px!important;overflow:hidden;border:var(--jluxe-showcase-image-frame-width,0px) solid var(--jluxe-showcase-image-frame-color,transparent);border-radius:var(--jluxe-showcase-image-radius,0px)!important;background:transparent!important;padding:0!important}
	.jluxe-category-showcase-image img{display:block!important;flex:0 0 auto;width:100%!important;height:100%!important;max-width:100%;max-height:100%;margin:0!important;padding:0!important;border:0!important;border-radius:inherit!important;object-fit:contain!important;box-shadow:none!important}
	.jluxe-category-showcase-name{display:block!important;width:100%;margin-top:8px;padding:0 3px;font-size:13px!important;line-height:1.65!important;font-weight:600!important;color:var(--jluxe-showcase-card-text,#1F2937)!important;white-space:normal;overflow-wrap:anywhere}
	.jluxe-category-showcase-scroll-wrap>[data-jluxe-scroll-prev],.jluxe-category-showcase-scroll-wrap>[data-jluxe-scroll-next]{background:transparent!important;border-color:transparent!important;box-shadow:none!important;color:inherit!important}
	@media(max-width:767px){.jluxe-category-showcase-v2{width:100%;padding-inline:12px;margin:20px auto 26px}.jluxe-category-showcase-grid{gap:12px 8px}.jluxe-category-showcase-row{gap:8px;padding:2px 36px 8px}.jluxe-category-showcase-card{flex-basis:var(--jluxe-mobile-card-width);width:var(--jluxe-mobile-card-width);min-width:0}.jluxe-category-showcase-image{flex-basis:var(--jluxe-mobile-image-size);width:var(--jluxe-mobile-image-size)!important;height:var(--jluxe-mobile-image-size)!important}.jluxe-category-showcase-name{margin-top:6px;font-size:12px!important}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-grid,.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-row{flex-wrap:nowrap;justify-content:flex-start;overflow-x:auto;overflow-y:hidden;padding:2px 36px 8px}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] [data-jluxe-scroller].is-centered{justify-content:center}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] [data-jluxe-scroller].is-overflowing{justify-content:flex-start}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="row"] .jluxe-category-showcase-card{flex:0 0 calc(var(--jluxe-mobile-image-size) + 16px);width:calc(var(--jluxe-mobile-image-size) + 16px);min-width:calc(var(--jluxe-mobile-image-size) + 16px)}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-grid,.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-row{display:flex;flex-wrap:wrap;justify-content:center;gap:12px 8px;overflow:visible;padding:2px 0 8px}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"] .jluxe-category-showcase-card{flex:0 0 var(--jluxe-mobile-card-width);width:var(--jluxe-mobile-card-width);min-width:0}.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"]>[data-jluxe-scroll-prev],.jluxe-category-showcase-scroll-wrap[data-mobile-layout="grid"]>[data-jluxe-scroll-next]{display:none}}
	@media(prefers-reduced-motion:reduce){.jluxe-category-showcase-row{scroll-behavior:auto}}
	</style><?php endif; ?>
	<?php
}

function jluxe_render_homepage_category_grid( array $section ): void {
	static $printed = false;
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
		$image_data = $image_id
			? jluxe_get_responsive_attachment_image( $image_id, 'jluxe-category-grid-ref-240', '240px' )
			: array();
		$img_url    = ! empty( $image_data['src'] ) ? $image_data['src'] : wc_placeholder_img_src( 'thumbnail' );
		$img_srcset = (string) ( $image_data['srcset'] ?? '' );
		$display    = trim( (string) ( $item['display_name'] ?? '' ) );
		$rows[]     = array(
			'term'         => $term,
			'img_url'      => $img_url,
			'img_srcset'   => $img_srcset,
			'img_width'    => absint( $image_data['width'] ?? 120 ) ?: 120,
			'img_height'   => absint( $image_data['height'] ?? 120 ) ?: 120,
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
	$section_rad = max( 0, min( 56, (int) ( $section['section_radius'] ?? 20 ) ) );
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
		'soft'   => '0 4px 16px rgba(15,23,42,.06)',
		'medium' => '0 8px 24px rgba(15,23,42,.09)',
		'strong' => '0 14px 34px rgba(15,23,42,.13)',
	);
	$shadow      = $shadow_map[ $section['card_shadow'] ?? 'soft' ] ?? $shadow_map['soft'];
	$lift        = array_key_exists( 'hover_lift', $section ) ? ! empty( $section['hover_lift'] ) : false;
	$zoom        = array_key_exists( 'zoom_enabled', $section ) ? ! empty( $section['zoom_enabled'] ) : false;
	$align_css   = 'start' === $alignment ? 'flex-start' : ( 'end' === $alignment ? 'flex-end' : 'center' );
	$text_css    = 'start' === $alignment ? 'right' : ( 'end' === $alignment ? 'left' : 'center' );
	$cards_alignment = jluxe_homepage_section_choice( $section, 'cards_alignment', array( 'start', 'center', 'end' ), 'center' );
	$cards_justify = 'start' === $cards_alignment ? 'flex-start' : ( 'end' === $cards_alignment ? 'flex-end' : 'center' );
	$mobile_layout = jluxe_homepage_section_choice( $section, 'mobile_layout', array( 'row', 'grid' ), 'row' );
	$swipe_hint_attr = 'row' === $mobile_layout && jluxe_homepage_mobile_category_hint_enabled()
		? ' data-jluxe-category-swipe-hint'
		: '';
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
			<div class="jluxe-category-grid-ref-scroll <?php echo 'grid' === $layout ? 'is-grid' : 'is-row'; ?>" data-jluxe-scroller<?php echo $swipe_hint_attr; ?>>
				<?php foreach ( $rows as $row ) : ?>
					<a href="<?php echo esc_url( get_term_link( $row['term'] ) ); ?>" class="jluxe-category-grid-ref-item<?php echo $lift ? ' is-lift' : ''; ?>" aria-label="<?php echo esc_attr( 'مشاهده دسته‌بندی ' . $row['display_name'] ); ?>" title="<?php echo esc_attr( $row['display_name'] ); ?>">
						<span class="jluxe-home-card jluxe-category-grid-ref-card">
							<span class="jluxe-category-grid-ref-image"><img class="<?php echo $zoom ? 'is-zoom' : ''; ?>" width="<?php echo esc_attr( (string) $row['img_width'] ); ?>" height="<?php echo esc_attr( (string) $row['img_height'] ); ?>" src="<?php echo esc_url( $row['img_url'] ); ?>"<?php if ( $row['img_srcset'] ) : ?> srcset="<?php echo esc_attr( $row['img_srcset'] ); ?>" sizes="<?php echo esc_attr( '(max-width: 767px) ' . min( $image_size, 60 ) . 'px, ' . $image_size . 'px' ); ?>"<?php endif; ?> alt="" aria-hidden="true" <?php echo jluxe_lazy_attr(); ?> /></span>
							<span class="jluxe-category-grid-ref-name"><?php echo esc_html( $row['display_name'] ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
			<button type="button" class="jluxe-category-grid-ref-arrow is-next" data-jluxe-scroll-next aria-label="اسکرول به راست"><svg class="is-rotated" xmlns="http://www.w3.org/2000/svg" width="26" viewBox="0 0 24 24"><path fill="currentColor" d="M14 17.308L8.692 12L14 6.692l.708.708l-4.6 4.6l4.6 4.6z"></path></svg></button>
		</div>
	</section>
	<?php if ( ! $printed ) : $printed = true; ?><style>
	/* Keep the category group centered when it fits; JS switches overflow rows back to a safe scroll start. */
	.jluxe-category-grid-ref{box-sizing:border-box;width:min(calc(100% - 32px),1288px);max-width:none;margin:24px auto;background:transparent;border:1px solid hsl(var(--border));border-radius:var(--jluxe-cat-section-radius,20px);color:hsl(var(--foreground));padding:40px 16px 0;display:flex;flex-direction:column;justify-content:center;overflow:visible}
	.jluxe-category-grid-ref-head{padding:0 16px}.jluxe-category-grid-ref-heading{display:flex;align-items:center;justify-content:flex-start;gap:16px;color:hsl(var(--foreground))}.jluxe-category-grid-ref-icon{display:flex;align-items:center;justify-content:center;flex:0 0 48px;width:48px;height:48px;background:hsl(var(--surface));border:1px solid hsl(var(--border));border-radius:12px;color:hsl(var(--primary));font-size:22px;line-height:1}.jluxe-category-grid-ref-title-wrap{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:4px}.jluxe-category-grid-ref-title-wrap h2{margin:0 0 0 32px;font-size:22px;line-height:1.25;letter-spacing:-.065em;font-weight:800;color:hsl(var(--foreground))}.jluxe-category-grid-ref-all{font-size:12px;line-height:1.5;color:hsl(var(--text-muted));text-decoration:none;letter-spacing:-.02em}
	.jluxe-category-grid-ref-carousel{position:relative;display:flex;align-items:center;justify-content:center;min-width:0;width:100%;overflow:visible}.jluxe-category-grid-ref-scroll{display:flex!important;flex-direction:row;align-items:center;justify-content:flex-start;gap:12px;width:100%;max-width:100%;min-width:0;overflow-x:auto;overflow-y:visible;scrollbar-width:none;scroll-behavior:smooth;padding:40px 4px 40px 16px}.jluxe-category-grid-ref-scroll.is-centered{justify-content:var(--jluxe-cat-cards-justify)}.jluxe-category-grid-ref-scroll.is-overflowing{justify-content:flex-start}.jluxe-category-grid-ref-scroll::-webkit-scrollbar{display:none}.jluxe-category-grid-ref-scroll.is-grid{display:flex!important;flex-wrap:nowrap;overflow-x:auto;overflow-y:visible;padding:40px 4px 40px 16px}.jluxe-category-grid-ref-item{display:block;flex:0 0 var(--jluxe-cat-card-width);width:var(--jluxe-cat-card-width);text-decoration:none;color:inherit;cursor:pointer}.jluxe-category-grid-ref-scroll.is-grid .jluxe-category-grid-ref-item{width:var(--jluxe-cat-card-width);min-width:var(--jluxe-cat-card-width);flex:0 0 var(--jluxe-cat-card-width)}.jluxe-category-grid-ref-card{box-sizing:border-box;display:flex;flex-direction:column;gap:14px;align-items:var(--jluxe-cat-align);justify-content:center;width:var(--jluxe-cat-card-width);height:var(--jluxe-cat-card-height);background:hsl(var(--surface));border:1px solid hsl(var(--border));border-radius:var(--jluxe-cat-card-radius);overflow:visible;box-shadow:var(--jluxe-cat-shadow);transition:transform .24s cubic-bezier(.2,.7,.2,1),border-color .2s ease,box-shadow .24s ease}.jluxe-category-grid-ref-item.is-lift:hover .jluxe-category-grid-ref-card{transform:translateY(-3px);border-color:hsl(var(--primary) / .28)}.jluxe-category-grid-ref-image{display:flex;align-items:center;justify-content:center;width:var(--jluxe-cat-image-size);height:var(--jluxe-cat-image-size);flex:0 0 var(--jluxe-cat-image-size);overflow:hidden;background:var(--jluxe-cat-image-bg);border-radius:var(--jluxe-cat-image-radius)}.jluxe-category-grid-ref-image img{display:block;width:100%;height:100%;object-fit:contain;border:0!important;outline:0!important;box-shadow:none!important;border-radius:inherit;transition:transform .42s cubic-bezier(.2,.7,.2,1);transform-origin:center}.jluxe-category-grid-ref-image img.is-zoom{transform:scale(1)}.jluxe-category-grid-ref-item:hover .jluxe-category-grid-ref-image img.is-zoom{transform:scale(1.045)}.jluxe-category-grid-ref-name{display:block;width:calc(100% - 16px);font-size:13px;line-height:1.55;text-align:var(--jluxe-cat-text-align);color:hsl(var(--foreground));white-space:normal;overflow-wrap:anywhere}
	.jluxe-category-grid-ref-arrow{display:flex;position:absolute;top:50%;z-index:10;width:38px;height:76px;align-items:center;justify-content:center;transform:translateY(-50%);padding:0 8px;border:0;border-radius:12px;background:rgba(255,255,255,.88);color:hsl(var(--foreground));box-shadow:0 5px 18px rgba(15,23,42,.08);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);cursor:pointer;transition:background .2s ease,opacity .2s ease}.jluxe-category-grid-ref-arrow:hover{background:#fff}.jluxe-category-grid-ref-arrow.is-prev{left:8px}.jluxe-category-grid-ref-arrow.is-next{right:8px}.jluxe-category-grid-ref-arrow .is-rotated{transform:rotate(180deg)}
	@media(max-width:767px){.jluxe-category-grid-ref{max-width:none;margin:20px auto;width:100%;padding-inline:0;border-left:0;border-right:0;border-radius:0;padding-top:32px;padding-bottom:0}.jluxe-category-grid-ref-head{padding:0 12px}.jluxe-category-grid-ref-heading{gap:12px}.jluxe-category-grid-ref-title-wrap h2{font-size:18px;margin-left:0;letter-spacing:-.04em}.jluxe-category-grid-ref-all{font-size:11px}.jluxe-category-grid-ref-scroll{gap:8px;padding:20px 12px 24px}.jluxe-category-grid-ref-item{flex-basis:120px;width:120px}.jluxe-category-grid-ref-card{width:120px;height:116px;gap:10px;border-radius:var(--jluxe-cat-card-radius,20px)}.jluxe-category-grid-ref-image{width:min(var(--jluxe-cat-image-size),60px);height:min(var(--jluxe-cat-image-size),60px);flex-basis:min(var(--jluxe-cat-image-size),60px)}.jluxe-category-grid-ref-name{font-size:12px;width:calc(100% - 10px)}.jluxe-category-grid-ref-arrow{top:50%;width:40px;height:40px;min-width:40px;min-height:40px;border-radius:50%}.jluxe-category-grid-ref-arrow.is-prev{left:4px}.jluxe-category-grid-ref-arrow.is-next{right:4px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-scroll{display:flex!important;flex-wrap:wrap;justify-content:var(--jluxe-cat-cards-justify);gap:8px;overflow:visible;padding:12px 6px 20px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-item,.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-scroll.is-grid .jluxe-category-grid-ref-item{flex:0 0 var(--jluxe-cat-mobile-card-width);width:var(--jluxe-cat-mobile-card-width);min-width:0}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-card{width:100%;height:auto;min-height:100px;gap:8px;padding:8px 4px}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-image{width:var(--jluxe-cat-mobile-image-size);height:var(--jluxe-cat-mobile-image-size);min-width:0;flex-basis:var(--jluxe-cat-mobile-image-size)}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-name{width:100%;font-size:clamp(10px,3vw,12px);line-height:1.45}.jluxe-category-grid-ref-carousel[data-mobile-layout="grid"] .jluxe-category-grid-ref-arrow{display:none}}
	@media(prefers-reduced-motion:reduce){.jluxe-category-grid-ref-card,.jluxe-category-grid-ref-image img{transition:none!important}}
	</style><?php endif; ?>
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
	$blog_page_id = (int) get_option( 'page_for_posts' );
	$blog_url = $blog_page_id ? get_permalink( $blog_page_id ) : home_url( '/' );
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-8">
		<?php jluxe_render_homepage_section_header( (string) ( $section['title'] ?? 'از وبلاگ' ), (string) $blog_url, (string) ( $section['title'] ?? 'وبلاگ' ) ); ?>
		<div class="jluxe-home-blog-viewport" role="region" aria-label="نوشته‌های وبلاگ">
			<div class="jluxe-home-blog-scroller grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $posts as $p ) : ?>
				<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" class="jluxe-home-card jluxe-home-blog-card group flex flex-col overflow-hidden rounded-xl border border-border bg-surface transition-shadow hover:shadow-md">
					<span class="block aspect-video overflow-hidden bg-muted">
						<?php if ( has_post_thumbnail( $p ) ) : ?>
							<?php echo get_the_post_thumbnail( $p, 'medium_large', array( 'class' => 'size-full object-cover transition-transform duration-300 group-hover:scale-105', 'loading' => 'lazy' ) ); ?>
						<?php else : ?><span class="grid size-full place-items-center text-text-muted" aria-hidden="true"><span class="text-3xl">▧</span></span><?php endif; ?>
					</span>
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
			<?php foreach ( $cards as $card ) :
				$icon_alias = array( 'badge-percent' => 'percent' );
				$icon_name = (string) ( $card['icon'] ?? '' );
				$trust_icon = ! empty( $card['svg'] ) ? jluxe_sanitize_svg_markup( (string) $card['svg'] ) : jluxe_nav_icon_svg( $icon_alias[ $icon_name ] ?? $icon_name, 'size-5' );
				?>
				<div class="flex flex-col items-center gap-2 rounded-xl border border-border bg-surface p-4 text-center">
					<span class="grid size-11 place-items-center rounded-xl border border-border bg-muted/40 text-primary" aria-hidden="true"><?php echo $trust_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized via helper. ?></span>
					<strong class="text-small text-foreground"><?php echo esc_html( $card['title'] ); ?></strong>
					<span class="text-caption text-text-muted"><?php echo esc_html( $card['subtitle'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
