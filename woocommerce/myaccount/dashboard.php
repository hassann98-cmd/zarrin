<?php
/**
 * داشبورد «حساب کاربری» — کارت‌های آماریِ واقعی و محدود به سفارش‌های همین
 * کاربر (از wc_get_orders، بدون بارگذاری همهٔ سفارش‌ها)؛ وضعیت‌های پرداخت،
 * آماده‌سازی، تکمیل و سفارش‌های بسته‌شده از خودِ WooCommerce خوانده می‌شوند.
 * پنل «دسترسی سریع» هم فقط لینک‌های واقعاً پیکربندی‌شده را نمایش می‌دهد.

 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jluxe_account_order_count( int $user_id, $status ): int {
	if ( $user_id < 1 ) {
		return 0;
	}
	$result = wc_get_orders(
		array(
			'customer' => $user_id,
			'status'   => $status,
			'return'   => 'ids',
			'limit'    => 1,
			'paginate' => true,
		)
	);
	if ( is_object( $result ) && isset( $result->total ) ) {
		return max( 0, (int) $result->total );
	}
	return is_countable( $result ) ? count( $result ) : 0;
}

$jluxe_user_id  = get_current_user_id();
$jluxe_counts   = array(
	'pending'    => jluxe_account_order_count( $jluxe_user_id, array( 'wc-pending', 'wc-on-hold' ) ),
	'processing' => jluxe_account_order_count( $jluxe_user_id, 'wc-processing' ),
	'completed'  => jluxe_account_order_count( $jluxe_user_id, 'wc-completed' ),
	'closed'     => jluxe_account_order_count( $jluxe_user_id, array( 'wc-cancelled', 'wc-failed', 'wc-refunded' ) ),
);

$jluxe_phone        = jluxe_get_setting( 'contact.phone', '' );
$jluxe_announcement = jluxe_get_setting( 'contact.dashboard_announcement', '' );
$jluxe_announce_url = jluxe_get_setting( 'contact.dashboard_announcement_link', '' );

$jluxe_stat_cards = array(
	array( 'label' => 'منتظر پرداخت یا تأیید', 'value' => jluxe_fa_digits( $jluxe_counts['pending'] ) . ' سفارش', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
	array( 'label' => 'در حال آماده‌سازی', 'value' => jluxe_fa_digits( $jluxe_counts['processing'] ) . ' سفارش', 'icon' => '<path d="M20.5 7.3 12 12l-8.5-4.7"/><path d="M12 22V12"/><path d="m20.5 7.3-8.4-4.6a1 1 0 0 0-1 0L3 7.3"/>' ),
	array( 'label' => 'تکمیل شده', 'value' => jluxe_fa_digits( $jluxe_counts['completed'] ) . ' سفارش', 'icon' => '<path d="M20 6 9 17l-5-5"/>' ),
	array( 'label' => 'لغو یا بازگشت وجه', 'value' => jluxe_fa_digits( $jluxe_counts['closed'] ) . ' سفارش', 'icon' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>' ),
);
?>

<div class="flex flex-col gap-6">
	<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
		<?php foreach ( $jluxe_stat_cards as $card ) : ?>
			<div class="flex flex-col gap-2 rounded-2xl border border-border bg-surface p-4">
				<svg class="size-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $card['icon']; ?></svg>
				<p class="text-h4 font-bold text-foreground"><?php echo esc_html( $card['value'] ); ?></p>
				<p class="text-caption text-text-secondary"><?php echo esc_html( $card['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $jluxe_phone || $jluxe_announcement ) : ?>
		<div class="rounded-2xl border border-border bg-surface p-4">
			<h2 class="mb-3 text-small font-bold text-foreground">دسترسی سریع</h2>
			<div class="flex flex-col divide-y divide-border">
				<?php if ( $jluxe_phone ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $jluxe_phone ) ); ?>" class="flex items-center gap-3 py-3 text-small text-text-secondary transition-colors hover:text-primary">
						<svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.8 16.6a1 1 0 0 0 1.2-.3l.4-.5a2 2 0 0 1 1.6-.8h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.5.4a1 1 0 0 0-.3 1.2 14 14 0 0 0 6.4 6.4Z"/></svg>
						<span class="flex-1">پشتیبانی — <bdi dir="ltr"><?php echo esc_html( $jluxe_phone ); ?></bdi></span>
					</a>
				<?php endif; ?>
				<?php if ( $jluxe_announcement ) : ?>
					<?php if ( $jluxe_announce_url ) : ?>
						<a href="<?php echo esc_url( $jluxe_announce_url ); ?>" class="flex items-center gap-3 py-3 text-small text-text-secondary transition-colors hover:text-primary">
					<?php else : ?>
						<div class="flex items-center gap-3 py-3 text-small text-text-secondary">
					<?php endif; ?>
						<svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M3.3 8.9a8.7 8.7 0 0 1 17.4 0c0 5.2 2.3 6.9 2.3 6.9H1s2.3-1.7 2.3-6.9"/></svg>
						<span class="flex-1"><?php echo esc_html( $jluxe_announcement ); ?></span>
					<?php echo $jluxe_announce_url ? '</a>' : '</div>'; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
	do_action( 'woocommerce_account_dashboard' );
	do_action( 'woocommerce_before_my_account' );
	do_action( 'woocommerce_after_my_account' );
