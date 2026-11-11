#!/usr/bin/env node
/**
 * Private sandbox download endpoint for the current installable theme ZIP.
 *
 * Default mode follows package.json and downloads/jluxe-mobile-nav-{version}.zip,
 * which is what release-theme.mjs verifies. Set DOWNLOAD_FILE and optionally
 * DOWNLOAD_VERSION to expose a one-off/private ZIP without changing project
 * release metadata.
 */
import { createReadStream } from "node:fs";
import { readFile, stat } from "node:fs/promises";
import { createServer } from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const packagePath = path.join(root, "package.json");
const downloadDir = path.resolve(process.env.DOWNLOAD_DIR || path.join(root, "downloads"));
const requestedPort = process.env.PORT ?? process.env.THEME_DOWNLOAD_SERVER_PORT ?? "4181";
const port = Number(requestedPort);
const host = process.env.HOST || "0.0.0.0";
const customFile = process.env.DOWNLOAD_FILE || "";
const customVersion = process.env.DOWNLOAD_VERSION || "";
const cacheControl = "no-store, no-cache, must-revalidate, max-age=0";

if (!Number.isInteger(port) || port < 0 || port > 65535) {
  throw new Error(`Invalid PORT: ${requestedPort}`);
}

function safeFilename(value) {
  if (
    typeof value !== "string" ||
    !value ||
    value === "." ||
    value === ".." ||
    path.basename(value) !== value ||
    value.includes("/") ||
    value.includes("\\") ||
    !/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(value) ||
    /[\r\n\u0000-\u001f]/.test(value)
  ) {
    throw new Error("Invalid download filename configuration");
  }
  return value;
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

async function currentRelease() {
  const pkg = JSON.parse(await readFile(packagePath, "utf8"));
  const version = customVersion || pkg.version;
  if (!/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(String(version))) {
    throw new Error("Theme release has no valid version");
  }
  const file = safeFilename(customFile || `jluxe-mobile-nav-${version}.zip`);
  const filePath = path.resolve(downloadDir, file);
  if (path.dirname(filePath) !== downloadDir) {
    throw new Error("Download path escaped DOWNLOAD_DIR");
  }
  try {
    const details = await stat(filePath);
    if (!details.isFile()) return { version, file, filePath, sizeBytes: 0, exists: false };
    return { version, file, filePath, sizeBytes: details.size, exists: true };
  } catch (error) {
    if (error?.code === "ENOENT") {
      return { version, file, filePath, sizeBytes: 0, exists: false };
    }
    throw error;
  }
}

function sendJson(response, statusCode, body, version = "") {
  response.writeHead(statusCode, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": cacheControl,
    "Pragma": "no-cache",
    "Expires": "0",
    "X-Content-Type-Options": "nosniff",
    ...(version ? { "X-Theme-Version": version } : {}),
  });
  response.end(JSON.stringify(body));
}

async function sendZip(request, response, release) {
  if (!release.exists) {
    sendJson(response, 404, { error: "download_not_ready", version: release.version, file: release.file }, release.version);
    return;
  }
  const headers = {
    "Content-Type": "application/zip",
    "Content-Disposition": `attachment; filename="${release.file}"`,
    "Content-Length": String(release.sizeBytes),
    "Cache-Control": cacheControl,
    "Pragma": "no-cache",
    "Expires": "0",
    "X-Content-Type-Options": "nosniff",
    "X-Theme-Version": release.version,
  };
  response.writeHead(200, headers);
  if (request.method === "HEAD") {
    response.end();
    return;
  }
  const stream = createReadStream(release.filePath);
  stream.on("error", (error) => {
    console.error(`[download] ${error.message}`);
    if (!response.headersSent) sendJson(response, 500, { error: "download_failed" }, release.version);
    else response.destroy(error);
  });
  stream.pipe(response);
}

