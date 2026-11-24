<?php
defined( 'ABSPATH' ) || exit;

/**
 * One-click, checksummed snapshot of the active homepage layout option.
 * This deliberately stores the raw option value (not the defaults or a sanitized
 * re-save), so restoration is byte-for-byte at the PHP value level.
 */
function jluxe_homepage_layout_backup_option(): string {
	return 'jluxe_homepage_layout_backup_v1';
}

function jluxe_homepage_layout_backup_checksum( $value ): string {
	return hash( 'sha256', serialize( $value ) );
}

function jluxe_homepage_layout_backup_is_valid( $backup ): bool {
	return is_array( $backup ) &&
		JLUXE_SETTINGS_OPTION === ( $backup['option_name'] ?? '' ) &&
		get_current_blog_id() === (int) ( $backup['blog_id'] ?? 0 ) &&
		array_key_exists( 'value', $backup ) &&
		isset( $backup['checksum'] ) &&
		hash_equals( (string) $backup['checksum'], jluxe_homepage_layout_backup_checksum( $backup['value'] ) );
}

function jluxe_save_homepage_layout_backup_snapshot(): void {
	$missing = '__jluxe_homepage_option_missing__';
	$current = get_option( JLUXE_SETTINGS_OPTION, $missing );
	$exists  = $missing !== $current;
	$value   = $exists ? $current : null;
	$backup  = array(
		'label'       => 'بکاپ چیدمان قبلی (قبل از بازطراحی)',
		'option_name' => JLUXE_SETTINGS_OPTION,
		'exists'      => $exists,
		'value'       => $value,
		'checksum'    => jluxe_homepage_layout_backup_checksum( $value ),
		'created_at'  => current_time( 'mysql' ),
		'created_by'  => get_current_user_id(),
		'blog_id'     => get_current_blog_id(),
	);
	update_option( jluxe_homepage_layout_backup_option(), $backup, false );
}

