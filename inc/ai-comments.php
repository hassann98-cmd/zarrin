<?php
/**
 * پاسخ خودکار به دیدگاه‌ها + خلاصهٔ قابل‌ویرایش هر محصول (بخش «AI دیدگاه‌ها»).
 * از همان provider/کلید/مدلِ دستیار چت استفاده می‌کند ولی منطقش جدا است:
 * هیچ تماسی از رندر صفحه نمی‌رود — فقط Cron یا دکمه‌های دستیِ پیشخوان.
 * امنیت: همهٔ اکشن‌های مدیریتی capability + nonce دارند؛ پاسخ‌ها همیشه با
 * برچسب «پاسخ خودکار» نمایش داده می‌شوند تا محتوای ماشینی، انسانی جلوه نکند.
 */

defined( 'ABSPATH' ) || exit;

const JLUXE_AI_CRON_HOOK        = 'jluxe_ai_comments_tick';
const JLUXE_AI_SUMMARY_META     = '_jluxe_ai_summary_manual';
const JLUXE_AI_REPLIED_META     = '_jluxe_ai_replied';
const JLUXE_AI_REPLY_FLAG_META  = '_jluxe_ai_reply';
const JLUXE_AI_REPLY_FAILS_META = '_jluxe_ai_reply_fails';

/** تنظیمات این بخش با مقادیر امن برای همان لحظه. */
function jluxe_ai_comments_settings(): array {
	$settings = jluxe_get_theme_settings()['ai_comments'] ?? array();
	return is_array( $settings ) ? $settings : array();
}

/** این بخش فقط با کلید و سرویسِ پیکربندی‌شدهٔ دستیار کار می‌کند. */
function jluxe_ai_comments_ready(): bool {
	$settings = jluxe_ai_comments_settings();
	return ! empty( $settings['enabled'] )
		&& '' !== (string) jluxe_get_ai_api_key()
		&& '' !== (string) ( jluxe_get_theme_settings()['ai_assistant']['provider'] ?? '' );
}

/* -------------------------------------------------------------------------
 * Cron: بازهٔ انتخابی مدیر (۱ تا ۶۰ دقیقه) — بدون scheduleهای ثابت جدید.
 * ---------------------------------------------------------------------- */
add_filter( 'cron_schedules', function ( array $schedules ): array {
	$interval = (int) ( jluxe_ai_comments_settings()['cron_interval'] ?? 30 );
	if ( $interval < 1 ) {
		$interval = 30;
	}
	$schedules['jluxe_ai_comments_interval'] = array(
		'interval' => $interval * MINUTE_IN_SECONDS,
		'display'  => 'بازهٔ پردازش هوش مصنوعی دیدگاه‌ها',
	);
	return $schedules;
} );

/** فعال → مطمئن شو رویداد ثبت است؛ غیرفعال → پاکش کن (هیچ تماس پنهانی نماند). */
function jluxe_ai_comments_schedule(): void {
	if ( ! jluxe_ai_comments_ready() ) {
		wp_clear_scheduled_hook( JLUXE_AI_CRON_HOOK );
		return;
	}
	if ( ! wp_next_scheduled( JLUXE_AI_CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, 'jluxe_ai_comments_interval', JLUXE_AI_CRON_HOOK );
	}
}

/* -------------------------------------------------------------------------
 * کارِ هر تیک: پاسخ‌های در انتظار + پرکردن خلاصه‌های تولیدنشده (بودجه‌دار).
 * ---------------------------------------------------------------------- */
function jluxe_ai_comments_process_queue(): array {
	if ( ! jluxe_ai_comments_ready() ) {
		return array( 'replies' => 0, 'summaries' => 0 );
	}
	$lock = jluxe_security_lock( 'ai_comments_tick', 600 );
	if ( ! $lock ) {
		return array( 'replies' => 0, 'summaries' => 0 );
	}
	try {
		$settings = jluxe_ai_comments_settings();
		$replies   = ! empty( $settings['auto_reply_enabled'] ) ? jluxe_ai_process_comment_replies( max( 1, (int) $settings['max_replies_per_run'] ) ) : 0;
		$summaries = jluxe_ai_fill_missing_summaries( max( 1, (int) $settings['max_summaries_per_run'] ) );
		return array( 'replies' => $replies, 'summaries' => $summaries );
	} finally {
		jluxe_security_unlock( $lock );
	}
}
add_action( JLUXE_AI_CRON_HOOK, 'jluxe_ai_comments_process_queue' );

