import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import jquery from "jquery";

const source = await readFile(new URL("../assets/js/visual-builder.js", import.meta.url), "utf8");
const settingsAdminSource = await readFile(new URL("../assets/js/theme-settings-admin.js", import.meta.url), "utf8");
const styleSource = await readFile(new URL("../assets/css/visual-builder.css", import.meta.url), "utf8");

function wait(window, delay = 0) {
	return new Promise((resolve) => window.setTimeout(resolve, delay));
}

function createDom({ nativeHomepageActions = false, previewLoadTimeoutMs = 15000 } = {}) {
	const dom = new JSDOM(`<!doctype html><html><body>
		<div id="jluxe-visual-builder" data-preview-kind="live">
			<form id="jluxe-visual-builder-form">
				<span data-jvb-save-state>پیش‌نویس تازه</span>
				<p data-jvb-section-count>پیش‌نویس مستقل · 1 بخش</p>
				<button class="jvb-save-button" name="action" value="jluxe_visual_builder_save"></button>
				<button data-jvb-publish name="action" value="jluxe_visual_builder_publish"></button>
				<button type="button" data-jvb-undo disabled></button>
				<button type="button" data-jvb-redo disabled></button>
				<nav class="jvb-tabs">
					<button type="button" data-jvb-tab="components" aria-selected="true"></button>
					<button type="button" data-jvb-tab="settings" aria-selected="false"></button>
					<button type="button" data-jvb-tab="structure" aria-selected="false"></button>
					<button type="button" data-jvb-tab="history" aria-selected="false"></button>
					<button type="button" data-jvb-tab="page" aria-selected="false"></button>
				</nav>
				<h2 data-jvb-panel-title></h2><span data-jvb-panel-count></span>
				<div data-jvb-panel="components"></div>
				<div data-jvb-panel="settings" hidden><span data-jvb-selection-title></span><div data-jvb-canvas><div id="jluxe-hb-sections"><div class="jluxe-hb-section" data-type="text"><details><summary><span class="jluxe-hb-type-label">متن سفارشی</span><label class="jluxe-hb-enabled"><input type="checkbox" name="homepage[sections][0][enabled]" value="1" checked></label></summary><input name="homepage[sections][0][title]" value="متن فعلی"></details></div></div></div></div>
				<div data-jvb-panel="structure" hidden><ol data-jvb-outline></ol></div>
				<div data-jvb-panel="history" hidden><span data-jvb-history-count></span><p data-jvb-history-empty></p><ol data-jvb-history-list></ol></div>
				<div data-jvb-panel="page" hidden></div>
				<input data-jvb-search>
				<div data-jvb-group>
					<button type="button" data-jvb-add-section data-type="banner" data-search-label="بنر تک"><span class="jvb-component-icon"></span>بنر تک</button>
				</div>
				<p data-jvb-empty-search hidden></p>
				<span data-jvb-outline-count>1</span>
				<p data-jvb-outline-empty hidden></p>
				<div data-jvb-canvas-wrap data-viewport="desktop">
					<div data-jvb-live-preview>
						<div data-jvb-preview-frame-shell><iframe data-jvb-preview-frame src="about:blank"></iframe><div data-jvb-preview-loading hidden></div><div data-jvb-preview-drop-overlay hidden></div></div>
						<span data-jvb-preview-status></span>
					</div>
				</div>
				<a data-jvb-open-preview href="#" aria-disabled="true" tabindex="-1"></a>
				<button type="button" data-jvb-viewport="desktop" aria-pressed="true"></button>
				<button type="button" data-jvb-viewport="tablet" aria-pressed="false"></button>
				<button type="button" data-jvb-viewport="mobile" aria-pressed="false"></button>
				<button type="button" data-jvb-preview-toggle aria-pressed="false"><span data-jvb-preview-label>تمرکز روی پیش‌نمایش</span></button>
				<strong data-jvb-stage-title>پیش‌نمایش زندهٔ صفحهٔ اصلی</strong>
				<span data-jvb-stage-subtitle></span>
				<select id="jluxe-hb-add-type"><option value="banner">banner</option></select>
				<button type="button" id="jluxe-hb-add"></button>
				<div id="jluxe-hb-templates" hidden>
					<template data-hb-template="banner"><div class="jluxe-hb-section" data-type="banner"><details open><summary><span class="jluxe-hb-type-label">بنر تک</span><label class="jluxe-hb-enabled"><input type="checkbox" name="homepage[sections][__INDEX__][enabled]" value="1" checked></label></summary><input name="homepage[sections][__INDEX__][title]" value=""></details></div></template>
				</div>
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
	window.jluxeVisualBuilder = {
		ajaxUrl: "https://shop.test/wp-admin/admin-ajax.php",
		previewNonce: "preview-nonce",
		previewAction: "jluxe_visual_builder_preview",
		previewLoadTimeoutMs,
	};
	window.__previewRequests = [];
	window.fetch = (url, init) => {
		window.__previewRequests.push({ url, body: init.body });
		const revision = window.__previewRequests.length;
		return Promise.resolve({
			ok: true,
			json: () => Promise.resolve({
				success: true,
				data: { url: `https://shop.test/?jluxe_builder_preview=live&jvb_revision=${revision}` },
			}),
		});
	};

	const sections = window.document.getElementById("jluxe-hb-sections") || window.document.querySelector("[data-jvb-canvas]");
	sections.id = "jluxe-hb-sections";
	if (nativeHomepageActions) {
		window.jQuery.fn.sortable = function () { return this; };
		window.jQuery.fn.wpColorPicker = function () { return this; };
		window.eval(settingsAdminSource);
	} else {
		window.document.getElementById("jluxe-hb-add").addEventListener("click", () => {
			const index = sections.querySelectorAll(":scope > .jluxe-hb-section").length;
			const section = window.document.createElement("div");
			section.className = "jluxe-hb-section";
			section.setAttribute("data-type", "banner");
			section.innerHTML = `<details open><summary><span class="jluxe-hb-type-label">بنر تک</span><label class="jluxe-hb-enabled"><input type="checkbox" name="homepage[sections][${index}][enabled]" value="1" checked></label></summary><input name="homepage[sections][${index}][title]"></details>`;
			sections.appendChild(section);
		});
	}
	window.eval(source);
	return dom;
}

