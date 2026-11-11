import fs from "node:fs";
import path from "node:path";
const root = path.resolve("assets/compiled");
const manifest = JSON.parse(
  fs.readFileSync(path.join(root, "manifest.json"), "utf8"),
);
if (!manifest["src/main.js"]?.isEntry || !manifest["src/main.js"]?.css?.length)
  throw Error("Missing storefront entry or stylesheet");
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

const version = JSON.parse(fs.readFileSync("package.json", "utf8")).version;
const stylesheet = fs.readFileSync("style.css", "utf8");
if (
  !stylesheet.includes(`Version: ${version}\n`) ||
  !stylesheet.includes(`Build: JLUXE ${version} `)
)
  throw Error("Theme/package release versions disagree");
