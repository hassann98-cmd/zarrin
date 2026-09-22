<?php
/**
 * دستیار هوش مصنوعی — تنظیمات ادمین + REST endpoint سمت سرور. کلید API
 * هیچ‌وقت به مرورگر/جاوااسکریپت فرستاده نمی‌شه: فقط سرور (این فایل) بهش
 * دسترسی داره، JS فرانت فقط تاریخچه‌ی مکالمه رو به /wp-json/jluxe/v1/assistant
 * می‌فرسته و متن جواب رو پس می‌گیره.
 *
 * هیچ پاسخ ساختگی/فرضی تولید نمی‌شه — اگر provider/کلید تنظیم نشده باشه،
 * endpoint خطای صریح «پیکربندی نشده» برمی‌گردونه، نه یک پاسخ ثابتِ فیک؛ و
 * تمام ابزارهای دیتا (search_products، get_order_status و...) فقط از
 * WooCommerce/تنظیمات واقعیِ همین سایت می‌خونن، هیچ‌چیزی اختراع نمی‌کنن.
 */

defined( 'ABSPATH' ) || exit;

// =====================================================================
// صفحه‌ی تنظیمات
// =====================================================================
function jluxe_render_ai_assistant_page(): void {
	$status = null;

	if ( isset( $_POST['jluxe_settings_nonce'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jluxe_settings_nonce'] ) ), 'jluxe_save_settings' ) ) {
		if ( ! empty( $_POST['jluxe_reset_section'] ) ) {
			jluxe_update_settings_section( 'ai_assistant', jluxe_theme_settings_defaults()['ai_assistant'] );
			jluxe_set_ai_api_key( '' );
			$status = 'reset';
		} else {
			$defaults = jluxe_theme_settings_defaults();
			$posted   = wp_unslash( $_POST['ai_assistant'] ?? array() );
			$clean    = jluxe_sanitize_ai_assistant( $posted, $defaults['ai_assistant'] );
			jluxe_update_settings_section( 'ai_assistant', $clean );

			// کلید API فقط اگر کاربر چیز جدیدی تایپ کرده باشه به‌روزرسانی می‌شه —
			// فیلد رمز همیشه خالی رندر می‌شه، پس «خالی گذاشتن» به‌معنای «تغییر نده»ست،
			// نه «پاک کن» (اون یک دکمه‌ی جدای explicit داره).
			if ( ! empty( $_POST['ai_api_key'] ) ) {
				jluxe_set_ai_api_key( sanitize_text_field( wp_unslash( $_POST['ai_api_key'] ) ) );
			}
			if ( ! empty( $_POST['ai_api_key_clear'] ) ) {
				jluxe_set_ai_api_key( '' );
			}
			$status = 'saved';
		}
	}

	$settings = jluxe_get_fresh_settings();
	$ai       = $settings['ai_assistant'];
	$has_key  = '' !== jluxe_get_ai_api_key();

	jluxe_settings_page_shell( 'دستیار هوش مصنوعی', 'jluxe-ai-assistant', $status, function () use ( $ai, $has_key ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'jluxe_save_settings', 'jluxe_settings_nonce' ); ?>

			<h2>عمومی</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی دستیار</th>
					<td><label><input type="checkbox" name="ai_assistant[enabled]" value="1" <?php checked( $ai['enabled'] ); ?> /> نمایش ویجت گفتگو در سایت</label>
						<?php if ( $ai['enabled'] && ! $has_key ) : ?>
							<p class="description" style="color:#b32d2e">فعاله ولی کلید API تنظیم نشده — ویجت تا زمان تنظیم کلید در سایت نمایش داده نمی‌شه.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-name">نام دستیار</label></th>
					<td><input type="text" id="jluxe-ai-name" name="ai_assistant[name]" value="<?php echo esc_attr( $ai['name'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-welcome">پیام خوش‌آمدگویی</label></th>
					<td><textarea id="jluxe-ai-welcome" name="ai_assistant[welcome_message]" rows="2" class="large-text"><?php echo esc_textarea( $ai['welcome_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row">آواتار هدر ویجت</th>
					<td><?php jluxe_render_media_field( 'ai_assistant[avatar_id]', (int) $ai['avatar_id'], 'آیکون پیش‌فرض' ); ?>
						<p class="description">تصویر گرد بالای پنجره‌ی گفتگو (کنار نام دستیار).</p></td>
				</tr>
				<tr>
					<th scope="row">تصویر دکمه‌ی شناور</th>
					<td><?php jluxe_render_media_field( 'ai_assistant[button_id]', (int) $ai['button_id'], 'آیکون پیش‌فرض' ); ?></td>
				</tr>
				<tr>
					<th scope="row">نمایش</th>
					<td>
						<label><input type="checkbox" name="ai_assistant[show_desktop]" value="1" <?php checked( $ai['show_desktop'] ); ?> /> دسکتاپ</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="ai_assistant[show_mobile]" value="1" <?php checked( $ai['show_mobile'] ); ?> /> موبایل</label>
					</td>
				</tr>
			</table>

			<h2>اتصال به مدل هوش مصنوعی</h2>
			<p class="description">کلید API فقط سمت سرور ذخیره می‌شه (آپشن جدا، غیر از بقیه‌ی تنظیمات) و هیچ‌وقت به مرورگر/HTML/برون‌بری فرستاده نمی‌شه.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">ارائه‌دهنده</th>
					<td>
						<select name="ai_assistant[provider]" id="jluxe-ai-provider" data-jluxe-toggle-id="jluxe-ai-provider">
							<option value="">— انتخاب کن —</option>
							<option value="openai" <?php selected( $ai['provider'], 'openai' ); ?>>OpenAI</option>
							<option value="anthropic" <?php selected( $ai['provider'], 'anthropic' ); ?>>Anthropic</option>
							<option value="gapgpt" <?php selected( $ai['provider'], 'gapgpt' ); ?>>گپ‌جی‌پی‌تی (GapGPT)</option>
							<option value="custom" <?php selected( $ai['provider'], 'custom' ); ?>>سازگار با OpenAI (پروکسی/سرویس دیگر)</option>
						</select>
						<?php if ( 'gapgpt' === $ai['provider'] ) : ?>
							<p class="description">آدرس سرویس (<code dir="ltr">https://api.gapgpt.app/v1</code>) خودکار استفاده می‌شه — نیازی به تنظیم نیست.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr data-jluxe-show-if="jluxe-ai-provider:custom">
					<th scope="row"><label for="jluxe-ai-baseurl">Base URL</label></th>
					<td><input type="text" id="jluxe-ai-baseurl" dir="ltr" class="regular-text" name="ai_assistant[base_url]" value="<?php echo esc_attr( $ai['base_url'] ); ?>" placeholder="https://api.example.com/v1" />
						<p class="description">فقط برای «سازگار با OpenAI» — آدرس پایه‌ی سرویسی که همون فرمت chat/completions را پیاده‌سازی کرده.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-model">مدل</label></th>
					<td><input type="text" id="jluxe-ai-model" name="ai_assistant[model]" value="<?php echo esc_attr( $ai['model'] ); ?>" class="regular-text" placeholder="مثلاً gpt-4o-mini یا claude-sonnet-5 یا gapgpt-qwen-3.6" dir="ltr" />
						<?php if ( 'gapgpt' === $ai['provider'] ) : ?>
							<p class="description">گپ‌جی‌پی‌تی چند مدل داره (مثلاً <code dir="ltr">gpt-4o</code>، <code dir="ltr">claude-sonnet-5</code>، <code dir="ltr">gapgpt-qwen-3.6</code> و…) — دقیقاً همون اسمی که خودِ گپ‌جی‌پی‌تی برای مدلِ موردنظرت می‌ده رو این‌جا بذار.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-key">کلید API</label></th>
					<td>
						<input type="password" id="jluxe-ai-key" name="ai_api_key" value="" class="regular-text" placeholder="<?php echo $has_key ? '•••••••••••••••• (تنظیم شده — برای تغییر، مقدار جدید بنویس)' : 'هنوز تنظیم نشده'; ?>" dir="ltr" autocomplete="off" />
						<?php if ( $has_key ) : ?>
							<label><input type="checkbox" name="ai_api_key_clear" value="1" onclick="return confirm('کلید API حذف بشه؟ دستیار تا تنظیم دوباره کار نمی‌کنه.');" /> حذف کلید فعلی</label>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-temp">Temperature</label></th>
					<td><input type="number" id="jluxe-ai-temp" step="0.1" min="0" max="2" name="ai_assistant[temperature]" value="<?php echo esc_attr( $ai['temperature'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-tokens">حداکثر توکن پاسخ</label></th>
					<td><input type="number" id="jluxe-ai-tokens" min="50" max="4000" name="ai_assistant[max_tokens]" value="<?php echo esc_attr( $ai['max_tokens'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row">شدت استدلال (Reasoning)</th>
					<td>
						<select name="ai_assistant[reasoning_effort]">
							<option value="minimal" <?php selected( $ai['reasoning_effort'], 'minimal' ); ?>>حداقل (سریع‌ترین)</option>
							<option value="low" <?php selected( $ai['reasoning_effort'], 'low' ); ?>>کم (پیشنهادی)</option>
							<option value="medium" <?php selected( $ai['reasoning_effort'], 'medium' ); ?>>متوسط</option>
							<option value="high" <?php selected( $ai['reasoning_effort'], 'high' ); ?>>زیاد (کندترین)</option>
						</select>
						<p class="description">فقط وقتی مدل واقعاً از خانواده‌ی reasoning باشه (مثل o-series یا gpt-5) اعمال می‌شه؛ برای بقیه‌ی مدل‌ها بی‌اثره.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-prompt">System Prompt (اختیاری)</label></th>
					<td>
						<textarea id="jluxe-ai-prompt" class="large-text code" rows="6" name="ai_assistant[system_prompt]" placeholder="خالی = استفاده از پرامپت پیش‌فرض داخلی (بر اساس دانش/ابزارهای فعال همین صفحه)"><?php echo esc_textarea( $ai['system_prompt'] ); ?></textarea>
						<p class="description">اگه خالی بمونه، دستیار یک پرامپت پیش‌فرض بر اساس تنظیمات همین صفحه (منابع دانش، ابزارها، راه‌های ارجاع به پشتیبانی) خودش می‌سازه.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">تست اتصال</th>
					<td>
						<button type="button" class="button" id="jluxe-ai-test-conn">تست اتصال به سرویس</button>
						<span id="jluxe-ai-test-res" style="margin-inline-start:10px;font-weight:600;"></span>
						<p class="description">یک درخواست ساده بدون ابزار می‌فرستد. اول تنظیمات را ذخیره کن، بعد تست بزن.</p>
					</td>
				</tr>
			</table>

			<h2>خلاصه‌ی هوشمند نظرات محصول</h2>
			<p class="description">یک خلاصه‌ی کوتاه از نظراتِ واقعیِ ثبت‌شده روی هر محصول، بالای لیست نظرات نشون داده می‌شه — با همون provider/کلید بالا. نتیجه کش می‌شه (فقط وقتی نظر جدیدی تأیید بشه، دوباره ساخته می‌شه)، پس بار هر بازدید یک درخواست جدید به سرویس هوش مصنوعی نمی‌ره.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی</th>
					<td><label><input type="checkbox" name="ai_assistant[review_summary_enabled]" value="1" <?php checked( $ai['review_summary_enabled'] ?? false ); ?> /> نمایش خلاصه‌ی هوشمند بالای نظرات محصول</label>
						<?php if ( ( $ai['review_summary_enabled'] ?? false ) && ! $has_key ) : ?>
							<p class="description" style="color:#b32d2e">فعاله ولی کلید API تنظیم نشده — تا تنظیمِ کلید، خلاصه نمایش داده نمی‌شه.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-rs-min">حداقل تعداد نظر</label></th>
					<td><input type="number" id="jluxe-ai-rs-min" min="1" max="50" name="ai_assistant[review_summary_min_count]" value="<?php echo esc_attr( $ai['review_summary_min_count'] ?? 3 ); ?>" class="small-text" />
						<p class="description">اگه تعداد نظراتِ منتشرشده‌ی محصول کمتر از این باشه، خلاصه‌سازی معنی نداره و نمایش داده نمی‌شه.</p>
					</td>
				</tr>
			</table>

			<h2>منابع دانش</h2>
			<p class="description">این منابع همیشه (بدون نیاز به مکالمه) به‌عنوان زمینه‌ی ثابت به دستیار داده می‌شن.</p>
			<table class="form-table" role="presentation">
				<?php
				$knowledge_labels = array(
					'products'   => 'محصولات (نام/قیمت/موجودی)',
					'categories' => 'دسته‌بندی‌ها',
					'prices'     => 'قیمت‌ها',
					'stock'      => 'موجودی انبار',
					'shipping'   => 'اطلاعات ارسال',
					'returns'    => 'قوانین بازگشت کالا',
					'faq'        => 'سوالات متداول',
				);
				foreach ( $knowledge_labels as $key => $label ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><label><input type="checkbox" name="ai_assistant[knowledge][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $ai['knowledge'][ $key ] ) ); ?> /></label></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2>ابزارهای دیتا</h2>
			<p class="description">دستیار حین مکالمه، در صورت نیاز، این ابزارها را صدا می‌زند تا داده‌ی واقعی و لحظه‌ای بگیرد (نه حدس بزند).</p>
			<table class="form-table" role="presentation">
				<?php
				$tool_labels = array(
					'get_order_status'     => array( 'وضعیت سفارش', 'get_order_status' ),
					'search_products'      => array( 'جستجوی محصول', 'search_products' ),
					'get_product_info'     => array( 'جزئیات محصول', 'get_product_info' ),
					'get_categories'       => array( 'دسته‌بندی‌ها', 'get_categories' ),
					'recommend_products'   => array( 'پیشنهاد محصول', 'recommend_products' ),
					'get_product_reviews'  => array( 'نظرات محصول', 'get_product_reviews' ),
					'get_customer_context' => array( 'پروفایل مشتری', 'get_customer_context' ),
					'get_store_info'       => array( 'اطلاعات فروشگاه', 'get_store_info' ),
				);
				foreach ( $tool_labels as $key => [ $label, $code ] ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><label><input type="checkbox" name="ai_assistant[tools][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $ai['tools'][ $key ] ) ); ?> /> <code><?php echo esc_html( $code ); ?></code></label></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2>ویجت‌ها</h2>
			<p class="description">نوع محتوای غنی‌ای (کارت محصول، مقایسه، وضعیت سفارش و ...) که دستیار مجاز است هنگام پاسخ به‌صورت تصویری/ساخت‌یافته نشان دهد.</p>
			<table class="form-table" role="presentation">
				<?php
				$widget_labels = array(
					'order_status'           => array( 'وضعیت سفارش', 'order_status' ),
					'product_info'           => array( 'معرفی محصول', 'product_info' ),
					'product_recommendation' => array( 'پیشنهاد خرید', 'product_recommendation' ),
					'complementary_products' => array( 'محصولات مکمل', 'complementary_products' ),
					'product_compare'        => array( 'مقایسه محصول', 'product_compare' ),
					'pros_cons'              => array( 'مزایا و معایب', 'pros_cons' ),
					'product_summary'        => array( 'خلاصه محصول', 'product_summary' ),
					'reviews_analysis'       => array( 'تحلیل نظرات', 'reviews_analysis' ),
					'shipment_tracking'      => array( 'رهگیری مرسوله', 'shipment_tracking' ),
					'smart_offer'            => array( 'تخفیف هوشمند', 'smart_offer' ),
					'customer_context'       => array( 'پروفایل مشتری', 'customer_context' ),
					'support'                => array( 'پشتیبانی / اپراتور', 'support' ),
					'support_ticket'         => array( 'ثبت پیام برای پشتیبان', 'support_ticket' ),
					'buying_advisor'         => array( 'مشاور خرید مرحله‌ای', 'buying_advisor' ),
				);
				foreach ( $widget_labels as $key => [ $label, $code ] ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><label><input type="checkbox" name="ai_assistant[widgets][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $ai['widgets'][ $key ] ) ); ?> /> <code><?php echo esc_html( $code ); ?></code></label></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2>پیام‌های آماده</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="jluxe-ai-quick">Quick Replies (هر خط یک مورد)</label></th>
					<td><textarea id="jluxe-ai-quick" class="large-text" rows="4" name="ai_assistant[quick_replies]"><?php echo esc_textarea( implode( "\n", $ai['quick_replies'] ) ); ?></textarea></td>
				</tr>
			</table>

			<h2>ظاهر</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">رنگ اصلی</th><td><input type="text" name="ai_assistant[primary_color]" value="<?php echo esc_attr( $ai['primary_color'] ); ?>" class="jluxe-color-field" placeholder="خالی = رنگ اصلی سایت" /></td></tr>
				<tr><th scope="row">رنگ متن</th><td><input type="text" name="ai_assistant[text_color]" value="<?php echo esc_attr( $ai['text_color'] ); ?>" class="jluxe-color-field" /></td></tr>
				<tr><th scope="row">پس‌زمینه حباب کاربر</th><td><input type="text" name="ai_assistant[bg_user_color]" value="<?php echo esc_attr( $ai['bg_user_color'] ); ?>" class="jluxe-color-field" /></td></tr>
				<tr><th scope="row">پس‌زمینه حباب دستیار</th><td><input type="text" name="ai_assistant[bg_bot_color]" value="<?php echo esc_attr( $ai['bg_bot_color'] ); ?>" class="jluxe-color-field" /></td></tr>
				<tr><th scope="row">رنگ حالت خطا/ناراضی</th><td><input type="text" name="ai_assistant[negative_color]" value="<?php echo esc_attr( $ai['negative_color'] ); ?>" class="jluxe-color-field" /></td></tr>
				<tr>
					<th scope="row">موقعیت</th>
					<td>
						<label><input type="radio" name="ai_assistant[position]" value="end" <?php checked( $ai['position'], 'end' ); ?> /> پایین-چپ</label>
						&nbsp;&nbsp;
						<label><input type="radio" name="ai_assistant[position]" value="start" <?php checked( $ai['position'], 'start' ); ?> /> پایین-راست</label>
					</td>
				</tr>
				<tr>
					<th scope="row">فاصله دکمه شناور (دسکتاپ)</th>
					<td>
						<label style="margin-inline-end:14px;">از پایین: <input type="number" min="0" max="400" style="width:80px;" name="ai_assistant[offset_bottom_desktop]" value="<?php echo esc_attr( $ai['offset_bottom_desktop'] ); ?>" /> px</label>
						<label>از کنار: <input type="number" min="0" max="400" style="width:80px;" name="ai_assistant[offset_side_desktop]" value="<?php echo esc_attr( $ai['offset_side_desktop'] ); ?>" /> px</label>
					</td>
				</tr>
				<tr>
					<th scope="row">فاصله دکمه شناور (موبایل)</th>
					<td>
						<label style="margin-inline-end:14px;">از پایین: <input type="number" min="0" max="400" style="width:80px;" name="ai_assistant[offset_bottom_mobile]" value="<?php echo esc_attr( $ai['offset_bottom_mobile'] ); ?>" /> px</label>
						<label>از کنار: <input type="number" min="0" max="400" style="width:80px;" name="ai_assistant[offset_side_mobile]" value="<?php echo esc_attr( $ai['offset_side_mobile'] ); ?>" /> px</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-width">عرض پنجره (px)</label></th>
					<td><input type="number" id="jluxe-ai-width" name="ai_assistant[window_width]" value="<?php echo esc_attr( $ai['window_width'] ); ?>" min="280" max="520" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="jluxe-ai-radius">گردی گوشه (px)</label></th>
					<td><input type="number" id="jluxe-ai-radius" name="ai_assistant[border_radius]" value="<?php echo esc_attr( $ai['border_radius'] ); ?>" min="0" max="32" class="small-text" /></td>
				</tr>
			</table>

			<h2>پشتیبانی / ارجاع به انسان</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">WhatsApp</th><td><input type="url" dir="ltr" class="regular-text" name="ai_assistant[handoff_whatsapp]" value="<?php echo esc_attr( $ai['handoff_whatsapp'] ); ?>" placeholder="https://wa.me/98..." /></td></tr>
				<tr><th scope="row">Telegram</th><td><input type="url" dir="ltr" class="regular-text" name="ai_assistant[handoff_telegram]" value="<?php echo esc_attr( $ai['handoff_telegram'] ); ?>" placeholder="https://t.me/..." /></td></tr>
				<tr><th scope="row">فرم تماس (URL)</th><td><input type="url" dir="ltr" class="regular-text" name="ai_assistant[handoff_form_url]" value="<?php echo esc_attr( $ai['handoff_form_url'] ); ?>" /></td></tr>
			</table>

			<h2>پیشرفته</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Rate limit (پیام در دقیقه)</th><td><input type="number" min="0" max="120" name="ai_assistant[rate_limit]" value="<?php echo esc_attr( $ai['rate_limit'] ); ?>" class="small-text" /></td></tr>
				<tr><th scope="row">لاگ خطاها</th><td><label><input type="checkbox" name="ai_assistant[log_enabled]" value="1" <?php checked( $ai['log_enabled'] ); ?> /> ثبت خطاها در error_log</label></td></tr>
			</table>

			<?php jluxe_settings_submit_button( true, 'ai_assistant' ); ?>
		</form>
		<?php
	} );
}

// =====================================================================
// REST endpoint — سمت سرور، کلید API هیچ‌وقت به client نمی‌ره.
// =====================================================================
function jluxe_register_ai_rest_route(): void {
	register_rest_route(
		'jluxe/v1',
		'/assistant',
		array(
			'methods'             => 'POST',
			'callback'            => 'jluxe_handle_ai_rest_request',
			'permission_callback' => '__return_true', // ویجت مشتری، بدون لاگین — rate-limit پایین‌تر جلوی سوءاستفاده رو می‌گیره.
			'args'                => array(
				'message'  => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
				'messages' => array(
					'required' => false,
					'type'     => 'array',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'jluxe_register_ai_rest_route' );

/**
 * تاریخچه‌ی مکالمه رو از بدنه‌ی درخواست می‌گیره — یا آرایه‌ی `messages`
 * (چندمرحله‌ای، فرمت جدید)، یا `message` تکی (سازگاری با نسخه‌ی قدیمی‌تر
 * فرانت). خروجی همیشه آرایه‌ای از {role: user|assistant, content: string}
 * است، پاک‌سازی‌شده و محدود به آخرین ۱۲ پیام (برای کنترل هزینه/توکن).
 */
function jluxe_extract_ai_messages( WP_REST_Request $request ): array {
	$raw = $request->get_param( 'messages' );
	$out = array();

	if ( is_array( $raw ) ) {
		foreach ( $raw as $m ) {
			if ( ! is_array( $m ) || empty( $m['content'] ) ) {
				continue;
			}
			$role = isset( $m['role'] ) && 'assistant' === $m['role'] ? 'assistant' : 'user';
			$out[] = array(
				'role'    => $role,
				'content' => sanitize_textarea_field( (string) $m['content'] ),
			);
		}
	}

	if ( empty( $out ) ) {
		$single = (string) $request->get_param( 'message' );
		if ( '' !== trim( $single ) ) {
			$out[] = array( 'role' => 'user', 'content' => sanitize_textarea_field( $single ) );
		}
	}

	return array_slice( $out, -12 );
}

function jluxe_handle_ai_rest_request( WP_REST_Request $request ) {
	$settings = jluxe_get_theme_settings()['ai_assistant'];
	$api_key  = jluxe_get_ai_api_key();

	if ( ! $settings['enabled'] || '' === $api_key || '' === $settings['provider'] ) {
		return new WP_Error( 'jluxe_ai_disabled', 'دستیار هوش مصنوعی پیکربندی نشده است.', array( 'status' => 503 ) );
	}

	// rate limit ساده بر اساس IP — حداکثر تنظیم‌شده در «پیشرفته»، برای
	// جلوگیری از مصرف بی‌رویه‌ی اعتبار API روی یک endpoint عمومی بدون لاگین.
	$limit = max( 0, (int) $settings['rate_limit'] );
	if ( $limit > 0 ) {
		$ip    = jluxe_theme_get_client_ip();
		$key   = 'jluxe_ai_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'jluxe_ai_rate_limited', 'تعداد درخواست‌ها زیاده — کمی صبر کن.', array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	}

	$messages = jluxe_extract_ai_messages( $request );
	if ( empty( $messages ) ) {
		return new WP_Error( 'jluxe_ai_empty', 'پیامی ارسال نشده.', array( 'status' => 400 ) );
	}
	foreach ( $messages as $m ) {
		if ( mb_strlen( $m['content'] ) > 1000 ) {
			return new WP_Error( 'jluxe_ai_too_long', 'یکی از پیام‌ها خیلی طولانیه.', array( 'status' => 400 ) );
		}
	}

	$system = '' !== trim( $settings['system_prompt'] )
		? $settings['system_prompt']
		: jluxe_ai_default_system_prompt( $settings );

	$tool_specs = jluxe_ai_tool_specs( $settings['tools'] );

	$reply = jluxe_call_ai_provider( $settings, $api_key, $system, $messages, $tool_specs );
	if ( is_wp_error( $reply ) ) {
		return $reply;
	}

	return array( 'reply' => $reply );
}

function jluxe_theme_get_client_ip(): string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return preg_match( '/^[0-9a-fA-F:.]+$/', $ip ) ? $ip : '0.0.0.0';
}

/**
 * پرامپت پیش‌فرض — کاملاً پویا، از روی تنظیمات واقعیِ همین صفحه ساخته
 * می‌شه (نه یک متنِ ثابتِ هاردکد) تا همیشه با دانش/ابزارها/راه‌های
 * ارجاع‌شده‌ی واقعاً فعال هماهنگ بمونه.
 */
function jluxe_ai_default_system_prompt( array $settings ): string {
	$lines   = array();
	$lines[] = 'تو «' . $settings['name'] . '» هستی، دستیار هوشمند فروشگاه «' . get_bloginfo( 'name' ) . '». همیشه مودب، دقیق و کاملاً فارسی پاسخ بده.';
	$lines[] = 'هرگز اطلاعاتی که در اختیار نداری (قیمت، موجودی، وضعیت سفارش و…) را حدس نزن یا نسازی؛ اگر لازم بود از ابزارهای در دسترست استفاده کن، و اگر باز هم چیزی معلوم نبود صادقانه بگو نمی‌دونی و کاربر رو به پشتیبانی ارجاع بده.';

	$knowledge_context = jluxe_build_ai_context( $settings['knowledge'] );
	if ( '' !== $knowledge_context ) {
		$lines[] = $knowledge_context;
	}

	$enabled_tools = array_keys( array_filter( $settings['tools'] ) );
	if ( ! empty( $enabled_tools ) ) {
		$lines[] = 'برای گرفتن داده‌ی واقعی و لحظه‌ای، این ابزارها در اختیارت هستن و باید به‌جای حدس زدن ازشون استفاده کنی: ' . implode( '، ', $enabled_tools ) . '.';
	}

	if ( ! empty( array_filter( $settings['widgets'] ) ) ) {
		$lines[] = 'هروقت محصولی رو معرفی می‌کنی، اگر لینک و تصویر واقعی از ابزارها داری، دقیقاً با همین فرمت مارک‌داون بنویس: **نام محصول**، بعد `![نام محصول](لینک تصویر)` در خط بعد، بعد قیمت/موجودی، و در آخر `[مشاهده و خرید](لینک صفحه محصول)`. هرگز لینک تصویر یا صفحه‌ی محصول رو از خودت نساز — فقط از همون مقادیر واقعی‌ای که ابزارها برگردوندن استفاده کن.';
	}

	$handoff_links = array();
	if ( ! empty( $settings['handoff_whatsapp'] ) ) {
		$handoff_links[] = '[واتساپ](' . esc_url_raw( $settings['handoff_whatsapp'] ) . ')';
	}
	if ( ! empty( $settings['handoff_telegram'] ) ) {
		$handoff_links[] = '[تلگرام](' . esc_url_raw( $settings['handoff_telegram'] ) . ')';
	}
	if ( ! empty( $settings['handoff_form_url'] ) ) {
		$handoff_links[] = '[فرم تماس](' . esc_url_raw( $settings['handoff_form_url'] ) . ')';
	}
	if ( ! empty( $handoff_links ) ) {
		$lines[] = 'اگر کاربر صراحتاً خواست با پشتیبانی انسانی صحبت کنه، این لینک‌ها رو دقیقاً با همین فرمت مارک‌داون `[نام](URL)` پیشنهاد بده: ' . implode( ' ', $handoff_links ) . '.';
	}

	return implode( "\n\n", $lines );
}

/**
 * فقط داده‌ی واقعی از WooCommerce/دیتابیس — بر اساس منابع فعال‌شده در
 * تنظیمات. هیچ محتوای فرضی/mock تولید نمی‌شه. (جستجوی محصول بر اساس متن
 * پیام کاربر دیگه این‌جا انجام نمی‌شه — اون کار حالا با ابزار
 * search_products/get_product_info به‌صورت دقیق‌تر و لحظه‌ای انجام می‌شه.)
 */
function jluxe_build_ai_context( array $knowledge ): string {
	$parts = array();

	if ( ! empty( $knowledge['categories'] ) && class_exists( 'WooCommerce' ) ) {
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 15 ) );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$parts[] = 'دسته‌بندی‌های فروشگاه: ' . implode( '، ', wp_list_pluck( $cats, 'name' ) );
		}
	}

	return implode( "\n\n", $parts );
}

// =====================================================================
// ابزارهای دیتا (function calling) — تعریف + اجرای واقعی روی WooCommerce
// =====================================================================

/**
 * فهرست ابزارهای فعال به فرمت خنثیِ provider (JSON Schema)؛ توابع
 * jluxe_ai_tools_to_openai_schema()/jluxe_ai_tools_to_anthropic_schema()
 * همین آرایه رو به فرمت هر provider تبدیل می‌کنن.
 */
function jluxe_ai_tool_specs( array $enabled_tools ): array {
	$all = array(
		'get_order_status'     => array(
			'description' => 'وضعیت یک سفارش واقعی را با شماره سفارش و شماره موبایل صاحب سفارش برمی‌گرداند (برای احراز هویت هر دو لازم است).',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'order_number' => array( 'type' => 'string', 'description' => 'شماره سفارش' ),
					'phone'        => array( 'type' => 'string', 'description' => 'شماره موبایلی که سفارش با آن ثبت شده' ),
				),
				'required'   => array( 'order_number', 'phone' ),
			),
		),
		'search_products'      => array(
			'description' => 'جستجوی واقعی محصولات فروشگاه بر اساس متن و/یا دسته‌بندی.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'query'    => array( 'type' => 'string', 'description' => 'عبارت جستجو' ),
					'category' => array( 'type' => 'string', 'description' => 'اسلاگ یا نام دسته‌بندی (اختیاری)' ),
				),
				'required'   => array(),
			),
		),
		'get_product_info'     => array(
			'description' => 'جزئیات کامل یک محصول مشخص (با شناسه یا نام) شامل قیمت، موجودی، توضیحات و ویژگی‌ها.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array( 'type' => 'integer', 'description' => 'شناسه‌ی محصول (در صورت وجود)' ),
					'name'       => array( 'type' => 'string', 'description' => 'نام یا بخشی از نام محصول' ),
				),
				'required'   => array(),
			),
		),
		'get_categories'       => array(
			'description' => 'فهرست دسته‌بندی‌های واقعی فروشگاه به همراه تعداد محصول هر دسته.',
			'parameters'  => array( 'type' => 'object', 'properties' => new stdClass(), 'required' => array() ),
		),
		'recommend_products'   => array(
			'description' => 'پیشنهاد چند محصول واقعی بر اساس دسته‌بندی، حداکثر قیمت، یا تخفیف‌دار بودن.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'category'  => array( 'type' => 'string', 'description' => 'اسلاگ یا نام دسته‌بندی (اختیاری)' ),
					'max_price' => array( 'type' => 'number', 'description' => 'حداکثر قیمت (اختیاری)' ),
					'on_sale'   => array( 'type' => 'boolean', 'description' => 'فقط محصولات تخفیف‌دار' ),
				),
				'required'   => array(),
			),
		),
		'get_product_reviews'  => array(
			'description' => 'نظرات و امتیازهای واقعی ثبت‌شده روی یک محصول مشخص.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array( 'type' => 'integer', 'description' => 'شناسه‌ی محصول' ),
				),
				'required'   => array( 'product_id' ),
			),
		),
		'get_customer_context' => array(
			'description' => 'اطلاعات پروفایل و سفارش‌های اخیر مشتریِ فعلاً واردشده به حساب کاربری (اگر مشتری لاگین نباشد، logged_in=false برمی‌گردد).',
			'parameters'  => array( 'type' => 'object', 'properties' => new stdClass(), 'required' => array() ),
		),
		'get_store_info'       => array(
			'description' => 'اطلاعات تماس، ساعت پاسخگویی و شبکه‌های اجتماعیِ واقعیِ فروشگاه.',
			'parameters'  => array( 'type' => 'object', 'properties' => new stdClass(), 'required' => array() ),
		),
	);

	$out = array();
	foreach ( $all as $name => $spec ) {
		if ( ! empty( $enabled_tools[ $name ] ) ) {
			$out[ $name ] = $spec;
		}
	}
	return $out;
}