/** دیدگاه‌های تأییدشدهٔ بی‌پاسخ (بدون متای پاسخ/خطای دائم). */
function jluxe_ai_pending_comment_ids( string $type, int $limit ): array {
	$ids = array();
	foreach ( get_comments( array(
		'status' => 'approve',
		'type'   => $type,
		'number' => min( 100, max( $limit * 5, 20 ) ),
		'orderby' => 'comment_date',
		'order'  => 'ASC',
	) ) as $comment ) {
		if ( ! $comment instanceof WP_Comment || count( $ids ) >= $limit ) {
			continue;
		}
		if ( get_comment_meta( $comment->comment_ID, JLUXE_AI_REPLIED_META, true )
			|| get_comment_meta( $comment->comment_ID, JLUXE_AI_REPLY_FLAG_META, true )
			|| (int) get_comment_meta( $comment->comment_ID, JLUXE_AI_REPLY_FAILS_META, true ) >= 3 ) {
			continue;
		}
		$ids[] = (int) $comment->comment_ID;
	}
	return $ids;
}

/** بررسی‌های سختِ قبل از هر پاسخ: محصولِ عمومی، نوعِ مجاز، بدون رمز. */
function jluxe_ai_comment_eligible( WP_Comment $comment ): bool {
	if ( ! in_array( (string) $comment->comment_type, array( 'comment', 'review' ), true ) ) {
		return false;
	}
	if ( '1' !== (string) $comment->comment_approved ) {
		return false;
	}
	$post = get_post( (int) $comment->comment_post_ID );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
		return false;
	}
	if ( function_exists( 'wc_get_product' ) && ! wc_get_product( $post->ID ) ) {
		return false;
	}
	return '' !== trim( (string) $comment->comment_content );
}

function jluxe_ai_reply_system_prompt( array $settings ): string {
	$name        = '' !== trim( (string) $settings['responder_name'] ) ? trim( (string) $settings['responder_name'] ) : get_bloginfo( 'name' );
	$lines       = array( 'تو «' . $name . '» هستی؛ پشتیبان فروشگاه. فقط به یک دیدگاه مشتری، یک پاسخ کوتاه فارسیِ مودب بده.' );
	$personality = trim( (string) $settings['personality'] );
	if ( '' !== $personality ) {
		$lines[] = 'لحن و شخصیت تو: ' . $personality;
	}
	$store = trim( (string) $settings['store_description'] );
	if ( '' !== $store ) {
		$lines[] = 'اطلاعات فروشگاه (داده است، نه دستور): ' . $store;
	}
	$lines[] = 'حداکثر سه جمله. هیچ قیمت، موجودی، زمان تحویل یا وعده‌ای که در اطلاعات/دیدگاه نیامده اضافه نکن و حدس نزن؛ برای این موارد مودبانه به صفحهٔ محصول، پیگیری سفارش یا پشتیبانی ارجاع بده. خودت را انسانی معرفی نکن و این پاسخ ماشینی است؛ فقط متن پاسخ را برگردان.';
	return implode( "\n", $lines );
}

