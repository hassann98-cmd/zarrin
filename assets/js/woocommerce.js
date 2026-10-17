/**
 * دکمه‌های +/- تعداد در صفحهٔ محصول و سبد خرید. در سبدِ صفحهٔ کامل، رویدادِ
 * change به مسیرِ AJAX سبکِ jluxe_cart می‌رسد؛ اگر endpoint در دسترس نبود،
 * همان submit استانداردِ فرم WooCommerce باقی می‌ماند. فرم‌های صفحهٔ محصول
 * فقط مقدار را تغییر می‌دهند و جریان رسمی خودشان را حفظ می‌کنند.
 */
(function () {
	document.addEventListener("click", function (event) {
		var button = event.target.closest("[data-jluxe-qty-step]");
		if (!button) {
			return;
		}

		var wrapper = button.closest(".quantity");
		var input = wrapper && wrapper.querySelector("input.qty");
		var cartRow = button.closest("[data-jluxe-cart-item-key]");
		if (!input || (cartRow && cartRow.hasAttribute("data-jluxe-cart-removing"))) {
			return;
		}

		var step = parseFloat(input.step) || 1;
		var min = input.min !== "" ? parseFloat(input.min) : 0;
		var max = input.max !== "" ? parseFloat(input.max) : Infinity;
		var current = parseFloat(input.value) || 0;
		var direction = button.getAttribute("data-jluxe-qty-step") === "increase" ? 1 : -1;
		var next = Math.min(max, Math.max(min, current + direction * step));

		if (next === current) {
			return;
		}

		input.value = next;
		input.dispatchEvent(new Event("change", { bubbles: true }));

		var form = input.closest("form.woocommerce-cart-form");
		var updateCartButton = form && form.querySelector('[name="update_cart"]');
		var cartSettings = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		var hasFastCartEndpoint = !!(
			form && cartRow && cartSettings && cartSettings.ajaxUrl &&
			typeof window.jluxeCartPost === "function"
		);
		if (updateCartButton && !hasFastCartEndpoint) {
			updateCartButton.disabled = false;
			updateCartButton.click();
		}
	});
})();

/** Retry a cart request only when WordPress explicitly rejects its nonce before mutation. */
(function () {
	var nonceRefresh = null;

	function refreshCartNonce(cartSettings) {
		if (nonceRefresh) { return nonceRefresh; }
		var themeSettings = window.JLuxeThemeSettings || {};
		var sessionUrl = (themeSettings.rest && themeSettings.rest.sessionUrl) || (cartSettings && cartSettings.sessionUrl) || (cartSettings && cartSettings.ajaxUrl);
		if (!sessionUrl) { return Promise.resolve(""); }

		nonceRefresh = fetch(sessionUrl, {
			method: "POST",
			credentials: "same-origin",
			cache: "no-store",
			body: new URLSearchParams({ action: "jluxe_session" }),
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			return payload && payload.success && payload.data && payload.data.cartNonce
				? String(payload.data.cartNonce)
				: "";
		}).catch(function () {
			return "";
		}).then(function (nonce) {
			nonceRefresh = null;
			return nonce;
		}, function () {
			nonceRefresh = null;
			return "";
		});
		return nonceRefresh;
	}

	window.jluxeCartPost = function (url, body, cartSettings) {
		function send() {
			return fetch(url, {
				method: "POST",
				body: body,
				credentials: "same-origin",
				cache: "no-store",
			}).then(function (response) {
				return response.json().then(function (payload) {
					return { status: response.status, payload: payload };
				});
			});
		}

		return send().then(function (result) {
			if (!result || result.status !== 403 || !result.payload || !result.payload.data || result.payload.data.code !== "jluxe_cart_invalid_nonce") {
				return result && result.payload;
			}
			return refreshCartNonce(cartSettings).then(function (nonce) {
				if (!nonce || !body || typeof body.set !== "function") {
					return result.payload;
				}
				if (cartSettings) { cartSettings.nonce = nonce; }
				body.set("nonce", nonce);
				return send().then(function (retry) { return retry && retry.payload; });
			});
		});
	};
})();

/**
 * Fast cart-page mutations. WooCommerce still validates and calculates the cart
 * on the server; the response carries only the changed cart fragments instead
 * of forcing a navigation or parsing a full checkout page.
 */
(function () {
	var initialForm = document.querySelector("form.woocommerce-cart-form");
	var themeSettings = window.JLuxeThemeSettings || {};
	var cartSettings = themeSettings.cart || null;
	if (!initialForm || !cartSettings || !cartSettings.ajaxUrl || typeof window.jluxeCartPost !== "function") {
		return;
	}

	var quantityDebounceMs = 160;
	var quantityTimers = new WeakMap();
	var lineStates = new Map();
	var requestQueue = Promise.resolve();
	var intentRevision = 0;

	function lineKey(row) {
		return row && row.getAttribute("data-jluxe-cart-item-key");
	}

	function isCartQuantityInput(input) {
		return !!(
			input && input.matches && input.matches("form.woocommerce-cart-form .cart_item input.qty") &&
			input.closest("[data-jluxe-cart-item-key]")
		);
	}

	function lineState(row, key) {
		var state = lineStates.get(key);
		if (!state) {
			state = { row: row, timer: null, pending: 0, removing: false };
			lineStates.set(key, state);
		} else {
			state.row = row;
		}
		return state;
	}

	function markBusy(state) {
		if (state.row && state.row.isConnected) {
			state.row.setAttribute("aria-busy", "true");
			state.row.setAttribute("data-jluxe-cart-pending", "");
		}
	}

	function clearBusyIfIdle(state) {
		if (state.timer || state.pending || state.removing || !state.row || !state.row.isConnected) {
			return;
		}
		state.row.removeAttribute("aria-busy");
		state.row.removeAttribute("data-jluxe-cart-pending");
	}

	function toPersianDigits(value) {
		var digits = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };
		return String(value).replace(/[0-9]/g, function (digit) { return digits[digit]; });
	}

	function setInputQuantity(input, quantity) {
		if (!input) { return; }
		var value = String(quantity);
		input.value = value;
		input.setAttribute("data-jluxe-cart-confirmed-qty", value);
		var wrapper = input.closest(".quantity");
		var overlay = wrapper && wrapper.querySelector("[data-jluxe-qty-fa]");
		var readOnlyValue = wrapper && wrapper.querySelector(".qty[aria-hidden='true']");
		if (overlay) { overlay.textContent = toPersianDigits(value); }
		if (readOnlyValue && readOnlyValue !== input) { readOnlyValue.textContent = toPersianDigits(value); }
	}

	function itemsByKey(snapshot) {
		var items = new Map();
		snapshot.items.forEach(function (item) { items.set(String(item.key), item); });
		return items;
	}

	function seedConfirmedQuantities(root) {
		(root || document).querySelectorAll("[data-jluxe-cart-item-key] input.qty").forEach(function (input) {
			if (!input.hasAttribute("data-jluxe-cart-confirmed-qty")) {
				input.setAttribute("data-jluxe-cart-confirmed-qty", input.value);
			}
		});
	}

	function rememberConfirmedSnapshot(snapshot) {
		if (!snapshot || !Array.isArray(snapshot.items)) { return; }
		var knownItems = itemsByKey(snapshot);
		document.querySelectorAll("[data-jluxe-cart-item-key]").forEach(function (row) {
			var item = knownItems.get(String(lineKey(row)));
			var input = row.querySelector("input.qty");
			if (item && input) {
				input.setAttribute("data-jluxe-cart-confirmed-qty", String(item.qty));
			}
		});
	}

	function clearCartMessage() {
		var message = document.querySelector("[data-jluxe-cart-ajax-message]");
		if (message) { message.remove(); }
	}

	function showCartMessage(text) {
		var message = document.querySelector("[data-jluxe-cart-ajax-message]");
		if (!message) {
			message = document.createElement("div");
			message.className = "woocommerce-error";
			message.setAttribute("role", "alert");
			message.setAttribute("data-jluxe-cart-ajax-message", "");
			var form = document.querySelector("form.woocommerce-cart-form");
			var host = form && form.parentNode;
			if (host && form) { host.insertBefore(message, form); }
		}
		if (message) { message.textContent = text; }
	}

	function restoreConfirmedQuantity(row) {
		var input = row && row.querySelector("input.qty");
		if (input && input.hasAttribute("data-jluxe-cart-confirmed-qty")) {
			setInputQuantity(input, input.getAttribute("data-jluxe-cart-confirmed-qty"));
		}
	}

	function replaceTotals(html) {
		var current = document.querySelector(".cart_totals");
		if (!current || typeof html !== "string" || !html) { return false; }
		var holder = document.createElement("div");
		holder.innerHTML = html;
		var replacement = holder.querySelector(".cart_totals");
		if (!replacement) { return false; }
		current.replaceWith(replacement);
		return true;
	}

	function replaceCartContent(html) {
		if (typeof html !== "string" || !html) { return false; }
		var holder = document.createElement("div");
		holder.innerHTML = html;
		var replacement = holder.querySelector("[data-jluxe-cart-page-content]");
		var current = document.querySelector("[data-jluxe-cart-page-content]");
		if (!replacement || !current) { return false; }
		current.replaceWith(replacement);
		return true;
	}

	function publishCartUpdate(snapshot, emptied) {
		var publicSnapshot = Object.assign({}, snapshot);
		delete publicSnapshot.cartPage;
		window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: publicSnapshot }));
		if (window.jQuery) {
			if (emptied) {
				window.jQuery(document.body).trigger("wc_cart_emptied");
			} else {
				window.jQuery(document.body).trigger("updated_cart_totals");
			}
			// The theme disables Woo's background cart-fragment poll by default.
			// If a plugin explicitly re-enables it, preserve its refresh lifecycle;
			// otherwise updated_wc_div would launch a redundant second AJAX call.
			if (window.wc_cart_fragments_params) {
				window.jQuery(document.body).trigger("updated_wc_div");
			}
		}
	}

	function applyCartSnapshot(snapshot) {
		if (!snapshot || !Array.isArray(snapshot.items) || !snapshot.cartPage) {
			window.location.reload();
			return false;
		}
		rememberConfirmedSnapshot(snapshot);
		clearCartMessage();

		var emptied = snapshot.cartPage.isEmpty === true || snapshot.items.length === 0;
		if (emptied) {
			if (!replaceCartContent(snapshot.cartPage.emptyHtml)) {
				window.location.reload();
				return false;
			}
		} else {
			var knownItems = itemsByKey(snapshot);
			document.querySelectorAll("[data-jluxe-cart-item-key]").forEach(function (row) {
				var item = knownItems.get(String(lineKey(row)));
				if (!item) {
					row.remove();
					return;
				}
				var input = row.querySelector("input.qty");
				if (input) { setInputQuantity(input, item.qty); }
				if (typeof item.lineTotalHtml === "string") {
					row.querySelectorAll("[data-jluxe-cart-item-total]").forEach(function (total) {
						total.innerHTML = item.lineTotalHtml;
					});
				}
			});
			if (!replaceTotals(snapshot.cartPage.totalsHtml)) {
				window.location.reload();
				return false;
			}
		}

		document.querySelectorAll("[data-jluxe-cart-item-key]").forEach(function (row) {
			row.removeAttribute("aria-busy");
			row.removeAttribute("data-jluxe-cart-pending");
		});
		seedConfirmedQuantities(document);
		publishCartUpdate(snapshot, emptied);
		return true;
	}

	function requestBody(operation, key, quantity) {
		var body = new URLSearchParams({
			action: "jluxe_cart",
			nonce: cartSettings.nonce || "",
			op: operation,
			key: String(key),
			cart_page: "1",
		});
		if (quantity !== null && quantity !== undefined) { body.set("qty", String(quantity)); }
		return body;
	}

	function finishMutation(state) {
		state.pending = Math.max(0, state.pending - 1);
		clearBusyIfIdle(state);
		if (state.removing && state.row && !state.row.isConnected && !state.pending && !state.timer) {
			lineStates.delete(lineKey(state.row));
		}
	}

	function enqueueMutation(operation, key, quantity, row, state, revision) {
		state.pending += 1;
		markBusy(state);

		function execute() {
			var body = requestBody(operation, key, quantity);
			var request;
			try {
				request = window.jluxeCartPost(cartSettings.ajaxUrl, body, cartSettings);
			} catch (error) {
				request = Promise.reject(error);
			}
			return Promise.resolve(request).then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					if (revision === intentRevision) {
						if (payload && payload.success === false) {
							if ("update_qty" === operation) { restoreConfirmedQuantity(row); }
						if ("remove" === operation) {
							state.removing = false;
							row.removeAttribute("data-jluxe-cart-removing");
							restoreConfirmedQuantity(row);
						}
						showCartMessage(payload && payload.data && payload.data.message ? payload.data.message : "تغییر سبد خرید انجام نشد؛ دوباره تلاش کنید.");
						} else {
						showCartMessage("وضعیت تغییر سبد مشخص نیست؛ برای اطمینان صفحه را تازه کنید.");
						if ("remove" === operation) {
							state.removing = false;
						row.removeAttribute("data-jluxe-cart-removing");
						}
					}
					clearBusyIfIdle(state);
				}
				return;
				}
				rememberConfirmedSnapshot(payload.data);
				if (revision === intentRevision) { applyCartSnapshot(payload.data); }
			}, function () {
				if (revision === intentRevision) {
					showCartMessage("وضعیت تغییر سبد مشخص نیست؛ برای اطمینان صفحه را تازه کنید.");
					if ("remove" === operation) {
						state.removing = false;
						row.removeAttribute("data-jluxe-cart-removing");
					}
					clearBusyIfIdle(state);
				}
			}).then(function (result) {
				finishMutation(state);
				return result;
			}, function (error) {
				finishMutation(state);
				throw error;
			});
		}

		var task = requestQueue.then(execute, execute);
		requestQueue = task.then(function () {}, function () {});
		return task;
	}

	function clearQuantityTimer(input, state) {
		var timer = quantityTimers.get(input);
		if (timer) {
			window.clearTimeout(timer);
			quantityTimers.delete(input);
			if (state.timer === timer) { state.timer = null; }
		}
	}

	function scheduleQuantity(input, immediate) {
		var row = input && input.closest("[data-jluxe-cart-item-key]");
		var key = lineKey(row);
		if (!row || !key) { return false; }
		var state = lineState(row, key);
		if (state.removing) { return true; }
		clearQuantityTimer(input, state);
		var revision = ++intentRevision;
		state.timer = window.setTimeout(function () {
			state.timer = null;
			quantityTimers.delete(input);
			enqueueMutation("update_qty", key, input.value, row, state, revision);
		}, immediate ? 0 : quantityDebounceMs);
		quantityTimers.set(input, state.timer);
		markBusy(state);
		return true;
	}

	seedConfirmedQuantities(document);
	if (window.jQuery) {
		window.jQuery(document.body).on("updated_wc_div", function () { seedConfirmedQuantities(document); });
	}

	document.addEventListener("change", function (event) {
		if (isCartQuantityInput(event.target)) { scheduleQuantity(event.target, false); }
	});

	["keydown", "keypress"].forEach(function (eventName) {
		document.addEventListener(eventName, function (event) {
			if ("Enter" !== event.key && 13 !== event.keyCode) { return; }
			if (!isCartQuantityInput(event.target)) { return; }
			event.preventDefault();
			event.stopImmediatePropagation();
			scheduleQuantity(event.target, true);
		}, true);
	});

	document.addEventListener("click", function (event) {
		var link = event.target.closest && event.target.closest("[data-jluxe-cart-remove]");
		if (!link) { return; }
		var row = link.closest("[data-jluxe-cart-item-key]");
		var key = lineKey(row);
		if (!row || !key) { return; }
		event.preventDefault();
		var state = lineState(row, key);
		if (state.removing) { return; }
		state.removing = true;
		row.setAttribute("data-jluxe-cart-removing", "");
		markBusy(state);
		row.querySelectorAll("input.qty").forEach(function (input) { clearQuantityTimer(input, state); });
		var revision = ++intentRevision;
		enqueueMutation("remove", key, null, row, state, revision);
	});
})();

/**
 * گالری تصویر صفحه‌ی محصول (woocommerce/single-product/product-image.php):
 * جابه‌جایی بین تصاویر با opacity + به‌روزرسانی شمارنده‌ی فعال. ناوبری هم با
 * دو دکمه‌ی قبلی/بعدی و هم با کلیکِ مستقیم روی هر تامبنیلِ ردیفِ زیرِ عکس
 * (data-jluxe-gallery-thumb) ممکنه — کلیکِ تامبنیل مستقیم ایندکسِ همون
 * تامبنیل رو ست می‌کنه (نه relative مثلِ prev/next).
 */
(function () {
	document.addEventListener("click", function (event) {
		var gallery = event.target.closest("[data-jluxe-gallery]");
		if (!gallery) {
			return;
		}

		var images = gallery.querySelectorAll("[data-jluxe-gallery-image]");
		if (images.length < 2) {
			return;
		}

		/*
		 * باگِ واقعیِ پیدا‌شده: قبلاً «تصویرِ فعلی» با چک‌کردنِ img.style.opacity
		 * (فقط استایلِ inline) تشخیص داده می‌شد؛ ولی رنگِ اولیه‌ی هر تصویر
		 * (opacity-100/opacity-0) با کلاسِ Tailwind تویِ خودِ PHP ست می‌شه،
		 * نه inline style — پس تا قبل از اولین کلیک، style.opacity همه‌ی
		 * عکس‌ها رشته‌ی خالیه، که "" !== "0" همیشه true می‌ده و حلقه با آخرین
		 * عکس (نه عکسِ واقعاً نمایان) تموم می‌شد. قبلاً چون کلیک روی تامبنیل
		 * ایندکس رو مستقیم از data-attribute می‌گرفت (نه از رویِ این تشخیص)
		 * دیده نمی‌شد؛ با حذفِ تامبنیل‌ها، اولین کلیکِ روی فلش همیشه به‌جای
		 * تصویرِ درست، تصویرِ آخر رو مبنا می‌گرفت. حالا ایندکسِ فعلی مستقیم
		 * رو خودِ گالری نگه‌داری می‌شه (data-jluxe-gallery-current)، نه از
		 * حدسِ رویِ استایل.
		 */
		var current = parseInt(gallery.getAttribute("data-jluxe-gallery-current"), 10);
		if (isNaN(current)) {
			current = 0;
		}

		var next = current;
		var prevButton = event.target.closest("[data-jluxe-gallery-prev]");
		var nextButton = event.target.closest("[data-jluxe-gallery-next]");
		var thumbButton = event.target.closest("[data-jluxe-gallery-thumb]");

		if (prevButton) {
			next = (current - 1 + images.length) % images.length;
		} else if (nextButton) {
			next = (current + 1) % images.length;
		} else if (thumbButton) {
			next = parseInt(thumbButton.getAttribute("data-jluxe-gallery-thumb"), 10);
			if (isNaN(next) || next === current) {
				return;
			}
		} else {
			return;
		}

		gallery.setAttribute("data-jluxe-gallery-current", String(next));

		images.forEach(function (img, i) {
			img.style.opacity = i === next ? "1" : "0";
		});

		var thumbs = gallery.querySelectorAll("[data-jluxe-gallery-thumb]");
		thumbs.forEach(function (thumb, i) {
			thumb.classList.toggle("border-primary", i === next);
			thumb.classList.toggle("border-border", i !== next);
		});

		var counter = gallery.querySelector("[data-jluxe-gallery-counter]");
		if (counter) {
			var faDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
			var toFa = function (n) {
				return String(n)
					.split("")
					.map(function (d) {
						return faDigits[d] || d;
					})
					.join("");
			};
			counter.textContent = toFa(next + 1) + " / " + toFa(images.length);
		}
	});
})();



/**
 * جعبه‌ی قیمتِ صفحه‌ی محصولِ متغیر (woocommerce/single-product/price.php):
 * چون قیمت/تخفیف/موجودیِ واقعی مالِ variation انتخاب‌شده‌ست، به رویدادهای
 * واقعیِ found_variation/reset_data که خودِ assets/js/frontend/add-to-cart-variation.js
 * ووکامرس روی .variations_form دیسپچ می‌کنه گوش می‌دیم — نه polling.
 * قیمتِ فعلی/خط‌خورده مستقیم از HTML واقعیِ ووکامرس (variation.price_html که
 * خودش با wc_price() و تنظیماتِ واقعیِ سایت — تومان/جداکننده/رقم فارسی —
 * ساخته شده) گرفته می‌شه؛ این‌جوری نیازی به بازسازیِ فرمتِ عدد در JS نیست.
 *
 * این تابع برای هر .variations_form که واقعاً وجود داره صدا زده می‌شه —
 * هم برای فرمِ ایستای صفحه‌ی محصول (رندرشده هنگام بارگذاری صفحه) هم برای
 * فرمِ مودالِ افزودنِ سریع (که با AJAX بعد از بارگذاریِ صفحه به DOM اضافه
 * می‌شه؛ قبلاً این بایندینگ فقط یک‌بار روی اولین .variations_form موجودِ
 * لحظه‌ی بارگذاریِ اسکریپت انجام می‌شد، پس فرمِ مودال که دیرتر تزریق می‌شه
 * اصلاً این listener رو نمی‌گرفت و قیمت بعد از انتخابِ سایز/رنگ در مودال
 * برای همیشه مخفی می‌موند — باگِ واقعیِ گزارش‌شده). محلِ صدا زدنش رو در
 * پایینِ همین فایل (برای فرمِ ایستا) و در بخشِ مودال (بعد از تزریقِ HTML
 * و wc_variation_form()) ببین.
 */
function jluxeBindPriceBox(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var $form = $(form);
	var $priceBox = $form.find("[data-jluxe-price-box]").first();
	if (!$form.length || !$priceBox.length || $form.data("jluxePriceBoxBound")) {
		return;
	}
	$form.data("jluxePriceBoxBound", true);

	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };
	function jluxeFaDigits(str) {
		return String(str).replace(/[0-9]/g, function (d) {
			return faDigitsMap[d];
		});
	}
	var stockIcon =
		'<svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>';

	var $discountRow = $priceBox.find("[data-jluxe-price-discount-row]");
	var $badge = $priceBox.find("[data-jluxe-price-badge]");
	var $strike = $priceBox.find("[data-jluxe-price-strike]");
	var $current = $priceBox.find("[data-jluxe-price-current]");
	var $saving = $priceBox.find("[data-jluxe-price-saving]");
	var $stock = $priceBox.find("[data-jluxe-price-stock]");
	var defaultCurrentHtml = $current.html();
	var defaultStockHtml = $stock.html();
	var priceBoxEl = $priceBox[0];

	/*
	 * وقتی قیمت/بج‌تخفیف/موجودی یهو ظاهر می‌شن، ارتفاعِ priceBoxEl یهو
	 * زیاد می‌شه و کل مودال (باگِ واقعیِ گزارش‌شده: «کادر دراز می‌شه») به‌جای
	 * جابه‌جاییِ نرم، با یک پرش دیده می‌شه. با تکنیکِ FLIP (ارتفاعِ قبل رو
	 * اندازه می‌گیریم، محتوا رو آپدیت می‌کنیم، ارتفاعِ بعد رو اندازه می‌گیریم،
	 * از قبل به بعد با transition انیمیت می‌کنیم، بعدش height رو auto
	 * برمی‌گردونیم که تغییراتِ بعدی/ریسپانسیو گیر نکنن) این پرش حذف می‌شه.
	 */
	function jluxeAnimateBoxHeight(mutate) {
		if (!priceBoxEl) {
			mutate();
			return;
		}
		// ووکامرس گاهی reset_data و found_variation رو پشت‌سرِ هم/سینکرون
		// (بدون فرصتِ paint بینشون) dispatch می‌کنه؛ اگه انیمیشنِ قبلی هنوز
		// در جریانه، callback معلقش رو لغو می‌کنیم تا فقط مقصدِ نهایی (آخرین
		// mutate) اعمال بشه، نه دو انیمیشنِ رقیب که با هم تداخل کنن.
		if (priceBoxEl._jluxeAnimCancel) {
			priceBoxEl._jluxeAnimCancel();
		}

		var startHeight = priceBoxEl.getBoundingClientRect().height;
		mutate();
		var endHeight = priceBoxEl.scrollHeight;
		if (startHeight === endHeight) {
			return;
		}

		priceBoxEl.style.height = startHeight + "px";
		priceBoxEl.style.overflow = "hidden";
		// eslint-disable-next-line no-unused-expressions
		priceBoxEl.offsetHeight;

		var cancelled = false;
		priceBoxEl._jluxeAnimCancel = function () {
			cancelled = true;
		};

		function grow() {
			if (cancelled) {
				return;
			}
			priceBoxEl.style.transition = "height 260ms ease";
			priceBoxEl.style.height = endHeight + "px";
		}
		function finish() {
			if (cancelled) {
				return;
			}
			cancelled = true;
			clearTimeout(growTimeoutId);
			clearTimeout(settleTimeoutId);
			priceBoxEl.removeEventListener("transitionend", onTransitionEnd);
			priceBoxEl.style.height = "";
			priceBoxEl.style.overflow = "";
			priceBoxEl.style.transition = "";
			priceBoxEl._jluxeAnimCancel = null;
		}
		function onTransitionEnd(event) {
			if (event.propertyName === "height") {
				finish();
			}
		}

		// هم rAF (مسیرِ نرمِ معمولی وقتی تب دیده می‌شه) هم یک fallback با
		// setTimeout (چون rAF توی تبِ پس‌زمینه/غیرِ‌قابل‌مشاهده اصلاً اجرا
		// نمی‌شه و انیمیشن برای همیشه با overflow:hidden گیر می‌کرد — تستِ
		// همین محیطِ خودکار دقیقاً همین حالت رو نشون داد، document.hidden
		// true بود و rAF هیچ‌وقت صدا زده نشد).
		requestAnimationFrame(grow);
		var growTimeoutId = setTimeout(grow, 50);
		var settleTimeoutId = setTimeout(finish, 450);
		priceBoxEl.addEventListener("transitionend", onTransitionEnd);
	}

	$form.on("found_variation", function (event, variation) {
		jluxeAnimateBoxHeight(function () {
			var $parsed = $("<div></div>").html(variation.price_html || "");
			var $del = $parsed.find("del").first();
			var $ins = $parsed.find("ins").first();

			if ($del.length && $ins.length) {
				var pct = 0;
				if (variation.display_regular_price > 0) {
					pct = Math.round(100 - (variation.display_price / variation.display_regular_price) * 100);
				}
				$badge.text(jluxeFaDigits(String(pct)));
				$strike.html($del.html());
				$current.html($ins.html());
				$discountRow.removeClass("hidden").addClass("flex");
			} else {
				$current.html(variation.price_html || "");
				$discountRow.addClass("hidden").removeClass("flex");
			}
			// طبقِ درخواستِ کاربر: قیمت تا وقتی سواچی واقعاً انتخاب/found_variation
			// نشده مخفیه (price.php: hidden پیش‌فرض روی data-jluxe-price-current) —
			// همین‌جا که یک variation واقعی پیدا شده، نمایش داده می‌شه.
			$current.removeClass("hidden");
			// خطِ سود سبز طبقِ درخواستِ صریحِ کاربر («روی محصولِ متغیر هم نشون
			// بده») حالا از jluxe_saving_html استفاده می‌کنه — یک رشته‌ی
			// آماده‌ی سرور (inc/woocommerce.php: jluxe_variation_saving_html)
			// که با wc_price() واقعیِ خودِ سایت ساخته شده، نه بازسازیِ عدد در JS.
			if ($del.length && $ins.length && variation.jluxe_saving_html) {
				$saving.html(variation.jluxe_saving_html).removeClass("hidden");
			} else {
				$saving.addClass("hidden").text("");
			}

			var stockText = $("<div></div>").html(variation.availability_html || "").text().trim();
			if (stockText) {
				$stock.html('<p class="mt-1.5 flex items-center gap-1 text-[11px] font-medium text-text-muted">' + stockIcon + jluxeFaDigits(stockText) + "</p>");
			} else {
				$stock.empty();
			}
		});
	});

	$form.on("reset_data", function () {
		jluxeAnimateBoxHeight(function () {
			$current.html(defaultCurrentHtml).addClass("hidden");
			$discountRow.addClass("hidden").removeClass("flex");
			$saving.addClass("hidden").text("");
			$stock.html(defaultStockHtml);
		});
	});
}
jluxeBindPriceBox(document.querySelector(".variations_form[data-product_variations]"));

/**
 * The classic product layout owns a custom gallery rather than WooCommerce's
 * .woocommerce-product-gallery. Mirror the selected variation image into its
 * main image, first thumbnail, and full-screen slide; the default layout stays
 * on WooCommerce's native variation-image handler.
 */
function jluxeBindClassicVariationGallery(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var root = form.closest(".jluxe-cp3");
	var gallery = root && root.querySelector("[data-cp3-gallery]");
	if (!gallery) {
		return;
	}
	var $form = $(form);
	if ($form.data("jluxeClassicGalleryBound")) {
		return;
	}
	var mainImage = gallery.querySelector("[data-cp3-zoom-img]");
	var modalImage = root.querySelector("[data-jluxe-gallery-modal-image]");
	if (!mainImage || !modalImage) {
		return;
	}
	$form.data("jluxeClassicGalleryBound", true);

	var firstThumb = gallery.querySelector("[data-cp3-thumb]");
	var firstThumbImage = firstThumb && firstThumb.querySelector("img");
	var imageAttributes = ["src", "srcset", "sizes", "width", "height", "alt"];
	var thumbAttributes = ["data-full"];
	var thumbImageAttributes = ["src", "srcset", "sizes", "alt"];
	var modalAttributes = ["data-src", "data-srcset", "data-sizes", "src", "srcset", "sizes", "alt"];

	function rememberAttributes(element, names) {
		var saved = {};
		if (!element) {
			return saved;
		}
		names.forEach(function (name) {
			saved[name] = element.hasAttribute(name) ? element.getAttribute(name) : null;
		});
		return saved;
	}
	function restoreAttributes(element, saved) {
		if (!element) {
			return;
		}
		Object.keys(saved).forEach(function (name) {
			if (saved[name] === null) {
				element.removeAttribute(name);
			} else {
				element.setAttribute(name, saved[name]);
			}
		});
	}
	function setOrRemove(element, name, value) {
		if (!element) {
			return;
		}
		if (value === undefined || value === null || value === "") {
			element.removeAttribute(name);
		} else {
			element.setAttribute(name, String(value));
		}
	}

	var original = {
		main: rememberAttributes(mainImage, imageAttributes),
		thumb: rememberAttributes(firstThumb, thumbAttributes),
		thumbImage: rememberAttributes(firstThumbImage, thumbImageAttributes),
		modal: rememberAttributes(modalImage, modalAttributes),
		galleryIndex: gallery.getAttribute("data-jluxe-gallery-current") || "0",
	};

	function syncGalleryThumbs(activeIndex) {
		var thumbs = gallery.querySelectorAll("[data-cp3-thumb]");
		if (!gallery.querySelector("[data-reference-gallery-index]")) {
			/* Preserve the existing Classic layout's index-based behavior unchanged. */
			thumbs.forEach(function (thumb, index) {
				thumb.classList.toggle("is-active", index === activeIndex);
			});
			return;
		}
		thumbs.forEach(function (thumb, fallbackIndex) {
			var rawIndex = thumb.getAttribute("data-reference-gallery-index") || thumb.getAttribute("data-jluxe-gallery-open");
			var thumbIndex = parseInt(rawIndex, 10);
			if (isNaN(thumbIndex)) { thumbIndex = fallbackIndex; }
			var selected = thumbIndex === activeIndex;
			thumb.classList.toggle("is-active", selected);
			thumb.setAttribute("aria-pressed", selected ? "true" : "false");
		});
	}
	function selectFirstGalleryImage() {
		gallery.setAttribute("data-jluxe-gallery-current", "0");
		syncGalleryThumbs(0);
	}
	function updateMainImageLoadedState(previousSource) {
		if (previousSource !== mainImage.getAttribute("src")) {
			mainImage.classList.remove("is-loaded");
		}
		if (mainImage.complete && mainImage.naturalWidth) {
			mainImage.classList.add("is-loaded");
		}
	}
	function restoreGalleryImage() {
		var previousSource = mainImage.getAttribute("src");
		restoreAttributes(mainImage, original.main);
		restoreAttributes(firstThumb, original.thumb);
		restoreAttributes(firstThumbImage, original.thumbImage);
		restoreAttributes(modalImage, original.modal);
		updateMainImageLoadedState(previousSource);
		gallery.setAttribute("data-jluxe-gallery-current", original.galleryIndex);
		syncGalleryThumbs(Number(original.galleryIndex));
	}
	function showVariationImage(variation) {
		var image = variation && variation.image;
		if (!image || !image.src) {
			restoreGalleryImage();
			return;
		}

		var fullSrc = image.full_src || image.src;
		var previousSource = mainImage.getAttribute("src");
		setOrRemove(mainImage, "src", image.src);
		setOrRemove(mainImage, "srcset", image.srcset);
		setOrRemove(mainImage, "sizes", image.sizes);
		setOrRemove(mainImage, "width", image.src_w);
		setOrRemove(mainImage, "height", image.src_h);
		setOrRemove(mainImage, "alt", image.alt || original.main.alt || "");
		updateMainImageLoadedState(previousSource);

		if (firstThumb) {
			setOrRemove(firstThumb, "data-full", fullSrc);
		}
		if (firstThumbImage) {
			setOrRemove(firstThumbImage, "src", image.gallery_thumbnail_src || image.thumb_src || image.src);
			// The parent's srcset can otherwise win over the selected variation's thumbnail.
			firstThumbImage.removeAttribute("srcset");
			firstThumbImage.removeAttribute("sizes");
		}

		setOrRemove(modalImage, "data-src", fullSrc);
		setOrRemove(modalImage, "data-srcset", image.srcset);
		setOrRemove(modalImage, "data-sizes", image.sizes);
		setOrRemove(modalImage, "alt", image.alt || original.modal.alt || "");
		// showImage() lazy-loads from data-src, but keep an already-open/loaded
		// first slide in sync as well, without eagerly loading an unopened modal.
		if (modalImage.hasAttribute("src")) {
			setOrRemove(modalImage, "src", fullSrc);
			setOrRemove(modalImage, "srcset", image.srcset);
			setOrRemove(modalImage, "sizes", image.sizes);
		}
		selectFirstGalleryImage();
	}

	$form.on("found_variation.jluxeClassicGallery", function (event, variation) {
		showVariationImage(variation);
	});
	$form.on("reset_data.jluxeClassicGallery hide_variation.jluxeClassicGallery", restoreGalleryImage);
}

