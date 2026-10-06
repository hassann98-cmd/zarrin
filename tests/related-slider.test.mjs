import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";

/*
 * R85 — the related-products slider, exercised the way the browser sees it.
 *
 * jsdom has no layout engine, so widths/offsets are faked: every card is reported
 * as 220px wide with a 12px gap, in the document order the RTL row uses
 * (first card on the right). The script must then:
 *   - stay silent when all cards fit (arrows hidden),
 *   - move one card per arrow click, in the right direction for the page language,
 *   - clamp at the ends and never leave a partly empty row.
 */
const source = fs.readFileSync(new URL("../assets/js/related-slider.js", import.meta.url), "utf8");

const CARD = 220;
const GAP = 12;
const VIEW = 940; // ~4 cards + gaps

function build({ cards = 5, rtl = true } = {}) {
  const dir = rtl ? "rtl" : "ltr";
  const parts = [];
  for (let i = 0; i < cards; i++) parts.push(`<li class="product" data-i="${i}"></li>`);
  return `<!doctype html><html dir="${dir}"><body>
    <div class="cp3-related" data-cp3-related>
      <div class="cp3-relnav" data-cp3-rel-nav hidden>
        <button type="button" data-cp3-rel-prev aria-label="قبلی"></button>
        <button type="button" data-cp3-rel-next aria-label="بعدی"></button>
      </div>
      <ul class="products" style="direction:${dir}">${parts.join("")}</ul>
    </div></body></html>`;
}

async function load(html) {
  const { JSDOM } = await import("jsdom");
  const dom = new JSDOM(html, { pretendToBeVisual: true });
  const { window } = dom;
  const track = window.document.querySelector("ul.products");
  const cards = [...track.querySelectorAll("li.product")];
  const rtl = "rtl" === track.style.direction;
  const content = cards.length * CARD + (cards.length - 1) * GAP;
  const max = Math.max(0, content - VIEW);

  // minimal layout + scroll model: RTL runs 0 -> -max, LTR runs 0 -> +max
  let scrollLeft = 0;
  const clamp = (value) => (rtl ? Math.max(-max, Math.min(0, value)) : Math.max(0, Math.min(max, value)));
  Object.defineProperty(track, "scrollWidth", { value: content });
  Object.defineProperty(track, "clientWidth", { value: VIEW });
  Object.defineProperty(track, "scrollLeft", {
    get: () => scrollLeft,
    set: (value) => { scrollLeft = clamp(value); },
  });
  track.scrollBy = ({ left }) => {
    track.scrollLeft = scrollLeft + left;
    track.dispatchEvent(new window.Event("scroll"));
  };
  cards.forEach((card, i) => {
    card.getBoundingClientRect = () => ({ left: i * (CARD + GAP), width: CARD });
  });

  window.matchMedia = () => ({ matches: false, addEventListener() {}, removeEventListener() {} });
  vm.runInContext(source, vm.createContext(window));
  return { window, track, cards, max, rtl };
}

test("R85 the row is one line and the arrows appear only when something overflows", async () => {
  const { window, track } = await load(build({ cards: 5 }));
  const nav = window.document.querySelector("[data-cp3-rel-nav]");
  assert.equal(nav.hidden, false, "five cards in a four-card view show the arrows");
  assert.ok(track.scrollWidth > track.clientWidth, "the track is horizontally scrollable");

  const few = await load(build({ cards: 4 }));
  assert.equal(
    few.window.document.querySelector("[data-cp3-rel-nav]").hidden,
    true,
    "when every card fits the arrows hide themselves",
  );
});

test("R85 next moves exactly one card and never past the end", async () => {
  const { window, track, max } = await load(build({ cards: 6 }));
  const next = window.document.querySelector("[data-cp3-rel-next]");
  const prev = window.document.querySelector("[data-cp3-rel-prev]");

  assert.equal(prev.disabled, true, "at the start there is nothing before the first card");
  next.dispatchEvent(new window.Event("click"));
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(Math.abs(track.scrollLeft), CARD + GAP, "one click reveals exactly one more card");

  for (let i = 0; i < 10; i++) {
    next.dispatchEvent(new window.Event("click"));
    await new Promise((resolve) => window.setTimeout(resolve, 5));
  }
  assert.equal(Math.abs(track.scrollLeft), max, "clicking past the end clamps to the last card, no empty space");
  assert.equal(next.disabled, true, "and the next arrow disables itself at the end");

  prev.dispatchEvent(new window.Event("click"));
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(Math.abs(track.scrollLeft), max - (CARD + GAP), "previous walks back one card");
});

test("R85 the same clicks move the right way in a left-to-right page", async () => {
  const { window, track } = await load(build({ cards: 6, rtl: false }));
  const next = window.document.querySelector("[data-cp3-rel-next]");
  next.dispatchEvent(new window.Event("click"));
  await new Promise((resolve) => window.setTimeout(resolve, 30));
  assert.equal(track.scrollLeft, CARD + GAP, "LTR scrolls forward with a positive offset");
});
