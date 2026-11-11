<?php
/** Native document transitions: no DOM swapping, rewritten head tags or fetch-based routing. */
defined( 'ABSPATH' ) || exit;

function jluxe_page_motion_allowed(): bool {
	if ( is_admin() ) { return false; }
	foreach ( array( 'is_cart', 'is_checkout', 'is_account_page', 'is_order_received_page', 'is_feed', 'is_preview' ) as $check ) {
		if ( function_exists( $check ) && $check() ) { return false; }
	}
	return true;
}

function jluxe_enqueue_page_motion(): void {
	if ( ! jluxe_page_motion_allowed() ) { return; }
	$perf = jluxe_get_theme_settings()['performance'];
	$transitions = ! empty( $perf['page_transitions'] );
	$prefetch = ! empty( $perf['page_prefetch'] ) && ! is_user_logged_in();
	if ( ! $transitions && ! $prefetch ) { return; }
	$urls = jluxe_public_urls();
	$excluded = array();
	foreach ( array( 'cart', 'checkout', 'dashboard', 'login', 'lost_password' ) as $key ) {
		if ( ! empty( $urls[ $key ] ) ) { $excluded[] = $urls[ $key ]; }
	}
	if ( $transitions ) {
		wp_enqueue_style( 'jluxe-page-motion', JLUXE_THEME_URI . '/assets/css/page-motion.css', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/css/page-motion.css' ) );
	}
	wp_enqueue_script( 'jluxe-page-motion', JLUXE_THEME_URI . '/assets/js/page-motion.js', array(), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/page-motion.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'jluxe-page-motion', 'jluxePageMotion', array(
		'transitions' => $transitions, 'prefetch' => $prefetch,
		'home' => $urls['home'] ?? home_url( '/' ), 'excluded' => $excluded,
	) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_enqueue_page_motion', 19 );
