<?php
defined( 'ABSPATH' ) || exit;

/** WooCommerce product admin fields, saves and cache invalidation. */

/** Render the product-level related product selector in WooCommerce's Linked Products tab. */
function jluxe_render_manual_related_products_field(): void {
	global $post;

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$product_id = absint( $post->ID );
	$selected   = get_post_meta( $product_id, '_jluxe_related_product_ids', true );
	$selected   = is_array( $selected ) ? array_values( array_unique( array_filter( array_map( 'absint', $selected ) ) ) ) : array();

	echo '<p class="form-field _jluxe_related_product_ids_field">';
	echo '<label for="_jluxe_related_product_ids">' . esc_html__( 'محصولات مرتبطِ دستی', 'jluxe' ) . '</label>';
	echo '<select id="_jluxe_related_product_ids" name="_jluxe_related_product_ids[]" class="wc-product-search" style="width: 50%;" multiple="multiple" data-placeholder="' . esc_attr( __( 'برای جستجو نام محصول را وارد کنید…', 'jluxe' ) ) . '" data-action="woocommerce_json_search_products">';

	foreach ( $selected as $related_id ) {
		$related_product = wc_get_product( $related_id );
		if ( ! $related_product instanceof WC_Product ) {
			continue;
		}
		echo '<option value="' . esc_attr( (string) $related_id ) . '" selected="selected">' . esc_html( $related_product->get_name() ) . '</option>';
	}

	echo '</select>';
	echo '<input type="hidden" name="_jluxe_related_product_order" value="' . esc_attr( implode( ',', $selected ) ) . '" />';
	echo '<input type="hidden" name="_jluxe_related_product_ids_present" value="1" />';
	echo '<span class="description">' . esc_html__( 'فهرست دستی مشخص می‌کند کدام محصولات نمایش داده شوند؛ ترتیب نمایش همیشه بر اساس موجودی (موجودها اول) و سپس جدیدتر بودن است. اگر فهرست خالی باشد، محصولات هم‌دسته و در صورت کمبود پیشنهادهای ووکامرس به‌عنوان نامزد اضافه می‌شوند.', 'jluxe' ) . '</span>';
	echo '</p>';
}

/** Save the ordered list submitted by the product editor. */
function jluxe_save_manual_related_products( int $product_id ): void {
	if ( ! isset( $_POST['_jluxe_related_product_ids_present'] ) || ! current_user_can( 'edit_post', $product_id ) ) {
		return;
	}

	$raw_ids        = isset( $_POST['_jluxe_related_product_ids'] ) ? wp_unslash( $_POST['_jluxe_related_product_ids'] ) : array();
	$raw_ids        = is_array( $raw_ids ) ? array_values( array_filter( array_map( 'absint', $raw_ids ) ) ) : array();
	$selected_lookup = array_fill_keys( $raw_ids, true );
	$ordered_raw     = isset( $_POST['_jluxe_related_product_order'] ) ? (string) wp_unslash( $_POST['_jluxe_related_product_order'] ) : '';
	$ordered_ids     = '' !== $ordered_raw ? array_map( 'absint', explode( ',', $ordered_raw ) ) : array();
	$ordered_ids     = array_values( array_filter( array_unique( $ordered_ids ), static function ( $id ) use ( $selected_lookup ) {
		return isset( $selected_lookup[ $id ] );
	} ) );
	$raw_ids         = array_merge( $ordered_ids, array_values( array_diff( $raw_ids, $ordered_ids ) ) );
	$ids             = array();

	foreach ( $raw_ids as $raw_id ) {
		$related_id = absint( $raw_id );
		if ( ! $related_id || $related_id === $product_id || in_array( $related_id, $ids, true ) ) {
			continue;
		}

		$related_product = wc_get_product( $related_id );
		if ( ! $related_product instanceof WC_Product || 'publish' !== $related_product->get_status() || 'hidden' === $related_product->get_catalog_visibility() ) {
			continue;
		}

		$ids[] = $related_id;
	}

	update_post_meta( $product_id, '_jluxe_related_product_ids', $ids );
}

