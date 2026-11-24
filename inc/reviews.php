<?php
/** Review criteria UI and bounded asynchronous aggregates. */
defined( 'ABSPATH' ) || exit;

function jluxe_product_reviews_available( int $product_id ): bool {
	return function_exists( 'wc_get_product' )
		&& ( ! function_exists( 'wc_reviews_enabled' ) || wc_reviews_enabled() )
		&& jluxe_product_is_public( wc_get_product( $product_id ) );
}

/**
 * WooCommerce's single-product-reviews.php returns before rendering anything
 * when comments_open() is false. Old/auto-closed product comment statuses can
 * therefore hide already-approved reviews as well as the review form. Keep
 * published product discussions open whenever the store-wide reviews switch
 * is enabled; do not alter normal post/page comments or the global WC switch.
 */
function jluxe_product_reviews_keep_published_product_open( $open, $post_id ): bool {
	$post_id = (int) $post_id;
	if ( 'product' !== get_post_type( $post_id ) ) {
		return (bool) $open;
	}
	if ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $post_id ) ) {
		return (bool) $open;
	}
	if ( ! function_exists( 'wc_reviews_enabled' ) ) {
		return (bool) $open;
	}

	return (bool) wc_reviews_enabled();
}
add_filter( 'comments_open', 'jluxe_product_reviews_keep_published_product_open', 99, 2 );

/**
 * Let authenticated customers review a product even when WooCommerce's
 * verified-purchaser-only display option is enabled. The stored admin option
 * is untouched; the override is scoped to a logged-in product review form or
 * its comment-post request.
 *
 * @param mixed  $pre_option Existing pre-option value.
 * @param string $option     Option name.
 * @param mixed  $default    Requested default value.
 * @return mixed
 */
