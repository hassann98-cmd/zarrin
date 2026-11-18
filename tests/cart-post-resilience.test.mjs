import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/woocommerce.js", import.meta.url), "utf8");
const marker = source.indexOf("/** Retry a cart request only when WordPress explicitly rejects its nonce before mutation. */");
const end = source.indexOf("\n})();", marker);
assert.ok(marker >= 0 && end > marker, "the narrowly scoped cart request helper is isolated for behavior tests");
const helperScript = source.slice(source.indexOf("(function () {", marker), end + "\n})();".length);

function boot(t) {
  const dom = new JSDOM("<!doctype html><html><body></body></html>", {
    url: "https://shop.test/product/item/",
    runScripts: "outside-only",
  });
  t.after(() => dom.window.close());
  dom.window.JLuxeThemeSettings = { rest: { sessionUrl: "https://shop.test/admin-ajax.php" } };
  dom.window.eval(helperScript);
  return dom.window;
}

function response(status, payload) {
  return { status, json: async () => payload };
}

test("cart POST refreshes a nonce once only after the explicit pre-mutation 403 code", async (t) => {
  const window = boot(t);
  const calls = [];
  const settings = { ajaxUrl: "https://shop.test/admin-ajax.php", nonce: "expired" };
  const body = new window.FormData();
  body.set("action", "jluxe_cart");
  body.set("op", "add");
  body.set("product_id", "74");
  body.set("nonce", settings.nonce);
  window.fetch = async (url, options) => {
    const params = Object.fromEntries(options.body);
    calls.push({ url, params, cache: options.cache, credentials: options.credentials });
    if (calls.length === 1) return response(403, { success: false, data: { code: "jluxe_cart_invalid_nonce" } });
    if (params.action === "jluxe_session") return response(200, { success: true, data: { cartNonce: "fresh" } });
    return response(200, { success: true, data: { itemCount: 1 } });
  };

  const result = await window.jluxeCartPost(settings.ajaxUrl, body, settings);
  assert.deepEqual(calls.map(({ params }) => params.action), ["jluxe_cart", "jluxe_session", "jluxe_cart"]);
  assert.deepEqual(calls.map(({ params }) => params.nonce), ["expired", undefined, "fresh"]);
  assert.equal(calls[0].params.op, "add");
  assert.equal(calls[2].params.op, "add", "the one permitted replay preserves the requested operation");
  assert.equal(settings.nonce, "fresh", "subsequent cart actions use the refreshed nonce");
  assert.equal(result.success, true);
  assert.ok(calls.every((call) => call.cache === "no-store" && call.credentials === "same-origin"));
});

test("cart POST never retries a non-explicit nonce failure, another status, or an ambiguous response", async (t) => {
  const window = boot(t);
  for (const failed of [
    response(403, { success: false, data: { code: "forbidden" } }),
    response(403, { success: false, data: { message: "expired" } }),
    response(500, { success: false, data: { code: "jluxe_cart_invalid_nonce" } }),
    response(409, { success: false, data: { code: "jluxe_cart_invalid_nonce" } }),
  ]) {
    let calls = 0;
    window.fetch = async () => { calls++; return failed; };
    const body = new window.URLSearchParams({ action: "jluxe_cart", op: "add", nonce: "cached" });
    if (failed.status >= 500) {
      await assert.rejects(window.jluxeCartPost("https://shop.test/admin-ajax.php", body, { nonce: "cached" }), /Ambiguous/);
    } else {
      const result = await window.jluxeCartPost("https://shop.test/admin-ajax.php", body, { nonce: "cached" });
      assert.equal(result.success, false);
    }
    assert.equal(calls, 1, "only the exact 403/code pair is retryable");
  }
});

test("cart POST never performs a second replay after a refreshed nonce is rejected", async (t) => {
  const window = boot(t);
  const calls = [];
  window.fetch = async (url, options) => {
    calls.push({ url, params: Object.fromEntries(options.body) });
    if (calls.at(-1).params.action === "jluxe_session") return response(200, { success: true, data: { cartNonce: "fresh" } });
    return response(403, { success: false, data: { code: "jluxe_cart_invalid_nonce" } });
  };
  const body = new window.URLSearchParams({ action: "jluxe_cart", op: "remove", nonce: "expired", key: "line-1" });
  const result = await window.jluxeCartPost("https://shop.test/admin-ajax.php", body, { nonce: "expired" });
  assert.equal(result.success, false);
  assert.deepEqual(calls.map(({ params }) => params.action), ["jluxe_cart", "jluxe_session", "jluxe_cart"]);
  assert.equal(calls.length, 3, "one original, one nonce read, and at most one replay");
});

test('HTTP failures and malformed payloads cannot become successful cart operations',async t=>{
 const w=boot(t);
 for(const [status,payload] of [[500,{success:true}],[403,{success:true}],[302,{success:true}],[200,{}],[200,{success:true}],[200,null]]){
  let calls=0;w.fetch=async()=>{calls++;return response(status,payload);};
  await assert.rejects(w.jluxeCartPost('/ajax',new w.URLSearchParams({op:'add'}),{}),/Ambiguous/);assert.equal(calls,1);
 }
});

test('a stalled response body expires, aborts and ignores late success without replay', async t => {
 const w=boot(t), nativeTimer=w.setTimeout.bind(w);w.setTimeout=(fn,ms)=>nativeTimer(fn,ms===20000?5:ms);
 let finishBody, signal, calls=0;
 w.fetch=async(_url,opts)=>{calls++;signal=opts.signal;return {status:200,json:()=>new Promise(resolve=>{finishBody=resolve;})};};
 await assert.rejects(w.jluxeCartPost('/ajax',new w.URLSearchParams({op:'add'}),{}),/deadline/);
 assert.equal(signal.aborted,true);assert.equal(calls,1);finishBody({success:true,data:{items:[],itemCount:1}});await Promise.resolve();assert.equal(calls,1);
});
test('a stalled nonce refresh expires and never sends the mutation with a late nonce', async t => {
 const w=boot(t), nativeTimer=w.setTimeout.bind(w);w.setTimeout=(fn,ms)=>nativeTimer(fn,ms===20000?5:ms);let calls=0,late;
 w.fetch=async()=>{calls++;return calls===1?response(403,{success:false,data:{code:'jluxe_cart_invalid_nonce'}}):{status:200,json:()=>new Promise(resolve=>{late=resolve;})};};
 const r=await w.jluxeCartPost('/ajax',new w.URLSearchParams({op:'add'}),{});assert.equal(r.success,false);late({success:true,data:{cartNonce:'late'}});await Promise.resolve();assert.equal(calls,2);
});
