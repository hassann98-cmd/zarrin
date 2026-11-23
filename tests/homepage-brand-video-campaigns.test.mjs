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

function installIntersectionObserver(window) {
  const observers = [];
  window.IntersectionObserver = class {
    constructor(callback, options) { this.callback = callback; this.options = options; this.targets = []; observers.push(this); }
    observe(target) { this.targets.push(target); }
    disconnect() {}
    enter(target, isIntersecting = true) {
      this.callback([{ target, isIntersecting, intersectionRatio: isIntersecting ? 1 : 0 }]);
    }
  };
  return observers;
}

function observerFor(observers, target) {
  return observers.find(observer => observer.targets.includes(target));
}

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
  assert.match(source, /video\.autoplay = !reducedMotion/);
  assert.match(source, /data-mobile-src/);
  assert.match(adminSource, /syncBrandCampaignEditors/);
  assert.match(adminSource, /انتخاب تصویر یا GIF/);
  assert.match(homepage, /jluxe-bvc-empty-media/);
  assert.match(homepage, /data-jluxe-bvc-mute/);
  assert.match(homepage, /class="jluxe-bvc-products-rail relative"/);
  assert.match(homepage, /data-jluxe-scroller/);
  assert.match(homepage, /width="32" height="32" loading="lazy"/);
  assert.match(homepage, /data-jluxe-product-link/);
  assert.match(homepage, /get_variation_prices\( true \)/);
  assert.match(homepage, /'stock_status'\s*=>\s*'instock'/);
  assert.match(homepage, /'limit'\s*=>\s*8/);
  assert.match(homepage, /data-jluxe-bvc-bg-picker/);
  assert.match(homepage, /\[campaigns\].*\[bg_color\]/);
  assert.match(homepage, /static \$printed = false/);
  assert.match(homepage, /class="jluxe-bvc-item jluxe-bvc-media-item"/);
  assert.match(homepage, /class="jluxe-bvc-see-all"/);
  const campaignRenderer = homepage.slice(
    homepage.indexOf("function jluxe_render_homepage_brand_video_campaigns"),
    homepage.indexOf("function jluxe_render_homepage_brand_marquee"),
  );
  assert.match(campaignRenderer, /poster=\"' \. esc_url\( \$campaign\['poster'\] \) \. '\" data-desktop-poster=/);
  assert.doesNotMatch(campaignRenderer, /jluxe_toman_display_icon_svg/);
  assert.match(sanitize, /case 'brand_video_campaigns'/);
  assert.match(sanitize, /array_slice\( \(array\) \( \$posted\['campaigns'\].*0, 6/s);
  assert.match(sanitize, /sanitize_hex_color\( \(string\) \( \$campaign\['bg_color'\] \?\? '' \) \)/);
  assert.match(adminSource, /\$card\.find\('\[data-jluxe-bvc-for=/);
  assert.match(adminSource, /\$card\.find\('\[data-jluxe-bvc-media-poster=/);
  assert.match(adminSource, /syncBrandCampaignEditors\(\$clone\)/);
  assert.match(adminSource, /syncBrandCampaignEditors\(\$newSection\)/);
  assert.equal((adminSource.match(/trigger\("wc-enhanced-select-init"\)/g) || []).length, 2);
});

test("brand campaign styles implement per-campaign contrast, responsive Digikala surface, capsule tabs, and unified rail", () => {
  assert.match(homepage, /--bvc-primary:hsl\(var\(--primary\)\)/);
  assert.match(homepage, /background-color:var\(--bvc-active-bg,var\(--bvc-background,#7E0820\)\)/);
  assert.match(homepage, /color:var\(--bvc-fg,#ECEDEF\)/);
  assert.match(homepage, /transition:background-color \.5s cubic-bezier\(\.61,1,\.88,1\),color \.5s cubic-bezier\(\.61,1,\.88,1\)/);
  assert.match(homepage, /border:1px solid hsl\(var\(--border\)\)/);
  assert.match(homepage, /border-radius:16px/);
  assert.match(homepage, /padding:0 16px 24px/);
  assert.match(homepage, /padding:0 12px 16px/);
  assert.match(homepage, /height:40px;padding:4px 12px/);
  assert.match(homepage, /width:32px;height:32px;flex:0 0 32px;border-radius:50%/);
  assert.match(homepage, /gap:12px/);
  assert.match(homepage, /margin-inline-start:24px/);
  assert.match(homepage, /margin-inline-start:16px/);
  assert.match(homepage, /width:clamp\(280px,28vw,340px\)/);
  assert.match(homepage, /width:clamp\(220px,68vw,260px\)/);
  assert.match(homepage, /width:168px/);
  assert.match(homepage, /width:148px/);
  assert.match(homepage, /jluxe-bvc-media-in 380ms cubic-bezier\(\.61,1,\.88,1\)/);
  assert.match(homepage, /translateX\(-12px\)/);
  assert.match(homepage, /prefers-reduced-motion:reduce/);
  assert.match(homepage, /color:#5C5C5C;font-size:10px;font-weight:400/);
  assert.match(homepage, /border:1px solid color-mix\(in srgb,var\(--bvc-fg,#ECEDEF\) 28%,transparent\)/);
  assert.match(homepage, /\.jluxe-bvc-tab\.is-active\{border-color:rgba\(31,31,31,\.14\)/);
  assert.match(homepage, /\.jluxe-bvc-tab-link\{display:none;place-items:center;width:40px;height:40px/);
  assert.match(homepage, /\.jluxe-bvc-tab-item:has\(\.jluxe-bvc-tab\.is-active\) \.jluxe-bvc-tab-link\{display:grid/);
  assert.match(homepage, /\.jluxe-bvc-tab-link\{width:44px;height:44px;min-width:44px;min-height:44px\}/);
});

test("brand campaigns defer video loading to IntersectionObserver and update panels, colors, scroll, and controls", async () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns>
      <button role="tab" aria-selected="true" tabindex="0" aria-controls="panel-a" data-bg-color="#7E0820" data-fg-color="#ECEDEF"></button>
      <button role="tab" aria-selected="false" tabindex="-1" aria-controls="panel-b" data-bg-color="#FFFFFF" data-fg-color="#1F1F1F"></button>
      <div role="tabpanel" id="panel-a"><div class="relative"><ul data-jluxe-scroller><li></li></ul><button data-jluxe-scroll-prev></button></div><video data-desktop-src="https://shop.test/a.mp4"></video><button data-jluxe-bvc-mute aria-pressed="false" aria-label="فعال‌کردن صدا"><span data-jluxe-bvc-mute-label>بی‌صدا</span></button></div>
      <div role="tabpanel" id="panel-b" hidden><div class="relative"><ul data-jluxe-scroller><li></li></ul><button data-jluxe-scroll-next></button></div><video data-desktop-src="https://shop.test/b.mp4"></video></div>
    </section>
  </body></html>`, {
    url: "https://shop.test/",
    pretendToBeVisual: true,
    runScripts: "outside-only",
  });
  const { window } = dom;
  const observers = installIntersectionObserver(window);
  const root = window.document.querySelector("[data-jluxe-brand-campaigns]");
  const videos = [...window.document.querySelectorAll("video")];
  const calls = [[], []];
  videos.forEach((video, i) => {
    video.muted = true;
    video.play = () => { calls[i].push("play"); return Promise.resolve(); };
    video.pause = () => calls[i].push("pause");
    video.load = () => calls[i].push("load");
  });
  let resizeEvents = 0;
  window.addEventListener("resize", () => { resizeEvents++; });
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));

  const tabs = [...window.document.querySelectorAll('[role="tab"]')];
  const panels = [...window.document.querySelectorAll('[role="tabpanel"]')];
  assert.equal(panels[0].hidden, false);
  assert.equal(panels[1].hidden, true);
  assert.equal(videos[0].hasAttribute("src"), false);
  assert.equal(calls.flat().includes("play"), false);
  assert.equal(observerFor(observers, root).options.rootMargin, "200px 0px");
  observerFor(observers, root).enter(root);
  assert.equal(videos[0].getAttribute("src"), "https://shop.test/a.mp4");
  assert.equal(calls[0].at(-1), "play");

  const newScroller = panels[1].querySelector("[data-jluxe-scroller]");
  newScroller.scrollLeft = 90;
  tabs[0].dispatchEvent(new window.KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true, cancelable: true }));
  assert.equal(tabs[1].getAttribute("aria-selected"), "true");
  assert.equal(tabs[1].tabIndex, 0);
  assert.equal(panels[1].hidden, false);
  assert.equal(panels[0].hidden, true);
  assert.equal(newScroller.scrollLeft, 0);
  assert.equal(root.style.getPropertyValue("--bvc-active-bg"), "#FFFFFF");
  assert.equal(root.style.getPropertyValue("--bvc-fg"), "#1F1F1F");
  assert.equal(videos[0].hasAttribute("src"), true);
  assert.equal(videos[1].getAttribute("src"), "https://shop.test/b.mp4");
  assert.equal(calls[0].at(-1), "pause");
  assert.equal(calls[1].at(-1), "play");
  await new Promise(resolve => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
  assert.ok(resizeEvents > 0);
  assert.equal(panels[1].classList.contains("is-entering"), true);

  observerFor(observers, root).enter(root, false);
  assert.equal(calls[1].at(-1), "pause");
  tabs[0].click();
  const mute = panels[0].querySelector("[data-jluxe-bvc-mute]");
  mute.click();
  assert.equal(videos[0].muted, false);
  assert.equal(mute.getAttribute("aria-pressed"), "true");
  assert.equal(mute.getAttribute("aria-label"), "قطع صدا");
  assert.equal(mute.querySelector("[data-jluxe-bvc-mute-label]").textContent, "با صدا");
  const campaignInitializer = source.slice(source.indexOf("function initBrandVideoCampaigns()"), source.indexOf("function whenDocumentReady"));
  assert.doesNotMatch(campaignInitializer, /offsetWidth/);
  dom.window.close();
});

test("brand campaign videos switch to an optional mobile asset when the viewport crosses the mobile breakpoint", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns><button role="tab" aria-selected="true" tabindex="0"></button>
      <div role="tabpanel"><video data-desktop-src="https://shop.test/desktop.mp4" data-mobile-src="https://shop.test/mobile.mp4"></video></div>
    </section>
  </body></html>`, { runScripts: "outside-only", pretendToBeVisual: true });
  const { window } = dom;
  const observers = installIntersectionObserver(window);
  const root = window.document.querySelector("[data-jluxe-brand-campaigns]");
  const changes = [];
  const mobileQuery = { matches: false, addEventListener: (_type, callback) => changes.push(callback) };
  window.matchMedia = query => query.includes("max-width") ? mobileQuery : { matches: false };
  const video = window.document.querySelector("video");
  video.load = () => {};
  video.play = () => Promise.resolve();
  video.pause = () => {};
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  assert.equal(video.hasAttribute("src"), false);
  observerFor(observers, root).enter(root);
  assert.equal(video.getAttribute("src"), "https://shop.test/desktop.mp4");
  mobileQuery.matches = true;
  changes[0]();
  assert.equal(video.getAttribute("src"), "https://shop.test/mobile.mp4");
  dom.window.close();
});

test("brand campaign videos still start for legacy browsers without IntersectionObserver", () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <section data-jluxe-brand-campaigns>
      <button role="tab" aria-selected="true" tabindex="0"></button>
      <div role="tabpanel"><video data-desktop-src="https://shop.test/legacy.mp4"></video></div>
    </section>
  </body></html>`, { runScripts: "outside-only", pretendToBeVisual: true });
  const { window } = dom;
  const video = window.document.querySelector("video");
  let playCalls = 0;
  video.load = () => {};
  video.play = () => { playCalls++; return Promise.resolve(); };
  video.pause = () => {};
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  assert.equal(video.getAttribute("src"), "https://shop.test/legacy.mp4");
  assert.equal(playCalls, 1);
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
  const observers = installIntersectionObserver(window);
  const root = window.document.querySelector("[data-jluxe-brand-campaigns]");
  window.matchMedia = () => ({ matches: true });
  const video = window.document.querySelector("video");
  let playCalls = 0;
  video.play = () => { playCalls++; return Promise.resolve(); };
  video.pause = () => {};
  window.eval(source);
  window.document.dispatchEvent(new window.Event("DOMContentLoaded"));
  assert.equal(video.hasAttribute("src"), false);
  observerFor(observers, root).enter(root);
  assert.equal(playCalls, 0);
  assert.equal(video.autoplay, false);
  assert.equal(video.controls, true);
  dom.window.close();
});
