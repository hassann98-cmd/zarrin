<?php
defined( 'ABSPATH' ) || exit;

/** Top-level hook registrations extracted from woocommerce.php in original source order. */

add_filter( 'the_content', 'jluxe_wrap_single_product_description_tables', 99 );

add_filter(
	'pre_comment_approved',
	function ( $approved, $commentdata ) {
		if ( 1 === $approved && ! empty( $commentdata['comment_post_ID'] ) && 'product' === get_post_type( $commentdata['comment_post_ID'] ) ) {
			return 0; // منتظرِ تاییدِ مدیر — طبقِ درخواستِ صریحِ کاربر.
		}
		return $approved;
	},
	20,
	2
);

add_filter( 'wp_kses_allowed_html', 'jluxe_allow_svg_in_kses_post', 10, 2 );

add_action( 'wp', 'jluxe_defer_guided_checkout_coupon_form', 20 );

add_action( 'woocommerce_checkout_create_order', 'jluxe_checkout_store_map_coordinates', 10, 2 );
add_action( 'woocommerce_checkout_create_order', 'jluxe_checkout_store_gift_wrap', 11, 2 );
add_action( 'woocommerce_checkout_process', 'jluxe_sync_gift_wrap_request', 5 );
add_action( 'woocommerce_checkout_update_order_review', 'jluxe_sync_gift_wrap_request', 10, 1 );
add_action( 'woocommerce_cart_calculate_fees', 'jluxe_apply_gift_wrap_fee', 30 );
add_action( 'woocommerce_cart_emptied', 'jluxe_clear_gift_wrap_session', 20 );
add_action( 'woocommerce_thankyou', 'jluxe_clear_gift_wrap_session', 30 );
add_action( 'woocommerce_after_cart_table', 'jluxe_render_gift_wrap_box', 20 );
add_action( 'woocommerce_checkout_after_customer_details', 'jluxe_render_gift_wrap_box', 20 );
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_admin_order_gift_wrap_details', 20, 1 );
add_action( 'woocommerce_order_details_after_order_table', 'jluxe_render_order_gift_wrap_details', 20, 1 );
add_action( 'woocommerce_email_after_order_table', 'jluxe_email_order_gift_wrap_details', 20, 4 );
add_filter( 'woocommerce_privacy_export_order_personal_data', 'jluxe_checkout_export_gift_wrap', 20, 2 );
add_action( 'woocommerce_privacy_remove_order_personal_data', 'jluxe_checkout_erase_gift_wrap', 20, 1 );

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'jluxe_admin_order_delivery_map_location', 10, 1 );

add_filter( 'woocommerce_privacy_export_order_personal_data', 'jluxe_checkout_export_delivery_map_location', 10, 2 );

add_action( 'woocommerce_privacy_remove_order_personal_data', 'jluxe_checkout_erase_delivery_map_location', 10, 1 );

add_filter( 'wc_empty_cart_message', 'jluxe_cart_empty_message', 9999 );

add_action( 'wp_body_open', 'jluxe_output_toman_icon_sprite', 1 );

add_filter( 'woocommerce_currency_symbol', 'jluxe_toman_symbol', 10, 2 );

add_filter( 'woocommerce_show_variation_price', '__return_true' );

add_action( 'wp_head', 'jluxe_emit_structured_data', 4 );

add_filter( 'woocommerce_structured_data_website', 'jluxe_add_website_search_action' );

add_filter( 'woocommerce_structured_data_product', 'jluxe_add_product_schema_identity', 10, 2 );

add_filter( 'woocommerce_structured_data_product', 'jluxe_add_grouped_product_specs_to_schema', 20, 2 );

add_action( 'wp_head', 'jluxe_preload_single_product_lcp_image', 1 );

add_filter( 'wp_get_attachment_image_attributes', 'jluxe_product_lcp_image_attributes', 10, 3 );

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_sticky_cta' );

add_filter( 'woocommerce_available_variation', 'jluxe_variation_saving_html', 10, 3 );