const server = createServer(async (request, response) => {
  response.setHeader("X-Content-Type-Options", "nosniff");
  response.setHeader("Referrer-Policy", "no-referrer");
  response.setHeader("Cache-Control", cacheControl);

  if (request.method !== "GET" && request.method !== "HEAD") {
    response.writeHead(405, { Allow: "GET, HEAD", "Content-Type": "text/plain; charset=utf-8" });
    response.end("Method not allowed");
    return;
  }

  let pathname;
  try {
    pathname = new URL(request.url || "/", "http://download.local").pathname;
    pathname = decodeURIComponent(pathname);
  } catch {
    response.writeHead(400, { "Content-Type": "text/plain; charset=utf-8" });
    response.end("Bad request");
    return;
  }

  try {
    const release = await currentRelease();
    if (pathname === "/version") {
      sendJson(response, 200, { version: release.version, file: release.file }, release.version);
      return;
    }
    if (pathname === "/healthz") {
      sendJson(response, 200, {
        status: "ok",
        version: release.version,
        file: release.file,
        sizeBytes: release.sizeBytes,
      }, release.version);
      return;
    }
    if (pathname === "/releases.json") {
      sendJson(response, 200, {
        latest: release.version,
        releases: [{
          version: release.version,
          file: release.file,
          url: "/download/latest",
          sizeBytes: release.sizeBytes,
        }],
      }, release.version);
      return;
    }
    const latestPaths = new Set([
      "/download",
      "/download/latest",
      "/download/latest.zip",
      `/${release.file}`,
      `/download/${release.version}`,
      `/download/${release.file}`,
    ]);
    if (latestPaths.has(pathname)) {
      await sendZip(request, response, release);
      return;
    }
    if (pathname.startsWith("/download/")) {
      const requestedFile = pathname.slice("/download/".length);
      if (requestedFile !== release.file) {
        sendJson(response, 404, { error: "not_found" }, release.version);
        return;
      }
      await sendZip(request, response, release);
      return;
    }
    if (pathname !== "/" && pathname !== "/index.html") {
      sendJson(response, 404, { error: "not_found" }, release.version);
      return;
    }

    const sizeText = release.exists
      ? `${(release.sizeBytes / (1024 * 1024)).toFixed(2)} MB`
      : "در حال آماده‌سازی";
    const file = escapeHtml(release.file);
    const version = escapeHtml(release.version);
    const size = escapeHtml(sizeText);
    const html = `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>دانلود پوستهٔ زرین</title>
  <style>
    :root{color-scheme:light dark;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f5f7;color:#171717}
    main{width:min(92vw,560px);box-sizing:border-box;padding:32px;border:1px solid #e2e4e8;border-radius:20px;background:#fff;box-shadow:0 16px 48px #1112}
    h1{margin:0 0 12px;font-size:1.6rem}p{color:#555;line-height:1.8}
    .meta{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-block:1px solid #eceef0;margin:20px 0}
    a{display:block;text-align:center;text-decoration:none;background:#111;color:#fff;padding:14px 20px;border-radius:12px;font-weight:700}
    a:hover{background:#2b2b2b}.file{direction:ltr;overflow-wrap:anywhere;text-align:left;font-size:.9rem;color:#666}
    @media(prefers-color-scheme:dark){body{background:#101113;color:#f7f7f7}main{background:#1c1e21;border-color:#34373c}p,.file{color:#b5b8bd}.meta{border-color:#34373c}a{background:#f4f4f4;color:#111}}
  </style>
</head>
<body>
  <main>
    <h1>بستهٔ نصب پوستهٔ زرین</h1>
    <p>فایل ZIP را دریافت و از پیشخوان وردپرس در بخش پوسته‌ها نصب یا جایگزین کنید.</p>
    <div class="meta"><span>نسخهٔ <strong>${version}</strong></span><span>${size}</span></div>
    <p class="file">${file}</p>
    <a href="/download/latest" download>دانلود فایل نصب</a>
  </main>
</body>
</html>`;
    response.writeHead(200, {
      "Content-Type": "text/html; charset=utf-8",
      "Content-Length": Buffer.byteLength(html),
      "Cache-Control": cacheControl,
      "Pragma": "no-cache",
      "Expires": "0",
      "X-Theme-Version": release.version,
    });
    response.end(html);
  } catch (error) {
    console.error(`[download] ${error.stack || error.message}`);
    sendJson(response, 500, { error: "server_error" });
  }
});

server.on("error", (error) => {
  console.error(`[download] ${error.message}`);
  process.exitCode = 1;
});

server.listen(port, host, () => {
  const address = server.address();
  console.log(`Download server listening on 0.0.0.0:${address.port}`);
  console.log(`Download file: ${customFile || `jluxe-mobile-nav-${customVersion || "(package version)"}.zip`}`);
});

for (const signal of ["SIGINT", "SIGTERM"]) {
  process.on(signal, () => server.close(() => process.exit(0)));
}
