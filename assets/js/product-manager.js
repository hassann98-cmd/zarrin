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
		selected: new Set(),
		currentProduct: null,
		variationPage: 1,
		variationDraft: {},
		removedVariationOptions: {},
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

	function add(parent, child) {
		if (child) parent.appendChild(child);
		return child;
	}

	function showNotice(message, type) {
		notice.hidden = !message;
		notice.className = "jluxe-pm-notice" + (type ? " is-" + type : "");
		notice.textContent = message || "";
		if (message) notice.scrollIntoView({ block: "nearest" });
	}

	function setLoading(message) {
		rows.replaceChildren();
		var tr = node("tr");
		var td = node("td", "jluxe-pm-loading", message || "در حال دریافت محصولات…");
		td.colSpan = 7;
		tr.appendChild(td);
		rows.appendChild(tr);
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
		};
	}

	function loadProducts(resetPage) {
		if (resetPage) state.page = 1;
		if (state.loading) {
			state.pendingReload = state.page;
			return;
		}
		state.loading = true;
		setLoading("در حال جست‌وجو و دریافت محصولات…");
		var data = currentFilters();
		data.page = state.page;
		request("jluxe_product_manager_list", data).then(function (result) {
			state.loading = false;
			state.page = result.page || 1;
			state.pages = result.pages || 1;
			state.total = result.total || 0;
			state.products = result.products || [];
			renderProducts();
			updateSelectionUI();
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
			var cell = node("td", "jluxe-pm-empty", "محصولی با این جست‌وجو و فیلتر پیدا نشد.");
			cell.colSpan = 7;
			empty.appendChild(cell);
			rows.appendChild(empty);
		} else {
			state.products.forEach(function (product) {
				rows.appendChild(makeProductRow(product));
			});
		}
		$("#jluxe-pm-summary").textContent = state.total ? faNumber(state.total) + " محصول · صفحهٔ " + faNumber(state.page) + " از " + faNumber(state.pages) : "";
		$("#jluxe-pm-page-label").textContent = "صفحهٔ " + faNumber(state.page);
		$("#jluxe-pm-prev").disabled = state.page <= 1;
		$("#jluxe-pm-next").disabled = state.page >= state.pages;
	}

	function makeProductRow(product) {
		var tr = node("tr", "jluxe-pm-product-row");
		tr.dataset.productId = product.id;
		var checkCell = node("td", "jluxe-pm-check-col");
		var checkbox = node("input");
		checkbox.type = "checkbox";
		checkbox.className = "jluxe-pm-product-check";
		checkbox.dataset.id = product.id;
		checkbox.checked = state.selected.has(Number(product.id));
		checkbox.setAttribute("aria-label", "انتخاب " + product.name);
		checkCell.appendChild(checkbox);
		tr.appendChild(checkCell);

		var productCell = node("td", "jluxe-pm-product-cell");
		var image = node("span", "jluxe-pm-thumb");
		if (product.image) {
			var img = node("img");
			img.src = product.image;
			img.alt = "";
			img.loading = "lazy";
			image.appendChild(img);
		} else {
			image.appendChild(node("span", "dashicons dashicons-format-image"));
		}
		productCell.appendChild(image);
		var identity = node("div", "jluxe-pm-identity");
		var nameButton = node("button", "jluxe-pm-product-name", product.name || "(بدون نام)");
		nameButton.type = "button";
		nameButton.dataset.pmEdit = product.id;
		identity.appendChild(nameButton);
		var subline = node("span", "jluxe-pm-subline", "SKU: " + (product.sku || "—") + " · شناسه: " + faNumber(product.id));
		identity.appendChild(subline);
		productCell.appendChild(identity);
		tr.appendChild(productCell);

		var typeCell = node("td");
		typeCell.appendChild(node("span", "jluxe-pm-type-pill is-" + product.type, product.typeLabel || product.type));
		tr.appendChild(typeCell);

		tr.appendChild(node("td", "jluxe-pm-price-cell", product.priceLabel || "—"));
		var stockCell = node("td");
		var stockClass = product.stockStatus === "outofstock" ? "is-out" : (product.stockStatus === "onbackorder" ? "is-backorder" : "is-in");
		stockCell.appendChild(node("span", "jluxe-pm-stock-pill " + stockClass, product.stockLabel || "—"));
		tr.appendChild(stockCell);
		tr.appendChild(node("td", "jluxe-pm-category-cell", product.categories || "—"));

		var actions = node("td", "jluxe-pm-actions");
		if (product.type === "simple") {
			var quick = node("button", "button button-small", "قیمت/موجودی");
			quick.type = "button";
			quick.dataset.pmQuick = product.id;
			actions.appendChild(quick);
		} else {
			var variationButton = node("button", "button button-small", "ویرایش تنوع‌ها");
			variationButton.type = "button";
			variationButton.dataset.pmEdit = product.id;
			actions.appendChild(variationButton);
		}
		var full = node("button", "button button-primary button-small", "ویرایش کامل");
		full.type = "button";
		full.dataset.pmEdit = product.id;
		actions.appendChild(full);
		if (product.editUrl) {
			var native = node("a", "jluxe-pm-native-link", "صفحهٔ ووکامرس");
			native.href = product.editUrl;
			native.target = "_blank";
			native.rel = "noopener";
			actions.appendChild(native);
		}
		tr.appendChild(actions);
		return tr;
	}

	function makeQuickEditor(product) {
		var tr = node("tr", "jluxe-pm-quick-row");
		tr.dataset.quickFor = product.id;
		var td = node("td");
		td.colSpan = 7;
		var form = node("form", "jluxe-pm-quick-form");
		form.dataset.productId = product.id;
		form.appendChild(quickInput("قیمت عادی", "regular_price", product.regularPrice, "0.01"));
		form.appendChild(quickInput("قیمت حراج", "sale_price", product.salePrice, "0.01"));
		var stockWrap = node("div", "jluxe-pm-quick-field");
		var stockLabel = node("label", "jluxe-pm-inline-toggle");
		var manage = node("input");
		manage.type = "checkbox";
		manage.name = "manage_stock";
		manage.checked = !!product.manageStock;
		stockLabel.appendChild(manage);
		stockLabel.appendChild(node("span", "", "مدیریت موجودی"));
		stockWrap.appendChild(stockLabel);
		var qty = node("input", "jluxe-pm-number");
		qty.type = "number";
		qty.name = "stock_quantity";
		qty.min = "0";
		qty.step = "1";
		qty.value = product.stockQuantity === null ? "" : product.stockQuantity;
		qty.placeholder = "عدد موجودی";
		qty.disabled = !manage.checked;
		manage.addEventListener("change", function () { qty.disabled = !manage.checked; });
		stockWrap.appendChild(qty);
		form.appendChild(stockWrap);
		var actions = node("div", "jluxe-pm-quick-actions");
		var cancel = node("button", "button", "انصراف");
		cancel.type = "button";
		cancel.dataset.pmQuickClose = product.id;
		var save = node("button", "button button-primary", "ذخیرهٔ سریع");
		save.type = "submit";
		actions.appendChild(cancel);
		actions.appendChild(save);
		form.appendChild(actions);
		form.addEventListener("submit", function (event) {
			event.preventDefault();
			quickSave(product.id, form, save);
		});
		td.appendChild(form);
		tr.appendChild(td);
		return tr;
	}

	function quickInput(labelText, name, value, step) {
		var wrap = node("div", "jluxe-pm-quick-field");
		wrap.appendChild(node("label", "", labelText));
		var input = node("input", "jluxe-pm-number");
		input.type = "number";
		input.name = name;
		input.min = "0";
		input.step = step;
		input.value = value || "";
		input.placeholder = "بدون تغییر";
		wrap.appendChild(input);
		return wrap;
	}

	function quickSave(id, form, button) {
		button.disabled = true;
		button.textContent = "در حال ذخیره…";
		request("jluxe_product_manager_quick_save", {
			product_id: id,
			regular_price: form.elements.regular_price.value,
			sale_price: form.elements.sale_price.value,
			manage_stock: form.elements.manage_stock.checked ? "1" : "0",
			stock_quantity: form.elements.stock_quantity.value,
		}).then(function (result) {
			showNotice(result.message || "ذخیره شد.", "success");
			loadProducts(false);
		}).catch(function (error) {
			button.disabled = false;
			button.textContent = "ذخیرهٔ سریع";
			showNotice(error.message, "error");
		});
	}

	function updateSelectionUI() {
		$("#jluxe-pm-selected-count").textContent = faNumber(state.selected.size) + " انتخاب";
		$("#jluxe-pm-bulk-open").disabled = state.selected.size === 0;
		var visibleBoxes = $$(".jluxe-pm-product-check");
		var checked = visibleBoxes.filter(function (box) { return box.checked; }).length;
		$("#jluxe-pm-select-all").checked = visibleBoxes.length > 0 && checked === visibleBoxes.length;
		$("#jluxe-pm-select-all").indeterminate = checked > 0 && checked < visibleBoxes.length;
		visibleBoxes.forEach(function (box) {
			box.disabled = !box.checked && state.selected.size >= 25;
		});
	}

	function openBulk() {
		if (!state.selected.size) return;
		var panel = $("#jluxe-pm-bulk-panel");
		panel.hidden = false;
		var field = $("#jluxe-pm-bulk-field");
		field.focus();
	}

	function closeBulk() {
		$("#jluxe-pm-bulk-panel").hidden = true;
	}

	function updateBulkOperations() {
		var field = $("#jluxe-pm-bulk-field").value;
		var operation = $("#jluxe-pm-bulk-operation");
		Array.prototype.forEach.call(operation.options, function (option) {
			var priceOnly = option.value.indexOf("percent_") === 0;
			option.disabled = priceOnly && field !== "regular_price";
		});
		if (operation.selectedOptions[0] && operation.selectedOptions[0].disabled) operation.value = "set";
	}

	function applyBulk() {
		var ids = Array.from(state.selected);
		var value = $("#jluxe-pm-bulk-value").value;
		if (!ids.length || !value || Number(value) < 0) {
			showNotice("محصول و مقدار معتبر انتخاب کنید.", "error");
			return;
		}
		if (!window.confirm("این عملیات روی " + faNumber(ids.length) + " محصول انتخاب‌شده اجرا می‌شود. ادامه می‌دهید؟")) return;
		var button = $("#jluxe-pm-bulk-apply");
		button.disabled = true;
		button.textContent = "در حال ذخیره…";
		request("jluxe_product_manager_bulk", {
			ids: ids,
			field: $("#jluxe-pm-bulk-field").value,
			operation: $("#jluxe-pm-bulk-operation").value,
			value: value,
		}).then(function (result) {
			closeBulk();
			state.selected.clear();
			showNotice(result.message || "عملیات گروهی انجام شد.", result.skipped && result.skipped.length ? "warning" : "success");
			loadProducts(false);
		}).catch(function (error) {
			showNotice(error.message, "error");
		}).finally(function () {
			button.disabled = false;
			button.textContent = "اعمال روی انتخاب‌ها";
			updateSelectionUI();
		});
	}

	function field(labelText, control, hint, className) {
		var wrap = node("div", "jluxe-pm-field" + (className ? " " + className : ""));
			var label = node("label", "jluxe-pm-label", labelText);
			if (control.id) {
				label.htmlFor = control.id;
				wrap.appendChild(label);
				wrap.appendChild(control);
			} else {
				label.classList.add("has-control");
				label.appendChild(control);
				wrap.appendChild(label);
			}
		if (hint) wrap.appendChild(node("small", "jluxe-pm-hint", hint));
		return wrap;
	}

	function inputControl(key, labelText, value, type, options) {
		options = options || {};
		if (type === "textarea") {
			var area = node("textarea", "jluxe-pm-control");
			area.rows = options.rows || 4;
			area.value = value == null ? "" : String(value);
			area.dataset.field = key;
			if (key) area.id = "jluxe-pm-field-" + key;
			if (options.placeholder) area.placeholder = options.placeholder;
			return field(labelText, area, options.hint, options.className);
		}
		var input = node("input", "jluxe-pm-control");
		input.type = type || "text";
		input.value = value == null ? "" : String(value);
		input.dataset.field = key;
		if (key) input.id = "jluxe-pm-field-" + key;
		if (options.step) input.step = options.step;
		if (options.min !== undefined) input.min = String(options.min);
		if (options.placeholder) input.placeholder = options.placeholder;
		return field(labelText, input, options.hint, options.className);
	}

	function checkboxControl(key, labelText, checked, hint) {
		var wrap = node("label", "jluxe-pm-check-field");
		var input = node("input");
		input.type = "checkbox";
		input.dataset.field = key;
		input.checked = !!checked;
		wrap.appendChild(input);
		wrap.appendChild(node("span", "", labelText));
		if (hint) wrap.appendChild(node("small", "jluxe-pm-hint", hint));
		return wrap;
	}

	function selectControl(key, labelText, value, options, hint) {
		var select = node("select", "jluxe-pm-control");
		select.dataset.field = key;
		if (key) select.id = "jluxe-pm-field-" + key;
		options.forEach(function (option) {
			var item = node("option", "", option.label);
			item.value = String(option.value);
			item.selected = String(option.value) === String(value == null ? "" : value);
			select.appendChild(item);
		});
		return field(labelText, select, hint);
	}

	function section(title, description) {
		var wrap = node("section", "jluxe-pm-section");
		wrap.appendChild(node("h3", "jluxe-pm-section-title", title));
		if (description) wrap.appendChild(node("p", "jluxe-pm-section-description", description));
		return wrap;
	}

	function grid() { return node("div", "jluxe-pm-grid"); }

	function makePanel(key) {
		var panel = node("section", "jluxe-pm-tab-panel");
		panel.dataset.panel = key;
		panel.hidden = key !== "basic";
		return panel;
	}

	function makeTab(key, labelText, active) {
		var tab = node("button", "jluxe-pm-tab" + (active ? " is-active" : ""), labelText);
		tab.type = "button";
		tab.dataset.tab = key;
		tab.setAttribute("role", "tab");
		tab.setAttribute("aria-selected", active ? "true" : "false");
		return tab;
	}

	function openEditor(productId) {
		if (!productId || state.saving) return;
		state.lastFocus = document.activeElement;
		state.currentProduct = null;
		state.variationDraft = {};
		state.removedVariationOptions = {};
		state.variationPage = 1;
		var requestId = ++state.editorRequest;
		state.dirty = false;
		editor.hidden = false;
		document.body.classList.add("jluxe-pm-modal-open");
		$("#jluxe-pm-editor-title").textContent = "در حال بارگذاری…";
		$("#jluxe-pm-editor-subtitle").textContent = "";
		$("#jluxe-pm-save").disabled = true;
		editorBody.replaceChildren(node("p", "jluxe-pm-loading", "در حال دریافت اطلاعات کامل محصول…"));
		editorCard.focus();
		request("jluxe_product_manager_get_product", { product_id: productId, variation_page: 1 }).then(function (product) {
			if (requestId !== state.editorRequest) return;
			state.currentProduct = product;
			$("#jluxe-pm-editor-title").textContent = product.name || "محصول بدون نام";
			$("#jluxe-pm-editor-subtitle").textContent = "شناسهٔ " + faNumber(product.id) + " · " + (product.type === "variable" ? "محصول متغیر" : "محصول ساده") + " · SKU: " + (product.sku || "—");
			$("#jluxe-pm-native-edit").href = product.editUrl || "#";
			state.variationPage = product.variations && product.variations.page ? product.variations.page : 1;
			renderEditor();
			$("#jluxe-pm-save").disabled = true;
		}).catch(function (error) {
			if (requestId !== state.editorRequest) return;
			editorBody.replaceChildren(node("p", "jluxe-pm-error", error.message));
		});
	}

	function closeEditor(force) {
		if (state.saving) {
			showNotice("ذخیره در حال انجام است؛ تا پایان ذخیره پنجره را نبندید.", "warning");
			return;
		}
		if (!force && state.dirty && !window.confirm("تغییرات ذخیره‌نشده از بین می‌روند. پنجره بسته شود؟")) return;
		state.editorRequest++;
		editor.hidden = true;
		document.body.classList.remove("jluxe-pm-modal-open");
		state.currentProduct = null;
		state.variationDraft = {};
		state.removedVariationOptions = {};
		state.dirty = false;
		if (state.lastFocus && typeof state.lastFocus.focus === "function") state.lastFocus.focus();
	}

	function renderEditor() {
		var product = state.currentProduct;
		if (!product) return;
		var tabs = [
			["basic", "اطلاعات اصلی"],
			["price", "قیمت و موجودی"],
			["organization", "دسته، کلیدواژه و رنگ"],
			["shipping", "حمل‌ونقل و مالیات"],
			["media", "تصاویر"],
			["zarrin", "تنظیمات زرین و پرسش‌ها"],
		];
		if (product.type === "variable") tabs.push(["variations", "تنوع‌ها"]);
		if (product.seo && product.seo.provider) tabs.splice(3, 0, ["seo", "سئو"]);
		var nav = $("#jluxe-pm-tabs");
		nav.replaceChildren();
		tabs.forEach(function (entry, index) { nav.appendChild(makeTab(entry[0], entry[1], index === 0)); });
		editorBody.replaceChildren();
		var panelMap = {};
		tabs.forEach(function (entry, index) {
			var panel = makePanel(entry[0]);
			if (index === 0) panel.classList.add("is-active");
			panelMap[entry[0]] = panel;
			editorBody.appendChild(panel);
		});
		renderBasic(panelMap.basic, product);
		renderPrice(panelMap.price, product);
		renderOrganization(panelMap.organization, product);
		if (panelMap.seo) renderSeo(panelMap.seo, product);
		renderShipping(panelMap.shipping, product);
		renderMedia(panelMap.media, product);
		renderZarrin(panelMap.zarrin, product);
		if (panelMap.variations) renderVariations(panelMap.variations, product.variations);
		state.dirty = false;
		$("#jluxe-pm-save").disabled = true;
	}

	function renderBasic(panel, product) {
		var sectionBasic = section("مشخصات و متن", "متن‌ها از فیلتر HTML امن وردپرس عبور می‌کنند. تغییر نامک یا وضعیت انتشار روی صفحهٔ محصول اثر می‌گذارد.");
		var fields = grid();
		fields.appendChild(inputControl("name", "نام محصول", product.name, "text", { className: "is-wide" }));
		fields.appendChild(inputControl("slug", "نامک نشانی", product.slug, "text", { hint: "خالی بگذارید تا وردپرس آن را از نام بسازد." }));
		fields.appendChild(inputControl("sku", "شناسهٔ کالا (SKU)", product.sku, "text"));
		fields.appendChild(selectControl("status", "وضعیت انتشار", product.status, [
			{ value: "publish", label: "منتشرشده" }, { value: "draft", label: "پیش‌نویس" }, { value: "private", label: "خصوصی" }, { value: "pending", label: "در انتظار بررسی" },
		]));
		fields.appendChild(selectControl("catalogVisibility", "نمایش در فروشگاه", product.catalogVisibility, [
			{ value: "visible", label: "فروشگاه و جست‌وجو" }, { value: "catalog", label: "فقط فروشگاه" }, { value: "search", label: "فقط جست‌وجو" }, { value: "hidden", label: "پنهان" },
		]));
		sectionBasic.appendChild(fields);
		sectionBasic.appendChild(inputControl("shortDescription", "توضیحات کوتاه", product.shortDescription, "textarea", { rows: 4, hint: "خلاصه‌ای که کنار خرید/ابتدای صفحهٔ محصول نمایش داده می‌شود." }));
		sectionBasic.appendChild(inputControl("description", "توضیحات کامل", product.description, "textarea", { rows: 10, hint: "می‌توانید متن چندبخشی و HTML مجاز وردپرس وارد کنید." }));
		var toggles = node("div", "jluxe-pm-check-grid");
		toggles.appendChild(checkboxControl("featured", "محصول ویژه", product.featured));
		toggles.appendChild(checkboxControl("virtual", "محصول مجازی (بدون حمل‌ونقل)", product.virtual));
		toggles.appendChild(checkboxControl("reviewsAllowed", "پذیرش دیدگاه برای این محصول", product.reviewsAllowed));
		sectionBasic.appendChild(toggles);
		panel.appendChild(sectionBasic);
	}

	function renderPrice(panel, product) {
		var pricing = section("قیمت‌گذاری", "قیمت حراج اختیاری است؛ برای زمان‌بندی، تاریخ شروع و پایان را با منطقهٔ زمانی سایت وارد کنید.");
		var pricingFields = grid();
		pricingFields.appendChild(inputControl("regularPrice", "قیمت عادی", product.regularPrice, "number", { min: 0, step: "any", hint: config.currency || "" }));
		pricingFields.appendChild(inputControl("salePrice", "قیمت حراج", product.salePrice, "number", { min: 0, step: "any" }));
		pricingFields.appendChild(inputControl("saleFrom", "شروع حراج", product.saleFrom, "datetime-local"));
		pricingFields.appendChild(inputControl("saleTo", "پایان حراج", product.saleTo, "datetime-local"));
		pricing.appendChild(pricingFields);
		panel.appendChild(pricing);
		var inventory = section("موجودی", product.type === "variable" ? "موجودی محصول مادر یا موجودی هر تنوع را تعیین کنید؛ تغییر قیمت/موجودی تنوع‌ها در زبانهٔ «تنوع‌ها» انجام می‌شود." : "موجودی را روشن کنید تا تعداد و هشدار کمبود اعمال شود.");
		var toggles = node("div", "jluxe-pm-check-grid");
		toggles.appendChild(checkboxControl("manageStock", "مدیریت موجودی", product.manageStock));
		toggles.appendChild(checkboxControl("soldIndividually", "فروش حداکثر یک عدد به هر سفارش", product.soldIndividually));
		inventory.appendChild(toggles);
		var stockFields = grid();
		stockFields.appendChild(inputControl("stockQuantity", "تعداد موجودی", product.stockQuantity, "number", { min: 0, step: "any" }));
		stockFields.appendChild(selectControl("stockStatus", "وضعیت موجودی", product.stockStatus, Object.keys(product.stockOptions || {}).map(function (key) { return { value: key, label: product.stockOptions[key] }; })));
		stockFields.appendChild(selectControl("backorders", "پیش‌فروش ناموجود", product.backorders, [
			{ value: "no", label: "غیرفعال" }, { value: "notify", label: "فعال با اطلاع به مشتری" }, { value: "yes", label: "فعال" },
		]));
		stockFields.appendChild(inputControl("lowStockAmount", "هشدار کمبود در تعداد", product.lowStockAmount, "number", { min: 0, step: "any", hint: "خالی = مقدار پیش‌فرض ووکامرس." }));
		inventory.appendChild(stockFields);
		panel.appendChild(inventory);
	}

	function renderOrganization(panel, product) {
		var terms = section("دسته‌بندی، برچسب و برند", "برچسب محصول نقش کلمات کلیدی فروشگاه را دارد. می‌توانید برچسب تازه را هم بسازید؛ برند فقط وقتی نمایش داده می‌شود که taxonomy افزونهٔ برند فعال باشد.");
		terms.appendChild(makeTermPicker("product_cat", "دسته‌بندی‌ها", product.categories || [], false));
		terms.appendChild(makeTermPicker("product_tag", "برچسب‌ها / کلیدواژه‌ها", product.tags || [], true));
		if (product.brandTaxonomy) terms.appendChild(makeTermPicker(product.brandTaxonomy, "برند", product.brands || [], false));
		panel.appendChild(terms);

		var attributes = section("رنگ و ویژگی‌های سراسری", "ویژگی‌ها از «محصولات ← ویژگی‌ها»ی ووکامرس می‌آیند. برای ویژگی‌های تنوع‌ساز، گزینهٔ استفاده در تنوع‌ها حفظ می‌شود تا تنوع‌های موجود ناخواسته از کار نیفتند.");
		if (product.attributes && product.attributes.length) {
			product.attributes.forEach(function (attribute) {
				var block = node("div", "jluxe-pm-attribute-row");
				var heading = node("div", "jluxe-pm-attribute-heading");
				heading.appendChild(node("strong", "", attribute.label));
				if (isColorAttribute(attribute.label, attribute.taxonomy)) heading.appendChild(node("span", "jluxe-pm-color-mark", "رنگ"));
				if (attribute.variation) heading.appendChild(node("span", "jluxe-pm-variation-mark", "ویژگی تنوع"));
				block.appendChild(heading);
				block.appendChild(makeTermPicker(attribute.taxonomy, "مقادیر «" + attribute.label + "»", attribute.terms || [], false, { attribute: true, visible: attribute.visible, variation: attribute.variation }));
				attributes.appendChild(block);
			});
		} else {
			attributes.appendChild(node("p", "jluxe-pm-muted", "ویژگی سراسری ثبت نشده است. ابتدا از صفحهٔ ویژگی‌های ووکامرس، مثلاً «رنگ»، بسازید؛ سپس اینجا مقادیر محصول را انتخاب کنید."));
		}
		panel.appendChild(attributes);

		var local = section("ویژگی‌های اختصاصی همین محصول", "هر ردیف یک نام دارد؛ چند مقدار را در خط‌های جداگانه وارد کنید.");
		var localList = node("div", "jluxe-pm-local-attributes");
		(product.localAttributes || []).forEach(function (attribute) { localList.appendChild(makeLocalAttributeRow(attribute)); });
		local.appendChild(localList);
		var addAttribute = node("button", "button", "افزودن ویژگی اختصاصی");
		addAttribute.type = "button";
		addAttribute.dataset.pmAddLocalAttribute = "1";
		local.appendChild(addAttribute);
		panel.appendChild(local);
	}

	function isColorAttribute(label, taxonomy) {
		var text = (String(label || "") + " " + String(taxonomy || "")).toLowerCase();
		return text.indexOf("رنگ") !== -1 || text.indexOf("color") !== -1 || text.indexOf("colour") !== -1 || text.indexOf("rang") !== -1;
	}

	function makeTermPicker(taxonomy, labelText, selectedTerms, allowCreate, options) {
		options = options || {};
		var picker = node("div", "jluxe-pm-term-picker");
		picker.dataset.taxonomy = taxonomy;
		if (options.attribute) picker.dataset.attributePicker = "1";
		var stateMap = new Map();
		(selectedTerms || []).forEach(function (term) { stateMap.set(String(term.id), term); });
		picker._selectedTerms = stateMap;
		var selected = node("div", "jluxe-pm-term-chips");
		selected.setAttribute("aria-label", labelText + " انتخاب‌شده");
		picker.appendChild(selected);
		var input = node("input", "jluxe-pm-term-search");
		input.type = "search";
		input.placeholder = "جست‌وجوی «" + labelText + "»…";
		input.autocomplete = "off";
		input.setAttribute("aria-label", "جست‌وجوی " + labelText);
		picker.appendChild(input);
		var results = node("div", "jluxe-pm-term-results");
		results.hidden = true;
		results.setAttribute("role", "listbox");
		picker.appendChild(results);
		var foot = node("small", "jluxe-pm-hint", options.variation ? "این ویژگی در ساخت تنوع‌ها استفاده می‌شود." : "برای انتخاب، نام گزینه را جست‌وجو کنید.");
		picker.appendChild(foot);
		if (options.attribute) {
			var visibleLabel = node("label", "jluxe-pm-attribute-visible");
			var visible = node("input");
			visible.type = "checkbox";
			visible.checked = options.visible !== false;
			visible.dataset.attributeVisible = taxonomy;
			visibleLabel.appendChild(visible);
			visibleLabel.appendChild(node("span", "", "نمایش در مشخصات محصول"));
			picker.appendChild(visibleLabel);
		}
		function renderChips() {
			selected.replaceChildren();
			stateMap.forEach(function (term) {
				var chip = node("span", "jluxe-pm-term-chip");
				chip.dataset.id = String(term.id);
				chip.dataset.slug = term.slug || "";
				chip.appendChild(node("span", "", term.name));
				var remove = node("button", "jluxe-pm-chip-remove", "×");
				remove.type = "button";
				remove.dataset.pmRemoveTerm = String(term.id);
				remove.setAttribute("aria-label", "حذف " + term.name);
				chip.appendChild(remove);
				selected.appendChild(chip);
			});
		}
		renderChips();
		return picker;
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
			renderTermResults(picker, data.terms || [], query, !!data.canCreate);
		}).catch(function (error) {
			results.replaceChildren(node("span", "jluxe-pm-term-empty", error.message));
			results.hidden = false;
		});
	}

	function renderTermResults(picker, terms, query, canCreate) {
		var results = $(".jluxe-pm-term-results", picker);
		results.replaceChildren();
		var selected = picker._selectedTerms;
		var count = 0;
		terms.forEach(function (term) {
			if (selected.has(String(term.id))) return;
			var button = node("button", "jluxe-pm-term-result", term.name);
			button.type = "button";
			button.dataset.pmSelectTerm = String(term.id);
			button.dataset.name = term.name;
			button.dataset.slug = term.slug || "";
			results.appendChild(button);
			count++;
		});
		if (!count) {
			results.appendChild(node("span", "jluxe-pm-term-empty", query ? "گزینه‌ای پیدا نشد." : "برای انتخاب، بخشی از نام را بنویسید."));
		}
		if (query && canCreate && !terms.some(function (term) { return String(term.name).toLocaleLowerCase() === query.toLocaleLowerCase(); })) {
			var create = node("button", "jluxe-pm-term-create", "ساخت برچسب تازه: «" + query + "»");
			create.type = "button";
			create.dataset.pmCreateTag = query;
			results.appendChild(create);
		}
		results.hidden = false;
	}

	function addTermToPicker(picker, term) {
		picker._selectedTerms.set(String(term.id), term);
		var selected = $(".jluxe-pm-term-chips", picker);
		var chip = node("span", "jluxe-pm-term-chip");
		chip.dataset.id = String(term.id);
		chip.dataset.slug = term.slug || "";
		chip.appendChild(node("span", "", term.name));
		var remove = node("button", "jluxe-pm-chip-remove", "×");
		remove.type = "button";
		remove.dataset.pmRemoveTerm = String(term.id);
		remove.setAttribute("aria-label", "حذف " + term.name);
		chip.appendChild(remove);
		selected.appendChild(chip);
		$(".jluxe-pm-term-results", picker).hidden = true;
		$(".jluxe-pm-term-search", picker).value = "";
		setDirty();
		if (picker.dataset.attributePicker) refreshVariationAttributeOptions(picker.dataset.taxonomy);
	}

	function createTag(picker, name) {
		request("jluxe_product_manager_create_tag", { name: name }).then(function (data) {
			addTermToPicker(picker, data.term);
			showNotice(data.existing ? "این برچسب قبلاً وجود داشت و انتخاب شد." : "برچسب ساخته و انتخاب شد.", "success");
		}).catch(function (error) { showNotice(error.message, "error"); });
	}

	function refreshVariationAttributeOptions(taxonomy) {
		if (!state.currentProduct || !state.currentProduct.variations) return;
		var definition = (state.currentProduct.variations.attributes || []).find(function (attribute) { return attribute.name === taxonomy; });
		var picker = $$('.jluxe-pm-term-picker[data-attribute-picker="1"]', editorBody).find(function (item) { return item.dataset.taxonomy === taxonomy; });
		if (!definition || !picker) return;
		var previousOptions = definition.options || [];
		var nextOptions = Array.from(picker._selectedTerms.values()).map(function (term) {
			return { value: String(term.slug || ""), label: String(term.name || term.slug || "") };
		}).filter(function (option) { return option.value !== ""; });
		var nextValues = new Set(nextOptions.map(function (option) { return option.value; }));
		var removedOptions = state.removedVariationOptions[taxonomy] || {};
		previousOptions.forEach(function (option) {
			var value = String(option.value);
			if (value && !nextValues.has(value)) removedOptions[value] = String(option.label || value);
		});
		Object.keys(removedOptions).forEach(function (value) { if (nextValues.has(value)) delete removedOptions[value]; });
		state.removedVariationOptions[taxonomy] = removedOptions;
		definition.options = nextOptions;
		$$('[data-v-attr="' + cssEscape(taxonomy) + '"]', editorBody).forEach(function (select) {
			var previousValue = select.value;
			var previousOption = previousOptions.find(function (option) { return String(option.value) === String(previousValue); });
			select.replaceChildren();
			var any = node("option", "", "هر مقدار");
			any.value = "";
			select.appendChild(any);
			nextOptions.forEach(function (optionData) {
				var option = node("option", "", optionData.label);
				option.value = optionData.value;
				select.appendChild(option);
			});
			if (previousValue && !nextOptions.some(function (option) { return option.value === previousValue; })) {
				var removed = node("option", "", (previousOption && previousOption.label ? previousOption.label : previousValue) + " (برای این محصول حذف شده)");
				removed.value = previousValue;
				select.appendChild(removed);
			}
			select.value = previousValue;
		});
	}

	function cssEscape(value) {
		if (window.CSS && typeof window.CSS.escape === "function") return window.CSS.escape(value);
		return String(value).replace(/[^a-zA-Z0-9_-]/g, "\\$&");
	}

	function makeLocalAttributeRow(attribute) {
		attribute = attribute || {};
		var row = node("div", "jluxe-pm-local-attribute");
		var fields = node("div", "jluxe-pm-grid");
		var name = node("input", "jluxe-pm-control");
		name.type = "text";
		name.placeholder = "مثلاً جنس";
		name.value = attribute.name || "";
		name.dataset.localAttributeName = "1";
		fields.appendChild(field("نام ویژگی", name));
		var values = node("textarea", "jluxe-pm-control");
		values.rows = 2;
		values.placeholder = "مثلاً پنبه‌ای\nکتان";
		values.value = attribute.options || "";
		values.dataset.localAttributeOptions = "1";
		fields.appendChild(field("مقادیر (هر خط یک مقدار)", values));
		row.appendChild(fields);
		var footer = node("div", "jluxe-pm-local-footer");
		var visible = node("label", "jluxe-pm-inline-toggle");
		var checkbox = node("input");
		checkbox.type = "checkbox";
		checkbox.checked = attribute.visible !== false;
		checkbox.dataset.localAttributeVisible = "1";
		visible.appendChild(checkbox);
		visible.appendChild(node("span", "", "نمایش در صفحهٔ محصول"));
		footer.appendChild(visible);
		if (attribute.variation) footer.appendChild(node("span", "jluxe-pm-variation-mark", "ویژگی تنوع؛ برای ویرایش مقادیر از تنوع‌ها استفاده کنید"));
		var remove = node("button", "button-link-delete", "حذف ویژگی");
		remove.type = "button";
		remove.dataset.pmRemoveLocalAttribute = "1";
		footer.appendChild(remove);
		row.appendChild(footer);
		return row;
	}

	function renderSeo(panel, product) {
		var block = section("تنظیمات افزونهٔ سئو", product.seo.provider === "rankmath" ? "Rank Math فعال است؛ این فیلدها در متای محصول همان افزونه ذخیره می‌شوند." : "Yoast SEO فعال است؛ این فیلدها در متای محصول همان افزونه ذخیره می‌شوند.");
		block.appendChild(inputControl("seoTitle", "عنوان سئو", product.seo.title, "text", { className: "is-wide" }));
		block.appendChild(inputControl("seoDescription", "توضیحات متا", product.seo.description, "textarea", { rows: 3 }));
		block.appendChild(inputControl("seoFocusKeyword", "عبارت کلیدی کانونی", product.seo.focusKeyword, "text", { hint: "در صورت نیاز چند عبارت را با ویرگول جدا کنید." }));
		panel.appendChild(block);
	}

	function renderShipping(panel, product) {
		var dimensions = section("حمل‌ونقل", "وزن و اندازه فقط وقتی استفاده می‌شوند که محصول فیزیکی باشد. واحدها مطابق تنظیمات ووکامرس هستند.");
		var fields = grid();
		fields.appendChild(inputControl("weight", "وزن", product.weight, "number", { min: 0, step: "any" }));
		fields.appendChild(inputControl("length", "طول", product.length, "number", { min: 0, step: "any" }));
		fields.appendChild(inputControl("width", "عرض", product.width, "number", { min: 0, step: "any" }));
		fields.appendChild(inputControl("height", "ارتفاع", product.height, "number", { min: 0, step: "any" }));
		var shippingOptions = [{ value: 0, label: "بدون کلاس" }].concat((product.shippingClasses || []).map(function (item) { return { value: item.id, label: item.name }; }));
		fields.appendChild(selectControl("shippingClassId", "کلاس حمل‌ونقل", product.shippingClassId, shippingOptions));
		dimensions.appendChild(fields);
		panel.appendChild(dimensions);
		var taxes = section("مالیات", "کلاس‌ها از تنظیمات فعال فروشگاه خوانده می‌شوند.");
		taxes.appendChild(selectControl("taxStatus", "وضعیت مالیات", product.taxStatus, [
			{ value: "taxable", label: "مشمول مالیات" }, { value: "shipping", label: "فقط هزینهٔ حمل مشمول مالیات" }, { value: "none", label: "بدون مالیات" },
		]));
		var taxClassOptions = Object.keys(product.taxClasses || {}).map(function (key) { return { value: key, label: product.taxClasses[key] }; });
		taxes.appendChild(selectControl("taxClass", "کلاس مالیاتی", product.taxClass, taxClassOptions));
		panel.appendChild(taxes);
		panel.appendChild(inputControl("purchaseNote", "یادداشت پس از خرید", product.purchaseNote, "textarea", { rows: 3, hint: "اگر ووکامرس برای محصول نمایش دهد، پس از خرید به مشتری نشان داده می‌شود." }));
	}

	function renderMedia(panel, product) {
		var block = section("تصویر اصلی", config.canUpload ? "تصویر را از کتابخانهٔ رسانه انتخاب کنید؛ امکان بارگذاری تصویر تازه نیز از پنجرهٔ وردپرس وجود دارد." : "برای تغییر تصاویر، نقش کاربری باید مجوز بارگذاری رسانه داشته باشد.");
		var imagePicker = node("div", "jluxe-pm-image-picker");
		imagePicker.dataset.imageId = product.image && product.image.id ? product.image.id : "0";
		var preview = node("img", "jluxe-pm-image-preview");
		preview.alt = "پیش‌نمایش تصویر محصول";
		if (product.image && product.image.url) preview.src = product.image.url;
		else preview.hidden = true;
		imagePicker.appendChild(preview);
		var buttons = node("div", "jluxe-pm-image-actions");
		var choose = node("button", "button", "انتخاب تصویر اصلی");
		choose.type = "button";
		choose.dataset.pmChooseImage = "main";
		choose.disabled = !config.canUpload;
		buttons.appendChild(choose);
		var remove = node("button", "button-link-delete", "حذف تصویر");
		remove.type = "button";
		remove.dataset.pmRemoveImage = "main";
		remove.disabled = !config.canUpload;
		buttons.appendChild(remove);
		imagePicker.appendChild(buttons);
		block.appendChild(imagePicker);
		panel.appendChild(block);

		var gallery = section("گالری تصاویر", "گالری تا ۳۰ تصویر می‌پذیرد. تصاویر را با انتخاب چندتایی اضافه یا جداگانه حذف کنید.");
		var galleryWrap = node("div", "jluxe-pm-gallery");
		galleryWrap.id = "jluxe-pm-gallery";
		(product.gallery || []).forEach(function (image) { galleryWrap.appendChild(makeGalleryImage(image)); });
		gallery.appendChild(galleryWrap);
		var addGallery = node("button", "button", "افزودن تصویر به گالری");
		addGallery.type = "button";
		addGallery.dataset.pmChooseImage = "gallery";
		addGallery.disabled = !config.canUpload;
		gallery.appendChild(addGallery);
		panel.appendChild(gallery);
	}

	function makeGalleryImage(image) {
		var item = node("div", "jluxe-pm-gallery-item");
		item.dataset.id = String(image.id);
		var img = node("img");
		img.src = image.url;
		img.alt = "";
		item.appendChild(img);
		var remove = node("button", "jluxe-pm-gallery-remove", "×");
		remove.type = "button";
		remove.dataset.pmRemoveGallery = image.id;
		remove.setAttribute("aria-label", "حذف تصویر از گالری");
		item.appendChild(remove);
		return item;
	}

	function chooseMedia(type) {
		if (!config.canUpload || !window.wp || !window.wp.media) {
			showNotice("کتابخانهٔ رسانه در این صفحه در دسترس نیست.", "error");
			return;
		}
		var frame = window.wp.media({
			title: type === "main" ? "انتخاب تصویر محصول" : "انتخاب تصاویر گالری",
			button: { text: type === "main" ? "استفاده از تصویر" : "افزودن به گالری" },
			library: { type: "image" },
			multiple: type === "gallery",
		});
		frame.on("select", function () {
			var selection = frame.state().get("selection").toJSON();
			if (type === "main" && selection[0]) {
				var imagePicker = $(".jluxe-pm-image-picker", editorBody);
				var preview = $(".jluxe-pm-image-preview", imagePicker);
				imagePicker.dataset.imageId = String(selection[0].id);
				preview.src = (selection[0].sizes && selection[0].sizes.thumbnail ? selection[0].sizes.thumbnail.url : selection[0].url);
				preview.hidden = false;
				setDirty();
			} else if (type === "gallery") {
				var gallery = $("#jluxe-pm-gallery", editorBody);
				selection.forEach(function (image) {
					if (!$(".jluxe-pm-gallery-item[data-id='" + cssEscape(image.id) + "']", gallery)) {
						gallery.appendChild(makeGalleryImage({ id: image.id, url: image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url }));
					}
				});
				setDirty();
			}
		});
		frame.open();
	}

	function renderZarrin(panel, product) {
		var badges = section("نشان‌های محصول زرین", "این گزینه‌ها همان نشان‌هایی هستند که پوسته روی صفحهٔ محصول نمایش می‌دهد.");
		var badgeGrid = node("div", "jluxe-pm-check-grid");
		badgeGrid.appendChild(checkboxControl("badgeAuthenticity", "گارانتی اصالت کالا", product.badges && product.badges.authenticity));
		badgeGrid.appendChild(checkboxControl("badgeWarranty", "کالای دارای ضمانت", product.badges && product.badges.warranty));
		badges.appendChild(badgeGrid);
		var modalOptions = [
			{ value: "default", label: "پیش‌فرض پوسته" },
			{ value: "yes", label: "روشن" },
			{ value: "no", label: "خاموش" },
		];
		badges.appendChild(selectControl("suggestedModal", "پاپ‌آپ محصولات پیشنهادی پس از افزودن به سبد", product.suggestedModal, modalOptions, "گزینهٔ پیش‌فرض، تنظیم ذخیره‌شدهٔ قبلی را حذف می‌کند و رفتار پیش‌فرض پوسته را برمی‌گرداند."));
		panel.appendChild(badges);
		var faq = section("پرسش‌وپاسخ همین محصول", "پرسش‌های ناقص هنگام ذخیره حذف می‌شوند. این فهرست در بخش پرسش‌های صفحهٔ محصول نمایش داده می‌شود.");
		var list = node("div", "jluxe-pm-faq-list");
		list.id = "jluxe-pm-faq-list";
		(product.faq || []).forEach(function (item) { list.appendChild(makeFaqRow(item)); });
		faq.appendChild(list);
		var addFaq = node("button", "button", "افزودن پرسش و پاسخ");
		addFaq.type = "button";
		addFaq.dataset.pmAddFaq = "1";
		faq.appendChild(addFaq);
		panel.appendChild(faq);
	}

	function makeFaqRow(item) {
		item = item || {};
		var row = node("div", "jluxe-pm-faq-row");
		var questionField = inputControl("", "پرسش", item.question || "", "text");
		var question = $("input", questionField);
		question.dataset.faqQuestion = "1";
		row.appendChild(questionField);
		var answerField = inputControl("", "پاسخ", item.answer || "", "textarea", { rows: 3 });
		var answer = $("textarea", answerField);
		answer.dataset.faqAnswer = "1";
		row.appendChild(answerField);
		var remove = node("button", "button-link-delete", "حذف پرسش");
		remove.type = "button";
		remove.dataset.pmRemoveFaq = "1";
		row.appendChild(remove);
		return row;
	}

	function renderVariations(panel, variationData) {
		variationData = variationData || { items: [], page: 1, pages: 1, total: 0, attributes: [] };
		var block = section("تنوع‌های محصول", "قیمت، SKU، تعداد و وضعیت موجودی، پیش‌فروش، فعال‌بودن و مقادیر ویژگی را در همین جدول ویرایش کنید. هر ذخیره حداکثر ۱۰۰ تنوعِ تغییرکرده می‌پذیرد؛ برای ساخت یا حذف تنوع‌ها، ویرایشگر استاندارد ووکامرس را باز کنید.");
		block.appendChild(node("p", "jluxe-pm-muted", faNumber(variationData.total || 0) + " تنوع · صفحهٔ " + faNumber(variationData.page || 1) + " از " + faNumber(variationData.pages || 1)));
		if (!variationData.total) {
			block.appendChild(node("p", "jluxe-pm-empty", "برای این محصول هنوز تنوعی ساخته نشده است. برای ساخت ماتریس تنوع، ویرایشگر استاندارد ووکامرس را باز کنید."));
			panel.appendChild(block);
			return;
		}
		var tableWrap = node("div", "jluxe-pm-variation-wrap");
		var table = node("table", "widefat jluxe-pm-variation-table");
		var head = node("thead");
		var headRow = node("tr");
		(variationData.attributes || []).forEach(function (attribute) {
			var th = node("th", "", attribute.label);
			th.scope = "col";
			headRow.appendChild(th);
		});
		["SKU", "قیمت عادی", "حراج", "تعداد", "وضعیت موجودی", "پیش‌فروش", "مدیریت موجودی", "فعال"].forEach(function (label) {
			var th = node("th", "", label);
			th.scope = "col";
			headRow.appendChild(th);
		});
		head.appendChild(headRow);
		table.appendChild(head);
		var tbody = node("tbody");
		(variationData.items || []).forEach(function (variation) {
			tbody.appendChild(makeVariationRow(variation, variationData.attributes || []));
		});
		table.appendChild(tbody);
		tableWrap.appendChild(table);
		block.appendChild(tableWrap);
		var pager = node("div", "jluxe-pm-variation-pager");
		var previous = node("button", "button", "تنوع‌های قبلی");
		previous.type = "button";
		previous.disabled = variationData.page <= 1;
		previous.dataset.pmVariationPage = String((variationData.page || 1) - 1);
		var pageLabel = node("span", "", "صفحهٔ " + faNumber(variationData.page) + " از " + faNumber(variationData.pages));
		var next = node("button", "button", "تنوع‌های بعدی");
		next.type = "button";
		next.disabled = variationData.page >= variationData.pages;
		next.dataset.pmVariationPage = String((variationData.page || 1) + 1);
		pager.appendChild(previous);
		pager.appendChild(pageLabel);
		pager.appendChild(next);
		block.appendChild(pager);
		if (Object.keys(state.variationDraft).length) block.appendChild(node("p", "jluxe-pm-warning", faNumber(Object.keys(state.variationDraft).length) + " تنوع تغییرِ ذخیره‌نشده دارد؛ دکمهٔ ذخیره را بزنید."));
		panel.appendChild(block);
	}

	function makeVariationRow(variation, definitions) {
		var draft = state.variationDraft[String(variation.id)];
		var current = draft || variation;
		var tr = node("tr", "jluxe-pm-variation-row");
		tr.dataset.variationId = variation.id;
		definitions.forEach(function (attribute) {
			var td = node("td");
			var select = node("select", "jluxe-pm-control jluxe-pm-small-control");
			select.dataset.vAttr = attribute.name;
			select.dataset.variationId = variation.id;
			var any = node("option", "", "هر مقدار");
			any.value = "";
			select.appendChild(any);
			var currentAttributeValue = current.attributes && current.attributes[attribute.name] != null ? String(current.attributes[attribute.name]) : "";
			var hasCurrentOption = false;
			(attribute.options || []).forEach(function (option) {
				var item = node("option", "", option.label);
				item.value = option.value;
				item.selected = currentAttributeValue === String(option.value);
				if (item.selected) hasCurrentOption = true;
				select.appendChild(item);
			});
			if (currentAttributeValue && !hasCurrentOption) {
				var removedLabels = state.removedVariationOptions[attribute.name] || {};
				var removed = node("option", "", (removedLabels[currentAttributeValue] || currentAttributeValue) + " (برای این محصول حذف شده)");
				removed.value = currentAttributeValue;
				removed.selected = true;
				select.appendChild(removed);
			}
			td.appendChild(select);
			tr.appendChild(td);
		});
		tr.appendChild(variationInputCell(variation.id, "sku", current.sku, "text"));
		tr.appendChild(variationInputCell(variation.id, "regularPrice", current.regularPrice, "number"));
		tr.appendChild(variationInputCell(variation.id, "salePrice", current.salePrice, "number"));
		tr.appendChild(variationInputCell(variation.id, "stockQuantity", current.stockQuantity, "number"));
		tr.appendChild(variationSelectCell(variation.id, "stockStatus", current.stockStatus, [
			{ value: "instock", label: "موجود" }, { value: "outofstock", label: "ناموجود" }, { value: "onbackorder", label: "پیش‌سفارش" },
		]));
		tr.appendChild(variationSelectCell(variation.id, "backorders", current.backorders, [
			{ value: "no", label: "غیرفعال" }, { value: "notify", label: "با اطلاع" }, { value: "yes", label: "فعال" },
		]));
		var manageCell = node("td");
		var manage = node("input");
		manage.type = "checkbox";
		manage.checked = !!current.manageStock;
		manage.dataset.vField = "manageStock";
		manage.dataset.variationId = variation.id;
		manage.setAttribute("aria-label", "مدیریت موجودی تنوع " + variation.id);
		manageCell.appendChild(manage);
		tr.appendChild(manageCell);
		var enabledCell = node("td");
		var enabled = node("input");
		enabled.type = "checkbox";
		enabled.checked = !!current.enabled;
		enabled.dataset.vField = "enabled";
		enabled.dataset.variationId = variation.id;
		enabled.setAttribute("aria-label", "فعال‌بودن تنوع " + variation.id);
		enabledCell.appendChild(enabled);
		tr.appendChild(enabledCell);
		return tr;
	}

	function variationSelectCell(id, key, value, options) {
		var td = node("td");
		var select = node("select", "jluxe-pm-control jluxe-pm-small-control");
		select.dataset.vField = key;
		select.dataset.variationId = id;
		var labels = { stockStatus: "وضعیت موجودی", backorders: "پیش‌فروش" };
		select.setAttribute("aria-label", (labels[key] || key) + " تنوع " + id);
		options.forEach(function (option) {
			var item = node("option", "", option.label);
			item.value = option.value;
			item.selected = String(value || "") === String(option.value);
			select.appendChild(item);
		});
		td.appendChild(select);
		return td;
	}

	function variationInputCell(id, key, value, type) {
		var td = node("td");
		var input = node("input", "jluxe-pm-control jluxe-pm-small-control");
		input.type = type;
		input.value = value == null ? "" : String(value);
		if (type === "number") { input.min = "0"; input.step = "any"; }
		input.dataset.vField = key;
		input.dataset.variationId = id;
		var labels = { sku: "SKU", regularPrice: "قیمت عادی", salePrice: "قیمت حراج", stockQuantity: "موجودی" };
		input.setAttribute("aria-label", (labels[key] || key) + " تنوع " + id);
		td.appendChild(input);
		return td;
	}

	function loadVariationPage(page) {
		if (!state.currentProduct || state.saving) return;
		captureVisibleVariations();
		var body = $("#jluxe-pm-editor-body");
		var panel = $("[data-panel='variations']", body);
		var oldContent = panel;
		oldContent.classList.add("is-loading");
		var localAttributeDefinitions = (state.currentProduct.variations && state.currentProduct.variations.attributes) || [];
		request("jluxe_product_manager_get_variations", { product_id: state.currentProduct.id, page: page }).then(function (data) {
			data.attributes = localAttributeDefinitions;
			state.currentProduct.variations = data;
			state.variationPage = data.page;
			oldContent.replaceChildren();
			renderVariations(oldContent, data);
			oldContent.classList.remove("is-loading");
		}).catch(function (error) {
			oldContent.classList.remove("is-loading");
			showNotice(error.message, "error");
		});
	}

	function captureVisibleVariations() {
		var panel = $("[data-panel='variations']", editorBody);
		if (!panel) return;
		$$(".jluxe-pm-variation-row", panel).forEach(function (row) {
			var id = row.dataset.variationId;
			var original = (state.currentProduct.variations.items || []).find(function (item) { return String(item.id) === String(id); });
			if (!original) return;
			var data = {
				id: Number(id), sku: "", regularPrice: "", salePrice: "", stockQuantity: "", stockStatus: "instock", backorders: "no", manageStock: false, enabled: false, attributes: {},
			};
			$$('[data-v-field][data-variation-id="' + cssEscape(id) + '"]', row).forEach(function (input) {
				var key = input.dataset.vField;
				data[key] = input.type === "checkbox" ? input.checked : input.value;
			});
			$$('[data-v-attr][data-variation-id="' + cssEscape(id) + '"]', row).forEach(function (select) { data.attributes[select.dataset.vAttr] = select.value; });
			if (JSON.stringify(normalizeVariation(data)) === JSON.stringify(normalizeVariation(original))) delete state.variationDraft[String(id)];
			else state.variationDraft[String(id)] = data;
		});
		if (Object.keys(state.variationDraft).length) setDirty();
	}

	function normalizeVariation(item) {
		var attrs = {};
		Object.keys(item.attributes || {}).sort().forEach(function (key) {
			attrs[key] = item.attributes[key] == null ? "" : String(item.attributes[key]);
		});
		var text = function (value) { return value == null ? "" : String(value); };
		return {
			id: Number(item.id), sku: text(item.sku), regularPrice: text(item.regularPrice), salePrice: text(item.salePrice),
			stockQuantity: text(item.stockQuantity), stockStatus: text(item.stockStatus || "instock"), backorders: text(item.backorders || "no"),
			manageStock: !!item.manageStock, enabled: !!item.enabled, attributes: attrs,
		};
	}

	function collectFormPayload() {
		var product = state.currentProduct;
		var value = function (key) {
			var control = $("[data-field='" + key + "']", editorBody);
			if (!control) return "";
			return control.type === "checkbox" ? control.checked : control.value;
		};
		var payload = {
			id: product.id,
			name: value("name"), slug: value("slug"), sku: value("sku"), status: value("status"), catalogVisibility: value("catalogVisibility"),
			description: value("description"), shortDescription: value("shortDescription"), regularPrice: value("regularPrice"), salePrice: value("salePrice"),
			saleFrom: value("saleFrom"), saleTo: value("saleTo"), manageStock: value("manageStock"), stockQuantity: value("stockQuantity"), stockStatus: value("stockStatus"),
			backorders: value("backorders"), lowStockAmount: value("lowStockAmount"), soldIndividually: value("soldIndividually"), featured: value("featured"), virtual: value("virtual"),
			reviewsAllowed: value("reviewsAllowed"), purchaseNote: value("purchaseNote"), weight: value("weight"), length: value("length"), width: value("width"), height: value("height"),
			taxStatus: value("taxStatus"), taxClass: value("taxClass"), shippingClassId: value("shippingClassId"), categories: [], tags: [], brands: [], brandTaxonomy: product.brandTaxonomy || "",
			attributes: {}, localAttributes: [], imageId: 0, galleryIds: [], faq: [], badges: { authenticity: value("badgeAuthenticity"), warranty: value("badgeWarranty") },
			suggestedModal: value("suggestedModal"), seo: { title: value("seoTitle"), description: value("seoDescription"), focusKeyword: value("seoFocusKeyword") }, variations: [],
		};
		$$(".jluxe-pm-term-picker", editorBody).forEach(function (picker) {
			var ids = Array.from(picker._selectedTerms.keys()).map(Number);
			var taxonomy = picker.dataset.taxonomy;
			if (picker.dataset.attributePicker) {
				var visible = $("[data-attribute-visible='" + cssEscape(taxonomy) + "']", picker);
				payload.attributes[taxonomy] = { term_ids: ids, visible: visible ? visible.checked : true, clear: true };
			} else if (taxonomy === "product_cat") payload.categories = ids;
			else if (taxonomy === "product_tag") payload.tags = ids;
			else if (taxonomy === product.brandTaxonomy) payload.brands = ids;
		});
		$$(".jluxe-pm-local-attribute", editorBody).forEach(function (row) {
			payload.localAttributes.push({ name: $("[data-local-attribute-name]", row).value, options: $("[data-local-attribute-options]", row).value, visible: $("[data-local-attribute-visible]", row).checked });
		});
		var imagePicker = $(".jluxe-pm-image-picker", editorBody);
		payload.imageId = imagePicker ? Number(imagePicker.dataset.imageId || 0) : 0;
		payload.galleryIds = $$(".jluxe-pm-gallery-item", editorBody).map(function (image) { return Number(image.dataset.id); });
		payload.faq = $$(".jluxe-pm-faq-row", editorBody).map(function (row) { return { question: $("[data-faq-question]", row).value, answer: $("[data-faq-answer]", row).value }; });
		captureVisibleVariations();
		payload.variations = Object.keys(state.variationDraft).map(function (key) { return state.variationDraft[key]; });
		return payload;
	}

	function saveProduct() {
		if (!state.currentProduct || state.saving) return;
		captureVisibleVariations();
		if (Object.keys(state.variationDraft).length > 100) {
			showNotice("در هر ذخیره حداکثر ۱۰۰ تنوع تغییرکرده پذیرفته می‌شود. بخشی از تغییرات را به حالت قبلی برگردانید یا از ویرایشگر استاندارد استفاده کنید.", "warning");
			return;
		}
		var payload = collectFormPayload();
		var button = $("#jluxe-pm-save");
		state.saving = true;
		button.disabled = true;
		button.textContent = "در حال ذخیرهٔ محصول…";
		$("#jluxe-pm-save-state").textContent = "در حال ذخیره؛ پنجره را نبندید…";
		request("jluxe_product_manager_save_product", { payload: payload }).then(function (result) {
			state.currentProduct = result.product;
			state.variationDraft = {};
			state.removedVariationOptions = {};
			state.dirty = false;
			$("#jluxe-pm-editor-title").textContent = result.product.name || "محصول";
			$("#jluxe-pm-editor-subtitle").textContent = "شناسهٔ " + faNumber(result.product.id) + " · " + (result.product.type === "variable" ? "محصول متغیر" : "محصول ساده") + " · SKU: " + (result.product.sku || "—");
			$("#jluxe-pm-native-edit").href = result.product.editUrl || "#";
			renderEditor();
			$("#jluxe-pm-save-state").textContent = result.message || "ذخیره شد.";
			showNotice(result.message || "تغییرات ذخیره شد.", "success");
			loadProducts(false);
		}).catch(function (error) {
			$("#jluxe-pm-save-state").textContent = error.message;
			showNotice(error.message, error.partial ? "warning" : "error");
		}).finally(function () {
			state.saving = false;
			button.disabled = !state.dirty;
			button.textContent = "ذخیرهٔ تغییرات";
		});
	}

	function setDirty() {
		state.dirty = true;
		$("#jluxe-pm-save").disabled = false;
		$("#jluxe-pm-save-state").textContent = "تغییر ذخیره‌نشده";
	}

	function switchTab(key) {
		$$(".jluxe-pm-tab", $("#jluxe-pm-tabs")).forEach(function (tab) {
			var active = tab.dataset.tab === key;
			tab.classList.toggle("is-active", active);
			tab.setAttribute("aria-selected", active ? "true" : "false");
		});
		$$(".jluxe-pm-tab-panel", editorBody).forEach(function (panel) {
			var active = panel.dataset.panel === key;
			panel.hidden = !active;
			panel.classList.toggle("is-active", active);
		});
	}

	function handleClick(event) {
		var target = event.target;
		var button = target.closest("button");
		if (!button) return;
		if (button.dataset.pmEdit) { openEditor(Number(button.dataset.pmEdit)); return; }
		if (button.dataset.pmQuick) {
			var id = Number(button.dataset.pmQuick);
			var product = state.products.find(function (item) { return Number(item.id) === id; });
			if (!product) return;
			var row = button.closest("tr");
			if (row.nextElementSibling && row.nextElementSibling.dataset.quickFor === String(id)) row.nextElementSibling.remove();
			else row.after(makeQuickEditor(product));
			return;
		}
		if (button.dataset.pmQuickClose) { button.closest(".jluxe-pm-quick-row").remove(); return; }
		if (button.dataset.pmVariationPage) { loadVariationPage(Number(button.dataset.pmVariationPage)); return; }
		if (button.dataset.tab) { switchTab(button.dataset.tab); return; }
		if (button.hasAttribute("data-pm-close-editor")) { closeEditor(false); return; }
		if (button.hasAttribute("data-pm-close-bulk")) { closeBulk(); return; }
		if (button.dataset.pmSelectTerm) {
			var picker = button.closest(".jluxe-pm-term-picker");
			addTermToPicker(picker, { id: button.dataset.pmSelectTerm, name: button.dataset.name, slug: button.dataset.slug });
			return;
		}
		if (button.dataset.pmRemoveTerm) {
			var chip = button.closest(".jluxe-pm-term-picker");
			chip._selectedTerms.delete(String(button.dataset.pmRemoveTerm));
			chip.querySelectorAll(".jluxe-pm-term-chip").forEach(function (item) { if (item.dataset.id === String(button.dataset.pmRemoveTerm)) item.remove(); });
			setDirty();
			if (chip.dataset.attributePicker) refreshVariationAttributeOptions(chip.dataset.taxonomy);
			return;
		}
		if (button.dataset.pmCreateTag) { createTag(button.closest(".jluxe-pm-term-picker"), button.dataset.pmCreateTag); return; }
		if (button.dataset.pmAddLocalAttribute) {
			var localList = $(".jluxe-pm-local-attributes", editorBody);
			localList.appendChild(makeLocalAttributeRow({ name: "", options: "", visible: true }));
			setDirty();
			return;
		}
		if (button.dataset.pmRemoveLocalAttribute) { button.closest(".jluxe-pm-local-attribute").remove(); setDirty(); return; }
		if (button.dataset.pmChooseImage) { chooseMedia(button.dataset.pmChooseImage); return; }
		if (button.dataset.pmRemoveImage) {
			var imagePicker = $(".jluxe-pm-image-picker", editorBody);
			imagePicker.dataset.imageId = "0";
			$(".jluxe-pm-image-preview", imagePicker).hidden = true;
			setDirty();
			return;
		}
		if (button.dataset.pmRemoveGallery) { button.closest(".jluxe-pm-gallery-item").remove(); setDirty(); return; }
		if (button.dataset.pmAddFaq) { $("#jluxe-pm-faq-list", editorBody).appendChild(makeFaqRow({})); setDirty(); return; }
		if (button.dataset.pmRemoveFaq) { button.closest(".jluxe-pm-faq-row").remove(); setDirty(); return; }
	}

	function handleChange(event) {
		var target = event.target;
		if (target.matches(".jluxe-pm-product-check")) {
			var id = Number(target.dataset.id);
			if (target.checked) {
				if (state.selected.size >= 25) { target.checked = false; showNotice("در هر عملیات گروهی حداکثر ۲۵ محصول انتخاب می‌شود.", "warning"); }
				else state.selected.add(id);
			} else state.selected.delete(id);
			updateSelectionUI();
			return;
		}
		if (target.id === "jluxe-pm-select-all") {
			$$(".jluxe-pm-product-check").forEach(function (box) {
				var id = Number(box.dataset.id);
				if (target.checked && state.selected.size < 25) { state.selected.add(id); box.checked = true; }
				else if (!target.checked) { state.selected.delete(id); box.checked = false; }
			});
			updateSelectionUI();
			return;
		}
		if (target.dataset.field || target.matches("[data-local-attribute-name], [data-local-attribute-options], [data-local-attribute-visible], [data-faq-question], [data-faq-answer], [data-v-field], [data-v-attr]")) {
			setDirty();
			return;
		}
		if (target.id === "jluxe-pm-bulk-field") { updateBulkOperations(); return; }
		if (target.matches(".jluxe-pm-term-search")) return;
	}

	function handleInput(event) {
		var target = event.target;
		if (target.matches("#jluxe-pm-search")) {
			window.clearTimeout(target._pmTimer);
			target._pmTimer = window.setTimeout(function () { loadProducts(true); }, 280);
			return;
		}
		if (target.matches(".jluxe-pm-term-search")) {
			window.clearTimeout(target._pmTimer);
			target._pmTimer = window.setTimeout(function () { searchTerms(target); }, 220);
			return;
		}
		if (target.dataset.field || target.matches("[data-local-attribute-name], [data-local-attribute-options], [data-faq-question], [data-faq-answer], [data-v-field], [data-v-attr]")) setDirty();
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
		$("#jluxe-pm-category").value = "";
		state.selected.clear();
		updateSelectionUI();
		loadProducts(true);
	});
	["#jluxe-pm-type", "#jluxe-pm-status", "#jluxe-pm-stock", "#jluxe-pm-category"].forEach(function (selector) {
		$(selector).addEventListener("change", function () { loadProducts(true); });
	});
	$("#jluxe-pm-refresh").addEventListener("click", function () { loadProducts(false); });
	$("#jluxe-pm-prev").addEventListener("click", function () { if (state.page > 1) { state.page--; loadProducts(false); } });
	$("#jluxe-pm-next").addEventListener("click", function () { if (state.page < state.pages) { state.page++; loadProducts(false); } });
	$("#jluxe-pm-bulk-open").addEventListener("click", openBulk);
	$("#jluxe-pm-bulk-apply").addEventListener("click", applyBulk);
	$("#jluxe-pm-save").addEventListener("click", saveProduct);
	document.addEventListener("keydown", function (event) {
		if (editor.hidden) return;
		if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
			event.preventDefault();
			saveProduct();
		} else if (event.key === "Escape") {
			closeEditor(false);
		}
	});
	root.addEventListener("click", function (event) {
		if (event.target.matches(".jluxe-pm-editor-backdrop")) closeEditor(false);
		if (event.target.matches(".jluxe-pm-bulk-panel")) closeBulk();
	});

	loadProducts(true);
})();
