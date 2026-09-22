<?php
/**
 * Generic WordPress Page template.
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) {
		the_post();
		?>
		<article <?php post_class(); ?>>
			<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
			<div class="page-content">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	}
	?>
</main>

<?php
get_footer();
