import test from 'node:test';
import assert from 'node:assert/strict';
import {createElement} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {CategoryVisual} from '../src/components/category-visual.js';
import fs from 'node:fs';
const render=category=>renderToStaticMarkup(createElement(CategoryVisual,{category}));
test('mobile category visual renders WooCommerce image at a fixed decorative size',()=>{
 const html=render({iconImage:'https://shop.test/category.jpg',iconSvg:''});
 assert.match(html,/<img/);assert.match(html,/width="24"/);assert.match(html,/height="24"/);assert.match(html,/alt=""/);assert.match(html,/jluxe-category-image-placeholder/);assert.match(html,/loading="eager"/);
});
test('theme SVG override and legacy fallback render without requiring a category image',()=>{
 assert.match(render({iconSvg:'<svg data-custom="yes"></svg>',iconImage:''}),/data-custom="yes"/);
 assert.match(render({}),/<svg/);
});
test('both expandable and leaf mobile category rows use the same visual renderer',()=>{
 const source=fs.readFileSync(new URL('../src/islands/CategoryDrawer.js',import.meta.url),'utf8');
 assert.equal((source.match(/e\.jsx\(CategoryVisual, \{category:t\}\)/g)||[]).length,2);
 assert.doesNotMatch(source,/t\.iconSvg/);
});

test('opened mobile drawer displays WC images for both branch and leaf rows',async()=>{
 const {JSDOM}=await import('jsdom');const {act}=await import('react');const {createRoot}=await import('react-dom/client');
 const {openMobileDrawer,closeMobileDrawer}=await import('../src/lib/mobile-drawers.js');
 const {default:Drawer}=await import('../src/islands/CategoryDrawer.js');
 const dom=new JSDOM('<body><div id="root"></div></body>',{url:'https://shop.test',pretendToBeVisual:true});
 const keys=['window','document','requestAnimationFrame','cancelAnimationFrame','IS_REACT_ACT_ENVIRONMENT'];const previous=keys.map(key=>globalThis[key]);
 Object.assign(globalThis,{window:dom.window,document:dom.window.document,requestAnimationFrame:dom.window.requestAnimationFrame.bind(dom.window),cancelAnimationFrame:dom.window.cancelAnimationFrame.bind(dom.window),IS_REACT_ACT_ENVIRONMENT:true});
 dom.window.JLuxeThemeSettings={megaMenu:{categories:[{id:'1',label:'Branch',url:'/one',iconImage:'https://shop.test/one.jpg',groups:[{title:'Child',url:'/child',items:[]}]},{id:'2',label:'Leaf',url:'/two',iconImage:'https://shop.test/two.jpg',groups:[]}]}};
 const root=createRoot(document.getElementById('root'));
 try{
  await act(async()=>root.render(createElement(Drawer)));
  await act(async()=>openMobileDrawer('categories',dom.window));
  await act(async()=>{await new Promise(resolve=>setTimeout(resolve,120));});
  assert.equal(document.querySelectorAll('#jluxe-category-drawer-dialog .jluxe-mobile-category-image').length,2);
  assert.equal(document.querySelector('a[href="/two"]').textContent,'Leaf');
 }finally{
  await act(async()=>{closeMobileDrawer('categories',dom.window);root.unmount();});dom.window.close();keys.forEach((key,i)=>{if(previous[i]===undefined)delete globalThis[key];else globalThis[key]=previous[i];});
 }
});
