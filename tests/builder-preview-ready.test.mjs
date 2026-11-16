import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/builder-preview.js',import.meta.url),'utf8');
test('head-loaded preview bridge waits for body before ready and scrolls newly selected nodes',()=>{
 const dom=new JSDOM('<html><head></head><body></body></html>',{runScripts:'outside-only',url:'https://shop.test/?preview=1'});
 try {
  const w=dom.window,messages=[];
  const parent={postMessage:(message,origin)=>messages.push({message,origin})};
  Object.defineProperty(w,'parent',{value:parent});w.jluxeBuilderPreview={parentOrigin:'https://shop.test'};
  w.eval(source);assert.equal(messages.length,0);
  const node=w.document.createElement('div');node.dataset.jvbNode='new-image';let scrolled=false;node.scrollIntoView=()=>{scrolled=true;};w.document.body.appendChild(node);
  w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
  assert.equal(messages.length,1);assert.deepEqual(Array.from(messages[0].message.nodes),['new-image']);
  w.dispatchEvent(new w.MessageEvent('message',{source:parent,origin:'https://shop.test',data:{source:'jluxe-builder-editor',type:'select',id:'new-image'}}));
  assert.equal(scrolled,true);assert.equal(node.hasAttribute('data-jb-selected'),true);
 }finally{dom.window.close();}
});
