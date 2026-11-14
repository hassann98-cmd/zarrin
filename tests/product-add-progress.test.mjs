import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const start=source.indexOf('/** Immediate, truthful product-add feedback;');
const end=source.indexOf('/** Retry a cart request',start);
function boot(t){
 const dom=new JSDOM('<h1 class="product_title">Ring</h1><form class="cart"><select name="attribute_color"><option value="black">Black</option><option value="white">White</option></select><button class="single_add_to_cart_button">Add</button></form><button data-jluxe-mobile-bar-add>Add</button>',{url:'https://shop.test/product/ring',runScripts:'outside-only'});
 t.after(()=>dom.window.close());const w=dom.window;w.JLuxeThemeSettings={cart:{cartUrl:'/basket/'}};w.eval(source.slice(start,end));return w;
}
function data(w,id='10'){return new w.URLSearchParams({product_id:'1',variation_id:id,quantity:'1',attribute_color:id==='10'?'black':'white'});}
test('immediate pending feedback, same selection guard and server-only confirmation',t=>{
 const w=boot(t),form=w.document.querySelector('form'),button=form.querySelector('button');
 const op=w.jluxeBeginProductAdd(form,button,data(w));
 assert.equal(form.querySelector('[data-jluxe-add-progress]').hidden,true);assert.equal(button.dataset.jluxeAddState,'pending');assert.equal(button.getAttribute('aria-busy'),'true');
 assert.equal(w.jluxeBeginProductAdd(form,button,data(w)),null);
 op.finish('success','Confirmed');assert.equal(button.getAttribute('aria-busy'),null);assert.equal(button.dataset.jluxeAddState,'success');
 assert.ok(w.jluxeBeginProductAdd(form,button,data(w)));
});
test('different variations queue immutable operations; earlier completion cannot clear latest busy feedback',async t=>{
 const w=boot(t),f=w.document.querySelector('form'),b=f.querySelector('button'),calls=[];
 const first=w.jluxeBeginProductAdd(f,b,data(w));let release;
 const a=first.run(()=>{calls.push('black');return new Promise(resolve=>{release=resolve;});});
 f.querySelector('select').value='white';const second=w.jluxeBeginProductAdd(f,b,data(w,'11'));
 const c=second.run(()=>{calls.push('white');});await Promise.resolve();assert.deepEqual(calls,['black']);
 first.finish('success','Done');assert.equal(b.dataset.jluxeAddState,'pending');assert.equal(b.getAttribute('aria-busy'),'true');
 release();await a;await c;assert.deepEqual(calls,['black','white']);second.finish('success','Done');
 assert.equal(f.querySelectorAll('[data-state="success"]').length,1);assert.equal(f.querySelector('[data-state="success"]').textContent,'به سبد اضافه شد');
});
test('definite errors allow retry, uncertain results stay guarded with cart inspection link',t=>{
 const w=boot(t),f=w.document.querySelector('form'),b=f.querySelector('button');
 w.jluxeBeginProductAdd(f,b,data(w)).finish('error','Out of stock');
 const retry=w.jluxeBeginProductAdd(f,b,data(w));assert.ok(retry);retry.finish('uncertain','Check cart');
 assert.equal(w.jluxeBeginProductAdd(f,b,data(w)),null);assert.equal(f.querySelector('a').getAttribute('href'),'/basket/');
});

