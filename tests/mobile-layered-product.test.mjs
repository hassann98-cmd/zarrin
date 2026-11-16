import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const read=p=>fs.readFileSync(new URL(p,import.meta.url),'utf8');
const css=read('../src/styles/storefront.css');
const block=css.slice(css.indexOf('/* Optional mobile-only composition.'), css.indexOf('/* Administrator-controlled compact mobile typography'));
test('layered composition is isolated to explicit setting and mobile media query',()=>{
 assert.match(block,/@media \(max-width:767\.98px\)/);
 const selectors=[...block.matchAll(/([^{}]+)\{[^{}]*\}/g)].map(m=>m[1].trim()).filter(s=>!s.startsWith('@'));
 assert.ok(selectors.length>5);
 for(const selector of selectors) for(const part of selector.split(',')) assert.ok(part.includes('[data-jluxe-mobile-layout="layered"]'),part);
 assert.match(block,/position:sticky;top:var\(--jluxe-product-nav-top,0px\);z-index:0/);
 assert.match(block,/position:relative;z-index:1/);
 assert.doesNotMatch(block,/position:fixed|overflow:auto|overflow:scroll|392px/);
 assert.match(block,/48svh/);
});
for(const template of ['content-single-product.php','content-single-product-classic.php','content-single-product-reference.php']){
 test(`layered hooks preserve native gallery and forms in ${template}`,()=>{
 const source=read('../woocommerce/'+template);
 assert.match(source,/data-jluxe-mobile-layout=/);assert.match(source,/data-jluxe-layer-stage/);
 assert.match(source,/mobile_layout.*standard/);assert.match(source,/variations_form cart/);
 assert.ok(source.includes('woocommerce_show_product_images()')||source.includes('data-cp3-gallery'));
 });
}

test('layered text starts below the image without initial overlap, while gallery remains sticky',()=>{
 assert.doesNotMatch(block,/margin:-20px/);
 assert.match(block,/width:100%;margin:0;padding:20px 16px/);
 assert.match(block,/position:sticky/);
 assert.match(block,/position:relative;z-index:1/);
});
