<?php
// Included by the isolated CLI runner, never execute through HTTP.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }

jluxe_register_responsive_image_sizes();
foreach ( array( 96, 160, 320, 480 ) as $width ) {
	check( array( 'width' => $width, 'height' => 0, 'crop' => false ) === $GLOBALS['registered_image_sizes'][ 'jluxe-media-' . $width ], 'R166 registers uncropped responsive width ' . $width );
}
$meta = array( 'width' => 1200, 'height' => 800, 'sizes' => array(
	'thumbnail' => array( 'width' => 150, 'height' => 150, 'file' => 'crop.jpg' ),
	'medium' => array( 'width' => 300, 'height' => 200, 'file' => 'medium.jpg' ),
	'large' => array( 'width' => 1024, 'height' => 683, 'file' => 'large.jpg' ),
	'jluxe-media-96' => array( 'width' => 96, 'height' => 64, 'file' => '96.jpg' ),
	'jluxe-media-160' => array( 'width' => 160, 'height' => 107, 'file' => '160.jpg' ),
	'jluxe-media-320' => array( 'width' => 320, 'height' => 213, 'file' => '320.jpg' ),
	'jluxe-media-480' => array( 'width' => 480, 'height' => 320, 'file' => '480.jpg' ),
) );
$GLOBALS['test_attachment_metadata'][9101] = $meta;
$GLOBALS['attachment_mimes'][9101] = 'image/jpeg';
$GLOBALS['test_attachment_sources'][9101] = array( 'full' => array( home_url('/fixture/original.jpg'), 1200, 800, false ) );
foreach ( $meta['sizes'] as $key => $value ) {
	$GLOBALS['test_attachment_sources'][9101][$key] = array( home_url('/fixture/' . $value['file']), $value['width'], $value['height'], true );
}
$GLOBALS['test_attachment_srcsets'][9101] = home_url('/fixture/96.jpg') . ' 96w, ' . home_url('/fixture/160.jpg') . ' 160w, ' . home_url('/fixture/320.jpg') . ' 320w, ' . home_url('/fixture/480.jpg') . ' 480w, ' . home_url('/fixture/original.jpg') . ' 1200w';
check( 'jluxe-media-96' === jluxe_uncropped_image_size(9101, 96), 'R166 thumbnail selects an existing 96px derivative' );
check( 'jluxe-media-160' === jluxe_uncropped_image_size(9101, 150), 'R166 a square hard crop is excluded; one-pixel proportional rounding is accepted' );
check( 'jluxe-media-320' === jluxe_uncropped_image_size(9101, 310), 'R166 selects the smallest sufficient existing uncropped width' );
check( 'full' === jluxe_uncropped_image_size(9101, 3000), 'R166 does not fabricate an upscaled derivative' );
$data = jluxe_responsive_image_data(9101, 96, 'medium');
check( home_url('/fixture/96.jpg') === $data['url'] && 96 === $data['width'] && 64 === $data['height'], 'R166 responsive data retains the selected dimensions and WordPress-filtered URL' );
check( false !== strpos($data['srcset'], '1200w') && false !== strpos($data['srcset'], '160w'), 'R166 retina candidates are retained rather than capping all devices to the preview width' );
check( null === jluxe_responsive_image_data(0, 96), 'R166 invalid attachment IDs are not rendered' );

