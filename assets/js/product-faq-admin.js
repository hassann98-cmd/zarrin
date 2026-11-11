/**
 * متاباکسِ «سوالات متداول محصول» (inc/product-faq.php) — افزودن/حذف/
 * جابه‌جایی ردیف‌های سوال/پاسخ در ویرایش محصول و ورود یک‌جای FAQ.
 * مستقل از theme-settings-admin.js است که فقط صفحهٔ تنظیمات پوسته را پوشش می‌دهد.
 *
 * PHP هنگام ذخیره‌سازی روی مقدارها پیمایش می‌کند، نه روی کلیدهای عددی؛ بنابراین
 * ردیف‌های تازه نام یکتا می‌گیرند و نیازی به renumber کردن نام فیلدها نیست.
 * متن کلیپ‌بورد فقط به‌صورت متن امن پردازش می‌شود و در فیلدهای جدا قرار می‌گیرد.
 */
(function ($) {
	"use strict";

	if (!$) {
		return;
	}

	function initProductFaqAdmin() {
		var $wrap = $("#jluxe-faq-items");
		var $tpl = $("#jluxe-faq-item-template");
		if (!$wrap.length || !$tpl.length) {
			return;
		}

		var $bulkText = $("#jluxe-faq-bulk-paste");
		var $replaceButton = $("#jluxe-faq-import-replace");
		var $appendButton = $("#jluxe-faq-import-append");
		var $bulkStatus = $("#jluxe-faq-bulk-status");
		var importedItems = [];
		var importedOverflow = false;
		var uniqueIndex = 0;

		function toPersianDigits(value) {
			return String(value).replace(/[0-9]/g, function (digit) {
				return "۰۱۲۳۴۵۶۷۸۹".charAt(Number(digit));
			});
		}

		function relabelItems() {
			$wrap.children(".jluxe-faq-item").each(function (index) {
				$(this)
					.find(".jluxe-faq-item-title")
					.text("سوال " + toPersianDigits(index + 1));
			});
		}

		function questionFields($item) {
			return {
				question: $item.find('input[name$="[question]"]').first(),
				answer: $item.find('textarea[name$="[answer]"]').first(),
			};
		}

		function addFaqItem(item, shouldFocus) {
			uniqueIndex += 1;
			var index = "new" + Date.now() + "_" + uniqueIndex;
			var html = $.trim($tpl.html()).replace(/__INDEX__/g, index);
			var $newItem = $(html);
			$wrap.append($newItem);
			if (item) {
				var fields = questionFields($newItem);
				fields.question.val(item.question);
				fields.answer.val(item.answer);
			}
			if (shouldFocus) {
				$newItem.find('input[type="text"]').first().trigger("focus");
			}
			return $newItem;
		}

		function normalizeClipboardText(value) {
			return String(value == null ? "" : value)
				.replace(/\r\n?/g, "\n")
				.replace(/\u00a0/g, " ")
				.replace(/[\u200e\u200f\u202a-\u202e\u2066-\u2069]/g, "")
				.replace(/[ \t]+\n/g, "\n")
				.replace(/\n{3,}/g, "\n\n")
				.trim();
		}

		function stripQuestionPrefix(value) {
			var text = normalizeClipboardText(value).replace(/\n/g, " ").replace(/\s+/g, " ").trim();
			var prefix = /^\s*(?:(?:[0-9۰-۹٠-٩]+)\s*[.٫、)）:：-]\s*|(?:سوال|سؤال|پرسش)\s*(?:[0-9۰-۹٠-٩]+\s*)?[:：.)-]\s*|(?:q|question)\s*[:：]\s*)/i;
		var hadPrefix = prefix.test(text);
		text = text.replace(prefix, "").replace(/^[\s*_`]+|[\s*_`]+$/g, "").trim();
		return {
			text: text,
			hadPrefix: hadPrefix,
		};
		}

		function looksLikeQuestion(value, hadPrefix) {
			var text = String(value || "").trim();
			if (!text) {
				return false;
			}
			if (hadPrefix || /[؟?]/.test(text)) {
				return true;
			}
			return /^(?:آیا|چرا|چه|کدام|کدامین|چگونه|چطور|کجا|کی|چقدر|چه‌قدر|چند|برای\s+چه|what|why|how|when|where|which|who|can|could|does|do|is|are|will)\b/i.test(text);
		}

		function parseQuestionHeading(value) {
			var parsed = stripQuestionPrefix(value);
			return {
				question: parsed.text,
				isQuestion: looksLikeQuestion(parsed.text, parsed.hadPrefix),
			};
		}

		function cleanAnswer(value) {
			return normalizeClipboardText(value)
				.replace(/\*\*([\s\S]*?)\*\*/g, "$1")
				.replace(/__([\s\S]*?)__/g, "$1")
				.replace(/(^|\n)\s*[-*•]\s+/g, "$1")
				.trim();
		}

		function parseBoldQuestionPairs(text) {
			var source = String(text || "");
			var pattern = /\*\*([\s\S]*?)\*\*|__([\s\S]*?)__/g;
			var matches = [];
			var match;
			while ((match = pattern.exec(source))) {
				var heading = parseQuestionHeading(match[1] != null ? match[1] : match[2]);
				if (heading.isQuestion && heading.question) {
					matches.push({
						question: heading.question,
						start: match.index,
						answerStart: pattern.lastIndex,
					});
				}
			}

			var items = [];
			matches.forEach(function (current, index) {
				var answerEnd = index + 1 < matches.length ? matches[index + 1].start : source.length;
				var answer = cleanAnswer(source.slice(current.answerStart, answerEnd));
				if (current.question && answer) {
					items.push({ question: current.question, answer: answer });
				}
			});
			return items;
		}

		function splitInlineQuestionAndAnswer(value) {
			var text = String(value || "").trim();
			var punctuation = /[؟?]/.exec(text);
			if (!punctuation) {
				return { question: text, answer: "" };
			}
			var end = punctuation.index + punctuation[0].length;
			return {
				question: text.slice(0, end).trim(),
				answer: text.slice(end).trim(),
			};
		}

		function parseLineQuestionPairs(text) {
			var lines = normalizeClipboardText(text).split("\n");
			var items = [];
			var current = null;

			function finishCurrent() {
				if (current && current.question && cleanAnswer(current.answer)) {
					items.push({ question: current.question, answer: cleanAnswer(current.answer) });
				}
				current = null;
			}

			lines.forEach(function (rawLine) {
				var line = String(rawLine || "").trim();
				if (!line) {
					return;
				}
				line = line
					.replace(/^\*\*([\s\S]*)\*\*$/, "$1")
					.replace(/^__([\s\S]*)__$/, "$1")
					.trim();
				var heading = parseQuestionHeading(line);
				if (heading.isQuestion && heading.question) {
					finishCurrent();
					var split = splitInlineQuestionAndAnswer(heading.question);
					current = { question: split.question, answer: split.answer };
					return;
				}
				if (current) {
					current.answer = current.answer ? current.answer + "\n" + line : line;
				}
				// Unnumbered headings before the first question are intentionally ignored.
			});

			finishCurrent();
			return items;
		}

		function htmlToMarkedText(html) {
			if (!html || typeof window.DOMParser !== "function") {
				return "";
			}
			var safeHtml = String(html)
				.replace(/<!--[\s\S]*?-->/g, "")
				.replace(/<(script|style|iframe|object|embed|noscript|template|svg)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, "")
				.replace(/<(script|style|iframe|object|embed|noscript|template|svg)\b[^>]*\/?\s*>/gi, "")
				.replace(/<([a-z][a-z0-9:-]*)\b([^>]*)>/gi, function (_tagText, tagName, attributes) {
					// Keep only inert inline style for detecting bold Word spans; remove
					// URLs, event attributes and every other copied HTML attribute.
					var styleMatch = /\bstyle\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i.exec(attributes || "");
					var style = styleMatch ? (styleMatch[1] || styleMatch[2] || styleMatch[3] || "") : "";
					style = style.replace(/url\s*\([^)]*\)/gi, "").replace(/[\u0000-\u001f]/g, " ");
					style = style.replace(/&/g, "&amp;").replace(/"/g, "&quot;");
					return "<" + String(tagName).toLowerCase() + (style ? " style=\"" + style + "\"" : "") + ">";
				});

			var parsedDocument;
			try {
				parsedDocument = new window.DOMParser().parseFromString(safeHtml, "text/html");
			} catch (error) {
				return "";
			}

			var blockTags = /^(?:address|article|blockquote|div|h[1-6]|li|ol|p|section|tr|ul)$/;
			function serialize(node) {
				if (node.nodeType === 3) {
					return node.nodeValue || "";
				}
				if (node.nodeType !== 1) {
					return "";
				}
				var tag = String(node.tagName || "").toLowerCase();
				if (/^(?:script|style|iframe|object|embed|noscript|template|svg)$/.test(tag)) {
					return "";
				}
				if (tag === "br") {
					return "\n";
				}
				var content = "";
				Array.prototype.forEach.call(node.childNodes, function (child) {
					content += serialize(child);
				});
				if (tag === "strong" || tag === "b" || (tag === "span" && /font-weight\s*:\s*(?:bold|[6-9]00)/i.test(node.getAttribute("style") || ""))) {
					content = "**" + content + "**";
				}
				if (tag === "td" || tag === "th") {
					return content + "\t";
				}
				return blockTags.test(tag) ? "\n" + content + "\n" : content;
			}

			return normalizeClipboardText(serialize(parsedDocument.body));
		}

		function parseFaqContent(plainText, html) {
			var htmlText = htmlToMarkedText(html);
			var sources = [htmlText, normalizeClipboardText(plainText)];
			for (var index = 0; index < sources.length; index += 1) {
				if (!sources[index]) {
					continue;
				}
				var boldItems = parseBoldQuestionPairs(sources[index]);
				if (boldItems.length) {
					return { items: boldItems.slice(0, 100), overflow: boldItems.length > 100 };
				}
			}
			for (var lineIndex = 0; lineIndex < sources.length; lineIndex += 1) {
				if (!sources[lineIndex]) {
					continue;
				}
				var lineItems = parseLineQuestionPairs(sources[lineIndex]);
				if (lineItems.length) {
					return { items: lineItems.slice(0, 100), overflow: lineItems.length > 100 };
				}
			}
			return { items: [], overflow: false };
		}

		function formatFaqItems(items) {
			return items.map(function (item) {
				return item.question + "\n" + item.answer;
			}).join("\n\n");
		}

		function updateBulkImport(parsed) {
			importedItems = parsed && Array.isArray(parsed.items) ? parsed.items : [];
			importedOverflow = !!(parsed && parsed.overflow);
			var ready = importedItems.length > 0;
			$replaceButton.prop("disabled", !ready);
			$appendButton.prop("disabled", !ready);
			if (!ready) {
				$bulkStatus.text($bulkText.val().trim() ? "سوالی با جواب پیدا نشد؛ متن شماره‌گذاری‌شده یا **سوال؟** جواب را بررسی کنید." : "");
				return;
			}
			var message = toPersianDigits(importedItems.length) + " جفت سوال و جواب شناسایی شد. برای فهرست تازه «جایگزینی» و برای نگه‌داشتن موارد فعلی «افزودن» را انتخاب کنید.";
			if (importedOverflow) {
				message += " فقط ۱۰۰ مورد نخست پذیرفته می‌شود.";
			}
			$bulkStatus.text(message);
		}

		function applyImportedItems(mode) {
			if (!importedItems.length) {
				return;
			}
			var currentItems = $wrap.children(".jluxe-faq-item");
			if (mode === "replace" && currentItems.length && !window.confirm("همهٔ سوال‌ها و جواب‌های فعلی در این فرم با " + toPersianDigits(importedItems.length) + " مورد جایگزین شوند؟")) {
				$bulkStatus.text("جایگزینی انجام نشد؛ موارد فعلی دست‌نخورده ماندند.");
				return;
			}

			var rows = importedItems.slice(0, 100);
			if (mode === "replace") {
				currentItems.remove();
			} else {
				var available = Math.max(0, 100 - currentItems.length);
				rows = rows.slice(0, available);
				if (!rows.length) {
					$bulkStatus.text("فهرست فعلی به سقف ۱۰۰ سوال رسیده است؛ برای ورود این مجموعه «جایگزینی» را انتخاب کنید.");
					return;
				}
			}

			rows.forEach(function (item) {
				addFaqItem(item, false);
			});
			relabelItems();
			var message = toPersianDigits(rows.length) + (mode === "replace" ? " سوال و جواب جایگزین شد؛ با ذخیرهٔ محصول ثبت می‌شود." : " سوال و جواب به انتهای فهرست افزوده شد؛ با ذخیرهٔ محصول ثبت می‌شود.");
			if (rows.length < importedItems.length) {
				message += " " + toPersianDigits(importedItems.length - rows.length) + " مورد به‌دلیل سقف ۱۰۰ سوال وارد نشد.";
			}
			$bulkStatus.text(message);
			importedItems = [];
			importedOverflow = false;
			$replaceButton.prop("disabled", true);
			$appendButton.prop("disabled", true);
		}

		$("#jluxe-add-faq-item").on("click", function () {
			addFaqItem(null, true);
			relabelItems();
		});

		$wrap.on("click", ".jluxe-faq-remove-item", function () {
			var $item = $(this).closest(".jluxe-faq-item");
			var fields = questionFields($item);
			var hasContent = fields.question.val() || fields.answer.val();
			if (hasContent && !window.confirm("این سوال حذف بشه؟")) {
				return;
			}
			$item.remove();
			relabelItems();
		});

		$replaceButton.on("click", function () {
			applyImportedItems("replace");
		});
		$appendButton.on("click", function () {
			applyImportedItems("append");
		});

		$bulkText.on("input", function () {
			updateBulkImport(parseFaqContent(this.value, ""));
		});
		$bulkText.on("paste", function (event) {
			var originalEvent = event.originalEvent || event;
			var clipboard = originalEvent.clipboardData || window.clipboardData;
			var html = "";
			var plainText = "";
			try {
				html = clipboard && clipboard.getData ? clipboard.getData("text/html") : "";
				plainText = clipboard && clipboard.getData ? (clipboard.getData("text/plain") || clipboard.getData("Text")) : "";
			} catch (error) {
				html = "";
				plainText = "";
			}
			var parsed = parseFaqContent(plainText, html);
			if (parsed.items.length) {
				event.preventDefault();
				this.value = plainText || formatFaqItems(parsed.items);
				updateBulkImport(parsed);
				return;
			}
			var textarea = this;
			window.setTimeout(function () {
				updateBulkImport(parseFaqContent(textarea.value, ""));
			}, 0);
		});

		if ($.fn.sortable) {
			$wrap.sortable({
				handle: ".jluxe-faq-drag-handle",
				axis: "y",
				update: relabelItems,
			});
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initProductFaqAdmin, { once: true });
	} else {
		initProductFaqAdmin();
	}
})(window.jQuery);
