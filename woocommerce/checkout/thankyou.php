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
 * طبقِ درخواستِ صریحِ کاربر («زیبا و پرامکانات باشه، حسِ خوب منتقل کنه، از
 * رنگ‌های شاد استفاده کن، کاملاً ریسپانسیو») — نسخه‌ی قبلی فقط یک آیکون +
 * عنوان + یک لیستِ متنیِ ساده بود. الان: یک هدرِ جشن‌مانند (گرادیانِ گرم
 * success/accent، چک‌مارکِ متحرک)، گریدِ اطلاعاتِ سفارش، لیستِ واقعیِ
 * اقلامِ خریداری‌شده (با تصویر/تعداد/قیمت، از خودِ $order، نه سبدِ خرید که
 * دیگه خالی شده)، یک نقشه‌راهِ ۴مرحله‌ای («چه اتفاقی می‌افته»)، آدرسِ ارسال
 * (اگه موجود بود)، و همون کارت‌های اعتمادِ فوتر (بدونِ تعریفِ دوباره‌ی
 * محتوا — از footer.feature_cards می‌خونه). حالتِ «پرداخت ناموفق» عمداً
 * جدا و کم‌رنگ‌تر موند (جشن گرفتن برای شکست منطقی نیست).
 */

defined( 'ABSPATH' ) || exit;

jluxe_render_checkout_stepper( 'done' );

