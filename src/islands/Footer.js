import { siteLink } from "../lib/api.js";
import { j as e } from "../lib/jsx.js";
import { BrandLogo } from "./Header.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { N as navIcons } from "../components/nav-icons.js";
import { c as createIcon } from "../lib/icons.js";
import { S as ShieldCheck } from "../icons/shield-check.js";
import { S as Send } from "../icons/send.js";
import { P as Phone } from "../icons/phone.js";
import { r as ReactDOM } from "../lib/react-dom.js";
import { useState } from "react";
import "../components/button.js";
import "../lib/utils.js";
import "../lib/use-cart.js";
import "../icons/search.js";
import "../icons/tag.js";
import "../icons/shopping-cart.js";
import "../icons/user.js";
import "../icons/x.js";
import "../icons/grid-2x2.js";
import "../icons/heart.js";
import "../icons/truck.js";

/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const BadgePercent = createIcon("BadgePercent", [
  [
    "path",
    {
      d: "M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z",
      key: "3c2336",
    },
  ],
  ["path", { d: "m15 9-6 6", key: "1uzhvr" }],
  ["path", { d: "M9 9h.01", key: "1q5me6" }],
  ["path", { d: "M15 15h.01", key: "lqbp3k" }],
]);

/**
 * @license lucide-react v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */
const Instagram = createIcon("Instagram", [
  [
    "rect",
    {
      width: "20",
      height: "20",
      x: "2",
      y: "2",
      rx: "5",
      ry: "5",
      key: "2e1cvw",
    },
  ],
  [
    "path",
    { d: "M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z", key: "9exkf1" },
  ],
  ["line", { x1: "17.5", x2: "17.51", y1: "6.5", y2: "6.5", key: "r4j83e" }],
]);

const featureIcons = { ...navIcons, "badge-percent": BadgePercent };
// Merchant claims are opt-in in settings; the JS fallback must not invent them.
const defaultFeatures = [];
const defaultColumns = [
  {
    title: "راهنما",
    links: [
      { label: "راهنمای خرید", url: "/shopping-guide/", icon: "", svg: "" },
      { label: "روش‌های پرداخت", url: "/payment-guide/", icon: "", svg: "" },
      {
        label: "ارسال و پیگیری سفارش",
        url: "/shipping-and-order-tracking/",
        icon: "",
        svg: "",
      },
      {
        label: "بازگشت و مرجوعی کالا",
        url: "/returns-and-exchanges/",
        icon: "",
        svg: "",
      },
      { label: "پیگیری سفارش", url: "/track-order/", icon: "", svg: "" },
    ],
  },
  {
    title: "شرکت",
    links: [
      { label: "درباره ما", url: "/about-us/", icon: "", svg: "" },
      { label: "تماس با ما", url: "/contact-us/", icon: "", svg: "" },
      { label: "سوالات متداول", url: "/faq/", icon: "", svg: "" },
    ],
  },
];
const socialIcons = {
  instagram: Instagram,
  telegram: Send,
  whatsapp: Send,
  rubika: Send,
  bale: Send,
};
const socialLabels = {
  instagram: "اینستاگرام",
  telegram: "تلگرام",
  whatsapp: "واتس‌اپ",
  rubika: "روبیکا",
  bale: "بله",
};
const defaultBrandDescription = "";
const defaultSupportHours = "";
const defaultSupportText = "";

function findFooterSlots(doc = typeof document === "undefined" ? null : document) {
  if (!doc) return null;
  const pick = (name) =>
    doc.querySelector(`[data-jluxe-footer-slot="${name}"]`);
  const features = pick("features");
  const columns = pick("columns");
  const bottom = pick("bottom");
  return features && columns && bottom ? { features, columns, bottom } : null;
}

function footerBackgroundStyle(background) {
  if (!background || background.mode === "default") return {};
  if (background.mode === "solid") {
    return background.solid_color
      ? { backgroundColor: background.solid_color }
      : {};
  }
  const colors = (background.gradient_colors ?? []).filter(Boolean);
  return colors.length < 2
    ? {}
    : {
        backgroundImage: `linear-gradient(${background.gradient_direction || "to bottom"}, ${colors.join(", ")})`,
      };
}

function phoneHref(value) {
  const asciiDigits = String(value ?? "").replace(/[۰-۹٠-٩]/g, (digit) => {
    const code = digit.charCodeAt(0);
    return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
  });
  const dialable = asciiDigits.trim().replace(/[^\d+]/g, "");
  return /^\+?\d+$/.test(dialable) ? `tel:${dialable}` : "";
}

