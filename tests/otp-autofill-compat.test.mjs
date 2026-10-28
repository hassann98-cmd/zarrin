import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";
import {
  keepOtpInputVisible,
  watchOtpInputViewport,
} from "../src/lib/otp-viewport.js";

const authSource = fs.readFileSync(
  new URL("../src/islands/AuthPage.jsx", import.meta.url),
  "utf8",
);

test("R353 starts Chrome WebOTP before requesting the SMS and keeps native OTP autofill fallback", () => {
  const listenPosition = authSource.indexOf("startWebOtp();");
  const requestPosition = authSource.indexOf('"auth/otp-request"');
  assert.ok(listenPosition >= 0 && listenPosition < requestPosition,
    "WebOTP must be listening before the server can trigger SMS delivery");
  assert.match(authSource, /autoComplete="one-time-code"/,
    "Safari/iOS, Firefox, and Samsung keyboard autofill use the native one-time-code hint");
  assert.match(authSource, /inputMode="numeric"/,
    "the fallback keeps a numeric keyboard for manual entry");
  assert.match(authSource, /OTP_AUTOFILL_SYNC_WINDOW_MS = 120_000/,
    "silent native autofill remains synchronized when the browser emits no input event");
});

test("R353 moves an OTP control above the visual keyboard viewport only when needed", () => {
  let rect = { top: 180, bottom: 240 };
  const scrollCalls = [];
  const input = {
    getBoundingClientRect: () => rect,
    scrollIntoView: (options) => scrollCalls.push(options),
  };
  const browserWindow = {
    innerHeight: 800,
    visualViewport: { offsetTop: 0, height: 500 },
  };

  assert.equal(keepOtpInputVisible(input, browserWindow), false,
    "a field already visible above the keyboard is not moved");
  assert.deepEqual(scrollCalls, []);

  rect = { top: 460, bottom: 520 };
  assert.equal(keepOtpInputVisible(input, browserWindow), true,
    "a field covered by the keyboard is brought into view");
  assert.deepEqual(scrollCalls, [{ block: "center", inline: "nearest", behavior: "auto" }]);
});

test("R353 watches keyboard viewport changes and cleans up its listeners", async (t) => {
  const dom = new JSDOM("<!doctype html><html><body></body></html>", {
    url: "https://shop.test/login/",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  const viewport = new window.EventTarget();
  viewport.offsetTop = 0;
  viewport.height = 700;
  Object.defineProperty(window, "visualViewport", {
    configurable: true,
    value: viewport,
  });

  let rect = { top: 180, bottom: 240 };
  const scrollCalls = [];
  const input = window.document.createElement("input");
  input.getBoundingClientRect = () => rect;
  input.scrollIntoView = (options) => scrollCalls.push(options);
  const stopWatching = watchOtpInputViewport(input, window);
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(scrollCalls.length, 0, "the initially visible field does not jump");

  rect = { top: 390, bottom: 450 };
  viewport.height = 300;
  viewport.dispatchEvent(new window.Event("resize"));
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(scrollCalls.length, 1, "opening the keyboard reveals the focused OTP field");

  stopWatching();
  rect = { top: 390, bottom: 450 };
  viewport.height = 200;
  viewport.dispatchEvent(new window.Event("resize"));
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(scrollCalls.length, 1, "cleanup prevents stale viewport listeners from moving the page");
});
