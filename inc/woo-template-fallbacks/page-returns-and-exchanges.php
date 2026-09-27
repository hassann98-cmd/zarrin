<?php
/**
 * صفحه‌ی «رویه‌ی شرایط مرجوعی و تعویض کالا» (اسلاگ returns-and-exchanges).
 * عنوان/مقدمه از jluxe-guide-pages میان و بقیه‌ی نامِ برند در متنِ بدنه با
 * نامِ واقعیِ سایت جایگزین شده — طبقِ درخواستِ صریحِ کاربر (قبلاً «زرین»
 * هاردکد بود). کلاس‌های CSS (jrp-*) کاملاً مستقل و خوداتکا هستن.
 */

get_header();

$jluxe_re_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_re           = $jluxe_re_settings['guide_pages']['returns_exchanges'] ?? array();
$jluxe_re_site_name = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
$jluxe_re_title     = $jluxe_re['title'] ?? 'رویهٔ شرایط مرجوعی و تعویض کالا';
$jluxe_re_intro     = str_replace( '{site_name}', $jluxe_re_site_name, $jluxe_re['intro'] ?? '' );
/*
 * طبقِ درخواستِ صریحِ کاربر — این متن‌ها قبلاً هاردکد بودن، الان از تنظیمات
 * (زرین ← صفحات راهنما ← رویه‌ی شرایط مرجوعی) میان. دکمه‌ی پایینِ صفحه هم
 * از قبل توی تنظیمات وجود داشت ولی این تمپلیت بهش وصل نبود — همون‌جا وصلش
 * کردیم (button_text/button_url/button_color، مثلِ بقیه‌ی صفحاتِ راهنما).
 */
