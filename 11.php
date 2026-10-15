<?php
/**
 * Plugin Name: JLuxe Consolidated (MU)
 * Description: Consolidated, persistent site logic migrated out of Code Snippets Pro into a single always-on mu-plugin. Covers: admin API auth/store-lock/media/FCM push, REST product cache, performance tweaks, SEO/schema fixes, and redirects.
 *
 * IMPORTANT - before activating this file:
 * The equivalent Code Snippets Pro snippets (IDs 14, 21, 23, 29, 32, 36,
 * 37, 38, 39, 44, 45, 48, 49, 50, 51, 52, 63, 65, 71, 72, 73, 74, 75,
 * 76, 81, 82, 83, 87, 90, 100, 101, 102, 105, 106) MUST be deactivated
 * at the same time this file is dropped into wp-content/mu-plugins/,
 * otherwise every named function/class below will be declared twice
 * and PHP will fatal-error with "Cannot redeclare function". mu-plugins
 * load automatically on every request and cannot be deactivated from
 * the admin UI, so double-check this before going live.
 *
 * Do NOT deactivate id=100 or id=102 individually without this file
 * live - id=100 is the only thing currently adding the required
 * "returnFees" schema field (folded into section 8 below), and id=102
 * is the only thing normalizing the site's raw "IRT" currency setting
 * to the valid ISO 4217 "IRR" code on archive-page schema (folded into
 * section 12 below).
 *
 * Deliberately NOT included below: id=97 ("بخش پرداخت چک اون ووکامرس",
 * a ~20K-character custom payment gateway). Left in Code Snippets Pro
 * for now - a payment-gateway bug has real financial/checkout blast
 * radius and deserves its own careful migration pass, not a casual
 * fold-in alongside smaller pieces. Keep id=97 ACTIVE.
 *
 * Changelog:
 * 2026-09-02+: sections 15-28 added in one batch - robots.txt additions
 * (persian-woocommerce-shipping + readme.txt disallow, was id=105);
 * disable/redirect low-value product & taxonomy feeds (was id=106);
 * parent-image-in-variation-picker (id=14); eBazar HPOS order-JSON
 * copy button (id=21, preserves a real pre-existing 'SB' duplicate-key
 * bug in $ebazar_states_map AS-IS, flagged separately, not silently
 * fixed); in-stock-first shop/category sorting (id=23); [woo_button]
 * shortcode (id=29); admin variable-products report (id=32); checkout
 * label/placeholder tweaks + shipping-method notes UI (id=36/37/38/39);
 * default shop sort newest-first (id=44); 4-tier in-stock-priority
 * search ranking (id=50); mobile floating contact panel (id=48, whose
 * Vazirmatn font enqueue is correctly cancelled out by section 13's
 * dequeue - not a bug); Order Tracking REST API (id=49); special-sale
 * category auto-sync (id=90); classic-editor product-category search
 * box (was a standalone local file, never in Code Snippets Pro).
 * 2026-08-23: section 14 - added "رنگ" to the hidden-row keyword list
 * (was size/dimensions only) after a same-day follow-up request.
 * 2026-08-23: added section 14 - hides the size/dimensions row from
 * the product page's "ویژگی‌های کلیدی" block via a small
 * MutationObserver script (no admin toggle exists for this; other
 * attribute rows are left untouched).
 * 2026-08-22: added section 3b - short (30s) browser Cache-Control on
 * /wc/v3/products and /wp/v2/posts GETs, so the homepage's confirmed
 * duplicate client-side calls to these routes resolve from the
 * browser's own cache instead of the network on repeat.
 * 2026-08-22: section 11 - fixed a ZWNJ-vs-hyphen mismatch that made
 * the "پیک-نیک" redirect never fire, and added 3 new mappings
 * (bare /category, the Victoria garlic press old slug, /index.html)
 * found in a fresh Coverage Drilldown export. See the comment above
 * section 11 for the two candidates deliberately left unmapped.
 * 2026-08-21: added section 12 (IRT->IRR currency fix, was id=102).
 * Real GSC "Invalid ISO 4217 currency code" critical issue - the raw
 * WooCommerce currency setting (get_option('woocommerce_currency'))
 * is literally the string "IRT", not a real ISO 4217 code. Product
 * pages already normalized this somewhere upstream (verified live:
 * always "IRR"), but taxonomy archive pages (e.g. /brand/victoria/)
 * echoed the raw "IRT" value unchanged in their ItemList/Offer schema.
 * Fixed sitewide by a full recursive JSON-LD tree walk.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * 1) Shared admin API auth (was: "کنترل اپلود عکس اپلیکیشن", id=45)
 * Everything below in this section is depended on by section 2 (store
 * lock / media / FCM) and by the Notification Banner API (section 3).
 * ============================================================ */

/*
 * The admin API token belongs in wp-config.php (never in this public file).
 * If it is not configured there, the protected routes below fail closed.
 */

if (!defined('JLUXE_FCM_PROJECT_ID')) {
    // Fixed 2026-08-21: this constant was referenced but never defined,
    // which throws a fatal "undefined constant" error on PHP 8+ every
    // time jluxe_fcm_send_order_notification() ran - i.e. push
    // notifications for new orders were silently broken. Value below
    // is not a guess - it's the project_id already embedded in the
    // service-account JSON in section 2c.
    define('JLUXE_FCM_PROJECT_ID', 'jluxe-admin');
}

if (!function_exists('jluxe_admin_check_token')) {
    function jluxe_admin_check_token(WP_REST_Request $request) {
        $header = $request->get_header('x-jluxe-token');
        $expected = defined('JLUXE_ADMIN_TOKEN') ? constant('JLUXE_ADMIN_TOKEN') : getenv('JLUXE_ADMIN_TOKEN');

        if (!is_string($header) || '' === $header || !is_string($expected) || '' === $expected) {
            return false;
        }

        return hash_equals($expected, $header);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('jluxe-admin/v1', '/store-lock', array(
        'methods' => array('GET', 'POST'),
        'callback' => 'jluxe_admin_store_lock',
        'permission_callback' => 'jluxe_admin_check_token',
    ));
    register_rest_route('jluxe-admin/v1', '/media', array(
        'methods' => 'POST',
        'callback' => 'jluxe_admin_upload_media',
        'permission_callback' => 'jluxe_admin_check_token',
    ));
    register_rest_route('jluxe-admin/v1', '/register-token', array(
        'methods' => 'POST',
        'callback' => 'jluxe_admin_register_fcm_token',
        'permission_callback' => 'jluxe_admin_check_token',
    ));
    register_rest_route('jluxe-admin/v1', '/notification-image', array(
        'methods' => 'POST',
        'callback' => 'jluxe_admin_set_notification_image',
        'permission_callback' => 'jluxe_admin_check_token',
    ));
});

/* ============================================================
 * 2a) Store Lock (id=45)
 * ============================================================ */

if (!function_exists('jluxe_store_is_closed')) {
    function jluxe_store_is_closed() {
        return get_option('jluxe_store_closed', '0') === '1';
    }
}

if (!function_exists('jluxe_store_notice_text')) {
    function jluxe_store_notice_text() {
        return get_option('jluxe_store_notice', 'فروشگاه موقتاً غیرفعال است. لطفاً بعداً مراجعه کنید.');
    }
}

if (!function_exists('jluxe_admin_store_lock')) {
    function jluxe_admin_store_lock(WP_REST_Request $request) {
        if ($request->get_method() === 'GET') {
            return array(
                'closed' => jluxe_store_is_closed(),
                'message' => jluxe_store_notice_text(),
            );
        }

        // Bug fix note (kept from original): (bool)"false" is always
        // true in PHP since a non-empty string is truthy; filter_var
        // parses the string value correctly.
        $closed = filter_var($request->get_param('closed'), FILTER_VALIDATE_BOOLEAN);
        $message = sanitize_text_field($request->get_param('message') ?: 'فروشگاه موقتاً غیرفعال است.');

        update_option('jluxe_store_closed', $closed ? '1' : '0', false);
        update_option('jluxe_store_notice', $message, false);

        return array('closed' => $closed, 'message' => $message);
    }
}

add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    return jluxe_store_is_closed() ? false : $purchasable;
}, 10, 2);

add_action('woocommerce_before_shop_loop', 'jluxe_store_closed_notice');
add_action('woocommerce_before_single_product', 'jluxe_store_closed_notice');
add_action('woocommerce_before_cart', 'jluxe_store_closed_notice');
add_action('woocommerce_before_checkout_form', 'jluxe_store_closed_notice');

if (!function_exists('jluxe_store_closed_notice')) {
    function jluxe_store_closed_notice() {
        if (!jluxe_store_is_closed()) {
            return;
        }
        echo '<div class="woocommerce-info" style="text-align:center;font-weight:bold;">'
            . esc_html(jluxe_store_notice_text())
            . '</div>';
    }
}

add_action('woocommerce_checkout_process', function () {
    if (jluxe_store_is_closed()) {
        wc_add_notice(jluxe_store_notice_text(), 'error');
    }
});

add_action('template_redirect', function () {
    if (!jluxe_store_is_closed()) {
        return;
    }
    if (function_exists('is_checkout') && (is_checkout() || is_cart())) {
        wc_add_notice(jluxe_store_notice_text(), 'notice');
        wp_safe_redirect(wc_get_page_permalink('shop'));
        exit;
    }
});

/*
 * Deliberately do not block WooCommerce REST writes when the storefront is
 * closed: mobile and desktop integrations must still be able to sync products
 * and orders. The storefront checkout itself remains closed by the hooks above.
 */

/* ============================================================
 * 2b) Media Upload + Notification Banner Image (id=45, id=72)
 * ============================================================ */

if (!function_exists('jluxe_admin_upload_media')) {
    function jluxe_admin_upload_media(WP_REST_Request $request) {
        if (empty($_FILES['file'])) {
            return new WP_Error('missing_file', 'فایل ارسال نشده است.', array('status' => 400));
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        return array('id' => $attachment_id, 'source_url' => wp_get_attachment_url($attachment_id));
    }
}

if (!function_exists('jluxe_admin_set_notification_image')) {
    function jluxe_admin_set_notification_image(WP_REST_Request $request) {
        $image_url = esc_url_raw($request->get_param('image_url'));
        if (empty($image_url)) {
            return new WP_Error('invalid_url', 'آدرس تصویر نامعتبر است', array('status' => 400));
        }
        $options = get_option('custom_theme_options', array());
        if (!is_array($options)) { $options = array(); }
        $options['notification_image'] = $image_url;
        update_option('custom_theme_options', $options);
        return array('success' => true, 'notification_image' => $image_url);
    }
}

/* ============================================================
 * 2c) Firebase Cloud Messaging - new order push (id=45)
 * ============================================================
 */



if (!function_exists('jluxe_admin_register_fcm_token')) {
    function jluxe_admin_register_fcm_token(WP_REST_Request $request) {
        $token = sanitize_text_field($request->get_param('token'));
        if (empty($token)) {
            return new WP_Error('missing_token', 'توکن ارسال نشده است.', array('status' => 400));
        }
        $tokens = get_option('jluxe_fcm_tokens', array());
        if (!is_array($tokens)) { $tokens = array(); }
        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
            update_option('jluxe_fcm_tokens', $tokens, false);
        }
        return array('registered' => true);
    }
}

if (!function_exists('jluxe_fcm_remove_token')) {
    function jluxe_fcm_remove_token($token) {
        $tokens = get_option('jluxe_fcm_tokens', array());
        if (!is_array($tokens)) return;
        update_option('jluxe_fcm_tokens', array_values(array_diff($tokens, array($token))), false);
    }
}

if (!function_exists('jluxe_fcm_get_access_token')) {
    /**
     * Builds the OAuth2 token needed to call FCM HTTP v1, signing a JWT
     * (RS256) with the service account and caching it per Google's real
     * expires_in.
     */
    function jluxe_fcm_get_access_token() {
        $cached = get_transient('jluxe_fcm_access_token');
        if ($cached) return $cached;

        // Supply this JSON through wp-config.php or the server environment, never this public file.
        $service_account_json = defined('JLUXE_FCM_SERVICE_ACCOUNT_JSON')
            ? constant('JLUXE_FCM_SERVICE_ACCOUNT_JSON')
            : getenv('JLUXE_FCM_SERVICE_ACCOUNT_JSON');
        if (!is_string($service_account_json) || '' === trim($service_account_json)) {
            return null;
        }

        $account = json_decode($service_account_json, true);
        if (!$account || empty($account['private_key']) || empty($account['client_email'])) {
            return null;
        }

        $now = time();
        $header = array('alg' => 'RS256', 'typ' => 'JWT');
        $claims = array(
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        );

        $b64 = function ($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        $segments = array($b64(wp_json_encode($header)), $b64(wp_json_encode($claims)));
        $signingInput = implode('.', $segments);

        $privateKey = openssl_pkey_get_private($account['private_key']);
        if (!$privateKey) return null;

        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, 'sha256WithRSAEncryption');
        $segments[] = $b64($signature);
        $jwt = implode('.', $segments);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('[JLuxe FCM] token exchange failed: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            error_log('[JLuxe FCM] token exchange returned no access_token: ' . wp_remote_retrieve_body($response));
            return null;
        }

        $ttl = max(60, (int) ($body['expires_in'] ?? 3600) - 300);
        set_transient('jluxe_fcm_access_token', $body['access_token'], $ttl);

        return $body['access_token'];
    }
}

if (!function_exists('jluxe_fcm_send_order_notification')) {
    function jluxe_fcm_send_order_notification($order_id) {
        $tokens = get_option('jluxe_fcm_tokens', array());
        if (empty($tokens) || !is_array($tokens)) return;

        $accessToken = jluxe_fcm_get_access_token();
        if (!$accessToken) return;

        foreach ($tokens as $token) {
            $payload = array(
                'message' => array(
                    'token' => $token,
                    'data' => array('order_id' => (string) $order_id),
                    'android' => array('priority' => 'high'),
                ),
            );

            $response = wp_remote_post(
                'https://fcm.googleapis.com/v1/projects/' . JLUXE_FCM_PROJECT_ID . '/messages:send',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => wp_json_encode($payload),
                    'timeout' => 15,
                )
            );

            if (is_wp_error($response)) {
                error_log('[JLuxe FCM] send failed for order ' . $order_id . ': ' . $response->get_error_message());
                continue;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $errorStatus = $body['error']['status'] ?? null;

            if ($errorStatus) {
                error_log('[JLuxe FCM] FCM error for order ' . $order_id . ': ' . wp_json_encode($body['error']));
                if (in_array($errorStatus, array('UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'), true)) {
                    jluxe_fcm_remove_token($token);
                }
            }
        }
    }
}

