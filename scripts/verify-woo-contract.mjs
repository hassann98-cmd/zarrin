/**
 * R86 — بررسیِ «قرارداد» پوسته با نسخهٔ واقعیِ ووکامرس.
 *
 * چرا: تا حالا هیچ‌جا با کدِ واقعیِ ووکامرس سنجیده نمی‌شد که پوسته فقط از
 * توابع/فیلترها/متدهای موجود استفاده می‌کند. این اسکریپت همان کار را انجام
 * می‌دهد؛ اگر بستهٔ ووکامرس در دسترس باشد (مثلاً `woocommerce.11.1.2.zip`
 * که کنارِ خودِ پوسته در مخزن است):
 *
 *   npm run verify:woo                     # پیدا کردنِ خودکارِ zip
 *   npm run verify:woo -- /path/to/woocommerce.zip
 *   WOOCOMMERCE_DIR=/path/to/woocommerce npm run verify:woo
 *
 * سه چیز بررسی می‌شود:
 *   ۱) هر `wc_*()`/`woocommerce_*()` که پوسته صدا می‌زند، در پلاگین تعریف شده باشد.
 *   ۲) هر متدی که پوسته روی آبجکت‌های ووکامرس صدا می‌زند (`$product->...`,
 *      `$order->...`) در همان نسخه وجود داشته باشد.
 *   ۳) قالب‌های بازنویسی‌شدهٔ پوسته (`woocommerce/**`) نسبت به نسخهٔ خودِ پلاگین
 *      منقضی نشده باشند (`@version` هر دو طرف مقایسه می‌شود).
 *
 * مرزِ صادقانه: این یک بررسیِ «سازگاریِ API» است، نه تستِ runtime. اجرای واقعیِ
 * خرید/سبد/تسویه هنوز به یک سایتِ staging با ووکامرسِ نصب‌شده نیاز دارد.
 */
import fs from "node:fs";
import path from "node:path";
import { execFileSync } from "node:child_process";

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const skipDirs = new Set(["node_modules", ".git", ".github", "dist", "tests", "coverage", "artifacts", ".cache", "assets"]);
const results = { pass: [], warn: [], fail: [] };
const pass = (m) => results.pass.push(m);
const warn = (m) => results.warn.push(m);
const fail = (m) => results.fail.push(m);

/* ------------------------------------------------------------------ inputs */
function findWooSource() {
  const explicit = process.argv[2] || process.env.WOOCOMMERCE_DIR;
  if (explicit) {
    const p = path.resolve(explicit);
    if (fs.existsSync(p) && fs.statSync(p).isDirectory()) return p;
    if (fs.existsSync(p) && /\.zip$/i.test(p)) return extract(p);
    throw new Error(`WooCommerce source not found at ${p}`);
  }
  const candidates = fs
    .readdirSync(root)
    .filter((name) => /^woocommerce[.-].*\.zip$/i.test(name));
  for (const name of candidates) return extract(path.join(root, name));
  if (fs.existsSync(path.join(root, "woocommerce-plugin"))) return path.join(root, "woocommerce-plugin");
  throw new Error(
    "No WooCommerce package found. Put woocommerce.<version>.zip next to the theme (or pass a path / WOOCOMMERCE_DIR).",
  );
}

function extract(zip) {
  const target = path.join(root, ".cache", "woo-verify");
  fs.rmSync(target, { recursive: true, force: true });
  fs.mkdirSync(target, { recursive: true });
  execFileSync("unzip", ["-q", zip, "-d", target]);
  const inner = fs.readdirSync(target).map((n) => path.join(target, n));
  const dir = inner.find((p) => fs.statSync(p).isDirectory() && fs.existsSync(path.join(p, "woocommerce.php")));
  if (!dir) throw new Error(`Could not find woocommerce.php inside ${zip}`);
  return dir;
}

function walk(dir, filter, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (skipDirs.has(entry.name)) continue;
      walk(path.join(dir, entry.name), filter, out);
    } else if (filter(entry.name)) {
      out.push(path.join(dir, entry.name));
    }
  }
  return out;
}