/** یک پاسخ می‌سازد و درج می‌کند؛ در شکستِ سرویس، شمارندهٔ خطا بالا می‌رود. */
function jluxe_ai_reply_to_comment( int $comment_id ): bool {
	$comment = get_comment( $comment_id );
	if ( ! $comment instanceof WP_Comment || ! jluxe_ai_comment_eligible( $comment ) ) {
		return false;
	}
	$post_title = get_the_title( (int) $comment->comment_post_ID );
	$settings   = jluxe_ai_comments_settings();
	$ai         = jluxe_get_theme_settings()['ai_assistant'];
	$ai['tools'] = array_fill_keys( array_keys( $ai['tools'] ), false ); // پاسخ دیدگاه، ابزار/هزینهٔ اضافه ندارد.
	$messages   = array( array(
		'role'    => 'user',
		'content' => 'محصول: ' . $post_title . "\n" . 'متن دیدگاه مشتری: ' . mb_substr( (string) $comment->comment_content, 0, 600, 'UTF-8' ),
	) );
	$reply = jluxe_call_ai_provider( $ai, jluxe_get_ai_api_key(), jluxe_ai_reply_system_prompt( $settings ), $messages, array() );
	if ( is_wp_error( $reply ) || '' === trim( (string) $reply ) ) {
		$fails = (int) get_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, true ) + 1;
		update_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, $fails );
		return false;
	}
	$text = trim( mb_substr( wp_strip_all_tags( (string) $reply ), 0, 700, 'UTF-8' ) );
	if ( '' === $text ) {
		update_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, (int) get_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, true ) + 1 );
		return false;
	}
	$responder = '' !== trim( (string) $settings['responder_name'] ) ? trim( (string) $settings['responder_name'] ) : get_bloginfo( 'name' );
	$reply_id  = wp_insert_comment( array(
		'comment_post_ID'      => (int) $comment->comment_post_ID,
		'comment_parent'       => (int) $comment->comment_ID,
		'comment_author'       => $responder,
		'comment_author_email' => '',
		'comment_author_url'   => '',
		'comment_content'      => $text,
		'comment_type'         => 'comment',
		'comment_approved'     => 1,
		'user_id'              => 0,
	) );
	if ( ! is_numeric( $reply_id ) || (int) $reply_id <= 0 ) {
		update_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, (int) get_comment_meta( $comment_id, JLUXE_AI_REPLY_FAILS_META, true ) + 1 );
		return false;
	}
	update_comment_meta( (int) $reply_id, JLUXE_AI_REPLY_FLAG_META, 1 );
	update_comment_meta( $comment_id, JLUXE_AI_REPLIED_META, time() );
	return true;
}

/** تا سقفِ بودجهٔ هر اجرا پاسخ می‌دهد؛ خروجی: تعداد پاسخ‌های موفق. */
function jluxe_ai_process_comment_replies( int $max ): int {
	$done = 0;
	foreach ( array_merge( jluxe_ai_pending_comment_ids( 'comment', $max * 3 ), jluxe_ai_pending_comment_ids( 'review', $max * 3 ) ) as $comment_id ) {
		if ( $done >= $max ) {
			break;
		}
		if ( jluxe_ai_reply_to_comment( $comment_id ) ) {
			++$done;
		}
	}
	return $done;
}

/** محصولات عمومیِ دارای دیدگاهِ تأییدشده که هنوز خلاصهٔ تولیدشده ندارند. */
function jluxe_ai_fill_missing_summaries( int $max ): int {
	if ( ! function_exists( 'jluxe_generate_ai_review_summary' ) ) {
		return 0;
	}
	$filled = 0;
	$post_ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => min( 50, $max * 10 ),
		'orderby'        => 'comment_count',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	foreach ( $post_ids as $product_id ) {
		if ( $filled >= $max ) {
			break;
		}
		$product_id = (int) $product_id;
		if ( '' !== trim( (string) get_post_meta( $product_id, JLUXE_AI_SUMMARY_META, true ) ) ) {
			continue; // متنِ دستیِ مدیر ملاک است؛ پولِ API هدر نرود.
		}
		$has_review = false;
		foreach ( array( 'review', 'comment' ) as $type ) {
			foreach ( get_comments( array( 'status' => 'approve', 'type' => $type, 'post_id' => $product_id, 'number' => 1 ) ) as $c ) {
				if ( $c instanceof WP_Comment ) {
					$has_review = true;
					break 2;
				}
			}
		}
		if ( ! $has_review ) {
			continue;
		}
		if ( '' === jluxe_get_ai_review_summary( $product_id ) ) {
			jluxe_generate_ai_review_summary( $product_id );
			if ( '' !== jluxe_get_ai_review_summary( $product_id ) ) {
				++$filled;
			}
		}
	}
	return $filled;
}

/* -------------------------------------------------------------------------
 * پیشخوان: صفحهٔ تنظیمات + دکمه‌های تست/پردازش دستی + متاباکس محصول.
 * ---------------------------------------------------------------------- */
function jluxe_ai_comments_admin_guard(): bool {
	return current_user_can( 'manage_options' ) && (bool) check_ajax_referer( 'jluxe_ai_admin', 'nonce', false );
}

