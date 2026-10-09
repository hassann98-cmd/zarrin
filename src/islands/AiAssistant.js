import React, { useEffect, useLayoutEffect, useRef, useState } from "react";
import { restRequest, restStreamRequest, getSession } from "../lib/api.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { c as createIcon } from "../lib/icons.js";
import { X as CloseIcon } from "../icons/x.js";
import { C as CheckIcon } from "../icons/check.js";
import { S as SendIcon } from "../icons/send.js";
import { P as PhoneIcon } from "../icons/phone.js";
import { S as CartIcon } from "../icons/shopping-cart.js";
import { useDialog } from "../lib/use-dialog.js";

const h = React.createElement;
const DEFAULT_ASSISTANT_ACCENT = "#2f7468";
const MessageIcon = createIcon("MessageCircle", [
  ["path", { d: "M7.9 20A9 9 0 1 0 4 16.1L2 22Z", key: "message" }],
]);
const SparklesIcon = createIcon("Sparkles", [
  ["path", { d: "m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3Z", key: "main-sparkle" }],
  ["path", { d: "m19 14 1.2 2.8L23 18l-2.8 1.2L19 22l-1.2-2.8L15 18l2.8-1.2L19 14Z", key: "small-sparkle" }],
]);
const HeadsetIcon = createIcon("Headset", [
  ["path", { d: "M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z", key: "headset" }],
]);
const FALLBACK_MARKS = {
  phone: "☎",
  whatsapp: "◉",
  telegram: "➤",
  instagram: "◎",
  rubika: "R",
  bale: "B",
  eitaa: "e",
};
const BRAND_COLORS = {
  phone: "#177c4b",
  whatsapp: "#25a45a",
  telegram: "#168ac4",
  instagram: "#c13584",
  rubika: "#7048c8",
  bale: "#1677d2",
  eitaa: "#d34c4c",
};

function normalizeHref(value) {
  try {
    const url = new URL(value, window.location.href);
    if (url.protocol === "http:" || url.protocol === "https:") {
      url.hash = "";
      return url.href.replace(/\/$/, "");
    }
    if (url.protocol === "tel:") return `tel:${url.pathname.replace(/[^0-9+]/g, "")}`;
    if (url.protocol === "mailto:") return `mailto:${url.pathname.toLowerCase()}`;
  } catch {}
  return "";
}

function getContactMatch(href, contact = {}) {
  const normalized = normalizeHref(href);
  if (!normalized) return null;
  const channels = Array.isArray(contact.channels) ? contact.channels : [];
  return channels.find((item) => normalizeHref(item.url) === normalized) || null;
}

function safeHref(rawHref, contact, assistant) {
  const href = String(rawHref || "").trim();
  if (!href || /[\u0000-\u001f\u007f]/.test(href)) return "";
  const normalized = normalizeHref(href);
  if (!normalized) return "";
  if (normalized.startsWith("tel:") || normalized.startsWith("mailto:")) {
    return getContactMatch(href, contact) ? normalized : "";
  }
  try {
    const url = new URL(normalized);
    if (url.username || url.password) return "";
    const home = new URL(window.location.href);
    if (url.origin === home.origin) return url.href;
    const allowed = [
      ...(Array.isArray(contact?.channels) ? contact.channels.map((item) => item.url) : []),
      assistant?.handoffWhatsapp,
      assistant?.handoffTelegram,
      ...(assistant?.enableTicketForm ? [] : [assistant?.handoffFormUrl]),
    ].filter(Boolean);
    return allowed.some((candidate) => normalizeHref(candidate) === normalized)
      ? url.href
      : "";
  } catch {
    return "";
  }
}

function getPhoneHoursState(contact) {
  const hours = contact?.hours || {};
  if (!hours.enabled) return null;
  try {
    const options = {
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
      weekday: "short",
    };
    if (contact.timezone) options.timeZone = contact.timezone;
    const parts = new Intl.DateTimeFormat("en-US", options).formatToParts(new Date());
    const hour = Number(parts.find((part) => part.type === "hour")?.value);
    const minute = Number(parts.find((part) => part.type === "minute")?.value);
    const weekday = parts.find((part) => part.type === "weekday")?.value;
    const day = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[weekday];
    const closedDays = Array.isArray(hours.closedDays) ? hours.closedDays.map(Number) : [];
    const [startHour, startMinute] = String(hours.start || "10:00").split(":").map(Number);
    const [endHour, endMinute] = String(hours.end || "20:00").split(":").map(Number);
    const now = hour * 60 + minute;
    return !closedDays.includes(day) && now >= startHour * 60 + startMinute && now < endHour * 60 + endMinute;
  } catch {
    // اگر مرورگر timezone نامعتبر داشت، زمانِ سیستمِ کاربر را جایگزین می‌کنیم.
    const now = new Date();
    const day = now.getDay();
    const mins = now.getHours() * 60 + now.getMinutes();
    const [sh, sm] = String(hours.start || "10:00").split(":").map(Number);
    const [eh, em] = String(hours.end || "20:00").split(":").map(Number);
    return !(hours.closedDays || []).map(Number).includes(day) && mins >= sh * 60 + sm && mins < eh * 60 + em;
  }
}

const DAY_ORDER = [6, 0, 1, 2, 3, 4, 5];
const DAY_LABELS = {
  0: "یکشنبه",
  1: "دوشنبه",
  2: "سه‌شنبه",
  3: "چهارشنبه",
  4: "پنجشنبه",
  5: "جمعه",
  6: "شنبه",
};