/**
 * ردیفِ «تعداد»ی صفحه‌ی محصولِ متغیر (woocommerce/single-product/
 * add-to-cart/variation-add-to-cart-button.php: data-jluxe-qty-row) —
 * باگِ واقعیِ گزارش‌شده: برای تنوعی که «فروش تکی» (sold individually) یا
 * موجودیش دقیقاً ۱ عدده، خودِ اسکریپتِ هسته‌ی ووکامرس
 * (add-to-cart-variation.js: onFoundVariation) کلِ .quantity رو با
 * jQuery .hide() مخفی می‌کنه (فرضش: چیزی برای تغییر نیست) — یعنی مشتری
 * اصلاً نمی‌دید داره چند عدد می‌خره. این تابع روی همون رویدادِ
 * found_variation (بعد از هسته‌ی ووکامرس، دقیقاً همون ترتیبی که
 * jluxeBindPriceBox هم ازش استفاده می‌کنه) دوباره ردیف رو نمایان می‌کنه؛
 * اگه تنوع واقعاً ثابت روی ۱ عدده، دکمه‌های +/- رو غیرفعال می‌کنه (نه
 * پنهان) تا مشتری «۱ عدد» رو ببینه ولی نتونه زیادش کنه. اگه تنوع واقعاً
 * ناموجود/غیرقابل‌خرید باشه، کلِ ردیف با یک نشانِ «ناموجود» جایگزین می‌شه.
 */
function jluxeBindQtyAvailability(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var $form = $(form);
	var qtyRow = form.querySelector("[data-jluxe-qty-row]");
	if (!qtyRow || $form.data("jluxeQtyAvailabilityBound")) {
		return;
	}
	$form.data("jluxeQtyAvailabilityBound", true);

	var oosEl = form.querySelector("[data-jluxe-qty-oos]");
	var decBtn = qtyRow.querySelector('[data-jluxe-qty-step="decrease"]');
	var incBtn = qtyRow.querySelector('[data-jluxe-qty-step="increase"]');
	var qtyInput = qtyRow.querySelector("input.qty");
	var qtyInner = qtyRow.querySelector(".quantity");
	var faOverlay = qtyRow.querySelector("[data-jluxe-qty-fa]");

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده روی همین انگشتر پیدا شد): این‌جا
	 * از attribute واقعیِ hidden برای مخفی‌کردنِ qtyRow/oosEl استفاده
	 * می‌کردیم، ولی چون خودِ این عنصرها کلاسِ Tailwindِ flex هم دارن، قانونِ
	 * پیش‌فرضِ [hidden]{display:none} مرورگر با قانونِ .flex هم‌اختصاصیته و
	 * چون .flex در stylesheet دیرتر میاد (لایه‌ی utility بعد از base)، برنده
	 * می‌شه — یعنی با وجودِ hidden=true، عنصر همچنان به‌صورتِ flex دیده
	 * می‌شد (دقیقاً همون الگویی که .jluxe-checkout-step-actions [hidden]
	 * قبلاً برایِ همین مشکل !important گرفته بود). راه‌حل این‌جا: مستقیم
	 * style.display رو ست می‌کنیم، نه فقط attribute رو.
	 */
	function setRowVisible(el, visible) {
		if (!el) {
			return;
		}
		el.hidden = !visible;
		el.style.display = visible ? "" : "none";
	}
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };

	function setFixedQty(value) {
		if (!qtyInput) {
			return;
		}
		qtyInput.value = value;
		qtyInput.dispatchEvent(new Event("change", { bubbles: true }));
		if (faOverlay) {
			faOverlay.textContent = String(value).replace(/[0-9]/g, function (d) {
				return faDigitsMap[d];
			});
		}
	}

	$form.on("found_variation", function (event, variation) {
		var purchasable = variation.is_purchasable && variation.is_in_stock && variation.variation_is_visible;
		if (!purchasable) {
			setRowVisible(qtyRow, false);
			setRowVisible(oosEl, true);
			return;
		}
		setRowVisible(qtyRow, true);
		setRowVisible(oosEl, false);
		// باگِ واقعیِ گزارش‌شده: برای تنوعِ «فروش تکی» خودِ ووکامرس همین‌جا
		// (add-to-cart-variation.js: onFoundVariation) .quantity رو با
		// jQuery .hide() (یعنی style.display=none) مخفی می‌کنه — چون بعد از
		// اجرای اون کد به این‌جا می‌رسیم، این‌جا صریحاً دوباره نمایانش
		// می‌کنیم؛ فقط دکمه‌های +/- (نه خودِ ردیف) غیرفعال می‌شن.
		if (qtyInner) {
			qtyInner.style.display = "";
		}

		var maxQty = parseFloat(variation.max_qty);
		var fixed = "yes" === variation.is_sold_individually || (!isNaN(maxQty) && maxQty <= 1);
		if (decBtn) {
			decBtn.disabled = fixed;
		}
		if (incBtn) {
			incBtn.disabled = fixed;
		}
		if (fixed) {
			setFixedQty(1);
		}
	});

	$form.on("reset_data", function () {
		setRowVisible(qtyRow, true);
		setRowVisible(oosEl, false);
		if (qtyInner) {
			qtyInner.style.display = "";
		}
		if (decBtn) {
			decBtn.disabled = false;
		}
		if (incBtn) {
			incBtn.disabled = false;
		}
	});
}
jluxeBindQtyAvailability(document.querySelector(".variations_form[data-product_variations]"));

/** Keep the CP3 buy-box price in sync with WooCommerce's resolved variation. */
function jluxeBindCp3VariationPrice(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var root = form.closest(".jluxe-cp3");
	var priceBox = root && root.querySelector("[data-cp3-price]");
	if (!priceBox || form.jluxeCp3VariationPriceBound) {
		return;
	}

	form.jluxeCp3VariationPriceBound = true;
	var $form = $(form);
	var emptyPrice = priceBox.getAttribute("data-cp3-empty-text") || "";
	var initialPriceHtml = priceBox.innerHTML;
	var initialPriceIsPlaceholder = priceBox.classList.contains("is-placeholder");
	var attributeSelects = Array.prototype.slice.call(form.querySelectorAll('select[name^="attribute_"]'));
	var initialAttributes = attributeSelects.map(function (select) { return select.value; });

	function stillHasInitialSelection() {
		var currentSelects = form.querySelectorAll('select[name^="attribute_"]');
		if (!initialAttributes.length || currentSelects.length !== initialAttributes.length) {
			return false;
		}
		for (var index = 0; index < currentSelects.length; index++) {
			if (!initialAttributes[index] || currentSelects[index].value !== initialAttributes[index]) {
				return false;
			}
		}
		return true;
	}

	function restoreInitialPriceOrPrompt() {
		/* WooCommerce can emit reset_data while initializing a preselected form.
		 * Keep the server-rendered exact default price if the real selects still
		 * represent that same default; a shopper reset/change still clears it. */
		if (stillHasInitialSelection() && initialPriceHtml.trim() && !initialPriceIsPlaceholder) {
			priceBox.innerHTML = initialPriceHtml;
			priceBox.classList.remove("is-placeholder");
			return;
		}
		priceBox.textContent = emptyPrice;
		priceBox.classList.toggle("is-placeholder", emptyPrice !== "");
	}

	function showVariationPrice(variation) {
		if (!variation || !(parseInt(variation.variation_id, 10) > 0)) {
			restoreInitialPriceOrPrompt();
			return;
		}
		if (variation.is_in_stock === false) {
			priceBox.textContent = "ناموجود";
			priceBox.classList.add("is-placeholder");
			return;
		}
		if (variation.is_purchasable === false) {
			priceBox.textContent = "در حال حاضر قابل خرید نیست";
			priceBox.classList.add("is-placeholder");
			return;
		}
		var priceHtml = typeof variation.price_html === "string" ? variation.price_html : "";
		if (!priceHtml.trim()) {
			priceBox.textContent = "قیمت این تنوع ثبت نشده است";
			priceBox.classList.add("is-placeholder");
			return;
		}
		priceBox.innerHTML = priceHtml;
		priceBox.classList.remove("is-placeholder");
	}

	$form.on("found_variation.jluxeCp3Price", function (event, variation) {
		showVariationPrice(variation);
	}).on("reset_data.jluxeCp3Price hide_variation.jluxeCp3Price", restoreInitialPriceOrPrompt);
}

/** Keep low-stock warnings in both CP3 layouts in sync with the selected variation. */
function jluxeBindCp3StockWarning(form) {
	var $ = window.jQuery;
	if (!$ || !form) {
		return;
	}
	var root = form.closest(".jluxe-cp3");
	var stockSlots = root && root.querySelectorAll("[data-cp3-low-stock]");
	if (!stockSlots || !stockSlots.length || form.jluxeCp3StockWarningBound) {
		return;
	}

	form.jluxeCp3StockWarningBound = true;
	var $form = $(form);
	var initialValue = form.getAttribute("data-cp3-low-stock-initial-quantity");
	var initialQuantity = initialValue !== null && initialValue !== "" && isFinite(Number(initialValue)) ? Number(initialValue) : null;
	var selects = Array.prototype.slice.call(form.querySelectorAll('select[name^="attribute_"]'));
	var initialAttributes = selects.map(function (select) { return select.value; });
	var threshold = parseInt(form.getAttribute("data-cp3-low-stock-threshold") || "10", 10);
	if (isNaN(threshold) || threshold < 1) {
		threshold = 10;
	}
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };

	function formatFaDigits(value) {
		return String(value).replace(/[0-9]/g, function (digit) { return faDigitsMap[digit]; });
	}

	function stillHasInitialSelection() {
		var currentSelects = form.querySelectorAll('select[name^="attribute_"]');
		if (!initialAttributes.length || currentSelects.length !== initialAttributes.length) {
			return false;
		}
		for (var index = 0; index < currentSelects.length; index++) {
			if (!initialAttributes[index] || currentSelects[index].value !== initialAttributes[index]) {
				return false;
			}
		}
		return true;
	}

	function setLowStockQuantity(quantity, available) {
		var numericQuantity = quantity !== null && quantity !== undefined && quantity !== "" ? Number(quantity) : NaN;
		var showWarning = available && isFinite(numericQuantity) && numericQuantity > 0 && numericQuantity <= threshold;
		for (var slotIndex = 0; slotIndex < stockSlots.length; slotIndex++) {
			var slot = stockSlots[slotIndex];
			slot.hidden = !showWarning;
			slot.style.display = showWarning ? "" : "none";
			var quantityNode = slot.querySelector("[data-cp3-stock-quantity]");
			if (!showWarning) {
				if (quantityNode) {
					quantityNode.textContent = "";
				}
				continue;
			}
			if (quantityNode) {
				quantityNode.textContent = formatFaDigits(numericQuantity);
			}
		}
	}

	function updateFromVariation(variation) {
		if (!variation || variation.is_in_stock === false || variation.is_purchasable === false || !Object.prototype.hasOwnProperty.call(variation, "jluxe_stock_quantity")) {
			setLowStockQuantity(null, false);
			return;
		}
		setLowStockQuantity(variation.jluxe_stock_quantity, true);
	}

	$form.on("found_variation.jluxeCp3StockWarning", function (event, variation) {
		updateFromVariation(variation);
	}).on("reset_data.jluxeCp3StockWarning hide_variation.jluxeCp3StockWarning", function () {
		if (stillHasInitialSelection()) {
			setLowStockQuantity(initialQuantity, true);
		} else {
			setLowStockQuantity(null, false);
		}
	});
}

/**
 * R65: unavailable variations are never selectable in any UI.
 *
 * The WooCommerce data-product_variations payload is authoritative: it
 * includes out-of-stock rows with is_in_stock=false. An option is selectable
 * only if an in-stock variation supports its value (or a wildcard) and is
 * compatible with the other currently selected attributes. Apply that rule to
 * the native select, visible swatches, and CP3 pills alike.
 */
function jluxeSyncVariationAvailability(form) {
	var raw = form.getAttribute("data-product_variations") || "[]";
	var variations;
	try { variations = JSON.parse(raw) || []; } catch (e) { variations = []; }
	if (!Array.isArray(variations) || !variations.length) { return; }

	/* R68: کلیدِ ویژگی در data-product_variations و name ی select هر دو
	sanitize_title اند ولی برای نام‌های فارسی percent-encoded ذخیره می‌شوند —
	تطبیق با decodeURIComponent تا طرفین حتی با تفاوتِ encode بخوانند. */
	var decodeKey = function (key) { return (key || "").replace(/^attribute_/, ""); };
	var sameKey = function (a, b) {
		if (a === b) { return true; }
		try { return decodeURIComponent(a) === decodeURIComponent(b); } catch (e) { return false; }
	};
	var selects = form.querySelectorAll("select[name^=\"attribute_\"]");
	var optionValuesBySelect = Object.create(null);
	selects.forEach(function (select) {
		var name = decodeKey(select.getAttribute("name"));
		var current = {};
		selects.forEach(function (other) {
			if (other !== select && other.value) { current[decodeKey(other.getAttribute("name"))] = other.value; }
		});
		var optionValues = {};
		Array.prototype.forEach.call(select.options, function (opt) {
			if (!opt.value) { return; }
			var selectable = variations.some(function (v) {
				if (!v || v.is_in_stock === false) { return false; }
				var attrs = v.attributes || {};
				var suffixes = {};
				Object.keys(attrs).forEach(function (k) { suffixes[decodeKey(k)] = attrs[k]; });
				var own = null;
				Object.keys(suffixes).forEach(function (k) { if (sameKey(k, name)) { own = suffixes[k]; } });
				if (own !== null && own !== "" && own !== opt.value) { return false; }
				for (var otherName in current) {
					if (!Object.prototype.hasOwnProperty.call(current, otherName)) { continue; }
					var otherVal = null;
					Object.keys(suffixes).forEach(function (k) { if (sameKey(k, otherName)) { otherVal = suffixes[k]; } });
					if (otherVal !== null && otherVal !== "" && otherVal !== current[otherName]) { return false; }
				}
				return true;
			});
			optionValues[select.name + "|" + opt.value] = selectable;
			opt.disabled = !selectable;
		});
		optionValuesBySelect[select.name] = optionValues;

		// سواچ‌های همان ویژگی (چیدمان پیش‌فرض/مودال انتخاب سریع).
		var group = select.closest("[data-jluxe-variation-group]");
		if (group) {
			group.querySelectorAll("[data-jluxe-variation-value]").forEach(function (btn) {
				var value = btn.getAttribute("data-jluxe-variation-value");
				/* R71: رگرسیونِ R68 — کلیدِ optionValues با پیشوندِ select.name
				ساخته می‌شود؛ خواندنِ بدونِ پیشوند همیشه undefined برمی‌گرداند
				و «همهٔ» سواچ‌ها (چیدمانِ پیش‌فرض + مودالِ انتخابِ سریع)
				برایِ همیشه disable می‌شدند — همان که کاربر دید. مثلِ قرص‌ها:
				=== true فقط وقتی واقعاً موجود است. */
				var selectable = optionValues[select.name + "|" + value] === true;
				btn.disabled = !selectable;
				btn.classList.toggle("jluxe-swatch-disabled", !selectable);
				if (!selectable) { btn.removeAttribute("data-active"); }
			});
		}
	});

	// قرص‌های چیدمان classic — نگاشتِ دقیق با data-cp3-select روی خودِ select
	// و وضعیت از optionValues (منبعِ موجودی) نه از opt.disabled — تا فیلترِ
	// «بدونِ انتخاب همه فعال» ووکامرس نتواند آن را پاک کند.
	form.querySelectorAll("select[name^=\"attribute_\"]").forEach(function (select) {
		var slug = select.getAttribute("data-cp3-select");
		if (!slug) { return; }
		var root = form.closest(".jluxe-cp3") || document;
		var cssEscape = function (value) { return (window.CSS && CSS.escape) ? CSS.escape(value) : value; };
		var pillGroup = root.querySelector('[data-cp3-pills="' + cssEscape(slug) + '"]');
		if (!pillGroup) { return; }
		var optionValues = optionValuesBySelect[select.name] || Object.create(null);
		pillGroup.querySelectorAll(".cp3-pill").forEach(function (pill) {
			var value = pill.getAttribute("data-value") || "";
			var selectable = optionValues[select.name + "|" + value] === true;
			pill.disabled = !selectable;
			pill.setAttribute("aria-disabled", selectable ? "false" : "true");
			pill.classList.toggle("is-disabled", !selectable);
			pill.title = pill.textContent.trim() + (selectable ? "" : " (ناموجود)");
			if (pill.classList.contains("is-active") && !selectable) { pill.classList.remove("is-active"); }
		});
	});
}

function jluxeSyncAllVariationForms() {
	document.querySelectorAll(".variations_form[data-product_variations]").forEach(function (form) {
		jluxeBindClassicVariationGallery(form);
		jluxeBindCp3VariationPrice(form);
		jluxeBindCp3StockWarning(form);
		if (form.jluxeAvailabilityBound) { return; }
		form.jluxeAvailabilityBound = true;
		form.addEventListener("change", function () {
			window.setTimeout(function () { jluxeSyncVariationAvailability(form); }, 0);
		});
		/* R68: ووکامرس در init «همه فعال» را منتشر می‌کند و اسکریپتِ
		درون‌خطیِ classic طبق آن کلاس‌ها را پاک می‌کند — این قلابِ بعد از
		رویدادِ وو، وضعیتِ موجودیِ درست را دوباره می‌نشاند (+ تاخیرِ اطمینان). */
		if (window.jQuery) {
			window.jQuery(form).on("woocommerce_update_variation_values", function () {
				window.setTimeout(function () { jluxeSyncVariationAvailability(form); }, 0);
			});
		}
		window.setTimeout(function () { jluxeSyncVariationAvailability(form); }, 0);
		window.setTimeout(function () { jluxeSyncVariationAvailability(form); }, 600);
	});
}
jluxeSyncAllVariationForms();
document.addEventListener("DOMContentLoaded", jluxeSyncAllVariationForms);
window.jluxeSyncAllVariationForms = jluxeSyncAllVariationForms;

/**
 * Keep the localized choice beside every variation attribute title. The
 * option's visible text is used intentionally; select values remain WooCommerce
 * slugs/internal keys and must never leak into the shopper-facing label.
 */
(function () {
	function updateSelectedLabel(select) {
		if (!select || !select.name || select.name.indexOf("attribute_") !== 0) {
			return;
		}
		var group = select;
		while (group && group !== document && !(group.nodeType === 1 && group.hasAttribute("data-jluxe-variation-group"))) {
			group = group.parentElement;
		}
		if (!group || group === document) {
			return;
		}
		var label = group.querySelector("[data-jluxe-variation-selected]");
		if (!label) {
			return;
		}
		var option = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
		label.textContent = select.value && option ? option.textContent.trim() : "";
	}

	function syncAllSelectedLabels() {
		var selects = document.querySelectorAll('select[name^="attribute_"]');
		for (var i = 0; i < selects.length; i++) {
			updateSelectedLabel(selects[i]);
		}
	}

	document.addEventListener("change", function (event) {
		var select = event.target;
		if (select && "SELECT" === select.tagName) {
			updateSelectedLabel(select);
		}
	});
	syncAllSelectedLabels();
	document.addEventListener("DOMContentLoaded", syncAllSelectedLabels);
})();

/**
 * سواچ‌های pill محصول متغیر (woocommerce/single-product/add-to-cart/variable.php):
 * کلیک روی دکمه، مقدار select واقعی (مخفی، sr-only) رو ست کرده و change
 * واقعی dispatch می‌کنه تا wc-add-to-cart-variation.js خودِ ووکامرس
 * (تطبیق قیمت/موجودی/دکمه‌ی خرید) دقیقاً مثل حالت select عادی کار کنه.
 */
(function () {
	document.addEventListener("click", function (event) {
		var swatch = event.target.closest("[data-jluxe-variation-value]");
		if (!swatch) {
			return;
		}

		var group = swatch.closest("[data-jluxe-variation-group]");
		if (!group) {
			return;
		}

		var select = group.querySelector("select");
		if (!select) {
			return;
		}

		/* R65: سواچِ غیرقابل‌انتخاب (تنوعِ ناموجود) کلیک را نمی‌پذیرد. */
		if (swatch.disabled || swatch.classList.contains("jluxe-swatch-disabled")) {
			return;
		}

		var value = swatch.getAttribute("data-jluxe-variation-value");
		select.value = value;
		select.dispatchEvent(new Event("change", { bubbles: true }));

		group.querySelectorAll("[data-jluxe-variation-value]").forEach(function (btn) {
			if (btn === swatch) {
				btn.setAttribute("data-active", "");
			} else {
				btn.removeAttribute("data-active");
			}
		});

		/*
		 * برای گروه‌های رنگ (variable.php: data-jluxe-variation-label فقط
		 * روی ویژگی‌های رنگ رندر می‌شه)، اسمِ رنگِ انتخاب‌شده هم توی عنوانِ
		 * بالای سواچ‌ها («انتخاب رنگ: قهوه‌ای») و هم توی ردیفِ متناظرش در
		 * «ویژگی‌های کلیدی» (که قبلاً همیشه لیستِ کاملِ همه‌ی رنگ‌های ممکن
		 * رو نشون می‌داد، نه فقط رنگِ واقعاً انتخاب‌شده) به‌روزرسانی می‌شه.
		 */
		var selectedLabel = swatch.getAttribute("title") || swatch.getAttribute("aria-label") || value;
		var labelSpan = group.querySelector("[data-jluxe-variation-selected]");
		if (labelSpan) {
			labelSpan.textContent = selectedLabel;
		}
		var groupAttr = group.getAttribute("data-jluxe-variation-group");
		var keyspecValue = document.querySelector('[data-jluxe-keyspec-attr="' + groupAttr + '"] [data-jluxe-keyspec-value]');
		if (keyspecValue) {
			keyspecValue.textContent = selectedLabel;
		}
	});

	// «پاک کردن گزینه‌ها» (reset_variations) که خودِ ووکامرس با JS تزریق می‌کنه —
	// باید data-active همه‌ی سواچ‌ها هم پاک بشه، چون فقط select واقعی رو ریست می‌کنه؛
	// عنوانِ «انتخاب رنگ» و ردیفِ «ویژگی‌های کلیدی» هم به حالتِ پیش‌فرض (لیستِ
	// کاملِ گزینه‌ها) برمی‌گردن.
	document.addEventListener("click", function (event) {
		if (!event.target.closest(".reset_variations")) {
			return;
		}
		document.querySelectorAll("[data-jluxe-variation-value]").forEach(function (btn) {
			btn.removeAttribute("data-active");
		});
		document.querySelectorAll("[data-jluxe-variation-selected]").forEach(function (span) {
			span.textContent = "";
		});
		document.querySelectorAll("[data-jluxe-keyspec-value]").forEach(function (span) {
			span.textContent = span.getAttribute("data-jluxe-keyspec-default") || span.textContent;
		});
	});


	/**
	 * پنل فیلتر صفحه‌ی فروشگاه (inc/woocommerce.php: jluxe_render_shop_toolbar).
	 * باز/بسته‌شدنِ پنل صرفاً UI محلیه؛ اعمال واقعیِ فیلترها با ناوبری به
	 * URL جدید انجام می‌شه: انتخاب دسته/برند به permalink واقعیِ همون ترم
	 * می‌ره (data-url روی <option>)، قیمت/موجودی به‌صورت querystring
	 * (min_price/max_price بومیِ ووکامرسه، filter_stock رو خودمون در
	 * inc/woocommerce.php با pre_get_posts اضافه کردیم).
	 */
	// کلاس‌های flex/justify-start عمداً همراه با toggle شدنِ hidden اضافه/حذف
	// می‌شن (نه استاتیک روی خودِ عنصر) — چون Tailwind این‌ها رو در لایه‌ی
	// utilities می‌سازه که همیشه روی [hidden] (لایه‌ی base) برنده می‌شه؛
	// اگه استاتیک می‌بودن، panel با وجود hidden=true همچنان display:flex
	// می‌موند و پاپ‌آپ عملاً نه باز می‌شد نه بسته.
	// جلوی flex/justify-start + reflow اجباری (panel.offsetWidth خوندنِ صرف)
	// لازمه تا مرورگر حالتِ اولیه (بسته/hidden) رو واقعاً رسم کنه قبل از
	// اضافه‌شدنِ jluxe-open — وگرنه دو تغییرِ کلاس تو یک فریم ادغام می‌شن و
	// ترنزیشنِ CSS اصلاً اجرا نمی‌شه (پاپ‌آپ یهویی ظاهر می‌شه، نه اسلاید).
	function jluxeOpenFilterPanel(panel) {
		panel.hidden = false;
		panel.classList.add("flex", "justify-start");
		void panel.offsetWidth;
		panel.classList.add("jluxe-open");
		document.body.style.overflow = "hidden";
	}
	// بستن هم متقارنه: اول jluxe-open برداشته می‌شه (اسلاید/فید به بیرون
	// شروع می‌شه) و hidden فقط بعدِ تمومِ ترنزیشنِ درارو اضافه می‌شه — با یک
	// safety timeout (همون الگویِ img-skeleton.js) برای مواقعی که
	// transitionend به هر دلیلی فایر نشه.
	function jluxeCloseFilterPanel(panel) {
		panel.classList.remove("jluxe-open");
		document.body.style.overflow = "";
		var drawer = panel.querySelector("[data-jluxe-filter-drawer]");
		var finished = false;
		function finish() {
			if (finished) {
				return;
			}
			finished = true;
			panel.hidden = true;
			panel.classList.remove("flex", "justify-start");
		}
		if (drawer) {
			drawer.addEventListener("transitionend", finish, { once: true });
			window.setTimeout(finish, 400);
		} else {
			finish();
		}
	}

	document.addEventListener("click", function (event) {
		var panel = document.querySelector("[data-jluxe-filter-panel]");
		if (!panel) {
			return;
		}
		if (event.target.closest("[data-jluxe-filter-toggle]")) {
			jluxeOpenFilterPanel(panel);
			return;
		}
		if (event.target.closest("[data-jluxe-filter-close]")) {
			jluxeCloseFilterPanel(panel);
		}
	});

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Escape") {
			return;
		}
		var panel = document.querySelector("[data-jluxe-filter-panel]");
		if (panel && !panel.hidden) {
			jluxeCloseFilterPanel(panel);
		}
	});

	function jluxeSubmitCatalogFilters(jluxeFilterForm, event) {
		event.preventDefault();

		var catSelect = jluxeFilterForm.querySelector('[name="filter_cat"]');
		var brandSelect = jluxeFilterForm.querySelector('[name="filter_brand"]');
		var minPrice = jluxeFilterForm.querySelector('[name="min_price"]');
		var maxPrice = jluxeFilterForm.querySelector('[name="max_price"]');
		var inStock = jluxeFilterForm.querySelector('[name="filter_stock"]');
		var onSale = jluxeFilterForm.querySelector('[name="on_sale"]');
		var settings = window.JLuxeThemeSettings || {};
		var baseUrl = settings.shopUrl || (settings.urls && settings.urls.shop);
		if (!baseUrl) {
			window.location.href = jluxeFilterForm.getAttribute("action") || window.location.href;
			return;
		}

		function priceValue(input) {
			if (!input) return "";
			var value = JLuxeStorefrontUtils.normalizeDigits(input.value).replace(/[,،٬]/g, "").trim();
			input.setCustomValidity("");
			if (value && (!/^\d+(?:\.\d+)?$/.test(value) || !Number.isFinite(Number(value)))) {
				input.setCustomValidity("مبلغ معتبر وارد کنید.");
				input.reportValidity();
				return null;
			}
			return value;
		}

		var minimum = priceValue(minPrice);
		var maximum = priceValue(maxPrice);
		if (minimum === null || maximum === null) return;
		if (minimum && maximum && Number(minimum) > Number(maximum)) {
			maxPrice.setCustomValidity("حداکثر قیمت باید از حداقل کمتر نباشد.");
			maxPrice.reportValidity();
			return;
		}

		var destination = JLuxeStorefrontUtils.buildFilterUrl(baseUrl, window.location.href, {
			product_cat: catSelect ? catSelect.value : "",
			product_brand: brandSelect ? brandSelect.value : "",
			min_price: minimum,
			max_price: maximum,
			filter_stock: inStock && inStock.checked ? "instock" : "",
			on_sale: onSale && onSale.checked ? "1" : ""
		});
		var softNavigation = window.JLuxeSoftNavigation;
		if (softNavigation && typeof softNavigation.navigate === "function" && softNavigation.isEnabled()) {
			Promise.resolve(softNavigation.navigate(destination)).then(function (handled) {
				if (handled === false) window.location.href = destination;
			}).catch(function () {
				window.location.href = destination;
			});
			return;
		}
		window.location.href = destination;
	}

	document.addEventListener("input", function (event) {
		var form = event.target.closest && event.target.closest("[data-jluxe-filter-form]");
		if (form && event.target.setCustomValidity) event.target.setCustomValidity("");
	});
	document.addEventListener("submit", function (event) {
		var jluxeFilterForm = event.target.closest && event.target.closest("[data-jluxe-filter-form]");
		if (jluxeFilterForm) jluxeSubmitCatalogFilters(jluxeFilterForm, event);
	});
})();

