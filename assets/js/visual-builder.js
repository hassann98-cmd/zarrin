/* global jQuery */
(function ($) {
	"use strict";

	var root = document.getElementById("jluxe-visual-builder");
	if (!root) return;

	var form = document.getElementById("jluxe-visual-builder-form");
	var sections = document.getElementById("jluxe-hb-sections");
	var outline = root.querySelector("[data-jvb-outline]");
	var outlineCount = root.querySelector("[data-jvb-outline-count]");
	var outlineEmpty = root.querySelector("[data-jvb-outline-empty]");
	var dropHint = root.querySelector("[data-jvb-drop-hint]");
	var countLabel = root.querySelector("[data-jvb-section-count]");
	var saveState = root.querySelector("[data-jvb-save-state]");
	var stageTitle = root.querySelector("[data-jvb-stage-title]");
	var stageSubtitle = root.querySelector("[data-jvb-stage-subtitle]");
	var canvas = root.querySelector("[data-jvb-canvas]");
	var livePreview = root.querySelector("[data-jvb-live-preview]");
	var previewFrame = root.querySelector("[data-jvb-preview-frame]");
	var previewToggle = root.querySelector("[data-jvb-preview-toggle]");
	var previewLabel = root.querySelector("[data-jvb-preview-label]");
	var saveButton = root.querySelector('.jvb-save-button[name="action"]');
	var dirty = false;
	var submitting = false;

	if (!form || !sections) return;

	function getSectionCards() {
		return Array.prototype.filter.call(sections.children, function (child) {
			return child.classList && child.classList.contains("jluxe-hb-section");
		});
	}

	function markDirty() {
		dirty = true;
		if (saveState) {
			saveState.textContent = "تغییرات ذخیره‌نشده";
			saveState.classList.add("is-dirty");
		}
	}

	function updateOutline() {
		if (!outline) return;
		var cards = getSectionCards();
		outline.replaceChildren();

		cards.forEach(function (card, index) {
			var row = document.createElement("li");
			var button = document.createElement("button");
			var order = document.createElement("span");
			var label = document.createElement("span");
			var state = document.createElement("span");
			var type = card.getAttribute("data-type") || "";
			var title = card.querySelector(".jluxe-hb-type-label");
			var enabled = card.querySelector(".jluxe-hb-enabled input[type=checkbox]");

			button.type = "button";
			button.setAttribute("data-jvb-outline-target", String(index));
			order.className = "jvb-outline-number";
			order.textContent = String(index + 1);
			label.textContent = title ? title.textContent.trim() : type;
			state.className = "jvb-outline-state";
			state.textContent = enabled && enabled.checked ? "فعال" : "خاموش";
			button.appendChild(order);
			button.appendChild(label);
			button.appendChild(state);
			row.appendChild(button);
			outline.appendChild(row);
		});

		if (outlineCount) outlineCount.textContent = String(cards.length);
		if (outlineEmpty) outlineEmpty.hidden = cards.length > 0;
		if (dropHint) dropHint.hidden = cards.length > 0;
		if (countLabel) countLabel.textContent = "پیش‌نویس مستقل · " + cards.length + " بخش";
	}

	function addSection(type) {
		var selector = document.getElementById("jluxe-hb-add-type");
		var addButton = document.getElementById("jluxe-hb-add");
		if (!selector || !addButton || !type) return;
		selector.value = type;
		addButton.click();
		window.setTimeout(function () {
			var cards = getSectionCards();
			var added = cards[cards.length - 1];
			updateOutline();
			markDirty();
			if (added) {
				added.scrollIntoView({ behavior: "smooth", block: "nearest" });
				var summary = added.querySelector("summary");
				if (summary) summary.focus({ preventScroll: true });
			}
		}, 0);
	}

	// پنل کتابخانه: جست‌وجو، انتخاب تب و افزودن با کلیک/کشیدن.
	root.querySelectorAll("[data-jvb-tab]").forEach(function (tab) {
		tab.addEventListener("click", function () {
			var selected = tab.getAttribute("data-jvb-tab");
			root.querySelectorAll("[data-jvb-tab]").forEach(function (otherTab) {
				var active = otherTab === tab;
				otherTab.setAttribute("aria-selected", active ? "true" : "false");
			});
			root.querySelectorAll("[data-jvb-panel]").forEach(function (panel) {
				panel.hidden = panel.getAttribute("data-jvb-panel") !== selected;
			});
		});
	});

	var search = root.querySelector("[data-jvb-search]");
	if (search) {
		search.addEventListener("input", function () {
			var query = String(search.value || "").trim().toLocaleLowerCase();
			var visibleCount = 0;
			root.querySelectorAll("[data-jvb-add-section]").forEach(function (item) {
				var label = String(item.getAttribute("data-search-label") || item.textContent || "").toLocaleLowerCase();
				var visible = !query || label.indexOf(query) !== -1;
				item.hidden = !visible;
				if (visible) visibleCount += 1;
			});
			root.querySelectorAll("[data-jvb-group]").forEach(function (group) {
				var hasVisible = !!group.querySelector("[data-jvb-add-section]:not([hidden])");
				group.hidden = !hasVisible;
			});
			var empty = root.querySelector("[data-jvb-empty-search]");
			if (empty) empty.hidden = visibleCount > 0;
		});
	}

	root.querySelectorAll("[data-jvb-add-section]").forEach(function (button) {
		button.addEventListener("click", function () {
			addSection(button.getAttribute("data-type"));
		});
		button.addEventListener("dragstart", function (event) {
			if (!event.dataTransfer) return;
			event.dataTransfer.effectAllowed = "copy";
			event.dataTransfer.setData("text/plain", button.getAttribute("data-type") || "");
			event.dataTransfer.setData("application/x-jluxe-section", button.getAttribute("data-type") || "");
		});
	});

	if (canvas) {
		canvas.addEventListener("dragover", function (event) {
			if (!event.dataTransfer || !event.dataTransfer.types) return;
			var canDrop = Array.prototype.indexOf.call(event.dataTransfer.types, "application/x-jluxe-section") !== -1;
			if (!canDrop) return;
			event.preventDefault();
			event.dataTransfer.dropEffect = "copy";
			sections.classList.add("jvb-drop-active");
		});
		canvas.addEventListener("dragleave", function (event) {
			if (!canvas.contains(event.relatedTarget)) sections.classList.remove("jvb-drop-active");
		});
		canvas.addEventListener("drop", function (event) {
			if (!event.dataTransfer) return;
			var type = event.dataTransfer.getData("application/x-jluxe-section") || event.dataTransfer.getData("text/plain");
			if (!type) return;
			event.preventDefault();
			sections.classList.remove("jvb-drop-active");
			addSection(type);
		});
	}

	// نمایهٔ ساختار به همان فرم‌های واقعی متصل است؛ انتخاب هر ردیف، بخش را باز می‌کند.
	if (outline) {
		outline.addEventListener("click", function (event) {
			var button = event.target.closest("[data-jvb-outline-target]");
			if (!button) return;
			var index = parseInt(button.getAttribute("data-jvb-outline-target"), 10);
			var card = getSectionCards()[index];
			if (!card) return;
			var details = card.querySelector("details");
			if (details) details.open = true;
			card.scrollIntoView({ behavior: "smooth", block: "center" });
			var summary = card.querySelector("summary");
			if (summary) summary.focus({ preventScroll: true });
		});
	}

	// تغییر اندازهٔ بوم فقط روی همین محیطِ ویرایش/پیش‌نمایش اثر دارد.
	root.querySelectorAll("[data-jvb-viewport]").forEach(function (button) {
		button.addEventListener("click", function () {
			var viewport = button.getAttribute("data-jvb-viewport") || "desktop";
			var wrap = root.querySelector("[data-jvb-canvas-wrap]");
			if (wrap) wrap.setAttribute("data-viewport", viewport);
			root.querySelectorAll("[data-jvb-viewport]").forEach(function (other) {
				other.setAttribute("aria-pressed", other === button ? "true" : "false");
			});
		});
	});

	function togglePreview(showPreview) {
		if (!livePreview || !canvas) return;
		var previewKind = root.getAttribute("data-preview-kind") || "live";
		var draftPreview = previewKind === "draft";
		canvas.hidden = showPreview;
		livePreview.hidden = !showPreview;
		if (previewToggle) previewToggle.setAttribute("aria-pressed", showPreview ? "true" : "false");
		if (previewLabel) previewLabel.textContent = showPreview ? "بازگشت به ویرایش" : (draftPreview ? "پیش‌نمایش پیش‌نویس ذخیره‌شده" : "پیش‌نمایش سایت فعلی");
		if (stageTitle) stageTitle.textContent = showPreview ? (draftPreview ? "پیش‌نویس ذخیره‌شده" : "نسخهٔ منتشرشده") : "چیدمان صفحهٔ اصلی";
		if (stageSubtitle) stageSubtitle.textContent = showPreview
			? (draftPreview ? "نمایش خصوصی برای مدیرِ واردشده؛ بازدیدکنندگان نسخهٔ فعال را می‌بینند" : "پیش‌نویس ذخیره‌نشده در این قاب اعمال نمی‌شود")
			: "پیش‌نویس مستقل از سایت فعال";
		if (showPreview && previewFrame && !previewFrame.getAttribute("src")) {
			previewFrame.setAttribute("src", previewFrame.getAttribute("data-src") || root.getAttribute("data-preview-url") || "");
		}
	}

	if (previewToggle) {
		previewToggle.addEventListener("click", function () {
			togglePreview(previewToggle.getAttribute("aria-pressed") !== "true");
		});
	}

	// هر تغییر در فرم، افزودن/حذف یا مرتب‌سازی، وضعیت ذخیره را به‌روز می‌کند.
	form.addEventListener("input", markDirty);
	form.addEventListener("change", function (event) {
		markDirty();
		if (event.target.closest(".jluxe-hb-section")) updateOutline();
	});

	if (window.MutationObserver) {
		var observer = new MutationObserver(function (records) {
			var sectionsChanged = records.some(function (record) {
				return record.target === sections || sections.contains(record.target);
			});
			if (sectionsChanged) {
				updateOutline();
				markDirty();
			}
		});
		observer.observe(sections, { childList: true, subtree: true });
	}

	form.addEventListener("submit", function (event) {
		var submitter = event.submitter;
		var isPublish = submitter && submitter.value === "jluxe_visual_builder_publish";
		if (isPublish && !window.confirm("با انتشار، چیدمانِ فعالِ صفحهٔ اصلی با این پیش‌نویس جایگزین می‌شود. ادامه می‌دهید؟")) {
			event.preventDefault();
			return;
		}
		submitting = true;
		if (saveState) {
			saveState.textContent = isPublish ? "در حال انتشار…" : "در حال ذخیره…";
			saveState.classList.remove("is-dirty");
		}
	});

	window.addEventListener("beforeunload", function (event) {
		if (!dirty || submitting) return;
		event.preventDefault();
		event.returnValue = "";
	});

	updateOutline();
}(jQuery));
