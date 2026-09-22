/**
 * فقط در صفحات wp-admin تنظیمات JLuxe لود می‌شه (enqueue شرطی در
 * inc/theme-settings-admin.php). ناوبری بین صفحات با لینک‌های واقعی
 * admin.php?page=... انجام می‌شه (نه تب JS)؛ این فایل فقط رفتار داخل هر
 * صفحه رو مدیریت می‌کنه: Media Uploader واقعی وردپرس، wp-color-picker، و
 * هومپیج بیلدر (drag&drop).
 */
jQuery(function ($) {
	"use strict";

	// Color picker واقعی وردپرس
	$(".jluxe-color-field").wpColorPicker();

	// دکمه‌های «رنگِ پیشنهادی» (مثلاً پس‌زمینه‌ی فوتر) — کلیک روی هرکدوم
	// رنگِ inputِ متنیِ قبل از خودش رو با API واقعیِ wp-color-picker ست
	// می‌کنه (نه فقط .val()، چون پیکر مقدار رو در یک دکمه/پیش‌نمایشِ جدا
	// نشون می‌ده که با .val() ساده به‌روز نمی‌شه).
	//
	// باگِ واقعیِ گزارش‌شده («روی این رنگ‌ها کلیک می‌شه ولی کار نمی‌کنن»):
	// قبلاً رنگ از .css("background-color") خونده می‌شد — مرورگر مقدارِ
	// هگزِ inline style رو به‌صورتِ رشته‌ی rgb(r, g, b) نرمال‌سازی می‌کنه،
	// و wpColorPicker("color", ...) (روی Iris) با این فرمت به‌طورِ قابل‌اعتماد
	// کار نمی‌کرد (سکوت می‌کرد، نه خطا). الان مستقیم از data-jluxe-fill-color
	// (که PHP از قبل با همون هگزِ واقعی پر می‌کنه) خونده می‌شه — بدونِ
	// نیاز به تبدیلِ فرمتِ رنگ.
	$(document).on("click", ".jluxe-color-preset-swatch", function (event) {
		event.preventDefault();
		// عمداً prev() روی نزدیک‌ترین والدِ .jluxe-color-presets (نه
		// closest("td").find(...)) — چون در حالتِ گرادیانت چند جفتِ
		// input+presets توی یک <td> واحد کنارِ هم هستن؛ find اولی همیشه
		// اولین input رو برمی‌گردوند صرف‌نظر از این‌که کدوم سواچ کلیک شده.
		var $input = $(this).closest(".jluxe-color-presets").prev(".jluxe-color-field");
		var color = $(this).attr("data-jluxe-fill-color");
		if ($input.length && color) {
			$input.wpColorPicker("color", color);
		}
	});

	// طیف‌های گرادیانِ آماده (پس‌زمینه‌ی فوتر) — طبقِ درخواستِ کاربر. هر
	// دکمه دو رنگ (data-jluxe-fill-gradient="#hex1,#hex2") داره؛ کلیک
	// همزمان gradient_colors[0]/[1] رو با API واقعیِ wp-color-picker پر
	// می‌کنه، [2]/[3] (اگه قبلاً چیزی داشتن) خالی می‌شه، و نوعِ پس‌زمینه
	// خودکار رو «گرادیانت» می‌ره تا نتیجه بلافاصله قابل‌دیدن باشه — بدونِ
	// این‌که کاربر مجبور باشه اول خودش select رو عوض کنه.
	$(document).on("click", ".jluxe-gradient-preset-swatch", function (event) {
		event.preventDefault();
		var colors = ($(this).attr("data-jluxe-fill-gradient") || "").split(",");
		var $inputs = $(this).closest("td").find(".jluxe-color-field");
		$inputs.each(function (i) {
			var $field = $(this);
			var value = colors[i] || "";
			if (value) {
				$field.wpColorPicker("color", value);
			} else if (i >= colors.length) {
				$field.wpColorPicker("color", "").val("");
			}
		});
		var $mode = $("#jluxe-footer-bg-mode");
		if ($mode.length && $mode.val() !== "gradient") {
			$mode.val("gradient").trigger("change");
		}
	});

	// select با جستجوی محلی (selectWoo — از قبل با wc-enhanced-select لود
	// می‌شه) برای انتخاب دسته‌ی هر پنل در «پنل‌های پیشنهادی» — چون تعداد
	// دسته‌ها می‌تونه زیاد بشه، اسکرول توی یک <select> ساده اذیت‌کننده‌ست؛
	// این‌جا AJAX لازم نیست چون همه‌ی option ها از قبل سمت سرور رندر شدن،
	// فقط جستجوی محلی روی همون گزینه‌های موجوده.
	if ($.fn.selectWoo) {
		$(".jluxe-select2-search").selectWoo({ width: "100%", dir: "rtl" });
	}

	// Media Uploader واقعی وردپرس (wp.media) — با delegation روی document، چون
	// ردیف‌های repeater (اسلاید/دسته‌بندی و...) بعداً به‌صورت پویا اضافه می‌شن؛
	// اگه این‌جا مستقیم روی .jluxe-media-select بایند بشه، دکمه‌ی ردیفِ تازه‌اضافه‌شده
	// هیچ‌وقت هندلر نمی‌گیره.
	$(document).on("click", ".jluxe-media-select", function (event) {
		event.preventDefault();
		var button = $(this);
		var targetInput = $("#" + button.data("target"));
		var previewEl = button.closest(".jluxe-media-field").find(".jluxe-media-preview");

		var frame = wp.media({
			title: "انتخاب تصویر",
			button: { text: "استفاده از این تصویر" },
			multiple: false,
		});

		frame.on("select", function () {
			var attachment = frame.state().get("selection").first().toJSON();
			targetInput.val(attachment.id);
			var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			previewEl.html('<img src="' + previewUrl + '" alt="" style="max-height:60px;" />');
		});

		frame.open();
	});

	$(document).on("click", ".jluxe-media-remove", function (event) {
		event.preventDefault();
		var button = $(this);
		var targetInput = $("#" + button.data("target"));
		targetInput.val(0);
		var emptyLabel = button.data("empty-label") || "از پیش‌فرض تم استفاده می‌شه.";
		button
			.closest(".jluxe-media-field")
			.find(".jluxe-media-preview")
			.html('<span class="description">' + emptyLabel + "</span>");
	});

	// ===================================================================
	// Repeater عمومی (اسلایدهای هیرو، آیتم‌های دسته‌بندی و هر لیست پویای
	// مشابه دیگه) — افزودن/حذف ردیف + renumber کردن index داخل name="...".
	// مستقل از renumberHomepageSections پایین (چون فقط بخش [group][N] رو
	// عوض می‌کنه، نه [sections][N] رو)، پس با جابه‌جایی/افزودن بخشِ بیرونی
	// تداخل نداره.
	// ===================================================================
	$(document).on("click", ".jluxe-repeater-add", function () {
		// find/first (نه children) عمداً غلطه وقتی repeater تودرتو داریم (مثلاً
		// ستون‌های فوتر که هرکدوم خودش یک repeaterِ لینکِ جداگونه داره): چون
		// find همه‌ی نوادگان رو می‌گرده، اگه از قبل حداقل یک آیتم به لیستِ
		// بیرونی اضافه شده باشه، اولین .jluxe-repeater-list که پیدا می‌کنه
		// می‌تونه لیستِ تودرتوی داخل همون آیتم باشه نه لیستِ خودِ این repeater.
		// چون ساختار همیشه «.jluxe-repeater > .jluxe-repeater-list» است (فرزند
		// مستقیم)، children اینجا درسته.
		var $repeater = $(this).closest(".jluxe-repeater");
		var $list = $repeater.children(".jluxe-repeater-list").first();
		var $tpl = $repeater.children(".jluxe-repeater-template").first();
		var max = parseInt($repeater.data("max"), 10) || 0;
		if (max && $list.children(".jluxe-repeater-item").length >= max) {
			return;
		}
		if (!$list.length || !$tpl.length) {
			return;
		}
		var $newItem = $($.parseHTML($.trim($tpl.html())));
		// تمپلیت یک HTML استاتیکِ سمت سرور با idهای ثابته (چون فقط یک بار
		// رندر شده)؛ اگه توی همون session چندبار «افزودن» بزنی، هر کپی همون
		// id رو داره — برای فیلد رسانه (jluxe_render_media_field) این باعث
		// می‌شه دکمه‌ی ردیف دوم به‌اشتباه روی input ردیف اول بنویسه. این‌جا
		// برای هر ردیف تازه یک id واقعاً یکتا می‌سازیم.
		$newItem.find(".jluxe-media-field").each(function () {
			var $field = $(this);
			var $hidden = $field.find('input[type="hidden"]').first();
			var uniqueId = "jluxe-media-" + Date.now() + "-" + Math.floor(Math.random() * 100000);
			$hidden.attr("id", uniqueId);
			$field.find(".jluxe-media-select, .jluxe-media-remove").attr("data-target", uniqueId);
		});
		$list.append($newItem);
		renumberRepeater($list);
		// اگه این ریپیتر داخلِ یک بخشِ صفحه‌ی اصلیه که همین الان (بدون رفرش)
		// اضافه شده، تمپلیتِ ردیفِ جدید هنوز placeholder ایندکسِ بخش رو داره
		// (چون renumberHomepageSections موقعِ افزودنِ خودِ بخش فقط فیلدهای
		// همون‌موقع‌موجود رو اصلاح می‌کنه، نه محتوای متنیِ خامِ تمپلیتِ
		// ریپیترِ تودرتو که تازه از توش کپی گرفتیم) — باگِ واقعیِ گزارش‌شده:
		// افزودنِ یک آیتم به ریپیترِ تودرتوی یک بخشِ تازه‌افزوده، name اش رو
		// با «__INDEX__» به‌جای شماره‌ی واقعیِ بخش ذخیره می‌کرد. صدازدنِ دوباره‌ی
		// این‌جا (idempotent و امن، حتی وقتی صفحه اصلاً هومپیج‌بیلدر نداره)
		// همیشه همه‌چی رو با شماره‌ی واقعیِ فعلی هماهنگ نگه می‌داره.
		if (typeof renumberHomepageSections === "function") {
			renumberHomepageSections();
		}
	});

	$(document).on("click", ".jluxe-repeater-remove", function () {
		if (!confirm("این ردیف حذف بشه؟")) {
			return;
		}
		var $list = $(this).closest(".jluxe-repeater-list");
		$(this).closest(".jluxe-repeater-item").remove();
		renumberRepeater($list);
	});

	$(".jluxe-repeater-list").each(function () {
		if ($(this).closest(".jluxe-repeater").data("sortable") === false) {
			return;
		}
		$(this).sortable({
			handle: ".jluxe-repeater-handle",
			axis: "y",
			update: function () {
				renumberRepeater($(this));
			},
		});
	});

	function renumberRepeater($list) {
		var group = $list.data("group");
		if (!group) {
			return;
		}
		var re = new RegExp("\\[" + group + "\\]\\[[^\\]]*\\]");
		$list.children(".jluxe-repeater-item").each(function (index) {
			$(this)
				.find("[name]")
				.each(function () {
					var name = $(this).attr("name");
					var renamed = name.replace(re, "[" + group + "][" + index + "]");
					$(this).attr("name", renamed);
				});
			$(this)
				.find(".jluxe-repeater-index")
				.text(index + 1);
		});
	}

	// ===================================================================
	// هومپیج بیلدر — drag&drop، افزودن/حذف/تکثیر بخش، renumber ایندکس‌ها
	// قبل از هر submit تا name="homepage[sections][N][...]" با ترتیب واقعیِ
	// نمایش‌داده‌شده یکی بمونه (وگرنه بعد از جابه‌جایی، ترتیبِ ذخیره‌شده
	// همون ترتیب قدیمی می‌مونه چون ایندکس در name عوض نشده).
	// ===================================================================
	var $hbList = $("#jluxe-hb-sections");
	if ($hbList.length) {
		$hbList.sortable({
			handle: ".jluxe-hb-handle",
			axis: "y",
			update: renumberHomepageSections,
		});

		// هدرِ هر بخش الان <summary> یک <details> اکاردونیه (برای جلوگیری از
		// طول زیاد صفحه) — بدون این خط، کلیک روی چک‌باکس «فعال»/دکمه‌ی
		// تکثیر/حذف/دستگیره‌ی جابه‌جایی هم به‌عنوان اثر جانبیِ رفتار بومیِ
		// <summary> باعث باز/بسته‌شدن اکاردیون می‌شد.
		$(document).on("click", ".jluxe-hb-enabled, .jluxe-hb-duplicate, .jluxe-hb-remove, .jluxe-hb-handle", function (event) {
			event.stopPropagation();
		});

		$(document).on("click", ".jluxe-hb-remove", function () {
			if (!confirm("این بخش حذف بشه؟")) {
				return;
			}
			$(this).closest(".jluxe-hb-section").remove();
			renumberHomepageSections();
		});

		$(document).on("click", ".jluxe-hb-duplicate", function () {
			var $clone = $(this).closest(".jluxe-hb-section").clone();
			$(this).closest(".jluxe-hb-section").after($clone);
			renumberHomepageSections();
		});

		$("#jluxe-hb-add").on("click", function () {
			var type = $("#jluxe-hb-add-type").val();
			var templateEl = document.querySelector('#jluxe-hb-templates template[data-hb-template="' + type + '"]');
			if (!templateEl) {
				return;
			}
			// <template>.content یک DocumentFragmentِ جداست، نه رشته‌ی HTML —
			// باید clone بشه و مستقیم append بشه (نه .html()/رشته‌سازی، که
			// معنای اون فرگمنت رو از دست می‌ده).
			$hbList.append(templateEl.content.cloneNode(true));
			renumberHomepageSections();
		});
	}

	function renumberHomepageSections() {
		$hbList.find(".jluxe-hb-section").each(function (index) {
			$(this)
				.find("[name]")
				.each(function () {
					var name = $(this).attr("name");
					var renamed = name.replace(/homepage\[sections\]\[[^\]]*\]/, "homepage[sections][" + index + "]");
					$(this).attr("name", renamed);
				});
		});
	}

	// ===================================================================
	// پیش‌نمایشِ زنده‌ی چیدمانِ «کلاژ بنر» — با تعویضِ select چیدمانِ
	// دسکتاپ، شکلِ واقعیِ grid رو (بدونِ رفرشِ صفحه) عوض می‌کنه؛ تعریفِ
	// همه‌ی فرمت‌ها از data-collage-layouts (که PHP همون تعریفِ واحدِ
	// jluxe_hb_collage_desktop_layouts() رو به JSON تبدیل کرده) خونده
	// می‌شه — نه یک کپیِ جداگانه‌ی این داده‌ها این‌جا در JS.
	// ===================================================================
	$(document).on("change", 'select[name*="[desktop_layout]"]', function () {
		var $select = $(this);
		var $preview = $select.closest(".jluxe-hb-section-body").find("[data-jluxe-collage-preview]").first();
		if (!$preview.length) {
			return;
		}
		var layouts;
		try {
			layouts = JSON.parse($preview.attr("data-collage-layouts"));
		} catch (e) {
			return;
		}
		var def = layouts[$select.val()];
		if (!def) {
			return;
		}
		$preview.css({
			gridTemplateColumns: def.columns,
			gridTemplateRows: def.rows,
			gridTemplateAreas: def.areas.map(function (row) { return '"' + row + '"'; }).join(" "),
			height: def.height,
		});
		$preview.find(".jxc-admin-slot").each(function (index) {
			var $slot = $(this);
			var inUse = index + 1 <= def.slot_count;
			$slot.css({ gridArea: "s" + (index + 1), display: inUse ? "" : "none" });
		});
	});

	/**
	 * فیلد‌های شرطی — یک select با data-jluxe-toggle-id="X" مقدارش رو با
	 * هر container ای که data-jluxe-show-if="X:value" داره مقایسه می‌کنه؛
	 * فقط اونی که value برابره نشون داده می‌شه (بقیه hidden). برای «منبع
	 * محصولات» در بخش «محصولات پرفروش» استفاده می‌شه (دسته/برند/دستی) ولی
	 * عمداً generic نوشته شده تا بخش‌های بعدی هم بتونن ازش استفاده کنن.
	 */
	function applyConditionalFields() {
		$("[data-jluxe-toggle-id]").each(function () {
			var id = $(this).data("jluxe-toggle-id");
			var val = $(this).val();
			$('[data-jluxe-show-if^="' + id + ':"]').each(function () {
				var cond = String($(this).data("jluxe-show-if")).split(":")[1];
				$(this).toggle(cond === val);
			});
		});
	}
	$(document).on("change", "[data-jluxe-toggle-id]", applyConditionalFields);
	applyConditionalFields();

	// ===================================================================
	// تست اتصالِ دستیار هوش مصنوعی (inc/theme-settings-ai.php) — یک
	// درخواستِ ساده به provider/کلیدِ همین‌الان‌ذخیره‌شده می‌فرسته. عمداً از
	// روی مقادیرِ فرمِ ذخیره‌نشده تست نمی‌کنه (طبق توضیحِ خودِ صفحه: «اول
	// ذخیره کن، بعد تست بزن»)، پس فقط به ajaxurl + nonce نیاز داره.
	// ===================================================================
	$(document).on("click", "#jluxe-ai-test-conn", function () {
		var $btn = $(this);
		var $res = $("#jluxe-ai-test-res");
		if (typeof window.jluxeSettingsAdmin === "undefined" || typeof window.ajaxurl === "undefined") {
			return;
		}
		$btn.prop("disabled", true);
		$res.css("color", "#1d2327").text("⏳ در حال تست…");
		$.ajax({
			url: window.ajaxurl,
			method: "POST",
			data: {
				action: "jluxe_ai_test_connection",
				nonce: window.jluxeSettingsAdmin.aiTestNonce,
			},
		})
			.done(function (response) {
				if (response && response.success) {
					$res.css("color", "#067d3a").text("✅ موفق (" + response.data.ms + "ms): " + (response.data.message || ""));
				} else {
					var message = response && response.data && response.data.message ? response.data.message : "خطا";
					$res.css("color", "#b32d2e").text("❌ " + message);
				}
			})
			.fail(function () {
				$res.css("color", "#b32d2e").text("❌ خطای ارتباط با سرور");
			})
			.always(function () {
				$btn.prop("disabled", false);
			});
	});

	// ناوبری گروهی تنظیمات زرین
	$(document).on('click', '.jluxe-settings-nav-group-toggle', function (e) {
		e.preventDefault();
		var $group = $(this).closest('.jluxe-settings-nav-group');
		var expanded = $(this).attr('aria-expanded') === 'true';
		$('.jluxe-settings-nav-group').not($group).removeClass('is-open').find('.jluxe-settings-nav-group-toggle').attr('aria-expanded', 'false');
		$group.toggleClass('is-open', !expanded);
		$(this).attr('aria-expanded', expanded ? 'false' : 'true');
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.jluxe-settings-nav-group').length) {
			$('.jluxe-settings-nav-group').removeClass('is-open').find('.jluxe-settings-nav-group-toggle').attr('aria-expanded', 'false');
		}
	});

});

