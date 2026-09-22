<?php
/**
 * صفحه‌ی «پیگیری سفارش» (اسلاگ track-order). فرانت‌اند و بک‌اند دقیقاً
 * همون منطق واقعیِ فعال روی jluxe.ir (script.js بدون تغییر منطقی؛
 * بک‌اند در inc/order-tracking.php). فقط یک بخش «شیم CSS» اضافه شده:
 * script.js اصلی از کلاس‌های عمومی Tailwind قالب Boom استفاده می‌کرد
 * (چون آن‌ها از قبل در بیلد Boom کامپایل شده بودن)؛ چون بیلد Tailwind
 * قالب JLuxe جدا و purge‌شده‌ست و این کلاس‌های عمومی توش نیستن، این‌جا
 * با مقادیر واقعیِ استاندارد Tailwind (نه رنگ اختصاصیِ Boom) بازتعریف
 * شدن — رنگ اصلی (bg-primary/text-primary) از توکن واقعیِ رنگ قالب
 * خودمون (hsl(var(--primary))) میاد تا با تنظیمات رنگ پوسته هماهنگ بمونه.
 */

get_header();
$jluxe_to_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_to_guide = $jluxe_to_settings['guide_pages']['track_order'] ?? array();
$jluxe_to_title = $jluxe_to_guide['form_title'] ?? 'پیگیری سفارش';
$jluxe_to_note = $jluxe_to_guide['form_note'] ?? '';
?>

