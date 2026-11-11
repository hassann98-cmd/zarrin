// Development-only fixture preparation. No downloads happen in the test runtime.
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createHash } from "node:crypto";
import { execFileSync } from "node:child_process";

const repo = fileURLToPath(new URL("../../", import.meta.url));
const destination = path.resolve(
  process.env.ZARRIN_INTEGRATION_DOWNLOADS ||
    path.join(repo, ".cache/integration/archives"),
);
fs.mkdirSync(destination, { recursive: true });
const sha256 = (bytes) => createHash("sha256").update(bytes).digest("hex");
const api = (endpoint) =>
  execFileSync("gh", ["api", endpoint], {
    maxBuffer: 128 * 1024 * 1024,
    timeout: 120_000,
  });

// Official WordPress GitHub distribution, pinned to the 6.9 release commit.
const core = path.join(destination, "wordpress-6.9.zip");
if (!fs.existsSync(core)) {
  const bytes = api(
    "repos/WordPress/WordPress/zipball/ec24ee6087dad52052c7d8a11d50c24c9ba89a3b",
  );
  if (
    sha256(bytes) !==
    "811bc36f11d587d8ae330b37e783f4b2d1e876c83f35409181e28613fc4dfb2a"
  )
    throw new Error(
      "WordPress archive checksum mismatch; review upstream before changing this pin.",
    );
  fs.writeFileSync(core, bytes);
}
if (
  sha256(fs.readFileSync(core)) !==
  "811bc36f11d587d8ae330b37e783f4b2d1e876c83f35409181e28613fc4dfb2a"
)
  throw new Error("Cached WordPress 6.9 archive checksum mismatch.");

// WooCommerce 11.2.0 requires WordPress 7.0. Keep the older 6.9 fixture for
// the Woo-absent baseline, and pin a separate current core for the real plugin.
const core70 = path.join(destination, "wordpress-7.0.zip");
const core70Commit = "b16cd68ea199838d8f9daf0ff7e3f35042ba0ad0";
const core70Digest = "b641eae7ea9a78c928596919644963198cd7b6c84e818c07403ec5ea9720ff8c";
if (!fs.existsSync(core70)) {
  const bytes = api(`repos/WordPress/WordPress/zipball/${core70Commit}`);
  if (sha256(bytes) !== core70Digest)
    throw new Error("WordPress 7.0 archive checksum mismatch; review upstream before changing this pin.");
  fs.writeFileSync(core70, bytes);
}
if (sha256(fs.readFileSync(core70)) !== core70Digest)
  throw new Error("Cached WordPress 7.0 archive checksum mismatch.");

// GitHub's source archive excludes the SQLite runtime via export-ignore, and
// release-asset downloads are unavailable in some sandboxes. Reproduce upstream's
// packaging (stored entries avoid zlib-version-dependent archive hashes): the plugin plus mysql-on-sqlite/src in wp-includes/database, replacing
// its source symlink. Every source file is verified against the pinned git tree.
const commit = "f3ea1a43ba525be382c7a9c17735b6b4d4b11d49"; // v2.2.23
const lock = JSON.parse(
  fs.readFileSync(new URL("sqlite-files.json", import.meta.url), "utf8"),
);
const directory = path.join(destination, "sqlite-source-2.2.23");
for (const { source, target, sha } of lock.files) {
  const file = path.join(directory, target);
  let bytes;
  if (fs.existsSync(file)) bytes = fs.readFileSync(file);
  else {
    const result = JSON.parse(
      api(`repos/WordPress/sqlite-database-integration/git/blobs/${sha}`),
    );
    if (result.encoding !== "base64")
      throw new Error(`Unexpected encoding for ${source}`);
    bytes = Buffer.from(result.content, "base64");
  }
  const actual = createHash("sha1")
    .update(`blob ${bytes.length}\0`)
    .update(bytes)
    .digest("hex");
  if (actual !== sha)
    throw new Error(`SQLite blob checksum mismatch: ${source} at ${commit}`);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, bytes);
}
const archive = path.join(destination, "sqlite-built-2.2.23.zip");
execFileSync("python3", [
  "-c",
  `
import sys, json, zipfile
from pathlib import Path
root, output, manifest = map(Path, sys.argv[1:])
with zipfile.ZipFile(output, 'w', zipfile.ZIP_STORED) as z:
 for item in json.loads(manifest.read_text())['files']:
  info = zipfile.ZipInfo('sqlite-database-integration/' + item['target'], (1980, 1, 1, 0, 0, 0))
  info.create_system = 3
  info.compress_type = zipfile.ZIP_STORED
  info.external_attr = 0o100644 << 16
  z.writestr(info, (root / item['target']).read_bytes())
`,
  directory,
  archive,
  fileURLToPath(new URL("sqlite-files.json", import.meta.url)),
]);
console.log(
  `Prepared pinned WordPress 6.9/7.0 + SQLite 2.2.23 fixtures in ${destination}`,
);
console.log(`SQLite archive SHA-256: ${sha256(fs.readFileSync(archive))}`);
