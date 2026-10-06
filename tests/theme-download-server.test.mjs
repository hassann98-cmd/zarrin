import test from "node:test";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { once } from "node:events";
import { mkdtemp, readFile, rm, writeFile } from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const serverPath = path.join(repoRoot, "scripts/theme-download-server.mjs");

test("R121 download server always advertises and serves the current versioned ZIP", async (t) => {
  const packageJson = JSON.parse(await readFile(path.join(repoRoot, "package.json"), "utf8"));
  const version = packageJson.version;
  const zipName = `jluxe-mobile-nav-${version}.zip`;
  const downloadDir = await mkdtemp(path.join(os.tmpdir(), "zarrin-download-server-"));
  const fixture = Buffer.from("PK\u0003\u0004zarrin test zip fixture", "binary");
  await writeFile(path.join(downloadDir, zipName), fixture);

  const child = spawn(process.execPath, [serverPath], {
    cwd: repoRoot,
    env: { ...process.env, PORT: "0", DOWNLOAD_DIR: downloadDir },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let output = "";
  let rejectReady;
  const ready = new Promise((resolve, reject) => {
    rejectReady = reject;
    child.stdout.on("data", (chunk) => {
      output += chunk.toString();
      const match = output.match(/listening on 0\.0\.0\.0:(\d+)/);
      if (match) resolve(Number(match[1]));
    });
    child.stderr.on("data", (chunk) => { output += chunk.toString(); });
    child.once("error", reject);
    child.once("exit", (code) => {
      if (!output.includes("listening on")) reject(new Error(`Download server exited early (${code}): ${output}`));
    });
  });
  const timeout = setTimeout(() => rejectReady(new Error(`Download server startup timed out: ${output}`)), 5000);
  t.after(async () => {
    clearTimeout(timeout);
    if (child.exitCode === null) {
      const stopped = once(child, "exit");
      child.kill("SIGTERM");
      await stopped;
    }
    await rm(downloadDir, { recursive: true, force: true });
  });

  const port = await ready;
  clearTimeout(timeout);
  const base = `http://127.0.0.1:${port}`;

  const page = await fetch(base);
  const html = await page.text();
  assert.equal(page.status, 200);
  assert.equal(page.headers.get("x-theme-version"), version);
  assert.match(html, new RegExp(`نسخهٔ <strong>${version}</strong>`));
  assert.match(html, /href="\/download\/latest"/);
  assert.ok(html.includes(zipName));

  const health = await fetch(`${base}/healthz`).then((response) => response.json());
  assert.equal(health.status, "ok");
  assert.equal(health.version, version);
  assert.equal(health.file, zipName);
  assert.equal(health.sizeBytes, fixture.length);

  const download = await fetch(`${base}/download/latest`);
  assert.equal(download.status, 200);
  assert.equal(download.headers.get("content-type"), "application/zip");
  assert.equal(download.headers.get("content-disposition"), `attachment; filename="${zipName}"`);
  assert.equal(download.headers.get("cache-control"), "no-store, no-cache, must-revalidate, max-age=0");
  assert.deepEqual(Buffer.from(await download.arrayBuffer()), fixture);

  const stale = await fetch(`${base}/download/jluxe-mobile-nav-1.64.44.zip`);
  assert.equal(stale.status, 404);
});
