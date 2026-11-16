import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/storefront-utils.js',import.meta.url),'utf8');
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
