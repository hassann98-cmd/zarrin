<?php
/**
 * چیدمان دوستونیِ صفحه‌ی «حساب کاربری» (سایدبار + محتوا)، مطابق طرح مرجع.
 * navigation.php/dashboard.php سبک‌شون رو خودشون دارن؛ این فایل فقط
 * container گرید رو جایگزین <div class="woocommerce-MyAccount-content">
 * خطیِ پیش‌فرض ووکامرس می‌کنه.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="jluxe-account-layout mx-auto grid max-w-[1180px] gap-6 px-4 py-8 lg:grid-cols-[260px_1fr]">
	<?php do_action( 'woocommerce_account_navigation' ); ?>

	<div class="woocommerce-MyAccount-content min-w-0">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
</div>
