import React from "react";
import { createRoot } from "react-dom/client";

const AUTH_HISTORY_KEY = "__jluxeAuthRoute";

function normalizePath(pathname) {
  const path = pathname.replace(/\/+$/, "");
  return path || "/";
}

function matchesRoute(candidateValue, expectedValue, win) {
  if (!candidateValue || !expectedValue) return false;
  try {
    const candidate = new win.URL(candidateValue, win.location.href);
    const expected = new win.URL(expectedValue, win.location.href);
    if (
      candidate.origin !== expected.origin ||
      normalizePath(candidate.pathname) !== normalizePath(expected.pathname)
    ) {
      return false;
    }
    // Preserve WordPress query-string routes (for example ?page_id=42), while
    // allowing a login link to add redirect_to or other return-target values.
    for (const [key, value] of expected.searchParams) {
      if (!candidate.searchParams.getAll(key).includes(value)) return false;
    }
    return true;
  } catch {
    return false;
  }
}

function isPlainClick(event) {
  return !(
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );
}

function createReactRenderer(win, loadAuthPage) {
  let authModulePromise;
  let root;
  let generation = 0;

  return async (container, open) => {
    const currentGeneration = ++generation;
    if (!open) {
      if (root) root.render(null);
      return;
    }

    authModulePromise ||= loadAuthPage();
    const authModule = await authModulePromise;
    if (currentGeneration !== generation || container.hidden) return;

    root ||= createRoot(container);
    root.render(React.createElement(authModule.default));

    const focusFirstField = () => {
      if (currentGeneration !== generation || container.hidden) return;
      const target =
        container.querySelector(".jluxe-auth-back") ||
        container.querySelector("input:not(:disabled)") ||
        container.querySelector("button:not(:disabled)") ||
        container.querySelector("h1");
      try {
        target?.focus({ preventScroll: true });
      } catch {
        target?.focus();
      }
    };
    if (typeof win.requestAnimationFrame === "function") {
      win.requestAnimationFrame(focusFirstField);
    } else {
      win.setTimeout(focusFirstField, 0);
    }
  };
}

/**
 * Turn guest account links into a client-side React screen. The current page
 * stays mounted underneath it, so closing the screen with its home link or the
 * browser Back button restores that page without requesting another document.
 */
