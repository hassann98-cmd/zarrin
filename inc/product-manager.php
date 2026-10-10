<?php
/**
 * A focused WooCommerce product workspace for routine price, stock,
 * category, brand, and existing-variation edits. Specialist work stays in the
 * native WooCommerce editor; all product writes use CRUD APIs, never direct SQL.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_PRODUCT_MANAGER_PAGE = 'jluxe-product-manager';

function jluxe_product_manager_register_page(): void {
	add_menu_page(
		'مدیریت آسان محصولات — زرین',
		'مدیریت آسان محصولات',
		'edit_products',
		JLUXE_PRODUCT_MANAGER_PAGE,
		'jluxe_product_manager_render_page',
		'dashicons-products',
		57
	);
}
add_action( 'admin_menu', 'jluxe_product_manager_register_page', 60 );

function jluxe_product_manager_enqueue_assets( string $hook ): void {
	if ( 'toplevel_page_' . JLUXE_PRODUCT_MANAGER_PAGE !== $hook ) {
		return;
	}
	wp_enqueue_script( 'jluxe-product-manager', JLUXE_THEME_URI . '/assets/js/product-manager.js', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/product-manager.js' ), true );
	wp_enqueue_style( 'jluxe-product-manager', JLUXE_THEME_URI . '/assets/css/product-manager.css', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/css/product-manager.css' ) );
	$attribute_filters = array();
	foreach ( jluxe_product_manager_registered_attributes() as $taxonomy => $attribute ) {
		$attribute_filters[] = array( 'taxonomy' => $taxonomy, 'label' => $attribute['label'] );
	}
	wp_localize_script(
		'jluxe-product-manager',
		'jluxeProductManager',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'jluxe_product_manager' ),
			'priceDecimals'=> function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 0,
			'currency'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			'brandTaxonomy'=> jluxe_product_manager_brand_taxonomy(),
			'attributes'   => $attribute_filters,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_product_manager_enqueue_assets' );

function jluxe_product_manager_render_filter_term( string $key, string $label, string $taxonomy, bool $enabled = true ): void {
	$wrapper_id = 'jluxe-pm-filter-' . $key;
	$search_id  = 'jluxe-pm-' . $key . '-search';
	$input_id   = 'jluxe-pm-' . $key;
	?>
	<div class="jluxe-pm-filter-term" id="<?php echo esc_attr( $wrapper_id ); ?>" data-pm-filter-term="<?php echo esc_attr( $key ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"<?php echo $enabled ? '' : ' hidden'; ?>>
		<label for="<?php echo esc_attr( $search_id ); ?>"><?php echo esc_html( $label ); ?></label>
		<div class="jluxe-pm-filter-control">
			<span class="jluxe-pm-filter-selected" data-pm-filter-selected hidden><span data-pm-filter-label></span><button type="button" data-pm-clear-filter aria-label="پاک‌کردن انتخاب <?php echo esc_attr( $label ); ?>">×</button></span>
			<input class="jluxe-pm-filter-search" id="<?php echo esc_attr( $search_id ); ?>" type="search" placeholder="جست‌وجوی <?php echo esc_attr( $label ); ?>…" autocomplete="off" aria-label="جست‌وجوی <?php echo esc_attr( $label ); ?>"<?php echo '' === $taxonomy ? ' disabled' : ''; ?>>
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" value="">
		</div>
		<div class="jluxe-pm-filter-results" role="listbox" aria-label="نتایج <?php echo esc_attr( $label ); ?>" hidden></div>
	</div>
	<?php
}

function jluxe_product_manager_render_page(): void {
	if ( ! current_user_can( 'edit_products' ) ) {
		wp_die( esc_html__( 'اجازهٔ مدیریت محصولات را ندارید.', 'jluxe' ) );
	}
	$brand_taxonomy = jluxe_product_manager_brand_taxonomy();
	?>
	<div class="wrap jluxe-pm" id="jluxe-product-manager">
		<header class="jluxe-pm-header">
			<div>
				<span class="jluxe-pm-kicker">میزکار روزمرهٔ ووکامرس</span>
				<h1>مدیریت آسان محصولات</h1>
				<p>قیمت و موجودی را همین‌جا تغییر دهید؛ تغییرات به‌طور خودکار ذخیره می‌شوند. برای تنظیمات بیشتر، ویرایشگر استاندارد ووکامرس در دسترس است.</p>
			</div>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">ویرایشگر استاندارد ووکامرس</a>
		</header>
		<div class="jluxe-pm-notice" id="jluxe-pm-notice" role="status" aria-live="polite" hidden></div>
		<section class="jluxe-pm-filters" aria-label="جست‌وجو و فیلتر محصولات">
			<div class="jluxe-pm-filter-main">
				<label class="jluxe-pm-search"><span class="screen-reader-text">جست‌وجو بر اساس نام یا SKU</span><input id="jluxe-pm-search" type="search" placeholder="نام محصول یا SKU را بنویسید…" autocomplete="off"><span class="dashicons dashicons-search" aria-hidden="true"></span></label>
				<select id="jluxe-pm-type" aria-label="نوع محصول"><option value="">همهٔ انواع</option><option value="simple">ساده</option><option value="variable">متغیر</option></select>
				<select id="jluxe-pm-stock" aria-label="وضعیت موجودی"><option value="">همهٔ موجودی‌ها</option><option value="instock">موجود</option><option value="outofstock">ناموجود</option><option value="onbackorder">پیش‌سفارش</option></select>
				<button class="button button-primary" id="jluxe-pm-search-button" type="button">جست‌وجو</button>
				<button class="button" id="jluxe-pm-reset" type="button">پاک‌کردن</button>
			</div>
			<details class="jluxe-pm-more-filters">
				<summary>فیلترهای بیشتر</summary>
				<div class="jluxe-pm-advanced-filters">
					<select id="jluxe-pm-status" aria-label="وضعیت انتشار"><option value="">همهٔ وضعیت‌ها</option><option value="publish">منتشرشده</option><option value="draft">پیش‌نویس</option><option value="private">خصوصی</option><option value="pending">در انتظار بررسی</option></select>
					<?php jluxe_product_manager_render_filter_term( 'category', 'دسته‌بندی', 'product_cat' ); ?>
					<?php jluxe_product_manager_render_filter_term( 'brand', 'برند', $brand_taxonomy, '' !== $brand_taxonomy ); ?>
					<label class="jluxe-pm-attribute-filter"><span>ویژگی محصول</span><select id="jluxe-pm-attribute-taxonomy"><option value="">همهٔ ویژگی‌ها</option><?php foreach ( jluxe_product_manager_registered_attributes() as $taxonomy => $attribute ) : ?><option value="<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $attribute['label'] ); ?></option><?php endforeach; ?></select></label>
					<?php jluxe_product_manager_render_filter_term( 'attribute-term', 'مقدار ویژگی', '' ); ?>
				</div>
			</details>
		</section>
		<div class="jluxe-pm-results-heading"><span id="jluxe-pm-summary" aria-live="polite"></span><button class="button" id="jluxe-pm-refresh" type="button">تازه‌سازی</button></div>
		<div class="jluxe-pm-table-wrap">
			<table class="widefat striped jluxe-pm-table">
				<thead><tr><th>محصول</th><th>قیمت عادی</th><th>موجودی</th><th>دسته‌بندی</th><th>مدیریت</th></tr></thead>
				<tbody id="jluxe-pm-rows"><tr><td colspan="5" class="jluxe-pm-loading">در حال بارگذاری محصولات…</td></tr></tbody>
			</table>
		</div>
		<footer class="jluxe-pm-pagination"><span id="jluxe-pm-page-label">صفحهٔ ۱</span><div><button class="button" id="jluxe-pm-prev" type="button" disabled>قبلی</button><button class="button" id="jluxe-pm-next" type="button" disabled>بعدی</button></div></footer>
		<section class="jluxe-pm-editor" id="jluxe-pm-editor" role="dialog" aria-modal="true" aria-labelledby="jluxe-pm-editor-title" hidden>
			<div class="jluxe-pm-editor-backdrop" data-pm-close-editor></div>
			<div class="jluxe-pm-editor-card" tabindex="-1">
				<header class="jluxe-pm-editor-header"><div><span class="jluxe-pm-editor-kicker">ویرایش سریع محصول</span><h2 id="jluxe-pm-editor-title">محصول</h2><p id="jluxe-pm-editor-subtitle"></p></div><button class="jluxe-pm-icon-button" type="button" data-pm-close-editor aria-label="بستن پنجره">×</button></header>
				<div class="jluxe-pm-editor-body" id="jluxe-pm-editor-body"><p class="jluxe-pm-loading">در حال دریافت اطلاعات محصول…</p></div>
				<footer class="jluxe-pm-editor-footer"><a class="button" id="jluxe-pm-native-edit" href="#" target="_blank" rel="noopener">ویرایش در ووکامرس</a><span class="jluxe-pm-save-state" id="jluxe-pm-save-state" aria-live="polite"></span><button class="button" type="button" data-pm-close-editor>بستن</button><button class="button button-primary" id="jluxe-pm-save" type="button" disabled>ذخیرهٔ دسته و برند</button></footer>
			</div>
		</section>
	</div>
	<?php
}

function jluxe_product_manager_filter_categories(): array {
	if ( ! function_exists( 'get_terms' ) || ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 250, 'orderby' => 'name', 'order' => 'ASC' ) );
	return is_array( $terms ) ? $terms : array();
}

function jluxe_product_manager_ajax_guard(): void {
	$nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'jluxe_product_manager' ) ) {
		wp_send_json_error( array( 'message' => 'نشست ویرایش معتبر نیست؛ صفحه را تازه‌سازی کنید.' ), 403 );
	}
	if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ مدیریت محصولات را ندارید.' ), 403 );
	}
	if ( ! function_exists( 'wc_get_product' ) ) {
		wp_send_json_error( array( 'message' => 'ووکامرس فعال نیست.' ), 503 );
	}
}

function jluxe_product_manager_can_edit_product( int $product_id ): bool {
	return $product_id > 0 && current_user_can( 'edit_post', $product_id );
}

function jluxe_product_manager_posted_payload() {
	$raw = isset( $_POST['payload'] ) && is_scalar( $_POST['payload'] ) ? (string) wp_unslash( $_POST['payload'] ) : '';
	$payload = json_decode( $raw, true );
	return is_array( $payload ) ? $payload : false;
}

function jluxe_product_manager_scalar_string( $value ): string {
	return is_scalar( $value ) ? (string) $value : '';
}

function jluxe_product_manager_registered_attributes(): array {
	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
		return array();
	}
	$taxonomies = wc_get_attribute_taxonomies();
	$out        = array();
	foreach ( is_array( $taxonomies ) ? $taxonomies : array() as $attribute ) {
		$name = isset( $attribute->attribute_name ) ? (string) $attribute->attribute_name : '';
		if ( '' === $name ) {
			continue;
		}
		$taxonomy = wc_attribute_taxonomy_name( $name );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$label = isset( $attribute->attribute_label ) && '' !== (string) $attribute->attribute_label
			? (string) $attribute->attribute_label
			: $name;
		$out[ $taxonomy ] = array(
			'taxonomy' => $taxonomy,
			'name'     => $name,
			'label'    => $label,
			'id'       => isset( $attribute->attribute_id ) ? absint( $attribute->attribute_id ) : ( function_exists( 'wc_attribute_taxonomy_id_by_name' ) ? absint( wc_attribute_taxonomy_id_by_name( $name ) ) : 0 ),
		);
	}
	return $out;
}

/**
 * WooCommerce stores non-ASCII taxonomy slugs as URL-encoded names on some
 * installs. Compare encoded and decoded forms without changing the canonical
 * key that WooCommerce registers and persists.
 */