/** Load the order-preservation helper only in the product editor. */
function jluxe_related_products_admin_assets( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! is_object( $screen ) || 'product' !== ( $screen->post_type ?? '' ) ) {
		return;
	}

	wp_enqueue_media();
	$file = JLUXE_THEME_DIR . '/assets/js/related-products-admin.js';
	if ( is_readable( $file ) ) {
		wp_enqueue_script(
			'jluxe-related-products-admin',
			JLUXE_THEME_URI . '/assets/js/related-products-admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			(string) filemtime( $file ),
			true
		);
	}
	$video_file = JLUXE_THEME_DIR . '/assets/js/product-video-admin.js';
	if ( is_readable( $video_file ) ) {
		wp_enqueue_script( 'jluxe-product-video-admin', JLUXE_THEME_URI . '/assets/js/product-video-admin.js', array( 'jquery' ), (string) filemtime( $video_file ), true );
	}
}

/**
 * دو بج‌ِ اعتماد زیرِ عنوانِ صفحه‌ی محصول («گارانتی اصالت کالا» و «کالای
 * دارای ضمانت») — قبلاً «گارانتی اصالت کالا» برای همه‌ی محصولات به‌صورت
 * ثابت نمایش داده می‌شد (باگِ واقعیِ گزارش‌شده: باید فقط برای محصولاتی که
 * واقعاً چنین ضمانتی دارند نمایش داده شود، نه برای همه). دو کنترلِ مستقل در
 * تبِ «گزینه‌های زرین»ِ اطلاعات محصول قرار می‌گیرند؛ خروجی در هر دو چیدمان
 * با jluxe_get_product_trust_badges() خوانده می‌شود.
 */
function jluxe_register_product_data_tab( array $tabs ): array {
	$tabs['jluxe_options'] = array(
		'label'    => 'گزینه‌های زرین',
		'target'   => 'jluxe_product_options',
		'priority' => 80,
	);
	return $tabs;
}

