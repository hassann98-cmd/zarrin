<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code(404); exit; }
$p = new JLuxe_Var_Product(9180);
$p->jluxe_defs = array('pa_color'=>'green');
$p->jluxe_option_map = array('pa_color'=>array('red','blue','green'));
$p->children = array(9181,9182,9183);
foreach(array('red','blue','green') as $i=>$color){
 $child=new JLuxe_Var_Product(9181+$i);$child->type='variation';$child->parent=9180;$child->stock=1;
 $child->jluxe_option_map=array('attribute_pa_color'=>$color);$GLOBALS['products'][$child->id]=$child;
}
$GLOBALS['products'][9180]=$p;
check(array('pa_color'=>'red')===jluxe_default_variation_pick($p),'R168 first one-unit variation outranks a later saved default');
check(jluxe_variable_variation_is_available($GLOBALS['products'][9181]),'R168 max quantity one is purchasable, not out of stock');
$p->stock=0;
$r=new WP_REST_Request(array('ids'=>'9180'));
check(true===jluxe_rest_recent_products($r)->get_data()['items'][0]['inStock'],'R168 history card reports available children even if the parent stock flag is false');
$GLOBALS['products'][9181]->stock=0;
check(array('pa_color'=>'blue')===jluxe_default_variation_pick($p),'R168 first unavailable row falls through to second row');
$GLOBALS['products'][9182]->stock=0;
check(array('pa_color'=>'green')===jluxe_default_variation_pick($p),'R168 second unavailable row falls through to third row');
$GLOBALS['products'][9183]->stock=0;$p->stock=10;
check(array()===jluxe_default_variation_pick($p) && !jluxe_product_has_available_offer($p),'R168 only no eligible children makes the variable offer unavailable');
check(false===jluxe_rest_recent_products($r)->get_data()['items'][0]['inStock'],'R168 stale in-stock parent cannot hide an entirely unavailable variation set');
$GLOBALS['products'][9183]->stock=1;
check(true===jluxe_rest_recent_products($r)->get_data()['items'][0]['inStock'],'R168 stock is recomputed after a child restock instead of held in a stale per-request cache');
$p->children=array(9183,9182,9181);$GLOBALS['products'][9181]->stock=1;
check(array('pa_color'=>'green')===jluxe_default_variation_pick($p),'R168 selection follows the actual Woo child order');
check(!jluxe_variable_variation_is_available(array('is_in_stock'=>true,'is_purchasable'=>true,'max_qty'=>0,'min_qty'=>1)),'R168 stale stock flag with zero allowed quantity is not a purchasable offer');
check(jluxe_variable_variation_is_available(array('is_in_stock'=>true,'is_purchasable'=>true,'max_qty'=>1,'min_qty'=>1)),'R168 inline Woo max_qty one remains available');
check(!jluxe_variable_variation_is_available(array('is_in_stock'=>true,'is_purchasable'=>true,'variation_is_active'=>false)),'R168 disabled variations do not become the initial selection');
$simple=new WC_Product(9184);$simple->stock=1;check(jluxe_product_has_available_offer($simple),'R168 simple one-unit stock behavior is preserved');$simple->stock=0;check(!jluxe_product_has_available_offer($simple),'R168 simple out-of-stock behavior is preserved');
foreach(array(9180,9181,9182,9183) as $id)unset($GLOBALS['products'][$id]);

// Additional array-payload and response-header regressions preserved from the remote branch.
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
