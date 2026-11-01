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

function luminance(hex) {
  const channels = hex.match(/[0-9a-f]{2}/gi).map((channel) => parseInt(channel, 16) / 255);
  const linear = channels.map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

function contrastRatio(a, b) {
  const [lighter, darker] = [luminance(a), luminance(b)].sort((left, right) => right - left);
  return (lighter + 0.05) / (darker + 0.05);
}

test("R354 the warm home-and-kitchen design is opt-in and visibly distinct from the rejected dark-luxury direction", () => {
  assert.match(css, /--jluxe-home-cream:\s*#FAF5F2/i);
  assert.match(css, /--jluxe-home-sage:\s*#E8F0E8/i);
  assert.match(css, /--jluxe-home-primary:\s*#2F6B52/i);
  assert.match(css, /--jluxe-home-clay:\s*#C2410C/i);
  assert.match(css, /background-color:\s*#FAF7F1/i);
  assert.match(css, /background-color:\s*#2F6B52/i);
  assert.match(css, /background-color:\s*#FFFDF9/i);
  assert.doesNotMatch(css, /#1C1917|#1B1815|#D4AF37|#D97706/i);

  assert.match(settings, /<option value="current"[\s\S]*?>نسخهٔ فعلی<\/option>/);
  assert.equal((settings.match(/بازطراحی خانه و آشپزخانه — طبیعی \(اختیاری\)/g) ?? []).length, 3);
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

test("R354 warm primary and terracotta action colors meet WCAG AA for normal white button text", () => {
  assert.ok(contrastRatio("#2F6B52", "#FFFFFF") >= 4.5);
  assert.ok(contrastRatio("#C2410C", "#FFFFFF") >= 4.5);
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

test("R355 site-wide Canva-inspired design stays opt-in and covers the storefront without replacing content or grids", () => {
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
  assert.match(sitewideCss, /--primary:\s*155 39% 30%/);
  assert.match(sitewideCss, /--accent:\s*18 88% 40%/);
  assert.match(sitewideCss, /#2F6B52/);
  assert.match(sitewideCss, /#C2410C/);
  assert.match(sitewideCss, /#FFF9F0/);
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
  assert.ok(contrastRatio("#2F6B52", "#FFFFFF") >= 4.5);
  assert.ok(contrastRatio("#C2410C", "#FFFFFF") >= 4.5);
});