function jluxe_render_product_badge_fields(): void {
	global $post;

	if ( ! $post || empty( $post->ID ) ) {
		return;
	}

	echo '<div id="jluxe_product_options" class="panel woocommerce_options_panel">';
	echo '<input type="hidden" name="jluxe_product_options_present" value="1" />';
	echo '<div class="options_group">';

	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_badge_authenticity',
			'value'       => get_post_meta( $post->ID, '_jluxe_badge_authenticity', true ),
			'label'       => 'گارانتی اصالت کالا',
			'description' => 'روی صفحه‌ی محصول، زیرِ عنوان، یک بج سبز «گارانتی اصالت کالا» نمایش داده می‌شه.',
		)
	);

	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_badge_warranty',
			'value'       => get_post_meta( $post->ID, '_jluxe_badge_warranty', true ),
			'label'       => 'کالای دارای ضمانت',
			'description' => 'روی صفحه‌ی محصول، زیرِ عنوان، یک بج «کالای دارای ضمانت» نمایش داده می‌شه.',
		)
	);

	echo '</div>';

	$video_id  = absint( get_post_meta( $post->ID, '_jluxe_product_video_id', true ) );
	$poster_id = absint( get_post_meta( $post->ID, '_jluxe_product_video_poster_id', true ) );
	$video_name = $video_id ? basename( (string) ( get_attached_file( $video_id ) ?: get_the_title( $video_id ) ) ) : '';
	$poster_name = $poster_id ? basename( (string) ( get_attached_file( $poster_id ) ?: get_the_title( $poster_id ) ) ) : '';
	echo '<div class="options_group jluxe-product-video-fields">';
	echo '<p class="form-field"><strong>ویدیوی کوتاه محصول (اختیاری)</strong></p>';
	echo '<p class="form-field"><label for="_jluxe_product_video_id">فایل ویدیو</label><input type="hidden" id="_jluxe_product_video_id" name="_jluxe_product_video_id" value="' . esc_attr( (string) $video_id ) . '" /><span class="jluxe-product-media-name">' . esc_html( $video_name ?: 'ویدیویی انتخاب نشده است' ) . '</span> <button type="button" class="button jluxe-product-video-select">انتخاب ویدیو</button> <button type="button" class="button jluxe-product-video-remove"' . ( $video_id ? '' : ' hidden' ) . '>حذف ویدیو</button><span class="description">فقط فایل ویدیویی MP4 یا WebM را انتخاب کنید.</span></p>';
	echo '<p class="form-field"><label for="_jluxe_product_video_poster_id">تصویر پوستر (اختیاری)</label><input type="hidden" id="_jluxe_product_video_poster_id" name="_jluxe_product_video_poster_id" value="' . esc_attr( (string) $poster_id ) . '" /><span class="jluxe-product-poster-name">' . esc_html( $poster_name ?: 'پوستر انتخاب نشده است؛ تصویر شاخص محصول استفاده می‌شود.' ) . '</span> <button type="button" class="button jluxe-product-poster-select">انتخاب تصویر</button> <button type="button" class="button jluxe-product-poster-remove"' . ( $poster_id ? '' : ' hidden' ) . '>حذف پوستر</button></p>';
	echo '</div>';

	$product                 = wc_get_product( (int) $post->ID );
	$key_feature_preview     = jluxe_product_key_feature_cards( $product );

	echo '<div class="options_group">';
	echo '<p class="form-field"><strong>ویژگی‌های برجستهٔ محصول</strong></p>';
	foreach ( $key_feature_preview as $feature_preview ) {
		echo '<p class="form-field"><strong>' . esc_html( $feature_preview['label'] ) . ':</strong> ' . esc_html( $feature_preview['value'] ) . '</p>';
	}
	echo '</div>';

	echo '<div class="options_group">';
	woocommerce_wp_text_input(
		array(
			'id'          => '_delivery_time',
			'value'       => jluxe_get_product_delivery_time( (int) $post->ID ),
			'label'       => 'زمان ارسال',
			'placeholder' => 'مثلاً: ارسال سریع یا ارسال طی ۳ روز کاری',
			'description' => 'متنی که در صفحهٔ محصول کنار عنوان «زمان ارسال» نمایش داده می‌شود.',
			'desc_tip'    => true,
		)
	);
	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_delivery_time_enabled',
			'value'       => jluxe_is_product_delivery_time_enabled( (int) $post->ID ) ? 'yes' : 'no',
			'label'       => 'نمایش ردیف زمان ارسال',
			'description' => 'برای نمایش یا پنهان‌کردن همین ردیف در صفحهٔ محصول.',
		)
	);
	echo '</div>';

	$notice_text = get_post_meta( $post->ID, '_jluxe_product_notice_text', true );
	$notice_text = is_scalar( $notice_text ) ? (string) $notice_text : '';
	$notice_type = sanitize_key( (string) get_post_meta( $post->ID, '_jluxe_product_notice_type', true ) );
	if ( ! in_array( $notice_type, array( 'warning', 'info' ), true ) ) {
		$notice_type = 'warning';
	}
	$notice_visibility = sanitize_key( (string) get_post_meta( $post->ID, '_jluxe_product_notice_visibility', true ) );
	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_product_notice_enabled',
			'value'       => 'hidden' === $notice_visibility ? 'no' : 'yes',
			'label'       => 'نمایش کادر اخطار/اطلاعیه',
			'description' => 'با واردکردن متن، کادر به‌طور پیش‌فرض نمایش داده می‌شود؛ برای پنهان‌کردن آن، این گزینه را خاموش کنید.',
		)
	);
	echo '<p class="form-field"><label for="_jluxe_product_notice_type">نوع کادر</label><select id="_jluxe_product_notice_type" name="_jluxe_product_notice_type"><option value="warning"' . ( 'warning' === $notice_type ? ' selected="selected"' : '' ) . '>اخطار</option><option value="info"' . ( 'info' === $notice_type ? ' selected="selected"' : '' ) . '>اطلاعیه</option></select></p>';
	echo '<p class="form-field"><label for="_jluxe_product_notice_text">متن اخطار/اطلاعیه</label><textarea id="_jluxe_product_notice_text" name="_jluxe_product_notice_text" rows="4" class="large-text">' . esc_textarea( $notice_text ) . '</textarea></p>';
	echo '</div>';
	echo '</div>';
}

