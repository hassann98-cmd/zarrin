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

test('native document navigation is never canceled or replaced, and progress is immediate/reset for bfcache', async t => {
  const { win, link, run } = boot(t);
  const a = link('/product/two/');
  // Dispatch on document to inspect defaultPrevented without JSDOM native navigation.
  const event = new win.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
  Object.defineProperty(event, 'target', { value: a });
  win.document.dispatchEvent(event);
  await Promise.resolve();
  assert.equal(event.defaultPrevented, false);
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
  assert.equal(win.document.querySelector('.jluxe-page-progress').hidden, true);
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start'));
  run(180);
  const bar = win.document.querySelector('.jluxe-page-progress');
  assert.equal(bar.textContent, 'در حال بازکردن صفحه…');
  win.document.dispatchEvent(new win.KeyboardEvent('keydown', { key: 'Escape' }));
  assert.equal(bar.hidden, true);
  win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start')); run(180); run(15000);
  assert.equal(bar.hidden, true);
  win.jluxePageMotion.transitions = false;
  const a = link('/product/two/');
  a.dispatchEvent(new win.MouseEvent('pointerdown', { bubbles: true, button: 0 }));
  assert.equal(a.classList.contains('jluxe-navigation-pressed'), false);
});

test('supported browsers get bounded speculation prefetch, never prerender or duplicate native hints', t => {
  // Install support before the module is evaluated.
  const dom = new JSDOM('<!doctype html><head></head><body></body>', { url: 'https://shop.test/', runScripts: 'outside-only' });
  t.after(() => dom.window.close());
  const win = dom.window;
  win.HTMLScriptElement.supports = type => type === 'speculationrules';
  win.jluxePageMotion = { prefetch: true, home: 'https://shop.test/' };
  win.jluxeCurrentAuthStatus = false;
  win.eval(script);
  const a = win.document.createElement('a');
  a.href = '/product/two/'; a.setAttribute('data-jluxe-product-link', '');
  win.document.body.appendChild(a);
  a.dispatchEvent(new win.FocusEvent('focusin', { bubbles: true }));
  const rule = JSON.parse(win.document.querySelector('script[type="speculationrules"]').textContent);
  assert.deepEqual(rule.prefetch[0].urls, ['https://shop.test/product/two/']);
  assert.equal(rule.prerender, undefined);
  assert.equal(win.document.querySelector('link[rel="prefetch"]'), null);
  assert.equal(win.document.querySelectorAll('link[data-jluxe-page-prefetch]').length, 1);
});

test('public home/product/blog/help/search links show immediate feedback on touch intent and click',async t=>{
 for(const href of ['/', '/product/simple/', '/product/variable/', '/blog/', '/blog/article/', '/help/shipping/', '/guide/', '/?s=lamp', '/shop/?orderby=price']){
  const {win,link,run}=boot(t),a=link(href);
  a.dispatchEvent(new win.MouseEvent('pointerdown',{bubbles:true,button:0,clientX:10,clientY:10}));
  const loader=win.document.querySelector('.jluxe-page-progress');assert.ok(loader,href);assert.equal(loader.hidden,false);assert.ok(loader.querySelector('svg'));
  const click=new win.MouseEvent('click',{bubbles:true,cancelable:true,button:0});Object.defineProperty(click,'target',{value:a});win.document.dispatchEvent(click);await Promise.resolve();assert.equal(click.defaultPrevented,false);assert.equal(loader.hidden,false);run(1200);assert.equal(loader.hidden,false,'committed navigation survives preview timeout');run(5000);assert.match(loader.textContent,/بیشتر/);win.dispatchEvent(new win.Event('pagereveal'));assert.equal(loader.hidden,true);
 }
});

test('scrolling away, canceled links and abandoned touch previews clear the graphic',async t=>{
 const {win,link,run}=boot(t),a=link('/help/');
 const down=()=>a.dispatchEvent(new win.MouseEvent('pointerdown',{bubbles:true,button:0,clientX:10,clientY:10}));down();const loader=win.document.querySelector('.jluxe-page-progress');
 win.document.dispatchEvent(new win.MouseEvent('pointermove',{bubbles:true,clientX:10,clientY:40}));assert.equal(loader.hidden,true);
 down();run(1200);assert.equal(loader.hidden,true);
 down();const e=new win.MouseEvent('click',{bubbles:true,cancelable:true});Object.defineProperty(e,'target',{value:a});win.document.addEventListener('click',event=>event.preventDefault(),{once:true});win.document.dispatchEvent(e);await Promise.resolve();assert.equal(loader.hidden,true);
});

test('soft archive navigation retains indicator despite prevented click, clears at completion',async t=>{
 const {win,link}=boot(t),a=link('/blog/page/2/');const e=new win.MouseEvent('click',{bubbles:true,cancelable:true});Object.defineProperty(e,'target',{value:a});
 win.document.addEventListener('click',event=>{event.preventDefault();win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-start'));},{once:true});win.document.dispatchEvent(e);await Promise.resolve();const loader=win.document.querySelector('.jluxe-page-progress');assert.equal(loader.hidden,false);win.document.dispatchEvent(new win.Event('jluxe:soft-navigation-end'));assert.equal(loader.hidden,true);
});

test('graphic is local, nonblocking, motion-aware and does not invent a percentage',()=>{
 const css=fs.readFileSync(new URL('../assets/css/page-motion.css',import.meta.url),'utf8');assert.match(css,/pointer-events:none/);assert.match(css,/prefers-reduced-motion: reduce/);assert.match(css,/animation:none/);assert.doesNotMatch(css,/url\(|@import/);assert.doesNotMatch(script,/preventDefault\(|pushState\(|\bfetch\(/);assert.ok(script.includes("bar.setAttribute('role','status')"));
});

test('configured same-origin logo sits inside orbit, fails back to graphic, never loads external logo',t=>{
 const {win,link}=boot(t,{logo:'https://shop.test/uploads/logo.png'});
 link('/help/').dispatchEvent(new win.MouseEvent('pointerdown',{bubbles:true,button:0}));
 const art=win.document.querySelector('.jluxe-page-orbit'),logo=art.querySelector('img');assert.ok(logo);assert.equal(logo.alt,'');assert.equal(art.classList.contains('has-logo'),false);logo.dispatchEvent(new win.Event('load'));assert.equal(art.classList.contains('has-logo'),true);logo.dispatchEvent(new win.Event('error'));assert.equal(art.classList.contains('has-logo'),false);
 const other=boot(t,{logo:'https://outside.test/logo.png'});other.link('/blog/').dispatchEvent(new other.win.MouseEvent('pointerdown',{bubbles:true,button:0}));assert.equal(other.win.document.querySelector('.jluxe-page-orbit img'),null);
 const css=fs.readFileSync(new URL('../assets/css/page-motion.css',import.meta.url),'utf8');assert.match(css,/jluxe-page-progress-label\{position:absolute;width:1px;height:1px/);assert.doesNotMatch(css,/jluxe-logo-shine|jluxe-page-logo::after/);assert.match(css,/background:transparent/);assert.match(css,/box-shadow:none/);
});
