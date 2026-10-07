<?php
/**
 * جستجوی زندهٔ سربرگ/موبایل — پیشنهادهای واقعیِ فروشگاه را در سه بخشِ
 * دسته‌بندی، برند و محصول می‌دهد. جستجو محدود است و برایِ املای فارسی،
 * نیم‌فاصله و رقم‌های فارسی/عربی/لاتین نرمال‌سازی و اصلاحِ نزدیک انجام می‌دهد.
 */

defined( 'ABSPATH' ) || exit;

/**
 * یکسان‌سازیِ حروف رایجِ عربی/فارسی، اعراب، فاصله و رقم‌ها برایِ جستجو.
 * خروجی در هر دو مسیرِ autocomplete و پیشنهادِ املایی استفاده می‌شود.
 */
function jluxe_normalize_persian_query( string $term ): string {
	// اگر intl رویِ هاست باشد، شکل‌هایِ compatibility (از جمله presentation form) هم باز می‌شوند؛ اجباری نیست.
	if ( class_exists( 'Normalizer' ) ) {
		$compatible = Normalizer::normalize( $term, Normalizer::FORM_KC );
		if ( is_string( $compatible ) ) {
			$term = $compatible;
		}
	}
	// ی/ي/ى و شکل‌های عربیِ صفحه‌آرایی‌شده.
	$term = str_replace( array( 'ي', 'ﻱ', 'ﻲ', 'ﻳ', 'ﻴ', 'ى', 'ﻯ', 'ﻰ' ), 'ی', $term );
	// کافِ عربی و شکل‌های رایجِ کافِ اردو.
	$term = str_replace( array( 'ك', 'ﻙ', 'ﻚ', 'ﻛ', 'ﻜ', 'ڪ' ), 'ک', $term );
	// شکل‌های الف و ه که در ورودیِ عربی برایِ همان واژه‌های فارسی به‌کار می‌روند.
	$term = str_replace( array( 'أ', 'إ', 'آ', 'ٱ', 'ﺍ', 'ﺎ' ), 'ا', $term );
	$term = str_replace( array( 'ة', 'ﺓ', 'ﺔ', 'ۀ', 'ہ', 'ھ', 'ﻩ', 'ﻪ', 'ﻫ', 'ﻬ' ), 'ه', $term );
	// رقم‌های فارسی، عربی و تمام‌عرض → رقمِ لاتین.
	$term = strtr(
		$term,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
		)
	);
	// حذفِ اعراب، علامت‌های ترکیبی و کشیده.
	$term = preg_replace( '/[\p{M}\x{0640}]/u', '', $term );
	// نیم‌فاصله/اتصال‌دهنده‌های نامرئی → فاصله؛ هر نوع فاصلهٔ پیاپی یکی می‌شود.
	$term = preg_replace( '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{00A0}\x{202F}]/u', ' ', (string) $term );
	$term = preg_replace( '/\s+/u', ' ', (string) $term );
	return strtolower( trim( (string) $term ) );
}

/** کلماتِ عبارت را بدونِ علائمِ نگارشی جدا می‌کند؛ stop wordها را هم نگه می‌دارد. */
function jluxe_search_phrase_tokens( string $term ): array {
	$normalized = jluxe_normalize_persian_query( $term );
	$tokens     = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $tokens ) ? array_values( $tokens ) : array();
}

/** کلماتِ مفید برایِ تطبیق؛ واژه‌هایِ رابطِ رایج از query محصولات حذف می‌شوند. */
function jluxe_search_tokens( string $term ): array {
	$stop_words = array( 'و', 'یا', 'در', 'با', 'برای', 'از', 'به', 'the', 'and', 'or', 'for', 'with' );
	$tokens     = array();
	foreach ( jluxe_search_phrase_tokens( $term ) as $token ) {
		if ( in_array( $token, $stop_words, true ) || isset( $tokens[ $token ] ) ) {
			continue;
		}
		$tokens[ $token ] = $token;
	}
	return array_values( $tokens );
}

/**
 * فاصلهٔ ویرایشیِ Unicode با پشتیبانی از جابه‌جاییِ دو حرفِ کنارِ هم.
 * levenshtein() خودِ PHP رویِ فارسی بایت‌شمار است؛ بنابراین این نسخه
 * کاراکترهایِ UTF-8 را جدا می‌کند و فقط برایِ کلماتِ کوتاه اجرا می‌شود.
 */
