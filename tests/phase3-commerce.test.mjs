import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

test('the current release keeps prior regressions closed and fixes panel and product CTA edges', () => {
  const woo = read('assets/js/woocommerce.js');
  const gallery = woo.slice(woo.indexOf("track.addEventListener('scroll',function(){edges();clearTimeout(timer)", woo.indexOf('/* Mobile gallery:')), woo.indexOf("new MutationObserver(function(){sync();}", woo.indexOf('/* Mobile gallery:')));
  assert.match(gallery, /if\(index<dots\.length\|\|\(!dots\.length&&index===0\)\)\{updateMeter\(index\);if\(dots\[index\]&&index!==Number\(gallery\.getAttribute\('data-jluxe-gallery-current'\)\|\|0\)\)/);
  const checkout = read('assets/js/checkout-login.js');
  assert.match(checkout, /form\.querySelectorAll\("\.jluxe-unknown-postcode-checkbox"\)\.forEach\(function \(checkbox\)/);
  assert.match(checkout, /if \(input && form\.contains\(input\)\) syncUnknownPostcodeFor\(input\)/);
  assert.match(read('src/styles/route-product.css'), /\.single-product \.product\[data-jluxe-layout="default"\] \.woocommerce-product-gallery \{\s*position: relative;/);
  const loader = read('inc/theme-settings-homepage.php');
  const editor = read('inc/homepage/admin-editor.php');
  assert.doesNotMatch(loader, /function jluxe_render_homepage_page\(/);
  assert.match(editor, /function jluxe_render_homepage_page\(/);
  assert.match(loader, /require_once __DIR__ \. '\/homepage\/admin-editor\.php'/);
  const version = JSON.parse(read('package.json')).version;
  for (const path of ['package.json', 'package-lock.json', 'style.css', 'README.md', 'docs/FIXES.fa.md']) {
    assert.ok(read(path).includes(version), `${path} advertises the current ${version} release`);
  }
});

test('shareable carts serialize only bounded IDs and safely merge public purchasable stock', () => {
  const cart = read('inc/cart-ux.php');
  assert.match(cart, /function jluxe_share_cart_encoded\(\): string/);
  assert.match(cart, /\$quantities\[ \$key \] = min\( 99, \$quantities\[ \$key \] \+ \$quantity \)/);
  assert.match(cart, /count\( \$quantities \) >= 20/);
  assert.match(cart, /max\( 1, min\( 99, absint\( \$item\['quantity'\]/);
  assert.match(cart, /function jluxe_parse_share_cart_payload\( string \$encoded \): \?array/);
  assert.match(cart, /count\( \$tokens \) > 20/);
  assert.match(cart, /function jluxe_import_share_cart\( string \$encoded \): array/);
  assert.match(cart, /\$needed = \$entry\['quantity'\] - \$existing_quantity/);
  assert.match(cart, /\$cart->add_to_cart\( \$entry\['product_id'\], \$needed, \$entry\['variation_id'\], \$variation_attributes \)/);
  assert.match(cart, /! jluxe_cart_checkout_option\( 'show_share_cart', true \)/);
  assert.match(cart, /set_customer_session_cookie\( true \)/);
  assert.match(cart, /remove_query_arg\( 'jluxe_share_cart' \)/);
  assert.match(cart, /'shareCartEnabled' => \$share_cart_enabled/);
  assert.match(cart, /'shareCartUrl'\s*=>\s*\$share_cart_url/);
});

test('gift wrap uses server-owned settings and persists safe order/privacy metadata', () => {
  const checkout = read('inc/woocommerce/checkout-fields.php');
  const hooks = read('inc/woocommerce/hooks.php');
  const cart = read('inc/cart-ux.php');
  assert.match(checkout, /function jluxe_get_gift_wrap_config\(\): array/);
  assert.match(checkout, /WC\(\)->cart->add_fee\( \$config\['label'\], \$config\['fee'\], false \)/);
  assert.match(checkout, /function jluxe_checkout_store_gift_wrap/);
  assert.match(checkout, /_jluxe_gift_wrap_note/);
  assert.match(checkout, /function jluxe_checkout_export_gift_wrap/);
  assert.match(checkout, /function jluxe_checkout_erase_gift_wrap/);
  assert.match(hooks, /add_action\( 'woocommerce_checkout_create_order', 'jluxe_checkout_store_gift_wrap'/);
  assert.match(hooks, /add_filter\( 'woocommerce_privacy_export_order_personal_data', 'jluxe_checkout_export_gift_wrap'/);
  assert.match(cart, /'gift_wrap' === \$op/);
  assert.match(cart, /'giftWrap'\s*=>\s*array\( 'enabled' => \(bool\) \$gift_wrap\['enabled'\]/);
  assert.match(checkout, /name="jluxe_gift_wrap_note" maxlength="200"/);
  assert.match(checkout, /name="jluxe_gift_wrap_present" value="1"/);
  assert.match(checkout, /\$data = wp_unslash\( \$_POST \)/);
  assert.match(checkout, /array_key_exists\( 'jluxe_gift_wrap_present', \$data \)/);
});

test('archive product-card color swatches are opt-in controlled, bounded, accessible and route-isolated', () => {
  const card = read('woocommerce/content-product.php');
  const defaults = read('inc/theme-settings.php');
  const sanitize = read('inc/theme-settings-sanitize.php');
  const admin = read('inc/theme-settings-render.php');
  const storefront = read('src/styles/storefront.css');
  const route = read('src/styles/route-cart-checkout.css');
  assert.match(defaults, /'show_color_swatches'\s*=>\s*true/);
  assert.match(sanitize, /'show_color_swatches'\s*=>\s*array_key_exists\( 'show_color_swatches'/);
  assert.match(admin, /name="product_card\[show_color_swatches\]"/);
  assert.match(card, /array_slice\( \$jluxe_all_color_swatches, 0, 4 \)/);
  assert.match(card, /\$jluxe_card_swatches_more = max\( 0, count\( \$jluxe_all_color_swatches \) - count\( \$jluxe_card_swatches \) \)/);
  assert.match(card, /'color' === \$swatch\['type'\]/);
  assert.match(card, /'image' === \$swatch\['type'\]/);
  assert.match(card, /role="img" title="<\?php echo esc_attr\( \$swatch\['label'\] \); \?>" aria-label=/);
  assert.match(storefront, /\.jluxe-product-card-swatches \[role=img\]/);
  assert.match(storefront, /hsl\(var\(--foreground\)\/.18\)/);
  assert.match(storefront, /\[data-jluxe-card-thumbs\]~\.jluxe-product-card-swatches/);
  assert.match(route, /\.jluxe-gift-wrap-card/);
  assert.match(route, /\.jluxe-share-cart-button/);
  assert.match(route, /\.jluxe-gift-wrap-card__description\{margin:8px 0 0;margin-inline-start:32px/);
  assert.match(route, /\.jluxe-gift-wrap-card__note\{[^}]*visibility:hidden/);
  assert.match(route, /:has\(\[data-jluxe-gift-enabled\]:checked\) \.jluxe-gift-wrap-card__note\{[^}]*visibility:visible/);
});

test('share and gift controls provide mobile native sharing, clipboard fallback and debounced checkout refresh', () => {
  const woo = read('assets/js/woocommerce.js');
  const minicart = read('src/islands/MiniCart.js');
  assert.match(woo, /navigator\.share\(\{ title: 'سبد خرید پیشنهادی', url: url \}\)/);
  assert.match(woo, /error\.name !== 'AbortError'/);
  assert.match(woo, /navigator\.clipboard\.writeText\(url\)/);
  assert.match(woo, /setTimeout\(function \(\) \{ request\(box\); \}, 350\)/);
  assert.match(woo, /trigger\('update_checkout'\)/);
  assert.match(woo, /var giftWrap = document\.querySelector\("\[data-jluxe-gift-wrap\]"\)/);
  assert.match(woo, /giftWrap\.style\.display = showShipping \? "" : "none"/);
  assert.match(woo, /delete cartUpdatedData\.cartPage/);
  assert.match(woo, /new CustomEvent\('jluxe:cart-updated', \{ detail: cartUpdatedData \}\)/);
  assert.match(woo, /trigger\('updated_cart_totals'\)/);
  assert.match(minicart, /s\.shareCartEnabled && s\.shareCartUrl/);
  assert.match(minicart, /"data-jluxe-mini-cart-share": ""/);
  assert.match(minicart, /onClick: shareCart/);
  assert.match(minicart, /className: "mt-2 flex h-11 /);
  assert.match(minicart, /async function shareCart\(event\)/);
  assert.match(minicart, /navigator\.share\(\{ title: "سبد خرید پیشنهادی", url \}\)/);
  assert.match(minicart, /navigator\.clipboard\.writeText\(url\)/);
  assert.match(minicart, /document\.execCommand\("copy"\)/);
  assert.match(minicart, /لینک سبد کپی شد ✓/);
  assert.match(minicart, /}, 2200\)/);
  assert.match(minicart, /window\.JLuxeStorefrontUtils\?\.haptic\?\./);
  assert.match(minicart, /"aria-live": "polite"/);
});
