<?php
/**
 * A short, accessible page skeleton while the browser is still parsing slow HTML.
 * Real content remains server-rendered underneath; image loading keeps using the
 * existing per-image shimmer after this first-paint overlay has gone away.
 */

defined( 'ABSPATH' ) || exit;

/** Return a small set of layouts for pages where a first-paint skeleton helps. */
function jluxe_page_skeleton_layout(): string {
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return '';
	}

	$context = function_exists( 'jluxe_page_context' ) ? jluxe_page_context() : '';
	$layouts = array(
		'home'     => 'home',
		'product'  => 'product',
		'blog'     => 'listing',
		'shop'     => 'listing',
		'search'   => 'listing',
		'archive'  => 'listing',
		'singular' => 'article',
	);

	return $layouts[ $context ] ?? '';
}

/** Start showing the overlay only if HTML parsing takes longer than 180ms. */
function jluxe_page_skeleton_bootstrap(): void {
	if ( '' === jluxe_page_skeleton_layout() ) {
		return;
	}
	?>
	<script id="jluxe-page-skeleton-bootstrap">
	(function () {
		var root = document.documentElement;
		var className = "jluxe-page-skeleton-active";
		var revealTimer = window.setTimeout(function () {
			if (document.readyState === "loading") {
				root.classList.add(className);
			}
		}, 180);
		function finish() {
			window.clearTimeout(revealTimer);
			root.classList.remove(className);
		}
		if (document.readyState === "loading") {
			document.addEventListener("readystatechange", function () {
				if (document.readyState !== "loading") finish();
			});
			window.addEventListener("load", finish, { once: true });
		} else {
			finish();
		}
	}());
	</script>
	<?php
}
add_action( 'wp_head', 'jluxe_page_skeleton_bootstrap', 1 );

/** Render an aria-hidden, pointer-transparent skeleton at the start of <body>. */
function jluxe_render_page_skeleton(): void {
	$layout = jluxe_page_skeleton_layout();
	if ( '' === $layout ) {
		return;
	}
	?>
	<div class="jluxe-page-skeleton jluxe-page-skeleton--<?php echo esc_attr( $layout ); ?>" data-jluxe-page-skeleton aria-hidden="true" hidden>
		<div class="jluxe-page-skeleton__page">
			<div class="jluxe-page-skeleton__header">
				<span class="jluxe-page-skeleton__block jluxe-page-skeleton__brand"></span>
				<span class="jluxe-page-skeleton__block jluxe-page-skeleton__search"></span>
				<div class="jluxe-page-skeleton__actions">
					<span class="jluxe-page-skeleton__block jluxe-page-skeleton__action"></span>
					<span class="jluxe-page-skeleton__block jluxe-page-skeleton__action"></span>
				</div>
			</div>
			<main class="jluxe-page-skeleton__content">
				<?php if ( 'home' === $layout ) : ?>
					<div class="jluxe-page-skeleton__block jluxe-page-skeleton__hero"></div>
					<div class="jluxe-page-skeleton__block jluxe-page-skeleton__section-title"></div>
					<div class="jluxe-page-skeleton__cards">
						<?php for ( $index = 0; $index < 4; $index++ ) : ?>
							<div class="jluxe-page-skeleton__card">
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__card-media"></span>
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--short"></span>
							</div>
						<?php endfor; ?>
					</div>
				<?php elseif ( 'product' === $layout ) : ?>
					<span class="jluxe-page-skeleton__block jluxe-page-skeleton__breadcrumb"></span>
					<div class="jluxe-page-skeleton__product">
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__product-gallery"></span>
						<div class="jluxe-page-skeleton__product-info">
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--title"></span>
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--short"></span>
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__price"></span>
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__options"></span>
							<span class="jluxe-page-skeleton__block jluxe-page-skeleton__button"></span>
						</div>
					</div>
					<div class="jluxe-page-skeleton__product-sections">
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__section-title"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
					</div>
				<?php elseif ( 'article' === $layout ) : ?>
					<span class="jluxe-page-skeleton__block jluxe-page-skeleton__breadcrumb"></span>
					<div class="jluxe-page-skeleton__article">
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__article-hero"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--title"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--short"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
						<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--medium"></span>
					</div>
				<?php else : ?>
					<span class="jluxe-page-skeleton__block jluxe-page-skeleton__section-title"></span>
					<div class="jluxe-page-skeleton__cards jluxe-page-skeleton__cards--listing">
						<?php for ( $index = 0; $index < 6; $index++ ) : ?>
							<div class="jluxe-page-skeleton__card">
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__card-media"></span>
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line"></span>
								<span class="jluxe-page-skeleton__block jluxe-page-skeleton__line jluxe-page-skeleton__line--short"></span>
							</div>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			</main>
		</div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'jluxe_render_page_skeleton', 0 );
