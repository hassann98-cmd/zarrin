<?php
/** Shared input, visibility and abuse controls. No WooCommerce session-table access. */
defined( 'ABSPATH' ) || exit;

function jluxe_ascii_digits( string $value ): string {
	return strtr( $value, array_combine(
		preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
		str_split( '01234567890123456789' )
	) );
}

/** Iranian mobile numbers only; never turn arbitrary trailing digits into an identity. */
function jluxe_normalize_phone( string $phone ): string {
	$phone = preg_replace( '/[\s()\-]+/u', '', jluxe_ascii_digits( $phone ) );
	if ( ! is_string( $phone ) || ! preg_match( '/^(?:(?:\+|00)?98|0)?(9[0-9]{9})$/D', $phone, $match ) ) {
		return '';
	}
	return $match[1];
}

/** Redact common contact details before public user text is sent to an AI provider. */
function jluxe_redact_public_contact_details( string $text ): string {
	$email_pattern = '/(?<![\p{L}\p{N}._%+\-])[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}(?![\p{L}\p{N}\-])/iu';
	$redacted      = preg_replace( $email_pattern, '[ایمیل حذف شد]', $text );
	if ( is_string( $redacted ) ) {
		$text = $redacted;
	}

	$digit        = '[0-9۰-۹٠-٩]';
	$zero         = '[0۰٠]';
	$nine         = '[9۹٩]';
	$eight        = '[8۸٨]';
	$separator    = '[\\s().\\-]*';
	$country_code = '(?:(?:\\+|' . $zero . $zero . ')?' . $nine . $eight . $separator . ')?';
	$phone_pattern = '/(?<![\\p{L}\\p{N}])(?:' . $country_code . $nine . '(?:' . $separator . $digit . '){9}|' . $zero . $separator . $nine . '(?:' . $separator . $digit . '){9}|' . $nine . '(?:' . $separator . $digit . '){9})(?![\\p{L}\\p{N}])/u';
	$redacted     = preg_replace( $phone_pattern, '[شماره تماس حذف شد]', $text );
	return is_string( $redacted ) ? $redacted : $text;
}

function jluxe_product_is_public( $product ): bool {
	if ( ! $product instanceof WC_Product || ! $product->exists() || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility() ) {
		return false;
	}
	// A password-protected product is not suitable for anonymous tools, even after a cookie unlock.
	if ( '' !== (string) get_post_field( 'post_password', $product->get_id() ) ) {
		return false;
	}
	if ( $product->is_type( 'variation' ) ) {
		$parent_id = $product->get_parent_id();
		return $parent_id && $parent_id !== $product->get_id() && jluxe_product_is_public( wc_get_product( $parent_id ) );
	}
	return true;
}

/**
 * DB-backed mutex, independent of the object-cache implementation.
 * add_option() is NOT a mutex: its upsert can overwrite a concurrent owner's value.
 * The options table's unique option_name plus INSERT IGNORE supplies the atomic claim.
 * The token-matched release cannot delete a replacement owner's lock after expiry.
 */
function jluxe_security_lock( string $scope, int $ttl = 120 ): ?array {
	global $wpdb;
	$key = 'jluxe_lock_' . hash( 'sha256', $scope );
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	if ( null !== $old && (int) $old < time() ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $old ) );
	}
	$value = ( time() + $ttl ) . ':' . wp_generate_password( 32, false, false );
	$claimed = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $value
	) );
	return 1 === $claimed ? array( $key, $value ) : null;
}

function jluxe_security_unlock( array $lock ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock[0], $lock[1] ) );
}

/** Atomic compare-and-delete for a one-use OTP record, including persistent-cache invalidation. */
function jluxe_consume_option( string $key, array $expected ): bool {
	global $wpdb;
	$deleted = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $expected )
	) );
	wp_cache_delete( $key, 'options' );
	return 1 === $deleted;
}

/**
 * Atomically checks and charges several fixed-window limits as one request.
 *
 * All bucket locks are acquired in a stable order, and no counter changes until
 * every bucket has capacity. This prevents a request rejected by one quota from
 * consuming another quota. Existing single-bucket callers keep using the
 * simpler helper below.
 *
 * @param array<int,array{bucket:string,identity:string,max:int,window:int}> $limits
 */