// ===================================================================
// نمادهای سایت — مستقل از custom-list عمومی.
// این بخش عمداً Vanilla JS است تا خرابی هر اسکریپت دیگر پنل، دکمه‌های
// اینماد/نمادها را از کار نیندازد.
//
// باگِ واقعیِ گزارش‌شده («دکمه افزودن آیتم جدید کار نمی‌کنه»، و بعد
// «اصلا ذخیره نمی‌شه»): این کامنت از اول هم می‌گفت این بخش عمداً باید
// مستقل از بقیه‌ی اسکریپت باشه، ولی توی کد واقعاً این‌طور نبود — این کل
// IIFE به‌طور تصادفی داخلِ همون jQuery(function($){...}) بزرگِ بالا
// (که خودش شاملِ color-picker، Media Uploader، هومپیج‌بیلدر و غیره است)
// تعریف شده بود، نه بعد از بسته‌شدنش. یعنی اگه هر جای دیگه‌ای از اون
// تابعِ بزرگ (مثلاً initِ رنگ‌ها یا هر بخشِ دیگه‌ی پنل) قبل از رسیدن به
// این‌جا خطا می‌داد، اجرا همون‌جا متوقف می‌شد و اصلاً به ثبتِ
// event listenerهای «افزودن»/«حذف»/سینک با فرم نمی‌رسید — نه فقط دکمه
// کار نمی‌کرد، بلکه سینکِ قبل از submit هم اصلاً ثبت نمی‌شد، برای همینه
// که محتوای واقعاً واردشده هیچ‌وقت به فیلدِ JSON نمی‌رسید و «ذخیره
// نمی‌شد». راه‌حل: این IIFE رو واقعاً بعد از بسته‌شدنِ اون تابعِ بزرگ
// (نه داخلش) اجرا می‌کنیم تا هر اتفاقی برای بقیه‌ی پنل بیفته، این بخش
// همیشه کار کنه.
// ===================================================================
(function () {
	function getEditorJson(editor) {
		var jsonField = editor.closest('td') ? editor.closest('td').querySelector('textarea.jluxe-site-badges-json') : null;
		if (!jsonField) return null;
		var items = [];
		var rows = editor.querySelectorAll('.jluxe-site-badges-items > .jluxe-site-badge-item');
		rows.forEach(function (row) {
			var htmlField = row.querySelector('.field-html');
			var linkField = row.querySelector('.field-link');
			var html = htmlField ? htmlField.value : '';
			var link = linkField ? linkField.value : '';
			if (html.trim() !== '' || link.trim() !== '') {
				items.push({ html: html, link: link });
			}
		});
		jsonField.value = JSON.stringify(items);
		return jsonField;
	}

	function syncAll() {
		document.querySelectorAll('[data-jluxe-site-badges]').forEach(getEditorJson);
	}

	document.addEventListener('click', function (event) {
		var addButton = event.target.closest('.jluxe-site-badge-add');
		if (addButton) {
			event.preventDefault();
			var editor = addButton.closest('[data-jluxe-site-badges]');
			if (!editor) return;
			var items = editor.querySelector('.jluxe-site-badges-items');
			var template = editor.querySelector('.jluxe-site-badge-template');
			if (!items || !template) return;
			var fragment = template.content ? template.content.cloneNode(true) : document.createRange().createContextualFragment(template.innerHTML);
			items.appendChild(fragment);
			var newItem = items.lastElementChild;
			if (newItem) {
				newItem.setAttribute('data-id', 'jluxe-badge-' + Date.now() + '-' + Math.floor(Math.random() * 100000));
				var focusField = newItem.querySelector('.field-link') || newItem.querySelector('.field-html');
				if (focusField) focusField.focus();
			}
			getEditorJson(editor);
			return;
		}

		var removeButton = event.target.closest('.jluxe-site-badge-item .jluxe-site-badge-remove');
		if (removeButton) {
			event.preventDefault();
			var editor = removeButton.closest('[data-jluxe-site-badges]');
			var row = removeButton.closest('.jluxe-site-badge-item');
			if (row) row.remove();
			if (editor) getEditorJson(editor);
		}
	});

	document.addEventListener('input', function (event) {
		if (event.target.matches('[data-jluxe-site-badges] .field-html, [data-jluxe-site-badges] .field-link')) {
			var editor = event.target.closest('[data-jluxe-site-badges]');
			if (editor) getEditorJson(editor);
		}
	});

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-jluxe-site-badges] .field-html, [data-jluxe-site-badges] .field-link')) {
			var editor = event.target.closest('[data-jluxe-site-badges]');
			if (editor) getEditorJson(editor);
		}
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;
		form.querySelectorAll('[data-jluxe-site-badges]').forEach(getEditorJson);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', syncAll);
	} else {
		syncAll();
	}
})();
