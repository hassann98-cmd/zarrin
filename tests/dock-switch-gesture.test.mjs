import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/storefront-utils.js',import.meta.url),'utf8');
for (const kind of ['mobile-bar-toggle','sticky-cta-mode-toggle']) {
 test(`${kind} owns retargeted taps without opening cart, suppresses ghosts, preserves keyboard and real cart taps`,t=>{
  const dom=new JSDOM(`<div style="transform:translateY(0)"><button data-jluxe-${kind}>Switch</button><div id="bar"></div></div><button id="cart">Cart</button>`,{url:'https://shop.test',runScripts:'outside-only'});t.after(()=>dom.window.close());const w=dom.window,d=w.document,button=d.querySelector('[data-jluxe-'+kind+']'),cart=d.querySelector('#cart');w.eval(source);
  w.JLuxeStorefrontUtils.protectDockSwitch(button,d.querySelector('#bar'));
  assert.equal(button.parentElement,d.querySelector('[data-jluxe-switch-layer]'));assert.equal(button.parentElement.parentElement,d.body);assert.equal(d.querySelector('#bar').parentElement,d.body);
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


for(const popover of [false,true])test(`switch layer isolates controls without filtering navigation; top layer support=${popover}`,()=>{
 const dom=new JSDOM('<div style="transform:translateY(0)"><button data-jluxe-mobile-bar-toggle>Switch</button><button data-jluxe-sticky-cta-mode-toggle>Switch2</button></div><nav><a>Home</a><a>Tracking</a><a>Categories</a><a>Account</a><a>Cart</a></nav>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document;let shown=0;if(popover){w.HTMLElement.prototype.showPopover=function(){shown++;};}
 w.eval(source);const buttons=[...d.querySelectorAll('button')];buttons.forEach(b=>w.JLuxeStorefrontUtils.protectDockSwitch(b));const layer=d.querySelector('[data-jluxe-switch-layer]');assert.equal(d.querySelectorAll('[data-jluxe-switch-layer]').length,1);assert.equal(layer.querySelectorAll('button').length,2);assert.equal(layer.parentElement,d.body);assert.equal(layer.getAttribute('popover'),popover?'manual':null);if(popover)assert.ok(shown>0);
 let opened=0;d.querySelectorAll('a').forEach(a=>a.onclick=()=>opened++);
 for(let pass=0;pass<5;pass++){buttons[0].click();for(const a of d.querySelectorAll('a')){a.dispatchEvent(new w.MouseEvent('pointerdown',{bubbles:true,button:0}));a.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,button:0,detail:1}));}}
 assert.equal(opened,25,'all destinations remain usable after repeated switching');
 }finally{dom.window.close();}
});
test('no blanket navigation hit-test or icon sizing overrides remain',()=>{
 const js=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');assert.doesNotMatch(js,/function boundNavigation|jluxeNavBounds/);
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');assert.doesNotMatch(css,/Cart\/account: only|Dock links own only/);const layer=css.split('/* Independent switch surface:')[1];assert.match(layer,/pointer-events:none/);assert.match(layer,/pointer-events:auto/);assert.doesNotMatch(layer,/mobile-nav-item|mobile-nav-icon/);
});
