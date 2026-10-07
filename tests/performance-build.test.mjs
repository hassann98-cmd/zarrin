import assert from "node:assert/strict";
import fs from "node:fs";
import { gzipSync } from "node:zlib";
import test from "node:test";

const root = new URL("../assets/compiled/", import.meta.url);
const manifest = JSON.parse(fs.readFileSync(new URL("manifest.json", root), "utf8"));
const roots = ["src/main.js", ...["Header", "MiniCart", "CategoryDrawer", "MegaMenu", "MobileNav", "AiAssistant"].map(name => `src/islands/${name}.js`)];
const collect = (keys) => {
  const seen = new Set();
  function visit(key) {
    if (seen.has(key)) return;
    assert.ok(manifest[key], `Manifest entry ${key}`);
    seen.add(key);
    (manifest[key].imports || []).forEach(visit);
  }
  keys.forEach(visit);
  return seen;
};

test("R166 homepage shared-import budget avoids the 36-file request fan-out", () => {
  const files = collect(roots);
  assert.ok(files.size <= 14, `Expected at most 14 JS files, got ${files.size}`);
  const gzipBytes = [...files].reduce((total, key) => total + gzipSync(fs.readFileSync(new URL(manifest[key].file, root))).length, 0);
  assert.ok(gzipBytes < 110 * 1024, `Shared grouping must not balloon into a site-wide bundle: ${gzipBytes}`);
  for (const page of ["AuthPage.jsx", "CartCheckout.js", "ProductDetails.js", "ShopArchive.js", "Footer.js"]) {
    assert.ok(!files.has(`src/islands/${page}`), `${page} remains separately loadable`);
  }
});

test("R166 entry static dependencies do not eagerly include AI, Lenis or page islands", () => {
  const files = collect(["src/main.js"]);
  assert.ok([...files].some(key => /react-runtime/.test(key)));
  assert.ok(files.size <= 4);
  assert.ok(![...files].some(key => /ui-shared|src\/islands\/|lenis/.test(key)));
  for (const name of ["AiAssistant.js", "AuthPage.jsx", "Footer.js", "ProductDetails.js"]) {
    assert.ok(manifest["src/main.js"].dynamicImports.includes(`src/islands/${name}`));
  }
});
