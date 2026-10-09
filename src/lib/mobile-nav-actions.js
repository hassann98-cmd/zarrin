const ROUTES = { "track-order": "track_order", shop: "shop", "فروشگاه": "shop" };

export function siteLink(value, urls = {}) {
  if (typeof value !== "string" || !/^\/(?!\/)/.test(value)) return value;
  const currentHref =
    typeof window === "undefined" ? "http://localhost/" : window.location.href;
  const base = new URL(urls.home || "/", currentHref);
  const url = new URL(value, base.origin);
  const prefix = base.pathname.replace(/\/+$/, "");
  const inside =
    prefix &&
    (url.pathname === prefix || url.pathname.startsWith(`${prefix}/`));
  if (!url.search && !url.hash) {
    const path = (inside ? url.pathname.slice(prefix.length) : url.pathname)
      .replace(/^\/+|\/+$/g, "");
    let slug = path;
    try {
      slug = decodeURIComponent(path);
    } catch {}
    const route = ROUTES[slug];
    if (route && urls[route]) return urls[route];
  }
  return inside
    ? url.toString()
    : new URL(value.replace(/^\/+/, ""), `${base.origin}${prefix}/`).toString();
}

export const MOBILE_NAV_ACTIONS = Object.freeze([
  "assistant",
  "categories",
  "home",
  "shop",
  "account",
  "track",
  "cart",
  "link",
]);

/** Resolve an editable floating-nav action to a safe native URL or an in-app drawer. */
export function resolveMobileNavItem(item, options = {}) {
  const urls = options.urls ?? {};
  const action = MOBILE_NAV_ACTIONS.includes(item?.action)
    ? item.action
    : "home";
  let href = urls.home || "/";
  let opensDrawer;

  switch (action) {
    case "assistant":
      href = options.assistantUrl || urls.home || "/";
      break;
    case "categories":
      href = options.categoriesUrl || options.shopUrl || urls.shop || "/";
      opensDrawer = "categories";
      break;
    case "home":
      href = urls.home || "/";
      break;
    case "shop":
      href = options.shopUrl || urls.shop || "/";
      break;
    case "account":
      href = options.isLoggedIn ? urls.dashboard || urls.login : urls.login;
      href ||= urls.home || "/";
      break;
    case "track":
      href = urls.track_order || urls.home || "/";
      break;
    case "cart":
      href = urls.cart || urls.home || "/";
      opensDrawer = "cart";
      break;
    case "link":
      href = typeof item?.url === "string" && item.url.trim()
        ? item.url.trim()
        : urls.home || "/";
      break;
  }

  return {
    ...item,
    action,
    href,
    opensDrawer,
    targetBlank: action === "link" && Boolean(item?.target_blank),
  };
}