/** یک درخواستِ کوچکِ بی‌ابزار برای اطمینان از سلامت کلید/سرویس/مسیر. */
function jluxe_ai_admin_test_connection(): void {
	if ( ! jluxe_ai_comments_admin_guard() ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
	}
	$ai = jluxe_get_theme_settings()['ai_assistant'];
	if ( '' === (string) jluxe_get_ai_api_key() || '' === (string) $ai['provider'] ) {
		wp_send_json_error( array( 'message' => 'اول سرویس و کلید API دستیار را در پنل تنظیم و ذخیره کنید.' ), 400 );
	}
	$ai['tools'] = array_fill_keys( array_keys( $ai['tools'] ), false );
	// بدون دستکاریِ temperature/max_tokens — بدنه‌ی درخواست دقیقاً همان
	// چیزی است که لایه‌ی رسمی برای مدلِ انتخابی می‌سازد (override این‌جا
	// برای مدل‌های استدلالی پاسخِ خالی/400 می‌ساخت و تشخیص را گمراه می‌کرد).
	$start = microtime( true );
	$reply = jluxe_call_ai_provider( $ai, jluxe_get_ai_api_key(), 'فقط با «OK» پاسخ بده.', array( array( 'role' => 'user', 'content' => 'تست اتصال' ) ), array() );
	$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );
	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( array(
			'message' => $reply->get_error_message(),
			'kind'    => $reply->get_error_code(),
			'model'   => (string) $ai['model'],
		), 502 );
	}
	// بعد از فراخوانی بخوان — اگر fallback رخ داده باشد، هاستِ واقعیِ
	// موفق داخل خود فراخوانی cache شده است.
	$host = 'gapgpt' === $ai['provider'] ? ( (string) get_transient( 'jluxe_gapgpt_base' ) ) : (string) $ai['base_url'];
	$extra = 'gapgpt' === $ai['provider'] ? ' — مسیر: ' . ( 'https://api.gapapi.com/v1' === $host ? 'api.gapapi.com (CDN خارجی)' : 'api.gapgpt.app' ) : '';
	wp_send_json_success( array(
		'message' => 'اتصال برقرار است' . $extra . ' — مدل: ' . ( '' !== (string) $ai['model'] ? (string) $ai['model'] : 'پیش‌فرض' ),
		'ms'      => $ms,
	) );
}
add_action( 'wp_ajax_jluxe_ai_admin_test', 'jluxe_ai_admin_test_connection' );

/** همان کارِ Cron، دستی و بودجه‌دار. */
function jluxe_ai_admin_process_now(): void {
	if ( ! jluxe_ai_comments_admin_guard() ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
	}
	$result = jluxe_ai_comments_process_queue();
	wp_send_json_success( array(
		'message'   => sprintf( 'پاسخ ثبت‌شده: %d — خلاصهٔ تازه: %d. اگر هنوز موردی مانده، بعداً دوباره بزنید.', $result['replies'], $result['summaries'] ),
		'replies'   => $result['replies'],
		'summaries' => $result['summaries'],
	) );
}
add_action( 'wp_ajax_jluxe_ai_admin_process', 'jluxe_ai_admin_process_now' );

/** تولید خلاصهٔ حداکثر ۱۰ محصولِ فاقد خلاصه در هر کلیک (بدون timeout طولانی). */
function jluxe_ai_admin_generate_summaries(): void {
	if ( ! jluxe_ai_comments_admin_guard() ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
	}
	$filled = jluxe_ai_fill_missing_summaries( 10 );
	wp_send_json_success( array(
		'message' => $filled > 0
			? sprintf( '%d خلاصهٔ تازه ساخته شد. اگر محصولِ بدون خلاصه مانده، دوباره بزنید.', $filled )
			: 'محصولی بدون خلاصه پیدا نشد یا بودجهٔ سرویس پر است؛ کمی بعد دوباره امتحان کنید.',
		'filled'  => $filled,
	) );
}
add_action( 'wp_ajax_jluxe_ai_admin_summaries', 'jluxe_ai_admin_generate_summaries' );