export function setupAuthNavigation(
  win = typeof window === "undefined" ? null : window,
  {
    loginUrl,
    homeUrl,
    isLoggedIn,
    renderAuth,
    loadAuthPage = () => import("../islands/AuthPage.jsx"),
  } = {},
) {
  const doc = win?.document;
  if (!doc || !doc.body || !win.history?.pushState) return () => {};

  const settings = win.JLuxeThemeSettings || {};
  const resolvedLoginUrl = loginUrl || settings.urls?.login;
  const resolvedHomeUrl = homeUrl || settings.urls?.home;
  const loggedIn = isLoggedIn ?? Boolean(settings.auth?.isLoggedIn);

  // The standalone auth route already mounts this same React component. Keep
  // its native home link as a fallback; this router is for in-page transitions.
  if (
    loggedIn ||
    doc.body.classList.contains("jluxe-auth-page") ||
    !resolvedLoginUrl ||
    !resolvedHomeUrl
  ) {
    return () => {};
  }

  const render = renderAuth || createReactRenderer(win, loadAuthPage);
  let overlay = null;
  let routeOpen = false;
  let previousOverflow = "";
  let inertBackground = [];
  let returnFocus = null;
  let renderGeneration = 0;

  function ensureOverlay() {
    if (overlay) return overlay;
    overlay = doc.createElement("div");
    overlay.className = "jluxe-auth-route-overlay";
    overlay.setAttribute("data-jluxe-auth-route", "");
    overlay.setAttribute("role", "dialog");
    overlay.setAttribute("aria-modal", "true");
    overlay.setAttribute("aria-label", "ورود به حساب کاربری");
    overlay.setAttribute("aria-hidden", "true");
    overlay.hidden = true;
    overlay.inert = true;
    doc.body.append(overlay);
    return overlay;
  }

  function setRouteOpen(open) {
    if (routeOpen === open && (!open || overlay)) return;
    routeOpen = open;
    if (!open && !overlay) return;

    const node = ensureOverlay();
    const generation = ++renderGeneration;
    node.hidden = !open;
    node.inert = !open;
    node.setAttribute("aria-hidden", String(!open));

    if (open) {
      previousOverflow = doc.body.style.overflow;
      doc.body.style.overflow = "hidden";
      setBackgroundInert(true);
      doc.documentElement.classList.add("jluxe-auth-route-open");
      Promise.resolve(render(node, true)).catch((error) => {
        console.error("[jluxe] React auth route could not be rendered", error);
        if (routeOpen && generation === renderGeneration) {
          // Keep the server-rendered login URL usable if a lazy chunk fails.
          win.location.assign(win.location.href);
        }
      });
      return;
    }

    setBackgroundInert(false);
    doc.body.style.overflow = previousOverflow;
    doc.documentElement.classList.remove("jluxe-auth-route-open");
    Promise.resolve(render(node, false)).catch((error) => {
      console.error("[jluxe] React auth route could not be unmounted", error);
    });
    if (returnFocus?.isConnected) {
      try {
        returnFocus.focus({ preventScroll: true });
      } catch {
        returnFocus.focus();
      }
    }
  }

  function setBackgroundInert(inert) {
    if (inert) {
      inertBackground = Array.from(doc.body.children)
        .filter((child) => child !== overlay)
        .map((child) => ({ child, wasInert: Boolean(child.inert) }));
      for (const { child } of inertBackground) child.inert = true;
      return;
    }
    for (const { child, wasInert } of inertBackground) {
      if (child.isConnected) child.inert = wasInert;
    }
    inertBackground = [];
  }

  function isAuthHistoryEntry() {
    const state = win.history.state;
    return Boolean(
      state &&
        state[AUTH_HISTORY_KEY] &&
        matchesRoute(win.location.href, resolvedLoginUrl, win),
    );
  }

  function onPopState() {
    setRouteOpen(isAuthHistoryEntry());
  }

  function onKeyDown(event) {
    if (
      routeOpen &&
      event.key === "Escape" &&
      isAuthHistoryEntry()
    ) {
      event.preventDefault();
      win.history.back();
    }
  }

  function onClick(event) {
    if (!isPlainClick(event)) return;
    const target = event.target;
    const anchor = target?.closest?.("a[href]");
    if (!anchor || !doc.documentElement.contains(anchor)) return;
    if (
      (anchor.target && anchor.target.toLowerCase() !== "_self") ||
      anchor.hasAttribute("download") ||
      anchor.getAttribute("rel")?.split(/\s+/).includes("external")
    ) {
      return;
    }

    let destination;
    try {
      destination = new win.URL(anchor.href, win.location.href);
    } catch {
      return;
    }

    if (routeOpen && matchesRoute(destination.href, resolvedHomeUrl, win)) {
      if (!isAuthHistoryEntry()) return;
      event.preventDefault();
      win.history.back();
      return;
    }

    if (!matchesRoute(destination.href, resolvedLoginUrl, win)) return;

    const state = win.history.state;
    const nextState =
      state && typeof state === "object" && !Array.isArray(state)
        ? { ...state }
        : {};
    nextState[AUTH_HISTORY_KEY] = true;
    try {
      win.history.pushState(nextState, "", destination.href);
    } catch {
      // Leave the anchor's standard navigation intact when History API rejects it.
      return;
    }

    event.preventDefault();
    returnFocus = anchor;
    setRouteOpen(true);
  }

  win.addEventListener("popstate", onPopState);
  doc.addEventListener("click", onClick);
  doc.addEventListener("keydown", onKeyDown);

  return () => {
    win.removeEventListener("popstate", onPopState);
    doc.removeEventListener("click", onClick);
    doc.removeEventListener("keydown", onKeyDown);
    if (routeOpen) setRouteOpen(false);
    overlay?.remove();
  };
}
