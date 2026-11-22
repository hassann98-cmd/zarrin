import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { JSDOM } from "jsdom";

const homepage = await readFile(
  new URL("../inc/theme-settings-homepage.php", import.meta.url),
  "utf8",
);
const sanitize = await readFile(
  new URL("../inc/theme-settings-sanitize.php", import.meta.url),
  "utf8",
);
const source = await readFile(
  new URL("../assets/js/homepage.js", import.meta.url),
  "utf8",
);
const adminSource = await readFile(
  new URL("../assets/js/theme-settings-admin.js", import.meta.url),
  "utf8",
);

test("brand video campaign section is registered, editable, sanitized, and rendered with a fallback", () => {
  assert.match(homepage, /'brand_video_campaigns'\s*=>\s*'کمپین ویدیویی برندها'/);
  assert.match(homepage, /option value="product"/);
  assert.match(homepage, /option value="category"/);
  assert.match(homepage, /background_color/);
  assert.match(homepage, /\[mobile_media_id\]/);
  assert.match(homepage, /data-desktop-src=/);
  assert.match(homepage, /<picture class="jluxe-bvc-picture">/);
  assert.match(homepage, /jluxe_render_homepage_brand_video_campaigns\( \$section \)/);
  assert.match(homepage, /data-jluxe-brand-campaigns/);
  assert.match(homepage, /role="tablist"/);
  assert.match(homepage, /muted loop playsinline preload="none"/);
  assert.match(source, /oldVideo\.autoplay = !reducedMotion/);
  assert.match(source, /data-mobile-src/);
  assert.match(adminSource, /syncBrandCampaignEditors/);
  assert.match(adminSource, /انتخاب تصویر یا GIF/);
  assert.match(homepage, /jluxe-bvc-empty-media/);
  assert.match(homepage, /data-jluxe-bvc-mute/);
  assert.match(homepage, /class="jluxe-bvc-products" data-jluxe-scroller/);
  assert.match(homepage, /jluxe-bvc-sale-badge/);
  assert.match(homepage, /border-inline-start: 1px solid #ededf0/);
  assert.match(homepage, /jluxe-bvc-product-meta/);
  assert.match(sanitize, /case 'brand_video_campaigns'/);
  assert.match(sanitize, /array_slice\( \(array\) \( \$posted\['campaigns'\].*0, 6/s);
});

test("brand campaign styling follows the supplied Digikala section tokens and desktop/mobile spacing", () => {
  assert.match(homepage, /--bvc-foreground:\s*#ECEDEF/i);
  assert.match(homepage, /background:\s*var\(--bvc-background, #7e0820\)/i);
  assert.match(homepage, /color:\s*var\(--bvc-foreground\)/i);
  assert.match(homepage, /padding-inline:\s*12px 0/);
  assert.match(homepage, /padding-block-end:\s*12px/);
  assert.match(homepage, /border:\s*1px solid #dedfe0/);
  assert.match(homepage, /border-radius:\s*16px/);
  assert.match(homepage, /box-shadow:\s*0 2px 8px -2px #0000001f, 0 1px 2px -1px #0000001f/);
  assert.match(homepage, /@media \(min-width: 1024px\)[\s\S]*?\.jluxe-brand-campaigns\s*\{[\s\S]*?margin-top:\s*0/);
  assert.match(homepage, /\.jluxe-brand-campaigns \{ width: 100%; margin: 20px 0 24px; padding: 0 8px 10px/);
});

test("brand campaign tabs switch panels and pause inactive video; keyboard and mute control work", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns>
      <button role="tab" aria-selected="true" tabindex="0" aria-controls="panel-a"></button>
      <button role="tab" aria-selected="false" tabindex="-1" aria-controls="panel-b"></button>
      <div role="tabpanel" id="panel-a"><video></video><button data-jluxe-bvc-mute aria-pressed="false" aria-label="فعال‌کردن صدا"><span data-jluxe-bvc-mute-label>بی‌صدا</span></button></div>
      <div role="tabpanel" id="panel-b" hidden><video></video></div>
    </section>
  </body></html>`, {
    url: "https://shop.test/",
    pretendToBeVisual: true,
    runScripts: "outside-only",
  });
  const { window } = dom;
  const videos = [...window.document.querySelectorAll("video")];
  const calls = [[], []];
  videos.forEach((video, i) => {
    video.muted = true;
    video.play = () => { calls[i].push("play"); return Promise.resolve(); };
    video.pause = () => calls[i].push("pause");
  });
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));

  const tabs = [...window.document.querySelectorAll('[role="tab"]')];
  const panels = [...window.document.querySelectorAll('[role="tabpanel"]')];
  assert.equal(panels[0].hidden, false);
  assert.equal(panels[1].hidden, true);
  assert.deepEqual(calls[0], ["play"]);

  tabs[0].dispatchEvent(new window.KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true, cancelable: true }));
  assert.equal(tabs[1].getAttribute("aria-selected"), "true");
  assert.equal(tabs[1].tabIndex, 0);
  assert.equal(panels[1].hidden, false);
  assert.equal(panels[0].hidden, true);
  assert.equal(calls[0].at(-1), "pause");
  assert.equal(calls[1].at(-1), "play");

  tabs[0].click();
  const mute = panels[0].querySelector("[data-jluxe-bvc-mute]");
  mute.click();
  assert.equal(videos[0].muted, false);
  assert.equal(mute.getAttribute("aria-pressed"), "true");
  assert.equal(mute.getAttribute("aria-label"), "قطع صدا");
  assert.equal(mute.querySelector("[data-jluxe-bvc-mute-label]").textContent, "با صدا");
  dom.window.close();
});

test("brand campaign videos switch to an optional mobile asset when the viewport crosses the mobile breakpoint", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns><button role="tab" aria-selected="true" tabindex="0"></button>
      <div role="tabpanel"><video data-desktop-src="https://shop.test/desktop.mp4" data-mobile-src="https://shop.test/mobile.mp4"></video></div>
    </section>
  </body></html>`, { runScripts: "outside-only", pretendToBeVisual: true });
  const { window } = dom;
  const changes = [];
  const mobileQuery = { matches: false, addEventListener: (_type, callback) => changes.push(callback) };
  window.matchMedia = query => query.includes("max-width") ? mobileQuery : { matches: false };
  const video = window.document.querySelector("video");
  video.load = () => {};
  video.play = () => Promise.resolve();
  video.pause = () => {};
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  assert.equal(video.getAttribute("src"), "https://shop.test/desktop.mp4");
  mobileQuery.matches = true;
  changes[0]();
  assert.equal(video.getAttribute("src"), "https://shop.test/mobile.mp4");
  dom.window.close();
});

test("brand video campaigns disable autoplay and reveal native controls for reduced-motion visitors", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns>
      <button role="tab" aria-selected="true" tabindex="0"></button>
      <div role="tabpanel"><video></video></div>
    </section>
  </body></html>`, { runScripts: "outside-only", pretendToBeVisual: true });
  const { window } = dom;
  window.matchMedia = () => ({ matches: true });
  const video = window.document.querySelector("video");
  let playCalls = 0;
  video.play = () => { playCalls++; return Promise.resolve(); };
  video.pause = () => {};
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  assert.equal(playCalls, 0);
  assert.equal(video.autoplay, false);
  assert.equal(video.controls, true);
  dom.window.close();
});
