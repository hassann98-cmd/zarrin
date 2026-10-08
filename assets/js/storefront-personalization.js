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

	function normalizeRecentPrice(value) {
		var text = String(value == null ? "" : value);
		for (var pass = 0; pass < 3; pass++) {
			var decoded = text
				.replace(/&amp;/gi, "&")
				.replace(/&ndash;|&#0*8211;|&#x0*2013;/gi, "–")
				.replace(/&mdash;|&#0*8212;|&#x0*2014;/gi, "—")
				.replace(/&nbsp;|&#0*160;|&#x0*a0;/gi, " ");
			if (decoded === text) { break; }
			text = decoded;
		}
		return text.replace(/[\u00a0\u202f]/g, " ").replace(/\s*([–—])\s*/g, " $1 ").replace(/\s+/g, " ").trim();
	}

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
	var recent = readRecent();
	if (/^\d+$/.test(currentId) && Number(currentId) > 0) {
		recent = [currentId].concat(recent.filter(function (id) { return id !== currentId; })).slice(0, MAX_RECENT);
		try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(recent)); } catch (error) {}
	}

	if (!settings.recentProductsUrl || typeof window.fetch !== "function") { return; }

	function idsForPanel(panel) {
		var ids = readRecent();
		if (panel.getAttribute("data-jluxe-recent-context") === "product" && currentId) {
			return ids.filter(function (id) { return id !== currentId; });
		}
		return ids;
	}

	function getCards(list) {
		return Array.prototype.slice.call(list.querySelectorAll(".jluxe-recent-card"));
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
	}

	function scrollCardIntoView(card) {
		if (!card || typeof card.scrollIntoView !== "function") { return; }
		card.scrollIntoView({ behavior: prefersReducedMotion() ? "auto" : "smooth", block: "nearest", inline: "nearest" });
	}

	function listActiveIndex(state) {
		var active = document.activeElement;
		var activeCard = active && active.closest ? active.closest(".jluxe-recent-card") : null;
		if (activeCard) {
			var focusedCards = getCards(state.list);
			var focusedIndex = focusedCards.indexOf(activeCard);
			if (focusedIndex !== -1) { return focusedIndex; }
		}
		return state.focusedIndex;
	}

	function updatePanelControls(state) {
		var cards = getCards(state.list);
		var nav = state.panel.querySelector("[data-jluxe-recent-nav]");
		var previous = state.panel.querySelector("[data-jluxe-recent-prev]");
		var next = state.panel.querySelector("[data-jluxe-recent-next]");
		var activeIndex = listActiveIndex(state);
		if (nav) {
			nav.hidden = cards.length < 2 || state.list.scrollWidth <= state.list.clientWidth + 1;
		}
		if (previous) { previous.disabled = activeIndex <= 0; }
		if (next) { next.disabled = activeIndex >= cards.length - 1; }
	}

	function moveToCard(state, index, moveFocus) {
		var cards = getCards(state.list);
		if (!cards.length) { return; }
		var targetIndex = Math.max(0, Math.min(cards.length - 1, index));
		state.focusedIndex = targetIndex;
		var card = cards[targetIndex];
		var link = card.querySelector("a");
		if (moveFocus && link) {
			try { link.focus({ preventScroll: true }); }
			catch (error) { link.focus(); }
		}
		scrollCardIntoView(card);
		updatePanelControls(state);
	}

	function setupPanelControls(state) {
		var previous = state.panel.querySelector("[data-jluxe-recent-prev]");
		var next = state.panel.querySelector("[data-jluxe-recent-next]");
		state.list.addEventListener("focusin", function (event) {
			var card = event.target && event.target.closest ? event.target.closest(".jluxe-recent-card") : null;
			if (!card) { return; }
			var cards = getCards(state.list);
			var index = cards.indexOf(card);
			if (index !== -1) { state.focusedIndex = index; }
			updatePanelControls(state);
		});
		state.list.addEventListener("keydown", function (event) {
			if (["ArrowLeft", "ArrowRight", "Home", "End"].indexOf(event.key) === -1) { return; }
			var cards = getCards(state.list);
			if (!cards.length) { return; }
			var current = listActiveIndex(state);
			var target = current;
			if (event.key === "Home") { target = 0; }
			else if (event.key === "End") { target = cards.length - 1; }
			else if (event.key === "ArrowLeft") { target = current + 1; }
			else if (event.key === "ArrowRight") { target = current - 1; }
			event.preventDefault();
			moveToCard(state, target, true);
		});
		state.list.addEventListener("scroll", function () {
			var active = document.activeElement;
			if (!active || !active.closest || !active.closest(".jluxe-recent-card")) {
				var cards = getCards(state.list);
				var listRect = state.list.getBoundingClientRect();
				var nearest = -1;
				var nearestDistance = Infinity;
				cards.forEach(function (card, index) {
					var rect = card.getBoundingClientRect();
					if (rect.right <= listRect.left || rect.left >= listRect.right) { return; }
					var distance = Math.abs(rect.right - listRect.right);
					if (distance < nearestDistance) { nearest = index; nearestDistance = distance; }
				});
				if (nearest !== -1) { state.focusedIndex = nearest; }
			}
			updatePanelControls(state);
		}, { passive: true });
		if (previous) {
			previous.addEventListener("click", function () {
				moveToCard(state, listActiveIndex(state) - 1, false);
			});
		}
		if (next) {
			next.addEventListener("click", function () {
				moveToCard(state, listActiveIndex(state) + 1, false);
			});
		}
		window.addEventListener("resize", function () { updatePanelControls(state); });
	}

	function safeImageUrl(value) {
		if (!value) { return ""; }
		try {
			var url = new URL(String(value), window.location.href);
			return url.protocol === "http:" || url.protocol === "https:" ? url.href : "";
		} catch (error) {
			return "";
		}
	}

	function makeCard(item) {
		if (!item || !item.url || !item.name) { return null; }
		var targetUrl;
		try { targetUrl = new URL(item.url, window.location.href); } catch (error) { return null; }
		if (targetUrl.protocol !== "http:" && targetUrl.protocol !== "https:") { return null; }

		var card = document.createElement("article");
		card.className = "jluxe-recent-card";
		card.setAttribute("role", "listitem");
		if (item.id != null) { card.setAttribute("data-jluxe-recent-product-id", String(item.id)); }
		var link = document.createElement("a");
		link.className = "jluxe-recent-card__link";
		link.href = targetUrl.href;

		var media = document.createElement("span");
		media.className = "jluxe-recent-card__media";
		var image = document.createElement("img");
		image.className = "jluxe-recent-card__image";
		image.alt = String(item.imageAlt || item.name);
		image.loading = "lazy";
		image.decoding = "async";
		image.addEventListener("error", function () {
			image.hidden = true;
			media.setAttribute("data-empty-image", "true");
		});
		var imageUrl = safeImageUrl(item.image);
		if (imageUrl) {
			image.src = imageUrl;
		} else {
			image.hidden = true;
			media.setAttribute("data-empty-image", "true");
		}
		media.appendChild(image);
		if (typeof item.inStock === "boolean") {
			var stock = document.createElement("span");
			stock.className = "jluxe-recent-card__stock " + (item.inStock ? "is-in-stock" : "is-out-of-stock");
			stock.textContent = item.inStock ? "موجود" : "فعلاً ناموجود";
			stock.setAttribute("aria-label", item.inStock ? "وضعیت موجودی: موجود" : "وضعیت موجودی: فعلاً ناموجود");
			media.appendChild(stock);
		}

		var body = document.createElement("span");
		body.className = "jluxe-recent-card__body";
		var name = document.createElement("span");
		name.className = "jluxe-recent-card__name";
		name.textContent = String(item.name);
		body.appendChild(name);

		var footer = document.createElement("span");
		footer.className = "jluxe-recent-card__footer";
		var price = document.createElement("span");
		price.className = "jluxe-recent-card__price";
		price.dir = "rtl";
		price.lang = "fa";
		if (item.priceIsRange === true) {
			var rangeLabel = document.createElement("span");
			rangeLabel.className = "jluxe-recent-card__price-range-label";
			rangeLabel.textContent = "بازهٔ قیمت";
			price.appendChild(rangeLabel);
		}
		var currentPrice = document.createElement("span");
		currentPrice.className = "jluxe-recent-card__price-current";
		currentPrice.dir = "auto";
		currentPrice.textContent = normalizeRecentPrice(item.currentPrice != null && String(item.currentPrice).trim() !== "" ? item.currentPrice : item.price);
		if (currentPrice.textContent) { price.appendChild(currentPrice); }
		var regularPriceText = normalizeRecentPrice(item.regularPrice);
		if (regularPriceText) {
			price.setAttribute("data-on-sale", "true");
			var regularPrice = document.createElement("del");
			regularPrice.className = "jluxe-recent-card__price-before";
			regularPrice.dir = "auto";
			regularPrice.textContent = regularPriceText;
			price.appendChild(regularPrice);
		}
		footer.appendChild(price);
		var cta = document.createElement("span");
		cta.className = "jluxe-recent-card__cta";
		cta.textContent = "مشاهده";
		var arrow = document.createElementNS("http://www.w3.org/2000/svg", "svg");
		arrow.setAttribute("viewBox", "0 0 24 24");
		arrow.setAttribute("fill", "none");
		arrow.setAttribute("stroke", "currentColor");
		arrow.setAttribute("stroke-width", "2");
		arrow.setAttribute("stroke-linecap", "round");
		arrow.setAttribute("stroke-linejoin", "round");
		arrow.setAttribute("aria-hidden", "true");
		var arrowPath = document.createElementNS("http://www.w3.org/2000/svg", "path");
		arrowPath.setAttribute("d", "M19 12H5m7 7-7-7 7-7");
		arrow.appendChild(arrowPath);
		cta.appendChild(arrow);
		footer.appendChild(cta);
		body.appendChild(footer);

		link.appendChild(media);
		link.appendChild(body);
		card.appendChild(link);
		return card;
	}

	function renderCount(panel, count) {
		var countNode = panel.querySelector("[data-jluxe-recent-count]");
		panel.setAttribute("data-jluxe-recent-item-count", String(count));
		if (!countNode) { return; }
		if (!count) {
			countNode.textContent = "";
			countNode.hidden = true;
			return;
		}
		var digits = String(count);
		try { digits = new Intl.NumberFormat("fa-IR").format(count); } catch (error) {}
		countNode.textContent = digits + " محصول";
		countNode.hidden = false;
	}

	function clearPanel(state) {
		state.list.textContent = "";
		state.panel.hidden = true;
		renderCount(state.panel, 0);
		updatePanelControls(state);
	}

	function renderPanel(state, items) {
		var activeElement = document.activeElement;
		var activeCard = activeElement && activeElement.closest ? activeElement.closest(".jluxe-recent-card") : null;
		if (activeCard && !state.list.contains(activeCard)) { activeCard = null; }
		var activeProductId = activeCard ? activeCard.getAttribute("data-jluxe-recent-product-id") : "";
		var listWasFocused = activeElement === state.list;
		var fragment = document.createDocumentFragment();
		var renderedIds = Object.create(null);
		var renderedCount = 0;
		items.forEach(function (item) {
			if (!item) { return; }
			var itemId = String(item.id == null ? "" : item.id);
			if (itemId && renderedIds[itemId]) { return; }
			var card = makeCard(item);
			if (!card) { return; }
			if (itemId) { renderedIds[itemId] = true; }
			fragment.appendChild(card);
			renderedCount++;
		});
		state.list.textContent = "";
		state.list.appendChild(fragment);
		state.focusedIndex = 0;
		renderCount(state.panel, renderedCount);
		state.panel.hidden = renderedCount === 0;
		if (renderedCount && (activeProductId || listWasFocused)) {
			var refreshedCards = getCards(state.list);
			var restoredIndex = activeProductId
				? refreshedCards.findIndex(function (card) { return card.getAttribute("data-jluxe-recent-product-id") === activeProductId; })
				: -1;
			if (restoredIndex !== -1) {
				state.focusedIndex = restoredIndex;
				var restoredLink = refreshedCards[restoredIndex].querySelector("a");
				if (restoredLink) {
					try { restoredLink.focus({ preventScroll: true }); }
					catch (error) { restoredLink.focus(); }
				}
			} else {
				try { state.list.focus({ preventScroll: true }); }
				catch (error) { state.list.focus(); }
			}
		}
		updatePanelControls(state);
	}

	function loadPanel(state) {
		var list = state.list;
		var ids = idsForPanel(state.panel).slice(0, MAX_RECENT);
		var signature = ids.join(",");
		var requestId = ++state.requestId;
		if (state.controller) {
			try { state.controller.abort(); } catch (error) {}
			state.controller = null;
		}
		if (!ids.length) {
			clearPanel(state);
			return;
		}

		var options = { method: "GET", credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" } };
		var Controller = window.AbortController;
		if (typeof Controller === "function") {
			state.controller = new Controller();
			options.signal = state.controller.signal;
		}
		var separator = settings.recentProductsUrl.indexOf("?") === -1 ? "?" : "&";
		var url = settings.recentProductsUrl + separator + "ids=" + encodeURIComponent(signature);
		window.fetch(url, options)
			.then(function (response) { return response.ok ? response.json() : null; })
			.then(function (payload) {
				if (state.requestId !== requestId || signature !== idsForPanel(state.panel).slice(0, MAX_RECENT).join(",")) { return; }
				var items = payload && Array.isArray(payload.items) ? payload.items : [];
				renderPanel(state, items);
				state.controller = null;
			})
			.catch(function () {
				if (state.requestId === requestId) { state.controller = null; }
			});
	}

	var panelStates = [];
	document.querySelectorAll("[data-jluxe-recent-products]").forEach(function (panel) {
		var list = panel.querySelector("[data-jluxe-recent-list]");
		if (!list) { return; }
		var state = { panel: panel, list: list, requestId: 0, controller: null, focusedIndex: 0 };
		panelStates.push(state);
		setupPanelControls(state);
		loadPanel(state);
	});

	function refreshPanels() {
		panelStates.forEach(loadPanel);
	}

	var refreshTimer = 0;
	function scheduleRefresh() {
		if (refreshTimer) { return; }
		refreshTimer = window.setTimeout(function () {
			refreshTimer = 0;
			if (document.hidden) { return; }
			refreshPanels();
		}, 0);
	}

	window.addEventListener("pageshow", function (event) {
		if (event && event.persisted) { scheduleRefresh(); }
	});
	document.addEventListener("visibilitychange", function () {
		if (!document.hidden) { scheduleRefresh(); }
	});
	window.addEventListener("storage", function (event) {
		if (!event || event.key === STORAGE_KEY || event.key === null) { scheduleRefresh(); }
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
