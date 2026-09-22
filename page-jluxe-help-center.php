<?php
/**
 * صفحه‌ی «مرکز راهنمایی» (اسلاگ jluxe-help-center — دقیقاً همون اسلاگِ
 * واقعیِ فعلیِ jluxe.ir، نه «help-hub»؛ بررسی‌شده زنده روی سایت واقعی).
 * لینک‌های داخلی نسخهٔ اصلی به‌صورت مطلق (https://jluxe.ir/...) بودن؛
 * این‌جا به مسیر نسبی تبدیل شدن تا در پوسته‌ی جدید به خودِ همین سایت
 * لینک بدن، نه به دامنه‌ی خارجی.
 */

/*
 * طبقِ درخواستِ صریحِ کاربر: هر جا نامِ برند («زرین») توی متنِ این صفحات
 * هارد-کد بود، باید از تنظیماتِ پوسته خونده بشه — چون این صفحات عیناً از
 * jluxe.ir کپی شده بودن و همون‌جا «زرین» جا مونده بود؛ برای سایتی مثلِ
 * noghrehmilad.ir که اسمِ واقعیش چیزِ دیگه‌ایه، این متنِ ثابت اشتباهه.
 */
$jluxe_site_name = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', 'زرین' ) : 'زرین';

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
			grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
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
			padding: 26px 24px;
			opacity: 0;
			transform: translateY(14px);
			transition: box-shadow 0.3s ease, transform 0.3s ease, opacity 0.3s ease;
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
			<p>همهٔ آنچه برای خرید، پرداخت، ارسال، مرجوعی و پیگیری سفارش نیاز دارید، یک‌جا در همین صفحه.</p>
		</header>

		<div class="jhh-grid">

			<div class="jhh-card">
				<h2>راهنمای خرید از سایت</h2>
				<p>آموزش گام‌به‌گام و تصویری مراحل پیدا‌کردن محصول، افزودن به سبد خرید و ثبت آسان سفارش در <?php echo esc_html( $jluxe_site_name ); ?>.</p>
				<a class="jhh-btn" href="/shopping-guide/">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>روش‌ها و راهنمای پرداخت سفارشات</h2>
				<p>آشنایی با شیوه‌های پرداخت امن آنلاین (درگاه بانکی) و راهنمای واریز به‌صورت کارت‌به‌کارت یا شبا.</p>
				<a class="jhh-btn" href="/payment-guide/">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>روش‌های ارسال و راهنمای پیگیری سفارشات</h2>
				<p>بررسی گزینه‌های ارسال (پست، تیپاکس و چاپار)، هزینه‌ها، قوانین بسته‌های شکستنی و آشنایی با وضعیت‌های سفارش.</p>
				<a class="jhh-btn" href="/shipping-and-order-tracking/">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card">
				<h2>رویهٔ شرایط مرجوعی و تعویض کالا</h2>
				<p>قوانین مربوط به مهلت ۷ روزهٔ تست کالا، استثنائات بهداشتی و ضوابط تعویض کالاهای آسیب‌دیده.</p>
				<a class="jhh-btn" href="/returns-and-exchanges/">مشاهدهٔ راهنما ←</a>
			</div>

			<div class="jhh-card jhh-card-wide">
				<h2>پیگیری سریع سفارش</h2>
				<p>مشاهدهٔ آنی و لحظه‌ای وضعیت بستهٔ خود، تنها با واردکردن شمارهٔ سفارش و شمارهٔ موبایل — بدون نیاز به ورود به حساب کاربری.</p>
				<a class="jhh-btn" href="/track-order/">پیگیری سفارش ←</a>
			</div>

			<div class="jhh-card jhh-card-wide">
				<h2>تماس با ما</h2>
				<p>راه‌های ارتباط مستقیم با پشتیبانی <?php echo esc_html( $jluxe_site_name ); ?>، شمارهٔ تماس، ساعات پاسخگویی و شبکه‌های اجتماعی.</p>
				<a class="jhh-btn" href="/contact-us/">تماس با ما ←</a>
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

			if (!window.IntersectionObserver) {
				cards.forEach(function (card) {
					card.classList.add("jhh-visible");
				});
				return;
			}

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
