<?php
/**
 * تشخیص و ترمیمِ «آدرس‌ها و فروشگاه» — پاسخِ مستقیم به گزارشِ کاربر:
 * /shop/ خالی است و آدرس‌های ورود/حساب/بلاگ خراب‌اند. این صفحه فقط
 * «وضعیتِ واقعی» را از دادهٔ خودِ وردپرس/ووکامرس می‌خواند و گزارش می‌دهد؛
 * ترمیم هم فقط با کلیکِ ادمین (admin-post + nonce) انجام می‌شود — طبق
 * اصلِ همیشگیِ پوسته، هیچ برگه‌ای در درخواستِ فرانت‌اند ساخته نمی‌شود.
 *
 * مرزِ صادقانه: اگر /shop/ باز می‌شود ولی محصولی نیست، این صفحه علتش را
 * (تعدادِ محصولِ منتشرشده = صفر یا نبودِ برگهٔ فروشگاه) نشان می‌دهد؛
 * محصول جایگزینی نمی‌شود و هیچ دادهٔ جعلی‌ای ساخته نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

/** وضعیتِ یک برگه از روی شناسهٔ ذخیره‌شده (option یا تنظیمِ ووکامرس). */
function jluxe_diagnosis_page_status( int $page_id ): string {
	if ( $page_id <= 0 ) {
		return 'unset';
	}
	$post = get_post( $page_id );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return 'missing';
	}
	if ( 'publish' === $post->post_status ) {
		return 'ok';
	}
	if ( 'trash' === $post->post_status ) {
		return 'trash';
	}
	return 'draft';
}

function jluxe_diagnosis_status_label( string $status ): array {
	$labels = array(
		'ok'      => array( 'برگه منتشر است', 'success' ),
		'draft'   => array( 'پیش‌نویس — منتشر نشده', 'warning' ),
		'trash'   => array( 'در زباله‌دان است', 'error' ),
		'missing' => array( 'شناسه تنظیم شده ولی برگه پیدا نمی‌شود', 'error' ),
		'unset'   => array( 'تنظیم نشده است', 'error' ),
		'no_woo'  => array( 'ووکامرس فعال نیست', 'error' ),
	);
	return $labels[ $status ] ?? array( $status, 'error' );
}

/**
 * گزارشِ کامل: برگه‌های پشتیبانِ آدرس‌ها (فروشگاه/سبد/تسویه/حساب/بلاگ)
 * + شمارشِ محصولِ منتشرشده و ناموجود — همان چیزی که برای پاسخ به
 * «چرا /shop/ خالی است» لازم است.
 */
function jluxe_diagnosis_report(): array {
	$has_woo = function_exists( 'wc_get_page_id' );
	$pages   = array();
	$map     = array(
		'shop'     => array( 'فروشگاه', 'shop' ),
		'cart'     => array( 'سبد خرید', 'cart' ),
		'checkout' => array( 'تسویه حساب', 'checkout' ),
		'myaccount' => array( 'حساب کاربری / ورود', 'login' ),
	);
	foreach ( $map as $key => [ $label, $route ] ) {
		$page_id = $has_woo ? (int) wc_get_page_id( $key ) : 0;
		$pages[] = array(
			'key'     => $key,
			'label'   => $label,
			'page_id' => $page_id,
			'status'  => $has_woo ? jluxe_diagnosis_page_status( $page_id ) : 'no_woo',
			'url'     => jluxe_route_url( $route ),
		);
	}
	$blog_id = (int) get_option( 'page_for_posts' );
	$blog_status = 'posts' === get_option( 'show_on_front' ) ? 'ok' : jluxe_diagnosis_page_status( $blog_id );
	$pages[]     = array(
		'key'     => 'blog',
		'label'   => 'بلاگ (نوشته‌ها)',
		'page_id' => $blog_id,
		'status'  => $blog_status,
		'url'     => $blog_id > 0 ? get_permalink( $blog_id ) : home_url( '/' ),
	);

	$counts = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'product' ) : null;
	$products = array(
		'publish' => (int) ( $counts->publish ?? 0 ),
		'draft'   => (int) ( $counts->draft ?? 0 ),
	);

	$shop_bad = $has_woo && in_array( jluxe_diagnosis_page_status( (int) wc_get_page_id( 'shop' ) ), array( 'unset', 'missing', 'trash' ), true );
	return array(
		'pages'    => $pages,
		'products' => $products,
		'has_woo'  => $has_woo,
		'shop_bad' => $shop_bad,
	);
}

