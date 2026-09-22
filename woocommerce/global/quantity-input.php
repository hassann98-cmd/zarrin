<?php
/**
 * ورودی تعداد با دکمه‌های +/-. ساختار پایه (label + input.qty) عیناً از
 * قالب اصلی ووکامرس حفظ شده تا AJAX واقعی سبد (assets/js/frontend/cart.js
 * ووکامرس، روی change شدن input.qty) بدون تغییر کار کنه؛ فقط دکمه‌های +/-
 * (با jluxe/assets/js/woocommerce.js) و استایل دورش اضافه شده.
 *
 * @see woocommerce/templates/global/quantity-input.php (نسخه‌ی اصلی)
 * @version 10.1.0
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: Quantity. */
$label = ! empty( $args['product_name'] ) ? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( $args['product_name'] ) ) : esc_html__( 'Quantity', 'woocommerce' );

// برخی فراخوان‌ها (simple.php، variation-add-to-cart-button.php) خودشون
// دکمه‌ی +/- سفارشی و هم‌استایل با ردیفِ فشرده‌ی «تعداد» دارن؛ اگه این
// قالبِ مشترک هم دکمه‌های خودش رو چاپ کنه، دو جفت +/- روی هم می‌افتن
// (باگ واقعیِ گزارش‌شده: دو دکمه‌ی + کنار هم در صفحه‌ی محصول). با پاس‌دادنِ
// 'jluxe_bare' => true از اون فایل‌ها، این‌جا فقط input خام چاپ می‌شه.
$jluxe_bare = ! empty( $args['jluxe_bare'] );
if ( ! $jluxe_bare ) :
	?>
<div class="quantity jluxe-qty flex h-11 w-fit items-center rounded-full border border-border">
	<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>

	<button type="button" data-jluxe-qty-step="decrease" aria-label="کم کردن تعداد" class="flex size-11 shrink-0 items-center justify-center text-text-secondary transition-all hover:text-foreground active:scale-90">
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
	</button>

	<?php endif; ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $label ); ?></label>
	<?php if ( 'hidden' === $type ) : ?>
		<?php
		/*
		 * وقتی min===max (مثلاً موجودیِ تنوعِ انتخاب‌شده ۱ عدده)، خودِ
		 * ووکامرس نوعِ input رو hidden می‌کنه چون چیزی برای تغییر نیست —
		 * ولی این یعنی مشتری اصلاً نمی‌بینه چند عدد داره می‌خره (باگِ واقعیِ
		 * گزارش‌شده: «عدد باید برگرده»، چون input مخفیه ولی دکمه‌های +/- رو
		 * خودِ فایل‌های فراخوان (simple.php/variation-add-to-cart-button.php)
		 * جدا رندر می‌کنن، نه این تمپلیت). یک span فقط‌خواندنی کنارِ همون
		 * inputِ مخفی (که هنوز مقدار رو submit می‌کنه) نشون داده می‌شه.
		 */
		?>
		<span class="qty w-10 shrink-0 text-center text-small font-medium text-foreground" aria-hidden="true"><?php echo esc_html( jluxe_fa_digits( (string) $input_value ) ); ?></span>
	<?php endif; ?>
	<?php
	/*
	 * input[type=number] فقط رقم لاتین رندر می‌کنه (مرورگر خودش گلیف‌ها رو
	 * می‌کشه، نه CSS/HTML) — برای حالتِ قابل‌تغییر (غیر hidden) یک لایه‌ی
	 * span فارسی دقیقاً روی همون اندازه‌ی input قرار می‌گیره و input زیرش
	 * با color:transparent (در globals.css) نامرئی می‌شه؛ فقط caret دیده
	 * می‌شه. جاوااسکریپت (assets/js/woocommerce.js) این span رو روی هر
	 * تغییرِ input (تایپ دستی، اسپینرِ بومی، یا دکمه‌های +/- بالا) سینک می‌کنه.
	 * عمداً اندازه‌ی ثابتی (کلاس‌های ارتفاع/عرض) به این wrapper داده نمی‌شه — inline-flex
	 * بدونِ اندازه‌ی صریح دقیقاً به سایزِ خودِ input (که بسته به context متفاوته:
	 * فرمِ کامل h-11/w-10 صفحه‌ی سبد، ولی استپرِ فشرده‌ی صفحه‌ی محصول/مودال
	 * w-7) می‌چسبه. اندازه‌ی ثابتِ قبلی باعثِ یک جعبه‌ی بزرگ‌تر از نسخه‌ی
	 * فشرده می‌شد و در سوییچِ بین رنگ‌ها (found_variation) یک پرشِ کوچیک
	 * در قدِ ردیفِ «تعداد» ایجاد می‌کرد — باگِ واقعیِ گزارش‌شده.
	 */
	$jluxe_qty_needs_fa_overlay = 'hidden' !== $type;
	if ( $jluxe_qty_needs_fa_overlay ) :
		?>
		<span class="relative inline-flex shrink-0 items-center justify-center" data-jluxe-qty-fa-wrap>
			<span class="pointer-events-none absolute inset-0 flex items-center justify-center text-small font-medium text-foreground" aria-hidden="true" data-jluxe-qty-fa><?php echo esc_html( jluxe_fa_digits( (string) $input_value ) ); ?></span>
	<?php endif; ?>
	<input
		type="<?php echo esc_attr( $type ); ?>"
		<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
		id="<?php echo esc_attr( $input_id ); ?>"
		class="qty h-11 w-10 shrink-0 border-0 bg-transparent text-center text-small font-medium text-foreground focus:outline-none<?php echo $jluxe_qty_needs_fa_overlay ? ' jluxe-qty-fa-input' : ''; ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $input_value ); ?>"
		aria-label="<?php esc_attr_e( 'Product quantity', 'woocommerce' ); ?>"
		min="<?php echo esc_attr( $min_value ); ?>"
		<?php if ( 0 < $max_value ) : ?>
			max="<?php echo esc_attr( $max_value ); ?>"
		<?php endif; ?>
		<?php if ( ! $readonly ) : ?>
			step="<?php echo esc_attr( $step ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
		<?php endif; ?>
	/>
	<?php if ( $jluxe_qty_needs_fa_overlay ) : ?>
		</span>
	<?php endif; ?>

	<?php if ( ! $jluxe_bare ) : ?>
	<button type="button" data-jluxe-qty-step="increase" aria-label="افزودن تعداد" class="flex size-11 shrink-0 items-center justify-center text-text-secondary transition-all hover:text-foreground active:scale-90">
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
	</button>

	<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
</div>
<?php endif; ?>
