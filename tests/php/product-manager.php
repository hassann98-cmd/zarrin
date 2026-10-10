<?php
// Product-manager regression coverage. All WooCommerce data and writes are mocked; no live store is contacted.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/stubs.php';

if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
	function wc_get_attribute_taxonomies() { return $GLOBALS['product_manager_attribute_taxonomies'] ?? array(); }
}
if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
	function wc_attribute_taxonomy_name( $name ) { return 'pa_' . $name; }
}
if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $post_id, $context = 'display' ) { return '/wp-admin/post.php?post=' . (int) $post_id; }
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ) { $GLOBALS['product_manager_menu_page'] = $args; return 'toplevel_page_' . $args[3]; }
}
if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['product_manager_object_terms'][ (int) $object_id ][ $taxonomy ] = array_map( 'absint', (array) $terms );
		return $GLOBALS['product_manager_object_terms'][ (int) $object_id ][ $taxonomy ];
	}
}
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $object_id, $taxonomy, $args = array() ) {
		return $GLOBALS['product_manager_object_terms'][ (int) $object_id ][ $taxonomy ] ?? array();
	}
}
if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( $sku ) { return (int) ( $GLOBALS['product_manager_sku_map'][ (string) $sku ] ?? 0 ); }
}

class WC_Product_Attribute {
	private $id = 0;
	private $name = '';
	private $options = array();
	private $position = 0;
	private $visible = true;
	private $variation = false;

	public function set_id( $value ) { $this->id = (int) $value; }
	public function get_id() { return $this->id; }
	public function set_name( $value ) { $this->name = (string) $value; }
	public function get_name() { return $this->name; }
	public function set_options( $value ) { $this->options = (array) $value; }
	public function get_options() { return $this->options; }
	public function set_position( $value ) { $this->position = (int) $value; }
	public function get_position() { return $this->position; }
	public function set_visible( $value ) { $this->visible = (bool) $value; }
	public function get_visible() { return $this->visible; }
	public function set_variation( $value ) { $this->variation = (bool) $value; }
	public function get_variation() { return $this->variation; }
	public function is_taxonomy() { return $this->id > 0; }
}

class WC_Product_Variable {
	public static function sync( $product, $save = true ) {
		$GLOBALS['product_manager_syncs'][] = array( is_object( $product ) ? $product->get_id() : (int) $product, $save );
	}
}

class ProductManagerTestProduct extends WC_Product {
	public $attributes = array();
	public $sku = '';
	public $regular_price = '';
	public $sale_price = '';
	public $stock_quantity = null;
	public $stock_status = 'instock';
	public $backorders = 'no';
	public $parent_id = 0;
	public $children_ids = array();
	public $product_name = 'Mock product';
	public $product_slug = '';
	public $visibility_value = 'visible';
	public $saved_fields = array();
	public $saved_variation_attributes = array();

