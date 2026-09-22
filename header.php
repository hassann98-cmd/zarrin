<?php
/**
 * Site header.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
/*
 * باگِ واقعیِ گزارش‌شده («قرار بود اسکلت داشته باشیم، الان فقط یک حلقه‌ی
 * چرخان داریم»): این لودرِ تمام‌صفحه (حلقه‌ی چرخان روی یک پردهٔ کاملاً
 * تیره‌کنندهٔ صفحه، z-index:9999) دقیقاً همون چیزی بود که جلوی دیده‌شدنِ
 * اسکلتِ واقعی رو می‌گرفت: assets/js/img-skeleton.js از قبل یک
 * شیمر/اسکلتِ per-image واقعی و سراسری (رو تک‌تکِ عکس‌های کل سایت) داره،
 * ولی چون این پردهٔ تمام‌صفحه تا لحظهٔ window.load (یعنی دقیقاً همون
 * لحظه‌ای که همهٔ عکس‌ها هم لود شدن) کل صفحه رو می‌پوشوند، کاربر هیچ‌وقت
 * فرصتِ دیدنِ اون شیمرهای تدریجی رو نداشت — فقط یک حلقهٔ چرخانِ یکنواخت
 * می‌دید و بعد یهو همه‌چیز کامل ظاهر می‌شد. با حذفِ همین پرده، محتوای
 * سرور-رندرشده بلافاصله دیده می‌شه و همون سیستمِ اسکلتِ per-image
 * (globals.css: img:not(.jluxe-img-loaded)) به‌جای این حلقه، به‌صورتِ
 * طبیعی برای هر عکس تا لودشدنش نمایان می‌مونه.
 */
?>

<!--
	شیشه‌ای (glassmorphism) — نسخه‌ی استاندارد (بلور + شفافیت + خط ظریف روشن)، چون به HTML
	واقعی هدر d.acchi.ir دسترسی نداشتم (دسترسی کروم به این دامنه مسدود بود) تا دقیق کپی کنم.
