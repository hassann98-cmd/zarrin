import { readHomepageSource } from "./helpers/theme-source.mjs";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const php = await Promise.resolve(readHomepageSource());
const sanitizer = await readFile(new URL("../inc/theme-settings-sanitize.php", import.meta.url), "utf8");
const js = await readFile(new URL("../assets/js/homepage.js", import.meta.url), "utf8");

test("homepage sections share the 1320px padded grid and consistent section headers", () => {
  assert.match(php, /\.jluxe-home-section\{box-sizing:border-box;width:100%;max-width:var\(--jluxe-container-max,1320px\);margin:24px auto;padding-inline:16px/);
  assert.match(php, /@media\(max-width:767\.98px\)\{\.jluxe-home-section\{padding-inline:12px\}\}/);
  assert.match(php, /function jluxe_render_homepage_section_header\(/);
  for (const name of ["product_grid", "special_products", "brick_products", "blog"]) {
    const start = php.indexOf(`function jluxe_render_homepage_${name}`);
    assert.notEqual(start, -1, `${name} renderer exists`);
    assert.ok(php.slice(start, start + 12000).includes("jluxe_render_homepage_section_header("), `${name} uses shared header`);
  }
  assert.match(php, /'cta'\s*=>\s*'دعوت به اقدام'/);
});

test("category, brand, collage, and recommendation surfaces use neutral tokens and one-time CSS", () => {
  for (const name of ["category_showcase", "category_grid", "brand_marquee"]) {
    const start = php.indexOf(`function jluxe_render_homepage_${name}`);
    const guard = php.indexOf("static $printed = false;", start);
    assert.ok(start >= 0 && guard > start && guard - start < 200, `${name} has a CSS print guard`);
  }
  assert.match(php, /rgba\(15,23,42/);
  assert.doesNotMatch(php, /rgba\(16,0,51/);
  assert.match(php, /jluxe-category-grid-ref\{box-sizing:border-box;width:min\(calc\(100% - 32px\),1288px\);max-width:none/);
  assert.match(php, /border-radius:var\(--jluxe-cat-section-radius,20px\)/);
  assert.match(php, /font-size:22px;line-height:1\.25/);
  assert.match(php, /jluxe-category-grid-ref-arrow\.is-prev\{left:8px\}/);
  assert.match(php, /width="<\?php echo esc_attr\( \(string\) \$row\['img_width'\] \); \?>" height="<\?php echo esc_attr\( \(string\) \$row\['img_height'\] \); \?>"/);
  assert.match(php, /background:hsl\(var\(--surface\)\)/);
  assert.ok(php.includes("sanitize_hex_color( $section['image_frame_color'] ?? '' ) ?: 'hsl(var(--border))'"));
  assert.ok(sanitizer.includes("$section['image_frame_color'] = sanitize_hex_color( $posted['image_frame_color'] ?? '' ) ?: '#E5E7EB'"));
});

test("recommended cards show the average rating and variable-product sale discount", () => {
  const start = php.indexOf("function jluxe_render_homepage_recommended_card");
  const end = php.indexOf("function jluxe_render_homepage_trust", start);
  const renderer = php.slice(start, end);
  assert.match(renderer, /\$product->get_average_rating\(\)/);
  assert.match(renderer, /if \( \$average_rating > 0 \)/);
  assert.match(renderer, /get_variation_prices\( true \)/);
  assert.match(renderer, /\$discount_pct = max\( \$discount_pct/);
  assert.doesNotMatch(renderer, /\$review_count/);
});

test("product-grid links, banner slider controls, trust icons, blog cards, and admin details are complete", () => {
  assert.match(php, /'sale' === \( \$section\['query'\] \?\? '' \)[\s\S]{0,150}add_query_arg\( 'on_sale', '1'/);
  assert.match(php, /get_term_by\( 'slug', sanitize_title\( \(string\) \$section\['category'\] \), 'product_cat' \)/);
  assert.match(php, /bg-gradient-to-l from-black\/60 via-black\/10 to-transparent/);
  assert.match(php, /<button type="button" data-jluxe-bs-dot[\s\S]+aria-label="رفتن به اسلاید/);
  assert.match(php, /\$trust_icon = ! empty\( \$card\['svg'\] \)/);
  assert.match(php, /data-jluxe-story-cta/);
  assert.match(php, /jluxe-home-blog-scroller grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3/);
  assert.match(php, /<details class="jluxe-hb-campaign-details"[\s\S]+0 === \$ci \? 'open'/);
  assert.match(php, /<details class="jluxe-hb-collage-slot-details"[\s\S]+0 === \$bc \? 'open'/);
});

test("story viewer navigates with buttons and keyboard and updates progress and optional CTA", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-stories>
      <button data-jluxe-story-avatar data-src="/one.jpg" data-media-type="image" data-title="اول" data-link="/product/one" data-button-text="محصول اول"></button>
      <button data-jluxe-story-avatar data-src="/two.jpg" data-media-type="image" data-title="دوم" data-link="/product/two" data-button-text=""></button>
      <div data-jluxe-story-viewer hidden>
        <button data-jluxe-story-close></button><button data-jluxe-story-prev></button><button data-jluxe-story-next></button>
        <div data-jluxe-story-progress aria-valuenow="0"><span data-jluxe-story-progress-fill></span></div>
        <div data-jluxe-story-media></div><span data-jluxe-story-title></span><a data-jluxe-story-cta hidden></a>
      </div>
    </section>
  </body></html>`, { runScripts: "outside-only", url: "https://shop.test/", pretendToBeVisual: true });
  const { window } = dom;
  window.eval(js);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  const avatars = [...window.document.querySelectorAll("[data-jluxe-story-avatar]")];
  const viewer = window.document.querySelector("[data-jluxe-story-viewer]");
  const title = viewer.querySelector("[data-jluxe-story-title]");
  const cta = viewer.querySelector("[data-jluxe-story-cta]");
  const progress = viewer.querySelector("[data-jluxe-story-progress]");

  avatars[0].click();
  assert.equal(viewer.hasAttribute("hidden"), false);
  assert.equal(title.textContent, "اول");
  assert.equal(cta.textContent, "محصول اول");
  assert.equal(cta.getAttribute("href"), "/product/one");
  assert.equal(progress.getAttribute("aria-valuenow"), "50");

  viewer.querySelector("[data-jluxe-story-next]").click();
  assert.equal(title.textContent, "دوم");
  assert.equal(cta.textContent, "مشاهده محصول");
  assert.equal(progress.getAttribute("aria-valuenow"), "100");

  window.document.dispatchEvent(new window.KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));
  assert.equal(title.textContent, "اول");
  window.document.dispatchEvent(new window.KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
  assert.equal(viewer.hasAttribute("hidden"), true);
  dom.window.close();
});