function jluxe_product_manager_taxonomy_names_match( string $first, string $second ): bool {
	return $first === $second || rawurldecode( $first ) === rawurldecode( $second );
}

function jluxe_product_manager_resolve_attribute_taxonomy( string $name, array $definitions ): string {
	foreach ( array_keys( $definitions ) as $taxonomy ) {
		if ( jluxe_product_manager_taxonomy_names_match( $name, (string) $taxonomy ) ) {
			return (string) $taxonomy;
		}
	}
	return '';
}

function jluxe_product_manager_brand_taxonomy(): string {
	foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			return $taxonomy;
		}
	}
	return '';
}

function jluxe_product_manager_taxonomies(): array {
	$taxonomies = array(
		'product_cat' => array( 'label' => 'دسته‌بندی', 'creatable' => false ),
		'product_tag' => array( 'label' => 'برچسب / کلیدواژه', 'creatable' => true ),
	);
	$brand_taxonomy = jluxe_product_manager_brand_taxonomy();
	if ( '' !== $brand_taxonomy ) {
		$taxonomies[ $brand_taxonomy ] = array( 'label' => 'برند', 'creatable' => false );
	}
	foreach ( jluxe_product_manager_registered_attributes() as $taxonomy => $attribute ) {
		$taxonomies[ $taxonomy ] = array( 'label' => $attribute['label'], 'creatable' => false );
	}
	return $taxonomies;
}

function jluxe_product_manager_term_payload( string $taxonomy, array $term_ids ): array {
	$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
	if ( empty( $term_ids ) || ! function_exists( 'get_term' ) ) {
		return array();
	}
	$out = array();
	foreach ( $term_ids as $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! is_object( $term ) || is_wp_error( $term ) || ( isset( $term->taxonomy ) && $taxonomy !== $term->taxonomy ) ) {
			continue;
		}
		$out[] = array(
			'id'   => $term_id,
			'name' => isset( $term->name ) ? (string) $term->name : (string) $term_id,
			'slug' => isset( $term->slug ) ? (string) $term->slug : '',
		);
	}
	return $out;
}

function jluxe_product_manager_validate_term_ids( $ids, string $taxonomy ) {
	$known = jluxe_product_manager_taxonomies();
	if ( ! isset( $known[ $taxonomy ] ) || ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', 'نوع ویژگی/دسته‌بندی معتبر نیست.' );
	}
	if ( ! is_array( $ids ) ) {
		return new WP_Error( 'invalid_term_ids', 'فهرست گزینه‌های انتخاب‌شده معتبر نیست.' );
	}
	$normalized_ids = array();
	foreach ( $ids as $id ) {
		if ( ! is_scalar( $id ) || 0 === absint( $id ) ) {
			return new WP_Error( 'invalid_term_ids', 'یکی از شناسه‌های گزینه معتبر نیست.' );
		}
		$normalized_ids[] = absint( $id );
	}
	$ids = array_values( array_unique( $normalized_ids ) );
	foreach ( $ids as $id ) {
		$term = get_term( $id, $taxonomy );
		if ( ! is_object( $term ) || is_wp_error( $term ) || ( isset( $term->taxonomy ) && $taxonomy !== $term->taxonomy ) ) {
			return new WP_Error( 'invalid_term', 'یکی از گزینه‌های انتخاب‌شده در این دسته‌بندی وجود ندارد.' );
		}
	}
	return $ids;
}

function jluxe_product_manager_terms_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$requested_taxonomy = isset( $_POST['taxonomy'] ) && is_scalar( $_POST['taxonomy'] ) ? trim( (string) wp_unslash( $_POST['taxonomy'] ) ) : '';
	$known              = jluxe_product_manager_taxonomies();
	$taxonomy           = jluxe_product_manager_resolve_attribute_taxonomy( $requested_taxonomy, $known );
	if ( '' === $taxonomy || ! function_exists( 'get_terms' ) ) {
		wp_send_json_error( array( 'message' => 'این نوع واژه‌نامه قابل جست‌وجو نیست.' ), 400 );
	}
	$query = isset( $_POST['q'] ) && is_scalar( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$args  = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'number'     => 30,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);
	if ( '' !== $query ) {
		$args['search'] = $query;
	}
	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) ) {
		wp_send_json_error( array( 'message' => 'جست‌وجوی گزینه‌ها انجام نشد.' ), 503 );
	}
	$results = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		if ( ! is_object( $term ) || empty( $term->term_id ) ) {
			continue;
		}
		$results[] = array( 'id' => (int) $term->term_id, 'name' => (string) $term->name, 'slug' => (string) $term->slug );
	}
	wp_send_json_success( array( 'terms' => $results, 'canCreate' => 'product_tag' === $taxonomy && current_user_can( 'manage_product_terms' ) ) );
}
add_action( 'wp_ajax_jluxe_product_manager_terms', 'jluxe_product_manager_terms_ajax' );

function jluxe_product_manager_create_tag_ajax(): void {
	jluxe_product_manager_ajax_guard();
	if ( ! current_user_can( 'manage_product_terms' ) || ! function_exists( 'wp_insert_term' ) ) {
		wp_send_json_error( array( 'message' => 'برای ساخت برچسب جدید، مجوز مدیریت برچسب‌ها لازم است.' ), 403 );
	}
	$name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$name = trim( $name );
	if ( '' === $name || strlen( $name ) > 120 ) {
		wp_send_json_error( array( 'message' => 'نام برچسب را کوتاه و معتبر وارد کنید.' ), 400 );
	}
	$result = wp_insert_term( $name, 'product_tag' );
	if ( is_wp_error( $result ) && 'term_exists' === $result->get_error_code() ) {
		$existing_id = absint( $result->get_error_data( 'term_exists' ) );
		$term        = $existing_id ? get_term( $existing_id, 'product_tag' ) : false;
		if ( is_object( $term ) && ! is_wp_error( $term ) ) {
			wp_send_json_success( array( 'term' => array( 'id' => $existing_id, 'name' => (string) $term->name, 'slug' => (string) $term->slug ), 'existing' => true ) );
		}
	}
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => 'ساخت برچسب انجام نشد.' ), 400 );
	}
	$term = get_term( (int) $result['term_id'], 'product_tag' );
	wp_send_json_success( array( 'term' => array( 'id' => (int) $result['term_id'], 'name' => is_object( $term ) && isset( $term->name ) ? (string) $term->name : $name, 'slug' => is_object( $term ) && isset( $term->slug ) ? (string) $term->slug : '' ) ) );
}
add_action( 'wp_ajax_jluxe_product_manager_create_tag', 'jluxe_product_manager_create_tag_ajax' );

function jluxe_product_manager_can_view_product( $product ): bool {
	return $product instanceof WC_Product && $product->exists() && jluxe_product_manager_can_edit_product( absint( $product->get_id() ) );
}

function jluxe_product_manager_product_summary( WC_Product $product ): array {
	$type          = method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'simple';
	$manage_stock  = method_exists( $product, 'get_manage_stock' ) ? (bool) $product->get_manage_stock() : ( method_exists( $product, 'managing_stock' ) && $product->managing_stock() );
	$regular_price = method_exists( $product, 'get_regular_price' ) ? (string) $product->get_regular_price( 'edit' ) : '';
	$sale_price    = method_exists( $product, 'get_sale_price' ) ? (string) $product->get_sale_price( 'edit' ) : '';
	$stock_qty     = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity( 'edit' ) : null;
	$stock_status  = method_exists( $product, 'get_stock_status' ) ? (string) $product->get_stock_status() : 'instock';
	$price_label   = '';
	if ( 'variable' === $type && method_exists( $product, 'get_price_html' ) ) {
		$price_label = wp_strip_all_tags( (string) $product->get_price_html() );
	} else {
		$price = '' !== $sale_price ? $sale_price : $regular_price;
		$price_label = '' !== $price && function_exists( 'wc_price' ) ? wp_strip_all_tags( (string) wc_price( $price ) ) : $price;
	}
	$stock_labels = array( 'instock' => 'موجود', 'outofstock' => 'ناموجود', 'onbackorder' => 'پیش‌سفارش' );
	$stock_label  = 'variable' === $type && ! $manage_stock ? 'موجودی هر تنوع' : ( $manage_stock && null !== $stock_qty ? (string) $stock_qty : ( $stock_labels[ $stock_status ] ?? $stock_status ) );
	$image_id     = method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0;
	$image        = $image_id && function_exists( 'wp_get_attachment_image_url' ) ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	$category_names = array();
	if ( method_exists( $product, 'get_category_ids' ) ) {
		foreach ( jluxe_product_manager_term_payload( 'product_cat', (array) $product->get_category_ids() ) as $term ) {
			$category_names[] = $term['name'];
		}
	}
	return array(
		'id'            => absint( $product->get_id() ),
		'name'          => (string) $product->get_name(),
		'sku'           => method_exists( $product, 'get_sku' ) ? (string) $product->get_sku() : '',
		'type'          => $type,
		'typeLabel'     => 'variable' === $type ? 'متغیر' : 'ساده',
		'status'        => (string) $product->get_status(),
		'regularPrice'  => $regular_price,
		'salePrice'     => $sale_price,
		'priceLabel'    => $price_label,
		'manageStock'   => $manage_stock,
		'stockQuantity' => null === $stock_qty ? '' : $stock_qty,
		'stockStatus'   => $stock_status,
		'stockLabel'    => $stock_label,
		'categories'    => implode( '، ', $category_names ),
		'image'         => $image,
		'editUrl'       => get_edit_post_link( $product->get_id(), 'raw' ),
	);
}

function jluxe_product_manager_product_has_term_id( WC_Product $product, string $taxonomy, int $term_id ): bool {
	if ( $term_id < 1 ) {
		return false;
	}
	if ( 'product_cat' === $taxonomy && method_exists( $product, 'get_category_ids' ) ) {
		$term_ids = (array) $product->get_category_ids();
	} elseif ( function_exists( 'wp_get_object_terms' ) ) {
		$term_ids = wp_get_object_terms( $product->get_id(), $taxonomy, array( 'fields' => 'ids' ) );
	} else {
		return false;
	}
	if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
		return false;
	}
	return in_array( $term_id, array_map( 'absint', $term_ids ), true );
}

