import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const source=fs.readFileSync(new URL('../assets/js/woocommerce.js',import.meta.url),'utf8');
const marker=source.indexOf('پاپ‌آپِ «محصولات پیشنهادی»'),start=source.indexOf('(function () {',marker),end=source.indexOf('\n})();\n\n/**\n * افزودن به سبد در فرمِ صفحه',start);
function boot(t,rows){
 const dom=new JSDOM(`<div data-jluxe-suggested-modal class="hidden" aria-hidden="true" data-pa-main="100"><div class="jluxe-pa-sheet" role="dialog"><div class="jluxe-pa-body">${rows}</div><strong data-pa-total></strong><button data-pa-confirm><span data-pa-confirm-label></span></button></div></div>`,{url:'https://shop.test/product/item',runScripts:'outside-only',pretendToBeVisual:true});t.after(()=>dom.window.close());const w=dom.window;w.JLuxeThemeSettings={cart:{ajaxUrl:'/ajax',nonce:'ok',cartUrl:'/basket/'}};return w;
}
function bind(w){w.eval(source.slice(start,end+'\n})();'.length));w.jluxeOpenSuggestedProductsModal();}
test('deselecting every stored service persists an empty selection before closing',async t=>{
 const w=boot(t,'<button data-pa-service="s0" data-pa-amount="10" aria-pressed="true">Service</button>');let stored=['s0'];const requests=[];
 w.jluxeCartPost=async(url,body)=>{requests.push(Object.fromEntries(body));stored=body.get('pa_services').split(',').filter(Boolean);return {success:true,data:{items:[],itemCount:1}};};bind(w);
 w.document.querySelector('[data-pa-service]').click();const result=await w.jluxeCommitSuggestedSelection();assert.equal(result.success,true);assert.deepEqual(stored,[]);assert.equal(requests.length,1);assert.equal(requests[0].op,'pa_services');assert.equal(requests[0].pa_services,'');
});
test('a hidden services section never clears stored service choices',async t=>{
 const w=boot(t,'');let requests=0;w.jluxeCartPost=async()=>{requests++;throw Error('unexpected');};bind(w);const result=await w.jluxeCommitSuggestedSelection();assert.equal(result.empty,true);assert.equal(requests,0);
});

test('unknown offer add is not replayed on another confirm or recommendation remount',async t=>{
 const row='<button data-pa-product="501" data-pa-amount="25" aria-pressed="true">Offer</button>';
 const w=boot(t,row);let adds=0;w.jluxeCartPost=async()=>{adds++;throw Error('response lost after commit');};bind(w);
 await w.jluxeCommitSuggestedSelection();assert.equal(adds,1);assert.ok(w.document.querySelector('[data-pa-error] a'));
 const retry=await w.jluxeCommitSuggestedSelection();assert.equal(retry.uncertain,true);assert.equal(adds,1);
 w.jluxeMountSuggestedModal(`<div data-jluxe-suggested-modal class="hidden"><div class="jluxe-pa-sheet"><div class="jluxe-pa-body">${row}</div><button data-pa-confirm></button></div></div>`);
 const remountRetry=await w.jluxeCommitSuggestedSelection();assert.equal(remountRetry.uncertain,true);assert.equal(adds,1);
});
test('unknown offer can be deselected so unrelated offers remain usable',async t=>{
 const w=boot(t,'<button data-pa-product="501" aria-pressed="true">Unknown</button><button data-pa-product="502" aria-pressed="false">Other</button>');const ids=[];
 w.jluxeCartPost=async(url,body)=>{ids.push(body.get('product_id'));if(body.get('product_id')==='501')throw Error('lost');return {success:true,data:{items:[],itemCount:1}};};bind(w);
 await w.jluxeCommitSuggestedSelection();w.document.querySelector('[data-pa-product="501"]').click();w.document.querySelector('[data-pa-product="502"]').click();const result=await w.jluxeCommitSuggestedSelection();assert.equal(result.success,true);assert.deepEqual(ids,['501','502']);
});