function jluxe_ai_tools_to_openai_schema( array $specs ): array {
	$out = array();
	foreach ( $specs as $name => $spec ) {
		$out[] = array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => $spec['description'],
				'parameters'  => $spec['parameters'],
			),
		);
	}
	return $out;
}

function jluxe_ai_tools_to_anthropic_schema( array $specs ): array {
	$out = array();
	foreach ( $specs as $name => $spec ) {
		$out[] = array(
			'name'         => $name,
			'description'  => $spec['description'],
			'input_schema' => $spec['parameters'],
		);
	}
	return $out;
}

/** اجرای واقعی یک ابزار — فقط اگر در تنظیمات فعال باشه. */
function jluxe_ai_execute_tool( string $name, array $args, array $enabled_tools ): array {
	if ( empty( $enabled_tools[ $name ] ) ) {
		return array( 'error' => 'این ابزار غیرفعال است.' );
	}
	switch ( $name ) {
		case 'get_order_status':
			return jluxe_ai_tool_get_order_status( $args );
		case 'search_products':
			return jluxe_ai_tool_search_products( $args );
		case 'get_product_info':
			return jluxe_ai_tool_get_product_info( $args );
		case 'get_categories':
			return jluxe_ai_tool_get_categories();
		case 'recommend_products':
			return jluxe_ai_tool_recommend_products( $args );
		case 'get_product_reviews':
			return jluxe_ai_tool_get_product_reviews( $args );
		case 'get_customer_context':
			return jluxe_ai_tool_get_customer_context();
		case 'get_store_info':
			return jluxe_ai_tool_get_store_info();
		default:
			return array( 'error' => 'ابزار ناشناخته.' );
	}
}

