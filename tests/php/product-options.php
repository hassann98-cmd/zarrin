<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code(404); exit; }

$tabs = jluxe_product_options_tab(array('general'=>array('label'=>'عمومی')));
check(isset($tabs['general']) && 'jluxe_product_options' === $tabs['jluxe_options']['target'] && array() === $tabs['jluxe_options']['class'], 'R167 dedicated product options tab remains visible for both simple and variable products');
$old_post=$GLOBALS['post']??null;$GLOBALS['post']=(object)array('ID'=>9171);
$GLOBALS['post_meta'][9171]=array('_jluxe_badge_authenticity'=>'yes','_jluxe_badge_warranty'=>'yes');
ob_start();jluxe_render_product_options_panel();$panel=ob_get_clean();
check(false!==strpos($panel,'id="jluxe_product_options"') && false!==strpos($panel,'name="_jluxe_product_options_present"'), 'R167 editor panel prints an explicit presence marker for safe checkbox saves');
foreach(array('_jluxe_badge_authenticity','_jluxe_badge_warranty','_jluxe_suggested_modal_enabled') as $key){check(1===substr_count($panel,'name="'.$key.'"'), 'R167 each product option is rendered once: '.$key);}
$option_product=new WC_Product(9171);$option_product->meta=array('_jluxe_badge_authenticity'=>'yes','_jluxe_badge_warranty'=>'yes','_jluxe_suggested_modal_enabled'=>'yes');
$before=$option_product->meta;$old_post_data=$_POST;$old_caps=$GLOBALS['denied_caps']??array();$old_manage=$GLOBALS['can_manage']??true;$GLOBALS['can_manage']=true;$GLOBALS['denied_caps']=array();
$_POST=array();jluxe_save_product_options($option_product);check($before===$option_product->meta,'R167 REST, stock and bulk saves without the editor panel cannot clear options');
$_POST=array('_jluxe_product_options_present'=>'1','_jluxe_product_options_nonce'=>'valid','_jluxe_badge_authenticity'=>'yes','_jluxe_suggested_modal_enabled'=>'yes');
jluxe_save_product_options($option_product);
check('yes'===$option_product->meta['_jluxe_badge_authenticity'] && 'no'===$option_product->meta['_jluxe_badge_warranty'] && 'yes'===$option_product->meta['_jluxe_suggested_modal_enabled'],'R167 object save persists checked and unchecked fields with the existing metadata keys');
$before=$option_product->meta;$GLOBALS['verify_nonce_result']=false;$_POST['_jluxe_badge_warranty']='yes';jluxe_save_product_options($option_product);check($before===$option_product->meta,'R167 an invalid panel nonce cannot mutate guarantees');unset($GLOBALS['verify_nonce_result']);
$GLOBALS['denied_caps']=array('edit_post');jluxe_save_product_options($option_product);check($before===$option_product->meta,'R167 editing another product without capability cannot mutate options');
$GLOBALS['post_meta'][9171]=$option_product->meta;
ob_start();jluxe_render_product_trust_badges(9171);$badges=ob_get_clean();
check(false!==strpos($badges,'گارانتی اصالت کالا') && false===strpos($badges,'کالای دارای ضمانت'),'R167 frontend renders only explicitly enabled guarantees');
$GLOBALS['post_meta'][9171]['_jluxe_badge_warranty']='yes';ob_start();jluxe_render_product_trust_badges(9171);$badges=ob_get_clean();
check(false!==strpos($badges,'کالای دارای ضمانت') && false!==strpos($badges,'text-boom-success') && false!==strpos($badges,'text-boom-info'),'R167 both badges retain semantic colors and labels');
foreach(array('default'=>'content-single-product.php','classic'=>'content-single-product-classic.php') as $layout=>$file){check(false!==strpos(file_get_contents(ABSPATH.'woocommerce/'.$file),'jluxe_render_product_trust_badges( $product->get_id() )'),'R167 '.$layout.' layout calls the same badge renderer');}
$GLOBALS['post_meta'][9171]=array();ob_start();jluxe_render_product_trust_badges(9171);check(''===ob_get_clean(),'R167 products without enabled guarantees do not claim warranty or authenticity');