function jluxe_product_review_verification_requirement( $pre_option, string $option, $default ) {
	if ( 'woocommerce_review_rating_verification_required' !== $option || ! is_user_logged_in() ) {
		return $pre_option;
	}

	$product_id = 0;
	if ( isset( $_POST['comment_post_ID'] ) && is_scalar( $_POST['comment_post_ID'] ) ) {
		$product_id = absint( wp_unslash( $_POST['comment_post_ID'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( function_exists( 'get_queried_object_id' ) ) {
		$product_id = (int) get_queried_object_id();
	}

	if ( $product_id && 'product' === get_post_type( $product_id ) ) {
		return 'no';
	}

	return $pre_option;
}
add_filter( 'pre_option_woocommerce_review_rating_verification_required', 'jluxe_product_review_verification_requirement', 99, 3 );


function jluxe_review_form_criteria( array $args ): array {
	/* R66: فرمِ دیدگاه از صفر ساخته می‌شود — امتیازِ کلی به‌جای dropdownِ
	پیش‌فرضِ ووکامرس، ویجتِ ستارهٔ CSS-خالص است (رادیوهای name="rating"
	که دقیقاً همان کلیدِ متا را می‌فرستند و WC_Comments ذخیره‌اش می‌کند)؛
	textarea و ردیف‌های امتیازِ ویژگی‌ها بلافاصله بعدش می‌آیند. */
	$field = '';
	if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) {
		$required = function_exists( 'wc_review_ratings_required' ) && wc_review_ratings_required();
		$labels   = array( 5 => 'عالی', 4 => 'خوب', 3 => 'متوسط', 2 => 'ضعیف', 1 => 'خیلی بد' );
		$field   .= '<div class="comment-form-rating jluxe-rating-widget"><span class="jluxe-rating-widget-label">امتیاز شما' . ( $required ? ' <span class="required">*</span>' : '' ) . '</span><div class="jluxe-rating-stars" dir="ltr">';
		foreach ( $labels as $i => $word ) {
			$field .= '<input type="radio" id="jluxe-rating-' . $i . '" name="rating" value="' . $i . '"' . ( $required ? ' required' : '' ) . ' />';
			$field .= '<label for="jluxe-rating-' . $i . '" aria-label="' . $i . ' از ۵ — ' . $word . '" title="' . $word . '">★</label>';
		}
		$field .= '</div></div>';
	}
	$field .= '<p class="comment-form-comment"><label for="comment">دیدگاه شما <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>';
	$field .= jluxe_render_review_criteria_inputs();
	$photo_settings = jluxe_get_theme_settings()['review_criteria'] ?? array();
	$may_upload_photos = jluxe_review_photo_upload_allowed( (int) get_queried_object_id() );
	if ( $may_upload_photos ) {
		$max_count = max( 1, min( 5, absint( $photo_settings['photos_max_count'] ?? 3 ) ) );
		$max_bytes = max( 1, min( 5, absint( $photo_settings['photos_max_size_mb'] ?? 2 ) ) ) * MB_IN_BYTES;
		$field .= '<div class="jluxe-review-photo-upload"><label for="jluxe-review-photos">افزودن عکس به دیدگاه (اختیاری)</label>';
		$field .= wp_nonce_field( 'jluxe_review_photos', 'jluxe_review_photos_nonce', false, false );
		$field .= '<label class="jluxe-review-upload-target" for="jluxe-review-photos"><span aria-hidden="true">＋</span><span>انتخاب عکس از دستگاه</span><small>حداکثر ' . esc_html( (string) $max_count ) . ' عکس، هرکدام تا ' . esc_html( (string) ( $max_bytes / MB_IN_BYTES ) ) . ' مگابایت؛ JPG، PNG یا WebP</small></label>';
		$field .= '<input type="file" id="jluxe-review-photos" name="jluxe_review_photos[]" accept="image/jpeg,image/png,image/webp" multiple data-max-count="' . esc_attr( (string) $max_count ) . '" data-max-bytes="' . esc_attr( (string) $max_bytes ) . '" />';
		$field .= '<div class="jluxe-review-upload-previews" aria-live="polite"></div><p class="jluxe-review-upload-error" role="alert" hidden></p></div>';
	}
	$args['comment_field'] = $field;
	$args['enctype'] = 'multipart/form-data';
	/* R73: فرم داخلِ پاپ‌آپِ «ثبت دیدگاه» باز می‌شود — عنوانِ کارت و
	یادداشتِ شفافِ «منتشرشدن پس از تأییدِ مدیر» (دیدگاه‌ها طبقِ قانونِ
	ثابتِ همین پوسته همیشه منتظرِ تأییدِ مدیر می‌مانند). */
	$args['title_reply'] = '';
	$args['comment_notes_before'] = '<p class="jluxe-review-moderation-note">دیدگاه شما پس از بررسی و تأیید مدیر منتشر می‌شود.</p>';
	return $args;
}
add_filter( 'woocommerce_product_review_comment_form_args', 'jluxe_review_form_criteria' );

/** Is the current customer allowed to attach images to this product review? */
function jluxe_review_photo_upload_allowed( int $product_id ): bool {
	if ( 'product' !== get_post_type( $product_id ) ) { return false; }
	$settings = jluxe_get_theme_settings()['review_criteria'] ?? array();
	return ! empty( $settings['photos_enabled'] ) && ( empty( $settings['photos_logged_in_only'] ) || is_user_logged_in() );
}

/** Start buffering only around an eligible product review form for the native multipart fallback. */
function jluxe_review_comment_form_buffer_before(): void {
	$post_id = isset( $GLOBALS['post']->ID ) ? absint( $GLOBALS['post']->ID ) : ( function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0 );
	if ( ! $post_id || ! jluxe_review_photo_upload_allowed( $post_id ) || ! empty( $GLOBALS['jluxe_review_comment_form_buffer_active'] ) ) { return; }
	$GLOBALS['jluxe_review_comment_form_buffer_active'] = true;
	ob_start();
}

/** Inject the multipart attribute into WordPress's native form after its markup has been rendered. */
function jluxe_review_comment_form_buffer_after(): void {
	if ( empty( $GLOBALS['jluxe_review_comment_form_buffer_active'] ) ) { return; }
	unset( $GLOBALS['jluxe_review_comment_form_buffer_active'] );
	$html = ob_get_clean();
	if ( ! is_string( $html ) ) { return; }
	$html = preg_replace_callback(
		'~<form\b[^>]*\bid\s*=\s*(["\x27])commentform\1[^>]*>~i',
		static function ( array $matches ): string {
			return preg_match( '/\benctype\s*=/i', $matches[0] ) ? $matches[0] : preg_replace( '/>$/', ' enctype="multipart/form-data">', $matches[0] );
		},
		$html,
		1
	);
	echo is_string( $html ) ? $html : '';
}
add_action( 'comment_form_before', 'jluxe_review_comment_form_buffer_before', 1, 0 );
add_action( 'comment_form_after', 'jluxe_review_comment_form_buffer_after', 999, 0 );

/** Validate a single uploaded image's actual bytes, declared MIME/extension and size. */
function jluxe_validate_review_photo_file( string $path, string $filename, int $size, int $max_bytes ): bool {
	if ( ! is_file( $path ) || $size < 1 || $size > $max_bytes ) { return false; }
	$allowed = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$filetype = wp_check_filetype_and_ext( $path, sanitize_file_name( $filename ), $allowed );
	$mime = is_array( $filetype ) ? (string) ( $filetype['type'] ?? '' ) : '';
	$image = @getimagesize( $path );
	$actual_mime = is_array( $image ) ? (string) ( $image['mime'] ?? '' ) : '';
	return in_array( $mime, $allowed, true ) && $mime === $actual_mime && (bool) preg_match( '/\.(?:jpe?g|jpe|png|webp)$/i', $filename );
}

function jluxe_review_photo_count_allowed( int $count, int $max_count ): bool {
	return $count > 0 && $count <= max( 1, min( 5, $max_count ) );
}

/** Save strictly verified customer review photos; invalid files are never attached. */
function jluxe_save_review_photos( int $comment_id, $comment_approved ): void {
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'review' !== (string) $comment->comment_type || 'product' !== get_post_type( (int) $comment->comment_post_ID ) ) { return; }
	$settings = jluxe_get_theme_settings()['review_criteria'] ?? array();
	if ( ! jluxe_review_photo_upload_allowed( (int) $comment->comment_post_ID ) ) { return; }
	$nonce = isset( $_POST['jluxe_review_photos_nonce'] ) && is_scalar( $_POST['jluxe_review_photos_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['jluxe_review_photos_nonce'] ) ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'jluxe_review_photos' ) || empty( $_FILES['jluxe_review_photos'] ) || ! is_array( $_FILES['jluxe_review_photos'] ) ) { return; }
	$files = $_FILES['jluxe_review_photos'];
	$names = isset( $files['name'] ) && is_array( $files['name'] ) ? $files['name'] : array();
	$count = count( array_filter( $names, static function ( $name ) { return is_string( $name ) && '' !== $name; } ) );
	$max_count = max( 1, min( 5, absint( $settings['photos_max_count'] ?? 3 ) ) );
	$max_bytes = max( 1, min( 5, absint( $settings['photos_max_size_mb'] ?? 2 ) ) ) * MB_IN_BYTES;
	if ( ! jluxe_review_photo_count_allowed( $count, $max_count ) ) { return; }
	$allowed = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$valid_files = array();
	for ( $i = 0; $i < count( $names ); $i++ ) {
		$name_value = $files['name'][ $i ] ?? '';
		if ( ! is_string( $name_value ) || '' === $name_value ) { continue; }
		$name = $name_value;
		$tmp_value = $files['tmp_name'][ $i ] ?? '';
		$tmp = is_string( $tmp_value ) ? $tmp_value : '';
		$reported_size = $files['size'][ $i ] ?? 0;
		$size = is_numeric( $reported_size ) ? (int) $reported_size : 0;
		$actual_size = $tmp && is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $actual_size !== $size ) { return; }
		$error = (int) ( $files['error'][ $i ] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) || $size < 1 || $size > $max_bytes ) { return; }
		if ( ! jluxe_validate_review_photo_file( $tmp, $name, $size, $max_bytes ) ) { return; }
		$filetype = wp_check_filetype_and_ext( $tmp, sanitize_file_name( $name ), $allowed );
		$mime = is_array( $filetype ) ? (string) ( $filetype['type'] ?? '' ) : '';
		$valid_files[] = array( 'name' => $name, 'tmp_name' => $tmp, 'type' => $mime, 'error' => $error, 'size' => $size );
	}
	if ( count( $valid_files ) !== $count ) { return; }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$product_id = (int) $comment->comment_post_ID;
	$attachment_ids = array();
	foreach ( $valid_files as $file ) {
		$_FILES['jluxe_review_photo_single'] = $file;
		$upload = wp_handle_upload( $_FILES['jluxe_review_photo_single'], array( 'test_form' => false, 'mimes' => $allowed ) );
		unset( $_FILES['jluxe_review_photo_single'] );
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) { continue; }
		$path = (string) $upload['file'];
		$editor = function_exists( 'wp_get_image_editor' ) ? wp_get_image_editor( $path ) : null;
		if ( ! is_wp_error( $editor ) && is_object( $editor ) ) {
			$dimensions = $editor->get_size();
			if ( max( (int) ( $dimensions['width'] ?? 0 ), (int) ( $dimensions['height'] ?? 0 ) ) > 1600 ) { $editor->resize( 1600, 1600, false ); }
			$original_path = $path;
			$saved = $editor->save( null, $file['type'] );
			if ( is_wp_error( $saved ) ) { @unlink( $original_path ); continue; }
			$path = (string) ( $saved['path'] ?? $original_path );
			if ( $path !== $original_path && is_file( $original_path ) ) { @unlink( $original_path ); }
		}
		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => $file['type'],
			'post_title' => sanitize_text_field( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_status' => 'inherit',
			'post_parent' => $product_id,
		), $path, $product_id, true );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) { @unlink( $path ); continue; }
		update_post_meta( (int) $attachment_id, '_jluxe_review_photo', '1' );
		update_post_meta( (int) $attachment_id, '_jluxe_review_comment_id', $comment_id );
		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $path );
			if ( is_array( $metadata ) ) { wp_update_attachment_metadata( (int) $attachment_id, $metadata ); }
		}
		$attachment_ids[] = (int) $attachment_id;
	}
	if ( $attachment_ids ) { update_comment_meta( $comment_id, '_jluxe_review_photo_ids', $attachment_ids ); }
}
add_action( 'comment_post', 'jluxe_save_review_photos', 15, 2 );

