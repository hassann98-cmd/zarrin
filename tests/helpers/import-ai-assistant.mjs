import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

// Native Node tests do not understand Vite's CSS imports. Evaluate an otherwise
// identical temporary module; Vite production builds use the unmodified source.
export async function importAssistantForTest() {
  const sourcePath = path.join(root, "src/islands/AiAssistant.js");
  const temporaryPath = path.join(
    path.dirname(sourcePath),
    `AiAssistant.test-${process.pid}-${Date.now()}.js`,
  );
  const source = (await fs.readFile(sourcePath, "utf8")).replace(
    /^import ["']\.\.\/styles\/ai-assistant\.css["'];\r?\n/m,
    "",
  );
  await fs.writeFile(temporaryPath, source);
  try {
    return await import(`${pathToFileURL(temporaryPath).href}?test=${Date.now()}`);
  } finally {
    await fs.unlink(temporaryPath).catch(() => {});
  }
}
