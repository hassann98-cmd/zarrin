<?php
defined( 'ABSPATH' ) || exit;

/** Homepage builder compatibility loader; keep public frontend/admin entry points stable. */

/**
 * انواع بخش قابل‌افزودن + برچسب فارسی — فقط چیزهایی که واقعاً پیاده‌سازی و
 * به داده‌ی واقعی (WooCommerce/رسانه/متن ادمین) وصل شدن.
 */
function jluxe_homepage_section_types(): array {
	return array(
		'container'           => 'کانتینر / ستون‌ها',
		'hero'                => 'اسلایدر هیرو',
		'stories'             => 'نوار استوری',
		'product_grid'        => 'گرید محصولات (جدید/تخفیف‌دار/پرفروش/ویژه)',
		'category_grid'       => 'دسته‌بندی‌های محصول',
		'category_showcase'  => 'دسته‌بندی‌های ویژه (جدید)',
		'brand_marquee'      => 'نوار متحرک برندها',
		'brand_video_campaigns' => 'کمپین ویدیویی برندها',
		'cta'                  => 'دعوت به اقدام',
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
		'heading'             => 'تیتر مستقل',
		'image'               => 'تصویر مستقل',
		'button'              => 'دکمه مستقل',
		'html'                => 'HTML سفارشی',
		'spacer'              => 'فاصله‌گذار',
	);
}

function jluxe_homepage_mobile_category_hint_enabled(): bool {
	return (bool) jluxe_get_setting( 'homepage.mobile_category_swipe_hint_enabled', true );
}

