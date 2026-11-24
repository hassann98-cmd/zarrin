<?php
defined( 'ABSPATH' ) || exit;

/** Homepage product-section renderers and WooCommerce queries. */

/** A featured homepage card is eligible only when WooCommerce reports it truly in stock. */
function jluxe_homepage_featured_product_is_available( $product ): bool {
	return $product instanceof WC_Product &&
		method_exists( $product, 'get_stock_status' ) &&
		method_exists( $product, 'is_in_stock' ) &&
		'instock' === $product->get_stock_status() &&
		$product->is_in_stock();
}

/** Shared heading/action row for homepage product and editorial sections. */
function jluxe_render_homepage_section_header( string $title, string $url, string $label, string $subtitle = '', bool $inverse = false ): void {
	if ( '' === trim( $title ) ) {
		return;
	}
	$heading_color = $inverse ? 'text-white' : 'text-foreground';
	$action_style  = $inverse ? 'border-white/40 text-white' : 'border-border text-foreground';
	$inverse_class = $inverse ? ' jluxe-home-section-view-all--inverse' : '';
	?>
	<div class="jluxe-home-section-heading mb-4 flex items-center justify-between gap-3">
		<div class="min-w-0">
			<h2 class="text-h2 <?php echo esc_attr( $heading_color ); ?>"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== trim( $subtitle ) ) : ?><p class="mt-1 text-small <?php echo $inverse ? 'text-white/80' : 'text-text-muted'; ?>"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
		</div>
		<?php if ( '' !== $url ) : ?><a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( 'مشاهده همهٔ %s', $label ) ); ?>" class="jluxe-home-section-view-all<?php echo esc_attr( $inverse_class ); ?> shrink-0 rounded-lg border px-3 py-1.5 text-caption font-medium <?php echo esc_attr( $action_style ); ?>">مشاهده همه <span aria-hidden="true">←</span></a><?php endif; ?>
	</div>
	<?php
}

