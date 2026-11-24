<?php
defined( 'ABSPATH' ) || exit;

/** Single-product markup, schema, variation, gallery and presentation logic. */

/** Normalize variation attribute names to WooCommerce's canonical, unprefixed form. */
function jluxe_normalize_variable_attribute_name( string $name ): string {
	if ( 0 === strpos( $name, 'attribute_' ) ) {
		$name = substr( $name, 10 );
	}
	return sanitize_title( rawurldecode( $name ) );
}

/** false asks wc-add-to-cart-variation to resolve a selection over AJAX. */
function jluxe_available_variations_for_form( $product ) {
	$threshold  = max( 0, (int) apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product ) );
	$variations = count( $product->get_children() ) <= $threshold ? $product->get_available_variations() : false;
	if ( ! is_array( $variations ) ) {
		return $variations;
	}

	/*
	 * WooCommerce's parent attributes, variation meta, and saved defaults can
	 * contain the same Persian attribute slug in decoded or percent-encoded
	 * form. Normalize the payload as well as the PHP comparison path so the
	 * native variation script receives keys identical to the rendered selects.
	 */
	foreach ( $variations as &$variation ) {
		if ( ! is_array( $variation ) || ! isset( $variation['attributes'] ) || ! is_array( $variation['attributes'] ) ) {
			continue;
		}
		$attributes = array();
		foreach ( $variation['attributes'] as $name => $value ) {
			$name = jluxe_normalize_variable_attribute_name( (string) $name );
			if ( '' !== $name ) {
				$attributes[ 'attribute_' . $name ] = $value;
			}
		}
		$variation['attributes'] = $attributes;
	}
	unset( $variation );

	return $variations;
}

/** Add the original attachment URLs to WordPress images in rendered descriptions. */
function jluxe_product_description_image_tag_with_media_data( string $tag ): string {
	$attachment_id = 0;
	if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $tag, $class_match ) && preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class_match[2], $id_match ) ) {
		$attachment_id = absint( $id_match[1] );
	} elseif ( preg_match( '/\bdata-id\s*=\s*(["\'])(\d+)\1/i', $tag, $id_match ) ) {
		$attachment_id = absint( $id_match[2] );
	}
	if ( ! $attachment_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
		return $tag;
	}

	$media_url = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : '';
	$mime_type = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $attachment_id ) : '';
	$full_url  = (string) ( wp_get_attachment_image_url( $attachment_id, 'full' ) ?: $media_url );
	if ( 'image/gif' === strtolower( $mime_type ) && '' !== $media_url ) {
		$full_url = $media_url;
	}
	$attributes = array();
	if ( '' !== $full_url ) {
		$attributes['data-jluxe-full-src'] = $full_url;
	}
	if ( '' !== $media_url ) {
		$attributes['data-jluxe-media-url'] = $media_url;
	}
	if ( function_exists( 'get_attachment_link' ) ) {
		$attachment_url = (string) get_attachment_link( $attachment_id );
	} else {
		$attachment_url = function_exists( 'get_permalink' ) ? (string) get_permalink( $attachment_id ) : '';
	}
	if ( '' !== $attachment_url ) {
		$attributes['data-jluxe-attachment-url'] = $attachment_url;
	}

	// A resized GIF can lose animation frames; keep the original media file and no srcset.
	if ( 'image/gif' !== strtolower( $mime_type ) && function_exists( 'wp_get_attachment_image_srcset' ) ) {
		$srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );
		if ( is_string( $srcset ) && '' !== $srcset ) {
			$attributes['data-jluxe-full-srcset'] = $srcset;
			$attributes['data-jluxe-full-sizes']  = '90vw';
		}
	}
	if ( ! $attributes ) {
		return $tag;
	}

	foreach ( array_keys( $attributes ) as $name ) {
		$pattern = '/\s+' . preg_quote( $name, '/' ) . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
		$tag     = preg_replace( $pattern, '', $tag );
	}
	$attribute_html = '';
	foreach ( $attributes as $name => $value ) {
		$is_url_attribute = in_array( $name, array( 'data-jluxe-full-src', 'data-jluxe-media-url', 'data-jluxe-attachment-url' ), true );
		$escaped_value    = $is_url_attribute ? esc_url_raw( $value ) : $value;
		$attribute_html  .= ' ' . $name . '="' . esc_attr( $escaped_value ) . '"';
	}
	$tag = preg_replace_callback(
		'/\s*\/?>$/',
		static function ( array $matches ) use ( $attribute_html ): string {
			return $attribute_html . $matches[0];
		},
		$tag,
		1
	);
	return is_string( $tag ) ? $tag : '';
}

/**
 * Add media metadata to rendered WordPress images and wrap description tables.
 * Saved product content remains untouched; data markers make repeated passes safe.
 */
function jluxe_wrap_product_description_tables( $content ): string {
	if ( ! is_string( $content ) ) {
		return '';
	}
	$has_table = false !== stripos( $content, '<table' );
	$has_wp_image = (bool) preg_match( '/<img\b[^>]*(?:\bwp-image-\d+\b|\bdata-id\s*=\s*["\']\d+["\'])/i', $content );
	if ( ! $has_table && ! $has_wp_image ) {
		return $content;
	}

	$tokens = function_exists( 'wp_html_split' )
		? wp_html_split( $content )
		: preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $tokens ) ) {
		return $content;
	}

	$output           = '';
	$table_depth      = 0;
	$wrap_outer_table = false;
	foreach ( $tokens as $token ) {
		// Keep copied table formatting from overriding the product's responsive, theme-neutral table styles.
		if ( $table_depth > 0 || preg_match( '/^<table\b/i', $token ) ) {
			$token = preg_replace( '/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $token );
			if ( preg_match( '/^<(?:table|colgroup|col|tr|th|td)\b/i', $token ) ) {
				$token = preg_replace( '/\s+(?:width|height)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $token );
			}
		}
		if ( preg_match( '/^<img\b/i', $token ) ) {
			$token = jluxe_product_description_image_tag_with_media_data( $token );
		}
		if ( preg_match( '/^<table\b/i', $token ) ) {
			if ( 0 === $table_depth ) {
				$wrap_outer_table = ! preg_match( '/\bdata-jluxe-description-table(?:\s|=|>)/i', $token );
				if ( $wrap_outer_table ) {
					$output .= '<div class="jluxe-description-table-scroll" role="region" tabindex="0" aria-label="جدول توضیحات محصول؛ برای مشاهدهٔ ستون‌های بیشتر، افقی پیمایش کنید.">';
					$token   = preg_replace( '/^<table\b/i', '<table data-jluxe-description-table="1"', $token, 1 );
				}
			}
			++$table_depth;
			$output .= $token;
			continue;
		}

		if ( preg_match( '/^<\/table\s*>/i', $token ) && $table_depth > 0 ) {
			$output .= $token;
			--$table_depth;
			if ( 0 === $table_depth && $wrap_outer_table ) {
				$output           .= '</div>';
				$wrap_outer_table = false;
			}
			continue;
		}

		$output .= $token;
	}

	return $table_depth > 0 ? $content : $output;
}

/** Only the long product-description output uses the_content; leave every other page alone. */
function jluxe_wrap_single_product_description_tables( $content ) {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $content;
	}
	return jluxe_wrap_product_description_tables( $content );
}

/**
 * دیتای ساختاریافته‌ی Product/WebSite (JSON-LD) — طبقِ درخواستِ صریحِ کاربر
 * («سئوی گوگل و هوش مصنوعی»). خودِ ووکامرس این‌ها رو خودکار می‌سازه، ولی
 * WC_Structured_Data::generate_product_data روی هوکِ
 * woocommerce_single_product_summary قلاب شده و generate_website_data روی
 * woocommerce_before_main_content — این تم چون طراحیِ سه‌ستونه‌ی خودش رو
 * برای صفحه‌ی محصول داره، دیگه این اکشن‌ها رو صدا نمی‌زنه (ببین کامنتِ
 * woocommerce/content-single-product.php: «دیگه استفاده نمی‌شن») — نتیجه:
 * تا الان هیچ Product JSON-LDای (قیمت/موجودی/امتیاز، پیش‌نیازِ Rich
 * Results گوگل برای محصولات) توی صفحه‌ی محصول چاپ نمی‌شد؛ با بررسیِ زنده‌ی
 * خروجیِ HTML (صفر تگِ application/ld+json) تأیید شد. راه‌حل: مستقیم و
 * بدون وابستگی به اون اکشن‌های حذف‌شده صداشون می‌زنیم — output_structured_data
 * (روی wp_footer، دست‌نخورده) هنوز خودش چاپش می‌کنه.
 */
function jluxe_emit_structured_data(): void {
	if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->structured_data ) {
		return;
	}
	/*
	 * طبقِ درخواستِ کاربر («باید کاملاً با Rank Math Pro هماهنگ باشیم»):
	 * چون این تابع generate_product_data رو مستقیم صدا می‌زنه (نه از طریقِ
	 * اکشنِ عادیِ woocommerce_single_product_summary)، اگه پلاگینِ سئوی
	 * دیگه‌ای (Rank Math/Yoast) هم برای همین محصول Product Schema بسازه،
	 * حذف‌کردنِ اون اکشنِ عادی توسطِ اون پلاگین (که معمولاً همینه) هیچ اثری
	 * روی این فراخوانیِ مستقیم نداره — نتیجه‌ش دو تا JSON-LD با
	 * "@type":"Product" همزمان توی <head>، یعنی دقیقاً همون چیزی که Rich
	 * Results گوگل رو گیج می‌کنه. پس اگه یکی از این پلاگین‌ها فعاله، خروجیِ
	 * Product/Website رو به خودش واگذار می‌کنیم؛ RANK_MATH_VERSION هم
	 * نسخه‌ی رایگان هم Pro رو پوشش می‌ده (هر دو همین ثابت رو ست می‌کنن).
	 */
	if ( function_exists( 'jluxe_seo_plugin_is_active' ) && jluxe_seo_plugin_is_active() ) {
		return;
	}
	/*
	 * باگِ واقعیِ پیدا‌شده در همین تست: generate_product_data() بدونِ آرگومان
	 * از globalِ $product می‌خونه، ولی اون global توسطِ خودِ ووکامرس فقط
	 * ضمنِ the_post() (یعنی داخلِ Loop، بعد از این‌جا) ست می‌شه — پس در
	 * wp_head (قبل از Loop) هنوز خالیه و set_data() به‌خاطرِ نبودِ @type
	 * ساکت رد می‌شد (count=0، با کامنتِ دیباگِ موقت تأیید شد). راه‌حل:
	 * مستقیم wc_get_product(get_queried_object_id()) رو صدا بزن — به
	 * globalِ Loop اصلاً وابسته نیست.
	 */
	if ( is_product() ) {
		$queried_product = wc_get_product( get_queried_object_id() );
		if ( $queried_product instanceof WC_Product ) {
			WC()->structured_data->generate_product_data( $queried_product );
		}
	}
	WC()->structured_data->generate_website_data();
}

/**
 * Connect WooCommerce's WebSite entity to the site's canonical Organization
 * and expose the real internal search URL as a machine-readable action. Google
 * retired its sitelinks-search-box feature; this is not a promise of a special
 * search result or a ranking boost.
 */
function jluxe_add_website_search_action( array $markup ): array {
	$markup['@id'] = $markup['@id'] ?? home_url( '/#website' );
	$publisher = isset( $markup['publisher'] ) && is_array( $markup['publisher'] ) ? $markup['publisher'] : array();
	$publisher['@id'] = $publisher['@id'] ?? home_url( '/#organization' );
	$markup['publisher'] = $publisher;
	$markup['potentialAction'] = array(
		'@type'       => 'SearchAction',
		'target'      => array(
			'@type'       => 'EntryPoint',
			'urlTemplate' => home_url( '/?s={search_term_string}' ),
		),
		'query-input' => 'required name=search_term_string',
	);
	return $markup;
}

/** Give WooCommerce Product entities a stable URL identity for linked JSON-LD graphs. */
function jluxe_add_product_schema_identity( array $markup, $product = null ): array {
	if ( ! $product instanceof WC_Product ) {
		return $markup;
	}
	$url = (string) get_permalink( $product->get_id() );
	if ( '' === $url ) {
		return $markup;
	}
	$markup['@id'] = $markup['@id'] ?? $url . '#product';
	$markup['url'] = $markup['url'] ?? $url;
	$markup['mainEntityOfPage'] = $markup['mainEntityOfPage'] ?? array( '@id' => $url );
	return $markup;
}

