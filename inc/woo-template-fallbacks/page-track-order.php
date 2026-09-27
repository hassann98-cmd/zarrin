<?php
/** Order-tracking view; maintained client in assets/js/order-tracking.js. */

wp_enqueue_script( 'jluxe-order-tracking', JLUXE_THEME_URI . '/assets/js/order-tracking.js', array( 'jluxe-storefront-utils' ), (string) filemtime( JLUXE_THEME_DIR . '/assets/js/order-tracking.js' ), true );
wp_add_inline_script( 'jluxe-order-tracking', 'window.JLuxeOrderTracking = ' . wp_json_encode( array(
	'endpoint'   => rest_url( 'jluxe/v1/order-track' ),
	'sessionUrl' => admin_url( 'admin-ajax.php' ),
	'storeUrl'   => jluxe_shop_url(),
	'accountUrl' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
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


</main>

<?php
get_footer();