/**
 * لیستِ کرکره‌ایِ مرتب‌سازی (.jluxe-shop-sort select.orderby) — خودِ
 * <option>های مرورگر (پاپ‌آپِ بومیِ سیستم‌عامل) با CSS قابلِ استایل‌دهی
 * نیستن؛ برای اینکه واقعاً «زیبا» بشه (طبقِ درخواستِ کاربر)، یک
 * لیست‌باکسِ سفارشی روی همون سلکتِ واقعی enhance می‌شه. خودِ <select>
 * تویِ DOM و کاملاً فعال می‌مونه (فقط با display:none مخفی می‌شه، نه
 * remove) — یعنی اگه به هر دلیلی این اسکریپت اجرا نشه یا خطا بده، فرمِ
 * اصلیِ ووکامرس دست‌نخورده و قابلِ استفاده‌ست (progressive enhancement).
 * انتخابِ یک گزینه‌ی سفارشی، value ی سلکتِ واقعی رو ست می‌کنه و یک
 * رویدادِ change واقعی dispatch می‌کنه — خودِ اسکریپتِ هسته‌یِ ووکامرس
 * (assets/js/frontend/woocommerce.js: تغییرِ .woocommerce-ordering
 * select.orderby) از همون رویداد برای submit خودکارِ فرم استفاده می‌کنه،
 * پس نیازی به بازنویسیِ منطقِ ارسال نیست.
 */
function jluxeEnhanceShopSort(root) {
	root = root || document;
	var wraps = [];
	if (root.matches && root.matches(".jluxe-shop-sort")) wraps.push(root);
	if (root.querySelectorAll) {
		Array.prototype.forEach.call(root.querySelectorAll(".jluxe-shop-sort"), function (wrap) { wraps.push(wrap); });
	}
	wraps.forEach(function (wrap) {
		if (wrap.getAttribute("data-jluxe-sort-enhanced") === "1") return;
		var select = wrap.querySelector("select.orderby");
		if (!select) {
			return;
		}

		select.classList.add("jluxe-sort-select-native");

		var trigger = document.createElement("button");
		trigger.type = "button";
		trigger.className = "jluxe-sort-trigger";
		trigger.setAttribute("aria-haspopup", "listbox");
		trigger.setAttribute("aria-expanded", "false");

		var label = document.createElement("span");
		label.className = "jluxe-sort-trigger-label";

		var chevron = wrap.querySelector("svg");
		trigger.appendChild(label);
		if (chevron) {
			trigger.appendChild(chevron);
		}

		var listbox = document.createElement("ul");
		listbox.className = "jluxe-sort-listbox";
		listbox.setAttribute("role", "listbox");
		listbox.hidden = true;

		function currentOption() {
			return select.options[select.selectedIndex];
		}

		function cleanOptionLabel(text) {
			return String(text || "").replace(/^\s*مرتب[\u200c\s]*سازی(?:\s+بر\s+اساس)?\s*[:：]?\s*/, "").trim();
		}

		function buildOptions() {
			listbox.innerHTML = "";
			Array.prototype.forEach.call(select.options, function (opt) {
				var li = document.createElement("li");
				li.setAttribute("role", "option");
				li.dataset.value = opt.value;
				var selected = opt.value === select.value;
				li.setAttribute("aria-selected", selected ? "true" : "false");
				li.className = "jluxe-sort-option" + (selected ? " jluxe-sort-option-active" : "");
				li.innerHTML = '<svg class="jluxe-sort-option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
				var optionLabel = document.createElement("span");
				optionLabel.textContent = cleanOptionLabel(opt.textContent);
				li.appendChild(optionLabel);
				li.tabIndex = -1;
				listbox.appendChild(li);
			});
		}

		function syncLabel() {
			var opt = currentOption();
			label.textContent = opt ? cleanOptionLabel(opt.textContent) : "";
		}

		// باز/بسته‌شدنِ لیست دو‌مرحله‌ایه (دقیقاً هم‌الگو با پنلِ فیلتر بالاتر
		// در همین فایل): چون display:none (از خودِ hidden attribute) و
		// کلاسِ ترانزیشن‌دارِ jluxe-sort-listbox-open نمی‌تونن تویِ یک فریم
		// با هم عوض بشن — وگرنه مرورگر ترانزیشن رو اصلاً اجرا نمی‌کنه و
		// لیست یهو ظاهر/محو می‌شه، نه نرم.
		function closeListbox() {
			listbox.classList.remove("jluxe-sort-listbox-open");
			trigger.setAttribute("aria-expanded", "false");
			trigger.classList.remove("jluxe-sort-trigger-open");
			var finished = false;
			function finish() {
				if (finished) {
					return;
				}
				finished = true;
				listbox.hidden = true;
			}
			listbox.addEventListener("transitionend", finish, { once: true });
			window.setTimeout(finish, 250);
		}

		function openListbox() {
			buildOptions();
			listbox.hidden = false;
			void listbox.offsetWidth;
			listbox.classList.add("jluxe-sort-listbox-open");
			trigger.setAttribute("aria-expanded", "true");
			trigger.classList.add("jluxe-sort-trigger-open");
			var active = listbox.querySelector(".jluxe-sort-option-active");
			if (active) {
				active.focus();
			}
		}

		trigger.addEventListener("click", function () {
			if (listbox.hidden) {
				openListbox();
			} else {
				closeListbox();
			}
		});

		listbox.addEventListener("click", function (event) {
			var li = event.target.closest(".jluxe-sort-option");
			if (!li) {
				return;
			}
			if (select.value !== li.dataset.value) {
				select.value = li.dataset.value;
				select.dispatchEvent(new Event("change", { bubbles: true }));
			}
			syncLabel();
			closeListbox();
			trigger.focus();
		});

		listbox.addEventListener("keydown", function (event) {
			var items = Array.prototype.slice.call(listbox.querySelectorAll(".jluxe-sort-option"));
			var currentIndex = items.indexOf(document.activeElement);
			if (event.key === "ArrowDown") {
				event.preventDefault();
				var next = items[Math.min(items.length - 1, currentIndex + 1)];
				if (next) {
					next.focus();
				}
			} else if (event.key === "ArrowUp") {
				event.preventDefault();
				var prev = items[Math.max(0, currentIndex - 1)];
				if (prev) {
					prev.focus();
				}
			} else if (event.key === "Enter" || event.key === " ") {
				event.preventDefault();
				document.activeElement.click();
			} else if (event.key === "Escape") {
				event.preventDefault();
				closeListbox();
				trigger.focus();
			} else if (event.key === "Tab") {
				closeListbox();
			}
		});

		listbox.addEventListener("focusout", function () {
			// اجازه می‌ده کلیک/فوکوسِ داخلِ همون لیست (بینِ گزینه‌ها) بدونِ
			// بسته‌شدن زودهنگام اتفاق بیفته — فقط وقتی فوکوس کاملاً از کلِ
			// wrap خارج شد ببندیم.
			window.setTimeout(function () {
				if (!wrap.contains(document.activeElement)) {
					closeListbox();
				}
			}, 0);
		});

		syncLabel();
		wrap._jluxeCloseSortListbox = closeListbox;
		wrap.appendChild(trigger);
		wrap.appendChild(listbox);
		wrap.setAttribute("data-jluxe-sort-enhanced", "1");
	});
}

(function () {
	// One delegated listener avoids retaining removed archive nodes after navigation.
	document.addEventListener("click", function (event) {
		var listbox = document.querySelector(".jluxe-sort-listbox:not([hidden])");
		var wrap = listbox && listbox.closest(".jluxe-shop-sort");
		if (wrap && !wrap.contains(event.target) && typeof wrap._jluxeCloseSortListbox === "function") {
			wrap._jluxeCloseSortListbox();
		}
	});
	window.jluxeEnhanceShopSort = jluxeEnhanceShopSort;
	jluxeEnhanceShopSort(document);
})();

/**
 * شهرستان (billing_city) وابسته به استان (billing_state) برای checkout و
 * فرم ویرایش نشانی حساب. billing در checkout و حساب جفت مستقل خودش را دارد؛
 * shipping فقط در فرم حساب فعال می‌شود تا جریان/فیلدهای ارسال در checkout
 * دست‌نخورده بمانند. فقط cityهای native از نوع select بازسازی می‌شوند.
 *
 * مرورگرهای موبایل گاهی استان را با Address Autofill بدون رویداد change
 * روی select می‌نویسند. تغییر را هم از رویدادها و هم هنگام فوکوس/پرشدنِ
 * فیلدهای آدرس کوتاه‌مدت بررسی می‌کنیم تا برای بارگذاری فهرست، مشتری مجبور
 * به انتخاب استان دیگر و برگشتن به استان اصلی نباشد.
 */
(function () {
	var cities = window.JLuxeThemeSettings && window.JLuxeThemeSettings.iranCities;
	if (!cities || "object" !== typeof cities) { cities = {}; }
	var checkoutCopy = (window.JLuxeThemeSettings && window.JLuxeThemeSettings.cartCheckout && window.JLuxeThemeSettings.cartCheckout.copy) || {};
	function getCheckoutText(key, fallback) {
		var currentSettings = window.JLuxeThemeSettings || {};
		var currentCopy = currentSettings.cartCheckout && currentSettings.cartCheckout.copy || checkoutCopy;
		var value = currentCopy[key];
		return typeof value === "string" && value.trim() ? value : fallback;
	}

	var addressPairs = [{ stateId: "billing_state", cityId: "billing_city" }];
	if (
		document.body &&
		document.body.classList.contains("woocommerce-account") &&
		document.querySelector(".woocommerce-address-fields")
	) {
		addressPairs.push({ stateId: "shipping_state", cityId: "shipping_city" });
	}

	addressPairs.forEach(function (addressPair) {
		var stateSelect = document.getElementById(addressPair.stateId);
		var citySelect = document.getElementById(addressPair.cityId);
		if (!stateSelect || !citySelect || stateSelect.tagName !== "SELECT" || citySelect.tagName !== "SELECT") {
			return;
		}

		function getProvinceCode() {
			return String(stateSelect.value || "").trim().toUpperCase();
		}

		function getProvinceCities(provinceCode) {
			var currentSettings = window.JLuxeThemeSettings || {};
			var currentCities = currentSettings.iranCities;
			if (currentCities && "object" === typeof currentCities) {
				cities = currentCities;
			}
			var list = cities[provinceCode];
			return Array.isArray(list) ? list : [];
		}

		var checkoutForm = stateSelect.closest("form.checkout");
		var preservePostedCheckoutLocation = !!(
			checkoutForm &&
			(stateSelect.hasAttribute("data-jluxe-preserve-checkout-location") || citySelect.hasAttribute("data-jluxe-preserve-checkout-location"))
		);
		var checkoutProvinceTouched = !checkoutForm || preservePostedCheckoutLocation;
		var checkoutLocationWasPreselected = false;
		if (checkoutForm) {
			stateSelect.setAttribute("autocomplete", "off");
			citySelect.setAttribute("autocomplete", "off");
			if (!preservePostedCheckoutLocation) {
				checkoutLocationWasPreselected = !!(stateSelect.value || citySelect.value);
				var hasBlankProvinceOption = Array.prototype.some.call(stateSelect.options, function (option) {
					return "" === option.value;
				});
				if (!hasBlankProvinceOption) {
					var blankProvinceOption = document.createElement("option");
					blankProvinceOption.value = "";
					blankProvinceOption.textContent = getCheckoutText("checkout_province_placeholder", "انتخاب استان");
					stateSelect.insertBefore(blankProvinceOption, stateSelect.firstChild);
				}
				stateSelect.value = "";
				citySelect.value = "";
			}
		}

		var renderedProvince = null;
		var renderedCitySignature = null;
		function rebuildCityOptions(preserveMode) {
			var provinceCode = getProvinceCode();
			var list = getProvinceCities(provinceCode);
			renderedProvince = provinceCode;
			renderedCitySignature = list.join("\u0000");
			var currentCity = citySelect.value;
			if ("clear" === preserveMode || !currentCity || ("valid" === preserveMode && list.indexOf(currentCity) === -1)) {
				currentCity = "";
			}

			citySelect.innerHTML = "";
			var placeholder = document.createElement("option");
			placeholder.value = "";
			placeholder.textContent = list.length
				? getCheckoutText("checkout_city_placeholder", "انتخاب شهرستان")
				: provinceCode
					? getCheckoutText("checkout_city_unavailable", "فهرست شهرستان‌های این استان در دسترس نیست")
					: getCheckoutText("checkout_city_prompt", "ابتدا استان را انتخاب کنید");
			citySelect.appendChild(placeholder);

			list.forEach(function (city) {
				var opt = document.createElement("option");
				opt.value = city;
				opt.textContent = city;
				citySelect.appendChild(opt);
			});

			if (currentCity) {
				if (list.indexOf(currentCity) === -1) {
					// Keep a legacy/custom saved value on first render only.
					var keep = document.createElement("option");
					keep.value = currentCity;
					keep.textContent = currentCity;
					citySelect.insertBefore(keep, citySelect.children[1] || null);
				}
				citySelect.value = currentCity;
			}
		}

		var activeProvince = getProvinceCode();
		rebuildCityOptions(checkoutForm && !preservePostedCheckoutLocation ? "clear" : "legacy");

		function stopAutofillWatch() {
			if (null !== autofillWatchTimer) {
				window.clearTimeout(autofillWatchTimer);
				autofillWatchTimer = null;
			}
		}

		function syncProvince(preserveMode) {
			var nextProvince = getProvinceCode();
			var nextCities = getProvinceCities(nextProvince);
			var nextCitySignature = nextCities.join("\u0000");
			var provinceChanged = nextProvince !== activeProvince;
			var cityListChanged = nextProvince !== renderedProvince || nextCitySignature !== renderedCitySignature;
			if (!provinceChanged && !cityListChanged) {
				return false;
			}
			activeProvince = nextProvince;
			rebuildCityOptions(provinceChanged ? preserveMode : "valid");
			citySelect.dispatchEvent(new Event("change", { bubbles: true }));
			return true;
		}

		var autofillWatchTimer = null;
		function clearAutofilledCheckoutLocation() {
			if (!checkoutForm || checkoutProvinceTouched) {
				return false;
			}
			var hadProvince = !!stateSelect.value;
			var hadCity = !!citySelect.value;
			if (!hadProvince && !hadCity) {
				return false;
			}
			stateSelect.value = "";
			citySelect.value = "";
			activeProvince = "";
			rebuildCityOptions("clear");
			if (hadProvince) {
				stateSelect.dispatchEvent(new Event("change", { bubbles: true }));
			}
			if (hadCity) {
				citySelect.dispatchEvent(new Event("change", { bubbles: true }));
			}
			return true;
		}

		function watchForAutofilledProvince() {
			stopAutofillWatch();
			var startedAt = Date.now();
			function checkForAutofill() {
				if (clearAutofilledCheckoutLocation()) {
					stopAutofillWatch();
					return;
				}
				if (syncProvince("valid")) {
					stopAutofillWatch();
					return;
				}
				if (Date.now() - startedAt >= 1800) {
					stopAutofillWatch();
					return;
				}
				autofillWatchTimer = window.setTimeout(checkForAutofill, 100);
			}
			checkForAutofill();
		}

		function markCheckoutProvinceTouched() {
			if (!checkoutForm) {
				return;
			}
			checkoutProvinceTouched = true;
			stopAutofillWatch();
		}

		function handleProvinceEvent() {
			if (clearAutofilledCheckoutLocation()) {
				stopAutofillWatch();
				return;
			}
			if (syncProvince("valid")) {
				stopAutofillWatch();
			}
		}

		/* WooCommerce SelectWoo emits jQuery events; mobile autofill may be native or silent. */
		if (window.jQuery) {
			window.jQuery(stateSelect).on("change", handleProvinceEvent);
			window.jQuery(stateSelect).on("select2:select", markCheckoutProvinceTouched);
		}
		stateSelect.addEventListener("change", handleProvinceEvent);
		stateSelect.addEventListener("input", handleProvinceEvent);
		if (checkoutForm) {
			var stateRow = stateSelect.closest(".form-row");
			[stateSelect, stateRow].forEach(function (target) {
				if (!target) {
					return;
				}
				target.addEventListener("pointerdown", markCheckoutProvinceTouched, true);
				target.addEventListener("touchstart", markCheckoutProvinceTouched, true);
				target.addEventListener("keydown", markCheckoutProvinceTouched, true);
			});
		}

		var addressRoot = stateSelect.closest("form") || document;
		function watchAfterOtherAddressField(event) {
			// The state control has its own direct handlers; don't rebuild ahead of WooCommerce's change listener.
			if (event && event.target === stateSelect) {
				return;
			}
			watchForAutofilledProvince();
		}
		addressRoot.addEventListener("focusin", watchForAutofilledProvince, true);
		addressRoot.addEventListener("input", watchAfterOtherAddressField, true);
		addressRoot.addEventListener("change", watchAfterOtherAddressField, true);
		window.addEventListener("pageshow", function (event) {
			if (checkoutForm && event && event.persisted) {
				checkoutProvinceTouched = false;
				clearAutofilledCheckoutLocation();
			}
			watchForAutofilledProvince();
		});
		if (checkoutLocationWasPreselected) {
			window.setTimeout(function () {
				stateSelect.dispatchEvent(new Event("change", { bubbles: true }));
			}, 0);
		}
		// Catch autofill applied shortly after DOM/script initialization as well.
		watchForAutofilledProvince();
	});
})();

/**
 * پیش‌نمایش عکس‌های دیگرِ محصول روی کارت (woocommerce/content-product.php):
 * هاور (دسکتاپ) یا لمس طولانی (موبایل) یک ردیف تامبنیل کوچیک پایین عکس
 * اصلی کارت نشون می‌ده؛ هاور/تپ روی هرکدوم عکس اصلیِ کارت رو موقتاً عوض
 * می‌کنه (بدون رفتن به صفحه محصول) و با خروج ماوس از کارت به حالت اول برمی‌گرده.
 */
(function () {
	function getCard(el) {
		return el && el.closest ? el.closest(".product") : null;
	}

	document.addEventListener("mouseover", function (event) {
		var thumb = event.target.closest && event.target.closest("[data-jluxe-card-thumb]");
		if (!thumb) {
			return;
		}
		var card = getCard(thumb);
		var img = card && card.querySelector("[data-jluxe-card-main-img]");
		if (!img) {
			return;
		}
		if (!img.dataset.jluxeOriginalSrc) {
			img.dataset.jluxeOriginalSrc = img.getAttribute("src") || "";
			img.dataset.jluxeOriginalSrcset = img.getAttribute("srcset") || "";
			img.dataset.jluxeOriginalSizes = img.getAttribute("sizes") || "";
		}
		img.removeAttribute("srcset");
		img.removeAttribute("sizes");
		var fullSrcset = thumb.getAttribute("data-srcset");
		var fullSizes = thumb.getAttribute("data-sizes");
		if (fullSrcset) { img.setAttribute("srcset", fullSrcset); }
		if (fullSizes) { img.setAttribute("sizes", fullSizes); }
		img.src = thumb.getAttribute("data-full");
	});

	// جلوگیری از رفتن به صفحه محصول وقتی فقط قصد پیش‌نمایش عکس رو داشتیم.
	document.addEventListener("click", function (event) {
		if (event.target.closest && event.target.closest("[data-jluxe-card-thumb]")) {
			event.preventDefault();
			event.stopPropagation();
		}
	});

	// خروج ماوس از کل کارت (نه فقط جابه‌جایی بین بچه‌هاش) → برگشت عکس اصلی + بستن ردیف تامبنیل.
	document.addEventListener("mouseout", function (event) {
		var card = getCard(event.target);
		if (!card) {
			return;
		}
		if (getCard(event.relatedTarget) === card) {
			return;
		}
		var img = card.querySelector("[data-jluxe-card-main-img]");
		if (img && img.dataset.jluxeOriginalSrc) {
			img.removeAttribute("srcset");
			img.removeAttribute("sizes");
			if (img.dataset.jluxeOriginalSrcset) { img.setAttribute("srcset", img.dataset.jluxeOriginalSrcset); }
			if (img.dataset.jluxeOriginalSizes) { img.setAttribute("sizes", img.dataset.jluxeOriginalSizes); }
			img.src = img.dataset.jluxeOriginalSrc;
		}
		card.removeAttribute("data-thumbs-active");
	});

	// لمس طولانی (موبایل، بدون :hover) برای باز کردن ردیف تامبنیل.
	var jluxePressTimer = null;
	document.addEventListener(
		"touchstart",
		function (event) {
			var card = getCard(event.target);
			if (!card || !card.querySelector("[data-jluxe-card-thumbs]")) {
				return;
			}
			jluxePressTimer = setTimeout(function () {
				card.setAttribute("data-thumbs-active", "");
			}, 450);
		},
		{ passive: true }
	);
	["touchend", "touchmove", "touchcancel"].forEach(function (evt) {
		document.addEventListener(
			evt,
			function () {
				clearTimeout(jluxePressTimer);
			},
			{ passive: true }
		);
	});
})();

/**
 * فیدبکِ افزودن به سبد جایگزینِ نوتیس استاندارد ووکامرس است. رویدادِ
 * added_to_cart از گرید، مودالِ انتخاب تنوع یا فرمِ محصول دریافت می‌شود.
 * دسکتاپ توست را می‌بیند؛ موبایل به‌جای کادرِ مشکی، پالسِ دکمه و در صورت
 * وجود، پنلِ پیشنهادها را می‌بیند تا نوارِ ناوبریِ پایین پوشانده نشود.
 * jluxe:cart-updated هم شمارنده و دراور سبد را تازه می‌کند.
 */
