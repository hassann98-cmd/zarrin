import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const plugin = fs.readFileSync(new URL("../11.php", import.meta.url), "utf8");

test("MU plugin keeps the mobile/web REST routes and does not block WooCommerce order sync", () => {
  for (const [namespace, route] of [
    ["jluxe-admin/v1", "/store-lock"],
    ["jluxe-admin/v1", "/media"],
    ["jluxe-admin/v1", "/register-token"],
    ["jluxe-admin/v1", "/notification-image"],
    ["jluxe/v1", "/order-track"],
  ]) {
    assert.ok(plugin.includes(`'${namespace}'`), `Missing REST namespace ${namespace}`);
    assert.ok(plugin.includes(`'${route}'`), `Missing REST path ${route}`);
  }
  assert.doesNotMatch(plugin, /\/wc\/v3\/orders/i, "the store lock must not block the app's WooCommerce REST order sync");
  assert.match(plugin, /Deliberately do not block WooCommerce REST writes/);
});

test("MU plugin reads credentials from external configuration and contains no embedded private key or token", () => {
  assert.match(plugin, /JLUXE_ADMIN_TOKENS/);
  assert.match(plugin, /getenv\('JLUXE_ADMIN_TOKEN'\)/);
  assert.match(plugin, /JLUXE_FCM_SERVICE_ACCOUNT_FILE/);
  assert.match(plugin, /is_readable\(\$service_account_file\)/);
  assert.doesNotMatch(plugin, /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i);
  assert.doesNotMatch(plugin, /"private_key"\s*:\s*"[^"\\]{20,}/i);
  assert.doesNotMatch(
    plugin,
    /define\s*\(\s*['"]JLUXE_ADMIN_TOKEN['"]\s*,\s*['"][^'"]{16,}['"]\s*\)/i,
  );
});


const themeTracking = fs.readFileSync(new URL("../inc/order-tracking.php", import.meta.url), "utf8");

test("legacy MU tracking helpers are namespaced to avoid fatal collisions with the theme", () => {
  const legacyHelpers = [
    "jluxe_build_customer_data",
    "jluxe_build_items_data",
    "jluxe_build_order_data",
    "jluxe_build_shipping_data",
    "jluxe_build_timeline_data",
    "jluxe_convert_digits_to_en",
    "jluxe_convert_digits_to_fa",
    "jluxe_find_order_by_number",
    "jluxe_format_price",
    "jluxe_get_status_label",
    "jluxe_get_tracking_info",
    "jluxe_gregorian_timestamp_to_jalali_string",
    "jluxe_gregorian_to_jalali",
    "jluxe_normalize_phone_last10",
    "jluxe_track_error_response",
    "jluxe_track_not_found_error",
    "jluxe_track_rate_limit_ok",
  ];

  for (const name of legacyHelpers) {
    const privateName = name.replace(/^jluxe_/, "jluxe_mu_legacy_");
    assert.match(plugin, new RegExp(`function\\s+${privateName}\\s*\\(`), `MU helper ${name} is retained under its private name`);
    assert.doesNotMatch(plugin, new RegExp(`function\\s+${name}\\s*\\(`), `MU plugin must not redeclare theme helper ${name}`);
    assert.match(themeTracking, new RegExp(`function\\s+${name}\\s*\\(`), `theme keeps its current ${name} implementation`);
  }
});
