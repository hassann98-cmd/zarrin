import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/hero-slider.js", import.meta.url), "utf8");

function markup({ effect = "slide", loop = "1", autoplay = "0", slides = 3 } = {}) {
  const items = Array.from({ length: slides }, (_, i) =>
    `<div class="jluxe-hero__slide" data-jluxe-hero-slide data-active="${i === 0}" role="group"${i ? ' aria-hidden="true" inert' : ""}>
      <a class="jluxe-hero__card" href="/s${i}"><picture><img src="/m${i}.jpg" alt="" loading="${i ? "lazy" : "eager"}" fetchpriority="${i ? "low" : "high"}"></picture></a>
    </div>`,
  ).join("");
  const dots = Array.from({ length: slides }, (_, i) => `<button class="jluxe-hero__dot" data-jluxe-hero-dot="${i}" aria-current="${i === 0}"><span data-jluxe-hero-fill></span></button>`).join("");
  return `<section class="jluxe-hero" data-jluxe-hero-slider data-effect="${effect}" data-speed="0" data-autoplay="${autoplay}" data-autoplay-ms="2000" data-loop="${loop}">
    <div class="jluxe-hero__viewport"><div class="jluxe-hero__track" data-jluxe-hero-track>${items}</div></div>
    <button data-jluxe-hero-prev></button><button data-jluxe-hero-next></button><div>${dots}</div></section>`;
}

function setup(opts, { reduced = false } = {}) {
  const dom = new JSDOM(`<!doctype html><html dir="rtl"><body>${markup(opts)}</body></html>`, { url: "https://shop.test/", pretendToBeVisual: true, runScripts: "outside-only" });
  const win = dom.window;
  win.matchMedia = (q) => ({ matches: reduced && q.includes("reduced-motion") });
  win.__JLUXE_HERO_MANUAL__ = true;
  win.eval(source);
  const lib = win.JLuxeHeroSlider;
  const el = win.document.querySelector("[data-jluxe-hero-slider]");
  const api = lib.initHeroSlider(el);
  const track = el.querySelector("[data-jluxe-hero-track]");
  const state = () =>
    [...el.querySelectorAll("[data-jluxe-hero-slide]")].map((s) => `${s.dataset.active === "true" ? "A" : "-"}${s.hasAttribute("inert") ? "i" : ""}`).join(" ");
  return { win, el, api, track, state };
}

test("slide + loop: clones at both ends, first real slide shown (RTL moves the track right)", () => {
  const { el, api, track, state } = setup();
  const clones = el.querySelectorAll("[data-jluxe-hero-clone]");
  assert.equal(clones.length, 2);
  for (const c of clones) {
    assert.equal(c.getAttribute("aria-hidden"), "true");
    assert.ok(c.hasAttribute("inert"));
    assert.equal(c.querySelector("img").getAttribute("fetchpriority"), "low");
  }
  assert.equal(track.firstElementChild.querySelector("a").getAttribute("href"), "/s2", "head clone = last slide");
  assert.equal(track.lastElementChild.querySelector("a").getAttribute("href"), "/s0", "tail clone = first slide");
  assert.equal(api.position, 1);
  assert.match(track.style.transform, /translate3d\(calc\(1 \* \(100% \+ var\(--jh-gap, 0px\)\) \+ 0px\)/);
  assert.equal(state(), "A -i -i");
});

test("loop wraps both ways and lands back on real slides", () => {
  const { api, state } = setup();
  api.next();
  api.next();
  assert.equal(api.index, 2);
  api.next(); // → tail clone, then settles on the real first slide
  assert.equal(api.index, 0);
  assert.equal(api.position, 1);
  api.prev(); // → head clone, settles on the real last slide
  assert.equal(api.index, 2);
  assert.equal(api.position, 3);
  assert.equal(state(), "-i -i A");
});

test("without loop: arrows stop at the ends; the dots and aria-current follow", () => {
  const { el, api } = setup({ loop: "0" });
  assert.equal(el.querySelectorAll("[data-jluxe-hero-clone]").length, 0);
  api.prev();
  assert.equal(api.index, 0);
  api.goTo(2);
  api.next();
  assert.equal(api.index, 2);
  const current = [...el.querySelectorAll("[data-jluxe-hero-dot]")].map((d) => d.getAttribute("aria-current"));
  assert.deepEqual(current, ["false", "false", "true"]);
});

test("fade: no clones, no transform, only the active slide is interactive; wraps when loop is on", () => {
  const { el, api, track, state } = setup({ effect: "fade" });
  assert.equal(el.querySelectorAll("[data-jluxe-hero-clone]").length, 0);
  assert.equal(track.style.transform, "");
  api.next();
  assert.equal(state(), "-i A -i");
  api.next();
  api.next();
  assert.equal(api.index, 0);
});

test("dots, arrows and keyboard (RTL: ArrowLeft = next)", () => {
  const { win, el, api } = setup({ effect: "fade" });
  el.querySelector('[data-jluxe-hero-dot="2"]').click();
  assert.equal(api.index, 2);
  el.querySelector("[data-jluxe-hero-next]").click();
  assert.equal(api.index, 0);
  el.dispatchEvent(new win.KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true }));
  assert.equal(api.index, 1);
  el.dispatchEvent(new win.KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));
  assert.equal(api.index, 0);
  el.querySelector("[data-jluxe-hero-prev]").click();
  assert.equal(api.index, 2);
});

test("autoplay advances, pauses on hover/focus, and never runs with reduced motion", async () => {
  const a = setup({ effect: "fade", autoplay: "1" });
  await new Promise((r) => setTimeout(r, 2150));
  assert.equal(a.api.index, 1, "advanced after the delay");
  a.el.dispatchEvent(new a.win.Event("mouseenter"));
  await new Promise((r) => setTimeout(r, 2150));
  assert.equal(a.api.index, 1, "paused while hovered");

  const b = setup({ effect: "fade", autoplay: "1" }, { reduced: true });
  await new Promise((r) => setTimeout(r, 2150));
  assert.equal(b.api.index, 0, "reduced motion: no autoplay");
});

test("a single slide is left alone", () => {
  const { api, el } = setup({ slides: 1 });
  assert.equal(api, null);
  assert.equal(el.getAttribute("data-ready"), null);
});

test("animated loop: lands on the clone first, then jumps to the real slide after the transition", async () => {
  const { api: live, track: liveTrack } = (() => {
    const dom = new JSDOM(`<!doctype html><html dir="rtl"><body>${markup().replace('data-speed="0"', 'data-speed="120"')}</body></html>`, { runScripts: "outside-only" });
    dom.window.matchMedia = () => ({ matches: false });
    dom.window.__JLUXE_HERO_MANUAL__ = true;
    dom.window.eval(source);
    const root = dom.window.document.querySelector("[data-jluxe-hero-slider]");
    return { api: dom.window.JLuxeHeroSlider.initHeroSlider(root), track: root.querySelector("[data-jluxe-hero-track]") };
  })();
  live.prev();
  assert.equal(live.position, 0, "animating towards the head clone");
  assert.equal(live.index, 2);
  await new Promise((r) => setTimeout(r, 250));
  assert.equal(live.position, 3, "settled on the real last slide");
  assert.match(liveTrack.style.transform, /calc\(3 \*/);
});