test('real product submit snapshots variation, prevents duplicate clicks and serializes a second selection',async t=>{
 const w=boot(t),f=w.document.querySelector('form'),b=f.querySelector('button');
 f.classList.add('variations_form');f.dataset.product_id='1';b.value='1';
 const input=w.document.createElement('input');input.name='variation_id';input.value='10';f.appendChild(input);
 w.JLuxeThemeSettings.cart.ajaxUrl='/ajax';w.JLuxeThemeSettings.cart.nonce='ok';
 const events=[];function jq(){return {trigger:(name,args)=>events.push([name,args])};}w.jQuery=jq;
 const calls=[];let release;w.jluxeCartPost=(url,body)=>{calls.push(Object.fromEntries(body));return new Promise(resolve=>{release=resolve;});};
 const marker=source.indexOf('افزودن به سبد در فرمِ صفحه‌ی تکیِ محصول');const a=source.indexOf('(function () {',marker),z=source.indexOf('\n})();\n\n/**\n * مودالِ انتخاب سریعِ تنوع',a);
 assert.ok(a>0&&z>a);w.eval(source.slice(a,z+'\n})();'.length));
 function submit(){f.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));}
 submit();submit();await Promise.resolve();assert.equal(calls.length,1);assert.equal(calls[0].variation_id,'10');
 input.value='11';f.querySelector('select').value='white';submit();await Promise.resolve();assert.equal(calls.length,1);
 release({success:true,data:{itemCount:1}});await new Promise(resolve=>setTimeout(resolve,5));assert.equal(calls.length,2);assert.equal(calls[1].variation_id,'11');assert.equal(calls[1].attribute_color,'white');assert.equal(events.length,0);
 release({success:true,data:{itemCount:2}});await new Promise(resolve=>setTimeout(resolve,5));assert.equal(events.length,1);assert.equal(events[0][0],'added_to_cart');assert.equal(b.getAttribute('aria-busy'),null);
});

test('confirmation replaces history and old timers cannot dismiss a newer success',t=>{
 const w=boot(t),f=w.document.querySelector('form'),b=f.querySelector('button'),timers=[];
 w.setTimeout=(callback,delay)=>{timers.push({callback,delay});return timers.length;};w.clearTimeout=()=>{};
 w.jluxeBeginProductAdd(f,b,data(w)).finish('success','ignored long product details');
 const panel=f.querySelector('[data-jluxe-add-progress]');assert.equal(panel.textContent,'به سبد اضافه شد');
 const old=timers.find(timer=>timer.delay===2600);
 const second=w.jluxeBeginProductAdd(f,b,data(w,'11'));assert.equal(panel.textContent,'');assert.equal(panel.hidden,true);
 second.finish('success','another product');old.callback();assert.equal(panel.hidden,false);assert.equal(panel.querySelectorAll('p:not([hidden])').length,1);
 timers.filter(timer=>timer.delay===2600).at(-1).callback();assert.equal(panel.hidden,true);
});

test('success moves inside an actually open populated recommendations sheet; disabled/empty/hidden keep fallback',t=>{
 const w=boot(t),f=w.document.querySelector('form'),b=f.querySelector('button');
 w.jluxeBeginProductAdd(f,b,data(w)).finish('success','ok');
 const panel=f.querySelector('[data-jluxe-add-progress]');
 assert.equal(w.jluxePlaceAddConfirmation(null,b),false);assert.equal(panel.hidden,false);
 const modal=w.document.createElement('div');modal.className='hidden';modal.innerHTML='<div class="jluxe-pa-sheet" role="dialog"><div data-pa-product="2"></div></div>';w.document.body.appendChild(modal);
 assert.equal(w.jluxePlaceAddConfirmation(modal,b),false);assert.equal(panel.hidden,false);
 modal.className='';modal.querySelector('[data-pa-product]').remove();assert.equal(w.jluxePlaceAddConfirmation(modal,b),false);
 modal.querySelector('.jluxe-pa-sheet').innerHTML='<div data-pa-service="1"></div>';
 assert.equal(w.jluxePlaceAddConfirmation(modal,b),true);assert.equal(panel.hidden,true);assert.equal(modal.querySelector('[data-jluxe-inline-confirmation]').textContent,'به سبد اضافه شد');
 w.jluxeBeginProductAdd(f,b,data(w,'11'));assert.equal(modal.querySelector('[data-jluxe-inline-confirmation]'),null);
});
