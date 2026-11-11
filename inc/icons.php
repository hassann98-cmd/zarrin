<?php
/**
 * کتابخانهٔ مشترک SVG زرین: UIcons گرد و برندهای Flaticon به‌صورت مسیرهای
 * SVG درون‌خطی، همراه با fallbackهای خطیِ قدیمی. هیچ فونت یا درخواست خارجی
 * لازم نیست؛ همهٔ آیکون‌ها با currentColor رنگ می‌گیرند.
 *
 * استفاده: jluxe_icon( 'bed', 'size-5 text-primary' ); نام نامعتبر خالی است.
 * منبع و مجوز: assets/icons/flaticon-uicons.json و docs/THIRD-PARTY-NOTICES.md.
 */

defined( 'ABSPATH' ) || exit;

/** بارگذاریِ یک‌بارهٔ مسیرهای UIcons از منبع مشترک PHP و React. */
function jluxe_uicon_paths(): array {
	static $paths = null;
	if ( null !== $paths ) {
		return $paths;
	}

	$paths = array();
	$file  = dirname( __DIR__ ) . '/assets/icons/flaticon-uicons.json';
	if ( ! is_readable( $file ) ) {
		return $paths;
	}

	$json = file_get_contents( $file );
	$data = is_string( $json ) ? json_decode( $json, true ) : null;
	if ( ! is_array( $data['icons'] ?? null ) ) {
		return $paths;
	}

	foreach ( $data['icons'] as $name => $icon ) {
		$d = is_array( $icon ) && is_string( $icon['d'] ?? null ) ? trim( $icon['d'] ) : '';
		if ( preg_match( '/^[a-z0-9-]+$/D', (string) $name ) && '' !== $d && preg_match( '/^[MmZzLlHhVvCcSsQqTtAaEe0-9,.+\-\s]+$/D', $d ) ) {
			$paths[ (string) $name ] = $d;
		}
	}

	// User-supplied tracking artwork; kept separate from the third-party catalog.
	$tracking_file = dirname( __DIR__ ) . '/assets/icons/order-tracking.svg';
	$tracking_svg = is_readable( $tracking_file ) ? file_get_contents( $tracking_file ) : '';
	if ( is_string( $tracking_svg ) && preg_match( '/<path\s+d="([^"]+)"/', $tracking_svg, $tracking_match ) && preg_match( '/^[MmZzLlHhVvCcSsQqTtAaEe0-9,.+\-\s]+$/D', $tracking_match[1] ) ) {
		$paths['order-tracking'] = $tracking_match[1];
	}

	return $paths;
}

/**
 * فقط مسیرهایی را به React می‌فرستد که در آیلندهای عمومیِ فعلی لازم‌اند؛
 * فایل کاملِ ۶۷ آیکون هرگز به باندل اولیهٔ JavaScript اضافه نمی‌شود.
 */
function jluxe_public_icon_glyphs( array $settings ): array {
	$names = array();
	$collect = static function ( $value ) use ( &$collect, &$names ): void {
		if ( ! is_array( $value ) ) {
			return;
		}
		if ( isset( $value['icon'] ) && is_scalar( $value['icon'] ) ) {
			$name = sanitize_key( (string) $value['icon'] );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$collect( $child );
			}
		}
	};

	foreach ( array(
		$settings['mobile']['nav_items'] ?? array(),
		$settings['mobile']['floating_nav_items'] ?? array(),
		$settings['footer']['feature_cards'] ?? array(),
		$settings['footer']['link_columns'] ?? array(),
	) as $section ) {
		$collect( $section );
	}

	foreach ( (array) ( $settings['social'] ?? array() ) as $network => $social ) {
		if ( ! is_array( $social ) || empty( $social['enabled'] ) || ! empty( $social['svg'] ) ) {
			continue;
		}
		$names[] = 'brand-' . sanitize_key( (string) $network );
	}

	$available = jluxe_uicon_paths();
	$glyphs    = array();
	foreach ( array_unique( $names ) as $name ) {
		if ( isset( $available[ $name ] ) ) {
			$glyphs[ $name ] = $available[ $name ];
		}
	}
	return $glyphs;
}

/** ساخت SVG پُر از یک glyph معتبر؛ رنگ از color/currentColor به ارث می‌رسد. */
function jluxe_uicon_svg_markup( string $name, string $class = 'size-5' ): string {
	$paths = jluxe_uicon_paths();
	if ( '' === $name || ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="%1$s" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" focusable="false"><path d="%2$s" fill-rule="nonzero"/></svg>',
		esc_attr( $class ),
		esc_attr( $paths[ $name ] )
	);
}