	public function get_type() { return $this->type; }
	public function get_name() { return $this->product_name; }
	public function get_sku() { return $this->sku; }
	public function get_attributes() { return $this->attributes; }
	public function get_children() { return $this->children_ids; }
	public function get_parent_id() { return $this->parent_id; }
	public function get_manage_stock() { return $this->manage_stock; }
	public function get_stock_quantity( $context = 'view' ) { return $this->stock_quantity; }
	public function get_stock_status() { return $this->stock_status; }
	public function get_backorders() { return $this->backorders; }
	public function get_regular_price( $context = 'view' ) { return $this->regular_price; }
	public function get_sale_price( $context = 'view' ) { return $this->sale_price; }
	public function get_status() { return $this->status; }
	public function get_catalog_visibility() { return $this->visibility; }
	public function get_low_stock_amount() { return ''; }
	public function is_sold_individually() { return false; }
	public function get_featured() { return false; }
	public function is_virtual() { return false; }
	public function get_reviews_allowed() { return true; }
	public function get_purchase_note() { return ''; }
	public function get_weight( $context = 'view' ) { return ''; }
	public function get_length( $context = 'view' ) { return ''; }
	public function get_width( $context = 'view' ) { return ''; }
	public function get_height( $context = 'view' ) { return ''; }
	public function get_tax_status() { return 'taxable'; }
	public function get_tax_class() { return ''; }
	public function set_name( $value ) { $this->product_name = (string) $value; }
	public function set_slug( $value ) { $this->product_slug = (string) $value; }
	public function set_catalog_visibility( $value ) { $this->visibility = (string) $value; $this->visibility_value = (string) $value; }
	public function set_description( $value ) { $this->saved_fields['description'] = $value; }
	public function set_short_description( $value ) { $this->saved_fields['short_description'] = $value; }
	public function set_date_on_sale_from( $value ) { $this->saved_fields['sale_from'] = $value; }
	public function set_date_on_sale_to( $value ) { $this->saved_fields['sale_to'] = $value; }
	public function set_low_stock_amount( $value ) { $this->saved_fields['low_stock_amount'] = $value; }
	public function set_sold_individually( $value ) { $this->sold = (bool) $value; }
	public function set_featured( $value ) { $this->saved_fields['featured'] = (bool) $value; }
	public function set_virtual( $value ) { $this->saved_fields['virtual'] = (bool) $value; }
	public function set_reviews_allowed( $value ) { $this->saved_fields['reviews_allowed'] = (bool) $value; }
	public function set_purchase_note( $value ) { $this->saved_fields['purchase_note'] = $value; }
	public function set_weight( $value ) { $this->saved_fields['weight'] = $value; }
	public function set_length( $value ) { $this->saved_fields['length'] = $value; }
	public function set_width( $value ) { $this->saved_fields['width'] = $value; }
	public function set_height( $value ) { $this->saved_fields['height'] = $value; }
	public function set_tax_status( $value ) { $this->saved_fields['tax_status'] = $value; }
	public function set_tax_class( $value ) { $this->saved_fields['tax_class'] = $value; }
	public function set_shipping_class_id( $value ) { $this->saved_fields['shipping_class_id'] = (int) $value; }
	public function set_category_ids( $value ) { $this->saved_fields['category_ids'] = (array) $value; }
	public function set_tag_ids( $value ) { $this->saved_fields['tag_ids'] = (array) $value; }
	public function set_attributes( $value ) { $this->saved_variation_attributes = (array) $value; $this->attributes = (array) $value; }
	public function set_image_id( $value ) { $this->image_id = (int) $value; }
	public function set_gallery_image_ids( $value ) { $GLOBALS['product_gallery_ids'][ $this->id ] = (array) $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function set_sku( $value ) { $this->sku = (string) $value; }
	public function set_regular_price( $value ) { $this->regular_price = (string) $value; }
	public function set_sale_price( $value ) { $this->sale_price = (string) $value; }
	public function set_manage_stock( $value ) { $this->manage_stock = (bool) $value; }
	public function set_stock_quantity( $value ) { $this->stock_quantity = $value; }
	public function set_stock_status( $value ) { $this->stock_status = (string) $value; }
	public function set_backorders( $value ) { $this->backorders = (string) $value; }
	public function set_status( $value ) { $this->status = (string) $value; }
	public function save() { $GLOBALS['product_manager_saves'][] = $this->id; return $this->id; }
}

require dirname( __DIR__, 2 ) . '/functions.php';

function product_manager_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$GLOBALS['product_manager_assertions'] = ( $GLOBALS['product_manager_assertions'] ?? 0 ) + 1;
	echo 'PASS: ' . $message . "\n";
}

function product_manager_json_call( callable $callback ): JsonReply {
	try {
		$callback();
	} catch ( JsonReply $reply ) {
		return $reply;
	}
	throw new RuntimeException( 'Expected a mocked JSON response.' );
}

