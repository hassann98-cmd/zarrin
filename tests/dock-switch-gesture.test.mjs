import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/storefront-utils.js',import.meta.url),'utf8')+fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8').split('/* Product dock navigation is confined')[1].replace(/^.*?\*\//s,'');
for (const kind of ['mobile-bar-toggle','sticky-cta-mode-toggle']) {
 test(`${kind} owns retargeted taps without opening cart, suppresses ghosts, preserves keyboard and real cart taps`,t=>{
  const dom=new JSDOM(`<div style="transform:translateY(0)"><button data-jluxe-${kind}>Switch</button><div id="bar"></div></div><button id="cart">Cart</button>`,{url:'https://shop.test',runScripts:'outside-only'});t.after(()=>dom.window.close());const w=dom.window,d=w.document,button=d.querySelector('[data-jluxe-'+kind+']'),cart=d.querySelector('#cart');w.eval(source);
  w.JLuxeStorefrontUtils.protectDockSwitch(button,d.querySelector('#bar'));
  assert.equal(button.parentElement,d.body);assert.equal(d.querySelector('#bar').parentElement,d.body);
  let switches=0,carts=0;button.addEventListener('click',()=>switches++);cart.addEventListener('click',()=>carts++);
  const fire=(target,name,detail=1,x=20)=>target.dispatchEvent(new w.MouseEvent(name,{bubbles:true,cancelable:true,clientX:x,clientY:30,detail,button:0}));
  fire(button,'pointerdown');fire(cart,'click');assert.equal(switches,1);assert.equal(carts,0);
  fire(cart,'click');assert.equal(switches,1);assert.equal(carts,0);
  button.click();assert.equal(switches,2,'keyboard activation still works');
  fire(cart,'pointerdown');fire(cart,'click');assert.equal(carts,1,'a fresh cart tap is not blocked');
  fire(button,'pointerdown');fire(button,'pointermove',1,80);fire(cart,'click');assert.equal(switches,2,'scroll gesture is not a switch');assert.equal(carts,1);
  d.documentElement.setAttribute('data-jluxe-mobile-drawer','cart');button.click();assert.equal(switches,2,'switch cannot activate behind an open drawer');
  d.documentElement.removeAttribute('data-jluxe-mobile-drawer');button.click();assert.equal(switches,3);
 });
}
test('drawer state hides both switchers without raising their layer above the drawer',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');
 assert.match(css,/html\[data-jluxe-mobile-drawer\] body \.jluxe-mobile-bar-toggle,[\s\S]*?\.jluxe-sticky-cta-mode-toggle\{visibility:hidden;pointer-events:none;/);
});

test('pointerup switches once before a displaced compatibility click; fresh cart gesture remains usable',()=>{
 const dom=new JSDOM('<button id="switch">Switch</button><button id="cart">Cart</button>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,b=d.querySelector('#switch'),cart=d.querySelector('#cart');w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);let switched=0,opened=0;b.onclick=()=>switched++;cart.onclick=()=>opened++;
 const fire=(node,type,x=20)=>node.dispatchEvent(new w.MouseEvent(type,{bubbles:true,cancelable:true,detail:1,clientX:x,clientY:30,button:0}));
 fire(b,'pointerdown');fire(b,'pointerup');assert.equal(switched,1);fire(cart,'click',100);assert.equal(opened,0);assert.equal(switched,1);fire(cart,'pointerdown');fire(cart,'pointerup');fire(cart,'click');assert.equal(opened,1);
 }finally{dom.window.close();}
});

test('both dock switches expose named modes, selected state, and compact purchase details',()=>{
 for(const file of ['woocommerce/content-single-product.php','inc/woocommerce.php']){
  const php=fs.readFileSync(new URL('../'+file,import.meta.url),'utf8');
  assert.match(php,/jluxe-dock-choice--menu" aria-hidden="true">منو/);
  assert.match(php,/jluxe-dock-choice--purchase" aria-hidden="true">خرید/);
 }
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Explicit two-mode switch')[1];
 assert.match(css,/width:118px;height:44px/);assert.match(css,/aria-pressed="true"/);assert.match(css,/aria-pressed="false"/);
 assert.match(css,/width:26px;min-width:26px;height:34px/);assert.match(css,/align-items:flex-end;text-align:left/);assert.match(css,/:is\(del,ins\)\{width:100%;margin-inline:0;text-align:left/);
});

test('after scrolling, taps on either half of the painted switch cannot reach underlying cart or account',()=>{
 const dom=new JSDOM('<button data-jluxe-mobile-bar-toggle>Switch</button><a id="cart">Cart</a><a id="account">Account</a>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,b=d.querySelector('button');w.matchMedia=()=>({matches:true});b.getBoundingClientRect=()=>({left:20,right:138,top:500,bottom:544,width:118,height:44});w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);let switches=0,underlying=0;b.onclick=()=>switches++;for(const id of ['cart','account'])d.getElementById(id).onclick=()=>underlying++;
 function fire(el,type,x,id=1){const e=new w.MouseEvent(type,{bubbles:true,cancelable:true,clientX:x,clientY:520,button:0,detail:1});Object.defineProperty(e,'pointerId',{value:id});el.dispatchEvent(e);}
 for(const [id,x] of [['cart',30],['account',125]]){const a=d.getElementById(id);fire(a,'pointerdown',x);fire(a,'pointerup',x);fire(a,'click',x);}
 assert.equal(switches,2);assert.equal(underlying,0);
 fire(b,'pointerdown',30,3);fire(b,'pointerup',30,4);assert.equal(switches,2,'unrelated pointer cannot complete the switch');fire(b,'pointerup',30,3);assert.equal(switches,3);
 fire(d.getElementById('cart'),'pointerdown',200);fire(d.getElementById('cart'),'click',200);assert.equal(underlying,1,'fresh real navigation tap outside switch works');
 }finally{dom.window.close();}
});

for(const variant of ['standard','floating'])test(`${variant} dock rejects cart/account taps outside its bounds after scroll; inside and keyboard work`,()=>{
 const dom=new JSDOM(`<button data-jluxe-mobile-bar-toggle>Switch</button><nav class="jluxe-mobile-nav ${variant==='floating'?'jluxe-mobile-nav--floating':''}" data-jluxe-mobile-nav-dock><a class="jluxe-mobile-nav-item" data-jluxe-drawer-toggle="cart"><span class="jluxe-mobile-nav-icon">Cart</span></a><a class="jluxe-mobile-nav-item" data-jluxe-account-link><span class="jluxe-mobile-nav-icon">Account</span></a></nav>`,{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,nav=d.querySelector('nav'),links=nav.querySelectorAll('a'),b=d.querySelector('button');w.matchMedia=()=>({matches:true});w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);
 let calls=0;d.addEventListener('click',()=>calls++,true);
 function geometry(top){nav.getBoundingClientRect=()=>({left:0,right:360,top,bottom:top+68,width:360,height:68});links.forEach((link,i)=>{link.getBoundingClientRect=()=>({left:i*180,right:(i+1)*180,top,bottom:top+68,width:180,height:68});link.querySelector('.jluxe-mobile-nav-icon').getBoundingClientRect=()=>({left:i*180+30,right:i*180+74,top:top+4,bottom:top+48,width:44,height:44});});}
 const click=(node,x,y,detail=1)=>node.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,button:0,clientX:x,clientY:y,detail}));
 for(const top of [700,600]){geometry(top);const before=calls;click(links[0],50,top-30);click(links[1],250,top-30);assert.equal(calls,before,'out-of-dock hits blocked before document handlers');click(links[0],50,top+20);click(links[1],250,top+20);assert.equal(calls,before+2);click(links[0],250,top+20);assert.equal(calls,before+2,'another item cannot claim this point');}
 const before=calls;click(links[1],0,0,0);assert.equal(calls,before+1,'keyboard preserved');nav.hidden=true;click(links[0],50,620);assert.equal(calls,before+1,'hidden dock cannot open cart');
 }finally{dom.window.close();}
});

test('dock CSS clips hit regions and disables inactive navigation without increasing z-index',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split("/* Dock links own only")[1];assert.match(css,/overflow:hidden;clip-path:inset\(0 round 18px\);contain:paint/);assert.match(css,/aria-hidden="true"/);assert.match(css,/pointer-events:none;visibility:hidden/);assert.doesNotMatch(css,/z-index/);
});

