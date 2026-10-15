<?php
/**
 * Global announcement bar. Its measured height is shared with the fixed
 * header, keeping the announcement, WordPress admin bar, and header stacked.
 */

defined( 'ABSPATH' ) || exit;

/** Return the selected, displayable content without losing GIF animation. */
function jluxe_announcement_content_payload( array $settings ): array {
	$mode = isset( $settings['display_mode'] ) && is_scalar( $settings['display_mode'] )
		? (string) $settings['display_mode']
		: 'text';
	if ( ! in_array( $mode, array( 'text', 'ticker', 'image' ), true ) ) {
		$mode = 'text';
	}

	if ( 'text' === $mode ) {
		$message = isset( $settings['message'] ) && is_scalar( $settings['message'] ) ? trim( (string) $settings['message'] ) : '';
		return array(
			'mode'    => 'text',
			'ready'   => '' !== $message,
			'message' => $message,
		);
	}

	if ( 'ticker' === $mode ) {
		$label = isset( $settings['ticker_label'] ) && is_scalar( $settings['ticker_label'] )
			? trim( (string) $settings['ticker_label'] )
			: 'ارسال سریع';
		$label = '' !== $label ? $label : 'ارسال سریع';
		$theme = isset( $settings['ticker_theme'] ) && is_scalar( $settings['ticker_theme'] )
			? sanitize_key( (string) $settings['ticker_theme'] )
			: 'classic';
		if ( ! in_array( $theme, array( 'classic', 'info', 'warning', 'discount', 'success', 'custom' ), true ) ) {
			$theme = 'classic';
		}
		$raw_messages = isset( $settings['ticker_messages'] ) && is_scalar( $settings['ticker_messages'] )
			? (string) $settings['ticker_messages']
			: '';
		$message_lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $raw_messages ) );
		$messages = array_slice(
			array_values( array_filter( array_map( 'trim', is_array( $message_lines ) ? $message_lines : array() ) ) ),
			0,
			5
		);
		$messages = array_values( array_filter( $messages, static fn( $message ) => '' !== $message ) );
		$summary = implode( ' ', $messages );

		return array(
			'mode'            => 'ticker',
			'ready'           => ! empty( $messages ),
			'ticker_label'    => $label,
			'ticker_theme'    => $theme,
			'ticker_messages' => $messages,
			'ticker_summary'  => $summary,
		);
	}

	$image_id = isset( $settings['image_id'] ) && is_scalar( $settings['image_id'] ) ? absint( $settings['image_id'] ) : 0;
	if ( 0 === $image_id ) {
		return array( 'mode' => 'image', 'ready' => false );
	}

	$mime = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $image_id ) : '';
	if ( '' !== $mime && 0 !== strpos( $mime, 'image/' ) ) {
		return array( 'mode' => 'image', 'ready' => false );
	}

	$is_gif    = 'image/gif' === strtolower( $mime );
	$image_url = '';
	if ( $is_gif && function_exists( 'wp_get_attachment_url' ) ) {
		// WordPress-generated GIF thumbnails often contain only the first frame.
		// Use the attachment's original URL so its animation remains intact.
		$image_url = (string) wp_get_attachment_url( $image_id );
	}
	if ( '' === $image_url && function_exists( 'wp_get_attachment_image_url' ) ) {
		$image_url = (string) wp_get_attachment_image_url( $image_id, $is_gif ? 'full' : 'large' );
	}
	if ( '' === $image_url ) {
		return array( 'mode' => 'image', 'ready' => false );
	}

	$image_size = $is_gif ? 'full' : 'large';
	$image_src  = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $image_id, $image_size ) : false;
	$alt        = isset( $settings['image_alt'] ) && is_scalar( $settings['image_alt'] ) ? trim( (string) $settings['image_alt'] ) : '';

	return array(
		'mode'   => 'image',
		'ready'  => true,
		'id'     => $image_id,
		'url'    => $image_url,
		'mime'   => $mime,
		'alt'    => $alt,
		'width'  => is_array( $image_src ) && isset( $image_src[1] ) ? absint( $image_src[1] ) : 0,
		'height' => is_array( $image_src ) && isset( $image_src[2] ) ? absint( $image_src[2] ) : 0,
	);
}

/**
 * Render a small, static badge for the text ticker instead of carrying its logo
 * inside a large animated banner. Prefer an explicitly selected logo, then the
 * WordPress site icon, and finally an accessible text badge.
 */