function getDaysLabel(hours = {}) {
  if (hours.daysLabel) return String(hours.daysLabel);
  const closed = new Set(Array.isArray(hours.closedDays) ? hours.closedDays.map(Number) : [5]);
  const open = DAY_ORDER.filter((day) => !closed.has(day));
  if (open.length === DAY_ORDER.length) return "هر روز";
  if (!open.length) return "روزِ پاسخگویی ثبت نشده";

  const groups = [];
  let current = [];
  DAY_ORDER.forEach((day) => {
    if (!closed.has(day)) current.push(DAY_LABELS[day]);
    else if (current.length) {
      groups.push(current);
      current = [];
    }
  });
  if (current.length) groups.push(current);
  return groups
    .map((group) => group.length > 2 ? `${group[0]} تا ${group[group.length - 1]}` : group.join(" و "))
    .join("، ");
}

function getHoursLabel(contact) {
  const hours = contact?.hours || {};
  if (contact?.hoursLabel) return String(contact.hoursLabel);
  return `${hours.start || "10:00"} تا ${hours.end || "20:00"}`;
}

function iconMark(key, contact, nodeKey) {
  const icons = contact?.icons || {};
  const src = icons[key] || "";
  return h(
    "span",
    { key: nodeKey, className: "jluxe-ai-contact-icon", style: { "--jluxe-ai-brand": BRAND_COLORS[key] || "#53766d" } },
    src
      ? h("img", {
          key: "icon-image",
          src,
          alt: "",
          loading: "lazy",
          onLoad: (event) => {
            const fallback = event.currentTarget.nextElementSibling;
            if (fallback) fallback.hidden = true;
          },
          onError: (event) => {
            event.currentTarget.hidden = true;
          },
        })
      : null,
    h("span", { key: "icon-fallback", className: "jluxe-ai-icon-fallback", "aria-hidden": true }, FALLBACK_MARKS[key] || "•"),
  );
}

function linkNode(label, rawHref, key, contact, assistant, index, onImage) {
  const href = safeHref(rawHref, contact, assistant);
  if (!href) return label;
  const matched = getContactMatch(href, contact);
  if (matched) {
    const isPhone = href.startsWith("tel:") || matched.key === "phone";
    const iconKey = matched.key || "phone";
    return h(
      "a",
      {
        key,
        href,
        className: "jluxe-ai-icon-link",
        "aria-label": matched.label || label,
        ...(isPhone ? {} : { target: "_blank", rel: "noopener noreferrer" }),
        children: [iconMark(iconKey, contact, "icon"), h("span", { key: "label", className: "jluxe-ai-icon-label", children: matched.label || label })],
      },
    );
  }
  const product = (Array.isArray(assistant?.products) ? assistant.products : []).find(
    (item) => normalizeHref(item.url) === normalizeHref(href),
  );
  if (product) {
    return h(
      "a",
      {
        key,
        href,
        className: "jluxe-ai-buy-btn",
        children: [h(CartIcon, { key: "cart", className: "size-4", "aria-hidden": true }), h("span", { key: "label", children: label || "مشاهده و خرید" })],
      },
    );
  }
  const external = new URL(href).origin !== window.location.origin;
  return h(
    "a",
    {
      key,
      href,
      className: "jluxe-ai-link-btn",
      ...(external ? { target: "_blank", rel: "noopener noreferrer" } : {}),
      children: [label, h("span", { key: "arrow", className: "jluxe-ai-link-arrow", "aria-hidden": true, children: "‹" })],
    },
  );
}

function renderInline(text, options, prefix = "line") {
  const { contact, assistant, onImage } = options;
  const pattern = /!\[([^\]]*)\]\(([^)\s]+)\)|\[([^\]]+)\]\(([^)\s]+)\)|\*\*([^*]+)\*\*/g;
  const nodes = [];
  let last = 0;
  let match;
  let index = 0;
  while ((match = pattern.exec(text)) !== null) {
    if (match.index > last) nodes.push(text.slice(last, match.index));
    if (match[2] !== undefined) {
      const raw = match[2];
      const href = safeHref(raw, contact, assistant);
      let sameOrigin = false;
      try {
        sameOrigin = !!href && new URL(href, window.location.href).origin === window.location.origin;
      } catch {}
      if (href && sameOrigin && /\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(href)) {
        const imageUrl = href;
        const imageAlt = match[1] || "تصویر محصول";
        nodes.push(
          h(
            "button",
            { key: `${prefix}-img-${index++}`, type: "button", className: "jluxe-ai-product-image-link", onClick: () => onImage(imageUrl, imageAlt), "aria-label": `بزرگ‌نمایی ${imageAlt}` },
            h("img", { src: imageUrl, alt: imageAlt === "تصویر محصول" ? "" : imageAlt, className: "jluxe-ai-product-image", loading: "lazy", onError: (event) => { if (event.currentTarget.closest("button")) event.currentTarget.closest("button").hidden = true; } }),
          ),
        );
      } else {
        nodes.push(match[1] || "");
      }
    } else if (match[4] !== undefined) {
      nodes.push(linkNode(match[3], match[4], `${prefix}-link-${index++}`, contact, assistant, index, onImage));
    } else if (match[5] !== undefined) {
      nodes.push(h("strong", { key: `${prefix}-strong-${index++}`, children: match[5] }));
    }
    last = pattern.lastIndex;
  }
  if (last < text.length) nodes.push(text.slice(last));
  return nodes.length ? nodes : text;
}

function parseContactLine(line, contact, assistant) {
  const match = line.trim().match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
  if (!match) return null;
  const href = safeHref(match[2], contact, assistant);
  if (!href) return null;
  const channel = getContactMatch(href, contact);
  return channel ? { label: channel.label || match[1], href, key: channel.key || "phone" } : null;
}

