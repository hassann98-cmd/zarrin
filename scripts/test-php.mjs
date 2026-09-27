import { spawnSync } from "node:child_process";
const binary = process.env.PHP_BINARY || "php";
for (const [file, marker] of [
  ["tests/php/lint.php", "PHP_LINT_OK:"],
  ["tests/php/run.php", "ALL_TESTS_PASSED:"],
]) {
  const result = spawnSync(binary, [file], {
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
