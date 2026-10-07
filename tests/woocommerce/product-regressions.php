<?php
/** Real WordPress + WooCommerce + MySQL CLI contract. Never execute from a public HTTP request. */
if ( PHP_SAPI !== 'cli' || ! defined('WP_CLI') || ! WP_CLI ) { http_response_code(404); exit; }
if ( ! defined('WC_VERSION') || ! class_exists('WC_Product_Variable') ) { throw new RuntimeException('WooCommerce must be activated'); }
if ( ! defined('DOING_AJAX') ) { define('DOING_AJAX', true); }
wc_load_cart();
$checks = 0;
function jluxe_woo_check($condition, $label) {
	if (!$condition) { throw new RuntimeException('FAIL: '.$label); }
	$GLOBALS['checks']++;
	echo 'PASS: '.$label."\n";
}
jluxe_woo_check(WC_VERSION === '11.1.2', 'Pinned real WooCommerce 11.1.2 is active');
update_option('woocommerce_calc_taxes', 'no');
$key = sanitize_title('رنگ');
$attribute = new WC_Product_Attribute();
$attribute->set_name('رنگ');$attribute->set_options(array('قرمز','آبی'));$attribute->set_visible(true);$attribute->set_variation(true);
$parent = new WC_Product_Variable();$parent->set_name('محصول آزمایشی تنوع');$parent->set_status('publish');$parent->set_attributes(array($attribute));$parent->save();
$children = array();
foreach(array('قرمز','آبی') as $i=>$color){
	$child = new WC_Product_Variation();$child->set_parent_id($parent->get_id());$child->set_status('publish');$child->set_attributes(array($key=>$color));$child->set_regular_price((string)(100+$i*20));$child->set_manage_stock(true);$child->set_stock_quantity(5);$child->set_stock_status('instock');$child->save();$children[$color]=$child->get_id();
}
WC_Product_Variable::sync($parent->get_id());
$parent=wc_get_product($parent->get_id());$parent->set_default_attributes(array($key=>'آبی'));$parent->save();
$parent=wc_get_product($parent->get_id());
jluxe_woo_check($parent->get_default_attributes('edit')[$key]==='آبی', 'Saving a non-first merchant default preserves it in WooCommerce storage');
jluxe_woo_check(jluxe_default_variation_pick($parent)===array($key=>'آبی'), 'Effective default uses the canonical Persian attribute key');
$chosen=jluxe_find_default_variable_variation($parent);
jluxe_woo_check($chosen instanceof WC_Product_Variation && $chosen->get_id()===$children['آبی'], 'The effective default resolves to the intended real child variation');
ob_start();jluxe_render_variation_swatches($parent,$parent->get_variation_attributes());$swatches=ob_get_clean();
jluxe_woo_check((bool)preg_match('~<option\b[^>]*value="آبی"[^>]*\bselected\b~u',$swatches), 'Real Woo dropdown marks the stored non-first default selected');
jluxe_woo_check(false!==strpos($swatches,'data-active=""'), 'Visible swatches are selected before JavaScript');

$admin=get_user_by('login','fixture-admin');wp_set_current_user($admin->ID);
$_POST=array('_jluxe_product_options_present'=>'1','_jluxe_product_options_nonce'=>wp_create_nonce('jluxe_product_options_'.$parent->get_id()),'_jluxe_badge_authenticity'=>'yes','_jluxe_badge_warranty'=>'yes','_jluxe_suggested_modal_enabled'=>'yes');
do_action('woocommerce_admin_process_product_object',$parent);$parent->save();
jluxe_woo_check('yes'===get_post_meta($parent->get_id(),'_jluxe_badge_authenticity',true) && 'yes'===get_post_meta($parent->get_id(),'_jluxe_badge_warranty',true), 'Both editor fields persist through the actual WooCommerce object save');
$_POST=array();do_action('woocommerce_admin_process_product_object',$parent);$parent->save();
jluxe_woo_check('yes'===get_post_meta($parent->get_id(),'_jluxe_badge_warranty',true), 'Saving without the panel leaves warranty metadata intact');
$GLOBALS['post']=get_post($parent->get_id());$GLOBALS['product_object']=$parent;
$tabs=apply_filters('woocommerce_product_data_tabs',array());
jluxe_woo_check(isset($tabs['jluxe_options']) && empty($tabs['jluxe_options']['class']), 'The editor has a product-type-independent Zarrin options tab');
ob_start();jluxe_render_product_options_panel();$panel=ob_get_clean();
jluxe_woo_check(false!==strpos($panel,'name="_jluxe_product_options_nonce"') && substr_count($panel,'name="_jluxe_badge_authenticity"')===1, 'The real editor panel emits its nonce and one copy of each checkbox');