/**
 * ترمیمِ برگه‌های پشتیبانِ آدرس‌ها — فقطِ ناقص‌ها:
 *  - پیش‌نویس → منتشر می‌شود (برگه‌های سبد/تسویه/حساب محتوای_shortcode دارند
 *    و باید عمومی باشند؛ این خودِ قراردادِ ووکامرس است).
 *  - تنظیم‌نشده/گم‌شده → اول برگهٔ موجودِ هم‌اسلاگ وصل می‌شود، وگرنه
 *    برگهٔ تازه با محتوای استانداردِ ووکامرس ساخته و تنظیم می‌شود.
 * برگه‌های سالم دست‌نخورده می‌مانند. خروجی: فهرستِ کارهای انجام‌شده/خطاها.
 */
function jluxe_diagnosis_repair_pages(): array {
	$fixed  = array();
	$errors = array();
	if ( ! current_user_can( 'manage_options' ) ) {
		return array( 'fixed' => $fixed, 'errors' => array( 'دسترسی غیرمجاز.' ) );
	}
	if ( function_exists( 'wc_get_page_id' ) ) {
		$map = array(
			'woocommerce_shop_page_id'      => array( 'shop', 'فروشگاه', '' ),
			'woocommerce_cart_page_id'      => array( 'cart', 'سبد خرید', '[woocommerce_cart]' ),
			'woocommerce_checkout_page_id'  => array( 'checkout', 'تسویه حساب', '[woocommerce_checkout]' ),
			'woocommerce_myaccount_page_id' => array( 'my-account', 'حساب کاربری', '[woocommerce_my_account]' ),
		);
		foreach ( $map as $option => [ $slug, $title, $content ] ) {
			$status = jluxe_diagnosis_page_status( (int) get_option( $option ) );
			if ( 'ok' === $status ) {
				continue;
			}
			if ( 'draft' === $status ) {
				$page_id = (int) get_option( $option );
				$result  = wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ), true );
				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'انتشارِ «%s» انجام نشد.', $title );
				} else {
					$fixed[] = sprintf( 'برگهٔ «%s» منتشر شد.', $title );
				}
				continue;
			}
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				update_option( $option, (int) $existing->ID );
				$fixed[] = sprintf( 'برگهٔ موجودِ «%s» به تنظیماتِ ووکامرس وصل شد.', $title );
				continue;
			}
			$created = wp_insert_post( array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_content' => $content,
			), true );
			if ( is_wp_error( $created ) ) {
				$errors[] = sprintf( 'ساختِ «%s» انجام نشد.', $title );
				continue;
			}
			update_option( $option, (int) $created );
			$fixed[] = sprintf( 'برگهٔ «%s» ساخته و تنظیم شد.', $title );
		}
	}
	// بلاگ: فقط وقتی صفحهٔ اولِ جدا (page_on_front) فعال است و برگهٔ نوشته‌ها ناقص است.
	if ( 'page' === get_option( 'show_on_front' ) ) {
		$blog_status = jluxe_diagnosis_page_status( (int) get_option( 'page_for_posts' ) );
		if ( in_array( $blog_status, array( 'unset', 'missing', 'trash' ), true ) ) {
			$existing = get_page_by_path( 'blog', OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				update_option( 'page_for_posts', (int) $existing->ID );
				$fixed[] = 'برگهٔ موجودِ «بلاگ» به «نوشته‌های اخیر» وصل شد.';
			} else {
				$created = wp_insert_post( array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => 'بلاگ',
					'post_name'   => 'blog',
					'post_content' => '',
				), true );
				if ( is_wp_error( $created ) ) {
					$errors[] = 'ساختِ برگهٔ «بلاگ» انجام نشد.';
				} else {
					update_option( 'page_for_posts', (int) $created );
					$fixed[] = 'برگهٔ «بلاگ» ساخته و به «نوشته‌های اخیر» وصل شد.';
				}
			}
		}
	}
	return array( 'fixed' => $fixed, 'errors' => $errors );
}