add_filter( 'woocommerce_available_variation', 'jluxe_variation_stock_quantity_payload', 20, 3 );

add_filter( 'woocommerce_price_format', 'jluxe_toman_price_format', 10, 2 );

add_filter( 'wc_get_price_thousand_separator', 'jluxe_price_thousand_separator' );

add_action( 'init', 'jluxe_disable_persian_wc_plugin_digit_filter', 20 );

add_filter( 'wc_price', 'jluxe_fa_digits_wc_price' );

add_filter( 'wc_price', 'jluxe_amount_first_wc_price', 20 );

add_filter( 'woocommerce_get_price_html', 'jluxe_amount_first_price_html', 99 );

add_filter( 'woocommerce_variation_price_html', 'jluxe_amount_first_price_html', 99 );

add_filter( 'woocommerce_product_get_default_attributes', 'jluxe_auto_variable_default_attributes', 20, 2 );

add_filter( 'woocommerce_product_get_stock_status', 'jluxe_variable_product_stock_status', 20, 2 );

add_action( 'woocommerce_after_product_object_save', 'jluxe_sync_variable_defaults_from_change', 20, 2 );

add_action( 'woocommerce_product_set_stock', 'jluxe_sync_variable_defaults_from_change', 20, 1 );

add_action( 'woocommerce_variation_set_stock', 'jluxe_sync_variable_defaults_from_change', 20, 1 );

add_action( 'woocommerce_product_set_stock_status', 'jluxe_sync_variable_defaults_from_change', 20, 3 );

add_action( 'woocommerce_variation_set_stock_status', 'jluxe_sync_variable_defaults_from_change', 20, 3 );

add_action( 'woocommerce_variable_product_before_variations', 'jluxe_variable_variations_feed_guidance', 10 );

remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

add_action( 'woocommerce_after_checkout_validation', 'jluxe_validate_checkout_iran_city_province', 20, 2 );

add_filter( 'woocommerce_checkout_get_value', 'jluxe_checkout_clear_cached_location_values', 999, 2 );

add_filter( 'woocommerce_billing_fields', 'jluxe_account_billing_fields', 999, 2 );

add_filter( 'woocommerce_shipping_fields', 'jluxe_account_shipping_fields', 999, 2 );

add_filter( 'woocommerce_my_account_edit_address_field_value', 'jluxe_account_edit_address_country_value', 10, 3 );

add_filter( 'woocommerce_checkout_fields', 'jluxe_optional_checkout_postcodes', 999 );

add_action( 'woocommerce_after_checkout_validation', 'jluxe_validate_required_checkout_postcode_for_post_shipping', 10, 2 );

add_filter( 'woocommerce_checkout_posted_data', 'jluxe_normalize_checkout_numeric_fields', 999 );

add_filter( 'woocommerce_form_field', 'jluxe_hide_field_wrapper', 10, 2 );

add_filter( 'woocommerce_checkout_fields', 'jluxe_billing_fields', 20 );

add_filter( 'woocommerce_checkout_fields', 'jluxe_order_notes_field', 20 );

add_filter( 'woocommerce_admin_billing_fields', 'jluxe_admin_billing_fields' );

add_filter( 'woocommerce_ship_to_different_address_checkbox_enabled', '__return_false' );

add_filter(
	'woocommerce_order_button_text',
	static function ( $button_text ): string {
		return jluxe_cart_checkout_required_copy( 'checkout_place_order_button', 'پرداخت' );
	},
	9999
);

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_woocommerce_assets', 20 );

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_guided_checkout_assets', 21 );

add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_checkout_notice_assets', 22 );

add_action( 'init', 'jluxe_remove_default_product_tabs' );

