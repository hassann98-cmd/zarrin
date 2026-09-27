<?php
/**
 * چیدمانِ «کلاسیک» صفحه‌ی محصول — بازطراحیِ کامل طبقِ نمونهٔ واقعیِ jluxe.ir
 * (کارتِ سفیدِ بزرگ با سه ستون: گالری/اطلاعات/جعبه‌ی خرید، نوارِ چسبانِ
 * بخش‌ها، سه بخشِ شماره‌دار: توضیحات/مشخصات/دیدگاه‌ها، سوالاتِ متداول و
 * محصولاتِ مرتبط).
 *
 * قراردادهای ثابت (تست‌های R31/R38/R40/R44 به آن‌ها وابسته‌اند):
 *  - data-jluxe-layout="classic" روی عنصر ریشه
 *  - jluxe_print_breadcrumb_jsonld( $product )
 *  - jluxe_render_sticky_add_to_cart( $product ) در انتها
 *  - <div id="reviews" class="jluxe-panel mt-6"> برای بخش دیدگاه‌ها
 *
 * هوک‌های واقعیِ ووکامرس دست نخورده‌اند: فرمِ تنوع (variations_form)،
 * دکمه‌ی واقعی add-to-cart، قیمت از wc_price (قانونِ «عدد سپس واحد»).
 */

defined( 'ABSPATH' ) || exit;

global $product;

$rating       = (float) $product->get_average_rating();
$review_count = (int) $product->get_review_count();

/* مشخصات: همه‌ی ویژگی‌های نمایان، با علامتِ «تنوع‌ساز» */
$cp3_specs     = array();
$cp3_variation_specs = array();
foreach ( array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' ) as $attribute ) {
	$values = $attribute->is_taxonomy()
		? wp_list_pluck( wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) ), 'name' )
		: $attribute->get_options();
	$row = array(
		'label' => wc_attribute_label( $attribute->get_name() ),
		'value' => implode( '، ', $values ),
	);
	if ( $attribute->get_variation() ) {
		$cp3_variation_specs[] = $row;
	} else {
		$cp3_specs[] = $row;
	}
}

$jluxe_is_variable          = $product->is_type( 'variable' );
$jluxe_variation_attributes = array();
$jluxe_available_variations = array();
if ( $jluxe_is_variable ) {
	$jluxe_variation_attributes = $product->get_variation_attributes();
	$jluxe_available_variations = jluxe_available_variations_for_form( $product );
	wp_enqueue_script( 'wc-add-to-cart-variation' );
}

$jluxe_gallery_ids = $product->get_gallery_image_ids();
$jluxe_main_id     = $product->get_image_id();
if ( $jluxe_main_id ) {
	array_unshift( $jluxe_gallery_ids, $jluxe_main_id );
}
$jluxe_gallery_ids = array_values( array_unique( array_filter( $jluxe_gallery_ids ) ) );
if ( empty( $jluxe_gallery_ids ) ) {
	$jluxe_gallery_ids = array( 0 );
}

$cp3_breadcrumbs = jluxe_breadcrumb_items( $product );
$cp3_short_desc  = $product->get_short_description();
$cp3_faq_items   = function_exists( 'jluxe_get_product_faq_items' ) ? jluxe_get_product_faq_items( $product->get_id() ) : array();
$cp3_stock_qty   = $product->managing_stock() ? $product->get_stock_quantity() : null;

