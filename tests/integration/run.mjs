// Real WordPress + SQLite, not the isolated WP/Woo doubles in tests/php.
// No HTTP listener, host filesystem mount, production database or outbound network.
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createHash } from "node:crypto";
import assert from "node:assert/strict";
import { loadNodeRuntime } from "@php-wasm/node";
import { HttpCookieStore } from "@php-wasm/universal";
import { bootWordPressAndRequestHandler } from "@wp-playground/wordpress";

const [major, minor] = process.versions.node.split(".").map(Number);
assert.ok(
  major > 24 || (major === 24 && minor >= 18),
  "Integration SDK requires Node >=24.18 (the normal frontend build still supports Node 22.12+).",
);
const repo = fileURLToPath(new URL("../../", import.meta.url));
const archives = path.resolve(
  process.env.ZARRIN_INTEGRATION_DOWNLOADS ||
    path.join(repo, ".cache/integration/archives"),
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
const freshVisitor = () => {
  jar = new HttpCookieStore();
};
function check(condition, label) {
  assert.ok(condition, label);
  results.push(label);
  console.log(`PASS: ${label}`);
}
async function archive(name, digest) {
  const bytes = await fs.readFile(path.join(archives, name));
  assert.equal(
    createHash("sha256").update(bytes).digest("hex"),
    digest,
    `${name}: untrusted fixture; run prepare:fixtures`,
  );
  return new File([bytes], name);
}
const handler = await bootWordPressAndRequestHandler({
  createPhpRuntime: () =>
    loadNodeRuntime(phpVersion, { emscriptenOptions: { processId: 1 } }),
  phpVersion,
  siteUrl: "https://shop.test/store/",
  cookieStore,
  maxPhpInstances: 1,
  wordPressZip: await archive(
    "wordpress-6.9.zip",
    "811bc36f11d587d8ae330b37e783f4b2d1e876c83f35409181e28613fc4dfb2a",
  ),
  sqliteIntegrationPluginZip: await archive(
    "sqlite-built-2.2.23.zip",
    "bef38f839cd7b74ed6dde1ecc26eae92ebe4eac4ab06f43357d31fc9f586c961",
  ),
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
        await fs.readFile(new URL("safety.php", import.meta.url)),
      );
    },
  },
});