function jluxe_repair_site_urls_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'jluxe_repair_site_urls' );
	$result = jluxe_diagnosis_repair_pages();
	wp_safe_redirect( add_query_arg(
		array(
			'page'                => 'jluxe-site-diagnosis',
			'jluxe_repair_result' => empty( $result['errors'] ) ? 'success' : 'partial',
			'jluxe_repair_count'  => count( $result['fixed'] ),
		),
		admin_url( 'admin.php' )
	) );
	exit;
}
add_action( 'admin_post_jluxe_repair_site_urls', 'jluxe_repair_site_urls_action' );

/* -------------------------------------------------------------------------
 * R80 — سلامتِ فایل‌های پوسته.
 *
 * دلیلِ ساخت: روی سایتِ فعال ثابت شد نسخهٔ «style.css» عددِ 1.68.0 را
 * نشان می‌دهد ولی فایلِ قالبِ کارتِ محصول هنوز نسخهٔ قدیمی است (آپلودِ
 * ناقص) و همین باعثِ «خطای مهم» در هر حلقهٔ محصول شده بود. هیچ‌کس بدونِ
 * ابزار نمی‌تواند بفهمد کدام فایل روی سرور قدیمی مانده. این‌جا با فهرستِ
 * SHA-256 که هنگامِ build از خودِ مخزن ساخته می‌شود (docs/FILES.sha256)
 * همهٔ فایل‌های پوسته بررسی و «گم‌شده/تغییرکرده» گزارش می‌شوند.
 *
 * مرزِ صادقانه: این ابزار فقط «تفاوتِ فایل با بستهٔ رسمی» را نشان می‌دهد؛
 * نمی‌گوید کدام فایل خطا دارد و چیزی را خودکار جایگزین نمی‌کند.
 * ---------------------------------------------------------------------- */

/** فهرستِ رسمیِ فایل‌ها با هشِ SHA-256 (path => hash). */
function jluxe_theme_manifest( bool $refresh = false ): array {
	static $manifest = null;
	if ( is_array( $manifest ) && ! $refresh ) {
		return $manifest;
	}
	$manifest = array();
	$file     = JLUXE_THEME_DIR . '/docs/FILES.sha256';
	if ( ! is_readable( $file ) ) {
		return $manifest;
	}
	foreach ( preg_split( '/\r\n|\n|\r/', (string) file_get_contents( $file ) ) as $line ) {
		if ( preg_match( '/^([0-9a-f]{64}) {2}(.+)$/', trim( $line ), $m ) ) {
			$manifest[ $m[2] ] = $m[1];
		}
	}
	return $manifest;
}

/**
 * گزارشِ سلامتِ فایل‌ها. برای اینکه در هر بارِ بازکردنِ پیشخوان دوباره
 * هشِ ۲۴۵ فایل محاسبه نشود، نتیجه ۶ ساعت کش می‌شود؛ با $refresh یا با
 * دکمهٔ «بررسی دوباره» در صفحهٔ تشخیص، کش پاک و دوباره محاسبه می‌شود.
 */