/** Add visible grouped specifications to WooCommerce's existing Product JSON-LD. */
function jluxe_add_grouped_product_specs_to_schema( array $markup, $product = null ): array {
	if ( ! $product instanceof WC_Product ) {
		return $markup;
	}

	$groups     = jluxe_get_product_advanced_attribute_groups( (int) $product->get_id() );
	$properties = array();
	foreach ( $groups as $group ) {
		$group_title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
		$items       = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) || ! is_scalar( $item['label'] ) || ! is_scalar( $item['value'] ) ) {
				continue;
			}
			$name  = sanitize_text_field( (string) $item['label'] );
			$value = sanitize_text_field( (string) $item['value'] );
			if ( '' === $name || '' === $value ) {
				continue;
			}
			$properties[] = array(
				'@type'       => 'PropertyValue',
				'name'        => $name,
				'value'       => $value,
				'description' => $group_title,
			);
		}
	}

	foreach ( jluxe_get_additional_product_specification_rows( $product, $groups ) as $row ) {
		$name  = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
		$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';
		if ( '' === $name || '' === $value ) {
			continue;
		}
		$properties[] = array(
			'@type'       => 'PropertyValue',
			'name'        => $name,
			'value'       => $value,
			'description' => $groups ? 'سایر مشخصات' : 'مشخصات فنی',
		);
	}

	if ( ! $properties ) {
		return $markup;
	}

	$existing = $markup['additionalProperty'] ?? array();
	if ( is_array( $existing ) && ( isset( $existing['@type'] ) || isset( $existing['name'] ) ) ) {
		$existing = array( $existing );
	}
	$existing = is_array( $existing ) ? array_values( $existing ) : array();
	$seen     = array();
	foreach ( $existing as $property ) {
		if ( ! is_array( $property ) || ! isset( $property['name'], $property['value'] ) || ! is_scalar( $property['name'] ) || ! is_scalar( $property['value'] ) ) {
			continue;
		}
		$key = strtolower( trim( (string) $property['name'] ) ) . "\0" . trim( (string) $property['value'] );
		$seen[ $key ] = true;
	}
	foreach ( $properties as $property ) {
		$key = strtolower( $property['name'] ) . "\0" . $property['value'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$existing[] = $property;
		$seen[ $key ] = true;
	}

	$markup['additionalProperty'] = $existing;
	return $markup;
}

/** Keep the product LCP preload's responsive slot size aligned with the rendered image. */
function jluxe_single_product_image_sizes( string $layout ): string {
	if ( in_array( $layout, array( 'classic', 'reference' ), true ) ) {
		return '(max-width: 767px) calc(100vw - 64px), 420px';
	}
	return '(max-width: 1024px) 100vw, 26rem';
}

/**
 * Preload the main product image using the same responsive candidates as the
 * rendered image. Classic and reference layouts use `large` plus srcset
 * (rather than downloading the original full-size file for a 420px image).
 */
function jluxe_preload_single_product_lcp_image(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return;
	}

	$image_id = $product->get_image_id();
	if ( ! $image_id ) {
		return;
	}

	$layout     = jluxe_get_theme_settings()['product_page']['layout'] ?? 'default';
	$size       = in_array( $layout, array( 'classic', 'reference' ), true ) ? 'large' : 'woocommerce_single';
	$imagesizes = jluxe_single_product_image_sizes( (string) $layout );
	$image      = jluxe_get_responsive_attachment_image( (int) $image_id, $size, $imagesizes );
	$url        = $image['src'];
	$srcset     = $image['srcset'];

	if ( $url ) {
		printf(
			'<link rel="preload" as="image" href="%1$s" fetchpriority="high"%2$s>' . "\n",
			esc_url( $url ),
			$srcset ? sprintf( ' imagesrcset="%s" imagesizes="%s"', esc_attr( $srcset ), esc_attr( $image['sizes'] ) ) : ''
		);
	}
}

/**
 * Mark only the featured image on the default product page as critical. Classic
 * and reference layouts emit their own image tag and critical attributes.
 *
 * @param array         $attr Image HTML attributes.
 * @param WP_Post|int   $attachment Attachment being rendered.
 * @param string|int[]  $size Requested image size.
 * @return array
 */
function jluxe_product_lcp_image_attributes( array $attr, $attachment, $size ): array {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $attr;
	}

	$attachment_id = is_object( $attachment ) ? absint( $attachment->ID ?? 0 ) : absint( $attachment );
	$product       = function_exists( 'wc_get_product' ) ? wc_get_product( get_queried_object_id() ) : false;
	if ( ! $product || ! $attachment_id || $attachment_id !== (int) $product->get_image_id() ) {
		return $attr;
	}

	$layout = jluxe_get_theme_settings()['product_page']['layout'] ?? 'default';
	if ( in_array( $layout, array( 'classic', 'reference' ), true ) || 'woocommerce_single' !== $size ) {
		return $attr;
	}

	$attr['data-no-lazy'] = '1';
	$attr['loading']      = 'eager';
	$attr['fetchpriority'] = 'high';
	$attr['sizes']        = jluxe_single_product_image_sizes( 'default' );

	return $attr;
}

/** نوار چسبانِ افزودن به سبد — اسکریپت فقط صفحهٔ محصول. */
function jluxe_enqueue_sticky_cta(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/sticky-cta.js';
	wp_enqueue_script(
		'jluxe-sticky-cta',
		JLUXE_THEME_URI . '/assets/js/sticky-cta.js',
		array( 'jluxe-storefront-utils' ),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		true
	);
}

/** زنجیرهٔ بردکرامب: خانه → فروشگاه → دستهٔ فعلی و والدهایش (حداکثر ۳ سطح) → محصول. */
function jluxe_breadcrumb_items( $product ): array {
	$items = array(
		array( 'خانه', home_url( '/' ) ),
	);
	if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
		$items[] = array( 'فروشگاه', get_permalink( wc_get_page_id( 'shop' ) ) );
	}
	if ( $product instanceof WC_Product && function_exists( 'wc_get_product_terms' ) ) {
		$terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) );
		$chain = array();
		foreach ( $terms as $term ) {
			if ( ! isset( $term->term_id ) ) {
				continue;
			}
			$walk   = array();
			$cursor = $term;
			$depth  = 0;
			while ( $cursor instanceof WP_Term && $depth < 3 ) {
				array_unshift( $walk, array( $cursor->name, get_term_link( $cursor ) ) );
				if ( ! $cursor->parent ) {
					break;
				}
				$parent = get_term( $cursor->parent, 'product_cat' );
				if ( $parent instanceof WP_Error ) {
					break;
				}
				$cursor = $parent;
				++$depth;
			}
			if ( count( $walk ) > count( $chain ) ) {
				$chain = array_slice( $walk, -3 );
			}
		}
		$items = array_merge( $items, $chain );
	}
	if ( $product instanceof WC_Product ) {
		$items[] = array( (string) $product->get_name(), get_permalink( $product->get_id() ) );
	}
	return $items;
}

/** چاپ BreadcrumbList JSON-LD — فقط داده؛ نمایشِ مرئی همان nav خود قالب‌هاست. */
function jluxe_print_breadcrumb_jsonld( $product ): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	if ( function_exists( 'jluxe_seo_plugin_is_active' ) && jluxe_seo_plugin_is_active() ) {
		return;
	}
	$items = jluxe_breadcrumb_items( $product );
	if ( count( $items ) < 2 ) {
		return;
	}
	$elements = array();
	$position = 0;
	foreach ( $items as $item ) {
		$label = trim( (string) $item[0] );
		$url   = (string) $item[1];
		if ( '' === $label || '' === $url ) {
			continue;
		}
		++$position;
		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $label,
			'item'     => esc_url_raw( $url ),
		);
	}
	if ( count( $elements ) < 2 ) {
		return;
	}
	echo '<script type="application/ld+json">' . jluxe_jsonld_encode( array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	) ) . '</script>' . "\n";
}

/**
 * متن و حالتِ موجودی برای CTA چسبان؛ فقط از موجودی واقعی WooCommerce استفاده می‌کند.
 * تنوع تا پیش از انتخاب، موجودیِ کلیِ والد را به‌جای گزینهٔ انتخاب‌شده جا نمی‌زند.
 */
function jluxe_sticky_stock_presentation( $product ): array {
	if ( ! $product instanceof WC_Product ) {
		return array( 'state' => 'out-of-stock', 'label' => 'ناموجود' );
	}

	$is_variable = $product->is_type( 'variable' );
	if ( $is_variable ) {
		$default_attributes = function_exists( 'jluxe_default_variation_pick' ) ? jluxe_default_variation_pick( $product ) : array();
		if ( ! empty( $default_attributes ) ) {
			// The product form selects this purchasable, in-stock variation on
			// first render, so the sticky CTA can add it without a false prompt.
			return array( 'state' => 'in-stock', 'label' => '' );
		}
		// A variable parent can report instock even with no child variations,
		// or when every child is out of stock/not purchasable. Trust the
		// eligible-variation scan, never the cached parent flag.
		return array( 'state' => 'out-of-stock', 'label' => 'ناموجود' );
	}

	$available = $product->is_in_stock() && $product->is_purchasable();
	if ( ! $available ) {
		return array( 'state' => 'out-of-stock', 'label' => 'ناموجود' );
	}
	if ( $is_variable ) {
		return array( 'state' => 'choose', 'label' => 'انتخاب گزینه برای بررسی موجودی' );
	}

	if ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'get_stock_quantity' ) ) {
		$quantity = $product->get_stock_quantity();
		if ( is_numeric( $quantity ) && (int) $quantity > 0 && (int) $quantity <= 5 ) {
			return array(
				'state' => 'low-stock',
				'label' => sprintf( 'فقط %s عدد باقی مانده', jluxe_fa_digits( (string) (int) $quantity ) ),
			);
		}
	}

	/* حالتِ موجودی برای منطقِ JS لازم است، اما برچسبِ معمولِ «موجود در انبار»
	 * عمداً چاپ نمی‌شود؛ خودِ دکمهٔ خرید برای موجودبودن کافی است. */
	return array( 'state' => 'in-stock', 'label' => '' );
}

/**
 * کنترلِ تعدادِ نوارِ چسبان؛ فقط دکمه و نمایشگر است و هیچ input/form موازی
 * نمی‌سازد. جاوااسکریپت مقدار را به input.qty واقعیِ فرم ووکامرس می‌نویسد.
 */
function jluxe_render_sticky_quantity_control(): void {
	?>
	<div class="jluxe-mobile-quantity" data-jluxe-mobile-qty-control hidden role="group" aria-label="تعداد محصول">
		<button type="button" class="jluxe-mobile-quantity-step" data-jluxe-mobile-qty-step="decrease" aria-label="کاهش تعداد" disabled>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>
		</button>
		<output class="jluxe-mobile-quantity-value" data-jluxe-mobile-qty-value aria-live="polite" aria-atomic="true">۱</output>
		<button type="button" class="jluxe-mobile-quantity-step" data-jluxe-mobile-qty-step="increase" aria-label="افزایش تعداد" disabled>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
		</button>
	</div>
	<?php
}

/**
 * نوار چسبانِ افزودن به سبد — فقط موبایل (CSS بالای 768px مخفی است).
 * رندر سروری دارد تا بدون JS هم قیمت/وضعیت/مسیر دیده شود؛ برای محصول متغیر،
 * پس از انتخابِ تنوع همین نوار قیمت و وضعیت گزینه را نشان می‌دهد.
 */