function jluxe_handle_homepage_layout_backup_action(): ?string {
	$action = isset( $_POST['jluxe_homepage_backup_action'] )
		? sanitize_key( wp_unslash( $_POST['jluxe_homepage_backup_action'] ) )
		: '';
	if ( ! in_array( $action, array( 'save', 'restore', 'apply_redesign' ), true ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	$nonce = isset( $_POST['jluxe_homepage_backup_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['jluxe_homepage_backup_nonce'] ) )
		: '';
	if ( ! wp_verify_nonce( $nonce, 'jluxe_homepage_layout_backup' ) ) {
		return 'error';
	}

	$backup_key = jluxe_homepage_layout_backup_option();
	if ( 'save' === $action ) {
		jluxe_save_homepage_layout_backup_snapshot();
		return 'homepage_backup_saved';
	}

	$backup = get_option( $backup_key, null );
	if ( 'apply_redesign' === $action ) {
		if ( null === $backup ) {
			// First activation automatically captures the existing active layout.
			jluxe_save_homepage_layout_backup_snapshot();
			$backup = get_option( $backup_key, null );
		}
		if ( ! jluxe_homepage_layout_backup_is_valid( $backup ) ) {
			return 'error';
		}
		$preset = jluxe_get_homepage_layout_presets();
		if ( empty( $preset['homepage']['sections'] ) ) {
			return 'error';
		}
		$defaults = jluxe_theme_settings_defaults();
		$clean_homepage = jluxe_sanitize_homepage( $preset['homepage'], $defaults['homepage'] );
		jluxe_update_settings_section( 'homepage', $clean_homepage );
		return 'homepage_preset_applied';
	}

	if ( ! jluxe_homepage_layout_backup_is_valid( $backup ) ) {
		return 'error';
	}

	if ( ! empty( $backup['exists'] ) ) {
		update_option( JLUXE_SETTINGS_OPTION, $backup['value'], false );
	} else {
		delete_option( JLUXE_SETTINGS_OPTION );
	}
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( JLUXE_SETTINGS_OPTION, 'options' );
	update_option( 'jluxe_theme_settings_updated_at', current_time( 'mysql' ), false );
	jluxe_get_theme_settings( true );
	if ( function_exists( 'jluxe_sync_homepage_banner_exclusions' ) ) {
		$homepage = is_array( $backup['value'] ?? null ) ? ( $backup['value']['homepage'] ?? array() ) : array();
		jluxe_sync_homepage_banner_exclusions( $homepage['sections'] ?? array() );
	}
	return 'homepage_backup_restored';
}

function jluxe_render_homepage_layout_backup_controls(): void {
	$backup = get_option( jluxe_homepage_layout_backup_option(), array() );
	$valid  = jluxe_homepage_layout_backup_is_valid( $backup );
	?>
	<section class="notice notice-info" style="padding:12px 16px;margin:12px 0 20px" aria-labelledby="jluxe-homepage-backup-title">
		<h2 id="jluxe-homepage-backup-title" style="margin:4px 0 8px">پشتیبانِ چیدمان صفحهٔ اصلی</h2>
		<p>چیدمان فعلی و شناسهٔ تصاویرش در همین تنظیمات پوسته حفظ می‌شود. با انتخاب پریست جدید، اگر بکاپ نداشته باشید ابتدا خودکار ثبت می‌شود؛ بازگشت به چیدمان قبلی هم از همین‌جا ممکن است. پریست فعلیِ این مرحله فقط ساختار UI را می‌چیند و هیرو/بنر/برندِ بدون تصویر را خالی نشان نمی‌دهد.</p>
		<?php if ( $valid ) : ?>
			<p><strong><?php echo esc_html( (string) ( $backup['label'] ?? 'بکاپ چیدمان' ) ); ?></strong>
				<?php if ( ! empty( $backup['created_at'] ) ) : ?> — زمان ثبت: <code><?php echo esc_html( (string) $backup['created_at'] ); ?></code><?php endif; ?>
			</p>
		<?php else : ?>
			<p><strong>هنوز بکاپی ثبت نشده است.</strong> تا ثبت بکاپ، چیدمان فعال را تغییر ندهید.</p>
		<?php endif; ?>
		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<form method="post" onsubmit="return confirm('یک نسخهٔ تازه از تنظیمات فعال صفحهٔ اصلی ذخیره شود؟');">
				<?php wp_nonce_field( 'jluxe_homepage_layout_backup', 'jluxe_homepage_backup_nonce' ); ?>
				<button class="button" type="submit" name="jluxe_homepage_backup_action" value="save">ثبت/به‌روزرسانی بکاپِ چیدمان فعلی</button>
			</form>
			<form method="post" onsubmit="return confirm('پریست تازه فعال شود؟ اگر هنوز بکاپ ثبت نشده باشد، چیدمان فعلی پیش از اعمال خودکار حفظ می‌شود.');">
				<?php wp_nonce_field( 'jluxe_homepage_layout_backup', 'jluxe_homepage_backup_nonce' ); ?>
				<button class="button button-primary" type="submit" name="jluxe_homepage_backup_action" value="apply_redesign">فعال‌کردن «پریست پیش‌فرض جدید صفحه اصلی»</button>
			</form>
			<?php if ( $valid ) : ?>
				<form method="post" onsubmit="return confirm('چیدمان صفحهٔ اصلی با نسخهٔ بکاپ جایگزین شود؟ این کار قابل بازگردانی نیست مگر بکاپ تازه‌ای بسازید.');">
					<?php wp_nonce_field( 'jluxe_homepage_layout_backup', 'jluxe_homepage_backup_nonce' ); ?>
					<button class="button" type="submit" name="jluxe_homepage_backup_action" value="restore">بازیابی یک‌کلیکی همین بکاپ</button>
				</form>
			<?php endif; ?>
		</div>
	</section>
	<?php
}
