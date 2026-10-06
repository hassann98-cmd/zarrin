import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import test from "node:test";

const root = new URL("../", import.meta.url);
const stickySource = await readFile(new URL("assets/js/sticky-cta.js", root), "utf8");
const mobileNavSource = await readFile(new URL("src/islands/MobileNav.js", root), "utf8");
const productTemplate = await readFile(new URL("woocommerce/content-single-product.php", root), "utf8");
const classicProductTemplate = await readFile(new URL("woocommerce/content-single-product-classic.php", root), "utf8");
const storefrontStyles = await readFile(new URL("src/styles/storefront.css", root), "utf8");
const footerTemplate = await readFile(new URL("footer.php", root), "utf8");
const thankyouTemplate = await readFile(new URL("woocommerce/checkout/thankyou.php", root), "utf8");
const { isMobileNavItemActive, mobileNavContainerStyle } = await import("../src/lib/mobile-navigation.js");

function runStickyLayout(markup, measurements) {
  const dom = new JSDOM(markup, {
    url: "https://shop.test/product/demo/",
    runScripts: "outside-only",
  });
  const { window } = dom;
  Object.defineProperty(window, "innerHeight", { configurable: true, value: 800 });

  for (const [selector, rect] of Object.entries(measurements)) {
    const element = window.document.querySelector(selector);
    Object.defineProperty(element, "getBoundingClientRect", {
      configurable: true,
      value: () => ({ ...rect, width: 360, right: 360, left: 0, bottom: rect.top + rect.height }),
    });
  }

  const bar = window.document.querySelector("[data-jluxe-sticky-cta]");
  Object.defineProperty(bar, "offsetHeight", { configurable: true, value: 56 });
  window.eval(stickySource);
  return { bar, body: window.document.body, dom };
}

test("R115 the sticky CTA clears the topmost visible dock even when it appears later in DOM order", async () => {
  const { bar, body, dom } = runStickyLayout(
    '<nav class="jluxe-mobile-nav"></nav>' +
      '<section id="bottom-navigation"><div id="tabs"></div></section>' +
      '<div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div>',
    { ".jluxe-mobile-nav": { top: 732, height: 68 }, "#bottom-navigation": { top: 714, height: 76 } },
  );

  assert.equal(bar.style.bottom, "96px");
  assert.equal(bar.hasAttribute("data-jluxe-bottom-dock"), true);
  assert.equal(body.style.paddingBottom, "164px");
  await new Promise((resolve) => setTimeout(resolve, 0));
});

test("R115 the native mobile dock uses measured top + 10px gap (not assumed height or duplicated safe-area padding)", async () => {
  const { bar, body, dom } = runStickyLayout(
    '<nav class="jluxe-mobile-nav" style="padding-bottom:20px"></nav>' +
      '<div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div>',
    { ".jluxe-mobile-nav": { top: 732, height: 68 } },
  );

  assert.equal(bar.style.bottom, "78px");
  assert.equal(bar.hasAttribute("data-jluxe-bottom-dock"), true);
  assert.equal(body.style.paddingBottom, "146px");
  await new Promise((resolve) => setTimeout(resolve, 0));
});

test("R115 a mobile menu mounted after page startup triggers a fresh measured CTA layout", async () => {
  const dom = new JSDOM(
    '<!doctype html><html><body><div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div></body></html>',
    { url: "https://shop.test/product/demo/", runScripts: "outside-only" },
  );
  const { window } = dom;
  Object.defineProperty(window, "innerHeight", { configurable: true, value: 800 });
  const bar = window.document.querySelector("[data-jluxe-sticky-cta]");
  Object.defineProperty(bar, "offsetHeight", { configurable: true, value: 56 });
  window.eval(stickySource);
  assert.equal(bar.style.bottom, "0px");

  const nav = window.document.createElement("nav");
  nav.className = "jluxe-mobile-nav";
  Object.defineProperty(nav, "getBoundingClientRect", {
    configurable: true,
    value: () => ({ top: 732, height: 68, bottom: 800, width: 360, left: 0, right: 360 }),
  });
  window.document.body.append(nav);
  await new Promise((resolve) => window.setTimeout(resolve, 0));

  assert.equal(bar.style.bottom, "78px");
  assert.equal(window.document.body.style.paddingBottom, "146px");
  await new Promise((resolve) => window.setTimeout(resolve, 0));
});

