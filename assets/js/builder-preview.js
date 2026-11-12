/* Private preview bridge: explicit origins and source checks, never parent DOM access. */
(function (win, doc) {
 'use strict';
 var origin = win.jluxeBuilderPreview?.parentOrigin;
 if (!origin || win.parent === win) return;
 var style = doc.createElement('style');
 style.textContent = '[data-jvb-node]{min-height:24px;position:relative}[data-jvb-node]:hover{outline:1px dashed #a166c8;outline-offset:1px}[data-jvb-node][data-jb-selected]{outline:3px solid #7620a4;outline-offset:2px}.jluxe-builder-container:empty{min-height:100px;border:2px dashed #b39bc8;background:#faf7ff}.jluxe-builder-container:empty:after{content:"کانتینر خالی — از پنل المان اضافه کنید";font:13px Tahoma;color:#6b4b84;padding:16px}';
 doc.head.appendChild(style);
 doc.addEventListener('click', function (event) {
  var node = event.target.closest?.('[data-jvb-node]');
  event.preventDefault(); event.stopImmediatePropagation();
  if (node) win.parent.postMessage({ source: 'jluxe-builder-preview', type: 'select', id: node.dataset.jvbNode }, origin);
 }, true);
 doc.addEventListener('submit', function (event) { event.preventDefault(); event.stopImmediatePropagation(); }, true);
 win.addEventListener('message', function (event) {
  if (event.source !== win.parent || event.origin !== origin || event.data?.source !== 'jluxe-builder-editor') return;
  if (event.data.type === 'select') doc.querySelectorAll('[data-jvb-node]').forEach(function (node) { node.toggleAttribute('data-jb-selected', node.dataset.jvbNode === event.data.id); });
 });
 win.parent.postMessage({ source: 'jluxe-builder-preview', type: 'ready', url: win.location.href }, origin);
})(window, document);