$settings=jluxe_theme_settings_defaults();
$GLOBALS['wp_query']=new WP_Query(array('p'=>$parent->get_id(),'post_type'=>'product'));
$GLOBALS['wp_query']->the_post();
foreach(array('default','classic') as $layout){
	$settings['product_page']['layout']=$layout;update_option(JLUXE_SETTINGS_OPTION,$settings);jluxe_get_theme_settings(true);
	$GLOBALS['product']=wc_get_product($parent->get_id());
	ob_start();wc_get_template('content-single-product.php');$html=ob_get_clean();
	jluxe_woo_check(false!==strpos($html,'گارانتی اصالت کالا') && false!==strpos($html,'کالای دارای ضمانت'), 'Real '.$layout.' product template displays both checked guarantees');
	jluxe_woo_check((bool)preg_match('~<option\b[^>]*value="آبی"[^>]*\bselected\b~u',$html), 'Real '.$layout.' product template renders the correct selected default');
}

$offer=new WC_Product_Simple();$offer->set_name('پیشنهاد آزمایشی');$offer->set_status('publish');$offer->set_regular_price('25');$offer->set_stock_status('instock');$offer->save();
$settings['purchase_addons']['mode']='fixed';$settings['purchase_addons']['fixed_ids']=array($offer->get_id());$settings['purchase_addons']['show_products']=true;
update_option(JLUXE_SETTINGS_OPTION,$settings);jluxe_get_theme_settings(true);
class JLuxe_Test_Json_End extends RuntimeException {}
$die=static function(){throw new JLuxe_Test_Json_End();};
add_filter('wp_die_ajax_handler',static function()use($die){return $die;},PHP_INT_MAX);
function jluxe_woo_cart_call(array $fields): array {
	$_SERVER['REQUEST_METHOD']='POST';$_POST=$fields;$_REQUEST=$fields;
	ob_start();try{jluxe_ajax_cart();}catch(JLuxe_Test_Json_End $e){}$text=ob_get_clean();
	$data=json_decode($text,true);if(!is_array($data)){throw new RuntimeException('Invalid AJAX fixture response: '.substr($text,0,200));}return $data;
}
$fields=array('action'=>'jluxe_cart','op'=>'add','nonce'=>'expired-cache-nonce','product_id'=>(string)$parent->get_id(),'variation_id'=>(string)$children['آبی'],'quantity'=>'1','attribute_'.$key=>'آبی');
$before=WC()->cart->get_cart_contents_count();$failed=jluxe_woo_cart_call($fields);
jluxe_woo_check(!$failed['success'] && $failed['data']['code']==='jluxe_cart_invalid_nonce' && $before===WC()->cart->get_cart_contents_count(), 'An expired cached nonce is explicitly rejected before any cart mutation');
$fields['nonce']=wp_create_nonce('jluxe_cart');$added=jluxe_woo_cart_call($fields);
jluxe_woo_check($added['success'] && (float) WC()->cart->get_cart_contents_count()===(float)($before+1), 'The selected default variation is added exactly once by the real Woo cart endpoint');
jluxe_woo_check(false!==strpos($added['data']['suggested_html'],'data-jluxe-suggested-modal') && false!==strpos($added['data']['suggested_html'],'پیشنهاد آزمایشی'), 'A successful real add returns fresh enabled recommendation markup');
$read=jluxe_woo_cart_call(array('action'=>'jluxe_cart','op'=>'get','nonce'=>wp_create_nonce('jluxe_cart'),'include_suggestions'=>'1','pa_context_id'=>(string)$parent->get_id()));
jluxe_woo_check($read['success'] && (float) WC()->cart->get_cart_contents_count()===(float)($before+1) && false!==strpos($read['data']['suggested_html'],'پیشنهاد آزمایشی'), 'Native loop adds can read fresh recommendations without a second product addition');
$parent=wc_get_product($parent->get_id());$parent->update_meta_data('_jluxe_suggested_modal_enabled','no');$parent->save();
$read=jluxe_woo_cart_call(array('action'=>'jluxe_cart','op'=>'get','nonce'=>wp_create_nonce('jluxe_cart'),'include_suggestions'=>'1','pa_context_id'=>(string)$parent->get_id()));
jluxe_woo_check(''===$read['data']['suggested_html'], 'Explicitly disabled recommendations remain disabled');
$purged=array();add_action('litespeed_purge_post',static function($id)use(&$purged){$purged[]=(int)$id;});
$child=wc_get_product($children['آبی']);$child->set_stock_quantity(4);$child->save();
jluxe_woo_check(in_array($parent->get_id(),$purged,true), 'Saving a real child variation requests a scoped LiteSpeed purge for the parent');
echo 'WOOCOMMERCE_PRODUCT_REGRESSIONS_PASSED: '.$checks."\n";
echo 'Scope: isolated WordPress/WooCommerce/MySQL product CRUD, templates and cart protocol. No production, real payment, SMS or LiteSpeed web-server execution.' . "\n";
