const AUTH_HISTORY_KEY = "__jluxeAuthOverlay";
const ROUTE_ONLY_QUERY_KEYS = new Set(["redirect", "redirect_to", "reauth"]);

function routeIdentity(value, base) {
  try {
    const url = new URL(value, base);
    const query = [...url.searchParams.entries()]
      .filter(([key]) => !ROUTE_ONLY_QUERY_KEYS.has(key))
      .sort(([aKey, aValue], [bKey, bValue]) =>
        aKey === bKey
          ? aValue.localeCompare(bValue)
          : aKey.localeCompare(bKey),
      );
    return JSON.stringify({
      origin: url.origin,
      pathname: url.pathname.replace(/\/+$/, "") || "/",
      query,
    });
  } catch {
    return "";
  }
}

/**
 * Open the guest login island as a history-backed overlay. The server-rendered
 * page underneath stays mounted, so leaving login restores its React islands
 * and current scroll/cart state without requesting another document.
 */
export function setupAuthNavigation({
  win = window,
  doc = win.document,
  loginUrl,
  homeUrl,
  loadAuth,
  mountIsland,
}) {
  if (!win || !doc?.body || !loginUrl || !homeUrl || !loadAuth || !mountIsland) {
    return () => {};
  }

  const loginIdentity = routeIdentity(loginUrl, win.location.href);
  const homeIdentity = routeIdentity(homeUrl, win.location.href);
  if (
    !loginIdentity ||
    !homeIdentity ||
    routeIdentity(win.location.href, win.location.href) === loginIdentity
  ) {
    // A direct request to the account route already has its server-rendered
    // React island; only storefront links need the client-side transition.
    return () => {};
  }

  let overlay = null;
  let unmountIsland = null;
  let opener = null;
  let priorOverflow = "";
  let inertSnapshot = [];
  let authModulePromise = null;

  function loadAuthOnce() {
    if (!authModulePromise) {
      authModulePromise = Promise.resolve()
        .then(loadAuth)
        .catch((error) => {
          authModulePromise = null;
          throw error;
        });
    }
    return authModulePromise;
  }

  function isLoginRoute(value) {
    return routeIdentity(value, win.location.href) === loginIdentity;
  }

  function isHomeRoute(value) {
    return routeIdentity(value, win.location.href) === homeIdentity;
  }

  function lockBackground() {
    priorOverflow = doc.body.style.overflow;
    doc.body.style.overflow = "hidden";
    inertSnapshot = [...doc.body.children]
      .filter(
        (element) =>
          element !== overlay &&
          !/^(SCRIPT|STYLE|LINK|NOSCRIPT)$/.test(element.tagName),
      )
      .map((element) => ({
        element,
        inert: Boolean(element.inert),
        hadInert: element.hasAttribute("inert"),
        ariaHidden: element.getAttribute("aria-hidden"),
      }));
    for (const { element } of inertSnapshot) {
      element.setAttribute("inert", "");
      element.setAttribute("aria-hidden", "true");
    }
    doc.body.setAttribute("data-jluxe-auth-overlay-open", "1");
  }

  function restoreBackground() {
    doc.body.style.overflow = priorOverflow;
    doc.body.removeAttribute("data-jluxe-auth-overlay-open");
    for (const { element, inert, hadInert, ariaHidden } of inertSnapshot) {
      if (hadInert) element.setAttribute("inert", "");
      else element.removeAttribute("inert");
      if ("inert" in element) element.inert = inert;
      if (ariaHidden === null) element.removeAttribute("aria-hidden");
      else element.setAttribute("aria-hidden", ariaHidden);
    }
    inertSnapshot = [];
  }

  function disposeOverlay() {
    const previousOverlay = overlay;
    if (!previousOverlay) return;
    overlay = null;
    if (unmountIsland) {
      unmountIsland();
      unmountIsland = null;
    }
    if (previousOverlay?.isConnected) previousOverlay.remove();
    restoreBackground();
    const previousOpener = opener;
    if (previousOpener?.isConnected && typeof previousOpener.focus === "function") {
      previousOpener.focus({ preventScroll: true });
    }
  }

  function showOverlay() {
    if (overlay) return;
    const root = doc.createElement("div");
    root.className = "jluxe-auth-overlay-root";
    root.id = "jluxe-auth-overlay-root";
    root.setAttribute("data-jluxe-auth-overlay-root", "");

    const island = doc.createElement("div");
    island.setAttribute("data-jluxe-island", "auth-page");
    const fallback = doc.createElement("p");
    fallback.className = "jluxe-auth-fallback";
    fallback.append(doc.createTextNode("در حال بازکردن صفحهٔ ورود… "));
    const fallbackLink = doc.createElement("a");
    fallbackLink.href = new URL(homeUrl, win.location.href).href;
    fallbackLink.textContent = "بازگشت به فروشگاه";
    fallback.append(fallbackLink);
    island.append(fallback);
    root.append(island);

    overlay = root;
    doc.body.append(root);
    lockBackground();

    const mountedRoot = root;
    Promise.resolve()
      .then(() => mountIsland(island, loadAuthOnce))
      .then((dispose) => {
        if (overlay === mountedRoot) {
          unmountIsland = dispose;
          const initialInput = mountedRoot.querySelector(
            '[role="dialog"] input:not([disabled])',
          );
          if (initialInput) initialInput.focus({ preventScroll: true });
        } else if (typeof dispose === "function") {
          dispose();
        }
      })
      .catch((error) => {
        console.error("[jluxe] login overlay could not be mounted", error);
      });
  }

  function goBack() {
    if (!overlay) return;
    if (win.history.state?.[AUTH_HISTORY_KEY] && isLoginRoute(win.location.href)) {
      win.history.back();
      return;
    }
    // If another script replaced the SPA entry, preserve the link's original
    // destination rather than leaving the overlay stuck open.
    win.location.assign(homeUrl);
  }

  function eligibleAnchor(event) {
    const isClick = event.type === "click";
    if (
      event.defaultPrevented ||
      (isClick && typeof event.button === "number" && event.button !== 0) ||
      (isClick &&
        (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey))
    ) {
      return null;
    }
    const target = event.target;
    const link = target?.closest?.("a[href]");
    if (
      !link ||
      link.hasAttribute("download") ||
      (link.target && link.target.toLowerCase() !== "_self") ||
      link.relList?.contains("external")
    ) {
      return null;
    }
    let url;
    try {
      url = new URL(link.href, win.location.href);
    } catch {
      return null;
    }
    if (url.origin !== win.location.origin) return null;
    return { link, url };
  }

  function handleClick(event) {
    const target = eligibleAnchor(event);
    if (!target) return;
    const { link, url } = target;

    if (overlay && isHomeRoute(url.href)) {
      event.preventDefault();
      event.stopPropagation();
      goBack();
      return;
    }
    if (overlay || !isLoginRoute(url.href) || isLoginRoute(win.location.href)) {
      return;
    }

    const nextState = {
      ...(win.history.state && typeof win.history.state === "object"
        ? win.history.state
        : {}),
      [AUTH_HISTORY_KEY]: true,
    };
    try {
      win.history.pushState(nextState, "", url.href);
    } catch {
      // Leave the native same-origin link alone if History API navigation is unavailable.
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    opener = link;
    showOverlay();
  }

  function handlePopState() {
    if (win.history.state?.[AUTH_HISTORY_KEY] && isLoginRoute(win.location.href)) {
      showOverlay();
      return;
    }
    if (overlay) disposeOverlay();
  }

  function handleAuthClose() {
    goBack();
  }

  function prefetchFromInteraction(event) {
    if (overlay) return;
    const target = eligibleAnchor(event);
    if (target && isLoginRoute(target.url.href)) {
      void loadAuthOnce().catch(() => {});
    }
  }

  doc.addEventListener("click", handleClick, true);
  doc.addEventListener("pointerover", prefetchFromInteraction, true);
  doc.addEventListener("focusin", prefetchFromInteraction, true);
  doc.addEventListener("touchstart", prefetchFromInteraction, {
    capture: true,
    passive: true,
  });
  win.addEventListener("popstate", handlePopState);
  win.addEventListener("jluxe:auth-close", handleAuthClose);

  return () => {
    doc.removeEventListener("click", handleClick, true);
    doc.removeEventListener("pointerover", prefetchFromInteraction, true);
    doc.removeEventListener("focusin", prefetchFromInteraction, true);
    doc.removeEventListener("touchstart", prefetchFromInteraction, true);
    win.removeEventListener("popstate", handlePopState);
    win.removeEventListener("jluxe:auth-close", handleAuthClose);
    disposeOverlay();
  };
}
