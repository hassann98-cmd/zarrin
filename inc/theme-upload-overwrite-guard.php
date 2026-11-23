<?php
/**
 * Preserve uploaded theme archives while WordPress renders its overwrite prompt.
 *
 * WordPress currently removes the uploaded-file attachment after a
 * `folder_exists` install result, before `Theme_Installer_Skin::do_overwrite()`
 * renders the Replace link. That link then points back to the deleted attachment
 * and File_Upload_Upgrader reports "Please select a file". Keep only that
 * specific theme ZIP attachment until the user replaces or cancels the upload.
 */

defined( 'ABSPATH' ) || exit;

/** Remember the attachment created for this request's uploaded theme ZIP. */
function jluxe_track_uploaded_theme_package( $attachment_id ): void {
	if (
		'upload-theme' !== ( $_GET['action'] ?? '' ) ||
		empty( $_FILES['themezip']['name'] )
	) {
		return;
	}

	$GLOBALS['jluxe_uploaded_theme_package_id'] = absint( $attachment_id );
}
add_action( 'add_attachment', 'jluxe_track_uploaded_theme_package' );

/** Mark the exact core overwrite-confirmation path before core cleanup runs. */
function jluxe_keep_theme_package_for_overwrite( $result, $hook_extra ) {
	if (
		'upload-theme' !== ( $_GET['action'] ?? '' ) ||
		! empty( $_GET['overwrite'] ) ||
		empty( $GLOBALS['jluxe_uploaded_theme_package_id'] ) ||
		! is_array( $hook_extra ) ||
		'theme' !== ( $hook_extra['type'] ?? '' ) ||
		'install' !== ( $hook_extra['action'] ?? '' ) ||
		! is_wp_error( $result ) ||
		'folder_exists' !== $result->get_error_code()
	) {
		return $result;
	}

	$GLOBALS['jluxe_preserve_theme_package_for_overwrite'] = true;
	return $result;
}
add_filter( 'upgrader_install_package_result', 'jluxe_keep_theme_package_for_overwrite', 10, 2 );

/** Prevent core cleanup from deleting the package linked by the overwrite prompt. */
function jluxe_preserve_theme_package_attachment( $delete, $post, $force_delete ) {
	if (
		empty( $GLOBALS['jluxe_preserve_theme_package_for_overwrite'] ) ||
		'upload-theme' !== ( $_GET['action'] ?? '' ) ||
		! empty( $_GET['overwrite'] ) ||
		! is_object( $post ) ||
		absint( $post->ID ?? 0 ) !== absint( $GLOBALS['jluxe_uploaded_theme_package_id'] ?? 0 )
	) {
		return $delete;
	}

	// Returning the post short-circuits wp_delete_attachment() without touching
	// the ZIP. WordPress' next Replace or Cancel request performs normal cleanup.
	return $post;
}
add_filter( 'pre_delete_attachment', 'jluxe_preserve_theme_package_attachment', 10, 3 );
