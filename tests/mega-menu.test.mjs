// R93b — «دسته‌بندی‌ها»ی هدر: کلیک ← برگهٔ همه دسته‌بندی‌ها (megaMenu.url)،
// هاور همچنان مگامنو؛ دادهٔ قدیمیِ بدونِ url ⇒ فروشگاه، مثلِ قبل.
import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { act } from "react";
import { JSDOM } from "jsdom";
import { mountIsland } from "../src/lib/islands.js";

const categoryTriggerCss = await readFile(
  new URL("../src/styles/sitewide-design.css", import.meta.url),
  "utf8",
);
const disposers = [];
afterEach(async () => {
  for (const dispose of disposers.splice(0).reverse()) await dispose();
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.IS_REACT_ACT_ENVIRONMENT;
});

const cats = [
  { id: "sale", label: "فروش ویژه", url: "https://shop.test/product-category/sale/", iconSvg: "", groups: [] },
  {
    id: "bath",
    label: "حمام",
    url: "https://shop.test/product-category/bath/",
    iconSvg: "",
    groups: [{ title: "حوله", url: "https://shop.test/product-category/towel/", items: [] }],
  },
];

async function render(megaMenu) {
  const appearance = megaMenu.appearance ?? {};
  const openMode = ["hover", "click", "both"].includes(appearance.openMode)
    ? appearance.openMode
    : "hover";
  const dom = new JSDOM(
    `<div id="jluxe-header-categories-menu" class="jluxe-header-categories-menu" data-jluxe-island="mega-menu" data-open-mode="${openMode}" data-trigger-effect="${appearance.triggerEffect ?? "lift"}" data-panel-effect="${appearance.panelEffect ?? "fade"}"><a href="/ssr/" class="jluxe-header-categories-trigger"><span class="jluxe-header-categories-label">دسته‌بندی‌ها</span><span class="jluxe-header-categories-chevron-wrap"><svg class="jluxe-header-categories-chevron"></svg></span></a></div>`,
    { url: "https://shop.test/", pretendToBeVisual: true },
  );
  dom.window.JLuxeThemeSettings = {
    urls: { home: "https://shop.test/", shop: "https://shop.test/فروشگاه/" },
    megaMenu,
  };
  dom.window.JLuxeMegaMenuChunks = {
    clickToggle: () => import("../src/islands/MegaMenuClickToggle.js"),
    panel: () => import("../src/islands/MegaMenuPanel.js"),
  };
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  disposers.push(() => dom.window.close());
  const el = dom.window.document.getElementById("jluxe-header-categories-menu");
  let dispose;
  await act(async () => {
    dispose = await mountIsland(el, () => import("../src/islands/MegaMenu.js"));
  });
  if (megaMenu.categories?.length) {
    await act(async () => {
      el.querySelector(".jluxe-header-categories-trigger").dispatchEvent(
        new dom.window.Event("pointerover", { bubbles: true }),
      );
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
  }
  if (typeof dispose === "function") disposers.push(() => act(async () => dispose()));
  return el;
}
const trigger = (el) =>
  [...el.querySelectorAll("a")].find((a) => a.textContent.trim() === "دسته‌بندی‌ها");

test("categories trigger exposes theme-controlled colors, icon, opening mode, effects, and reduced motion", () => {
  assert.match(categoryTriggerCss, /\.jluxe-header-categories-icon/);
  assert.match(categoryTriggerCss, /data-open-mode="hover"/);
  assert.match(categoryTriggerCss, /data-trigger-effect="lift"/);
  assert.match(categoryTriggerCss, /data-panel-effect="slide"/);
  assert.match(categoryTriggerCss, /--jluxe-categories-trigger-text/);
  assert.match(categoryTriggerCss, /--jluxe-categories-panel-bg/);
  assert.match(categoryTriggerCss, /font-weight: 700/);
  assert.match(categoryTriggerCss, /prefers-reduced-motion: reduce/);
});

test("header «دسته‌بندی‌ها» keeps its target, configurable icon, and hover panel", async () => {
  const el = await render({
    categories: cats,
    url: "https://shop.test/product-categories/",
    appearance: {
      openMode: "hover",
      iconMarkup: '<svg class="custom-categories-icon" aria-hidden="true"></svg>',
    },
  });
  assert.equal(el.id, "jluxe-header-categories-menu");
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(el.querySelector(".jluxe-header-categories-icon .custom-categories-icon"));
  assert.ok(trigger(el).querySelector(".jluxe-header-categories-chevron"), "the down-chevron remains visible beside the label");
  assert.ok(el.querySelector('nav[aria-label="دسته‌بندی‌های اصلی"]'), "mega panel still rendered");
  const hrefs = [...el.querySelectorAll("nav a")].map((a) => a.getAttribute("href"));
  assert.deepEqual(hrefs, cats.map((c) => c.url), "each category still links to its own archive");
});

test("click opening uses a separate accessible toggle and preserves the category-page link", async () => {
  const el = await render({
    categories: cats,
    url: "https://shop.test/product-categories/",
    appearance: { openMode: "click", panelEffect: "zoom" },
  });
  const link = trigger(el);
  const toggle = el.querySelector(".jluxe-header-categories-toggle");
  const inner = el.querySelector(".jluxe-header-categories-menu__inner");
  assert.equal(link.getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(toggle, "the arrow is a separate button, not part of the destination link");
  assert.equal(toggle.getAttribute("aria-expanded"), "false");
  assert.equal(el.dataset.openMode, "click");

  await act(async () => {
    toggle.dispatchEvent(new el.ownerDocument.defaultView.MouseEvent("click", { bubbles: true }));
  });
  assert.equal(toggle.getAttribute("aria-expanded"), "true");
  assert.ok(inner.classList.contains("is-click-open"));

  await act(async () => {
    el.ownerDocument.dispatchEvent(
      new el.ownerDocument.defaultView.KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
    );
  });
  assert.equal(toggle.getAttribute("aria-expanded"), "false");
  assert.equal(el.ownerDocument.activeElement, toggle, "Escape closes the panel and restores focus to its toggle");

  await act(async () => {
    toggle.dispatchEvent(new el.ownerDocument.defaultView.MouseEvent("click", { bubbles: true }));
    el.ownerDocument.body.dispatchEvent(
      new el.ownerDocument.defaultView.MouseEvent("pointerdown", { bubbles: true }),
    );
  });
  assert.equal(toggle.getAttribute("aria-expanded"), "false");
  assert.ok(!inner.classList.contains("is-click-open"), "an outside pointer closes the click-open panel");
});

test("an invalid opening mode falls back to hover without changing the category-page link", async () => {
  const el = await render({
    categories: cats,
    url: "https://shop.test/product-categories/",
    appearance: { openMode: "javascript" },
  });
  assert.equal(el.dataset.openMode, "hover");
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(trigger(el).querySelector(".jluxe-header-categories-chevron"));
  assert.equal(el.querySelector(".jluxe-header-categories-toggle"), null);
});

test("both mode supports a click toggle; empty categories keep the same link and omit the chevron", async () => {
  const both = await render({ categories: cats, appearance: { openMode: "both" } });
  const bothToggle = both.querySelector(".jluxe-header-categories-toggle");
  assert.ok(bothToggle);
  assert.equal(both.dataset.openMode, "both");
  await act(async () => {
    bothToggle.dispatchEvent(new both.ownerDocument.defaultView.MouseEvent("click", { bubbles: true }));
  });
  assert.equal(bothToggle.getAttribute("aria-expanded"), "true");
  await act(async () => {
    bothToggle.dispatchEvent(new both.ownerDocument.defaultView.MouseEvent("click", { bubbles: true }));
  });
  assert.equal(bothToggle.getAttribute("aria-expanded"), "false");

  const el = await render({
    categories: [],
    url: "https://shop.test/product-categories/",
    appearance: { iconMarkup: '<svg class="custom-categories-icon"></svg>' },
  });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(trigger(el).querySelector(".jluxe-header-categories-icon .custom-categories-icon"));
  assert.equal(trigger(el).querySelector(".jluxe-header-categories-chevron"), null);
  assert.equal(el.querySelector("nav"), null);
});

test("older localized data without url → shop, as before", async () => {
  const el = await render({ categories: cats });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/فروشگاه/");
});

test('category rows present icon then label then a conditional submenu arrow', async()=>{
 const el=await render({categories:[{...cats[0],iconSvg:'<svg data-custom-icon="sale"></svg>'},cats[1]]});
 const rows=el.querySelectorAll('.jluxe-header-categories-panel__category');
 assert.equal(rows.length,2);
 assert.ok(rows[0].children[0].querySelector('[data-custom-icon="sale"]'));
 assert.equal(rows[0].children[1].textContent,'فروش ویژه');
 assert.equal(rows[0].querySelector('.jluxe-mega-category-arrow'),null);
 assert.equal(rows[1].children[1].textContent,'حمام');
 assert.ok(rows[1].lastElementChild.classList.contains('jluxe-mega-category-arrow'));
 assert.equal(rows[0].href,cats[0].url);
});

test('WooCommerce category thumbnail occupies the leading icon slot without replacing the category name',async()=>{
 const el=await render({categories:[{...cats[1],iconImage:'https://shop.test/category.jpg',iconSvg:''}]});
 const row=el.querySelector('.jluxe-header-categories-panel__category');
 assert.equal(row.firstElementChild.tagName,'IMG');assert.equal(row.firstElementChild.alt,'');
 assert.equal(row.firstElementChild.getAttribute('width'),'24');
 assert.equal(row.children[1].textContent,'حمام');assert.ok(row.lastElementChild.classList.contains('jluxe-mega-category-arrow'));
});