$old_query=$GLOBALS['query_kind'];$GLOBALS['query_kind']='product';
$tag='<script defer src="/variation.js" id="v"></script>';
$protected=jluxe_litespeed_purchase_script_attrs($tag,'wc-add-to-cart-variation');
check(false!==strpos($protected,'data-no-optimize="1"') && false!==strpos($protected,' defer ') && false!==strpos($protected,'id="v"'),'R167 LiteSpeed exclusion preserves native defer and script attributes');
check($protected===jluxe_litespeed_purchase_script_attrs($protected,'wc-add-to-cart-variation'),'R167 LiteSpeed script attributes are idempotent');
check($tag===jluxe_litespeed_purchase_script_attrs($tag,'unrelated-analytics'),'R167 unrelated scripts are not excluded from optimization');
$attrs=jluxe_litespeed_purchase_inline_attrs(array('nonce'=>'csp-existing'), 'var JLuxeThemeSettings = {};');
check('1'===$attrs['data-no-optimize'] && 'csp-existing'===$attrs['nonce'],'R167 localized cart config cannot be delayed behind the purchase controller; CSP nonce is preserved');
check(array('id'=>'other')===jluxe_litespeed_purchase_inline_attrs(array('id'=>'other'), 'console.log("not purchase config");'),'R167 other inline scripts keep their optimization policy');
$GLOBALS['query_kind']='404';check($tag===jluxe_litespeed_purchase_script_attrs($tag,'jquery-core'),'R167 lean pages keep their original asset plan');
$GLOBALS['query_kind']='product';$variation=new WC_Product(9172);$variation->type='variation';$variation->parent=9171;
$GLOBALS['action_calls']=array();jluxe_litespeed_product_saved($variation);
check(in_array(array('litespeed_purge_post',9171),$GLOBALS['action_calls'],true),'R167 saving a variation purges its parent product through the scoped LiteSpeed API');
check(!in_array('litespeed_purge_all',array_column($GLOBALS['action_calls'],0),true),'R167 product saves never purge the entire site cache');

// Identity-only sanitize_title doubles used by older tests could not catch Persian keys.
$GLOBALS['test_sanitize_title']=static function($value){return strtolower(rawurlencode(preg_replace('/\s+/u','-',rawurldecode((string)$value))));};
$key=sanitize_title('رنگ');$p=new JLuxe_Var_Product(9173);$p->jluxe_defs=array($key=>'آبی');$p->jluxe_option_map=array('رنگ'=>array('قرمز','آبی'));
$p->jluxe_rows=array(array('attributes'=>array('attribute_'.$key=>'قرمز'),'is_in_stock'=>true,'is_purchasable'=>true),array('attributes'=>array('attribute_'.$key=>'آبی'),'is_in_stock'=>true,'is_purchasable'=>true));
check(array($key=>'قرمز')===jluxe_default_variation_pick($p),'R168 a Persian-name attribute follows first-available order over a stored later default');
ob_start();jluxe_render_variation_swatches($p,array('رنگ'=>array('قرمز','آبی')));$choices=ob_get_clean();
check(false!==strpos($choices,'value="قرمز" selected') && false!==strpos($choices,'data-active=""'),'R167 raw Persian attribute labels resolve through the same sanitized key as stored defaults');
unset($GLOBALS['test_sanitize_title']);

$GLOBALS['denied_caps']=$old_caps;$GLOBALS['can_manage']=$old_manage;$_POST=$old_post_data;$GLOBALS['post']=$old_post;$GLOBALS['query_kind']=$old_query;
unset($GLOBALS['post_meta'][9171]);
