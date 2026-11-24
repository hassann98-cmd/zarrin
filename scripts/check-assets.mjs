import fs from "node:fs";
import path from "node:path";
import { parseSync } from "rolldown/utils";
const root = path.resolve("assets/compiled");
const manifest = JSON.parse(
  fs.readFileSync(path.join(root, "manifest.json"), "utf8"),
);
if (!manifest["src/main.js"]?.isEntry || !manifest["src/main.js"]?.css?.length)
  throw Error("Missing storefront entry or stylesheet");
for (const entry of [
  "src/styles/design-variants.css",
  "src/styles/variant-canva-home.css",
  "src/styles/neumorphism-refinements.css",
  "src/styles/route-product.css",
  "src/styles/route-cart-checkout.css",
  "src/styles/route-account-auth.css",
  "src/styles/catalog-filter-chips.css",
  "src/styles/route-home.css",
]) {
  if (!manifest[entry]?.isEntry || !manifest[entry]?.file)
    throw Error(`Missing standalone CSS entry: ${entry}`);
}
if (!manifest["src/islands/AuthPage.jsx"]?.css?.length)
  throw Error("Missing AuthPage island stylesheet dependency");
if (!manifest["src/islands/AiAssistant.js"]?.css?.length)
  throw Error("Missing lazy AI assistant stylesheet from its island chunk");
if (!manifest["src/islands/SearchDiscovery.js"]?.css?.length)
  throw Error("Missing lazy SearchDiscovery stylesheet from its island chunk");
for (const [name, entry] of Object.entries(manifest)) {
  for (const file of [
    entry.file,
    ...(entry.css ?? []),
    ...(entry.assets ?? []),
  ].filter(Boolean)) {
    const target = path.resolve(root, file);
    if (!target.startsWith(root + path.sep) || !fs.existsSync(target))
      throw Error(`Missing/unsafe asset ${name}: ${file}`);
  }
  for (const ref of [
    ...(entry.imports ?? []),
    ...(entry.dynamicImports ?? []),
  ]) {
    if (!manifest[ref]) throw Error(`Missing manifest dependency: ${ref}`);
  }
}
console.log(
  `Asset manifest verified (${Object.keys(manifest).length} entries).`,
);

for (const bundle of ["woocommerce.min.js", "woocommerce.product.min.js", "woocommerce.cart-checkout.min.js"]) {
  const bundlePath = path.resolve(root, bundle);
  if (!fs.existsSync(bundlePath))
    throw Error(`Missing compiled WooCommerce production script: ${bundle}`);
  const code = fs.readFileSync(bundlePath, "utf8");
  if (!code)
    throw Error(`Compiled WooCommerce script is empty: ${bundle}`);
  const parsed = parseSync(bundle, code);
  if (parsed.errors.length)
    throw Error(`Invalid compiled WooCommerce script ${bundle}: ${parsed.errors.map((error) => error.message).join("; ")}`);
}

const version = JSON.parse(fs.readFileSync("package.json", "utf8")).version;
const stylesheet = fs.readFileSync("style.css", "utf8");
if (
  !stylesheet.includes(`Version: ${version}\n`) ||
  !stylesheet.includes(`Build: JLUXE ${version} `)
)
  throw Error("Theme/package release versions disagree");
