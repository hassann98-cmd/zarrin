<?php
/** Review criteria UI and bounded asynchronous aggregates. */
defined( 'ABSPATH' ) || exit;

function jluxe_product_reviews_available( int $product_id ): bool {
	return function_exists( 'wc_get_product' )
		&& ( ! function_exists( 'wc_reviews_enabled' ) || wc_reviews_enabled() )
		&& jluxe_product_is_public( wc_get_product( $product_id ) );
}


function jluxe_review_form_criteria( array $args ): array {
	$args['comment_field'] = ( $args['comment_field'] ?? '' ) . jluxe_render_review_criteria_inputs();
	return $args;
}
add_filter( 'woocommerce_product_review_comment_form_args', 'jluxe_review_form_criteria' );

function jluxe_review_averages_signature( int $product_id ): string {
	return hash( 'sha256', wp_json_encode( array(
		'criteria' => jluxe_get_theme_settings()['review_criteria']['items'],
		'changes' => get_post_meta( $product_id, '_jluxe_review_insights_version', true ),
	) ) );
}

/** No unbounded comment query in a product page request. */
function jluxe_get_review_criteria_averages( int $product_id ): array {
	if ( empty( jluxe_get_theme_settings()['review_criteria']['items'] ) || ! jluxe_product_reviews_available( $product_id ) ) {
		return array();
	}
	$signature = jluxe_review_averages_signature( $product_id );
	$cached = get_transient( 'jluxe_review_avgs_' . $product_id );
	if ( is_array( $cached ) && hash_equals( $signature, $cached['signature'] ) ) {
		return $cached['averages'];
	}
	$work = get_transient( 'jluxe_review_work_' . $product_id );
	$offset = is_array( $work ) && $work['signature'] === $signature ? (int) $work['offset'] : 0;
	$args = array( $product_id, $signature, $offset );
	if ( ! wp_next_scheduled( 'jluxe_build_review_averages', $args ) ) {
		wp_schedule_single_event( time() + 5, 'jluxe_build_review_averages', $args );
	}
	return array();
}

/** Each cron invocation reads at most 100 comments, carrying only sums/counts between batches. */
function jluxe_build_review_averages( int $product_id, string $signature, int $offset = 0 ): void {
	if ( ! hash_equals( jluxe_review_averages_signature( $product_id ), $signature ) || ! jluxe_product_reviews_available( $product_id ) ) { return; }
	$lock = jluxe_security_lock( 'review-averages:' . $product_id );
	if ( ! $lock ) { return; }
	try {
		$work_key = 'jluxe_review_work_' . $product_id;
		$work = get_transient( $work_key );
		if ( ! is_array( $work ) || $work['signature'] !== $signature ) {
			if ( $offset > 0 ) { return; }
			$work = array( 'signature' => $signature, 'offset' => 0, 'sums' => array(), 'counts' => array() );
		}
		if ( (int) $work['offset'] !== $offset ) { return; }
		$criteria = jluxe_get_theme_settings()['review_criteria']['items'];
		$valid_keys = wp_list_pluck( $criteria, 'key' );
		$comments = get_comments( array(
			'post_id' => $product_id, 'status' => 'approve', 'type' => 'review',
			'number' => 100, 'offset' => $offset, 'orderby' => 'comment_ID', 'order' => 'ASC',
		) );
		foreach ( $comments as $comment ) {
			$ratings = get_comment_meta( $comment->comment_ID, '_jluxe_review_criteria', true );
			foreach ( is_array( $ratings ) ? $ratings : array() as $key => $value ) {
				if ( ! in_array( $key, $valid_keys, true ) || ! is_scalar( $value ) || ! preg_match( '/^[1-5]$/D', (string) $value ) ) { continue; }
				$work['sums'][ $key ] = ( $work['sums'][ $key ] ?? 0 ) + (int) $value;
				$work['counts'][ $key ] = ( $work['counts'][ $key ] ?? 0 ) + 1;
			}
		}
		if ( ! hash_equals( jluxe_review_averages_signature( $product_id ), $signature ) ) { return; }
		if ( count( $comments ) === 100 ) {
			$work['offset'] += 100;
			set_transient( $work_key, $work, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + 5, 'jluxe_build_review_averages', array( $product_id, $signature, $work['offset'] ) );
			return;
		}
		$result = array();
		foreach ( $criteria as $item ) {
			$key = $item['key'];
			if ( empty( $work['counts'][ $key ] ) ) { continue; }
			$average = $work['sums'][ $key ] / $work['counts'][ $key ];
			$result[ $key ] = array( 'label' => $item['label'], 'average' => round( $average, 1 ), 'percent' => (int) round( $average * 20 ), 'count' => $work['counts'][ $key ] );
		}
		set_transient( 'jluxe_review_avgs_' . $product_id, array( 'signature' => $signature, 'averages' => $result ), DAY_IN_SECONDS );
		delete_transient( $work_key );
	} finally {
		jluxe_security_unlock( $lock );
	}
}
add_action( 'jluxe_build_review_averages', 'jluxe_build_review_averages', 10, 3 );

function jluxe_invalidate_review_insights( int $comment_id, $comment = null ): void {
	$comment = is_object( $comment ) ? $comment : get_comment( $comment_id );
	if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) { return; }
	$product_id = (int) $comment->comment_post_ID;
	update_post_meta( $product_id, '_jluxe_review_insights_version', wp_generate_uuid4() );
	delete_transient( 'jluxe_review_avgs_' . $product_id );
	delete_transient( 'jluxe_review_work_' . $product_id );
	delete_transient( 'jluxe_ai_review_summary_' . $product_id );
}
add_action( 'comment_post', 'jluxe_invalidate_review_insights', 20, 2 );
add_action( 'edit_comment', 'jluxe_invalidate_review_insights', 20, 2 );
add_action( 'deleted_comment', 'jluxe_invalidate_review_insights', 20, 2 );
add_action( 'transition_comment_status', function ( $new, $old, $comment ) {
	jluxe_invalidate_review_insights( (int) $comment->comment_ID, $comment );
}, 20, 3 );
function jluxe_review_meta_changed( $meta_id, $comment_id, $meta_key ): void {
	if ( in_array( $meta_key, array( 'rating', '_jluxe_review_criteria' ), true ) ) {
		jluxe_invalidate_review_insights( (int) $comment_id );
	}
}
foreach ( array( 'added_comment_meta', 'updated_comment_meta', 'deleted_comment_meta' ) as $hook ) {
	add_action( $hook, 'jluxe_review_meta_changed', 20, 3 );
}

function jluxe_render_review_insights( int $product_id ): void {
	jluxe_render_ai_review_summary( $product_id );
	$averages = jluxe_get_review_criteria_averages( $product_id );
	if ( ! $averages ) { return; }
	echo '<div class="jluxe-review-averages" aria-label="میانگین معیارهای نظرهای تأییدشده">';
	foreach ( $averages as $item ) {
		printf( '<div class="jluxe-review-average"><span>%s</span><meter min="0" max="5" value="%s" aria-label="%s"></meter><span>%s از ۵ (%s رأی)</span></div>',
			esc_html( $item['label'] ), esc_attr( (string) $item['average'] ), esc_attr( $item['label'] ), esc_html( jluxe_fa_digits( $item['average'] ) ), esc_html( jluxe_fa_digits( $item['count'] ) ) );
	}
	echo '</div>';
}
