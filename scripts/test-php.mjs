import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

/*
 * R86 — اجرای تست‌های PHP باید روی هر سیستمی قابلِ تکرار باشد (نقدِ بیرونی:
 * «سوئیت در محیطِ مستقل اجرا نمی‌شود»).
 *   ۱) اگر PHP_BINARY تنظیم شده باشد، همان استفاده می‌شود.
 *   ۲) وگرنه اگر `php` روی سیستم باشد، همان.
 *   ۳) وگرنه اگر وابستگی‌های dev نصب باشند، از PHP-WASM همین مخزن
 *      (scripts/php-wasm-cli.mjs) استفاده می‌شود — بدونِ نیاز به نصبِ PHP.
 */
function resolvePhp() {
  if (process.env.PHP_BINARY) return { binary: process.env.PHP_BINARY, prefix: [] };
  const probe = spawnSync("php", ["-v"], { encoding: "utf8" });
  if (!probe.error && probe.status === 0) return { binary: "php", prefix: [] };
  const shim = path.resolve(import.meta.dirname, "php-wasm-cli.mjs");
  if (fs.existsSync(shim)) {
    console.log("php not found on PATH — using the bundled PHP-WASM CLI (scripts/php-wasm-cli.mjs)");
    return { binary: process.execPath, prefix: [shim] };
  }
  return { binary: "php", prefix: [] };
}

const { binary, prefix } = resolvePhp();
for (const [file, marker] of [
  ["tests/php/lint.php", "PHP_LINT_OK:"],
  ["tests/php/run.php", "ALL_TESTS_PASSED:"],
]) {
  const result = spawnSync(binary, [...prefix, file], {
    encoding: "utf8",
    timeout: 120_000,
    maxBuffer: 8 * 1024 * 1024,
  });
  const log = `${result.stdout ?? ""}${result.stderr ?? ""}`;
  process.stdout.write(log);
  if (
    result.error ||
    result.status !== 0 ||
    !log.includes(marker) ||
    /Fatal error:|Parse error:|FAIL:/.test(log)
  ) {
    console.error(result.error?.message || `PHP check failed: ${file}`);
    console.error(
      "Use a PHP 8.0+ CLI, or set PHP_BINARY to a compatible PHP-WASM CLI. Completion markers are required even when a runtime returns exit 0.",
    );
    process.exit(1);
  }
}
