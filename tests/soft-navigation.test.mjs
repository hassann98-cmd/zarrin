import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(
  new URL("../assets/js/soft-navigation.js", import.meta.url),
  "utf8",
);
const tick = () => new Promise((resolve) => setTimeout(resolve, 15));

function documentHtml({
  kind = "catalog",
  title = "فروشگاه",
  canonical = "https://shop.test/store/catalog/",
  bodyClass = "archive post-type-archive-product",
  main = "<div class=\"jluxe-shop-toolbar\">فیلتر</div><div id=\"product-result\">نسخهٔ اولیه</div>",
  paginationBase = "page",
} = {}) {
  return `<!doctype html><html lang="fa-IR" dir="rtl"><head>
    <title>${title}</title>
    <meta name="description" content="${title} description">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="${title}">
    <link rel="canonical" href="${canonical}">
  </head><body class="${bodyClass}">
    <header class="jluxe-header-bar-sticky"></header>
    <main id="primary" data-jluxe-soft-nav="${kind}" data-jluxe-pagination-base="${paginationBase}">${main}</main>
  </body></html>`;
}

function responseFor(url, html, { ok = true, contentType = "text/html; charset=UTF-8" } = {}) {
  return {
    ok,
    status: ok ? 200 : 500,
    url: String(url),
    headers: { get: (name) => name.toLowerCase() === "content-type" ? contentType : null },
    text: async () => html,
  };
}

