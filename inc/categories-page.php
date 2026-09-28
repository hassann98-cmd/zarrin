<?php
/**
 * R91 — برگهٔ «همهٔ دسته‌بندی‌ها» + دکمهٔ «نمایش همه»ِ قابلِ‌ویرایش.
 *
 * درخواست: «در بخشِ “دسته‌بندی‌های محصول” صفحهٔ اصلی، “نمایش همه” قابلِ
 * ویرایش نیست؛ صفحه‌ای ساخته شود که همهٔ دسته‌ها داخلش باشند، بشود آیکونِ
 * هر دسته و مدلِ قرارگیری‌شان را تعیین کرد (مثلِ کدِ نمونه) و ریسپانسیو باشد».
 *
 * - برگه یک «برگهٔ» واقعیِ وردپرس است (نامکِ پیش‌فرض product-categories یا
 *   هر برگه‌ای که مدیر انتخاب کند) ⇒ عنوان/نامک/سئو (Rank Math) و افزودن به
 *   منو مثلِ هر برگهٔ دیگر؛ محتوای خودِ برگه (اگر بنویسید) بالای لیست می‌آید.
 * - خروجیِ اصلی سمتِ سرور (HTML + CSS) ⇒ کاملاً قابلِ‌خزش و بدونِ JS هم کارا.
 * - R92: حالت‌های «اپ‌گونه» (panel/stack) همان HTML را داخلِ React island
 *   «categories-browser» (src/islands/CategoriesBrowser.js) می‌گذارند تا
 *   زیردسته‌ها بدونِ رفرش باز شوند — پوسته از قبل React island دارد، پس هزینه
 *   فقط chunkِ کوچکِ همین island است (اصلاحِ ادعای R91؛ docs/FIXES.fa.md).
 * - تنظیمات: پیشخوان ← زرین ← «صفحهٔ دسته‌بندی‌ها» (jluxe-categories-page).
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_CATEGORIES_PAGE_SLUG = 'product-categories';

/** پیش‌فرض‌ها — در jluxe_theme_settings_defaults()['categories_page'] هم استفاده می‌شود. */
function jluxe_categories_page_defaults(): array {
	return array(
		'page_id'         => 0,
		'title'           => 'دسته‌بندی‌های محبوب',
		'layout'          => 'list',     // list (مثلِ نمونه) | tiles | compact
		'columns_desktop' => 2,
		'columns_tablet'  => 2,
		'columns_mobile'  => 1,
		'image_size'      => 80,
		'image_shape'     => 'rounded',  // rounded | circle | square
		'image_fit'       => 'cover',    // cover | contain
		'card_radius'     => 16,
		'card_bg'         => '#FFFFFF',
		'gap'             => 16,
		'show_count'      => false,
		'auto_append'     => true,
		'auto_depth'      => 'top',      // top | all
		'hide_empty'      => true,
		// R92 — مرورِ اپ‌گونه: panel (ستونِ دسته‌های اصلی + زیردسته‌ها) | stack (شبکه ← زیردسته‌ها) | grid (فقط شبکهٔ R91)
		'browse_mode'              => 'panel',
		'children_columns_desktop' => 4,
		'children_columns_tablet'  => 3,
		'children_columns_mobile'  => 2,
		'child_image_size'         => 64,
		'show_all_link'            => true,
		'items'           => array(),
	);
}

function jluxe_categories_page_browse_modes(): array {
	return array(
		'panel' => 'اپ‌گونه — ستونِ دسته‌های اصلی کنارِ زیردسته‌ها (مثلِ اپ‌های فروشگاهی)',
		'stack' => 'اپ‌گونه — شبکهٔ دسته‌های اصلی؛ لمس ← صفحهٔ زیردسته‌ها با «بازگشت»',
		'grid'  => 'ساده — فقط شبکهٔ دسته‌ها (هر کارت مستقیم به آرشیوِ دسته)',
	);
}

function jluxe_categories_page_layouts(): array {
	return array(
		'list'    => 'فهرستِ افقی (مثلِ نمونه: نام راست، آیکون چپ)',
		'tiles'   => 'کاشی (آیکون بالا، نام زیرش)',
		'compact' => 'فشرده (آیکونِ کوچک کنارِ نام)',
	);
}

/** تنظیماتِ ذخیره‌شده، ادغام با پیش‌فرض‌ها (کلیدِ جاافتاده ⇒ پیش‌فرض). */
function jluxe_categories_page_settings(): array {
	$saved = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'categories_page', array() ) : array();
	return array_merge( jluxe_categories_page_defaults(), is_array( $saved ) ? $saved : array() );
}

// =====================================================================
// sanitize
// =====================================================================
function jluxe_sanitize_categories_page( array $posted, array $defaults ): array {
	$defaults = array_merge( jluxe_categories_page_defaults(), $defaults );
	$choice   = static function ( $value, array $allowed, string $fallback ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	};
	$int = static function ( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return $fallback;
		}
		return max( $min, min( $max, (int) $value ) );
	};

	$clean = array(
		'page_id'         => absint( $posted['page_id'] ?? 0 ),
		'title'           => sanitize_text_field( (string) ( $posted['title'] ?? $defaults['title'] ) ),
		'layout'          => $choice( $posted['layout'] ?? '', array_keys( jluxe_categories_page_layouts() ), 'list' ),
		'columns_desktop' => $int( $posted['columns_desktop'] ?? null, 1, 6, 2 ),
		'columns_tablet'  => $int( $posted['columns_tablet'] ?? null, 1, 4, 2 ),
		'columns_mobile'  => $int( $posted['columns_mobile'] ?? null, 1, 3, 1 ),
		'image_size'      => $int( $posted['image_size'] ?? null, 32, 160, 80 ),
		'image_shape'     => $choice( $posted['image_shape'] ?? '', array( 'rounded', 'circle', 'square' ), 'rounded' ),
		'image_fit'       => $choice( $posted['image_fit'] ?? '', array( 'cover', 'contain' ), 'cover' ),
		'card_radius'     => $int( $posted['card_radius'] ?? null, 0, 40, 16 ),
		'card_bg'         => sanitize_hex_color( (string) ( $posted['card_bg'] ?? '' ) ) ?: '#FFFFFF',
		'gap'             => $int( $posted['gap'] ?? null, 0, 40, 16 ),
		'show_count'      => ! empty( $posted['show_count'] ),
		'auto_append'     => ! empty( $posted['auto_append'] ),
		'auto_depth'      => $choice( $posted['auto_depth'] ?? '', array( 'top', 'all' ), 'top' ),
		'hide_empty'      => ! empty( $posted['hide_empty'] ),
		'browse_mode'              => $choice( $posted['browse_mode'] ?? '', array_keys( jluxe_categories_page_browse_modes() ), 'panel' ),
		'children_columns_desktop' => $int( $posted['children_columns_desktop'] ?? null, 2, 8, 4 ),
		'children_columns_tablet'  => $int( $posted['children_columns_tablet'] ?? null, 1, 6, 3 ),
		'children_columns_mobile'  => $int( $posted['children_columns_mobile'] ?? null, 1, 4, 2 ),
		'child_image_size'         => $int( $posted['child_image_size'] ?? null, 32, 128, 64 ),
		'show_all_link'            => ! empty( $posted['show_all_link'] ),
		'items'           => array(),
	);

	$seen = array();
	foreach ( (array) ( $posted['items'] ?? array() ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$term_id = absint( $item['term_id'] ?? 0 );
		$title   = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
		$link    = trim( (string) ( $item['link'] ?? '' ) );
		$link    = '' !== $link ? esc_url_raw( $link ) : '';
		if ( $term_id ) {
			if ( isset( $seen[ $term_id ] ) || ! term_exists( $term_id, 'product_cat' ) ) {
				continue; // دسته‌ی تکراری/حذف‌شده
			}
			$seen[ $term_id ] = true;
		} elseif ( '' === $title || '' === $link ) {
			continue; // لینکِ دلخواه بدونِ عنوان یا آدرس معنایی ندارد
		}
		$clean['items'][] = array(
			'term_id'  => $term_id,
			'title'    => $title,
			'link'     => $term_id ? '' : $link,
			'image_id' => absint( $item['image_id'] ?? 0 ),
			'visible'  => ! empty( $item['visible'] ),
		);
		if ( count( $clean['items'] ) >= 80 ) {
			break;
		}
	}
	return $clean;
}

