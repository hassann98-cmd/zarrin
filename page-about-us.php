<?php
/**
 * صفحه‌ی «درباره ما» (اسلاگ about-us). عنوان/متنِ معرفیِ بالای صفحه و
 * نمایش/عدمِ‌نمایشِ لوگو از پنلِ زرین ← «تماس و صفحات» قابل‌ویرایشه
 * (info_pages.about) — طبقِ درخواستِ صریحِ کاربر. بقیه‌ی محتوا (داستان،
 * چرا ما، CTA) همچنان متنِ عمومیِ یک فروشگاهِ زیورآلاتِ نقره/استیله.
 *
 * چیدمان طبقِ درخواستِ صریحِ کاربر («تمام‌صفحه و کاملا ریسپانسیو») از
 * یک کارتِ باریکِ max-w-[900px] به عرضِ استانداردِ سراسرِ سایت
 * (max-w-[1296px]) تغییر کرد؛ برای اینکه پاراگراف‌های متنی رو صفحه‌نمایشِ
 * پهن خیلی کشیده/کم‌خوانا نشن، خودِ متن‌ها تو یک max-w-2xl داخلی محدود
 * موندن، ولی گریدِ «چرا ما» و باکسِ CTA کاملِ عرض رو استفاده می‌کنن.
 */

get_header();

$jluxe_about_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_about_info     = $jluxe_about_settings['info_pages']['about'] ?? array();
$jluxe_about_logo     = ! empty( $jluxe_about_info['show_logo'] ) && function_exists( 'jluxe_get_logo_url' ) ? jluxe_get_logo_url() : '';

// طبقِ درخواستِ صریحِ کاربر: بخشِ «داستانِ ما» + CTA کاملاً از تنظیمات میان
// (jluxe-contact-pages ← «متنِ صفحاتِ تماس/درباره»)، نه دیگه هاردکد.
$jluxe_about_story_html      = $jluxe_about_info['story_html'] ?? '';
$jluxe_about_cta_text        = $jluxe_about_info['cta_text'] ?? '';
$jluxe_about_cta_button_text = $jluxe_about_info['cta_button_text'] ?? '';
$jluxe_about_cta_button_url  = $jluxe_about_info['cta_button_url'] ?? '/shop/';
$jluxe_about_cta_button_style = ! empty( $jluxe_about_info['cta_button_color'] ) ? ' style="background-color:' . esc_attr( $jluxe_about_info['cta_button_color'] ) . '"' : '';
?>

