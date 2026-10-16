// Real WordPress + SQLite + the pinned, built WooCommerce plugin.
// No browser, live gateway, SMTP/SMS provider, production DB, or outbound HTTP.
import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { execFileSync } from "node:child_process";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadNodeRuntime } from "@php-wasm/node";
import { HttpCookieStore } from "@php-wasm/universal";
import { bootWordPressAndRequestHandler } from "@wp-playground/wordpress";

const [major, minor] = process.versions.node.split(".").map(Number);
assert.ok(
  major > 24 || (major === 24 && minor >= 18),
  "WooCommerce integration requires Node >=24.18.",
);

const repo = fileURLToPath(new URL("../../", import.meta.url));
const integrationDir = path.join(repo, "tests/integration");
const archives = path.resolve(
  process.env.ZARRIN_INTEGRATION_DOWNLOADS ||
    path.join(repo, ".cache/integration/archives"),
);
const wooZip = path.resolve(
  process.env.ZARRIN_WOOCOMMERCE_ARCHIVE ||
    path.join(archives, "woocommerce-11.2.0.zip"),
);
const phpVersion = process.env.ZARRIN_TEST_PHP || "8.3";
const root = "/store/";
const encoder = new TextEncoder();
const results = [];
let jar = new HttpCookieStore();
const cookieStore = {
  rememberCookiesFromResponseHeaders: (headers) =>
    jar.rememberCookiesFromResponseHeaders(headers),
  getCookieRequestHeader: () => jar.getCookieRequestHeader(),
};

function check(condition, label) {
  assert.ok(condition, label);
  results.push(label);
  console.log(`PASS: ${label}`);
}

async function verifiedArchive(name, expectedSha256) {
  const bytes = await fs.readFile(path.join(archives, name));
  const actual = createHash("sha256").update(bytes).digest("hex");
  assert.equal(actual, expectedSha256, `${name}: fixture checksum mismatch`);
  return new File([bytes], name);
}

assert.ok(
  await fs.stat(wooZip).then(() => true, () => false),
  `Missing official WooCommerce fixture at ${wooZip}; see the pinned download step in quality.yml.`,
);
const wooBytes = await fs.readFile(wooZip);
const wooSha256 = createHash("sha256").update(wooBytes).digest("hex");
assert.equal(
  wooSha256,
  "14e7539a4988c3654c637804301b430b30530d3421558313949cd2998d57cfa5",
  "WooCommerce 11.2.0 release archive checksum mismatch",
);