$GLOBALS['test_attachment_metadata'][9102] = array( 'width' => 600, 'height' => 600, 'sizes' => array( 'medium' => array( 'width' => 300, 'height' => 300, 'file' => 'old-300.jpg' ) ) );
$GLOBALS['test_attachment_sources'][9102] = array( 'full' => array(home_url('/fixture/old.jpg'),600,600,false), 'medium' => array(home_url('/fixture/old-300.jpg'),300,300,true) );
check( 'medium' === jluxe_uncropped_image_size(9102, 96), 'R166 old unregenerated uploads fall back to a real intermediate file, not a nonexistent 96px URL' );
check( home_url('/fixture/old-300.jpg') === jluxe_responsive_image_data(9102, 96)['url'], 'R166 missing new sizes do not silently force old images to the full original' );
check( 'medium' === jluxe_uncropped_image_size(9105, 96, 'medium'), 'R166 missing metadata keeps the explicit backwards-compatible fallback' );
$GLOBALS['test_attachment_metadata'][9104] = array( 'width' => 40, 'height' => 20, 'sizes' => array( 'bad-upscale' => array( 'width' => 96, 'height' => 48, 'file' => 'upscaled.jpg' ) ) );
check( 'full' === jluxe_uncropped_image_size(9104, 96), 'R166 malformed or upscaled metadata cannot override a small original' );
$GLOBALS['test_attachment_sources'][9106] = false;
check( null === jluxe_responsive_image_data(9106, 96), 'R166 a removed attachment is omitted instead of creating a broken image URL' );

$GLOBALS['attachment_mimes'][9103] = 'image/gif';
$GLOBALS['test_attachment_metadata'][9103] = $meta;
$GLOBALS['test_attachment_sources'][9103] = array( 'full' => array(home_url('/fixture/animated.gif'),1200,800,false), 'large' => array(home_url('/fixture/still.gif'),1024,683,true) );
check( 'full' === jluxe_uncropped_image_size(9103, 96), 'R166 GIF preview retains the animated original' );
$gif = jluxe_responsive_image_data(9103, 96);
check( home_url('/fixture/animated.gif') === $gif['url'] && '' === $gif['srcset'], 'R166 a still-frame GIF srcset cannot replace animation' );
$gif_html = jluxe_responsive_attachment_image(9103, 96, 'medium', array('alt'=>'<unsafe> "name"','class'=>'is-gray','loading'=>'lazy'));
check( false !== strpos($gif_html, 'animated.gif') && false === strpos($gif_html, 'srcset=') && false !== strpos($gif_html, 'class="is-gray"'), 'R166 animated brand images keep their appearance classes without an auto-generated still srcset' );
check( false === strpos($gif_html, '<unsafe>') && false !== strpos($gif_html, '&lt;unsafe&gt;'), 'R166 the original-animation markup escapes attribute text' );
$GLOBALS['test_filters']['jluxe_image_preserve_original'] = static function($value,$id) { return 9101 === $id || $value; };
check( '' === jluxe_responsive_image_data(9101,96)['srcset'] && false === strpos(jluxe_responsive_attachment_image(9101,96,'medium'), 'srcset='), 'R166 the opt-out filter also preserves other animated or art-directed formats' );
unset($GLOBALS['test_filters']['jluxe_image_preserve_original']);
check( 'auto, 32px' === jluxe_responsive_sizes('32px',true) && '32px' === jluxe_responsive_sizes('32px',false), 'R166 auto-sizes is only emitted for lazy images' );

ob_start(); jluxe_render_homepage_brand_marquee(array('title'=>'برند نمونه','grayscale'=>true,'pause_hover'=>true,'items'=>array(array('image_id'=>9101,'title'=>'لوگو'),array('image_id'=>9103,'title'=>'متحرک')))); $brands = ob_get_clean();
check( 2 === substr_count($brands, 'src="'.esc_url(home_url('/fixture/96.jpg')).'"') && 2 === substr_count($brands, 'src="'.esc_url(home_url('/fixture/animated.gif')).'"'), 'R166 real brand renderer uses small proportional logos and animated originals in both marquee copies' );
check( false !== strpos($brands,'jluxe-brand-marquee-pause') && false !== strpos($brands,'is-gray') && false !== strpos($brands,'@keyframes jluxe-brand-marquee'), 'R166 brand marquee, hover color and pause effects are unchanged' );

