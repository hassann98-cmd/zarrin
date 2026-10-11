(function () {
	"use strict";

	var root = document.getElementById("jluxe-product-manager");
	var config = window.jluxeProductManager;
	if (!root || !config || !config.ajaxUrl || !config.nonce) return;

	var state = {
		page: 1,
		pages: 1,
		total: 0,
		products: [],
		productSaves: Object.create(null),
		variationData: Object.create(null),
		variationLoading: Object.create(null),
		variationRequests: Object.create(null),
		variationDraft: Object.create(null),
		variationSaves: Object.create(null),
		defaultSaves: Object.create(null),
		openVariations: Object.create(null),
		currentProduct: null,
		lastFocus: null,
		editorRequest: 0,
		loading: false,
		pendingReload: null,
		saving: false,
		dirty: false,
	};

	var $ = function (selector, scope) { return (scope || root).querySelector(selector); };
	var $$ = function (selector, scope) { return Array.prototype.slice.call((scope || root).querySelectorAll(selector)); };
	var rows = $("#jluxe-pm-rows");
	var editor = $("#jluxe-pm-editor");
	var editorBody = $("#jluxe-pm-editor-body");
	var editorCard = $(".jluxe-pm-editor-card", editor);
	var notice = $("#jluxe-pm-notice");

	function faNumber(value) {
		try { return Number(value || 0).toLocaleString("fa-IR"); } catch (error) { return String(value || 0); }
	}

	function node(tag, className, text) {
		var result = document.createElement(tag);
		if (className) result.className = className;
		if (text !== undefined && text !== null) result.textContent = String(text);
		return result;
	}

	function showNotice(message, type) {
		notice.hidden = !message;
		notice.className = "jluxe-pm-notice" + (type ? " is-" + type : "");
		notice.textContent = message || "";
	}

	function normalizeNumber(value) {
		return String(value == null ? "" : value)
			.replace(/[۰-۹]/g, function (digit) { return String("۰۱۲۳۴۵۶۷۸۹".indexOf(digit)); })
			.replace(/[٠-٩]/g, function (digit) { return String("٠١٢٣٤٥٦٧٨٩".indexOf(digit)); })
			.replace(/[٬,،\s\u00a0\u202f]/g, "")
			.replace(/٫/g, ".")
			.trim();
	}

	function groupedNumber(value) {
		var normalized = normalizeNumber(value);
		if (!normalized) return "";
		var match = normalized.match(/^([+-]?)(\d*)(?:\.(\d*))?$/);
		if (!match) return String(value == null ? "" : value);
		var integer = match[2].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		return match[1] + integer + (match[3] === undefined ? "" : "." + match[3]);
	}

	function logicalNumberLength(value) {
		return normalizeNumber(value).replace(/[^0-9.\-+]/g, "").length;
	}

	function groupedCaretPosition(value, logicalOffset) {
		if (logicalOffset <= 0) return 0;
		var seen = 0;
		for (var index = 0; index < value.length; index++) {
			if (value.charAt(index) !== ",") seen++;
			if (seen >= logicalOffset) return index + 1;
		}
		return value.length;
	}

	function formatGroupedInput(input) {
		var original = input.value;
		var start = typeof input.selectionStart === "number" ? input.selectionStart : original.length;
		var end = typeof input.selectionEnd === "number" ? input.selectionEnd : start;
		var startOffset = logicalNumberLength(original.slice(0, start));
		var endOffset = logicalNumberLength(original.slice(0, end));
		var formatted = groupedNumber(original);
		if (formatted !== original) input.value = formatted;
		if (typeof input.setSelectionRange === "function" && document.activeElement === input) {
			input.setSelectionRange(groupedCaretPosition(formatted, startOffset), groupedCaretPosition(formatted, endOffset));
		}
		return normalizeNumber(formatted);
	}

	function safeNavigationUrl(value) {
		if (!value) return "";
		try {
			var url = new URL(String(value), window.location.href);
			return url.protocol === "http:" || url.protocol === "https:" ? url.href : "";
		} catch (error) {
			return "";
		}
	}

	function labelCell(cell, label) {
		cell.dataset.label = label;
		cell.setAttribute("aria-label", label);
		return cell;
	}

	function stockLabel(status) {
		var labels = { instock: "موجود", outofstock: "ناموجود", onbackorder: "پیش‌فروش" };
		return labels[status] || "وضعیت نامشخص";
	}

	function stockClass(status) {
		return status === "outofstock" ? "is-out" : (status === "onbackorder" ? "is-backorder" : "is-in");
	}

	function request(action, data) {
		var body = new URLSearchParams();
		body.set("action", action);
		body.set("nonce", config.nonce);
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { body.append(key + "[]", String(item)); });
			} else if (value && typeof value === "object") {
				body.set(key, JSON.stringify(value));
			} else if (value !== undefined && value !== null) {
				body.set(key, String(value));
			}
		});
		return fetch(config.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
			body: body.toString(),
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json || json.success !== true) {
					var error = new Error(json && json.data && json.data.message ? json.data.message : "درخواست انجام نشد؛ دوباره تلاش کنید.");
					error.partial = !!(json && json.data && json.data.partial);
					throw error;
				}
				return json.data;
			});
		});
	}

	function currentFilters() {
		return {
			search: $("#jluxe-pm-search").value.trim(),
			type: $("#jluxe-pm-type").value,
			status: $("#jluxe-pm-status").value,
			stock: $("#jluxe-pm-stock").value,
			category: $("#jluxe-pm-category").value,
			brand: $("#jluxe-pm-brand").value,
			attribute_taxonomy: $("#jluxe-pm-attribute-taxonomy").value,
			attribute_term: $("#jluxe-pm-attribute-term").value,
		};
	}

	function setLoading(message) {
		rows.replaceChildren();
		var tr = node("tr");
		var td = node("td", "jluxe-pm-loading", message || "در حال دریافت محصولات…");
		td.colSpan = 7;
		tr.appendChild(td);
		rows.appendChild(tr);
	}

	function loadProducts(resetPage) {
		if (resetPage) state.page = 1;
		if (state.loading) {
			state.pendingReload = state.page;
			return;
		}
		state.loading = true;
		showNotice("", "");
		setLoading("در حال دریافت محصولات…");
		var data = currentFilters();
		data.page = state.page;
		request("jluxe_product_manager_list", data).then(function (result) {
			state.loading = false;
			state.page = result.page || 1;
			state.pages = result.pages || 1;
			state.total = result.total || 0;
			state.products = result.products || [];
			renderProducts();
			flushPendingReload();
		}).catch(function (error) {
			state.loading = false;
			setLoading(error.message);
			showNotice(error.message, "error");
			flushPendingReload();
		});
	}

	function flushPendingReload() {
		if (state.pendingReload === null) return;
		state.page = state.pendingReload;
		state.pendingReload = null;
		loadProducts(false);
	}

	function renderProducts() {
		rows.replaceChildren();
		if (!state.products.length) {
			var empty = node("tr");
			var cell = node("td", "jluxe-pm-empty", "محصولی با این جست‌وجو و فیلترها پیدا نشد.");
			cell.colSpan = 7;
			empty.appendChild(cell);
			rows.appendChild(empty);
		} else {
			state.products.forEach(function (product, index) {
				var productRow = makeProductRow(product);
				productRow.classList.toggle("is-alt", index % 2 === 1);
				rows.appendChild(productRow);
				if (product.type === "variable" && state.openVariations[String(product.id)]) {
					var accordion = makeAccordionRow(product);
					accordion.classList.toggle("is-alt", index % 2 === 1);
					rows.appendChild(accordion);
					var panel = $("[data-pm-variation-panel]", accordion);
					var cached = state.variationData[String(product.id)];
					if (cached) renderVariations(panel, product.id, cached);
					else {
						panel.classList.add("is-loading");
						panel.appendChild(node("p", "jluxe-pm-loading", "در حال دریافت تنوع‌ها…"));
						loadVariations(product.id, 1);
					}
				}
			});
		}
		$("#jluxe-pm-summary").textContent = state.total ? faNumber(state.total) + " محصول" : "۰ محصول";
		$("#jluxe-pm-page-label").textContent = "صفحهٔ " + faNumber(state.page) + " از " + faNumber(state.pages);
		$("#jluxe-pm-prev").disabled = state.page <= 1;
		$("#jluxe-pm-next").disabled = state.page >= state.pages;
	}

	function defaultSummary(product) {
		var defaults = product.defaultAttributes || [];
		if (!defaults.length) return "پیش‌فرض تنوع: انتخاب نشده";
		return "پیش‌فرض تنوع: " + defaults.map(function (item) {
			return item.label + " «" + item.valueLabel + "»";
		}).join("، ");
	}

	function makeProductLink(value, label, kind) {
		var href = safeNavigationUrl(value);
		if (!href) return null;
		var link = node("a", "jluxe-pm-product-link jluxe-pm-product-link--" + kind, label);
		link.href = href;
		link.target = "_blank";
		link.rel = "noopener noreferrer";
		link.setAttribute("aria-label", label + " در زبانهٔ جدید");
		return link;
	}

	function makeProductRow(product) {
		var id = String(product.id);
		var tr = node("tr", "jluxe-pm-product-row");
		tr.dataset.productId = id;

		var productCell = node("td", "jluxe-pm-product-cell");
		var image = node("span", "jluxe-pm-thumb");
		if (product.image) {
			var img = node("img");
			img.src = product.image;
			img.alt = "";
			img.loading = "lazy";
			image.appendChild(img);
		} else {
			image.appendChild(node("span", "dashicons dashicons-format-image", ""));
		}
		productCell.appendChild(image);
		var identity = node("div", "jluxe-pm-identity");
		var nameButton = node("button", "jluxe-pm-product-name", product.name || "(بدون نام)");
		nameButton.type = "button";
		nameButton.dataset.pmEdit = id;
		identity.appendChild(nameButton);
		var subline = node("div", "jluxe-pm-subline");
		subline.appendChild(node("span", "jluxe-pm-type-pill is-" + product.type, product.typeLabel || (product.type === "variable" ? "متغیر" : "ساده")));
		if (product.sku) subline.appendChild(node("span", "jluxe-pm-sku", "SKU: " + product.sku));
		var featuredHint = node("small", "jluxe-pm-featured-hint" + (product.featured ? " is-active" : ""), product.featured ? "ویژه انتخاب شده" : "ویژه انتخاب نشده");
		featuredHint.dataset.pmFeaturedHint = id;
		subline.appendChild(featuredHint);
		identity.appendChild(subline);
		if (product.type === "variable") {
			var defaults = node("small", "jluxe-pm-default-summary", defaultSummary(product));
			defaults.dataset.pmDefaultSummary = id;
			identity.appendChild(defaults);
		}
		var productLinks = node("div", "jluxe-pm-product-links");
		var viewLink = makeProductLink(product.productUrl, "مشاهده محصول", "view");
		var editLink = makeProductLink(product.editUrl, "ویرایش ووکامرس", "edit");
		if (viewLink) productLinks.appendChild(viewLink);
		if (editLink) productLinks.appendChild(editLink);
		if (productLinks.childNodes.length) identity.appendChild(productLinks);
		productCell.appendChild(identity);
		tr.appendChild(productCell);

		tr.appendChild(makePriceCell(product, "regularPrice", "قیمت عادی"));
		tr.appendChild(makePriceCell(product, "salePrice", "قیمت ویژه"));
		tr.appendChild(makeFeaturedCell(product));
		tr.appendChild(makeStockCell(product));
		tr.appendChild(labelCell(node("td", "jluxe-pm-category-cell", product.categories || "—"), "دسته‌بندی"));

		var actions = labelCell(node("td", "jluxe-pm-actions"), "عملیات");
		var actionControls = node("div", "jluxe-pm-actions-controls");
		if (product.type === "variable") {
			var variations = node("button", "button button-primary button-small jluxe-pm-expand", "تنوع‌ها " + faNumber(product.variationCount || 0));
			variations.type = "button";
			variations.dataset.pmVariationsToggle = id;
			variations.setAttribute("aria-expanded", state.openVariations[id] ? "true" : "false");
			variations.setAttribute("aria-controls", "jluxe-pm-variations-" + id);
			variations.appendChild(node("span", "jluxe-pm-expand-icon", state.openVariations[id] ? "−" : "+"));
			actionControls.appendChild(variations);
		}
		var organization = node("button", "button button-small jluxe-pm-organization-button", "دسته و برند");
		organization.type = "button";
		organization.dataset.pmEdit = id;
		actionControls.appendChild(organization);
		actions.appendChild(actionControls);
		var status = node("span", "jluxe-pm-row-status");
		status.dataset.pmRowStatus = id;
		status.setAttribute("aria-live", "polite");
		var tracker = state.productSaves[id];
		if (tracker && tracker.status) setStatusContent(status, tracker.status, tracker.kind);
		actions.appendChild(status);
		tr.appendChild(actions);
		return tr;
	}

	function makeSaleState(onSale, salePrice, productType, pending) {
		if (pending) return node("small", "jluxe-pm-sale-state is-pending", "وضعیت ویژه پس از ذخیره به‌روز می‌شود");
		if (onSale) return node("small", "jluxe-pm-sale-state is-active", productType === "variable" ? "تخفیف در تنوع‌ها فعال است" : "تخفیف فعال است");
		if (productType === "variable") return node("small", "jluxe-pm-sale-state", "قیمت ویژهٔ تنوع‌ها را باز کنید");
		if (normalizeNumber(salePrice) !== "") return node("small", "jluxe-pm-sale-state is-inactive", "قیمت ویژه ثبت شده؛ اکنون فعال نیست");
		return node("small", "jluxe-pm-sale-state", "قیمت ویژه ثبت نشده");
	}

	function setProductSaleState(product) {
		var badge = $("[data-pm-sale-state='" + String(product.id) + "']", rows);
		if (!badge) return;
		var stateBadge = makeSaleState(product.onSale, product.salePrice, product.type, product.salePending);
		badge.className = stateBadge.className;
		badge.textContent = stateBadge.textContent;
		var cell = badge.closest(".jluxe-pm-price-cell--sale");
		if (cell) cell.classList.toggle("is-on-sale", !!product.onSale);
	}

	function makePriceCell(product, field, labelText) {
		var suffix = field === "salePrice" ? "--sale" : "--regular";
		var cell = labelCell(node("td", "jluxe-pm-price-cell jluxe-pm-price-cell" + suffix), labelText);
		var id = String(product.id);
		if (field === "salePrice" && product.onSale) cell.classList.add("is-on-sale");
		if (product.type === "simple") {
			var input = node("input", "jluxe-pm-inline-input jluxe-pm-price-input");
			input.type = "text";
			input.inputMode = "decimal";
			input.autocomplete = "off";
			input.value = groupedNumber(product[field]);
			input.placeholder = field === "salePrice" ? "بدون قیمت ویژه" : "ثبت قیمت";
			input.dir = "ltr";
			input.dataset.pmInlineField = field;
			input.dataset.productId = id;
			input.setAttribute("aria-label", labelText + "ِ " + (product.name || "محصول"));
			var control = node("div", "jluxe-pm-price-control");
			control.appendChild(input);
			if (config.currency) control.appendChild(node("small", "jluxe-pm-currency", config.currency));
			cell.appendChild(control);
			if (field === "salePrice") {
				var saleState = makeSaleState(product.onSale, product.salePrice, product.type, product.salePending);
				saleState.dataset.pmSaleState = id;
				cell.appendChild(saleState);
			}
		} else if (field === "regularPrice") {
			cell.appendChild(node("span", "jluxe-pm-readonly-value", product.priceLabel || "—"));
			cell.appendChild(node("small", "jluxe-pm-cell-hint", "بازهٔ قیمت تنوع‌های این محصول"));
		} else {
			cell.appendChild(node("span", "jluxe-pm-readonly-value", product.onSale ? "تخفیف فعال در تنوع‌ها" : "جزئیات قیمت ویژهٔ تنوع‌ها"));
			var variableSaleState = makeSaleState(product.onSale, product.salePrice, product.type, product.salePending);
			variableSaleState.dataset.pmSaleState = id;
			cell.appendChild(variableSaleState);
			cell.appendChild(node("small", "jluxe-pm-cell-hint", "قیمت ویژهٔ هر تنوع در بخش تنوع‌ها دیده می‌شود."));
		}
		return cell;
	}

	function makeFeaturedCell(product) {
		var cell = labelCell(node("td", "jluxe-pm-featured-cell"), "ویژه");
		var label = node("label", "jluxe-pm-toggle jluxe-pm-featured-toggle");
		var input = node("input");
		input.type = "checkbox";
		input.checked = !!product.featured;
		input.dataset.pmInlineField = "featured";
		input.dataset.productId = String(product.id);
		input.setAttribute("aria-label", "ویژه‌کردن " + (product.name || "محصول"));
		label.appendChild(input);
		label.appendChild(node("span", "jluxe-pm-toggle-track"));
		label.appendChild(node("span", "jluxe-pm-toggle-label", "ویژه"));
		cell.appendChild(label);
		return cell;
	}

	function makeStockCell(product) {
		var cell = labelCell(node("td", "jluxe-pm-stock-cell"), "موجودی");
		var id = String(product.id);
		var manageLabel = node("label", "jluxe-pm-manage-stock");
		var manage = node("input");
		manage.type = "checkbox";
		manage.checked = !!product.manageStock;
		manage.dataset.pmInlineField = "manageStock";
		manage.dataset.productId = id;
		manage.setAttribute("aria-label", "مدیریت موجودیِ " + (product.name || "محصول"));
		manageLabel.appendChild(manage);
		manageLabel.appendChild(node("span", "", "مدیریت موجودی"));
		cell.appendChild(manageLabel);

		var quantity = node("input", "jluxe-pm-inline-input jluxe-pm-stock-quantity");
		quantity.type = "text";
		quantity.inputMode = "numeric";
		quantity.autocomplete = "off";
		quantity.dir = "ltr";
		quantity.value = product.manageStock && product.stockQuantity != null ? String(product.stockQuantity) : "";
		quantity.placeholder = product.type === "variable" && !product.manageStock ? "موجودیِ تنوع‌ها" : "تعداد";
		quantity.disabled = !product.manageStock;
		quantity.hidden = !product.manageStock;
		quantity.dataset.pmInlineField = "stockQuantity";
		quantity.dataset.productId = id;
		quantity.setAttribute("aria-label", "تعداد موجودیِ " + (product.name || "محصول"));
		cell.appendChild(quantity);

		if (product.type === "simple") {
			var select = stockStatusSelect(product.stockStatus, "stockStatus", id, "وضعیت موجودیِ " + (product.name || "محصول"));
			select.hidden = !!product.manageStock;
			cell.appendChild(select);
		}
		var pill = stockPill(product.stockStatus, product.type === "variable" && !product.manageStock ? "موجودی بر پایهٔ تنوع‌ها" : stockLabel(product.stockStatus));
		pill.dataset.pmStockPill = id;
		pill.hidden = product.type === "simple" && !product.manageStock;
		cell.appendChild(pill);
		var hint = node("small", "jluxe-pm-cell-hint", product.type === "variable" ? "یا مدیریت موجودی را برای خود محصول روشن کنید، یا هر تنوع را جداگانه مدیریت کنید." : "برای فعال‌کردن شمارش عددی، تیک مدیریت موجودی را بزنید.");
		hint.hidden = !!product.manageStock;
		cell.appendChild(hint);
		return cell;
	}

	function stockStatusSelect(value, field, id, label) {
		var select = node("select", "jluxe-pm-stock-status-select");
		select.dataset.pmInlineField = field;
		select.dataset.productId = id;
		select.setAttribute("aria-label", label);
		[
			["instock", "موجود"],
			["outofstock", "ناموجود"],
			["onbackorder", "پیش‌فروش"],
		].forEach(function (optionData) {
			var option = node("option", "", optionData[1]);
			option.value = optionData[0];
			option.selected = optionData[0] === value;
			select.appendChild(option);
		});
		return select;
	}

	function stockPill(status, label) {
		return node("span", "jluxe-pm-stock-pill " + stockClass(status), label || stockLabel(status));
	}

	function setStatusContent(element, message, kind) {
		var baseClass = element.dataset.statusBase || element.className.replace(/\s+is-[^\s]+/g, "").trim();
		element.dataset.statusBase = baseClass;
		element.className = baseClass + (kind ? " " + kind : "");
		element.textContent = message || "";
		if (message) element.title = message;
		else element.removeAttribute("title");
	}

	function setProductStatus(productId, message, kind) {
		var target = $("[data-pm-row-status='" + String(productId) + "']", rows);
		if (target) setStatusContent(target, message, kind);
	}

	function productTracker(productId) {
		var key = String(productId);
		if (!state.productSaves[key]) state.productSaves[key] = { revision: 0, timer: null, inFlight: false, pending: false, status: "", kind: "" };
		return state.productSaves[key];
	}

	function updateFeaturedHint(product) {
		var hint = $("[data-pm-featured-hint='" + String(product.id) + "']", rows);
		if (hint) {
			hint.className = "jluxe-pm-featured-hint" + (product.featured ? " is-active" : "");
			hint.textContent = product.featured ? "ویژه انتخاب شده" : "ویژه انتخاب نشده";
		}
	}

	function syncProductRow(product) {
		var row = $("[data-product-id='" + String(product.id) + "']", rows);
		if (!row) return;
		["regularPrice", "salePrice"].forEach(function (field) {
			var input = $("[data-pm-inline-field='" + field + "']", row);
			if (input && document.activeElement !== input) input.value = groupedNumber(product[field]);
		});
		setProductSaleState(product);
		var featured = $("[data-pm-inline-field='featured']", row);
		if (featured && document.activeElement !== featured) featured.checked = !!product.featured;
		updateFeaturedHint(product);
		var manage = $("[data-pm-inline-field='manageStock']", row);
		var quantity = $("[data-pm-inline-field='stockQuantity']", row);
		if (manage && document.activeElement !== manage) manage.checked = !!product.manageStock;
		if (quantity) {
			quantity.disabled = !product.manageStock;
			quantity.hidden = !product.manageStock;
			if (document.activeElement !== quantity) quantity.value = product.manageStock && product.stockQuantity != null ? String(product.stockQuantity) : "";
			quantity.placeholder = product.type === "variable" && !product.manageStock ? "موجودیِ تنوع‌ها" : "تعداد";
		}
		var statusSelect = $("[data-pm-inline-field='stockStatus']", row);
		if (statusSelect) {
			statusSelect.hidden = !!product.manageStock;
			if (document.activeElement !== statusSelect) statusSelect.value = product.stockStatus;
		}
		var pill = $("[data-pm-stock-pill='" + String(product.id) + "']", row);
		if (pill) {
			pill.className = "jluxe-pm-stock-pill " + stockClass(product.stockStatus);
			pill.textContent = product.type === "variable" && !product.manageStock ? "موجودی بر پایهٔ تنوع‌ها" : stockLabel(product.stockStatus);
			pill.hidden = product.type === "simple" && !product.manageStock;
		}
		var hint = $(".jluxe-pm-stock-cell .jluxe-pm-cell-hint", row);
		if (hint) {
			hint.textContent = product.type === "variable" ? "یا مدیریت موجودی را برای خود محصول روشن کنید، یا هر تنوع را جداگانه مدیریت کنید." : "برای فعال‌کردن شمارش عددی، تیک مدیریت موجودی را بزنید.";
			hint.hidden = !!product.manageStock;
		}
	}

	function scheduleProductSave(productId) {
		var product = state.products.find(function (item) { return String(item.id) === String(productId); });
		if (!product) return;
		var tracker = productTracker(productId);
		tracker.revision++;
		tracker.status = "در حال ذخیره…";
		tracker.kind = "is-saving";
		setProductStatus(productId, tracker.status, tracker.kind);
		window.clearTimeout(tracker.timer);
		tracker.timer = window.setTimeout(function () { tracker.timer = null; sendProductSave(productId, tracker); }, 420);
	}

	function sendProductSave(productId, tracker) {
		if (tracker.inFlight) { tracker.pending = true; return; }
		var product = state.products.find(function (item) { return String(item.id) === String(productId); });
		if (!product) return;
		tracker.inFlight = true;
		tracker.pending = false;
		var revision = tracker.revision;
		var snapshot = {
			product_id: product.id,
			featured: product.featured ? "1" : "0",
			manage_stock: product.manageStock ? "1" : "0",
			stock_quantity: product.stockQuantity == null ? "" : product.stockQuantity,
			stock_status: product.stockStatus || "instock",
		};
		if (product.type === "simple") {
			snapshot.regular_price = product.regularPrice == null ? "" : product.regularPrice;
			snapshot.sale_price = product.salePrice == null ? "" : product.salePrice;
		}
		request("jluxe_product_manager_quick_save", snapshot).then(function (result) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			if (result.product) Object.keys(result.product).forEach(function (key) { product[key] = result.product[key]; });
			product.salePending = false;
			tracker.status = result.message || "ذخیره شد.";
			tracker.kind = "is-saved";
			syncProductRow(product);
			setProductStatus(productId, tracker.status, tracker.kind);
		}).catch(function (error) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			tracker.status = error.message;
			tracker.kind = "is-error";
			setProductStatus(productId, tracker.status, tracker.kind);
		}).finally(function () {
			tracker.inFlight = false;
			if (tracker.pending || revision !== tracker.revision) {
				tracker.pending = false;
				sendProductSave(productId, tracker);
			}
		});
	}

	function filterTaxonomy(wrapper) {
		if (!wrapper) return "";
		if (wrapper.dataset.pmFilterTerm === "attribute-term") return $("#jluxe-pm-attribute-taxonomy").value;
		return wrapper.dataset.taxonomy || "";
	}

	function searchFilterTerms(input) {
		var wrapper = input.closest(".jluxe-pm-filter-term");
		var taxonomy = filterTaxonomy(wrapper);
		var results = $(".jluxe-pm-filter-results", wrapper);
		if (!taxonomy) {
			results.replaceChildren(node("span", "jluxe-pm-term-empty", "ابتدا نوع ویژگی را انتخاب کنید."));
			results.hidden = false;
			return;
		}
		var requestId = String(Date.now()) + Math.random();
		wrapper.dataset.requestId = requestId;
		request("jluxe_product_manager_terms", { taxonomy: taxonomy, q: input.value.trim() }).then(function (data) {
			if (wrapper.dataset.requestId !== requestId) return;
			renderFilterTerms(wrapper, data.terms || [], input.value.trim());
		}).catch(function (error) {
			if (wrapper.dataset.requestId !== requestId) return;
			results.replaceChildren(node("span", "jluxe-pm-term-empty", error.message));
			results.hidden = false;
		});
	}

	function renderFilterTerms(wrapper, terms, query) {
		var results = $(".jluxe-pm-filter-results", wrapper);
		results.replaceChildren();
		var valueInput = $("input[type='hidden']", wrapper);
		var available = (terms || []).filter(function (term) { return String(term.id) !== String(valueInput.value); });
		available.forEach(function (term) {
			var button = node("button", "jluxe-pm-filter-result", term.name);
			button.type = "button";
			button.dataset.pmFilterSelect = String(term.id);
			button.dataset.name = term.name;
			button.dataset.slug = term.slug || "";
			button.setAttribute("role", "option");
			results.appendChild(button);
		});
		if (!available.length) results.appendChild(node("span", "jluxe-pm-term-empty", query ? "نتیجه‌ای پیدا نشد." : "برای انتخاب، نام را جست‌وجو کنید."));
		results.hidden = false;
	}

	function selectFilterTerm(wrapper, term) {
		var input = $(".jluxe-pm-filter-search", wrapper);
		var hidden = $("input[type='hidden']", wrapper);
		var selected = $("[data-pm-filter-selected]", wrapper);
		hidden.value = String(term.id);
		$("[data-pm-filter-label]", selected).textContent = term.name;
		selected.hidden = false;
		input.value = "";
		input.hidden = true;
		$(".jluxe-pm-filter-results", wrapper).hidden = true;
		loadProducts(true);
	}

	function clearFilterTerm(wrapper, reload) {
		var input = $(".jluxe-pm-filter-search", wrapper);
		var hidden = $("input[type='hidden']", wrapper);
		var selected = $("[data-pm-filter-selected]", wrapper);
		hidden.value = "";
		selected.hidden = true;
		input.hidden = false;
		input.value = "";
		$(".jluxe-pm-filter-results", wrapper).hidden = true;
		if (reload) loadProducts(true);
	}

	function makeAccordionRow(product) {
		var id = String(product.id);
		var row = node("tr", "jluxe-pm-accordion-row");
		row.dataset.pmAccordionFor = id;
		var cell = node("td");
		cell.colSpan = 7;
		var shell = node("div", "jluxe-pm-accordion-shell");
		var header = node("div", "jluxe-pm-accordion-header");
		header.appendChild(node("div", "jluxe-pm-accordion-title", "تنوع‌های «" + (product.name || "محصول") + "»"));
		var close = node("button", "jluxe-pm-icon-button jluxe-pm-accordion-close", "×");
		close.type = "button";
		close.dataset.pmVariationsToggle = id;
		close.setAttribute("aria-label", "بستن تنوع‌های " + (product.name || "محصول"));
		header.appendChild(close);
		shell.appendChild(header);
		var panel = node("div", "jluxe-pm-variation-panel");
		panel.id = "jluxe-pm-variations-" + id;
		panel.dataset.pmVariationPanel = id;
		shell.appendChild(panel);
		cell.appendChild(shell);
		row.appendChild(cell);
		return row;
	}

	function toggleVariations(productId) {
		var key = String(productId);
		var product = state.products.find(function (item) { return String(item.id) === key; });
		var productRow = $("[data-product-id='" + key + "']", rows);
		if (!product || !productRow) return;
		if (state.openVariations[key]) {
			delete state.openVariations[key];
			var openButton = $("[data-pm-variations-toggle='" + key + "']", productRow);
			if (openButton) openButton.setAttribute("aria-expanded", "false");
			var details = productRow.nextElementSibling;
			if (details && details.dataset.pmAccordionFor === key) details.remove();
			return;
		}
		state.openVariations[key] = true;
		var button = $("[data-pm-variations-toggle='" + key + "']", productRow);
		if (button) button.setAttribute("aria-expanded", "true");
		var accordion = makeAccordionRow(product);
		accordion.classList.toggle("is-alt", productRow.classList.contains("is-alt"));
		productRow.after(accordion);
		var panel = $("[data-pm-variation-panel]", accordion);
		var cached = state.variationData[key];
		if (cached) renderVariations(panel, key, cached);
		else {
			panel.classList.add("is-loading");
			panel.appendChild(node("p", "jluxe-pm-loading", "در حال دریافت تنوع‌ها…"));
			loadVariations(productId, 1);
		}
	}

	function currentVariationPanel(productId) {
		return $("[data-pm-variation-panel='" + String(productId) + "']", rows);
	}

	function loadVariations(productId, page) {
		var key = String(productId);
		if (state.variationLoading[key]) return;
		state.variationLoading[key] = true;
		var requestId = (state.variationRequests[key] || 0) + 1;
		state.variationRequests[key] = requestId;
		var panel = currentVariationPanel(key);
		if (panel) panel.classList.add("is-loading");
		request("jluxe_product_manager_get_variations", { product_id: key, page: page || 1 }).then(function (data) {
			if (state.variationRequests[key] !== requestId) return;
			state.variationLoading[key] = false;
			state.variationData[key] = data;
			var currentPanel = currentVariationPanel(key);
			if (currentPanel && state.openVariations[key]) renderVariations(currentPanel, key, data);
		}).catch(function (error) {
			if (state.variationRequests[key] !== requestId) return;
			state.variationLoading[key] = false;
			var currentPanel = currentVariationPanel(key);
			if (currentPanel) {
				currentPanel.classList.remove("is-loading");
				currentPanel.replaceChildren(node("p", "jluxe-pm-error", error.message));
				var retry = node("button", "button", "تلاش دوباره");
				retry.type = "button";
				retry.dataset.pmRetryVariations = key;
				currentPanel.appendChild(retry);
			}
		});
	}

	function renderVariations(panel, productId, variationData) {
		panel.classList.remove("is-loading");
		variationData = variationData || { items: [], page: 1, pages: 1, total: 0, attributes: [], defaultAttributes: {} };
		state.variationData[String(productId)] = variationData;
		panel.replaceChildren();
		var section = node("section", "jluxe-pm-variation-section");
		var intro = node("div", "jluxe-pm-variation-intro");
		intro.appendChild(node("strong", "", faNumber(variationData.total || 0) + " تنوع"));
		intro.appendChild(node("small", "", "قیمت، موجودی و تصویر هر تنوع را همین‌جا تغییر دهید؛ ذخیره خودکار است."));
		section.appendChild(intro);
		renderDefaultAttributes(section, productId, variationData);

		if (!variationData.total) {
			section.appendChild(node("p", "jluxe-pm-empty", "برای این محصول تنوعی ثبت نشده است. ساخت تنوع از ویرایشگر استاندارد ووکامرس انجام می‌شود."));
			panel.appendChild(section);
			return;
		}
		var tableWrap = node("div", "jluxe-pm-variation-wrap");
		var table = node("table", "widefat jluxe-pm-variation-table");
		var thead = node("thead");
		var head = node("tr");
		[
			"ویژگی و تصویر",
			"قیمت عادی",
			"قیمت ویژه",
			"مدیریت موجودی",
			"موجودی",
			"وضعیت",
			"ذخیره",
		].forEach(function (label) { head.appendChild(node("th", "", label)); });
		thead.appendChild(head);
		table.appendChild(thead);
		var tbody = node("tbody");
		(variationData.items || []).forEach(function (variation) { tbody.appendChild(makeVariationRow(productId, variation, variationData.attributes || [])); });
		table.appendChild(tbody);
		tableWrap.appendChild(table);
		section.appendChild(tableWrap);
		if ((variationData.pages || 1) > 1) {
			var pager = node("div", "jluxe-pm-variation-pager");
			var previous = node("button", "button", "قبلی");
			previous.type = "button";
			previous.disabled = variationData.page <= 1;
			previous.dataset.pmVariationPage = String((variationData.page || 1) - 1);
			previous.dataset.parentId = String(productId);
			var label = node("span", "", "صفحهٔ " + faNumber(variationData.page) + " از " + faNumber(variationData.pages));
			var next = node("button", "button", "بعدی");
			next.type = "button";
			next.disabled = variationData.page >= variationData.pages;
			next.dataset.pmVariationPage = String((variationData.page || 1) + 1);
			next.dataset.parentId = String(productId);
			pager.appendChild(previous);
			pager.appendChild(label);
			pager.appendChild(next);
			section.appendChild(pager);
		}
		panel.appendChild(section);
	}

	function renderDefaultAttributes(section, productId, variationData) {
		var definitions = variationData.attributes || [];
		var wrapper = node("div", "jluxe-pm-defaults");
		wrapper.dataset.pmDefaults = String(productId);
		var heading = node("div", "jluxe-pm-defaults-heading");
		heading.appendChild(node("strong", "", "پیش‌فرض انتخاب تنوع در صفحهٔ محصول"));
		heading.appendChild(node("small", "", "این گزینه‌ها هنگام بازشدن صفحهٔ محصول از ابتدا انتخاب می‌شوند."));
		wrapper.appendChild(heading);
		if (!definitions.length) {
			wrapper.appendChild(node("p", "jluxe-pm-muted", "ویژگی تنوعی برای انتخاب پیش‌فرض ثبت نشده است."));
			section.appendChild(wrapper);
			return;
		}
		var current = variationData.defaultAttributes || {};
		var fields = node("div", "jluxe-pm-default-fields");
		definitions.forEach(function (attribute) {
			var label = node("label", "jluxe-pm-default-field");
			label.appendChild(node("span", "", attribute.label));
			var select = node("select", "jluxe-pm-control");
			select.dataset.pmDefaultAttribute = attribute.name;
			select.dataset.parentId = String(productId);
			select.setAttribute("aria-label", "پیش‌فرض " + attribute.label);
			var blank = node("option", "", "انتخاب نشده");
			blank.value = "";
			select.appendChild(blank);
			(attribute.options || []).forEach(function (option) {
				var item = node("option", "", option.label);
				item.value = String(option.value);
				item.selected = String(current[attribute.name] || "") === String(option.value);
				select.appendChild(item);
			});
			label.appendChild(select);
			fields.appendChild(label);
		});
		wrapper.appendChild(fields);
		var tracker = state.defaultSaves[String(productId)];
		var status = node("span", "jluxe-pm-row-status jluxe-pm-default-status");
		status.dataset.pmDefaultStatus = String(productId);
		status.setAttribute("aria-live", "polite");
		if (tracker && tracker.status) setStatusContent(status, tracker.status, tracker.kind);
		wrapper.appendChild(status);
		section.appendChild(wrapper);
	}

	function makeVariationRow(parentId, variation, definitions) {
		var id = String(variation.id);
		var current = state.variationDraft[id] || variation;
		var row = node("tr", "jluxe-pm-variation-row");
		row.dataset.variationId = id;
		row.dataset.parentId = String(parentId);

		var attributesCell = labelCell(node("td", "jluxe-pm-variation-attributes-cell"), "ویژگی و تصویر");
		var attributeFields = node("div", "jluxe-pm-attribute-selects");
		if (!definitions.length) attributeFields.appendChild(node("span", "jluxe-pm-muted", "بدون ویژگی"));
		definitions.forEach(function (attribute) {
			var field = node("label", "jluxe-pm-variation-attribute");
			field.appendChild(node("span", "", attribute.label));
			var select = node("select", "jluxe-pm-control jluxe-pm-small-control");
			select.dataset.vAttr = attribute.name;
			select.dataset.variationId = id;
			select.dataset.parentId = String(parentId);
			select.setAttribute("aria-label", attribute.label + " تنوع " + faNumber(id));
			var empty = node("option", "", "هر مقدار");
			empty.value = "";
			select.appendChild(empty);
			var value = current.attributes && current.attributes[attribute.name] != null ? String(current.attributes[attribute.name]) : "";
			var found = false;
			(attribute.options || []).forEach(function (option) {
				var item = node("option", "", option.label);
				item.value = String(option.value);
				item.selected = value === String(option.value);
				if (item.selected) found = true;
				select.appendChild(item);
			});
			if (value && !found) {
				var unknown = node("option", "", value);
				unknown.value = value;
				unknown.selected = true;
				select.appendChild(unknown);
			}
			field.appendChild(select);
			attributeFields.appendChild(field);
		});
		var imagePicker = node("div", "jluxe-pm-variation-image");
		var preview = node("span", "jluxe-pm-variation-image-preview");
		if (current.imageUrl) {
			var previewImage = node("img");
			previewImage.src = current.imageUrl;
			previewImage.alt = "";
			preview.appendChild(previewImage);
		} else {
			preview.appendChild(node("span", "dashicons dashicons-format-image", ""));
		}
		imagePicker.appendChild(preview);
		var imageActions = node("div", "jluxe-pm-variation-image-actions");
		var imageButton = node("button", "button button-small", current.imageUrl ? "تغییر تصویر" : "انتخاب تصویر");
		imageButton.type = "button";
		imageButton.dataset.pmVariationImage = id;
		imageButton.dataset.parentId = String(parentId);
		imageButton.disabled = !config.canUpload;
		imageButton.title = config.canUpload ? "انتخاب تصویر همین تنوع" : "برای انتخاب تصویر، مجوز بارگذاری فایل لازم است.";
		imageActions.appendChild(imageButton);
		if (current.imageUrl) {
			var clearImage = node("button", "jluxe-pm-image-clear", "حذف");
			clearImage.type = "button";
			clearImage.dataset.pmClearVariationImage = id;
			clearImage.dataset.parentId = String(parentId);
			clearImage.disabled = !config.canUpload;
			imageActions.appendChild(clearImage);
		}
		var imageIdInput = node("input");
		imageIdInput.type = "hidden";
		imageIdInput.value = String(current.imageId || 0);
		imageIdInput.dataset.vField = "imageId";
		imageIdInput.dataset.variationId = id;
		imageIdInput.dataset.parentId = String(parentId);
		imagePicker.appendChild(imageActions);
		imagePicker.appendChild(imageIdInput);
		attributesCell.appendChild(attributeFields);
		attributesCell.appendChild(imagePicker);
		row.appendChild(attributesCell);

		row.appendChild(variationPriceCell(parentId, variation.id, "regularPrice", current.regularPrice, "قیمت عادی", current.onSale));
		row.appendChild(variationPriceCell(parentId, variation.id, "salePrice", current.salePrice, "قیمت ویژه", current.onSale));

		var manageCell = labelCell(node("td", "jluxe-pm-variation-manage-cell"), "مدیریت موجودی");
		var manageLabel = node("label", "jluxe-pm-manage-stock jluxe-pm-variation-manage-toggle");
		var manage = node("input");
		manage.type = "checkbox";
		manage.checked = !!current.manageStock;
		manage.dataset.vField = "manageStock";
		manage.dataset.variationId = id;
		manage.dataset.parentId = String(parentId);
		manage.setAttribute("aria-label", "مدیریت موجودی تنوع " + faNumber(id));
		manageLabel.appendChild(manage);
		manageLabel.appendChild(node("span", "", "مدیریت"));
		manageCell.appendChild(manageLabel);
		row.appendChild(manageCell);

		var quantityCell = labelCell(node("td", "jluxe-pm-stock-cell"), "تعداد موجودی");
		var quantity = variationFieldInput(parentId, variation.id, "stockQuantity", current.manageStock ? current.stockQuantity : "", "تعداد");
		quantity.disabled = !current.manageStock;
		quantity.hidden = !current.manageStock;
		quantity.placeholder = current.manageStock ? "تعداد" : "ابتدا فعال کنید";
		quantityCell.appendChild(quantity);
		row.appendChild(quantityCell);

		var statusCell = labelCell(node("td", "jluxe-pm-variation-status-cell"), "وضعیت موجودی");
		var statusPill = stockPill(current.stockStatus, stockLabel(current.stockStatus));
		statusPill.dataset.pmVariationStockPill = id;
		statusPill.hidden = !current.manageStock;
		statusCell.appendChild(statusPill);
		var statusSelect = stockStatusSelect(current.stockStatus, "stockStatus", id, "وضعیت موجودی تنوع " + faNumber(id));
		statusSelect.removeAttribute("data-pm-inline-field");
		statusSelect.removeAttribute("data-product-id");
		statusSelect.dataset.vField = "stockStatus";
		statusSelect.dataset.variationId = id;
		statusSelect.dataset.parentId = String(parentId);
		statusSelect.hidden = !!current.manageStock;
		statusCell.appendChild(statusSelect);
		row.appendChild(statusCell);

		var saveCell = labelCell(node("td", "jluxe-pm-actions"), "وضعیت ذخیره");
		var saveStatus = node("span", "jluxe-pm-row-status jluxe-pm-variation-status");
		saveStatus.dataset.pmVariationStatus = id;
		saveStatus.setAttribute("aria-live", "polite");
		var tracker = state.variationSaves[id];
		if (tracker && tracker.status) setStatusContent(saveStatus, tracker.status, tracker.kind);
		saveCell.appendChild(saveStatus);
		row.appendChild(saveCell);
		return row;
	}

	function variationPriceCell(parentId, variationId, key, value, label, onSale) {
		var suffix = key === "salePrice" ? "--sale" : "--regular";
		var cell = labelCell(node("td", "jluxe-pm-price-cell jluxe-pm-price-cell" + suffix), label);
		if (key === "salePrice" && onSale) cell.classList.add("is-on-sale");
		var control = node("div", "jluxe-pm-price-control");
		var input = variationFieldInput(parentId, variationId, key, value, label);
		control.appendChild(input);
		if (config.currency) control.appendChild(node("small", "jluxe-pm-currency", config.currency));
		cell.appendChild(control);
		if (key === "salePrice") cell.appendChild(makeSaleState(onSale, value, "simple", false));
		return cell;
	}

	function variationFieldInput(parentId, variationId, key, value, label) {
		var inputClass = "jluxe-pm-control jluxe-pm-small-control";
		if (key === "stockQuantity") inputClass += " jluxe-pm-stock-quantity";
		if (key === "regularPrice" || key === "salePrice") inputClass += " jluxe-pm-price-input";
		var input = node("input", inputClass);
		input.type = "text";
		input.inputMode = key === "stockQuantity" ? "numeric" : "decimal";
		input.autocomplete = "off";
		input.dir = "ltr";
		input.value = key === "regularPrice" || key === "salePrice" ? groupedNumber(value) : (value == null ? "" : String(value));
		input.placeholder = label;
		input.dataset.vField = key;
		input.dataset.variationId = String(variationId);
		input.dataset.parentId = String(parentId);
		input.setAttribute("aria-label", label + " تنوع " + faNumber(variationId));
		return input;
	}

	function defaultTracker(productId) {
		var key = String(productId);
		if (!state.defaultSaves[key]) state.defaultSaves[key] = { revision: 0, timer: null, inFlight: false, pending: false, status: "", kind: "" };
		return state.defaultSaves[key];
	}

	function scheduleDefaultSave(productId) {
		var tracker = defaultTracker(productId);
		tracker.revision++;
		tracker.status = "در حال ذخیره…";
		tracker.kind = "is-saving";
		setDefaultStatus(productId, tracker.status, tracker.kind);
		window.clearTimeout(tracker.timer);
		tracker.timer = window.setTimeout(function () { tracker.timer = null; sendDefaultSave(productId, tracker); }, 350);
	}

	function setDefaultStatus(productId, message, kind) {
		var target = $("[data-pm-default-status='" + String(productId) + "']", rows);
		if (target) setStatusContent(target, message, kind);
	}

	function sendDefaultSave(productId, tracker) {
		if (tracker.inFlight) { tracker.pending = true; return; }
		var wrapper = $("[data-pm-defaults='" + String(productId) + "']", rows);
		if (!wrapper) return;
		var defaults = Object.create(null);
		$$('[data-pm-default-attribute]', wrapper).forEach(function (select) { defaults[select.dataset.pmDefaultAttribute] = select.value; });
		var revision = tracker.revision;
		tracker.inFlight = true;
		tracker.pending = false;
		request("jluxe_product_manager_save_defaults", { product_id: productId, defaults: defaults }).then(function (result) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			var variationData = state.variationData[String(productId)];
			if (variationData) variationData.defaultAttributes = result.defaultMap || defaults;
			var product = state.products.find(function (item) { return String(item.id) === String(productId); });
			if (product) {
				product.defaultAttributes = result.defaultAttributes || [];
				var summary = $("[data-pm-default-summary='" + String(productId) + "']", rows);
				if (summary) summary.textContent = defaultSummary(product);
			}
			tracker.status = result.message || "ذخیره شد.";
			tracker.kind = "is-saved";
			setDefaultStatus(productId, tracker.status, tracker.kind);
		}).catch(function (error) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			tracker.status = error.message;
			tracker.kind = "is-error";
			setDefaultStatus(productId, tracker.status, tracker.kind);
		}).finally(function () {
			tracker.inFlight = false;
			if (tracker.pending || revision !== tracker.revision) {
				tracker.pending = false;
				sendDefaultSave(productId, tracker);
			}
		});
	}

	function variationTracker(variationId) {
		var key = String(variationId);
		if (!state.variationSaves[key]) state.variationSaves[key] = { revision: 0, timer: null, inFlight: false, pending: false, status: "", kind: "" };
		return state.variationSaves[key];
	}

	function findVariation(parentId, variationId) {
		var data = state.variationData[String(parentId)];
		if (!data) return null;
		return (data.items || []).find(function (item) { return String(item.id) === String(variationId); }) || null;
	}

	function readVariationRow(row) {
		var variationId = String(row.dataset.variationId);
		var parentId = String(row.dataset.parentId);
		var original = state.variationDraft[variationId] || findVariation(parentId, variationId) || { id: Number(variationId), sku: "", regularPrice: "", salePrice: "", stockQuantity: "", manageStock: false, stockStatus: "instock", backorders: "no", enabled: true, imageId: 0, imageUrl: "", attributes: {} };
		var data = Object.assign({}, original, { attributes: Object.assign({}, original.attributes || {}) });
		$$('[data-v-field]', row).forEach(function (input) {
			var field = input.dataset.vField;
			if (input.type === "checkbox") data[field] = input.checked;
			else if (field === "regularPrice" || field === "salePrice") data[field] = normalizeNumber(input.value);
			else data[field] = input.value;
		});
		$$('[data-v-attr]', row).forEach(function (select) { data.attributes[select.dataset.vAttr] = select.value; });
		if (data.manageStock && String(data.stockQuantity == null ? "" : data.stockQuantity) !== "") {
			var quantity = Number(normalizeNumber(data.stockQuantity));
			if (Number.isFinite(quantity)) data.stockStatus = quantity > 0 ? "instock" : "outofstock";
		} else if (!data.manageStock) {
			data.stockQuantity = "";
		}
		return data;
	}

	function syncVariationStockControls(row, data) {
		var manage = $("[data-v-field='manageStock']", row);
		var quantity = $("[data-v-field='stockQuantity']", row);
		var statusSelect = $("[data-v-field='stockStatus']", row);
		var pill = $("[data-pm-variation-stock-pill]", row);
		if (manage && document.activeElement !== manage) manage.checked = !!data.manageStock;
		if (quantity) {
			quantity.disabled = !data.manageStock;
			quantity.hidden = !data.manageStock;
			quantity.placeholder = data.manageStock ? "تعداد" : "ابتدا فعال کنید";
			if (document.activeElement !== quantity) quantity.value = data.manageStock && data.stockQuantity != null ? String(data.stockQuantity) : "";
		}
		if (statusSelect) {
			statusSelect.hidden = !!data.manageStock;
			if (document.activeElement !== statusSelect) statusSelect.value = data.stockStatus || "instock";
		}
		if (pill) {
			pill.className = "jluxe-pm-stock-pill " + stockClass(data.stockStatus);
			pill.textContent = stockLabel(data.stockStatus);
			pill.hidden = !data.manageStock;
		}
	}

	function scheduleVariationSave(row) {
		var variationId = String(row.dataset.variationId);
		var parentId = String(row.dataset.parentId);
		var tracker = variationTracker(variationId);
		var original = findVariation(parentId, variationId) || {};
		var data = readVariationRow(row);
		if (data.manageStock && !original.manageStock && normalizeNumber(data.stockQuantity) === "") {
			state.variationDraft[variationId] = data;
			syncVariationStockControls(row, data);
			tracker.status = "برای فعال‌کردن مدیریت موجودی، تعداد را وارد کنید.";
			tracker.kind = "is-warning";
			setVariationStatus(variationId, tracker.status, tracker.kind);
			var qtyInput = $("[data-v-field='stockQuantity']", row);
			if (qtyInput) qtyInput.focus();
			return;
		}
		state.variationDraft[variationId] = data;
		syncVariationStockControls(row, data);
		tracker.revision++;
		tracker.status = "در حال ذخیره…";
		tracker.kind = "is-saving";
		setVariationStatus(variationId, tracker.status, tracker.kind);
		window.clearTimeout(tracker.timer);
		tracker.timer = window.setTimeout(function () { tracker.timer = null; sendVariationSave(parentId, variationId, tracker); }, 420);
	}

	function updateVariation(parentId, variation) {
		if (!variation) return;
		var data = state.variationData[String(parentId)];
		if (!data) return;
		var index = (data.items || []).findIndex(function (item) { return String(item.id) === String(variation.id); });
		if (index >= 0) data.items[index] = variation;
	}

	function setVariationStatus(variationId, message, kind) {
		var target = $("[data-pm-variation-status='" + String(variationId) + "']", rows);
		if (target) setStatusContent(target, message, kind);
	}

	function sendVariationSave(parentId, variationId, tracker) {
		if (tracker.inFlight) { tracker.pending = true; return; }
		var data = state.variationDraft[String(variationId)];
		var variationData = state.variationData[String(parentId)];
		if (!data || !variationData) return;
		tracker.inFlight = true;
		tracker.pending = false;
		var revision = tracker.revision;
		var snapshot = Object.assign({}, data, { attributes: Object.assign({}, data.attributes || {}) });
		request("jluxe_product_manager_quick_save_variation", {
			parent_id: parentId,
			variation_id: variationId,
			page: variationData.page || 1,
			variation: snapshot,
		}).then(function (result) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			var updated = result.variation || snapshot;
			updateVariation(parentId, updated);
			delete state.variationDraft[String(variationId)];
			if (result.product) {
				var product = state.products.find(function (item) { return String(item.id) === String(parentId); });
				if (product) Object.keys(result.product).forEach(function (key) { product[key] = result.product[key]; });
			}
			tracker.status = result.message || "ذخیره شد.";
			tracker.kind = "is-saved";
			setVariationStatus(variationId, tracker.status, tracker.kind);
			loadProducts(false);
		}).catch(function (error) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			tracker.status = error.message;
			tracker.kind = "is-error";
			setVariationStatus(variationId, tracker.status, tracker.kind);
		}).finally(function () {
			tracker.inFlight = false;
			if (tracker.pending || revision !== tracker.revision) {
				tracker.pending = false;
				sendVariationSave(parentId, variationId, tracker);
			}
		});
	}

	function setVariationImage(row, imageId, imageUrl) {
		var input = $("[data-v-field='imageId']", row);
		var preview = $(".jluxe-pm-variation-image-preview", row);
		var button = $("[data-pm-variation-image]", row);
		if (!input || !preview) return;
		input.value = String(imageId || 0);
		preview.replaceChildren();
		if (imageUrl) {
			var img = node("img");
			img.src = imageUrl;
			img.alt = "";
			preview.appendChild(img);
		} else {
			preview.appendChild(node("span", "dashicons dashicons-format-image", ""));
		}
		if (button) button.textContent = imageUrl ? "تغییر تصویر" : "انتخاب تصویر";
		var actions = $(".jluxe-pm-variation-image-actions", row);
		var clear = $("[data-pm-clear-variation-image]", actions);
		if (imageUrl && !clear) {
			clear = node("button", "jluxe-pm-image-clear", "حذف");
			clear.type = "button";
			clear.dataset.pmClearVariationImage = row.dataset.variationId;
			clear.dataset.parentId = row.dataset.parentId;
			clear.disabled = !config.canUpload;
			actions.appendChild(clear);
		} else if (!imageUrl && clear) {
			clear.remove();
		}
	}

	function openVariationMedia(button) {
		if (!config.canUpload || !window.wp || !window.wp.media) {
			showNotice("کتابخانهٔ رسانه برای این حساب در دسترس نیست.", "error");
			return;
		}
		var row = button.closest(".jluxe-pm-variation-row");
		var frame = window.wp.media({
			title: "انتخاب تصویر تنوع",
			button: { text: "استفاده از این تصویر" },
			library: { type: "image" },
			multiple: false,
		});
		frame.on("select", function () {
			var selection = frame.state().get("selection");
			var attachment = selection && selection.first ? selection.first().toJSON() : null;
			if (!attachment || !attachment.id) return;
			var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			setVariationImage(row, attachment.id, imageUrl || "");
			scheduleVariationSave(row);
		});
		frame.open();
	}

	function clearVariationImage(button) {
		var row = button.closest(".jluxe-pm-variation-row");
		setVariationImage(row, 0, "");
		scheduleVariationSave(row);
	}

	function loadVariationPage(parentId, page) {
		var panel = currentVariationPanel(parentId);
		if (!panel) return;
		panel.classList.add("is-loading");
		panel.replaceChildren(node("p", "jluxe-pm-loading", "در حال دریافت صفحه…"));
		state.variationLoading[String(parentId)] = false;
		loadVariations(parentId, page);
	}

	function openEditor(productId) {
		if (!productId || state.saving) return;
		state.lastFocus = document.activeElement;
		state.currentProduct = null;
		var requestId = ++state.editorRequest;
		state.dirty = false;
		editor.hidden = false;
		document.body.classList.add("jluxe-pm-modal-open");
		$("#jluxe-pm-editor-title").textContent = "در حال بارگذاری…";
		$("#jluxe-pm-editor-subtitle").textContent = "";
		$("#jluxe-pm-save").disabled = true;
		$("#jluxe-pm-save-state").textContent = "";
		editorBody.replaceChildren(node("p", "jluxe-pm-loading", "در حال دریافت دسته‌بندی و برند…"));
		if (editorCard) editorCard.focus();
		request("jluxe_product_manager_get_product", { product_id: productId }).then(function (product) {
			if (requestId !== state.editorRequest) return;
			state.currentProduct = product;
			$("#jluxe-pm-editor-title").textContent = product.name || "محصول بدون نام";
			$("#jluxe-pm-editor-subtitle").textContent = (product.type === "variable" ? "محصول متغیر" : "محصول ساده") + (product.sku ? " · SKU: " + product.sku : "");
			$("#jluxe-pm-native-edit").href = product.editUrl || "#";
			renderOrganization();
		}).catch(function (error) {
			if (requestId !== state.editorRequest) return;
			editorBody.replaceChildren(node("p", "jluxe-pm-error", error.message));
		});
	}

	function renderOrganization() {
		var product = state.currentProduct;
		if (!product) return;
		editorBody.replaceChildren();
		var block = node("section", "jluxe-pm-section");
		block.appendChild(node("h3", "jluxe-pm-section-title", "دسته‌بندی و برند"));
		block.appendChild(node("p", "jluxe-pm-section-description", "برای پیدا کردن هر مورد جست‌وجو کنید و سپس ذخیره را بزنید."));
		block.appendChild(makeTermPicker("product_cat", "دسته‌بندی‌ها", product.categories || []));
		if (product.brandTaxonomy) block.appendChild(makeTermPicker(product.brandTaxonomy, "برند", product.brands || []));
		else block.appendChild(node("p", "jluxe-pm-muted", "واژه‌نامهٔ برند روی این فروشگاه فعال نیست."));
		editorBody.appendChild(block);
		state.dirty = false;
		$("#jluxe-pm-save").disabled = true;
		$("#jluxe-pm-save-state").textContent = "";
	}

	function makeTermPicker(taxonomy, labelText, selectedTerms) {
		var picker = node("div", "jluxe-pm-term-picker");
		picker.dataset.taxonomy = taxonomy;
		var selected = new Map();
		(selectedTerms || []).forEach(function (term) { selected.set(String(term.id), term); });
		picker._selectedTerms = selected;
		picker.appendChild(node("span", "jluxe-pm-term-label", labelText));
		var chips = node("div", "jluxe-pm-term-chips");
		chips.setAttribute("aria-label", labelText + " انتخاب‌شده");
		picker.appendChild(chips);
		var input = node("input", "jluxe-pm-term-search");
		input.type = "search";
		input.placeholder = "جست‌وجوی " + labelText + "…";
		input.autocomplete = "off";
		input.setAttribute("aria-label", "جست‌وجوی " + labelText);
		picker.appendChild(input);
		var results = node("div", "jluxe-pm-term-results");
		results.hidden = true;
		results.setAttribute("role", "listbox");
		picker.appendChild(results);
		picker.appendChild(node("small", "jluxe-pm-hint", "برای افزودن جست‌وجو کنید؛ برای برداشتن، × کنار نام را بزنید."));
		renderTermChips(picker);
		return picker;
	}

	function renderTermChips(picker) {
		var selected = $(".jluxe-pm-term-chips", picker);
		selected.replaceChildren();
		picker._selectedTerms.forEach(function (term) {
			var chip = node("span", "jluxe-pm-term-chip");
			chip.dataset.id = String(term.id);
			chip.appendChild(node("span", "", term.name));
			var remove = node("button", "jluxe-pm-chip-remove", "×");
			remove.type = "button";
			remove.dataset.pmRemoveTerm = String(term.id);
			remove.setAttribute("aria-label", "حذف " + term.name);
			chip.appendChild(remove);
			selected.appendChild(chip);
		});
	}

	function searchTerms(input) {
		var picker = input.closest(".jluxe-pm-term-picker");
		if (!picker) return;
		var query = input.value.trim();
		var results = $(".jluxe-pm-term-results", picker);
		var requestId = String(Date.now()) + Math.random();
		picker.dataset.requestId = requestId;
		request("jluxe_product_manager_terms", { taxonomy: picker.dataset.taxonomy, q: query }).then(function (data) {
			if (picker.dataset.requestId !== requestId) return;
			renderTermResults(picker, data.terms || [], query);
		}).catch(function (error) {
			if (picker.dataset.requestId !== requestId) return;
			results.replaceChildren(node("span", "jluxe-pm-term-empty", error.message));
			results.hidden = false;
		});
	}

	function renderTermResults(picker, terms, query) {
		var results = $(".jluxe-pm-term-results", picker);
		results.replaceChildren();
		var count = 0;
		(terms || []).forEach(function (term) {
			if (picker._selectedTerms.has(String(term.id))) return;
			var button = node("button", "jluxe-pm-term-result", term.name);
			button.type = "button";
			button.dataset.pmSelectTerm = String(term.id);
			button.dataset.name = term.name;
			button.dataset.slug = term.slug || "";
			button.setAttribute("role", "option");
			results.appendChild(button);
			count++;
		});
		if (!count) results.appendChild(node("span", "jluxe-pm-term-empty", query ? "گزینه‌ای پیدا نشد." : "برای انتخاب، نام را جست‌وجو کنید."));
		results.hidden = false;
	}

	function addTermToPicker(picker, term) {
		picker._selectedTerms.set(String(term.id), term);
		renderTermChips(picker);
		$(".jluxe-pm-term-results", picker).hidden = true;
		$(".jluxe-pm-term-search", picker).value = "";
		setDirty();
	}

	function setDirty() {
		state.dirty = true;
		$("#jluxe-pm-save").disabled = false;
		$("#jluxe-pm-save-state").className = "jluxe-pm-save-state is-pending";
		$("#jluxe-pm-save-state").textContent = "تغییر ذخیره‌نشده";
	}

	function selectedPickerTerms(taxonomy) {
		var picker = $$(".jluxe-pm-term-picker", editorBody).find(function (item) { return item.dataset.taxonomy === taxonomy; });
		return picker ? Array.from(picker._selectedTerms.values()) : [];
	}

	function pickerTermIds(taxonomy) {
		return selectedPickerTerms(taxonomy).map(function (term) { return Number(term.id); });
	}

	function saveOrganization() {
		if (!state.currentProduct || !state.dirty || state.saving) return;
		var product = state.currentProduct;
		var button = $("#jluxe-pm-save");
		var saveState = $("#jluxe-pm-save-state");
		state.saving = true;
		button.disabled = true;
		button.textContent = "در حال ذخیره…";
		saveState.className = "jluxe-pm-save-state is-saving";
		saveState.textContent = "در حال ذخیرهٔ دسته‌بندی و برند…";
		request("jluxe_product_manager_save_organization", {
			product_id: product.id,
			category_ids: pickerTermIds("product_cat"),
			brand_taxonomy: product.brandTaxonomy || "",
			brand_ids: product.brandTaxonomy ? pickerTermIds(product.brandTaxonomy) : [],
		}).then(function (result) {
			product.categories = selectedPickerTerms("product_cat");
			if (product.brandTaxonomy) product.brands = selectedPickerTerms(product.brandTaxonomy);
			state.dirty = false;
			saveState.className = "jluxe-pm-save-state is-saved";
			saveState.textContent = result.message || "ذخیره شد.";
			loadProducts(false);
		}).catch(function (error) {
			saveState.className = "jluxe-pm-save-state " + (error.partial ? "is-warning" : "is-error");
			saveState.textContent = error.message;
		}).finally(function () {
			state.saving = false;
			button.textContent = "ذخیرهٔ دسته و برند";
			button.disabled = !state.dirty;
		});
	}

	function handleClick(event) {
		var target = event.target;
		if (!target.closest(".jluxe-pm-filter-term")) $$(".jluxe-pm-filter-results").forEach(function (item) { item.hidden = true; });
		var button = target.closest("button");
		if (!button) return;
		if (button.dataset.pmVariationsToggle) { toggleVariations(button.dataset.pmVariationsToggle); return; }
		if (button.dataset.pmRetryVariations) { loadVariations(button.dataset.pmRetryVariations, 1); return; }
		if (button.dataset.pmVariationPage) { loadVariationPage(button.dataset.parentId, Number(button.dataset.pmVariationPage)); return; }
		if (button.dataset.pmVariationImage) { openVariationMedia(button); return; }
		if (button.dataset.pmClearVariationImage) { clearVariationImage(button); return; }
		if (button.dataset.pmEdit) { openEditor(Number(button.dataset.pmEdit)); return; }
		if (button.dataset.pmFilterSelect) {
			var filter = button.closest(".jluxe-pm-filter-term");
			selectFilterTerm(filter, { id: button.dataset.pmFilterSelect, name: button.dataset.name, slug: button.dataset.slug });
			return;
		}
		if (button.hasAttribute("data-pm-clear-filter")) { clearFilterTerm(button.closest(".jluxe-pm-filter-term"), true); return; }
		if (button.dataset.pmSelectTerm) {
			addTermToPicker(button.closest(".jluxe-pm-term-picker"), { id: button.dataset.pmSelectTerm, name: button.dataset.name, slug: button.dataset.slug });
			return;
		}
		if (button.dataset.pmRemoveTerm) {
			var picker = button.closest(".jluxe-pm-term-picker");
			picker._selectedTerms.delete(String(button.dataset.pmRemoveTerm));
			renderTermChips(picker);
			setDirty();
			return;
		}
		if (button.hasAttribute("data-pm-save-organization")) { saveOrganization(); return; }
		if (button.hasAttribute("data-pm-close-editor")) { closeEditor(false); return; }
	}

	function handleChange(event) {
		var target = event.target;
		if (target.id === "jluxe-pm-attribute-taxonomy") {
			var filter = $("#jluxe-pm-filter-attribute-term");
			filter.dataset.taxonomy = target.value;
			var search = $(".jluxe-pm-filter-search", filter);
			search.disabled = !target.value;
			search.placeholder = target.value ? "جست‌وجوی مقدار ویژگی…" : "ابتدا ویژگی را انتخاب کنید";
			clearFilterTerm(filter, false);
			loadProducts(true);
			return;
		}
		if (target.matches("#jluxe-pm-type, #jluxe-pm-status, #jluxe-pm-stock")) { loadProducts(true); return; }
		if (target.dataset.pmInlineField) {
			var product = state.products.find(function (item) { return String(item.id) === String(target.dataset.productId); });
			if (!product) return;
			var key = target.dataset.pmInlineField;
			if (key === "featured") {
				product.featured = target.checked;
				updateFeaturedHint(product);
			} else if (key === "manageStock") {
				var wasManaged = !!product.manageStock;
				product.manageStock = target.checked;
				syncProductRow(product);
				if (target.checked && !wasManaged && normalizeNumber(product.stockQuantity) === "") {
					var quantity = $("[data-pm-inline-field='stockQuantity']", target.closest("tr"));
					setProductStatus(product.id, "برای فعال‌کردن مدیریت موجودی، ابتدا تعداد را وارد کنید.", "is-warning");
					if (quantity) quantity.focus();
					return;
				}
			} else if (key === "stockStatus") product.stockStatus = target.value;
			scheduleProductSave(product.id);
			return;
		}
		if (target.dataset.pmDefaultAttribute) {
			scheduleDefaultSave(target.dataset.parentId);
			return;
		}
		if (target.dataset.vField || target.dataset.vAttr) {
			var row = target.closest(".jluxe-pm-variation-row");
			if (row) scheduleVariationSave(row);
		}
	}

	function handleInput(event) {
		var target = event.target;
		if (target.matches("#jluxe-pm-search")) {
			window.clearTimeout(target._pmTimer);
			target._pmTimer = window.setTimeout(function () { loadProducts(true); }, 280);
			return;
		}
		if (target.matches(".jluxe-pm-filter-search")) {
			window.clearTimeout(target._pmTimer);
			target._pmTimer = window.setTimeout(function () { searchFilterTerms(target); }, 220);
			return;
		}
		if (target.matches(".jluxe-pm-term-search")) {
			window.clearTimeout(target._pmTimer);
			target._pmTimer = window.setTimeout(function () { searchTerms(target); }, 220);
			return;
		}
		if (target.dataset.pmInlineField) {
			var product = state.products.find(function (item) { return String(item.id) === String(target.dataset.productId); });
			if (!product) return;
			var key = target.dataset.pmInlineField;
			if (key === "regularPrice") product.regularPrice = formatGroupedInput(target);
			if (key === "salePrice") {
				product.salePrice = formatGroupedInput(target);
				product.salePending = true;
				setProductSaleState(product);
			}
			if (key === "stockQuantity") {
				product.stockQuantity = target.value;
				var quantity = Number(normalizeNumber(target.value));
				if (product.manageStock && Number.isFinite(quantity) && normalizeNumber(target.value) !== "") product.stockStatus = quantity > 0 ? "instock" : "outofstock";
			}
			scheduleProductSave(product.id);
			return;
		}
		if (target.matches("[data-v-field]")) {
			if (target.type === "hidden") return;
			if (target.dataset.vField === "regularPrice" || target.dataset.vField === "salePrice") formatGroupedInput(target);
			var variationRow = target.closest(".jluxe-pm-variation-row");
			if (variationRow) scheduleVariationSave(variationRow);
		}
	}

	function closeEditor(force) {
		if (state.saving) return;
		if (!force && state.dirty && !window.confirm("تغییر ذخیره‌نشده‌ای وجود دارد. پنجره بسته شود؟")) return;
		state.editorRequest++;
		editor.hidden = true;
		document.body.classList.remove("jluxe-pm-modal-open");
		state.currentProduct = null;
		state.dirty = false;
		if (state.lastFocus && typeof state.lastFocus.focus === "function") state.lastFocus.focus();
	}

	root.addEventListener("click", handleClick);
	root.addEventListener("change", handleChange);
	root.addEventListener("input", handleInput);
	$("#jluxe-pm-search-button").addEventListener("click", function () { loadProducts(true); });
	$("#jluxe-pm-reset").addEventListener("click", function () {
		$("#jluxe-pm-search").value = "";
		$("#jluxe-pm-type").value = "";
		$("#jluxe-pm-status").value = "";
		$("#jluxe-pm-stock").value = "";
		$("#jluxe-pm-attribute-taxonomy").value = "";
		var attributeFilter = $("#jluxe-pm-filter-attribute-term");
		attributeFilter.dataset.taxonomy = "";
		var attributeSearch = $(".jluxe-pm-filter-search", attributeFilter);
		attributeSearch.disabled = true;
		attributeSearch.placeholder = "ابتدا ویژگی را انتخاب کنید";
		$$(".jluxe-pm-filter-term").forEach(function (filter) { clearFilterTerm(filter, false); });
		loadProducts(true);
	});
	$("#jluxe-pm-refresh").addEventListener("click", function () { loadProducts(false); });
	$("#jluxe-pm-prev").addEventListener("click", function () { if (state.page > 1) { state.page--; loadProducts(false); } });
	$("#jluxe-pm-next").addEventListener("click", function () { if (state.page < state.pages) { state.page++; loadProducts(false); } });
	$("#jluxe-pm-save").addEventListener("click", saveOrganization);
	document.addEventListener("keydown", function (event) {
		if (!editor.hidden && event.key === "Escape") closeEditor(false);
	});
	root.addEventListener("click", function (event) {
		if (event.target.matches(".jluxe-pm-editor-backdrop")) closeEditor(false);
	});

	loadProducts(true);
})();