function jluxe_search_edit_distance( string $left, string $right ): int {
	$left_chars  = preg_split( '//u', $left, -1, PREG_SPLIT_NO_EMPTY );
	$right_chars = preg_split( '//u', $right, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $left_chars ) || ! is_array( $right_chars ) ) {
		return max( strlen( $left ), strlen( $right ) );
	}
	$left_length  = count( $left_chars );
	$right_length = count( $right_chars );
	if ( $left_length > 32 || $right_length > 32 ) {
		return max( $left_length, $right_length );
	}
	$distance = array();
	for ( $i = 0; $i <= $left_length; $i++ ) {
		$distance[ $i ]     = array();
		$distance[ $i ][0]  = $i;
	}
	for ( $j = 0; $j <= $right_length; $j++ ) {
		$distance[0][ $j ] = $j;
	}
	for ( $i = 1; $i <= $left_length; $i++ ) {
		for ( $j = 1; $j <= $right_length; $j++ ) {
			$cost = $left_chars[ $i - 1 ] === $right_chars[ $j - 1 ] ? 0 : 1;
			$distance[ $i ][ $j ] = min(
				$distance[ $i - 1 ][ $j ] + 1,
				$distance[ $i ][ $j - 1 ] + 1,
				$distance[ $i - 1 ][ $j - 1 ] + $cost
			);
			if (
				$i > 1 && $j > 1 &&
				$left_chars[ $i - 1 ] === $right_chars[ $j - 2 ] &&
				$left_chars[ $i - 2 ] === $right_chars[ $j - 1 ]
			) {
				$distance[ $i ][ $j ] = min( $distance[ $i ][ $j ], $distance[ $i - 2 ][ $j - 2 ] + 1 );
			}
		}
	}
	return (int) $distance[ $left_length ][ $right_length ];
}

function jluxe_search_max_edit_distance( string $token ): int {
	$length = jluxe_strlen( $token );
	return $length < 3 ? 0 : ( $length < 6 ? 1 : 2 );
}

/** امتیازِ تطبیقِ یک واژهٔ query با یک واژهٔ واقعی ازِ فروشگاه. */
function jluxe_search_token_match_score( string $query_token, string $candidate_token ): int {
	if ( $query_token === $candidate_token ) {
		return 100;
	}
	$query_length     = jluxe_strlen( $query_token );
	$candidate_length = jluxe_strlen( $candidate_token );
	if ( $query_length < 2 || $candidate_length < 2 ) {
		return 0;
	}
	if ( 0 === strpos( $candidate_token, $query_token ) ) {
		return 78;
	}
	if ( false !== strpos( $candidate_token, $query_token ) ) {
		return 68;
	}
	if ( 0 === strpos( $query_token, $candidate_token ) && $candidate_length >= 3 ) {
		return 58;
	}
	$maximum = jluxe_search_max_edit_distance( $query_token );
	if ( 0 === $maximum || abs( $query_length - $candidate_length ) > $maximum ) {
		return 0;
	}
	$distance = jluxe_search_edit_distance( $query_token, $candidate_token );
	return $distance <= $maximum ? 52 - ( 10 * $distance ) : 0;
}

function jluxe_search_term_cache_key( string $taxonomy ): string {
	return 'jluxe_search_terms_v2_' . md5( $taxonomy );
}

/**
 * فهرستِ کوچکِ termهایِ پرکاربرد به‌علاوهٔ تطبیق‌هایِ مستقیمِ کلمه‌ای.
 * این کار هم typoهایِ پرتکرار را پیدا می‌کند و هم query را به یک get_terms
 * نامحدود رویِ فروشگاه‌هایِ بزرگ تبدیل نمی‌کند.
 */
