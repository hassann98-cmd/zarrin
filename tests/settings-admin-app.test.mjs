import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";

const appScript = await readFile(new URL("../assets/js/theme-settings-app.js", import.meta.url), "utf8");

function createAdminDom({ mobile = false } = {}) {
	const dom = new JSDOM(`<!doctype html>
		<div class="jluxe-settings" data-jluxe-settings-app data-theme="light">
			<header class="jluxe-settings-topbar">
				<button type="button" data-jluxe-sidebar-toggle aria-controls="jluxe-settings-sidebar" aria-expanded="true"></button>
				<div class="jluxe-settings-search">
					<input type="search" data-jluxe-settings-search aria-controls="jluxe-settings-search-results" aria-expanded="false">
					<div data-jluxe-search-results role="list" hidden></div>
				</div>
				<button type="button" data-jluxe-theme-toggle aria-pressed="false"><span class="dashicons dashicons-moon"></span><span class="jluxe-settings-theme-toggle-label">حالت شب</span></button>
			</header>
			<div class="jluxe-settings-app-layout">
				<aside class="jluxe-settings-sidebar" id="jluxe-settings-sidebar">
					<nav>
						<section data-jluxe-settings-nav-group>
							<ul>
								<li><a href="/wp-admin/admin.php?page=jluxe-colors-typography" data-jluxe-settings-nav-item data-jluxe-search-label="رنگ و تایپوگرافی" data-jluxe-search-group="ظاهر و صفحه‌ساز" data-jluxe-search-context="تنظیم رنگ‌های پوسته"><span class="dashicons dashicons-admin-customizer"></span><span>رنگ و تایپوگرافی</span></a></li>
								<li><a href="/wp-admin/admin.php?page=jluxe-urls" data-jluxe-settings-nav-item data-jluxe-search-label="کاربران و ورود" data-jluxe-search-group="کاربران و ورود" data-jluxe-search-context="نشانی‌های حساب کاربری"><span class="dashicons dashicons-admin-users"></span><span>کاربران و ورود</span></a></li>
							</ul>
						</section>
						<section data-jluxe-settings-nav-group>
							<ul><li><a href="/wp-admin/admin.php?page=jluxe-homepage" data-jluxe-settings-nav-item data-jluxe-search-label="صفحه اصلی" data-jluxe-search-group="ظاهر و صفحه‌ساز" data-jluxe-search-context="چیدمان صفحه"><span class="dashicons dashicons-admin-home"></span><span>صفحه اصلی</span></a></li></ul>
						</section>
					</nav>
				</aside>
				<button type="button" data-jluxe-sidebar-close aria-label="بستن منو" hidden></button>
				<main>
					<section data-jluxe-settings-card-group>
						<a href="/wp-admin/admin.php?page=jluxe-colors-typography" data-jluxe-settings-card data-jluxe-search-label="رنگ و تایپوگرافی" data-jluxe-search-group="ظاهر و صفحه‌ساز" data-jluxe-search-context="رنگ‌های پوسته">تنظیم رنگ‌ها</a>
						<a href="/wp-admin/admin.php?page=jluxe-ai-assistant" data-jluxe-settings-card data-jluxe-search-label="دستیار هوش مصنوعی" data-jluxe-search-group="هوش مصنوعی" data-jluxe-search-context="دستیار فروشگاه">دستیار</a>
					</section>
				</main>
			</div>
		</div>`, {
		url: "https://shop.test/wp-admin/admin.php?page=jluxe-theme-settings",
		runScripts: "outside-only",
	});

	dom.window.matchMedia = (query) => ({
		media: query,
		matches: mobile,
		addEventListener() {},
	});
	dom.window.eval(appScript);
	return dom;
}

