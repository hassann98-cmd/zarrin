<?php
/**
 * صفحه‌ی سوالات متداول (اسلاگ "faq" — لینکش از فوتر واقعاً به اینجا وصله،
 * قبلاً به یک صفحه‌ی ناموجود اشاره می‌کرد). محتوا از بخش «سوالات متداول»ی
 * تنظیمات پوسته میاد؛ اگر مدیر هنوز چیزی وارد نکرده، پیام واقعیِ خالی بودن
 * نشون داده می‌شه — نه چند سوال عمومیِ ساختگی.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$jluxe_faq_items = jluxe_get_setting( 'faq.items', array() );
?>

<main id="primary" class="site-main">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-6">
		<h1 class="text-h1 text-foreground mb-6">سوالات متداول</h1>

		<?php if ( $jluxe_faq_items ) : ?>
			<div class="divide-y divide-border rounded-2xl border border-border bg-surface">
				<?php foreach ( $jluxe_faq_items as $jluxe_faq_item ) : ?>
					<details class="p-4">
						<summary class="text-body font-bold text-foreground cursor-pointer"><?php echo esc_html( $jluxe_faq_item['question'] ); ?></summary>
						<?php if ( ! empty( $jluxe_faq_item['answer'] ) ) : ?>
							<p class="text-small text-muted-foreground mt-2"><?php echo nl2br( esc_html( $jluxe_faq_item['answer'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="text-body text-muted-foreground">در حال حاضر سوالی ثبت نشده. از پیشخوان → تنظیمات پوسته JLuxe → «سوالات متداول» قابل افزودنه.</p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