(function () {
	if (typeof window.jQuery === "undefined") {
		return;
	}

	function productNameFromButton(button) {
		if (!button) {
			return null;
		}
		// انتخابِ تنوعِ یک پیشنهاد، داخلِ یک فرمِ .cart قرار دارد؛ عنوانِ
		// مودالِ تنوع را بخوان تا توست نامِ محصولِ اصلیِ صفحه را اشتباه نگوید.
		var variantModal = button.closest(".jluxe-variant-modal");
		var variantTitle = variantModal && variantModal.querySelector(".jluxe-variant-modal-title");
		if (variantTitle && variantTitle.textContent.trim()) {
			return variantTitle.textContent.trim();
		}
		/*
		 * ترتیب عمداً مهمه: اول صفحه‌ی تکیِ محصول (marker اختصاصیِ
		 * data-jluxe-product-title در content-single-product.php) چک
		 * می‌شه؛ فقط اگه اون نبود (یعنی واقعاً از کارتِ گرید/مودالِ quick-add
		 * میایم) سراغِ عنوانِ کارت می‌ریم. قبلاً برعکس بود — روی صفحه‌ی تکیِ
		 * محصول، button.closest(".product") خودِ wrapperِ کلِ صفحه رو
		 * می‌گرفت (نه یک کارتِ گرید) و anchorِ اولِ منطبق با
		 * a[href][class*='text-foreground'] داخلش لینکِ بردکرامبِ «خانه»
		 * بود، نه عنوانِ محصول — باگِ واقعیِ گزارش‌شده («خانه به سبد خرید
		 * اضافه شد»).
		 */
		var singleTitle = document.querySelector("[data-jluxe-product-title]");
		if (singleTitle && button.closest("form.cart")) {
			return singleTitle.textContent.trim();
		}
		var card = button.closest(".product");
		var titleEl = card && card.querySelector(".woocommerce-loop-product__title, a[href][class*='text-foreground']");
		return titleEl && titleEl.textContent.trim() ? titleEl.textContent.trim() : null;
	}

	function productIdFromButton(button) {
		if (!button) { return ""; }
		var form = button.closest("form.cart");
		var addField = form && form.querySelector('[name="add-to-cart"]');
		var productRoot = button.closest("[data-jluxe-product-id]");
		return button.getAttribute("data-product_id") || button.getAttribute("data-product-id") || button.value ||
			(addField && addField.value) || (form && form.getAttribute("data-product_id")) ||
			(productRoot && productRoot.getAttribute("data-jluxe-product-id")) || "";
	}

	var cartFeedbackRevision = 0;

	function isConnectedElement(element) {
		if (!element) { return false; }
		if (typeof element.isConnected === "boolean") { return element.isConnected; }
		return !!(document.documentElement && document.documentElement.contains(element));
	}

	function isVisibleElement(element) {
		if (!isConnectedElement(element)) { return false; }
		var current = element;
		while (current && 1 === current.nodeType) {
			if (current.hidden || current.hasAttribute("hidden") || current.hasAttribute("inert") || current.getAttribute("aria-hidden") === "true" || current.classList.contains("hidden")) {
				return false;
			}
			var style = window.getComputedStyle(current);
			if (style.display === "none" || style.visibility === "hidden" || style.visibility === "collapse" || style.opacity === "0") {
				return false;
			}
			current = current.parentElement;
		}
		var rect = element.getBoundingClientRect();
		var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
		var right = typeof rect.right === "number" ? rect.right : rect.left + rect.width;
		var bottom = typeof rect.bottom === "number" ? rect.bottom : rect.top + rect.height;
		return rect.width > 0 && rect.height > 0 && right > 0 && bottom > 0 && rect.left < viewportWidth && rect.top < viewportHeight;
	}

	function cartGlyphInside(control) {
		if (!control) { return null; }
		var glyph = control.matches && control.matches("svg")
			? control
			: (control.querySelector && (control.querySelector("[data-jluxe-cart-icon-glyph]") || control.querySelector("svg")));
		if (glyph && isVisibleElement(glyph)) { return glyph; }
		return isVisibleElement(control) ? control : null;
	}

	function externalMobileCartLink() {
		var navigation = document.querySelector("#bottom-navigation");
		if (!isVisibleElement(navigation)) { return null; }
		var links = navigation.querySelectorAll("a[href], button, [role='link'], [role='button']");
		for (var linkIndex = 0; linkIndex < links.length; linkIndex++) {
			var link = links[linkIndex];
			if (!isVisibleElement(link)) { continue; }
			var href = (link.getAttribute("href") || "").toLowerCase();
			var label = [link.getAttribute("aria-label"), link.getAttribute("title"), link.textContent]
				.filter(Boolean).join(" ").toLowerCase();
			if (/(?:cart|basket)/.test(href) || /سبد/.test(label)) { return link; }
		}
		return null;
	}

	function findCartFeedbackTarget(isMobile) {
		var selectors = isMobile
			? [
				"[data-jluxe-mobile-price-bar] [data-jluxe-mobile-bar-add]",
				"[data-jluxe-sticky-cta].is-visible [data-jluxe-sticky-add]",
				"[data-jluxe-cart-icon-mobile]",
			]
			: ["[data-jluxe-cart-icon-desktop]"];
		for (var selectorIndex = 0; selectorIndex < selectors.length; selectorIndex++) {
			var controls = document.querySelectorAll(selectors[selectorIndex]);
			for (var controlIndex = 0; controlIndex < controls.length; controlIndex++) {
				var glyph = cartGlyphInside(controls[controlIndex]);
				if (glyph) { return glyph; }
			}
		}
		if (isMobile) {
			var oldDockCart = externalMobileCartLink();
			if (oldDockCart) { return cartGlyphInside(oldDockCart); }
			var priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");
			if (isVisibleElement(priceBar)) { return cartGlyphInside(priceBar); }
		}
		return null;
	}

	document.addEventListener("click", function (event) {
		var target = event.target && 1 === event.target.nodeType ? event.target : event.target && event.target.parentElement;
		var origin = target && target.closest
			? target.closest("[data-jluxe-mobile-bar-add], [data-jluxe-sticky-add]")
			: null;
		if (!origin || !isVisibleElement(origin) || origin.disabled) { return; }
		if (origin.getAttribute("data-jluxe-mobile-bar-mode") === "scroll" || origin.getAttribute("data-jluxe-sticky-mode") === "scroll") { return; }
		var realButton = document.querySelector("form.cart .single_add_to_cart_button");
		if (realButton && !realButton.disabled) {
			realButton._jluxeCartFeedbackOrigin = { element: origin, time: Date.now() };
		}
	}, true);

	function takeCartFeedbackOrigin(button) {
		if (!button || !button._jluxeCartFeedbackOrigin) { return button; }
		var pending = button._jluxeCartFeedbackOrigin;
		delete button._jluxeCartFeedbackOrigin;
		return Date.now() - pending.time <= 30000 && isVisibleElement(pending.element)
			? pending.element
			: button;
	}

	function discardSuggestedModal() {
		var stale = document.querySelector("[data-jluxe-suggested-modal]");
		if (stale && !stale.classList.contains("hidden") && typeof window.jluxeCloseSuggestedProductsModal === "function") {
			window.jluxeCloseSuggestedProductsModal();
		}
		if (stale && typeof stale._jluxeClearSuggestedAutoCloseTimer === "function") {
			stale._jluxeClearSuggestedAutoCloseTimer();
		}
		if (stale && stale.parentNode) { stale.parentNode.removeChild(stale); }
		window.jluxeOpenSuggestedProductsModal = null;
		window.jluxeCloseSuggestedProductsModal = null;
	}

	function mountAndOpenSuggestedModal(html, revision) {
		if (!html) { discardSuggestedModal(); return null; }
		if (typeof window.jluxeMountSuggestedModal !== "function") { return null; }
		var fresh = window.jluxeMountSuggestedModal(html);
		if (!fresh) { return null; }
		window.setTimeout(function () {
			if (revision === cartFeedbackRevision && fresh.isConnected && document.querySelector("[data-jluxe-suggested-modal]") === fresh && typeof window.jluxeOpenSuggestedProductsModal === "function") {
				window.jluxeOpenSuggestedProductsModal();
			}
		}, 0);
		return fresh;
	}

	function refreshNativeAddSnapshot(productId, revision) {
		var cart = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		if (!cart || !cart.ajaxUrl || !productId || typeof window.jluxeCartPost !== "function") {
			if (revision === cartFeedbackRevision) {
				discardSuggestedModal();
				window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: null }));
			}
			return;
		}
		var body = new URLSearchParams({
			action: "jluxe_cart",
			nonce: cart.nonce,
			op: "get",
			product_id: String(productId),
		});
		window.jluxeCartPost(cart.ajaxUrl, body, cart).then(function (response) {
			if (revision !== cartFeedbackRevision) { return; }
			if (!response || !response.success || !response.data) {
				discardSuggestedModal();
				window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: null }));
				return;
			}
			var snapshot = response.data;
			window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: snapshot }));
			if (typeof snapshot.suggested_html === "string" && snapshot.suggested_html) {
				mountAndOpenSuggestedModal(snapshot.suggested_html, revision);
			} else {
				discardSuggestedModal();
			}
		}).catch(function () {
			if (revision === cartFeedbackRevision) {
				discardSuggestedModal();
				window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: null }));
			}
		});
	}

	function suggestedProductNames(html) {
		if (!html) { return []; }
		var wrapper = document.createElement("div");
		wrapper.innerHTML = String(html);
		var names = [];
		wrapper.querySelectorAll("[data-pa-product], [data-pa-service]").forEach(function (row) {
			var name = row.querySelector(".jluxe-pa-name");
			var text = name && name.textContent ? name.textContent.trim() : "";
			if (text && names.indexOf(text) === -1) { names.push(text); }
		});
		return names.slice(0, 2);
	}

	function showToast(message, options) {
		options = options || {};
		var existing = document.querySelector(".jluxe-toast");
		if (existing) { existing.remove(); }
		// The mobile button pulse and suggestion sheet are the success feedback;
		// never leave a success toast sitting over the fixed bottom navigation.
		if (window.innerWidth < 768) { return; }
		var toast = document.createElement("section");
		toast.className = "jluxe-toast";
		toast.setAttribute("role", "status");
		toast.setAttribute("aria-live", "polite");
		toast.setAttribute("aria-atomic", "true");
		toast.setAttribute("dir", "rtl");
		toast.innerHTML =
			'<div class="jluxe-toast-main">' +
				'<span class="jluxe-toast-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>' +
				'<div class="jluxe-toast-copy"><strong class="jluxe-toast-msg"></strong><span class="jluxe-toast-product"></span><p class="jluxe-toast-suggestions" hidden></p></div>' +
				'<button type="button" class="jluxe-toast-close" aria-label="بستن پیام">×</button>' +
			'</div>' +
			'<div class="jluxe-toast-actions">' +
				'<a class="jluxe-toast-cta jluxe-toast-cta-primary" href="#">مشاهده سبد</a>' +
				'<button type="button" class="jluxe-toast-continue">ادامه خرید</button>' +
				'<button type="button" class="jluxe-toast-suggestions-cta" hidden>دیدن پیشنهادها</button>' +
			'</div>';
		toast.querySelector(".jluxe-toast-msg").textContent = message || "✓ به سبد اضافه شد";
		var productLine = toast.querySelector(".jluxe-toast-product");
		if (options.productName) { productLine.textContent = options.productName; }
		else { productLine.hidden = true; }
		var cartUrl = (window.jluxeWcSettings && window.jluxeWcSettings.cartUrl) || "/cart/";
		toast.querySelector(".jluxe-toast-cta-primary").href = cartUrl;

		var names = suggestedProductNames(options.suggestedHtml);
		var suggestionLine = toast.querySelector(".jluxe-toast-suggestions");
		var suggestionButton = toast.querySelector(".jluxe-toast-suggestions-cta");
		if (names.length) {
			suggestionLine.hidden = false;
			suggestionLine.textContent = "ممکن است این‌ها را هم لازم داشته باشید: " + names.join("، ");
			suggestionButton.hidden = false;
			suggestionButton.addEventListener("click", function () {
				if (typeof window.jluxeOpenSuggestedProductsModal === "function") {
					window.jluxeOpenSuggestedProductsModal();
					dismiss();
				}
			});
		}

		document.body.appendChild(toast);
		window.requestAnimationFrame(function () { toast.classList.add("jluxe-toast-visible"); });

		var timeout = null;
		var remaining = 8000;
		var startedAt = Date.now();
		function clearTimer() {
			if (timeout !== null) { window.clearTimeout(timeout); timeout = null; }
		}
		function startTimer() {
			clearTimer();
			startedAt = Date.now();
			timeout = window.setTimeout(dismiss, remaining);
		}
		function pauseTimer() {
			if (timeout === null) { return; }
			remaining = Math.max(1000, remaining - (Date.now() - startedAt));
			clearTimer();
		}
		function dismiss() {
			clearTimer();
			toast.classList.remove("jluxe-toast-visible");
			window.setTimeout(function () { toast.remove(); }, 220);
		}
		toast.addEventListener("mouseenter", pauseTimer);
		toast.addEventListener("mouseleave", startTimer);
		toast.addEventListener("focusin", pauseTimer);
		toast.addEventListener("focusout", function (event) {
			if (!toast.contains(event.relatedTarget)) { startTimer(); }
		});
		toast.querySelector(".jluxe-toast-close").addEventListener("click", dismiss);
		toast.querySelector(".jluxe-toast-cta-primary").addEventListener("click", function (event) {
			if (document.querySelector('[data-jluxe-island="mini-cart"]')) {
				event.preventDefault();
				window.dispatchEvent(new CustomEvent("jluxe:open-cart"));
			}
			dismiss();
		});
		toast.querySelector(".jluxe-toast-continue").addEventListener("click", function () {
			dismiss();
			if (options.sourceButton && options.sourceButton.isConnected && typeof options.sourceButton.focus === "function") {
				try { options.sourceButton.focus({ preventScroll: true }); } catch (error) { options.sourceButton.focus(); }
			}
		});
		startTimer();
	}

	/**
	 * انیمیشنِ سبکِ سبد (یک span و CSS transform/opacity، بدونِ rAF loop).
	 * مبدأ از کنترلِ stickyای می‌آید که مشتری واقعاً زده—نوارِ Boom یا
	 * Classic—نه دکمهٔ واقعیِ فرم که ممکن است بالاتر و بیرونِ دید باشد؛ مقصد
	 * هم آیکونِ قابل‌مشاهدهٔ dock فعال است: CTA خرید، MobileNav یا dock قدیمی.
	 * نقطهٔ پرواز بالاتر از نوار خرید و sheet پیشنهادها قرار می‌گیرد.
	 */
	function animateAddToCart(button, visualOrigin) {
		if (!button) {
			return;
		}
		var isMobile = window.innerWidth < 768;
		var target = findCartFeedbackTarget(isMobile);
		if (!target) { return; }
		// The proxy sticky CTA is what the customer actually tapped. Prefer it
		// over the often-offscreen WooCommerce form button for the animation start.
		var origin = visualOrigin || button;
		if (!isVisibleElement(origin)) { origin = target; }

		var startRect = origin.getBoundingClientRect();
		var endRect = target.getBoundingClientRect();
		var startX = startRect.left + startRect.width / 2;
		var startY = startRect.top + startRect.height / 2;
		var endX = endRect.left + endRect.width / 2;
		var endY = endRect.top + endRect.height / 2;
		var deltaX = endX - startX;
		var deltaY = endY - startY;
		// نقطه‌ی میانیِ مسیرِ کمانی — کمی بالاترِ خطِ راست تا حرکت به‌جای
		// خطِ صاف، یک قوسِ طبیعی داشته باشه (طبقِ درخواستِ کاربر: انیمیشنِ
		// قبلی چندان قشنگ نبود).
		var midX = deltaX / 2;
		var midY = deltaY / 2 - Math.max(50, Math.abs(deltaY) * 0.35);

		var dot = document.createElement("span");
		dot.className = "jluxe-cart-fly-dot";
		dot.style.left = startX + "px";
		dot.style.top = startY + "px";
		dot.style.setProperty("--jluxe-fly-mid-x", midX + "px");
		dot.style.setProperty("--jluxe-fly-mid-y", midY + "px");
		dot.style.setProperty("--jluxe-fly-end-x", deltaX + "px");
		dot.style.setProperty("--jluxe-fly-end-y", deltaY + "px");
		dot.innerHTML =
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>';
		document.body.appendChild(dot);

		var cleaned = false;
		function cleanup() {
			if (cleaned) {
				return;
			}
			cleaned = true;
			if (dot.parentNode) {
				dot.remove();
			}
			target.classList.add("jluxe-cart-bump");
			window.setTimeout(function () {
				target.classList.remove("jluxe-cart-bump");
			}, 380);
		}

		dot.addEventListener("animationend", cleanup, { once: true });
		// fallback: اگه animationend به هر دلیلی (مثلاً تب پس‌زمینه) فایر نشه.
		window.setTimeout(cleanup, 900);
	}

	/**
	 * محوشدنِ لحظه‌ایِ خودِ دکمه — جایگزینِ توستِ متنی روی موبایل (طبقِ
	 * درخواستِ کاربر: توی موبایل پیغامِ «به سبد اضافه شد» حذف بشه، به‌جاش
	 * خودِ دکمه یک لحظه محو بشه و دایره از روش به سمتِ سبد بپره) — فقط
	 * opacity/transform، سبک و GPU-composited.
	 */
	function fadeButtonPulse(button) {
		if (!button) {
			return;
		}
		button.classList.add("jluxe-btn-added-pulse");
		window.setTimeout(function () {
			button.classList.remove("jluxe-btn-added-pulse");
		}, 420);
	}

	window.jQuery(document.body).on("added_to_cart", function (event, fragments, cartHash, $button, cartSnapshot) {
		var feedbackRevision = ++cartFeedbackRevision;
		var button = $button && $button.length ? $button[0] : null;
		// خودِ اسکریپتِ هسته‌ی ووکامرس (assets/js/frontend/add-to-cart.js) روی
		// همین رویداد یک لینکِ خامِ «مشاهده سبد خرید» (.added_to_cart.wc-forward)
		// درست کنارِ دکمه اضافه می‌کنه — چیزی که توستِ سفارشیِ پایین جایگزینش
		// نمی‌کنه چون یک مکانیزمِ کاملاً جداست (تزریقِ DOM، نه نوتیس/session).
		// داخلِ کارتِ گرید چون فضایی براش دیده نشده، همین لینکِ اضافه باعثِ
		// بلندشدنِ ناگهانیِ کارت می‌شد (باگِ واقعیِ گزارش‌شده). چون خودمون
		// فیدبکِ معادل (توستِ دسکتاپ یا پالسِ موبایل + انیمیشنِ پرواز)
		// را داریم؛ این لینک همه‌جا حذف می‌شود.
		//
		// نکته‌ی مهم: این هندلر (روی همین رویدادِ added_to_cart) زودتر از
		// هندلرِ خودِ ووکامرس (که واقعاً لینک رو می‌سازه — با تستِ زنده تأیید
		// شد: jQuery._data(body,'events').added_to_cart لیستِ هندلرها رو به
		// همون ترتیبِ ثبت اجرا می‌کنه) صدا زده می‌شه، پس حذفِ مستقیم این‌جا
		// همیشه یک قدم دیر بود — لینک هنوز ساخته نشده. با setTimeout صفر
		// حذف به انتهای صفِ اجرای همین Tick می‌ره، بعدِ اینکه هندلرِ ووکامرس
		// (که خودش هم روی همین رویداده) لینک رو ساخته باشه.
		window.setTimeout(function () {
			document.querySelectorAll(".added_to_cart.wc-forward").forEach(function (el) {
				el.remove();
			});
		}, 0);
		var isMobile = window.innerWidth < 768;
		var visualOrigin = takeCartFeedbackOrigin(button);
		// On mobile pulse the control the customer actually tapped (the form
		// button can be off-screen behind the fixed product dock).
		if (isMobile && isVisibleElement(visualOrigin)) { fadeButtonPulse(visualOrigin); }
		var name = productNameFromButton(button);
		var fromSuggestion = !!(button && button.closest("[data-jluxe-suggested-modal], .jluxe-variant-modal-backdrop[data-jluxe-from-suggested='1']"));
		var hasSuggestionPayload = !!(cartSnapshot && typeof cartSnapshot.suggested_html === "string");
		var suggestedHtml = !fromSuggestion && hasSuggestionPayload ? cartSnapshot.suggested_html : "";
		if (suggestedHtml) {
			mountAndOpenSuggestedModal(suggestedHtml, feedbackRevision);
		} else if (hasSuggestionPayload && !fromSuggestion) {
			discardSuggestedModal();
		}
		showToast("✓ به سبد اضافه شد", {
			productName: name,
			suggestedHtml: suggestedHtml,
			sourceButton: button,
		});
		animateAddToCart(button, visualOrigin);
		/* R63 (فاز ۲): Quick Add واقعی روی دکمهٔ دایره‌ای کارت — آیکون +
		تبدیل به تیک و برچسبِ «به سبد اضافه شد»، بعدِ ~۲ ثانیه بازگشت.
		(بجِ سبد/مینی‌کارت توسطِ رویدادِ jluxe:cart-updatedِ همین هندلر —
		چند خط پایین‌تر — از مسیرِ REST رفرش می‌شود.) */
		if (button && button.classList && button.classList.contains("add_to_cart_button")) {
			if (button._jluxeAddedTimer) { window.clearTimeout(button._jluxeAddedTimer); button._jluxeAddedTimer = null; }
			if (button.dataset.jluxeOrigHtml === undefined) { button.dataset.jluxeOrigHtml = button.innerHTML; }
			button.classList.add("jluxe-btn-added");
			button.setAttribute("aria-label", "به سبد اضافه شد");
			button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-4"><path d="m5 12 5 5 9-10"/></svg>';
			button._jluxeAddedTimer = window.setTimeout(function () {
				button.classList.remove("jluxe-btn-added");
				button.setAttribute("aria-label", "افزودن به سبد خرید");
				button.innerHTML = button.dataset.jluxeOrigHtml;
				button._jluxeAddedTimer = null;
			}, 2200);
		}
		/* Our endpoint already returns the post-add cart snapshot and fresh suggestions.
		 * Native WooCommerce loop adds have no such payload, so one read-only op=get
		 * returns both in a single request; it never adds the product a second time. */
		if (!fromSuggestion && !hasSuggestionPayload) {
			refreshNativeAddSnapshot(productIdFromButton(button), feedbackRevision);
		} else {
			window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: cartSnapshot || null }));
		}
	});
})();

/*
 * R73: پاپ‌آپِ «ثبت دیدگاه» — فرمِ واقعیِ ووکامرس (#review_form_wrapper با
 * ستارهٔ کلی + ستاره‌های معیارها از تنظیماتِ پوسته) به‌صورتِ Progressive
 * Enhancement به داخلِ مودال منتقل می‌شود؛ بدونِ JS همان فرمِ درون‌خطیِ
 * همیشگی می‌ماند. ارسال با fetch و redirect:"manual" به wp-comments-post.php
 * خودِ وردپرس انجام می‌شود (ریدایرکتِ 302 یعنی موفق؛ wp-die های هسته — فیلدِ
 * خالی/تکراری/سیلاب — متنشان استخراج و داخلِ مودال نشان داده می‌شود)؛ پس از
 * موفقیت: پیامِ «ثبت شد، منتظرِ تأییدِ مدیر» — چون دیدگاه‌ها طبقِ قانونِ
 * پوسته همیشه مودِارت می‌شوند. تلهٔ فوکوس + Escape + قفلِ اسکرول مثل بقیهٔ
 * مودال‌ها.
 */
(function () {
	var modalRoot = null;
	var releaseDialogFocus = null;
	var lastFocused = null;
	var formNode = null;
	/* R73.1: فرمِ وو قرضی است — هنگامِ بستن باید به خانه‌اش (#review_form)
	برگردد، وگرنه با remove() مودال برای همیشه از DOM حذف می‌شد و باز
	کردنِ دومِ پاپ‌آپ ممکن نبود. */
	var reviewWrapper = null;
	var reviewFormHome = null;

	function closeReviewModal() {
		if (!modalRoot) { return; }
		if (releaseDialogFocus) { releaseDialogFocus(); releaseDialogFocus = null; }
		document.body.style.overflow = "";
		if (reviewWrapper && reviewFormHome && reviewFormHome.parentNode) {
			reviewFormHome.insertBefore(reviewWrapper, reviewFormHome.firstChild);
		}
		modalRoot.remove();
		modalRoot = null;
		if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
	}

	function showReviewSuccess() {
		if (!modalRoot) { return; }
		var body = modalRoot.querySelector(".jluxe-review-modal-body");
		if (body) {
			body.innerHTML = '<div class="jluxe-review-modal-success" role="status">' +
				'<span class="jluxe-review-modal-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>' +
				'<strong>دیدگاه شما ثبت شد</strong>' +
				'<span>پس از بررسی و تأیید مدیر منتشر می‌شود؛ از همراهی شما سپاسگزاریم.</span>' +
				"</div>";
		}
		if (formNode) { formNode.reset(); }
		window.setTimeout(closeReviewModal, 3200);
	}

	function showReviewError(message) {
		if (!modalRoot) { return; }
		var body = modalRoot.querySelector(".jluxe-review-modal-body");
		if (!body) { return; }
		var box = modalRoot.querySelector(".jluxe-review-modal-error");
		if (!box) {
			box = document.createElement("p");
			box.className = "jluxe-review-modal-error";
			box.setAttribute("role", "alert");
			body.insertBefore(box, body.firstChild);
		}
		box.textContent = message;
	}

	function showReviewUnavailable(trigger) {
		var toolbar = trigger && trigger.closest(".cp3-reviewtoolbar");
		if (!toolbar) { return; }
		var message = toolbar.querySelector(".cp3-review-unavailable");
		if (!message) {
			message = document.createElement("p");
			message.className = "cp3-review-unavailable";
			message.setAttribute("role", "status");
			toolbar.appendChild(message);
		}
		message.textContent = "فرم ثبت دیدگاه برای این محصول در دسترس نیست؛ ممکن است دیدگاه‌ها بسته باشند.";
	}

	function openReviewModal(trigger) {
		var wrapper = document.getElementById("review_form_wrapper");
		if (!wrapper || !wrapper.querySelector("form#commentform")) {
			showReviewUnavailable(trigger);
			return;
		}
		if (modalRoot) { closeReviewModal(); return; }
		lastFocused = document.activeElement;
		modalRoot = document.createElement("div");
		modalRoot.className = "jluxe-review-modal-backdrop";
		modalRoot.innerHTML =
			'<div class="jluxe-review-modal" role="dialog" aria-modal="true" aria-labelledby="jluxe-review-modal-title">' +
			'<button type="button" class="jluxe-review-modal-close" aria-label="بستن">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
			"</button>" +
			'<div class="jluxe-review-modal-head"><h2 id="jluxe-review-modal-title">ثبت دیدگاه</h2><p>به هر معیار امتیازِ ستاره بدهید؛ دیدگاه شما پس از تأیید مدیر منتشر می‌شود.</p></div>' +
			'<div class="jluxe-review-modal-body"></div>' +
			"</div>";
		document.body.appendChild(modalRoot);
		formNode = wrapper.querySelector("form#commentform") || null;
		reviewWrapper = wrapper;
		reviewFormHome = wrapper.parentNode;
		modalRoot.querySelector(".jluxe-review-modal-body").appendChild(wrapper);
		document.body.style.overflow = "hidden";
		if (window.JLuxeStorefrontUtils && typeof window.JLuxeStorefrontUtils.activateDialog === "function") {
			releaseDialogFocus = window.JLuxeStorefrontUtils.activateDialog(modalRoot.querySelector("[role=dialog]"), closeReviewModal);
		}
		modalRoot.addEventListener("click", function (event) {
			if (event.target === modalRoot || event.target.closest(".jluxe-review-modal-close")) {
				closeReviewModal();
			}
		});
	}

	document.addEventListener("click", function (event) {
		var trigger = event.target.closest("[data-jluxe-review-modal]");
		if (!trigger) { return; }
		event.preventDefault();
		openReviewModal(trigger);
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape" && modalRoot) { closeReviewModal(); }
	});

	document.addEventListener("submit", function (event) {
		var form = event.target.closest(".jluxe-review-modal form#commentform");
		if (!form || !window.fetch) { return; }
		var actionUrl;
		try {
			actionUrl = new URL(form.getAttribute("action") || window.location.href, window.location.href);
		} catch (error) {
			return; // Keep the browser's native POST as the safe fallback.
		}
		/* A cross-origin wp-comments-post.php action cannot receive same-origin
		 * login cookies through fetch. Let the browser's ordinary form POST handle it. */
		if (actionUrl.origin !== window.location.origin) { return; }
		event.preventDefault();
		var submit = form.querySelector("[type=submit]");
		if (submit) { submit.disabled = true; }
		fetch(form.getAttribute("action") || window.location.href, {
			method: "POST",
			body: new FormData(form),
			redirect: "manual",
			credentials: "same-origin",
		}).then(function (res) {
			if (res.type === "opaqueredirect" || 0 === res.status) { showReviewSuccess(); return null; }
			return res.text().then(function (html) {
				if (res.ok && -1 === html.indexOf("wp-die-message")) {
					/* پاسخِ پیش‌بینی‌نشده — صفحهٔ کامل نتیجه را نشان می‌دهد. */
					window.location.reload();
					return null;
				}
				var doc = new DOMParser().parseFromString(html, "text/html");
				var el = doc.querySelector(".wp-die-message");
				var msg = el ? String(el.textContent || "").replace(/\s+/g, " ").trim() : "";
				showReviewError(msg ? msg.slice(0, 240) : "ثبت دیدگاه انجام نشد؛ دوباره تلاش کنید.");
			});
		}).catch(function () {
			showReviewError("ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.");
		}).finally(function () {
			if (submit) { submit.disabled = false; }
		});
	});

	/* Hide the inline form only after the modal handler is ready; without JS the anchor falls back to it. */
	var availableReviewForm = document.getElementById("review_form_wrapper");
	if (availableReviewForm && availableReviewForm.querySelector("form#commentform")) {
		document.querySelectorAll("[data-jluxe-review-modal]").forEach(function (trigger) {
			var productRoot = trigger.closest(".jluxe-cp3");
			if (productRoot) { productRoot.classList.add("jluxe-review-modal-ready"); }
		});
	}
})();

/**
 * پاپ‌آپِ «محصولات پیشنهادی» — HTML سمتِ سرور رندر می‌شود، اما این ماژول
 * انتخاب‌ها و ثبتِ ترتیبیِ آن‌ها را مدیریت می‌کند. بعد از افزودنِ موفقِ
 * تنوع از picker، انتخاب‌های باقی‌مانده این‌جا ثبت می‌شوند؛ موفقیت‌ها از
 * انتخاب حذف می‌شوند و شکست‌ها برای تلاشِ آگاهانهٔ بعدی باقی می‌مانند.
 */