/** از توابعِ از قبل تست‌شده‌ی inc/order-tracking.php استفاده می‌کنه — بدون منطق تکراری/جدید برای احراز هویت سفارش. */
function jluxe_ai_tool_get_order_status( array $args ): array {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'jluxe_find_order_by_number' ) ) {
		return array( 'found' => false, 'message' => 'سرویس سفارش در دسترس نیست.' );
	}
	$order_number = jluxe_convert_digits_to_en( (string) ( $args['order_number'] ?? '' ) );
	$order_number = trim( ltrim( trim( $order_number ), '#' ) );
	$phone_last10 = jluxe_normalize_phone_last10( (string) ( $args['phone'] ?? '' ) );

	if ( '' === $order_number || strlen( $phone_last10 ) < 9 ) {
		return array( 'found' => false, 'message' => 'شماره سفارش یا موبایل ناقص است.' );
	}

	$order = jluxe_find_order_by_number( $order_number );
	if ( ! $order instanceof WC_Order ) {
		return array( 'found' => false, 'message' => 'سفارشی با این شماره پیدا نشد.' );
	}

	$order_phone_last10 = jluxe_normalize_phone_last10( $order->get_billing_phone() );
	if ( empty( $order_phone_last10 ) || ! hash_equals( $order_phone_last10, $phone_last10 ) ) {
		return array( 'found' => false, 'message' => 'شماره موبایل با این سفارش مطابقت ندارد.' );
	}

	return array(
		'found'    => true,
		'order'    => jluxe_build_order_data( $order ),
		'shipping' => jluxe_build_shipping_data( $order ),
	);
}