$encoded_attribute_slug = '%d8%b1%d9%86%da%af';
$taxonomy = 'pa_' . $encoded_attribute_slug;
$GLOBALS['taxonomies'] = array(
	'product_cat'  => true,
	'product_tag'  => true,
	'product_brand'=> true,
	$taxonomy      => true,
);
$GLOBALS['product_manager_attribute_taxonomies'] = array(
	(object) array(
		'attribute_name'  => $encoded_attribute_slug,
		'attribute_label' => 'رنگ',
		'attribute_id'    => 44,
	),
);
$red = new WP_Term();
$red->term_id = 601;
$red->name = 'قرمز';
$red->slug = 'red';
$red->taxonomy = $taxonomy;
$blue = new WP_Term();
$blue->term_id = 602;
$blue->name = 'آبی';
$blue->slug = 'blue';
$blue->taxonomy = $taxonomy;
$category_term = new WP_Term();
$category_term->term_id = 603;
$category_term->name = 'پوشاک';
$category_term->slug = 'clothing';
$category_term->taxonomy = 'product_cat';
$brand_term = new WP_Term();
$brand_term->term_id = 610;
$brand_term->name = 'برند آزمایشی';
$brand_term->slug = 'test-brand';
$brand_term->taxonomy = 'product_brand';
$GLOBALS['terms_by_id'] = array( 601 => $red, 602 => $blue, 603 => $category_term, 610 => $brand_term );
$GLOBALS['terms_by_slug'] = array(
	'product_cat'   => array( 'clothing' => $category_term ),
	'product_brand' => array( 'test-brand' => $brand_term ),
	$taxonomy       => array( 'red' => $red, 'blue' => $blue ),
);
$GLOBALS['test_terms'] = array( $red, $blue, $category_term, $brand_term );

$color = new WC_Product_Attribute();
$color->set_id( 44 );
$color->set_name( $taxonomy );
$color->set_options( array( 601, 602 ) );
$color->set_position( 0 );
$color->set_visible( true );
$color->set_variation( true );

$parent = new ProductManagerTestProduct( 5301 );
$parent->type = 'variable';
$parent->attributes = array( $taxonomy => $color );
$parent->children_ids = array( 5302, 5303 );
$first_variation = new ProductManagerTestProduct( 5302 );
$first_variation->type = 'variation';
$first_variation->parent_id = 5301;
$first_variation->attributes = array( $taxonomy => 'red' );
$first_variation->stock_status = 'outofstock';
$first_variation->backorders = 'notify';
$second_variation = new ProductManagerTestProduct( 5303 );
$second_variation->type = 'variation';
$second_variation->parent_id = 5301;
$second_variation->attributes = array( $taxonomy => 'blue' );
$second_variation->stock_status = 'instock';
$second_variation->backorders = 'no';
$GLOBALS['products'] = array( 5301 => $parent, 5302 => $first_variation, 5303 => $second_variation );
$simple_product = new ProductManagerTestProduct( 5304 );
$simple_product->type = 'simple';
$simple_product->product_name = 'محصول سادهٔ فیلتر';
$simple_product->sku = 'SIMPLE-5304';
$simple_product->regular_price = '80';
$simple_product->stock_quantity = null;
$GLOBALS['products'][5304] = $simple_product;
$GLOBALS['product_category_ids'][5304] = array( 603 );
$GLOBALS['product_manager_object_terms'][5304] = array(
	'product_brand' => array( 610 ),
	$taxonomy       => array( 601 ),
);

$GLOBALS['scripts'] = array();
$GLOBALS['styles'] = array();
$GLOBALS['product_manager_menu_page'] = array();
jluxe_product_manager_register_page();
product_manager_check(
	isset( $GLOBALS['product_manager_menu_page'][3] ) && JLUXE_PRODUCT_MANAGER_PAGE === $GLOBALS['product_manager_menu_page'][3] && 'edit_products' === $GLOBALS['product_manager_menu_page'][2],
	'product manager is registered as its own top-level admin page with product-edit permission'
);
ob_start();
jluxe_product_manager_render_page();
$product_manager_markup = (string) ob_get_clean();
product_manager_check(
	false !== strpos( $product_manager_markup, 'id="jluxe-pm-brand-search"' ) &&
	false !== strpos( $product_manager_markup, 'id="jluxe-pm-category-search"' ) &&
	false !== strpos( $product_manager_markup, 'id="jluxe-pm-attribute-taxonomy"' ) &&
	false === strpos( $product_manager_markup, 'jluxe-pm-bulk-panel' ),
	'easy product manager keeps searchable category/brand and attribute filters without the redundant bulk panel'
);
jluxe_product_manager_enqueue_assets( 'plugins.php' );
product_manager_check( empty( $GLOBALS['scripts']['jluxe-product-manager'] ), 'product-manager assets stay unloaded outside their dedicated admin screen' );
jluxe_product_manager_enqueue_assets( 'toplevel_page_' . JLUXE_PRODUCT_MANAGER_PAGE );
product_manager_check(
	isset( $GLOBALS['scripts']['jluxe-product-manager'], $GLOBALS['styles']['jluxe-product-manager'] ) &&
	isset( $GLOBALS['localized']['jluxeProductManager']['nonce'] ) &&
	'product_brand' === $GLOBALS['localized']['jluxeProductManager']['brandTaxonomy'] &&
	$GLOBALS['localized']['jluxeProductManager']['attributes'][0]['taxonomy'] === $taxonomy,
	'dedicated top-level screen enqueues only the focused editor assets and its allowed taxonomy configuration'
);