/** Remove only attachments explicitly owned by the deleted review. */
function jluxe_delete_review_photos( int $comment_id ): void {
	$ids = get_comment_meta( $comment_id, '_jluxe_review_photo_ids', true );
	foreach ( is_array( $ids ) ? $ids : array() as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id && '1' === (string) get_post_meta( $attachment_id, '_jluxe_review_photo', true ) && (int) get_post_meta( $attachment_id, '_jluxe_review_comment_id', true ) === $comment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
add_action( 'deleted_comment', 'jluxe_delete_review_photos', 10, 1 );

/** Accessible, lazy thumbnails for approved review cards; JS upgrades buttons to a lightbox. */
function jluxe_render_review_photo_thumbnails( $comment ): void {
	$comment = is_object( $comment ) ? $comment : get_comment( (int) $comment );
	if ( ! $comment || ! in_array( (string) $comment->comment_approved, array( '1', 'approve' ), true ) ) { return; }
	$ids = get_comment_meta( (int) $comment->comment_ID, '_jluxe_review_photo_ids', true );
	if ( ! is_array( $ids ) ) { return; }
	$ids = array_slice( array_filter( array_map( 'absint', $ids ) ), 0, 5 );
	if ( ! $ids ) { return; }
	echo '<div class="jluxe-review-photo-thumbnails" role="group" aria-label="تصاویر این دیدگاه">';
	foreach ( $ids as $index => $id ) {
		if ( '1' !== (string) get_post_meta( $id, '_jluxe_review_photo', true ) || (int) get_post_meta( $id, '_jluxe_review_comment_id', true ) !== (int) $comment->comment_ID || ! wp_attachment_is_image( $id ) ) { continue; }
		$url = wp_get_attachment_image_url( $id, 'large' );
		$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( ! $url || ! $thumb ) { continue; }
		printf( '<button type="button" class="jluxe-review-photo-thumb" data-jluxe-review-photo data-photo-src="%s" data-photo-caption="%s" data-photo-index="%d" aria-label="نمایش تصویر %d از دیدگاه %s"><img src="%s" alt="تصویر ارسالی خریدار" width="72" height="72" loading="lazy" decoding="async" /></button>', esc_url( $url ), esc_attr( (string) $comment->comment_author ), (int) $index, (int) $index + 1, esc_attr( (string) $comment->comment_author ), esc_url( $thumb ) );
	}
	echo '</div>';
}
add_action( 'woocommerce_review_after_comment_text', 'jluxe_render_review_photo_thumbnails', 20 );

/** Cached recent approved customer photos for the product-level photo strip. */
function jluxe_get_review_photo_gallery( int $product_id ): array {
	$key = 'jluxe_review_photos_gallery_' . $product_id;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) { return $cached; }
	$items = array();
	$comments = get_comments( array( 'post_id' => $product_id, 'status' => 'approve', 'type' => 'review', 'number' => 100, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
	foreach ( $comments as $comment ) {
		$ids = get_comment_meta( (int) $comment->comment_ID, '_jluxe_review_photo_ids', true );
		foreach ( is_array( $ids ) ? array_reverse( $ids ) : array() as $id ) {
			$id = absint( $id );
			if ( ! $id || '1' !== (string) get_post_meta( $id, '_jluxe_review_photo', true ) || (int) get_post_meta( $id, '_jluxe_review_comment_id', true ) !== (int) $comment->comment_ID || ! wp_attachment_is_image( $id ) ) { continue; }
			$src = wp_get_attachment_image_url( $id, 'large' );
			$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( ! $src || ! $thumb ) { continue; }
			$items[] = array( 'id' => $id, 'src' => $src, 'thumb' => $thumb, 'author' => (string) $comment->comment_author );
			if ( count( $items ) >= 12 ) { break 2; }
		}
	}
	set_transient( $key, $items, HOUR_IN_SECONDS );
	return $items;
}

function jluxe_render_review_photo_gallery( int $product_id ): void {
	$photos = jluxe_get_review_photo_gallery( $product_id );
	if ( ! $photos ) { return; }
	echo '<section class="jluxe-review-photo-gallery" aria-label="تصاویر خریداران"><h3>تصاویر خریداران</h3><div class="jluxe-review-photo-gallery__track">';
	foreach ( $photos as $photo ) {
		printf( '<button type="button" data-jluxe-review-photo data-photo-src="%s" data-photo-caption="%s" aria-label="نمایش تصویر خریدار %s"><img src="%s" alt="تصویر خریدار" width="72" height="72" loading="lazy" decoding="async" /><span class="screen-reader-text">ارسال‌شده توسط %s</span></button>', esc_url( $photo['src'] ), esc_attr( $photo['author'] ), esc_attr( $photo['author'] ), esc_url( $photo['thumb'] ), esc_html( $photo['author'] ) );
	}
	echo '</div></section>';
}

/** Let administrators inspect safe, owned photo thumbnails in comment moderation. */
function jluxe_admin_review_photo_comment_text( string $text, $comment = null ): string {
	if ( ! is_admin() ) { return $text; }
	$comment = is_object( $comment ) ? $comment : null;
	if ( ! $comment || 'review' !== (string) $comment->comment_type ) { return $text; }
	$ids = get_comment_meta( (int) $comment->comment_ID, '_jluxe_review_photo_ids', true );
	if ( ! is_array( $ids ) ) { return $text; }
	$html = '';
	foreach ( array_slice( $ids, 0, 5 ) as $id ) {
		$id = absint( $id );
		if ( ! $id || '1' !== (string) get_post_meta( $id, '_jluxe_review_photo', true ) || (int) get_post_meta( $id, '_jluxe_review_comment_id', true ) !== (int) $comment->comment_ID || ! wp_attachment_is_image( $id ) ) { continue; }
		$url = wp_get_attachment_image_url( $id, 'full' );
		$image = wp_get_attachment_image( $id, array( 64, 64 ), false, array( 'alt' => 'عکس ارسالی دیدگاه', 'loading' => 'lazy' ) );
		if ( ! $url || ! is_string( $image ) || '' === $image ) { continue; }
		$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $image . '</a>';
	}
	return '' !== $html ? $text . '<div class="jluxe-admin-review-photos" style="display:flex;gap:6px;margin-top:8px">' . $html . '</div>' : $text;
}
add_filter( 'comment_text', 'jluxe_admin_review_photo_comment_text', 20, 2 );

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
	delete_transient( 'jluxe_review_photos_gallery_' . $product_id );
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
	jluxe_render_review_photo_gallery( $product_id );
	$averages = jluxe_get_review_criteria_averages( $product_id );
	if ( ! $averages ) { return; }
	echo '<div class="jluxe-review-averages" aria-label="میانگین معیارهای نظرهای تأییدشده">';
	foreach ( $averages as $item ) {
		printf( '<div class="jluxe-review-average"><span>%s</span><meter min="0" max="5" value="%s" aria-label="%s"></meter><span>%s از ۵ (%s رأی)</span></div>',
			esc_html( $item['label'] ), esc_attr( (string) $item['average'] ), esc_attr( $item['label'] ), esc_html( jluxe_fa_digits( $item['average'] ) ), esc_html( jluxe_fa_digits( $item['count'] ) ) );
	}
	echo '</div>';
}
