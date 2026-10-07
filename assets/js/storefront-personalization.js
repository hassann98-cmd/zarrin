/* First-party wishlist, recently-viewed products, and back-in-stock opt-in. */
(function () {
	var STORAGE_KEY = "jluxe_wishlist";
	var GUEST_KEY = "jluxe_wishlist_guest";
	var USER_KEY = "jluxe_wishlist_user";
	var PENDING_KEY = "jluxe_wishlist_pending_user";
	var settings = window.jluxePersonalizationSettings || {};
	var themeSettings = window.JLuxeThemeSettings || {};
	var isLoggedIn = settings.isLoggedIn === true || !!(themeSettings.auth && themeSettings.auth.isLoggedIn);
	var authState = isLoggedIn ? "loading" : "guest";
	var activeUserId = "";
	var restNonce = "";
	var changedWhileSyncing = false;
	var writeQueue = Promise.resolve();
	var cachedUserMarker = "";
	try { cachedUserMarker = window.localStorage.getItem(USER_KEY) || ""; } catch (error) {}
	var hideUnverifiedAccountCache = isLoggedIn && !!cachedUserMarker;

	function normalizeIds(ids) {
		if (!Array.isArray(ids)) { return []; }
		var seen = Object.create(null);
		var result = [];
		ids.forEach(function (value) {
			var id = String(value == null ? "" : value).trim();
			if (!/^\d+$/.test(id) || Number(id) < 1 || seen[id]) { return; }
			seen[id] = true;
			result.push(id);
			if (result.length >= 200) { return; }
		});
		return result.slice(0, 200);
	}

	function readIds(key) {
		try { return normalizeIds(JSON.parse(window.localStorage.getItem(key) || "[]")); }
		catch (error) { return []; }
	}

	function writeIds(key, ids) {
		try { window.localStorage.setItem(key, JSON.stringify(normalizeIds(ids))); return true; }
		catch (error) { return false; }
	}

	function removeKey(key) {
		try { window.localStorage.removeItem(key); } catch (error) {}
	}

	function mergeIds() {
		var merged = [];
		Array.prototype.forEach.call(arguments, function (items) {
			normalizeIds(items).forEach(function (id) {
				if (merged.indexOf(id) === -1 && merged.length < 200) { merged.push(id); }
			});
		});
		return merged;
	}

	function setButtonState(button, active) {
		button.setAttribute("aria-pressed", active ? "true" : "false");
		var label = button.getAttribute(active ? "data-jluxe-wishlist-active-label" : "data-jluxe-wishlist-inactive-label");
		if (label) { button.setAttribute("aria-label", label); }
		button.classList.toggle("text-boom-sale", active);
		var svg = button.querySelector("svg");
		if (svg) { svg.setAttribute("fill", active ? "currentColor" : "none"); }
	}

	function renderWishlist(ids) {
		var activeIds = normalizeIds(ids);
		document.querySelectorAll("[data-jluxe-wishlist-toggle]").forEach(function (button) {
			setButtonState(button, activeIds.indexOf(button.getAttribute("data-jluxe-wishlist-toggle")) !== -1);
			if (hideUnverifiedAccountCache) { button.setAttribute("aria-disabled", "true"); }
			else { button.removeAttribute("aria-disabled"); }
		});
	}

	function getCurrentWishlist() {
		return readIds(STORAGE_KEY);
	}

	function setCurrentWishlist(ids) {
		var clean = normalizeIds(ids);
		writeIds(STORAGE_KEY, clean);
		renderWishlist(clean);
		return clean;
	}

	function requestSession() {
		if (typeof window.fetch !== "function" || !settings.sessionUrl) { return Promise.reject(new Error("No private session endpoint")); }
		var body = new FormData();
		body.set("action", "jluxe_session");
		return window.fetch(settings.sessionUrl, { method: "POST", body: body, credentials: "same-origin", cache: "no-store" })
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) { throw new Error("Private session unavailable"); }
				return payload.data;
			});
	}

	function requestAccountWishlist(method, ids) {
		var options = {
			method: method,
			credentials: "same-origin",
			cache: "no-store",
			headers: { "X-WP-Nonce": restNonce, "Content-Type": "application/json" },
		};
		if (method !== "GET") { options.body = JSON.stringify({ ids: normalizeIds(ids).map(Number) }); }
		return window.fetch(settings.wishlistUrl, options).then(function (response) {
			return response.json().then(function (payload) {
				if (!response.ok || !payload || !Array.isArray(payload.ids)) {
					throw new Error("Wishlist sync failed");
				}
				return normalizeIds(payload.ids);
			});
		});
	}

	function persistAccountWishlist(ids) {
		var desired = normalizeIds(ids);
		if (authState !== "account" || !activeUserId || !restNonce || typeof window.fetch !== "function") { return Promise.resolve(false); }
		try { window.localStorage.setItem(PENDING_KEY, activeUserId); } catch (error) {}
		writeQueue = writeQueue.catch(function () {}).then(function () {
			return requestAccountWishlist("PUT", desired);
		}).then(function (savedIds) {
			var storedUserId = "";
			try { storedUserId = String(window.localStorage.getItem(USER_KEY) || ""); } catch (error) {}
			if (activeUserId !== storedUserId) { return false; }
			var current = getCurrentWishlist();
			if (JSON.stringify(current) === JSON.stringify(desired)) {
				setCurrentWishlist(savedIds);
				removeKey(PENDING_KEY);
				removeKey(GUEST_KEY);
			}
			return true;
		}).catch(function () {
			// Keep an explicit dirty marker so an offline removal is not merged back on the next page.
			return false;
		});
		return writeQueue;
	}

	function useGuestWishlist(ids) {
		authState = "guest";
		activeUserId = "";
		hideUnverifiedAccountCache = false;
		var guestIds = mergeIds(readIds(GUEST_KEY), ids);
		writeIds(GUEST_KEY, guestIds);
		setCurrentWishlist(guestIds);
	}

	function syncAccountWishlist() {
		if (!isLoggedIn || !settings.wishlistUrl || !settings.sessionUrl || typeof window.fetch !== "function") {
			return Promise.resolve(false);
		}
		var initialCache = getCurrentWishlist();
		var initialGuest = readIds(GUEST_KEY);
		return requestSession().then(function (session) {
			var auth = session.auth || {};
			var userId = String(auth.userId || session.userId || "");
			if (!auth.isLoggedIn || !userId || !session.restNonce) {
				var previousAccount = "";
				try { previousAccount = window.localStorage.getItem(USER_KEY) || ""; } catch (error) {}
				if (previousAccount) {
					removeKey(STORAGE_KEY);
					removeKey(USER_KEY);
					removeKey(PENDING_KEY);
					useGuestWishlist(initialGuest);
				} else {
					useGuestWishlist(mergeIds(initialGuest, initialCache));
				}
				return false;
			}
			activeUserId = userId;
			restNonce = String(session.restNonce);
			var previousUser = "";
			var pendingUser = "";
			try {
				previousUser = window.localStorage.getItem(USER_KEY) || "";
				pendingUser = window.localStorage.getItem(PENDING_KEY) || "";
			} catch (error) {}
			var sameAccountCache = previousUser === userId ? initialCache : [];
			var legacyGuest = previousUser ? [] : initialCache;
			var guestItems = mergeIds(initialGuest, legacyGuest);
			try { window.localStorage.setItem(USER_KEY, userId); } catch (error) {}
			return requestAccountWishlist("GET").then(function (serverIds) {
				var localDirty = changedWhileSyncing || pendingUser === userId;
				var desired = localDirty
					? mergeIds(getCurrentWishlist(), guestItems)
					: mergeIds(serverIds, sameAccountCache, guestItems);
				hideUnverifiedAccountCache = false;
				setCurrentWishlist(desired);
				authState = "account";
				changedWhileSyncing = false;
				if (JSON.stringify(desired) === JSON.stringify(serverIds)) {
					removeKey(PENDING_KEY);
					removeKey(GUEST_KEY);
					return true;
				}
				return persistAccountWishlist(desired);
			});
		}).catch(function () {
			// A failed private-session request must never turn an account cache into guest data.
			authState = "loading";
			return false;
		});
	}

	// Render guest history immediately; do not expose a cached account list until the private session confirms its owner.
	renderWishlist(hideUnverifiedAccountCache ? readIds(GUEST_KEY) : getCurrentWishlist());
	if (!isLoggedIn) {
		var formerUser = "";
		try { formerUser = window.localStorage.getItem(USER_KEY) || ""; } catch (error) {}
		if (formerUser) {
			removeKey(STORAGE_KEY);
			removeKey(USER_KEY);
			removeKey(PENDING_KEY);
		}
		useGuestWishlist(readIds(GUEST_KEY).length ? readIds(GUEST_KEY) : getCurrentWishlist());
	} else {
		window.jluxeSyncWishlist = syncAccountWishlist;
		void syncAccountWishlist();
	}

	document.addEventListener("click", function (event) {
		var button = event.target.closest && event.target.closest("[data-jluxe-wishlist-toggle]");
		if (button) {
			if (hideUnverifiedAccountCache) { return; }
			var id = button.getAttribute("data-jluxe-wishlist-toggle");
			var list = getCurrentWishlist();
			var index = list.indexOf(id);
			if (index === -1) { list.push(id); } else { list.splice(index, 1); }
			list = setCurrentWishlist(list);
			if (authState === "account") {
				void persistAccountWishlist(list);
			} else if (authState === "loading") {
				changedWhileSyncing = true;
			} else {
				writeIds(GUEST_KEY, list);
			}
			return;
		}

		var shareButton = event.target.closest && event.target.closest("[data-jluxe-share]");
		if (!shareButton) { return; }
		var shareData = { title: document.title, url: window.location.href };
		if (navigator.share) {
			navigator.share(shareData).catch(function () {});
			return;
		}
		if (navigator.clipboard) {
			navigator.clipboard.writeText(shareData.url).then(function () {
				var original = shareButton.getAttribute("aria-label");
				shareButton.setAttribute("aria-label", "لینک کپی شد");
				window.setTimeout(function () { shareButton.setAttribute("aria-label", original); }, 2000);
			});
		}
	});

	window.addEventListener("storage", function (event) {
		if (event.key === STORAGE_KEY) { renderWishlist(getCurrentWishlist()); }
	});
	window.addEventListener("jluxe:auth-changed", function () {
		isLoggedIn = true;
		changedWhileSyncing = true;
		if (authState !== "account") { authState = "loading"; }
		void syncAccountWishlist();
	});
})();

