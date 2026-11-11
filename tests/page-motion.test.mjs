import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
const script = fs.readFileSync(new URL('../assets/js/page-motion.js', import.meta.url), 'utf8');
function boot(t, options = {}) {
  const dom = new JSDOM('<!doctype html><html><head><title>Product title</title><link rel="canonical" href="https://shop.test/product/one/"></head><body><main>Server-rendered product</main></body></html>', { url: 'https://shop.test/product/one/', runScripts: 'outside-only' });
  t.after(() => dom.window.close());
  const win = dom.window;
  win.jluxePageMotion = { transitions: true, prefetch: true, home: 'https://shop.test/', excluded: ['https://shop.test/basket/', 'https://shop.test/pay/', 'https://shop.test/members/'], ...options };
  win.jluxeCurrentAuthStatus = false;
  const timers = new Map(); let next = 0;
  win.setTimeout = (fn, delay) => { timers.set(++next, { fn, delay }); return next; };
  win.clearTimeout = id => timers.delete(id);
  win.fetch = () => { throw new Error('must never fetch/replace a document'); };
  win.eval(script);
  function link(href, attrs = {}) {
    const a = win.document.createElement('a'); a.href = href;
    for (const [name, value] of Object.entries(attrs)) a.setAttribute(name, value);
    a.textContent = 'Link'; win.document.body.append(a); return a;
  }
  return { win, link, timers,
    run(delay) { for (const [id, timer] of [...timers]) if (timer.delay === delay) { timers.delete(id); timer.fn(); } },
    hints: () => [...win.document.querySelectorAll('link[data-jluxe-page-prefetch]')],
  };
}

test('native document navigation is never canceled or replaced, and progress is delayed/reset for bfcache', async t => {
  const { win, link, run } = boot(t);
  const a = link('/product/two/');
  // Dispatch on document to inspect defaultPrevented without JSDOM native navigation.
  const event = new win.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
  Object.defineProperty(event, 'target', { value: a });
  win.document.dispatchEvent(event);
  await Promise.resolve();
  assert.equal(event.defaultPrevented, false);
  assert.equal(win.document.querySelector('.jluxe-page-progress'), null);
  run(180);
  const bar = win.document.querySelector('.jluxe-page-progress');
  assert.equal(bar.hidden, false);
  assert.equal(bar.getAttribute('role'), 'status');
  assert.equal(win.document.title, 'Product title');
  assert.equal(win.document.querySelector('main').textContent, 'Server-rendered product');
  assert.equal(win.document.querySelector('link[rel="canonical"]').href, 'https://shop.test/product/one/');
  win.dispatchEvent(new win.PageTransitionEvent('pageshow', { persisted: true }));
  assert.equal(bar.hidden, true);
  assert.equal(bar.hasAttribute('role'), false);
});

test('canceled React/archive clicks, sensitive/action URLs, modified taps and fragments never start progress', async t => {
  const { win, link, run } = boot(t);
  for (const href of ['/basket/', '/pay/', '/members/orders/', '/product/x/?add-to-cart=4', '#details', '/product/one/', 'https://elsewhere.test/', '/asset.zip']) {
    const event = new win.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
    Object.defineProperty(event, 'target', { value: link(href) });
    win.document.dispatchEvent(event);
  }
  const canceled = new win.MouseEvent('click', { bubbles: true, cancelable: true });
  Object.defineProperty(canceled, 'target', { value: link('/product/two/') });
  win.document.addEventListener('click', event => event.preventDefault(), { once: true });
  win.document.dispatchEvent(canceled);
  await Promise.resolve(); run(180);
  assert.equal(win.document.querySelector('.jluxe-page-progress'), null);
});

test('prefetch is intent-based, two-document bounded, deduplicated and never warms sensitive links', t => {
  const { win, link, hints } = boot(t);
  function focus(a) { a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true })); }
  for (const href of ['/pay/', '/members/', '/product/x/?add-to-cart=1', 'https://evil.test/product/', '/product/two/#reviews']) focus(link(href, { 'data-jluxe-product-link': '' }));
  focus(link('/random-page/'));
  assert.equal(hints().length, 0);
  const product = link('/product/two/', { 'data-jluxe-product-link': '' });
  focus(product); focus(product); hints()[0].dispatchEvent(new win.Event('load')); focus(link('/'));
  focus(link('/product/three/', { 'data-jluxe-product-link': '' }));
  assert.equal(hints().length, 2);
  assert.deepEqual(hints().map(h => h.href), ['https://shop.test/product/two/', 'https://shop.test/']);
});