function jluxe_product_manager_list_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$page     = isset( $_POST['page'] ) ? max( 1, min( 10000, absint( $_POST['page'] ) ) ) : 1;
	$search   = isset( $_POST['search'] ) && is_scalar( $_POST['search'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['search'] ) ) ) : '';
	$type     = isset( $_POST['type'] ) && in_array( $_POST['type'], array( 'simple', 'variable' ), true ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	$status   = isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'publish', 'draft', 'private', 'pending' ), true ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$stock    = isset( $_POST['stock'] ) && in_array( $_POST['stock'], array( 'instock', 'outofstock', 'onbackorder' ), true ) ? sanitize_key( wp_unslash( $_POST['stock'] ) ) : '';
	$category_input = isset( $_POST['category'] ) && is_scalar( $_POST['category'] ) ? trim( (string) wp_unslash( $_POST['category'] ) ) : '';
	$category_term  = false;
	if ( '' !== $category_input ) {
		$category_term = ctype_digit( $category_input )
			? get_term( absint( $category_input ), 'product_cat' )
			: ( function_exists( 'get_term_by' ) ? get_term_by( 'slug', sanitize_title( $category_input ), 'product_cat' ) : false );
		if ( ! is_object( $category_term ) || is_wp_error( $category_term ) || 'product_cat' !== ( $category_term->taxonomy ?? '' ) ) {
			wp_send_json_error( array( 'message' => 'دسته‌بندی انتخاب‌شده معتبر نیست.' ), 400 );
		}
	}
	$category_id = is_object( $category_term ) ? absint( $category_term->term_id ?? 0 ) : 0;
	$brand_input = isset( $_POST['brand'] ) && is_scalar( $_POST['brand'] ) ? trim( (string) wp_unslash( $_POST['brand'] ) ) : '';
	$brand_id    = '' === $brand_input ? 0 : absint( $brand_input );
	$brand_taxonomy = jluxe_product_manager_brand_taxonomy();
	if ( '' !== $brand_input && ( ! ctype_digit( $brand_input ) || $brand_id < 1 || '' === $brand_taxonomy || is_wp_error( jluxe_product_manager_validate_term_ids( array( $brand_id ), $brand_taxonomy ) ) ) ) {
		wp_send_json_error( array( 'message' => 'برند انتخاب‌شده معتبر نیست.' ), 400 );
	}
	$attribute_taxonomy_input = isset( $_POST['attribute_taxonomy'] ) && is_scalar( $_POST['attribute_taxonomy'] ) ? trim( (string) wp_unslash( $_POST['attribute_taxonomy'] ) ) : '';
	$attribute_taxonomy = '' === $attribute_taxonomy_input ? '' : jluxe_product_manager_resolve_attribute_taxonomy( $attribute_taxonomy_input, jluxe_product_manager_registered_attributes() );
	$attribute_input = isset( $_POST['attribute_term'] ) && is_scalar( $_POST['attribute_term'] ) ? trim( (string) wp_unslash( $_POST['attribute_term'] ) ) : '';
	$attribute_id = '' === $attribute_input ? 0 : absint( $attribute_input );
	if ( '' !== $attribute_input && ( ! ctype_digit( $attribute_input ) || $attribute_id < 1 || '' === $attribute_taxonomy || is_wp_error( jluxe_product_manager_validate_term_ids( array( $attribute_id ), $attribute_taxonomy ) ) ) ) {
		wp_send_json_error( array( 'message' => 'ویژگی انتخاب‌شده معتبر نیست.' ), 400 );
	}
	$args     = array(
		'limit'    => 20,
		'page'     => $page,
		'paginate' => true,
		'return'   => 'objects',
		'orderby'  => 'date',
		'order'    => 'DESC',
		'type'     => $type ? array( $type ) : array( 'simple', 'variable' ),
		'status'   => $status ? array( $status ) : array( 'publish', 'draft', 'private', 'pending' ),
	);
	if ( '' !== $search ) {
		$args['s'] = function_exists( 'jluxe_substr' ) ? jluxe_substr( $search, 0, 120 ) : substr( $search, 0, 120 );
	}
	if ( '' !== $stock ) {
		$args['stock_status'] = $stock;
	}
	if ( is_object( $category_term ) && ! empty( $category_term->slug ) ) {
		$args['category'] = array( (string) $category_term->slug );
	}
	$taxonomy_queries = array();
	if ( $brand_id > 0 ) {
		$taxonomy_queries[] = array( 'taxonomy' => $brand_taxonomy, 'field' => 'term_id', 'terms' => array( $brand_id ) );
	}
	if ( $attribute_id > 0 ) {
		$taxonomy_queries[] = array( 'taxonomy' => $attribute_taxonomy, 'field' => 'term_id', 'terms' => array( $attribute_id ) );
	}
	if ( ! empty( $taxonomy_queries ) ) {
		$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $taxonomy_queries );
	}
	if ( ! current_user_can( 'edit_others_products' ) && ! current_user_can( 'edit_others_posts' ) && function_exists( 'get_current_user_id' ) ) {
		$args['author'] = get_current_user_id();
	}
	$result = wc_get_products( $args );
	if ( is_array( $result ) ) {
		$products = $result;
		$total     = count( $products );
		$pages     = 1;
	} else {
		$products = isset( $result->products ) && is_array( $result->products ) ? $result->products : array();
		$total     = isset( $result->total ) ? absint( $result->total ) : count( $products );
		$pages     = isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : max( 1, (int) ceil( $total / 20 ) );
	}
	// WooCommerce's product search is title/content based on some versions; include an exact SKU/ID match too.
	if ( '' !== $search && 1 === $page ) {
		$exact_id = function_exists( 'wc_get_product_id_by_sku' ) ? absint( wc_get_product_id_by_sku( $search ) ) : 0;
		if ( ! $exact_id && ctype_digit( $search ) ) {
			$exact_id = absint( $search );
		}
		if ( $exact_id ) {
			$exact_product = wc_get_product( $exact_id );
			if ( $exact_product instanceof WC_Product && $exact_product->is_type( 'variation' ) && $exact_product->get_parent_id() ) {
				$exact_product = wc_get_product( $exact_product->get_parent_id() );
			}
			$exact_matches_filters = $exact_product instanceof WC_Product && in_array( $exact_product->get_type(), array( 'simple', 'variable' ), true ) && jluxe_product_manager_can_view_product( $exact_product );
			if ( $exact_matches_filters && '' !== $type && ! $exact_product->is_type( $type ) ) {
				$exact_matches_filters = false;
			}
			if ( $exact_matches_filters && '' !== $status && $exact_product->get_status() !== $status ) {
				$exact_matches_filters = false;
			}
			if ( $exact_matches_filters && '' !== $stock && $exact_product->get_stock_status() !== $stock ) {
				$exact_matches_filters = false;
			}
			if ( $exact_matches_filters && $category_id > 0 ) {
				$exact_matches_filters = jluxe_product_manager_product_has_term_id( $exact_product, 'product_cat', $category_id );
			}
			if ( $exact_matches_filters && $brand_id > 0 ) {
				$exact_matches_filters = jluxe_product_manager_product_has_term_id( $exact_product, $brand_taxonomy, $brand_id );
			}
			if ( $exact_matches_filters && $attribute_id > 0 ) {
				$exact_matches_filters = jluxe_product_manager_product_has_term_id( $exact_product, $attribute_taxonomy, $attribute_id );
			}
			if ( $exact_matches_filters ) {
				$present = false;
				foreach ( $products as $listed ) {
					if ( $listed instanceof WC_Product && (int) $listed->get_id() === (int) $exact_product->get_id() ) {
						$present = true;
						break;
					}
				}
				if ( ! $present ) {
					array_unshift( $products, $exact_product );
					$total++;
				}
			}
		}
	}
	$rows = array();
	foreach ( $products as $product ) {
		if ( ! $product instanceof WC_Product || ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) || ! jluxe_product_manager_can_view_product( $product ) ) {
			continue;
		}
		$rows[] = jluxe_product_manager_product_summary( $product );
	}
	wp_send_json_success( array( 'products' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => 20 ) );
}
add_action( 'wp_ajax_jluxe_product_manager_list', 'jluxe_product_manager_list_ajax' );

function jluxe_product_manager_date_value( $date ): string {
	if ( ! $date instanceof DateTimeInterface ) {
		return '';
	}
	return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d\\TH:i', $date->getTimestamp() ) : $date->format( 'Y-m-d\\TH:i' );
}

function jluxe_product_manager_attribute_options( $attribute ): array {
	$options = is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ? (array) $attribute->get_options() : array();
	if ( is_object( $attribute ) && method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
		$taxonomy = (string) $attribute->get_name();
		$terms    = array();
		foreach ( $options as $option ) {
			$term_id = absint( $option );
			$term    = $term_id ? get_term( $term_id, $taxonomy ) : false;
			if ( is_object( $term ) && ! is_wp_error( $term ) ) {
				$terms[] = array( 'value' => isset( $term->slug ) ? (string) $term->slug : (string) $term_id, 'label' => isset( $term->name ) ? (string) $term->name : (string) $term_id );
			}
		}
		return $terms;
	}
	return array_map( static function ( $option ) {
		return array( 'value' => (string) $option, 'label' => (string) $option );
	}, $options );
}

function jluxe_product_manager_variation_payload( WC_Product $product, int $page = 1 ): array {
	$children  = method_exists( $product, 'get_children' ) ? array_values( array_map( 'absint', (array) $product->get_children() ) ) : array();
	$total     = count( $children );
	$per_page  = 20;
	$page      = max( 1, min( max( 1, (int) ceil( $total / $per_page ) ), $page ) );
	$attr_defs = array();
	foreach ( (array) $product->get_attributes() as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_variation' ) || ! $attribute->get_variation() ) {
			continue;
		}
		$name = (string) $attribute->get_name();
		$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
		$attr_defs[] = array( 'name' => $name, 'label' => (string) $label, 'options' => jluxe_product_manager_attribute_options( $attribute ) );
	}
	$variations = array();
	foreach ( array_slice( $children, ( $page - 1 ) * $per_page, $per_page ) as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $product->get_id() || ! jluxe_product_manager_can_edit_product( $variation_id ) ) {
			continue;
		}
		$raw_variation_attrs = method_exists( $variation, 'get_attributes' ) ? (array) $variation->get_attributes() : array();
		$variation_attrs     = array();
		foreach ( $attr_defs as $definition ) {
			foreach ( $raw_variation_attrs as $attribute_name => $attribute_value ) {
				$attribute_name = (string) $attribute_name;
				if ( 0 === strpos( $attribute_name, 'attribute_' ) ) {
					$attribute_name = substr( $attribute_name, 10 );
				}
				if ( jluxe_product_manager_taxonomy_names_match( $attribute_name, (string) $definition['name'] ) ) {
					$variation_attrs[ $definition['name'] ] = is_scalar( $attribute_value ) ? (string) $attribute_value : '';
					break;
				}
			}
			if ( ! array_key_exists( $definition['name'], $variation_attrs ) ) {
				$variation_attrs[ $definition['name'] ] = '';
			}
		}
		$manage_stock    = method_exists( $variation, 'get_manage_stock' ) ? (bool) $variation->get_manage_stock() : false;
		$variations[]    = array(
			'id'            => $variation_id,
			'sku'           => (string) $variation->get_sku(),
			'regularPrice'  => (string) $variation->get_regular_price( 'edit' ),
			'salePrice'     => (string) $variation->get_sale_price( 'edit' ),
			'manageStock'   => $manage_stock,
			'stockQuantity' => null === $variation->get_stock_quantity( 'edit' ) ? '' : $variation->get_stock_quantity( 'edit' ),
			'stockStatus'   => (string) $variation->get_stock_status(),
			'backorders'    => (string) $variation->get_backorders(),
			'enabled'       => 'publish' === $variation->get_status(),
			'attributes'    => $variation_attrs,
		);
	}
	return array( 'items' => $variations, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per_page ) ), 'total' => $total, 'perPage' => $per_page, 'attributes' => $attr_defs );
}

function jluxe_product_manager_seo_key( int $product_id ): string {
	if ( class_exists( 'RankMath\\Helper' ) || defined( 'RANK_MATH_VERSION' ) || ( function_exists( 'metadata_exists' ) && metadata_exists( 'post', $product_id, 'rank_math_focus_keyword' ) ) ) {
		return 'rank_math_focus_keyword';
	}
	if ( class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) || ( function_exists( 'metadata_exists' ) && metadata_exists( 'post', $product_id, '_yoast_wpseo_focuskw' ) ) ) {
		return '_yoast_wpseo_focuskw';
	}
	return '';
}

function jluxe_product_manager_seo_payload( int $product_id ): array {
	$key = jluxe_product_manager_seo_key( $product_id );
	if ( '' === $key ) {
		return array();
	}
	$provider = 0 === strpos( $key, 'rank_math_' ) ? 'rankmath' : 'yoast';
	return array(
		'provider'       => $provider,
		'focusKeyword'   => (string) get_post_meta( $product_id, $key, true ),
		'title'          => (string) get_post_meta( $product_id, 'rankmath' === $provider ? 'rank_math_title' : '_yoast_wpseo_title', true ),
		'description'    => (string) get_post_meta( $product_id, 'rankmath' === $provider ? 'rank_math_description' : '_yoast_wpseo_metadesc', true ),
	);
}

