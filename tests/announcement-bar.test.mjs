import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

// R100: exercise the exact inline initializer emitted before header.php.
// JSDOM has no layout engine, so geometry is deliberately mocked here; this
// verifies offset updates and dismiss behavior, not real-device rendering.
const phpModule = fs.readFileSync(new URL("../inc/announcement-bar.php", import.meta.url), "utf8");
const inlineScript = phpModule.match(/<script>\s*([\s\S]*?)\s*<\/script>/)?.[1];
assert.ok(inlineScript, "announcement initializer exists in the PHP renderer");

function createDom({ dismissible = "1", withButton = true, sticky = "0", adminHeight = 0 } = {}) {
  const button = withButton
    ? '<button type="button" data-jluxe-announcement-dismiss>close</button>'
    : "";
  const adminBar = adminHeight > 0 ? '<div id="wpadminbar"></div>' : "";
  const dom = new JSDOM(
    `<!doctype html><html><body>
      ${adminBar}
      <div id="jluxe-announcement-bar" data-sticky="${sticky}" data-dismissible="${dismissible}" data-dismiss-key="test-key">
        ${button}
      </div>
      <div class="jluxe-header-bar"></div>
    </body></html>`,
    { url: "https://shop.test/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  const bar = window.document.getElementById("jluxe-announcement-bar");
  const admin = window.document.getElementById("wpadminbar");
  let height = 47.5;
  let top = adminHeight;
  bar.getBoundingClientRect = () => ({ height, top, bottom: top + height });
  if (admin) admin.getBoundingClientRect = () => ({ height: adminHeight });
  return {
    dom,
    window,
    bar,
    setHeight: (next) => { height = next; },
    setTop: (next) => { top = next; },
  };
}

test("R100 measures the rendered bar for the fixed-header offset (mocked geometry)", () => {
  const { dom, window } = createDom();
  window.eval(inlineScript);
  assert.equal(
    window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "47.5px",
  );
  assert.equal(window.document.body.classList.contains("jluxe-announcement-ready"), true);
  dom.window.close();
});

test("R100 ResizeObserver updates the header offset when the banner wraps or resizes", () => {
  const { dom, window, bar, setHeight } = createDom();
  let observed;
  let onResize;
  window.ResizeObserver = class {
    constructor(callback) { onResize = callback; }
    observe(element) { observed = element; }
  };
  window.eval(inlineScript);
  assert.equal(observed, bar);
  assert.ok(bar._jluxeAnnouncementResizeObserver, "the observer stays reachable for future resize notifications");
  setHeight(83);
  onResize();
  assert.equal(
    window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "83px",
  );
  dom.window.close();
});

test("R101 an image load recalculates the banner offset when ResizeObserver is unavailable", () => {
  const { dom, window, bar, setHeight } = createDom();
  const image = window.document.createElement("img");
  bar.append(image);
  window.eval(inlineScript);
  setHeight(96);
  image.dispatchEvent(new window.Event("load"));
  assert.equal(
    window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "96px",
  );
  dom.window.close();
});

test("R100 dismissing the bar collapses the offset and persists only for this session", () => {
  const { dom, window, bar } = createDom();
  window.eval(inlineScript);
  bar.querySelector("[data-jluxe-announcement-dismiss]").click();
  assert.equal(bar.hidden, true);
  assert.equal(
    window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "0px",
  );
  assert.equal(window.sessionStorage.getItem("jluxe-announcement:test-key"), "1");
  dom.window.close();
});

test("R100 previously dismissed announcements start hidden; non-dismissible bars do not read storage", () => {
  const dismissed = createDom();
  dismissed.window.sessionStorage.setItem("jluxe-announcement:test-key", "1");
  dismissed.window.eval(inlineScript);
  assert.equal(dismissed.bar.hidden, true);
  assert.equal(
    dismissed.window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "0px",
  );
  dismissed.dom.window.close();

  const permanent = createDom({ dismissible: "0", withButton: false });
  permanent.window.sessionStorage.setItem("jluxe-announcement:test-key", "1");
  permanent.window.eval(inlineScript);
  assert.equal(permanent.bar.hidden, false);
  assert.equal(
    permanent.window.document.documentElement.style.getPropertyValue("--jluxe-announcement-height"),
    "47.5px",
  );
  permanent.dom.window.close();
});

test("R107 a non-sticky bar scrolls away and the fixed header follows its visible bottom", async () => {
  const { dom, window, setTop } = createDom({ sticky: "0", adminHeight: 32 });
  const offset = () => window.document.documentElement.style.getPropertyValue("--jluxe-announcement-sticky-height");
  window.eval(inlineScript);
  assert.equal(offset(), "47.5px", "at page top, the fixed header starts below the banner and admin bar");

  setTop(17);
  window.dispatchEvent(new window.Event("scroll"));
  await new Promise((resolve) => window.requestAnimationFrame(resolve));
  assert.equal(offset(), "32.5px", "the offset shrinks as the normal-flow banner scrolls away");

  setTop(-28);
  window.dispatchEvent(new window.Event("scroll"));
  await new Promise((resolve) => window.requestAnimationFrame(resolve));
  assert.equal(offset(), "0px", "after the banner passes the admin bar, only the header remains pinned");
  dom.window.close();
});

test("R107 a sticky announcement keeps its full measured offset under the fixed header", () => {
  const { dom, window, setTop } = createDom({ sticky: "1", adminHeight: 32 });
  window.eval(inlineScript);
  const offset = () => window.document.documentElement.style.getPropertyValue("--jluxe-announcement-sticky-height");
  assert.equal(offset(), "47.5px");
  setTop(-100);
  window.dispatchEvent(new window.Event("scroll"));
  assert.equal(offset(), "47.5px", "sticky mode does not remove the banner offset during scroll");
  dom.window.close();
});
