import { useCategoryImageWarmup, warmCategoryImages } from '../lib/category-image-warmup.js';
import { lazy, Suspense, useState } from "react";
import { siteUrl } from "../lib/api.js";
import { j as jsx } from "../lib/jsx.js";
import { g as getThemeSettings } from "../lib/theme-settings.js";
import { C as ChevronDown } from "../icons/chevron-down.js";

const ClickToggle = lazy(() => window.JLuxeMegaMenuChunks.clickToggle());
const CategoryPanel = lazy(() => window.JLuxeMegaMenuChunks.panel());

function MegaMenu() {
  const settings = getThemeSettings();
  const megaMenu = settings.megaMenu ?? {};
  const categories = megaMenu.categories ?? [];
  useCategoryImageWarmup(categories);
  const appearance = megaMenu.appearance ?? {};
  const clickToggleEnabled =
    appearance.openMode === "click" || appearance.openMode === "both";
  const categoryUrl = megaMenu.url || siteUrl("shop");
  const [activeCategoryId, setActiveCategoryId] = useState(
    categories[0]?.id ?? "",
  );
  const [panelRequested, setPanelRequested] = useState(false);

  const triggerContents = [
    appearance.iconMarkup
      ? jsx.jsx("span", {
          className: "jluxe-header-categories-icon",
          "aria-hidden": true,
          dangerouslySetInnerHTML: { __html: appearance.iconMarkup },
        }, "icon")
      : null,
    jsx.jsx("span", { className: "jluxe-header-categories-label", children: "دسته‌بندی‌ها" }, "label"),
  ];
  const chevronIcon = jsx.jsx(ChevronDown, {
    className: "jluxe-header-categories-chevron",
    "aria-hidden": true,
  });
  const chevron = jsx.jsx("span", {
    className: "jluxe-header-categories-chevron-wrap",
    "aria-hidden": true,
    children: chevronIcon,
  });
  const requestPanel = () => { warmCategoryImages(categories, 16); setPanelRequested(true); };

  if (categories.length === 0) {
    return jsx.jsx("a", {
      href: categoryUrl,
      className: "jluxe-header-categories-trigger",
      children: triggerContents,
    });
  }

  const closeAndResetSelection = () => {
    setActiveCategoryId(categories[0]?.id ?? "");
  };

  return jsx.jsxs("div", {
    className: "group relative jluxe-header-categories-menu__inner",
    onPointerEnter: requestPanel,
    onMouseEnter: requestPanel,
    onPointerDownCapture: requestPanel,
    onFocusCapture: requestPanel,
    onMouseLeave: closeAndResetSelection,
    children: [
      jsx.jsxs("a", {
        href: categoryUrl,
        className: "jluxe-header-categories-trigger",
        "aria-haspopup": clickToggleEnabled ? undefined : "true",
        "aria-controls": "jluxe-header-categories-panel",
        children: [
          ...triggerContents,
          clickToggleEnabled ? null : chevron,
        ],
      }),
      clickToggleEnabled
        ? jsx.jsx(
            Suspense,
            {
              fallback: null,
              children: jsx.jsx(ClickToggle, { children: chevronIcon }),
            },
            "toggle",
          )
        : null,
      panelRequested
        ? jsx.jsx(
            Suspense,
            {
              fallback: null,
              children: jsx.jsx(CategoryPanel, {
                categories,
                activeCategoryId,
                setActiveCategoryId,
              }),
            },
            "panel",
          )
        : null,
    ],
  });
}

export { MegaMenu as default };