(function () {
	/* R61 — بازسازی به‌صورتِ بایندِ تابعی: مودال می‌تواند (۱) در لودِ صفحه به‌صورتِ
	SSR حاضر باشد (رفتارِ قبلی) یا (۲) بعدِ افزودنِ موفق، با HTML تازه‌ای که
	خودِ endpoint افزودن در پاسخ برمی‌گرداند سوار شود (موجودی/قابل‌خریدِ لحظه‌ای).
	keydown به‌صورتِ سراسری یک‌بار بسته می‌شود و همیشه «مودالِ فعلیِ DOM» را
	می‌بندد — وگرنه بعد از جایگزینیِ مودال، Escape گره‌ی قدیمیِ حذف‌شده را
	می‌بست و مودالِ تازه با Esc بسته نمی‌شد. */
	function jluxeBindSuggestedModal(modal) {
		if (!modal || modal.getAttribute("data-cp3-pa-bound") === "1") { return; }
		modal.setAttribute("data-cp3-pa-bound", "1");

		var releaseDialogFocus = null;
		var previousBodyOverflow = "";
		var autoCloseTimer = null;
		var autoCloseCountdownTimer = null;
		var autoCloseDeadline = 0;
		var countdownDisplay = modal.querySelector("[data-pa-countdown]");
		var countdownValue = countdownDisplay && countdownDisplay.querySelector("[data-pa-countdown-value]");
		var autoCloseEnabled = modal.getAttribute("data-pa-auto-close") === "1";
		var autoCloseSeconds = parseInt(modal.getAttribute("data-pa-auto-close-seconds"), 10);
		if (!isFinite(autoCloseSeconds)) { autoCloseSeconds = 15; }
		autoCloseSeconds = Math.max(3, Math.min(300, autoCloseSeconds));

		function clearSuggestedAutoCloseTimer() {
			if (autoCloseTimer !== null) {
				window.clearTimeout(autoCloseTimer);
				autoCloseTimer = null;
			}
			if (autoCloseCountdownTimer !== null) {
				window.clearInterval(autoCloseCountdownTimer);
				autoCloseCountdownTimer = null;
			}
			autoCloseDeadline = 0;
		}

		function formatSuggestedCountdown(seconds) {
			var minutes = Math.floor(seconds / 60);
			var remainder = seconds % 60;
			var value = (minutes < 10 ? "0" : "") + minutes + ":" + (remainder < 10 ? "0" : "") + remainder;
			return value.replace(/[0-9]/g, function (digit) { return "۰۱۲۳۴۵۶۷۸۹".charAt(Number(digit)); });
		}

		function updateSuggestedCountdown() {
			if (!countdownValue || !autoCloseDeadline) { return; }
			var remaining = Math.max(0, Math.ceil((autoCloseDeadline - Date.now()) / 1000));
			countdownValue.textContent = formatSuggestedCountdown(remaining);
			if (countdownDisplay) {
				if (remaining <= 5) { countdownDisplay.setAttribute("data-pa-countdown-urgent", "1"); }
				else { countdownDisplay.removeAttribute("data-pa-countdown-urgent"); }
			}
			if (remaining === 0 && autoCloseCountdownTimer !== null) {
				window.clearInterval(autoCloseCountdownTimer);
				autoCloseCountdownTimer = null;
			}
		}

		function scheduleSuggestedAutoClose() {
			clearSuggestedAutoCloseTimer();
			if (!autoCloseEnabled || modal.classList.contains("hidden")) {
				if (countdownDisplay) {
					countdownDisplay.hidden = true;
					countdownDisplay.removeAttribute("data-pa-countdown-urgent");
				}
				return;
			}
			autoCloseDeadline = Date.now() + autoCloseSeconds * 1000;
			if (countdownDisplay) { countdownDisplay.hidden = false; }
			updateSuggestedCountdown();
			if (countdownValue) { autoCloseCountdownTimer = window.setInterval(updateSuggestedCountdown, 1000); }
			autoCloseTimer = window.setTimeout(function () {
				autoCloseTimer = null;
				// Never dismiss the sheet in the middle of a WooCommerce mutation.
				if (isCommitting) { scheduleSuggestedAutoClose(); return; }
				closeSuggestedProductsModal();
			}, autoCloseSeconds * 1000);
		}
		modal._jluxeClearSuggestedAutoCloseTimer = clearSuggestedAutoCloseTimer;

		window.jluxeOpenSuggestedProductsModal = function () {
			var wasHidden = modal.classList.contains("hidden");
			if (wasHidden) { previousBodyOverflow = document.body.style.overflow; }
			modal.classList.remove("hidden");
			modal.setAttribute("aria-hidden", "false");
			document.body.style.overflow = "hidden";
			window.jluxeCloseSuggestedProductsModal = closeSuggestedProductsModal;
			/* R63 (فاز ۶): تلهٔ فوکوسِ مشترک (storefront-utils.js) — Tab داخل
			شیت می‌ماند، فوکوس اول به خودِ مودال می‌رود و بعد از بستن به
			عنصرِ قبلی برمی‌گردد. */
			if (!releaseDialogFocus && window.JLuxeStorefrontUtils && typeof window.JLuxeStorefrontUtils.activateDialog === "function") {
				releaseDialogFocus = window.JLuxeStorefrontUtils.activateDialog(modal.querySelector(".jluxe-pa-sheet") || modal, function () {
					if (typeof window.jluxeCloseSuggestedProductsModal === "function") { window.jluxeCloseSuggestedProductsModal(); }
				});
			}
			if (wasHidden) { scheduleSuggestedAutoClose(); }
		};

		function closeSuggestedProductsModal() {
			if (isCommitting || modal.classList.contains("hidden")) { return false; }
			clearSuggestedAutoCloseTimer();
			if (countdownDisplay) {
				countdownDisplay.hidden = true;
				countdownDisplay.removeAttribute("data-pa-countdown-urgent");
			}
			modal.classList.add("hidden");
			modal.setAttribute("aria-hidden", "true");
			var drawerStillOpen = document.documentElement && document.documentElement.hasAttribute("data-jluxe-mobile-drawer");
			document.body.style.overflow = drawerStillOpen ? "hidden" : previousBodyOverflow;
			if (releaseDialogFocus) { releaseDialogFocus(); releaseDialogFocus = null; }
			if (window.jluxeCloseSuggestedProductsModal === closeSuggestedProductsModal) {
				window.jluxeCloseSuggestedProductsModal = null;
			}
			return true;
		}

		modal.querySelectorAll("[data-jluxe-suggested-close]").forEach(function (el) {
			el.addEventListener("click", closeSuggestedProductsModal);
		});

		var paTotal = modal.querySelector("[data-pa-total]");
		var paConfirm = modal.querySelector("[data-pa-confirm]");
		var paConfirmLabel = paConfirm && paConfirm.querySelector("[data-pa-confirm-label]");
		var paMainAmount = parseFloat(modal.getAttribute("data-pa-main")) || 0;

		function paFormat(amount) {
			/* R64: اعدادِ مبلغِ مودال همیشه فارسی — قبلاً به window.jluxeFaDigits
			تکیه می‌کرد که سراسری تعریف نشده بود و بعدِ اولین بازمحاسبهٔ JS،
			ارقامِ لاتین («795,000») جای ارقامِ فارسیِ سرور می‌نشست. */
			var rounded = Math.max(0, Math.round(amount));
			var withSeparators = String(rounded).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			return withSeparators.replace(/[0-9]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".charAt(+d); });
		}

		function paUpdateTotal() {
			var total = paMainAmount;
			var selectedRows = modal.querySelectorAll("[data-pa-service][aria-pressed='true'], [data-pa-product][aria-pressed='true']");
			selectedRows.forEach(function (row) {
				total += parseFloat(row.getAttribute("data-pa-amount")) || 0;
			});
			if (paTotal) { paTotal.textContent = paFormat(total); }
			if (paConfirmLabel && paConfirm) {
				var labelAttribute = selectedRows.length ? "data-pa-confirm-selected" : "data-pa-confirm-empty";
				paConfirmLabel.textContent = paConfirm.getAttribute(labelAttribute) || "ادامه بدون افزودن";
			}
		}

		function clearSuggestedError() {
			var error = modal.querySelector("[data-pa-error]");
			if (error) { error.remove(); }
		}

		function showSuggestedError(message) {
			var error = modal.querySelector("[data-pa-error]");
			if (!error) {
				error = document.createElement("div");
				error.className = "jluxe-pa-error";
				error.setAttribute("data-pa-error", "");
				error.setAttribute("role", "alert");
				var body = modal.querySelector(".jluxe-pa-body");
				if (body) { body.insertBefore(error, body.firstChild); }
			}
			var decoder = new DOMParser().parseFromString(String(message || "خطا در ثبتِ انتخاب‌ها. دوباره تلاش کنید."), "text/html");
			error.textContent = String(decoder.body.textContent || "").trim();
		}

		function restorePaConfirm() {
			if (!paConfirm) { return; }
			paConfirm.disabled = false;
			paConfirm.classList.remove("opacity-60", "pointer-events-none");
		}

		function releaseAddedProducts(productIds) {
			if (!productIds.length) { return; }
			modal.querySelectorAll("[data-pa-product][aria-pressed='true']").forEach(function (row) {
				if (productIds.indexOf(row.getAttribute("data-pa-product")) === -1) { return; }
				row.setAttribute("aria-pressed", "false");
				row.classList.remove("is-selected");
			});
			paUpdateTotal();
		}

		modal.querySelectorAll("[data-pa-service], [data-pa-product]").forEach(function (row) {
			row.addEventListener("click", function () {
				if (isCommitting) { return; }
				var pressed = row.getAttribute("aria-pressed") === "true";
				row.setAttribute("aria-pressed", pressed ? "false" : "true");
				row.classList.toggle("is-selected", !pressed);
				clearSuggestedError();
				paUpdateTotal();
			});
		});
		modal.addEventListener("jluxe:pa-refresh", paUpdateTotal);
		paUpdateTotal();

		var cartCfg = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		var isCommitting = false;
		var busyButtons = [];
		var activeOperation = null;

		function clearSuggestedStatus() {
			var status = modal.querySelector("[data-pa-status]");
			if (status) { status.remove(); }
		}

		function showSuggestedStatus(message) {
			var status = modal.querySelector("[data-pa-status]");
			if (!status) {
				status = document.createElement("p");
				status.className = "jluxe-pa-status";
				status.setAttribute("data-pa-status", "");
				status.setAttribute("role", "status");
				status.setAttribute("aria-live", "polite");
				status.setAttribute("tabindex", "-1");
				var body = modal.querySelector(".jluxe-pa-body");
				if (body) { body.insertBefore(status, body.firstChild); }
			}
			status.textContent = message;
			try { status.focus({ preventScroll: true }); } catch (error) { status.focus(); }
			return status;
		}

		function setModalBusy(busy) {
			modal.setAttribute("aria-busy", busy ? "true" : "false");
			if (busy) {
				busyButtons = [];
				modal.querySelectorAll("button").forEach(function (button) {
					busyButtons.push({ button: button, wasDisabled: button.disabled });
					button.disabled = true;
				});
				return;
			}
			busyButtons.forEach(function (entry) {
				if (entry.button && entry.button.isConnected) { entry.button.disabled = entry.wasDisabled; }
			});
			busyButtons = [];
		}

		function updateSuggestedModalSnapshot(snapshot) {
			var html = snapshot && typeof snapshot.suggested_html === "string" ? snapshot.suggested_html : "";
			if (!html) { return; }
			var wrapper = document.createElement("div");
			wrapper.innerHTML = html;
			var fresh = wrapper.querySelector("[data-jluxe-suggested-modal]");
			if (!fresh) { return; }
			var freshBase = parseFloat(fresh.getAttribute("data-pa-main"));
			if (Number.isFinite(freshBase)) {
				paMainAmount = Math.max(0, freshBase);
				modal.setAttribute("data-pa-main", String(paMainAmount));
			}
			var freshSummary = fresh.querySelector(".jluxe-pa-cartinfo");
			var currentSummary = modal.querySelector(".jluxe-pa-cartinfo");
			if (freshSummary && currentSummary) { currentSummary.textContent = freshSummary.textContent; }
			modal.dispatchEvent(new Event("jluxe:pa-refresh"));
		}

		function publishCartSnapshot(snapshot) {
			if (!snapshot || typeof snapshot !== "object") { return; }
			updateSuggestedModalSnapshot(snapshot);
			window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: snapshot }));
		}

		function disposeSuggestedModal() {
			var isCurrent = document.querySelector("[data-jluxe-suggested-modal]") === modal;
			closeSuggestedProductsModal();
			if (modal.parentNode) { modal.parentNode.removeChild(modal); }
			if (isCurrent) {
				window.jluxeOpenSuggestedProductsModal = null;
				window.jluxeCloseSuggestedProductsModal = null;
				window.jluxeCommitSuggestedSelection = null;
				window.jluxeSyncSuggestedModalCart = null;
				window.jluxeShowSuggestedAddWarning = null;
			}
		}

		function selectedIds(selector, attribute) {
			return Array.prototype.map.call(modal.querySelectorAll(selector), function (row) {
				return row.getAttribute(attribute) || "";
			}).filter(function (value, index, all) {
				return value && all.indexOf(value) === index;
			});
		}

		function postOp(fields) {
			if (!cartCfg || !cartCfg.ajaxUrl || typeof window.jluxeCartPost !== "function") {
				return Promise.reject(new Error("Cart endpoint is unavailable"));
			}
			var body = new FormData();
			body.set("action", "jluxe_cart");
			body.set("nonce", cartCfg.nonce);
			Object.keys(fields).forEach(function (key) { body.set(key, fields[key]); });
			var contextId = modal.getAttribute("data-pa-context");
			if (contextId) { body.set("pa_context_id", contextId); }
			return window.jluxeCartPost(cartCfg.ajaxUrl, body, cartCfg);
		}

		function commitSelectedSuggestions(options) {
			options = options || {};
			if (isCommitting) { return Promise.resolve({ success: false, busy: true }); }
			var productIds = selectedIds("[data-pa-product][aria-pressed='true']", "data-pa-product");
			var serviceKeys = selectedIds("[data-pa-service][aria-pressed='true']", "data-pa-service");
			var hasModalServices = modal.querySelector("[data-pa-service]") !== null;
			var removeAfterCommit = !!options.removeOnSuccess || modal.getAttribute("data-pa-variation-committed") === "1";
			var removeIfEmpty = !!options.removeWhenEmpty || removeAfterCommit;

			if (!productIds.length && !serviceKeys.length) {
				if (removeIfEmpty) { disposeSuggestedModal(); }
				else { closeSuggestedProductsModal(); }
				return Promise.resolve({ success: true, empty: true });
			}

			if (options.openOnStart && modal.classList.contains("hidden") && typeof window.jluxeOpenSuggestedProductsModal === "function") {
				window.jluxeOpenSuggestedProductsModal();
			}
			clearSuggestedError();
			clearSuggestedStatus();
			isCommitting = true;
			setModalBusy(true);
			showSuggestedStatus(options.source === "variation"
				? "تنوع به سبد اضافه شد؛ در حال ثبت انتخاب‌های قبلی…"
				: "در حال افزودن انتخاب‌ها به سبد…");
			if (paConfirmLabel) { paConfirmLabel.textContent = "در حال ثبت انتخاب‌ها…"; }

			var addedProductIds = [];
			var errors = [];
			var lastSnapshot = null;
			var chain = Promise.resolve();
			productIds.forEach(function (productId) {
				chain = chain.then(function () {
					// WooCommerce revalidates every selected offer on the server.
					activeOperation = { type: "add", productId: String(productId) };
					return postOp({ op: "add", product_id: productId, quantity: 1 }).then(function (response) {
						activeOperation = null;
						if (!response || !response.success) {
							var message = response && response.data && response.data.message ? response.data.message : "افزودنِ پیشنهاد انجام نشد.";
							errors.push(message);
							return null;
						}
						addedProductIds.push(String(productId));
						releaseAddedProducts([String(productId)]);
						lastSnapshot = response.data || lastSnapshot;
						publishCartSnapshot(response.data);
						return response.data;
					});
				});
			});
			chain = chain.then(function () {
				// A hidden services section must never overwrite its saved session state.
				if (!hasModalServices) { return null; }
				activeOperation = { type: "services" };
				return postOp({ op: "pa_services", pa_services: serviceKeys.join(",") }).then(function (response) {
					activeOperation = null;
					if (!response || !response.success) {
						var message = response && response.data && response.data.message ? response.data.message : "ثبتِ خدمات انتخاب‌شده انجام نشد.";
						errors.push(message);
						return null;
					}
					lastSnapshot = response.data || lastSnapshot;
					publishCartSnapshot(response.data);
					return response.data;
				});
			});

			function finishFailure(message) {
				releaseAddedProducts(addedProductIds);
				setModalBusy(false);
				isCommitting = false;
				restorePaConfirm();
				clearSuggestedStatus();
				paUpdateTotal();
				showSuggestedError(message);
				return { success: false, addedProductIds: addedProductIds.slice(), snapshot: lastSnapshot };
			}

			return chain.then(function () {
				setModalBusy(false);
				isCommitting = false;
				restorePaConfirm();
				releaseAddedProducts(addedProductIds);
				clearSuggestedStatus();
				paUpdateTotal();
				if (errors.length) { return finishFailure(errors.join(" ")); }
				if (removeAfterCommit) { disposeSuggestedModal(); }
				else { closeSuggestedProductsModal(); }
				return { success: true, addedProductIds: addedProductIds.slice(), snapshot: lastSnapshot };
			}).catch(function () {
				var uncertainty = "ارتباط با سرور برقرار نشد؛ نتیجهٔ یکی از درخواست‌ها تأیید نشد. پیش از تلاشِ دوباره، سبد را بررسی کنید.";
				if (activeOperation && activeOperation.type === "add") {
					uncertainty = "ارتباط با سرور برقرار نشد؛ وضعیتِ افزودنِ این پیشنهاد مشخص نیست و ممکن است به سبد اضافه شده باشد. پیش از تلاشِ دوباره، سبد را بررسی کنید.";
				} else if (activeOperation && activeOperation.type === "services") {
					uncertainty = "ارتباط با سرور برقرار نشد؛ وضعیتِ ثبتِ خدمات مشخص نیست. پیش از تلاشِ دوباره، سبد را بررسی کنید.";
				}
				return finishFailure(errors.concat([uncertainty]).join(" "));
			});
		}

		window.jluxeCommitSuggestedSelection = function (targetModal, options) {
			if (targetModal && targetModal !== modal) { return Promise.resolve({ success: false, stale: true }); }
			return commitSelectedSuggestions(options);
		};
		window.jluxeSyncSuggestedModalCart = function (targetModal, snapshot) {
			if (targetModal && targetModal !== modal) { return; }
			updateSuggestedModalSnapshot(snapshot);
		};
		window.jluxeShowSuggestedAddWarning = function (targetModal, message) {
			if (targetModal && targetModal !== modal) { return; }
			showSuggestedError(message);
		};

		if (paConfirm && cartCfg && cartCfg.ajaxUrl) {
			paConfirm.addEventListener("click", function () { commitSelectedSuggestions(); });
		}
		modal.addEventListener("click", function (event) {
			if (isCommitting) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		}, true);
}

	jluxeBindSuggestedModal(document.querySelector("[data-jluxe-suggested-modal]"));

	window.jluxeCaptureSuggestedModalSelections = function (modal) {
		var selections = {};
		if (!modal) { return selections; }
		modal.querySelectorAll("[data-pa-service], [data-pa-product]").forEach(function (row) {
			var productId = row.getAttribute("data-pa-product");
			var key;
			if (productId) {
				key = "product:" + productId;
			} else {
				var serviceTitle = row.querySelector(".jluxe-pa-name");
				key = "service:" + row.getAttribute("data-pa-service") + ":" + (row.getAttribute("data-pa-amount") || "") + ":" + (serviceTitle ? serviceTitle.textContent.trim() : "");
			}
			selections[key] = row.getAttribute("aria-pressed") === "true";
		});
		return selections;
	};

	window.jluxeMountSuggestedModal = function (html, selectionState) {
		var old = document.querySelector("[data-jluxe-suggested-modal]");
		if (old && typeof old._jluxeClearSuggestedAutoCloseTimer === "function") {
			old._jluxeClearSuggestedAutoCloseTimer();
		}
		if (old && !old.classList.contains("hidden") && typeof window.jluxeCloseSuggestedProductsModal === "function") {
			window.jluxeCloseSuggestedProductsModal();
		}
		if (old && old.parentNode) { old.parentNode.removeChild(old); }
		var wrap = document.createElement("div");
		wrap.innerHTML = html;
		var fresh = wrap.firstElementChild;
		if (!fresh) { return null; }
		document.body.appendChild(fresh);
		jluxeBindSuggestedModal(fresh);
		if (selectionState && typeof selectionState === "object") {
			fresh.querySelectorAll("[data-pa-service], [data-pa-product]").forEach(function (row) {
				var productId = row.getAttribute("data-pa-product");
				var key;
				if (productId) {
					key = "product:" + productId;
				} else {
					var serviceTitle = row.querySelector(".jluxe-pa-name");
					key = "service:" + row.getAttribute("data-pa-service") + ":" + (row.getAttribute("data-pa-amount") || "") + ":" + (serviceTitle ? serviceTitle.textContent.trim() : "");
				}
				if (!Object.prototype.hasOwnProperty.call(selectionState, key)) { return; }
				var selected = !!selectionState[key];
				row.setAttribute("aria-pressed", selected ? "true" : "false");
				row.classList.toggle("is-selected", selected);
			});
			fresh.dispatchEvent(new Event("jluxe:pa-refresh"));
		}
		return fresh;
	};

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Escape") { return; }
		var current = document.querySelector("[data-jluxe-suggested-modal]");
		if (current && !current.classList.contains("hidden") && typeof window.jluxeCloseSuggestedProductsModal === "function") {
			window.jluxeCloseSuggestedProductsModal();
		}
	});
})();

/**
 * افزودن به سبد در فرمِ صفحه‌ی تکیِ محصول (woocommerce/single-product/add-to-cart/{simple,variation-add-to-cart-button}.php)
 * پیش‌فرضِ ووکامرس submit واقعیِ فرم (رفرش کامل صفحه) بود — بعدش همون نوتیسِ
 * رنگیِ قدیمیِ خودِ ووکامرس (wc_print_notices، از هوکِ woocommerce_before_single_product)
 * بالای صفحه ظاهر می‌شد که با توستِ سفارشیِ بالای همین فایل ناهماهنگه (باگ
 * گزارش‌شده — گاهی حتی همزمان با پیامِ موفقیتِ تکراری). اینجا submit رو
 * می‌گیریم و AJAX می‌فرستیم.
 *
 * باگِ واقعیِ بعدی (کاربر دقیقاً همین رو گزارش کرد — با یک سایتِ دیگه‌ای که
 * همین تمِ زرین رو داره، ولی چون کدش عیناً همینه، خودِ jluxe.ir هم موقعِ
 * لانچ می‌گرفتش): این‌جا قبلاً مستقیم به endpoint خامِ ووکامرس
 * (wc-ajax=add_to_cart) می‌زد و برای محصولِ متغیر، product_id رو با
 * variation_id عوض می‌کرد (استدلال: «endpoint خودش تشخیص می‌ده»). آیتم
 * واقعاً درست به سبد اضافه می‌شد (session سمتِ سرور mutate می‌شد) ولی
 * WC_AJAX::add_to_cart بعدش status رو هنوز روی همون ID (که تنوعه، نه
 * والد) چک می‌کرد و {error:true} برمی‌گردوند — یعنی کاربر توستِ خطا
 * («لطفاً گزینه‌های محصول را انتخاب کنید») می‌دید ولی با رفتن به صفحه‌ی
 * دیگه می‌دید واقعاً به سبد اضافه شده. راه‌حل: به‌جایِ endpoint خامِ
 * ووکامرس، از endpoint خودمون (inc/cart-ux.php: jluxe_ajax_cart_add،
 * op=add) استفاده می‌کنیم که product_id (والد) و variation_id رو جدا جدا
 * می‌فرسته و دقیقاً امضای ۴تاییِ استانداردِ WC()->cart->add_to_cart() رو
 * صدا می‌زنه — بدونِ حدس‌زدن.
 */
(function () {
	var form = document.querySelector("form.cart");
	var cartCfg = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
	if (!form || !cartCfg || !cartCfg.ajaxUrl || !window.jQuery) {
		return;
	}
	/*
	 * باگِ واقعیِ دیگه‌ای که همینِ‌جا کشف شد (گزارشِ کاربر: «پاپ‌آپِ
	 * محصولاتِ پیشنهادی فقط برایِ محصولِ ساده میاد، برایِ متغیر نه»):
	 * قبلاً این‌جا form.querySelector('[name="add-to-cart"]') رو یک‌بار،
	 * همین اول (موقعِ لودِ صفحه) می‌خوند و اگه null بود (خیلی معمول برایِ
	 * محصولِ متغیر — دکمه‌ی افزودن اصلاً توی DOM نیست تا وقتی کاربر یک
	 * تنوعِ معتبر انتخاب کنه؛ فقط اگه ووکامرس مقدارِ پیش‌فرضی برایِ
	 * ویژگی‌ها تنظیم کرده باشه از اول توی DOMه) کلِ IIFE (بدونِ اتصالِ هیچ
	 * listenerی) return می‌کرد — یعنی برایِ اکثرِ محصولاتِ متغیر، این
	 * فایل هیچ‌وقت submit رو نمی‌گرفت، حتی بعدِ اینکه کاربر بعداً یک
	 * تنوع انتخاب می‌کرد؛ فرم با submit خامِ خودِ مرورگر (رفرشِ کامل
	 * صفحه) می‌رفت، پس نه AJAX ما نه توست نه پاپ‌آپِ پیشنهادی هیچ‌کدوم
	 * اجرا نمی‌شدن (هرچند خودِ افزودن به سبد از راهِ رفرشِ کامل درست
	 * کار می‌کرد — برای همین کاربر خطایی نمی‌دید، فقط پاپ‌آپ رو نمی‌دید).
	 * الان listener مستقیم رو خودِ form وصل می‌شه (که همیشه توی DOMه)،
	 * و دکمه/product_id هر بار توی خودِ submit (وقتی حتماً یک تنوعِ
	 * معتبر انتخاب شده و دکمه حتماً ساخته شده) دوباره خونده می‌شن.
	 */

	// پیام‌های خطای خودِ ووکامرس (مثلاً «...&mdash; ما مجموعا...») نویسه‌ی
	// HTML خام (نه یک خط‌تیره‌ی واقعی) دارن — چون این متن‌ها برای چاپِ مستقیمِ
	// HTML (wc_print_notices) نوشته شدن. با textContent decode نمی‌شن (باگِ
	// واقعیِ گزارش‌شده: کاربر عیناً «&mdash;» رو روی توست می‌دید)، پس این‌جا
	// یک بار از مرورگر خودش decode گرفته می‌شه.
	function decodeHtmlEntities(text) {
		var el = document.createElement("textarea");
		el.innerHTML = text;
		return el.value;
	}

	function showErrorToast(message) {
		var existing = document.querySelector(".jluxe-toast");
		if (existing) {
			existing.remove();
		}
		var toast = document.createElement("div");
		toast.className = "jluxe-toast jluxe-toast-error";
		toast.innerHTML =
			'<span class="jluxe-toast-icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>' +
			"</span>" +
			'<span class="jluxe-toast-msg"></span>';
		toast.querySelector(".jluxe-toast-msg").textContent = decodeHtmlEntities(message);
		document.body.appendChild(toast);
		requestAnimationFrame(function () {
			toast.classList.add("jluxe-toast-visible");
		});
		window.setTimeout(function () {
			toast.classList.remove("jluxe-toast-visible");
			window.setTimeout(function () {
				toast.remove();
			}, 250);
		}, 4500);
	}

	form.addEventListener("submit", function (event) {
		var button = form.querySelector('[name="add-to-cart"], .single_add_to_cart_button');
		if (button && button.disabled) {
			event.preventDefault();
			return;
		}
		event.preventDefault();
		if (button) {
			button.disabled = true;
			button.classList.add("opacity-60", "pointer-events-none");
		}

		function restoreButton() {
			if (button) {
				button.disabled = false;
				button.classList.remove("opacity-60", "pointer-events-none");
			}
		}

		// آیدیِ والدِ محصول: تا این‌جا (داخلِ submit) حتماً یک تنوعِ معتبر
		// انتخاب شده، پس button (که همین بالا از رویِ خودِ فرم دوباره
		// خونده شد) حتماً وجود داره و button.value همیشه آیدیِ واقعیِ
		// محصولِ والده (چه ساده چه متغیر — قالبِ ووکامرس این‌جوری
		// می‌سازتش). form.dataset.product_id فقط fallback برایِ حالتِ
		// نادرِ نبودِ دکمه‌ست (content-single-product.php: data-product_id
		// رویِ خودِ <form> — نامِ attribute زیرخط داره نه خط‌تیره، پس
		// dataset اسمش رو camelCase نمی‌کنه، دقیقاً product_id می‌مونه).
		// variation_id (اگه محصول متغیره) از فیلدِ جداگونه‌ی خودِ فرم میاد،
		// همون فیلدی که اسکریپتِ سواچِ ووکامرس (wc-add-to-cart-variation)
		// موقعِ انتخابِ یک تنوعِ معتبر مقدارش رو ست می‌کنه.
		var formData = new FormData(form);
		// Keep the native field in the DOM, but never send it to our AJAX protocol.
		formData.delete("add-to-cart");
		formData.set("action", "jluxe_cart");
		formData.set("nonce", cartCfg.nonce);
		formData.set("op", "add");
		formData.set("product_id", (button && button.value) || form.dataset.product_id || "");

		window.jluxeCartPost(cartCfg.ajaxUrl, formData, cartCfg)
			.then(function (response) {
				restoreButton();
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : "لطفاً گزینه‌های محصول را انتخاب کنید.";
					showErrorToast(message);
					return;
				}
				window.jQuery(document.body).trigger("added_to_cart", [null, null, window.jQuery(button), response.data]);
				/* The shared success handler mounts and opens only the fresh, enabled server suggestions. */
			})
			.catch(function () {
				restoreButton();
				showErrorToast("خطا در افزودن به سبد خرید. دوباره تلاش کنید.");
			});
	});
})();

/**
 * مودالِ انتخاب سریعِ تنوع — کلیک روی [data-jluxe-quick-variant] (دکمه‌ی
 * افزودن سریع/دکمه‌ی پایین کارت برای محصولِ متغیر، woocommerce/
 * content-product.php) به‌جای رفتن به صفحه‌ی محصول، سواچ‌ها + جعبه‌ی
 * خرید همون محصول رو (inc/cart-ux.php: jluxe_ajax_variation_picker) در
 * یک مودال باز می‌کنه. فرمِ داخلِ مودال با submit عادی navigate می‌کنه
 * (single_add_to_cart_button کلاسِ ajax_add_to_cart نداره)، پس submit
 * دستی گرفته و به endpoint امنِ پوسته برای افزودن به سبد
 * (jluxe_cart با op=add) فرستاده می‌شود.
 */
(function () {
	var modalRoot = null;
	var lastFocused = null;
	var releaseDialogFocus = null;
	/* باز شدنِ picker از پیشنهاد، مودالِ زیرین را می‌بندد تا بک‌دراپ‌ها
	روی‌هم نیفتند. لغو/خطا شیت را برمی‌گرداند؛ پس از افزودنِ موفق، انتخاب‌های
	ثابت/خدماتِ تیک‌خورده نیز در همان مسیر خرید ثبت می‌شوند. */
	var pickerFromSuggested = false;
	var pickerAdded = false;
	var pickerAddInProgress = false;
	var pickerSuggestedContext = "";
	var pickerProductId = "";
	var pickerTriggerEl = null;
	var pickerUncertaintyMessage = "";

	function closeModal() {
		if (pickerAddInProgress) { return; }
		if (!modalRoot) {
			return;
		}
		if (releaseDialogFocus) { releaseDialogFocus(); releaseDialogFocus = null; }
		modalRoot.remove();
		modalRoot = null;
		document.body.style.overflow = "";
		if (!pickerFromSuggested && lastFocused && lastFocused.focus) {
			lastFocused.focus();
		}
		if (pickerFromSuggested && !pickerAdded) {
			var staleSuggested = document.querySelector("[data-jluxe-suggested-modal]");
			if (staleSuggested && typeof window.jluxeOpenSuggestedProductsModal === "function") {
				window.jluxeOpenSuggestedProductsModal();
			}
		}
		// A successful recommendation add keeps the old sheet alive briefly so
		// its checked fixed products/services can be committed as one selection.
		pickerFromSuggested = false;
		pickerAdded = false;
		pickerAddInProgress = false;
		pickerSuggestedContext = "";
		pickerProductId = "";
		pickerTriggerEl = null;
		pickerUncertaintyMessage = "";
	}

	function openModal(productId, triggerEl) {
		var cart = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		if (!cart || !cart.ajaxUrl) {
			return;
		}
		// جلوگیری از باگِ واقعیِ گزارش‌شده («صفحه هی مشکی و تیره‌تر می‌شه»):
		// اگه از قبل یک نمونه از همین مودال باز مونده (مثلاً کاربر دوبار
		// پشتِ‌هم روی [data-jluxe-quick-variant] زده)، اول بسته می‌شه — وگرنه
		// modalRoot قبلی هیچ‌وقت remove نمی‌شد و بک‌دراپِ ۵۰٪-سیاهش زیرِ
		// نمونه‌ی جدید می‌موند، با هر کلیک یک لایه‌ی تیره‌ی دیگه روی هم.
		if (modalRoot) {
			if (pickerAddInProgress) { return; }
			pickerFromSuggested = false;
			pickerAdded = false;
			closeModal();
		}
		pickerFromSuggested = !!(triggerEl && triggerEl.closest && triggerEl.closest("[data-jluxe-suggested-modal]"));
		pickerAdded = false;
		pickerAddInProgress = false;
		pickerSuggestedContext = "";
		pickerProductId = String(productId || "");
		pickerTriggerEl = triggerEl || null;
		pickerUncertaintyMessage = triggerEl && triggerEl.getAttribute("data-jluxe-add-uncertain") === "1"
			? (triggerEl.getAttribute("data-jluxe-add-uncertain-message") || "وضعیتِ افزودنِ قبلی مشخص نیست؛ پیش از تلاشِ دوباره، سبد را بررسی کنید.")
			: "";
		if (pickerFromSuggested) {
			var suggestedSource = triggerEl.closest("[data-jluxe-suggested-modal]");
			pickerSuggestedContext = suggestedSource.getAttribute("data-pa-context") || "";
		}
		// If this picker was opened from the suggested-products sheet, close
		// the sheet first so the two full-screen backdrops never stack. The
		// picker has its own higher overlay layer and focus trap.
		if (typeof window.jluxeCloseSuggestedProductsModal === "function") {
			window.jluxeCloseSuggestedProductsModal();
		}

		lastFocused = triggerEl || document.activeElement;
		modalRoot = document.createElement("div");
		modalRoot.className = "jluxe-variant-modal-backdrop";
		if (pickerFromSuggested) { modalRoot.setAttribute("data-jluxe-from-suggested", "1"); }
		modalRoot.innerHTML =
			'<div class="jluxe-variant-modal" role="dialog" aria-modal="true" aria-label="انتخاب گزینه‌های محصول">' +
			'<button type="button" class="jluxe-variant-modal-close" aria-label="بستن">' +
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
			"</button>" +
			'<div class="jluxe-variant-modal-head"></div>' +
			'<div class="jluxe-variant-modal-body"><div class="jluxe-variant-modal-loading">در حال بارگذاری...</div></div>' +
			"</div>";
		document.body.appendChild(modalRoot);
		releaseDialogFocus = JLuxeStorefrontUtils.activateDialog(modalRoot.querySelector("[role=dialog]"), closeModal);
		document.body.style.overflow = "hidden";

		modalRoot.addEventListener("click", function (event) {
			if (event.target === modalRoot || event.target.closest(".jluxe-variant-modal-close")) {
				closeModal();
			}
		});

		var requestedModal = modalRoot;
		var body = new URLSearchParams({ action: "jluxe_variation_picker", nonce: cart.nonce, product_id: String(productId) });
		window.jluxeCartPost(cart.ajaxUrl, body, cart)
			.then(function (response) {
				if (!modalRoot || modalRoot !== requestedModal) {
					return;
				}
				if (!response.success) {
					modalRoot.querySelector(".jluxe-variant-modal-body").innerHTML =
						'<p class="jluxe-variant-modal-error">مشکلی پیش آمد، دوباره تلاش کنید.</p>';
					return;
				}
				var data = response.data;
				modalRoot.querySelector(".jluxe-variant-modal-head").innerHTML =
					'<img src="' + data.image + '" alt="" class="jluxe-variant-modal-img" />' +
					'<a href="' + data.url + '" class="jluxe-variant-modal-title">' + data.name + "</a>";
				var pickerBody = modalRoot.querySelector(".jluxe-variant-modal-body");
				pickerBody.innerHTML = data.html;
				if (pickerUncertaintyMessage) {
					var uncertaintyNotice = document.createElement("p");
					uncertaintyNotice.className = "jluxe-variant-modal-error";
					uncertaintyNotice.setAttribute("role", "alert");
					uncertaintyNotice.textContent = pickerUncertaintyMessage;
					pickerBody.prepend(uncertaintyNotice);
				}
				/* R65: مودالِ تازه‌تزریق‌شده هم بلافاصله گیتِ «ناموجود = غیرقابل‌انتخاب» می‌گیرد. */
				if (typeof window.jluxeSyncAllVariationForms === "function") { window.jluxeSyncAllVariationForms(); }

				var form = modalRoot.querySelector("form.variations_form");
				if (form && window.jQuery && window.jQuery.fn.wc_variation_form) {
					window.jQuery(form).wc_variation_form();
					jluxeBindPriceBox(form);
					jluxeBindQtyAvailability(form);
				}
			})
			.catch(function () {
				if (modalRoot && modalRoot === requestedModal) {
					modalRoot.querySelector(".jluxe-variant-modal-body").innerHTML =
						'<p class="jluxe-variant-modal-error">مشکلی پیش آمد، دوباره تلاش کنید.</p>';
				}
			});
	}

	document.addEventListener("click", function (event) {
		var trigger = event.target.closest("[data-jluxe-quick-variant]");
		if (!trigger) {
			return;
		}
		event.preventDefault();
		openModal(trigger.getAttribute("data-jluxe-quick-variant"), trigger);
	});

	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape" && modalRoot) {
			closeModal();
		}
	});

	document.addEventListener("submit", function (event) {
		var form = event.target.closest(".jluxe-variant-modal form.variations_form");
		if (!form) {
			return;
		}
		event.preventDefault();
		if (pickerAddInProgress) { return; }

		var addButton = form.querySelector(".single_add_to_cart_button");
		if (addButton && addButton.classList.contains("disabled")) {
			return;
		}

		var cart = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cart;
		if (!cart || !cart.ajaxUrl) {
			return;
		}

		/*
		 * همون باگِ خامِ wc-ajax=add_to_cart که در فرمِ صفحه‌ی تکیِ محصول
		 * پیدا شد این‌جا هم بود — با یک شکلِ حتی بدتر: این فرم (خروجیِ
		 * jluxe_ajax_variation_picker در inc/cart-ux.php، کپیِ ساختارِ
		 * variable.php خودِ ووکامرس) اصلاً input جدایی به نامِ product_id
		 * نداره، فقط data-product_id روی خودِ <form> (که FormData ازش چیزی
		 * نمی‌خونه، چون attribute دیتاسته نه فیلدِ فرم). یعنی $_POST['product_id']
		 * سمتِ سرور اصلاً ست نمی‌شد و WC_AJAX::add_to_cart بی‌سروصدا wp_die(0)
		 * می‌کرد — پاسخِ خام "0" هم چون هیچ‌وقت response.error نداره، کدِ قبلی
		 * این رو موفقیت حساب می‌کرد و مودال رو می‌بست، در حالی که هیچ‌چیزی
		 * واقعاً به سبد اضافه نشده بود. حالا از همون endpoint خودمون
		 * (jluxe_ajax_cart_add) استفاده می‌کنیم که product_id/variation_id
		 * رو جدا جدا و صریح می‌گیره.
		 */
		var formData = new FormData(form);
		// Keep the native field in the DOM, but never send it to our AJAX protocol.
		formData.delete("add-to-cart");
		formData.set("action", "jluxe_cart");
		formData.set("nonce", cart.nonce);
		formData.set("op", "add");
		formData.set("product_id", form.dataset.product_id || "");
		if (pickerFromSuggested && pickerSuggestedContext) {
			formData.set("pa_context_id", pickerSuggestedContext);
		}

		pickerAddInProgress = true;
		if (addButton) {
			addButton.disabled = true;
			addButton.setAttribute("aria-busy", "true");
			addButton.classList.add("jluxe-loading");
		}
		function restorePickerAdd(keepSubmitGuard) {
			if (!keepSubmitGuard) { pickerAddInProgress = false; }
			if (addButton) {
				addButton.disabled = false;
				addButton.removeAttribute("aria-busy");
				addButton.classList.remove("jluxe-loading");
			}
		}

		function showPickerError(message) {
			var errorEl = modalRoot && modalRoot.querySelector(".jluxe-variant-modal-error");
			if (!errorEl && modalRoot) {
				errorEl = document.createElement("p");
				errorEl.className = "jluxe-variant-modal-error";
				errorEl.setAttribute("role", "alert");
				var formBody = modalRoot.querySelector(".jluxe-variant-modal-body");
				if (formBody) { formBody.prepend(errorEl); }
			}
			if (errorEl) {
				var decoder = document.createElement("textarea");
				decoder.innerHTML = String(message || "افزودن انجام نشد؛ دوباره تلاش کنید.");
				errorEl.textContent = decoder.value;
			}
		}

		window.jluxeCartPost(cart.ajaxUrl, formData, cart)
			.then(function (response) {
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : "لطفاً یک گزینه انتخاب کنید.";
					restorePickerAdd();
					showPickerError(message);
					return;
				}

				var wasFromSuggested = pickerFromSuggested;
				var suggestedModal = wasFromSuggested ? document.querySelector("[data-jluxe-suggested-modal]") : null;
				var addedProductId = String(form.dataset.product_id || pickerProductId || "");
				var snapshot = response.data || null;
				restorePickerAdd(true);
				if (window.jQuery) {
					window.jQuery(document.body).trigger("added_to_cart", [null, null, window.jQuery(addButton), snapshot]);
				} else if (snapshot) {
					window.dispatchEvent(new CustomEvent("jluxe:cart-updated", { detail: snapshot }));
				}
				if (pickerTriggerEl) {
					pickerTriggerEl.removeAttribute("data-jluxe-add-uncertain");
					pickerTriggerEl.removeAttribute("data-jluxe-add-uncertain-message");
				}
				pickerAdded = true;
				pickerAddInProgress = false;
				closeModal();

				if (wasFromSuggested && suggestedModal && suggestedModal.isConnected) {
					suggestedModal.setAttribute("data-pa-variation-committed", "1");
					suggestedModal.querySelectorAll("[data-jluxe-quick-variant]").forEach(function (row) {
						if (row.getAttribute("data-jluxe-quick-variant") === addedProductId) { row.remove(); }
					});
					if (typeof window.jluxeSyncSuggestedModalCart === "function") {
						window.jluxeSyncSuggestedModalCart(suggestedModal, snapshot);
					}
					if (typeof window.jluxeCommitSuggestedSelection === "function") {
						window.jluxeCommitSuggestedSelection(suggestedModal, {
							source: "variation",
							openOnStart: true,
							removeOnSuccess: true,
							removeWhenEmpty: true,
						});
					} else if (typeof window.jluxeOpenSuggestedProductsModal === "function") {
						// Fallback keeps selected offers visible rather than discarding them.
						window.jluxeOpenSuggestedProductsModal();
					}
				}
			})
			.catch(function () {
				restorePickerAdd();
				var uncertainty = "ارتباط با سرور برقرار نشد؛ وضعیتِ افزودنِ این تنوع مشخص نیست و ممکن است به سبد اضافه شده باشد. پیش از تلاشِ دوباره، سبد را بررسی کنید.";
				pickerUncertaintyMessage = uncertainty;
				if (pickerTriggerEl) {
					pickerTriggerEl.setAttribute("data-jluxe-add-uncertain", "1");
					pickerTriggerEl.setAttribute("data-jluxe-add-uncertain-message", uncertainty);
				}
				if (pickerFromSuggested) {
					var suggestedModal = document.querySelector("[data-jluxe-suggested-modal]");
					if (suggestedModal && typeof window.jluxeShowSuggestedAddWarning === "function") {
						window.jluxeShowSuggestedAddWarning(suggestedModal, uncertainty);
					}
				}
				showPickerError(uncertainty);
			});
	});
})();