// Uses woocommerce_order_status_changed (not woocommerce_new_order,
// which fires the instant a customer lands on the pending-payment
// screen even if payment never completes) and only notifies once per
// order via the _jluxe_fcm_notified order meta flag. HPOS-safe (uses
// the order object's own meta API).
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status, $order) {
    if (!in_array($new_status, array('processing', 'on-hold'), true)) {
        return;
    }
    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if (!$order) {
        return;
    }
    if ($order->get_meta('_jluxe_fcm_notified')) {
        return;
    }
    $order->update_meta_data('_jluxe_fcm_notified', '1');
    $order->save();

    // Send asynchronously via Action Scheduler so this request
    // (checkout completion / admin status change) doesn't block on
    // Firebase's HTTP response.
    if (function_exists('as_schedule_single_action')) {
        if (!as_has_scheduled_action('jluxe_fcm_async_send_event', array('order_id' => $order_id), 'jluxe-fcm')) {
            as_schedule_single_action(time(), 'jluxe_fcm_async_send_event', array('order_id' => $order_id), 'jluxe-fcm');
        }
    } elseif (function_exists('wp_schedule_single_event')) {
        // Keep order/status sync requests fast even when Action Scheduler is absent.
        wp_schedule_single_event(time() + 5, 'jluxe_fcm_async_send_event', array($order_id));
    } else {
        // Last-resort fallback for non-standard WordPress bootstrap environments.
        jluxe_fcm_send_order_notification($order_id);
    }
}, 20, 4);

add_action('jluxe_fcm_async_send_event', 'jluxe_fcm_send_order_notification');


/* ============================================================
 * 3) REST product cache (was: "JLuxe Perf - cache wc/v3/products REST
 * response", id=63). Redis-backed, version-namespaced GET cache for
 * /wc/v3/products with a stats/diagnostics admin page.
 * ============================================================ */

if (!function_exists('jluxe_rest_request_has_api_credentials')) {
    /** Detect REST requests whose responses must never enter a shared cache. */
    function jluxe_rest_request_has_api_credentials($request) {
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return true;
        }
        if (!is_object($request) || !method_exists($request, 'get_header') || !method_exists($request, 'get_query_params')) {
            return false;
        }

        foreach (array('authorization', 'x-jluxe-token', 'x-wc-webhook-signature') as $header_name) {
            $header = $request->get_header($header_name);
            if (is_string($header) && '' !== trim($header)) {
                return true;
            }
        }

        $params = $request->get_query_params();
        foreach (array('consumer_key', 'consumer_secret', 'oauth_consumer_key', 'oauth_signature', 'access_token') as $key) {
            if (isset($params[$key]) && is_scalar($params[$key]) && '' !== (string) $params[$key]) {
                return true;
            }
        }

        return false;
    }
}

if (!class_exists('Jluxe_Products_Rest_Cache')) {

    final class Jluxe_Products_Rest_Cache {

        const GROUP = 'jluxe_products_rest_cache';
        const VERSION_OPT = 'jluxe_products_cache_version';
        const STATS_PREFIX = 'jluxe_pc_stats:';
        const TTL = 300;
        const ROUTE = '/wc/v3/products';

        public static function init() {
            add_filter('rest_pre_dispatch', array(__CLASS__, 'maybe_serve_cached'), 10, 3);
            add_filter('rest_post_dispatch', array(__CLASS__, 'maybe_store_cache'), 10, 3);

            add_action('save_post_product', array(__CLASS__, 'bump_version'));
            add_action('woocommerce_update_product', array(__CLASS__, 'bump_version'));
            add_action('save_post_product_variation', array(__CLASS__, 'bump_version'));
            add_action('woocommerce_update_product_variation', array(__CLASS__, 'bump_version'));
            add_action('before_delete_post', array(__CLASS__, 'maybe_bump_on_delete'));
            add_action('wp_trash_post', array(__CLASS__, 'maybe_bump_on_delete'));

            add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
            add_action('admin_post_jluxe_pc_invalidate', array(__CLASS__, 'handle_invalidate'));
            add_action('admin_post_jluxe_pc_reset_stats', array(__CLASS__, 'handle_reset_stats'));
        }

        private static function redis() {
            if (!class_exists('Redis')) {
                throw new \RuntimeException('Redis extension is not available.');
            }
            $r = new Redis();
            if (!$r->connect('127.0.0.1', 6379, 0.1)) {
                throw new \RuntimeException('Local Redis is not available.');
            }
            return $r;
        }

        private static function is_eligible($request) {
            return 'GET' === $request->get_method()
                && self::ROUTE === $request->get_route()
                && !jluxe_rest_request_has_api_credentials($request);
        }

        private static function get_version() {
            try {
                $cached = wp_cache_get(self::VERSION_OPT, self::GROUP);
                if (false !== $cached) {
                    return $cached;
                }
            } catch (\Throwable $e) {}
            $version = null;
            try {
                $version = get_option(self::VERSION_OPT);
            } catch (\Throwable $e) {}
            if (!$version) {
                $version = (string) microtime(true);
                try {
                    update_option(self::VERSION_OPT, $version, false);
                } catch (\Throwable $e) {
                    return $version;
                }
            }
            try {
                wp_cache_set(self::VERSION_OPT, $version, self::GROUP, HOUR_IN_SECONDS);
            } catch (\Throwable $e) {}
            return $version;
        }

        public static function bump_version() {
            try {
                $version = (string) microtime(true);
                update_option(self::VERSION_OPT, $version, false);
                wp_cache_set(self::VERSION_OPT, $version, self::GROUP, HOUR_IN_SECONDS);
            } catch (\Throwable $e) {}
            try {
                self::redis()->incr(self::STATS_PREFIX . 'invalidate_count');
            } catch (\Throwable $e) {}
        }

        public static function maybe_bump_on_delete($post_id) {
            try {
                if ('product' === get_post_type($post_id)) {
                    self::bump_version();
                }
            } catch (\Throwable $e) {}
        }

        private static function request_context_key() {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
            $logged_in = is_user_logged_in() ? '1' : '0';
            $role = 'guest';
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $role = !empty($user->roles) ? implode(',', $user->roles) : 'none';
            }
            return $locale . '|' . $currency . '|' . $logged_in . '|' . $role;
        }

        private static function cache_key($request) {
            $params = $request->get_query_params();
            ksort($params);
            $version = self::get_version();
            return 'p_' . md5(
                $request->get_route() . '?' . http_build_query($params) .
                '|' . self::request_context_key() .
                '|v' . $version
            );
        }

        private static function record_stat($type, $elapsed_ms = null, $size_bytes = null) {
            try {
                $r = self::redis();
                if ('hit' === $type) {
                    $r->incr(self::STATS_PREFIX . 'hits');
                    if (null !== $elapsed_ms) {
                        $r->incrByFloat(self::STATS_PREFIX . 'hit_time_sum_ms', $elapsed_ms);
                        $r->incr(self::STATS_PREFIX . 'hit_time_count');
                    }
                } elseif ('miss' === $type) {
                    $r->incr(self::STATS_PREFIX . 'misses');
                    $r->set(self::STATS_PREFIX . 'last_build_time', time());
                    if (null !== $elapsed_ms) {
                        $r->incrByFloat(self::STATS_PREFIX . 'build_time_sum_ms', $elapsed_ms);
                        $r->incr(self::STATS_PREFIX . 'build_time_count');
                    }
                }
                if (null !== $size_bytes) {
                    $current_largest = (int) $r->get(self::STATS_PREFIX . 'largest_bytes');
                    if ($size_bytes > $current_largest) {
                        $r->set(self::STATS_PREFIX . 'largest_bytes', $size_bytes);
                    }
                }
            } catch (\Throwable $e) {}
        }

        public static function maybe_serve_cached($result, $server, $request) {
            if (null !== $result) {
                return $result;
            }
            try {
                if (!self::is_eligible($request)) {
                    return $result;
                }
                $t0 = microtime(true);
                $GLOBALS['__jluxe_pc_t0'] = $t0;

                $key = self::cache_key($request);
                $cached = wp_cache_get($key, self::GROUP);

                if (false === $cached || !is_array($cached) || !array_key_exists('data', $cached)) {
                    return $result;
                }

                $response = new WP_REST_Response($cached['data'], $cached['status']);
                if (!empty($cached['headers']) && is_array($cached['headers'])) {
                    foreach ($cached['headers'] as $h_name => $h_value) {
                        $response->header($h_name, $h_value);
                    }
                }
                $response->header('X-JLuxe-Rest-Cache', 'hit');

                self::record_stat('hit', round((microtime(true) - $t0) * 1000, 2));

                return $response;
            } catch (\Throwable $e) {
                return $result;
            }
        }

        public static function maybe_store_cache($response, $server, $request) {
            try {
                if (!self::is_eligible($request)) {
                    return $response;
                }
                if (!($response instanceof WP_REST_Response) || $response->is_error()) {
                    return $response;
                }
                $headers = $response->get_headers();
                if (isset($headers['X-JLuxe-Rest-Cache'])) {
                    return $response;
                }

                $kept_headers = array();
                foreach (array('X-WP-Total', 'X-WP-TotalPages') as $h_name) {
                    if (isset($headers[$h_name])) {
                        $kept_headers[$h_name] = $headers[$h_name];
                    }
                }
                $data = $response->get_data();
                $data_json = wp_json_encode($data);

                wp_cache_set(
                    self::cache_key($request),
                    array(
                        'data' => $data,
                        'status' => $response->get_status(),
                        'headers' => $kept_headers,
                    ),
                    self::GROUP,
                    self::TTL
                );

                $elapsed_ms = isset($GLOBALS['__jluxe_pc_t0']) ? round((microtime(true) - $GLOBALS['__jluxe_pc_t0']) * 1000, 2) : null;
                self::record_stat('miss', $elapsed_ms, strlen($data_json));
            } catch (\Throwable $e) {
            }
            return $response;
        }

        private static function get_stats() {
            $stats = array('error' => null);
            try {
                $r = self::redis();
                $hits = (int) $r->get(self::STATS_PREFIX . 'hits');
                $misses = (int) $r->get(self::STATS_PREFIX . 'misses');
                $total = $hits + $misses;
                $build_sum = (float) $r->get(self::STATS_PREFIX . 'build_time_sum_ms');
                $build_count = (int) $r->get(self::STATS_PREFIX . 'build_time_count');
                $hit_sum = (float) $r->get(self::STATS_PREFIX . 'hit_time_sum_ms');
                $hit_count_t = (int) $r->get(self::STATS_PREFIX . 'hit_time_count');
                $largest = (int) $r->get(self::STATS_PREFIX . 'largest_bytes');
                $invalidate_ct = (int) $r->get(self::STATS_PREFIX . 'invalidate_count');
                $last_build = (int) $r->get(self::STATS_PREFIX . 'last_build_time');
                $mem_info = $r->info('memory');

                $stats['hits'] = $hits;
                $stats['misses'] = $misses;
                $stats['hit_rate'] = $total > 0 ? round(($hits / $total) * 100, 1) : null;
                $stats['avg_build_ms'] = $build_count > 0 ? round($build_sum / $build_count, 1) : null;
                $stats['avg_hit_ms'] = $hit_count_t > 0 ? round($hit_sum / $hit_count_t, 1) : null;
                $stats['largest_bytes'] = $largest;
                $stats['invalidate_ct'] = $invalidate_ct;
                $stats['last_build'] = $last_build;
                $stats['redis_mem'] = $mem_info['used_memory_human'] ?? 'N/A';
            } catch (\Throwable $e) {
                $stats['error'] = $e->getMessage();
            }
            return $stats;
        }

        public static function register_admin_page() {
            add_menu_page(
                'JLuxe Perf',
                'JLuxe Perf',
                'manage_options',
                'jluxe-perf-rest-cache',
                array(__CLASS__, 'render_admin_page'),
                'dashicons-performance',
                80
            );
        }

        public static function render_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }
            $stats = self::get_stats();
            $version = self::get_version();
            ?>
            <div class="wrap">
                <h1>JLuxe Perf &mdash; REST Cache Diagnostics</h1>
                <p>Route: <code><?php echo esc_html(self::ROUTE); ?></code></p>
                <?php if ($stats['error']) : ?>
                    <div class="notice notice-error"><p>خطا در اتصال به Redis: <?php echo esc_html($stats['error']); ?></p></div>
                <?php else : ?>
                    <table class="widefat striped" style="max-width:700px;margin-top:20px;">
                        <tbody>
                        <tr><th style="width:260px;">Hit Rate</th><td><?php echo null === $stats['hit_rate'] ? '&mdash;' : esc_html($stats['hit_rate'] . '%'); ?> <span style="color:#777;">(<?php echo esc_html($stats['hits'] . ' hit / ' . $stats['misses'] . ' miss'); ?>)</span></td></tr>
                        <tr><th>Average Build Time (miss)</th><td><?php echo null === $stats['avg_build_ms'] ? '&mdash;' : esc_html($stats['avg_build_ms'] . ' ms'); ?></td></tr>
                        <tr><th>Average Hit Time</th><td><?php echo null === $stats['avg_hit_ms'] ? '&mdash;' : esc_html($stats['avg_hit_ms'] . ' ms'); ?></td></tr>
                        <tr><th>Largest Cached Object</th><td><?php echo esc_html(size_format($stats['largest_bytes'])); ?></td></tr>
                        <tr><th>Current Version</th><td><code><?php echo esc_html($version); ?></code></td></tr>
                        <tr><th>Invalidate Count</th><td><?php echo esc_html($stats['invalidate_ct']); ?></td></tr>
                        <tr><th>Last Build</th><td><?php echo $stats['last_build'] ? esc_html(human_time_diff($stats['last_build'], time()) . ' پیش') : '&mdash;'; ?></td></tr>
                        <tr><th>Redis Memory (کل نمونه)</th><td><?php echo esc_html($stats['redis_mem']); ?></td></tr>
                        </tbody>
                    </table>
                    <p style="margin-top:20px;">
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jluxe_pc_invalidate'), 'jluxe_pc_invalidate')); ?>" class="button">Invalidate cache now</a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jluxe_pc_reset_stats'), 'jluxe_pc_reset_stats')); ?>" class="button" onclick="return confirm('Reset all stats counters?');">Reset stats</a>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        public static function handle_invalidate() {
            if (!current_user_can('manage_options') || !check_admin_referer('jluxe_pc_invalidate')) {
                wp_die('forbidden');
            }
            self::bump_version();
            wp_safe_redirect(admin_url('admin.php?page=jluxe-perf-rest-cache'));
            exit;
        }

        public static function handle_reset_stats() {
            if (!current_user_can('manage_options') || !check_admin_referer('jluxe_pc_reset_stats')) {
                wp_die('forbidden');
            }
            try {
                $r = self::redis();
                foreach (array('hits', 'misses', 'build_time_sum_ms', 'build_time_count', 'hit_time_sum_ms', 'hit_time_count', 'largest_bytes', 'invalidate_count', 'last_build_time') as $k) {
                    $r->del(self::STATS_PREFIX . $k);
                }
            } catch (\Throwable $e) {}
            wp_safe_redirect(admin_url('admin.php?page=jluxe-perf-rest-cache'));
            exit;
        }
    }

    Jluxe_Products_Rest_Cache::init();
}


