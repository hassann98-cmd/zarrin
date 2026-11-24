<?php
defined( 'ABSPATH' ) || exit;

/** Catalog queries, navigation, related/suggested products and shop toolbar. */

/**
 * شمارشگرِ ترتیبِ رندرِ کارتِ محصول در طولِ یک درخواست — برای تشخیصِ اینکه
 * کدوم عکس‌ها واقعاً بالای صفحه‌ن (باید eager/fetchpriority=high باشن) و
 * کدوم‌ها پایین‌ترن (lazy). عمداً یک تابعِ واقعیه، نه یک static در سطحِ
 * بالای خودِ woocommerce/content-product.php — چون wc_get_template_part()
 * اون فایل رو برای هر محصول با include (نه include_once) جدا include
 * می‌کنه، و static در سطحِ فایل (نه داخلِ تابع) بینِ include-های جدا پایدار
 * نمی‌مونه (هر بار از نو صفر می‌شه). static داخلِ یک تابعِ واقعی، برخلافش،
 * بینِ همه‌ی فراخوانی‌های همون تابع در طولِ کلِ درخواست درست پایدار می‌مونه.
 */
function jluxe_next_product_card_index(): int {
	static $i = 0;
	++$i;
	return $i;
}

/** Attach current term icons even when the hierarchy comes from an older cache. */
function jluxe_mega_menu_category_icons( array $categories ): array {
 foreach ( $categories as &$category ) {
  if ( ! is_array( $category ) || empty( $category['id'] ) ) { continue; }
  $id = absint( $category['id'] );
  $custom = get_term_meta( $id, '_jluxe_category_icon_svg', true );
  $custom = is_string( $custom ) ? jluxe_sanitize_svg_markup( $custom ) : '';
  $preset = get_term_meta( $id, '_jluxe_category_icon', true );
  $override = is_string( $preset ) && '' !== $preset && 'auto' !== $preset;
  $thumbnail = absint( get_term_meta( $id, 'thumbnail_id', true ) );
  $image = ! $custom && ! $override && $thumbnail ? wp_get_attachment_image_url( $thumbnail, 'thumbnail' ) : '';
  // Existing uploads keep their thumbnail until this size is regenerated.
  if ( $image && function_exists( 'image_get_intermediate_size' ) && image_get_intermediate_size( $thumbnail, 'jluxe-category-icon' ) ) {
    $image = wp_get_attachment_image_url( $thumbnail, 'jluxe-category-icon' );
  }
  $category['iconImage'] = is_string( $image ) ? esc_url_raw( $image ) : '';
  $category['iconSvg'] = $custom ?: ( $override || ! $category['iconImage'] ? jluxe_nav_icon_svg( $override ? $preset : 'grid', 'jluxe-mega-category-svg' ) : '' );
 }
 unset( $category );
 return $categories;
}

function jluxe_get_mega_menu_categories(): array {
	$cache_key = 'jluxe_mega_menu_tree_v1';
	/* R62: دو لایهٔ کش — Object Cache (با Redis واقعی می‌ماند؛ بدونِ آن فقط
	حافظهٔ همین درخواست است) و بعد Transient (fallback برای نصب‌های بدونِ
	object-cache.php). خروجی در هر دو لایه «آرایهٔ» دیتاست است، نه HTML. */
	$cached = wp_cache_get( $cache_key, 'jluxe' );
	if ( is_array( $cached ) ) {
		return jluxe_mega_menu_category_icons( $cached );
	}
	$cached = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		wp_cache_set( $cache_key, $cached, 'jluxe', HOUR_IN_SECONDS );
		return jluxe_mega_menu_category_icons( $cached );
	}

	$default_cat_id = (int) get_option( 'default_product_cat' );

	$top_terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'exclude'    => $default_cat_id ? array( $default_cat_id ) : array(),
		)
	);
	if ( is_wp_error( $top_terms ) ) {
		return array();
	}

	$categories = array();
	foreach ( $top_terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term->term_id,
				'hide_empty' => true,
			)
		);
		$children = is_wp_error( $children ) ? array() : $children;

		$groups = array();
		foreach ( $children as $child ) {
			$child_link = get_term_link( $child );
			if ( is_wp_error( $child_link ) ) {
				continue;
			}

			$grandchildren = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $child->term_id,
					'hide_empty' => true,
				)
			);
			$grandchildren = is_wp_error( $grandchildren ) ? array() : $grandchildren;

			$items = array();
			foreach ( $grandchildren as $grandchild ) {
				$grandchild_link = get_term_link( $grandchild );
				if ( is_wp_error( $grandchild_link ) ) {
					continue;
				}
				$items[] = array(
					'label' => $grandchild->name,
					'url'   => $grandchild_link,
				);
			}

			$groups[] = array(
				'title' => $child->name,
				'url'   => $child_link,
				'items' => $items,
			);
		}

		$categories[] = array(
			'id'     => (string) $term->term_id,
			'label'  => $term->name,
			'url'    => $link,
			'count'  => (int) $term->count,
			'groups' => $groups,
		);
	}

	set_transient( $cache_key, $categories, DAY_IN_SECONDS );
	wp_cache_set( $cache_key, $categories, 'jluxe', HOUR_IN_SECONDS );
	return jluxe_mega_menu_category_icons( $categories );
}

/**
 * پاک‌سازی کش درخت مگامنو پس از تغییر دسته‌های محصول — هر دو لایه
 * (Object Cache و Transient) با هم.
 */
function jluxe_clear_mega_menu_cache( $term_id = 0, $taxonomy = '' ): void {
	if ( 'product_cat' === $taxonomy ) {
		delete_transient( 'jluxe_mega_menu_tree_v1' );
		wp_cache_delete( 'jluxe_mega_menu_tree_v1', 'jluxe' );
	}
}

/**
 * تاکسونومیِ واقعیِ «برند»؛ پنل آن به‌صورت فهرستِ تیک‌خور و جدا از برچسب‌های
 * محصول نمایش داده می‌شود تا دو مفهوم در صفحهٔ ویرایش محصول با هم اشتباه نشوند.
 * این تاکسونومی در فیلتر محصول و بخش‌های صفحهٔ اصلی هم استفاده می‌شود.
 */
function jluxe_register_product_brand_taxonomy(): void {
	register_taxonomy(
		'product_brand',
		'product',
		array(
			'label'             => 'برندها',
			'labels'            => array(
				'name'                       => 'برندها',
				'singular_name'              => 'برند',
				'menu_name'                  => 'برندها',
				'search_items'               => 'جستجوی برندها',
				'popular_items'              => 'برندهای پرکاربرد',
				'all_items'                  => 'همهٔ برندها',
				'parent_item'                => 'برند والد',
				'parent_item_colon'          => 'برند والد:',
				'edit_item'                  => 'ویرایش برند',
				'view_item'                  => 'نمایش برند',
				'update_item'                => 'به‌روزرسانی برند',
				'add_new_item'               => 'افزودن برند جدید',
				'new_item_name'              => 'نام برند جدید',
				'separate_items_with_commas' => 'نام برندها را با ویرگول جدا کنید.',
				'add_or_remove_items'        => 'افزودن یا حذف برندها',
				'choose_from_most_used'      => 'انتخاب از برندهای پرکاربرد',
				'not_found'                  => 'برندی پیدا نشد.',
				'no_terms'                   => 'هنوز برندی ثبت نشده است.',
				'items_list_navigation'      => 'پیمایش فهرست برندها',
				'items_list'                 => 'فهرست برندها',
			),
			// WordPress uses its checkbox-style taxonomy meta box for this panel;
			// the default flat product_tag taxonomy keeps its separate tag editor.
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'brand' ),
		)
	);
}

function jluxe_maybe_disable_related_products(): void {
	if ( ! jluxe_get_setting( 'product_page.show_related', true ) ) {
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
	}
}

function jluxe_related_products_args( array $args ): array {
	$count                = max( 2, min( 8, (int) jluxe_get_setting( 'product_page.related_count', 4 ) ) );
	$args['posts_per_page'] = $count;
	$args['columns']        = min( 4, $count );
	// The IDs are explicitly ranked below; WooCommerce's default "rand" would erase that order.
	$args['orderby']       = 'none';
	$args['order']         = 'asc';
	return $args;
}

/** Keep our category/availability ranking instead of WooCommerce's final shuffle. */
function jluxe_related_products_shuffle( $shuffle ): bool {
	return function_exists( 'is_product' ) && is_product() ? false : (bool) $shuffle;
}

/** Get public, non-hidden category IDs for an automatic related-products query. */
function jluxe_related_category_slugs( array $category_ids ): array {
	$slugs = array();

	foreach ( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) as $category_id ) {
		$term = get_term( $category_id, 'product_cat' );
		if ( is_wp_error( $term ) || ! is_object( $term ) || empty( $term->slug ) ) {
			continue;
		}
		$slugs[] = (string) $term->slug;
	}

	return array_values( array_unique( $slugs ) );
}

