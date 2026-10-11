import { j as jsx } from "../lib/jsx.js";
import { X as CloseIcon } from "../icons/x.js";

/** Cross-fade a drawer's normal icon into a close mark without motion for reduced-motion users. */
export function MobileDrawerIcon({ Icon, open, className, animate = true }) {
  const transition = animate
    ? "opacity 170ms ease, transform 230ms cubic-bezier(.2,.8,.2,1)"
    : "none";
  const iconStyle = {
    gridArea: "1 / 1",
    transition,
    transformOrigin: "center",
  };

  return jsx.jsxs("span", {
    className: "shrink-0",
    style: { display: "inline-grid", placeItems: "center" },
    "aria-hidden": true,
    children: [
      jsx.jsx(Icon, {
        className,
        "aria-hidden": true,
        style: {
          ...iconStyle,
          opacity: open ? 0 : 1,
          transform: open ? "rotate(-28deg) scale(.72)" : "rotate(0) scale(1)",
        },
      }),
      jsx.jsx(CloseIcon, {
        className,
        "aria-hidden": true,
        style: {
          ...iconStyle,
          opacity: open ? 1 : 0,
          transform: open ? "rotate(0) scale(1)" : "rotate(28deg) scale(.72)",
        },
      }),
    ],
  });
}