function jluxe_ai_product_summary( WC_Product $product ): array {
	$image_id = $product->get_image_id();
	return array(
		'id'                 => $product->get_id(),
		'name'               => $product->get_name(),
		'price'              => wp_strip_all_tags( $product->get_price_html() ),
		'in_stock'           => $product->is_in_stock(),
		'stock_status'       => $product->is_in_stock() ? 'موجود' : 'ناموجود',
		'on_sale'            => $product->is_on_sale(),
		'permalink'          => get_permalink( $product->get_id() ),
		'image'              => $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '',
		'short_description'  => wp_strip_all_tags( $product->get_short_description() ),
	);
}

function jluxe_ai_tool_search_products( array $args ): array {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'products' => array() );
	}
	$query_args = array(
		's'      => sanitize_text_field( (string) ( $args['query'] ?? '' ) ),
		'limit'  => 8,
		'status' => 'publish',
	);
	if ( ! empty( $args['category'] ) ) {
		$query_args['category'] = array( sanitize_text_field( (string) $args['category'] ) );
	}
	$products = wc_get_products( $query_args );
	return array(
		'products' => array_map( 'jluxe_ai_product_summary', $products ),
		'count'    => count( $products ),
	);
}

function jluxe_ai_tool_get_product_info( array $args ): array {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'found' => false );
	}
	$id = ! empty( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
	if ( ! $id && ! empty( $args['name'] ) ) {
		$found = wc_get_products( array( 's' => sanitize_text_field( (string) $args['name'] ), 'limit' => 1, 'status' => 'publish' ) );
		$id    = ! empty( $found ) ? $found[0]->get_id() : 0;
	}
	$product = $id ? wc_get_product( $id ) : null;
	if ( ! $product ) {
		return array( 'found' => false );
	}

	$attributes = array();
	foreach ( $product->get_attributes() as $attr ) {
		if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
			continue;
		}
		$values = $attr->is_taxonomy()
			? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) )
			: $attr->get_options();
		$attributes[] = array(
			'name'   => wc_attribute_label( $attr->get_name() ),
			'values' => array_values( (array) $values ),
		);
	}

	$data                = jluxe_ai_product_summary( $product );
	$data['found']       = true;
	$data['description'] = wp_strip_all_tags( $product->get_description() );
	$data['attributes']  = $attributes;
	return $data;
}