(function () {
	var settings = window.jluxePersonalizationSettings || {};
	var STORAGE_KEY = "jluxe_recently_viewed";
	var MAX_RECENT = 8;

	function normalizeIds(ids) {
		if (!Array.isArray(ids)) { return []; }
		var seen = Object.create(null);
		var clean = [];
		ids.forEach(function (value) {
			var id = String(value == null ? "" : value).trim();
			if (!/^\d+$/.test(id) || Number(id) < 1 || seen[id]) { return; }
			seen[id] = true;
			clean.push(id);
		});
		return clean.slice(0, MAX_RECENT);
	}

	function readRecent() {
		try { return normalizeIds(JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]")); }
		catch (error) { return []; }
	}

	var productRoot = document.querySelector(".single-product [data-jluxe-product-id]") || document.querySelector("[data-jluxe-layout][data-jluxe-product-id]");
	var currentId = productRoot ? String(productRoot.getAttribute("data-jluxe-product-id") || "") : "";
	function rememberCurrent() {
		var recent = readRecent();
		if (/^\d+$/.test(currentId) && Number(currentId) > 0) {
			recent = [currentId].concat(recent.filter(function (id) { return id !== currentId; })).slice(0, MAX_RECENT);
			try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(recent)); } catch (error) {}
		}
		return recent;
	}
	var recent = rememberCurrent();
	if (!settings.recentProductsUrl || typeof window.fetch !== "function") { return; }

	function httpUrl(value) {
		if (!value) { return ""; }
		try {
			var url = new URL(String(value), window.location.href);
			return url.protocol === "http:" || url.protocol === "https:" ? url.href : "";
		} catch (error) { return ""; }
	}

	function span(className, text) {
		var element = document.createElement("span");
		element.className = className;
		if (text != null) { element.textContent = String(text); }
		return element;
	}

	function icon(pathData, className) {
		var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
		svg.setAttribute("class", className);
		svg.setAttribute("viewBox", "0 0 24 24");
		svg.setAttribute("fill", "none");
		svg.setAttribute("stroke", "currentColor");
		svg.setAttribute("stroke-width", "1.6");
		svg.setAttribute("stroke-linecap", "round");
		svg.setAttribute("stroke-linejoin", "round");
		svg.setAttribute("aria-hidden", "true");
		var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
		path.setAttribute("d", pathData);
		svg.appendChild(path);
		return svg;
	}

	function makeCard(item) {
		if (!item || !item.name) { return null; }
		var targetUrl = httpUrl(item.url);
		if (!targetUrl) { return null; }
		var card = document.createElement("article");
		card.className = "jluxe-recent-card";
		card.setAttribute("role", "listitem");
		var link = document.createElement("a");
		link.className = "jluxe-recent-card__link";
		link.href = targetUrl;
		var media = span("jluxe-recent-card__media");
		function showPlaceholder() {
			media.textContent = "";
			media.classList.add("jluxe-recent-card__media--placeholder");
			media.appendChild(icon("m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 18v-9M3 8l9 4 9-4M7.5 5.5l9 5", "jluxe-recent-card__placeholder-icon"));
			var placeholder = span("jluxe-recent-card__placeholder", "تصویر محصول");
			placeholder.setAttribute("aria-hidden", "true");
			media.appendChild(placeholder);
		}
		var imageUrl = httpUrl(item.image);
		if (imageUrl) {
			var image = document.createElement("img");
			image.className = "jluxe-recent-card__image";
			image.alt = String(item.imageAlt || item.name);
			image.loading = "lazy";
			image.decoding = "async";
			image.width = 320;
			image.height = 320;
			image.addEventListener("error", showPlaceholder, { once: true });
			image.src = imageUrl;
			media.appendChild(image);
		} else { showPlaceholder(); }
		var body = span("jluxe-recent-card__body");
		var name = span("jluxe-recent-card__name", item.name);
		var footer = span("jluxe-recent-card__footer");
		var prices = span("jluxe-recent-card__prices");
		if (item.inStock && item.regularPrice && item.regularPrice !== item.price) {
			var oldPrice = document.createElement("del");
			oldPrice.className = "jluxe-recent-card__old-price";
			oldPrice.appendChild(span("screen-reader-text", "قیمت قبلی: "));
			oldPrice.appendChild(document.createTextNode(String(item.regularPrice)));
			prices.appendChild(oldPrice);
		}
		var price = span("jluxe-recent-card__price", item.inStock ? (item.price || "") : "فعلاً ناموجود");
		if (!item.inStock) { price.classList.add("is-unavailable"); }
		prices.appendChild(price);
		var action = span("jluxe-recent-card__action", "مشاهده محصول");
		action.appendChild(icon("M19 12H5m6-6-6 6 6 6", "jluxe-recent-card__action-arrow"));
		footer.appendChild(prices);
		footer.appendChild(action);
		body.appendChild(name);
		body.appendChild(footer);
		link.appendChild(media);
		link.appendChild(body);
		card.appendChild(link);
		return card;
	}

	var refreshers = [];
	document.querySelectorAll("[data-jluxe-recent-products]").forEach(function (panel) {
		var list = panel.querySelector("[data-jluxe-recent-list]");
		if (!list) { return; }
		var count = panel.querySelector("[data-jluxe-recent-count]");
		var controls = panel.querySelector("[data-jluxe-recent-controls]");
		var prev = panel.querySelector("[data-jluxe-recent-prev]");
		var next = panel.querySelector("[data-jluxe-recent-next]");
		var requestId = 0;
		var lastKey = null;
		var frame = 0;

		function isRtl() { return window.getComputedStyle(list).direction !== "ltr"; }
		function updateControls() {
			var overflow = !panel.hidden && list.scrollWidth > list.clientWidth + 2;
			if (controls) { controls.hidden = !overflow; }
			if (overflow) { list.setAttribute("tabindex", "0"); } else { list.removeAttribute("tabindex"); }
			if (!prev || !next) { return; }
			if (!overflow || !list.firstElementChild) { prev.disabled = true; next.disabled = true; return; }
			var view = list.getBoundingClientRect();
			var first = list.firstElementChild.getBoundingClientRect();
			var last = list.lastElementChild.getBoundingClientRect();
			prev.disabled = isRtl() ? first.right <= view.right + 4 : first.left >= view.left - 4;
			next.disabled = isRtl() ? last.left >= view.left - 4 : last.right <= view.right + 4;
		}
		function scheduleControls() {
			if (frame) { return; }
			frame = window.requestAnimationFrame(function () { frame = 0; updateControls(); });
		}
		function move(direction) {
			var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
			var distance = Math.max(1, list.clientWidth * .85) * direction * (isRtl() ? -1 : 1);
			if (typeof list.scrollBy === "function") {
				list.scrollBy({ left: distance, behavior: reduceMotion ? "auto" : "smooth" });
			} else { list.scrollLeft += distance; }
			scheduleControls();
		}
		if (prev) { prev.addEventListener("click", function () { move(-1); }); }
		if (next) { next.addEventListener("click", function () { move(1); }); }
		list.addEventListener("scroll", scheduleControls, { passive: true });
		list.addEventListener("keydown", function (event) {
			if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || (event.key !== "ArrowLeft" && event.key !== "ArrowRight")) { return; }
			event.preventDefault();
			move((event.key === "ArrowLeft") === isRtl() ? 1 : -1);
		});
		if (typeof window.ResizeObserver === "function") {
			new window.ResizeObserver(scheduleControls).observe(list);
		} else { window.addEventListener("resize", scheduleControls, { passive: true }); }

		function refresh(history, force) {
			var ids = panel.getAttribute("data-jluxe-recent-context") === "product" && currentId
				? history.filter(function (id) { return id !== currentId; }) : history;
			var key = ids.slice(0, MAX_RECENT).join(",");
			if (!force && key === lastKey) { return; }
			var token = ++requestId;
			if (lastKey !== key || !ids.length) {
				list.textContent = "";
				panel.hidden = true;
				panel.removeAttribute("data-jluxe-recent-size");
				if (count) { count.textContent = ""; }
				updateControls();
			}
			lastKey = key;
			if (!ids.length) { return; }
			var separator = settings.recentProductsUrl.indexOf("?") === -1 ? "?" : "&";
			var url = settings.recentProductsUrl + separator + "ids=" + encodeURIComponent(key);
			window.fetch(url, { method: "GET", credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" } })
				.then(function (response) { return response.ok ? response.json() : null; })
				.then(function (payload) {
					if (token !== requestId) { return; }
					var items = payload && Array.isArray(payload.items) ? payload.items.slice(0, MAX_RECENT) : [];
					var fragment = document.createDocumentFragment();
					items.forEach(function (item) {
						var card = makeCard(item);
						if (card) { fragment.appendChild(card); }
					});
					list.textContent = "";
					list.appendChild(fragment);
					var size = list.children.length;
					panel.setAttribute("data-jluxe-recent-size", String(size));
					if (count) { count.textContent = String(size).replace(/\d/g, function (digit) { return "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]; }) + " محصول"; }
					panel.hidden = size === 0;
					scheduleControls();
				}).catch(function () { if (token === requestId) { lastKey = null; } });
		}
		refreshers.push(refresh);
		refresh(recent, false);
	});
	// A back/forward-cache restore does not re-execute this script. Refresh the
	// private list after a product visit, even if the homepage started empty.
	window.addEventListener("pageshow", function (event) {
		if (!event.persisted) { return; }
		var history = rememberCurrent();
		refreshers.forEach(function (refresh) { refresh(history, true); });
	});
	window.addEventListener("storage", function (event) {
		if (event.key !== STORAGE_KEY && event.key !== null) { return; }
		var history = readRecent();
		refreshers.forEach(function (refresh) { refresh(history, false); });
	});
})();

(function () {
	var settings = window.jluxePersonalizationSettings || {};
	var ajaxUrl = settings.ajaxUrl || (window.JLuxeThemeSettings && window.JLuxeThemeSettings.rest && window.JLuxeThemeSettings.rest.sessionUrl) || "";

	function updateVariationAlert(form, variation) {
		if (!form || form.closest(".jluxe-variant-modal")) { return; }
		var root = form.closest("[data-jluxe-product-id]");
		if (!root) { return; }
		var trigger = root.querySelector("[data-jluxe-stock-alert-trigger]");
		if (!trigger) { return; }
		var button = trigger.querySelector("[data-jluxe-stock-alert-open]");
		var dialog = document.getElementById(button ? button.getAttribute("aria-controls") : "");
		var variationId = variation && parseInt(variation.variation_id, 10) > 0 ? String(parseInt(variation.variation_id, 10)) : "0";
		var isUnavailable = !!variation && variation.is_in_stock === false;
		trigger.hidden = variation
			? !isUnavailable
			: trigger.getAttribute("data-jluxe-stock-alert-default-visible") !== "true";
		if (button) { button.setAttribute("data-variation-id", isUnavailable ? variationId : "0"); }
		if (dialog) {
			var variationField = dialog.querySelector("[data-jluxe-stock-alert-variation]");
			if (variationField) { variationField.value = isUnavailable ? variationId : "0"; }
		}
	}

	if (window.jQuery) {
		window.jQuery(document).on("found_variation.jluxeStockAlert", "form.variations_form", function (event, variation) {
			updateVariationAlert(event.currentTarget, variation);
		});
		window.jQuery(document).on("reset_data.jluxeStockAlert hide_variation.jluxeStockAlert", "form.variations_form", function (event) {
			updateVariationAlert(event.currentTarget, null);
		});
	}

	function restoreDialogOpener(dialog) {
		var opener = dialog && dialog._jluxeStockAlertOpener;
		if (opener && opener.isConnected && typeof opener.focus === "function") {
			try { opener.focus({ preventScroll: true }); } catch (error) { opener.focus(); }
		}
		if (dialog) { dialog._jluxeStockAlertOpener = null; }
	}

	function closeDialog(dialog, returnFocus) {
		if (!dialog) { return; }
		if (typeof dialog.close === "function" && dialog.open) {
			try { dialog.close(); } catch (error) { dialog.removeAttribute("open"); }
		} else {
			dialog.removeAttribute("open");
		}
		dialog.classList.remove("jluxe-stock-alert-dialog--fallback");
		if (returnFocus) { dialog._jluxeStockAlertOpener = returnFocus; }
		restoreDialogOpener(dialog);
	}

	document.querySelectorAll("[data-jluxe-stock-alert-dialog]").forEach(function (dialog) {
		dialog.addEventListener("close", function () {
			dialog.classList.remove("jluxe-stock-alert-dialog--fallback");
			restoreDialogOpener(dialog);
		});
	});

	document.addEventListener("keydown", function (event) {
		var dialog = document.querySelector("[data-jluxe-stock-alert-dialog][open]");
		if (!dialog || !dialog._jluxeStockAlertFallback) { return; }
		if (event.key === "Escape") {
			event.preventDefault();
			closeDialog(dialog, dialog._jluxeStockAlertOpener);
			return;
		}
		if (event.key !== "Tab") { return; }
		var focusable = dialog.querySelectorAll('a[href], button:not([disabled]):not([hidden]), input:not([disabled]):not([type="hidden"]), [tabindex]:not([tabindex="-1"])');
		if (!focusable.length) { event.preventDefault(); dialog.focus(); return; }
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	});

	document.addEventListener("click", function (event) {
		var openButton = event.target.closest && event.target.closest("[data-jluxe-stock-alert-open]");
		if (openButton) {
			var dialogId = openButton.getAttribute("aria-controls");
			var dialog = dialogId ? document.getElementById(dialogId) : null;
			if (!dialog) { return; }
			var field = dialog.querySelector("[data-jluxe-stock-alert-variation]");
			if (field) { field.value = String(openButton.getAttribute("data-variation-id") || "0"); }
			if (!dialog.open) {
				dialog._jluxeStockAlertOpener = openButton;
				dialog._jluxeStockAlertFallback = typeof dialog.showModal !== "function";
				if (!dialog._jluxeStockAlertFallback) {
					try { dialog.showModal(); } catch (error) { dialog._jluxeStockAlertFallback = true; }
				}
				if (dialog._jluxeStockAlertFallback) {
					dialog.setAttribute("open", "");
					dialog.classList.add("jluxe-stock-alert-dialog--fallback");
				}
			}
			var input = dialog.querySelector('input[name="phone"]');
			if (input) { window.setTimeout(function () { input.focus(); }, 0); }
			return;
		}
		var closeButton = event.target.closest && event.target.closest("[data-jluxe-stock-alert-close]");
		if (closeButton) {
			var containingDialog = closeButton.closest("[data-jluxe-stock-alert-dialog]");
			closeDialog(containingDialog, containingDialog && containingDialog._jluxeStockAlertOpener);
		}
	});

	document.addEventListener("click", function (event) {
		var dialog = event.target.closest && event.target.closest("[data-jluxe-stock-alert-dialog]");
		if (dialog && event.target === dialog) {
			closeDialog(dialog, dialog._jluxeStockAlertOpener);
		}
	});

	document.addEventListener("submit", function (event) {
		var form = event.target.closest && event.target.closest("[data-jluxe-stock-alert-form]");
		if (!form || !ajaxUrl || typeof window.fetch !== "function") { return; }
		event.preventDefault();
		var submit = form.querySelector('[type="submit"]');
		var status = form.querySelector("[data-jluxe-stock-alert-status]");
		var dialog = form.closest("[data-jluxe-stock-alert-dialog]");
		var opener = dialog && dialog._jluxeStockAlertOpener;
		if (submit) { submit.disabled = true; }
		if (status) { status.textContent = "در حال ثبت درخواست…"; status.setAttribute("role", "status"); }
		window.fetch(ajaxUrl, { method: "POST", body: new FormData(form), credentials: "same-origin", cache: "no-store" })
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (!payload || !payload.success) {
					var message = payload && payload.data && payload.data.message ? payload.data.message : "ثبت درخواست انجام نشد؛ دوباره تلاش کنید.";
					if (status) { status.textContent = String(message); status.setAttribute("role", "alert"); }
					return;
				}
				var successMessage = payload.data && payload.data.message ? payload.data.message : "درخواست شما ثبت شد؛ وقتی محصول موجود شود از طریق پیامک خبرتان می‌کنیم.";
				if (status) { status.textContent = String(successMessage); status.setAttribute("role", "status"); }
				if (submit) { submit.hidden = true; }
				var input = form.querySelector('input[name="phone"]');
				if (input) { input.value = ""; }
			}).catch(function () {
				if (status) { status.textContent = "ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید."; status.setAttribute("role", "alert"); }
			}).finally(function () {
				if (submit && !submit.hidden) { submit.disabled = false; }
				if (status) { status.focus && status.focus(); }
				if (dialog) { dialog._jluxeStockAlertOpener = opener; }
			});
	});
})();
