import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
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
  assert.match(js, /jluxe-mobile-gallery-video/);
  assert.match(js, /index<dots\.length/);
});

test('photo settings are rendered on the review-criteria page and upload UI is restricted to raster images', () => {
  const reviews = read('inc/reviews.php');
  const settingsPage = read('inc/theme-settings-render.php');
  const reviewSettingsStart = settingsPage.indexOf('function jluxe_render_review_criteria_page');
  const photoSettingsStart = settingsPage.indexOf('دیدگاه تصویری خریداران');
  const nextFunctionStart = settingsPage.indexOf('\nfunction ', reviewSettingsStart + 1);
  const js = read('assets/js/woocommerce.js');
  assert.ok(photoSettingsStart > reviewSettingsStart && photoSettingsStart < nextFunctionStart, 'photo controls belong to the review-criteria settings page');
  assert.match(reviews, /accept="image\/jpeg,image\/png,image\/webp"/);
  assert.match(reviews, /wp_verify_nonce\(\s*\$nonce,\s*'jluxe_review_photos'/);
  assert.match(reviews, /wp_check_filetype_and_ext/);
  assert.match(reviews, /getimagesize/);
  assert.match(reviews, /wp_get_image_editor/);
  assert.match(js, /\['image\/jpeg', 'image\/png', 'image\/webp'\]/);
  assert.match(js, /new DataTransfer\(\)/);
  assert.match(js, /URL\.revokeObjectURL/);
});

test('new visual styles stay in route-product CSS; initial main CSS byte count is unchanged', () => {
  const route = read('src/styles/route-product.css');
  const manifest = JSON.parse(read('assets/compiled/manifest.json'));
  const mainCss = manifest['src/main.js'].css.find((name) => name.startsWith('assets/main-'));
  const currentBytes = statSync(new URL(`../assets/compiled/${mainCss}`, import.meta.url)).size;
  const baseline = Number(execFileSync('git', ['show', 'HEAD:assets/compiled/assets/main-cTRK1YX2.css'], { encoding: 'buffer' }).length);
  assert.match(route, /\.jluxe-product-video-modal/);
  assert.match(route, /\.jluxe-review-photo-lightbox/);
  assert.equal(currentBytes, baseline, 'main stylesheet must not grow by even one byte');
});
