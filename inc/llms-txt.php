<?php
/**
 * R80 — llms.txt (llmstxt.org).
 *
 * چرا این‌جاست و نه در ریشهٔ سایت: وردپرس فایل‌های واقعیِ ریشه را از دیسک
 * می‌خواند، ولی پوسته اجازهٔ نوشتن در ریشه را ندارد. پس همان مسیر دقیقاً
 * («/llms.txt») از طریقِ template_redirect با Content-Type درست سرو می‌شود.
 * اگر ادمین خودش یک فایلِ واقعیِ llms.txt در ریشه بگذارد، وب‌سرور آن را
 * زودتر سرو می‌کند و این تابع هرگز اجرا نمی‌شود.
 *
 * محتوا عمداً «واقعیتِ خودِ سایت» است: عنوان یک H1، یک خلاصه، و فهرستِ
 * لینک‌های اصلی (فروشگاه/دسته‌ها/بلاگ/تماس) — نه توضیحِ سلیقه‌ای. هیچ
 * داده‌ای جعل نمی‌شود و اگر ساختاری نباشد، همان بخش حذف می‌شود.
 */

defined( 'ABSPATH' ) || exit;

/** فعال/غیرفعالِ سروِ llms.txt (پیش‌فرض: فعال). */
function jluxe_llms_txt_enabled(): bool {
	return (bool) apply_filters( 'jluxe_llms_txt_enabled', true );
}

/** ساختِ متنِ llms.txt از دادهٔ واقعی سایت. */
function jluxe_llms_txt_content(): string {
	$name = (string) get_bloginfo( 'name' );
	$desc = (string) get_bloginfo( 'description' );
	$home = untrailingslashit( home_url( '/' ) );

	$out = '# ' . ( $name ? $name : 'فروشگاه' ) . "\n\n";
	if ( $desc ) {
		$out .= '> ' . $desc . "\n\n";
	}

	$out .= "## صفحاتِ اصلی\n\n";
	$out .= '- [صفحهٔ اصلی](' . $home . "/)\n";
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$shop = wc_get_page_permalink( 'shop' );
		if ( $shop ) {
			$out .= '- [فروشگاه (همهٔ محصولات)](' . untrailingslashit( $shop ) . ")\n";
		}
		$sale = add_query_arg( 'on_sale', '1', $shop );
		$out .= '- [محصولات تخفیف‌دار](' . $sale . ")\n";
		$out .= '- [سبد خرید](' . untrailingslashit( wc_get_page_permalink( 'cart' ) ) . ")\n";
		$out .= '- [تسویه حساب](' . untrailingslashit( wc_get_page_permalink( 'checkout' ) ) . ")\n";
		$out .= '- [حساب کاربری](' . untrailingslashit( wc_get_page_permalink( 'myaccount' ) ) . ")\n";
	}
	if ( function_exists( 'jluxe_blog_url' ) ) {
		$out .= '- [بلاگ](' . untrailingslashit( (string) jluxe_blog_url() ) . ")\n";
	}
	$out .= "\n";

	if ( function_exists( 'get_terms' ) && function_exists( 'get_term_link' ) && taxonomy_exists( 'product_cat' ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => 20,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$out .= "## دسته‌بندی‌های اصلی\n\n";
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$count = (int) $term->count;
				$out  .= sprintf( "- [%s](%s)%s\n", $term->name, untrailingslashit( (string) $link ), $count > 0 ? ' — ' . $count . ' محصول' : '' );
			}
			$out .= "\n";
		}
	}

	$out .= "## دربارهٔ این فایل\n\n";
	$out .= "- کاتالوگِ محصولات HTMLِ همین سایت است؛ API عمومیِ محصولات وجود ندارد. برای دادهٔ ساخت‌یافته به «sitemap_index.xml» مراجعه کنید.\n";
	$out .= '- Owner: ' . $home . "/\n";
	if ( $desc ) {
		$out .= '- Summary: ' . $desc . "\n";
	}

	return (string) apply_filters( 'jluxe_llms_txt_content', $out );
}

/** سروِ /llms.txt با Content-Type درست و بدونِ دخالتِ قالب. */
function jluxe_serve_llms_txt(): void {
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! jluxe_llms_txt_enabled() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( '/llms.txt' !== untrailingslashit( $path ) ) {
		return;
	}

	$body = jluxe_llms_txt_content();

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'X-Content-Type-Options: nosniff' );
	status_header( 200 );
	if ( 'HEAD' !== $method ) {
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — متنِ Markdown، نه HTML.
	}
	exit;
}
add_action( 'template_redirect', 'jluxe_serve_llms_txt', 0 );