function fakeDataTransfer() {
	const values = new Map();
	const types = [];
	return {
		types,
		effectAllowed: "",
		dropEffect: "",
		setData(type, value) {
			if (!types.includes(type)) types.push(type);
			values.set(type, value);
		},
		getData(type) {
			return values.get(type) || "";
		},
	};
}

function attachDataTransfer(event, dataTransfer) {
	Object.defineProperty(event, "dataTransfer", { value: dataTransfer });
	return event;
}

test("library search and tabs stay separate, while added widgets update the private live preview", async () => {
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
	const tabArrow = new dom.window.KeyboardEvent("keydown", { key: "ArrowDown", bubbles: true, cancelable: true });
	componentTab.dispatchEvent(tabArrow);
	assert.equal(tabArrow.defaultPrevented, true);
	assert.equal(document.querySelector('[data-jvb-tab="settings"]').getAttribute("aria-selected"), "true", "the vertical tab rail supports arrow-key navigation");
	componentTab.click();

	const search = document.querySelector("[data-jvb-search]");
	const component = document.querySelector("[data-jvb-add-section]");
	search.value = "ناموجود";
	search.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(component.hidden, true);
	assert.equal(document.querySelector("[data-jvb-empty-search]").hidden, false);
	search.value = "بنر";
	search.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	assert.equal(component.hidden, false);

	assert.equal(document.querySelector("[data-jvb-open-preview]").getAttribute("aria-disabled"), "true", "opening a saved or public URL is blocked until a current private snapshot is ready");
	await wait(dom.window, 10);
	assert.equal(dom.window.__previewRequests.length, 1, "initial form state gets a live preview");
	assert.equal(document.querySelector("[data-jvb-open-preview]").hasAttribute("aria-disabled"), false, "the current private preview link becomes available only after the server responds");
	component.click();
	await wait(dom.window, 30);
	assert.equal(sections.querySelectorAll(":scope > .jluxe-hb-section").length, 2);
	assert.equal(document.querySelector("[data-jvb-outline-count]").textContent, "2");
	assert.match(document.querySelector("[data-jvb-save-state]").textContent, /ذخیره‌نشده/);
	await wait(dom.window, 180);

	const requests = dom.window.__previewRequests;
	assert.ok(requests.length >= 2, "adding a component refreshes the server-rendered preview");
	const latestRequest = requests[requests.length - 1];
	assert.equal(latestRequest.url, "https://shop.test/wp-admin/admin-ajax.php");
	assert.equal(latestRequest.body.get("action"), "jluxe_visual_builder_preview");
	assert.equal(latestRequest.body.get("nonce"), "preview-nonce");
	assert.equal(latestRequest.body.get("homepage[sections][1][title]"), "");
	assert.match(document.querySelector("[data-jvb-preview-frame]").getAttribute("src"), /jluxe_builder_preview=live/);
	assert.equal(document.querySelector("[data-jvb-open-preview]").getAttribute("href"), document.querySelector("[data-jvb-preview-frame]").getAttribute("src"));
	assert.match(document.querySelector("[data-jvb-preview-status]").textContent, /به‌روز شد/);
	dom.window.close();
});

