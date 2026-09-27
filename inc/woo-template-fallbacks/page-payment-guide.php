<?php
/**
 * صفحه‌ی «روش‌ها و راهنمای پرداخت سفارشات» (اسلاگ payment-guide). قبلاً این
 * صفحه عیناً همون متن و شماره‌حساب/شبای واقعیِ jluxe.ir رو هاردکد داشت —
 * طبقِ درخواستِ صریحِ کاربر («هر نامی از جهیزیه لوکس یا اطلاعاتِ کارت یا
 * شماره حساب یا نامِ دارندهٔ حساب باید در تنظیمات قابلِ‌تغییر باشه») عنوان/
 * مقدمه/دکمه از jluxe-guide-pages و اطلاعاتِ حساب از jluxe-payment-account
 * میاد. کلاس‌های CSS این صفحه (jpp-*) کاملاً مستقل و خوداتکا هستن (بدون
 * وابستگی به Tailwind کامپایل‌شده‌ی قالب).
 *
 * توجه: «زرین‌پال» در متنِ بخشِ ۱ نامِ واقعیِ درگاهِ پرداخته، نه نامِ برند —
 * عمداً دست‌نخورده مونده.
 */

get_header();

$jluxe_pg_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_pg           = $jluxe_pg_settings['guide_pages']['payment_guide'] ?? array();
$jluxe_pg_account   = $jluxe_pg_settings['payment_account'] ?? array();
$jluxe_pg_site_name = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );

$jluxe_pg_token = static function ( string $text ) use ( $jluxe_pg_site_name ): string {
	return str_replace( '{site_name}', $jluxe_pg_site_name, $text );
};

$jluxe_pg_title = $jluxe_pg_token( $jluxe_pg['title'] ?? 'روش‌ها و راهنمای پرداخت سفارشات' );
$jluxe_pg_intro = $jluxe_pg_token( $jluxe_pg['intro'] ?? '' );
$jluxe_pg_btn_text  = $jluxe_pg['button_text'] ?? '';
$jluxe_pg_btn_url   = $jluxe_pg['button_url'] ?? '/';
$jluxe_pg_btn_style = ! empty( $jluxe_pg['button_color'] ) ? ' style="background-color:' . esc_attr( $jluxe_pg['button_color'] ) . '"' : '';

