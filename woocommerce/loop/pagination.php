<?php
/**
 * صفحه‌بندیِ آرشیوِ محصولات — بازطراحیِ کامل، طبقِ مرجعِ HTML/CSSِ ارسالیِ
 * کاربر از یک سایتِ دیگر (ویجتِ کارت‌مانند: دکمه‌ی «صفحه‌ی قبلی/بعدی» +
 * یک اینپوتِ عددیِ قابل‌ویرایش برای پرشِ مستقیم به هر صفحه‌ای) — جایگزینِ
 * پاژینیشنِ شماره‌ایِ قبلی، فقط برای آرشیوِ محصولات. عمداً کلاسِ خودش
 * (jluxe-shop-pagination) رو داره، نه woocommerce-pagination/page-numbers —
 * اون کلاس‌ها هنوز جای دیگه‌ای (پاژینیشنِ دیدگاه‌های محصول، single-product-
 * reviews.php) زنده و لازمن، پس globals.css دست‌نخورده می‌مونه.
 *
 * override رسمیِ خودِ ووکامرس (کپیِ همین مسیر از پلاگین، مطابقِ مستندِ
 * «Template structure» خودِ ووکامرس) — نه یک هکِ سراسری؛ فقط UI عوض می‌شه،
 * منطقِ خودِ صفحه‌بندی (کوئری/URL، $total/$current/$base) همون چیزیه که
 * خودِ وردپرس/ووکامرس محاسبه می‌کنه، دست‌نخورده از تمپلیتِ اصلی کپی شده.
 *
 * اینپوتِ پرشِ صفحه: مقدارش با اعدادِ فارسی نمایش داده می‌شه (jluxe_fa_digits،
 * هماهنگ با بقیه‌ی سایت) ولی خودِ رفتارِ پرش (assets/js/woocommerce.js) هم
 * اعدادِ فارسی و هم لاتین رو قبول می‌کنه — کاربر مجبور نیست کیبورد عوض کنه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total   = isset( $total ) ? $total : wc_get_loop_prop( 'total_pages' );
$current = max( 1, isset( $current ) ? $current : wc_get_loop_prop( 'current_page' ) );
$base    = isset( $base ) ? $base : esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) );

if ( $total <= 1 ) {
	return;
}

$jluxe_prev_url = $current > 1 ? str_replace( '%#%', (string) ( $current - 1 ), $base ) : '';
$jluxe_next_url = $current < $total ? str_replace( '%#%', (string) ( $current + 1 ), $base ) : '';
?>
<nav
	class="jluxe-shop-pagination mx-auto mt-8 flex max-w-[1296px] items-center justify-between gap-2 rounded-2xl border border-border bg-surface p-2.5 sm:gap-3 sm:p-3"
	aria-label="صفحه‌بندیِ محصولات"
	data-jluxe-pagination-base="<?php echo esc_url( $base ); ?>"
	data-jluxe-pagination-total="<?php echo (int) $total; ?>"
>
	<?php if ( $jluxe_prev_url ) : ?>
		<a href="<?php echo esc_url( $jluxe_prev_url ); ?>" class="flex shrink-0 items-center gap-1.5 rounded-xl px-2.5 py-2 text-small font-medium text-foreground transition-colors hover:bg-muted sm:px-3">
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
			<span class="hidden sm:inline">صفحه قبلی</span>
		</a>
	<?php else : ?>
		<span aria-disabled="true" class="flex shrink-0 cursor-default items-center gap-1.5 rounded-xl px-2.5 py-2 text-small font-medium text-text-muted opacity-50 sm:px-3">
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
			<span class="hidden sm:inline">صفحه قبلی</span>
		</span>
	<?php endif; ?>

	<div class="flex items-center gap-1.5 whitespace-nowrap text-small text-text-secondary">
		<span>صفحه</span>
		<input
			type="text"
			inputmode="numeric"
			data-jluxe-pagination-input
			value="<?php echo esc_attr( jluxe_fa_digits( $current ) ); ?>"
			aria-label="شماره‌ی صفحه"
			class="h-8 w-11 rounded-lg border border-border bg-background text-center text-small font-semibold text-foreground focus:border-primary focus:outline-none"
		/>
		<span>از <?php echo esc_html( jluxe_fa_digits( $total ) ); ?></span>
	</div>

	<?php if ( $jluxe_next_url ) : ?>
		<a href="<?php echo esc_url( $jluxe_next_url ); ?>" class="flex shrink-0 items-center gap-1.5 rounded-xl px-2.5 py-2 text-small font-medium text-foreground transition-colors hover:bg-muted sm:px-3">
			<span class="hidden sm:inline">صفحه بعدی</span>
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
		</a>
	<?php else : ?>
		<span aria-disabled="true" class="flex shrink-0 cursor-default items-center gap-1.5 rounded-xl px-2.5 py-2 text-small font-medium text-text-muted opacity-50 sm:px-3">
			<span class="hidden sm:inline">صفحه بعدی</span>
			<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
		</span>
	<?php endif; ?>
</nav>
