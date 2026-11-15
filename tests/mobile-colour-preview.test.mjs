import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const script=source.slice(source.indexOf('/** Compact mobile variation strips;'));
test('no extra photo preview, native choices stay in a single strip and facts move below options only on mobile',()=>{
 const dom=new JSDOM('<body class="single-product"><div class="jluxe-product-reference"><div data-reference-product-meta><span data-reference-stock-status="in-stock">In stock</span><span data-reference-sku><bdi dir="ltr">SKU-123</bdi></span><span data-jluxe-delivery-time-row>Tomorrow</span></div><div data-reference-low-stock hidden>2 left</div><div data-reference-variation-options><div data-cp3-pills="pa_color"><button data-value="red">Red</button><button data-value="blue">Blue</button></div></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window;const changes=[];const change=()=>changes.forEach(fn=>fn());const media={matches:true,addEventListener(_e,fn){changes.push(fn);}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document;
 assert.equal(d.querySelectorAll('.jluxe-option-strip').length,1);assert.equal(d.querySelectorAll('.jluxe-option-strip__arrow').length,2);assert.equal(d.querySelectorAll('[data-value]').length,2);assert.equal(d.querySelector('.jluxe-colour-preview'),null);
 const facts=d.querySelector('.jluxe-mobile-purchase-facts');assert.ok(facts.querySelector('[data-reference-stock-status]'));assert.equal(d.querySelector('.reference-mobile-taxonomy [data-reference-sku] bdi').textContent,'SKU-123');assert.ok(d.querySelector('.jluxe-variation-heading [data-reference-low-stock]').hidden);assert.equal(facts.querySelector('[data-reference-sku]'),null);assert.ok(facts.querySelector('[data-jluxe-delivery-time-row]'));assert.equal(d.querySelector('[data-reference-variation-options]').nextElementSibling,facts);
 media.matches=false;change();assert.ok(d.querySelector('[data-reference-product-meta] [data-jluxe-delivery-time-row]'));assert.equal(facts.children.length,0);assert.ok(d.querySelector('[data-reference-product-meta] [data-reference-stock-status]'));assert.ok(d.querySelector('[data-reference-product-meta] [data-reference-sku]'));media.matches=true;change();assert.equal(d.querySelectorAll('.reference-mobile-taxonomy [data-reference-sku]').length,1);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));assert.equal(d.querySelectorAll('.jluxe-mobile-purchase-facts').length,1);
 }finally{dom.window.close();}
});

test('mobile facts and choices share a neutral design while selection remains explicit',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Quiet mobile selection system:')[1];
 assert.match(css,/@media\(max-width:767\.98px\)/);assert.match(css,/font-weight:500/);assert.match(css,/background:hsl\(var\(--muted\)\)/);assert.match(css,/data-jluxe-delivery-time-row\]\{order:-1;margin-inline-end:auto;text-align:right/);assert.match(css,/cp3-pill\.is-active\{border-color:hsl\(var\(--primary\)\)/);assert.match(css,/out-of-stock/);
});

test('purchase facts are an independent two-column panel with no empty placeholder cards',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Four related facts form')[1];
 assert.match(css,/@media\(max-width:767\.98px\)/);
 assert.match(css,/grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/);
 assert.match(css,/not\(:has\(>:not\(\[hidden\]\)\)\)\{display:none/);
 assert.match(css,/data-reference-stock-status\]\{order:0;grid-row:1;grid-column:1/);
 assert.match(css,/data-reference-sku\]\{order:2;grid-row:2;grid-column:1/);
 assert.match(css,/data-jluxe-delivery-time-row\]\{order:3;grid-row:2;grid-column:2/);
 assert.match(css,/data-jluxe-stock-count\]\{order:1;grid-row:1;grid-column:2/);
 assert.ok(source.includes("bar.setAttribute('role','group')"));
 assert.ok(source.includes("bar.setAttribute('aria-label','اطلاعات موجودی و ارسال محصول')"));
});

test('SKU and stock are positioned by meaning without cloning dynamic quantities',()=>{
 assert.ok(source.includes('if(mobile.matches)identity.appendChild(sku)'));
 assert.ok(source.includes("item.node.hasAttribute('data-reference-low-stock') || item.node.hasAttribute('data-jluxe-stock-count'))heading.appendChild(item.node)"));
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');
 assert.match(template,/<span><bdi data-cp3-stock-quantity>[\s\S]*?<\/bdi> عدد موجود<\/span>/);
});
