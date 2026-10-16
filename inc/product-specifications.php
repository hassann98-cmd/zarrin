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
	<p class="description">برای کپی از Excel، Google Sheets، Word یا جدول یک وب‌سایت، فقط دو ستون «نام ویژگی» و «مقدار ویژگی» را انتخاب و کپی کنید؛ سپس داخل یکی از دو ورودیِ ردیف مقصد کلیک و جای‌گذاری کنید. جدول HTML، متن تب‌دار و Markdown پشتیبانی می‌شوند؛ سرستون‌های رایجِ نام/ویژگی و مقدار و خط جداکنندهٔ Markdown نادیده گرفته می‌شوند، فقط متن سلول‌ها وارد می‌شود و رنگ، قالب‌بندی، لینک یا کدِ منبع منتقل نمی‌شود. ردیف‌های قبلی حفظ و داده‌های تازه به گروه جاری افزوده می‌شوند؛ متن معمولی مثل همیشه paste می‌شود.</p>
	<p class="description">برای مشخصات فنیِ «ویژگی ← مقدار»، همین ویرایشگر بهترین مسیر است: خروجیِ قابل‌خواندن و ساخت‌یافته تولید می‌کند، در Product JSON-LD به‌صورت PropertyValue می‌آید و به ویژگی‌های استاندارد ووکامرس هم منتقل می‌شود. برای جدول مقایسه‌ای یا اطلاعات چندستونه، از بلوک بومی «جدول» WordPress در توضیحات محصول استفاده کنید، ردیف اول را در تنظیمات بلوک سرستون قرار دهید و در صورت نیاز عنوان کوتاهی در Caption بنویسید؛ جدول را به‌صورت عکس وارد نکنید تا متن برای کاربران و موتورهای جست‌وجو قابل‌خواندن بماند. برای انتقال محصولات قبلی، در فهرست محصولات «اقدامات گروهی» ← «همگام‌سازی مشخصات برای انتقال» را اجرا کنید (حداکثر ۱۰۰ محصول در هر اجرا).</p>
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

/** Normalize a specification or attribute label for safe duplicate detection. */
function jluxe_product_specifications_export_label_key( $label ): string {
	$label = trim( preg_replace( '/\\s+/u', ' ', wp_strip_all_tags( (string) $label ) ) ?? '' );
	return strtolower( $label );
}

/** Read normalized string options from a saved WooCommerce attribute. */
function jluxe_product_specifications_export_attribute_values( WC_Product $product, $attribute ): array {
	if ( ! is_object( $attribute ) ) {
		return array();
	}
	$values = array();
	$name   = method_exists( $attribute, 'get_name' ) ? (string) $attribute->get_name() : '';
	if ( '' !== $name && method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ) {
		$terms = wc_get_product_terms( (int) $product->get_id(), $name, array( 'fields' => 'all' ) );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( is_object( $term ) && isset( $term->name ) && is_scalar( $term->name ) ) {
					$values[] = sanitize_text_field( (string) $term->name );
				} elseif ( is_scalar( $term ) ) {
					$values[] = sanitize_text_field( (string) $term );
				}
			}
		}
	}
	if ( ! $values && method_exists( $attribute, 'get_options' ) ) {
		$options = $attribute->get_options();
		foreach ( is_array( $options ) ? $options : array( $options ) as $option ) {
			if ( is_scalar( $option ) ) {
				$values[] = sanitize_text_field( (string) $option );
			}
		}
	}
	return array_values( array_filter( array_map( 'trim', $values ), static function ( $value ): bool { return '' !== $value; } ) );
}

