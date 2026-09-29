import test, { before, after } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { spawnSync } from "node:child_process";
import React, { act } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createServer } from "vite";
import { JSDOM } from "jsdom";
import jquery from "jquery";

let server, AuthPage, resolvePostAuthUrl;
before(async () => {
  // Transform the actual JSX source, with no HTTP listener and no HMR server.
  server = await createServer({
    configFile: false,
    appType: "custom",
    server: { middlewareMode: true, hmr: false, watch: null },
  });
  ({ default: AuthPage, resolvePostAuthUrl } = await server.ssrLoadModule(
    "/src/islands/AuthPage.jsx",
  ));
});
after(async () => {
  await server?.close();
  delete globalThis.window;
});

function authMarkup({ sms = false, registration = false } = {}) {
  globalThis.window = {
    location: { href: "https://shop.test/store/customer-zone/" },
    JLuxeThemeSettings: {
      sms: { enabled: sms },
      auth: { registrationEnabled: registration },
      urls: {
        home: "https://shop.test/store/",
        lost_password: "https://shop.test/store/customer-zone/recover/",
      },
    },
  };
  return renderToStaticMarkup(React.createElement(AuthPage));
}

test("R08/R09 guest auth exposes the actual native recovery URL and labeled inputs", () => {
  const html = authMarkup();
  assert.match(
    html,
    /href="https:\/\/shop.test\/store\/customer-zone\/recover\/"/,
  );
  assert.match(html, /autoComplete="current-password"/i);
  assert.match(html, /for="jluxe-auth-login"/);
});

test("R13 disabled registration hides the registration control", () => {
  assert.doesNotMatch(authMarkup(), /ثبت‌نام/);
  assert.match(authMarkup({ registration: true }), /ثبت‌نام/);
});

test("R97 OTP UI supports SMS-first signup and automatic six-digit verification", () => {
  assert.doesNotMatch(authMarkup(), /id="jluxe-auth-phone"/);
  const html = authMarkup({ sms: true, registration: false });
  assert.match(html, /id="jluxe-auth-phone"/);
  assert.match(html, /type="tel"/);
  assert.match(
    fs.readFileSync(new URL("../src/islands/AuthPage.jsx", import.meta.url), "utf8"),
    /autoComplete="one-time-code"/,
  );
  assert.match(html, /کد درست/);
  assert.doesNotMatch(html, /ورود پیامکی فقط برای حساب‌های موجود/);
});

test("R97 post-login redirect keeps the same-site checkout but rejects external or out-of-install URLs", () => {
  const settings = {
    urls: {
      home: "https://shop.test/store/",
      dashboard: "https://shop.test/store/customer-zone/",
    },
  };
  globalThis.window = {
    location: {
      href: "https://shop.test/store/customer-zone/?redirect_to=%2Fstore%2Fcheckout%2F",
      search: "?redirect_to=%2Fstore%2Fcheckout%2F",
    },
    JLuxeThemeSettings: settings,
  };
  assert.equal(
    resolvePostAuthUrl(),
    "https://shop.test/store/checkout/",
    "a local checkout return target preserves the purchase flow",
  );
  globalThis.window.location.search =
    "?redirect_to=https%3A%2F%2Fevil.test%2Fcollect";
  assert.equal(
    resolvePostAuthUrl(),
    settings.urls.dashboard,
    "an external redirect target falls back to the account page",
  );
  globalThis.window.location.search = "?redirect=%2Fevil%2Fpath";
  assert.equal(
    resolvePostAuthUrl(),
    settings.urls.dashboard,
    "same-origin paths outside the WordPress installation are rejected",
  );
});

