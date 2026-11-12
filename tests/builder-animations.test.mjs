import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';
const script=fs.readFileSync(new URL('../assets/js/builder-animations.js',import.meta.url),'utf8');
function boot(reduced=false){
 const dom=new JSDOM('<div data-jvb-animation="fade-up" data-jvb-repeat="1"></div><div data-jvb-animation="zoom" data-jvb-repeat="0"></div><div data-jvb-animation="none"></div>',{runScripts:'outside-only'});
 const watched=[],unwatched=[];let callback,change,disconnected=false;
 dom.window.matchMedia=()=>({matches:reduced,addEventListener:(_,cb)=>{change=cb;}});
 dom.window.IntersectionObserver=class {constructor(cb){callback=cb;}observe(n){watched.push(n);}unobserve(n){unwatched.push(n);}disconnect(){disconnected=true;}};
 dom.window.eval(script);return {dom,watched,unwatched,emit:e=>callback(e),reduce:()=>change({matches:true}),disconnected:()=>disconnected};
}
test('entrance observer repeats only opted-in nodes and supports runtime reduced motion',()=>{
 const x=boot();assert.equal(x.watched.length,2);
 x.emit(x.watched.map(target=>({target,isIntersecting:true})));
 assert.equal(x.unwatched.length,1);assert.equal(x.unwatched[0],x.watched[1]);
 x.emit([{target:x.watched[0],isIntersecting:false}]);assert.equal(x.watched[0].classList.contains('jvb-entering'),false);
 x.reduce();assert.equal(x.disconnected(),true);assert.equal(x.dom.window.document.querySelectorAll('.jvb-entering').length,0);x.dom.window.close();
});
test('reduced motion never starts entrance animation',()=>{const x=boot(true);assert.equal(x.watched.length,0);x.dom.window.close();});
