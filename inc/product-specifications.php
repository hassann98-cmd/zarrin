<?php
/**
 * Product-level editor for Boom's legacy grouped specifications.
 *
 * The legacy _boom_advanced_attributes value is only read to prefill this
 * form. Edits are stored in JLuxe-owned metadata and selected by an explicit
 * marker, so even an empty override can intentionally hide all old groups.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_add_product_specifications_meta_box(): void {
	add_meta_box(
		'jluxe_product_specifications',
		'مشخصات فنی گروه‌بندی‌شده',
		'jluxe_render_product_specifications_meta_box',
		'product',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'jluxe_add_product_specifications_meta_box' );

/** Load the editor interactions only on product edit screens. */
function jluxe_enqueue_product_specifications_admin_assets( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	global $post;
	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/product-specifications-admin.js';
	wp_enqueue_script(
		'jluxe-product-specifications-admin',
		JLUXE_THEME_URI . '/assets/js/product-specifications-admin.js',
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_enqueue_product_specifications_admin_assets' );

/** Render one key/value feature row for either a saved group or a JS template. */
function jluxe_render_product_specification_row_fields( $group_index, $row_index, array $item ): void {
	?>
	<div class="jluxe-spec-row">
		<label class="jluxe-spec-field">
			<span class="screen-reader-text">نام ویژگی</span>
			<input type="text" class="widefat" name="jluxe_product_specs[<?php echo esc_attr( (string) $group_index ); ?>][items][<?php echo esc_attr( (string) $row_index ); ?>][label]" value="<?php echo esc_attr( $item['label'] ?? '' ); ?>" placeholder="نام ویژگی (مثلاً: جنس)" aria-label="نام ویژگی" />
		</label>
		<label class="jluxe-spec-field">
			<span class="screen-reader-text">مقدار ویژگی</span>
			<input type="text" class="widefat" name="jluxe_product_specs[<?php echo esc_attr( (string) $group_index ); ?>][items][<?php echo esc_attr( (string) $row_index ); ?>][value]" value="<?php echo esc_attr( $item['value'] ?? '' ); ?>" placeholder="مقدار ویژگی" aria-label="مقدار ویژگی" />
		</label>
		<button type="button" class="button jluxe-spec-remove-row" data-jluxe-spec-remove-row aria-label="حذف این ویژگی">حذف ویژگی</button>
	</div>
	<?php
}

/** Render one editable group, including all of its feature rows. */
function jluxe_render_product_specification_group_fields( $group_index, array $group ): void {
	$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
	?>
	<section class="jluxe-spec-group" data-jluxe-spec-group data-group-index="<?php echo esc_attr( (string) $group_index ); ?>" data-next-row-index="<?php echo esc_attr( (string) count( $items ) ); ?>">
		<header class="jluxe-spec-group-header">
			<label class="jluxe-spec-group-title">
				<span>عنوان گروه</span>
				<input type="text" class="widefat" name="jluxe_product_specs[<?php echo esc_attr( (string) $group_index ); ?>][title]" value="<?php echo esc_attr( $group['title'] ?? '' ); ?>" placeholder="مثلاً: مشخصات فنی" aria-label="عنوان گروه مشخصات" />
			</label>
			<button type="button" class="button jluxe-spec-remove-group" data-jluxe-spec-remove-group aria-label="حذف این گروه">حذف گروه</button>
		</header>
		<div class="jluxe-spec-rows" data-jluxe-spec-rows>
			<?php foreach ( $items as $row_index => $item ) : ?>
				<?php if ( is_array( $item ) ) : ?>
					<?php jluxe_render_product_specification_row_fields( $group_index, $row_index, $item ); ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<p class="jluxe-spec-add-row-wrap"><button type="button" class="button" data-jluxe-spec-add-row>افزودن ویژگی</button></p>
	</section>
	<?php
}

/** Render the grouped specifications editor on the product post screen. */
function jluxe_render_product_specifications_meta_box( WP_Post $post ): void {
	$has_override = '1' === (string) get_post_meta( $post->ID, '_jluxe_product_advanced_attributes_override', true );
	$groups       = jluxe_get_product_advanced_attribute_groups( (int) $post->ID );
	// Give new products a ready-to-fill row, but don't disguise an intentional
	// empty override as an editable old group after the user removed everything.
	if ( ! $groups && ! $has_override ) {
		$groups = array(
			array(
				'title' => '',
				'items' => array( array( 'label' => '', 'value' => '' ) ),
			),
		);
	}
	?>
	<style>
		.jluxe-spec-group { margin: 0 0 14px; padding: 14px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; }
		.jluxe-spec-group-header { display: flex; align-items: flex-end; gap: 12px; margin-bottom: 12px; }
		.jluxe-spec-group-title { flex: 1; display: grid; gap: 5px; font-weight: 600; }
		.jluxe-spec-rows { display: grid; gap: 8px; }
		.jluxe-spec-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; align-items: center; gap: 8px; }
		.jluxe-spec-field { min-width: 0; }
		.jluxe-spec-remove-group, .jluxe-spec-remove-row { color: #b32d2e; }
		.jluxe-spec-add-row-wrap { margin: 10px 0 0; }
		#jluxe-product-spec-group-template, #jluxe-product-spec-row-template { display: none; }
		@media screen and (max-width: 782px) { .jluxe-spec-group-header { align-items: stretch; flex-direction: column; } .jluxe-spec-row { grid-template-columns: 1fr; } }
	</style>
	<p class="description">گروه‌ها و ویژگی‌های Boom قدیمی برای شروع در این فرم بارگذاری می‌شوند. ذخیرهٔ تغییرات فقط override پوسته را می‌نویسد و متای اصلی Boom را دست‌نخورده نگه می‌دارد؛ خالی‌کردن فرم هم به‌عنوان حذف همهٔ گروه‌ها ثبت می‌شود. ویژگی‌های تب «ویژگی‌ها»ی ووکامرس جدا هستند و این فرم آن‌ها را تغییر نمی‌دهد.</p>
	<?php wp_nonce_field( 'jluxe_product_specifications_save', 'jluxe_product_specifications_nonce' ); ?>
	<input type="hidden" name="jluxe_product_specs_present" value="1" />
	<div id="jluxe-product-spec-groups" data-next-group-index="<?php echo esc_attr( (string) count( $groups ) ); ?>">
		<?php foreach ( $groups as $group_index => $group ) : ?>
			<?php jluxe_render_product_specification_group_fields( $group_index, $group ); ?>
		<?php endforeach; ?>
	</div>
	<template id="jluxe-product-spec-group-template">
		<section class="jluxe-spec-group" data-jluxe-spec-group data-group-index="__GROUP__" data-next-row-index="0">
			<header class="jluxe-spec-group-header">
				<label class="jluxe-spec-group-title">
					<span>عنوان گروه</span>
					<input type="text" class="widefat" name="jluxe_product_specs[__GROUP__][title]" value="" placeholder="مثلاً: مشخصات فنی" aria-label="عنوان گروه مشخصات" />
				</label>
				<button type="button" class="button jluxe-spec-remove-group" data-jluxe-spec-remove-group aria-label="حذف این گروه">حذف گروه</button>
			</header>
			<div class="jluxe-spec-rows" data-jluxe-spec-rows></div>
			<p class="jluxe-spec-add-row-wrap"><button type="button" class="button" data-jluxe-spec-add-row>افزودن ویژگی</button></p>
		</section>
	</template>
	<template id="jluxe-product-spec-row-template">
		<?php jluxe_render_product_specification_row_fields( '__GROUP__', '__ROW__', array() ); ?>
	</template>
	<p><button type="button" class="button button-primary" data-jluxe-spec-add-group>افزودن گروه مشخصات</button></p>
	<p><label><input type="checkbox" name="jluxe_product_specs_restore_boom" value="1" /> بازگشت به مشخصات اصلی Boom (حذف override پوسته)</label></p>
	<?php
}

/**
 * Validate and sanitize the submitted editor state. A returned empty array is
 * meaningful: it is a deliberate empty override, not a missing form.
 *
 * @return array<int, array{title: string, items: array<int, array{label: string, value: string}>}>|null
 */
function jluxe_product_specifications_posted_groups( int $post_id, ?bool &$restore_boom = null ): ?array {
	$restore_boom = false;
	if ( ! isset( $_POST['jluxe_product_specs_present'] ) || ! is_scalar( $_POST['jluxe_product_specs_present'] ) || '1' !== (string) wp_unslash( $_POST['jluxe_product_specs_present'] ) ) {
		return null;
	}
	if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
		return null;
	}
	$nonce = isset( $_POST['jluxe_product_specifications_nonce'] ) && is_scalar( $_POST['jluxe_product_specifications_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jluxe_product_specifications_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'jluxe_product_specifications_save' ) ) {
		return null;
	}
	if ( isset( $_POST['jluxe_product_specs_restore_boom'] ) && is_scalar( $_POST['jluxe_product_specs_restore_boom'] ) && '1' === (string) wp_unslash( $_POST['jluxe_product_specs_restore_boom'] ) ) {
		$restore_boom = true;
		return array();
	}

	$posted = isset( $_POST['jluxe_product_specs'] ) && is_array( $_POST['jluxe_product_specs'] ) ? wp_unslash( $_POST['jluxe_product_specs'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce و capability بالاتر بررسی شده؛ مقادیر پایین‌تر یک‌به‌یک sanitize می‌شوند.
	$clean  = array();
	foreach ( array_slice( $posted, 0, 40 ) as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		$title         = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
		$posted_items  = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		$clean_items   = array();
		foreach ( array_slice( $posted_items, 0, 100 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) && is_scalar( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			$value = isset( $item['value'] ) && is_scalar( $item['value'] ) ? sanitize_text_field( (string) $item['value'] ) : '';
			if ( '' === $label || '' === $value ) {
				continue;
			}
			$clean_items[] = array(
				'label' => $label,
				'value' => $value,
			);
		}
		if ( ! $clean_items ) {
			continue; // گروهِ بدونِ ویژگی در خروجی فرانت‌اند قابل‌نمایش نیست.
		}
		if ( '' === $title ) {
			$title = 'مشخصات فنی'; // مقدارهای کامل بدونِ عنوان هم نباید پس از ذخیره ناپدید شوند.
		}
		$clean[] = array(
			'title' => $title,
			'items' => $clean_items,
		);
	}
	return $clean;
}

/** Save the theme override through WooCommerce's current product-object flow. */
function jluxe_save_product_specifications_meta_object( WC_Product $product ): void {
	$restore_boom = false;
	$groups       = jluxe_product_specifications_posted_groups( (int) $product->get_id(), $restore_boom );
	if ( null === $groups ) {
		return;
	}
	if ( $restore_boom ) {
		$product->delete_meta_data( '_jluxe_product_advanced_attributes' );
		$product->delete_meta_data( '_jluxe_product_advanced_attributes_override' );
		return;
	}
	$product->update_meta_data( '_jluxe_product_advanced_attributes', $groups );
	$product->update_meta_data( '_jluxe_product_advanced_attributes_override', '1' );
}
add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_specifications_meta_object' );

/** Save the theme override through the classic product editor flow. */
function jluxe_save_product_specifications_meta( int $post_id ): void {
	$restore_boom = false;
	$groups       = jluxe_product_specifications_posted_groups( $post_id, $restore_boom );
	if ( null === $groups ) {
		return;
	}
	if ( $restore_boom ) {
		delete_post_meta( $post_id, '_jluxe_product_advanced_attributes' );
		delete_post_meta( $post_id, '_jluxe_product_advanced_attributes_override' );
		return;
	}
	update_post_meta( $post_id, '_jluxe_product_advanced_attributes', $groups );
	update_post_meta( $post_id, '_jluxe_product_advanced_attributes_override', '1' );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_specifications_meta' );
