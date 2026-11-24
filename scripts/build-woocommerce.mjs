import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { minify } from "rolldown/utils";

const root = process.cwd();
const sourcePath = path.join(root, "assets/js/woocommerce.js");
const outputDir = path.join(root, "assets/compiled");
const source = await readFile(sourcePath, "utf8");
const bundles = new Map([
  ["cart-checkout", []],
  ["product", []],
]);
const marker = /\/\* @jluxe-bundle:(cart-checkout|product):(start|end) \*\//g;
let match;
let cursor = 0;
let active = null;
let base = "";
while ((match = marker.exec(source))) {
  const [token, bundle, edge] = match;
  const before = source.slice(cursor, match.index);
  if (active) bundles.get(active).push(before);
  else base += before;
  if (edge === "start") {
    if (active) throw new Error(`Nested WooCommerce bundle marker: ${token}`);
    active = bundle;
  } else {
    if (active !== bundle) throw new Error(`Unmatched WooCommerce bundle marker: ${token}`);
    active = null;
  }
  cursor = marker.lastIndex;
}
if (active) throw new Error(`Unclosed WooCommerce bundle marker: ${active}`);
base += source.slice(cursor);
for (const [name, regions] of bundles) {
  if (!regions.length) throw new Error(`No source regions found for ${name}`);
}

async function compile(name, code, minRatio = 0.8) {
  const outputPath = path.join(outputDir, `woocommerce${name ? `.${name}` : ""}.min.js`);
  const result = await minify(outputPath, code, {
    compress: true,
    mangle: true,
    codegen: { legalComments: "none" },
  });
  if (result.errors.length) {
    throw new Error(`Could not minify ${name || "core"} WooCommerce bundle: ${result.errors.map((error) => error.message).join("; ")}`);
  }
  if (!result.code || result.code.length >= code.length * minRatio) {
    throw new Error(`Minified ${name || "core"} WooCommerce bundle is unexpectedly large (${result.code?.length ?? 0}/${code.length} bytes).`);
  }
  await writeFile(outputPath, `${result.code}\n`, "utf8");
  console.log(`WooCommerce ${name || "core"} bundle: ${code.length} → ${result.code.length} bytes (${Math.round((1 - result.code.length / code.length) * 100)}% smaller).`);
}

await compile("", base);
for (const [name, regions] of bundles) {
  await compile(name, regions.join("\n"), 0.95);
}