function jluxe_search_taxonomy_candidates( string $taxonomy, array $query_tokens ): array {
	$cache_key = jluxe_search_term_cache_key( $taxonomy );
	$terms     = get_transient( $cache_key );
	if ( ! is_array( $terms ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 200,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$terms = is_wp_error( $terms ) || ! is_array( $terms ) ? array() : $terms;
		set_transient( $cache_key, $terms, 2 * HOUR_IN_SECONDS );
	}
	$merged = array();
	foreach ( $terms as $candidate ) {
		if ( is_object( $candidate ) && isset( $candidate->term_id, $candidate->name ) ) {
			$merged[ (int) $candidate->term_id ] = $candidate;
		}
	}
	foreach ( array_slice( $query_tokens, 0, 4 ) as $token ) {
		if ( jluxe_strlen( $token ) < 2 ) {
			continue;
		}
		$known_matches = jluxe_search_rank_taxonomy_terms( array_values( $merged ), array( $token ) );
		if ( ! empty( $known_matches ) && $known_matches[0]['score'] >= 68 ) {
			continue;
		}
		$direct = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name__like' => $token,
				'hide_empty' => true,
				'number'     => 8,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $direct ) || ! is_array( $direct ) ) {
			continue;
		}
		foreach ( $direct as $candidate ) {
			if ( is_object( $candidate ) && isset( $candidate->term_id, $candidate->name ) ) {
				$merged[ (int) $candidate->term_id ] = $candidate;
			}
		}
	}
	return array_values( $merged );
}

/** رتبه‌بندیِ term بر اساسِ تطبیقِ دقیق، پیشوندی و typoیِ نزدیک. */
function jluxe_search_rank_taxonomy_terms( array $terms, array $query_tokens ): array {
	$ranked = array();
	foreach ( $terms as $candidate ) {
		if ( ! is_object( $candidate ) || empty( $candidate->name ) ) {
			continue;
		}
		$candidate_tokens = jluxe_search_tokens( (string) $candidate->name );
		if ( empty( $candidate_tokens ) ) {
			continue;
		}
		$score = 0;
		foreach ( $query_tokens as $query_token ) {
			$best = 0;
			foreach ( $candidate_tokens as $candidate_token ) {
				$best = max( $best, jluxe_search_token_match_score( $query_token, $candidate_token ) );
			}
			$score += $best;
		}
		if ( $score > 0 ) {
			$ranked[] = array(
				'term'  => $candidate,
				'score' => $score,
			);
		}
	}
	usort(
		$ranked,
		static function ( array $left, array $right ): int {
			if ( $left['score'] !== $right['score'] ) {
				return $right['score'] <=> $left['score'];
			}
			return (int) ( $right['term']->count ?? 0 ) <=> (int) ( $left['term']->count ?? 0 );
		}
	);
	return $ranked;
}

/** واژه‌هایِ یک نامِ واقعی را به دیکشنریِ محدودِ typo اضافه می‌کند. */
function jluxe_search_add_vocabulary_text( array &$dictionary, string $text, int $weight = 1 ): void {
	$weight = max( 1, min( 20, $weight ) );
	foreach ( jluxe_search_tokens( $text ) as $token ) {
		if ( jluxe_strlen( $token ) < 3 ) {
			continue;
		}
		$dictionary[ $token ] = min( 100000, ( $dictionary[ $token ] ?? 0 ) + $weight );
	}
}

/** دیکشنریِ واژه‌هایِ عنوانِ محصولاتِ پرفروش؛ فقط در مسیرِ typo و با cache ساخته می‌شود. */
function jluxe_search_popular_product_vocabulary(): array {
	$cache_key = 'jluxe_search_product_vocabulary_v1';
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$dictionary = array();
	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'visible',
				'limit'      => 160,
				'orderby'    => 'popularity',
				'order'      => 'DESC',
				'return'     => 'objects',
			)
		);
		foreach ( (array) $products as $product ) {
			if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
				jluxe_search_add_vocabulary_text( $dictionary, (string) $product->get_name(), 2 );
			}
		}
	}
	set_transient( $cache_key, $dictionary, 6 * HOUR_IN_SECONDS );
	return $dictionary;
}

function jluxe_search_build_vocabulary( array $taxonomy_terms, array $product_posts ): array {
	$dictionary = array();
	foreach ( $taxonomy_terms as $candidate ) {
		if ( is_object( $candidate ) && isset( $candidate->name ) ) {
			$weight = max( 1, min( 20, (int) ( $candidate->count ?? 1 ) ) );
			jluxe_search_add_vocabulary_text( $dictionary, (string) $candidate->name, $weight );
		}
	}
	foreach ( $product_posts as $post ) {
		$post_id = jluxe_search_post_id( $post );
		$product = $post_id ? wc_get_product( $post_id ) : false;
		if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
			jluxe_search_add_vocabulary_text( $dictionary, (string) $product->get_name(), 3 );
		}
	}
	foreach ( jluxe_search_popular_product_vocabulary() as $token => $weight ) {
		$dictionary[ $token ] = min( 100000, ( $dictionary[ $token ] ?? 0 ) + (int) $weight );
	}
	return $dictionary;
}

