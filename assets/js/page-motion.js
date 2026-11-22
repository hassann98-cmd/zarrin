/* Native anchors/HTML/history remain authoritative; this module never fetches or replaces a document. */
(function (win, doc) {
  'use strict';
  var config = win.jluxePageMotion || {};
  var showTimer, resetTimer, intentTimer, bar, pressed, pressTimer, slowTimer, softBusy=false, originPoint=null;
  function clearPress() {
    win.clearTimeout(pressTimer);
    if (pressed) pressed.classList.remove('jluxe-navigation-pressed');
    pressed = null;
  }
  function press(link) {
    if (!config.transitions || !target(link,true)) return;
    clearPress(); pressed = link;
    link.classList.add('jluxe-navigation-pressed');
    pressTimer = win.setTimeout(clearPress, 240);
  }
  var warmed = new Set();
  var activeHint = false;
  var speculation;
  var speculationHints = new Map();
  var speculationLimit = 6;
  var supportsRules = typeof win.HTMLScriptElement?.supports === 'function' && win.HTMLScriptElement.supports('speculationrules');
  function privateSession() {
    return /(?:^|;\s*)(?:woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_[^=;]*|wordpress_logged_in_[^=;]*)=/.test(doc.cookie);
  }
  var blocked = /\/(?:wp-admin|wp-login\.php|wp-json|cart|checkout|my-account|wc-api)(?:\/|$)/i;
  function target(link,feedback) {
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-jluxe-drawer-toggle') ||
        (link.target && link.target !== '_self') || /(?:external|nofollow)/i.test(link.rel || '') ||
        link.matches('.add_to_cart_button,[data-jluxe-account-link],[data-no-prefetch],[data-no-transition]')) return null;
    try {
      var url = new win.URL(link.href, win.location.href);
      var current = new win.URL(win.location.href);
      if (!/^https?:$/.test(url.protocol) || url.origin !== current.origin || url.username || url.password ||
          (url.search && (!feedback || Array.from(url.searchParams.keys()).some(function(key){return !/^(?:s|paged|page|orderby|order|post_type|product-page|min_price|max_price|filter_[a-z0-9_-]+|query_type_[a-z0-9_-]+|utm_[a-z0-9_-]+)$/.test(key);}))) || url.hash || (url.pathname === current.pathname && url.search === current.search) || /\.(?:pdf|zip|png|jpe?g|svg|webp|mp4|mp3)$/i.test(url.pathname) || blocked.test(decodeURIComponent(url.pathname))) return null;
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
    win.clearTimeout(showTimer); win.clearTimeout(resetTimer); win.clearTimeout(slowTimer);
    originPoint=null;
    if (bar) { bar.hidden = true; bar.removeAttribute('role'); bar.removeAttribute('aria-label'); }
  }
  function showProgress() {
    if (!bar) {
      bar = doc.createElement('div'); bar.className = 'jluxe-page-progress';
      var art=doc.createElement('span');art.className='jluxe-page-orbit';art.setAttribute('aria-hidden','true');
      var ns='http://www.w3.org/2000/svg',svg=doc.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 40 40');svg.setAttribute('fill','none');
      [['circle',{cx:20,cy:20,r:16}],['path',{d:'M20 4a16 16 0 0 1 16 16'}],['path',{'class':'jluxe-page-spark',d:'m20 13 2 5 5 2-5 2-2 5-2-5-5-2 5-2Z'}]].forEach(function(item){var node=doc.createElementNS(ns,item[0]);Object.keys(item[1]).forEach(function(key){node.setAttribute(key,item[1][key]);});svg.appendChild(node);});art.appendChild(svg);
      try {
        var logoUrl=new win.URL(config.logo || '',win.location.href);
        if(config.logo && logoUrl.origin===win.location.origin && /^https?:$/.test(logoUrl.protocol)){
          var frame=doc.createElement('span');frame.className='jluxe-page-logo';
          var logo=doc.createElement('img');logo.alt='';logo.width=36;logo.height=36;logo.decoding='async';
          logo.addEventListener('load',function(){art.classList.add('has-logo');});
          logo.addEventListener('error',function(){art.classList.remove('has-logo');});
          logo.src=logoUrl.href;frame.appendChild(logo);art.appendChild(frame);
        }
      }catch(_){} // Missing/unavailable logo keeps the built-in local graphic.
      var label = doc.createElement('span');label.className = 'jluxe-page-progress-label';
      bar.appendChild(art);bar.appendChild(label);
      bar.setAttribute('aria-live', 'polite');bar.setAttribute('aria-atomic', 'true');doc.body.appendChild(bar);
    }
    bar.querySelector('.jluxe-page-progress-label').textContent='در حال بازکردن صفحه…';
    bar.hidden=false;bar.setAttribute('role','status');bar.setAttribute('aria-label','در حال بازکردن صفحه');
  }
  function start() {
    if (!config.transitions) return;
    reset();
    // A brief touch, scroll gesture or fast navigation should not flash a loader.
    showTimer=win.setTimeout(showProgress,300);
    slowTimer=win.setTimeout(function(){if(bar && !bar.hidden)bar.querySelector('.jluxe-page-progress-label').textContent='کمی بیشتر طول کشید؛ لطفاً صبر کنید…';},5000);
    resetTimer=win.setTimeout(reset,10000); // Fail open if navigation never reports completion.
  }
  function publishSpeculationRules() {
    if (speculation) speculation.remove();
    speculation = doc.createElement('script');
    speculation.type = 'speculationrules';
    speculation.setAttribute('data-jluxe-speculation', '');
    speculation.textContent = JSON.stringify({ prefetch: [{ source: 'list', urls: Array.from(warmed), eagerness: 'moderate' }] });
    doc.head.appendChild(speculation);
  }
  function prefetch(link) {
    var connection = win.navigator.connection || {};
    if (!config.prefetch || config.cacheSafe === false || (!supportsRules && activeHint) || privateSession() || doc.visibilityState === 'hidden' || connection.saveData ||
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
    if (url.href !== home && !category && !(link && link.matches('[data-jluxe-product-link],.woocommerce-LoopProduct-link,.woocommerce-loop-product__link,.jluxe-product-card-title'))) return;
    var hints = doc.querySelectorAll('link[data-jluxe-page-prefetch],link[data-jluxe-mobile-home-prefetch]');
    var duplicate = Array.from(hints).some(function (hint) { return hint.href === url.href; });
    if (duplicate || (!supportsRules && hints.length >= 2)) return;
    var hint = doc.createElement('link');
    hint.href = url.href;
    hint.setAttribute('data-jluxe-page-prefetch', '');
    warmed.add(url.href);
    if (supportsRules) {
      // Speculation rules are low-eagerness and capped to a rolling list of six documents.
      speculationHints.set(url.href, hint);
      while (speculationHints.size > speculationLimit) {
        var oldest = speculationHints.keys().next().value;
        speculationHints.get(oldest).remove();
        speculationHints.delete(oldest);
        warmed.delete(oldest);
      }
      doc.head.appendChild(hint);
      publishSpeculationRules();
    } else {
      hint.rel = 'prefetch';
      hint.as = 'document';
      activeHint = true;
      var unlockTimer;
      function unlock() { activeHint = false; win.clearTimeout(unlockTimer); }
      hint.addEventListener('load', unlock, { once: true });
      hint.addEventListener('error', unlock, { once: true });
      unlockTimer = win.setTimeout(unlock, 5000);
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
    if (!target(link,true)) return;
    // Other routers (React login/archive/drawers) get to cancel the event first.
    Promise.resolve().then(function () {
      if (!event.defaultPrevented) { start(); press(link); }
      else if(!softBusy) reset();
    });
  });
  doc.addEventListener('pointerdown', function (event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link=event.target.closest?.('a[href]');
    if(target(link,true) && config.transitions){originPoint={x:event.clientX,y:event.clientY};press(link);}
  });
  doc.addEventListener('pointermove',function(event){if(originPoint && Math.hypot(event.clientX-originPoint.x,event.clientY-originPoint.y)>12)reset();},{passive:true});
  doc.addEventListener('pointercancel', reset);
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
  doc.addEventListener('jluxe:soft-navigation-start', function(){softBusy=true;start();});
  doc.addEventListener('jluxe:soft-navigation-end', function(){softBusy=false;reset();});
  win.addEventListener('pageshow', function(){softBusy=false;reset();});
  win.addEventListener('pagereveal',reset);
  win.addEventListener('pagehide', reset);
  win.addEventListener('pageswap', reset);
  win.addEventListener('popstate', reset);
})(window, document);