/**
 * توضیحاتِ کوتاهِ محصول (content-single-product.php، زیرِ «ویژگی‌های کلیدی»):
 * ۳ خط با CSS کلمپ می‌شه؛ این‌جا فقط اگه واقعاً بیشتر از ۳ خط باشه (scrollHeight
 * بزرگ‌تر از clientHeight) دکمه‌ی «بیشتر» نشون داده می‌شه — طبق درخواستِ کاربر
 * («اگه توضیحات کوتاه زیاد بود... از یه جایی سه‌نقطه بزنه، با کلیکِ بیشتر
 * بقیه رو نشون بده»)، نه همیشه/بی‌ربط به طول واقعیِ متن.
 */
(function () {
	document.querySelectorAll("[data-jluxe-short-desc]").forEach(function (wrap) {
		var text = wrap.querySelector("[data-jluxe-short-desc-text]");
		var toggle = wrap.querySelector("[data-jluxe-short-desc-toggle]");
		if (!text || !toggle) {
			return;
		}
		if (text.scrollHeight - text.clientHeight <= 1) {
			return;
		}
		toggle.classList.remove("hidden");
		toggle.addEventListener("click", function () {
			var expanded = text.classList.toggle("jluxe-clamp-expanded");
			toggle.textContent = expanded ? "کمتر" : "بیشتر";
		});
	});
})();

/**
 * سینکِ لایه‌ی رقمِ فارسیِ روی input[type=number].qty (تعداد قابل‌تغییر —
 * quantity-input.php + globals.css). چون ممکنه input دیرتر (مودالِ
 * افزودنِ سریع، AJAX) به DOM اضافه بشه، به‌جای querySelectorAll یک‌باره
 * روی document با delegation گوش می‌دیم — هر inputای که بعداً هم اضافه
 * بشه پوشش داده می‌شه. دو رویداد لازمه: "input" برای تایپِ دستی/اسپینرِ
 * بومیِ مرورگر، "change" برای وقتی دکمه‌های +/- بالای همین فایل مقدار رو
 * با جاوااسکریپت ست می‌کنن و change رو dispatch می‌کنن (که خودش رویدادِ
 * input رو تولید نمی‌کنه).
 */
(function () {
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };
	function toFaDigits(value) {
		return String(value).replace(/[0-9]/g, function (d) {
			return faDigitsMap[d];
		});
	}
	function syncOverlay(input) {
		var wrap = input.closest("[data-jluxe-qty-fa-wrap]");
		var overlay = wrap && wrap.querySelector("[data-jluxe-qty-fa]");
		if (overlay) {
			overlay.textContent = toFaDigits(input.value || "0");
		}
	}
	["input", "change"].forEach(function (eventName) {
		document.addEventListener(eventName, function (event) {
			if (event.target.matches && event.target.matches(".jluxe-qty-fa-input")) {
				syncOverlay(event.target);
			}
		});
	});
})();

/**
 * کنترلِ تعدادِ نوارهای چسبانِ موبایل (چیدمانِ پیش‌فرض و classic).
 * نمایشگر و دکمه‌ها فقط proxy هستند؛ تنها input[name=quantity] داخلِ فرمِ
 * واقعیِ cart تغییر می‌کند. برایِ تنوع، min_qty/max_qty و فروشِ تکی از
 * همان payload رویدادِ رسمیِ found_variation ووکامرس خوانده می‌شود.
 */
(function ($) {
	var controls = document.querySelectorAll("[data-jluxe-mobile-qty-control]");
	var form = document.querySelector("form.cart");
	if (!controls.length || !form) {
		return;
	}

	var isVariableForm = form.classList.contains("variations_form");
	var activeVariation = null;
	var writingQuantity = false;
	var epsilon = 0.00000001;
	var faDigitsMap = { "0": "۰", "1": "۱", "2": "۲", "3": "۳", "4": "۴", "5": "۵", "6": "۶", "7": "۷", "8": "۸", "9": "۹" };

	function quantityInput() {
		return form.querySelector(".quantity input.qty") || form.querySelector("input.qty") || form.querySelector('input[name="quantity"]');
	}

	function numberOr(value, fallback) {
		if (value === null || value === undefined || value === "") {
			return fallback;
		}
		var number = Number(value);
		return Number.isFinite(number) ? number : fallback;
	}

	function hasOwn(object, key) {
		return !!object && Object.prototype.hasOwnProperty.call(object, key);
	}

	function isSoldIndividually(variation) {
		return !!variation && (variation.is_sold_individually === true || variation.is_sold_individually === 1 || variation.is_sold_individually === "1" || variation.is_sold_individually === "yes");
	}

	function quantityRules(input) {
		var min = numberOr(input.getAttribute("min"), 0);
		var max = numberOr(input.getAttribute("max"), Infinity);
		if (max <= 0) {
			max = Infinity;
		}
		var step = numberOr(input.getAttribute("step"), 1);
		if (!(step > 0)) {
			step = 1;
		}

		if (activeVariation) {
			if (hasOwn(activeVariation, "min_qty")) {
				min = numberOr(activeVariation.min_qty, min);
			}
			if (hasOwn(activeVariation, "max_qty")) {
				var variationMax = numberOr(activeVariation.max_qty, Infinity);
				max = variationMax > 0 ? variationMax : Infinity;
			}
			if (isSoldIndividually(activeVariation)) {
				min = 1;
				max = 1;
			}
		}

		return {
			min: min,
			max: max,
			step: step,
			fixed: input.readOnly || max <= min + epsilon,
			valid: max + epsilon >= min,
		};
	}

	function variationIsPurchasable() {
		if (!activeVariation) {
			return true;
		}
		return activeVariation.is_purchasable !== false && activeVariation.is_in_stock !== false && activeVariation.variation_is_visible !== false;
	}

	function isReady(input) {
		if (!input || input.disabled) {
			return false;
		}
		if (!isVariableForm) {
			return true;
		}

		var variationIdInput = form.querySelector("input.variation_id");
		var variationId = variationIdInput ? parseInt(variationIdInput.value, 10) : 0;
		if (!(variationId > 0) && !(activeVariation && parseInt(activeVariation.variation_id, 10) > 0)) {
			return false;
		}
		var addButton = form.querySelector(".single_add_to_cart_button");
		if (!addButton || addButton.disabled || addButton.classList.contains("disabled") || addButton.classList.contains("wc-variation-selection-needed")) {
			return false;
		}
		return variationIsPurchasable();
	}

	function decimalPlaces(value) {
		var text = String(Math.abs(value));
		var dot = text.indexOf(".");
		return dot < 0 ? 0 : Math.min(8, text.length - dot - 1);
	}

	function roundQuantity(value, rules) {
		var places = Math.max(decimalPlaces(rules.min), decimalPlaces(rules.step));
		if (Number.isFinite(rules.max)) {
			places = Math.max(places, decimalPlaces(rules.max));
		}
		var scale = Math.pow(10, Math.min(8, places));
		return Math.round((value + Number.EPSILON) * scale) / scale;
	}

	function normalizeQuantity(value, rules) {
		/* max_qty is only an availability ceiling; keep the user's actual
		 * quantity (normally one) unless it falls outside WooCommerce bounds. */
		if (!Number.isFinite(value)) {
			value = rules.min > 0 ? rules.min : 1;
		}
		value = Math.min(rules.max, Math.max(rules.min, value));
		value = roundQuantity(value, rules);
		return Math.min(rules.max, Math.max(rules.min, value));
	}

	function toFaDigits(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return faDigitsMap[digit];
		});
	}

	function setSourceQuantity(input, value) {
		var current = numberOr(input.value, NaN);
		if (Number.isFinite(current) && Math.abs(current - value) <= epsilon) {
			return;
		}
		writingQuantity = true;
		try {
			input.value = String(value);
			input.dispatchEvent(new Event("input", { bubbles: true }));
			input.dispatchEvent(new Event("change", { bubbles: true }));
		} finally {
			writingQuantity = false;
		}
	}

	function setControlVisible(control, visible) {
		control.hidden = !visible;
		control.style.display = visible ? "" : "none";
	}

	function refresh(clampValue) {
		var input = quantityInput();
		if (!input || !isReady(input)) {
			for (var hiddenIndex = 0; hiddenIndex < controls.length; hiddenIndex++) {
				setControlVisible(controls[hiddenIndex], false);
			}
			return;
		}

		var rules = quantityRules(input);
		if (!rules.valid) {
			for (var invalidIndex = 0; invalidIndex < controls.length; invalidIndex++) {
				setControlVisible(controls[invalidIndex], false);
			}
			return;
		}

		var value = numberOr(input.value, NaN);
		if (clampValue) {
			value = normalizeQuantity(value, rules);
			setSourceQuantity(input, value);
		} else if (!Number.isFinite(value)) {
			value = rules.min > 0 ? rules.min : 1;
		}

		for (var controlIndex = 0; controlIndex < controls.length; controlIndex++) {
			var control = controls[controlIndex];
			setControlVisible(control, true);
			var output = control.querySelector("[data-jluxe-mobile-qty-value]");
			var decrease = control.querySelector('[data-jluxe-mobile-qty-step="decrease"]');
			var increase = control.querySelector('[data-jluxe-mobile-qty-step="increase"]');
			if (output) {
				output.textContent = toFaDigits(value);
			}
			if (decrease) {
				decrease.disabled = rules.fixed || input.readOnly || value <= rules.min + epsilon;
			}
			if (increase) {
				increase.disabled = rules.fixed || input.readOnly || value >= rules.max - epsilon;
			}
		}
	}

	function findPreselectedVariation() {
		if (!isVariableForm) {
			return null;
		}
		var variationIdInput = form.querySelector("input.variation_id");
		var selectedId = variationIdInput ? parseInt(variationIdInput.value, 10) : 0;
		var raw = form.getAttribute("data-product_variations") || "";
		if (!(selectedId > 0) || !raw || raw === "false") {
			return null;
		}
		try {
			var variations = JSON.parse(raw);
			if (Array.isArray(variations)) {
				for (var variationIndex = 0; variationIndex < variations.length; variationIndex++) {
					if (parseInt(variations[variationIndex].variation_id, 10) === selectedId) {
						return variations[variationIndex];
					}
				}
			}
		} catch (error) {
			return null;
		}
		return null;
	}

	for (var controlIndex = 0; controlIndex < controls.length; controlIndex++) {
		controls[controlIndex].addEventListener("click", function (event) {
			var button = event.target.closest("[data-jluxe-mobile-qty-step]");
			if (!button || !event.currentTarget.contains(button) || button.disabled) {
				return;
			}
			event.preventDefault();
			var input = quantityInput();
			if (!isReady(input)) {
				refresh(false);
				return;
			}
			var rules = quantityRules(input);
			if (!rules.valid || rules.fixed || input.readOnly) {
				refresh(true);
				return;
			}
			var current = numberOr(input.value, rules.min > 0 ? rules.min : 1);
			var direction = button.getAttribute("data-jluxe-mobile-qty-step") === "increase" ? 1 : -1;
			var next = normalizeQuantity(current + direction * rules.step, rules);
			if (Math.abs(next - current) <= epsilon) {
				refresh(true);
				return;
			}
			setSourceQuantity(input, next);
			refresh(true);
		});
	}

	document.addEventListener("input", function (event) {
		if (!writingQuantity && event.target === quantityInput()) {
			refresh(false);
		}
	});
	document.addEventListener("change", function (event) {
		if (!writingQuantity && event.target === quantityInput()) {
			refresh(true);
		}
	});

	if (isVariableForm) {
		activeVariation = findPreselectedVariation();
		if ($) {
			$(form).on("found_variation.jluxeMobileQty", function (event, variation) {
				activeVariation = variation || null;
				refresh(true);
			});
			$(form).on("reset_data.jluxeMobileQty hide_variation.jluxeMobileQty woocommerce_variation_has_changed.jluxeMobileQty", function () {
				activeVariation = null;
				refresh(false);
				window.setTimeout(function () { refresh(true); }, 0);
			});
		}
		form.addEventListener("change", function (event) {
			if (event.target !== quantityInput() && !event.target.matches(".variation_id")) {
				activeVariation = null;
				refresh(false);
				window.setTimeout(function () { refresh(true); }, 0);
			}
		});
	}

	refresh(true);
	window.setTimeout(function () { refresh(true); }, 0);
})(window.jQuery);

/**
 * نوارِ چسبانِ موبایلِ قیمت+افزودن‌به‌سبدِ صفحه‌ی محصول (الگوی Boom) —
 * woocommerce/content-single-product.php این نوار رو رندر می‌کنه؛ این‌جا
 * فقط رفتارش وصل می‌شه: دکمه‌ی افزودن دکمه‌ی واقعیِ فرم رو کلیک می‌کنه،
 * دکمه‌ی toggle یک رویدادِ jluxe:toggle-mobile-bar می‌فرسته که هم خودِ
 * این نوار (کلاسِ hidden) هم MobileNav (React، state داخلیِ خودش) گوش می‌ده.
 * رویداد حالتِ صریح می‌فرسته، نه toggle؛ خودِ React هم هنگامِ mount و
 * pageshow دوباره state را از DOM می‌خواند تا tap پیش از hydration گم نشود.
 * اگر dock قدیمیِ #bottom-navigation روی صفحه حاضر باشد، هنگامِ نمایشِ
 * نوارِ خرید موقتاً کنار می‌رود و در حالتِ منو با وضعیتِ اصلی‌اش برمی‌گردد.
 */
(function () {
	var priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");
	var toggleBtn = document.querySelector("[data-jluxe-mobile-bar-toggle]");
	if (!priceBar || !toggleBtn) {
		return;
	}
	var addBtn = priceBar.querySelector("[data-jluxe-mobile-bar-add]");
	var yieldedBottomNavigation = [];

	function bottomNavigationStateIndex(dock) {
		for (var stateIndex = 0; stateIndex < yieldedBottomNavigation.length; stateIndex++) {
			if (yieldedBottomNavigation[stateIndex].dock === dock) { return stateIndex; }
		}
		return -1;
	}

	function setBottomNavigationYielded(yielded) {
		var docks = document.querySelectorAll("#bottom-navigation");
		for (var dockIndex = 0; dockIndex < docks.length; dockIndex++) {
			var dock = docks[dockIndex];
			var dockStateIndex = bottomNavigationStateIndex(dock);
			if (yielded) {
				if (-1 === dockStateIndex) {
					var dockStyle = window.getComputedStyle(dock);
					var mobileViewport = window.innerWidth < 768;
					var available = !dock.hidden && !dock.classList.contains("hidden") && dock.style.display !== "none" && dock.getAttribute("aria-hidden") !== "true" && !dock.hasAttribute("inert") && !!dock.querySelector("a[href], button, [role='link'], [role='button']") && (!mobileViewport || (dockStyle.display !== "none" && dockStyle.visibility !== "hidden"));
					yieldedBottomNavigation.push({
						dock: dock,
						hidden: dock.hidden,
						ariaHidden: dock.getAttribute("aria-hidden"),
						inert: dock.hasAttribute("inert"),
						available: available,
						availabilityAttribute: dock.getAttribute("data-jluxe-mobile-bar-available"),
					});
					dock.setAttribute("data-jluxe-mobile-bar-available", available ? "true" : "false");
				}
				dock.setAttribute("data-jluxe-mobile-bar-yielded", "");
				dock.hidden = true;
				dock.setAttribute("aria-hidden", "true");
				dock.setAttribute("inert", "");
				continue;
			}
			if (-1 === dockStateIndex) { continue; }
			var original = yieldedBottomNavigation[dockStateIndex];
			dock.removeAttribute("data-jluxe-mobile-bar-yielded");
			dock.hidden = original.hidden;
			if (null === original.ariaHidden) { dock.removeAttribute("aria-hidden"); }
			else { dock.setAttribute("aria-hidden", original.ariaHidden); }
			if (!original.inert) { dock.removeAttribute("inert"); }
			if (null === original.availabilityAttribute) { dock.removeAttribute("data-jluxe-mobile-bar-available"); }
			else { dock.setAttribute("data-jluxe-mobile-bar-available", original.availabilityAttribute); }
			yieldedBottomNavigation.splice(dockStateIndex, 1);
		}
	}

	function hasAvailableBottomNavigation() {
		var docks = document.querySelectorAll("#bottom-navigation");
		for (var dockIndex = 0; dockIndex < docks.length; dockIndex++) {
			var dock = docks[dockIndex];
			var stateIndex = bottomNavigationStateIndex(dock);
			var original = -1 === stateIndex ? null : yieldedBottomNavigation[stateIndex];
			if (original) {
				if (original.available) { return true; }
				continue;
			}
			var dockStyle = window.getComputedStyle(dock);
			var mobileViewport = window.innerWidth < 768;
			if (!dock.hidden && !dock.classList.contains("hidden") && dock.style.display !== "none" && dock.getAttribute("aria-hidden") !== "true" && !dock.hasAttribute("inert") && dock.querySelector("a[href], button, [role='link'], [role='button']") && (!mobileViewport || (dockStyle.display !== "none" && dockStyle.visibility !== "hidden"))) {
				return true;
			}
		}
		return false;
	}

	function dispatchMobileBarMode() {
		var priceBarVisible = !priceBar.classList.contains("hidden");
		priceBar.setAttribute("data-jluxe-mobile-bar-ready", "true");
		window.dispatchEvent(new CustomEvent("jluxe:toggle-mobile-bar", {
			detail: {
				mode: priceBarVisible ? "purchase" : "navigation",
				priceBarVisible: priceBarVisible,
				externalNavigationAvailable: hasAvailableBottomNavigation(),
			},
		}));
	}

	function guideToVariationChoice() {
		var variationForm = document.querySelector("form.variations_form.cart");
		if (!variationForm) {
			return;
		}
		var target = variationForm.querySelector("[data-cp3-pills], [data-jluxe-variation-group]") || variationForm.querySelector(".variations") || variationForm;
		var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
		var behavior = reducedMotion ? "auto" : "smooth";
		if (typeof target.scrollIntoView === "function") {
			target.scrollIntoView({ behavior: behavior, block: "center" });
		} else if (typeof window.scrollTo === "function" && typeof target.getBoundingClientRect === "function") {
			var targetTop = target.getBoundingClientRect().top + (window.pageYOffset || 0) - 120;
			window.scrollTo({ top: Math.max(0, targetTop), behavior: behavior });
		}
		var choice = variationForm.querySelector("[data-cp3-pills] .cp3-pill:not(:disabled), [data-jluxe-variation-swatches] button:not(:disabled)");
		if (!choice) {
			var selects = variationForm.querySelectorAll(".variations select:not(:disabled)");
			for (var index = 0; index < selects.length; index++) {
				var style = window.getComputedStyle(selects[index]);
				if (!selects[index].hasAttribute("data-cp3-select") && style.display !== "none" && style.visibility !== "hidden" && style.opacity !== "0") {
					choice = selects[index];
					break;
				}
			}
		}
		if (choice) {
			window.setTimeout(function () {
				if (!choice.isConnected) {
					return;
				}
				try {
					choice.focus({ preventScroll: true });
				} catch (error) {
					choice.focus();
				}
			}, 350);
		}
	}

	addBtn.addEventListener("click", function () {
		var mode = addBtn.getAttribute("data-jluxe-mobile-bar-mode") || "add";
		var variationForm = document.querySelector("form.variations_form.cart");
		var realBtn = document.querySelector("form.cart .single_add_to_cart_button");
		if (mode === "scroll" || (variationForm && (!realBtn || realBtn.disabled))) {
			guideToVariationChoice();
			return;
		}
		if (realBtn && typeof realBtn.click === "function" && !realBtn.disabled) {
			realBtn.click();
		} else if (variationForm) {
			guideToVariationChoice();
		}
	});

	function viewportHeight() {
		/* Keep position:fixed offsets in the same layout-viewport coordinate
		 * space as getBoundingClientRect(), even when the keyboard resizes the
		 * visual viewport. */
		return window.innerHeight || document.documentElement.clientHeight || 0;
	}

	function positionToggleAwayFromAssistant() {
		var assistant = window.JLuxeThemeSettings && window.JLuxeThemeSettings.aiAssistant;
		var assistantSide = assistant && assistant.position === "start" ? "start" : "end";
		var toggleSide = assistantSide === "start" ? "end" : "start";
		var direction = window.getComputedStyle(document.documentElement).direction || document.documentElement.dir || "ltr";
		var rtl = direction === "rtl";
		var safeStartSide = rtl ? "right" : "left";
		var safeEndSide = rtl ? "left" : "right";
		toggleBtn.style.setProperty(
			"inset-inline-start",
			toggleSide === "start" ? `max(12px, env(safe-area-inset-${safeStartSide}, 0px))` : "auto",
		);
		toggleBtn.style.setProperty(
			"inset-inline-end",
			toggleSide === "end" ? `max(12px, env(safe-area-inset-${safeEndSide}, 0px))` : "auto",
		);
	}

	function positionToggle() {
		positionToggleAwayFromAssistant();
		var viewportH = viewportHeight();
		var activeTop = null;
		if (!priceBar.classList.contains("hidden")) {
			var priceRect = priceBar.getBoundingClientRect();
			if (priceRect.height > 0) { activeTop = priceRect.top; }
		} else {
			var navCandidates = document.querySelectorAll("#bottom-navigation, .jluxe-mobile-nav");
			for (var navIndex = 0; navIndex < navCandidates.length; navIndex++) {
				var nav = navCandidates[navIndex];
				var navRect = nav.getBoundingClientRect();
				var navStyle = window.getComputedStyle(nav);
				if (!nav.hidden && navRect.height > 0 && navRect.bottom > 0 && navRect.top < viewportH && navStyle.display !== "none" && navStyle.visibility !== "hidden") {
					activeTop = null === activeTop ? navRect.top : Math.min(activeTop, navRect.top);
				}
			}
		}
		if (null !== activeTop) {
			var bottom = Math.max(0, Math.ceil(viewportH - activeTop + 8)) + "px";
			if (toggleBtn.style.bottom !== bottom) { toggleBtn.style.bottom = bottom; }
		}
	}

	var positionFramePending = false;
	function schedulePositionToggle() {
		if (positionFramePending) { return; }
		positionFramePending = true;
		var update = function () {
			positionFramePending = false;
			positionToggle();
		};
		if (typeof window.requestAnimationFrame === "function") {
			window.requestAnimationFrame(update);
		} else {
			window.setTimeout(update, 0);
		}
	}
	priceBar.addEventListener("transitionend", schedulePositionToggle);

	function syncToggleState() {
		var priceMode = !priceBar.classList.contains("hidden");
		toggleBtn.setAttribute("aria-pressed", priceMode ? "true" : "false");
		toggleBtn.setAttribute("aria-label", priceMode ? "نمایش منوی موبایل" : "نمایش نوار خرید");
		var icons = toggleBtn.querySelectorAll("[data-jluxe-mobile-bar-mode-icon]");
		for (var iconIndex = 0; iconIndex < icons.length; iconIndex++) {
			icons[iconIndex].hidden = icons[iconIndex].getAttribute("data-jluxe-mobile-bar-mode-icon") !== (priceMode ? "menu" : "purchase");
		}
		setBottomNavigationYielded(priceMode);
		positionToggle();
	}

	priceBar.classList.remove("hidden");
	syncToggleState();
	dispatchMobileBarMode();

	toggleBtn.addEventListener("click", function () {
		priceBar.classList.toggle("hidden");
		syncToggleState();
		dispatchMobileBarMode();
		if (typeof window.requestAnimationFrame === "function") { window.requestAnimationFrame(positionToggle); }
		window.setTimeout(positionToggle, 0);
	});

	window.addEventListener("resize", schedulePositionToggle, { passive: true });
	window.addEventListener("orientationchange", schedulePositionToggle, { passive: true });
	window.addEventListener("pageshow", schedulePositionToggle, { passive: true });
	window.addEventListener("scroll", schedulePositionToggle, { passive: true });
	if (window.visualViewport) {
		window.visualViewport.addEventListener("resize", schedulePositionToggle, { passive: true });
		window.visualViewport.addEventListener("scroll", schedulePositionToggle, { passive: true });
	}
	if (typeof window.MutationObserver === "function" && document.body) {
		var navObserver = new window.MutationObserver(function (mutations) {
			for (var mutationIndex = 0; mutationIndex < mutations.length; mutationIndex++) {
				var changedNodes = Array.prototype.slice.call(mutations[mutationIndex].addedNodes).concat(
					Array.prototype.slice.call(mutations[mutationIndex].removedNodes)
				);
				for (var nodeIndex = 0; nodeIndex < changedNodes.length; nodeIndex++) {
					var node = changedNodes[nodeIndex];
					if (1 === node.nodeType && (node.matches("#bottom-navigation, .jluxe-mobile-nav") || node.querySelector("#bottom-navigation, .jluxe-mobile-nav"))) {
						setBottomNavigationYielded(!priceBar.classList.contains("hidden"));
						dispatchMobileBarMode();
						positionToggle();
						return;
					}
				}
			}
		});
		navObserver.observe(document.body, { childList: true, subtree: true });
	}
})();

/**
 * سینکِ قیمتِ نوارِ چسبانِ موبایل (بالا) با تنوعِ واقعاً انتخاب‌شده —
 * دقیقاً همون رویدادهای found_variation/reset_data که jluxeBindPriceBox
 * (بالاتر در همین فایل) هم گوش می‌ده، ولی جدا نگه داشته شده که به منطقِ
 * انیمیشنِ ارتفاعِ آن تابع (که مخصوصِ جعبه‌ی اصلیِ قیمته) وابسته نباشه.
 */
(function ($) {
	if (!$) {
		return;
	}
	var priceSlots = [];
	var stockSlots = document.querySelectorAll("[data-jluxe-mobile-bar-stock], [data-jluxe-sticky-stock-status]");
	var mobilePriceSlot = document.querySelector("[data-jluxe-mobile-bar-price]");
	var stickyPriceSlots = document.querySelectorAll("[data-jluxe-sticky-variation-price]");
	if (mobilePriceSlot) {
		priceSlots.push(mobilePriceSlot);
	}
	for (var slotIndex = 0; slotIndex < stickyPriceSlots.length; slotIndex++) {
		priceSlots.push(stickyPriceSlots[slotIndex]);
	}
	var stickyAddButton = document.querySelector("[data-jluxe-sticky-add]");
	var mobileAddButton = document.querySelector("[data-jluxe-mobile-bar-add]");
	if (!priceSlots.length && !stockSlots.length && !stickyAddButton && !mobileAddButton) {
		return;
	}
	var $form = $(".variations_form[data-product_variations]").first();
	if (!$form.length) {
		return;
	}
	var initialHtml = priceSlots.map(function (slot) { return slot.innerHTML; });

	function setVariationButton(button, labelSelector, ready, isSticky) {
		if (!button) {
			return;
		}
		var mode = ready ? "add" : "scroll";
		var label = ready ? "افزودن به سبد" : "انتخاب گزینه‌ها";
		button.setAttribute(isSticky ? "data-jluxe-sticky-mode" : "data-jluxe-mobile-bar-mode", mode);
		button.setAttribute("aria-label", ready ? "افزودن به سبد خرید" : "رفتن به انتخاب تنوع");
		var labelNode = button.querySelector(labelSelector);
		if (labelNode) {
			labelNode.textContent = label;
		}
	}

	function setAddState(ready) {
		setVariationButton(stickyAddButton, "[data-jluxe-sticky-label]", ready, true);
		setVariationButton(mobileAddButton, "[data-jluxe-mobile-bar-add-label]", ready, false);
	}

	function resetPrices() {
		priceSlots.forEach(function (slot, index) {
			var placeholder = slot.getAttribute("data-jluxe-price-placeholder");
			slot.innerHTML = placeholder !== null ? placeholder : initialHtml[index];
		});
	}

	function setStockStatus(label, state) {
		for (var stockIndex = 0; stockIndex < stockSlots.length; stockIndex++) {
			stockSlots[stockIndex].textContent = label;
			stockSlots[stockIndex].setAttribute("data-jluxe-stock-state", state);
		}
	}

	function resetStockStatus() {
		for (var stockIndex = 0; stockIndex < stockSlots.length; stockIndex++) {
			var stockSlot = stockSlots[stockIndex];
			var placeholder = stockSlot.getAttribute("data-jluxe-stock-placeholder");
			var placeholderState = stockSlot.getAttribute("data-jluxe-stock-placeholder-state");
			if (placeholder !== null) {
				stockSlot.textContent = placeholder;
			}
			if (placeholderState !== null) {
				stockSlot.setAttribute("data-jluxe-stock-state", placeholderState);
			}
		}
	}

	function updateStockStatus(variation) {
		if (!variation || !(parseInt(variation.variation_id, 10) > 0)) {
			resetStockStatus();
			return;
		}
		if (variation.is_in_stock === false) {
			setStockStatus("ناموجود", "out-of-stock");
		} else if (variation.is_purchasable === false) {
			setStockStatus("در حال حاضر قابل خرید نیست", "unavailable");
		} else if (variation.is_in_stock === true) {
			setStockStatus("", "in-stock");
		} else {
			setStockStatus("وضعیت موجودی مشخص نیست", "unknown");
		}
	}

	function isPurchasableVariation(variation) {
		return !!variation && parseInt(variation.variation_id, 10) > 0 && variation.is_purchasable !== false && variation.is_in_stock !== false;
	}

	$form.on("found_variation", function (event, variation) {
		updateStockStatus(variation);
		if (!isPurchasableVariation(variation)) {
			resetPrices();
			setAddState(false);
			return;
		}
		var priceHtml = typeof variation.price_html === "string" ? variation.price_html : "";
		if (priceHtml) {
			priceSlots.forEach(function (slot) {
				slot.innerHTML = priceHtml;
			});
		}
		setAddState(true);
	});
	$form.on("reset_data hide_variation", function () {
		resetPrices();
		resetStockStatus();
		setAddState(false);
	});

	var selectedVariationId = parseInt($form.find("input.variation_id").val(), 10);
	if (selectedVariationId > 0) {
		var availableVariations = $form.data("product_variations");
		var selectedVariation = null;
		if (Array.isArray(availableVariations)) {
			for (var variationIndex = 0; variationIndex < availableVariations.length; variationIndex++) {
				if (parseInt(availableVariations[variationIndex].variation_id, 10) === selectedVariationId) {
					selectedVariation = availableVariations[variationIndex];
					break;
				}
			}
		}
		if (selectedVariation) {
			updateStockStatus(selectedVariation);
			setAddState(isPurchasableVariation(selectedVariation));
		} else {
			setAddState(true);
		}
	}
})(window.jQuery);