function jluxe_ai_tool_get_categories(): array {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'categories' => array() );
	}
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 40 ) );
	if ( is_wp_error( $terms ) ) {
		return array( 'categories' => array() );
	}
	$out = array();
	foreach ( $terms as $t ) {
		$out[] = array(
			'name'  => $t->name,
			'slug'  => $t->slug,
			'count' => $t->count,
			'url'   => get_term_link( $t ),
		);
	}
	return array( 'categories' => $out );
}

function jluxe_ai_tool_recommend_products( array $args ): array {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return array( 'products' => array() );
	}
	$query_args = array(
		'limit'   => 6,
		'status'  => 'publish',
		'orderby' => 'popularity',
	);
	if ( ! empty( $args['category'] ) ) {
		$query_args['category'] = array( sanitize_text_field( (string) $args['category'] ) );
	}
	if ( ! empty( $args['max_price'] ) ) {
		$query_args['max_price'] = (float) $args['max_price'];
	}
	if ( ! empty( $args['on_sale'] ) ) {
		$on_sale_ids = wc_get_product_ids_on_sale();
		if ( empty( $on_sale_ids ) ) {
			return array( 'products' => array() );
		}
		$query_args['include'] = $on_sale_ids;
	}
	$products = wc_get_products( $query_args );
	return array( 'products' => array_map( 'jluxe_ai_product_summary', $products ) );
}

