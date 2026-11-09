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

test("mobile dock state recovers pre-hydration taps and treats events as explicit modes", () => {
  const dom = new JSDOM(`<!doctype html><html><body><div data-jluxe-mobile-price-bar class="hidden"></div></body></html>`);
  const { document } = dom.window;
  const priceBar = document.querySelector("[data-jluxe-mobile-price-bar]");

  assert.equal(getMobilePriceBarVisible(document), true, "server markup starts in purchase mode until the controller is ready");
  priceBar.setAttribute("data-jluxe-mobile-bar-ready", "true");
  assert.equal(getMobilePriceBarVisible(document), false, "a fast pre-hydration toggle is recovered from the hidden class");
  assert.equal(getMobilePriceBarVisibleFromEvent({ detail: { priceBarVisible: false } }, document), false);
  priceBar.classList.remove("hidden");
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

test("Boom's existing mobile toggle switches between the price bar and mobile navigation", async () => {
  const dom = new JSDOM(
    `<!doctype html><html><head><style>.hidden { display: none; } #bottom-navigation[hidden], #bottom-navigation[data-jluxe-mobile-bar-yielded] { display: none !important; }</style></head><body>
      <nav id="bottom-navigation" aria-hidden="false"><a href="/cart/">سبد خرید</a></nav>
      <button data-jluxe-mobile-bar-toggle aria-controls="jluxe-mobile-price-bar" aria-pressed="true" aria-label="نمایش منوی موبایل">
        <svg data-jluxe-mobile-bar-mode-icon="menu"></svg>
        <svg data-jluxe-mobile-bar-mode-icon="purchase" hidden></svg>
      </button>
      <div id="jluxe-mobile-price-bar" data-jluxe-mobile-price-bar class="hidden">
        <button data-jluxe-mobile-bar-add>افزودن</button>
      </div>
    </body></html>`,
    { url: "https://shop.test/product/demo/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  let viewportH = 800;
  Object.defineProperty(window, "innerHeight", { configurable: true, get: () => viewportH });
  Object.defineProperty(window, "visualViewport", {
    configurable: true,
    value: { height: 320, addEventListener() {}, removeEventListener() {} },
  });
  window.document.documentElement.dir = "rtl";
  window.JLuxeThemeSettings = { aiAssistant: { position: "end" } };
  let navTop = 732;
  const priceBar = window.document.querySelector("[data-jluxe-mobile-price-bar]");
  const toggle = window.document.querySelector("[data-jluxe-mobile-bar-toggle]");
  const nav = window.document.querySelector("#bottom-navigation");
  const menuIcon = toggle.querySelector('[data-jluxe-mobile-bar-mode-icon="menu"]');
  const purchaseIcon = toggle.querySelector('[data-jluxe-mobile-bar-mode-icon="purchase"]');
  priceBar.getBoundingClientRect = () => priceBar.classList.contains("hidden")
    ? { top: 800, bottom: 800, height: 0, width: 390, left: 0, right: 390 }
    : { top: 720, bottom: 800, height: 80, width: 390, left: 0, right: 390 };
  nav.getBoundingClientRect = () => ({ top: navTop, bottom: viewportH, height: viewportH - navTop, width: 390, left: 0, right: 390 });
  let switches = 0;
  const modeEvents = [];
  window.addEventListener("jluxe:toggle-mobile-bar", (event) => { switches += 1; modeEvents.push(event.detail); });
  window.MutationObserver = undefined;

  window.eval(toggleScript);
  assert.equal(priceBar.classList.contains("hidden"), false, "the price bar remains the initial mode");
  assert.equal(priceBar.getAttribute("data-jluxe-mobile-bar-ready"), "true", "the DOM records when mode state becomes authoritative");
  assert.equal(modeEvents[0].mode, "purchase");
  assert.equal(modeEvents[0].priceBarVisible, true);
  assert.equal(modeEvents[0].externalNavigationAvailable, true);
  assert.equal(nav.hidden, true, "the external bottom dock yields while the purchase card is active");
  assert.equal(nav.getAttribute("data-jluxe-mobile-bar-yielded"), "");
  assert.equal(nav.getAttribute("data-jluxe-mobile-bar-available"), "true");
  assert.equal(nav.getAttribute("aria-hidden"), "true");
  assert.equal(nav.hasAttribute("inert"), true);
  switches = 0;
  assert.equal(toggle.getAttribute("aria-pressed"), "true");
  assert.equal(toggle.getAttribute("aria-label"), "نمایش منوی موبایل");
  assert.equal(menuIcon.hidden, false);
  assert.equal(purchaseIcon.hidden, true);
  assert.equal(toggle.style.bottom, "88px", "the control is positioned above the active price bar using layout-viewport geometry");
  assert.equal(
    toggle.style.getPropertyValue("inset-inline-start"),
    "max(12px, env(safe-area-inset-right, 0px))",
    "the mode switch sits on the side opposite the configured AI assistant launcher",
  );

  toggle.click();
  assert.equal(priceBar.classList.contains("hidden"), true);
  assert.equal(toggle.getAttribute("aria-pressed"), "false");
  assert.equal(toggle.getAttribute("aria-label"), "نمایش نوار خرید");
  assert.equal(menuIcon.hidden, true);
  assert.equal(purchaseIcon.hidden, false);
  assert.equal(toggle.style.bottom, "76px", "the control follows the mobile dock when it is active");
  assert.equal(nav.hidden, false, "the external menu is restored in navigation mode");
  assert.equal(nav.hasAttribute("data-jluxe-mobile-bar-yielded"), false);
  assert.equal(nav.getAttribute("aria-hidden"), "false", "pre-existing accessibility state is restored");
  assert.equal(nav.hasAttribute("inert"), false);
  assert.equal(modeEvents.at(-1).mode, "navigation");
  assert.equal(modeEvents.at(-1).priceBarVisible, false);
  assert.equal(modeEvents.at(-1).externalNavigationAvailable, true);
  assert.equal(switches, 1, "the mode event explicitly tells the React nav which dock is active");

  toggle.click();
  assert.equal(priceBar.classList.contains("hidden"), false);
  assert.equal(toggle.getAttribute("aria-pressed"), "true");
  assert.equal(menuIcon.hidden, false);
  assert.equal(purchaseIcon.hidden, true);
  assert.equal(nav.hidden, true);
  assert.equal(modeEvents.at(-1).mode, "purchase");
  assert.equal(switches, 2);

  toggle.click();
  viewportH = 820;
  navTop = 746;
  window.dispatchEvent(new window.Event("scroll"));
  await new Promise((resolve) => window.requestAnimationFrame(resolve));
  assert.equal(toggle.style.bottom, "82px", "the control is recalculated after mobile viewport/scroll geometry changes");
  assert.equal(switches, 3);
  dom.window.close();
});

test("the default product layout retains one accessible, visually meaningful mode toggle", () => {
  assert.match(source, /return window\.innerHeight \|\| document\.documentElement\.clientHeight \|\| 0;/);
  assert.match(source, /positionToggleAwayFromAssistant\(\)/);
  assert.match(source, /window\.addEventListener\("scroll", schedulePositionToggle/);
  assert.match(source, /visualViewport\.addEventListener\("scroll", schedulePositionToggle/);
  assert.match(template, /data-jluxe-mobile-bar-toggle[\s\S]*?aria-controls="jluxe-mobile-price-bar"[\s\S]*?aria-pressed="true"/);
  assert.equal((template.match(/data-jluxe-mobile-bar-toggle/g) || []).length, 1);
  assert.match(template, /data-jluxe-mobile-bar-mode-icon="menu"/);
  assert.match(template, /data-jluxe-mobile-bar-mode-icon="purchase"[^>]*hidden/);
  assert.match(dockModeSource, /getMobilePriceBarVisibleFromEvent/);
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
  assert.match(styles, /#bottom-navigation\[data-jluxe-mobile-bar-yielded\] \{ display: none !important; \}/);
  assert.match(classicMarkup, /data-jluxe-sticky-cta-mode-toggle[\s\S]*?aria-controls="jluxe-sticky-cta"[\s\S]*?aria-pressed="true"/);
  assert.match(styles, /body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="purchase"\] #bottom-navigation,[\s\S]*?body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="purchase"\] \.jluxe-mobile-nav \{ display: none; \}/);
  assert.match(styles, /body\.jluxe-has-sticky-cta\[data-jluxe-sticky-cta-mode="navigation"\] \.jluxe-sticky-cta \{ display: none; \}/);
  assert.match(styles, /\.jluxe-mobile-bar-toggle svg\[hidden\] \{ display: none; \}/);
  assert.match(styles, /body\.single-product:has\(\[data-jluxe-mobile-bar-toggle\]\)[\s\S]*?margin-bottom: calc\(146px \+ env\(safe-area-inset-bottom, 0px\)\)/);
  assert.match(styles, /body:has\(\[data-jluxe-sticky-cta-mode-toggle\]:not\(\[hidden\]\)\)[\s\S]*?margin-bottom: 0;/);
  assert.match(themeStyles, /\[data-jluxe-mobile-bar-toggle\]\s*\{\s*bottom: calc\(90px \+ env\(safe-area-inset-bottom, 0px\)\);/);
  assert.match(themeStyles, /body:has\(#bottom-navigation\) \.jluxe-pwa-install\s*\{\s*bottom: calc\(96px \+ env\(safe-area-inset-bottom, 0px\)\) !important;/);
  assert.match(themeStyles, /body:has\(#bottom-navigation\) \[data-jluxe-mobile-bar-toggle\]\s*\{\s*bottom: calc\(96px \+ env\(safe-area-inset-bottom, 0px\)\);/);
  assert.doesNotMatch(themeStyles, /\[data-jluxe-mobile-bar-toggle\]\s*\{\s*bottom:[^;]*!important;/, "measured inline positioning can win over the legacy fallback");
});