/**
 * آکاردئونِ سوالاتِ متداولِ محصول (inc/product-faq.php →
 * jluxe_render_product_faq_section) — با کلیک/لمس روی هر سوال، پاسخش با
 * ترانزیشنِ grid-template-rows (0fr → 1fr، همون تکنیکِ رایجِ CSS-only
 * برای انیمیشنِ ارتفاعِ نامعلوم بدون اندازه‌گیریِ JS) باز/بسته می‌شه. هر
 * سوال مستقله (باز بودنِ یکی، بقیه رو نمی‌بنده) — چون این یک لیستِ
 * اطلاعاتیه نه یک ناوبریِ تک‌انتخابی.
 */
(function () {
	document.addEventListener("click", function (event) {
		var trigger = event.target.closest(".jluxe-faq-trigger");
		if (!trigger) {
			return;
		}
		var item = trigger.closest(".jluxe-faq-accordion-item");
		if (!item) {
			return;
		}
		var panel = item.querySelector(".jluxe-faq-panel");
		if (!panel) {
			return;
		}
		var isOpen = trigger.getAttribute("aria-expanded") === "true";
		trigger.setAttribute("aria-expanded", isOpen ? "false" : "true");
		panel.setAttribute("aria-hidden", isOpen ? "true" : "false");
		panel.classList.toggle("grid-rows-[0fr]", isOpen);
		panel.classList.toggle("grid-rows-[1fr]", !isOpen);
		var chevron = trigger.querySelector(".jluxe-faq-chevron");
		if (chevron) {
			chevron.style.transform = isOpen ? "" : "rotate(180deg)";
		}
	});
})();

/**
 * R307 — کد پستی فقط برای نرخِ انتخاب‌شدهٔ ادارهٔ پست لازم است.
 * فیلدها در HTML پایه اختیاری می‌مانند؛ هنگام انتخاب Post، فقط فیلدِ مقصد
 * (billing یا shipping) در مرورگر required می‌شود. نرخ و مبلغ WooCommerce
 * دست‌کاری نمی‌شوند و الزامِ سمتِ سرور هم جداگانه اعمال می‌شود.
 */
(function () {
	var form = document.querySelector("form.checkout");
	if (!form) {
		return;
	}

	function postcodeRequiredForPost() {
		var settings = window.JLuxeThemeSettings && window.JLuxeThemeSettings.cartCheckout;
		var value = settings && settings.require_postcode_for_post;
		return false !== value && 0 !== value && "0" !== value;
	}

	function selectedPostRate() {
		return form.querySelectorAll('input.shipping_method:checked[data-jluxe-shipping-kind="post"]').length > 0;
	}

	function setPostcodeRequired(input, required, placeholder) {
		if (!input) {
			return;
		}

		input.setAttribute("placeholder", placeholder);
		var row = input.closest(".form-row");
		if (row) {
			row.classList.toggle("validate-required", required);
			row.classList.toggle("validate-optional", !required);
			if (!required) {
				row.classList.remove("woocommerce-invalid", "woocommerce-invalid-required-field");
			}
		}
		input.required = required;
		if (required) {
			input.setAttribute("aria-required", "true");
		} else {
			input.removeAttribute("aria-required");
		}

		var label = row ? row.querySelector("label") : null;
		if (!label) {
			return;
		}
		var requiredMarker = label.querySelector(".required");
		var optionalMarker = label.querySelector(".optional");
		if (required) {
			if (optionalMarker) {
				optionalMarker.remove();
			}
			if (!requiredMarker) {
				requiredMarker = document.createElement("abbr");
				requiredMarker.className = "required";
				requiredMarker.title = "الزامی";
				requiredMarker.setAttribute("aria-hidden", "true");
				requiredMarker.textContent = "*";
				label.appendChild(document.createTextNode(" "));
				label.appendChild(requiredMarker);
			}
		} else {
			if (requiredMarker) {
				requiredMarker.remove();
			}
			if (!optionalMarker) {
				optionalMarker = document.createElement("span");
				optionalMarker.className = "optional";
				optionalMarker.textContent = " (اختیاری)";
				label.appendChild(optionalMarker);
			}
		}
	}

	function updatePostcodeRequirement() {
		var postcodeSettingEnabled = postcodeRequiredForPost();
		var needsPostcode = postcodeSettingEnabled && selectedPostRate();
		var placeholder = postcodeSettingEnabled
			? "برای ارسال پستی، کد پستی الزامی است"
			: "کد پستی (اختیاری)";
		var billingPostcode = form.querySelector('input[name="billing_postcode"]');
		var shippingPostcode = form.querySelector('input[name="shipping_postcode"]');
		var shipToDifferent = form.querySelector('#ship-to-different-address-checkbox, input[name="ship_to_different_address"]');
		var useShippingAddress = !!(shipToDifferent && shipToDifferent.checked && shippingPostcode);

		setPostcodeRequired(billingPostcode, needsPostcode && !useShippingAddress, placeholder);
		setPostcodeRequired(shippingPostcode, needsPostcode && useShippingAddress, placeholder);
	}

	document.addEventListener("change", updatePostcodeRequirement, true);
	if (window.jQuery) {
		window.jQuery(document.body).on("updated_checkout", updatePostcodeRequirement);
	}
	updatePostcodeRequirement();
})();

/**
 * R308 — با بازشدن/تغییر اندازه‌ی کیبورد موبایل، فیلد فعالِ checkout را در
 * محدوده‌ی visual viewport نگه می‌دارد؛ فقط فرم checkout هدف است و محاسبه‌ی
 * نرخ/مرحله‌های WooCommerce یا فرم‌های ویرایش نشانی حساب را تغییر نمی‌دهد.
 */
(function () {
	var form = document.querySelector("form.checkout");
	if (!form) {
		return;
	}

	var activeField = null;
	var focusTimers = [];
	var revealTimer = null;

	function isEditableCheckoutField(target) {
		if (!target || !form.contains(target) || target.nodeType !== 1) {
			return false;
		}
		var tagName = target.tagName;
		if ("INPUT" === tagName) {
			var type = String(target.type || "text").toLowerCase();
			if (/^(hidden|button|submit|reset|checkbox|radio|file|image|range|color)$/.test(type)) {
				return false;
			}
		} else if ("TEXTAREA" !== tagName && "SELECT" !== tagName && !target.isContentEditable) {
			return false;
		}
		if (target.disabled || target.readOnly || target.closest("[hidden], [aria-hidden='true']")) {
			return false;
		}
		return true;
	}

	function clearFocusTimers() {
		focusTimers.forEach(function (timer) {
			window.clearTimeout(timer);
		});
		focusTimers = [];
		if (null !== revealTimer) {
			window.clearTimeout(revealTimer);
			revealTimer = null;
		}
	}

	function revealActiveField() {
		revealTimer = null;
		if (!isEditableCheckoutField(activeField)) {
			return;
		}

		var rect = activeField.getBoundingClientRect();
		if (!rect || (0 === rect.top && 0 === rect.bottom)) {
			return;
		}
		var viewport = window.visualViewport;
		var viewportTop = viewport ? Math.max(0, Number(viewport.offsetTop) || 0) : 0;
		var viewportHeight = viewport && Number(viewport.height) > 0 ? Number(viewport.height) : Number(window.innerHeight);
		if (!(viewportHeight > 0)) {
			return;
		}
		var visibleBottom = viewportTop + viewportHeight;
		var safeTop = viewportTop + Math.min(72, Math.max(28, viewportHeight * 0.12));
		var safeBottom = visibleBottom - 28;
		var delta = rect.bottom > safeBottom
			? rect.bottom - safeBottom
			: rect.top < safeTop
				? rect.top - safeTop
				: 0;
		if (delta && "function" === typeof window.scrollBy) {
			window.scrollBy(0, delta);
		}
	}

	function queueReveal() {
		if (null !== revealTimer) {
			window.clearTimeout(revealTimer);
		}
		revealTimer = window.setTimeout(revealActiveField, 32);
	}

	function handleFocusIn(event) {
		if (!isEditableCheckoutField(event.target)) {
			return;
		}
		activeField = event.target;
		clearFocusTimers();
		[0, 120, 280, 520, 850].forEach(function (delay) {
			focusTimers.push(window.setTimeout(queueReveal, delay));
		});
	}

	function handleFocusOut(event) {
		var leavingField = event.target;
		window.setTimeout(function () {
			if (document.activeElement === leavingField || isEditableCheckoutField(document.activeElement)) {
				return;
			}
			activeField = null;
			clearFocusTimers();
		}, 0);
	}

	form.addEventListener("focusin", handleFocusIn, true);
	form.addEventListener("focusout", handleFocusOut, true);
	if (window.visualViewport && "function" === typeof window.visualViewport.addEventListener) {
		window.visualViewport.addEventListener("resize", queueReveal);
		window.visualViewport.addEventListener("scroll", queueReveal);
	} else {
		window.addEventListener("resize", queueReveal);
	}
	if (window.jQuery) {
		window.jQuery(document.body).on("updated_checkout", function () {
			if (isEditableCheckoutField(document.activeElement)) {
				activeField = document.activeElement;
				queueReveal();
			}
		});
	}
})();

/**
 * R289 — ارقامِ فارسی/عربی را پیش از validation به مقدارِ ASCII تبدیل می‌کند.
 * خودِ input همیشه متنش را با رندر بومی مرورگر نشان می‌دهد؛ لایهٔ overlay و
 * شفاف‌کردن نوشتهٔ input حذف شده‌اند تا Address Autofill موبایل هم فوراً
 * قابل دیدن باشد و عددها بیرون/زیر کادر ظاهر نشوند.
 */
(function () {
	var form = document.querySelector("form.checkout");
	if (!form) {
		return;
	}

	var fieldSelector = [
		'form.checkout input[name="billing_postcode"]',
		'form.checkout input[name="shipping_postcode"]',
		'form.checkout input[name="billing_phone"]',
		'form.checkout input[name="shipping_phone"]',
		'form.checkout input[name="billing_landline"]',
	].join(", ");

	function normalizeDigits(value) {
		var helpers = window.JLuxeStorefrontUtils;
		if (helpers && typeof helpers.normalizeDigits === "function") {
			return helpers.normalizeDigits(value);
		}
		return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
			var code = digit.charCodeAt(0);
			return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
		});
	}

	function removeLegacyDigitDisplay(input) {
		var wrapper = input.closest(".woocommerce-input-wrapper");
		input.removeAttribute("data-jluxe-ascii-digit-input");
		if (!wrapper) {
			return;
		}
		wrapper.classList.remove("jluxe-numeric-input-wrapper");
		wrapper.querySelectorAll(".jluxe-numeric-digit-display").forEach(function (display) {
			display.remove();
		});
	}

	function updateField(input) {
		// Remove stale markup from a cached/previous version before updating the native value.
		removeLegacyDigitDisplay(input);
		var value = input.value;
		var asciiValue = normalizeDigits(value);
		if (asciiValue === value) {
			return;
		}

		var selectionStart = null;
		var selectionEnd = null;
		try {
			selectionStart = input.selectionStart;
			selectionEnd = input.selectionEnd;
		} catch (error) { /* Some browser-managed controls do not expose a selection. */ }
		input.value = asciiValue;
		if (null !== selectionStart && null !== selectionEnd && typeof input.setSelectionRange === "function") {
			try { input.setSelectionRange(selectionStart, selectionEnd); } catch (error) { /* Keep the normalized value even if caret restoration is unsupported. */ }
		}
	}

	function isNumericCheckoutField(target) {
		return !!(target && target.matches && target.matches(fieldSelector));
	}

	function normalizeEvent(event) {
		if (event.isComposing || !isNumericCheckoutField(event.target)) {
			return;
		}
		updateField(event.target);
	}

	function normalizeComposition(event) {
		if (isNumericCheckoutField(event.target)) {
			updateField(event.target);
		}
	}

	function initializeFields(root) {
		if (root && root.matches && root.matches(fieldSelector)) {
			updateField(root);
		}
		if (root && root.querySelectorAll) {
			root.querySelectorAll(fieldSelector).forEach(updateField);
		}
	}

	document.addEventListener("input", normalizeEvent, true);
	document.addEventListener("change", normalizeEvent, true);
	document.addEventListener("compositionend", normalizeComposition, true);
	document.addEventListener("submit", function (event) {
		if (event.target && event.target.matches && event.target.matches("form.checkout")) {
			initializeFields(event.target);
		}
	}, true);
	initializeFields(form);
	if (window.jQuery) {
		window.jQuery(document.body).on("updated_checkout", function () {
			initializeFields(document);
		});
	}
})();

/**
 * گیت‌کردنِ واقعیِ مرحله‌ی «روش پرداخت» توی چک‌اوت (woocommerce/checkout/
 * form-checkout.php + inc/woocommerce.php: jluxe_render_checkout_stepper).
 *
 * طبقِ درخواستِ صریحِ کاربر، «اطلاعات ارسال» (فرم آدرس + انتخابِ روشِ
 * ارسال) و «نحوه پرداخت» (انتخابِ درگاه) باید دو مرحله‌ی کاملاً جدا حس
 * بشن — نه این‌که با زدنِ «ادامه»، پرداخت کنارِ همون فرمِ آدرس/روشِ ارسال
 * ظاهر بشه. پس این‌جا موقعِ رفتن به مرحله‌ی پرداخت، هم #customer_details
 * (فرمِ آدرس) و هم #jluxe-shipping-section (انتخابِ روشِ ارسال) مخفی
 * می‌شن — نه فقط #payment که قبلاً تنها چیزِ مخفی‌شده بود.
 *
 * همچنین طبقِ درخواستِ صریح، نه روشِ ارسال و نه روشِ پرداخت هیچ‌کدوم از
 * پیش انتخاب‌شده نیستن (woocommerce/cart/cart-shipping.php و
 * woocommerce/checkout/payment-method.php دیگه هیچ رادیویی رو pre-check
 * نمی‌کنن) — پس این‌جا هم قبلِ رفتنِ به مرحله‌ی بعد واقعاً چک می‌کنیم که
 * کاربر خودش یکی رو زده باشه، وگرنه پیغامِ خطای واقعی نشون می‌دیم.
 *
 * چون #jluxe-shipping-section و #payment هر دو داخلِ #order_review
 * هستن و با هر AJAX ووکامرس (تغییرِ آدرس/روشِ ارسال → updated_checkout)
 * کاملاً از نو رندر می‌شن، وضعیتِ نمایش/مخفی‌بودن باید رویِ همون رویداد
 * دوباره اعمال بشه. دکمه‌های «ادامه»/«بازگشت» عمداً بیرونِ #order_review
 * (بلافاصله بعدش) قرار می‌گیرن تا خودِ همون AJAX پاکشون نکنه.
 */
(function () {
	var form = document.querySelector("form.checkout");
	var stepper = document.querySelector(".jluxe-checkout-stepper");
	var orderReview = document.getElementById("order_review");
	if (!form || !orderReview || !window.jQuery) {
		return;
	}

	var CHECK_SVG =
		'<svg class="size-4 sm:size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	var currentStep = "shipping";
	var customerDetails = document.getElementById("customer_details");
	// آدرسِ واقعیِ سبدِ خرید — از inc/woocommerce.php (wc_get_cart_url())
	// localize شده؛ قبلاً از یک لینکِ داخلِ #payment خونده می‌شد که دیگه
	// (طبقِ همین درخواست) اونجا رندر نمی‌شه.
	var wcCartUrl = (window.jluxeWcSettings && window.jluxeWcSettings.cartUrl) || "/cart/";
	var checkoutCopy = (window.JLuxeThemeSettings && window.JLuxeThemeSettings.cartCheckout && window.JLuxeThemeSettings.cartCheckout.copy) || {};

	function getCheckoutCopy(key, fallback) {
		var value = checkoutCopy[key];
		return typeof value === "string" && value.trim() ? value : fallback;
	}

	function setStepVisual(id, state) {
		if (!stepper) {
			return;
		}
		var stepEl = stepper.querySelector('[data-jluxe-step="' + id + '"]');
		if (!stepEl) {
			return;
		}
		var circle = stepEl.querySelector("[data-jluxe-step-circle]");
		var label = stepEl.querySelector("[data-jluxe-step-label]");
		if (!circle) {
			return;
		}
		stepEl.setAttribute("data-jluxe-step-state", state);
		if ("active" === state) {
			stepEl.setAttribute("aria-current", "step");
		} else {
			stepEl.removeAttribute("aria-current");
		}
		circle.classList.remove("border-success", "bg-success", "text-white", "border-primary", "text-primary", "border-border", "text-text-muted");
		if (label) {
			label.classList.remove("font-medium", "text-foreground", "text-text-muted");
		}
		if (state === "done") {
			circle.classList.add("border-success", "bg-success", "text-white");
			circle.innerHTML = CHECK_SVG;
			if (label) { label.classList.add("text-text-muted"); }
		} else if (state === "active") {
			circle.classList.add("border-primary", "text-primary");
			circle.innerHTML = '<span class="text-caption font-bold">' + circle.getAttribute("data-jluxe-step-number") + "</span>";
			if (label) { label.classList.add("font-medium", "text-foreground"); }
		} else {
			circle.classList.add("border-border", "text-text-muted");
			circle.innerHTML = '<span class="text-caption font-bold">' + circle.getAttribute("data-jluxe-step-number") + "</span>";
			if (label) { label.classList.add("text-text-muted"); }
		}
	}

	// #jluxe-shipping-section/#payment داخل #order_review هستن و با هر
	// AJAX از نو ساخته می‌شن، پس هر بار از DOM واقعی (نه یک متغیرِ ذخیره‌شده)
	// خونده می‌شن.
	function applyStepVisibility() {
		var shippingSection = document.getElementById("jluxe-shipping-section");
		var payment = document.getElementById("payment");
		// wrapper عریضِ جدیدِ روشِ پرداخت (woocommerce/checkout/form-checkout.php)
		// — برخلافِ #payment، این با AJAX ووکامرس جایگزین نمی‌شه (فرگمنت فقط
		// روی خودِ .woocommerce-checkout-payment عمل می‌کنه، نه wrapperِ
		// بیرونیش)، پس همیشه قابلِ‌اعتمادتره؛ ولی هر دو رو با هم toggle
		// می‌کنیم تا هر جا #payment از نو ساخته شد هم بلافاصله وضعیتِ درست رو
		// داشته باشه.
		var paymentColumn = document.getElementById("jluxe-payment-column");
		// دکمه‌ی «پرداخت» + شرایط — حالا توی سایدبار، کنارِ «بازگشت»
		// (inc/woocommerce.php: jluxe_render_payment_submit).
		var paymentSubmit = document.getElementById("jluxe-payment-submit");
		var showShipping = "shipping" === currentStep;
		if (customerDetails) {
			customerDetails.style.display = showShipping ? "" : "none";
		}
		if (shippingSection) {
			shippingSection.style.display = showShipping ? "" : "none";
		}
		if (payment) {
			payment.style.display = showShipping ? "none" : "";
		}
		if (paymentColumn) {
			paymentColumn.hidden = showShipping;
		}
		if (paymentSubmit) {
			paymentSubmit.hidden = showShipping;
		}
		if (actions) {
			actions.hidden = !showShipping;
		}
	}

	function hasShippingMethodSelected() {
		var shippingSection = document.getElementById("jluxe-shipping-section");
		if (!shippingSection) {
			return true; // سبد نیازی به ارسال نداره (مثلاً کاملاً محصول دانلودی) — چیزی برای انتخاب نیست.
		}
		var radios = shippingSection.querySelectorAll("input.shipping_method");
		if (!radios.length) {
			return true;
		}
		var groups = {};
		for (var i = 0; i < radios.length; i++) {
			var name = radios[i].name;
			if (!(name in groups)) {
				groups[name] = false;
			}
			if (radios[i].checked) {
				groups[name] = true;
			}
		}
		for (var key in groups) {
			if (Object.prototype.hasOwnProperty.call(groups, key) && !groups[key]) {
				return false;
			}
		}
		return true;
	}

	function toggleError(selector, show) {
		var el = document.querySelector(selector);
		if (el) {
			el.hidden = !show;
		}
		return el;
	}

	function scrollCheckoutToTop() {
		// A deliberate step change should be immediate; smooth scrolling the
		// entire checkout can make the local shipping/payment switch feel stalled.
		var root = document.documentElement;
		var previousScrollBehavior = root.style.scrollBehavior;
		root.style.scrollBehavior = "auto";
		try {
			if (typeof window.scrollTo === "function") {
				window.scrollTo({ top: 0, behavior: "auto" });
			} else if (stepper && typeof stepper.scrollIntoView === "function") {
				stepper.scrollIntoView({ behavior: "auto", block: "start" });
			}
		} finally {
			root.style.scrollBehavior = previousScrollBehavior;
		}
	}


	/* دکمه‌های مرحله‌ی ارسال پایینِ خلاصه سفارش می‌مانند؛ با تغییر مرحله،
	 * خود صفحه به ابتدای viewport برمی‌گردد تا استپر و عنوان مرحله دیده شوند. */
	var actions = document.createElement("div");
	actions.className = "jluxe-checkout-step-actions jluxe-checkout-action-row";
	var toCartBtn = document.createElement("a");
	toCartBtn.href = wcCartUrl;
	toCartBtn.setAttribute("data-jluxe-checkout-to-cart", "");
	toCartBtn.className = "jluxe-checkout-action-button jluxe-checkout-action-button--secondary";
	toCartBtn.textContent = getCheckoutCopy("checkout_back_to_cart_button", "بازگشت به سبد خرید");
	var nextBtn = document.createElement("button");
	nextBtn.type = "button";
	nextBtn.setAttribute("data-jluxe-checkout-next", "");
	nextBtn.className = "jluxe-checkout-action-button jluxe-checkout-action-button--primary";
	nextBtn.textContent = getCheckoutCopy("checkout_next_button", "نهایی‌سازی سفارش");
	actions.appendChild(toCartBtn);
	actions.appendChild(nextBtn);
	orderReview.insertAdjacentElement("afterend", actions);
	var paymentBackBtn = document.querySelector("#jluxe-payment-submit [data-jluxe-checkout-back]");

	function goToPayment() {
		currentStep = "payment";
		setStepVisual("shipping", "done");
		setStepVisual("payment", "active");
		applyStepVisibility();
		nextBtn.hidden = true;
		toCartBtn.hidden = true;
		if (paymentBackBtn) {
			paymentBackBtn.hidden = false;
		}
		scrollCheckoutToTop();
	}

	function goToShipping() {
		currentStep = "shipping";
		setStepVisual("shipping", "active");
		setStepVisual("payment", "pending");
		applyStepVisibility();
		nextBtn.hidden = false;
		toCartBtn.hidden = false;
		if (paymentBackBtn) {
			paymentBackBtn.hidden = true;
		}
		toggleError('[data-jluxe-payment-error]', false);
		scrollCheckoutToTop();
	}

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده پیدا شد): فیلدهای الزامیِ فرمِ آدرس
	 * رو خالی می‌ذاشتیم و «نهایی‌سازی سفارش» بدونِ هیچ مانعی به مرحله‌ی
	 * پرداخت می‌رفت. علتِ واقعی: چکِ قبلی روی pseudo-class بومیِ مرورگر
	 * (:invalid) بود، ولی خودِ ووکامرس (woocommerce_form_field در
	 * wc-template-functions.php) هیچ‌وقت attribute واقعیِ HTML5
	 * required="required" رو روی input نمی‌ذاره — فقط کلاسِ CSS
	 * validate-required روی <p class="form-row"> و aria-required روی خودِ
	 * input. یعنی هیچ فیلدی هیچ‌وقت واقعاً :invalid نمی‌شد، پس اون چک همیشه
	 * بی‌اثر بود. اصلاح: همون قراردادِ خودِ ووکامرس رو پیاده می‌کنیم —
	 * دقیقاً همون چیزی که assets/frontend/checkout.js خودِ ووکامرس موقعِ
	 * submit واقعی روی .validate-required:visible انجام می‌ده (کلاسِ
	 * woocommerce-invalid/woocommerce-invalid-required-field که CSS
	 * قرمزِ globals.css روش سوار می‌شه) — نه یک اعتبارسنجیِ ابداعیِ جدید.
	 */
	function findFirstInvalidRequiredField() {
		if (!customerDetails || !window.jQuery) {
			return null;
		}
		var $ = window.jQuery;
		var $rows = $(customerDetails).find(".validate-required:visible");
		var $firstInvalid = null;
		$rows.each(function () {
			var $row = $(this);
			var $input = $row.find("input.input-text, select, textarea, input:checkbox");
			if (!$input.length) {
				return;
			}
			var isEmpty = $input.is(":checkbox") ? !$input.is(":checked") : $input.val() === "" || $input.val() === null;
			if (isEmpty) {
				$row.addClass("woocommerce-invalid woocommerce-invalid-required-field");
				if (!$firstInvalid) {
					$firstInvalid = $row;
				}
			} else {
				$row.removeClass("woocommerce-invalid woocommerce-invalid-required-field");
			}
		});
		return $firstInvalid ? $firstInvalid[0] : null;
	}

	nextBtn.addEventListener("click", function () {
		var invalid = findFirstInvalidRequiredField();
		if (invalid) {
			invalid.scrollIntoView({ behavior: "smooth", block: "center" });
			var focusable = invalid.querySelector("input, select, textarea");
			if (focusable) {
				focusable.focus();
			}
			return;
		}
		if (!hasShippingMethodSelected()) {
			var errorEl = toggleError('[data-jluxe-shipping-error]', true);
			if (errorEl) {
				errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
			}
			return;
		}
		toggleError('[data-jluxe-shipping-error]', false);
		goToPayment();
	});
	if (paymentBackBtn) {
		paymentBackBtn.addEventListener("click", goToShipping);
	}

	// وقتی کاربر خودش یک روشِ ارسال رو انتخاب کرد، پیغامِ خطا (اگه نشون
	// داده شده بود) خودکار مخفی می‌شه — کاربر مجبور نیست دوباره «ادامه»
	// بزنه تا خطا بره.
	document.addEventListener("change", function (event) {
		if (event.target && event.target.classList && event.target.classList.contains("shipping_method")) {
			toggleError('[data-jluxe-shipping-error]', false);
		}
		if (event.target && event.target.classList && event.target.classList.contains("payment_method")) {
			toggleError('[data-jluxe-payment-error]', false);
		}
	});
	/*
	 * روشِ پرداخت هم دیگه از پیش انتخاب نیست، پس قبلِ اجازه‌دادنِ submitِ
	 * واقعیِ فرم (که خودِ اسکریپتِ چک‌اوتِ ووکامرس روی همین رویداد گوش
	 * می‌ده و پردازشِ AJAX سفارش رو شروع می‌کنه) چک می‌کنیم یک درگاه واقعاً
	 * انتخاب شده. capture:true عمداً استفاده شده — این‌طوری این listener
	 * تضمینی، مستقل از ترتیبِ لودِ اسکریپت‌ها، زودتر از هر listenerِ
	 * دیگه‌ای (از جمله خودِ ووکامرس) روی همین submit اجرا می‌شه؛
	 * stopImmediatePropagation جلوی رسیدنِ رویداد به اون listenerها رو
	 * کاملاً می‌گیره، نه فقطِ preventDefault.
	 */
	document.addEventListener(
		"submit",
		function (event) {
			if (event.target !== form || "payment" !== currentStep) {
				return;
			}
			var payment = document.getElementById("payment");
			var methodRadios = payment ? payment.querySelectorAll("input.payment_method") : [];
			if (!methodRadios.length) {
				return; // سفارش نیاز به پرداخت نداره (مثلاً مبلغ صفر) — چیزی برای انتخاب نیست.
			}
			var chosen = payment.querySelectorAll("input.payment_method:checked").length > 0;
			if (!chosen) {
				event.preventDefault();
				event.stopImmediatePropagation();
				var errorEl = toggleError('[data-jluxe-payment-error]', true);
				if (errorEl) {
					errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
				}
			}
		},
		true
	);

	/*
	 * باگِ واقعیِ گزارش‌شده (با تستِ زنده پیدا شد): با اینکه دیگه
	 * payment-method.php رادیوی درگاه رو pre-check نمی‌کنه، وقتی فقط یک
	 * درگاهِ پرداخت فعال باشه، خودِ اسکریپتِ هسته‌ی ووکامرس
	 * (assets/js/frontend/checkout.js) مستقل از تمپلیت، همون تنها رادیو
	 * رو با جاوااسکریپت خودش دوباره checked می‌کنه (رفتارِ استانداردِ
	 * خودِ ووکامرس برای «وقتی فقط یک انتخاب هست»). چون طبقِ درخواستِ صریحِ
	 * کاربر روشِ پرداخت هم نباید هیچ‌وقت از پیش انتخاب‌شده باشه، این‌جا
	 * دقیقاً همون رفتارِ خودکارِ ووکامرس رو (فقط همین یک مورد، نه هیچ
	 * منطقِ دیگه‌ای از چک‌اوت) خنثی می‌کنیم — با تأخیرِ کوتاه تا مطمئن
	 * بشیم بعد از اجرای اسکریپتِ خودِ ووکامرس اجرا می‌شه، نه قبلش.
	 */
	var jluxeManualPaymentMethod = null;
	document.addEventListener("change", function (event) {
		if (event.isTrusted && event.target.matches("#payment input.payment_method") && event.target.checked) {
			jluxeManualPaymentMethod = event.target.value;
		}
	}, true);
	function clearAutoSelectedPaymentMethod() {
		var payment = document.getElementById("payment");
		if (!payment) {
			return;
		}
		var radios = payment.querySelectorAll("input.payment_method");
		if (jluxeManualPaymentMethod) {
			var selected = Array.prototype.find.call(radios, function (radio) { return radio.value === jluxeManualPaymentMethod; });
			if (selected) {
				if (!selected.checked) window.jQuery(selected).trigger("click");
				return;
			}
		}
		if (radios.length !== 1 || !radios[0].checked) {
			return;
		}
		radios[0].checked = false;
		var box = payment.querySelector(".payment_box.payment_method_" + radios[0].value.replace(/[^a-zA-Z0-9_-]/g, ""));
		if (box) {
			box.style.display = "none";
		}
	}

	applyStepVisibility();
	window.jQuery(document.body).on("updated_checkout", function () {
		applyStepVisibility();
		setTimeout(clearAutoSelectedPaymentMethod, 50);
	});
	setTimeout(clearAutoSelectedPaymentMethod, 50);
})();

/**
 * اینپوتِ پرشِ صفحه در پاژینیشنِ آرشیوِ محصولات (woocommerce/loop/pagination.php).
 * data-jluxe-pagination-base الگوی نهاییِ URL با placeholderِ %#% رو داره؛
 * قالبِ PHP قبلاً base/formatِ استانداردِ ووکامرس رو با هم ترکیب کرده. این‌جا
 * فقط شماره‌ی واردشده رو (چه فارسی چه لاتین) در جای خالی می‌ذاریم و ناوبری
 * می‌کنیم؛ هیچ منطقِ کوئری/صفحه‌بندیِ جدیدی ساخته نمی‌شه.
 */
