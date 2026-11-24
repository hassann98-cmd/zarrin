import { j as e } from "../lib/jsx.js";
import "../styles/search-discovery.css";
import { T } from "../icons/tag.js";

function SearchDiscovery({ recent = [], trending = [], onChoose, onRemove, onClear }) {
  const recentSection = recent.length
    ? e.jsxs("section", {
        className: "jluxe-search-discovery__section",
        "aria-label": "جستجوهای اخیر شما",
        children: [
          e.jsxs("div", {
            className: "jluxe-search-discovery__heading",
            children: [
              e.jsx("h3", { children: "جستجوهای اخیر شما" }),
              e.jsx("button", { type: "button", onMouseDown: (event) => event.preventDefault(), onClick: onClear, children: "پاک کردن" }),
            ],
          }),
          e.jsx("div", {
            className: "jluxe-search-discovery__list",
            children: recent.map((term) =>
              e.jsxs("span", {
                className: "jluxe-search-chip",
                children: [
                  e.jsx("button", { type: "button", onMouseDown: (event) => event.preventDefault(), onClick: () => onChoose(term), children: term }),
                  e.jsx("button", {
                    type: "button",
                    className: "jluxe-search-chip__remove",
                    "aria-label": `حذف جستجوی ${term}`,
                    onMouseDown: (event) => event.preventDefault(),
                    onClick: (event) => { event.stopPropagation(); onRemove(term); },
                    children: "×",
                  }),
                ],
              }, term),
            ),
          }),
        ],
      })
    : null;
  const trendingSection = trending.length
    ? e.jsxs("section", {
        className: "jluxe-search-discovery__section",
        "aria-label": "جستجوهای پرطرفدار",
        children: [
          e.jsxs("div", {
            className: "jluxe-search-discovery__heading",
            children: [e.jsx(T, { className: "jluxe-search-discovery__icon", "aria-hidden": true }), e.jsx("h3", { children: "جستجوهای پرطرفدار" })],
          }),
          e.jsx("div", {
            className: "jluxe-search-discovery__list",
            children: trending.map((term) =>
              e.jsx("button", {
                type: "button",
                className: "jluxe-search-chip jluxe-search-chip--trending",
                onMouseDown: (event) => event.preventDefault(),
                onClick: () => onChoose(term),
                children: term,
              }, term),
            ),
          }),
        ],
      })
    : null;
  if (!recentSection && !trendingSection) return null;
  return e.jsxs("div", {
    className: "jluxe-search-discovery",
    children: [recentSection, trendingSection],
  });
}
export default SearchDiscovery;
