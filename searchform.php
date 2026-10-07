<?php
/**
 * فرم جست‌وجوی زرین — RTL، هدف لمسی ۴۸px، آیکون درون‌خطی (بدون درخواست شبکه).
 */
$jluxe_search_home = home_url( '/' );
if ( ! class_exists( 'JLuxe_Search_Form_Counter' ) ) {
	class JLuxe_Search_Form_Counter { public static int $instance = 0; }
}
?>
<form role="search" method="get" class="jluxe-search" action="<?php echo esc_url( $jluxe_search_home ); ?>">
	<?php $jluxe_search_id = 'jluxe-search-' . ++JLuxe_Search_Form_Counter::$instance; ?>
	<label class="sr-only" for="<?php echo esc_attr( $jluxe_search_id ); ?>">جست‌وجو در فروشگاه</label>
	<input
		id="<?php echo esc_attr( $jluxe_search_id ); ?>"
		type="search"
		class="jluxe-search-field"
		name="s"
		value="<?php echo get_search_query(); ?>"
		placeholder="نام محصول را بنویسید…"
		aria-label="جست‌وجو در فروشگاه"
	/>
	<button type="submit" class="jluxe-search-submit" aria-label="جست‌وجو">
		<?php
		if ( function_exists( 'jluxe_icon' ) ) {
			jluxe_icon( 'search', 'size-5' );
		}
		?>
	</button>
</form>
