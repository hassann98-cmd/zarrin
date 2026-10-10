import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);
const [
  headerTemplate,
  headerIsland,
  storefrontStyles,
  settingsDefaults,
  settingsAdmin,
  settingsSanitize,
  classicProductTemplate,
  mainEntry,
] = await Promise.all([
  readFile(new URL("header.php", root), "utf8"),
  readFile(new URL("src/islands/Header.js", root), "utf8"),
  readFile(new URL("src/styles/storefront.css", root), "utf8"),
  readFile(new URL("inc/theme-settings.php", root), "utf8"),
  readFile(new URL("inc/theme-settings-render.php", root), "utf8"),
  readFile(new URL("inc/theme-settings-sanitize.php", root), "utf8"),
  readFile(new URL("woocommerce/content-single-product-classic.php", root), "utf8"),
  readFile(new URL("src/main.js", root), "utf8"),
]);

test("R125 header DOM and keyboard order match each breakpoint's visual order", () => {
  const mobileSearch = headerTemplate.indexOf('data-jluxe-island="header-mobile-search"');
  const logo = headerTemplate.indexOf('data-jluxe-island="header-logo"');
  const actions = headerTemplate.indexOf('data-jluxe-island="header-actions"');
  const rowEnd = headerTemplate.indexOf('\n\t\t</div>\n\t\t<div data-jluxe-island="mini-cart"');
  const miniCart = headerTemplate.indexOf('data-jluxe-island="mini-cart"');
  const categoryDrawer = headerTemplate.indexOf('data-jluxe-island="category-drawer"');

  assert.ok(mobileSearch >= 0 && mobileSearch < logo && logo < actions, "mobile search precedes the logo and action controls");
  assert.match(headerTemplate, /class="md:hidden" data-jluxe-island="header-mobile-search"/);
  assert.match(mainEntry, /"header-mobile-search": \(\) =>[\s\S]*?MobileHeaderSearch/);
  assert.ok(rowEnd > actions && rowEnd < miniCart, "portal-only islands stay outside the grid row");
  assert.ok(miniCart < categoryDrawer, "both overlay islands remain mounted");

  const desktopSearch = headerIsland.indexOf('"data-jluxe-header-grid": "search"');
  const account = headerIsland.indexOf('"data-jluxe-header-grid": "account"');
  const cart = headerIsland.indexOf('"data-jluxe-header-grid": "cart"');
  assert.ok(desktopSearch >= 0 && desktopSearch < account && account < cart, "desktop order remains logo, search, account, cart");
});

test("R123 RTL header keeps the accepted order and lets search shrink around fixed controls", () => {
  const layout = storefrontStyles.slice(storefrontStyles.indexOf("/* R123 —"));

  assert.match(layout, /direction:\s*rtl/);
  assert.match(layout, /grid-template-areas:\s*"logo search spacer account cart"/);
  assert.match(layout, /data-jluxe-island="header-actions"\],\s*#masthead \.jluxe-header-row > \[data-jluxe-island="header-actions"\] > \.jluxe-header-actions-layout\s*\{\s*display:\s*contents/);
  assert.match(headerIsland, /className: "jluxe-header-actions-layout"/);
  assert.match(layout, /data-jluxe-header-grid="logo"\]\s*\{\s*grid-area:\s*logo/);
  assert.match(layout, /data-jluxe-header-grid="search"\]\s*\{\s*grid-area:\s*search/);
  assert.match(layout, /data-jluxe-header-grid="cart"\]\s*\{\s*grid-area:\s*cart/);
  assert.match(layout, /data-jluxe-header-grid="account"\]\s*\{\s*grid-area:\s*account/);
  assert.match(layout, /grid-template-columns:\s*max-content minmax\(0, clamp\(220px, 37vw, 440px\)\) minmax\(0, 1fr\) max-content max-content/);
  assert.match(layout, /max-width:\s*clamp\(72px, 16vw, 200px\)/);
  assert.match(layout, /max-width:\s*clamp\(56px, 24vw, 104px\)/);
});