function jluxe_ai_tool_get_product_reviews( array $args ): array {
	if ( ! class_exists( 'WooCommerce' ) || empty( $args['product_id'] ) ) {
		return array( 'reviews' => array() );
	}
	$id       = absint( $args['product_id'] );
	$comments = get_comments( array( 'post_id' => $id, 'status' => 'approve', 'number' => 8, 'type' => 'review' ) );
	$out      = array();
	foreach ( $comments as $c ) {
		$rating = get_comment_meta( $c->comment_ID, 'rating', true );
		$out[]  = array(
			'author'  => $c->comment_author,
			'rating'  => $rating ? (int) $rating : null,
			'content' => wp_strip_all_tags( $c->comment_content ),
			'date'    => $c->comment_date,
		);
	}
	$product = wc_get_product( $id );
	return array(
		'reviews'         => $out,
		'count'           => count( $out ),
		'average_rating'  => $product ? $product->get_average_rating() : null,
	);
}

function jluxe_ai_tool_get_customer_context(): array {
	if ( ! is_user_logged_in() || ! class_exists( 'WooCommerce' ) ) {
		return array( 'logged_in' => false );
	}
	$user   = wp_get_current_user();
	$orders = wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) );
	$out    = array();
	foreach ( $orders as $o ) {
		$date    = $o->get_date_created();
		$out[]   = array(
			'order_number' => $o->get_order_number(),
			'status_label' => function_exists( 'jluxe_get_status_label' ) ? jluxe_get_status_label( $o->get_status() ) : $o->get_status(),
			'total'        => function_exists( 'jluxe_format_price' ) ? jluxe_format_price( $o->get_total() ) : $o->get_total(),
			'date'         => $date ? $date->date( 'Y-m-d' ) : null,
		);
	}
	return array(
		'logged_in'     => true,
		'first_name'    => $user->first_name ?: $user->display_name,
		'recent_orders' => $out,
	);
}

function jluxe_ai_tool_get_store_info(): array {
	$settings = jluxe_get_theme_settings();
	$contact  = $settings['contact'] ?? array();
	$social   = $settings['social'] ?? array();
	$footer   = $settings['footer'] ?? array();

	$social_links = array();
	foreach ( $social as $key => $s ) {
		if ( ! empty( $s['enabled'] ) && ! empty( $s['url'] ) ) {
			$social_links[ $key ] = $s['url'];
		}
	}

	return array(
		'store_name'      => get_bloginfo( 'name' ),
		'phone'           => $contact['phone'] ?? '',
		'phone_secondary' => $contact['phone_secondary'] ?? '',
		'email'           => $contact['email'] ?? '',
		'address'         => $contact['address'] ?? '',
		'support_hours'   => $footer['support_hours'] ?? '',
		'social_links'    => $social_links,
		'currency_unit'   => defined( 'JLUXE_CURRENCY_UNIT_LABEL' ) ? JLUXE_CURRENCY_UNIT_LABEL : 'تومان',
	);
}

