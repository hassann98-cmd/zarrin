import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const script=source.slice(source.indexOf('/** Compact mobile variation strips;'));
test('no extra photo preview, native choices stay in a single strip and facts move below options only on mobile',()=>{
 const dom=new JSDOM('<body class="single-product"><div class="jluxe-product-reference"><div data-reference-product-meta><span data-jluxe-delivery-time-row>Tomorrow</span></div><div data-reference-low-stock hidden>2 left</div><div data-reference-variation-options><div data-cp3-pills="pa_color"><button data-value="red">Red</button><button data-value="blue">Blue</button></div></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window;let change;const media={matches:true,addEventListener(_e,fn){change=fn;}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document;
 assert.equal(d.querySelectorAll('.jluxe-option-strip').length,1);assert.equal(d.querySelectorAll('.jluxe-option-strip__arrow').length,2);assert.equal(d.querySelectorAll('[data-value]').length,2);assert.equal(d.querySelector('.jluxe-colour-preview'),null);
 const facts=d.querySelector('.jluxe-mobile-purchase-facts');assert.ok(facts.querySelector('[data-reference-low-stock]').hidden);assert.ok(facts.querySelector('[data-jluxe-delivery-time-row]'));assert.equal(d.querySelector('[data-reference-variation-options]').nextElementSibling,facts);
 media.matches=false;change();assert.ok(d.querySelector('[data-reference-product-meta] [data-jluxe-delivery-time-row]'));assert.equal(facts.children.length,0);
 }finally{dom.window.close();}
});