<main id="primary" class="site-main">
	<style>
		#jluxe-track-order-app {
			direction: rtl;
			max-width: 560px;
			margin: 28px auto 60px;
			padding: 0 16px;
		}

		@media (min-width: 640px) {
			#jluxe-track-order-app {
				padding: 0;
			}
		}

		@media (max-width: 380px) {
			#jluxe-track-order-app {
				margin-top: 16px;
				margin-bottom: 40px;
			}
			.jto-outer-card {
				padding: 16px;
			}
		}

		#jluxe-track-order-app * {
			box-sizing: border-box;
		}

		#jluxe-track-order-app img,
		#jluxe-track-order-app svg {
			max-width: 100%;
		}

		.jto-row-wrap {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 6px 12px;
		}

		.jto-tracking-code-text {
			word-break: break-all;
		}

		.jto-outer-card {
			background: #ffffff !important;
			border-radius: 14px !important;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
			padding: 24px !important;
		}

		.dark .jto-outer-card {
			background: #111827 !important;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35) !important;
		}

		.jto-field-error {
			display: none;
			align-items: flex-start;
			gap: 6px;
			font-size: 12.5px;
			line-height: 1.7;
			color: #dc2626;
			background: #fef2f2;
			padding: 9px 11px;
			border-radius: 10px;
		}

		.dark .jto-field-error {
			background: rgba(220, 38, 38, 0.15);
			color: #f87171;
		}

		@keyframes jto-fade-in {
			from {
				opacity: 0;
				transform: translateY(12px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.jto-animate-in {
			animation: jto-fade-in 0.4s ease both;
		}

		.jto-field-box {
			transition: box-shadow 0.2s;
		}

		.jto-field-box:focus-within {
			box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.14);
		}

		.dark .jto-field-box:focus-within {
			box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.18);
		}

		.jto-field-label {
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.jto-field-label svg {
			opacity: 0.65;
			flex-shrink: 0;
		}

		.jto-input {
			outline: none;
			border: none;
			transition: box-shadow 0.2s;
			font-family: inherit;
			font-size: 16px;
			letter-spacing: 0.3px;
		}

		.jto-input-numeric {
			direction: ltr;
			text-align: right;
		}

		.jto-alert-icon-circle {
			width: 52px;
			height: 52px;
			border-radius: 9999px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 22px;
			margin: 0 auto 14px;
		}

		.jto-alert-notfound {
			background: #eff6ff;
		}
		.jto-alert-ratelimit {
			background: #fffbeb;
		}
		.jto-alert-network {
			background: #f3f4f6;
		}
		.jto-alert-generic {
			background: #fef2f2;
		}

		.dark .jto-alert-notfound {
			background: rgba(59, 130, 246, 0.15);
		}
		.dark .jto-alert-ratelimit {
			background: rgba(217, 119, 6, 0.18);
		}
		.dark .jto-alert-network {
			background: rgba(107, 114, 128, 0.25);
		}
		.dark .jto-alert-generic {
			background: rgba(220, 38, 38, 0.15);
		}

		.jto-spinner {
			display: inline-block;
			width: 16px;
			height: 16px;
			border: 2px solid rgba(255, 255, 255, 0.4);
			border-top-color: #fff;
			border-radius: 9999px;
			animation: jto-spin 0.7s linear infinite;
			vertical-align: middle;
		}

		@keyframes jto-spin {
			to {
				transform: rotate(360deg);
			}
		}

		.jto-skel {
			background: linear-gradient(90deg, #eceaf4 25%, #f5f4fa 37%, #eceaf4 63%);
			background-size: 400% 100%;
			animation: jto-shimmer 1.4s ease infinite;
			border-radius: 8px;
		}

		.dark .jto-skel {
			background: linear-gradient(90deg, #374151 25%, #4b5563 37%, #374151 63%);
			background-size: 400% 100%;
		}

		@keyframes jto-shimmer {
			0% {
				background-position: 100% 50%;
			}
			100% {
				background-position: 0 50%;
			}
		}

		.jto-chip {
			display: inline-block;
			font-size: 11px;
			padding: 3px 9px;
			border-radius: 9999px;
			background: #eceaf4;
			color: #6b7280;
		}

		.dark .jto-chip {
			background: #374151;
			color: #d1d5db;
		}

		.jto-item-image {
			display: block;
		}

		/* =========================================================
		   شیمِ کلاس‌های عمومیِ Tailwind — چون بیلد Tailwind خودِ قالب
		   JLuxe جدا و purge‌شده‌ست، این کلاس‌ها که script.js تولید
		   می‌کنه رو با مقادیر استاندارد Tailwind این‌جا واقعی می‌کنیم.
		   ========================================================= */
		#jluxe-track-order-app .flex { display: flex; }
		#jluxe-track-order-app .flex-col { flex-direction: column; }
		#jluxe-track-order-app .flex-1 { flex: 1 1 0%; }
		#jluxe-track-order-app .items-center { align-items: center; }
		#jluxe-track-order-app .justify-center { justify-content: center; }
		#jluxe-track-order-app .w-full { width: 100%; }
		#jluxe-track-order-app .w-1\/3 { width: 33.3333%; }
		#jluxe-track-order-app .w-2\/5 { width: 40%; }
		#jluxe-track-order-app .w-3\/5 { width: 60%; }
		#jluxe-track-order-app .w-10 { width: 2.5rem; }
		#jluxe-track-order-app .h-3 { height: 0.75rem; }
		#jluxe-track-order-app .h-4 { height: 1rem; }
		#jluxe-track-order-app .h-10 { height: 2.5rem; }
		#jluxe-track-order-app .h-full { height: 100%; }
		#jluxe-track-order-app .gap-2 { gap: 0.5rem; }
		#jluxe-track-order-app .gap-3 { gap: 0.75rem; }
		#jluxe-track-order-app .gap-5 { gap: 1.25rem; }
		#jluxe-track-order-app .mb-2 { margin-bottom: 0.5rem; }
		#jluxe-track-order-app .mb-3 { margin-bottom: 0.75rem; }
		#jluxe-track-order-app .mb-4 { margin-bottom: 1rem; }
		#jluxe-track-order-app .mt-1 { margin-top: 0.25rem; }
		#jluxe-track-order-app .mt-3 { margin-top: 0.75rem; }
		#jluxe-track-order-app .mt-4 { margin-top: 1rem; }
		#jluxe-track-order-app .p-3 { padding: 0.75rem; }
		#jluxe-track-order-app .p-4 { padding: 1rem; }
		#jluxe-track-order-app .p-5 { padding: 1.25rem; }
		#jluxe-track-order-app .p-6 { padding: 1.5rem; }
		#jluxe-track-order-app .rounded-lg { border-radius: 0.5rem; }
		#jluxe-track-order-app .rounded-xl { border-radius: 0.75rem; }
		#jluxe-track-order-app .rounded-2xl { border-radius: 1rem; }
		#jluxe-track-order-app .rounded-full { border-radius: 9999px; }
		#jluxe-track-order-app .rounded-\[12px\] { border-radius: 12px; }
		#jluxe-track-order-app .rounded-\[10px\] { border-radius: 10px; }
		#jluxe-track-order-app .font-bold { font-weight: 700; }
		#jluxe-track-order-app .font-medium { font-weight: 500; }
		#jluxe-track-order-app .text-xs { font-size: 0.75rem; }
		#jluxe-track-order-app .text-sm { font-size: 0.875rem; }
		#jluxe-track-order-app .text-base { font-size: 1rem; }
		#jluxe-track-order-app .text-xl { font-size: 1.25rem; }
		#jluxe-track-order-app .text-medium { font-size: 0.9375rem; }
		#jluxe-track-order-app .text-\[12px\] { font-size: 12px; }
		#jluxe-track-order-app .text-\[14px\] { font-size: 14px; }
		#jluxe-track-order-app .text-center { text-align: center; }
		#jluxe-track-order-app .overflow-hidden { overflow: hidden; }
		#jluxe-track-order-app .cursor-pointer { cursor: pointer; }
		#jluxe-track-order-app .transition-colors { transition: background-color .2s, color .2s, border-color .2s; }
		#jluxe-track-order-app .hover\:opacity-90:hover { opacity: .9; }
		#jluxe-track-order-app .space-y-2 > * + * { margin-top: 0.5rem; }
		#jluxe-track-order-app .border { border: 1px solid hsl(var(--border)); }
		#jluxe-track-order-app .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1); }
		#jluxe-track-order-app .bg-white { background-color: #ffffff; }
		#jluxe-track-order-app .bg-gray-50 { background-color: #f9fafb; }
		#jluxe-track-order-app .bg-\[\#f7f8fa\] { background-color: #f7f8fa; }
		#jluxe-track-order-app .text-gray-500 { color: #6b7280; }
		#jluxe-track-order-app .text-gray-600 { color: #4b5563; }
		#jluxe-track-order-app .bg-white\/20 { background-color: rgba(255,255,255,.2); }
		#jluxe-track-order-app .bg-default-300\/50 { background-color: rgba(0,0,0,.08); }
		#jluxe-track-order-app .bg-primary { background-color: hsl(var(--primary)); }
		#jluxe-track-order-app .text-primary { color: hsl(var(--primary)); }
		#jluxe-track-order-app .bg-primary-200 { background-color: hsl(var(--primary) / 0.15); }
		#jluxe-track-order-app .text-white { color: #ffffff; }

		.dark #jluxe-track-order-app .bg-white,
		.dark #jluxe-track-order-app .dark\:bg-gray-900 { background-color: #111827; }
		.dark #jluxe-track-order-app .dark\:bg-gray-800 { background-color: #1f2937; }
		.dark #jluxe-track-order-app .dark\:text-gray-300 { color: #d1d5db; }
		.dark #jluxe-track-order-app .dark\:text-gray-400 { color: #9ca3af; }
		.dark #jluxe-track-order-app .dark\:border-gray-700 { border-color: #374151; }
		.dark #jluxe-track-order-app .dark\:shadow-gray-900\/50 { box-shadow: 0 10px 15px -3px rgba(17,24,39,.5), 0 4px 6px -4px rgba(17,24,39,.5); }
	</style>

	<div id="jluxe-track-order-app" class="jto-app" dir="rtl">
		<noscript>
			<p style="text-align:center;padding:40px 16px;font-family:tahoma,sans-serif;">
				برای استفاده از صفحهٔ پیگیری سفارش، لطفاً جاوااسکریپت مرورگر خود را فعال کنید.
			</p>
		</noscript>
	</div>

	<script>
	(function () {
		"use strict";

		var CONTAINER_ID = "jluxe-track-order-app";
		var API_ENDPOINT = "/wp-json/jluxe/v1/order-track";
		var STORE_URL = "/";
		var LAST_ORDER_STORAGE_KEY = "jluxe_track_last_order_number";

		var CARRIERS = {
			"پست": { url: "https://tracking.post.ir/?id=%CODE%", color: "#fcba24" },
			"تیپاکس": { url: "https://tipaxco.com/tracking?code=%CODE%", color: "#0ea5e9" },
			"چاپار": { url: "https://www.chapar.io/tracking/%CODE%", color: "#16a34a" }
		};

		function init() {
			var container = document.getElementById(CONTAINER_ID);
			if (!container) {
				return;
			}
			if (container.getAttribute("data-jto-init") === "1") {
				return;
			}
			container.setAttribute("data-jto-init", "1");
			renderSearchView(container);
		}

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
		} else {
			init();
		}

		function toPersianDigits(input) {
			var fa = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
			return String(input).replace(/[0-9]/g, function (d) {
				return fa[d];
			});
		}

		function escapeHtml(str) {
			if (str === null || str === undefined) {
				return "";
			}
			return String(str)
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;")
				.replace(/'/g, "&#039;");
		}

		function qs(root, selector) {
			return root.querySelector(selector);
		}

		function qsa(root, selector) {
			return Array.prototype.slice.call(root.querySelectorAll(selector));
		}

		function saveLastOrderNumber(value) {
			try {
				if (window.localStorage && value) {
					window.localStorage.setItem(LAST_ORDER_STORAGE_KEY, value);
				}
			} catch (e) {}
		}

		function getLastOrderNumber() {
			try {
				return (window.localStorage && window.localStorage.getItem(LAST_ORDER_STORAGE_KEY)) || "";
			} catch (e) {
				return "";
			}
		}

		function renderSearchView(container) {
			var lastOrderNumber = getLastOrderNumber();

			container.innerHTML =
				'<div id="jto-search-wrap">' +
				'  <div class="jto-outer-card jto-animate-in w-full bg-white dark:bg-gray-900 shadow-lg dark:shadow-gray-900/50 rounded-lg p-6">' +
				'    <h2 class="font-bold text-xl mb-3">پیگیری سفارش</h2>' +
				'    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">شماره سفارش و شماره موبایل ثبت‌شده در سفارش را وارد کنید</p>' +
				'    <form id="jto-search-form" novalidate>' +
				'      <div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl jto-field-box">' +
				'        <label for="jto-order-number" class="jto-field-label text-sm text-gray-600 dark:text-gray-300">' +
				'          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6"/></svg>' +
				"          شماره سفارش" +
				"        </label>" +
				'        <input class="jto-input jto-input-numeric w-full bg-transparent mt-1 font-bold" type="text" inputmode="numeric" dir="ltr" id="jto-order-number" placeholder="مثلاً 1024" autocomplete="off" value="' + escapeHtml(lastOrderNumber) + '" required />' +
				"      </div>" +
				'      <div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl jto-field-box">' +
				'        <label for="jto-phone" class="jto-field-label text-sm text-gray-600 dark:text-gray-300">' +
				'          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>' +
				"          شماره موبایل" +
				"        </label>" +
				'        <input class="jto-input jto-input-numeric w-full bg-transparent mt-1 font-bold" type="tel" inputmode="numeric" dir="ltr" id="jto-phone" placeholder="09xxxxxxxxx" autocomplete="off" required />' +
				"      </div>" +
				'      <div id="jto-field-error" class="jto-field-error mb-3" style="display:none;"></div>' +
				'      <button type="submit" class="w-full bg-primary text-white rounded-xl p-3 font-bold flex items-center justify-center gap-2" id="jto-submit-btn">' +
				"        <span>پیگیری سفارش</span>" +
				"      </button>" +
				"    </form>" +
				"  </div>" +
				"</div>" +
				'<div id="jto-result-area" class="mt-3"></div>';

			qs(container, "#jto-search-form").addEventListener("submit", function (e) {
				e.preventDefault();
				handleSearchSubmit(container);
			});

			var orderNumberField = qs(container, "#jto-order-number");
			if (orderNumberField) {
				orderNumberField.focus();
				if (lastOrderNumber) {
					orderNumberField.select();
				}
			}
		}

		function handleSearchSubmit(container) {
			var orderNumberInput = qs(container, "#jto-order-number");
			var phoneInput = qs(container, "#jto-phone");
			var submitBtn = qs(container, "#jto-submit-btn");
			var resultArea = qs(container, "#jto-result-area");
			var fieldError = qs(container, "#jto-field-error");

			var orderNumber = (orderNumberInput.value || "").trim();
			var phone = (phoneInput.value || "").trim();
			var phoneDigitsOnly = phone.replace(/\D/g, "");

			if (fieldError) {
				fieldError.style.display = "none";
				fieldError.innerHTML = "";
			}

			if (!orderNumber) {
				showFieldError(fieldError, "لطفاً شماره سفارش را وارد کنید.");
				orderNumberInput.focus();
				return;
			}
			if (!phone) {
				showFieldError(fieldError, "لطفاً شماره موبایل را وارد کنید.");
				phoneInput.focus();
				return;
			}
			if (phoneDigitsOnly.length < 10) {
				showFieldError(fieldError, "شماره موبایل واردشده کامل نیست؛ لطفاً دوباره بررسی کنید.");
				phoneInput.focus();
				return;
			}

			setButtonLoading(submitBtn, true);
			renderSkeletonLoader(resultArea);

			var url =
				API_ENDPOINT +
				"?order_number=" +
				encodeURIComponent(orderNumber) +
				"&phone=" +
				encodeURIComponent(phone);

			fetch(url, { method: "GET", headers: { Accept: "application/json" } })
				.then(function (response) {
					return response.json().then(function (json) {
						return { ok: response.ok, status: response.status, body: json };
					});
				})
				.then(function (result) {
					setButtonLoading(submitBtn, false);

					if (!result.ok || !result.body || result.body.success !== true) {
						var message =
							(result.body && result.body.message) ||
							"سفارشی با این مشخصات یافت نشد. لطفاً اطلاعات وارد شده را بررسی کنید.";
						var type = result.status === 429 ? "ratelimit" : "notfound";
						renderErrorMessage(resultArea, message, type, container);
						return;
					}

					saveLastOrderNumber(orderNumber);
					renderOrderResult(container, result.body.data);
				})
				.catch(function () {
					setButtonLoading(submitBtn, false);
					renderErrorMessage(
						resultArea,
						"خطا در برقراری ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی و دوباره تلاش کنید.",
						"network",
						container
					);
				});
		}

		function showFieldError(fieldErrorEl, message) {
			if (!fieldErrorEl) {
				return;
			}
			fieldErrorEl.innerHTML =
				'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>' +
				"<span>" + escapeHtml(message) + "</span>";
			fieldErrorEl.style.display = "flex";
		}

		function setButtonLoading(btn, isLoading) {
			if (isLoading) {
				btn.disabled = true;
				btn.innerHTML = '<span class="jto-spinner"></span><span>در حال جست‌وجو...</span>';
			} else {
				btn.disabled = false;
				btn.innerHTML = "<span>پیگیری سفارش</span>";
			}
		}

		function renderSkeletonLoader(resultArea) {
			resultArea.innerHTML =
				'<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">' +
				'  <div class="jto-skel h-4 w-2/5 mb-2"></div>' +
				'  <div class="jto-skel h-3 w-3/5"></div>' +
				"</div>" +
				'<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">' +
				'  <div class="jto-skel h-4 w-1/3"></div>' +
				"</div>";
		}

		function renderErrorMessage(resultArea, message, type, container) {
			var icons = {
				notfound: "🔍",
				ratelimit: "⏳",
				network: "📡",
				generic: "⚠️"
			};
			var icon = icons[type] || icons.generic;
			var circleClass = "jto-alert-icon-circle jto-alert-" + (type || "generic");

			resultArea.innerHTML =
				'<div class="jto-animate-in bg-[#f7f8fa] dark:bg-gray-800 p-6 rounded-xl text-center">' +
				'  <div class="' + circleClass + '">' + icon + "</div>" +
				'  <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">' + escapeHtml(message) + "</p>" +
				'  <div class="flex items-center justify-center gap-5">' +
				(container ? '    <button type="button" id="jto-retry-btn" class="text-sm text-primary font-bold">تلاش مجدد</button>' : "") +
				'    <a href="' + escapeHtml(STORE_URL) + '" class="text-sm text-gray-500 dark:text-gray-400">بازگشت به فروشگاه</a>' +
				"  </div>" +
				"</div>";

			if (container) {
				var retryBtn = qs(resultArea, "#jto-retry-btn");
				if (retryBtn) {
					retryBtn.addEventListener("click", function () {
						var phoneField = qs(container, "#jto-phone");
						resultArea.innerHTML = "";
						if (phoneField) {
							phoneField.focus();
						}
					});
				}
			}
		}

		function renderOrderResult(container, data) {
			var order = data.order || {};
			var customer = data.customer || {};
			var shipping = data.shipping || {};
			var items = data.items || [];
			var timeline = data.timeline || { steps: [], is_cancelled: false, current_step: 1 };

			var jalaliDate = (order.created_date && order.created_date.jalali) || "";
			var totalFormatted = (order.total && order.total.formatted) || "";
			var percent = Math.round(((timeline.current_step || 1) / 6) * 100);
			var isCancelled = !!timeline.is_cancelled;

			var html = '<div class="jto-outer-card jto-animate-in w-full bg-white dark:bg-gray-900 shadow-lg dark:shadow-gray-900/50 rounded-lg p-6">';

			html += '<button type="button" id="jto-back-to-search" class="mb-4 text-sm text-primary">بازگشت</button>';
			html += '<h2 class="font-bold text-xl mb-3">سفارش #' + escapeHtml(toPersianDigits(order.order_number || "")) + "</h2>";

			html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">';
			html += '  <div class="flex flex-col gap-2 w-full">';
			html += '    <div class="jto-row-wrap">';
			html += '      <span class="text-medium">' + escapeHtml(isCancelled ? (timeline.cancel_label || order.status_label) : order.status_label) + "</span>";
			html += '      <span class="text-sm text-gray-600 dark:text-gray-300">' + escapeHtml(toPersianDigits(jalaliDate)) + "</span>";
			html += "    </div>";
			if (!isCancelled) {
				html += '    <div class="bg-default-300/50 overflow-hidden h-3 rounded-full">';
				html += '      <div class="h-full bg-primary rounded-full" style="width:' + percent + '%;transition:width .5s;"></div>';
				html += "    </div>";
			}
			html += "  </div>";
			html += "</div>";

			html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">مبلغ کل: <span class="font-bold">' + escapeHtml(totalFormatted) + "</span></div>";

			if (order.payment_method) {
				html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">روش پرداخت: ' + escapeHtml(toPersianDigits(order.payment_method)) + "</div>";
			}

			if (shipping.tracking_code) {
				html += '<div class="mb-3 bg-primary-200 p-3 rounded-xl jto-row-wrap">';
				html += '  <span class="jto-tracking-code-text">کد رهگیری: <span class="font-bold">' + escapeHtml(shipping.tracking_code) + '</span></span>';
				html += '  <button type="button" class="text-xs font-bold" data-copy="' + escapeHtml(shipping.tracking_code) + '">کپی</button>';
				html += "</div>";

				var carrier = getCarrierInfo(shipping.shipping_company, shipping.tracking_code);
				if (carrier) {
					html +=
						'<a href="' + escapeHtml(carrier.url) + '" target="_blank" rel="noopener noreferrer" ' +
						'class="mb-3 flex items-center gap-3 rounded-[12px] p-4 hover:opacity-90 transition-colors cursor-pointer" ' +
						'style="background-color:' + escapeHtml(carrier.color) + ';">' +
						'  <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-[10px]">' +
						'    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 100-4 2 2 0 000 4zM18 20a2 2 0 100-4 2 2 0 000 4z"/></svg>' +
						"  </div>" +
						'  <div class="flex flex-col flex-1">' +
						'    <span class="text-[14px] font-bold text-white">پیگیری سفارش</span>' +
						'    <span class="text-[12px] text-white">پیگیری مرسوله از طریق ' + escapeHtml(toPersianDigits(shipping.shipping_company || "شرکت حمل")) + "</span>" +
						"  </div>" +
						"</a>";
				}
			}

			html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">نام: ' + escapeHtml(customer.full_name || "-") + "</div>";
			if (customer.address) {
				html += '<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">آدرس: ' + escapeHtml(toPersianDigits(customer.address)) + "</div>";
			}
			if (customer.phone) {
				html +=
					'<div class="mb-3 bg-[#f7f8fa] dark:bg-gray-800 p-3 rounded-xl">تلفن: <a href="tel:' +
					escapeHtml(customer.phone) +
					'" class="text-primary font-bold">' +
					escapeHtml(toPersianDigits(customer.phone)) +
					"</a></div>";
			}

			if (items.length) {
				html += '<h3 class="font-bold mt-4 mb-3">محصولات سفارش</h3>';
				html += '<ul class="space-y-2">';
				items.forEach(function (item) {
					var thumbUrl = (item.image && item.image.thumbnail) || "";
					var itemSubtotal = (item.subtotal && item.subtotal.formatted) || "";
					var attrsText = "";
					if (item.attributes && item.attributes.length) {
						attrsText = item.attributes
							.map(function (a) {
								return toPersianDigits(a.name) + ": " + toPersianDigits(a.value);
							})
							.join(" | ");
					}

					html += '<li class="border dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-800 p-5">';
					html += '  <div class="flex gap-3 mb-3">';
					if (thumbUrl) {
						html +=
							'    <img src="' + escapeHtml(thumbUrl) + '" alt="' + escapeHtml(item.product_name) +
							'" class="jto-item-image" style="width:56px;height:56px;border-radius:12px;object-fit:cover;flex-shrink:0;" />';
					}
					html += '    <div class="flex-1">';
					html += '      <span class="text-sm text-gray-600 dark:text-gray-300">نام محصول: </span>';
					html += '      <div class="font-bold text-base mt-1">' + escapeHtml(toPersianDigits(item.product_name)) + "</div>";
					if (attrsText) {
						html += '      <div class="jto-chip mt-1">' + escapeHtml(attrsText) + "</div>";
					}
					html += "    </div>";
					html += "  </div>";
					html += '  <div class="jto-row-wrap text-sm">';
					html += '    <span>تعداد: <span class="font-medium">' + escapeHtml(toPersianDigits(item.quantity)) + '</span></span>';
					html += '    <span>قیمت: <span class="font-bold text-primary">' + escapeHtml(itemSubtotal) + "</span></span>";
					html += "  </div>";
					html += "</li>";
				});
				html += "</ul>";
			}

			html += "</div>";

			var resultArea = qs(container, "#jto-result-area");
			resultArea.innerHTML = html;

			var searchWrap = qs(container, "#jto-search-wrap");
			if (searchWrap) {
				searchWrap.style.display = "none";
			}

			qs(resultArea, "#jto-back-to-search").addEventListener("click", function () {
				resultArea.innerHTML = "";
				if (searchWrap) {
					searchWrap.style.display = "";
				}
			});

			bindResultEvents(resultArea);
		}

		function getCarrierInfo(companyName, trackingCode) {
			if (!companyName || !trackingCode) {
				return null;
			}
			var carrier = CARRIERS[companyName.trim()];
			if (!carrier) {
				return null;
			}
			return {
				url: carrier.url.replace("%CODE%", encodeURIComponent(trackingCode)),
				color: carrier.color || "#7c3aed"
			};
		}

		function bindResultEvents(resultArea) {
			qsa(resultArea, ".jto-item-image").forEach(function (img) {
				img.addEventListener("error", function () {
					img.style.display = "none";
				});
			});

			qsa(resultArea, "[data-copy]").forEach(function (btn) {
				btn.addEventListener("click", function () {
					var text = btn.getAttribute("data-copy");
					copyToClipboard(text)
						.then(function () {
							var original = btn.textContent;
							btn.textContent = "کپی شد ✓";
							setTimeout(function () {
								btn.textContent = original;
							}, 1800);
						})
						.catch(function () {});
				});
			});
		}

		function copyToClipboard(text) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				return navigator.clipboard.writeText(text);
			}
			return new Promise(function (resolve, reject) {
				try {
					var textarea = document.createElement("textarea");
					textarea.value = text;
					textarea.style.position = "fixed";
					textarea.style.opacity = "0";
					document.body.appendChild(textarea);
					textarea.focus();
					textarea.select();
					document.execCommand("copy");
					document.body.removeChild(textarea);
					resolve();
				} catch (err) {
					reject(err);
				}
			});
		}
	})();
	</script>
</main>

<?php
get_footer();
