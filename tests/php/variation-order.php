<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$p = new JLuxe_Var_Product(9810);
$p->jluxe_option_map = array('pa_color'=>array('first','second','third'));
$p->jluxe_defs = array('pa_color'=>'third');
$p->jluxe_rows = array(
 array('attributes'=>array('attribute_pa_color'=>'first'),'is_in_stock'=>true,'is_purchasable'=>true,'variation_is_active'=>true,'max_qty'=>1),
 array('attributes'=>array('attribute_pa_color'=>'second'),'is_in_stock'=>true,'is_purchasable'=>true,'max_qty'=>1),
 array('attributes'=>array('attribute_pa_color'=>'third'),'is_in_stock'=>true,'is_purchasable'=>true,'max_qty'=>1),
);
check(jluxe_default_variation_pick($p)===array('pa_color'=>'first'),'R168 one unit in the first variation wins over a later saved default');
$p->jluxe_rows[0]['is_in_stock']=false;
check(jluxe_default_variation_pick($p)===array('pa_color'=>'second'),'R168 first out of stock advances to second in Woo order');
$p->jluxe_rows[1]['is_purchasable']=false;
check(jluxe_default_variation_pick($p)===array('pa_color'=>'third'),'R168 a non-purchasable second offer advances to third');
$p->jluxe_rows[2]['variation_is_active']=false;
check(jluxe_default_variation_pick($p)===array() && !jluxe_product_has_available_offer($p),'R168 no active in-stock purchasable child produces no default and unavailable state');
$p->jluxe_rows[2]['variation_is_active']=true;
$p->jluxe_rows[2]['max_qty']=0;
check(jluxe_default_variation_pick($p)===array(),'R168 stale stock booleans do not override zero purchasable quantity');
$p->jluxe_rows[2]['max_qty']=1;
$p->stock=0;
$GLOBALS['products'][9810]=$p;
$response=jluxe_rest_recent_products(new WP_REST_Request(array('ids'=>'9810')));
check(count($response->get_data()['items'])===1 && $response->get_data()['items'][0]['inStock'],'R168 recent-product cards use available child inventory rather than a stale out-of-stock parent');
check(false!==strpos($response->get_headers()['Cache-Control'],'no-store') && $response->get_headers()['X-LiteSpeed-Cache-Control']==='no-cache','R168 history stock responses are explicitly non-cacheable including the LiteSpeed response policy');
$p->jluxe_rows[2]['is_in_stock']=false;$p->stock=50;
$response=jluxe_rest_recent_products(new WP_REST_Request(array('ids'=>'9810')));
check(!$response->get_data()['items'][0]['inStock'],'R168 an instock parent cannot conceal zero available variations in history');
$p->jluxe_rows[1]['is_purchasable']=true;
check(jluxe_rest_recent_products(new WP_REST_Request(array('ids'=>'9810')))->get_data()['items'][0]['inStock'],'R168 history immediately reflects a restocked eligible child within the same request');
unset($GLOBALS['products'][9810]);
