import { j as jsx } from "../lib/jsx.js";
import { r as React } from "../lib/icons.js";
import {
  getActiveMobileDrawer,
  subscribeMobileDrawerState,
  toggleMobileDrawer,
} from "../lib/mobile-drawers.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { u as useCart } from "../lib/use-cart.js";
import { N as navIcons, H as fallbackIcon } from "../components/nav-icons.js";
import { resolveMobileNavItem, siteLink } from "../lib/mobile-nav-actions.js";

const DEFAULT_ITEMS = [
  { id: "support", label: "پشتیبانی", icon: "headphones", action: "assistant", enabled: true },
  { id: "categories", label: "دسته‌ها", icon: "menu", action: "categories", enabled: true },
  { id: "home", label: "خانه", icon: "home", action: "home", enabled: true },
  { id: "account", label: "اکانت", icon: "user", action: "account", enabled: true },
  { id: "cart", label: "سبد خرید", icon: "cart", action: "cart", enabled: true },
];
function isActiveRoute(currentHref, targetHref, itemId) {
  try {
    const current = new URL(currentHref);
    const target = new URL(targetHref, current);
    if (current.origin !== target.origin) return false;
    const currentPath = current.pathname.replace(/\/+$/, "") || "/";
    const targetPath = target.pathname.replace(/\/+$/, "") || "/";
    return (
      currentPath === targetPath ||
      (itemId === "account" &&
        targetPath !== "/" &&
        currentPath.startsWith(`${targetPath}/`))
    );
  } catch {
    return false;
  }
}

function MenuIcon({ className, ...props }) {
  return jsx.jsxs("svg", {
    className,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 2,
    strokeLinecap: "round",
    strokeLinejoin: "round",
    ...props,
    children: [
      jsx.jsx("path", { d: "M4 6h16" }),
      jsx.jsx("path", { d: "M4 12h16" }),
      jsx.jsx("path", { d: "M4 18h16" }),
    ],
  });
}

function isModifiedClick(event) {
  return (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );
}