/* ============================================================
 * 3b) Short browser-cache window on high-traffic public REST GETs
 * (/wc/v3/products, /wp/v2/posts). Confirmed via live PageSpeed
 * network trace (2026-08-22) - not a guess: the homepage's client JS
 * calls these exact routes 2-3 times each on a single page load. The
 * server already answers fast (REST cache above for products; core's
 * own query for posts), but under throttled/mobile bandwidth every
 * duplicate call still costs a full round trip queued behind the
 * page's other requests. A short max-age lets the 2nd/3rd identical
 * request in the same load resolve from the browser's own HTTP cache
 * at zero network cost - it can't fix the duplicate calls themselves
 * (that's in Boom's compiled/encrypted JS, out of reach), but it
 * makes the duplication cheap instead of expensive. 30s is short
 * enough that no one sees meaningfully stale data across separate
 * page loads. Anonymous-only (skipped when logged in) so a shared
 * proxy/cache can never serve one logged-in user's response to
 * another.
 * ============================================================ */

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if ($response instanceof WP_REST_Response && '/jluxe/v1/order-track' === $request->get_route()) {
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Pragma', 'no-cache');
    }
    if (!($response instanceof WP_REST_Response) || $response->is_error()) {
        return $response;
    }
    if ('GET' !== $request->get_method()) {
        return $response;
    }
    if (!in_array($request->get_route(), array('/wc/v3/products', '/wp/v2/posts'), true)) {
        return $response;
    }
    if (jluxe_rest_request_has_api_credentials($request)) {
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }
    $response->header('Cache-Control', 'public, max-age=30');
    return $response;
}, 20, 3);


/* ============================================================
 * 4) Performance (id=51, id=52, id=75)
 * ============================================================ */

// no_found_rows on homepage secondary product queries (id=51)
add_action('pre_get_posts', function ($query) {
    if (is_admin() || $query->is_main_query() || !is_front_page()) {
        return;
    }
    $post_type = $query->get('post_type');
    $is_product_query = ($post_type === 'product')
        || (is_array($post_type) && in_array('product', $post_type, true));
    if (!$is_product_query) {
        return;
    }
    $query->set('no_found_rows', true);
}, 999);

// dequeue checkout-only WC scripts off cart/checkout/account (id=52)
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $is_checkout = function_exists('is_checkout') && is_checkout();
    $is_cart = function_exists('is_cart') && is_cart();
    $is_account = function_exists('is_account_page') && is_account_page();

    if (!$is_checkout) {
        wp_dequeue_script('wc-checkout');
    }
    if (!$is_cart) {
        wp_dequeue_script('wc-cart');
    }
    if (!$is_checkout && !$is_account) {
        wp_dequeue_script('wc-country-select');
        wp_dequeue_script('wc-address-i18n');
    }
}, 20);

// disable WP default emoji detection sitewide (id=75)
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    add_filter('tiny_mce_plugins', function ($plugins) {
        return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : array();
    });

    add_filter('wp_resource_hints', function ($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            $urls = array_filter($urls, function ($url) {
                $url_string = is_array($url) ? ($url['href'] ?? '') : $url;
                return strpos($url_string, 'https://s.w.org/') === false;
            });
        }
        return $urls;
    }, 10, 2);
}, 20);


/* ============================================================
 * 5) SEO/technical fixes (id=73, id=74, id=65, id=71)
 * ============================================================ */

// Force correct robots.txt for ALL user-agents (id=73)
// Bug: /robots.txt was returning the SPA homepage HTML for most UAs
// (curl, browsers, GPTBot, ClaudeBot, PerplexityBot), only a small
// allowlist (Googlebot, Bingbot) got the real text/plain rules. Hooks
// init at priority 0 to win the race against UA-based routing, calls
// WP's own do_robots() so existing filters (Rank Math, WooCommerce)
// still apply unchanged.
add_action('init', function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path === '/robots.txt') {
        do_robots();
        exit;
    }
}, 0);

// Fallback og:image/twitter:image for pages without a featured image
// (id=74). Uses get_queried_object_id() explicitly rather than bare
// has_post_thumbnail() - the homepage template leaves a stray global
// $post from an internal product query, which made has_post_thumbnail()
// false-positive there.
add_action('wp_head', function () {
    if (is_admin() || is_feed() || is_search()) {
        return;
    }
    if (function_exists('jluxe_output_seo_meta') || defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) {
        return;
    }
    $is_singular_target = is_front_page() || is_page();
    $is_archive_target = (function_exists('is_shop') && is_shop())
        || (function_exists('is_product_category') && is_product_category())
        || (function_exists('is_product_tag') && is_product_tag());

    if (!$is_singular_target && !$is_archive_target) {
        return;
    }
    if ($is_singular_target) {
        $queried_id = get_queried_object_id();
        if ($queried_id && has_post_thumbnail($queried_id)) {
            return;
        }
    }
    $logo_url = esc_url('https://jluxe.ir/wp-content/uploads/2026/04/cropped-اینستا-نهایی.png');
    echo '<meta property="og:image" content="' . $logo_url . '" />' . "\n";
    echo '<meta property="og:image:secure_url" content="' . $logo_url . '" />' . "\n";
    echo '<meta property="og:image:width" content="512" />' . "\n";
    echo '<meta property="og:image:height" content="512" />' . "\n";
    echo '<meta property="og:image:type" content="image/png" />' . "\n";
    echo '<meta name="twitter:image" content="' . $logo_url . '" />' . "\n";
}, 5);

// llms.txt virtual file (id=65)
add_action('init', function () {
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($path !== '/llms.txt') {
        return;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "# جهیزیه لوکس (JLuxe)\n\n";
    echo "> فروشگاه اینترنتی جهیزیه لوکس، عرضه‌کننده انواع لوازم جهیزیه، لوازم آشپزخانه و محصولات کاربردی خانه با ارسال به سراسر ایران.\n\n";
    echo "## درباره\n";
    echo "- [صفحه اصلی](https://jluxe.ir/)\n";
    echo "- [سوالات متداول](https://jluxe.ir/faq/)\n\n";
    echo "## خرید\n";
    echo "- [راهنمای پرداخت](https://jluxe.ir/payment-guide/)\n";
    echo "- [سبد خرید](https://jluxe.ir/cart/)\n\n";
    echo "## نقشه سایت\n";
    echo "- [Sitemap](https://jluxe.ir/sitemap_index.xml)\n";
    exit;
}, 0);

// FAQPage schema for /faq/ (id=71) - additive to Rank Math's existing
// graph (which currently only outputs BreadcrumbList on this URL).
// Reads the same faq_list theme_mod already used to render the visible
// Q&A, so it can't drift out of sync with what's on the page.
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if (!is_page('faq')) {
        return $data;
    }
    $options = get_option('custom_theme_options');
    if (empty($options['faq_list'])) {
        return $data;
    }
    $faq_list = json_decode($options['faq_list'], true);
    if (empty($faq_list) || !is_array($faq_list)) {
        return $data;
    }
    $main_entity = array();
    foreach ($faq_list as $item) {
        if (empty($item['question']) || empty($item['answer'])) {
            continue;
        }
        $main_entity[] = array(
            '@type' => 'Question',
            'name' => wp_strip_all_tags($item['question']),
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text' => wp_strip_all_tags($item['answer']),
            ),
        );
    }
    if (empty($main_entity)) {
        return $data;
    }
    $data['jluxeFaqPage'] = array(
        '@type' => 'FAQPage',
        '@id' => home_url('/faq/#faqpage'),
        'mainEntity' => $main_entity,
    );
    return $data;
}, 10, 2);


/* ============================================================
 * 6) Product schema (brand + FAQ) + feed/redirects + LCP + cleanup
 * (was: "برند + سوالات متداول", id=76)
 * ============================================================ */

// 6.1) brand entity on Product rich-snippet schema
add_filter('rank_math/snippet/rich_snippet_product_entity', function ($entity) {
    $product_id = get_the_ID();
    if (!$product_id || !function_exists('wc_get_product_terms')) {
        return $entity;
    }
    $brand_terms = wc_get_product_terms($product_id, 'product_brand', array('fields' => 'names'));
    if (!empty($brand_terms)) {
        $entity['brand'] = array(
            '@type' => 'Brand',
            'name' => $brand_terms[0],
        );
    }
    return $entity;
});

// 6.2) Legacy FAQ fallback. The active JLuxe theme owns visible FAQ content
// and emits matching JSON-LD; keep this only for older themes without that hook.
add_action('wp_head', function () {
    if (function_exists('jluxe_output_product_faq_schema')) {
        return;
    }
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    $faq = array(
        array(
            'q' => 'زمان تحویل و ارسال این محصول چقدر است؟',
            'a' => 'اگر سفارش خود را در روزهای کاری پیش از ساعت ۱۱ صبح ثبت کنید، همان روز پردازش و ارسال می‌شود.',
        ),
        array(
            'q' => 'آیا امکان بازگشت این کالا وجود دارد؟',
            'a' => 'بله، تا ۷ روز پس از دریافت کالا در صورت باقی ماندن بسته‌بندی اصلی و عدم تغییر فرم ظاهری کالا، امکان مرجوعی وجود دارد.',
        ),
        array(
            'q' => 'روش‌های پرداخت برای خرید این محصول چیست؟',
            'a' => 'پرداخت به‌صورت آنلاین یا پرداخت در محل (کد پستی سراسری) امکان‌پذیر است.',
        ),
        array(
            'q' => 'آیا این محصول ضمانت اصالت کالا دارد؟',
            'a' => 'بله، تمام محصولات جهیزیه لوکس دارای ضمانت اصالت کالا هستند.',
        ),
    );
    $entities = array_map(function ($item) {
        return array(
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text' => $item['a'],
            ),
        );
    }, $faq);
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    );
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 25);

// 6.3) redirect product/category/brand RSS feed requests to their
// parent page - nobody subscribes to a per-product feed, and Google
// was wasting crawl budget on ~48 of these.
add_action('template_redirect', function () {
    if (!is_feed()) {
        return;
    }
    $target = null;
    if (is_singular('product')) {
        $target = get_permalink();
    } elseif (is_product_category() || is_product_tag() || is_tax('product_brand')) {
        $queried = get_queried_object();
        if ($queried instanceof WP_Term) {
            $target = get_term_link($queried);
        }
    }
    if ($target && !is_wp_error($target)) {
        wp_safe_redirect($target, 301);
        exit;
    }
}, 5);

// 6.4) dead old-slug product URLs (found 404-ing in Search Console)
add_action('template_redirect', function () {
    if (!is_404()) {
        return;
    }
    $old_to_new_slug = array(
        'بانکه-شیشه-ای-هانا-6-سایز-درب-استیل-نظم‌دهی-آشپزخانه-زیبا-سازان' => 'zibasazan-hana-glass-jar-6-sizes-steel-lid',
        'بانکه‌های-مربع-رویال-لیمون-در-4-سایز-ارسال-سریع' => 'limon-royal-square-jar-transparent-lid-4-sizes-unbreakable-acrylic',
        'اسکاچ-براش-مخزن‌دار-WS-تمیزکاری-آسان-و-بهداشتی-ظروف-ارسال-سریع' => 'ws-soap-dispensing-dish-brush',
        'تابه-چهارگوش-شیشه‌ای-2-لیتر-لیمون-کد-2237' => 'limon-square-glass-pan-2l-2237',
        'بالش-پارچه-پنبه‌ای-زارا-هوم-الیاف-ضد-حساسیت-میکروژل-ارسال-سریع' => 'zara-home-cotton-pillow-1227',
        'تابه-گرد-شیشه‌ای-3-4-لیتر-لیمون-کد-2236' => 'limon-round-glass-pan-3-4l-2236',
    );
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $segments = explode('/', $path);
    $last = urldecode(end($segments));
    if (isset($old_to_new_slug[$last])) {
        $target = home_url('/product/' . $old_to_new_slug[$last] . '/');
        wp_safe_redirect($target, 301);
        exit;
    }
}, 5);

// 6.5) dead legacy "/category/xxx/" URLs (predating the current
// "/product-category/" taxonomy base)
add_action('template_redirect', function () {
    if (!is_404()) {
        return;
    }
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (strpos($path, 'category/') !== 0) {
        return;
    }
    $old_slug = urldecode(substr($path, strlen('category/')));
    $old_category_to_new_path = array(
        'سبد-نان-و-سبزی' => '/product-category/kitchenware/organizers-and-storage/bread-and-vegetable-baskets/',
        'لوازم-حمام-لگن-صندلی' => '/product-category/bathroom-and-toilet/bath-supplies/basins-and-bath-chairs/',
        'ابزار-آشپزخانه-ویکتوریا' => '/brand/victoria/',
        'mattress' => '/product-category/home-textiles-and-bedding/bedding/guest-and-travel-mattresses-pillows/',
        'بانکه-حبوبات-و-ادویه' => '/product-category/kitchenware/organizers-and-storage/canisters-and-jars/',
    );
    if (isset($old_category_to_new_path[$old_slug])) {
        wp_safe_redirect(home_url($old_category_to_new_path[$old_slug]), 301);
        exit;
    }
}, 5);

