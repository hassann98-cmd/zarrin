import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const source = await readFile(
  new URL("../assets/js/homepage.js", import.meta.url),
  "utf8",
);

test("R178 category carousel arrows bind through the relative wrapper and center only when content fits", () => {
  const dom = new JSDOM(
    `<!doctype html><html><body>
      <div class="jluxe-category-showcase-scroll-wrap relative" data-mobile-layout="row">
        <button type="button" data-jluxe-scroll-prev></button>
        <div class="jluxe-category-showcase-row" data-jluxe-scroller style="column-gap:10px">
          <a href="#one">One</a><a href="#two">Two</a>
        </div>
        <button type="button" data-jluxe-scroll-next></button>
      </div>
    </body></html>`,
    { url: "https://shop.test/", pretendToBeVisual: true, runScripts: "outside-only" },
  );
  const { window } = dom;
  const scroller = window.document.querySelector("[data-jluxe-scroller]");
  const prev = window.document.querySelector("[data-jluxe-scroll-prev]");
  const next = window.document.querySelector("[data-jluxe-scroll-next]");
  let clientWidth = 300;
  const scrollCalls = [];
  Object.defineProperty(scroller, "clientWidth", { get: () => clientWidth });
  Object.defineProperty(scroller, "scrollWidth", { get: () => 600 });
  scroller.children[0].getBoundingClientRect = () => ({ width: 120 });
  scroller.scrollBy = (options) => {
    scrollCalls.push(options);
    scroller.scrollLeft += options.left;
    scroller.dispatchEvent(new window.Event("scroll"));
  };

  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));

  assert.equal(scroller.classList.contains("is-overflowing"), true);
  assert.equal(scroller.classList.contains("is-centered"), false);
  assert.equal(prev.classList.contains("opacity-0"), false);
  assert.equal(next.classList.contains("opacity-0"), true);

  next.dispatchEvent(new window.MouseEvent("click", { bubbles: true }));
  assert.equal(scrollCalls.length, 1);
  assert.equal(scrollCalls[0].left, 130);
  assert.equal(scrollCalls[0].behavior, "instant");
  assert.equal(next.classList.contains("opacity-0"), false);

  clientWidth = 600;
  window.dispatchEvent(new window.Event("resize"));
  assert.equal(scroller.classList.contains("is-overflowing"), false);
  assert.equal(scroller.classList.contains("is-centered"), true);
  assert.equal(prev.classList.contains("opacity-0"), true);
  assert.equal(next.classList.contains("opacity-0"), true);

  window.close();
});