test("an out-of-order old iframe load cannot clear the loading state for the latest private revision", async () => {
	const dom = createDom();
	const { document } = dom.window;
	await wait(dom.window, 15);
	const frame = document.querySelector("[data-jvb-preview-frame]");
	const firstUrl = frame.getAttribute("src");
	assert.match(firstUrl, /jvb_revision=1/);

	document.querySelector("[data-jvb-add-section]").click();
	await wait(dom.window, 160);
	const latestUrl = frame.getAttribute("src");
	assert.match(latestUrl, /jvb_revision=2/);
	assert.notEqual(latestUrl, firstUrl);
	assert.equal(document.querySelector("[data-jvb-preview-loading]").hidden, false);

	Object.defineProperty(frame.contentDocument, "URL", { configurable: true, value: firstUrl });
	frame.dispatchEvent(new dom.window.Event("load"));
	assert.equal(document.querySelector("[data-jvb-preview-loading]").hidden, false, "the previous document is not mistaken for the new private revision");
	Object.defineProperty(frame.contentDocument, "URL", { configurable: true, value: latestUrl });
	frame.dispatchEvent(new dom.window.Event("load"));
	assert.equal(document.querySelector("[data-jvb-preview-loading]").hidden, true, "the spinner clears only after the matching revision document loads");
	dom.window.close();
});

test("an iframe navigation timeout clears the spinner, reports the failure, and keeps the latest private link", async () => {
	const dom = createDom({ previewLoadTimeoutMs: 20 });
	const { document } = dom.window;
	await wait(dom.window, 55);
	const frame = document.querySelector("[data-jvb-preview-frame]");
	const openLink = document.querySelector("[data-jvb-open-preview]");
	assert.equal(document.querySelector("[data-jvb-preview-loading]").hidden, true);
	assert.match(document.querySelector("[data-jvb-preview-status]").textContent, /بارگذاری نشد/);
	assert.equal(document.querySelector("[data-jvb-preview-status]").classList.contains("is-error"), true);
	assert.equal(openLink.getAttribute("aria-disabled"), null);
	assert.equal(openLink.getAttribute("href"), frame.getAttribute("src"), "a frame load failure does not discard the private URL returned by WordPress");
	Object.defineProperty(frame.contentDocument, "URL", { configurable: true, value: frame.getAttribute("src") });
	frame.dispatchEvent(new dom.window.Event("load"));
	assert.equal(document.querySelector("[data-jvb-preview-status]").classList.contains("is-error"), false, "a late successful reload clears the frame-load error");
	dom.window.close();
});

