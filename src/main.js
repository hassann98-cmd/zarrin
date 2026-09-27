import { mountIsland, startSmoothScrolling } from "./lib/islands.js";
import Lenis from "lenis";
import "./styles/storefront.css";
const islands = {
  footer: () => import("./islands/Footer.js"),
  "header-logo": () =>
    import("./islands/Header.js").then((m) => ({ default: m.BrandLogo })),
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
};
startSmoothScrolling(window, Lenis);
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
  observer.observe(element);
}
