// R92 — مرورگرِ دسته‌ها (React island): زیردسته‌ها بدونِ رفرش، آدرسِ ?cat،
// دکمهٔ «برگشت»، و حفظِ HTMLِ سرور وقتی اسکریپت/داده خراب است.
import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import { act } from "react";
import { JSDOM } from "jsdom";
import { mountIsland } from "../src/lib/islands.js";

const base = "https://shop.test/store/product-categories/";
const disposers = [];
afterEach(async () => {
  for (const dispose of disposers.splice(0).reverse()) await dispose();
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.IS_REACT_ACT_ENVIRONMENT;
});

const child = (id, name) => ({
  id,
  name,
  url: `${base}../c/${id}/`,
  img: null,
  svg: "<svg></svg>",
  count: 3,
  countLabel: "۳ کالا",
});
const data = (mode, extra = {}) => ({
  mode,
  active: mode === "panel" ? 100 : 0,
  defaultId: 100,
  gridLayout: "list",
  gridSize: 80,
  childSize: 64,
  railSize: 44,
  showCount: false,
  showAll: true,
  stickyHeader: true,
  resetHref: base,
  style: { "--jc-ch-m": "2" },
  gridStyle: { "--jc-cols-m": "1" },
  labels: {
    rail: "دسته‌های اصلی",
    back: "بازگشت به دسته‌ها",
    all: "همهٔ کالاهای این دسته",
    empty: "این دسته زیردسته‌ای ندارد",
  },
  parents: [
    {
      id: 100,
      name: "آشپزخانه",
      url: `${base}../c/100/`,
      catHref: `${base}?cat=100`,
      allUrl: `${base}../c/100/`,
      img: { src: "/a.png", srcset: "" },
      svg: "",
      count: 9,
      countLabel: "",
      hasPanel: true,
      children: [child(1, "قابلمه"), child(2, "لیوان")],
    },
    {
      id: 101,
      name: "حمام",
      url: `${base}../c/101/`,
      catHref: `${base}?cat=101`,
      allUrl: `${base}../c/101/`,
      img: null,
      svg: "<svg></svg>",
      count: 4,
      countLabel: "",
      hasPanel: true,
      children: [child(3, "حوله")],
    },
    {
      id: 102,
      name: "دکور",
      url: `${base}../c/102/`,
      catHref: mode === "panel" ? `${base}?cat=102` : "",
      allUrl: `${base}../c/102/`,
      img: null,
      svg: "",
      count: 1,
      countLabel: "",
      hasPanel: mode === "panel",
      children: [],
    },
    {
      id: 0,
      name: "فروش ویژه",
      url: "https://shop.test/store/shop/?on_sale=1",
      catHref: "",
      allUrl: "",
      img: null,
      svg: "",
      count: -1,
      countLabel: "",
      hasPanel: false,
      children: [],
    },
  ],
  ...extra,
});

const SSR =
  '<nav class="jc-rail"><a class="jc-rail__item" href="?cat=101">حمام (سرور)</a></nav>';
function page(json, url = base) {
  const script =
    json === undefined
      ? ""
      : `<script type="application/json" id="jluxe-cats-data">${typeof json === "string" ? json : JSON.stringify(json)}</script>`;
  const dom = new JSDOM(
    `<div id="root" data-jluxe-island="categories-browser" data-jluxe-keep-ssr>${SSR}</div>${script}`,
    { url, pretendToBeVisual: true },
  );
  dom.window.scrollTo = () => {};
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  disposers.push(() => dom.window.close());
  return dom.window;
}
async function mount(win) {
  const el = win.document.getElementById("root");
  let dispose;
  await act(async () => {
    dispose = await mountIsland(
      el,
      () => import("../src/islands/CategoriesBrowser.js"),
    );
  });
  disposers.push(() => act(dispose));
  return el;
}
function click(win, node, init = {}) {
  const ev = new win.MouseEvent("click", {
    bubbles: true,
    cancelable: true,
    button: 0,
    ...init,
  });
  let prevented = null;
  // record what the island decided, then stop JSDOM's unimplemented navigation
  win.addEventListener(
    "click",
    (e) => {
      prevented = e.defaultPrevented;
      e.preventDefault();
    },
    { once: true },
  );
  act(() => node.dispatchEvent(ev));
  return prevented;
}
const panelTitle = (el) =>
  el.querySelector(".jc-panel .jc-panel__title")?.textContent;

