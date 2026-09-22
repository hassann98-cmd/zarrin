<?php
/**
 * گزارش‌های دستیار — «ثبت پیام برای پشتیبان» (طبق درخواستِ صریحِ کاربر،
 * پورتِ همون فیچرِ پوسته‌ی قبلی/Boom). وقتی دستیارِ هوش مصنوعی نمی‌تونه
 * کمک کنه یا کاربر می‌خواد مستقیم با پشتیبانیِ انسانی صحبت کنه، از داخلِ
 * ویجتِ چت یک پیام (نام/راهِ ارتباطی/متن) ثبت می‌کنه که این‌جا به‌عنوانِ
 * یک تیکتِ واقعی ذخیره و در پیشخوان قابلِ پیگیری (جدید/در حالِ پیگیری/
 * حل‌شده) می‌شه — دقیقاً همون سه وضعیتی که تویِ نمونه‌ی پوسته‌ی قبلی بود.
 *
 * سوییچِ روشن/خاموش: از همون تنظیمِ از قبل موجودِ ai_assistant.widgets.support_ticket
 * استفاده می‌شه (چک‌باکسِ «ثبت پیام برای پشتیبان» که از قبل تویِ پنلِ
 * دستیار هوش مصنوعی وجود داشت ولی هیچ پیاده‌سازیِ واقعی پشتش نبود) — به‌جایِ
 * اضافه‌کردنِ یک سوییچِ تکراریِ جدید.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_register_ai_ticket_post_type(): void {
	register_post_type(
		'jluxe_ai_ticket',
		array(
			'labels'          => array(
				'name'          => __( 'گزارش‌های دستیار', 'jluxe' ),
				'singular_name' => __( 'گزارش دستیار', 'jluxe' ),
			),
			'public'          => false,
			'show_ui'         => false, // لیستِ سفارشیِ خودمون (جدولِ ساده) به‌جایِ UI پیش‌فرضِ نوعِ‌پست.
			'show_in_menu'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
		)
	);
}
add_action( 'init', 'jluxe_register_ai_ticket_post_type' );

/**
 * REST endpoint — دقیقاً همون سطحِ اعتمادِ /assistant (بدونِ لاگین، فقط
 * rate-limit بر اساسِ IP)، چون از همون ویجتِ چتِ عمومی صدا زده می‌شه.
 */
