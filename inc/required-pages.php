<?php
/**
 * ساختِ خودکارِ برگه‌های موردنیازِ تمپلیت‌های سفارشیِ پوسته — طبقِ درخواستِ
 * صریحِ کاربر («این برگه باید توی برگه‌ها روی هر سایتی بریم ساخته شه»).
 *
 * دلیلِ ریشه‌ای: هر فایلِ page-{اسلاگ}.php یک تمپلیتِ ووردپرسه که فقط وقتی
 * واقعاً استفاده می‌شه که یک «برگه» (post_type=page) با همون اسلاگِ دقیق در
 * دیتابیسِ سایت هم وجود داشته باشه (سلسله‌مراتبِ تمپلیتِ خودِ ووردپرس) — صرفِ
 * وجودِ فایلِ تمِ کافی نیست. یعنی مثلاً /track-order/ فقط وقتی واقعاً کار
 * می‌کنه که یک برگه با اسلاگِ «track-order» از قبل در پیشخوانِ همون سایتِ
 * خاص ساخته شده باشه — کاری که تا الان باید هر بار دستی روی هر سایتِ جدید
 * انجام می‌شد.
 *
 * این‌جا به‌جای تکیه بر after_switch_theme (که با آپلودِ زیپِ همون تمِ از
 * قبل‌فعال — روشِ استقرارِ همیشگیِ همین پروژه — اصلاً fire نمی‌شه، چون
 * ووردپرس این کار رو «سوییچِ تم» حساب نمی‌کنه)، از همون الگویِ نسخه‌دارِ
 * idempotent که inc/sms.php برای jsms_db_version استفاده می‌کنه پیروی
 * می‌شه: روی after_setup_theme (که هر بار واقعاً اجرا می‌شه) یک نسخه چک
 * می‌شه، پس هم روی نصبِ اول هم روی هر آپدیتِ بعدیِ تم (وقتی این آرایه
 * اضافه/تغییر کنه) واقعاً دوباره بررسی می‌شه — بدونِ اینکه هر بار برای
 * هیچی یک کوئریِ اضافه به دیتابیس بزنه.
 */

defined( 'ABSPATH' ) || exit;

/**
 * نگاشتِ اسلاگ → عنوانِ فارسیِ برگه. عمداً «سبد خرید»/«تسویه‌حساب»/
 * «حساب کاربری» (page-cart.php/page-checkout.php/page-my-account.php)
 * این‌جا نیستن — این سه، برگه‌های خودِ ووکامرسن (با فعال‌سازیِ ووکامرس
 * خودکار ساخته می‌شن؛ ساختنِ دوباره‌شون این‌جا فقط یک برگه‌ی تکراری با
 * اسلاگِ «-2» می‌ساخت که هیچ‌وقت با تمپلیتِ درست match نمی‌شد).
 */
function jluxe_required_pages_map(): array {
	return array(
		'about-us'                     => 'درباره ما',
		'contact-us'                   => 'تماس با ما',
		'faq'                          => 'سوالات متداول',
		'jluxe-help-center'            => 'مرکز راهنمایی',
		'payment-guide'                => 'روش‌ها و راهنمای پرداخت سفارشات',
		'returns-and-exchanges'        => 'رویه‌ی شرایط مرجوعی و تعویض کالا',
		'shipping-and-order-tracking'  => 'روش‌های ارسال و راهنمای پیگیری سفارشات',
		'shopping-guide'               => 'راهنمای گام‌به‌گام خرید',
		'track-order'                  => 'پیگیری سفارش',
	);
}

/**
 * برای هر اسلاگِ بالا، اگه برگه‌ای با همون اسلاگِ دقیق (صرف‌نظر از وضعیت —
 * پیش‌نویس/منتشرشده/زباله‌دان، تا برگه‌ی تکراری نسازه) وجود نداشته باشه،
 * یکی می‌سازه: منتشرشده، بدونِ محتوای دستی (چون محتوای واقعی رو خودِ
 * تمپلیتِ page-{اسلاگ}.php می‌سازه، نه post_content) و بدونِ نمایش در
 * منوها (خودِ ادمین اگه خواست دستی به منو اضافه می‌کنه).
 */
function jluxe_ensure_required_pages(): void {
	foreach ( jluxe_required_pages_map() as $slug => $title ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_content' => '',
			)
		);
	}
}

const JLUXE_REQUIRED_PAGES_VERSION = '1.0.0';

add_action(
	'after_setup_theme',
	function () {
		if ( get_option( 'jluxe_required_pages_version' ) === JLUXE_REQUIRED_PAGES_VERSION ) {
			return;
		}
		jluxe_ensure_required_pages();
		update_option( 'jluxe_required_pages_version', JLUXE_REQUIRED_PAGES_VERSION, false );
	}
);