test('disabled prefetch, private identity and constrained connections do not issue hints', t => {
  const { win, link, hints } = boot(t);
  const a = link('/product/two/', { 'data-jluxe-product-link': '' });
  for (const connection of [{ saveData: true }, { effectiveType: '2g' }, { effectiveType: '3g' }]) {
    Object.defineProperty(win.navigator, 'connection', { configurable: true, value: connection });
    a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  }
  Object.defineProperty(win.navigator, 'connection', { configurable: true, value: {} });
  win.jluxeCurrentAuthStatus = true;
  a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  win.jluxeCurrentAuthStatus = false; win.jluxePageMotion.prefetch = false;
  a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  assert.equal(hints().length, 0);
});

test('page CSS uses native opt-in, no reduced-motion animations, and no fake progress percentage', () => {
  const css = fs.readFileSync(new URL('../assets/css/page-motion.css', import.meta.url), 'utf8');
  assert.match(css, /@view-transition\s*\{ navigation: auto/);
  assert.match(css, /prefers-reduced-motion: reduce/);
  assert.match(css, /navigation: none/);
  assert.doesNotMatch(script, /preventDefault\(|pushState\(|innerHTML|\bfetch\(/);
});


test('category hints use configured base and share a serial two-document budget', t => {
  const { win, link, hints } = boot(t, { categoryBase: 'https://shop.test/product-category/' });
  const focus = href => link(href).dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  focus('/product-category/cleaning/');
  assert.equal(hints().length, 1);
  win.jluxePrefetchPublicPage('/');
  assert.equal(hints().length, 1);
  hints()[0].dispatchEvent(new win.Event('error'));
  win.jluxePrefetchPublicPage('/');
  assert.equal(hints().length, 2);
});

test('cache veto and WooCommerce guest session prevent speculative requests', t => {
  const { win, link, hints } = boot(t, { cacheSafe: false });
  const a = link('/product/two/', { 'data-jluxe-product-link': '' });
  a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  win.jluxePageMotion.cacheSafe = true;
  win.document.cookie = 'wp_woocommerce_session_example=private; path=/';
  a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  assert.equal(hints().length, 0);
});


test('local press feedback is bounded and cleared on cancellation and bfcache', t => {
  const { win, link, run } = boot(t);
  const a = link('/product/two/');
  const down = () => a.dispatchEvent(new win.MouseEvent('pointerdown', { bubbles: true, button: 0 }));
  down(); assert.equal(a.classList.contains('jluxe-navigation-pressed'), true);
  run(240); assert.equal(a.classList.contains('jluxe-navigation-pressed'), false);
  down(); win.document.dispatchEvent(new win.Event('pointercancel'));
  assert.equal(a.classList.contains('jluxe-navigation-pressed'), false);
  down(); win.dispatchEvent(new win.PageTransitionEvent('pageshow', { persisted: true }));
  assert.equal(a.classList.contains('jluxe-navigation-pressed'), false);
});

test('quick navigation, Escape, timeout and disabled motion never leave waiting UI', t => {
  const { win, link, run } = boot(t);
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start'));
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-end'));
  run(180); assert.equal(win.document.querySelector('.jluxe-page-progress'), null);
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start'));
  run(180);
  const bar = win.document.querySelector('.jluxe-page-progress');
  assert.equal(bar.textContent, 'در حال بازکردن صفحه');
  win.document.dispatchEvent(new win.KeyboardEvent('keydown', { key: 'Escape' }));
  assert.equal(bar.hidden, true);
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start')); run(180); run(10000);
  assert.equal(bar.hidden, true);
  win.jluxePageMotion.transitions = false;
  const a = link('/product/two/');
  a.dispatchEvent(new win.MouseEvent('pointerdown', { bubbles: true, button: 0 }));
  assert.equal(a.classList.contains('jluxe-navigation-pressed'), false);
});