// 6.6) preload main product image on product pages (Boom's product
// page is client-side-rendered - the browser must download/parse/exec
// the JS bundle and call the product API before the hero image element
// even exists in the DOM, which is what drove the >4s LCP flagged by
// Search Console. This preload hint lets the browser fetch the image
// bytes immediately from <head>, in parallel with the JS bundle.)
add_action('wp_head', function () {
    if (function_exists('jluxe_preload_single_product_lcp_image')) {
        return;
    }
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    $product_id = get_the_ID();
    if (!$product_id || !has_post_thumbnail($product_id)) {
        return;
    }
    $image_url = get_the_post_thumbnail_url($product_id, 'large');
    if ($image_url) {
        echo '<link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">' . "\n";
    }
}, 1);

// 6.7) stop advertising the exact WordPress version via the generator
// meta tag - trivial for vulnerability scanners, no functional purpose.
remove_action('wp_head', 'wp_generator');

// 6.8) Boom's homepage template leaves stray single-product query state
// behind, so body_class() outputs both "home" and
// "single-product product-template-default" at once on the front page.
// Can't fix at the root without touching Boom's template file, but the
// false-claim classes can be safely stripped from the emitted HTML.
add_filter('body_class', function ($classes) {
    if (is_front_page()) {
        $classes = array_diff($classes, array(
            'single-product',
            'product-template-default',
            'woocommerce-shop',
            'woocommerce',
            'woocommerce-page',
            'woocommerce-no-js',
        ));
    }
    return $classes;
});


/* ============================================================
 * 7) checkout/track-order noindex + sitemap exclusion (id=81)
 * ============================================================ */

add_action('wp_head', function () {
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $is_target = (function_exists('is_checkout') && is_checkout())
        || $path === 'checkout'
        || $path === 'track-order';
    if ($is_target) {
        echo '<meta name="robots" content="noindex,follow">' . "\n";
    }
}, 99);

add_filter('rank_math/sitemap/exclude_post_ids', function ($ids) {
    $checkout_id = (int) get_option('woocommerce_checkout_page_id');
    if ($checkout_id) {
        $ids[] = $checkout_id;
    }
    $track_order = get_page_by_path('track-order');
    if ($track_order) {
        $ids[] = $track_order->ID;
    }
    return $ids;
});


/* ============================================================
 * 8) blog post/category RSS feed redirect to parent page (id=82)
 * ============================================================ */

add_action('template_redirect', function () {
    if (!is_feed()) {
        return;
    }
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (strpos($path, 'blog/') !== 0) {
        return;
    }
    $trimmed = preg_replace('#/feed/?$#', '', $path);
    $target = home_url('/' . $trimmed . '/');
    wp_safe_redirect($target, 301);
    exit;
}, 5);


/* ============================================================
 * 9) font-display:swap for all 5 Shabnam weights (id=83)
 * In wp_footer (not wp_head) because LiteSpeed's CSS-combine feature
 * repositions enqueued stylesheets during output buffering in a way
 * that made even a priority-999 wp_head hook render before the
 * combined CSS link - a footer <style> tag, being truly last in
 * document order, always wins the cascade for identical @font-face
 * selectors.
 * ============================================================ */

add_action('wp_footer', function () {
    $base = '/wp-content/uploads/2026/06/';
    $weights = array(
        array('file' => 'Shabnam-Thin.woff2', 'weight' => 100),
        array('file' => 'Shabnam-Light.woff2', 'weight' => 300),
        array('file' => 'Shabnam.woff2', 'weight' => 400),
        array('file' => 'Shabnam-Medium.woff2', 'weight' => 500),
        array('file' => 'Shabnam-Bold.woff2', 'weight' => 700),
    );
    echo '<style id="jluxe-font-display-fix">';
    foreach ($weights as $w) {
        printf(
            "@font-face{font-family:'Shabnam';src:url(%s%s) format('woff2');font-weight:%d;font-style:normal;font-display:swap}",
            $base,
            $w['file'],
            $w['weight']
        );
    }
    echo '</style>' . "\n";
}, 999);


/* ============================================================
 * 10) Merchant Listings schema completeness (was: v38_merchant_
 * listings_final id=87, with the returnFees field from id=100 folded
 * directly into the same recursive pass instead of a second filter).
 * Facts used (confirmed by the store owner, not fabricated): 7-day
 * return window by mail: store pays if defective/wrong item, customer
 * pays return shipping if they changed their mind. Shipping is COD,
 * 150,000-450,000 IRR by size/weight, handling 0-1 days, transit 2-5
 * business days. validFrom uses the product's own last-modified date.
 * mpn uses the SKU already assigned to that offer/variation (store
 * owner-approved substitute for a formal manufacturer part number,
 * since no GTIN exists).
 * ============================================================ */

add_filter('rank_math/json_ld', function ($data) {
    $product_id = get_the_ID();
    $valid_from = $product_id ? get_post_modified_time('c', false, $product_id) : null;

    $base_additions = array(
        'hasMerchantReturnPolicy' => array(
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 7,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'itemDefectReturnFees' => 'https://schema.org/FreeReturn',
            'customerRemorseReturnFees' => 'https://schema.org/ReturnShippingFees',
            'returnFees' => 'https://schema.org/ReturnShippingFees',
            'applicableCountry' => 'IR',
        ),
        'shippingDetails' => array(
            '@type' => 'OfferShippingDetails',
            'shippingRate' => array('@type' => 'MonetaryAmount', 'currency' => 'IRR', 'minValue' => 150000, 'maxValue' => 450000),
            'shippingDestination' => array('@type' => 'DefinedRegion', 'addressCountry' => 'IR'),
            'deliveryTime' => array(
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => array('@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'),
                'transitTime' => array('@type' => 'QuantitativeValue', 'minValue' => 2, 'maxValue' => 5, 'unitCode' => 'DAY'),
            ),
        ),
    );
    if ($valid_from) {
        $base_additions['validFrom'] = $valid_from;
    }

    jluxe_whc_add_offer_fields_recursive($data, $base_additions);

    return $data;
}, 99);

if (!function_exists('jluxe_whc_add_offer_fields_recursive')) {
    function jluxe_whc_add_offer_fields_recursive(&$node, $base_additions) {
        if (!is_array($node)) {
            return;
        }
        $sibling_sku = (isset($node['sku']) && is_string($node['sku']) && $node['sku'] !== '') ? $node['sku'] : null;

        foreach ($node as $key => &$value) {
            if (!is_array($value)) {
                continue;
            }
            if ($key === 'offers') {
                $additions = $base_additions;
                if ($sibling_sku) {
                    $additions['mpn'] = $sibling_sku;
                }
                if (isset($value['@type'])) {
                    foreach ($additions as $field => $field_value) {
                        if (!array_key_exists($field, $value) || null === $value[$field] || '' === $value[$field] || (is_array($value[$field]) && empty($value[$field]))) {
                            $value[$field] = $field_value;
                        }
                    }
                } else {
                    foreach ($value as &$offer) {
                        if (is_array($offer)) {
                            foreach ($additions as $field => $field_value) {
                                if (!array_key_exists($field, $offer) || null === $offer[$field] || '' === $offer[$field] || (is_array($offer[$field]) && empty($offer[$field]))) {
                                    $offer[$field] = $field_value;
                                }
                            }
                        }
                    }
                    unset($offer);
                }
                continue;
            }
            jluxe_whc_add_offer_fields_recursive($value, $base_additions);
        }
        unset($value);
    }
}


/* ============================================================
 * 11) 301 redirects for dead old Persian product/category slugs
 * found in the 2026-08-21 Coverage 404 report (was: id=101). Each
 * target verified live (200 OK) and matched by exact product/category
 * name before mapping - not guessed. Two candidates from the same
 * report had no confident current match and were deliberately left
 * unmapped rather than redirected to a wrong page.
 *
 * 2026-08-22 update (from the fresh Coverage Drilldown export):
 * - The 'لوازم-مسافرت-و-پیک-نیک' key above never matched. Verified via
 *   codepoint diff: the real crawled URL has a ZWNJ (U+200C) between
 *   "پیک" and "نیک", not a plain hyphen. Added the correct ZWNJ variant
 *   below rather than editing the original line, so both survive if
 *   any other reference to the hyphen form exists elsewhere.
 * - Added: bare '/category' (no slug - malformed link somewhere,
 *   sent to the category index), the Victoria garlic press old slug
 *   (confirmed live via wp-admin product search, ID 1384, still
 *   published just out of stock), and '/index.html' (stray legacy
 *   artifact, sent home).
 * - NOT added (same "don't guess" policy as above): '/?page_id=3' -
 *   that post ID is the Privacy Policy page, currently unpublished
 *   (preview-only in wp-admin), not a broken link - decide whether to
 *   publish it rather than redirect it. '/category/sobhane-khori-ja-
 *   korei-panir-khori' - no current category matches this old name
 *   closely enough to redirect with confidence (checked live via the
 *   WC categories REST endpoint - no match).
 * ============================================================ */

add_action('template_redirect', function () {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $path = untrailingslashit($path);

    $map = array();
    $map['/product/اسکاچ-براش-مخزن‌دار-WS-تمیزکاری-آسان-و-بهداشتی-ظروف-ارسال-سریع'] = '/product/ws-soap-dispensing-dish-brush/';
    $map['/product/بانکه‌های-مربع-رویال-لیمون-در-4-سایز-ارسال-سریع'] = '/product/limon-royal-square-jar-transparent-lid-4-sizes-unbreakable-acrylic/';
    $map['/product/تابه-گرد-شیشه‌ای-3-4-لیتر-لیمون-کد-2236'] = '/product/limon-round-glass-pan-3-4l-2236/';
    $map['/product/آویز-کابینتی-حوله-آشپزخانه-لیمیتاک-کد-430'] = '/product/limitak-430-cabinet-towel-hanger/';
    $map['/category/جاقاشقی'] = '/product-category/kitchenware/organizers-and-storage/cutlery-and-drawer-organizers/';
    $map['/category/نظم-دهنده-یخچال-و-کابینت'] = '/product-category/kitchenware/organizers-and-storage/';
    $map['/category/لوازم-مسافرت-و-پیک-نیک'] = '/product-category/picnic-and-travel/';
    $map['/category/لوازم-مسافرت-و-پیک‌نیک'] = '/product-category/picnic-and-travel/';
    $map['/category/ست-بهداشتی-پلی‌-رزین'] = '/product-category/bathroom-and-toilet/bathroom-accessories/complete-bathroom-sets/';
    $map['/category/mattress'] = '/product-category/home-textiles-and-bedding/bedding/guest-and-travel-mattresses-pillows/';
    $map['/category'] = '/cats/';
    $map['/product/سیر-له-کن-تمام-استیل-ویکتوریا-ارسال-سریع'] = '/product/victoria-all-steel-garlic-press/';
    $map['/index.html'] = '/';

    if (isset($map[$path])) {
        wp_safe_redirect(home_url($map[$path]), 301);
        exit;
    }
}, 5);


/* ============================================================
 * 12) Normalize invalid "IRT" currency code to "IRR" in schema
 * (was: id=102). Root cause verified live: get_option('woocommerce_
 * currency') is literally "IRT" - not a real ISO 4217 code. Singular
 * product pages already normalize this somewhere upstream (schema
 * always showed "IRR" there), but taxonomy archive pages (e.g.
 * /brand/victoria/, /brand/zibasazan/) echoed the raw "IRT" value
 * unchanged, which Google's Merchant Listings / Product snippets
 * checks flag as a critical error. Fixed with a full recursive
 * JSON-LD tree walk so it's caught regardless of nesting depth.
 * ============================================================ */

add_filter('rank_math/json_ld', function ($data) {
    jluxe_whc_fix_irt_currency_recursive($data);
    return $data;
}, 100);

if (!function_exists('jluxe_whc_fix_irt_currency_recursive')) {
    function jluxe_whc_fix_irt_currency_recursive(&$node) {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $key => &$value) {
            if (is_array($value)) {
                jluxe_whc_fix_irt_currency_recursive($value);
                continue;
            }
            if (($key === 'priceCurrency' || $key === 'currency') && $value === 'IRT') {
                $node[$key] = 'IRR';
            }
        }
        unset($value);
    }
}


/* ============================================================
 * 13) Dequeue the unused Vazirmatn web font (2026-08-22, PageSpeed
 * investigation). Confirmed live: this stylesheet is only referenced
 * by the site's own floating contact/support panel
 * (`.custom-contact-panel .contact-panel-content`), which already
 * declares `Tahoma, sans-serif` as a fallback - Vazirmatn isn't the
 * site's chosen font anywhere (that's Shabnam, set in Theme Settings
 * > Font). Confirmed via live JS inspection (checked computed
 * stylesheet rules + a matching <link rel=stylesheet> with a WP-style
 * ?ver= query string, i.e. genuinely wp_enqueue_style()'d, not
 * hand-written markup).
 *
 * This one small external stylesheet pulls in 3 separate woff2 files
 * (~150KB total) from a third-party CDN (cdn.jsdelivr.net) purely for
 * a corner contact widget's font, adding a DNS lookup + 3 blocking
 * font requests to every single page load. Dequeued by scanning
 * registered style handles for "vazirmatn" in the src (matching this
 * codebase's existing pattern of matching by content rather than a
 * hardcoded handle name, e.g. section 1's closure-source-file
 * matching in the other mu-plugin) rather than guessing Boom's
 * internal handle name, since that name isn't visible from outside
 * Boom's encrypted source.
 * ============================================================ */

add_action('wp_enqueue_scripts', function () {
    global $wp_styles;

    if (empty($wp_styles) || empty($wp_styles->registered)) {
        return;
    }

    foreach ($wp_styles->registered as $handle => $style) {
        if (!empty($style->src) && false !== stripos($style->src, 'vazirmatn')) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }
}, 999);