test("R123 lets search absorb the narrow md-grid deficit instead of widening the page", () => {
  const viewport = 768;
  const rowContent = viewport - 15 - 32; // Representative stable root gutter + header horizontal padding.
  const logoAtTabletCap = Math.max(92, Math.min(144, viewport * 0.12));
  const preferredSearch = Math.max(220, Math.min(440, viewport * 0.37));
  const fixedControlsAndGaps = 151 + 171 + 4 * 8;
  const availableSearch = rowContent - logoAtTabletCap - fixedControlsAndGaps;

  // If an uploaded logo fills its 92px tablet cap, the old fixed search track overshot this row by 9.32px.
  assert.ok(availableSearch >= 220 && availableSearch < preferredSearch);
  assert.equal(Number((preferredSearch - availableSearch).toFixed(2)), 9.32);
  assert.match(storefrontStyles, /grid-template-columns:\s*max-content minmax\(0, clamp\(220px, 37vw, 440px\)\)/);
});

test("R122 keeps the existing desktop search, cart, account controls and responsive sizes", () => {
  assert.match(headerIsland, /className: "relative h-10 w-full min-w-0 md:hidden"/);
  assert.match(headerIsland, /className: "relative hidden w-full min-w-0 md:block"/);
  assert.match(headerIsland, /md:w-\[171px\]/);
  assert.match(headerIsland, /md:w-\[151px\]/);
  assert.match(headerIsland, /hidden text-button md:inline/);
  assert.match(headerIsland, /jluxe:open-cart/);
  assert.match(headerIsland, /siteUrl\(y \? "dashboard" : "login"\)/);
  assert.match(headerIsland, /onFocus: \(\) => d\(!0\)/);
});

