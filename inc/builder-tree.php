<?php
/** Bounded, backwards-compatible homepage layout tree. */
defined( 'ABSPATH' ) || exit;

function jluxe_builder_validate_tree( array $nodes, int $depth = 0, ?array &$seen = null ): bool {
	if ( null === $seen ) { $seen = array(); }
	if ( $depth > 6 ) { return false; }
	$types = jluxe_visual_builder_section_types();
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || ! is_string( $node['type'] ?? null ) || ! isset( $types[ $node['type'] ] ) ) { return false; }
		$id = $node['id'] ?? '';
		if ( ! is_string( $id ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,80}$/D', $id ) || isset( $seen[ $id ] ) || count( $seen ) >= 150 ) { return false; }
		$seen[ $id ] = true;
		if ( 'container' === $node['type'] ) {
			if ( ! is_array( $node['children'] ?? null ) || ! jluxe_builder_validate_tree( $node['children'], $depth + 1, $seen ) ) { return false; }
		} elseif ( ! empty( $node['children'] ) ) { return false; }
	}
	return true;
}

function jluxe_builder_flatten( array $nodes ): array {
	$out = array();
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['enabled'] ) ) { continue; }
		$out[] = $node;
		if ( 'container' === ( $node['type'] ?? '' ) ) { $out = array_merge( $out, jluxe_builder_flatten( (array) ( $node['children'] ?? array() ) ) ); }
	}
	return $out;
}

function jluxe_builder_container_options( array $raw ): array {
	$out = array();
	foreach ( array( 'columns' => array( 1, 4, 2 ), 'mobile_columns' => array( 1, 2, 1 ), 'gap' => array( 0, 100, 20 ), 'max_width' => array( 280, 1920, 1320 ) ) as $key => $range ) {
		$out[ $key ] = is_scalar( $raw[ $key ] ?? null ) ? max( $range[0], min( $range[1], (int) $raw[ $key ] ) ) : $range[2];
	}
	$out['reverse_mobile'] = ! empty( $raw['reverse_mobile'] );
	return $out;
}
