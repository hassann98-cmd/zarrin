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

/** Normalize WooCommerce-generated price markup for a compact, safe REST text value. */
function jluxe_recent_price_markup_text( string $price_html ): string {
	$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $price_html ) : strip_tags( $price_html );

	// WooCommerce price filters sometimes double-escape entities (for example, &amp;ndash;).
	for ( $pass = 0; $pass < 3; $pass++ ) {
		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded === $text ) {
			break;
		}
		$text = $decoded;
	}

	$text = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF" ), ' ', $text );
	$dashed_text = preg_replace( '/\s*([–—])\s*/u', ' $1 ', $text );
	if ( is_string( $dashed_text ) ) {
		$text = $dashed_text;
	}

	// Some price-range extensions append a second textual range after WooCommerce's range.
	$range_pattern = '/محدوده[\s\p{Z}]*قیمت[\s\p{Z}]*:\s*([0-9۰-۹٠-٩][0-9۰-۹٠-٩,٬،٫.]*)\s*تا\s*([0-9۰-۹٠-٩][0-9۰-۹٠-٩,٬،٫.]*)/u';
	if ( preg_match( $range_pattern, $text, $range, PREG_OFFSET_CAPTURE ) ) {
		$prefix = rtrim( substr( $text, 0, $range[0][1] ) );
		if ( preg_match_all( '/[0-9۰-۹٠-٩][0-9۰-۹٠-٩,٬،٫.]*/u', $prefix, $prefix_numbers ) && count( $prefix_numbers[0] ) >= 2 ) {
			$digit_map = array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			);
			$normalize_number = static function ( string $number ) use ( $digit_map ): string {
				$number = strtr( $number, $digit_map );
				$number = str_replace( array( ',', '٬', '،', '٫' ), array( '', '', '', '.' ), $number );
				$number = preg_replace( '/[^0-9.]/', '', $number );
				$parts  = explode( '.', (string) $number, 2 );
				$whole  = ltrim( $parts[0], '0' );
				$whole  = '' === $whole ? '0' : $whole;
				$fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
				return $whole . ( '' !== $fraction ? '.' . $fraction : '' );
			};
			$last_two = array_slice( $prefix_numbers[0], -2 );
			if (
				$normalize_number( $last_two[0] ) === $normalize_number( $range[1][0] )
				&& $normalize_number( $last_two[1] ) === $normalize_number( $range[2][0] )
			) {
				$text = $prefix;
			}
		}
	}

	$normalized = preg_replace( '/[\s\p{Z}]+/u', ' ', trim( $text ) );
	return is_string( $normalized ) ? $normalized : trim( $text );
}

/** Return WooCommerce's compact plain-text price as a fallback for product types without numeric getters. */
function jluxe_recent_product_price_text( WC_Product $product ): string {
	return jluxe_recent_price_markup_text( (string) $product->get_price_html() );
}

/** Format one WooCommerce amount using the store's active currency and display rules. */
function jluxe_recent_price_amount_text( $amount ): string {
	if ( ! is_numeric( $amount ) || ! function_exists( 'wc_price' ) ) {
		return '';
	}
	return jluxe_recent_price_markup_text( (string) wc_price( $amount ) );
}

/** Format a variable product range without leaking any extension's duplicate range label. */
function jluxe_recent_price_range_text( $minimum, $maximum ): string {
	$minimum_text = jluxe_recent_price_amount_text( $minimum );
	$maximum_text = jluxe_recent_price_amount_text( $maximum );
	if ( '' === $minimum_text ) {
		return $maximum_text;
	}
	if ( '' === $maximum_text || $minimum_text === $maximum_text ) {
		return $minimum_text;
	}

	if ( function_exists( 'wc_format_price_range' ) ) {
		$range_text = jluxe_recent_price_markup_text( (string) wc_format_price_range( $minimum, $maximum ) );
		if ( '' !== $range_text ) {
			return $range_text;
		}
	}

	return $minimum_text . ' – ' . $maximum_text;
}

