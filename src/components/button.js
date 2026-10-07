import React from "react";
import { jsx, jsxs, Fragment } from "react/jsx-runtime";
import { Slot } from "@radix-ui/react-slot";
import { cva } from "class-variance-authority";
import { LoaderCircle } from "lucide-react";
import { c as cn } from "../lib/utils.js";
const variants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-button font-medium transition-all active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 disabled:active:scale-100",
  {
    variants: {
      variant: {
        primary:
          "bg-primary text-primary-foreground hover:bg-primary-hover active:bg-primary-active",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary-hover",
        outline:
          "border border-border bg-transparent text-foreground hover:bg-muted",
        ghost: "bg-transparent text-foreground hover:bg-muted",
        destructive: "bg-error text-error-foreground hover:bg-error/90",
      },
      size: { sm: "h-8 px-3", md: "h-10 px-4", lg: "h-12 px-6 text-body" },
    },
    defaultVariants: { variant: "primary", size: "md" },
  },
);
const Button = React.forwardRef(
  (
    {
      className,
      variant,
      size,
      asChild = false,
      loading = false,
      disabled,
      children,
      ...props
    },
    ref,
  ) =>
    jsx(asChild ? Slot : "button", {
      className: cn(variants({ variant, size, className })),
      ref,
      disabled: disabled || loading,
      "aria-busy": loading || undefined,
      ...props,
      children: asChild
        ? children
        : jsxs(Fragment, {
            children: [
              loading
                ? jsx(LoaderCircle, {
                    className: "size-4 animate-spin",
                    "aria-hidden": true,
                  })
                : null,
              children,
            ],
          }),
    }),
);
Button.displayName = "Button";
export { Button as B, LoaderCircle as L, cva as c };
