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
	if ( ! in_array( $mode, array( 'text', 'image' ), true ) ) {
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
	$message   = isset( $content['message'] ) ? $content['message'] : '';
	$is_image = 'image' === $content['mode'];
	$is_sticky = ! empty( $settings['sticky'] );

	$defaults         = jluxe_theme_settings_defaults()['announcement_bar'];
	$background_color = isset( $settings['background_color'] ) && is_scalar( $settings['background_color'] ) ? sanitize_hex_color( (string) $settings['background_color'] ) : null;
	$text_color       = isset( $settings['text_color'] ) && is_scalar( $settings['text_color'] ) ? sanitize_hex_color( (string) $settings['text_color'] ) : null;
	$background_color = $background_color ?: $defaults['background_color'];
	$text_color       = $text_color ?: $defaults['text_color'];
	$link_text        = isset( $settings['link_text'] ) && is_scalar( $settings['link_text'] ) ? trim( (string) $settings['link_text'] ) : '';
	$link_url         = isset( $settings['link_url'] ) && is_scalar( $settings['link_url'] ) ? trim( (string) $settings['link_url'] ) : '';
	$dismissible      = ! empty( $settings['dismissible'] );
	$image_alt        = ! empty( $content['alt'] ) ? $content['alt'] : 'اطلاعیهٔ سایت';
	$dismiss_key      = md5( serialize( array( $content['mode'], $message, $content['id'] ?? 0, $content['url'] ?? '', $image_alt, $link_text, $link_url ) ) );
	?>
	<div
		id="jluxe-announcement-bar"
		class="jluxe-announcement-bar jluxe-announcement-bar--<?php echo esc_attr( $content['mode'] ); ?><?php echo $is_sticky ? ' jluxe-announcement-bar--sticky' : ''; ?>"
		role="region"
		data-content-mode="<?php echo esc_attr( $content['mode'] ); ?>"
		data-sticky="<?php echo $is_sticky ? '1' : '0'; ?>"
		aria-label="اطلاعیهٔ سایت"
		aria-live="polite"
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