$hero_items = array( array('image_id'=>9106), array('image_id'=>9101,'mobile_image_id'=>9106), array('image_id'=>9102,'mobile_image_id'=>9103) );
$resolved = jluxe_resolve_hero_slides(array('items'=>$hero_items));
check( 2 === count($resolved) && 9101 === $resolved[0]['item']['image_id'], 'R166 hero skips a deleted first attachment in both render and preload selection' );
check( $resolved[0]['desktop_url'] === $resolved[0]['mobile_url'] && $resolved[0]['desktop_srcset'] === $resolved[0]['mobile_srcset'] && false === $resolved[0]['has_mobile'], 'R166 invalid mobile attachment falls back identically for picture and preload' );
check( '' === $resolved[1]['mobile_srcset'] && home_url('/fixture/animated.gif') === $resolved[1]['mobile_url'], 'R166 a mobile GIF hero stays animated' );
$settings = jluxe_theme_settings_defaults();
$section = array_merge($settings['homepage']['sections'][0], array('items'=>$hero_items, 'enabled'=>true));
$settings['homepage']['sections'] = array($section);
update_test_settings($settings);
$GLOBALS['query_kind'] = 'front';
ob_start(); jluxe_preload_homepage_hero_lcp_image(); $preload = ob_get_clean();
ob_start(); jluxe_render_homepage_hero($section); $hero_html = ob_get_clean();
check( 2 === substr_count($preload,'rel="preload"') && 2 === substr_count($preload,esc_url($resolved[0]['desktop_url'])), 'R166 mobile and desktop preloads select the same first valid fallback as the renderer' );
check( false !== strpos($hero_html,'src="'.esc_url($resolved[0]['mobile_url']).'"') && 1 === substr_count($hero_html,'fetchpriority="high"') && 1 === substr_count($hero_html,'loading="eager"'), 'R166 rendering retains exactly one eager/high-priority first slide' );
check( 1 === substr_count($hero_html,'data-no-lazy="1"'), 'R166 the critical hero image explicitly bypasses LiteSpeed lazy rewriting without excluding later slides' );
check( false === jluxe_product_card_high_priority(1) && false === jluxe_product_card_high_priority(2), 'R166 a leading homepage hero is not competing with a high-priority first product card' );
$settings['homepage']['sections'] = array(array('type'=>'product_grid','enabled'=>true), $section);
update_test_settings($settings);
ob_start(); jluxe_preload_homepage_hero_lcp_image(); $below = ob_get_clean();
check( '' === $below && true === jluxe_product_card_high_priority(1), 'R166 a lower hero is not preloaded ahead of the leading product section' );
$settings['homepage']['sections'][0]['enabled'] = false;
update_test_settings($settings);
check( null !== jluxe_homepage_lcp_hero(), 'R166 disabled preceding sections do not suppress the leading hero' );
$GLOBALS['query_kind'] = 'shop';
check( null === jluxe_homepage_lcp_hero() && true === jluxe_product_card_high_priority(1) && false === jluxe_product_card_high_priority(2), 'R166 shop pages retain first-card priority without any homepage preload' );

update_test_settings(jluxe_theme_settings_defaults());
ob_start(); jluxe_render_homepage_banners(array('items'=>array(array('image_id'=>9101,'zoom_enabled'=>true,'shine_enabled'=>true),array('image_id'=>9101)))); $banner = ob_get_clean();
check( 2 === substr_count($banner,'srcset=') && 2 === substr_count($banner,'sizes="auto, (max-width: 639px) 100vw, 50vw"'), 'R166 ordinary banners provide responsive candidates and lazy auto-sizes with a column-aware fallback' );
check( 2 === substr_count($banner,'width="1024" height="683"') && false !== strpos($banner,'jluxe-banner-shine') && false !== strpos($banner,'group-hover:scale-105') && false !== strpos($banner,'bg-gradient-to-t'), 'R166 banners retain their intrinsic frame, shine, zoom and overlay' );
$settings = jluxe_theme_settings_defaults();$settings['performance']['lazy_load_images']=false;update_test_settings($settings);
ob_start(); jluxe_render_homepage_banners(array('items'=>array(array('image_id'=>9101)))); $eager_banner = ob_get_clean();
check( false === strpos($eager_banner,'sizes="auto,') && false === strpos($eager_banner,'loading="lazy"'), 'R166 disabling image lazy-loading does not leave invalid auto-sizes on eager banners' );
ob_start(); jluxe_render_homepage_banners(array('items'=>array(array('image_id'=>9103)))); $animated_banner = ob_get_clean();
check( false !== strpos($animated_banner,'animated.gif') && false === strpos($animated_banner,'srcset='), 'R166 ordinary GIF banners are not flattened by responsive delivery' );