/** Invalidate only this product and its parent; never flush the site-wide page/object cache. */
function jluxe_purge_product_related_caches( int $product_id ): void {
	$product = wc_get_product( $product_id );
	$ids     = array( $product_id );
	if ( $product instanceof WC_Product && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$ids[] = $product->get_parent_id();
	}
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	foreach ( $ids as $id ) {
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $id );
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $id );
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $id );
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_post', $id );
		}
	}
}

/** Purge only the homepage URL, whose featured-product row can change with stock or starring. */
function jluxe_purge_featured_homepage_cache( int $product_id = 0 ): void {
	$homepage_url = home_url( '/' );
	if ( function_exists( 'rocket_clean_files' ) ) {
		rocket_clean_files( array( $homepage_url ) );
	}
	if ( function_exists( 'w3tc_flush_url' ) ) {
		w3tc_flush_url( $homepage_url );
	}
	if ( defined( 'LSCWP_V' ) ) {
		do_action( 'litespeed_purge_url', $homepage_url );
	}
	// Give other full-page-cache integrations a targeted, URL-only invalidation hook.
	do_action( 'jluxe_purge_featured_homepage_cache', $homepage_url, $product_id );
}

/** Resolve a variation to its parent and purge the homepage only if that product is featured. */
function jluxe_purge_featured_homepage_cache_for_product( int $product_id ): void {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$product = wc_get_product( $product_id );
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$parent = wc_get_product( (int) $product->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			$product = $parent;
		}
	}
	if ( method_exists( $product, 'get_featured' ) && $product->get_featured() ) {
		jluxe_purge_featured_homepage_cache( (int) $product->get_id() );
	}
}

/** Purge the targeted homepage URL when WooCommerce adds or removes its native featured term. */
function jluxe_purge_homepage_after_featured_term_change( $object_id, $terms, $term_taxonomy_ids, $taxonomy, $append, $old_term_taxonomy_ids ): void {
	if ( 'product_visibility' !== $taxonomy || ! function_exists( 'get_term_by' ) ) {
		return;
	}
	$featured_term = get_term_by( 'slug', 'featured', 'product_visibility' );
	$featured_ttid = is_object( $featured_term ) ? absint( $featured_term->term_taxonomy_id ?? 0 ) : 0;
	if ( $featured_ttid < 1 ) {
		return;
	}
	$changed_terms = array_merge( (array) $term_taxonomy_ids, (array) $old_term_taxonomy_ids );
	$changed_terms = array_map( 'absint', $changed_terms );
	if ( in_array( $featured_ttid, $changed_terms, true ) ) {
		jluxe_purge_featured_homepage_cache( absint( $object_id ) );
	}
}

/**
 * Stock status is the live sort key for product archives. Flush only the affected
 * product and (for variations) its parent when that metadata changes, so persistent
 * post, WooCommerce, and page caches cannot preserve the previous stock grouping.
 */
function jluxe_invalidate_catalog_stock_order_cache( $meta_id, $object_id, $meta_key, $meta_value = null ): void {
	if ( '_stock_status' !== $meta_key ) {
		return;
	}

	$product_id = absint( $object_id );
	if ( ! $product_id ) {
		return;
	}

	if ( function_exists( 'jluxe_purge_product_related_caches' ) && function_exists( 'wc_get_product' ) ) {
		jluxe_purge_product_related_caches( $product_id );
		jluxe_purge_featured_homepage_cache_for_product( $product_id );
	} elseif ( function_exists( 'clean_post_cache' ) ) {
		clean_post_cache( $product_id );
	}
}

