<?php
/**
 * سواچ رنگ/تصویر برای ترم‌های ویژگی‌های ووکامرس (pa_*) — توی صفحه‌ی
 * ویرایشِ محصولاتِ ووکامرس (Products → Attributes → [ویژگی] → ترم‌ها)،
 * هر ترم (مثلاً «آبی» زیرِ ویژگیِ «رنگ») می‌تونه نوعِ سواچش رو انتخاب
 * کنه: بدون سواچ (متن ساده)، رنگ (رنگ‌یاب واقعی)، یا تصویر (آپلودِ
 * رسانه). این جایگزینِ حدسِ نامِ رنگ (jluxe_persian_color_to_hex در
 * inc/woocommerce.php) می‌شه — طبقِ درخواستِ کاربر، ادمین باید بتونه
 * صریحاً رنگ/تصویر رو انتخاب کنه، نه اینکه سایت از روی متنِ نامِ رنگ
 * حدس بزنه. حدسِ قبلی به‌عنوانِ fallback برای ترم‌هایی که هنوز سواچ
 * دستی ندارن باقی می‌مونه (backward-compatible، چیزی خراب نمی‌شه).
 */

defined( 'ABSPATH' ) || exit;

/**
 * روی هر تاکسونومیِ ویژگیِ واقعاً ثبت‌شده (pa_*) هوک می‌زنه — چون این
 * تاکسونومی‌ها اسمشون از قبل معلوم نیست (هر فروشگاه ویژگیِ خودش رو
 * می‌سازه)، باید دینامیک لوپ بزنیم، نه یک اسمِ ثابت رو هاردکد کنیم.
 * روی 'init' با priority بالا (بعد از این‌که خودِ ووکامرس تاکسونومی‌ها
 * رو register کرده، که init با priority 0 انجام می‌ده).
 */
function jluxe_register_swatch_term_hooks(): void {
	if ( ! function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
		return;
	}

	foreach ( wc_get_attribute_taxonomy_names() as $jluxe_taxonomy ) {
		add_action( "{$jluxe_taxonomy}_add_form_fields", 'jluxe_render_swatch_add_fields' );
		add_action( "{$jluxe_taxonomy}_edit_form_fields", 'jluxe_render_swatch_edit_fields', 10, 2 );
		add_action( "created_{$jluxe_taxonomy}", 'jluxe_save_swatch_term_meta' );
		add_action( "edited_{$jluxe_taxonomy}", 'jluxe_save_swatch_term_meta' );
	}
}
add_action( 'init', 'jluxe_register_swatch_term_hooks', 20 );

/**
 * مقادیرِ فعلیِ سواچِ یک ترم — پیش‌فرض برای ترمِ تازه (فرمِ افزودن) خالیه.
 *
 * @return array{type: string, color: string, image_id: int}
 */
function jluxe_get_swatch_term_meta( int $term_id ): array {
	return array(
		'type'     => (string) get_term_meta( $term_id, '_jluxe_swatch_type', true ) ?: 'none',
		'color'    => (string) get_term_meta( $term_id, '_jluxe_swatch_color', true ),
		'image_id' => (int) get_term_meta( $term_id, '_jluxe_swatch_image_id', true ),
	);
}

/**
 * فیلدهای سواچ برای فرمِ «افزودنِ ترمِ جدید» (بدون جدول — این صفحه از
 * <div class="form-field"> استفاده می‌کنه، نه <tr>).
 */
function jluxe_render_swatch_add_fields(): void {
	wp_nonce_field( 'jluxe_save_swatch', 'jluxe_swatch_nonce' );
	?>
	<div class="form-field jluxe-swatch-field">
		<label>تنظیمات سواچ</label>
		<?php jluxe_render_swatch_type_radios( 'none', '' ); ?>
		<?php jluxe_render_swatch_color_field( '' ); ?>
		<?php jluxe_render_swatch_image_field( 0 ); ?>
		<p class="description">نوعِ نمایشِ این گزینه در سواچ‌های انتخابِ تنوعِ صفحه‌ی محصول.</p>
	</div>
	<?php
}

/**
 * فیلدهای سواچ برای فرمِ «ویرایشِ ترمِ موجود» (جدولی — <tr>/<th>/<td>).
 *
 * @param WP_Term $term Current term being edited.
 */
function jluxe_render_swatch_edit_fields( $term ): void {
	$meta = jluxe_get_swatch_term_meta( $term->term_id );
	wp_nonce_field( 'jluxe_save_swatch', 'jluxe_swatch_nonce' );
	?>
	<tr class="form-field jluxe-swatch-field">
		<th scope="row"><label>تنظیمات سواچ</label></th>
		<td>
			<?php jluxe_render_swatch_type_radios( $meta['type'], '' ); ?>
			<?php jluxe_render_swatch_color_field( $meta['color'] ); ?>
			<?php jluxe_render_swatch_image_field( $meta['image_id'] ); ?>
			<p class="description">نوعِ نمایشِ این گزینه در سواچ‌های انتخابِ تنوعِ صفحه‌ی محصول.</p>
		</td>
	</tr>
	<?php
}

function jluxe_render_swatch_type_radios( string $current, string $unused ): void {
	$options = array(
		'none'  => 'بدون سواچ (متن ساده)',
		'color' => 'رنگ',
		'image' => 'تصویر',
	);
	?>
	<div class="jluxe-swatch-type-selector">
		<?php foreach ( $options as $value => $label ) : ?>
			<label style="display:inline-block;margin-inline-end:14px;">
				<input type="radio" name="jluxe_swatch_type" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current, $value ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
	</div>
	<?php
}