test('account/cart icon only: dead cell padding and label cannot open either destination',()=>{
 const dom=new JSDOM('<button data-jluxe-mobile-bar-toggle>Switch</button><nav data-jluxe-mobile-nav-dock><a class="jluxe-mobile-nav-item" data-jluxe-account-link><span class="jluxe-mobile-nav-icon">Icon</span><span>Login</span></a><a class="jluxe-mobile-nav-item" data-jluxe-drawer-toggle="cart"><span class="jluxe-mobile-nav-icon">Icon</span><span>Cart</span></a></nav>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document;w.matchMedia=()=>({matches:true});w.eval(source);const nav=d.querySelector('nav');nav.getBoundingClientRect=()=>({left:0,right:360,top:600,bottom:668,width:360,height:68});let calls=0;d.addEventListener('click',()=>calls++,true);
 for(const [i,link] of [...d.querySelectorAll('a')].entries()){const x=i*180;link.getBoundingClientRect=()=>({left:x,right:x+180,top:600,bottom:668,width:180,height:68});const icon=link.querySelector('.jluxe-mobile-nav-icon');icon.getBoundingClientRect=()=>({left:x+68,right:x+112,top:604,bottom:648,width:44,height:44});const fire=(px,y,detail=1)=>link.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,clientX:px,clientY:y,detail,button:0}));const before=calls;fire(x+10,620);fire(x+90,660);fire(x+90,580);assert.equal(calls,before,'padding, text label and area above dock are dead');fire(x+90,620);assert.equal(calls,before+1);fire(0,0,0);assert.equal(calls,before+2,'keyboard activation preserved');}
 }finally{dom.window.close();}
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Cart/account: only')[1];assert.match(css,/pointer-events:none/);assert.match(css,/pointer-events:auto;box-sizing:border-box;width:44px;height:44px/);
});