(function () {
	function faToEnDigits(str) {
		var fa = "۰۱۲۳۴۵۶۷۸۹";
		return String(str).replace(/[۰-۹]/g, function (ch) {
			return String(fa.indexOf(ch));
		});
	}

	function goToPage(nav, input) {
		var total = parseInt(nav.getAttribute("data-jluxe-pagination-total"), 10) || 1;
		var base = nav.getAttribute("data-jluxe-pagination-base") || "";
		var n = parseInt(faToEnDigits(input.value), 10);
		if (!n || n < 1) {
			n = 1;
		}
		if (n > total) {
			n = total;
		}
		if (!base) {
			return;
		}
		window.location.href = base.replace("%#%", String(n));
	}

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Enter") {
			return;
		}
		var input = event.target.closest("[data-jluxe-pagination-input]");
		if (!input) {
			return;
		}
		event.preventDefault();
		goToPage(input.closest(".jluxe-shop-pagination"), input);
	});

	document.addEventListener(
		"blur",
		function (event) {
			var input = event.target.closest && event.target.closest("[data-jluxe-pagination-input]");
			if (!input) {
				return;
			}
			var nav = input.closest(".jluxe-shop-pagination");
			var total = parseInt(nav.getAttribute("data-jluxe-pagination-total"), 10) || 1;
			var current = parseInt(faToEnDigits(input.value), 10);
			// اگه کاربر واقعاً عددِ متفاوتی وارد کرده، ناوبری کن؛ وگرنه (فقط
			// روی اینپوت کلیک کرده و بدونِ تغییر ازش خارج شده) کاری نکن.
			var originalMatch = input.defaultValue && parseInt(faToEnDigits(input.defaultValue), 10);
			if (current && current !== originalMatch && current >= 1 && current <= total) {
				goToPage(nav, input);
			}
		},
		true
	);
})();

/* R321 — Product-description gallery links open in an accessible, navigable lightbox. */
(function () {
	var descriptionSelector = '.jluxe-product-description-content, [data-jluxe-short-desc-text]';
	var gallerySelector     = '.wp-block-gallery, .gallery, .blocks-gallery-grid';
	var modalSequence       = 0;

	function faNumber( value ) {
		var digits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
		return String( value ).replace( /\d/g, function ( digit ) { return digits[ parseInt( digit, 10 ) ] || digit; } );
	}

	function normalizedUrl( value ) {
		if ( ! value ) { return ''; }
		var link = document.createElement( 'a' );
		link.href = String( value );
		var path = link.pathname || '/';
		path = path.replace( /\/+$/, '' );
		return link.protocol.toLowerCase() + '//' + link.host.toLowerCase() + path;
	}

	function sameUrl( first, second ) {
		return !!first && !!second && normalizedUrl( first ) === normalizedUrl( second );
	}

	function isImageUrl( value ) {
		return /\.(?:avif|bmp|gif|jpe?g|png|svg|webp)(?:$|[?#])/i.test( String( value || '' ) );
	}

	function isGifUrl( value ) {
		return /\.gif(?:$|[?#])/i.test( String( value || '' ) );
	}

	function largestSrcsetUrl( srcset ) {
		if ( ! srcset ) { return ''; }
		var bestUrl = '';
		var bestSize = -1;
		String( srcset ).split( ',' ).forEach( function ( candidate ) {
			var parts = candidate.trim().split( /\s+/ );
			if ( ! parts[ 0 ] ) { return; }
			var descriptor = parts[ 1 ] || '1x';
			var width = descriptor.match( /^(\d+)w$/ );
			var density = descriptor.match( /^(\d+(?:\.\d+)?)x$/ );
			var size = width ? parseInt( width[ 1 ], 10 ) : ( density ? parseFloat( density[ 1 ] ) * 1000000 : 1 );
			if ( size > bestSize ) { bestSize = size; bestUrl = parts[ 0 ]; }
		} );
		return bestUrl;
	}

	function classifyImageLink( anchor, image ) {
		if ( ! anchor || ! anchor.href ) { return { eligible: false, media: false }; }
		var href = anchor.href;
		var attachmentUrl = image.getAttribute( 'data-jluxe-attachment-url' ) || '';
		var mediaUrl = image.getAttribute( 'data-jluxe-media-url' ) || '';
		var fullUrl = image.getAttribute( 'data-jluxe-full-src' ) || '';
		var attachmentPage = sameUrl( href, attachmentUrl );
		var mediaFile = sameUrl( href, mediaUrl ) || sameUrl( href, fullUrl ) || isImageUrl( href );
		return { eligible: attachmentPage || mediaFile, media: mediaFile };
	}

	function slideSource( image, anchor, linkInfo ) {
		var mediaUrl = image.getAttribute( 'data-jluxe-media-url' ) || '';
		if ( isGifUrl( mediaUrl ) ) { return mediaUrl; }
		var fullUrl = image.getAttribute( 'data-jluxe-full-src' ) || '';
		if ( fullUrl ) { return fullUrl; }
		if ( linkInfo.media && anchor && anchor.href ) { return anchor.href; }
		var current = image.currentSrc || image.getAttribute( 'src' ) || image.getAttribute( 'data-src' ) || '';
		if ( isGifUrl( current ) ) { return current; }
		return largestSrcsetUrl( image.getAttribute( 'srcset' ) ) || current;
	}

	function imageAnchor( image, gallery ) {
		var parent = image.parentElement;
		while ( parent && parent !== gallery ) {
			if ( 'A' === parent.tagName && parent.hasAttribute( 'href' ) ) { return parent; }
			parent = parent.parentElement;
		}
		return null;
	}

	function hasGalleryClass( element ) {
		var classes = element && element.classList;
		return !!classes && ( classes.contains( 'wp-block-gallery' ) || classes.contains( 'gallery' ) || classes.contains( 'blocks-gallery-grid' ) );
	}

	function hasGalleryAncestor( element, boundary ) {
		var parent = element.parentElement;
		while ( parent && parent !== boundary ) {
			if ( hasGalleryClass( parent ) ) { return true; }
			parent = parent.parentElement;
		}
		return false;
	}

	function makeButton( action, label, path ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'jluxe-description-gallery-modal__button';
		button.setAttribute( 'data-jluxe-gallery-modal-' + action, '' );
		button.setAttribute( 'aria-label', label );
		if ( 'close' === action ) { button.className += ' jluxe-description-gallery-modal__close'; }
		button.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="' + path + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		return button;
	}

	function buildModal( modalId, entries ) {
		var modal = document.createElement( 'div' );
		modal.id = modalId;
		modal.className = 'jluxe-description-gallery-modal hidden opacity-0';
		modal.setAttribute( 'data-jluxe-gallery-modal', '' );
		modal.setAttribute( 'data-jluxe-gallery-modal-current', '0' );
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', 'گالری تصاویر توضیحات محصول' );
		modal.setAttribute( 'aria-hidden', 'true' );
		modal.setAttribute( 'tabindex', '-1' );

		var top = document.createElement( 'div' );
		top.className = 'jluxe-description-gallery-modal__top';
		var counter = document.createElement( 'span' );
		counter.className = 'jluxe-description-gallery-modal__counter';
		counter.setAttribute( 'data-jluxe-gallery-modal-counter', '' );
		counter.setAttribute( 'aria-live', 'polite' );
		counter.textContent = faNumber( 1 ) + ' / ' + faNumber( entries.length );
		top.appendChild( counter );
		top.appendChild( makeButton( 'close', 'بستن گالری', 'm6 6 12 12M18 6 6 18' ) );
		modal.appendChild( top );

		var stage = document.createElement( 'div' );
		stage.className = 'jluxe-description-gallery-modal__stage';
		if ( entries.length > 1 ) { stage.appendChild( makeButton( 'prev', 'تصویر قبلی', 'm14 6-6 6 6 6' ) ); }
		var track = document.createElement( 'div' );
		track.className = 'jluxe-description-gallery-modal__track';
		track.setAttribute( 'data-jluxe-gallery-modal-track', '' );
		entries.forEach( function ( entry, index ) {
			var slide = document.createElement( 'img' );
			slide.setAttribute( 'data-jluxe-gallery-modal-image', '' );
			slide.setAttribute( 'data-src', entry.src );
			if ( entry.srcset ) { slide.setAttribute( 'data-srcset', entry.srcset ); }
			if ( entry.sizes ) { slide.setAttribute( 'data-sizes', entry.sizes ); }
			slide.setAttribute( 'alt', entry.alt || ( 'تصویر ' + faNumber( index + 1 ) ) );
			slide.setAttribute( 'aria-hidden', 'true' );
			slide.setAttribute( 'draggable', 'false' );
			track.appendChild( slide );
		} );
		stage.appendChild( track );
		if ( entries.length > 1 ) { stage.appendChild( makeButton( 'next', 'تصویر بعدی', 'm10 6 6 6-6 6' ) ); }
		modal.appendChild( stage );
		document.body.appendChild( modal );
	}

	function initializeGallery( gallery ) {
		if ( '1' === gallery.getAttribute( 'data-jluxe-description-gallery-initialized' ) ) { return; }
		var images = gallery.querySelectorAll( 'img' );
		var entries = [];
		var hasOpenableLink = false;
		Array.prototype.forEach.call( images, function ( image ) {
			var anchor = imageAnchor( image, gallery );
			var linkInfo = classifyImageLink( anchor, image );
			var src = slideSource( image, anchor, linkInfo );
			if ( ! src ) { return; }
			if ( isGifUrl( src ) && ! image.getAttribute( 'data-jluxe-full-src' ) && !( linkInfo.media && anchor ) ) { return; }
			entries.push( {
				image: image,
				anchor: anchor,
				linkInfo: linkInfo,
				src: src,
				srcset: isGifUrl( src ) ? '' : ( image.getAttribute( 'data-jluxe-full-srcset' ) || '' ),
				sizes: isGifUrl( src ) ? '' : ( image.getAttribute( 'data-jluxe-full-sizes' ) || ( image.getAttribute( 'data-jluxe-full-srcset' ) ? '90vw' : '' ) ),
				alt: image.getAttribute( 'alt' ) || '',
			} );
			if ( linkInfo.eligible ) { hasOpenableLink = true; }
		} );
		if ( ! hasOpenableLink || ! entries.length ) { return; }

		modalSequence += 1;
		var modalId = 'jluxe-description-gallery-modal-' + modalSequence;
		while ( document.getElementById( modalId ) ) {
			modalSequence += 1;
			modalId = 'jluxe-description-gallery-modal-' + modalSequence;
		}
		gallery.setAttribute( 'data-jluxe-description-gallery-initialized', '1' );
		gallery.setAttribute( 'data-jluxe-gallery', '' );
		gallery.setAttribute( 'data-jluxe-gallery-current', '0' );
		gallery.setAttribute( 'data-jluxe-gallery-modal-id', modalId );
		entries.forEach( function ( entry, index ) {
			if ( ! entry.anchor || ! entry.linkInfo.eligible ) { return; }
			entry.anchor.setAttribute( 'data-jluxe-gallery-open', String( index ) );
			entry.anchor.setAttribute( 'data-jluxe-description-gallery-trigger', '' );
			entry.anchor.setAttribute( 'aria-haspopup', 'dialog' );
			entry.anchor.setAttribute( 'aria-controls', modalId );
			if ( ! entry.alt && ! entry.anchor.hasAttribute( 'aria-label' ) ) {
				entry.anchor.setAttribute( 'aria-label', 'باز کردن تصویر ' + faNumber( index + 1 ) + ' در گالری' );
			}
		} );
		buildModal( modalId, entries );
	}

	function initializeDescriptionGalleries() {
		if ( ! document.body ) { return; }
		var scopes = document.querySelectorAll( descriptionSelector );
		Array.prototype.forEach.call( scopes, function ( scope ) {
			var galleries = scope.querySelectorAll( gallerySelector );
			Array.prototype.forEach.call( galleries, function ( gallery ) {
				if ( hasGalleryAncestor( gallery, scope ) ) { return; }
				initializeGallery( gallery );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initializeDescriptionGalleries );
	} else {
		initializeDescriptionGalleries();
	}
})();

/**
 * پاپ‌آپِ آلبومِ تصاویرِ محصول (woocommerce/single-product/product-image.php):
 * کلیک روی هر تامبنیلِ ردیفِ ثابتِ زیرِ عکس (data-jluxe-gallery-open) این
 * پاپ‌آپِ تمام‌صفحه رو دقیقاً از همون ایندکس باز می‌کنه. ناوبری با دکمه‌های
 * قبلی/بعدی، کلیدِ Escape، کلیک رویِ پس‌زمینه، و کشیدنِ لمسی (swipe) — همه
 * روی یک منبعِ حقیقتِ واحد (data-jluxe-gallery-modal-current رویِ خودِ
 * مودال) کار می‌کنن.
 */
(function () {
	/*
	 * باگِ واقعیِ گزارش‌شده (با اسکرین‌شاتِ کاربر تأیید شد): پاپ‌آپ چون
	 * همون‌جایی که تویِ HTML نوشته شده (داخلِ گریدِ ۳ستونیِ فرمِ محصول)
	 * می‌مونه، z-index:100اش فقط در برابرِ خواهروبرادرهایِ همون گرید
	 * مقایسه می‌شه، نه کلِ صفحه — عناصرِ خارج از اون گرید (نوارِ چسبانِ
	 * «معرفی/سوالات متداول/...» زیرِ گالری، حتی خودِ باکسِ قیمت) می‌تونن
	 * روش بیفتن. دقیقاً همون مشکلی که مینی‌کارت/دراورِ دسته‌بندی (کامپوننتِ
	 * React) با createPortal(..., document.body) حلش کردن؛ این‌جا چون
	 * PHP خام و JSِ ساده‌ست، همون کار رو دستی انجام می‌دیم: یک‌بار موقعِ
	 * لودِ صفحه پاپ‌آپ رو از جایِ اصلیش می‌کَنیم و مستقیم زیرِ <body>
	 * می‌ذاریم — دیگه هیچ ancestor‌ای نمی‌تونه stacking context بشکنه.
	 */
	function relocateModalsToBody() {
		document.querySelectorAll("[data-jluxe-gallery-modal]").forEach(function (modal) {
			if (modal.parentElement !== document.body) {
				document.body.appendChild(modal);
			}
		});
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", relocateModalsToBody);
	} else {
		relocateModalsToBody();
	}

	function faDigits(n) {
		var map = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
		return String(n)
			.split("")
			.map(function (d) {
				return map[d] || d;
			})
			.join("");
	}

	function imageZoomState(img) {
		return {
			scale: parseFloat(img.getAttribute("data-jluxe-zoom-scale")) || 1,
			x: parseFloat(img.getAttribute("data-jluxe-zoom-x")) || 0,
			y: parseFloat(img.getAttribute("data-jluxe-zoom-y")) || 0,
		};
	}

	function setImageZoom(img, scale, x, y) {
		scale = Math.max(1, Math.min(3.5, scale));
		var width = img.offsetWidth || img.clientWidth || 0;
		var height = img.offsetHeight || img.clientHeight || 0;
		var maxX = Math.max(0, (width * (scale - 1)) / 2);
		var maxY = Math.max(0, (height * (scale - 1)) / 2);
		x = Math.max(-maxX, Math.min(maxX, x));
		y = Math.max(-maxY, Math.min(maxY, y));
		img.setAttribute("data-jluxe-zoom-scale", String(Math.round(scale * 1000) / 1000));
		img.setAttribute("data-jluxe-zoom-x", String(x));
		img.setAttribute("data-jluxe-zoom-y", String(y));
		img.style.transform = "translate3d(" + x + "px," + y + "px,0) scale(" + scale + ")";
	}

	function resetImageZoom(img) {
		img.setAttribute("data-jluxe-zoom-scale", "1");
		img.setAttribute("data-jluxe-zoom-x", "0");
		img.setAttribute("data-jluxe-zoom-y", "0");
		img.style.transform = "translate3d(0,0,0) scale(1)";
		img.style.cursor = "zoom-in";
	}

	function showImage(modal, index) {
		var images = modal.querySelectorAll("[data-jluxe-gallery-modal-image]");
		if (index < 0 || index >= images.length) {
			return;
		}
		modal.setAttribute("data-jluxe-gallery-modal-current", String(index));
		images.forEach(function (img, i) {
			var active = i === index;
			img.style.opacity = active ? "1" : "0";
			img.setAttribute("aria-hidden", active ? "false" : "true");
			img.classList.toggle("pointer-events-none", !active);
			if (active) {
				var src = img.getAttribute("data-src");
				var srcset = img.getAttribute("data-srcset");
				var sizes = img.getAttribute("data-sizes");
				if (src && !img.getAttribute("src")) {
					img.setAttribute("src", src);
				}
				if (srcset && !img.getAttribute("srcset")) {
					img.setAttribute("srcset", srcset);
				}
				if (sizes && !img.getAttribute("sizes")) {
					img.setAttribute("sizes", sizes);
				}
				resetImageZoom(img);
			} else {
				img.style.transform = "scale(0.96)";
			}
		});
		var counter = modal.querySelector("[data-jluxe-gallery-modal-counter]");
		if (counter) {
			counter.textContent = faDigits(index + 1) + " / " + faDigits(images.length);
		}
	}

	/*
	 * انیمیشنِ نرمِ باز/بسته‌شدن: صرفِ برداشتنِ کلاسِ hidden کافی نیست —
	 * چون transition روی opacity تعریف شده، مرورگر باید بینِ «حالتِ ۰
	 * (هنوز مخفی)» و «حالتِ ۱ (نمایان)» یک فریمِ واقعیِ paint‌شده ببینه، وگرنه
	 * مستقیم به حالتِ نهایی می‌پره (دقیقاً همون باگی که قبلاً برایِ
	 * بازوبسته‌شدنِ مینی‌کارت هم پیش اومده بود) — برای همین از double-rAF
	 * استفاده می‌شه، نه یک setTimeout ساده.
	 */
	function openModal(modal, index, returnFocusElement) {
		if (modal._jluxeCloseTimer) {
			window.clearTimeout(modal._jluxeCloseTimer);
			modal._jluxeCloseTimer = null;
		}
		modal._jluxeReturnFocus = returnFocusElement && returnFocusElement.focus ? returnFocusElement : document.activeElement;
		modal._jluxePreviousOverflow = document.body.style.overflow;
		modal.classList.remove("hidden");
		modal.classList.add("flex");
		modal.setAttribute("aria-hidden", "false");
		document.body.style.overflow = "hidden";
		showImage(modal, index);
		var closeButton = modal.querySelector("[data-jluxe-gallery-modal-close]");
		if (closeButton && closeButton.focus) {
			closeButton.focus();
		}
		/*
		 * باگِ واقعیِ کشف‌شده حینِ تست: تکیه‌کردنِ صرف روی double-rAF (بدونِ
		 * fallback) باعث می‌شد پاپ‌آپ ساختاری باز بشه (hidden برداشته بشه) ولی
		 * opacity-0 هیچ‌وقت برداشته نشه — یعنی کاربر چیزی نمی‌دید، انگار اصلاً
		 * باز نشده — دقیقاً همون کلاسِ باگی که قبلاً تویِ مینی‌کارت (MiniCart.tsx)
		 * هم پیش اومده بود و اونجا با یک setTimeout پشتیبان حل شده بود. همون
		 * الگو این‌جا هم تکرار شده: هرکدوم از rAF/setTimeout زودتر برسه، برنده‌ست.
		 */
		var done = false;
		function reveal() {
			if (done) {
				return;
			}
			done = true;
			modal.classList.remove("opacity-0");
		}
		requestAnimationFrame(function () {
			requestAnimationFrame(reveal);
		});
		window.setTimeout(reveal, 60);
	}

	function closeModal(modal) {
		modal.classList.add("opacity-0");
		modal.setAttribute("aria-hidden", "true");
		document.body.style.overflow = modal._jluxePreviousOverflow || "";
		modal._jluxeCloseTimer = window.setTimeout(function () {
			modal.classList.add("hidden");
			modal.classList.remove("flex");
			modal._jluxeCloseTimer = null;
			var returnFocus = modal._jluxeReturnFocus;
			if (returnFocus && document.contains(returnFocus) && returnFocus.focus) {
				returnFocus.focus();
			}
		}, 240);
	}

	function currentIndex(modal) {
		return parseInt(modal.getAttribute("data-jluxe-gallery-modal-current"), 10) || 0;
	}

	function modalForGallery(gallery) {
		var modalId = gallery && gallery.getAttribute("data-jluxe-gallery-modal-id");
		if (modalId) {
			return document.getElementById(modalId);
		}
		return document.querySelector("[data-jluxe-gallery-modal]");
	}

	document.addEventListener("click", function (event) {
		// هم تامبنیل‌های ثابتِ زیرِ عکس (data-jluxe-gallery-open) هم دکمه‌ی
		// «مشاهده گالری» رویِ خودِ تصویرِ اصلی (data-jluxe-gallery-open-current)
		// — دومی همون ایندکسِ فعلاً نمایش‌داده‌شده رو از data-jluxe-gallery-current
		// خودِ گالری (که listener جداگانه‌ی prev/next نگه‌داری‌اش می‌کنه) می‌خونه.
		// نکته: خودِ پاپ‌آپ دیگه داخلِ [data-jluxe-gallery] نیست (relocateModalsToBody
		// اون رو مستقیم زیرِ body برده)، پس دیگه نمی‌شه با gallery.querySelector
		// پیداش کرد. گالری‌های توضیحات شناسهٔ مودالِ خودشان را روی ریشه دارند؛
		// گالری اصلی همان انتخابگر سراسریِ قبلی را به‌عنوان fallback نگه می‌دارد.
		var clickTarget = event.target;
		var opener = clickTarget.closest && clickTarget.closest("[data-jluxe-gallery-open]");
		var openCurrent = clickTarget.closest && clickTarget.closest("[data-jluxe-gallery-open-current]");
		if (opener || openCurrent) {
			var trigger = opener || openCurrent;
			var gallery = trigger.closest("[data-jluxe-gallery]");
			var modal = modalForGallery(gallery);
			if (modal && gallery) {
				if ("A" === trigger.tagName) { event.preventDefault(); }
				var startIndex = opener
					? parseInt(opener.getAttribute("data-jluxe-gallery-open"), 10) || 0
					: parseInt(gallery.getAttribute("data-jluxe-gallery-current"), 10) || 0;
				openModal(modal, startIndex, trigger);
			}
			return;
		}

		var modalEl = event.target.closest("[data-jluxe-gallery-modal]");
		if (!modalEl) {
			return;
		}

		if (event.target.closest("[data-jluxe-gallery-modal-close]") || event.target === modalEl) {
			closeModal(modalEl);
			return;
		}

		var images = modalEl.querySelectorAll("[data-jluxe-gallery-modal-image]");
		if (event.target.closest("[data-jluxe-gallery-modal-prev]")) {
			showImage(modalEl, (currentIndex(modalEl) - 1 + images.length) % images.length);
		} else if (event.target.closest("[data-jluxe-gallery-modal-next]")) {
			showImage(modalEl, (currentIndex(modalEl) + 1) % images.length);
		}
	});

		document.addEventListener("keydown", function (event) {
		var openModalEl = document.querySelector('[data-jluxe-gallery-modal][aria-hidden="false"]');
		if (!openModalEl) {
			return;
		}
		if ("Escape" === event.key) {
			closeModal(openModalEl);
			return;
		}
		var images = openModalEl.querySelectorAll("[data-jluxe-gallery-modal-image]");
		if ("ArrowLeft" === event.key) {
			event.preventDefault();
			showImage(openModalEl, (currentIndex(openModalEl) - 1 + images.length) % images.length);
		} else if ("ArrowRight" === event.key) {
			event.preventDefault();
			showImage(openModalEl, (currentIndex(openModalEl) + 1) % images.length);
		} else if ("Tab" === event.key) {
			var focusable = openModalEl.querySelectorAll('button:not([disabled]), [tabindex="0"]');
			if (!focusable.length) {
				return;
			}
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});

	// Touch gestures: swipe between slides at 1x; pinch/double-tap to zoom;
	// drag the zoomed image in either direction and clamp it inside the frame.
	var touchGesture = null;
	var lastTap = { img: null, x: 0, y: 0, time: 0 };
	var mousePan = null;

	function activeModalImage(track) {
		return track.querySelector('[data-jluxe-gallery-modal-image][aria-hidden="false"]') || track.querySelector("[data-jluxe-gallery-modal-image]");
	}

	function pointDistance(a, b) {
		var dx = b.clientX - a.clientX;
		var dy = b.clientY - a.clientY;
		return Math.sqrt(dx * dx + dy * dy);
	}

	function pointMidpoint(a, b) {
		return { x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2 };
	}

	function localPoint(track, point) {
		var rect = track.getBoundingClientRect();
		return { x: point.x - rect.left - rect.width / 2, y: point.y - rect.top - rect.height / 2 };
	}

	function startPinchGesture(gesture, touches) {
		var midpoint = pointMidpoint(touches[0], touches[1]);
		var local = localPoint(gesture.track, midpoint);
		var state = imageZoomState(gesture.img);
		gesture.mode = "pinch";
		gesture.startDistance = Math.max(1, pointDistance(touches[0], touches[1]));
		gesture.startScale = state.scale;
		gesture.startPanX = state.x;
		gesture.startPanY = state.y;
		gesture.startMidX = local.x;
		gesture.startMidY = local.y;
		gesture.moved = true;
	}

	document.addEventListener(
		"touchstart",
		function (event) {
			var track = event.target.closest && event.target.closest("[data-jluxe-gallery-modal-track]");
			if (!track) {
				return;
			}
			var img = activeModalImage(track);
			if (!img) {
				return;
			}
			var state = imageZoomState(img);
			var touch = event.touches[0];
			touchGesture = {
				track: track,
				modal: track.closest("[data-jluxe-gallery-modal]"),
				img: img,
				mode: state.scale > 1.01 ? "pan" : "swipe",
				startX: touch ? touch.clientX : 0,
				startY: touch ? touch.clientY : 0,
				lastX: touch ? touch.clientX : 0,
				lastY: touch ? touch.clientY : 0,
				startAt: Date.now(),
				startScale: state.scale,
				startPanX: state.x,
				startPanY: state.y,
				moved: false,
				doubleTapTarget: event.target.closest("[data-jluxe-gallery-modal-image]") === img,
			};
			if (event.touches.length >= 2) {
				startPinchGesture(touchGesture, event.touches);
				if (event.cancelable) event.preventDefault();
			}
		},
		{ passive: false }
	);

	document.addEventListener(
		"touchmove",
		function (event) {
			if (!touchGesture) {
				return;
			}
			if (event.touches.length >= 2) {
				if (touchGesture.mode !== "pinch") {
					startPinchGesture(touchGesture, event.touches);
				}
				var midpoint = pointMidpoint(event.touches[0], event.touches[1]);
				var local = localPoint(touchGesture.track, midpoint);
				var ratio = pointDistance(event.touches[0], event.touches[1]) / touchGesture.startDistance;
				var scale = Math.max(1, Math.min(3.5, touchGesture.startScale * ratio));
				var relative = scale / touchGesture.startScale;
				setImageZoom(
					touchGesture.img,
					scale,
					local.x - relative * (touchGesture.startMidX - touchGesture.startPanX),
					local.y - relative * (touchGesture.startMidY - touchGesture.startPanY),
				);
				if (event.cancelable) event.preventDefault();
				touchGesture.moved = true;
				return;
			}
			if (event.touches.length !== 1) {
				return;
			}
			var point = event.touches[0];
			var dx = point.clientX - touchGesture.startX;
			var dy = point.clientY - touchGesture.startY;
			touchGesture.lastX = point.clientX;
			touchGesture.lastY = point.clientY;
			if (touchGesture.mode === "pan") {
				setImageZoom(touchGesture.img, touchGesture.startScale, touchGesture.startPanX + dx, touchGesture.startPanY + dy);
				if (event.cancelable) event.preventDefault();
				touchGesture.moved = Math.abs(dx) > 2 || Math.abs(dy) > 2;
			} else if (Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) {
				if (event.cancelable) event.preventDefault();
				touchGesture.moved = true;
			}
		},
		{ passive: false }
	);

	document.addEventListener(
		"touchend",
		function (event) {
			if (!touchGesture) {
				return;
			}
			if (event.touches.length > 0) {
				if (touchGesture.mode === "pinch") {
					var remaining = event.touches[0];
					var state = imageZoomState(touchGesture.img);
					touchGesture.mode = "pan";
					touchGesture.startX = remaining.clientX;
					touchGesture.startY = remaining.clientY;
					touchGesture.startScale = state.scale;
					touchGesture.startPanX = state.x;
					touchGesture.startPanY = state.y;
					touchGesture.moved = true;
				}
				return;
			}
			var gesture = touchGesture;
			touchGesture = null;
			if (!gesture.modal) {
				return;
			}
			var changed = event.changedTouches[0];
			var endX = changed ? changed.clientX : gesture.lastX;
			var endY = changed ? changed.clientY : gesture.lastY;
			var dx = endX - gesture.startX;
			var dy = endY - gesture.startY;
			if (gesture.mode === "swipe" && gesture.moved && Math.abs(dx) >= 40 && Math.abs(dx) > Math.abs(dy)) {
				var images = gesture.modal.querySelectorAll("[data-jluxe-gallery-modal-image]");
				// dir=rtl: کشیدن به راست (deltaX>0) یعنی «قبلی»، به چپ یعنی «بعدی».
				showImage(gesture.modal, dx > 0 ? (currentIndex(gesture.modal) - 1 + images.length) % images.length : (currentIndex(gesture.modal) + 1) % images.length);
				lastTap = { img: null, x: 0, y: 0, time: 0 };
				return;
			}
			if ((gesture.mode !== "swipe" && gesture.mode !== "pan") || gesture.moved || !gesture.doubleTapTarget || Math.abs(dx) > 16 || Math.abs(dy) > 16 || Date.now() - gesture.startAt > 350) {
				return;
			}
			var now = Date.now();
			if (lastTap.img === gesture.img && now - lastTap.time < 350 && Math.abs(endX - lastTap.x) < 32 && Math.abs(endY - lastTap.y) < 32) {
				var zoom = imageZoomState(gesture.img);
				if (zoom.scale > 1.01) {
					resetImageZoom(gesture.img);
				} else {
					setImageZoom(gesture.img, 2.5, 0, 0);
				}
				lastTap = { img: null, x: 0, y: 0, time: 0 };
				if (event.cancelable) event.preventDefault();
			} else {
				lastTap = { img: gesture.img, x: endX, y: endY, time: now };
			}
		},
		{ passive: false }
	);
	document.addEventListener("touchcancel", function () { touchGesture = null; }, { passive: true });

	// Mouse drag is useful for panning a zoomed product on desktop as well.
	document.addEventListener("mousedown", function (event) {
		if (event.button !== 0) return;
		var img = event.target.closest && event.target.closest('[data-jluxe-gallery-modal-image][aria-hidden="false"]');
		if (!img) return;
		var state = imageZoomState(img);
		if (state.scale <= 1.01) return;
		event.preventDefault();
		img.style.cursor = "grabbing";
		mousePan = { img: img, x: event.clientX, y: event.clientY, panX: state.x, panY: state.y, scale: state.scale };
	});
	document.addEventListener("mousemove", function (event) {
		if (!mousePan) return;
		setImageZoom(mousePan.img, mousePan.scale, mousePan.panX + event.clientX - mousePan.x, mousePan.panY + event.clientY - mousePan.y);
	});
	document.addEventListener("mouseup", function () {
		if (mousePan) {
			mousePan.img.style.cursor = "grab";
			mousePan = null;
		}
	});
})();
