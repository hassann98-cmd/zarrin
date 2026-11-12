import React from "react";

export function CartHourglass() {
  return React.createElement("span", { className: "jluxe-cart-hourglass", role: "status", "aria-label": "در حال به‌روزرسانی سبد خرید" },
    React.createElement("svg", { viewBox: "0 0 24 24", width: 20, height: 20, fill: "none", stroke: "currentColor", strokeWidth: 1.7, "aria-hidden": true },
      React.createElement("path", { d: "M6 3h12M6 21h12M7 3v4c0 3 3 3 5 5-2 2-5 2-5 5v4m10-18v4c0 3-3 3-5 5 2 2 5 2 5 5v4M9 18h6" })));
}