/** True when a related product can be shown in the public catalog. */
function jluxe_related_product_is_public( $product ): bool {
	if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility() ) {
		return false;
	}

	return ! method_exists( $product, 'is_visible' ) || $product->is_visible();
}

/**
 * Query a bounded set of same-category products by stock status. We make the stock buckets
 * explicit so catalog hide-out-of-stock settings do not silently drop the final fallback;
 * each bucket starts with its newest products.
 */
function jluxe_related_category_stock_pools( array $category_slugs, int $product_id, array $excluded_ids, int $limit ): array {
	$pools = array(
		'available'   => array(),
		'unavailable' => array(),
	);

	if ( empty( $category_slugs ) || ! function_exists( 'wc_get_products' ) ) {
		return $pools;
	}

	$query_limit      = max( 20, min( 100, $limit * 5 ) );
	$query_exclusions = array_values( array_unique( array_merge( array_map( 'absint', $excluded_ids ), array( $product_id ) ) ) );
	$seen             = array_fill_keys( $query_exclusions, true );

	foreach ( array( 'instock', 'onbackorder', 'outofstock' ) as $stock_status ) {
		$products = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => $stock_status,
				'exclude'      => $query_exclusions,
				'category'     => $category_slugs,
				'limit'        => $query_limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'objects',
			)
		);

		if ( ! is_array( $products ) ) {
			continue;
		}

		foreach ( $products as $related_product ) {
			if ( ! jluxe_related_product_is_public( $related_product ) ) {
				continue;
			}

			$related_id = absint( $related_product->get_id() );
			if ( ! $related_id || isset( $seen[ $related_id ] ) ) {
				continue;
			}

			$seen[ $related_id ] = true;
			$bucket              = jluxe_suggested_is_available( $related_product ) ? 'available' : 'unavailable';
			$pools[ $bucket ][]  = $related_id;
		}
	}

	return $pools;
}

/** Return a product's publication timestamp, preferring WooCommerce's typed date object. */
function jluxe_related_product_date_timestamp( int $product_id, $product = null ): int {
	if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $product_id );
	}

	if ( $product instanceof WC_Product && method_exists( $product, 'get_date_created' ) ) {
		$date = $product->get_date_created();
		if ( is_object( $date ) && method_exists( $date, 'getTimestamp' ) ) {
			return (int) $date->getTimestamp();
		}
		if ( is_numeric( $date ) ) {
			return (int) $date;
		}
	}

	$post_date = function_exists( 'get_post_field' ) ? get_post_field( 'post_date_gmt', $product_id ) : '';
	if ( ! is_string( $post_date ) || '' === trim( $post_date ) || '0000-00-00 00:00:00' === $post_date ) {
		$post_date = function_exists( 'get_post_field' ) ? get_post_field( 'post_date', $product_id ) : '';
	}
	$timestamp = is_string( $post_date ) && '' !== trim( $post_date ) ? strtotime( $post_date ) : false;

	return false === $timestamp ? 0 : (int) $timestamp;
}

/** Sort a known set of related IDs by availability, then newest publication date. */
function jluxe_sort_related_product_ids( array $product_ids ): array {
	$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
	$sort_keys   = array();

	foreach ( $product_ids as $product_id ) {
		$product                 = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		$sort_keys[ $product_id ] = array(
			'availability' => jluxe_suggested_is_available( $product ) ? 0 : 1,
			'date'         => jluxe_related_product_date_timestamp( $product_id, $product ),
		);
	}

	usort(
		$product_ids,
		static function ( int $left, int $right ) use ( $sort_keys ): int {
			$availability_order = $sort_keys[ $left ]['availability'] <=> $sort_keys[ $right ]['availability'];
			if ( 0 !== $availability_order ) {
				return $availability_order;
			}

			$date_order = $sort_keys[ $right ]['date'] <=> $sort_keys[ $left ]['date'];
			return 0 !== $date_order ? $date_order : ( $right <=> $left );
		}
	);

	return $product_ids;
}

/**
 * Rank product-page related products by one consistent rule: genuinely available first,
 * then newest-to-oldest. Manual selections define the candidate set; automatic suggestions
 * use same-category candidates before WooCommerce's fallbacks, but every final candidate is
 * sorted by the same availability/date rule before WooCommerce applies its display limit.
 */
function jluxe_order_related_products( $related_posts, $product_id, $args = array() ): array {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return is_array( $related_posts ) ? $related_posts : array();
	}

	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return is_array( $related_posts ) ? $related_posts : array();
	}

	$excluded_ids   = isset( $args['excluded_ids'] ) && is_array( $args['excluded_ids'] ) ? array_map( 'absint', $args['excluded_ids'] ) : array();
	$excluded_ids[] = $product_id;
	$excluded       = array_fill_keys( $excluded_ids, true );

	$manual_ids = get_post_meta( $product_id, '_jluxe_related_product_ids', true );
	if ( is_array( $manual_ids ) && ! empty( $manual_ids ) ) {
		$ordered_manual = array();
		foreach ( $manual_ids as $manual_id ) {
			$manual_id = absint( $manual_id );
			if ( ! $manual_id || isset( $excluded[ $manual_id ] ) || in_array( $manual_id, $ordered_manual, true ) ) {
				continue;
			}

			$manual_product = wc_get_product( $manual_id );
			if ( jluxe_related_product_is_public( $manual_product ) ) {
				$ordered_manual[] = $manual_id;
			}
		}

		if ( ! empty( $ordered_manual ) ) {
			return jluxe_sort_related_product_ids( $ordered_manual );
		}
	}

	$current_product = wc_get_product( $product_id );
	$category_ids    = $current_product instanceof WC_Product
		? ( method_exists( $current_product, 'get_category_ids' ) ? $current_product->get_category_ids() : array() )
		: array();
	$category_ids    = array_values( array_unique( array_filter( array_map( 'absint', (array) $category_ids ) ) ) );
	$category_slugs  = jluxe_related_category_slugs( $category_ids );
	$limit           = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : max( 2, min( 8, (int) jluxe_get_setting( 'product_page.related_count', 4 ) ) );
	$pools           = jluxe_related_category_stock_pools( $category_slugs, $product_id, array_keys( $excluded ), $limit );
	$ordered         = array_merge( $pools['available'], $pools['unavailable'] );

	foreach ( is_array( $related_posts ) ? $related_posts : array() as $related_post_id ) {
		$related_id = absint( $related_post_id );
		if ( ! $related_id || isset( $excluded[ $related_id ] ) ) {
			continue;
		}

		$related_product = wc_get_product( $related_id );
		if ( jluxe_related_product_is_public( $related_product ) ) {
			$ordered[] = $related_id;
		}
	}

	$unique = array();
	foreach ( $ordered as $related_id ) {
		$related_id = absint( $related_id );
		if ( $related_id && ! isset( $excluded[ $related_id ] ) && ! in_array( $related_id, $unique, true ) ) {
			$unique[] = $related_id;
		}
	}

	return jluxe_sort_related_product_ids( $unique );
}

/**
 * R85 — اسکریپتِ اسلایدرِ «محصولات مرتبط» (یک ردیف با فلشِ قبلی/بعدی).
 *
 * فقط در صفحهٔ محصول بارگذاری می‌شود، فایلِ مستقل و بدونِ وابستگی است (بدونِ
 * jQuery/فریم‌ورک) و نسخه‌اش از زمانِ تغییرِ خودِ فایل می‌آید تا کشِ مرورگر
 * پس از هر آپدیت تازه شود. اگر فایل نباشد، هیچ چیز بارگذاری نمی‌شود و ردیف
 * همچنان با اسکرولِ لمسی کار می‌کند.
 */
function jluxe_related_slider_assets(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$file = JLUXE_THEME_DIR . '/assets/js/related-slider.js';
	if ( ! is_readable( $file ) ) {
		return;
	}
	wp_enqueue_script(
		'jluxe-related-slider',
		JLUXE_THEME_URI . '/assets/js/related-slider.js',
		array(),
		(string) filemtime( $file ),
		true
	);
}

