<?php
/**
 * پرسش و پاسخ محصول.
 *
 * این یک فیچر سفارشیِ جدید است، نه بخشی از ووکامرس استاندارد — بوم هم آن را
 * با یک افزونه/فیچر اختصاصی پیاده کرده بود، نه با هسته‌ی ووکامرس. این‌جا با
 * یک Custom Post Type واقعی (jluxe_question) پیاده شده که هر پرسش تا وقتی
 * ادمین از طریق متاباکس زیر پاسخ ندهد و پست را منتشر نکند، در سایت دیده
 * نمی‌شود — یعنی داده‌ی واقعیه، نه دموی ثابت.
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت نوع پست پرسش‌وپاسخ.
 */
function jluxe_register_qa_post_type() {
	register_post_type(
		'jluxe_question',
		array(
			'labels'          => array(
				'name'          => __( 'پرسش و پاسخ محصولات', 'jluxe' ),
				'singular_name' => __( 'پرسش', 'jluxe' ),
				'all_items'     => __( 'همه‌ی پرسش‌ها', 'jluxe' ),
				'edit_item'     => __( 'ویرایش / پاسخ به پرسش', 'jluxe' ),
				'search_items'  => __( 'جستجوی پرسش‌ها', 'jluxe' ),
				'not_found'     => __( 'پرسشی یافت نشد.', 'jluxe' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-editor-help',
			'menu_position'   => 26,
			'supports'        => array( 'editor' ),
			'capability_type' => 'post',
		)
	);
}
add_action( 'init', 'jluxe_register_qa_post_type' );

/**
 * ستون‌های اختصاصی در لیست ادمین: محصول مرتبط + وضعیت پاسخ.
 */
function jluxe_qa_admin_columns( array $columns ): array {
	$date = $columns['date'] ?? null;
	unset( $columns['date'] );

	$columns['jluxe_qa_product'] = __( 'محصول', 'jluxe' );
	$columns['jluxe_qa_answer']  = __( 'وضعیت پاسخ', 'jluxe' );

	if ( $date ) {
		$columns['date'] = $date;
	}

	return $columns;
}
add_filter( 'manage_jluxe_question_posts_columns', 'jluxe_qa_admin_columns' );

function jluxe_qa_admin_column_content( string $column, int $post_id ): void {
	if ( 'jluxe_qa_product' === $column ) {
		$product_id = (int) get_post_meta( $post_id, '_jluxe_qa_product_id', true );
		$product    = $product_id ? get_post( $product_id ) : null;

		if ( $product ) {
			printf(
				'<a href="%s">%s</a>',
				esc_url( (string) get_edit_post_link( $product_id ) ),
				esc_html( $product->post_title )
			);
		} else {
			echo '—';
		}
	}

	if ( 'jluxe_qa_answer' === $column ) {
		$answer = get_post_meta( $post_id, '_jluxe_qa_answer', true );

		if ( $answer ) {
			echo '<span style="color:#0e6d7a;font-weight:600;">' . esc_html__( 'پاسخ داده شده', 'jluxe' ) . '</span>';
		} else {
			echo '<span style="color:#a15c00;font-weight:600;">' . esc_html__( 'بدون پاسخ', 'jluxe' ) . '</span>';
		}
	}
}
add_action( 'manage_jluxe_question_posts_custom_column', 'jluxe_qa_admin_column_content', 10, 2 );

/**
 * متاباکس پاسخ فروشگاه — روی صفحه‌ی ویرایش پرسش.
 */
function jluxe_qa_register_meta_box(): void {
	add_meta_box(
		'jluxe_qa_answer_box',
		__( 'جزئیات پرسش و پاسخ فروشگاه', 'jluxe' ),
		'jluxe_qa_render_meta_box',
		'jluxe_question',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'jluxe_qa_register_meta_box' );

function jluxe_qa_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'jluxe_qa_save_answer', 'jluxe_qa_answer_nonce' );

	$product_id  = (int) get_post_meta( $post->ID, '_jluxe_qa_product_id', true );
	$product     = $product_id ? get_post( $product_id ) : null;
	$author_name = get_post_meta( $post->ID, '_jluxe_qa_author_name', true );
	$answer      = get_post_meta( $post->ID, '_jluxe_qa_answer', true );
	?>
	<p>
		<strong><?php esc_html_e( 'محصول:', 'jluxe' ); ?></strong>
		<?php if ( $product ) : ?>
			<a href="<?php echo esc_url( (string) get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( $product->post_title ); ?></a>
		<?php else : ?>
			<?php esc_html_e( 'نامشخص', 'jluxe' ); ?>
		<?php endif; ?>
	</p>

	<?php if ( $author_name ) : ?>
		<p><strong><?php esc_html_e( 'نام مشتری:', 'jluxe' ); ?></strong> <?php echo esc_html( $author_name ); ?></p>
	<?php endif; ?>

	<p><strong><?php esc_html_e( 'متن پرسش:', 'jluxe' ); ?></strong></p>
	<p style="background:#f6f7f7;padding:10px;border-radius:6px;"><?php echo esc_html( $post->post_content ); ?></p>

	<p>
		<label for="jluxe_qa_answer"><strong><?php esc_html_e( 'پاسخ فروشگاه:', 'jluxe' ); ?></strong></label>
	</p>
	<textarea name="jluxe_qa_answer" id="jluxe_qa_answer" rows="4" style="width:100%;"><?php echo esc_textarea( $answer ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'برای نمایش پرسش و پاسخ در سایت، این متن را پر کنید و سپس پست را «منتشر» کنید.', 'jluxe' ); ?>
	</p>
	<?php
}

function jluxe_qa_save_meta_box( int $post_id ): void {
	if ( ! isset( $_POST['jluxe_qa_answer_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_qa_answer_nonce'] ) ), 'jluxe_qa_save_answer' )
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['jluxe_qa_answer'] ) ) {
		update_post_meta(
			$post_id,
			'_jluxe_qa_answer',
			sanitize_textarea_field( wp_unslash( $_POST['jluxe_qa_answer'] ) )
		);
	}
}
add_action( 'save_post_jluxe_question', 'jluxe_qa_save_meta_box' );

