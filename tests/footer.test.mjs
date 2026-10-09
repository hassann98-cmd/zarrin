import test, { afterEach } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { JSDOM } from "jsdom";

/*
 * R88 — the footer badges used to live in a hidden "staging" div and a script
 * polled every 100ms (plus a MutationObserver) for the React grid by its
 * Tailwind class string, then moved the badges into it. Now footer.php renders
 * the shell and the badge column in place; the island only portals into slots.
 */
const base = "https://shop.test/store/";
const shell = (badges = true) => `
<footer id="jluxe-footer-root" class="flow-root" data-jluxe-footer>
  <div class="mx-auto"><div class="card">
    <div data-jluxe-footer-slot="features"></div>
    <div class="jluxe-footer-content-grid grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4" id="main-grid">
      <div data-jluxe-footer-slot="columns" style="display:contents"></div>
      ${badges ? '<section class="jluxe-site-badges-section" id="badges"><h3>نمادهای سایت</h3><div class="jluxe-site-badge-card"><img src="https://trustseal.enamad.ir/logo.png" alt="enamad"></div></section>' : ""}
    </div>
    <div data-jluxe-footer-slot="bottom"></div>
  </div></div>
  <div data-jluxe-island="footer" id="island"></div>
</footer>`;

const disposers = [];
afterEach(async () => {
  for (const dispose of disposers.splice(0).reverse()) await dispose();
  delete globalThis.window;
  delete globalThis.document;
  delete globalThis.IS_REACT_ACT_ENVIRONMENT;
});

function dom(markup, footer = {}) {
  const instance = new JSDOM(`<!doctype html><body>${markup}</body>`, {
    url: base,
    pretendToBeVisual: true,
  });
  const win = instance.window;
  win.JLuxeThemeSettings = {
    siteName: "زرین",
    urls: { home: base, shop: base + "catalog/", track_order: base + "order-status/" },
    footer: {
      enabled: true,
      trust_badges: [],
      link_columns: [
        {
          title: "راهنما",
          links: [{ label: "پیگیری سفارش", url: "/track-order/", icon: "", svg: "" }],
        },
        {
          title: "شرکت",
          links: [{ label: "درباره ما", url: "/about-us/", icon: "", svg: "" }],
        },
      ],
      copyright: "© زرین",
      ...footer,
    },
    social: { instagram: { enabled: true, url: "https://instagram.com/x" } },
    contact: {},
  };
  globalThis.window = win;
  globalThis.document = win.document;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  disposers.push(() => win.close());
  return win;
}

async function render() {
  const { default: Footer } = await import(
    `../src/islands/Footer.js?case=${Math.random()}`
  );
  const root = createRoot(document.getElementById("island"));
  disposers.push(() => act(() => root.unmount()));
  await act(async () => root.render(React.createElement(Footer)));
}

test("R88 the island fills the server slots; the badge column stays where PHP printed it", async () => {
  dom(shell());
  const badges = document.getElementById("badges");
  await render();
  const grid = document.getElementById("main-grid");
  const columns = grid.querySelector('[data-jluxe-footer-slot="columns"]');
  assert.ok(columns.querySelector("h3"), "link columns render inside the columns slot");
  assert.equal(
    [...columns.querySelectorAll(".jluxe-footer-link-column h3")].map((h) => h.textContent).join("|"),
    "راهنما|شرکت",
  );
  assert.equal(document.getElementById("badges"), badges, "the badge node is the very same node (never re-created)");
  assert.equal(badges.parentElement, grid, "badges are a direct item of the main grid");
  assert.equal(grid.lastElementChild, badges, "…placed after the link columns (in front of «شرکت» in RTL)");
  assert.equal(columns.nextElementSibling, badges);
  assert.equal(document.getElementById("island").childNodes.length, 0, "the island root itself renders nothing visible");
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  assert.match(bottom.textContent, /© زرین/);
  assert.ok(bottom.querySelector('a[aria-label="اینستاگرام"]'), "icon-only social link keeps its aria-label");
  assert.equal(
    columns.querySelector("a.jluxe-footer-nav-link").getAttribute("href"),
    base + "order-status/",
    "R87 mapping still applies inside the portal",
  );
  assert.equal(document.querySelectorAll("footer").length, 1, "no second <footer> is created");
});

