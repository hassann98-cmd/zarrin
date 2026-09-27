<?php
/** WordPress fallback for blog/search/archive views. */
get_header();
?>
<main id="primary" class="site-main mx-auto max-w-[1296px] px-4 py-8">
	<?php if ( ! is_singular() ) : ?>
		<h1 class="text-h1 mb-6"><?php
			if ( is_search() ) { echo esc_html( 'نتایج جستجو برای «' . get_search_query() . '»' ); }
			elseif ( is_archive() ) { echo wp_kses_post( get_the_archive_title() ); }
			else { esc_html_e( 'نوشته‌ها', 'jluxe' ); }
		?></h1>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'mb-8' ); ?> id="post-<?php the_ID(); ?>">
				<?php if ( is_singular() ) : ?>
					<?php the_title( '<h1 class="text-h1 mb-4">', '</h1>' ); ?>
					<?php the_content(); wp_link_pages(); ?>
				<?php else : ?>
					<h2 class="text-h2 mb-3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination( array( 'prev_text' => 'صفحهٔ قبل', 'next_text' => 'صفحهٔ بعد' ) ); ?>
	<?php else : ?>
		<section class="rounded-xl border border-border p-6" aria-labelledby="jluxe-no-results">
			<h1 id="jluxe-no-results" class="text-h2 mb-3">محتوایی پیدا نشد</h1>
			<p class="mb-4">عبارت دیگری را جستجو کنید یا به صفحهٔ اصلی برگردید.</p>
			<?php get_search_form(); ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحهٔ اصلی</a>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
