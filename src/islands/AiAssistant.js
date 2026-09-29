import React, { useEffect, useRef, useState } from "react";
import { restRequest, getSession } from "../lib/api.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { c as createIcon } from "../lib/icons.js";
import { X as CloseIcon } from "../icons/x.js";
import { C as CheckIcon } from "../icons/check.js";
import { S as SendIcon } from "../icons/send.js";
import { P as PhoneIcon } from "../icons/phone.js";
import { S as CartIcon } from "../icons/shopping-cart.js";
import { useDialog } from "../lib/use-dialog.js";

const h = React.createElement;
const MessageIcon = createIcon("MessageCircle", [
  ["path", { d: "M7.9 20A9 9 0 1 0 4 16.1L2 22Z", key: "message" }],
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
      assistant?.handoffFormUrl,
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
        rows.push(h("div", { key: `hours-${i}`, className: `jluxe-ai-hours-notice ${isOpen ? "is-open" : "is-closed"}`, role: "status", children: notice }));
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
  return {
    url: window.location.href,
    title: document.title || "",
    ...(productId > 0 ? { productId } : {}),
  };
}

function Assistant() {
  const settings = getThemeSettings();
  const assistant = settings.aiAssistant;
  const auth = settings.auth || {};
  const [open, setOpen] = useState(false);
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
  const scrollRef = useRef(null);
  const inputRef = useRef(null);
  const dialogRef = useRef(null);
  const lightboxRef = useRef(null);

  useDialog(open && !lightbox, dialogRef, () => setOpen(false));
  useDialog(!!lightbox, lightboxRef, () => setLightbox(null));

  useEffect(() => {
    const resize = () => setViewportWidth(window.innerWidth);
    window.addEventListener("resize", resize, { passive: true });
    return () => window.removeEventListener("resize", resize);
  }, []);

  useEffect(() => {
    if (!assistant?.enabled) return undefined;
    const launch = () => {
      setTicketMode(false);
      setContactOpen(false);
      setError(null);
      setOpen(true);
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
    const timer = window.setTimeout(() => inputRef.current?.focus(), 60);
    return () => {
      active = false;
      window.clearTimeout(timer);
    };
  }, [open]);

  useEffect(() => {
    const element = scrollRef.current;
    if (element) element.scrollTop = element.scrollHeight;
  }, [messages, loading, error, open, ticketMode]);

  if (!assistant || !assistant.enabled) return null;
  const mobileBreakpoint = Number(assistant.mobileBreakpoint || 820);
  const isMobile = viewportWidth < mobileBreakpoint;
  const launcherVisible = isMobile ? !assistant.hideMobileLauncher : assistant.showDesktop;
  if (!open && !launcherVisible) return null;

  const side = assistant.position === "start" ? "insetInlineStart" : "insetInlineEnd";
  const bottom = isMobile ? assistant.offsetBottomMobile : assistant.offsetBottomDesktop;
  const offset = isMobile ? assistant.offsetSideMobile : assistant.offsetSideDesktop;
  const rootStyle = { position: "fixed", zIndex: 90, bottom: `${bottom}px`, [side]: `${offset}px` };
  const handoffEnabled = !!(assistant.handoffWhatsapp || assistant.handoffTelegram || assistant.handoffFormUrl || assistant.enableTicketForm);

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
    const prompt = String(value ?? input).trim();
    if (!prompt || loading) return;
    const next = [...messages, { role: "user", text: prompt }];
    setMessages(next);
    setInput("");
    setLoading(true);
    setError(null);
    try {
      const result = await restRequest("assistant", {
        messages: next.slice(-12).map((message) => ({ role: message.role, content: message.text })),
        page: pageContext(),
      });
      setMessages((current) => [...current, {
        role: "assistant",
        text: String(result.reply || ""),
        products: Array.isArray(result.products) ? result.products : [],
      }]);
    } catch (requestError) {
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
          className: "jluxe-ai-window flex flex-col overflow-hidden border border-border bg-surface shadow-xl",
          style: {
            width: `min(${assistant.windowWidth || 380}px, calc(100vw - 24px))`,
            height: isMobile
              ? `min(600px, calc(100dvh - ${Number(bottom) + 24}px))`
              : "min(600px, calc(100dvh - 36px))",
            borderRadius: `${assistant.borderRadius || 16}px`,
            color: assistant.textColor || undefined,
            "--aia-primary": assistant.primaryColor || "hsl(var(--primary))",
          },
          key: "panel",
          role: "dialog",
          "aria-modal": true,
          "aria-label": `گفتگو با ${assistant.name}`,
          children: [
            h("header", {
              key: "header",
              className: "flex items-center gap-2 p-3 text-white",
              style: { background: assistant.primaryColor || "hsl(var(--primary))" },
              children: [
                assistant.avatarUrl
                  ? h("img", { key: "avatar", src: assistant.avatarUrl, alt: "", className: "size-9 rounded-full object-cover", "data-jluxe-no-skeleton": true })
                  : h(MessageIcon, { key: "avatar", className: "size-6", "aria-hidden": true }),
                h("span", { key: "title", className: "flex-1 truncate text-small font-bold", children: assistant.name }),
                handoffEnabled
                  ? h("div", { key: "handoff", className: "relative", children: [
                      h("button", { key: "handoff-button", type: "button", onClick: () => setContactOpen((current) => !current), "aria-label": "ارتباط با پشتیبانی انسانی", className: "flex size-8 items-center justify-center rounded-full hover:bg-white/20", children: h(HeadsetIcon, { className: "size-4", "aria-hidden": true }) }),
                      contactOpen
                        ? h("div", { key: "handoff-menu", className: "jluxe-ai-handoff-menu absolute end-0 top-10 z-10 w-52 rounded-xl border border-border bg-surface p-2 text-foreground shadow-lg", children: [
                            assistant.handoffWhatsapp ? h("a", { key: "wa", href: assistant.handoffWhatsapp, target: "_blank", rel: "noopener noreferrer", className: "block rounded-lg px-2 py-2 text-small hover:bg-muted", children: "واتساپ" }) : null,
                            assistant.handoffTelegram ? h("a", { key: "tg", href: assistant.handoffTelegram, target: "_blank", rel: "noopener noreferrer", className: "block rounded-lg px-2 py-2 text-small hover:bg-muted", children: "تلگرام" }) : null,
                            assistant.handoffFormUrl ? h("a", { key: "form", href: assistant.handoffFormUrl, target: "_blank", rel: "noopener noreferrer", className: "block rounded-lg px-2 py-2 text-small hover:bg-muted", children: "فرم تماس" }) : null,
                            assistant.enableTicketForm ? h("button", { key: "ticket", type: "button", onClick: () => { setTicketMode(true); setContactOpen(false); setTicketSent(false); setTicketError(null); }, className: "block w-full rounded-lg px-2 py-2 text-start text-small hover:bg-muted", children: "ثبت پیام برای پشتیبان" }) : null,
                          ] })
                        : null,
                    ] })
                  : null,
                h("button", { key: "close", type: "button", onClick: () => { setOpen(false); setContactOpen(false); }, "aria-label": "بستن گفتگو", className: "flex size-8 items-center justify-center rounded-full hover:bg-white/20", children: h(CloseIcon, { className: "size-4", "aria-hidden": true }) }),
              ],
            }),
            ticketMode
              ? h("div", { key: "ticket", className: "flex flex-1 flex-col gap-3 overflow-y-auto p-3", children: [
                  h("button", { key: "back", type: "button", onClick: () => setTicketMode(false), className: "w-fit text-caption text-text-muted hover:text-foreground", children: "← بازگشت به گفتگو" }),
                  ticketSent
                    ? h("div", { key: "ticket-sent", className: "flex flex-1 flex-col items-center justify-center gap-2 text-center", children: [
                        h("span", { key: "ticket-success-icon", className: "grid size-12 place-items-center rounded-full bg-success/10 text-success", children: h(CheckIcon, { className: "size-6", "aria-hidden": true }) }),
                        h("p", { key: "ticket-success-title", className: "text-small font-bold text-foreground", children: "پیامت ثبت شد." }),
                        h("p", { key: "ticket-success-copy", className: "text-caption text-text-muted", children: "همکارانِ ما به‌زودی باهات تماس می‌گیرن." }),
                      ] })
                    : h("form", { key: "ticket-form", className: "flex flex-1 flex-col gap-2.5", onSubmit: (event) => { event.preventDefault(); submitTicket(); }, children: [
                        h("p", { key: "ticket-copy", className: "text-caption text-text-secondary", children: "پیامت رو برایِ پشتیبانیِ انسانی ثبت کن — به‌زودی باهات تماس می‌گیریم." }),
                        h("input", { key: "ticket-name", type: "text", value: name, onChange: (event) => setName(event.target.value), placeholder: "نام شما", required: true, disabled: ticketLoading, className: "rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        h("input", { key: "ticket-contact", type: "text", value: email, onChange: (event) => setEmail(event.target.value), placeholder: "ایمیل یا شماره تماس", required: true, disabled: ticketLoading, className: "rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        h("textarea", { key: "ticket-message", value: ticketMessage, onChange: (event) => setTicketMessage(event.target.value), placeholder: "پیامت رو بنویس…", required: true, rows: 4, disabled: ticketLoading, className: "flex-1 resize-none rounded-lg border border-border bg-surface px-3 py-2 text-small outline-none focus:border-primary disabled:opacity-60" }),
                        ticketError ? h("p", { key: "ticket-error", role: "alert", className: "text-caption", style: { color: assistant.negativeColor || undefined }, children: ticketError }) : null,
                        h("button", { key: "ticket-submit", type: "submit", disabled: ticketLoading || !name.trim() || !email.trim() || !ticketMessage.trim(), className: "rounded-lg bg-primary py-2.5 text-small font-medium text-primary-foreground disabled:opacity-40", children: ticketLoading ? "در حال ارسال…" : "ارسال پیام" }),
                      ] }),
                ] })
              : h(React.Fragment, { key: "chat", children: [
                  h("div", { key: "messages", ref: scrollRef, className: "jluxe-ai-messages flex-1 space-y-3 overflow-y-auto p-3", role: "log", "aria-live": "polite", "aria-relevant": "additions text", children: [
                    messages.length === 0 ? h("div", { key: "welcome", className: "rounded-xl p-3 text-small leading-6 text-text-secondary", style: { background: assistant.bgBotColor || undefined }, children: assistant.welcomeMessage }) : null,
                    ...messages.map((message, index) => h("div", {
                      key: `${message.role}-${index}`,
                      className: ["jluxe-ai-bubble max-w-[88%] rounded-xl p-2.5 text-small leading-6", message.role === "user" ? "mr-auto text-primary-foreground" : "ml-auto text-foreground"].join(" "),
                      style: { background: message.role === "user" ? assistant.bgUserColor || "hsl(var(--primary))" : assistant.bgBotColor || "hsl(var(--muted))" },
                      children: message.role === "assistant" ? renderReply(message.text, { ...assistant, products: message.products || [] }, (src, alt) => setLightbox({ src, alt })) : message.text,
                    })),
                    loading ? h("div", { key: "typing", className: "jluxe-ai-typing text-caption text-text-muted", role: "status", children: [h("span", { key: "dots", className: "jluxe-ai-typing-dots", "aria-hidden": true, children: "•••" }), " در حال تایپ…"] }) : null,
                    error ? h("div", { key: "error", className: "text-caption", role: "alert", style: { color: assistant.negativeColor || undefined }, children: error }) : null,
                  ] }),
                  messages.length === 0 && Array.isArray(assistant.quickReplies) && assistant.quickReplies.length
                    ? h("div", { key: "quick-replies", className: "flex flex-wrap gap-1.5 border-t border-border p-2", children: assistant.quickReplies.map((reply) => h("button", { key: reply, type: "button", onClick: () => sendMessage(reply), className: "rounded-full border border-border px-3 py-1 text-caption text-text-secondary hover:border-primary hover:text-primary", children: reply })) })
                    : null,
                  h("form", { key: "chat-form", className: "flex items-center gap-2 border-t border-border p-2", onSubmit: (event) => { event.preventDefault(); sendMessage(); }, children: [
                    h("input", { key: "chat-input", ref: inputRef, type: "text", value: input, onChange: (event) => setInput(event.target.value), placeholder: "پیامت رو بنویس…", "aria-label": "پیام شما", autoComplete: "off", className: "flex-1 rounded-lg border border-border bg-surface px-3 py-2.5 text-small outline-none focus:border-primary" }),
                    h("button", { key: "send", type: "submit", disabled: loading || !input.trim(), "aria-label": "ارسال", className: "flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground disabled:opacity-40", children: h(SendIcon, { className: "size-4", "aria-hidden": true }) }),
                  ] }),
                ] }),
          ],
        },
      )
    : h("button", {
        type: "button",
        onClick: () => setOpen(true),
        "aria-label": `گفتگو با ${assistant.name}`,
        className: "jluxe-ai-launcher flex size-14 items-center justify-center rounded-full text-white shadow-lg transition-transform hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2",
        style: { background: assistant.primaryColor || "hsl(var(--primary))" },
        children: assistant.buttonUrl
          ? h("img", { src: assistant.buttonUrl, alt: "", className: "size-8 rounded-full object-cover", "data-jluxe-no-skeleton": true })
          : h(MessageIcon, { key: "avatar", className: "size-6", "aria-hidden": true }),
      });

  const imageModal = lightbox
    ? h("div", { ref: lightboxRef, key: "lightbox", className: "jluxe-ai-lightbox", role: "dialog", "aria-modal": true, "aria-label": lightbox.alt || "نمایش تصویر محصول", onClick: (event) => { if (event.target === event.currentTarget) setLightbox(null); }, children: [
        h("button", { key: "lightbox-close", type: "button", className: "jluxe-ai-lightbox-close", "aria-label": "بستن تصویر", onClick: () => setLightbox(null), children: h(CloseIcon, { className: "size-6", "aria-hidden": true }) }),
        h("img", { key: "lightbox-image", src: lightbox.src, alt: lightbox.alt || "تصویر محصول", className: "jluxe-ai-lightbox-image" }),
      ] })
    : null;

  return h("div", { className: "jluxe-ai-root", style: rootStyle, children: [panel, imageModal] });
}

export default Assistant;