test("a failed live-preview refresh reports an error without replacing the last good iframe URL", async () => {
	const dom = createDom();
	const { document } = dom.window;
	let previewRequests = 0;
	dom.window.fetch = () => {
		previewRequests += 1;
		if (previewRequests > 1) return Promise.reject(new Error("Preview network failure"));
		return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data: { url: "about:blank" } }) });
	};
	await wait(dom.window, 12);
	const frame = document.querySelector("[data-jvb-preview-frame]");
	const openLink = document.querySelector("[data-jvb-open-preview]");
	const lastGoodUrl = frame.getAttribute("src");
	document.querySelector("[data-jvb-add-section]").click();
	await wait(dom.window, 190);

	assert.equal(previewRequests, 2);
	assert.equal(frame.getAttribute("src"), lastGoodUrl);
	assert.equal(openLink.getAttribute("href"), lastGoodUrl);
	assert.equal(document.querySelector("[data-jvb-preview-loading]").hidden, true);
	assert.equal(document.querySelector("[data-jvb-preview-status]").classList.contains("is-error"), true);
	assert.match(document.querySelector("[data-jvb-preview-status]").textContent, /Preview network failure/);
	dom.window.close();
});

test("undo, redo, keyboard shortcuts, and history restore the independent homepage draft", async () => {
	const dom = createDom();
	const { document } = dom.window;
	await wait(dom.window, 15);

	const component = document.querySelector("[data-jvb-add-section]");
	component.click();
	await wait(dom.window, 30);
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["text", "banner"]);
	assert.equal(document.querySelector('[data-jvb-tab="settings"]').getAttribute("aria-selected"), "true", "adding or selecting a section opens its native settings panel");
	await wait(dom.window, 350);

	let cards = Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section"));
	cards[1].querySelector('[data-jvb-section-action="up"]').click();
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["banner", "text"], "the section action moves one item without losing its fields");
	await wait(dom.window, 350);

	const undo = document.querySelector("[data-jvb-undo]");
	const redo = document.querySelector("[data-jvb-redo]");
	assert.equal(undo.disabled, false);
	undo.click();
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["text", "banner"]);
	assert.equal(redo.disabled, false);
	redo.click();
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["banner", "text"]);

	const historyTab = document.querySelector('[data-jvb-tab="history"]');
	historyTab.click();
	assert.equal(document.querySelector('[data-jvb-panel="history"]').hidden, false);
	assert.ok(document.querySelector('[data-jvb-history-list] [data-jvb-history-id]'), "the history panel exposes restorable entries");
	const keyboardUndo = new dom.window.KeyboardEvent("keydown", { key: "z", ctrlKey: true, bubbles: true, cancelable: true });
	document.body.dispatchEvent(keyboardUndo);
	assert.equal(keyboardUndo.defaultPrevented, true);
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["text", "banner"]);
	const keyboardRedo = new dom.window.KeyboardEvent("keydown", { key: "z", shiftKey: true, ctrlKey: true, bubbles: true, cancelable: true });
	document.body.dispatchEvent(keyboardRedo);
	assert.equal(keyboardRedo.defaultPrevented, true);
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["banner", "text"]);

	dom.window.close();
});

