/*
 * Progressive, same-origin navigation for server-rendered product and blog
 * archives. The server HTML remains the source of truth; this script only
 * replaces an archive after fetching and validating the next rendered page.
 * Product, cart, checkout, account, login, and arbitrary CMS pages are never
 * intercepted. Native links remain available as the failure/no-JS fallback.
 */
(function (win, doc) {
  "use strict";

  var STATE_KEY = "jluxeSoftNavigation";
  var HEAD_SELECTOR = [
    'meta[name="description"]',
    'meta[name="robots"]',
    'meta[property^="og:"]',
    'meta[name^="twitter:"]',
    'link[rel="canonical"]',
    'link[rel="prev"]',
    'link[rel="next"]',
  ].join(",");
  var requestId = 0;
  var activeController = null;
  var scrollSaveTimer = null;
  var status = null;

  function currentMain() {
    return doc.querySelector("main[data-jluxe-soft-nav]");
  }

  function routeKind(main) {
    return main ? main.getAttribute("data-jluxe-soft-nav") || "" : "";
  }

  function safeUrl(value) {
    try {
      var url = new URL(value, win.location.href);
      if (
        url.origin !== win.location.origin ||
        (url.protocol !== "http:" && url.protocol !== "https:") ||
        url.username ||
        url.password
      ) {
        return null;
      }
      return url;
    } catch (_error) {
      return null;
    }
  }

  function historyState(kind, scrollY) {
    var previous = win.history.state;
    var next = previous && typeof previous === "object" ? Object.assign({}, previous) : {};
    next[STATE_KEY] = {
      kind: kind,
      scrollY: Number.isFinite(Number(scrollY)) ? Math.max(0, Number(scrollY)) : 0,
    };
    return next;
  }

  function getStatus() {
    if (status && status.isConnected) return status;
    status = doc.createElement("div");
    status.className = "jluxe-soft-navigation-status sr-only";
    status.setAttribute("role", "status");
    status.setAttribute("aria-live", "polite");
    status.setAttribute("aria-atomic", "true");
    doc.body.appendChild(status);
    return status;
  }

  function setBusy(main, busy) {
    if (!main) return;
    if (busy) {
      main.setAttribute("aria-busy", "true");
      main.classList.add("jluxe-soft-nav-busy");
      getStatus().textContent = "در حال به‌روزرسانی فهرست…";
      return;
    }
    main.removeAttribute("aria-busy");
    main.classList.remove("jluxe-soft-nav-busy");
  }

  function copySeoHead(nextDocument) {
    if (nextDocument.title) doc.title = nextDocument.title;
    doc.head.querySelectorAll(HEAD_SELECTOR).forEach(function (node) {
      node.remove();
    });
    nextDocument.head.querySelectorAll(HEAD_SELECTOR).forEach(function (node) {
      doc.head.appendChild(doc.importNode(node, true));
    });
    var nextLang = nextDocument.documentElement.getAttribute("lang");
    var nextDir = nextDocument.documentElement.getAttribute("dir");
    if (nextLang) doc.documentElement.setAttribute("lang", nextLang);
    if (nextDir) doc.documentElement.setAttribute("dir", nextDir);
  }

  function reducedMotion() {
    try {
      return !!win.matchMedia && win.matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch (_error) {
      return false;
    }
  }

  function scrollToResults(main, requestedScrollY) {
    if (typeof win.scrollTo !== "function") return;
    var top = Number(requestedScrollY);
    var behavior = "auto";
    if (!Number.isFinite(top)) {
      var target = main.querySelector(
        routeKind(main) === "catalog"
          ? ".jluxe-shop-toolbar, h1"
          : ".jluxe-blog-listing__title, h1",
      );
      var header = doc.querySelector(".jluxe-header-bar-sticky");
      var offset = header ? header.getBoundingClientRect().height : 0;
      top = target
        ? Math.max(0, target.getBoundingClientRect().top + win.scrollY - offset - 16)
        : 0;
      behavior = reducedMotion() ? "auto" : "smooth";
    }
    try {
      win.scrollTo({ top: Math.max(0, top), behavior: behavior });
    } catch (_error) {
      try { win.scrollTo(0, Math.max(0, top)); } catch (_ignored) { /* native scroll remains available */ }
    }
  }

  function announceUpdate(main) {
    var message = "فهرست به‌روزرسانی شد.";
    var count = main.querySelector(".jluxe-shop-toolbar-count, .woocommerce-result-count");
    if (count && count.textContent.trim()) {
      message = count.textContent.replace(/\s+/g, " ").trim() + "؛ فهرست به‌روزرسانی شد.";
    } else {
      var heading = main.querySelector("h1");
      if (heading && heading.textContent.trim()) {
        message = heading.textContent.replace(/\s+/g, " ").trim() + " بارگذاری شد.";
      }
    }
    getStatus().textContent = message;
  }

  function focusUpdatedContent(main) {
    var target = main.querySelector(
      routeKind(main) === "catalog"
        ? ".jluxe-shop-toolbar, h1"
        : ".jluxe-blog-listing__title, h1",
    ) || main;
    if (!target.hasAttribute("tabindex")) target.setAttribute("tabindex", "-1");
    try { target.focus({ preventScroll: true }); } catch (_error) { target.focus(); }
  }

  function requestPage(url) {
    var options = {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "text/html" },
    };
    if (activeController) activeController.abort();
    if (typeof win.AbortController === "function") {
      activeController = new win.AbortController();
      options.signal = activeController.signal;
    }
    return win.fetch(url.href, options);
  }

  /** Returns true on a soft transition, false when a native navigation should take over, null if superseded. */
  function navigate(rawUrl, options) {
    options = options || {};
    var source = currentMain();
    var kind = routeKind(source);
    var targetUrl = safeUrl(rawUrl);
    if (
      !source || !kind || !targetUrl || targetUrl.hash ||
      typeof win.fetch !== "function" || typeof win.DOMParser !== "function"
    ) return Promise.resolve(false);

    var token = ++requestId;
    setBusy(source, true);
    doc.dispatchEvent(new win.CustomEvent("jluxe:soft-navigation-start", {
      detail: { kind: kind, url: targetUrl.href },
    }));

    return requestPage(targetUrl)
      .then(function (response) {
        if (token !== requestId) return null;
        if (!response || !response.ok) return false;
        var contentType = response.headers && response.headers.get
          ? response.headers.get("content-type") || ""
          : "";
        if (contentType && !/text\/html/i.test(contentType)) return false;
        var finalUrl = safeUrl(response.url || targetUrl.href);
        if (!finalUrl) return false;
        return response.text().then(function (html) {
          if (token !== requestId) return null;
          var parser = new win.DOMParser();
          var nextDocument = parser.parseFromString(html, "text/html");
          var selector = 'main[data-jluxe-soft-nav="' + kind.replace(/[^a-z0-9_-]/gi, "") + '"]';
          var nextMain = nextDocument.querySelector(selector);
          var liveMain = currentMain();
          if (!nextMain || !liveMain || routeKind(liveMain) !== kind) return false;

          if (options.history !== "none") {
            try {
              win.history.replaceState(historyState(kind, win.scrollY), "", win.location.href);
              win.history.pushState(historyState(kind, 0), "", finalUrl.href);
            } catch (_error) {
              return false;
            }
          }

          var replacement = doc.importNode(nextMain, true);
          liveMain.replaceWith(replacement);
          doc.body.className = nextDocument.body.className;
          doc.body.style.overflow = "";
          copySeoHead(nextDocument);
          if (typeof win.jluxeEnhanceShopSort === "function") {
            win.jluxeEnhanceShopSort(replacement);
          }
          replacement.classList.add("jluxe-soft-nav-enter");
          win.setTimeout(function () {
            replacement.classList.remove("jluxe-soft-nav-enter");
          }, 220);
          setBusy(replacement, false);
          scrollToResults(replacement, options.scrollY);
          focusUpdatedContent(replacement);
          announceUpdate(replacement);
          doc.dispatchEvent(new win.CustomEvent("jluxe:soft-navigation", {
            detail: { kind: kind, url: finalUrl.href, main: replacement },
          }));
          return true;
        });
      })
      .then(function (result) {
        if (token !== requestId) return null;
        if (result === false) setBusy(currentMain(), false);
        if (activeController && token === requestId) activeController = null;
        if (result === true) {
          doc.dispatchEvent(new win.CustomEvent("jluxe:soft-navigation-end", {
            detail: { kind: kind, url: win.location.href },
          }));
        }
        return result;
      })
      .catch(function (error) {
        if (token !== requestId) return null;
        setBusy(currentMain(), false);
        activeController = null;
        if (error && error.name === "AbortError") return false;
        return false;
      });
  }

  function navigateOrFallback(url) {
    navigate(url.href).then(function (handled) {
      if (handled === false) win.location.href = url.href;
    });
  }

  function unmodifiedPrimaryClick(event, anchor) {
    return event.button === 0 &&
      !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey &&
      !anchor.hasAttribute("download") &&
      (!anchor.target || anchor.target === "_self") &&
      !(anchor.rel || "").split(/\s+/).includes("external");
  }

  function eligibleArchiveLink(main, anchor) {
    var kind = routeKind(main);
    if (kind === "catalog") {
      return !!anchor.closest(".woocommerce-pagination") || anchor.hasAttribute("data-jluxe-catalog-clear");
    }
    if (kind === "blog") {
      return !!anchor.closest(".jluxe-blog-pagination") || anchor.classList.contains("jluxe-blog-card__category");
    }
    return false;
  }

  function saveScrollPosition() {
    if (scrollSaveTimer) win.clearTimeout(scrollSaveTimer);
    scrollSaveTimer = win.setTimeout(function () {
      scrollSaveTimer = null;
      var main = currentMain();
      var kind = routeKind(main);
      var state = win.history.state && win.history.state[STATE_KEY];
      if (!kind || !state || state.kind !== kind) return;
      try {
        win.history.replaceState(historyState(kind, win.scrollY), "", win.location.href);
      } catch (_error) { /* scroll restoration is a progressive enhancement */ }
    }, 120);
  }

  var initialMain = currentMain();
  var initialKind = routeKind(initialMain);
  if (!initialMain || !initialKind) return;

  doc.addEventListener("click", function (event) {
    if (event.defaultPrevented) return;
    var main = currentMain();
    var anchor = event.target && event.target.closest
      ? event.target.closest("a[href]")
      : null;
    if (!main || !anchor || !main.contains(anchor) || !eligibleArchiveLink(main, anchor)) return;
    if (
      !unmodifiedPrimaryClick(event, anchor) ||
      typeof win.fetch !== "function" || typeof win.DOMParser !== "function"
    ) return;
    var url = safeUrl(anchor.href);
    if (!url || url.hash || url.href === win.location.href) return;
    event.preventDefault();
    navigateOrFallback(url);
  }, true);

  doc.addEventListener("submit", function (event) {
    var main = currentMain();
    var form = event.target;
    if (
      !main || routeKind(main) !== "blog" || !form ||
      !form.matches("form.jluxe-search") || !main.contains(form) ||
      typeof win.fetch !== "function" || typeof win.DOMParser !== "function"
    ) return;
    var formData;
    try { formData = new win.FormData(form); } catch (_error) { return; }
    var term = String(formData.get("s") || "").trim();
    if (!term) return;
    var url = safeUrl(form.getAttribute("action") || win.location.href);
    if (!url) return;
    formData.forEach(function (value, key) {
      if (typeof value === "string") url.searchParams.set(key, value);
    });
    if (url.hash) url.hash = "";
    event.preventDefault();
    navigateOrFallback(url);
  }, true);

  doc.addEventListener("change", function (event) {
    var main = currentMain();
    var select = event.target && event.target.closest
      ? event.target.closest('form.woocommerce-ordering select.orderby')
      : null;
    if (
      !main || routeKind(main) !== "catalog" || !select || !main.contains(select) ||
      typeof win.fetch !== "function" || typeof win.DOMParser !== "function"
    ) return;
    var url = safeUrl(win.location.href);
    if (!url) return;
    var form = select.closest("form.woocommerce-ordering");
    var action = form && form.getAttribute("action");
    var actionUrl = action ? safeUrl(action) : null;
    if (actionUrl && actionUrl.pathname !== url.pathname) url.pathname = actionUrl.pathname;
    else {
      var paginationBase = main.getAttribute("data-jluxe-pagination-base") || "page";
      var escapedBase = paginationBase.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      url.pathname = url.pathname.replace(new RegExp("/" + escapedBase + "/\\d+/?$", "i"), "/");
      url.pathname = url.pathname.replace(/\/product-page\/\d+\/?$/i, "/");
    }
    url.searchParams.delete("paged");
    url.searchParams.delete("product-page");
    url.searchParams.set("orderby", select.value);
    url.hash = "";
    event.preventDefault();
    event.stopImmediatePropagation();
    navigateOrFallback(url);
  }, true);

  win.addEventListener("scroll", saveScrollPosition, { passive: true });

  win.addEventListener("popstate", function (event) {
    var main = currentMain();
    if (!main) return;
    var kind = routeKind(main);
    var state = event.state && event.state[STATE_KEY];
    if (!state || state.kind !== kind) {
      win.location.reload();
      return;
    }
    navigate(win.location.href, { history: "none", scrollY: state.scrollY }).then(function (handled) {
      if (handled === false) win.location.reload();
    });
  });

  var initialState = win.history.state && win.history.state[STATE_KEY];
  if (!initialState || initialState.kind !== initialKind) {
    try { win.history.replaceState(historyState(initialKind, win.scrollY), "", win.location.href); }
    catch (_error) { /* native navigation remains fully functional */ }
  }

  win.JLuxeSoftNavigation = {
    navigate: navigate,
    isEnabled: function () {
      return !!currentMain() && typeof win.fetch === "function" && typeof win.DOMParser === "function";
    },
  };
})(window, document);