function findSocialLinks(settings) {
  return Object.entries(settings ?? {}).filter(
    ([, item]) =>
      item?.enabled && typeof item.url === "string" && item.url.trim().length > 0,
  );
}

function Footer() {
  /** R88 — the server owns the three portal slots and the official badge markup. */
  const [slots] = useState(() => findFooterSlots());
  const settings = getThemeSettings();
  const footer = settings.footer;
  if (footer && !footer.enabled) return null;

  const siteName = settings.siteName || "فروشگاه";
  const brandDescription =
    typeof footer?.brand_description === "string"
      ? footer.brand_description.trim()
      : defaultBrandDescription;
  const supportHours = footer?.support_hours || defaultSupportHours;
  const supportText = footer?.support_text || defaultSupportText;
  const features = (footer?.feature_cards ?? defaultFeatures).filter(
    (feature) => feature.enabled,
  );
  const mobileFeatureColumns = footer?.feature_cards_mobile_columns === 2 ? 2 : 1;
  const columns = footer?.link_columns ?? defaultColumns;
  const badges = Array.isArray(footer?.trust_badges) ? footer.trust_badges : [];
  const badgeTitle = footer?.trust_badges_title || "نمادهای اعتماد";
  const socials = findSocialLinks(settings.social);
  const textStyle = footer?.text_color ? { color: footer.text_color } : undefined;
  const linkColor = footer?.link_color || undefined;
  const hoverColor = footer?.link_hover_color || undefined;
  const featureIconColor = footer?.feature_cards_icon_color || undefined;
  const featureIconStyle = featureIconColor
    ? {
        color: featureIconColor,
        backgroundColor: `color-mix(in srgb, ${featureIconColor} 11%, transparent)`,
      }
    : undefined;
  const contact = settings.contact ?? {};
  const phoneNumbers = [contact.phone, contact.phone_secondary]
    .filter((phone) => typeof phone === "string" && phone.trim().length > 0)
    .map((phone) => phone.trim())
    .filter((phone, index, all) => all.indexOf(phone) === index);
  const phoneSupportTitle = phoneNumbers.length > 0 ? "پشتیبانی تلفنی" : "راه‌های تماس";

  const featuresNode =
    features.length > 0
      ? e.jsxs(e.Fragment, {
          children: [
            e.jsx("style", {
              children: `.jluxe-feature-grid{grid-template-columns:repeat(${mobileFeatureColumns},minmax(0,1fr))}@media (min-width:640px){.jluxe-feature-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (min-width:1024px){.jluxe-feature-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}`,
            }),
            e.jsx("div", {
              className: `jluxe-feature-grid jluxe-footer-feature-grid ${
                mobileFeatureColumns === 2
                  ? "jluxe-footer-feature-grid--mobile-two"
                  : "jluxe-footer-feature-grid--mobile-one"
              } grid gap-3 border-b border-border p-4`,
              children: features.map((feature, index) => {
                const Icon = featureIcons[feature.icon] ?? ShieldCheck;
                return e.jsxs(
                  "div",
                  {
                    className:
                      "jluxe-footer-feature-card flex items-center gap-3 rounded-xl border border-border bg-surface/70 p-3",
                    children: [
                      e.jsx("span", {
                        className:
                          "jluxe-footer-feature-icon grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary",
                        style: featureIconStyle,
                        children: feature.svg
                          ? e.jsx("span", {
                              className: "size-5 [&>svg]:size-full",
                              "aria-hidden": true,
                              dangerouslySetInnerHTML: { __html: feature.svg },
                            })
                          : e.jsx(Icon, {
                              className: "size-5",
                              "aria-hidden": true,
                            }),
                      }),
                      e.jsxs("div", {
                        className: "jluxe-footer-feature-copy min-w-0",
                        children: [
                          e.jsx("p", {
                            className:
                              "jluxe-footer-feature-title text-caption font-bold text-foreground",
                            style: textStyle,
                            children: feature.title,
                          }),
                          e.jsx("p", {
                            className:
                              "jluxe-footer-feature-subtitle text-caption text-text-secondary",
                            style: textStyle,
                            children: feature.subtitle,
                          }),
                        ],
                      }),
                    ],
                  },
                  feature.id || `${feature.title}-${index}`,
                );
              }),
            }),
          ],
        })
      : null;

  const phoneLinks =
    phoneNumbers.length > 0
      ? e.jsx("div", {
          className: "jluxe-footer-phone-links",
          children: phoneNumbers.map((number, index) => {
            const href = phoneHref(number);
            const tag = href ? "a" : "span";
            const props = {
              className: "jluxe-footer-phone-link",
              dir: "ltr",
              children: number,
            };
            if (href) {
              props.href = href;
              props["aria-label"] = `تماس تلفنی با ${number}`;
            }
            return e.jsx(tag, props, `phone-${index}`);
          }),
        })
      : e.jsx("a", {
          href: siteLink("/contact-us/"),
          className: "jluxe-footer-contact-link",
          children: "مشاهدهٔ راه‌های تماس",
        });

  const supportNode = e.jsxs("section", {
    className: "jluxe-footer-support-list",
    "aria-label": phoneSupportTitle,
    children: [
      e.jsxs("div", {
        className: "jluxe-footer-support-card",
        children: [
          e.jsx("span", {
            className: "jluxe-footer-support-icon",
            "aria-hidden": true,
            children: e.jsx(Phone, { className: "size-5" }),
          }),
          e.jsxs("div", {
            className: "min-w-0",
            children: [
              e.jsx("h3", {
                className: "jluxe-footer-support-title",
                style: textStyle,
                children: phoneSupportTitle,
              }),
              supportHours
                ? e.jsx("p", {
                    className: "jluxe-footer-support-copy",
                    style: textStyle,
                    children: supportHours,
                  })
                : null,
              phoneLinks,
            ],
          }),
        ],
      }),
    ],
  });

  const linkColumnsNode = e.jsx("div", {
    className: "jluxe-footer-link-columns grid grid-cols-2 gap-6 sm:contents",
    children: columns.map((column, columnIndex) =>
      e.jsxs(
        "nav",
        {
          className: "jluxe-footer-link-column",
          "aria-label": column.title || "لینک‌های فوتر",
          children: [
            e.jsx("h3", {
              className:
                "jluxe-footer-column-title mb-3 text-small font-bold text-foreground",
              style: textStyle,
              children: column.title,
            }),
            e.jsx("ul", {
              className: "jluxe-footer-nav-list",
              children: (column.links ?? []).map((link, linkIndex) => {
                const Icon = link.icon ? navIcons[link.icon] : undefined;
                return e.jsx(
                  "li",
                  {
                    children: e.jsxs("a", {
                      href: siteLink(link.url),
                      "data-jluxe-footer-link": true,
                      className:
                        "jluxe-footer-nav-link flex items-center gap-1.5 text-caption text-text-muted transition-colors hover:text-primary",
                      style: linkColor ? { color: linkColor } : undefined,
                      children: [
                        link.svg
                          ? e.jsx("span", {
                              className: "size-3.5 shrink-0 [&>svg]:size-full",
                              "aria-hidden": true,
                              dangerouslySetInnerHTML: { __html: link.svg },
                            })
                          : Icon
                            ? e.jsx(Icon, {
                                className: "size-3.5 shrink-0",
                                "aria-hidden": true,
                              })
                            : null,
                        link.label,
                      ],
                    }),
                  },
                  link.url || link.label || linkIndex,
                );
              }),
            }),
          ],
        },
        column.id || column.title || columnIndex,
      ),
    ),
  });

  const badgeNode =
    badges.length > 0
      ? e.jsxs("section", {
          className: "jluxe-site-badges-section sm:col-span-2 lg:col-span-1",
          "aria-labelledby": "jluxe-footer-react-badges-heading",
          children: [
            e.jsx("h3", {
              id: "jluxe-footer-react-badges-heading",
              className:
                "jluxe-footer-column-title mb-3 text-small font-bold text-foreground",
              style: textStyle,
              children: badgeTitle,
            }),
            e.jsx("div", {
              className:
                "jluxe-footer-react-badges flex flex-wrap items-center justify-center gap-3 sm:justify-start",
              children: badges.map((badge, index) =>
                e.jsx(
                  "span",
                  {
                    className:
                      "jluxe-site-badge-card inline-flex max-w-[120px] items-center justify-center rounded-xl border border-border bg-white p-1.5",
                    dangerouslySetInnerHTML: { __html: badge.html || "" },
                  },
                  index,
                ),
              ),
            }),
          ],
        })
      : null;

  const brandNode = e.jsxs("div", {
    className: "jluxe-footer-brand-column sm:col-span-2 lg:col-span-1",
    children: [
      e.jsx(BrandLogo, { className: "jluxe-footer-logo" }),
      e.jsx("p", {
        className:
          "jluxe-footer-brand-description mt-3 text-caption text-text-muted",
        style: textStyle,
        children: brandDescription,
      }),
      supportNode,
    ],
  });

  const columnsNode = [
    e.jsx(e.Fragment, { children: brandNode }, "brand"),
    e.jsx(e.Fragment, { children: linkColumnsNode }, "link-columns"),
    e.jsx(e.Fragment, { children: badgeNode }, "trust-badges"),
  ];
  const socialNode =
    socials.length > 0 || supportText
      ? e.jsxs("div", {
          className: "jluxe-footer-social-row",
          children: [
            e.jsxs("div", {
              className: "jluxe-footer-social-copy",
              children: [
                supportText
                  ? e.jsx("span", {
                      className: "jluxe-footer-social-support",
                      children: supportText,
                    })
                  : null,
                socials.length > 0
                  ? e.jsx("span", {
                      className: "jluxe-footer-social-divider",
                      "aria-hidden": true,
                    })
                  : null,
                socials.length > 0
                  ? e.jsx("span", {
                      className: "jluxe-footer-social-follow",
                      children: "ما را دنبال کنید",
                    })
                  : null,
              ],
            }),
            socials.length > 0
              ? e.jsx("div", {
                  className: "jluxe-footer-social-links",
                  children: socials.map(([network, networkSettings]) => {
                    const Icon = socialIcons[network] ?? Send;
                    return e.jsx(
                      "a",
                      {
                        href: siteLink(networkSettings.url),
                        target: "_blank",
                        rel: "noopener noreferrer",
                        "aria-label": socialLabels[network] ?? network,
                        className: "jluxe-footer-social-link",
                        children: networkSettings.svg
                          ? e.jsx("span", {
                              className: "size-4 [&>svg]:size-full",
                              "aria-hidden": true,
                              dangerouslySetInnerHTML: { __html: networkSettings.svg },
                            })
                          : e.jsx(Icon, { className: "size-4", "aria-hidden": true }),
                      },
                      network,
                    );
                  }),
                })
              : null,
          ],
        })
      : null;
  const copyrightNode = e.jsx("div", {
    className:
      "jluxe-footer-copyright border-t border-border bg-surface/50 px-5 py-4 text-center",
    children: e.jsx("p", {
      className: "text-caption text-text-muted",
      style: textStyle,
      children:
        footer?.copyright ||
        `© ${new Date().getFullYear()} ${siteName} — تمامی حقوق محفوظ است.`,
    }),
  });
  const hoverNode = hoverColor
    ? e.jsx("style", {
        children: `[data-jluxe-footer-link]:hover{color:${hoverColor}!important}`,
      })
    : null;

  /*
   * R88 — footer.php renders the shell and official trust badges server-side.
   * This island only portals into its three named slots; there is no DOM mover,
   * polling loop or MutationObserver.
   */
  if (slots) {
    return e.jsxs(e.Fragment, {
      children: [
        ReactDOM.createPortal(featuresNode, slots.features, "features"),
        ReactDOM.createPortal(
          e.jsx(e.Fragment, { children: columnsNode }),
          slots.columns,
          "columns",
        ),
        ReactDOM.createPortal(
          e.jsx(e.Fragment, {
            children: [
              e.jsx(e.Fragment, { children: socialNode }, "social"),
              e.jsx(e.Fragment, { children: copyrightNode }, "copyright"),
              e.jsx(e.Fragment, { children: hoverNode }, "hover-style"),
            ],
          }),
          slots.bottom,
          "bottom",
        ),
      ],
    });
  }

  // Compatibility path for child themes that still provide the previous footer template.
  return e.jsxs("footer", {
    className: "flow-root",
    style: footerBackgroundStyle(footer?.background),
    children: [
      e.jsx("div", {
        className:
          "mx-auto mb-24 mt-4 w-full max-w-[1320px] px-3 md:mb-6 md:px-4",
        children: e.jsxs("div", {
          className:
            "jluxe-footer-shell overflow-hidden rounded-2xl border border-border bg-muted/40",
          children: [
            featuresNode,
            e.jsxs("div", {
              className:
                "jluxe-footer-content-grid grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4",
              children: columnsNode,
            }),
            socialNode,
            copyrightNode,
          ],
        }),
      }),
      hoverNode,
    ],
  });
}

export { Footer as default, findFooterSlots };