function jluxe_security_rate_limits( array $limits ): bool {
	if ( empty( $limits ) ) {
		return true;
	}

	$pending = array();
	foreach ( $limits as $limit ) {
		if ( ! is_array( $limit ) ) {
			return false;
		}
		$bucket   = (string) ( $limit['bucket'] ?? '' );
		$identity = (string) ( $limit['identity'] ?? '' );
		$max      = (int) ( $limit['max'] ?? 0 );
		$window   = (int) ( $limit['window'] ?? 0 );
		if ( '' === $bucket || '' === $identity || $max < 1 || $window < 1 ) {
			return false;
		}
		$key = 'jluxe_rl_v2_' . hash_hmac( 'sha256', $bucket . ':' . $identity, wp_salt( 'auth' ) );
		if ( isset( $pending[ $key ] ) ) {
			// A duplicated bucket with conflicting limits is ambiguous; fail closed.
			return false;
		}
		$pending[ $key ] = array( 'max' => $max, 'window' => $window );
	}
	ksort( $pending, SORT_STRING );

	$locks = array();
	try {
		foreach ( array_keys( $pending ) as $key ) {
			$lock = jluxe_security_lock( $key );
			if ( ! $lock ) {
				return false;
			}
			$locks[] = $lock;
		}

		$now    = time();
		$states = array();
		foreach ( $pending as $key => $limit ) {
			$old   = get_transient( $key );
			$state = $old;
			if ( ! is_array( $state ) || ! isset( $state['count'], $state['reset'] ) || ! is_numeric( $state['count'] ) || ! is_numeric( $state['reset'] ) || (int) $state['reset'] <= $now ) {
				$state = array( 'count' => 0, 'reset' => $now + $limit['window'] );
			}
			if ( (int) $state['count'] >= $limit['max'] ) {
				return false;
			}
			$state['count'] = max( 0, (int) $state['count'] ) + 1;
			$states[ $key ] = array(
				'old' => $old,
				'new' => $state,
				'ttl' => max( 1, (int) $state['reset'] - $now ),
			);
		}

		foreach ( $states as $key => $state ) {
			if ( set_transient( $key, $state['new'], $state['ttl'] ) ) {
				continue;
			}

			// Best-effort rollback keeps a failed multi-bucket write from charging
			// only a subset of the request's quotas.
			foreach ( $states as $rollback_key => $rollback ) {
				$old = $rollback['old'];
				if ( is_array( $old ) && isset( $old['reset'] ) && is_numeric( $old['reset'] ) && (int) $old['reset'] > $now ) {
					set_transient( $rollback_key, $old, max( 1, (int) $old['reset'] - $now ) );
				} else {
					delete_transient( $rollback_key );
				}
			}
			return false;
		}
		return true;
	} finally {
		foreach ( array_reverse( $locks ) as $lock ) {
			jluxe_security_unlock( $lock );
		}
	}
}

/** Fixed-window limiter; fail closed on lock contention or storage failure. */
function jluxe_security_rate_limit( string $bucket, string $identity, int $max, int $window ): bool {
	$key = 'jluxe_rl_v2_' . hash_hmac( 'sha256', $bucket . ':' . $identity, wp_salt( 'auth' ) );
	$lock = jluxe_security_lock( $key );
	if ( ! $lock ) {
		return false;
	}
	try {
		$state = get_transient( $key );
		if ( ! is_array( $state ) || $state['reset'] <= time() ) {
			$state = array( 'count' => 0, 'reset' => time() + $window );
		}
		if ( $state['count'] >= $max ) {
			return false;
		}
		++$state['count'];
		return (bool) set_transient( $key, $state, max( 1, $state['reset'] - time() ) );
	} finally {
		jluxe_security_unlock( $lock );
	}
}

function jluxe_registration_enabled(): bool {
	$enabled = function_exists( 'WC' )
		? 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' )
		: (bool) get_option( 'users_can_register', false );
	return (bool) apply_filters( 'jluxe_registration_enabled', $enabled );
}

/** OTP must never be a password bypass for staff or accounts with elevated capabilities. */
function jluxe_otp_user_allowed( $user ): bool {
	if ( ! $user instanceof WP_User || ! $user->exists() || ! $user->roles ) {
		return false;
	}
	$allowed_roles = (array) apply_filters( 'jluxe_otp_allowed_roles', array( 'customer', 'subscriber' ) );
	if ( array_diff( $user->roles, $allowed_roles ) ) {
		return false;
	}
	foreach ( array( 'manage_options', 'manage_woocommerce', 'view_woocommerce_reports', 'edit_posts', 'delete_posts', 'read_private_posts', 'edit_products', 'edit_shop_orders', 'edit_users', 'delete_users', 'create_users', 'promote_users', 'install_plugins', 'activate_plugins', 'update_plugins', 'edit_theme_options', 'unfiltered_html', 'manage_network' ) as $capability ) {
		if ( user_can( $user, $capability ) ) {
			return false;
		}
	}
	return true;
}

/** Theme REST routes carry authentication, order, conversation or support data. */
function jluxe_private_rest_headers(): array {
	return array(
		'Cache-Control' => 'private, no-store, max-age=0',
		'Pragma'        => 'no-cache',
		'Expires'       => '0',
		'X-Robots-Tag'  => 'noindex, noarchive',
	);
}

/** Include core validation/authentication/method errors, not just handler success. */
function jluxe_protect_private_rest_responses( $response, $server, $request ) {
	// Core route matching is case-insensitive; privacy must be as well.
	if ( 0 === stripos( $request->get_route(), '/jluxe/v1/' ) && $response instanceof WP_REST_Response ) {
		foreach ( jluxe_private_rest_headers() as $name => $value ) {
			$response->header( $name, $value );
		}
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'jluxe_protect_private_rest_responses', 10, 3 );
