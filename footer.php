<?php
/**
 * Site footer.
 */
?>
	<?php
	/*
	 * R88 — پوستهٔ فوتر سمتِ سرور. ستونِ «نمادهای سایت» مستقیم همین‌جا، داخلِ
	 * گریدِ اصلی و جلوی «شرکت»، چاپ می‌شود (تا <script> رسمیِ اینماد هنگامِ پارسِ
	 * HTML اجرا شود و بدونِ JS هم دیده شود). آیلندِ React فقط محتوای خودش را با
	 * portal در سه جایگاهِ data-jluxe-footer-slot می‌گذارد — هیچ جابه‌جاییِ DOM،
	 * polling یا MutationObserver در کار نیست (پیش‌تر بود: R87 همین را پیدا کرد).
	 */
	if ( jluxe_should_render_site_footer() ) :
		$jluxe_footer_bg = jluxe_footer_background_style();
		?>
	<footer id="jluxe-footer-root" class="flow-root" data-jluxe-footer<?php echo '' !== $jluxe_footer_bg ? ' style="' . esc_attr( $jluxe_footer_bg ) . '"' : ''; ?>>
		<div class="jluxe-footer-content-wrap mx-auto mt-4 mb-24 w-full max-w-[1320px] px-3 md:px-4 md:mb-6">
			<div class="jluxe-footer-shell overflow-hidden rounded-2xl border border-border bg-muted/40">
				<div data-jluxe-footer-slot="features"></div>
				<div class="jluxe-footer-content-grid grid gap-8 p-5 sm:grid-cols-2 lg:grid-cols-4">
					<div data-jluxe-footer-slot="columns" style="display:contents"></div>
					<?php jluxe_render_site_trust_badges(); ?>
				</div>
				<div data-jluxe-footer-slot="bottom"></div>
			</div>
		</div>
		<div data-jluxe-island="footer"></div>
	</footer>
	<?php endif; ?>

	<?php if ( ! jluxe_is_woocommerce_account_page() && ! empty( jluxe_get_setting( 'footer.show_gradient_strip', false ) ) ) : ?>
		<div aria-hidden="true" style="height:6px;width:100%;background:linear-gradient(90deg, hsl(var(--primary)) 0%, hsl(var(--accent)) 50%, hsl(var(--secondary)) 100%);"></div>
	<?php endif; ?>


	<?php
	$jluxe_mobile_nav_island = 'floating' === jluxe_get_setting( 'mobile.nav_variant', 'classic' )
		? 'mobile-nav-floating'
		: 'mobile-nav';
	?>
	<div data-jluxe-island="<?php echo esc_attr( $jluxe_mobile_nav_island ); ?>"></div>

	<?php
	// این آیلند فقط وقتی داخلش واقعاً چیزی رندر می‌کنه که سرور enabled=true
	// برگردونده باشه (هم فعال، هم کلید API واقعاً ست شده) — وگرنه null
	// برمی‌گردونه و هیچ اثری روی صفحه نداره. بدون شرط اضافه این‌جا چون خودِ
	// کامپوننت این چک رو انجام می‌ده (src/islands/AiAssistant.tsx).
	?>
	<div data-jluxe-island="ai-assistant"></div>

<?php wp_footer(); ?>
</body>
</html>
