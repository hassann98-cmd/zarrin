<?php
/**
 * مرکز راهنمایی: متن‌های وضعیت‌وابسته باید با روش‌ها و سیاست‌های واقعی
 * ثبت‌شده توسط مدیر هماهنگ بمانند؛ این صفحه فقط به مسیرهای قابل‌بررسی لینک می‌دهد.
 */
$jluxe_site_name = function_exists( 'jluxe_get_setting' )
	? (string) jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) )
	: (string) get_bloginfo( 'name' );
if ( '' === trim( $jluxe_site_name ) ) {
	$jluxe_site_name = (string) get_bloginfo( 'name' );
}

get_header();
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-help-hub {
			--hh-ink: #1c2b2d;
			--hh-sub: #5b6a6b;
			--hh-line: #dbe7ec;
			--hh-teal: #0f7475;
			--hh-green: #059669;
			--hh-amber1: #c26309;
			--hh-amber2: #ea910a;

			box-sizing: border-box;
			max-width: 900px;
			margin: 28px auto 60px;
			padding: 0 clamp(16px, 6vw, 32px);
			direction: rtl;
			text-align: right;
			color: var(--hh-ink);
			font-size: 15px;
			line-height: 1.7;
		}

		#jluxe-help-hub *,
		#jluxe-help-hub *::before,
		#jluxe-help-hub *::after {
			box-sizing: inherit;
		}

		.jhh-hero {
			padding: 36px 0 30px;
			text-align: center;
		}

		.jhh-eyebrow {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.3px;
			color: var(--hh-green);
			margin-bottom: 8px;
		}

		.jhh-hero h1 {
			margin: 8px 0 12px;
			font-size: 22px;
			font-weight: 800;
			line-height: 1.5;
			color: var(--hh-ink);
		}

		.jhh-hero p {
			margin: 0 auto;
			max-width: 480px;
			font-size: 14px;
			color: var(--hh-sub);
			line-height: 1.85;
		}

		.jhh-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
			gap: 16px;
		}

		.jhh-card-wide {
			grid-column: 1 / -1;
		}

		.jhh-card {
			display: flex;
			flex-direction: column;
			background: #ffffff;
			border: 1px solid var(--hh-line);
			border-radius: 16px;
			min-width: 0;
			padding: 26px 24px;
			opacity: 1;
			transform: none;
			transition: box-shadow 0.3s ease, transform 0.3s ease, opacity 0.3s ease;
		}

		#jluxe-help-hub[data-jhh-animate="1"] .jhh-card:not(.jhh-visible) {
			opacity: 0;
			transform: translateY(14px);
		}

		.jhh-card.jhh-visible {
			opacity: 1;
			transform: translateY(0);
		}

		.jhh-card:hover {
			box-shadow: 0 14px 30px rgba(15, 60, 65, 0.1);
			transform: translateY(-4px);
		}

		.jhh-card.jhh-visible:hover {
			transform: translateY(-4px);
		}

		.jhh-card h2 {
			margin: 0 0 10px;
			font-size: 16px;
			font-weight: 800;
			color: var(--hh-ink);
			line-height: 1.5;
		}

		.jhh-card p {
			margin: 0 0 20px;
			font-size: 13px;
			color: var(--hh-sub);
			line-height: 1.8;
			flex-grow: 1;
		}

		.jhh-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			align-self: flex-start;
			background: linear-gradient(135deg, var(--hh-amber1), var(--hh-amber2));
			color: #ffffff;
			text-decoration: none;
			font-size: 13.5px;
			font-weight: 700;
			padding: 11px 26px;
			border-radius: 999px;
			box-shadow: 0 8px 18px rgba(194, 99, 9, 0.28);
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.jhh-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 12px 22px rgba(194, 99, 9, 0.36);
		}

		@media (max-width: 480px) {
			.jhh-card {
				padding: 20px 18px;
			}
			.jhh-hero h1 {
				font-size: 19px;
			}
			.jhh-btn {
				align-self: stretch;
				text-align: center;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.jhh-card {
				opacity: 1;
				transform: none;
				transition: box-shadow 0.2s ease;
			}
			.jhh-btn {
				transition: none;
			}
		}
	</style>

	<div id="jluxe-help-hub">

		<header class="jhh-hero">
			<span class="jhh-eyebrow">— مرکز راهنمایی</span>
			<h1>لیست صفحات و راهنمای <?php echo esc_html( $jluxe_site_name ); ?></h1>
			<p>راهنماهای موجود را مرور کنید و پیش از خرید، گزینه‌های درج‌شده در صفحهٔ محصول و تسویه‌حساب را بررسی کنید.</p>
		</header>

		<div class="jhh-grid">

			<div class="jhh-card">
				<h2>راهنمای خرید از سایت</h2>
				<p>مراحل عمومیِ انتخاب کالا و مرور سبد را بخوانید؛ گزینه‌های نهایی برای هر سفارش در تسویه‌حساب نمایش داده می‌شوند.</p>
				<a class="jhh-btn" href="<?php echo esc_url( home_url( '/shopping-guide/' ) ); ?>">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>روش‌ها و راهنمای پرداخت سفارشات</h2>
				<p>روش‌های پرداختِ قابل انتخاب برای سفارش خود را در تسویه‌حساب بررسی کنید؛ فقط از گزینه‌های ارائه‌شده در مسیر رسمی خرید استفاده کنید.</p>
				<a class="jhh-btn" href="<?php echo esc_url( home_url( '/payment-guide/' ) ); ?>">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>روش‌های ارسال و راهنمای پیگیری سفارشات</h2>
				<p>روش‌ها و هزینه‌های در دسترس را برای سبد و نشانیِ خود در تسویه‌حساب ببینید. صفحهٔ پیگیری، اطلاعات ثبت‌شدهٔ سفارش را نمایش می‌دهد.</p>
				<a class="jhh-btn" href="<?php echo esc_url( home_url( '/shipping-and-order-tracking/' ) ); ?>">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>رویهٔ شرایط مرجوعی و تعویض کالا</h2>
				<p>شرایط مرجوعی و تعویض باید توسط فروشگاه در صفحهٔ این رویه درج شود. پیش از خرید، متنِ ثبت‌شده را بررسی کنید.</p>
				<a class="jhh-btn" href="<?php echo esc_url( home_url( '/returns-and-exchanges/' ) ); ?>">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card jhh-card-wide">
				<h2>پیگیری سریع سفارش</h2>
				<p>صفحهٔ پیگیری، وضعیت و اطلاعاتی را نشان می‌دهد که برای سفارش ثبت شده‌اند؛ ممکن است برای احراز، شمارهٔ سفارش و شمارهٔ تماسِ ثبت‌شده درخواست شود.</p>
				<a class="jhh-btn" href="<?php echo esc_url( jluxe_route_url( 'track_order' ) ); ?>">پیگیری سفارش ←</a>
			</div>

			<div class="jhh-card jhh-card-wide">
				<h2>تماس با ما</h2>
				<p>راه‌های تماس و ساعات پاسخ‌گویی، در صورت ثبت، در صفحهٔ تماس با ما نمایش داده می‌شوند.</p>
				<a class="jhh-btn" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">تماس با ما ←</a>
			</div>

		</div>

	</div>

	<script>
	(function () {
		"use strict";

		var CONTAINER_ID = "jluxe-help-hub";

		function init() {
			var container = document.getElementById(CONTAINER_ID);
			if (!container) {
				return;
			}
			if (container.getAttribute("data-jhh-init") === "1") {
				return;
			}
			container.setAttribute("data-jhh-init", "1");

			var cards = container.querySelectorAll(".jhh-card");

			var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
			if (!window.IntersectionObserver || reduceMotion) {
				cards.forEach(function (card) {
					card.classList.add("jhh-visible");
				});
				return;
			}

			container.setAttribute("data-jhh-animate", "1");
			try {
				var observer = new IntersectionObserver(
					function (entries) {
						entries.forEach(function (entry) {
							if (entry.isIntersecting) {
								entry.target.classList.add("jhh-visible");
								observer.unobserve(entry.target);
							}
						});
					},
					{ threshold: 0.15 }
				);

				cards.forEach(function (card, index) {
					card.style.transitionDelay = (index * 60) + "ms";
					observer.observe(card);
				});
			} catch (error) {
				container.removeAttribute("data-jhh-animate");
				cards.forEach(function (card) {
					card.classList.add("jhh-visible");
				});
			}
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
