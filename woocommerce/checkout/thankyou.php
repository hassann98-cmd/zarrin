<?php
/**
 * صفحه‌ی «پایان خرید» — با داده‌ی واقعی سفارش (order number/total/status
 * واقعی از WC_Order). این تنها زمانی رندر می‌شه که ووکامرس واقعاً سفارش
 * رو ساخته باشه؛ هیچ صفحه‌ی موفقیت جعلی قبل از ساخته‌شدن سفارش وجود
 * نداره.
 *
 * @see woocommerce/templates/checkout/thankyou.php (نسخه‌ی اصلی)
 * @version 8.1.0
 * @var WC_Order|false $order
 *
 * صفحه فقط از اطلاعات واقعی WC_Order استفاده می‌کند: شماره و مبلغِ سفارش،
 * اقلام با واحد پول خود سفارش، نشانیِ ثبت‌شده (اگر موجود باشد) و لینکِ
 * پیگیری. وضعیتِ پرداخت ناموفق جدا نمایش داده می‌شود و وعدهٔ زمانِ ارسال
 * یا پیامکِ رهگیریِ تأییدنشده‌ای ساخته نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

jluxe_render_checkout_stepper( 'done' );

if ( $order && ! $order->has_status( 'failed' ) ) {
	$jluxe_ty_contact = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['contact'] : array();
	$jluxe_ty_phone   = $jluxe_ty_contact['phone'] ?? '';
	$jluxe_ty_items   = $order->get_items();
	$jluxe_ty_has_shipping_address = (bool) array_filter( $order->get_address( 'shipping' ) );
	$jluxe_ty_address = $jluxe_ty_has_shipping_address ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();

	// کارت‌های اعتمادِ همون بخشِ فوتر (footer.feature_cards) — طبقِ اصلِ
	// «تکرار نکن»، به‌جایِ تعریفِ دوباره‌ی متنِ ارسال/پشتیبانی/امنیت اینجا.
	$jluxe_ty_trust_cards = array_filter(
		function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings()['footer']['feature_cards'] : array(),
		fn( $c ) => ! empty( $c['enabled'] )
	);
	// feature_cards دیفالت از کلیدِ badge-percent استفاده می‌کنه که توی
	// مجموعه‌ی مشترکِ jluxe_nav_icon_svg نیست (فقط «percent» ساده هست) —
	// این نگاشتِ کوچیک همون‌جا رو پوشش می‌ده، بدونِ اضافه‌کردنِ یک آیکونِ
	// جدید به یک مجموعه‌ی مشترکِ دیگه فقط برایِ یک صفحه.
	$jluxe_ty_icon_alias = array( 'badge-percent' => 'percent' );
}
?>

<div class="mx-auto w-full max-w-[760px] px-4 py-6">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>
			<div class="flex flex-col items-center gap-4 rounded-2xl border border-error/30 bg-error/5 py-16 text-center">
				<svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" class="text-error" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
				<h1 class="text-h2 text-foreground">پرداخت ناموفق بود</h1>
				<p class="max-w-md text-body text-text-secondary">متأسفانه سفارش شما توسط بانک/درگاه پرداخت رد شد. لطفاً دوباره تلاش کنید.</p>
				<div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="flex h-12 w-full items-center justify-center rounded-lg bg-primary px-6 text-button text-primary-foreground sm:w-auto">تلاش مجدد برای پرداخت</a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( jluxe_route_url( 'dashboard' ) ); ?>" class="flex h-12 w-full items-center justify-center rounded-lg border border-border px-6 text-button text-foreground sm:w-auto">حساب کاربری</a>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>

			<?php
			/*
			 * هدرِ جشن‌مانند — گرادیانِ گرمِ success→accent (نه فقط success تنها،
			 * تا حسِ «شاد» به‌جایِ صرفاً «تأیید» منتقل بشه)، با یک چک‌مارکِ
			 * SVG که خطش با stroke-dasharray/offset «کشیده می‌شه» (نه صرفاً
			 * fade-in) — الگویِ رایجِ صفحاتِ موفقیتِ مدرن. prefers-reduced-motion
			 * کاملاً خاموشش می‌کنه (globals.css).
			 */
			?>
			<div class="jluxe-ty-hero relative overflow-hidden rounded-3xl border border-success/20 bg-gradient-to-br from-success/10 via-surface to-accent/10 px-6 py-10 text-center sm:py-14">
				<div class="mx-auto mb-5 flex size-20 items-center justify-center rounded-full bg-success text-white shadow-lg shadow-success/25 sm:size-24">
					<svg class="jluxe-ty-check-path size-10 sm:size-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
				</div>
				<h1 class="text-h1 font-extrabold text-foreground">سفارش شما با موفقیت ثبت شد 🎉</h1>
				<p class="mx-auto mt-3 max-w-md text-body text-text-secondary">جزئیات واقعی سفارش در همین صفحه قرار دارد. برای پیگیری، از دکمهٔ «پیگیری سفارش» استفاده کنید.</p>
				<div class="mt-6 inline-flex items-center gap-2 rounded-full bg-surface px-5 py-2 text-small font-bold text-foreground shadow-sm">
					شماره سفارش
					<span class="text-primary" dir="ltr">#<?php echo esc_html( jluxe_fa_digits( $order->get_order_number() ) ); ?></span>
				</div>
			</div>

			<?php
			/*
			 * گریدِ اطلاعاتِ سفارش — طبقِ درخواستِ «پرامکانات»، به‌جایِ یک
			 * لیستِ متنیِ خطی، هر آیتم یک کارتِ کوچیکِ جداگونه با آیکون. روی
			 * موبایل ۲ستونه، از sm به بالا ۴ستونه — ریسپانسیوِ کامل.
			 */
			?>
			<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
				<div class="flex flex-col items-center gap-1.5 rounded-2xl border border-border bg-surface p-4 text-center">
					<span class="text-caption text-text-muted">شماره سفارش</span>
					<strong class="text-small text-foreground" dir="ltr">#<?php echo esc_html( jluxe_fa_digits( $order->get_order_number() ) ); ?></strong>
				</div>
				<div class="flex flex-col items-center gap-1.5 rounded-2xl border border-border bg-surface p-4 text-center">
					<span class="text-caption text-text-muted">تاریخ ثبت</span>
					<strong class="text-small text-foreground"><?php echo esc_html( jluxe_fa_digits( wc_format_datetime( $order->get_date_created() ) ) ); ?></strong>
				</div>
				<div class="flex flex-col items-center gap-1.5 rounded-2xl border border-border bg-surface p-4 text-center">
					<span class="text-caption text-text-muted">مبلغ کل</span>
					<strong class="text-small text-foreground"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
				</div>
				<div class="flex flex-col items-center gap-1.5 rounded-2xl border border-border bg-surface p-4 text-center">
					<span class="text-caption text-text-muted">روش پرداخت</span>
					<strong class="text-small text-foreground"><?php echo esc_html( function_exists( 'jluxe_get_payment_method_label' ) ? jluxe_get_payment_method_label( $order ) : ( $order->get_payment_method_title() ?: '—' ) ); ?></strong>
				</div>
			</div>

			<?php if ( ! empty( $jluxe_ty_items ) ) : ?>
				<?php
				/*
				 * لیستِ واقعیِ اقلامِ خریداری‌شده — از خودِ $order (نه سبدِ خرید،
				 * که توسطِ خودِ ووکامرس بعدِ ثبتِ سفارش خالی می‌شه). تصویر/نام/
				 * تعداد/قیمتِ هرخط از WC_Order_Item_Product و فرمتِ مبلغ از واحد
				 * پول و تنظیماتِ قالب‌بندیِ خودِ سفارش می‌آید.
				 */
				?>
				<div class="mt-5 rounded-2xl border border-border bg-surface p-4 sm:p-5">
					<h2 class="mb-3 text-small font-bold text-foreground">محصولات این سفارش</h2>
					<ul class="flex flex-col divide-y divide-border">
						<?php foreach ( $jluxe_ty_items as $jluxe_ty_item ) :
							$jluxe_ty_product   = $jluxe_ty_item->get_product();
							$jluxe_ty_thumb_id  = $jluxe_ty_product ? $jluxe_ty_product->get_image_id() : 0;
							$jluxe_ty_thumb_url = $jluxe_ty_thumb_id ? wp_get_attachment_image_url( $jluxe_ty_thumb_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
							?>
							<li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
								<span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted/40">
									<img src="<?php echo esc_url( $jluxe_ty_thumb_url ); ?>" alt="<?php echo esc_attr( $jluxe_ty_item->get_name() ); ?>" class="size-full object-contain" loading="lazy" />
								</span>
								<div class="min-w-0 flex-1">
									<p class="line-clamp-2 text-small font-medium text-foreground"><?php echo esc_html( $jluxe_ty_item->get_name() ); ?></p>
									<p class="mt-0.5 text-caption text-text-muted"><?php echo esc_html( jluxe_fa_digits( (string) $jluxe_ty_item->get_quantity() ) ); ?> عدد</p>
								</div>
								<div class="shrink-0 text-small font-bold text-foreground">
									<?php echo wp_kses_post( $order->get_formatted_line_subtotal( $jluxe_ty_item ) ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $jluxe_ty_address ) : ?>
				<div class="mt-5 rounded-2xl border border-border bg-surface p-4 sm:p-5">
					<h2 class="mb-2 text-small font-bold text-foreground"><?php echo $jluxe_ty_has_shipping_address ? 'آدرس ارسال' : 'آدرس صورتحساب'; ?></h2>
					<p class="text-small text-text-secondary"><?php echo wp_kses_post( $jluxe_ty_address ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $jluxe_ty_trust_cards ) ) : ?>
				<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
					<?php foreach ( $jluxe_ty_trust_cards as $jluxe_ty_card ) : ?>
						<div class="flex flex-col items-center gap-1.5 rounded-xl border border-border bg-surface p-3 text-center">
							<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
								<?php if ( ! empty( $jluxe_ty_card['svg'] ) ) : ?>
									<span class="size-[18px] [&>svg]:size-full" aria-hidden="true"><?php echo $jluxe_ty_card['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php else : ?>
									<?php echo jluxe_nav_icon_svg( $jluxe_ty_icon_alias[ $jluxe_ty_card['icon'] ] ?? $jluxe_ty_card['icon'], 'size-[18px]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</span>
							<p class="text-caption font-bold text-foreground"><?php echo esc_html( $jluxe_ty_card['title'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			// مشتریِ لاگین‌کرده حسابِ واقعی داره، پس مستقیم به بخش سفارش‌های
			// همون حساب می‌ره (نیازی به وارد کردن دوباره‌ی شماره سفارش/موبایل
			// نیست)؛ فقط خریدِ مهمان (بدون حساب) به صفحه‌ی پیگیریِ عمومی می‌ره.
			$jluxe_ty_track_url = jluxe_route_url( 'thankyou_orders' );
			?>
			<div class="mt-6 flex flex-col gap-3 sm:flex-row">
				<a href="<?php echo esc_url( $jluxe_ty_track_url ); ?>" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-primary px-6 text-button text-primary-foreground transition-colors hover:bg-primary-hover">پیگیری سفارش</a>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-border px-6 text-button text-foreground transition-colors hover:bg-muted">ادامه‌ی خرید</a>
			</div>

			<?php if ( $jluxe_ty_phone ) : ?>
				<p class="mt-4 text-center text-caption text-text-muted">
					سؤالی دارید؟ با شماره‌ی
					<a href="tel:<?php echo esc_attr( $jluxe_ty_phone ); ?>" class="font-bold text-foreground" dir="ltr"><?php echo esc_html( jluxe_fa_digits( $jluxe_ty_phone ) ); ?></a>
					در تماس باشید.
				</p>
			<?php endif; ?>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
	<?php else : ?>
		<p class="text-body text-text-secondary">سفارشی یافت نشد.</p>
	<?php endif; ?>
</div>