function jluxe_product_manager_product_payload( WC_Product $product, int $variation_page = 1 ): array {
	$product_id = absint( $product->get_id() );
	$attrs      = (array) $product->get_attributes();
	$global_attrs = array();
	foreach ( jluxe_product_manager_registered_attributes() as $taxonomy => $definition ) {
		$current = null;
		foreach ( $attrs as $attribute ) {
			if ( is_object( $attribute ) && method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() && jluxe_product_manager_taxonomy_names_match( (string) $attribute->get_name(), (string) $taxonomy ) ) {
				$current = $attribute;
				break;
			}
		}
		$options = is_object( $current ) && method_exists( $current, 'get_options' ) ? (array) $current->get_options() : array();
		$selected = array();
		foreach ( $options as $option_id ) {
			$term = get_term( absint( $option_id ), $taxonomy );
			if ( is_object( $term ) && ! is_wp_error( $term ) ) {
				$selected[] = array( 'id' => (int) $term->term_id, 'name' => (string) $term->name, 'slug' => (string) $term->slug );
			}
		}
		$global_attrs[] = array(
			'taxonomy'  => $taxonomy,
			'label'     => $definition['label'],
			'terms'     => $selected,
			'visible'   => is_object( $current ) && method_exists( $current, 'get_visible' ) ? (bool) $current->get_visible() : true,
			'variation' => is_object( $current ) && method_exists( $current, 'get_variation' ) ? (bool) $current->get_variation() : false,
		);
	}
	$local_attrs = array();
	foreach ( $attrs as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'is_taxonomy' ) || $attribute->is_taxonomy() ) {
			continue;
		}
		$options = method_exists( $attribute, 'get_options' ) ? (array) $attribute->get_options() : array();
		$local_attrs[] = array(
			'name'     => method_exists( $attribute, 'get_name' ) ? (string) $attribute->get_name() : '',
			'options'  => implode( "\n", array_map( 'strval', $options ) ),
			'visible'  => method_exists( $attribute, 'get_visible' ) ? (bool) $attribute->get_visible() : true,
			'variation'=> method_exists( $attribute, 'get_variation' ) ? (bool) $attribute->get_variation() : false,
		);
	}
	$brand_taxonomy = jluxe_product_manager_brand_taxonomy();
	$brand_ids      = '' !== $brand_taxonomy && function_exists( 'wp_get_object_terms' )
		? wp_get_object_terms( $product_id, $brand_taxonomy, array( 'fields' => 'ids' ) )
		: array();
	$brands = '' !== $brand_taxonomy && is_array( $brand_ids )
		? jluxe_product_manager_term_payload( $brand_taxonomy, $brand_ids )
		: array();
	$categories = method_exists( $product, 'get_category_ids' ) ? (array) $product->get_category_ids() : array();
	$tags       = method_exists( $product, 'get_tag_ids' ) ? (array) $product->get_tag_ids() : array();
	$gallery    = method_exists( $product, 'get_gallery_image_ids' ) ? (array) $product->get_gallery_image_ids() : array();
	$gallery_data = array();
	foreach ( $gallery as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$url = $attachment_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : false;
		if ( $url ) {
			$gallery_data[] = array( 'id' => $attachment_id, 'url' => (string) $url );
		}
	}
	$image_id = method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0;
	$image_url = $image_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
	$faq = function_exists( 'jluxe_get_product_faq_items' ) ? jluxe_get_product_faq_items( $product_id ) : get_post_meta( $product_id, '_jluxe_product_faq', true );
	if ( ! is_array( $faq ) ) {
		$faq = array();
	}
	$tax_class_options = function_exists( 'wc_get_product_tax_class_options' ) ? wc_get_product_tax_class_options() : array( '' => 'پیش‌فرض' );
	$shipping_classes = array();
	if ( taxonomy_exists( 'product_shipping_class' ) && function_exists( 'get_terms' ) ) {
		$terms = get_terms( array( 'taxonomy' => 'product_shipping_class', 'hide_empty' => false, 'number' => 200 ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$shipping_classes[] = array( 'id' => (int) $term->term_id, 'name' => (string) $term->name );
		}
	}
	$from = method_exists( $product, 'get_date_on_sale_from' ) ? $product->get_date_on_sale_from( 'edit' ) : null;
	$to   = method_exists( $product, 'get_date_on_sale_to' ) ? $product->get_date_on_sale_to( 'edit' ) : null;
	$type = $product->get_type();
	$stock_qty = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity( 'edit' ) : null;
	$manage_stock = method_exists( $product, 'get_manage_stock' ) ? (bool) $product->get_manage_stock() : false;
	$stock_options = array( 'instock' => 'موجود', 'outofstock' => 'ناموجود', 'onbackorder' => 'پیش‌سفارش' );
	$badge_authenticity = (string) get_post_meta( $product_id, '_jluxe_badge_authenticity', true );
	$badge_warranty = (string) get_post_meta( $product_id, '_jluxe_badge_warranty', true );
	$modal_raw = (string) get_post_meta( $product_id, '_jluxe_suggested_modal_enabled', true );
	$seo = jluxe_product_manager_seo_payload( $product_id );
	$variation_data = 'variable' === $type ? jluxe_product_manager_variation_payload( $product, $variation_page ) : array();
	return array(
		'id'               => $product_id,
		'name'             => (string) $product->get_name(),
		'slug'             => (string) get_post_field( 'post_name', $product_id ),
		'type'             => (string) $type,
		'sku'              => (string) $product->get_sku(),
		'status'           => (string) $product->get_status(),
		'catalogVisibility'=> (string) $product->get_catalog_visibility(),
		'description'      => (string) $product->get_description( 'edit' ),
		'shortDescription' => (string) $product->get_short_description( 'edit' ),
		'regularPrice'     => (string) $product->get_regular_price( 'edit' ),
		'salePrice'        => (string) $product->get_sale_price( 'edit' ),
		'saleFrom'         => jluxe_product_manager_date_value( $from ),
		'saleTo'           => jluxe_product_manager_date_value( $to ),
		'manageStock'      => $manage_stock,
		'stockQuantity'    => null === $stock_qty ? '' : $stock_qty,
		'stockStatus'      => (string) $product->get_stock_status(),
		'stockOptions'     => $stock_options,
		'backorders'       => (string) $product->get_backorders(),
		'lowStockAmount'   => (string) $product->get_low_stock_amount(),
		'soldIndividually' => (bool) $product->is_sold_individually(),
		'featured'         => (bool) $product->get_featured(),
		'virtual'          => (bool) $product->is_virtual(),
		'reviewsAllowed'   => (bool) $product->get_reviews_allowed(),
		'purchaseNote'     => (string) $product->get_purchase_note(),
		'weight'           => (string) $product->get_weight( 'edit' ),
		'length'           => (string) $product->get_length( 'edit' ),
		'width'            => (string) $product->get_width( 'edit' ),
		'height'           => (string) $product->get_height( 'edit' ),
		'taxStatus'        => (string) $product->get_tax_status(),
		'taxClass'         => (string) $product->get_tax_class(),
		'taxClasses'       => $tax_class_options,
		'shippingClassId'  => method_exists( $product, 'get_shipping_class_id' ) ? absint( $product->get_shipping_class_id() ) : 0,
		'shippingClasses'  => $shipping_classes,
		'categories'       => jluxe_product_manager_term_payload( 'product_cat', $categories ),
		'tags'             => jluxe_product_manager_term_payload( 'product_tag', $tags ),
		'brands'           => $brands,
		'brandTaxonomy'    => $brand_taxonomy,
		'attributes'       => $global_attrs,
		'localAttributes'  => $local_attrs,
		'image'            => array( 'id' => $image_id, 'url' => $image_url ? (string) $image_url : '' ),
		'gallery'          => $gallery_data,
		'faq'              => $faq,
		'badges'           => array( 'authenticity' => 'yes' === $badge_authenticity, 'warranty' => 'yes' === $badge_warranty ),
		'suggestedModal'   => in_array( $modal_raw, array( 'yes', 'no' ), true ) ? $modal_raw : 'default',
		'seo'              => $seo,
		'variations'       => $variation_data,
		'editUrl'          => get_edit_post_link( $product_id, 'raw' ),
		'productUrl'       => get_permalink( $product_id ),
	);
}

function jluxe_product_manager_get_product_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$product_id = isset( $_POST['product_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['product_id'] ) ) ) : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! jluxe_product_manager_can_view_product( $product ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد یا اجازهٔ ویرایش آن را ندارید.' ), 404 );
	}
	if ( ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
		wp_send_json_error( array( 'message' => 'این نوع محصول را از ویرایشگر استاندارد ووکامرس باز کنید.' ), 400 );
	}
	$page = isset( $_POST['variation_page'] ) ? max( 1, absint( $_POST['variation_page'] ) ) : 1;
	wp_send_json_success( jluxe_product_manager_product_payload( $product, $page ) );
}
add_action( 'wp_ajax_jluxe_product_manager_get_product', 'jluxe_product_manager_get_product_ajax' );

function jluxe_product_manager_get_variations_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$product_id = isset( $_POST['product_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['product_id'] ) ) ) : 0;
	$product = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) || ! jluxe_product_manager_can_view_product( $product ) ) {
		wp_send_json_error( array( 'message' => 'محصول متغیر یا دسترسی ویرایش آن پیدا نشد.' ), 404 );
	}
	$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
	wp_send_json_success( jluxe_product_manager_variation_payload( $product, $page ) );
}
add_action( 'wp_ajax_jluxe_product_manager_get_variations', 'jluxe_product_manager_get_variations_ajax' );

function jluxe_product_manager_save_organization_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$product_id = isset( $_POST['product_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['product_id'] ) ) ) : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! jluxe_product_manager_can_view_product( $product ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد یا اجازهٔ ویرایش آن را ندارید.' ), 404 );
	}
	$category_input = isset( $_POST['category_ids'] ) && is_array( $_POST['category_ids'] ) ? wp_unslash( $_POST['category_ids'] ) : array();
	$category_ids   = jluxe_product_manager_validate_term_ids( $category_input, 'product_cat' );
	if ( is_wp_error( $category_ids ) ) {
		wp_send_json_error( array( 'message' => $category_ids->get_error_message() ), 400 );
	}
	$brand_taxonomy = jluxe_product_manager_brand_taxonomy();
	$requested_brand_taxonomy = isset( $_POST['brand_taxonomy'] ) && is_scalar( $_POST['brand_taxonomy'] )
		? trim( (string) wp_unslash( $_POST['brand_taxonomy'] ) )
		: '';
	$brand_input = isset( $_POST['brand_ids'] ) && is_array( $_POST['brand_ids'] ) ? wp_unslash( $_POST['brand_ids'] ) : array();
	if ( '' !== $requested_brand_taxonomy && $requested_brand_taxonomy !== $brand_taxonomy ) {
		wp_send_json_error( array( 'message' => 'واژه‌نامهٔ برند دیگر در دسترس نیست؛ صفحه را تازه‌سازی کنید.' ), 400 );
	}
	if ( '' === $brand_taxonomy && ! empty( $brand_input ) ) {
		wp_send_json_error( array( 'message' => 'برای این فروشگاه واژه‌نامهٔ برند فعالی پیدا نشد.' ), 400 );
	}
	$brand_ids = array();
	if ( '' !== $brand_taxonomy ) {
		$brand_ids = jluxe_product_manager_validate_term_ids( $brand_input, $brand_taxonomy );
		if ( is_wp_error( $brand_ids ) ) {
			wp_send_json_error( array( 'message' => $brand_ids->get_error_message() ), 400 );
		}
	}
	$save_error = '';
	$partial    = false;
	try {
		$product->set_category_ids( $category_ids );
		$product->save();
		if ( '' !== $brand_taxonomy ) {
			if ( ! function_exists( 'wp_set_object_terms' ) ) {
				$save_error = 'ذخیرهٔ برند در این نسخهٔ وردپرس در دسترس نیست.';
				$partial    = true;
			} else {
				$brand_result = wp_set_object_terms( $product_id, $brand_ids, $brand_taxonomy, false );
				if ( is_wp_error( $brand_result ) ) {
					$save_error = 'دسته‌بندی ذخیره شد، اما ذخیرهٔ برند انجام نشد: ' . $brand_result->get_error_message();
					$partial    = true;
				}
			}
		}
	} catch ( Throwable $error ) {
		$save_error = 'ذخیرهٔ دسته‌بندی و برند انجام نشد: ' . $error->getMessage();
	}
	if ( '' !== $save_error ) {
		wp_send_json_error( array( 'message' => $save_error, 'partial' => $partial ), $partial ? 500 : 400 );
	}
	jluxe_purge_product_related_caches( $product_id );
	wp_send_json_success(
		array(
			'message' => 'دسته‌بندی و برند ذخیره شد.',
			'product' => jluxe_product_manager_product_summary( wc_get_product( $product_id ) ),
		)
	);
}
add_action( 'wp_ajax_jluxe_product_manager_save_organization', 'jluxe_product_manager_save_organization_ajax' );

