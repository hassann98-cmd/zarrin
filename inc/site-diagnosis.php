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