$registered = jluxe_product_manager_registered_attributes();
product_manager_check( isset( $registered[ $taxonomy ] ), 'registered non-ASCII WooCommerce attribute keeps its canonical URL-encoded taxonomy key' );
product_manager_check(
	jluxe_product_manager_taxonomy_names_match( $taxonomy, 'pa_رنگ' ) && jluxe_product_manager_resolve_attribute_taxonomy( 'pa_رنگ', $registered ) === $taxonomy,
	'encoded and decoded taxonomy names resolve to the same registered attribute without rewriting its canonical key'
);

$payload = jluxe_product_manager_product_payload( $parent, 1 );
product_manager_check(
	count( $payload['attributes'] ) === 1 && $payload['attributes'][0]['taxonomy'] === $taxonomy && array_column( $payload['attributes'][0]['terms'], 'id' ) === array( 601, 602 ),
	'full-product payload finds existing encoded taxonomy attributes and selected color terms'
);
product_manager_check(
	$payload['variations']['attributes'][0]['name'] === $taxonomy &&
	$payload['variations']['items'][0]['attributes'][ $taxonomy ] === 'red' &&
	$payload['variations']['items'][0]['stockStatus'] === 'outofstock' &&
	$payload['variations']['items'][0]['backorders'] === 'notify',
	'variation payload preserves exact taxonomy keys, current option values, stock status, and backorder settings'
);

$validated = jluxe_product_manager_validate_attributes(
	$parent,
	array( $taxonomy => array( 'term_ids' => array( 601, 602 ), 'visible' => true, 'clear' => true ) )
);
product_manager_check(
	! is_wp_error( $validated ) && count( $validated ) === 1 && $validated[0]->get_name() === $taxonomy && $validated[0]->get_options() === array( 601, 602 ),
	'attribute validation retains the existing taxonomy attribute using the canonical WooCommerce name'
);

$blue_only = clone $color;
$blue_only->set_options( array( 602 ) );
$in_use = jluxe_product_manager_validate_variation_changes( $parent, array(), array( $blue_only ) );
product_manager_check(
	is_wp_error( $in_use ) && 'variation_attribute_in_use' === $in_use->get_error_code(),
	'parent option removal is rejected while an untouched saved variation still uses the removed term'
);

$edited_row = array(
	'id' => 5302,
	'sku' => '',
	'regularPrice' => '125',
	'salePrice' => '',
	'stockQuantity' => '3',
	'manageStock' => true,
	'stockStatus' => 'outofstock',
	'backorders' => 'notify',
	'enabled' => true,
	'attributes' => array( $taxonomy => 'blue' ),
);
$valid_edit = jluxe_product_manager_validate_variation_changes( $parent, array( $edited_row ), array( $blue_only ) );
product_manager_check(
	! is_wp_error( $valid_edit ) && $valid_edit[0]['attributes'][ $taxonomy ] === 'blue' && $valid_edit[0]['stock_status'] === 'outofstock' && $valid_edit[0]['backorders'] === 'notify',
	'variation edits validate against the submitted parent options and retain the exact selected value and inventory settings'
);

$invalid_row = $edited_row;
$invalid_row['attributes'][ $taxonomy ] = 'BLUE';
$invalid_value = jluxe_product_manager_validate_variation_changes( $parent, array( $invalid_row ), array( $blue_only ) );
product_manager_check(
	is_wp_error( $invalid_value ) && 'invalid_variation_attribute' === $invalid_value->get_error_code(),
	'variation option values are compared exactly instead of being rewritten with sanitize_title'
);

