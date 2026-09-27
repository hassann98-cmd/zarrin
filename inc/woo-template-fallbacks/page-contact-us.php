<?php
/**
 * صفحه‌ی «تماس با ما» (اسلاگ contact-us). محتوا از پنلِ ادمین می‌خونه
 * (جزئیات در ابتدای همین کامنت‌بلاک قبلاً توضیح داده شده بود، حذف نشده،
 * فقط چیدمان عوض شد). طبقِ درخواستِ صریحِ کاربر («باید تمام‌صفحه و کاملا
 * ریسپانسیو باشه») عرضِ کانتینر از max-w-[720px] (خیلی باریک‌تر از بقیه‌ی
 * سایت) به همون max-w-[1296px]ِ استانداردِ سراسرِ سایت تغییر کرد و
 * چیدمان از یک کارتِ باریکِ عمودی به یک گریدِ کارتِ ریسپانسیو (۱ ستون رو
 * موبایل → ۳ ستون رو دسکتاپ، بسته به تعدادِ بخش‌هایی که واقعاً فعالن)
 * تغییر کرد.
 */

get_header();

$jluxe_contact_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_contact          = $jluxe_contact_settings['contact'] ?? array();
$jluxe_hours            = $jluxe_contact_settings['footer']['support_hours'] ?? '';
$jluxe_social           = $jluxe_contact_settings['social'] ?? array();
$jluxe_page_info        = $jluxe_contact_settings['info_pages']['contact'] ?? array();
$jluxe_page_logo        = ! empty( $jluxe_page_info['show_logo'] ) && function_exists( 'jluxe_get_logo_url' ) ? jluxe_get_logo_url() : '';

$jluxe_phones = array_filter(
	array(
		$jluxe_contact['phone'] ?? '',
		$jluxe_contact['phone_secondary'] ?? '',
	)
);

$jluxe_social_labels = array(
	'instagram' => 'اینستاگرام',
	'telegram'  => 'تلگرام',
	'whatsapp'  => 'واتس‌اپ',
	'rubika'    => 'روبیکا',
	'bale'      => 'بله',
);
// آیکونِ عمومیِ پیش‌فرض برای هر شبکه‌ای که آیکونِ سفارشی (social[*][svg])
// ست نکرده — همون الگویی که Footer.tsx هم برای پیش‌فرض استفاده می‌کنه.
$jluxe_social_default_icon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 9-18 9 4-9-4-9Z"/></svg>';
$jluxe_active_socials       = array_filter(
	$jluxe_social,
	fn( $row ) => ! empty( $row['enabled'] ) && ! empty( $row['url'] )
);

// چند تا از کارت‌های اطلاعاتی واقعاً چیزی برای نشون‌دادن دارن — برای
// اینکه گرید (grid-cols) متناسب با تعدادِ واقعیِ کارت‌ها باشه، نه همیشه
// ۳ ستونِ نصفه‌خالی وقتی مثلاً فقط شماره‌تماس پر شده.
$jluxe_visible_card_count = ( ! empty( $jluxe_phones ) ? 1 : 0 ) + ( $jluxe_hours ? 1 : 0 ) + ( ! empty( $jluxe_active_socials ) ? 1 : 0 );
$jluxe_grid_cols_class    = $jluxe_visible_card_count >= 3 ? 'lg:grid-cols-3' : ( 2 === $jluxe_visible_card_count ? 'lg:grid-cols-2' : 'lg:grid-cols-1' );
?>

<main id="primary" class="site-main">
	<div class="mx-auto w-full max-w-[1296px] px-4 py-10 sm:py-14">
		<div class="mx-auto max-w-2xl text-center">
			<?php if ( $jluxe_page_logo ) : ?>
				<img src="<?php echo esc_url( $jluxe_page_logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="mx-auto mb-4 h-14 w-14 object-contain" />
			<?php endif; ?>
			<span class="text-[12px] font-bold tracking-wide text-primary">— تماس با ما</span>
			<h1 class="mb-3 mt-2 text-[26px] font-extrabold leading-relaxed text-foreground sm:text-[32px]"><?php echo esc_html( $jluxe_page_info['intro_title'] ?? 'همیشه در دسترس شما هستیم' ); ?></h1>
			<p class="text-[14px] leading-8 text-text-secondary sm:text-[15px]">
				<?php echo esc_html( $jluxe_page_info['intro_text'] ?? '' ); ?>
			</p>
		</div>

		<?php if ( $jluxe_visible_card_count > 0 ) : ?>
			<div class="mx-auto mt-10 grid max-w-5xl grid-cols-1 gap-4 sm:mt-14 sm:grid-cols-2 <?php echo esc_attr( $jluxe_grid_cols_class ); ?>">
				<?php if ( ! empty( $jluxe_phones ) ) : ?>
					<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md sm:p-7">
						<div class="mb-4 grid size-12 place-items-center rounded-xl bg-primary/10 text-primary">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
						</div>
						<h2 class="mb-2.5 text-[15px] font-bold text-foreground"><?php echo esc_html( $jluxe_page_info['phone_title'] ?? 'شماره‌ی تماس' ); ?></h2>
						<div class="flex flex-wrap items-center gap-x-2 gap-y-1">
							<?php foreach ( $jluxe_phones as $i => $jluxe_phone_number ) : ?>
								<?php if ( $i > 0 ) : ?><span class="text-text-muted">–</span><?php endif; ?>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $jluxe_phone_number ) ); ?>" dir="ltr" class="text-[15px] font-bold text-primary"><?php echo esc_html( jluxe_fa_digits( $jluxe_phone_number ) ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $jluxe_hours ) : ?>
					<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md sm:p-7">
						<div class="mb-4 grid size-12 place-items-center rounded-xl bg-primary/10 text-primary">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						</div>
						<h2 class="mb-2.5 text-[15px] font-bold text-foreground"><?php echo esc_html( $jluxe_page_info['hours_title'] ?? 'ساعات پاسخگویی' ); ?></h2>
						<p class="text-[14px] leading-7 text-text-secondary"><?php echo esc_html( $jluxe_hours ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $jluxe_active_socials ) ) : ?>
					<div class="rounded-2xl border border-border bg-surface p-6 transition-shadow hover:shadow-md sm:p-7">
						<div class="mb-4 grid size-12 place-items-center rounded-xl bg-primary/10 text-primary">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
						</div>
						<h2 class="mb-3.5 text-[15px] font-bold text-foreground"><?php echo esc_html( $jluxe_page_info['social_title'] ?? 'شبکه‌های اجتماعی' ); ?></h2>
						<div class="flex flex-wrap gap-2.5">
							<?php foreach ( $jluxe_active_socials as $jluxe_key => $jluxe_row ) : ?>
								<a
									title="<?php echo esc_attr( $jluxe_social_labels[ $jluxe_key ] ?? $jluxe_key ); ?>"
									href="<?php echo esc_url( $jluxe_row['url'] ); ?>"
									target="_blank"
									rel="noopener"
									class="grid size-11 place-items-center rounded-full border border-border bg-background text-foreground transition-transform hover:scale-[1.08] hover:text-primary active:scale-95"
								>
									<?php echo ! empty( $jluxe_row['svg'] ) ? $jluxe_row['svg'] : $jluxe_social_default_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- svg از قبل موقعِ ذخیره با jluxe_sanitize_svg_markup (wp_kses با whitelistِ تگ‌های SVG) پاک‌سازی شده. ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
