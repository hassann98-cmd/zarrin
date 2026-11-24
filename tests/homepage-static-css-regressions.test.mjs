import { readHomepageSource } from "./helpers/theme-source.mjs";
import { readStorefrontCss } from "./helpers/storefront-css.mjs";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import path from "node:path";
import test from "node:test";

const root = process.cwd();
const [php, storefrontCss, routeHomeCss] = await Promise.all([
  readHomepageSource(),
  Promise.resolve(readStorefrontCss()),
  fs.readFile(path.join(root, "src/styles/route-home.css"), "utf8"),
]);
const manifest = JSON.parse(
  await fs.readFile(path.join(root, "assets/compiled/manifest.json"), "utf8"),
);
const mainCss = (
  await Promise.all(
    (manifest["src/main.js"]?.css ?? []).map((file) =>
      fs.readFile(path.join(root, "assets/compiled", file), "utf8"),
    ),
  )
).join("\n");

function section(start, end) {
  const from = php.indexOf(start);
  assert.notEqual(from, -1, `Missing source section: ${start}`);
  const to = php.indexOf(end, from + start.length);
  assert.notEqual(to, -1, `Missing end marker: ${end}`);
  return php.slice(from, to);
}

function compiledUtilitySelector(name) {
  const escaped = name.replace(/([:/])/g, "\\$1");
  assert.ok(
    mainCss.includes(`.${escaped}{`),
    `Tailwind utility .${name} is missing from compiled main CSS`,
  );
}

test("homepage utilities changed for static PHP templates exist in compiled CSS", () => {
  for (const utility of [
    "grid-cols-1",
    "sm:grid-cols-2",
    "lg:grid-cols-3",
    "aspect-video",
    "w-5",
    "via-black/10",
    "border-white/40",
    "shadow-sm",
  ]) {
    compiledUtilitySelector(utility);
  }
});

