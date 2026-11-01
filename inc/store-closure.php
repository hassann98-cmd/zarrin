<?php
/**
 * تعطیلی موقتِ سفارش‌گیری — محصولات و سفارش‌های قبلی همچنان در دسترس می‌مانند؛
 * فقط ایجاد سفارش تازه از مسیرهای عمومیِ فروشگاه متوقف می‌شود.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_STORE_CLOSURE_OPTION = 'jluxe_store_closure';
const JLUXE_STORE_CLOSURE_DEFAULT_MESSAGE = 'ثبت سفارش موقتاً متوقف است. به‌محض فعال‌شدن دوبارهٔ فروشگاه اطلاع‌رسانی می‌کنیم. سفارش‌های قبلی همچنان قابل پیگیری هستند.';
const JLUXE_STORE_CLOSURE_REOPEN_HOOK = 'jluxe_store_closure_auto_reopen';

/** منطقهٔ زمانیِ تنظیم‌شده در وردپرس؛ زمان‌های فرم هم با همین منطقه تفسیر می‌شوند. */
function jluxe_store_closure_timezone(): DateTimeZone {
	try {
		if ( function_exists( 'wp_timezone' ) ) {
			$timezone = wp_timezone();
			if ( $timezone instanceof DateTimeZone ) {
				return $timezone;
			}
		}

		$timezone_name = (string) get_option( 'timezone_string', 'UTC' );
		return new DateTimeZone( '' !== $timezone_name ? $timezone_name : 'UTC' );
	} catch ( Throwable $error ) {
		return new DateTimeZone( 'UTC' );
	}
}

/** تنظیماتِ مستقلِ وضعیت فروشگاه؛ هیچ‌کدام از گزینه‌های نوار عادی را تغییر نمی‌دهد. */
function jluxe_store_closure_settings(): array {
	$stored = get_option( JLUXE_STORE_CLOSURE_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();

	$message = isset( $stored['message'] ) && is_scalar( $stored['message'] )
		? sanitize_textarea_field( (string) $stored['message'] )
		: '';

	return array(
		'closed'    => ! empty( $stored['closed'] ),
		'message'   => '' !== trim( $message ) ? $message : JLUXE_STORE_CLOSURE_DEFAULT_MESSAGE,
		'reopen_at' => isset( $stored['reopen_at'] ) ? max( 0, (int) $stored['reopen_at'] ) : 0,
	);
}

/** وضعیت فعال؛ تاریخِ بازگشاییِ رسیده، حتی پیش از اجرای پاک‌سازی، به معنیِ باز است. */
function jluxe_store_closure_is_closed(): bool {
	$settings = jluxe_store_closure_settings();
	if ( empty( $settings['closed'] ) ) {
		return false;
	}

	return 0 === $settings['reopen_at'] || $settings['reopen_at'] > time();
}

function jluxe_store_closure_message(): string {
	return jluxe_store_closure_settings()['message'];
}

/** تاریخِ ورودیِ datetime-local را بر اساسِ منطقهٔ زمانیِ سایت به timestamp تبدیل می‌کند. */
function jluxe_store_closure_parse_local_datetime( string $local_value ) {
	$local_value = trim( $local_value );
	if ( '' === $local_value ) {
		return 0;
	}

	$format   = 'Y-m-d\TH:i';
	$datetime = DateTimeImmutable::createFromFormat( '!' . $format, $local_value, jluxe_store_closure_timezone() );
	$errors   = DateTimeImmutable::getLastErrors();
	if ( ! $datetime || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $datetime->format( $format ) !== $local_value ) {
		return false;
	}

	$timestamp = $datetime->getTimestamp();
	return $timestamp > time() ? $timestamp : false;
}

/** حذفِ رخدادِ قبلی و ثبتِ زمان‌بندیِ جدیدِ بازگشایی (در صورتِ انتخاب‌شدن). */
function jluxe_store_closure_schedule_reopen( int $timestamp ): void {
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( JLUXE_STORE_CLOSURE_REOPEN_HOOK );
	}
	if ( $timestamp > time() && function_exists( 'wp_schedule_single_event' ) ) {
		wp_schedule_single_event( $timestamp, JLUXE_STORE_CLOSURE_REOPEN_HOOK );
	}
}

/** پاک‌کردنِ کشِ صفحه‌ها تا پیامِ وضعیت بلافاصله روی همهٔ صفحه‌ها عوض شود. */
function jluxe_store_closure_purge_caches(): void {
	if ( function_exists( 'do_action' ) ) {
		// افزونه‌های رایجِ کش؛ actionهای ناموجود در وردپرس بی‌اثرند.
		do_action( 'litespeed_purge_all' );
		do_action( 'rocket_clean_domain' );
		do_action( 'w3tc_flush_all' );
	}
}

