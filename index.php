<?php
/**
 * WordPress fallback for blog/search/archive views.
 * Blog and archive results use the same responsive, three-column card design
 * as the homepage blog section; singular fallbacks retain their simple output.
 */
get_header();
$jluxe_search_types = (array) get_query_var( 'post_type' );
$jluxe_is_blog_listing = is_home() || is_category() || is_tag() || is_date() || is_author() || ( is_search() && ! in_array( 'product', $jluxe_search_types, true ) );
?>
<main id="primary" class="site-main"<?php echo $jluxe_is_blog_listing ? ' data-jluxe-soft-nav="blog"' : ''; ?>>
	<?php if ( $jluxe_is_blog_listing ) : ?>
		<div class="jluxe-blog-listing" dir="rtl">
			<header class="jluxe-blog-listing__header">
				<p class="jluxe-blog-listing__eyebrow">مجلهٔ زرین</p>
				<h1 class="jluxe-blog-listing__title">
					<?php
					if ( is_search() ) {
						echo esc_html( 'نتایج جستجو برای «' . get_search_query() . '»' );
					} elseif ( is_archive() ) {
						echo wp_kses_post( get_the_archive_title() );
					} else {
						esc_html_e( 'نوشته‌های وبلاگ', 'jluxe' );
					}
					?>
				</h1>
				<?php if ( is_archive() && get_the_archive_description() ) : ?>
					<div class="jluxe-blog-listing__description"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
				<?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="jluxe-blog-grid" aria-label="مطالب وبلاگ">
					<?php while ( have_posts() ) : the_post(); ?>
						<article <?php post_class( 'jluxe-blog-card' ); ?> id="post-<?php the_ID(); ?>">
							<a class="jluxe-blog-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( 'مطالعهٔ مطلب: ' . get_the_title() ); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'medium_large', array( 'class' => 'jluxe-blog-card__image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
								<?php else : ?>
									<span class="jluxe-blog-card__placeholder">
										<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="9" y="7" width="30" height="34" rx="5" stroke="currentColor" stroke-width="2"/><path d="M16 17h16M16 24h16M16 31h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
									</span>
								<?php endif; ?>
							</a>

							<div class="jluxe-blog-card__body">
								<div class="jluxe-blog-card__meta">
									<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
									<?php $jluxe_post_categories = get_the_category(); ?>
									<?php if ( ! empty( $jluxe_post_categories ) ) : ?>
										<a class="jluxe-blog-card__category" href="<?php echo esc_url( get_category_link( $jluxe_post_categories[0]->term_id ) ); ?>"><?php echo esc_html( $jluxe_post_categories[0]->name ); ?></a>
									<?php endif; ?>
								</div>

								<h2 class="jluxe-blog-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<p class="jluxe-blog-card__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 28, '…' ) ); ?></p>
								<a class="jluxe-blog-card__read-more" href="<?php the_permalink(); ?>">
									<span>ادامهٔ مطلب</span>
									<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</a>
							</div>
						</article>
					<?php endwhile; ?>
				</div>

				<div class="jluxe-blog-pagination">
					<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => 'صفحهٔ قبل', 'next_text' => 'صفحهٔ بعد' ) ); ?>
				</div>
			<?php else : ?>
				<section class="jluxe-blog-empty" aria-labelledby="jluxe-no-results">
					<h2 id="jluxe-no-results">محتوایی پیدا نشد</h2>
					<p>عبارت دیگری را جستجو کنید یا به صفحهٔ اصلی برگردید.</p>
					<?php get_search_form(); ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
				</section>
			<?php endif; ?>
		</div>
	<?php elseif ( ! is_singular() ) : ?>
		<div class="mx-auto max-w-[1320px] px-3 py-8 md:px-4">
			<h1 class="text-h1 mb-6"><?php
				if ( is_search() ) { echo esc_html( 'نتایج جستجو برای «' . get_search_query() . '»' ); }
				elseif ( is_archive() ) { echo wp_kses_post( get_the_archive_title() ); }
				else { esc_html_e( 'نوشته‌ها', 'jluxe' ); }
			?></h1>
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<article <?php post_class( 'mb-8' ); ?> id="post-<?php the_ID(); ?>">
						<h2 class="text-h2 mb-3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php the_excerpt(); ?>
					</article>
				<?php endwhile; ?>
				<?php the_posts_pagination( array( 'prev_text' => 'صفحهٔ قبل', 'next_text' => 'صفحهٔ بعد' ) ); ?>
			<?php else : ?>
				<section class="rounded-xl border border-border p-6" aria-labelledby="jluxe-no-results">
					<h2 id="jluxe-no-results" class="text-h2 mb-3">محتوایی پیدا نشد</h2>
					<p class="mb-4">عبارت دیگری را جستجو کنید یا به صفحهٔ اصلی برگردید.</p>
					<?php get_search_form(); ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
				</section>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="mx-auto max-w-[1320px] px-3 py-8 md:px-4">
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<article <?php post_class( 'mb-8' ); ?> id="post-<?php the_ID(); ?>">
						<?php the_title( '<h1 class="text-h1 mb-4">', '</h1>' ); ?>
						<?php the_content(); wp_link_pages(); ?>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<section class="rounded-xl border border-border p-6" aria-labelledby="jluxe-no-results">
					<h1 id="jluxe-no-results" class="text-h2 mb-3">محتوایی پیدا نشد</h1>
					<p class="mb-4">عبارت دیگری را جستجو کنید یا به صفحهٔ اصلی برگردید.</p>
					<?php get_search_form(); ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
				</section>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
