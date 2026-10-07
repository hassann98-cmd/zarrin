import test from 'node:test';
import assert from 'node:assert/strict';
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {JSDOM} from 'jsdom';
import {containSearchWheel,searchPanelPosition,SearchResultsPanel} from '../src/lib/search-panel.js';

test('R168 native result wheels do not bubble into the smooth page scroll handler',()=>{
 const dom=new JSDOM('<div id="results"></div>');const w=dom.window,el=w.document.getElementById('results');
 Object.defineProperties(el,{scrollHeight:{value:1000},clientHeight:{value:200}});
 let pageWheels=0;w.addEventListener('wheel',()=>pageWheels++);const clean=containSearchWheel(el);
 const wheel=(dy,mode=0,ctrl=false)=>{const e=new w.WheelEvent('wheel',{deltaY:dy,deltaMode:mode,ctrlKey:ctrl,cancelable:true,bubbles:true});el.dispatchEvent(e);return e;};
 el.scrollTop=300;
 assert.equal(wheel(120).defaultPrevented,false,'native browser scrolling stays enabled inside the list');
 assert.equal(wheel(2,1).defaultPrevented,false);assert.equal(wheel(1,2).defaultPrevented,false);
 el.scrollTop=800;assert.equal(wheel(120).defaultPrevented,true);
 el.scrollTop=0;assert.equal(wheel(-120).defaultPrevented,true);assert.equal(pageWheels,0);
 assert.equal(wheel(20,0,true).defaultPrevented,false,'browser pinch/zoom is not hijacked');assert.equal(pageWheels,1);
 clean();wheel(10);assert.equal(pageWheels,2);dom.window.close();
});
test('R168 popup stays within visual viewport, including keyboard-sized mobile viewports',()=>{
 for(const width of [320,390,768,1440])for(const height of [270,800]){
  const p=searchPanelPosition({left:width-110,bottom:100,width:90},{width,height},true);
  assert.ok(p.left>=8 && p.left+p.width<=width-8);assert.ok(p.top+p.maxHeight<=height-8);assert.ok(p.maxHeight>=0);
 }
 const offset=searchPanelPosition({left:450,bottom:100,width:300},{left:200,top:50,width:400,height:300});
 assert.ok(offset.left>=208 && offset.left+offset.width<=592 && offset.top+offset.maxHeight<=342);
});
test('R168 search portal escapes the header and retains keyboard focus, Escape and outside-click closing',async()=>{
 const dom=new JSDOM('<header style="transform:translateZ(0);z-index:30"><form><input type="search"></form><div id="mount"></div></header><div class="cp3-nav"></div><button id="outside">outside</button>',{url:'https://shop.test',pretendToBeVisual:true});
 const w=dom.window;globalThis.window=w;globalThis.document=w.document;globalThis.IS_REACT_ACT_ENVIRONMENT=true;
 const anchor=w.document.querySelector('form');anchor.getBoundingClientRect=()=>({left:100,top:20,bottom:60,width:300,height:40});
 let closes=0;const root=createRoot(w.document.getElementById('mount'));
 try{
  await act(async()=>root.render(React.createElement(SearchResultsPanel,{anchorRef:{current:anchor},id:'results',onClose:()=>closes++},React.createElement('a',{href:'/product'},'result'))));
  const panel=w.document.getElementById('results');assert.equal(panel.parentElement,w.document.body);assert.equal(panel.getAttribute('data-lenis-prevent'),'');
  anchor.querySelector('input').focus();anchor.querySelector('input').dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));assert.equal(w.document.activeElement,panel.querySelector('a'));
  panel.querySelector('a').dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));assert.equal(closes,1);assert.equal(w.document.activeElement,anchor.querySelector('input'));
  w.document.getElementById('outside').dispatchEvent(new w.Event('pointerdown',{bubbles:true}));assert.equal(closes,2);
  await act(async()=>{w.dispatchEvent(new w.Event('resize'));await new Promise(r=>w.requestAnimationFrame(r));});
 }finally{await act(async()=>root.unmount());delete globalThis.window;delete globalThis.document;delete globalThis.IS_REACT_ACT_ENVIRONMENT;w.close();}
});