test("blog uses the restored desktop grid and leaves mobile swiping to authored CSS", () => {
  const blog = section(
    "function jluxe_render_homepage_blog(",
    "function jluxe_render_homepage_trust(",
  );
  assert.match(
    blog,
    /jluxe-home-blog-scroller grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3/,
  );
  assert.match(blog, /jluxe-home-blog-card/);
  assert.match(blog, /class="block aspect-video overflow-hidden bg-muted"/);
  assert.match(blog, /if \( has_post_thumbnail\( \$p \) \)/);
  assert.doesNotMatch(
    blog,
    /(?:^|\s)lg:grid(?:\s|")|w-\[80vw\]|sm:w-\[46vw\]|lg:w-auto|lg:shrink/,
  );
});

test("story viewer positions and translucent surfaces use authored CSS", () => {
  const stories = section(
    "function jluxe_render_homepage_stories(",
    "function jluxe_carousel_autoscroll_attr(",
  );
  for (const rule of [
    ".jluxe-story-viewer{background:rgba(0,0,0,.9)}",
    ".jluxe-story-progress{top:16px;background:rgba(255,255,255,.3)}",
    ".jluxe-story-close{top:32px;z-index:2;min-height:40px;background:rgba(255,255,255,.1)}",
    ".jluxe-story-nav{z-index:2;background:rgba(255,255,255,.16);font-size:24px}",
    ".jluxe-story-cta{bottom:28px;left:50%;white-space:nowrap}",
  ]) {
    assert.ok(stories.includes(rule), `Missing authored story CSS: ${rule}`);
  }
  assert.match(stories, /static \$viewer_style_printed = false/);
  assert.match(stories, /data-jluxe-story-prev[^>]*>›<\/button>/);
  assert.match(stories, /data-jluxe-story-next[^>]*>‹<\/button>/);
  assert.doesNotMatch(
    stories,
    /(?:bg-black\/90|bg-white\/(?:30|15)|top-8|bottom-8|pt-5|text-2xl)/,
  );
});

test("mobile homepage controls resist the global 44px button minimum without losing round tap targets", () => {
  assert.match(php, /\.jluxe-home-scroll-arrow,\.jluxe-banner-slider-arrow\{[^}]*width:40px;height:40px;min-width:40px;min-height:40px/);
  assert.match(php, /\[data-jluxe-bs-dot\]\{[^}]*width:24px;height:24px;min-width:24px;min-height:0/);
  assert.match(php, /\[data-jluxe-bs-dot\]::before\{[^}]*width:8px;height:8px/);
  assert.match(php, /\.jluxe-bvc-mute\{[^}]*width:44px;height:44px;min-width:44px;min-height:44px/);
  assert.match(php, /\.jluxe-story-close\{top:32px;z-index:2;min-height:40px/);
  assert.match(php, /\.jluxe-story-nav\{z-index:2/);
  assert.match(php, /\.jluxe-category-grid-ref-arrow\{top:50%;width:40px;height:40px;min-width:40px;min-height:40px/);
  assert.match(php, /jluxe-banner-slider-arrow absolute end-3/);
  assert.match(php, /jluxe-banner-slider-arrow absolute start-3/);
});

test("mobile search, recommendations and product cards get narrow-screen-safe dimensions", () => {
  assert.match(storefrontCss, /\.jluxe-header-search-results--mobile\s*\{[^}]*position:\s*fixed;[^}]*inset-inline:\s*12px;[^}]*top:\s*calc\(var\(--jluxe-product-header-row-height, 72px\) \+ var\(--jluxe-mobile-banner-offset, 0px\) \+ 6px\);[^}]*width:\s*auto;[^}]*max-width:\s*none;[^}]*left:\s*12px;[^}]*right:\s*12px;[^}]*transform:\s*none;/s);
  assert.match(storefrontCss, /\.jluxe-recommended-panels-scroller > \.jluxe-home-panel\s*\{\s*flex-basis:\s*calc\(100% - 40px\);/);
  assert.match(php, /\$panel_frame_class\s*=\s*\$has_multiple_panels \? ' jluxe-recommended-panels-frame--multiple'/);
  assert.match(storefrontCss, /\.jluxe-recommended-panels-frame--multiple::before\s*\{[^}]*left:\s*0;[^}]*width:\s*18px;[^}]*pointer-events:\s*none;[^}]*linear-gradient\(90deg/s);
  assert.ok(mainCss.includes(".jluxe-recommended-panels-frame--multiple:before") || mainCss.includes(".jluxe-recommended-panels-frame--multiple::before"));
  assert.match(storefrontCss, /\.jluxe-recommended-card-discount-badge\s*\{\s*inset-inline-start:\s*0;\s*inset-inline-end:\s*auto;/);
  assert.match(mainCss, /\.jluxe-recommended-card-discount-badge\{inset-inline:0 auto\}/);
  assert.match(storefrontCss, /\.jluxe-recommended-card-meta\s*\{\s*flex-wrap:\s*wrap;\s*gap:\s*4px;/);
  assert.match(storefrontCss, /\.jluxe-recommended-card-price\s*\{\s*font-size:\s*13px/);
  assert.match(storefrontCss, /\.jluxe-recommended-card-currency\s*\{\s*width:\s*15px;\s*height:\s*12px/);
  assert.match(storefrontCss, /\.jluxe-product-card-price\s*\{\s*flex-direction:\s*column;\s*align-items:\s*flex-start;\s*gap:\s*1px;/);
  assert.match(storefrontCss, /\.jluxe-product-card-current-price\s*\{\s*font-size:\s*13px/);
  assert.match(storefrontCss, /\.jluxe-product-card-cta\s*\{\s*width:\s*40px;\s*min-width:\s*40px;\s*height:\s*40px;\s*min-height:\s*40px;\s*flex-basis:\s*40px;/);
  assert.match(storefrontCss, /@media \(max-width: 639px\)\s*\{\s*\.woocommerce ul\.products li\.product a\.jluxe-product-card-cta,[\s\S]*?max-width:\s*40px;[\s\S]*?max-height:\s*40px;/);
  assert.match(mainCss, /\.jluxe-header-search-results--mobile\{[^}]*position:fixed/);
  assert.match(mainCss, /\.jluxe-product-card-current-price\{font-size:13px\}/);
  assert.match(mainCss, /\.jluxe-recommended-panels-scroller\s*>\s*\.jluxe-home-panel\{\s*flex-basis:\s*calc\(100% - 40px\)/);
});

test("homepage frame widths, transparent showcase images and trust icon alias stay correct", () => {
  assert.doesNotMatch(php, /@media\(max-width:767\.98px\)\{\.jluxe-home-section\{margin-block:12px;padding-block:8px/);
  assert.match(routeHomeCss, /@media \(max-width: 767\.98px\)[\s\S]*body\.home \.jluxe-home-section[\s\S]*margin-block: 1rem; padding-block: \.25rem/);
  assert.match(php, /\.jluxe-brand-campaigns\{[^}]*width:min\(calc\(100% - 32px\),1288px\)[^}]*padding:0 0 24px/);
  assert.match(php, /\.jluxe-brand-strip\{[^}]*width:min\(calc\(100% - 32px\),1288px\)/);
  assert.match(php, /\.jluxe-category-grid-ref\{[^}]*width:min\(calc\(100% - 32px\),1288px\)/);
  assert.match(php, /\.jluxe-brand-campaigns\{width:100%;[^}]*padding:0 0 16px/);
  assert.match(php, /\.jluxe-brand-strip\{width:calc\(100% - 24px\)/);
  assert.match(php, /\.jluxe-bvc-header\{align-items:center;flex-direction:row;gap:8px;padding:12px 12px 0\}/);
  assert.match(php, /\.jluxe-category-grid-ref-head\{padding:0 12px\}/);
  assert.match(php, /\.jluxe-category-grid-ref-scroll\{gap:8px;padding:20px 12px 24px\}/);
  assert.match(php, /\.jluxe-brand-strip\{width:calc\(100% - 24px\);padding-inline:0/);
  assert.match(php, /\.jluxe-category-grid-ref\{[^}]*width:100%;padding-inline:0/);
  assert.match(php, /\.jluxe-category-showcase-image\{[^}]*background:transparent!important/);
  assert.match(php, /\$icon_alias = array\( 'badge-percent' => 'percent' \)/);
  assert.match(php, /\$icon_alias\[ \$icon_name \] \?\? \$icon_name/);
});