function jluxe_render_sticky_add_to_cart( $product, bool $always_visible = false ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$is_variable  = $product->is_type( 'variable' );
	$stock_status = jluxe_sticky_stock_presentation( $product );
	$in_stock     = 'out-of-stock' !== $stock_status['state'];
	$purchasable  = $product->is_purchasable();
	$has_default_variation = $is_variable && 'in-stock' === $stock_status['state'];
	$price_html   = $is_variable ? '' : jluxe_reference_price_html( $product->get_price_html() );
	if ( $in_stock && $purchasable ) {
		$mode   = $is_variable && ! $has_default_variation ? 'scroll' : 'add';
		$label  = 'scroll' === $mode ? 'انتخاب گزینه‌ها' : 'افزودن به سبد';
		$aria   = 'scroll' === $mode ? 'رفتن به انتخاب تنوع' : 'افزودن به سبد خرید';
		$button = '<button type="button" class="jluxe-btn jluxe-btn-primary" data-jluxe-sticky-add data-jluxe-sticky-mode="' . esc_attr( $mode ) . '" aria-label="' . esc_attr( $aria ) . '">' . jluxe_icon_markup( 'cart', 'size-5' ) . '<span data-jluxe-sticky-label>' . esc_html( $label ) . '</span></button>';
	} else {
		$button = '<span class="jluxe-sticky-cta-out">ناموجود</span>';
	}
	?>
	<div id="jluxe-sticky-cta" class="jluxe-sticky-cta" data-jluxe-sticky-cta<?php if ( $always_visible ) : ?> data-jluxe-sticky-cta-always<?php endif; ?>>
		<div class="jluxe-sticky-cta-info">
			<?php if ( $is_variable ) : ?>
				<span class="jluxe-sticky-cta-price" data-jluxe-sticky-variation-price data-jluxe-price-placeholder="" aria-live="polite" aria-atomic="true"></span>
			<?php elseif ( '' !== (string) $price_html ) : ?>
				<span class="jluxe-sticky-cta-price"><?php echo jluxe_price_kses( $price_html ); ?></span>
			<?php endif; ?>
			<?php if ( 'in-stock' !== $stock_status['state'] ) : ?>
				<span
					class="jluxe-sticky-cta-stock"
					data-jluxe-sticky-stock-status
					data-jluxe-stock-state="<?php echo esc_attr( $stock_status['state'] ); ?>"
					data-jluxe-stock-placeholder="<?php echo esc_attr( $stock_status['label'] ); ?>"
					data-jluxe-stock-placeholder-state="<?php echo esc_attr( $stock_status['state'] ); ?>"
					aria-live="polite"
					aria-atomic="true"
				><?php echo esc_html( $stock_status['label'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $in_stock && $purchasable ) : ?>
			<?php jluxe_render_sticky_quantity_control(); ?>
		<?php endif; ?>
		<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزای داخلی escape شده‌اند. ?>
	</div>
	<button
		type="button"
		class="jluxe-sticky-cta-mode-toggle"
		data-jluxe-sticky-cta-mode-toggle
		aria-controls="jluxe-sticky-cta"
		aria-label="نمایش منوی موبایل"
		aria-pressed="true"
		hidden
	>
		<span class="jluxe-dock-choice jluxe-dock-choice--menu" aria-hidden="true">منو</span><span class="jluxe-dock-exchange" aria-hidden="true">⇄</span><span class="jluxe-dock-choice jluxe-dock-choice--purchase" aria-hidden="true">خرید</span>
		<span data-jluxe-sticky-cta-mode-label>منو</span>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v16M7 9l5-5 5 5M7 15l5 5 5-5"/></svg>
	</button>
	<?php
}

/** نسخهٔ برگشت‌دهندهٔ SVG آیکون (برای چاپ داخل رشته‌ها) — از inc/icons.php. */
function jluxe_icon_markup( string $name, string $class = 'size-5' ): string {
	ob_start();
	jluxe_icon( $name, $class );
	return (string) ob_get_clean();
}

/**
 * خطِ سبزِ «سود شما از این خرید» برای محصولِ متغیر — طبقِ درخواستِ صریحِ
 * کاربر («روی محصولِ متغیر نشون داده نمی‌شه»). قبلاً این خط عمداً فقط
 * برای محصولِ ساده رندر می‌شد (globals.css/price.php کامنتِ قدیمی: بازسازیِ
 * عددِ دقیقِ صرفه‌جویی با جداکننده‌ی هزارگان درست در JS بدون منطقِ فرمتِ
 * سمت سرور مطمئن نیست) — اون نگرانی واقعیه، پس راه‌حلش بازسازی در JS
 * نیست؛ خودِ سرور (همین‌جا) با wc_price() دقیقاً همون رشته‌ی فرمت‌شده‌ی
 * سایت (تومان، جداکننده‌ی هزارگان، رقمِ فارسی — از طریقِ فیلترهای
 * wc_price بالاتر در همین فایل) رو برای هر تنوع از قبل می‌سازه و به
 * دیتای واقعیِ ووکامرس اضافه می‌کنه؛ assets/js/woocommerce.js
 * (jluxeBindPriceBox → found_variation) فقط همین HTML آماده رو
 * می‌ذاره، دقیقاً همون الگویی که برای خودِ price_html هم استفاده می‌شه.
 */
function jluxe_variation_saving_html( array $data, $product, $variation ): array {
	$regular = (float) $variation->get_regular_price();
	$sale    = (float) $variation->get_price();
	if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
		$data['jluxe_saving_html'] = wp_kses_post( wc_price( $regular - $sale ) ) . ' سود شما از این خرید';
	}
	/* R55: قیمتِ تنویع که با found_variation جابه‌جا می‌شود هم همان فرمتِ مرجع (عدد + گلیف) — JS آن را دست‌نخورده innerHTML می‌کند. */
	if ( ! empty( $data['price_html'] ) ) {
		$data['price_html'] = jluxe_reference_price_html( (string) $data['price_html'] );
	}
	return $data;
}

/** Attach the exact managed stock quantity to WooCommerce's variation event data. */
function jluxe_variation_stock_quantity_payload( array $data, $product, $variation ): array {
	if ( $variation instanceof WC_Product && method_exists( $variation, 'managing_stock' ) && $variation->managing_stock() && method_exists( $variation, 'get_stock_quantity' ) ) {
		$quantity = $variation->get_stock_quantity();
		if ( is_numeric( $quantity ) ) {
			$data['jluxe_stock_quantity'] = (int) $quantity;
		}
	}
	return $data;
}

/**
 * نگاشتِ نام‌های رایج فارسیِ رنگ به کدِ HEX — برای نمایش سواچ رنگی واقعی
 * (دایره‌ی رنگی) به‌جای پیل متنی، دقیقاً مثل مرجع (Boom). فقط برای
 * ویژگی‌هایی که واقعاً «رنگ» هستن استفاده می‌شه؛ نامِ کاملِ شناخته‌شده
 * یا یک عبارتِ رنگِ کامل داخلِ برچسب/نامک پذیرفته می‌شود. اگر هیچ عبارتِ
 * موجود در نقشه پیدا نشود، به پیل متنی برمی‌گردد و رنگی حدس زده نمی‌شود.
 */
function jluxe_persian_color_to_hex( string $name ): ?string {
	static $map = null;
	static $en_map = null;
	if ( null === $map ) {
		$map = array(
			'سفید' => '#FFFFFF', 'سفید صدفی' => '#F5F0E8', 'شیری' => '#F6F1E7', 'استخوانی' => '#EFEAE0',
			'مشکی' => '#18181B', 'سیاه' => '#18181B', 'مشکی مات' => '#1C1C1E',
			'قرمز' => '#ED1A45', 'قرمز تیره' => '#B91C1C', 'قرمز روشن' => '#F87171', 'زرشکی' => '#9F1239', 'سرخابی' => '#DB2777', 'گلبهی' => '#FDA4AF', 'هلویی' => '#FDBA96',
			'آبی' => '#1D4ED8', 'ابی' => '#1D4ED8', 'آبی تیره' => '#1E3A8A', 'آبی سیر' => '#1E3A8A', 'آبی روشن' => '#93C5FD', 'آبی آسمانی' => '#7DD3FC', 'سرمه ای' => '#1E293B',
			'سبز' => '#16A34A', 'سبز تیره' => '#166534', 'سبز روشن' => '#86EFAC', 'زیتونی' => '#6B8E23', 'پسته ای' => '#93C572', 'فیروزه ای' => '#2DD4BF', 'نیلی' => '#312E81', 'لاجوردی' => '#2563EB',
			'زرد' => '#EAB308', 'زرد روشن' => '#FDE047', 'زرد تیره' => '#CA8A04', 'خردلی' => '#A16207', 'طلایی' => '#D4AF37', 'طلایی روشن' => '#E9C46A', 'طلایی تیره' => '#B8860B',
			'نارنجی' => '#F97316', 'نارنجی روشن' => '#FDBA74', 'نارنجی سوخته' => '#C2410C', 'مسی' => '#B87333',
			'صورتی' => '#EC4899', 'صورتی روشن' => '#F9A8D4', 'صورتی تیره' => '#BE185D',
			'بنفش' => '#7C3AED', 'بنفش تیره' => '#5B21B6', 'بنفش روشن' => '#C4B5FD', 'یاسی' => '#A78BFA',
			'قهوه ای' => '#78350F', 'قهوه ای روشن' => '#A16207', 'قهوه ای تیره' => '#451A03', 'شکلاتی' => '#5C3317', 'کالباسی' => '#8A3324',
			'طوسی' => '#9CA3AF', 'طوسی تیره' => '#4B5563', 'طوسی روشن' => '#D1D5DB', 'خاکستری' => '#9CA3AF', 'خاکستری تیره' => '#4B5563', 'خاکستری روشن' => '#D1D5DB', 'دودی' => '#6B7280',
			'نقره ای' => '#C0C0C0', 'نقره ای تیره' => '#94A3B8',
			'کرم' => '#E9DFC7', 'کرم روشن' => '#F5EEDC', 'کرم تیره' => '#D9C9A8', 'وانیلی' => '#F3E5AB', 'بژ' => '#E9DFC7', 'بژ روشن' => '#F5EEDC', 'بژ تیره' => '#CBB896', 'پنبه ای' => '#F2EFE6',
		);
		$en_map = array(
			'white' => '#FFFFFF', 'ivory' => '#F6F1E7', 'vanilla' => '#F3E5AB', 'cream' => '#E9DFC7', 'beige' => '#E9DFC7', 'black' => '#18181B', 'charcoal' => '#374151',
			'red' => '#ED1A45', 'dark red' => '#B91C1C', 'light red' => '#F87171', 'maroon' => '#9F1239', 'burgundy' => '#9F1239', 'wine' => '#9F1239', 'hot pink' => '#DB2777', 'pink' => '#EC4899', 'light pink' => '#F9A8D4', 'dark pink' => '#BE185D', 'rose' => '#FDA4AF', 'peach' => '#FDBA96', 'coral' => '#F97355',
			'blue' => '#1D4ED8', 'dark blue' => '#1E3A8A', 'light blue' => '#93C5FD', 'sky blue' => '#7DD3FC', 'navy' => '#1E293B',
			'green' => '#16A34A', 'dark green' => '#166534', 'light green' => '#86EFAC', 'olive' => '#6B8E23', 'mint' => '#93C572', 'turquoise' => '#2DD4BF', 'teal' => '#0D9488', 'indigo' => '#312E81', 'violet' => '#A78BFA',
			'yellow' => '#EAB308', 'light yellow' => '#FDE047', 'dark yellow' => '#CA8A04', 'mustard' => '#A16207', 'khaki' => '#A16207', 'gold' => '#D4AF37', 'golden' => '#D4AF37',
			'orange' => '#F97316', 'light orange' => '#FDBA74', 'burnt orange' => '#C2410C', 'copper' => '#B87333',
			'purple' => '#7C3AED', 'dark purple' => '#5B21B6', 'light purple' => '#C4B5FD', 'lavender' => '#C4B5FD',
			'brown' => '#78350F', 'light brown' => '#A16207', 'dark brown' => '#451A03', 'chocolate' => '#5C3317', 'tan' => '#D2B48C',
			'gray' => '#9CA3AF', 'grey' => '#9CA3AF', 'dark gray' => '#4B5563', 'dark grey' => '#4B5563', 'light gray' => '#D1D5DB', 'light grey' => '#D1D5DB', 'silver' => '#C0C0C0', 'smoke' => '#6B7280',
		);
	}
	/*
	 * R65/R99/R113: یکسان‌سازیِ نامِ فارسی و انگلیسی، چه از term name
	 * بیاید چه از نامکِ percent-encoded. جداکننده‌هایِ نامک و نشانه‌گذاری
	 * به فاصله تبدیل می‌شوند؛ این‌طوری «قهوه-ای» و نیز برچسبِ توصیفیِ
	 * «کرپ (صورتی تیره)» به همان رنگِ شناخته‌شده می‌رسند.
	 */
	$normalize = static function ( string $candidate ): string {
		$candidate = rawurldecode( $candidate );
		$candidate = str_replace( array( 'ي', 'ى', 'ك' ), array( 'ی', 'ی', 'ک' ), $candidate );
		$candidate = (string) preg_replace( '/[\x{200B}-\x{200F}\x{FEFF}\x{00A0}]/u', ' ', $candidate );
		$candidate = (string) preg_replace( '/[-_]+/u', ' ', $candidate );
		$candidate = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $candidate );
		return strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $candidate ) ) );
	};

	$color_lookup = array();
	foreach ( array_merge( $map, $en_map ) as $color_name => $hex ) {
		$color_lookup[ $normalize( (string) $color_name ) ] = (string) $hex;
	}

	$key = $normalize( $name );
	if ( isset( $color_lookup[ $key ] ) ) {
		return $color_lookup[ $key ];
	}

	/*
	 * نامِ ویژگی ممکن است توضیحِ جنس را کنارِ رنگ داشته باشد یا نامک با
	 * پیشوندِ جنس ساخته شده باشد («کرپ (صورتی تیره)» / «crepe-dark-pink»).
	 * فقط عبارت‌هایِ کاملِ موجود در نقشه را می‌پذیریم، بلندترین رنگ را
	 * ترجیح می‌دهیم و اگر چند رنگِ هم‌طول/مبهم پیدا شد، حدس نمی‌زنیم.
	 */
	$tokens = '' === $key ? array() : explode( ' ', $key );
	for ( $word_count = count( $tokens ); $word_count > 0; $word_count-- ) {
		$matches = array();
		for ( $start = 0; $start + $word_count <= count( $tokens ); $start++ ) {
			$phrase = implode( ' ', array_slice( $tokens, $start, $word_count ) );
			if ( isset( $color_lookup[ $phrase ] ) ) {
				$matches[ $color_lookup[ $phrase ] ] = true;
			}
		}
		if ( $matches ) {
			return 1 === count( $matches ) ? (string) array_key_first( $matches ) : null;
		}
	}

	return null;
}

/**
 * آیا این ویژگی از نوع «رنگ»ه؟ نامِ نمایشیِ ویژگی اولویت دارد؛ نامک‌هایِ
 * color/colour/rang هم پوشش داده می‌شوند تا taxonomy فارسیِ encode‌شده
 * حتی وقتی label در cache/فیلترها موجود نیست، درست تشخیص داده شود.
 */
function jluxe_is_color_attribute( string $attribute_name ): bool {
	$names = array_values( array_unique( array( $attribute_name, rawurldecode( $attribute_name ) ) ) );
	foreach ( $names as $name ) {
		$label = wc_attribute_label( $name );
		if ( false !== jluxe_strpos( $label, 'رنگ' ) || false !== stripos( $label, 'color' ) || false !== stripos( $label, 'colour' ) ) {
			return true;
		}
		$slug = preg_replace( '/^pa_/i', '', $name );
		if ( is_string( $slug ) && preg_match( '/(?:^|[_\-\s])(?:colou?rs?|rangi?)(?:$|[_\-\s])/i', $slug ) ) {
			return true;
		}
	}
	return false;
}