function jluxe_render_homepage_sections(): void {
	$settings = jluxe_get_theme_settings();
	?>
	<style id="jluxe-homepage-system-style">
	.jluxe-home-section{box-sizing:border-box;width:100%;max-width:var(--jluxe-container-max,1320px);margin:24px auto;padding-inline:16px;}
	@media(max-width:767.98px){.jluxe-home-section{padding-inline:12px}}
	.jluxe-builder-container{box-sizing:border-box;display:grid;grid-template-columns:var(--jbc-tracks,repeat(var(--jbc-cols,2),minmax(0,1fr)));gap:var(--jbc-gap,20px);width:calc(100% - 32px);margin:24px auto;align-items:var(--jbc-align,start);min-height:40px}
	.jluxe-builder-container>.jluxe-home-section,.jluxe-builder-container>.jvb-node-preview>.jluxe-home-section{width:100%;margin:0;min-width:0}
	.jluxe-builder-container>*{min-width:0}
	@media(max-width:767.98px){.jluxe-builder-container:not(.jluxe-stack-never){grid-template-columns:repeat(var(--jbc-mobile,1),minmax(0,1fr));width:calc(100% - 24px)}.jluxe-builder-reverse-mobile:not(.jluxe-stack-never)[style*="--jbc-mobile:1"]{display:flex;flex-direction:column-reverse}.jluxe-builder-reverse-mobile:not(.jluxe-stack-never)[style*="--jbc-mobile:1"]>*{width:100%}}
	@media(min-width:768px) and (max-width:1023.98px){.jluxe-stack-tablet{grid-template-columns:repeat(var(--jbc-mobile,1),minmax(0,1fr))}.jluxe-design-hide-tablet{display:none!important}}
	.jluxe-design-width-boxed{max-width:var(--jluxe-container-max,1320px);margin-inline:auto}
	.jluxe-design-width-full>.jluxe-home-section,.jluxe-design-width-full>.jluxe-builder-container{width:100%;max-width:none!important}
	.jluxe-design-shadow-small{box-shadow:0 2px 8px #00000012}.jluxe-design-shadow-medium{box-shadow:0 8px 24px #0000001a}.jluxe-design-shadow-large{box-shadow:0 16px 48px #00000026}
	.jluxe-designed-section[style*="--jvb-text"] :is(h1,h2,h3,h4,p){color:var(--jvb-text)}
	@keyframes jvb-enter{from{opacity:0;transform:translate(var(--jvb-x,0),var(--jvb-y,0)) scale(var(--jvb-scale,1))}to{opacity:1;transform:none}}
	.jvb-entering{animation:jvb-enter var(--jvb-duration,600ms) both;animation-delay:var(--jvb-delay,0ms)}
	[data-jvb-animation="fade-up"]{--jvb-y:24px}[data-jvb-animation="fade-down"]{--jvb-y:-24px}[data-jvb-animation="slide-right"]{--jvb-x:24px}[data-jvb-animation="slide-left"]{--jvb-x:-24px}[data-jvb-animation="zoom"]{--jvb-scale:.95}
	@media(prefers-reduced-motion:reduce){.jvb-entering{animation:none!important}}
	.jluxe-designed-section{padding:var(--jvb-pad,0);box-sizing:border-box;}
	.jluxe-designed-section>.jluxe-home-section{margin-top:0;margin-bottom:0;}
	@media(max-width:767.98px){.jluxe-designed-section{padding:var(--jvb-mobile-pad,0)}.jluxe-design-hide-mobile{display:none!important}}
	@media(min-width:768px){.jluxe-design-hide-desktop{display:none!important}}
	.jluxe-home-section h2{color:hsl(var(--foreground));}
	.jluxe-home-section .jluxe-home-card,.jluxe-home-section .jluxe-home-panel{transition:transform .24s cubic-bezier(.2,.7,.2,1),box-shadow .24s ease,border-color .24s ease;}
	.jluxe-home-section .jluxe-home-card:hover,.jluxe-home-section .jluxe-home-panel:hover{transform:translateY(-2px);}
	.jluxe-home-section .jluxe-home-media{transition:transform .42s cubic-bezier(.2,.7,.2,1),filter .25s ease;}
	.jluxe-home-section .jluxe-home-card:hover .jluxe-home-media{transform:scale(1.03);}
	@media(max-width:767.98px){.jluxe-home-section{margin-block:12px;padding-block:8px}.jluxe-home-scroll-arrow,.jluxe-banner-slider-arrow{box-sizing:border-box;width:40px;height:40px;min-width:40px;min-height:40px;flex:0 0 40px}.jluxe-category-grid-ref-arrow{width:40px;height:40px;min-width:40px;min-height:40px;border-radius:50%}[data-jluxe-bs-dot]{box-sizing:border-box;display:grid;place-items:center;width:24px;height:24px;min-width:24px;min-height:0;padding:0;background:transparent!important}[data-jluxe-bs-dot]::before{content:"";display:block;width:8px;height:8px;border-radius:999px;background:rgba(255,255,255,.6);transition:width .2s ease,background-color .2s ease}[data-jluxe-bs-dot][data-active="true"]::before{width:20px;background:#fff}}
	@media(max-width:639px){.jluxe-home-section{margin-inline:auto;}}
	@media(prefers-reduced-motion:reduce){.jluxe-home-section .jluxe-home-card,.jluxe-home-section .jluxe-home-panel,.jluxe-home-section .jluxe-home-media{transition:none!important;transform:none!important;}}
	</style>
	<?php
	$builder_preview = function_exists( 'jluxe_visual_builder_get_authorized_preview_homepage' ) && null !== jluxe_visual_builder_get_authorized_preview_homepage();
	foreach ( $settings['homepage']['sections'] as $index => $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$enabled = ! empty( $section['enabled'] );
		if ( ! $builder_preview && ! $enabled ) {
			continue;
		}
		if ( ! $builder_preview ) {
			jluxe_render_homepage_section_frontend( $section );
			continue;
		}

		$section_type = isset( $section['type'] ) ? sanitize_key( (string) $section['type'] ) : '';
		echo '<div class="jvb-preview-section" data-jvb-preview-index="' . esc_attr( (string) $index ) . '" data-jvb-preview-enabled="' . ( $enabled ? 'true' : 'false' ) . '" data-jvb-preview-type="' . esc_attr( $section_type ) . '" style="display:contents">';
		if ( ! $enabled ) {
			jluxe_visual_builder_render_preview_placeholder( $section, 'disabled' );
		} else {
			ob_start();
			jluxe_render_homepage_section_frontend( $section );
			$section_html = (string) ob_get_clean();
			$visible_html = preg_replace( '#<(style|script)\b[^>]*>.*?</\1\s*>#is', '', $section_html );
			$visible_html = preg_replace( '/<!--.*?-->/s', '', (string) $visible_html );
			if ( '' === trim( (string) $visible_html ) ) {
				jluxe_visual_builder_render_preview_placeholder( $section, 'empty' );
			} else {
				echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the native section renderer above.
			}
		}
		echo '</div>';
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
	$preview_request = function_exists( 'jluxe_visual_builder_preview_request_is_authorized' ) && jluxe_visual_builder_preview_request_is_authorized();
	if ( ! is_front_page() && ! $preview_request ) {
		return;
	}

	$sections = jluxe_get_theme_settings()['homepage']['sections'] ?? array();
	if ( ! is_array( $sections ) ) {
		return;
	}

	$sections = jluxe_builder_flatten( $sections );
	$needs_homepage_script = false;
	$needs_hero_slider     = false;
	$needs_cta_styles      = false;
	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) || empty( $section['enabled'] ) ) {
			continue;
		}

		$type = $section['type'] ?? '';
		if ( 'cta' === $type ) {
			$needs_cta_styles = true;
		}
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

			case 'brand_video_campaigns':
				$needs_homepage_script = ! empty( $section['campaigns'] );
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

	if ( $needs_cta_styles ) {
		$cta_style_path = JLUXE_THEME_DIR . '/assets/css/homepage-cta.css';
		wp_enqueue_style(
			'jluxe-homepage-cta',
			JLUXE_THEME_URI . '/assets/css/homepage-cta.css',
			array(),
			file_exists( $cta_style_path ) ? (string) filemtime( $cta_style_path ) : '1.0.0'
		);
	}
}

function jluxe_render_homepage_section_frontend( array $section ): void {
 $preview = function_exists( 'jluxe_visual_builder_get_authorized_preview_homepage' ) && null !== jluxe_visual_builder_get_authorized_preview_homepage();
 if ( ! $preview ) { jluxe_render_homepage_section_styled( $section ); return; }
 ob_start(); jluxe_render_homepage_section_styled( $section ); $html = (string) ob_get_clean();
 // Inspect content before adding the editor wrapper: an empty wrapper must not
 // make an unconfigured widget appear to have rendered real content.
 $visible = preg_replace( '#<(style|script)\b[^>]*>.*?</\1\s*>#is', '', $html );
 $visible = preg_replace( '/<!--.*?-->/s', '', (string) $visible );
 echo '<div class="jvb-node-preview" data-jvb-node="' . esc_attr( $section['id'] ?? '' ) . '">';
 if ( '' === trim( (string) $visible ) ) { jluxe_visual_builder_render_preview_placeholder( $section, 'empty' ); }
 else { echo $html; } // Native renderer HTML, never imported HTML here.
 echo '</div>';
}

function jluxe_render_homepage_section_styled( array $section ): void {
	if ( empty( $section['design_enabled'] ) ) { jluxe_render_homepage_section_content( $section ); return; }
	// Normalize again at the output boundary; never accept arbitrary CSS from storage.
	$safe = jluxe_sanitize_homepage_section_design( $section );
	$d = $safe['design'];
	$classes = 'jluxe-designed-section' . ( $d['hide_mobile'] ? ' jluxe-design-hide-mobile' : '' ) . ( $d['hide_desktop'] ? ' jluxe-design-hide-desktop' : '' );
	$classes .= ' jluxe-design-width-' . $d['width'] . ' jluxe-design-shadow-' . $d['shadow'] . ( $d['hide_tablet'] ? ' jluxe-design-hide-tablet' : '' ) . ' ' . $d['class_name'];
	$style = sprintf( '--jvb-pad:%dpx;--jvb-mobile-pad:%dpx;margin-top:%dpx;margin-bottom:%dpx;border-radius:%dpx;text-align:%s;', $d['padding'], $d['mobile_padding'], $d['margin_top'], $d['margin_bottom'], $d['radius'], $d['align'] );
	if ( $d['background'] ) { $style .= 'background-color:' . $d['background'] . ';'; }
	if ( $d['text_color'] ) { $style .= '--jvb-text:' . $d['text_color'] . ';color:' . $d['text_color'] . ';'; }
	if ( $d['border_color'] ) { $style .= 'border:1px solid ' . $d['border_color'] . ';'; }
	if ( $d['background_image'] ) { $style .= 'background-image:url(' . wp_json_encode( $d['background_image'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP ) . ');background-size:cover;background-position:center;'; }
	$style .= '--jvb-duration:' . $d['animation_duration'] . 'ms;--jvb-delay:' . $d['animation_delay'] . 'ms;';
	echo '<div data-surface-tone="' . esc_attr( $d['surface_tone'] ) . '" data-jvb-animation="' . esc_attr( $d['animation'] ) . '" data-jvb-repeat="' . ( $d['animation_repeat'] ? '1' : '0' ) . '"' . ( $d['anchor'] ? ' id="' . esc_attr( $d['anchor'] ) . '"' : '' ) . ' class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">';
	jluxe_render_homepage_section_content( $section );
	echo '</div>';
}

function jluxe_render_homepage_section_content( array $section ): void {
	switch ( $section['type'] ?? '' ) {
		case 'container':
			$d = jluxe_builder_container_options( $section );
			$tracks = implode( ' ', array_map( static function ( $ratio ) { return 'minmax(0,' . (float) $ratio . 'fr)'; }, explode( ',', $d['column_ratios'] ) ) );
			printf( '<section class="jluxe-builder-container%s jluxe-stack-%s" style="--jbc-cols:%d;--jbc-tracks:%s;--jbc-align:%s;--jbc-mobile:%d;--jbc-gap:%dpx;max-width:%dpx">', $d['reverse_mobile'] ? ' jluxe-builder-reverse-mobile' : '', esc_attr( $d['stack_on'] ), $d['columns'], esc_attr( $tracks ), esc_attr( $d['vertical_align'] ), $d['mobile_columns'], $d['gap'], $d['max_width'] );
			foreach ( (array) ( $section['children'] ?? array() ) as $child ) {
				if ( is_array( $child ) && ! empty( $child['enabled'] ) ) { jluxe_render_homepage_section_frontend( $child ); }
			}
			echo '</section>';
			break;
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

		case 'brand_video_campaigns':
			jluxe_render_homepage_brand_video_campaigns( $section );
			break;

		case 'trust':
			jluxe_render_homepage_trust( $section );
			break;

		case 'blog':
			jluxe_render_homepage_blog( $section );
			break;

		case 'cta':
			jluxe_render_homepage_cta( $section );
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

		case 'heading':
			$tag = in_array( $section['tag'] ?? '', array( 'h2', 'h3', 'h4' ), true ) ? $section['tag'] : 'h2';
			if ( ! empty( $section['title'] ) ) { echo '<section class="jluxe-home-section"><' . $tag . '>' . esc_html( $section['title'] ) . '</' . $tag . '></section>'; }
			break;
		case 'image':
			if ( empty( $section['image_id'] ) ) { break; }
			echo '<section class="jluxe-home-section">';
			$url = esc_url( $section['url'] ?? '' );
			if ( $url ) { echo '<a href="' . $url . '">'; }
			echo wp_get_attachment_image( absint( $section['image_id'] ), 'large', false, array( 'alt' => $section['alt'] ?? '', 'style' => 'max-width:100%;height:auto;display:inline-block' ) );
			if ( $url ) { echo '</a>'; }
			echo '</section>';
			break;
		case 'button':
			$url = esc_url( $section['url'] ?? '' );
			if ( $url && ! empty( $section['title'] ) ) { echo '<section class="jluxe-home-section"><a class="inline-flex rounded-xl bg-primary px-6 py-3 text-white" href="' . $url . '">' . esc_html( $section['title'] ) . '</a></section>'; }
			break;

		case 'spacer':
			printf( '<div style="height:%dpx" aria-hidden="true"></div>', (int) ( $section['height'] ?? 40 ) );
			break;
	}
}

/** Small public enhancement: no hidden content when JS/observer is unavailable. */
function jluxe_enqueue_builder_animations(): void {
 if ( ! is_front_page() ) { return; }
 wp_enqueue_script( 'jluxe-builder-animations', JLUXE_THEME_URI . '/assets/js/builder-animations.js', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/builder-animations.js' ), true );
}

require_once __DIR__ . '/homepage/admin-editor.php';
require_once __DIR__ . '/homepage/sections-products.php';
require_once __DIR__ . '/homepage/sections-media.php';
require_once __DIR__ . '/homepage/sections-catalog.php';
require_once __DIR__ . '/homepage/hooks.php';
