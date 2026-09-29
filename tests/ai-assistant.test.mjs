import test from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";

test("R96 mobile launcher stays visible and the in-chat support form wins over an old help URL", async (t) => {
  const dom = new JSDOM(
    '<!doctype html><html><head><title>گردنبند</title></head><body class="single-product postid-44"><a id="open-ai" href="/contact/#open-ai-assistant">گفتگو با ما</a><div id="app"></div></body></html>',
    { url: "https://shop.test/product/ring/", pretendToBeVisual: true },
  );
  const previous = {
    window: globalThis.window,
    document: globalThis.document,
    Element: globalThis.Element,
    fetch: globalThis.fetch,
    actFlag: globalThis.IS_REACT_ACT_ENVIRONMENT,
  };
  Object.defineProperty(dom.window, "innerWidth", { configurable: true, writable: true, value: 390 });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;

  const responseBody = {
    reply: [
      "راهنمای خرید این گردنبند:",
      "[تماس](tel:+989120902336)",
      "[واتساپ](https://wa.me/989120902336)",
      "",
      "**گردنبند نقره**",
      "![گردنبند نقره](https://shop.test/wp-content/uploads/ring.webp)",
      "قیمت: ۱۰۰٬۰۰۰ تومان — موجود",
      "[مشاهده و خرید](https://shop.test/product/ring/)",
      "[راهنما](https://shop.test/shopping-guide/)",
      "[لینک ناامن](https://evil.example/phishing)",
      "[شماره جعلی](tel:+989000000000)",
    ].join("\n"),
    products: [
      {
        id: 44,
        name: "گردنبند نقره",
        url: "https://shop.test/product/ring/",
        image: "https://shop.test/wp-content/uploads/ring.webp",
        price: "۱۰۰٬۰۰۰ تومان",
        inStock: true,
      },
    ],
  };
  const calls = [];
  const fetchMock = async (input, options = {}) => {
    const url = String(input);
    calls.push({ url, options });
    if (url.includes("admin-ajax.php")) {
      return { ok: true, status: 200, json: async () => ({ success: true, data: { restNonce: "test", auth: {} } }) };
    }
    if (url.includes("/assistant")) {
      const body = JSON.parse(options.body || "{}");
      assert.equal(body.page.productId, 44, "the current product context reaches the server");
      return { ok: true, status: 200, json: async () => responseBody };
    }
    throw new Error(`Unexpected network request: ${url}`);
  };
  globalThis.fetch = fetchMock;
  dom.window.fetch = fetchMock;
  dom.window.JLuxeThemeSettings = {
    urls: { home: "https://shop.test/" },
    rest: {
      root: "https://shop.test/wp-json/jluxe/v1/",
      sessionUrl: "https://shop.test/wp-admin/admin-ajax.php",
    },
    auth: {},
    aiAssistant: {
      enabled: true,
      name: "دستیار زرین",
      welcomeMessage: "سلام!",
      showDesktop: true,
      showMobile: true,
      hideMobileLauncher: false,
      mobileBreakpoint: 820,
      position: "end",
      offsetBottomMobile: 80,
      offsetSideMobile: 12,
      offsetBottomDesktop: 22,
      offsetSideDesktop: 22,
      windowWidth: 380,
      borderRadius: 16,
      quickReplies: ["معرفی محصول"],
      handoffFormUrl: "https://shop.test/shopping-guide/",
      enableTicketForm: true,
      contact: {
        phone: "09120902336",
        tel: "tel:+989120902336",
        timezone: "Asia/Tehran",
        hours: { enabled: true, start: "10:00", end: "20:00", closedDays: [5] },
        openText: "پاسخگوی تلفنی هستیم.",
        closedText: "خارج از ساعت پاسخگویی هستیم.",
        icons: {},
        channels: [
          { key: "phone", label: "تماس", url: "tel:+989120902336" },
          { key: "whatsapp", label: "واتساپ", url: "https://wa.me/989120902336" },
        ],
      },
      widgets: {},
    },
  };

  t.after(() => {
    dom.window.close();
    for (const [key, value] of Object.entries(previous)) {
      if (value === undefined) delete globalThis[key];
      else globalThis[key] = value;
    }
  });

  const React = await import("react");
  const { act } = React;
  const { createRoot } = await import("react-dom/client");
  const { default: Assistant } = await import("../src/islands/AiAssistant.js");
  const mount = document.getElementById("app");
  const root = createRoot(mount);

  await act(async () => {
    root.render(React.createElement(Assistant));
    await Promise.resolve();
  });
  assert.ok(mount.querySelector(".jluxe-ai-launcher"), "mobile launcher is visible by default");

  const trigger = document.getElementById("open-ai");
  const click = new dom.window.MouseEvent("click", { bubbles: true, cancelable: true });
  await act(async () => {
    trigger.dispatchEvent(click);
    await Promise.resolve();
  });
  assert.equal(click.defaultPrevented, true, "#open-ai-assistant clicks are intercepted without navigation");
  assert.ok(mount.querySelector('[role="dialog"][aria-modal="true"]'), "a hash link also opens the assistant");
  assert.ok(mount.querySelector(".jluxe-ai-root.is-mobile.is-open .jluxe-ai-backdrop"), "mobile opens as a focused, dismissible app-like sheet");
  assert.match(mount.querySelector(".jluxe-ai-window").style.width, /100vw/);
  assert.match(mount.querySelector(".jluxe-ai-window").style.height, /100dvh/);
  assert.ok(mount.querySelector(".jluxe-ai-welcome"), "the opening message is presented as a styled welcome card");

  await act(async () => {
    mount.querySelector('button[aria-label="ارتباط با پشتیبانی انسانی"]').click();
    await Promise.resolve();
  });
  const handoffMenu = mount.querySelector(".jluxe-ai-handoff-menu");
  assert.ok(handoffMenu, "support actions are available from the chat header");
  assert.equal(handoffMenu.querySelector('a[href="https://shop.test/shopping-guide/"]'), null, "a stale guide URL is not exposed as the contact-form link");
  const ticketButton = [...handoffMenu.querySelectorAll("button")].find((button) => button.textContent === "فرم تماس با پشتیبانی");
  assert.ok(ticketButton, "the header offers the built-in support form");
  await act(async () => {
    ticketButton.click();
    await Promise.resolve();
  });
  assert.ok(mount.querySelector(".jluxe-ai-ticket-form"), "the contact action opens the in-chat ticket form");
  await act(async () => {
    mount.querySelector(".jluxe-ai-back-button").click();
    await Promise.resolve();
  });

  await act(async () => {
    [...mount.querySelectorAll("button")].find((button) => button.textContent === "معرفی محصول").click();
    await new Promise((resolve) => setTimeout(resolve, 0));
  });
  assert.equal(calls.some((call) => call.url.includes("/assistant")), true, "quick reply sends a real assistant request");
  assert.equal(mount.querySelectorAll(".jluxe-ai-contact-row .jluxe-ai-icon-link").length, 2, "phone and WhatsApp links become icon buttons");
  assert.ok(mount.querySelector(".jluxe-ai-hours-notice"), "phone hours notice appears with contact links");
  assert.ok(mount.querySelector(".jluxe-ai-hours-meta"), "the status card displays configured response days and hours");
  assert.match(mount.querySelector(".jluxe-ai-hours-meta").textContent, /شنبه تا پنجشنبه/);
  assert.ok(mount.querySelector(".jluxe-ai-buy-btn"), "a real product link is styled as a buy CTA");
  assert.ok(mount.querySelector(".jluxe-ai-link-btn"), "same-site helpful links remain styled and usable");
  assert.equal(mount.querySelector('a[href^="https://evil.example"]'), null, "an unconfigured external link is not made clickable");
  assert.equal(mount.querySelector('a[href="tel:+989000000000"]'), null, "a fabricated phone number is not turned into a contact button");

  await act(async () => {
    mount.querySelector(".jluxe-ai-product-image-link").click();
    await Promise.resolve();
  });
  assert.ok(mount.querySelector(".jluxe-ai-lightbox[role=dialog]"), "product image opens in an accessible lightbox");
  await act(async () => {
    mount.querySelector(".jluxe-ai-lightbox-close").click();
    await Promise.resolve();
  });
  assert.equal(mount.querySelector(".jluxe-ai-lightbox"), null, "lightbox close button dismisses the image");
  await act(async () => root.unmount());
});
