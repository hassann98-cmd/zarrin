/* Reconcile cacheable guest-page settings with WordPress's private session endpoint. */
(function () {
	if (typeof Promise !== "function") { return; }
	var settings = window.jluxeMobileAuthSettings || {};
	var statusResolved = false;
	var currentStatus = null;
	var statusPromise = null;

	function isBoolean(value) {
		return value === true || value === false;
	}

	function getStatus(payload) {
		var auth = payload && payload.success && payload.data && payload.data.auth;
		return auth && isBoolean(auth.isLoggedIn) ? auth.isLoggedIn : null;
	}

	function accountHref(link, authenticated) {
		var key = authenticated
			? "data-jluxe-account-dashboard-href"
			: "data-jluxe-account-login-href";
		return link.getAttribute(key) || link.getAttribute("href") || "";
	}

	function updateLink(link, authenticated) {
		var href = accountHref(link, authenticated);
		var label = link.querySelector("[data-jluxe-account-label]");
		if (href) { link.setAttribute("href", href); }
		if (label) {
			label.textContent = authenticated
				? link.getAttribute("data-jluxe-account-logged-label") || "حساب کاربری"
				: "ورود";
		}
	}

	function publishStatus(authenticated) {
		statusResolved = true;
		currentStatus = authenticated;
		if (!isBoolean(authenticated)) { return null; }

		window.jluxeCurrentAuthStatus = authenticated;
		var themeSettings = window.JLuxeThemeSettings;
		if (themeSettings && typeof themeSettings === "object") {
			themeSettings.auth = themeSettings.auth && typeof themeSettings.auth === "object"
				? themeSettings.auth
				: {};
			themeSettings.auth.isLoggedIn = authenticated;
		}
		Array.prototype.forEach.call(document.querySelectorAll("[data-jluxe-account-link]"), function (link) {
			updateLink(link, authenticated);
		});
		return authenticated;
	}

	function requestSession() {
		var endpoint = settings.sessionUrl;
		if (!endpoint) { return Promise.resolve(null); }
		if (typeof window.fetch === "function") {
			return window.fetch(endpoint, {
				method: "POST",
				credentials: "same-origin",
				cache: "no-store",
				headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
				body: "action=jluxe_session",
			}).then(function (response) {
				if (!response.ok) { return null; }
				return response.json();
			}).then(getStatus).catch(function () { return null; });
		}
		if (typeof window.XMLHttpRequest !== "function") { return Promise.resolve(null); }
		return new Promise(function (resolve) {
			var request = new window.XMLHttpRequest();
			request.open("POST", endpoint, true);
			request.withCredentials = true;
			request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			request.setRequestHeader("Cache-Control", "no-store");
			request.onreadystatechange = function () {
				if (request.readyState !== 4) { return; }
				if (request.status < 200 || request.status >= 300) { resolve(null); return; }
				try { resolve(getStatus(JSON.parse(request.responseText))); }
				catch (error) { resolve(null); }
			};
			request.onerror = function () { resolve(null); };
			request.send("action=jluxe_session");
		});
	}

	function accountLinkFrom(target) {
		while (target && target !== document) {
			if (target.getAttribute && target.hasAttribute("data-jluxe-account-link")) return target;
			target = target.parentNode;
		}
		return null;
	}

	function handleAccountClick(event) {
		var link = accountLinkFrom(event.target);
		if (!link || event.defaultPrevented) { return; }
		if ((typeof event.button === "number" && event.button !== 0) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
		if (statusResolved) {
			if (isBoolean(currentStatus)) { updateLink(link, currentStatus); }
			return;
		}
		if (!statusPromise) { return; }
		event.preventDefault();
		statusPromise.then(function (authenticated) {
			if (isBoolean(authenticated)) { updateLink(link, authenticated); }
			var destination = isBoolean(authenticated)
				? accountHref(link, authenticated)
				: link.getAttribute("href");
			if (destination) { window.location.assign(destination); }
		});
	}

	document.addEventListener("click", handleAccountClick, true);
	statusPromise = requestSession().then(publishStatus);
	window.jluxeMobileAuthStatusPromise = statusPromise;
})();