/** Identify unchanged attributes generated from the grouped-specification editor. */
function jluxe_is_grouped_specification_export_attribute( WC_Product $product, $attribute ): bool {
	if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) || ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) ) {
		return false;
	}
	$name = trim( (string) $attribute->get_name() );
	if ( '' === $name ) {
		return false;
	}
	$snapshot = method_exists( $product, 'get_meta' ) ? $product->get_meta( '_jluxe_product_specs_export_snapshot', true ) : get_post_meta( (int) $product->get_id(), '_jluxe_product_specs_export_snapshot', true );
	$key      = sanitize_title( $name );
	if ( ! is_array( $snapshot ) || ! isset( $snapshot[ $key ] ) || ! is_array( $snapshot[ $key ] ) ) {
		return false;
	}
	$previous = $snapshot[ $key ];
	if ( ! isset( $previous['name'], $previous['values'] ) || ! is_scalar( $previous['name'] ) || ! is_array( $previous['values'] ) || $name !== (string) $previous['name'] ) {
		return false;
	}
	$expected_values = array_values( array_map( 'strval', $previous['values'] ) );
	return jluxe_product_specifications_export_attribute_values( $product, $attribute ) === $expected_values;
}

/**
 * Mirror grouped display data into visible, non-variation WooCommerce custom
 * attributes so the standard product object / REST API / export plugins can
 * transfer it. Existing user-created attributes are never overwritten.
 */
function jluxe_sync_product_specifications_to_wc_attributes( WC_Product $product, array $groups ): bool {
	if ( ! class_exists( 'WC_Product_Attribute' ) || ! method_exists( $product, 'get_attributes' ) || ! method_exists( $product, 'set_attributes' ) || ! method_exists( $product, 'update_meta_data' ) ) {
		return false;
	}

	$product_id       = (int) $product->get_id();
	$attributes = (array) $product->get_attributes();
	$snapshot   = method_exists( $product, 'get_meta' ) ? $product->get_meta( '_jluxe_product_specs_export_snapshot', true ) : get_post_meta( $product_id, '_jluxe_product_specs_export_snapshot', true );
	$snapshot         = is_array( $snapshot ) ? $snapshot : array();
	$existing_by_name = array();
	$position         = count( $attributes );

	foreach ( $attributes as $attribute_key => $attribute ) {
		if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
			continue;
		}
		if ( method_exists( $attribute, 'get_position' ) ) {
			$position = max( $position, (int) $attribute->get_position() + 1 );
		}
		$name = trim( (string) $attribute->get_name() );
		if ( '' === $name ) {
			continue;
		}

		$storage_key = sanitize_title( $name );
		if ( isset( $snapshot[ $storage_key ] ) && is_array( $snapshot[ $storage_key ] ) ) {
			$previous = $snapshot[ $storage_key ];
			$expected_name = isset( $previous['name'] ) && is_scalar( $previous['name'] ) ? (string) $previous['name'] : '';
			$expected_values = isset( $previous['values'] ) && is_array( $previous['values'] ) ? array_values( array_map( 'strval', $previous['values'] ) ) : array();
			$current_values = jluxe_product_specifications_export_attribute_values( $product, $attribute );
			$is_taxonomy = method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy();
			if ( ! $is_taxonomy && $name === $expected_name && $current_values === $expected_values ) {
				unset( $attributes[ $attribute_key ] );
				continue;
			}
		}

		$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name, $product ) : $name;
		$label_key = jluxe_product_specifications_export_label_key( $label );
		if ( '' === $label_key ) {
			continue;
		}
		$visible = ! method_exists( $attribute, 'get_visible' ) || (bool) $attribute->get_visible();
		if ( ! isset( $existing_by_name[ $label_key ] ) ) {
			$existing_by_name[ $label_key ] = array( 'visible' => false, 'values' => array() );
		}
		$existing_by_name[ $label_key ]['visible'] = $existing_by_name[ $label_key ]['visible'] || $visible;
		$existing_by_name[ $label_key ]['values']  = array_values( array_unique( array_merge( $existing_by_name[ $label_key ]['values'], jluxe_product_specifications_export_attribute_values( $product, $attribute ) ) ) );
	}

	$rows = array();
	foreach ( array_slice( $groups, 0, 40 ) as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		$group_title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : 'مشخصات فنی';
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? array_slice( $group['items'], 0, 100 ) : array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) || ! is_scalar( $item['label'] ) || ! is_scalar( $item['value'] ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) $item['label'] );
			$value = sanitize_text_field( (string) $item['value'] );
			if ( '' !== $label && '' !== $value ) {
				$rows[] = array( 'group' => $group_title, 'label' => $label, 'value' => $value );
			}
		}
	}

	$new_snapshot      = array();
	$generated_by_name = array();
	foreach ( $rows as $row ) {
		$label_key = jluxe_product_specifications_export_label_key( $row['label'] );
		if ( '' === $label_key ) {
			continue;
		}
		if ( isset( $generated_by_name[ $label_key ] ) && in_array( $row['value'], $generated_by_name[ $label_key ], true ) ) {
			continue;
		}
		if ( isset( $existing_by_name[ $label_key ] ) && $existing_by_name[ $label_key ]['visible'] && in_array( $row['value'], $existing_by_name[ $label_key ]['values'], true ) ) {
			continue;
		}

		$attribute_name = $row['label'];
		$name_key       = $label_key;
		if ( isset( $existing_by_name[ $label_key ] ) || isset( $generated_by_name[ $label_key ] ) ) {
			$prefix        = '' !== trim( $row['group'] ) ? trim( $row['group'] ) : 'مشخصات فنی';
			$attribute_name = $prefix . ' — ' . $row['label'];
			$name_key       = jluxe_product_specifications_export_label_key( $attribute_name );
		}
		$base_name = $attribute_name;
		$suffix    = 2;
		while ( '' === $name_key || isset( $existing_by_name[ $name_key ] ) || isset( $generated_by_name[ $name_key ] ) ) {
			if ( isset( $existing_by_name[ $name_key ] ) && $existing_by_name[ $name_key ]['visible'] && in_array( $row['value'], $existing_by_name[ $name_key ]['values'], true ) ) {
				$attribute_name = '';
				break;
			}
			$attribute_name = $base_name . ' (' . $suffix . ')';
			$name_key       = jluxe_product_specifications_export_label_key( $attribute_name );
			++$suffix;
		}
		if ( '' === $attribute_name ) {
			continue;
		}

		$wc_attribute = new WC_Product_Attribute();
		$wc_attribute->set_id( 0 );
		$wc_attribute->set_name( $attribute_name );
		$wc_attribute->set_options( array( $row['value'] ) );
		$wc_attribute->set_position( $position++ );
		$wc_attribute->set_visible( true );
		$wc_attribute->set_variation( false );

		$array_key = sanitize_title( $attribute_name );
		$key_base  = $array_key;
		$key_suffix = 2;
		while ( array_key_exists( $array_key, $attributes ) ) {
			$array_key = $key_base . '-' . $key_suffix;
			++$key_suffix;
		}
		$attributes[ $array_key ] = $wc_attribute;
		$new_snapshot[ sanitize_title( $attribute_name ) ] = array(
			'name'   => $attribute_name,
			'values' => array( $row['value'] ),
		);
		$generated_by_name[ $name_key ] = array( $row['value'] );
		$existing_by_name[ $name_key ]  = array( 'visible' => true, 'values' => array( $row['value'] ) );
	}

	$product->set_attributes( $attributes );
	$product->update_meta_data( '_jluxe_product_specs_export_snapshot', $new_snapshot );
	return true;
}