/**
 * ثبت پرسش از سمت مشتری — AJAX، بدون نیاز به لاگین. پست با وضعیت «در
 * انتظار بررسی» (pending) ذخیره می‌شود و تا پاسخ و انتشار توسط ادمین،
 * در سایت نمایش داده نمی‌شود.
 */
function jluxe_qa_handle_submit(): void {
	check_ajax_referer( 'jluxe_qa_submit', 'jluxe_qa_nonce' );

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$question   = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
	$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

	if ( ! $product_id || '' === trim( $question ) ) {
		wp_send_json_error( array( 'message' => __( 'لطفاً متن پرسش را وارد کنید.', 'jluxe' ) ) );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'jluxe_question',
			'post_status'  => 'pending',
			'post_title'   => wp_trim_words( $question, 8, '…' ),
			'post_content' => $question,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'jluxe' ) ) );
	}

	update_post_meta( $post_id, '_jluxe_qa_product_id', $product_id );

	if ( $name ) {
		update_post_meta( $post_id, '_jluxe_qa_author_name', $name );
	}

	wp_send_json_success(
		array( 'message' => __( 'پرسش شما ثبت شد و پس از بررسی فروشگاه نمایش داده می‌شود.', 'jluxe' ) )
	);
}
add_action( 'wp_ajax_jluxe_qa_submit', 'jluxe_qa_handle_submit' );
add_action( 'wp_ajax_nopriv_jluxe_qa_submit', 'jluxe_qa_handle_submit' );

/**
 * پرسش‌های منتشرشده‌ی یک محصول.
 *
 * @return WP_Post[]
 */
function jluxe_get_product_questions( int $product_id ): array {
	return get_posts(
		array(
			'post_type'      => 'jluxe_question',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_jluxe_qa_product_id',
					'value'   => $product_id,
					'compare' => '=',
				),
			),
		)
	);
}

