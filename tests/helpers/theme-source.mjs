import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const modules = {
  homepage: [
    "inc/theme-settings-homepage.php",
    "inc/homepage/admin-editor.php",
    "inc/homepage/sections-products.php",
    "inc/homepage/sections-media.php",
    "inc/homepage/sections-catalog.php",
    "inc/homepage/hooks.php",
  ],
  woocommerce: [
    "inc/woocommerce.php",
    "inc/woocommerce/pricing-toman.php",
    "inc/woocommerce/product-single.php",
    "inc/woocommerce/checkout-fields.php",
    "inc/woocommerce/catalog-shop.php",
    "inc/woocommerce/admin-product-meta.php",
    "inc/woocommerce/hooks.php",
  ],
};

/** Read a stable source view for static tests after the public files became loaders. */
export function readThemePhpSource(area) {
  const files = modules[area];
  if (!files) throw new Error(`Unknown theme PHP source group: ${area}`);
  return files.map((file) => fs.readFileSync(path.join(root, file), "utf8")).join("\n");
}

export const readHomepageSource = () => readThemePhpSource("homepage");
export const readWooCommerceSource = () => readThemePhpSource("woocommerce");
