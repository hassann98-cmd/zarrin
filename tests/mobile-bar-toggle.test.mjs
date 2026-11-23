import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import test from "node:test";
import {
  getMobilePriceBarVisible,
  getMobilePriceBarVisibleFromEvent,
  hasExternalMobileNavigation,
  hasExternalMobileNavigationFromEvent,
} from "../src/lib/mobile-dock-mode.js";

const source = await readFile(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const template = await readFile(new URL("../woocommerce/content-single-product.php", import.meta.url), "utf8");
const mobileNav = await readFile(new URL("../src/islands/MobileNav.js", import.meta.url), "utf8");
const floatingNav = await readFile(new URL("../src/islands/FloatingMobileNav.js", import.meta.url), "utf8");
const dockModeSource = await readFile(new URL("../src/lib/mobile-dock-mode.js", import.meta.url), "utf8");
const classicMarkup = await readFile(new URL("../inc/woocommerce.php", import.meta.url), "utf8");
const styles = await readFile(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const themeStyles = await readFile(new URL("../style.css", import.meta.url), "utf8");
const startMarker = '(function () {\n\tvar priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");';
const endMarker = "\n/**\n * سینکِ قیمتِ نوارِ چسبانِ موبایل";
const start = source.indexOf(startMarker);
const end = source.indexOf(endMarker, start);
assert.notEqual(start, -1, "the mobile price bar behavior is present in WooCommerce JS");
assert.notEqual(end, -1, "the mobile price bar behavior has a stable boundary for isolated testing");
const toggleScript = source.slice(start, end);

test("mobile dock state reads its explicit purchase/navigation mode", () => {
  const dom = new JSDOM(`<!doctype html><html><body><div data-jluxe-mobile-price-bar data-jluxe-mobile-bar-mode="purchase"></div></body></html>`);
  const { document } = dom.window;
  const priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");

  assert.equal(getMobilePriceBarVisible(document), true, "server markup starts in purchase mode");
  priceBar.setAttribute("data-jluxe-mobile-bar-ready", "true");
  priceBar.setAttribute("data-jluxe-mobile-bar-mode", "inactive");
  assert.equal(getMobilePriceBarVisible(document), false, "the explicit inactive mode is authoritative");
  assert.equal(getMobilePriceBarVisibleFromEvent({ detail: { priceBarVisible: false } }, document), false);
  priceBar.setAttribute("data-jluxe-mobile-bar-mode", "purchase");
  assert.equal(getMobilePriceBarVisibleFromEvent({ detail: { priceBarVisible: false } }, document), false, "duplicate events cannot invert the mode");
  assert.equal(getMobilePriceBarVisibleFromEvent({ type: "pageshow" }, document), true, "lifecycle events reconcile with the restored DOM");
  dom.window.close();
});

test("an active legacy bottom dock remains the sole product-page navigation", () => {
  const dom = new JSDOM(`<!doctype html><html><body><nav id="bottom-navigation"><a href="/cart/">سبد خرید</a></nav></body></html>`);
  const { document } = dom.window;
  const nav = document.querySelector("#bottom-navigation");

  assert.equal(hasExternalMobileNavigation(document), true);
  nav.hidden = true;
  nav.setAttribute("aria-hidden", "true");
  nav.setAttribute("inert", "");
  nav.setAttribute("data-jluxe-mobile-bar-available", "true");
  assert.equal(hasExternalMobileNavigation(document), true, "availability survives while the purchase bar temporarily yields the dock");
  nav.setAttribute("data-jluxe-mobile-bar-available", "false");
  assert.equal(hasExternalMobileNavigation(document), false, "an explicitly inactive dock does not suppress the React navigation");
  assert.equal(hasExternalMobileNavigationFromEvent({ detail: { externalNavigationAvailable: true } }, document), true);
  dom.window.close();
});

test("Boom's existing mobile toggle switches docks using inert and native click state", async () => {
  const dom = new JSDOM(
    `<!doctype html><html><head><style>
      [data-jluxe-mobile-bar-mode="inactive"] { opacity: 0; visibility: hidden; pointer-events: none; }
      #bottom-navigation[data-jluxe-mobile-bar-yielded] { opacity: 0; visibility: hidden; pointer-events: none; }
    </style></head><body>
      <nav id="bottom-navigation" aria-hidden="false"><a href="/cart/">سبد خرید</a></nav>
      <div style="transform:translateY(0)"><button data-jluxe-mobile-bar-toggle aria-controls="jluxe-mobile-price-bar" aria-pressed="true" aria-label="نمایش منوی موبایل">
        <svg data-jluxe-mobile-bar-mode-icon="menu"></svg>
        <svg data-jluxe-mobile-bar-mode-icon="purchase" hidden></svg>
      </button><div id="jluxe-mobile-price-bar" data-jluxe-mobile-price-bar data-jluxe-mobile-bar-mode="purchase">
        <button data-jluxe-mobile-bar-add>افزودن</button>
      </div></div>
    </body></html>`,
    { url: "https://shop.test/product/demo/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
  Object.defineProperty(window, "visualViewport", { configurable: true, value: { addEventListener() {}, removeEventListener() {} } });
  window.document.documentElement.dir = "rtl";
  window.JLuxeThemeSettings = { aiAssistant: { position: "end" } };
  const priceBar = window.document.querySelector("[data-jluxe-mobile-price-bar]");
  const toggle = window.document.querySelector("[data-jluxe-mobile-bar-toggle]");
  const nav = window.document.querySelector("#bottom-navigation");
  const menuIcon = toggle.querySelector('[data-jluxe-mobile-bar-mode-icon="menu"]');
  const purchaseIcon = toggle.querySelector('[data-jluxe-mobile-bar-mode-icon="purchase"]');
  Object.defineProperty(priceBar, "offsetHeight", { configurable: true, get: () => 80 });
  Object.defineProperty(nav, "offsetHeight", { configurable: true, get: () => 68 });
  let switches = 0;
  const modeEvents = [];
  window.addEventListener("jluxe:toggle-mobile-bar", event => { switches += 1; modeEvents.push(event.detail); });
  window.MutationObserver = undefined;

  window.eval(await readFile(new URL("../assets/js/storefront-utils.js", import.meta.url), "utf8"));
  window.eval(toggleScript);
  assert.equal(priceBar.getAttribute("data-jluxe-mobile-bar-mode"), "purchase");
  assert.equal(priceBar.hasAttribute("inert"), false);
  assert.equal(priceBar.hasAttribute("aria-hidden"), false);
  assert.equal(priceBar.getAttribute("data-jluxe-mobile-bar-ready"), "true");
  assert.equal(modeEvents[0].mode, "purchase");
  assert.equal(modeEvents[0].externalNavigationAvailable, true);
  assert.equal(toggle.parentElement, window.document.body);
  assert.equal(priceBar.parentElement, window.document.body);
  assert.equal(nav.hasAttribute("inert"), true, "the legacy dock is inert while purchase mode is active");
  assert.equal(nav.getAttribute("aria-hidden"), "true");
  assert.equal(nav.hidden, false, "the dock stays in the render tree");
  assert.equal(nav.style.display, "", "the dock is not hidden with display:none");
  assert.equal(toggle.style.bottom, "", "JS leaves bottom positioning to the stable CSS dock-height formula");
  assert.equal(window.document.body.style.getPropertyValue("--jluxe-active-dock-height"), "80px");
  assert.equal(toggle.getAttribute("aria-pressed"), "true");
  assert.equal(menuIcon.hidden, false);
  assert.equal(purchaseIcon.hidden, true);

  let bubbledClicks = 0;
  window.addEventListener("click", () => { bubbledClicks++; });
  toggle.click();
  assert.equal(bubbledClicks, 1, "the native switch click bubbles instead of being stopped in capture");
  assert.equal(priceBar.getAttribute("data-jluxe-mobile-bar-mode"), "inactive");
  assert.equal(priceBar.hasAttribute("inert"), true);
  assert.equal(priceBar.getAttribute("aria-hidden"), "true");
  assert.equal(toggle.getAttribute("aria-pressed"), "false");
  assert.equal(menuIcon.hidden, true);
  assert.equal(purchaseIcon.hidden, false);
  assert.equal(nav.hasAttribute("inert"), false, "navigation restores its interactive state");
  assert.equal(nav.getAttribute("aria-hidden"), "false", "navigation restores its original aria-hidden value");
  assert.equal(nav.hidden, false);
  assert.equal(nav.hasAttribute("data-jluxe-mobile-bar-yielded"), false);
  assert.equal(modeEvents.at(-1).mode, "navigation");
  assert.equal(window.document.body.style.paddingBottom, "", "switching does not change body padding");
  assert.equal(window.document.body.style.getPropertyValue("--jluxe-active-dock-height"), "68px");

  await new Promise(resolve => window.setTimeout(resolve, 90));
  toggle.click();
  assert.equal(priceBar.getAttribute("data-jluxe-mobile-bar-mode"), "purchase");
  assert.equal(nav.hasAttribute("inert"), true);
  const activeHeight = window.document.body.style.getPropertyValue("--jluxe-active-dock-height");
  window.dispatchEvent(new window.Event("scroll"));
  assert.equal(window.document.body.style.getPropertyValue("--jluxe-active-dock-height"), activeHeight, "scroll does not reposition the switch or resize page layout");
  assert.equal(window.document.body.style.paddingBottom, "");
  dom.window.close();
});

test("the default product layout retains one accessible, visually meaningful mode toggle", () => {
  assert.match(source, /--jluxe-active-dock-height/);
  assert.match(source, /toggleBtn\.addEventListener\("click"/);
  assert.doesNotMatch(source, /window\.addEventListener\("scroll", schedulePositionToggle/);
  assert.doesNotMatch(source, /visualViewport\.addEventListener\("scroll", schedulePositionToggle/);
  assert.doesNotMatch(source, /toggleBtn\.style\.bottom/);
  assert.match(template, /data-jluxe-mobile-bar-toggle[\s\S]*?aria-controls="jluxe-mobile-price-bar"[\s\S]*?aria-pressed="true"/);
  assert.equal((template.match(/data-jluxe-mobile-bar-toggle/g) || []).length, 1);
  assert.match(template, /data-jluxe-mobile-bar-mode-icon="menu"/);
  assert.match(template, /data-jluxe-mobile-bar-mode="purchase"/);
  assert.doesNotMatch(template, /data-jluxe-mobile-price-bar[^>]*class="[^"]*\bhidden\b/);
  assert.match(dockModeSource, /getMobilePriceBarVisibleFromEvent/);
  assert.match(dockModeSource, /data-jluxe-mobile-bar-mode/);
  assert.match(dockModeSource, /data-jluxe-mobile-bar-ready/);
  assert.match(mobileNav, /getMobilePriceBarVisibleFromEvent\(event, document\)/);
  assert.match(mobileNav, /window\.addEventListener\("jluxe:toggle-mobile-bar", syncMode\)/);
  assert.match(mobileNav, /window\.addEventListener\("pageshow", syncMode\)/);
  assert.match(mobileNav, /if \(hasPriceBar \|\| hasExternalProductNav\) return null;/);
  assert.match(floatingNav, /getMobilePriceBarVisibleFromEvent\(event, document\)/);
  assert.match(floatingNav, /window\.addEventListener\("jluxe:toggle-mobile-bar", syncMode\)/);
  assert.match(floatingNav, /window\.addEventListener\("pageshow", syncMode\)/);
  assert.match(floatingNav, /if \(hasMobilePriceBar \|\| hasExternalProductNav\) return null;/);
  assert.match(source, /externalNavigationAvailable: hasAvailableBottomNavigation\(\)/);
  assert.match(source, /setBottomNavigationYielded\(priceMode\)/);
  assert.match(styles, /#bottom-navigation\[data-jluxe-mobile-bar-yielded\],[\s\S]*?opacity: 0;[\s\S]*?visibility: hidden;[\s\S]*?pointer-events: none;/);
  assert.match(classicMarkup, /data-jluxe-sticky-cta-mode-toggle[\s\S]*?aria-controls="jluxe-sticky-cta"[\s\S]*?aria-pressed="true"/);
  assert.match(styles, /body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="purchase"\] #bottom-navigation,[\s\S]*?opacity: 0;[\s\S]*?visibility: hidden;[\s\S]*?pointer-events: none;/);
  assert.match(styles, /body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="navigation"\] \.jluxe-sticky-cta\s*\{\s*opacity: 0;\s*visibility: hidden;\s*pointer-events: none;/);
  assert.match(styles, /body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="navigation"\] \.jluxe-mobile-nav\s*\{\s*opacity: 1;\s*visibility: visible;\s*pointer-events: auto;/);
  assert.match(styles, /\.jluxe-mobile-bar-toggle svg\[hidden\] \{ display: none; \}/);
  assert.match(styles, /body\.single-product:has\(\[data-jluxe-mobile-bar-toggle\]\)[\s\S]*?margin-bottom: calc\(146px \+ env\(safe-area-inset-bottom, 0px\)\)/);
  assert.match(styles, /body:has\(\[data-jluxe-sticky-cta-mode-toggle\]:not\(\[hidden\]\)\)[\s\S]*?margin-bottom: 0;/);
  assert.match(themeStyles, /\[data-jluxe-mobile-bar-toggle\]\s*\{\s*bottom: calc\(var\(--jluxe-active-dock-height, 76px\) \+ env\(safe-area-inset-bottom, 0px\) \+ 10px\);/);
  assert.match(themeStyles, /body:has\(#bottom-navigation\) \.jluxe-pwa-install\s*\{\s*bottom: calc\(96px \+ env\(safe-area-inset-bottom, 0px\)\) !important;/);
  assert.match(themeStyles, /body:has\(#bottom-navigation\) \[data-jluxe-mobile-bar-toggle\]\s*\{\s*bottom: calc\(var\(--jluxe-active-dock-height, 76px\) \+ env\(safe-area-inset-bottom, 0px\) \+ 10px\);/);
});