/** Normalize WooCommerce default/variation attribute keys to their unprefixed form. */
function jluxe_normalize_variable_attributes( $attributes, bool $keep_empty = false ): array {
	if ( ! is_array( $attributes ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $attributes as $name => $value ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			continue;
		}
		$name  = jluxe_normalize_variable_attribute_name( (string) $name );
		$value = null === $value ? '' : (string) $value;
		if ( '' === $name || ( '' === $value && ! $keep_empty ) ) {
			continue;
		}
		$normalized[ $name ] = $value;
	}

	return $normalized;
}

/** Attribute values may be URL-encoded by WooCommerce/third-party variation tools. */
function jluxe_variable_attribute_values_match( string $left, string $right ): bool {
	return $left === $right || rawurldecode( $left ) === rawurldecode( $right );
}

/** Return normalized attributes from either WooCommerce variation objects or its legacy array payload. */
function jluxe_variable_variation_attributes( $variation ): array {
	if ( is_object( $variation ) && method_exists( $variation, 'get_variation_attributes' ) ) {
		$attributes = $variation->get_variation_attributes();
	} elseif ( is_array( $variation ) ) {
		$attributes = $variation['attributes'] ?? array();
	} else {
		$attributes = array();
	}

	return jluxe_normalize_variable_attributes( $attributes, true );
}

/** Normalize a WooCommerce boolean or a common serialized yes/no value. */
function jluxe_variable_flag_is_true( $value ): bool {
	if ( is_bool( $value ) ) {
		return $value;
	}
	return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
}

/** Only an enabled, purchasable, in-stock variation can become a default offer. */
function jluxe_variable_variation_is_available( $variation ): bool {
	if ( is_array( $variation ) ) {
		if ( ! jluxe_variable_flag_is_true( $variation['is_in_stock'] ?? false ) ) {
			return false;
		}
		if ( array_key_exists( 'is_purchasable', $variation ) && ! jluxe_variable_flag_is_true( $variation['is_purchasable'] ) ) {
			return false;
		}
		return true;
	}

	if ( ! is_object( $variation ) ) {
		return false;
	}
	if ( method_exists( $variation, 'is_type' ) && ! $variation->is_type( 'variation' ) ) {
		return false;
	}
	return method_exists( $variation, 'is_in_stock' )
		&& $variation->is_in_stock()
		&& method_exists( $variation, 'is_purchasable' )
		&& $variation->is_purchasable();
}

/** Yield visible variation objects in WooCommerce order, stopping work as soon as a match is found. */
function jluxe_variable_default_variation_candidates( WC_Product $product ): iterable {
	$children = method_exists( $product, 'get_children' ) ? (array) $product->get_children() : array();
	if ( ! empty( $children ) && function_exists( 'wc_get_product' ) ) {
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $children );
		}
		$hide_out_of_stock = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items', 'no' );
		foreach ( $children as $variation_id ) {
			$variation = wc_get_product( absint( $variation_id ) );
			if ( ! $variation instanceof WC_Product || ! $variation->exists() || ! $variation->is_type( 'variation' ) ) {
				continue;
			}
			if ( $hide_out_of_stock && ! $variation->is_in_stock() ) {
				continue;
			}
			$hide_invisible = apply_filters( 'woocommerce_hide_invisible_variations', true, $product->get_id(), $variation );
			if ( $hide_invisible && method_exists( $variation, 'variation_is_visible' ) && ! $variation->variation_is_visible() ) {
				continue;
			}
			yield $variation;
		}
		return;
	}

	// Lightweight test doubles and compatible extensions may expose only the public getter.
	$variations = $product->get_available_variations( 'objects' );
	if ( is_array( $variations ) ) {
		foreach ( $variations as $variation ) {
			yield $variation;
		}
	}
}

/**
 * Resolve one complete default combination. Keep an explicit default when it
 * still identifies a purchasable in-stock variation; otherwise use the first
 * available variation in WooCommerce's variation/menu order. A wildcard value
 * is filled with the requested option or the first option used by the product.
 */
function jluxe_pick_variable_default_attributes( WC_Product $product, $stored_defaults = array() ): array {
	if ( ! $product->is_type( 'variable' ) || ! method_exists( $product, 'get_available_variations' ) ) {
		return array();
	}

	$requested = jluxe_normalize_variable_attributes( $stored_defaults );
	$options   = array();
	if ( method_exists( $product, 'get_variation_attributes' ) ) {
		foreach ( (array) $product->get_variation_attributes() as $name => $values ) {
			$name = jluxe_normalize_variable_attribute_name( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			$values = is_array( $values ) ? $values : array( $values );
			foreach ( $values as $value ) {
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$options[ $name ][] = (string) $value;
				}
			}
			$options[ $name ] = array_values( array_unique( $options[ $name ] ?? array() ) );
		}
	}

	// Do not generate the expensive price/image HTML for the whole variation set.
	$first_available = array();
	foreach ( jluxe_variable_default_variation_candidates( $product ) as $variation ) {
		if ( ! jluxe_variable_variation_is_available( $variation ) ) {
			continue;
		}

		$variation_attributes = jluxe_variable_variation_attributes( $variation );
		if ( empty( $variation_attributes ) ) {
			continue;
		}

		$matches_requested = true;
		foreach ( $requested as $name => $value ) {
			if ( ! array_key_exists( $name, $variation_attributes ) ) {
				$matches_requested = false;
				continue;
			}
			$variation_value = $variation_attributes[ $name ];
			if ( '' !== $variation_value && ! jluxe_variable_attribute_values_match( $variation_value, $value ) ) {
				$matches_requested = false;
			}
			if ( '' === $variation_value && ! empty( $options[ $name ] ) ) {
				$option_exists = false;
				foreach ( $options[ $name ] as $option ) {
					if ( jluxe_variable_attribute_values_match( $option, $value ) ) {
						$option_exists = true;
						break;
					}
				}
				if ( ! $option_exists ) {
					$matches_requested = false;
				}
			}
		}

		$attribute_names = array_keys( $options );
		if ( empty( $attribute_names ) ) {
			$attribute_names = array_keys( $variation_attributes );
		}
		$resolved = array();
		foreach ( $attribute_names as $name ) {
			$value = $variation_attributes[ $name ] ?? '';
			if ( '' === $value ) {
				$wanted = $requested[ $name ] ?? '';
				foreach ( $options[ $name ] ?? array() as $option ) {
					if ( '' !== $wanted && jluxe_variable_attribute_values_match( $option, $wanted ) ) {
						$value = $option;
						break;
					}
				}
				if ( '' === $value && '' !== $wanted && empty( $options[ $name ] ) ) {
					$value = $wanted;
				}
				if ( '' === $value && ! empty( $options[ $name ] ) ) {
					$value = (string) reset( $options[ $name ] );
				}
			}
			if ( '' === $value ) {
				$resolved = array();
				break;
			}
			$resolved[ $name ] = (string) $value;
		}

		if ( empty( $resolved ) ) {
			continue;
		}
		if ( empty( $first_available ) ) {
			$first_available = $resolved;
		}
		if ( $matches_requested ) {
			return $resolved;
		}
	}

	return $first_available;
}

/** Find the purchasable variation represented by an effective default attribute set. */
function jluxe_find_default_variable_variation( WC_Product $product, $defaults = null ) {
	if ( ! $product->is_type( 'variable' ) ) {
		return false;
	}
	if ( ! is_array( $defaults ) ) {
		$defaults = jluxe_pick_variable_default_attributes( $product, method_exists( $product, 'get_default_attributes' ) ? $product->get_default_attributes( 'edit' ) : array() );
	}
	$defaults = jluxe_normalize_variable_attributes( $defaults );
	if ( empty( $defaults ) ) {
		return false;
	}

	foreach ( jluxe_variable_default_variation_candidates( $product ) as $variation ) {
		if ( ! jluxe_variable_variation_is_available( $variation ) ) {
			continue;
		}
		$attributes = jluxe_variable_variation_attributes( $variation );
		$matches    = true;
		foreach ( $defaults as $name => $value ) {
			if ( ! array_key_exists( $name, $attributes ) ) {
				$matches = false;
				break;
			}
			$variation_value = $attributes[ $name ];
			if ( '' !== $variation_value && ! jluxe_variable_attribute_values_match( $variation_value, $value ) ) {
				$matches = false;
				break;
			}
		}
		if ( $matches ) {
			return $variation;
		}
	}

	return false;
}

/** Return the exact initial price for the chosen variation, never the variable product's price range. */
function jluxe_default_variable_variation_price_html( WC_Product $product, $defaults = null ): string {
	$variation = jluxe_find_default_variable_variation( $product, $defaults );
	if ( false === $variation ) {
		return '';
	}

	$price_html = '';
	if ( is_array( $variation ) ) {
		$price_html = (string) ( $variation['price_html'] ?? '' );
	} elseif ( method_exists( $product, 'get_available_variation' ) ) {
		$data = $product->get_available_variation( $variation );
		if ( is_array( $data ) ) {
			$price_html = (string) ( $data['price_html'] ?? '' );
		}
	}
	if ( '' === trim( $price_html ) && is_object( $variation ) && method_exists( $variation, 'get_price_html' ) ) {
		$price_html = (string) $variation->get_price_html();
	}
	if ( '' === trim( $price_html ) ) {
		return '';
	}

	return jluxe_price_kses( jluxe_reference_price_html( $price_html ) );
}

/** Resolve a taxonomy/encoded attribute key to readable text without changing the WooCommerce key. */
function jluxe_variable_attribute_display_label( string $attribute_name ): string {
	$label         = trim( (string) wc_attribute_label( $attribute_name ) );
	$decoded_label = rawurldecode( $label );
	$decoded_name  = rawurldecode( $attribute_name );

	/*
	 * WooCommerce normally supplies its configured attribute label. If it
	 * instead returns the taxonomy key (for example pa_%d8%b1...), decode it
	 * for display and remove only the internal pa_ prefix. Variation keys,
	 * option values, defaults and swatches continue using their canonical slugs.
	 */
	$is_attribute_key = $decoded_label !== $label ||
		$decoded_label === $decoded_name ||
		0 === stripos( $decoded_label, 'attribute_pa_' ) ||
		0 === stripos( $decoded_label, 'pa_' );
	if ( $is_attribute_key ) {
		$label = $decoded_label;
		$label = preg_replace( '/^attribute_/i', '', $label );
		if ( 0 === stripos( (string) $label, 'pa_' ) ) {
			$label = substr( (string) $label, 3 );
		}
		$label = preg_replace( '/[-_]+/u', ' ', (string) $label );
	} else {
		$label = $decoded_label;
	}

	return trim( (string) preg_replace( '/\s+/u', ' ', (string) $label ) );
}

/** Produce one concise choice label even when the saved attribute name already contains an instruction. */
function jluxe_variable_attribute_prompt( string $attribute_name ): string {
	$label = trim( jluxe_variable_attribute_display_label( $attribute_name ) );
	$label = preg_replace( '/\s*[:：]\s*$/u', '', $label );
	$label = preg_replace( '/\s*(?:(?:مورد\s+نظر\s+)?را\s+)?انتخاب\s+کنید\s*[.!؟?،؛:：]*$/u', '', (string) $label );
	$label = trim( (string) $label, " \t\n\r\0\x0B:：" );
	if ( '' === $label ) {
		$label = 'تنوع';
	}
	return 'انتخاب ' . $label . ':';
}

/** Dynamic WooCommerce default: fixes existing products immediately, without waiting for an admin save. */
function jluxe_auto_variable_default_attributes( $defaults, $product ): array {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return is_array( $defaults ) ? $defaults : array();
	}
	return jluxe_pick_variable_default_attributes( $product, $defaults );
}

/** Used by the custom variation swatches as well as the standard WooCommerce default values. */
function jluxe_default_variation_pick( WC_Product $product ): array {
	$stored_defaults = method_exists( $product, 'get_default_attributes' ) ? $product->get_default_attributes( 'edit' ) : array();
	return jluxe_pick_variable_default_attributes( $product, $stored_defaults );
}

/**
 * Read only the stock level that belongs to the product currently shown.
 * Variable parents can hold an aggregate/cache quantity that is not the
 * quantity of the selected child, so resolve the effective default variation
 * first and ask that variation (which naturally inherits parent-managed stock
 * in WooCommerce) for its stock quantity. An unselected/untracked variation
 * has no exact count to show.
 */
function jluxe_product_stock_quantity_for_display( WC_Product $product, $default_attributes = null ) {
	$stock_product = $product;
	if ( $product->is_type( 'variable' ) ) {
		if ( ! is_array( $default_attributes ) ) {
			$default_attributes = jluxe_default_variation_pick( $product );
		}
		if ( empty( $default_attributes ) ) {
			return null;
		}

		$stock_product = jluxe_find_default_variable_variation( $product, $default_attributes );
		if ( is_array( $stock_product ) ) {
			$variation_id = absint( $stock_product['variation_id'] ?? 0 );
			$stock_product = $variation_id && function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : false;
		}
		if ( ! ( $stock_product instanceof WC_Product ) ) {
			return null;
		}
	}

	if ( ! method_exists( $stock_product, 'managing_stock' ) || ! $stock_product->managing_stock() || ! method_exists( $stock_product, 'get_stock_quantity' ) ) {
		return null;
	}

	$quantity = $stock_product->get_stock_quantity();
	return is_numeric( $quantity ) ? (int) $quantity : null;
}

