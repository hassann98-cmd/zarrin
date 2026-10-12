import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { JSDOM } from "jsdom";
import test from "node:test";

const root = new URL("../", import.meta.url);
const stickySource = await readFile(new URL("assets/js/sticky-cta.js", root), "utf8");
const mobileNavSource = await readFile(new URL("src/islands/MobileNav.js", root), "utf8");
const mainSource = await readFile(new URL("src/main.js", root), "utf8");
const floatingNavSource = await readFile(new URL("src/islands/FloatingMobileNav.js", root), "utf8");
const categoryDrawerSource = await readFile(new URL("src/islands/CategoryDrawer.js", root), "utf8");
const miniCartSource = await readFile(new URL("src/islands/MiniCart.js", root), "utf8");
const drawerStateSource = await readFile(new URL("src/lib/mobile-drawers.js", root), "utf8");
const assistantSource = await readFile(new URL("src/islands/AiAssistant.js", root), "utf8");
const productTemplate = await readFile(new URL("woocommerce/content-single-product.php", root), "utf8");
const classicProductTemplate = await readFile(new URL("woocommerce/content-single-product-classic.php", root), "utf8");
const storefrontStyles = await readFile(new URL("src/styles/storefront.css", root), "utf8");
const footerTemplate = await readFile(new URL("footer.php", root), "utf8");
const headerTemplate = await readFile(new URL("header.php", root), "utf8");
const headerIslandSource = await readFile(new URL("src/islands/Header.js", root), "utf8");
const shopToolbarSource = await readFile(new URL("inc/woocommerce.php", root), "utf8");
const thankyouTemplate = await readFile(new URL("woocommerce/checkout/thankyou.php", root), "utf8");
const {
  isMobileNavItemActive,
  mobileNavContainerStyle,
} = await import("../src/lib/mobile-navigation.js");
const { resolveMobileNavItem, MOBILE_NAV_ACTIONS, siteLink } = await import(
  "../src/lib/mobile-nav-actions.js"
);

function runStickyLayout(markup, measurements) {
  const dom = new JSDOM(markup, {
    url: "https://shop.test/product/demo/",
    runScripts: "outside-only",
  });
  const { window } = dom;
  Object.defineProperty(window, "innerHeight", { configurable: true, value: 800 });
  Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });

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

test("R115 purchase mode yields both supplied and native bottom docks instead of stacking over either", async () => {
  const { bar, body, dom } = runStickyLayout(
    '<nav class="jluxe-mobile-nav"></nav>' +
      '<section id="bottom-navigation"><div id="tabs"></div></section>' +
      '<div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div>',
    { ".jluxe-mobile-nav": { top: 732, height: 68 }, "#bottom-navigation": { top: 714, height: 76 } },
  );

  assert.equal(bar.style.bottom, "0px", "the purchase CTA occupies the viewport edge after both docks yield");
  assert.equal(bar.hasAttribute("data-jluxe-bottom-dock"), false);
  assert.equal(dom.window.document.querySelector(".jluxe-mobile-nav").hidden, true);
  assert.equal(dom.window.document.querySelector("#bottom-navigation").hidden, true);
  assert.equal(body.style.paddingBottom, "68px", "page clearance follows the single active CTA");
  await new Promise((resolve) => setTimeout(resolve, 0));
});

test("R115 purchase mode hides the native mobile dock and removes its safe-area space from the CTA", async () => {
  const { bar, body, dom } = runStickyLayout(
    '<nav class="jluxe-mobile-nav" style="padding-bottom:20px"></nav>' +
      '<div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div>',
    { ".jluxe-mobile-nav": { top: 732, height: 68 } },
  );

  assert.equal(bar.style.bottom, "0px");
  assert.equal(bar.hasAttribute("data-jluxe-bottom-dock"), false);
  assert.equal(dom.window.document.querySelector(".jluxe-mobile-nav").hidden, true);
  assert.equal(body.style.paddingBottom, "68px");
  await new Promise((resolve) => setTimeout(resolve, 0));
});

