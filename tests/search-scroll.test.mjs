import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import { containSearchScroll } from '../src/lib/search-scroll.js';

test('R168 search uses native wheel scrolling and prevents chaining only at boundaries',t=>{
 const dom=new JSDOM('<div id="panel" data-lenis-prevent></div>',{pretendToBeVisual:true});t.after(()=>dom.window.close());
 const {window}=dom, el=window.document.getElementById('panel');
 Object.defineProperty(el,'scrollHeight',{configurable:true,value:900});Object.defineProperty(el,'clientHeight',{configurable:true,value:300});
 el.getBoundingClientRect=()=>({top:100});
 const cleanup=containSearchScroll(el,window);
 const wheel=(deltaY,extra={})=>{const event=new window.WheelEvent('wheel',{deltaY,bubbles:true,cancelable:true,...extra});el.dispatchEvent(event);return event.defaultPrevented;};
 el.scrollTop=0;assert.equal(wheel(-80),true);assert.equal(wheel(80),false);
 el.scrollTop=300;assert.equal(wheel(80),false);assert.equal(wheel(-80),false);
 el.scrollTop=600;assert.equal(wheel(80),true);assert.equal(wheel(-80),false);
 assert.equal(wheel(80,{ctrlKey:true}),false,'pinch/ctrl zoom is not blocked');
 assert.equal(wheel(0,{deltaX:80}),false,'horizontal gestures are not remapped');
 Object.defineProperty(el,'scrollHeight',{value:300});assert.equal(wheel(80),true,'short/no results must not scroll the page underneath');
 assert.equal(el.style.maxHeight,'537px');
 cleanup();assert.equal(wheel(80),false,'unmount removes the non-passive handler');
});

test('R168 search panel height tracks the visual viewport and releases listeners',t=>{
 const dom=new JSDOM('<div id="panel"></div>',{pretendToBeVisual:true});t.after(()=>dom.window.close());
 const {window}=dom, el=window.document.getElementById('panel');
 window.visualViewport=new window.EventTarget();window.visualViewport.height=330;window.visualViewport.offsetTop=0;
 el.getBoundingClientRect=()=>({top:100});
 const cleanup=containSearchScroll(el,window,0.7);assert.equal(el.style.maxHeight,'218px');cleanup();
});

test('R168 all search surfaces opt out of Lenis interception and header beats the product sticky bar',()=>{
 const source=fs.readFileSync(new URL('../src/islands/Header.js',import.meta.url),'utf8');
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8');
 assert.equal((source.match(/e\.jsx\(SearchResultsPanel/g)||[]).length,2);
 assert.equal((source.match(/e\.jsx\(SearchScrollArea/g)||[]).length,1);
 assert.match(source,/"data-lenis-prevent": ""/);
 assert.match(css,/#masthead \{ position: relative; z-index: 50; \}/);
 assert.match(css,/#masthead \.jluxe-header-bar \{ z-index: 50; \}/);
 assert.match(css,/\.jluxe-search-scroll\s*\{[^}]*overscroll-behavior: contain/s);
 assert.doesNotMatch(source,/onMouseDown: \(.*\) => .*preventDefault/);
});
