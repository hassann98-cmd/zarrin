<?php
/** Account wishlist synchronization and first-party recently-viewed products. */
defined( 'ABSPATH' ) || exit;

const JLUXE_WISHLIST_META_KEY = '_jluxe_wishlist_product_ids';

/**
 * Normalize a wishlist payload and retain only unique, public parent products.
 *
 * @param mixed $ids Untrusted IDs from REST or legacy user meta.
 * @return array<int, int>
 */
function jluxe_normalize_wishlist_product_ids( $ids, int $limit = 200 ): array {
	if ( ! is_array( $ids ) || ! function_exists( 'wc_get_product' ) ) {
		return array();
	}

	$clean = array();
	foreach ( $ids as $raw_id ) {
		if ( ! is_scalar( $raw_id ) || ! is_numeric( $raw_id ) ) {
			continue;
		}
		$product_id = absint( $raw_id );
		if ( ! $product_id || isset( $clean[ $product_id ] ) ) {
			continue;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) || ! jluxe_product_is_public( $product ) ) {
			continue;
		}
		if ( method_exists( $product, 'is_visible' ) && ! $product->is_visible() ) {
			continue;
		}
		$clean[ $product_id ] = $product_id;
		if ( count( $clean ) >= max( 1, $limit ) ) {
			break;
		}
	}

	return array_values( $clean );
}

/** REST permission is scoped to the authenticated WordPress cookie/user. */
function jluxe_wishlist_rest_allowed(): bool {
	return is_user_logged_in() && current_user_can( 'read' );
}

function jluxe_rest_wishlist_get( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	$ids     = jluxe_normalize_wishlist_product_ids( get_user_meta( $user_id, JLUXE_WISHLIST_META_KEY, true ) );
	$response = new WP_REST_Response( array( 'ids' => $ids ), 200 );
	foreach ( function_exists( 'jluxe_private_rest_headers' ) ? jluxe_private_rest_headers() : array( 'Cache-Control' => 'private, no-store, max-age=0' ) as $name => $value ) {
		$response->header( $name, $value );
	}
	return $response;
}

function jluxe_rest_wishlist_update( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	$ids     = jluxe_normalize_wishlist_product_ids( $request->get_param( 'ids' ) );
	if ( false === update_user_meta( $user_id, JLUXE_WISHLIST_META_KEY, $ids ) ) {
		return new WP_Error( 'jluxe_wishlist_storage', 'ذخیرهٔ علاقه‌مندی‌ها انجام نشد؛ دوباره تلاش کنید.', array( 'status' => 503 ) );
	}
	$response = new WP_REST_Response( array( 'ids' => $ids ), 200 );
	foreach ( function_exists( 'jluxe_private_rest_headers' ) ? jluxe_private_rest_headers() : array( 'Cache-Control' => 'private, no-store, max-age=0' ) as $name => $value ) {
		$response->header( $name, $value );
	}
	return $response;
}

/**
 * A user's guest wishlist is merged by the browser after authentication. This
 * endpoint never accepts a user ID, so callers cannot write to another account.
 */
