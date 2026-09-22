<?php
/**
 * قالبِ تک‌پستِ بلاگ.
 *
 * باگِ واقعیِ گزارش‌شده («بلاگ خیلی ساده نمایش داده می‌شه»): تا قبل از این،
 * هیچ single.php ای توی این تم نبود، پس وردپرس به‌صورتِ خودکار به
 * index.php برمی‌گشت — که فقط یک <h1> خام + the_content() ساده بود، بدون
 * هیچ کلاس/استایلی. این‌جا یک قالبِ کاملِ متناسب با بقیه‌ی سایت (همون
 * توکن‌های رنگ/تایپوگرافیِ jluxe که در صفحاتِ محصول/فوتر هم استفاده
 * می‌شن) نوشته شده.
 *
 * نکته‌ی فنی: چون main-DrI8xx-i.css یک باندلِ Tailwind از پیش کامپایل‌شده
 * و قفل‌شده‌ست (نه چیزی که این‌جا بتونیم rebuild کنیم)، فقط کلاس‌هایی که
 * قبلاً واقعاً جایی توی همون CSS استفاده شدن (و پس کامپایل شدن) این‌جا
 * به‌کار رفتن. برای چیزهایی که واقعاً جدیدن (تایپوگرافیِ محتوای پست،
 * کارت‌های پستِ مرتبط، تصویرِ هیرو) یک بلوکِ <style> مجزا نوشته شده —
 * دقیقاً همون الگویی که برای jluxe-feature-grid و بخشِ نمادهای سایت هم
 * جواب داد.
 */

get_header();

$jluxe_categories = get_the_category();
$jluxe_cat_name    = ! empty( $jluxe_categories ) ? $jluxe_categories[0]->name : '';
$jluxe_cat_link    = ! empty( $jluxe_categories ) ? get_category_link( $jluxe_categories[0]->term_id ) : '';

// خواندنِ تخمینی — تقریبِ ساده‌ی «تعدادِ کلمات ÷ ۱۵۰ کلمه در دقیقه»، فقط
// یک لمسِ حرفه‌ای‌تر، بدونِ وابستگی به هیچ افزونه‌ای.
$jluxe_word_count   = str_word_count( wp_strip_all_tags( get_the_content() ) );
$jluxe_reading_mins = max( 1, (int) ceil( $jluxe_word_count / 150 ) );
?>

<style>
.jluxe-hover-primary:hover{color:hsl(var(--primary))}
.jluxe-article-hero{aspect-ratio:16/9;background:hsl(var(--muted) / .4)}
@media (min-width:768px){.jluxe-article-hero{aspect-ratio:21/9}}
.jluxe-article-content{color:hsl(var(--foreground))}
.jluxe-article-content>*+*{margin-top:1.25em}
.jluxe-article-content p{line-height:1.9;font-size:.9375rem}
.jluxe-article-content h2{font-size:1.375rem;font-weight:700;margin-top:2em}
.jluxe-article-content h3{font-size:1.125rem;font-weight:700;margin-top:1.75em}
.jluxe-article-content img{border-radius:1rem;max-width:100%;height:auto;display:block;margin:1.5em auto}
.jluxe-article-content ul,.jluxe-article-content ol{padding-inline-start:1.5em;line-height:1.9}
.jluxe-article-content li{margin-top:.5em}
.jluxe-article-content a{color:hsl(var(--primary));text-decoration:underline;text-underline-offset:3px}
.jluxe-article-content table{width:100%;border-collapse:collapse;margin:1.5em 0}
.jluxe-article-content th,.jluxe-article-content td{padding:.75em 1em;border-bottom:1px solid hsl(var(--border));text-align:start}
.jluxe-article-content th{background:hsl(var(--muted) / .5);font-weight:700}
.jluxe-article-card{box-shadow:0 12px 34px -16px rgba(0,0,0,.16)}
/* درخواستِ کاربر: توی دسکتاپ متن‌ها وسط‌چین باشن؛ توی موبایل همون حالتِ خوانا/راست‌چینِ عادی بمونه */
@media (min-width:768px){
	.jluxe-article-header{text-align:center}
	.jluxe-article-header .jluxe-article-meta{justify-content:center}
	.jluxe-article-content{text-align:center}
	.jluxe-article-content ul,.jluxe-article-content ol{text-align:start;display:inline-block}
	.jluxe-article-content table{text-align:center}
	.jluxe-article-content th,.jluxe-article-content td{text-align:center}
}
.jluxe-related-card{transition:box-shadow .2s ease,transform .2s ease}
.jluxe-related-card:hover{box-shadow:0 10px 30px -12px rgba(0,0,0,.18);transform:translateY(-2px)}
.jluxe-related-thumb{aspect-ratio:4/3;background:hsl(var(--muted) / .4)}
</style>

