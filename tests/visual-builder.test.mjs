import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import jquery from "jquery";

const source = await readFile(new URL("../assets/js/visual-builder.js", import.meta.url), "utf8");

function createDom() {
	const dom = new JSDOM(`<!doctype html><html><body>
		<div id="jluxe-visual-builder" data-preview-url="https://shop.test/">
			<form id="jluxe-visual-builder-form">
				<span data-jvb-save-state>پیش‌نویس تازه</span>
				<p data-jvb-section-count>پیش‌نویس مستقل · 1 بخش</p>
				<button class="jvb-save-button" name="action" value="jluxe_visual_builder_save"></button>
				<button data-jvb-publish name="action" value="jluxe_visual_builder_publish"></button>
				<button type="button" data-jvb-tab="components" aria-selected="true"></button>
				<button type="button" data-jvb-tab="structure" aria-selected="false"></button>
				<div data-jvb-panel="components"></div>
				<div data-jvb-panel="structure" hidden></div>
				<input data-jvb-search>
				<div data-jvb-group>
					<button type="button" data-jvb-add-section data-type="banner" data-search-label="بنر تک">بنر تک</button>
				</div>
				<p data-jvb-empty-search hidden></p>
				<ol data-jvb-outline><li><button type="button" data-jvb-outline-target="0"></button></li></ol>
				<span data-jvb-outline-count>1</span>
				<p data-jvb-outline-empty hidden></p>
				<div data-jvb-canvas-wrap data-viewport="desktop">
					<div data-jvb-canvas>
						<div id="jluxe-hb-sections">
							<div class="jluxe-hb-section" data-type="text">
								<details><summary><span class="jluxe-hb-type-label">متن سفارشی</span><label class="jluxe-hb-enabled"><input type="checkbox" checked></label></summary></details>
							</div>
						</div>
						<p data-jvb-drop-hint hidden></p>
					</div>
					<div data-jvb-live-preview hidden><iframe data-jvb-preview-frame data-src="https://shop.test/"></iframe></div>
				</div>
				<button type="button" data-jvb-viewport="desktop" aria-pressed="true"></button>
				<button type="button" data-jvb-viewport="tablet" aria-pressed="false"></button>
				<button type="button" data-jvb-preview-toggle aria-pressed="false"><span data-jvb-preview-label>پیش‌نمایش سایت فعلی</span></button>
				<strong data-jvb-stage-title>چیدمان صفحهٔ اصلی</strong>
				<span data-jvb-stage-subtitle>پیش‌نویس مستقل از سایت فعال</span>
				<select id="jluxe-hb-add-type"><option value="banner">banner</option></select>
				<button type="button" id="jluxe-hb-add"></button>
			</form>
		</div>
	</body></html>`, {
		url: "https://shop.test/wp-admin/admin.php?page=jluxe-visual-builder",
		runScripts: "outside-only",
		pretendToBeVisual: true,
	});
	const { window } = dom;
	window.jQuery = jquery(window);
	window.confirm = () => false;
	window.HTMLElement.prototype.scrollIntoView = function () {};
	const sections = window.document.getElementById("jluxe-hb-sections");
	window.document.getElementById("jluxe-hb-add").addEventListener("click", () => {
		const index = sections.querySelectorAll(":scope > .jluxe-hb-section").length;
		const section = window.document.createElement("div");
		section.className = "jluxe-hb-section";
		section.setAttribute("data-type", "banner");
		section.innerHTML = `<details open><summary><span class="jluxe-hb-type-label">بنر تک</span><label class="jluxe-hb-enabled"><input type="checkbox" checked></label></summary><input name="homepage[sections][${index}][title]"></details>`;
		sections.appendChild(section);
	});
	window.eval(source);
	return dom;
}

test("visual builder tabs, outline, search, and native section library stay in sync", async () => {
	const dom = createDom();
	const { document } = dom.window;
	const sections = document.getElementById("jluxe-hb-sections");
	const tabs = document.querySelectorAll("[data-jvb-tab]");
	const structureTab = Array.from(tabs).find((tab) => tab.getAttribute("data-jvb-tab") === "structure");
	const componentTab = Array.from(tabs).find((tab) => tab.getAttribute("data-jvb-tab") === "components");

	structureTab.click();
	assert.equal(structureTab.getAttribute("aria-selected"), "true");
	assert.equal(document.querySelector('[data-jvb-panel="components"]').hidden, true);
	componentTab.click();
	assert.equal(document.querySelector('[data-jvb-panel="components"]').hidden, false);

	const search = document.querySelector("[data-jvb-search]");
	const component = document.querySelector("[data-jvb-add-section]");
	search.value = "ناموجود";
	search.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(component.hidden, true);
	assert.equal(document.querySelector("[data-jvb-empty-search]").hidden, false);
	search.value = "بنر";
	search.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(component.hidden, false);

	component.click();
	await new Promise((resolve) => dom.window.setTimeout(resolve, 10));
	assert.equal(sections.querySelectorAll(":scope > .jluxe-hb-section").length, 2);
	assert.equal(sections.querySelectorAll("[name^='homepage[sections]']").length, 1);
	assert.equal(document.querySelector("[data-jvb-outline-count]").textContent, "2");
	assert.match(document.querySelector("[data-jvb-save-state]").textContent, /ذخیره‌نشده/);
	dom.window.close();
});

test("saved-draft preview mode is explicitly labeled and remains separate from the published version", () => {
	const dom = createDom();
	const { document } = dom.window;
	const root = document.getElementById("jluxe-visual-builder");
	root.setAttribute("data-preview-kind", "draft");
	const previewToggle = document.querySelector("[data-jvb-preview-toggle]");

	previewToggle.click();
	assert.equal(document.querySelector("[data-jvb-stage-title]").textContent, "پیش‌نویس ذخیره‌شده");
	assert.match(document.querySelector("[data-jvb-stage-subtitle]").textContent, /مدیرِ واردشده/);
	previewToggle.click();
	assert.equal(document.querySelector("[data-jvb-preview-label]").textContent, "پیش‌نمایش پیش‌نویس ذخیره‌شده");
	dom.window.close();
});

test("device preview is read-only and publish requires explicit confirmation", () => {
	const dom = createDom();
	const { document } = dom.window;
	const viewport = document.querySelector('[data-jvb-viewport="tablet"]');
	viewport.click();
	assert.equal(document.querySelector("[data-jvb-canvas-wrap]").getAttribute("data-viewport"), "tablet");
	assert.equal(viewport.getAttribute("aria-pressed"), "true");

	const previewToggle = document.querySelector("[data-jvb-preview-toggle]");
	previewToggle.click();
	assert.equal(document.querySelector("[data-jvb-live-preview]").hidden, false);
	assert.equal(document.querySelector("[data-jvb-preview-frame]").getAttribute("src"), "https://shop.test/");
	assert.equal(document.querySelector("[data-jvb-canvas]").hidden, true);
	previewToggle.click();
	assert.equal(document.querySelector("[data-jvb-canvas]").hidden, false);

	let prevented = false;
	const submit = new dom.window.SubmitEvent("submit", {
		bubbles: true,
		cancelable: true,
		submitter: document.querySelector("[data-jvb-publish]"),
	});
	document.getElementById("jluxe-visual-builder-form").addEventListener("submit", (event) => {
		if (event.defaultPrevented) prevented = true;
	});
	document.getElementById("jluxe-visual-builder-form").dispatchEvent(submit);
	assert.equal(submit.defaultPrevented, true);
	assert.equal(prevented, true);
	dom.window.close();
});
