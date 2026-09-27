<?php
/**
 * Site footer.
 */
?>
	<div id="jluxe-footer-root" data-jluxe-island="footer"></div>

	<?php
	/*
	 * عمداً بلافاصله بعدِ #jluxe-footer-root و سمتِ PHP (نه داخلِ خودِ
	 * آیلندِ React) — چون <script> فقط وقتی مرورگر داره خودِ HTML رو پارس
	 * می‌کنه اجرا می‌شه، نه وقتی از طریقِ innerHTML (کاری که React با
	 * dangerouslySetInnerHTML می‌کرد) تزریق بشه. توضیح کامل: تابع
	 * jluxe_render_site_trust_badges() در inc/theme-settings.php.
	 */
	jluxe_render_site_trust_badges();
	?>

	<?php if ( ! empty( jluxe_get_setting( 'footer.show_gradient_strip', false ) ) ) : ?>
		<div aria-hidden="true" style="height:6px;width:100%;background:linear-gradient(90deg, hsl(var(--primary)) 0%, hsl(var(--accent)) 50%, hsl(var(--secondary)) 100%);"></div>
	<?php endif; ?>


	<div data-jluxe-island="mobile-nav"></div>

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