function jluxe_theme_integrity_report( bool $refresh = false ): array {
	$manifest = jluxe_theme_manifest( $refresh );
	$version  = function_exists( 'wp_get_theme' ) ? (string) wp_get_theme()->get( 'Version' ) : '';
	$key      = 'jluxe_integrity_' . md5( $version . '|' . count( $manifest ) . '|' . JLUXE_THEME_DIR );
	if ( ! $refresh && function_exists( 'get_transient' ) ) {
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}
	$report = array(
		'manifest_missing' => empty( $manifest ),
		'checked'          => 0,
		'missing'          => array(),
		'modified'         => array(),
		'ok'               => true,
		'at'               => time(),
	);
	foreach ( $manifest as $path => $hash ) {
		$abs = JLUXE_THEME_DIR . '/' . $path;
		if ( ! is_readable( $abs ) ) {
			$report['missing'][] = $path;
			continue;
		}
		++$report['checked'];
		if ( hash_file( 'sha256', $abs ) !== $hash ) {
			$report['modified'][] = $path;
		}
	}
	$report['ok'] = ! $report['manifest_missing'] && empty( $report['missing'] ) && empty( $report['modified'] );
	if ( function_exists( 'set_transient' ) ) {
		set_transient( $key, $report, 6 * HOUR_IN_SECONDS );
	}
	return $report;
}

/** پاک‌کردنِ کش و بررسیِ دوباره با کلیکِ ادمین (admin-post + nonce). */
function jluxe_refresh_integrity_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی نیست.', 'jluxe' ) );
	}
	check_admin_referer( 'jluxe_refresh_integrity' );
	jluxe_theme_integrity_report( true );
	wp_safe_redirect( admin_url( 'themes.php?page=jluxe-site-diagnosis&jluxe_integrity=refreshed' ) );
	exit;
}
add_action( 'admin_post_jluxe_refresh_integrity', 'jluxe_refresh_integrity_action' );

/** آزمونِ بومیِ «سلامتِ سایتِ» وردپرس (ابزارها ← سلامتِ سایت) — بدونِ UIِ اختصاصی. */
function jluxe_site_health_theme_integrity(): array {
	$report = jluxe_theme_integrity_report();
	if ( $report['manifest_missing'] ) {
		return array(
			'label'       => 'فهرستِ فایل‌های پوسته در دسترس نیست',
			'status'      => 'recommended',
			'badge'       => array( 'label' => 'پوستهٔ زرین', 'color' => 'blue' ),
			'description' => '<p>فایلِ <code>docs/FILES.sha256</code> پیدا نشد؛ بدونِ آن نمی‌توان فهمید فایل‌های پوسته با بستهٔ رسمی یکی هستند یا نه. بستهٔ کاملِ پوسته را دوباره نصب کنید.</p>',
			'test'        => 'jluxe_theme_files',
		);
	}
	$bad = count( $report['missing'] ) + count( $report['modified'] );
	if ( 0 === $bad ) {
		return array(
			'label'       => 'همهٔ فایل‌های پوسته با بستهٔ رسمی یکسان‌اند',
			'status'      => 'good',
			'badge'       => array( 'label' => 'پوستهٔ زرین', 'color' => 'blue' ),
			'description' => sprintf( '<p>%s فایل بررسی شد و هیچ تفاوتی با بستهٔ رسمی پیدا نشد.</p>', esc_html( jluxe_fa_digits( (string) $report['checked'] ) ) ),
			'test'        => 'jluxe_theme_files',
		);
	}
	return array(
		'label'       => 'فایل‌های پوسته با بستهٔ رسمی یکی نیستند',
		'status'      => 'critical',
		'badge'       => array( 'label' => 'پوستهٔ زرین', 'color' => 'red' ),
		'description' => sprintf(
			'<p><strong>%s فایل تغییرکرده و %s فایل گم‌شده</strong> است (از %s فایل بررسی‌شده). این وضعیت یعنی آپلود/نصبِ پوسته کامل نشده و می‌تواند باعثِ «خطای مهم» در صفحه‌های فروشگاه شود.</p><p>بستهٔ رسمی را دوباره و کامل نصب کنید، سپس از «تشخیص سایت ← سلامتِ فایل‌های پوسته» دوباره بررسی کنید.</p><p>مثال‌ها: <code>%s</code></p>',
			esc_html( jluxe_fa_digits( (string) count( $report['modified'] ) ) ),
			esc_html( jluxe_fa_digits( (string) count( $report['missing'] ) ) ),
			esc_html( jluxe_fa_digits( (string) ( $report['checked'] + count( $report['missing'] ) ) ) ),
			esc_html( implode( '</code>، <code>', array_slice( array_merge( $report['missing'], $report['modified'] ), 0, 5 ) ) )
		),
		'test'        => 'jluxe_theme_files',
	);
}
add_filter(
	'site_status_tests',
	static function ( array $tests ): array {
		$tests['direct']['jluxe_theme_files'] = array(
			'label' => 'سلامتِ فایل‌های پوستهٔ زرین',
			'test'  => 'jluxe_site_health_theme_integrity',
		);
		return $tests;
	}
);

