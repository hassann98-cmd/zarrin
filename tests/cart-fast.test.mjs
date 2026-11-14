import test from 'node:test';
import assert from 'node:assert/strict';
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { JSDOM } from 'jsdom';
import { CartHourglass } from '../src/components/cart-hourglass.js';

const data = (qty = 1) => ({ items: qty ? [{ key: 'one', qty, maxQty: 9, lineTotalHtml: `price-${qty}` }] : [], itemCount: qty, subtotalHtml: `total-${qty}` });
const json = (qty) => ({ ok: true, json: async () => ({ success: true, data: data(qty) }) });
async function boot(t) {
  const dom = new JSDOM('<div id="root"></div>', { url: 'https://shop.test/' });
  const keys = ['window', 'document', 'navigator', 'fetch', 'IS_REACT_ACT_ENVIRONMENT'];
  const saved = keys.map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]);
  for (const [key, value] of Object.entries({ window: dom.window, document: dom.window.document, navigator: dom.window.navigator, IS_REACT_ACT_ENVIRONMENT: true })) {
    Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
  }
  dom.window.JLuxeThemeSettings = { cart: { ajaxUrl: '/cart-ajax', nonce: 'test' } };
  const api = await import(`../src/lib/use-cart.js?fast=${Math.random()}`);
  let root;
  t.after(async () => {
    if (root) await act(async () => root.unmount());
    dom.window.close();
    for (const [key, descriptor] of saved) {
      if (descriptor) Object.defineProperty(globalThis, key, descriptor);
      else delete globalThis[key];
    }
  });
  let current;
  return {
    ...api,
    get current() { return current; },
    async mount() {
      function Consumer() {
        current = api.u();
        return React.createElement('output', null, `${current.snapshot.itemCount}|${current.snapshot.subtotalHtml}|${current.loading}`);
      }
      root = createRoot(document.getElementById('root'));
      await act(async () => root.render(React.createElement(Consumer)));
    },
  };
}

test('simultaneous plain cart reads share a request, but a write separates reads', async t => {
  const cart = await boot(t);
  const operations = [];
  globalThis.fetch = async (_url, options) => {
    operations.push(options.body.get('op'));
    return json(1);
  };
  const a = cart.cartRequest('get');
  assert.equal(a, cart.cartRequest('get'));
  await a;
  const b = cart.cartRequest('get');
  const write = cart.cartRequest('remove', { key: 'two' });
  const c = cart.cartRequest('get');
  assert.notEqual(b, c);
  await Promise.all([b, write, c]);
  assert.deepEqual(operations, ['get', 'get', 'remove', 'get']);
});

test('rapid quantity taps update the count immediately and send only the final quantity; totals remain server-confirmed', async t => {
  const cart = await boot(t);
  const writes = [];
  let finish;
  globalThis.fetch = async (_url, options) => {
    if (options.body.get('op') === 'get') return json(1);
    writes.push(options.body.get('qty'));
    return new Promise(resolve => { finish = resolve; });
  };
  await cart.mount();
  let promises;
  await act(async () => { promises = [cart.current.updateQty('one', 2), cart.current.updateQty('one', 3), cart.current.updateQty('one', 4)]; });
  assert.equal(cart.current.snapshot.items[0].qty, 4);
  assert.equal(cart.current.snapshot.itemCount, 4);
  assert.equal(cart.current.snapshot.subtotalHtml, 'total-1');
  assert.equal(cart.current.loading, true);
  await act(async () => new Promise(resolve => setTimeout(resolve, 180)));
  assert.deepEqual(writes, ['4']);
  await act(async () => { finish(json(4)); await Promise.all(promises); });
  assert.equal(cart.current.snapshot.subtotalHtml, 'total-4');
  assert.equal(cart.current.loading, false);
});

test('new taps during a quantity write survive its older reply; a rejected next write rolls back without replay', async t => {
  const cart = await boot(t);
  const writes = [];
  let finish;
  globalThis.fetch = async (_url, options) => {
    if (options.body.get('op') === 'get') return json(1);
    writes.push(options.body.get('qty'));
    return new Promise(resolve => { finish = resolve; });
  };
  await cart.mount();
  let first, second;
  await act(async () => { first = cart.current.updateQty('one', 2); });
  await act(async () => new Promise(resolve => setTimeout(resolve, 180)));
  await act(async () => { second = cart.current.updateQty('one', 3); });
  await act(async () => { finish(json(2)); await Promise.resolve(); });
  assert.equal(cart.current.snapshot.items[0].qty, 3);
  assert.equal(cart.current.loading, true);
  await act(async () => new Promise(resolve => setTimeout(resolve, 180)));
  await act(async () => {
    finish({ ok: false, json: async () => ({ success: false, data: { message: 'موجودی کافی نیست' } }) });
    await Promise.all([first, second]);
  });
  assert.deepEqual(writes, ['2', '3']);
  assert.equal(cart.current.snapshot.items[0].qty, 2);
  assert.equal(cart.current.snapshot.subtotalHtml, 'total-2');
  assert.equal(cart.current.error, 'موجودی کافی نیست');
  assert.equal(cart.current.loading, false);
});

test('hourglass has an accessible status without visible waiting prose', () => {
  const html = renderToStaticMarkup(React.createElement(CartHourglass));
  assert.match(html, /role="status"/);
  assert.match(html, /aria-label="در حال به‌روزرسانی سبد خرید"/);
  assert.match(html, /<svg/);
  assert.doesNotMatch(html, />در حال/);
});