test("undo and redo restore native text and checkbox values while text-field Ctrl+Z remains native", async () => {
	const dom = createDom();
	const { document } = dom.window;
	await wait(dom.window, 15);
	const title = document.querySelector('input[name="homepage[sections][0][title]"]');
	const enabled = document.querySelector(".jluxe-hb-enabled input[type=checkbox]");
	title.value = "متن ویرایش‌شده";
	title.dispatchEvent(new dom.window.Event("input", { bubbles: true }));
	enabled.checked = false;
	enabled.dispatchEvent(new dom.window.Event("change", { bubbles: true }));
	await wait(dom.window, 350);

	const undo = document.querySelector("[data-jvb-undo]");
	const redo = document.querySelector("[data-jvb-redo]");
	const nativeUndo = new dom.window.KeyboardEvent("keydown", { key: "z", ctrlKey: true, bubbles: true, cancelable: true });
	title.dispatchEvent(nativeUndo);
	assert.equal(nativeUndo.defaultPrevented, false, "typing undo stays with the browser when a field has focus");
	undo.click();
	assert.equal(document.querySelector('input[name="homepage[sections][0][title]"]').value, "متن فعلی");
	assert.equal(document.querySelector(".jluxe-hb-enabled input[type=checkbox]").checked, true);
	redo.click();
	assert.equal(document.querySelector('input[name="homepage[sections][0][title]"]').value, "متن ویرایش‌شده");
	assert.equal(document.querySelector(".jluxe-hb-enabled input[type=checkbox]").checked, false);
	dom.window.close();
});

test("a library click uses the native WordPress homepage template to add a real section", async () => {
	const dom = createDom({ nativeHomepageActions: true });
	const { document } = dom.window;
	await wait(dom.window, 10);
	document.querySelector("[data-jvb-add-section]").click();
	await wait(dom.window, 25);
	const cards = Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section"));
	assert.deepEqual(cards.map((card) => card.getAttribute("data-type")), ["text", "banner"]);
	assert.equal(cards[1].querySelector('input[name="homepage[sections][1][title]"]') !== null, true, "the real PHP-style section template is cloned and reindexed by the native manager");
	assert.match(document.querySelector("[data-jvb-save-state]").textContent, /ذخیره‌نشده/);
	dom.window.close();
});

test("dragging a library widget through the native homepage builder inserts it at that visual position", async () => {
	const dom = createDom({ nativeHomepageActions: true });
	const { document } = dom.window;
	dom.window.fetch = (url, init) => {
		dom.window.__previewRequests.push({ url, body: init.body });
		return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data: { url: "about:blank" } }) });
	};
	await wait(dom.window, 10);

	const frame = document.querySelector("[data-jvb-preview-frame]");
	const frameDocument = frame.contentDocument || frame.contentWindow.document;
	assert.ok(frameDocument && frameDocument.body, "same-origin preview iframe exposes a document for editor interactions");
	frameDocument.body.innerHTML = `<main id="primary"><div class="jvb-preview-section" data-jvb-preview-index="0"><section class="jluxe-home-section">Existing content</section></div></main>`;
	frame.dispatchEvent(new dom.window.Event("load"));

	const component = document.querySelector("[data-jvb-add-section]");
	const transfer = fakeDataTransfer();
	component.dispatchEvent(attachDataTransfer(new dom.window.Event("dragstart", { bubbles: true, cancelable: true }), transfer));
	assert.equal(transfer.getData("application/x-jluxe-section"), "banner");

	const renderedSection = frameDocument.querySelector(".jluxe-home-section");
	const dragover = attachDataTransfer(new frameDocument.defaultView.Event("dragover", { bubbles: true, cancelable: true }), transfer);
	Object.defineProperty(dragover, "clientY", { value: 0 });
	renderedSection.dispatchEvent(dragover);
	assert.equal(dragover.defaultPrevented, true);

	const drop = attachDataTransfer(new frameDocument.defaultView.Event("drop", { bubbles: true, cancelable: true }), transfer);
	Object.defineProperty(drop, "clientY", { value: 0 });
	renderedSection.dispatchEvent(drop);
	await wait(dom.window, 30);

	const cards = Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section"));
	assert.deepEqual(cards.map((card) => card.getAttribute("data-type")), ["banner", "text"]);
	assert.equal(cards[0].querySelector("input[name^='homepage[sections][0]']") !== null, true);
	assert.equal(cards[1].querySelector("input[name^='homepage[sections][1]']") !== null, true);

	const click = new frameDocument.defaultView.MouseEvent("click", { bubbles: true, cancelable: true });
	renderedSection.dispatchEvent(click);
	assert.equal(cards[0].classList.contains("jvb-selected"), true, "clicking a section in preview selects its editor");
	assert.equal(document.querySelector('[data-jvb-tab="settings"]').getAttribute("aria-selected"), "true", "preview selection opens the matching native settings panel");
	assert.equal(click.defaultPrevented, true, "preview clicks edit instead of following storefront links");
	dom.window.close();
});