function jluxe_announcement_ticker_logo_markup( int $logo_id = 0 ): string {
	$site_icon_id = function_exists( 'get_option' ) ? absint( get_option( 'site_icon' ) ) : 0;
	$logo_ids     = array_values( array_unique( array_filter( array( absint( $logo_id ), $site_icon_id ) ) ) );

	foreach ( $logo_ids as $candidate_id ) {
		$mime_type = function_exists( 'get_post_mime_type' ) ? strtolower( (string) get_post_mime_type( $candidate_id ) ) : '';
		if ( ( '' !== $mime_type && 0 !== strpos( $mime_type, 'image/' ) ) || 'image/gif' === $mime_type ) {
			continue;
		}

		if ( function_exists( 'wp_get_attachment_image' ) ) {
			$image = wp_get_attachment_image(
				$candidate_id,
				array( 96, 96 ),
				false,
				array(
					'class'         => 'jluxe-announcement-bar__ticker-logo-image',
					'alt'           => '',
					'aria-hidden'   => 'true',
					'width'         => 96,
					'height'        => 96,
					'loading'       => 'eager',
					'decoding'      => 'async',
					'sizes'         => '48px',
				)
			);
			if ( is_string( $image ) && '' !== $image ) {
				return $image;
			}
		}

		if ( function_exists( 'wp_get_attachment_image_url' ) ) {
			$image_url = wp_get_attachment_image_url( $candidate_id, 'thumbnail' );
			if ( $image_url ) {
				return '<img class="jluxe-announcement-bar__ticker-logo-image" src="' . esc_url( $image_url ) . '" alt="" aria-hidden="true" width="96" height="96" loading="eager" decoding="async" sizes="48px" />';
			}
		}
	}

	return '<span class="jluxe-announcement-bar__ticker-logo-fallback" aria-hidden="true"><span>جهیزیه</span><span>لوکس</span></span>';
}

/** Mark pages with a real bar so CSS can prevent overlap if JavaScript is off. */
function jluxe_announcement_body_classes( array $classes ): array {
	if ( function_exists( 'jluxe_is_woocommerce_account_page' ) && jluxe_is_woocommerce_account_page() ) {
		return $classes;
	}
	if ( ! function_exists( 'jluxe_get_theme_settings' ) ) {
		return $classes;
	}
	$settings = jluxe_get_theme_settings()['announcement_bar'] ?? array();
	if ( ! is_array( $settings ) || empty( $settings['enabled'] ) ) {
		return $classes;
	}
	$content = jluxe_announcement_content_payload( $settings );
	if ( ! empty( $content['ready'] ) && ! in_array( 'jluxe-has-announcement', $classes, true ) ) {
		$classes[] = 'jluxe-has-announcement';
	}
	return $classes;
}
add_filter( 'body_class', 'jluxe_announcement_body_classes', 10, 1 );

