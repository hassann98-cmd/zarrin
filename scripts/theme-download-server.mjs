import { createHash } from "node:crypto";
import { createReadStream } from "node:fs";
import { readFile, stat } from "node:fs/promises";
import { createServer } from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const downloadsDir = path.resolve(process.env.DOWNLOAD_DIR || path.join(repoRoot, "downloads"));
const port = Number(process.env.PORT || 4181);
const noCacheHeaders = {
  "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
  Pragma: "no-cache",
  Expires: "0",
  "X-Content-Type-Options": "nosniff",
};

async function currentRelease() {
  const packageJson = JSON.parse(await readFile(path.join(repoRoot, "package.json"), "utf8"));
  const version = String(packageJson.version || "");
  if (!/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(version)) {
    throw new Error("package.json has no valid theme version");
  }
  const zipName = `jluxe-mobile-nav-${version}.zip`;
  const zipPath = path.join(downloadsDir, zipName);
  const file = await stat(zipPath);
  if (!file.isFile() || file.size < 4) {
    throw new Error(`Current release ZIP is missing or empty: ${zipPath}`);
  }
  return { version, zipName, zipPath, size: file.size };
}

function send(res, status, headers, body = "") {
  res.writeHead(status, { ...noCacheHeaders, ...headers });
  res.end(body);
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

  let release;
  try {
    // Read the current version for every request, so a long-running server
    // cannot keep advertising the package version captured at process start.
    release = await currentRelease();
  } catch (error) {
    console.error("Current theme release is unavailable:", error.message);
    return send(res, 503, { "Content-Type": "text/plain; charset=utf-8" }, "Current theme ZIP is not available");
  }
  const versionHeaders = { "X-Theme-Version": release.version };

  if (pathname === "/healthz" || pathname === "/version") {
    const body = JSON.stringify({
      status: "ok",
      version: release.version,
      file: release.zipName,
      sizeBytes: release.size,
      download: "/download/latest",
    });
    return send(res, 200, { ...versionHeaders, "Content-Type": "application/json; charset=utf-8" }, req.method === "HEAD" ? "" : body);
  }

  if (pathname === "/releases.json") {
    const body = JSON.stringify({
      latest: release.version,
      releases: [{ version: release.version, file: release.zipName, url: "/download/latest", sizeBytes: release.size }],
    });
    return send(res, 200, { ...versionHeaders, "Content-Type": "application/json; charset=utf-8" }, req.method === "HEAD" ? "" : body);
  }

  if (pathname === "/" || pathname === "/index.html") {
    const page = `<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>دانلود پوسته زرین · نسخه ${release.version}</title>
  <style>
    * { box-sizing: border-box; }
    body { display:grid; place-items:center; min-height:100vh; margin:0; padding:24px; background:#f2f4f3; color:#202725; font-family:Tahoma,Arial,sans-serif; }
    main { width:min(100%,480px); padding:28px; border:1px solid #e0e5e2; border-radius:20px; background:#fff; box-shadow:0 18px 50px #14221a12; text-align:center; }
    h1 { margin:0 0 10px; font-size:21px; }
    p { margin:0 0 20px; color:#63706a; font-size:14px; line-height:1.9; }
    a { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:0 22px; border-radius:12px; background:#087a68; color:#fff; font-weight:700; text-decoration:none; }
    a:hover,a:focus-visible { background:#066453; }
    a:focus-visible { outline:3px solid #83cbb9; outline-offset:3px; }
    small { display:block; margin-top:16px; color:#7c8782; direction:ltr; overflow-wrap:anywhere; }
  </style>
</head>
<body>
  <main>
    <h1>دانلود پوسته زرین</h1>
    <p>نسخهٔ <strong>${release.version}</strong> · فایل نصب ZIP · آمادهٔ دانلود</p>
    <a href="/download/latest" download="${release.zipName}">دانلود فایل پوسته</a>
    <small>${release.zipName}</small>
  </main>
</body>
</html>`;
    return send(res, 200, { ...versionHeaders, "Content-Type": "text/html; charset=utf-8" }, req.method === "HEAD" ? "" : page);
  }

  const isLatestDownload = [
    "/download",
    "/download/latest",
    "/download/latest.zip",
    `/${release.zipName}`,
    `/download/${release.version}`,
    `/download/${release.zipName}`,
  ].includes(pathname);
  if (isLatestDownload) {
    const headers = {
      ...versionHeaders,
      "Content-Type": "application/zip",
      "Content-Length": release.size,
      "Content-Disposition": `attachment; filename="${release.zipName}"`,
    };
    if (req.method === "HEAD") return send(res, 200, headers);
    res.writeHead(200, { ...noCacheHeaders, ...headers });
    const stream = createReadStream(release.zipPath);
    stream.on("error", (error) => {
      console.error("ZIP stream failed:", error);
      if (!res.headersSent) res.writeHead(500);
      res.destroy(error);
    });
    return stream.pipe(res);
  }

  return send(res, 404, { ...versionHeaders, "Content-Type": "text/plain; charset=utf-8" }, "Not found");
});

server.listen(port, "0.0.0.0", () => {
  const address = server.address();
  const activePort = typeof address === "object" && address ? address.port : port;
  console.log(`Theme download server listening on 0.0.0.0:${activePort}`);
});