/* ============================================================
 * 14) Hide the "سایز"/"ابعاد"/"رنگ" (size/dimensions/colour) rows
 * from the product page's "ویژگی‌های کلیدی" (Key Features) summary
 * block (2026-08-23 request, extended same day to also cover رنگ).
 * Boom auto-generates one summary row per product
 * attribute (colour, material, size, ...) there with no admin toggle
 * to exclude a specific one - confirmed by checking the page-builder's
 * "شخصی‌سازی مشخصات محصول" settings screen, which controls a
 * DIFFERENT summary block entirely (shipping/warranty/rating icons),
 * not this one. And since Boom's product page is React-rendered (the
 * row can appear/re-render after a variation change, not just once on
 * load), a wp_footer <style> can't target it - there's no stable
 * class or data-attribute distinguishing "this row is the size one"
 * from "this row is the colour one" (verified live: both use the
 * identical `div.flex.items-start.gap-2.rounded-lg.bg-ink-50` markup,
 * differing only in their label text). So this uses a small
 * MutationObserver-based script instead of CSS: it re-scans on every
 * DOM change and hides only rows whose label span's own text contains
 * "سایز" or "ابعاد", leaving every other attribute row (colour,
 * material, ...) untouched.
 * ============================================================ */

add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    ?>
    <script>
    (function () {
        var HIDE_KEYWORDS = ['سایز', 'ابعاد', 'رنگ'];
        var ROW_SELECTOR = 'div.flex.items-start.gap-2.rounded-lg.bg-ink-50';
        var scheduled = false;

        function hideMatchingRows() {
            var rows = document.querySelectorAll(ROW_SELECTOR);
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var label = row.querySelector('p > span:first-child');
                if (!label) continue;
                var text = label.textContent || '';
                var match = false;
                for (var k = 0; k < HIDE_KEYWORDS.length; k++) {
                    if (text.indexOf(HIDE_KEYWORDS[k]) !== -1) {
                        match = true;
                        break;
                    }
                }
                row.style.display = match ? 'none' : '';
            }
        }

        function schedule() {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                scheduled = false;
                hideMatchingRows();
            });
        }

        hideMatchingRows();
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}, 999);
/**
 * =====================================================================
 * ADDITIONS TO jluxe-consolidated-mu-plugin.php - append everything
 * below (sections 15-24) to the END of the existing file, before
 * nothing else needs to change. All function/class names checked
 * against the existing file's sections 1-14 - no collisions.
 *
 * Covers, in order:
 *  15) robots.txt additions (persian-woocommerce-shipping + readme.txt)
 *      - was Code Snippets Pro id=105
 *  16) disable/redirect low-value product & taxonomy feeds
 *      - was id=106
 *  17) show parent product images when picking a variation's image
 *      - was id=14
 *  18) eBazar HPOS order-JSON copy button (admin order list)
 *      - was id=21
 *  19) in-stock-first sorting on shop/category archives
 *      - was id=23
 *  20) [woo_button] shortcode
 *      - was id=29
 *  21) admin report: variable products missing default attributes
 *      - was id=32
 *  22) checkout label/placeholder tweaks (shipping name, address,
 *      postcode) + shipping-method notes UI
 *      - was id=36, id=37, id=38, id=39
 *  23) default shop sort: newest first
 *      - was id=44
 *  24) in-stock-priority search ranking (4-tier)
 *      - was id=50
 *  25) mobile floating contact panel
 *      - was id=48
 *  26) Order Tracking REST API (GET /wp-json/jluxe/v1/order-track)
 *      - was id=49
 *  27) special-sale category auto-sync
 *      - was id=90
 *  28) product-category search box in the classic product editor
 *      - was a standalone local file (my-custom-codes.php), never in
 *      Code Snippets Pro
 *
 * IMPORTANT - before activating: deactivate Code Snippets Pro IDs
 * 14, 21, 23, 29, 32, 36, 37, 38, 39, 44, 48, 49, 50, 90, 105, 106
 * at the same time this addition goes live, for the same
 * double-declaration reason explained at the top of the main file.
 *
 * NOT included here: id=97 ("بخش پرداخت چک اون ووکامرس", ~20K chars,
 * likely a custom payment gateway) - deliberately left out of this
 * batch. It needs its own careful read-and-migrate pass given its size
 * and that a payment-gateway bug has real financial/checkout blast
 * radius; do not fold it in casually alongside these smaller, already
 * fully-verified pieces.
 *
 * A real (not cosmetic) data bug was found while transcribing id=21 and
 * is preserved AS-IS below, not silently fixed: $ebazar_states_map has
 * the key 'SB' assigned twice (once to "خراسان جنوبی" => 30, once to
 * "سیستان و بلوچستان" => 26) - in PHP this means the array literal
 * silently keeps only the SECOND value (26), so orders billed to
 * "خراسان جنوبی" get the WRONG state_id (26, Sistan-Baluchestan's,
 * not 30) when copied to eBazar. Flagged to the store owner separately;
 * intentionally not "fixed" here without confirmation, since eBazar's
 * own state-code convention isn't something to guess at.
 * =====================================================================
 */


/* ============================================================
 * 15) robots.txt additions (was: id=105)
 * ============================================================ */

add_filter('robots_txt', function ($output) {
    $output = rtrim($output) . "\nDisallow: /wp-content/plugins/persian-woocommerce-shipping/\nDisallow: /*/readme.txt\n";
    return $output;
}, 20);


/* ============================================================
 * 16) disable/redirect low-value product & taxonomy feeds
 * (was: id=106). Stops advertising per-product/per-taxonomy feed
 * <link> tags in <head> (the main discovery path Google was using),
 * and 301-redirects any such feed URL already indexed/linked
 * elsewhere to its canonical non-feed page. Blog post/category feeds
 * are handled separately (see section 8 above) and are untouched here
 * - is_tax() only matches CUSTOM taxonomies (product_cat, product_tag,
 * product_brand, pa_*, ...), never 'category'/'post_tag'.
 * ============================================================ */

remove_action('wp_head', 'feed_links_extra', 3);

add_action('template_redirect', function () {
    if (!is_feed()) {
        return;
    }
    $redirect_to = null;
    if (is_singular('product')) {
        $redirect_to = get_permalink();
    } elseif (is_post_type_archive('product')) {
        $redirect_to = get_post_type_archive_link('product');
    } elseif (is_tax()) {
        $term = get_queried_object();
        if ($term && !is_wp_error($term)) {
            $redirect_to = get_term_link($term);
        }
    }
    if ($redirect_to && !is_wp_error($redirect_to)) {
        wp_safe_redirect($redirect_to, 301);
        exit;
    }
}, 5);


/* ============================================================
 * 17) show parent product's images when picking a variation's image
 * in the media library (classic product editor). (was: id=14)
 * ============================================================ */

add_filter('ajax_query_attachments_args', 'wc_show_parent_images_in_variation', 10, 1);

if (!function_exists('wc_show_parent_images_in_variation')) {
    function wc_show_parent_images_in_variation($query) {
        if (isset($query['post_parent']) && $query['post_parent'] > 0) {
            $post_id = absint($query['post_parent']);
            $post = get_post($post_id);

            if ($post && $post->post_type === 'product_variation') {
                $parent_product_id = $post->post_parent;

                if ($parent_product_id) {
                    unset($query['post_parent']);
                    $query['post_parent__in'] = array($post_id, $parent_product_id);
                }
            }
        }

        return $query;
    }
}


/* ============================================================
 * 18) eBazar HPOS order-JSON copy button (admin order list).
 * (was: id=21). See file header for the known 'SB' key-collision bug
 * preserved as-is in $ebazar_states_map below.
 * ============================================================ */

add_action('manage_woocommerce_page_wc-orders_custom_column', 'ebazar_hpos_json_copy_button', 10, 2);

if (!function_exists('ebazar_hpos_json_copy_button')) {
    function ebazar_hpos_json_copy_button($column, $order) {
        if ('order_total' === $column) {

            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $full_name = trim($first_name . ' ' . $last_name);
            $phone = $order->get_billing_phone();
            $postcode = $order->get_billing_postcode();
            $address = $order->get_billing_address_1();
            $state_code = $order->get_billing_state();
            $city_name = $order->get_billing_city();

            // نگاشت کدهای ووکامرس به شناسه‌های عددی سورس ایبازار.
            // هشدار: کلید 'SB' دو بار تعریف شده (خراسان جنوبی و سیستان
            // و بلوچستان) - همان‌طور که در فایل اصلی بود حفظ شده، رفع
            // نشده. جزئیات در توضیح بالای فایل.
            $ebazar_states_map = array(
                'EA' => '3', 'WA' => '16', 'AR' => '15', 'ES' => '6', 'AL' => '31',
                'IL' => '27', 'BU' => '21', 'TE' => '1', 'CB' => '24', 'SB' => '30',
                'KH' => '7', 'NK' => '29', 'KZ' => '4', 'ZN' => '12', 'SM' => '9',
                'SB' => '26', 'FA' => '5', 'QZ' => '8', 'QM' => '10', 'KD' => '18',
                'KE' => '22', 'KS' => '19', 'KB' => '28', 'GO' => '14', 'GI' => '2',
                'LO' => '20', 'MZ' => '13', 'MK' => '11', 'HR' => '23', 'HD' => '17',
                'YD' => '25',
            );

            $ebazar_state_id = isset($ebazar_states_map[$state_code]) ? $ebazar_states_map[$state_code] : '';
            $address_clean = str_replace(array("\r", "\n"), " ", $address);

            $order_data = array(
                'is_ebazar_data' => true,
                'name' => $full_name,
                'mobile' => $phone,
                'postcode' => $postcode,
                'address' => $address_clean,
                'state_id' => $ebazar_state_id,
                'city_name' => trim($city_name),
            );
            $order_json = wp_json_encode($order_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($order_json)) {
                $order_json = '{}';
            }
            ?>
            <div style="margin-top: 7px;">
                <button type="button"
                    class="button"
                    style="background-color:#2ecc71 !important; color:white !important; font-weight: bold; border: none; border-radius: 4px; padding: 4px 10px; font-size: 11px; cursor: pointer;"
                    data-copy="<?php echo esc_attr($order_json); ?>"
                    onclick='(function(btn){
                        var txt = btn.getAttribute("data-copy") || "{}";
                        var el = document.createElement("textarea");
                        el.value = txt;
                        document.body.appendChild(el);
                        el.select();
                        document.execCommand("copy");
                        document.body.removeChild(el);

                        var oldText = btn.innerText;
                        btn.innerText = "کپی شد ✓";
                        btn.style.backgroundColor = "#27ae60";
                        setTimeout(function(){
                            btn.innerText = oldText;
                            btn.style.backgroundColor = "#2ecc71";
                        }, 2000);
                    })(this); return false;'>
                    کپی برای ایبازار
                </button>
            </div>
            <?php
        }
    }
}


/* ============================================================
 * 19) in-stock-first sorting on shop/category/tag archives.
 * (was: id=23)
 * ============================================================ */

add_filter('posts_clauses', 'standard_order_by_stock_and_modified_date', 2000);

if (!function_exists('standard_order_by_stock_and_modified_date')) {
    function standard_order_by_stock_and_modified_date($posts_clauses) {
        global $wpdb;

        if (is_admin() || !is_woocommerce() || !(is_shop() || is_product_category() || is_product_tag())) {
            return $posts_clauses;
        }

        $posts_clauses['join'] .= " INNER JOIN $wpdb->postmeta istockstatus ON ($wpdb->posts.ID = istockstatus.post_id) ";
        $posts_clauses['orderby'] = " istockstatus.meta_value ASC, $wpdb->posts.post_modified DESC, " . $posts_clauses['orderby'];
        $posts_clauses['where'] = " AND istockstatus.meta_key = '_stock_status' AND istockstatus.meta_value <> '' " . $posts_clauses['where'];

        return $posts_clauses;
    }
}


/* ============================================================
 * 20) [woo_button] shortcode - reusable styled button in content.
 * (was: id=29)
 * ============================================================ */

add_shortcode('woo_button', function ($atts) {
    $atts = shortcode_atts(array(
        'text' => 'خرید سریع',
        'url' => '',
        'target' => '_self',
        'style' => 'primary',
    ), $atts, 'woo_button');

    $text = wp_strip_all_tags(trim((string) $atts['text']));
    $url = trim((string) $atts['url']);
    $target = trim((string) $atts['target']);
    $style = trim((string) $atts['style']);

    if ($text === '' || $url === '' || $url === '#') {
        return '';
    }

    $target = in_array($target, array('_self', '_blank'), true) ? $target : '_self';
    $style = in_array($style, array('primary', 'outline', 'dark'), true) ? $style : 'primary';
    $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';

    $wrap_style = 'text-align:center;margin:18px 0;';
    $base_style = 'display:inline-block;padding:16px 28px;border-radius:18px;text-decoration:none;text-align:center;font-weight:800;font-size:17px;line-height:1.3;letter-spacing:-0.2px;box-sizing:border-box;box-shadow:0 12px 30px rgba(0,0,0,.14);transition:all .25s ease;';

    if ($style === 'outline') {
        $button_style = $base_style . 'background:#ffffff;color:#1f2937;border:1px solid #d1d5db;';
    } elseif ($style === 'dark') {
        $button_style = $base_style . 'background:linear-gradient(135deg,#111827 0%,#1f2937 100%);color:#ffffff;border:none;';
    } else {
        $button_style = $base_style . 'background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);color:#ffffff;border:none;';
    }

    return '<div style="' . esc_attr($wrap_style) . '"><a href="' . esc_url($url) . '" target="' . esc_attr($target) . '"' . $rel . ' style="' . esc_attr($button_style) . '">' . esc_html($text) . '</a></div>';
});


/* ============================================================
 * 21) admin report: variable products missing default attributes.
 * Tools > گزارش محصولات متغیر. Read-only report, makes no changes.
 * (was: id=32)
 * ============================================================ */

add_action('admin_menu', function () {
    add_management_page(
        'Variable Products Report',
        'گزارش محصولات متغیر',
        'manage_woocommerce',
        'variable-products-report',
        'wc_variable_products_report_page'
    );
});

