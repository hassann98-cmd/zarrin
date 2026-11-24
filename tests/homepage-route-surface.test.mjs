import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { execFileSync } from "node:child_process";

const read = (path) => fs.readFileSync(new URL(`../${path}`, import.meta.url), "utf8");

test("R403 homepage CSS is a standalone route entry and only enqueued for home", () => {
  const vite = read("vite.config.js");
  const php = read("functions.php");
  const main = read("src/main.js");
  const checker = read("scripts/check-assets.mjs");
  assert.match(vite, /"route-home":\s*"src\/styles\/route-home\.css"/);
  assert.match(php, /'home'\s*=>\s*array\( 'src\/styles\/route-home\.css', 'jluxe-route-home' \)/);
  assert.ok(php.includes("$route_styles[ $context ][0]"));
  assert.match(main, /assetConfig\.context === "home"[\s\S]*import\("\.\/styles\/route-home\.css"\)/);
  assert.match(checker, /"src\/styles\/route-home\.css"/);
  assert.doesNotMatch(read("src/styles/storefront.css"), /jluxe-home-section-heading/);
});

test("R403 homepage surfaces use constrained tone tokens and the route stylesheet", () => {
  const css = read("src/styles/route-home.css");
  const sanitize = read("inc/theme-settings-sanitize.php");
  const editor = read("inc/homepage/admin-editor.php");
  const renderer = read("inc/theme-settings-homepage.php");
  assert.ok(sanitize.includes("$raw['surface_tone'] ?? '', array( 'transparent', 'soft', 'card', 'tinted' ), true"));
  assert.ok(sanitize.includes("? $raw['surface_tone'] : 'transparent'"));
  assert.match(editor, /'surface_tone'.*'شفاف'.*'ملایم'.*'کارت برجسته'.*'رنگی لطیف'/);
  assert.match(renderer, /data-surface-tone=/);
  for (const tone of ["transparent", "soft", "card", "tinted"]) assert.ok(css.includes(`data-surface-tone="${tone}"`));
});

test("R403 adds three fixed luxury presets and keeps product action markup untouched", () => {
  const presets = read("inc/theme-settings-presets.php");
  for (const id of ["atelier_noir", "pearl_sage", "royal_sapphire"]) assert.ok(presets.includes(`'${id}' => array(`));
  const product = read("woocommerce/content-product.php");
  assert.match(product, /ajax_add_to_cart add_to_cart_button/);
  assert.match(product, /product_type_.*variable/);
  assert.match(read("src/styles/storefront.css"), /R405 product-card contrast/);
});

