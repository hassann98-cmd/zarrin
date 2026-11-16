/**
 * R86 — یک PHP CLI قابلِ حمل برای همین مخزن (بدونِ نیاز به نصبِ PHP روی سیستم).
 *
 * چرا: تست‌های PHP و lint این پروژه به یک باینریِ PHP 8 نیاز دارند
 * (`PHP_BINARY` را در README ببینید). روی سیستم‌هایی که PHP نصب نیست (یا
 * محیط‌های آزمایشیِ موقت)، این فایل همان کار را با WebAssembly انجام می‌دهد:
 *
 *   npm run php:cli -- tests/php/lint.php
 *   PHP_BINARY="node scripts/php-wasm-cli.mjs" npm run test:php
 *
 * عمداً از SAPIِ CLI استفاده می‌کند تا `PHP_SAPI === 'cli'` مثلِ یک PHP واقعی
 * باشد (تست‌ها روی همین شرط گارد دارند). این فایل در خودِ بستهٔ منتشرشده هم
 * بی‌ضرر است و هیچ وابستگیِ runtime برای پوسته نمی‌سازد.
 */
import { PHP } from "@php-wasm/universal";
import { loadNodeRuntime, useHostFilesystem } from "@php-wasm/node";
import process from "node:process";
import path from "node:path";

const args = process.argv.slice(2).map((arg) =>
  /^[-\/]/.test(arg) || arg === "php" ? arg : path.resolve(process.cwd(), arg),
);

const php = new PHP(await loadNodeRuntime("8.3", { emscriptenOptions: { processId: 1 } }));
await useHostFilesystem(php);
const response = await php.cli(["php", ...args]);
const [exitCode, stdout, stderr] = await Promise.all([
  response.exitCode,
  response.stdoutText,
  response.stderrText,
]);
// CLI output can exceed the pipe buffer; never exit before the completion marker drains.
await Promise.all([
  stdout ? new Promise((resolve, reject) => process.stdout.write(stdout, error => error ? reject(error) : resolve())) : undefined,
  stderr ? new Promise((resolve, reject) => process.stderr.write(stderr, error => error ? reject(error) : resolve())) : undefined,
]);
process.exit(exitCode ?? 0);
