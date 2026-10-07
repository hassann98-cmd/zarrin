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

test("recently viewed history renders polished, mobile-friendly product and homepage cards", async (t) => {
  const requests = [];
  const dom = boot({
    html: `
      <main class="single-product"><div data-jluxe-product-id="100"></div></main>
      <section data-jluxe-recent-products data-jluxe-recent-context="product" hidden>
        <h2>اخیراً دیده‌اید</h2><div data-jluxe-recent-list role="list"></div>
      </section>
      <section data-jluxe-recent-products data-jluxe-recent-context="home" hidden>
        <h2>ادامه خرید شما</h2><div data-jluxe-recent-list role="list"></div>
      </section>`,
    settings: { recentProductsUrl: "/wp-json/jluxe/v1/recent-products" },
    recent: ["51", "52"],
    fetch: async (url) => {
      const ids = new URL(String(url), "https://shop.test").searchParams.get("ids");
      requests.push(ids);
      return response({ items: [
        { id: 51, name: "چراغ رومیزی", url: "/product/51/", image: "/lamp.jpg", imageAlt: "چراغ", price: "۱۰۰ تومان", inStock: true },
        { id: 52, name: "محصول <script>نامطمئن</script>", url: "/product/52/", image: "", price: "", inStock: false },
      ] });
    },
  });
  t.after(() => dom.window.close());
  await tick();

  assert.deepEqual(requests.sort(), ["100,51,52", "51,52"].sort(), "product detail excludes its own current item while the homepage retains the full history");
  assert.deepEqual(JSON.parse(dom.window.localStorage.getItem("jluxe_recently_viewed")), ["100", "51", "52"]);
  const panels = [...dom.window.document.querySelectorAll("[data-jluxe-recent-products]")];
  assert.equal(panels[0].hidden, false);
  assert.equal(panels[1].hidden, false);
  assert.equal(panels[0].querySelectorAll('[role="listitem"]').length, 2);
  assert.equal(panels[0].querySelector(".jluxe-recent-card__price").textContent, "۱۰۰ تومان");
  assert.equal(panels[0].querySelectorAll(".jluxe-recent-card__media").length, 2);
  assert.equal(panels[0].querySelector(".jluxe-recent-card__action").firstChild.textContent, "مشاهده محصول");
  assert.equal(panels[0].querySelector(".jluxe-recent-card__action-arrow").namespaceURI, "http://www.w3.org/2000/svg", "the arrow does not depend on a missing font glyph");
  assert.equal(panels[0].getAttribute("data-jluxe-recent-size"), "2");
  assert.equal(panels[0].querySelector(".jluxe-recent-card__price").closest("a").href, "https://shop.test/product/51/");
  assert.equal(panels[0].querySelector("img").loading, "lazy");
  assert.equal(panels[0].querySelector("img").width, 320);
  assert.equal(panels[0].querySelectorAll("script").length, 0, "product names use textContent rather than HTML injection");
  const unavailableCard = panels[0].querySelectorAll(".jluxe-recent-card")[1];
  assert.equal(unavailableCard.querySelector(".jluxe-recent-card__price").textContent, "فعلاً ناموجود");
  assert.ok(unavailableCard.querySelector(".jluxe-recent-card__price").classList.contains("is-unavailable"));
  assert.equal(unavailableCard.querySelector(".jluxe-recent-card__placeholder").textContent, "تصویر محصول");
});

const historyPanel = `
  <section data-jluxe-recent-products data-jluxe-recent-context="home" hidden>
    <h2 id="recent-heading">ادامه خرید شما</h2><span data-jluxe-recent-count></span>
    <div data-jluxe-recent-controls hidden>
      <button type="button" data-jluxe-recent-prev disabled>قبلی</button>
      <button type="button" data-jluxe-recent-next>بعدی</button>
    </div>
    <div data-jluxe-recent-list role="list" aria-labelledby="recent-heading" style="direction:rtl"></div>
  </section>`;
const historySettings = { recentProductsUrl: "/wp-json/jluxe/v1/recent-products" };
const product = (id, extra = {}) => ({ id, name: `محصول ${id}`, url: `/product/${id}/`, price: "۱۰۰ تومان", inStock: true, ...extra });

test("a missing or failed image has a stable fallback and sale prices stay separate", async (t) => {
  const dom = boot({
    html: historyPanel, settings: historySettings, recent: ["1"],
    fetch: async () => response({ items: [product(1, { image: "/missing.jpg", regularPrice: "۲۰۰ تومان" })] }),
  });
  t.after(() => dom.window.close());
  await tick();
  const panel = dom.window.document.querySelector("section");
  assert.equal(panel.getAttribute("data-jluxe-recent-size"), "1", "one product uses the compact layout");
  assert.equal(panel.querySelector("[data-jluxe-recent-count]").textContent, "۱ محصول");
  assert.equal(panel.querySelector("[data-jluxe-recent-controls]").hidden, true);
  assert.match(panel.querySelector("del").textContent, /۲۰۰ تومان/);
  assert.equal(panel.querySelector(".jluxe-recent-card__price").textContent, "۱۰۰ تومان");
  panel.querySelector("img").dispatchEvent(new dom.window.Event("error"));
  assert.equal(panel.querySelectorAll("img").length, 0);
  assert.equal(panel.querySelectorAll(".jluxe-recent-card__placeholder-icon").length, 1);
  assert.equal(panel.querySelectorAll("a").length, 1, "the entire card is one link, with no nested actions");
});

