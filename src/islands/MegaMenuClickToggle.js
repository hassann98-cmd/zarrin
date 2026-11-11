import { useEffect, useRef } from "react";
import { j as jsx } from "../lib/jsx.js";

function MegaMenuClickToggle({ children }) {
  const buttonRef = useRef(null);

  useEffect(() => {
    const button = buttonRef.current;
    const menu = button?.parentElement;
    const doc = button?.ownerDocument;
    if (!button || !menu || !doc) return undefined;

    const closeMenu = (event) => {
      if (button.getAttribute("aria-expanded") !== "true") return;
      if (event.type === "keydown") {
        if (event.key !== "Escape") return;
        event.preventDefault();
        button.focus();
      } else if (menu.contains(event.target)) return;
      button.setAttribute("aria-expanded", "false");
      button.setAttribute("aria-label", "بازکردن زیرمنوی دسته‌بندی‌ها");
      menu.classList.remove("is-click-open");
    };

    doc.addEventListener("pointerdown", closeMenu);
    doc.addEventListener("keydown", closeMenu);
    return () => {
      doc.removeEventListener("pointerdown", closeMenu);
      doc.removeEventListener("keydown", closeMenu);
    };
  }, []);

  return jsx.jsx("button", {
    ref: buttonRef,
    type: "button",
    className: "jluxe-header-categories-toggle",
    "aria-label": "بازکردن زیرمنوی دسته‌بندی‌ها",
    "aria-haspopup": "true",
    "aria-controls": "jluxe-header-categories-panel",
    "aria-expanded": false,
    onClick: (event) => {
      const button = event.currentTarget;
      const open = button.getAttribute("aria-expanded") !== "true";
      button.setAttribute("aria-expanded", String(open));
      button.setAttribute(
        "aria-label",
        open ? "بستن زیرمنوی دسته‌بندی‌ها" : "بازکردن زیرمنوی دسته‌بندی‌ها",
      );
      button.parentElement.classList.toggle("is-click-open", open);
    },
    children,
  });
}

export { MegaMenuClickToggle as default };
