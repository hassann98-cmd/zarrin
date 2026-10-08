<?php
/**
 * Responsive attachment-image helpers.
 *
 * @package JLuxe
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a usable responsive image without ever replacing an animated GIF
 * with WordPress's generated (static) intermediate frame.
 *
 * @param int    $attachment_id WordPress attachment ID.
 * @param string $size          Registered WordPress image size.
 * @param string $sizes         The rendered CSS slot size.
 * @return array{src:string,srcset:string,sizes:string,width:int,height:int,mime_type:string}
 */
function jluxe_get_responsive_attachment_image( int $attachment_id, string $size = 'large', string $sizes = '100vw' ): array {
	$empty = array(
		'src'       => '',
		'srcset'    => '',
		'sizes'     => $sizes,
		'width'     => 0,
		'height'    => 0,
		'mime_type' => '',
	);

	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
		return $empty;
	}

	$mime_type = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $attachment_id ) : '';
	if ( '' !== $mime_type && 0 !== strpos( $mime_type, 'image/' ) ) {
		return $empty;
	}

	$src = wp_get_attachment_image_url( $attachment_id, $size );
	if ( ! $src && function_exists( 'wp_get_attachment_url' ) ) {
		$src = wp_get_attachment_url( $attachment_id );
	}
	if ( ! $src ) {
		return $empty;
	}

	$is_animated_gif = 'image/gif' === strtolower( $mime_type );
	if ( $is_animated_gif && function_exists( 'wp_get_attachment_url' ) ) {
		// An intermediate GIF is often only its first frame; always use the original.
		$original_src = wp_get_attachment_url( $attachment_id );
		if ( $original_src ) {
			$src = $original_src;
		}
	}

	$srcset = '';
	if ( ! $is_animated_gif && function_exists( 'wp_get_attachment_image_srcset' ) ) {
		$srcset = (string) wp_get_attachment_image_srcset( $attachment_id, $size );
	}

	$dimensions = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
	$width      = is_array( $dimensions ) && isset( $dimensions[1] ) ? absint( $dimensions[1] ) : 0;
	$height     = is_array( $dimensions ) && isset( $dimensions[2] ) ? absint( $dimensions[2] ) : 0;

	return array(
		'src'       => (string) $src,
		'srcset'    => $srcset,
		'sizes'     => $sizes,
		'width'     => $width,
		'height'    => $height,
		'mime_type' => $mime_type,
	);
}

/**
 * Build escaped srcset/sizes HTML attributes for an image or its data-* fields.
 *
 * @param array  $image  Result from jluxe_get_responsive_attachment_image().
 * @param string $prefix Either an empty string or "data-".
 * @return string
 */
function jluxe_responsive_image_attributes( array $image, string $prefix = '' ): string {
	if ( empty( $image['srcset'] ) ) {
		return '';
	}

	$prefix = 'data-' === $prefix ? 'data-' : '';
	$sizes  = isset( $image['sizes'] ) ? (string) $image['sizes'] : '100vw';

	return ' ' . $prefix . 'srcset="' . esc_attr( (string) $image['srcset'] ) . '" ' . $prefix . 'sizes="' . esc_attr( $sizes ) . '"';
}

/**
 * Keep the original GIF as the source even when WordPress has generated
 * intermediate thumbnails that contain only a still frame.
 *
 * @param array|false $downsize Existing image_downsize result.
 * @param int         $attachment_id Attachment ID.
 * @param string|int[] $size Requested image size.
 * @return array|false
 */
function jluxe_keep_animated_gif_original( $downsize, $attachment_id, $size ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || ! function_exists( 'get_post_mime_type' ) || 'image/gif' !== strtolower( (string) get_post_mime_type( $attachment_id ) ) ) {
		return $downsize;
	}

	$url = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $attachment_id ) : false;
	if ( ! $url ) {
		return $downsize;
	}

	$metadata = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $attachment_id ) : array();
	$width    = is_array( $metadata ) && isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0;
	$height   = is_array( $metadata ) && isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0;

	return array( (string) $url, $width, $height, false );
}
add_filter( 'image_downsize', 'jluxe_keep_animated_gif_original', 100, 3 );

/**
 * Generated GIF srcsets can point at static intermediate frames. Do not expose
 * them to the browser; the original animated file is used at every size.
 *
 * @param array|false $sources Candidate sources.
 * @param array       $size_array Requested dimensions.
 * @param string      $image_src Source URL.
 * @param array       $image_meta Attachment metadata.
 * @param int         $attachment_id Attachment ID.
 * @return array|false
 */
function jluxe_disable_animated_gif_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
	if ( $attachment_id && function_exists( 'get_post_mime_type' ) && 'image/gif' === strtolower( (string) get_post_mime_type( absint( $attachment_id ) ) ) ) {
		return false;
	}
	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'jluxe_disable_animated_gif_srcset', 100, 5 );