/**
 * The option-panel marker distinguishes an intentional checkbox save from
 * stock, REST, import, and bulk updates that do not submit this panel. An
 * absent checkbox means "off" only when the panel itself was submitted.
 */
function jluxe_save_product_badge_fields( int $post_id ): void {
	if ( ! isset( $_POST['jluxe_product_options_present'] ) || '1' !== (string) $_POST['jluxe_product_options_present'] || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_jluxe_badge_authenticity', isset( $_POST['_jluxe_badge_authenticity'] ) ? 'yes' : 'no' );
	update_post_meta( $post_id, '_jluxe_badge_warranty', isset( $_POST['_jluxe_badge_warranty'] ) ? 'yes' : 'no' );

	$video_id = isset( $_POST['_jluxe_product_video_id'] ) && is_scalar( $_POST['_jluxe_product_video_id'] ) ? absint( wp_unslash( $_POST['_jluxe_product_video_id'] ) ) : 0;
	$video_mime = $video_id ? (string) get_post_mime_type( $video_id ) : '';
	if ( $video_id && in_array( strtolower( $video_mime ), array( 'video/mp4', 'video/webm' ), true ) && get_post( $video_id ) instanceof WP_Post && 'attachment' === get_post_type( $video_id ) ) {
		update_post_meta( $post_id, '_jluxe_product_video_id', $video_id );
	} else {
		delete_post_meta( $post_id, '_jluxe_product_video_id' );
	}
	$poster_id = isset( $_POST['_jluxe_product_video_poster_id'] ) && is_scalar( $_POST['_jluxe_product_video_poster_id'] ) ? absint( wp_unslash( $_POST['_jluxe_product_video_poster_id'] ) ) : 0;
	if ( $poster_id && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( $poster_id ) ) {
		update_post_meta( $post_id, '_jluxe_product_video_poster_id', $poster_id );
	} else {
		delete_post_meta( $post_id, '_jluxe_product_video_poster_id' );
	}

	/*
	 * Do not write the legacy key-feature selection/brand metadata. Summary
	 * cards now follow WooCommerce's own visible/non-variation flags, and
	 * historical product metadata must remain byte-for-byte untouched.
	 */
	update_post_meta( $post_id, '_jluxe_delivery_time_enabled', isset( $_POST['_jluxe_delivery_time_enabled'] ) ? 'yes' : 'no' );
	/* Partial option updates must not erase or hide a notice whose fields were not submitted. */
	if ( isset( $_POST['_jluxe_product_notice_text'] ) && is_scalar( $_POST['_jluxe_product_notice_text'] ) ) {
		$notice_is_visible = isset( $_POST['_jluxe_product_notice_enabled'] );
		/*
		 * The legacy yes/no key defaulted to "no", which silently hid text users
		 * had entered. Keep it in sync for integrations, but use an explicit
		 * visibility choice so legacy notices with text are visible by default.
		 */
		update_post_meta( $post_id, '_jluxe_product_notice_enabled', $notice_is_visible ? 'yes' : 'no' );
		update_post_meta( $post_id, '_jluxe_product_notice_visibility', $notice_is_visible ? 'visible' : 'hidden' );

		$notice_text = isset( $_POST['_jluxe_product_notice_text'] ) && is_scalar( $_POST['_jluxe_product_notice_text'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['_jluxe_product_notice_text'] ) )
			: '';
		$notice_type = isset( $_POST['_jluxe_product_notice_type'] ) && is_scalar( $_POST['_jluxe_product_notice_type'] )
			? sanitize_key( wp_unslash( (string) $_POST['_jluxe_product_notice_type'] ) )
			: 'warning';
		if ( ! in_array( $notice_type, array( 'warning', 'info' ), true ) ) {
			$notice_type = 'warning';
		}
		update_post_meta( $post_id, '_jluxe_product_notice_text', $notice_text );
		update_post_meta( $post_id, '_jluxe_product_notice_type', $notice_type );
	}

	jluxe_purge_product_related_caches( $post_id );
}

/**
 * چک‌باکسِ روشن/خاموش‌کردنِ دستیِ پاپ‌آپِ «محصولات پیشنهادی» برایِ هر
 * محصول جداگونه — طبقِ درخواستِ صریحِ کاربر. برخلافِ دو بجِ بالا، این‌جا
 * پیش‌فرض (وقتی متا هنوز اصلاً ذخیره نشده، یعنی محصولاتِ موجود قبل از این
 * قابلیت) باید «روشن» باشه — وگرنه همون لحظه‌ی آپلود، پاپ‌آپ برایِ همه‌ی
 * محصولاتِ موجود خاموش می‌شد (رگرسیون). برایِ همین مقدارِ چک‌باکس صریح
 * محاسبه می‌شه: فقط وقتی متا دقیقاً 'no'ه غیرفعال نشون داده می‌شه.
 */
function jluxe_render_suggested_modal_toggle_field(): void {
	global $post;

	$stored  = get_post_meta( $post->ID, '_jluxe_suggested_modal_enabled', true );
	$checked = 'no' !== $stored ? 'yes' : '';

	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => '_jluxe_suggested_modal_enabled',
			'value'       => $checked,
			'label'       => 'پاپ‌آپ محصولات پیشنهادی',
			'description' => 'پس از افزودن موفق این محصول به سبد (بعد از انتخاب رنگ/سایز در محصول متغیر)، پاپ‌آپ نمایش داده می‌شود. پیشنهادها و خدمات از «زرین ← اضافه خرید» تنظیم می‌شوند؛ اگر موردی برای پیشنهاد تعریف نشده باشد، پاپ‌آپ پیامِ نبود پیشنهاد را نشان می‌دهد.',
		)
	);
	echo '</div>';
}

