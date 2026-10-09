import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const js = fs.readFileSync(new URL("../assets/js/site-faq.js", import.meta.url), "utf8");
const php = fs.readFileSync(new URL("../page-faq.php", import.meta.url), "utf8");
const css = fs.readFileSync(new URL("../src/styles/storefront.css", import.meta.url), "utf8");

function createFaqDom({ reducedMotion = false } = {}) {
  const dom = new JSDOM(
    `<!doctype html><html lang="fa" dir="rtl"><body>
      <section class="jluxe-site-faq">
        <details class="jluxe-site-faq__item">
          <summary class="jluxe-site-faq__trigger">سوال یک</summary>
          <div class="jluxe-site-faq__panel"><div class="jluxe-site-faq__panel-inner"><p>پاسخ یک</p></div></div>
        </details>
        <details class="jluxe-site-faq__item">
          <summary class="jluxe-site-faq__trigger">سوال دو</summary>
          <div class="jluxe-site-faq__panel"><div class="jluxe-site-faq__panel-inner"><p>پاسخ دو</p></div></div>
        </details>
      </section>
    </body></html>`,
    { pretendToBeVisual: true, runScripts: "outside-only" },
  );
  dom.window.matchMedia = (query) => ({
    matches: query === "(prefers-reduced-motion: reduce)" && reducedMotion,
    media: query,
    addEventListener() {},
    removeEventListener() {},
  });
  dom.window.eval(js);
  return dom;
}

function click(window, target) {
  target.dispatchEvent(new window.MouseEvent("click", { bubbles: true, cancelable: true }));
}

test("R180 the FAQ page uses accessible native disclosures, the requested heading and a page-scoped script", () => {
  assert.match(php, /<h1 id="jluxe-site-faq-title">سوال داری؟<\/h1>/);
  assert.match(php, /شاید جواب سوالت رو اینجا پیدا کردی/);
  assert.match(php, /<details class="jluxe-site-faq__item">/);
  assert.match(php, /class="jluxe-site-faq__trigger" aria-controls=/);
  assert.match(php, /wp_enqueue_script\(\s*'jluxe-site-faq'/s);
  assert.match(php, /nl2br\( esc_html\( \$jluxe_faq_answer \) \)/);
  assert.doesNotMatch(php, /tel:09120902336/);
});

test("R180 FAQ disclosures open independently and keep expanded answers available", () => {
  const dom = createFaqDom();
  const { document } = dom.window;
  const [first, second] = document.querySelectorAll("details");
  const firstSummary = first.querySelector("summary");
  const firstPanel = first.querySelector(".jluxe-site-faq__panel");

  click(dom.window, firstSummary);
  assert.equal(first.open, true, "native details opens on summary activation");
  assert.equal(firstPanel.hasAttribute("aria-hidden"), false);
  assert.equal(firstPanel.inert, false);

  click(dom.window, second.querySelector("summary"));
  assert.equal(first.open, true, "opening another question does not collapse the first");
  assert.equal(second.open, true);
  dom.window.close();
});

test("R180 closing animates before native details hides the panel and then marks it inert", () => {
  const dom = createFaqDom();
  const { document, Event } = dom.window;
  const details = document.querySelector("details");
  const summary = details.querySelector("summary");
  const panel = details.querySelector(".jluxe-site-faq__panel");

  click(dom.window, summary);
  click(dom.window, summary);
  assert.equal(details.open, true, "details remains open during the visual collapse");
  assert.equal(details.classList.contains("is-closing"), true);
  assert.equal(panel.getAttribute("aria-hidden"), "true");
  assert.equal(panel.inert, true);

  const transitionEnd = new Event("transitionend", { bubbles: true });
  Object.defineProperty(transitionEnd, "propertyName", { value: "grid-template-rows" });
  panel.dispatchEvent(transitionEnd);
  assert.equal(details.open, false);
  assert.equal(details.classList.contains("is-closing"), false);
  assert.equal(panel.inert, true);
  dom.window.close();
});

test("R180 reduced-motion users get immediate open and close without waiting for transition", () => {
  const dom = createFaqDom({ reducedMotion: true });
  const details = dom.window.document.querySelector("details");
  const summary = details.querySelector("summary");
  click(dom.window, summary);
  assert.equal(details.open, true);
  click(dom.window, summary);
  assert.equal(details.open, false);
  assert.equal(details.classList.contains("is-closing"), false);
  dom.window.close();
});

test("R180 FAQ styling is centered, capped at 900px, token-based and responsive", () => {
  assert.match(css, /\.jluxe-site-faq\s*\{[^}]*max-width:\s*900px/s);
  assert.match(css, /grid-template-rows:\s*0fr[\s\S]*?grid-template-rows:\s*1fr/);
  assert.match(css, /@media\s*\(max-width:\s*639px\)[\s\S]*?\.jluxe-site-faq/);
  assert.match(css, /hsl\(var\(--surface\)\)/);
  assert.match(css, /hsl\(var\(--foreground\)\)/);
  assert.match(css, /\.dark \.jluxe-site-faq__card\s*\{[^}]*background:\s*#111827/s);
  assert.match(css, /\.dark \.jluxe-site-faq__question\s*\{[^}]*#f9fafb/s);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
});
