<?php
/**
 * صفحه‌ی «روش‌های ارسال و راهنمای پیگیری سفارشات» (اسلاگ
 * shipping-and-order-tracking). عنوان/مقدمه از jluxe-guide-pages میان و
 * بقیه‌ی نامِ برند در متنِ بدنه با نامِ واقعیِ سایت جایگزین شده — طبقِ
 * درخواستِ صریحِ کاربر (قبلاً «زرین» هاردکد بود). کلاس‌های CSS (jst-*)
 * کاملاً مستقل و خوداتکا هستن.
 */

get_header();

$jluxe_st_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_st           = $jluxe_st_settings['guide_pages']['shipping_tracking'] ?? array();
$jluxe_st_site_name = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
$jluxe_st_title     = $jluxe_st['title'] ?? 'روش‌های ارسال و راهنمای پیگیری سفارشات';
$jluxe_st_intro     = str_replace( '{site_name}', $jluxe_st_site_name, $jluxe_st['intro'] ?? '' );
$jluxe_st_btn_text  = $jluxe_st['button_text'] ?? '';
$jluxe_st_btn_url   = $jluxe_st['button_url'] ?? '/';
$jluxe_st_btn_style = ! empty( $jluxe_st['button_color'] ) ? ' style="background-color:' . esc_attr( $jluxe_st['button_color'] ) . '"' : '';
$jluxe_st_body = str_replace( '{site_name}', $jluxe_st_site_name, $jluxe_st['body_html'] ?? '' );
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-shipping-tracking-page {
			--jst-ink: #1c2b2d;
			--jst-sub: #5b6a6b;
			--jst-line: #dbe7ec;
			--jst-blue: #3c7bdb;
			--jst-cyan: #0e9ed5;
			--jst-teal: #0f7475;
			--jst-green: #059669;
			--jst-amber1: #c26309;
			--jst-amber2: #ea910a;
			--jst-red: #b91c1c;

			box-sizing: border-box;
			max-width: 720px;
			margin: 28px auto 60px;
			padding: 0 clamp(16px, 6vw, 32px);
			direction: rtl;
			text-align: right;
			color: var(--jst-ink);
			font-size: 15px;
			line-height: 1.8;
		}

		#jluxe-shipping-tracking-page *,
		#jluxe-shipping-tracking-page *::before,
		#jluxe-shipping-tracking-page *::after {
			box-sizing: inherit;
		}

		.jst-hero {
			padding: 36px 0 24px;
		}

		.jst-eyebrow {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.3px;
			color: var(--jst-green);
			margin-bottom: 8px;
		}

		.jst-hero h1 {
			margin: 8px 0 16px;
			font-size: 22px;
			font-weight: 800;
			line-height: 1.5;
			color: var(--jst-ink);
		}

		.jst-hero > p {
			margin: 0;
			font-size: 14px;
			color: var(--jst-sub);
			line-height: 1.85;
		}

		.jst-part-label {
			display: block;
			font-size: 12.5px;
			font-weight: 800;
			color: var(--jst-teal);
			margin: 30px 0 14px;
			padding-bottom: 8px;
			border-bottom: 1px solid var(--jst-line);
		}

		.jst-callout {
			border-radius: 10px;
			padding: 14px 16px;
			margin: 16px 0;
		}

		.jst-callout p {
			margin: 0;
			font-size: 13px;
			line-height: 1.8;
		}

		.jst-callout-warning {
			background: #fdf0e8;
			border-right: 3px solid var(--jst-amber1);
		}

		.jst-callout-warning p {
			color: #8a4409;
		}

		.jst-methods {
			display: flex;
			flex-direction: column;
			gap: 18px;
		}

		.jst-method {
			background: #ffffff;
			border: 1px solid var(--jst-line);
			border-radius: 14px;
			padding: 22px 22px 24px;
			transition: box-shadow 0.25s ease, transform 0.25s ease;
		}

		.jst-method:hover {
			box-shadow: 0 10px 26px rgba(15, 60, 65, 0.08);
			transform: translateY(-2px);
		}

		.jst-method-head {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 12px;
		}

		.jst-num {
			display: flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 32px;
			width: 32px;
			height: 32px;
			border-radius: 50%;
			background: linear-gradient(135deg, var(--jst-blue), var(--jst-cyan), var(--jst-teal));
			color: #ffffff;
			font-weight: 800;
			font-size: 13px;
			box-shadow: 0 4px 10px rgba(15, 116, 117, 0.25);
		}

		.jst-method-head h2 {
			margin: 0;
			font-size: 15.5px;
			font-weight: 700;
			color: var(--jst-ink);
		}

		.jst-method-head h2 small {
			display: inline-block;
			margin-right: 4px;
			font-size: 12px;
			font-weight: 500;
			color: var(--jst-sub);
		}

		.jst-method-desc {
			margin: 0 0 12px !important;
			font-size: 13.5px !important;
			color: var(--jst-ink) !important;
		}

		.jst-method p {
			margin: 0 0 8px;
			font-size: 13px;
			color: var(--jst-sub);
			line-height: 1.75;
		}

		.jst-method p:last-of-type {
			margin-bottom: 14px;
		}

		.jst-track-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			border: none;
			border-radius: 10px;
			padding: 11px 20px;
			text-decoration: none;
			transition: filter 0.2s ease, transform 0.2s ease;
		}

		.jst-track-btn:hover {
			transform: translateY(-1px);
			filter: brightness(1.08);
		}

		.jst-track-btn span {
			font-size: 13px;
			font-weight: 700;
			color: #ffffff;
		}

		.jst-track-btn-post {
			background: #14315c;
			box-shadow: 0 6px 16px rgba(20, 49, 92, 0.3);
		}

		.jst-track-btn-tipax {
			background: #1a9c4b;
			box-shadow: 0 6px 16px rgba(26, 156, 75, 0.3);
		}

		.jst-track-btn-chapar {
			background: #d3352b;
			box-shadow: 0 6px 16px rgba(211, 53, 43, 0.3);
		}

		.jst-section {
			background: #ffffff;
			border: 1px solid var(--jst-line);
			border-radius: 14px;
			padding: 24px 22px;
		}

		.jst-question {
			margin: 0 0 12px;
			font-size: 16px;
			font-weight: 800;
			color: var(--jst-ink);
			line-height: 1.5;
		}

		.jst-section > p {
			margin: 0 0 8px;
			font-size: 13.5px;
			color: var(--jst-sub);
			line-height: 1.8;
		}

		.jst-substeps-title {
			margin: 22px 0 14px !important;
			font-size: 13.5px !important;
			font-weight: 700 !important;
			color: var(--jst-ink) !important;
		}

		.jst-status {
			border-radius: 10px;
			padding: 14px 16px;
			margin-bottom: 10px;
			background: #f8fafb;
			border-right: 3px solid var(--jst-line);
		}

		.jst-status:last-of-type {
			margin-bottom: 0;
		}

		.jst-status p {
			margin: 8px 0 0;
			font-size: 12.5px;
			color: var(--jst-sub);
			line-height: 1.75;
		}

		.jst-status-pill {
			display: inline-block;
			font-size: 12px;
			font-weight: 800;
			padding: 4px 12px;
			border-radius: 999px;
			color: #ffffff;
		}

		.jst-status-pending {
			border-color: var(--jst-amber1);
		}
		.jst-status-pending .jst-status-pill {
			background: linear-gradient(135deg, var(--jst-amber1), var(--jst-amber2));
		}

		.jst-status-processing {
			border-color: var(--jst-blue);
		}
		.jst-status-processing .jst-status-pill {
			background: linear-gradient(135deg, var(--jst-blue), var(--jst-cyan));
		}

		.jst-status-done {
			border-color: var(--jst-green);
		}
		.jst-status-done .jst-status-pill {
			background: var(--jst-green);
		}

		.jst-status-cancelled {
			border-color: var(--jst-red);
		}
		.jst-status-cancelled .jst-status-pill {
			background: var(--jst-red);
		}

		.jst-quicktrack {
			margin-top: 4px;
		}

		.jst-quicktrack p {
			margin: 0 0 10px;
			font-size: 13px;
			color: var(--jst-sub);
			line-height: 1.8;
		}

		.jst-track-order-btn {
			display: inline-block;
			margin-top: 8px;
			background: linear-gradient(135deg, var(--jst-amber1), var(--jst-amber2));
			color: #ffffff;
			text-decoration: none;
			font-size: 14px;
			font-weight: 700;
			padding: 13px 30px;
			border-radius: 999px;
			box-shadow: 0 8px 18px rgba(194, 99, 9, 0.3);
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.jst-track-order-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 12px 22px rgba(194, 99, 9, 0.38);
		}

		@media (max-width: 480px) {
			.jst-method,
			.jst-section {
				padding: 18px 16px 20px;
			}
			.jst-hero h1 {
				font-size: 19px;
			}
			.jst-track-btn {
				width: 100%;
			}
			.jst-track-order-btn {
				display: block;
				text-align: center;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.jst-method,
			.jst-track-btn,
			.jst-track-order-btn {
				transition: none;
			}
		}
	</style>

	<div id="jluxe-shipping-tracking-page">

		<header class="jst-hero">
			<span class="jst-eyebrow">— ارسال و پیگیری</span>
			<h1><?php echo esc_html( $jluxe_st_title ); ?></h1>
			<?php if ( $jluxe_st_intro ) : ?><p><?php echo esc_html( $jluxe_st_intro ); ?></p><?php endif; ?>
		</header>
		<div class="jluxe-guide-body"><?php echo wp_kses_post( $jluxe_st_body ); ?></div>
		<?php if ( $jluxe_st_btn_text ) : ?><div class="jst-footer-cta"><a href="<?php echo esc_url( $jluxe_st_btn_url ); ?>" class="jst-track-order-btn"<?php echo $jluxe_st_btn_style; ?>><?php echo esc_html( $jluxe_st_btn_text ); ?></a></div><?php endif; ?>

</main>

<?php
get_footer();