function jluxe_save_suggested_modal_toggle_field( int $post_id ): void {
	update_post_meta( $post_id, '_jluxe_suggested_modal_enabled', isset( $_POST['_jluxe_suggested_modal_enabled'] ) ? 'yes' : 'no' );
}

/** Sanitize the product editor's existing canonical `_delivery_time` field. */
function jluxe_save_product_delivery_time_primary_meta( WC_Product $product ): void {
	if ( ! isset( $_POST['_delivery_time'] ) || ! is_scalar( $_POST['_delivery_time'] ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( (string) $_POST['_delivery_time'] ) );
	$product->update_meta_data( '_delivery_time', $value );
}

/** Support the legacy product-editor save hook for the canonical field too. */
function jluxe_save_product_delivery_time_primary_meta_legacy( int $product_id ): void {
	if ( ! isset( $_POST['_delivery_time'] ) || ! is_scalar( $_POST['_delivery_time'] ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( (string) $_POST['_delivery_time'] ) );
	update_post_meta( $product_id, '_delivery_time', $value );
}

/** Continue accepting the old theme-specific meta key for existing integrations. */
function jluxe_save_product_delivery_time_meta( WC_Product $product ): void {
	if ( ! isset( $_POST['_jluxe_delivery_time'] ) || ! is_scalar( $_POST['_jluxe_delivery_time'] ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( (string) $_POST['_jluxe_delivery_time'] ) );
	$product->update_meta_data( '_jluxe_delivery_time', $value );
}

/** Continue accepting old theme-specific values posted by the legacy editor. */
function jluxe_save_product_delivery_time_meta_legacy( int $product_id ): void {
	if ( ! isset( $_POST['_jluxe_delivery_time'] ) || ! is_scalar( $_POST['_jluxe_delivery_time'] ) ) {
		return;
	}

	$value = sanitize_text_field( wp_unslash( (string) $_POST['_jluxe_delivery_time'] ) );
	update_post_meta( $product_id, '_jluxe_delivery_time', $value );
}
