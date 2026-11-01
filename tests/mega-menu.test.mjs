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
  const dom = new JSDOM(
    '<div id="jluxe-header-categories-menu" class="jluxe-header-categories-menu" data-jluxe-island="mega-menu"><a href="/ssr/" class="jluxe-header-categories-trigger">دسته‌بندی‌ها</a></div>',
    { url: "https://shop.test/", pretendToBeVisual: true },
  );
  dom.window.JLuxeThemeSettings = {
    urls: { home: "https://shop.test/", shop: "https://shop.test/فروشگاه/" },
    megaMenu,
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
  if (typeof dispose === "function") disposers.push(() => act(async () => dispose()));
  return el;
}
const trigger = (el) =>
  [...el.querySelectorAll("a")].find((a) => a.textContent.trim() === "دسته‌بندی‌ها");

test("R358 categories trigger styling is bold, unmistakably menu-like, and reduced-motion aware", () => {
  assert.match(categoryTriggerCss, /#masthead #jluxe-header-categories-menu > \.group > a::before/);
  assert.match(categoryTriggerCss, /font-weight: 700/);
  assert.match(categoryTriggerCss, /#2F6B52/);
  assert.match(categoryTriggerCss, /#C2410C/);
  assert.match(categoryTriggerCss, /prefers-reduced-motion: reduce/);
});

test("header «دسته‌بندی‌ها» opens the categories page; the hover panel is untouched", async () => {
  const el = await render({ categories: cats, url: "https://shop.test/product-categories/" });
  assert.equal(el.id, "jluxe-header-categories-menu");
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(trigger(el).querySelector("svg"), "the down-chevron remains visible beside the label");
  assert.ok(el.querySelector('nav[aria-label="دسته‌بندی‌های اصلی"]'), "mega panel still rendered");
  const hrefs = [...el.querySelectorAll("nav a")].map((a) => a.getAttribute("href"));
  assert.deepEqual(hrefs, cats.map((c) => c.url), "each category still links to its own archive");
});

test("no categories yet → compact plain link with no false submenu chevron, same target", async () => {
  const el = await render({ categories: [], url: "https://shop.test/product-categories/" });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.equal(trigger(el).querySelector("svg"), null);
  assert.equal(el.querySelector("nav"), null);
});

test("older localized data without url → shop, as before", async () => {
  const el = await render({ categories: cats });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/فروشگاه/");
});