test("R115 a mobile menu mounted after startup yields immediately to an active purchase CTA", async () => {
  const dom = new JSDOM(
    '<!doctype html><html><body><div data-jluxe-sticky-cta class="is-visible"><button data-jluxe-sticky-add></button></div></body></html>',
    { url: "https://shop.test/product/demo/", runScripts: "outside-only" },
  );
  const { window } = dom;
  Object.defineProperty(window, "innerHeight", { configurable: true, value: 800 });
  Object.defineProperty(window, "innerWidth", { configurable: true, value: 390 });
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

  assert.equal(nav.hidden, true, "the newly mounted dock follows the already active purchase mode");
  assert.equal(bar.style.bottom, "0px");
  assert.equal(window.document.body.style.paddingBottom, "68px");
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
  assert.match(mobileNavSource, /isActive\(currentHref, item\.href, item\.id\)/);
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

test("R182 floating navigation resolves its built-in support, category, shop, cart, and account actions", () => {
  const urls = {
    home: "https://shop.test/store/",
    shop: "https://shop.test/store/store/",
    dashboard: "https://shop.test/store/members/",
    login: "https://shop.test/store/login/",
    cart: "https://shop.test/store/cart/",
    track_order: "https://shop.test/store/track-order/",
  };

  assert.deepEqual(MOBILE_NAV_ACTIONS, [
    "assistant",
    "categories",
    "home",
    "shop",
    "account",
    "track",
    "cart",
    "link",
  ]);
  assert.equal(
    resolveMobileNavItem({ action: "assistant" }, { urls, assistantUrl: "https://shop.test/store/faq/#open-ai-assistant" }).href,
    "https://shop.test/store/faq/#open-ai-assistant",
  );
  assert.deepEqual(
    (({ href, opensDrawer }) => ({ href, opensDrawer }))(
      resolveMobileNavItem({ action: "categories" }, { urls, categoriesUrl: "https://shop.test/store/product-categories/" }),
    ),
    { href: "https://shop.test/store/product-categories/", opensDrawer: "categories" },
  );
  assert.equal(resolveMobileNavItem({ action: "shop" }, { urls }).opensDrawer, undefined);
  assert.equal(resolveMobileNavItem({ action: "account" }, { urls, isLoggedIn: false }).href, urls.login);
  assert.equal(resolveMobileNavItem({ action: "account" }, { urls, isLoggedIn: true }).href, urls.dashboard);
  const cartItem = resolveMobileNavItem({ action: "cart" }, { urls });
  assert.equal(cartItem.href, urls.cart, "the configured cart page remains available as a native fallback");
  assert.equal(cartItem.opensDrawer, "cart", "the floating cart action opens the mini-cart drawer");
  assert.equal(resolveMobileNavItem({ action: "track" }, { urls }).href, urls.track_order);
});

test("R182 custom floating-menu links support new tabs while invalid actions fail closed", () => {
  const resolved = resolveMobileNavItem(
    { id: "support", label: "تماس", action: "link", url: "https://contact.test/", target_blank: true },
    { urls: { home: "https://shop.test/" } },
  );
  assert.equal(resolved.href, "https://contact.test/");
  assert.equal(resolved.targetBlank, true);
  assert.equal(resolveMobileNavItem({ action: "not-allowed" }, { urls: { home: "/" } }).href, "/");
  assert.match(mainSource, /mobile-nav-floating.*FloatingMobileNav\.js/);
  assert.match(footerTemplate, /mobile-nav-floating/);
  assert.match(floatingNavSource, /jluxe-mobile-nav--floating/);
  assert.match(floatingNavSource, /data-jluxe-mobile-nav-variant/);
  assert.match(floatingNavSource, /jluxe:toggle-mobile-bar/);
  assert.match(floatingNavSource, /data-jluxe-mobile-price-bar/);
});

test("R182 floating links follow configured routes and retain a WordPress subdirectory", () => {
  const urls = {
    home: "https://shop.test/store/",
    shop: "https://shop.test/store/catalog/",
  };
  assert.equal(siteLink("/faq#open-ai-assistant", urls), "https://shop.test/store/faq#open-ai-assistant");
  assert.equal(siteLink("/cats", urls), "https://shop.test/store/cats");
  assert.equal(siteLink("/shop", urls), urls.shop);
  const configuredFaq = { ...urls, faq: "https://shop.test/store/help-center/" };
  assert.equal(
    `${siteLink(configuredFaq.faq, configuredFaq)}#open-ai-assistant`,
    "https://shop.test/store/help-center/#open-ai-assistant",
  );
  assert.equal(siteLink("https://external.test/contact", urls), "https://external.test/contact");
  assert.equal(siteLink("/faq", {}), "http://localhost/faq");
});

test("R182 floating dock matches the supplied pill layout and leaves the classic dock intact", () => {
  assert.match(storefrontStyles, /\.jluxe-mobile-nav\.jluxe-mobile-nav--floating/);
  assert.match(storefrontStyles, /bottom: calc\(8px \+ env\(safe-area-inset-bottom, 0px\)\)/);
  assert.match(storefrontStyles, /border: 1px solid hsl\(var\(--border\)\)/);
  assert.match(storefrontStyles, /\.jluxe-mobile-nav--floating \.jluxe-mobile-nav-item--floating:focus-visible/);
  assert.match(floatingNavSource, /prefers-reduced-motion: reduce/);
  assert.match(floatingNavSource, /urls\.faq \|\| "\/faq"/);
  assert.match(footerTemplate, /mobile\.nav_variant/);
  assert.match(mobileNavSource, /nav_items \?\? \[\]/);
});

test("mobile category/cart controls keep their normal icons, expose close state, and retain real-page fallbacks", () => {
  assert.match(mobileNavSource, /shop: \{ href: siteUrl\("shop"\), opensDrawer: "categories" \}/);
  assert.match(mobileNavSource, /cart: \{ href: siteUrl\("cart"\), opensDrawer: "cart" \}/);
  assert.match(mobileNavSource, /toggleMobileDrawer\(item\.opensDrawer, window\)/);
  assert.match(floatingNavSource, /toggleMobileDrawer\(item\.opensDrawer, window\)/);
  assert.match(mobileNavSource, /!wasOpen[\s\S]*?jluxeCloseSuggestedProductsModal/);
  assert.match(floatingNavSource, /!wasOpen[\s\S]*?jluxeCloseSuggestedProductsModal/);
  assert.match(mobileNavSource, /aria-expanded": drawerOpen/);
  assert.match(floatingNavSource, /aria-expanded": drawerOpen/);
  assert.match(mobileNavSource, /"aria-current": routeActive \? "page"/);
  assert.match(floatingNavSource, /"aria-current": routeActive \? "page"/);
  assert.match(mobileNavSource, /بستن دسته‌بندی‌ها/);
  assert.match(mobileNavSource, /بستن سبد خرید/);
  assert.match(floatingNavSource, /بستن دسته‌بندی‌ها/);
  assert.match(floatingNavSource, /بستن سبد خرید/);
  assert.match(mobileNavSource, /children: \[\s*jsx\.jsx\(Icon,/);
  assert.match(floatingNavSource, /Icon\s*\?\s*jsx\.jsx\(Icon,/);
  assert.doesNotMatch(mobileNavSource, /MobileDrawerIcon|rotate\(-28deg\)/);
  assert.doesNotMatch(floatingNavSource, /MobileDrawerIcon|rotate\(-28deg\)/);
  assert.match(drawerStateSource, /toggleMobileDrawer/);
  assert.match(drawerStateSource, /registerMobileDrawer/);
  assert.match(drawerStateSource, /jluxe:mobile-drawer-state/);
  assert.match(categoryDrawerSource, /registerMobileDrawer\([\s\S]*?categories/);
  assert.match(categoryDrawerSource, /id: "jluxe-category-drawer-dialog"/);
  assert.match(categoryDrawerSource, /type: "button",\s*onClick: a,\s*"aria-label": "بستن دسته‌بندی‌ها"/);
  assert.match(miniCartSource, /registerMobileDrawer\([\s\S]*?"cart"/);
  assert.match(miniCartSource, /id: "jluxe-cart-drawer-dialog"/);
  assert.match(miniCartSource, /type: "button",\s*onClick: closeDrawer,\s*"aria-label": "بستن سبد خرید"/);
  assert.match(miniCartSource, /useDialog\(n && y, dialogRef, closeDrawer\)/);
  assert.match(categoryDrawerSource, /const exitDelay = window\.matchMedia/);
  assert.match(miniCartSource, /const exitDelay = window\.matchMedia/);
  assert.match(headerIslandSource, /href: siteUrl\("cart"\)/);
  assert.match(headerIslandSource, /event\.metaKey[\s\S]*event\.ctrlKey[\s\S]*event\.shiftKey[\s\S]*event\.altKey/);
  assert.match(headerIslandSource, /event\.preventDefault\(\)[\s\S]*?requestCartDrawerOpen\(window\)/);
  assert.match(headerIslandSource, /window\.jluxeCloseSuggestedProductsModal\(\)/);
  assert.match(miniCartSource, /href: s\.cartUrl[\s\S]*?children: "مشاهده سبد خرید"/);
  assert.match(headerTemplate, /data-jluxe-island="mini-cart"/);
  assert.match(mobileNavSource, /if \(isModifiedClick\(event\)\) return/);
  assert.match(mobileNavSource, /href: item\.href/);
});

test("mobile overlays and inline popovers clear the sticky header and dock", () => {
  const suggestionZ = Number(storefrontStyles.match(/\.jluxe-pa \{ z-index: (\d+); \}/)?.[1]);
  const reviewZ = Number(storefrontStyles.match(/\.jluxe-review-modal-backdrop\s*\{[^}]*z-index:\s*(\d+);/s)?.[1]);
  const variationZ = Number(storefrontStyles.match(/\.jluxe-variant-modal-backdrop\s*\{[^}]*z-index:\s*(\d+);/s)?.[1]);
  const filterZ = Number(storefrontStyles.match(/\.jluxe-filter-panel-overlay\s*\{\s*z-index:\s*(\d+);/)?.[1]);
  const mobileSearchZ = Number(storefrontStyles.match(/\.jluxe-mobile-search-dialog\s*\{\s*z-index:\s*(\d+);/)?.[1]);
  const stockFallbackZ = Number(storefrontStyles.match(/\.jluxe-stock-alert-dialog--fallback\[open\]\s*\{[^}]*z-index:\s*(\d+);/s)?.[1]);
  const skipLinkZ = Number(storefrontStyles.match(/\.jluxe-skip-link\s*\{[^}]*z-index:\s*(\d+);/s)?.[1]);
  const sortZ = Number(storefrontStyles.match(/\.jluxe-sort-listbox\s*\{\s*position: absolute;\s*z-index:\s*(\d+);/)?.[1]);
  const layering = storefrontStyles.slice(storefrontStyles.indexOf("/* Mobile drawers cover"));
  const headerZ = Number(layering.match(/#masthead \.jluxe-header-bar \{\s*z-index: (\d+);/)?.[1]);
  const headerResultsZ = Number(layering.match(/#masthead \.jluxe-header-bar:has\(\.jluxe-header-search-results--mobile\)\s*\{\s*z-index:\s*(\d+);/)?.[1]);
  const dockZ = Number(layering.match(/\.jluxe-mobile-nav\[data-jluxe-mobile-nav-dock\] \{\s*z-index: (\d+);/)?.[1]);
  const drawerZ = Number(drawerStateSource.match(/MOBILE_DRAWER_Z_INDEX = (\d+)/)?.[1]);
  const [assistantOpenZ, assistantIdleZ] = assistantSource
    .match(/zIndex: isMobile && open \? (\d+) : (\d+)/)
    ?.slice(1)
    .map(Number) ?? [];

  assert.equal(headerZ, 130, "the sticky search/logo header keeps its established layer");
  assert.equal(skipLinkZ, 150, "the focused skip link remains visible above the sticky header");
  assert.equal(dockZ, 160, "the mobile dock keeps its established layer");
  assert.equal(sortZ, 165, "the catalog sort menu can be used above the dock");
  assert.equal(headerResultsZ, 165, "mobile header search suggestions can be used above the dock");
  assert.equal(drawerZ, 170, "cart and category drawers cover both sticky controls");
  assert.equal(assistantIdleZ, 90, "the closed/desktop assistant launcher preserves its base layer");
  assert.equal(assistantOpenZ, 180, "the open mobile assistant covers sticky chrome and drawers");
  assert.equal(suggestionZ, 190, "the post-add suggestions sheet covers mobile chrome");
  assert.equal(reviewZ, 200, "the review dialog covers mobile chrome");
  assert.equal(filterZ, 200, "the catalog filter drawer covers mobile chrome");
  assert.equal(variationZ, 210, "the product-card variation picker covers mobile chrome");
  assert.equal(stockFallbackZ, 210, "the non-native stock-alert dialog fallback covers mobile chrome");
  assert.equal(mobileSearchZ, 220, "the full-screen mobile search tops the drawer/assistant/dialog stack");
  assert.ok(headerZ < skipLinkZ && skipLinkZ < dockZ);
  assert.ok(dockZ < sortZ && dockZ < headerResultsZ && sortZ < drawerZ && headerResultsZ < drawerZ);
  assert.ok(dockZ < drawerZ && drawerZ < assistantOpenZ && assistantOpenZ < suggestionZ);
  assert.ok(suggestionZ < reviewZ && reviewZ === filterZ && reviewZ < variationZ && variationZ === stockFallbackZ && stockFallbackZ < mobileSearchZ);
  assert.ok([sortZ, headerResultsZ, drawerZ, assistantOpenZ, suggestionZ, reviewZ, variationZ, filterZ, stockFallbackZ, mobileSearchZ].every((z) => z > dockZ));
  assert.match(categoryDrawerSource, /style: \{ zIndex: MOBILE_DRAWER_Z_INDEX \}/);
  assert.match(miniCartSource, /style: \{ zIndex: MOBILE_DRAWER_Z_INDEX \}/);
  assert.match(headerIslandSource, /jluxe-mobile-search-dialog fixed inset-0/);
  assert.match(shopToolbarSource, /data-jluxe-filter-panel hidden class="fixed inset-0 jluxe-filter-panel-overlay"/);
  assert.match(storefrontStyles, /prefers-reduced-motion: reduce[\s\S]*?\.jluxe-mobile-drawer-panel/);
  assert.match(mobileNavSource, /prefers-reduced-motion: reduce/);
  assert.match(mobileNavSource, /data-jluxe-mobile-nav-dock/);
  assert.match(floatingNavSource, /data-jluxe-mobile-nav-dock/);
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