test("empty history, invalid products and network failures never expose a blank panel", async (t) => {
  let requests = 0;
  const empty = boot({ html: historyPanel, settings: historySettings, fetch: async () => { requests++; return response({ items: [] }); } });
  t.after(() => empty.window.close());
  await tick();
  assert.equal(requests, 0, "no product request without local history");
  assert.equal(empty.window.document.querySelector("section").hidden, true);
  for (const fetch of [
    async () => response({ items: [null, product(1, { url: "javascript:alert(1)" }), product(2, { name: "" })] }),
    async () => response(null, false),
    async () => { throw new Error("Offline"); },
  ]) {
    const dom = boot({ html: historyPanel, settings: historySettings, recent: ["1"], fetch });
    t.after(() => dom.window.close());
    await tick();
    assert.equal(dom.window.document.querySelector("section").hidden, true);
    assert.equal(dom.window.document.querySelectorAll(".jluxe-recent-card").length, 0);
  }
});

test("restored homepages refresh from local history; clearing another tab cannot resurrect a stale response", async (t) => {
  const pending = [];
  const dom = boot({ html: historyPanel, settings: historySettings, fetch: (url) => new Promise(resolve => pending.push({ url, resolve })) });
  t.after(() => dom.window.close());
  const { window } = dom;
  const panel = window.document.querySelector("section");
  window.localStorage.setItem("jluxe_recently_viewed", '["51"]');
  window.dispatchEvent(new window.PageTransitionEvent("pageshow", { persisted: true }));
  assert.equal(pending.length, 1, "back/forward restoration reloads a homepage that originally had no history");
  pending[0].resolve(response({ items: [product(51)] }));
  await tick();
  assert.equal(panel.hidden, false);
  window.localStorage.setItem("jluxe_recently_viewed", '["52","51"]');
  window.dispatchEvent(new window.StorageEvent("storage", { key: "jluxe_recently_viewed" }));
  assert.equal(pending.length, 2);
  window.localStorage.removeItem("jluxe_recently_viewed");
  window.dispatchEvent(new window.StorageEvent("storage", { key: null }));
  assert.equal(panel.hidden, true);
  pending[1].resolve(response({ items: [product(52), product(51)] }));
  await tick();
  assert.equal(panel.hidden, true, "a late request must not put cleared browsing history back on screen");
  assert.equal(panel.querySelectorAll(".jluxe-recent-card").length, 0);
});

test("rapid history changes show only the newest response and ignore unrelated storage events", async (t) => {
  const pending = [];
  const dom = boot({ html: historyPanel, settings: { recentProductsUrl: "/?rest_route=/jluxe/v1/recent-products" }, recent: ["1"], fetch: (url) => new Promise(resolve => pending.push({ url, resolve })) });
  t.after(() => dom.window.close());
  const { window } = dom;
  assert.match(pending[0].url, /&ids=1$/);
  window.dispatchEvent(new window.StorageEvent("storage", { key: "jluxe_wishlist" }));
  assert.equal(pending.length, 1);
  window.localStorage.setItem("jluxe_recently_viewed", '["2","1"]');
  window.dispatchEvent(new window.StorageEvent("storage", { key: "jluxe_recently_viewed" }));
  pending[1].resolve(response({ items: [product(2), product(1)] }));
  await tick();
  pending[0].resolve(response({ items: [product(1)] }));
  await tick();
  const links = [...window.document.querySelectorAll(".jluxe-recent-card__link")];
  assert.deepEqual(links.map(link => link.href), ["https://shop.test/product/2/", "https://shop.test/product/1/"]);
});