try {
  const php = await handler.getPrimaryPhp();
  const themePath = "/wordpress/wp-content/themes/zarrin";
  // Copy only the theme runtime into WASM memory. Never mount the checkout or its
  // .git, credentials, dependencies, fixtures, source archives or test scripts.
  async function copyRuntime(source, target) {
    php.mkdirTree(target);
    for (const entry of await fs.readdir(source, { withFileTypes: true })) {
      if (entry.name.startsWith(".")) continue;
      if (entry.isDirectory())
        await copyRuntime(
          path.join(source, entry.name),
          `${target}/${entry.name}`,
        );
      else if (entry.isFile())
        php.writeFile(
          `${target}/${entry.name}`,
          await fs.readFile(path.join(source, entry.name)),
        );
    }
  }
  php.mkdirTree(themePath);
  for (const entry of await fs.readdir(repo, { withFileTypes: true })) {
    if (
      entry.isFile() &&
      (entry.name.endsWith(".php") || entry.name === "style.css")
    )
      php.writeFile(
        `${themePath}/${entry.name}`,
        await fs.readFile(path.join(repo, entry.name)),
      );
    if (
      entry.isDirectory() &&
      ["inc", "assets", "woocommerce", "template-parts"].includes(entry.name)
    )
      await copyRuntime(
        path.join(repo, entry.name),
        `${themePath}/${entry.name}`,
      );
  }
  async function phpJson(code) {
    const response = await php.run({
      code: `<?php require '/wordpress/wp-load.php'; $result = (function() { ${code}\n})(); echo '__ZARRIN_JSON__'.wp_json_encode($result);`,
    });
    // PHP-WASM can report exit=0 after a fatal: insist on the completion marker.
    assert.ok(
      response.text.startsWith("__ZARRIN_JSON__"),
      `PHP fixture failed: ${response.text.slice(0, 1000)} ${response.errors}`,
    );
    assert.equal(response.errors, "", "Unexpected PHP stderr");
    return JSON.parse(response.text.slice("__ZARRIN_JSON__".length));
  }
  async function request(
    url,
    { method = "POST", body, nonce, form = false } = {},
  ) {
    const headers = {};
    if (body !== undefined)
      headers["Content-Type"] = form
        ? "application/x-www-form-urlencoded"
        : "application/json";
    if (nonce) headers["X-WP-Nonce"] = nonce;
    const response = await handler.request({
      url,
      method,
      headers,
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
  const rest = (route, body, options = {}) =>
    request(`${root}?rest_route=${encodeURIComponent(route)}`, {
      body,
      ...options,
    });
  const ajax = (body, options = {}) =>
    request(`${root}wp-admin/admin-ajax.php`, { body, form: true, ...options });
  const noStore = (response) => {
    const cache = Object.entries(response.headers).find(
      ([key]) => key.toLowerCase() === "cache-control",
    )?.[1];
    return /no-store/i.test(String(cache)) && /private/i.test(String(cache));
  };
  const getSession = async () => {
    const response = await ajax({ action: "jluxe_session" });
    assert.equal(response.status, 200);
    assert.ok(noStore(response));
    return response.json().data;
  };
  const login = async (user) => {
    const response = await rest("/jluxe/v1/auth/login", {
      login: user,
      password: "integration-only-password-2026",
    });
    assert.equal(response.status, 200, response.text);
    assert.equal(response.json().success, true);
    check(
      noStore(response),
      "Authentication response must be private/no-store",
    );
    return getSession();
  };

  const versions = await phpJson(
    `switch_theme('zarrin'); return array('wp'=>get_bloginfo('version'),'php'=>PHP_VERSION,'theme'=>get_stylesheet());`,
  );
  check(
    versions.wp === "6.9" && versions.theme === "zarrin",
    `Real WordPress ${versions.wp} + PHP ${versions.php} activates the theme`,
  );
  const users = await phpJson(`
    $users = array();
    foreach (array('customer'=>'subscriber','other'=>'subscriber','staff'=>'administrator','billing'=>'subscriber') as $name=>$role) {
      $id=wp_insert_user(array('user_login'=>'integration-'.$name,'user_pass'=>'integration-only-password-2026','user_email'=>$name.'@example.invalid','display_name'=>'Private fixture '.$name,'role'=>$role));
      if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
      $users[$name]=$id;
    }
    update_user_meta($users['customer'],'jluxe_phone','9120000000');
    update_user_meta($users['staff'],'jluxe_phone','9120000001');
    update_user_meta($users['billing'],'billing_phone','09120000002');
    update_option('users_can_register',0);
    update_option('timezone_string','Asia/Tehran');
    $settings=jluxe_theme_settings_defaults();
    $settings['sms']['enabled']=true;
    $settings['sms']['provider']='kavenegar';
    update_option(JLUXE_SETTINGS_OPTION,$settings);
    update_option(JLUXE_SMS_API_KEY_OPTION,'integration-only-no-real-provider');
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('');
    flush_rewrite_rules(false);
    $users['trackingPage']=wp_insert_post(array('post_type'=>'page','post_name'=>'track-order','post_title'=>'Integration tracking fixture','post_status'=>'publish'));
    return $users;
  `);
  const home = await request(root, { method: "GET" });
  check(
    home.status === 200 &&
      home.text.includes("/assets/compiled/") &&
      !home.text.includes("Private fixture"),
    "Theme renders anonymously without WooCommerce or cached customer identity",
  );
  check(
    await phpJson(
      "return is_wp_error(wp_remote_get('https://example.invalid/blocked')) && false === wp_mail('nobody@example.invalid','blocked','blocked');",
    ),
    "Real WordPress HTTP and mail boundaries are blocked by the test-only safety plugin",
  );

  const trackingPage = await request(`${root}?page_id=${users.trackingPage}`, {
    method: "GET",
  });
  if (
    trackingPage.status !== 200 ||
    !trackingPage.text.includes("window.JLuxeOrderTracking")
  )
    console.error(
      "Tracking template response:",
      trackingPage.status,
      trackingPage.headers.location,
      trackingPage.text.slice(-700),
    );
  check(
    trackingPage.status === 200 &&
      trackingPage.text.includes("assets/js/order-tracking.js") &&
      trackingPage.text.includes("window.JLuxeOrderTracking") &&
      !trackingPage.text.includes("Private fixture"),
    "Real tracking page template enqueues its maintained client and public server-generated endpoints without WooCommerce",
  );
  {
    // Keep the fixture response local to this assertion.
    const error = await rest("/JLUXE/v1/auth/login", {
      login: [],
      password: "ignored",
    });
    check(
      error.status === 400 && noStore(error),
      "Case-insensitive core REST route matching cannot bypass authentication privacy headers",
    );
  }

  let session = await getSession();
  check(
    !session.auth.isLoggedIn &&
      session.restNonce === "" &&
      session.cartNonce.length > 0 &&
      session.auth.email === "",
    "Guest AJAX bootstrap has a fresh cart nonce, no REST identity or private profile",
  );
  const sessionGet = await request(
    `${root}wp-admin/admin-ajax.php?action=jluxe_session`,
    { method: "GET" },
  );
  check(
    sessionGet.status === 405 && noStore(sessionGet),
    "Session bootstrap rejects GET with a private/no-store 405",
  );
  let response = await ajax({
    action: "jluxe_cart",
    nonce: "stale",
    op: "add",
    product_id: "1",
  });
  check(
    response.status === 403 &&
      response.json().data.code === "jluxe_cart_invalid_nonce" &&
      noStore(response),
    "Cart rejects a stale nonce before reaching unavailable WooCommerce and never caches the failure",
  );
  response = await ajax({
    action: "jluxe_cart",
    nonce: "stale",
    op: "add",
    product_id: "51",
    "add-to-cart": "51",
  });
  check(
    response.status === 403 &&
      (await phpJson(
        "return get_option('zarrin_test_native_cart_trigger','not-observed');",
      )) === "absent",
    "Real wp_loaded ordering clears the native auto-add trigger before priority 20 and before rejecting a bad cart nonce",
  );
  response = await ajax({
    action: "jluxe_cart",
    nonce: session.cartNonce,
    op: "get",
  });
  check(
    response.status === 503 && noStore(response),
    "A valid cart nonce reaches a controlled 503, not a PHP fatal, without WooCommerce",
  );

  response = await rest("/jluxe/v1/order-track", {
    order_number: "51",
    phone: "09120000000",
  });
  check(
    response.status === 503 &&
      noStore(response) &&
      response.json().success === false,
    "Tracking without WooCommerce returns a private/no-store JSON 503 (the reproduced fatal is fixed)",
  );
  for (const body of [
    {},
    { order_number: "51", phone: [] },
    { order_number: "1".repeat(101), phone: "09120000000" },
  ]) {
    response = await rest("/jluxe/v1/order-track", body);
    if (response.status !== 400 || !noStore(response))
      console.error(
        "Invalid input response:",
        response.status,
        response.text.slice(0, 500),
        "noStore:",
        noStore(response),
      );
    check(
      response.status === 400 && noStore(response),
      "Core REST argument validation is bounded and also receives tracking privacy headers",
    );
  }
  for (const [route, body] of [
    ["/jluxe/v1/auth/login", { login: [], password: "ignored" }],
    ["/jluxe/v1/auth/otp-request", { phone: ["09120000000"] }],
    ["/jluxe/v1/auth/otp-verify", { phone: "09120000000", code: ["123456"] }],
    [
      "/jluxe/v1/assistant/ticket",
      { name: [], contact: "test", message: "test" },
    ],
    ["/jluxe/v1/assistant", { message: [] }],
    [
      "/jluxe/v1/assistant",
      { messages: [{ role: "user", content: ["nested array"] }] },
    ],
    [
      "/jluxe/v1/assistant",
      {
        messages: Array.from({ length: 13 }, () => ({
          role: "user",
          content: "test",
        })),
      },
    ],
  ]) {
    response = await rest(route, body);
    check(
      response.status === 400 &&
        response.json().code === "rest_invalid_param" &&
        noStore(response),
      `Real REST schema rejects malformed/oversized input before ${route} callback`,
    );
  }
  response = await request(
    `${root}?rest_route=/jluxe/v1/order-track&order_number=51&phone=09120000000`,
    { method: "GET" },
  );
  check(
    response.status === 404 && noStore(response),
    "Legacy query-string tracking GET cannot disclose an order and has no-store headers",
  );
  await phpJson(
    "global $wp_rewrite; $wp_rewrite->set_permalink_structure('/%postname%/'); flush_rewrite_rules(false); return true;",
  );
  response = await request(`${root}wp-json/jluxe/v1/order-track`, {
    body: { order_number: "51", phone: "09120000000" },
  });
  check(
    response.status === 503 && noStore(response),
    "Tracking route also works with pretty REST URLs under a WordPress subdirectory",
  );

  const sql = await phpJson(`
    global $wpdb;
    $first=jluxe_security_lock('integration-lock');
    $duplicate=jluxe_security_lock('integration-lock');
    $wpdb->update($wpdb->options,array('option_value'=>(time()-1).':expired'),array('option_name'=>$first[0]));
    $replacement=jluxe_security_lock('integration-lock');
    jluxe_security_unlock($first);
    $held=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$replacement[0]));
    jluxe_security_unlock($replacement);
    $record=array('hash'=>'test-record','attempts'=>0);
    update_option('zarrin-test-one-use',$record,false);
    get_option('zarrin-test-one-use');
    $consumed=jluxe_consume_option('zarrin-test-one-use',$record);
    return array('exclusive'=>$first && null===$duplicate,'replacement'=>$held===$replacement[1],
      'consumption'=>$consumed && !jluxe_consume_option('zarrin-test-one-use',$record) && false===get_option('zarrin-test-one-use'),
      'limit'=>array(jluxe_security_rate_limit('integration','test',2,600),jluxe_security_rate_limit('integration','test',2,600),jluxe_security_rate_limit('integration','test',2,600)));
  `);
  check(
    sql.exclusive && sql.replacement,
    "Real wpdb/SQLite lock claims are exclusive sequentially; an old owner cannot release its replacement",
  );
  check(
    sql.consumption,
    "Real options-table compare-and-delete consumes once and invalidates WordPress's warmed option cache",
  );
  check(
    JSON.stringify(sql.limit) === "[true,true,false]",
    "Real WordPress transient-backed rate limit admits two requests then rejects the third",
  );

  response = await rest("/jluxe/v1/auth/register", {
    username: "newfixture",
    email: "new@example.invalid",
    password: "integration-password",
  });
  check(
    response.status === 403 &&
      response.json().code === "jluxe_registration_disabled",
    "Real registration policy prevents account creation while disabled",
  );
  response = await rest("/jluxe/v1/auth/login", {
    login: "integration-customer",
    password: "wrong",
  });
  check(
    response.status === 401 &&
      response.json().code === "jluxe_auth_login_failed",
    "Real WordPress password authentication rejects the wrong password",
  );
  session = await login("integration-customer");
  check(
    session.auth.isLoggedIn &&
      session.auth.email === "customer@example.invalid" &&
      session.restNonce.length > 0,
    "Password login sets a real WordPress cookie; private bootstrap returns the current identity and REST nonce",
  );
  const privatePage = await request(root, { method: "GET" });
  check(
    privatePage.status === 200 && noStore(privatePage),
    "A logged-in WordPress page is explicitly private/no-store too",
  );
  response = await rest("/wp/v2/users/me", undefined, { method: "GET" });
  check(
    response.status === 401,
    "A WordPress cookie alone cannot supply REST identity without a REST nonce",
  );
  response = await rest("/wp/v2/users/me", undefined, {
    method: "GET",
    nonce: session.restNonce,
  });
  check(
    response.status === 200 && response.json().id === users.customer,
    "Cookie plus fresh REST nonce proves the actual logged-in WordPress identity",
  );
  response = await rest(
    "/jluxe/v1/order-track",
    { order_number: "51", phone: "09120000000" },
    { nonce: "expired" },
  );
  check(
    response.status === 403 &&
      response.json().code === "rest_cookie_invalid_nonce" &&
      noStore(response),
    "Even core REST authentication errors for tracking remain private/no-store",
  );
  freshVisitor();
  response = await rest("/jluxe/v1/auth/otp-link", {
    phone: "09120000000",
    code: "123456",
  });
  check(
    response.status === 401,
    "Real REST permission callback blocks anonymous phone linking",
  );

  const sendOtp = async (phone, intent = "login", nonce) => {
    const response = await rest(
      "/jluxe/v1/auth/otp-request",
      { phone, intent },
      { nonce },
    );
    assert.equal(response.status, 200, response.text);
    assert.equal(response.json().sent, true);
    const outbox = await phpJson(
      "return get_option('zarrin_test_sms_outbox');",
    );
    assert.match(outbox.token, /^[0-9]{6}$/);
    return outbox.token;
  };
  let code = await sendOtp("۰۹۱۲۰۰۰۰۰۰۰");
  const record = await phpJson(
    "return get_option(jluxe_otp_key('9120000000')); ",
  );
  check(
    record.hash.length === 64 &&
      Object.values(record).every((value) => value !== code),
    "An actual OTP request stores only its HMAC in the real WordPress options table (SMS delivery mocked)",
  );
  const persian = code.replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[digit]);
  response = await rest("/jluxe/v1/auth/otp-verify", {
    phone: "۰۹۱۲۰۰۰۰۰۰۰",
    code: persian,
  });
  session = await getSession();
  check(
    response.status === 200 &&
      response.json().success &&
      session.auth.email === "customer@example.invalid",
    "Persian-digit OTP verification consumes the challenge and establishes a real customer cookie (mock SMS)",
  );
  freshVisitor();
  response = await rest("/jluxe/v1/auth/otp-verify", {
    phone: "09120000000",
    code,
  });
  check(
    response.status >= 400 && !(await getSession()).auth.isLoggedIn,
    "A consumed real database challenge cannot be replayed into another WordPress cookie",
  );
  code = await sendOtp("09120000001");
  response = await rest("/jluxe/v1/auth/otp-verify", {
    phone: "09120000001",
    code,
  });
  check(
    response.status === 403 && !(await getSession()).auth.isLoggedIn,
    "A real WordPress administrator cannot use the customer OTP password bypass",
  );
  code = await sendOtp("09120000002");
  response = await rest("/jluxe/v1/auth/otp-verify", {
    phone: "09120000002",
    code,
  });
  check(
    response.status >= 400 &&
      response.json().code === "jluxe_sms_link_required" &&
      !(await getSession()).auth.isLoggedIn,
    "Real billing_phone metadata alone never authorizes login or silently binds an account",
  );
  session = await login("integration-billing");
  code = await sendOtp("09120000002", "link", session.restNonce);
  response = await rest(
    "/jluxe/v1/auth/otp-link",
    { phone: "09120000002", code },
    { nonce: session.restNonce },
  );
  const linked = await phpJson(
    `return get_user_meta(${users.billing}, 'jluxe_phone', true);`,
  );
  check(
    response.status === 200 &&
      response.json().success &&
      linked === "9120000002",
    "An authenticated real customer can explicitly verify and persist a phone binding (mock SMS)",
  );

  freshVisitor();
  code = await sendOtp("09120000000");
  let wrongAttemptsRejected = true;
  for (let attempt = 1; attempt <= 5; attempt++) {
    response = await rest("/jluxe/v1/auth/otp-verify", {
      phone: "09120000000",
      code: "000000",
    });
    wrongAttemptsRejected &&= response.status === (attempt === 5 ? 429 : 400);
  }
  check(
    wrongAttemptsRejected &&
      false ===
        (await phpJson("return get_option(jluxe_otp_key('9120000000'));")),
    "Five wrong OTP attempts delete the actual database challenge and return 429 on exhaustion",
  );
  response = await rest("/jluxe/v1/auth/otp-verify", {
    phone: "09120000000",
    code,
  });
  check(
    response.status >= 400 && !(await getSession()).auth.isLoggedIn,
    "The correct code cannot rescue a challenge exhausted in real WordPress",
  );
  const sentBefore = await phpJson(
    "update_option('zarrin_test_sms_mode','reject'); return (int)get_option('zarrin_test_sms_calls',0);",
  );
  let sendsRejected = true;
  for (let attempt = 0; attempt < 3; attempt++) {
    response = await rest("/jluxe/v1/auth/otp-request", {
      phone: "09120000003",
    });
    sendsRejected &&= response.status === 502 && noStore(response);
  }
  check(
    sendsRejected &&
      false ===
        (await phpJson("return get_option(jluxe_otp_key('9120000003'));")),
    "Mock provider rejection leaves no usable OTP and returns private failures through real REST",
  );
  response = await rest("/jluxe/v1/auth/otp-request", { phone: "09120000003" });
  const sentAfter = await phpJson(
    "return (int)get_option('zarrin_test_sms_calls',0);",
  );
  check(
    response.status === 429 && sentAfter - sentBefore === 3,
    "Failed deliveries consume the real WordPress delivery budget; a fourth attempt never calls even the mock provider",
  );

  // Exercise the schema using real WordPress capabilities, not fake current_user_can.
  const policy = await phpJson(`
    wp_set_current_user(${users.customer});
    $denied=false;
    try { jluxe_sanitize_settings_payload(array('custom_code'=>array('js'=>'alert(1)','css'=>'')),jluxe_theme_settings_defaults()); }
    catch (InvalidArgumentException $e) { $denied=true; }
    wp_set_current_user(${users.staff});
    $settings=jluxe_theme_settings_defaults();
    $settings['custom_code']['js']='const re = /\\\\d+\\\\s/;';
    $settings['header_nav']['items']=array();
    $first=jluxe_sanitize_settings_payload($settings,jluxe_theme_settings_defaults());
    $second=jluxe_sanitize_settings_payload(json_decode(wp_json_encode($first),true),jluxe_theme_settings_defaults());
    return array('denied'=>$denied,'roundtrip'=>$first===$second,'empty'=>$second['header_nav']['items']===array(),'slashes'=>$settings['custom_code']['js']===$second['custom_code']['js']);
  `);
  check(
    policy.denied,
    "Real WordPress customer capabilities cannot import executable custom code",
  );
  check(
    policy.roundtrip && policy.empty && policy.slashes,
    "Settings round-trip preserves empty lists and code backslashes under real WordPress administrator capabilities",
  );

  // R79: the homepage carousel card widths must ride on the li through the real
  // woocommerce_post_class filter — attached for the loop and detached right after,
  // so no other product loop on the site inherits the fixed widths.
  const widthFilter = await phpJson(`
    $clean = apply_filters('woocommerce_post_class', array('product'));
    jluxe_homepage_card_width_classes(true);
    $during = apply_filters('woocommerce_post_class', array('product'));
    jluxe_homepage_card_width_classes(false);
    $after = apply_filters('woocommerce_post_class', array('product'));
    return array('clean'=>in_array('w-[160px]',$clean,true),'during'=>in_array('w-[160px]',$during,true),'lg'=>in_array('lg:w-[300px]',$during,true),'after'=>in_array('w-[160px]',$after,true));
  `);
  check(
    !widthFilter.clean && widthFilter.during && widthFilter.lg && !widthFilter.after,
    "R79 the carousel card-width classes are added and removed around the loop on real WordPress, never leaking into other product loops",
  );

  const debug = php.isFile("/wordpress/wp-content/debug.log")
    ? php.readFileAsText("/wordpress/wp-content/debug.log")
    : "";
  check(
    !/PHP (?:Fatal error|Parse error|Warning|Notice|Deprecated)|WordPress database error|Uncaught /i.test(
      debug,
    ),
    `WordPress debug log has no PHP/database errors, warnings or notices${debug ? `: ${debug.slice(-1500)}` : ""}`,
  );
  console.log(`WORDPRESS_INTEGRATION_PASSED: ${results.length}`);
  console.log(
    "Scope: WordPress 6.9, PHP-WASM, SQLite; WooCommerce absent. No MySQL, real SMS/payment provider, real browser or concurrent worker claim.",
  );
} finally {
  await handler[Symbol.asyncDispose]();
}
