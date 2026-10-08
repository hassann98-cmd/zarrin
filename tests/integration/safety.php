<?php
/** Test-only MU plugin; the runner copies this into an in-memory disposable site. */
if ( ! defined( 'ZARRIN_INTEGRATION_TEST' ) || ! ZARRIN_INTEGRATION_TEST ) {
	http_response_code( 404 );
	exit;
}

// No mail, network, paid API calls, gateway requests or real SMS delivery.
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
// Capture which real theme template WordPress resolves for the most recent
// front-end request; this is test-only state in the disposable database.
add_action( 'wp', function () {
	update_option( 'zarrin_test_last_query', array(
		'is_front_page' => is_front_page(),
		'is_home'       => is_home(),
		'is_page'       => is_page(),
		'object_id'     => get_queried_object_id(),
		'page_id'       => get_query_var( 'page_id' ),
		'pagename'      => get_query_var( 'pagename' ),
		'show_on_front' => get_option( 'show_on_front' ),
		'page_on_front'       => get_option( 'page_on_front' ),
		'queried_slug'         => is_object( get_queried_object() ) && isset( get_queried_object()->post_name ) ? get_queried_object()->post_name : '',
		'page_template'        => get_page_template(),
		'page_template_lookup' => locate_template( array( 'page-checkout.php' ) ),
	), false );
}, PHP_INT_MAX );
add_filter( 'template_include', function ( $template ) {
	update_option( 'zarrin_test_last_template', $template, false );
	return $template;
}, PHP_INT_MAX );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( 0 === strpos( $url, 'https://api.kavenegar.com/v1/integration-only-no-real-provider/verify/lookup.json?' ) ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $parameters );
		update_option( 'zarrin_test_sms_outbox', $parameters, false );
		update_option( 'zarrin_test_sms_calls', (int) get_option( 'zarrin_test_sms_calls', 0 ) + 1, false );
		return array(
			'headers'  => array(),
			'response' => array( 'code' => 200, 'message' => 'TEST DOUBLE: never sent' ),
			'body'     => wp_json_encode( array( 'return' => array( 'status' => 'reject' === get_option( 'zarrin_test_sms_mode' ) ? 400 : 200 ) ) ),
			'cookies'  => array(),
		);
	}
	return new WP_Error( 'zarrin_test_network_blocked', 'External requests are disabled in this test environment.' );
}, PHP_INT_MAX, 3 );

// Observation only, not a WooCommerce mock. Upstream WC_Form_Handler runs at
// wp_loaded priority 20; inspect whether the unwanted native trigger survives.
add_action( 'wp_loaded', function () {
	if ( wp_doing_ajax() && 'jluxe_cart' === ( $_REQUEST['action'] ?? '' ) ) {
		update_option( 'zarrin_test_native_cart_trigger', ( isset( $_REQUEST['add-to-cart'] ) || isset( $_POST['add-to-cart'] ) || isset( $_GET['add-to-cart'] ) ) ? 'present' : 'absent', false );
	}
}, 20 );
