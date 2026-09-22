<?php
/**
 * چیدمانِ جایگزین («کلاسیک») صفحه‌ی محصول — طبقِ درخواستِ کاربر و نمونه‌ی
 * HTML/CSS واقعی که خودش فرستاد. این فایل مستقیماً از content-single-product.php
 * (چیدمانِ پیش‌فرض) صدا زده می‌شه، فقط وقتی product_page.layout روی
 * 'classic' تنظیم شده باشه — همون global $product و همون هوک‌های واقعیِ
 * ووکامرس (فرم/سبد/تنوع/موجودی) این‌جا هم استفاده می‌شن، فقط چیدمانِ
 * بصری فرق می‌کنه.
 *
 * نکته‌ی فنی (طبقِ تجربه‌ی مستندشده‌ی خودِ پروژه توی product-image.php):
 * کلاس‌های Tailwindِ تازه فقط با build دوباره کامپایل می‌شن، پس این‌جا یا
 * از کلاس‌هایی که قبلاً جای دیگه‌ای واقعاً استفاده شدن (و پس کامپایل شدن)
 * استفاده شده، یا برای بخش‌های کاملاً جدید (ذره‌بین، تامبنیل، جعبه‌ی
 * کناری، دکمه‌های شناور) یک بلوکِ <style> مجزا نوشته شده.
 */

defined( 'ABSPATH' ) || exit;

global $product;

$rating       = (float) $product->get_average_rating();
$review_count = (int) $product->get_review_count();
$categories   = wc_get_product_category_list( $product->get_id() );

$attributes = array();
foreach ( array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' ) as $attribute ) {
	if ( $attribute->get_variation() ) {
		continue;
	}
	if ( $attribute->is_taxonomy() ) {
		$values = wp_list_pluck( wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) ), 'name' );
	} else {
		$values = $attribute->get_options();
	}
	$attributes[] = array(
		'name'     => sanitize_title( $attribute->get_name() ),
		'label'    => wc_attribute_label( $attribute->get_name() ),
		'value'    => implode( '، ', $values ),
		'is_brand' => false !== mb_strpos( wc_attribute_label( $attribute->get_name() ), 'برند' ),
	);
}
usort( $attributes, fn( $a, $b ) => (int) $b['is_brand'] - (int) $a['is_brand'] );
$key_attributes = array_slice( $attributes, 0, 3 );

$jluxe_is_variable          = $product->is_type( 'variable' );
$jluxe_variation_attributes = array();
$jluxe_available_variations = array();
if ( $jluxe_is_variable ) {
	$jluxe_variation_attributes = $product->get_variation_attributes();
	$jluxe_available_variations = $product->get_available_variations();
	wp_enqueue_script( 'wc-add-to-cart-variation' );
}