function jluxe_product_manager_clean_decimal( $value, bool $allow_empty = true ) {
	if ( ! is_scalar( $value ) ) {
		return new WP_Error( 'invalid_number', 'مقدار عددی معتبر نیست.' );
	}
	$value = trim( (string) $value );
	if ( '' === $value && $allow_empty ) {
		return '';
	}
	$value = strtr( $value, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','٬'=>'',','=>'', ' '=>'' ) );
	if ( ! preg_match( '/^-?\d+(?:\.\d+)?$/', $value ) ) {
		return new WP_Error( 'invalid_number', 'عدد را با رقم فارسی یا لاتین و بدون متن وارد کنید.' );
	}
	if ( function_exists( 'wc_format_decimal' ) ) {
		$value = wc_format_decimal( $value );
	}
	return (string) $value;
}

function jluxe_product_manager_validate_sale_date( $value ) {
	if ( ! is_scalar( $value ) ) {
		return new WP_Error( 'invalid_date', 'تاریخ حراج معتبر نیست.' );
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) || ! function_exists( 'wc_string_to_datetime' ) ) {
		return new WP_Error( 'invalid_date', 'تاریخ را از انتخاب‌گر وارد کنید.' );
	}
	$date = wc_string_to_datetime( str_replace( 'T', ' ', $value ) );
	return $date instanceof DateTimeInterface ? $date : new WP_Error( 'invalid_date', 'تاریخ حراج قابل خواندن نیست.' );
}

function jluxe_product_manager_validate_attributes( WC_Product $product, array $submitted ) {
	$definitions = jluxe_product_manager_registered_attributes();
	$by_name     = array();
	$preserved   = array();
	foreach ( (array) $product->get_attributes() as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
			continue;
		}
		$name = (string) $attribute->get_name();
		if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
			$canonical_name = jluxe_product_manager_resolve_attribute_taxonomy( $name, $definitions );
			if ( '' !== $canonical_name ) {
				$by_name[ $canonical_name ] = $attribute;
			} else {
				$preserved[] = $attribute;
			}
		}
	}
	foreach ( $definitions as $taxonomy => $definition ) {
		$data = isset( $submitted[ $taxonomy ] ) && is_array( $submitted[ $taxonomy ] ) ? $submitted[ $taxonomy ] : array();
		$ids  = jluxe_product_manager_validate_term_ids( $data['term_ids'] ?? array(), $taxonomy );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		$existing  = $by_name[ $taxonomy ] ?? null;
		$visible   = array_key_exists( 'visible', $data ) ? (bool) $data['visible'] : ( is_object( $existing ) && method_exists( $existing, 'get_visible' ) ? (bool) $existing->get_visible() : true );
		$variation = is_object( $existing ) && method_exists( $existing, 'get_variation' ) ? (bool) $existing->get_variation() : false;
		if ( empty( $ids ) ) {
			if ( $variation && is_object( $existing ) && ! empty( $data['clear'] ) ) {
				return new WP_Error( 'variation_attribute_required', 'ویژگیِ سازندهٔ تنوع را نمی‌توان حذف کرد؛ ابتدا از ویرایشگر تنوع‌ها آن را تغییر دهید.' );
			}
			if ( $existing && ! $variation && ! empty( $data['clear'] ) ) {
				unset( $by_name[ $taxonomy ] );
			}
			continue;
		}
		if ( ! class_exists( 'WC_Product_Attribute' ) ) {
			return new WP_Error( 'attribute_api_missing', 'API ویژگی‌های ووکامرس در دسترس نیست.' );
		}
		$attribute = $existing instanceof WC_Product_Attribute ? clone $existing : new WC_Product_Attribute();
		$attribute_id = isset( $definition['id'] ) ? absint( $definition['id'] ) : 0;
		$attribute->set_id( $attribute_id );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $ids );
		$attribute->set_position( is_object( $existing ) && method_exists( $existing, 'get_position' ) ? (int) $existing->get_position() : count( $by_name ) );
		$attribute->set_visible( $visible );
		$attribute->set_variation( $variation );
		$by_name[ $taxonomy ] = $attribute;
	}
	return array_merge( array_values( $by_name ), $preserved );
}

function jluxe_product_manager_clean_local_attributes( $submitted, WC_Product $product ) {
	if ( ! is_array( $submitted ) ) {
		return new WP_Error( 'invalid_attributes', 'ویژگی‌های سفارشی معتبر نیستند.' );
	}
	$existing_attributes = array();
	foreach ( (array) $product->get_attributes() as $existing ) {
		if ( is_object( $existing ) && method_exists( $existing, 'is_taxonomy' ) && ! $existing->is_taxonomy() && method_exists( $existing, 'get_name' ) ) {
			$existing_attributes[ sanitize_title( $existing->get_name() ) ] = $existing;
		}
	}
	$updated  = array();
	$position = count( $product->get_attributes() );
	foreach ( array_slice( $submitted, 0, 20 ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$name = trim( sanitize_text_field( jluxe_product_manager_scalar_string( $item['name'] ?? '' ) ) );
		if ( '' === $name ) {
			continue;
		}
		$options_raw = jluxe_product_manager_scalar_string( $item['options'] ?? '' );
		$options = preg_split( '/\r\n|\r|\n/', $options_raw );
		$options = array_values( array_filter( array_map( 'sanitize_text_field', $options ), static function ( $value ) { return '' !== trim( $value ); } ) );
		if ( empty( $options ) ) {
			continue;
		}
		$existing = $existing_attributes[ sanitize_title( $name ) ] ?? null;
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( $name );
		$attribute->set_options( $options );
		$attribute->set_position( is_object( $existing ) && method_exists( $existing, 'get_position' ) ? (int) $existing->get_position() : $position++ );
		$attribute->set_visible( array_key_exists( 'visible', $item ) ? ! empty( $item['visible'] ) : ( is_object( $existing ) && method_exists( $existing, 'get_visible' ) ? (bool) $existing->get_visible() : true ) );
		$attribute->set_variation( is_object( $existing ) && method_exists( $existing, 'get_variation' ) ? (bool) $existing->get_variation() : false );
		$updated[] = $attribute;
	}
	$updated_names = array();
	foreach ( $updated as $attribute ) {
		$updated_names[ sanitize_title( $attribute->get_name() ) ] = true;
	}
	foreach ( $existing_attributes as $name => $existing ) {
		if ( method_exists( $existing, 'get_variation' ) && $existing->get_variation() && ! isset( $updated_names[ $name ] ) ) {
			return new WP_Error( 'variation_attribute_required', 'ویژگی اختصاصیِ سازندهٔ تنوع را نمی‌توان حذف یا خالی کرد؛ ابتدا ساختار تنوع را در ویرایشگر استاندارد ووکامرس تغییر دهید.' );
		}
	}
	return $updated;
}

function jluxe_product_manager_collect_product_changes( WC_Product $product, array $payload ) {
	$name = sanitize_text_field( jluxe_product_manager_scalar_string( $payload['name'] ?? '' ) );
	if ( '' === trim( $name ) ) {
		return new WP_Error( 'name_required', 'نام محصول نمی‌تواند خالی باشد.' );
	}
	$regular = jluxe_product_manager_clean_decimal( $payload['regularPrice'] ?? '', true );
	$sale    = jluxe_product_manager_clean_decimal( $payload['salePrice'] ?? '', true );
	$stock_qty = jluxe_product_manager_clean_decimal( $payload['stockQuantity'] ?? '', true );
	$low_stock = jluxe_product_manager_clean_decimal( $payload['lowStockAmount'] ?? '', true );
	$weight = jluxe_product_manager_clean_decimal( $payload['weight'] ?? '', true );
	$length = jluxe_product_manager_clean_decimal( $payload['length'] ?? '', true );
	$width  = jluxe_product_manager_clean_decimal( $payload['width'] ?? '', true );
	$height = jluxe_product_manager_clean_decimal( $payload['height'] ?? '', true );
	foreach ( array( $regular, $sale, $stock_qty, $low_stock, $weight, $length, $width, $height ) as $number ) {
		if ( is_wp_error( $number ) ) {
			return $number;
		}
	}
	foreach ( array( $regular, $sale, $stock_qty, $low_stock, $weight, $length, $width, $height ) as $number ) {
		if ( '' !== $number && (float) $number < 0 ) {
			return new WP_Error( 'negative_value', 'قیمت، موجودی و ابعاد باید صفر یا بیشتر باشند.' );
		}
	}
	$status = sanitize_key( jluxe_product_manager_scalar_string( $payload['status'] ?? 'draft' ) );
	if ( ! in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
		return new WP_Error( 'invalid_status', 'وضعیت انتشار معتبر نیست.' );
	}
	$visibility = sanitize_key( jluxe_product_manager_scalar_string( $payload['catalogVisibility'] ?? 'visible' ) );
	if ( ! in_array( $visibility, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
		return new WP_Error( 'invalid_visibility', 'وضعیت نمایش محصول معتبر نیست.' );
	}
	$stock_status = sanitize_key( jluxe_product_manager_scalar_string( $payload['stockStatus'] ?? 'instock' ) );
	if ( ! in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
		return new WP_Error( 'invalid_stock_status', 'وضعیت موجودی معتبر نیست.' );
	}
	$backorders = sanitize_key( jluxe_product_manager_scalar_string( $payload['backorders'] ?? 'no' ) );
	if ( ! in_array( $backorders, array( 'no', 'notify', 'yes' ), true ) ) {
		return new WP_Error( 'invalid_backorders', 'تنظیم پیش‌فروش معتبر نیست.' );
	}
	$tax_status = sanitize_key( jluxe_product_manager_scalar_string( $payload['taxStatus'] ?? 'taxable' ) );
	if ( ! in_array( $tax_status, array( 'taxable', 'shipping', 'none' ), true ) ) {
		return new WP_Error( 'invalid_tax_status', 'وضعیت مالیات معتبر نیست.' );
	}
	$tax_class = sanitize_title( jluxe_product_manager_scalar_string( $payload['taxClass'] ?? '' ) );
	$tax_classes = function_exists( 'wc_get_product_tax_class_options' ) ? wc_get_product_tax_class_options() : array( '' => 'پیش‌فرض' );
	if ( ! array_key_exists( $tax_class, $tax_classes ) ) {
		return new WP_Error( 'invalid_tax_class', 'کلاس مالیاتی معتبر نیست.' );
	}
	$shipping_class_id = absint( jluxe_product_manager_scalar_string( $payload['shippingClassId'] ?? 0 ) );
	if ( $shipping_class_id && ( ! function_exists( 'term_exists' ) || ! term_exists( $shipping_class_id, 'product_shipping_class' ) ) ) {
		return new WP_Error( 'invalid_shipping_class', 'کلاس حمل‌ونقل انتخاب‌شده پیدا نشد.' );
	}
	$category_ids = jluxe_product_manager_validate_term_ids( $payload['categories'] ?? array(), 'product_cat' );
	$tag_ids      = jluxe_product_manager_validate_term_ids( $payload['tags'] ?? array(), 'product_tag' );
	if ( is_wp_error( $category_ids ) ) {
		return $category_ids;
	}
	if ( is_wp_error( $tag_ids ) ) {
		return $tag_ids;
	}
	$brand_taxonomy = sanitize_key( jluxe_product_manager_scalar_string( $payload['brandTaxonomy'] ?? '' ) );
	$brand_ids = array();
	if ( '' !== $brand_taxonomy ) {
		$brand_ids = jluxe_product_manager_validate_term_ids( $payload['brands'] ?? array(), $brand_taxonomy );
		if ( is_wp_error( $brand_ids ) ) {
			return $brand_ids;
		}
	}
	$badges_payload = isset( $payload['badges'] ) && is_array( $payload['badges'] ) ? $payload['badges'] : array();
	$attributes = jluxe_product_manager_validate_attributes( $product, isset( $payload['attributes'] ) && is_array( $payload['attributes'] ) ? $payload['attributes'] : array() );
	if ( is_wp_error( $attributes ) ) {
		return $attributes;
	}
	$local_attributes = jluxe_product_manager_clean_local_attributes( $payload['localAttributes'] ?? array(), $product );
	if ( is_wp_error( $local_attributes ) ) {
		return $local_attributes;
	}
	$from = jluxe_product_manager_validate_sale_date( $payload['saleFrom'] ?? '' );
	$to   = jluxe_product_manager_validate_sale_date( $payload['saleTo'] ?? '' );
	if ( is_wp_error( $from ) || is_wp_error( $to ) ) {
		return is_wp_error( $from ) ? $from : $to;
	}
	$sku = wc_clean( jluxe_product_manager_scalar_string( $payload['sku'] ?? '' ) );
	if ( '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
		$sku_owner = absint( wc_get_product_id_by_sku( $sku ) );
		if ( $sku_owner && $sku_owner !== (int) $product->get_id() ) {
			return new WP_Error( 'duplicate_sku', 'این SKU قبلاً برای محصول یا تنوع دیگری ثبت شده است.' );
		}
	}
	if ( isset( $payload['imageId'] ) && ! is_scalar( $payload['imageId'] ) ) {
		return new WP_Error( 'invalid_image', 'شناسهٔ تصویر اصلی معتبر نیست.' );
	}
	$image_id = absint( jluxe_product_manager_scalar_string( $payload['imageId'] ?? 0 ) );
	$gallery_input = $payload['galleryIds'] ?? array();
	if ( ! is_array( $gallery_input ) ) {
		return new WP_Error( 'invalid_image_gallery', 'فهرست تصاویر گالری معتبر نیست.' );
	}
	$gallery_ids = array();
	foreach ( $gallery_input as $gallery_id ) {
		if ( ! is_scalar( $gallery_id ) ) {
			return new WP_Error( 'invalid_image_gallery', 'یکی از شناسه‌های تصویر گالری معتبر نیست.' );
		}
		$gallery_id = absint( $gallery_id );
		if ( $gallery_id ) {
			$gallery_ids[] = $gallery_id;
		}
	}
	$gallery_ids = array_slice( array_values( array_unique( $gallery_ids ) ), 0, 30 );
	if ( current_user_can( 'upload_files' ) ) {
		foreach ( array_merge( $image_id ? array( $image_id ) : array(), $gallery_ids ) as $attachment_id ) {
			if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'invalid_image', 'فقط فایل تصویریِ معتبر را انتخاب کنید.' );
			}
		}
	} else {
		$image_id = method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0;
		$gallery_ids = method_exists( $product, 'get_gallery_image_ids' ) ? (array) $product->get_gallery_image_ids() : array();
	}
	return array(
		'name'             => $name,
		'slug'             => sanitize_title( jluxe_product_manager_scalar_string( $payload['slug'] ?? '' ) ),
		'sku'              => $sku,
		'status'           => $status,
		'visibility'       => $visibility,
		'description'      => wp_kses_post( jluxe_product_manager_scalar_string( $payload['description'] ?? '' ) ),
		'short_description' => wp_kses_post( jluxe_product_manager_scalar_string( $payload['shortDescription'] ?? '' ) ),
		'regular_price'    => $regular,
		'sale_price'       => $sale,
		'sale_from'        => $from,
		'sale_to'          => $to,
		'manage_stock'     => ! empty( $payload['manageStock'] ),
		'stock_quantity'   => $stock_qty,
		'stock_status'     => $stock_status,
		'backorders'       => $backorders,
		'low_stock_amount' => $low_stock,
		'sold_individually'=> ! empty( $payload['soldIndividually'] ),
		'featured'         => ! empty( $payload['featured'] ),
		'virtual'          => ! empty( $payload['virtual'] ),
		'reviews_allowed'  => ! empty( $payload['reviewsAllowed'] ),
		'purchase_note'    => wp_kses_post( jluxe_product_manager_scalar_string( $payload['purchaseNote'] ?? '' ) ),
		'weight'           => $weight,
		'length'           => $length,
		'width'            => $width,
		'height'           => $height,
		'tax_status'       => $tax_status,
		'tax_class'        => $tax_class,
		'shipping_class_id'=> $shipping_class_id,
		'category_ids'     => $category_ids,
		'tag_ids'          => $tag_ids,
		'brand_taxonomy'   => $brand_taxonomy,
		'brand_ids'        => $brand_ids,
		'attributes'       => array_merge( $attributes, $local_attributes ),
		'image_id'         => $image_id,
		'gallery_ids'      => $gallery_ids,
		'faq'              => jluxe_product_manager_clean_faq( $payload['faq'] ?? array() ),
		'badges'           => array( 'authenticity' => ! empty( $badges_payload['authenticity'] ), 'warranty' => ! empty( $badges_payload['warranty'] ) ),
		'suggested_modal'  => in_array( $payload['suggestedModal'] ?? 'default', array( 'default', 'yes', 'no' ), true ) ? (string) $payload['suggestedModal'] : 'default',
		'seo'              => isset( $payload['seo'] ) && is_array( $payload['seo'] ) ? $payload['seo'] : array(),
	);
}

