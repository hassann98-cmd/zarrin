#!/usr/bin/env node
/**
 * R80 — ساختِ فهرستِ رسمیِ فایل‌های پوسته با هشِ SHA-256 (docs/FILES.sha256).
 *
 * چرا: روی سایتِ فعال ثابت شد نسخهٔ style.css عددِ جدید را نشان می‌دهد ولی
 * چند فایلِ قالب هنوز نسخهٔ قدیمی‌اند (آپلودِ ناقص) و همین باعثِ «خطای مهم»
 * در حلقهٔ محصولات شده بود. این فهرست مرجعِ «بستهٔ رسمی» است: هم ابزارِ
 * «تشخیص سایت» در پیشخوان و هم خودِ اپراتور با
 *   cd wp-content/themes/zarrin && sha256sum -c docs/FILES.sha256
 * می‌توانند نصب را در چند ثانیه راستی‌آزمایی کنند.
 *
 * فقط فایل‌های ردیابی‌شدهٔ مخزن (git ls-files) هش می‌شوند؛ خودِ این فهرست
 * و بسته‌های zip کنار گذاشته می‌شوند تا خروجی قطعی (deterministic) بماند.
 */
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import url from "node:url";

export const MANIFEST_PATH = "docs/FILES.sha256";
const SKIP = new Set([MANIFEST_PATH]);

export function buildManifest(root) {
  const files = execFileSync("git", ["ls-files", "-z"], { cwd: root })
    .toString()
    .split("\0")
    .filter(Boolean)
    .filter((file) => !SKIP.has(file))
    .filter((file) => !file.endsWith(".zip"))
    .sort();
  const lines = files.map((file) => {
    const hash = createHash("sha256")
      .update(fs.readFileSync(path.join(root, file)))
      .digest("hex");
    return `${hash}  ${file}`;
  });
  return `${lines.join("\n")}\n`;
}

export function readManifest(root) {
  const file = path.join(root, MANIFEST_PATH);
  if (!fs.existsSync(file)) return null;
  return fs.readFileSync(file, "utf8");
}

function main() {
  const root = path.resolve(url.fileURLToPath(new URL("..", import.meta.url)));
  const wanted = buildManifest(root);
  const current = readManifest(root);
  if (process.argv.includes("--check")) {
    if (current === wanted) {
      console.log(`Manifest is up to date (${wanted.trim().split("\n").length} files).`);
      return;
    }
    console.error(
      "docs/FILES.sha256 is stale — run `node scripts/write-manifest.mjs` and commit the result.",
    );
    process.exit(1);
  }
  if (current === wanted) {
    console.log(`Manifest unchanged (${wanted.trim().split("\n").length} files).`);
    return;
  }
  fs.writeFileSync(path.join(root, MANIFEST_PATH), wanted);
  console.log(`Manifest written: ${MANIFEST_PATH} (${wanted.trim().split("\n").length} files).`);
}

if (process.argv[1] && import.meta.url === url.pathToFileURL(process.argv[1]).href) {
  main();
}
