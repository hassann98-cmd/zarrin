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
 const facts=d.querySelector('.jluxe-mobile-purchase-facts');assert.ok(facts.querySelector('[data-reference-stock-status]'));assert.equal(d.querySelector('[data-reference-product-meta] [data-reference-sku] bdi').textContent,'SKU-123');assert.ok(d.querySelector('.jluxe-variation-heading [data-reference-low-stock]').hidden);assert.equal(facts.querySelector('[data-reference-sku]'),null);assert.ok(facts.querySelector('[data-jluxe-delivery-time-row]'));assert.equal(d.querySelector('[data-reference-variation-options]').nextElementSibling,facts);
 media.matches=false;change();assert.ok(d.querySelector('[data-reference-product-meta] [data-jluxe-delivery-time-row]'));assert.equal(facts.children.length,0);assert.ok(d.querySelector('[data-reference-product-meta] [data-reference-stock-status]'));assert.ok(d.querySelector('[data-reference-product-meta] [data-reference-sku]'));media.matches=true;change();assert.equal(d.querySelectorAll('[data-reference-product-meta] [data-reference-sku]').length,1);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));assert.equal(d.querySelectorAll('.jluxe-mobile-purchase-facts').length,1);
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
 assert.ok(source.includes('if(mobile.matches)identity.appendChild(trust)'));
 assert.ok(source.includes("item.node.hasAttribute('data-reference-low-stock') || item.node.hasAttribute('data-jluxe-stock-count'))heading.appendChild(item.node)"));
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');
 assert.match(template,/<span><bdi data-cp3-stock-quantity>[\s\S]*?<\/bdi> عدد موجود<\/span>/);
});

