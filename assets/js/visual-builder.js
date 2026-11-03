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
	var settingsCanvas = root.querySelector("[data-jvb-canvas]");
	var livePreview = root.querySelector("[data-jvb-live-preview]");
	var previewFrame = root.querySelector("[data-jvb-preview-frame]");
	var previewFrameShell = root.querySelector("[data-jvb-preview-frame-shell]");
	var previewLoading = root.querySelector("[data-jvb-preview-loading]");
	var previewStatus = root.querySelector("[data-jvb-preview-status]");
	var previewDropOverlay = root.querySelector("[data-jvb-preview-drop-overlay]");
	var previewToggle = root.querySelector("[data-jvb-preview-toggle]");
	var previewLabel = root.querySelector("[data-jvb-preview-label]");
	var openPreviewLink = root.querySelector("[data-jvb-open-preview]");
	var config = window.jluxeVisualBuilder || {};
	var dirty = false;
	var submitting = false;
	var draggedType = "";
	var previewRequestTimer = null;
	var previewRequestNumber = 0;
	var previewAbortController = null;
	var latestPreviewUrl = "";
	var selectedSectionIndex = -1;
	var activeFrameTarget = null;
	var frameDropSupported = false;
	var undoButton = root.querySelector("[data-jvb-undo]");
	var redoButton = root.querySelector("[data-jvb-redo]");
	var historyList = root.querySelector("[data-jvb-history-list]");
	var historyEmpty = root.querySelector("[data-jvb-history-empty]");
	var historyCount = root.querySelector("[data-jvb-history-count]");
	var panelTitle = root.querySelector("[data-jvb-panel-title]");
	var panelCount = root.querySelector("[data-jvb-panel-count]");
	var selectionTitle = root.querySelector("[data-jvb-selection-title]");
	var historyEntries = [];
	var historyIndex = -1;
	var nextHistoryId = 1;
	var historyCaptureTimer = null;
	var historyPendingLabel = "";
	var historyRestoring = false;
	var ignoreHistoryMutations = false;
	var activePanel = "components";
	var historyLimit = 30;

	if (!form || !sections) return;

	function getSectionCards() {
		return Array.prototype.filter.call(sections.children, function (child) {
			return child.classList && child.classList.contains("jluxe-hb-section");
		});
	}

	function getSectionLabel(card) {
		var title = card && card.querySelector(".jluxe-hb-type-label");
		return title ? title.textContent.trim() : (card && card.getAttribute("data-type")) || "بخش";
	}

	function ensureSectionActions() {
		getSectionCards().forEach(function (card, index, cards) {
			var summary = card.querySelector("summary.jluxe-hb-section-head") || card.querySelector("summary");
			if (!summary) return;
			var group = summary.querySelector("[data-jvb-section-actions]");
			var chevron = summary.querySelector(".jluxe-hb-chevron");
			if (!group) {
				group = document.createElement("span");
				group.className = "jvb-section-actions";
				group.setAttribute("data-jvb-section-actions", "true");
				group.setAttribute("aria-label", "ترتیب بخش");
				summary.insertBefore(group, chevron || null);
			}

			[
				{ action: "up", label: "یک پله بالا", icon: "arrow-up-alt2", disabled: index === 0 },
				{ action: "down", label: "یک پله پایین", icon: "arrow-down-alt2", disabled: index === cards.length - 1 },
			].forEach(function (item) {
				var button = group.querySelector('[data-jvb-section-action="' + item.action + '"]');
				if (!button) {
					button = document.createElement("button");
					button.type = "button";
					button.className = "button-link jvb-section-order-button";
					button.setAttribute("data-jvb-section-action", item.action);
					button.setAttribute("aria-label", item.label);
					button.title = item.label;
					var icon = document.createElement("span");
					icon.className = "dashicons dashicons-" + item.icon;
					icon.setAttribute("aria-hidden", "true");
					button.appendChild(icon);
				}
				button.disabled = item.disabled;
				if (button.parentNode !== group) group.appendChild(button);
			});
		});
	}

	function getHomepageControls() {
		return Array.prototype.slice.call(form.querySelectorAll('input[name^="homepage["], select[name^="homepage["], textarea[name^="homepage["]'));
	}

	function captureControlState(field) {
		var state = {
			name: field.getAttribute("name") || "",
			tag: field.tagName.toLowerCase(),
			type: String(field.type || "").toLowerCase(),
			value: field.value,
		};
		if ("select" === state.tag) {
			state.selected = [];
			Array.prototype.forEach.call(field.options, function (option, index) {
				if (option.selected) state.selected.push(index);
			});
		} else if ("checkbox" === state.type || "radio" === state.type) {
			state.checked = Boolean(field.checked);
		}
		return state;
	}

	function captureHistorySnapshot(label) {
		return {
			label: label || "ویرایش صفحه",
			createdAt: new Date(),
			sectionsHtml: sections.innerHTML,
			controls: getHomepageControls().map(captureControlState),
			selectedIndex: selectedSectionIndex,
		};
	}

	function snapshotsEqual(left, right) {
		if (!left || !right || left.sectionsHtml !== right.sectionsHtml || left.controls.length !== right.controls.length) return false;
		for (var i = 0; i < left.controls.length; i += 1) {
			var a = left.controls[i];
			var b = right.controls[i];
			if (a.name !== b.name || a.tag !== b.tag || a.type !== b.type || a.value !== b.value || a.checked !== b.checked) return false;
			if (JSON.stringify(a.selected || []) !== JSON.stringify(b.selected || [])) return false;
		}
		return true;
	}

	function formatHistoryCount(value) {
		try {
			return new Intl.NumberFormat("fa-IR").format(value);
		} catch (error) {
			return String(value);
		}
	}

	function formatHistoryTime(date) {
		try {
			return date.toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit" });
		} catch (error) {
			return date.toLocaleTimeString();
		}
	}

	function updateHistoryControls() {
		if (undoButton) undoButton.disabled = historyIndex <= 0;
		if (redoButton) redoButton.disabled = historyIndex < 0 || historyIndex >= historyEntries.length - 1;
	}

	function updatePanelMetadata() {
		var cards = getSectionCards();
		var titles = {
			components: "المان‌های صفحه",
			settings: selectedSectionIndex >= 0 && cards[selectedSectionIndex] ? "تنظیمات بخش" : "تنظیمات صفحه",
			structure: "ساختار صفحه",
			history: "تاریخچهٔ صفحه",
			page: "برگهٔ اصلی",
		};
		var counts = {
			components: root.querySelectorAll("[data-jvb-add-section]").length,
			settings: cards.length,
			structure: cards.length,
			history: historyEntries.length,
			page: "۱",
		};
		if (panelTitle) panelTitle.textContent = titles[activePanel] || titles.components;
		if (panelCount) panelCount.textContent = typeof counts[activePanel] === "number" ? formatHistoryCount(counts[activePanel]) : counts[activePanel];
		if (selectionTitle) {
			selectionTitle.textContent = selectedSectionIndex >= 0 && cards[selectedSectionIndex]
				? "ویرایش «" + getSectionLabel(cards[selectedSectionIndex]) + "»"
				: "تنظیمات صفحهٔ اصلی";
		}
	}

	function activatePanel(name) {
		var validPanels = ["components", "settings", "structure", "history", "page"];
		if (validPanels.indexOf(name) === -1) return;
		activePanel = name;
		root.querySelectorAll("[data-jvb-tab]").forEach(function (tab) {
			tab.setAttribute("aria-selected", tab.getAttribute("data-jvb-tab") === name ? "true" : "false");
		});
		root.querySelectorAll("[data-jvb-panel]").forEach(function (panel) {
			panel.hidden = panel.getAttribute("data-jvb-panel") !== name;
		});
		var navigation = root.querySelector(".jvb-tabs");
		if (navigation) {
			navigation.setAttribute("aria-orientation", window.matchMedia && window.matchMedia("(max-width: 782px)").matches ? "horizontal" : "vertical");
		}
		updatePanelMetadata();
	}

	function renderHistoryPanel() {
		if (historyCount) historyCount.textContent = formatHistoryCount(historyEntries.length);
		if (historyEmpty) historyEmpty.hidden = historyEntries.length > 1;
		if (historyList) {
			historyList.replaceChildren();
			for (var i = historyEntries.length - 1; i >= 0; i -= 1) {
				var entry = historyEntries[i];
				var item = document.createElement("li");
				var button = document.createElement("button");
				var label = document.createElement("span");
				var time = document.createElement("time");
				button.type = "button";
				button.className = "jvb-history-entry";
				button.setAttribute("data-jvb-history-id", String(entry.id));
				button.setAttribute("aria-label", entry.label + "، ساعت " + formatHistoryTime(entry.createdAt));
				if (i === historyIndex) button.setAttribute("aria-current", "step");
				label.className = "jvb-history-entry-label";
				label.textContent = entry.label;
				time.textContent = formatHistoryTime(entry.createdAt);
				time.dateTime = entry.createdAt.toISOString();
				button.appendChild(label);
				button.appendChild(time);
				item.appendChild(button);
				historyList.appendChild(item);
			}
		}
		updateHistoryControls();
		updatePanelMetadata();
	}

	function commitHistoryCapture() {
		if (historyCaptureTimer) window.clearTimeout(historyCaptureTimer);
		historyCaptureTimer = null;
		if (historyRestoring) return;
		var label = historyPendingLabel || "ویرایش صفحه";
		historyPendingLabel = "";
		var snapshot = captureHistorySnapshot(label);
		if (historyIndex >= 0 && snapshotsEqual(historyEntries[historyIndex], snapshot)) return;
		if (historyIndex < historyEntries.length - 1) historyEntries.splice(historyIndex + 1);
		snapshot.id = nextHistoryId;
		nextHistoryId += 1;
		historyEntries.push(snapshot);
		if (historyEntries.length > historyLimit) {
			historyEntries.shift();
		}
		historyIndex = historyEntries.length - 1;
		renderHistoryPanel();
	}

	function queueHistoryCapture(label) {
		if (historyRestoring) return;
		if (label && (!historyPendingLabel || label !== "تغییر ساختار")) historyPendingLabel = label;
		if (historyCaptureTimer) window.clearTimeout(historyCaptureTimer);
		historyCaptureTimer = window.setTimeout(commitHistoryCapture, 320);
	}

	function flushHistoryCapture() {
		if (historyCaptureTimer) commitHistoryCapture();
	}

	function applyControlState(field, state) {
		if (!state || field.getAttribute("name") !== state.name || field.tagName.toLowerCase() !== state.tag) return;
		if ("select" === state.tag) {
			Array.prototype.forEach.call(field.options, function (option, index) {
				option.selected = (state.selected || []).indexOf(index) !== -1;
			});
		} else if ("checkbox" === state.type || "radio" === state.type) {
			field.checked = Boolean(state.checked);
		} else {
			field.value = state.value;
		}
	}

	function restoreHistoryEntry(index) {
		var entry = historyEntries[index];
		if (!entry) return false;
		ignoreHistoryMutations = true;
		historyRestoring = true;
		sections.innerHTML = entry.sectionsHtml;
		renumberSectionInputs();
		getHomepageControls().forEach(function (field, fieldIndex) {
			applyControlState(field, entry.controls[fieldIndex]);
		});
		selectedSectionIndex = entry.selectedIndex >= 0 && entry.selectedIndex < getSectionCards().length ? entry.selectedIndex : -1;
		getSectionCards().forEach(function (card, cardIndex) {
			card.classList.toggle("jvb-selected", cardIndex === selectedSectionIndex);
		});
		historyIndex = index;
		updateOutline();
		updateHistoryControls();
		updatePanelMetadata();
		markDirty();
		if (selectedSectionIndex >= 0) highlightFrameSection(selectedSectionIndex);
		schedulePreviewRefresh(90);
		setPreviewStatus("نسخهٔ انتخاب‌شده از تاریخچه بازیابی شد؛ برای ثبت آن، پیش‌نویس را ذخیره کنید.", false);
		renderHistoryPanel();
		historyRestoring = false;
		window.setTimeout(function () {
			ignoreHistoryMutations = false;
		}, 0);
		return true;
	}

	function stepHistory(direction) {
		flushHistoryCapture();
		var targetIndex = historyIndex + direction;
		if (targetIndex < 0 || targetIndex >= historyEntries.length) return false;
		return restoreHistoryEntry(targetIndex);
	}

	function handleHistoryShortcut(event) {
		if (!event || !(event.ctrlKey || event.metaKey) || event.altKey) return;
		var key = String(event.key || "").toLowerCase();
		var target = event.target;
		var isEditingField = target && typeof target.closest === "function" && target.closest('input, textarea, select, [contenteditable="true"]');
		if (isEditingField) return;
		var undo = "z" === key && !event.shiftKey;
		var redo = ("z" === key && event.shiftKey) || ("y" === key && !event.shiftKey);
		if (!undo && !redo) return;
		var available = undo ? historyIndex > 0 : historyIndex >= 0 && historyIndex < historyEntries.length - 1;
		if (!available) return;
		event.preventDefault();
		stepHistory(undo ? -1 : 1);
	}

	function setPreviewStatus(message, isError) {
		if (!previewStatus) return;
		previewStatus.textContent = message;
		previewStatus.classList.toggle("is-error", Boolean(isError));
	}

	function setPreviewLoading(isLoading) {
		if (previewFrameShell) previewFrameShell.setAttribute("data-loading", isLoading ? "true" : "false");
		if (previewLoading) previewLoading.hidden = !isLoading;
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
		ensureSectionActions();
		var cards = getSectionCards();
		var selectedCard = sections.querySelector(".jluxe-hb-section.jvb-selected");
		if (selectedCard) {
			selectedSectionIndex = cards.indexOf(selectedCard);
		} else if (selectedSectionIndex >= cards.length) {
			selectedSectionIndex = -1;
		}
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
		updatePanelMetadata();
	}

	function renumberSectionInputs() {
		getSectionCards().forEach(function (card, index) {
			card.querySelectorAll("[name]").forEach(function (field) {
				var name = field.getAttribute("name");
				if (name && name.indexOf("homepage[sections][") !== -1) {
					field.setAttribute("name", name.replace(/homepage\[sections\]\[[^\]]*\]/, "homepage[sections][" + index + "]"));
				}
			});
		});
	}

	function selectSection(index, fromPreview) {
		var cards = getSectionCards();
		var card = cards[index];
		if (!card) return;

		cards.forEach(function (item) {
			item.classList.remove("jvb-selected");
		});
		card.classList.add("jvb-selected");
		selectedSectionIndex = index;
		activatePanel("settings");

		var details = card.querySelector("details");
		if (details) details.open = true;
		card.scrollIntoView({ behavior: "smooth", block: "nearest" });
		var summary = card.querySelector("summary");
		if (summary && !fromPreview) summary.focus({ preventScroll: true });

		if (fromPreview) {
			var title = card.querySelector(".jluxe-hb-type-label");
			setPreviewStatus("بخش «" + (title ? title.textContent.trim() : "صفحه") + "» انتخاب شد؛ تنظیمات بومی آن در پنل کناری باز شد.", false);
		}
		highlightFrameSection(index);
	}

	sections.addEventListener("click", function (event) {
		var button = event.target && typeof event.target.closest === "function" ? event.target.closest("[data-jvb-section-action]") : null;
		if (!button || !sections.contains(button)) return;
		event.preventDefault();
		event.stopPropagation();
		var cards = getSectionCards();
		var card = button.closest(".jluxe-hb-section");
		var index = cards.indexOf(card);
		var action = button.getAttribute("data-jvb-section-action");
		if (index < 0 || ("up" === action && index === 0) || ("down" === action && index >= cards.length - 1)) return;

		queueHistoryCapture("جابه‌جایی «" + getSectionLabel(card) + "»");
		if ("up" === action) {
			sections.insertBefore(card, cards[index - 1]);
		} else {
			sections.insertBefore(cards[index + 1], card);
		}
		renumberSectionInputs();
		updateOutline();
		markDirty();
		selectSection(getSectionCards().indexOf(card), false);
		schedulePreviewRefresh(120);
	});

	function highlightFrameSection(index) {
		if (!previewFrame) return;
		try {
			var doc = previewFrame.contentDocument;
			if (!doc) return;
			doc.querySelectorAll("[data-jvb-preview-index]").forEach(function (wrapper) {
				var item = wrapper.firstElementChild;
				if (item) item.classList.toggle("jvb-builder-selected", Number(wrapper.getAttribute("data-jvb-preview-index")) === index);
			});
		} catch (error) {
			// Cross-origin fallback preview stays read-only; form selection remains available.
		}
	}

	function refreshLivePreview() {
		if (!window.fetch || !window.FormData || !config.ajaxUrl || !config.previewNonce) {
			setPreviewStatus("پیش‌نمایش زنده در این مرورگر آماده نشد؛ تغییرها همچنان در پیش‌نویس باقی می‌مانند.", true);
			return Promise.resolve(false);
		}

		var requestId = ++previewRequestNumber;
		if (previewAbortController) previewAbortController.abort();
		previewAbortController = window.AbortController ? new window.AbortController() : null;
		var payload = new window.FormData(form);
		payload.set("action", config.previewAction || "jluxe_visual_builder_preview");
		payload.set("nonce", config.previewNonce);
		setPreviewLoading(true);
		setPreviewStatus("در حال رندر پیش‌نمایش از روی تغییرهای فعلی…", false);

		var requestOptions = {
			method: "POST",
			credentials: "same-origin",
			body: payload,
			headers: { "X-Requested-With": "XMLHttpRequest" },
		};
		if (previewAbortController) requestOptions.signal = previewAbortController.signal;

		return window.fetch(config.ajaxUrl, requestOptions).then(function (response) {
			if (!response.ok) throw new Error("Preview request failed: " + response.status);
			return response.json();
		}).then(function (result) {
			if (requestId !== previewRequestNumber) return false;
			previewAbortController = null;
			if (!result || result.success !== true || !result.data || !result.data.url) {
				throw new Error(result && result.data && result.data.message ? result.data.message : "پاسخ پیش‌نمایش معتبر نیست.");
			}

			latestPreviewUrl = String(result.data.url);
			if (openPreviewLink) openPreviewLink.href = latestPreviewUrl;
			if (previewFrame && previewFrame.getAttribute("src") !== latestPreviewUrl) {
				previewFrame.setAttribute("src", latestPreviewUrl);
			} else {
				setPreviewLoading(false);
			}
			setPreviewStatus("پیش‌نمایش زنده با آخرین تغییرها به‌روز شد؛ برای دیدن نسخهٔ کامل، ذخیره یا انتشار لازم نیست.", false);
			return true;
		}).catch(function (error) {
			if (requestId !== previewRequestNumber) return false;
			previewAbortController = null;
			if (error && error.name === "AbortError") return false;
			setPreviewLoading(false);
			setPreviewStatus(error && error.message ? error.message : "ساخت پیش‌نمایش زنده ممکن نشد.", true);
			return false;
		});
	}

	function schedulePreviewRefresh(delay) {
		if (previewRequestTimer) window.clearTimeout(previewRequestTimer);
		previewRequestTimer = window.setTimeout(function () {
			previewRequestTimer = null;
			refreshLivePreview();
		}, typeof delay === "number" ? delay : 360);
	}

	function getTransferTypes(dataTransfer) {
		if (!dataTransfer || !dataTransfer.types) return [];
		try {
			return Array.prototype.slice.call(dataTransfer.types);
		} catch (error) {
			return [];
		}
	}

	function getSectionType(dataTransfer) {
		if (dataTransfer && typeof dataTransfer.getData === "function") {
			try {
				var type = dataTransfer.getData("application/x-jluxe-section");
				if (type) return type;
				type = dataTransfer.getData("text/plain");
				if (type && type.indexOf("move:") !== 0) return type;
			} catch (error) {
				// Some browsers expose transfer data only during drop.
			}
		}
		return draggedType;
	}

	function isLibraryDrag(event) {
		var types = getTransferTypes(event.dataTransfer);
		return Boolean(draggedType) || types.indexOf("application/x-jluxe-section") !== -1;
	}

	function showFallbackDropOverlay() {
		root.classList.add("jvb-is-dragging");
		if (!frameDropSupported && previewDropOverlay) previewDropOverlay.hidden = false;
	}

	function clearDragState() {
		draggedType = "";
		root.classList.remove("jvb-is-dragging");
		if (previewDropOverlay) previewDropOverlay.hidden = true;
	}

	function getTypeLabel(type) {
		var components = root.querySelectorAll("[data-jvb-add-section]");
		for (var i = 0; i < components.length; i += 1) {
			if (components[i].getAttribute("data-type") === type) return components[i].textContent.trim().replace(/\s+/g, " ");
		}
		return type || "بخش";
	}

	function addSection(type, insertIndex) {
		var selector = document.getElementById("jluxe-hb-add-type");
		var addButton = document.getElementById("jluxe-hb-add");
		if (!selector || !addButton || !type) return;

		queueHistoryCapture("افزودن «" + getTypeLabel(type) + "»");
		var oldCount = getSectionCards().length;
		selector.value = type;
		addButton.click();
		window.setTimeout(function () {
			var cards = getSectionCards();
			if (cards.length <= oldCount) {
				setPreviewStatus("این بخش به کتابخانهٔ صفحه‌ساز وصل نیست و اضافه نشد.", true);
				return;
			}

			var added = cards[cards.length - 1];
			if (typeof insertIndex === "number" && cards.length > 1) {
				var targetIndex = Math.max(0, Math.min(cards.length - 1, insertIndex));
				var target = cards[targetIndex];
				if (target && target !== added) sections.insertBefore(added, target);
				renumberSectionInputs();
				cards = getSectionCards();
				added = cards.indexOf(added) !== -1 ? added : cards[cards.length - 1];
			}

			updateOutline();
			markDirty();
			if (added) {
				var details = added.querySelector("details");
				if (details) details.open = true;
				var newIndex = getSectionCards().indexOf(added);
				selectSection(newIndex, false);
			}
			schedulePreviewRefresh(120);
		}, 0);
	}

	// پنل کتابخانه: انتخاب تب، جست‌وجو و افزودن با کلیک.
	root.querySelectorAll("[data-jvb-tab]").forEach(function (tab) {
		tab.addEventListener("click", function () {
			activatePanel(tab.getAttribute("data-jvb-tab") || "components");
		});
	});
	var tabsNavigation = root.querySelector(".jvb-tabs");
	if (tabsNavigation) {
		tabsNavigation.addEventListener("keydown", function (event) {
			var tabs = Array.prototype.slice.call(root.querySelectorAll("[data-jvb-tab]"));
			var currentIndex = tabs.indexOf(event.target);
			if (currentIndex < 0 || !tabs.length) return;
			var horizontal = tabsNavigation.getAttribute("aria-orientation") === "horizontal";
			var direction = 0;
			if (horizontal && "ArrowRight" === event.key) direction = 1;
			if (horizontal && "ArrowLeft" === event.key) direction = -1;
			if (!horizontal && "ArrowDown" === event.key) direction = 1;
			if (!horizontal && "ArrowUp" === event.key) direction = -1;
			if ("Home" === event.key) direction = -tabs.length;
			if ("End" === event.key) direction = tabs.length;
			if (!direction) return;
			event.preventDefault();
			var nextIndex = Math.max(0, Math.min(tabs.length - 1, currentIndex + direction));
			tabs[nextIndex].focus();
			activatePanel(tabs[nextIndex].getAttribute("data-jvb-tab") || "components");
		});
	}

	if (undoButton) undoButton.addEventListener("click", function () { stepHistory(-1); });
	if (redoButton) redoButton.addEventListener("click", function () { stepHistory(1); });
	if (historyList) {
		historyList.addEventListener("click", function (event) {
			var button = event.target && typeof event.target.closest === "function" ? event.target.closest("[data-jvb-history-id]") : null;
			if (!button) return;
			flushHistoryCapture();
			var id = parseInt(button.getAttribute("data-jvb-history-id"), 10);
			var index = historyEntries.findIndex(function (entry) { return entry.id === id; });
			if (index >= 0) restoreHistoryEntry(index);
		});
	}

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
				group.hidden = !group.querySelector("[data-jvb-add-section]:not([hidden])");
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
			draggedType = button.getAttribute("data-type") || "";
			event.dataTransfer.effectAllowed = "copy";
			event.dataTransfer.setData("text/plain", draggedType);
			event.dataTransfer.setData("application/x-jluxe-section", draggedType);
			showFallbackDropOverlay();
		});
		button.addEventListener("dragend", clearDragState);
	});

	// مرورگرهای قدیمی/فریم‌های cross-origin از لایهٔ امنِ روی iframe استفاده می‌کنند.
	if (previewDropOverlay) {
		previewDropOverlay.addEventListener("dragover", function (event) {
			if (!isLibraryDrag(event)) return;
			event.preventDefault();
			if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
		});
		previewDropOverlay.addEventListener("drop", function (event) {
			var type = getSectionType(event.dataTransfer);
			if (!type) return;
			event.preventDefault();
			clearDragState();
			addSection(type, getSectionCards().length);
		});
	}

	// در iframe همان صفحهٔ واقعیِ پوسته رندر می‌شود؛ رویدادهای drop و انتخابِ
	// بخش را به parent وصل می‌کنیم تا با کلیک، تنظیمات همان ویجت باز شود.
	function installFrameInteractions() {
		if (!previewFrame) return;
		try {
			var doc = previewFrame.contentDocument;
			if (!doc || !doc.documentElement || !doc.body) {
				frameDropSupported = false;
				return;
			}

			frameDropSupported = true;
			if (!doc.__jluxeVisualBuilderHistoryKeys) {
				doc.addEventListener("keydown", handleHistoryShortcut, true);
				doc.__jluxeVisualBuilderHistoryKeys = true;
			}
			if (doc.__jluxeVisualBuilderBound) {
				highlightFrameSection(selectedSectionIndex);
				return;
			}
			doc.__jluxeVisualBuilderBound = true;

			var style = doc.createElement("style");
			style.setAttribute("data-jluxe-visual-builder-style", "true");
			style.textContent = [
				".jvb-preview-section{display:contents!important}",
				".jvb-preview-section > *{transition:outline-color .12s ease,filter .12s ease}",
				".jvb-preview-section > *.jvb-builder-selected{outline:2px solid #A313B9!important;outline-offset:3px!important}",
				".jvb-preview-section > *.jvb-builder-drop-target{outline:2px dashed #A313B9!important;outline-offset:3px!important}",
				"#jvb-editor-drop-indicator{position:fixed;z-index:2147483647;left:10px;right:10px;height:0;border-top:3px solid #A313B9;pointer-events:none;filter:drop-shadow(0 1px 2px rgba(0,0,0,.2))}",
				"#jvb-editor-drop-indicator:after{content:'';position:absolute;inset-inline-end:0;top:-7px;width:11px;height:11px;border-radius:50%;background:#A313B9}",
			].join("");
			doc.head.appendChild(style);

			var marker = doc.createElement("div");
			marker.id = "jvb-editor-drop-indicator";
			marker.setAttribute("aria-hidden", "true");
			marker.hidden = true;
			doc.body.appendChild(marker);

			function getEventElement(event) {
				var target = event.target;
				return target && target.nodeType === 1 ? target : (target && target.parentElement ? target.parentElement : null);
			}

			function getWrapper(event) {
				var target = getEventElement(event);
				return target && typeof target.closest === "function" ? target.closest("[data-jvb-preview-index]") : null;
			}

			function getInsertionIndex(event) {
				var cards = getSectionCards();
				var wrapper = getWrapper(event);
				if (wrapper) {
					var index = parseInt(wrapper.getAttribute("data-jvb-preview-index"), 10);
					var content = wrapper.firstElementChild;
					var rect = content ? content.getBoundingClientRect() : wrapper.getBoundingClientRect();
					var after = rect.height > 0 && event.clientY >= rect.top + rect.height / 2;
					return Math.max(0, Math.min(cards.length, index + (after ? 1 : 0)));
				}

				var wrappers = Array.prototype.slice.call(doc.querySelectorAll("[data-jvb-preview-index]"));
				for (var i = 0; i < wrappers.length; i += 1) {
					var section = wrappers[i].firstElementChild;
					if (!section) continue;
					var sectionRect = section.getBoundingClientRect();
					if (sectionRect.height > 0 && event.clientY < sectionRect.top + sectionRect.height / 2) {
						return Math.max(0, Math.min(cards.length, parseInt(wrappers[i].getAttribute("data-jvb-preview-index"), 10)));
					}
				}
				return cards.length;
			}

			function setDropMarker(event) {
				var wrapper = getWrapper(event);
				var y = event.clientY;
				if (wrapper && wrapper.firstElementChild) {
					var rect = wrapper.firstElementChild.getBoundingClientRect();
					if (rect.height > 0) y = event.clientY < rect.top + rect.height / 2 ? rect.top : rect.bottom;
					var target = wrapper.firstElementChild;
					if (activeFrameTarget && activeFrameTarget !== target) activeFrameTarget.classList.remove("jvb-builder-drop-target");
					activeFrameTarget = target;
					target.classList.add("jvb-builder-drop-target");
				} else if (activeFrameTarget) {
					activeFrameTarget.classList.remove("jvb-builder-drop-target");
					activeFrameTarget = null;
				}
				marker.style.top = Math.round(y) + "px";
				marker.hidden = false;
			}

			function hideDropMarker() {
				marker.hidden = true;
				if (activeFrameTarget) activeFrameTarget.classList.remove("jvb-builder-drop-target");
				activeFrameTarget = null;
			}

			doc.addEventListener("click", function (event) {
				var wrapper = getWrapper(event);
				if (!wrapper) return;
				var index = parseInt(wrapper.getAttribute("data-jvb-preview-index"), 10);
				if (!Number.isInteger(index)) return;
				event.preventDefault();
				event.stopPropagation();
				selectSection(index, true);
			}, true);

			doc.addEventListener("dragover", function (event) {
				if (!isLibraryDrag(event)) return;
				event.preventDefault();
				if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
				root.classList.add("jvb-is-dragging");
				setDropMarker(event);
			}, true);

			doc.addEventListener("drop", function (event) {
				if (!isLibraryDrag(event)) return;
				var type = getSectionType(event.dataTransfer);
				if (!type) return;
				event.preventDefault();
				event.stopPropagation();
				var insertIndex = getInsertionIndex(event);
				hideDropMarker();
				clearDragState();
				addSection(type, insertIndex);
			}, true);

			doc.addEventListener("dragleave", function (event) {
				if (!event.relatedTarget || !doc.documentElement.contains(event.relatedTarget)) hideDropMarker();
			}, true);
			doc.addEventListener("dragend", function () {
				hideDropMarker();
				clearDragState();
			}, true);
			highlightFrameSection(selectedSectionIndex);
		} catch (error) {
			frameDropSupported = false;
		}
	}

	if (previewFrame) {
		previewFrame.addEventListener("load", function () {
			var currentUrl = previewFrame.getAttribute("src") || "";
			if (!latestPreviewUrl || currentUrl === latestPreviewUrl) {
				setPreviewLoading(false);
				installFrameInteractions();
			}
		});
	}

	if (settingsCanvas) {
		settingsCanvas.addEventListener("dragover", function (event) {
			if (!isLibraryDrag(event)) return;
			event.preventDefault();
			if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
			sections.classList.add("jvb-drop-active");
		});
		settingsCanvas.addEventListener("dragleave", function (event) {
			if (!settingsCanvas.contains(event.relatedTarget)) sections.classList.remove("jvb-drop-active");
		});
		settingsCanvas.addEventListener("drop", function (event) {
			var type = getSectionType(event.dataTransfer);
			if (!type) return;
			event.preventDefault();
			sections.classList.remove("jvb-drop-active");
			clearDragState();
			addSection(type, getSectionCards().length);
		});
	}

	// نمایهٔ ساختار به همان فرم‌ها وصل است؛ انتخاب هر ردیف، تنظیمات همان بخش را باز می‌کند.
	if (outline) {
		outline.addEventListener("click", function (event) {
			var button = event.target.closest("[data-jvb-outline-target]");
			if (!button) return;
			var index = parseInt(button.getAttribute("data-jvb-outline-target"), 10);
			selectSection(index, false);
		});
	}

	// تغییر اندازهٔ بوم، عرض viewport واقعیِ iframe را تغییر می‌دهد.
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

	function togglePreviewFocus(isFocused) {
		if (!livePreview) return;
		root.classList.toggle("jvb-preview-focus", isFocused);
		if (previewToggle) previewToggle.setAttribute("aria-pressed", isFocused ? "true" : "false");
		if (previewLabel) previewLabel.textContent = isFocused ? "بازگشت به ویرایش" : "تمرکز روی پیش‌نمایش";
		if (stageTitle) stageTitle.textContent = isFocused ? "پیش‌نمایش زندهٔ تمام‌صفحه" : "پیش‌نمایش زندهٔ صفحهٔ اصلی";
		if (stageSubtitle) stageSubtitle.textContent = isFocused
			? "در حال مشاهدهٔ همان طرحی هستید که با تغییرها ساخته می‌شود؛ هنوز منتشر نشده است"
			: "تغییراتِ ذخیره‌نشده فقط در این بومِ خصوصی نمایش داده می‌شوند";
	}

	if (previewToggle) {
		previewToggle.addEventListener("click", function () {
			togglePreviewFocus(previewToggle.getAttribute("aria-pressed") !== "true");
		});
	}

	// تغییر فیلدها، افزودن/حذف و مرتب‌سازی، هم وضعیت را به‌روز می‌کند و هم
	// پس از مکث کوتاه همان خروجی واقعیِ PHP را در قاب خصوصی دوباره رندر می‌کند.
	function isHomepageField(target) {
		return Boolean(target && target.name && target.name.indexOf("homepage[") === 0);
	}

	function getFieldHistoryLabel(target) {
		var card = target && typeof target.closest === "function" ? target.closest(".jluxe-hb-section") : null;
		return card ? "ویرایش «" + getSectionLabel(card) + "»" : "ویرایش تنظیمات کلی صفحه";
	}

	form.addEventListener("click", function (event) {
		var button = event.target && typeof event.target.closest === "function" ? event.target.closest(".jluxe-hb-duplicate, .jluxe-hb-remove") : null;
		if (!button) return;
		var card = button.closest(".jluxe-hb-section");
		if (!card) return;
		var label = getSectionLabel(card);
		var isDuplicate = button.classList.contains("jluxe-hb-duplicate");
		queueHistoryCapture(isDuplicate ? "تکثیر «" + label + "»" : "حذف «" + label + "»");
		if (!isDuplicate && card.classList.contains("jvb-selected")) {
			window.setTimeout(function () {
				if (!sections.contains(card)) {
					selectedSectionIndex = -1;
					updatePanelMetadata();
				}
			}, 0);
		}
	}, true);

	form.addEventListener("input", function (event) {
		if (!isHomepageField(event.target)) return;
		queueHistoryCapture(getFieldHistoryLabel(event.target));
		markDirty();
		schedulePreviewRefresh();
	});
	form.addEventListener("change", function (event) {
		if (!isHomepageField(event.target)) return;
		queueHistoryCapture(getFieldHistoryLabel(event.target));
		markDirty();
		if (event.target.closest(".jluxe-hb-section")) updateOutline();
		schedulePreviewRefresh();
	});

	if (window.MutationObserver) {
		var observer = new MutationObserver(function (records) {
			var sectionsChanged = records.some(function (record) {
				return record.target === sections || sections.contains(record.target);
			});
			if (sectionsChanged && !ignoreHistoryMutations && !historyRestoring) {
				queueHistoryCapture("تغییر ساختار");
				updateOutline();
				markDirty();
				schedulePreviewRefresh(220);
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
	document.addEventListener("keydown", handleHistoryShortcut, true);

	if (previewFrame && previewFrame.getAttribute("src")) latestPreviewUrl = previewFrame.getAttribute("src");
	setPreviewLoading(true);
	ignoreHistoryMutations = true;
	updateOutline();
	var initialHistorySnapshot = captureHistorySnapshot("شروع صفحه‌ساز");
	initialHistorySnapshot.id = nextHistoryId;
	nextHistoryId += 1;
	historyEntries.push(initialHistorySnapshot);
	historyIndex = 0;
	renderHistoryPanel();
	activatePanel("components");
	window.setTimeout(function () {
		ignoreHistoryMutations = false;
	}, 0);
	schedulePreviewRefresh(0);
}(jQuery));
