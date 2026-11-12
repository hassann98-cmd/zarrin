import { j as jsx } from "../lib/jsx.js";
import { G as Grid2x2 } from "../icons/grid-2x2.js";
import { C as ChevronLeft } from "../icons/chevron-left.js";

function groupsFor(category) { return Array.isArray(category.groups) && category.groups.length > 0; }

function MegaMenuPanel({ categories, activeCategoryId, setActiveCategoryId }) {
  const activeCategory =
    categories.find((category) => category.id === activeCategoryId) ??
    categories[0];
  const groups = activeCategory?.groups ?? [];

  return jsx.jsxs("div", {
    id: "jluxe-header-categories-panel",
    className:
      "absolute start-0 top-full z-40 flex w-[1180px] max-w-[calc(100vw-32px)] overflow-hidden rounded-xl border bg-surface/95 shadow-lg backdrop-blur-xl jluxe-header-categories-panel",
    role: "region",
    "aria-label": "زیرمنوی دسته‌بندی‌ها",
    children: [
      jsx.jsx("nav", {
        "aria-label": "دسته‌بندی‌های اصلی",
        className: "w-56 shrink-0 border-s border-border py-3 jluxe-header-categories-panel__nav",
        children: categories.map((category) => {
          const isActive = category.id === activeCategoryId;
          return jsx.jsxs(
            "a",
            {
              href: category.url,
              onMouseEnter: () => setActiveCategoryId(category.id),
              onFocus: () => setActiveCategoryId(category.id),
              className: `flex items-center justify-between gap-2 border-e-2 px-4 py-2.5 text-small transition-colors jluxe-header-categories-panel__category${isActive ? " is-active" : ""}`,
              "aria-current": isActive ? "true" : undefined,
              children: [
                category.iconImage ? jsx.jsx("img", {src:category.iconImage,alt:"",width:24,height:24,loading:"lazy",className:"jluxe-mega-category-image",onError:(event)=>{event.currentTarget.style.visibility="hidden";}}) : category.iconSvg
                  ? jsx.jsx("span", {
                      className: "size-4 shrink-0 [&>svg]:size-full jluxe-mega-category-icon",
                      "aria-hidden": true,
                      dangerouslySetInnerHTML: { __html: category.iconSvg },
                    })
                  : jsx.jsx(Grid2x2, {
                      className: "size-4 shrink-0",
                      "aria-hidden": true,
                    }),
                jsx.jsx("span", { className: "min-w-0 flex-1 jluxe-mega-category-label", children: category.label }),
                groupsFor(category) ? jsx.jsx(ChevronLeft, { className: "size-3.5 shrink-0 jluxe-mega-category-arrow", "aria-hidden": true }) : null,
              ],
            },
            category.id,
          );
        }),
      }),
      activeCategory
        ? jsx.jsxs("div", {
            className: "flex-1 p-6 jluxe-header-categories-panel__content",
            children: [
              jsx.jsxs("div", {
                className:
                  "flex items-center justify-between border-b border-border pb-3 jluxe-header-categories-panel__heading",
                children: [
                  jsx.jsxs("a", {
                    href: activeCategory.url,
                    className:
                      "flex items-center gap-2 text-caption text-text-secondary hover:text-foreground jluxe-header-categories-panel__link",
                    children: [
                      "مشاهده همه",
                      jsx.jsx(ChevronLeft, {
                        className: "size-3.5",
                        "aria-hidden": true,
                      }),
                    ],
                  }),
                  jsx.jsxs("div", {
                    className: "flex items-center gap-2",
                    children: [
                      jsx.jsxs("div", {
                        className: "text-end",
                        children: [
                          jsx.jsx("p", {
                            className:
                              "text-h3 text-foreground jluxe-header-categories-panel__title",
                            children: activeCategory.label,
                          }),
                          jsx.jsxs("p", {
                            className: "text-caption text-text-muted",
                            children: [
                              groups.length.toLocaleString("fa-IR"),
                              " زیردسته",
                            ],
                          }),
                        ],
                      }),
                      activeCategory.iconImage ? jsx.jsx("img", {src:activeCategory.iconImage,alt:"",width:28,height:28,className:"jluxe-mega-category-image",onError:(event)=>{event.currentTarget.style.visibility="hidden";}}) : activeCategory.iconSvg
                        ? jsx.jsx("span", {
                            className:
                              "size-6 [&>svg]:size-full text-primary jluxe-header-categories-panel__icon",
                            dangerouslySetInnerHTML: {
                              __html: activeCategory.iconSvg,
                            },
                          })
                        : jsx.jsx(Grid2x2, {
                            className:
                              "size-6 text-primary jluxe-header-categories-panel__icon",
                            "aria-hidden": true,
                          }),
                    ],
                  }),
                ],
              }),
              groups.length > 0
                ? jsx.jsx("div", {
                    className:
                      "mt-5 grid grid-cols-2 gap-6 lg:grid-cols-4 jluxe-header-categories-panel__groups",
                    children: groups.map((group) =>
                      jsx.jsxs(
                        "div",
                        {
                          className: "jluxe-header-categories-panel__group",
                          children: [
                            jsx.jsx("h3", {
                              className:
                                "border-s-2 ps-2 text-label text-foreground jluxe-header-categories-panel__group-title",
                              children: jsx.jsx("a", {
                                href: group.url,
                                className: "jluxe-header-categories-panel__link",
                                children: group.title,
                              }),
                            }),
                            group.items?.length > 0
                              ? jsx.jsx("ul", {
                                  className: "mt-3 flex flex-col gap-2",
                                  children: group.items.map((item) =>
                                    jsx.jsx(
                                      "li",
                                      {
                                        children: jsx.jsx("a", {
                                          href: item.url,
                                          className:
                                            "text-small text-text-secondary jluxe-header-categories-panel__link",
                                          children: item.label,
                                        }),
                                      },
                                      item.label,
                                    ),
                                  ),
                                })
                              : null,
                          ],
                        },
                        group.title,
                      ),
                    ),
                  })
                : jsx.jsx("p", {
                    className: "mt-5 text-small text-text-muted",
                    children: "این دسته زیردسته‌ای ندارد.",
                  }),
            ],
          })
        : null,
    ],
  });
}

export { MegaMenuPanel as default };
