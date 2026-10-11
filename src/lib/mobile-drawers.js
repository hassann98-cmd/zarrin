/** Keep cart/category drawers above the mobile dock; dismiss them from their own close controls or backdrop. */
export const MOBILE_DRAWER_Z_INDEX = 170;

const DRAWERS = Object.freeze({
  categories: Object.freeze({
    openEvent: "jluxe:open-categories",
    closeEvent: "jluxe:close-categories",
  }),
  cart: Object.freeze({
    openEvent: "jluxe:open-cart",
    closeEvent: "jluxe:close-cart",
  }),
});

const STATE_EVENT = "jluxe:mobile-drawer-state";
const activeByWindow = new WeakMap();
const scrollLocksByDocument = new WeakMap();

function isWindowLike(win) {
  return Boolean(
    win &&
      typeof win.dispatchEvent === "function" &&
      typeof win.addEventListener === "function" &&
      typeof win.CustomEvent === "function",
  );
}

function dispatch(win, name, detail = {}) {
  win.dispatchEvent(new win.CustomEvent(name, { detail }));
}

export function getActiveMobileDrawer(win = globalThis.window) {
  if (!isWindowLike(win)) return null;
  if (activeByWindow.has(win)) return activeByWindow.get(win);

  const saved = win.document?.documentElement?.getAttribute(
    "data-jluxe-mobile-drawer",
  );
  const active = Object.prototype.hasOwnProperty.call(DRAWERS, saved) ? saved : null;
  activeByWindow.set(win, active);
  return active;
}

function setActive(win, active, previous) {
  activeByWindow.set(win, active);
  const root = win.document?.documentElement;
  if (root) {
    if (active) root.setAttribute("data-jluxe-mobile-drawer", active);
    else root.removeAttribute("data-jluxe-mobile-drawer");
  }
  dispatch(win, STATE_EVENT, { active, previous });
}

/** Open a drawer and close the other drawer first; its state survives late island mounting. */
export function openMobileDrawer(name, win = globalThis.window) {
  const config = DRAWERS[name];
  if (!config || !isWindowLike(win)) return false;

  const previous = getActiveMobileDrawer(win);
  if (previous !== name) {
    setActive(win, name, previous);
    if (previous) dispatch(win, DRAWERS[previous].closeEvent, { source: name });
  }
  dispatch(win, config.openEvent, { source: "mobile-navigation" });
  return true;
}

/** Close only the requested drawer, leaving the active state of any other panel alone. */
export function closeMobileDrawer(name, win = globalThis.window) {
  const config = DRAWERS[name];
  if (!config || !isWindowLike(win)) return false;

  const previous = getActiveMobileDrawer(win);
  if (previous !== name) return false;
  setActive(win, null, previous);
  dispatch(win, config.closeEvent, { source: "drawer-dismiss" });
  return true;
}

/** Toggle a requested drawer; its own close controls and backdrop remain available while open. */
export function toggleMobileDrawer(name, win = globalThis.window) {
  if (getActiveMobileDrawer(win) === name) {
    closeMobileDrawer(name, win);
    return null;
  }
  return openMobileDrawer(name, win) ? name : null;
}

/** Keep legacy open/close events in sync while supporting requests sent before island mount. */
export function registerMobileDrawer(name, handlers = {}, win = globalThis.window) {
  const config = DRAWERS[name];
  if (!config || !isWindowLike(win)) return () => {};

  const onOpen = () => {
    const previous = getActiveMobileDrawer(win);
    if (previous !== name) {
      setActive(win, name, previous);
      if (previous) dispatch(win, DRAWERS[previous].closeEvent, { source: name });
    }
    handlers.open?.();
  };
  const onClose = () => {
    const previous = getActiveMobileDrawer(win);
    if (previous === name) setActive(win, null, previous);
    handlers.close?.();
  };

  win.addEventListener(config.openEvent, onOpen);
  win.addEventListener(config.closeEvent, onClose);
  if (getActiveMobileDrawer(win) === name) handlers.open?.();

  return () => {
    win.removeEventListener(config.openEvent, onOpen);
    win.removeEventListener(config.closeEvent, onClose);
  };
}

export function subscribeMobileDrawerState(handler, win = globalThis.window) {
  if (!isWindowLike(win) || typeof handler !== "function") return () => {};
  const onState = (event) => handler(event.detail?.active ?? null);
  win.addEventListener(STATE_EVENT, onState);
  handler(getActiveMobileDrawer(win));
  return () => win.removeEventListener(STATE_EVENT, onState);
}

/** Reference-count drawer scroll locks so one panel's exit cannot unlock another. */
export function setMobileDrawerScrollLock(name, locked, win = globalThis.window) {
  if (!Object.prototype.hasOwnProperty.call(DRAWERS, name) || !isWindowLike(win)) return;
  const document = win.document;
  const body = document?.body;
  if (!document || !body) return;

  let state = scrollLocksByDocument.get(document);
  if (locked) {
    if (!state) {
      state = { owners: new Set(), originalOverflow: body.style.overflow };
      scrollLocksByDocument.set(document, state);
    }
    state.owners.add(name);
    body.style.overflow = "hidden";
    return;
  }

  if (!state || !state.owners.delete(name)) return;
  if (state.owners.size > 0) {
    body.style.overflow = "hidden";
    return;
  }
  body.style.overflow = state.originalOverflow;
  scrollLocksByDocument.delete(document);
}
