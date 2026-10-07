import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import jquery from 'jquery';
import { JSDOM, VirtualConsole } from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const utils=fs.readFileSync(new URL('../assets/js/storefront-utils.js',import.meta.url),'utf8');
const html=(id=55)=>`<div class="jluxe-pa fixed hidden" data-jluxe-suggested-modal aria-hidden="true"><div class="jluxe-pa-sheet" role="dialog" aria-modal="true"><button type="button" data-jluxe-suggested-close>بستن</button><button data-pa-product="${id}"><span class="jluxe-pa-name">پیشنهاد ${id}</span></button></div></div>`;
const response=(data,status=200)=>({ok:status<400,status,json:async()=>data});
const settle=()=>new Promise(resolve=>setTimeout(resolve,25));
function boot(t,width=390){
 const errors=[];const virtualConsole=new VirtualConsole();virtualConsole.on('jsdomError',e=>errors.push(e.message));
 const dom=new JSDOM('<!doctype html><body><h1 data-jluxe-product-title>محصول اصلی</h1><form class="cart" data-product_id="50"><input type="hidden" name="add-to-cart" value="50"><input name="quantity" value="1"><button type="submit" class="single_add_to_cart_button">افزودن</button></form><button id="native" data-product_id="60">افزودن در گرید</button></body>',{url:'https://shop.test/product/example/',runScripts:'outside-only',pretendToBeVisual:true,virtualConsole});
 t.after(()=>dom.window.close());const {window}=dom;window.innerWidth=width;window.jQuery=jquery(window);
 window.JLuxeThemeSettings={cart:{ajaxUrl:'/ajax',nonce:'old'},rest:{sessionUrl:'/session'}};window.jluxeWcSettings={cartUrl:'/cart'};
 window.eval(utils);window.eval(source);
 return {window,errors,form:window.document.querySelector('form'),button:window.document.querySelector('form button')};
}
for(const width of [390,1366])test(`R167 a successful main add opens one popup automatically at ${width}px, with no toast covering it`,async t=>{
 const {window,errors,form,button}=boot(t,width);let calls=0;
 window.fetch=async(_url,options)=>{calls++;assert.equal(options.body.get('product_id'),'50');assert.equal(options.body.has('add-to-cart'),false);return response({success:true,data:{items:[],itemCount:1,suggested_html:html()}});};
 button.focus();form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.equal(calls,1,'duplicate submissions do not create another cart addition');
 let modal=window.document.querySelector('[data-jluxe-suggested-modal]');assert.equal(modal.getAttribute('aria-hidden'),'false');assert.equal(window.document.body.style.overflow,'hidden');assert.equal(window.document.querySelector('.jluxe-toast'),null);
 assert.ok(modal.contains(window.document.activeElement));window.document.dispatchEvent(new window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));assert.equal(modal.getAttribute('aria-hidden'),'true');assert.equal(window.document.body.style.overflow,'');assert.equal(window.document.activeElement,button);
 form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(window.document.querySelectorAll('[data-jluxe-suggested-modal]').length,1);assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]').getAttribute('aria-hidden'),'false');assert.deepEqual(errors,[]);
});
test('R167 failed and explicitly disabled adds never open recommendations',async t=>{
 const {window,form}=boot(t);window.fetch=async()=>response({success:false,data:{message:'موجود نیست'}},409);
 form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]'),null);
 window.fetch=async()=>response({success:true,data:{items:[],itemCount:1,suggested_html:''}});
 form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]'),null);assert.ok(window.document.querySelector('.jluxe-toast'));
});
test('R167 native Woo grid events read fresh recommendations without replaying the add',async t=>{
 const {window}=boot(t);const requests=[];
 window.fetch=async(_url,options)=>{requests.push(options.body.get('op'));assert.equal(options.body.get('include_suggestions'),'1');assert.equal(options.body.get('pa_context_id'),'60');return response({success:true,data:{items:[],itemCount:1,suggested_html:html(61)}});};
 window.jQuery(window.document.body).trigger('added_to_cart',[null,null,window.jQuery('#native')]);await settle();
 assert.deepEqual(requests,['get']);assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]').getAttribute('aria-hidden'),'false');
 const button=window.document.querySelector('[data-pa-product]');
 window.jQuery(window.document.body).trigger('added_to_cart',[null,null,window.jQuery(button),{items:[],itemCount:2,suggested_html:html(99)}]);
 assert.equal(window.document.querySelector('[data-pa-product]').getAttribute('data-pa-product'),'61','an offer add does not recursively replace/reopen suggestions');
});
test('R167 only the latest native add may display its asynchronous recommendations',async t=>{
 const {window}=boot(t);const pending=[];window.fetch=()=>new Promise(resolve=>pending.push(resolve));
 const native=window.document.getElementById('native');
 window.jQuery(window.document.body).trigger('added_to_cart',[null,null,window.jQuery(native)]);native.dataset.product_id='70';window.jQuery(window.document.body).trigger('added_to_cart',[null,null,window.jQuery(native)]);
 pending[1](response({success:true,data:{items:[],itemCount:2,suggested_html:html(71)}}));await settle();pending[0](response({success:true,data:{items:[],itemCount:1,suggested_html:html(61)}}));await settle();assert.equal(window.document.querySelector('[data-pa-product]').getAttribute('data-pa-product'),'71');
});
test('R167 cached nonce rejection refreshes identity once before one successful mutation',async t=>{
 const {window,form}=boot(t);const calls=[];
 window.fetch=async(url,options)=>{calls.push({url,nonce:options.body.get('nonce'),cache:options.cache});if(url==='/session')return response({success:true,data:{cartNonce:'fresh'}});if(options.body.get('nonce')==='old')return response({success:false,data:{code:'jluxe_cart_invalid_nonce'}},403);return response({success:true,data:{suggested_html:html()}});};
 form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.deepEqual(calls.map(c=>c.url),['/ajax','/session','/ajax']);assert.equal(calls[2].nonce,'fresh');assert.ok(calls.every(c=>c.cache==='no-store'));assert.equal(window.JLuxeThemeSettings.cart.nonce,'fresh');assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]').getAttribute('aria-hidden'),'false');
});
for(const mode of ['network','malformed','500','repeat-nonce'])test(`R167 ${mode} does not cause an unsafe add retry`,async t=>{
 const {window}=boot(t);let calls=0;window.fetch=async(url)=>{calls++;if(mode==='network')throw new Error('Network');if(mode==='malformed')return{status:200,ok:true,json:async()=>{throw new Error('Invalid JSON');}};if(mode==='500')return response({success:false,data:{}},500);if(url==='/session')return response({success:true,data:{cartNonce:'fresh'}});return response({success:false,data:{code:'jluxe_cart_invalid_nonce'}},403);};
 const body=new window.URLSearchParams({op:'add',nonce:'old'});try{await window.jluxeCartRequest(window.JLuxeThemeSettings.cart,body);}catch{}
 assert.equal(calls,mode==='repeat-nonce'?3:1);assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]'),null);
});

