<?php
/**
 * صفحهٔ «تماس با ما» (اسلاگ contact-us). متن و جزئیات از تنظیمات پوسته
 * خوانده می‌شوند؛ هیچ داده‌ای در این قالب به یک برگهٔ زندهٔ وردپرس نوشته نمی‌شود.
 */

get_header();

$jluxe_contact_settings = function_exists( 'jluxe_get_theme_settings' ) ? jluxe_get_theme_settings() : array();
$jluxe_contact          = $jluxe_contact_settings['contact'] ?? array();
$jluxe_social            = $jluxe_contact_settings['social'] ?? array();
$jluxe_page_info         = $jluxe_contact_settings['info_pages']['contact'] ?? array();
$jluxe_page_logo         = ! empty( $jluxe_page_info['show_logo'] ) && function_exists( 'jluxe_get_logo_image_data' ) ? jluxe_get_logo_image_data() : array( 'src' => '', 'srcset' => '', 'sizes' => '' );
$jluxe_page_title        = isset( $jluxe_page_info['intro_title'] ) && is_string( $jluxe_page_info['intro_title'] ) ? $jluxe_page_info['intro_title'] : 'راه‌های ارتباط با جهیزیه لوکس';
$jluxe_intro_text        = isset( $jluxe_page_info['intro_text'] ) && is_string( $jluxe_page_info['intro_text'] ) ? $jluxe_page_info['intro_text'] : '';
$jluxe_phone_hours       = isset( $jluxe_page_info['phone_hours'] ) && is_string( $jluxe_page_info['phone_hours'] ) ? $jluxe_page_info['phone_hours'] : '';
$jluxe_email             = sanitize_email( (string) ( $jluxe_contact['email'] ?? '' ) );
$jluxe_has_email         = is_email( $jluxe_email );
$jluxe_phones            = array_values(
	array_filter(
		array(
			$jluxe_contact['phone'] ?? '',
			$jluxe_contact['phone_secondary'] ?? '',
		),
		static function ( $phone ): bool {
			return is_string( $phone ) && '' !== trim( $phone );
		}
	)
);
$jluxe_instagram         = $jluxe_social['instagram'] ?? array();
$jluxe_instagram_url     = ! empty( $jluxe_instagram['enabled'] ) && ! empty( $jluxe_instagram['url'] ) ? esc_url_raw( (string) $jluxe_instagram['url'] ) : '';
$jluxe_instagram_label   = '' !== $jluxe_instagram_url ? rtrim( (string) preg_replace( '#^https?://#i', '', $jluxe_instagram_url ), '/' ) : '';
$jluxe_contact_page_url  = function_exists( 'jluxe_resolve_site_link' ) ? jluxe_resolve_site_link( '/contact-us' ) : home_url( '/contact-us' );
$jluxe_site_url          = home_url( '/' );
$jluxe_has_phone_card    = ! empty( $jluxe_phones ) || '' !== trim( $jluxe_phone_hours );
$jluxe_has_social_card   = '' !== trim( (string) ( $jluxe_page_info['social_text'] ?? '' ) ) || '' !== $jluxe_instagram_url;
?>

