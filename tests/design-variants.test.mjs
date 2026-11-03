import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

const css = readFileSync(new URL("../src/styles/design-variants.css", import.meta.url), "utf8");
const settings = readFileSync(new URL("../inc/theme-settings-render.php", import.meta.url), "utf8");
const storefrontCss = readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");
const sitewideCss = readFileSync(new URL("../src/styles/sitewide-design.css", import.meta.url), "utf8");
const settingsPhp = readFileSync(new URL("../inc/theme-settings.php", import.meta.url), "utf8");
const sanitizePhp = readFileSync(new URL("../inc/theme-settings-sanitize.php", import.meta.url), "utf8");
const adminPhp = readFileSync(new URL("../inc/theme-settings-admin.php", import.meta.url), "utf8");
const mainJs = readFileSync(new URL("../src/main.js", import.meta.url), "utf8");
const aboutPage = readFileSync(new URL("../page-about-us.php", import.meta.url), "utf8");

function luminance(hex) {
  const channels = hex.match(/[0-9a-f]{2}/gi).map((channel) => parseInt(channel, 16) / 255);
  const linear = channels.map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

function contrastRatio(a, b) {
  const [lighter, darker] = [luminance(a), luminance(b)].sort((left, right) => right - left);
  return (lighter + 0.05) / (darker + 0.05);
}

test("R354 the optional home-and-kitchen variant uses the requested Electric Indigo palette without changing the default", () => {
  assert.match(css, /--jluxe-home-primary:\s*var\(--color-electric-indigo-500\)/i);
  assert.match(css, /--jluxe-home-clay:\s*var\(--color-electric-indigo-400\)/i);
  assert.match(css, /--jluxe-home-cream:\s*var\(--color-electric-indigo-50\)/i);
  assert.match(css, /background-color:\s*var\(--color-electric-indigo-500\)/i);
  assert.match(css, /background-color:\s*#FFFFFF/i);
  assert.doesNotMatch(css, /#2F6B52|#FAF7F1|#FFFDF9/i);

  assert.match(settings, /<option value="current"[\s\S]*?>نسخهٔ فعلی<\/option>/);
  assert.equal((settings.match(/خانه و آشپزخانه — نیلی الکتریکی \(اختیاری\)/g) ?? []).length, 4);
  assert.match(settings, /نسخهٔ فعلی پیش‌فرض می‌ماند/);
  assert.match(settings, /رفتار WooCommerce حفظ می‌شود/);
  assert.match(settings, /SEO/);
});

test("R354 footer gets distinct feature, brand, link, and trust modules without overriding responsive grid placement", () => {
  const footerStart = css.indexOf("/* Footer:");
  const footerEnd = css.indexOf("/* Product cards:", footerStart);
  assert.ok(footerStart >= 0 && footerEnd > footerStart, "footer variant block exists");
  const footer = css.slice(footerStart, footerEnd);
  assert.match(footer, /\.jluxe-footer-feature-card/);
  assert.match(footer, /\.jluxe-footer-brand-column/);
  assert.match(footer, /\.jluxe-footer-link-column/);
  assert.match(footer, /\.jluxe-site-badges-section/);
  assert.doesNotMatch(footer, /grid-template-columns\s*:|grid-column\s*:|display\s*:\s*contents/i);
  const baseFooterStart = storefrontCss.indexOf("/* R191");
  const baseFooterEnd = storefrontCss.indexOf("/* R211", baseFooterStart);
  const baseFooter = storefrontCss.slice(baseFooterStart, baseFooterEnd);
  assert.ok(baseFooterStart >= 0 && baseFooterEnd > baseFooterStart);
  assert.ok(baseFooter.includes("@media (min-width: 640px)"));
  assert.ok(baseFooter.includes("grid-template-columns: repeat(2, minmax(0, 1fr));"));
  assert.ok(baseFooter.includes("@media (min-width: 1024px)"));
  assert.ok(baseFooter.includes("grid-template-columns: minmax(0, 1.15fr)"));
});

test("R354 Electric Indigo primary and action colors meet WCAG AA for normal white button text", () => {
  assert.ok(contrastRatio("#5100FF", "#FFFFFF") >= 4.5);
  assert.ok(contrastRatio("#7433FF", "#FFFFFF") >= 4.5);
  assert.ok(contrastRatio("#4100CC", "#FFFFFF") >= 4.5);
});

test("R354 optional effects stay scoped and include focus, low-power, and reduced-motion fallbacks", () => {
  assert.match(css, /#masthead\[data-jluxe-header-variant="premium"\]/);
  assert.match(css, /#jluxe-footer-root\[data-jluxe-footer-variant="premium"\]/);
  assert.match(css, /ul\.products li\.product\[data-jluxe-product-card-variant="premium"\]/);
  assert.match(css, /#masthead\[data-jluxe-header-variant="premium"\] \.jluxe-header-bar[\s\S]*?backdrop-filter:\s*none/);
  assert.match(css, /html\.jluxe-lite ul\.products li\.product\[data-jluxe-product-card-variant="premium"\]/);
  assert.doesNotMatch(css, /backdrop-filter:\s*blur/);
  assert.match(css, /:focus-visible/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
});

test("R355 site-wide Electric Indigo design stays opt-in and covers the storefront without replacing content or grids", () => {
  assert.match(settingsPhp, /'visual_design'\s*=>\s*array\(\s*'variant'\s*=>\s*'current'/);
  assert.match(settingsPhp, /function jluxe_visual_design_body_classes/);
  assert.match(settingsPhp, /jluxe-design-canva-home/);
  assert.match(sanitizePhp, /function jluxe_sanitize_visual_design[\s\S]*?'current', 'canva_home'/);
  assert.match(settings, /name="visual_design\[variant\]"/);
  assert.match(settings, /value="canva_home"/);
  assert.match(settings, /انتخاب‌های جداگانهٔ هدر\/فوتر\/کارت محصول هم باقی می‌مانند/);
  assert.match(adminPhp, /'jluxe-visual-design'\s*=>\s*array\(\s*'visual_design', 'jluxe_sanitize_visual_design'\s*\)/);
  assert.match(adminPhp, /isset\( \$_POST\['visual_design'\] \)/);
  assert.match(mainJs, /\.\/styles\/sitewide-design\.css/);

  assert.match(sitewideCss, /body\.jluxe-design-canva-home/);
  assert.match(sitewideCss, /--primary:\s*259\.06 100% 50%/);
  assert.match(sitewideCss, /--accent:\s*259\.12 100% 60%/);
  assert.match(sitewideCss, /var\(--color-electric-indigo-500\)/);
  assert.match(sitewideCss, /var\(--color-electric-indigo-50\)/);
  assert.doesNotMatch(sitewideCss, /#2F6B52|#FFF9F0/i);
  assert.match(sitewideCss, /\.jluxe-home-section/);
  assert.match(sitewideCss, /padding-inline:\s*clamp\(12px, 2vw, 24px\)/);
  assert.match(sitewideCss, /\.jluxe-home-section:nth-of-type\(4n \+ 2\)/);
  assert.match(sitewideCss, /\.jluxe-home-section > div\.mb-3\.flex\.items-center\.justify-between > a/);
  assert.match(sitewideCss, /#masthead/);
  assert.match(sitewideCss, /#jluxe-footer-root/);
  assert.match(sitewideCss, /ul\.products li\.product/);
  assert.match(sitewideCss, /\.woocommerce-checkout/);
  assert.match(sitewideCss, /@media \(max-width: 767\.98px\)/);
  assert.match(sitewideCss, /@media \(prefers-reduced-motion: reduce\)/);
  assert.doesNotMatch(sitewideCss, /grid-template-columns\s*:|grid-column\s*:|display\s*:\s*contents/i);
  assert.ok(contrastRatio("#5100FF", "#FFFFFF") >= 4.5);
  assert.ok(contrastRatio("#7433FF", "#FFFFFF") >= 4.5);
});

test("R362 ordinary text is neutral while action, hover, and semantic status colors remain distinct", () => {
  assert.match(storefrontCss, /--foreground:\s*215 28% 17%/);
  assert.match(storefrontCss, /--surface-foreground:\s*215 28% 17%/);
  assert.match(storefrontCss, /--text-secondary:\s*215 19% 35%/);
  assert.match(storefrontCss, /--text-muted:\s*215 19% 35%/);
  assert.match(storefrontCss, /--success:\s*156\.43 71\.79% 30\.59%/);
  assert.match(storefrontCss, /--warning:\s*17\.47 88\.35% 40\.39%/);
  assert.match(storefrontCss, /--error:\s*0 72\.22% 50\.59%/);
  assert.match(storefrontCss, /--info:\s*221\.21 83\.19% 53\.33%/);
  assert.match(storefrontCss, /\.text-gray-600 \{\s*--tw-text-opacity: 1;\s*color: rgb\(71 85 105/);
  assert.match(storefrontCss, /\.dark\\:text-gray-300:is\(\.dark \*\) \{\s*--tw-text-opacity: 1;\s*color: rgb\(209 213 219/);
  assert.match(storefrontCss, /\.dark \.jluxe-site-faq__heading h1,[\s\S]*?color: #F3F4F6/);
  assert.match(storefrontCss, /\.dark \.jluxe-site-faq__empty \{ color: #D1D5DB; \}/);
  assert.match(sitewideCss, /--jluxe-home-ink:\s*#1F2937/);
  assert.match(sitewideCss, /\.jluxe-home-section h2:not\(\.text-white\) \{\s*color: #1F2937;/);
  assert.match(sitewideCss, /\.jluxe-product-card-title \{\s*color: #1F2937;/);
  assert.match(css, /\.jluxe-product-card-title \{\s*color: #1F2937;/);
  assert.match(css, /\.jluxe-product-card-current-price \{\s*color: #1F2937;/);
  assert.match(storefrontCss, /\.jluxe-blog-listing__eyebrow \{[\s\S]*?color: hsl\(var\(--text-secondary\)\)/);
  assert.match(storefrontCss, /\.jluxe-pa-price \{[\s\S]*?color: hsl\(var\(--foreground\)\)/);
  assert.match(storefrontCss, /\.jluxe-recent-products__count \{[\s\S]*?color: hsl\(var\(--text-secondary\)\)/);
  assert.match(storefrontCss, /\.jluxe-recent-products__eyebrow \{[\s\S]*?color: hsl\(var\(--text-secondary\)\)/);
  assert.match(storefrontCss, /\.jluxe-recent-card__price\[data-on-sale="true"\] \.jluxe-recent-card__price-current \{ color: hsl\(var\(--error\)\)/);
  assert.match(css, /\.jluxe-product-card-sale-badge \{[\s\S]*?background-color: #DC2626/);
  assert.match(sitewideCss, /\.woocommerce-message \{[\s\S]*?color: #166534/);
  assert.match(sitewideCss, /\.woocommerce-info \{[\s\S]*?color: #1D4ED8/);
  assert.match(sitewideCss, /\.woocommerce-error \{[\s\S]*?color: #B91C1C/);
  assert.match(sitewideCss, /nav > a:hover,[\s\S]*?color: var\(--color-electric-indigo-400\)/, "interactive hover color stays Electric Indigo");
  assert.equal((aboutPage.match(/\[&_\.jluxe-about-eyebrow\]:text-text-secondary/g) ?? []).length, 2);
  assert.doesNotMatch(aboutPage, /\[&_\.jluxe-about-eyebrow\]:text-primary/);
  assert.doesNotMatch(sitewideCss, /color:\s*var\(--color-electric-indigo-(?:700|800|900)\)/);
});