function renderReply(text, assistant, onImage) {
  const contact = assistant?.contact || {};
  const lines = String(text || "").split(/\r\n|\r|\n/);
  const rows = [];
  const options = { contact, assistant, onImage };
  for (let i = 0; i < lines.length; ) {
    const first = parseContactLine(lines[i], contact, assistant);
    if (first) {
      const group = [first];
      let next = i + 1;
      while (next < lines.length) {
        const item = parseContactLine(lines[next], contact, assistant);
        if (!item) break;
        group.push(item);
        next += 1;
      }
      const hasPhone = group.some((item) => item.key === "phone" || item.href.startsWith("tel:"));
      if (hasPhone && contact.hours?.enabled) {
        const isOpen = getPhoneHoursState(contact);
        const notice = isOpen ? contact.openText : contact.closedText;
        const hoursLabel = getHoursLabel(contact);
        const daysLabel = getDaysLabel(contact.hours);
        rows.push(
          h("div", {
            key: `hours-${i}`,
            className: `jluxe-ai-hours-notice ${isOpen ? "is-open" : "is-closed"}`,
            role: "status",
            children: [
              h("span", { key: "icon", className: "jluxe-ai-hours-icon", "aria-hidden": true, children: isOpen ? "✓" : "◷" }),
              h("div", { key: "content", className: "jluxe-ai-hours-content", children: [
                h("div", { key: "head", className: "jluxe-ai-hours-head", children: [
                  h("strong", { key: "title", className: "jluxe-ai-hours-title", children: "ساعت پاسخگویی" }),
                  h("span", { key: "state", className: "jluxe-ai-hours-state", children: isOpen ? "اکنون پاسخگو" : "خارج از ساعت" }),
                ] }),
                notice ? h("p", { key: "notice", className: "jluxe-ai-hours-copy", children: notice }) : null,
                h("div", { key: "schedule", className: "jluxe-ai-hours-meta", children: [
                  h("span", { key: "days", children: daysLabel }),
                  h("span", { key: "separator", className: "jluxe-ai-hours-separator", "aria-hidden": true, children: "•" }),
                  h("span", { key: "time", children: hoursLabel }),
                ] }),
              ] }),
            ],
          }),
        );
      }
      rows.push(
        h(
          "div",
          { key: `contact-${i}`, className: "jluxe-ai-contact-row" },
          ...group.map((item, n) => linkNode(item.label, item.href, `contact-${i}-${n}`, contact, assistant, n, onImage)),
        ),
      );
      i = next;
      continue;
    }
    rows.push(
      h("div", { key: `line-${i}`, className: lines[i] ? "jluxe-ai-md-line" : "jluxe-ai-md-spacer" },
        lines[i] ? renderInline(lines[i], options, `line-${i}`) : h("span", { "aria-hidden": true, children: " " }),
      ),
    );
    i += 1;
  }
  return rows;
}

function pageContext() {
  const match = String(document.body?.className || "").match(/(?:^|\s)postid-(\d+)(?:\s|$)/);
  const productId = match ? Number(match[1]) : Number(document.querySelector("[data-product-id]")?.getAttribute("data-product-id") || 0);
  let url = "";
  try {
    const current = new URL(window.location.href);
    // Query strings and fragments can contain order keys, search terms, or campaign tokens.
    url = `${current.origin}${current.pathname}`;
  } catch {
    // The product ID/title remain useful if the browser URL is unavailable.
  }
  return {
    url: url.length <= 2048 ? url : "",
    title: String(document.title || "").slice(0, 120),
    ...(productId > 0 ? { productId } : {}),
  };
}

