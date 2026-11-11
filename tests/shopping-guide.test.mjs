import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const template = await readFile(new URL("../page-shopping-guide.php", import.meta.url), "utf8");
const inlineScript = template.match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(inlineScript, "the shopping guide keeps its optional page-specific script");

function prepareDom(markup = "") {
  const dom = new JSDOM(`<!doctype html><html><body>${markup}</body></html>`, {
    runScripts: "outside-only",
    url: "https://shop.test/shopping-guide/",
  });
  Object.defineProperty(dom.window.document, "readyState", {
    configurable: true,
    value: "complete",
  });
  return dom;
}

function guideMarkup(count = 5) {
  return `<div id="jluxe-step-guide">${Array.from(
    { length: count },
    (_, index) => `<section class="jsg-step" data-step="${index + 1}"><span class="jsg-step-num">${index + 1}</span></section>`,
  ).join("")}</div>`;
}

test("shopping guide observes visible steps and toggles the matching number", () => {
  const dom = prepareDom(guideMarkup());
  const observed = [];
  let callback;
  let options;
  dom.window.IntersectionObserver = class {
    constructor(onChange, observerOptions) {
      callback = onChange;
      options = observerOptions;
    }

    observe(target) {
      observed.push(target);
    }
  };

  dom.window.eval(inlineScript);

  const container = dom.window.document.getElementById("jluxe-step-guide");
  assert.equal(container.getAttribute("data-jsg-init"), "1");
  assert.equal(observed.length, 5);
  assert.equal(options.rootMargin, "-35% 0px -50% 0px");
  assert.equal(options.threshold, 0);

  const firstMarker = observed[0].querySelector(".jsg-step-num");
  callback([{ target: observed[0], isIntersecting: true }]);
  assert.equal(firstMarker.classList.contains("jsg-active"), true);
  callback([{ target: observed[0], isIntersecting: false }]);
  assert.equal(firstMarker.classList.contains("jsg-active"), false);

  dom.window.dispatchEvent(new dom.window.Event("boom:page-loaded"));
  assert.equal(observed.length, 5, "repeated page-load events do not add duplicate observers");
});

test("shopping guide initializes after its container is inserted by soft navigation", async () => {
  const dom = prepareDom();
  const observed = [];
  dom.window.IntersectionObserver = class {
    observe(target) {
      observed.push(target);
    }
  };

  dom.window.eval(inlineScript);
  assert.equal(observed.length, 0);

  const staging = dom.window.document.createElement("div");
  staging.innerHTML = guideMarkup(1);
  dom.window.document.body.append(staging.firstElementChild);
  await new Promise((resolve) => dom.window.setTimeout(resolve, 0));

  assert.equal(observed.length, 1);
  assert.equal(
    dom.window.document.getElementById("jluxe-step-guide").getAttribute("data-jsg-init"),
    "1",
  );
});

test("shopping guide remains fully usable when IntersectionObserver is unavailable", () => {
  const dom = prepareDom(guideMarkup(1));
  dom.window.IntersectionObserver = undefined;

  assert.doesNotThrow(() => dom.window.eval(inlineScript));
  assert.equal(
    dom.window.document.getElementById("jluxe-step-guide").getAttribute("data-jsg-init"),
    "1",
  );
  assert.equal(
    dom.window.document.querySelector(".jsg-step-num").classList.contains("jsg-active"),
    false,
  );
});
