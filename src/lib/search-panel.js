import { containSearchScroll } from "./search-scroll.js";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { jsx } from "react/jsx-runtime";

/** Native wheel/touch scrolling with boundary containment; keep the existing scroll helper API. */
export function containSearchWheel(element) {
  return element ? containSearchScroll(element, element.ownerDocument.defaultView, 0.7, { positioned: true }) : () => {};
}

export function searchPanelPosition(rect, viewport, mobile = false) {
  const gutter = 8;
  const leftEdge = viewport.left || 0;
  const topEdge = viewport.top || 0;
  const width = Math.max(0, Math.min(mobile ? Math.max(rect.width, 300) : rect.width, viewport.width - gutter * 2));
  const left = Math.max(leftEdge + gutter, Math.min(rect.left, leftEdge + viewport.width - gutter - width));
  const top = Math.max(topEdge + gutter, Math.min(rect.bottom + 8, topEdge + viewport.height - gutter));
  return { left, top, width, maxHeight: Math.max(0, Math.min(viewport.height * (mobile ? 0.6 : 0.7), topEdge + viewport.height - top - gutter)) };
}

/** Native scroll area is also used by the fullscreen mobile-search dialog. */
export function SearchScrollArea({ children, className = "", ...props }) {
  const ref = useRef(null);
  useEffect(() => containSearchWheel(ref.current), []);
  return jsx("div", { ...props, ref, "data-lenis-prevent": "", "data-jluxe-search-scroll": "", className: `jluxe-search-scroll ${className}`, children });
}

/** Body portal escapes the transformed/backdrop-filtered header's stacking context. */
export function SearchResultsPanel({ anchorRef, id, mobile = false, onClose, children }) {
  const panelRef = useRef(null);
  const closeRef = useRef(onClose);
  closeRef.current = onClose;
  const [position, setPosition] = useState(null);
  useLayoutEffect(() => {
    const anchor = anchorRef.current;
    const panel = panelRef.current;
    if (!anchor || !panel) return;
    const doc = anchor.ownerDocument;
    const win = doc.defaultView;
    let frame = 0;
    const measure = () => {
      frame = 0;
      const rect = anchor.getBoundingClientRect();
      if (!rect.width || !rect.height) { closeRef.current(); return; }
      const viewport = win.visualViewport;
      const top = viewport?.offsetTop || 0;
      const height = viewport?.height || win.innerHeight;
      if (rect.bottom < top || rect.top > top + height) { closeRef.current(); return; }
      setPosition(searchPanelPosition(rect, {
        left: viewport?.offsetLeft || 0, top: viewport?.offsetTop || 0,
        width: viewport?.width || doc.documentElement.clientWidth || win.innerWidth,
        height: viewport?.height || win.innerHeight,
      }, mobile));
    };
    const schedule = (event) => {
      if (event?.target?.nodeType && (event.target === panel || panel.contains(event.target))) return;
      if (!frame) frame = win.requestAnimationFrame(measure);
    };
    const inside = (target) => target && (anchor.contains(target) || panel.contains(target));
    const outside = (event) => { if (!inside(event.target)) closeRef.current(); };
    const keys = (event) => {
      if (!inside(event.target)) return;
      if (event.key === "Escape") {
        event.preventDefault();
        if (panel.contains(event.target)) anchor.querySelector("input")?.focus({ preventScroll: true });
        closeRef.current();
      } else if (event.key === "ArrowDown" && anchor.contains(event.target)) {
        const first = panel.querySelector("a[href], button:not([disabled]), [tabindex='0']");
        if (first) { event.preventDefault(); first.focus({ preventScroll: true }); }
      }
    };
    measure();
    const observer = typeof win.ResizeObserver === "function" ? new win.ResizeObserver(schedule) : null;
    observer?.observe(anchor);
    win.addEventListener("resize", schedule, { passive: true });
    doc.addEventListener("scroll", schedule, { capture: true, passive: true });
    win.visualViewport?.addEventListener("resize", schedule, { passive: true });
    win.visualViewport?.addEventListener("scroll", schedule, { passive: true });
    doc.addEventListener("pointerdown", outside, true);
    doc.addEventListener("focusin", outside);
    doc.addEventListener("keydown", keys);
    const releaseWheel = containSearchWheel(panel);
    return () => {
      if (frame) win.cancelAnimationFrame(frame);
      observer?.disconnect();
      win.removeEventListener("resize", schedule);
      doc.removeEventListener("scroll", schedule, true);
      win.visualViewport?.removeEventListener("resize", schedule);
      win.visualViewport?.removeEventListener("scroll", schedule);
      doc.removeEventListener("pointerdown", outside, true);
      doc.removeEventListener("focusin", outside);
      doc.removeEventListener("keydown", keys);
      releaseWheel();
    };
  }, [anchorRef, mobile]);
  return createPortal(jsx("div", {
    ref: panelRef, id, tabIndex: -1, role: "region", "aria-label": "نتایج جستجو",
    "data-lenis-prevent": "", "data-jluxe-search-scroll": "",
    className: `jluxe-search-portal jluxe-search-scroll ${mobile ? "jluxe-header-search-results--mobile" : "jluxe-header-search-results--desktop"} rounded-2xl border border-border bg-surface ${mobile ? "p-3" : "p-4"} shadow-lg`,
    style: { ...(position || {}), visibility: position ? "visible" : "hidden" },
    children,
  }), anchorRef.current?.ownerDocument.body || document.body);
}