/** Save grouped data and its portable WooCommerce attributes before product save. */
function jluxe_save_product_specifications_meta_object( WC_Product $product ): void {
	$product_id   = (int) $product->get_id();
	$restore_boom = false;
	$groups       = jluxe_product_specifications_posted_groups( $product_id, $restore_boom );

	if ( null === $groups ) {
		if ( isset( $_POST['jluxe_product_specs_present'] ) ) {
			return; // فرم حاضر است اما nonce/capability/ساختار معتبر نیست.
		}
		$groups = jluxe_get_product_advanced_attribute_groups( $product_id );
	} elseif ( $restore_boom ) {
		$product->delete_meta_data( '_jluxe_product_advanced_attributes' );
		$product->delete_meta_data( '_jluxe_product_advanced_attributes_override' );
		$groups = jluxe_get_product_advanced_attribute_groups( $product_id, true );
	} else {
		$product->update_meta_data( '_jluxe_product_advanced_attributes', $groups );
		$product->update_meta_data( '_jluxe_product_advanced_attributes_override', '1' );
	}

	jluxe_sync_product_specifications_to_wc_attributes( $product, is_array( $groups ) ? $groups : array() );
	$GLOBALS['jluxe_product_specifications_object_sync'][ $product_id ] = true;
}
add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_specifications_meta_object' );

