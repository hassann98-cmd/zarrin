import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const source = await readFile(
  new URL("../assets/js/homepage.js", import.meta.url),
  "utf8",
);

function setup({ width = 390, reducedMotion = false, scrollWidth = 820, readyState = "loading" } = {}) {
  const dom = new JSDOM(
    `<!doctype html><html><body><div id="wrap"><div id="track" data-jluxe-scroller data-jluxe-category-swipe-hint></div></div></body></html>`,
    { url: "https://shop.test/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  Object.defineProperty(window.document, "readyState", { configurable: true, get: () => readyState });
  const scroller = window.document.getElementById("track");
  const observers = [];
  const positions = [];
  let scrollLeft = 0;

  window.matchMedia = (query) => ({
    matches: query.includes("max-width: 767.98px")
      ? width <= 767.98
      : query.includes("prefers-reduced-motion")
        ? reducedMotion
        : false,
    media: query,
    addEventListener() {},
    removeEventListener() {},
  });
  window.IntersectionObserver = class {
    constructor(callback, options) {
      this.callback = callback;
      this.options = options;
      this.target = null;
      this.disconnected = false;
      observers.push(this);
    }
    observe(target) {
      this.target = target;
    }
    disconnect() {
      this.disconnected = true;
    }
    trigger() {
      this.callback([{ target: this.target, isIntersecting: true }], this);
    }
  };
  Object.defineProperty(scroller, "clientWidth", { configurable: true, get: () => width });
  Object.defineProperty(scroller, "scrollWidth", { configurable: true, get: () => scrollWidth });
  Object.defineProperty(scroller, "scrollLeft", {
    configurable: true,
    get: () => scrollLeft,
    set(value) {
      scrollLeft = value;
      positions.push(value);
    },
  });

  window.eval(source);
  if (readyState === "loading") {
    window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  }
  return { dom, window, scroller, observers, positions, get scrollLeft() { return scrollLeft; } };
}

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

test("R305 mobile category hint nudges briefly, returns to its starting scroll, and runs once", async () => {
  const state = setup();
  assert.equal(state.observers.length, 1);
  state.observers[0].trigger();
  await wait(1400);

  assert.ok(Math.min(...state.positions) <= -28, "the row gives a clearly visible glimpse of more categories");
  assert.ok(Math.min(...state.positions) >= -46, "the nudge stays deliberately short");
  assert.equal(state.scrollLeft, 0, "the row returns to its exact starting position");
  assert.equal(state.observers[0].disconnected, true);
  state.observers[0].trigger();
  await wait(100);
  assert.equal(state.scrollLeft, 0, "a completed hint never plays again");
  state.dom.window.close();
});

test("R305 the homepage script initializes the hint even when it loads after DOMContentLoaded", () => {
  const state = setup({ readyState: "complete" });
  assert.equal(state.observers.length, 1);
  assert.equal(state.observers[0].target, state.scroller);
  state.dom.window.close();
});

test("R305 the category hint is mobile-only, respects reduced motion, and skips rows without overflow", async () => {
  const desktop = setup({ width: 1024 });
  const reduced = setup({ reducedMotion: true });
  const fits = setup({ scrollWidth: 390 });

  assert.equal(desktop.observers.length, 0);
  assert.equal(reduced.observers.length, 0);
  assert.equal(fits.observers.length, 1);
  fits.observers[0].trigger();
  await wait(50);
  assert.equal(fits.positions.length, 0);
  assert.equal(fits.observers[0].disconnected, true);

  desktop.dom.window.close();
  reduced.dom.window.close();
  fits.dom.window.close();
});

test("R305 a user's first touch cancels the hint before it can affect a manual swipe", async () => {
  const state = setup();
  state.observers[0].trigger();
  state.window.setTimeout(() => {
    state.window.document.getElementById("wrap").dispatchEvent(
      new state.window.Event("touchstart", { bubbles: true }),
    );
  }, 50);
  await wait(350);

  assert.equal(state.positions.length, 0);
  assert.equal(state.scrollLeft, 0);
  assert.equal(state.observers[0].disconnected, true);
  state.dom.window.close();
});