/** Return display-tax-aware, structured prices for cards in recently viewed history. */
function jluxe_recent_product_price_data( WC_Product $product ): array {
	$fallback = jluxe_recent_product_price_text( $product );
	$data     = array(
		'currentPrice' => $fallback,
		'regularPrice' => '',
		'priceIsRange' => false,
		'onSale'       => false,
	);

	if (
		$product->is_type( 'variable' )
		&& method_exists( $product, 'get_variation_price' )
		&& method_exists( $product, 'get_variation_regular_price' )
		&& function_exists( 'wc_price' )
	) {
		try {
			$current_min = $product->get_variation_price( 'min', true );
			$current_max = $product->get_variation_price( 'max', true );
			if ( is_numeric( $current_min ) && is_numeric( $current_max ) && ( (float) $current_min > 0 || (float) $current_max > 0 ) ) {
				$current_text = jluxe_recent_price_range_text( $current_min, $current_max );
				if ( '' !== $current_text ) {
					$data['currentPrice'] = $current_text;
					$minimum_text         = jluxe_recent_price_amount_text( $current_min );
					$maximum_text         = jluxe_recent_price_amount_text( $current_max );
					$data['priceIsRange'] = '' !== $maximum_text && $minimum_text !== $maximum_text;
					$data['onSale']       = method_exists( $product, 'is_on_sale' ) && (bool) $product->is_on_sale();

					if ( $data['onSale'] ) {
						$regular_min = $product->get_variation_regular_price( 'min', true );
						$regular_max = $product->get_variation_regular_price( 'max', true );
						if (
							is_numeric( $regular_min )
							&& is_numeric( $regular_max )
							&& ( (float) $regular_min > (float) $current_min || (float) $regular_max > (float) $current_max )
						) {
							$data['regularPrice'] = jluxe_recent_price_range_text( $regular_min, $regular_max );
					}
					}
					return $data;
				}
			}
		} catch ( Throwable $error ) {
			// A third-party price getter can fail; the safe, stripped WooCommerce HTML remains available.
		}
	}

	if ( ! $product->is_type( 'variable' ) && method_exists( $product, 'get_price' ) && method_exists( $product, 'get_regular_price' ) && function_exists( 'wc_price' ) ) {
		try {
			$raw_current    = $product->get_price();
			$raw_regular    = $product->get_regular_price();
			$current_amount = is_numeric( $raw_current ) ? $raw_current : null;
			$regular_amount = is_numeric( $raw_regular ) ? $raw_regular : null;
			if ( function_exists( 'wc_get_price_to_display' ) ) {
				if ( null !== $current_amount ) {
					$current_amount = wc_get_price_to_display( $product, array( 'price' => $raw_current ) );
				}
				if ( null !== $regular_amount ) {
					$regular_amount = wc_get_price_to_display( $product, array( 'price' => $raw_regular ) );
				}
			}

			$current_text = null !== $current_amount ? jluxe_recent_price_amount_text( $current_amount ) : '';
			if ( '' !== $current_text ) {
				$data['currentPrice'] = $current_text;
			}
			$data['onSale'] =
				method_exists( $product, 'is_on_sale' )
				&& (bool) $product->is_on_sale()
				&& is_numeric( $raw_current )
				&& is_numeric( $raw_regular )
				&& (float) $raw_regular > (float) $raw_current;
			if ( $data['onSale'] && null !== $regular_amount ) {
				$data['regularPrice'] = jluxe_recent_price_amount_text( $regular_amount );
			}
		} catch ( Throwable $error ) {
			// Retain the stripped WooCommerce price HTML as the error-safe fallback.
		}
	}

	return $data;
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

			$image_id   = (int) $product->get_image_id();
			$image      = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
			$price_data = jluxe_recent_product_price_data( $product );
			if ( ! $image && function_exists( 'wc_placeholder_img_src' ) ) {
				$image = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}
			$items[] = array(
				'id'           => $product_id,
				'name'         => (string) $product->get_name(),
				'url'          => (string) $product->get_permalink(),
				'image'        => (string) $image,
				'imageAlt'     => (string) $product->get_name(),
				'price'        => $price_data['currentPrice'],
				'currentPrice' => $price_data['currentPrice'],
				'regularPrice' => $price_data['regularPrice'],
				'priceIsRange' => $price_data['priceIsRange'],
				'onSale'       => $price_data['onSale'],
				'inStock'      => jluxe_recent_product_is_in_stock( $product ),
			);
			if ( count( $items ) >= 8 ) {
				break;
			}
		}
	}

	$response = new WP_REST_Response( array( 'items' => $items ), 200 );
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );
	return $response;
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
		data-jluxe-recent-item-count="0"
		dir="rtl"
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
				<div class="jluxe-recent-products__tools">
					<span class="jluxe-recent-products__count" data-jluxe-recent-count hidden aria-live="polite" aria-atomic="true"></span>
					<div class="jluxe-recent-products__nav" data-jluxe-recent-nav role="group" aria-label="پیمایش محصولات بازدیدشده" hidden>
						<button type="button" class="jluxe-recent-products__nav-button" data-jluxe-recent-prev aria-controls="<?php echo esc_attr( $panel_id . '-list' ); ?>" aria-label="محصول جدیدتر">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
						</button>
						<button type="button" class="jluxe-recent-products__nav-button" data-jluxe-recent-next aria-controls="<?php echo esc_attr( $panel_id . '-list' ); ?>" aria-label="محصول قدیمی‌تر">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7" /></svg>
						</button>
					</div>
					<span class="jluxe-recent-products__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="8.5" />
							<path d="M12 7v5l3 2" />
						</svg>
					</span>
				</div>
			</div>
			<div id="<?php echo esc_attr( $panel_id . '-list' ); ?>" class="jluxe-recent-products__list" data-jluxe-recent-list role="list" aria-label="محصولات بازدیدشده؛ برای پیمایش از کلیدهای جهت‌دار استفاده کنید" aria-keyshortcuts="ArrowLeft ArrowRight Home End" tabindex="0"></div>
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