/** یک واژه را فقط وقتی بی‌ابهام باشد به نزدیک‌ترین واژهٔ واقعی اصلاح می‌کند. */
function jluxe_search_correct_phrase_tokens( array $phrase_tokens, array $dictionary ): array {
	$corrected = $phrase_tokens;
	foreach ( $phrase_tokens as $index => $token ) {
		$token_length = jluxe_strlen( $token );
		if ( $token_length < 3 || isset( $dictionary[ $token ] ) ) {
			continue;
		}
		$maximum = jluxe_search_max_edit_distance( $token );
		if ( 0 === $maximum ) {
			continue;
		}
		$best_distance   = $maximum + 1;
		$best_candidates = array();
		foreach ( $dictionary as $candidate => $weight ) {
			$candidate_length = jluxe_strlen( (string) $candidate );
			if ( abs( $token_length - $candidate_length ) > $maximum ) {
				continue;
			}
			// پیشوندِ ناتمام را املایِ غلط فرض نکن؛ این حالت در autocomplete طبیعی است.
			if (
				$token_length !== $candidate_length &&
				( 0 === strpos( (string) $candidate, $token ) || 0 === strpos( $token, (string) $candidate ) )
			) {
				continue;
			}
			$distance = jluxe_search_edit_distance( $token, (string) $candidate );
			if ( $distance > $maximum ) {
				continue;
			}
			if ( $distance < $best_distance ) {
				$best_distance   = $distance;
				$best_candidates = array();
			}
			if ( $distance === $best_distance ) {
				$best_candidates[ (string) $candidate ] = (int) $weight;
			}
		}
		if ( empty( $best_candidates ) ) {
			continue;
		}
		arsort( $best_candidates, SORT_NUMERIC );
		$ranked_candidates = array_keys( $best_candidates );
		$best_word         = $ranked_candidates[0];
		$best_weight       = (int) $best_candidates[ $best_word ];
		$second_weight     = isset( $ranked_candidates[1] ) ? (int) $best_candidates[ $ranked_candidates[1] ] : 0;
		if ( 1 === count( $ranked_candidates ) || $best_weight >= max( 4, 2 * $second_weight ) ) {
			$corrected[ $index ] = $best_word;
		}
	}
	return array_values( $corrected );
}

/** واژه‌هایِ نامِ برندِ شناسایی‌شده را از عبارتِ جستجویِ محصول جدا می‌کند. */
function jluxe_search_remove_brand_tokens( array $query_tokens, array $brand_matches ): array {
	$brand_words = array();
	foreach ( $brand_matches as $match ) {
		if ( isset( $match['term']->name ) ) {
			$brand_words = array_merge( $brand_words, jluxe_search_tokens( (string) $match['term']->name ) );
		}
	}
	$remaining = array();
	foreach ( $query_tokens as $query_token ) {
		$belongs_to_brand = false;
		foreach ( $brand_words as $brand_word ) {
			if ( jluxe_search_token_match_score( $query_token, $brand_word ) >= 50 ) {
				$belongs_to_brand = true;
				break;
			}
		}
		if ( ! $belongs_to_brand ) {
			$remaining[] = $query_token;
		}
	}
	return $remaining;
}