/**
 * محصولاتِ پیشنهادیِ پاپ‌آپِ بعدِ افزودن به سبد (صفحه‌ی تکیِ محصول).
 *
 * R72 — بازبینیِ کاملِ شرط‌ها. حالت‌ها (پنل زرین ← اضافه خرید):
 *  - fixed: فقط شناسه‌های انتخاب‌شدهٔ سراسریِ مدیر (محد به خودِ همین فهرست می‌ماند).
 *  - per_product (پیش‌فرض): اول Cross-sells واقعیِ خودِ ووکامرس (ویرایش محصول ←
 *    داده‌های محصول ← محصولات پیوسته — یعنی انتخابِ مدیر برای همان محصول)، بعد
 *    پرکردنِ اسلات‌های خالی با محصولاتِ موجودِ همان دسته، بعد کلِ فروشگاه.
 *  - per_category: همیشه فقط محصولاتِ موجودِ همان دسته.
 *  - random: اتفاقی بین کالاهای موجودِ کلِ فروشگاه.
 *
 * «موجود» (R72) دیگر فقط وضعیتِ والدِ محصول متغیر نیست: is_in_stock() روی
 * WC_Product_Variable فقط متای _stock_status والد را می‌خواند — محصولی که
 * همهٔ تنوع‌هایش ناموجودند ولی والدش «موجود» ثبت شده، قبلاً پیشنهاد می‌شد و
 * مودالِ تنوعِ آن همهٔ سواچ‌هایش بسته بود. حالا برای محصول متغیر باید حداقلِ
 * یک تنوعِ purchasable و در-انبارِ واقعی وجود داشته باشد (کشِ استاتیک در
 * طولِ یک درخواست). کوئری‌هایِ تصادفی هم «visibility=visible» می‌خواهند
 * (کالای مخفی از کاتالوگ پیشنهاد نمی‌شود) و «orderby=rand» — خروجیِ این
 * تابع داخلِ پاسخِ POST افزودن‌به‌سبد رندر می‌شود (غیرقابلِ کشِ صفحه)، پس
 * رندومِ به‌روز مشکلی برای کش ندارد (تصمیمِ قطعیِ R61 با درخواستِ صریحِ
 * کاربر در R72 به رندومِ بینِ کالاهای موجود تغییر کرد). هر گزینه‌ای بعد از
 * کوئری دوباره با همین شرطِ سخت‌گیرانه فیلتر می‌شود (استخرِ بزرگ‌تر از limit
 * کشیده می‌شود تا فیلتر بعد از limit اسلات هدر ندهد — باگِ واقعیِ نسخهٔ قبل).
 */
function jluxe_suggested_is_available( $product ): bool {
	if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
		return false;
	}
	static $cache = array();
	$id = $product->get_id();
	if ( array_key_exists( $id, $cache ) ) {
		return (bool) $cache[ $id ];
	}
	if ( $product->is_type( 'variable' ) ) {
		$available = false;
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation && $variation->is_purchasable() && $variation->is_in_stock() ) {
				$available = true;
				break;
			}
		}
	} else {
		$available = $product->is_in_stock();
	}
	$cache[ $id ] = $available;
	return $available;
}

/**
 * استخرِ اتفاقیِ کالاهای موجود — برایِ پرکردنِ اسلات‌های پیشنهاد.
 * stock_status=instock در کوئری فقط وضعیتِ والد را می‌بیند؛ به همین دلیل
 * استخر کمی بزرگ‌تر از نیاز کشیده می‌شود و هر گزینه دوباره با
 * jluxe_suggested_is_available (بررسیِ تنوعِ واقعی) فیلتر می‌شود.
 */
function jluxe_suggested_random_pool( array $exclude_ids, array $category_ids = array(), int $pool_size = 12 ): array {
	$args = array(
		'status'       => 'publish',
		'visibility'   => 'visible',
		'exclude'      => array_map( 'absint', $exclude_ids ),
		'stock_status' => 'instock',
		'orderby'      => 'rand',
		'limit'        => max( 1, $pool_size ),
		'return'       => 'objects',
	);
	if ( ! empty( $category_ids ) ) {
		$args['category'] = array_map( 'jluxe_term_id_to_slug_product_cat', $category_ids );
	}
	$found = wc_get_products( $args );
	$out   = array();
	foreach ( (array) $found as $candidate ) {
		if ( jluxe_suggested_is_available( $candidate ) ) {
			$out[] = $candidate;
		}
	}
	return $out;
}

function jluxe_get_suggested_products_for_cart( WC_Product $product, int $limit = 4 ): array {
	$limit = max( 1, min( 4, $limit ) );
	$mode  = (string) jluxe_get_setting( 'purchase_addons.mode', 'per_product' );
	if ( ! in_array( $mode, array( 'fixed', 'per_product', 'per_category', 'random' ), true ) ) {
		$mode = 'per_product';
	}

	// پیشنهادِ کالایی که همین حالا در سبد است، دوباره نمایش داده نمی‌شود.
	// WooCommerce برای سطرِ تنوع هم product_id والد را نگه می‌دارد؛ فالبکِ
	// data هم برای cart itemهای سفارشی/آزمون‌ها پوشش داده شده است.
	$seen = array( $product->get_id() => true );
	if ( function_exists( 'WC' ) && WC()->cart && method_exists( WC()->cart, 'get_cart' ) ) {
		foreach ( (array) WC()->cart->get_cart() as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}
			$cart_product_id = absint( $cart_item['product_id'] ?? 0 );
			if ( ! $cart_product_id && ( $cart_item['data'] ?? null ) instanceof WC_Product ) {
				$cart_product_id = (int) ( $cart_item['data']->get_parent_id() ?: $cart_item['data']->get_id() );
			}
			if ( $cart_product_id > 0 ) {
				$seen[ $cart_product_id ] = true;
			}
		}
	}
	$out  = array();
	$take = static function ( array $candidates ) use ( &$out, &$seen, $limit ): bool {
		foreach ( $candidates as $candidate ) {
			$pid = $candidate instanceof WC_Product ? $candidate->get_id() : 0;
			if ( ! $pid || isset( $seen[ $pid ] ) || ! jluxe_suggested_is_available( $candidate ) ) {
				continue;
			}
			$seen[ $pid ] = true;
			$out[]        = $candidate;
			if ( count( $out ) >= $limit ) {
				return true;
			}
		}
		return false;
	};

	if ( 'fixed' === $mode ) {
		// حالتِ ثابت: فقط و فقط فهرستِ مدیر — اسلاتِ خالی با جایگزین پر نمی‌شود.
		$picked = array();
		foreach ( (array) jluxe_get_setting( 'purchase_addons.fixed_ids', array() ) as $fixed_id ) {
			$fixed = wc_get_product( absint( $fixed_id ) );
			if ( $fixed ) {
				$picked[] = $fixed;
			}
		}
		$take( $picked );
		return $out;
	}

	if ( 'random' === $mode ) {
		// R72: حالتِ اتفاقی — بینِ کالاهای موجودِ کلِ فروشگاه.
		$take( jluxe_suggested_random_pool( array_keys( $seen ), array(), $limit * 4 ) );
		return $out;
	}

	if ( 'per_product' === $mode ) {
		// ① انتخابِ مدیر برای همین محصول (فیلدِ رسمیِ Cross-sells ووکامرس).
		// اگر مدیر برای این محصول چیزی انتخاب کرده باشد، «همان» نهایی است —
		// گزینه‌های از-دست-رفته (ناموجود/مخفی) حذف می‌شوند ولی با پیشنهادِ
		// تصادفی رقیق نمی‌شوند؛ fallback فقط وقتی است که چیزی انتخاب نشده باشد.
		$cross = array();
		foreach ( (array) $product->get_cross_sell_ids() as $cross_id ) {
			$cross_product = wc_get_product( absint( $cross_id ) );
			if ( $cross_product ) {
				$cross[] = $cross_product;
			}
		}
		if ( ! empty( $cross ) ) {
			$take( $cross );
			return $out;
		}
	}

	// ② (per_product بدونِ cross-sell) همان دسته ③ (per_product) کلِ
	//    فروشگاه / (per_category) فقط همان دسته.
	$category_ids = $product->get_category_ids();
	if ( ! empty( $category_ids ) ) {
		if ( $take( jluxe_suggested_random_pool( array_keys( $seen ), $category_ids, $limit * 4 ) ) ) {
			return $out;
		}
	}
	if ( 'per_product' === $mode ) {
		$take( jluxe_suggested_random_pool( array_keys( $seen ), array(), $limit * 4 ) );
	}
	return $out;
}

/**
 * wc_get_products()['category'] آرگومان اسلاگِ ترم می‌خواد، نه ID —
 * get_category_ids() ولی ID برمی‌گردونه؛ این تبدیلِ کوچیک همون‌جا لازمه.
 */
function jluxe_term_id_to_slug_product_cat( int $term_id ): string {
	$term = get_term( $term_id, 'product_cat' );
	return ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
}

/**
 * خدماتِ «اضافه خرید» از تنظیمات — با کلیدِ پایدارِ s0..s7 (ایندکس) تا
 * کلاینت فقط کلید را بفرستد و مبلغ/عنوان همیشه از سرور خوانده شود.
 */
function jluxe_pa_services(): array {
	$out = array();
	foreach ( (array) jluxe_get_setting( 'purchase_addons.services', array() ) as $i => $service ) {
		if ( ! is_array( $service ) || '' === (string) ( $service['title'] ?? '' ) ) {
			continue;
		}
		$out[ 's' . $i ] = array(
			'title'   => (string) $service['title'],
			'amount'  => (float) ( $service['amount'] ?? 0 ),
			'context' => in_array( (string) ( $service['context'] ?? 'modal' ), array( 'modal', 'cart', 'both' ), true ) ? (string) $service['context'] : 'modal',
			'auto'    => ! empty( $service['auto'] ),
		);
	}
	return $out;
}