function jluxe_product_manager_clean_faq( $faq ): array {
	if ( ! is_array( $faq ) ) {
		return array();
	}
	$clean = array();
	foreach ( array_slice( $faq, 0, 20 ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = sanitize_text_field( jluxe_product_manager_scalar_string( $item['question'] ?? '' ) );
		$answer   = sanitize_textarea_field( jluxe_product_manager_scalar_string( $item['answer'] ?? '' ) );
		if ( '' !== $question && '' !== $answer ) {
			$clean[] = array( 'question' => $question, 'answer' => $answer );
		}
	}
	return $clean;
}

function jluxe_product_manager_variation_attribute_value( array $attributes, string $name ): ?string {
	foreach ( $attributes as $attribute_name => $value ) {
		$attribute_name = (string) $attribute_name;
		if ( 0 === strpos( $attribute_name, 'attribute_' ) ) {
			$attribute_name = substr( $attribute_name, 10 );
		}
		if ( jluxe_product_manager_taxonomy_names_match( $attribute_name, $name ) ) {
			return is_scalar( $value ) ? (string) $value : null;
		}
	}
	return null;
}

function jluxe_product_manager_validate_variation_changes( WC_Product $parent, $submitted, ?array $product_attributes = null ) {
	if ( ! is_array( $submitted ) ) {
		return new WP_Error( 'invalid_variations', 'اطلاعات تنوع‌ها معتبر نیست.' );
	}
	if ( count( $submitted ) > 100 ) {
		return new WP_Error( 'too_many_variations', 'در هر ذخیره حداکثر ۱۰۰ تنوع را تغییر دهید.' );
	}
	$variation_ids = array_fill_keys( array_filter( array_map( 'absint', (array) $parent->get_children() ) ), true );
	$variation_defs = array();
	$registered_attributes = jluxe_product_manager_registered_attributes();
	$attributes_to_check = null === $product_attributes ? (array) $parent->get_attributes() : $product_attributes;
	foreach ( $attributes_to_check as $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_variation' ) || ! $attribute->get_variation() || ! method_exists( $attribute, 'get_name' ) ) {
			continue;
		}
		$name = (string) $attribute->get_name();
		$is_taxonomy = method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy();
		if ( $is_taxonomy ) {
			$canonical_name = jluxe_product_manager_resolve_attribute_taxonomy( $name, $registered_attributes );
			if ( '' !== $canonical_name ) {
				$name = $canonical_name;
			}
		}
		$allowed_values = array();
		$options = method_exists( $attribute, 'get_options' ) ? (array) $attribute->get_options() : array();
		foreach ( $options as $option ) {
			if ( $is_taxonomy ) {
				$term = get_term( absint( $option ), $name );
				if ( is_object( $term ) && ! is_wp_error( $term ) && isset( $term->slug ) ) {
					$allowed_values[ (string) $term->slug ] = true;
				}
			} elseif ( is_scalar( $option ) ) {
				$allowed_values[ (string) $option ] = true;
			}
		}
		$variation_defs[ $name ] = $allowed_values;
	}

	$clean = array();
	$seen_ids = array();
	foreach ( $submitted as $variation_data ) {
		if ( ! is_array( $variation_data ) ) {
			return new WP_Error( 'invalid_variation_row', 'یکی از ردیف‌های تنوع معتبر نیست.' );
		}
		$id = isset( $variation_data['id'] ) ? absint( $variation_data['id'] ) : 0;
		if ( ! $id || ! isset( $variation_ids[ $id ] ) || isset( $seen_ids[ $id ] ) || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'invalid_variation_id', 'یکی از تنوع‌ها متعلق به این محصول نیست، تکراری است یا مجوز ویرایش آن را ندارید.' );
		}
		$seen_ids[ $id ] = true;
		$current_variation = wc_get_product( $id );
		if ( ! $current_variation instanceof WC_Product || ! $current_variation->is_type( 'variation' ) || (int) $current_variation->get_parent_id() !== (int) $parent->get_id() ) {
			return new WP_Error( 'invalid_variation_id', 'یکی از تنوع‌ها در این محصول دیگر وجود ندارد.' );
		}
		$regular = jluxe_product_manager_clean_decimal( $variation_data['regularPrice'] ?? '', true );
		$sale    = jluxe_product_manager_clean_decimal( $variation_data['salePrice'] ?? '', true );
		$qty     = jluxe_product_manager_clean_decimal( $variation_data['stockQuantity'] ?? '', true );
		foreach ( array( $regular, $sale, $qty ) as $value ) {
			if ( is_wp_error( $value ) || ( '' !== $value && (float) $value < 0 ) ) {
				return new WP_Error( 'invalid_variation_value', 'قیمت و موجودی تنوع باید عدد صفر یا بیشتر باشد.' );
			}
		}

		$submitted_attrs = isset( $variation_data['attributes'] ) && is_array( $variation_data['attributes'] ) ? $variation_data['attributes'] : array();
		$normalized_attrs = array();
		foreach ( $submitted_attrs as $raw_name => $raw_value ) {
			if ( ! is_scalar( $raw_value ) ) {
				return new WP_Error( 'invalid_variation_attribute', 'مقدار ویژگیِ یکی از تنوع‌ها معتبر نیست.' );
			}
			$name = (string) $raw_name;
			if ( 0 === strpos( $name, 'attribute_' ) ) {
				$name = substr( $name, 10 );
			}
			$canonical_name = '';
			foreach ( array_keys( $variation_defs ) as $definition_name ) {
				if ( jluxe_product_manager_taxonomy_names_match( $name, (string) $definition_name ) ) {
					$canonical_name = (string) $definition_name;
					break;
				}
			}
			if ( '' === $canonical_name || array_key_exists( $canonical_name, $normalized_attrs ) ) {
				return new WP_Error( 'invalid_variation_attribute', 'ویژگیِ یکی از تنوع‌ها با ویژگی‌های محصول سازگار نیست.' );
			}
			$normalized_attrs[ $canonical_name ] = (string) $raw_value;
		}

		$current_attrs = (array) $current_variation->get_attributes();
		$final_attrs = array();
		foreach ( $variation_defs as $name => $allowed_values ) {
			$value = array_key_exists( $name, $normalized_attrs )
				? $normalized_attrs[ $name ]
				: jluxe_product_manager_variation_attribute_value( $current_attrs, (string) $name );
			$value = null === $value ? '' : $value;
			if ( '' !== $value && ! isset( $allowed_values[ $value ] ) ) {
				return new WP_Error( 'invalid_variation_attribute', 'مقدار ویژگیِ یکی از تنوع‌ها دقیقاً در فهرست گزینه‌های فعلی محصول نیست.' );
			}
			$final_attrs[ $name ] = $value;
		}

		$sku_value = $variation_data['sku'] ?? '';
		if ( ! is_scalar( $sku_value ) ) {
			return new WP_Error( 'invalid_variation_sku', 'SKU تنوع معتبر نیست.' );
		}
		$sku = wc_clean( $sku_value );
		if ( '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$owner = absint( wc_get_product_id_by_sku( $sku ) );
			if ( $owner && $owner !== $id ) {
				return new WP_Error( 'duplicate_variation_sku', 'SKU یکی از تنوع‌ها قبلاً ثبت شده است.' );
			}
		}
		$stock_status = isset( $variation_data['stockStatus'] ) ? sanitize_key( jluxe_product_manager_scalar_string( $variation_data['stockStatus'] ) ) : (string) $current_variation->get_stock_status();
		$backorders = isset( $variation_data['backorders'] ) ? sanitize_key( jluxe_product_manager_scalar_string( $variation_data['backorders'] ) ) : (string) $current_variation->get_backorders();
		if ( ! in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) || ! in_array( $backorders, array( 'no', 'notify', 'yes' ), true ) ) {
			return new WP_Error( 'invalid_variation_stock', 'وضعیت موجودی یا پیش‌فروش یکی از تنوع‌ها معتبر نیست.' );
		}
		$clean[] = array(
			'id'             => $id,
			'sku'            => $sku,
			'regular_price'  => $regular,
			'sale_price'     => $sale,
			'manage_stock'   => array_key_exists( 'manageStock', $variation_data ) ? ! empty( $variation_data['manageStock'] ) : (bool) $current_variation->get_manage_stock(),
			'stock_quantity' => $qty,
			'stock_status'   => $stock_status,
			'backorders'     => $backorders,
			'enabled'        => array_key_exists( 'enabled', $variation_data ) ? ! empty( $variation_data['enabled'] ) : 'publish' === $current_variation->get_status(),
			'attributes'     => $final_attrs,
		);
	}

	// Removing a term from a variation attribute is safe only if every child
	// that used it is changed in this same request. The UI may edit children in
	// pages, so untouched rows are checked against the prospective definitions.
	foreach ( array_keys( $variation_ids ) as $variation_id ) {
		if ( isset( $seen_ids[ $variation_id ] ) ) {
			continue;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $parent->get_id() ) {
			continue;
		}
		$current_attrs = (array) $variation->get_attributes();
		foreach ( $variation_defs as $name => $allowed_values ) {
			$value = jluxe_product_manager_variation_attribute_value( $current_attrs, (string) $name );
			if ( null !== $value && '' !== $value && ! isset( $allowed_values[ $value ] ) ) {
				return new WP_Error( 'variation_attribute_in_use', 'یکی از تنوع‌های ذخیره‌شده هنوز از گزینه‌ای استفاده می‌کند که از ویژگی محصول حذف شده است؛ آن تنوع را در زبانهٔ تنوع‌ها تغییر دهید و دوباره ذخیره کنید.' );
			}
		}
	}
	return $clean;
}