$jluxe_ty_toman_svg = '<svg class="shrink-0" width="16" height="13" viewBox="0 0 22 18" fill="none" aria-hidden="true"><path fill="currentColor" d="M16.898.75h-2.376a.688.688 0 0 0 0 1.376h2.376a.688.688 0 0 0 0-1.376ZM21.247 3.814c-.021-.375-.083-.868-.187-1.477a26 26 0 0 0-.187-1.005.658.658 0 0 0-.762-.465.663.663 0 0 0-.487.72c.064.32.125.639.186.98.099.552.159.98.18 1.282.02.375-.092.667-.337.876-.245.208-.67.312-1.274.312H6.673V3.47c0-.667-.12-1.258-.36-1.774a2.63 2.63 0 0 0-1.032-1.211A2.71 2.71 0 0 0 3.718.047c-.563 0-1.066.151-1.508.453S1.423.802 1.178 1.323c-.245.522-.367 1.1-.367 1.737 0 .938.268 1.667.805 2.188.537.521 1.243.782 2.118.782h1.688v.094c0 .25-.099.448-.297.594-.198.146-.49.271-.876.375-.386.104-1.032.25-1.938.438l-.021.004a.688.688 0 1 0 .28.76c.148-.03.295-.06.443-.089.98-.198 1.722-.396 2.228-.594.505-.198.873-.456 1.102-.774.229-.318.344-.748.344-1.29v-.094h11.745c.636 0 1.17-.125 1.602-.375.433-.25.75-.576.954-.977a2.05 2.05 0 0 0 .275-1.008ZM5.453 5.033H3.734c-.594 0-1.026-.117-1.297-.352-.271-.234-.407-.638-.407-1.211 0-.615.149-1.107.446-1.477.297-.37.711-.555 1.243-.555.573 0 1.005.18 1.297.539.292.36.437.857.437 1.494v2.562Z"/><path fill="currentColor" d="M6.235 12.841a.781.781 0 1 0-1.563 0 .781.781 0 0 0 1.563 0ZM20.772 12.35c-.229-.537-.552-.963-.969-1.282a2.36 2.36 0 0 0-1.437-.477c-.678 0-1.256.233-1.735.696-.48.463-.834 1.102-1.063 1.914l-.5 1.797a.63.63 0 0 1-.198.293.62.62 0 0 1-.402.146c-.469 0-.805-.049-1.008-.148-.203-.099-.339-.274-.407-.524a4.5 4.5 0 0 1-.104-.72l-.016-4.017c0-.396-.068-.744-.203-1.048a1.62 1.62 0 0 0-.66-.71 1.98 1.98 0 0 0-.95-.26h-.516c-.458 0-.836.089-1.133.261a1.62 1.62 0 0 0-.664.703 2.4 2.4 0 0 0-.203.997l.016 4.36c0 .605-.084 1.082-.25 1.43a1.42 1.42 0 0 1-.936.766c-.396.163-.948.242-1.655.242h-.227c-.646 0-1.178-.134-1.594-.406a2.35 2.35 0 0 1-.923-1.117 4.1 4.1 0 0 1-.293-1.535c0-.218.034-.514.076-.796a.71.71 0 0 0-.62-.73.71.71 0 0 0-.796.53 6.5 6.5 0 0 0-.075.83c0 .792.154 1.538.461 2.235a3.5 3.5 0 0 0 1.396 1.688c.604.428 1.339.641 2.203.641h.227c.928 0 1.686-.156 2.275-.469a2.72 2.72 0 0 0 1.302-1.328 4.6 4.6 0 0 0 .396-2.047l-.015-4.362c0-.239.049-.4.148-.484.099-.084.3-.126.602-.126h.516c.281 0 .474.047.578.14.104.094.156.25.156.469l.016 4.017c0 .71.08 1.304.242 1.782a2.16 2.16 0 0 0 .84 1.117c.397.266.949.399 1.657.399.303 0 .594-.068.876-.203.281-.135.526-.322.734-.563l.063.032c.812.416 1.417.702 1.813.852a2.9 2.9 0 0 0 1.187.226c.396 0 .753-.111 1.102-.336.35-.224.634-.583.853-1.078.219-.495.328-1.128.328-1.9 0-.626-.115-1.206-.344-1.743Zm-1.14 3.25c-.178.267-.443.4-.798.4-.27 0-.557-.06-.86-.18-.302-.12-.807-.357-1.516-.712l-.11-.062.407-1.47c.146-.531.357-.927.633-1.187.276-.26.601-.39.977-.39.5 0 .88.185 1.14.554.261.37.392.883.392 1.54 0 .74-.089 1.242-.266 1.507Z"/></svg>';

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

	$jluxe_ty_steps = array(
		array( 'icon' => 'shield-check', 'title' => 'بررسی سفارش', 'text' => 'سفارش شما ثبت و برای پردازش ارسال شد.' ),
		array( 'icon' => 'package', 'title' => 'آماده‌سازی', 'text' => 'کالاها بسته‌بندی و برای ارسال آماده می‌شن.' ),
		array( 'icon' => 'truck', 'title' => 'ارسال', 'text' => 'مرسوله تحویلِ پست/باربری داده می‌شه.' ),
		array( 'icon' => 'home', 'title' => 'تحویل به شما', 'text' => 'کد پیگیری از طریق پیامک ارسال می‌شه.' ),
	);
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
				<div class="flex items-center gap-3">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-button text-primary-foreground">تلاش مجدد برای پرداخت</a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="flex h-12 items-center justify-center rounded-lg border border-border px-6 text-button text-foreground">حساب کاربری</a>
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
				<p class="mx-auto mt-3 max-w-md text-body text-text-secondary">ممنون که <?php echo esc_html( function_exists( 'jluxe_get_setting' ) ? jluxe_get_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' ) ); ?> رو برای این خرید انتخاب کردید؛ به‌زودی برای ارسال آماده می‌شه.</p>
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
					<strong class="text-small text-foreground"><?php echo wp_kses_post( $order->get_payment_method_title() ?: '—' ); ?></strong>
				</div>
			</div>

			<?php if ( ! empty( $jluxe_ty_items ) ) : ?>
				<?php
				/*
				 * لیستِ واقعیِ اقلامِ خریداری‌شده — از خودِ $order (نه سبدِ خرید،
				 * که توسطِ خودِ ووکامرس بعدِ ثبتِ سفارش خالی می‌شه). تصویر/نام/
				 * تعداد/قیمتِ هرخط دقیقاً از WC_Order_Item_Product، مبلغ هم با
				 * همون آیکونِ تومانِ استانداردِ سایت.
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
									<img src="<?php echo esc_url( $jluxe_ty_thumb_url ); ?>" alt="<?php echo esc_attr( $jluxe_ty_item->get_name() ); ?>" class="size-full object-contain mix-blend-multiply" loading="lazy" />
								</span>
								<div class="min-w-0 flex-1">
									<p class="line-clamp-2 text-small font-medium text-foreground"><?php echo esc_html( $jluxe_ty_item->get_name() ); ?></p>
									<p class="mt-0.5 text-caption text-text-muted"><?php echo esc_html( jluxe_fa_digits( (string) $jluxe_ty_item->get_quantity() ) ); ?> عدد</p>
								</div>
								<div class="flex shrink-0 items-center gap-1 text-foreground">
									<span class="text-small font-bold"><?php echo esc_html( jluxe_fa_digits( number_format( (float) $jluxe_ty_item->get_total(), 0 ) ) ); ?></span>
									<?php echo $jluxe_ty_toman_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * نقشه‌راهِ «بعدش چی می‌شه» — طبقِ درخواستِ «پرامکانات/حسِ خوب»؛
			 * مشتریِ تازه‌خریدکرده معمولاً همین سؤال رو داره. ۴ مرحله، روی
			 * موبایل عمودی (ستونی)، از sm به بالا افقی (ردیفی با خطِ رابط).
			 */
			?>
			<div class="mt-5 rounded-2xl border border-border bg-surface p-4 sm:p-5">
				<h2 class="mb-4 text-small font-bold text-foreground">از این به بعد چه اتفاقی می‌افتد؟</h2>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:gap-2">
					<?php foreach ( $jluxe_ty_steps as $jluxe_ty_i => $jluxe_ty_step ) : ?>
						<div class="relative flex items-start gap-3 sm:flex-col sm:items-center sm:text-center">
							<?php if ( $jluxe_ty_i < count( $jluxe_ty_steps ) - 1 ) : ?>
								<span class="absolute start-5 top-10 h-[calc(100%-1rem)] w-px bg-border sm:start-1/2 sm:top-5 sm:h-px sm:w-[calc(100%-2.5rem)] sm:translate-x-1/2" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="relative z-10 grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
								<?php echo jluxe_nav_icon_svg( $jluxe_ty_step['icon'], 'size-5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<div class="min-w-0 sm:mt-1">
								<p class="text-small font-bold text-foreground"><?php echo esc_html( $jluxe_ty_step['title'] ); ?></p>
								<p class="mt-0.5 text-caption text-text-muted"><?php echo esc_html( $jluxe_ty_step['text'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

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
			$jluxe_ty_track_url = is_user_logged_in() ? wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) : '/track-order/';
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