update_test_settings(jluxe_theme_settings_defaults());
$GLOBALS['product'] = new WC_Product(9170);
$GLOBALS['product']->image_id = 9101;
$GLOBALS['product_gallery_ids'][9170] = array(9101,9103);
for($i=0;$i<6;$i++) { ob_start(); include ABSPATH.'woocommerce/content-product.php'; $card=ob_get_clean(); }
check( false !== strpos($card,'fixture/96.jpg') && false !== strpos($card,'sizes="auto, 32px"') && false !== strpos($card,'width="96" height="64"'), 'R166 rendered gallery preview uses responsive small sources without changing its frame classes' );
check( false !== strpos($card,'data-full="'.esc_url(home_url('/fixture/large.jpg')).'"') && false !== strpos($card,'data-srcset=') && false !== strpos($card,'data-jluxe-card-thumbs'), 'R166 full-quality image selection and the existing hover/touch gallery contract are retained' );
check( false !== strpos($card,'data-full="'.esc_url(home_url('/fixture/animated.gif')).'"') && false === strpos($card,'still.gif'), 'R166 selecting a GIF gallery item also keeps the animated original' );
check( false !== strpos($card,'sizes="auto, (max-width: 480px)') && false !== strpos($card,'size-full object-contain'), 'R166 lazy main card images measure their actual slot while preserving full-item rendering' );

$fixture_manifest = array(
	'src/main.js'=>array('file'=>'assets/main-valid.js','imports'=>array('shared','missing'),'dynamicImports'=>array('private-ui')),
	'shared'=>array('file'=>'assets/shared-valid.js','imports'=>array('src/main.js','unsafe')),
	'unsafe'=>array('file'=>'../private.js'),
	'private-ui'=>array('file'=>'assets/optional-valid.js'),
);
check( array('assets/main-valid.js','assets/shared-valid.js') === jluxe_modulepreload_files($fixture_manifest), 'R166 static modulepreload traversal deduplicates cycles, rejects unsafe paths and omits dynamic/page islands' );
$scripts_before = $GLOBALS['scripts'];
$GLOBALS['scripts'] = array();
ob_start(); jluxe_preload_entry_modules(); check(''===ob_get_clean(),'R166 a dequeued entry causes no speculative module downloads');
jluxe_enqueue_assets();
ob_start(); jluxe_preload_entry_modules(); $module_preload = ob_get_clean();
$initial_files=jluxe_modulepreload_files(jluxe_vite_manifest());
check(count($initial_files)===substr_count($module_preload,'rel="modulepreload"') && false!==strpos($module_preload,'crossorigin'), 'R166 the entry and static runtime dependencies are discovered together in the head');
check(false===strpos($module_preload,'AiAssistant') && false===strpos($module_preload,'AuthPage'), 'R166 modulepreload does not promote optional AI or authentication chunks');
$GLOBALS['scripts']=$scripts_before;

foreach(array(9101,9102,9103,9104,9105,9106) as $id){unset($GLOBALS['test_attachment_metadata'][$id],$GLOBALS['test_attachment_sources'][$id],$GLOBALS['test_attachment_srcsets'][$id],$GLOBALS['attachment_mimes'][$id]);}
unset($GLOBALS['product_gallery_ids'][9170]);
update_test_settings(jluxe_theme_settings_defaults());