test("panel: first category open, click switches subcategories in place and rewrites ?cat with replaceState", async () => {
  const win = page(data("panel"));
  const el = await mount(win);
  assert.equal(panelTitle(el), "آشپزخانه");
  assert.equal(el.querySelectorAll(".jc-child").length, 2);
  const len = win.history.length;
  const prevented = click(win, el.querySelector('[data-jc-parent="101"]'));
  assert.equal(prevented, true, "no navigation — the browser never reloads");
  assert.equal(panelTitle(el), "حمام");
  assert.deepEqual(
    [...el.querySelectorAll(".jc-child__name")].map((n) => n.textContent),
    ["حوله"],
  );
  assert.equal(win.location.search, "?cat=101");
  assert.equal(
    win.history.length,
    len,
    "replaceState — tab switches don't pile up in history",
  );
  assert.equal(
    el.querySelector(".jc-rail__item.is-active").getAttribute("data-jc-parent"),
    "101",
  );
  assert.equal(
    el.querySelector('[aria-current="true"]').getAttribute("data-jc-parent"),
    "101",
  );
  assert.equal(
    el.querySelector(".jc-panel__all").getAttribute("href"),
    `${base}../c/101/`,
  );
  click(win, el.querySelector('[data-jc-parent="102"]'));
  assert.match(
    el.querySelector(".jc-panel__empty").textContent,
    /زیردسته‌ای ندارد/,
  );
});

test("panel: ?cat in the URL wins; modifier clicks and custom links keep native navigation", async () => {
  const win = page(data("panel"), base + "?cat=101");
  const el = await mount(win);
  assert.equal(panelTitle(el), "حمام");
  assert.equal(
    click(win, el.querySelector('[data-jc-parent="100"]'), { ctrlKey: true }),
    false,
  );
  assert.equal(panelTitle(el), "حمام");
  const custom = [...el.querySelectorAll(".jc-rail__item")].find(
    (a) => !a.dataset.jcParent,
  );
  assert.equal(
    custom.getAttribute("href"),
    "https://shop.test/store/shop/?on_sale=1",
  );
  assert.equal(click(win, custom), false);
});

test("stack: grid → subcategories via pushState, focus moves to the title, Back returns to the grid", async () => {
  const win = page(data("stack"));
  const el = await mount(win);
  assert.ok(el.querySelector(".jluxe-cats-grid"));
  assert.equal(el.querySelector(".jc-panel"), null);
  assert.equal(
    el.querySelectorAll("[data-jc-parent]").length,
    2,
    "only categories with children drill in",
  );
  const len = win.history.length;
  assert.equal(click(win, el.querySelector('[data-jc-parent="100"]')), true);
  assert.equal(
    win.history.length,
    len + 1,
    "pushState — phone Back button returns to the grid",
  );
  assert.equal(win.location.search, "?cat=100");
  assert.equal(el.querySelector(".jluxe-cats-grid"), null);
  assert.equal(panelTitle(el), "آشپزخانه");
  assert.equal(
    win.document.activeElement,
    el.querySelector(".jc-panel__title"),
  );
  // simulate the browser Back (history.back + popstate)
  win.history.replaceState(null, "", base);
  await act(async () =>
    win.dispatchEvent(new win.PopStateEvent("popstate", { state: null })),
  );
  assert.ok(el.querySelector(".jluxe-cats-grid"));
  assert.equal(
    win.document.activeElement,
    el.querySelector('[data-jc-parent="100"]'),
    "focus returns to the tapped card",
  );
});

test("stack: deep link ?cat=101 then on-page «بازگشت» resets to the grid without leaving the page", async () => {
  const win = page(data("stack"), base + "?cat=101");
  const el = await mount(win);
  assert.equal(panelTitle(el), "حمام");
  const back = el.querySelector("button.jc-back");
  assert.match(back.textContent, /بازگشت به دسته‌ها/);
  click(win, back);
  assert.ok(el.querySelector(".jluxe-cats-grid"));
  assert.equal(win.location.search, "");
});

test("keep-ssr: a failed chunk import or broken JSON keeps the server-rendered browser (links still work)", async () => {
  let win = page(data("panel"));
  let el = win.document.getElementById("root");
  const origError = console.error;
  console.error = () => {};
  try {
    await mountIsland(el, () => Promise.reject(new Error("chunk 404")));
    assert.equal(el.innerHTML, SSR);
    win = page("{broken");
    el = await mount(win);
    assert.match(el.innerHTML, /حمام \(سرور\)/);
    assert.equal(el.querySelector('[role="alert"]'), null);
  } finally {
    console.error = origError;
  }
});
