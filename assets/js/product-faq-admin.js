/**
 * متاباکسِ «سوالات متداول محصول» (inc/product-faq.php) — افزودن/حذف/
 * جابه‌جاییِ ردیف‌های سوال/پاسخ توی صفحه‌ی ویرایشِ محصول. فقط همین یک
 * متاباکس رو پوشش می‌ده (نه کلِ theme-settings-admin.js که فقط توی صفحه‌ی
 * تنظیماتِ تم enqueue می‌شه، نه صفحه‌ی ویرایشِ محصول) — الگوی مشابهِ
 * renumberRepeater اونجا، ولی مستقل و سبک.
 *
 * چون تابعِ ذخیره‌سازیِ سمتِ PHP (jluxe_save_product_faq_meta) فقط روی
 * VALUE هایِ آرایه foreach می‌کنه (نه روی KEY)، دقیقِ‌بودنِ ایندکسِ عددیِ
 * name="jluxe_product_faq[N][question]" اصلاً مهم نیست — فقط باید هر
 * ردیف یک نامِ منحصربه‌فرد داشته باشه که با ردیف‌های دیگه قاطی نشه؛ برای
 * همین ردیف‌های تازه با یک شمارنده‌ی یکتا (Date.now()) نام‌گذاری می‌شن،
 * نه با renumber کردنِ دقیقِ کل لیست.
 */
(function ($) {
	"use strict";

	if (!$) {
		return;
	}

	$(function () {
		var $wrap = $("#jluxe-faq-items");
		var $tpl = $("#jluxe-faq-item-template");
		if (!$wrap.length || !$tpl.length) {
			return;
		}

		function relabelItems() {
			$wrap.children(".jluxe-faq-item").each(function (index) {
				$(this)
					.find(".jluxe-faq-item-title")
					.text("سوال " + (index + 1));
			});
		}

		$("#jluxe-add-faq-item").on("click", function () {
			var uniqueIndex = "new" + Date.now();
			var html = $.trim($tpl.html()).replace(/__INDEX__/g, uniqueIndex);
			var $newItem = $(html);
			$wrap.append($newItem);
			relabelItems();
			$newItem.find('input[type="text"]').first().trigger("focus");
		});

		$wrap.on("click", ".jluxe-faq-remove-item", function () {
			var $item = $(this).closest(".jluxe-faq-item");
			var hasContent =
				$item.find('input[type="text"]').val() || $item.find("textarea").val();
			if (hasContent && !window.confirm("این سوال حذف بشه؟")) {
				return;
			}
			$item.remove();
			relabelItems();
		});

		if ($.fn.sortable) {
			$wrap.sortable({
				handle: ".jluxe-faq-drag-handle",
				axis: "y",
				update: relabelItems,
			});
		}
	});
})(window.jQuery);