function FloatingMobileNav() {
  const settings = getThemeSettings();
  const mobile = settings.mobile ?? {};
  const style = mobile.nav_style ?? {};
  const urls = settings.urls ?? {};
  const loggedIn = settings.auth?.isLoggedIn ?? false;
  const reducedMotion =
    typeof window !== "undefined" &&
    window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches;
  const animate = style.animate !== false && !reducedMotion;
  const { snapshot } = useCart();
  const [activeDrawer, setActiveDrawer] = React.useState(() =>
    typeof window === "undefined" ? null : getActiveMobileDrawer(window),
  );
  const [currentHref, setCurrentHref] = React.useState(
    typeof window === "undefined" ? "/" : window.location.href,
  );
  const [hasMobilePriceBar, setHasMobilePriceBar] = React.useState(
    () =>
      typeof document !== "undefined" &&
      Boolean(document.querySelector("[data-jluxe-mobile-price-bar]")),
  );

  React.useEffect(
    () => subscribeMobileDrawerState(setActiveDrawer, window),
    [],
  );
  React.useEffect(() => {
    const toggleMobilePriceBar = () => setHasMobilePriceBar((visible) => !visible);
    window.addEventListener("jluxe:toggle-mobile-bar", toggleMobilePriceBar);
    return () =>
      window.removeEventListener("jluxe:toggle-mobile-bar", toggleMobilePriceBar);
  }, []);
  React.useEffect(() => {
    const syncPath = () => setCurrentHref(window.location.href);
    window.addEventListener("popstate", syncPath);
    return () => window.removeEventListener("popstate", syncPath);
  }, []);

  if (hasMobilePriceBar) return null;

  const colors = {
    background: style.background ?? "#ffffff",
    active: style.active_color ?? "#087A68",
    icon: style.icon_color ?? "#667085",
    radius: style.radius ?? "22",
  };
  const shadow =
    style.shadow === "none"
      ? "none"
      : style.shadow === "strong"
        ? "0 -10px 30px -8px rgba(0,0,0,.20)"
        : style.shadow === "soft"
          ? "0 -5px 18px -7px rgba(0,0,0,.10)"
          : "0 -7px 24px -7px rgba(0,0,0,.15)";
  const radius = Math.max(12, Math.min(18, Number.parseInt(colors.radius, 10) || 18));
  const items = (mobile.floating_nav_items ?? DEFAULT_ITEMS)
    .filter((item) => item?.enabled)
    .map((item) => {
      const resolved = resolveMobileNavItem(item, {
        urls,
        shopUrl: urls.shop || settings.shopUrl,
        categoriesUrl: siteLink(settings.megaMenu?.url, urls) || urls.shop,
        assistantUrl: `${siteLink(urls.faq || "/faq", urls)}#open-ai-assistant`,
        isLoggedIn: loggedIn,
      });
      return {
        ...resolved,
        href: siteLink(resolved.href, urls),
        label:
          resolved.action === "account" && !loggedIn
            ? "ورود"
            : resolved.label,
      };
    });

  const handleClick = (event, item) => {
    if (isModifiedClick(event)) return;
    if (item.opensDrawer) {
      event.preventDefault();
      const wasOpen = getActiveMobileDrawer(window) === item.opensDrawer;
      if (
        !wasOpen &&
        typeof window.jluxeCloseSuggestedProductsModal === "function"
      ) {
        window.jluxeCloseSuggestedProductsModal();
      }
      toggleMobileDrawer(item.opensDrawer, window);
      return;
    }
    if (!item.targetBlank && item.action !== "assistant") {
      setCurrentHref(item.href);
    }
  };

  return jsx.jsx("nav", {
    className: "jluxe-mobile-nav jluxe-mobile-nav--floating fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-border bg-surface/95 md:hidden",
    style: {
      backgroundColor: colors.background,
      borderRadius: `${radius}px`,
      boxShadow: shadow,
      color: colors.icon,
      backdropFilter: style.blur === false ? "none" : "blur(14px)",
      WebkitBackdropFilter: style.blur === false ? "none" : "blur(14px)",
    },
    "aria-label": "ناوبری پایین موبایل",
    "data-jluxe-mobile-nav-dock": "",
    "data-jluxe-mobile-nav-variant": "floating",
    children: items.map((item) => {
      const drawerOpen = Boolean(
        item.opensDrawer && activeDrawer === item.opensDrawer,
      );
      const routeActive = isActiveRoute(
        currentHref,
        item.href,
        item.action === "account" ? "account" : item.id,
      );
      const active = routeActive || drawerOpen;
      const Icon = item.icon
        ? item.icon === "menu"
          ? MenuIcon
          : navIcons[item.icon] ?? fallbackIcon
        : null;
      const cartAction = item.action === "cart";
      const color = active ? colors.active : colors.icon;
      return jsx.jsxs(
        "a",
        {
          href: item.href,
          onClick: (event) => handleClick(event, item),
          target: item.targetBlank ? "_blank" : undefined,
          rel: item.targetBlank ? "noopener noreferrer" : undefined,
          className: "jluxe-mobile-nav-item jluxe-mobile-nav-item--floating relative flex flex-1 flex-col items-center justify-center gap-1 pt-1 text-[10.5px] font-medium",
          style: {
            color,
            transition: animate ? "color 220ms ease,transform 180ms ease" : "none",
          },
          "aria-current": routeActive ? "page" : undefined,
          ...(item.opensDrawer
            ? {
                "aria-label": drawerOpen
                  ? item.opensDrawer === "cart"
                    ? "بستن سبد خرید"
                    : "بستن دسته‌بندی‌ها"
                  : item.label,
                "aria-haspopup": "dialog",
                "aria-expanded": drawerOpen,
                "aria-controls":
                  item.opensDrawer === "cart"
                    ? "jluxe-cart-drawer-dialog"
                    : "jluxe-category-drawer-dialog",
                "data-jluxe-drawer-toggle": item.opensDrawer,
                "data-jluxe-drawer-open": drawerOpen ? "true" : "false",
              }
            : {}),
          children: [
            jsx.jsxs("span", {
              className: "jluxe-mobile-nav-icon jluxe-mobile-nav-icon--floating relative flex size-10 items-center justify-center rounded-[15px]",
              style: {
                backgroundColor: "transparent",
                color,
                boxShadow: "none",
                transform: "none",
                transition: animate ? "color 180ms ease" : "none",
              },
              ...(cartAction ? { "data-jluxe-cart-icon-mobile": "" } : {}),
              children: [
                Icon
                  ? jsx.jsx(Icon, {
                      className: "size-[23px]",
                      "aria-hidden": true,
                    })
                  : null,
                cartAction && snapshot.itemCount > 0
                  ? jsx.jsx("span", {
                      className: "jluxe-mobile-nav-cart-count absolute -end-1 -top-1 flex size-[17px] items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground shadow-md",
                      children: snapshot.itemCount,
                    })
                  : null,
              ],
            }),
            jsx.jsx("span", {
              className: "jluxe-mobile-nav-label leading-none",
              children: item.label,
            }),
          ],
        },
        item.id,
      );
    }),
  });
}

export { FloatingMobileNav as default };
