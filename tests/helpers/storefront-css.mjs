import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const sourceFiles = [
  "src/styles/storefront.css",
  "src/styles/route-product.css",
  "src/styles/route-cart-checkout.css",
  "src/styles/route-account-auth.css",
  "src/styles/ai-assistant.css",
];

/** All authored runtime styles, for legacy source-level regression assertions. */
export function readStorefrontCss() {
  return sourceFiles
    .map((file) => fs.readFileSync(path.join(root, file), "utf8"))
    .join("\n");
}

export function readProductCss() {
  return fs.readFileSync(path.join(root, "src/styles/route-product.css"), "utf8");
}
