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
		currentProduct: null,
		variationDraft: Object.create(null),
		variationSaves: Object.create(null),
		inlineSaves: Object.create(null),
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

	function setLoading(message) {
		rows.replaceChildren();
		var tr = node("tr");
		var td = node("td", "jluxe-pm-loading", message || "در حال دریافت محصولات…");
		td.colSpan = 5;
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
			brand: $("#jluxe-pm-brand").value,
			attribute_taxonomy: $("#jluxe-pm-attribute-taxonomy").value,
			attribute_term: $("#jluxe-pm-attribute-term").value,
		};
	}

	function loadProducts(resetPage) {
		if (resetPage) state.page = 1;
		if (state.loading) {
			state.pendingReload = state.page;
			return;
		}
		state.loading = true;
		showNotice("", "");
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

	function stockLabel(status) {
		var labels = { instock: "موجود", outofstock: "ناموجود", onbackorder: "پیش‌سفارش" };
		return labels[status] || "وضعیت نامشخص";
	}

	function stockClass(status) {
		return status === "outofstock" ? "is-out" : (status === "onbackorder" ? "is-backorder" : "is-in");
	}

	function renderProducts() {
		rows.replaceChildren();
		if (!state.products.length) {
			var empty = node("tr");
			var cell = node("td", "jluxe-pm-empty", "محصولی با این جست‌وجو و فیلتر پیدا نشد.");
			cell.colSpan = 5;
			empty.appendChild(cell);
			rows.appendChild(empty);
		} else {
			state.products.forEach(function (product) { rows.appendChild(makeProductRow(product)); });
		}
		$("#jluxe-pm-summary").textContent = state.total ? faNumber(state.total) + " محصول" : "۰ محصول";
		$("#jluxe-pm-page-label").textContent = "صفحهٔ " + faNumber(state.page) + " از " + faNumber(state.pages);
		$("#jluxe-pm-prev").disabled = state.page <= 1;
		$("#jluxe-pm-next").disabled = state.page >= state.pages;
	}

	function makeProductRow(product) {
		var tr = node("tr", "jluxe-pm-product-row");
		tr.dataset.productId = String(product.id);

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
		nameButton.dataset.pmEdit = String(product.id);
		identity.appendChild(nameButton);
		var subline = node("div", "jluxe-pm-subline");
		subline.appendChild(node("span", "jluxe-pm-type-pill is-" + product.type, product.typeLabel || (product.type === "variable" ? "متغیر" : "ساده")));
		if (product.sku) subline.appendChild(node("span", "jluxe-pm-sku", "SKU: " + product.sku));
		identity.appendChild(subline);
		productCell.appendChild(identity);
		tr.appendChild(productCell);

		var priceCell = node("td", "jluxe-pm-price-cell");
		if (product.type === "simple") {
			var priceInput = node("input", "jluxe-pm-inline-input");
			priceInput.type = "number";
			priceInput.min = "0";
			priceInput.step = "any";
			priceInput.inputMode = "decimal";
			priceInput.value = product.regularPrice == null ? "" : String(product.regularPrice);
			priceInput.placeholder = "ثبت قیمت";
			priceInput.dataset.pmInlineField = "regularPrice";
			priceInput.dataset.productId = String(product.id);
			priceInput.setAttribute("aria-label", "قیمت عادیِ " + (product.name || "محصول"));
			priceCell.appendChild(priceInput);
			if (product.salePrice !== "" && product.salePrice != null) {
				priceCell.appendChild(node("small", "jluxe-pm-cell-hint", "حراج: " + product.salePrice));
			}
		} else {
			priceCell.appendChild(node("span", "jluxe-pm-readonly-value", product.priceLabel || "—"));
			priceCell.appendChild(node("small", "jluxe-pm-cell-hint", "از جدول تنوع‌ها"));
		}
		if (config.currency) priceCell.appendChild(node("small", "jluxe-pm-currency", config.currency));
		tr.appendChild(priceCell);

		var stockCell = node("td", "jluxe-pm-stock-cell");
		if (product.type === "simple") {
			var stockInput = node("input", "jluxe-pm-inline-input");
			stockInput.type = "number";
			stockInput.min = "0";
			stockInput.step = "any";
			stockInput.inputMode = "decimal";
			stockInput.value = product.manageStock && product.stockQuantity != null ? String(product.stockQuantity) : "";
			stockInput.placeholder = product.manageStock ? "تعداد" : "ثبت تعداد";
			stockInput.dataset.pmInlineField = "stockQuantity";
			stockInput.dataset.productId = String(product.id);
			stockInput.setAttribute("aria-label", "تعداد موجودیِ " + (product.name || "محصول"));
			stockCell.appendChild(stockInput);
			stockCell.appendChild(node("span", "jluxe-pm-stock-pill " + stockClass(product.stockStatus), stockLabel(product.stockStatus)));
			if (!product.manageStock) stockCell.appendChild(node("small", "jluxe-pm-cell-hint", "با ثبت عدد، موجودی عددی فعال می‌شود."));
		} else {
			stockCell.appendChild(node("span", "jluxe-pm-readonly-value", product.stockLabel || stockLabel(product.stockStatus)));
		}
		tr.appendChild(stockCell);

		tr.appendChild(node("td", "jluxe-pm-category-cell", product.categories || "—"));
		var actions = node("td", "jluxe-pm-actions");
		var manage = node("button", "button button-primary button-small", product.type === "variable" ? "دسته، برند و تنوع‌ها" : "دسته و برند");
		manage.type = "button";
		manage.dataset.pmEdit = String(product.id);
		actions.appendChild(manage);
		var status = node("span", "jluxe-pm-row-status");
		status.dataset.pmRowStatus = String(product.id);
		status.setAttribute("aria-live", "polite");
		var tracker = state.inlineSaves[String(product.id)];
		if (tracker && tracker.status) setStatusContent(status, tracker.status, tracker.kind);
		actions.appendChild(status);
		tr.appendChild(actions);
		return tr;
	}

	function setStatusContent(element, message, kind) {
		element.className = "jluxe-pm-row-status" + (kind ? " " + kind : "");
		element.textContent = message || "";
		if (message) element.title = message;
		else element.removeAttribute("title");
	}

	function setProductStatus(productId, message, kind) {
		var target = $("[data-pm-row-status='" + String(productId) + "']", rows);
		if (target) setStatusContent(target, message, kind);
	}

	function setVariationStatus(variationId, message, kind) {
		var target = $("[data-pm-variation-status='" + String(variationId) + "']", editorBody);
		if (target) {
			target.className = "jluxe-pm-row-status jluxe-pm-variation-status" + (kind ? " is-" + kind : "");
			target.textContent = message || "";
			if (message) target.title = message;
			else target.removeAttribute("title");
		}
	}

	function productSaveTracker(productId) {
		var key = String(productId);
		if (!state.inlineSaves[key]) state.inlineSaves[key] = { revision: 0, timer: null, inFlight: false, pending: false, status: "", kind: "" };
		return state.inlineSaves[key];
	}

	function scheduleProductSave(productId) {
		var product = state.products.find(function (item) { return String(item.id) === String(productId); });
		if (!product) return;
		var tracker = productSaveTracker(productId);
		tracker.revision++;
		tracker.status = "در حال ذخیره…";
		tracker.kind = "is-saving";
		setProductStatus(productId, tracker.status, tracker.kind);
		window.clearTimeout(tracker.timer);
		tracker.timer = window.setTimeout(function () { tracker.timer = null; sendProductSave(productId, tracker); }, 420);
	}

	function syncProductRow(product) {
		var row = $("[data-product-id='" + String(product.id) + "']", rows);
		if (!row) return;
		var price = $("[data-pm-inline-field='regularPrice']", row);
		if (price && document.activeElement !== price) price.value = product.regularPrice == null ? "" : String(product.regularPrice);
		var quantity = $("[data-pm-inline-field='stockQuantity']", row);
		if (quantity && document.activeElement !== quantity) quantity.value = product.manageStock && product.stockQuantity != null ? String(product.stockQuantity) : "";
		if (quantity) quantity.placeholder = product.manageStock ? "تعداد" : "ثبت تعداد";
		var pill = $(".jluxe-pm-stock-pill", row);
		if (pill) {
			pill.className = "jluxe-pm-stock-pill " + stockClass(product.stockStatus);
			pill.textContent = stockLabel(product.stockStatus);
		}
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
			regular_price: product.regularPrice == null ? "" : product.regularPrice,
			sale_price: product.salePrice == null ? "" : product.salePrice,
			manage_stock: product.manageStock ? "1" : "0",
			stock_quantity: product.stockQuantity == null ? "" : product.stockQuantity,
		};
		request("jluxe_product_manager_quick_save", snapshot).then(function (result) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			if (result.product) {
				Object.keys(result.product).forEach(function (key) { product[key] = result.product[key]; });
			}
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

	function openEditor(productId) {
		if (!productId || state.saving) return;
		state.lastFocus = document.activeElement;
		state.currentProduct = null;
		state.variationDraft = Object.create(null);
		state.variationSaves = Object.create(null);
		state.variationPage = 1;
		var requestId = ++state.editorRequest;
		state.dirty = false;
		editor.hidden = false;
		document.body.classList.add("jluxe-pm-modal-open");
		$("#jluxe-pm-editor-title").textContent = "در حال بارگذاری…";
		$("#jluxe-pm-editor-subtitle").textContent = "";
		$("#jluxe-pm-save").disabled = true;
		$("#jluxe-pm-save-state").textContent = "";
		editorBody.replaceChildren(node("p", "jluxe-pm-loading", "در حال دریافت اطلاعات محصول…"));
		editorCard.focus();
		request("jluxe_product_manager_get_product", { product_id: productId, variation_page: 1 }).then(function (product) {
			if (requestId !== state.editorRequest) return;
			state.currentProduct = product;
			state.variationPage = product.variations && product.variations.page ? product.variations.page : 1;
			$("#jluxe-pm-editor-title").textContent = product.name || "محصول بدون نام";
			$("#jluxe-pm-editor-subtitle").textContent = (product.type === "variable" ? "محصول متغیر" : "محصول ساده") + (product.sku ? " · SKU: " + product.sku : "");
			$("#jluxe-pm-native-edit").href = product.editUrl || "#";
			renderEditor();
		}).catch(function (error) {
			if (requestId !== state.editorRequest) return;
			editorBody.replaceChildren(node("p", "jluxe-pm-error", error.message));
		});
	}

	function pendingVariationSave() {
		return Object.keys(state.variationSaves).some(function (id) {
			var tracker = state.variationSaves[id];
			return !!(tracker.timer || tracker.inFlight || tracker.pending);
		});
	}

	function closeEditor(force) {
		if (state.saving) {
			showNotice("ذخیرهٔ دسته‌بندی و برند در حال انجام است؛ تا پایان آن پنجره را نبندید.", "warning");
			return;
		}
		if (pendingVariationSave()) {
			showNotice("ذخیرهٔ خودکار تنوع‌ها در حال انجام است؛ پس از نمایش وضعیت ذخیره، پنجره را ببندید.", "warning");
			return;
		}
		var hasUnsaved = state.dirty || Object.keys(state.variationDraft).length > 0;
		if (!force && hasUnsaved && !window.confirm("تغییر ذخیره‌نشده‌ای وجود دارد. پنجره بسته شود؟")) return;
		state.editorRequest++;
		editor.hidden = true;
		document.body.classList.remove("jluxe-pm-modal-open");
		state.currentProduct = null;
		state.variationDraft = Object.create(null);
		state.dirty = false;
		if (state.lastFocus && typeof state.lastFocus.focus === "function") state.lastFocus.focus();
	}

	function renderEditor() {
		var product = state.currentProduct;
		if (!product) return;
		editorBody.replaceChildren();
		var organizationPanel = node("section", "jluxe-pm-editor-panel");
		organizationPanel.dataset.panel = "organization";
		renderOrganization(organizationPanel, product);
		editorBody.appendChild(organizationPanel);
		if (product.type === "variable") {
			var variationPanel = node("section", "jluxe-pm-editor-panel");
			variationPanel.dataset.panel = "variations";
			renderVariations(variationPanel, product.variations);
			editorBody.appendChild(variationPanel);
		}
		state.dirty = false;
		$("#jluxe-pm-save").disabled = true;
		$("#jluxe-pm-save-state").textContent = "";
	}

	function section(title, description) {
		var wrap = node("section", "jluxe-pm-section");
		wrap.appendChild(node("h3", "jluxe-pm-section-title", title));
		if (description) wrap.appendChild(node("p", "jluxe-pm-section-description", description));
		return wrap;
	}

	function renderOrganization(panel, product) {
		var block = section("دسته‌بندی و برند", "برای پیدا کردن هر مورد، نام آن را جست‌وجو و انتخاب کنید؛ سپس یک‌بار «ذخیرهٔ دسته و برند» را بزنید.");
		block.appendChild(makeTermPicker("product_cat", "دسته‌بندی‌ها", product.categories || []));
		if (product.brandTaxonomy) {
			block.appendChild(makeTermPicker(product.brandTaxonomy, "برند", product.brands || []));
		} else {
			block.appendChild(node("p", "jluxe-pm-muted", "واژه‌نامهٔ برند فعال نیست؛ جزئیات بیشتر را از ویرایشگر استاندارد ووکامرس بررسی کنید."));
		}
		panel.appendChild(block);
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
		picker.appendChild(node("small", "jluxe-pm-hint", "برای افزودن، نام را جست‌وجو کنید؛ برای برداشتن، × کنار نام را بزنید."));
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

	function variationTracker(variationId) {
		var key = String(variationId);
		if (!state.variationSaves[key]) state.variationSaves[key] = { revision: 0, timer: null, inFlight: false, pending: false, status: "", kind: "" };
		return state.variationSaves[key];
	}

	function findVariation(variationId) {
		if (!state.currentProduct || !state.currentProduct.variations) return null;
		return (state.currentProduct.variations.items || []).find(function (item) { return String(item.id) === String(variationId); }) || null;
	}

	function readVariationRow(row) {
		var variationId = row.dataset.variationId;
		var original = state.variationDraft[String(variationId)] || findVariation(variationId) || { id: Number(variationId), sku: "", regularPrice: "", salePrice: "", stockQuantity: "", manageStock: false, stockStatus: "instock", backorders: "no", enabled: true, attributes: {} };
		var data = Object.assign({}, original, { attributes: Object.assign({}, original.attributes || {}) });
		$$('[data-v-field]', row).forEach(function (input) {
			data[input.dataset.vField] = input.value;
		});
		$$('[data-v-attr]', row).forEach(function (select) {
			data.attributes[select.dataset.vAttr] = select.value;
		});
		if (String(data.stockQuantity == null ? "" : data.stockQuantity) !== "") {
			data.manageStock = true;
			var quantity = Number(String(data.stockQuantity).replace(/٬/g, "").replace(/٫/g, "."));
			if (Number.isFinite(quantity)) data.stockStatus = quantity > 0 ? "instock" : "outofstock";
		}
		return data;
	}

	function scheduleVariationSave(row) {
		var variationId = row.dataset.variationId;
		var tracker = variationTracker(variationId);
		state.variationDraft[String(variationId)] = readVariationRow(row);
		tracker.revision++;
		tracker.status = "در حال ذخیره…";
		tracker.kind = "is-saving";
		setVariationStatus(variationId, tracker.status, tracker.kind);
		window.clearTimeout(tracker.timer);
		tracker.timer = window.setTimeout(function () { tracker.timer = null; sendVariationSave(variationId, tracker); }, 420);
	}

	function updateVariationInCurrentProduct(variation) {
		if (!variation || !state.currentProduct || !state.currentProduct.variations) return;
		var items = state.currentProduct.variations.items || [];
		var index = items.findIndex(function (item) { return String(item.id) === String(variation.id); });
		if (index >= 0) items[index] = variation;
	}

	function sendVariationSave(variationId, tracker) {
		if (tracker.inFlight) { tracker.pending = true; return; }
		var product = state.currentProduct;
		var variation = state.variationDraft[String(variationId)];
		if (!product || !variation) return;
		tracker.inFlight = true;
		tracker.pending = false;
		var revision = tracker.revision;
		var snapshot = Object.assign({}, variation, { attributes: Object.assign({}, variation.attributes || {}) });
		request("jluxe_product_manager_quick_save_variation", {
			parent_id: product.id,
			variation_id: variationId,
			page: product.variations && product.variations.page ? product.variations.page : 1,
			variation: snapshot,
		}).then(function (result) {
			if (revision !== tracker.revision) { tracker.pending = true; return; }
			var updated = result.variation || snapshot;
			updateVariationInCurrentProduct(updated);
			delete state.variationDraft[String(variationId)];
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
				sendVariationSave(variationId, tracker);
			}
		});
	}

	function renderVariations(panel, variationData) {
		variationData = variationData || { items: [], page: 1, pages: 1, total: 0, attributes: [] };
		panel.classList.remove("is-loading");
		panel.replaceChildren();
		var block = section("تنوع‌ها: اندازه، قیمت و موجودی", "هر تغییر به‌طور خودکار ذخیره می‌شود. مقادیر ترکیب‌های محصول از ویژگی‌های ازپیش‌تعریف‌شده می‌آیند؛ ساختار یا تنظیمات تخصصی را در ویرایشگر ووکامرس تغییر دهید.");
		block.appendChild(node("p", "jluxe-pm-muted", faNumber(variationData.total || 0) + " تنوع"));
		if (!variationData.total) {
			block.appendChild(node("p", "jluxe-pm-empty", "برای این محصول تنوعی ثبت نشده است. ساخت تنوع از ویرایشگر استاندارد ووکامرس انجام می‌شود."));
			panel.appendChild(block);
			return;
		}
		var tableWrap = node("div", "jluxe-pm-variation-wrap");
		var table = node("table", "widefat jluxe-pm-variation-table");
		var head = node("thead");
		var headRow = node("tr");
		var definitions = variationData.attributes || [];
		if (!definitions.length) headRow.appendChild(node("th", "", "ویژگی"));
		definitions.forEach(function (attribute) { headRow.appendChild(node("th", "", attribute.label)); });
		["قیمت عادی", "حراج", "موجودی", "وضعیت", "ذخیره"].forEach(function (label) { headRow.appendChild(node("th", "", label)); });
		head.appendChild(headRow);
		table.appendChild(head);
		var tbody = node("tbody");
		(variationData.items || []).forEach(function (variation) { tbody.appendChild(makeVariationRow(variation, definitions)); });
		table.appendChild(tbody);
		tableWrap.appendChild(table);
		block.appendChild(tableWrap);
		if ((variationData.pages || 1) > 1) {
			var pager = node("div", "jluxe-pm-variation-pager");
			var previous = node("button", "button", "قبلی");
			previous.type = "button";
			previous.disabled = variationData.page <= 1;
			previous.dataset.pmVariationPage = String((variationData.page || 1) - 1);
			var label = node("span", "", "صفحهٔ " + faNumber(variationData.page) + " از " + faNumber(variationData.pages));
			var next = node("button", "button", "بعدی");
			next.type = "button";
			next.disabled = variationData.page >= variationData.pages;
			next.dataset.pmVariationPage = String((variationData.page || 1) + 1);
			pager.appendChild(previous);
			pager.appendChild(label);
			pager.appendChild(next);
			block.appendChild(pager);
		}
		panel.appendChild(block);
	}

	function makeVariationRow(variation, definitions) {
		var current = state.variationDraft[String(variation.id)] || variation;
		var tr = node("tr", "jluxe-pm-variation-row");
		tr.dataset.variationId = String(variation.id);
		if (!definitions.length) tr.appendChild(node("td", "jluxe-pm-muted", "برای تعریف ویژگی‌ها، ویرایشگر ووکامرس را باز کنید."));
		definitions.forEach(function (attribute) {
			var td = node("td");
			var select = node("select", "jluxe-pm-control jluxe-pm-small-control");
			select.dataset.vAttr = attribute.name;
			select.dataset.variationId = String(variation.id);
			select.setAttribute("aria-label", attribute.label + " تنوع " + faNumber(variation.id));
			var any = node("option", "", "هر مقدار");
			any.value = "";
			select.appendChild(any);
			var currentValue = current.attributes && current.attributes[attribute.name] != null ? String(current.attributes[attribute.name]) : "";
			var found = false;
			(attribute.options || []).forEach(function (option) {
				var item = node("option", "", option.label);
				item.value = String(option.value);
				item.selected = currentValue === String(option.value);
				if (item.selected) found = true;
				select.appendChild(item);
			});
			if (currentValue && !found) {
				var unknown = node("option", "", currentValue);
				unknown.value = currentValue;
				unknown.selected = true;
				select.appendChild(unknown);
			}
			td.appendChild(select);
			tr.appendChild(td);
		});
		tr.appendChild(variationInputCell(variation.id, "regularPrice", current.regularPrice, "قیمت عادی"));
		tr.appendChild(variationInputCell(variation.id, "salePrice", current.salePrice, "قیمت حراج"));
		var stock = variationInputCell(variation.id, "stockQuantity", current.manageStock ? current.stockQuantity : "", "تعداد موجودی");
		var stockInput = $("input", stock);
		stockInput.placeholder = current.manageStock ? "تعداد" : "ثبت تعداد";
		tr.appendChild(stock);
		var stockStatusCell = node("td");
		stockStatusCell.appendChild(node("span", "jluxe-pm-stock-pill " + stockClass(current.stockStatus), stockLabel(current.stockStatus)));
		tr.appendChild(stockStatusCell);
		var statusCell = node("td");
		var status = node("span", "jluxe-pm-row-status jluxe-pm-variation-status");
		status.dataset.pmVariationStatus = String(variation.id);
		status.setAttribute("aria-live", "polite");
		var tracker = state.variationSaves[String(variation.id)];
		if (tracker && tracker.status) {
			status.className += " " + tracker.kind;
			status.textContent = tracker.status;
			status.title = tracker.status;
		}
		statusCell.appendChild(status);
		tr.appendChild(statusCell);
		return tr;
	}

	function variationInputCell(id, key, value, labelText) {
		var td = node("td");
		var input = node("input", "jluxe-pm-control jluxe-pm-small-control");
		input.type = "number";
		input.min = "0";
		input.step = "any";
		input.inputMode = "decimal";
		input.value = value == null ? "" : String(value);
		input.placeholder = labelText;
		input.dataset.vField = key;
		input.dataset.variationId = String(id);
		input.setAttribute("aria-label", labelText + "، تنوع " + faNumber(id));
		td.appendChild(input);
		return td;
	}

	function loadVariationPage(page) {
		if (!state.currentProduct || state.saving) return;
		var panel = $("[data-panel='variations']", editorBody);
		if (!panel) return;
		panel.classList.add("is-loading");
		var localAttributes = (state.currentProduct.variations && state.currentProduct.variations.attributes) || [];
		request("jluxe_product_manager_get_variations", { product_id: state.currentProduct.id, page: page }).then(function (data) {
			data.attributes = localAttributes;
			state.currentProduct.variations = data;
			state.variationPage = data.page;
			renderVariations(panel, data);
		}).catch(function (error) {
			panel.classList.remove("is-loading");
			showNotice(error.message, "error");
		});
	}

	function handleClick(event) {
		var target = event.target;
		var button = target.closest("button");
		if (!event.target.closest(".jluxe-pm-filter-term")) {
			$$(".jluxe-pm-filter-results").forEach(function (item) { item.hidden = true; });
		}
		if (!button) return;
		if (button.dataset.pmEdit) { openEditor(Number(button.dataset.pmEdit)); return; }
		if (button.dataset.pmFilterSelect) {
			var filter = button.closest(".jluxe-pm-filter-term");
			selectFilterTerm(filter, { id: button.dataset.pmFilterSelect, name: button.dataset.name, slug: button.dataset.slug });
			return;
		}
		if (button.hasAttribute("data-pm-clear-filter")) {
			clearFilterTerm(button.closest(".jluxe-pm-filter-term"), true);
			return;
		}
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
		if (button.dataset.pmVariationPage) { loadVariationPage(Number(button.dataset.pmVariationPage)); return; }
		if (button.dataset.pmSaveOrganization) { saveOrganization(); return; }
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
		if (target.dataset.pmInlineField && target.tagName === "SELECT") {
			scheduleProductSave(target.dataset.productId);
			return;
		}
		if ((target.dataset.vField || target.dataset.vAttr) && target.tagName === "SELECT") {
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
			if (target.dataset.pmInlineField === "regularPrice") product.regularPrice = target.value;
			if (target.dataset.pmInlineField === "stockQuantity") {
				product.stockQuantity = target.value;
				if (target.value !== "") product.manageStock = true;
			}
			scheduleProductSave(product.id);
			return;
		}
		if (target.matches("[data-v-field], [data-v-attr]")) {
			var variationRow = target.closest(".jluxe-pm-variation-row");
			if (variationRow) scheduleVariationSave(variationRow);
		}
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
		if (editor.hidden) return;
		if (event.key === "Escape") closeEditor(false);
	});
	root.addEventListener("click", function (event) {
		if (event.target.matches(".jluxe-pm-editor-backdrop")) closeEditor(false);
	});

	loadProducts(true);
})();
