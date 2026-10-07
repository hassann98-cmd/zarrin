<?php
/** R166 — responsive delivery only; never crop, recompress or regenerate on a page request. */
defined( 'ABSPATH' ) || exit;

/** New uploads get these sizes; existing media needs an explicit, backed-up regeneration. */
function jluxe_register_responsive_image_sizes(): void {
	foreach ( array( 96, 160, 320, 480 ) as $width ) {
		add_image_size( 'jluxe-media-' . $width, $width, 0, false );
	}
}
add_action( 'after_setup_theme', 'jluxe_register_responsive_image_sizes', 11 );

/** Keep GIF animation; sites can also opt animated WebP/APNG or art-directed media out. */
function jluxe_image_preserve_original( int $image_id ): bool {
	return (bool) apply_filters( 'jluxe_image_preserve_original', 'image/gif' === strtolower( (string) get_post_mime_type( $image_id ) ), $image_id );
}

/**
 * Choose an EXISTING same-aspect-ratio derivative, not an ungenerated size that
 * WordPress would silently resolve to the full original. Never fabricate URLs,
 * inspect remote files, upscale, or generate thumbnails in the render path.
 */
function jluxe_uncropped_image_size( int $image_id, int $target_width, string $fallback = 'large' ): string {
	if ( jluxe_image_preserve_original( $image_id ) ) {
		return 'full';
	}
	$meta = wp_get_attachment_metadata( $image_id );
	$meta = is_array( $meta ) ? $meta : array();
	$width = (int) ( $meta['width'] ?? 0 );
	$height = (int) ( $meta['height'] ?? 0 );
	if ( $width <= 0 || $height <= 0 ) {
		return $fallback;
	}
	$target_width = max( 1, $target_width );
	$candidates = array( 'full' => $width );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
		if ( ! is_string( $name ) || ! is_array( $size ) || empty( $size['file'] ) ) {
			continue;
		}
		$w = (int) ( $size['width'] ?? 0 );
		$h = (int) ( $size['height'] ?? 0 );
		if ( $w <= 0 || $h <= 0 || $w > $width || $h > $height ) {
			continue;
		}
		// Allow at most one rounding pixel, as used for generated resize dimensions.
		if ( abs( $h - $height * $w / $width ) > 1 ) {
			continue;
		}
		$candidates[ $name ] = $w;
	}
	asort( $candidates, SORT_NUMERIC );
	foreach ( $candidates as $name => $w ) {
		if ( $w >= $target_width ) {
			return $name;
		}
	}
	return 'full'; // Small source: preserve the best available original, never upscale.
}

/** Data only: the caller retains its exact classes, effects, links, and image frame. */
function jluxe_responsive_image_data( int $image_id, int $target_width, string $fallback = 'large' ): ?array {
	if ( $image_id <= 0 ) {
		return null;
	}
	$size = jluxe_uncropped_image_size( $image_id, $target_width, $fallback );
	$source = wp_get_attachment_image_src( $image_id, $size );
	if ( ! $source || empty( $source[0] ) ) {
		return null;
	}
	return array(
		'url' => (string) $source[0],
		'width' => (int) $source[1],
		'height' => (int) $source[2],
		'size' => $size,
		'srcset' => jluxe_image_preserve_original( $image_id ) ? '' : (string) wp_get_attachment_image_srcset( $image_id, $size ),
	);
}

/** Native auto-sizes is valid only for lazy images. Keep a useful older-browser fallback. */
function jluxe_responsive_sizes( string $fallback, bool $lazy ): string {
	return $lazy ? 'auto, ' . $fallback : $fallback;
}

/** Keep core image/CDN filters for normal media; never let auto-srcset flatten opted-out animation. */
function jluxe_responsive_attachment_image( int $image_id, int $target_width, string $fallback, array $attributes = array() ): string {
	if ( ! jluxe_image_preserve_original( $image_id ) ) {
		return (string) wp_get_attachment_image( $image_id, jluxe_uncropped_image_size( $image_id, $target_width, $fallback ), false, $attributes );
	}
	$image = jluxe_responsive_image_data( $image_id, $target_width, $fallback );
	if ( ! $image ) {
		return '';
	}
	$attributes = array_merge( array( 'alt' => '', 'width' => $image['width'], 'height' => $image['height'] ), $attributes );
	$attributes['src'] = $image['url'];
	unset( $attributes['srcset'], $attributes['sizes'] );
	$html = '<img';
	foreach ( $attributes as $name => $value ) {
		if ( false !== $value && in_array( $name, array( 'src', 'alt', 'class', 'width', 'height', 'loading', 'decoding', 'fetchpriority', 'aria-hidden' ), true ) ) {
			$escaped = 'src' === $name ? esc_url( (string) $value ) : esc_attr( (string) $value );
			$html .= ' ' . $name . '="' . $escaped . '"';
		}
	}
	return $html . ' />';
}

/** The leading homepage hero outranks the first product card; shop pages keep their image priority. */
function jluxe_product_card_high_priority( int $index ): bool {
	return 1 === $index && ! jluxe_homepage_lcp_hero();
}
