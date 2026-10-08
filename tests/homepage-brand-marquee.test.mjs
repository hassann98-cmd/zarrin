import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile(
  new URL("../inc/theme-settings-homepage.php", import.meta.url),
  "utf8",
);

test("brand marquee uses uncropped responsive logo assets and fits them without cropping", () => {
  assert.match(
    source,
    /jluxe_get_responsive_attachment_image\( \(int\) \$item\['image_id'\], 'jluxe-uncropped-320', '\(max-width: 639px\) 78px, 96px' \)/,
  );
  assert.match(source, /jluxe_responsive_image_attributes\( \$logo_image \)/);
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