function jluxe_render_announcement_bar(): void {
	if ( function_exists( 'jluxe_is_woocommerce_account_page' ) && jluxe_is_woocommerce_account_page() ) {
		return;
	}
	if ( ! function_exists( 'jluxe_get_theme_settings' ) ) {
		return;
	}

	$settings = jluxe_get_theme_settings()['announcement_bar'] ?? array();
	if ( ! is_array( $settings ) ) {
		return;
	}
	$content = jluxe_announcement_content_payload( $settings );
	if ( empty( $settings['enabled'] ) || empty( $content['ready'] ) ) {
		return;
	}
	$message    = isset( $content['message'] ) ? $content['message'] : '';
	$is_image   = 'image' === $content['mode'];
	$is_ticker  = 'ticker' === $content['mode'];
	$is_sticky  = ! empty( $settings['sticky'] );

	$defaults         = jluxe_theme_settings_defaults()['announcement_bar'];
	$background_color = isset( $settings['background_color'] ) && is_scalar( $settings['background_color'] ) ? sanitize_hex_color( (string) $settings['background_color'] ) : null;
	$text_color       = isset( $settings['text_color'] ) && is_scalar( $settings['text_color'] ) ? sanitize_hex_color( (string) $settings['text_color'] ) : null;
	$background_color = $background_color ?: $defaults['background_color'];
	$text_color       = $text_color ?: $defaults['text_color'];
	$link_text        = isset( $settings['link_text'] ) && is_scalar( $settings['link_text'] ) ? trim( (string) $settings['link_text'] ) : '';
	$link_url         = isset( $settings['link_url'] ) && is_scalar( $settings['link_url'] ) ? trim( (string) $settings['link_url'] ) : '';
	$dismissible      = ! empty( $settings['dismissible'] );
	$image_alt        = ! empty( $content['alt'] ) ? $content['alt'] : 'اطلاعیهٔ سایت';
	$dismiss_key      = md5( serialize( array( $content['mode'], $message, $content['id'] ?? 0, $content['url'] ?? '', $image_alt, $content['ticker_label'] ?? '', $content['ticker_theme'] ?? '', $content['ticker_messages'] ?? array(), $link_text, $link_url ) ) );
	$region_label     = $is_ticker
		? 'اطلاعیهٔ سایت؛ ' . $content['ticker_label'] . ': ' . $content['ticker_summary']
		: 'اطلاعیهٔ سایت';
	$ticker_messages         = $is_ticker ? $content['ticker_messages'] : array();
	$ticker_message_count    = count( $ticker_messages );
	$ticker_message_duration = 4;
	$ticker_cycle            = $ticker_message_duration * $ticker_message_count;
	$ticker_sequence_class   = $ticker_message_count > 1 ? ' has-multiple has-' . $ticker_message_count : '';
	$ticker_theme             = $is_ticker ? $content['ticker_theme'] : 'classic';
	$ticker_logo_id           = isset( $settings['ticker_logo_id'] ) && is_scalar( $settings['ticker_logo_id'] ) ? absint( $settings['ticker_logo_id'] ) : 0;
	$ticker_logo_markup       = $is_ticker ? jluxe_announcement_ticker_logo_markup( $ticker_logo_id ) : '';
	?>
	<div
		id="jluxe-announcement-bar"
		class="jluxe-announcement-bar jluxe-announcement-bar--<?php echo esc_attr( $content['mode'] ); ?><?php echo $is_sticky ? ' jluxe-announcement-bar--sticky' : ''; ?>"
		role="region"
		data-content-mode="<?php echo esc_attr( $content['mode'] ); ?>"
		<?php if ( $is_ticker ) : ?>data-ticker-theme="<?php echo esc_attr( $ticker_theme ); ?>"<?php endif; ?>
		data-sticky="<?php echo $is_sticky ? '1' : '0'; ?>"
		aria-label="<?php echo esc_attr( $region_label ); ?>"
		aria-live="<?php echo $is_ticker ? 'off' : 'polite'; ?>"
		data-dismissible="<?php echo $dismissible ? '1' : '0'; ?>"
		data-dismiss-key="<?php echo esc_attr( $dismiss_key ); ?>"
		style="--jluxe-announcement-background:<?php echo esc_attr( $background_color ); ?>;--jluxe-announcement-foreground:<?php echo esc_attr( $text_color ); ?>"
	>
		<div class="jluxe-announcement-bar__inner">
			<?php if ( $is_image ) : ?>
				<?php if ( '' !== $link_url ) : ?>
					<a class="jluxe-announcement-bar__image-link" href="<?php echo esc_url( $link_url ); ?>" aria-label="<?php echo esc_attr( '' !== $link_text ? $link_text : $image_alt ); ?>">
				<?php endif; ?>
				<span class="jluxe-announcement-bar__image-window">
					<img class="jluxe-announcement-bar__image" src="<?php echo esc_url( $content['url'] ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>"<?php if ( ! empty( $content['width'] ) && ! empty( $content['height'] ) ) : ?> width="<?php echo esc_attr( $content['width'] ); ?>" height="<?php echo esc_attr( $content['height'] ); ?>"<?php endif; ?> decoding="async" />
				</span>
				<?php if ( '' !== $link_url ) : ?>
					</a>
				<?php endif; ?>
			<?php elseif ( $is_ticker ) : ?>
				<svg class="jluxe-announcement-bar__ticker-waves" viewBox="0 0 1600 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
					<path d="M-20 46 C35 16 72 76 127 46 S219 16 274 46 S366 76 421 46 S513 16 568 46 S660 76 715 46 S807 16 862 46 S954 76 1009 46 S1101 16 1156 46 S1248 76 1303 46 S1395 16 1450 46 S1542 76 1597 46 S1689 16 1744 46" />
					<path d="M-20 70 C35 40 72 100 127 70 S219 40 274 70 S366 100 421 70 S513 40 568 70 S660 100 715 70 S807 40 862 70 S954 100 1009 70 S1101 40 1156 70 S1248 100 1303 70 S1395 40 1450 70 S1542 100 1597 70 S1689 40 1744 70" />
					<path d="M-20 22 C35 -8 72 52 127 22 S219 -8 274 22 S366 52 421 22 S513 -8 568 22 S660 52 715 22 S807 -8 862 22 S954 52 1009 22 S1101 -8 1156 22 S1248 52 1303 22 S1395 -8 1450 22 S1542 52 1597 22 S1689 -8 1744 22" />
				</svg>
				<div class="jluxe-announcement-bar__ticker-visual" dir="rtl" aria-hidden="true">
					<strong class="jluxe-announcement-bar__ticker-label"><?php echo esc_html( $content['ticker_label'] ); ?></strong>
					<span class="jluxe-announcement-bar__ticker-divider"></span>
					<span class="jluxe-announcement-bar__ticker-window">
						<span class="jluxe-announcement-bar__ticker-sequence<?php echo esc_attr( $ticker_sequence_class ); ?>">
							<?php foreach ( $ticker_messages as $ticker_index => $ticker_message ) : ?>
								<span class="jluxe-announcement-bar__ticker-phrase" style="--jluxe-ticker-cycle:<?php echo esc_attr( $ticker_cycle ); ?>s;--jluxe-ticker-delay:<?php echo esc_attr( $ticker_message_duration * $ticker_index ); ?>s;"><?php echo esc_html( $ticker_message ); ?></span>
							<?php endforeach; ?>
						</span>
						<span class="jluxe-announcement-bar__ticker-static"><?php echo esc_html( $content['ticker_summary'] ); ?></span>
					</span>
					<span class="jluxe-announcement-bar__ticker-logo"><?php echo $ticker_logo_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- تولیدشده توسط wp_get_attachment_image یا fallback ثابتِ خودِ تم. ?></span>
				</div>
				<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
					<a class="jluxe-announcement-bar__ticker-link" href="<?php echo esc_url( $link_url ); ?>"><?php echo esc_html( $link_text ); ?></a>
				<?php endif; ?>
			<?php else : ?>
				<p class="jluxe-announcement-bar__message"><?php echo esc_html( $message ); ?></p>
				<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
					<a class="jluxe-announcement-bar__link" href="<?php echo esc_url( $link_url ); ?>"><?php echo esc_html( $link_text ); ?></a>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $dismissible ) : ?>
				<button type="button" class="jluxe-announcement-bar__dismiss" data-jluxe-announcement-dismiss aria-label="بستن اطلاعیه">
					<span aria-hidden="true">×</span>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<script>
	(function () {
		var bar = document.getElementById('jluxe-announcement-bar');
		if (!bar) return;
		var root = document.documentElement;
		var storageKey = 'jluxe-announcement:' + (bar.getAttribute('data-dismiss-key') || '');
		var sticky = bar.getAttribute('data-sticky') === '1';
		var adminBar = document.getElementById('wpadminbar');
		var scrollFramePending = false;
		var getAdminBarHeight = function () {
			return adminBar ? Math.max(0, adminBar.getBoundingClientRect().height) : 0;
		};
		var updateHeaderOffset = function () {
			var height = bar.hidden ? 0 : Math.max(0, bar.getBoundingClientRect().height);
			var headerOffset = 0;
			if (!bar.hidden) {
				headerOffset = sticky
					? height
					: Math.max(0, bar.getBoundingClientRect().bottom - getAdminBarHeight());
			}
			root.style.setProperty('--jluxe-announcement-height', height + 'px');
			root.style.setProperty('--jluxe-announcement-sticky-height', headerOffset + 'px');
		};
		var scheduleHeaderOffsetUpdate = function () {
			if (scrollFramePending) return;
			scrollFramePending = true;
			var applyUpdate = function () {
				scrollFramePending = false;
				updateHeaderOffset();
			};
			if (window.requestAnimationFrame) window.requestAnimationFrame(applyUpdate);
			else applyUpdate();
		};
		if (bar.getAttribute('data-dismissible') === '1') {
			try {
				if (window.sessionStorage.getItem(storageKey) === '1') bar.hidden = true;
			} catch (error) {}
		}
		updateHeaderOffset();
		if (document.body) document.body.classList.add('jluxe-announcement-ready');
		window.addEventListener('resize', updateHeaderOffset, { passive: true });
		if (!sticky) window.addEventListener('scroll', scheduleHeaderOffsetUpdate, { passive: true });
		if ('ResizeObserver' in window) {
			var resizeObserver = new ResizeObserver(updateHeaderOffset);
			resizeObserver.observe(bar);
			bar._jluxeAnnouncementResizeObserver = resizeObserver;
		}
		bar.querySelectorAll('img').forEach(function (image) {
			image.addEventListener('load', updateHeaderOffset);
			image.addEventListener('error', updateHeaderOffset);
		});
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(updateHeaderOffset);
		var dismissButton = bar.querySelector('[data-jluxe-announcement-dismiss]');
		if (dismissButton) {
			dismissButton.addEventListener('click', function () {
				bar.hidden = true;
				updateHeaderOffset();
				try { window.sessionStorage.setItem(storageKey, '1'); } catch (error) {}
			});
		}
	}());
	</script>
	<?php
}
