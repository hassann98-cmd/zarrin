import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import test from "node:test";
import {
  registerCartDrawerOpen,
  requestCartDrawerOpen,
} from "../src/lib/cart-drawer.js";

test("a mobile cart tap is queued until the asynchronous mini-cart island registers", (t) => {
  const dom = new JSDOM("<!doctype html><html><body></body></html>");
  const { window } = dom;
  let opens = 0;

  assert.equal(requestCartDrawerOpen(window), true);
  assert.equal(opens, 0, "a not-yet-mounted drawer does not drop the open request");

  const unregister = registerCartDrawerOpen(() => opens++, window);
  t.after(() => {
    unregister();
    window.close();
  });
  assert.equal(opens, 1, "the pending request opens the drawer immediately on registration");

  requestCartDrawerOpen(window);
  assert.equal(opens, 2, "subsequent cart taps open the already-mounted drawer");
  window.dispatchEvent(new window.CustomEvent("jluxe:open-cart"));
  assert.equal(opens, 3, "existing event-based cart entry points remain supported");

  unregister();
  requestCartDrawerOpen(window);
  const unregisterAgain = registerCartDrawerOpen(() => opens++, window);
  assert.equal(opens, 4, "a request during drawer teardown is retained for the next mount");
  unregisterAgain();
});