test('delivery shares one row with compact green stock without line wrapping',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Delivery and compact availability')[1];
 assert.match(css,/grid-template-columns:max-content minmax\(0,1fr\)/);
 assert.match(css,/data-reference-stock-status\]\{width:auto;min-width:0;padding-inline:6px;white-space:nowrap;flex-wrap:nowrap/);
 assert.match(css,/@media\(max-width:767\.98px\)/);
 assert.match(css,/grid-row:1;grid-column:2;flex-wrap:nowrap;white-space:nowrap;overflow-x:auto/);
 assert.match(css,/flex-shrink:0;white-space:nowrap;overflow-wrap:normal;word-break:normal/);
 assert.match(css,/in-stock"\]\{color:hsl\(var\(--success\)\)/);
 assert.match(css,/out-of-stock"\]\{color:hsl\(var\(--error\)\)/);
});

test('overflow fades follow scroll boundaries in RTL and LTR without masking short rows',()=>{
 for(const direction of ['rtl','ltr']){
  const dom=new JSDOM(`<div style="direction:${direction}" data-cp3-pills><button>One</button></div>`,{runScripts:'outside-only'});
  try{const w=dom.window;w.matchMedia=q=>({matches:true});const track=w.document.querySelector('[data-cp3-pills]');let width=500;
   Object.defineProperties(track,{scrollWidth:{get:()=>width},clientWidth:{get:()=>200}});
   w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const wrap=track.parentElement;
   const check=(left,right)=>{assert.equal(wrap.classList.contains('has-more-left'),left);assert.equal(wrap.classList.contains('has-more-right'),right);};
   check(direction==='rtl',direction==='ltr');
   track.scrollLeft=direction==='rtl'?-120:120;track.dispatchEvent(new w.Event('scroll'));check(true,true);
   track.scrollLeft=direction==='rtl'?-300:300;track.dispatchEvent(new w.Event('scroll'));check(direction==='ltr',direction==='rtl');
   width=200;w.dispatchEvent(new w.Event('resize'));check(false,false);assert.equal(wrap.classList.contains('has-overflow'),false);
  }finally{dom.window.close();}
 }
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* A soft clipped edge')[1];
 assert.match(css,/mask-image:linear-gradient/);assert.match(css,/data-jluxe-variation-value\]\{background:#fff;/);
});

test('compact mobile gallery contains actual adjacent images and preserves original fullscreen control',()=>{
 const dom=new JSDOM('<div class="jluxe-product-reference"><div data-cp3-gallery data-jluxe-gallery-current="0"><button data-cp3-zoom-box><img data-cp3-zoom-img src="one.jpg" alt="product"></button><button data-reference-gallery-index="0" data-full="one.jpg"></button><button data-reference-gallery-index="1" data-full="two.jpg"></button></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window;w.eval(source.slice(source.indexOf('/* Mobile gallery: native')));w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const track=w.document.querySelector('.jluxe-mobile-gallery-track');assert.equal(track.children.length,2);assert.match(track.children[1].querySelector('img').src,/two.jpg$/);let opened=0;w.document.querySelector('[data-cp3-zoom-box]').onclick=()=>opened++;track.children[1].click();assert.equal(opened,1);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));assert.equal(w.document.querySelectorAll('.jluxe-mobile-gallery-track').length,1);
 }finally{dom.window.close();}
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Compact native mobile gallery:')[1];assert.match(css,/gap:6px;width:100%;height:auto/);assert.match(css,/flex:0 0 min\(82%,320px\);[\s\S]*?aspect-ratio:1\/1/);assert.match(css,/object-fit:cover;object-position:center/);assert.doesNotMatch(css,/object-fit:contain/);assert.match(css,/overflow-x:auto/);assert.match(css,/scroll-snap-type:x mandatory/);assert.match(css,/justify-content:flex-end;text-align:left/);
});

test('mobile facts place delivery right and compact availability left in the same RTL row',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Delivery on the right, compact availability')[1];
 assert.match(css,/@media\(max-width:767\.98px\)/);
 assert.match(css,/grid-template-columns:minmax\(0,1fr\) max-content/);
 assert.match(css,/data-jluxe-delivery-time-row\]\{grid-row:1;grid-column:1;/);
 assert.match(css,/data-reference-stock-status\]\{grid-row:1;grid-column:2;/);
});

test('mobile header pins natively without scroll transforms; gallery has compact left meter',()=>{
 const css=fs.readFileSync(new URL('../src/styles/storefront.css',import.meta.url),'utf8').split('/* Let the compositor pin')[1];
 assert.match(css,/position:sticky;top:var\(--wp-admin--admin-bar--height/);
 assert.match(css,/position:relative;inset:auto;transform:none;will-change:auto/);
 assert.match(css,/jluxe-header-spacer\{display:none/);
 assert.match(css,/reference-mobile-gallery-dots\{display:none/);
 assert.match(css,/left:12px;bottom:10px/);
 assert.ok(source.includes("function sync(){updateMeter();"));
});

test('mobile notice immediately follows choices and enabled trust moves to identity; desktop restores both',()=>{
 const dom=new JSDOM('<div class="jluxe-product-reference"><div class="cp3-info"><div class="reference-mobile-taxonomy">Category</div><div data-jluxe-trust-badges>Authenticity</div><div data-reference-variation-options></div></div><div id="desktop"><aside data-jluxe-product-notice>Notice</aside></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window,changes=[],media={matches:true,addEventListener(_e,fn){changes.push(fn);}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document,options=d.querySelector('[data-reference-variation-options]'),notice=d.querySelector('[data-jluxe-product-notice]');assert.equal(options.nextElementSibling,notice);assert.ok(d.querySelector('.reference-mobile-taxonomy>[data-jluxe-trust-badges]'));media.matches=false;changes.forEach(fn=>fn());assert.equal(notice.parentElement.id,'desktop');assert.ok(d.querySelector('.cp3-info>[data-jluxe-trust-badges]'));media.matches=true;changes.forEach(fn=>fn());assert.equal(options.nextElementSibling,notice);assert.equal(d.querySelectorAll('[data-jluxe-product-notice]').length,1);
 }finally{dom.window.close();}
});