// بخشِ کارت‌به‌کارت فقط وقتی نمایش داده می‌شه که ادمین واقعاً شماره‌کارت رو
// پر کرده باشه — پیش‌فرضِ همه‌چی خالیه تا اطلاعاتِ حسابِ اشتباه/متعلق‌به‌
// کس‌دیگه به مشتری نشون داده نشه.
$jluxe_pg_has_account = ! empty( $jluxe_pg_account['card_number'] );
$jluxe_pg_body = strtr( $jluxe_pg['body_html'] ?? '', array( '{site_name}' => $jluxe_pg_site_name, '{card_number}' => $jluxe_pg_account['card_number'] ?? '', '{sheba}' => $jluxe_pg_account['sheba'] ?? '', '{holder_name}' => $jluxe_pg_account['holder_name'] ?? '', '{bank_name}' => $jluxe_pg_account['bank_name'] ?? '' ) );
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-payment-page {
			--pp-ink: #1c2b2d;
			--pp-sub: #5b6a6b;
			--pp-line: #dbe7ec;
			--pp-blue: #3c7bdb;
			--pp-cyan: #0e9ed5;
			--pp-teal: #0f7475;
			--pp-green: #059669;
			--pp-amber1: #c26309;
			--pp-amber2: #ea910a;

			box-sizing: border-box;
			max-width: 720px;
			margin: 28px auto 60px;
			padding: 0 clamp(16px, 6vw, 32px);
			direction: rtl;
			text-align: right;
			color: var(--pp-ink);
			font-size: 15px;
			line-height: 1.8;
		}

		#jluxe-payment-page *,
		#jluxe-payment-page *::before,
		#jluxe-payment-page *::after {
			box-sizing: inherit;
		}

		.jpp-hero {
			padding: 36px 0 26px;
		}

		.jpp-eyebrow {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.3px;
			color: var(--pp-green);
			margin-bottom: 8px;
		}

		.jpp-hero h1 {
			margin: 8px 0 14px;
			font-size: 22px;
			font-weight: 800;
			line-height: 1.5;
			color: var(--pp-ink);
		}

		.jpp-hero p {
			margin: 0;
			font-size: 14px;
			color: var(--pp-sub);
			line-height: 1.85;
		}

		.jpp-section {
			background: #ffffff;
			border: 1px solid var(--pp-line);
			border-radius: 14px;
			padding: 22px 22px 24px;
			margin-bottom: 18px;
		}

		.jpp-section-head {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 14px;
		}

		.jpp-num {
			display: flex;
			align-items: center;
			justify-content: center;
			flex: 0 0 32px;
			width: 32px;
			height: 32px;
			border-radius: 50%;
			background: linear-gradient(135deg, var(--pp-blue), var(--pp-cyan), var(--pp-teal));
			color: #ffffff;
			font-weight: 800;
			font-size: 13px;
			box-shadow: 0 4px 10px rgba(15, 116, 117, 0.25);
		}

		.jpp-section-head h2 {
			margin: 0;
			font-size: 15.5px;
			font-weight: 700;
			color: var(--pp-ink);
		}

		.jpp-section-head h2 small {
			display: inline-block;
			margin-right: 4px;
			font-size: 12px;
			font-weight: 500;
			color: var(--pp-sub);
		}

		.jpp-section p {
			margin: 0 0 8px;
			font-size: 13px;
			color: var(--pp-sub);
			line-height: 1.8;
		}

		.jpp-section > p:last-of-type {
			margin-bottom: 0;
		}

		.jpp-substeps-title {
			margin: 20px 0 12px !important;
			font-size: 13.5px !important;
			font-weight: 700 !important;
			color: var(--pp-ink) !important;
		}

		.jpp-substep-note {
			margin: -4px 0 12px !important;
			padding-right: 4px;
			font-size: 12px !important;
			color: #8a9495 !important;
		}

		.jpp-inline-code {
			display: inline-block;
			direction: ltr;
			unicode-bidi: embed;
			background: #f1f5f5;
			border-radius: 4px;
			padding: 1px 6px;
			font-size: 12px;
			color: var(--pp-ink);
		}

		.jpp-callout {
			margin: 12px 0 14px;
			border-radius: 10px;
			padding: 14px 16px;
		}

		.jpp-callout p {
			margin: 0 !important;
			font-size: 12.5px !important;
			line-height: 1.75 !important;
		}

		.jpp-callout-tip {
			background: #eef7f5;
		}
		.jpp-callout-tip p {
			color: var(--pp-teal) !important;
		}

		.jpp-callout-warning {
			background: #fdf0e8;
			border-right: 3px solid var(--pp-amber1);
		}
		.jpp-callout-warning p {
			color: #8a4409 !important;
		}

		.jpp-callout-note {
			background: #eef4fb;
			margin-top: 16px;
		}
		.jpp-callout-note p {
			color: #245a94 !important;
		}

		.jpp-account-box {
			background: #f7fafa;
			border: 1.5px dashed var(--pp-teal);
			border-radius: 12px;
			padding: 16px 18px;
			margin-bottom: 18px;
		}

		.jpp-account-title {
			margin: 0 0 12px !important;
			font-size: 12.5px !important;
			font-weight: 700 !important;
			color: var(--pp-teal) !important;
		}

		.jpp-account-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			flex-wrap: wrap;
			padding: 8px 0;
			border-bottom: 1px dashed #d7e6e6;
		}

		.jpp-account-row:last-of-type {
			border-bottom: none;
		}

		.jpp-account-label {
			font-size: 12.5px;
			color: var(--pp-sub);
			flex-shrink: 0;
		}

		.jpp-account-value {
			direction: ltr;
			unicode-bidi: embed;
			font-size: 14px;
			font-weight: 700;
			color: var(--pp-ink);
			letter-spacing: 0.4px;
		}

		.jpp-account-value-name {
			direction: rtl;
			unicode-bidi: embed;
		}

		.jpp-account-note {
			margin: 10px 0 0 !important;
			font-size: 11.5px !important;
			color: var(--pp-sub) !important;
		}

		.jpp-footer-cta {
			text-align: center;
			padding: 20px 0 10px;
		}

		.jpp-btn {
			display: inline-block;
			background: linear-gradient(135deg, var(--pp-amber1), var(--pp-amber2));
			color: #ffffff;
			text-decoration: none;
			font-size: 14px;
			font-weight: 700;
			padding: 13px 32px;
			border-radius: 999px;
			box-shadow: 0 8px 18px rgba(194, 99, 9, 0.3);
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.jpp-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 12px 22px rgba(194, 99, 9, 0.38);
		}

		@media (max-width: 480px) {
			.jpp-section {
				padding: 18px 16px 20px;
			}
			.jpp-hero h1 {
				font-size: 19px;
			}
			.jpp-account-row {
				flex-direction: column;
				align-items: flex-start;
				gap: 4px;
			}
			.jpp-account-value {
				font-size: 13px;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.jpp-btn {
				transition: none;
			}
		}
	</style>

	<div id="jluxe-payment-page">

		<header class="jpp-hero">
			<span class="jpp-eyebrow">— راهنمای پرداخت</span>
			<h1><?php echo esc_html( $jluxe_pg_title ); ?></h1>
			<?php if ( $jluxe_pg_intro ) : ?><p><?php echo esc_html( $jluxe_pg_intro ); ?></p><?php endif; ?>
		</header>
		<div class="jluxe-guide-body"><?php echo wp_kses_post( $jluxe_pg_body ); ?></div>
		<?php if ( $jluxe_pg_btn_text ) : ?><div class="jpp-footer-cta"><a href="<?php echo esc_url( $jluxe_pg_btn_url ); ?>" class="jpp-btn"<?php echo $jluxe_pg_btn_style; ?>><?php echo esc_html( $jluxe_pg_btn_text ); ?></a></div><?php endif; ?>

</main>

<?php
get_footer();