$material = new WC_Product_Attribute();
$material->set_id( 0 );
$material->set_name( 'Material type' );
$material->set_options( array( 'Blue / Green' ) );
$material->set_variation( true );
$parent->attributes['Material type'] = $material;
$first_variation->attributes['Material type'] = 'Blue / Green';
$second_variation->attributes['Material type'] = 'Blue / Green';
$both_options = array( $color, $material );
$removed_local_variation_attribute = jluxe_product_manager_clean_local_attributes( array(), $parent );
product_manager_check(
	is_wp_error( $removed_local_variation_attribute ) && 'variation_attribute_required' === $removed_local_variation_attribute->get_error_code(),
	'local attributes used to generate variations cannot be silently removed from the parent product'
);
$local_row = $edited_row;
$local_row['attributes']['Material type'] = 'Blue / Green';
$local_exact = jluxe_product_manager_validate_variation_changes( $parent, array( $local_row ), $both_options );
product_manager_check(
	! is_wp_error( $local_exact ) && $local_exact[0]['attributes']['Material type'] === 'Blue / Green',
	'local variation attribute values with punctuation and spaces are preserved exactly'
);

$duplicate_rows = jluxe_product_manager_validate_variation_changes( $parent, array( $edited_row, $edited_row ), array( $color ) );
product_manager_check( is_wp_error( $duplicate_rows ) && 'invalid_variation_id' === $duplicate_rows->get_error_code(), 'duplicate variation IDs in one save are rejected' );

$normalized_faq = jluxe_product_manager_clean_faq(
	array(
		array( 'question' => 'پرسش', 'answer' => 'پاسخ' ),
		array( 'question' => 'ناقص', 'answer' => '' ),
		array( 'question' => array( 'invalid' ), 'answer' => 'پاسخ ناامن' ),
	)
);
product_manager_check( count( $normalized_faq ) === 1 && $normalized_faq[0]['question'] === 'پرسش', 'FAQ cleanup keeps complete text entries and safely ignores malformed rows' );
product_manager_check( '1250.5' === jluxe_product_manager_clean_decimal( '۱۲۵۰٫۵' ), 'product-manager decimal parser accepts Persian digits and decimal separators' );

$GLOBALS['valid_wp_nonce'] = true;
$_POST = array( 'nonce' => 'nonce-jluxe_product_manager', 'taxonomy' => 'pa_رنگ', 'q' => '' );
$terms_reply = product_manager_json_call( 'jluxe_product_manager_terms_ajax' );
product_manager_check(
	$terms_reply->success && count( $terms_reply->data['terms'] ) === 2 && false === $terms_reply->data['canCreate'],
	'AJAX term search accepts a decoded Persian attribute name only after matching it to the registered taxonomy allowlist'
);
$GLOBALS['valid_wp_nonce'] = false;
$_POST = array( 'nonce' => 'bad', 'taxonomy' => $taxonomy );
$nonce_reply = product_manager_json_call( 'jluxe_product_manager_terms_ajax' );
product_manager_check( ! $nonce_reply->success && 403 === $nonce_reply->status, 'AJAX endpoints reject an invalid nonce before reading taxonomy data' );