if (!function_exists('wc_variable_products_report_page')) {
    function wc_variable_products_report_page() {

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>گزارش محصولات متغیر بدون مقدار پیش‌فرض کامل</h1>';

        if (isset($_POST['run_report'])) {

            check_admin_referer('wc_variable_report');

            $products = wc_get_products(array(
                'type' => 'variable',
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ));

            $problem_products = array();
            $checked_products = count($products);

            foreach ($products as $product_id) {

                $product = wc_get_product($product_id);

                if (!$product || !$product->is_type('variable')) {
                    continue;
                }

                $attributes = $product->get_attributes();
                $defaults = $product->get_default_attributes();

                $variation_attributes = array();

                foreach ($attributes as $attribute) {
                    if (!$attribute->get_variation()) {
                        continue;
                    }
                    $variation_attributes[] = $attribute;
                }

                $variation_count = count($variation_attributes);
                $default_count = 0;

                foreach ($defaults as $default_value) {
                    if (!empty($default_value)) {
                        $default_count++;
                    }
                }

                if ($variation_count > $default_count) {

                    $missing_attributes = array();

                    foreach ($variation_attributes as $attribute) {

                        $attribute_name = $attribute->get_name();
                        $found = false;

                        foreach ($defaults as $default_key => $default_value) {
                            if (empty($default_value)) {
                                continue;
                            }
                            $decoded_key = urldecode($default_key);
                            if ($decoded_key === $attribute_name || $decoded_key === urldecode($attribute_name)) {
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            $missing_attributes[] = wc_attribute_label($attribute_name);
                        }
                    }

                    $problem_products[] = array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'missing' => implode(' ، ', $missing_attributes),
                    );
                }
            }

            echo '<div class="notice notice-info inline"><p>تعداد محصولات متغیر بررسی شده: <strong>' . intval($checked_products) . '</strong></p></div>';
            echo '<div class="notice notice-warning inline"><p>تعداد محصولات دارای مشکل: <strong>' . count($problem_products) . '</strong></p></div>';

            if (!empty($problem_products)) {

                echo '<table class="widefat striped" style="margin-top:20px;">';
                echo '<thead><tr><th style="width:80px">شناسه</th><th>نام محصول</th><th>ویژگی‌های بدون مقدار پیش‌فرض</th><th style="width:120px">عملیات</th></tr></thead>';
                echo '<tbody>';

                foreach ($problem_products as $item) {
                    $edit_url = admin_url('post.php?post=' . $item['id'] . '&action=edit');
                    echo '<tr>';
                    echo '<td>' . intval($item['id']) . '</td>';
                    echo '<td>' . esc_html($item['name']) . '</td>';
                    echo '<td>' . esc_html($item['missing']) . '</td>';
                    echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '" target="_blank">ویرایش</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';

            } else {
                echo '<div class="notice notice-success inline"><p><strong>هیچ محصول مشکل‌داری پیدا نشد.</strong></p></div>';
            }
        }

        echo '<form method="post" style="margin-top:20px;">';
        wp_nonce_field('wc_variable_report');
        echo '<p>این گزارش فقط بررسی می‌کند و هیچ تغییری در محصولات ایجاد نمی‌کند.</p>';
        echo '<p><input type="submit" name="run_report" class="button button-primary button-large" value="اجرای گزارش"></p>';
        echo '</form>';
        echo '</div>';
    }
}


/* ============================================================
 * 22) checkout label/placeholder tweaks + shipping-method notes UI.
 * (was: id=36, id=37, id=38, id=39)
 * ============================================================ */

// تغییر عنوان "حمل و نقل" به "روش ارسال" (id=36)
add_filter('woocommerce_shipping_package_name', 'jluxe_change_shipping_package_name', 9999, 3);

if (!function_exists('jluxe_change_shipping_package_name')) {
    function jluxe_change_shipping_package_name($package_name, $package_id, $package) {
        return 'روش ارسال را انتخاب کنید';
    }
}

// placeholder فیلد آدرس (id=37)
add_filter('woocommerce_default_address_fields', 'jluxe_change_default_address_placeholder', 9999);

if (!function_exists('jluxe_change_default_address_placeholder')) {
    function jluxe_change_default_address_placeholder($address_fields) {
        if (isset($address_fields['address_1'])) {
            $address_fields['address_1']['placeholder'] = 'خیابان، کوچه، پلاک، واحد و...';
        }
        return $address_fields;
    }
}

// placeholder فیلد کد پستی (id=38)
add_filter('woocommerce_checkout_fields', 'jluxe_change_postcode_field');

if (!function_exists('jluxe_change_postcode_field')) {
    function jluxe_change_postcode_field($fields) {
        if (isset($fields['billing']['billing_postcode'])) {
            $fields['billing']['billing_postcode']['placeholder'] = 'برای ارسال از طریق پست، درج کد پستی الزامی است';
        }
        return $fields;
    }
}

// استایل/توضیحات زیر گزینه‌های روش ارسال در checkout (id=39)
add_action('wp_footer', 'jluxe_shipping_method_notes_ui', 99999);

if (!function_exists('jluxe_shipping_method_notes_ui')) {
    function jluxe_shipping_method_notes_ui() {

        if (!is_checkout() || is_order_received_page()) {
            return;
        }
        ?>
        <style id="jluxe-shipping-method-notes-css">
        .woocommerce-checkout ul#shipping_method li label{
            position:relative;
            display:block;
            width:50%;
            box-sizing:border-box;
            padding-left:50px;
        }
        .woocommerce-checkout ul#shipping_method li label .jluxe-shipping-title{
            display:block;
            color:#1f2937;
            font-size:14px;
            font-weight:600;
            line-height:1.8;
            white-space:normal;
        }
        .woocommerce-checkout ul#shipping_method li label .jluxe-shipping-note{
            display:block;
            width:70%;
            box-sizing:border-box;
            margin-top:8px;
            padding:5px 8px;
            border-radius:10px;
            background:#f3f4f6;
            border:1px solid #e5e7eb;
            color:#5f6368;
            font-size:12.5px;
            font-weight:400;
            line-height:1.9;
            text-align:right;
        }
        .woocommerce-checkout ul#shipping_method li:has(input.shipping_method:checked) label .jluxe-shipping-note{
            background:#f6f6f7;
            border-color:#d1d5db;
            color:#374151;
        }
        .woocommerce-checkout ul#shipping_method li label::after{
            position:absolute;
            left:15px;
            top:24px;
            margin:0;
            white-space:nowrap;
        }
        @media (max-width:768px){
            .woocommerce-checkout ul#shipping_method li label{
                padding-left:100px;
            }
            .woocommerce-checkout ul#shipping_method li label .jluxe-shipping-title{
                font-size:13.5px;
                line-height:1.8;
            }
            .woocommerce-checkout ul#shipping_method li label .jluxe-shipping-note{
                margin-top:7px;
                padding:7px 9px;
                font-size:11.8px;
                line-height:1.8;
                border-radius:9px;
            }
            .woocommerce-checkout ul#shipping_method li label::after{
                left:10px;
                top:8px;
            }
        }
        </style>

        <script id="jluxe-shipping-method-notes-js">
        (function() {
            function jluxeApplyShippingNotes() {
                var notes = {
                    'flat_rate:1': 'اقتصادی',
                    'flat_rate:2': 'سریع‌تر، هزینه بالا',
                    'flat_rate:3': 'مناسب مرسوله‌های حجیم'
                };

                document.querySelectorAll('ul#shipping_method li').forEach(function(item) {
                    var input = item.querySelector('input.shipping_method');
                    var label = item.querySelector('label');

                    if (!input || !label) {
                        return;
                    }

                    var oldNote = label.querySelector('.jluxe-shipping-note');

                    if (oldNote) {
                        oldNote.remove();
                    }

                    if (notes[input.value]) {
                        var note = document.createElement('span');
                        note.className = 'jluxe-shipping-note';
                        note.textContent = notes[input.value];
                        label.appendChild(note);
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', jluxeApplyShippingNotes);

            if (window.jQuery) {
                jQuery(document.body).on('updated_checkout updated_shipping_method', function() {
                    jluxeApplyShippingNotes();
                });
            }

            document.addEventListener('change', function(event) {
                if (event.target && event.target.matches('input.shipping_method')) {
                    jluxeApplyShippingNotes();
                }
            });
        })();
        </script>
        <?php
    }
}


/* ============================================================
 * 23) default shop sort: newest first (unless the shopper picked a
 * sort explicitly). (was: id=44)
 * ============================================================ */

add_filter('woocommerce_default_catalog_orderby', function () {
    return 'date';
});

add_action('woocommerce_before_shop_loop', function () {
    if (!isset($_GET['orderby'])) {
        add_filter('woocommerce_get_catalog_ordering_args', function ($args) {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            $args['meta_key'] = '';
            return $args;
        });
    }
}, 5);


/* ============================================================
 * 24) in-stock-priority search ranking, 4-tier (simple in-stock >
 * variable-with-an-in-stock-variation > backorder > fully out of
 * stock). (was: id=50)
 * ============================================================ */

add_filter('posts_clauses', 'jluxe_search_stock_priority_clauses', 9999, 2);

if (!function_exists('jluxe_search_stock_priority_clauses')) {
    function jluxe_search_stock_priority_clauses($clauses, $query) {

        $post_type = $query->get('post_type');
        $is_product_query = ($post_type === 'product') || (is_array($post_type) && in_array('product', $post_type, true));
        $search_term = $query->get('s');

        if (!$is_product_query || $search_term === '' || $search_term === false) {
            return $clauses;
        }

        global $wpdb;
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';

        $clauses['join'] .= "
            LEFT JOIN {$lookup} AS own_stock
            ON {$wpdb->posts}.ID = own_stock.product_id
        ";

        $clauses['join'] .= "
            LEFT JOIN (
                SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} AS tr
                INNER JOIN {$wpdb->term_taxonomy} AS tt
                    ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    AND tt.taxonomy = 'product_type'
                INNER JOIN {$wpdb->terms} AS t
                    ON t.term_id = tt.term_id
                    AND t.slug = 'variable'
            ) AS variable_type
            ON variable_type.object_id = {$wpdb->posts}.ID
        ";

        $clauses['join'] .= "
            LEFT JOIN (
                SELECT
                    v.post_parent AS parent_id,
                    MAX(CASE WHEN vl.stock_status = 'instock' THEN 1 ELSE 0 END) AS has_instock_variation,
                    MAX(CASE WHEN vl.stock_status = 'onbackorder' THEN 1 ELSE 0 END) AS has_backorder_variation
                FROM {$wpdb->posts} AS v
                INNER JOIN {$lookup} AS vl ON vl.product_id = v.ID
                WHERE v.post_type = 'product_variation'
                AND v.post_status = 'publish'
                GROUP BY v.post_parent
            ) AS var_stock
            ON var_stock.parent_id = {$wpdb->posts}.ID
        ";

        $clauses['orderby'] = "
            CASE
                WHEN variable_type.object_id IS NULL AND own_stock.stock_status = 'instock' THEN 0
                WHEN variable_type.object_id IS NOT NULL AND var_stock.has_instock_variation = 1 THEN 1
                WHEN own_stock.stock_status = 'onbackorder' OR var_stock.has_backorder_variation = 1 THEN 2
                ELSE 3
            END ASC,
            {$wpdb->posts}.post_date DESC
        ";

        return $clauses;
    }
}


/* ============================================================
 * 25) mobile floating contact panel (dark/gold boutique-concierge
 * theme). Enqueues the Vazirmatn font (jsdelivr CDN) as its intended
 * font, which section 13 above then correctly dequeues sitewide -
 * this is expected, not a bug: the panel's own CSS already falls back
 * to Tahoma, matching current live behaviour exactly.
 * (was: id=48)
 * ============================================================ */

add_action('wp_footer', function () {
    ?>
    <style>
    .custom-contact-panel {
        position: fixed;
        inset: 0;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        visibility: hidden;
        pointer-events: none;
    }
    .custom-contact-panel.is-active {
        visibility: visible;
        pointer-events: auto;
    }
    .contact-panel-overlay {
        position: absolute;
        inset: 0;
        background: rgba(20, 16, 10, 0.4);
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    .custom-contact-panel.is-active .contact-panel-overlay {
        opacity: 1;
    }
    .contact-panel-content {
        position: relative;
        direction: rtl;
        font-family: 'Vazirmatn', Tahoma, sans-serif;
        background: #fffdf9;
        border-top-right-radius: 22px;
        border-top-left-radius: 22px;
        padding: 14px 20px 26px;
        width: 100%;
        max-height: 75vh;
        overflow-y: auto;
        box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(102%);
        transition: transform 0.38s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .custom-contact-panel.is-active .contact-panel-content {
        transform: translateY(0);
    }
    .contact-panel-foil {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        border-top-right-radius: 22px;
        border-top-left-radius: 22px;
        background: linear-gradient(90deg, transparent, #12a89d, #e8ac8f, transparent);
        background-size: 200% 100%;
    }
    .contact-panel-foil.is-playing {
        animation: cp-shimmer 1.1s ease-out;
    }
    @keyframes cp-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    .contact-panel-handle {
        width: 36px;
        height: 4px;
        border-radius: 2px;
        background: rgba(18, 168, 157, 0.25);
        margin: 2px auto 16px;
    }
    .contact-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .contact-panel-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #8a8478;
    }
    .contact-panel-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #c9c2b3;
        flex-shrink: 0;
    }
    .contact-panel-status-dot.is-open {
        background: #12a89d;
    }
    .contact-panel-title {
        font-size: 15px;
        font-weight: 700;
        color: #1a1a1a;
    }
    .close-btn {
        position: absolute;
        top: 14px;
        left: 16px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #f2efe8;
        border: none;
        color: #5a564c;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }
    .close-btn:hover,
    .close-btn:focus-visible {
        background: #e8e3d8;
        color: #1a1a1a;
    }
    .close-btn:focus-visible {
        outline: 2px solid #12a89d;
        outline-offset: 2px;
    }
    .contact-panel-body ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .contact-panel-body li {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.35s ease, transform 0.35s ease;
    }
    .custom-contact-panel.is-active .contact-panel-body li {
        opacity: 1;
        transform: translateY(0);
    }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(1) { transition-delay: 0.09s; }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(2) { transition-delay: 0.16s; }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(3) { transition-delay: 0.23s; }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(4) { transition-delay: 0.30s; }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(5) { transition-delay: 0.37s; }
    .custom-contact-panel.is-active .contact-panel-body li:nth-child(n+6) { transition-delay: 0.44s; }
    .contact-panel-body a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 4px;
        text-decoration: none;
        border-bottom: 0.5px solid rgba(0, 0, 0, 0.06);
        transition: background 0.2s ease;
    }
    .contact-panel-body li:last-child a {
        border-bottom: none;
    }
    .contact-panel-body a:hover,
    .contact-panel-body a:focus-visible {
        background: rgba(18, 168, 157, 0.06);
    }
    .contact-panel-body a:focus-visible {
        outline: 2px solid #12a89d;
        outline-offset: -2px;
        border-radius: 8px;
    }
    .contact-panel-badge {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 50%;
        background: #eaf7f5;
        border: 1px solid #12a89d;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .contact-panel-badge img {
        width: 20px;
        height: 20px;
        object-fit: contain;
    }
    .contact-panel-badge#contact-panel-badge-phone {
        background: #fdf1ea;
        border-color: #e8ac8f;
    }
    .contact-panel-badge.pulse {
        animation: cp-pulse 2.2s ease-in-out infinite;
    }
    @keyframes cp-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(232, 172, 143, 0.5); }
        50% { box-shadow: 0 0 0 6px rgba(232, 172, 143, 0); }
    }
    .contact-panel-text {
        flex: 1;
    }
    .contact-panel-label {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        color: #1a1a1a;
    }
    .contact-panel-caption {
        display: block;
        font-size: 11px;
        color: #a39d8e;
        margin-top: 2px;
    }
    .contact-panel-chevron {
        font-size: 15px;
        color: #c9c2b3;
    }
    @media (prefers-reduced-motion: reduce) {
        .contact-panel-content,
        .contact-panel-body li,
        .contact-panel-overlay {
            transition: none !important;
        }
        .contact-panel-foil.is-playing,
        .contact-panel-badge.pulse {
            animation: none !important;
        }
    }
    </style>

    <div class="custom-contact-panel" id="contact-panel" role="dialog" aria-modal="true" aria-labelledby="contact-panel-title" hidden>
        <div class="contact-panel-overlay" data-close-panel></div>
        <div class="contact-panel-content">
            <div class="contact-panel-foil" id="contact-panel-foil"></div>
            <div class="contact-panel-handle"></div>

            <div class="contact-panel-header">
                <div class="contact-panel-status">
                    <span class="contact-panel-status-dot" id="contact-panel-status-dot"></span>
                    <span id="contact-panel-status-text">در حال بررسی ساعت کاری</span>
                </div>
                <span class="contact-panel-title" id="contact-panel-title">راه‌های ارتباطی</span>
            </div>

            <button type="button" class="close-btn" aria-label="بستن پنل تماس" data-close-panel>&times;</button>

            <div class="contact-panel-body">
                <ul>
                    <li>
                        <a href="tel:+989120902336">
                            <span class="contact-panel-badge" id="contact-panel-badge-phone">
                                <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23e8ac8f'><path d='M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.58 1 1 0 0 1-.25 1.01l-2.2 2.2z'/></svg>" alt="">
                            </span>
                            <span class="contact-panel-text">
                                <span class="contact-panel-label">تماس تلفنی</span>
                                <span class="contact-panel-caption">روز های کاری ۱۰ صبح تا ۸ شب</span>
                            </span>
                            <span class="contact-panel-chevron">‹</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://wa.me/989120902336" target="_blank" rel="noopener">
                            <span class="contact-panel-badge">
                                <img src="https://jluxe.ir/wp-content/uploads/2026/05/APPizo-147.png" alt="">
                            </span>
                            <span class="contact-panel-text"><span class="contact-panel-label">واتس‌اپ</span></span>
                            <span class="contact-panel-chevron">‹</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://instagram.com/jluxeir?igsh=MzNlNGNkZWQ4Mg==" target="_blank" rel="noopener">
                            <span class="contact-panel-badge">
                                <img src="https://jluxe.ir/wp-content/uploads/2026/05/APPizo-140.png" alt="">
                            </span>
                            <span class="contact-panel-text"><span class="contact-panel-label">اینستاگرام</span></span>
                            <span class="contact-panel-chevron">‹</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://rubika.ir/jluxeir" target="_blank" rel="noopener">
                            <span class="contact-panel-badge">
                                <img src="https://jluxe.ir/wp-content/uploads/2026/05/rubika.png" alt="">
                            </span>
                            <span class="contact-panel-text"><span class="contact-panel-label">روبیکا</span></span>
                            <span class="contact-panel-chevron">‹</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://ble.ir/jluxeir" target="_blank" rel="noopener">
                            <span class="contact-panel-badge">
                                <img src="https://jluxe.ir/wp-content/uploads/2026/05/bale.png" alt="">
                            </span>
                            <span class="contact-panel-text"><span class="contact-panel-label">بله</span></span>
                            <span class="contact-panel-chevron">‹</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    (function () {
        function initContactPanel() {
            var panel = document.getElementById('contact-panel');
            if (!panel) return;

            var reduced = false;
            if (typeof window.matchMedia === 'function') {
                try {
                    reduced = !!window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                } catch (e) {}
            }
            var foil = document.getElementById('contact-panel-foil');
            var phoneBadge = document.getElementById('contact-panel-badge-phone');
            var lastFocusedEl = null;
            var closeTimer = 0;
            var closeTransitionHandler = null;

            function clearPendingClose() {
                var content = panel.querySelector('.contact-panel-content');
                if (closeTimer) {
                    window.clearTimeout(closeTimer);
                    closeTimer = 0;
                }
                if (content && content.removeEventListener && closeTransitionHandler) {
                    content.removeEventListener('transitionend', closeTransitionHandler);
                }
                closeTransitionHandler = null;
            }

            function findCloseTarget(element) {
                if (element && element.nodeType !== 1) element = element.parentNode;
                while (element && element !== panel && element.nodeType === 1) {
                    if (element.hasAttribute && element.hasAttribute('data-close-panel')) return element;
                    element = element.parentNode;
                }
                return null;
            }

            function findOpenTrigger(element) {
                if (element && element.nodeType !== 1) element = element.parentNode;
                while (element && element !== document && element.nodeType === 1) {
                    if (element.tagName && 'a' === element.tagName.toLowerCase()) {
                        var href = element.getAttribute('href') || '';
                        if (href.indexOf('#open-contact-panel') !== -1) return element;
                    }
                    element = element.parentNode;
                }
                return null;
            }

            setContactStatus();

            function setContactStatus() {
                var dot = document.getElementById('contact-panel-status-dot');
                var text = document.getElementById('contact-panel-status-text');
                if (!dot || !text) return;
                try {
                    var hour = parseInt(
                        new Date().toLocaleString('en-US', { timeZone: 'Asia/Tehran', hour: '2-digit', hour12: false }),
                        10
                    );
                    var isOpen = hour >= 10 && hour < 20;
                    if (isOpen) {
                        dot.classList.add('is-open');
                    } else {
                        dot.classList.remove('is-open');
                    }
                    text.textContent = isOpen ? 'اکنون پاسخگو هستیم' : 'خارج از ساعت کاری(پاسخگویی در شبکه های اجتماعی)';
                } catch (e) {
                    text.textContent = '۱۰ صبح تا ۸ شب';
                }
            }

            function openPanel() {
                clearPendingClose();
                lastFocusedEl = document.activeElement;
                panel.hidden = false;
                var activatePanel = function () {
                    panel.classList.add('is-active');
                };
                if (typeof window.requestAnimationFrame === 'function') {
                    window.requestAnimationFrame(activatePanel);
                } else {
                    window.setTimeout(activatePanel, 16);
                }
                document.body.style.overflow = 'hidden';

                if (!reduced) {
                    if (foil) {
                        foil.classList.remove('is-playing');
                        void foil.offsetWidth;
                        foil.classList.add('is-playing');
                    }
                    if (phoneBadge) phoneBadge.classList.add('pulse');
                }

                var closeBtn = panel.querySelector('.close-btn');
                if (closeBtn) closeBtn.focus();
            }

            function closePanel() {
                panel.classList.remove('is-active');
                document.body.style.overflow = '';
                if (phoneBadge) phoneBadge.classList.remove('pulse');
                clearPendingClose();

                var content = panel.querySelector('.contact-panel-content');
                var hideIfStillClosed = function () {
                    if (!panel.classList.contains('is-active')) {
                        panel.hidden = true;
                    }
                };

                if (!content || reduced || !content.addEventListener) {
                    hideIfStillClosed();
                } else {
                    closeTransitionHandler = function (e) {
                        if (e.target !== content || (e.propertyName && 'transform' !== e.propertyName)) return;
                        clearPendingClose();
                        hideIfStillClosed();
                    };
                    content.addEventListener('transitionend', closeTransitionHandler);
                    closeTimer = window.setTimeout(function () {
                        clearPendingClose();
                        hideIfStillClosed();
                    }, 500);
                }
                if (lastFocusedEl) lastFocusedEl.focus();
            }

            panel.addEventListener('click', function (e) {
                if (findCloseTarget(e.target)) {
                    closePanel();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (('Escape' === e.key || 27 === e.keyCode) && panel.classList.contains('is-active')) {
                    closePanel();
                }
            });

            document.addEventListener('click', function (e) {
                var trigger = findOpenTrigger(e.target);
                if (trigger) {
                    e.preventDefault();
                    openPanel();
                }
            }, true);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initContactPanel);
        } else {
            initContactPanel();
        }
    })();
    </script>
    <?php
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'vazirmatn-font',
        'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
        array(),
        '33.003'
    );
});


