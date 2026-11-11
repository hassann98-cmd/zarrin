const MOBILE_VIEWPORT = "(max-width: 767.98px)";
const PREFETCH_SELECTOR = 'link[data-jluxe-mobile-home-prefetch]';
const SLOW_NETWORKS = new Set(["slow-2g", "2g"]);

function canPrefetchOnMobile(win) {
  if (!win?.document || !win.location || !win.navigator) return false;
  if (typeof win.matchMedia === "function" && !win.matchMedia(MOBILE_VIEWPORT).matches) {
    return false;
  }
  const connection = win.navigator.connection;
  if (connection?.saveData || SLOW_NETWORKS.has(connection?.effectiveType)) {
    return false;
  }
  return true;
}

/** Add one low-priority, same-origin hint for the real home document. */
export function prefetchMobileHome(href, win = globalThis.window) {
  if (!canPrefetchOnMobile(win) || typeof href !== "string" || !href.trim()) {
    return false;
  }

  let current;
  let target;
  try {
    current = new URL(win.location.href);
    target = new URL(href, current);
  } catch {
    return false;
  }
  if (target.origin !== current.origin) return false;

  // A fragment never changes the fetched document. Avoid warming the current
  // page, but allow its normal homepage link when the current URL has a query.
  target.hash = "";
  if (target.pathname === current.pathname && target.search === current.search) {
    return false;
  }

  const existing = win.document.querySelector(PREFETCH_SELECTOR);
  if (existing) return existing.href === target.href;

  const hint = win.document.createElement("link");
  hint.rel = "prefetch";
  hint.href = target.href;
  hint.setAttribute("data-jluxe-mobile-home-prefetch", "");
  win.document.head.appendChild(hint);
  return true;
}

/** Warm the homepage once the browser is idle; native anchors remain the fallback. */
export function scheduleMobileHomePrefetch(href, win = globalThis.window) {
  if (!canPrefetchOnMobile(win)) return () => {};

  if (typeof win.requestIdleCallback === "function") {
    const task = win.requestIdleCallback(
      () => prefetchMobileHome(href, win),
      { timeout: 2200 },
    );
    return () => {
      if (typeof win.cancelIdleCallback === "function") {
        win.cancelIdleCallback(task);
      }
    };
  }

  const task = win.setTimeout(() => prefetchMobileHome(href, win), 900);
  return () => win.clearTimeout(task);
}
