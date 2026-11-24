import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), "utf8");

test("R402 account order details render gift-wrap data and use emitted button heights", () => {
  const viewOrder = read("woocommerce/myaccount/view-order.php");
  const checkoutFields = read("inc/woocommerce/checkout-fields.php");
  assert.match(viewOrder, /class="inline-flex h-10 items-center[^\"]*"/);
  assert.match(viewOrder, /class="mt-3 flex h-11 w-full items-center[^\"]*"/);
  assert.doesNotMatch(viewOrder, /min-h-10|min-h-11/);
  const productsEnd = viewOrder.indexOf("$jluxe_notes = $order->get_customer_order_notes()");
  const giftWrapCall = viewOrder.indexOf("jluxe_render_order_gift_wrap_details( $order )");
  assert.ok(giftWrapCall >= 0 && giftWrapCall < productsEnd, "gift-wrap details are rendered after the item list");
  assert.match(viewOrder, /function_exists\( 'jluxe_render_order_gift_wrap_details' \)/);
  assert.match(checkoutFields, /inline-flex h-11 items-center justify-center rounded-xl border border-border/);
  assert.doesNotMatch(checkoutFields.match(/function jluxe_render_customer_order_actions[\s\S]*?\n}/)?.[0] ?? "", /min-h-11/);
});

test("R402 thank-you keeps extension hooks, suppresses only Woo's duplicate table, and shows Jalali date and gift-wrap", () => {
  const thankyou = read("woocommerce/checkout/thankyou.php");
  assert.match(thankyou, /jluxe_gregorian_timestamp_to_jalali_string\( \$jluxe_ty_date_created->getTimestamp\(\), 'Y\/m\/d' \)/);
  assert.match(thankyou, /jluxe_fa_digits\( wc_format_datetime\( \$jluxe_ty_date_created \) \)/, "date fallback uses WooCommerce formatting");
  assert.match(thankyou, /jluxe_render_order_gift_wrap_details\( \$order \)/);
  assert.equal((thankyou.match(/remove_action\( 'woocommerce_thankyou', 'woocommerce_order_details_table'/g) ?? []).length, 2);
  assert.equal((thankyou.match(/add_action\( 'woocommerce_thankyou', 'woocommerce_order_details_table'/g) ?? []).length, 2);
  assert.equal((thankyou.match(/do_action\( 'woocommerce_thankyou', \$order->get_id\(\) \)/g) ?? []).length, 2);
  assert.equal((thankyou.match(/do_action\( 'woocommerce_thankyou_' \. \$order->get_payment_method\(\)/g) ?? []).length, 2);
});

test("R403 order-pay styling stays route-isolated and homepage CSS keeps shared growth below 1.5KB", () => {
  const route = read("src/styles/route-cart-checkout.css");
  const accountRoute = read("src/styles/route-account-auth.css");
  assert.match(route, /body\.woocommerce-order-pay #order_review\s*\{[^}]*max-width:\s*760px;[^}]*margin:\s*24px auto 48px;[^}]*padding:\s*0 16px;/s);
  assert.match(route, /body\.woocommerce-order-pay #order_review table\.shop_table/);
  assert.match(route, /body\.woocommerce-order-pay #payment ul\.payment_methods/);
  assert.match(accountRoute, /\.jluxe-order-shipment__tracking-code\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*word-break:\s*break-all;[^}]*font-variant-numeric:\s*tabular-nums;/s);
  assert.match(accountRoute, /\.jluxe-order-shipment__note p:last-child\s*\{[^}]*white-space:\s*pre-line;/s);
  assert.match(accountRoute, /\.jluxe-account-layout \.text-h4\s*\{[^}]*font-size:\s*1\.0625rem;[^}]*line-height:\s*1\.5;/s);
  assert.match(accountRoute, /\.jluxe-order-gift-wrap\s*\{/);

  const manifest = JSON.parse(read("assets/compiled/manifest.json"));
  const mainCss = manifest["src/main.js"].css.find((path) => path.startsWith("assets/main-"));
  const current = readFileSync(new URL(`../assets/compiled/${mainCss}`, import.meta.url));
  const baselineManifest = JSON.parse(execFileSync("git", ["show", "v1.7.401:assets/compiled/manifest.json"], { encoding: "utf8" }));
  const baselineCss = baselineManifest["src/main.js"].css.find((path) => path.startsWith("assets/main-"));
  const baseline = execFileSync("git", ["show", `v1.7.401:assets/compiled/${baselineCss}`]);
  assert.ok(current.length - baseline.length < 1536, `main CSS growth is ${current.length - baseline.length} bytes, below 1.5KB`);
});
