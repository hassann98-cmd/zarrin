import {
  markLiteDevice,
  mountIsland,
  startSmoothScrolling,
} from "./lib/islands.js";
import { setupAuthNavigation } from "./lib/auth-navigation.js";
markLiteDevice(window);
setupAuthNavigation(window);
import "./styles/storefront.css";
import { setupPwa } from "./lib/pwa.js";
// R88 — PWAِ امن: ثبت بعد از load؛ هیچ HTMLی کش نمی‌شود (inc/pwa.php).
setupPwa(window, window.JLuxeThemeSettings?.pwa);
const islands = {
  footer: () => import("./islands/Footer.js"),
  "header-logo": () =>
    import("./islands/Header.js").then((m) => ({ default: m.BrandLogo })),
  "header-mobile-search": () =>
    import("./islands/Header.js").then((m) => ({ default: m.MobileHeaderSearch })),
  "header-actions": () => import("./islands/Header.js"),
  "mega-menu": () => import("./islands/MegaMenu.js"),
  "mini-cart": () => import("./islands/MiniCart.js"),
  "category-drawer": () => import("./islands/CategoryDrawer.js"),
  "mobile-nav": () => import("./islands/MobileNav.js"),
  "product-details-demo": () => import("./islands/ProductDetails.js"),
  "shop-archive-demo": () => import("./islands/ShopArchive.js"),
  "cart-checkout-demo": () => import("./islands/CartCheckout.js"),
  "ai-assistant": () => import("./islands/AiAssistant.js"),
  "auth-page": () => import("./islands/AuthPage.jsx"),
  "categories-browser": () => import("./islands/CategoriesBrowser.js"),
};
// R88 — Lenis فقط روی دستگاهِ دارای ماوس، به‌صورتِ chunkِ جدا و بعد از بیکار شدنِ مرورگر.
const whenIdle = window.requestIdleCallback
  ? (cb) => window.requestIdleCallback(cb, { timeout: 2500 })
  : (cb) => setTimeout(cb, 200);
whenIdle(() =>
  startSmoothScrolling(window, () =>
    import("lenis").then((module) => module.default),
  ),
);
for (const element of document.querySelectorAll("[data-jluxe-island]")) {
  const name = element.dataset.jluxeIsland;
  const load = islands[name];
  if (!load) continue;
  if (name !== "footer" || !("IntersectionObserver" in window)) {
    mountIsland(element, load);
    continue;
  }
  const observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        observer.disconnect();
        mountIsland(element, load);
      }
    },
    { rootMargin: "1200px 0px" },
  );
  // R88 — ریشهٔ آیلند داخلِ پوستهٔ سمتِ سرورِ فوتر خالی (بدونِ ارتفاع) است؛ خودِ فوتر مشاهده می‌شود.
  observer.observe(element.closest("[data-jluxe-footer]") || element);
}