<style>
	#jluxe-contact-page {
		box-sizing: border-box;
		width: 100%;
		max-width: 800px;
		margin: 40px auto;
		padding: 20px;
		color: #252a2d;
		direction: rtl;
	}
	#jluxe-contact-page *,
	#jluxe-contact-page *::before,
	#jluxe-contact-page *::after { box-sizing: border-box; }
	#jluxe-contact-page .jhh-hero {
		max-width: 680px;
		margin: 0 auto 32px;
		text-align: center;
	}
	#jluxe-contact-page .jhh-hero img {
		display: block;
		width: 56px;
		height: 56px;
		margin: 0 auto 16px;
		object-fit: contain;
	}
	#jluxe-contact-page .jhh-eyebrow {
		margin: 0 0 8px;
		color: #355c5a;
		font-size: 14px;
		font-weight: 700;
	}
	#jluxe-contact-page .jhh-hero h1 {
		margin: 0;
		color: #202426;
		font-size: clamp(1.75rem, 5vw, 2.25rem);
		font-weight: 800;
		line-height: 1.55;
	}
	#jluxe-contact-page .jhh-hero > p:last-child {
		margin: 12px 0 0;
		color: #555e61;
		font-size: 16px;
		line-height: 1.95;
	}
	#jluxe-contact-page .jluxe-contact-grid {
		display: grid;
		grid-template-columns: minmax(0, 1fr);
		gap: 16px;
	}
	#jluxe-contact-page .jluxe-contact-card {
		min-width: 0;
		padding: 24px;
		border: 1px solid #e7e5e0;
		border-radius: 14px;
		background: #fff;
		box-shadow: 0 4px 14px rgba(28, 35, 34, 0.045);
	}
	#jluxe-contact-page .jluxe-contact-card h2 {
		margin: 0 0 10px;
		color: #252a2d;
		font-size: 18px;
		font-weight: 700;
		line-height: 1.6;
	}
	#jluxe-contact-page .jluxe-contact-card p {
		margin: 0;
		color: #555e61;
		font-size: 15px;
		line-height: 1.9;
	}
	#jluxe-contact-page .jluxe-contact-phone-list {
		display: grid;
		gap: 8px;
		margin: 14px 0 0;
		padding: 0;
		list-style: none;
	}
	#jluxe-contact-page .jluxe-contact-phone-list a,
	#jluxe-contact-page .jluxe-contact-link {
		color: #285a56;
		font-weight: 700;
		text-decoration: underline;
		text-decoration-thickness: 1px;
		text-underline-offset: 3px;
		overflow-wrap: anywhere;
	}
	#jluxe-contact-page a:focus-visible {
		border-radius: 3px;
		outline: 3px solid #355c5a;
		outline-offset: 4px;
	}
	#jluxe-contact-page .jluxe-contact-link { display: inline-block; margin-top: 4px; }
	@media (max-width: 480px) {
		#jluxe-contact-page { margin: 24px auto; padding: 16px; }
		#jluxe-contact-page .jhh-hero { margin-bottom: 24px; }
		#jluxe-contact-page .jluxe-contact-card { padding: 20px; }
	}
</style>