function jluxe_render_homepage_product_grid( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$count = (int) ( $section['count'] ?? 8 );
	$args  = array(
		'status'   => 'publish',
		'limit'    => $count,
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
			$count                = max( 2, min( 16, absint( $count ) ) );
			$args['featured']     = true;
			$args['stock_status'] = 'instock';
			$args['visibility']   = 'visible';
			$args['orderby']      = 'menu_order';
			$args['order']        = 'ASC';
			// Fetch a small reserve so stale variable-parent stock flags can be
			// checked below without leaving a short row when another item is available.
			$args['limit'] = min( 48, $count * 3 );
			break;
	}

	if ( ! empty( $section['category'] ) ) {
		$args['category'] = array( sanitize_title( $section['category'] ) );
	}

	$products = wc_get_products( $args );
	if ( 'featured' === ( $section['query'] ?? 'newest' ) ) {
		$products = array_values( array_filter( (array) $products, 'jluxe_homepage_featured_product_is_available' ) );
		$products = array_slice( $products, 0, $count );
	}
	if ( empty( $products ) ) {
		return;
	}

	$cols_d    = (int) ( $section['columns_desktop'] ?? 4 );
	$cols_t    = (int) ( $section['columns_tablet'] ?? 3 );
	$cols_m    = (int) ( $section['columns_mobile'] ?? 2 );
	$layout    = $section['layout'] ?? 'grid';
	$highlight = 'highlight' === ( $section['style'] ?? 'plain' );
	$is_carousel = 'carousel' === $layout;
	$grid_view_all = wc_get_page_permalink( 'shop' );
	if ( 'sale' === ( $section['query'] ?? '' ) ) {
		$grid_view_all = add_query_arg( 'on_sale', '1', $grid_view_all );
	} elseif ( ! empty( $section['category'] ) ) {
		$grid_term = get_term_by( 'slug', sanitize_title( (string) $section['category'] ), 'product_cat' );
		if ( $grid_term && ! is_wp_error( $grid_term ) ) {
			$grid_term_link = get_term_link( $grid_term );
			if ( ! is_wp_error( $grid_term_link ) ) { $grid_view_all = $grid_term_link; }
		}
	}

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
			<?php jluxe_render_homepage_section_header( (string) ( $section['title'] ?? '' ), $grid_view_all, (string) ( $section['title'] ?? 'محصولات' ), (string) ( $section['subtitle'] ?? '' ), $highlight ); ?>

			<?php if ( $is_carousel ) : ?>
				<div class="relative">
					<ul data-jluxe-scroller<?php echo jluxe_carousel_autoscroll_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="jluxe-scroll-x jluxe-hide-scrollbar m-0 flex list-none gap-4 overflow-x-auto p-0 scroll-smooth pb-2" aria-label="<?php echo esc_attr( $section['title'] ?: 'محصولات' ); ?>">
						<?php
						jluxe_with_product_card_image_sizes(
							'(max-width: 639px) 126px, (max-width: 1023px) 156px, 266px',
							static function () use ( $products ): void {
								/*
								 * lg (دسکتاپ) طبقِ اندازه‌گیریِ زنده از صفحه‌ی فروشگاه به w-[300px]
								 * عریض شده تا کارتِ محصولِ کروسلِ صفحه‌ی اصلی هم‌اندازه‌ی کارتِ
								 * صفحه‌ی فروشگاه (grid ستون‌بندیِ ۴تایی، ~304px در 1440px) بشه —
								 * طبقِ درخواستِ صریحِ کاربر. موبایل (w-[160px]) و تبلت
								 * (sm:w-[190px]) دست‌نخورده مونده، چون کاربر گفته اونا از قبل
								 * درستن.
								 */
								foreach ( $products as $product ) {
									$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
									wc_get_template_part( 'content', 'product' );
								}
								wp_reset_postdata();
							},
							'w-[160px] shrink-0 sm:w-[190px] lg:w-[300px]'
						);
						?>
					</ul>
					<?php jluxe_scroll_arrows(); ?>
				</div>
			<?php else : ?>
				<style>
					@media(min-width:640px){.jluxe-pg-<?php echo esc_attr( $section['id'] ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_t ); ?>,1fr)!important}}
					@media(min-width:1024px){.jluxe-pg-<?php echo esc_attr( $section['id'] ); ?>{grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_d ); ?>,1fr)!important}}
				</style>
				<ul class="jluxe-pg-<?php echo esc_attr( $section['id'] ); ?> m-0 grid list-none gap-4 p-0" style="grid-template-columns:repeat(<?php echo esc_attr( (string) $cols_m ); ?>,1fr)" aria-label="<?php echo esc_attr( $section['title'] ?: 'محصولات' ); ?>">
					<?php
					jluxe_with_product_card_image_sizes(
						jluxe_product_card_grid_image_sizes( $cols_m, $cols_t, $cols_d, $highlight, 640 ),
						static function () use ( $products ): void {
							foreach ( $products as $product ) {
								$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
								wc_get_template_part( 'content', 'product' );
							}
							wp_reset_postdata();
						}
					);
					?>
				</ul>
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

