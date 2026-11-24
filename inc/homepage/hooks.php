<?php
defined( 'ABSPATH' ) || exit;

/** Top-level hook registrations extracted from theme-settings-homepage.php in original source order. */

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_homepage_assets' );

add_filter( 'woocommerce_product_data_store_cpt_get_products_query', 'jluxe_sale_grid_query_args', 10, 2 );

add_filter( 'posts_orderby', 'jluxe_discount_orderby', 50, 2 );

add_action( 'wp_head', 'jluxe_preload_homepage_hero_lcp_image', 1 );

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_builder_animations', 35 );
