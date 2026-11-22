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
  fire(cart,'click');assert.equal(switches,1);assert.equal(carts,1,'ownership was consumed by the first compatibility click');
  button.click();assert.equal(switches,2,'keyboard activation still works');
  fire(cart,'pointerdown');fire(cart,'click');assert.equal(carts,2,'a fresh cart tap is not blocked');
  fire(button,'pointerdown');fire(button,'pointermove',1,80);fire(cart,'click');assert.equal(switches,2,'scroll gesture is not a switch');assert.equal(carts,2);
  d.documentElement.setAttribute('data-jluxe-mobile-drawer','cart');button.click();assert.equal(switches,2,'switch cannot activate behind an open drawer');
  d.documentElement.removeAttribute('data-jluxe-mobile-drawer');button.click();assert.equal(switches,3);
 });
}
test('drawer state hides both switchers without raising their layer above the drawer',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');
 assert.match(css,/html\[data-jluxe-mobile-drawer\] body \.jluxe-mobile-bar-toggle,[\s\S]*?\.jluxe-sticky-cta-mode-toggle\{visibility:hidden;pointer-events:none;/);
});

test('switch ghost clicks are consumed once and clicks over 28px away are never swallowed',()=>{
 const dom=new JSDOM('<button id="switch">Switch</button><button id="cart">Cart</button>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,b=d.querySelector('#switch'),cart=d.querySelector('#cart');w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);let switched=0,opened=0;b.onclick=()=>switched++;cart.onclick=()=>opened++;
 const fire=(node,type,x=20)=>node.dispatchEvent(new w.MouseEvent(type,{bubbles:true,cancelable:true,detail:1,clientX:x,clientY:30,button:0}));
 fire(b,'pointerdown');fire(b,'pointerup');assert.equal(switched,1);
 fire(cart,'click',25);assert.equal(opened,0,'compatibility click within 28px is suppressed');
 fire(cart,'click',70);assert.equal(opened,1,'a subsequent click at a different coordinate is allowed');
 fire(b,'pointerdown');fire(b,'pointerup');fire(cart,'click',20);assert.equal(opened,1,'the switch compatibility click is consumed');
 fire(cart,'pointerdown');fire(cart,'pointerup');fire(cart,'click',20);assert.equal(opened,2,'a new cart tap immediately after the switch is usable');
 }finally{dom.window.close();}
});