/**
 * فِیِ خدماتِ انتخاب‌شده — کلاینت فقط «کلید» خدمات را POST می‌کند؛ مبلغ و
 * عنوان در همین‌جا از تنظیماتِ ذخیره‌شده خوانده می‌شود (بدونِ اعتماد به
 * POST). فِی در session سبد ثبت می‌شود و روی هر محاسبهٔ totals از هوکِ
 * رسمیِ ووکامرس اعمال می‌گردد؛ خالی‌شدنِ سبد، session را هم پاک می‌کند.
 */
function jluxe_pa_session_service_keys(): array {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return array();
	}
	$stored = WC()->session->get( 'jluxe_pa_services' );
	return is_array( $stored ) ? $stored : array();
}

function jluxe_pa_apply_service_fees(): void {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
		return;
	}
	$services = jluxe_pa_services();
	foreach ( jluxe_pa_session_service_keys() as $key ) {
		if ( isset( $services[ (string) $key ] ) ) {
			$service = $services[ (string) $key ];
			WC()->cart->add_fee( $service['title'], $service['amount'], false );
		}
	}
}

function jluxe_pa_clear_service_fees(): void {
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'jluxe_pa_services', array() );
	}
}

/**
 * پاپ‌آپِ «اضافه خرید» — بعدِ کلیکِ موفقِ افزودن به سبد روی دکمه‌ی
 * اصلیِ صفحه‌ی تکی محصول با JS باز می‌شود (assets/js/woocommerce.js).
 * مطابقِ نمونهٔ مرجع: سربرگِ «افزودن به سبد خرید»، کارتِ خلاصهٔ محصول با
 * قیمتِ خط‌خورده/درصدِ تخفیف، بخشِ «این محصولات را هم اضافه کنید» با
 * ردیف‌های انتخابی (چک‌باکس)، خدماتِ قابل‌انتخاب، و پایین‌برگِ «مبلغ قابل
 * پرداخت» + دکمهٔ تأیید. انتخاب‌ها سمتِ مرورگر جمع می‌شوند و تأیید، فقط
 * کلیدِ خدمات + شناسهٔ محصولات را به endpointهای واقعی می‌فرستد.
 */
