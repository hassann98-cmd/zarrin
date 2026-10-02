// R93b — «دسته‌بندی‌ها»ی هدر: کلیک ← برگهٔ همه دسته‌بندی‌ها (megaMenu.url)،
// هاور همچنان مگامنو؛ دادهٔ قدیمیِ بدونِ url ⇒ فروشگاه، مثلِ قبل.
import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import { act } from "react";
import { JSDOM } from "jsdom";
import { mountIsland } from "../src/lib/islands.js";

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
    '<div id="root" data-jluxe-island="mega-menu"><a href="/ssr/">دسته‌بندی‌ها</a></div>',
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
  const el = dom.window.document.getElementById("root");
  let dispose;
  await act(async () => {
    dispose = await mountIsland(el, () => import("../src/islands/MegaMenu.js"));
  });
  if (typeof dispose === "function") disposers.push(() => act(async () => dispose()));
  return el;
}
const trigger = (el) =>
  [...el.querySelectorAll("a")].find((a) => a.textContent.trim() === "دسته‌بندی‌ها");

test("header «دسته‌بندی‌ها» opens the categories page; the hover panel is untouched", async () => {
  const el = await render({ categories: cats, url: "https://shop.test/product-categories/" });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.ok(el.querySelector('nav[aria-label="دسته‌بندی‌های اصلی"]'), "mega panel still rendered");
  const hrefs = [...el.querySelectorAll("nav a")].map((a) => a.getAttribute("href"));
  assert.deepEqual(hrefs, cats.map((c) => c.url), "each category still links to its own archive");
});

test("no categories yet → plain link, same target", async () => {
  const el = await render({ categories: [], url: "https://shop.test/product-categories/" });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/product-categories/");
  assert.equal(el.querySelector("nav"), null);
});

test("older localized data without url → shop, as before", async () => {
  const el = await render({ categories: cats });
  assert.equal(trigger(el).getAttribute("href"), "https://shop.test/فروشگاه/");
});
