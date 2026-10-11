let activeWindow = null;
let activeListener = null;
let currentCleanup = null;
let pendingOpen = false;

/** Request the mini-cart drawer, remembering the request if its island is not mounted yet. */
export function requestCartDrawerOpen(win = globalThis.window) {
  if (!win || typeof win.dispatchEvent !== "function") return false;
  if (activeWindow !== win || !activeListener) pendingOpen = true;
  win.dispatchEvent(new win.CustomEvent("jluxe:open-cart"));
  return true;
}

/** Register the drawer listener and consume an open request queued during island startup. */
export function registerCartDrawerOpen(handler, win = globalThis.window) {
  if (!win || typeof win.addEventListener !== "function" || typeof handler !== "function") {
    return () => {};
  }

  currentCleanup?.();
  const onOpen = () => handler();
  activeWindow = win;
  activeListener = onOpen;
  win.addEventListener("jluxe:open-cart", onOpen);

  const cleanup = () => {
    win.removeEventListener("jluxe:open-cart", onOpen);
    if (activeListener === onOpen) {
      activeListener = null;
      activeWindow = null;
    }
    if (currentCleanup === cleanup) currentCleanup = null;
  };
  currentCleanup = cleanup;

  if (pendingOpen) {
    pendingOpen = false;
    onOpen();
  }

  return cleanup;
}