function Assistant() {
  const settings = getThemeSettings();
  const assistant = settings.aiAssistant;
  const auth = settings.auth || {};
  const [open, setOpen] = useState(false);
  const [closing, setClosing] = useState(false);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [messages, setMessages] = useState([]);
  const [contactOpen, setContactOpen] = useState(false);
  const [ticketMode, setTicketMode] = useState(false);
  const [name, setName] = useState(auth.displayName || "");
  const [email, setEmail] = useState(auth.email || "");
  const [ticketMessage, setTicketMessage] = useState("");
  const [ticketLoading, setTicketLoading] = useState(false);
  const [ticketError, setTicketError] = useState(null);
  const [ticketSent, setTicketSent] = useState(false);
  const [lightbox, setLightbox] = useState(null);
  const [viewportWidth, setViewportWidth] = useState(() => typeof window === "undefined" ? 1024 : window.innerWidth);
  const [dockClearance, setDockClearance] = useState(0);
  const mobileBreakpoint = Number(assistant?.mobileBreakpoint || 820);
  const isMobile = viewportWidth < mobileBreakpoint;
  const isProductPage = typeof document !== "undefined" && (
    document.body?.classList?.contains("single-product") ||
    !!document.querySelector("[data-jluxe-mobile-price-bar], [data-jluxe-sticky-cta], [data-jluxe-product-buybox]")
  );
  const scrollRef = useRef(null);
  const inputRef = useRef(null);
  const dialogRef = useRef(null);
  const lightboxRef = useRef(null);
  const rootRef = useRef(null);
  const closeTimerRef = useRef(null);

  function openAssistant() {
    if (closeTimerRef.current !== null) {
      window.clearTimeout(closeTimerRef.current);
      closeTimerRef.current = null;
    }
    setClosing(false);
    setOpen(true);
  }

  function closeAssistant() {
    if (!open || closing) return;
    setClosing(true);
    setContactOpen(false);
    const prefersReducedMotion = typeof window.matchMedia === "function"
      && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    closeTimerRef.current = window.setTimeout(() => {
      closeTimerRef.current = null;
      setOpen(false);
      setClosing(false);
      setTicketMode(false);
    }, prefersReducedMotion ? 0 : 180);
  }

  useDialog(open && !lightbox, dialogRef, closeAssistant);
  useDialog(!!lightbox, lightboxRef, () => setLightbox(null));

  useEffect(() => () => {
    if (closeTimerRef.current !== null) window.clearTimeout(closeTimerRef.current);
  }, []);

  useEffect(() => {
    const resize = () => setViewportWidth(window.innerWidth);
    window.addEventListener("resize", resize, { passive: true });
    return () => window.removeEventListener("resize", resize);
  }, []);

  useLayoutEffect(() => {
    if (!assistant?.enabled || !isMobile || open || typeof document === "undefined" || !document.body) {
      setDockClearance((current) => current === 0 ? current : 0);
      return undefined;
    }

    const dockSelector = "#bottom-navigation, .jluxe-mobile-nav, [data-jluxe-mobile-price-bar], [data-jluxe-sticky-cta]";
    let animationFrame = 0;
    const pendingSettledMeasurements = new Set();
    const observedDocks = new WeakSet();
    const resizeObserver = typeof window.ResizeObserver === "function"
      ? new window.ResizeObserver(schedule)
      : null;

    function measureDocks() {
      const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
      const docks = document.querySelectorAll(dockSelector);
      let topEdge = null;
      for (let index = 0; index < docks.length; index += 1) {
        const dock = docks[index];
        if (
          dock.hidden ||
          dock.classList.contains("hidden") ||
          dock.getAttribute("aria-hidden") === "true" ||
          (dock.matches("[data-jluxe-sticky-cta]") && !dock.classList.contains("is-visible"))
        ) continue;

        const rect = dock.getBoundingClientRect();
        const style = window.getComputedStyle(dock);
        const opacity = Number.parseFloat(style.opacity);
        if (
          rect.width <= 0 ||
          rect.height <= 0 ||
          rect.bottom <= 0 ||
          rect.top >= viewportHeight ||
          style.display === "none" ||
          style.visibility === "hidden" ||
          (Number.isFinite(opacity) && opacity <= 0)
        ) continue;

        topEdge = topEdge === null ? rect.top : Math.min(topEdge, rect.top);
        if (resizeObserver && !observedDocks.has(dock)) {
          resizeObserver.observe(dock);
          observedDocks.add(dock);
        }
      }

      const nextClearance = topEdge === null
        ? 0
        : Math.ceil(Math.max(0, viewportHeight - topEdge) + 12);
      setDockClearance((current) => current === nextClearance ? current : nextClearance);
    }

    function schedule() {
      if (animationFrame) return;
      if (typeof window.requestAnimationFrame === "function") {
        animationFrame = window.requestAnimationFrame(() => {
          animationFrame = 0;
          measureDocks();
        });
      } else {
        measureDocks();
      }
    }

    function scheduleAfterTransition() {
      const timeout = window.setTimeout(() => {
        pendingSettledMeasurements.delete(timeout);
        schedule();
      }, 320);
      pendingSettledMeasurements.add(timeout);
    }

    function onDockTransition(event) {
      const target = event.target;
      if (!target || target.nodeType !== 1) return;
      if (target.matches(dockSelector) || target.closest(dockSelector)) schedule();
    }

    function containsDock(node) {
      return node?.nodeType === 1 && (
        node.matches(dockSelector) ||
        !!node.querySelector(dockSelector)
      );
    }

    const mutationObserver = typeof window.MutationObserver === "function"
      ? new window.MutationObserver((mutations) => {
          const relevant = mutations.some((mutation) => {
            if (mutation.type === "attributes") {
              return mutation.target.matches?.(dockSelector) || !!mutation.target.closest?.(dockSelector);
            }
            return [...mutation.addedNodes, ...mutation.removedNodes].some(containsDock);
          });
          if (relevant) {
            schedule();
            scheduleAfterTransition();
          }
        })
      : null;

    mutationObserver?.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "style", "hidden", "aria-hidden"],
    });
    window.addEventListener("resize", schedule, { passive: true });
    window.addEventListener("orientationchange", schedule, { passive: true });
    window.addEventListener("pageshow", schedule, { passive: true });
    window.addEventListener("scroll", schedule, { passive: true });
    document.addEventListener("transitionend", onDockTransition, true);
    document.addEventListener("transitioncancel", onDockTransition, true);
    window.visualViewport?.addEventListener("resize", schedule, { passive: true });
    measureDocks();

    return () => {
      mutationObserver?.disconnect();
      resizeObserver?.disconnect();
      window.removeEventListener("resize", schedule);
      window.removeEventListener("orientationchange", schedule);
      window.removeEventListener("pageshow", schedule);
      window.removeEventListener("scroll", schedule);
      document.removeEventListener("transitionend", onDockTransition, true);
      document.removeEventListener("transitioncancel", onDockTransition, true);
      window.visualViewport?.removeEventListener("resize", schedule);
      pendingSettledMeasurements.forEach((timeout) => window.clearTimeout(timeout));
      pendingSettledMeasurements.clear();
      if (animationFrame && typeof window.cancelAnimationFrame === "function") {
        window.cancelAnimationFrame(animationFrame);
      }
    };
  }, [assistant?.enabled, isMobile, open]);

  // R127: keep the launcher fixed at its configured screen corner. Its mobile
  // bottom offset clears the actual nav/purchase dock; it never tracks the
  // product card vertically, which could leave the help chip stranded mid-screen.

  useEffect(() => {
    if (!assistant?.enabled) return undefined;
    const launch = () => {
      setTicketMode(false);
      setContactOpen(false);
      setError(null);
      openAssistant();
    };
    const click = (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const trigger = target?.closest('a[href*="#open-ai-assistant"]');
      if (!trigger) return;
      event.preventDefault();
      launch();
    };
    const hash = () => {
      if (window.location.hash.includes("#open-ai-assistant")) launch();
    };
    document.addEventListener("click", click);
    window.addEventListener("jluxe:open-ai-assistant", launch);
    window.addEventListener("hashchange", hash);
    hash();
    return () => {
      document.removeEventListener("click", click);
      window.removeEventListener("jluxe:open-ai-assistant", launch);
      window.removeEventListener("hashchange", hash);
    };
  }, [assistant?.enabled]);

  useEffect(() => {
    if (!open) return undefined;
    let active = true;
    getSession()
      .then((session) => {
        if (active) {
          setName((current) => current || session.auth?.displayName || "");
          setEmail((current) => current || session.auth?.email || "");
        }
      })
      .catch(() => {});
    return () => { active = false; };
  }, [open]);

  useEffect(() => {
    if (!open || closing || isMobile) return undefined;
    const timer = window.setTimeout(() => inputRef.current?.focus(), 60);
    return () => window.clearTimeout(timer);
  }, [open, closing, isMobile]);

  useEffect(() => {
    if (!open || !isMobile || !rootRef.current) return undefined;
    const root = rootRef.current;
    const visualViewport = window.visualViewport;
    let animationFrame = 0;
    let fallbackTimer = 0;
    const requestFrame = typeof window.requestAnimationFrame === "function"
      ? window.requestAnimationFrame.bind(window)
      : (callback) => window.setTimeout(callback, 16);
    const cancelFrame = typeof window.cancelAnimationFrame === "function"
      ? window.cancelAnimationFrame.bind(window)
      : window.clearTimeout.bind(window);

    function updateViewport() {
      animationFrame = 0;
      fallbackTimer = 0;
      const layoutHeight = window.innerHeight || document.documentElement.clientHeight || 1;
      const visibleHeight = Math.max(1, Math.min(layoutHeight, visualViewport?.height || layoutHeight));
      const visibleTop = Math.max(0, visualViewport?.offsetTop || 0);
      root.style.setProperty("--aia-visual-viewport-height", `${visibleHeight}px`);
      root.style.setProperty("--aia-visual-viewport-offset-top", `${visibleTop}px`);
      root.style.setProperty("--aia-quick-replies-max-height", `${Math.min(160, Math.max(76, Math.round(visibleHeight * 0.2)))}px`);
    }

    function scheduleViewportUpdate() {
      if (animationFrame || fallbackTimer) return;
      if (typeof window.requestAnimationFrame === "function") {
        animationFrame = requestFrame(updateViewport);
      } else {
        fallbackTimer = requestFrame(updateViewport);
      }
    }

    scheduleViewportUpdate();
    visualViewport?.addEventListener("resize", scheduleViewportUpdate, { passive: true });
    visualViewport?.addEventListener("scroll", scheduleViewportUpdate, { passive: true });
    window.addEventListener("resize", scheduleViewportUpdate, { passive: true });
    window.addEventListener("orientationchange", scheduleViewportUpdate, { passive: true });
    return () => {
      visualViewport?.removeEventListener("resize", scheduleViewportUpdate);
      visualViewport?.removeEventListener("scroll", scheduleViewportUpdate);
      window.removeEventListener("resize", scheduleViewportUpdate);
      window.removeEventListener("orientationchange", scheduleViewportUpdate);
      if (animationFrame || fallbackTimer) cancelFrame(animationFrame || fallbackTimer);
      root.style.removeProperty("--aia-visual-viewport-height");
      root.style.removeProperty("--aia-visual-viewport-offset-top");
      root.style.removeProperty("--aia-quick-replies-max-height");
    };
  }, [open, isMobile]);

  useEffect(() => {
    const element = scrollRef.current;
    if (element) element.scrollTop = element.scrollHeight;
  }, [messages, loading, error, open, ticketMode]);

  if (!assistant || !assistant.enabled) return null;
  const launcherVisible = isMobile ? !assistant.hideMobileLauncher : assistant.showDesktop;
  if (!open && !launcherVisible) return null;

  const configuredSide = assistant.position === "start" ? "insetInlineStart" : "insetInlineEnd";
  const safeAreaSide = configuredSide === "insetInlineStart" ? "right" : "left";
  const bottom = isMobile ? assistant.offsetBottomMobile : assistant.offsetBottomDesktop;
  const offset = isMobile ? assistant.offsetSideMobile : assistant.offsetSideDesktop;
  const launcherBottom = Math.max(Number(bottom) || 0, dockClearance);
  const assistantAccent = assistant.primaryColor || DEFAULT_ASSISTANT_ACCENT;
  const rootStyle = {
    position: "fixed",
    zIndex: 90,
    "--aia-primary": assistantAccent,
    top: isMobile && open ? "calc(var(--aia-visual-viewport-offset-top, 0px) + max(8px, env(safe-area-inset-top)))" : undefined,
    bottom: isMobile && open ? "auto" : `${launcherBottom}px`,
    [configuredSide]: isMobile && open ? `max(8px, env(safe-area-inset-${safeAreaSide}))` : `${offset}px`,
  };
  const handoffEnabled = !!(assistant.handoffWhatsapp || assistant.handoffTelegram || assistant.handoffFormUrl || assistant.enableTicketForm);
  const productLauncher = isMobile && isProductPage;
  const hasStreamingReply = messages.some((message) => message.role === "assistant" && message.streaming);

  async function submitTicket() {
    const cleanName = name.trim();
    const cleanEmail = email.trim();
    const body = ticketMessage.trim();
    if (!cleanName || !cleanEmail || !body || ticketLoading) return;
    setTicketLoading(true);
    setTicketError(null);
    try {
      await restRequest("assistant/ticket", {
        name: cleanName,
        contact: cleanEmail,
        message: body,
        page_url: window.location.href,
      });
      setTicketSent(true);
      setTicketMessage("");
    } catch (requestError) {
      setTicketError(requestError.message || "ارتباط با سرور برقرار نشد.");
    } finally {
      setTicketLoading(false);
    }
  }

  async function sendMessage(value) {
    const prompt = String(value ?? input).trim().slice(0, 1000);
    if (!prompt || loading) return;
    const next = [...messages, { role: "user", text: prompt }];
    const requestBody = {
      messages: next.slice(-12).map((message) => ({ role: message.role, content: message.text.slice(0, 1000) })),
      page: pageContext(),
    };
    let streamedText = "";
    let pendingDelta = "";
    let flushTimer = null;
    const clearFlushTimer = () => {
      if (flushTimer !== null) window.clearTimeout(flushTimer);
      flushTimer = null;
    };
    const flushDelta = () => {
      const chunk = pendingDelta;
      pendingDelta = "";
      flushTimer = null;
      if (!chunk) return;
      setMessages((current) => {
        const last = current[current.length - 1];
        if (last?.role === "assistant" && last.streaming) {
          return [...current.slice(0, -1), { ...last, text: last.text + chunk }];
        }
        return [...current, { role: "assistant", text: chunk, products: [], streaming: true }];
      });
    };
    const handleStreamEvent = (streamEvent) => {
      if (streamEvent.type === "delta" && typeof streamEvent.text === "string") {
        streamedText += streamEvent.text;
        pendingDelta += streamEvent.text;
        if (flushTimer === null) flushTimer = window.setTimeout(flushDelta, 40);
      } else if (streamEvent.type === "replace" && typeof streamEvent.text === "string") {
        streamedText = streamEvent.text;
        pendingDelta = "";
        clearFlushTimer();
        setMessages((current) => {
          const last = current[current.length - 1];
          if (last?.role === "assistant" && last.streaming) {
            return [...current.slice(0, -1), { ...last, text: streamedText }];
          }
          return [...current, { role: "assistant", text: streamedText, products: [], streaming: true }];
        });
      }
    };

    setMessages(next);
    setInput("");
    setLoading(true);
    setError(null);
    try {
      let result;
      try {
        result = await restStreamRequest("assistant/stream", requestBody, handleStreamEvent);
      } catch (streamError) {
        const canFallback = streamedText === "" && (
          streamError.code === "rest_no_route" ||
          streamError.status === 404 ||
          (streamError.code === "jluxe_ai_stream_unavailable" && streamError.status === 501)
        );
        if (!canFallback) throw streamError;
        result = await restRequest("assistant", requestBody);
      }
      clearFlushTimer();
      pendingDelta = "";
      const finalText = typeof result.reply === "string" ? result.reply : streamedText;
      setMessages((current) => {
        const last = current[current.length - 1];
        if (last?.role === "assistant" && last.streaming) {
          return [...current.slice(0, -1), {
            ...last,
            text: finalText || last.text,
            products: Array.isArray(result.products) ? result.products : [],
            streaming: false,
          }];
        }
        return [...current, {
          role: "assistant",
          text: finalText,
          products: Array.isArray(result.products) ? result.products : [],
        }];
      });
    } catch (requestError) {
      clearFlushTimer();
      const remaining = pendingDelta;
      pendingDelta = "";
      setMessages((current) => {
        const last = current[current.length - 1];
        if (last?.role === "assistant" && last.streaming) {
          return [...current.slice(0, -1), { ...last, text: last.text + remaining, streaming: false }];
        }
        if (remaining) return [...current, { role: "assistant", text: remaining, products: [], streaming: false }];
        return current;
      });
      setError(requestError.message || "ارتباط با سرور برقرار نشد.");
    } finally {
      setLoading(false);
    }
  }

  const panel = open
    ? h(
        "section",
        {
          ref: dialogRef,
          className: `jluxe-ai-window flex flex-col overflow-hidden border border-border bg-surface shadow-xl ${isMobile ? "is-mobile" : "is-desktop"}${closing ? " is-closing" : ""}`,
          style: {
            width: isMobile ? "min(520px, calc(100vw - 16px))" : `min(${assistant.windowWidth || 380}px, calc(100vw - 24px))`,
            height: isMobile
              ? "min(820px, calc(var(--aia-visual-viewport-height, 100dvh) - max(8px, env(safe-area-inset-top)) - max(8px, env(safe-area-inset-bottom))))"
              : "min(680px, calc(100dvh - 36px))",
            borderRadius: isMobile ? "24px" : `${assistant.borderRadius || 16}px`,
            transformOrigin: isMobile ? "bottom center" : assistant.position === "start" ? "bottom right" : "bottom left",
            color: assistant.textColor || undefined,
            "--aia-primary": assistantAccent,
          },
          key: "panel",
          role: "dialog",
          "aria-modal": true,
          "aria-label": `گفتگو با ${assistant.name}`,
          children: [
            h("header", {
              key: "header",
              className: "jluxe-ai-header flex items-center gap-3 p-3 text-white",
              children: [
                h("span", { key: "avatar-wrap", className: "jluxe-ai-avatar", children: assistant.avatarUrl
                  ? h("img", { key: "avatar", src: assistant.avatarUrl, alt: "", className: "size-full rounded-full object-cover", "data-jluxe-no-skeleton": true })
                  : h(MessageIcon, { key: "avatar", className: "size-6", "aria-hidden": true }) }),
                h("div", { key: "identity", className: "jluxe-ai-identity", children: [
                  h("strong", { key: "title", className: "jluxe-ai-title", children: assistant.name }),
                  h("span", { key: "subtitle", className: "jluxe-ai-subtitle", children: [
                    h("span", { key: "dot", className: "jluxe-ai-live-dot", "aria-hidden": true }),
                    "دستیار هوشمند فروشگاه",
                  ] }),
                ] }),
                handoffEnabled
                  ? h("div", { key: "handoff", className: "relative", children: [
                      h("button", { key: "handoff-button", type: "button", onClick: () => setContactOpen((current) => !current), "aria-label": "ارتباط با پشتیبانی انسانی", className: "jluxe-ai-header-action flex size-9 items-center justify-center rounded-full", children: h(HeadsetIcon, { className: "size-4", "aria-hidden": true }) }),
                      contactOpen
                        ? h("div", { key: "handoff-menu", className: "jluxe-ai-handoff-menu absolute end-0 top-11 z-10 w-52 rounded-xl border border-border bg-surface p-2 text-foreground shadow-lg", children: [
                            assistant.handoffWhatsapp ? h("a", { key: "wa", href: assistant.handoffWhatsapp, target: "_blank", rel: "noopener noreferrer", className: "jluxe-ai-handoff-item block rounded-lg px-2 py-2 text-small", children: "واتساپ" }) : null,
                            assistant.handoffTelegram ? h("a", { key: "tg", href: assistant.handoffTelegram, target: "_blank", rel: "noopener noreferrer", className: "jluxe-ai-handoff-item block rounded-lg px-2 py-2 text-small", children: "تلگرام" }) : null,
                            !assistant.enableTicketForm && assistant.handoffFormUrl ? h("a", { key: "form", href: assistant.handoffFormUrl, target: "_blank", rel: "noopener noreferrer", className: "jluxe-ai-handoff-item block rounded-lg px-2 py-2 text-small", children: "فرم تماسِ وب‌سایت" }) : null,
                            assistant.enableTicketForm ? h("button", { key: "ticket", type: "button", onClick: () => { setTicketMode(true); setContactOpen(false); setTicketSent(false); setTicketError(null); }, className: "jluxe-ai-handoff-item block w-full rounded-lg px-2 py-2 text-start text-small", children: "فرم تماس با پشتیبانی" }) : null,
                          ] })
                        : null,
                    ] })
                  : null,
                h("button", { key: "close", type: "button", onClick: closeAssistant, "aria-label": "بستن گفتگو", className: "jluxe-ai-header-action flex size-9 items-center justify-center rounded-full", children: h(CloseIcon, { className: "size-4", "aria-hidden": true }) }),
              ],
            }),
            ticketMode
              ? h("div", { key: "ticket", className: "flex flex-1 flex-col gap-3 overflow-y-auto p-3", children: [
                  h("button", { key: "back", type: "button", onClick: () => setTicketMode(false), className: "jluxe-ai-back-button w-fit text-caption text-text-muted", children: "← بازگشت به گفتگو" }),
                  ticketSent
                    ? h("div", { key: "ticket-sent", className: "flex flex-1 flex-col items-center justify-center gap-2 text-center", children: [
                        h("span", { key: "ticket-success-icon", className: "grid size-12 place-items-center rounded-full bg-success/10 text-success", children: h(CheckIcon, { className: "size-6", "aria-hidden": true }) }),
                        h("p", { key: "ticket-success-title", className: "text-small font-bold text-foreground", children: "پیامت ثبت شد." }),
                        h("p", { key: "ticket-success-copy", className: "text-caption text-text-muted", children: "همکارانِ ما به‌زودی باهات تماس می‌گیرن." }),
                      ] })
                    : h("form", { key: "ticket-form", className: "jluxe-ai-ticket-form flex flex-1 flex-col gap-2.5", onSubmit: (event) => { event.preventDefault(); submitTicket(); }, children: [
                        h("p", { key: "ticket-copy", className: "text-caption text-text-secondary", children: "پیامت رو برایِ پشتیبانیِ انسانی ثبت کن — به‌زودی باهات تماس می‌گیریم." }),
                        h("input", { key: "ticket-name", type: "text", value: name, onChange: (event) => setName(event.target.value), placeholder: "نام شما", required: true, maxLength: 80, disabled: ticketLoading, className: "rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        h("input", { key: "ticket-contact", type: "text", value: email, onChange: (event) => setEmail(event.target.value), placeholder: "ایمیل یا شماره تماس", required: true, maxLength: 254, disabled: ticketLoading, className: "rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        h("textarea", { key: "ticket-message", value: ticketMessage, onChange: (event) => setTicketMessage(event.target.value), placeholder: "پیامت رو بنویس…", required: true, maxLength: 2000, rows: 4, disabled: ticketLoading, className: "flex-1 resize-none rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        ticketError ? h("p", { key: "ticket-error", role: "alert", className: "jluxe-ai-error text-caption", style: { color: assistant.negativeColor || undefined }, children: ticketError }) : null,
                        h("button", { key: "ticket-submit", type: "submit", disabled: ticketLoading || !name.trim() || !email.trim() || !ticketMessage.trim(), className: "rounded-lg bg-primary py-2.5 text-small font-medium text-primary-foreground disabled:opacity-40", style: { background: assistantAccent }, children: ticketLoading ? "در حال ارسال…" : "ارسال پیام" }),
                      ] }),
                ] })
              : h(React.Fragment, { key: "chat", children: [
                  h("div", { key: "messages", ref: scrollRef, className: "jluxe-ai-messages flex-1 overflow-y-auto p-3", role: "log", "aria-live": "polite", "aria-relevant": "additions text", children: [
                    messages.length === 0 ? h("div", { key: "welcome", className: "jluxe-ai-welcome", style: { background: assistant.bgBotColor || undefined }, children: [
                      h("span", { key: "welcome-icon", className: "jluxe-ai-welcome-icon", "aria-hidden": true, children: h(MessageIcon, { className: "size-5" }) }),
                      h("div", { key: "welcome-copy", className: "jluxe-ai-welcome-copy", children: [
                        h("span", { key: "welcome-label", className: "jluxe-ai-welcome-label", children: "خوش اومدی!" }),
                        h("p", { key: "welcome-message", className: "jluxe-ai-welcome-message", children: assistant.welcomeMessage }),
                      ] }),
                    ] }) : null,
                    ...messages.map((message, index) => h("div", {
                      key: `${message.role}-${index}`,
                      className: ["jluxe-ai-bubble max-w-[92%] rounded-xl p-2.5 text-small leading-6", message.role === "user" ? "jluxe-ai-bubble--user text-primary-foreground" : "jluxe-ai-bubble--assistant text-foreground"].join(" "),
                      style: { background: message.role === "user" ? assistant.bgUserColor || assistantAccent : assistant.bgBotColor || "hsl(var(--muted))" },
                      children: message.role === "assistant" ? renderReply(message.text, { ...assistant, products: message.products || [] }, (src, alt) => setLightbox({ src, alt })) : message.text,
                    })),
                    loading && !hasStreamingReply ? h("div", { key: "typing", className: "jluxe-ai-typing", role: "status", children: [
                      h("span", { key: "typing-avatar", className: "jluxe-ai-typing-avatar", "aria-hidden": true, children: h(MessageIcon, { className: "size-4" }) }),
                      h("span", { key: "dots", className: "jluxe-ai-typing-dots", "aria-hidden": true, children: "•••" }),
                      h("span", { key: "typing-label", children: "در حال نوشتن پاسخ…" }),
                    ] }) : null,
                    error ? h("div", { key: "error", className: "jluxe-ai-error text-caption", role: "alert", style: { color: assistant.negativeColor || undefined }, children: error }) : null,
                  ] }),
                  messages.length === 0 && Array.isArray(assistant.quickReplies) && assistant.quickReplies.length
                    ? h("div", {
                        key: "quick-replies",
                        className: "jluxe-ai-quick-replies",
                        role: "group",
                        "aria-label": "پیشنهادهای شروع گفتگو",
                        children: assistant.quickReplies.map((reply, index) => h("button", {
                          key: `${reply}-${index}`,
                          type: "button",
                          onClick: () => sendMessage(reply),
                          className: "jluxe-ai-quick-reply",
                          children: [
                            h(SparklesIcon, { key: "icon", className: "jluxe-ai-quick-reply-icon", "aria-hidden": true }),
                            h("span", { key: "text", className: "jluxe-ai-quick-reply-text", children: reply }),
                          ],
                        })),
                      })
                    : null,
                  h("form", { key: "chat-form", className: "jluxe-ai-chat-form", onSubmit: (event) => { event.preventDefault(); sendMessage(); }, children: [
                    h("input", { key: "chat-input", ref: inputRef, type: "text", value: input, onChange: (event) => setInput(event.target.value), placeholder: "پیامت رو بنویس…", "aria-label": "پیام شما", autoComplete: "off", enterKeyHint: "send", maxLength: 1000, className: "jluxe-ai-chat-input" }),
                    h("button", { key: "send", type: "submit", disabled: loading || !input.trim(), "aria-label": "ارسال", className: "jluxe-ai-send", children: h(SendIcon, { className: "size-4", "aria-hidden": true }) }),
                  ] }),
                ] }),
          ],
        },
      )
    : h("button", {
        key: "launcher",
        type: "button",
        onClick: openAssistant,
        "aria-label": productLauncher ? `راهنمای خرید با ${assistant.name}` : `گفتگو با ${assistant.name}`,
        className: `jluxe-ai-launcher flex size-14 items-center justify-center rounded-full text-white shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2${productLauncher ? " jluxe-ai-launcher--product" : ""}`,
        style: { background: assistantAccent },
        children: [
          h("span", {
            key: "mark",
            className: "jluxe-ai-launcher-mark",
            children: assistant.buttonUrl
              ? h("img", { src: assistant.buttonUrl, alt: "", className: "jluxe-ai-launcher-image size-8 rounded-full object-cover", "data-jluxe-no-skeleton": true })
              : h(MessageIcon, { className: "size-6", "aria-hidden": true }),
          }),
          productLauncher ? h("span", { key: "label", className: "jluxe-ai-launcher-label", children: "راهنمای خرید" }) : null,
        ],
      });

  const imageModal = lightbox
    ? h("div", { ref: lightboxRef, key: "lightbox", className: "jluxe-ai-lightbox", role: "dialog", "aria-modal": true, "aria-label": lightbox.alt || "نمایش تصویر محصول", onClick: (event) => { if (event.target === event.currentTarget) setLightbox(null); }, children: [
        h("button", { key: "lightbox-close", type: "button", className: "jluxe-ai-lightbox-close", "aria-label": "بستن تصویر", onClick: () => setLightbox(null), children: h(CloseIcon, { className: "size-6", "aria-hidden": true }) }),
        h("img", { key: "lightbox-image", src: lightbox.src, alt: lightbox.alt || "تصویر محصول", className: "jluxe-ai-lightbox-image" }),
      ] })
    : null;

  const mobileBackdrop = open && isMobile
    ? h("button", {
        key: "backdrop",
        type: "button",
        tabIndex: -1,
        className: `jluxe-ai-backdrop${closing ? " is-closing" : ""}`,
        "aria-label": "بستن گفتگوی دستیار",
        onClick: closeAssistant,
      })
    : null;

  return h("div", {
    ref: rootRef,
    className: `jluxe-ai-root ${isMobile ? "is-mobile" : "is-desktop"}${isProductPage ? " is-product" : ""} ${open ? "is-open" : "is-closed"}${closing ? " is-closing" : ""}`,
    style: rootStyle,
    children: [mobileBackdrop, panel, imageModal],
  });
}

export default Assistant;