function jluxe_suggested_modal_html_for( WC_Product $product ): string {
	// کلیدِ سراسریِ «فعال‌سازی آیتم‌های اضافه خرید» (پنل زرین ← اضافه خرید).
	if ( ! jluxe_get_setting( 'purchase_addons.enabled', false ) ) {
		return '';
	}
	// چک‌باکسِ «پاپ‌آپ محصولات پیشنهادی» توی تبِ عمومیِ ویرایشِ همین محصول
	// (jluxe_render_suggested_modal_toggle_field) — پیش‌فرض روشنه، فقط
	// مقدارِ صریحِ 'no' خاموشش می‌کند.
	if ( 'no' === get_post_meta( $product->get_id(), '_jluxe_suggested_modal_enabled', true ) ) {
		return '';
	}

	$show_products = (bool) jluxe_get_setting( 'purchase_addons.show_products', true );
	$show_services = (bool) jluxe_get_setting( 'purchase_addons.show_services', false );
	$max_products  = max( 1, min( 4, (int) jluxe_get_setting( 'purchase_addons.max_products', 4 ) ) );
	$show_main     = (bool) jluxe_get_setting( 'purchase_addons.show_main_product', false );
	$show_summary  = (bool) jluxe_get_setting( 'purchase_addons.show_cart_summary', false );
	$show_cart     = (bool) jluxe_get_setting( 'purchase_addons.show_cart_link', false );
	$show_continue = (bool) jluxe_get_setting( 'purchase_addons.show_continue', false );
	$show_total    = (bool) jluxe_get_setting( 'purchase_addons.show_total', true );

	$suggested = $show_products ? jluxe_get_suggested_products_for_cart( $product, $max_products ) : array();
	$services  = $show_services ? jluxe_pa_services() : array();
	$services  = array_filter( $services, static fn( $service ) => in_array( $service['context'], array( 'modal', 'both' ), true ) );
	$show_empty_state = ! $show_main && empty( $suggested ) && empty( $services );

	// اگر محصولِ اصلی متغیر بوده، خلاصهٔ اختیاریِ کارت از همان variation
	// افزوده‌شده خوانده شود؛ نه از قیمتِ والد/بازهٔ تنوع‌ها.
	$main_product = $product;
	$cart         = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;
	if ( $cart && method_exists( $cart, 'get_cart' ) ) {
		foreach ( (array) $cart->get_cart() as $cart_item ) {
			if ( ! is_array( $cart_item ) || absint( $cart_item['product_id'] ?? 0 ) !== $product->get_id() ) {
				continue;
			}
			if ( ( $cart_item['data'] ?? null ) instanceof WC_Product ) {
				$main_product = $cart_item['data'];
				break;
			}
		}
	}

	$main_amount   = (float) $main_product->get_price( 'edit' );
	$main_regular  = (float) $main_product->get_regular_price( 'edit' );
	$main_discount = 0;
	if ( $main_regular > 0 && $main_amount > 0 && $main_amount < $main_regular ) {
		$main_discount = (int) round( ( $main_regular - $main_amount ) * 100 / $main_regular );
	}

	// مبلغِ پایه از جمعِ فعلیِ سبد می‌آید تا مودال بدونِ کارتِ محصولِ اصلی
	// هم جمعِ معناداری نشان بدهد. کارمزدهای خدماتِ ازقبل‌ثبت‌شده کم می‌شوند؛
	// چون انتخاب‌های این مودال جایگزینِ فهرستِ خدماتِ session خواهند شد.
	$cart_total = $main_amount > 0 ? $main_amount : 0.0;
	$cart_count = 0;
	$old_service_total = 0.0;
	if ( $cart && method_exists( $cart, 'get_total' ) ) {
		$cart_total = (float) $cart->get_total( 'edit' );
		if ( method_exists( $cart, 'get_cart_contents_count' ) ) {
			$cart_count = (int) $cart->get_cart_contents_count();
		}
		if ( $show_services && ! empty( $services ) && isset( WC()->session ) && WC()->session ) {
			$known_services = jluxe_pa_services();
			foreach ( jluxe_pa_session_service_keys() as $service_key ) {
				if ( isset( $known_services[ (string) $service_key ] ) && in_array( $known_services[ (string) $service_key ]['context'], array( 'modal', 'both' ), true ) ) {
					$old_service_total += (float) $known_services[ (string) $service_key ]['amount'];
				}
			}
		}
	}
	$total_base = max( 0, $cart_total - $old_service_total );
	$unit       = jluxe_currency_label( get_woocommerce_currency() );
	$cart_url   = $show_cart && function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';

	$modal_title            = (string) jluxe_get_setting( 'purchase_addons.modal_title', 'افزودن به سبد خرید' );
	$products_heading       = (string) jluxe_get_setting( 'purchase_addons.products_heading', 'ممکن است این‌ها را هم لازم داشته باشید' );
	if ( 'این محصولات را هم اضافه کنید' === trim( $products_heading ) ) {
		$products_heading = 'ممکن است این‌ها را هم لازم داشته باشید';
	}
	$services_heading       = (string) jluxe_get_setting( 'purchase_addons.services_heading', 'این خدمات را هم اضافه کنید' );
	$total_label            = (string) jluxe_get_setting( 'purchase_addons.total_label', 'برآورد مبلغ سبد' );
	if ( 'مبلغ قابل پرداخت' === trim( $total_label ) ) { $total_label = 'برآورد مبلغ سبد'; }
	$confirm_selected_label = (string) jluxe_get_setting( 'purchase_addons.confirm_selected_label', 'افزودن انتخاب‌ها به سبد' );
	$confirm_empty_label    = (string) jluxe_get_setting( 'purchase_addons.confirm_empty_label', 'ادامه بدون افزودن' );
	$cart_summary_label     = (string) jluxe_get_setting( 'purchase_addons.cart_summary_label', 'سبد شما' );
	$view_cart_label        = (string) jluxe_get_setting( 'purchase_addons.view_cart_label', 'مشاهده سبد' );
	$continue_label         = (string) jluxe_get_setting( 'purchase_addons.continue_label', 'ادامه خرید' );
	$auto_close_enabled     = (bool) jluxe_get_setting( 'purchase_addons.auto_close_enabled', false );
	$auto_close_seconds     = max( 3, min( 300, absint( jluxe_get_setting( 'purchase_addons.auto_close_seconds', 15 ) ) ) );

	// Null means first use; an explicitly stored empty array is a customer choice.
	$stored_service_keys = function_exists( 'WC' ) && isset( WC()->session ) && WC()->session ? WC()->session->get( 'jluxe_pa_services' ) : null;
	foreach ( $services as $key => &$service ) {
		$service['selected'] = is_array( $stored_service_keys ) ? in_array( $key, $stored_service_keys, true ) : ! empty( $service['auto'] );
	}
	unset( $service );
	$initial_selection_count = 0;
	foreach ( $services as $service ) {
		if ( ! empty( $service['selected'] ) ) {
			++$initial_selection_count;
		}
	}

	ob_start();
	?>
	<div class="jluxe-pa fixed inset-0 hidden" data-jluxe-suggested-modal aria-hidden="true" data-pa-context="<?php echo esc_attr( (string) $product->get_id() ); ?>" data-pa-unit="<?php echo esc_attr( $unit ); ?>" data-pa-main="<?php echo esc_attr( (string) $total_base ); ?>" data-pa-auto-close="<?php echo $auto_close_enabled ? '1' : '0'; ?>" data-pa-auto-close-seconds="<?php echo esc_attr( (string) $auto_close_seconds ); ?>">
		<div class="jluxe-pa-backdrop" data-jluxe-suggested-close></div>
		<div class="jluxe-pa-sheet" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $modal_title ); ?>">
			<header class="jluxe-pa-head">
				<span class="jluxe-pa-head-ic" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 8.5a2 2 0 0 1-2 1.5H8.6a2 2 0 0 1-2-1.6L4.6 3.7A1 1 0 0 0 3.6 3H2"/><circle cx="9.5" cy="20" r="1.5"/><circle cx="17.5" cy="20" r="1.5"/></svg>
				</span>
				<span class="jluxe-pa-head-title"><?php echo esc_html( $modal_title ); ?></span>
				<button type="button" data-jluxe-suggested-close aria-label="بستن" class="jluxe-pa-close">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</header>

			<div class="jluxe-pa-body">
				<?php if ( $show_empty_state ) : ?>
					<div class="jluxe-pa-empty" role="status">در حال حاضر پیشنهاد محصول یا خدمت دیگری برای نمایش وجود ندارد.</div>
				<?php endif; ?>
				<?php if ( $show_main ) : ?>
					<div class="jluxe-pa-main">
						<span class="jluxe-pa-thumb">
							<?php $jluxe_pa_thumb = wp_get_attachment_image_url( $main_product->get_image_id(), 'thumbnail' ); ?>
							<?php if ( $jluxe_pa_thumb ) : ?>
								<img src="<?php echo esc_url( $jluxe_pa_thumb ); ?>" alt="<?php echo esc_attr( $main_product->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
							<?php endif; ?>
						</span>
						<span class="jluxe-pa-maininfo">
							<span class="jluxe-pa-mainname"><?php echo esc_html( $main_product->get_name() ); ?></span>
							<span class="jluxe-pa-mainprice">
								<?php if ( $main_discount > 0 ) : ?>
									<del><?php echo wp_kses_post( wc_price( $main_regular ) ); ?></del>
									<span class="jluxe-pa-badge"><?php echo esc_html( jluxe_fa_digits( $main_discount ) ); ?>٪</span>
									<span class="jluxe-pa-now"><?php echo wp_kses_post( wc_price( $main_amount ) ); ?></span>
								<?php else : ?>
									<span class="jluxe-pa-now"><?php echo wp_kses_post( $main_product->get_price_html() ); ?></span>
								<?php endif; ?>
							</span>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $services ) ) : ?>
					<div class="jluxe-pa-secthead">
						<span><?php echo esc_html( $services_heading ); ?></span>
						<small>اختیاری</small>
					</div>
					<div class="jluxe-pa-rows">
						<?php foreach ( $services as $jluxe_pa_key => $jluxe_pa_service ) : ?>
							<button type="button" class="jluxe-pa-row<?php echo $jluxe_pa_service['selected'] ? ' is-selected' : ''; ?>" data-pa-service="<?php echo esc_attr( $jluxe_pa_key ); ?>" data-pa-amount="<?php echo esc_attr( (string) $jluxe_pa_service['amount'] ); ?>" aria-pressed="<?php echo $jluxe_pa_service['selected'] ? 'true' : 'false'; ?>">
								<span class="jluxe-pa-rowinfo">
									<span class="jluxe-pa-name"><?php echo esc_html( $jluxe_pa_service['title'] ); ?></span>
									<span class="jluxe-pa-price"><?php echo wp_kses_post( wc_price( $jluxe_pa_service['amount'] ) ); ?></span>
								</span>
								<span class="jluxe-pa-check" aria-hidden="true">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $suggested ) ) : ?>
					<div class="jluxe-pa-secthead">
						<span><?php echo esc_html( $products_heading ); ?></span>
						<small>اختیاری</small>
					</div>
					<div class="jluxe-pa-rows">
						<?php foreach ( $suggested as $sp ) : ?>
							<?php
							$sp_simple = ! $sp->is_type( 'variable' ) && ! $sp->is_type( 'grouped' ) && ! $sp->is_type( 'external' );
							$sp_amount = (float) $sp->get_price( 'edit' );
							$sp_thumb  = wp_get_attachment_image_url( $sp->get_image_id(), 'thumbnail' );
							?>
							<?php if ( $sp_simple && $sp_amount > 0 ) : ?>
								<button type="button" class="jluxe-pa-row" data-pa-product="<?php echo esc_attr( (string) $sp->get_id() ); ?>" data-pa-amount="<?php echo esc_attr( (string) $sp_amount ); ?>" aria-pressed="false">
									<span class="jluxe-pa-thumb">
										<?php if ( $sp_thumb ) : ?>
											<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
										<?php endif; ?>
									</span>
									<span class="jluxe-pa-rowinfo">
										<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
										<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
									</span>
									<span class="jluxe-pa-check" aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
									</span>
								</button>
							<?php else : ?>
								<?php if ( $sp->is_type( 'variable' ) ) : ?>
									<button type="button" class="jluxe-pa-row jluxe-pa-rowlink" data-jluxe-quick-variant="<?php echo esc_attr( (string) $sp->get_id() ); ?>">
										<span class="jluxe-pa-thumb">
											<?php if ( $sp_thumb ) : ?>
												<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
											<?php endif; ?>
										</span>
										<span class="jluxe-pa-rowinfo">
											<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
											<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
										</span>
										<span class="jluxe-pa-goto">انتخاب گزینه‌ها</span>
									</button>
								<?php else : ?>
									<a class="jluxe-pa-row jluxe-pa-rowlink" href="<?php echo esc_url( $sp->get_permalink() ); ?>">
										<span class="jluxe-pa-thumb">
											<?php if ( $sp_thumb ) : ?>
												<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="<?php echo esc_attr( $sp->get_name() ); ?>" <?php echo jluxe_lazy_attr(); ?> />
											<?php endif; ?>
										</span>
										<span class="jluxe-pa-rowinfo">
											<span class="jluxe-pa-name"><?php echo esc_html( $sp->get_name() ); ?></span>
											<span class="jluxe-pa-price"><?php echo wp_kses_post( $sp->get_price_html() ); ?></span>
										</span>
										<span class="jluxe-pa-goto">مشاهدهٔ محصول</span>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<footer class="jluxe-pa-foot">
				<?php $show_foot_top = ( $show_summary && $cart_count > 0 ) || ( $show_cart && '' !== $cart_url ) || $show_continue; ?>
				<?php if ( $show_foot_top ) : ?>
					<div class="jluxe-pa-foot-top">
						<?php if ( $show_summary && $cart_count > 0 ) : ?>
							<span class="jluxe-pa-cartinfo"><?php echo esc_html( $cart_summary_label ); ?>: <?php echo esc_html( jluxe_fa_digits( $cart_count ) ); ?> کالا · <?php echo esc_html( jluxe_fa_digits( number_format( $cart_total, 0, '.', ',' ) ) ); ?> <?php echo esc_html( $unit ); ?></span>
						<?php endif; ?>
						<span class="jluxe-pa-foot-links">
							<?php if ( $show_cart && '' !== $cart_url ) : ?>
								<a class="jluxe-pa-viewcart" href="<?php echo esc_url( $cart_url ); ?>"><?php echo esc_html( $view_cart_label ); ?></a>
							<?php endif; ?>
							<?php if ( $show_continue ) : ?>
								<button type="button" class="jluxe-pa-continue" data-jluxe-suggested-close><?php echo esc_html( $continue_label ); ?></button>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
				<div class="jluxe-pa-foot-main">
					<?php if ( $show_total ) : ?>
						<span class="jluxe-pa-totalwrap">
							<small><?php echo esc_html( $total_label ); ?></small>
							<small data-pa-estimate-note>این مبلغ برآوردی است؛ مبلغ نهایی با مالیات، ارسال و تخفیف‌ها در سبد محاسبه می‌شود.</small>
							<span class="jluxe-pa-totalrow">
								<strong class="jluxe-pa-total" data-pa-total><?php echo esc_html( jluxe_fa_digits( number_format( $total_base, 0, '.', ',' ) ) ); ?></strong>
								<small><?php echo esc_html( $unit ); ?></small>
							</span>
						</span>
					<?php endif; ?>
					<div class="jluxe-pa-action-row">
						<button type="button" class="jluxe-pa-confirm" data-pa-confirm data-pa-confirm-selected="<?php echo esc_attr( $confirm_selected_label ); ?>" data-pa-confirm-empty="<?php echo esc_attr( $confirm_empty_label ); ?>">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
							<span data-pa-confirm-label><?php echo esc_html( $initial_selection_count > 0 ? $confirm_selected_label : $confirm_empty_label ); ?></span>
						</button>
						<?php if ( $auto_close_enabled ) : ?>
							<span class="jluxe-pa-countdown" data-pa-countdown hidden role="timer" aria-live="off">
								<span class="sr-only">زمان باقی‌مانده تا بسته‌شدن پیشنهادها</span>
								<strong class="jluxe-pa-countdown-value" data-pa-countdown-value dir="ltr"><?php echo esc_html( jluxe_fa_digits( sprintf( '%02d:%02d', intdiv( $auto_close_seconds, 60 ), $auto_close_seconds % 60 ) ) ); ?></strong>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</footer>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** چاپِ مودال در تمپلیت‌ها — API قدیمی حفظ شد (R61: builder جدا شد تا endpoint هم از همان HTML استفاده کند). */
