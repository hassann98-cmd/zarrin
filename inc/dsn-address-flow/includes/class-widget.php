<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

class DSN_ACF_Widget extends Widget_Base {

	public function get_name() { return 'dsn_address_flow'; }
	public function get_title() { return 'آدرس و پرداخت (Desiano)'; }
	public function get_icon() { return 'eicon-map-pin'; }
	public function get_categories() { return [ 'general' ]; }
	public function get_keywords() { return [ 'address', 'checkout', 'map', 'neshan', 'آدرس', 'پرداخت', 'نقشه', 'سفارش' ]; }
	public function get_script_depends() { return [ 'leaflet', 'dsn-acf' ]; }
	public function get_style_depends() { return [ 'leaflet', 'dsn-acf' ]; }

	/* ================= helpers ================= */
	private function s( $sel ) {
		return implode( ', ', array_map( function ( $p ) { return '{{WRAPPER}} ' . trim( $p ); }, explode( ',', $sel ) ) );
	}
	private function sec( $id, $label ) {
		$this->start_controls_section( $id, [ 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ] );
	}
	private function head( $id, $label ) {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
	}
	private function typo( $id, $label, $sel ) {
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => $id, 'label' => $label, 'selector' => $this->s( $sel ) ] );
	}
	private function color( $id, $label, $sel, $prop = 'color' ) {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ $this->s( $sel ) => $prop . ': {{VALUE}};' ] ] );
	}
	private function bg( $id, $label, $sel ) {
		$this->add_group_control( Group_Control_Background::get_type(), [ 'name' => $id, 'label' => $label, 'types' => [ 'classic', 'gradient' ], 'selector' => $this->s( $sel ) ] );
	}
	private function border( $id, $sel ) {
		$this->add_group_control( Group_Control_Border::get_type(), [ 'name' => $id, 'selector' => $this->s( $sel ) ] );
	}
	private function shadow( $id, $sel, $label = 'سایه' ) {
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => $id, 'label' => $label, 'selector' => $this->s( $sel ) ] );
	}
	private function dim( $id, $label, $sel, $prop = 'padding' ) {
		$this->add_responsive_control( $id, [
			'label' => $label, 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em', 'rem' ],
			'selectors' => [ $this->s( $sel ) => $prop . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		] );
	}
	private function radius( $id, $sel, $label = 'گردی گوشه‌ها' ) {
		$this->dim( $id, $label, $sel, 'border-radius' );
	}
	private function slider( $id, $label, $sel, $prop, $min = 0, $max = 100, $unit = 'px', $step = 1 ) {
		$this->add_responsive_control( $id, [
			'label' => $label, 'type' => Controls_Manager::SLIDER, 'size_units' => [ $unit ],
			'range' => [ $unit => [ 'min' => $min, 'max' => $max, 'step' => $step ] ],
			'selectors' => [ $this->s( $sel ) => $prop . ': {{SIZE}}{{UNIT}};' ],
		] );
	}
	/** تب‌های حالت (عادی/هاور/انتخاب‌شده/...) */
	private function states( $id, array $states, array $parts ) {
		$this->start_controls_tabs( $id . '_tabs' );
		foreach ( $states as $k => $st ) {
			$this->start_controls_tab( $id . '_' . $k, [ 'label' => $st[0] ] );
			$sel = $st[1];
			if ( in_array( 'color', $parts, true ) ) $this->color( "{$id}_{$k}_color", 'رنگ متن', $sel );
			if ( in_array( 'bg', $parts, true ) )    $this->bg( "{$id}_{$k}_bg", 'پس‌زمینه', $sel );
			if ( in_array( 'border', $parts, true ) ) $this->border( "{$id}_{$k}_border", $sel );
			if ( in_array( 'shadow', $parts, true ) ) $this->shadow( "{$id}_{$k}_shadow", $sel );
			if ( in_array( 'opacity', $parts, true ) ) {
				$this->add_control( "{$id}_{$k}_op", [
					'label' => 'شفافیت', 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => .05 ] ],
					'selectors' => [ $this->s( $sel ) => 'opacity: {{SIZE}};' ],
				] );
			}
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
	}
	private function button_section( $id, $label, $sel ) {
		$this->sec( $id, $label );
		$this->typo( "{$id}_typo", 'تایپوگرافی', $sel );
		$this->states( $id, [
			'normal' => [ 'عادی', $sel ],
			'hover'  => [ 'هاور', $sel . ':hover, ' . $sel . ':focus' ],
		], [ 'color', 'bg', 'border', 'shadow', 'opacity' ] );
		$this->radius( "{$id}_radius", $sel );
		$this->dim( "{$id}_padding", 'پدینگ', $sel );
		$this->dim( "{$id}_margin", 'مارجین', $sel, 'margin' );
		$this->end_controls_section();
	}

	/* ================= controls ================= */
	protected function register_controls() {

		/* ---------- Content: texts ---------- */
		$groups = [
			'ct_list' => [ 'متن‌ها: صفحه آدرس‌ها', [ 'list_title', 'loading', 'empty', 'add', 'next', 'edit' ] ],
			'ct_map'  => [ 'متن‌ها: پاپ‌آپ نقشه', [ 'map_title', 'map_hint', 'outside', 'locate', 'set_loc', 'manual' ] ],
			'ct_form' => [ 'متن‌ها: فرم آدرس', [ 'form_title', 'first_name', 'last_name', 'phone', 'map_addr', 'edit_loc', 'form_hint', 'province', 'province_ph', 'city', 'city_ph', 'city_first', 'address', 'plaque', 'postcode', 'save', 'delete' ] ],
			'ct_pay'  => [ 'متن‌ها: سفارش و پرداخت', [ 'sum_title', 'deliver_to', 'pay', 'back', 'gw_title', 'gw_confirm' ] ],
		];
		$texts = DSN_ACF::texts();
		foreach ( $groups as $gid => $g ) {
			$this->start_controls_section( $gid, [ 'label' => $g[0], 'tab' => Controls_Manager::TAB_CONTENT ] );
			foreach ( $g[1] as $k ) {
				$this->add_control( 't_' . $k, [ 'label' => $texts[ $k ][0], 'type' => Controls_Manager::TEXT, 'default' => $texts[ $k ][1], 'label_block' => true ] );
			}
			$this->end_controls_section();
		}

		/* ---------- Content: map ---------- */
		$this->start_controls_section( 'ct_mapset', [ 'label' => 'تنظیمات نقشه', 'tab' => Controls_Manager::TAB_CONTENT ] );
		$has_key = trim( (string) get_option( 'dsn_acf_map_key' ) ) !== '';
		$this->add_control( 'map_keys_note', [
			'type' => Controls_Manager::RAW_HTML,
			'raw'  => ( $has_key ? '<b style="color:#2e9b4f">کلید نشان ثبت شده است.</b>' : '<b style="color:#d33">کلید نشان ثبت نشده (نقشه OpenStreetMap فعال است).</b>' )
				. '<br><a href="' . esc_url( admin_url( 'admin.php?page=dsn-acf' ) ) . '" target="_blank">ورود / ویرایش کلیدهای نشان ←</a>',
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		] );
		$this->add_control( 'map_lat', [ 'label' => 'عرض جغرافیایی پیش‌فرض', 'type' => Controls_Manager::NUMBER, 'default' => 35.6892, 'step' => 0.0001 ] );
		$this->add_control( 'map_lng', [ 'label' => 'طول جغرافیایی پیش‌فرض', 'type' => Controls_Manager::NUMBER, 'default' => 51.3890, 'step' => 0.0001 ] );
		$this->add_control( 'map_zoom', [ 'label' => 'زوم اولیه', 'type' => Controls_Manager::NUMBER, 'default' => 12, 'min' => 3, 'max' => 19 ] );
		$this->add_control( 'map_type', [
			'label' => 'سبک نقشه (فقط نشان)', 'type' => Controls_Manager::SELECT, 'default' => 'standard-day',
			'options' => [ 'standard-day' => 'Standard Day', 'standard-night' => 'Standard Night', 'neshan' => 'Neshan', 'dreamy' => 'Dreamy', 'dreamy-gold' => 'Dreamy Gold', 'osm-bright' => 'OSM Bright' ],
		] );
		$this->add_control( 'map_locate', [ 'label' => 'دکمه تشخیص موقعیت', 'type' => Controls_Manager::SWITCHER, 'label_on' => 'بله', 'label_off' => 'خیر', 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->end_controls_section();

		/* ---------- Content: editor preview ---------- */
		$this->start_controls_section( 'ct_preview', [ 'label' => 'پیش‌نمایش در ادیتور', 'tab' => Controls_Manager::TAB_CONTENT ] );
		$this->add_control( 'preview', [
			'label' => 'نمایش بخش‌ها برای طراحی', 'type' => Controls_Manager::SELECT, 'default' => 'none',
			'options' => [ 'none' => 'عادی (بدون پیش‌نمایش)', 'loading' => 'لودینگ', 'list' => 'لیست آدرس‌ها (نمونه)', 'map' => 'پاپ‌آپ نقشه', 'form' => 'پاپ‌آپ فرم آدرس', 'summary' => 'جزئیات سفارش', 'gateways' => 'پاپ‌آپ درگاه پرداخت' ],
			'description' => 'فقط داخل ادیتور المنتور اثر دارد و روی سایت نمایش داده نمی‌شود. برای استایل دادن به پاپ‌آپ‌ها از این گزینه استفاده کنید.',
		] );
		$this->end_controls_section();

		/* ================= STYLE ================= */

		/* 1. باکس اصلی */
		$this->sec( 'st_box', 'باکس اصلی' );
		$this->slider( 'box_maxw', 'حداکثر عرض', '.dsn-root', 'max-width', 280, 1400 );
		$this->bg( 'box_bg', 'پس‌زمینه', '.dsn-wrap' );
		$this->border( 'box_border', '.dsn-wrap' );
		$this->radius( 'box_radius', '.dsn-wrap' );
		$this->shadow( 'box_shadow', '.dsn-wrap' );
		$this->dim( 'box_padding', 'پدینگ', '.dsn-wrap' );
		$this->dim( 'box_margin', 'مارجین بیرونی', '.dsn-root', 'margin' );
		$this->end_controls_section();

		/* 2. عنوان‌ها */
		$this->sec( 'st_title', 'عنوان صفحه' );
		$this->typo( 'title_typo', 'تایپوگرافی', '.dsn-wrap h3' );
		$this->color( 'title_color', 'رنگ', '.dsn-wrap h3' );
		$this->add_responsive_control( 'title_align', [ 'label' => 'تراز', 'type' => Controls_Manager::CHOOSE, 'options' => [
			'right' => [ 'title' => 'راست', 'icon' => 'eicon-text-align-right' ], 'center' => [ 'title' => 'وسط', 'icon' => 'eicon-text-align-center' ], 'left' => [ 'title' => 'چپ', 'icon' => 'eicon-text-align-left' ] ],
			'selectors' => [ $this->s( '.dsn-wrap h3' ) => 'text-align: {{VALUE}};' ] ] );
		$this->dim( 'title_margin', 'مارجین', '.dsn-wrap h3', 'margin' );
		$this->end_controls_section();

		/* 3. کارت آدرس */
		$this->sec( 'st_card', 'کارت آدرس' );
		$this->states( 'card', [
			'normal'   => [ 'عادی', '.dsn-card:not(.is-on)' ],
			'selected' => [ 'انتخاب‌شده', '.dsn-card.is-on' ],
		], [ 'bg', 'border', 'shadow' ] );
		$this->radius( 'card_radius', '.dsn-card' );
		$this->dim( 'card_padding', 'پدینگ', '.dsn-card' );
		$this->slider( 'card_gap', 'فاصله بین کارت‌ها', '.dsn-card', 'margin-bottom', 0, 60 );
		$this->slider( 'card_inner_gap', 'فاصله اجزای داخل کارت', '.dsn-card', 'gap', 0, 40 );
		$this->head( 'card_name_h', 'نام گیرنده' );
		$this->typo( 'card_name_typo', 'تایپوگرافی', '.dsn-txt b' );
		$this->color( 'card_name_color', 'رنگ', '.dsn-txt b' );
		$this->head( 'card_addr_h', 'متن آدرس' );
		$this->typo( 'card_addr_typo', 'تایپوگرافی', '.dsn-txt small' );
		$this->color( 'card_addr_color', 'رنگ', '.dsn-txt small' );
		$this->end_controls_section();

		/* 4. چک‌باکس */
		$this->sec( 'st_check', 'چک‌باکس' );
		$this->slider( 'chk_size', 'اندازه', '.dsn-card .dsn-box', 'width', 12, 48 );
		$this->slider( 'chk_size_h', 'ارتفاع', '.dsn-card .dsn-box', 'height', 12, 48 );
		$this->radius( 'chk_radius', '.dsn-card .dsn-box' );
		$this->states( 'chk', [
			'normal'  => [ 'عادی', '.dsn-card:not(.is-on) .dsn-box' ],
			'checked' => [ 'انتخاب‌شده', '.dsn-card.is-on .dsn-box' ],
		], [ 'bg', 'border' ] );
		$this->color( 'chk_tick', 'رنگ تیک', '.dsn-card.is-on .dsn-box:after', 'border-color' );
		$this->end_controls_section();

		/* 5. دکمه ویرایش کارت */
		$this->sec( 'st_edit', 'دکمه «تغییر / ویرایش»' );
		$this->typo( 'edit_typo', 'تایپوگرافی', '.dsn-mini' );
		$this->states( 'edit', [
			'normal' => [ 'عادی', '.dsn-mini' ],
			'hover'  => [ 'هاور', '.dsn-mini:hover' ],
		], [ 'color', 'bg', 'border' ] );
		$this->radius( 'edit_radius', '.dsn-mini' );
		$this->dim( 'edit_padding', 'پدینگ', '.dsn-mini' );
		$this->end_controls_section();

		/* 6. متن خالی */
		$this->sec( 'st_empty', 'متن «آدرسی وارد نشده»' );
		$this->typo( 'empty_typo', 'تایپوگرافی', '.dsn-empty' );
		$this->color( 'empty_color', 'رنگ', '.dsn-empty' );
		$this->dim( 'empty_padding', 'پدینگ', '.dsn-empty' );
		$this->end_controls_section();

		/* 7-8. دکمه‌ها */
		$this->button_section( 'st_btn1', 'دکمه اصلی (ادامه / ثبت / پرداخت)', '.dsn-btn:not(.dsn-btn-ghost)' );
		$this->button_section( 'st_btn2', 'دکمه ثانویه (افزودن آدرس / ثبت دستی)', '.dsn-btn-ghost' );

		/* 9. پاپ‌آپ */
		$this->sec( 'st_popup', 'پاپ‌آپ (Bottom Sheet)' );
		$this->add_responsive_control( 'popup_pos', [
			'label' => 'موقعیت پاپ‌آپ', 'type' => Controls_Manager::SELECT, 'options' => [ 'flex-end' => 'پایین صفحه', 'center' => 'وسط صفحه', 'flex-start' => 'بالای صفحه' ],
			'selectors' => [ $this->s( '.dsn-sheet-ov' ) => 'align-items: {{VALUE}};' ],
		] );
		$this->color( 'popup_overlay', 'رنگ پس‌زمینه تیره (Overlay)', '.dsn-sheet-ov', 'background-color' );
		$this->bg( 'popup_bg', 'پس‌زمینه پاپ‌آپ', '.dsn-sheet' );
		$this->border( 'popup_border', '.dsn-sheet' );
		$this->radius( 'popup_radius', '.dsn-sheet' );
		$this->shadow( 'popup_shadow', '.dsn-sheet' );
		$this->dim( 'popup_padding', 'پدینگ', '.dsn-sheet' );
		$this->slider( 'popup_maxw', 'حداکثر عرض', '.dsn-sheet', 'max-width', 280, 1200 );
		$this->head( 'popup_title_h', 'عنوان پاپ‌آپ' );
		$this->typo( 'popup_title_typo', 'تایپوگرافی', '.dsn-sh-h' );
		$this->color( 'popup_title_color', 'رنگ', '.dsn-sh-h' );
		$this->head( 'popup_x_h', 'دکمه بستن' );
		$this->color( 'popup_x_color', 'رنگ', '.dsn-x' );
		$this->slider( 'popup_x_size', 'اندازه', '.dsn-x', 'font-size', 12, 48 );
		$this->end_controls_section();

		/* 10. نقشه */
		$this->sec( 'st_map', 'نقشه' );
		$this->slider( 'map_h', 'ارتفاع نقشه اصلی', '.dsn-map:not(.sm)', 'height', 150, 800 );
		$this->slider( 'map_h_sm', 'ارتفاع نقشه کوچک (فرم)', '.dsn-map.sm', 'height', 80, 500 );
		$this->border( 'map_border', '.dsn-mapbox' );
		$this->radius( 'map_radius', '.dsn-mapbox' );
		$this->color( 'pin_color', 'رنگ پین', '.dsn-pin', 'background-color' );
		$this->slider( 'pin_size', 'اندازه پین', '.dsn-pin', 'width', 16, 64 );
		$this->slider( 'pin_size_h', 'ارتفاع پین', '.dsn-pin', 'height', 16, 64 );
		$this->head( 'loc_h', 'دکمه تشخیص موقعیت' );
		$this->typo( 'loc_typo', 'تایپوگرافی', '.dsn-loc' );
		$this->states( 'loc', [
			'normal' => [ 'عادی', '.dsn-loc' ],
			'hover'  => [ 'هاور', '.dsn-loc:hover' ],
		], [ 'color', 'bg', 'border', 'shadow' ] );
		$this->radius( 'loc_radius', '.dsn-loc' );
		$this->dim( 'loc_padding', 'پدینگ', '.dsn-loc' );
		$this->head( 'maddr_h', 'متن آدرس زیر نقشه' );
		$this->typo( 'maddr_typo', 'تایپوگرافی', '.dsn-maddr' );
		$this->color( 'maddr_color', 'رنگ', '.dsn-maddr' );
		$this->color( 'maddr_err_color', 'رنگ پیام خطا (خارج از ایران)', '.dsn-maddr.is-err' );
		$this->end_controls_section();

		/* 11. فرم */
		$this->sec( 'st_form', 'فرم آدرس' );
		$this->head( 'lbl_h', 'برچسب فیلدها' );
		$this->typo( 'lbl_typo', 'تایپوگرافی', '.dsn-f span' );
		$this->color( 'lbl_color', 'رنگ', '.dsn-f span' );
		$this->head( 'fld_h', 'فیلدها' );
		$this->typo( 'fld_typo', 'تایپوگرافی', '.dsn-f input, .dsn-f select, .dsn-f textarea' );
		$this->states( 'fld', [
			'normal' => [ 'عادی', '.dsn-f input, .dsn-f select, .dsn-f textarea' ],
			'focus'  => [ 'فوکوس', '.dsn-f input:focus, .dsn-f select:focus, .dsn-f textarea:focus' ],
		], [ 'color', 'bg', 'border', 'shadow' ] );
		$this->color( 'fld_ph', 'رنگ پلیس‌هولدر', '.dsn-f input::placeholder', 'color' );
		$this->color( 'fld_ro_bg', 'پس‌زمینه فیلد فقط‌خواندنی (آدرس نقشه)', '.dsn-f input[readonly]', 'background-color' );
		$this->radius( 'fld_radius', '.dsn-f input, .dsn-f select, .dsn-f textarea' );
		$this->dim( 'fld_padding', 'پدینگ', '.dsn-f input, .dsn-f select, .dsn-f textarea' );
		$this->slider( 'row_gap', 'فاصله فیلدهای کنار هم', '.dsn-row', 'gap', 0, 40 );
		$this->slider( 'fld_mt', 'فاصله بین فیلدها', '.dsn-f', 'margin-top', 0, 40 );
		$this->head( 'hint_h', 'متن راهنما' );
		$this->typo( 'hint_typo', 'تایپوگرافی', '.dsn-hint' );
		$this->color( 'hint_color', 'رنگ', '.dsn-hint' );
		$this->head( 'err_h', 'پیام خطا' );
		$this->typo( 'err_typo', 'تایپوگرافی', '.dsn-err' );
		$this->color( 'err_color', 'رنگ', '.dsn-err' );
		$this->head( 'lnk_h', 'لینک‌ها (ویرایش موقعیت / حذف)' );
		$this->typo( 'lnk_typo', 'تایپوگرافی', '.dsn-link' );
		$this->states( 'lnk', [
			'normal' => [ 'عادی', '.dsn-link' ],
			'hover'  => [ 'هاور', '.dsn-link:hover' ],
		], [ 'color' ] );
		$this->end_controls_section();

		/* 12. خلاصه سفارش */
		$this->sec( 'st_sum', 'جزئیات سفارش' );
		$this->bg( 'sum_bg', 'پس‌زمینه باکس‌ها', '.dsn-sum' );
		$this->border( 'sum_border', '.dsn-sum' );
		$this->radius( 'sum_radius', '.dsn-sum' );
		$this->shadow( 'sum_shadow', '.dsn-sum' );
		$this->dim( 'sum_padding', 'پدینگ', '.dsn-sum' );
		$this->slider( 'sum_mb', 'فاصله بین باکس‌ها', '.dsn-sum', 'margin-bottom', 0, 60 );
		$this->head( 'sumline_h', 'ردیف‌ها' );
		$this->typo( 'sumline_typo', 'تایپوگرافی', '.dsn-line' );
		$this->color( 'sumline_color', 'رنگ', '.dsn-line' );
		$this->head( 'sumtot_h', 'ردیف مبلغ نهایی' );
		$this->typo( 'sumtot_typo', 'تایپوگرافی', '.dsn-line.t' );
		$this->color( 'sumtot_color', 'رنگ', '.dsn-line.t' );
		$this->color( 'sumtot_line', 'رنگ خط بالای مبلغ نهایی', '.dsn-line.t', 'border-top-color' );
		$this->head( 'sumaddr_h', 'عنوان و متن آدرس تحویل' );
		$this->typo( 'sumaddr_t_typo', 'تایپوگرافی عنوان', '.dsn-sum > b' );
		$this->color( 'sumaddr_t_color', 'رنگ عنوان', '.dsn-sum > b' );
		$this->typo( 'sumaddr_typo', 'تایپوگرافی متن', '.dsn-sum-addr' );
		$this->color( 'sumaddr_color', 'رنگ متن', '.dsn-sum-addr' );
		$this->end_controls_section();

		/* 13. درگاه‌ها */
		$this->sec( 'st_gw', 'انتخاب درگاه پرداخت' );
		$this->states( 'gw', [
			'normal'   => [ 'عادی', '.dsn-gw:not(.is-on)' ],
			'selected' => [ 'انتخاب‌شده', '.dsn-gw.is-on' ],
		], [ 'bg', 'border', 'shadow' ] );
		$this->radius( 'gw_radius', '.dsn-gw' );
		$this->dim( 'gw_padding', 'پدینگ', '.dsn-gw' );
		$this->slider( 'gw_mb', 'فاصله بین موارد', '.dsn-gw', 'margin-bottom', 0, 40 );
		$this->head( 'gw_t_h', 'عنوان درگاه' );
		$this->typo( 'gw_t_typo', 'تایپوگرافی', '.dsn-gw b' );
		$this->color( 'gw_t_color', 'رنگ', '.dsn-gw b' );
		$this->head( 'gw_d_h', 'توضیح درگاه' );
		$this->typo( 'gw_d_typo', 'تایپوگرافی', '.dsn-gw small' );
		$this->color( 'gw_d_color', 'رنگ', '.dsn-gw small' );
		$this->head( 'gw_r_h', 'دکمه رادیویی' );
		$this->states( 'gwr', [
			'normal'  => [ 'عادی', '.dsn-gw:not(.is-on) .dsn-box' ],
			'checked' => [ 'انتخاب‌شده', '.dsn-gw.is-on .dsn-box' ],
		], [ 'bg', 'border' ] );
		$this->color( 'gw_dot', 'رنگ نقطه داخلی', '.dsn-gw.is-on .dsn-box:after', 'background-color' );
		$this->slider( 'gw_icon_h', 'ارتفاع آیکن درگاه', '.dsn-gw img', 'max-height', 12, 80 );
		$this->end_controls_section();

		/* 14. لودینگ */
		$this->sec( 'st_loader', 'لودینگ (انیمیشن انتظار)' );
		$this->slider( 'ld_minh', 'حداقل ارتفاع ناحیه', '.dsn-loader', 'min-height', 80, 600 );
		$this->slider( 'ld_size', 'اندازه انیمیشن', '.dsn-spin', 'width', 16, 120 );
		$this->slider( 'ld_size_h', 'ارتفاع انیمیشن', '.dsn-spin', 'height', 16, 120 );
		$this->slider( 'ld_thick', 'ضخامت', '.dsn-spin', 'border-width', 1, 16 );
		$this->color( 'ld_color', 'رنگ چرخنده', '.dsn-spin', 'border-top-color' );
		$this->color( 'ld_track', 'رنگ مسیر', '.dsn-spin', 'border-color' );
		$this->slider( 'ld_speed', 'مدت یک دور (ثانیه)', '.dsn-spin', 'animation-duration', 0.3, 4, 's', 0.1 );
		$this->slider( 'ld_gap', 'فاصله انیمیشن تا متن', '.dsn-loader', 'gap', 0, 40 );
		$this->typo( 'ld_typo', 'تایپوگرافی متن', '.dsn-loader-t' );
		$this->color( 'ld_text', 'رنگ متن', '.dsn-loader-t' );
		$this->end_controls_section();
	}

	/* ================= render ================= */
	protected function render() {
		$s   = $this->get_settings_for_display();
		$cfg = DSN_ACF::default_cfg();
		foreach ( DSN_ACF::texts() as $k => $d ) {
			if ( isset( $s[ 't_' . $k ] ) && $s[ 't_' . $k ] !== '' ) $cfg['t'][ $k ] = wp_strip_all_tags( $s[ 't_' . $k ] );
		}
		$cfg['map'] = [
			'lat'    => (float) ( $s['map_lat'] ?: 35.6892 ),
			'lng'    => (float) ( $s['map_lng'] ?: 51.3890 ),
			'zoom'   => (int) ( $s['map_zoom'] ?: 12 ),
			'type'   => $s['map_type'] ?: 'standard-day',
			'locate' => ( $s['map_locate'] ?? 'yes' ) === 'yes',
		];
		$cfg['preview'] = $s['preview'] ?? 'none';
		echo DSN_ACF::root_html( $cfg ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