add_action( 'created_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );

add_action( 'edited_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );

add_action( 'delete_product_cat', 'jluxe_clear_mega_menu_cache', 10, 2 );

add_action( 'clean_term_cache', 'jluxe_clear_mega_menu_cache', 10, 2 );

add_action( 'init', 'jluxe_register_product_brand_taxonomy' );

add_filter( 'gettext', 'jluxe_fix_untranslated_woocommerce_strings', 10, 3 );

add_filter( 'number_format_i18n', 'jluxe_fa_digits_number_format' );

add_action( 'wp', 'jluxe_remove_single_product_notices' );

add_action( 'wp', 'jluxe_maybe_disable_related_products' );

add_filter( 'woocommerce_output_related_products_args', 'jluxe_related_products_args', 99 );

add_filter( 'woocommerce_product_related_posts_shuffle', 'jluxe_related_products_shuffle', 99 );

add_action( 'woocommerce_product_options_related', 'jluxe_render_manual_related_products_field' );

add_action( 'woocommerce_process_product_meta', 'jluxe_save_manual_related_products', 20 );

add_action( 'admin_enqueue_scripts', 'jluxe_related_products_admin_assets', 20 );

add_filter( 'woocommerce_related_products', 'jluxe_order_related_products', 99, 3 );

add_action( 'wp_enqueue_scripts', 'jluxe_related_slider_assets', 20 );

add_action( 'woocommerce_cart_calculate_fees', 'jluxe_pa_apply_service_fees' );

add_action( 'woocommerce_cart_emptied', 'jluxe_pa_clear_service_fees' );

add_filter( 'posts_clauses', 'jluxe_out_of_stock_last_clauses', 10, 2 );

add_filter( 'woocommerce_get_privacy_policy_text', 'jluxe_hide_checkout_privacy_text', 10, 2 );

add_filter( 'loop_shop_per_page', 'jluxe_shop_per_page', 20 );

add_filter( 'loop_shop_columns', 'jluxe_shop_columns' );

add_action( 'wp_head', 'jluxe_output_shop_columns_css', 30 );

add_action( 'wp_head', 'jluxe_output_product_page_colors_css', 30 );

add_filter( 'woocommerce_catalog_orderby', 'jluxe_shop_orderby_labels', 20 );

add_action( 'wp', 'jluxe_shop_toolbar_hooks' );

add_action( 'pre_get_posts', 'jluxe_filter_stock_query' );

add_filter( 'woocommerce_product_data_tabs', 'jluxe_register_product_data_tab' );

add_action( 'woocommerce_product_data_panels', 'jluxe_render_product_badge_fields' );

add_action( 'set_object_terms', 'jluxe_purge_homepage_after_featured_term_change', 10, 6 );

add_action( 'added_post_meta', 'jluxe_invalidate_catalog_stock_order_cache', 10, 4 );

add_action( 'updated_post_meta', 'jluxe_invalidate_catalog_stock_order_cache', 10, 4 );

add_action( 'deleted_post_meta', 'jluxe_invalidate_catalog_stock_order_cache', 10, 4 );

add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_badge_fields', 20 );

add_action( 'woocommerce_product_options_general_product_data', 'jluxe_render_suggested_modal_toggle_field' );

add_action( 'woocommerce_process_product_meta', 'jluxe_save_suggested_modal_toggle_field' );

add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_delivery_time_primary_meta' );

add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_delivery_time_primary_meta_legacy' );

add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_delivery_time_meta' );

add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_delivery_time_meta_legacy' );

add_filter( 'woocommerce_account_menu_items', 'jluxe_maybe_remove_account_downloads_tab' );

add_filter( 'woocommerce_account_menu_items', 'jluxe_hide_account_payment_methods_menu_item', 20 );

add_filter( 'wc_add_to_cart_message_html', 'jluxe_suppress_ajax_add_to_cart_notice' );

add_filter( 'woocommerce_add_message', 'jluxe_suppress_cart_updated_notice' );

add_filter( 'woocommerce_add_message', 'jluxe_suppress_cart_item_removed_notice' );

add_action( 'wp', 'jluxe_remove_empty_cart_default_message' );

add_action( 'comment_post', 'jluxe_save_review_criteria_ratings', 10, 2 );

add_action( 'woocommerce_product_query', 'jluxe_filter_sale_query' );

add_action( 'wp_head', 'jluxe_output_product_mobile_design_css', 31 );