// =====================================================================
// برگه
// =====================================================================

/** شناسهٔ برگهٔ منتشرشدهٔ «همهٔ دسته‌بندی‌ها»، یا 0 اگر نیست. */
function jluxe_categories_page_id(): int {
	$cfg = jluxe_categories_page_settings();
	$id  = (int) $cfg['page_id'];
	if ( $id > 0 ) {
		$post = get_post( $id );
		if ( $post && 'page' === ( $post->post_type ?? '' ) && 'publish' === ( $post->post_status ?? '' ) ) {
			return (int) $post->ID;
		}
	}
	$page = get_page_by_path( JLUXE_CATEGORIES_PAGE_SLUG, OBJECT, 'page' );
	if ( $page && 'publish' === ( $page->post_status ?? '' ) ) {
		return (int) $page->ID;
	}
	return 0;
}

function jluxe_categories_page_url(): string {
	$id = jluxe_categories_page_id();
	return $id ? (string) get_permalink( $id ) : '';
}

/**
 * لینک/متنِ دکمهٔ «نمایش همه» بخش‌های دسته‌بندیِ صفحهٔ اصلی.
 *
 * @return array{url:string,text:string,label:string}|null null یعنی دکمه نمایش داده نشود.
 */
function jluxe_category_section_view_all( array $section ): ?array {
	$mode = (string) ( $section['view_all_mode'] ?? 'categories_page' );
	if ( ! in_array( $mode, array( 'categories_page', 'shop', 'custom', 'hidden' ), true ) ) {
		$mode = 'categories_page';
	}
	if ( 'hidden' === $mode ) {
		return null;
	}
	$default_text = 'category_showcase' === ( $section['type'] ?? '' ) ? 'مشاهده همه' : 'نمایش همه';
	$text         = trim( (string) ( $section['view_all_text'] ?? '' ) );
	$text         = '' !== $text ? $text : $default_text;
	$shop         = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$url          = '';
	$label        = 'نمایش همه دسته‌بندی‌ها';
	if ( 'custom' === $mode ) {
		$custom = trim( (string) ( $section['view_all_link'] ?? '' ) );
		$url    = '' !== $custom && function_exists( 'jluxe_resolve_site_link' ) ? jluxe_resolve_site_link( $custom ) : $custom;
		$label  = $text;
	} elseif ( 'categories_page' === $mode ) {
		$url = jluxe_categories_page_url();
	}
	if ( '' === $url ) {
		// برگه هنوز ساخته/منتشر نشده ⇒ مثلِ قبل به فروشگاه (هرگز ۴۰۴).
		$url   = $shop;
		$label = 'shop' === $mode || 'custom' === $mode ? 'مشاهده همه محصولات' : $label;
	}
	return array( 'url' => $url, 'text' => $text, 'label' => $label );
}

/** قالبِ برگه: هم با نامکِ product-categories (سلسله‌مراتبِ وردپرس) و هم هر برگهٔ انتخاب‌شده. */
function jluxe_categories_page_template( string $template ): string {
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return $template;
	}
	$id = jluxe_categories_page_id();
	if ( $id && (int) get_queried_object_id() === $id ) {
		$file = JLUXE_THEME_DIR . '/page-product-categories.php';
		if ( file_exists( $file ) ) {
			return $file;
		}
	}
	return $template;
}
add_filter( 'template_include', 'jluxe_categories_page_template', 20 );

/** ساختِ برگه (فقط با کلیکِ مدیر — هیچ‌وقت خودکار در درخواستِ فرانت). */
function jluxe_create_categories_page_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_create_categories_page' );
	$page = get_page_by_path( JLUXE_CATEGORIES_PAGE_SLUG, OBJECT, 'page' );
	if ( $page ) {
		$id = (int) $page->ID;
		if ( 'publish' !== $page->post_status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		}
	} else {
		$id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'همه دسته‌بندی‌ها',
				'post_name'    => JLUXE_CATEGORIES_PAGE_SLUG,
				'post_content' => '',
			)
		);
	}
	if ( $id > 0 ) {
		$cfg            = jluxe_categories_page_settings();
		$cfg['page_id'] = $id;
		jluxe_update_settings_section( 'categories_page', $cfg );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-categories-page&jluxe_cats_page=' . ( $id > 0 ? 'created' : 'error' ) ) );
	exit;
}
add_action( 'admin_post_jluxe_create_categories_page', 'jluxe_create_categories_page_action' );

// =====================================================================
// داده
// =====================================================================

/** دسته‌هایی که «افزودنِ خودکار» در نظر می‌گیرد (بدونِ «دسته‌بندی نشده»). */
function jluxe_categories_page_auto_terms( array $cfg ): array {
	if ( ! function_exists( 'get_terms' ) ) {
		return array();
	}
	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => ! empty( $cfg['hide_empty'] ),
		'orderby'    => 'name',
		'menu_order' => 'ASC', // ترتیبِ دستیِ خودِ ووکامرس (کشیدن در «دسته‌ها»)
	);
	if ( 'all' !== ( $cfg['auto_depth'] ?? 'top' ) ) {
		$args['parent'] = 0;
	}
	$terms = get_terms( $args );
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$uncategorized = (int) get_option( 'default_product_cat', 0 );
	$out           = array();
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) || (int) $term->term_id === $uncategorized || 'uncategorized' === ( $term->slug ?? '' ) ) {
			continue;
		}
		$out[] = $term;
	}
	return $out;
}

/**
 * فهرستِ نهاییِ کارت‌ها به ترتیب: ردیف‌های دستیِ «نمایش»‌دار، سپس (اگر
 * «افزودنِ خودکار» روشن است) هر دسته‌ای که در فهرست نیامده — ردیفِ مخفی‌شده
 * هم «در فهرست» حساب می‌شود، پس دوباره برنمی‌گردد.
 *
 * @return array<int,array{name:string,url:string,image_id:int,icon_svg:string,count:int,term_id:int}>
 */
function jluxe_categories_page_rows( array $cfg ): array {
	$rows   = array();
	$listed = array();
	foreach ( (array) ( $cfg['items'] ?? array() ) as $item ) {
		$term_id = (int) ( $item['term_id'] ?? 0 );
		if ( $term_id ) {
			$listed[ $term_id ] = true;
		}
		if ( empty( $item['visible'] ) && array_key_exists( 'visible', $item ) ) {
			continue;
		}
		if ( $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$row = jluxe_categories_page_term_row( $term, (int) ( $item['image_id'] ?? 0 ) );
			if ( '' !== trim( (string) ( $item['title'] ?? '' ) ) ) {
				$row['name'] = trim( (string) $item['title'] );
			}
			$rows[] = $row;
		} else {
			$title = trim( (string) ( $item['title'] ?? '' ) );
			$link  = trim( (string) ( $item['link'] ?? '' ) );
			if ( '' === $title || '' === $link ) {
				continue;
			}
			$rows[] = array(
				'term_id'  => 0,
				'name'     => $title,
				'url'      => function_exists( 'jluxe_resolve_site_link' ) ? jluxe_resolve_site_link( $link ) : $link,
				'image_id' => (int) ( $item['image_id'] ?? 0 ),
				'icon_svg' => empty( $item['image_id'] ) && function_exists( 'jluxe_nav_icon_svg' ) ? jluxe_nav_icon_svg( 'tag', 'jluxe-cats-card__svg' ) : '',
				'count'    => -1,
			);
		}
	}
	if ( ! empty( $cfg['auto_append'] ) ) {
		foreach ( jluxe_categories_page_auto_terms( $cfg ) as $term ) {
			if ( isset( $listed[ (int) $term->term_id ] ) ) {
				continue;
			}
			$rows[] = jluxe_categories_page_term_row( $term, 0 );
		}
	}
	return $rows;
}