test("R404 inverse section actions stay white-glass and readable over brand bands", () => {
  const products = read("inc/homepage/sections-products.php");
  const css = read("src/styles/route-home.css");
  assert.match(products, /\$inverse \? ' jluxe-home-section-view-all--inverse' : ''/);
  assert.match(products, /class="jluxe-home-section-view-all<\?php echo esc_attr\( \$inverse_class \); \?>/);
  const inverseRule = css.match(/\.jluxe-home-section-heading \.jluxe-home-section-view-all--inverse\s*\{([^}]+)\}/)?.[1] ?? "";
  assert.match(inverseRule, /background:\s*rgb\(255 255 255 \/ .16\)/);
  assert.match(inverseRule, /color:\s*#fff/);
  assert.doesNotMatch(inverseRule, /background:\s*hsl\(var\(--surface\)\)/);
  assert.match(css, /\.jluxe-home-section-heading \.jluxe-home-section-view-all--inverse:hover[\s\S]*background:\s*rgb\(255 255 255 \/ .26\)/);
});

test("R404 renders the supported soft surface wrapper without requiring design_enabled", () => {
  const homepage = read("inc/theme-settings-homepage.php");
  const css = read("src/styles/route-home.css");
  assert.match(homepage, /if \( empty\( \$section\['design_enabled'\] \) \)[\s\S]*'transparent' === \$d\['surface_tone'\][\s\S]*jluxe-home-tone-wrapper/);
  assert.match(homepage, /class="jluxe-designed-section jluxe-home-tone-wrapper" data-surface-tone=/);
  assert.match(css, /\.jluxe-designed-section\[data-surface-tone="soft"\][\s\S]*background:\s*color-mix\(in srgb, hsl\(var\(--background\)\) 93%, hsl\(var\(--foreground\)\) 7%\)/);
});

test("R404 scopes generic order-pay method frames away from custom payment cards", () => {
  const css = read("src/styles/route-cart-checkout.css");
  assert.match(css, /body\.woocommerce-order-pay #payment ul\.payment_methods > li:not\(\.jluxe-payment-method-item\)\s*\{/);
  assert.match(css, /body\.woocommerce-order-pay #payment ul\.payment_methods > li:not\(\.jluxe-payment-method-item\) > label\s*\{/);
});

test("R404 real homepage renderers and product cards expose the requested presentation hooks", () => {
  const product = read("woocommerce/content-product.php");
  const sections = read("inc/homepage/sections-products.php");
  const catalog = read("inc/homepage/sections-catalog.php");
  assert.match(product, /wc_product_class\( 'jluxe-product-card group relative flex flex-col/);
  assert.match(product, /border border-border bg-surface transition-all/);
  assert.doesNotMatch(product, /bg-gradient-to-b from-surface to-muted\/40/);
  assert.match(sections, /class="jluxe-home-special-band"/);
  assert.match(catalog, /class="jluxe-home-trust-card/);
});

test("R405 keeps homepage reveal off the hero and initially visible sections", async () => {
  const { JSDOM } = await import("jsdom");
  const script = read("assets/js/homepage.js");
  const createPage = ({ reduced = false, lite = false } = {}) => {
    const dom = new JSDOM(`<!doctype html><html class="${lite ? "jluxe-lite" : ""}"><body>
      <section id="first" class="jluxe-home-section"></section>
      <section id="hero" class="jluxe-home-section"><div class="jluxe-hero"></div></section>
      <section id="visible" class="jluxe-home-section"></section>
      <section id="below" class="jluxe-home-section"></section>
      <section id="hero-child" class="jluxe-home-section"><div class="jluxe-hero"></div></section>
      <section id="last" class="jluxe-home-section"></section>
    </body></html>`, { runScripts: "outside-only" });
    Object.defineProperty(dom.window, "innerHeight", { value: 800, configurable: true });
    dom.window.matchMedia = (query) => ({ matches: reduced && query.includes("prefers-reduced-motion"), media: query });
    const positions = { first: [0, 360], hero: [360, 760], visible: [740, 920], below: [900, 1150], "hero-child": [1200, 1450], last: [1500, 1800] };
    Object.entries(positions).forEach(([id, [top, bottom]]) => {
      dom.window.document.getElementById(id).getBoundingClientRect = () => ({ top, bottom, left: 0, right: 400, width: 400, height: bottom - top });
    });
    const observers = [];
    dom.window.IntersectionObserver = class {
      constructor(callback, options) { this.callback = callback; this.options = options; this.targets = []; this.unobserved = []; observers.push(this); }
      observe(target) { this.targets.push(target); }
      unobserve(target) { this.unobserved.push(target); }
    };
    dom.window.eval(script);
    dom.window.document.dispatchEvent(new dom.window.Event("DOMContentLoaded"));
    return { dom, observers };
  };

  const { dom, observers } = createPage();
  const observedIds = observers[0].targets.map((el) => el.id);
  assert.deepEqual(observedIds, ["below", "last"]);
  assert.equal(observers[0].options.threshold, 0.08);
  assert.ok(dom.window.document.getElementById("below").classList.contains("jluxe-home-reveal"));
  assert.ok(!dom.window.document.getElementById("hero").classList.contains("jluxe-home-reveal"));
  assert.ok(!dom.window.document.getElementById("hero-child").classList.contains("jluxe-home-reveal"));
  observers[0].callback([{ target: dom.window.document.getElementById("below"), isIntersecting: true }]);
  assert.ok(dom.window.document.getElementById("below").classList.contains("is-revealed"));
  assert.deepEqual(observers[0].unobserved.map((el) => el.id), ["below"]);
  dom.window.close();

  for (const options of [{ reduced: true }, { lite: true }]) {
    const disabled = createPage(options);
    assert.equal(disabled.observers.length, 0, `reveal observer disabled for ${JSON.stringify(options)}`);
    assert.ok(!disabled.dom.window.document.getElementById("below").classList.contains("jluxe-home-reveal"));
    disabled.dom.window.close();
  }
});

test("R405 homepage motion stays route-isolated, respects motion preferences, and barely changes main CSS", () => {
  const css = read("src/styles/route-home.css");
  const js = read("assets/js/homepage.js");
  assert.match(read("inc/theme-settings-homepage.php"), /\.jluxe-home-section\{[^}]*margin-inline:auto/);
  assert.match(js, /IntersectionObserver[\s\S]*?threshold: 0\.08/);
  assert.match(js, /section\.matches\("\.jluxe-hero"\) \|\| section\.closest\("\.jluxe-hero"\) \|\| section\.querySelector\("\.jluxe-hero"\)/);
  assert.match(js, /prefers-reduced-motion: reduce[\s\S]*?jluxe-lite/);
  assert.match(js, /function initCarouselPeekHint\(\)\s*\{\s*if \(document\.documentElement\.classList\.contains\("jluxe-lite"\)\) return;\s*if \(window\.matchMedia[\s\S]*?prefers-reduced-motion: reduce/);
  assert.match(css, /\.jluxe-home-reveal\s*\{\s*opacity: 0; transform: translateY\(18px\);/);
  assert.match(css, /\.jluxe-home-reveal\.is-revealed\s*\{\s*opacity: 1; transform: translateY\(0\); transition: opacity \.52s[\s\S]*transform \.58s/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)\s*\{[\s\S]*?\.jluxe-home-reveal \{ opacity: 1 !important/);
  assert.match(css, /html\.jluxe-lite \.jluxe-home-reveal \{ opacity: 1 !important/);
  const manifest = JSON.parse(read("assets/compiled/manifest.json"));
  const mainPath = manifest["src/main.js"].css.find((name) => name.startsWith("assets/main-"));
  const current = fs.readFileSync(new URL(`../assets/compiled/${mainPath}`, import.meta.url));
  assert.doesNotMatch(current.toString("utf8"), /jluxe-home-reveal|jluxe-story-ring|jluxe-home-special-band/);
  const baselineManifest = JSON.parse(execFileSync("git", ["show", "f9bc899:assets/compiled/manifest.json"], { encoding: "utf8" }));
  const baselinePath = baselineManifest["src/main.js"].css.find((name) => name.startsWith("assets/main-"));
  const baseline = execFileSync("git", ["show", `f9bc899:assets/compiled/${baselinePath}`]);
  assert.ok(current.length - baseline.length < 1024, `main CSS growth is ${current.length - baseline.length} bytes (must remain below 1 KB)`);
  const currentStorefront = Buffer.from(read("src/styles/storefront.css"));
  const baselineStorefront = execFileSync("git", ["show", "f9bc899:src/styles/storefront.css"]);
  assert.ok(currentStorefront.length - baselineStorefront.length < 1024, `product-card stylesheet growth is ${currentStorefront.length - baselineStorefront.length} bytes (must remain below 1 KB)`);
});