/** ذخیرهٔ امنِ وضعیت؛ تاریخِ نامعتبر/گذشته هیچ تغییری در وضعیتِ فعلی ایجاد نمی‌کند. */
function jluxe_store_closure_save( string $action, string $message, string $reopen_local = '' ): array {
	$action = sanitize_key( $action );
	if ( ! in_array( $action, array( 'close', 'open' ), true ) ) {
		return array( 'ok' => false, 'code' => 'invalid_action' );
	}

	$message = sanitize_textarea_field( $message );
	$message = trim( $message );
	if ( '' === $message ) {
		$message = JLUXE_STORE_CLOSURE_DEFAULT_MESSAGE;
	}
	$characters = preg_split( '//u', $message, -1, PREG_SPLIT_NO_EMPTY );
	if ( is_array( $characters ) && count( $characters ) > 500 ) {
		$message = implode( '', array_slice( $characters, 0, 500 ) );
	} elseif ( false === $characters ) {
		$message = substr( $message, 0, 500 );
	}

	$reopen_at = 0;
	if ( 'close' === $action && '' !== trim( $reopen_local ) ) {
		$parsed = jluxe_store_closure_parse_local_datetime( $reopen_local );
		if ( false === $parsed ) {
			return array( 'ok' => false, 'code' => 'invalid_reopen_time' );
		}
		$reopen_at = (int) $parsed;
	}

	$stored = array(
		'closed'    => 'close' === $action,
		'message'   => $message,
		'reopen_at' => $reopen_at,
	);
	update_option( JLUXE_STORE_CLOSURE_OPTION, $stored, false );
	jluxe_store_closure_schedule_reopen( $reopen_at );
	jluxe_store_closure_purge_caches();

	return array( 'ok' => true, 'code' => 'open' === $action ? 'opened' : 'closed' );
}

/** بازگشایی خودکار برای WP-Cron و نیز اولین درخواست پس از سررسید. */
function jluxe_store_closure_maybe_auto_reopen(): void {
	$settings = jluxe_store_closure_settings();
	if ( empty( $settings['closed'] ) || 0 === $settings['reopen_at'] || $settings['reopen_at'] > time() ) {
		return;
	}

	$settings['closed']    = false;
	$settings['reopen_at'] = 0;
	update_option( JLUXE_STORE_CLOSURE_OPTION, $settings, false );
	jluxe_store_closure_schedule_reopen( 0 );
	jluxe_store_closure_purge_caches();
}
add_action( 'init', 'jluxe_store_closure_maybe_auto_reopen', 1 );
add_action( JLUXE_STORE_CLOSURE_REOPEN_HOOK, 'jluxe_store_closure_maybe_auto_reopen' );

/** هنگامِ تعطیلی، از کش‌شدنِ دوبارهٔ HTML با پیامِ قدیمی جلوگیری می‌کند. */
function jluxe_store_closure_prevent_page_cache(): void {
	jluxe_store_closure_maybe_auto_reopen();
	if ( ( function_exists( 'is_admin' ) && is_admin() ) || ! jluxe_store_closure_is_closed() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( function_exists( 'do_action' ) ) {
		do_action( 'litespeed_control_set_nocache', 'jluxe-store-closure' );
	}
	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}
}
add_action( 'after_setup_theme', 'jluxe_store_closure_prevent_page_cache', 1 );

/** بازگشتِ موقتِ فقط-نمایشی برای نوار؛ گزینهٔ اصلی هرگز با این آرایه بازنویسی نمی‌شود. */
function jluxe_store_closure_announcement_override( array $announcement_settings ): array {
	if ( ! jluxe_store_closure_is_closed() ) {
		return $announcement_settings;
	}

	$announcement_settings['enabled']      = true;
	$announcement_settings['display_mode'] = 'text';
	$announcement_settings['message']      = jluxe_store_closure_message();
	$announcement_settings['link_text']    = '';
	$announcement_settings['link_url']     = '';
	$announcement_settings['dismissible']  = false;

	return $announcement_settings;
}

/** کنترلِ افزودن به سبد برای فرم‌های استاندارد ووکامرس و endpoint سفارشیِ سبد. */
function jluxe_store_closure_validate_add_to_cart( $passed, $product_id = 0, $quantity = 1, $variation_id = 0, $variations = array() ) {
	if ( ! jluxe_store_closure_is_closed() ) {
		return $passed;
	}

	if ( function_exists( 'wc_add_notice' ) ) {
		wc_add_notice( jluxe_store_closure_message(), 'error' );
	}
	return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'jluxe_store_closure_validate_add_to_cart', 9999, 5 );

