<?php
/**
 * کارت محصول در گرید (آرشیو فروشگاه + محصولات مرتبط + صفحه اصلی). طراحی
 * دقیقاً مطابق نمونه‌ی HTML/عکسِ ارسالیِ کاربر (باکس تصویر با پس‌زمینه‌ی
 * خاکستری روشن + object-contain، بج تخفیف/موجودی، ردیف امتیاز، دکمه‌ی
 * دایره‌ای افزودن سریع کنار قیمت) — با این تفاوت که گالریِ تامبنیلِ هاور
 * (نمایش عکس‌های دیگر محصول با هاور/لمس‌طولانی، قبلاً به‌درخواستِ صریحِ
 * کاربر ساخته شده) عمداً حفظ و روی همین طراحیِ جدید سوار شده — چیزی از
 * قبل حذف نشده.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product || ! $product->is_visible() ) {
	return;
}

$is_variable   = $product->is_type( 'variable' );
// موجودی محصول متغیر واقعاً سطحِ هر تنوعه، نه خودِ محصولِ والد — پس بج
// «ناموجود» فقط برای محصول ساده نشون داده می‌شه تا با «چند نوع موجود» تناقض نداشته باشه.
$out_of_stock  = ! $is_variable && ! $product->is_in_stock();
$regular_price = (float) $product->get_regular_price();
$sale_price    = (float) $product->get_sale_price();
$has_discount  = ! $is_variable && $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;
$discount_pct  = $has_discount ? (int) round( 100 - ( $sale_price / $regular_price ) * 100 ) : 0;
$rating        = (float) $product->get_average_rating();
$review_count  = (int) $product->get_review_count();
$image_id      = $product->get_image_id();
/*
 * برگردوندنِ سایزِ عکس به woocommerce_thumbnail — تلاشِ قبلی (سایزِ
 * 'medium'ِ بدونِ کراپ) باعثِ رگرسیونِ واقعی شد: خیلی از عکس‌های محصول با
 * object-contain داخلِ قابِ مربع هم دیگه کاملاً پر نمی‌شدن (شکافِ خالیِ
 * نامتقارن برایِ عکس‌های غیرمربعی)، و چون تمامِ سه نسبت (۱:۱/۳:۴/۴:۵) به
 * هم خیلی نزدیکن، این شکاف باعث می‌شد عوض‌کردنِ نسبت اصلاً محسوس نباشه.
 * راه‌حلِ درست این نبود که سایزِ عکس رو عوض کنیم؛ راه‌حل تغییرِ
 * object-fit بود (پایین‌تر، همین فایل): object-cover.
 */