/** بازتولید خلاصهٔ یک محصول از متاباکس؛ متنِ دستی را هم پاک می‌کند. */
function jluxe_ai_admin_regenerate_summary(): void {
	if ( ! current_user_can( 'edit_products' ) || ! (bool) check_ajax_referer( 'jluxe_ai_admin', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
	}
	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$product    = $product_id ? get_post( $product_id ) : null;
	if ( ! $product instanceof WP_Post || 'product' !== $product->post_type || ! current_user_can( 'edit_post', $product_id ) ) {
		wp_send_json_error( array( 'message' => 'محصول معتبر نیست.' ), 400 );
	}
	delete_post_meta( $product_id, JLUXE_AI_SUMMARY_META );
	delete_transient( 'jluxe_ai_review_summary_' . $product_id );
	jluxe_generate_ai_review_summary( $product_id );
	$summary = jluxe_get_ai_review_summary( $product_id );
	if ( '' === $summary ) {
		wp_send_json_error( array( 'message' => 'خلاصهٔ تازه ساخته نشد (بودجه/سرویس). چند دقیقه بعد دوباره تلاش کنید.' ), 502 );
	}
	wp_send_json_success( array( 'summary' => $summary ) );
}
add_action( 'wp_ajax_jluxe_ai_regenerate_summary', 'jluxe_ai_admin_regenerate_summary' );

/* متاباکس ویرایش محصول: خلاصهٔ دستی که همیشه بر خلاصهٔ خودکار مقدم است. */
add_action( 'add_meta_boxes', function (): void {
	add_meta_box(
		'jluxe-ai-review-summary',
		'خلاصه دیدگاه‌ها (هوش مصنوعی)',
		'jluxe_ai_render_review_metabox',
		'product',
		'normal',
		'default'
	);
} );

function jluxe_ai_render_review_metabox( WP_Post $post ): void {
	wp_nonce_field( 'jluxe_ai_review_metabox', '_jluxe_ai_meta_nonce' );
	$manual = trim( (string) get_post_meta( $post->ID, JLUXE_AI_SUMMARY_META, true ) );
	$auto   = '' === $manual ? (string) jluxe_get_ai_review_summary( $post->ID ) : '';
	$value  = '' !== $manual ? $manual : $auto;
	$nonce  = wp_create_nonce( 'jluxe_ai_admin' );
	?>
	<p>
		<label for="jluxe_ai_manual_summary"><strong>متن خلاصه</strong></label>
		<textarea id="jluxe_ai_manual_summary" name="jluxe_ai_manual_summary" rows="5" class="large-text" maxlength="1500"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			اگر این‌جا متنی ذخیره کنید، همین متن (ویرایش خودتان) در صفحهٔ محصول نمایش داده می‌شود و تولید خودکار برای این محصول متوقف می‌شود؛ برای بازگشت به تولید خودکار، این‌جا را خالی و ذخیره کنید.
			<?php if ( '' !== $auto ) : ?>خلاصهٔ خودکارِ فعلی در کادر پیش‌بارگذاری شده است.<?php endif; ?>
		</p>
	</p>
	<p>
		<button type="button" class="button" id="jluxe-ai-regen" data-product="<?php echo esc_attr( (string) $post->ID ); ?>">حذف متن دستی و تولیدِ تازه با هوش مصنوعی</button>
		<span id="jluxe-ai-regen-result" style="margin-inline-start:10px;"></span>
	</p>
	<script>
	( function () {
		var btn = document.getElementById( 'jluxe-ai-regen' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'متنِ دستی فعلی حذف و خلاصهٔ تازه با هوش مصنوعی ساخته شود؟' ) ) { return; }
			var out = document.getElementById( 'jluxe-ai-regen-result' );
			btn.disabled = true;
			out.style.color = '#1d2327';
			out.textContent = '⏳ در حال تولید…';
			var fd = new FormData();
			fd.append( 'action', 'jluxe_ai_regenerate_summary' );
			fd.append( 'nonce', <?php echo wp_json_encode( $nonce ); ?> );
			fd.append( 'product_id', btn.getAttribute( 'data-product' ) );
			fetch( window.ajaxurl || '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( d ) {
					if ( d && d.success && d.data && d.data.summary ) {
						document.getElementById( 'jluxe_ai_manual_summary' ).value = d.data.summary;
						out.style.color = '#067d3a';
						out.textContent = '✅ خلاصهٔ تازه در کادر گذاشته شد؛ برای ماندگاری «به‌روزرسانی» را بزنید.';
					} else {
						out.style.color = '#b32d2e';
						out.textContent = '❌ ' + ( d && d.data && d.data.message ? d.data.message : 'خطا' );
					}
				} )
				.catch( function () { out.style.color = '#b32d2e'; out.textContent = '❌ خطای ارتباط با سرور'; } )
				.finally( function () { btn.disabled = false; } );
		} );
	} )();
	</script>
	<?php
}