function jluxe_render_homepage_special_products( array $section ): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$sale_ids    = wc_get_product_ids_on_sale();
	$category_id = absint( $section['category'] ?? 0 );
	if ( $category_id ) {
		$category = get_term( $category_id, 'product_cat' );
		if ( is_wp_error( $category ) || ! $category || empty( $category->slug ) ) {
			return;
		}
		$category_sale_ids = wc_get_products(
			array(
				'status'   => 'publish',
				'include'  => array_map( 'absint', (array) $sale_ids ),
				'category' => array( (string) $category->slug ),
				'limit'    => max( 1, count( (array) $sale_ids ) ),
				'return'   => 'ids',
			)
		);
		$sale_ids = array_values( array_map( 'absint', (array) $category_sale_ids ) );
	}
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
			// Randomize sale IDs in PHP, then query small ordered batches. This
			// preserves a random eligible sequence without asking MySQL to RAND()
			// across the entire (potentially very large) on-sale ID list.
			$random_sale_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $sale_ids ) ) ) );
			shuffle( $random_sale_ids );
			$batch_size = max( 12, min( 90, $count * 3 ) );
			$products   = array();

			foreach ( array_chunk( $random_sale_ids, $batch_size ) as $random_batch ) {
				$remaining_ids = $random_batch;
				while ( ! empty( $remaining_ids ) && count( $products ) < $count ) {
					$batch_args             = $args;
					$batch_args['include']  = $remaining_ids;
					$batch_args['limit']    = min( count( $remaining_ids ), $count - count( $products ) );
					$batch_args['orderby']  = 'include';
					$batch_products         = wc_get_products( $batch_args );
					$returned_ids           = array();

					foreach ( (array) $batch_products as $product ) {
						if ( ! $product instanceof WC_Product ) {
							continue;
						}
						$product_id = (int) $product->get_id();
						if ( ! in_array( $product_id, $remaining_ids, true ) ) {
							continue;
					}
						$returned_ids[] = $product_id;
						if ( jluxe_product_is_public( $product ) ) {
							$products[] = $product;
							if ( count( $products ) >= $count ) {
								break;
							}
						}
					}

					// If an extension or a stale visibility record makes a returned
					// product unsuitable, retry the rest of this small batch before
					// moving on; never return a short row just because of sampling.
					if ( empty( $returned_ids ) ) {
						break;
					}
					$remaining_ids = array_values( array_diff( $remaining_ids, $returned_ids ) );
				}
				if ( count( $products ) >= $count ) {
					break;
				}
			}
			$products = array_slice( $products, 0, $count );
			break;
		case 'discount':
		default:
			$args['jluxe_discount_order'] = true;
			$args['orderby'] = 'date';
			break;
	}

	// The database sorts BEFORE LIMIT; never instantiate every sale product just to slice it.
	if ( 'rand' !== $sort ) {
		$products = array_values( array_filter( wc_get_products( $args ), 'jluxe_product_is_public' ) );
	}
	if ( empty( $products ) ) {
		return;
	}

	$view_all_link = ! empty( $section['view_all_link'] )
		? $section['view_all_link']
		: add_query_arg( 'on_sale', '1', wc_get_page_permalink( 'shop' ) );
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<div class="jluxe-home-special-band">
		<?php jluxe_render_homepage_section_header( (string) ( $section['title'] ?? 'فروش ویژه' ), $view_all_link, (string) ( $section['title'] ?? 'فروش ویژه' ) ); ?>
		<div class="relative">
			<ul data-jluxe-scroller data-jluxe-autoscroll class="jluxe-scroll-x jluxe-hide-scrollbar m-0 flex list-none gap-4 overflow-x-auto p-0 scroll-smooth pb-2" aria-label="<?php echo esc_attr( $section['title'] ?? 'فروش ویژه' ); ?>">
				<?php
				jluxe_with_product_card_image_sizes(
					'(max-width: 639px) 126px, (max-width: 1023px) 156px, 266px',
					static function () use ( $products ): void {
						/*
						 * lg (دسکتاپ) طبقِ اندازه‌گیریِ زنده از صفحه‌ی فروشگاه به w-[300px]
						 * عریض شده تا کارتِ محصولِ کروسلِ صفحه‌ی اصلی هم‌اندازه‌ی کارتِ
						 * صفحه‌ی فروشگاه (grid ستون‌بندیِ ۴تایی، ~304px در 1440px) بشه —
						 * طبقِ درخواستِ صریحِ کاربر. موبایل (w-[160px]) و تبلت
						 * (sm:w-[190px]) دست‌نخورده مونده، چون کاربر گفته اونا از قبل
						 * درستن.
						 */
						foreach ( $products as $product ) {
							$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
							wc_get_template_part( 'content', 'product' );
						}
						wp_reset_postdata();
					},
					'w-[160px] shrink-0 sm:w-[190px] lg:w-[300px]'
				);
				?>
			</ul>
			<?php jluxe_scroll_arrows(); ?>
		</div>
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

		default: // bestsellers
			// wc_get_products() forwards these meta_* query vars to WP_Query;
			// the WooCommerce CPT data store does not forward a generic meta_query.
			// Thus only products with numeric total_sales > 0 stay eligible.
			$args['meta_key']     = 'total_sales';
			$args['meta_value']   = 0;
			$args['meta_compare'] = '>';
			$args['meta_type']    = 'NUMERIC';
			// wc_get_products() forwards orderby to WP_Query; 'popularity' is
			// not a WP_Query orderby token here and silently falls back to date.
			// The active meta_key makes meta_value_num the explicit sales sort.
			$args['orderby']      = 'meta_value_num';
			$args['order']        = 'DESC';
			$products             = wc_get_products( $args );
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
		<?php jluxe_render_homepage_section_header( (string) ( $section['title'] ?? '' ), (string) $view_all, (string) ( $section['title'] ?? 'محصولات' ) ); ?>
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
	$image_id         = $product->get_image_id();
	$image_dimensions = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;
	$image_url        = $image_id ? ( wp_get_attachment_image_url( $image_id, 'large' ) ?: wp_get_attachment_image_url( $image_id, 'full' ) ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	$image_srcset     = $image_id ? wp_get_attachment_image_srcset( $image_id, 'jluxe-uncropped-200' ) : '';
	$image_width      = $image_dimensions ? absint( $image_dimensions[1] ) : 0;
	$image_height     = $image_dimensions ? absint( $image_dimensions[2] ) : 0;
	if ( ! $image_id && function_exists( 'wc_get_image_size' ) ) {
		$placeholder_dimensions = wc_get_image_size( 'woocommerce_thumbnail' );
		$image_width             = absint( $placeholder_dimensions['width'] ?? 0 );
		$image_height            = absint( $placeholder_dimensions['height'] ?? 0 );
	}
	if ( $image_id && ! $image_srcset ) {
		$image_srcset = wp_get_attachment_image_srcset( $image_id, 'large' );
	}

	$is_variable   = $product->is_type( 'variable' );
	$regular_price = (float) $product->get_regular_price();
	$sale_price    = (float) $product->get_sale_price();
	$has_discount  = ! $is_variable && $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;
	$price         = $is_variable ? $product->get_variation_price( 'min' ) : ( $has_discount ? $sale_price : $regular_price );
	?>
	<a data-jluxe-product-link href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="group flex flex-col justify-between p-4 transition-colors hover:bg-muted/40">
		<div class="flex items-center gap-3">
			<div class="flex size-[65px] shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted/40">
				<img
					data-jluxe-card-main-img
					src="<?php echo esc_url( $image_url ); ?>"
					<?php if ( $image_width > 0 && $image_height > 0 ) : ?>width="<?php echo esc_attr( (string) $image_width ); ?>" height="<?php echo esc_attr( (string) $image_height ); ?>"<?php endif; ?>
					<?php if ( $image_srcset ) : ?>srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="65px"<?php endif; ?>
					alt="<?php echo esc_attr( $product->get_name() ); ?>"
					loading="lazy"
					data-jluxe-no-skeleton
					class="size-full object-contain transition-transform duration-300 group-hover:scale-105"
				/>
			</div>
			<h3 class="line-clamp-2 min-w-0 flex-1 pt-1 text-[12.5px] leading-5 text-text-secondary sm:text-[13px]"><?php echo esc_html( $product->get_name() ); ?></h3>
		</div>
		<div class="mt-2 flex items-center justify-end gap-1 text-foreground">
			<span class="text-[13px] font-bold"><?php echo esc_html( jluxe_fa_digits( number_format( (float) $price, 0 ) ) ); ?></span>
			<?php echo jluxe_toman_display_icon_svg( 'shrink-0 text-text-muted', '16px', '13px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	$has_multiple_panels = count( $panels ) > 1;
	$panel_scroller_class = $has_multiple_panels ? ' jluxe-recommended-panels-scroller' : '';
	$panel_frame_class    = $has_multiple_panels ? ' jluxe-recommended-panels-frame--multiple' : '';
	?>
	<section class="jluxe-home-section mx-auto max-w-[1320px] px-3 md:px-4 py-6">
		<?php jluxe_render_homepage_section_header( (string) ( $section['title'] ?? '' ), '', (string) ( $section['title'] ?? 'محصولات پیشنهادی' ) ); ?>
		<?php
		/*
		 * R111 — زیرِ 640px، یک پنلِ کامل به‌اضافهٔ 40px از پنلِ بعدی
		 * دیده می‌شود تا وجودِ اسلاید بعد معلوم باشد؛ اندازه از عرضِ همین
		 * قاب و gap-4 محاسبه شده و فقط وقتی بیش از یک پنل داریم اعمال می‌شود.
		 * Snap/drag/touch و فلش‌های موجود باقی می‌مانند. در دسکتاپ (lg+)
		 * پنل‌ها مثل قبل هم‌عرض و بدونِ کاروسل‌اند.
		 */
		?>
		<div class="jluxe-recommended-panels-frame relative<?php echo esc_attr( $panel_frame_class ); ?>">
			<div data-jluxe-scroller class="jluxe-scroll-x jluxe-hide-scrollbar flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2 lg:overflow-visible lg:snap-none<?php echo esc_attr( $panel_scroller_class ); ?>">
				<?php foreach ( $panels as $panel ) :
				$term_link   = get_term_link( $panel['term'] );
				$button_link = ! empty( $panel['button_link'] ) ? $panel['button_link'] : ( is_wp_error( $term_link ) ? '' : $term_link );
				$arrow_style = ! empty( $panel['button_color'] ) ? ' style="background-color:' . esc_attr( $panel['button_color'] ) . '"' : '';
				?>
				<div class="jluxe-home-panel snap-start shrink-0 basis-full rounded-3xl bg-surface p-4 shadow-sm lg:basis-0 lg:flex-1" style="box-shadow:0 2px 18px rgba(15,23,42,.05)">
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
	$image_id         = $product->get_image_id();
	$image_dimensions = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;
	$image_url        = $image_id ? ( wp_get_attachment_image_url( $image_id, 'large' ) ?: wp_get_attachment_image_url( $image_id, 'full' ) ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	$image_srcset     = $image_id ? wp_get_attachment_image_srcset( $image_id, 'jluxe-uncropped-480' ) : '';
	$image_width      = $image_dimensions ? absint( $image_dimensions[1] ) : 0;
	$image_height     = $image_dimensions ? absint( $image_dimensions[2] ) : 0;
	if ( ! $image_id && function_exists( 'wc_get_image_size' ) ) {
		$placeholder_dimensions = wc_get_image_size( 'woocommerce_thumbnail' );
		$image_width             = absint( $placeholder_dimensions['width'] ?? 0 );
		$image_height            = absint( $placeholder_dimensions['height'] ?? 0 );
	}
	if ( $image_id && ! $image_srcset ) {
		$image_srcset = wp_get_attachment_image_srcset( $image_id, 'large' );
	}

	$is_variable   = $product->is_type( 'variable' );
	$regular_price = (float) $product->get_regular_price();
	$sale_price    = (float) $product->get_sale_price();
	$has_discount = false;
	$discount_pct = 0;
	if ( $is_variable ) {
		$variation_prices = $product->get_variation_prices( true );
		foreach ( (array) ( $variation_prices['regular_price'] ?? array() ) as $variation_id => $variation_regular ) {
			$variation_regular = (float) $variation_regular;
			$variation_sale = (float) ( $variation_prices['sale_price'][ $variation_id ] ?? 0 );
			if ( $variation_regular > 0 && $variation_sale > 0 && $variation_sale < $variation_regular ) {
				$has_discount = true;
				$discount_pct = max( $discount_pct, (int) round( 100 - ( $variation_sale / $variation_regular * 100 ) ) );
			}
		}
	} else {
		$has_discount = $product->is_on_sale() && $sale_price > 0 && $regular_price > 0 && $sale_price < $regular_price;
		$discount_pct = $has_discount ? (int) round( 100 - ( $sale_price / $regular_price ) * 100 ) : 0;
	}
	$price = $is_variable ? $product->get_variation_price( 'min' ) : ( $has_discount ? $sale_price : $regular_price );
	$average_rating = (float) $product->get_average_rating();

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
	<a data-jluxe-product-link href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="group relative flex flex-col p-3 sm:p-4 <?php echo esc_attr( implode( ' ', $border_classes ) ); ?>">
		<div class="relative h-6">
			<?php if ( $has_discount ) : ?>
				<span dir="ltr" class="jluxe-recommended-card-discount-badge absolute top-0 inline-flex w-fit items-center gap-0.5 rounded-lg bg-primary px-2 py-1 text-[11px] font-bold text-primary-foreground">
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
				<?php if ( $image_width > 0 && $image_height > 0 ) : ?>width="<?php echo esc_attr( (string) $image_width ); ?>" height="<?php echo esc_attr( (string) $image_height ); ?>"<?php endif; ?>
				<?php if ( $image_srcset ) : ?>srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="(max-width:639px) 96px, (max-width:1023px) 112px, 240px"<?php endif; ?>
				alt="<?php echo esc_attr( $product->get_name() ); ?>"
				loading="lazy"
				data-jluxe-no-skeleton
				class="max-h-full max-w-full rounded-[15px] object-contain transition-transform duration-300 group-hover:scale-105"
			/>
		</div>

		<h3 class="line-clamp-1 text-right text-[12px] leading-6 text-text-secondary sm:text-[13px]"><?php echo esc_html( $product->get_name() ); ?></h3>

		<div class="jluxe-recommended-card-meta mt-2 flex items-center justify-between">
			<?php if ( $average_rating > 0 ) : ?><div class="flex items-center gap-1" aria-label="<?php echo esc_attr( sprintf( 'امتیاز %s از ۵', number_format_i18n( $average_rating, 1 ) ) ); ?>">
				<svg class="size-[15px] shrink-0 text-boom-star" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m7.625 6.4l2.8-3.625q.3-.4.713-.587T12 2t.863.188t.712.587l2.8 3.625l4.25 1.425q.65.2 1.025.738t.375 1.187q0 .3-.088.6t-.287.575l-2.75 3.9l.1 4.1q.025.875-.575 1.475t-1.4.6q-.05 0-.55-.075L12 19.675l-4.475 1.25q-.125.05-.275.063T6.975 21q-.8 0-1.4-.6T5 18.925l.1-4.125l-2.725-3.875q-.2-.275-.288-.575T2 9.75q0-.625.363-1.162t1.012-.763z"/></svg>
				<span class="text-[13px] font-medium text-text-secondary"><?php echo esc_html( jluxe_fa_digits( number_format_i18n( $average_rating, 1 ) ) ); ?></span>
			</div><?php else : ?><span aria-hidden="true"></span><?php endif; ?>
			<div class="flex items-center gap-1 text-foreground">
				<span class="jluxe-recommended-card-price text-[14px] font-bold sm:text-[15px]"><?php echo esc_html( jluxe_fa_digits( number_format( (float) $price, 0 ) ) ); ?></span>
				<?php echo jluxe_toman_display_icon_svg( 'jluxe-recommended-card-currency shrink-0 text-text-muted', '18px', '15px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</a>
	<?php
}
