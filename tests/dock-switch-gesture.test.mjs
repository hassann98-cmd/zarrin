import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';

const source = fs.readFileSync(new URL('../assets/js/storefront-utils.js', import.meta.url), 'utf8');
const sticky = fs.readFileSync(new URL('../assets/js/sticky-cta.js', import.meta.url), 'utf8');
const woo = fs.readFileSync(new URL('../assets/js/woocommerce.js', import.meta.url), 'utf8');
const css = fs.readFileSync(new URL('../src/styles/storefront.css', import.meta.url), 'utf8');

test('dock switches and purchase bars move to body and clicks remain native/bubbling', () => {
  const dom = new JSDOM(`<!doctype html><body>
    <div style="transform:translateY(0)"><button data-jluxe-mobile-bar-toggle>Switch</button><div id="price-bar"></div></div>
    <nav><a href="/">Home</a><a href="/categories/">Categories</a><a href="/login/">Login</a><a href="/cart/">Cart</a></nav>
  </body>`, { url: 'https://shop.test', runScripts: 'outside-only' });
  try {
    const { window } = dom;
    const doc = window.document;
    const button = doc.querySelector('[data-jluxe-mobile-bar-toggle]');
    const bar = doc.querySelector('#price-bar');
    window.eval(source);
    window.JLuxeStorefrontUtils.protectDockSwitch(button, bar);
    assert.equal(button.parentElement, doc.body);
    assert.equal(bar.parentElement, doc.body);
    assert.equal(doc.querySelector('[popover]'), null);
    assert.equal(doc.querySelector('.jluxe-dock-switch-layer'), null);

    const bubbled = [];
    window.addEventListener('click', event => bubbled.push(event.target));
    button.click();
    for (const link of doc.querySelectorAll('nav a')) link.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    assert.deepEqual(bubbled, [button, ...doc.querySelectorAll('nav a')], 'switch and navigation clicks bubble normally to window');
  } finally {
    dom.window.close();
  }
});

test('storefront utilities do not overwrite an existing shared module', () => {
  const dom = new JSDOM('<!doctype html><body></body>', { runScripts: 'outside-only' });
  try {
    const existing = Object.freeze({ sentinel: true });
    dom.window.JLuxeStorefrontUtils = existing;
    dom.window.eval(source);
    assert.equal(dom.window.JLuxeStorefrontUtils, existing);
  } finally {
    dom.window.close();
  }
});

test('dock switch protection installs no gesture capture, synthetic click, or popover machinery', () => {
  const match = source.match(/function protectDockSwitch\(button, bar\) \{([\s\S]*?)\n  \}/);
  assert.ok(match, 'shared dock protection helper exists');
  assert.doesNotMatch(match[1], /pointer|touch|click|popover|capture|showPopover/i);
  assert.doesNotMatch(source, /setPointerCapture|showPopover|popover=["']manual/);
  assert.doesNotMatch(source, /addEventListener\(['"](?:pointerdown|pointermove|pointerup|click)['"],[\s\S]{0,160},\s*true\)/);
});

test('both mode switches handle only their own native click with a local debounce', () => {
  assert.match(sticky, /modeToggle\.addEventListener\(\s*'click'/);
  assert.match(sticky, /now - lastModeToggleClick < 80/);
  assert.match(woo, /toggleBtn\.addEventListener\("click"/);
  assert.match(woo, /now - lastModeClick < 80/);
  assert.doesNotMatch(sticky, /window\.addEventListener\(\s*['"]click['"][\s\S]{0,250}modeToggle/);
  assert.doesNotMatch(woo, /window\.addEventListener\(\s*['"]click['"][\s\S]{0,250}toggleBtn/);
  assert.doesNotMatch(sticky, /setPointerCapture/);
  assert.doesNotMatch(woo, /setPointerCapture/);
});

test('drawer state hides only the two switches and the switcher layer is gone', () => {
  assert.match(css, /html\[data-jluxe-mobile-drawer\] body \.jluxe-mobile-bar-toggle,[\s\S]*?\.jluxe-sticky-cta-mode-toggle\{visibility:hidden;pointer-events:none;/);
  assert.doesNotMatch(css, /jluxe-dock-switch-layer/);
});

test('single-product mobile navigation has an opaque, unblurred surface', () => {
  assert.match(css, /body\.single-product \.jluxe-mobile-nav\s*\{[^}]*background:\s*hsl\(var\(--surface\)\);[^}]*backdrop-filter:\s*none;[^}]*-webkit-backdrop-filter:\s*none;/s);
  assert.match(css, /body\.single-product #bottom-navigation #tabs\s*\{[^}]*--jluxe-bottom-nav-surface:\s*hsl\(var\(--surface\)\);[^}]*backdrop-filter:\s*none;[^}]*-webkit-backdrop-filter:\s*none;/s);
  for (const sourceText of [
    fs.readFileSync(new URL('../src/islands/MobileNav.js', import.meta.url), 'utf8'),
    fs.readFileSync(new URL('../src/islands/FloatingMobileNav.js', import.meta.url), 'utf8'),
  ]) {
    assert.match(sourceText, /isSingleProduct \|\| style\.blur === false \? "none"/);
  }
});
