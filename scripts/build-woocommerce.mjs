import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { minify } from "rolldown/utils";

const root = process.cwd();
const sourcePath = path.join(root, "assets/js/woocommerce.js");
const outputPath = path.join(root, "assets/compiled/woocommerce.min.js");
const source = await readFile(sourcePath, "utf8");
const result = await minify(sourcePath, source, {
  compress: true,
  mangle: true,
  codegen: { legalComments: "none" },
});

if (result.errors.length) {
  throw new Error(`Could not minify woocommerce.js: ${result.errors.map((error) => error.message).join("; ")}`);
}
if (!result.code || result.code.length >= source.length * 0.8) {
  throw new Error(`Minified WooCommerce bundle is unexpectedly large (${result.code?.length ?? 0}/${source.length} bytes).`);
}

await writeFile(outputPath, `${result.code}\n`, "utf8");
console.log(
  `WooCommerce bundle minified: ${source.length} → ${result.code.length} bytes (${Math.round((1 - result.code.length / source.length) * 100)}% smaller).`,
);