/** آیا نام مدل واقعاً یک مدل reasoning هست؟ (o-series / gpt-5) — فقط برای این‌ها reasoning_effort فرستاده می‌شه تا مدل‌های معمولی رو خراب نکنه. */
function jluxe_ai_model_supports_reasoning_effort( string $model ): bool {
	return (bool) preg_match( '/^(o[0-9]|gpt-5)/i', trim( $model ) );
}

// =====================================================================
// فراخوانی provider — با پشتیبانی از tool calling چندمرحله‌ای (حداکثر ۳
// رفت‌وبرگشت، برای جلوگیری از حلقه‌ی بی‌پایان/هزینه‌ی کنترل‌نشده).
// =====================================================================
function jluxe_call_ai_provider( array $settings, string $api_key, string $system, array $messages, array $tool_specs ) {
	if ( 'anthropic' === $settings['provider'] ) {
		return jluxe_call_anthropic( $settings, $api_key, $system, $messages, $tool_specs );
	}
	/*
	 * گپ‌جی‌پی‌تی (GapGPT) — یک سرویسِ سازگار با OpenAI است؛ طبق مستندات
	 * رسمیِ خودِ gapgpt.app دقیقاً همون endpoint و فرمتِ
	 * POST /v1/chat/completions را پیاده‌سازی کرده (همون چیزی که
	 * jluxe_call_openai_compatible() از قبل برای openai/custom پیاده‌سازی
	 * کرده)، پس نیازی به کدِ جداگانه نیست — فقط base_url رو خودمون (نه از
	 * روی فیلدِ base_url، که برای گزینه‌ی «custom» عمومیه) روی آدرس ثابتِ
	 * gapgpt ست می‌کنیم.
	 */
	if ( 'gapgpt' === $settings['provider'] ) {
		$settings['base_url'] = 'https://api.gapgpt.app/v1';
		return jluxe_call_openai_compatible( $settings, $api_key, $system, $messages, $tool_specs );
	}
	if ( in_array( $settings['provider'], array( 'openai', 'custom' ), true ) ) {
		return jluxe_call_openai_compatible( $settings, $api_key, $system, $messages, $tool_specs );
	}
	return new WP_Error( 'jluxe_ai_unknown_provider', 'ارائه‌دهنده‌ی هوش مصنوعی نامعتبر است.', array( 'status' => 500 ) );
}

function jluxe_ai_log_error( array $settings, string $message ): void {
	if ( ! empty( $settings['log_enabled'] ) ) {
		error_log( '[jluxe-ai] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

function jluxe_call_openai_compatible( array $settings, string $api_key, string $system, array $messages, array $tool_specs ) {
	$base = ! empty( $settings['base_url'] ) ? untrailingslashit( $settings['base_url'] ) : 'https://api.openai.com/v1';
	$url  = $base . '/chat/completions';

	$oa_messages = array( array( 'role' => 'system', 'content' => $system ) );
	foreach ( $messages as $m ) {
		$oa_messages[] = array( 'role' => $m['role'], 'content' => $m['content'] );
	}

	$model    = $settings['model'] ?: 'gpt-4o-mini';
	$oa_tools = jluxe_ai_tools_to_openai_schema( $tool_specs );

	for ( $round = 0; $round < 3; $round++ ) {
		$body = array(
			'model'       => $model,
			'temperature' => (float) $settings['temperature'],
			'max_tokens'  => (int) $settings['max_tokens'],
			'messages'    => $oa_messages,
		);
		if ( ! empty( $oa_tools ) ) {
			$body['tools']       = $oa_tools;
			$body['tool_choice'] = 'auto';
		}
		if ( jluxe_ai_model_supports_reasoning_effort( $model ) && ! empty( $settings['reasoning_effort'] ) ) {
			$body['reasoning_effort'] = $settings['reasoning_effort'];
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			jluxe_ai_log_error( $settings, 'openai transport error: ' . $response->get_error_message() );
			return new WP_Error( 'jluxe_ai_upstream', 'ارتباط با سرویس هوش مصنوعی برقرار نشد.', array( 'status' => 502 ) );
		}
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
		$msg    = $parsed['choices'][0]['message'] ?? null;
		if ( ! $msg ) {
			jluxe_ai_log_error( $settings, 'openai bad response: ' . wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'jluxe_ai_upstream', 'پاسخی از سرویس هوش مصنوعی دریافت نشد.', array( 'status' => 502 ) );
		}

		$tool_calls = $msg['tool_calls'] ?? null;
		if ( empty( $tool_calls ) ) {
			return (string) ( $msg['content'] ?? '' );
		}

		$oa_messages[] = $msg;
		foreach ( $tool_calls as $call ) {
			$name = $call['function']['name'] ?? '';
			$args = json_decode( $call['function']['arguments'] ?? '{}', true );
			$args = is_array( $args ) ? $args : array();
			$result = jluxe_ai_execute_tool( $name, $args, $settings['tools'] );
			$oa_messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $call['id'] ?? '',
				'content'      => wp_json_encode( $result ),
			);
		}
	}

	return new WP_Error( 'jluxe_ai_tool_loop', 'دستیار نتونست به پاسخ نهایی برسه.', array( 'status' => 502 ) );
}

function jluxe_call_anthropic( array $settings, string $api_key, string $system, array $messages, array $tool_specs ) {
	$url = 'https://api.anthropic.com/v1/messages';

	$an_messages = array();
	foreach ( $messages as $m ) {
		$an_messages[] = array( 'role' => $m['role'], 'content' => $m['content'] );
	}

	$model    = $settings['model'] ?: 'claude-sonnet-5';
	$an_tools = jluxe_ai_tools_to_anthropic_schema( $tool_specs );

	for ( $round = 0; $round < 3; $round++ ) {
		$body = array(
			'model'      => $model,
			'max_tokens' => (int) $settings['max_tokens'],
			'system'     => $system,
			'messages'   => $an_messages,
		);
		if ( ! empty( $an_tools ) ) {
			$body['tools'] = $an_tools;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			jluxe_ai_log_error( $settings, 'anthropic transport error: ' . $response->get_error_message() );
			return new WP_Error( 'jluxe_ai_upstream', 'ارتباط با سرویس هوش مصنوعی برقرار نشد.', array( 'status' => 502 ) );
		}
		$parsed  = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $parsed['content'] ?? null;
		if ( ! is_array( $content ) ) {
			jluxe_ai_log_error( $settings, 'anthropic bad response: ' . wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'jluxe_ai_upstream', 'پاسخی از سرویس هوش مصنوعی دریافت نشد.', array( 'status' => 502 ) );
		}

		$tool_uses = array_values( array_filter( $content, fn( $b ) => 'tool_use' === ( $b['type'] ?? '' ) ) );
		if ( empty( $tool_uses ) ) {
			$text_blocks = array_filter( $content, fn( $b ) => 'text' === ( $b['type'] ?? '' ) );
			return implode( "\n", wp_list_pluck( $text_blocks, 'text' ) );
		}

		$an_messages[] = array( 'role' => 'assistant', 'content' => $content );
		$tool_results   = array();
		foreach ( $tool_uses as $use ) {
			$name   = $use['name'] ?? '';
			$args   = is_array( $use['input'] ?? null ) ? $use['input'] : array();
			$result = jluxe_ai_execute_tool( $name, $args, $settings['tools'] );
			$tool_results[] = array(
				'type'        => 'tool_result',
				'tool_use_id' => $use['id'] ?? '',
				'content'     => wp_json_encode( $result ),
			);
		}
		$an_messages[] = array( 'role' => 'user', 'content' => $tool_results );
	}

	return new WP_Error( 'jluxe_ai_tool_loop', 'دستیار نتونست به پاسخ نهایی برسه.', array( 'status' => 502 ) );
}

// =====================================================================
// AJAX — تست اتصال (فقط ادمین) — یک درخواست ساده و بدون ابزار می‌فرسته
// تا provider/model/کلیدِ همین الان ذخیره‌شده رو بسنجه.
// =====================================================================
function jluxe_ajax_ai_test_connection(): void {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'jluxe_ai_test_connection', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
	}

	$settings = jluxe_get_theme_settings()['ai_assistant'];
	$api_key  = jluxe_get_ai_api_key();
	if ( '' === $api_key || '' === $settings['provider'] ) {
		wp_send_json_error( array( 'message' => 'اول provider/کلید API رو ذخیره کن.' ) );
	}

	$start = microtime( true );
	$reply = jluxe_call_ai_provider(
		$settings,
		$api_key,
		'فقط دقیقاً همین یک کلمه رو جواب بده: سلام',
		array( array( 'role' => 'user', 'content' => 'سلام' ) ),
		array()
	);
	$ms = (int) round( ( microtime( true ) - $start ) * 1000 );

	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( array( 'message' => $reply->get_error_message() ) );
	}
	wp_send_json_success( array( 'message' => (string) $reply, 'ms' => $ms ) );
}
add_action( 'wp_ajax_jluxe_ai_test_connection', 'jluxe_ajax_ai_test_connection' );