/** Do not expose a variable parent as in stock through WooCommerce's view API when no child can be bought. */
function jluxe_variable_product_stock_status( $stock_status, $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $stock_status;
	}
	return empty( jluxe_default_variation_pick( $product ) ) ? 'outofstock' : $stock_status;
}

/** Compare defaults without treating attribute ordering as a data change. */
function jluxe_variable_default_attributes_equal( $left, $right ): bool {
	$left  = jluxe_normalize_variable_attributes( $left );
	$right = jluxe_normalize_variable_attributes( $right );
	ksort( $left );
	ksort( $right );
	return $left === $right;
}

/** Persist the computed defaults so Torob/feed integrations reading saved product data stay in sync. */
function jluxe_sync_variable_default_attributes( WC_Product $product ): bool {
	if ( ! $product->is_type( 'variable' ) || ! method_exists( $product, 'set_default_attributes' ) || ! method_exists( $product, 'save' ) ) {
		return false;
	}
	$stored = method_exists( $product, 'get_default_attributes' ) ? $product->get_default_attributes( 'edit' ) : array();
	$chosen = jluxe_pick_variable_default_attributes( $product, $stored );
	if ( jluxe_variable_default_attributes_equal( $stored, $chosen ) ) {
		return false;
	}
	$product->set_default_attributes( $chosen );
	$product->save();
	return true;
}

/** Recompute defaults after product/variation saves and WooCommerce stock changes. */
function jluxe_sync_variable_defaults_from_change( ...$args ): void {
	static $syncing = array();
	$source = null;
	foreach ( $args as $argument ) {
		if ( $argument instanceof WC_Product ) {
			$source = $argument;
		}
	}
	if ( ! $source ) {
		foreach ( $args as $argument ) {
			if ( is_numeric( $argument ) && absint( $argument ) > 0 && function_exists( 'wc_get_product' ) ) {
				$source = wc_get_product( absint( $argument ) );
				if ( $source instanceof WC_Product ) {
					break;
				}
			}
		}
	}
	if ( ! $source instanceof WC_Product ) {
		return;
	}

	$parent_id = $source->is_type( 'variation' ) ? (int) $source->get_parent_id() : (int) $source->get_id();
	if ( $parent_id < 1 || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$parent = $source->is_type( 'variable' ) ? $source : wc_get_product( $parent_id );
	if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || isset( $syncing[ $parent_id ] ) ) {
		return;
	}

	$syncing[ $parent_id ] = true;
	try {
		jluxe_sync_variable_default_attributes( $parent );
	} finally {
		unset( $syncing[ $parent_id ] );
	}
}

/** Small admin guide beside WooCommerce's default-variation controls (also clarifies the Torob setup). */
function jluxe_variable_variations_feed_guidance(): void {
	?>
	<div class="notice notice-info inline" style="margin:10px 0 14px">
		<p><strong>ترب و فید محصولات:</strong> اگر پیش‌فرض خالی یا ناموجود باشد، زرین اولین تنوعِ فعال، قابل‌خرید و موجود را انتخاب می‌کند؛ اگر همهٔ تنوع‌ها ناموجود/غیرقابل‌خرید باشند، پیش‌فرض خالی می‌ماند. برای نمایش دقیق‌تر، برای هر تنوع SKU یکتا، قیمت و موجودی واقعی، ویژگی‌های کامل و در صورت تفاوت تصویر همان تنوع را ثبت کنید. اگر افزونهٔ ترب شما ارسال جداگانهٔ تنوع‌ها را پشتیبانی می‌کند، آن گزینه را هم فعال کنید.
		</p>
	</div>
	<?php
}

function jluxe_render_variation_swatches( WC_Product $product, array $variation_attributes ): void {
	/*
	 * باگِ واقعیِ گزارش‌شده («انتخابِ سایزِ X، ولی درخواستِ واقعی برایِ سایزِ
	 * دیگه‌ای می‌رفت»): این سواچ‌ها از رویِ کل فهرستِ ترم‌هایِ ویژگی رندر
	 * می‌شدن (get_variation_attributes که مقادیرِ خامِ attribute رو
	 * برمی‌گردونه)، نه فقط اونایی که واقعاً یک Variation برایِ این محصول
	 * دارن. مثلاً یه محصول با ویژگیِ «سایز»ِ ۴۸ تا ۷۶ (۲۹ مقدار)، ولی فقط
	 * ۱۵ تنوعِ واقعی (۴۸ تا ۶۲) ساخته‌شده — ۱۴تایِ باقی سواچِ کاملاً «واقعی»
	 * نشون داده می‌شدن (۶۳ تا ۷۶) بدونِ اینکه هیچ Variationـی پشتشون باشه.
	 * با تستِ زنده تأیید شد: کلیک‌کردنِ یکی از این سواچ‌هایِ بدونِ Variation
	 * باعث می‌شد اسکریپتِ خودِ ووکامرس (wc-add-to-cart-variation.js) هیچ
	 * found_variationی پیدا نکنه و select زیرین رو عوض‌شده نگه داره ولی
	 * hidden inputِ variation_id همون مقدارِ آخرین انتخابِ معتبرِ قبلی رو
	 * حفظ می‌کرد — یعنی کاربر فکر می‌کرد سایزِ جدیدی انتخاب کرده، ولی
	 * درخواستِ افزودن‌به‌سبد برایِ همون سایزِ قبلی می‌رفت. راه‌حل: سواچ‌ها
	 * رو از اول فقط از رویِ مقادیریِ که واقعاً توی get_available_variations()
	 * (یعنی Variationِ واقعاً موجود) دیده می‌شن می‌سازیم — دقیقاً همون منبعِ
	 * دیتایی که خودِ فرم (data-product_variations) و found_variation ازش
	 * استفاده می‌کنن، پس دیگه هیچ‌وقت از هم جدا نمی‌افتن.
	 */
	$jluxe_valid_options = array();
	$jluxe_stocky_options = array();
	$jluxe_has_wildcard   = array();
	/* R138: انتخابِ پیش‌فرضِ مؤثر (پیش‌فرضِ ذخیره‌شدهٔ معتبر یا اولین تنوعِ موجود). */
	$jluxe_defaults = jluxe_default_variation_pick( $product );
	$_jluxe_same_pick = static function ( $option, $pick ): bool {
		$option = (string) $option;
		$pick   = (string) $pick;
		return '' !== $pick && ( $option === $pick || rawurldecode( $option ) === rawurldecode( $pick ) );
	};
	foreach ( $product->get_available_variations() as $jluxe_variation ) {
		/* R65: تنوعِ ناموجود نباید قابلِ انتخاب باشد — جدا از «معتبر»،
		مجموعهٔ «موجود» (is_in_stock) هم ساخته می‌شود؛ گزینه‌ای که فقط در
		تنوعِ ناموجود حاضر است، disabled رندر می‌شود (نه حذف، تا کاربر
		بداند چنین گزینه‌ای وجود دارد اما فعلاً خریدنی نیست). */
		$jluxe_in_stock = jluxe_variable_variation_is_available( $jluxe_variation );
		foreach ( $jluxe_variation['attributes'] as $jluxe_attr_key => $jluxe_attr_value ) {
			$jluxe_attr_name = jluxe_normalize_variable_attribute_name( (string) $jluxe_attr_key );
			if ( '' === $jluxe_attr_value ) {
				// مقدارِ خالی یعنی «هر مقداری» — این Variation با هر گزینه‌ای
				// از این ویژگی مچ می‌شه، پس فیلترکردن برایِ این ویژگی معنی
				// نداره (همه‌ی گزینه‌ها واقعاً معتبرن).
				$jluxe_has_wildcard[ $jluxe_attr_name ] = true;
				continue;
			}
			$jluxe_valid_options[ $jluxe_attr_name ][ $jluxe_attr_value ] = true;
			if ( $jluxe_in_stock ) {
				$jluxe_stocky_options[ $jluxe_attr_name ][ $jluxe_attr_value ] = true;
			}
		}
	}

	foreach ( $variation_attributes as $attr_name => $attr_options ) {
		$attr_name          = jluxe_normalize_variable_attribute_name( (string) $attr_name );
		$attr_label         = wc_attribute_label( rawurldecode( $attr_name ) );
		$select_id          = sanitize_title( $attr_name );
		$is_color_attr      = jluxe_is_color_attribute( $attr_name );
		$is_taxonomy_attr   = 0 === strpos( rawurldecode( $attr_name ), 'pa_' );
		$jluxe_default_pick = isset( $jluxe_defaults[ $attr_name ] ) ? (string) $jluxe_defaults[ $attr_name ] : '';

		if ( empty( $jluxe_has_wildcard[ $attr_name ] ) && ! empty( $jluxe_valid_options[ $attr_name ] ) ) {
			$attr_options = array_values( array_filter( $attr_options, fn( $o ) => isset( $jluxe_valid_options[ $attr_name ][ $o ] ) ) );
		}
		$jluxe_default_label = '';
		foreach ( $attr_options as $jluxe_candidate_option ) {
			if ( $_jluxe_same_pick( $jluxe_candidate_option, $jluxe_default_pick ) ) {
				$jluxe_default_label = jluxe_attribute_option_label( (string) $jluxe_candidate_option, $attr_name, $product );
				break;
			}
		}
		?>
		<div class="mt-4" data-jluxe-variation-group="<?php echo esc_attr( $select_id ); ?>">
			<p class="text-[12.5px] font-medium text-foreground" data-jluxe-variation-label>
				<?php if ( $is_color_attr ) : ?>انتخاب <?php endif; ?><?php echo esc_html( $attr_label ); ?>:
				<span data-jluxe-variation-selected aria-live="polite" aria-atomic="true"><?php echo esc_html( $jluxe_default_label ); ?></span>
			</p>
			<div class="mt-1.5 flex flex-wrap items-center gap-2" data-jluxe-variation-swatches>
				<?php foreach ( $attr_options as $option ) :
					$option_label = jluxe_attribute_option_label( (string) $option, $attr_name, $product );
					$jluxe_swatch = jluxe_resolve_variation_swatch( $attr_name, (string) $option, $option_label, $is_color_attr );
					/* R65/R74: وضعیتِ ناموجودیِ واقعیِ هر گزینه از همان منبعِ «موجود» —
					این دو متغیر قبلاً هرگز تعریف نشده بودند (نالِ بی‌صدا → سواچِ
					ناموجودِ رندرشدهٔ سرور هرگز disable نمی‌شد و فقط JS جبران
					می‌کرد؛ در فرم‌های بالای آستانه که سینک early-return می‌کند،
					سواچ کاملاً آزاد دیده می‌شد). */
					$jluxe_oos = ( empty( $jluxe_stocky_options[ $attr_name ]['*'] ) && ! empty( $jluxe_valid_options[ $attr_name ] ) && empty( $jluxe_stocky_options[ $attr_name ][ $option ] ) );
					$jluxe_oos_attrs = $jluxe_oos ? ' disabled="disabled" aria-disabled="true" title="' . esc_attr( (string) $option_label . ' (ناموجود)' ) . '"' : '';
					?>
					<?php if ( $jluxe_swatch && 'color' === $jluxe_swatch['type'] ) : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="relative grid size-9 shrink-0 place-items-center rounded-full outline-none transition-transform<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<span class="size-7 rounded-full border border-black/10 shadow-inner" style="background:<?php echo esc_attr( $jluxe_swatch['value'] ); ?>"></span>
							<span data-jluxe-variation-ring class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-primary ring-offset-2 ring-offset-surface opacity-0"></span>
						</button>
					<?php elseif ( $jluxe_swatch && 'image' === $jluxe_swatch['type'] ) : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="relative grid size-9 shrink-0 place-items-center rounded-full outline-none transition-transform<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<span class="size-7 overflow-hidden rounded-full border border-black/10 shadow-inner">
								<img src="<?php echo esc_url( $jluxe_swatch['value'] ); ?>" alt="" class="size-full object-cover" />
							</span>
							<span data-jluxe-variation-ring class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-primary ring-offset-2 ring-offset-surface opacity-0"></span>
						</button>
					<?php else : ?>
						<button
							type="button"
							data-jluxe-variation-value="<?php echo esc_attr( $option ); ?>"
							title="<?php echo esc_attr( $option_label ); ?>"
							aria-label="<?php echo esc_attr( $option_label ); ?>"
							class="rounded-full border border-border px-3.5 py-1.5 text-[12.5px] font-medium text-text-secondary transition-colors hover:border-primary/50 data-[active]:border-primary data-[active]:bg-primary/5 data-[active]:text-primary<?php echo $jluxe_oos ? ' jluxe-swatch-disabled' : '' ; ?>"<?php echo $jluxe_oos_attrs; ?><?php echo $_jluxe_same_pick( $option, $jluxe_default_pick ) ? ' data-active=""' : ''; ?>
						>
							<?php echo esc_html( $option_label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<div class="variations sr-only">
				<?php
				wc_dropdown_variation_attribute_options(
					array(
						'options'   => $attr_options,
						'attribute' => $attr_name,
						'product'   => $product,
						'selected'  => $jluxe_default_pick,
					)
				);
				?>
			</div>
		</div>
	<?php }
}

/**
 * سواچِ رنگ/تصویر در دکمه‌هایِ variation چیدمانِ classic.
 * مقدارِ data-value عمداً نامکِ ووکامرس می‌ماند؛ نامِ انسانی هم به‌صورت
 * متن، title و aria-label حفظ می‌شود تا هم رنگ دیده شود و هم نوعش مشخص باشد.
 * رشتهٔ خالی یعنی این ترم سواچِ معتبر ندارد و قالب باید قرصِ متنیِ معمول را نشان دهد.
 */
function jluxe_classic_variation_swatch_html( string $value, string $attribute_name, string $label, bool $is_color_attr, bool $disabled = false, bool $active = false ): string {
	if ( ! function_exists( 'jluxe_resolve_variation_swatch' ) ) {
		return '';
	}

	$swatch = jluxe_resolve_variation_swatch( $attribute_name, $value, $label, $is_color_attr );
	if ( ! is_array( $swatch ) || empty( $swatch['type'] ) || ! isset( $swatch['value'] ) ) {
		return '';
	}

	$visual = '';
	if ( 'color' === $swatch['type'] ) {
		$color = sanitize_hex_color( (string) $swatch['value'] );
		if ( $color ) {
			$visual = '<span class="cp3-swatch-color" style="background-color:' . esc_attr( $color ) . '" aria-hidden="true"></span>';
		}
	} elseif ( 'image' === $swatch['type'] ) {
		$image = esc_url( (string) $swatch['value'] );
		if ( $image ) {
			$visual = '<img class="cp3-swatch-image" src="' . $image . '" alt="" aria-hidden="true" />';
		}
	}

	if ( '' === $visual ) {
		return '';
	}

	$class = 'cp3-pill cp3-pill--swatch';
	if ( $disabled ) {
		$class .= ' is-disabled';
	} elseif ( $active ) {
		$class .= ' is-active';
	}
	$accessible_label = $label . ( $disabled ? ' (ناموجود)' : '' );
	$html              = '<button type="button" class="' . esc_attr( $class ) . '" data-value="' . esc_attr( $value ) . '" title="' . esc_attr( $accessible_label ) . '" aria-label="' . esc_attr( $accessible_label ) . '"';
	if ( $disabled ) {
		$html .= ' disabled="disabled" aria-disabled="true"';
	}
	$html .= '>' . $visual . '<span class="cp3-swatch-label">' . esc_html( $label ) . '</span></button>';

	return $html;
}

/**
 * سواچ‌های ایستای یک ویژگی رنگ؛ هم برای انتخابِ تنوع و هم برای ویژگی‌های
 * غیرتنوعیِ محصولِ ساده استفاده می‌شود. مقادیر می‌توانند term object، نامک
 * یا گزینه‌ی متنیِ ویژگی سفارشی باشند.
 *
 * @return array<int, array{type: string, value: string, label: string}>
 */
function jluxe_attribute_swatch_options( string $attribute_name, array $options, ?WC_Product $product = null ): array {
	if ( ! jluxe_is_color_attribute( $attribute_name ) || ! function_exists( 'jluxe_resolve_variation_swatch' ) ) {
		return array();
	}

	$resolved = array();
	foreach ( $options as $option ) {
		if ( is_object( $option ) ) {
			$value = isset( $option->slug ) ? (string) $option->slug : (string) ( $option->name ?? '' );
			$label = isset( $option->name ) ? (string) $option->name : '';
		} else {
			$value = (string) $option;
			$label = '';
		}
		if ( '' === $value ) {
			continue;
		}
		if ( '' === $label ) {
			$label = jluxe_attribute_option_label( $value, $attribute_name, $product );
		}

		$swatch = jluxe_resolve_variation_swatch( $attribute_name, $value, $label, true );
		if ( ! is_array( $swatch ) || empty( $swatch['type'] ) || ! isset( $swatch['value'] ) ) {
			continue;
		}
		$type  = (string) $swatch['type'];
		$value = (string) $swatch['value'];
		if ( 'color' === $type ) {
			$value = (string) sanitize_hex_color( $value );
			if ( '' === $value ) {
				continue;
			}
		} elseif ( 'image' === $type ) {
			$value = (string) esc_url_raw( $value );
			if ( '' === $value ) {
				continue;
			}
		} else {
			continue;
		}

		$key = strtolower( $type . '|' . $value );
		if ( ! isset( $resolved[ $key ] ) ) {
			$resolved[ $key ] = array(
				'type'  => $type,
				'value' => $value,
				'label' => $label,
			);
		}
	}

	return array_values( $resolved );
}

/**
 * ویژگی‌های واجد شرایط برای کارت‌های خلاصهٔ محصول. ویژگی‌های تنوع‌سازِ
 * قابل‌نمایش در اولویت‌اند؛ در مجموع حداکثر سه ویژگیِ غیر از برند نمایش
 * داده می‌شود تا تعداد کل کارت‌ها به چهار محدود بماند.
 *
 * @return array<int, array{name: string, attribute_name: string, label: string, value: string, variation: bool}>
 */
function jluxe_product_key_feature_attribute_candidates( $product ): array {
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$product_attributes = method_exists( $product, 'get_attributes' ) ? (array) $product->get_attributes() : array();
	$candidates        = array();
	$seen_names         = array();
	foreach ( $product_attributes as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) || ! method_exists( $attribute, 'get_variation' ) ) {
			continue;
		}
		if ( method_exists( $attribute, 'get_visible' ) && ! $attribute->get_visible() ) {
			continue;
		}
		if ( function_exists( 'wc_attributes_array_filter_visible' ) && ! wc_attributes_array_filter_visible( $attribute ) ) {
			continue;
		}

		$is_variation_attribute = $product->is_type( 'variable' ) && (bool) $attribute->get_variation();
		$attribute_name         = trim( (string) $attribute->get_name() );
		if ( '' === $attribute_name || isset( $seen_names[ $attribute_name ] ) ) {
			continue;
		}
		$attribute_label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $attribute_name, $product ) : $attribute_name;
		$normalized_label = strtolower( preg_replace( '/[\\s\\p{Cf}]+/u', ' ', trim( wp_strip_all_tags( $attribute_label ) ) ) ?? '' );
		if ( $is_variation_attribute && in_array( $normalized_label, array( 'آیتم مورد نظر را انتخاب کنید', 'یک ویژگی را انتخاب کنید', 'انتخاب یک ویژگی', 'select an attribute', 'choose an attribute' ), true ) ) {
			// این متنِ راهنما در نمونهٔ محصول به‌جای نامِ واقعی ویژگی ذخیره شده است.
			// فقط برچسبِ این کارت را جایگزین می‌کنیم؛ کلید و دادهٔ WooCommerce ثابت می‌مانند.
			$attribute_label = 'گزینه‌های محصول';
		}
		$normalized_name = strtolower( sanitize_title( $attribute_name ) );
		if (
			in_array( $normalized_name, array( 'brand', 'pa_brand', 'product_brand' ), true ) ||
			false !== strpos( $attribute_label, 'برند' ) ||
			false !== stripos( $attribute_label, 'brand' )
		) {
			continue;
		}

		if ( ! method_exists( $attribute, 'is_taxonomy' ) || ! method_exists( $attribute, 'get_options' ) ) {
			continue;
		}
		if ( $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ) {
			$raw_values = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
			if ( is_wp_error( $raw_values ) ) {
				continue;
			}
			$values = array();
			foreach ( (array) $raw_values as $term ) {
				if ( is_object( $term ) && isset( $term->name ) ) {
					$values[] = (string) $term->name;
				}
			}
		} else {
			$values = (array) $attribute->get_options();
		}
		$values = array_values(
			array_filter(
				array_map( static fn( $value ) => is_scalar( $value ) ? trim( (string) $value ) : '', $values ),
				static fn( $value ) => '' !== $value
			)
		);
		if ( ! $values ) {
			continue;
		}

		$seen_names[ $attribute_name ] = true;
		$candidates[]                  = array(
			'name'           => sanitize_title( $attribute_name ),
			'attribute_name' => $attribute_name,
			'label'          => $attribute_label,
			'value'          => implode( '، ', array_unique( $values ) ),
			'variation'      => $is_variation_attribute,
		);
	}

	return $candidates;
}