$GLOBALS['valid_wp_nonce'] = true;
$GLOBALS['product_manager_sku_map'] = array( 'VAR-203' => 5302 );
$GLOBALS['product_query_handler'] = static function ( $args ) {
	$GLOBALS['product_manager_last_query'] = $args;
	return (object) array( 'products' => array(), 'total' => 0, 'max_num_pages' => 1 );
};
$_POST = array( 'nonce' => 'nonce-jluxe_product_manager', 'search' => 'VAR-203', 'type' => '', 'status' => '', 'stock' => '', 'category' => '', 'page' => '1' );
$sku_search = product_manager_json_call( 'jluxe_product_manager_list_ajax' );
product_manager_check(
	$sku_search->success && count( $sku_search->data['products'] ) === 1 && $sku_search->data['products'][0]['id'] === 5301,
	'exact variation-SKU search resolves to its editable parent product'
);
$_POST['type'] = 'simple';
$filtered_sku_search = product_manager_json_call( 'jluxe_product_manager_list_ajax' );
product_manager_check(
	$filtered_sku_search->success && array() === $filtered_sku_search->data['products'],
	'exact variation-SKU lookup does not bypass the selected product-type filter'
);
$GLOBALS['product_manager_sku_map']['SIMPLE-5304'] = 5304;
$_POST = array(
	'nonce' => 'nonce-jluxe_product_manager',
	'search' => 'SIMPLE-5304',
	'type' => 'simple',
	'status' => '',
	'stock' => '',
	'category' => '603',
	'brand' => '610',
	'attribute_taxonomy' => $taxonomy,
	'attribute_term' => '601',
	'page' => '1',
);
$filtered_term_search = product_manager_json_call( 'jluxe_product_manager_list_ajax' );
$filter_tax_query = $GLOBALS['product_manager_last_query']['tax_query'] ?? array();
product_manager_check(
	$filtered_term_search->success && 1 === count( $filtered_term_search->data['products'] ) && 5304 === $filtered_term_search->data['products'][0]['id'] &&
	array( 'clothing' ) === ( $GLOBALS['product_manager_last_query']['category'] ?? array() ) &&
	3 === count( $filter_tax_query ) && 'AND' === $filter_tax_query['relation'] && 'product_brand' === $filter_tax_query[0]['taxonomy'] && $taxonomy === $filter_tax_query[1]['taxonomy'],
	'product list combines category, brand, and selected global-attribute filters, including exact SKU results'
);
$GLOBALS['product_manager_object_terms'][5304]['product_brand'] = array();
$brand_mismatch = product_manager_json_call( 'jluxe_product_manager_list_ajax' );
product_manager_check(
	$brand_mismatch->success && array() === $brand_mismatch->data['products'],
	'exact SKU lookup does not bypass a selected brand or attribute term filter'
);
$GLOBALS['product_manager_object_terms'][5304]['product_brand'] = array( 610 );
unset( $GLOBALS['product_query_handler'], $GLOBALS['product_manager_last_query'] );

$GLOBALS['valid_wp_nonce'] = true;
$_POST = array(
	'nonce' => 'nonce-jluxe_product_manager',
	'product_id' => '5304',
	'regular_price' => '۱۲۵٫۵',
	'sale_price' => '',
	'manage_stock' => '1',
	'stock_quantity' => '4',
);
$quick_save_reply = product_manager_json_call( 'jluxe_product_manager_save_quick_ajax' );
product_manager_check(
	$quick_save_reply->success && '125.5' === $simple_product->regular_price && true === $simple_product->manage_stock && '4' === (string) $simple_product->stock_quantity && 'instock' === $simple_product->stock_status,
	'inline simple-product price and inventory saves use WooCommerce CRUD and accept Persian decimal digits'
);

$_POST = array(
	'nonce' => 'nonce-jluxe_product_manager',
	'product_id' => '5304',
	'category_ids' => array( 603 ),
	'brand_taxonomy' => 'product_brand',
	'brand_ids' => array( 610 ),
);
$organization_reply = product_manager_json_call( 'jluxe_product_manager_save_organization_ajax' );
product_manager_check(
	$organization_reply->success && array( 603 ) === $simple_product->saved_fields['category_ids'] &&
	array( 610 ) === $GLOBALS['product_manager_object_terms'][5304]['product_brand'],
	'search-selected category and brand values save through WooCommerce and the taxonomy API only'
);
$_POST['brand_ids'] = array( 601 );
$invalid_brand_reply = product_manager_json_call( 'jluxe_product_manager_save_organization_ajax' );
product_manager_check(
	! $invalid_brand_reply->success && 400 === $invalid_brand_reply->status,
	'organization save rejects a brand term from a different taxonomy before writing'
);

