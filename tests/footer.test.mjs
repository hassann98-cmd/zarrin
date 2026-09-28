import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { JSDOM } from "jsdom";

/*
 * R88 — the footer badges used to live in a hidden "staging" div and a script
 * polled every 100ms (plus a MutationObserver) for the React grid by its
 * Tailwind class string, then moved the badges into it. Now footer.php renders
 * the shell and the badge column in place; the island only portals into slots.
 */
const base = "https://shop.test/store/";
const shell = (badges = true) => `
<footer id="jluxe-footer-root" class="flow-root" data-jluxe-footer>
  <div class="mx-auto"><div class="card">
    <div data-jluxe-footer-slot="features"></div>
    <div class="grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4" id="main-grid">
      <div data-jluxe-footer-slot="columns" style="display:contents"></div>
      ${badges ? '<section class="jluxe-site-badges-section" id="badges"><h3>نمادهای سایت</h3><div class="jluxe-site-badge-card"><img src="https://trustseal.enamad.ir/logo.png" alt="enamad"></div></section>' : ""}
    </div>
    <div data-jluxe-footer-slot="bottom"></div>
  </div></div>
  <div data-jluxe-island="footer" id="island"></div>
</footer>`;

const disposers = [];
afterEach(async () => {
  for (const dispose of disposers.splice(0).reverse()) await dispose();
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.IS_REACT_ACT_ENVIRONMENT;
});

function dom(markup, footer = {}) {
  const instance = new JSDOM(`<!doctype html><body>${markup}</body>`, {
    url: base,
    pretendToBeVisual: true,
  });
  const win = instance.window;
  win.JLuxeThemeSettings = {
    siteName: "زرین",
    urls: { home: base, shop: base + "catalog/", track_order: base + "order-status/" },
    footer: {
      enabled: true,
      trust_badges: [],
      link_columns: [
        {
          title: "راهنما",
          links: [{ label: "پیگیری سفارش", url: "/track-order/", icon: "", svg: "" }],
        },
        {
          title: "شرکت",
          links: [{ label: "درباره ما", url: "/about-us/", icon: "", svg: "" }],
        },
      ],
      copyright: "© زرین",
      ...footer,
    },
    social: { instagram: { enabled: true, url: "https://instagram.com/x" } },
  };
  globalThis.window = win;
  globalThis.document = win.document;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  disposers.push(() => win.close());
  return win;
}

async function render() {
  const { default: Footer } = await import(
    `../src/islands/Footer.js?case=${Math.random()}`
  );
  const root = createRoot(document.getElementById("island"));
  disposers.push(() => act(() => root.unmount()));
  await act(async () => root.render(React.createElement(Footer)));
}

test("R88 the island fills the server slots; the badge column stays where PHP printed it", async () => {
  dom(shell());
  const badges = document.getElementById("badges");
  await render();
  const grid = document.getElementById("main-grid");
  const columns = grid.querySelector('[data-jluxe-footer-slot="columns"]');
  assert.ok(columns.querySelector("h3"), "link columns render inside the columns slot");
  assert.equal(
    [...columns.querySelectorAll("h3")].map((h) => h.textContent).join("|"),
    "راهنما|شرکت",
  );
  assert.equal(document.getElementById("badges"), badges, "the badge node is the very same node (never re-created)");
  assert.equal(badges.parentElement, grid, "badges are a direct item of the main grid");
  assert.equal(grid.lastElementChild, badges, "…placed after the link columns (in front of «شرکت» in RTL)");
  assert.equal(columns.nextElementSibling, badges);
  assert.equal(document.getElementById("island").childNodes.length, 0, "the island root itself renders nothing visible");
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  assert.match(bottom.textContent, /© زرین/);
  assert.ok(bottom.querySelector('a[aria-label="اینستاگرام"]'), "icon-only social link keeps its aria-label");
  assert.equal(
    columns.querySelector("a[data-jluxe-footer-link]").getAttribute("href"),
    base + "order-status/",
    "R87 mapping still applies inside the portal",
  );
  assert.equal(document.querySelectorAll("footer").length, 1, "no second <footer> is created");
});

test("R88 without badges the grid simply has no badge column", async () => {
  dom(shell(false));
  await render();
  assert.equal(document.querySelector(".jluxe-site-badges-section"), null);
  assert.ok(document.querySelector('[data-jluxe-footer-slot="columns"] h3'));
});

test("R88 an old child-theme footer.php (no slots) still gets the complete React footer", async () => {
  dom('<div id="island" data-jluxe-island="footer"></div>');
  await render();
  const footer = document.querySelector("#island > footer");
  assert.ok(footer, "legacy full footer rendered inside the island");
  assert.ok(footer.querySelector(".grid h3"));
  assert.match(footer.textContent, /© زرین/);
});

test("R88 a disabled footer renders nothing, slots or not", async () => {
  dom(shell(), { enabled: false });
  await render();
  assert.equal(document.querySelector('[data-jluxe-footer-slot="columns"]').childNodes.length, 0);
});

test("R88 the old staging/polling mover is gone from the PHP renderer", () => {
  const php = fs.readFileSync(new URL("../inc/theme-settings.php", import.meta.url), "utf8");
  const fn = php.slice(
    php.indexOf("function jluxe_render_site_trust_badges"),
    php.indexOf("function jluxe_footer_background_style"),
  );
  const code = fn.replace(/\/\*[\s\S]*?\*\//g, "");
  for (const banned of ["jluxe-site-badges-staging", "setInterval", "MutationObserver", "appendChild", "<script"])
    assert.ok(!code.includes(banned), `renderer no longer contains ${banned}`);
  const footer = fs.readFileSync(new URL("../footer.php", import.meta.url), "utf8");
  const grid = footer.indexOf('class="grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4"');
  assert.ok(grid > 0 && footer.indexOf("jluxe_render_site_trust_badges()", grid) > grid, "badges are printed inside the server grid");
  assert.ok(footer.indexOf('data-jluxe-footer-slot="columns"', grid) > grid);
});