/* ============================================================
 * 26) Order Tracking REST API - GET /wp-json/jluxe/v1/order-track
 * ?order_number=X&phone=Y. Public endpoint (order number + last-10-
 * digit phone match required to view), rate-limited 10 req/10min/IP.
 * (was: id=49)
 * ============================================================ */

if (!defined('JLUXE_CURRENCY_UNIT_LABEL')) {
    define('JLUXE_CURRENCY_UNIT_LABEL', 'تومان');
}
if (!defined('JLUXE_CURRENCY_DIVIDE_BY')) {
    define('JLUXE_CURRENCY_DIVIDE_BY', 1);
}

add_action('rest_api_init', function () {
    register_rest_route(
        'jluxe/v1',
        '/order-track',
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'jluxe_order_track_handler',
            'permission_callback' => '__return_true',
            'args' => array(
                'order_number' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'phone' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        )
    );
});

/**if (!function_exists('jluxe_order_track_handler')) {
    function jluxe_order_track_handler(WP_REST_Request $request) {

        if (!jluxe_track_rate_limit_ok()) {
            return jluxe_track_error_response('تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً چند دقیقه دیگر تلاش کنید.', 429);
        }

        $order_number_raw = $request->get_param('order_number');
        $phone_raw = $request->get_param('phone');

        $order_number_input = jluxe_convert_digits_to_en($order_number_raw);
        $order_number_input = trim(ltrim(trim($order_number_input), '#'));
        $phone_last10 = jluxe_normalize_phone_last10($phone_raw);

        if ('' === $order_number_input || strlen($phone_last10) < 9) {
            return jluxe_track_not_found_error();
        }

        $order = jluxe_find_order_by_number($order_number_input);

        if (!$order instanceof WC_Order) {
            return jluxe_track_not_found_error();
        }

        $order_phone_last10 = jluxe_normalize_phone_last10($order->get_billing_phone());

        if (empty($order_phone_last10) || !hash_equals($order_phone_last10, $phone_last10)) {
            return jluxe_track_not_found_error();
        }

        $response = array(
            'success' => true,
            'version' => '1.1',
            'data' => array(
                'order' => jluxe_build_order_data($order),
                'customer' => jluxe_build_customer_data($order),
                'shipping' => jluxe_build_shipping_data($order),
                'items' => jluxe_build_items_data($order),
                'timeline' => jluxe_build_timeline_data($order),
            ),
        );

        return new WP_REST_Response($response, 200);
    }
}

if (!function_exists('jluxe_find_order_by_number')) {
    function jluxe_find_order_by_number($input) {

        $numeric_id = absint($input);
        if ($numeric_id > 0) {
            $order = wc_get_order($numeric_id);

            if ($order instanceof WC_Order) {
                $display_number = (string) $order->get_order_number();

                if ($display_number === $input || (string) $order->get_id() === $input) {
                    return $order;
                }
            }
        }

        $meta_keys_to_try = array('_order_number', '_order_number_formatted', 'order_number');

        foreach ($meta_keys_to_try as $meta_key) {
            $orders = wc_get_orders(array(
                'limit' => 1,
                'meta_key' => $meta_key,
                'meta_value' => $input,
                'return' => 'objects',
            ));

            if (!empty($orders) && $orders[0] instanceof WC_Order) {
                return $orders[0];
            }
        }

        return null;
    }
}

if (!function_exists('jluxe_convert_digits_to_en')) {
    function jluxe_convert_digits_to_en($string) {
        $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $arabic = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
        $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        $string = str_replace($persian, $english, (string) $string);
        $string = str_replace($arabic, $english, $string);

        return $string;
    }
}

if (!function_exists('jluxe_convert_digits_to_fa')) {
    function jluxe_convert_digits_to_fa($string) {
        $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');

        return str_replace($english, $persian, (string) $string);
    }
}

if (!function_exists('jluxe_normalize_phone_last10')) {
    function jluxe_normalize_phone_last10($phone) {
        $phone = jluxe_convert_digits_to_en($phone);
        $digits_only = preg_replace('/\D+/', '', $phone);

        if (strlen($digits_only) < 10) {
            return '';
        }

        return substr($digits_only, -10);
    }
}

if (!function_exists('jluxe_track_error_response')) {
    function jluxe_track_error_response($message, $status = 404) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => $message,
            ),
            $status
        );
    }
}

if (!function_exists('jluxe_track_not_found_error')) {
    function jluxe_track_not_found_error() {
        return jluxe_track_error_response('اطلاعات سفارش صحیح نیست.', 404);
    }
}

if (!function_exists('jluxe_track_rate_limit_ok')) {
    function jluxe_track_rate_limit_ok() {
        $ip = jluxe_get_client_ip();
        $key = 'jluxe_track_' . md5($ip);
        $window = 10 * MINUTE_IN_SECONDS;
        $max = 10;

        $data = get_transient($key);

        if (false === $data || !is_array($data) || empty($data['expires_at'])) {
            set_transient(
                $key,
                array(
                    'count' => 1,
                    'expires_at' => time() + $window,
                ),
                $window
            );
            return true;
        }

        if ($data['count'] >= $max) {
            return false;
        }

        $remaining = max(1, $data['expires_at'] - time());

        $data['count']++;
        set_transient($key, $data, $remaining);

        return true;
    }
}

if (!function_exists('jluxe_get_client_ip')) {
    function jluxe_get_client_ip() {
        $keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip_list = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])));
                return trim($ip_list[0]);
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('jluxe_gregorian_timestamp_to_jalali_string')) {
    function jluxe_gregorian_timestamp_to_jalali_string($timestamp, $format = 'Y/m/d H:i') {

        if (function_exists('jdate')) {
            return jdate($format, $timestamp);
        }

        list($jy, $jm, $jd) = jluxe_gregorian_to_jalali(
            (int) gmdate('Y', $timestamp),
            (int) gmdate('n', $timestamp),
            (int) gmdate('j', $timestamp)
        );

        $time_part = gmdate('H:i', $timestamp);
        $jalali = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        if (false !== strpos($format, 'H:i')) {
            $jalali .= ' ' . $time_part;
        }

        return $jalali;
    }
}

if (!function_exists('jluxe_gregorian_to_jalali')) {
    function jluxe_gregorian_to_jalali($gy, $gm, $gd) {
        $g_days_in_month = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666
            + (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            + $gd
            + $g_days_in_month[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return array($jy, $jm, $jd);
    }
}

if (!function_exists('jluxe_format_price')) {
    function jluxe_format_price($raw_amount) {
        $divided = ((float) $raw_amount) / JLUXE_CURRENCY_DIVIDE_BY;

        $formatted_en = number_format($divided, 0);
        $formatted_fa = jluxe_convert_digits_to_fa($formatted_en) . ' ' . JLUXE_CURRENCY_UNIT_LABEL;

        return array(
            'raw' => $divided,
            'formatted' => $formatted_fa,
            'unit' => JLUXE_CURRENCY_UNIT_LABEL,
        );
    }
}

if (!function_exists('jluxe_build_order_data')) {
    function jluxe_build_order_data(WC_Order $order) {
        $status = $order->get_status();
        $date_obj = $order->get_date_created();
        $timestamp = $date_obj ? $date_obj->getTimestamp() : time();

        return array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $status,
            'status_label' => jluxe_get_status_label($status),
            'created_date' => array(
                'gregorian' => $date_obj ? $date_obj->date('Y-m-d H:i') : null,
                'jalali' => $date_obj ? jluxe_gregorian_timestamp_to_jalali_string($timestamp) : null,
            ),
            'total' => jluxe_format_price($order->get_total()),
            'payment_method' => $order->get_payment_method_title(),
        );
    }
}

if (!function_exists('jluxe_build_customer_data')) {
    function jluxe_build_customer_data(WC_Order $order) {
        $address_parts = array_filter(array(
            $order->get_billing_state(),
            $order->get_billing_city(),
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
        ));

        return array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'full_name' => trim($order->get_formatted_billing_full_name()),
            'phone' => $order->get_billing_phone(),
            'address' => implode('، ', $address_parts),
        );
    }
}

if (!function_exists('jluxe_build_shipping_data')) {
    function jluxe_build_shipping_data(WC_Order $order) {
        $tracking = jluxe_get_tracking_info($order);

        return array(
            'shipping_method' => $order->get_shipping_method() ? $order->get_shipping_method() : null,
            'shipping_company' => $tracking['shipping_company'],
            'tracking_code' => $tracking['tracking_code'],
            'tracking_date' => $tracking['tracking_date'],
        );
    }
}

if (!function_exists('jluxe_get_tracking_info')) {
    function jluxe_get_tracking_info(WC_Order $order) {

        $company = $order->get_meta('_jluxe_shipping_company');
        $code = $order->get_meta('_jluxe_tracking_code');
        $date = $order->get_meta('_jluxe_tracking_date');

        if ('' !== $code) {
            return array(
                'shipping_company' => ('' !== $company) ? $company : null,
                'tracking_code' => $code,
                'tracking_date' => ('' !== $date) ? $date : null,
            );
        }

        $tracking_items = $order->get_meta('_wc_shipment_tracking_items');

        if (is_array($tracking_items) && !empty($tracking_items)) {
            $first = reset($tracking_items);

            if (!empty($first['tracking_number'])) {
                $provider = !empty($first['tracking_provider'])
                    ? $first['tracking_provider']
                    : (!empty($first['custom_tracking_provider']) ? $first['custom_tracking_provider'] : null);

                return array(
                    'shipping_company' => $provider,
                    'tracking_code' => $first['tracking_number'],
                    'tracking_date' => !empty($first['date_shipped']) ? $first['date_shipped'] : null,
                );
            }
        }

        $fallback_code_keys = array('_tracking_number', '_tracking_code', '_shipment_tracking_number');
        $fallback_company_keys = array('_tracking_company', '_tracking_provider', '_shipping_company');
        $fallback_date_keys = array('_tracking_date', '_date_shipped', '_shipped_date');

        $found_code = '';
        foreach ($fallback_code_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if ('' !== $value) {
                $found_code = $value;
                break;
            }
        }

        if ('' === $found_code) {
            return array(
                'shipping_company' => null,
                'tracking_code' => null,
                'tracking_date' => null,
            );
        }

        $found_company = '';
        foreach ($fallback_company_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if ('' !== $value) {
                $found_company = $value;
                break;
            }
        }

        $found_date = '';
        foreach ($fallback_date_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if ('' !== $value) {
                $found_date = $value;
                break;
            }
        }

        return array(
            'shipping_company' => ('' !== $found_company) ? $found_company : null,
            'tracking_code' => $found_code,
            'tracking_date' => ('' !== $found_date) ? $found_date : null,
        );
    }
}

if (!function_exists('jluxe_build_items_data')) {
    function jluxe_build_items_data(WC_Order $order) {
        $items = array();

        foreach ($order->get_items() as $item_id => $item) {

            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            $attributes = array();
            foreach ($item->get_formatted_meta_data() as $meta) {
                $attributes[] = array(
                    'name' => wp_strip_all_tags($meta->display_key),
                    'value' => wp_strip_all_tags($meta->display_value),
                );
            }

            $thumbnail_url = wc_placeholder_img_src('thumbnail');
            $full_url = wc_placeholder_img_src('full');

            if ($product) {
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $thumb_src = wp_get_attachment_image_url($image_id, 'thumbnail');
                    $full_src = wp_get_attachment_image_url($image_id, 'full');

                    if ($thumb_src) {
                        $thumbnail_url = $thumb_src;
                    }
                    if ($full_src) {
                        $full_url = $full_src;
                    }
                }
            }

            $unit_price = $order->get_item_total($item, false, false);
            $line_total = $order->get_line_total($item, false, false);

            $items[] = array(
                'id' => $item_id,
                'product_id' => $product ? $product->get_id() : 0,
                'product_name' => $item->get_name(),
                'image' => array(
                    'thumbnail' => $thumbnail_url,
                    'full' => $full_url,
                ),
                'quantity' => (int) $item->get_quantity(),
                'price' => jluxe_format_price($unit_price),
                'subtotal' => jluxe_format_price($line_total),
                'sku' => $product ? $product->get_sku() : null,
                'attributes' => $attributes,
            );
        }

        return $items;
    }
}

if (!function_exists('jluxe_get_status_label')) {
    function jluxe_get_status_label($status) {
        $map = array(
            'pending' => 'در انتظار پرداخت',
            'on-hold' => 'در انتظار بررسی پرداخت',
            'processing' => 'در حال آماده‌سازی',
            'completed' => 'تحویل شده',
            'cancelled' => 'لغو شده',
            'failed' => 'پرداخت ناموفق',
            'refunded' => 'بازگشت وجه',
        );

        return isset($map[$status]) ? $map[$status] : $status;
    }
}

if (!function_exists('jluxe_build_timeline_data')) {
    function jluxe_build_timeline_data(WC_Order $order) {

        $steps = array(
            1 => 'ثبت سفارش',
            2 => 'در انتظار پرداخت',
            3 => 'تایید پرداخت',
            4 => 'در حال آماده‌سازی',
            5 => 'ارسال شده',
            6 => 'تحویل شده',
        );

        $status = $order->get_status();
        $tracking = jluxe_get_tracking_info($order);
        $tracking_code = $tracking['tracking_code'];
        $is_cancelled = in_array($status, array('cancelled', 'failed', 'refunded'), true);

        $current_step = 1;

        if (!$is_cancelled) {
            switch ($status) {
                case 'pending':
                case 'on-hold':
                    $current_step = 2;
                    break;
                case 'processing':
                    $current_step = (!empty($tracking_code)) ? 5 : 4;
                    break;
                case 'completed':
                    $current_step = 6;
                    break;
                default:
                    $current_step = 3;
            }
        }

        $timeline = array();
        foreach ($steps as $step_number => $label) {
            $timeline[] = array(
                'step' => $step_number,
                'label' => $label,
                'done' => !$is_cancelled && $step_number < $current_step,
                'active' => !$is_cancelled && $step_number === $current_step,
            );
        }

        return array(
            'is_cancelled' => $is_cancelled,
            'cancel_label' => $is_cancelled ? jluxe_get_status_label($status) : null,
            'current_step' => $current_step,
            'steps' => $timeline,
        );
    }
}


/* ============================================================
 * 27) special-sale category auto-sync: keeps the "فروش ویژه"
 * (product_cat, slug 'sale') term in sync with the _is_special_sale
 * postmeta flag, whichever side (admin or the mobile app's REST calls)
 * changes it. Includes a one-time backfill for products that already
 * had the flag set before this went live. (was: id=90)
 * ============================================================ */