/** ارقامِ ASCII/Persian/Arabic برایِ تطبیق با عنوان‌هایی که رقمِ دیگری ذخیره کرده‌اند. */
function jluxe_search_digit_variants( string $term ): array {
	$variants = array( $term );
	if ( preg_match( '/[0-9]/', $term ) ) {
		$variants[] = strtr( $term, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		$variants[] = strtr( $term, array( '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩' ) );
	}
	return array_values( array_unique( $variants ) );
}

/** شکل‌هایِ رایجِ عربیِ حروف را فقط در fallbackِ بدونِ نتیجه امتحان می‌کند. */
function jluxe_search_letter_variants( string $term ): array {
	$families = array(
		'ی' => array( 'ي', 'ى' ),
		'ک' => array( 'ك', 'ڪ' ),
		'ا' => array( 'أ', 'إ', 'آ', 'ٱ' ),
		'ه' => array( 'ة', 'ۀ', 'ہ', 'ھ' ),
	);
	$words    = jluxe_search_phrase_tokens( $term );
	if ( empty( $words ) ) {
		return array();
	}
	$combinations = array( array() );
	foreach ( $words as $word ) {
		$word_variants = array( $word );
		foreach ( $families as $source => $alternatives ) {
			$current = $word_variants;
			foreach ( $current as $variant ) {
				if ( false === strpos( $variant, $source ) ) {
					continue;
				}
				foreach ( $alternatives as $alternative ) {
					$candidate = str_replace( $source, $alternative, $variant );
					if ( ! in_array( $candidate, $word_variants, true ) ) {
						$word_variants[] = $candidate;
					}
					if ( count( $word_variants ) >= 12 ) {
						break 2;
					}
				}
			}
		}
		$expanded = array();
		foreach ( $combinations as $combination ) {
			foreach ( $word_variants as $variant ) {
				$expanded[] = array_merge( $combination, array( $variant ) );
				if ( count( $expanded ) >= 24 ) {
					break 2;
				}
			}
		}
		$combinations = $expanded;
	}
	$variants = array();
	foreach ( $combinations as $combination ) {
		$variants[] = implode( ' ', $combination );
	}
	return array_values( array_diff( array_unique( $variants ), array( $term ) ) );
}

function jluxe_search_post_id( $post ): int {
	if ( is_object( $post ) && isset( $post->ID ) ) {
		return absint( $post->ID );
	}
	return is_numeric( $post ) ? absint( $post ) : 0;
}

/** یک WP_Query محدود، با filter اختیاری رویِ taxonomy برند. */
function jluxe_search_run_product_query( string $search_term, array $brand_ids, int $limit = 12 ): array {
	if ( '' === $search_term && empty( $brand_ids ) ) {
		return array();
	}
	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, min( 12, $limit ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( '' !== $search_term ) {
		$args['s'] = $search_term;
	}
	if ( ! empty( $brand_ids ) ) {
		$args['tax_query'] = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => array_values( array_unique( array_map( 'absint', $brand_ids ) ) ),
				'operator' => 'IN',
			),
		);
	}
	$query = new WP_Query( $args );
	return isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : array();
}

/** عبارتِ کامل و در صورتِ نیاز چند token مستقل را با سقفِ ثابت جستجو می‌کند. */
function jluxe_search_collect_product_posts( string $search_term, array $brand_ids ): array {
	$posts       = array();
	$full_posts  = array();
	$tokens      = jluxe_search_tokens( $search_term );
	$queries_run = 0;
	$max_queries = 10;
	if ( '' === $search_term && ! empty( $brand_ids ) ) {
		$queries_run = 1;
		$full_posts  = jluxe_search_run_product_query( '', $brand_ids, 12 );
		$posts       = $full_posts;
	} elseif ( '' !== $search_term ) {
		foreach ( jluxe_search_digit_variants( $search_term ) as $variant ) {
			if ( $queries_run >= $max_queries ) {
				break;
			}
			$queries_run++;
			foreach ( jluxe_search_run_product_query( $variant, $brand_ids, 12 ) as $post ) {
				$post_id = jluxe_search_post_id( $post );
				if ( $post_id ) {
					$full_posts[ $post_id ] = $post;
				}
			}
		}
		if ( empty( $full_posts ) ) {
			// اگر عنوانِ دیتابیس با نویسهٔ عربی ذخیره شده باشد، شکل‌هایِ متناظر را
			// فقط پس ازِ بی‌نتیجه‌ماندنِ query نرمال‌شده امتحان می‌کنیم.
			foreach ( jluxe_search_digit_variants( $search_term ) as $digit_variant ) {
				foreach ( array_slice( jluxe_search_letter_variants( $digit_variant ), 0, 8 ) as $variant ) {
					if ( $queries_run >= $max_queries ) {
						break 2;
					}
					$queries_run++;
					foreach ( jluxe_search_run_product_query( $variant, $brand_ids, 12 ) as $post ) {
						$post_id = jluxe_search_post_id( $post );
						if ( $post_id ) {
							$full_posts[ $post_id ] = $post;
						}
					}
					if ( ! empty( $full_posts ) ) {
						break 2;
					}
				}
			}
		}
		$posts = $full_posts;
	}
	$has_full_match = ! empty( $full_posts );
	if ( count( $posts ) < 6 && count( $tokens ) > 1 ) {
		foreach ( array_slice( $tokens, 0, 4 ) as $token ) {
			if ( jluxe_strlen( $token ) < 2 ) {
				continue;
			}
			foreach ( jluxe_search_digit_variants( $token ) as $variant ) {
				if ( $queries_run >= $max_queries ) {
					break 2;
				}
				$queries_run++;
				foreach ( jluxe_search_run_product_query( $variant, $brand_ids, 8 ) as $post ) {
					$post_id = jluxe_search_post_id( $post );
					if ( $post_id && ! isset( $posts[ $post_id ] ) ) {
						$posts[ $post_id ] = $post;
					}
				}
			}
			if ( count( $posts ) >= 18 ) {
				break;
			}
		}
	}
	return array(
		'posts'       => array_values( $posts ),
		'full_match'  => $has_full_match,
	);
}

