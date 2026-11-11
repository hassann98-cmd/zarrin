/* Zarrin settings-app interactions. Kept separate from form/media scripts so
 * search, sidebar and theme controls remain isolated from settings handlers. */
(() => {
	"use strict";

	const roots = document.querySelectorAll(".jluxe-settings[data-jluxe-settings-app]");
	if (!roots.length) return;

	const normalize = (value) => {
		const raw = String(value || "");
		const normalized = typeof raw.normalize === "function" ? raw.normalize("NFKC") : raw;
		return normalized
			.toLowerCase()
			.replace(/[يى]/g, "ی")
			.replace(/ك/g, "ک")
			.replace(/[\u064B-\u065F\u0670\u06D6-\u06ED]/g, "")
			.replace(/\s+/g, " ")
			.trim();
	};

	roots.forEach((root) => {
		const searchInput = root.querySelector("[data-jluxe-settings-search]");
		const resultsPanel = root.querySelector("[data-jluxe-search-results]");
		const sidebar = root.querySelector(".jluxe-settings-sidebar");
		const sidebarFocusables = sidebar ? Array.from(sidebar.querySelectorAll("a[href], button, input, select, textarea, [tabindex]")) : [];
		const sidebarToggle = root.querySelector("[data-jluxe-sidebar-toggle]");
		const sidebarBackdrop = root.querySelector("[data-jluxe-sidebar-close]");
		const themeToggle = root.querySelector("[data-jluxe-theme-toggle]");
		const navItems = Array.from(root.querySelectorAll("[data-jluxe-settings-nav-item]"));
		const navGroups = Array.from(root.querySelectorAll("[data-jluxe-settings-nav-group]"));
		const featureCards = Array.from(root.querySelectorAll("[data-jluxe-settings-card]"));
		const featureGroups = Array.from(root.querySelectorAll("[data-jluxe-settings-card-group]"));
		const mobileQuery = window.matchMedia ? window.matchMedia("(max-width: 782px)") : { matches: false };

		const getIndex = (element) => normalize([
			element.dataset.jluxeSearchLabel,
			element.dataset.jluxeSearchGroup,
			element.dataset.jluxeSearchContext,
			element.textContent,
		].filter(Boolean).join(" "));

		function updateSearch() {
			if (!searchInput || !resultsPanel) return;
			const query = normalize(searchInput.value);
			const matches = query ? navItems.filter((item) => getIndex(item).includes(query)) : [];

			navItems.forEach((item) => {
				const visible = !query || getIndex(item).includes(query);
				const listItem = item.closest("li");
				if (listItem) listItem.hidden = !visible;
			});
			navGroups.forEach((group) => {
				group.hidden = Boolean(query) && !group.querySelector("li:not([hidden]) [data-jluxe-settings-nav-item]");
			});
			featureCards.forEach((card) => {
				card.hidden = Boolean(query) && !getIndex(card).includes(query);
			});
			featureGroups.forEach((group) => {
				group.hidden = Boolean(query) && !group.querySelector("[data-jluxe-settings-card]:not([hidden])");
			});

			while (resultsPanel.firstChild) resultsPanel.removeChild(resultsPanel.firstChild);
			if (query) {
				matches.slice(0, 12).forEach((item) => {
					const result = document.createElement("a");
					result.className = "jluxe-settings-search-result";
					result.href = item.href;
					result.setAttribute("role", "listitem");

					const sourceIcon = item.querySelector(".dashicons");
					if (sourceIcon) {
						const icon = sourceIcon.cloneNode(false);
						icon.setAttribute("aria-hidden", "true");
						result.appendChild(icon);
					}

					const label = document.createElement("span");
					label.textContent = item.dataset.jluxeSearchLabel || item.textContent.trim();
					result.appendChild(label);

					if (item.dataset.jluxeSearchGroup) {
						const group = document.createElement("small");
						group.textContent = item.dataset.jluxeSearchGroup;
						result.appendChild(group);
					}
					resultsPanel.appendChild(result);
				});

				if (!matches.length) {
					const empty = document.createElement("p");
					empty.className = "jluxe-settings-search-empty";
					empty.setAttribute("role", "status");
					empty.textContent = "بخشی با این عبارت پیدا نشد.";
					resultsPanel.appendChild(empty);
				}
			}

			resultsPanel.hidden = !query;
			searchInput.setAttribute("aria-expanded", query ? "true" : "false");
		}

		if (searchInput) {
			searchInput.addEventListener("input", updateSearch);
			searchInput.addEventListener("keydown", (event) => {
				if (event.key === "Escape") {
					event.preventDefault();
					if (searchInput.value) {
						searchInput.value = "";
						updateSearch();
					} else {
						resultsPanel.hidden = true;
						searchInput.setAttribute("aria-expanded", "false");
						searchInput.blur();
					}
				} else if (event.key === "ArrowDown") {
					const firstResult = resultsPanel && resultsPanel.querySelector("a");
					if (firstResult) {
						event.preventDefault();
						firstResult.focus();
					}
				} else if (event.key === "Enter" && resultsPanel && !resultsPanel.hidden) {
					const firstResult = resultsPanel.querySelector("a");
					if (firstResult) {
						event.preventDefault();
						window.location.assign(firstResult.href);
					}
				}
			});
		}

		function setTheme(theme) {
			const isDark = theme === "dark";
			root.dataset.theme = isDark ? "dark" : "light";
			if (!themeToggle) return;
			themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
			themeToggle.setAttribute("aria-label", isDark ? "فعال‌کردن حالت روشن" : "فعال‌کردن حالت شب");
			themeToggle.title = isDark ? "حالت روشن" : "حالت شب";
			const icon = themeToggle.querySelector(".dashicons");
			if (icon) {
				icon.classList.toggle("dashicons-moon", !isDark);
				icon.classList.toggle("dashicons-lightbulb", isDark);
			}
			const label = themeToggle.querySelector(".jluxe-settings-theme-toggle-label");
			if (label) label.textContent = isDark ? "حالت روشن" : "حالت شب";
			try { window.localStorage.setItem("jluxe-settings-theme", isDark ? "dark" : "light"); } catch (_error) { /* storage may be disabled */ }
		}

		if (themeToggle) {
			let savedTheme = "light";
			try { savedTheme = window.localStorage.getItem("jluxe-settings-theme") || "light"; } catch (_error) { /* storage may be disabled */ }
			setTheme(savedTheme === "dark" ? "dark" : "light");
			themeToggle.addEventListener("click", () => setTheme(root.dataset.theme === "dark" ? "light" : "dark"));
		}

		function setMobileSidebarOpen(isOpen, returnFocus = false) {
			const isInteractive = !mobileQuery.matches || isOpen;
			root.classList.toggle("is-mobile-sidebar-open", isOpen);
			if (sidebarBackdrop) sidebarBackdrop.hidden = !isOpen;
			if (sidebar) {
				if (isInteractive) sidebar.removeAttribute("aria-hidden");
				else sidebar.setAttribute("aria-hidden", "true");
				if ("inert" in sidebar) sidebar.inert = !isInteractive;
			}
			sidebarFocusables.forEach((element) => {
				if (isInteractive) {
					if (Object.prototype.hasOwnProperty.call(element.dataset, "jluxeSavedTabindex")) {
						const originalTabindex = element.dataset.jluxeSavedTabindex;
						if (originalTabindex) element.setAttribute("tabindex", originalTabindex);
						else element.removeAttribute("tabindex");
						delete element.dataset.jluxeSavedTabindex;
					}
				} else if (!Object.prototype.hasOwnProperty.call(element.dataset, "jluxeSavedTabindex")) {
					element.dataset.jluxeSavedTabindex = element.getAttribute("tabindex") || "";
					element.setAttribute("tabindex", "-1");
				}
			});
			if (sidebarToggle) sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
			if (returnFocus && sidebarToggle) sidebarToggle.focus();
		}

		function setDesktopSidebarCollapsed(isCollapsed) {
			root.dataset.sidebarCollapsed = isCollapsed ? "true" : "false";
			if (sidebar) sidebar.removeAttribute("aria-hidden");
			if (sidebarToggle) sidebarToggle.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
			try { window.localStorage.setItem("jluxe-settings-sidebar-collapsed", isCollapsed ? "1" : "0"); } catch (_error) { /* storage may be disabled */ }
		}

		if (sidebarToggle) {
			if (mobileQuery.matches) {
				setMobileSidebarOpen(false);
			} else {
				let collapsed = false;
				try { collapsed = window.localStorage.getItem("jluxe-settings-sidebar-collapsed") === "1"; } catch (_error) { /* storage may be disabled */ }
				setDesktopSidebarCollapsed(collapsed);
			}
			sidebarToggle.addEventListener("click", () => {
				if (mobileQuery.matches) {
					setMobileSidebarOpen(!root.classList.contains("is-mobile-sidebar-open"));
				} else {
					setDesktopSidebarCollapsed(root.dataset.sidebarCollapsed !== "true");
				}
			});
		}

		if (sidebarBackdrop) sidebarBackdrop.addEventListener("click", () => setMobileSidebarOpen(false, true));
		if (mobileQuery.addEventListener) {
			mobileQuery.addEventListener("change", (event) => {
				if (event.matches) {
					setMobileSidebarOpen(false);
				} else {
					setMobileSidebarOpen(false);
					let collapsed = false;
					try { collapsed = window.localStorage.getItem("jluxe-settings-sidebar-collapsed") === "1"; } catch (_error) { /* storage may be disabled */ }
					setDesktopSidebarCollapsed(collapsed);
				}
			});
		}

		document.addEventListener("keydown", (event) => {
			if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k" && searchInput) {
				event.preventDefault();
				searchInput.focus();
				searchInput.select();
			}
			if (event.key === "Escape" && mobileQuery.matches && root.classList.contains("is-mobile-sidebar-open")) {
				setMobileSidebarOpen(false, true);
			}
		});
	});
})();
