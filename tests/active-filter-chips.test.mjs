import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import { readWooCommerceSource } from "./helpers/theme-source.mjs";
import { readStorefrontCss } from "./helpers/storefront-css.mjs";

const catalog = readWooCommerceSource();
const globalCss = readStorefrontCss();
const filterCss = fs.readFileSync(new URL("../src/styles/catalog-filter-chips.css", import.meta.url), "utf8");
const discoveryCss = fs.readFileSync(new URL("../src/styles/search-discovery.css", import.meta.url), "utf8");
const mainPhp = fs.readFileSync(new URL("../functions.php", import.meta.url), "utf8");
const vite = fs.readFileSync(new URL("../vite.config.js", import.meta.url), "utf8");
const searchIsland = fs.readFileSync(new URL("../src/islands/SearchDiscovery.js", import.meta.url), "utf8");
const mainSource = fs.readFileSync(new URL("../src/main.js", import.meta.url), "utf8");
const softNavigation = fs.readFileSync(new URL("../assets/js/soft-navigation.js", import.meta.url), "utf8");

test("catalog toolbar renders accessible filter links below the toolbar with clear-all only for multiple chips", () => {
  assert.match(catalog, /class="jluxe-active-filters" role="region" aria-label="فیلترهای فعال"/);
  assert.match(catalog, /data-jluxe-catalog-filter-chip aria-label=/);
  assert.match(catalog, /count\( \$active_filter_chips \) > 1/);
  assert.match(catalog, /data-jluxe-catalog-clear aria-label="پاک کردن همهٔ فیلترها"/);
  assert.match(catalog, /remove_query_arg\( array_values\( array_unique\( array_merge\( array\( 'paged', 'page', 'product-page' \)/);
});

test("authored chip CSS is split out of the global initial stylesheet and conditionally loaded", () => {
  for (const selector of [".jluxe-active-filters", ".jluxe-filter-chip", ".jluxe-filter-chip__clear"]) {
    assert.ok(filterCss.includes(selector), `${selector} has authored CSS`);
    assert.ok(!globalCss.includes(selector), `${selector} does not increase the global initial CSS`);
  }
  assert.match(filterCss, /@media \(max-width: 639px\)\s*\{\s*\.jluxe-active-filters\s*\{[^}]*flex-wrap:\s*nowrap;[^}]*overflow-x:\s*auto;/s);
  assert.match(filterCss, /\.jluxe-filter-chip\s*\{[^}]*min-height:\s*44px;/s);
  assert.match(vite, /"catalog-filter-chips": "src\/styles\/catalog-filter-chips\.css"/);
  assert.match(mainPhp, /in_array\( \$context, array\( 'shop', 'search' \), true \)[\s\S]*?jluxe_enqueue_manifest_stylesheet\( 'src\/styles\/catalog-filter-chips\.css'/);
  assert.match(mainPhp, /'src\/styles\/catalog-filter-chips\.css'/);
  assert.match(mainPhp, /src\/islands\/SearchDiscovery\.js'\]\['css'\]/);
});

test("search-discovery CSS is real and fetched with the deferred discovery module on focus", () => {
  for (const selector of [".jluxe-search-discovery", ".jluxe-search-chip", ".jluxe-search-chip__remove"]) {
    assert.ok(discoveryCss.includes(selector), `${selector} has authored CSS`);
    assert.ok(!globalCss.includes(selector), `${selector} does not increase the global initial CSS`);
  }
  assert.match(searchIsland, /import "\.\.\/styles\/search-discovery\.css"/);
  assert.match(discoveryCss, /\.jluxe-search-chip\s*\{[^}]*padding-inline:\s*0\.85rem 0\.25rem;/s);
  assert.match(mainSource, /\["shop", "search"\]\.includes\(assetConfig\.context\)[\s\S]*?import\("\.\/styles\/catalog-filter-chips\.css"\)/);
});

test("soft-navigation recognizes individual filter-chip links for catalog archives", () => {
  assert.match(softNavigation, /anchor\.hasAttribute\("data-jluxe-catalog-filter-chip"\)/);
});