/* خلاصه‌ی رأی‌ها: مثبت (۴–۵)، بی‌طرف (۳)، منفی (۱–۲) */
$cp3_counts = array( 'pos' => 0, 'neu' => 0, 'neg' => 0 );
foreach ( (array) $product->get_rating_counts() as $cp3_stars => $cp3_c ) {
	$cp3_stars = (int) $cp3_stars;
	if ( $cp3_stars >= 4 ) {
		$cp3_counts['pos'] += (int) $cp3_c;
	} elseif ( 3 === $cp3_stars ) {
		$cp3_counts['neu'] += (int) $cp3_c;
	} else {
		$cp3_counts['neg'] += (int) $cp3_c;
	}
}
?>
<style>
/* ====== CP3 — چیدمانِ کلاسیکِ بازطراحی‌شده (منبعِ مرجع: jluxe.ir) ====== */
.jluxe-cp3{background:#f7f8fa}
.jluxe-cp3 .cp3-bc{display:flex;align-items:center;gap:8px;font-size:13px;padding:14px 0 6px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-bc a{color:hsl(var(--text-secondary));transition:color .15s}
.jluxe-cp3 .cp3-bc a:hover{color:hsl(var(--primary))}
.jluxe-cp3 .cp3-bc svg{opacity:.55}
.jluxe-cp3 .cp3-bc .is-current{color:hsl(var(--foreground));overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px}
@media(min-width:768px){.jluxe-cp3 .cp3-bc .is-current{white-space:normal;max-width:none}}
.jluxe-cp3 .cp3-card{position:relative;background:hsl(var(--surface));border-radius:18px;box-shadow:0 3px 5px rgba(0,0,0,.05);padding:20px;margin-top:5px}
.jluxe-cp3 .cp3-grid{display:flex;flex-direction:column}
@media(min-width:768px){.jluxe-cp3 .cp3-grid{flex-direction:row}}
.jluxe-cp3 .cp3-gallery{padding-inline-end:0}
@media(min-width:768px){.jluxe-cp3 .cp3-gallery{flex:none;width:460px;padding-inline-end:40px;border-inline-end:1px dashed hsl(var(--panel-border))}}
.jluxe-cp3 .cp3-zoom{position:relative;height:344px;border-radius:30px;background:linear-gradient(180deg,#f8f9fb,#f3f5f9);margin-bottom:16px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:crosshair;box-shadow:0 1px 2px rgba(16,24,40,.05)}
.jluxe-cp3 .cp3-zoom::after{content:'';position:absolute;inset:0;border-radius:inherit;box-shadow:inset 0 0 0 1px rgba(16,24,40,.04);pointer-events:none}
@media(min-width:768px){.jluxe-cp3 .cp3-zoom{height:420px;width:420px;max-width:100%}}
.jluxe-cp3 .cp3-zoom img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:opacity .45s ease,transform .3s ease-out;will-change:transform;opacity:0}
.jluxe-cp3 .cp3-zoom img.is-loaded{opacity:1}
@media (prefers-reduced-motion: reduce){.jluxe-cp3 .cp3-zoom img{transition:none;opacity:1}}
.jluxe-cp3 .cp3-thumbs{display:flex;gap:8px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;padding:2px;scroll-snap-type:x mandatory}
.jluxe-cp3 .cp3-thumbs::-webkit-scrollbar{display:none}
.jluxe-cp3 .cp3-thumb{scroll-snap-align:start;flex:none;width:96px;height:96px;border-radius:15px;background:hsl(var(--muted));padding:4px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .15s,background .15s,transform .15s,box-shadow .15s}
.jluxe-cp3 .cp3-thumb img{width:100%;height:100%;object-fit:cover;border-radius:11px}
.jluxe-cp3 .cp3-thumb:hover{background:hsl(var(--muted-foreground)/.15);transform:translateY(-2px)}
.jluxe-cp3 .cp3-thumb.is-active{border-color:hsl(var(--primary));box-shadow:0 4px 12px rgba(16,24,40,.07)}
.jluxe-cp3 .cp3-thumbsrow{display:flex;align-items:center;gap:8px}
.jluxe-cp3 .cp3-thumbs{flex:1 1 auto;min-width:0}
.jluxe-cp3 .cp3-tarrow{flex:none;width:34px;height:34px;border-radius:12px;border:1px solid hsl(var(--panel-border));background:hsl(var(--surface));color:hsl(var(--text-secondary));display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;font-family:inherit}
.jluxe-cp3 .cp3-tarrow:hover:not(:disabled){background:hsl(var(--primary)/.1);color:hsl(var(--primary));border-color:hsl(var(--primary)/.4)}
.jluxe-cp3 .cp3-tarrow:disabled{opacity:.3;cursor:default}
@media (prefers-reduced-motion: reduce){.jluxe-cp3 .cp3-tarrow{transition:none}}
/* باگِ واقعی (R58): display:inline-flexِ کلاس، اتریبیوتِ hidden مرورگر را خنثی می‌کند
(استایلِ نویسنده همیشه بر UA استایل مقدم است) — فلش‌ها حتی بدونِ سرریز دیده می‌شدند. */
.jluxe-cp3 .cp3-tarrow[hidden]{display:none}
@media (prefers-reduced-motion: reduce){.jluxe-cp3 .cp3-thumb{transition:none}}
.jluxe-cp3 .cp3-info{flex:1 1 0;min-width:min(250px,100%);padding-inline-start:0;margin-top:16px}
@media(min-width:768px){.jluxe-cp3 .cp3-info{padding-inline-start:16px;margin-top:0}}
.jluxe-cp3 .cp3-title{font-size:20px;font-weight:800;line-height:1.6;color:hsl(var(--foreground));margin:0 0 6px}
.jluxe-cp3 .cp3-meta{display:flex;align-items:center;gap:8px;font-size:12px;color:hsl(var(--text-secondary));padding-top:12px}
.jluxe-cp3 .cp3-meta svg{color:hsl(var(--primary));flex:none}
.jluxe-cp3 .cp3-meta a{color:inherit}
.jluxe-cp3 .cp3-meta a:hover{color:hsl(var(--primary))}
.jluxe-cp3 .cp3-short{font-size:13px;line-height:1.8;color:hsl(var(--text-secondary));padding-top:10px}
.jluxe-cp3 .cp3-short .cp3-short-body{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin:0}
.jluxe-cp3 .cp3-short.is-open .cp3-short-body{-webkit-line-clamp:unset}
.jluxe-cp3 .cp3-short-toggle{color:hsl(var(--primary));cursor:pointer;font-size:13px;margin-inline-start:6px;background:none;border:none;padding:0;font-family:inherit}
.jluxe-cp3 .cp3-short-toggle:hover{text-decoration:underline}
.jluxe-cp3 .cp3-varlabel{font-weight:700;color:hsl(var(--foreground));margin-bottom:8px}
.jluxe-cp3 .cp3-pills{display:flex;flex-wrap:wrap;gap:8px}
.jluxe-cp3 .cp3-pill{position:relative;display:inline-flex;align-items:center;min-width:64px;justify-content:center;padding:6px 14px;border-radius:14px;border:1px solid hsl(var(--panel-border));background:transparent;color:hsl(var(--foreground));font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;font-family:inherit}
.jluxe-cp3 .cp3-pill:hover{border-color:hsl(var(--primary)/.5)}
.jluxe-cp3 .cp3-pill.is-active{background:hsl(var(--primary));border-color:hsl(var(--primary));color:hsl(var(--primary-foreground));font-weight:700}
/* گزینه‌ای که ووکامرس در update_variation_values کارت می‌کند — مثل مرجع: کم‌رنگ + خط‌خورده */
.jluxe-cp3 .cp3-pill.is-disabled{opacity:.45;text-decoration:line-through;cursor:not-allowed}
.jluxe-cp3 .cp3-pill.is-disabled:hover{border-color:hsl(var(--panel-border))}
.jluxe-cp3 .cp3-pills .reset_variations{display:inline-flex;align-items:center;align-self:center;padding:6px 12px;border-radius:12px;border:1px dashed hsl(var(--panel-border));background:none;color:hsl(var(--text-muted));font-size:12px;text-decoration:none;cursor:pointer;visibility:hidden;font-family:inherit;transition:all .15s}
.jluxe-cp3 .cp3-pills .reset_variations:hover{color:#e0405f;border-color:rgba(224,64,95,.4)}
.jluxe-cp3 .cp3-chips-label{font-weight:700;color:hsl(var(--foreground));margin-bottom:10px}
.jluxe-cp3 .cp3-chips{display:flex;gap:8px;flex-wrap:wrap}
.jluxe-cp3 .cp3-chip{background:hsl(var(--panel));border:1px solid hsl(var(--panel-border));border-radius:12px;padding:10px 12px;min-width:96px;max-width:230px}
.jluxe-cp3 .cp3-chip small{display:block;font-size:10px;color:hsl(var(--text-muted));margin-bottom:4px}
.jluxe-cp3 .cp3-chip strong{font-size:12px;color:hsl(var(--foreground));line-height:1.6}
.jluxe-cp3 .cp3-side{padding-inline:0;margin-top:20px}
@media(min-width:768px){.jluxe-cp3 .cp3-side{width:25%;flex:none;margin-top:0;padding-inline-start:16px;position:sticky;top:calc(88px + var(--wp-admin--admin-bar--height,0px));align-self:flex-start}}
.jluxe-cp3 .cp3-sidebox{background:hsl(var(--panel));border:1px solid hsl(var(--panel-border));border-radius:20px;padding:16px;display:flex;flex-direction:column;gap:2px;font-size:12px}
.jluxe-cp3 .cp3-sidebox .cp3-sidehead{font-size:14px;font-weight:800;color:hsl(var(--foreground));margin-bottom:6px}
.jluxe-cp3 .cp3-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;font-size:13px;color:hsl(var(--text-secondary))}
.jluxe-cp3 .cp3-row .cp3-rowlab{display:inline-flex;align-items:center;gap:6px}
.jluxe-cp3 .cp3-row .cp3-rowlab svg{color:hsl(var(--primary));flex:none}
.jluxe-cp3 .cp3-row .cp3-rowval{display:flex;align-items:center;gap:4px;font-weight:600;color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-row .is-ok{color:hsl(var(--success))}
.jluxe-cp3 .cp3-row .is-out{color:hsl(var(--error))}
.jluxe-cp3 .cp3-stockwarn{display:flex;align-items:center;gap:10px;background:hsl(var(--primary)/.07);color:hsl(var(--primary));border:none;border-radius:12px;padding:10px 14px;font-size:12px;font-weight:600;margin-top:8px}
.jluxe-cp3 .cp3-sku{color:hsl(var(--text-muted));font-size:12px;margin:14px 0}
.jluxe-cp3 .cp3-dashed{height:1px;border-bottom:1px dashed hsl(var(--panel-border));margin-bottom:12px}
.jluxe-cp3 .cp3-price{font-size:24px;font-weight:800;color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-price del{font-size:18px;font-weight:500;color:hsl(var(--text-muted));margin-inline-end:8px;opacity:.85}
.jluxe-cp3 .cp3-price .jluxe-toman-glyph,.jluxe-sticky-cta-price .jluxe-toman-glyph{display:inline-flex;align-items:center;opacity:.75;vertical-align:middle}
.jluxe-cp3 .cp3-price .jluxe-toman-glyph svg,.jluxe-sticky-cta-price .jluxe-toman-glyph svg{display:block}
.jluxe-sticky-cta-price .jluxe-toman-glyph svg{width:14px;height:auto}
.jluxe-sticky-cta-price del{font-size:13px;font-weight:500;color:hsl(var(--text-muted));margin-inline-end:6px}
.jluxe-cp3 .cp3-price ins{text-decoration:none}
.jluxe-cp3 .cp3-addrow{display:flex;gap:8px;margin-top:14px}
.jluxe-cp3 .cp3-addrow .single_add_to_cart_button,.jluxe-cp3 .cp3-addrow .cp3-add-simple{flex:1;min-height:48px;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:hsl(var(--primary)) !important;color:hsl(var(--primary-foreground)) !important;border:none !important;border-radius:16px !important;font-size:15px !important;font-weight:700 !important;cursor:pointer;padding:12px 16px !important;box-shadow:0 6px 16px hsl(var(--primary)/.25) !important;transition:background .15s,transform .12s;font-family:inherit;line-height:1.2;width:100% !important;max-width:100%;min-width:0;text-align:center}
.jluxe-cp3 .cp3-addrow .single_add_to_cart_button::after,.jluxe-cp3 .cp3-addrow .cp3-add-simple::after{content:'';width:18px;height:18px;flex:none;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 5v14M5 12h14' stroke='black' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center/contain;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 5v14M5 12h14' stroke='black' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center/contain}
.jluxe-cp3 .cp3-addrow .single_add_to_cart_button:hover{background:hsl(var(--primary-hover))}
.jluxe-cp3 .cp3-addrow .single_add_to_cart_button:disabled{opacity:.5;cursor:not-allowed}
.jluxe-cp3 .cp3-heart{flex:none;width:48px;min-height:48px;border-radius:16px;background:hsl(var(--muted));border:none;color:hsl(var(--text-secondary));display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s}
.jluxe-cp3 .cp3-heart:hover,.jluxe-cp3 .cp3-heart[aria-pressed="true"]{color:#e0405f}
.jluxe-cp3 .cp3-qty{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:12px;color:hsl(var(--text-secondary))}
.jluxe-cp3 .cp3-qty .quantity input{width:76px;min-height:44px;text-align:center;border:1px solid hsl(var(--panel-border));border-radius:12px;background:hsl(var(--surface));color:hsl(var(--foreground));font-family:inherit}
.jluxe-cp3 .cp3-fabs{display:flex;gap:10px;margin-top:16px}
.jluxe-cp3 .cp3-fab{width:40px;height:40px;border-radius:10px;background:hsl(var(--panel));border:1px solid hsl(var(--panel-border));color:hsl(var(--primary));display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .12s,background .15s}
.jluxe-cp3 .cp3-fab:hover{background:hsl(var(--primary)/.1)}
.jluxe-cp3 .cp3-fab:active{transform:scale(.95)}
/* بلاکِ پیش‌فرضِ ووکامرس برای محصولاتِ متغیر — بازنویسیِ کاملِ چیدمان در
ناحیهٔ خرید: کلاس‌های real وو (single_variation_wrap / variations_button /
quantity / single_add_to_cart_button) با اولویت و !important به‌شکلِ
ردیفِ مرتبِ [تعداد | دکمه | قلب] درمی‌آیند؛ float و اندازه‌های خودِ وو
خنثی می‌شوند تا با هیچ افزونه‌ای هم به‌هم نریزد. */
.jluxe-cp3 .cp3-addrow .woocommerce-variation,.jluxe-cp3 .cp3-addrow .woocommerce-variation-price,.jluxe-cp3 .cp3-addrow .woocommerce-variation-availability{display:none !important}
.jluxe-cp3 .cp3-addrow .single_variation_wrap{flex:1 1 auto;min-width:0;margin:0 !important;display:flex;flex-direction:column}
.jluxe-cp3 .cp3-addrow .woocommerce-variation-add-to-cart{display:flex !important;align-items:center;gap:8px;flex:1 1 auto;min-width:0;float:none !important;margin:0 !important;flex-wrap:nowrap !important}
.jluxe-cp3 .cp3-addrow .woocommerce-variation-add-to-cart::before{display:none !important}
/* استپرِ تعداد، کامپوننتِ خودِ پوسته است (quantity-input.php: .quantity.jluxe-qty
با دکمه‌های − / +) — این‌جا فقط خنثی‌سازیِ float/margin خودِ وو؛ هرگز ظاهرش
را بازنویسی نمی‌کنیم تا با کلاس‌های rounded-full خودش تضاد پیدا نکند. */
.jluxe-cp3 .cp3-addrow .quantity{float:none !important;margin:0 !important;flex:none}
@media(min-width:768px){.jluxe-cp3 .cp3-addrow .quantity{display:none !important}}
@media(max-width:767px){.jluxe-cp3 .cp3-addrow{display:none !important}}
.jluxe-cp3 .cp3-addrow .woocommerce-variation-add-to-cart .single_add_to_cart_button{float:none !important;flex:1 1 auto !important;width:auto;max-width:100%;min-width:0;display:inline-flex !important;align-items:center;justify-content:center;gap:8px}
.jluxe-cp3 .cp3-addrow .woocommerce-variation-add-to-cart .single_add_to_cart_button::after{content:'';width:18px;height:18px;flex:none;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 5v14M5 12h14' stroke='black' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center/contain;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 5v14M5 12h14' stroke='black' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat center/contain}
.jluxe-cp3 .cp3-addrow .woocommerce-variation-add-to-cart.woocommerce-variation-add-to-cart-disabled .single_add_to_cart_button{opacity:.5;cursor:not-allowed;pointer-events:none}
/* پیامِ «هیچ تنوعی با این ترکیب هم‌خوانی ندارد» که ووکامرس بعد از .single_variation تزریق می‌کند */
.jluxe-cp3 .cp3-addrow .wc-no-matching-variations{margin:8px 0 0;padding:8px 14px;border-radius:10px;background:hsl(var(--primary)/.06);border:1px dashed hsl(var(--primary)/.35);color:hsl(var(--foreground));font-size:12.5px;line-height:1.9;display:flex;float:none !important}
.jluxe-cp3 .cp3-side .woocommerce-variation-price,.jluxe-cp3 .cp3-side .woocommerce-variation-availability{display:none}
/* نوارِ چسبانِ بخش‌ها */
.jluxe-cp3 .cp3-nav{position:sticky;z-index:30;top:calc(72px + var(--wp-admin--admin-bar--height,0px) + 6px);display:flex;align-items:center;justify-content:space-between;gap:12px;background:hsl(var(--surface)/.92);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid hsl(var(--panel-border));border-radius:20px;padding:10px 12px;box-shadow:0 8px 30px rgba(0,0,0,.08);margin-top:32px}
.jluxe-cp3 .cp3-nav-cur{display:flex;flex-direction:column;gap:2px;min-width:96px}
.jluxe-cp3 .cp3-nav-cur small{font-size:10px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-nav-cur strong{font-size:15px;font-weight:800;color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-nav-arrows{display:flex;gap:2px}
.jluxe-cp3 .cp3-nav-arrows button{height:26px;width:26px;border:none;background:none;color:hsl(var(--text-muted));border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.jluxe-cp3 .cp3-nav-arrows button:hover:not(:disabled){background:hsl(var(--primary)/.1);color:hsl(var(--primary))}
.jluxe-cp3 .cp3-nav-arrows button:disabled{opacity:.3;cursor:default}
.jluxe-cp3 .cp3-tabs{display:flex;gap:4px;overflow-x:auto;scrollbar-width:none}
.jluxe-cp3 .cp3-tabs::-webkit-scrollbar{display:none}
.jluxe-cp3 .cp3-tab{white-space:nowrap;border:none;background:none;border-radius:12px;padding:8px 14px;font-size:13px;font-weight:500;color:hsl(var(--text-muted));cursor:pointer;transition:all .2s;font-family:inherit}
.jluxe-cp3 .cp3-tab:hover{color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-tab.is-active{background:transparent;color:hsl(var(--primary));font-weight:700}
/* بخش‌های شماره‌دار */
.jluxe-cp3 .cp3-sec{margin-top:20px;scroll-margin-top:130px}
.jluxe-cp3 .cp3-sechead{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.jluxe-cp3 .cp3-num{display:inline-flex;align-items:center;color:hsl(var(--primary));font-size:15px;font-weight:800;line-height:1}
.jluxe-cp3 .cp3-sechead h3{font-size:18px;font-weight:800;color:hsl(var(--foreground));margin:0}
.jluxe-cp3 .cp3-sechead .line{height:1px;flex:1;background:linear-gradient(to left,hsl(var(--panel-border)),transparent)}
.jluxe-cp3 .cp3-panelcard{border:1px solid hsl(var(--panel-border));background:hsl(var(--surface));border-radius:22px;box-shadow:0 2px 14px rgba(0,0,0,.04);padding:20px}
@media(min-width:768px){.jluxe-cp3 .cp3-panelcard{padding:28px}}
.jluxe-cp3 .cp3-descwrap{position:relative;max-height:600px;overflow:hidden}
.jluxe-cp3 .cp3-descwrap.is-open{max-height:none}
.jluxe-cp3 .cp3-descfade{position:absolute;inset-inline:0;bottom:0;height:128px;background:linear-gradient(to top,hsl(var(--surface)) 20%,hsl(var(--surface)/.95) 55%,transparent);display:flex;align-items:flex-end;justify-content:center;padding-bottom:16px}
.jluxe-cp3 .cp3-descwrap.is-open .cp3-descfade{display:none}
.jluxe-cp3 .cp3-descbtn{display:inline-flex;align-items:center;gap:8px;background:hsl(var(--primary));color:hsl(var(--primary-foreground));border:none;border-radius:16px;padding:10px 24px;font-size:14px;font-weight:500;cursor:pointer;box-shadow:0 8px 20px hsl(var(--primary)/.3);font-family:inherit}
.jluxe-cp3 .cp3-descbody{color:hsl(var(--text-secondary));line-height:2;font-size:14px;text-align:justify}
.jluxe-cp3 .cp3-descbody h2,.jluxe-cp3 .cp3-descbody h3{color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-descbody img{max-width:100%;height:auto;border-radius:16px}
.jluxe-cp3 .cp3-descbody table{width:100%;border-collapse:collapse}
.jluxe-cp3 .cp3-descbody td,.jluxe-cp3 .cp3-descbody th{border:1px solid hsl(var(--panel-border));padding:8px 12px}
/* جدول مشخصات */
.jluxe-cp3 .cp3-specgroup{border:1px solid hsl(var(--panel-border));border-radius:16px;overflow:hidden;margin-bottom:20px}
.jluxe-cp3 .cp3-specgroup:last-child{margin-bottom:0}
.jluxe-cp3 .cp3-specgroup-head{display:flex;align-items:center;gap:10px;background:linear-gradient(to left,hsl(var(--primary)/.1),transparent);padding:12px 20px}
.jluxe-cp3 .cp3-specgroup-head .dot{width:6px;height:16px;border-radius:99px;background:hsl(var(--primary))}
.jluxe-cp3 .cp3-specgroup-head h4{font-size:15px;font-weight:800;color:hsl(var(--foreground));margin:0}
.jluxe-cp3 .cp3-specrow{display:flex;align-items:baseline;justify-content:space-between;gap:24px;padding:13px 20px;border-top:1px solid hsl(var(--panel-border));font-size:14px;transition:background .15s}
.jluxe-cp3 .cp3-specrow:first-of-type{border-top:none}
.jluxe-cp3 .cp3-specrow:hover{background:hsl(var(--panel)/.6)}
.jluxe-cp3 .cp3-specrow .k{color:hsl(var(--text-muted));flex:none}
.jluxe-cp3 .cp3-specrow .v{color:hsl(var(--foreground));font-weight:500;text-align:end}
/* خلاصه‌ی امتیاز دیدگاه‌ها */
.jluxe-cp3 .cp3-rate{display:grid;grid-template-columns:1fr;gap:20px;border:1px solid hsl(var(--panel-border));border-radius:24px;padding:24px;margin-bottom:20px}
@media(min-width:768px){.jluxe-cp3 .cp3-rate{grid-template-columns:1fr 220px}}
.jluxe-cp3 .cp3-rate-score{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;border-radius:16px;background:linear-gradient(135deg,#fff8ec,#fff3e6);padding:24px 0}
.jluxe-cp3 .cp3-rate-score .score{font-size:48px;font-weight:900;color:hsl(var(--foreground));line-height:1}
.jluxe-cp3 .cp3-rate-score .stars{color:#f5a623;letter-spacing:2px;font-size:15px}
.jluxe-cp3 .cp3-rate-score .of{font-size:12px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-rate-bars{display:flex;flex-direction:column;justify-content:center;gap:12px}
.jluxe-cp3 .cp3-ratebar{display:flex;align-items:center;gap:12px;font-size:13px}
.jluxe-cp3 .cp3-ratebar .lbl{flex:none;font-weight:700}
.jluxe-cp3 .cp3-ratebar .lbl i{font-style:normal;font-weight:500;font-size:11px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-ratebar .track{height:10px;flex:1;border-radius:99px;background:hsl(var(--panel));overflow:hidden}
.jluxe-cp3 .cp3-ratebar .fill{height:100%;border-radius:99px}
.jluxe-cp3 .cp3-ratebar .pct{flex:none;font-size:12px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-ratebar.pos .fill{background:#10b981}
.jluxe-cp3 .cp3-ratebar.pos .lbl{color:#047857}
.jluxe-cp3 .cp3-ratebar.neu .fill{background:#fbbf24}
.jluxe-cp3 .cp3-ratebar.neu .lbl{color:#b45309}
.jluxe-cp3 .cp3-ratebar.neg .fill{background:#f43f5e}
.jluxe-cp3 .cp3-ratebar.neg .lbl{color:#be123c}
.jluxe-cp3 .cp3-reviewtoolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.jluxe-cp3 .cp3-sortsel{display:inline-flex;align-items:center;gap:2px;background:hsl(var(--panel));border:1px solid hsl(var(--panel-border));border-radius:999px;padding:4px}
.jluxe-cp3 .cp3-sortsel button{border:1px solid transparent;background:none;border-radius:999px;padding:7px 14px;font-size:12px;color:hsl(var(--text-muted));cursor:pointer;transition:all .15s;font-family:inherit}
.jluxe-cp3 .cp3-sortsel button:hover{color:hsl(var(--foreground))}
.jluxe-cp3 .cp3-sortsel button.is-active{background:hsl(var(--surface));border-color:hsl(var(--panel-border));color:hsl(var(--foreground));font-weight:700}
.jluxe-cp3 .cp3-wishline{display:inline-flex;align-items:center;gap:6px;background:none;border:none;padding:0;color:hsl(var(--foreground));font-size:12px;cursor:pointer;font-family:inherit}
.jluxe-cp3 .cp3-wishline svg{color:hsl(var(--primary))}
.jluxe-cp3 .cp3-wishline:hover,.jluxe-cp3 .cp3-wishline[aria-pressed="true"]{color:#e0405f}
.jluxe-cp3 .cp3-reviewbtn{display:inline-flex;align-items:center;gap:8px;background:hsl(var(--primary));color:hsl(var(--primary-foreground));border:none;border-radius:999px;padding:10px 20px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;font-family:inherit}
.jluxe-cp3 .cp3-reviewbtn:hover{opacity:.9}
/* کارتِ خلاصه‌ی هوش مصنوعی — هم‌زبان با مرجع (بنفش، آیکونِ چپ) */
.jluxe-cp3 .jluxe-ai-review-summary{display:flex;flex-direction:row-reverse;gap:12px;align-items:flex-start;padding:20px;margin-bottom:20px;border:1px solid #ede9fe;border-radius:24px;background:linear-gradient(135deg,#f5f3ff,#fdf4ff)}
.jluxe-cp3 .jluxe-ai-review-summary-icon{background:linear-gradient(135deg,#8b5cf6,#d946ef);color:#fff;border-radius:16px;width:44px;height:44px}
.jluxe-cp3 .jluxe-ai-review-summary-badge{color:hsl(var(--foreground));font-weight:800;font-size:15px}
.jluxe-cp3 .jluxe-ai-review-summary-sub{display:block;color:#7c3aed;font-size:12px;margin:2px 0 8px}
.jluxe-cp3 .jluxe-ai-review-summary-text{font-size:14px;line-height:1.9;color:hsl(var(--text-secondary))}
/* R66: دیدگاه‌ها — کارت‌های جمع‌وجورِ ۱۶px (استایل کامل در storefront.css) */
/* سوالات متداول */
.jluxe-cp3 .cp3-faq{border:1px solid hsl(var(--panel-border));background:hsl(var(--surface));border-radius:24px;padding:20px;box-shadow:0 2px 14px rgba(0,0,0,.04)}
.jluxe-cp3 .cp3-faq-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
.jluxe-cp3 .cp3-faq-head .ic{width:48px;height:48px;flex:none;color:hsl(var(--foreground));display:inline-flex;align-items:center;justify-content:center}
.jluxe-cp3 .cp3-faq-head h3{font-size:20px;font-weight:800;color:hsl(var(--foreground));margin:0}
.jluxe-cp3 .cp3-faq-head span{font-size:16px;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-faq-list{background:hsl(var(--panel));border-radius:20px;padding:4px 18px}
.jluxe-cp3 .cp3-faq-item{border-bottom:1px solid hsl(var(--panel-border))}
.jluxe-cp3 .cp3-faq-item:last-child{border-bottom:none}
.jluxe-cp3 .cp3-faq-item summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 4px;font-size:14px;font-weight:500;color:hsl(var(--foreground));cursor:pointer;list-style:none}
.jluxe-cp3 .cp3-faq-item summary::-webkit-details-marker{display:none}
.jluxe-cp3 .cp3-faq-item summary .chev{transition:transform .2s;color:hsl(var(--text-muted))}
.jluxe-cp3 .cp3-faq-item[open] summary .chev{transform:rotate(90deg)}
.jluxe-cp3 .cp3-faq-item .a{padding:0 4px 16px;font-size:14px;line-height:2;color:hsl(var(--text-secondary))}
/* محصولات مرتبط */
.jluxe-cp3 .cp3-relhead{display:flex;align-items:center;gap:14px;margin:32px 0 8px}
.jluxe-cp3 .cp3-relhead .ic{width:48px;height:48px;border-radius:12px;background:hsl(var(--surface));border:1px solid hsl(var(--panel-border));color:hsl(var(--primary));display:inline-flex;align-items:center;justify-content:center}
.jluxe-cp3 .cp3-relhead h3{font-size:22px;font-weight:800;color:hsl(var(--foreground));margin:0}
.jluxe-cp3 .cp3-related ul.products{display:grid !important;grid-template-columns:repeat(2,1fr);gap:12px;margin:16px 0 0 !important;padding:0 !important}
@media(min-width:768px){.jluxe-cp3 .cp3-related ul.products{grid-template-columns:repeat(3,1fr)}}
@media(min-width:1024px){.jluxe-cp3 .cp3-related ul.products{grid-template-columns:repeat(4,1fr)}}
.jluxe-cp3 .cp3-related ul.products li.product{width:100% !important;float:none !important;margin:0 !important;background:linear-gradient(180deg,hsl(var(--surface)),hsl(var(--panel)/.5));border:1px solid hsl(var(--panel-border));border-radius:24px;overflow:hidden;padding:12px !important}
.jluxe-cp3 .cp3-related ul.products li.product img{border-radius:18px;background:#f8f9fb;mix-blend-mode:multiply;transition:transform .7s ease}
@media (prefers-reduced-motion: no-preference){.jluxe-cp3 .cp3-related ul.products li.product:hover img{transform:scale(1.06)}}
.jluxe-cp3 .cp3-related .woocommerce-loop-product__title{font-size:13px !important;font-weight:500 !important;color:hsl(var(--text-secondary)) !important;padding:0 4px}
.jluxe-cp3 .cp3-related .price{padding:0 4px}
.jluxe-cp3 .cp3-related .button{display:none}
</style>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'jluxe-cp3', $product ); ?> data-jluxe-layout="classic">
	<div class="mx-auto w-full max-w-[1296px] px-3 md:px-5 py-2">

		<nav aria-label="مسیر صفحه" class="cp3-bc">
			<?php
			$cp3_bc_items   = $cp3_breadcrumbs;
			$cp3_last_label = (string) end( $cp3_bc_items )[0];
			array_pop( $cp3_bc_items );
			foreach ( $cp3_bc_items as $cp3_bc ) :
				?>
				<div class="flex items-center gap-2">
					<a href="<?php echo esc_url( (string) $cp3_bc[1] ); ?>"><?php echo esc_html( (string) $cp3_bc[0] ); ?></a>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6s-6 4.419-6 6s6 6 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
			<?php endforeach; ?>
			<span class="is-current"><?php echo esc_html( $cp3_last_label ); ?></span>
		</nav>
		<?php jluxe_print_breadcrumb_jsonld( $product ); ?>

		<?php if ( $jluxe_is_variable ) : ?>
			<?php $cp3_variations_json = wc_esc_json( wp_json_encode( $jluxe_available_variations ) ); ?>
			<form class="variations_form cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $cp3_variations_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<?php endif; ?>

		<div class="cp3-card">
			<div class="cp3-grid">

				<!-- گالری -->
				<div class="cp3-gallery" data-cp3-gallery>
					<div class="cp3-zoom" data-cp3-zoom-box>
						<?php $cp3_main_url = wp_get_attachment_image_url( $jluxe_gallery_ids[0], 'full' ); ?>
						<img src="<?php echo esc_url( $cp3_main_url ?: wc_placeholder_img_src( 'full' ) ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" data-cp3-zoom-img loading="eager" fetchpriority="high" />
					</div>
					<?php if ( count( $jluxe_gallery_ids ) > 1 ) : ?>
						<div class="cp3-thumbsrow">
							<button type="button" class="cp3-tarrow" data-cp3-thumbs-prev aria-label="تصاویر قبلی" disabled><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
							<div class="cp3-thumbs">
							<?php foreach ( $jluxe_gallery_ids as $cp3_i => $cp3_att ) : ?>
								<button type="button" class="cp3-thumb<?php echo 0 === $cp3_i ? ' is-active' : ''; ?>" data-cp3-thumb data-full="<?php echo esc_url( wp_get_attachment_image_url( $cp3_att, 'full' ) ?: '' ); ?>" aria-label="نمایش عکس <?php echo esc_attr( (string) ( $cp3_i + 1 ) ); ?>">
									<img src="<?php echo esc_url( wp_get_attachment_image_url( $cp3_att, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ) ); ?>" alt="" loading="lazy" />
								</button>
							<?php endforeach; ?>
						</div>
							<button type="button" class="cp3-tarrow" data-cp3-thumbs-next aria-label="تصاویر بعدی"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
						</div>
					<?php endif; ?>
				</div>

				<!-- اطلاعات -->
				<div class="cp3-info">
					<h1 class="cp3-title"><?php the_title(); ?></h1>

					<div class="cp3-meta">
						<button type="button" class="cp3-wishline" data-jluxe-wishlist-toggle="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-pressed="false">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19.463 3.994c-2.682-1.645-5.023-.982-6.429.074c-.576.433-.864.65-1.034.65s-.458-.217-1.034-.65C9.56 3.012 7.219 2.349 4.537 3.994C1.018 6.153.222 13.274 8.34 19.284C9.886 20.427 10.659 21 12 21s2.114-.572 3.66-1.717c8.118-6.008 7.322-13.13 3.803-15.289" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<span>۱۰۰٪ شاید این محصول را هم پسندید</span>
						</button>
					</div>

					<?php if ( $review_count > 0 ) : ?>
						<div class="cp3-meta">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m13.728 3.444l1.76 3.549c.24.494.88.968 1.42 1.058l3.189.535c2.04.343 2.52 1.835 1.05 3.307l-2.48 2.5c-.42.423-.65 1.24-.52 1.825l.71 3.095c.56 2.45-.73 3.397-2.88 2.117l-2.99-1.785c-.54-.322-1.43-.322-1.98 0L8.019 21.43c-2.14 1.28-3.44.322-2.88-2.117l.71-3.095c.13-.585-.1-1.402-.52-1.825l-2.48-2.5C1.39 10.42 1.86 8.929 3.899 8.586l3.19-.535c.53-.09 1.17-.564 1.41-1.058l1.76-3.549c.96-1.925 2.52-1.925 3.47 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<a href="#sec-reviews"><?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?> نظر</a>
						</div>
					<?php endif; ?>

					<?php if ( '' !== trim( (string) $cp3_short_desc ) ) : ?>
						<div class="cp3-short" data-cp3-short>
							<div class="cp3-short-body"><?php echo wp_kses_post( wpautop( $cp3_short_desc ) ); ?></div>
							<button type="button" class="cp3-short-toggle" data-cp3-short-toggle aria-expanded="false">مشاهده همه</button>
						</div>
					<?php endif; ?>

				<?php if ( $jluxe_is_variable && ! empty( $jluxe_variation_attributes ) ) : ?>
					<?php
					/* دو باگِ واقعیِ گزارش‌شده این‌جا رفع می‌شود:
					 * ۱) get_variation_attributes() برای ویژگی‌های تاکسونومی «نامک»
					 *    (مثل cochek) را برمی‌گرداند، نه نامِ نمایشیِ ترم (مثل «کوچک») —
					 *    پس با get_term_by نامِ واقعیِ ترم چاپ می‌شود.
					 * ۲) ممکن است گزینه‌هایی در فهرست باشند که هیچ تنوعِ واقعیِ
					 *    ساخته‌شده‌ای پشتشان نیست؛ همان منبعِ data-product_variations
					 *    فیلترشان می‌کند تا کلیک روی قرصِ بی‌تنوع، دکمهٔ خرید را
					 *    گمراه نکند (همان قراردادِ سواچ‌های چیدمانِ پیش‌فرض). */
					$cp3_valid_options = array();
					foreach ( (array) $jluxe_available_variations as $cp3_variation ) {
						foreach ( (array) ( $cp3_variation['attributes'] ?? array() ) as $cp3_attr_key => $cp3_attr_value ) {
							$cp3_valid_name = str_replace( 'attribute_', '', (string) $cp3_attr_key );
							if ( '' === (string) $cp3_attr_value ) {
								$cp3_valid_options[ $cp3_valid_name ]['*'] = true;
							} else {
								$cp3_valid_options[ $cp3_valid_name ][ (string) $cp3_attr_value ] = true;
							}
						}
					}
					$cp3_option_label = static function ( string $cp3_value, string $cp3_name ) use ( $product ): string {
						if ( taxonomy_exists( $cp3_name ) ) {
							$cp3_term = get_term_by( 'slug', $cp3_value, $cp3_name );
							if ( $cp3_term && ! is_wp_error( $cp3_term ) ) {
								return (string) $cp3_term->name;
							}
							return $cp3_value;
						}
						return (string) apply_filters( 'woocommerce_variation_option_name', $cp3_value, null, $cp3_name, $product );
					};
					?>
					<?php $cp3_attr_keys = array_keys( $jluxe_variation_attributes ); ?>
					<?php foreach ( $jluxe_variation_attributes as $cp3_attr_name => $cp3_attr_options ) : ?>
						<?php
						/* R65: تنوعِ ناموجود غیرقابلِ انتخاب — همان قراردادِ سواچ‌ها؛
						   گزینهٔ فقط-ناموجود با is-disabled رندر می‌شود (حذف نمی‌شود). */
						$cp3_stocky_options = array();
						foreach ( (array) $jluxe_available_variations as $cp3_stocky_variation ) {
							if ( empty( $cp3_stocky_variation['is_in_stock'] ) ) {
								continue;
							}
							foreach ( (array) ( $cp3_stocky_variation['attributes'] ?? array() ) as $cp3_stocky_key => $cp3_stocky_value ) {
								$cp3_stocky_name = str_replace( 'attribute_', '', (string) $cp3_stocky_key );
								if ( '' === (string) $cp3_stocky_value ) {
									$cp3_stocky_options[ $cp3_stocky_name ]['*'] = true;
								} else {
									$cp3_stocky_options[ $cp3_stocky_name ][ (string) $cp3_stocky_value ] = true;
								}
							}
						}

						$cp3_attr_slug = sanitize_title( $cp3_attr_name );
						if ( ! empty( $cp3_valid_options[ $cp3_attr_name ] ) && empty( $cp3_valid_options[ $cp3_attr_name ]['*'] ) ) {
							$cp3_attr_options = array_values( array_filter( (array) $cp3_attr_options, fn( $cp3_opt ) => isset( $cp3_valid_options[ $cp3_attr_name ][ (string) $cp3_opt ] ) ) );
						}
						?>
						<div style="margin-top:16px">
							<div class="cp3-varlabel"><?php echo esc_html( wc_attribute_label( $cp3_attr_name ) ); ?> مورد نظر را انتخاب کنید:</div>
							<div class="cp3-pills" data-cp3-pills="<?php echo esc_attr( $cp3_attr_slug ); ?>">
								<?php foreach ( $cp3_attr_options as $cp3_opt ) : ?>
									<?php $cp3_opt = (string) $cp3_opt; ?>
									<?php
										$cp3_oos = ( empty( $cp3_stocky_options[ $cp3_attr_name ]['*'] ) && ! empty( $cp3_valid_options[ $cp3_attr_name ] ) && empty( $cp3_stocky_options[ $cp3_attr_name ][ $cp3_opt ] ) );
									?>
									<button type="button" class="cp3-pill<?php echo $cp3_oos ? ' is-disabled' : ''; ?>" data-value="<?php echo esc_attr( $cp3_opt ); ?>"<?php echo $cp3_oos ? ' disabled="disabled" aria-disabled="true"' : ''; ?>><?php echo esc_html( $cp3_option_label( $cp3_opt, $cp3_attr_name ) ); ?></button>
								<?php endforeach; ?>
								<?php if ( end( $cp3_attr_keys ) === $cp3_attr_name ) : ?>
									<a class="reset_variations" href="#" aria-label="پاک کردن انتخاب‌ها">حذف انتخاب</a>
								<?php endif; ?>
							</div>
							<div class="variations">
							<select name="attribute_<?php echo esc_attr( $cp3_attr_slug ); ?>" data-attribute_name="attribute_<?php echo esc_attr( $cp3_attr_slug ); ?>" data-show_option_none="yes" data-cp3-select="<?php echo esc_attr( $cp3_attr_slug ); ?>" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none" aria-label="<?php echo esc_attr( wc_attribute_label( $cp3_attr_name ) ); ?>">
								<option value=""><?php echo esc_html( sprintf( 'انتخاب %s', wc_attribute_label( $cp3_attr_name ) ) ); ?></option>
								<?php foreach ( $cp3_attr_options as $cp3_opt ) : ?>
									<?php $cp3_opt = (string) $cp3_opt; ?>
									<option value="<?php echo esc_attr( $cp3_opt ); ?>"><?php echo esc_html( $cp3_option_label( $cp3_opt, $cp3_attr_name ) ); ?></option>
								<?php endforeach; ?>
							</select>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

					<?php
					$cp3_brand_row = null;
					foreach ( $cp3_specs as $cp3_spec ) {
						if ( false !== mb_strpos( $cp3_spec['label'], 'برند' ) ) {
							$cp3_brand_row = $cp3_spec;
							break;
						}
					}
					$cp3_chip_specs = array_slice( array_diff_key( $cp3_specs, $cp3_brand_row ? array( $cp3_brand_row => 0 ) : array() ) + ( $cp3_brand_row ? array( $cp3_brand_row ) : array() ), 0, 3 );
					?>
					<?php if ( ! empty( $cp3_chip_specs ) ) : ?>
						<div style="margin-top:16px">
							<div class="cp3-chips-label">ویژگی ها :</div>
							<div class="cp3-chips">
								<?php foreach ( $cp3_chip_specs as $cp3_chip ) : ?>
									<div class="cp3-chip">
										<small><?php echo esc_html( $cp3_chip['label'] ); ?> :</small>
										<strong><?php echo esc_html( wp_trim_words( $cp3_chip['value'], 8, '…' ) ); ?></strong>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- جعبه‌ی خرید -->
				<div class="cp3-side">
					<div class="cp3-sidebox">
						<div class="cp3-sidehead">مشخصات</div>
						<div class="cp3-row">
							<span class="cp3-rowlab"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21.4 11.05l-7.5-7.52c-.7-.7-1.9-.7-2.6 0l-8.1 8.1c-.4.4-.6.94-.6 1.5V19c0 1.1.9 2 2 2h5.9c.56 0 1.1-.22 1.5-.62l9.4-9.4c.7-.7.7-1.83 0-2.53ZM5 11.7L13.5 3.2M8 21l9.5-9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>زمان ارسال</span>
							<span class="cp3-rowval" style="color:hsl(var(--primary))">ارسال سریع</span>
						</div>
						<div class="cp3-row">
							<span class="cp3-rowlab is-ok"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="m8.5 12.2 2.4 2.4 4.6-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>وضعیت موجودی</span>
							<?php if ( $product->is_in_stock() ) : ?>
								<span class="cp3-rowval is-ok">موجود</span>
							<?php else : ?>
								<span class="cp3-rowval is-out">ناموجود</span>
							<?php endif; ?>
						</div>
						<?php if ( null !== $cp3_stock_qty && $cp3_stock_qty > 0 && $cp3_stock_qty <= 10 ) : ?>
							<div class="cp3-stockwarn">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 9V14M12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21ZM12.0498 17V17.1L11.9502 17.1002V17H12.0498Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<span>فقط <?php echo esc_html( jluxe_fa_digits( $cp3_stock_qty ) ); ?> عدد موجود است</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $product->get_sku() ) : ?>
						<div class="cp3-sku">کد محصول: <?php echo esc_html( $product->get_sku() ); ?></div>
					<?php endif; ?>
					<div class="cp3-dashed max-md:hidden" aria-hidden="true"></div>

					<div class="cp3-price" data-cp3-price><?php echo jluxe_price_kses( jluxe_reference_price_html( $product->get_price_html() ) ); ?></div>

					<div class="cp3-addrow">
						<?php if ( $jluxe_is_variable ) : ?>
							<div class="single_variation_wrap">
							<?php do_action( 'woocommerce_before_single_variation' ); ?>
							<?php do_action( 'woocommerce_single_variation' ); ?>
							<?php do_action( 'woocommerce_after_single_variation' ); ?>
							</div>
						<?php else : ?>
							<?php woocommerce_template_single_add_to_cart(); ?>
						<?php endif; ?>
						<button type="button" class="cp3-heart" data-jluxe-wishlist-toggle="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-pressed="false" aria-label="افزودن به علاقه‌مندی‌ها">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19.463 3.994c-2.682-1.645-5.023-.982-6.429.074c-.576.433-.864.65-1.034.65s-.458-.217-1.034-.65C9.56 3.012 7.219 2.349 4.537 3.994C1.018 6.153.222 13.274 8.34 19.284C9.886 20.427 10.659 21 12 21s2.114-.572 3.66-1.717c8.118-6.008 7.322-13.13 3.803-15.289" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					</div>

					<div class="cp3-fabs">
						<button type="button" class="cp3-fab" data-jluxe-wishlist-toggle="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-pressed="false" aria-label="علاقه‌مندی">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4h6v16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2" opacity=".16" fill="currentColor"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7m4-16h1a2 2 0 0 1 2 2v1m0 10v1a2 2 0 0 1-2 2h-1m3-9v2M12 2v20"/></svg>
						</button>
						<button type="button" class="cp3-fab" data-jluxe-share aria-label="اشتراک‌گذاری">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10.002 3c-2.947.032-4.591.22-5.684 1.312C3 5.63 3 7.752 3 11.997s0 6.366 1.318 7.685S7.76 21 12.003 21s6.366 0 7.685-1.319c1.093-1.092 1.28-2.737 1.312-5.685M14 3h4c1.414 0 2.121 0 2.56.44C21 3.878 21 4.585 21 6v4m-1-6l-9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<a class="cp3-fab" href="#sec-reviews" aria-label="دیدگاه‌ها">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21H4.6c-.56 0-.84 0-1.054-.109a1 1 0 0 1-.437-.437C3 20.24 3 19.96 3 19.4V3m17 5l-3.919 4.183a.5.5 0 0 1-.612.085L11.53 9.268a.5.5 0 0 0-.612.085L7 13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</a>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $jluxe_is_variable ) : ?>
		</form>
		<?php endif; ?>

		<!-- نوارِ چسبانِ بخش‌ها -->
		<div class="cp3-nav" data-cp3-nav>
			<div class="cp3-nav-cur">
				<small>پرش به قسمت</small>
				<strong data-cp3-nav-label>توضیحات</strong>
			</div>
			<nav class="cp3-tabs" aria-label="بخش‌های صفحه محصول">
				<button type="button" class="cp3-tab is-active" data-cp3-tab="sec-desc">توضیحات</button>
				<button type="button" class="cp3-tab" data-cp3-tab="sec-specs">مشخصات</button>
				<button type="button" class="cp3-tab" data-cp3-tab="sec-reviews">دیدگاه‌ها</button>
			</nav>
			<div class="cp3-nav-arrows">
				<button type="button" data-cp3-nav-prev aria-label="بخش قبلی" disabled>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m18 15-6-6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" data-cp3-nav-next aria-label="بخش بعدی">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
			</div>
		</div>

		<div class="mt-5 flex flex-col gap-5">
			<!-- ۱) توضیحات -->
			<section id="sec-desc" class="cp3-sec" data-cp3-section="توضیحات">
				<div class="cp3-sechead">
					<span class="cp3-num" dir="ltr">1</span>
					<h3>توضیحات</h3>
					<span class="line" aria-hidden="true"></span>
				</div>
				<div class="cp3-panelcard">
					<div class="cp3-descwrap" data-cp3-descwrap>
						<div class="cp3-descbody"><?php the_content(); ?></div>
						<div class="cp3-descfade">
							<button type="button" class="cp3-descbtn" data-cp3-descbtn aria-expanded="false">
								<span>مشاهده بیشتر</span>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 9l-7 7-7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</button>
						</div>
					</div>
				</div>
			</section>

			<!-- ۲) مشخصات -->
			<?php if ( ! empty( $cp3_specs ) || ! empty( $cp3_variation_specs ) ) : ?>
			<section id="sec-specs" class="cp3-sec" data-cp3-section="مشخصات">
				<div class="cp3-sechead">
					<span class="cp3-num" dir="ltr">2</span>
					<h3>مشخصات</h3>
					<span class="line" aria-hidden="true"></span>
				</div>
				<div class="cp3-panelcard">
					<?php if ( ! empty( $cp3_specs ) ) : ?>
						<div class="cp3-specgroup">
							<div class="cp3-specgroup-head">
								<span class="dot" aria-hidden="true"></span>
								<h4>مشخصات فنی</h4>
							</div>
							<?php foreach ( $cp3_specs as $cp3_spec ) : ?>
								<div class="cp3-specrow">
									<span class="k"><?php echo esc_html( $cp3_spec['label'] ); ?></span>
									<span class="v"><?php echo esc_html( $cp3_spec['value'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $cp3_variation_specs ) ) : ?>
						<div class="cp3-specgroup">
							<div class="cp3-specgroup-head">
								<span class="dot" aria-hidden="true"></span>
								<h4>سایر مشخصات</h4>
							</div>
							<?php foreach ( $cp3_variation_specs as $cp3_spec ) : ?>
								<div class="cp3-specrow">
									<span class="k"><?php echo esc_html( $cp3_spec['label'] ); ?></span>
									<span class="v"><?php echo esc_html( $cp3_spec['value'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>

			<!-- ۳) دیدگاه‌ها -->
			<section id="sec-reviews" class="cp3-sec" data-cp3-section="دیدگاه‌ها">
				<div class="cp3-sechead">
					<span class="cp3-num" dir="ltr">3</span>
					<h3>دیدگاه‌ها</h3>
					<span class="line" aria-hidden="true"></span>
				</div>
				<div id="reviews" class="jluxe-panel mt-6">
					<?php if ( $review_count > 0 ) : ?>
						<div class="cp3-rate">
							<div class="cp3-rate-bars">
								<?php
								$cp3_total = max( 1, $review_count );
								foreach ( array(
									'pos' => array( 'مثبت', $cp3_counts['pos'] ),
									'neu' => array( 'بی‌طرف', $cp3_counts['neu'] ),
									'neg' => array( 'منفی', $cp3_counts['neg'] ),
								) as $cp3_key => $cp3_bar ) :
									$cp3_pct = (int) round( $cp3_bar[1] * 100 / $cp3_total );
									?>
									<div class="cp3-ratebar <?php echo esc_attr( $cp3_key ); ?>">
										<span class="lbl"><?php echo esc_html( $cp3_bar[0] ); ?> <i dir="ltr">(<?php echo esc_html( jluxe_fa_digits( $cp3_bar[1] ) ); ?>)</i></span>
										<div class="track"><div class="fill" style="width:<?php echo esc_attr( (string) $cp3_pct ); ?>%"></div></div>
										<span class="pct"><?php echo esc_html( jluxe_fa_digits( $cp3_pct ) ); ?>٪</span>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="cp3-rate-score">
								<div class="score"><?php echo esc_html( jluxe_fa_digits( number_format_i18n( $rating, 1 ) ) ); ?></div>
								<div class="stars" dir="ltr" aria-hidden="true">★★★★★</div>
								<div class="of">از <?php echo esc_html( jluxe_fa_digits( $review_count ) ); ?> امتیاز</div>
							</div>
						</div>
					<?php endif; ?>
				<?php jluxe_render_review_insights( (int) $product->get_id() ); ?>
				<div class="cp3-reviewtoolbar">
					<div class="cp3-sortsel" role="group" aria-label="مرتب‌سازی دیدگاه‌ها" data-cp3-sort>
						<button type="button" class="is-active" data-sort="newest" aria-pressed="true">جدیدترین</button>
						<button type="button" data-sort="best" aria-pressed="false">بیشترین امتیاز</button>
						<button type="button" data-sort="worst" aria-pressed="false">کمترین امتیاز</button>
					</div>
					<a class="cp3-reviewbtn" href="#review_form_wrapper">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						ثبت دیدگاه
					</a>
				</div>
				<?php
				/* امتیازِ هر دیدگاه (متای استانداردِ ووکامرس) به‌شکل کلاس می‌آید تا مرتب‌سازیِ سمتِ مرورگر ممکن باشد */
				add_filter( 'comment_class', function ( array $classes, $cp3_class, $cp3_comment_id ) {
					$cp3_rating = (int) get_comment_meta( (int) $cp3_comment_id, 'rating', true );
					if ( $cp3_rating > 0 ) { $classes[] = 'jluxe-rating-' . $cp3_rating; }
					return $classes;
				}, 10, 3 );
				comments_template();
				?>
				</div>
			</section>

			<!-- سوالات متداول -->
			<?php if ( ! empty( $cp3_faq_items ) ) : ?>
				<section class="cp3-faq" aria-labelledby="cp3-faq-title">
					<div class="cp3-faq-head">
						<div>
							<h3 id="cp3-faq-title">سوالات متداول</h3>
							<span>شاید سوال تو هم باشه</span>
						</div>
						<span class="ic" aria-hidden="true">
							<svg width="34" height="34" viewBox="0 0 24 24" fill="none"><g stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="M14.17 20.89c4.184-.277 7.516-3.657 7.79-7.9c.053-.83.053-1.69 0-2.52c-.274-4.242-3.606-7.62-7.79-7.899a33 33 0 0 0-4.34 0c-4.184.278-7.516 3.657-7.79 7.9a20 20 0 0 0 0 2.52c.1 1.545.783 2.976 1.588 4.184c.467.845.159 1.9-.328 2.823c-.35.665-.526.997-.385 1.237c.14.24.455.248 1.084.263c1.245.03 2.084-.322 2.75-.813c.377-.279.566-.418.696-.434s.387.09.899.3c.46.19.995.307 1.485.34c1.425.094 2.914.094 4.342 0Z"/><path stroke-linecap="round" d="M10.5 9.538C10.5 8.688 11.172 8 12 8s1.5.689 1.5 1.538c0 .307-.087.592-.238.832c-.448.714-1.262 1.396-1.262 2.245V13"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15h.009"/></g></svg>
						</span>
					</div>
					<div class="cp3-faq-list">
					<?php foreach ( $cp3_faq_items as $cp3_faq ) : ?>
						<?php
						$cp3_q = trim( (string) ( $cp3_faq['question'] ?? '' ) );
						$cp3_a = trim( (string) ( $cp3_faq['answer'] ?? '' ) );
						if ( '' === $cp3_q || '' === $cp3_a ) { continue; }
						?>
						<details class="cp3-faq-item">
							<summary>
								<span><?php echo esc_html( $cp3_q ); ?></span>
								<svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15.5 19l-7-7 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</summary>
							<div class="a"><?php echo esc_html( $cp3_a ); ?></div>
						</details>
					<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<!-- محصولات مرتبط -->
		<?php
		$cp3_related_args = apply_filters(
			'woocommerce_output_related_products_args',
			array(
				'posts_per_page' => 4,
				'columns'        => 4,
			)
		);
		?>
		<div class="cp3-related" data-cp3-related>
			<div class="cp3-relhead">
				<span class="ic" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M2.5 7.5v6c0 3.771 0 5.657 1.172 6.828S6.729 21.5 10.5 21.5h3c3.771 0 5.657 0 6.828-1.172S21.5 17.271 21.5 13.5v-6M3.87 5.315L2.5 7.5h19l-1.252-2.087c-.854-1.423-1.28-2.134-1.969-2.524c-.687-.389-1.517-.389-3.176-.389h-6.15c-1.623 0-2.435 0-3.113.375c-.678.376-1.109 1.064-1.97 2.44M12 7.5v-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
				<h3>محصولات مرتبط</h3>
			</div>
			<?php woocommerce_output_related_products(); ?>
		</div>

		<div style="height:70px" aria-hidden="true"></div>
	</div>
</div>
<?php
/* R60 — باگِ واقعیِ گزارش‌شده («بعد از افزودن به سبد، محصولاتِ پیشنهادی را نمایش نمی‌دهد»):
مودالِ «اضافه خرید» (R47) فقط در تمپلیتِ modern رندر می‌شد؛ چیدمانِ classic
(پیش‌فرضِ سایت) markup را هرگز نداشت → window.jluxeOpenSuggestedProductsModal
تعریف نمی‌شد و بعدِ افزودنِ موفقِ AJAX هیچ مودالی باز نمی‌شد. همان گاردِ
تمپلیتِ modern، این‌جا هم بیرونِ <form> (بعد از بستنِ آن در بالا): */
if ( $product->is_purchasable() && function_exists( 'jluxe_render_suggested_products_modal' ) ) {
	jluxe_render_suggested_products_modal( $product );
}
?>
<?php jluxe_render_sticky_add_to_cart( $product ); ?>

<script>
( function () {
	'use strict';
	var root = document.querySelector( '.jluxe-cp3' );
	if ( ! root ) { return; }

	/* گالری: تامبنیل + زومِ زیر موس */
	var zoomBox = root.querySelector( '[data-cp3-zoom-box]' );
	var zoomImg = root.querySelector( '[data-cp3-zoom-img]' );
	var motionOk = ! window.matchMedia || ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	function cp3MarkLoaded() { if ( zoomImg ) { zoomImg.classList.add( 'is-loaded' ); } }
	if ( zoomImg ) {
		if ( zoomImg.complete && zoomImg.naturalWidth ) { cp3MarkLoaded(); }
		zoomImg.addEventListener( 'load', cp3MarkLoaded );
	}
	root.querySelectorAll( '[data-cp3-thumb]' ).forEach( function ( thumb ) {
		thumb.addEventListener( 'click', function () {
			root.querySelectorAll( '[data-cp3-thumb]' ).forEach( function ( t ) { t.classList.remove( 'is-active' ); } );
			thumb.classList.add( 'is-active' );
			thumb.scrollIntoView( { behavior: motionOk ? 'smooth' : 'auto', block: 'nearest', inline: 'nearest' } );
			if ( zoomImg && thumb.getAttribute( 'data-full' ) ) {
				zoomImg.classList.remove( 'is-loaded' );
				zoomImg.src = thumb.getAttribute( 'data-full' );
				if ( zoomImg.complete && zoomImg.naturalWidth ) { cp3MarkLoaded(); }
			}
		} );
	} );
	if ( zoomBox && zoomImg && motionOk ) {
		zoomBox.addEventListener( 'mousemove', function ( e ) {
			var rect = zoomBox.getBoundingClientRect();
			var x = ( ( e.clientX - rect.left ) / rect.width ) * 100;
			var y = ( ( e.clientY - rect.top ) / rect.height ) * 100;
			zoomImg.style.transformOrigin = x + '% ' + y + '%';
			zoomImg.style.transform = 'scale(1.8)';
		} );
		zoomBox.addEventListener( 'mouseleave', function () {
			zoomImg.style.transform = 'scale(1)';
		} );
	}

	/* قرص‌های تنوع ↔ سلکتِ واقعی ووکامرس */
	root.querySelectorAll( '[data-cp3-pills]' ).forEach( function ( pillGroup ) {
		var slug = pillGroup.getAttribute( 'data-cp3-pills' );
		var select = root.querySelector( 'select[data-cp3-select="' + slug + '"]' );
		if ( ! select ) { return; }
		function sync( value ) {
			pillGroup.querySelectorAll( '.cp3-pill' ).forEach( function ( pill ) {
				pill.classList.toggle( 'is-active', pill.getAttribute( 'data-value' ) === value );
			} );
		}
		pillGroup.querySelectorAll( '.cp3-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var v = pill.getAttribute( 'data-value' );
				// گزینه‌ای که ووکامرس فیلتر کرده (option.disabled / حذف‌شده) انتخاب نمی‌شود — دقیقاً مثل خودِ select.
				var opt = select.querySelector( 'option[value="' + String( v ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ) + '"]' );
				if ( ! opt || opt.disabled ) { return; }
				select.value = v;
				sync( select.value );
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
		select.addEventListener( 'change', function () { sync( select.value ); } );
	} );

	/* سینکِ قیمتِ جعبه‌ی خرید با تنوعِ انتخاب‌شده.
	باگِ واقعی (R55): تم jquery-core را defer می‌کند (functions.php) — پس در
	لحظهٔ اجرای همین اسکریپتِ درون‌خطی، window.jQuery هنوز تعریف نشده و این
	بلاک هرگز اجرا نمی‌شد؛ نتیجه: با انتخابِ تنویع، قیمتِ جعبه هیچ‌وقت عوض
	نمی‌شد و قرص‌های فیلترشده هم خط نمی‌خوردند. راه‌حل: بایندهای jQuery تا
	DOMContentLoaded (بعد از اجرای همهٔ deferها) عقب می‌افتند. */
	var priceBox = root.querySelector( '[data-cp3-price]' );
	var form = root.querySelector( 'form.variations_form' );
	function jluxeBindJqParts() {
		if ( ! window.jQuery || ! priceBox || ! form ) { return; }
		if ( form.getAttribute( 'data-cp3-jq-bound' ) === '1' ) { return; }
		form.setAttribute( 'data-cp3-jq-bound', '1' );
		var defaultPrice = priceBox.innerHTML;
		window.jQuery( form ).on( 'found_variation', function ( ev, variation ) {
			if ( variation && variation.price_html ) { priceBox.innerHTML = variation.price_html; }
		} ).on( 'reset_data', function () { priceBox.innerHTML = defaultPrice; } );
		// قرص‌هایی که update_variation_valuesِ ووکامرس فیلتر می‌کند خط می‌خورند و دیگر انتخاب نمی‌شوند.
		window.jQuery( form ).on( 'woocommerce_update_variation_values', function () {
			root.querySelectorAll( '[data-cp3-pills]' ).forEach( function ( pillGroup ) {
				var slug = pillGroup.getAttribute( 'data-cp3-pills' );
				var sel = root.querySelector( 'select[data-cp3-select="' + slug + '"]' );
				if ( ! sel ) { return; }
				Array.prototype.forEach.call( sel.options, function ( opt ) {
					if ( ! opt.value ) { return; }
					var pill = pillGroup.querySelector( '.cp3-pill[data-value="' + String( opt.value ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ) + '"]' );
					if ( pill ) { pill.classList.toggle( 'is-disabled', !! opt.disabled ); }
				} );
			} );
		} );
	}
	jluxeBindJqParts();
	if ( ! window.jQuery ) { document.addEventListener( 'DOMContentLoaded', jluxeBindJqParts ); }

	/* فلش‌های چپ/راستِ نوارِ تامبنیل: برای تصاویری که در عرضِ صفحه جا نمی‌شوند.
	در RTL مرورگر scrollLeft منفی است؛ قدرمطلقِ آن مختصاتِ جهت‌بی‌طرف می‌دهد. */
	var thumbsStrip = root.querySelector( '.cp3-thumbs' );
	var thumbsRow = root.querySelector( '.cp3-thumbsrow' );
	if ( thumbsStrip && thumbsRow ) {
		var tPrev = thumbsRow.querySelector( '[data-cp3-thumbs-prev]' );
		var tNext = thumbsRow.querySelector( '[data-cp3-thumbs-next]' );
		var tMax = function () { return Math.max( 0, thumbsStrip.scrollWidth - thumbsStrip.clientWidth ); };
		var tPos = function () { return Math.abs( thumbsStrip.scrollLeft ); };
		var tUpdate = function () {
			var over = tMax() > 2;
			if ( tPrev ) { tPrev.hidden = ! over; }
			if ( tNext ) { tNext.hidden = ! over; }
			if ( ! over ) { return; }
			if ( tPrev ) { tPrev.disabled = tPos() <= 2; }
			if ( tNext ) { tNext.disabled = tPos() >= tMax() - 2; }
		};
		var tGo = function ( dir ) {
			var rtl = ! ( window.getComputedStyle && getComputedStyle( thumbsStrip ).direction !== 'rtl' );
			var amount = Math.max( 120, thumbsStrip.clientWidth * 0.8 ) * dir;
			thumbsStrip.scrollBy( { left: rtl ? -amount : amount, behavior: motionOk ? 'smooth' : 'auto' } );
		};
		if ( tPrev ) { tPrev.addEventListener( 'click', function () { tGo( -1 ); } ); }
		if ( tNext ) { tNext.addEventListener( 'click', function () { tGo( 1 ); } ); }
		thumbsStrip.addEventListener( 'scroll', function () { window.requestAnimationFrame( tUpdate ); }, { passive: true } );
		window.addEventListener( 'resize', tUpdate );
		tUpdate();
	}

	/* نوارِ چسبانِ بخش‌ها: اسکرول‌اسپای + قبلی/بعدی */
	var nav = root.querySelector( '[data-cp3-nav]' );
	if ( nav ) {
		var sections = Array.prototype.slice.call( root.querySelectorAll( '[data-cp3-section]' ) );
		var tabs = Array.prototype.slice.call( nav.querySelectorAll( '.cp3-tab' ) );
		var label = nav.querySelector( '[data-cp3-nav-label]' );
		var prev = nav.querySelector( '[data-cp3-nav-prev]' );
		var next = nav.querySelector( '[data-cp3-nav-next]' );
		var current = 0;
		function setActive( index ) {
			current = Math.max( 0, Math.min( sections.length - 1, index ) );
			tabs.forEach( function ( tab, i ) {
				tab.classList.toggle( 'is-active', i === current );
			} );
			if ( label ) { label.textContent = sections[ current ].getAttribute( 'data-cp3-section' ); }
			if ( prev ) { prev.disabled = current === 0; }
			if ( next ) { next.disabled = current === sections.length - 1; }
		}
		function goTo( index ) {
			var target = sections[ Math.max( 0, Math.min( sections.length - 1, index ) ) ];
			if ( target && target.scrollIntoView ) { target.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
		}
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				goTo( sections.findIndex( function ( s ) { return s.id === tab.getAttribute( 'data-cp3-tab' ); } ) );
			} );
		} );
		if ( prev ) { prev.addEventListener( 'click', function () { goTo( current - 1 ); } ); }
		if ( next ) { next.addEventListener( 'click', function () { goTo( current + 1 ); } ); }
		if ( 'IntersectionObserver' in window ) {
			var spy = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						setActive( sections.findIndex( function ( s ) { return s === entry.target; } ) );
					}
				} );
			}, { rootMargin: '-40% 0px -55% 0px' } );
			sections.forEach( function ( s ) { spy.observe( s ); } );
		}
		setActive( 0 );
	}

	/* «مشاهده بیشتر» توضیحات + خلاصه‌ی کوتاه */
	var descWrap = root.querySelector( '[data-cp3-descwrap]' );
	var descBtn = root.querySelector( '[data-cp3-descbtn]' );
	if ( descWrap && descBtn ) {
		descBtn.addEventListener( 'click', function () {
			var open = descWrap.classList.toggle( 'is-open' );
			descBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			descBtn.querySelector( 'span' ).textContent = open ? 'نمایش کمتر' : 'مشاهده بیشتر';
		} );
	}
	var shortBox = root.querySelector( '[data-cp3-short]' );
	var shortBtn = root.querySelector( '[data-cp3-short-toggle]' );
	if ( shortBox && shortBtn ) {
		var shortBody = shortBox.querySelector( '.cp3-short-body' );
		// متنِ کوتاه که clamp نمی‌شود به دکمه نیاز ندارد — باگِ واقعی:
		// دکمهٔ «مشاهده همه» حتی برای توضیحِ یک‌خطی نشان داده می‌شد.
		if ( shortBody && shortBody.scrollHeight <= shortBody.clientHeight + 2 ) {
			shortBtn.hidden = true;
		}
		shortBtn.addEventListener( 'click', function () {
			var open = shortBox.classList.toggle( 'is-open' );
			shortBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			shortBtn.textContent = open ? 'بستن' : 'مشاهده همه';
		} );
	}

	/* مرتب‌سازیِ دیدگاه‌ها: جدیدترین = ترتیبِ سرور؛ امتیاز از کلاسِ jluxe-rating-N (متای ووکامرس) */
	var sortsel = root.querySelector( '[data-cp3-sort]' );
	if ( sortsel ) {
		var sortList = root.querySelector( '.commentlist' );
		var sortBtns = Array.prototype.slice.call( sortsel.querySelectorAll( 'button[data-sort]' ) );
		sortBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				sortBtns.forEach( function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
					b.setAttribute( 'aria-pressed', b === btn ? 'true' : 'false' );
				} );
				var mode = btn.getAttribute( 'data-sort' );
				if ( ! sortList || 'newest' === mode || ! sortList.children.length ) { return; }
				var ratingOf = function ( el ) {
					var m = ( el.className || '' ).match( /jluxe-rating-(\d+)/ );
					return m ? parseInt( m[1], 10 ) : -1;
				};
				var items = Array.prototype.slice.call( sortList.children ).filter( function ( el ) { return 1 === el.nodeType; } );
				items.sort( function ( a, b ) {
					var d = ratingOf( a ) - ratingOf( b );
					return 'best' === mode ? -d : d;
				} );
				items.forEach( function ( el ) { sortList.appendChild( el ); } );
			} );
		} );
	}
}() );
</script>