function jluxe_categories_page_term_row( $term, int $image_id ): array {
	$term_id = (int) $term->term_id;
	if ( ! $image_id ) {
		$image_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
	}
	$icon_svg = '';
	if ( ! $image_id ) {
		// بدونِ تصویر ⇒ همان آیکونی که در «فروشگاه و دسته‌بندی ← آیکون دسته‌بندی‌ها» تعیین شده.
		$icon_svg = (string) get_term_meta( $term_id, '_jluxe_category_icon_svg', true );
		$icon_svg = '' !== $icon_svg && function_exists( 'jluxe_sanitize_svg_markup' ) ? jluxe_sanitize_svg_markup( $icon_svg ) : '';
		if ( '' === $icon_svg && function_exists( 'jluxe_nav_icon_svg' ) ) {
			$preset   = (string) get_term_meta( $term_id, '_jluxe_category_icon', true );
			$icon_svg = jluxe_nav_icon_svg( '' !== $preset ? $preset : 'grid', 'jluxe-cats-card__svg' );
		}
	}
	$link = get_term_link( $term );
	return array(
		'term_id'  => $term_id,
		'name'     => (string) $term->name,
		'url'      => is_string( $link ) ? $link : '',
		'image_id' => $image_id,
		'icon_svg' => $icon_svg,
		'count'    => (int) ( $term->count ?? 0 ),
	);
}

// =====================================================================
// رندر
// =====================================================================

