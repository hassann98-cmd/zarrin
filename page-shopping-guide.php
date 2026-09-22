<?php
/**
 * صفحه‌ی «راهنمای گام‌به‌گام خرید» (اسلاگ shopping-guide). عنوان و دکمهٔ
 * پایانی از تنظیماتِ jluxe-guide-pages میان (طبقِ درخواستِ صریحِ کاربر) —
 * چون قبلاً «زرین» هاردکد بود. کلاس‌های CSS (jsg-*) مستقل و خوداتکا هستن؛
 * script.js فقط یک انیمیشن اختیاری اسکرول است (بدون آن هم صفحه کامل کار
 * می‌کند).
 */

get_header();

$jluxe_sg_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_sg           = $jluxe_sg_settings['guide_pages']['shopping_guide'] ?? array();
$jluxe_sg_site_name = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
$jluxe_sg_eyebrow   = str_replace( '{site_name}', $jluxe_sg_site_name, $jluxe_sg['eyebrow'] ?? '— راهنمای خرید' );
$jluxe_sg_title     = str_replace( '{site_name}', $jluxe_sg_site_name, $jluxe_sg['title'] ?? 'راهنمای گام‌به‌گام خرید از {site_name}' );
$jluxe_sg_intro     = str_replace( '{site_name}', $jluxe_sg_site_name, $jluxe_sg['intro'] ?? '' );
$jluxe_sg_steps     = isset( $jluxe_sg['steps'] ) && is_array( $jluxe_sg['steps'] ) ? $jluxe_sg['steps'] : array();
$jluxe_sg_btn_text  = $jluxe_sg['button_text'] ?? 'شروع خرید ←';
if ( mb_strlen( wp_strip_all_tags( (string) $jluxe_sg_btn_text ) ) > 80 ) { $jluxe_sg_btn_text = 'شروع خرید ←'; }
$jluxe_sg_btn_url   = $jluxe_sg['button_url'] ?? '/';
$jluxe_sg_btn_style = ! empty( $jluxe_sg['button_color'] ) ? ' style="background-color:' . esc_attr( $jluxe_sg['button_color'] ) . '"' : '';
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-step-guide {
			--sg-ink: #1c2b2d;
			--sg-sub: #5b6a6b;
			--sg-line: #dbe7ec;
			--sg-blue: #3c7bdb;
			--sg-cyan: #0e9ed5;
			--sg-teal: #0f7475;
			--sg-green: #059669;
			--sg-orange1: #c26309;
			--sg-orange2: #ea910a;

			box-sizing: border-box;
			max-width: 720px;
			margin: 28px auto 60px;
			padding: 0 clamp(16px, 6vw, 32px);
			direction: rtl;
			text-align: right;
			color: var(--sg-ink);
			font-size: 15px;
			line-height: 1.8;
		}

		#jluxe-step-guide *,
		#jluxe-step-guide *::before,
		#jluxe-step-guide *::after {
			box-sizing: inherit;
		}

		.jsg-hero {
			padding: 36px 0 30px;
		}

		.jsg-eyebrow {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.3px;
			color: var(--sg-green);
			margin-bottom: 8px;
		}

		.jsg-hero h1 {
			margin: 8px 0 14px;
			font-size: 22px;
			font-weight: 800;
			line-height: 1.5;
			color: var(--sg-ink);
		}

		.jsg-hero p {
			margin: 0;
			font-size: 14px;
			color: var(--sg-sub);
			line-height: 1.85;
		}

		.jsg-timeline {
			position: relative;
		}

		.jsg-step {
			position: relative;
			display: flex;
			gap: 16px;
			padding-bottom: 30px;
		}

		.jsg-step:last-child {
			padding-bottom: 0;
		}

		.jsg-step-marker {
			position: relative;
			flex: 0 0 34px;
			width: 34px;
		}

		.jsg-step:not(:last-child) .jsg-step-marker::after {
			content: "";
			position: absolute;
			top: 34px;
			bottom: -30px;
			right: 16px;
			width: 2px;
			background: linear-gradient(to bottom, var(--sg-blue), var(--sg-teal));
			opacity: 0.3;
		}

		.jsg-step-num {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 34px;
			height: 34px;
			border-radius: 50%;
			background: linear-gradient(135deg, var(--sg-blue), var(--sg-cyan), var(--sg-teal));
			color: #ffffff;
			font-weight: 800;
			font-size: 14px;
			box-shadow: 0 4px 10px rgba(15, 116, 117, 0.25);
			transition: transform 0.3s ease, box-shadow 0.3s ease;
		}

		.jsg-step-num.jsg-active {
			transform: scale(1.15);
			box-shadow: 0 6px 18px rgba(15, 116, 117, 0.4);
		}

		.jsg-step-body {
			flex: 1 1 auto;
			min-width: 0;
			background: #ffffff;
			border: 1px solid var(--sg-line);
			border-radius: 14px;
			padding: 18px 20px;
			transition: box-shadow 0.25s ease, transform 0.25s ease;
		}

		.jsg-step-body:hover {
			box-shadow: 0 10px 24px rgba(15, 60, 65, 0.08);
			transform: translateY(-2px);
		}

		.jsg-step-body h2 {
			margin: 0 0 10px;
			font-size: 15.5px;
			font-weight: 700;
			color: var(--sg-ink);
		}

		.jsg-step-body p {
			margin: 0 0 8px;
			font-size: 13px;
			color: var(--sg-sub);
			line-height: 1.75;
		}

		.jsg-step-body p:last-child {
			margin-bottom: 0;
		}

		.jsg-rich-content { color: var(--sg-sub); font-size: 13px; line-height: 1.85; }
		.jsg-rich-content p { margin: 0 0 8px; color: inherit; font-size: inherit; }
		.jsg-rich-content p:last-child { margin-bottom: 0; }
		.jsg-rich-content ul, .jsg-rich-content ol { margin: 8px 0; padding-right: 22px; }
		.jsg-rich-content li { margin: 3px 0; }
		.jsg-rich-content strong { color: var(--sg-ink); }
		.jsg-rich-content a { color: var(--sg-teal); text-decoration: underline; }

		.jsg-callout {
			margin-top: 12px;
			border-radius: 10px;
			padding: 12px 14px;
			font-size: 12.5px;
			line-height: 1.7;
		}

		.jsg-callout p {
			margin: 0;
			color: inherit;
			font-size: inherit;
		}

		.jsg-callout-tip {
			background: #eef7f5;
			color: var(--sg-teal);
		}

		.jsg-callout-note {
			background: #eef4fb;
			color: #245a94;
		}

		.jsg-footer-cta {
			text-align: center;
			padding: 30px 0 10px;
		}

		.jsg-btn {
			display: inline-block;
			background: linear-gradient(135deg, var(--sg-orange1), var(--sg-orange2));
			color: #ffffff;
			text-decoration: none;
			font-size: 14px;
			font-weight: 700;
			padding: 13px 32px;
			border-radius: 14px;
			max-width: 100%;
			white-space: normal;
			word-break: break-word;
			box-shadow: 0 8px 18px rgba(194, 99, 9, 0.3);
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.jsg-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 12px 22px rgba(194, 99, 9, 0.38);
		}

		@media (max-width: 520px) {
			.jsg-step {
				gap: 12px;
			}
			.jsg-step-marker,
			.jsg-step-num {
				width: 30px;
				flex-basis: 30px;
				height: 30px;
				font-size: 12.5px;
			}
			.jsg-step:not(:last-child) .jsg-step-marker::after {
				top: 30px;
				right: 14px;
			}
			.jsg-step-body {
				padding: 14px 16px;
			}
			.jsg-hero h1 {
				font-size: 19px;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.jsg-step-num,
			.jsg-step-body,
			.jsg-btn {
				transition: none;
			}
		}
	</style>

	<div id="jluxe-step-guide">

		<header class="jsg-hero">
			<span class="jsg-eyebrow"><?php echo esc_html( $jluxe_sg_eyebrow ); ?></span>
			<h1><?php echo esc_html( $jluxe_sg_title ); ?></h1>
			<p><?php echo esc_html( $jluxe_sg_intro ); ?></p>
		</header>

		<div class="jsg-timeline">
			<?php for ( $i = 0; $i < 5; $i++ ) :
				$step = $jluxe_sg_steps[ $i ] ?? array();
				$step_title = str_replace( '{site_name}', $jluxe_sg_site_name, $step['title'] ?? '' );
				$step_content = str_replace( '{site_name}', $jluxe_sg_site_name, $step['content'] ?? '' );
				$callout_type = $step['callout_type'] ?? '';
				$callout_title = str_replace( '{site_name}', $jluxe_sg_site_name, $step['callout_title'] ?? '' );
				$callout_text = str_replace( '{site_name}', $jluxe_sg_site_name, $step['callout_text'] ?? '' );
			?>
			<div class="jsg-step">
				<div class="jsg-step-marker"><span class="jsg-step-num"><?php echo esc_html( $i + 1 ); ?></span></div>
				<div class="jsg-step-body">
					<h2><?php echo esc_html( $step_title ); ?></h2>
					<div class="jsg-rich-content"><?php echo wp_kses_post( $step_content ); ?></div>
					<?php if ( $callout_type && $callout_text ) : ?>
						<div class="jsg-callout <?php echo 'note' === $callout_type ? 'jsg-callout-note' : 'jsg-callout-tip'; ?>">
							<p><strong><?php echo esc_html( $callout_title ?: ( 'note' === $callout_type ? 'توجه' : 'نکته' ) ); ?>:</strong> <?php echo esc_html( $callout_text ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endfor; ?>
		</div>

		<div class="jsg-footer-cta">
			<?php if ( ! empty( $jluxe_sg['button_enabled'] ) ) : ?>
			<a href="<?php echo esc_url( $jluxe_sg_btn_url ); ?>" class="jsg-btn"<?php echo $jluxe_sg_btn_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $jluxe_sg_btn_text ); ?></a>
			<?php endif; ?>
		</div>

	</div>

	<script>
	(function () {
		"use strict";

		var CONTAINER_ID = "jluxe-step-guide";

		function init() {
			var container = document.getElementById(CONTAINER_ID);
			if (!container) {
				return;
			}
			if (container.getAttribute("data-jsg-init") === "1") {
				return;
			}
			container.setAttribute("data-jsg-init", "1");

			if (!window.IntersectionObserver) {
				return;
			}

			var steps = container.querySelectorAll(".jsg-step");

			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						var marker = entry.target.querySelector(".jsg-step-num");
						if (!marker) {
							return;
						}
						if (entry.isIntersecting) {
							marker.classList.add("jsg-active");
						} else {
							marker.classList.remove("jsg-active");
						}
					});
				},
				{ rootMargin: "-35% 0px -50% 0px", threshold: 0 }
			);

			steps.forEach(function (step) {
				observer.observe(step);
			});
		}

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
		} else {
			init();
		}
	})();
	</script>
</main>

<?php
get_footer();
