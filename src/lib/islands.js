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
  const isCart = name === "mini-cart" || name === "cart-checkout-demo";
  const href =
    existing?.getAttribute("href") || siteUrl(isCart ? "cart" : "shop");
  const label =
    existing?.textContent.trim() ||
    (isCart ? "مشاهده سبد خرید" : "رفتن به فروشگاه");
  const message = "بارگذاری این بخش ممکن نشد. ";
  const report = (error) => {
    console.error(`[jluxe] ${name} could not be rendered`, error);
    const CustomEvent = element.ownerDocument.defaultView.CustomEvent;
    element.dispatchEvent(
      new CustomEvent("jluxe:load-error", { bubbles: true }),
    );
  };
  const fallback = React.createElement(
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

/** Animation is progressive enhancement; initialization failures keep native scrolling. */
export function startSmoothScrolling(win, Lenis) {
  try {
    if (
      !win.matchMedia ||
      win.matchMedia("(prefers-reduced-motion: reduce)").matches
    )
      return;
    const lenis = new Lenis({ autoRaf: true });
    win.addEventListener("pagehide", () => lenis.destroy(), { once: true });
  } catch (error) {
    console.warn(
      "[jluxe] smooth scrolling unavailable; native scrolling retained",
      error,
    );
  }
}