/** توضیحات/کامنت‌ها را حذف می‌کند تا متنِ توضیحی با نامِ توابع اشتباه گرفته نشود. */
function stripComments(code) {
  return code
    .replace(/\/\*[\s\S]*?\*\//g, " ")
    .replace(/(^|[^:'"\\])\/\/[^\n]*/g, "$1 ")
    .replace(/#[^\n]*/g, " ");
}

const __t0 = Date.now();
const wooDir = findWooSource();
const __t1 = Date.now();
const pluginHeader = fs.readFileSync(path.join(wooDir, "woocommerce.php"), "utf8");
const wooVersion = (pluginHeader.match(/Version:\s*([0-9.]+)/) || [, "?"])[1];
console.log(`WooCommerce source: ${wooDir} (version ${wooVersion})`);

const pluginPhp = walk(wooDir, (n) => n.endsWith(".php"));
const __t2 = Date.now();
const pluginBody = pluginPhp.map((f) => fs.readFileSync(f, "utf8"));
const pluginAll = pluginBody.join("\n");
const themePhp = walk(root, (n) => n.endsWith(".php"));
const themeBody = themePhp.map((f) => stripComments(fs.readFileSync(f, "utf8")));
const themeAll = themeBody.join("\n");
const rel = (p) => path.relative(root, p);
if (process.env.WOO_VERIFY_PROFILE) console.error(`[profile] extract ${__t1-__t0}ms, plugin walk ${__t2-__t1}ms, theme walk ${Date.now()-__t2}ms`);
const __t3 = Date.now();

if (process.env.WOO_VERIFY_PROFILE) console.error(`[profile] before checks ${Date.now()-__t3}ms`);
if (process.env.WOO_VERIFY_PROFILE) console.error('[profile] sec1 at ' + (Date.now()-__t3) + 'ms');
/* ---------------------------------------- 1) wc_ and woocommerce_ functions */
const calledFunctions = new Set();
for (const body of themeBody) {
  for (const match of body.matchAll(/(?<![a-zA-Z0-9_$>:])((?:wc|woocommerce)_[a-z0-9_]+)\s*\(/g)) {
    calledFunctions.add(match[1]);
  }
}
const missingFunctions = [];
for (const name of [...calledFunctions].sort()) {
  if (!new RegExp(`function\\s+${name}\\s*\\(`).test(pluginAll)) missingFunctions.push(name);
}
if (missingFunctions.length) {
  fail(`theme calls ${missingFunctions.length} WooCommerce function(s) that WooCommerce ${wooVersion} does not define: ${missingFunctions.join(", ")}`);
} else {
  pass(`${calledFunctions.size} WooCommerce functions used by the theme all exist in ${wooVersion}`);
}

if (process.env.WOO_VERIFY_PROFILE) console.error('[profile] sec2 at ' + (Date.now()-__t3) + 'ms');
/* --------------------------------------------------- 2) object method calls */
const objectCalls = new Map(); // method => Set(variable)
for (const [i, body] of themeBody.entries()) {
  for (const match of body.matchAll(/\$([a-z_][a-z0-9_]*)\s*->\s*([a-z_][a-z0-9_]*)\s*\(/g)) {
    const [, variable, method] = match;
    if (!objectCalls.has(method)) objectCalls.set(method, new Set());
    objectCalls.get(method).add(`${variable} (${rel(themePhp[i])})`);
  }
}
// Only methods called on things that look like WooCommerce objects.
const wooish = /product|order|item|variation|cart|session|coupon|address|attribute|shipping|payment|gateway|zone|term/i;
const missingMethods = [];
const checkedMethods = [];
for (const [method, where] of [...objectCalls].sort()) {
  const owners = [...where];
  if (!owners.some((o) => wooish.test(o))) continue;
  checkedMethods.push(method);
  if (!new RegExp(`function\\s+${method}\\s*\\(`).test(pluginAll)) missingMethods.push(`${method}() — called as ${owners[0]}`);
}
if (missingMethods.length) {
  fail(`theme calls ${missingMethods.length} method(s) that do not exist anywhere in WooCommerce ${wooVersion}: ${missingMethods.join("; ")}`);
} else {
  pass(`${checkedMethods.length} WooCommerce object methods used by the theme exist in ${wooVersion}`);
}

if (process.env.WOO_VERIFY_PROFILE) console.error('[profile] sec3 at ' + (Date.now()-__t3) + 'ms');
/* --------------------------------------------------------------- 3) hooks */
const hooks = new Set();
for (const body of themeBody) {
  for (const match of body.matchAll(/(?:add_action|add_filter|do_action|apply_filters|remove_action|remove_filter)\s*\(\s*'([a-z0-9_]+)'/gi)) {
    hooks.add(match[1]);
  }
}
const ourHooks = /^jluxe_/;
const wpCore = new RegExp(
  "^(" +
    ["wp_", "gettext", "script_loader_tag", "style_loader_tag", "posts_", "clean_", "created_", "edited_", "deleted_",
     "number_format_", "cron_", "registration_", "image_editor_", "user_", "comment_", "save_", "edit_", "manage_",
     "transition_", "pre_", "post_", "the_", "admin_", "rest_", "template_", "init", "after_", "before_", "woo",
     "woocommerce_", "wc_", "product_cat", "product_tag", "term_", "taxonomy_", "plugin_", "option_", "update_",
     "automatic_", "upgrader_", "http_", "auth_", "login_", "logout_", "profile_", "personal_", "password_",
     "lostpassword_", "retrieve_", "signup_", "wpmu_", "network_", "site_", "wp$", "body_class", "trashed_post",
     "comments_open", "delete_term", "created_.*(cat|tag)$", "edited_.*(cat|tag)$", "delete_.*(cat|tag)$"].join("|") +
  ")",
);
const pluginHooks = new Set();
for (const match of pluginAll.matchAll(/(?:do_action|apply_filters)\s*\(\s*['"]([a-z0-9_]+)['"]/gi)) pluginHooks.add(match[1]);
const unknownHooks = [];
const coreHooks = [];
for (const hook of [...hooks].sort()) {
  if (ourHooks.test(hook)) continue;
  const known = pluginHooks.has(hook);
  if (known) continue;
  if (wpCore.test(hook)) coreHooks.push(hook);
  else unknownHooks.push(hook);
}
if (unknownHooks.length) {
  warn(`hooks the theme listens to that are neither in WooCommerce ${wooVersion} nor recognisable WordPress core hooks (check who owns them): ${unknownHooks.join(", ")}`);
} else {
  pass(`${hooks.size} hooks used by the theme are recognised (${coreHooks.length} of them WordPress/third-party, not verifiable without WordPress core)`);
}

if (process.env.WOO_VERIFY_PROFILE) console.error('[profile] sec4 at ' + (Date.now()-__t3) + 'ms');
/* ------------------------------------------- 4) template override freshness */
function templateVersion(text) {
  const match = text.match(/@version\s+([0-9.]+)/);
  return match ? match[1] : null;
}
const overrides = walk(path.join(root, "woocommerce"), (n) => n.endsWith(".php"));
const similarities = [];
let stale = 0;
let matchingVersion = 0;
const missingInPlugin = [];
const knownCustomTemplates = new Set(["content-single-product-classic.php"]);
const customTemplates = [];
for (const file of overrides) {
  const relative = path.relative(path.join(root, "woocommerce"), file);
  const upstream = path.join(wooDir, "templates", relative);
  const ours = templateVersion(fs.readFileSync(file, "utf8"));
  if (!fs.existsSync(upstream)) {
    (knownCustomTemplates.has(relative) ? customTemplates : missingInPlugin).push(relative);
    continue;
  }
  const upstreamText = fs.readFileSync(upstream, "utf8");
  const theirs = templateVersion(upstreamText);
  const sim = similarity(fs.readFileSync(file, "utf8"), upstreamText);
  similarities.push([relative, sim, theirs]);
  if (ours && theirs && ours === theirs) {
    matchingVersion++;
  } else if (ours && theirs) {
    stale++;
    warn(`template override ${relative}: our @version ${ours} vs WooCommerce ${theirs} — re-check it against the upstream file`);
  } else {
    warn(`template override ${relative}: no @version header on one side (ours ${ours ?? "none"}, upstream ${theirs ?? "none"})`);
  }
}
if (customTemplates.length) {
  pass(`known theme-specific template(s) have no upstream counterpart: ${customTemplates.join(", ")}`);
}
if (missingInPlugin.length) {
  warn(`template overrides with no upstream counterpart and no explicit custom classification: ${missingInPlugin.join(", ")}`);
}
pass(`${overrides.length} WooCommerce template files checked against ${wooVersion}: ${matchingVersion} declare a matching @version${stale ? `, ${stale} version mismatch(es)` : ""}`);
const closeCopies = similarities.filter(([, sim]) => sim >= 70).sort((a, b) => b[1] - a[1]);
if (closeCopies.length) {
  pass(
    `${closeCopies.length} override(s) retain at least 70% normalized line overlap with upstream; this is informational, while @version parity above is the staleness signal: ` +
      closeCopies.map(([file, sim, version]) => `${file} ~${sim}% of ${version ?? "?"}`).join(", "),
  );
}

/** شباهتِ خطی (خطوطِ کدِ غیرِخالیِ مشترک) — نشان می‌دهد بازنویسی چقدر به قالبِ خودِ ووکامرس نزدیک است. */
function similarity(ours, theirs) {
  const norm = (text) =>
    text
      .split("\n")
      .map((line) => line.trim())
      .filter((line) => line && !line.startsWith("//") && !line.startsWith("*") && !line.startsWith("/*"));
  const a = norm(ours);
  const b = new Set(norm(theirs));
  if (!a.length) return 0;
  return Math.round((a.filter((line) => b.has(line)).length / a.length) * 100);
}

if (process.env.WOO_VERIFY_PROFILE) console.error(`[profile] checks ${Date.now()-__t3}ms`);
/* ----------------------------------------------------------------- report */
console.log("");
for (const line of results.pass) console.log(`PASS  ${line}`);
for (const line of results.warn) console.log(`WARN  ${line}`);
for (const line of results.fail) console.log(`FAIL  ${line}`);
console.log(
  `\nWOO_CONTRACT_SUMMARY: ${results.pass.length} pass, ${results.warn.length} warn, ${results.fail.length} fail (WooCommerce ${wooVersion})`,
);
process.exit(results.fail.length ? 1 : 0);
