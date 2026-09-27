import{a as p}from"./main-CPzaaPhe.js";function m(e,t){for(var r=0;r<t.length;r++){const o=t[r];if(typeof o!="string"&&!Array.isArray(o)){for(const n in o)if(n!=="default"&&!(n in e)){const a=Object.getOwnPropertyDescriptor(o,n);a&&Object.defineProperty(e,n,a.get?a:{enumerable:!0,get:()=>o[n]})}}}return Object.freeze(Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}))}function g(e){return e&&e.__esModule&&Object.prototype.hasOwnProperty.call(e,"default")?e.default:e}var s=p();const b=g(s),O=m({__proto__:null,default:b},[s]);/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const w=e=>e.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase(),c=(...e)=>e.filter((t,r,o)=>!!t&&t.trim()!==""&&o.indexOf(t)===r).join(" ").trim();/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var y={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=s.forwardRef(({color:e="currentColor",size:t=24,strokeWidth:r=2,absoluteStrokeWidth:o,className:n="",children:a,iconNode:i,...u},l)=>s.createElement("svg",{ref:l,...y,width:t,height:t,stroke:e,strokeWidth:o?Number(r)*24/Number(t):r,className:c("lucide",n),...u},[...i.map(([f,d])=>s.createElement(f,d)),...Array.isArray(a)?a:[a]]));/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const v=(e,t)=>{const r=s.forwardRef(({className:o,...n},a)=>s.createElement(h,{ref:a,iconNode:t,className:c(`lucide-${w(e)}`,o),...n}));return r.displayName=`${e}`,r};export{O as R,v as c,s as r};