function jluxe_render_suggested_products_modal( WC_Product $product ): void {
	$html = jluxe_suggested_modal_html_for( $product );
	if ( '' !== $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزای داخلی escape شده‌اند.
	}
}

/**
 * تنظیمات «فروشگاه و دسته‌بندی» (jluxe_get_theme_settings()['shop']) —
 * تعداد محصول در صفحه و تعداد ستون از قلاب‌های واقعی خودِ ووکامرس رد می‌شن،
 * نه یک shortcode/کوئری موازی.
 */
function jluxe_shop_per_page( $per_page ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName, Squiz.Commenting.FunctionComment
	return (int) jluxe_get_setting( 'shop.products_per_page', $per_page );
}

/**
 * Return true when an AND/OR meta_query can only match products with _stock_status=instock.
 * This lets the stock ordering filter avoid an extra postmeta join and a constant CASE for
 * product lists that already exclude every out-of-stock product.
 */
function jluxe_meta_query_requires_in_stock( array $meta_query ): bool {
	if ( isset( $meta_query['key'] ) ) {
		$key     = (string) $meta_query['key'];
		$compare = strtoupper( (string) ( $meta_query['compare'] ?? '=' ) );
		$value   = $meta_query['value'] ?? null;
		return '_stock_status' === $key
			&& ( '=' === $compare && 'instock' === $value || 'IN' === $compare && array( 'instock' ) === (array) $value );
	}

	$relation = strtoupper( (string) ( $meta_query['relation'] ?? 'AND' ) );
	$children = array();
	foreach ( $meta_query as $key => $clause ) {
		if ( 'relation' !== $key && is_array( $clause ) ) {
			$children[] = $clause;
		}
	}
	if ( empty( $children ) ) {
		return false;
	}

	if ( 'OR' === $relation ) {
		foreach ( $children as $child ) {
			if ( ! jluxe_meta_query_requires_in_stock( $child ) ) {
				return false;
			}
		}
		return true;
	}

	foreach ( $children as $child ) {
		if ( jluxe_meta_query_requires_in_stock( $child ) ) {
			return true;
		}
	}
	return false;
}

/**
 * R64/R337: stock-first ordering for product queries. The main shop and product-taxonomy
 * archives (category, brand and attribute) always use availability, then post_date DESC;
 * this is deliberately independent of the legacy toggle. Detect archives from the current
 * WP_Query's parsed flags: global WooCommerce conditional tags can still point at the old
 * query while posts_clauses is building the main brand/archive query.
 * The CASE reads WooCommerce's product-meta lookup stock status (with a postmeta fallback
 * for older/custom stores), so a restocked product moves into the in-stock group automatically.
 * Other product queries retain the prior optional stock grouping and their own order (for
 * example homepage/popular/random rows). If a query already requires in-stock products, its
 * stock CASE is constant and the extra join is skipped. Missing status remains in-stock.
 */
function jluxe_out_of_stock_last_clauses( array $clauses, WP_Query $query ): array {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $clauses;
	}

	$post_type        = $query->get( 'post_type' );
	$is_product_query = 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
	$is_main_query    = $query->is_main_query();
	$product_taxonomies = function_exists( 'get_object_taxonomies' ) ? get_object_taxonomies( 'product', 'names' ) : array();
	$is_catalog_archive = $is_main_query
		&& (
			$query->is_post_type_archive( 'product' ) ||
			( ! empty( $product_taxonomies ) && $query->is_tax( $product_taxonomies ) )
		);

	// Product tax archives are authoritative even if a custom WooCommerce setup has
	// not populated post_type on the query yet. All other queries must explicitly
	// include product so the legacy optional ordering never touches unrelated posts.
	if ( ! $is_product_query && ! $is_catalog_archive ) {
		return $clauses;
	}
	if ( ! $is_catalog_archive && ! jluxe_get_setting( 'shop.out_of_stock_last', true ) ) {
		return $clauses;
	}

	$meta_query_requires_in_stock = jluxe_meta_query_requires_in_stock( (array) $query->get( 'meta_query' ) );
	// A secondary list restricted to in-stock products is already correctly grouped;
	// preserve its requested order (including RAND()) without another postmeta join.
	if ( ! $is_catalog_archive && $meta_query_requires_in_stock ) {
		return $clauses;
	}

	global $wpdb;
	$stock_lookup_table  = isset( $wpdb->wc_product_meta_lookup ) ? (string) $wpdb->wc_product_meta_lookup : '';
	$stock_status_column = '' !== $stock_lookup_table ? 'jluxe_oos.stock_status' : 'jluxe_oos.meta_value';
	if ( ! $meta_query_requires_in_stock && false === strpos( (string) $clauses['join'], ' jluxe_oos ' ) ) {
		if ( '' !== $stock_lookup_table ) {
			// WooCommerce maintains this one-row-per-product lookup table; prefer it
			// to scanning postmeta for the stock sort, with a legacy fallback below.
			$clauses['join'] .= " LEFT JOIN {$stock_lookup_table} AS jluxe_oos ON ({$wpdb->posts}.ID = jluxe_oos.product_id)";
		} else {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS jluxe_oos ON ({$wpdb->posts}.ID = jluxe_oos.post_id AND jluxe_oos.meta_key = '_stock_status')";
		}
	}

	if ( $is_catalog_archive ) {
		// Fixed sort for the requested catalog archives: stock priority, newest first,
		// then ID as a deterministic tie-breaker. Do not let WooCommerce's menu order,
		// popularity, or post__in order override this storefront rule.
		$base = "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
	} else {
		$base = trim( (string) $clauses['orderby'] );
		if ( '' === $base ) {
			$base = "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		}
	}

	$clauses['orderby'] = $meta_query_requires_in_stock
		? $base
		: "CASE WHEN COALESCE( {$stock_status_column}, 'instock' ) = 'outofstock' THEN 1 ELSE 0 END ASC, " . $base;
	return $clauses;
}

function jluxe_shop_columns( $columns ) { // phpcs:ignore Squiz.Commenting.FunctionComment
	return (int) jluxe_get_setting( 'shop.columns_desktop', $columns );
}

/**
 * چیدمانِ گرید واقعیِ کارت‌های محصول — کاملاً مستقل از CSS داخلیِ خودِ
 * ووکامرس. باگِ واقعی‌ای که پیدا شد: content-product.php این تم به‌جای
 * <li> (چیزی که سلکتور رسمی ووکامرس یعنی «.products li.product» انتظارش
 * رو داره) یک <div> با wc_product_class() می‌سازه؛ در نتیجه هیچ CSS
 * float/grid ای از woocommerce.css روی کارت‌ها اعمال نمی‌شه و همه‌ی
 * کارت‌ها تکی و تمام‌عرض (block پیش‌فرض مرورگر) زیر هم می‌افتن. راه‌حل:
 * یک گرید CSS واقعی و مستقل روی خودِ «.products» تعریف می‌کنیم که به تگِ
 * فرزند (li یا div) وابسته نیست — چون در Grid Formatting Context، فرزندان
 * مستقیم به‌صورت خودکار «آیتم گرید» می‌شن و float/width قدیمی‌شون بی‌اثر
 * می‌شه. همه‌جا چاپ می‌شه (نه فقط شاپ) چون همین کلاس «.products» توی
 * محصولات مرتبط/upsell/cross-sell هم استفاده می‌شه.
 */