/** Read the assigned WooCommerce product_brand term as the summary's first card. */
function jluxe_product_key_feature_brand_card( $product ): ?array {
	if (
		! $product instanceof WC_Product ||
		! function_exists( 'taxonomy_exists' ) ||
		! taxonomy_exists( 'product_brand' ) ||
		! function_exists( 'wc_get_product_terms' )
	) {
		return null;
	}

	$brand_terms = wc_get_product_terms( $product->get_id(), 'product_brand', array( 'fields' => 'all' ) );
	if ( is_wp_error( $brand_terms ) ) {
		return null;
	}
	$brand_names = array();
	foreach ( (array) $brand_terms as $brand_term ) {
		$name = is_object( $brand_term ) && isset( $brand_term->name ) ? trim( (string) $brand_term->name ) : '';
		if ( '' !== $name ) {
			$brand_names[] = $name;
		}
	}
	$brand_names = array_values( array_unique( $brand_names ) );
	if ( ! $brand_names ) {
		return null;
	}

	return array(
		'name'  => 'product_brand',
		'label' => 'برند',
		'value' => implode( '، ', $brand_names ),
	);
}

/** Legacy compatibility reader; automatic summary-card rendering no longer uses this toggle. */
function jluxe_product_key_feature_brand_enabled( $product ): bool {
	$product_id = $product instanceof WC_Product ? (int) $product->get_id() : absint( $product );
	if ( ! $product_id ) {
		return false;
	}
	$meta_exists = function_exists( 'metadata_exists' )
		? metadata_exists( 'post', $product_id, '_jluxe_key_feature_brand_enabled' )
		: '' !== (string) get_post_meta( $product_id, '_jluxe_key_feature_brand_enabled', true );

	return ! $meta_exists || 'yes' === get_post_meta( $product_id, '_jluxe_key_feature_brand_enabled', true );
}

/**
 * Product-summary feature cards: the actual product_brand term comes first,
 * followed by at most three visible attributes in total. Variation attributes
 * take priority over regular attributes; legacy JLuxe selection metadata is
 * deliberately ignored and left untouched so native flags stay authoritative.
 *
 * @return array<int, array{name: string, label: string, value: string}>
 */
function jluxe_product_key_feature_cards( $product ): array {
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$cards      = array();
	$brand_card = jluxe_product_key_feature_brand_card( $product );
	if ( $brand_card ) {
		$cards[] = $brand_card;
	}

	$candidates   = jluxe_product_key_feature_attribute_candidates( $product );
	$feature_count = 0;
	foreach ( $candidates as $candidate ) {
		if ( empty( $candidate['variation'] ) ) {
			continue;
		}
		if ( $feature_count >= 3 ) {
			break;
		}
		$cards[] = array(
			'name'  => $candidate['name'],
			'label' => $candidate['label'],
			'value' => $candidate['value'],
		);
		++$feature_count;
	}

	foreach ( $candidates as $candidate ) {
		if ( ! empty( $candidate['variation'] ) ) {
			continue;
		}
		if ( $feature_count >= 3 ) {
			break;
		}
		$cards[] = array(
			'name'  => $candidate['name'],
			'label' => $candidate['label'],
			'value' => $candidate['value'],
		);
		++$feature_count;
	}
	return $cards;
}

/**
 * رنگ‌های قابل‌نمایشِ محصول، چه از variation attribute یک محصولِ متغیر
 * بیایند چه از ویژگیِ ساده/غیرتنوعی. این تابع یک منبعِ داده برای کارت‌ها و
 * جدول مشخصات است تا هر دو نوع محصول term name/slug را به یک شکل resolve کنند.
 *
 * @return array<int, array{type: string, value: string, label: string}>
 */
function jluxe_product_color_swatch_options( WC_Product $product ): array {
	$is_variable = $product->is_type( 'variable' );

	if ( $is_variable && method_exists( $product, 'get_variation_attributes' ) ) {
		foreach ( $product->get_variation_attributes() as $attribute_name => $options ) {
			if ( ! jluxe_is_color_attribute( (string) $attribute_name ) ) {
				continue;
			}
			$swatches = jluxe_attribute_swatch_options( (string) $attribute_name, (array) $options, $product );
			if ( $swatches ) {
				return $swatches;
			}
		}
	}

	if ( ! method_exists( $product, 'get_attributes' ) ) {
		return array();
	}
	foreach ( (array) $product->get_attributes() as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
			continue;
		}
		$attribute_name = (string) $attribute->get_name();
		if ( ! jluxe_is_color_attribute( $attribute_name ) ) {
			continue;
		}
		if ( $is_variable && method_exists( $attribute, 'get_variation' ) && $attribute->get_variation() ) {
			continue;
		}

		if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
			$options = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
			if ( is_wp_error( $options ) ) {
				continue;
			}
		} else {
			$options = method_exists( $attribute, 'get_options' ) ? (array) $attribute->get_options() : array();
		}

		$swatches = jluxe_attribute_swatch_options( $attribute_name, (array) $options, $product );
		if ( $swatches ) {
			return $swatches;
		}
	}

	return array();
}

