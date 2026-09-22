<?php
/**
 * هدر مینیمال — برای صفحاتی مثل سبد خرید/تسویه‌حساب که طبق رفتار معمول
 * فروشگاه‌ها (algetshop.ir/checkout هم همینه، بدون هدر/فوتر سایت) باید
 * تمرکز کاربر روی تکمیل خرید بمونه، نه ناوبری سایت.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'jluxe-minimal-layout' ); ?>>
<?php wp_body_open(); ?>
