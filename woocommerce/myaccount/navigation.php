<?php
/**
 * سایدبار «حساب کاربری» — بازنویسیِ کامل قالب پیش‌فرض ووکامرس (نه پچ CSS)،
 * چون طرح مرجع (jluxe.ir/account) یک کارت پروفایل + آیتم‌های آیکون‌دار
 * داره که با <ul><li> ساده‌ی خودِ ووکامرس قابل ساخت نیست.
 *
 * آیتم‌ها همچنان از wc_get_account_menu_items() واقعی میان (نه هاردکد)،
 * پس اگر افزونه‌ای بعداً endpoint جدید اضافه/کم کنه، سایدبار خودش همگام
 * می‌مونه — فقط آیکونش fallback عمومی می‌گیره.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jluxe_account_icons = array(
	'dashboard'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
	'orders'          => '<path d="M20.5 7.3 12 12l-8.5-4.7"/><path d="M12 22V12"/><path d="m20.5 7.3-8.4-4.6a1 1 0 0 0-1 0L3 7.3"/><path d="M3 7.3v9.4a1 1 0 0 0 .5.9l8 4.5a1 1 0 0 0 1 0l8-4.5a1 1 0 0 0 .5-.9V7.3"/>',
	'downloads'       => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/>',
	'edit-address'    => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
	'payment-methods' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
	'edit-account'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
	'customer-logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
);

$jluxe_current_user = wp_get_current_user();

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="jluxe-account-nav woocommerce-MyAccount-navigation" style="width:100%;float:none" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<div class="mb-4 flex items-center gap-3 rounded-2xl border border-border bg-surface p-4">
		<?php echo get_avatar( $jluxe_current_user->ID, 44, '', '', array( 'class' => 'rounded-full' ) ); ?>
		<div class="min-w-0">
			<p class="truncate text-small font-bold text-foreground"><?php echo esc_html( $jluxe_current_user->display_name ); ?></p>
			<p class="truncate text-caption text-text-secondary" dir="ltr"><?php echo esc_html( $jluxe_current_user->user_email ); ?></p>
		</div>
	</div>

	<ul class="flex flex-col gap-1 rounded-2xl border border-border bg-surface p-2">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
			$is_current = wc_is_current_account_menu_item( $endpoint );
			$icon_path  = $jluxe_account_icons[ $endpoint ] ?? '<circle cx="12" cy="12" r="8"/>';
			?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a
					href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
					<?php echo $is_current ? 'aria-current="page"' : ''; ?>
					class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-small font-medium transition-colors <?php echo $is_current ? 'bg-primary/10 text-primary' : 'text-text-secondary hover:bg-muted hover:text-foreground'; ?>"
				>
					<svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon_path; ?></svg>
					<?php echo esc_html( $label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
