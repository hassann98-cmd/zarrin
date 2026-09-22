<?php
/**
 * صفحه‌ی محصول — override کامل. چیدمان سه‌ستونه (گالری ۲۶rem / اطلاعات ۱fr /
 * جعبه‌ی خرید ۱۹rem چسبان) دقیقاً با اندازه‌گیری واقعی از d.acchi.ir (Boom،
 * پوسته‌ی قبلی که این پوسته جایگزینش شده) گرفته شده. طبق تصمیم صریح و
 * تکرارشده‌ی کاربر، رنگ‌ها هم دقیقاً از همون مرجع کپی می‌شن (نه توکن‌های
 * JLuxe) — شامل پس‌زمینه‌ی کل صفحه (#F7F8FA، اندازه‌گیری‌شده‌ی واقعی از
 * body مرجع) و ستاره/تخفیف/موفقیت (--boom-* در globals.css). توضیحات/
 * مشخصات/دیدگاه‌ها به‌جای تب‌باکس پیش‌فرض ووکامرس، بخش‌های جدا با ناوبری
 * چسبان بالای صفحه‌ان (مطابق طراحی مرجع).
 *
 * هوک‌های woocommerce_before_single_product_summary /
 * woocommerce_single_product_summary دیگه استفاده نمی‌شن چون طراحی سه‌ستونه
 * نیاز داره قیمت/دکمه‌ی خرید (که پیش‌فرض کنار عنوان میان) رو در ستون سوم
 * جدا از عنوان/امتیاز (ستون دوم) قرار بده؛ به‌جاش template partها مستقیم
 * صدا زده می‌شن. هوک‌های after_single_product_summary (پرسش‌وپاسخ واقعی در
 * inc/qa.php + محصولات مرتبط) دست‌نخورده باقی موندن.
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form();
	return;
}

/*
 * طبقِ درخواستِ کاربر: چیدمانِ جایگزین («کلاسیک») قابل‌انتخاب از تنظیماته.
 * اگه انتخاب شده باشه، همین‌جا کنترل به فایلِ جدا داده می‌شه و این فایل
 * (چیدمانِ پیش‌فرضِ سه‌ستونه) اصلاً ادامه پیدا نمی‌کنه — یعنی صفر ریسک
 * برای کسایی که چیدمانِ پیش‌فرض رو نگه می‌دارن.
 */
if ( 'classic' === ( jluxe_get_theme_settings()['product_page']['layout'] ?? 'default' ) ) {
	require JLUXE_THEME_DIR . '/woocommerce/content-single-product-classic.php';
	return;
}

$rating       = (float) $product->get_average_rating();
$review_count = (int) $product->get_review_count();
$categories = wc_get_product_category_list( $product->get_id() );

/**
 * wc_display_product_attributes() هسته‌ی ووکامرس مقدار برمی‌گردونه نه
 * echo می‌کنه — چون جدول خودمون رو با استایل جدا می‌خوایم، همون منطق
 * تبدیل مقدار (taxonomy → نام ترم، سفارشی → get_options) رو این‌جا
 * بازسازی می‌کنیم تا آرایه‌ی label/value واقعی داشته باشیم.
 *
 * ویژگی‌هایی که برای تولید تنوع (سواچ رنگ/سایز بالای جعبه‌ی خرید) استفاده
 * می‌شن (is_variation()) عمداً از این لیست حذف می‌شن — طبق گزارشِ واقعیِ
 * کاربر، قبلاً همون «رنگ» که بالای سواچ‌ها انتخاب می‌شد این‌جا هم دوباره
 * (با مقدارِ کاملِ همه‌ی گزینه‌ها، نه فقط انتخاب‌شده) تکرار می‌شد. ویژگیِ
 * «برند» (اگه محصول داشته باشه) اول لیست میاد.
 */
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
		'name'    => sanitize_title( $attribute->get_name() ),
		'label'   => wc_attribute_label( $attribute->get_name() ),
		'value'   => implode( '، ', $values ),
		'is_brand' => false !== mb_strpos( wc_attribute_label( $attribute->get_name() ), 'برند' ),
	);
}
usort(
	$attributes,
	function ( $a, $b ) {
		return (int) $b['is_brand'] - (int) $a['is_brand'];
	}
);
$key_attributes = array_slice( $attributes, 0, 3 );

