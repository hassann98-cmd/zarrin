import { readStorefrontCss } from "./helpers/storefront-css.mjs";
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const script=source.slice(source.indexOf('/** Compact mobile variation strips;'));
test('no extra photo preview; mobile facts remain server-rendered while choices stay in a single strip',()=>{
 const dom=new JSDOM('<body class="single-product"><div class="jluxe-product-reference"><div data-reference-product-meta><span data-reference-stock-status="in-stock">In stock</span><span data-reference-sku><bdi dir="ltr">SKU-123</bdi></span><span data-jluxe-delivery-time-row>Tomorrow</span></div><div data-reference-low-stock hidden>2 left</div><div data-reference-variation-options><div data-cp3-pills="pa_color"><button data-value="red">Red</button><button data-value="blue">Blue</button></div></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window;const changes=[];const media={matches:true,addEventListener(_e,fn){changes.push(fn);}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document;
 assert.equal(d.querySelectorAll('.jluxe-option-strip').length,1);assert.equal(d.querySelectorAll('.jluxe-option-strip__arrow').length,2);assert.equal(d.querySelectorAll('[data-value]').length,2);assert.equal(d.querySelector('.jluxe-colour-preview'),null);
 const meta=d.querySelector('[data-reference-product-meta]');assert.ok(meta.querySelector('[data-reference-stock-status]'));assert.equal(meta.querySelector('[data-reference-sku] bdi').textContent,'SKU-123');assert.ok(meta.querySelector('[data-jluxe-delivery-time-row]'));assert.ok(meta.compareDocumentPosition(d.querySelector('[data-reference-variation-options]')) & w.Node.DOCUMENT_POSITION_FOLLOWING);assert.equal(d.querySelector('.jluxe-mobile-purchase-facts'),null);assert.equal(d.querySelector('.jluxe-variation-heading'),null);
 media.matches=false;changes.forEach(fn=>fn());assert.ok(d.querySelector('[data-reference-product-meta]').isSameNode(meta));assert.equal(meta.querySelectorAll('[data-reference-stock-status]').length,1);assert.equal(meta.querySelectorAll('[data-jluxe-delivery-time-row]').length,1);media.matches=true;changes.forEach(fn=>fn());assert.equal(meta.querySelectorAll('[data-reference-sku]').length,1);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));assert.equal(d.querySelectorAll('.jluxe-mobile-purchase-facts').length,0);
 }finally{dom.window.close();}
});

