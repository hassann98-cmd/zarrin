<?php
/**
 * سوالات متداولِ هر محصول — یک postbox جدا تو صفحه‌ی ویرایشِ محصول (نه
 * توی تب‌های تنگِ Product data، چون ردیف‌های پویای سوال/پاسخ اونجا جا
 * نمی‌شن)، مطابقِ نمونه‌ای که کاربر فرستاد. خروجی زیرِ «معرفی محصول» تو
 * صفحه‌ی تکیِ محصول، به‌صورتِ آکاردئون.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_add_product_faq_meta_box(): void {
	add_meta_box(
		'jluxe_product_faq',
		'سوالات متداول محصول',
		'jluxe_render_product_faq_meta_box',
		'product',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'jluxe_add_product_faq_meta_box' );

function jluxe_render_product_faq_meta_box( WP_Post $post ): void {
	$items = jluxe_get_product_faq_items( $post->ID );
	?>
	<style>
		.jluxe-faq-item { border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 10px; background: #fff; }
		.jluxe-faq-item-header { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f6f7f7; border-bottom: 1px solid #dcdcde; border-radius: 4px 4px 0 0; }
		.jluxe-faq-drag-handle { cursor: move; color: #8c8f94; }
		.jluxe-faq-item-title { flex: 1; }
		.jluxe-faq-remove-item { line-height: 1; color: #b32d2e; }
		.jluxe-faq-remove-item:hover { color: #b32d2e; border-color: #b32d2e; }
		.jluxe-faq-item-content { padding: 12px; }
		.jluxe-faq-item-content p { margin: 0 0 10px; }
		.jluxe-faq-item-content label { display: block; margin-bottom: 4px; font-weight: 600; }
		#jluxe-faq-item-template { display: none; }
	</style>
	<p class="description">سوالات متداولِ مربوط به این محصول رو اضافه کن — زیرِ «معرفی محصول» تو صفحه‌ی محصول، به‌صورتِ آکاردئون (با کلیک/لمس باز می‌شه) نشون داده می‌شه.</p>
	<input type="hidden" name="jluxe_product_faq_present" value="1" />
	<div id="jluxe-faq-items" class="jluxe-faq-items">
		<?php foreach ( $items as $index => $item ) : ?>
			<?php jluxe_render_product_faq_item_fields( $index, $item ); ?>
		<?php endforeach; ?>
	</div>
	<template id="jluxe-faq-item-template">
		<?php jluxe_render_product_faq_item_fields( '__INDEX__', array() ); ?>
	</template>
	<p>
		<button type="button" class="button button-primary" id="jluxe-add-faq-item">
			<span class="dashicons dashicons-plus-alt" style="vertical-align:middle;"></span>
			افزودن سوال جدید
		</button>
	</p>
	<?php
}

/**
 * یک ردیفِ سوال/پاسخ — هم برای ردیف‌های موجود (SSR loop) و هم برای
 * <template> که JS موقعِ «افزودن» کپی می‌کنه. چون تابعِ ذخیره‌سازی پایین‌تر
 * فقط روی VALUE هاش foreach می‌کنه (نه روی KEY)، دقیقِ‌بودنِ $index مهم
 * نیست — فقط باید هر ردیف یک اسمِ منحصربه‌فرد داشته باشه که با بقیه قاطی
 * نشه؛ برای همین سمتِ JS به‌جای renumber کردنِ دقیق، فقط یک شمارنده‌ی
 * یکتا (Date.now()) برای ردیف‌های تازه استفاده می‌شه.
 */