test("history navigation follows RTL, disables the ends, hides when it fits and respects reduced motion", async (t) => {
  const dom = boot({ html: historyPanel, settings: historySettings, recent: ["1", "2", "3"], fetch: async () => response({ items: [product(1), product(2), product(3)] }) });
  t.after(() => dom.window.close());
  const { window } = dom;
  const list = window.document.querySelector("[data-jluxe-recent-list]");
  const controls = window.document.querySelector("[data-jluxe-recent-controls]");
  const prev = controls.querySelector("[data-jluxe-recent-prev]");
  const next = controls.querySelector("[data-jluxe-recent-next]");
  let offset = 0;
  let scrollWidth = 600;
  Object.defineProperties(list, { clientWidth: { value: 300 }, scrollWidth: { get: () => scrollWidth } });
  list.getBoundingClientRect = () => ({ left: 0, right: 300 });
  await tick();
  list.firstElementChild.getBoundingClientRect = () => ({ left: 100 + offset, right: 300 + offset });
  list.lastElementChild.getBoundingClientRect = () => ({ left: -300 + offset, right: -100 + offset });
  const moves = [];
  list.scrollBy = options => {
    moves.push(options);
    offset = Math.max(0, Math.min(300, offset - options.left));
    list.dispatchEvent(new window.Event("scroll"));
  };
  window.dispatchEvent(new window.Event("resize"));
  await tick();
  assert.equal(controls.hidden, false);
  assert.equal(list.getAttribute("tabindex"), "0");
  assert.equal(prev.disabled, true);
  assert.equal(next.disabled, false);
  next.click();
  await tick();
  assert.equal(moves[0].left < 0, true, "next moves left through an RTL row");
  assert.equal(moves[0].behavior, "smooth");
  assert.equal(prev.disabled, false);
  next.click();
  await tick();
  assert.equal(next.disabled, true);
  window.matchMedia = () => ({ matches: true });
  list.dispatchEvent(new window.KeyboardEvent("keydown", { key: "ArrowRight", cancelable: true, bubbles: true }));
  await tick();
  assert.equal(moves.at(-1).left > 0, true);
  assert.equal(moves.at(-1).behavior, "auto");
  scrollWidth = 300;
  window.dispatchEvent(new window.Event("resize"));
  await tick();
  assert.equal(controls.hidden, true);
  assert.equal(list.hasAttribute("tabindex"), false);
});

test("history is bounded and product names, prices and image URLs remain inert", async (t) => {
  const dom = boot({ html: historyPanel, settings: historySettings, recent: ["1"], fetch: async () => response({ items: Array.from({ length: 12 }, (_, i) => product(i + 1, {
    name: '<img src=x onerror="alert(1)">', price: '<b onclick="alert(1)">100</b>', image: 'javascript:alert(1)',
  })) }) });
  t.after(() => dom.window.close());
  await tick();
  const panel = dom.window.document.querySelector("section");
  assert.equal(panel.querySelectorAll(".jluxe-recent-card").length, 8);
  assert.equal(panel.querySelector("[data-jluxe-recent-count]").textContent, "۸ محصول");
  assert.equal(panel.querySelectorAll("img, script, [onerror], [onclick], b").length, 0);
  assert.equal(panel.querySelectorAll(".jluxe-recent-card__placeholder").length, 8);
  assert.match(panel.querySelector(".jluxe-recent-card__price").textContent, /<b/);
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

test("PHP renders privacy-scoped wishlist routes, the recent-products presentation, and opt-in copy", () => {
  const php = read("../inc/storefront-personalization.php");
  const stock = read("../inc/stock-alert.php");
  const functions = read("../functions.php");
  const storefront = read("../src/styles/storefront.css");
  assert.match(php, /register_rest_route\([\s\S]*?\/wishlist/);
  assert.match(php, /'permission_callback'\s*=>\s*'jluxe_wishlist_rest_allowed'/);
  assert.match(php, /get_current_user_id\(\)/);
  assert.match(php, /'ادامه خرید شما'/);
  assert.match(php, /'اخیراً دیده‌اید'/);
  assert.match(php, /class="jluxe-recent-products__header"/);
  assert.match(php, /class="jluxe-recent-products__description"/);
  assert.match(php, /data-jluxe-recent-count/);
  assert.match(php, /aria-label="محصولات قبلی"/);
  assert.match(php, /aria-label="محصولات بعدی"/);
  assert.match(storefront, /\.jluxe-recent-products__header/);
  assert.match(storefront, /\.jluxe-recent-card__media/);
  assert.match(storefront, /\.jluxe-recent-card__link:focus-visible/);
  assert.match(storefront, /@media \(max-width: 639px\)/);
  assert.match(storefront, /prefers-reduced-motion: reduce/);
  assert.match(php, /'ajaxUrl'\s*=>\s*admin_url\( 'admin-ajax\.php' \)/);
  assert.match(stock, /name="nonce" value="<\?php echo esc_attr\( wp_create_nonce\( 'jluxe_stock_alert_signup' \) \); \?>/);
  assert.match(stock, /این محصول فعلاً موجود نیست/);
  assert.match(stock, /وقتی موجود شد خبرم کن/);
  assert.match(stock, /name="phone" type="tel" inputmode="tel"/);
  assert.match(stock, /openssl_encrypt\(\s*\$phone, 'aes-256-gcm'/);
  assert.match(functions, /require_once JLUXE_THEME_DIR \. '\/inc\/storefront-personalization\.php';/);
  assert.match(functions, /require_once JLUXE_THEME_DIR \. '\/inc\/stock-alert\.php';/);
});