test('R167 dismissing native add feedback cancels a late popup but still updates the cart snapshot',async t=>{
 const {window}=boot(t);let resolve;let count;
 window.fetch=()=>new Promise(done=>{resolve=done;});
 window.addEventListener('jluxe:cart-updated',e=>{count=e.detail?.itemCount;});
 window.jQuery(window.document.body).trigger('added_to_cart',[null,null,window.jQuery('#native')]);
 window.document.querySelector('.jluxe-toast-continue').click();
 resolve(response({success:true,data:{items:[],itemCount:3,suggested_html:html(61)}}));await settle();
 assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]'),null);
 assert.equal(count,3);
});

test('R167 a normal quick-variable add hands off to recommendations after closing the picker',async t=>{
 const {window}=boot(t);
 const trigger=window.document.createElement('button');trigger.setAttribute('data-jluxe-quick-variant','80');window.document.body.appendChild(trigger);
 const formHtml='<form class="variations_form" data-product_id="80"><input name="add-to-cart" value="80" type="hidden"><input name="variation_id" value="81" type="hidden"><input name="quantity" value="1"><button type="submit" class="single_add_to_cart_button">افزودن تنوع</button></form>';
 const ops=[];window.fetch=async(_url,{body})=>{ops.push(body.get('action'));return response(body.get('action')==='jluxe_variation_picker'?{success:true,data:{image:'/image.jpg',url:'/product/80',name:'تنوع',html:formHtml}}:{success:true,data:{items:[],itemCount:1,suggested_html:html(82)}});};
 trigger.click();await settle();const form=window.document.querySelector('.jluxe-variant-modal form');assert.ok(form);
 form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await settle();
 assert.deepEqual(ops,['jluxe_variation_picker','jluxe_cart']);assert.equal(window.document.querySelector('.jluxe-variant-modal-backdrop'),null);
 assert.equal(window.document.querySelector('[data-jluxe-suggested-modal]').getAttribute('aria-hidden'),'false');assert.equal(window.document.body.style.overflow,'hidden');
});
