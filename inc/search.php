<?php
/**
 * جستجوی زنده‌ی سربرگ/موبایل — یک endpoint سبک AJAX که هم‌زمان محصولات،
 * دسته‌بندی‌ها و برندها رو می‌گرده. جستجوی WP_Query روی عنوان (LIKE) کاملاً
 * زبان‌کوره — فارسی/انگلیسی هر دو بدون نیاز به تنظیم اضافه کار می‌کنن.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_ajax_search(): void {
	check_ajax_referer( 'jluxe_search', 'nonce' );

	$term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$term = trim( $term );

	if ( mb_strlen( $term ) < 2 ) {
		wp_send_json_success(
			array(
				'products'   => array(),
				'categories' => array(),
				'brands'     => array(),
				'viewAllUrl' => '',
			)
		);
	}

	$products = array();
	$query    = new WP_Query(
		array(
			's'                   => $term,
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	foreach ( $query->posts as $post ) {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			continue;
		}
		$image_id = $product->get_image_id();
		// get_price_html() خودش داخلاً wc_price() صدا می‌زنه، که فیلترِ
		// jluxe_fa_digits_wc_price (inc/woocommerce.php) دیگه توی AJAX هم
		// (نه فقط بارگذاریِ عادیِ صفحه) ارقام رو فارسی می‌کنه؛ پیچیدنِ دوباره‌ش
		// توی jluxe_fa_digits اینجا آیکونِ SVG تومان رو خراب می‌کرد.
		$products[] = array(
			'id'    => $product->get_id(),
			'name'  => $product->get_name(),
			'price' => $product->get_price_html(),
			'image' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			'url'   => get_permalink( $product->get_id() ),
		);
	}
	wp_reset_postdata();

	$categories = jluxe_search_taxonomy_terms( 'product_cat', $term );
	$brands     = taxonomy_exists( 'product_brand' ) ? jluxe_search_taxonomy_terms( 'product_brand', $term ) : array();

	wp_send_json_success(
		array(
			'products'   => $products,
			'categories' => $categories,
			'brands'     => $brands,
			'viewAllUrl' => add_query_arg(
				array(
					's'        => rawurlencode( $term ),
					'post_type' => 'product',
				),
				home_url( '/' )
			),
		)
	);
}
add_action( 'wp_ajax_jluxe_search', 'jluxe_ajax_search' );
add_action( 'wp_ajax_nopriv_jluxe_search', 'jluxe_ajax_search' );

/**
 * @return array<int, array{name: string, url: string, count: int}>
 */
function jluxe_search_taxonomy_terms( string $taxonomy, string $term ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'name__like' => $term,
			'hide_empty' => true,
			'number'     => 4,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$results = array();
	foreach ( $terms as $t ) {
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$results[] = array(
			'name'  => $t->name,
			'url'   => $link,
			'count' => (int) $t->count,
		);
	}
	return $results;
}