test("admin search filters existing settings and dashboard cards, and normalizes Persian/Arabic letters", () => {
	const dom = createAdminDom();
	const { document } = dom.window;
	const root = document.querySelector("[data-jluxe-settings-app]");
	const input = document.querySelector("[data-jluxe-settings-search]");

	input.value = "رنگ";
	input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(document.querySelector("[data-jluxe-search-results]").hidden, false);
	assert.equal(document.querySelectorAll(".jluxe-settings-search-result").length, 1);
	assert.match(document.querySelector(".jluxe-settings-search-result").getAttribute("href"), /jluxe-colors-typography/);
	assert.equal(document.querySelector("[data-jluxe-settings-card][data-jluxe-search-label='دستیار هوش مصنوعی']").hidden, true);
	assert.equal(document.querySelector("[data-jluxe-settings-card-group]").hidden, false);

	input.value = "كاربران";
	input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(document.querySelectorAll(".jluxe-settings-search-result").length, 1);
	assert.match(document.querySelector(".jluxe-settings-search-result").textContent, /کاربران و ورود/);

	input.value = "عبارت ناموجود";
	input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.match(document.querySelector(".jluxe-settings-search-empty").textContent, /پیدا نشد/);
	assert.equal(document.querySelectorAll("[data-jluxe-settings-nav-group]:not([hidden])").length, 0);

	input.value = "";
	input.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(document.querySelector("[data-jluxe-search-results]").hidden, true);
	assert.equal(document.querySelectorAll("[data-jluxe-settings-nav-group]:not([hidden])").length, 2);
	assert.equal(document.querySelectorAll("[data-jluxe-settings-card]:not([hidden])").length, 2);
	assert.equal(root.dataset.theme, "light");
	dom.window.close();
});

test("theme mode and desktop sidebar state persist without affecting page navigation", () => {
	const dom = createAdminDom();
	const { document, localStorage } = dom.window;
	const root = document.querySelector("[data-jluxe-settings-app]");
	const themeToggle = document.querySelector("[data-jluxe-theme-toggle]");
	const sidebarToggle = document.querySelector("[data-jluxe-sidebar-toggle]");

	themeToggle.click();
	assert.equal(root.dataset.theme, "dark");
	assert.equal(themeToggle.getAttribute("aria-pressed"), "true");
	assert.equal(localStorage.getItem("jluxe-settings-theme"), "dark");
	themeToggle.click();
	assert.equal(root.dataset.theme, "light");

	sidebarToggle.click();
	assert.equal(root.dataset.sidebarCollapsed, "true");
	assert.equal(sidebarToggle.getAttribute("aria-expanded"), "false");
	assert.equal(localStorage.getItem("jluxe-settings-sidebar-collapsed"), "1");

	const shortcut = new dom.window.KeyboardEvent("keydown", { key: "k", ctrlKey: true, bubbles: true, cancelable: true });
	document.dispatchEvent(shortcut);
	assert.equal(document.activeElement, document.querySelector("[data-jluxe-settings-search]"));
	dom.window.close();
});

test("mobile sidebar opens and closes accessibly via its backdrop or Escape", () => {
	const dom = createAdminDom({ mobile: true });
	const { document } = dom.window;
	const root = document.querySelector("[data-jluxe-settings-app]");
	const sidebar = document.querySelector(".jluxe-settings-sidebar");
	const firstLink = sidebar.querySelector("a");
	const toggle = document.querySelector("[data-jluxe-sidebar-toggle]");
	const backdrop = document.querySelector("[data-jluxe-sidebar-close]");

	assert.equal(sidebar.getAttribute("aria-hidden"), "true");
	assert.equal(firstLink.getAttribute("tabindex"), "-1");
	toggle.click();
	assert.equal(root.classList.contains("is-mobile-sidebar-open"), true);
	assert.equal(sidebar.hasAttribute("aria-hidden"), false);
	assert.equal(firstLink.hasAttribute("tabindex"), false);
	assert.equal(backdrop.hidden, false);
	assert.equal(toggle.getAttribute("aria-expanded"), "true");

	backdrop.click();
	assert.equal(root.classList.contains("is-mobile-sidebar-open"), false);
	assert.equal(sidebar.getAttribute("aria-hidden"), "true");
	assert.equal(firstLink.getAttribute("tabindex"), "-1");
	assert.equal(document.activeElement, toggle);

	toggle.click();
	document.dispatchEvent(new dom.window.KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
	assert.equal(root.classList.contains("is-mobile-sidebar-open"), false);
	dom.window.close();
});
