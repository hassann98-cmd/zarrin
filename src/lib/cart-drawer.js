import { openMobileDrawer, registerMobileDrawer } from "./mobile-drawers.js";

/** Request the mini-cart drawer, retaining the request until its island registers. */
export function requestCartDrawerOpen(win = globalThis.window) {
  return openMobileDrawer("cart", win);
}

/** Preserve the existing cart-specific registration API and custom event. */
export function registerCartDrawerOpen(handler, win = globalThis.window) {
  return registerMobileDrawer("cart", { open: handler }, win);
}