/**
 * آیا این نشست دقیقاً در حال پرداختِ یک سفارشِ موجودِ غیررایگان است؟
 * checkout/statusهای پیش‌نویس و سبدِ تغییریافته مجاز نیستند؛ retry سفارشِ pending/failed
 * با همان سبد می‌تواند از مسیرِ پرداختِ قبلی ادامه پیدا کند.
 */
function jluxe_store_closure_has_existing_payment_order( string $flow ): bool {
	if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$woocommerce = WC();
	$session     = is_object( $woocommerce ) && isset( $woocommerce->session ) ? $woocommerce->session : null;
	$cart        = is_object( $woocommerce ) && isset( $woocommerce->cart ) ? $woocommerce->cart : null;
	if ( ! is_object( $session ) || ! method_exists( $session, 'get' ) || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart_hash' ) ) {
		return false;
	}

	$session_keys = 'store_api' === $flow
		? array( 'store_api_draft_order', 'order_awaiting_payment', 'checkout_pending_order_id' )
		: array( 'order_awaiting_payment', 'checkout_pending_order_id', 'store_api_draft_order' );
	$cart_hash = (string) $cart->get_cart_hash();
	if ( '' === $cart_hash ) {
		return false;
	}

	foreach ( $session_keys as $session_key ) {
		$order_id = absint( $session->get( $session_key, 0 ) );
		if ( 0 === $order_id ) {
			continue;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'needs_payment' ) || ! $order->needs_payment() || ! method_exists( $order, 'has_cart_hash' ) || ! $order->has_cart_hash( $cart_hash ) ) {
			continue;
		}
		if ( method_exists( $order, 'get_status' ) && 'checkout-draft' === $order->get_status() ) {
			continue;
		}

		$customer_id = method_exists( $order, 'get_customer_id' ) ? absint( $order->get_customer_id() ) : 0;
		$current_user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		if ( $customer_id > 0 && $customer_id !== $current_user_id ) {
			continue;
		}

		return true;
	}

	return false;
}

/** از ساخته‌شدنِ سفارشِ تازه در checkout استاندارد جلوگیری می‌کند، اما retry سفارش موجود را دست‌نخورده می‌گذارد. */
function jluxe_store_closure_validate_checkout( array $data, $errors ): void {
	if ( ! jluxe_store_closure_is_closed() || jluxe_store_closure_has_existing_payment_order( 'classic' ) || ! is_object( $errors ) || ! method_exists( $errors, 'add' ) ) {
		return;
	}

	$errors->add(
		'jluxe_store_closed',
		jluxe_store_closure_message(),
		array( 'id' => 'place_order' )
	);
}
add_action( 'woocommerce_after_checkout_validation', 'jluxe_store_closure_validate_checkout', 9999, 2 );

/** بستنِ Store API برای add-item و سفارشِ تازه؛ retry سفارشِ موجود همچنان قابل پرداخت می‌ماند. */
function jluxe_store_closure_block_store_api( $response, $handler, $request ) {
	if ( ! jluxe_store_closure_is_closed() || ! ( $request instanceof WP_REST_Request ) ) {
		return $response;
	}

	$route  = '/' . trim( (string) $request->get_route(), '/' );
	$method = strtoupper( (string) $request->get_method() );
	if ( 'POST' === $method && preg_match( '#^/wc/store/v[0-9]+/cart/add-item$#', $route ) ) {
		return new WP_Error(
			'jluxe_store_closed',
			jluxe_store_closure_message(),
			array( 'status' => 503 )
		);
	}

	if ( 'POST' !== $method || ! preg_match( '#^/wc/store/v[0-9]+/checkout$#', $route ) ) {
		return $response;
	}

	if ( jluxe_store_closure_has_existing_payment_order( 'store_api' ) ) {
		return $response;
	}

	return new WP_Error(
		'jluxe_store_closed',
		jluxe_store_closure_message(),
		array( 'status' => 503 )
	);
}
add_filter( 'rest_request_before_callbacks', 'jluxe_store_closure_block_store_api', 9999, 3 );

function jluxe_store_closure_can_manage(): bool {
	return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
}