function jluxe_render_swatch_color_field( string $color ): void {
	?>
	<div class="jluxe-swatch-color-field" style="margin-top:8px;">
		<input type="text" name="jluxe_swatch_color" class="jluxe-swatch-color-picker" value="<?php echo esc_attr( $color ); ?>" data-default-color="#cccccc" />
	</div>
	<?php
}

function jluxe_render_swatch_image_field( int $image_id ): void {
	$url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	?>
	<div class="jluxe-swatch-image-field" style="margin-top:8px;">
		<input type="hidden" name="jluxe_swatch_image_id" class="jluxe-swatch-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>" />
		<img class="jluxe-swatch-image-preview" src="<?php echo esc_url( $url ); ?>" style="max-width:60px;max-height:60px;display:<?php echo $url ? 'block' : 'none'; ?>;margin-bottom:8px;" alt="" />
		<button type="button" class="button jluxe-swatch-image-select">انتخاب تصویر</button>
		<button type="button" class="button jluxe-swatch-image-remove" style="<?php echo $image_id ? '' : 'display:none;'; ?>">حذف تصویر</button>
	</div>
	<?php
}

/**
 * ذخیره‌ی متای سواچ روی ساخت/ویرایشِ ترم. هر دو هوک (created_/edited_)
 * فقط $term_id رو با اطمینان می‌ده (آرگومانِ دومشون tt_id هست، نه چیزِ
 * دیگه)، پس نیازی به $taxonomy پارامتر نداریم.
 */
function jluxe_save_swatch_term_meta( int $term_id ): void {
	if ( ! isset( $_POST['jluxe_swatch_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['jluxe_swatch_nonce'] ), 'jluxe_save_swatch' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}

	$type = isset( $_POST['jluxe_swatch_type'] ) ? sanitize_key( wp_unslash( $_POST['jluxe_swatch_type'] ) ) : 'none';
	if ( ! in_array( $type, array( 'none', 'color', 'image' ), true ) ) {
		$type = 'none';
	}
	update_term_meta( $term_id, '_jluxe_swatch_type', $type );

	$color = isset( $_POST['jluxe_swatch_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['jluxe_swatch_color'] ) ) : '';
	update_term_meta( $term_id, '_jluxe_swatch_color', $color ?: '' );

	$image_id = isset( $_POST['jluxe_swatch_image_id'] ) ? absint( $_POST['jluxe_swatch_image_id'] ) : 0;
	update_term_meta( $term_id, '_jluxe_swatch_image_id', $image_id );
}

/**
 * سواچِ نهایی برای یک گزینه‌ی تنوع — اول متای واقعیِ ادمین‌انتخاب‌شده،
 * بعد (فقط برای ویژگی‌های رنگ که هنوز دستی تنظیم نشدن) حدسِ نامِ رنگِ
 * قدیمی (jluxe_persian_color_to_hex) به‌عنوانِ fallback، که چیزی برای
 * ترم‌های تنظیم‌نشده‌ی موجود خراب نشه.
 *
 * @return array{type: string, value: string}|null نال یعنی سواچی نیست (پیلِ متنی نشون داده بشه).
 */
function jluxe_resolve_variation_swatch( string $attr_name, string $option_slug, string $option_label, bool $is_color_attr ): ?array {
	if ( 0 === strpos( $attr_name, 'pa_' ) ) {
		$term = get_term_by( 'slug', $option_slug, $attr_name );
		if ( $term ) {
			// مقدارِ خامِ متا (نه jluxe_get_swatch_term_meta که برای رندرِ
			// فرمِ ادمین 'none' رو پیش‌فرض می‌ذاره) — چون این‌جا باید فرقِ
			// «ادمین صریحاً گزینه‌ی بدون‌سواچ رو زده» رو از «هنوز اصلاً به
			// این ترم سر نزده» تشخیص بدیم؛ وگرنه هر ترمِ دست‌نخورده
			// (یعنی همه‌ی ترم‌های موجودِ فعلی، قبل از این‌که ادمین دستی
			// تنظیمشون کنه) بلافاصله حدسِ نامِ رنگِ قدیمی رو از دست می‌داد
			// (باگِ واقعیِ کشف‌شده حینِ تست).
			$raw_type = (string) get_term_meta( $term->term_id, '_jluxe_swatch_type', true );

			if ( 'color' === $raw_type ) {
				$color = (string) get_term_meta( $term->term_id, '_jluxe_swatch_color', true );
				if ( $color ) {
					return array(
						'type'  => 'color',
						'value' => $color,
					);
				}
			}
			if ( 'image' === $raw_type ) {
				$image_id = (int) get_term_meta( $term->term_id, '_jluxe_swatch_image_id', true );
				$url      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
				if ( $url ) {
					return array(
						'type'  => 'image',
						'value' => $url,
					);
				}
			}
			if ( 'none' === $raw_type ) {
				return null;
			}
		}
	}

	if ( $is_color_attr ) {
		$hex = jluxe_persian_color_to_hex( $option_label );
		if ( $hex ) {
			return array(
				'type'  => 'color',
				'value' => $hex,
			);
		}
	}

	return null;
}

/**
 * اسکریپت/استایلِ ادمین برای رنگ‌یاب و آپلودگرِ رسانه — فقط توی صفحه‌ی
 * افزودن/ویرایشِ ترمِ یک ویژگیِ ووکامرس (pa_*) لود می‌شه، نه همه‌جای ادمین.
 */
function jluxe_enqueue_swatch_admin_assets( string $hook ): void {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_media();
	$js_path = JLUXE_THEME_DIR . '/assets/js/attribute-swatches-admin.js';
	wp_enqueue_script(
		'jluxe-swatch-admin',
		JLUXE_THEME_URI . '/assets/js/attribute-swatches-admin.js',
		array( 'jquery', 'wp-color-picker' ),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_enqueue_swatch_admin_assets' );