test("R211 widens the desktop search and modestly enlarges the logo without changing account/cart controls", () => {
  assert.match(storefrontStyles, /@media \(min-width: 1200px\)\s*\{\s*#masthead \.jluxe-header-row\s*\{\s*grid-template-columns: max-content minmax\(0, clamp\(460px, 34vw, 500px\)\) minmax\(0, 1fr\) max-content max-content/);
  assert.match(storefrontStyles, /@media \(min-width: 1200px\) and \(max-width: 1279\.98px\)[\s\S]*?max-width: 160px;\s*--jluxe-header-logo-height: 4\.25rem/);
  assert.match(storefrontStyles, /@media \(min-width: 1280px\)[\s\S]*?max-width: 220px;\s*--jluxe-header-logo-height: 4\.5rem/);
  assert.match(storefrontStyles, /\[data-jluxe-island="header-logo"\] img\s*\{\s*height: var\(--jluxe-header-logo-height, 4rem\) !important/);
  assert.match(headerIsland, /md:w-\[151px\]/);
  assert.match(headerIsland, /md:w-\[171px\]/);
});

test("R212 shared desktop containers never grow past 1462px, including product-width utilities", () => {
  const cap = storefrontStyles.slice(storefrontStyles.indexOf("/* R120/R212: keep desktop page shells fluid"));
  assert.match(cap, /--jluxe-container-max:\s*1462px/);
  assert.match(cap, /\[class~="max-w-\[1320px\]"\]/);
  assert.match(cap, /max-width:\s*min\(var\(--jluxe-container-max\), 1462px\)/);
  assert.doesNotMatch(cap, /--jluxe-container-max:\s*(?:1600|1760|2048|2304)px/);
  assert.match(classicProductTemplate, /class="mx-auto w-full max-w-\[1320px\] px-3 md:px-4 py-2"/);
});

test("R126 places the editable mobile search beside and before the logo in the same row", () => {
  assert.match(headerIsland, /function MobileSearch\(\)/);
  assert.match(headerIsland, /className:\s*"h-10 w-full rounded-xl border border-border bg-surface/);
  assert.match(headerIsland, /placeholder: searchPlaceholder/);
  assert.match(headerIsland, /search_placeholder/);
  assert.match(headerTemplate, /data-jluxe-island="header-mobile-search" data-jluxe-header-grid="search"/);
  assert.match(headerTemplate, /jluxe-header-row--has-mobile-search/);
  assert.match(headerTemplate, /jluxe-header-spacer--has-mobile-search/);
  assert.match(settingsDefaults, /'search_placeholder'\s*=>\s*'جستجو در فروشگاه'/);
  assert.match(settingsAdmin, /name="header\[search_placeholder\]"/);
  assert.match(settingsSanitize, /sanitize_text_field\( \(string\) \$posted\['search_placeholder'\] \)/);

  const mobileHeader = storefrontStyles.slice(storefrontStyles.indexOf("/* R126 —"));
  assert.match(mobileHeader, /grid-template-rows: 72px/);
  assert.match(mobileHeader, /grid-template-areas: "search logo account cart"/);
  assert.match(mobileHeader, /column-gap: 0\.375rem/);
  assert.match(mobileHeader, /jluxe-header-spacer--has-mobile-search\s*\{\s*height: 72px/);
  assert.match(mobileHeader, /--jluxe-product-header-row-height: 72px/);
  assert.doesNotMatch(mobileHeader, /grid-template-areas: "search search search"/);

  // At 320px, the same-row search still has usable width without shrinking the controls.
  const rowContent = 320 - 32;
  const logo = Math.max(56, Math.min(104, 320 * 0.24));
  const account = 44;
  const cart = 70;
  const gaps = 3 * 6;
  assert.ok(rowContent - logo - account - cart - gaps >= 72);
});

test("live search keeps category, brand and product suggestions grouped and surfaces spelling corrections", () => {
  const categories = headerIsland.indexOf("r.categories.map");
  const brands = headerIsland.indexOf("r.brands.map");
  const products = headerIsland.indexOf("r.products.map");

  assert.ok(categories >= 0 && categories < brands && brands < products);
  assert.match(headerIsland, /setTimeout\(\(\) => \{[\s\S]*?\}, 300\)/);
  assert.match(headerIsland, /fetchLiveSearch\(i, m\)/);
  assert.match(headerIsland, /r\.correctedQuery/);
  assert.match(headerIsland, /املای جستجو از/);
  assert.match(headerIsland, /displayQuery/);
});

test("R152 Enter submits a full product search from desktop, mobile header, and mobile dialog", () => {
  assert.equal((headerIsland.match(/action: siteUrl\("home"\)/g) ?? []).length, 3);
  assert.equal((headerIsland.match(/method: "get"/g) ?? []).length, 3);
  assert.equal((headerIsland.match(/name: "post_type", value: "product"/g) ?? []).length, 3);
  assert.equal((headerIsland.match(/type: "search"/g) ?? []).length, 3);
  assert.doesNotMatch(headerIsland, /onSubmit: \(?(?:event|a)\)? ?=> ?[^\n]*preventDefault/);
  assert.match(headerIsland, /enterKeyHint: "search"/);
});

test("R152 empty live-search state offers spelling guidance, full results, and the configured categories destination", () => {
  assert.match(headerIsland, /categoryUrl = \(settings\.megaMenu && settings\.megaMenu\.url\) \|\| siteUrl\("shop"\)/);
  assert.match(headerIsland, /املای عبارت را بررسی کنید، جستجو را کوتاه‌تر کنید/);
  assert.match(headerIsland, /نام یک برند\/دسته‌بندی را امتحان کنید/);
  assert.match(headerIsland, /مرور دسته‌بندی‌ها/);
  assert.match(headerIsland, /جستجوی کامل برای/);
  assert.match(headerIsland, /href: r\.viewAllUrl/);
});

test("R124 sticky banner offsets move the fixed header on a compositor transform", () => {
  assert.match(
    storefrontStyles,
    /\.jluxe-header-bar-sticky\s*\{\s*top: var\(--wp-admin--admin-bar--height, 0px\);\s*transform: translate3d\(0, var\(--jluxe-announcement-sticky-height, 0px\), 0\);/,
  );
  assert.match(storefrontStyles, /#masthead \.jluxe-header-bar-sticky\s*\{\s*will-change: transform/);
});

test("R124 product breadcrumbs wrap and break unusually long category/product labels", () => {
  assert.match(classicProductTemplate, /\.cp3-bc\{display:flex;flex-wrap:wrap/);
  assert.match(classicProductTemplate, /overflow-wrap:anywhere/);
  assert.match(classicProductTemplate, /\.cp3-bc \.is-current\{[^}]*flex-basis:100%;font-size:12px/s);
  assert.match(classicProductTemplate, /\.cp3-bc\{gap:5px 7px;padding-top:10px;font-size:11\.5px\}/);
});

test("R169 header/footer brand logo uses one responsive picture and keeps LiteSpeed's no-lazy guard", () => {
  const start = headerIsland.indexOf("function K({ className: l })");
  const end = headerIsland.indexOf("export {", start);
  const brandLogo = headerIsland.slice(start, end);

  assert.match(brandLogo, /r\.logoImage/);
  assert.match(brandLogo, /r\.mobileLogoImage/);
  assert.match(brandLogo, /media: "\(min-width: 768px\)"/);
  assert.match(brandLogo, /srcSet: a\.srcset \|\| n \|\| o/);
  assert.match(brandLogo, /srcSet: m\.srcset,/);
  assert.match(brandLogo, /"data-no-lazy": "1"/);
  assert.equal((brandLogo.match(/e\.jsx\("img"/g) ?? []).length, 1);
});
