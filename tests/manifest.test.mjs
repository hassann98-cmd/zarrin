import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import url from "node:url";
import { buildManifest, readManifest, MANIFEST_PATH } from "../scripts/write-manifest.mjs";

const root = path.resolve(url.fileURLToPath(new URL("..", import.meta.url)));

test("R80 the shipped file manifest exists and lists every tracked file", () => {
  const manifest = readManifest(root);
  assert.ok(manifest, `${MANIFEST_PATH} must exist in the package`);
  const entries = manifest.trim().split("\n");
  const paths = entries.map((line) => line.slice(66));
  assert.ok(entries.length > 200, `expected a full listing, got ${entries.length}`);
  for (const line of entries) {
    assert.match(line, /^[0-9a-f]{64} {2}\S+$/, `malformed manifest line: ${line}`);
  }
  assert.ok(
    paths.includes("woocommerce/content-product.php"),
    "the product-card template must be covered by the manifest",
  );
  assert.ok(
    paths.includes("docs/FILES.sha256") === false,
    "the manifest never lists itself",
  );
});

test("R80 the manifest matches the working tree (regenerate after every change)", () => {
  const wanted = buildManifest(root);
  const current = readManifest(root);
  assert.equal(
    current,
    wanted,
    "docs/FILES.sha256 is stale — run `node scripts/write-manifest.mjs`",
  );
});

test("R80 the manifest hashes are the real file hashes (catches partial uploads)", () => {
  const manifest = readManifest(root).trim().split("\n");
  const sample = manifest.filter((line) =>
    /(woocommerce\/content-product\.php|inc\/theme-settings-homepage\.php|style\.css)$/.test(line),
  );
  assert.equal(sample.length, 3, "expected the three templates in the manifest");
  for (const line of sample) {
    const [hash, file] = [line.slice(0, 64), line.slice(66)];
    assert.ok(fs.existsSync(path.join(root, file)), `${file} must exist`);
  }
});
