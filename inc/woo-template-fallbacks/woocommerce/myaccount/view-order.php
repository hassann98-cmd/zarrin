<?php
/**
 * جزئیات یک سفارش در «حساب کاربری» — جایگزینِ متن ساده‌ی پیش‌فرض ووکامرس
 * («Order #X was placed on...») با کارت‌های پیشرفت/پرداخت/پیگیری/مشتری/
 * اقلام، مطابق طرح مرجع (jluxe.ir/account، صفحه‌ی جزئیاتِ سفارش).
 *
 * کد پیگیری از همون jluxe_get_tracking_info() موجود در inc/order-tracking.php
 * خونده می‌شه (که خودش کلیدهای واقعیِ متای افزونه‌ی jluxe-sms رو چک می‌کنه) —
 * دکمه‌ی «پیگیری سفارش» به صفحه‌ی خودِ سایت (/track-order/) لینک می‌ده، نه
 * یک URL شرکتِ پستیِ حدسی، چون نمی‌دونیم واقعاً از کدوم شرکت پست استفاده
 * می‌شه (طبق قانون «بدون محتوای جعلی» پروژه).
 *
 * @see woocommerce/templates/myaccount/view-order.php (نسخه‌ی اصلی)
 */

defined( 'ABSPATH' ) || exit;

$jluxe_status        = $order->get_status();
$jluxe_status_label   = function_exists( 'jluxe_get_status_label' ) ? jluxe_get_status_label( $jluxe_status ) : wc_get_order_status_name( $jluxe_status );
$jluxe_timeline       = function_exists( 'jluxe_build_timeline_data' ) ? jluxe_build_timeline_data( $order ) : null;
$jluxe_is_cancelled   = $jluxe_timeline ? $jluxe_timeline['is_cancelled'] : in_array( $jluxe_status, array( 'cancelled', 'failed', 'refunded' ), true );
$jluxe_progress       = ( $jluxe_timeline && ! $jluxe_is_cancelled ) ? round( $jluxe_timeline['current_step'] / 6 * 100 ) : 0;
$jluxe_status_color   = $jluxe_is_cancelled ? 'text-error' : ( 'completed' === $jluxe_status ? 'text-success' : 'text-primary' );
$jluxe_bar_color      = $jluxe_is_cancelled ? 'bg-error' : 'bg-primary';

$jluxe_tracking       = function_exists( 'jluxe_get_tracking_info' ) ? jluxe_get_tracking_info( $order ) : array( 'tracking_code' => null, 'shipping_company' => null );

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

	<div class="rounded-2xl border border-border bg-surface p-4">
		<div class="mb-2 flex items-center justify-between">
			<span class="text-small font-bold <?php echo esc_attr( $jluxe_status_color ); ?>"><?php echo esc_html( $jluxe_status_label ); ?></span>
			<?php if ( ! $jluxe_is_cancelled ) : ?>
				<span class="text-caption text-text-secondary"><?php echo esc_html( jluxe_fa_digits( (string) $jluxe_progress ) ); ?>٪</span>
			<?php endif; ?>
		</div>
		<div class="h-2 overflow-hidden rounded-full bg-muted">
			<div class="h-full <?php echo esc_attr( $jluxe_bar_color ); ?> rounded-full transition-all" style="width:<?php echo $jluxe_is_cancelled ? '100' : esc_attr( $jluxe_progress ); ?>%"></div>
		</div>
	</div>

	<div class="grid grid-cols-2 gap-3">
		<div class="rounded-2xl border border-border bg-surface p-4">
			<p class="text-caption text-text-secondary">مبلغ کل</p>
			<p class="mt-1 text-small font-bold text-foreground"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
		</div>
		<div class="rounded-2xl border border-border bg-surface p-4">
			<p class="text-caption text-text-secondary">روش پرداخت</p>
			<p class="mt-1 truncate text-small font-bold text-foreground"><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : '—' ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $jluxe_tracking['tracking_code'] ) ) : ?>
		<div class="rounded-2xl border border-border bg-surface p-4">
			<div class="mb-3 flex items-center justify-between gap-2">
				<div>
					<p class="text-caption text-text-secondary">کد پیگیری مرسوله<?php echo $jluxe_tracking['shipping_company'] ? ' — ' . esc_html( $jluxe_tracking['shipping_company'] ) : ''; ?></p>
					<p class="mt-1 text-small font-bold text-foreground" dir="ltr"><?php echo esc_html( jluxe_fa_digits( $jluxe_tracking['tracking_code'] ) ); ?></p>
				</div>
			</div>
			<a href="/track-order/" class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-button font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-[0.98]">
				<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle><path d="M5 17.972c-1.097-.054-1.78-.217-2.268-.704s-.65-1.171-.704-2.268M9 18h6m4-.028c1.097-.054 1.78-.217 2.268-.704C22 16.535 22 15.357 22 13v-2h-4.7c-.745 0-1.117 0-1.418-.098a2 2 0 0 1-1.284-1.284C14.5 9.317 14.5 8.945 14.5 8.2c0-1.117 0-1.675-.147-2.127a3 3 0 0 0-1.926-1.926C11.975 4 11.417 4 10.3 4H2m0 4h6m-6 3h4"></path><path d="M14.5 6h1.821c1.456 0 2.183 0 2.775.354c.593.353.938.994 1.628 2.276L22 11"></path></svg>
				پیگیری سفارش
			</a>
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