/** بر پایهٔ termهایِ مرتبط و عنوان‌هایِ همان کاندیدها، typo را اصلاح می‌کند. */
function jluxe_search_try_corrected_products( string $term, array $brand_matches, array $brand_ids, array $taxonomy_terms, array $raw_posts ): array {
	$phrase_tokens = jluxe_search_phrase_tokens( $term );
	if ( empty( $phrase_tokens ) ) {
		return array();
	}
	$dictionary = jluxe_search_build_vocabulary( $taxonomy_terms, $raw_posts );
	$corrected  = jluxe_search_correct_phrase_tokens( $phrase_tokens, $dictionary );
	if ( $corrected === $phrase_tokens ) {
		return array();
	}
	$corrected_term   = jluxe_normalize_persian_query( implode( ' ', $corrected ) );
	$corrected_tokens = jluxe_search_tokens( $corrected_term );
	$product_tokens   = jluxe_search_remove_brand_tokens( $corrected_tokens, $brand_matches );
	$results          = jluxe_search_collect_product_posts( implode( ' ', $product_tokens ), $brand_ids );
	if ( empty( $results['posts'] ) ) {
		return array();
	}
	return array(
		'query'       => $corrected_term,
		'tokens'      => $corrected_tokens,
		'posts'       => $results['posts'],
		'full_match'  => $results['full_match'],
	);
}

function jluxe_search_product_score( string $product_name, array $query_tokens ): int {
	$name        = jluxe_normalize_persian_query( $product_name );
	$name_tokens = jluxe_search_tokens( $name );
	$phrase      = implode( ' ', $query_tokens );
	$score       = '' !== $phrase && false !== strpos( $name, $phrase ) ? 1000 : 0;
	foreach ( $query_tokens as $query_token ) {
		$best = 0;
		foreach ( $name_tokens as $name_token ) {
			$best = max( $best, jluxe_search_token_match_score( $query_token, $name_token ) );
		}
		$score += $best;
	}
	return $score;
}

/** محصولات را رتبه‌بندی می‌کند، سپس همان کاندیدها را برایِ facetها برمی‌گرداند. */
function jluxe_search_format_products( array $posts, array $query_tokens, int $limit = 6 ): array {
	$ranked = array();
	$seen   = array();
	$order  = 0;
	foreach ( $posts as $post ) {
		$post_id = jluxe_search_post_id( $post );
		if ( ! $post_id || isset( $seen[ $post_id ] ) ) {
			continue;
		}
		$seen[ $post_id ] = true;
		$product           = wc_get_product( $post_id );
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! method_exists( $product, 'get_name' ) ) {
			continue;
		}
		$ranked[] = array(
			'id'      => (int) $product->get_id(),
			'product' => $product,
			'score'   => jluxe_search_product_score( (string) $product->get_name(), $query_tokens ),
			'order'   => $order++,
		);
	}
	usort(
		$ranked,
		static function ( array $left, array $right ): int {
			if ( $left['score'] !== $right['score'] ) {
				return $right['score'] <=> $left['score'];
			}
			return $left['order'] <=> $right['order'];
		}
	);
	$facet_ids = array();
	foreach ( array_slice( $ranked, 0, 12 ) as $row ) {
		$facet_ids[] = $row['id'];
	}
	$items = array();
	foreach ( array_slice( $ranked, 0, $limit ) as $row ) {
		$product  = $row['product'];
		$image_id = method_exists( $product, 'get_image_id' ) ? (int) $product->get_image_id() : 0;
		$items[]  = array(
			'id'    => $row['id'],
			'name'  => (string) $product->get_name(),
			'price' => method_exists( $product, 'get_price_html' ) ? $product->get_price_html() : '',
			'image' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			'url'   => get_permalink( $row['id'] ),
		);
	}
	return array(
		'items'     => $items,
		'facet_ids' => $facet_ids,
	);
}

