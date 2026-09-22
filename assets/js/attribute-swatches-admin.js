/**
 * رفتار فرم سواچ ترم‌های ویژگی (inc/attribute-swatches.php) — فقط توی
 * صفحه‌ی افزودن/ویرایشِ ترمِ یک ویژگیِ ووکامرس (pa_*) لود می‌شه.
 * از event delegation روی document استفاده می‌شه (نه بایندِ مستقیم)
 * چون فرمِ «افزودنِ ترمِ جدید» بعد از هر ثبتِ موفق با AJAX خودِ وردپرس
 * دوباره در DOM باقی می‌مونه (فقط مقادیرش خالی می‌شه)، پس بایندِ اولیه
 * هنوز معتبره؛ delegation فقط برای استحکامِ بیشتر در برابر تغییراتِ
 * احتمالیِ DOM هست.
 */
(function ($) {
	if (!$) {
		return;
	}

	function toggleFields($wrap) {
		var type = $wrap.find('input[name="jluxe_swatch_type"]:checked').val();
		$wrap.find(".jluxe-swatch-color-field").toggle(type === "color");
		$wrap.find(".jluxe-swatch-image-field").toggle(type === "image");
	}

	function initWrap($wrap) {
		toggleFields($wrap);

		var $colorInput = $wrap.find(".jluxe-swatch-color-picker");
		if ($colorInput.length && !$colorInput.hasClass("jluxe-swatch-color-picker-inited")) {
			$colorInput.addClass("jluxe-swatch-color-picker-inited").wpColorPicker();
		}
	}

	$(document).on("change", 'input[name="jluxe_swatch_type"]', function () {
		initWrap($(this).closest(".jluxe-swatch-field"));
	});

	$(document).on("click", ".jluxe-swatch-image-select", function (event) {
		event.preventDefault();
		var $field = $(this).closest(".jluxe-swatch-image-field");
		var frame = wp.media({
			title: "انتخاب تصویر سواچ",
			multiple: false,
			library: { type: "image" },
		});
		frame.on("select", function () {
			var attachment = frame.state().get("selection").first().toJSON();
			$field.find(".jluxe-swatch-image-id").val(attachment.id);
			$field
				.find(".jluxe-swatch-image-preview")
				.attr("src", (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) || attachment.url)
				.show();
			$field.find(".jluxe-swatch-image-remove").show();
		});
		frame.open();
	});

	$(document).on("click", ".jluxe-swatch-image-remove", function (event) {
		event.preventDefault();
		var $field = $(this).closest(".jluxe-swatch-image-field");
		$field.find(".jluxe-swatch-image-id").val("");
		$field.find(".jluxe-swatch-image-preview").hide().attr("src", "");
		$(this).hide();
	});

	$(function () {
		$(".jluxe-swatch-field").each(function () {
			initWrap($(this));
		});
	});

	// فرمِ «افزودنِ ترمِ جدید» بعد از ثبتِ موفق با AJAX خالی می‌شه — رنگ‌یاب
	// باید به رنگِ پیش‌فرض برگرده و پیش‌نمایشِ تصویر مخفی بشه، وگرنه مقدارِ
	// ترمِ قبلی روی صفحه می‌مونه انگار هنوز انتخاب شده.
	$(document).on("wp_ajax_added_tag", function () {
		var $wrap = $("#addtag .jluxe-swatch-field");
		if (!$wrap.length) {
			return;
		}
		$wrap.find('input[name="jluxe_swatch_type"][value="none"]').prop("checked", true);
		var $picker = $wrap.find(".jluxe-swatch-color-picker");
		if ($picker.length && $picker.wpColorPicker) {
			$picker.wpColorPicker("color", "");
		}
		$wrap.find(".jluxe-swatch-image-id").val("");
		$wrap.find(".jluxe-swatch-image-preview").hide().attr("src", "");
		$wrap.find(".jluxe-swatch-image-remove").hide();
		toggleFields($wrap);
	});
})(window.jQuery);
