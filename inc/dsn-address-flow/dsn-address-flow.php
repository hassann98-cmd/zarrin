<?php
/**
 * Plugin Name: Desiano Address Checkout Flow
 * Description: ویجت المنتور: انتخاب آدرس با نقشه (نشان)، ثبت آدرس، خلاصه سفارش و انتخاب درگاه (ایجکس) - مهمان و کاربر. با کنترل‌های کامل استایل. شورتکد: [dsn_address_flow]
 * Version: 1.3.1
 * Text Domain: dsn-acf
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class DSN_ACF {
	const META = 'dsn_addresses';
	const SEL  = 'dsn_selected_address';
	const VIA  = 'dsn-address-flow';

	static function init() {
		add_shortcode( 'dsn_address_flow', [ __CLASS__, 'shortcode' ] );
		foreach ( [ 'list', 'save', 'delete', 'select', 'summary', 'gateways', 'pay', 'geocode' ] as $a ) {
			add_action( "wp_ajax_dsn_$a", [ __CLASS__, "ajax_$a" ] );
			add_action( "wp_ajax_nopriv_dsn_$a", [ __CLASS__, "ajax_$a" ] );
		}
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'maybe_empty_cart' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ], 5 );
		add_action( 'elementor/widgets/register', function ( $wm ) {
			require_once __DIR__ . '/includes/class-widget.php';
			$wm->register( new DSN_ACF_Widget() );
		} );
		add_action( 'admin_menu', function () {
			add_menu_page( 'تنظیمات آدرس و پرداخت', 'آدرس و پرداخت', 'manage_options', 'dsn-acf', [ __CLASS__, 'settings_page' ], 'dashicons-location-alt', 58 );
		} );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=dsn-acf' ) ) . '">تنظیمات نقشه</a>' );
			return $links;
		} );
		add_action( 'admin_init', function () {
			register_setting( 'dsn_acf', 'dsn_acf_map_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );
			register_setting( 'dsn_acf', 'dsn_acf_service_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		} );
	}

	static function settings_page() {
		?>
		<div class="wrap"><h1>تنظیمات نقشه نشان</h1>
		<p>کلیدها را از <a href="https://platform.neshan.org" target="_blank">platform.neshan.org</a> بگیرید. اگر خالی بماند، از OpenStreetMap استفاده می‌شود (دقت و زبان فارسی ضعیف‌تر).</p>
		<form method="post" action="options.php" style="max-width:600px">
			<?php settings_fields( 'dsn_acf' ); ?>
			<table class="form-table">
				<tr><th>کلید نقشه (web.xxxx)</th><td><input class="regular-text" dir="ltr" name="dsn_acf_map_key" value="<?php echo esc_attr( get_option( 'dsn_acf_map_key' ) ); ?>"></td></tr>
				<tr><th>کلید سرویس (service.xxxx)</th><td><input class="regular-text" dir="ltr" name="dsn_acf_service_key" value="<?php echo esc_attr( get_option( 'dsn_acf_service_key' ) ); ?>"><p class="description">برای تبدیل مختصات به آدرس؛ فقط سمت سرور استفاده می‌شود.</p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form></div>
		<?php
	}

	static function provinces() {
		static $cache = null;
		if ( null === $cache ) {
			$f = __DIR__ . '/assets/iran-cities.json';
			$d = is_readable( $f ) ? json_decode( file_get_contents( $f ), true ) : null;
			$cache = is_array( $d ) ? array_keys( $d ) : [ 'آذربایجان شرقی','آذربایجان غربی','اردبیل','اصفهان','البرز','ایلام','بوشهر','تهران','چهارمحال و بختیاری','خراسان جنوبی','خراسان رضوی','خراسان شمالی','خوزستان','زنجان','سمنان','سیستان و بلوچستان','فارس','قزوین','قم','کردستان','کرمان','کرمانشاه','کهگیلویه و بویراحمد','گلستان','گیلان','لرستان','مازندران','مرکزی','هرمزگان','همدان','یزد' ];
		}
		return $cache;
	}

	static function need_phone() {
		return ! is_user_logged_in() || ! get_user_meta( get_current_user_id(), 'billing_phone', true );
	}

	/** متن‌های قابل ویرایش: کلید => [برچسب در المنتور, پیش‌فرض] */
	static function texts() {
		return [
			'list_title' => [ 'عنوان صفحه آدرس‌ها', 'انتخاب آدرس تحویل' ],
			'loading'    => [ 'متن لودینگ (زیر انیمیشن)', 'لطفاً منتظر بمانید' ],
			'empty'      => [ 'متن بدون آدرس', 'آدرسی وارد نشده' ],
			'add'        => [ 'دکمه افزودن آدرس', 'افزودن آدرس' ],
			'next'       => [ 'دکمه ادامه', 'ادامه' ],
			'edit'       => [ 'دکمه ویرایش آدرس', 'تغییر / ویرایش' ],
			'map_title'  => [ 'پاپ‌آپ نقشه: عنوان', 'تعیین موقعیت روی نقشه' ],
			'map_hint'   => [ 'پاپ‌آپ نقشه: راهنما', 'نقشه را حرکت دهید تا پین روی محل دقیق قرار گیرد.' ],
			'outside'    => [ 'پیام خطا: خارج از ایران', 'موقعیت انتخاب‌شده خارج از ایران است. لطفاً نقطه‌ای داخل ایران انتخاب کنید.' ],
			'locate'     => [ 'دکمه تشخیص موقعیت', '📍 تشخیص موقعیت من' ],
			'set_loc'    => [ 'دکمه ثبت موقعیت', 'ثبت موقعیت مکانی' ],
			'manual'     => [ 'دکمه ثبت دستی', 'ثبت دستی آدرس' ],
			'form_title' => [ 'فرم: عنوان', 'ثبت آدرس' ],
			'first_name' => [ 'برچسب نام', 'نام' ],
			'last_name'  => [ 'برچسب نام خانوادگی', 'نام خانوادگی' ],
			'phone'      => [ 'برچسب موبایل', 'شماره موبایل' ],
			'map_addr'   => [ 'برچسب آدرس نقشه', 'آدرس انتخاب‌شده روی نقشه' ],
			'edit_loc'   => [ 'دکمه ویرایش موقعیت', 'ویرایش موقعیت' ],
			'form_hint'  => [ 'متن راهنمای زیر آدرس', 'اگر آدرس نادرست است می‌توانید از ثبت دستی آدرس استفاده کنید.' ],
			'province'   => [ 'برچسب استان', 'استان' ],
			'province_ph'=> [ 'متن پیش‌فرض لیست استان', 'انتخاب استان' ],
			'city'       => [ 'برچسب شهر', 'شهر' ],
			'city_ph'    => [ 'متن پیش‌فرض لیست شهر', 'انتخاب شهر' ],
			'city_first' => [ 'متن لیست شهر (قبل از انتخاب استان)', 'ابتدا استان را انتخاب کنید' ],
			'address'    => [ 'برچسب آدرس دقیق', 'آدرس دقیق' ],
			'plaque'     => [ 'برچسب پلاک', 'پلاک' ],
			'postcode'   => [ 'برچسب کدپستی', 'کدپستی' ],
			'save'       => [ 'دکمه ثبت آدرس', 'ثبت آدرس' ],
			'delete'     => [ 'لینک حذف آدرس', 'حذف این آدرس' ],
			'sum_title'  => [ 'جزئیات سفارش: عنوان', 'جزئیات سفارش' ],
			'deliver_to' => [ 'عنوان آدرس تحویل', 'آدرس تحویل' ],
			'pay'        => [ 'دکمه پرداخت', 'پرداخت' ],
			'back'       => [ 'دکمه بازگشت', 'بازگشت به آدرس‌ها' ],
			'gw_title'   => [ 'پاپ‌آپ درگاه: عنوان', 'انتخاب درگاه پرداخت' ],
			'gw_confirm' => [ 'دکمه تایید پرداخت', 'تایید و پرداخت' ],
		];
	}

	static function default_cfg() {
		$t = [];
		foreach ( self::texts() as $k => $d ) $t[ $k ] = $d[1];
		return [ 't' => $t, 'map' => [ 'lat' => 35.6892, 'lng' => 51.3890, 'zoom' => 12, 'type' => 'standard-day', 'locate' => true ], 'preview' => 'none' ];
	}

	static function root_html( $cfg ) {
		$t = isset( $cfg['t']['loading'] ) ? $cfg['t']['loading'] : 'لطفاً منتظر بمانید';
		return '<div class="dsn-root" dir="rtl" data-dsn="' . esc_attr( wp_json_encode( $cfg ) ) . '"><div class="dsn-view"><div class="dsn-wrap"><div class="dsn-loader" role="status" aria-live="polite"><span class="dsn-spin" aria-hidden="true"></span><div class="dsn-loader-t">' . esc_html( $t ) . '</div></div></div></div><div class="dsn-layer"></div></div>';
	}

	static function register_assets() {
		if ( ! class_exists( 'WooCommerce' ) ) return;
		$u  = plugin_dir_url( __FILE__ );
		$mk = trim( (string) get_option( 'dsn_acf_map_key' ) );
		if ( $mk ) {
			wp_register_style( 'leaflet', 'https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.css', [], null );
			wp_register_script( 'leaflet', 'https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.js', [], null, true );
		} else {
			wp_register_style( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', [], '1.9.4' );
			wp_register_script( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', [], '1.9.4', true );
		}
		wp_register_style( 'dsn-acf', $u . 'assets/app.css', [ 'leaflet' ], '1.3.0' );
		wp_register_script( 'dsn-acf', $u . 'assets/app.js', [ 'leaflet' ], '1.3.0', true );
		wp_localize_script( 'dsn-acf', 'DSN', [
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => '',
			'provinces' => self::provinces(),
			'mapKey'    => $mk,
			'tiles'     => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			'needPhone' => self::need_phone(),
			'citiesUrl' => $u . 'assets/iran-cities.json',
			'bbox'      => [ 24.0, 43.5, 40.5, 64.0 ],
		] );
	}

	static function shortcode() {
		if ( ! class_exists( 'WooCommerce' ) ) return '';
		wp_enqueue_style( 'dsn-acf' );
		wp_enqueue_script( 'dsn-acf' );
		return self::root_html( self::default_cfg() );
	}

	/* ---------- storage (کاربر: user meta | مهمان: WC session) ---------- */
	static function guard( $check_nonce = true ) {
		if ( $check_nonce ) check_ajax_referer( 'dsn_acf', 'nonce' );
		if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) wc_load_cart();
		if ( ! is_user_logged_in() && WC()->session ) WC()->session->set_customer_session_cookie( true );
	}
	static function addresses() {
		$a = is_user_logged_in() ? get_user_meta( get_current_user_id(), self::META, true ) : WC()->session->get( self::META );
		return is_array( $a ) ? array_values( $a ) : [];
	}
	static function put_addresses( $list ) {
		if ( is_user_logged_in() ) update_user_meta( get_current_user_id(), self::META, $list );
		else WC()->session->set( self::META, $list );
	}
	static function put_sel( $id ) {
		if ( is_user_logged_in() ) update_user_meta( get_current_user_id(), self::SEL, $id );
		else WC()->session->set( self::SEL, $id );
	}
	static function state() {
		$list = self::addresses();
		$sel  = is_user_logged_in() ? get_user_meta( get_current_user_id(), self::SEL, true ) : WC()->session->get( self::SEL );
		if ( ! in_array( $sel, wp_list_pluck( $list, 'id' ), true ) ) $sel = '';
		return [ 'addresses' => $list, 'selected' => $sel ];
	}
	static function selected() {
		$s = self::state();
		foreach ( $s['addresses'] as $a ) if ( $a['id'] === $s['selected'] ) return $a;
		return null;
	}

	static function ajax_list() {
		self::guard( false );
		$s = self::state();
		$s['nonce'] = wp_create_nonce( 'dsn_acf' ); // بعد از ساخت سشن مهمان ساخته می‌شود
		wp_send_json_success( $s );
	}

	/* ---------- geocode (سمت سرور؛ فقط داخل ایران) ---------- */
	static function in_bbox( $lat, $lng ) {
		return $lat >= 24.0 && $lat <= 40.5 && $lng >= 43.5 && $lng <= 64.0;
	}
	static function norm( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( [ 'ي', 'ك' ], [ 'ی', 'ک' ], (string) $s ) ) );
	}
	static function en2fa_digits( $s ) {
		return strtr( (string) $s, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] );
	}
	static function match_province( $state ) {
		$st = self::norm( preg_replace( '/^استان\s+/u', '', (string) $state ) );
		if ( $st === '' ) return '';
		$best = '';
		foreach ( self::provinces() as $p ) {
			$pn = self::norm( $p );
			if ( $pn === $st ) return $p;
			if ( strpos( $st, $pn ) !== false && strlen( $pn ) > strlen( $best ) ) $best = $p;
		}
		return $best;
	}
	static function nominatim( $lat, $lng ) {
		$r = wp_remote_get( add_query_arg( [ 'format' => 'jsonv2', 'accept-language' => 'fa', 'zoom' => 18, 'addressdetails' => 1, 'lat' => $lat, 'lon' => $lng ], 'https://nominatim.openstreetmap.org/reverse' ), [ 'timeout' => 8, 'user-agent' => 'DSN-Address-Flow/1.3 (' . home_url() . ')' ] );
		return is_wp_error( $r ) ? [] : (array) json_decode( wp_remote_retrieve_body( $r ), true );
	}

	static function ajax_geocode() {
		self::guard();
		$lat = (float) ( $_POST['lat'] ?? 0 );
		$lng = (float) ( $_POST['lng'] ?? 0 );
		$out_msg = 'موقعیت انتخاب‌شده خارج از ایران است. لطفاً نقطه‌ای داخل ایران انتخاب کنید.';
		if ( ! $lat || ! $lng ) wp_send_json_error( [ 'message' => 'مختصات نامعتبر است.' ] );
		if ( ! self::in_bbox( $lat, $lng ) ) wp_send_json_error( [ 'code' => 'outside', 'message' => $out_msg ] );

		$ck = 'dsn_geo2_' . md5( round( $lat, 5 ) . ',' . round( $lng, 5 ) );
		if ( $c = get_transient( $ck ) ) wp_send_json_success( $c );

		$f = [ 'province' => '', 'city' => '', 'street' => '', 'alley' => '', 'plaque' => '', 'postcode' => '' ];
		$skey = trim( (string) get_option( 'dsn_acf_service_key' ) );
		$n = [];
		if ( $skey ) {
			$r = wp_remote_get( add_query_arg( [ 'lat' => $lat, 'lng' => $lng ], 'https://api.neshan.org/v5/reverse' ), [ 'timeout' => 8, 'headers' => [ 'Api-Key' => $skey ] ] );
			$n = is_wp_error( $r ) ? [] : (array) json_decode( wp_remote_retrieve_body( $r ), true );
		}
		$from_neshan = ! empty( $n['formatted_address'] ) || ! empty( $n['state'] );
		if ( $from_neshan ) {
			$f['province'] = self::match_province( $n['state'] ?? '' );
			$f['city']     = self::norm( $n['city'] ?? ( $n['county'] ?? '' ) );
			$f['street']   = self::norm( $n['route_name'] ?? '' );
			foreach ( preg_split( '/[،,]/u', (string) ( $n['formatted_address'] ?? '' ) ) as $seg ) {
				$seg = self::norm( $seg );
				if ( $seg === '' ) continue;
				if ( ! $f['street'] && preg_match( '/^(خیابان|بلوار|بزرگراه|اتوبان|جاده|میدان)/u', $seg ) ) $f['street'] = $seg;
				elseif ( ! $f['alley'] && preg_match( '/^(کوچه|بن\s?بست|پاساژ)/u', $seg ) ) $f['alley'] = $seg;
				elseif ( ! $f['plaque'] && preg_match( '/^پلاک\s*(.+)$/u', $seg, $m ) ) $f['plaque'] = $m[1];
				elseif ( ! $f['postcode'] && preg_match( '/(\d{10})/', self::en2fa_digits( $seg ), $m ) ) $f['postcode'] = $m[1];
			}
		}
		// اگر نشان استان را نشناخت یا داده‌ای نداد: بررسی کشور با Nominatim
		if ( ! $f['province'] ) {
			$nm = self::nominatim( $lat, $lng );
			$a  = (array) ( $nm['address'] ?? [] );
			$cc = strtolower( $a['country_code'] ?? '' );
			if ( $cc && $cc !== 'ir' ) wp_send_json_error( [ 'code' => 'outside', 'message' => $out_msg ] );
			if ( $a ) {
				$f['province'] = self::match_province( $a['state'] ?? '' );
				if ( ! $f['city'] )     $f['city']     = self::norm( $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['county'] ?? '' );
				if ( ! $f['street'] )   $f['street']   = self::norm( $a['road'] ?? '' );
				if ( ! $f['plaque'] )   $f['plaque']   = self::norm( $a['house_number'] ?? '' );
				if ( ! $f['postcode'] ) $f['postcode'] = preg_replace( '/\D/', '', self::en2fa_digits( $a['postcode'] ?? '' ) );
			}
		}
		$f['text'] = implode( '، ', array_filter( [
			$f['province'], $f['city'], $f['street'], $f['alley'],
			$f['plaque'] ? 'پلاک ' . $f['plaque'] : '',
			$f['postcode'] ? 'کدپستی ' . $f['postcode'] : '',
		] ) );
		if ( $f['text'] ) set_transient( $ck, $f, DAY_IN_SECONDS );
		wp_send_json_success( $f );
	}

	/* ---------- CRUD ---------- */
	static function ajax_save() {
		self::guard();
		$raw = json_decode( wp_unslash( $_POST['data'] ?? '' ), true );
		if ( ! is_array( $raw ) ) wp_send_json_error( [ 'message' => 'داده نامعتبر است.' ] );
		$f = function ( $k ) use ( $raw ) { return sanitize_text_field( $raw[ $k ] ?? '' ); };
		$a = [
			'id' => $f( 'id' ), 'first_name' => $f( 'first_name' ), 'last_name' => $f( 'last_name' ),
			'phone' => preg_replace( '/\D/', '', $f( 'phone' ) ),
			'lat' => $f( 'lat' ), 'lng' => $f( 'lng' ), 'map_address' => $f( 'map_address' ),
			'province' => $f( 'province' ), 'city' => $f( 'city' ),
			'address' => sanitize_textarea_field( $raw['address'] ?? '' ),
			'plaque' => $f( 'plaque' ), 'postcode' => preg_replace( '/\D/', '', $f( 'postcode' ) ),
		];
		foreach ( [ 'first_name', 'last_name', 'province', 'city', 'address', 'plaque', 'postcode' ] as $req ) {
			if ( $a[ $req ] === '' ) wp_send_json_error( [ 'message' => 'لطفاً همه فیلدها را تکمیل کنید.' ] );
		}
		if ( self::need_phone() && ! preg_match( '/^09\d{9}$/', $a['phone'] ) ) wp_send_json_error( [ 'message' => 'شماره موبایل معتبر نیست (مثال: 09123456789).' ] );
		if ( ! preg_match( '/^\d{10}$/', $a['postcode'] ) ) wp_send_json_error( [ 'message' => 'کدپستی باید ۱۰ رقم باشد.' ] );
		if ( $a['lat'] !== '' && ! self::in_bbox( (float) $a['lat'], (float) $a['lng'] ) ) wp_send_json_error( [ 'message' => 'موقعیت انتخاب‌شده خارج از ایران است.' ] );
		if ( ! in_array( $a['province'], self::provinces(), true ) ) wp_send_json_error( [ 'message' => 'استان نامعتبر است.' ] );

		$list = self::addresses();
		$found = false;
		foreach ( $list as $i => $old ) {
			if ( $a['id'] && $old['id'] === $a['id'] ) { $list[ $i ] = $a; $found = true; break; }
		}
		if ( ! $found ) { $a['id'] = wp_generate_uuid4(); $list[] = $a; }
		self::put_addresses( $list );
		self::put_sel( $a['id'] );
		wp_send_json_success( self::state() );
	}
	static function ajax_delete() {
		self::guard();
		$id = sanitize_text_field( $_POST['id'] ?? '' );
		self::put_addresses( array_values( array_filter( self::addresses(), function ( $x ) use ( $id ) { return $x['id'] !== $id; } ) ) );
		wp_send_json_success( self::state() );
	}
	static function ajax_select() {
		self::guard();
		self::put_sel( sanitize_text_field( $_POST['id'] ?? '' ) );
		wp_send_json_success( self::state() );
	}

	/* ---------- summary ---------- */
	static function ajax_summary() {
		self::guard();
		$addr = self::selected();
		if ( ! $addr ) wp_send_json_error( [ 'message' => 'آدرسی انتخاب نشده است.' ] );
		$cart = WC()->cart;
		if ( $cart->is_empty() ) wp_send_json_error( [ 'message' => 'سبد خرید خالی است.' ] );
		$cart->calculate_totals();
		$items = [];
		foreach ( $cart->get_cart() as $it ) {
			$items[] = [ 'name' => esc_html( $it['data']->get_name() ), 'qty' => (int) $it['quantity'], 'total' => wc_price( $it['line_total'] + $it['line_tax'] ) ];
		}
		$rows = [ [ 'جمع جزء', $cart->get_cart_subtotal() ] ];
		if ( $cart->get_discount_total() ) $rows[] = [ 'تخفیف', '-' . wc_price( $cart->get_discount_total() ) ];
		if ( $cart->needs_shipping() ) $rows[] = [ 'حمل و نقل', $cart->get_cart_shipping_total() ];
		$rows[] = [ 'مبلغ قابل پرداخت', $cart->get_total() ];
		wp_send_json_success( [ 'items' => $items, 'rows' => $rows, 'address' => $addr ] );
	}

	/* ---------- gateways + pay ---------- */
	static function ajax_gateways() {
		self::guard();
		$out = [];
		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gw ) {
			$out[] = [ 'id' => $id, 'title' => wp_strip_all_tags( $gw->get_title() ), 'desc' => wp_strip_all_tags( $gw->get_description() ), 'icon' => $gw->get_icon() ];
		}
		if ( ! $out ) wp_send_json_error( [ 'message' => 'درگاه پرداختی فعال نیست.' ] );
		wp_send_json_success( $out );
	}

	static function ajax_pay() {
		self::guard();
		$gws  = WC()->payment_gateways()->get_available_payment_gateways();
		$gid  = sanitize_text_field( $_POST['gateway'] ?? '' );
		$addr = self::selected();
		if ( ! isset( $gws[ $gid ] ) ) wp_send_json_error( [ 'message' => 'درگاه نامعتبر است.' ] );
		if ( ! $addr ) wp_send_json_error( [ 'message' => 'آدرسی انتخاب نشده است.' ] );
		if ( WC()->cart->is_empty() ) wp_send_json_error( [ 'message' => 'سبد خرید خالی است.' ] );

		try {
			$cart = WC()->cart;
			$cart->calculate_totals();
			$uid  = get_current_user_id();
			$user = $uid ? get_userdata( $uid ) : null;
			$phone = $addr['phone'] ?? '';
			if ( ! $phone && $uid ) $phone = get_user_meta( $uid, 'billing_phone', true );

			// سفارش pending قبلی همین سشن (تلاش مجدد پرداخت) لغو می‌شود تا سفارش تکراری نماند
			$prev = WC()->session ? absint( WC()->session->get( 'dsn_last_order' ) ) : 0;
			if ( $prev && ( $po = wc_get_order( $prev ) ) && $po->get_created_via() === self::VIA && $po->has_status( 'pending' ) ) {
				$po->update_status( 'cancelled', 'جایگزین سفارش جدید شد.' );
			}

			$order = wc_create_order( [ 'customer_id' => $uid, 'status' => 'pending', 'created_via' => self::VIA ] );
			foreach ( $cart->get_cart() as $it ) {
				$order->add_product( $it['data'], $it['quantity'], [
					'variation' => $it['variation'],
					'totals'    => [ 'subtotal' => $it['line_subtotal'], 'subtotal_tax' => $it['line_subtotal_tax'], 'total' => $it['line_total'], 'tax' => $it['line_tax'], 'tax_data' => $it['line_tax_data'] ],
				] );
			}
			$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : [];
			foreach ( WC()->shipping()->get_packages() as $i => $pkg ) {
				$c = $chosen[ $i ] ?? '';
				if ( $c && isset( $pkg['rates'][ $c ] ) ) {
					$si = new WC_Order_Item_Shipping();
					$si->set_shipping_rate( $pkg['rates'][ $c ] );
					$order->add_item( $si );
				}
			}
			foreach ( $cart->get_applied_coupons() as $code ) $order->apply_coupon( $code );

			$base = [
				'first_name' => $addr['first_name'], 'last_name' => $addr['last_name'],
				'address_1'  => trim( $addr['province'] . '، ' . $addr['city'] . '، ' . $addr['address'] . '، پلاک ' . $addr['plaque'] ),
				'city' => $addr['city'], 'state' => $addr['province'], 'postcode' => $addr['postcode'], 'country' => 'IR',
			];
			$order->set_address( $base + [ 'email' => $user ? $user->user_email : '', 'phone' => $phone ], 'billing' );
			$order->set_address( $base, 'shipping' );
			$order->update_meta_data( '_dsn_lat', $addr['lat'] );
			$order->update_meta_data( '_dsn_lng', $addr['lng'] );
			$order->update_meta_data( '_dsn_plaque', $addr['plaque'] );
			$order->set_payment_method( $gws[ $gid ] );
			$order->calculate_totals();
			$order->save();
			if ( WC()->session ) WC()->session->set( 'dsn_last_order', $order->get_id() );

			$res = $gws[ $gid ]->process_payment( $order->get_id() );
			if ( is_array( $res ) && ( $res['result'] ?? '' ) === 'success' ) {
				// روش‌هایی مثل پرداخت در محل / کارت‌به‌کارت: سفارش همین‌جا نهایی می‌شود
				$o = wc_get_order( $order->get_id() );
				if ( $o && ! $o->has_status( [ 'pending', 'failed', 'cancelled' ] ) ) $cart->empty_cart();
				wp_send_json_success( [ 'redirect' => $res['redirect'] ] );
			}
			wp_send_json_error( [ 'message' => 'اتصال به درگاه ناموفق بود.' ] );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/* بعد از بازگشت از درگاه: فقط اگر سفارش واقعاً ثبت/پرداخت شده، سبد خالی شود */
	static function maybe_empty_cart( $order_id ) {
		$o = wc_get_order( $order_id );
		if ( $o && $o->get_created_via() === self::VIA && ( $o->is_paid() || $o->has_status( [ 'on-hold', 'processing', 'completed' ] ) ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
	}
}
DSN_ACF::init();