function jluxe_register_ai_ticket_rest_route(): void {
	register_rest_route(
		'jluxe/v1',
		'/assistant/ticket',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_ai_ticket_submit',
			'permission_callback' => '__return_true',
			'args'                => array(
				'name'     => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'contact'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'message'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
				'page_url' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_ai_ticket_rest_route' );

function jluxe_handle_ai_ticket_submit( WP_REST_Request $request ) {
	$settings = jluxe_get_theme_settings()['ai_assistant'];
	if ( empty( $settings['widgets']['support_ticket'] ) ) {
		return new WP_Error( 'jluxe_ticket_disabled', 'این قابلیت فعال نیست.', array( 'status' => 503 ) );
	}

	// rate limit مشترک با الگویِ بقیه‌ی endpointهای عمومی: حداکثر ۵ تیکت
	// در ۱۰ دقیقه به‌ازایِ هر IP — جلوگیری از اسپم بدونِ نیاز به لاگین/کپچا.
	$ip  = jluxe_theme_get_client_ip();
	$key = 'jluxe_ticket_rl_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		return new WP_Error( 'jluxe_ticket_rate_limited', 'تعداد درخواست‌ها زیاده — کمی صبر کن.', array( 'status' => 429 ) );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, 10 * MINUTE_IN_SECONDS );

	$name    = trim( (string) $request->get_param( 'name' ) );
	$contact = trim( (string) $request->get_param( 'contact' ) );
	$message = trim( (string) $request->get_param( 'message' ) );

	if ( '' === $name || '' === $contact || '' === $message ) {
		return new WP_Error( 'jluxe_ticket_missing_fields', 'نام، راه ارتباطی و پیام الزامی هستن.', array( 'status' => 400 ) );
	}
	if ( mb_strlen( $message ) > 2000 ) {
		return new WP_Error( 'jluxe_ticket_too_long', 'پیام خیلی طولانیه.', array( 'status' => 400 ) );
	}

	$page_url = (string) $request->get_param( 'page_url' );
	$user_id  = get_current_user_id();

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'jluxe_ai_ticket',
			'post_status' => 'publish',
			'post_title'  => $name . ' — ' . wp_trim_words( $message, 6, '…' ),
			'post_author' => $user_id ?: 0,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'jluxe_ticket_failed', 'ثبت پیام ناموفق بود. لطفاً دوباره تلاش کن.', array( 'status' => 500 ) );
	}

	update_post_meta( $post_id, '_jluxe_ticket_name', $name );
	update_post_meta( $post_id, '_jluxe_ticket_contact', $contact );
	update_post_meta( $post_id, '_jluxe_ticket_message', $message );
	update_post_meta( $post_id, '_jluxe_ticket_page_url', $page_url );
	update_post_meta( $post_id, '_jluxe_ticket_user_id', $user_id );
	update_post_meta( $post_id, '_jluxe_ticket_status', 'new' );

	return array( 'success' => true );
}

/**
 * صفحه‌ی پیشخوان — جدولِ ساده (نه UI پیش‌فرضِ نوعِ‌پست)، دقیقاً هم‌راستا با
 * نمونه‌ای که کاربر از پوسته‌ی قبلی فرستاد: هر ردیف نام/راهِ‌ارتباطی/پیام/
 * صفحه‌ی مرتبط/وضعیت + یک فرمِ کوچیکِ تغییرِ وضعیت.
 */
function jluxe_render_ai_tickets_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}

	$tickets = get_posts(
		array(
			'post_type'      => 'jluxe_ai_ticket',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$status_labels = array(
		'new'         => 'جدید',
		'in_progress' => 'در حال پیگیری',
		'resolved'    => 'حل‌شده',
	);
	$status_colors = array(
		'new'         => array( '#fee2e2', '#991b1b' ),
		'in_progress' => array( '#fef9c3', '#854d0e' ),
		'resolved'    => array( '#dcfce7', '#166534' ),
	);
	?>
	<div class="wrap jluxe-settings" dir="rtl">
		<h1><span class="dashicons dashicons-format-chat"></span> گزارش‌های دستیار</h1>
		<p class="description">پیام‌هایی که کاربران از طریقِ دستیارِ هوش مصنوعی برایِ پشتیبانیِ انسانی ثبت کرده‌اند.</p>

		<?php if ( empty( $tickets ) ) : ?>
			<p style="margin-top:16px;">هنوز هیچ پیامی ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:14px;">
				<thead>
					<tr>
						<th style="width:60px;">#</th>
						<th>تاریخ</th>
						<th>فرستنده</th>
						<th>راه ارتباطی</th>
						<th>پیام</th>
						<th>وضعیت</th>
						<th>عملیات</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tickets as $ticket ) : ?>
						<?php
						$name      = get_post_meta( $ticket->ID, '_jluxe_ticket_name', true );
						$contact   = get_post_meta( $ticket->ID, '_jluxe_ticket_contact', true );
						$message   = get_post_meta( $ticket->ID, '_jluxe_ticket_message', true );
						$page_url  = get_post_meta( $ticket->ID, '_jluxe_ticket_page_url', true );
						$user_id   = (int) get_post_meta( $ticket->ID, '_jluxe_ticket_user_id', true );
						$status    = get_post_meta( $ticket->ID, '_jluxe_ticket_status', true ) ?: 'new';
						$status    = isset( $status_labels[ $status ] ) ? $status : 'new';
						list( $bg, $fg ) = $status_colors[ $status ];
						?>
						<tr>
							<td><?php echo (int) $ticket->ID; ?></td>
							<td><?php echo esc_html( get_the_date( 'Y/m/d H:i', $ticket ) ); ?></td>
							<td>
								<?php echo esc_html( $name ); ?>
								<?php if ( $user_id ) : ?>
									<br><small>کاربر #<?php echo (int) $user_id; ?></small>
								<?php endif; ?>
							</td>
							<td dir="ltr" style="text-align:right;"><?php echo esc_html( $contact ); ?></td>
							<td style="max-width:320px;">
								<?php echo esc_html( $message ); ?>
								<?php if ( $page_url ) : ?>
									<br><a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><small>صفحه مرتبط</small></a>
								<?php endif; ?>
							</td>
							<td>
								<span style="padding:2px 10px;border-radius:999px;font-size:12px;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>;">
									<?php echo esc_html( $status_labels[ $status ] ); ?>
								</span>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;">
									<?php wp_nonce_field( 'jluxe_ticket_status_' . $ticket->ID, 'jluxe_ticket_nonce' ); ?>
									<input type="hidden" name="action" value="jluxe_update_ticket_status" />
									<input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->ID; ?>" />
									<select name="new_status">
										<?php foreach ( $status_labels as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button button-small">ثبت</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function jluxe_handle_ai_ticket_status_update(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
	if ( ! $ticket_id || ! isset( $_POST['jluxe_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_ticket_nonce'] ) ), 'jluxe_ticket_status_' . $ticket_id ) ) {
		wp_die( 'درخواست نامعتبر است.' );
	}

	$new_status    = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : 'new';
	$valid_statuses = array( 'new', 'in_progress', 'resolved' );
	if ( ! in_array( $new_status, $valid_statuses, true ) ) {
		$new_status = 'new';
	}

	if ( 'jluxe_ai_ticket' === get_post_type( $ticket_id ) ) {
		update_post_meta( $ticket_id, '_jluxe_ticket_status', $new_status );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=jluxe-ai-tickets' ) );
	exit;
}
add_action( 'admin_post_jluxe_update_ticket_status', 'jluxe_handle_ai_ticket_status_update' );