function jluxe_render_product_faq_item_fields( $index, array $item ): void {
	$label = is_numeric( $index ) ? 'سوال ' . jluxe_fa_digits( (string) ( (int) $index + 1 ) ) : 'سوال';
	?>
	<div class="jluxe-faq-item">
		<div class="jluxe-faq-item-header">
			<span class="jluxe-faq-drag-handle dashicons dashicons-menu"></span>
			<strong class="jluxe-faq-item-title"><?php echo esc_html( $label ); ?></strong>
			<button type="button" class="button jluxe-faq-remove-item" aria-label="حذف این سوال">×</button>
		</div>
		<div class="jluxe-faq-item-content">
			<p>
				<label>سوال</label>
				<input type="text" name="jluxe_product_faq[<?php echo esc_attr( (string) $index ); ?>][question]" value="<?php echo esc_attr( $item['question'] ?? '' ); ?>" class="widefat" placeholder="مثلاً: آیا این محصول قابل شست‌وشو در ماشین لباسشویی است؟" />
			</p>
			<p>
				<label>پاسخ</label>
				<textarea name="jluxe_product_faq[<?php echo esc_attr( (string) $index ); ?>][answer]" class="widefat" rows="3" placeholder="پاسخ را وارد کنید..."><?php echo esc_textarea( $item['answer'] ?? '' ); ?></textarea>
			</p>
		</div>
	</div>
	<?php
}

/**
 * ذخیره‌سازی — هم به هوکِ رسمیِ product-object (ویرایشگرِ جدیدِ محصول) و
 * هم به هوکِ legacyِ woocommerce_process_product_meta وصله؛ هر دو در مسیرِ
 * ذخیرهٔ تأییدشدهٔ خودِ ووکامرس اجرا می‌شن. نشانگرِ hidden هم از پاک‌شدنِ
 * ناخواستهٔ FAQ هنگامِ ذخیرهٔ REST/ویرایشگرهای بدونِ این متاباکس جلوگیری می‌کنه.
 */
function jluxe_product_faq_posted_items(): ?array {
	// فقط وقتی متاباکسِ ما در فرم حضور داشته مقدار را تغییر بده؛ ذخیرهٔ محصول
	// از REST/ویرایشگرهای دیگر نباید FAQ موجود را تصادفاً خالی کند.
	if ( ! isset( $_POST['jluxe_product_faq_present'] ) ) {
		return null;
	}

	$posted = isset( $_POST['jluxe_product_faq'] ) && is_array( $_POST['jluxe_product_faq'] ) ? wp_unslash( $_POST['jluxe_product_faq'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce توسطِ هوکِ ووکامرس تأیید شده؛ مقادیر پایین‌تر یک‌به‌یک sanitize می‌شن.
	$clean  = array();
	foreach ( $posted as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '';
		$answer   = isset( $item['answer'] ) ? sanitize_textarea_field( $item['answer'] ) : '';
		if ( '' === $question || '' === $answer ) {
			continue; // ردیفِ ناقص (فقط سوال یا فقط پاسخ)، نگه‌داشتنش فایده نداره.
		}
		$clean[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
	}
	return $clean;
}

/** Save on both the current product-object flow and the classic product editor. */
function jluxe_save_product_faq_meta_object( WC_Product $product ): void {
	$clean = jluxe_product_faq_posted_items();
	if ( null !== $clean ) {
		$product->update_meta_data( '_jluxe_product_faq', $clean );
	}
}
add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_faq_meta_object' );

function jluxe_save_product_faq_meta( int $post_id ): void {
	$clean = jluxe_product_faq_posted_items();
	if ( null !== $clean ) {
		update_post_meta( $post_id, '_jluxe_product_faq', $clean );
	}
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_faq_meta' );

function jluxe_get_product_faq_items( int $product_id ): array {
	$items = get_post_meta( $product_id, '_jluxe_product_faq', true );
	return is_array( $items ) ? $items : array();
}

/**
 * FAQPage JSON-LD mirrors the same editor-managed questions and answers that
 * are visibly rendered on the product page. It gives parsers explicit Q/A
 * boundaries without inventing facts; search engines decide whether to use it
 * and it is not a promise of an FAQ rich result or ranking change.
 */
function jluxe_output_product_faq_schema(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	// نه global $product — این تابع رویِ wp_head اجراش می‌کنه، قبل از اینکه
	// حلقه‌ی اصلی (که global $product رو ست می‌کنه) اصلاً اجرا بشه؛ دقیقاً
	// همون الگویِ امنی که jluxe_output_seo_meta() چند تابع بالاتر استفاده
	// می‌کنه.
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_queried_object_id() ) : null;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$items = jluxe_get_product_faq_items( $product->get_id() );
	if ( empty( $items ) ) {
		return;
	}

	$entities = array();
	foreach ( $items as $item ) {
		if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
			continue;
		}
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['answer'] ),
			),
		);
	}
	if ( empty( $entities ) ) {
		return;
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		jluxe_jsonld_encode( $schema ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD encoder emits safe, hex-escaped JSON for script context.
	);
}
add_action( 'wp_head', 'jluxe_output_product_faq_schema', 25 );