// =====================================================================
// خلاصه‌ی هوشمند نظرات محصول — کاملاً مجزا از ویجت گفتگو، همون
// provider/کلید بالا رو استفاده می‌کنه.
// =====================================================================

/**
 * خلاصه‌ی نظراتِ واقعیِ یک محصول رو برمی‌گردونه (یا اگه شرایط برقرار
 * نباشه/چیزی برای خلاصه‌کردن نباشه، رشته‌ی خالی).
 *
 * کش: کلیدِ کش از رویِ خودِ محتوا ساخته می‌شه (شناسه‌های دقیقِ نظراتِ
 * تأییدشده)، نه فقط یک TTL ثابت — یعنی به‌محض این‌که نظرِ جدیدی تأیید یا
 * حذف بشه، مجموعه‌ی شناسه‌ها عوض می‌شه و خودکار یک خلاصه‌ی تازه ساخته
 * می‌شه؛ نیازی به هیچ هوکِ جداگانه‌ای برای invalidate کردن نیست. سقفِ ۳۰
 * روزه هم فقط برای اطمینانه (جلوگیری از تجمعِ transientِ قدیمی).
 */
function jluxe_get_ai_review_summary( int $product_id ): string {
	$settings = jluxe_get_theme_settings()['ai_assistant'];
	if ( empty( $settings['review_summary_enabled'] ) ) {
		return '';
	}
	$api_key = jluxe_get_ai_api_key();
	if ( '' === $api_key || '' === $settings['provider'] ) {
		return '';
	}

	$comments = get_comments(
		array(
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'number'  => 30,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);

	$min_count = max( 1, (int) ( $settings['review_summary_min_count'] ?? 3 ) );
	if ( count( $comments ) < $min_count ) {
		return '';
	}

	$ids       = wp_list_pluck( $comments, 'comment_ID' );
	$cache_key = 'jluxe_ai_rs_' . $product_id . '_' . md5( implode( ',', $ids ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$lines = array();
	foreach ( $comments as $c ) {
		$rating  = get_comment_meta( $c->comment_ID, 'rating', true );
		$lines[] = ( $rating ? '(امتیاز ' . (int) $rating . ' از ۵) ' : '' ) . wp_strip_all_tags( $c->comment_content );
	}
	$reviews_text = implode( "\n---\n", $lines );

	/*
	 * سیستم‌پرامپتِ سخت‌گیرانه، عمداً: هیچ‌چیزی خارج از همین متنِ نظراتِ
	 * واقعی نباید اضافه بشه — دقیقاً همون فلسفه‌ای که کل این فایل برای
	 * ابزارهای دیگه (get_order_status و…) هم داره: داده‌ی واقعی، نه حدس.
	 */
	$system = 'تو دستیاری هستی که فقط بر اساس نظراتِ واقعیِ مشتری‌ها که زیر آورده شده، یک خلاصه‌ی کوتاه (حداکثر ۳ جمله، کاملاً فارسی، بدون مقدمه) از حس کلیِ خریداران درباره‌ی این محصول می‌نویسی. فقط از همین نظرات استفاده کن، هیچ ویژگی/ادعایی که توی نظرات نیومده اضافه نکن. اگه نظرات متناقض بودن (بعضی مثبت بعضی منفی)، هر دو طرف رو منصفانه و خلاصه اشاره کن. خروجیِ نهایی فقط خودِ خلاصه باشه.';

	$reply = jluxe_call_ai_provider(
		$settings,
		$api_key,
		$system,
		array( array( 'role' => 'user', 'content' => $reviews_text ) ),
		array()
	);

	if ( is_wp_error( $reply ) ) {
		jluxe_ai_log_error( $settings, 'review summary error: ' . $reply->get_error_message() );
		return '';
	}

	$summary = trim( wp_strip_all_tags( (string) $reply ) );
	if ( '' === $summary ) {
		return '';
	}

	set_transient( $cache_key, $summary, 30 * DAY_IN_SECONDS );
	return $summary;
}

/**
 * چاپِ کارتِ خلاصه — با استایلِ اختصاصیِ خودش (نه کلاس‌های Tailwind، چون
 * main-DrI8xx-i.css از پیش کامپایل و قفله و کلاسِ جدید توش اثر نداره؛
 * دقیقاً همون الگویی که برای نمادهای فوتر/تمپلیتِ بلاگ هم جواب داد).
 */
function jluxe_render_ai_review_summary( int $product_id ): void {
	$summary = jluxe_get_ai_review_summary( $product_id );
	if ( '' === $summary ) {
		return;
	}
	?>
	<style>
	.jluxe-ai-review-summary{display:flex;gap:.75rem;align-items:flex-start;padding:1rem 1.1rem;margin-bottom:1.25rem;border:1px solid hsl(var(--border));border-radius:.9rem;background:hsl(var(--primary) / .05)}
	.jluxe-ai-review-summary-icon{flex:none;display:grid;place-items:center;width:2rem;height:2rem;border-radius:.6rem;background:hsl(var(--primary) / .12);color:hsl(var(--primary))}
	.jluxe-ai-review-summary-body{min-width:0}
	.jluxe-ai-review-summary-badge{font-size:.75rem;font-weight:700;color:hsl(var(--primary));margin-bottom:.3rem}
	.jluxe-ai-review-summary-text{margin:0;line-height:1.9;font-size:.875rem;color:hsl(var(--foreground))}
	</style>
	<div class="jluxe-ai-review-summary">
		<span class="jluxe-ai-review-summary-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
		</span>
		<div class="jluxe-ai-review-summary-body">
			<div class="jluxe-ai-review-summary-badge">خلاصه‌ی هوشمند نظرات</div>
			<p class="jluxe-ai-review-summary-text"><?php echo esc_html( $summary ); ?></p>
		</div>
	</div>
	<?php
}