/** termهایِ واقعاً متصل به محصولاتِ یافته‌شده را می‌گیرد؛ والدهایِ دسته هم اضافه می‌شوند. */
function jluxe_search_related_terms( string $taxonomy, array $product_ids ): array {
	$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
	if ( empty( $product_ids ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'object_ids' => $product_ids,
			'hide_empty' => true,
			'number'     => 24,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}
	$related = array();
	foreach ( $terms as $term ) {
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			$related[ (int) $term->term_id ] = $term;
		}
	}
	if ( 'product_cat' !== $taxonomy || ! function_exists( 'get_term' ) ) {
		return array_values( $related );
	}
	foreach ( array_values( $related ) as $term ) {
		$parent_id = (int) ( $term->parent ?? 0 );
		$depth     = 0;
		while ( $parent_id > 0 && $depth < 8 && ! isset( $related[ $parent_id ] ) ) {
			$parent = get_term( $parent_id, $taxonomy );
			if ( is_wp_error( $parent ) || ! is_object( $parent ) || ! isset( $parent->term_id ) ) {
				break;
			}
			$related[ (int) $parent->term_id ] = $parent;
			$parent_id                        = (int) ( $parent->parent ?? 0 );
			$depth++;
		}
	}
	return array_values( $related );
}

/** خروجیِ public term؛ تطبیق‌هایِ مستقیم پیش از دسته/برندِ متصل به محصولات می‌آیند. */
function jluxe_search_format_taxonomy_results( string $taxonomy, array $direct_matches, array $product_ids, int $limit = 4 ): array {
	$ordered = array();
	foreach ( $direct_matches as $match ) {
		if ( isset( $match['term'] ) && is_object( $match['term'] ) ) {
			$ordered[] = $match['term'];
		}
	}
	foreach ( jluxe_search_related_terms( $taxonomy, $product_ids ) as $related ) {
		$ordered[] = $related;
	}
	$results = array();
	$seen    = array();
	foreach ( $ordered as $term ) {
		$term_id = (int) ( $term->term_id ?? 0 );
		if ( ! $term_id || isset( $seen[ $term_id ] ) || empty( $term->name ) ) {
			continue;
		}
		$seen[ $term_id ] = true;
		$link             = get_term_link( $term );
		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			continue;
		}
		$results[] = array(
			'name'  => (string) $term->name,
			'url'   => $link,
			'count' => (int) ( $term->count ?? 0 ),
		);
		if ( count( $results ) >= $limit ) {
			break;
		}
	}
	return $results;
}

/** سازگاری با فراخوانی‌هایِ قبلیِ helper جستجویِ term. */
function jluxe_search_taxonomy_terms( string $taxonomy, string $term ): array {
	$query_tokens = jluxe_search_tokens( $term );
	if ( empty( $query_tokens ) ) {
		return array();
	}
	$candidates = jluxe_search_taxonomy_candidates( $taxonomy, $query_tokens );
	$matches    = jluxe_search_rank_taxonomy_terms( $candidates, $query_tokens );
	return jluxe_search_format_taxonomy_results( $taxonomy, $matches, array(), 4 );
}

