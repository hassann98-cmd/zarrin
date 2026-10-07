import { createReadStream } from "node:fs";
import { readFile, readdir, stat } from "node:fs/promises";
import { createServer } from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const downloadsDir = path.resolve(process.env.DOWNLOAD_DIR || path.join(repoRoot, "downloads"));
const releaseHistoryFile = path.resolve(process.env.RELEASE_HISTORY_FILE || path.join(repoRoot, "release-history.json"));
const port = process.env.PORT === undefined ? 0 : Number(process.env.PORT);
const VERSION_PATTERN = /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/;
const ZIP_PATTERN = /^jluxe-mobile-nav-(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\.zip$/;

if (!Number.isInteger(port) || port < 0 || port > 65535) {
  throw new Error(`Invalid PORT value: ${process.env.PORT}`);
}

const noCacheHeaders = {
  "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
  Pragma: "no-cache",
  Expires: "0",
  "X-Content-Type-Options": "nosniff",
};

function compareVersionsDescending(left, right) {
  const leftCore = left.split(/[+-]/, 1)[0].split(".").map(Number);
  const rightCore = right.split(/[+-]/, 1)[0].split(".").map(Number);
  for (let index = 0; index < 3; index += 1) {
    if (leftCore[index] !== rightCore[index]) return rightCore[index] - leftCore[index];
  }

  const leftIsPrerelease = left.includes("-");
  const rightIsPrerelease = right.includes("-");
  if (leftIsPrerelease !== rightIsPrerelease) return leftIsPrerelease ? 1 : -1;
  return right.localeCompare(left, "en", { numeric: true });
}

async function availableReleases() {
  let entries;
  try {
    entries = await readdir(downloadsDir, { withFileTypes: true });
  } catch (error) {
    if (error && error.code === "ENOENT") return [];
    throw error;
  }

  const releases = await Promise.all(
    entries.flatMap((entry) => {
      if (!entry.isFile()) return [];
      const match = ZIP_PATTERN.exec(entry.name);
      if (!match || !VERSION_PATTERN.test(match[1])) return [];

      const file = entry.name;
      const version = match[1];
      const zipPath = path.join(downloadsDir, file);
      return [
        stat(zipPath).then((metadata) => {
          if (!metadata.isFile() || metadata.size < 4) return null;
          return { version, file, zipPath, sizeBytes: metadata.size };
        }),
      ];
    }),
  );

  return releases.filter(Boolean).sort((left, right) => compareVersionsDescending(left.version, right.version));
}

async function historicalReleases() {
  let history;
  try {
    history = JSON.parse(await readFile(releaseHistoryFile, "utf8"));
  } catch (error) {
    if (error && error.code === "ENOENT") return [];
    throw error;
  }

  if (!Array.isArray(history.releases)) return [];
  return history.releases.flatMap((entry) => {
    const version = String(entry?.version || "");
    const file = String(entry?.file || "");
    const remoteUrl = String(entry?.url || "");
    const sizeBytes = Number(entry?.sizeBytes || 0);
    let parsedUrl;
    try {
      parsedUrl = new URL(remoteUrl);
    } catch {
      return [];
    }
    if (
      !VERSION_PATTERN.test(version) ||
      path.basename(file) !== file ||
      !file.endsWith(".zip") ||
      !Number.isSafeInteger(sizeBytes) || sizeBytes < 4 ||
      parsedUrl.protocol !== "https:" ||
      parsedUrl.hostname !== "github.com" ||
      !parsedUrl.pathname.includes("/releases/download/")
    ) {
      return [];
    }
    return [{ version, file, sizeBytes, remoteUrl }];
  });
}

async function releaseState() {
  const packageJson = JSON.parse(await readFile(path.join(repoRoot, "package.json"), "utf8"));
  const version = String(packageJson.version || "");
  if (!VERSION_PATTERN.test(version)) {
    throw new Error("package.json has no valid theme version");
  }

  const localReleases = await availableReleases();
  const localVersions = new Set(localReleases.map((release) => release.version));
  const remoteReleases = (await historicalReleases()).filter((release) => !localVersions.has(release.version));
  const releases = [...localReleases, ...remoteReleases].sort((left, right) => compareVersionsDescending(left.version, right.version));
  const current = localReleases.find((release) => release.version === version);
  if (!current) {
    throw new Error(`Current release ZIP is missing: jluxe-mobile-nav-${version}.zip`);
  }
  return { current, releases };
}

function send(res, status, headers, body = "") {
  res.writeHead(status, { ...noCacheHeaders, ...headers });
  res.end(body);
}

function publicRelease(release, latestVersion) {
  return {
    version: release.version,
    file: release.file,
    url: release.version === latestVersion ? "/download/latest" : `/download/${release.version}`,
    sizeBytes: release.sizeBytes,
    latest: release.version === latestVersion,
    source: release.remoteUrl ? "github" : "sandbox",
  };
}

function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function renderReleaseRows(releases, latestVersion) {
  return releases.map((release) => {
    const item = publicRelease(release, latestVersion);
    const latestBadge = item.latest ? '<span class="badge">جدیدترین</span>' : "";
    const sourceLabel = item.source === "github" ? " · آرشیو GitHub" : "";
    return `<li>
      <div class="release-info">
        <strong>نسخهٔ ${escapeHtml(item.version)}</strong>${latestBadge}
        <small>${escapeHtml(item.file)} · ${formatBytes(item.sizeBytes)}${sourceLabel}</small>
      </div>
      <a class="release-link" href="${escapeHtml(item.url)}" download="${escapeHtml(item.file)}" aria-label="دانلود نسخهٔ ${escapeHtml(item.version)}">دانلود</a>
    </li>`;
  }).join("\n");
}

const server = createServer(async (req, res) => {
  let pathname;
  try {
    pathname = new URL(req.url || "/", "http://download.local").pathname;
  } catch {
    return send(res, 400, { "Content-Type": "text/plain; charset=utf-8" }, "Bad request");
  }
  if (req.method !== "GET" && req.method !== "HEAD") {
    return send(res, 405, { Allow: "GET, HEAD", "Content-Type": "text/plain; charset=utf-8" }, "Method not allowed");
  }

  let state;
  try {
    // Re-scan on every request so each newly packaged version appears without a restart.
    state = await releaseState();
  } catch (error) {
    console.error("Current theme release is unavailable:", error.message);
    return send(res, 503, { "Content-Type": "text/plain; charset=utf-8" }, "Current theme ZIP is not available");
  }

  const { current, releases } = state;
  const latestHeaders = { "X-Theme-Version": current.version };

  if (pathname === "/healthz" || pathname === "/version") {
    const body = JSON.stringify({
      status: "ok",
      version: current.version,
      file: current.file,
      sizeBytes: current.sizeBytes,
      download: "/download/latest",
      releases: "/releases.json",
      releaseCount: releases.length,
    });
    return send(res, 200, { ...latestHeaders, "Content-Type": "application/json; charset=utf-8" }, req.method === "HEAD" ? "" : body);
  }

  if (pathname === "/releases.json") {
    const body = JSON.stringify({
      latest: current.version,
      releases: releases.map((release) => publicRelease(release, current.version)),
    });
    return send(res, 200, { ...latestHeaders, "Content-Type": "application/json; charset=utf-8" }, req.method === "HEAD" ? "" : body);
  }

  if (pathname === "/" || pathname === "/index.html") {
    const rows = renderReleaseRows(releases, current.version);
    const page = `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>دانلود پوسته زرین · نسخه ${escapeHtml(current.version)}</title>
  <style>
    * { box-sizing: border-box; }
    body { display:grid; place-items:center; min-height:100vh; margin:0; padding:24px; background:#f2f4f3; color:#202725; font-family:Tahoma,Arial,sans-serif; }
    main { width:min(100%,640px); padding:28px; border:1px solid #e0e5e2; border-radius:20px; background:#fff; box-shadow:0 18px 50px #14221a12; }
    h1 { margin:0 0 10px; font-size:21px; text-align:center; }
    .intro { margin:0 0 22px; color:#63706a; font-size:14px; line-height:1.9; text-align:center; }
    h2 { margin:0 0 12px; font-size:16px; }
    ul { display:grid; gap:10px; margin:0; padding:0; list-style:none; }
    li { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px; border:1px solid #e0e5e2; border-radius:14px; background:#fff; }
    .release-info { display:grid; gap:5px; min-width:0; }
    .release-info strong { display:flex; align-items:center; gap:8px; font-size:14px; }
    .release-info small { color:#63706a; direction:ltr; text-align:right; overflow-wrap:anywhere; }
    .badge { padding:3px 8px; border-radius:999px; background:#e9f5f1; color:#075b4d; font-size:11px; }
    .release-link { display:inline-flex; flex:none; align-items:center; justify-content:center; min-height:44px; padding:0 18px; border-radius:11px; background:#087a68; color:#fff; font-weight:700; text-decoration:none; }
    .release-link:hover,.release-link:focus-visible { background:#066453; }
    .release-link:focus-visible { outline:3px solid #83cbb9; outline-offset:3px; }
    @media(max-width:480px) { body { padding:14px; } main { padding:20px 14px; } li { align-items:flex-start; padding:12px; } .release-link { padding-inline:12px; } }
  </style>
</head>
<body>
  <main>
    <h1>دانلود پوسته زرین</h1>
    <p class="intro">آخرین نسخه: <strong>${escapeHtml(current.version)}</strong> · نسخه‌های قبلی نیز از فهرست زیر در دسترس‌اند.</p>
    <h2 id="releases-title">همهٔ نسخه‌های موجود</h2>
    <ul aria-labelledby="releases-title">${rows}</ul>
  </main>
</body>
</html>`;
    return send(res, 200, { ...latestHeaders, "Content-Type": "text/html; charset=utf-8" }, req.method === "HEAD" ? "" : page);
  }

  const latestPaths = new Set(["/download", "/download/latest", "/download/latest.zip", `/${current.file}`]);
  let selectedRelease = latestPaths.has(pathname) ? current : null;
  if (!selectedRelease) {
    selectedRelease = releases.find((release) => (
      pathname === `/download/${release.version}` ||
      pathname === `/download/${release.file}` ||
      pathname === `/${release.file}`
    )) || null;
  }

  if (selectedRelease?.remoteUrl) {
    return send(res, 302, {
      "X-Theme-Version": selectedRelease.version,
      Location: selectedRelease.remoteUrl,
      "Content-Type": "text/plain; charset=utf-8",
    }, req.method === "HEAD" ? "" : `Redirecting to the archived release ${selectedRelease.version}.`);
  }

  if (selectedRelease) {
    const headers = {
      "X-Theme-Version": selectedRelease.version,
      "Content-Type": "application/zip",
      "Content-Length": selectedRelease.sizeBytes,
      "Content-Disposition": `attachment; filename="${selectedRelease.file}"`,
    };
    if (req.method === "HEAD") return send(res, 200, headers);
    res.writeHead(200, { ...noCacheHeaders, ...headers });
    const stream = createReadStream(selectedRelease.zipPath);
    stream.on("error", (error) => {
      console.error("ZIP stream failed:", error);
      if (!res.headersSent) res.writeHead(500);
      res.destroy(error);
    });
    return stream.pipe(res);
  }

  return send(res, 404, { ...latestHeaders, "Content-Type": "text/plain; charset=utf-8" }, "Not found");
});

server.listen(port, "0.0.0.0", () => {
  const address = server.address();
  const activePort = typeof address === "object" && address ? address.port : port;
  console.log(`Theme download server listening on 0.0.0.0:${activePort}`);
});