test("cross-origin iframe drag fallback survives DataTransfer restrictions and appends to the private preview form", async () => {
	const dom = createDom();
	const { document } = dom.window;
	dom.window.fetch = (url, init) => {
		dom.window.__previewRequests.push({ url, body: init.body });
		return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data: { url: "about:blank" } }) });
	};
	await wait(dom.window, 10);

	const frame = document.querySelector("[data-jvb-preview-frame]");
	Object.defineProperty(frame, "contentDocument", {
		configurable: true,
		get: () => { throw new dom.window.DOMException("Cross-origin access denied", "SecurityError"); },
	});
	frame.dispatchEvent(new dom.window.Event("load"));
	const overlay = document.querySelector("[data-jvb-preview-drop-overlay]");
	const component = document.querySelector("[data-jvb-add-section]");
	const restrictedTransfer = {
		types: [],
		dropEffect: "",
		setData() { throw new dom.window.DOMException("Drag data is restricted", "SecurityError"); },
		getData() { return ""; },
	};
	component.dispatchEvent(attachDataTransfer(new dom.window.Event("dragstart", { bubbles: true, cancelable: true }), restrictedTransfer));
	assert.equal(overlay.hidden, false, "an inaccessible iframe exposes the explicit append target");
	const dragover = attachDataTransfer(new dom.window.Event("dragover", { bubbles: true, cancelable: true }), restrictedTransfer);
	overlay.dispatchEvent(dragover);
	assert.equal(dragover.defaultPrevented, true);
	const drop = attachDataTransfer(new dom.window.Event("drop", { bubbles: true, cancelable: true }), restrictedTransfer);
	overlay.dispatchEvent(drop);
	await wait(dom.window, 30);

	assert.equal(drop.defaultPrevented, true);
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["text", "banner"]);
	assert.equal(overlay.hidden, true, "the fallback target always clears after drop");
	dom.window.close();
});

test("touch drag uses pointer events for precise same-origin insertion without adding a duplicate click", async () => {
	const dom = createDom();
	const { document } = dom.window;
	dom.window.fetch = (url, init) => {
		dom.window.__previewRequests.push({ url, body: init.body });
		return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, data: { url: "about:blank" } }) });
	};
	await wait(dom.window, 10);

	const frame = document.querySelector("[data-jvb-preview-frame]");
	const frameDocument = frame.contentDocument;
	frameDocument.body.innerHTML = '<main><div class="jvb-preview-section" data-jvb-preview-index="0"><section class="jluxe-home-section">Existing</section></div></main>';
	const renderedSection = frameDocument.querySelector(".jluxe-home-section");
	frame.getBoundingClientRect = () => ({ left: 20, top: 80, right: 420, bottom: 680, width: 400, height: 600 });
	renderedSection.getBoundingClientRect = () => ({ left: 0, top: 20, right: 400, bottom: 120, width: 400, height: 100 });
	frameDocument.elementFromPoint = () => renderedSection;
	frame.dispatchEvent(new dom.window.Event("load"));

	const component = document.querySelector("[data-jvb-add-section]");
	const pointerDown = new dom.window.MouseEvent("pointerdown", { bubbles: true, cancelable: true, clientX: 10, clientY: 10, button: 0 });
	Object.defineProperties(pointerDown, { pointerType: { value: "touch" }, pointerId: { value: 7 }, isPrimary: { value: true } });
	component.querySelector(".jvb-component-icon").dispatchEvent(pointerDown);
	const pointerMove = new dom.window.MouseEvent("pointermove", { bubbles: true, cancelable: true, clientX: 100, clientY: 130 });
	Object.defineProperty(pointerMove, "pointerId", { value: 7 });
	document.dispatchEvent(pointerMove);
	assert.equal(pointerMove.defaultPrevented, true, "the pointer drag takes over only after the movement threshold");
	assert.ok(document.querySelector(".jvb-touch-drag-ghost"), "the dragged widget stays visible over the iframe");

	const pointerUp = new dom.window.MouseEvent("pointerup", { bubbles: true, cancelable: true, clientX: 100, clientY: 130 });
	Object.defineProperty(pointerUp, "pointerId", { value: 7 });
	document.dispatchEvent(pointerUp);
	const syntheticClick = new dom.window.MouseEvent("click", { bubbles: true, cancelable: true });
	component.dispatchEvent(syntheticClick);
	await wait(dom.window, 30);

	assert.equal(syntheticClick.defaultPrevented, true, "the browser's post-drag click cannot add the same widget twice");
	assert.equal(document.querySelector(".jvb-touch-drag-ghost"), null);
	assert.deepEqual(Array.from(document.querySelectorAll("#jluxe-hb-sections > .jluxe-hb-section")).map((card) => card.getAttribute("data-type")), ["banner", "text"], "a touch drop inside the live preview inserts before the section at that position");
	dom.window.close();
});