function jluxe_ajax_search(): void {
	check_ajax_referer( 'jluxe_search', 'nonce' );
	$raw_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$raw_term = jluxe_substr( trim( $raw_term ), 0, 100 );
	$term     = jluxe_substr( jluxe_normalize_persian_query( $raw_term ), 0, 100 );

	if ( jluxe_strlen( $term ) < 2 || empty( jluxe_search_tokens( $term ) ) ) {
		wp_send_json_success(
			array(
				'products'       => array(),
				'categories'     => array(),
				'brands'         => array(),
				'viewAllUrl'     => '',
				'query'          => $term,
				'correctedQuery' => '',
			)
		);
		return;
	}

	$query_tokens       = jluxe_search_tokens( $term );
	$category_candidates = jluxe_search_taxonomy_candidates( 'product_cat', $query_tokens );
	$category_matches    = jluxe_search_rank_taxonomy_terms( $category_candidates, $query_tokens );
	$brand_candidates    = taxonomy_exists( 'product_brand' ) ? jluxe_search_taxonomy_candidates( 'product_brand', $query_tokens ) : array();
	$brand_matches       = jluxe_search_rank_taxonomy_terms( $brand_candidates, $query_tokens );
	$brand_ids           = array();
	foreach ( $brand_matches as $match ) {
		$brand_id = (int) ( $match['term']->term_id ?? 0 );
		if ( $brand_id > 0 ) {
			$brand_ids[] = $brand_id;
		}
	}
	$product_tokens = jluxe_search_remove_brand_tokens( $query_tokens, $brand_matches );
	$raw_search     = jluxe_search_collect_product_posts( implode( ' ', $product_tokens ), $brand_ids );
	$selected_posts = $raw_search['posts'];
	$score_tokens   = $query_tokens;
	$corrected_query = '';

	// ابتدا نتایجِ عادی را حفظ می‌کنیم؛ typo correction فقط وقتی اجرا می‌شود
	// که عبارتِ کامل hit نشده باشد و یک اصلاحِ واقعی بتواند محصولِ مرتبط‌تری بیابد.
	if ( empty( $raw_search['full_match'] ) ) {
		$taxonomy_terms = array_merge( $category_candidates, $brand_candidates );
		$corrected      = jluxe_search_try_corrected_products( $term, $brand_matches, $brand_ids, $taxonomy_terms, $raw_search['posts'] );
		if (
			! empty( $corrected['posts'] ) &&
			( ! empty( $corrected['full_match'] ) || count( $corrected['posts'] ) > count( $raw_search['posts'] ) )
		) {
			$merged_posts = array();
			foreach ( array_merge( $corrected['posts'], $raw_search['posts'] ) as $post ) {
				$post_id = jluxe_search_post_id( $post );
				if ( $post_id ) {
					$merged_posts[ $post_id ] = $post;
				}
			}
			$selected_posts  = array_values( $merged_posts );
			$score_tokens    = $corrected['tokens'];
			$corrected_query = $corrected['query'];
		}
	}

	$product_results = jluxe_search_format_products( $selected_posts, $score_tokens, 6 );
	$categories      = jluxe_search_format_taxonomy_results( 'product_cat', $category_matches, $product_results['facet_ids'], 4 );
	$brands          = taxonomy_exists( 'product_brand' )
		? jluxe_search_format_taxonomy_results( 'product_brand', $brand_matches, $product_results['facet_ids'], 4 )
		: array();
	$view_all_query  = '' !== $corrected_query ? $corrected_query : $raw_term;

	wp_send_json_success(
		array(
			'products'       => $product_results['items'],
			'categories'     => $categories,
			'brands'         => $brands,
			'viewAllUrl'     => add_query_arg(
				array(
					's'        => $view_all_query,
					'post_type' => 'product',
				),
				home_url( '/' )
			),
			'query'          => $term,
			'correctedQuery' => $corrected_query,
		)
	);
}
add_action( 'wp_ajax_jluxe_search', 'jluxe_ajax_search' );
add_action( 'wp_ajax_nopriv_jluxe_search', 'jluxe_ajax_search' );

/** پس ازِ تغییرِ محصول یا taxonomy، vocabularyهایِ cacheشده را تازه می‌کند. */
function jluxe_search_clear_product_vocabulary_cache( int $post_id = 0 ): void {
	delete_transient( 'jluxe_search_product_vocabulary_v1' );
}
add_action( 'save_post_product', 'jluxe_search_clear_product_vocabulary_cache', 10, 1 );

function jluxe_search_clear_taxonomy_cache( int $term_id, int $term_taxonomy_id, string $taxonomy ): void {
	if ( in_array( $taxonomy, array( 'product_cat', 'product_brand' ), true ) ) {
		delete_transient( jluxe_search_term_cache_key( $taxonomy ) );
	}
}
add_action( 'created_term', 'jluxe_search_clear_taxonomy_cache', 10, 3 );
add_action( 'edited_term', 'jluxe_search_clear_taxonomy_cache', 10, 3 );
add_action( 'delete_term', 'jluxe_search_clear_taxonomy_cache', 10, 3 );