$jluxe_re_deny_title  = $jluxe_re['deny_title'] ?? '';
$jluxe_re_deny1_label = $jluxe_re['deny_item_1_label'] ?? '';
$jluxe_re_deny1_text  = $jluxe_re['deny_item_1_text'] ?? '';
$jluxe_re_deny2_label = $jluxe_re['deny_item_2_label'] ?? '';
$jluxe_re_deny2_text  = $jluxe_re['deny_item_2_text'] ?? '';
$jluxe_re_tip_label   = $jluxe_re['tip_label'] ?? '';
$jluxe_re_tip_text    = $jluxe_re['tip_text'] ?? '';
$jluxe_re_btn_text    = $jluxe_re['button_text'] ?? 'بازگشت به فروشگاه ←';
$jluxe_re_btn_url     = $jluxe_re['button_url'] ?? '/';
$jluxe_re_btn_style   = ! empty( $jluxe_re['button_color'] ) ? ' style="background:' . esc_attr( $jluxe_re['button_color'] ) . '"' : '';
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-returns-page {
			--rp-ink: #1c2b2d;
			--rp-sub: #5b6a6b;
			--rp-line: #dbe7ec;
			--rp-blue: #3c7bdb;
			--rp-cyan: #0e9ed5;
			--rp-teal: #0f7475;
			--rp-green: #059669;
			--rp-amber1: #c26309;
			--rp-amber2: #ea910a;
			--rp-red: #b91c1c;

			box-sizing: border-box;
			max-width: 720px;
			margin: 28px auto 60px;
			padding: 0 clamp(16px, 6vw, 32px);
			direction: rtl;
			text-align: right;
			color: var(--rp-ink);
			font-size: 15px;
			line-height: 1.8;
		}

		#jluxe-returns-page *,
		#jluxe-returns-page *::before,
		#jluxe-returns-page *::after {
			box-sizing: inherit;
		}

		.jrp-hero {
			padding: 36px 0 26px;
		}

		.jrp-eyebrow {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.3px;
			color: var(--rp-green);
			margin-bottom: 8px;
		}

		.jrp-hero h1 {
			margin: 8px 0 14px;
			font-size: 22px;
			font-weight: 800;
			line-height: 1.5;
			color: var(--rp-ink);
		}

		.jrp-hero p {
			margin: 0;
			font-size: 14px;
			color: var(--rp-sub);
			line-height: 1.85;
		}

		.jrp-section {
			background: #ffffff;
			border: 1px solid var(--rp-line);
			border-radius: 14px;
			padding: 22px 22px 24px;
			margin-bottom: 18px;
		}

		.jrp-section-head {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 14px;
		}

		.jrp-num {
			display: flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 32px;
			width: 32px;
			height: 32px;
			border-radius: 50%;
			background: linear-gradient(135deg, var(--rp-blue), var(--rp-cyan), var(--rp-teal));
			color: #ffffff;
			font-weight: 800;
			font-size: 13px;
			box-shadow: 0 4px 10px rgba(15, 116, 117, 0.25);
		}

		.jrp-section-head h2 {
			margin: 0;
			font-size: 15.5px;
			font-weight: 700;
			color: var(--rp-ink);
		}

		.jrp-section-head h2 small {
			display: inline-block;
			margin-right: 4px;
			font-size: 12px;
			font-weight: 500;
			color: var(--rp-sub);
		}

		.jrp-section p,
		.jrp-golden-rule > p {
			margin: 0 0 8px;
			font-size: 13px;
			color: var(--rp-sub);
			line-height: 1.8;
		}

		.jrp-section p:last-of-type {
			margin-bottom: 0;
		}

		.jrp-lead {
			color: var(--rp-ink) !important;
			font-weight: 600;
		}

		.jrp-callout {
			margin-top: 14px;
			border-radius: 10px;
			padding: 14px 16px;
		}

		.jrp-callout p {
			margin: 0 0 6px;
			font-size: 12.5px;
			line-height: 1.75;
		}

		.jrp-callout p:last-child {
			margin-bottom: 0;
		}

		.jrp-callout-title {
			font-weight: 800 !important;
			font-size: 13px !important;
			margin-bottom: 8px !important;
		}

		.jrp-callout-deny {
			background: #fdeeee;
		}

		.jrp-callout-deny .jrp-callout-title,
		.jrp-callout-deny p {
			color: var(--rp-red);
		}

		.jrp-callout-tip {
			background: #eef7f5;
		}

		.jrp-callout-tip p {
			color: var(--rp-teal);
		}

		.jrp-golden-rule {
			position: relative;
			background: #fff8e8;
			border: 2px solid var(--rp-amber1);
			border-radius: 14px;
			padding: 30px 22px 24px;
			margin-bottom: 18px;
		}

		.jrp-golden-badge {
			position: absolute;
			top: -13px;
			right: 20px;
			background: linear-gradient(135deg, var(--rp-amber1), var(--rp-amber2));
			color: #ffffff;
			font-size: 11.5px;
			font-weight: 800;
			padding: 5px 14px;
			border-radius: 999px;
			box-shadow: 0 4px 10px rgba(194, 99, 9, 0.35);
		}

		.jrp-golden-rule .jrp-num-alert {
			background: linear-gradient(135deg, var(--rp-amber1), var(--rp-amber2));
			box-shadow: 0 4px 10px rgba(194, 99, 9, 0.3);
		}

		.jrp-golden-rule .jrp-section-head h2 {
			color: #7a3d05;
		}

		.jrp-golden-warning {
			margin-top: 12px;
			background: #ffffff;
			border: 1.5px solid var(--rp-amber1);
			border-radius: 10px;
			padding: 14px 16px;
		}

		.jrp-golden-warning p {
			margin: 0 !important;
			font-size: 12.5px !important;
			color: #7a3d05 !important;
			line-height: 1.75 !important;
		}

		.jrp-footer-cta {
			text-align: center;
			padding: 20px 0 10px;
		}

		.jrp-btn {
			display: inline-block;
			background: linear-gradient(135deg, var(--rp-amber1), var(--rp-amber2));
			color: #ffffff;
			text-decoration: none;
			font-size: 14px;
			font-weight: 700;
			padding: 13px 32px;
			border-radius: 999px;
			box-shadow: 0 8px 18px rgba(194, 99, 9, 0.3);
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.jrp-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 12px 22px rgba(194, 99, 9, 0.38);
		}

		@media (max-width: 480px) {
			.jrp-section,
			.jrp-golden-rule {
				padding: 18px 16px 20px;
			}
			.jrp-golden-rule {
				padding-top: 26px;
			}
			.jrp-hero h1 {
				font-size: 19px;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.jrp-btn {
				transition: none;
			}
		}
	</style>

	<div id="jluxe-returns-page">

		<header class="jrp-hero">
			<span class="jrp-eyebrow">— شرایط مرجوعی</span>
			<h1><?php echo esc_html( $jluxe_re_title ); ?></h1>
			<?php if ( $jluxe_re_intro ) : ?><p><?php echo esc_html( $jluxe_re_intro ); ?></p><?php endif; ?>
		</header>
		<div class="jluxe-guide-body"><?php echo wp_kses_post( $jluxe_re_body ); ?></div>
		<?php if ( $jluxe_re_btn_text ) : ?><div class="jrp-footer-cta"><a href="<?php echo esc_url( $jluxe_re_btn_url ); ?>" class="jrp-btn"<?php echo $jluxe_re_btn_style; ?>><?php echo esc_html( $jluxe_re_btn_text ); ?></a></div><?php endif; ?>

</main>

<?php
get_footer();