/*
 * محصول متغیر: طبق طراحی مرجع (تصویر ضمیمه‌ی کاربر)، سواچ‌های رنگ/سایز باید
 * توی ستون وسط (کنار عنوان/امتیاز) باشن، نه توی جعبه‌ی چسبانِ خرید سمت چپ —
 * ولی qty/دکمه‌ی خرید همچنان باید توی همون جعبه بمونه. چون
 * assets/js/frontend/add-to-cart-variation.js خودِ ووکامرس با
 * $('form.variations_form').find('.variations select') کار می‌کنه، سواچ‌ها
 * و دکمه باید توی یک <form> واحد بمونن، وگرنه تطبیق قیمت/موجودی می‌شکنه.
 * راه‌حل: خودِ <form> با display:contents باز می‌شه (یعنی در چیدمانِ گرید
 * شرکت نمی‌کنه) درست قبل از ستون ۲، و بعد از ستون ۳ بسته می‌شه — نتیجه:
 * دو <div> ستون ۲ و ۳ فرزندهای مستقیمِ گرید می‌مونن (دقیقاً همون‌جا که
 * بدون فرم هم می‌بودن)، ولی هر دو واقعاً توی یک <form> واحدن.
 */
$jluxe_is_variable = $product->is_type( 'variable' );
$jluxe_variation_attributes = array();
$jluxe_available_variations = array();
if ( $jluxe_is_variable ) {
	$jluxe_variation_attributes = $product->get_variation_attributes();
	$jluxe_available_variations = $product->get_available_variations();
	// چون دیگه woocommerce_template_single_add_to_cart() (که خودش تویِ
	// woocommerce_variable_add_to_cart() این اسکریپت رو enqueue می‌کرد)
	// صدا زده نمی‌شه، باید دستی enqueue بشه — وگرنه با انتخاب سواچ هیچ
	// اتفاقی نمی‌افته (قیمت/موجودیِ تنوع match نمی‌شه، دکمه‌ی خرید غیرفعال
	// می‌مونه) چون خودِ اسکریپتِ match‌کننده اصلاً لود نشده.
	wp_enqueue_script( 'wc-add-to-cart-variation' );
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?> style="background:#F7F8FA">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-6">

		<nav aria-label="مسیر صفحه" class="flex flex-wrap items-center gap-2 text-caption text-text-muted">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-foreground">خانه</a>
			<?php if ( $categories ) : ?>
				<span>/</span>
				<span><?php echo wp_kses_post( $categories ); ?></span>
			<?php endif; ?>
			<span>/</span>
			<span class="text-text-secondary"><?php the_title(); ?></span>
		</nav>

		<?php
		/*
		 * باگِ واقعیِ گزارش‌شده («گالری غیب شده») — دو مرحله داشت:
		 *
		 * ۱) اول: خودِ <form> اشتباه قبل از کلِ گرید (نه قبل از ستونِ ۲) باز
		 * می‌شد، پس گالری هم داخلش می‌افتاد. یک بار جابه‌جا شد به «قبل از
		 * ستونِ ۲»، ولی با زنده‌تستِ دقیق‌تر (روی دو مرورگرِ کاملاً جدا)
		 * معلوم شد مشکلِ اصلی‌تر خودِ display:contents بود، نه فقط جای فرم:
		 * وقتی <form style="display:contents"> فقط ۲ تا از ۳ ستونِ گرید رو
		 * دربر می‌گرفت، خودِ Grid Layout در ترکیب با display:contents
		 * جای‌گذاریِ ستون‌ها رو قاطی می‌کرد (عرضِ ستونِ ۳ به ستونِ ۱ می‌خورد و
		 * برعکس) — یک باگِ واقعیِ مرورگر، نه فقط رنگ‌آمیزی.
		 *
		 * ۲) راه‌حلِ نهایی (تست‌شده و درست): اصلاً از display:contents
		 * استفاده نشه. چون همه‌ی دکمه‌های داخلِ گالری (ویشلیست/اشتراک/قبلی/
		 * بعدی) از قبل type="button" دارن (نه submit)، هیچ خطری نداره که
		 * گالری هم داخلِ خودِ <form> باشه — پس فرم دورِ کلِ گرید (هر ۳ ستون)
		 * باز/بسته می‌شه، یک <form> معمولی (بدونِ display:contents)، و خودِ
		 * grid که یک پله پایین‌ترِ فرمه بدونِ هیچ مشکلی سه‌ستونه رندر می‌شه —
		 * چون فرم فقط یک باکسِ block معمولیه، هیچ تداخلی با چیدمانِ داخلیِ
		 * گریدِ فرزندش نداره.
		 */
		if ( $jluxe_is_variable ) {
			do_action( 'woocommerce_before_add_to_cart_form' );
			$jluxe_variations_json = wc_esc_json( wp_json_encode( $jluxe_available_variations ) );
			?>
			<form class="variations_form cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $jluxe_variations_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<?php do_action( 'woocommerce_before_variations_form' ); ?>
			<?php
		}
		?>

		<div class="mt-4 grid gap-5 lg:grid-cols-[26rem_1fr_19rem]">
			<?php wc_get_template_part( 'single-product/product-image' ); ?>

			<div>
				<?php if ( $categories ) : ?>
					<span class="text-[12px] font-medium text-text-muted"><?php echo wp_kses_post( wp_strip_all_tags( $categories ) ); ?></span>
				<?php endif; ?>

				<h1 class="mt-1.5 text-lg font-bold leading-7 text-foreground sm:text-[20px] sm:leading-8" data-jluxe-product-title><?php the_title(); ?></h1>

				<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border pb-4">
					<?php if ( $review_count > 0 ) : ?>
						<a href="#reviews" class="flex items-center gap-1.5 rounded-lg px-1 py-0.5 hover:bg-muted">
							<?php echo jluxe_boom_star_row( $rating, 'size-[15px]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="text-[13px] font-bold text-foreground"><?php echo esc_html( jluxe_fa_digits( number_format_i18n( $rating, 1 ) ) ); ?></span>
							<span class="text-[12px] text-text-muted">(<?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?> دیدگاه)</span>
						</a>
						<span class="h-3.5 w-px bg-border" aria-hidden="true"></span>
					<?php endif; ?>
					<a href="#qa" class="flex items-center gap-1.5 text-[12px] text-text-secondary hover:text-primary">
						<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.5-2 1.8-2 3.3M12 17h.01"/></svg>
						پرسش و پاسخ
					</a>
				</div>

				<?php
				/*
				 * قبلاً «گارانتی اصالت کالا» برای همه‌ی محصولات ثابت نمایش
				 * داده می‌شد (باگِ واقعیِ گزارش‌شده). حالا هر دو بج از
				 * inc/woocommerce.php (jluxe_get_product_trust_badges،
				 * تیک‌های جداگانه توی ویرایشِ محصولِ ووکامرس) خونده می‌شن —
				 * فقط برای محصولاتی که واقعاً تیک خورده باشن نشون داده می‌شن.
				 */
				$jluxe_trust_badges = jluxe_get_product_trust_badges( $product->get_id() );
				if ( $jluxe_trust_badges ) :
					?>
					<div class="mt-4 flex flex-wrap items-center gap-2">
						<?php foreach ( $jluxe_trust_badges as $jluxe_badge ) : ?>
							<span class="inline-flex w-fit items-center gap-1 rounded-full <?php echo esc_attr( $jluxe_badge['bg_class'] ); ?> px-2 py-1 text-[11px] font-bold <?php echo esc_attr( $jluxe_badge['text_class'] ); ?>">
								<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg>
								<?php echo esc_html( $jluxe_badge['label'] ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $jluxe_is_variable ) : ?>
					<?php if ( empty( $jluxe_available_variations ) && false !== $jluxe_available_variations ) : ?>
						<p class="mt-4 rounded-xl bg-muted px-3 py-2.5 text-center text-small font-medium text-text-secondary">این محصول در حال حاضر ناموجود است</p>
					<?php else : ?>
						<?php jluxe_render_variation_swatches( $product, $jluxe_variation_attributes ); ?>
						<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
						<?php do_action( 'woocommerce_after_variations_table' ); ?>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $key_attributes ) : ?>
					<div class="mt-5">
						<h2 class="mb-2 text-[13px] font-bold text-foreground">ویژگی‌های کلیدی</h2>
						<div class="grid gap-1.5 sm:grid-cols-2">
							<?php foreach ( $key_attributes as $attribute ) : ?>
								<div class="flex items-start gap-2 rounded-lg bg-muted px-3 py-2" data-jluxe-keyspec-attr="<?php echo esc_attr( $attribute['name'] ); ?>">
									<span class="mt-1.5 size-1 shrink-0 rounded-full bg-primary"></span>
									<p class="min-w-0 text-[12.5px] leading-5">
										<span class="text-text-muted"><?php echo esc_html( $attribute['label'] ); ?>:</span>
										<span class="font-medium text-foreground" data-jluxe-keyspec-value data-jluxe-keyspec-default="<?php echo esc_attr( $attribute['value'] ); ?>"><?php echo esc_html( $attribute['value'] ); ?></span>
									</p>
								</div>
							<?php endforeach; ?>
						</div>
						<a href="#specs" class="mt-2.5 inline-flex items-center gap-1 text-[12.5px] font-semibold text-primary hover:text-primary-hover">
							مشاهده مشخصات کامل
							<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"></path></svg>
						</a>
					</div>
				<?php endif; ?>

				<?php
				$jluxe_short_desc = $product->get_short_description();
				if ( $jluxe_short_desc ) :
					?>
					<div class="mt-4" data-jluxe-short-desc>
						<div class="jluxe-clamp-3 text-[13px] leading-6 text-text-secondary" data-jluxe-short-desc-text><?php echo wp_kses_post( wpautop( $jluxe_short_desc ) ); ?></div>
						<button type="button" class="mt-1.5 hidden text-[12.5px] font-semibold text-primary hover:text-primary-hover" data-jluxe-short-desc-toggle>بیشتر</button>
					</div>
				<?php endif; ?>

				<?php do_action( 'woocommerce_share', $product ); ?>
			</div>

			<div class="lg:sticky lg:top-24 lg:self-start">
				<div class="rounded-2xl border border-border bg-muted/40 p-3.5">
					<?php // تک‌فروشنده‌ست (نه مارکت‌پلیس) — کارت فروشگاه/فروشنده عمداً حذف شده. ?>
					<?php wc_get_template_part( 'single-product/price' ); ?>
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

					<?php
					/*
					 * قبلاً این ۳ ردیف کاملاً ثابت بودن (باگِ واقعیِ
					 * گزارش‌شده: باید قابل‌ویرایش/فعال‌سازی باشن). عنوان/
					 * توضیح/فعال‌بودنِ هرکدوم حالا از تنظیماتِ تم (JLuxe
					 * Theme → محصول → صفحه محصول) خونده می‌شه؛ فقط آیکونِ
					 * هر ردیف (بر اساسِ موقعیت، نه از تنظیمات) ثابت می‌مونه.
					 */
					$jluxe_trust_item_icons = array(
						'<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m-9 0 1 14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-14"/>',
						'<path d="M3 16.5V6a1 1 0 0 1 1-1h9v11.5M16 8h3.2a1 1 0 0 1 .93.63L21.5 12v4.5H16"/><circle cx="7.5" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/>',
						'<path d="M12 3 5 5.8v5c0 4.2 2.9 8.1 7 9.2 4.1-1.1 7-5 7-9.2v-5Z"/><path d="m9 11.8 2 2 4-4"/>',
					);
					$jluxe_trust_items      = jluxe_get_setting( 'product_page.trust_items', array() );
					$jluxe_trust_items      = array_filter( $jluxe_trust_items, fn( $item ) => ! empty( $item['enabled'] ) );
					?>
					<?php if ( $jluxe_trust_items ) : ?>
						<ul class="mt-3.5 flex flex-col gap-2.5 border-t border-border pt-3">
							<?php foreach ( $jluxe_trust_items as $jluxe_ti_index => $jluxe_ti ) : ?>
								<li class="flex items-start gap-2.5">
									<svg class="mt-0.5 size-4 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $jluxe_trust_item_icons[ $jluxe_ti_index ] ?? $jluxe_trust_item_icons[0]; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG paths defined above, not user input. ?></svg>
									<div class="min-w-0">
										<p class="text-[12px] font-medium text-foreground"><?php echo esc_html( $jluxe_ti['title'] ?? '' ); ?></p>
										<p class="text-[11px] text-text-muted"><?php echo esc_html( $jluxe_ti['subtitle'] ?? '' ); ?></p>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( $jluxe_is_variable ) : ?>
				<?php do_action( 'woocommerce_after_variations_form' ); ?>
			</form>
			<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
		<?php endif; ?>

		<?php $jluxe_faq_items = function_exists( 'jluxe_get_product_faq_items' ) ? jluxe_get_product_faq_items( $product->get_id() ) : array(); ?>

		<nav aria-label="بخش‌های محصول" class="sticky top-0 z-20 mt-8 -mx-4 border-b border-border bg-background/95 px-4 backdrop-blur-md">
			<ul class="flex gap-1 overflow-x-auto">
				<li><a href="#description" class="flex h-12 items-center whitespace-nowrap px-3.5 text-[13px] font-medium text-text-secondary hover:text-foreground">معرفی</a></li>
				<?php if ( $jluxe_faq_items ) : ?>
					<li><a href="#faq" class="flex h-12 items-center whitespace-nowrap px-3.5 text-[13px] font-medium text-text-secondary hover:text-foreground">سوالات متداول</a></li>
				<?php endif; ?>
				<?php if ( $attributes ) : ?>
					<li><a href="#specs" class="flex h-12 items-center whitespace-nowrap px-3.5 text-[13px] font-medium text-text-secondary hover:text-foreground">مشخصات</a></li>
				<?php endif; ?>
				<li><a href="#reviews" class="flex h-12 items-center whitespace-nowrap px-3.5 text-[13px] font-medium text-text-secondary hover:text-foreground">دیدگاه کاربران</a></li>
				<li><a href="#qa" class="flex h-12 items-center whitespace-nowrap px-3.5 text-[13px] font-medium text-text-secondary hover:text-foreground">پرسش و پاسخ</a></li>
			</ul>
		</nav>

		<section id="description" class="scroll-mt-16 py-6">
			<h2 class="mb-4 flex items-center gap-2 text-base font-bold text-foreground sm:text-lg">
				<span class="h-4 w-[3px] rounded-full bg-primary" aria-hidden="true"></span>
				معرفی محصول
			</h2>
			<div class="rounded-2xl border border-border bg-surface p-4 sm:p-5">
				<div class="jluxe-product-description w-full space-y-3 text-justify text-[13.5px] leading-7 text-text-secondary">
					<?php the_content(); ?>
				</div>
			</div>
		</section>

		<?php if ( $jluxe_faq_items && function_exists( 'jluxe_render_product_faq_section' ) ) : ?>
			<?php jluxe_render_product_faq_section( $product ); ?>
		<?php endif; ?>

		<?php if ( $attributes ) : ?>
			<section id="specs" class="scroll-mt-16 py-6">
				<h2 class="mb-4 flex items-center gap-2 text-base font-bold text-foreground sm:text-lg">
					<span class="h-4 w-[3px] rounded-full bg-primary" aria-hidden="true"></span>
					مشخصات فنی
				</h2>
				<div class="overflow-hidden rounded-2xl border border-border bg-surface">
					<dl class="divide-y divide-border">
						<?php foreach ( $attributes as $attribute ) : ?>
							<div class="flex gap-3 px-4 py-3 sm:gap-6">
								<dt class="w-28 shrink-0 text-[12.5px] text-text-muted sm:w-40"><?php echo esc_html( $attribute['label'] ); ?></dt>
								<dd class="min-w-0 flex-1 text-[12.5px] leading-6 text-foreground"><?php echo esc_html( $attribute['value'] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			</section>
		<?php endif; ?>

		<section id="reviews" class="scroll-mt-16 py-6">
			<h2 class="mb-4 flex items-center gap-2 text-base font-bold text-foreground sm:text-lg">
				<span class="h-4 w-[3px] rounded-full bg-primary" aria-hidden="true"></span>
				دیدگاه کاربران
			</h2>

			<?php if ( $review_count > 0 && wc_review_ratings_enabled() ) : ?>
				<?php $rating_counts = $product->get_rating_counts(); ?>
				<div class="mb-4 flex items-center gap-4 rounded-2xl border border-border bg-surface p-4">
					<div class="flex flex-col items-center">
						<span class="text-3xl font-bold leading-none text-foreground"><?php echo esc_html( jluxe_fa_digits( number_format_i18n( $rating, 1 ) ) ); ?></span>
						<span class="mt-1.5">
							<?php echo jluxe_boom_star_row( $rating, 'size-3.5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span class="mt-1 text-[11px] text-text-muted">از <?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?> دیدگاه</span>
					</div>
					<div class="min-w-0 flex-1 space-y-1.5">
						<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
							<?php
							$count = isset( $rating_counts[ $star ] ) ? (int) $rating_counts[ $star ] : 0;
							$pct   = $review_count > 0 ? round( ( $count / $review_count ) * 100 ) : 0;
							?>
							<div class="flex items-center gap-2 text-[11px] text-text-muted">
								<span class="w-3 shrink-0"><?php echo esc_html( jluxe_fa_digits( $star ) ); ?></span>
								<span class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
									<span class="block h-full rounded-full bg-boom-star" style="width: <?php echo esc_attr( (string) $pct ); ?>%"></span>
								</span>
								<span class="w-8 shrink-0 text-start"><?php echo esc_html( jluxe_fa_digits( $pct ) ); ?>٪</span>
							</div>
						<?php endfor; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="jluxe-reviews rounded-2xl border border-border bg-surface p-4 sm:p-5">
				<?php comments_template(); ?>
			</div>
		</section>

		<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
	</div>
</div>

<?php if ( $product->is_purchasable() ) : ?>
	<?php
	/*
	 * نوارِ چسبانِ موبایلِ قیمت+افزودن‌به‌سبد — الگوی پوسته‌ی Boom (کاربر
	 * خودِ HTML مرجعِ Boom رو پیست کرد): یک کارتِ شناور (نه نوارِ چسبیده به
	 * لبه) با margin دورش، و یک دکمه‌ی نیم‌دایره‌ای («دکمه‌ی کناری») که از
	 * گوشه‌اش بیرون می‌زنه. توی صفحه‌ی محصول، به‌جایِ نوارِ ۵-آیکونیِ
	 * استانداردِ MobileNav.tsx، پیش‌فرض همین کارت نشون داده می‌شه (چون
	 * اکشنِ اصلیِ این صفحه خریده، نه ناوبری). دکمه‌ی «افزودن» دکمه‌ی
	 * واقعیِ form.cart .single_add_to_cart_button رو کلیک می‌کنه (نه
	 * submit جدا) تا کل منطقِ AJAX/toast موجود بدونِ کپی دوباره کار کنه.
	 *
	 * دکمه‌ی toggle عمداً بیرونِ priceBar (نه فرزندِ مستقیمش) و همیشه
	 * قابل‌مشاهده‌ست — چون priceBar موقعِ نمایشِ نوارِ ۵-آیکونی مخفی
	 * می‌شه، اگه toggle داخلش بود، دیگه راهی برای برگشتن نمی‌موند.
	 */
	?>
	<button
		type="button"
		data-jluxe-mobile-bar-toggle
		aria-label="جابه‌جایی بین قیمت و منو"
		class="fixed bottom-16 end-3 z-50 flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface text-text-secondary shadow-md transition-transform active:scale-95 md:hidden"
		style="margin-bottom: env(safe-area-inset-bottom);"
	>
		<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h12M4 12h16M4 19h8"/></svg>
	</button>
	<div class="fixed inset-x-0 bottom-0 z-40 hidden md:hidden" data-jluxe-mobile-price-bar>
		<div class="m-2" style="margin-bottom: calc(0.5rem + env(safe-area-inset-bottom));">
			<div class="jluxe-mobile-price-card flex items-center gap-2.5 rounded-2xl border border-border bg-surface/95 p-2 px-3 shadow-2xl backdrop-blur-md">
				<div class="min-w-0 flex-1">
					<span class="block text-[10px] text-text-muted">قیمت</span>
					<span class="text-[15px] font-black text-foreground" data-jluxe-mobile-bar-price>
						<?php echo wp_kses_post( $product->get_price_html() ); ?>
					</span>
				</div>
				<button
					type="button"
					data-jluxe-mobile-bar-add
					class="flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 text-[13px] font-bold text-primary-foreground shadow-[0_6px_16px_-6px_hsl(var(--primary))] transition-transform active:scale-95"
				>
					<svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
					افزودن به سبد
				</button>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php if ( $product->is_purchasable() && function_exists( 'jluxe_render_suggested_products_modal' ) ) : ?>
	<?php jluxe_render_suggested_products_modal( $product ); ?>
<?php endif; ?>

<?php do_action( 'woocommerce_after_single_product' ); ?>
