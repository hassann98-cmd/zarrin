import test, { before, after } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { spawnSync } from "node:child_process";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createServer } from "vite";
import { JSDOM } from "jsdom";
import jquery from "jquery";

let server, AuthPage;
before(async () => {
  // Transform the actual JSX source, with no HTTP listener and no HMR server.
  server = await createServer({
    configFile: false,
    appType: "custom",
    server: { middlewareMode: true, hmr: false, watch: null },
  });
  ({ default: AuthPage } = await server.ssrLoadModule(
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

test("R03 OTP UI is conditional on configured SMS and uses a real telephone field", () => {
  assert.doesNotMatch(authMarkup(), /id="jluxe-auth-phone"/);
  const html = authMarkup({ sms: true });
  assert.match(html, /id="jluxe-auth-phone"/);
  assert.match(html, /type="tel"/);
  assert.match(html, /ورود پیامکی فقط برای حساب‌های موجود/);
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
