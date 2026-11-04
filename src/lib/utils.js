import { clsx } from "clsx";
import { extendTailwindMerge } from "tailwind-merge";
const merge = extendTailwindMerge({
  extend: {
    classGroups: {
      "font-size": [
        "text-display",
        "text-h1",
        "text-h2",
        "text-h3",
        "text-body",
        "text-small",
        "text-caption",
        "text-button",
        "text-price",
        "text-label",
      ],
    },
  },
});
export const c = (...values) => merge(clsx(values));
export { clsx as a };