test("R97 WebOTP and autofilled six-digit input verify automatically; a rejected code can be retried", async () => {
  const dom = new JSDOM('<!doctype html><div id="root"></div>', {
    url: "https://shop.test/store/customer-zone/?redirect_to=%2Fstore%2Fcheckout%2F",
    pretendToBeVisual: true,
  });
  const targetWindow = dom.window;
  let redirectedTo = "";
  const location = {
    href: targetWindow.location.href,
    origin: targetWindow.location.origin,
    protocol: targetWindow.location.protocol,
    host: targetWindow.location.host,
    hostname: targetWindow.location.hostname,
    port: targetWindow.location.port,
    pathname: targetWindow.location.pathname,
    search: targetWindow.location.search,
    hash: targetWindow.location.hash,
    assign: (url) => (redirectedTo = String(url)),
  };
  let resolveWebOtp;
  const credentialRequests = [];
  const credentials = {
    get(options) {
      credentialRequests.push(options);
      return new Promise((resolve) => {
        resolveWebOtp = resolve;
      });
    },
  };
  const navigator = new Proxy(targetWindow.navigator, {
    get(target, property) {
      if (property === "credentials") return credentials;
      return Reflect.get(target, property, target);
    },
  });
  const browserWindow = new Proxy(targetWindow, {
    get(target, property) {
      if (property === "location") return location;
      if (property === "navigator") return navigator;
      if (property === "isSecureContext") return true;
      return Reflect.get(target, property, target);
    },
    has(target, property) {
      return property === "OTPCredential" || Reflect.has(target, property);
    },
  });
  targetWindow.JLuxeThemeSettings = {
    sms: { enabled: true },
    auth: { registrationEnabled: false, otpOnly: false },
    rest: { root: "https://shop.test/wp-json/jluxe/v1/" },
    urls: {
      home: "https://shop.test/store/",
      dashboard: "https://shop.test/store/customer-zone/",
      lost_password: "https://shop.test/store/customer-zone/recover/",
    },
  };

  const names = [
    "window",
    "document",
    "navigator",
    "HTMLElement",
    "HTMLInputElement",
    "Node",
    "Event",
    "IS_REACT_ACT_ENVIRONMENT",
    "fetch",
  ];
  const descriptors = new Map(
    names.map((name) => [name, Object.getOwnPropertyDescriptor(globalThis, name)]),
  );
  const requests = [];
  globalThis.window = browserWindow;
  globalThis.document = targetWindow.document;
  Object.defineProperty(globalThis, "navigator", {
    configurable: true,
    enumerable: true,
    writable: true,
    value: navigator,
  });
  globalThis.HTMLElement = targetWindow.HTMLElement;
  globalThis.HTMLInputElement = targetWindow.HTMLInputElement;
  globalThis.Node = targetWindow.Node;
  globalThis.Event = targetWindow.Event;
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  globalThis.fetch = async (url, init) => {
    const body = JSON.parse(init.body);
    const path = new URL(url).pathname;
    requests.push({ path, body });
    if (path.endsWith("/auth/otp-request")) {
      return { ok: true, status: 200, json: async () => ({ sent: true }) };
    }
    if (body.code === "111111") {
      return {
        ok: false,
        status: 400,
        json: async () => ({ message: "کد وارد شده اشتباه است." }),
      };
    }
    return { ok: true, status: 200, json: async () => ({ success: true }) };
  };

  const { createRoot } = await import("react-dom/client");
  const root = createRoot(targetWindow.document.getElementById("root"));
  const changeValue = (input, value) => {
    Object.getOwnPropertyDescriptor(
      targetWindow.HTMLInputElement.prototype,
      "value",
    ).set.call(input, value);
    input.dispatchEvent(new targetWindow.Event("input", { bubbles: true }));
  };
  try {
    await act(async () => root.render(React.createElement(AuthPage)));
    const phone = targetWindow.document.getElementById("jluxe-auth-phone");
    const form = targetWindow.document.querySelector("form");
    await act(async () => {
      changeValue(phone, "09120000000");
      form.dispatchEvent(
        new targetWindow.Event("submit", { bubbles: true, cancelable: true }),
      );
    });
    assert.equal(requests[0].path.endsWith("/auth/otp-request"), true);
    assert.equal(credentialRequests.length, 1);
    assert.deepEqual(credentialRequests[0].otp.transport, ["sms"]);

    await act(async () => {
      resolveWebOtp({ code: "111111" });
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
    assert.equal(requests[1].body.code, "111111");
    assert.match(
      targetWindow.document.querySelector('[role="alert"]').textContent,
      /کد وارد شده اشتباه است/,
    );
    const codeInput = targetWindow.document.getElementById("jluxe-auth-code");
    assert.equal(codeInput.value, "", "wrong OTP is cleared for a fresh attempt");
    assert.equal(redirectedTo, "", "wrong OTP never redirects or authenticates");

    await act(async () => {
      changeValue(codeInput, "123456");
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
    assert.equal(requests[2].body.code, "123456");
    assert.equal(
      redirectedTo,
      "https://shop.test/store/checkout/",
      targetWindow.document.querySelector('[role="alert"]')?.textContent ?? "no auth error rendered",
    );
  } finally {
    await act(async () => root.unmount());
    targetWindow.close();
    for (const [name, descriptor] of descriptors) {
      if (descriptor) Object.defineProperty(globalThis, name, descriptor);
      else delete globalThis[name];
    }
  }
});

test("R19 actual classic WooCommerce script accepts AJAX-mode variation JSON false", () => {
  const dom = new JSDOM(
    '<!doctype html><html><body><form class="variations_form" data-product_variations="false"><input class="qty" type="number" value="1"><input name="variation_id" value="0"></form></body></html>',
    {
      url: "https://shop.test/store/product/test/",
      pretendToBeVisual: true,
      runScripts: "outside-only",
    },
  );
  const window = dom.window;
  window.jQuery = jquery(window);
  window.JLuxeThemeSettings = {
    urls: { home: "https://shop.test/store/" },
    shopUrl: "https://shop.test/store/catalog/",
  };
  window.matchMedia = () => ({
    matches: false,
    addEventListener() {},
    removeEventListener() {},
  });
  try {
    window.eval(
      fs.readFileSync(
        new URL("../assets/js/storefront-utils.js", import.meta.url),
        "utf8",
      ),
    );
    assert.doesNotThrow(() =>
      window.eval(
        fs.readFileSync(
          new URL("../assets/js/woocommerce.js", import.meta.url),
          "utf8",
        ),
      ),
    );
  } finally {
    window.close();
  }
});

test("Every maintained classic JavaScript file parses in Node", () => {
  const folder = new URL("../assets/js/", import.meta.url);
  for (const name of fs
    .readdirSync(folder)
    .filter((name) => name.endsWith(".js"))) {
    const result = spawnSync(
      process.execPath,
      ["--check", new URL(name, folder).pathname],
      { encoding: "utf8" },
    );
    assert.equal(result.status, 0, `${name}: ${result.stderr}`);
  }
});

test("Modal focus moves in, wraps Tab, handles Escape, and returns to the trigger", async () => {
  const { activateDialog } = await import("../src/lib/input.js");
  const dom = new JSDOM(
    '<button id="trigger">Open</button><div id="dialog" role="dialog" aria-modal="true"><button id="first">First</button><input id="last"><button hidden>Hidden</button></div>',
    { pretendToBeVisual: true },
  );
  const doc = dom.window.document;
  doc.getElementById("trigger").focus();
  let closed = false;
  const release = activateDialog(doc.getElementById("dialog"), () => {
    closed = true;
  });
  assert.equal(doc.activeElement.id, "first");
  doc.dispatchEvent(
    new dom.window.KeyboardEvent("keydown", {
      key: "Tab",
      shiftKey: true,
      bubbles: true,
      cancelable: true,
    }),
  );
  assert.equal(doc.activeElement.id, "last");
  doc.dispatchEvent(
    new dom.window.KeyboardEvent("keydown", {
      key: "Tab",
      bubbles: true,
      cancelable: true,
    }),
  );
  assert.equal(doc.activeElement.id, "first");
  doc.dispatchEvent(
    new dom.window.KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
  );
  assert.equal(closed, true);
  release();
  assert.equal(doc.activeElement.id, "trigger");
  dom.window.close();
});
