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
      update_option('woocommerce_store_address','10 Integration Road');
      update_option('woocommerce_store_city','Test City');
      update_option('woocommerce_store_postcode','90001');
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
      $simple_id=$simple->save();

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
      $attribute=new WC_Product_Attribute();
      $attribute->set_id($attribute_id);
      $attribute->set_name($taxonomy);
      $attribute->set_options(array((int)$term['term_id']));
      $attribute->set_position(0);
      $attribute->set_visible(true);
      $attribute->set_variation(true);
      $variable=new WC_Product_Variable();
      $variable->set_name('Integration variable product');
      $variable->set_status('publish');
      $variable->set_catalog_visibility('visible');
      $variable->set_attributes(array($taxonomy=>$attribute));
      $variable_id=$variable->save();
      $variation=new WC_Product_Variation();
      $variation->set_parent_id($variable_id);
      $variation->set_attributes(array($taxonomy=>'red'));
      $variation->set_regular_price('15.00');
      $variation->set_manage_stock(true);
      $variation->set_stock_quantity(2);
      $variation->set_stock_status('instock');
      $variation_id=$variation->save();
      return array('cartPage'=>$cart_page,'checkoutPage'=>$checkout_page,'checkoutSlug'=>get_post_field('post_name',$checkout_page),'simple'=>$simple_id,'coupon'=>$coupon_id,'variable'=>$variable_id,'variation'=>$variation_id,'taxonomy'=>$taxonomy);
    `);
    check(
      fixtures.simple > 0 && fixtures.coupon > 0 && fixtures.variable > 0 && fixtures.variation > 0 && fixtures.checkoutSlug === "checkout",
      "Actual WooCommerce data stores persist published simple/variable products, a stocked variation, coupon and the custom-checkout page slug",
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
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.items.find((item) => item.key === simpleKey)?.qty === 3,
      "Theme quantity update persists through the real WooCommerce cart and stock checker",
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
    const cityField = response.text.match(/<input\b[^>]*\bid="billing_city"[^>]*>/);
    const cityIsFreeText = !!cityField && /\btype="text"/.test(cityField[0]);
    const accessibleProgress = response.text.includes('<ol class="m-0 flex list-none') && response.text.includes('data-jluxe-step="shipping" aria-current="step"');
    const checkoutDiagnostic = `HTTP ${response.status}; selectedTemplate=${selectedTemplate}; query=${JSON.stringify(queryState)}; resolution=${JSON.stringify(checkoutResolution)}; form=${response.text.includes('class="checkout woocommerce-checkout"')}; payment=${response.text.includes("payment_method")}; cityIsFreeText=${cityIsFreeText}; accessibleProgress=${accessibleProgress}; empty=${/cart is empty|سبد.*خالی/i.test(checkoutText)}; body=${checkoutText}`;
    check(
      response.status === 200 &&
        response.text.includes('class="checkout woocommerce-checkout"') &&
        response.text.includes("payment_method") && cityIsFreeText && accessibleProgress &&
        response.text.includes('data-jluxe-payment-required="1"') &&
        response.text.includes('id="place_order"'),
      `The classic checkout renders an offline gateway, free-entry city, accessible progress, payment state and real submit control (${checkoutDiagnostic})`,
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
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true &&
        snapshot.itemCount === 3 && !snapshot.items.some((item) => item.key === variableKey),
      "Theme remove action deletes only the requested WooCommerce variation cart line",
    );
    response = await ajax({
      action: "jluxe_cart",
      nonce: session.cartNonce,
      op: "remove",
      key: simpleKey,
    });
    snapshot = response.json().data;
    check(
      response.status === 200 && response.json().success === true && snapshot.itemCount === 0,
      "Theme remove action empties the real WooCommerce cart without deleting product inventory",
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