const extractedWoo = await fs.mkdtemp(path.join(os.tmpdir(), "zarrin-woo-11.2.0-"));
try {
  execFileSync("unzip", ["-q", wooZip, "-d", extractedWoo]);
  async function findPlugin(directory) {
    for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
      const candidate = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        if (entry.name === "node_modules" || entry.name === ".git") continue;
        const pluginFile = path.join(candidate, "woocommerce.php");
        try {
          const source = await fs.readFile(pluginFile, "utf8");
          if (/Plugin Name:\s*WooCommerce/.test(source)) return candidate;
        } catch {}
        const nested = await findPlugin(candidate);
        if (nested) return nested;
      }
    }
    return null;
  }
  const wooSource = await findPlugin(extractedWoo);
  assert.ok(wooSource, "Official archive must contain the built woocommerce/woocommerce.php plugin");

  const core = await verifiedArchive(
    "wordpress-7.0.zip",
    "b641eae7ea9a78c928596919644963198cd7b6c84e818c07403ec5ea9720ff8c",
  );
  const sqlite = await verifiedArchive(
    "sqlite-built-2.2.23.zip",
    "bef38f839cd7b74ed6dde1ecc26eae92ebe4eac4ab06f43357d31fc9f586c961",
  );
  const handler = await bootWordPressAndRequestHandler({
    createPhpRuntime: () =>
      loadNodeRuntime(phpVersion, { emscriptenOptions: { processId: 1 } }),
    phpVersion,
    siteUrl: "https://shop.test/store/",
    cookieStore,
    maxPhpInstances: 1,
    wordPressZip: core,
    sqliteIntegrationPluginZip: sqlite,
    constants: {
      ZARRIN_INTEGRATION_TEST: true,
      WP_DEBUG: true,
      WP_DEBUG_DISPLAY: false,
      WP_DEBUG_LOG: true,
      WP_HTTP_BLOCK_EXTERNAL: true,
      DISABLE_WP_CRON: true,
      AUTOMATIC_UPDATER_DISABLED: true,
      WP_ENVIRONMENT_TYPE: "local",
    },
    hooks: {
      async beforeDatabaseSetup(php) {
        php.mkdirTree("/wordpress/wp-content/mu-plugins");
        php.writeFile(
          "/wordpress/wp-content/mu-plugins/zarrin-test-safety.php",
          await fs.readFile(path.join(integrationDir, "safety.php")),
        );
      },
    },
  });

  try {
    const php = await handler.getPrimaryPhp();
    const themePath = "/wordpress/wp-content/themes/zarrin";
    const pluginPath = "/wordpress/wp-content/plugins/woocommerce";

    async function copyRuntime(source, target) {
      php.mkdirTree(target);
      for (const entry of await fs.readdir(source, { withFileTypes: true })) {
        if (entry.name.startsWith(".")) continue;
        const sourceFile = path.join(source, entry.name);
        // This production override intentionally removes the alternate-address form.
        // Omit only this file in the disposable integration site so the real upstream
        // WooCommerce template renders its normally hidden required shipping fields.
        if (path.relative(repo, sourceFile) === "woocommerce/checkout/form-shipping.php") continue;
        const destination = `${target}/${entry.name}`;
        if (entry.isDirectory()) {
          await copyRuntime(path.join(source, entry.name), destination);
        } else if (entry.isFile()) {
          php.writeFile(destination, await fs.readFile(path.join(source, entry.name)));
        }
      }
    }

    // Only runtime files enter the disposable PHP filesystem. The release archive,
    // checkout, .git, npm directories and test runner never enter WordPress.
    php.mkdirTree(themePath);
    for (const entry of await fs.readdir(repo, { withFileTypes: true })) {
      if (
        entry.isFile() &&
        (entry.name.endsWith(".php") || entry.name === "style.css")
      ) {
        php.writeFile(
          `${themePath}/${entry.name}`,
          await fs.readFile(path.join(repo, entry.name)),
        );
      }
      if (
        entry.isDirectory() &&
        ["inc", "assets", "woocommerce", "template-parts"].includes(entry.name)
      ) {
        await copyRuntime(path.join(repo, entry.name), `${themePath}/${entry.name}`);
      }
    }
    await copyRuntime(wooSource, pluginPath);

    async function phpJson(code) {
      let response;
      try {
        response = await php.run({
          code: `<?php require '/wordpress/wp-load.php'; $result = (function() { ${code}\n})(); echo '__ZARRIN_WOO_JSON__'.wp_json_encode($result);`,
        });
      } catch (error) {
        const failedResponse = error?.response;
        const debugLog = php.isFile("/wordpress/wp-content/debug.log")
          ? php.readFileAsText("/wordpress/wp-content/debug.log")
          : "";
        const phpErrors = String(failedResponse?.errors || "");
        const fatalLines = `${phpErrors}\n${debugLog}`
          .split(/\r?\n/)
          .filter((line) => /fatal|uncaught|parse error|warning|database error/i.test(line))
          .slice(-8)
          .join(" | ")
          .slice(0, 1400);
        const htmlText = String(failedResponse?.text || "")
          .replace(/<style[\s\S]*?<\/style>/gi, " ")
          .replace(/<script[\s\S]*?<\/script>/gi, " ")
          .replace(/<[^>]*>/g, " ")
          .replace(/&(?:nbsp|amp|lt|gt|quot);/g, " ")
          .replace(/\s+/g, " ")
          .trim()
          .slice(0, 1000);
        throw new Error(
          `PHP fixture execution failed (exit ${failedResponse?.exitCode ?? "unknown"}); PHP/debug: ${fatalLines || "no fatal line captured"}; page: ${htmlText || "no response body"}`,
        );
      }
      assert.ok(
        response.text.startsWith("__ZARRIN_WOO_JSON__"),
        `PHP fixture failed: ${response.text.slice(0, 1200)} ${response.errors}`,
      );
      assert.equal(response.errors, "", "Unexpected PHP stderr");
      return JSON.parse(response.text.slice("__ZARRIN_WOO_JSON__".length));
    }

    async function request(url, { method = "POST", body, form = false } = {}) {
      const response = await handler.request({
        url,
        method,
        headers:
          body === undefined
            ? {}
            : {
                "Content-Type": form
                  ? "application/x-www-form-urlencoded"
                  : "application/json",
              },
        ...(body === undefined
          ? {}
          : {
              body: encoder.encode(
                form
                  ? new URLSearchParams(body).toString()
                  : JSON.stringify(body),
              ),
            }),
      });
      return {
        status: response.httpStatusCode,
        text: response.text,
        headers: response.headers,
        json: () => JSON.parse(response.text),
      };
    }

    const ajax = (body) =>
      request(`${root}wp-admin/admin-ajax.php`, { body, form: true });
    const noStore = (response) => {
      const value = Object.entries(response.headers).find(
        ([key]) => key.toLowerCase() === "cache-control",
      )?.[1];
      return /no-store/i.test(String(value)) && /private/i.test(String(value));
    };
    const getSession = async () => {
      const response = await ajax({ action: "jluxe_session" });
      assert.equal(response.status, 200, response.text);
      assert.ok(noStore(response));
      return response.json().data;
    };

    const activation = await phpJson(`
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
      $activated = activate_plugin('woocommerce/woocommerce.php', '', false, false);
      if (is_wp_error($activated)) throw new RuntimeException($activated->get_error_message());
      return array('active'=>is_plugin_active('woocommerce/woocommerce.php'),'version'=>defined('WC_VERSION')?WC_VERSION:'missing','wpVersion'=>get_bloginfo('version'));
    `);
    check(
      activation.active && activation.version === "11.2.0" && activation.wpVersion === "7.0",
      `Official WooCommerce ${activation.version} activates on real WordPress ${activation.wpVersion}, PHP-WASM ${phpVersion} + SQLite`,
    );

    const fixtures = await phpJson(`
      switch_theme('zarrin');
      update_option('woocommerce_allow_tracking','no');
      update_option('woocommerce_coming_soon','no');
      update_option('woocommerce_store_pages_only','no');
      update_option('woocommerce_currency','USD');
      update_option('woocommerce_default_country','US:CA');
      update_option('woocommerce_ship_to_countries','all');
      update_option('woocommerce_store_address','10 Integration Road');
      update_option('woocommerce_store_city','Test City');
      update_option('woocommerce_store_postcode','90001');
      update_option('woocommerce_ship_to_destination','billing');
      update_option('woocommerce_calc_taxes','no');
      update_option('woocommerce_enable_coupons','yes');
      update_option('woocommerce_enable_guest_checkout','yes');
      update_option('woocommerce_checkout_page_id',0);
      update_option('woocommerce_cart_page_id',0);
      update_option('woocommerce_hold_stock_minutes',5);
      update_option('show_on_front','posts');
      update_option('page_on_front',0);
      update_option('page_for_posts',0);
      update_option('woocommerce_bacs_settings',array('enabled'=>'yes','title'=>'Offline test transfer','description'=>'Sandbox only','instructions'=>''));
      $zone=new WC_Shipping_Zone();
      $zone->set_zone_name('Integration shipping zone');
      $zone_id=$zone->save();
      $zone->add_location('US','country');
      $zone->save();
      $shipping_instance=$zone->add_shipping_method('flat_rate');
      if (!$zone_id || !$shipping_instance) throw new RuntimeException('Could not create an isolated integration shipping rate.');
      update_option('woocommerce_flat_rate_'.$shipping_instance.'_settings',array('enabled'=>'yes','title'=>'پست پیشتاز','tax_status'=>'none','cost'=>'5'));
      global $wp_rewrite;
      $wp_rewrite->set_permalink_structure('');
      flush_rewrite_rules(false);
      $cart_post=get_page_by_path('cart',OBJECT,'page');
      $cart_page=$cart_post?$cart_post->ID:wp_insert_post(array('post_type'=>'page','post_name'=>'cart','post_title'=>'Cart fixture','post_status'=>'publish'));
      wp_update_post(array('ID'=>$cart_page,'post_title'=>'Cart fixture','post_status'=>'publish'));
      $checkout_post=get_page_by_path('checkout',OBJECT,'page');
      $checkout_page=$checkout_post?$checkout_post->ID:wp_insert_post(array('post_type'=>'page','post_name'=>'checkout','post_title'=>'Checkout fixture','post_status'=>'publish'));
      wp_update_post(array('ID'=>$checkout_page,'post_title'=>'Checkout fixture','post_status'=>'publish'));
      update_option('woocommerce_cart_page_id',$cart_page);
      update_option('woocommerce_checkout_page_id',$checkout_page);

      $simple=new WC_Product_Simple();
      $simple->set_name('Integration simple product');
      $simple->set_status('publish');
      $simple->set_catalog_visibility('visible');
      $simple->set_virtual(true);
      $simple->set_regular_price('10.00');
      $simple->set_manage_stock(true);
      $simple->set_stock_quantity(3);
      $simple->set_stock_status('instock');
      $simple->set_total_sales(0);
      $simple_id=$simple->save();

      $sold_product=new WC_Product_Simple();
      $sold_product->set_name('Integration bestseller with sales');
      $sold_product->set_status('publish');
      $sold_product->set_catalog_visibility('visible');
      $sold_product->set_virtual(true);
      $sold_product->set_regular_price('12.00');
      $sold_product->set_total_sales(3);
      $sold_product_id=$sold_product->save();

      $coupon=new WC_Coupon();
      $coupon->set_code('integration-fixed');
      $coupon->set_discount_type('fixed_cart');
      $coupon->set_amount('2.00');
      $coupon->set_product_ids(array($simple_id));
      $coupon->set_usage_limit(10);
      $coupon_id=$coupon->save();

      $attribute_id=wc_create_attribute(array('name'=>'Integration shade','slug'=>'integration_shade','type'=>'select','order_by'=>'menu_order','has_archives'=>false));
      if (is_wp_error($attribute_id)) throw new RuntimeException($attribute_id->get_error_message());
      $taxonomy=wc_attribute_taxonomy_name('integration_shade');
      if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy,array('product'));
      $term=wp_insert_term('Red',$taxonomy);
      if (is_wp_error($term)) throw new RuntimeException($term->get_error_message());
      $persian_term=wp_insert_term('طرح شماره ۳',$taxonomy);
      if (is_wp_error($persian_term)) throw new RuntimeException($persian_term->get_error_message());
      $persian_term_object=get_term((int)$persian_term['term_id'],$taxonomy);
      if (!$persian_term_object || is_wp_error($persian_term_object)) throw new RuntimeException('Persian taxonomy term was not created');
      $attribute=new WC_Product_Attribute();
      $attribute->set_id($attribute_id);
      $attribute->set_name($taxonomy);
      $attribute->set_options(array((int)$term['term_id'],(int)$persian_term['term_id']));
      $attribute->set_position(0);
      $attribute->set_visible(true);
      $attribute->set_variation(true);
      $variable=new WC_Product_Variable();
      $variable->set_name('Integration variable product');
      $variable->set_status('publish');
      $variable->set_catalog_visibility('visible');
      $variable->set_virtual(false);
      $variable->set_attributes(array($taxonomy=>$attribute));
      $variable_id=$variable->save();
      $variation=new WC_Product_Variation();
      $variation->set_parent_id($variable_id);
      $variation->set_attributes(array($taxonomy=>'red'));
      $variation->set_regular_price('15.00');
      $variation->set_virtual(false);
      $variation->set_manage_stock(true);
      $variation->set_stock_quantity(2);
      $variation->set_stock_status('instock');
      $variation_id=$variation->save();
      $persian_variation=new WC_Product_Variation();
      $persian_variation->set_parent_id($variable_id);
      $persian_variation->set_attributes(array($taxonomy=>$persian_term_object->slug));
      $persian_variation->set_regular_price('16.00');
      $persian_variation->set_virtual(false);
      $persian_variation->set_manage_stock(true);
      $persian_variation->set_stock_quantity(2);
      $persian_variation->set_stock_status('instock');
      $persian_variation_id=$persian_variation->save();
      return array('cartPage'=>$cart_page,'checkoutPage'=>$checkout_page,'checkoutSlug'=>get_post_field('post_name',$checkout_page),'simple'=>$simple_id,'soldProduct'=>$sold_product_id,'coupon'=>$coupon_id,'variable'=>$variable_id,'variation'=>$variation_id,'persianVariation'=>$persian_variation_id,'persianSlug'=>$persian_term_object->slug,'taxonomy'=>$taxonomy,'shippingMethods'=>wc_get_shipping_method_count(true),'shippingEnabled'=>wc_shipping_enabled(),'variationNeedsShipping'=>wc_get_product($variation_id)->needs_shipping());
    `);
    const persianSlugIsEncoded = typeof fixtures.persianSlug === "string" && /%[a-f0-9]{2}/i.test(fixtures.persianSlug);
    check(
      fixtures.simple > 0 && fixtures.soldProduct > 0 && fixtures.coupon > 0 && fixtures.variable > 0 && fixtures.variation > 0 && fixtures.persianVariation > 0 && persianSlugIsEncoded && fixtures.checkoutSlug === "checkout" && fixtures.shippingMethods > 0 && fixtures.shippingEnabled && fixtures.variationNeedsShipping,
      `Actual WooCommerce data stores persist Latin and percent-encoded Persian variations, coupon, custom-checkout page and enabled shippable zone method (${JSON.stringify({ shippingMethods: fixtures.shippingMethods, shippingEnabled: fixtures.shippingEnabled, variationNeedsShipping: fixtures.variationNeedsShipping, persianSlug: fixtures.persianSlug })})`,
    );
    const bestsellerRender = await phpJson(`
      $section=array('id'=>'integration-bestsellers','title'=>'Integration bestsellers','source'=>'bestsellers','columns_desktop'=>2,'columns_tablet'=>2,'columns_mobile'=>1,'rows'=>1,'hide_out_of_stock'=>false);
      ob_start();
      jluxe_render_homepage_brick_products($section);
      $html=(string)ob_get_clean();
      return array('soldVisible'=>false!==strpos($html,'Integration bestseller with sales'),'zeroSalesVisible'=>false!==strpos($html,'Integration simple product'));
    `);
    check(
      bestsellerRender.soldVisible && !bestsellerRender.zeroSalesVisible,
      "Homepage bestseller bricks include WooCommerce products with sales and exclude a published product with zero sales",
    );

    const home = await request(root, { method: "GET" });
    const homeText = home.text
      .replace(/<style[\s\S]*?<\/style>/gi, " ")
      .replace(/<script[\s\S]*?<\/script>/gi, " ")
      .replace(/<[^>]*>/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 360);
    const homeDiagnostic = `HTTP ${home.status}; header=${home.text.includes('id="masthead"')}; assets=${home.text.includes("/assets/compiled/")}; body=${homeText}`;
    check(
      home.status === 200 && home.text.includes('id="masthead"') && home.text.includes("/assets/compiled/"),
      `The theme renders on an active WooCommerce storefront (${homeDiagnostic})`,
    );
    const session = await getSession();
    check(
      session.cartNonce.length > 0 && !session.auth.isLoggedIn,
      "Anonymous WooCommerce cart receives a real session cookie and the theme's AJAX nonce",
    );

    let response = await ajax({
      action: "jluxe_cart",
      nonce: "stale-integration-nonce",
      op: "add",
      product_id: String(fixtures.simple),
      quantity: "1",
    });
    check(
      response.status === 403 && response.json().data.code === "jluxe_cart_invalid_nonce",
      "Invalid cart nonce is rejected before WooCommerce mutates its real cart",
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "add",
      product_id: String(fixtures.simple),
      quantity: "2",
    });
    let snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.itemCount === 2 && snapshot.items.length === 1 &&
        snapshot.items[0].productId === fixtures.simple && snapshot.items[0].qty === 2,
      "Theme AJAX adds a real published WooCommerce simple product with the requested quantity",
    );
    const simpleKey = snapshot.items[0].key;

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "add",
      product_id: String(fixtures.variable),
      variation_id: String(fixtures.variation),
      quantity: "1",
      [`attribute_${fixtures.taxonomy}`]: "red",
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.itemCount === 3 && snapshot.items.length === 2 &&
        snapshot.items.some((item) => item.productId === fixtures.variable && item.qty === 1 && item.variation),
      "Theme AJAX resolves a real WooCommerce variation against its parent, taxonomy value and stock",
    );
    const variableKey = snapshot.items.find((item) => item.productId === fixtures.variable).key;

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "add",
      product_id: String(fixtures.variable),
      variation_id: String(fixtures.persianVariation),
      quantity: "1",
      [`attribute_${fixtures.taxonomy}`]: fixtures.persianSlug,
    });
    const persianAddResult = response.json();
    snapshot = persianAddResult.data;
    const persianCartItem = snapshot?.items?.find(
      (item) => item.productId === fixtures.variable && item.variation === 'طرح شماره ۳',
    );
    const persianAddDiagnostic = JSON.stringify({
      status: response.status,
      success: persianAddResult.success,
      message: persianAddResult.data?.message,
      itemCount: snapshot?.itemCount,
      persianCartItem,
      expectedSlug: fixtures.persianSlug,
    });
    check(
      response.status === 200 && persianAddResult.success === true &&
        snapshot?.itemCount === 4 && snapshot?.items?.length === 3 && persianCartItem?.qty === 1,
      `Theme AJAX accepts the percent-encoded Persian taxonomy slug and resolves the matching WooCommerce term (${persianAddDiagnostic})`,
    );
    const persianVariationKey = persianCartItem.key;

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "remove",
      key: persianVariationKey,
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.itemCount === 3 && !snapshot.items.some((item) => item.key === persianVariationKey),
      "Persian-variation regression fixture is removed without changing the pre-existing simple or English-variation cart lines",
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "add",
      product_id: String(fixtures.simple),
      quantity: "2",
    });
    snapshot = response.json().data;
    check(
      response.status !== 200 || response.json().success === false,
      "WooCommerce stock validation prevents adding beyond the simple product's actual stock",
    );
    const afterOversell = await ajax({ action: "jluxe_cart", nonce: session.cartNonce, op: "get" });
    check(
      afterOversell.json().data.itemCount === 3 &&
        afterOversell.json().data.items.find((item) => item.key === simpleKey)?.qty === 2,
      "Rejected over-stock add leaves both WooCommerce cart quantity and other cart lines unchanged",
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "update_qty",
      key: simpleKey,
      qty: "3",
      cart_page: "1",
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.items.find((item) => item.key === simpleKey)?.qty === 3 &&
        snapshot.cartPage?.isEmpty === false &&
        snapshot.cartPage.totalsHtml.includes('class="cart_totals') &&
        snapshot.items.find((item) => item.key === simpleKey)?.lineTotalHtml.includes("woocommerce-Price-amount"),
      "Theme quantity update persists, recalculates real WooCommerce totals and returns the live cart fragment",
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "apply_coupon",
      coupon_code: "integration-fixed",
    });
    const couponResult = response.json();
    snapshot = couponResult.data || {};
    const couponDiagnostic = JSON.stringify({ status: response.status, result: couponResult }).slice(0, 700);
    check(
      response.status === 200 && couponResult.success === true &&
        snapshot.coupons?.some((coupon) => coupon.code === "integration-fixed") &&
        snapshot.discountHtml !== null,
      `Theme coupon action applies a real WooCommerce fixed-cart coupon and returns the current discount (${couponDiagnostic})`,
    );

    const checkoutResolution = await phpJson(`
      return array('theme'=>get_stylesheet(),'themeDirectory'=>get_template_directory(),'templateFile'=>is_file(get_template_directory().'/page-checkout.php'),'checkoutSlug'=>get_post_field('post_name',${fixtures.checkoutPage}),'templateMeta'=>get_post_meta(${fixtures.checkoutPage},'_wp_page_template',true),'configuredPage'=>wc_get_page_id('checkout'));
    `);
    response = await request(`${root}?pagename=checkout`, { method: "GET" });
    const checkoutText = response.text
      .replace(/<style[\s\S]*?<\/style>/gi, " ")
      .replace(/<script[\s\S]*?<\/script>/gi, " ")
      .replace(/<[^>]*>/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 400);
    const selectedTemplate = await phpJson("return get_option('zarrin_test_last_template','');");
    const queryState = await phpJson("return get_option('zarrin_test_last_query',array());");
    const citySelect = response.text.match(/<select\b(?=[^>]*\bid="billing_city")[^>]*>[\s\S]*?<\/select>/)?.[0] || "";
    const cityField = citySelect.match(/<select\b[^>]*>/)?.[0] || "";
    const stateSelect = response.text.match(/<select\b(?=[^>]*\bid="billing_state")[^>]*>[\s\S]*?<\/select>/)?.[0] || "";
    const cityOptionCount = (citySelect.match(/<option\b/g) || []).length;
    const stateOptionCount = (stateSelect.match(/<option\b/g) || []).length;
    const cityIsV1758Select = !!cityField &&
      !/data-jluxe-city-select|disabled="disabled"/.test(cityField) &&
      /<option\b[^>]*value=""[^>]*>(?:ابتدا استان را انتخاب کنید|انتخاب شهرستان)<\/option>/.test(citySelect);
    const cityIsRequired = /<p\b(?=[^>]*\bid="billing_city_field")(?=[^>]*\bclass="[^"]*\bvalidate-required)[^>]*>/.test(response.text);
    const billingPostcodeRow = response.text.match(/<p\b(?=[^>]*\bid="billing_postcode_field")[^>]*>[\s\S]*?<\/p>/)?.[0] || "";
    const billingPostcodeRowTag = billingPostcodeRow.match(/<p\b[^>]*>/)?.[0] || "";
    const billingPostcodeInput = billingPostcodeRow.match(/<input\b(?=[^>]*\bid="billing_postcode")[^>]*>/)?.[0] || "";
    const billingPostcodeOptional = !!billingPostcodeRow &&
      !/\bvalidate-required\b/.test(billingPostcodeRowTag) && !/aria-required="true"/.test(billingPostcodeInput);
    const billingPostcodePlaceholder = billingPostcodeInput.match(/\bplaceholder="([^"]*)"/)?.[1] || "";
    const billingPostcodeHelpRemoved = !/<(?:span|div)\b[^>]*class="[^"]*\bdescription\b/.test(billingPostcodeRow);
    const paymentList = response.text.match(/<ul class="wc_payment_methods payment_methods methods jluxe-payment-method-list"[^>]*>[\s\S]*?<\/ul>/)?.[0] || "";
    const paymentGatewayRow = paymentList.match(/<li class="wc_payment_method jluxe-payment-method-item payment_method_[^"]+">[\s\S]*?<\/li>/)?.[0] || "";
    const paymentGatewayInput = paymentGatewayRow.match(/<input\b[^>]*>/)?.[0] || "";
    const paymentListHasHorizontalGatewayCard = !!paymentGatewayRow &&
      /name="payment_method"/.test(paymentGatewayInput) &&
      /type="radio"/.test(paymentGatewayInput) &&
      /class="[^"]*\bsr-only\b[^"]*"/.test(paymentGatewayInput) &&
      /<label\b[^>]*for="payment_method_[^"]+"[^>]*class="[^"]*jluxe-payment-card/.test(paymentGatewayRow);
    const legacyCityMapLoaded = response.text.includes('"iranCities"') && response.text.includes('"KRH"');
    const checkoutAddressDataRemoved = !response.text.includes('checkoutAddressData');
    const cityLabelPayloadRemoved = !response.text.includes('jluxe_billing_city_label');
    const checkoutStepCount = (response.text.match(/data-jluxe-step="/g) || []).length;
    const baselineProgress = response.text.includes('class="jluxe-checkout-stepper"') &&
      response.text.includes('class="jluxe-checkout-stepper__list"') &&
      response.text.includes('data-jluxe-step="shipping"') && checkoutStepCount === 4;
    const hasPlaceOrderButton = response.text.includes('id="place_order"') &&
      response.text.includes('name="woocommerce_checkout_place_order"');
    const hasCheckoutNonce = response.text.includes('name="woocommerce-process-checkout-nonce"');
    const shipDifferentAddress = response.text.match(/<input\b[^>]*id="ship-to-different-address-checkbox"[^>]*>/);
    const shipsToBillingByDefault = !!shipDifferentAddress && !/\bchecked(?:="checked")?/.test(shipDifferentAddress[0]);
    const hiddenShippingRecipientIsRequired = /<p\b(?=[^>]*id="shipping_first_name_field")(?=[^>]*class="[^"]*validate-required)[^>]*>/.test(response.text);
    const checkoutDiagnostic = `HTTP=${response.status}; v1758CitySelect=${cityIsV1758Select}; cityRequiredRow=${cityIsRequired}; cityOptions=${cityOptionCount}; stateOptions=${stateOptionCount}; postcodeOptional=${billingPostcodeOptional}; postcodePlaceholder=${billingPostcodePlaceholder}; postcodeHelpRemoved=${billingPostcodeHelpRemoved}; cityMap=${legacyCityMapLoaded}; addressJsonRemoved=${checkoutAddressDataRemoved}; cityLabelRemoved=${cityLabelPayloadRemoved}; baselineProgress=${baselineProgress}; checkoutStepCount=${checkoutStepCount}; placeOrder=${hasPlaceOrderButton}; nonce=${hasCheckoutNonce}; hiddenShippingRequired=${hiddenShippingRecipientIsRequired}; shippingToBilling=${shipsToBillingByDefault}; form=${response.text.includes('class="checkout woocommerce-checkout"')}; payment=${response.text.includes("payment_method")}; paymentList=${!!paymentList}; gatewayRow=${!!paymentGatewayRow}; horizontalGatewayCard=${paymentListHasHorizontalGatewayCard}; selectedTemplate=${selectedTemplate}; query=${JSON.stringify(queryState)}; resolution=${JSON.stringify(checkoutResolution)}; empty=${/cart is empty|سبد.*خالی/i.test(checkoutText)}; body=${checkoutText}`;
    check(
      response.status === 200 &&
        response.text.includes('class="checkout woocommerce-checkout"') &&
        response.text.includes("payment_method") && paymentListHasHorizontalGatewayCard && cityIsV1758Select && cityIsRequired && cityOptionCount >= 1 && stateOptionCount >= 2 &&
        legacyCityMapLoaded && checkoutAddressDataRemoved && cityLabelPayloadRemoved && baselineProgress &&
        billingPostcodeOptional && billingPostcodePlaceholder === "برای ارسال پستی، کد پستی الزامی است" && billingPostcodeHelpRemoved && shipsToBillingByDefault && hiddenShippingRecipientIsRequired && hasPlaceOrderButton && hasCheckoutNonce,
      `The real classic checkout renders the required dependent city, native postcode placeholder without a separate help row, a single-column gateway with its real WooCommerce radio, order button and nonce (${checkoutDiagnostic})`,
    );

    const coreCityValidation = await phpJson(`
      $previousPost=$_POST;
      $checkout=WC()->checkout();
      $fieldsProperty=new ReflectionProperty($checkout,'fields');
      $fieldsProperty->setAccessible(true);
      $validator=new ReflectionMethod($checkout,'validate_posted_data');
      $validator->setAccessible(true);
      $stateOptions=WC()->countries->get_states('IR');
      $state=is_array($stateOptions)&&!empty($stateOptions)?(string)array_key_first($stateOptions):'';
      $_POST=array(
        'billing_country'=>'IR',
        'billing_state'=>$state,
        'billing_city'=>'',
        'billing_first_name'=>'Integration',
        'billing_last_name'=>'Customer',
        'billing_phone'=>'09120000000',
        'billing_address_1'=>'10 Integration Road',
        'billing_postcode'=>''
      );
      $fieldsProperty->setValue($checkout,null);
      $emptyFields=$checkout->get_checkout_fields();
      $emptyPosted=$checkout->get_posted_data();
      $emptyErrors=new WP_Error();
      $emptyArgs=array(&$emptyPosted,&$emptyErrors);
      $validator->invokeArgs($checkout,$emptyArgs);
      $emptyCodes=$emptyErrors->get_error_codes();
      $_POST['billing_city']='Test City';
      $fieldsProperty->setValue($checkout,null);
      $cityFields=$checkout->get_checkout_fields();
      $cityOptions=$cityFields['billing']['billing_city']['options']??array();
      $billingPostcodeField=$emptyFields['billing']['billing_postcode']??array();
      $shippingPostcodeField=$emptyFields['shipping']['shipping_postcode']??array();
      $posted=$checkout->get_posted_data();
      $errors=new WP_Error();
      $args=array(&$posted,&$errors);
      $validator->invokeArgs($checkout,$args);
      $validCodes=$errors->get_error_codes();
      $result=array(
        'type'=>$cityFields['billing']['billing_city']['type']??'',
        'required'=>!empty($cityFields['billing']['billing_city']['required']),
        'emptyCityError'=>in_array('billing_city_required',$emptyCodes,true),
        'emptyCityRemainsEmpty'=>($emptyPosted['billing_city']??'')==='',
        'postedCityOptionAdded'=>isset($cityOptions['Test City'])&&$cityOptions['Test City']==='Test City',
        'nonemptyCityError'=>in_array('billing_city_required',$validCodes,true),
        'postcodeRequired'=>!empty($billingPostcodeField['required']),
        'postcodePlaceholder'=>$billingPostcodeField['placeholder']??'',
        'postcodeDescriptionMissing'=>!array_key_exists('description',$billingPostcodeField),
        'emptyPostcodeError'=>in_array('billing_postcode_required',$emptyCodes,true),
        'shippingPostcodeRequired'=>!empty($shippingPostcodeField['required']),
        'shippingPostcodePlaceholder'=>$shippingPostcodeField['placeholder']??'',
        'shippingPostcodeDescriptionMissing'=>!array_key_exists('description',$shippingPostcodeField),
        'noCustomProvinceMismatch'=>!in_array('jluxe_billing_city_province_mismatch',$validCodes,true)
      );
      $fieldsProperty->setValue($checkout,null);
      $_POST=$previousPost;
      return $result;
    `);
    check(
      coreCityValidation.type === "select" && coreCityValidation.required &&
        coreCityValidation.emptyCityError && coreCityValidation.emptyCityRemainsEmpty &&
        coreCityValidation.postedCityOptionAdded && !coreCityValidation.nonemptyCityError &&
        !coreCityValidation.postcodeRequired && !coreCityValidation.emptyPostcodeError &&
        coreCityValidation.postcodePlaceholder === "برای ارسال پستی، کد پستی الزامی است" &&
        coreCityValidation.postcodeDescriptionMissing &&
        !coreCityValidation.shippingPostcodeRequired &&
        coreCityValidation.shippingPostcodePlaceholder === coreCityValidation.postcodePlaceholder &&
        coreCityValidation.shippingPostcodeDescriptionMissing &&
        coreCityValidation.noCustomProvinceMismatch,
      `Real WooCommerce keeps billing/shipping postcode optional with a native placeholder and no help description while still enforcing the dependent city (${JSON.stringify(coreCityValidation)})`,
    );

    const updateOrderReviewNonce = response.text.match(/update_order_review_nonce["']?\s*:\s*["']([^"']+)/)?.[1] || "";
    const initialShippingInput = response.text.match(/<input\b(?=[^>]*\bclass="[^"]*\bshipping_method\b)[^>]*>/)?.[0] || "";
    const selectedShippingRate = initialShippingInput.match(/\bvalue="([^"]+)"/)?.[1] || "";
    const checkoutFields = new URLSearchParams({
      billing_first_name: "Integration",
      billing_last_name: "Customer",
      billing_country: "US",
      billing_state: "CA",
      billing_postcode: "90001",
      billing_city: "Test City",
      billing_address_1: "10 Integration Road",
      payment_method: "bacs",
      "shipping_method[0]": selectedShippingRate,
    }).toString();
    const shippingUpdate = await request(`${root}?wc-ajax=update_order_review`, {
      body: {
        security: updateOrderReviewNonce,
        payment_method: "bacs",
        country: "US",
        state: "CA",
        postcode: "90001",
        city: "Test City",
        address: "10 Integration Road",
        address_2: "",
        s_country: "US",
        s_state: "CA",
        s_postcode: "90001",
        s_city: "Test City",
        s_address: "10 Integration Road",
        s_address_2: "",
        has_full_address: "1",
        post_data: checkoutFields,
        "shipping_method[0]": selectedShippingRate,
      },
      form: true,
    });
    const shippingUpdatePayload = shippingUpdate.json();
    const updatedReview = shippingUpdatePayload.fragments?.[".woocommerce-checkout-review-order-table"] || "";
    const refreshedShippingInput = updatedReview.match(/<input\b(?=[^>]*\bclass="[^"]*\bshipping_method\b)[^>]*>/)?.[0] || "";
    const refreshedShippingRate = refreshedShippingInput.match(/\bvalue="([^"]+)"/)?.[1] || "";
    const refreshedShippingIsUnchecked = !/(?:^|\s)checked(?:\s|=|\/?>)/i.test(refreshedShippingInput);
    const refreshedShippingCard = updatedReview.match(/<label\b(?=[^>]*class="[^"]*\bjluxe-shipping-card\b)[^>]*>[\s\S]*?<\/label>/)?.[0] || "";
    const shippingBadgeAndWooCost = /class="jluxe-shipping-note[^"]*"[^>]*>اقتصادی<\/span>/.test(refreshedShippingCard) &&
      /woocommerce-Price-amount/.test(refreshedShippingCard);
    check(
      shippingUpdate.status === 200 && !!updateOrderReviewNonce && !!selectedShippingRate &&
        shippingUpdatePayload.has_errors === false && refreshedShippingRate === selectedShippingRate &&
        refreshedShippingIsUnchecked && shippingBadgeAndWooCost,
      `The official WooCommerce update_order_review endpoint preserves its unchecked radio and renders the separate shipping badge alongside WooCommerce's real rate cost (${JSON.stringify({ status: shippingUpdate.status, nonce: !!updateOrderReviewNonce, selectedShippingRate, refreshedShippingRate, unchecked: refreshedShippingIsUnchecked, shippingBadgeAndWooCost, fragments: Object.keys(shippingUpdatePayload.fragments || {}) })})`,
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "remove_coupon",
      coupon_code: "integration-fixed",
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true && snapshot.coupons.length === 0,
      "Theme coupon removal clears the real WooCommerce applied-coupon state",
    );

    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "remove",
      key: variableKey,
      cart_page: "1",
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.itemCount === 3 && !snapshot.items.some((item) => item.key === variableKey) &&
        snapshot.cartPage?.isEmpty === false && snapshot.cartPage.totalsHtml.includes('class="cart_totals'),
      "Theme remove action deletes only the requested line and returns current WooCommerce cart totals",
    );
    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "remove",
      key: simpleKey,
      cart_page: "1",
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true && snapshot.itemCount === 0 &&
        snapshot.cartPage?.isEmpty === true &&
        snapshot.cartPage.emptyHtml.includes("data-jluxe-cart-page-content"),
      "Theme remove action returns the empty-cart template after emptying the cart without touching stock",
    );

    const final = await phpJson(`
      $product=wc_get_product(${fixtures.simple});
      $calls=(int)get_option('zarrin_test_sms_calls',0);
      $orders=wc_get_orders(array('limit'=>1,'return'=>'ids'));
      return array('stock'=>$product?$product->get_stock_quantity():null,'smsCalls'=>$calls,'orderCount'=>count($orders),'wcVersion'=>WC_VERSION);
    `);
    check(
      final.stock === 3 && final.smsCalls === 0 && final.orderCount === 0 && final.wcVersion === "11.2.0",
      "Cart-only add/remove creates no order, does not reduce stock or send SMS, and never calls an external payment/provider",
    );
    check(
      await phpJson("return is_wp_error(wp_remote_get('https://example.invalid/blocked'));"),
      "Outbound HTTP remains blocked for the entire real WooCommerce integration test",
    );
    console.log(`WOOCOMMERCE_INTEGRATION_PASSED: ${results.length}`);
    console.log(
      "Scope: WordPress 7.0, PHP-WASM, SQLite, built WooCommerce 11.2.0; simple/variable cart, stock, coupon and checkout render. No order submission, live gateway, SMS, browser, MySQL or production data.",
    );
  } finally {
    await handler[Symbol.asyncDispose]();
  }
} finally {
  await fs.rm(extractedWoo, { recursive: true, force: true });
}