function jluxe_register_wishlist_rest_route(): void {
	register_rest_route(
		'jluxe/v1',
		'/wishlist',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'jluxe_rest_wishlist_get',
				'permission_callback' => 'jluxe_wishlist_rest_allowed',
			),
			array(
				'methods'             => 'PUT',
				'callback'            => 'jluxe_rest_wishlist_update',
				'permission_callback' => 'jluxe_wishlist_rest_allowed',
				'args'                => array(
					'ids' => array(
						'required'          => true,
						'type'              => 'array',
						'maxItems'          => 200,
						'items'             => array( 'type' => 'integer', 'minimum' => 1 ),
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_wishlist_rest_route' );

/**
 * A variable product is available when at least one purchasable child variation has stock.
 * WooCommerce can leave a variable parent's cached stock status stale after variation edits.
 */
function jluxe_recent_product_is_in_stock( WC_Product $product ): bool {
	if ( ! $product->is_type( 'variable' ) ) {
		return (bool) $product->is_in_stock();
	}

	if ( ! method_exists( $product, 'get_children' ) || ! function_exists( 'wc_get_product' ) ) {
		return false;
	}

	foreach ( (array) $product->get_children() as $variation_id ) {
		$variation = wc_get_product( absint( $variation_id ) );
		if (
			$variation instanceof WC_Product
			&& $variation->is_type( 'variation' )
			&& $variation->is_purchasable()
			&& $variation->is_in_stock()
		) {
			return true;
		}
	}

	return false;
}

/** Read at most eight public products, in the order supplied by localStorage. */
function jluxe_rest_recent_products( WP_REST_Request $request ) {
	$raw_ids = $request->get_param( 'ids' );
	$ids     = is_array( $raw_ids ) ? $raw_ids : explode( ',', (string) $raw_ids );
	$items   = array();
	$seen    = array();

	if ( function_exists( 'wc_get_product' ) ) {
		foreach ( $ids as $raw_id ) {
			if ( ! is_scalar( $raw_id ) || ! is_numeric( $raw_id ) ) {
				continue;
			}
			$product_id = absint( $raw_id );
			if ( ! $product_id || isset( $seen[ $product_id ] ) ) {
				continue;
			}
			$seen[ $product_id ] = true;
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) || ! jluxe_product_is_public( $product ) ) {
				continue;
			}
			if ( method_exists( $product, 'is_visible' ) && ! $product->is_visible() ) {
				continue;
			}

			$image_id = (int) $product->get_image_id();
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
			if ( ! $image && function_exists( 'wc_placeholder_img_src' ) ) {
				$image = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}
			$items[] = array(
				'id'        => $product_id,
				'name'      => (string) $product->get_name(),
				'url'       => (string) $product->get_permalink(),
				'image'     => (string) $image,
				'imageAlt'  => (string) $product->get_name(),
				'price'     => function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $product->get_price_html() ) : strip_tags( (string) $product->get_price_html() ),
				'inStock'   => jluxe_recent_product_is_in_stock( $product ),
			);
			if ( count( $items ) >= 8 ) {
				break;
			}
		}
	}

	return new WP_REST_Response( array( 'items' => $items ), 200 );
}

function jluxe_register_recent_products_rest_route(): void {
	register_rest_route(
		'jluxe/v1',
		'/recent-products',
		array(
			'methods'             => 'GET',
			'callback'            => 'jluxe_rest_recent_products',
			'permission_callback' => '__return_true',
			'args'                => array(
				'ids' => array(
					'required'          => true,
					'type'              => 'string',
					'maxLength'         => 512,
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_recent_products_rest_route' );

/** A hidden client-rendered panel has zero height until private local history exists. */
function jluxe_render_recent_products_panel( string $context = 'product' ): void {
	$is_home  = 'home' === $context;
	$heading  = $is_home ? 'ادامه خرید شما' : 'اخیراً دیده‌اید';
	$subtitle = $is_home
		? 'محصولاتی که اخیراً بررسی کرده‌اید، اینجا نگه داشته‌ایم.'
		: 'برای مقایسه و انتخاب دوباره، به محصولات بازدیدشده برگردید.';
	$panel_id = $is_home ? 'jluxe-recent-products-home' : 'jluxe-recent-products-product';
	?>
	<section
		id="<?php echo esc_attr( $panel_id ); ?>"
		class="jluxe-recent-products"
		data-jluxe-recent-products
		data-jluxe-recent-context="<?php echo esc_attr( $is_home ? 'home' : 'product' ); ?>"
		hidden
		aria-labelledby="<?php echo esc_attr( $panel_id . '-title' ); ?>"
	>
		<div class="jluxe-recent-products__inner">
			<div class="jluxe-recent-products__heading">
				<div class="jluxe-recent-products__copy">
					<span class="jluxe-recent-products__eyebrow">بازدیدهای اخیر</span>
					<h2 id="<?php echo esc_attr( $panel_id . '-title' ); ?>" class="jluxe-recent-products__title"><?php echo esc_html( $heading ); ?></h2>
					<p class="jluxe-recent-products__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				</div>
				<span class="jluxe-recent-products__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="8.5" />
						<path d="M12 7v5l3 2" />
					</svg>
				</span>
			</div>
			<div class="jluxe-recent-products__list" data-jluxe-recent-list role="list" aria-label="محصولات بازدیدشده"></div>
		</div>
	</section>
	<?php
}

/** The wishlist and product-history bundle is only useful where Woo UI can exist. */
function jluxe_enqueue_storefront_personalization_assets(): void {
	if ( ! function_exists( 'wc_get_product' ) || empty( jluxe_asset_plan()['theme_woo_ux'] ) ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/storefront-personalization.js';
	wp_enqueue_script(
		'jluxe-storefront-personalization',
		JLUXE_THEME_URI . '/assets/js/storefront-personalization.js',
		array( 'jluxe-woocommerce' ),
		file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0',
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	wp_localize_script(
		'jluxe-storefront-personalization',
		'jluxePersonalizationSettings',
		array(
			'wishlistUrl'       => esc_url_raw( rest_url( 'jluxe/v1/wishlist' ) ),
			'recentProductsUrl' => esc_url_raw( rest_url( 'jluxe/v1/recent-products' ) ),
			'sessionUrl'        => admin_url( 'admin-ajax.php' ),
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'isLoggedIn'        => is_user_logged_in(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_storefront_personalization_assets', 25 );