/** شبکهٔ کارت‌ها (هم در برگه، هم در پیش‌نمایشِ پیشخوان). */
function jluxe_render_categories_grid( array $cfg, array $rows, int $limit = 0 ): void {
	if ( $limit > 0 ) {
		$rows = array_slice( $rows, 0, $limit );
	}
	if ( empty( $rows ) ) {
		echo '<p class="jluxe-cats-page__empty">هنوز دسته‌بندی‌ای برای نمایش نیست.</p>';
		return;
	}
	$layout = in_array( $cfg['layout'] ?? '', array_keys( jluxe_categories_page_layouts() ), true ) ? $cfg['layout'] : 'list';
	$size   = max( 32, min( 160, (int) $cfg['image_size'] ) );
	$radius = array( 'rounded' => '14px', 'circle' => '9999px', 'square' => '0px' )[ $cfg['image_shape'] ?? 'rounded' ] ?? '14px';
	$style  = sprintf(
		'--jc-cols-d:%d;--jc-cols-t:%d;--jc-cols-m:%d;--jc-img:%dpx;--jc-img-r:%s;--jc-fit:%s;--jc-card-r:%dpx;--jc-card-bg:%s;--jc-gap:%dpx',
		max( 1, min( 6, (int) $cfg['columns_desktop'] ) ),
		max( 1, min( 4, (int) $cfg['columns_tablet'] ) ),
		max( 1, min( 3, (int) $cfg['columns_mobile'] ) ),
		$size,
		$radius,
		'contain' === ( $cfg['image_fit'] ?? 'cover' ) ? 'contain' : 'cover',
		max( 0, min( 40, (int) $cfg['card_radius'] ) ),
		sanitize_hex_color( (string) ( $cfg['card_bg'] ?? '' ) ) ?: '#FFFFFF',
		max( 0, min( 40, (int) $cfg['gap'] ) )
	);
	jluxe_print_categories_page_css();
	?>
	<ul class="jluxe-cats-grid jluxe-cats-grid--<?php echo esc_attr( $layout ); ?>" style="<?php echo esc_attr( $style ); ?>" role="list">
		<?php foreach ( array_values( $rows ) as $i => $row ) : ?>
			<li>
				<a class="jluxe-cats-card" href="<?php echo esc_url( $row['url'] ); ?>">
					<span class="jluxe-cats-card__media" aria-hidden="true">
						<?php
						$src = $row['image_id'] ? wp_get_attachment_image_url( $row['image_id'], 'thumbnail' ) : '';
						if ( $src ) :
							$srcset = function_exists( 'wp_get_attachment_image_srcset' ) ? (string) wp_get_attachment_image_srcset( $row['image_id'], 'thumbnail' ) : '';
							?>
							<img src="<?php echo esc_url( $src ); ?>"<?php echo '' !== $srcset ? ' srcset="' . esc_attr( $srcset ) . '" sizes="' . esc_attr( $size . 'px' ) . '"' : ''; ?> width="<?php echo (int) $size; ?>" height="<?php echo (int) $size; ?>" alt="" decoding="async"<?php echo $i < 6 ? '' : ' loading="lazy"'; ?> />
						<?php else : ?>
							<span class="jluxe-cats-card__icon"><?php echo $row['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG پاک‌سازی‌شده/آیکونِ ثابتِ پوسته. ?></span>
						<?php endif; ?>
					</span>
					<span class="jluxe-cats-card__body">
						<span class="jluxe-cats-card__name"><?php echo esc_html( $row['name'] ); ?></span>
						<?php if ( ! empty( $cfg['show_count'] ) && $row['count'] >= 0 ) : ?>
							<span class="jluxe-cats-card__count"><?php echo esc_html( ( function_exists( 'jluxe_fa_digits' ) ? jluxe_fa_digits( $row['count'] ) : (string) $row['count'] ) . ' کالا' ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/** CSS یک‌بار در هر صفحه — بدونِ وابستگی به کلاس‌های کامپایل‌شدهٔ Tailwind. */
function jluxe_print_categories_page_css(): void {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<style id="jluxe-cats-page-css">
	.jluxe-cats-page{box-sizing:border-box;width:100%;max-width:1200px;margin:0 auto;padding:28px 16px;color:#1B1F22}
	.jluxe-cats-page__title{margin:0 0 24px;font-size:20px;line-height:1.6;font-weight:700;color:inherit}
	.jluxe-cats-page__intro{margin:-12px 0 24px;color:#5f6368;font-size:14px;line-height:1.9}
	.jluxe-cats-page__empty{color:#5f6368}
	.jluxe-cats-grid{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(var(--jc-cols-m),minmax(0,1fr));gap:var(--jc-gap)}
	.jluxe-cats-grid>li{margin:0;padding:0;min-width:0}
	.jluxe-cats-card{box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:12px;height:100%;padding:16px;border-radius:var(--jc-card-r);background:var(--jc-card-bg);color:#1B1F22;text-decoration:none;transition:box-shadow .2s ease,transform .2s ease}
	.jluxe-cats-card:focus-visible{outline:2px solid hsl(var(--primary,24 55% 47%));outline-offset:2px}
	.jluxe-cats-card__media{flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:var(--jc-img);height:var(--jc-img);border-radius:var(--jc-img-r);overflow:hidden}
	.jluxe-cats-card__media img{display:block;width:100%;height:100%;object-fit:var(--jc-fit);border-radius:var(--jc-img-r)}
	.jluxe-cats-card__icon{display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#F4F1EC;color:#8a6a3d;border-radius:var(--jc-img-r)}
	.jluxe-cats-card__icon svg{width:46%;height:46%}
	.jluxe-cats-card__body{display:flex;flex-direction:column;gap:2px;min-width:0}
	.jluxe-cats-card__name{font-size:18px;line-height:1.7;font-weight:500;overflow-wrap:anywhere}
	.jluxe-cats-card__count{font-size:12px;color:#6b7075}
	/* list — مثلِ نمونه: flex-row-reverse ⇒ در RTL نام سمتِ راست و تصویر سمتِ چپ */
	.jluxe-cats-grid--list .jluxe-cats-card{flex-direction:row-reverse}
	.jluxe-cats-grid--list .jluxe-cats-card__body{padding-inline-start:12px;flex:1 1 auto}
	/* tiles */
	.jluxe-cats-grid--tiles .jluxe-cats-card{flex-direction:column;justify-content:flex-start;text-align:center}
	.jluxe-cats-grid--tiles .jluxe-cats-card__body{align-items:center}
	.jluxe-cats-grid--tiles .jluxe-cats-card__name{font-size:15px}
	/* compact */
	.jluxe-cats-grid--compact .jluxe-cats-card{justify-content:flex-start;padding:10px 12px}
	.jluxe-cats-grid--compact .jluxe-cats-card__media{width:min(var(--jc-img),48px);height:min(var(--jc-img),48px)}
	.jluxe-cats-grid--compact .jluxe-cats-card__name{font-size:15px}
	@media (min-width:768px){.jluxe-cats-page{padding:28px 32px}.jluxe-cats-page__title{font-size:24px;margin-bottom:32px}.jluxe-cats-grid{grid-template-columns:repeat(var(--jc-cols-t),minmax(0,1fr))}}
	@media (min-width:1024px){.jluxe-cats-grid{grid-template-columns:repeat(var(--jc-cols-d),minmax(0,1fr))}}
	@media (max-width:420px){.jluxe-cats-card__name{font-size:16px}}
	@media (hover:hover) and (pointer:fine){.jluxe-cats-card:hover{box-shadow:0 8px 24px rgba(16,24,40,.08);transform:translateY(-2px)}}
	@media (prefers-reduced-motion:reduce){.jluxe-cats-card{transition:none}.jluxe-cats-card:hover{transform:none}}
	/* R92 — مرورِ اپ‌گونه */
	.jc-island [hidden]{display:none!important}
	.jc-browser{--jc-rail-top:12px;box-sizing:border-box}
	.has-sticky-header .jc-browser{--jc-rail-top:calc(var(--wp-admin--admin-bar--height,0px) + 84px)}
	.jc-browser--panel{display:grid;grid-template-columns:88px minmax(0,1fr);gap:12px;align-items:start}
	.jc-rail{position:sticky;top:var(--jc-rail-top);max-height:calc(100vh - var(--jc-rail-top) - 12px);max-height:calc(100dvh - var(--jc-rail-top) - 12px);overflow-y:auto;overscroll-behavior:contain;scrollbar-width:thin;background:#FAF8F5;border-radius:16px;padding:6px}
	.jc-rail__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px}
	.jc-rail__list>li{margin:0;padding:0}
	.jc-rail__item{position:relative;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;gap:6px;min-height:44px;padding:10px 4px;border-radius:12px;color:#3c4043;text-decoration:none;text-align:center;font-size:11.5px;line-height:1.6;transition:background-color .15s ease,color .15s ease}
	.jc-rail__item::before{content:"";position:absolute;inset-block:10px;inset-inline-start:0;width:3px;border-radius:3px;background:transparent}
	.jc-rail__item.is-active{background:#fff;color:hsl(var(--primary,24 55% 47%));font-weight:700;box-shadow:0 1px 3px rgba(16,24,40,.06)}
	.jc-rail__item.is-active::before{background:hsl(var(--primary,24 55% 47%))}
	.jc-rail__item:focus-visible,.jc-child:focus-visible,.jc-back:focus-visible,.jc-panel__all:focus-visible{outline:2px solid hsl(var(--primary,24 55% 47%));outline-offset:2px}
	.jc-rail__icon{flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;overflow:hidden}
	.jc-rail__icon img{display:block;width:100%;height:100%;object-fit:var(--jc-fit,cover)}
	.jc-rail__name{overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
	.jc-icon{display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:#F4F1EC;color:#8a6a3d;border-radius:inherit}
	.jc-icon svg{width:50%;height:50%}
	.jc-rail__item.is-active .jc-icon{background:hsl(var(--primary,24 55% 47%) / .1);color:hsl(var(--primary,24 55% 47%))}
	.jc-panels{min-width:0}
	.jc-panel{background:var(--jc-card-bg,#fff);border-radius:var(--jc-card-r,16px);padding:14px}
	.jc-panel__head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 12px;margin-bottom:14px}
	.jc-panel__title{margin:0;font-size:17px;line-height:1.7;font-weight:700;color:#1B1F22;outline:none;scroll-margin-top:var(--jc-rail-top)}
	.jc-panel__all{display:inline-flex;align-items:center;gap:4px;min-height:44px;font-size:13px;font-weight:500;color:hsl(var(--primary,24 55% 47%));text-decoration:none}
	.jc-back{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 14px 0 12px;margin:0;border:1px solid #e6e1d8;border-radius:9999px;background:#fff;color:#1B1F22;font:inherit;font-size:13px;cursor:pointer;text-decoration:none;flex:0 0 auto}
	.jc-browser--stack .jc-panel__head{justify-content:flex-start}
	.jc-browser--stack .jc-panel__all{margin-inline-start:auto}
	.jc-panel__empty{margin:8px 0 0;color:#5f6368;font-size:14px;line-height:1.9}
	.jc-children{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(var(--jc-ch-m,2),minmax(0,1fr));gap:10px}
	.jc-children>li{margin:0;padding:0;min-width:0}
	.jc-child{box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:8px;height:100%;min-height:44px;padding:12px 6px;border-radius:12px;background:#FAF8F5;color:#1B1F22;text-decoration:none;text-align:center;transition:background-color .15s ease,box-shadow .15s ease}
	.jc-child__media{display:flex;align-items:center;justify-content:center;width:var(--jc-ch-img,64px);height:var(--jc-ch-img,64px);max-width:100%;border-radius:var(--jc-img-r,14px);overflow:hidden}
	.jc-child__media img{display:block;width:100%;height:100%;object-fit:var(--jc-fit,cover)}
	.jc-child__name{font-size:13px;line-height:1.7;font-weight:500;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
	.jc-child__count{font-size:11.5px;color:#6b7075}
	.jc-anim{animation:jc-in .22s ease-out both}
	@keyframes jc-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
	@media (min-width:768px){
		.jc-browser--panel{grid-template-columns:240px minmax(0,1fr);gap:20px}
		.has-sticky-header .jc-browser{--jc-rail-top:calc(var(--wp-admin--admin-bar--height,0px) + 102px)}
		.jc-rail{padding:8px}
		.jc-rail__item{flex-direction:row;justify-content:flex-start;gap:12px;padding:8px 10px;font-size:14px;text-align:start}
		.jc-rail__icon{width:40px;height:40px}
		.jc-panel{padding:20px 24px}
		.jc-panel__title{font-size:20px}
		.jc-children{grid-template-columns:repeat(var(--jc-ch-t,3),minmax(0,1fr));gap:14px}
		.jc-child__name{font-size:14px}
	}
	@media (min-width:1024px){.jc-children{grid-template-columns:repeat(var(--jc-ch-d,4),minmax(0,1fr))}}
	@media (hover:hover) and (pointer:fine){.jc-rail__item:not(.is-active):hover{background:#fff}.jc-child:hover{background:#F4EFE7;box-shadow:0 4px 14px rgba(16,24,40,.06)}.jc-back:hover{background:#FAF8F5}}
	@media (prefers-reduced-motion:reduce){.jc-anim{animation:none}.jc-rail__item,.jc-child{transition:none}}
	</style>
	<?php
}

// =====================================================================
// R92 — مرورِ اپ‌گونه (زیردسته‌ها بدونِ رفرش)
// =====================================================================

/** حالتِ مرورِ مؤثر. */
function jluxe_categories_page_browse_mode( array $cfg ): string {
	$mode = (string) ( $cfg['browse_mode'] ?? 'panel' );
	return array_key_exists( $mode, jluxe_categories_page_browse_modes() ) ? $mode : 'panel';
}

/**
 * زیردسته‌های مستقیمِ هر دسته با یک کوئری (نه یک get_terms برای هر والد).
 *
 * @param int[] $parent_ids
 * @return array<int,array<int,array>> parent_id => ردیف‌ها (همان شکلِ jluxe_categories_page_term_row)
 */
function jluxe_categories_page_children( array $cfg, array $parent_ids ): array {
	$parent_ids = array_values( array_filter( array_map( 'intval', $parent_ids ) ) );
	if ( empty( $parent_ids ) || ! function_exists( 'get_terms' ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => ! empty( $cfg['hide_empty'] ),
			'orderby'    => 'name',
			'menu_order' => 'ASC',
		)
	);
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$wanted        = array_flip( $parent_ids );
	$uncategorized = (int) get_option( 'default_product_cat', 0 );
	$out           = array();
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) ) {
			continue;
		}
		$parent = (int) ( $term->parent ?? 0 );
		if ( ! $parent || ! isset( $wanted[ $parent ] ) || (int) $term->term_id === $uncategorized ) {
			continue;
		}
		$out[ $parent ][] = jluxe_categories_page_term_row( $term, 0 );
	}
	return $out;
}

/** src/srcsetِ تصویرِ بندانگشتی، یا null. */
function jluxe_categories_page_image_data( int $image_id ): ?array {
	if ( ! $image_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
		return null;
	}
	$src = (string) wp_get_attachment_image_url( $image_id, 'thumbnail' );
	if ( '' === $src ) {
		return null;
	}
	$srcset = function_exists( 'wp_get_attachment_image_srcset' ) ? (string) wp_get_attachment_image_srcset( $image_id, 'thumbnail' ) : '';
	return array(
		'src'    => $src,
		'srcset' => $srcset,
	);
}

/** آدرسِ پایهٔ همین برگه (بدونِ ?cat). */
function jluxe_categories_page_base_url(): string {
	$base = jluxe_categories_page_url();
	if ( '' === $base && function_exists( 'get_queried_object_id' ) && function_exists( 'get_permalink' ) && get_queried_object_id() ) {
		$base = (string) get_permalink( (int) get_queried_object_id() );
	}
	return $base;
}

/** آدرسِ «?cat=ID» روی همین برگه (بدونِ JS هم همان پنل را باز می‌کند). */
function jluxe_categories_page_cat_href( int $term_id, ?string $base = null ): string {
	$base = $base ?? jluxe_categories_page_base_url();
	return '' !== $base ? add_query_arg( 'cat', $term_id, $base ) : '?cat=' . $term_id;
}

/** دستهٔ فعال از ?cat= (فقط اگر یکی از والدهای پنل‌دار باشد). */
function jluxe_categories_page_requested_cat(): int {
	return isset( $_GET['cat'] ) && is_scalar( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- فقط ناوبریِ خواندنی.
}

/**
 * دادهٔ کاملِ مرورگر — هم برای رندرِ سرور و هم JSONِ island.
 *
 * @param array<int,array> $rows     ردیف‌های والد (jluxe_categories_page_rows)
 * @param array<int,array> $children parent_id => ردیف‌ها
 */
function jluxe_categories_browser_data( array $cfg, array $rows, array $children, int $requested = 0 ): array {
	$mode      = jluxe_categories_page_browse_mode( $cfg );
	$digits    = static fn( int $n ): string => ( function_exists( 'jluxe_fa_digits' ) ? jluxe_fa_digits( $n ) : (string) $n ) . ' کالا';
	$grid_size = max( 32, min( 160, (int) $cfg['image_size'] ) );
	$child_sz  = max( 32, min( 128, (int) ( $cfg['child_image_size'] ?? 64 ) ) );
	$parents   = array();
	$default   = 0;
	$active    = 0;
	$base      = jluxe_categories_page_base_url();
	foreach ( array_values( $rows ) as $row ) {
		$id   = (int) $row['term_id'];
		$kids = $id ? ( $children[ $id ] ?? array() ) : array();
		// panel: هر دستهٔ واقعی پنل دارد (حتی بی‌زیردسته: «همهٔ کالاها» + پیامِ خالی).
		// stack: فقط دسته‌ای که زیردسته دارد؛ بقیه مستقیم به آرشیو می‌روند.
		$has_panel = $id && ( 'panel' === $mode || ! empty( $kids ) );
		$kid_data  = array();
		foreach ( $kids as $kid ) {
			$kid_data[] = array(
				'id'         => (int) $kid['term_id'],
				'name'       => (string) $kid['name'],
				'url'        => (string) $kid['url'],
				'img'        => jluxe_categories_page_image_data( (int) $kid['image_id'] ),
				'svg'        => (string) $kid['icon_svg'],
				'count'      => (int) $kid['count'],
				'countLabel' => $digits( max( 0, (int) $kid['count'] ) ),
			);
		}
		$parents[] = array(
			'id'         => $id,
			'name'       => (string) $row['name'],
			'url'        => (string) $row['url'],
			'catHref'    => $has_panel ? jluxe_categories_page_cat_href( $id, $base ) : '',
			'allUrl'     => $id ? (string) $row['url'] : '',
			'img'        => jluxe_categories_page_image_data( (int) $row['image_id'] ),
			'svg'        => (string) $row['icon_svg'],
			'count'      => (int) $row['count'],
			'countLabel' => $digits( max( 0, (int) $row['count'] ) ),
			'hasPanel'   => $has_panel,
			'children'   => $kid_data,
		);
		if ( $has_panel && ! $default ) {
			$default = $id;
		}
		if ( $has_panel && $requested === $id ) {
			$active = $id;
		}
	}
	if ( ! $active && 'panel' === $mode ) {
		$active = $default;
	}
	$layout = in_array( $cfg['layout'] ?? '', array_keys( jluxe_categories_page_layouts() ), true ) ? $cfg['layout'] : 'list';
	$radius = array( 'rounded' => '14px', 'circle' => '9999px', 'square' => '0px' )[ $cfg['image_shape'] ?? 'rounded' ] ?? '14px';
	$header = function_exists( 'jluxe_get_theme_settings' ) ? (array) ( jluxe_get_theme_settings()['header'] ?? array() ) : array( 'sticky' => true );
	return array(
		'mode'         => $mode,
		'active'       => $active,
		'defaultId'    => $default,
		'gridLayout'   => $layout,
		'gridSize'     => $grid_size,
		'childSize'    => $child_sz,
		'railSize'     => 44,
		'showCount'    => ! empty( $cfg['show_count'] ),
		'showAll'      => ! empty( $cfg['show_all_link'] ),
		'stickyHeader' => ! empty( $header['sticky'] ),
		'resetHref'    => '' !== $base ? $base : '?',
		'style'        => array(
			'--jc-ch-d'   => (string) max( 2, min( 8, (int) $cfg['children_columns_desktop'] ) ),
			'--jc-ch-t'   => (string) max( 1, min( 6, (int) $cfg['children_columns_tablet'] ) ),
			'--jc-ch-m'   => (string) max( 1, min( 4, (int) $cfg['children_columns_mobile'] ) ),
			'--jc-ch-img' => $child_sz . 'px',
			'--jc-img-r'  => $radius,
			'--jc-fit'    => 'contain' === ( $cfg['image_fit'] ?? 'cover' ) ? 'contain' : 'cover',
			'--jc-card-r' => max( 0, min( 40, (int) $cfg['card_radius'] ) ) . 'px',
			'--jc-card-bg' => sanitize_hex_color( (string) ( $cfg['card_bg'] ?? '' ) ) ?: '#FFFFFF',
		),
		'gridStyle'    => array(
			'--jc-cols-d' => (string) max( 1, min( 6, (int) $cfg['columns_desktop'] ) ),
			'--jc-cols-t' => (string) max( 1, min( 4, (int) $cfg['columns_tablet'] ) ),
			'--jc-cols-m' => (string) max( 1, min( 3, (int) $cfg['columns_mobile'] ) ),
			'--jc-img'    => $grid_size . 'px',
			'--jc-gap'    => max( 0, min( 40, (int) $cfg['gap'] ) ) . 'px',
		),
		'labels'       => array(
			'rail'  => 'دسته‌های اصلی',
			'back'  => 'بازگشت به دسته‌ها',
			'all'   => 'همهٔ کالاهای این دسته',
			'empty' => 'این دسته زیردسته‌ای ندارد؛ از «همهٔ کالاهای این دسته» ببینید.',
		),
		'parents'      => $parents,
	);
}

/** style-آرایه ⇒ رشته‌ی style (برای HTMLِ سرور؛ React همان آرایه را می‌گیرد). */
function jluxe_categories_style_attr( array $style ): string {
	$out = array();
	foreach ( $style as $prop => $value ) {
		$out[] = $prop . ':' . $value;
	}
	return implode( ';', $out );
}

/** تصویر/آیکونِ یک آیتم — هم‌ساختار با Media در CategoriesBrowser.js. */
function jluxe_categories_browser_media( array $item, string $class, int $size, bool $eager, string $icon_class = 'jc-icon' ): string {
	$img = $item['img'] ?? null;
	if ( is_array( $img ) && ! empty( $img['src'] ) ) {
		$srcset = (string) ( $img['srcset'] ?? '' );
		$inner  = '<img src="' . esc_url( $img['src'] ) . '"'
			. ( '' !== $srcset ? ' srcset="' . esc_attr( $srcset ) . '" sizes="' . esc_attr( $size . 'px' ) . '"' : '' )
			. ' width="' . (int) $size . '" height="' . (int) $size . '" alt="" decoding="async"' . ( $eager ? '' : ' loading="lazy"' ) . ' />';
	} else {
		$inner = '<span class="' . esc_attr( $icon_class ) . '">' . (string) ( $item['svg'] ?? '' ) . '</span>'; // SVG پاک‌سازی‌شده/آیکونِ ثابت
	}
	return '<span class="' . esc_attr( $class ) . '" aria-hidden="true">' . $inner . '</span>';
}

/** یک پنلِ زیردسته‌ها (سرور). $visible=false ⇒ hidden اما قابلِ‌خزش. */
function jluxe_categories_browser_panel( array $data, array $parent, bool $visible ): string {
	$id   = (int) $parent['id'];
	$html = '<section class="jc-panel" id="jc-panel-' . $id . '" aria-labelledby="jc-panel-' . $id . '-t"' . ( $visible ? '' : ' hidden' ) . '>';
	$html .= '<div class="jc-panel__head">';
	if ( 'stack' === $data['mode'] ) {
		$html .= '<a class="jc-back" href="' . esc_url( $data['resetHref'] ) . '"><span aria-hidden="true">→</span> ' . esc_html( $data['labels']['back'] ) . '</a>';
	}
	$html .= '<h2 class="jc-panel__title" id="jc-panel-' . $id . '-t" tabindex="-1">' . esc_html( $parent['name'] ) . '</h2>';
	if ( $data['showAll'] && '' !== $parent['allUrl'] ) {
		$html .= '<a class="jc-panel__all" href="' . esc_url( $parent['allUrl'] ) . '">' . esc_html( $data['labels']['all'] ) . ' <span aria-hidden="true">←</span></a>';
	}
	$html .= '</div>';
	if ( ! empty( $parent['children'] ) ) {
		$html .= '<ul class="jc-children" role="list">';
		foreach ( $parent['children'] as $i => $child ) {
			$html .= '<li><a class="jc-child" href="' . esc_url( $child['url'] ) . '">'
				. jluxe_categories_browser_media( $child, 'jc-child__media', (int) $data['childSize'], $visible && $i < 8 )
				. '<span class="jc-child__name">' . esc_html( $child['name'] ) . '</span>'
				. ( $data['showCount'] && $child['count'] >= 0 ? '<span class="jc-child__count">' . esc_html( $child['countLabel'] ) . '</span>' : '' )
				. '</a></li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<p class="jc-panel__empty">' . esc_html( $data['labels']['empty'] ) . '</p>';
	}
	return $html . '</section>';
}

/** لینکِ یک دستهٔ اصلی (ریل یا کارتِ شبکه) — هم‌ساختار با parentLink در island. */
function jluxe_categories_browser_parent_open( array $data, array $parent, string $class ): string {
	if ( empty( $parent['hasPanel'] ) ) {
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $parent['url'] ) . '">';
	}
	$is_active = (int) $parent['id'] === (int) $data['active'];
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $parent['catHref'] ) . '" data-jc-parent="' . (int) $parent['id'] . '" aria-controls="jc-panel-' . (int) $parent['id'] . '"' . ( $is_active ? ' aria-current="true"' : '' ) . '>';
}

/**
 * مرورگرِ کامل: HTMLِ سرور (بدونِ JS هم کار می‌کند) داخلِ island
 * «categories-browser» + JSONِ داده. React بعد از بارگذاری جابه‌جایی را
 * بدونِ رفرش انجام می‌دهد؛ اگر اسکریپت نرسد، همین HTML و لینک‌های ?cat= می‌مانند.
 */
function jluxe_render_categories_browser( array $cfg, array $rows, array $children, int $requested = 0, bool $island = true ): void {
	if ( empty( $rows ) ) {
		echo '<p class="jluxe-cats-page__empty">هنوز دسته‌بندی‌ای برای نمایش نیست.</p>';
		return;
	}
	jluxe_print_categories_page_css();
	$data   = jluxe_categories_browser_data( $cfg, $rows, $children, $requested );
	$mode   = $data['mode'];
	$active = (int) $data['active'];
	$html   = '';
	if ( 'panel' === $mode ) {
		$html .= '<div class="jc-browser jc-browser--panel" style="' . esc_attr( jluxe_categories_style_attr( $data['style'] ) ) . '">';
		$html .= '<nav class="jc-rail" aria-label="' . esc_attr( $data['labels']['rail'] ) . '"><ul class="jc-rail__list" role="list">';
		foreach ( $data['parents'] as $i => $parent ) {
			$class = 'jc-rail__item' . ( $parent['hasPanel'] && (int) $parent['id'] === $active ? ' is-active' : '' );
			$html .= '<li>' . jluxe_categories_browser_parent_open( $data, $parent, $class )
				. jluxe_categories_browser_media( $parent, 'jc-rail__icon', (int) $data['railSize'], $i < 10 )
				. '<span class="jc-rail__name">' . esc_html( $parent['name'] ) . '</span></a></li>';
		}
		$html .= '</ul></nav><div class="jc-panels" aria-live="polite">';
		foreach ( $data['parents'] as $parent ) {
			if ( $parent['hasPanel'] ) {
				$html .= jluxe_categories_browser_panel( $data, $parent, (int) $parent['id'] === $active );
			}
		}
		$html .= '</div></div>';
	} else {
		$html .= '<div class="jc-browser jc-browser--stack' . ( $active ? ' is-drilled' : '' ) . '" style="' . esc_attr( jluxe_categories_style_attr( $data['style'] ) ) . '">';
		$html .= '<ul class="jluxe-cats-grid jluxe-cats-grid--' . esc_attr( $data['gridLayout'] ) . '" style="' . esc_attr( jluxe_categories_style_attr( $data['gridStyle'] ) ) . '" role="list"' . ( $active ? ' hidden' : '' ) . '>';
		foreach ( $data['parents'] as $i => $parent ) {
			$html .= '<li>' . jluxe_categories_browser_parent_open( $data, $parent, 'jluxe-cats-card' )
				. jluxe_categories_browser_media( $parent, 'jluxe-cats-card__media', (int) $data['gridSize'], $i < 6, 'jluxe-cats-card__icon' )
				. '<span class="jluxe-cats-card__body"><span class="jluxe-cats-card__name">' . esc_html( $parent['name'] ) . '</span>'
				. ( $data['showCount'] && $parent['count'] >= 0 ? '<span class="jluxe-cats-card__count">' . esc_html( $parent['countLabel'] ) . '</span>' : '' )
				. '</span></a></li>';
		}
		$html .= '</ul><div class="jc-panels">';
		foreach ( $data['parents'] as $parent ) {
			if ( $parent['hasPanel'] ) {
				$html .= jluxe_categories_browser_panel( $data, $parent, (int) $parent['id'] === $active );
			}
		}
		$html .= '</div></div>';
	}
	if ( ! $island ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- پیش‌نمایشِ پیشخوان؛ اجزا escape شده‌اند.
		return;
	}
	$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo '<div class="jc-island" data-jluxe-island="categories-browser" data-jluxe-keep-ssr>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- تک‌تکِ اجزا بالا escape شده‌اند.
	echo '<script type="application/json" id="jluxe-cats-data">' . ( is_string( $json ) ? $json : 'null' ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG ⇒ «</script>» ممکن نیست.
}

/** بدنهٔ برگه (در page-product-categories.php صدا زده می‌شود). */
function jluxe_render_categories_page(): void {
	$cfg   = jluxe_categories_page_settings();
	$title = trim( (string) $cfg['title'] );
	if ( '' === $title && function_exists( 'get_the_title' ) ) {
		$title = (string) get_the_title();
	}
	$intro = '';
	if ( function_exists( 'get_post' ) && function_exists( 'get_queried_object_id' ) ) {
		$post = get_post( (int) get_queried_object_id() );
		if ( $post && '' !== trim( (string) ( $post->post_content ?? '' ) ) ) {
			$intro = function_exists( 'apply_filters' ) ? (string) apply_filters( 'the_content', $post->post_content ) : (string) $post->post_content;
		}
	}
	?>
	<?php
	$header_cfg = function_exists( 'jluxe_get_theme_settings' ) ? (array) ( jluxe_get_theme_settings()['header'] ?? array() ) : array( 'sticky' => true );
	?>
	<section class="jluxe-cats-page<?php echo ! empty( $header_cfg['sticky'] ) ? ' has-sticky-header' : ''; ?>" aria-labelledby="jluxe-cats-page-title">
		<h1 id="jluxe-cats-page-title" class="jluxe-cats-page__title"><?php echo esc_html( '' !== $title ? $title : 'همه دسته‌بندی‌ها' ); ?></h1>
		<?php if ( '' !== $intro ) : ?>
			<div class="jluxe-cats-page__intro"><?php echo wp_kses_post( $intro ); ?></div>
		<?php endif; ?>
		<?php
		$mode = jluxe_categories_page_browse_mode( $cfg );
		if ( 'grid' === $mode ) {
			jluxe_render_categories_grid( $cfg, class_exists( 'WooCommerce' ) ? jluxe_categories_page_rows( $cfg ) : array() );
		} else {
			$cfg['auto_depth'] = 'top'; // مرورِ اپ‌گونه: ردیفِ اول فقط دسته‌های اصلی؛ زیردسته‌ها در پنل.
			$rows              = class_exists( 'WooCommerce' ) ? jluxe_categories_page_rows( $cfg ) : array();
			$children          = jluxe_categories_page_children( $cfg, array_column( $rows, 'term_id' ) );
			jluxe_render_categories_browser( $cfg, $rows, $children, jluxe_categories_page_requested_cat() );
		}
		?>
	</section>
	<?php
}

// =====================================================================
// پیشخوان
// =====================================================================
function jluxe_render_categories_page_settings(): void {
	$status = jluxe_handle_generic_settings_save( 'jluxe-categories-page' );
	if ( null === $status && isset( $_GET['jluxe_cats_page'] ) ) {
		$status = 'created' === sanitize_key( wp_unslash( $_GET['jluxe_cats_page'] ) ) ? 'saved' : 'error';
	}
	$cfg = jluxe_categories_page_settings();

	jluxe_settings_page_shell( 'صفحهٔ همه دسته‌بندی‌ها', 'jluxe-categories-page', $status, function () use ( $cfg ) {
		$page_id  = jluxe_categories_page_id();
		$page_url = $page_id ? get_permalink( $page_id ) : '';
		$items    = (array) $cfg['items'];
		if ( empty( $items ) && class_exists( 'WooCommerce' ) ) {
			// بارِ اول: همهٔ دسته‌ها از پیش در فهرست (تا «ذخیره» نزنید چیزی ثبت نمی‌شود).
			foreach ( jluxe_categories_page_auto_terms( array_merge( $cfg, array( 'hide_empty' => false ) ) ) as $term ) {
				$items[] = array( 'term_id' => (int) $term->term_id, 'title' => '', 'link' => '', 'image_id' => 0, 'visible' => true );
			}
		}
		$cat_options = function_exists( 'jluxe_hb_category_options' ) ? jluxe_hb_category_options() : array( 0 => '— انتخاب دسته —' );
		$cat_options[0] = '— لینکِ دلخواه (بدونِ دسته) —';
		?>
		<div class="jluxe-cats-admin-page">
			<h2>برگه</h2>
			<?php if ( $page_url ) : ?>
				<p>✓ برگه منتشر شده: <a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( rawurldecode( (string) $page_url ) ); ?></a> — دکمهٔ «نمایش همه»ِ بخش‌های دسته‌بندیِ صفحهٔ اصلی به این برگه می‌رود.</p>
			<?php else : ?>
				<div class="notice notice-warning inline"><p>برگهٔ «همه دسته‌بندی‌ها» هنوز منتشر نشده؛ تا آن موقع «نمایش همه» مثلِ قبل به فروشگاه می‌رود.</p></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jluxe_create_categories_page" />
					<?php wp_nonce_field( 'jluxe_create_categories_page' ); ?>
					<p><button type="submit" class="button button-secondary">ساخت و انتشارِ برگه (<code dir="ltr">/<?php echo esc_html( JLUXE_CATEGORIES_PAGE_SLUG ); ?>/</code>)</button></p>
				</form>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jluxe-cats-page-id">برگهٔ نمایش</label></th>
						<td>
							<?php
							if ( function_exists( 'wp_dropdown_pages' ) ) {
								wp_dropdown_pages(
									array(
										'name'              => 'categories_page[page_id]',
										'id'                => 'jluxe-cats-page-id',
										'selected'          => (int) $cfg['page_id'],
										'show_option_none'  => 'خودکار (برگه با نامکِ ' . JLUXE_CATEGORIES_PAGE_SLUG . ')',
										'option_none_value' => '0',
									)
								);
							}
							?>
							<p class="description">هر برگه‌ای را انتخاب کنید، لیستِ دسته‌ها جای قالبِ آن نمایش داده می‌شود؛ متنِ خودِ برگه (اگر بنویسید) بالای لیست می‌آید. عنوانِ سئو/نامک را از ویرایشِ برگه تغییر دهید.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jluxe-cats-title">تیترِ بالای لیست</label></th>
						<td><input type="text" id="jluxe-cats-title" name="categories_page[title]" value="<?php echo esc_attr( $cfg['title'] ); ?>" class="regular-text" /> <span class="description">خالی = عنوانِ برگه</span></td>
					</tr>
				</table>

				<h2>نحوهٔ مرور (زیردسته‌ها بدونِ رفرش)</h2>
				<fieldset class="jluxe-cats-layouts">
					<legend class="screen-reader-text">نحوهٔ مرور</legend>
					<?php
					$browse_mode = jluxe_categories_page_browse_mode( $cfg );
					foreach ( jluxe_categories_page_browse_modes() as $key => $label ) :
						?>
						<label class="jluxe-cats-layout-option">
							<input type="radio" name="categories_page[browse_mode]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $browse_mode, $key ); ?> />
							<span class="jluxe-cats-browse-demo jluxe-cats-browse-demo--<?php echo esc_attr( $key ); ?>" aria-hidden="true"><?php echo 'grid' === $key ? '<i></i><i></i><i></i><i></i>' : ( 'stack' === $key ? '<i></i><i></i><i></i>' : '<i></i><i></i>' ); ?></span>
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">دو حالتِ «اپ‌گونه» با React (همان جزیره‌هایی که هدر/منو هم دارند) زیردسته‌ها را بدونِ بارگذاریِ دوبارهٔ صفحه نشان می‌دهند؛ آدرس (<code dir="ltr">?cat=</code>) هم عوض می‌شود تا رفرش/اشتراک‌گذاری همان دسته را باز کند و دکمهٔ «برگشت» گوشی کار کند. HTML کامل سمتِ سرور هم هست ⇒ بدونِ JS و برای گوگل هم همهٔ زیردسته‌ها در دسترس‌اند. در این دو حالت ردیفِ اول همیشه فقط دسته‌های اصلی است.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">ستون‌های زیردسته‌ها</th>
						<td class="jluxe-cats-columns">
							<label>دسکتاپ <input type="number" name="categories_page[children_columns_desktop]" value="<?php echo esc_attr( $cfg['children_columns_desktop'] ); ?>" min="2" max="8" class="small-text" /></label>
							<label>تبلت <input type="number" name="categories_page[children_columns_tablet]" value="<?php echo esc_attr( $cfg['children_columns_tablet'] ); ?>" min="1" max="6" class="small-text" /></label>
							<label>موبایل <input type="number" name="categories_page[children_columns_mobile]" value="<?php echo esc_attr( $cfg['children_columns_mobile'] ); ?>" min="1" max="4" class="small-text" /></label>
						</td>
					</tr>
					<tr>
						<th scope="row">زیردسته</th>
						<td>
							<label>اندازهٔ تصویر (px) <input type="number" name="categories_page[child_image_size]" value="<?php echo esc_attr( $cfg['child_image_size'] ); ?>" min="32" max="128" class="small-text" /></label>
							<label><input type="checkbox" name="categories_page[show_all_link]" value="1" <?php checked( ! empty( $cfg['show_all_link'] ) ); ?> /> لینکِ «همهٔ کالاهای این دسته» بالای زیردسته‌ها</label>
						</td>
					</tr>
				</table>

				<h2>مدلِ قرارگیریِ کارت‌ها</h2>
				<p class="description">برای حالتِ «ساده» و صفحهٔ اولِ حالتِ «شبکه ← زیردسته‌ها».</p>
				<fieldset class="jluxe-cats-layouts">
					<legend class="screen-reader-text">مدلِ قرارگیری</legend>
					<?php foreach ( jluxe_categories_page_layouts() as $key => $label ) : ?>
						<label class="jluxe-cats-layout-option">
							<input type="radio" name="categories_page[layout]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $cfg['layout'], $key ); ?> />
							<span class="jluxe-cats-layout-demo jluxe-cats-layout-demo--<?php echo esc_attr( $key ); ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">تعدادِ ستون</th>
						<td class="jluxe-cats-columns">
							<label>دسکتاپ <input type="number" name="categories_page[columns_desktop]" value="<?php echo esc_attr( $cfg['columns_desktop'] ); ?>" min="1" max="6" class="small-text" /></label>
							<label>تبلت <input type="number" name="categories_page[columns_tablet]" value="<?php echo esc_attr( $cfg['columns_tablet'] ); ?>" min="1" max="4" class="small-text" /></label>
							<label>موبایل <input type="number" name="categories_page[columns_mobile]" value="<?php echo esc_attr( $cfg['columns_mobile'] ); ?>" min="1" max="3" class="small-text" /></label>
							<p class="description">نمونهٔ شما: دسکتاپ ۲، تبلت ۲، موبایل ۱ (پیش‌فرض). تبلت از ۷۶۸px، دسکتاپ از ۱۰۲۴px.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">آیکون / تصویر</th>
						<td>
							<label>اندازه (px) <input type="number" name="categories_page[image_size]" value="<?php echo esc_attr( $cfg['image_size'] ); ?>" min="32" max="160" class="small-text" /></label>
							<label>شکل <select name="categories_page[image_shape]">
								<option value="rounded" <?php selected( $cfg['image_shape'], 'rounded' ); ?>>گوشه‌گرد</option>
								<option value="circle" <?php selected( $cfg['image_shape'], 'circle' ); ?>>دایره</option>
								<option value="square" <?php selected( $cfg['image_shape'], 'square' ); ?>>مربع</option>
							</select></label>
							<label>برش <select name="categories_page[image_fit]">
								<option value="cover" <?php selected( $cfg['image_fit'], 'cover' ); ?>>پُرکردنِ کادر</option>
								<option value="contain" <?php selected( $cfg['image_fit'], 'contain' ); ?>>کاملِ تصویر (بدونِ برش)</option>
							</select></label>
						</td>
					</tr>
					<tr>
						<th scope="row">کارت</th>
						<td>
							<label>گردیِ گوشه (px) <input type="number" name="categories_page[card_radius]" value="<?php echo esc_attr( $cfg['card_radius'] ); ?>" min="0" max="40" class="small-text" /></label>
							<label>فاصلهٔ کارت‌ها (px) <input type="number" name="categories_page[gap]" value="<?php echo esc_attr( $cfg['gap'] ); ?>" min="0" max="40" class="small-text" /></label>
							<p><label>رنگِ کارت <input type="text" name="categories_page[card_bg]" value="<?php echo esc_attr( $cfg['card_bg'] ); ?>" class="jluxe-color-field" data-default-color="#FFFFFF" /></label></p>
							<label><input type="checkbox" name="categories_page[show_count]" value="1" <?php checked( ! empty( $cfg['show_count'] ) ); ?> /> نمایشِ تعدادِ کالای هر دسته</label>
						</td>
					</tr>
				</table>

				<h2>دسته‌ها و آیکون‌ها</h2>
				<p class="description">ترتیب را با کشیدنِ ☰ عوض کنید؛ برای هر دسته آیکون/تصویر (SVG یا PNG) انتخاب کنید — خالی = تصویرِ خودِ دسته در ووکامرس، و اگر آن هم نبود آیکونِ «فروشگاه و دسته‌بندی ← آیکون دسته‌بندی‌ها». برای پنهان‌کردن، تیکِ «نمایش» را بردارید. برای کارتی مثلِ «فروش ویژه» یک ردیفِ «لینکِ دلخواه» اضافه کنید (دسته را خالی بگذارید و عنوان + لینک بنویسید).</p>
				<p>
					<label><input type="checkbox" name="categories_page[auto_append]" value="1" <?php checked( ! empty( $cfg['auto_append'] ) ); ?> /> دسته‌های جدید خودکار به انتهای لیست اضافه شوند</label><br />
					<label><input type="checkbox" name="categories_page[hide_empty]" value="1" <?php checked( ! empty( $cfg['hide_empty'] ) ); ?> /> دسته‌های خالی (بدونِ کالا) خودکار اضافه نشوند</label><br />
					<label>دامنهٔ افزودنِ خودکار <select name="categories_page[auto_depth]">
						<option value="top" <?php selected( $cfg['auto_depth'], 'top' ); ?>>فقط دسته‌های اصلی</option>
						<option value="all" <?php selected( $cfg['auto_depth'], 'all' ); ?>>همهٔ دسته‌ها و زیردسته‌ها</option>
					</select></label>
				</p>
				<div class="jluxe-repeater" data-max="80">
					<div class="jluxe-repeater-list" data-group="items">
						<?php foreach ( array_values( $items ) as $i => $item ) { jluxe_render_categories_page_item_fields( $i, $item, $cat_options ); } ?>
					</div>
					<script type="text/template" class="jluxe-repeater-template"><?php jluxe_render_categories_page_item_fields( 0, array( 'visible' => true ), $cat_options ); ?></script>
					<button type="button" class="button jluxe-repeater-add">+ افزودن ردیف (دسته یا لینکِ دلخواه)</button>
				</div>
				<?php jluxe_settings_submit_button( true, 'categories_page' ); ?>
			</form>

			<h2>پیش‌نمایش (ذخیره‌شده، بدونِ جابه‌جایی — روی سایت کلیک‌پذیر است)</h2>
			<div class="jluxe-cats-admin-preview jc-island" dir="rtl">
				<?php
				if ( 'grid' === jluxe_categories_page_browse_mode( $cfg ) ) {
					jluxe_render_categories_grid( $cfg, class_exists( 'WooCommerce' ) ? jluxe_categories_page_rows( $cfg ) : array(), 8 );
				} else {
					$preview_cfg               = $cfg;
					$preview_cfg['auto_depth'] = 'top';
					$preview_rows              = class_exists( 'WooCommerce' ) ? array_slice( jluxe_categories_page_rows( $preview_cfg ), 0, 8 ) : array();
					jluxe_render_categories_browser( $preview_cfg, $preview_rows, jluxe_categories_page_children( $preview_cfg, array_column( $preview_rows, 'term_id' ) ), 0, false );
				}
				?>
			</div>
		</div>
		<?php
	} );
}

function jluxe_render_categories_page_item_fields( int $index, array $item, array $cat_options ): void {
	$base    = "categories_page[items][{$index}]";
	$visible = ! array_key_exists( 'visible', $item ) || ! empty( $item['visible'] );
	?>
	<div class="jluxe-repeater-item jluxe-cats-item">
		<div class="jluxe-repeater-item-head">
			<span class="jluxe-repeater-handle dashicons dashicons-menu"></span>
			<span>ردیف <span class="jluxe-repeater-index"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span></span>
			<label class="jluxe-cats-item-visible"><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[visible]" value="1" <?php checked( $visible ); ?> /> نمایش</label>
			<a href="#" class="jluxe-repeater-remove" title="حذف">✕</a>
		</div>
		<div class="jluxe-repeater-row">
			<?php jluxe_hb_field_select( $base, 'term_id', 'دسته‌ی ووکامرس', (string) ( $item['term_id'] ?? 0 ), $cat_options ); ?>
			<?php jluxe_hb_field_text( $base, 'title', 'نامِ نمایشی (برای لینکِ دلخواه اجباری)', (string) ( $item['title'] ?? '' ) ); ?>
			<?php jluxe_hb_field_text( $base, 'link', 'لینکِ دلخواه (فقط وقتی دسته انتخاب نشده)', (string) ( $item['link'] ?? '' ) ); ?>
			<div>
				<label>آیکون / تصویر</label>
				<?php jluxe_render_media_field( "{$base}[image_id]", (int) ( $item['image_id'] ?? 0 ), 'تصویرِ خودِ دسته' ); ?>
			</div>
		</div>
	</div>
	<?php
}
