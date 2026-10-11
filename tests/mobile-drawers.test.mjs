import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import test from "node:test";
import {
  closeMobileDrawer,
  getActiveMobileDrawer,
  openMobileDrawer,
  registerMobileDrawer,
  setMobileDrawerScrollLock,
  subscribeMobileDrawerState,
  toggleMobileDrawer,
} from "../src/lib/mobile-drawers.js";

test("mobile drawer requests survive late mounting and the active dock icon toggles the panel closed", () => {
  const dom = new JSDOM("<!doctype html><html><body></body></html>");
  const { window } = dom;
  const states = [];
  let opens = 0;
  let closes = 0;
  const unsubscribeState = subscribeMobileDrawerState((active) => states.push(active), window);

  assert.equal(toggleMobileDrawer("categories", window), "categories");
  assert.equal(getActiveMobileDrawer(window), "categories");
  assert.equal(
    window.document.documentElement.dataset.jluxeMobileDrawer,
    "categories",
  );

  const unregister = registerMobileDrawer(
    "categories",
    { open: () => opens++, close: () => closes++ },
    window,
  );
  assert.equal(opens, 1, "the requested drawer opens when its island mounts");

  assert.equal(toggleMobileDrawer("categories", window), null);
  assert.equal(closes, 1, "tapping the active category item requests close");
  assert.equal(getActiveMobileDrawer(window), null);
  assert.equal(
    window.document.documentElement.hasAttribute("data-jluxe-mobile-drawer"),
    false,
  );
  assert.deepEqual(states, [null, "categories", null]);

  unregister();
  unsubscribeState();
  window.close();
});

test("category and cart drawers are mutually exclusive and legacy open/close events stay synchronized", (t) => {
  const dom = new JSDOM("<!doctype html><html><body></body></html>");
  const { window } = dom;
  const calls = [];
  const unregisterCategories = registerMobileDrawer(
    "categories",
    { open: () => calls.push("categories:open"), close: () => calls.push("categories:close") },
    window,
  );
  const unregisterCart = registerMobileDrawer(
    "cart",
    { open: () => calls.push("cart:open"), close: () => calls.push("cart:close") },
    window,
  );
  t.after(() => {
    unregisterCategories();
    unregisterCart();
    window.close();
  });

  openMobileDrawer("categories", window);
  assert.equal(getActiveMobileDrawer(window), "categories");
  openMobileDrawer("cart", window);
  assert.equal(getActiveMobileDrawer(window), "cart");
  assert.deepEqual(calls, [
    "categories:open",
    "categories:close",
    "cart:open",
  ]);

  window.dispatchEvent(new window.CustomEvent("jluxe:close-cart"));
  assert.equal(getActiveMobileDrawer(window), null, "the drawer's own close path clears the nav icon state");
  window.dispatchEvent(new window.CustomEvent("jluxe:open-categories"));
  assert.equal(getActiveMobileDrawer(window), "categories", "pre-existing open events remain supported");
  assert.equal(closeMobileDrawer("categories", window), true);
  assert.equal(getActiveMobileDrawer(window), null);
});

test("drawer scroll lock remains active through overlapping exit/entry transitions and restores prior body style", (t) => {
  const dom = new JSDOM("<!doctype html><html><body style='overflow:auto'></body></html>");
  const { window } = dom;
  const { body } = window.document;
  t.after(() => window.close());

  setMobileDrawerScrollLock("cart", true, window);
  assert.equal(body.style.overflow, "hidden");
  setMobileDrawerScrollLock("categories", true, window);
  setMobileDrawerScrollLock("cart", false, window);
  assert.equal(body.style.overflow, "hidden", "one drawer's exit cannot unlock the other drawer");
  setMobileDrawerScrollLock("categories", false, window);
  assert.equal(body.style.overflow, "auto", "the original overflow style is restored after the final close");
});
