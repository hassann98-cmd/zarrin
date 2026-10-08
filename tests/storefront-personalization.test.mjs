import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/storefront-personalization.js", import.meta.url), "utf8");
const read = (path) => fs.readFileSync(new URL(path, import.meta.url), "utf8");
const tick = () => new Promise((resolve) => setTimeout(resolve, 20));
const response = (payload, ok = true) => ({ ok, json: async () => payload });

function boot({ html, settings = {}, recent = [], fetch = async () => response({ items: [] }), url = "https://shop.test/product/current/" }) {
  const dom = new JSDOM(`<!doctype html><html><body>${html}</body></html>`, {
    url,
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  const { window } = dom;
  window.jluxePersonalizationSettings = settings;
  window.fetch = fetch;
  window.jQuery = jquery(window);
  if (recent.length) window.localStorage.setItem("jluxe_recently_viewed", JSON.stringify(recent));
  vm.runInContext(source, dom.getInternalVMContext());
  return dom;
}

test("recently viewed history renders separate mobile-friendly product and homepage headings", async (t) => {
  const requests = [];
  const dom = boot({
    html: `
      <main class="single-product"><div data-jluxe-product-id="100"></div></main>
      <section data-jluxe-recent-products data-jluxe-recent-context="product" data-jluxe-recent-item-count="0" hidden>
        <h2>اخیراً دیده‌اید</h2><span data-jluxe-recent-count hidden></span>
        <div data-jluxe-recent-nav hidden><button data-jluxe-recent-prev></button><button data-jluxe-recent-next></button></div>
        <div data-jluxe-recent-list role="list" tabindex="0" aria-keyshortcuts="ArrowLeft ArrowRight Home End"></div>
      </section>
      <section data-jluxe-recent-products data-jluxe-recent-context="home" data-jluxe-recent-item-count="0" hidden>
        <h2>ادامه خرید شما</h2><span data-jluxe-recent-count hidden></span>
        <div data-jluxe-recent-nav hidden><button data-jluxe-recent-prev></button><button data-jluxe-recent-next></button></div>
        <div data-jluxe-recent-list role="list" tabindex="0" aria-keyshortcuts="ArrowLeft ArrowRight Home End"></div>
      </section>`,
    settings: { recentProductsUrl: "/wp-json/jluxe/v1/recent-products" },
    recent: ["51", "52", "53", "54"],
    fetch: async (url) => {
      const ids = new URL(String(url), "https://shop.test").searchParams.get("ids");
      requests.push(ids);
      return response({ items: [
        { id: 51, name: "چراغ رومیزی", url: "/product/51/", image: "/lamp.jpg", imageAlt: "چراغ", price: "۱۰۰ تومان", inStock: true },
        { id: 52, name: "محصول <script>نامطمئن</script>", url: "/product/52/", image: "", price: "۲۰۰ تومان", inStock: false },
        { id: 53, name: "چارپایه لیمون 2143 رنگ موکا", url: "/product/53/", image: "/stool.jpg", imageAlt: "چارپایه رنگ موکا", price: "۱٬۲۰۰٬۰۰۰ تومان", inStock: true },
        { id: 54, name: "محصول متغیر با بازهٔ قیمت", url: "/product/54/", image: "/range.jpg", imageAlt: "محصول با بازهٔ قیمت", price: "۱,۷۸۰,۰۰۰ &amp;amp;ndash; ۱,۸۵۰,۰۰۰", currentPrice: "۱,۷۸۰,۰۰۰ &amp;amp;ndash; ۱,۸۵۰,۰۰۰", regularPrice: "۱,۹۰۰,۰۰۰ تا ۲,۰۰۰,۰۰۰", priceIsRange: true, onSale: true, inStock: true },
      ] });
    },
  });
  t.after(() => dom.window.close());
  await tick();

  assert.deepEqual(requests.sort(), ["100,51,52,53,54", "51,52,53,54"].sort(), "product detail excludes its own current item while the homepage retains the full history");
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_recently_viewed")), ["100", "51", "52", "53", "54"]);
  const panels = [...dom.window.document.querySelectorAll("[data-jluxe-recent-products]")];
  assert.equal(panels[0].hidden, false);
  assert.equal(panels[1].hidden, false);
  assert.equal(panels[0].querySelectorAll('[role="listitem"]').length, 4);
  assert.equal(panels[0].querySelector("[data-jluxe-recent-count]").textContent, "۴ محصول");
  assert.equal(panels[0].getAttribute("data-jluxe-recent-item-count"), "4");
  assert.equal(panels[0].querySelector(".jluxe-recent-card__price").textContent, "۱۰۰ تومان");
  assert.equal(panels[0].querySelectorAll("script").length, 0, "product names use textContent rather than HTML injection");
  const cards = [...panels[0].querySelectorAll(".jluxe-recent-card")];
  assert.equal(cards[1].querySelector(".jluxe-recent-card__stock").textContent, "فعلاً ناموجود");
  assert.equal(cards[1].querySelector(".jluxe-recent-card__price-current").textContent, "۲۰۰ تومان", "availability no longer replaces the product price");
  assert.equal(cards[1].querySelector(".jluxe-recent-card__media").getAttribute("data-empty-image"), "true", "a missing photo keeps a designed media placeholder");
  assert.equal(cards[2].querySelector(".jluxe-recent-card__name").textContent, "چارپایه لیمون 2143 رنگ موکا");
  assert.equal(cards[2].querySelector(".jluxe-recent-card__stock").textContent, "موجود", "an in-stock variable parent is presented as available");
  assert.equal(cards[2].querySelector(".jluxe-recent-card__price-current").textContent, "۱٬۲۰۰٬۰۰۰ تومان");
  assert.equal(cards[2].querySelector(".jluxe-recent-card__cta").textContent, "مشاهده");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price-current").textContent, "۱,۷۸۰,۰۰۰ – ۱,۸۵۰,۰۰۰", "escaped price separators render as a single clean Persian range");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price-before").textContent, "۱,۹۰۰,۰۰۰ تا ۲,۰۰۰,۰۰۰", "the pre-sale range is shown separately and struck through");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price-range-label").textContent, "بازهٔ قیمت");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price").getAttribute("data-on-sale"), "true");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price").dir, "rtl");
  assert.equal(cards[3].querySelector(".jluxe-recent-card__price-current").dir, "auto");

  const brokenImage = cards[0].querySelector(".jluxe-recent-card__image");
  brokenImage.dispatchEvent(new dom.window.Event("error"));
  assert.equal(brokenImage.hidden, true);
  assert.equal(cards[0].querySelector(".jluxe-recent-card__media").getAttribute("data-empty-image"), "true", "broken images switch to the designed fallback");

  const list = panels[0].querySelector("[data-jluxe-recent-list]");
  list.focus();
  list.dispatchEvent(new dom.window.KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true, cancelable: true }));
  assert.equal(dom.window.document.activeElement, cards[1].querySelector("a"), "left arrow moves toward older cards in the RTL rail");
  list.dispatchEvent(new dom.window.KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true, cancelable: true }));
  assert.equal(dom.window.document.activeElement, cards[0].querySelector("a"), "right arrow moves toward newer cards in the RTL rail");
  dom.window.dispatchEvent(new dom.window.PageTransitionEvent("pageshow", { persisted: true }));
  await tick();
  assert.equal(
    dom.window.document.activeElement,
    panels[0].querySelector('[data-jluxe-recent-product-id="51"] a'),
    "refreshing the rail keeps keyboard focus on the same product when it still exists",
  );
});