/** Accessible markup for read-only color/image swatches in product specs. */
function jluxe_render_attribute_swatches_html( array $swatches, string $attribute_label, string $class = '' ): string {
	$swatches = array_values( array_filter( $swatches, 'is_array' ) );
	if ( ! $swatches ) {
		return '';
	}

	$labels = array_values( array_filter( array_map( static fn( $swatch ) => (string) ( $swatch['label'] ?? '' ), $swatches ) ) );
	$group_label = $attribute_label;
	if ( $labels ) {
		$group_label .= ': ' . implode( '، ', $labels );
	}
	$classes = 'jluxe-attribute-swatches' . ( '' !== trim( $class ) ? ' ' . esc_attr( trim( $class ) ) : '' );
	$html    = '<span class="' . $classes . '" role="group" aria-label="' . esc_attr( $group_label ) . '">';

	foreach ( $swatches as $swatch ) {
		$type  = (string) ( $swatch['type'] ?? '' );
		$value = (string) ( $swatch['value'] ?? '' );
		$label = (string) ( $swatch['label'] ?? $attribute_label );
		if ( 'color' === $type ) {
			$color = sanitize_hex_color( $value );
			if ( ! $color ) {
				continue;
			}
			$html .= '<span class="jluxe-attribute-swatch" role="img" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '" style="background-color:' . esc_attr( $color ) . '"></span>';
		} elseif ( 'image' === $type ) {
			$url = esc_url( $value );
			if ( '' === $url ) {
				continue;
			}
			$html .= '<span class="jluxe-attribute-swatch jluxe-attribute-swatch-image" role="img" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '"><img src="' . $url . '" alt="" loading="lazy" /></span>';
		}
	}

	return $html . '</span>';
}

/**
 * مشخصاتِ گروه‌بندی‌شده را از override پوسته (در صورت وجود) وگرنه از متای
 * قدیمی Boom می‌خواند. دادهٔ Boom دست‌نخورده می‌ماند؛ آرایه، JSON معمولی و
 * JSON کدشده با Base64 (قالبِ boom_adv_attributes_json) پشتیبانی می‌شوند.
 *
 * @return array<int, array{title: string, items: array<int, array{label: string, value: string}>}>
 */
function jluxe_get_product_advanced_attribute_groups( int $product_id, bool $use_legacy_boom_meta = false ): array {
	$product_id = absint( $product_id );
	if ( $product_id < 1 ) {
		return array();
	}

	$has_theme_override = ! $use_legacy_boom_meta && '1' === (string) get_post_meta( $product_id, '_jluxe_product_advanced_attributes_override', true );
	$meta_key           = $has_theme_override ? '_jluxe_product_advanced_attributes' : '_boom_advanced_attributes';
	$stored             = get_post_meta( $product_id, $meta_key, true );
	$data   = null;
	if ( is_array( $stored ) ) {
		// WordPress از پیش مقدارهای serializeشده را به آرایه برمی‌گرداند.
		$data = $stored;
	} elseif ( is_string( $stored ) && '' !== trim( $stored ) ) {
		$candidates      = array( trim( $stored ) );
		$base64_candidate = preg_replace( '/\\s+/', '', $stored );
		if ( is_string( $base64_candidate ) && '' !== $base64_candidate ) {
			$base64_json = base64_decode( $base64_candidate, true );
			if ( false !== $base64_json ) {
				$candidates[] = $base64_json;
			}
		}

		foreach ( $candidates as $candidate ) {
			$decoded = json_decode( $candidate, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$data = $decoded;
				break;
			}
		}
	}

	if ( ! is_array( $data ) ) {
		return array();
	}

	$groups = array();
	foreach ( array_slice( $data, 0, 40 ) as $group ) {
		if ( ! is_array( $group ) || ! isset( $group['title'], $group['items'] ) || ! is_array( $group['items'] ) || ! is_scalar( $group['title'] ) ) {
			continue;
		}

		$title = sanitize_text_field( (string) $group['title'] );
		if ( '' === $title ) {
			continue;
		}

		$items = array();
		foreach ( array_slice( $group['items'], 0, 100 ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) || ! is_scalar( $item['label'] ) || ! is_scalar( $item['value'] ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $item['label'] );
			$value = sanitize_text_field( (string) $item['value'] );
			if ( '' === $label || '' === $value ) {
				continue;
			}
			$items[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		if ( $items ) {
			$groups[] = array(
				'title' => $title,
				'items' => $items,
			);
		}
	}

	return $groups;
}

/**
 * ویژگی‌های نمایانِ معمولی و تنوع‌سازِ ووکامرس را برای گروهِ «سایر مشخصات»
 * آماده می‌کند و برچسب‌هایی را که قبلاً در گروه‌های Boom آمده‌اند تکرار نمی‌کند.
 *
 * @return array<int, array{label: string, value: string, swatches: array}>
 */
function jluxe_get_additional_product_specification_rows( WC_Product $product, array $groups ): array {
	if ( ! method_exists( $product, 'get_attributes' ) ) {
		return array();
	}

	$normalize_label = static function ( $label ): string {
		$label = trim( preg_replace( '/\\s+/', ' ', wp_strip_all_tags( (string) $label ) ) ?? '' );
		return strtolower( $label );
	};
	$seen_labels = array();
	foreach ( $groups as $group ) {
		foreach ( $group['items'] ?? array() as $item ) {
			$key = $normalize_label( $item['label'] ?? '' );
			if ( '' !== $key ) {
				$seen_labels[ $key ] = true;
			}
		}
	}

	$rows = array();
	foreach ( (array) $product->get_attributes() as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
			continue;
		}
		if ( function_exists( 'jluxe_is_grouped_specification_export_attribute' ) && jluxe_is_grouped_specification_export_attribute( $product, $attribute ) ) {
			continue; // این ردیف در گروه‌های اصلی چاپ می‌شود؛ در «سایر مشخصات» تکرار نشود.
		}
		if ( method_exists( $attribute, 'get_visible' ) && ! $attribute->get_visible() ) {
			continue;
		}

		$attribute_name = (string) $attribute->get_name();
		if ( '' === $attribute_name ) {
			continue;
		}
		$is_taxonomy = method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy();
		$raw_values  = $is_taxonomy && function_exists( 'wc_get_product_terms' )
			? wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) )
			: ( method_exists( $attribute, 'get_options' ) ? (array) $attribute->get_options() : array() );
		if ( is_wp_error( $raw_values ) ) {
			continue;
		}

		$values = $is_taxonomy
			? ( is_array( $raw_values ) ? wp_list_pluck( $raw_values, 'name' ) : array() )
			: (array) $raw_values;
		$values = array_values(
			array_filter(
				array_map(
					static function ( $value ): string {
					return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
				},
				$values
			),
			static function ( $value ): bool {
				return '' !== $value;
			}
			)
		);
		if ( ! $values ) {
			continue;
		}

		$label = sanitize_text_field( (string) wc_attribute_label( $attribute_name, $product ) );
		$key   = $normalize_label( $label );
		if ( '' === $key || isset( $seen_labels[ $key ] ) ) {
			continue;
		}
		$seen_labels[ $key ] = true;
		$rows[]              = array(
			'label'    => $label,
			'value'    => implode( '، ', $values ),
			'swatches' => jluxe_attribute_swatch_options( $attribute_name, (array) $raw_values, $product ),
		);
	}

	return $rows;
}

/**
 * Return only grouped sections that should be visible in the product table.
 * The original groups remain untouched for storage, exports, and Product JSON-LD.
 *
 * @return array<int, array<string, mixed>>
 */
function jluxe_get_displayable_product_specification_groups( array $groups ): array {
	$normalize_title = static function ( $title ): string {
		$title = trim( preg_replace( '/\\s+/', ' ', wp_strip_all_tags( (string) $title ) ) ?? '' );
		return strtolower( $title );
	};
	$other_title = $normalize_title( 'سایر مشخصات' );
	$visible     = array();

	foreach ( $groups as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		$title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		if ( '' === $title || $other_title === $normalize_title( $title ) || ! $items ) {
			continue;
		}
		$group['title'] = $title;
		$visible[]      = $group;
	}

	return $visible;
}

/**
 * Render only the intended grouped specifications. The «سایر مشخصات» group
 * and extra WooCommerce attributes stay intact in storage/schema/export data,
 * but are deliberately omitted from the visual product table.
 *
 * @param array $additional_rows Kept for backwards compatibility; not rendered.
 */
function jluxe_render_grouped_product_specifications( array $groups, array $additional_rows = array() ): string {
	$groups = jluxe_get_displayable_product_specification_groups( $groups );
	if ( ! $groups ) {
		return '';
	}

	$html = '<div class="jluxe-product-advanced-specs" dir="rtl">';
	foreach ( $groups as $group ) {
		$title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		if ( '' === $title || ! $items ) {
			continue;
		}

		$html .= '<section class="jluxe-product-advanced-specs__group">';
		$html .= '<header class="jluxe-product-advanced-specs__heading"><span class="jluxe-product-advanced-specs__accent" aria-hidden="true"></span><h4>' . esc_html( $title ) . '</h4></header>';
		$html .= '<dl class="jluxe-product-advanced-specs__rows">';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) || ! is_scalar( $item['label'] ) || ! is_scalar( $item['value'] ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) $item['label'] );
			$value = sanitize_text_field( (string) $item['value'] );
			if ( '' === $label || '' === $value ) {
				continue;
			}

			$value_html    = esc_html( $value );
			$swatches_html = isset( $item['swatches'] ) && is_array( $item['swatches'] )
				? jluxe_render_attribute_swatches_html( $item['swatches'], $label )
				: '';
			if ( '' !== $swatches_html ) {
				$value_html = '<span class="jluxe-product-advanced-specs__value"><span class="jluxe-product-advanced-specs__value-text">' . esc_html( $value ) . '</span>' . $swatches_html . '</span>';
			}
			$html .= '<div class="jluxe-product-advanced-specs__row"><dt>' . esc_html( $label ) . '</dt><dd>' . $value_html . '</dd></div>';
		}

		$html .= '</dl></section>';
	}

	return $html . '</div>';
}

/**
 * ردیف ستاره‌ی امتیاز با پرشدنِ اعشاری واقعی (مثلاً ۴.۲۷ از ۵ → ستاره‌ی
 * پنجم حدود ۲۷٪ پر می‌شه)، نه رند‌شده به نزدیک‌ترین عدد صحیح — دقیقاً
 * همون رفتاری که ووکامرس/مرجع (Boom) نشون می‌ده. با یک لایه‌ی «۵ ستاره‌ی
 * خالی» زیرین + یک لایه‌ی «۵ ستاره‌ی پر» روییِ clip‌شده با عرض درصدی پیاده
 * شده — بدون نیاز به SVG جدا برای هر حالت نیم‌پر.
 */
function jluxe_boom_star_row( float $rating, string $size_class = 'size-[15px]' ): string {
	$pct = max( 0, min( 100, ( $rating / 5 ) * 100 ) );
	$star_path = 'm12 3.6 2.6 5.3 5.8.85-4.2 4.1 1 5.75L12 16.85 6.8 19.6l1-5.75L3.6 9.75l5.8-.85Z';
	$star_svg  = sprintf(
		'<svg class="%1$s shrink-0" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="%2$s"></path></svg>',
		esc_attr( $size_class ),
		$star_path
	);

	return sprintf(
		'<span class="relative inline-flex" aria-hidden="true"><span class="flex text-border">%1$s</span><span class="absolute inset-0 flex overflow-hidden text-boom-star" style="width:%2$s%%">%1$s</span></span>',
		str_repeat( $star_svg, 5 ),
		esc_attr( (string) round( $pct, 1 ) )
	);
}

/**
 * خط تعداد موجودیِ واقعی (نه عدد ساختگی) — فقط وقتی محصول/تنوع واقعاً
 * موجودیِ عددی رو مدیریت می‌کنه (managing_stock) چیزی نشون داده می‌شه؛
 * وگرنه (فقط وضعیت موجود/ناموجودِ ساده) خط خالی برمی‌گرده. زیر ۵ عدد
 * رنگ هشدار می‌گیره.
 */
function jluxe_render_product_stock_line( $product ): string {
	if ( ! $product || ! $product->managing_stock() ) {
		return '';
	}
	$qty = $product->get_stock_quantity();
	if ( null === $qty ) {
		return '';
	}
	$low  = $qty <= 5;
	$icon = '<svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>';
	$text = $low
		? sprintf( 'فقط %s عدد در انبار باقی مانده!', jluxe_fa_digits( (string) $qty ) )
		: sprintf( '%s عدد در انبار موجود است', jluxe_fa_digits( (string) $qty ) );

	return sprintf(
		'<p class="mt-1.5 flex items-center gap-1 text-[11px] font-medium %1$s">%2$s%3$s</p>',
		$low ? 'jluxe-text-danger' : 'text-text-muted',
		$icon,
		esc_html( $text )
	);
}

/**
 * صفحه‌ی محصول (woocommerce/content-single-product.php) توضیحات/مشخصات/
 * دیدگاه‌ها رو خودش به‌صورت بخش‌های جدا (نه تب‌باکس) رندر می‌کنه، پس
 * تب‌باکس پیش‌فرض ووکامرس (که همون محتوا رو دوباره تکرار می‌کرد) و
 * upsell (که در طراحی تأییدشده وجود نداره) حذف می‌شن. محصولات مرتبط
 * (woocommerce_output_related_products) و پرسش‌وپاسخ واقعی
 * (jluxe_render_qa_section در inc/qa.php) دست‌نخورده می‌مونن.
 */
