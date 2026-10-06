/* Shared with React and the non-module WooCommerce/tracking scripts. */
(function (root) {
  'use strict';
  function normalizeDigits(value) {
    return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
      var code = digit.charCodeAt(0);
      return String(code >= 1776 ? code - 1776 : code - 1632);
    });
  }
  function normalizePhone(value) {
    var match = normalizeDigits(value).replace(/[\s()\-]+/g, '').match(/^(?:(?:\+|00)?98|0)?(9[0-9]{9})$/);
    return match ? match[1] : '';
  }
  function buildFilterUrl(base, current, values) {
    var url = new URL(base, current);
    var previous = new URL(current);
    // Only preserve unrelated supported state, never the old page number or obsolete filters.
    ['s', 'post_type', 'orderby', 'on_sale', 'min_rating'].forEach(function (key) {
      if (previous.searchParams.has(key)) url.searchParams.set(key, previous.searchParams.get(key));
    });
    // Preserve WooCommerce layered attribute filters as well.
    previous.searchParams.forEach(function (value, key) {
      if (/^(filter_|query_type_)/.test(key) && !/^(filter_brand|query_type_brand)$/.test(key)) url.searchParams.set(key, value);
    });
    ['product_cat', 'product_brand', 'min_price', 'max_price', 'filter_stock', 'on_sale'].forEach(function (key) {
      var value = values[key];
      if (value !== undefined && value !== null && String(value) !== '') url.searchParams.set(key, String(value));
      else if (Object.prototype.hasOwnProperty.call(values, key)) url.searchParams.delete(key);
    });
    url.searchParams.delete('paged');
    url.searchParams.delete('product-page');
    return url.toString();
  }
  /** Keyboard focus management shared by native and React modal dialogs. */
  function activateDialog(dialog, onClose) {
    if (!dialog) return function () {};
    var doc = dialog.ownerDocument;
    var win = doc.defaultView;
    var previous = doc.activeElement;
    var previousTabIndex = dialog.getAttribute('tabindex');
    dialog.setAttribute('tabindex', '-1');
    function controls() {
      return Array.prototype.filter.call(dialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'), function (node) {
        return !node.disabled && node.tabIndex >= 0 && !node.closest('[hidden], [inert], [aria-hidden="true"], .hidden') && win.getComputedStyle(node).display !== 'none' && win.getComputedStyle(node).visibility !== 'hidden';
      });
    }
    function first() { return dialog.querySelector('[data-dialog-initial-focus]') || controls()[0] || dialog; }
    function keydown(event) {
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); onClose(); return; }
      if (event.key !== 'Tab') return;
      var nodes = controls();
      var firstNode = nodes[0] || dialog;
      var lastNode = nodes[nodes.length - 1] || dialog;
      if (event.shiftKey && (doc.activeElement === firstNode || doc.activeElement === dialog || !dialog.contains(doc.activeElement))) {
        event.preventDefault(); lastNode.focus();
      } else if (!event.shiftKey && (doc.activeElement === lastNode || !dialog.contains(doc.activeElement))) {
        event.preventDefault(); firstNode.focus();
      }
    }
    function focusin(event) { if (!dialog.contains(event.target)) first().focus({ preventScroll: true }); }
    doc.addEventListener('keydown', keydown, true);
    doc.addEventListener('focusin', focusin);
    first().focus({ preventScroll: true });
    return function () {
      doc.removeEventListener('keydown', keydown, true);
      doc.removeEventListener('focusin', focusin);
      if (previousTabIndex === null) dialog.removeAttribute('tabindex');
      else dialog.setAttribute('tabindex', previousTabIndex);
      if (previous && previous.isConnected && previous.focus) previous.focus({ preventScroll: true });
    };
  }
  /* R63 (فاز ۵): هدر روی اسکرول کمی جمع/سایه‌دار می‌شود — ظریف، بدونِ
  جابه‌جاییِ layout (فقط سایه + فشرده‌سازیِ ردیفِ اول با کلاس). */
  try {
    var masthead = document.getElementById('masthead');
    if (masthead && !masthead.dataset.jluxeCondenseBound) {
      masthead.dataset.jluxeCondenseBound = '1';
      var condenseTick = false;
      var condenseUpdate = function () {
        condenseTick = false;
        masthead.classList.toggle('jluxe-header-condensed', (root.scrollY || 0) > 96);
      };
      root.addEventListener('scroll', function () {
        if (condenseTick) return;
        condenseTick = true;
        root.requestAnimationFrame(condenseUpdate);
      }, { passive: true });
      condenseUpdate();
    }
  } catch (e) { /* هدرِ حاضر نبود = هیچ. */ }
  root.JLuxeStorefrontUtils = Object.freeze({ normalizeDigits: normalizeDigits, normalizePhone: normalizePhone, buildFilterUrl: buildFilterUrl, activateDialog: activateDialog });
}(globalThis));