$_POST = array(
	'nonce' => 'nonce-jluxe_product_manager',
	'parent_id' => '5301',
	'variation_id' => '5302',
	'page' => '1',
	'variation' => json_encode(
		array(
			'id' => 5302,
			'sku' => '',
			'regularPrice' => '145',
			'salePrice' => '',
			'stockQuantity' => '2',
			'manageStock' => true,
			'stockStatus' => 'instock',
			'backorders' => 'notify',
			'enabled' => true,
			'attributes' => array( $taxonomy => 'blue' ),
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	),
);
$duplicate_variation_reply = product_manager_json_call( 'jluxe_product_manager_save_variation_quick_ajax' );
product_manager_check(
	! $duplicate_variation_reply->success && 400 === $duplicate_variation_reply->status && 'red' === $first_variation->attributes[ $taxonomy ],
	'quick variation editor refuses a duplicate size/color combination without changing the original variation'
);
$_POST['variation'] = json_encode(
	array(
		'id' => 5302,
		'sku' => '',
		'regularPrice' => '145',
		'salePrice' => '',
		'stockQuantity' => '2',
		'manageStock' => true,
		'stockStatus' => 'instock',
		'backorders' => 'notify',
		'enabled' => true,
		'attributes' => array( $taxonomy => 'red' ),
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
$variation_quick_save_reply = product_manager_json_call( 'jluxe_product_manager_save_variation_quick_ajax' );
product_manager_check(
	$variation_quick_save_reply->success && '145' === $first_variation->regular_price && '2' === (string) $first_variation->stock_quantity &&
	$variation_quick_save_reply->data['variation']['attributes'][ $taxonomy ] === 'red',
	'variable-product size, price, and stock autosave with the validated WooCommerce variation data'
);

$GLOBALS['valid_wp_nonce'] = true;
$full_save_payload = array(
	'id' => 5301,
	'name' => 'محصول آزمایشی ویرایش‌شده',
	'slug' => 'test-product-updated',
	'sku' => '',
	'status' => 'publish',
	'catalogVisibility' => 'visible',
	'description' => 'توضیحات کامل',
	'shortDescription' => 'توضیح کوتاه',
	'regularPrice' => '',
	'salePrice' => '',
	'saleFrom' => '',
	'saleTo' => '',
	'manageStock' => false,
	'stockQuantity' => '',
	'stockStatus' => 'instock',
	'backorders' => 'no',
	'lowStockAmount' => '',
	'soldIndividually' => false,
	'featured' => false,
	'virtual' => false,
	'reviewsAllowed' => true,
	'purchaseNote' => '',
	'weight' => '',
	'length' => '',
	'width' => '',
	'height' => '',
	'taxStatus' => 'taxable',
	'taxClass' => '',
	'shippingClassId' => 0,
	'categories' => array(),
	'tags' => array(),
	'brands' => array(),
	'brandTaxonomy' => '',
	'attributes' => array( $taxonomy => array( 'term_ids' => array( 601, 602 ), 'visible' => true, 'clear' => true ) ),
	'localAttributes' => array( array( 'name' => 'Material type', 'options' => 'Blue / Green', 'visible' => true ) ),
	'imageId' => 0,
	'galleryIds' => array(),
	'faq' => array( array( 'question' => 'پرسش تست', 'answer' => 'پاسخ تست' ) ),
	'badges' => array( 'authenticity' => true, 'warranty' => false ),
	'suggestedModal' => 'default',
	'seo' => array(),
	'variations' => array(),
);
$_POST = array( 'nonce' => 'nonce-jluxe_product_manager', 'payload' => json_encode( $full_save_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
$GLOBALS['product_manager_syncs'] = array();
$save_reply = product_manager_json_call( 'jluxe_product_manager_save_product_ajax' );
product_manager_check(
	$save_reply->success && 'محصول آزمایشی ویرایش‌شده' === $parent->product_name &&
	isset( $parent->meta['_jluxe_product_faq'] ) && $parent->meta['_jluxe_product_faq'][0]['question'] === 'پرسش تست' &&
	count( $GLOBALS['product_manager_syncs'] ) === 1 && $GLOBALS['product_manager_syncs'][0][0] === 5301,
	'full mocked AJAX save persists WooCommerce CRUD fields and JLuxe FAQ metadata, then synchronizes the variable parent when no variation row changed'
);

$GLOBALS['product_manager_syncs'] = array();
jluxe_product_manager_sync_variable_product( $parent );
product_manager_check(
	count( $GLOBALS['product_manager_syncs'] ) === 1 && $GLOBALS['product_manager_syncs'][0][0] === 5301,
	'variable-product synchronization uses WooCommerce CRUD even when no child variation changed'
);

echo 'PRODUCT_MANAGER_TESTS_PASSED: ' . $GLOBALS['product_manager_assertions'] . "\n";
