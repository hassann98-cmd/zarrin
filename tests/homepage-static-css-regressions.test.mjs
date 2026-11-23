import assert from "node:assert/strict";
import fs from "node:fs/promises";
import path from "node:path";
import test from "node:test";

const root = process.cwd();
const php = await fs.readFile(
  path.join(root, "inc/theme-settings-homepage.php"),
  "utf8",
);
const manifest = JSON.parse(
  await fs.readFile(path.join(root, "assets/compiled/manifest.json"), "utf8"),
);
const mainCss = (
  await Promise.all(
    (manifest["src/main.js"]?.css ?? []).map((file) =>
      fs.readFile(path.join(root, "assets/compiled", file), "utf8"),
    ),
  )
).join("\n");

function section(start, end) {
  const from = php.indexOf(start);
  assert.notEqual(from, -1, `Missing source section: ${start}`);
  const to = php.indexOf(end, from + start.length);
  assert.notEqual(to, -1, `Missing end marker: ${end}`);
  return php.slice(from, to);
}

function compiledUtilitySelector(name) {
  const escaped = name.replace(/([:/])/g, "\\$1");
  assert.ok(
    mainCss.includes(`.${escaped}{`),
    `Tailwind utility .${name} is missing from compiled main CSS`,
  );
}

test("homepage utilities changed for static PHP templates exist in compiled CSS", () => {
  for (const utility of [
    "grid-cols-1",
    "sm:grid-cols-2",
    "lg:grid-cols-3",
    "aspect-video",
    "w-5",
    "via-black/10",
    "border-white/40",
    "shadow-sm",
  ]) {
    compiledUtilitySelector(utility);
  }
});

test("blog uses the restored desktop grid and leaves mobile swiping to authored CSS", () => {
  const blog = section(
    "function jluxe_render_homepage_blog(",
    "function jluxe_banner_position_classes(",
  );
  assert.match(
    blog,
    /jluxe-home-blog-scroller grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3/,
  );
  assert.match(blog, /jluxe-home-blog-card/);
  assert.match(blog, /class="block aspect-video overflow-hidden bg-muted"/);
  assert.match(blog, /if \( has_post_thumbnail\( \$p \) \)/);
  assert.doesNotMatch(
    blog,
    /(?:^|\s)lg:grid(?:\s|")|w-\[80vw\]|sm:w-\[46vw\]|lg:w-auto|lg:shrink/,
  );
});

test("story viewer positions and translucent surfaces use authored CSS", () => {
  const stories = section(
    "function jluxe_render_homepage_stories(",
    "function jluxe_carousel_autoscroll_attr(",
  );
  for (const rule of [
    ".jluxe-story-viewer{background:rgba(0,0,0,.9)}",
    ".jluxe-story-progress{top:16px;background:rgba(255,255,255,.3)}",
    ".jluxe-story-close{top:32px;background:rgba(255,255,255,.1)}",
    ".jluxe-story-nav{background:rgba(255,255,255,.16);font-size:24px}",
    ".jluxe-story-cta{bottom:28px}",
  ]) {
    assert.ok(stories.includes(rule), `Missing authored story CSS: ${rule}`);
  }
  assert.match(stories, /static \$viewer_style_printed = false/);
  assert.match(stories, /data-jluxe-story-prev[^>]*>›<\/button>/);
  assert.match(stories, /data-jluxe-story-next[^>]*>‹<\/button>/);
  assert.doesNotMatch(
    stories,
    /(?:bg-black\/90|bg-white\/(?:30|15)|top-8|bottom-8|pt-5|text-2xl)/,
  );
});

test("homepage frame widths, transparent showcase images and trust icon alias stay correct", () => {
  assert.match(php, /@media\(max-width:639px\)\{\.jluxe-home-section\{margin:20px auto;\}\}/);
  assert.match(php, /\.jluxe-brand-campaigns\{[^}]*width:min\(calc\(100% - 32px\),1288px\)[^}]*padding:0 0 24px/);
  assert.match(php, /\.jluxe-brand-strip\{[^}]*width:min\(calc\(100% - 32px\),1288px\)/);
  assert.match(php, /\.jluxe-category-grid-ref\{[^}]*width:min\(calc\(100% - 32px\),1288px\)/);
  assert.match(php, /\.jluxe-brand-campaigns\{width:100%;[^}]*padding:0 0 16px/);
  assert.match(php, /\.jluxe-brand-strip\{width:calc\(100% - 24px\)/);
  assert.match(php, /\.jluxe-category-grid-ref\{[^}]*width:100%;padding-inline:0/);
  assert.match(php, /\.jluxe-category-showcase-image\{[^}]*background:transparent!important/);
  assert.match(php, /\$icon_alias = array\( 'badge-percent' => 'percent' \)/);
  assert.match(php, /\$icon_alias\[ \$icon_name \] \?\? \$icon_name/);
});