if (!function_exists('jluxe_sync_special_sale_term')) {
    function jluxe_sync_special_sale_term(int $product_id): void {
        if ('product' !== get_post_type($product_id)) {
            return;
        }
        $term = get_term_by('slug', 'sale', 'product_cat');
        if (!$term || is_wp_error($term)) {
            return;
        }
        $is_special = 'yes' === get_post_meta($product_id, '_is_special_sale', true);
        $current_terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        if (is_wp_error($current_terms)) {
            return;
        }
        $current_terms = array_map('intval', $current_terms);
        $has_term = in_array((int) $term->term_id, $current_terms, true);

        if ($is_special && !$has_term) {
            wp_set_post_terms($product_id, array_merge($current_terms, array($term->term_id)), 'product_cat', true);
        } elseif (!$is_special && $has_term) {
            wp_set_post_terms($product_id, array_diff($current_terms, array($term->term_id)), 'product_cat', false);
        }
    }
}

foreach (array('updated_post_meta', 'added_post_meta') as $jluxe_special_sale_hook) {
    add_action($jluxe_special_sale_hook, function ($meta_id, $post_id, $meta_key) {
        if ('_is_special_sale' === $meta_key) {
            jluxe_sync_special_sale_term((int) $post_id);
        }
    }, 10, 3);
}
add_action('deleted_post_meta', function ($meta_ids, $post_id, $meta_key) {
    if ('_is_special_sale' === $meta_key) {
        jluxe_sync_special_sale_term((int) $post_id);
    }
}, 10, 3);

add_action('jluxe_special_sale_backfill_batch', function () {
    if (get_option('jluxe_special_sale_backfilled')) {
        return;
    }

    global $wpdb;
    $cursor = absint(get_option('jluxe_special_sale_backfill_cursor', 0));
    $query = $wpdb->prepare(
        "SELECT DISTINCT p.ID
        FROM {$wpdb->posts} AS p
        INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND pm.meta_key = %s
          AND pm.meta_value = %s
          AND p.ID > %d
        ORDER BY p.ID ASC
        LIMIT %d",
        'product',
        'publish',
        '_is_special_sale',
        'yes',
        $cursor,
        100
    );
    $ids = $wpdb->get_col($query);

    if (!is_array($ids)) {
        wp_schedule_single_event(time() + 60, 'jluxe_special_sale_backfill_batch');
        return;
    }
    if (empty($ids)) {
        update_option('jluxe_special_sale_backfilled', 1, false);
        delete_option('jluxe_special_sale_backfill_cursor');
        return;
    }

    foreach ($ids as $id) {
        jluxe_sync_special_sale_term((int) $id);
    }
    update_option('jluxe_special_sale_backfill_cursor', max(array_map('absint', $ids)), false);
    wp_schedule_single_event(time() + 15, 'jluxe_special_sale_backfill_batch');
});

add_action('init', function () {
    if (get_option('jluxe_special_sale_backfilled') || wp_next_scheduled('jluxe_special_sale_backfill_batch')) {
        return;
    }
    wp_schedule_single_event(time() + 15, 'jluxe_special_sale_backfill_batch');
}, 20);


/* ============================================================
 * 28) product-category search box in the classic product editor.
 * Was never in Code Snippets Pro - a standalone local file the store
 * owner had (my-custom-codes.php). Admin-only, cosmetic/UX, no data
 * writes - safe to fold in as-is.
 * ============================================================ */

add_action('admin_footer', 'add_product_category_search_filter');

if (!function_exists('add_product_category_search_filter')) {
    function add_product_category_search_filter() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            var $catList = $('#product_cat-all');
            if ($catList.length) {
                var $searchInput = $('<input type="text" id="custom-cat-search" placeholder="جستجوی دسته‌بندی..." style="width: 100%; margin-bottom: 12px; padding: 6px 8px; box-sizing: border-box; border-radius: 4px; border: 1px solid #8c8f94;">');
                $catList.prepend($searchInput);

                $searchInput.on('keyup', function() {
                    var searchTerm = $(this).val().toLowerCase();
                    $('#product_catchecklist li').each(function() {
                        var text = $(this).children('label').text().toLowerCase();
                        if (text.indexOf(searchTerm) > -1) {
                            $(this).show();
                            $(this).parents('li').show();
                        } else {
                            if ($(this).find('li:visible').length === 0) {
                                $(this).hide();
                            }
                        }
                    });
                });

                $searchInput.on('keypress', function(e) {
                    if (e.which == 13) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
        </script>
        <?php
    }
}
