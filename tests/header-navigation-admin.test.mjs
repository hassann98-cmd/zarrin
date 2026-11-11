import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";
import jquery from "jquery";

const adminSource = await readFile(
  new URL("../assets/js/theme-settings-admin.js", import.meta.url),
  "utf8",
);
const adminCss = await readFile(
  new URL("../assets/css/theme-settings-admin.css", import.meta.url),
  "utf8",
);

function itemMarkup(index, label) {
  const key = String.fromCharCode(97 + index);
  return `
    <div class="jluxe-header-nav-item" role="listitem">
      <details class="jluxe-header-nav-item__accordion">
        <summary class="jluxe-header-nav-item__summary">
          <strong class="jluxe-header-nav-item__number">آیتم ${index + 1}</strong>
          <span data-jluxe-header-nav-title>${label}</span>
        </summary>
        <div class="jluxe-header-nav-item__body">
          <input data-jluxe-header-nav-label name="header_nav[items][${index}][label]" value="${label}">
          <input name="header_nav[items][${index}][url]" value="/${key}">
          <select name="header_nav[items][${index}][icon]"><option value="icon-${key}" selected>icon</option></select>
          <textarea name="header_nav[items][${index}][svg]">parent-svg-${key}</textarea>
          <input type="hidden" name="header_nav[items][${index}][id]" value="id-${key}">
          <input name="header_nav[items][${index}][children][0][label]" value="child-${key}">
          <input name="header_nav[items][${index}][children][0][url]" value="/${key}/child">
          <select name="header_nav[items][${index}][children][0][icon]"><option value="child-icon-${key}" selected>icon</option></select>
          <input name="header_nav[items][${index}][children][0][svg]" value="child-svg-${key}">
        </div>
      </details>
      <div class="jluxe-header-nav-item__controls">
        <button type="button" class="jluxe-header-nav-item__drag"></button>
        <button type="button" class="jluxe-header-nav-item__move-up" aria-label="move up">↑</button>
        <button type="button" class="jluxe-header-nav-item__move-down" aria-label="move down">↓</button>
      </div>
    </div>`;
}

async function mountAdminMenu() {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <form>
        <p data-jluxe-header-nav-live aria-live="polite"></p>
        <div class="jluxe-header-nav-list" role="list">
          ${itemMarkup(0, "خانه")}
          ${itemMarkup(1, "پیشنهادها")}
          ${itemMarkup(2, "مردانه")}
        </div>
      </form>
    </body></html>`,
    { url: "https://shop.test/wp-admin/", pretendToBeVisual: true, runScripts: "outside-only" },
  );

  const { window } = dom;
  const $ = jquery(window);
  $.fn.wpColorPicker = function () { return this; };
  $.fn.sortable = function (options) {
    return this.each(function () {
      if (options) $(this).data("headerNavSortableOptions", options);
    });
  };
  window.jQuery = $;
  window.$ = $;
  window.eval(adminSource);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  await new Promise((resolve) => window.setTimeout(resolve, 0));

  return { dom, window, $, list: window.document.querySelector(".jluxe-header-nav-list") };
}

function getItems(list) {
  return Array.from(list.querySelectorAll(":scope > .jluxe-header-nav-item"));
}

function getLabel(item) {
  return item.querySelector("[data-jluxe-header-nav-label]").value;
}

test("R357 header navigation renders responsive accordion and sortable-list styling", () => {
  assert.match(adminCss, /\.jluxe-header-nav-item__accordion/);
  assert.match(adminCss, /\.jluxe-header-nav-item__controls/);
  assert.match(adminCss, /@media \(max-width: 600px\)/);
});

test("R357 accessible move buttons reorder complete header-menu items and reindex every nested field", async () => {
  const { dom, window, list } = await mountAdminMenu();
  const originalItems = getItems(list);
  const movedItem = originalItems[2];

  movedItem.querySelector(".jluxe-header-nav-item__move-up").click();

  const items = getItems(list);
  assert.deepEqual(items.map(getLabel), ["خانه", "مردانه", "پیشنهادها"]);
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][url]"]').value, "/c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][id]"]').value, "id-c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][children][0][label]"]').value, "child-c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][children][0][url]"]').value, "/c/child");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][children][0][icon]"]').value, "child-icon-c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][children][0][svg]"]').value, "child-svg-c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][svg]"]').value, "parent-svg-c");
  assert.equal(movedItem.querySelector('[name="header_nav[items][1][icon]"]').value, "icon-c");
  assert.equal(movedItem.querySelector("[data-jluxe-header-nav-title]").textContent, "مردانه");
  assert.match(window.document.querySelector("[data-jluxe-header-nav-live]").textContent, /اکنون آیتم ۲ است/);
  assert.equal(items[0].querySelector(".jluxe-header-nav-item__move-up").disabled, true);
  assert.equal(items.at(-1).querySelector(".jluxe-header-nav-item__move-down").disabled, true);

  const labelInput = movedItem.querySelector("[data-jluxe-header-nav-label]");
  labelInput.value = "نام تازه";
  labelInput.dispatchEvent(new window.Event("input", { bubbles: true }));
  assert.equal(movedItem.querySelector("[data-jluxe-header-nav-title]").textContent, "نام تازه");

  movedItem.querySelector(".jluxe-header-nav-item__move-up").click();
  assert.equal(getItems(list)[0], movedItem);
  assert.equal(window.document.activeElement, movedItem.querySelector(".jluxe-header-nav-item__move-down"));

  dom.window.close();
});

test("R357 drag-sort update writes the visible order into submitted parent indexes", async () => {
  const { dom, $, list } = await mountAdminMenu();
  const originalItems = getItems(list);
  const movedItem = originalItems[2];
  list.insertBefore(movedItem, originalItems[0]);

  const sortableOptions = $(list).data("headerNavSortableOptions");
  assert.equal(sortableOptions.handle, ".jluxe-header-nav-item__drag");
  assert.equal(sortableOptions.items, "> .jluxe-header-nav-item");
  sortableOptions.update.call(list, null, { item: $(movedItem) });

  const items = getItems(list);
  assert.deepEqual(items.map(getLabel), ["مردانه", "خانه", "پیشنهادها"]);
  assert.equal(movedItem.querySelector('[name="header_nav[items][0][label]"]').value, "مردانه");
  assert.equal(movedItem.querySelector('[name="header_nav[items][0][children][0][label]"]').value, "child-c");
  assert.equal(items[2].querySelector('[name="header_nav[items][2][id]"]').value, "id-b");

  dom.window.close();
});