function jluxe_product_manager_save_variation_changes( WC_Product $parent, array $variations ) {
	$saved = 0;
	foreach ( $variations as $changes ) {
		$variation = wc_get_product( $changes['id'] );
		if ( ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $parent->get_id() ) {
			return new WP_Error( 'variation_disappeared', 'یکی از تنوع‌ها هنگام ذخیره پیدا نشد؛ محصول را تازه‌سازی کنید.' );
		}
		$variation->set_sku( $changes['sku'] );
		$variation->set_regular_price( $changes['regular_price'] );
		$variation->set_sale_price( $changes['sale_price'] );
		$variation->set_manage_stock( $changes['manage_stock'] );
		if ( $changes['manage_stock'] && '' !== $changes['stock_quantity'] ) {
			$variation->set_stock_quantity( wc_stock_amount( $changes['stock_quantity'] ) );
		}
		$variation->set_stock_status( $changes['stock_status'] );
		$variation->set_backorders( $changes['backorders'] );
		$variation->set_status( $changes['enabled'] ? 'publish' : 'private' );
		if ( method_exists( $variation, 'set_attributes' ) ) {
			$variation->set_attributes( $changes['attributes'] );
		}
		try {
			$variation->save();
		} catch ( Throwable $error ) {
			return new WP_Error( 'variation_save_failed', 'ذخیرهٔ یکی از تنوع‌ها انجام نشد: ' . $error->getMessage() );
		}
		++$saved;
	}
	return $saved;
}

function jluxe_product_manager_sync_variable_product( WC_Product $product ): void {
	if ( $product->is_type( 'variable' ) && class_exists( 'WC_Product_Variable' ) && method_exists( 'WC_Product_Variable', 'sync' ) ) {
		WC_Product_Variable::sync( $product->get_id() );
	}
}

function jluxe_product_manager_save_product_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$payload = jluxe_product_manager_posted_payload();
	if ( ! is_array( $payload ) ) {
		wp_send_json_error( array( 'message' => 'دادهٔ فرم خوانده نشد؛ دوباره تلاش کنید.' ), 400 );
	}
	$product_id = isset( $payload['id'] ) ? absint( jluxe_product_manager_scalar_string( $payload['id'] ) ) : 0;
	$product = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! jluxe_product_manager_can_view_product( $product ) || ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد یا اجازهٔ ویرایش آن را ندارید.' ), 404 );
	}
	$changes = jluxe_product_manager_collect_product_changes( $product, $payload );
	if ( is_wp_error( $changes ) ) {
		wp_send_json_error( array( 'message' => $changes->get_error_message() ), 400 );
	}
	$variation_changes = jluxe_product_manager_validate_variation_changes( $product, $payload['variations'] ?? array(), $changes['attributes'] );
	if ( is_wp_error( $variation_changes ) ) {
		wp_send_json_error( array( 'message' => $variation_changes->get_error_message() ), 400 );
	}
	try {
		$product->set_name( $changes['name'] );
		$product->set_slug( $changes['slug'] );
		$product->set_sku( $changes['sku'] );
		$product->set_status( $changes['status'] );
		$product->set_catalog_visibility( $changes['visibility'] );
		$product->set_description( $changes['description'] );
		$product->set_short_description( $changes['short_description'] );
		$product->set_regular_price( $changes['regular_price'] );
		$product->set_sale_price( $changes['sale_price'] );
		$product->set_date_on_sale_from( $changes['sale_from'] );
		$product->set_date_on_sale_to( $changes['sale_to'] );
		$product->set_manage_stock( $changes['manage_stock'] );
		if ( $changes['manage_stock'] && '' !== $changes['stock_quantity'] ) {
			$product->set_stock_quantity( wc_stock_amount( $changes['stock_quantity'] ) );
		}
		$product->set_stock_status( $changes['stock_status'] );
		$product->set_backorders( $changes['backorders'] );
		$product->set_low_stock_amount( $changes['low_stock_amount'] );
		$product->set_sold_individually( $changes['sold_individually'] );
		$product->set_featured( $changes['featured'] );
		$product->set_virtual( $changes['virtual'] );
		$product->set_reviews_allowed( $changes['reviews_allowed'] );
		$product->set_purchase_note( $changes['purchase_note'] );
		$product->set_weight( $changes['weight'] );
		$product->set_length( $changes['length'] );
		$product->set_width( $changes['width'] );
		$product->set_height( $changes['height'] );
		$product->set_tax_status( $changes['tax_status'] );
		$product->set_tax_class( $changes['tax_class'] );
		$product->set_shipping_class_id( $changes['shipping_class_id'] );
		$product->set_category_ids( $changes['category_ids'] );
		$product->set_tag_ids( $changes['tag_ids'] );
		$product->set_attributes( $changes['attributes'] );
		if ( current_user_can( 'upload_files' ) ) {
			$product->set_image_id( $changes['image_id'] );
			$product->set_gallery_image_ids( $changes['gallery_ids'] );
		}
		$product->update_meta_data( '_jluxe_product_faq', $changes['faq'] );
		$product->update_meta_data( '_jluxe_badge_authenticity', $changes['badges']['authenticity'] ? 'yes' : 'no' );
		$product->update_meta_data( '_jluxe_badge_warranty', $changes['badges']['warranty'] ? 'yes' : 'no' );
		if ( 'default' === $changes['suggested_modal'] ) {
			$product->delete_meta_data( '_jluxe_suggested_modal_enabled' );
		} else {
			$product->update_meta_data( '_jluxe_suggested_modal_enabled', $changes['suggested_modal'] );
		}
		$product->save();
	} catch ( Throwable $error ) {
		wp_send_json_error( array( 'message' => 'ذخیرهٔ محصول انجام نشد: ' . $error->getMessage() ), 400 );
	}
	if ( '' !== $changes['brand_taxonomy'] && function_exists( 'wp_set_object_terms' ) ) {
		wp_set_object_terms( $product_id, $changes['brand_ids'], $changes['brand_taxonomy'], false );
	}
	$variation_result = jluxe_product_manager_save_variation_changes( $product, $variation_changes );
	jluxe_product_manager_sync_variable_product( $product );
	if ( is_wp_error( $variation_result ) ) {
		jluxe_purge_product_related_caches( $product_id );
		wp_send_json_error( array( 'message' => $variation_result->get_error_message(), 'partial' => true ), 500 );
	}
	$seo_key = jluxe_product_manager_seo_key( $product_id );
	if ( '' !== $seo_key ) {
		update_post_meta( $product_id, $seo_key, sanitize_text_field( jluxe_product_manager_scalar_string( $changes['seo']['focusKeyword'] ?? '' ) ) );
		if ( 0 === strpos( $seo_key, 'rank_math_' ) ) {
			update_post_meta( $product_id, 'rank_math_title', sanitize_text_field( jluxe_product_manager_scalar_string( $changes['seo']['title'] ?? '' ) ) );
			update_post_meta( $product_id, 'rank_math_description', sanitize_text_field( jluxe_product_manager_scalar_string( $changes['seo']['description'] ?? '' ) ) );
		} else {
			update_post_meta( $product_id, '_yoast_wpseo_title', sanitize_text_field( jluxe_product_manager_scalar_string( $changes['seo']['title'] ?? '' ) ) );
			update_post_meta( $product_id, '_yoast_wpseo_metadesc', sanitize_text_field( jluxe_product_manager_scalar_string( $changes['seo']['description'] ?? '' ) ) );
		}
	}
	jluxe_purge_product_related_caches( $product_id );
	wp_send_json_success(
		array(
			'message'   => $variation_result > 0 ? 'محصول و ' . $variation_result . ' تنوع ذخیره شد.' : 'تغییرات محصول ذخیره شد.',
			'product'   => jluxe_product_manager_product_payload( wc_get_product( $product_id ), 1 ),
		)
	);
}
add_action( 'wp_ajax_jluxe_product_manager_save_product', 'jluxe_product_manager_save_product_ajax' );