function jluxe_remove_default_product_tabs(): void {
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
}

/**
 * نوتیس‌های قدیمیِ خودِ ووکامرس (wc_print_notices، از هوکِ رسمیِ
 * woocommerce_before_single_product) بالای صفحه‌ی محصول ظاهر می‌شن —
 * چه سبز (موفقیت) چه قرمز (خطا، مثلاً «لطفاً گزینه‌های محصول را انتخاب
 * کنید» برای محصولِ متغیر بدون variation معتبر) — که با سیستمِ toastِ
 * سفارشیِ ما (assets/js/woocommerce.js: showToast) قاطی/تکراری می‌شه
 * (باگِ واقعیِ گزارش‌شده). فقط روی صفحه‌ی محصول این هوکِ خاص حذف می‌شه —
 * سبد/تسویه‌حساب/فروشگاه که toast ندارن دست‌نخورده می‌مونن، چون اون‌جا
 * نمایشِ نوتیسِ سرور واقعاً لازمه.
 */
function jluxe_remove_single_product_notices(): void {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
	}
}

/**
 * رنگ‌های صفحه‌ی محصول (تنظیمات → صفحه محصول). پیش‌فرض‌هایِ رنگِ تخفیف/سود/
 * امتیاز از توکن‌های semantic و رنگ‌های پیوند از توکنِ اصلی پوسته می‌آیند؛
 * فقط انتخابِ صریحِ مدیر به‌صورت override ذخیره و رندر می‌شود.
 */
function jluxe_output_product_page_colors_css(): void {
	$settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
	$pp = isset( $settings['product_page'] ) && is_array( $settings['product_page'] ) ? $settings['product_page'] : array();
	$overrides = array();
	foreach (
		array(
			'discount_color'                      => '--jluxe-sale-color',
			'savings_color'                       => '--jluxe-savings-color',
			'star_color'                          => '--jluxe-rating-color',
			'variation_label_color'               => '--jluxe-variation-label-color',
			'description_link_bg_color'           => '--jluxe-description-link-bg',
			'description_link_text_color'         => '--jluxe-description-link-text',
			'description_link_border_color'       => '--jluxe-description-link-border',
		) as $setting_key => $variable
	) {
		$color = isset( $pp[ $setting_key ] ) ? sanitize_hex_color( $pp[ $setting_key ] ) : '';
		if ( $color ) {
			$overrides[] = $variable . ':' . $color;
		}
	}
	if ( empty( $overrides ) ) {
		return;
	}
	printf( '<style id="jluxe-product-page-colors">:root{%s}</style>', esc_html( implode( ';', $overrides ) ) );
}

/**
 * Read the product-specific delivery message from the product editor's
 * existing `_delivery_time` field. Keep `_jluxe_delivery_time` as a legacy
 * fallback for values created by releases before the editor field was shared.
 * An explicitly empty canonical value stays empty instead of reviving stale
 * legacy text; the example label is never rendered as a fixed promise.
 */
function jluxe_get_product_delivery_time( $product = null ): string {
	$product_id = $product instanceof WC_Product ? (int) $product->get_id() : absint( $product );
	if ( ! $product_id ) {
		return '';
	}

	$canonical_value = get_post_meta( $product_id, '_delivery_time', true );
	if ( function_exists( 'metadata_exists' ) && metadata_exists( 'post', $product_id, '_delivery_time' ) ) {
		return is_scalar( $canonical_value ) ? trim( sanitize_text_field( (string) $canonical_value ) ) : '';
	}
	$canonical_text = is_scalar( $canonical_value ) ? trim( sanitize_text_field( (string) $canonical_value ) ) : '';
	if ( '' !== $canonical_text ) {
		return $canonical_text;
	}

	$legacy_value = get_post_meta( $product_id, '_jluxe_delivery_time', true );
	return is_scalar( $legacy_value ) ? trim( sanitize_text_field( (string) $legacy_value ) ) : '';
}

/** Existing products keep their shipping row visible unless it is explicitly disabled. */
function jluxe_is_product_delivery_time_enabled( $product = null ): bool {
	$product_id = $product instanceof WC_Product ? (int) $product->get_id() : absint( $product );
	if ( ! $product_id ) {
		return false;
	}
	$meta_exists = function_exists( 'metadata_exists' )
		? metadata_exists( 'post', $product_id, '_jluxe_delivery_time_enabled' )
		: '' !== (string) get_post_meta( $product_id, '_jluxe_delivery_time_enabled', true );

	return ! $meta_exists || 'yes' === get_post_meta( $product_id, '_jluxe_delivery_time_enabled', true );
}

/** Read the optional, product-specific warning or information notice. */
function jluxe_get_product_notice( $product = null ): array {
	$product_id = $product instanceof WC_Product ? (int) $product->get_id() : absint( $product );
	if ( ! $product_id ) {
		return array( 'enabled' => false, 'type' => 'warning', 'text' => '' );
	}

	$type = sanitize_key( (string) get_post_meta( $product_id, '_jluxe_product_notice_type', true ) );
	if ( ! in_array( $type, array( 'warning', 'info' ), true ) ) {
		$type = 'warning';
	}
	$text       = get_post_meta( $product_id, '_jluxe_product_notice_text', true );
	$visibility = sanitize_key( (string) get_post_meta( $product_id, '_jluxe_product_notice_visibility', true ) );

	return array(
		/* Only a deliberate new-style opt-out hides a notice; legacy text remains visible. */
		'enabled' => 'hidden' !== $visibility,
		'type'    => $type,
		'text'    => is_scalar( $text ) ? sanitize_textarea_field( (string) $text ) : '',
	);
}

/** Render a text-only product notice so stored markup can never become executable HTML. */
function jluxe_render_product_notice( $product ): void {
	$notice = jluxe_get_product_notice( $product );
	if ( empty( $notice['enabled'] ) || '' === trim( (string) $notice['text'] ) ) {
		return;
	}

	$is_warning = 'warning' === $notice['type'];
	$title      = $is_warning ? 'توجه' : 'اطلاعیه';
	echo '<aside class="jluxe-product-notice jluxe-product-notice--' . esc_attr( $notice['type'] ) . '" data-jluxe-product-notice role="note" aria-label="' . esc_attr( $title ) . '">';
	echo '<span class="jluxe-product-notice__icon" aria-hidden="true">';
	if ( $is_warning ) {
		echo '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3 2.8 19a1.5 1.5 0 0 0 1.3 2.2h15.8a1.5 1.5 0 0 0 1.3-2.2L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 9v4.5m0 3h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
	} else {
		echo '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5m0-8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
	}
	echo '</span><div class="jluxe-product-notice__content"><strong class="jluxe-product-notice__title">' . esc_html( $title ) . '</strong><div class="jluxe-product-notice__text">' . nl2br( esc_html( $notice['text'] ) ) . '</div></div></aside>';
}

/** Read the optional English product name without inventing a fallback label. */
function jluxe_get_product_english_name( $product = null ): string {
	$product_id = $product instanceof WC_Product ? (int) $product->get_id() : absint( $product );
	if ( ! $product_id ) {
		return '';
	}

	$value = get_post_meta( $product_id, '_english_product_name', true );
	return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
}

/**
 * Return the enabled trust badges for one product.
 *
 * @return array<int, array{label: string, text_class: string, bg_class: string}>
 */
function jluxe_get_product_trust_badges( int $product_id ): array {
	$badges = array();

	if ( 'yes' === get_post_meta( $product_id, '_jluxe_badge_authenticity', true ) ) {
		$badges[] = array(
			'label'      => 'گارانتی اصالت کالا',
			'text_class' => 'text-boom-success',
			'bg_class'   => 'bg-boom-success-soft',
		);
	}

	if ( 'yes' === get_post_meta( $product_id, '_jluxe_badge_warranty', true ) ) {
		$badges[] = array(
			'label'      => 'کالای دارای ضمانت',
			'text_class' => 'text-boom-info',
			'bg_class'   => 'bg-boom-info-soft',
		);
	}

	return $badges;
}

/**
 * Render the enabled product trust badges using the shared storefront markup.
 * The spacing utility is whitelisted so both product layouts retain their
 * existing vertical rhythm without accepting arbitrary classes from callers.
 */
function jluxe_render_product_trust_badges( int $product_id, string $spacing_class = 'mt-3' ): void {
	$badges = jluxe_get_product_trust_badges( $product_id );
	if ( ! $badges ) {
		return;
	}

	if ( ! in_array( $spacing_class, array( 'mt-3', 'mt-4' ), true ) ) {
		$spacing_class = 'mt-3';
	}

	echo '<div class="flex flex-wrap items-center gap-2 ' . esc_attr( $spacing_class ) . '" data-jluxe-trust-badges>';
	foreach ( $badges as $badge ) {
		printf(
			'<span class="inline-flex w-fit items-center gap-1 rounded-full %1$s px-2 py-1 text-[11px] font-bold %2$s"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg>%3$s</span>',
			esc_attr( $badge['bg_class'] ),
			esc_attr( $badge['text_class'] ),
			esc_html( $badge['label'] )
		);
	}
	echo '</div>';
}

/**
 * ذخیره‌سازیِ امتیازهای هر معیار موقعِ ثبتِ دیدگاه — روی همون هوکِ
 * comment_post که خودِ ووکامرس هم برایِ ذخیره‌ی rating اصلی استفاده می‌کنه؛
 * فقط برای دیدگاه‌های واقعیِ محصول (comment_type='review'، توسطِ ووکامرس
 * موقعِ ارسالِ فرمِ روی صفحه‌ی محصول ست می‌شه).
 */
function jluxe_save_review_criteria_ratings( int $comment_id, $comment_approved ): void {
	if ( empty( $_POST['jluxe_review_criteria'] ) || ! is_array( $_POST['jluxe_review_criteria'] ) ) {
		return;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'review' !== $comment->comment_type ) {
		return;
	}

	$criteria = jluxe_get_theme_settings()['review_criteria']['items'];
	$valid_keys = wp_list_pluck( $criteria, 'key' );

	$clean = array();
	foreach ( wp_unslash( $_POST['jluxe_review_criteria'] ) as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$key = sanitize_key( $key );
		if ( ! in_array( $key, $valid_keys, true ) ) {
			continue; // فقط معیارهایِ واقعاً تعریف‌شده — جلوگیری از تزریقِ کلیدِ دلخواه.
		}
		$value = is_scalar( $value ) ? trim( jluxe_ascii_digits( (string) $value ) ) : '';
		$rating = (int) $value;
		if ( ! preg_match( '/^[1-5]$/D', $value ) ) {
			continue;
		}
		$clean[ $key ] = $rating;
	}

	if ( ! empty( $clean ) ) {
		update_comment_meta( $comment_id, '_jluxe_review_criteria', $clean );
	}
}

/**
 * ستاره‌های ورودی برایِ هر معیار — یک ردیف رادیو با ترفندِ CSS خالص
 * (چیدمانِ معکوس + سیبلینگِ :checked~label، بدونِ نیاز به هیچ جاوااسکریپت)
 * که در globals.css پیاده شده (.jluxe-review-criteria-stars).
 */
function jluxe_render_review_criteria_inputs(): string {
	$criteria = jluxe_get_theme_settings()['review_criteria']['items'];
	if ( empty( $criteria ) ) {
		return '';
	}

	$html = '<div class="jluxe-review-criteria-fields">';
	foreach ( $criteria as $item ) {
		$field_id = 'jluxe-crit-' . esc_attr( $item['key'] );
		$html    .= '<fieldset class="jluxe-review-criteria-row">';
		$html    .= '<legend class="jluxe-review-criteria-label">' . esc_html( $item['label'] ) . '</legend>';
		$html    .= '<div class="jluxe-review-criteria-stars" dir="ltr">';
		for ( $i = 5; $i >= 1; $i-- ) {
			$input_id = $field_id . '-' . $i;
			$html    .= '<input type="radio" id="' . esc_attr( $input_id ) . '" name="jluxe_review_criteria[' . esc_attr( $item['key'] ) . ']" value="' . esc_attr( (string) $i ) . '" />';
			$html    .= '<label for="' . esc_attr( $input_id ) . '" aria-label="' . esc_attr( (string) $i ) . ' از ۵">★</label>';
		}
		$html .= '</div></fieldset>';
	}
	$html .= '</div>';

	return $html;
}

/** Mobile-only variables: independent from desktop and the selected layout. */
function jluxe_output_product_mobile_design_css(): void {
	$settings = jluxe_get_theme_settings();
	$values = jluxe_product_mobile_design_values( (array) ( $settings['product_page'] ?? array() ) );
	$rules = array();
	foreach ( $values as $key => $value ) {
		$rules[] = '--jluxe-' . str_replace( '_', '-', $key ) . ':' . $value . ( 'mobile_title_weight' === $key ? '' : 'px' );
	}
	printf( '<style id="jluxe-product-mobile-design">@media(max-width:767.98px){body.single-product{%s}}</style>', esc_html( implode( ';', $rules ) ) );
}