test("device widths affect the live iframe, match the reference viewports, and focus mode keeps preview open", () => {
	assert.ok(styleSource.includes('.jvb-canvas-wrap[data-viewport="tablet"] .jvb-preview-frame-shell iframe {\n\tmax-width: 820px;'));
	assert.ok(styleSource.includes('.jvb-canvas-wrap[data-viewport="mobile"] .jvb-preview-frame-shell iframe {\n\tmax-width: 390px;'));
	assert.match(styleSource, /\.jvb-preview-frame-shell \{[\s\S]*?overflow: hidden;/, "the parent shell no longer creates a second scrollable viewport");
	assert.match(styleSource, /\.jvb-preview-frame-shell iframe \{[\s\S]*?min-height: 0;[\s\S]*?height: 100%;/, "the iframe fills the single preview viewport instead of exceeding the shell");
	assert.match(styleSource, /\.jvb-preview-frame-shell \{\n\t\tflex: 0 0 auto;[\s\S]*?height: 46vh;[\s\S]*?overflow: hidden;/, "mobile preview height is fixed once, while only the site inside the iframe scrolls");
	assert.ok(styleSource.includes("touch-action: none;"), "touch can drag a widget rather than invoking native page scrolling");
	const dom = createDom();
	const { document } = dom.window;
	const viewport = document.querySelector('[data-jvb-viewport="tablet"]');
	viewport.click();
	assert.equal(document.querySelector("[data-jvb-canvas-wrap]").getAttribute("data-viewport"), "tablet");
	assert.equal(viewport.getAttribute("aria-pressed"), "true");

	const previewToggle = document.querySelector("[data-jvb-preview-toggle]");
	previewToggle.click();
	assert.equal(document.getElementById("jluxe-visual-builder").classList.contains("jvb-preview-focus"), true);
	assert.equal(document.querySelector("[data-jvb-live-preview]").hidden, false);
	assert.equal(document.querySelector("[data-jvb-canvas]").hidden, false, "focus mode is managed by the layout, not by replacing the page preview");
	assert.equal(document.querySelector("[data-jvb-preview-label]").textContent, "بازگشت به ویرایش");
	previewToggle.click();
	assert.equal(document.getElementById("jluxe-visual-builder").classList.contains("jvb-preview-focus"), false);

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

test('focused inspector keeps all sibling controls in saved FormData and can show all again', async () => {
 const dom = createDom(); const { document } = dom.window;
 document.querySelector('[data-jvb-add-section]').click(); await wait(dom.window, 20);
 const sections = document.getElementById('jluxe-hb-sections');
 assert.equal(sections.classList.contains('jvb-inspector-active'), true);
 const data = new dom.window.FormData(document.querySelector('form'));
 assert.equal(data.has('homepage[sections][0][title]'), true);
 assert.equal(data.has('homepage[sections][1][title]'), true);
 document.querySelector('[data-jvb-show-all]').click();
 assert.equal(sections.classList.contains('jvb-inspector-active'), false);
 dom.window.close();
});

test('canvas toolbar selects native settings and inserts a widget after the selected section', async () => {
 const dom = createDom(); const { document } = dom.window;
 dom.window.fetch = () => Promise.resolve({ ok: true, json: () => Promise.resolve({ success:true, data:{ url:'about:blank' } }) });
 await wait(dom.window, 20);
 const frame = document.querySelector('[data-jvb-preview-frame]');
 const doc = frame.contentDocument;
 doc.body.innerHTML = '<main><div data-jvb-preview-index="0" class="jvb-preview-section"><section>Title</section></div></main>';
 Object.defineProperty(doc, 'URL', { configurable: true, value: frame.getAttribute('src') });
 frame.dispatchEvent(new dom.window.Event('load'));
 doc.querySelector('section').dispatchEvent(new frame.contentWindow.MouseEvent('click', { bubbles:true, cancelable:true }));
 const tools = doc.querySelector('#jvb-canvas-toolbar');
 assert.ok(tools); assert.equal(tools.hidden, false);
 tools.querySelector('[data-jvb-canvas-action="add"]').click();
 assert.equal(document.querySelector('[data-jvb-tab="components"]').getAttribute('aria-selected'), 'true');
 document.querySelector('[data-jvb-add-section]').click(); await wait(dom.window, 20);
 assert.equal(document.querySelectorAll('#jluxe-hb-sections > .jluxe-hb-section')[1].dataset.type, 'banner');
 dom.window.close();
});

test('layers drag reorder renumbers form names and remains undoable', async () => {
 const dom = createDom(); const { document } = dom.window;
 document.querySelector('[data-jvb-add-section]').click(); await wait(dom.window, 30);
 const rows = document.querySelectorAll('[data-jvb-layer-index]');
 rows[1].dispatchEvent(attachDataTransfer(new dom.window.Event('dragstart', { bubbles:true }), fakeDataTransfer()));
 rows[0].dispatchEvent(new dom.window.Event('drop', { bubbles:true, cancelable:true }));
 await wait(dom.window, 30);
 const first = document.querySelector('#jluxe-hb-sections > .jluxe-hb-section');
 assert.equal(first.dataset.type, 'banner');
 assert.ok(first.querySelector('[name="homepage[sections][0][title]"]'));
 dom.window.close();
});

test('inline heading editing writes plaintext to the native field and Escape cancels', async () => {
 const dom = createDom(); const { document } = dom.window;
 dom.window.fetch = () => Promise.resolve({ ok:true, json: () => Promise.resolve({ success:true, data:{url:'about:blank'} }) });
 await wait(dom.window, 20);
 const frame = document.querySelector('[data-jvb-preview-frame]'), doc = frame.contentDocument;
 doc.body.innerHTML = '<div data-jvb-preview-index="0" class="jvb-preview-section"><section><h2>متن فعلی</h2></section></div>';
 frame.dispatchEvent(new dom.window.Event('load'));
 const h = doc.querySelector('h2');
 h.dispatchEvent(new frame.contentWindow.MouseEvent('dblclick', {bubbles:true,cancelable:true}));
 assert.equal(h.getAttribute('contenteditable'), 'true');
 h.textContent = '<script>literal text</script>';
 h.dispatchEvent(new frame.contentWindow.Event('blur'));
 const input = document.querySelector('[name="homepage[sections][0][title]"]');
 assert.equal(input.value, '<script>literal text</script>');
 assert.equal(h.querySelector('script'), null);
 h.dispatchEvent(new frame.contentWindow.MouseEvent('dblclick', {bubbles:true,cancelable:true}));
 h.textContent = 'discard';
 h.dispatchEvent(new frame.contentWindow.KeyboardEvent('keydown', {key:'Escape',bubbles:true}));
 assert.equal(input.value, '<script>literal text</script>');
 dom.window.close();
});