function jluxe_ai_save_review_metabox( int $post_id ): void {
	if ( ! isset( $_POST['_jluxe_ai_meta_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_jluxe_ai_meta_nonce'] ) ), 'jluxe_ai_review_metabox' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['jluxe_ai_manual_summary'] ) ) {
		return;
	}
	$text = trim( mb_substr( sanitize_textarea_field( wp_unslash( (string) $_POST['jluxe_ai_manual_summary'] ) ), 0, 1500, 'UTF-8' ) );
	if ( '' === $text ) {
		delete_post_meta( $post_id, JLUXE_AI_SUMMARY_META );
	} else {
		update_post_meta( $post_id, JLUXE_AI_SUMMARY_META, $text );
	}
}
add_action( 'save_post_product', 'jluxe_ai_save_review_metabox' );

/* -------------------------------------------------------------------------
 * نمایش شفاف در سایت: کلاس + برچسب «پاسخ خودکار» + آواتار اختصاصی.
 * ---------------------------------------------------------------------- */
add_filter( 'comment_class', function ( array $classes, $comment ): array {
	if ( $comment instanceof WP_Comment && get_comment_meta( $comment->comment_ID, JLUXE_AI_REPLY_FLAG_META, true ) ) {
		$classes[] = 'jluxe-ai-reply';
	}
	return $classes;
}, 10, 2 );

/** برچشفِ شفاف «پاسخ خودکار» — تابع مستقل تا رفتارش مستقیم تست شود. */
function jluxe_ai_comment_reply_badge( $text, $comment ): string {
	if ( $comment instanceof WP_Comment && get_comment_meta( $comment->comment_ID, JLUXE_AI_REPLY_FLAG_META, true ) && false === strpos( (string) $text, 'jluxe-ai-reply-badge' ) ) {
		$text .= ' <span class="jluxe-ai-reply-badge">پاسخ خودکار فروشگاه (هوش مصنوعی)</span>';
	}
	return $text;
}
add_filter( 'comment_text', 'jluxe_ai_comment_reply_badge', 10, 2 );