<main id="primary" class="site-main">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-10">

		<?php
		/*
		 * ناوبریِ breadcrumb — خانه › دسته‌بندی › عنوانِ کوتاه‌شده. مستقیم
		 * با کلاس‌های موجود، بدونِ نیاز به هیچ افزونه‌ی breadcrumb.
		 */
		?>
		<nav class="mb-6 flex flex-wrap items-center gap-2 text-caption text-text-muted" aria-label="breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jluxe-hover-primary transition-colors"><?php bloginfo( 'name' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog' ) ); ?>" class="jluxe-hover-primary transition-colors">بلاگ</a>
			<?php if ( $jluxe_cat_name ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( $jluxe_cat_link ); ?>" class="jluxe-hover-primary transition-colors"><?php echo esc_html( $jluxe_cat_name ); ?></a>
			<?php endif; ?>
		</nav>

		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'jluxe-article-card rounded-2xl bg-surface/70 overflow-hidden' ); ?>>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="jluxe-article-hero w-full overflow-hidden">
						<?php
						the_post_thumbnail(
							'full',
							array(
								'class'   => 'size-full object-cover',
								'alt'     => get_the_title(),
								'loading' => 'eager',
								'fetchpriority' => 'high',
							)
						);
						?>
					</div>
				<?php endif; ?>

				<div class="p-5 sm:p-8">
					<div class="jluxe-article-header">
						<?php if ( $jluxe_cat_name ) : ?>
							<a href="<?php echo esc_url( $jluxe_cat_link ); ?>" class="inline-block rounded-xl bg-primary/10 px-3 py-1 text-caption font-bold text-primary"><?php echo esc_html( $jluxe_cat_name ); ?></a>
						<?php endif; ?>

						<h1 class="mt-4 text-3xl font-bold text-foreground"><?php the_title(); ?></h1>

						<div class="jluxe-article-meta mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-text-muted">
							<span class="flex items-center gap-1.5">
								<?php echo get_avatar( get_the_author_meta( 'ID' ), 20, '', '', array( 'class' => 'rounded-full' ) ); ?>
								<?php the_author(); ?>
							</span>
							<span><?php echo esc_html( get_the_date() ); ?></span>
							<span><?php echo (int) $jluxe_reading_mins; ?> دقیقه مطالعه</span>
						</div>
					</div>

					<div class="jluxe-article-content mt-8">
						<?php the_content(); ?>
					</div>
				</div>
			</article>

			<?php
			/*
			 * پست‌های مرتبط — از همون دسته‌بندیِ پستِ فعلی، حداکثر ۳ تا،
			 * بدونِ خودِ همین پست.
			 */
			$jluxe_related_ids = ! empty( $jluxe_categories ) ? wp_list_pluck( $jluxe_categories, 'term_id' ) : array();
			if ( ! empty( $jluxe_related_ids ) ) :
				$jluxe_related = new WP_Query(
					array(
						'category__in'   => $jluxe_related_ids,
						'post__not_in'   => array( get_the_ID() ),
						'posts_per_page' => 3,
						'ignore_sticky_posts' => true,
						'no_found_rows'  => true,
					)
				);
				if ( $jluxe_related->have_posts() ) :
					?>
					<div class="mt-10">
						<h2 class="mb-4 text-lg font-bold text-foreground">مطالب مرتبط</h2>
						<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-6">
							<?php
							while ( $jluxe_related->have_posts() ) :
								$jluxe_related->the_post();
								?>
								<a href="<?php the_permalink(); ?>" class="jluxe-related-card block overflow-hidden rounded-2xl border border-border bg-surface/70">
									<div class="jluxe-related-thumb w-full overflow-hidden">
										<?php if ( has_post_thumbnail() ) : ?>
											<?php the_post_thumbnail( 'medium', array( 'class' => 'size-full object-cover', 'alt' => get_the_title(), 'loading' => 'lazy' ) ); ?>
										<?php endif; ?>
									</div>
									<div class="p-3">
										<p class="line-clamp-2 text-caption font-bold text-foreground"><?php the_title(); ?></p>
										<p class="mt-1 text-caption text-text-muted"><?php echo esc_html( get_the_date() ); ?></p>
									</div>
								</a>
								<?php
							endwhile;
							wp_reset_postdata();
							?>
						</div>
					</div>
					<?php
				endif;
			endif;
			?>

		<?php endwhile; ?>
	</div>
</main>

<?php
get_footer();
