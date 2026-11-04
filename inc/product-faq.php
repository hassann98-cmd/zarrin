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
	// ابتدا FAQهایِ قابل‌مشاهده (از جمله دادهٔ قدیمی Boom) را نشان بده؛ پس از
	// ذخیره، snapshot پوسته جایگزین می‌شود و کلیدِ اصلی Boom دست‌نخورده می‌ماند.
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
		.jluxe-faq-bulk-import { margin: 14px 0; padding: 12px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; }
		.jluxe-faq-bulk-import__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
		#jluxe-faq-bulk-status:empty { display: none; }
	</style>
	<p class="description">سوال‌وجواب‌هایی که اینجا می‌بینی همان موارد قابل‌نمایش هستند؛ دادهٔ قدیمی Boom برای شروع بارگذاری شده و تغییرات فقط در override پوسته ذخیره می‌شوند. کلید قدیمی Boom حذف یا بازنویسی نمی‌شود. FAQ نهایی زیرِ «معرفی محصول» نمایش داده می‌شود و برای همان محتوا FAQPage JSON-LD ساخته می‌شود.</p>
	<div class="jluxe-faq-bulk-import">
		<p><label for="jluxe-faq-bulk-paste"><strong>ورود یک‌جای سوال‌ها و جواب‌ها</strong></label></p>
		<p class="description">کل متن را یک‌بار اینجا جای‌گذاری کنید؛ متن‌هایی مثل «**۱. سوال؟** جواب» و قالب پرسش‌وپاسخِ پررنگِ Word پشتیبانی می‌شوند. تیترهایی مثل «سوالات متداول خریداران» به‌عنوان سوال ثبت نمی‌شوند. بعد از تشخیص، برای جایگزینی یا افزودن به فهرست فعلی یکی از دکمه‌ها را بزنید.</p>
		<textarea id="jluxe-faq-bulk-paste" class="widefat" rows="6" placeholder="سوالات متداول خریداران&#10;&#10;**۱. آیا محصول قابل شست‌وشو است؟** بله، با دستمال مرطوب تمیز می‌شود."></textarea>
		<div class="jluxe-faq-bulk-import__actions">
			<button type="button" class="button button-primary" id="jluxe-faq-import-replace" disabled>جایگزینی سوال‌های فعلی</button>
			<button type="button" class="button" id="jluxe-faq-import-append" disabled>افزودن به انتهای فهرست</button>
		</div>
		<p id="jluxe-faq-bulk-status" class="description" role="status" aria-live="polite"></p>
	</div>
	<?php wp_nonce_field( 'jluxe_product_faq_save', 'jluxe_product_faq_nonce' ); ?>
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
	<p>
		<label><input type="checkbox" name="jluxe_product_faq_restore_boom" value="1" /> بازگشت به داده‌های قبلی (حذف override پوسته و نمایش دوبارهٔ FAQهای Boom)</label>
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
function jluxe_product_faq_posted_items( int $post_id = 0, ?bool &$restore_boom = null ): ?array {
	$restore_boom = false;
	// فقط فرمِ همین متاباکس، با nonce معتبر و دسترسی ویرایش، اجازهٔ تغییر دارد.
	if ( ! isset( $_POST['jluxe_product_faq_present'] ) || ! is_scalar( $_POST['jluxe_product_faq_present'] ) || '1' !== (string) wp_unslash( $_POST['jluxe_product_faq_present'] ) ) {
		return null;
	}
	if ( $post_id < 1 && isset( $_POST['post_ID'] ) && is_scalar( $_POST['post_ID'] ) ) {
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) );
	}
	if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
		return null;
	}
	$nonce = isset( $_POST['jluxe_product_faq_nonce'] ) && is_scalar( $_POST['jluxe_product_faq_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jluxe_product_faq_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'jluxe_product_faq_save' ) ) {
		return null;
	}
	if ( isset( $_POST['jluxe_product_faq_restore_boom'] ) && is_scalar( $_POST['jluxe_product_faq_restore_boom'] ) && '1' === (string) wp_unslash( $_POST['jluxe_product_faq_restore_boom'] ) ) {
		$restore_boom = true;
		return array();
	}

	$posted = isset( $_POST['jluxe_product_faq'] ) && is_array( $_POST['jluxe_product_faq'] ) ? wp_unslash( $_POST['jluxe_product_faq'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce و capability همین‌جا بررسی شده‌اند؛ هر فیلد پایین‌تر sanitize می‌شود.
	$clean  = array();
	foreach ( array_slice( $posted, 0, 100 ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = isset( $item['question'] ) && is_scalar( $item['question'] ) ? sanitize_text_field( (string) $item['question'] ) : '';
		$answer   = isset( $item['answer'] ) && is_scalar( $item['answer'] ) ? sanitize_textarea_field( (string) $item['answer'] ) : '';
		if ( '' === $question || '' === $answer ) {
			continue; // ردیفِ ناقص (فقط سوال یا فقط پاسخ)، نگه‌داشتنش فایده ندارد.
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
	$restore_boom = false;
	$clean        = jluxe_product_faq_posted_items( (int) $product->get_id(), $restore_boom );
	if ( null === $clean ) {
		return;
	}
	if ( $restore_boom ) {
		$product->delete_meta_data( '_jluxe_product_faq_override_items' );
		$product->delete_meta_data( '_jluxe_product_faq_override' );
		return;
	}
	$product->update_meta_data( '_jluxe_product_faq_override_items', $clean );
	$product->update_meta_data( '_jluxe_product_faq_override', '1' );
}
add_action( 'woocommerce_admin_process_product_object', 'jluxe_save_product_faq_meta_object' );

function jluxe_save_product_faq_meta( int $post_id ): void {
	$restore_boom = false;
	$clean        = jluxe_product_faq_posted_items( $post_id, $restore_boom );
	if ( null === $clean ) {
		return;
	}
	if ( $restore_boom ) {
		delete_post_meta( $post_id, '_jluxe_product_faq_override_items' );
		delete_post_meta( $post_id, '_jluxe_product_faq_override' );
		return;
	}
	update_post_meta( $post_id, '_jluxe_product_faq_override_items', $clean );
	update_post_meta( $post_id, '_jluxe_product_faq_override', '1' );
}
add_action( 'woocommerce_process_product_meta', 'jluxe_save_product_faq_meta' );

/**
 * Decode FAQ metadata returned as a WordPress array, plain JSON, or Base64 JSON.
 * WordPress already unserializes arrays saved through post meta; do not call
 * unserialize() on arbitrary strings here.
 *
 * @param mixed $stored Value returned by get_post_meta().
 * @return array<int|string, mixed>
 */
function jluxe_decode_product_faq_meta_value( $stored ): array {
	if ( is_array( $stored ) ) {
		return $stored;
	}
	if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
		return array();
	}

	$candidates       = array( trim( $stored ) );
	$base64_candidate = preg_replace( '/\s+/', '', $stored );
	if ( is_string( $base64_candidate ) && '' !== $base64_candidate ) {
		$base64_json = base64_decode( $base64_candidate, true );
		if ( false !== $base64_json ) {
			$candidates[] = $base64_json;
		}
	}

	foreach ( $candidates as $candidate ) {
		$decoded = json_decode( $candidate, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}
	}

	return array();
}

/**
 * Keep only complete, text-only question/answer rows before display.
 *
 * @param mixed $stored Value returned by get_post_meta().
 * @return array<int, array{question: string, answer: string}>
 */
function jluxe_sanitize_product_faq_items( $stored ): array {
	$data  = jluxe_decode_product_faq_meta_value( $stored );
	$items = array();
	foreach ( array_slice( $data, 0, 100 ) as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['question'], $item['answer'] ) || ! is_scalar( $item['question'] ) || ! is_scalar( $item['answer'] ) ) {
			continue;
		}

		$question = sanitize_text_field( (string) $item['question'] );
		$answer   = sanitize_textarea_field( (string) $item['answer'] );
		if ( '' === $question || '' === $answer ) {
			continue;
		}
		$items[] = array(
			'question' => $question,
			'answer'   => $answer,
		);
	}

	return $items;
}

/** Produce a stable comparison key without changing the visible question. */
function jluxe_product_faq_question_key( string $question ): string {
	$normalized = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $question ) );
	$normalized = trim( is_string( $normalized ) ? $normalized : $question );
	return strtolower( $normalized );
}

