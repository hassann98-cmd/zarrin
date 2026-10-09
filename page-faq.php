<?php
/**
 * صفحهٔ سوالات متداول — محتوایش از «تنظیمات پوسته ← سوالات متداول» می‌آید.
 * آکاردئون از <details>/<summary> استفاده می‌کند تا بدون JavaScript هم
 * قابل‌استفاده بماند؛ اسکریپت فقط بازوبسته‌شدن را روان‌تر می‌کند.
 */

defined( 'ABSPATH' ) || exit;

$jluxe_faq_script_path = JLUXE_THEME_DIR . '/assets/js/site-faq.js';
wp_enqueue_script(
	'jluxe-site-faq',
	JLUXE_THEME_URI . '/assets/js/site-faq.js',
	array(),
	file_exists( $jluxe_faq_script_path ) ? (string) filemtime( $jluxe_faq_script_path ) : null,
	array(
		'in_footer' => true,
		'strategy'  => 'defer',
	)
);

$jluxe_faq_items = jluxe_get_setting( 'faq.items', array() );
$jluxe_faq_items = is_array( $jluxe_faq_items ) ? array_values( $jluxe_faq_items ) : array();

get_header();
?>

<main id="primary" class="site-main">
	<section class="jluxe-site-faq" dir="rtl" aria-labelledby="jluxe-site-faq-title">
		<div class="jluxe-site-faq__card">
			<header class="jluxe-site-faq__header">
				<span class="jluxe-site-faq__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="9"></circle>
						<path d="M9.7 9a2.45 2.45 0 1 1 4.5 1.35c-.8 1.03-2.2 1.22-2.2 2.65"></path>
						<path d="M12 16.5h.01"></path>
					</svg>
				</span>
				<div class="jluxe-site-faq__heading">
					<h1 id="jluxe-site-faq-title">سوال داری؟</h1>
					<p>شاید جواب سوالت رو اینجا پیدا کردی</p>
				</div>
			</header>

			<?php if ( ! empty( $jluxe_faq_items ) ) : ?>
				<div class="jluxe-site-faq__list">
					<?php foreach ( $jluxe_faq_items as $jluxe_faq_index => $jluxe_faq_item ) : ?>
						<?php
						if ( ! is_array( $jluxe_faq_item ) ) {
							continue;
						}
						$jluxe_faq_question = trim( (string) ( $jluxe_faq_item['question'] ?? '' ) );
						if ( '' === $jluxe_faq_question ) {
							continue;
						}
						$jluxe_faq_answer    = trim( (string) ( $jluxe_faq_item['answer'] ?? '' ) );
						$jluxe_faq_panel_id  = 'jluxe-site-faq-panel-' . (int) $jluxe_faq_index;
						$jluxe_faq_title_id  = 'jluxe-site-faq-question-' . (int) $jluxe_faq_index;
						?>
						<details class="jluxe-site-faq__item">
							<summary id="<?php echo esc_attr( $jluxe_faq_title_id ); ?>" class="jluxe-site-faq__trigger" aria-controls="<?php echo esc_attr( $jluxe_faq_panel_id ); ?>">
								<span class="jluxe-site-faq__question"><?php echo esc_html( $jluxe_faq_question ); ?></span>
								<svg class="jluxe-site-faq__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 10 5 5 5-5"></path></svg>
							</summary>
							<div id="<?php echo esc_attr( $jluxe_faq_panel_id ); ?>" class="jluxe-site-faq__panel" aria-labelledby="<?php echo esc_attr( $jluxe_faq_title_id ); ?>">
								<div class="jluxe-site-faq__panel-inner">
									<?php if ( '' !== $jluxe_faq_answer ) : ?>
										<p><?php echo nl2br( esc_html( $jluxe_faq_answer ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html پیشاپیش متن را پاک‌سازی می‌کند؛ nl2br فقط شکست‌خط امن می‌افزاید. ?></p>
									<?php else : ?>
										<p>پاسخ این پرسش هنوز ثبت نشده است.</p>
									<?php endif; ?>
								</div>
							</div>
						</details>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="jluxe-site-faq__empty">در حال حاضر پرسشی ثبت نشده است.</p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php get_footer(); ?>
