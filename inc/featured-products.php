<?php
/**
 * Native WooCommerce featured-product discovery and homepage collection helpers.
 */

defined( 'ABSPATH' ) || exit;

/** Read the optional featured-only filter without trusting arbitrary request data. */
function jluxe_featured_products_admin_filter_value(): string {
	if ( ! isset( $_GET['jluxe_featured_filter'] ) || ! is_scalar( $_GET['jluxe_featured_filter'] ) ) {
		return '';
	}

	$value = sanitize_key( wp_unslash( (string) $_GET['jluxe_featured_filter'] ) );
	return 'featured' === $value ? $value : '';
}

/** Add a light-touch filter beside WooCommerce's native product-list filters. */
function jluxe_render_featured_products_admin_filter( string $post_type, string $which = 'top' ): void {
	if ( 'product' !== $post_type || 'top' !== $which ) {
		return;
	}

	$value = jluxe_featured_products_admin_filter_value();
	?>
	<label class="screen-reader-text" for="jluxe-featured-product-filter">فیلتر محصولات ویژه</label>
	<select name="jluxe_featured_filter" id="jluxe-featured-product-filter">
		<option value="" <?php selected( $value, '' ); ?>>همهٔ محصولات</option>
		<option value="featured" <?php selected( $value, 'featured' ); ?>>فقط محصولات ستاره‌دار</option>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'jluxe_render_featured_products_admin_filter', 40, 2 );

/** Resolve WooCommerce's native product_visibility term for the featured flag. */
function jluxe_featured_products_visibility_term_id(): int {
	if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
		$visibility_terms = wc_get_product_visibility_term_ids();
		$featured_term_id = is_array( $visibility_terms ) ? absint( $visibility_terms['featured'] ?? 0 ) : 0;
		if ( $featured_term_id > 0 ) {
			return $featured_term_id;
		}
	}

	if ( function_exists( 'get_term_by' ) ) {
		$featured_term = get_term_by( 'slug', 'featured', 'product_visibility' );
		if ( is_object( $featured_term ) && ! is_wp_error( $featured_term ) ) {
			return absint( $featured_term->term_id ?? 0 );
		}
	}

	return 0;
}

/** Filter only the native WooCommerce Products list; leave storefront queries untouched. */
function jluxe_filter_admin_product_list_to_featured( $query ): void {
	if ( ! is_admin() || ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_product_query = 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
	if ( ! $is_product_query || 'featured' !== jluxe_featured_products_admin_filter_value() ) {
		return;
	}

	$featured_term_id = jluxe_featured_products_visibility_term_id();
	if ( $featured_term_id < 1 ) {
		// Fail closed: if WooCommerce's featured term is unavailable, never show all products.
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	$existing_tax_query = $query->get( 'tax_query' );
	$featured_clause    = array(
		'taxonomy'         => 'product_visibility',
		'field'            => 'term_id',
		'terms'            => array( $featured_term_id ),
		'operator'         => 'IN',
		'include_children' => false,
	);
	$tax_query = array( 'relation' => 'AND' );
	if ( is_array( $existing_tax_query ) && ! empty( $existing_tax_query ) ) {
		$tax_query[] = $existing_tax_query;
	}
	$tax_query[] = $featured_clause;
	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'jluxe_filter_admin_product_list_to_featured', 20 );