$jluxe_gallery_ids = $product->get_gallery_image_ids();
$jluxe_main_id     = $product->get_image_id();
if ( $jluxe_main_id ) {
	array_unshift( $jluxe_gallery_ids, $jluxe_main_id );
}
$jluxe_gallery_ids = array_values( array_unique( array_filter( $jluxe_gallery_ids ) ) );
if ( empty( $jluxe_gallery_ids ) ) {
	$jluxe_gallery_ids = array( 0 );
}
$jluxe_trust_badges = jluxe_get_product_trust_badges( $product->get_id() );
?>
<style>
.jluxe-cpv2-page{background:#F7F8FA}
.jluxe-cpv2-card{background:#fff;border-radius:18px;box-shadow:0 3px 5px rgba(0,0,0,.06);padding:20px}
.jluxe-cpv2-grid{display:flex;flex-direction:column;gap:24px}
@media (min-width:1024px){.jluxe-cpv2-grid{flex-direction:row}}
.jluxe-cpv2-gallery{flex:none}
@media (min-width:1024px){.jluxe-cpv2-gallery{width:22rem;padding-inline-end:2rem;border-inline-end:1px dashed hsl(var(--border))}}
.jluxe-cpv2-zoom{position:relative;width:100%;aspect-ratio:1/1;border-radius:24px;background:#F6F7FB;overflow:hidden;cursor:crosshair}
.jluxe-cpv2-zoom img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .25s ease-out;transform-origin:50% 50%}
.jluxe-cpv2-thumbs{display:flex;gap:8px;margin-top:12px;overflow-x:auto}
.jluxe-cpv2-thumb{flex:none;width:5rem;height:5rem;border-radius:14px;overflow:hidden;background:hsl(var(--muted));cursor:pointer;border:2px solid transparent;transition:border-color .15s ease}
.jluxe-cpv2-thumb img{width:100%;height:100%;object-fit:cover}
.jluxe-cpv2-thumb.is-active{border-color:hsl(var(--primary))}
.jluxe-cpv2-info{flex:1;min-width:0}
.jluxe-cpv2-feature{background:hsl(var(--muted)/.5);border-radius:12px;padding:10px 12px}
.jluxe-cpv2-feature span{display:block}
.jluxe-cpv2-sidebar{flex:none}
@media (min-width:1024px){.jluxe-cpv2-sidebar{width:16rem}}
.jluxe-cpv2-sidebar-box{background:hsl(var(--muted)/.5);border-radius:16px;padding:14px;font-size:.75rem}
.jluxe-cpv2-sidebar-row{display:flex;align-items:center;gap:8px;padding:10px 0}
.jluxe-cpv2-sidebar-row+.jluxe-cpv2-sidebar-row{border-top:1px dashed hsl(var(--border))}
.jluxe-cpv2-urgency{display:flex;align-items:center;gap:8px;background:hsl(var(--primary)/.08);border:1px solid hsl(var(--primary)/.25);color:hsl(var(--primary));padding:8px 12px;border-radius:8px;font-size:.8rem;margin-bottom:10px}
.jluxe-cpv2-fab-wrap{display:flex;gap:10px;margin-top:16px;justify-content:flex-end}
.jluxe-cpv2-fab{display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:10px;background:hsl(var(--muted));color:hsl(var(--primary));transition:background .15s ease}
.jluxe-cpv2-fab:hover{background:hsl(var(--muted-foreground)/.15)}
.jluxe-cpv2-fab[aria-pressed="true"]{color:#ef4056}
.jluxe-product-description>*+*{margin-top:.75rem}
.jluxe-cpv2-specs-row{border-top:1px solid hsl(var(--border))}
.jluxe-hover-primary:hover{color:hsl(var(--primary))}
</style>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'jluxe-cpv2-page', $product ); ?>>
	<div class="mx-auto w-full max-w-[1296px] px-4 py-4">

		<nav aria-label="مسیر صفحه" class="flex flex-wrap items-center gap-2 text-caption text-text-muted">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="jluxe-hover-primary">خانه</a>
			<?php if ( $categories ) : ?>
				<span>/</span>
				<span><?php echo wp_kses_post( $categories ); ?></span>
			<?php endif; ?>
			<span>/</span>
			<span class="text-text-secondary"><?php the_title(); ?></span>
		</nav>

		<div class="jluxe-cpv2-card mt-3">
			<?php if ( $jluxe_is_variable ) : ?>
				<?php
				$jluxe_variations_json = wc_esc_json( wp_json_encode( $jluxe_available_variations ) );
				?>
				<form class="variations_form cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $jluxe_variations_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<?php endif; ?>

			<div class="jluxe-cpv2-grid">
				<div class="jluxe-cpv2-gallery" data-jluxe-cpv2-gallery>
					<div class="jluxe-cpv2-zoom" data-jluxe-zoom-box>
						<?php $jluxe_main_url = wp_get_attachment_image_url( $jluxe_gallery_ids[0], 'full' ); ?>
						<img src="<?php echo esc_url( $jluxe_main_url ?: wc_placeholder_img_src( 'full' ) ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" data-jluxe-zoom-img loading="eager" fetchpriority="high" />
					</div>
					<?php if ( count( $jluxe_gallery_ids ) > 1 ) : ?>
						<div class="jluxe-cpv2-thumbs">
							<?php foreach ( $jluxe_gallery_ids as $i => $att_id ) : ?>
								<?php $thumb_url = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' ); ?>
								<button type="button" class="jluxe-cpv2-thumb<?php echo 0 === $i ? ' is-active' : ''; ?>" data-jluxe-zoom-thumb data-full="<?php echo esc_url( wp_get_attachment_image_url( $att_id, 'full' ) ?: '' ); ?>" aria-label="نمایش عکس <?php echo esc_attr( (string) ( $i + 1 ) ); ?>">
									<img src="<?php echo esc_url( $thumb_url ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ) ); ?>" alt="" loading="lazy" />
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="jluxe-cpv2-info">
					<?php if ( $categories ) : ?>
						<span class="text-caption text-text-muted"><?php echo wp_kses_post( wp_strip_all_tags( $categories ) ); ?></span>
					<?php endif; ?>

					<h1 class="mt-1.5 text-xl font-bold text-foreground"><?php the_title(); ?></h1>

					<div class="mt-2 flex flex-wrap items-center gap-4">
						<?php if ( $review_count > 0 ) : ?>
							<a href="#reviews" class="flex items-center gap-1.5">
								<?php echo jluxe_boom_star_row( $rating, 'size-[15px]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="text-caption font-bold text-foreground"><?php echo esc_html( jluxe_fa_digits( number_format_i18n( $rating, 1 ) ) ); ?></span>
								<span class="text-caption text-text-muted">(<?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?> دیدگاه)</span>
							</a>
						<?php else : ?>
							<span class="text-caption text-text-muted">هنوز دیدگاهی ثبت نشده — اولین نفر باشید!</span>
						<?php endif; ?>
					</div>

					<?php if ( $jluxe_trust_badges ) : ?>
						<div class="mt-3 flex flex-wrap items-center gap-2">
							<?php foreach ( $jluxe_trust_badges as $jluxe_badge ) : ?>
								<span class="inline-flex w-fit items-center gap-1 rounded-full <?php echo esc_attr( $jluxe_badge['bg_class'] ); ?> px-2 py-1 text-caption font-bold <?php echo esc_attr( $jluxe_badge['text_class'] ); ?>">
									<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg>
									<?php echo esc_html( $jluxe_badge['label'] ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $jluxe_is_variable ) : ?>
						<div class="mt-4">
							<?php if ( empty( $jluxe_available_variations ) && false !== $jluxe_available_variations ) : ?>
								<p class="rounded-xl bg-muted px-3 py-2.5 text-center text-small font-medium text-text-secondary">این محصول در حال حاضر ناموجود است</p>
							<?php else : ?>
								<?php jluxe_render_variation_swatches( $product, $jluxe_variation_attributes ); ?>
								<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
								<?php do_action( 'woocommerce_after_variations_table' ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $key_attributes ) : ?>
						<div class="mt-5">
							<span class="mb-3 block text-caption font-bold text-foreground">ویژگی‌ها :</span>
							<div class="flex flex-wrap items-start gap-2">
								<?php foreach ( $key_attributes as $attribute ) : ?>
									<div class="jluxe-cpv2-feature">
										<span class="text-[10px] text-text-muted"><?php echo esc_html( $attribute['label'] ); ?> :</span>
										<span class="text-caption font-bold text-foreground"><?php echo esc_html( $attribute['value'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="jluxe-cpv2-sidebar">
					<div class="jluxe-cpv2-sidebar-box">
						<div class="text-caption font-bold text-foreground">مشخصات</div>
						<div class="jluxe-cpv2-sidebar-row">
							<svg class="size-5 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
							<span>زمانِ ارسال: <strong class="text-primary">ارسال سریع و مطمئن</strong></span>
						</div>
						<div class="jluxe-cpv2-sidebar-row">
							<?php if ( $product->is_in_stock() ) : ?>
								<svg class="size-5 shrink-0 text-boom-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
								<span>وضعیتِ موجودی: <strong class="text-boom-success">موجود</strong></span>
							<?php else : ?>
								<svg class="size-5 shrink-0 jluxe-text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
								<span>وضعیتِ موجودی: <strong class="jluxe-text-danger">ناموجود</strong></span>
							<?php endif; ?>
						</div>
						<?php $jluxe_stock_line = jluxe_render_product_stock_line( $product ); ?>
						<?php if ( $jluxe_stock_line ) : ?>
							<div class="jluxe-cpv2-urgency"><?php echo $jluxe_stock_line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>
					</div>

					<?php if ( $product->get_sku() ) : ?>
						<div class="mt-3 text-caption text-text-muted">کد محصول: <?php echo esc_html( $product->get_sku() ); ?></div>
					<?php endif; ?>

					<div class="mt-3 border-t border-border pt-3">
						<?php wc_get_template_part( 'single-product/price' ); ?>
					</div>

					<div class="mt-3">
						<?php if ( $jluxe_is_variable ) : ?>
							<?php if ( ! empty( $jluxe_available_variations ) || false === $jluxe_available_variations ) : ?>
								<div class="single_variation_wrap">
									<?php
									do_action( 'woocommerce_before_single_variation' );
									do_action( 'woocommerce_single_variation' );
									do_action( 'woocommerce_after_single_variation' );
									?>
								</div>
							<?php endif; ?>
						<?php else : ?>
							<?php woocommerce_template_single_add_to_cart(); ?>
						<?php endif; ?>
					</div>

					<div class="jluxe-cpv2-fab-wrap">
						<button type="button" class="jluxe-cpv2-fab" data-jluxe-wishlist-toggle="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19.46 3.99c-2.68-1.64-5.02-.98-6.43.08c-.57.43-.86.65-1.03.65s-.46-.22-1.03-.65c-1.9-1.44-4.25-2.1-6.93-.46C1.02 6.15.22 13.27 8.34 19.28c1.55 1.14 2.32 1.72 3.66 1.72s2.11-.58 3.66-1.72c8.12-6.01 7.32-13.13 3.8-15.29"/></svg>
						</button>
						<button type="button" class="jluxe-cpv2-fab" data-jluxe-share aria-label="اشتراک‌گذاری">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.002 3c-2.947.032-4.591.22-5.684 1.312C3 5.63 3 7.752 3 11.997s0 6.366 1.318 7.685S7.76 21 12.003 21s6.366 0 7.685-1.319c1.093-1.092 1.28-2.737 1.312-5.685"/><path d="M14 3h4c1.414 0 2.121 0 2.56.44C21 3.878 21 4.585 21 6v4m-1-6l-9 9"/></svg>
						</button>
					</div>
				</div>
			</div>

			<?php if ( $jluxe_is_variable ) : ?>
				</form>
			<?php endif; ?>
		</div>

		<?php
		$jluxe_faq_items = function_exists( 'jluxe_get_product_faq_items' ) ? jluxe_get_product_faq_items( $product->get_id() ) : array();
		$jluxe_short_desc = $product->get_short_description();
		?>

		<?php if ( $jluxe_short_desc || get_the_content() ) : ?>
			<section id="description" class="mt-6 rounded-2xl border border-border bg-surface p-4 sm:p-5">
				<h2 class="mb-3 text-base font-bold text-foreground">معرفی محصول</h2>
				<div class="jluxe-product-description w-full text-justify text-caption leading-7 text-text-secondary">
					<?php the_content(); ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $jluxe_faq_items && function_exists( 'jluxe_render_product_faq_section' ) ) : ?>
			<?php jluxe_render_product_faq_section( $product ); ?>
		<?php endif; ?>

		<?php if ( $attributes ) : ?>
			<section id="specs" class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface">
				<h2 class="border-b border-border p-4 text-base font-bold text-foreground sm:p-5">مشخصات فنی</h2>
				<dl class="jluxe-cpv2-specs">
					<?php foreach ( $attributes as $i => $attribute ) : ?>
						<div class="flex gap-3 px-4 py-3 sm:gap-6<?php echo $i > 0 ? ' jluxe-cpv2-specs-row' : ''; ?>">
							<dt class="w-28 shrink-0 text-caption text-text-muted sm:w-40"><?php echo esc_html( $attribute['label'] ); ?></dt>
							<dd class="min-w-0 flex-1 text-caption leading-6 text-foreground"><?php echo esc_html( $attribute['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>

		<section id="reviews" class="mt-6 rounded-2xl border border-border bg-surface p-4 sm:p-5">
			<h2 class="mb-4 text-base font-bold text-foreground">دیدگاه کاربران</h2>
			<div class="jluxe-reviews"><?php comments_template(); ?></div>
		</section>

		<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
	</div>
</div>

<script>
(function(){
	'use strict';
	/*
	 * افکتِ ذره‌بین — mousemove روی قابِ عکس، transform-origin رو دنبالِ
	 * موس می‌بره و scale رو زیاد می‌کنه؛ موقعِ خروجِ موس برمی‌گرده به
	 * حالتِ عادی. فقط transform (بدونِ تغییرِ layout)، پس هزینه‌ی
	 * پردازشی‌اش ناچیزه.
	 */
	document.querySelectorAll('[data-jluxe-zoom-box]').forEach(function(box){
		var img = box.querySelector('[data-jluxe-zoom-img]');
		if (!img) return;
		box.addEventListener('mousemove', function(e){
			var rect = box.getBoundingClientRect();
			var x = ((e.clientX - rect.left) / rect.width) * 100;
			var y = ((e.clientY - rect.top) / rect.height) * 100;
			img.style.transformOrigin = x + '% ' + y + '%';
			img.style.transform = 'scale(1.9)';
		});
		box.addEventListener('mouseleave', function(){
			img.style.transform = 'scale(1)';
			img.style.transformOrigin = '50% 50%';
		});
	});

	document.querySelectorAll('[data-jluxe-zoom-thumb]').forEach(function(thumb){
		thumb.addEventListener('click', function(){
			var wrap = thumb.closest('[data-jluxe-cpv2-gallery]');
			if (!wrap) return;
			var mainImg = wrap.querySelector('[data-jluxe-zoom-img]');
			var full = thumb.getAttribute('data-full');
			if (mainImg && full) {
				mainImg.src = full;
			}
			wrap.querySelectorAll('[data-jluxe-zoom-thumb]').forEach(function(t){ t.classList.remove('is-active'); });
			thumb.classList.add('is-active');
		});
	});
})();
</script>