function jluxe_output_shop_columns_css(): void {
	$desktop = max( 2, min( 6, (int) jluxe_get_setting( 'shop.columns_desktop', 4 ) ) );
	$tablet  = max( 2, min( 4, (int) jluxe_get_setting( 'shop.columns_tablet', 3 ) ) );
	$mobile  = max( 1, min( 3, (int) jluxe_get_setting( 'shop.columns_mobile', 2 ) ) );
	printf(
		'<style id="jluxe-shop-columns">
			ul.products{display:grid!important;grid-template-columns:repeat(%3$d,minmax(0,1fr))!important;gap:1rem!important;list-style:none!important;margin:0!important;padding:0!important}
			ul.products li.product,ul.products>.product{float:none!important;width:auto!important;margin:0!important}
			@media (min-width:600px){ ul.products{grid-template-columns:repeat(%2$d,minmax(0,1fr))!important} }
			@media (min-width:1024px){ ul.products{grid-template-columns:repeat(%1$d,minmax(0,1fr))!important} }
		</style>',
		$desktop,
		$tablet,
		$mobile
	);
}

/** Remove the redundant «مرتب‌سازی بر اساس» prefix from WooCommerce options. */
function jluxe_shop_orderby_labels( array $labels ): array {
	foreach ( $labels as $value => $label ) {
		if ( ! is_string( $label ) ) {
			continue;
		}
		$clean = preg_replace( '/^\\s*مرتب[\\x{200C}\\s]*سازی(?:\\s+بر\\s+اساس)?\\s*[:：]?\\s*/u', '', $label );
		if ( is_string( $clean ) ) {
			$labels[ $value ] = $clean;
		}
	}
	return $labels;
}

/**
 * نوار بالای گرید محصولات («نمایش X از Y نتیجه» + مرتب‌سازی). پیش‌فرضِ
 * ووکامرس این دو هوک رو جدا و تمام‌عرض زیر هم چاپ می‌کنه (فاصله‌ی خالی
 * زیاد در دسکتاپ). این‌جا هر دو رو حذف و در یک ردیف flex کنارِ هم دوباره
 * چاپ می‌کنیم؛ ارقامِ «نمایش X از Y» هم به فارسی تبدیل می‌شن چون خودِ
 * ووکامرس فقط متن رو ترجمه می‌کنه، رقم‌ها رو نه (طبق jluxe_fa_digits بالا).
 */
function jluxe_shop_toolbar_hooks(): void {
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	add_action( 'woocommerce_before_shop_loop', 'jluxe_render_shop_toolbar', 25 );
}

/**
 * فیلترِ «فقط کالاهای موجود» — برخلاف min_price/max_price که خودِ ووکامرس
 * از GET param می‌خونه، وضعیتِ موجودی همچین پارامتری نداره؛ این‌جا با
 * ?filter_stock=instock دستی اضافه‌ش می‌کنیم.
 */
