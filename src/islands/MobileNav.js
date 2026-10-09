import { siteUrl } from "../lib/api.js";
import {
  isMobileNavItemActive as isActive,
  mobileNavContainerStyle,
} from "../lib/mobile-navigation.js";
import { j as jsx } from "../lib/jsx.js";
import { r as React } from "../lib/icons.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { u as useCart } from "../lib/use-cart.js";
import { N as navIcons, H as fallbackIcon } from "../components/nav-icons.js";

const DESTINATIONS = {
  track: { href: siteUrl("track_order") },
  shop: { href: siteUrl("shop"), opensDrawer: "categories" },
  home: { href: siteUrl("home") },
  account: { href: siteUrl("dashboard") },
  cart: { href: siteUrl("cart"), opensDrawer: "cart" },
};

function MobileNav() {
  const { snapshot } = useCart();
  const settings = getThemeSettings();
  const mobile = settings.mobile ?? {};
  const style = mobile.nav_style ?? {};
  const loggedIn = settings.auth?.isLoggedIn ?? false;
  const [currentHref, setCurrentHref] = React.useState(
    typeof window === "undefined" ? "/" : window.location.href,
  );
  const [hasPriceBar, setHasPriceBar] = React.useState(
    () =>
      typeof document !== "undefined" &&
      Boolean(document.querySelector("[data-jluxe-mobile-price-bar]")),
  );

  React.useEffect(() => {
    const toggle = () => setHasPriceBar((visible) => !visible);
    window.addEventListener("jluxe:toggle-mobile-bar", toggle);
    return () => window.removeEventListener("jluxe:toggle-mobile-bar", toggle);
  }, []);
  React.useEffect(() => {
    const syncPath = () => setCurrentHref(window.location.href);
    window.addEventListener("popstate", syncPath);
    return () => window.removeEventListener("popstate", syncPath);
  }, []);

  if (hasPriceBar) return null;

  const colors = {
    background: style.background ?? "#fff",
    active: style.active_color ?? "#087A68",
    icon: style.icon_color ?? "#667085",
    iconBg: style.icon_bg ?? "#f5f7f8",
    activeBg: style.active_bg ?? "#e8f4f1",
    radius: style.radius ?? "22",
    height: style.height ?? "68",
  };
  const shadow =
    style.shadow === "none"
      ? "none"
      : style.shadow === "strong"
        ? "0 -10px 30px -8px rgba(0,0,0,.20)"
        : style.shadow === "soft"
          ? "0 -5px 18px -7px rgba(0,0,0,.10)"
          : "0 -7px 24px -7px rgba(0,0,0,.15)";
  const items = (mobile.nav_items ?? [])
    .filter((item) => item.enabled && DESTINATIONS[item.id])
    .map((item) => ({
      ...item,
      ...DESTINATIONS[item.id],
      href:
        item.id === "account"
          ? siteUrl(loggedIn ? "dashboard" : "login")
          : DESTINATIONS[item.id].href,
      label: item.id === "account" && !loggedIn ? "ورود" : item.label,
    }));

  return jsx.jsx("nav", {
    className: "jluxe-mobile-nav fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-border bg-surface/95 md:hidden",
    style: {
      backgroundColor: colors.background,
      borderRadius: `${colors.radius}px ${colors.radius}px 0 0`,
      boxShadow: shadow,
      color: colors.icon,
      backdropFilter: style.blur === false ? "none" : "blur(14px)",
      WebkitBackdropFilter: style.blur === false ? "none" : "blur(14px)",
      ...mobileNavContainerStyle(colors.height),
    },
    "aria-label": "ناوبری موبایل",
    "data-jluxe-mobile-nav-dock": "",
    children: [
      jsx.jsx("span", {
        key: "accent-rule",
        style: {
          position: "absolute",
          insetInlineStart: 0,
          insetInlineEnd: 0,
          bottom: "100%",
          height: "1px",
          background: `linear-gradient(90deg,transparent, ${colors.active}, transparent)`,
          opacity: 0.18,
        },
        "aria-hidden": true,
      }),
      ...items.map((item) => {
        const active = isActive(currentHref, item.href, item.id);
        const Icon = navIcons[item.icon] ?? fallbackIcon;
        return jsx.jsxs(
          "a",
          {
            href: item.href,
            onClick: (event) => {
              if (item.opensDrawer) {
                event.preventDefault();
                window.dispatchEvent(
                  new CustomEvent(
                    item.opensDrawer === "cart"
                      ? "jluxe:open-cart"
                      : "jluxe:open-categories",
                  ),
                );
              } else {
                setCurrentHref(item.href);
              }
            },
            className: "jluxe-mobile-nav-item relative flex flex-1 flex-col items-center justify-center gap-1 pt-1 text-[10.5px] font-medium",
            style: {
              color: active ? colors.active : colors.icon,
              transition:
                style.animate === false
                  ? "none"
                  : "color 220ms ease,transform 180ms ease",
            },
            "aria-current": active ? "page" : undefined,
            children: [
              jsx.jsxs("span", {
                className: "jluxe-mobile-nav-icon relative flex size-10 items-center justify-center rounded-[15px]",
                style: {
                  backgroundColor: active ? colors.activeBg : colors.iconBg,
                  color: active ? colors.active : colors.icon,
                  boxShadow: active
                    ? `0 5px 14px -8px ${colors.active}`
                    : "0 2px 10px -8px rgba(16,24,40,.18)",
                  transform: active ? "translateY(-2px)" : "translateY(0)",
                  transition: style.animate === false ? "none" : "all 220ms ease",
                },
                ...(item.id === "cart" ? { "data-jluxe-cart-icon-mobile": "" } : {}),
                children: [
                  jsx.jsx(Icon, { className: "size-[21px]", "aria-hidden": true }),
                  item.id === "cart" && snapshot.itemCount > 0
                    ? jsx.jsx("span", {
                        className: "absolute -end-1 -top-1 flex size-[17px] items-center justify-center rounded-full bg-accent-hover text-[10px] font-bold text-accent-foreground shadow-md",
                        children: snapshot.itemCount,
                      })
                    : null,
                ],
              }),
              jsx.jsx("span", { className: "leading-none", children: item.label }),
            ],
          },
          item.id,
        );
      }),
    ],
  });
}

export { MobileNav as default };
