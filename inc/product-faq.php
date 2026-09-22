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
 * ذخیره‌سازی — به هوکِ رسمیِ woocommerce_process_product_meta وصله (نه
 * save_post مستقیم)، چون این هوک فقط وقتی صدا زده می‌شه که خودِ ووکامرس
 * nonce صفحه‌ی ویرایشِ محصول رو از قبل تأیید کرده باشه — نیازی به
 * nonce/چکِ دسترسیِ جداگانه نیست.
 */
function jluxe_save_product_faq_meta( int $post_id ): void {
	$posted = isset( $_POST['jluxe_product_faq'] ) && is_array( $_POST['jluxe_product_faq'] ) ? wp_unslash( $_POST['jluxe_product_faq'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce توسطِ خودِ ووکامرس قبل از این هوک تأیید شده؛ مقادیر پایین‌تر یک‌به‌یک sanitize می‌شن.

	$clean = array();
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

	update_post_meta( $post_id, '_jluxe_product_faq', $clean );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_faq_meta' );

function jluxe_get_product_faq_items( int $product_id ): array {
	$items = get_post_meta( $product_id, '_jluxe_product_faq', true );
	return is_array( $items ) ? $items : array();
}

/**
 * FAQPage schema (JSON-LD) — طبقِ ممیزیِ سئو/GEO: این محتوا (سوال/پاسخِ
 * واقعیِ هر محصول) از قبل تویِ دیتابیس بود ولی هیچ‌جا به‌صورتِ ساختاریافته
 * (structured data) اعلام نمی‌شد — یعنی نه ریچ‌اسنیپتِ گوگل ازش استفاده
 * می‌کرد، نه موتورهای جستجویِ AI-محور (که برایِ فهمِ محتوا بیشتر از
 * schema.org استفاده می‌کنن تا صرفاً متنِ HTML). چون همون داده‌ی واقعی که
 * قبلاً در jluxe_render_product_faq_section نمایش داده می‌شه این‌جا هم
 * استفاده می‌شه، ریسکِ محتوایِ ساختگی/ناهماهنگ صفر است — اگه محصول سوالی
 * نداشته باشه، هیچ schema‌ای چاپ نمی‌شه (نه یک FAQPage خالی/جعلی).
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
		wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode خودش escape امنِ JSON انجام می‌ده؛ متنِ داخلش هم قبلاً wp_strip_all_tags شده.
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
	<section id="faq" class="scroll-mt-16 py-6">
		<h2 class="mb-4 flex items-center gap-2 text-base font-bold text-foreground sm:text-lg">
			<span class="h-4 w-[3px] rounded-full bg-primary" aria-hidden="true"></span>
			سوالات متداول
		</h2>
		<div class="overflow-hidden rounded-2xl border border-border bg-surface">
			<?php foreach ( $items as $i => $item ) : ?>
				<div class="jluxe-faq-accordion-item<?php echo 0 === $i ? '' : ' border-t border-border'; ?>">
					<button type="button" class="jluxe-faq-trigger flex w-full items-center justify-between gap-3 p-4 text-start transition-colors hover:bg-muted" aria-expanded="false">
						<span class="text-[13.5px] font-medium text-foreground"><?php echo esc_html( $item['question'] ); ?></span>
						<svg class="jluxe-faq-chevron size-4 shrink-0 text-text-muted transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</button>
					<div class="jluxe-faq-panel grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-out">
						<div class="overflow-hidden">
							<p class="px-4 pb-4 text-[13px] leading-6 text-text-secondary"><?php echo nl2br( esc_html( $item['answer'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html قبلاً روی مقدار اعمال شده، nl2br فقط <br> اضافه می‌کنه. ?></p>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
