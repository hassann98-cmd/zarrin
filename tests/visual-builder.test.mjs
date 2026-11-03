import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import jquery from "jquery";

const source = await readFile(new URL("../assets/js/visual-builder.js", import.meta.url), "utf8");
const styleSource = await readFile(new URL("../assets/css/visual-builder.css", import.meta.url), "utf8");

function wait(window, delay = 0) {
	return new Promise((resolve) => window.setTimeout(resolve, delay));
}

function createDom() {
	const dom = new JSDOM(`<!doctype html><html><body>
		<div id="jluxe-visual-builder" data-preview-kind="live" data-preview-url="https://shop.test/">
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
					<button type="button" data-jvb-add-section data-type="banner" data-search-label="بنر تک">بنر تک</button>
				</div>
				<p data-jvb-empty-search hidden></p>
				<span data-jvb-outline-count>1</span>
				<p data-jvb-outline-empty hidden></p>
				<div data-jvb-canvas-wrap data-viewport="desktop">
					<div data-jvb-live-preview>
						<div data-jvb-preview-frame-shell><iframe data-jvb-preview-frame data-src="https://shop.test/"></iframe><div data-jvb-preview-loading hidden></div><div data-jvb-preview-drop-overlay hidden></div></div>
						<span data-jvb-preview-status></span>
					</div>
				</div>
				<a data-jvb-open-preview href="https://shop.test/"></a>
				<button type="button" data-jvb-viewport="desktop" aria-pressed="true"></button>
				<button type="button" data-jvb-viewport="tablet" aria-pressed="false"></button>
				<button type="button" data-jvb-viewport="mobile" aria-pressed="false"></button>
				<button type="button" data-jvb-preview-toggle aria-pressed="false"><span data-jvb-preview-label>تمرکز روی پیش‌نمایش</span></button>
				<strong data-jvb-stage-title>پیش‌نمایش زندهٔ صفحهٔ اصلی</strong>
				<span data-jvb-stage-subtitle></span>
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
	window.jluxeVisualBuilder = {
		ajaxUrl: "https://shop.test/wp-admin/admin-ajax.php",
		previewNonce: "preview-nonce",
		previewAction: "jluxe_visual_builder_preview",
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
	window.document.getElementById("jluxe-hb-add").addEventListener("click", () => {
		const index = sections.querySelectorAll(":scope > .jluxe-hb-section").length;
		const section = window.document.createElement("div");
		section.className = "jluxe-hb-section";
		section.setAttribute("data-type", "banner");
		section.innerHTML = `<details open><summary><span class="jluxe-hb-type-label">بنر تک</span><label class="jluxe-hb-enabled"><input type="checkbox" name="homepage[sections][${index}][enabled]" value="1" checked></label></summary><input name="homepage[sections][${index}][title]"></details>`;
		sections.appendChild(section);
	});
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

	await wait(dom.window, 10);
	assert.equal(dom.window.__previewRequests.length, 1, "initial form state gets a live preview");
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

test("dragging a library widget onto the rendered page inserts it at that visual position", async () => {
	const dom = createDom();
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

test("device widths affect the live iframe, match the reference viewports, and focus mode keeps preview open", () => {
	assert.ok(styleSource.includes('.jvb-canvas-wrap[data-viewport="tablet"] .jvb-preview-frame-shell iframe {\n\tmax-width: 820px;'));
	assert.ok(styleSource.includes('.jvb-canvas-wrap[data-viewport="mobile"] .jvb-preview-frame-shell iframe {\n\tmax-width: 390px;'));
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
