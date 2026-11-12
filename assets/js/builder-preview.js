/* Private preview bridge: explicit origins and source checks, never parent DOM access. */
(function (win, doc) {
 'use strict';
 var origin = win.jluxeBuilderPreview?.parentOrigin;
 if (!origin || win.parent === win) return;
 var style = doc.createElement('style');
 style.textContent = '[data-jvb-node]{min-height:24px;position:relative}[data-jvb-node]:hover{outline:1px dashed #a166c8;outline-offset:1px}[data-jvb-node][data-jb-selected]{outline:3px solid #7620a4;outline-offset:2px}.jluxe-builder-container:empty{min-height:100px;border:2px dashed #b39bc8;background:#faf7ff}.jluxe-builder-container:empty:after{content:"کانتینر خالی — از پنل المان اضافه کنید";font:13px Tahoma;color:#6b4b84;padding:16px}';
 doc.head.appendChild(style);
 var selectedId = null, activeDrag = null;
 var handles = doc.createElement('div'); handles.setAttribute('data-jvb-resizers', ''); doc.documentElement.appendChild(handles);
 style.textContent += '.jvb-column-handle{position:fixed;z-index:2147483646;width:12px;min-height:32px;padding:0;border:0;border-radius:6px;background:#0d9488;cursor:col-resize;touch-action:none;transform:translateX(-50%)}.jvb-column-handle:focus-visible{outline:3px solid #111827}';
 function send(type, data) { win.parent.postMessage(Object.assign({source:'jluxe-builder-preview',type:type},data),origin); }
 function resizers() {
  if (activeDrag) return;
  handles.replaceChildren();
  var node = Array.from(doc.querySelectorAll('[data-jvb-node]')).find(function (item) { return item.dataset.jvbNode === selectedId; });
  var grid = node?.querySelector('.jluxe-builder-container'); if (!grid || !grid.getBoundingClientRect().width) return;
  var computed = win.getComputedStyle(grid), tracks = computed.gridTemplateColumns.split(/\s+/).map(parseFloat);
  var columns = parseInt(computed.getPropertyValue('--jbc-cols'),10);
  if (tracks.length !== columns || columns < 2 || computed.display !== 'grid' || tracks.some(function (v) { return !Number.isFinite(v) || v <= 0; })) return;
  var total = tracks.reduce(function(a,b){return a+b;},0), gap = parseFloat(computed.columnGap) || 0, box = grid.getBoundingClientRect(), offset = 0;
  for (var i=0;i<columns-1;i++) {
   offset += tracks[i];
   (function(index, edge) {
    var handle=doc.createElement('button');handle.type='button';handle.className='jvb-column-handle';handle.setAttribute('aria-label','تغییر مرز ستون '+(index+1)+'؛ کلیدهای چپ و راست');
    handle.style.top=Math.max(0,box.top)+'px';handle.style.height=Math.min(160,Math.max(32,box.height))+'px';
    handle.style.left=(computed.direction==='rtl'?box.right-edge:box.left+edge)+'px';
    function apply(delta, commit) {
     var values=tracks.map(function(v){return v/total*100;}), change=delta/total*100;
     change=Math.max(1-values[index],Math.min(values[index+1]-1,change));
     values[index]+=change;values[index+1]-=change;
     var ratios=values.map(function(v){return Number(v.toFixed(3));});
     grid.style.setProperty('--jbc-tracks',ratios.map(function(v){return 'minmax(0,'+v+'fr)';}).join(' '));
     if(commit)send('resize',{id:selectedId,ratios:ratios});
    }
    handle.addEventListener('pointerdown',function(event){event.preventDefault();event.stopPropagation();activeDrag={x:event.clientX,id:event.pointerId};handle.setPointerCapture?.(event.pointerId);});
    handle.addEventListener('pointermove',function(event){if(activeDrag?.id===event.pointerId)apply((event.clientX-activeDrag.x)*(computed.direction==='rtl'?-1:1),false);});
    function finish(event){if(activeDrag?.id!==event.pointerId)return;apply((event.clientX-activeDrag.x)*(computed.direction==='rtl'?-1:1),true);activeDrag=null;resizers();}
    handle.addEventListener('pointerup',finish);
    handle.addEventListener('pointercancel',function(){activeDrag=null;grid.style.setProperty('--jbc-tracks',computed.getPropertyValue('--jbc-tracks'));resizers();});
    handle.addEventListener('keydown',function(event){if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;event.preventDefault();apply((event.key==='ArrowRight'?1:-1)*(computed.direction==='rtl'?-1:1)*total/100,true);resizers();});
    handles.appendChild(handle);
   })(i,offset+gap*(i+.5));
  }
 }
 doc.addEventListener('DOMContentLoaded',resizers);
 win.addEventListener('resize',resizers);win.addEventListener('scroll',resizers,true);
 doc.addEventListener('dragover',function(event){if(Array.from(event.dataTransfer?.types||[]).some(function(type){return type==='text/x-jb-type';}))event.preventDefault();});
 doc.addEventListener('drop',function(event){
  var type=event.dataTransfer?.getData('text/x-jb-type');if(!type)return;
  event.preventDefault();event.stopPropagation();var node=event.target.closest?.('[data-jvb-node]');
  send('drop',{id:node?.dataset.jvbNode||null,widget:type,preset:event.dataTransfer.getData('text/x-jb-preset')});
 });
 doc.addEventListener('click', function (event) {
  if (event.target.closest?.('[data-jvb-resizers]')) return;
  var node = event.target.closest?.('[data-jvb-node]');
  event.preventDefault(); event.stopImmediatePropagation();
  if (node) win.parent.postMessage({ source: 'jluxe-builder-preview', type: 'select', id: node.dataset.jvbNode }, origin);
 }, true);
 doc.addEventListener('submit', function (event) { event.preventDefault(); event.stopImmediatePropagation(); }, true);
 win.addEventListener('message', function (event) {
  if (event.source !== win.parent || event.origin !== origin || event.data?.source !== 'jluxe-builder-editor') return;
  if (event.data.type === 'select') { selectedId=event.data.id; resizers(); }
  if (event.data.type === 'select') doc.querySelectorAll('[data-jvb-node]').forEach(function (node) { node.toggleAttribute('data-jb-selected', node.dataset.jvbNode === event.data.id); });
 });
 win.parent.postMessage({ source: 'jluxe-builder-preview', type: 'ready', url: win.location.href }, origin);
})(window, document);