function jluxe_filter_stock_query( WP_Query $query ): void {
	$is_product_listing = $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) );
	if ( is_admin() || ! $query->is_main_query() || ! $is_product_listing ) {
		return;
	}
	if ( ! isset( $_GET['filter_stock'] ) || 'instock' !== $_GET['filter_stock'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$meta_query   = $query->get( 'meta_query' ) ?: array();
	$meta_query[] = array(
		'key'   => '_stock_status',
		'value' => 'instock',
	);
	$query->set( 'meta_query', $meta_query );
}

function jluxe_render_shop_toolbar(): void {
	ob_start();
	if ( function_exists( 'woocommerce_result_count' ) ) {
		woocommerce_result_count();
	}
	$result_count = jluxe_fa_digits( ob_get_clean() );

	ob_start();
	if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
		woocommerce_catalog_ordering();
	}
	$ordering = ob_get_clean();

	// وضعیت فعلیِ فیلترها — برای این‌که پنل موقع بازشدن دقیقاً همون چیزی رو
	// نشون بده که الان واقعاً اعمال شده (نه همیشه خالی).
	$current_cat_id   = 0;
	$current_brand_id = 0;
	$queried          = get_queried_object();
	if ( $queried instanceof WP_Term ) {
		if ( 'product_cat' === $queried->taxonomy ) {
			$current_cat_id = $queried->term_id;
		} elseif ( 'product_brand' === $queried->taxonomy ) {
			$current_brand_id = $queried->term_id;
		}
	}
	foreach ( array( 'product_cat', 'product_brand' ) as $taxonomy ) {
		if ( isset( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) && taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ), $taxonomy );
			if ( $term ) {
				if ( 'product_cat' === $taxonomy ) { $current_cat_id = $term->term_id; }
				else { $current_brand_id = $term->term_id; }
			}
		}
	}
	$current_min_price = isset( $_GET['min_price'] ) && is_scalar( $_GET['min_price'] ) ? wc_format_decimal( wp_unslash( $_GET['min_price'] ) ) : '';
	$current_max_price = isset( $_GET['max_price'] ) && is_scalar( $_GET['max_price'] ) ? wc_format_decimal( wp_unslash( $_GET['max_price'] ) ) : '';
	$current_on_sale = isset( $_GET['on_sale'] ) && '1' === $_GET['on_sale'];

	$current_in_stock  = isset( $_GET['filter_stock'] ) && 'instock' === $_GET['filter_stock']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$cat_terms   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	$cat_terms   = is_wp_error( $cat_terms ) ? array() : $cat_terms;
	$brand_terms = taxonomy_exists( 'product_brand' ) ? get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => true ) ) : array();
	$brand_terms = is_wp_error( $brand_terms ) ? array() : $brand_terms;
	$has_min_price = '' !== $current_min_price;
	$has_max_price = '' !== $current_max_price;
	$price_currency_label = function_exists( 'jluxe_currency_label' ) ? jluxe_currency_label( get_woocommerce_currency() ) : get_woocommerce_currency_symbol();
	$has_filters   = $current_cat_id || $current_brand_id || $has_min_price || $has_max_price || $current_in_stock || $current_on_sale;

	$filter_base_url = function_exists( 'get_pagenum_link' ) ? get_pagenum_link( 1 ) : wc_get_page_permalink( 'shop' );
	$filter_base_url = remove_query_arg( array( 'paged', 'page' ), $filter_base_url );
	$filter_url      = static function ( array $remove_keys = array() ) use ( $filter_base_url ): string {
		return remove_query_arg( array_values( array_unique( array_merge( array( 'paged', 'page' ), $remove_keys ) ) ), $filter_base_url );
	};
	$active_filter_chips = array();
	$add_filter_chip     = static function ( string $label, string $remove_label, array $remove_keys ) use ( &$active_filter_chips, $filter_url ): void {
		$active_filter_chips[] = array(
			'label'        => $label,
			'remove_label' => $remove_label,
			'url'          => $filter_url( $remove_keys ),
		);
	};

	if ( isset( $_GET['product_cat'] ) && is_scalar( $_GET['product_cat'] ) && taxonomy_exists( 'product_cat' ) ) {
		$filter_cat_slug = sanitize_title( wp_unslash( (string) $_GET['product_cat'] ) );
		$filter_cat_term = '' !== $filter_cat_slug ? get_term_by( 'slug', $filter_cat_slug, 'product_cat' ) : false;
		$is_archive_cat  = $queried instanceof WP_Term && 'product_cat' === $queried->taxonomy && $filter_cat_slug === $queried->slug;
		if ( $filter_cat_term && ! is_wp_error( $filter_cat_term ) && ! $is_archive_cat ) {
			$add_filter_chip( 'دسته: ' . (string) $filter_cat_term->name, 'دسته ' . (string) $filter_cat_term->name, array( 'product_cat' ) );
		}
	}
	if ( isset( $_GET['product_brand'] ) && is_scalar( $_GET['product_brand'] ) && taxonomy_exists( 'product_brand' ) ) {
		$filter_brand_slug = sanitize_title( wp_unslash( (string) $_GET['product_brand'] ) );
		$filter_brand_term = '' !== $filter_brand_slug ? get_term_by( 'slug', $filter_brand_slug, 'product_brand' ) : false;
		$is_archive_brand  = $queried instanceof WP_Term && 'product_brand' === $queried->taxonomy && $filter_brand_slug === $queried->slug;
		if ( $filter_brand_term && ! is_wp_error( $filter_brand_term ) && ! $is_archive_brand ) {
			$add_filter_chip( 'برند: ' . (string) $filter_brand_term->name, 'برند ' . (string) $filter_brand_term->name, array( 'product_brand' ) );
		}
	}
	foreach ( array( 'min_price' => array( 'از ', 'حداقل قیمت' ), 'max_price' => array( 'تا ', 'حداکثر قیمت' ) ) as $price_key => $price_labels ) {
		$price_value = 'min_price' === $price_key ? $current_min_price : $current_max_price;
		if ( '' === $price_value ) {
			continue;
		}
		$formatted_price = jluxe_fa_digits( number_format( (float) $price_value, 0, '.', ',' ) );
		$add_filter_chip( $price_labels[0] . $formatted_price . ' ' . $price_currency_label, $price_labels[1], array( $price_key ) );
	}
	if ( $current_on_sale ) {
		$add_filter_chip( 'تخفیف‌دار', 'فقط تخفیف‌دارها', array( 'on_sale' ) );
	}
	if ( $current_in_stock ) {
		$add_filter_chip( 'فقط موجودها', 'فقط موجودها', array( 'filter_stock' ) );
	}

	$clear_filter_keys = array( 'product_cat', 'product_brand', 'min_price', 'max_price', 'on_sale', 'filter_stock' );
	foreach ( array_keys( $_GET ) as $query_key ) {
		$query_key = (string) $query_key;
		if ( 0 === strpos( $query_key, 'filter_' ) || 0 === strpos( $query_key, 'query_type_' ) ) {
			$clear_filter_keys[] = $query_key;
		}
	}
	$clear_filter_url = $filter_url( $clear_filter_keys );
	?>
	<div class="jluxe-shop-toolbar mb-4">
		<button type="button" data-jluxe-filter-toggle class="jluxe-shop-toolbar-filter relative flex h-11 items-center gap-2 rounded-xl border border-border bg-surface px-3 text-caption font-medium text-foreground transition-all hover:bg-muted active:scale-95 sm:px-4">
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
			فیلتر
			<?php if ( $has_filters ) : ?><span class="absolute -end-1 -top-1 size-2.5 rounded-full bg-primary" aria-hidden="true"></span><?php endif; ?>
		</button>
		<div class="jluxe-shop-toolbar-controls">
			<div class="jluxe-shop-toolbar-count text-caption text-text-muted"><?php echo $result_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="jluxe-shop-sort relative inline-flex items-center">
				<?php echo $ordering; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<svg class="pointer-events-none absolute end-3 size-3.5 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $active_filter_chips ) ) : ?>
		<div class="jluxe-active-filters" role="region" aria-label="فیلترهای فعال">
			<?php foreach ( $active_filter_chips as $chip ) : ?>
				<a class="jluxe-filter-chip" href="<?php echo esc_url( $chip['url'] ); ?>" data-jluxe-catalog-filter-chip aria-label="<?php echo esc_attr( 'حذف فیلتر ' . $chip['remove_label'] ); ?>">
					<span><?php echo esc_html( $chip['label'] ); ?></span><span class="jluxe-filter-chip__remove" aria-hidden="true">×</span>
				</a>
			<?php endforeach; ?>
			<?php if ( count( $active_filter_chips ) > 1 ) : ?>
				<a class="jluxe-filter-chip jluxe-filter-chip__clear" href="<?php echo esc_url( $clear_filter_url ); ?>" data-jluxe-catalog-clear aria-label="پاک کردن همهٔ فیلترها">پاک کردن همه</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	/*
	 * پنل فیلتر — یک پاپ‌آپ/مودال واقعی (نه یک جعبه‌ی درازِ inline بالای
	 * گرید). چون jluxe_render_shop_toolbar بیرونِ هدر رندر می‌شه (نه
	 * داخلش)، مشکلِ containing-block ناشیِ از backdrop-filter هدر این‌جا
	 * مطرح نیست — پس نیازی به React portal نداره، یک fixed ساده کافیه.
	 */
	?>
	<?php /* flex/justify-start عمداً استاتیک نیست، JS با باز/بستن اضافه/حذفش می‌کنه — چون Tailwind این کلاس‌ها رو در لایه‌ی utilities تولید می‌کنه که همیشه روی [hidden] (لایه‌ی base) برنده می‌شه، یعنی اگه استاتیک باشن panel با وجود hidden=true همیشه display:flex می‌مونه (باگ واقعی: پاپ‌آپ نه باز می‌شد نه بسته). */ ?>
	<div data-jluxe-filter-panel hidden class="fixed inset-0 jluxe-filter-panel-overlay">
		<div data-jluxe-filter-close class="jluxe-filter-backdrop absolute inset-0 bg-foreground/50 backdrop-blur-[1px]" aria-hidden="true"></div>
		<div data-jluxe-filter-drawer class="jluxe-filter-drawer relative flex h-full w-full max-w-sm flex-col bg-surface shadow-2xl">
			<div class="flex items-center justify-between border-b border-border p-4">
				<button type="button" data-jluxe-filter-close aria-label="بستن" class="grid size-9 place-items-center rounded-lg text-text-muted transition-all hover:bg-muted active:scale-90 active:bg-muted">
					<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
				<h2 class="flex items-center gap-2 text-body font-bold text-foreground">
					فیلترها
					<svg class="size-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
				</h2>
			</div>

			<form data-jluxe-filter-form class="flex flex-1 flex-col overflow-hidden">
				<div class="flex-1 overflow-y-auto px-4">
					<?php if ( $has_filters ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" data-jluxe-catalog-clear class="mt-3 inline-block text-caption font-medium text-primary hover:text-primary-hover">حذف فیلترها</a>
					<?php endif; ?>

					<details class="group border-b border-border py-3.5" open>
						<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
							محدوده قیمت (<?php echo esc_html( jluxe_currency_label( get_woocommerce_currency() ) ); ?>)
							<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</summary>
						<div class="mt-3 flex items-center gap-2">
							<input type="text" inputmode="decimal" aria-label="حداقل قیمت" name="min_price" value="<?php echo esc_attr( $current_min_price ); ?>" placeholder="حداقل" class="h-11 w-full min-w-0 rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none" />
							<span class="shrink-0 text-text-muted">تا</span>
							<input type="text" inputmode="decimal" aria-label="حداکثر قیمت" name="max_price" value="<?php echo esc_attr( $current_max_price ); ?>" placeholder="حداکثر" class="h-11 w-full min-w-0 rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none" />
						</div>
					</details>

					<details class="group border-b border-border py-3.5" <?php echo $current_cat_id ? 'open' : ''; ?>>
						<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
							دسته‌بندی
							<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</summary>
						<select aria-label="دسته‌بندی محصول" name="filter_cat" class="mt-3 h-11 w-full rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none">
							<option value="">همه‌ی دسته‌ها</option>
							<?php foreach ( $cat_terms as $term ) :
								$term_link = get_term_link( $term );
								if ( is_wp_error( $term_link ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" data-url="<?php echo esc_url( $term_link ); ?>" <?php selected( $current_cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</details>

					<?php if ( ! empty( $brand_terms ) ) : ?>
						<details class="group border-b border-border py-3.5" <?php echo $current_brand_id ? 'open' : ''; ?>>
							<summary class="flex cursor-pointer list-none items-center justify-between text-small font-medium text-foreground">
								برند
								<svg class="size-4 text-text-muted transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
							</summary>
							<select aria-label="برند محصول" name="filter_brand" class="mt-3 h-11 w-full rounded-lg border border-border bg-muted px-3 text-small text-foreground focus:border-primary focus:bg-surface focus:outline-none">
								<option value="">همه‌ی برندها</option>
								<?php foreach ( $brand_terms as $term ) :
									$term_link = get_term_link( $term );
									if ( is_wp_error( $term_link ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $term->slug ); ?>" data-url="<?php echo esc_url( $term_link ); ?>" <?php selected( $current_brand_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</details>
					<?php endif; ?>

					<div class="py-3.5">
						<label class="flex h-11 cursor-pointer items-center justify-between rounded-lg border border-border px-3 text-small">
							فقط محصولات تخفیف‌دار
							<input type="checkbox" name="on_sale" value="1" <?php checked( $current_on_sale ); ?> class="size-4 accent-primary" />
						</label>
					</div>
					<div class="py-3.5">
						<label class="flex h-11 cursor-pointer items-center justify-between rounded-lg border border-border px-3 text-small text-foreground transition-colors hover:border-primary/40">
							فقط کالاهای موجود
							<input type="checkbox" name="filter_stock" value="instock" <?php checked( $current_in_stock ); ?> class="size-4 accent-primary" />
						</label>
					</div>
				</div>

				<div class="border-t border-border p-4">
					<button type="submit" class="flex h-12 w-full items-center justify-center rounded-xl bg-primary text-button font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.98]">اعمال فیلترها</button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

/** Apply the sale flag to the main WooCommerce product query, not unrelated queries. */
function jluxe_filter_sale_query( $query ): void {
	if ( is_admin() || ! isset( $_GET['on_sale'] ) || '1' !== $_GET['on_sale'] ) {
		return;
	}
	$sale_ids = array_map( 'absint', wc_get_product_ids_on_sale() );
	$existing = $query->get( 'post__in' );
	$ids = $existing ? array_values( array_intersect( (array) $existing, $sale_ids ) ) : $sale_ids;
	$query->set( 'post__in', $ids ?: array( 0 ) );
}
