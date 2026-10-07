import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile(
  new URL("../inc/theme-settings-homepage.php", import.meta.url),
  "utf8",
);

test("brand marquee uses responsive medium logo sources and fits them without cropping", () => {
  const start = source.indexOf("function jluxe_render_homepage_brand_marquee");
  const end = source.indexOf("\nfunction jluxe_render_homepage_blog", start);
  const marquee = source.slice(start, end);
  assert.match(marquee, /wp_get_attachment_image\([\s\S]*?'medium'/);
  assert.match(marquee, /'sizes'\s*=>\s*'\(max-width: 639px\) 78px, 96px'/);
  assert.match(marquee, /'decoding'\s*=>\s*'async'/);
  assert.doesNotMatch(marquee, /wp_get_attachment_image_url\([\s\S]*?'full'/);
  assert.match(
    source,
    /\.jluxe-brand-strip-logo img\{[^}]*width:auto!important;height:auto!important;max-width:96px!important;max-height:40px!important;object-fit:contain!important;object-position:50% 50%!important/s,
  );
  assert.match(source, /@media\(max-width:639px\)/);
  assert.match(source, /\.jluxe-brand-strip-logo\{width:78px;height:32px\}/);
  assert.match(
    source,
    /\.jluxe-brand-strip-logo img\{max-width:78px!important;max-height:32px!important\}/,
  );
});
