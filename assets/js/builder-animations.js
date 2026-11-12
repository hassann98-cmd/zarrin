/* Progressive enhancement: content is always visible without this script. */
(function (win, doc) {
 'use strict';
 if (!win.IntersectionObserver || !win.matchMedia) return;
 var reduced = win.matchMedia('(prefers-reduced-motion: reduce)');
 if (reduced.matches) return;
 var observer = new win.IntersectionObserver(function (entries) {
  entries.forEach(function (entry) {
   if (entry.isIntersecting) {
    entry.target.classList.add('jvb-entering');
    if (entry.target.dataset.jvbRepeat !== '1') observer.unobserve(entry.target);
   } else entry.target.classList.remove('jvb-entering');
  });
 }, {threshold: 0.08});
 doc.querySelectorAll('[data-jvb-animation]:not([data-jvb-animation="none"])').forEach(function (node) { observer.observe(node); });
 reduced.addEventListener?.('change', function (event) {
  if (event.matches) { observer.disconnect(); doc.querySelectorAll('.jvb-entering').forEach(function (node) { node.classList.remove('jvb-entering'); }); }
 });
})(window, document);