<main id="primary" class="site-main">
	<div id="jluxe-contact-page">
		<header class="jhh-hero">
			<?php if ( ! empty( $jluxe_page_logo['src'] ) ) : ?>
				<img src="<?php echo esc_url( $jluxe_page_logo['src'] ); ?>"<?php echo jluxe_responsive_image_attributes( $jluxe_page_logo ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped responsive-image attributes. ?> alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-no-lazy="1" loading="eager" />
			<?php endif; ?>
			<p class="jhh-eyebrow">تماس با ما</p>
			<h1><?php echo esc_html( $jluxe_page_title ); ?></h1>
			<?php if ( '' !== trim( $jluxe_intro_text ) ) : ?>
				<p><?php echo esc_html( $jluxe_intro_text ); ?></p>
			<?php endif; ?>
		</header>

		<div class="jluxe-contact-grid">
			<?php if ( $jluxe_has_phone_card ) : ?>
				<section class="jluxe-contact-card" aria-labelledby="jluxe-contact-phone-heading">
					<h2 id="jluxe-contact-phone-heading"><?php echo esc_html( $jluxe_page_info['phone_title'] ?? 'تماس تلفنی' ); ?></h2>
					<?php if ( '' !== trim( $jluxe_phone_hours ) ) : ?>
						<p><?php echo esc_html( $jluxe_phone_hours ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $jluxe_phones ) ) : ?>
						<ul class="jluxe-contact-phone-list">
							<?php foreach ( $jluxe_phones as $jluxe_phone_number ) : ?>
								<?php
								$jluxe_ascii_phone = function_exists( 'jluxe_ascii_digits' ) ? jluxe_ascii_digits( $jluxe_phone_number ) : $jluxe_phone_number;
								$jluxe_tel_number = function_exists( 'jluxe_normalize_phone' ) ? jluxe_normalize_phone( $jluxe_phone_number ) : '';
								$jluxe_tel_href   = '' !== $jluxe_tel_number ? '+98' . $jluxe_tel_number : preg_replace( '/[^0-9+]/', '', $jluxe_ascii_phone );
								$jluxe_phone_text = function_exists( 'jluxe_fa_digits' ) ? jluxe_fa_digits( $jluxe_phone_number ) : $jluxe_phone_number;
								?>
								<li>
									<?php if ( '' !== $jluxe_tel_href ) : ?>
										<a href="tel:<?php echo esc_attr( $jluxe_tel_href ); ?>" dir="ltr"><?php echo esc_html( $jluxe_phone_text ); ?></a>
									<?php else : ?>
										<span dir="ltr"><?php echo esc_html( $jluxe_phone_text ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $jluxe_has_email ) : ?>
				<section class="jluxe-contact-card" aria-labelledby="jluxe-contact-email-heading">
					<h2 id="jluxe-contact-email-heading"><?php echo esc_html( $jluxe_page_info['email_title'] ?? 'ایمیل' ); ?></h2>
					<a class="jluxe-contact-link" href="<?php echo esc_url( 'mailto:' . $jluxe_email ); ?>" dir="ltr"><?php echo esc_html( $jluxe_email ); ?></a>
				</section>
			<?php endif; ?>

			<?php if ( $jluxe_has_social_card ) : ?>
				<section class="jluxe-contact-card" aria-labelledby="jluxe-contact-social-heading">
					<h2 id="jluxe-contact-social-heading"><?php echo esc_html( $jluxe_page_info['social_title'] ?? 'شبکه‌های اجتماعی' ); ?></h2>
					<?php if ( ! empty( $jluxe_page_info['social_text'] ) ) : ?>
						<p><?php echo esc_html( $jluxe_page_info['social_text'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $jluxe_instagram_url ) : ?>
						<a class="jluxe-contact-link" href="<?php echo esc_url( $jluxe_instagram_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="اینستاگرام، بازشدن در زبانهٔ جدید" dir="ltr"><?php echo esc_html( $jluxe_instagram_label ); ?></a>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
$jluxe_site_name = isset( $jluxe_contact_settings['identity']['site_name'] ) && is_string( $jluxe_contact_settings['identity']['site_name'] ) ? trim( $jluxe_contact_settings['identity']['site_name'] ) : '';
if ( '' === $jluxe_site_name && function_exists( 'get_bloginfo' ) ) {
	$jluxe_site_name = trim( (string) get_bloginfo( 'name' ) );
}
if ( '' === $jluxe_site_name ) {
	$jluxe_site_name = 'جهیزیه لوکس';
}

$jluxe_organization_schema = array(
	'@type' => 'Organization',
	'@id'   => $jluxe_site_url . '#organization',
	'name'  => $jluxe_site_name,
	'url'   => $jluxe_site_url,
);
if ( $jluxe_has_email ) {
	$jluxe_organization_schema['email'] = $jluxe_email;
}
$jluxe_schema_phones = array();
foreach ( $jluxe_phones as $jluxe_phone_number ) {
	$jluxe_schema_phone = function_exists( 'jluxe_schema_telephone' ) ? jluxe_schema_telephone( $jluxe_phone_number ) : sanitize_text_field( $jluxe_phone_number );
	if ( '' !== $jluxe_schema_phone ) {
		$jluxe_schema_phones[] = $jluxe_schema_phone;
	}
}
if ( ! empty( $jluxe_schema_phones ) ) {
	$jluxe_organization_schema['telephone'] = 1 === count( $jluxe_schema_phones ) ? reset( $jluxe_schema_phones ) : array_values( array_unique( $jluxe_schema_phones ) );
}
$jluxe_same_as = array();
foreach ( $jluxe_social as $jluxe_social_row ) {
	if ( ! is_array( $jluxe_social_row ) || empty( $jluxe_social_row['enabled'] ) || empty( $jluxe_social_row['url'] ) ) {
		continue;
	}
	$jluxe_social_url = esc_url_raw( (string) $jluxe_social_row['url'] );
	if ( '' !== $jluxe_social_url ) {
		$jluxe_same_as[] = $jluxe_social_url;
	}
}
if ( ! empty( $jluxe_same_as ) ) {
	$jluxe_organization_schema['sameAs'] = array_values( array_unique( $jluxe_same_as ) );
}

$jluxe_contact_page_schema = array(
	'@context'  => 'https://schema.org',
	'@type'     => 'ContactPage',
	'@id'       => $jluxe_contact_page_url . '#contact-page',
	'url'       => $jluxe_contact_page_url,
	'name'      => '' !== trim( $jluxe_page_title ) ? $jluxe_page_title : 'تماس با ما',
	'mainEntity' => $jluxe_organization_schema,
);
if ( function_exists( 'jluxe_jsonld_encode' ) ) {
	echo '<script type="application/ld+json">' . jluxe_jsonld_encode( $jluxe_contact_page_schema ) . '</script>' . "\n";
}

get_footer();