/**
 * اسکریپتِ ادمینِ این متاباکس فقط تویِ صفحه‌ی ویرایش/افزودنِ محصول لود
 * می‌شه — نه همه‌جای ادمین.
 */
function jluxe_enqueue_product_faq_admin_assets( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	global $post;
	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}
	$path = JLUXE_THEME_DIR . '/assets/js/product-faq-admin.js';
	wp_enqueue_script(
		'jluxe-product-faq-admin',
		JLUXE_THEME_URI . '/assets/js/product-faq-admin.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		file_exists( $path ) ? (string) filemtime( $path ) : null,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'jluxe_enqueue_product_faq_admin_assets' );

/**
 * رندرِ فرانت‌اند — آکاردئونِ سوالات متداول، زیرِ «معرفی محصول» تو
 * content-single-product.php صدا زده می‌شه. اگه محصول هیچ سوالی نداشته
 * باشه، چیزی چاپ نمی‌شه (و content-single-product.php خودش تبِ ناوبریِ
 * «سوالات متداول» رو هم مخفی می‌کنه — دقیقاً مثلِ الگوی موجودِ تبِ
 * «مشخصات» که فقط وقتی attribute واقعی هست نشون داده می‌شه).
 */
function jluxe_render_product_faq_section( WC_Product $product ): void {
	$items = jluxe_get_product_faq_items( $product->get_id() );
	if ( empty( $items ) ) {
		return;
	}
	?>
	<section id="faq" class="jluxe-product-faq jluxe-product-section-anchor scroll-mt-16 py-6" aria-labelledby="jluxe-product-faq-title" dir="rtl">
		<h2 id="jluxe-product-faq-title" class="jluxe-product-faq-heading">
			<span class="jluxe-product-faq-heading__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.6 1.4c-.8 1-2.2 1.2-2.2 2.6"/><path d="M12 16.5h.01"/></svg>
			</span>
			<span>سوالات متداول</span>
		</h2>
		<div class="jluxe-product-faq-list overflow-hidden rounded-2xl border border-border bg-surface">
			<?php foreach ( $items as $i => $item ) : ?>
				<?php $jluxe_faq_panel_id = 'jluxe-product-faq-' . $product->get_id() . '-' . (int) $i; ?>
				<div class="jluxe-faq-accordion-item<?php echo 0 === $i ? '' : ' border-t border-border'; ?>">
					<button type="button" class="jluxe-faq-trigger flex w-full items-center justify-between gap-3 p-4 text-start transition-colors hover:bg-muted" aria-expanded="false" aria-controls="<?php echo esc_attr( $jluxe_faq_panel_id ); ?>">
						<span class="jluxe-faq-question text-[13.5px] font-medium text-foreground"><?php echo esc_html( $item['question'] ); ?></span>
						<svg class="jluxe-faq-chevron size-4 shrink-0 text-text-muted transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</button>
					<div id="<?php echo esc_attr( $jluxe_faq_panel_id ); ?>" class="jluxe-faq-panel grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-out" aria-hidden="true">
						<div class="overflow-hidden">
							<p class="jluxe-faq-answer px-4 pb-4 text-[13px] leading-6 text-text-secondary"><?php echo nl2br( esc_html( $item['answer'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html قبلاً روی مقدار اعمال شده، nl2br فقط <br> اضافه می‌کنه. ?></p>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
