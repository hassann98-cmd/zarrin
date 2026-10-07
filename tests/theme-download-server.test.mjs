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

test("download server lists every archived version and serves each ZIP by version", async (t) => {
  const packageJson = JSON.parse(await readFile(path.join(repoRoot, "package.json"), "utf8"));
  const version = packageJson.version;
  const zipName = `jluxe-mobile-nav-${version}.zip`;
  const olderVersion = "1.6.99";
  const olderZipName = `jluxe-mobile-nav-${olderVersion}.zip`;
  const remoteVersion = "1.5.99";
  const remoteFile = `zarrin-${remoteVersion}.zip`;
  const remoteUrl = `https://github.com/example/zarrin/releases/download/v${remoteVersion}/${remoteFile}`;
  const downloadDir = await mkdtemp(path.join(os.tmpdir(), "zarrin-download-server-"));
  const historyFile = path.join(downloadDir, "history.json");
  const fixture = Buffer.from("PK\u0003\u0004zarrin current test zip fixture", "binary");
  const olderFixture = Buffer.from("PK\u0003\u0004zarrin older test zip fixture", "binary");
  await writeFile(path.join(downloadDir, zipName), fixture);
  await writeFile(path.join(downloadDir, olderZipName), olderFixture);
  await writeFile(historyFile, JSON.stringify({ releases: [{ version: remoteVersion, file: remoteFile, url: remoteUrl, sizeBytes: 2048 }] }));

  const child = spawn(process.execPath, [serverPath], {
    cwd: repoRoot,
    env: { ...process.env, PORT: "0", DOWNLOAD_DIR: downloadDir, RELEASE_HISTORY_FILE: historyFile },
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
  assert.match(html, new RegExp(`آخرین نسخه: <strong>${version}</strong>`));
  assert.match(html, /href="\/download\/latest"/);
  assert.match(html, new RegExp(`href="\\/download\\/${olderVersion}"`));
  assert.match(html, new RegExp(`href="\\/download\\/${remoteVersion}"`));
  assert.ok(html.includes(zipName));
  assert.ok(html.includes(olderZipName));
  assert.ok(html.includes(remoteFile));
  assert.ok(html.includes("آرشیو GitHub"));

  const health = await fetch(`${base}/healthz`).then((response) => response.json());
  assert.equal(health.status, "ok");
  assert.equal(health.version, version);
  assert.equal(health.file, zipName);
  assert.equal(health.sizeBytes, fixture.length);
  assert.equal(health.releaseCount, 3);

  const catalogResponse = await fetch(`${base}/releases.json`);
  const catalog = await catalogResponse.json();
  assert.equal(catalogResponse.status, 200);
  assert.equal(catalog.latest, version);
  assert.deepEqual(catalog.releases.map((release) => release.version), [version, olderVersion, remoteVersion]);
  assert.equal(catalog.releases[0].url, "/download/latest");
  assert.equal(catalog.releases[1].url, `/download/${olderVersion}`);
  assert.equal(catalog.releases[1].sizeBytes, olderFixture.length);
  assert.equal(catalog.releases[2].url, `/download/${remoteVersion}`);
  assert.equal(catalog.releases[2].source, "github");
  assert.equal(catalog.releases[2].sizeBytes, 2048);

  const download = await fetch(`${base}/download/latest`);
  assert.equal(download.status, 200);
  assert.equal(download.headers.get("content-type"), "application/zip");
  assert.equal(download.headers.get("x-theme-version"), version);
  assert.equal(download.headers.get("content-disposition"), `attachment; filename="${zipName}"`);
  assert.equal(download.headers.get("cache-control"), "no-store, no-cache, must-revalidate, max-age=0");
  assert.deepEqual(Buffer.from(await download.arrayBuffer()), fixture);

  const olderDownload = await fetch(`${base}/download/${olderVersion}`);
  assert.equal(olderDownload.status, 200);
  assert.equal(olderDownload.headers.get("x-theme-version"), olderVersion);
  assert.equal(olderDownload.headers.get("content-disposition"), `attachment; filename="${olderZipName}"`);
  assert.deepEqual(Buffer.from(await olderDownload.arrayBuffer()), olderFixture);

  const olderFileDownload = await fetch(`${base}/download/${olderZipName}`);
  assert.equal(olderFileDownload.status, 200);
  assert.deepEqual(Buffer.from(await olderFileDownload.arrayBuffer()), olderFixture);

  const remoteDownload = await fetch(`${base}/download/${remoteVersion}`, { redirect: "manual" });
  assert.equal(remoteDownload.status, 302);
  assert.equal(remoteDownload.headers.get("location"), remoteUrl);
  assert.equal(remoteDownload.headers.get("x-theme-version"), remoteVersion);

  const stale = await fetch(`${base}/download/jluxe-mobile-nav-1.64.44.zip`);
  assert.equal(stale.status, 404);
});
