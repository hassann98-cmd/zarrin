<?php
/**
 * Selective archive navigation enhancement. Full server-rendered pages remain
 * canonical; only product/blog archives opt into the lightweight client helper.
 */
defined( 'ABSPATH' ) || exit;

/** Return the only page contexts whose archive markup can be safely replaced. */
function jluxe_soft_navigation_kind(): string {
	if (
		function_exists( 'is_shop' ) && is_shop() ||
		function_exists( 'is_product_taxonomy' ) && is_product_taxonomy()
	) {
		return 'catalog';
	}

	$post_type = function_exists( 'get_query_var' ) ? (array) get_query_var( 'post_type' ) : array();
	if (
		( function_exists( 'is_home' ) && is_home() && ( ! function_exists( 'is_front_page' ) || ! is_front_page() ) ) ||
		( function_exists( 'is_category' ) && is_category() ) ||
		( function_exists( 'is_tag' ) && is_tag() ) ||
		( function_exists( 'is_date' ) && is_date() ) ||
		( function_exists( 'is_author' ) && is_author() ) ||
		( function_exists( 'is_search' ) && is_search() && ! in_array( 'product', $post_type, true ) )
	) {
		return 'blog';
	}

	return '';
}

/** Load only on server-rendered archive contexts, and defer execution. */
function jluxe_enqueue_soft_navigation(): void {
	if ( '' === jluxe_soft_navigation_kind() ) {
		return;
	}

	$file = JLUXE_THEME_DIR . '/assets/js/soft-navigation.js';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_script(
		'jluxe-soft-navigation',
		JLUXE_THEME_URI . '/assets/js/soft-navigation.js',
		array(),
		(string) filemtime( $file ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_soft_navigation', 18 );
