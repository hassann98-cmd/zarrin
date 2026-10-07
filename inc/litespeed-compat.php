<?php
/** R167 — narrow storefront compatibility, without disabling page cache or site-wide optimization. */
defined( 'ABSPATH' ) || exit;

function jluxe_litespeed_purchase_script_attrs( string $tag, string $handle ): string {
	if ( is_admin() || ! function_exists( 'wc_get_product' ) || empty( jluxe_asset_plan()['theme_woo_ux'] ) ) {
		return $tag;
	}
	if ( ! in_array( $handle, array( 'jquery-core', 'jquery-migrate', 'underscore', 'wp-util', 'jquery-blockui', 'wc-jquery-blockui', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'jluxe-storefront-utils', 'jluxe-woocommerce' ), true ) ) {
		return $tag;
	}
	if ( false === strpos( $tag, 'data-no-optimize' ) ) {
		// LiteSpeed 7.9.1 _parse_js skips this tag. Native WordPress defer/dependency order stays intact.
		$tag = preg_replace( '/<script\b/i', '<script data-no-optimize="1"', $tag, 1 );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'jluxe_litespeed_purchase_script_attrs', 30, 2 );

function jluxe_litespeed_purchase_inline_attrs( array $attributes, string $data ): array {
	if ( ! is_admin() && function_exists( 'wc_get_product' ) && preg_match( '/\b(?:var|let|const)\s+(?:JLuxeThemeSettings|jluxeWcSettings|wc_add_to_cart_variation_params|wc_add_to_cart_params|_wpUtilSettings)\s*=/', $data ) ) {
		// Localized configuration must be available before the excluded purchase controllers execute.
		$attributes['data-no-optimize'] = '1';
	}
	return $attributes;
}
add_filter( 'wp_inline_script_attributes', 'jluxe_litespeed_purchase_inline_attrs', 30, 2 );

/** Called after persistence, not before. LiteSpeed's public API is a no-op when the plugin is absent. */
function jluxe_litespeed_product_saved( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
	if ( $id > 0 ) {
		do_action( 'litespeed_purge_post', $id );
	}
}
add_action( 'woocommerce_after_product_object_save', 'jluxe_litespeed_product_saved', 90 );