test("R121 mobile navigation matches same-origin routes, normalizes slashes, and keeps account endpoints active", () => {
  const home = "https://shop.test/store/";
  const account = "https://shop.test/store/members/";

  assert.equal(isMobileNavItemActive("https://shop.test/store/", home, "home"), true);
  assert.equal(isMobileNavItemActive("https://shop.test/store/?campaign=spring", home, "home"), true);
  assert.equal(isMobileNavItemActive("https://shop.test/store/catalog", "https://shop.test/store/catalog/", "shop"), true);
  assert.equal(isMobileNavItemActive("https://shop.test/store/members/orders/view/51/", account, "account"), true);
  assert.equal(isMobileNavItemActive("https://shop.test/store/members/orders/", account, "account"), true);
  assert.equal(isMobileNavItemActive("https://shop.test/store/members-orders/", account, "account"), false);
  assert.equal(isMobileNavItemActive("https://outside.test/store/members/", account, "account"), false);
  assert.equal(isMobileNavItemActive("https://shop.test/store/members/orders/", account, "shop"), false);
  assert.match(mobileNavSource, /matchesCurrentRoute\(p, t, e\)/);
});

test("R121 the mobile dock adds the safe-area inset outside its configured touch row", () => {
  const safeArea = "env(safe-area-inset-bottom, 0px)";
  assert.deepEqual(mobileNavContainerStyle("68"), {
    boxSizing: "border-box",
    height: `calc(68px + ${safeArea})`,
    paddingBottom: safeArea,
  });
  assert.equal(mobileNavContainerStyle("45").height, `calc(60px + ${safeArea})`);
  assert.equal(mobileNavContainerStyle("120").height, `calc(82px + ${safeArea})`);
  assert.equal(mobileNavContainerStyle("invalid").height, `calc(68px + ${safeArea})`);
  assert.match(footerTemplate, /jluxe-footer-content-wrap/);
  assert.match(storefrontStyles, /margin-bottom: calc\(90px \+ env\(safe-area-inset-bottom, 0px\)\)/);
});

test("R121 product tabs and in-page targets account for the live sticky header and announcement offsets", () => {
  assert.match(productTemplate, /jluxe-product-section-nav sticky/);
  assert.match(productTemplate, /jluxe-product-section-anchor scroll-mt-16/);
  assert.match(storefrontStyles, /body\.jluxe-announcement-ready/);
  assert.match(storefrontStyles, /var\(--jluxe-announcement-sticky-height, 0px\)/);
  assert.match(storefrontStyles, /var\(--jluxe-product-header-row-height\)/);
  assert.match(storefrontStyles, /scroll-margin-top: calc\(var\(--jluxe-product-nav-top, 0px\) \+ 49px \+ 12px\)/);
  assert.match(classicProductTemplate, /top:calc\(var\(--jluxe-product-nav-top,0px\) \+ 6px\)/);
  assert.match(classicProductTemplate, /scroll-margin-top:calc\(var\(--jluxe-product-nav-top,0px\) \+ 6px \+ 60px \+ 12px\)/);
});

test("R121 the failed-checkout recovery actions stack at phone width", () => {
  assert.match(thankyouTemplate, /class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center"/);
  assert.match(thankyouTemplate, /h-12 w-full items-center justify-center rounded-lg bg-primary[^\"]*sm:w-auto/);
  assert.match(thankyouTemplate, /h-12 w-full items-center justify-center rounded-lg border border-border[^\"]*sm:w-auto/);
});
