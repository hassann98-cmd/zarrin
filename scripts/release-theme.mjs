#!/usr/bin/env node
/**
 * Release the next patch and publish one verified, versioned ZIP to downloads/.
 * The live download server reads package.json on each request, so /download/latest
 * always resolves to the newly staged release without a server restart.
 */
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { resolveReleaseVersion } from "./release-version.mjs";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const read = async (relativePath) => readFile(path.join(root, relativePath), "utf8");
const write = async (relativePath, contents) =>
  writeFile(path.join(root, relativePath), contents, "utf8");
const replaceExact = (text, before, after, label) => {
  if (!text.includes(before)) throw new Error(`Cannot update ${label}; its current version marker is missing.`);
  return text.replace(before, after);
};

const pkg = JSON.parse(await read("package.json"));
const lock = JSON.parse(await read("package-lock.json"));
const current = pkg.version;
if (
  lock.version !== current ||
  lock.packages?.[""]?.version !== current
) {
  throw new Error("package.json and both package-lock root versions must agree before release.");
}
const requestedNext = process.argv[2] || process.env.THEME_RELEASE_VERSION || "";
const next = resolveReleaseVersion(current, requestedNext, process.env.THEME_ALLOW_VERSION_DOWNGRADE === "1");

const stylesheet = await read("style.css");
const buildMatch = /^Build: JLUXE (\d+\.\d+\.\d+)(.*)$/m.exec(stylesheet);
const versionMatch = /^Version: (\d+\.\d+\.\d+)$/m.exec(stylesheet);
if (buildMatch?.[1] !== current || versionMatch?.[1] !== current) {
  throw new Error("style.css build/version markers do not match package.json.");
}
const readme = await read("README.md");
const fixes = await read("docs/FIXES.fa.md");
const nextReadme = replaceExact(
  readme,
  `نسخهٔ این بستهٔ اصلاحی **${current}** است.`,
  `نسخهٔ این بستهٔ اصلاحی **${next}** است.`,
  "README current version",
);
const currentZipPath = `artifacts/zarrin-${current}.zip`;
const nextReadmeWithZip = nextReadme.includes(currentZipPath)
  ? replaceExact(nextReadme, currentZipPath, `artifacts/zarrin-${next}.zip`, "README release ZIP path")
  : nextReadme;
const nextFixes = replaceExact(
  fixes,
  `**نسخهٔ اصلاحی:** \`${current}\``,
  `**نسخهٔ اصلاحی:** \`${next}\``,
  "FIXES current version",
);

pkg.version = next;
lock.version = next;
lock.packages[""].version = next;
const nextStylesheet = stylesheet
  .replace(/^Build: JLUXE \d+\.\d+\.\d+/m, `Build: JLUXE ${next}`)
  .replace(/^Version: \d+\.\d+\.\d+$/m, `Version: ${next}`);

await write("package.json", `${JSON.stringify(pkg, null, 2)}\n`);
await write("package-lock.json", `${JSON.stringify(lock, null, 2)}\n`);
await write("style.css", nextStylesheet);
await write("README.md", nextReadmeWithZip);
await write("docs/FIXES.fa.md", nextFixes);

const run = (args) => execFileSync("npm", args, { cwd: root, stdio: "inherit" });
console.log(`Releasing ${current} → ${next}`);
run(["run", "check"]);
run(["run", "package"]);

const artifactPath = path.join(root, "artifacts", `zarrin-${next}.zip`);
const downloadPath = path.join(root, "downloads", `jluxe-mobile-nav-${next}.zip`);
const [artifact, staged] = await Promise.all([readFile(artifactPath), readFile(downloadPath)]);
if (!artifact.equals(staged)) throw new Error("The staged download ZIP differs from the verified artifact.");

const configuredServerPort = process.env.THEME_DOWNLOAD_SERVER_PORT || process.env.PORT;
const serverBase = (
  process.env.THEME_DOWNLOAD_SERVER_URL ||
  (configuredServerPort ? `http://127.0.0.1:${configuredServerPort}` : "")
).replace(/\/$/, "");
if (!serverBase) {
  throw new Error("Set THEME_DOWNLOAD_SERVER_URL or THEME_DOWNLOAD_SERVER_PORT/PORT to the active fresh download server.");
}
const metadataResponse = await fetch(`${serverBase}/version`, { cache: "no-store" });
if (!metadataResponse.ok) throw new Error(`Download server /version returned HTTP ${metadataResponse.status}.`);
const metadata = await metadataResponse.json();
if (metadata.version !== next || metadata.file !== `jluxe-mobile-nav-${next}.zip`) {
  throw new Error(`Download server advertises ${metadata.version}/${metadata.file}, expected ${next}.`);
}
const catalogResponse = await fetch(`${serverBase}/releases.json`, { cache: "no-store" });
if (!catalogResponse.ok) throw new Error(`Download server /releases.json returned HTTP ${catalogResponse.status}.`);
const catalog = await catalogResponse.json();
if (
  catalog.latest !== next ||
  !Array.isArray(catalog.releases) ||
  !catalog.releases.some((release) => release.version === next && release.file === `jluxe-mobile-nav-${next}.zip`)
) {
  throw new Error(`Download server release list does not include the new version ${next}.`);
}
const response = await fetch(`${serverBase}/download/latest`, { cache: "no-store" });
if (!response.ok) throw new Error(`Download server returned HTTP ${response.status} for /download/latest.`);
const served = Buffer.from(await response.arrayBuffer());
if (!served.equals(staged)) throw new Error("The live /download/latest response does not byte-match the staged ZIP.");
const versionedResponse = await fetch(`${serverBase}/download/${next}`, { cache: "no-store" });
if (!versionedResponse.ok) throw new Error(`Download server returned HTTP ${versionedResponse.status} for /download/${next}.`);
const versionedBytes = Buffer.from(await versionedResponse.arrayBuffer());
if (!versionedBytes.equals(staged)) throw new Error(`The listed /download/${next} ZIP does not byte-match the staged package.`);

const sha256 = createHash("sha256").update(staged).digest("hex");
console.log(`Release ${next} is verified and live at ${serverBase}/download/latest`);
console.log(`Bytes: ${staged.length}; SHA-256: ${sha256}`);