/** Save grouped data through the legacy product-editor flow when present. */
function jluxe_save_product_specifications_meta( int $post_id ): void {
	$restore_boom = false;
	$groups       = jluxe_product_specifications_posted_groups( $post_id, $restore_boom );
	if ( null === $groups ) {
		return;
	}
	if ( $restore_boom ) {
		delete_post_meta( $post_id, '_jluxe_product_advanced_attributes' );
		delete_post_meta( $post_id, '_jluxe_product_advanced_attributes_override' );
		$groups = jluxe_get_product_advanced_attribute_groups( $post_id, true );
	} else {
		update_post_meta( $post_id, '_jluxe_product_advanced_attributes', $groups );
		update_post_meta( $post_id, '_jluxe_product_advanced_attributes_override', '1' );
	}

	if ( ! empty( $GLOBALS['jluxe_product_specifications_object_sync'][ $post_id ] ) ) {
		unset( $GLOBALS['jluxe_product_specifications_object_sync'][ $post_id ] );
		return;
	}
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : false;
	if ( $product instanceof WC_Product && jluxe_sync_product_specifications_to_wc_attributes( $product, is_array( $groups ) ? $groups : array() ) && method_exists( $product, 'save' ) ) {
		$product->save();
	}
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_specifications_meta' );

/** Add the safe grouped-specification migration to the product list bulk menu. */
function jluxe_add_product_specifications_bulk_action( array $actions ): array {
	$actions['jluxe_sync_product_specs_for_export'] = 'همگام‌سازی مشخصات برای انتقال';
	return $actions;
}
add_filter( 'bulk_actions-edit-product', 'jluxe_add_product_specifications_bulk_action' );

/** Sync selected legacy/override groups into standard WooCommerce attributes. */
function jluxe_handle_product_specifications_bulk_action( string $redirect_to, string $action, array $product_ids ): string {
	if ( 'jluxe_sync_product_specs_for_export' !== $action || ! current_user_can( 'edit_products' ) ) {
		return $redirect_to;
	}
	check_admin_referer( 'bulk-posts' );
	$processed = 0;
	$ids       = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) ), 0, 100 );
	foreach ( $ids as $product_id ) {
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			continue;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || ! jluxe_sync_product_specifications_to_wc_attributes( $product, jluxe_get_product_advanced_attribute_groups( $product_id ) ) ) {
			continue;
		}
		if ( method_exists( $product, 'save' ) ) {
			$product->save();
		}
		++$processed;
	}
	return add_query_arg( 'jluxe_product_specs_transfer_synced', $processed, $redirect_to );
}
add_filter( 'handle_bulk_actions-edit-product', 'jluxe_handle_product_specifications_bulk_action', 10, 3 );

/** Show a clear result after the product-list bulk migration. */
function jluxe_product_specifications_bulk_action_notice(): void {
	if ( ! isset( $_GET['jluxe_product_specs_transfer_synced'] ) || ! is_scalar( $_GET['jluxe_product_specs_transfer_synced'] ) || ! current_user_can( 'edit_products' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && isset( $screen->id ) && 'edit-product' !== $screen->id ) {
		return;
	}
	$count = absint( wp_unslash( $_GET['jluxe_product_specs_transfer_synced'] ) );
	$message = $count > 0
		? sprintf( 'مشخصات %s محصول به ویژگی‌های استاندارد ووکامرس همگام شد.', function_exists( 'jluxe_fa_digits' ) ? jluxe_fa_digits( (string) $count ) : (string) $count )
		: 'محصولی برای همگام‌سازی پیدا نشد یا دسترسی لازم وجود نداشت.';
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'jluxe_product_specifications_bulk_action_notice' );
