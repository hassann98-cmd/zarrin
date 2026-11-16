import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const read=p=>fs.readFileSync(new URL('../'+p,import.meta.url),'utf8');
const utils=read('assets/js/storefront-utils.js');
const response=data=>({ok:true,status:200,json:async()=>data});
const turn=()=>new Promise(resolve=>setImmediate(resolve));
function boot(t,html=''){
 const dom=new JSDOM(html,{url:'https://shop.test/account',runScripts:'outside-only'});t.after(()=>dom.window.close());const w=dom.window;w.eval(utils);const timer=w.setTimeout.bind(w);w.setTimeout=(fn,ms)=>timer(fn,ms===20000?10:ms);return w;
}
test('shared deadline includes a stalled JSON body, aborts and never resolves a late reply',async t=>{
 const w=boot(t);let late,signal,calls=0;w.fetch=async(_url,opts)=>{calls++;signal=opts.signal;return {json:()=>new Promise(resolve=>{late=resolve;})};};
 await assert.rejects(w.JLuxeStorefrontUtils.requestJson('/api',{}),e=>e.code==='jluxe_response_timeout');assert.equal(signal.aborted,true);late({success:true});await turn();assert.equal(calls,1);
});
test('a synchronous network throw is a rejected request rather than a leaked timer',async t=>{
 const w=boot(t);w.fetch=()=>{throw Error('offline');};await assert.rejects(w.JLuxeStorefrontUtils.requestJson('/api',{}),e=>e.code==='jluxe_response_unknown');
});
async function api(t){
 const oldWindow=globalThis.window,oldFetch=globalThis.fetch;globalThis.window={location:{href:'https://shop.test/'},JLuxeThemeSettings:{rest:{root:'https://shop.test/wp-json/jluxe/v1/',sessionUrl:'/session'}}};t.after(()=>{globalThis.window=oldWindow;globalThis.fetch=oldFetch;});return import(`../src/lib/api.js?journey=${Math.random()}`);
}
test('OTP send/verify keep their distinct real contracts and never accept malformed successful replies',async t=>{
 const a=await api(t);let calls=0;
 for(const payload of [null,{}, {success:false},{success:true}]){globalThis.fetch=async()=>{calls++;return response(payload);};await assert.rejects(a.restRequest('auth/otp-request',{}, {authenticated:false}));}
 globalThis.fetch=async()=>response({sent:true,expiresIn:120,codeLength:6});assert.equal((await a.restRequest('auth/otp-request',{}, {authenticated:false})).sent,true);
 for(const path of ['auth/login','auth/register','auth/otp-verify']){globalThis.fetch=async()=>response({success:true});assert.equal((await a.restRequest(path,{}, {authenticated:false})).success,true);globalThis.fetch=async()=>response({sent:true});await assert.rejects(a.restRequest(path,{}, {authenticated:false}));}assert.equal(calls,4);
});
test('a truncated successful OTP body rejects without resending the SMS',async t=>{
 const a=await api(t);let calls=0;globalThis.fetch=async()=>{calls++;return {ok:true,json:async()=>{throw Error('truncated');}};};await assert.rejects(a.restRequest('auth/otp-request',{}, {authenticated:false}));assert.equal(calls,1);
});
test('session timeout releases the shared promise and an old response cannot publish stale identity',async t=>{
 const a=await api(t);const nativeTimer=globalThis.setTimeout;t.after(()=>globalThis.setTimeout=nativeTimer);globalThis.setTimeout=(fn,ms,...args)=>nativeTimer(fn,ms===20000?10:ms,...args);let late,calls=0;const identities=[];window.jluxePublishAuthStatus=value=>identities.push(value);
 globalThis.fetch=()=>{calls++;return new Promise(resolve=>{late=resolve;});};const first=a.getSession();assert.equal(first,a.getSession(true));await assert.rejects(first);
 globalThis.fetch=async()=>{calls++;return response({success:true,data:{auth:{isLoggedIn:false}}});};await a.getSession(true);late(response({success:true,data:{auth:{isLoggedIn:true}}}));await turn();assert.deepEqual(identities,[false]);assert.equal(calls,2);
});
function link(t){const w=boot(t,'<form data-jluxe-phone-link data-session-url="/session" data-confirm-url="/confirm" data-request-url="/request"><input name="phone" value="09120000000"><input name="code" value="123456"><button type="button" data-send-code>Send</button><button type="submit">Link</button><p data-link-status></p></form>');w.eval(read('assets/js/phone-link.js'));return w;}
test('phone linking cannot report success:false as verified and accepts the actual OTP send contract',async t=>{
 const w=link(t);w.fetch=async url=>response(url==='/session'?{success:true,data:{restNonce:'ok'}}:{success:false,message:'rejected'});
 w.document.querySelector('form').dispatchEvent(new w.Event('submit',{cancelable:true}));await turn();assert.equal(w.document.querySelector('[data-link-status]').textContent,'rejected');assert.equal(w.document.querySelector('input[name=code]').value,'123456');
 w.fetch=async url=>response(url==='/session'?{success:true,data:{restNonce:'ok'}}:{sent:true,expiresIn:120,codeLength:6});w.document.querySelector('[data-send-code]').click();await turn();assert.match(w.document.querySelector('[data-link-status]').textContent,/ارسال شد/);
});
test('stalled phone-link request releases controls and does not retry',async t=>{
 const w=link(t);let calls=0;w.fetch=()=>{calls++;return new Promise(()=>{});};w.document.querySelector('[data-send-code]').click();await new Promise(resolve=>setTimeout(resolve,25));assert.equal(calls,1);assert.equal(w.document.querySelector('button').disabled,false);assert.doesNotMatch(w.document.querySelector('[data-link-status]').textContent,/ارسال شد/);
});
test('stalled tracking session releases submit and a later private response cannot repopulate history-cleared UI',async t=>{
 const w=boot(t,'<div id="jluxe-track-order-app"></div>');w.JLuxeOrderTracking={endpoint:'/track-api',sessionUrl:'/session'};let late,calls=0;w.fetch=()=>{calls++;return new Promise(resolve=>{late=resolve;});};w.eval(read('assets/js/order-tracking.js'));w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
 const submit=()=>{w.document.querySelector('#jto-order-number').value='123';w.document.querySelector('#jto-phone').value='09120000000';w.document.querySelector('form').dispatchEvent(new w.Event('submit',{cancelable:true}));};submit();await new Promise(resolve=>setTimeout(resolve,25));assert.equal(w.document.querySelector('#jto-submit-btn').disabled,false);assert.equal(calls,1);assert.ok(w.document.querySelector('#jto-retry-btn'));w.dispatchEvent(new w.Event('pagehide'));late(response({success:true,data:{restNonce:'old'}}));await turn();assert.equal(calls,1);assert.equal(w.document.querySelector('#jto-result-area').textContent,'');
});