add_filter( 'pre_get_avatar_data', function ( array $args, $id_or_email ): array {
	if ( ! $id_or_email instanceof WP_Comment || ! get_comment_meta( $id_or_email->comment_ID, JLUXE_AI_REPLY_FLAG_META, true ) ) {
		return $args;
	}
	$url = trim( (string) ( jluxe_ai_comments_settings()['responder_avatar'] ?? '' ) );
	if ( '' === $url || '' === esc_url( $url ) ) {
		return $args;
	}
	$args['url']          = esc_url( $url );
	$args['found_avatar'] = true;
	return $args;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * صفحهٔ تنظیمات: «دیدگاه‌ها (AI)» — الگوی ذخیرهٔ همان صفحات دیگر پنل.
 * ---------------------------------------------------------------------- */
function jluxe_render_ai_comments_page(): void {
	$status = null;
	if ( isset( $_POST['jluxe_settings_nonce'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
		if ( ! empty( $_POST['jluxe_reset_section'] ) ) {
			jluxe_update_settings_section( 'ai_comments', jluxe_theme_settings_defaults()['ai_comments'] );
			$status = 'reset';
		} else {
			$defaults = jluxe_theme_settings_defaults();
			$posted   = wp_unslash( $_POST['ai_comments'] ?? array() );
			$clean    = jluxe_sanitize_ai_comments( $posted, $defaults['ai_comments'] );
			jluxe_update_settings_section( 'ai_comments', $clean );
			$status = 'saved';
		}
	}
	$settings = jluxe_get_fresh_settings();
	$c        = $settings['ai_comments'];
	$ready    = '' !== jluxe_get_ai_api_key() && '' !== (string) $settings['ai_assistant']['provider'];
	jluxe_ai_comments_schedule(); // خودترمیم: اگر رویداد Cron گم شده باشد، همین‌جا برمی‌گردد.

	jluxe_settings_page_shell( 'دیدگاه‌ها (AI)', 'jluxe-ai-comments', $status, function () use ( $c, $ready ) {
		$nonce = wp_create_nonce( 'jluxe_ai_admin' );
		?>
		<?php if ( ! $ready ) : ?>
			<div class="notice notice-warning inline"><p>این بخش به سرویس و کلید API دستیار هوش مصنوعی وابسته است. اول از صفحهٔ «دستیار هوش مصنوعی» سرویس/کلید را تنظیم کنید.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>
			<h2>قابلیت‌ها</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی</th>
					<td>
						<label><input type="checkbox" name="ai_comments[enabled]" value="1" <?php checked( $c['enabled'] ); ?> /> خلاصه‌سازی خودکار دیدگاه‌های محصول (همان خلاصه‌ای که در صفحهٔ محصول نمایش داده می‌شود)</label><br />
						<label><input type="checkbox" name="ai_comments[auto_reply_enabled]" value="1" <?php checked( $c['auto_reply_enabled'] ); ?> /> پاسخ خودکار به دیدگاه‌ها (با برچشف «پاسخ خودکار فروشگاه»)</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-c-interval">زمان‌بندی پردازش خودکار</label></th>
					<td>
						<select id="jluxe-ai-c-interval" name="ai_comments[cron_interval]">
							<?php foreach ( array( 1 => 'هر ۱ دقیقه', 5 => 'هر ۵ دقیقه', 10 => 'هر ۱۰ دقیقه', 15 => 'هر ۱۵ دقیقه', 30 => 'هر ۳۰ دقیقه', 60 => 'هر ۱ ساعت' ) as $value => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) $c['cron_interval'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">فقط وقتی دیدگاهِ بی‌پاسخ یا محصولِ بدون خلاصه باشد تماس با سرویس می‌رود. WP-Cron باید فعال باشد (اگر DISABLE_WP_CRON تعریف کرده‌اید، Cron واقعی سرور را تنظیم کنید).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">سقف هر اجرا</th>
					<td>
						<label>حداکثر پاسخ در هر اجرا: <input type="number" min="1" max="20" name="ai_comments[max_replies_per_run]" value="<?php echo esc_attr( (string) $c['max_replies_per_run'] ); ?>" style="width:70px;" /></label>
						<label style="margin-inline-start:16px;">حداکثر خلاصه در هر اجرا: <input type="number" min="1" max="10" name="ai_comments[max_summaries_per_run]" value="<?php echo esc_attr( (string) $c['max_summaries_per_run'] ); ?>" style="width:70px;" /></label>
						<p class="description">سقفِ کلِ ساعتِ خلاصه‌سازی هم جداگانه کنترل می‌شود تا هزینهٔ سرویس کنترل‌شده بماند.</p>
					</td>
				</tr>
			</table>
			<h2>شخصی‌سازی پاسخ‌دهنده</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="jluxe-ai-c-name">نام پاسخ‌دهنده</label></th>
					<td><input type="text" id="jluxe-ai-c-name" name="ai_comments[responder_name]" value="<?php echo esc_attr( $c['responder_name'] ); ?>" class="regular-text" />
						<p class="description">خالی = نام سایت. پاسخ با همین نام منتشر می‌شود.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-c-avatar">آواتار پاسخ‌دهنده</label></th>
					<td><input type="url" id="jluxe-ai-c-avatar" dir="ltr" name="ai_comments[responder_avatar]" value="<?php echo esc_attr( $c['responder_avatar'] ); ?>" class="regular-text" placeholder="https://…" />
						<?php if ( '' !== $c['responder_avatar'] ) : ?><p><img src="<?php echo esc_url( $c['responder_avatar'] ); ?>" style="max-width:60px;max-height:60px;margin-top:8px;border-radius:8px;" alt="" /></p><?php endif; ?>
						<p class="description">آدرس تصویر؛ فقط برای پاسخ‌های خودکار به‌جای Gravatar نمایش داده می‌شود.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-c-personality">شخصیت و لحن</label></th>
					<td><textarea id="jluxe-ai-c-personality" name="ai_comments[personality]" rows="4" class="large-text" placeholder="مثال: صمیمی، محترم و کوتاه؛ برای ظروف آشپزخانه…"><?php echo esc_textarea( $c['personality'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-c-store">توضیحات فروشگاه</label></th>
					<td><textarea id="jluxe-ai-c-store" name="ai_comments[store_description]" rows="4" class="large-text" placeholder="حوزه فعالیت، ساعت پاسخگویی، روش‌های ارسال…"><?php echo esc_textarea( $c['store_description'] ); ?></textarea>
						<p class="description">این متن‌ها به مدل داده می‌شوند؛ حدس زدن قیمت/موجودی/زمان تحویل همیشه ممنوع است.</p></td>
				</tr>
			</table>
			<h2>عملیات</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">تست اتصال</th>
					<td>
						<button type="button" class="button" id="jluxe-ai-c-test">تست اتصال به سرویس</button>
						<span id="jluxe-ai-c-test-res" style="margin-inline-start:10px;"></span>
						<p class="description">یک درخواستِ کوچکِ کم‌هزینه می‌فرستد. اول تنظیمات دستیار را ذخیره کنید. هر دو آدرس رسمی GapGPT (اصلی و CDN خارجی) به‌طور خودکار امتحان می‌شوند و مسیرِ موفق در پیام نتیجه اعلام می‌شود.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">پردازش دستی دیدگاه‌ها</th>
					<td>
						<button type="button" class="button button-secondary" id="jluxe-ai-c-process">پردازش الان (بدون انتظار برای Cron)</button>
						<span id="jluxe-ai-c-process-res" style="margin-inline-start:10px;"></span>
					</td>
				</tr>
				<tr>
					<th scope="row">تولید خلاصهٔ محصولات</th>
					<td>
						<button type="button" class="button button-primary" id="jluxe-ai-c-summaries">ساخت خلاصه برای محصولاتِ بدون خلاصه</button>
						<span id="jluxe-ai-c-summaries-res" style="margin-inline-start:10px;"></span>
						<p class="description">هر کلیک حداکثر ۱۰ محصول؛ برای فروشگاه بزرگ چند بار بزنید. متن‌های دستیِ متاباکس دست‌نخورده می‌مانند.</p>
					</td>
				</tr>
			</table>
			<script>
			( function () {
				var CFG = { ajax: <?php echo wp_json_encode( esc_url_raw( admin_url( 'admin-ajax.php' ) ) ); ?>, nonce: <?php echo wp_json_encode( $nonce ); ?> };
				function call( action, button, output, confirmText ) {
					if ( confirmText && ! window.confirm( confirmText ) ) { return; }
					button.disabled = true;
					output.style.color = '#1d2327';
					output.textContent = '⏳ …';
					var fd = new FormData();
					fd.append( 'action', action );
					fd.append( 'nonce', CFG.nonce );
					fetch( CFG.ajax, { method: 'POST', credentials: 'same-origin', body: fd } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( d ) {
							if ( d && d.success ) {
								output.style.color = '#067d3a';
								output.textContent = '✅ ' + ( d.data && d.data.message ? d.data.message : 'انجام شد' );
							} else {
								output.style.color = '#b32d2e';
								output.textContent = '❌ ' + ( d && d.data && d.data.message ? d.data.message : 'خطا' );
							}
						} )
						.catch( function () { output.style.color = '#b32d2e'; output.textContent = '❌ خطای ارتباط با سرور'; } )
						.finally( function () { button.disabled = false; } );
				}
				function bind( buttonId, outputId, action, confirmText ) {
					var btn = document.getElementById( buttonId );
					if ( btn ) { btn.addEventListener( 'click', function () { call( action, btn, document.getElementById( outputId ), confirmText ); } ); }
				}
				bind( 'jluxe-ai-c-test', 'jluxe-ai-c-test-res', 'jluxe_ai_admin_test' );
				bind( 'jluxe-ai-c-process', 'jluxe-ai-c-process-res', 'jluxe_ai_admin_process' );
				bind( 'jluxe-ai-c-summaries', 'jluxe-ai-c-summaries-res', 'jluxe_ai_admin_summaries', 'برای محصولاتِ بدون خلاصه، خلاصهٔ تازه ساخته شود؟' );
			} )();
			</script>
			<?php submit_button( 'ذخیرهٔ تغییرات' ); ?>
			<?php submit_button( 'بازگشت به پیش‌فرض', 'delete', 'jluxe_reset_section', false ); ?>
		</form>
		<?php
	} );
}
