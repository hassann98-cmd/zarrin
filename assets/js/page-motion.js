/* Native anchors/HTML/history remain authoritative; this module never fetches or replaces a document. */
(function (win, doc) {
  'use strict';
  var config = win.jluxePageMotion || {};
  var showTimer, resetTimer, intentTimer, bar, pressed, pressTimer;
  function clearPress() {
    win.clearTimeout(pressTimer);
    if (pressed) pressed.classList.remove('jluxe-navigation-pressed');
    pressed = null;
  }
  function press(link) {
    if (!config.transitions || !target(link)) return;
    clearPress(); pressed = link;
    link.classList.add('jluxe-navigation-pressed');
    pressTimer = win.setTimeout(clearPress, 240);
  }
  var warmed = new Set();
  var activeHint = false;
  var speculation;
  var supportsRules = typeof win.HTMLScriptElement?.supports === 'function' && win.HTMLScriptElement.supports('speculationrules');
  function privateSession() {
    return /(?:^|;\s*)(?:woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_[^=;]*|wordpress_logged_in_[^=;]*)=/.test(doc.cookie);
  }
  var blocked = /\/(?:wp-admin|wp-login\.php|wp-json|cart|checkout|my-account|wc-api)(?:\/|$)/i;
  function target(link) {
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-jluxe-drawer-toggle') ||
        (link.target && link.target !== '_self') || /(?:external|nofollow)/i.test(link.rel || '') ||
        link.matches('.add_to_cart_button,[data-jluxe-account-link],[data-no-prefetch],[data-no-transition]')) return null;
    try {
      var url = new win.URL(link.href, win.location.href);
      var current = new win.URL(win.location.href);
      if (!/^https?:$/.test(url.protocol) || url.origin !== current.origin || url.username || url.password ||
          url.search || url.hash || (url.pathname === current.pathname && url.search === current.search) || /\.(?:pdf|zip|png|jpe?g|svg|webp|mp4|mp3)$/i.test(url.pathname) || blocked.test(decodeURIComponent(url.pathname))) return null;
      if ((config.excluded || []).some(function (value) {
        var excluded = new win.URL(value, current);
        var path = excluded.pathname.replace(/\/+$/, '') || '/';
        return url.origin === excluded.origin && (url.pathname.replace(/\/+$/, '') === path || url.pathname.indexOf(path + '/') === 0);
      })) return null;
      return url;
    } catch (_) { return null; }
  }
  function reset() {
    clearPress();
    win.clearTimeout(showTimer); win.clearTimeout(resetTimer);
    if (bar) { bar.hidden = true; bar.removeAttribute('role'); bar.removeAttribute('aria-label'); }
  }
  function start() {
    if (!config.transitions) return;
    reset();
    showTimer = win.setTimeout(function () {
      if (!bar) {
        bar = doc.createElement('div'); bar.className = 'jluxe-page-progress';
        var label = doc.createElement('span');
        label.className = 'jluxe-page-progress-label';
        label.textContent = 'در حال بازکردن صفحه';
        bar.appendChild(label);
        bar.setAttribute('aria-live', 'polite');
        bar.setAttribute('aria-atomic', 'true');
        doc.body.appendChild(bar);
      }
      bar.hidden = false;
      bar.setAttribute('role', 'status'); bar.setAttribute('aria-label', 'در حال بازکردن صفحه');
    }, 180);
    resetTimer = win.setTimeout(reset, 10000); // canceled navigation must not leave a permanent loader
  }
  function prefetch(link) {
    var connection = win.navigator.connection || {};
    if (!config.prefetch || config.cacheSafe === false || activeHint || privateSession() || doc.visibilityState === 'hidden' || connection.saveData ||
        /^(slow-2g|2g|3g)$/.test(connection.effectiveType || '') ||
        win.jluxeCurrentAuthStatus !== false || win.JLuxeThemeSettings?.auth?.isLoggedIn === true) return;
    var url = target(link);
    if (!url || warmed.has(url.href)) return;
    var home;
    try { home = new win.URL(config.home, win.location.href).href; } catch (_) { return; }
    var category = false;
    try {
      var base = new win.URL(config.categoryBase, win.location.href);
      category = !!config.categoryBase && base.origin === url.origin &&
        url.pathname.indexOf(base.pathname.replace(/\/+$/, '') + '/') === 0;
    } catch (_) {}
    if (url.href !== home && !category && !link.matches('[data-jluxe-product-link],.woocommerce-LoopProduct-link,.woocommerce-loop-product__link,.jluxe-product-card-title')) return;
    var hints = doc.querySelectorAll('link[data-jluxe-page-prefetch],link[data-jluxe-mobile-home-prefetch]');
    if (hints.length >= 2 || Array.from(hints).some(function (hint) { return hint.href === url.href; })) return;
    var hint = doc.createElement('link');
    hint.rel = 'prefetch'; hint.as = 'document'; hint.href = url.href;
    hint.setAttribute('data-jluxe-page-prefetch', '');
    activeHint = true;
    var unlockTimer;
    function unlock() { activeHint = false; win.clearTimeout(unlockTimer); }
    hint.addEventListener('load', unlock, { once: true });
    hint.addEventListener('error', unlock, { once: true });
    unlockTimer = win.setTimeout(unlock, 5000);
    warmed.add(url.href);
    if (supportsRules) {
      // One cumulative list, no broad document rule and no JavaScript execution ahead of navigation.
      if (speculation) speculation.remove();
      speculation = doc.createElement('script');
      speculation.type = 'speculationrules';
      speculation.setAttribute('data-jluxe-speculation', '');
      speculation.textContent = JSON.stringify({ prefetch: [{ source: 'list', urls: Array.from(warmed) }] });
      // Keep budget markers separate: do not also issue rel=prefetch for the same URL.
      hint.removeAttribute('rel');
      doc.head.appendChild(hint);
      doc.head.appendChild(speculation);
    } else {
      doc.head.appendChild(hint);
    }
  }
  // Shared entry point prevents the legacy mobile home warmer bypassing this budget.
  win.jluxePrefetchPublicPage = function (href) {
    var link = doc.createElement('a'); link.href = href; prefetch(link);
  };
  doc.addEventListener('click', function (event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest?.('a[href]');
    if (!target(link)) return;
    // Other routers (React login/archive/drawers) get to cancel the event first.
    Promise.resolve().then(function () {
      if (!event.defaultPrevented) { start(); press(link); }
      else clearPress();
    });
  });
  doc.addEventListener('pointerdown', function (event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    press(event.target.closest?.('a[href]'));
  });
  doc.addEventListener('pointercancel', clearPress);
  doc.addEventListener('keydown', function (event) { if (event.key === 'Escape') reset(); });
  doc.addEventListener('visibilitychange', function () { if (doc.visibilityState === 'hidden') reset(); });
  doc.addEventListener('pointerover', function (event) {
    if (event.pointerType === 'touch') return;
    win.clearTimeout(intentTimer);
    var link = event.target.closest?.('a[href]');
    intentTimer = win.setTimeout(function () { prefetch(link); }, 100);
  });
  doc.addEventListener('pointerout', function () { win.clearTimeout(intentTimer); });
  doc.addEventListener('focusin', function (event) { prefetch(event.target.closest?.('a[href]')); });
  doc.addEventListener('touchstart', function (event) { prefetch(event.target.closest?.('a[href]')); }, { passive: true });
  doc.addEventListener('jluxe:soft-navigation-start', start);
  doc.addEventListener('jluxe:soft-navigation-end', reset);
  win.addEventListener('pageshow', reset);
  win.addEventListener('pagehide', reset);
  win.addEventListener('pageswap', reset);
  win.addEventListener('popstate', reset);
})(window, document);