/**
 * Return the effective FAQ set without writing either source key. Before an
 * explicit override exists, Boom rows and existing theme rows are merged for
 * display; the override marker makes even an empty theme list authoritative.
 * Boom rows stay first and win exact-question conflicts until then.
 *
 * @return array<int, array{question: string, answer: string}>
 */
function jluxe_get_product_faq_items( int $product_id, bool $include_legacy_boom = true ): array {
	$theme_items  = jluxe_sanitize_product_faq_items( get_post_meta( $product_id, '_jluxe_product_faq', true ) );
	$has_override = '1' === (string) get_post_meta( $product_id, '_jluxe_product_faq_override', true );
	if ( $has_override ) {
		return jluxe_sanitize_product_faq_items( get_post_meta( $product_id, '_jluxe_product_faq_override_items', true ) );
	}
	if ( ! $include_legacy_boom ) {
		return $theme_items;
	}

	$boom_items = jluxe_sanitize_product_faq_items( get_post_meta( $product_id, '_boom_product_faq', true ) );
	if ( ! $boom_items ) {
		return $theme_items;
	}

	$items = $boom_items;
	$seen  = array();
	foreach ( $boom_items as $item ) {
		$key = jluxe_product_faq_question_key( $item['question'] );
		if ( '' !== $key ) {
			$seen[ $key ] = true;
		}
	}
	foreach ( $theme_items as $item ) {
		$key = jluxe_product_faq_question_key( $item['question'] );
		if ( '' !== $key && isset( $seen[ $key ] ) ) {
			continue;
		}
		$items[] = $item;
		if ( '' !== $key ) {
			$seen[ $key ] = true;
		}
	}

	return $items;
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