/** ساخت SVG عمومی؛ مسیرهای موجودِ قدیمی را حفظ و بقیه را از UIcons تکمیل می‌کند. */
function jluxe_icon_svg_markup( string $name, string $class = 'size-5', float $stroke_width = 1.6 ): string {
	$icons = jluxe_icon_paths();
	if ( '' !== $name && isset( $icons[ $name ] ) ) {
		return sprintf(
			'<svg class="%1$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $class ),
			esc_attr( rtrim( rtrim( number_format( max( 0.5, min( 4.0, $stroke_width ) ), 2, '.', '' ), '0' ), '.' ) ),
			$icons[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- مسیرهای ثابت همین فایل.
		);
	}

	return jluxe_uicon_svg_markup( $name, $class );
}

/** چاپ یک آیکون UIcon فقط در صورت معتبر بودن نام. */
function jluxe_uicon( string $name, string $class = 'size-5' ): void {
	echo jluxe_uicon_svg_markup( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی از glyphهای ثابت و escapeشده ساخته می‌شود.
}

/** پیوند انتسابِ لازم برای استفادهٔ رایگان از UIcons. */
function jluxe_uicons_attribution( bool $standalone = false ): void {
	$class = $standalone ? 'jluxe-uicons-attribution jluxe-uicons-attribution--standalone' : 'jluxe-uicons-attribution';
	printf(
		'<div class="%1$s" style="padding:0 .75rem .75rem;text-align:center;font-size:11px;line-height:1.5;color:hsl(var(--muted-foreground));"><a href="%2$s" style="color:inherit;text-decoration:underline;text-underline-offset:2px">Uicons by Flaticon</a></div>',
		esc_attr( $class ),
		esc_url( 'https://www.flaticon.com/uicons' )
	);
}

/** چاپ آیکون عمومی؛ شکل‌های قدیمی حفظ می‌شوند و نام‌های جدید از UIcons می‌آیند. */
function jluxe_icon( string $name, string $class = 'size-5' ): void {
	echo jluxe_icon_svg_markup( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی از markup ثابت و escapeشده ساخته می‌شود.
}

/** نام آیکون‌های موجود (برای تست و پنل). */
function jluxe_icon_names(): array {
	return array_values( array_unique( array_merge( array_keys( jluxe_uicon_paths() ), array_keys( jluxe_icon_paths() ) ) ) );
}

/** مسیرهای SVG خطیِ قدیمی؛ fallback برای نام‌هایی که در UIcons نیستند. */
function jluxe_icon_paths(): array {
	return array(
		'home'          => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
		'cart'          => '<circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M2.5 3.5h2l2.6 12h10.8l2.1-8.5H6"/>',
		'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-4.6-4.6"/>',
		'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
		'user'          => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-3.8 3.9-6 7.5-6s6.7 2.2 7.5 6"/>',
		'heart'         => '<path d="M19.46 4.99c-2.68-1.64-5.02-.98-6.43.08-.57.43-.86.65-1.03.65s-.46-.22-1.03-.65c-1.9-1.44-4.25-2.1-6.93-.46C1.02 7.15.22 14.27 8.34 20.28c1.55 1.14 2.32 1.72 3.66 1.72s2.11-.58 3.66-1.72c8.12-6.01 7.32-13.13 3.8-15.29"/>',
		'star'          => '<path d="m12 3 2.9 5.9 6.5.95-4.7 4.58 1.1 6.47L12 17.85 6.2 20.9l1.1-6.47-4.7-4.58 6.5-.95Z"/>',
		'chevron-left'  => '<path d="m14.5 6-6 6 6 6"/>',
		'chevron-right' => '<path d="m9.5 6 6 6-6 6"/>',
		'chevron-down'  => '<path d="m6 9.5 6 6 6-6"/>',
		'arrow-left'    => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
		'arrow-right'   => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
		'phone'         => '<path d="M15.5 20.5c-6-1.5-10.5-6-12-12-.3-1.2.4-2.4 1.5-2.8l1.9-.6c.8-.3 1.7.1 2 .9l1 2.6c.3.7.1 1.5-.5 2l-1.2 1c1.1 2.4 3 4.3 5.4 5.4l1-1.2c.5-.6 1.3-.8 2-.5l2.6 1c.8.3 1.2 1.2.9 2l-.6 1.9c-.4 1.1-1.6 1.8-2.8 1.5Z"/>',
		'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
		'map-pin'       => '<path d="M20 10c0 5.25-8 11-8 11S4 15.25 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
		'map'           => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15m6-12v15"/>',
		'credit-card'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-13 5h3"/>',
		'package'       => '<path d="m3.5 7.5 8.5-4 8.5 4v9l-8.5 4-8.5-4v-9Z"/><path d="M12 12v8.5M3.7 7.6 12 12l8.3-4.4M8 5.4l8.5 4.4"/>',
		'wallet'        => '<path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h13v16H5.5A2.5 2.5 0 0 1 3 17.5v-11A2.5 2.5 0 0 1 5.5 4"/><path d="M3 7h17m-4 5h4m-4-.01h.01"/>',
		'locate'        => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/><circle cx="12" cy="12" r="8"/>',
		'truck'         => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
		'shield-check'  => '<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10Z"/><path d="m9 11.5 2 2 4-4.5"/>',
		'headset'       => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M21 17v1a3 3 0 0 1-3 3h-4"/>',
		'menu'          => '<path d="M4 6.5h16"/><path d="M4 12h16"/><path d="M4 17.5h16"/>',
		'close'         => '<path d="m6 6 12 12"/><path d="M18 6 6 18"/>',
		'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
		'minus'         => '<path d="M5 12h14"/>',
		'trash'         => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v5"/><path d="M14 11v5"/>',
		'filter'        => '<path d="M4 5h16l-6.5 8v5.5L10.5 21v-8Z"/>',
		'compass'       => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/>',
		'eye'           => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
		'check'         => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'refresh'       => '<path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v4h-4"/>',
		'sparkles'      => '<path d="M12 3.5 13.8 9l5.5 1.8-5.5 1.8L12 18.1l-1.8-5.5-5.5-1.8L10.2 9Z"/><path d="M19 15.5l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9Z"/>',
	);
}
