/* Shared with React and the non-module WooCommerce/tracking scripts. */
(function (root) {
  'use strict';
  // Shared JSON transport for React and classic account/tracking scripts.
  // Covers response parsing too. Timeout/abort never implies server rollback.
  function requestJson(url, options, timeoutMs) {
    if (timeoutMs === undefined) timeoutMs = 20000;
    var controller = typeof root.AbortController === 'function' ? new root.AbortController() : null;
    var init = Object.assign({}, options);
    if (controller) init.signal = controller.signal;
    return new Promise(function (resolve, reject) {
      var settled = false;
      var timer = timeoutMs > 0 ? root.setTimeout(function () {
        if (settled) return;
        settled = true;
        if (controller) controller.abort();
        var error = new Error('پاسخ سرور تأیید نشد؛ پیش از تلاش دوباره وضعیت را بررسی کنید.');
        error.code = 'jluxe_response_timeout';
        reject(error);
      }, timeoutMs) : null;
      var flight;
      try { flight = root.fetch(url, init); } catch (error) { flight = Promise.reject(error); }
      Promise.resolve(flight).then(function (response) {
        return response.json().then(function (data) { return { response: response, data: data }; });
      }).then(function (result) {
        if (settled) return;
        settled = true; root.clearTimeout(timer); resolve(result);
      }, function (cause) {
        if (settled) return;
        settled = true; root.clearTimeout(timer);
        var error = new Error('پاسخ سرور تأیید نشد؛ پیش از تلاش دوباره وضعیت را بررسی کنید.');
        error.code = 'jluxe_response_unknown';
        reject(error);
      });
    });
  }
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
  /** Keep a dock switch outside transformed product wrappers and own its tap,
   * including compatibility clicks retargeted after the dock changes shape. */
  var dockSwitchUntil = 0;
  function isDockSwitchInteraction() { return Date.now() < dockSwitchUntil; }
  function protectDockSwitch(button, bar) {
    if (!button || button.dataset.jluxeSwitchProtected) return;
    button.dataset.jluxeSwitchProtected = '1';
    var doc = button.ownerDocument, win = doc.defaultView;
    doc.body.appendChild(button);
    if (bar) doc.body.appendChild(bar);
    var gesture = null;
    win.addEventListener('keydown', function(event){if(!button.contains(event.target))dockSwitchUntil=0;}, true);
    button.addEventListener('click', function(){dockSwitchUntil=Date.now()+1500;}, true);
    function blocked() { return doc.documentElement.hasAttribute('data-jluxe-mobile-drawer'); }
    function stop(event) { event.preventDefault(); event.stopImmediatePropagation(); }
    win.addEventListener('pointerdown', function (event) {
      if (!button.contains(event.target) || event.isPrimary === false || event.button > 0) { gesture = null; if (!event.target.closest || !event.target.closest('[data-jluxe-mobile-bar-toggle], [data-jluxe-sticky-cta-mode-toggle]')) dockSwitchUntil = 0; return; }
      if (blocked()) { stop(event); return; }
      dockSwitchUntil = Date.now() + 1500;
      gesture = { x: event.clientX, y: event.clientY, time: Date.now(), done: false, moved: false };
      if (button.setPointerCapture) { try { button.setPointerCapture(event.pointerId); } catch (error) {} }
      event.stopPropagation();
    }, true);
    win.addEventListener('pointermove', function (event) {
      if (gesture && Math.hypot(event.clientX - gesture.x, event.clientY - gesture.y) > 16) gesture.moved = true;
    }, true);
    win.addEventListener('pointercancel', function () { if (gesture) gesture.moved = true; }, true);
    // Complete on pointerup before layout changes can retarget the compatibility click.
    win.addEventListener('pointerup', function (event) {
      if (!gesture || gesture.done) return;
      if (gesture.moved || blocked()) { gesture.done = true; stop(event); return; }
      gesture.done = true;
      stop(event);
      button.click();
    }, true);
    // Older touch-only browsers need the same ownership, including delegated touch handlers.
    if (!win.PointerEvent) {
      win.addEventListener('touchstart', function (event) {
        if (!button.contains(event.target)) { gesture = null; if (!event.target.closest || !event.target.closest('[data-jluxe-mobile-bar-toggle], [data-jluxe-sticky-cta-mode-toggle]')) dockSwitchUntil=0; return; }
        if (blocked()) { stop(event); return; }
        dockSwitchUntil=Date.now()+1500;
        var touch = event.touches[0];
        gesture = { x: touch.clientX, y: touch.clientY, time: Date.now(), done: false, moved: false };
        event.stopImmediatePropagation();
      }, { capture: true, passive: false });
      win.addEventListener('touchmove', function () { if (gesture) gesture.moved = true; }, { capture: true, passive: true });
      win.addEventListener('touchend', function (event) {
        if (!gesture) return;
        stop(event);
        if (!gesture.done && !gesture.moved && !blocked()) { gesture.done = true; button.click(); }
      }, { capture: true, passive: false });
      win.addEventListener('touchcancel', function () { if (gesture) gesture.moved = true; }, true);
    }
    win.addEventListener('click', function (event) {
      var direct = button.contains(event.target);
      if (direct && blocked()) { stop(event); return; }
      var recent = gesture && Date.now() - gesture.time < 1200 && event.detail > 0 && (gesture.done || Math.hypot(event.clientX - gesture.x, event.clientY - gesture.y) < 32);
      if (!recent) return;
      if (gesture.done || gesture.moved || blocked()) { stop(event); return; }
      gesture.done = true;
      if (!direct) { stop(event); button.click(); }
    }, true);
  }
  root.JLuxeStorefrontUtils = Object.freeze({ isDockSwitchInteraction: isDockSwitchInteraction, protectDockSwitch: protectDockSwitch, requestJson: requestJson, normalizeDigits: normalizeDigits, normalizePhone: normalizePhone, buildFilterUrl: buildFilterUrl, activateDialog: activateDialog });
}(globalThis));