function jluxe_product_manager_save_quick_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$product_id = isset( $_POST['product_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['product_id'] ) ) ) : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! $product instanceof WC_Product || ! jluxe_product_manager_can_view_product( $product ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد یا اجازهٔ ویرایش آن را ندارید.' ), 404 );
	}
	if ( ! $product->is_type( 'simple' ) ) {
		wp_send_json_error( array( 'message' => 'قیمت و موجودی محصول متغیر را از جدول تنوع‌ها ویرایش کنید.' ), 400 );
	}
	$regular = jluxe_product_manager_clean_decimal( $_POST['regular_price'] ?? '', true );
	$sale    = jluxe_product_manager_clean_decimal( $_POST['sale_price'] ?? '', true );
	$qty     = jluxe_product_manager_clean_decimal( $_POST['stock_quantity'] ?? '', true );
	foreach ( array( $regular, $sale, $qty ) as $value ) {
		if ( is_wp_error( $value ) || ( '' !== $value && (float) $value < 0 ) ) {
			wp_send_json_error( array( 'message' => is_wp_error( $value ) ? $value->get_error_message() : 'قیمت و موجودی باید صفر یا بیشتر باشند.' ), 400 );
		}
	}
	try {
		$product->set_regular_price( $regular );
		$product->set_sale_price( $sale );
		$product->set_manage_stock( ! empty( $_POST['manage_stock'] ) );
		if ( ! empty( $_POST['manage_stock'] ) && '' !== $qty ) {
			$product->set_stock_quantity( wc_stock_amount( $qty ) );
			$product->set_stock_status( (float) $qty > 0 ? 'instock' : 'outofstock' );
		}
		$product->save();
	} catch ( Throwable $error ) {
		wp_send_json_error( array( 'message' => 'ذخیره انجام نشد: ' . $error->getMessage() ), 400 );
	}
	jluxe_purge_product_related_caches( $product_id );
	wp_send_json_success( array( 'message' => 'قیمت و موجودی ذخیره شد.', 'product' => jluxe_product_manager_product_summary( wc_get_product( $product_id ) ) ) );
}
add_action( 'wp_ajax_jluxe_product_manager_quick_save', 'jluxe_product_manager_save_quick_ajax' );

function jluxe_product_manager_variation_combination_exists( WC_Product $parent, int $ignore_id, array $attributes ): bool {
	if ( empty( $attributes ) ) {
		return false;
	}
	foreach ( (array) $parent->get_children() as $child_id ) {
		$child_id = absint( $child_id );
		if ( $child_id < 1 || $child_id === $ignore_id ) {
			continue;
		}
		$other = wc_get_product( $child_id );
		if ( ! $other instanceof WC_Product || ! $other->is_type( 'variation' ) || (int) $other->get_parent_id() !== (int) $parent->get_id() ) {
			continue;
		}
		$other_attributes = (array) $other->get_attributes();
		$same = true;
		foreach ( $attributes as $name => $value ) {
			$other_value = jluxe_product_manager_variation_attribute_value( $other_attributes, (string) $name );
			if ( (string) ( null === $other_value ? '' : $other_value ) !== (string) $value ) {
				$same = false;
				break;
			}
		}
		if ( $same ) {
			return true;
		}
	}
	return false;
}

function jluxe_product_manager_save_variation_quick_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$variation_id = isset( $_POST['variation_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['variation_id'] ) ) ) : 0;
	$parent_id    = isset( $_POST['parent_id'] ) ? absint( jluxe_product_manager_scalar_string( wp_unslash( $_POST['parent_id'] ) ) ) : 0;
	$parent       = $parent_id ? wc_get_product( $parent_id ) : false;
	$variation    = $variation_id ? wc_get_product( $variation_id ) : false;
	if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || ! jluxe_product_manager_can_view_product( $parent ) || ! $variation instanceof WC_Product || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $parent_id ) {
		wp_send_json_error( array( 'message' => 'محصول متغیر یا تنوع برای ویرایش پیدا نشد.' ), 404 );
	}
	$raw_variation = isset( $_POST['variation'] ) && is_scalar( $_POST['variation'] ) ? (string) wp_unslash( $_POST['variation'] ) : '';
	$submitted     = json_decode( $raw_variation, true );
	if ( ! is_array( $submitted ) || ( isset( $submitted['id'] ) && absint( $submitted['id'] ) !== $variation_id ) ) {
		wp_send_json_error( array( 'message' => 'اطلاعات تنوع برای ذخیره معتبر نیست.' ), 400 );
	}
	$submitted['id']            = $variation_id;
	$submitted['sku']           = array_key_exists( 'sku', $submitted ) ? $submitted['sku'] : (string) $variation->get_sku();
	$submitted['regularPrice']  = array_key_exists( 'regularPrice', $submitted ) ? $submitted['regularPrice'] : (string) $variation->get_regular_price( 'edit' );
	$submitted['salePrice']     = array_key_exists( 'salePrice', $submitted ) ? $submitted['salePrice'] : (string) $variation->get_sale_price( 'edit' );
	$submitted['stockQuantity'] = array_key_exists( 'stockQuantity', $submitted ) ? $submitted['stockQuantity'] : ( null === $variation->get_stock_quantity( 'edit' ) ? '' : $variation->get_stock_quantity( 'edit' ) );
	$submitted['manageStock']   = array_key_exists( 'manageStock', $submitted ) ? ! empty( $submitted['manageStock'] ) : (bool) $variation->get_manage_stock();
	$submitted['stockStatus']   = array_key_exists( 'stockStatus', $submitted ) ? $submitted['stockStatus'] : (string) $variation->get_stock_status();
	$submitted['backorders']    = array_key_exists( 'backorders', $submitted ) ? $submitted['backorders'] : (string) $variation->get_backorders();
	$submitted['enabled']       = array_key_exists( 'enabled', $submitted ) ? ! empty( $submitted['enabled'] ) : 'publish' === $variation->get_status();
	$submitted['attributes']    = isset( $submitted['attributes'] ) && is_array( $submitted['attributes'] ) ? $submitted['attributes'] : (array) $variation->get_attributes();

	$validated = jluxe_product_manager_validate_variation_changes( $parent, array( $submitted ) );
	if ( is_wp_error( $validated ) ) {
		wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
	}
	$attributes_changed = false;
	$current_attributes = (array) $variation->get_attributes();
	foreach ( $validated[0]['attributes'] as $name => $value ) {
		$current_value = jluxe_product_manager_variation_attribute_value( $current_attributes, (string) $name );
		if ( (string) ( null === $current_value ? '' : $current_value ) !== (string) $value ) {
			$attributes_changed = true;
			break;
		}
	}
	if ( $attributes_changed && jluxe_product_manager_variation_combination_exists( $parent, $variation_id, $validated[0]['attributes'] ) ) {
		wp_send_json_error( array( 'message' => 'این ترکیب ویژگی برای تنوع دیگری استفاده شده است؛ مقدار دیگری انتخاب کنید.' ), 400 );
	}
	$saved = jluxe_product_manager_save_variation_changes( $parent, $validated );
	if ( is_wp_error( $saved ) ) {
		wp_send_json_error( array( 'message' => $saved->get_error_message() ), 500 );
	}
	jluxe_product_manager_sync_variable_product( $parent );
	jluxe_purge_product_related_caches( $variation_id );
	$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
	$variation_payload = jluxe_product_manager_variation_payload( $parent, $page );
	$updated_variation = null;
	foreach ( $variation_payload['items'] as $item ) {
		if ( (int) $item['id'] === $variation_id ) {
			$updated_variation = $item;
			break;
		}
	}
	wp_send_json_success( array( 'message' => 'تنوع ذخیره شد.', 'variation' => $updated_variation ) );
}
add_action( 'wp_ajax_jluxe_product_manager_quick_save_variation', 'jluxe_product_manager_save_variation_quick_ajax' );

function jluxe_product_manager_bulk_ajax(): void {
	jluxe_product_manager_ajax_guard();
	$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array();
	$ids = array();
	foreach ( $raw_ids as $raw_id ) {
		if ( ! is_scalar( $raw_id ) || 0 === absint( $raw_id ) ) {
			wp_send_json_error( array( 'message' => 'فهرست محصولات انتخاب‌شده معتبر نیست.' ), 400 );
		}
		$ids[] = absint( $raw_id );
	}
	$ids = array_values( array_unique( $ids ) );
	if ( empty( $ids ) || count( $ids ) > 25 ) {
		wp_send_json_error( array( 'message' => 'از ۱ تا ۲۵ محصول را انتخاب کنید.' ), 400 );
	}
	$field = isset( $_POST['field'] ) ? sanitize_key( jluxe_product_manager_scalar_string( wp_unslash( $_POST['field'] ) ) ) : '';
	$operation = isset( $_POST['operation'] ) ? sanitize_key( jluxe_product_manager_scalar_string( wp_unslash( $_POST['operation'] ) ) ) : '';
	$value = jluxe_product_manager_clean_decimal( $_POST['value'] ?? '', false );
	if ( is_wp_error( $value ) || (float) $value < 0 || ! in_array( $field, array( 'regular_price', 'stock_quantity' ), true ) ) {
		wp_send_json_error( array( 'message' => 'عملیات گروهی یا مقدار واردشده معتبر نیست.' ), 400 );
	}
	$allowed_operations = 'regular_price' === $field ? array( 'set', 'increase', 'decrease', 'percent_increase', 'percent_decrease' ) : array( 'set', 'increase', 'decrease' );
	if ( ! in_array( $operation, $allowed_operations, true ) ) {
		wp_send_json_error( array( 'message' => 'این نوع تغییر برای فیلد انتخاب‌شده پشتیبانی نمی‌شود.' ), 400 );
	}
	$saved = 0;
	$skipped = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || ! jluxe_product_manager_can_view_product( $product ) ) {
			$skipped[] = $id;
			continue;
		}
		try {
			if ( 'regular_price' === $field ) {
				$current = (float) $product->get_regular_price( 'edit' );
				$amount  = (float) $value;
				$new_value = $amount;
				if ( 'increase' === $operation ) {
					$new_value = $current + $amount;
				} elseif ( 'decrease' === $operation ) {
					$new_value = max( 0, $current - $amount );
				} elseif ( 'percent_increase' === $operation ) {
					$new_value = $current * ( 1 + ( $amount / 100 ) );
				} elseif ( 'percent_decrease' === $operation ) {
					$new_value = $current * max( 0, 1 - ( $amount / 100 ) );
				}
				$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
				$product->set_regular_price( number_format( $new_value, $decimals, '.', '' ) );
			} else {
				$current = null === $product->get_stock_quantity( 'edit' ) ? 0 : (float) $product->get_stock_quantity( 'edit' );
				$amount  = (float) $value;
				$new_value = $amount;
				if ( 'increase' === $operation ) {
					$new_value = $current + $amount;
				} elseif ( 'decrease' === $operation ) {
					$new_value = max( 0, $current - $amount );
				}
				$product->set_manage_stock( true );
				$product->set_stock_quantity( wc_stock_amount( $new_value ) );
				$product->set_stock_status( $new_value > 0 ? 'instock' : 'outofstock' );
			}
			$product->save();
			jluxe_purge_product_related_caches( $id );
			++$saved;
		} catch ( Throwable $error ) {
			$skipped[] = $id;
		}
	}
	wp_send_json_success( array( 'saved' => $saved, 'skipped' => $skipped, 'message' => $saved . ' محصول ذخیره شد؛ ' . count( $skipped ) . ' مورد رد شد.' ) );
}
add_action( 'wp_ajax_jluxe_product_manager_bulk', 'jluxe_product_manager_bulk_ajax' );