function jluxe_store_closure_datetime_input_value( int $timestamp ): string {
	if ( $timestamp <= 0 ) {
		return '';
	}
	return ( new DateTimeImmutable( '@' . $timestamp ) )
		->setTimezone( jluxe_store_closure_timezone() )
		->format( 'Y-m-d\TH:i' );
}

function jluxe_store_closure_datetime_label( int $timestamp ): string {
	if ( $timestamp <= 0 ) {
		return '';
	}
	if ( function_exists( 'wp_date' ) ) {
		return wp_date( 'Y/m/d H:i', $timestamp, jluxe_store_closure_timezone() );
	}
	return ( new DateTimeImmutable( '@' . $timestamp ) )
		->setTimezone( jluxe_store_closure_timezone() )
		->format( 'Y/m/d H:i' );
}

function jluxe_store_closure_result_message( string $result ): string {
	$messages = array(
		'closed'                => 'ثبت سفارش تازه متوقف شد؛ محصولات و سفارش‌های قبلی همچنان در دسترس هستند.',
		'opened'                => 'فروشگاه دوباره باز شد و تنظیمات عادی نوار اطلاع‌رسانی برگشت.',
		'invalid_reopen_time'   => 'زمان بازگشایی معتبر نیست یا از زمان فعلی گذشته است؛ وضعیت فروشگاه تغییر نکرد.',
		'invalid_action'        => 'درخواست معتبر نبود؛ وضعیت فروشگاه تغییر نکرد.',
		'error'                 => 'ذخیرهٔ وضعیت فروشگاه انجام نشد؛ دوباره تلاش کنید.',
	);
	return $messages[ $result ] ?? '';
}

/** رندرِ فرمِ مشترک در داشبورد زرین و صفحهٔ مدیریت ووکامرس. */
function jluxe_render_store_closure_control( string $return_to = 'store-status' ): void {
	if ( ! jluxe_store_closure_can_manage() ) {
		return;
	}

	$settings = jluxe_store_closure_settings();
	$closed   = jluxe_store_closure_is_closed();
	$timezone = jluxe_store_closure_timezone();
	$return_to = 'dashboard' === $return_to ? 'dashboard' : 'store-status';
	$result    = isset( $_GET['jluxe_store_status_result'] ) && is_scalar( $_GET['jluxe_store_status_result'] )
		? sanitize_key( wp_unslash( (string) $_GET['jluxe_store_status_result'] ) )
		: '';
	$result_message = jluxe_store_closure_result_message( $result );
	?>
	<section class="jluxe-store-closure-panel" aria-labelledby="jluxe-store-closure-title">
		<div class="jluxe-store-closure-heading">
			<span class="dashicons dashicons-store" aria-hidden="true"></span>
			<div>
				<h2 id="jluxe-store-closure-title">وضعیت سفارش‌گیری</h2>
				<p><?php echo $closed ? 'ثبت سفارش جدید متوقف است؛ محصولات همچنان نمایش داده می‌شوند.' : 'فروشگاه باز است و سفارش‌گیری عادی انجام می‌شود.'; ?></p>
			</div>
			<strong class="jluxe-store-closure-badge <?php echo $closed ? 'is-closed' : 'is-open'; ?>" role="status">
				<?php echo $closed ? 'موقتاً بسته' : 'باز'; ?>
			</strong>
		</div>

		<?php if ( '' !== $result_message ) : ?>
			<div class="notice <?php echo 'closed' === $result || 'opened' === $result ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $result_message ); ?></p></div>
		<?php endif; ?>

		<?php if ( $closed && ! empty( $settings['reopen_at'] ) ) : ?>
			<p class="jluxe-store-closure-scheduled">بازگشاییِ زمان‌بندی‌شده: <strong><?php echo esc_html( jluxe_store_closure_datetime_label( (int) $settings['reopen_at'] ) ); ?></strong> (<?php echo esc_html( $timezone->getName() ); ?>)</p>
		<?php endif; ?>

		<form class="jluxe-store-closure-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jluxe_update_store_closure" />
			<input type="hidden" name="store_closure_return_to" value="<?php echo esc_attr( $return_to ); ?>" />
			<?php wp_nonce_field( 'jluxe_update_store_closure', 'jluxe_store_closure_nonce' ); ?>

			<div class="jluxe-store-closure-field">
				<label for="jluxe-store-closure-message">پیام موقتِ نوار اطلاع‌رسانی</label>
				<textarea id="jluxe-store-closure-message" name="store_closure_message" rows="3" maxlength="500" required><?php echo esc_textarea( $settings['message'] ); ?></textarea>
				<p class="description">این پیام فقط هنگامِ تعطیلی نمایش داده می‌شود؛ متن، تصویر یا پیام‌های عادیِ نوار بدون تغییر باقی می‌مانند.</p>
			</div>

			<div class="jluxe-store-closure-field">
				<label for="jluxe-store-closure-reopen">بازگشایی خودکار (اختیاری)</label>
				<input id="jluxe-store-closure-reopen" type="datetime-local" name="store_closure_reopen_at" value="<?php echo esc_attr( jluxe_store_closure_datetime_input_value( (int) $settings['reopen_at'] ) ); ?>" />
				<p class="description">زمان بر اساس منطقهٔ زمانیِ سایت، <code><?php echo esc_html( $timezone->getName() ); ?></code> است. خالی بگذارید تا فروشگاه را دستی باز کنید. اگر WP-Cron سایت اجرا نشود، بازگشایی ممکن است تا اولین درخواستِ بعد از زمان تعیین‌شده کمی به‌تأخیر بیفتد.</p>
			</div>

			<div class="jluxe-store-closure-actions">
				<button class="button button-primary jluxe-store-closure-submit" type="submit" name="store_closure_action" value="close">
					<?php echo $closed ? 'ذخیرهٔ پیام و زمان بازگشایی' : 'بستن موقت فروشگاه'; ?>
				</button>
				<?php if ( $closed ) : ?>
					<button class="button jluxe-store-closure-open" type="submit" name="store_closure_action" value="open">بازگشایی فروشگاه</button>
				<?php endif; ?>
			</div>
		</form>
	</section>
	<?php
}