/**
 * رندر بخش پرسش‌وپاسخ زیر خلاصه‌ی صفحه‌ی محصول ووکامرس.
 */
function jluxe_render_qa_section(): void {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$product_id = $product->get_id();
	$questions  = jluxe_get_product_questions( $product_id );
	?>
	<div id="qa" class="jluxe-qa mx-auto mt-10 max-w-[1296px] scroll-mt-24 px-4">
		<h2 class="flex items-center gap-2 text-h2 text-foreground">
			<span class="h-5 w-1 rounded-full bg-primary" aria-hidden="true"></span>
			<?php esc_html_e( 'پرسش و پاسخ', 'jluxe' ); ?>
		</h2>

		<form class="jluxe-qa-form mt-4 flex flex-col items-start gap-3 rounded-xl border border-border p-5" data-jluxe-qa-form>
			<?php wp_nonce_field( 'jluxe_qa_submit', 'jluxe_qa_nonce' ); ?>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />

			<p class="text-body font-medium text-foreground"><?php esc_html_e( 'پرسشی درباره این کالا دارید؟', 'jluxe' ); ?></p>
			<textarea
				name="question"
				required
				rows="3"
				class="w-full rounded-lg border border-border p-3 text-small"
				placeholder="<?php esc_attr_e( 'سوال خود را بنویسید…', 'jluxe' ); ?>"
			></textarea>
			<input
				type="text"
				name="name"
				class="w-full max-w-xs rounded-lg border border-border p-2 text-small"
				placeholder="<?php esc_attr_e( 'نام شما (اختیاری)', 'jluxe' ); ?>"
			/>
			<button
				type="submit"
				class="w-fit rounded-lg bg-primary px-5 py-2.5 text-button text-primary-foreground transition-colors hover:bg-primary-hover"
			>
				<?php esc_html_e( 'ثبت پرسش', 'jluxe' ); ?>
			</button>
			<p class="jluxe-qa-form-message hidden text-small" data-jluxe-qa-message></p>
		</form>

		<div class="jluxe-qa-list mt-6 flex flex-col divide-y divide-border">
			<?php if ( empty( $questions ) ) : ?>
				<p class="py-4 text-center text-small text-text-muted">
					<?php esc_html_e( 'هنوز پرسشی ثبت نشده است. اولین نفر باشید!', 'jluxe' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $questions as $q ) :
					$answer = get_post_meta( $q->ID, '_jluxe_qa_answer', true );
					$name   = get_post_meta( $q->ID, '_jluxe_qa_author_name', true );
					?>
					<div class="py-4 first:pt-0">
						<p class="text-small font-medium text-foreground">
							<?php echo esc_html( $name ? $name : __( 'کاربر زرین', 'jluxe' ) ); ?>
							<span class="text-caption font-normal text-text-muted"><?php echo esc_html( get_the_date( '', $q ) ); ?></span>
						</p>
						<p class="mt-1 text-body text-text-secondary"><?php echo esc_html( $q->post_content ); ?></p>
						<?php if ( $answer ) : ?>
							<div class="mt-2 rounded-lg bg-muted/60 p-3">
								<p class="text-caption font-medium text-primary"><?php esc_html_e( 'پاسخ زرین', 'jluxe' ); ?></p>
								<p class="mt-1 text-small text-text-secondary"><?php echo esc_html( $answer ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'jluxe_render_qa_section', 15 );

/**
 * اسکریپت ارسال AJAX فرم پرسش — فقط در صفحه‌ی محصول لود می‌شه.
 */
function jluxe_qa_enqueue_script(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	wp_enqueue_script(
		'jluxe-qa',
		JLUXE_THEME_URI . '/assets/js/qa.js',
		array(),
		'1.0.0',
		true
	);

	wp_localize_script( 'jluxe-qa', 'jluxeQa', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'wp_enqueue_scripts', 'jluxe_qa_enqueue_script' );