test("recent-product history uses a polished responsive rail and clear stock styling", () => {
  const styles = read("../src/styles/storefront.css");
  assert.match(styles, /\.jluxe-recent-products__heading\s*\{/);
  assert.match(styles, /\.jluxe-recent-products__subtitle\s*\{/);
  assert.match(styles, /\.jluxe-recent-card__media\s*\{/);
  assert.match(styles, /\.jluxe-recent-card__stock\.is-in-stock\s*\{/);
  assert.match(styles, /\.jluxe-recent-card:hover\s*\{/);
  assert.match(styles, /\.jluxe-recent-products\s*\{[^}]*font-family: IRANYekan/);
  assert.match(styles, /\.jluxe-recent-card__price\s*\{[^}]*font-family: IRANYekan/);
  assert.match(styles, /@media \(max-width: 639px\)[\s\S]*?\.jluxe-recent-card\s*\{\s*flex-basis: min\(78vw, 296px\)/);
  assert.match(styles, /@media \(max-width: 639px\)[\s\S]*?\.jluxe-recent-card__link \{ display: grid; grid-template-columns: 98px minmax\(0, 1fr\);/);
  assert.match(styles, /\.jluxe-recent-card__price \{ display: flex; max-width: 100%; font-size: 14px; font-weight: 800;/);
  assert.match(styles, /\.jluxe-recent-card__price\s*\{[^}]*white-space: normal;/);
  assert.match(styles, /\.jluxe-recent-products__list\s*\{[^}]*overflow-x: auto;[^}]*direction: rtl;/);
  assert.match(styles, /\.jluxe-recent-card__image\[hidden\] \{ display: none; \}/);
  assert.match(styles, /data-jluxe-recent-item-count=\"1\"[\s\S]*?grid-template-columns: 128px minmax\(0, 1fr\)/);
  assert.match(styles, /\.jluxe-recent-card__price-before \{[^}]*text-decoration: line-through;/);
  const reducedMotionRule = styles.indexOf(".jluxe-recent-card, .jluxe-recent-card__image { transition: none;");
  assert.ok(reducedMotionRule > 0 && styles.lastIndexOf("@media (prefers-reduced-motion: reduce)", reducedMotionRule) >= 0, "card motion respects reduced-motion preferences");
});

test("history refreshes after bfcache return, tab visibility and storage updates without stale responses", async (t) => {
  const pending = [];
  const dom = boot({
    html: `<section data-jluxe-recent-products data-jluxe-recent-context="home" hidden>
      <span data-jluxe-recent-count hidden></span><div data-jluxe-recent-list role="list" tabindex="0"></div>
    </section>`,
    settings: { recentProductsUrl: "/wp-json/jluxe/v1/recent-products" },
    recent: ["51"],
    fetch: (url) => new Promise((resolve) => {
      pending.push({ ids: new URL(String(url), "https://shop.test").searchParams.get("ids"), resolve });
    }),
  });
  t.after(() => dom.window.close());
  const { document, localStorage } = dom.window;
  const panel = document.querySelector("[data-jluxe-recent-products]");
  const list = panel.querySelector("[data-jluxe-recent-list]");
  const item = (id) => ({ id, name: `محصول ${id}`, url: `/product/${id}/`, image: "", price: `${id} تومان`, inStock: true });

  assert.equal(pending.length, 1, "initial history fetch starts once");
  dom.window.dispatchEvent(new dom.window.PageTransitionEvent("pageshow", { persisted: true }));
  await tick();
  assert.equal(pending.length, 2, "a page restored from browser history requests fresh data");
  assert.equal(pending[1].ids, "51");
  pending[1].resolve(response({ items: [item(52)] }));
  await tick();
  assert.equal(list.querySelector(".jluxe-recent-card__name").textContent, "محصول 52");

  pending[0].resolve(response({ items: [item(51)] }));
  await tick();
  assert.equal(list.querySelector(".jluxe-recent-card__name").textContent, "محصول 52", "an older response cannot overwrite the more recent request");

  localStorage.setItem("jluxe_recently_viewed", JSON.stringify(["53", "52"]));
  dom.window.dispatchEvent(new dom.window.StorageEvent("storage", { key: "jluxe_recently_viewed", newValue: '["53","52"]' }));
  await tick();
  assert.equal(pending.length, 3, "a history change in another tab triggers a refresh");
  assert.equal(pending[2].ids, "53,52");
  pending[2].resolve(response({ items: [item(53), item(52)] }));
  await tick();
  assert.equal(panel.querySelector("[data-jluxe-recent-count]").textContent, "۲ محصول");

  document.dispatchEvent(new dom.window.Event("visibilitychange"));
  await tick();
  assert.equal(pending.length, 4, "returning to a visible tab refreshes current price and stock data");
  pending[3].resolve(response({ items: [item(54)] }));
  await tick();
  assert.equal(list.querySelector(".jluxe-recent-card__name").textContent, "محصول 54");
});

test("a single recent product uses compact markup and search results layer above sticky product tabs", async (t) => {
  const dom = boot({
    html: `<section data-jluxe-recent-products data-jluxe-recent-context="home" data-jluxe-recent-item-count="0" hidden>
      <span data-jluxe-recent-count hidden></span><div data-jluxe-recent-list role="list" tabindex="0"></div>
    </section>`,
    settings: { recentProductsUrl: "/wp-json/jluxe/v1/recent-products" },
    recent: ["71"],
    fetch: async () => response({ items: [{ id: 71, name: "محصول تکی", url: "/product/71/", image: "", price: "۷۱۰ تومان", inStock: true }] }),
  });
  t.after(() => dom.window.close());
  await tick();
  const panel = dom.window.document.querySelector("[data-jluxe-recent-products]");
  assert.equal(panel.getAttribute("data-jluxe-recent-item-count"), "1");
  assert.equal(panel.querySelector("[data-jluxe-recent-count]").textContent, "۱ محصول");
  assert.equal(panel.querySelectorAll(".jluxe-recent-card").length, 1);

  const styles = read("../src/styles/storefront.css");
  const header = read("../header.php");
  const classicProduct = read("../woocommerce/content-single-product-classic.php");
  assert.match(styles, /data-jluxe-recent-item-count="1"[\s\S]*?grid-template-columns: 128px minmax\(0, 1fr\)/);
  assert.match(header, /fixed inset-x-0 z-40 jluxe-header-bar-sticky/);
  assert.match(classicProduct, /\.cp3-nav\{position:sticky;z-index:30;/);
});

test("variation stock state opens an accessible mobile alert dialog and sends a phone opt-in", async (t) => {
  let submitted = null;
  const dom = boot({
    html: `
      <div class="single-product" data-jluxe-product-id="100" data-jluxe-layout="default">
        <form class="variations_form"><select><option>رنگ</option></select></form>
        <div data-jluxe-stock-alert-trigger data-jluxe-stock-alert-default-visible="false" hidden>
          <p>این محصول فعلاً موجود نیست</p>
          <button type="button" data-jluxe-stock-alert-open aria-controls="alert-100" data-variation-id="0">وقتی موجود شد خبرم کن</button>
        </div>
        <dialog id="alert-100" data-jluxe-stock-alert-dialog tabindex="-1">
          <button type="button" data-jluxe-stock-alert-close>بستن</button>
          <form data-jluxe-stock-alert-form>
            <input type="hidden" name="variation_id" value="0" data-jluxe-stock-alert-variation>
            <input name="phone" type="tel" value="09121234567">
            <p data-jluxe-stock-alert-status role="status" tabindex="-1"></p>
            <button type="submit">ثبت درخواست</button>
          </form>
        </dialog>
      </div>`,
    settings: { ajaxUrl: "/admin-ajax.php" },
    fetch: async (url, options) => {
      submitted = { url, phone: options.body.get("phone"), variationId: options.body.get("variation_id") };
      return response({ success: true, data: { message: "ثبت شد؛ پیامک می‌دهیم." } });
    },
  });
  t.after(() => dom.window.close());
  const { document, jQuery: $ } = dom.window;
  const form = document.querySelector("form.variations_form");
  const trigger = document.querySelector("[data-jluxe-stock-alert-trigger]");
  const open = trigger.querySelector("[data-jluxe-stock-alert-open]");
  const dialog = document.getElementById("alert-100");
  const variationField = dialog.querySelector("[data-jluxe-stock-alert-variation]");

  $(form).trigger("found_variation", [{ variation_id: 210, is_in_stock: false }]);
  assert.equal(trigger.hidden, false);
  assert.equal(open.getAttribute("data-variation-id"), "210");
  assert.equal(variationField.value, "210");
  $(form).trigger("found_variation", [{ variation_id: 211, is_in_stock: true }]);
  assert.equal(trigger.hidden, true, "an available variation hides the back-in-stock action");
  $(form).trigger("found_variation", [{ variation_id: 210, is_in_stock: false }]);

  open.focus();
  open.click();
  assert.equal(dialog.open, true);
  assert.equal(dialog.classList.contains("jluxe-stock-alert-dialog--fallback"), true, "non-native dialog fallback still creates a modal overlay");
  await tick();
  assert.equal(document.activeElement, dialog.querySelector('input[name="phone"]'));
  const submit = dialog.querySelector("form[data-jluxe-stock-alert-form]");
  submit.dispatchEvent(new dom.window.Event("submit", { bubbles: true, cancelable: true }));
  await tick();
  assert.deepEqual(submitted, { url: "/admin-ajax.php", phone: "09121234567", variationId: "210" });
  assert.equal(dialog.querySelector("[data-jluxe-stock-alert-status]").textContent, "ثبت شد؛ پیامک می‌دهیم.");
  assert.equal(submit.querySelector('[type="submit"]').hidden, true);

  document.dispatchEvent(new dom.window.KeyboardEvent("keydown", { key: "Escape", bubbles: true, cancelable: true }));
  assert.equal(dialog.open, false);
  assert.equal(document.activeElement, open, "closing restores focus to the trigger");
});

test("PHP renders privacy-scoped wishlist routes, exact recent headings, opt-in copy, and mobile-number UI", () => {
  const php = read("../inc/storefront-personalization.php");
  const stock = read("../inc/stock-alert.php");
  const functions = read("../functions.php");
  assert.match(php, /register_rest_route\([\s\S]*?\/wishlist/);
  assert.match(php, /'permission_callback'\s*=>\s*'jluxe_wishlist_rest_allowed'/);
  assert.match(php, /get_current_user_id\(\)/);
  assert.match(php, /'ادامه خرید شما'/);
  assert.match(php, /'اخیراً دیده‌اید'/);
  assert.match(php, /data-jluxe-recent-count/);
  assert.match(php, /aria-keyshortcuts="ArrowLeft ArrowRight Home End"/);
  assert.match(php, /data-jluxe-recent-nav/);
  assert.match(php, /'ajaxUrl'\s*=>\s*admin_url\( 'admin-ajax\.php' \)/);
  assert.match(stock, /name="nonce" value="<\?php echo esc_attr\( wp_create_nonce\( 'jluxe_stock_alert_signup' \) \); \?>/);
  assert.match(stock, /این محصول فعلاً موجود نیست/);
  assert.match(stock, /وقتی موجود شد خبرم کن/);
  assert.match(stock, /name="phone" type="tel" inputmode="tel"/);
  assert.match(stock, /openssl_encrypt\(\s*\$phone, 'aes-256-gcm'/);
  assert.match(functions, /require_once JLUXE_THEME_DIR \. '\/inc\/storefront-personalization\.php';/);
  assert.match(functions, /require_once JLUXE_THEME_DIR \. '\/inc\/stock-alert\.php';/);
});