function jluxe_render_store_closure_admin_page(): void {
	if ( ! jluxe_store_closure_can_manage() ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	?>
	<div class="wrap jluxe-settings" dir="rtl">
		<h1><span class="dashicons dashicons-store" aria-hidden="true"></span> وضعیت فروشگاه</h1>
		<p class="description">با بستن موقت، کاتالوگ و صفحه‌های محصول برای بازدیدکنندگان باقی می‌مانند؛ فقط افزودن به سبد و ثبت سفارش جدید متوقف می‌شود. پرداخت یا پیگیری سفارش‌های قبلی تغییری نمی‌کند.</p>
		<?php jluxe_render_store_closure_control( 'store-status' ); ?>
	</div>
	<?php
}

function jluxe_register_store_closure_admin_page(): void {
	if ( function_exists( 'add_submenu_page' ) ) {
		add_submenu_page(
			'woocommerce',
			'وضعیت فروشگاه — زرین',
			'وضعیت سفارش‌گیری',
			'manage_woocommerce',
			'jluxe-store-status',
			'jluxe_render_store_closure_admin_page'
		);
	}
}
add_action( 'admin_menu', 'jluxe_register_store_closure_admin_page', 35 );

function jluxe_handle_store_closure_admin_post(): void {
	if ( ! jluxe_store_closure_can_manage() ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_update_store_closure', 'jluxe_store_closure_nonce' );

	$action = isset( $_POST['store_closure_action'] ) && is_scalar( $_POST['store_closure_action'] )
		? sanitize_key( wp_unslash( (string) $_POST['store_closure_action'] ) )
		: '';
	$message = isset( $_POST['store_closure_message'] ) && is_scalar( $_POST['store_closure_message'] )
		? sanitize_textarea_field( wp_unslash( (string) $_POST['store_closure_message'] ) )
		: '';
	$reopen_local = isset( $_POST['store_closure_reopen_at'] ) && is_scalar( $_POST['store_closure_reopen_at'] )
		? sanitize_text_field( wp_unslash( (string) $_POST['store_closure_reopen_at'] ) )
		: '';
	$result = jluxe_store_closure_save( $action, $message, $reopen_local );
	$result_slug = ! empty( $result['ok'] )
		? $result['code']
		: ( 'invalid_reopen_time' === ( $result['code'] ?? '' ) ? 'invalid_reopen_time' : ( 'invalid_action' === ( $result['code'] ?? '' ) ? 'invalid_action' : 'error' ) );

	$return_to = isset( $_POST['store_closure_return_to'] ) && is_scalar( $_POST['store_closure_return_to'] )
		? sanitize_key( wp_unslash( (string) $_POST['store_closure_return_to'] ) )
		: 'store-status';
	$page_slug = 'dashboard' === $return_to ? JLUXE_SETTINGS_MENU_SLUG : 'jluxe-store-status';
	$redirect  = admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) . '&jluxe_store_status_result=' . rawurlencode( $result_slug ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_jluxe_update_store_closure', 'jluxe_handle_store_closure_admin_post' );