function jluxe_render_site_diagnosis_page(): void {
	$repair_result = isset( $_GET['jluxe_repair_result'] ) ? sanitize_key( wp_unslash( $_GET['jluxe_repair_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$repair_count  = isset( $_GET['jluxe_repair_count'] ) ? absint( $_GET['jluxe_repair_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$report        = jluxe_diagnosis_report();

	jluxe_settings_page_shell( 'تشخیص سایت', 'jluxe-site-diagnosis', null, function () use ( $report, $repair_result, $repair_count ) {
		if ( 'success' === $repair_result ) {
			printf( '<div class="notice notice-success"><p>✓ ترمیم انجام شد (%d مورد). وضعیتِ زیر به‌روز است.</p></div>', esc_html( jluxe_fa_digits( (string) $repair_count ) ) );
		} elseif ( 'partial' === $repair_result ) {
			echo '<div class="notice notice-warning"><p>⚠ برخی موارد ترمیم شد؛ ردیف‌های باقی‌مانده را در فهرستِ زیر ببینید.</p></div>';
		}
		?>
		<h2>برگه‌های پشتیبانِ آدرس‌ها</h2>
		<p class="description">آدرس‌های هدر/فوتر/حساب از این برگه‌ها می‌آیند (یا از آدرسِ دستیِ «آدرس‌های ورود و کاربر»). اگر ورود/حساب کاربری خطای ۴۰۴ می‌دهد، علتش در همین جدول است.</p>
		<table class="widefat striped">
			<thead><tr><th>بخش</th><th>وضعیت</th><th>شناسهٔ برگه</th><th>آدرسِ نهایی</th></tr></thead>
			<tbody>
			<?php foreach ( $report['pages'] as $page ) : ?>
				<?php [ $label, $tone ] = jluxe_diagnosis_status_label( (string) $page['status'] ); ?>
				<tr>
					<td><strong><?php echo esc_html( $page['label'] ); ?></strong></td>
					<td>
						<span style="color:<?php echo 'success' === $tone ? 'green' : ( 'warning' === $tone ? '#996800' : '#b32d2e' ); ?>;font-weight:600"><?php echo esc_html( $label ); ?></span>
					</td>
					<td><?php echo 'no_woo' === $page['status'] ? '—' : esc_html( jluxe_fa_digits( (string) $page['page_id'] ) ); ?></td>
					<td><code dir="ltr"><?php echo esc_html( (string) $page['url'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$integrity = jluxe_theme_integrity_report();
		$integrity_bad = array_merge( $integrity['missing'], $integrity['modified'] );
		?>
		<h2>سلامتِ فایل‌های پوسته</h2>
		<p class="description">فایل‌های پوسته با فهرستِ رسمیِ بستهٔ <code>docs/FILES.sha256</code> مقایسه می‌شوند. اگر «تغییرکرده/گم‌شده» بالای صفر بود، یعنی نصب/آپلودِ پوسته کامل نشده است — همان چیزی که می‌تواند کلِ حلقهٔ محصولات را با «خطای مهم» از کار بیندازد.</p>
		<?php if ( $integrity['manifest_missing'] ) : ?>
			<p class="description" style="color:#996800">فایلِ فهرست (<code>docs/FILES.sha256</code>) در این نصب نیست؛ بستهٔ کاملِ پوسته را دوباره نصب کنید.</p>
		<?php elseif ( 0 === count( $integrity_bad ) ) : ?>
			<p style="color:green;font-weight:600">✓ هر <?php echo esc_html( jluxe_fa_digits( (string) $integrity['checked'] ) ); ?> فایل با بستهٔ رسمی یکسان است.</p>
		<?php else : ?>
			<p style="color:#b32d2e;font-weight:600">⚠ <?php echo esc_html( jluxe_fa_digits( (string) count( $integrity['modified'] ) ) ); ?> فایل تغییرکرده و <?php echo esc_html( jluxe_fa_digits( (string) count( $integrity['missing'] ) ) ); ?> فایل گم‌شده است.</p>
			<table class="widefat striped">
				<thead><tr><th>فایل</th><th>وضعیت</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $integrity['missing'], 0, 15 ) as $path ) : ?>
					<tr><td><code dir="ltr"><?php echo esc_html( $path ); ?></code></td><td style="color:#b32d2e">گم‌شده</td></tr>
				<?php endforeach; ?>
				<?php foreach ( array_slice( $integrity['modified'], 0, 15 ) as $path ) : ?>
					<tr><td><code dir="ltr"><?php echo esc_html( $path ); ?></code></td><td style="color:#996800">تغییرکرده</td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $integrity_bad ) > 30 ) : ?><p class="description">… و <?php echo esc_html( jluxe_fa_digits( (string) ( count( $integrity_bad ) - 30 ) ) ); ?> فایلِ دیگر.</p><?php endif; ?>
			<p class="description">راهِ سریع روی سرور: <code dir="ltr">cd wp-content/themes/zarrin &amp;&amp; sha256sum -c docs/FILES.sha256</code></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jluxe_refresh_integrity" />
			<?php wp_nonce_field( 'jluxe_refresh_integrity' ); ?>
			<p><button type="submit" class="button">بررسی دوبارهٔ فایل‌ها</button></p>
		</form>

		<?php $fatals = jluxe_recent_php_fatals(); ?>
		<h2>آخرین خطاهای کشندهٔ PHP</h2>
		<p class="description">هر بار که یک فایلِ ناقص/قدیمی وسطِ رندر خطا بدهد، پیامِ واقعیِ PHP (فایل + خط) این‌جا ثبت می‌شود — همان چیزی که در حالتِ عادی فقط در لاگِ سرور دیده می‌شود و کاربر به‌جایش «یک خطای مهم در این وب‌سایت رخ داده است» می‌بیند.</p>
		<?php if ( empty( $fatals ) ) : ?>
			<p style="color:green;font-weight:600">✓ خطای کشندهٔ ثبت‌شده‌ای وجود ندارد.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>زمان</th><th>پیام</th><th>فایل:خط</th><th>تکرار</th></tr></thead>
				<tbody>
				<?php foreach ( $fatals as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
						<td><code dir="ltr"><?php echo esc_html( mb_substr( (string) ( $entry['message'] ?? '' ), 0, 160 ) ); ?></code><br><small><?php echo esc_html( jluxe_fatal_type_label( (int) ( $entry['type'] ?? 0 ) ) ); ?></small></td>
						<td><code dir="ltr"><?php echo esc_html( (string) ( $entry['file'] ?? '' ) ); ?>:<?php echo esc_html( jluxe_fa_digits( (string) ( $entry['line'] ?? 0 ) ) ); ?></code></td>
						<td><?php echo esc_html( jluxe_fa_digits( (string) ( $entry['count'] ?? 1 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jluxe_clear_php_fatals" />
				<?php wp_nonce_field( 'jluxe_clear_php_fatals' ); ?>
				<p><button type="submit" class="button">پاک‌کردنِ این فهرست</button></p>
			</form>
		<?php endif; ?>

		<?php $guard_hits = get_option( JLUXE_TEMPLATE_GUARD_OPTION, array() ); ?>
		<h2>محافظِ قالب‌های ووکامرس</h2>
		<p class="description">اگر فایلِ قالبی با بستهٔ رسمی یکی نباشد، پوسته به‌جای آن نسخهٔ پشتیبانِ سالم (یا قالبِ پیش‌فرضِ ووکامرس) را رندر می‌کند تا فروشگاه از کار نیفتد.</p>
		<?php if ( empty( $guard_hits ) || ! is_array( $guard_hits ) ) : ?>
			<p style="color:green;font-weight:600">✓ هیچ قالبی تا حالا نیاز به جایگزینی نداشته است.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>فایل</th><th>جایگزینِ استفاده‌شده</th><th>آخرین بار</th></tr></thead>
				<tbody>
				<?php foreach ( $guard_hits as $relative => $hit ) : ?>
					<tr>
						<td><code dir="ltr"><?php echo esc_html( (string) $relative ); ?></code></td>
						<td><?php echo 'fallback' === ( $hit['mode'] ?? '' ) ? 'نسخهٔ پشتیبانِ سالمِ پوسته' : 'قالبِ پیش‌فرضِ ووکامرس'; ?></td>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) ( $hit['time'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jluxe_template_guard_clear" />
				<?php wp_nonce_field( 'jluxe_template_guard_clear' ); ?>
				<p><button type="submit" class="button">بعد از بازآپلودِ موفق، این فهرست را پاک کن</button></p>
			</form>
		<?php endif; ?>

		<h2>محصولات</h2>
		<table class="widefat striped">
			<tbody>
				<tr><td><strong>منتشرشده</strong></td><td><?php echo esc_html( jluxe_fa_digits( (string) $report['products']['publish'] ) ); ?></td></tr>
				<tr><td><strong>پیش‌نویس</strong></td><td><?php echo esc_html( jluxe_fa_digits( (string) $report['products']['draft'] ) ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( $report['shop_bad'] ) : ?>
			<p class="description" style="color:#b32d2e">آدرسِ فروشگاه به برگه‌ای سالم اشاره نمی‌کند — همین علتِ خالی‌بودنِ /shop/ است. با دکمهٔ پایین ترمیمش کنید.</p>
		<?php elseif ( 0 === $report['products']['publish'] ) : ?>
			<p class="description" style="color:#996800">برگهٔ فروشگاه سالم است اما هیچ محصولِ منتشرشده‌ای وجود ندارد؛ محصولات را از «محصولات ← افزودن» منتشر کنید تا در /shop/ دیده شوند.</p>
		<?php else : ?>
			<p class="description">برگهٔ فروشگاه سالم است و محصولِ منتشرشده وجود دارد. اگر با این حال فهرست خالی است، وضعیتِ موجودی (ناموجود) یا «پنهان» بودنِ فروشگاهیِ محصولات را در ویرایشِ خودِ محصولات بررسی کنید.</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jluxe_repair_site_urls" />
			<?php wp_nonce_field( 'jluxe_repair_site_urls' ); ?>
			<p>
				<button type="submit" class="button button-primary">ترمیمِ برگه‌های ناقص (فروشگاه، سبد، تسویه، حساب، بلاگ)</button>
			</p>
			<p class="description">برگه‌های سالم دست‌نخورده می‌مانند؛ فقط پیش‌نویس‌ها منتشر و مواردِ تنظیم‌نشده/حذف‌شده ساخته یا وصل می‌شوند. برگه‌های ووکامرس با محتوای استانداردِ خودش (<code>[woocommerce_cart]</code> و…) ساخته می‌شوند.</p>
		</form>
		<?php
	} );
}