function boot({ url, html, respond } = {}) {
  const dom = new JSDOM(html, {
    url,
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  const { window } = dom;
  const requests = [];
  const scrolls = [];
  const enhancedSortRoots = [];
  window.fetch = async (requestUrl, options) => {
    requests.push({ url: String(requestUrl), options });
    return respond
      ? respond(String(requestUrl), options)
      : responseFor(requestUrl, documentHtml({ canonical: String(requestUrl) }));
  };
  window.matchMedia = () => ({ matches: false });
  window.scrollTo = (options) => scrolls.push(options);
  window.jluxeEnhanceShopSort = (root) => enhancedSortRoots.push(root);
  vm.runInContext(source, dom.getInternalVMContext());
  return { dom, window, requests, scrolls, enhancedSortRoots };
}

test("catalog pagination swaps validated server HTML, updates URL/SEO and keeps common shell", async (t) => {
  const firstUrl = "https://shop.test/store/catalog/";
  const nextUrl = "https://shop.test/store/catalog/page/2/?orderby=price";
  const html = documentHtml({
    title: "صفحهٔ دوم فروشگاه",
    canonical: nextUrl,
    bodyClass: "archive tax-product_cat term-new-category",
    main: `<div class="jluxe-shop-toolbar"><span class="jluxe-shop-toolbar-count">نمایش ۱۳ تا ۲۴</span></div>
      <div id="product-result">محصولات صفحهٔ دوم</div>
      <nav class="woocommerce-pagination"><a href="${nextUrl}" rel="next">بعدی</a></nav>`,
  });
  const app = boot({
    url: firstUrl,
    html: documentHtml({
      main: `<div class="jluxe-shop-toolbar">فیلتر</div><div id="product-result">محصولات صفحهٔ اول</div>
        <nav class="woocommerce-pagination"><a id="next" href="${nextUrl}" rel="next">بعدی</a></nav>`,
    }),
    respond: (url, options) => responseFor(url, html),
  });
  t.after(() => app.dom.window.close());
  const { document } = app.window;
  const header = document.querySelector("header");
  const link = document.querySelector("#next");
  const click = new app.window.MouseEvent("click", { bubbles: true, cancelable: true, button: 0 });
  link.dispatchEvent(click);
  await tick();

  assert.equal(click.defaultPrevented, true);
  assert.equal(app.requests.length, 1);
  assert.equal(app.requests[0].url, nextUrl);
  assert.equal(app.requests[0].options.method, "GET");
  assert.equal(app.requests[0].options.credentials, "same-origin");
  assert.equal(app.requests[0].options.cache, "no-store");
  assert.equal(app.window.location.href, nextUrl);
  assert.equal(document.querySelector("#product-result").textContent, "محصولات صفحهٔ دوم");
  assert.equal(document.querySelector("header"), header, "the React-powered site shell is not rebuilt");
  assert.equal(document.title, "صفحهٔ دوم فروشگاه");
  assert.equal(document.querySelector('meta[name="description"]').content, "صفحهٔ دوم فروشگاه description");
  assert.equal(document.querySelector('meta[name="robots"]').content, "index,follow");
  assert.equal(document.querySelector('meta[property="og:title"]').content, "صفحهٔ دوم فروشگاه");
  assert.equal(document.querySelector('link[rel="canonical"]').href, nextUrl);
  assert.match(document.body.className, /term-new-category/);
  assert.equal(document.querySelector("main").hasAttribute("aria-busy"), false);
  assert.equal(app.enhancedSortRoots.length, 1, "the rebuilt sort widget is re-enhanced");
  assert.equal(app.window.history.state.jluxeSoftNavigation.kind, "catalog");
  assert.equal(document.querySelector('[role="status"]').textContent, "نمایش ۱۳ تا ۲۴؛ فهرست به‌روزرسانی شد.");
});

test("catalog sorting removes pagination, preserves active query filters, and does not bubble to Woo's native submit", async (t) => {
  const start = "https://shop.test/store/catalog/page/3/?min_price=100&filter_stock=instock&paged=3";
  let servedUrl = "";
  const app = boot({
    url: start,
    html: documentHtml({
      main: `<form class="woocommerce-ordering"><select class="orderby" name="orderby"><option value="menu_order">پیش‌فرض</option><option value="price">ارزان‌ترین</option></select></form>
        <div class="jluxe-shop-toolbar"><span class="jluxe-shop-toolbar-count">۱۲ محصول</span></div>
        <div id="product-result">قدیمی</div>`,
    }),
    respond: (url) => {
      servedUrl = url;
      return responseFor(url, documentHtml({
        title: "مرتب‌شده بر اساس قیمت",
        canonical: url,
        main: `<div class="jluxe-shop-toolbar"><span class="jluxe-shop-toolbar-count">۱۲ محصول</span></div><div id="product-result">مرتب‌شده</div>`,
      }));
    },
  });
  t.after(() => app.dom.window.close());
  const select = app.window.document.querySelector("select.orderby");
  select.value = "price";
  const change = new app.window.Event("change", { bubbles: true, cancelable: true });
  select.dispatchEvent(change);
  await tick();

  assert.equal(change.defaultPrevented, true);
  assert.equal(servedUrl, "https://shop.test/store/catalog/?min_price=100&filter_stock=instock&orderby=price");
  assert.equal(app.window.location.pathname, "/store/catalog/");
  const query = new URLSearchParams(app.window.location.search);
  assert.equal(query.get("min_price"), "100");
  assert.equal(query.get("filter_stock"), "instock");
  assert.equal(query.get("orderby"), "price");
  assert.equal(query.has("paged"), false);
  assert.equal(app.window.document.querySelector("#product-result").textContent, "مرتب‌شده");
});

test("blog category links, pagination, and search use the same progressive route layer", async (t) => {
  const home = "https://shop.test/store/magazine/";
  const category = "https://shop.test/store/category/home/";
  const search = "https://shop.test/store/?s=%D8%B1%D8%A7%D9%87%D9%86%D9%85%D8%A7";
  const pages = new Map([
    [category, documentHtml({ kind: "blog", title: "دستهٔ خانه", canonical: category, main: `<section class="jluxe-blog-listing"><h1 class="jluxe-blog-listing__title">دستهٔ خانه</h1><form class="jluxe-search" method="get" action="https://shop.test/store/"><input name="s" value=""><button type="submit">جستجو</button></form></section>` })],
    [search, documentHtml({ kind: "blog", title: "نتیجهٔ راهنما", canonical: search, main: "<h1>نتیجهٔ راهنما</h1>" })],
  ]);
  const app = boot({
    url: home,
    html: documentHtml({
      kind: "blog",
      main: `<section class="jluxe-blog-listing"><h1 class="jluxe-blog-listing__title">مجله</h1>
        <a class="jluxe-blog-card__category" href="${category}">خانه</a>
        <form class="jluxe-search" method="get" action="https://shop.test/store/"><input name="s" value=""><button type="submit">جستجو</button></form>
        <nav class="jluxe-blog-pagination"><a href="https://shop.test/store/page/2/">بعدی</a></nav></section>`,
    }),
    respond: (url) => responseFor(url, pages.get(url) || documentHtml({ kind: "blog", canonical: url })),
  });
  t.after(() => app.dom.window.close());
  const categoryLink = app.window.document.querySelector(".jluxe-blog-card__category");
  const categoryClick = new app.window.MouseEvent("click", { bubbles: true, cancelable: true, button: 0 });
  categoryLink.dispatchEvent(categoryClick);
  await tick();
  assert.equal(categoryClick.defaultPrevented, true);
  assert.equal(app.window.location.href, category);
  assert.equal(app.window.document.title, "دستهٔ خانه");

  const form = app.window.document.querySelector("form.jluxe-search");
  form.querySelector('[name="s"]').value = "راهنما";
  const submit = new app.window.Event("submit", { bubbles: true, cancelable: true });
  form.dispatchEvent(submit);
  await tick();
  assert.equal(submit.defaultPrevented, true);
  assert.equal(app.window.location.href, search);
  assert.equal(app.window.document.querySelector("h1").textContent, "نتیجهٔ راهنما");
  assert.deepEqual(app.requests.map((request) => request.url), [category, search]);
});

test("external/modified clicks and non-archive responses leave normal navigation intact", async (t) => {
  const current = "https://shop.test/store/catalog/";
  const app = boot({
    url: current,
    html: documentHtml({
      main: `<div class="jluxe-shop-toolbar"></div><nav class="woocommerce-pagination"><a id="next" href="https://other.test/page/2/">بعدی</a></nav>`,
    }),
    respond: (url) => responseFor(url, "<!doctype html><html><body><main id=primary>no archive marker</main></body></html>"),
  });
  t.after(() => app.dom.window.close());

  const link = app.window.document.querySelector("#next");
  const modified = new app.window.MouseEvent("click", { bubbles: true, cancelable: true, button: 0, ctrlKey: true });
  link.dispatchEvent(modified);
  assert.equal(modified.defaultPrevented, false);
  assert.equal(app.requests.length, 0);

  const result = await app.window.JLuxeSoftNavigation.navigate("https://shop.test/store/catalog/page/2/");
  assert.equal(result, false);
  assert.equal(app.window.location.href, current);
  assert.equal(app.window.document.querySelector("main").hasAttribute("aria-busy"), false);
});

test("sort reset honors a custom WordPress pagination base", async (t) => {
  const current = "https://shop.test/store/catalog/pagina/4/?paged=4";
  let requested = "";
  const app = boot({
    url: current,
    html: documentHtml({
      paginationBase: "pagina",
      main: `<form class="woocommerce-ordering"><select class="orderby"><option value="menu_order">پیش‌فرض</option><option value="date">جدیدترین</option></select></form><div class="jluxe-shop-toolbar"></div>`,
    }),
    respond: (url) => {
      requested = url;
      return responseFor(url, documentHtml({ kind: "catalog", canonical: url, main: "<div class=\"jluxe-shop-toolbar\"></div>" }));
    },
  });
  t.after(() => app.dom.window.close());
  const select = app.window.document.querySelector("select.orderby");
  select.value = "date";
  select.dispatchEvent(new app.window.Event("change", { bubbles: true, cancelable: true }));
  await tick();
  assert.equal(requested, "https://shop.test/store/catalog/?orderby=date");
});

test("Back/forward history restores the prior SSR listing and saved scroll position", async (t) => {
  const firstUrl = "https://shop.test/store/catalog/";
  const app = boot({
    url: "https://shop.test/store/catalog/page/2/",
    html: documentHtml({ main: "<div class=\"jluxe-shop-toolbar\"></div><div id=\"product-result\">صفحهٔ دوم</div>" }),
    respond: (url) => responseFor(url, documentHtml({
      title: "صفحهٔ نخست",
      canonical: firstUrl,
      main: "<div class=\"jluxe-shop-toolbar\"></div><div id=\"product-result\">صفحهٔ نخست</div>",
    })),
  });
  t.after(() => app.dom.window.close());
  const state = { jluxeSoftNavigation: { kind: "catalog", scrollY: 91 } };
  app.window.history.replaceState(state, "", firstUrl);
  app.window.dispatchEvent(new app.window.PopStateEvent("popstate", { state }));
  await tick();

  assert.equal(app.requests[0].url, firstUrl);
  assert.equal(app.window.document.title, "صفحهٔ نخست");
  assert.equal(app.window.document.querySelector("#product-result").textContent, "صفحهٔ نخست");
  assert.equal(app.scrolls.at(-1).top, 91, "the saved scroll position is restored after replacement");
  assert.equal(app.window.location.href, firstUrl);
});

test("non-opted-in pages do not install the router or intercept normal links", (t) => {
  const dom = new JSDOM("<!doctype html><html><body><main id=primary><a id=next href=/store/next/>Next</a></main></body></html>", {
    url: "https://shop.test/store/",
    runScripts: "outside-only",
  });
  t.after(() => dom.window.close());
  vm.runInContext(source, dom.getInternalVMContext());
  assert.equal(dom.window.JLuxeSoftNavigation, undefined);
  const event = new dom.window.MouseEvent("click", { bubbles: true, cancelable: true, button: 0 });
  dom.window.document.querySelector("#next").dispatchEvent(event);
  assert.equal(event.defaultPrevented, false);
});

test("the current archive history entry tracks scroll so Forward restores its real position", async (t) => {
  const app = boot({
    url: "https://shop.test/store/catalog/page/2/",
    html: documentHtml({ main: "<div class=\"jluxe-shop-toolbar\"></div><div id=\"product-result\">صفحهٔ دوم</div>" }),
  });
  t.after(() => app.dom.window.close());
  Object.defineProperty(app.window, "scrollY", { configurable: true, value: 248 });
  app.window.dispatchEvent(new app.window.Event("scroll"));
  await new Promise((resolve) => setTimeout(resolve, 160));
  assert.equal(app.window.history.state.jluxeSoftNavigation.scrollY, 248);
});

test("a slower obsolete archive request cannot overwrite the newest route", async (t) => {
  const olderUrl = "https://shop.test/store/catalog/page/2/";
  const latestUrl = "https://shop.test/store/catalog/page/3/";
  let finishOlder;
  const app = boot({
    url: "https://shop.test/store/catalog/",
    html: documentHtml(),
    respond: (url) => {
      if (url === olderUrl) {
        return new Promise((resolve) => { finishOlder = resolve; });
      }
      return responseFor(url, documentHtml({
        title: "آخرین صفحه",
        canonical: latestUrl,
        main: "<div id=\"product-result\">آخرین نتیجه</div>",
      }));
    },
  });
  t.after(() => app.dom.window.close());
  const older = app.window.JLuxeSoftNavigation.navigate(olderUrl);
  const latest = app.window.JLuxeSoftNavigation.navigate(latestUrl);
  assert.equal(await latest, true);
  finishOlder(responseFor(olderUrl, documentHtml({ title: "نتیجهٔ کهنه", main: "<div id=\"product-result\">قدیمی</div>" })));
  assert.equal(await older, null);
  assert.equal(app.requests[0].options.signal.aborted, true);
  assert.equal(app.window.document.querySelector("#product-result").textContent, "آخرین نتیجه");
  assert.equal(app.window.location.href, latestUrl);
});

test("browsers without fetch keep archive links and WooCommerce sorting fully native", (t) => {
  const nextUrl = "https://shop.test/store/catalog/page/2/";
  const app = boot({
    url: "https://shop.test/store/catalog/",
    html: documentHtml({
      main: `<form class="woocommerce-ordering"><select class="orderby"><option value="menu_order">پیش‌فرض</option><option value="price">ارزان‌ترین</option></select></form>
        <nav class="woocommerce-pagination"><a id="next" href="${nextUrl}">بعدی</a></nav>`,
    }),
  });
  t.after(() => app.dom.window.close());
  app.window.fetch = undefined;

  const linkClick = new app.window.MouseEvent("click", { bubbles: true, cancelable: true, button: 0 });
  app.window.document.querySelector("#next").dispatchEvent(linkClick);
  const select = app.window.document.querySelector("select.orderby");
  const sortChange = new app.window.Event("change", { bubbles: true, cancelable: true });
  select.dispatchEvent(sortChange);

  assert.equal(app.window.JLuxeSoftNavigation.isEnabled(), false);
  assert.equal(linkClick.defaultPrevented, false);
  assert.equal(sortChange.defaultPrevented, false);
  assert.equal(app.requests.length, 0);
});