$image_url     = $image_id ? ( wp_get_attachment_image_url( $image_id, 'jluxe-product-thumb-sm' ) ?: wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
$image_srcset  = $image_id ? wp_get_attachment_image_srcset( $image_id, 'woocommerce_thumbnail' ) : '';
/*
 * اخطارِ واقعیِ Lighthouse («This image file is larger than it needs to
 * be … use responsive images»): مقدارِ خودکارِ wp_get_attachment_image_sizes()
 * فرض می‌کنه اسلات تا ۳۰۰px (عرضِ اسمیِ خودِ سایزِ woocommerce_thumbnail)
 * می‌تونه بره، در حالی که در گریدِ واقعیِ کارتِ محصول عرضِ نمایشِ واقعی
 * حدودِ ۲۲۰-۲۴۰px هست — روی گوشیِ retina (dpr=2/3) همین اختلاف باعث می‌شه
 * مرورگر به‌جای نسخه‌ی ۳۰۰w، نسخه‌ی ۶۰۰w (خیلی بزرگ‌تر از لازم) رو انتخاب
 * کنه. مقدارِ دستیِ زیر با عرضِ واقعیِ کارت در گریدِ ۲/۳/۴ستونه هماهنگه.
 */
$image_sizes = $image_id ? '(max-width: 480px) 42vw, (max-width: 1024px) 24vw, 180px' : '';
/*
 * اخطارِ واقعیِ Lighthouse («Improve image delivery»): این تصویر نه srcset
 * داشت (یعنی موبایل هم همون فایلِ اندازه‌ی ثابتِ دسکتاپ رو دانلود می‌کرد)
 * نه eager/lazy درست تفکیک شده بود — هر عکسِ کارتِ محصول، حتی اولین/
 * دومینِ گرید (که تقریباً همیشه بالای صفحه‌ن و می‌تونن خودِ LCP باشن)،
 * لِیزی بود؛ لیزی‌کردنِ عکسِ LCP دقیقاً همون چیزیه که LCP رو بدتر می‌کنه.
 *
 * باگِ واقعیِ گزارش‌شده (رگرسیونِ جدی — LCP از ۳.۹s به ۱۹.۸s پرید): این‌جا
 * قبلاً `static $i = 0;` مستقیم توی همین فایل (نه توی یک تابع) بود. چون
 * این تمپلیت با wc_get_template_part() → include (نه include_once) برای
 * هر محصول جداگانه include می‌شه، static در سطحِ بالای یک فایل (نه داخلِ
 * تابع) بینِ include-های جدا پایدار نمی‌مونه — هر بار از نو صفر می‌شه و
 * بلافاصله ۱ می‌شه، یعنی «فقط کارتِ اول» در واقع «همه‌ی کارت‌ها» می‌شد:
 * ده‌ها عکسِ fetchpriority=high هم‌زمان، دقیقاً برعکسِ هدف. راه‌حل: شمارشگر
 * به یک تابعِ واقعی (jluxe_next_product_card_index در inc/woocommerce.php)
 * منتقل شد — static داخلِ تابع، برخلافِ static در فایل، بینِ فراخوانی‌های
 * مختلفِ همون تابع در طولِ یک درخواست واقعاً پایدار می‌مونه.
 */
$jluxe_card_render_count = function_exists( 'jluxe_next_product_card_index' ) ? jluxe_next_product_card_index() : 999;
$jluxe_card_is_eager     = $jluxe_card_render_count <= 4;

// JLuxe Theme → کارت محصول (نسبت تصویر/نمایش امتیاز و بج‌ها). قبلاً فقط
// دو حالت بود (مربع/عمودیِ ۴:۵) — طبقِ درخواستِ کاربر «فقط نباید مربع
// باشه»، یک حالتِ سومِ ۳:۴ («classic») هم اضافه شد.
$jluxe_ratio_map = array(
	'square'  => 'aspect-square',
	'classic' => 'aspect-[3/4]',
	'portrait' => 'aspect-[4/5]',
);
$jluxe_pc       = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['product_card'] : array();
$jluxe_ratio    = $jluxe_ratio_map[ $jluxe_pc['image_ratio'] ?? 'square' ] ?? $jluxe_ratio_map['square'];
$jluxe_show_rating = $jluxe_pc['show_rating'] ?? true;
$jluxe_show_sale   = $jluxe_pc['show_sale_badge'] ?? true;
$jluxe_show_stock  = $jluxe_pc['show_stock_badge'] ?? true;
$jluxe_price_style = ! empty( $jluxe_pc['price_color'] ) ? ' style="color:' . esc_attr( $jluxe_pc['price_color'] ) . '"' : '';
/*
 * نگاشتِ گردیِ گوشه‌ها (تنظیمِ «کارت محصول» → گردی گوشه‌ها) — طبقِ درخواستِ
 * صریحِ کاربر («دور عکسا گرد بشه مثل کارت») جعبه‌ی تصویر دیگه یک گردیِ
 * کوچیک‌ترِ جداگونه نداره؛ چون تصویر حالا (بدونِ padding/margin اضافه‌ی
 * بیرونی) درست لبه‌به‌لبه‌ی بالای کارت می‌شینه، فقط دو گوشه‌ی بالاییش
 * دقیقاً هم‌اندازه‌ی خودِ کارت گرد می‌شن (rounded-t-*).
 */
/*
 * کلیدِ جدید 'image_inner' — طبقِ درخواستِ کاربر («گوشه‌ی خودِ عکس هم
 * کمی گرد بشه، شبیهِ خودِ کادر») چون جعبه‌ی عکس ۱۶px پدینگ (p-4) داره،
 * خودِ <img> از لبه‌های گردِ جعبه فاصله داره و گوشه‌های کاملاً تیزش کنارِ
 * اون فاصله عجیب به نظر می‌رسید. یک گردیِ کوچیک‌تر (تقریباً نصفِ گردیِ
 * خودِ کارت) به خودِ عکس اضافه شده — نه هم‌اندازه‌ی کارت (که با اون فاصله‌ی
 * پدینگ منطقی نیست)، فقط هماهنگ‌تر از گوشه‌ی کاملاً تیز.
 */
$jluxe_radius_map = array(
	'sm' => array( 'card' => 'rounded-xl', 'image' => 'rounded-t-xl', 'image_inner' => 'rounded-lg' ),
	'md' => array( 'card' => 'rounded-2xl', 'image' => 'rounded-t-2xl', 'image_inner' => 'rounded-xl' ),
	'lg' => array( 'card' => 'rounded-[24px]', 'image' => 'rounded-t-[24px]', 'image_inner' => 'rounded-2xl' ),
);
$jluxe_radius   = $jluxe_radius_map[ $jluxe_pc['radius'] ?? 'lg' ] ?? $jluxe_radius_map['lg'];
// طبقِ درخواستِ صریحِ کاربر — گردیِ خودِ عکس (image_inner، مستقل از گردیِ
// کلیِ کارت) حالا یک سوییچِ جدا داره: 'sharp' یعنی گوشه‌های کاملاً تیز
// (بدونِ هیچ کلاسِ rounded-*)، خالی/'rounded' یعنی همون رفتارِ قبلی.
$jluxe_image_inner_class = ( 'sharp' === ( $jluxe_pc['image_corners'] ?? 'rounded' ) ) ? '' : $jluxe_radius['image_inner'];

?>
<li <?php wc_product_class( 'group relative flex flex-col ' . esc_attr( $jluxe_radius['card'] ) . ' border border-border bg-gradient-to-b from-surface to-muted/40 transition-all duration-300 hover:border-primary/30', $product ); ?>>
	<?php
	// گالری محصول (عکس‌های دیگه، غیر از تصویر شاخص) — با هاور (دسکتاپ) یا
	// لمس طولانی (موبایل) به‌صورت ردیف تامبنیل کوچیک پایین باکس تصویر
	// نشون داده می‌شه. فقط وقتی واقعاً محصول عکس اضافه داره رندر می‌شه.
	$jluxe_all_gallery_ids  = $product->get_gallery_image_ids();
	$jluxe_gallery_ids      = array_slice( $jluxe_all_gallery_ids, 0, 4 );
	$jluxe_gallery_overflow = max( 0, count( $jluxe_all_gallery_ids ) - 4 );
	?>
	<?php
	/*
	 * طبقِ درخواستِ صریحِ کاربر: دیگه bg-muted جدا نداره (پس‌زمینه‌ی گرادیانِ
	 * خودِ کارت — li.product بالاتر — از پشتِ تصویر دیده می‌شه، «کل بکگراند
	 * رو بکگراند کارت بگیره»)، بزرگ‌نماییِ هاور (group-hover:scale) حذف
	 * شده، و p-3ِ بیرونیِ قبلی هم حذف شده تا جعبه‌ی تصویر واقعاً لبه‌به‌لبه‌ی
	 * بالای کارت بشینه (باگِ گزارش‌شده‌ی «تصویر کمی بالا قرار داره» دقیقاً
	 * همین فاصله‌ی اضافه بود). به‌جای بزرگ‌نمایی، یک درخشِ اریبِ سریع/ملایمِ
	 * jluxe-card-shine (در globals.css، همون تکنیکِ لوگو ولی سریع‌تر و
	 * کم‌شدت‌تر و فقط با هاور) اضافه شده.
	 *
	 * باگِ واقعیِ بعدی («عکس باید بیاد پایین‌تر تا بزلِ چپ/راست/بالا برابر
	 * شه»): با اندازه‌گیریِ مستقیمِ زنده (getBoundingClientRect) روی سایتِ
	 * واقعی، فاصله‌ی بالا ۸px و پایین ۲۴px بود، در حالی که چپ/راست هرکدوم
	 * ۱۶px (خودِ p-4) — یعنی flex + items-center روی <a> این عکسِ size-full
	 * رو دقیقاً وسط‌چین نمی‌کرد (رفتارِ نامنظمِ محاسبه‌ی اندازه‌ی عنصرِ
	 * جایگزین/replaced-element با ارتفاعِ درصدی در فلکس).
	 *
	 * تلاشِ اولِ رفع (absolute inset-4 مستقیم روی خودِ <img>) هم کار نکرد —
	 * با زنده‌تست دوباره معلوم شد: مرورگرها برای عنصرِ جایگزین (replaced
	 * element مثلِ <img>) با position:absolute، حتی وقتی هر ۴ سمتِ inset
	 * ست شده باشه، بازم عرض/ارتفاع رو بر اساسِ اندازه‌ی ذاتی/طبیعی حساب
	 * می‌کنن، نه کِشش تا پرکردنِ فاصله‌ی بینِ inset ها (برخلافِ یک <div>
	 * معمولی که این کِشش براش تضمین‌شده‌ست) — نتیجه یک جابه‌جاییِ نامتقارنِ
	 * دیگه بود. راه‌حلِ درست: یک <div> ساده (نه replaced) بینِ <a> و <img>
	 * که خودش absolute inset-4 می‌گیره (این کِشش براش قابلِ‌اتکاست)، و
	 * <img> داخلش فقط size-full می‌گیره — الان ۱۶px از هر چهار طرف
	 * تضمین‌شده و یکسانه، روی هر نسبت‌تصویری، بدونِ وابستگی به رفتارِ
	 * فلکس یا الگوریتمِ اندازه‌گیریِ عنصرِ جایگزین.
	 */
	?>
	<div class="relative">
		<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="jluxe-card-shine relative flex <?php echo esc_attr( $jluxe_ratio ); ?> overflow-hidden <?php echo esc_attr( $jluxe_radius['image'] ); ?>">
			<div class="absolute inset-4">
				<img
					data-jluxe-card-main-img
					src="<?php echo esc_url( $image_url ); ?>"
					<?php if ( $image_srcset ) : ?>srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="<?php echo esc_attr( $image_sizes ); ?>"<?php endif; ?>
					alt="<?php echo esc_attr( $product->get_name() ); ?>"
					<?php echo jluxe_lazy_attr( $jluxe_card_is_eager ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php // fetchpriority=high فقط برای اولین کارت (تنها کاندیدِ واقعیِ LCP) — دادنش به چند عکس هم‌زمان خودِ سیگنال رو کم‌اثر می‌کنه. ?>
					<?php echo 1 === $jluxe_card_render_count ? ' fetchpriority="high"' : ''; ?>
					<?php
					/*
					 * باگِ واقعیِ گزارش‌شده («عوض‌کردنِ نسبت ۱:۱/۳:۴/۴:۵ اصلاً اثر
					 * نداره»): با object-contain، وقتی عکسِ منبع (woocommerce_thumbnail،
					 * همیشه مربعِ هارد-کراپ‌شده) با قابی غیرِمربعی فرق داره،
					 * فقط یک شکافِ خالیِ نامتقارن ایجاد می‌شد؛ چون هر سه نسبت
					 * به‌هم نزدیکن، این شکاف تقریباً یکسان به‌نظر می‌رسید —
					 * یعنی تغییرِ نسبت عملاً «دیده نمی‌شد». راه‌حل: object-cover
					 * (نه contain) — عکس همیشه کاملِ قاب رو پر می‌کنه (بدونِ
					 * شکافِ خالی) و با هر نسبتی، واقعاً یک برشِ متفاوتِ محسوس
					 * نشون می‌ده. الگویِ استانداردِ گریدِ محصولِ اکثرِ فروشگاه‌های
					 * آنلاینه.
					 */
					?>
					class="size-full object-cover mix-blend-multiply <?php echo esc_attr( $jluxe_image_inner_class ); ?><?php echo $out_of_stock ? ' opacity-60 grayscale' : ''; ?>"
				/>
			</div>

			<?php if ( $has_discount && $jluxe_show_sale ) : ?>
				<span class="absolute top-2 end-2 rounded-lg bg-primary px-2 py-0.5 text-[11px] font-bold text-primary-foreground"><?php echo esc_html( jluxe_fa_digits( $discount_pct ) ); ?>٪</span>
			<?php elseif ( $out_of_stock && $jluxe_show_stock ) : ?>
				<span class="absolute top-2 end-2 rounded-lg bg-muted-foreground/80 px-2 py-0.5 text-[11px] font-bold text-white">ناموجود</span>
			<?php else : ?>
				<span class="absolute top-2.5 start-2.5 size-2 rounded-full ring-2 ring-surface" style="background:<?php echo esc_attr( $jluxe_pc['in_stock_color'] ?? '#34d399' ); ?>" aria-hidden="true"></span>
			<?php endif; ?>

			<?php if ( ! empty( $jluxe_gallery_ids ) ) : ?>
				<div data-jluxe-card-thumbs class="pointer-events-none absolute inset-x-1.5 bottom-1.5 flex gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-hover:pointer-events-auto group-data-[thumbs-active]:opacity-100 group-data-[thumbs-active]:pointer-events-auto">
					<?php foreach ( $jluxe_gallery_ids as $jluxe_gid ) :
						$jluxe_thumb_url = wp_get_attachment_image_url( $jluxe_gid, 'woocommerce_gallery_thumbnail' );
						$jluxe_full_url  = wp_get_attachment_image_url( $jluxe_gid, 'woocommerce_thumbnail' );
						if ( ! $jluxe_thumb_url || ! $jluxe_full_url ) {
							continue;
						}
						?>
						<span data-jluxe-card-thumb data-full="<?php echo esc_url( $jluxe_full_url ); ?>" class="size-8 shrink-0 overflow-hidden rounded-md border-2 border-white/90 bg-white/90 shadow-sm">
							<img src="<?php echo esc_url( $jluxe_thumb_url ); ?>" alt="" class="size-full object-cover" loading="lazy" />
						</span>
					<?php endforeach; ?>
					<?php if ( $jluxe_gallery_overflow > 0 ) : ?>
						<span class="flex size-8 shrink-0 items-center justify-center rounded-md border-2 border-white/90 bg-black/60 text-caption font-medium text-white">+<?php echo esc_html( jluxe_fa_digits( $jluxe_gallery_overflow ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</a>
	</div>

	<div class="flex flex-1 flex-col px-3 pb-3 md:px-4 md:pb-4">
		<?php
		// این ردیف همیشه رندر می‌شه (حتی بدون امتیاز) تا ارتفاعش برای همه‌ی
		// کارت‌های یک ردیف گرید یکسان بمونه؛ وقتی امتیازی نیست با invisible
		// فقط از دید مخفی می‌شه ولی جای خودش رو نگه می‌داره.
		$jluxe_show_rating_row = $jluxe_show_rating && $review_count > 0;
		?>
		<div class="mb-1.5 flex min-h-[16px] items-center gap-1<?php echo $jluxe_show_rating_row ? '' : ' invisible'; ?>">
			<?php echo jluxe_boom_star_row( $rating, 'size-3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="text-[10px] text-text-muted"><?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?></span>
		</div>

		<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="mb-3 min-h-[40px] text-[13px] leading-5 line-clamp-2 break-words text-text-secondary hover:text-primary">
			<?php echo esc_html( $product->get_name() ); ?>
		</a>

		<div class="mt-auto flex items-end justify-between gap-2">
			<?php
			/*
			 * دکمه‌ی دایره‌ای افزودن سریع — مطابق نمونه‌ی ارسالی، همیشه دیده
			 * می‌شه (نه فقط روی هاور). برای محصولِ متغیر data-jluxe-quick-variant
			 * می‌ذاریم تا assets/js/woocommerce.js کلیک رو بگیره و مودالِ
			 * انتخاب سریعِ تنوع (inc/cart-ux.php: jluxe_ajax_variation_picker)
			 * رو باز کنه؛ برای محصول ساده مستقیم AJAX add-to-cart واقعیِ خودِ
			 * ووکامرس (کلاس‌های ajax_add_to_cart/add_to_cart_button).
			 */
			?>
			<a
				href="<?php echo esc_url( $out_of_stock || $is_variable ? get_permalink( $product->get_id() ) : $product->add_to_cart_url() ); ?>"
				aria-label="<?php echo esc_attr( $out_of_stock ? 'ناموجود' : ( $is_variable ? 'انتخاب گزینه‌ها' : 'افزودن به سبد خرید' ) ); ?>"
				data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
				<?php echo ( $is_variable && ! $out_of_stock ) ? 'data-jluxe-quick-variant="' . esc_attr( $product->get_id() ) . '"' : ''; ?>
				class="group/btn flex size-9 md:size-10 shrink-0 items-center justify-center rounded-2xl text-button font-medium transition-all duration-300 active:scale-90 <?php echo $out_of_stock ? 'bg-muted text-muted-foreground opacity-50' : ( $is_variable ? 'bg-foreground text-surface hover:bg-primary' : 'ajax_add_to_cart add_to_cart_button bg-foreground text-surface hover:bg-primary' ); ?>"
			>
				<svg class="size-4 transition-transform duration-300 group-hover/btn:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			</a>

			<div class="flex flex-col items-end gap-0">
				<span class="text-[11px] text-text-muted line-through<?php echo $has_discount ? '' : ' invisible'; ?>"><?php echo wp_kses_post( wc_price( $regular_price ) ); ?></span>
				<span class="text-[14px] md:text-[15px] font-semibold text-foreground"<?php echo $jluxe_price_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo wp_kses_post( wc_price( $has_discount ? $sale_price : ( $is_variable ? $product->get_variation_price( 'min' ) : $regular_price ) ) ); ?></span>
			</div>
		</div>
	</div>
</li>
