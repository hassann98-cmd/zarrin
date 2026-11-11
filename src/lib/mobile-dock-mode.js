/**
 * Read the mobile product-page dock from its authoritative DOM state.
 * The server-rendered bar is the initial purchase mode until its controller
 * marks the bar ready; after that, its `hidden` class reflects the last mode
 * even when React hydration missed the change event.
 */
function getDocumentReference(documentRef) {
  if (documentRef) return documentRef;
  return typeof document !== "undefined" ? document : null;
}

export function getMobilePriceBarVisible(documentRef) {
  documentRef = getDocumentReference(documentRef);
  if (!documentRef || typeof documentRef.querySelector !== "function") return false;
  const priceBar = documentRef.querySelector("[data-jluxe-mobile-price-bar]");
  if (!priceBar) return false;
  if (priceBar.getAttribute("data-jluxe-mobile-bar-ready") !== "true") return true;
  return !priceBar.classList.contains("hidden");
}

/**
 * Mode-change events carry an explicit value (not a toggle), so duplicate or
 * delayed delivery cannot invert the React state. Lifecycle events such as
 * `pageshow` fall back to the current DOM state.
 */
export function getMobilePriceBarVisibleFromEvent(event, documentRef) {
  const detail = event && event.detail;
  const value = detail && detail.priceBarVisible;
  return typeof value === "boolean"
    ? value
    : getMobilePriceBarVisible(documentRef);
}

export function hasExternalMobileNavigation(documentRef) {
  documentRef = getDocumentReference(documentRef);
  if (!documentRef || typeof documentRef.querySelector !== "function") return false;
  const nav = documentRef.querySelector("#bottom-navigation");
  if (!nav) return false;
  const explicitAvailability = nav.getAttribute("data-jluxe-mobile-bar-available");
  if (explicitAvailability === "true") return true;
  if (explicitAvailability === "false") return false;
  if (
    nav.hidden ||
    nav.classList.contains("hidden") ||
    nav.style.display === "none" ||
    nav.getAttribute("aria-hidden") === "true" ||
    nav.hasAttribute("inert") ||
    !nav.querySelector("a[href], button, [role='link'], [role='button']")
  ) {
    return false;
  }
  const view = documentRef.defaultView;
  const isMobileViewport = !!(view && typeof view.innerWidth === "number" && view.innerWidth < 768);
  const style = isMobileViewport && view && typeof view.getComputedStyle === "function"
    ? view.getComputedStyle(nav)
    : null;
  return !style || (style.display !== "none" && style.visibility !== "hidden");
}

export function hasExternalMobileNavigationFromEvent(event, documentRef) {
  const detail = event && event.detail;
  const value = detail && detail.externalNavigationAvailable;
  return typeof value === "boolean"
    ? value
    : hasExternalMobileNavigation(documentRef);
}