test('scroll-mode pointer capture is explicitly released before cart and account navigation',()=>{
 const dom=new JSDOM('<button data-jluxe-mobile-bar-toggle>Switch</button><a id="cart" href="/cart/">Cart</a><a id="account" href="/login/">Account</a>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,b=d.querySelector('button');let captured=null,releases=0,switches=0,carts=0,accounts=0;w.scrollY=640;
  b.setPointerCapture=id=>{captured=id;};b.hasPointerCapture=id=>captured===id;b.releasePointerCapture=id=>{if(captured===id){captured=null;releases++;}};
  w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);b.onclick=()=>switches++;d.querySelector('#cart').onclick=()=>carts++;d.querySelector('#account').onclick=()=>accounts++;
  function fire(el,type,id,x=24){const e=new w.MouseEvent(type,{bubbles:true,cancelable:true,clientX:x,clientY:700,button:0,detail:type==='click'?1:0});Object.defineProperty(e,'pointerId',{value:id});el.dispatchEvent(e);}
  fire(b,'pointerdown',7);assert.equal(captured,7);fire(b,'pointerup',7);assert.equal(captured,null,'switch releases pointer capture on pointerup');assert.equal(releases,1);assert.equal(switches,1);
  fire(b,'click',7);assert.equal(switches,1,'the compatibility click from the switch is still suppressed');
  for(const [id,x] of [['cart',32],['account',110]]){const link=d.getElementById(id);fire(link,'pointerdown',id==='cart'?8:9,x);fire(link,'pointerup',id==='cart'?8:9,x);fire(link,'click',id==='cart'?8:9,x);}
  assert.equal(carts,1);assert.equal(accounts,1,'following taps reach their real navigation targets after scroll');
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

test('cart and account hit targets are never stolen by the switch bounding-box fallback',()=>{
 const dom=new JSDOM('<button data-jluxe-mobile-bar-toggle>Switch</button><a id="cart">Cart</a><a id="account">Account</a>',{runScripts:'outside-only'});
 try{const w=dom.window,d=w.document,b=d.querySelector('button');w.matchMedia=()=>({matches:true});b.getBoundingClientRect=()=>({left:20,right:138,top:500,bottom:544,width:118,height:44});w.eval(source);w.JLuxeStorefrontUtils.protectDockSwitch(b);let switches=0,cartOpens=0,accountNavigations=0;b.onclick=()=>switches++;d.getElementById('cart').onclick=()=>cartOpens++;d.getElementById('account').onclick=()=>accountNavigations++;
 function fire(el,type,x,id=1){const e=new w.MouseEvent(type,{bubbles:true,cancelable:true,clientX:x,clientY:520,button:0,detail:1});Object.defineProperty(e,'pointerId',{value:id});el.dispatchEvent(e);}
 for(const [id,x] of [['cart',30],['account',125]]){const a=d.getElementById(id);fire(a,'pointerdown',x);fire(a,'pointerup',x);fire(a,'click',x);}
 assert.equal(switches,0);assert.equal(cartOpens,1);assert.equal(accountNavigations,1);
 fire(b,'pointerdown',30,3);fire(b,'pointerup',30,4);assert.equal(switches,0,'unrelated pointer cannot complete the switch');fire(b,'pointerup',30,3);assert.equal(switches,1);
 fire(d.getElementById('cart'),'click',30,3);assert.equal(cartOpens,1);
 fire(d.getElementById('cart'),'pointerdown',200);fire(d.getElementById('cart'),'click',200);assert.equal(cartOpens,2);
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
test('shared gesture layer and owner replace the duplicate component pointer captures',()=>{
 const sticky=fs.readFileSync(new URL('../assets/js/sticky-cta.js',import.meta.url),'utf8');
 const woo=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
 assert.doesNotMatch(sticky,/modeToggle\.addEventListener\(\s*['"]pointerdown/);
 assert.doesNotMatch(woo,/toggleBtn\.addEventListener\(\s*['"]pointerdown/);
 assert.match(woo,/data-order-number-copy/);
 assert.match(source,/age <= 450/);assert.match(source,/distance <= 28/);
 assert.match(fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8'),/height:100dvh/);
});

test('no blanket navigation hit-test or icon sizing overrides remain',()=>{
 const js=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');assert.doesNotMatch(js,/function boundNavigation|jluxeNavBounds/);
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');assert.doesNotMatch(css,/Cart\/account: only|Dock links own only/);const layer=css.split('/* Independent switch surface:')[1];assert.match(layer,/pointer-events:none/);assert.match(layer,/pointer-events:auto/);assert.doesNotMatch(layer,/mobile-nav-item|mobile-nav-icon/);
});

test('thank-you order number copy control copies the raw order number and confirms success', async () => {
 const dom=new JSDOM('<button data-order-number-copy="5102">کپی</button>',{url:'https://shop.test/order-received/',runScripts:'outside-only'});
 try {
  const w=dom.window;let copied='';Object.defineProperty(w.navigator,'clipboard',{configurable:true,value:{writeText(value){copied=value;return Promise.resolve();}}});
  const woo=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
  w.eval(woo.slice(woo.indexOf('/* Order-number copy control')));
  w.document.querySelector('button').click();await Promise.resolve();
  assert.equal(copied,'5102');assert.equal(w.document.querySelector('button').textContent,'کپی شد');
 } finally { dom.window.close(); }
});
