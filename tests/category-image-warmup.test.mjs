import test from 'node:test';
import assert from 'node:assert/strict';
import {warmCategoryImages} from '../src/lib/category-image-warmup.js';
test('warmup is bounded, low priority, deduplicated and respects data saver',()=>{
 const images=[];const win={navigator:{connection:{}},Image:class{constructor(){images.push(this);}}};
 const categories=Array.from({length:20},(_,i)=>({iconImage:`https://test.test/warm-${i}.webp`}));
 warmCategoryImages(categories,6,win);assert.equal(images.length,6);assert.equal(images[0].fetchPriority,'low');
 warmCategoryImages(categories,6,win);assert.equal(images.length,6);
 win.navigator.connection.saveData=true;warmCategoryImages(categories,16,win);assert.equal(images.length,6);
 win.navigator.connection={effectiveType:'2g'};warmCategoryImages(categories,16,win);assert.equal(images.length,6);
 win.navigator.connection={};warmCategoryImages(categories,16,win);assert.equal(images.length,16);
});
