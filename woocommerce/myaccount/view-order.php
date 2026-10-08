<?php
/**
 * جزئیات یک سفارش در «حساب کاربری» — جایگزینِ متن ساده‌ی پیش‌فرض ووکامرس
 * («Order #X was placed on...») با کارت‌های پیشرفت/پرداخت/پیگیری/مشتری/
 * اقلام، مطابق طرح مرجع (jluxe.ir/account، صفحه‌ی جزئیاتِ سفارش).
 * روش ارسال از خودِ سفارش ووکامرس و کد رهگیری/یادداشت از متادیتای امن
 * سفارش خوانده می‌شود؛ در صورت شناسایی شرکت حمل، لینک رسمی پیگیری با کد
 * سفارش باز می‌شود و CAPTCHA پست همچنان دستی تکمیل می‌شود.
 *
 * @see woocommerce/templates/myaccount/view-order.php (نسخه‌ی اصلی)

 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$jluxe_status        = $order->get_status();
$jluxe_status_label   = function_exists( 'jluxe_get_order_display_status_label' ) ? jluxe_get_order_display_status_label( $order ) : wc_get_order_status_name( $jluxe_status );
$jluxe_timeline       = function_exists( 'jluxe_build_timeline_data' ) ? jluxe_build_timeline_data( $order ) : null;
$jluxe_is_cancelled   = $jluxe_timeline ? $jluxe_timeline['is_cancelled'] : in_array( $jluxe_status, array( 'cancelled', 'failed', 'refunded' ), true );
$jluxe_step_count     = $jluxe_timeline ? max( 1, (int) ( $jluxe_timeline['step_count'] ?? count( $jluxe_timeline['steps'] ) ) ) : 4;
$jluxe_progress       = ( $jluxe_timeline && ! $jluxe_is_cancelled && $jluxe_timeline['current_step'] > 0 ) ? round( $jluxe_timeline['current_step'] / $jluxe_step_count * 100 ) : 0;
$jluxe_status_color   = $jluxe_is_cancelled ? 'text-error' : ( 'completed' === $jluxe_status ? 'text-success' : 'text-primary' );
$jluxe_shipping       = function_exists( 'jluxe_build_shipping_data' ) ? jluxe_build_shipping_data( $order ) : array();
$jluxe_tracking       = function_exists( 'jluxe_get_tracking_info' ) ? jluxe_get_tracking_info( $order ) : array( 'tracking_code' => null, 'shipping_company' => null, 'shipping_note' => null );

$jluxe_address_parts  = array_filter(
	array(
		$order->get_billing_state(),
		$order->get_billing_city(),
		$order->get_billing_address_1(),
		$order->get_billing_address_2(),
	)
);
?>

<div class="flex flex-col gap-4">
	<div class="flex items-center justify-between gap-3">
		<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>" class="flex items-center gap-1.5 text-caption font-medium text-primary hover:text-primary-hover">
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
			بازگشت
		</a>
		<h1 class="text-h4 font-bold text-foreground" dir="ltr">#<?php echo esc_html( jluxe_fa_digits( $order->get_order_number() ) ); ?></h1>
	</div>

	<div class="rounded-2xl border border-border bg-surface p-4 sm:p-5">
		<div class="mb-3 flex items-center justify-between gap-3">
			<span class="text-small font-bold <?php echo esc_attr( $jluxe_status_color ); ?>"><?php echo esc_html( $jluxe_status_label ); ?></span>
			<?php if ( ! $jluxe_is_cancelled && $jluxe_progress > 0 ) : ?>
				<span class="text-caption text-text-secondary"><?php echo esc_html( jluxe_fa_digits( (string) $jluxe_progress ) ); ?>٪</span>
			<?php endif; ?>
		</div>
		<?php if ( $jluxe_is_cancelled ) : ?>
			<p class="rounded-xl bg-error/10 px-3 py-2 text-caption font-bold text-error">برای این وضعیت، مراحل معمول سفارش نمایش داده نمی‌شود.</p>
		<?php elseif ( $jluxe_timeline && $jluxe_timeline['current_step'] > 0 ) : ?>
			<ol class="jluxe-order-timeline" aria-label="مراحل سفارش">
				<?php foreach ( $jluxe_timeline['steps'] as $jluxe_step ) :
					$jluxe_step_state = ! empty( $jluxe_step['done'] ) ? 'is-done' : ( ! empty( $jluxe_step['active'] ) ? 'is-active' : 'is-upcoming' );
					?>
					<li class="jluxe-order-timeline__step <?php echo esc_attr( $jluxe_step_state ); ?>" <?php echo ! empty( $jluxe_step['active'] ) ? 'aria-current="step"' : ''; ?>>
						<span class="jluxe-order-timeline__marker" aria-hidden="true"><?php echo ! empty( $jluxe_step['done'] ) ? '✓' : esc_html( jluxe_fa_digits( (string) $jluxe_step['step'] ) ); ?></span>
						<span class="jluxe-order-timeline__label"><?php echo esc_html( $jluxe_step['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="text-caption text-text-secondary">وضعیت سفارش پس از به‌روزرسانی ووکامرس در این بخش نمایش داده می‌شود.</p>
		<?php endif; ?>
	</div>

	<div class="grid grid-cols-2 gap-3">
		<div class="rounded-2xl border border-border bg-surface p-4">
			<p class="text-caption text-text-secondary">مبلغ کل</p>
			<p class="mt-1 text-small font-bold text-foreground"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
		</div>
		<div class="rounded-2xl border border-border bg-surface p-4">
			<p class="text-caption text-text-secondary">روش پرداخت</p>
			<p class="mt-1 truncate text-small font-bold text-foreground"><?php echo esc_html( function_exists( 'jluxe_get_payment_method_label' ) ? jluxe_get_payment_method_label( $order ) : ( $order->get_payment_method_title() ?: '—' ) ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $jluxe_shipping['shipping_method'] ) || ! empty( $jluxe_shipping['tracking_code'] ) || ! empty( $jluxe_tracking['shipping_note'] ) ) : ?>
		<div class="jluxe-order-shipment rounded-2xl border border-border bg-surface p-4">
			<h2 class="mb-3 text-small font-bold text-foreground">ارسال و پیگیری مرسوله</h2>
			<?php if ( ! empty( $jluxe_shipping['shipping_method'] ) ) : ?>
				<p class="jluxe-order-shipment__method text-caption text-text-secondary">روش ارسال ثبت‌شده: <strong class="text-foreground"><?php echo esc_html( $jluxe_shipping['shipping_method'] ); ?></strong></p>
			<?php endif; ?>
			<?php if ( ! empty( $jluxe_shipping['tracking_code'] ) ) : ?>
				<div class="jluxe-order-shipment__tracking mt-3 rounded-xl bg-muted/60 p-3">
					<p class="text-caption text-text-secondary">کد پیگیری<?php echo ! empty( $jluxe_shipping['shipping_company'] ) ? ' — ' . esc_html( $jluxe_shipping['shipping_company'] ) : ''; ?></p>
					<p class="jluxe-order-shipment__tracking-code mt-1 break-all font-mono text-small font-extrabold text-foreground" dir="ltr"><?php echo esc_html( jluxe_convert_digits_to_en( $jluxe_shipping['tracking_code'] ) ); ?></p>
				</div>
				<?php if ( ! empty( $jluxe_shipping['tracking_url'] ) ) : ?>
					<a href="<?php echo esc_url( $jluxe_shipping['tracking_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-button font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.98]">
						<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></svg>
						پیگیری در سایت <?php echo esc_html( $jluxe_shipping['shipping_company'] ?: 'شرکت حمل' ); ?>
					</a>
					<?php if ( ! empty( $jluxe_shipping['requires_captcha'] ) ) : ?>
						<p class="mt-2 text-caption text-text-muted">کد را در سامانهٔ پست وارد کنید و برای ادامه، کپچای نمایش‌داده‌شده را خودتان تکمیل کنید.</p>
					<?php endif; ?>
				<?php else : ?>
					<p class="mt-2 text-caption text-text-muted">کد رهگیری ثبت شده است؛ آن را در سامانهٔ شرکت حمل‌ونقل وارد کنید.</p>
				<?php endif; ?>
			<?php elseif ( ! empty( $jluxe_shipping['shipping_method'] ) ) : ?>
				<p class="mt-2 text-caption text-text-muted">کد رهگیری پس از تحویل مرسوله و ثبت آن توسط فروشگاه در همین صفحه نمایش داده می‌شود.</p>
			<?php endif; ?>
			<?php if ( ! empty( $jluxe_tracking['shipping_note'] ) ) : ?>
				<div class="jluxe-order-shipment__note mt-3 rounded-xl border border-border bg-surface p-3">
					<p class="mb-1 text-caption font-bold text-foreground">یادداشت فروشگاه</p>
					<p class="whitespace-pre-line text-caption text-text-secondary"><?php echo esc_html( $jluxe_tracking['shipping_note'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rounded-2xl border border-border bg-surface p-4">
		<h2 class="mb-3 text-small font-bold text-foreground">اطلاعات مشتری</h2>
		<div class="flex flex-col gap-2 text-caption text-text-secondary">
			<p><span class="text-text-muted">نام:</span> <?php echo esc_html( trim( $order->get_formatted_billing_full_name() ) ); ?></p>
			<?php if ( $jluxe_address_parts ) : ?>
				<p><span class="text-text-muted">آدرس:</span> <?php echo esc_html( implode( '، ', $jluxe_address_parts ) ); ?></p>
			<?php endif; ?>
			<?php if ( $order->get_billing_postcode() ) : ?>
				<p><span class="text-text-muted">کد پستی:</span> <bdi dir="ltr"><?php echo esc_html( jluxe_fa_digits( $order->get_billing_postcode() ) ); ?></bdi></p>
			<?php endif; ?>
			<?php if ( $order->get_billing_email() ) : ?>
				<p><span class="text-text-muted">ایمیل:</span> <bdi dir="ltr"><?php echo esc_html( $order->get_billing_email() ); ?></bdi></p>
			<?php endif; ?>
			<?php if ( $order->get_billing_phone() ) : ?>
				<p><span class="text-text-muted">تلفن:</span> <bdi dir="ltr"><?php echo esc_html( jluxe_fa_digits( $order->get_billing_phone() ) ); ?></bdi></p>
			<?php endif; ?>
		</div>
	</div>

	<div>
		<h2 class="mb-3 text-small font-bold text-foreground">محصولات سفارش</h2>
		<ul class="flex flex-col gap-2">
			<?php foreach ( $order->get_items() as $item ) :
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				?>
				<li class="rounded-2xl border border-border bg-surface p-4">
					<p class="mb-2 text-small font-bold text-foreground"><?php echo esc_html( $item->get_name() ); ?></p>
					<div class="flex items-center justify-between text-caption text-text-secondary">
						<span>تعداد: <span class="font-medium text-foreground"><?php echo esc_html( jluxe_fa_digits( (string) $item->get_quantity() ) ); ?></span></span>
						<span><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php
	$jluxe_notes = $order->get_customer_order_notes();
	if ( $jluxe_notes ) :
		?>
		<div>
			<h2 class="mb-3 text-small font-bold text-foreground">به‌روزرسانی‌های سفارش</h2>
			<ul class="flex flex-col gap-2">
				<?php foreach ( $jluxe_notes as $jluxe_note ) : ?>
					<li class="rounded-2xl border border-border bg-surface p-4">
						<p class="mb-1 text-caption text-text-muted"><?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $jluxe_note->comment_date ) ) ); ?></p>
						<div class="text-caption text-text-secondary"><?php echo wp_kses_post( wpautop( wptexturize( $jluxe_note->comment_content ) ) ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