<main id="primary" class="site-main">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-10 sm:py-14">

		<div class="mx-auto max-w-2xl text-center">
			<?php if ( $jluxe_about_logo ) : ?>
				<img src="<?php echo esc_url( $jluxe_about_logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="mx-auto mb-4 h-14 w-14 object-contain" />
			<?php endif; ?>
			<div class="mb-3 flex items-center justify-center gap-1">
				<span class="size-1.5 rounded-full bg-primary"></span>
				<span class="size-1.5 rounded-full bg-primary"></span>
				<span class="size-1 rounded-full bg-primary"></span>
			</div>
			<h1 class="mb-3 text-[26px] font-extrabold leading-relaxed text-foreground sm:text-[32px]"><?php echo esc_html( $jluxe_about_info['intro_title'] ?? 'همیشه در کنار شما' ); ?></h1>
			<p class="text-[14px] leading-8 text-text-secondary sm:text-[15px]">
				<?php echo esc_html( $jluxe_about_info['intro_text'] ?? '' ); ?>
			</p>
		</div>

		<?php if ( $jluxe_about_story_html ) : ?>
			<div class="mx-auto mt-10 max-w-2xl rounded-2xl border border-border bg-surface p-6 sm:mt-14 sm:p-8 [&_.jluxe-about-eyebrow]:mb-2 [&_.jluxe-about-eyebrow]:block [&_.jluxe-about-eyebrow]:text-[12px] [&_.jluxe-about-eyebrow]:font-bold [&_.jluxe-about-eyebrow]:tracking-wide [&_.jluxe-about-eyebrow]:text-primary [&_h2]:mb-3.5 [&_h2]:mt-2 [&_h2]:text-[19px] [&_h2]:font-extrabold [&_h2]:leading-relaxed [&_h2]:text-foreground [&_p]:mb-2.5 [&_p]:text-[14px] [&_p]:leading-8 [&_p]:text-text-secondary [&_p:last-child]:mb-0">
				<?php echo $jluxe_about_story_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- از پنلِ ادمین با wp_kses_post سالم‌سازی شده. ?>
			</div>
		<?php endif; ?>

		<div class="mt-10 sm:mt-14">
			<div class="mx-auto mb-6 max-w-2xl text-center sm:mb-8">
				<span class="text-[12px] font-bold tracking-wide text-primary">— چرا ما را انتخاب کنید</span>
				<h2 class="mt-2 text-[19px] font-extrabold leading-relaxed text-foreground sm:text-[22px]">هر جزئیات، برای آسودگی خیال شما</h2>
			</div>
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
				<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md">
					<div class="mb-3.5 h-[3px] w-6 rounded-full bg-primary"></div>
					<h3 class="mb-1.5 text-[14.5px] font-bold text-foreground">اصالتِ تضمین‌شده</h3>
					<p class="text-[12.5px] leading-7 text-text-muted">نقره‌ی وارداتیِ اصل عیار ۹۲۵ و استیلِ ضدحساسیت، با گارانتیِ اصالتِ کالا.</p>
				</div>
				<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md">
					<div class="mb-3.5 h-[3px] w-6 rounded-full bg-primary"></div>
					<h3 class="mb-1.5 text-[14.5px] font-bold text-foreground">قیمتی منصفانه، بدون واسطه</h3>
					<p class="text-[12.5px] leading-7 text-text-muted">با ارتباطِ مستقیم با تولیدکننده، باکیفیت‌ترین محصولات را با رقابتی‌ترین قیمت تقدیمِ نگاهتان می‌کنیم.</p>
				</div>
				<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md">
					<div class="mb-3.5 h-[3px] w-6 rounded-full bg-primary"></div>
					<h3 class="mb-1.5 text-[14.5px] font-bold text-foreground">ارسال مطمئن و بسته‌بندی ایمن</h3>
					<p class="text-[12.5px] leading-7 text-text-muted">سفارش‌ها با دقت و ایمن‌ترین روشِ بسته‌بندی آماده و در سریع‌ترین زمانِ ممکن ارسال می‌شوند.</p>
				</div>
				<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md">
					<div class="mb-3.5 h-[3px] w-6 rounded-full bg-primary"></div>
					<h3 class="mb-1.5 text-[14.5px] font-bold text-foreground">همراهی صمیمانه</h3>
					<p class="text-[12.5px] leading-7 text-text-muted">تیمِ پشتیبانیِ ما پاسخگوی سوالاتِ شماست تا با مشاوره‌ی رایگان، تصمیمی مطمئن بگیرید.</p>
				</div>
			</div>
		</div>

		<?php if ( $jluxe_about_cta_text || $jluxe_about_cta_button_text ) : ?>
			<div class="mx-auto mt-10 max-w-4xl rounded-2xl bg-gradient-to-l from-primary to-primary/80 px-6 py-8 text-center shadow-md sm:mt-14 sm:px-10 sm:py-10">
				<?php if ( $jluxe_about_cta_text ) : ?>
					<p class="mb-5 text-[16px] font-bold leading-8 text-primary-foreground sm:text-[18px]"><?php echo esc_html( $jluxe_about_cta_text ); ?></p>
				<?php endif; ?>
				<?php if ( $jluxe_about_cta_button_text ) : ?>
					<a href="<?php echo esc_url( $jluxe_about_cta_button_url ); ?>" class="inline-block rounded-full bg-surface px-8 py-3.5 text-[14px] font-bold text-foreground transition-transform hover:scale-[1.03] active:scale-95"<?php echo $jluxe_about_cta_button_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $jluxe_about_cta_button_text ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
