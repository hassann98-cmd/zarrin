import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const read=p=>fs.readFileSync(new URL(p,import.meta.url),'utf8');
test('mobile product title and chip overrides use saved variables only below the mobile breakpoint',()=>{
 const css=read('../src/styles/storefront.css').split('/* Administrator-controlled compact mobile typography and variation chips. */')[1].split('/* Open drawers own the screen:')[0];
 assert.ok(css);assert.match(css,/@media\(max-width:767\.98px\)/);
 for(const v of ['title-size','title-weight','option-height','option-font','option-padding']) assert.ok(css.includes('--jluxe-mobile-'+v));
 assert.match(css,/\.cp3-title/);assert.match(css,/\[data-jluxe-product-title\]/);assert.match(css,/\[data-jluxe-variation-value\]/);assert.match(css,/\.cp3-pill/);
 assert.doesNotMatch(css,/single_add_to_cart_button|data-jluxe-mobile-bar-add/);
 assert.match(css,/min-width:48px/);assert.match(css,/gap:8px/);
});
test('all five controls are exposed to the administrator',()=>{
 const ui=read('../inc/theme-settings-render.php');
 for(const key of ['mobile_title_size','mobile_title_weight','mobile_option_height','mobile_option_font','mobile_option_padding']) assert.ok(ui.includes(key));
});
