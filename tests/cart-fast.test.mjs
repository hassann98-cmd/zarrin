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
  dom.window.JLuxeThemeSettings = { cart: { ajaxUrl: '/cart-ajax', nonce: 'test', initialCount: 1 } };
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
  await act(async () => new Promise(resolve => setTimeout(resolve, 80)));
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
  await act(async () => new Promise(resolve => setTimeout(resolve, 80)));
  await act(async () => { second = cart.current.updateQty('one', 3); });
  await act(async () => { finish(json(2)); await Promise.resolve(); });
  assert.equal(cart.current.snapshot.items[0].qty, 3);
  assert.equal(cart.current.loading, true);
  await act(async () => new Promise(resolve => setTimeout(resolve, 80)));
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

test('lost removal response reconciles with a read instead of restoring a known stale cart or replaying',async t=>{
 const cart=await boot(t),ops=[];let removed=false;
 globalThis.fetch=async(_url,options)=>{const op=options.body.get('op');ops.push(op);if(op==='remove'){removed=true;throw Error('lost after commit');}return json(removed?0:1);};await cart.mount();let ok;
 await act(async()=>{ok=await cart.current.removeItem('one');});assert.equal(ok,false);assert.equal(cart.current.snapshot.itemCount,0);assert.equal(cart.current.loading,false);assert.match(cart.current.error,/بررسی/);assert.deepEqual(ops,['get','remove','get']);
});
test('lost quantity response reconciles server quantity and never replays later queued taps',async t=>{
 const cart=await boot(t),ops=[];let qty=1;
 globalThis.fetch=async(_url,options)=>{const op=options.body.get('op');ops.push(op);if(op==='update_qty'){qty=Number(options.body.get('qty'));throw Error('lost');}return json(qty);};await cart.mount();let p;
 await act(async()=>{p=cart.current.updateQty('one',3);});await act(async()=>{await p;});assert.equal(cart.current.snapshot.items[0].qty,3);assert.equal(cart.current.loading,false);assert.deepEqual(ops,['get','update_qty','get']);
});
test('failed reconciliation stays explicitly unknown and releases the drawer',async t=>{
 const cart=await boot(t);let calls=0;globalThis.fetch=async()=>{if(++calls===1)return json(1);throw Error('offline');};await cart.mount();await act(async()=>{await cart.current.removeItem('one');});assert.equal(cart.current.loading,false);assert.match(cart.current.error,/مشخص نیست/);assert.equal(calls,3);
});

test('malformed successful snapshots never report a completed mutation and recover by GET only',async t=>{
 const cart=await boot(t);const malformed=[undefined,null,{}, {items:[],itemCount:-1,subtotalHtml:''}, {items:null,itemCount:0,subtotalHtml:''},{items:[null],itemCount:1,subtotalHtml:''},{items:[],itemCount:0}];
 for(const payload of malformed){const ops=[];globalThis.fetch=async(_url,options)=>{const op=options.body.get('op');ops.push(op);return op==='get'?json(0):{ok:true,status:200,json:async()=>({success:true,data:payload})};};const result=await cart.cartRequest('remove',{key:'one'});assert.ok(result.error);assert.equal(result.uncertain,true);assert.equal(result.snapshot.itemCount,0);assert.deepEqual(ops,['remove','get']);}
});
test('a late reconciliation read cannot overwrite a newer externally confirmed cart',async t=>{
 const cart=await boot(t);globalThis.fetch=async()=>json(1);await cart.mount();let readStarted,lateRead;const started=new Promise(resolve=>{readStarted=resolve;});
 globalThis.fetch=async(_url,options)=>{if(options.body.get('op')==='remove')throw Error('lost');readStarted();return new Promise(resolve=>{lateRead=resolve;});};let p;
 await act(async()=>{p=cart.current.removeItem('one');await started;});
 await act(async()=>{window.dispatchEvent(new window.CustomEvent('jluxe:cart-updated',{detail:data(4)}));await Promise.resolve();});
 await act(async()=>{lateRead(json(0));await p;});assert.equal(cart.current.snapshot.itemCount,4);assert.ok(cart.current.error);assert.equal(cart.current.loading,false);
});
test('drawer deadline covers stalled body, bounds reconciliation, ignores late responses and releases queue',async t=>{
 const cart=await boot(t);const nativeTimer=globalThis.setTimeout;let bodyFinish,calls=0;globalThis.fetch=async()=>json(1);await cart.mount();
 t.after(()=>{globalThis.setTimeout=nativeTimer;});globalThis.setTimeout=(fn,ms,...args)=>nativeTimer(fn,ms===20000?5:ms,...args);
 globalThis.fetch=async()=>{calls++;return {ok:true,status:200,json:()=>new Promise(resolve=>{bodyFinish=resolve;})};};let result;
 await act(async()=>{result=await cart.current.removeItem('one');});assert.equal(result,false);assert.equal(cart.current.loading,false);assert.equal(calls,2);assert.match(cart.current.error,/مشخص نیست/);
 bodyFinish({success:true,data:data(0)});await Promise.resolve();assert.equal(cart.current.snapshot.itemCount,1);
 globalThis.fetch=async()=>json(2);const next=await cart.cartRequest('get');assert.equal(next.snapshot.itemCount,2);
});

test('malformed cart events are read-only refresh hints, never published as cart truth',async t=>{
 const cart=await boot(t);globalThis.fetch=async()=>json(1);await cart.mount();let reads=0;
 const invalid=[{items:[null],itemCount:99,subtotalHtml:'bad'},{items:[],itemCount:0},{items:[],itemCount:0.5,subtotalHtml:'bad'},{items:[{key:'one',qty:-1}],itemCount:1,subtotalHtml:'bad'}];
 for(const detail of invalid){let finish;globalThis.fetch=()=>{reads++;return new Promise(resolve=>{finish=resolve;});};
 await act(async()=>{window.dispatchEvent(new window.CustomEvent('jluxe:cart-updated',{detail}));await Promise.resolve();await Promise.resolve();});
 assert.equal(cart.current.snapshot.itemCount,1,'invalid event must not replace confirmed state');assert.equal(cart.current.loading,true);
 await act(async()=>{finish(json(1));});assert.equal(cart.current.loading,false);
 }assert.equal(reads,invalid.length);
});
test('valid complete cart events update immediately without another network read',async t=>{
 const cart=await boot(t);globalThis.fetch=async()=>json(1);await cart.mount();let reads=0;globalThis.fetch=async()=>{reads++;return json(9);};
 await act(async()=>{window.dispatchEvent(new window.CustomEvent('jluxe:cart-updated',{detail:data(3)}));});assert.equal(cart.current.snapshot.itemCount,3);assert.equal(cart.current.loading,false);assert.equal(reads,0);
});
