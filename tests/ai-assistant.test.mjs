import test from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";

test("R96 mobile launcher stays visible and the in-chat support form wins over an old help URL", async (t) => {
  const dom = new JSDOM(
    '<!doctype html><html dir="rtl"><head><title>گردنبند</title></head><body class="single-product postid-44"><section id="bottom-navigation"></section><div id="mobile-price-bar" data-jluxe-mobile-price-bar></div><a id="open-ai" href="/contact/#open-ai-assistant">گفتگو با ما</a><div id="app"></div></body></html>',
    { url: "https://shop.test/product/ring/?order_key=private#secret", pretendToBeVisual: true },
  );
  const previous = {
    window: globalThis.window,
    document: globalThis.document,
    Element: globalThis.Element,
    fetch: globalThis.fetch,
    actFlag: globalThis.IS_REACT_ACT_ENVIRONMENT,
  };
  Object.defineProperty(dom.window, "innerWidth", { configurable: true, writable: true, value: 390 });
  Object.defineProperty(dom.window, "innerHeight", { configurable: true, writable: true, value: 800 });
  const visualViewportListeners = new Map();
  const visualViewport = {
    width: 390,
    height: 760,
    offsetTop: 0,
    addEventListener(type, listener) {
      if (!visualViewportListeners.has(type)) visualViewportListeners.set(type, new Set());
      visualViewportListeners.get(type).add(listener);
    },
    removeEventListener(type, listener) {
      visualViewportListeners.get(type)?.delete(listener);
    },
    dispatch(type) {
      visualViewportListeners.get(type)?.forEach((listener) => listener());
    },
  };
  Object.defineProperty(dom.window, "visualViewport", { configurable: true, value: visualViewport });
  dom.window.document.title = "x".repeat(150);
  dom.window.document.getElementById("bottom-navigation").getBoundingClientRect = () => ({
    top: 714,
    right: 366,
    bottom: 790,
    left: 12,
    width: 354,
    height: 76,
    x: 12,
    y: 714,
    toJSON() { return this; },
  });
  dom.window.document.getElementById("mobile-price-bar").getBoundingClientRect = () => ({
    top: 690,
    right: 390,
    bottom: 790,
    left: 0,
    width: 390,
    height: 100,
    x: 0,
    y: 690,
    toJSON() { return this; },
  });
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
    if (url.includes("/assistant/stream")) {
      const body = JSON.parse(options.body || "{}");
      assert.equal(options.headers.Accept, "text/event-stream", "the chat negotiates an SSE response");
      assert.equal(body.page.productId, 44, "the current product context reaches the server");
      assert.equal(body.page.url, "https://shop.test/product/ring/", "order-key query parameters and fragments are not forwarded to the AI provider");
      assert.equal(body.page.title.length, 120, "page context title stays within the server schema limit");
      assert.equal(body.messages.at(-1).content, "معرفی محصول", "chat requests send the bounded latest message text");
      const splitAt = responseBody.reply.indexOf("**گردنبند نقره**");
      const frames = [
        "event: start\r\n\r\n",
        `event: delta\r\ndata: ${JSON.stringify({ text: responseBody.reply.slice(0, splitAt) })}\r\n\r\n`,
        `event: delta\ndata: ${JSON.stringify({ text: responseBody.reply.slice(splitAt) })}\n\n`,
        `event: done\ndata: ${JSON.stringify(responseBody)}\n\n`,
      ];
      const encoded = frames.map((frame) => new TextEncoder().encode(frame));
      return {
        ok: true,
        status: 200,
        headers: { get: (name) => name.toLowerCase() === "content-type" ? "text/event-stream; charset=utf-8" : null },
        body: new ReadableStream({
          start(controller) {
            encoded.forEach((chunk) => controller.enqueue(chunk));
            controller.close();
          },
        }),
      };
    }
    if (url.endsWith("/assistant")) {
      const body = JSON.parse(options.body || "{}");
      assert.equal(body.messages.at(-1).content, "معرفی محصول");
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
      quickReplies: ["معرفی محصول", "راهنمای انتخاب محصول بر اساس نیاز و بودجه", "پیگیری وضعیت سفارش"],
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
  assert.ok(mount.querySelector(".jluxe-ai-root.is-product"), "single-product pages receive the refined product launcher treatment");
  assert.equal(mount.querySelector(".jluxe-ai-root").style.getPropertyValue("--aia-primary"), "#2f7468", "the assistant has a calm teal default accent when no admin color is configured");
  assert.equal(mount.querySelector(".jluxe-ai-launcher-label")?.textContent, "راهنمای خرید");
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "the product launcher clears the measured price-and-add bar with a 12px gap");

  const priceBar = document.getElementById("mobile-price-bar");
  await act(async () => {
    priceBar.classList.add("hidden");
    await new Promise((resolve) => setTimeout(resolve, 40));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "98px", "when the price bar is toggled away, the launcher moves above the measured mobile nav instead");
  await act(async () => {
    priceBar.classList.remove("hidden");
    await new Promise((resolve) => setTimeout(resolve, 40));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "the launcher returns smoothly above the price bar when it becomes visible again");

  let stickyRect = { top: 820, right: 390, bottom: 900, left: 0, width: 390, height: 80 };
  const stickyCta = document.createElement("div");
  stickyCta.setAttribute("data-jluxe-sticky-cta", "");
  stickyCta.className = "jluxe-sticky-cta";
  stickyCta.getBoundingClientRect = () => ({ ...stickyRect, x: stickyRect.left, y: stickyRect.top, toJSON() { return this; } });
  document.body.appendChild(stickyCta);
  await act(async () => {
    priceBar.classList.add("hidden");
    stickyCta.classList.add("is-visible");
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "98px", "a newly visible CTA is ignored only while its entrance transform keeps it off-screen");
  await act(async () => {
    stickyRect = { top: 650, right: 390, bottom: 730, left: 0, width: 390, height: 80 };
    stickyCta.dispatchEvent(new dom.window.Event("transitionend", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "162px", "after the CTA transition settles, the launcher clears its real top edge with a 12px gap");
  await act(async () => {
    stickyRect = { top: 820, right: 390, bottom: 900, left: 0, width: 390, height: 80 };
    stickyCta.classList.remove("is-visible");
    stickyCta.dispatchEvent(new dom.window.Event("transitionend", { bubbles: true }));
    await new Promise((resolve) => setTimeout(resolve, 35));
    stickyCta.remove();
    priceBar.classList.remove("hidden");
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "hiding the CTA restores the mobile price-bar clearance without leaving a stale offset");

  let mobileBuyBoxRect = { top: 600, right: 390, bottom: 790, left: 0, width: 390, height: 190 };
  const mobileBuyBox = document.createElement("aside");
  mobileBuyBox.setAttribute("data-jluxe-product-buybox", "");
  mobileBuyBox.getBoundingClientRect = () => ({ ...mobileBuyBoxRect, x: mobileBuyBoxRect.left, y: mobileBuyBoxRect.top, toJSON() { return this; } });
  document.body.appendChild(mobileBuyBox);
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "the launcher keeps its dock clearance instead of tracking the product purchase box");
  await act(async () => {
    mobileBuyBoxRect = { top: 500, right: 390, bottom: 650, left: 0, width: 390, height: 150 };
    dom.window.dispatchEvent(new dom.window.Event("scroll"));
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "scrolling the product purchase box does not pull the launcher toward the middle of the screen");
  await act(async () => {
    mobileBuyBoxRect = { top: -200, right: 390, bottom: -20, left: 0, width: 390, height: 180 };
    dom.window.dispatchEvent(new dom.window.Event("scroll"));
    await new Promise((resolve) => setTimeout(resolve, 35));
    mobileBuyBox.remove();
    await new Promise((resolve) => setTimeout(resolve, 20));
  });
  assert.equal(mount.querySelector(".jluxe-ai-root").style.bottom, "122px", "the launcher returns to its normal dock clearance after the purchase box scrolls away");

  const trigger = document.getElementById("open-ai");
  const click = new dom.window.MouseEvent("click", { bubbles: true, cancelable: true });
  await act(async () => {
    trigger.dispatchEvent(click);
    await new Promise((resolve) => setTimeout(resolve, 90));
  });
  assert.equal(click.defaultPrevented, true, "#open-ai-assistant clicks are intercepted without navigation");
  assert.ok(mount.querySelector('[role="dialog"][aria-modal="true"]'), "a hash link also opens the assistant");
  assert.ok(mount.querySelector(".jluxe-ai-root.is-mobile.is-open .jluxe-ai-backdrop"), "mobile opens as a focused, dismissible app-like sheet");
  assert.match(mount.querySelector(".jluxe-ai-window").style.width, /100vw/);
  assert.match(mount.querySelector(".jluxe-ai-window").style.height, /100dvh/);
  const mobileRoot = mount.querySelector(".jluxe-ai-root");
  assert.equal(mobileRoot.style.getPropertyValue("--aia-visual-viewport-height"), "760px", "the open sheet follows the visible mobile viewport rather than only the layout viewport");
  assert.equal(mobileRoot.style.getPropertyValue("--aia-quick-replies-max-height"), "152px", "suggestion space is sized to the available viewport");
  assert.ok(mount.querySelector(".jluxe-ai-window").classList.contains("is-mobile"));
  assert.notEqual(document.activeElement, mount.querySelector(".jluxe-ai-chat-input"), "opening the assistant does not force the mobile keyboard to appear");
  const quickReplyGroup = mount.querySelector('.jluxe-ai-quick-replies[role="group"]');
  assert.equal(quickReplyGroup?.getAttribute("aria-label"), "پیشنهادهای شروع گفتگو");
  assert.equal(quickReplyGroup?.querySelectorAll(".jluxe-ai-quick-reply").length, 3, "long quick prompts remain separate, readable buttons");
  assert.ok(mount.querySelector(".jluxe-ai-welcome"), "the opening message is presented as a styled welcome card");
  assert.equal(mount.querySelector(".jluxe-ai-chat-input").maxLength, 1000, "chat input matches the server-side message bound");

  visualViewport.height = 420;
  visualViewport.offsetTop = 24;
  await act(async () => {
    visualViewport.dispatch("resize");
    visualViewport.dispatch("scroll");
    await new Promise((resolve) => setTimeout(resolve, 30));
  });
  assert.equal(mobileRoot.style.getPropertyValue("--aia-visual-viewport-height"), "420px", "opening the on-screen keyboard resizes the assistant sheet to the visible viewport");
  assert.equal(mobileRoot.style.getPropertyValue("--aia-visual-viewport-offset-top"), "24px", "visual viewport panning keeps the sheet aligned with the visible area");
  assert.equal(mobileRoot.style.getPropertyValue("--aia-quick-replies-max-height"), "84px", "quick replies compact when the keyboard reduces available height");

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
  const ticketForm = mount.querySelector(".jluxe-ai-ticket-form");
  assert.ok(ticketForm, "the contact action opens the in-chat ticket form");
  assert.equal(ticketForm.querySelector('input[placeholder="نام شما"]').maxLength, 80, "ticket name has the server-matching length limit");
  assert.equal(ticketForm.querySelector('input[placeholder="ایمیل یا شماره تماس"]').maxLength, 254, "ticket contact has the server-matching length limit");
  assert.equal(ticketForm.querySelector("textarea").maxLength, 2000, "ticket message has the server-matching length limit");
  await act(async () => {
    mount.querySelector(".jluxe-ai-back-button").click();
    await Promise.resolve();
  });

  await act(async () => {
    [...mount.querySelectorAll("button")].find((button) => button.textContent === "معرفی محصول").click();
    await new Promise((resolve) => setTimeout(resolve, 0));
  });
  assert.equal(calls.some((call) => call.url.endsWith("/assistant/stream")), true, "quick reply requests the streaming assistant route");
  assert.equal(calls.some((call) => call.url.endsWith("/assistant")), false, "successful stream requests do not call the legacy JSON route");
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

  await act(async () => {
    mount.querySelector('button[aria-label="بستن گفتگو"]').click();
    await Promise.resolve();
  });
  assert.ok(mount.querySelector(".jluxe-ai-root.is-closing .jluxe-ai-window"), "closing plays the exit transition before unmounting the chat");
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 200));
  });
  assert.equal(mount.querySelector(".jluxe-ai-window"), null, "the panel unmounts after the close animation completes");
  Object.defineProperty(dom.window, "innerWidth", { configurable: true, writable: true, value: 1024 });
  await act(async () => {
    dom.window.dispatchEvent(new dom.window.Event("resize"));
    await new Promise((resolve) => setTimeout(resolve, 25));
  });
  const desktopRoot = mount.querySelector(".jluxe-ai-root");
  const desktopLauncher = mount.querySelector(".jluxe-ai-launcher");
  desktopLauncher.getBoundingClientRect = () => ({
    top: 722, right: 78, bottom: 778, left: 22, width: 56, height: 56, x: 22, y: 722,
    toJSON() { return this; },
  });
  const buyBox = document.createElement("aside");
  buyBox.setAttribute("data-jluxe-product-buybox", "");
  buyBox.getBoundingClientRect = () => ({
    top: 80, right: 270, bottom: 790, left: 0, width: 270, height: 710, x: 0, y: 80,
    toJSON() { return this; },
  });
  document.body.appendChild(buyBox);
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 35));
  });
  assert.ok(desktopRoot.classList.contains("is-product") && desktopRoot.classList.contains("is-desktop"), "product context is retained on desktop, not only mobile");
  assert.equal(desktopRoot.style.insetInlineStart, "", "the launcher does not switch sides to avoid a product buy box");
  assert.equal(desktopRoot.style.insetInlineEnd, "22px", "on RTL desktop the launcher stays at its configured logical corner");
  await act(async () => root.unmount());
});
