import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

test('product video remains a user-triggered lazy media element across all layouts', () => {
  const reference = read('woocommerce/content-single-product-reference.php');
  const classic = read('woocommerce/content-single-product-classic.php');
  const standard = read('woocommerce/content-single-product.php');
  const js = read('assets/js/woocommerce.js');
  for (const source of [reference, classic, standard]) {
    assert.doesNotMatch(source, /<video\b[^>]*\bsrc\s*=/i, 'server output must not include a video src');
  }
  for (const source of [reference, classic]) {
    assert.match(source, /data-jluxe-product-video-trigger/);
    assert.match(source, /data-video-src=/);
  }
  assert.match(read('inc/woocommerce/product-single.php'), /data-jluxe-product-video-trigger/);
  assert.match(reference, /fetchpriority="high"/);
  assert.match(classic, /fetchpriority="high"/);
  assert.match(js, /preload="metadata"/);
  assert.match(js, /video\.pause\(\); video\.removeAttribute\('src'\); video\.load\(\)/);
  assert.match(standard, /ob_start\(\);\s*woocommerce_show_product_images\(\);/);
  assert.match(standard, /strrpos\(\s*\$jluxe_default_gallery_html,\s*'<\/div>'\s*\)/, 'default-layout trigger is injected before the gallery closing wrapper');
  assert.match(standard, /substr_replace\(\s*\$jluxe_default_gallery_html,\s*\$jluxe_default_video_trigger/);
  assert.doesNotMatch(read('inc/woocommerce/product-single.php'), /jluxe-product-video-pill--below/);
  const route = read('src/styles/route-product.css');
  assert.match(route, /\.jluxe-product-video-pill\{[^}]*inset-inline-start:12px;inset-inline-end:auto;bottom:12px/);
  assert.match(route, /@media\(min-width:768px\)\{\.jluxe-product-video-pill:not\(\.jluxe-product-video-pill--below\)\{top:12px/);
  assert.match(js, /jluxe-mobile-gallery-video/);
  assert.match(js, /if\(hasVideo&&index===\(dots\.length\|\|1\)\)\{updateMeter\(index\);return;\}/, 'video-only slide updates the meter without indexing a missing WooCommerce dot');
  assert.match(js, /Number\(gallery\.getAttribute\('data-jluxe-gallery-current'\) \|\| 0\)!==0/, 'browsing away from the first image cannot replace its source');
});

test('photo settings are rendered on the review-criteria page and upload UI is restricted to raster images', () => {
  const reviews = read('inc/reviews.php');
  const settingsPage = read('inc/theme-settings-render.php');
  const reviewSettingsStart = settingsPage.indexOf('function jluxe_render_review_criteria_page');
  const photoSettingsStart = settingsPage.indexOf('دیدگاه تصویری خریداران');
  const nextFunctionStart = settingsPage.indexOf('\nfunction ', reviewSettingsStart + 1);
  const js = read('assets/js/woocommerce.js');
  const uploadStart = js.indexOf("document.addEventListener('change', function (event)");
  const uploadHandlers = js.slice(uploadStart, js.indexOf('document.addEventListener("submit"', uploadStart));
  assert.ok(photoSettingsStart > reviewSettingsStart && photoSettingsStart < nextFunctionStart, 'photo controls belong to the review-criteria settings page');
  assert.match(reviews, /accept="image\/jpeg,image\/png,image\/webp"/);
  assert.match(reviews, /wp_verify_nonce\(\s*\$nonce,\s*'jluxe_review_photos'/);
  assert.match(reviews, /wp_check_filetype_and_ext/);
  assert.match(reviews, /'jpg\|jpeg\|jpe'\s*=>\s*'image\/jpeg'/);
  assert.match(reviews, /in_array\(\s*\$mime,\s*\$allowed,\s*true\s*\)/, 'accepted MIME is checked strictly against WordPress values');
  assert.match(reviews, /getimagesize/);
  assert.match(reviews, /wp_get_image_editor/);
  assert.match(reviews, /if \( ! \$url \|\| ! is_string\( \$image \) \|\| '' === \$image \) \{ continue; \}/);
  assert.match(js, /\['image\/jpeg', 'image\/png', 'image\/webp'\]/);
  assert.equal((uploadHandlers.match(/new DataTransfer\(\)/g) || []).length, 2, 'each upload change/removal builds one file transfer');
  assert.doesNotMatch(uploadHandlers, /input\.value\s*=\s*''|transferAgain|fakeEvent/);
  assert.match(js, /errorBox\.textContent = ''; errorBox\.hidden = true;/);
  assert.match(js, /URL\.revokeObjectURL/);
});

test('review photo lightbox locks and restores scrolling, uses Persian counts, and points arrows for RTL', () => {
  const js = read('assets/js/woocommerce.js');
  const lightbox = js.slice(js.indexOf('/* Approved customer-review photo lightbox'), js.indexOf('/* Mobile gallery:'));
  assert.match(lightbox, /previousOverflow = document\.body\.style\.overflow; document\.body\.style\.overflow = 'hidden'/);
  assert.match(lightbox, /document\.body\.style\.overflow = previousOverflow/);
  assert.match(lightbox, /persianDigits\(index \+ 1\).*persianDigits\(group\.length\)/);
  assert.match(lightbox, /data-photo-prev[^>]*>›/);
  assert.match(lightbox, /data-photo-next[^>]*>‹/);
});

test('new visual styles stay in route-product CSS; initial main CSS byte count is unchanged', () => {
  const route = read('src/styles/route-product.css');
  const manifest = JSON.parse(read('assets/compiled/manifest.json'));
  const mainCss = manifest['src/main.js'].css.find((name) => name.startsWith('assets/main-'));
  const currentPath = new URL(`../assets/compiled/${mainCss}`, import.meta.url);
  const currentBytes = readFileSync(currentPath);
  const baseline = execFileSync('git', ['show', 'HEAD:assets/compiled/assets/main-cTRK1YX2.css']);
  assert.match(route, /\.jluxe-product-video-modal/);
  assert.match(route, /\.jluxe-review-photo-lightbox/);
  assert.deepEqual(currentBytes, baseline, 'main stylesheet must remain byte-identical');
});
