const MIN_NAV_ROW_HEIGHT = 60;
const MAX_NAV_ROW_HEIGHT = 82;
const DEFAULT_NAV_ROW_HEIGHT = 68;

function normalizedPathname(pathname) {
  const path = String(pathname || "/").replace(/\/+$/, "");
  return path || "/";
}

/**
 * Match a bottom-navigation destination against the current same-origin URL.
 * Account links stay active on WooCommerce endpoint/detail routes nested below
 * the configured account page; other destinations require an exact path.
 */
export function isMobileNavItemActive(currentHref, targetHref, itemId) {
  try {
    const current = new URL(currentHref);
    const target = new URL(targetHref, current);
    if (current.origin !== target.origin) return false;

    const currentPath = normalizedPathname(current.pathname);
    const targetPath = normalizedPathname(target.pathname);
    if (currentPath === targetPath) return true;

    return (
      itemId === "account" &&
      targetPath !== "/" &&
      currentPath.startsWith(`${targetPath}/`)
    );
  } catch {
    return false;
  }
}

/** Keep the configured content row intact while adding the iOS home-indicator inset. */
export function mobileNavContainerStyle(configuredHeight) {
  const parsed = Number.parseInt(configuredHeight, 10);
  const rowHeight = Number.isFinite(parsed)
    ? Math.min(MAX_NAV_ROW_HEIGHT, Math.max(MIN_NAV_ROW_HEIGHT, parsed))
    : DEFAULT_NAV_ROW_HEIGHT;
  const safeArea = "env(safe-area-inset-bottom, 0px)";

  return {
    boxSizing: "border-box",
    height: `calc(${rowHeight}px + ${safeArea})`,
    paddingBottom: safeArea,
  };
}
