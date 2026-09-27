<?php
/**
 * لیست سفارش‌های «حساب کاربری» — جایگزینِ جدول پیش‌فرض ووکامرس با کارت‌های
 * لمسی، مطابق طرح مرجع (jluxe.ir/account، بخش سفارش‌ها). داده‌ها همگی از
 * همون متغیرهایی میان که خودِ WC_Shortcode_My_Account::orders() قبل از
 * include این فایل ست می‌کنه ($customer_orders/$has_orders/$current_page) —
 * نه کوئری جدا، تا فیلتر/pagination اصلیِ ووکامرس دست‌نخورده بمونه.
 *
 * @see woocommerce/templates/myaccount/orders.php (نسخه‌ی اصلی)
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders );
?>

<?php if ( $has_orders ) : ?>

	<div class="flex flex-col gap-3">
		<?php foreach ( $customer_orders->orders as $customer_order ) :
			$order         = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$status        = $order->get_status();
			$status_label  = function_exists( 'jluxe_get_status_label' ) ? jluxe_get_status_label( $status ) : wc_get_order_status_name( $status );
			$date_created  = $order->get_date_created();
			$jalali_date   = ( $date_created && function_exists( 'jluxe_gregorian_timestamp_to_jalali_string' ) )
				? jluxe_gregorian_timestamp_to_jalali_string( $date_created->getTimestamp(), 'Y/m/d' )
				: ( $date_created ? wc_format_datetime( $date_created ) : '' );
			$status_color  = in_array( $status, array( 'cancelled', 'failed' ), true )
				? 'text-error'
				: ( in_array( $status, array( 'completed' ), true ) ? 'text-success' : 'text-primary' );
			?>
			<a
				href="<?php echo esc_url( $order->get_view_order_url() ); ?>"
				aria-label="<?php echo esc_attr( sprintf( __( 'View order number %s', 'woocommerce' ), $order->get_order_number() ) ); ?>"
				class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-surface p-4 transition-colors hover:border-primary/30"
			>
				<div class="flex min-w-0 items-center gap-3">
					<span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 <?php echo esc_attr( $status_color ); ?>">
						<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.3 12 12l-8.5-4.7"/><path d="M12 22V12"/><path d="m20.5 7.3-8.4-4.6a1 1 0 0 0-1 0L3 7.3"/><path d="M3 7.3v9.4a1 1 0 0 0 .5.9l8 4.5a1 1 0 0 0 1 0l8-4.5a1 1 0 0 0 .5-.9V7.3"/></svg>
					</span>
					<div class="min-w-0">
						<p class="truncate text-small font-bold text-foreground"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
						<p class="text-caption text-text-secondary" dir="ltr">#<?php echo esc_html( jluxe_fa_digits( $order->get_order_number() ) ); ?></p>
					</div>
				</div>
				<div class="shrink-0 text-left">
					<p class="text-small font-bold <?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( $status_label ); ?></p>
					<p class="text-caption text-text-secondary"><?php echo esc_html( $jalali_date ); ?></p>
				</div>
			</a>
		<?php endforeach; ?>
	</div>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<div class="mt-4 flex items-center justify-between gap-3">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="flex h-11 items-center justify-center rounded-xl border border-border px-5 text-button font-medium text-foreground transition-colors hover:bg-muted" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>

			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="flex h-11 items-center justify-center rounded-xl border border-border px-5 text-button font-medium text-foreground transition-colors hover:bg-muted" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else : ?>

	<div class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-surface py-14 text-center">
		<span class="grid size-14 place-items-center rounded-2xl bg-muted text-text-muted/60">
			<svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.3 12 12l-8.5-4.7"/><path d="M12 22V12"/><path d="m20.5 7.3-8.4-4.6a1 1 0 0 0-1 0L3 7.3"/><path d="M3 7.3v9.4a1 1 0 0 0 .5.9l8 4.5a1 1 0 0 0 1 0l8-4.5a1 1 0 0 0 .5-.9V7.3"/></svg>
		</span>
		<p class="text-small font-bold text-foreground">هنوز سفارشی ثبت نکرده‌اید</p>
		<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="mt-1 rounded-xl bg-primary px-5 py-2.5 text-caption font-bold text-primary-foreground transition-all hover:bg-primary-hover active:scale-95">
			مشاهده فروشگاه
		</a>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