test("R196 shipping slogan is a full bold heading in the shared footer hierarchy", async () => {
  dom(shell());
  await render();

  const brand = document.querySelector(".jluxe-footer-brand-description");
  assert.equal(
    brand?.textContent,
    "JLuxe | هنرِ انتخاب برای خانه‌های لوکس. مجموعه‌ای از ظریف‌ترین لوازم خانه و جهیزیه که اصالت و کیفیت را با هم ترکیب کرده است. تجربه‌ای متفاوت از خرید آنلاین.",
  );
  const cards = [...document.querySelectorAll(".jluxe-footer-feature-card")];
  assert.deepEqual(
    cards.map((card) => [
      card.querySelector(".jluxe-footer-feature-title")?.textContent,
      card.querySelector(".jluxe-footer-feature-subtitle")?.textContent,
    ]),
    [
      ["ارسال فوری به سراسر ایران", ""],
      ["پشتیبانی آنلاین", "۲۴ ساعته از طریق شبکه‌های اجتماعی"],
      ["بهترین قیمت", "کف قیمت بازار"],
      ["امنیت خرید", "پرداخت از درگاه مطمئن"],
    ],
  );
  assert.doesNotMatch(
    document.querySelector("footer")?.textContent ?? "",
    /ساعات تماس|آدرس|تضمینِ مرجوعی|نمادِ جدید/,
  );
  const css = fs.readFileSync(
    new URL("../src/styles/storefront.css", import.meta.url),
    "utf8",
  );
  assert.match(css, /#jluxe-footer-root \.jluxe-footer-feature-grid\s*\{[^}]*font-family:\s*IRANYekan,/s);
  assert.match(css, /#jluxe-footer-root \.jluxe-footer-feature-title\s*\{[^}]*font-size:\s*\.9rem;[^}]*font-weight:\s*700;/s);
  assert.match(css, /#jluxe-footer-root \.jluxe-footer-feature-subtitle\s*\{[^}]*font-size:\s*\.77rem;[^}]*min-height:\s*1\.2em;/s);
  assert.match(css, /#jluxe-footer-root \.jluxe-footer-feature-copy\s*\{[^}]*display:\s*grid;[^}]*min-height:\s*2\.85rem;/s);
});

test("R192 configured footer copy, optional features, phone links, and touch/focus styling stay clear", async () => {
  const win = dom(shell(), {
    brand_description: "معرفیِ ثبت‌شده توسط مدیر.",
    support_hours: "ساعات تماسِ ثبت‌شده در تنظیمات.",
    support_text: "راه‌های پشتیبانیِ ثبت‌شده در تنظیمات.",
    feature_cards: [],
  });
  win.JLuxeThemeSettings.shortDescription = "این tagline عمومی نباید متن فوتر را جایگزین کند.";
  win.JLuxeThemeSettings.contact = {
    phone: "021-12345678",
    phone_secondary: "۰۹۱۲۱۲۳۴۵۶۷",
  };
  await render();

  const columns = document.querySelector('[data-jluxe-footer-slot="columns"]');
  assert.match(
    columns.querySelector(".jluxe-footer-brand-description").textContent,
    /معرفیِ ثبت‌شده توسط مدیر/,
  );
  assert.doesNotMatch(
    columns.querySelector(".jluxe-footer-brand-description").textContent,
    /tagline عمومی/,
  );
  assert.match(columns.textContent, /ساعات تماسِ ثبت‌شده در تنظیمات/);
  assert.doesNotMatch(columns.textContent, /پشتیبانی متنی ۲۴ ساعته/);
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  const socialRow = bottom.querySelector(".jluxe-footer-social-row");
  assert.match(socialRow.textContent, /راه‌های پشتیبانیِ ثبت‌شده در تنظیمات/);
  assert.match(socialRow.textContent, /ما را دنبال کنید/);
  assert.equal(columns.querySelectorAll(".jluxe-footer-support-card").length, 1);
  const features = document.querySelector('[data-jluxe-footer-slot="features"]');
  assert.equal(features.querySelector(".jluxe-footer-feature-grid"), null, "no feature claim is added until the merchant configures one");
  assert.doesNotMatch(document.querySelector("footer").textContent, /ارسال فوری|به سراسر ایران|ارسال سریع و مطمئن|۲۴ ساعته|کف قیمت بازار|پرداخت از درگاه مطمئن/);
  assert.equal(columns.querySelectorAll('a[href^="tel:"]').length, 2);
  assert.equal(columns.querySelector('a[href="tel:02112345678"]')?.dir, "ltr");
  assert.equal(columns.querySelector('a[href="tel:09121234567"]')?.dir, "ltr");

  const css = fs.readFileSync(
    new URL("../src/styles/storefront.css", import.meta.url),
    "utf8",
  );
  assert.match(css, /\.jluxe-footer-social-link\s*\{[^}]*width:\s*2\.75rem;[^}]*height:\s*2\.75rem;/s);
  assert.match(css, /\.jluxe-footer-nav-link:focus-visible/);
  assert.match(css, /\.jluxe-site-badge-card:focus-within/);
  assert.match(css, /#jluxe-footer-root \.jluxe-site-badge-card img\s*\{[^}]*width:\s*100%/s);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
});

test("R191 footer is compact, keeps phone numbers together, and merges configured support with follow links", async () => {
  const win = dom(shell(), {
    feature_cards_mobile_columns: 2,
    feature_cards: [
      { enabled: true, icon: "truck", title: "مزیتِ ثبت‌شده", subtitle: "توضیحِ واردشده توسط مدیر" },
    ],
    support_text: "راه‌های پشتیبانیِ ثبت‌شده در تنظیمات.",
  });
  win.JLuxeThemeSettings.contact = { phone: "09120000000", phone_secondary: "02100000000" };
  await render();

  const columns = document.querySelector('[data-jluxe-footer-slot="columns"]');
  const supportCards = columns.querySelectorAll(".jluxe-footer-support-card");
  assert.equal(supportCards.length, 1, "only the phone support block remains in the brand column");
  assert.equal(supportCards[0].querySelector("h3")?.textContent, "پشتیبانی تلفنی");
  assert.deepEqual(
    [...supportCards[0].querySelectorAll(".jluxe-footer-phone-link")].map((node) => node.textContent),
    ["09120000000", "02100000000"],
  );
  assert.doesNotMatch(supportCards[0].textContent, /پشتیبانی آنلاین/);

  const featureGrid = document.querySelector(
    '[data-jluxe-footer-slot="features"] .jluxe-footer-feature-grid',
  );
  assert.ok(featureGrid.classList.contains("jluxe-footer-feature-grid--mobile-two"));
  assert.equal(
    document.querySelectorAll("nav.jluxe-footer-link-column").length,
    2,
    "link groups expose separate navigation landmarks",
  );
  assert.deepEqual(
    [...document.querySelectorAll("nav.jluxe-footer-link-column")].map((nav) => nav.getAttribute("aria-label")),
    ["راهنما", "شرکت"],
  );

  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  const socialRow = bottom.querySelector(".jluxe-footer-social-row");
  assert.match(socialRow.textContent, /راه‌های پشتیبانیِ ثبت‌شده در تنظیمات/);
  assert.match(socialRow.textContent, /ما را دنبال کنید/);
  assert.equal(socialRow.querySelectorAll(".jluxe-footer-social-link").length, 1);

  const css = fs.readFileSync(
    new URL("../src/styles/storefront.css", import.meta.url),
    "utf8",
  );
  const r191 = css.slice(css.indexOf("/* R191 —"));
  assert.match(r191, /\.jluxe-footer-feature-grid\s*\{[^}]*display:\s*grid;/s);
  assert.match(r191, /\.jluxe-footer-content-grid\s*\{[^}]*display:\s*grid;/s);
  assert.match(r191, /grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/);
  assert.match(r191, /flex-wrap:\s*nowrap/);
  assert.match(r191, /overflow-wrap:\s*normal/);
  assert.match(r191, /word-break:\s*normal/);
  assert.match(r191, /@media \(min-width:\s*1024px\)/);
  assert.match(r191, /:not\(:has\(\.jluxe-site-badges-section\)\)/);
  assert.match(r191, /@media \(max-width:\s*359\.98px\)/);
  assert.match(r191, /prefers-reduced-motion:\s*reduce/);
  assert.match(r191, /background:\s*transparent/);
  assert.doesNotMatch(r191, /var\(--primary|var\(--accent|#[0-9a-f]{3,8}/i);
  assert.ok(!r191.includes("backdrop-filter"), "the footer uses flat, theme-colored surfaces");

  const php = fs.readFileSync(
    new URL("../inc/theme-settings.php", import.meta.url),
    "utf8",
  );
  const badgeRenderer = php.slice(
    php.indexOf("function jluxe_render_site_trust_badges"),
    php.indexOf("function jluxe_footer_background_style"),
  );
  assert.doesNotMatch(badgeRenderer, /var\(--primary\)|rgba\(0,\s*0,\s*0|translateY/);
});

test("R193 footer text, link, and hover colors apply to every configured interactive link", async () => {
  const win = dom(shell(), {
    text_color: "#123456",
    link_color: "#234567",
    link_hover_color: "#345678",
    support_text: "پشتیبانیِ ثبت‌شده در تنظیمات.",
  });
  win.JLuxeThemeSettings.contact = {
    phone: "021-12345678",
    phone_secondary: "09120000000",
  };
  win.JLuxeThemeSettings.social = {
    instagram: { enabled: true, url: "https://instagram.com/example" },
    telegram: { enabled: true, url: "https://t.me/example" },
  };
  await render();

  const footer = document.querySelector("#jluxe-footer-root");
  const configuredLinks = [...footer.querySelectorAll("[data-jluxe-footer-link]")];
  assert.ok(configuredLinks.length >= 6, "navigation, phone, and social links share the settings-backed color hook");
  for (const link of configuredLinks) {
    assert.equal(link.style.color, "rgb(35, 69, 103)");
  }
  const supportCopy = footer.querySelector(".jluxe-footer-social-support");
  assert.equal(supportCopy?.style.color, "rgb(18, 52, 86)");
  const followCopy = footer.querySelector(".jluxe-footer-social-follow");
  assert.equal(followCopy?.style.color, "rgb(18, 52, 86)");
  const hoverRule = footer.querySelector('[data-jluxe-footer-slot="bottom"] style')?.textContent;
  assert.match(hoverRule, /\[data-jluxe-footer-link\]:hover/);
  assert.match(hoverRule, /\[data-jluxe-footer-link\]:focus-visible/);
  assert.match(hoverRule, /\[data-jluxe-footer-link\]:active/);
  assert.match(hoverRule, /#345678!important/);
});

test("R193 the contact-page fallback link follows configured footer link colors", async () => {
  const win = dom(shell(), { link_color: "#234567", link_hover_color: "#345678" });
  win.JLuxeThemeSettings.contact = {};
  win.JLuxeThemeSettings.social = {};
  await render();

  const contactLink = document.querySelector(".jluxe-footer-contact-link");
  assert.equal(contactLink?.style.color, "rgb(35, 69, 103)");
  assert.equal(contactLink?.getAttribute("data-jluxe-footer-link"), "true");
  const hoverRule = document.querySelector('[data-jluxe-footer-slot="bottom"] style')?.textContent;
  assert.match(hoverRule, /\[data-jluxe-footer-link\]:focus-visible/);
});

test("R192 no social row is emitted when no social account or support copy is configured", async () => {
  const win = dom(shell());
  win.JLuxeThemeSettings.social = {};
  win.JLuxeThemeSettings.footer.support_text = "";
  await render();
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  assert.equal(bottom.querySelector(".jluxe-footer-social-row"), null);
});

test("R194 intentionally empty footer settings stay empty instead of restoring defaults", async () => {
  const win = dom(shell(), {
    brand_description: "",
    support_hours: "",
    support_text: "",
    feature_cards: [],
  });
  win.JLuxeThemeSettings.shortDescription = "";
  win.JLuxeThemeSettings.social = {};
  win.JLuxeThemeSettings.contact = {};
  await render();

  const footer = document.querySelector("#jluxe-footer-root");
  const columns = footer.querySelector('[data-jluxe-footer-slot="columns"]');
  assert.equal(columns.querySelector(".jluxe-footer-brand-description"), null, "an empty optional brand description leaves no blank paragraph gap");
  assert.equal(columns.querySelector(".jluxe-footer-support-card p"), null);
  assert.equal(columns.querySelector(".jluxe-footer-support-card h3")?.textContent, "راه‌های تماس");
  assert.equal(footer.querySelector('[data-jluxe-footer-slot="features"] .jluxe-footer-feature-grid'), null);
  assert.equal(footer.querySelector('[data-jluxe-footer-slot="bottom"] .jluxe-footer-social-row'), null);
  assert.doesNotMatch(footer.textContent, /JLuxe|ارسال سریع|۲۴ ساعته|کف قیمت بازار|درگاه مطمئن|روز ضمانت/);
});

test("R108 the footer logo portal has a scoped compact-image rule", async () => {
  const win = dom(shell());
  win.JLuxeThemeSettings.logoUrl = `${base}logo-desktop.svg`;
  win.JLuxeThemeSettings.mobileLogoUrl = `${base}logo-mobile.svg`;
  win.JLuxeThemeSettings.logoImage = {
    src: `${base}logo-desktop-320.webp`,
    srcset: `${base}logo-desktop-320.webp 320w`,
    sizes: "(max-width: 767px) 160px, 240px",
  };
  win.JLuxeThemeSettings.mobileLogoImage = {
    src: `${base}logo-mobile-320.webp`,
    srcset: `${base}logo-mobile-320.webp 320w`,
    sizes: "(max-width: 767px) 160px, 240px",
  };
  await render();

  const logo = document.querySelector(
    '[data-jluxe-footer-slot="columns"] a.jluxe-footer-logo',
  );
  assert.ok(logo, "the portal-mounted BrandLogo receives its footer-only class");
  assert.equal(logo.querySelectorAll("picture img").length, 1, "one responsive image serves both mobile and desktop logo variants");
  assert.equal(logo.querySelector("picture source")?.getAttribute("media"), "(min-width: 768px)");
  assert.equal(logo.querySelector("picture img")?.getAttribute("data-no-lazy"), "1");

  const css = fs.readFileSync(
    new URL("../src/styles/storefront.css", import.meta.url),
    "utf8",
  );
  assert.match(css, /\.jluxe-footer-logo img\s*\{[^}]*height:\s*3rem\s*!important/s);
});

test("R88 without badges the grid simply has no badge column", async () => {
  dom(shell(false));
  await render();
  assert.equal(document.querySelector(".jluxe-site-badges-section"), null);
  assert.ok(document.querySelector('[data-jluxe-footer-slot="columns"] h3'));
});

test("R88 an old child-theme footer.php (no slots) still gets the complete React footer", async () => {
  dom('<div id="island" data-jluxe-island="footer"></div>');
  await render();
  const footer = document.querySelector("#island > footer");
  assert.ok(footer, "legacy full footer rendered inside the island");
  assert.ok(footer.querySelector(".grid h3"));
  assert.match(footer.textContent, /© زرین/);
});

test("R88 a disabled footer renders nothing, slots or not", async () => {
  dom(shell(), { enabled: false });
  await render();
  assert.equal(document.querySelector('[data-jluxe-footer-slot="columns"]').childNodes.length, 0);
});

test("R88 the old staging/polling mover is gone from the PHP renderer", () => {
  const php = fs.readFileSync(new URL("../inc/theme-settings.php", import.meta.url), "utf8");
  const fn = php.slice(
    php.indexOf("function jluxe_render_site_trust_badges"),
    php.indexOf("function jluxe_footer_background_style"),
  );
  const code = fn.replace(/\/\*[\s\S]*?\*\//g, "");
  for (const banned of ["jluxe-site-badges-staging", "setInterval", "MutationObserver", "appendChild", "<script"])
    assert.ok(!code.includes(banned), `renderer no longer contains ${banned}`);
  const footer = fs.readFileSync(new URL("../footer.php", import.meta.url), "utf8");
  const grid = footer.indexOf('class="jluxe-footer-content-grid grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4"');
  assert.ok(grid > 0 && footer.indexOf("jluxe_render_site_trust_badges()", grid) > grid, "badges are printed inside the server grid");
  assert.ok(footer.indexOf('data-jluxe-footer-slot="columns"', grid) > grid);
});
