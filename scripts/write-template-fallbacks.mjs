#!/usr/bin/env node
/**
 * R81 — نسخهٔ پشتیبانِ قالب‌های ووکامرس.
 *
 * چرا: روی سایتِ فعال ثابت شد چند فایلِ قالبِ پوسته (از جمله کارتِ محصول)
 * هنگامِ آپلود ناقص/بریده شده‌اند و چون PHP هنگامِ include خطای نحوی می‌دهد،
 * هر حلقهٔ محصول کلِ صفحه را می‌خواباند. محافظِ قالبی (inc/template-guard.php)
 * فقط می‌تواند فایلی را جای فایلِ خراب بگذارد که خودش سالم باشد — و آن هم
 * چیزی نیست جز یک کپیِ دست‌نخورده از همان قالب.
 *
 * این اسکریپت همهٔ قالب‌های ووکامرسِ مخزن را عیناً (بایت‌به‌بایت) در
 * `inc/woo-template-fallbacks/woocommerce/...` کپی می‌کند. کپی‌ها بخشی از
 * بسته و فهرستِ SHA-256 هستند: اگر خودشان هم خراب باشند، محافظ آن‌ها را
 * رد می‌کند و به قالبِ پیش‌فرضِ خودِ ووکامرس برمی‌گردد.
 */
import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import url from "node:url";

export const FALLBACK_DIR = "inc/woo-template-fallbacks";

export function syncTemplateFallbacks(root) {
  // قالب‌هایی که بعد از بارگذاریِ پوسته include می‌شوند: قالب‌های ووکامرس و
  // قالب‌های ریشه (front-page.php، single.php، ...). فایل‌های inc/ عمداً
  // نیستند: آن‌ها پیش از اجرای محافظ و همراهِ functions.php بارگذاری می‌شوند
  // و محافظ هیچ‌وقت فرصتِ دخالت ندارد.
  const candidates = execFileSync("git", ["ls-files", "-z"], { cwd: root })
    .toString()
    .split("\0")
    .filter(Boolean)
    .filter((file) => file.endsWith(".php"))
    .filter((file) => !file.startsWith("inc/") && !file.startsWith("tests/"))
    .filter((file) => file.startsWith("woocommerce/") || !file.includes("/"))
    .sort();
  const sources = candidates;
  const targetRoot = path.join(root, FALLBACK_DIR);
  let copied = 0;
  const expected = new Set();
  for (const file of sources) {
    const dest = path.join(targetRoot, file);
    expected.add(dest);
    const source = fs.readFileSync(path.join(root, file));
    if (fs.existsSync(dest) && fs.readFileSync(dest).equals(source)) continue;
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest, source);
    copied++;
  }
  // کپیِ یتیم (قالبی که دیگر وجود ندارد) حذف می‌شود تا فهرست دقیق بماند
  let removed = 0;
  const walk = (dir) => {
    if (!fs.existsSync(dir)) return;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const abs = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(abs);
      else if (!expected.has(abs)) {
        fs.unlinkSync(abs);
        removed++;
      }
    }
  };
  walk(targetRoot);
  return { sources: sources.length, copied, removed };
}

function main() {
  const root = path.resolve(url.fileURLToPath(new URL("..", import.meta.url)));
  const { sources, copied, removed } = syncTemplateFallbacks(root);
  console.log(
    `Template fallbacks in sync: ${sources} templates (${copied} copied, ${removed} removed).`,
  );
}

if (process.argv[1] && import.meta.url === url.pathToFileURL(process.argv[1]).href) {
  main();
}