-->
<header id="masthead" class="site-header">
	<!--
		ترتیب DOM عمدیه: اکشن‌ها (سرچ/حساب/سبد) اول، لوگو دوم. توی dir="rtl" با flex
		معمولی، اولین فرزند سمت راست می‌شینه و آخرین سمت چپ — همون آرایشی که خواستی
		(لوگو چپ، اکشن‌ها راست)، بدون نیاز به row-reverse یا بازی با CSS logical properties.
	-->
	<!--
		فقط ردیف اول (اکشن‌ها + لوگو) چسبانه؛ ردیف منو/دسته‌بندی عمداً چسبان نیست و با اسکرول
		از بالا خارج می‌شه — طبق درخواست صریح کاربر. هر دو ردیف هم‌عرض و در وسط صفحه‌ان
		(mx-auto max-w-[1296px])، با کمی فاصله از بالا برای ردیف اول (pt-8 ≈ ۳۲px، طبق algetshop.ir).

		fixed به‌جای sticky عمدیه: چون ردیف اول و دوم داخل یک <header> کوتاه بودن، sticky فقط
		تا ارتفاع خود هدر (~۱۵۴px) چسبیده می‌موند و بعدش کلاً از بالا خارج می‌شد — محدودیت ذاتی
		CSS sticky (containing block هدره، نه کل صفحه). با fixed + یک spacer هم‌ارتفاع، ردیف اول
		واقعاً برای کل طول اسکرول صفحه بالا می‌مونه.
	-->
	<?php
	// header.sticky (JLuxe Theme → هدر) — وقتی خاموشه، ردیف اول یک بلوک عادی
	// (static) می‌مونه، بدون fixed/spacer. پیش‌فرض روشنه (رفتار فعلی، بدون تغییر).
	$jluxe_header_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['header'] : array( 'sticky' => true );
	$jluxe_header_sticky   = ! empty( $jluxe_header_settings['sticky'] );
	?>
	<?php
	/*
	 * top-0 ساده وقتی نوار ادمین وردپرس نشون داده می‌شه (کاربر لاگین) اشتباهه:
	 * نوار ادمین با z-index بالاتر روی ۳۲px بالای هدر fixed می‌شینه (نه هلش می‌ده)،
	 * در حالی که spacer (چون در جریان عادی سنده) با margin-top:32px که خودِ وردپرس
	 * روی <html> می‌ذاره هل می‌خوره — نتیجه‌ش یک فاصله‌ی خالی ۳۲پیکسلی بین ردیف اول
	 * و ردیف منو می‌شه. راه‌حل استاندارد وردپرس (از نسخه‌ی ۶.۴) دقیقاً همین متغیره:
	 * --wp-admin--admin-bar--height، که خودش با ریسپانسیو نوار ادمین (۳۲px دسکتاپ/
	 * ۴۶px موبایل) هماهنگه؛ وقتی نوار ادمین نیست، مقدار پیش‌فرض ۰px استفاده می‌شه.
	 */
	?>
	<?php
	/*
	 * ارتفاعِ ردیفِ موبایل از h-16 (۶۴px) به ۷۲px رسید — طبقِ درخواستِ دوباره‌ی
	 * کاربر برای بزرگ‌ترشدنِ لوگوی هدر (globals.css: [data-jluxe-island=
	 * "header-logo"] img). با ۶۴px ردیفِ قبلی، لوگو (که خودش ۵۶px بود) فقط
	 * ۸px فاصله داشت — دیگه جایی برای بزرگ‌ترشدن نبود. spacer پایین‌تر هم
	 * باید دقیقاً هم‌ارزِ همین مقدار بمونه، وگرنه محتوای زیرِ هدرِ fixed یک
	 * فاصله‌ی خالی/کوتاهیِ نامتقارن می‌گیره.
	 */
	?>
	<div class="<?php echo $jluxe_header_sticky ? 'fixed inset-x-0 top-[var(--wp-admin--admin-bar--height,0px)] z-30' : 'relative'; ?> border-b border-white/10 bg-surface/60 shadow-sm backdrop-blur-xl">
		<div class="mx-auto flex h-[72px] max-w-[1296px] items-center justify-between gap-4 px-4 md:h-[90px]">
			<div data-jluxe-island="header-actions"></div>
			<div data-jluxe-island="mini-cart"></div>
			<div data-jluxe-island="category-drawer"></div>

			<!--
				لوگو به‌صورت React island mount می‌شه (src/islands/Header.tsx → BrandLogo) که
				خودش یک <a href="/"> واقعی داخلش رندر می‌کنه؛ برای همین این wrapper عمداً
				<span> است نه <a> — تا بعد از mount شدن یک anchor تودرتوی نامعتبر
				(<a><a>...</a></a>) ساخته نشه. fallback متنی زیرش برای قبل از هیدریشن/بدون JS.
			-->
			<span class="site-title shrink-0" data-jluxe-island="header-logo">
				<?php bloginfo( 'name' ); ?>
			</span>
		</div>
	</div>
	<?php if ( $jluxe_header_sticky ) : ?>
		<!-- spacer به ارتفاع ردیف fixed بالا، تا محتوای زیرش قایم نشه -->
		<div class="h-[72px] md:h-[90px]" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="hidden border-t border-border/60 md:block">
		<nav class="mx-auto flex h-16 max-w-[1296px] items-center gap-6 px-4 text-body">
			<?php
			/*
			 * دسته‌بندی‌ها به‌صورت مگامنو (island) — لینک اصلیش سمت سرور رندر می‌شه (href="/shop/")
			 * تا قبل از اجرای JS هم کار کنه، پنل کشویی فقط enhancement روی هاوره.
			 *
			 * ساختار ۶ آیتمِ سطح اول («صفحات راهنما» و «درباره جهیزیه لوکس» به‌صورت
			 * زیرمنوی کشویی) دقیقاً مطابق منوی واقعیِ فعلیِ jluxe.ir — بررسی‌شده زنده،
			 * نه حدسی. قبلاً این‌ها به‌صورت ۱۱ لینک تخت (flat) اضافه شده بودن که هم با
			 * ساختار واقعی سایت فرق داشت هم باعث overflow-x-auto روی nav شده بود؛
			 * چون پنل مگامنوی «دسته‌بندی‌ها» یک absolute با عرض ۱۱۸۰px هست، هر
			 * overflow روی nav (که ancestor یاش هست) این پنل رو کلیپ/مخفی می‌کنه —
			 * دقیقاً همون باگی که کاربر گزارش داد («مگامنو زیر صفحات باز می‌شه»).
			 * با برگشت به ۶ آیتم، دیگه نیازی به overflow-x-auto نیست و این باگ هم حل می‌شه.
			 */
			$jluxe_chevron = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 transition-transform group-hover:rotate-180" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
			$jluxe_nav_items = function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'header_nav.items', array() ) : array();
			?>
			<div data-jluxe-island="mega-menu"><a href="<?php echo esc_url( jluxe_shop_url() ); ?>">دسته‌بندی‌ها</a></div>

			<?php
			/*
			 * کدِ SVG سفارشی (اگه ادمین برای این آیتم تعریف کرده باشه، از
			 * jluxe_sanitize_svg_markup سالم‌سازی و ذخیره شده) به آیکونِ
			 * ثابتِ jluxe_nav_icon_svg اولویت داره — بسته‌بندیِ span با
			 * [&>svg]:size-full باعث می‌شه اندازه‌ی خودِ SVG (هرچی که باشه)
			 * با اندازه‌ی استانداردِ آیکون‌های دیگه یکی بشه.
			 */
			?>
			<?php foreach ( $jluxe_nav_items as $jluxe_item ) :
				$jluxe_icon = ! empty( $jluxe_item['svg'] )
					? '<span class="inline-flex size-4 shrink-0 [&>svg]:size-full">' . $jluxe_item['svg'] . '</span>'
					: jluxe_nav_icon_svg( $jluxe_item['icon'] ?? '', 'size-4' );
				if ( empty( $jluxe_item['children'] ) ) : ?>
					<a href="<?php echo esc_url( $jluxe_item['url'] ?: '#' ); ?>" class="flex shrink-0 items-center gap-1.5 text-text-secondary transition-colors hover:text-foreground">
						<?php echo $jluxe_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( $jluxe_item['label'] ); ?>
					</a>
				<?php else : ?>
					<div class="group relative">
						<a href="<?php echo esc_url( $jluxe_item['url'] ?: '#' ); ?>" class="flex shrink-0 items-center gap-1.5 text-text-secondary transition-colors hover:text-foreground">
							<?php echo $jluxe_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html( $jluxe_item['label'] ); ?>
							<?php echo $jluxe_chevron; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
						<div class="invisible absolute start-0 top-full z-40 min-w-[240px] rounded-xl border border-white/10 bg-surface/95 py-2 opacity-0 shadow-lg backdrop-blur-xl transition-all duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
							<?php foreach ( $jluxe_item['children'] as $jluxe_child ) :
								$jluxe_child_icon = ! empty( $jluxe_child['svg'] )
									? '<span class="inline-flex size-4 shrink-0 [&>svg]:size-full">' . $jluxe_child['svg'] . '</span>'
									: jluxe_nav_icon_svg( $jluxe_child['icon'] ?? '', 'size-4' );
								?>
								<a href="<?php echo esc_url( $jluxe_child['url'] ?: '#' ); ?>" class="flex items-center gap-1.5 px-4 py-2 text-small text-text-secondary transition-colors hover:bg-muted hover:text-primary">
									<?php echo $jluxe_child_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php echo esc_html( $jluxe_child['label'] ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif;
			endforeach; ?>
		</nav>
	</div>
</header>
