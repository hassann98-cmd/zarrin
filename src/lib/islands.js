import React from "react";
import { createRoot } from "react-dom/client";
import { siteUrl } from "./api.js";

class IslandBoundary extends React.Component {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error) {
    this.props.onError(error);
  }

  render() {
    return this.state.failed ? this.props.fallback : this.props.children;
  }
}

/** A broken optional island must not remove the native login/cart/shop route. */
export function mountIsland(element, load) {
  const name = element.dataset.jluxeIsland;
  const existing = element.querySelector("a[href]");
  const isCart = name === "mini-cart";
  const href =
    existing?.getAttribute("href") || siteUrl(isCart ? "cart" : "shop");
  const label =
    existing?.textContent.trim() ||
    (isCart ? "مشاهده سبد خرید" : "رفتن به فروشگاه");
  const message = "بارگذاری این بخش ممکن نشد. ";
  // R92 — islandی که HTMLِ کامل و کارای سرور را در خود دارد (data-jluxe-keep-ssr)
  // در صورتِ خطا همان HTML را نگه می‌دارد، نه پیامِ خطا (لینک‌های ?cat= بدونِ JS کار می‌کنند).
  const keepSsr = element.hasAttribute("data-jluxe-keep-ssr");
  const ssrHtml = keepSsr ? element.innerHTML : "";
  const report = (error) => {
    console.error(`[jluxe] ${name} could not be rendered`, error);
    const CustomEvent = element.ownerDocument.defaultView.CustomEvent;
    element.dispatchEvent(
      new CustomEvent("jluxe:load-error", { bubbles: true }),
    );
  };
  const fallback = keepSsr
    ? React.createElement("div", {
        className: "jluxe-island-ssr",
        style: { display: "contents" },
        dangerouslySetInnerHTML: { __html: ssrHtml },
      })
    : React.createElement(
        "span",
        { role: "alert", className: "jluxe-island-error" },
        message,
        React.createElement("a", { href, className: "underline" }, label),
      );
  return Promise.resolve()
    .then(load)
    .then((module) => {
      const root = createRoot(element, { onCaughtError() {} });
      root.render(
        React.createElement(
          IslandBoundary,
          { fallback, onError: report },
          React.createElement(module.default),
        ),
      );
      return () => root.unmount();
    })
    .catch((error) => {
      // Import failures occur before React can mount its error boundary.
      if (keepSsr) {
        report(error);
        return () => {};
      }
      const node = element.ownerDocument.createElement("span");
      node.setAttribute("role", "alert");
      node.className = "jluxe-island-error";
      const link = element.ownerDocument.createElement("a");
      link.setAttribute("href", href);
      link.textContent = label;
      node.append(message, link);
      element.replaceChildren(node);
      report(error);
      return () => {};
    });
}

/**
 * R88 — اسکرولِ نرم فقط جایی که واقعاً اثر دارد. Lenis به‌طورِ پیش‌فرض
 * (syncTouch: false) اسکرولِ لمسی را نرم نمی‌کند؛ روی گوشی فقط یک حلقهٔ
 * requestAnimationFrame دائمی و ~۱۲KB جاوااسکریپتِ اضافه بود. حالا:
 * ماوس/تاچ‌پدِ دقیق + بدونِ reduced-motion + بدونِ «صرفه‌جویی در داده».
 */
export function shouldSmoothScroll(win) {
  try {
    if (!win || !win.matchMedia) return false;
    if (win.matchMedia("(prefers-reduced-motion: reduce)").matches)
      return false;
    if (!win.matchMedia("(hover: hover) and (pointer: fine)").matches)
      return false;
    if (win.navigator?.connection?.saveData) return false;
    return true;
  } catch {
    return false;
  }
}

/**
 * Animation is progressive enhancement; initialization failures keep native
 * scrolling. `loadLenis` is either the Lenis class or a loader returning it
 * (a dynamic import, so touch devices never download the library).
 */
export function startSmoothScrolling(win, loadLenis) {
  if (!shouldSmoothScroll(win)) return Promise.resolve(null);
  const isClass =
    typeof loadLenis === "function" &&
    /^class\b/.test(Function.prototype.toString.call(loadLenis));
  return Promise.resolve()
    .then(() => (isClass ? loadLenis : loadLenis()))
    .then((Lenis) => {
      const lenis = new Lenis({ autoRaf: true });
      win.addEventListener("pagehide", () => lenis.destroy(), { once: true });
      return lenis;
    })
    .catch((error) => {
      console.warn(
        "[jluxe] smooth scrolling unavailable; native scrolling retained",
        error,
      );
      return null;
    });
}

/**
 * R88 — «jluxe-lite» روی <html> برای دستگاهِ کم‌توان (Device Memory ≤ 2GB،
 * فعلاً فقط مرورگرهای Chromium گزارشش می‌کنند) یا وقتی کاربر «صرفه‌جویی در
 * داده» را روشن کرده. CSS با این کلاس blurها را برمی‌دارد (storefront.css).
 */
export function markLiteDevice(win) {
  try {
    const nav = win.navigator || {};
    const lite =
      Boolean(nav.connection?.saveData) ||
      (typeof nav.deviceMemory === "number" && nav.deviceMemory <= 2);
    if (lite) win.document.documentElement.classList.add("jluxe-lite");
    return lite;
  } catch {
    return false;
  }
}
