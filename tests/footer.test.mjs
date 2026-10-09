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
    columns.querySelector("a[data-jluxe-footer-link]").getAttribute("href"),
    base + "order-status/",
    "R87 mapping still applies inside the portal",
  );
  assert.equal(document.querySelectorAll("footer").length, 1, "no second <footer> is created");
});

test("R189 footer copy, configured phone links, feature claims, and touch/focus styling stay clear", async () => {
  const win = dom(shell(), {
    brand_description:
      "JLuxe | هنرِ انتخاب برای خانه‌های لوکس. معرفی جدا از توضیح کوتاه سایت.",
    support_hours: "در روزهای کاری، از ساعت ۹ صبح تا ۸ شب پاسخ‌گوی تماس شما هستیم.",
    support_text: "پشتیبانی متنی ۲۴ ساعته از طریق شبکه‌های اجتماعی.",
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
    /JLuxe \| هنرِ انتخاب/,
  );
  assert.doesNotMatch(
    columns.querySelector(".jluxe-footer-brand-description").textContent,
    /tagline عمومی/,
  );
  assert.match(columns.textContent, /در روزهای کاری، از ساعت ۹ صبح تا ۸ شب/);
  assert.doesNotMatch(columns.textContent, /پشتیبانی متنی ۲۴ ساعته/);
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  const socialRow = bottom.querySelector(".jluxe-footer-social-row");
  assert.match(socialRow.textContent, /پشتیبانی متنی ۲۴ ساعته از طریق شبکه‌های اجتماعی/);
  assert.match(socialRow.textContent, /ما را دنبال کنید/);
  assert.equal(columns.querySelectorAll(".jluxe-footer-support-card").length, 1);
  const features = document.querySelector('[data-jluxe-footer-slot="features"]');
  assert.match(features.textContent, /ارسال سریع و مطمئن/);
  assert.match(features.textContent, /به سراسر ایران/);
  assert.match(features.textContent, /کف قیمت بازار/);
  assert.match(features.textContent, /پرداخت از درگاه مطمئن/);
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
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
});

test("R191 footer is compact, keeps phone numbers together, and merges social support with follow links", async () => {
  const win = dom(shell(), { feature_cards_mobile_columns: 2 });
  win.JLuxeThemeSettings.footer.support_text =
    "پشتیبانی متنی ۲۴ ساعته از طریق شبکه‌های اجتماعی.";
  await render();

  const columns = document.querySelector('[data-jluxe-footer-slot="columns"]');
  const supportCards = columns.querySelectorAll(".jluxe-footer-support-card");
  assert.equal(supportCards.length, 1, "only the phone support block remains in the brand column");
  assert.equal(supportCards[0].querySelector("h3")?.textContent, "پشتیبانی تلفنی");
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
  assert.match(socialRow.textContent, /پشتیبانی متنی ۲۴ ساعته از طریق شبکه‌های اجتماعی/);
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

test("R191 social support text remains available when no social account is configured", async () => {
  const win = dom(shell());
  win.JLuxeThemeSettings.social = {};
  await render();
  const bottom = document.querySelector('[data-jluxe-footer-slot="bottom"]');
  const row = bottom.querySelector(".jluxe-footer-social-row");
  assert.match(row.textContent, /پشتیبانی متنی ۲۴ ساعته از طریق شبکه‌های اجتماعی/);
  assert.doesNotMatch(row.textContent, /ما را دنبال کنید/);
  assert.equal(row.querySelectorAll(".jluxe-footer-social-link").length, 0);
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