test('mobile product metadata is visible in its original row; option selection remains explicit',()=>{
 const css=readStorefrontCss();
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');
 assert.match(template,/reference-mobile-product-meta\{display:flex;flex-wrap:wrap/);assert.match(css,/cp3-pill\.is-active\{border-color:hsl\(var\(--primary\)\)/);assert.match(template,/reference-mobile-stock\.is-in-stock\{color:hsl\(var\(--success\)\)/);assert.match(template,/reference-mobile-stock\.is-out-of-stock\{color:hsl\(var\(--error\)\)/);
});

test('product facts use the server-rendered layout without injected placeholders',()=>{
 assert.doesNotMatch(script,/jluxe-mobile-purchase-facts|jluxe-variation-heading|trust badge desktop position|purchase fact position/);
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');
 assert.ok(template.indexOf('data-reference-product-meta')<template.indexOf('data-reference-variation-options'));
 assert.match(template,/<span><bdi data-cp3-stock-quantity>[\s\S]*?<\/bdi> عدد موجود<\/span>/);
});

test('dynamic stock quantities are rendered once and are not relocated after load',()=>{
 assert.doesNotMatch(script,/appendChild\(trust\)|appendChild\(item\.node\)|insertAdjacentElement\('beforebegin',notice\)/);
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');
 assert.match(template,/data-reference-low-stock data-cp3-low-stock/);
 assert.match(template,/data-jluxe-stock-quantity/);
});

test('delivery shares one row with compact green stock without line wrapping',()=>{
 const css=readStorefrontCss().split('/* Delivery and compact availability')[1];
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
 const css=readStorefrontCss().split('/* A soft clipped edge')[1];
 assert.match(css,/mask-image:linear-gradient/);assert.match(css,/data-jluxe-variation-value\]\{background:#fff;/);
});

test('mobile gallery reserves its first-paint frame and uses responsive preview files',()=>{
 const dom=new JSDOM('<div class="jluxe-product-reference"><div data-cp3-gallery data-jluxe-gallery-current="0"><button data-cp3-zoom-box><img data-cp3-zoom-img src="one.jpg" width="900" height="700" alt="product"></button><button data-reference-gallery-index="0" data-full="one-full.jpg"></button><button data-reference-gallery-index="1" data-full="two-full.jpg" data-preview-src="two-large.jpg" data-preview-srcset="two-small.jpg 320w, two-large.jpg 768w" data-preview-sizes="(max-width: 390px) 82vw, 320px" data-preview-width="640" data-preview-height="480"></button></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window;w.eval(source.slice(source.indexOf('/* Mobile gallery: native')));w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const track=w.document.querySelector('.jluxe-mobile-gallery-track'),first=track.children[0].querySelector('img'),second=track.children[1].querySelector('img');assert.equal(track.children.length,2);assert.match(first.src,/one\.jpg$/);assert.equal(first.getAttribute('width'),'900');assert.equal(second.getAttribute('src'),'two-large.jpg');assert.equal(second.getAttribute('srcset'),'two-small.jpg 320w, two-large.jpg 768w');assert.equal(second.getAttribute('sizes'),'(max-width: 390px) 82vw, 320px');assert.equal(second.getAttribute('width'),'640');assert.equal(second.getAttribute('height'),'480');let opened=0;w.document.querySelector('[data-cp3-zoom-box]').onclick=()=>opened++;track.children[1].click();assert.equal(opened,1);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));assert.equal(w.document.querySelectorAll('.jluxe-mobile-gallery-track').length,1);
 }finally{dom.window.close();}
 const styles=readStorefrontCss();const css=styles.split('/* Compact native mobile gallery:')[1];assert.match(styles,/\[data-cp3-gallery\]::before\{content:"";display:block;width:min\(82%,320px\);aspect-ratio:1\/1/);assert.match(styles,/\[data-cp3-gallery\]:has\(>\.jluxe-mobile-gallery-track\)::before\{display:none/);assert.match(css,/gap:6px;width:100%;height:auto/);assert.match(css,/flex:0 0 min\(82%,320px\);[\s\S]*?aspect-ratio:1\/1/);assert.match(css,/object-fit:cover;object-position:center/);assert.match(css,/overflow-x:auto/);assert.match(css,/scroll-snap-type:x mandatory/);
 const template=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');assert.match(template,/data-preview-srcset=/);assert.match(template,/width="<\?php echo absint\( \$cp3_main_image\['width'\] \); \?>" height="<\?php echo absint\( \$cp3_main_image\['height'\] \); \?>"/);
});

test('mobile facts place delivery right and compact availability left in the same RTL row',()=>{
 const css=readStorefrontCss().split('/* Delivery on the right, compact availability')[1];
 assert.match(css,/@media\(max-width:767\.98px\)/);
 assert.match(css,/grid-template-columns:minmax\(0,1fr\) max-content/);
 assert.match(css,/data-jluxe-delivery-time-row\]\{grid-row:1;grid-column:1;/);
 assert.match(css,/data-reference-stock-status\]\{grid-row:1;grid-column:2;/);
});

test('mobile header pins natively without scroll transforms; gallery has compact left meter',()=>{
 const css=readStorefrontCss().split('/* Let the compositor pin')[1];
 assert.match(css,/position:sticky;top:var\(--wp-admin--admin-bar--height/);
 assert.match(css,/position:relative;inset:auto;transform:none;will-change:auto/);
 assert.match(css,/jluxe-header-spacer\{display:none/);
 assert.match(css,/reference-mobile-gallery-dots\{display:none/);
 assert.match(css,/left:12px;bottom:10px/);
 assert.ok(source.includes("function sync(){updateMeter();"));
});

test('product trust, options, and notice keep their server-rendered order across breakpoints',()=>{
 const dom=new JSDOM('<div class="jluxe-product-reference"><div class="cp3-info"><div class="reference-mobile-taxonomy">Category</div><h1>Product</h1><div data-jluxe-trust-badges>Authenticity</div><div data-reference-product-meta><span data-reference-stock-status="in-stock">Stock</span></div><div data-reference-variation-options></div><aside data-jluxe-product-notice>Notice</aside></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window,changes=[],media={matches:true,addEventListener(_e,fn){changes.push(fn);}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document,options=d.querySelector('[data-reference-variation-options]'),notice=d.querySelector('[data-jluxe-product-notice]'),trust=d.querySelector('[data-jluxe-trust-badges]'),meta=d.querySelector('[data-reference-product-meta]');assert.equal(options.nextElementSibling,notice);assert.equal(trust.previousElementSibling.tagName,'H1');assert.equal(meta.parentElement,d.querySelector('.cp3-info'));assert.equal(d.querySelector('.jluxe-mobile-purchase-facts'),null);
 media.matches=false;changes.forEach(fn=>fn());assert.equal(notice.previousElementSibling,options);assert.equal(trust.previousElementSibling.tagName,'H1');media.matches=true;changes.forEach(fn=>fn());assert.equal(d.querySelectorAll('[data-jluxe-product-notice]').length,1);assert.equal(d.querySelectorAll('[data-jluxe-trust-badges]').length,1);
 }finally{dom.window.close();}
});

test('simple-product facts and notice remain in the original markup without synthetic controls',()=>{
 const dom=new JSDOM('<div class="jluxe-product-reference"><div class="cp3-info"><div class="reference-simple-product-meta" data-reference-product-meta><span data-reference-stock-status="in-stock">Stock</span><span data-jluxe-delivery-time-row>Tomorrow</span><span data-jluxe-stock-count>3</span></div><aside data-jluxe-product-notice>Read this</aside></div></div>',{runScripts:'outside-only'});
 try{const w=dom.window,changes=[],media={matches:true,addEventListener(_e,fn){changes.push(fn);}};w.matchMedia=q=>q.includes('reduced')?{matches:true}:media;w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));const d=w.document,meta=d.querySelector('[data-reference-product-meta]'),notice=d.querySelector('[data-jluxe-product-notice]');assert.ok(meta.querySelector('[data-reference-stock-status]'));assert.ok(meta.querySelector('[data-jluxe-stock-count]'));assert.ok(meta.querySelector('[data-jluxe-delivery-time-row]'));assert.equal(meta.nextElementSibling,notice);assert.equal(d.querySelector('.jluxe-mobile-purchase-facts'),null);assert.equal(d.querySelector('.jluxe-variation-heading'),null);assert.equal(meta.hasAttribute('data-jluxe-facts-ready'),false);media.matches=false;changes.forEach(fn=>fn());assert.equal(meta.querySelectorAll('[data-reference-stock-status]').length,1);assert.equal(d.querySelectorAll('[data-jluxe-product-notice]').length,1);
 }finally{dom.window.close();}
 const php=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');assert.equal((php.match(/jluxe_render_product_notice\( \$product \)/g)||[]).length,1);assert.ok(php.indexOf('jluxe_render_product_notice( $product )')<php.indexOf('<div class="reference-mobile-rating"'));
});

test('single mobile image is centered without fade; simple products have server-side frame fallback',()=>{
 const css=readStorefrontCss().split('/* A single image is centered;')[1];
 assert.match(css,/:only-child\)\{justify-content:center;overflow-x:hidden;mask-image:none/);
 assert.match(css,/reference-simple-product-meta:not\(\[data-jluxe-facts-ready\]\)/);
 assert.match(css,/grid-template-columns:minmax\(0,1fr\) max-content/);
 const php=fs.readFileSync(new URL('../woocommerce/content-single-product-reference.php',import.meta.url),'utf8');assert.ok(php.includes("$jluxe_is_simple ? ' reference-simple-product-meta'"));
});

test('gallery browsing preserves slide zero while variation/reset updates still replace it',async()=>{
 for(const count of [1,3]){
  const dots=Array.from({length:count},(_,i)=>`<button data-reference-gallery-index="${i}" data-full="https://shop.test/${i}.jpg"></button>`).join('');
  const dom=new JSDOM(`<div class="jluxe-product-reference"><div data-cp3-gallery data-jluxe-gallery-current="0"><button data-cp3-zoom-box><img data-cp3-zoom-img src="https://shop.test/0.jpg" srcset="https://shop.test/0-large.jpg 900w" sizes="82vw" alt="Original"></button>${dots}</div></div>`,{runScripts:'outside-only'});
  try{const w=dom.window,d=w.document;w.eval(source.slice(source.indexOf('/* Mobile gallery: native')));d.dispatchEvent(new w.Event('DOMContentLoaded'));const gallery=d.querySelector('[data-cp3-gallery]'),main=d.querySelector('[data-cp3-zoom-img]'),slides=d.querySelectorAll('.jluxe-mobile-gallery-slide img');assert.equal(slides[0].getAttribute('srcset'),main.getAttribute('srcset'));
   if(count>1){for(const index of [1,2,1]){gallery.setAttribute('data-jluxe-gallery-current',String(index));main.src=`https://shop.test/${index}.jpg`;main.removeAttribute('srcset');main.removeAttribute('sizes');await Promise.resolve();assert.equal(slides[0].src,'https://shop.test/0.jpg');assert.equal(slides[index].src,`https://shop.test/${index}.jpg`);}}
   main.src='https://shop.test/variation.jpg';main.setAttribute('srcset','https://shop.test/variation-large.jpg 900w');main.setAttribute('sizes','80vw');main.alt='Selected variation';gallery.setAttribute('data-jluxe-gallery-current','0');await Promise.resolve();assert.equal(slides[0].src,main.src);assert.equal(slides[0].alt,'Selected variation');assert.equal(slides[0].getAttribute('sizes'),'80vw');
   main.src='https://shop.test/0.jpg';main.removeAttribute('srcset');main.removeAttribute('sizes');main.alt='Original';await Promise.resolve();assert.equal(slides[0].src,'https://shop.test/0.jpg');assert.equal(slides[0].hasAttribute('srcset'),false);assert.equal(slides[0].hasAttribute('sizes'),false);if(count>1)assert.equal(slides[1].src,'https://shop.test/1.jpg');
  }finally{dom.window.close();}
 }
});
