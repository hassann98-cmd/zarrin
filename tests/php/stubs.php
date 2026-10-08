<?php
// Development checks must never execute through a public web request.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
// Isolated behavioral tests: no real database, no network, no SMS, no real account.
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
const MINUTE_IN_SECONDS = 60;
const HOUR_IN_SECONDS = 3600;
const DAY_IN_SECONDS = 86400;
const MB_IN_BYTES = 1048576;
$GLOBALS['options']=[];
$GLOBALS['transients']=[];
$GLOBALS['objcache']=[];
$GLOBALS['dequeued_scripts']=[];
$GLOBALS['dequeued_styles']=[];
$GLOBALS['actions']=[];
$GLOBALS['scripts']=[];
$GLOBALS['styles']=[];
$GLOBALS['user_meta']=[];
$GLOBALS['create_calls']=0;
function add_action(...$a){$GLOBALS['actions'][]=$a;}
function add_filter(...$a){$GLOBALS['filters'][]=$a;}
function add_theme_support(...$a){$GLOBALS['theme_supports'][]=$a;}
function register_nav_menus($menus){$GLOBALS['registered_nav_menus']=$menus;}
function add_image_size($name,$width=0,$height=0,$crop=false){$GLOBALS['registered_image_sizes'][$name]=array('width'=>$width,'height'=>$height,'crop'=>$crop);}
function remove_action(...$a){}
function do_action(...$a){$GLOBALS['fired'][]=$a[0];}
function wp_footer(){do_action('wp_footer');}
function apply_filters($hook,$value,...$a){$GLOBALS['filtered'][]=$hook; return isset($GLOBALS['test_filters'][$hook]) ? $GLOBALS['test_filters'][$hook]($value,...$a) : $value;}
function get_bloginfo($k){return 'Audit shop';}
function home_url($p=''){return 'https://shop.test/store' . ($p ? '/'.ltrim($p,'/') : '');}
function __($s,...$a){return $s;}
function get_option($k,$default=false){
 $value=$GLOBALS['options'][$k]??$default;
 $pre_option=false;
 foreach($GLOBALS['filters']??[] as $filter){
  if(($filter[0]??'')!=='pre_option_'.$k || !is_callable($filter[1]??null)) continue;
  $accepted=max(1,(int)($filter[3]??1));
  $pre_option=call_user_func_array($filter[1],array_slice(array($pre_option,$k,$default),0,$accepted));
 }
 return false!==$pre_option?$pre_option:$value;
}
function update_option($k,$v,...$a){$GLOBALS['options'][$k]=$v; return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v; return true;}
function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function get_user_meta($id,$key,...$a){return $GLOBALS['user_meta'][$id][$key]??'';}
function update_user_meta($id,$key,$val){if(!empty($GLOBALS['fail_user_meta']))return false;$GLOBALS['user_meta'][$id][$key]=$val;return true;}
function wp_list_pluck($list,$field,$index=null){return array_column($list,$field,$index);}
function wp_is_numeric_array($a){return is_array($a) && (!$a || array_keys($a)===range(0,count($a)-1));}
function sanitize_text_field($v){return is_scalar($v)?trim(strip_tags((string)$v)):'';}
function sanitize_textarea_field($v){return sanitize_text_field($v);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower($s));}
function sanitize_hex_color($s){return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i',$s)?$s:null;}
function sanitize_email($s){return $s;}
function absint($v){return abs((int)$v);}
function wp_unslash($v){return is_array($v)?array_map('wp_unslash',$v):stripslashes($v);}
function wp_check_invalid_utf8($v){return $v;}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_kses_post($s){return $s;}
function wp_kses($s,...$a){return $s;}
function wp_kses_allowed_html($c=''){return array();}
function esc_url_raw($s){return $s;}
function esc_url($s){return $s;}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function wp_http_validate_url($s){return filter_var($s,FILTER_VALIDATE_URL);}
function wp_json_encode($v,...$a){return json_encode($v,...$a);}
function wp_parse_url($v,$c=-1){return parse_url($v,$c);}
function is_admin(){return false;}

function current_user_can(...$a){return !in_array($a[0],$GLOBALS['denied_caps']??[],true) && ($GLOBALS['can_manage']??true);}
function check_admin_referer(...$a){return true;}

function wp_doing_ajax(){return $GLOBALS['doing_ajax']??false;}
function check_ajax_referer(...$a){return $GLOBALS['valid_ajax_nonce'] ?? true;}
function wp_verify_nonce(...$a){return true;}
function current_time($v){return '2026-09-23 00:00:00';}
function wp_cache_delete($key,$group=''){unset($GLOBALS['objcache'][$group][$key]);return true;}
function get_users($a){$GLOBALS['users_query']=$a; return isset($a['meta_query']) ? ($GLOBALS['billing_users']??[]) : ($GLOBALS['phone_users']??[]);}
function wp_set_current_user($id){$GLOBALS['authenticated_user']=$id;}
function wp_set_auth_cookie($id,...$a){$GLOBALS['cookie_user']=$id;}
function validate_username($s){return true;}
function username_exists($s){return false;}
function email_exists($s){return false;}
function is_email($s){return (bool)filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_insert_user($args){$GLOBALS['create_calls']++; $id=100+$GLOBALS['create_calls']; $GLOBALS['users'][$id]=new WP_User($id, [$args['role']]); foreach($args['meta_input']??[] as $key=>$value)update_user_meta($id,$key,$value); return $id;}
function wp_new_user_notification(...$a){}
function wp_generate_password(...$a){return bin2hex(random_bytes(16));}
function wp_generate_uuid4(){return bin2hex(random_bytes(16));}
function wp_enqueue_script($h,...$a){$GLOBALS['scripts'][$h]=$a;}
function wp_enqueue_style($h,...$a){$GLOBALS['styles'][$h]=$a;}
function is_wp_error($e){return $e instanceof WP_Error;}
class WP_Error {public $code; public $data; public $message; public function __construct($code='',$message='',$data=[]){$this->code=$code;$this->data=$data;$this->message=$message;} public function get_error_code(){return $this->code;} public function get_error_message(){return $this->message;} public function has_errors(){return (bool)$this->code;}}
class WP_REST_Request {
 private $p;
 function __construct($p){$this->p=$p;}
 function get_param($k){return $this->p[$k]??null;}
 function get_method(){return $this->p['_method']??'POST';}
 function get_route(){return $this->p['_route']??'/jluxe/v1/order-track';}
}
class WP_REST_Response {
 private $data; private $status; private $headers;
 function __construct($data=null,$status=200,$headers=[]){$this->data=$data;$this->status=$status;$this->headers=$headers;}
 function get_data(){return $this->data;}
 function get_status(){return $this->status;}
 function get_headers(){return $this->headers;}
 function header($key,$value){$this->headers[$key]=$value;}
}
class WC_Order {
 public $customer_id=42;
 public $status='processing';
 public $phone='09120000000';
 function get_id(){return 51;}
 function get_order_number(){return '51';}
 function get_customer_id(){return $this->customer_id;}
 function get_status(){return $this->status;}
 function get_date_created(){return null;}
 function get_date_modified(){return null;}
 function get_total(){return 450000;}
 function get_currency(){return 'IRR';}
 function get_payment_method_title(){return 'Test gateway';}
 function get_billing_phone(){return $this->phone;}
 function get_billing_first_name(){return 'Private';}
 function get_billing_last_name(){return 'Customer';}
 function get_formatted_billing_full_name(){return 'Private Customer';}
 function get_billing_state(){return 'Private state';}
 function get_billing_city(){return 'Private city';}
 function get_billing_address_1(){return 'Private address';}
 function get_billing_address_2(){return 'Private unit';}
 function get_shipping_method(){return 'Test shipping';}
 function get_items(){return [];}
 function get_meta($key){return $key==='_jsms_tracking'?'private-tracking-token':'';}
}
function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
function wc_get_orders($args){$GLOBALS['wc_order_queries']=$GLOBALS['wc_order_queries']??[];$GLOBALS['wc_order_queries'][]=$args;return $GLOBALS['wc_orders_result']??[];}

function show($name,$data){echo $name.': '.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}

function get_template_directory(){return rtrim(ABSPATH,'/');}
function get_template_directory_uri(){return home_url('/wp-content/themes/zarrin');}
function remove_filter(...$args){}
function add_shortcode(...$args){}
function get_role($role){return true;}
function get_current_user_id(){return $GLOBALS['authenticated_user']??0;}
function is_user_logged_in(){return get_current_user_id()>0;}
function wp_get_current_user(){return get_userdata(get_current_user_id())?:new WP_User(0,[]);}
function get_userdata($id){return $GLOBALS['users'][$id]??false;}
function user_can($user,$capability){return in_array($capability,$user->caps??[],true);}
function is_ssl(){return true;}
function wp_salt($scheme='auth'){return 'unit-test-salt-not-a-deployment-secret';}
function wp_rand($min,$max){return 123456;}
function wp_schedule_single_event($time,$hook,$args=[]){$GLOBALS['scheduled'][$hook.'|'.json_encode($args)]=[$time,$hook,$args];return true;}
function wp_next_scheduled($hook,$args=[]){return $GLOBALS['scheduled'][$hook.'|'.json_encode($args)][0]??false;}
function maybe_serialize($value){return is_array($value)||is_object($value)?serialize($value):(string)$value;}
function maybe_unserialize($value){$r=@unserialize($value);return $r!==false?$r:$value;}
function get_post_field($field,$id=0){return $GLOBALS['post_fields'][$id][$field]??'';}
function get_post_status($id=0){return $GLOBALS['posts'][$id]['post_status']??$GLOBALS['post_fields'][$id]['post_status']??false;}
function get_post_type($id=0){return $GLOBALS['post_types'][$id]??'product';}
function comments_open($post_id=0){
 $open=get_post_field('comment_status',$post_id)==='open';
 foreach($GLOBALS['filters']??[] as $filter){
  if(($filter[0]??'')!=='comments_open' || !is_callable($filter[1]??null)) continue;
  $accepted=max(1,(int)($filter[3]??1));
  $open=call_user_func_array($filter[1],array_slice(array($open,(int)$post_id),0,$accepted));
 }
 return (bool)$open;
}
function post_password_required($id=0){return (bool)get_post_field('post_password',$id);}
function sanitize_title($value){return (string)$value;}
function wc_clean($value){return sanitize_text_field($value);}
function wc_stock_amount($value){return (int)$value;}
function wc_get_product($id=0){return $GLOBALS['products'][$id]??false;}
// R84 — توابعِ ووکامرس که هنگامِ رندرِ واقعیِ قالبِ کارت لازم می‌شوند.
function wc_product_class($class='',$product=null,$echo=true){$css=trim('product '.$class);if($echo){echo ' class="'.htmlspecialchars($css,ENT_QUOTES).'"';}return $css;}
function wc_get_loop_prop($prop,$default=''){return $GLOBALS['loop_props'][$prop]??$default;}
function wc_set_loop_prop($prop,$value=''){$GLOBALS['loop_props'][$prop]=$value;}
function wc_get_image_size($size='woocommerce_thumbnail'){return array('width'=>300,'height'=>300,'crop'=>1);}
function wc_placeholder_img_src($size='woocommerce_thumbnail'){return home_url('/placeholder.png');}
function wc_get_gallery_image_html($id,$main=false){return '<img src="gallery-'.(int)$id.'.jpg" alt="">';}
function wc_get_template($name,$args=array(),$path='',$default=''){return true;}
function wc_get_template_part($slug,$name=''){if(function_exists('jluxe_test_render_card_probe')){jluxe_test_render_card_probe($slug,$name);}return true;}
function get_the_ID(){return $GLOBALS['current_product_id']??0;}
function wp_get_post_terms($id,$tax,$args=array()){return $GLOBALS['post_terms'][$id][$tax]??array();}

function wc_attribute_label($name,$product=null){return $name;}
function wc_dropdown_variation_attribute_options($args=array()){$options=isset($args["options"])?$args["options"]:array();$attribute=(string)($args["attribute"]??"");$selected=isset($args["selected"])?(string)$args["selected"]:"";echo '<select name="'.esc_attr("attribute_".$attribute).'">';foreach((array)$options as $o){$value=(string)$o;$label=function_exists('jluxe_attribute_option_label')?jluxe_attribute_option_label($value,$attribute,$args['product']??null):$value;echo '<option value="'.esc_attr($value).'"'.($value===$selected?' selected':'').'>'.esc_html($label).'</option>';}echo '</select>';}
function wc_get_products($args){$GLOBALS['product_query_args']=$args;if(isset($GLOBALS['product_query_handler'])&&is_callable($GLOBALS['product_query_handler'])){return call_user_func($GLOBALS['product_query_handler'],$args);}return $GLOBALS['product_query_results']??[];}
function wc_get_product_ids_on_sale(){return $GLOBALS['sale_ids']??[];}
function wc_format_coupon_code($value){return strtolower(trim($value));}
function wc_clear_notices(){$GLOBALS['notices']=[];}
function wc_get_notices($type){return $GLOBALS['notices'][$type]??[];}
function wp_send_json_error($data=[], $status=200){throw new JsonReply(false,$data,$status);}
function wp_send_json_success($data=[], $status=200){throw new JsonReply(true,$data,$status);}
function wp_die($message){throw new RuntimeException($message);}
function WC(){return $GLOBALS['wc'];}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES);}
function esc_html__($value,...$args){return $value;}
function wp_login_url(){return home_url('/wp-login.php');}
function wp_lostpassword_url(){return home_url('/wp-login.php?action=lostpassword');}
function wc_get_page_permalink($page){return home_url('/'.(['shop'=>'catalog','myaccount'=>'customer-zone'][$page]??$page).'/');}
function wc_get_account_endpoint_url($endpoint){return wc_get_page_permalink('myaccount').$endpoint.'/';}
function wc_get_cart_url(){return home_url('/basket/');}
function wc_get_checkout_url(){return home_url('/pay/');}
function wc_lostpassword_url(){return wc_get_account_endpoint_url('lost-password');}
function get_woocommerce_currency(){return $GLOBALS['currency']??'IRT';}
function get_woocommerce_currency_symbol($currency){return ['USD'=>'$','IRR'=>'ریال','IRT'=>'تومان'][$currency]??$currency;}
function wc_get_price_decimals(){return 0;}
function wc_get_price_decimal_separator(){return '.';}
function wc_get_price_thousand_separator(){return ',';}
function wc_price($amount){return '<span>'.$amount.'</span>';}
function get_permalink($id=0){return home_url('/product/'.$id.'/');}
function wp_get_attachment_image_url(...$args){$id=(int)($args[0]??0);$size=$args[1]??'thumbnail';$images=$GLOBALS['attachment_image_urls']??[];if(array_key_exists($id,$images)){$value=$images[$id];if(is_array($value)){if(array_key_exists($size,$value))return $value[$size];if(array_key_exists('default',$value))return $value['default'];return false;}return $value;}return home_url('/image.jpg');}
function wp_get_attachment_image_srcset(...$args){$id=(int)($args[0]??0);$size=$args[1]??'thumbnail';$sets=$GLOBALS['attachment_srcsets']??[];if(array_key_exists($id,$sets)){$value=$sets[$id];if(is_array($value)){if(array_key_exists($size,$value))return $value[$size];if(array_key_exists('default',$value))return $value['default'];return false;}return $value;}return 'https://shop.test/store/image-1024.jpg 1024w, https://shop.test/store/image-2048.jpg 2048w';}
function wp_get_attachment_url($id){if(array_key_exists($id,$GLOBALS['attachment_urls']??[]))return $GLOBALS['attachment_urls'][$id];return home_url('/uploads/'.$id.'.gif');}
function get_post_mime_type($id){return $GLOBALS['attachment_mimes'][$id]??'';}
function wp_get_attachment_metadata($id){if(array_key_exists($id,$GLOBALS['attachment_metadata']??[]))return $GLOBALS['attachment_metadata'][$id];return ['width'=>1200,'height'=>300];}
function wp_get_attachment_image_src($id,$size='thumbnail'){if(array_key_exists($id,$GLOBALS['attachment_image_sources']??[]))return $GLOBALS['attachment_image_sources'][$id];$url=wp_get_attachment_image_url($id,$size);return [$url,1200,300,true];}
function get_theme_mod($name,$default=false){return $GLOBALS['theme_mods'][$name]??$default;}
function wp_remote_get($url,$args=[]){$GLOBALS['provider_calls']=($GLOBALS['provider_calls']??0)+1; $GLOBALS['provider_url']=$url;return $GLOBALS['provider_response']??['response'=>['code'=>200],'body'=>'{"return":{"status":200},"entries":[]}'];}
function wp_remote_post($url,$args=[]){$GLOBALS['provider_args']=$args;$GLOBALS['http_posts'][]=['url'=>$url,'args'=>$args];$GLOBALS['provider_calls']=($GLOBALS['provider_calls']??0)+1;$GLOBALS['provider_url']=$url;if(!empty($GLOBALS['http_post_responses'])&&is_array($GLOBALS['http_post_responses'])){return array_shift($GLOBALS['http_post_responses']);}return $GLOBALS['http_post_response']??$GLOBALS['provider_response']??['response'=>['code'=>200],'body'=>''];}
function wp_remote_retrieve_response_code($response){return $response['response']['code'];}
function wp_remote_retrieve_body($response){return $response['body'];}
function wp_create_nonce($action){return 'nonce-'.$action;}
function wp_add_inline_script(...$args){}
function wp_localize_script($handle,$name,$data){$GLOBALS['localized'][$name]=$data;}
function nocache_headers(){$GLOBALS['no_cache']=true;}
function rest_url($path){return home_url('/wp-json/'.ltrim($path,'/'));}
function admin_url($path){return home_url('/wp-admin/'.ltrim($path,'/'));}
function get_comments($args){
 $GLOBALS['comment_queries'][]=$args;
 $comments=array_values(array_filter($GLOBALS['comments']??[],function($comment)use($args){
  return ($comment->comment_post_ID??1)==($args['post_id']??1) && ($comment->comment_approved??'1')==='1' && ($comment->comment_type??'review')===($args['type']??'review');
 }));
 return isset($args['number']) ? array_slice($comments,$args['offset']??0,$args['number']) : $comments;
}
function get_comment_meta($id,$key,...$args){return $GLOBALS['comment_meta'][$id][$key]??'';}
function update_comment_meta($id,$key,$value){$GLOBALS['comment_meta'][$id][$key]=$value;}
function get_comment($id){return $GLOBALS['comment_objects'][$id]??null;}
function wp_strip_all_shortcodes($text){return $text;}
function wp_insert_post($data,$errors=false){$GLOBALS['inserted_posts'][]=$data;return count($GLOBALS['inserted_posts']);}
function update_post_meta($id,$key,$value){$GLOBALS['post_meta'][$id][$key]=$value;}
function clean_post_cache($id){$GLOBALS['cleaned_posts'][]=(int)$id;}
function wc_delete_product_transients($id){$GLOBALS['deleted_product_transients'][]=(int)$id;}
function woocommerce_wp_checkbox($args){
 $id=(string)($args['id']??'');$value=(string)($args['value']??'');
 echo '<p class="form-field"><label for="'.esc_attr($id).'">'.esc_html($args['label']??'').'</label><input type="checkbox" id="'.esc_attr($id).'" name="'.esc_attr($id).'" value="1"'.('yes'===$value?' checked="checked"':'').' /></p>';
}
function wp_trim_words($text,$count,$more=''){return implode(' ',array_slice(explode(' ',$text),0,$count)).$more;}
function wp_trim_excerpt($text){return $text;}
function wp_parse_str($text,&$out){parse_str($text,$out);}
function wc_format_decimal($value){return is_numeric($value)?(string)$value:'';}
function get_queried_object_id(){return 1;}
function is_product(){return $GLOBALS['query_kind']==='product';}
function is_shop(){return $GLOBALS['query_kind']==='shop';}
function is_product_taxonomy(){return false;}
function wc_review_ratings_enabled(){return $GLOBALS['ratings_enabled']??true;}
function wc_review_ratings_required(){return $GLOBALS['ratings_required']??true;}
function is_cart(){return $GLOBALS['query_kind']==='cart';}
function is_checkout(){return $GLOBALS['query_kind']==='checkout';}
function is_account_page(){return $GLOBALS['query_kind']==='account';}
function is_date(){return false;}
function wp_cache_get($key,$group=''){return $GLOBALS['objcache'][$group][$key]??false;}
function wp_cache_set($key,$value,$group='',$ttl=0){$GLOBALS['objcache'][$group][$key]=$value;return true;}
function wp_dequeue_script($handle){$GLOBALS['dequeued_scripts'][]=$handle;}
function wp_dequeue_style($handle){$GLOBALS['dequeued_styles'][]=$handle;}
function is_front_page(){return $GLOBALS['query_kind']==='front';}
function is_singular(){return $GLOBALS['query_kind']==='single';}
function is_category(){return false;}
function is_tag(){return false;}
function is_tax(){return $GLOBALS['query_kind']==='tax';}
function is_post_type_archive(){return $GLOBALS['query_kind']==='archive';}
function is_home(){return $GLOBALS['query_kind']==='blog';}
function is_search(){return $GLOBALS['query_kind']==='search';}
function is_404(){return $GLOBALS['query_kind']==='404';}
function get_query_var($name,$default=''){return $GLOBALS['query_vars'][$name]??$default;}
function wc_get_page_id($name){return 10;}
function get_queried_object(){return (object)['term_id'=>2];}
function get_term_link($term){return home_url('/category/gold/');}
function get_post_type_archive_link($type){return home_url('/'.$type.'/');}
function wp_get_canonical_url(){return home_url('/article/');}
function user_trailingslashit($value,$type=''){return rtrim($value,'/').'/';}
function trailingslashit($value){return rtrim($value,'/').'/';}
function add_query_arg($key,$value=null,$url=null){
 if(is_array($key)){ $args=$key; $url=$value; }else{$args=[$key=>$value];}
 $parts=parse_url($url);parse_str($parts['query']??'',$q);$q=array_merge($q,$args);
 foreach($q as $k=>$v)if($v===false)unset($q[$k]);
 return preg_replace('/\?.*$/','',$url).($q?'?'.http_build_query($q):'');
}
class WP_User {
 public $ID; public $roles; public $caps=[]; public $user_login; public $user_pass=''; public $display_name='Test customer'; public $user_email='test@example.invalid';
 function __construct($id,$roles=['customer']){$this->ID=$id;$this->roles=$roles;$this->user_login='customer'.$id;}
 function exists(){return $this->ID>0;}
}
class JsonReply extends RuntimeException {
 public $success;public $data;public $status;
 function __construct($success,$data,$status){parent::__construct('JSON response');$this->success=$success;$this->data=$data;$this->status=$status;}
}
class WC_Product {
 public $id;public $type='simple';public $status='publish';public $visibility='visible';public $sold=false;public $stock=20;public $manage_stock=false;public $parent=0;public $children=[];public $variationCalls=0;public $meta=[];public $image_id=0;
 function __construct($id){$this->id=$id;}
 function exists(){return true;}
 function get_id(){return $this->id;}
 function update_meta_data($key,$value){$this->meta[$key]=$value;}
 function is_type($type){return is_array($type)?in_array($this->type,$type):$this->type===$type;}
 function get_status(){return $this->status;}
 function get_catalog_visibility(){return $this->visibility;}
 function get_parent_id(){return $this->parent;}
 function is_sold_individually(){return $this->sold;}
 function is_purchasable(){return $this->status==='publish';}
 function is_in_stock(){return $this->stock>0;}
 function managing_stock(){return $this->manage_stock;}
 function get_stock_quantity(){return $this->stock;}
 function get_max_purchase_quantity(){return $this->sold?1:$this->stock;}
 function has_enough_stock($quantity){return $quantity<=$this->stock;}
 function get_stock_managed_by_id(){return $this->parent?:$this->id;}
 function get_children(){return $this->children;}
 function get_available_variations(){$this->variationCalls++;return ['test-variation'];}
 function get_image_id(){return $this->image_id;}
 function get_name(){return 'Public product';}
 function get_attributes(){return [];}
 function get_price_html(){return $GLOBALS['product_price_html'][$this->id]??'100';}
 function is_on_sale(){return true;}
 function get_price($context='view'){return $GLOBALS['product_prices'][$this->id]['price']??0.0;}
 function get_regular_price($context='view'){return $GLOBALS['product_prices'][$this->id]['regular']??0.0;}
 function get_cross_sell_ids(){return $GLOBALS['product_cross_sells'][$this->id]??[];}
 function get_category_ids(){return $GLOBALS['product_category_ids'][$this->id]??[];}
 function get_default_attributes(){return $GLOBALS['product_defaults'][$this->id]??[];}
 function get_permalink(){return home_url('/product/'.$this->id.'/');}
 function get_short_description(){return 'Short description';}
 function get_description(){return 'Product description';}
 // R84 — متدهایی که قالبِ کارتِ محصول (`woocommerce/content-product.php`)
 // در زمانِ رندر صدا می‌زند. بدونِ این‌ها هیچ تستی نمی‌توانست قالب را واقعاً
 // رندر کند و خطاهای زمانِ اجرا (مثل «Undefined constant» نسخهٔ 1.65) دیده
 // نمی‌شدند — همان شکافی که یک باگِ واقعی را تا روی سرورِ زنده برد.
 function is_visible(){return true;}
 function get_variation_price($min_or_max='min',$for_display=false){$range=$GLOBALS['variation_price_ranges'][$this->id]['price']??null;if(is_array($range)&&array_key_exists($min_or_max,$range))return $range[$min_or_max];return $GLOBALS['product_prices'][$this->id]['price']??0.0;}
 function get_variation_regular_price($min_or_max='min',$for_display=false){$range=$GLOBALS['variation_price_ranges'][$this->id]['regular']??null;if(is_array($range)&&array_key_exists($min_or_max,$range))return $range[$min_or_max];return $GLOBALS['product_prices'][$this->id]['regular']??0.0;}
 function get_sale_price($context='view'){return ($GLOBALS['product_prices'][$this->id]['regular']??0.0)>($GLOBALS['product_prices'][$this->id]['price']??0.0)?($GLOBALS['product_prices'][$this->id]['price']??0.0):'';}
 function get_review_count(){return (int)($GLOBALS['product_review_counts'][$this->id]??0);}
 function get_average_rating(){return (float)($GLOBALS['product_avg_ratings'][$this->id]??0.0);}
 function get_gallery_image_ids(){return (array)($GLOBALS['product_gallery_ids'][$this->id]??[]);}
 function add_to_cart_url(){return home_url('/?add-to-cart='.$this->id);}
}
class WooCommerce {}
class FakeSession { public $data=[];
 function get($key){return $this->data[$key]??null;}
 function set($key,$value){$this->data[$key]=$value;}
}
class FakeCart {
 public $items=[]; public $added=0;public $changed=0; public $addFails=false;public $mutateOnFailure=false; public $fees=[];
 function get_cart(){return $this->items;}
 function add_fee($name,$amount,$taxed=true){$this->fees[]=[$name,$amount];return true;}
 function get_cart_item($key){return $this->items[$key]??[];}
 function remove_cart_item($key){if(!isset($this->items[$key]))return false;unset($this->items[$key]);return true;}
 function set_quantity($key,$quantity,$refresh=true){$this->changed++;if(!$quantity)return $this->remove_cart_item($key);$this->items[$key]['quantity']=$quantity;return true;}
 function add_to_cart($id,$qty,$variation=0,$attrs=[]){$this->added++;if(!$this->addFails||$this->mutateOnFailure)$this->items['new']=['data'=>wc_get_product($variation?:$id),'product_id'=>$id,'variation_id'=>$variation,'variation'=>$attrs,'quantity'=>$qty];return $this->addFails?false:'new';}
 public $stockFail=false;
 function check_cart_item_stock(){return $this->stockFail?new WP_Error('out-of-stock','stub stock failure'):true;}
 function get_cart_item_quantities(){$out=[];foreach($this->items as $item){$id=$item['data']->get_stock_managed_by_id();$out[$id]=($out[$id]??0)+$item['quantity'];}return $out;}
 function calculate_totals(){}
 function get_product_subtotal($product,$qty){return '100';}
 function get_applied_coupons(){return [];}
 function get_cart_contents_count(){return array_sum(array_column($this->items,'quantity'));}
 function get_total($context='view'){return 1250000.0;}
 function get_subtotal(){return 100;}
 function get_discount_total(){return 0;}
}
class FakeWpdb {
 public $options='wp_options';public $posts='wp_posts';public $comments='wp_comments';public $postmeta='wp_postmeta';public $wc_product_meta_lookup='wp_wc_product_meta_lookup';public $prefix='wp_';
 function prepare($sql,...$args){return serialize([$sql,$args]);}
 function get_charset_collate(){return '';}
 function get_var($prepared){
  [$sql,$args]=unserialize($prepared);
  if(strpos($sql,'SELECT option_value')===0)return isset($GLOBALS['options'][$args[0]])?maybe_serialize($GLOBALS['options'][$args[0]]):null;
  if(strpos($sql,'SELECT id FROM wp_jluxe_stock_alerts')===0){
   if(!empty($GLOBALS['stock_alert_db_error']))return false;
   foreach($GLOBALS['stock_alert_rows']??[] as $row){if($row['product_id']===(int)$args[0]&&$row['variation_id']===(int)$args[1]&&$row['phone_hash']===$args[2])return $row['id'];}
   return null;
  }
  throw new RuntimeException('Unexpected SQL read: '.$sql);
 }
 function query($prepared){
  [$sql,$args]=unserialize($prepared);$key=$args[0];
  if(strpos($sql,'INSERT IGNORE')===0){if(array_key_exists($key,$GLOBALS['options']))return 0;$GLOBALS['options'][$key]=$args[1];return 1;}
  if(strpos($sql,'DELETE FROM')===0){if(isset($GLOBALS['options'][$key])&&maybe_serialize($GLOBALS['options'][$key])===$args[1]){unset($GLOBALS['options'][$key]);return 1;}return 0;}
  throw new RuntimeException('Unexpected SQL write: '.$sql);
 }
 function insert($table,$data,$format=null){
  if($table!=='wp_jluxe_stock_alerts')return false;
  foreach($GLOBALS['stock_alert_rows']??[] as $row){if($row['product_id']===$data['product_id']&&$row['variation_id']===$data['variation_id']&&$row['phone_hash']===$data['phone_hash'])return false;}
  $id=count($GLOBALS['stock_alert_rows']??[])+1;$GLOBALS['stock_alert_rows'][$id]=array_merge(['id'=>$id],$data);return 1;
 }
 function get_results($prepared,$output=null){
  [$sql,$args]=unserialize($prepared);
  if(strpos($sql,'SELECT id, phone_cipher FROM wp_jluxe_stock_alerts')===0){
   if(!empty($GLOBALS['stock_alert_db_error']))return null;
   $rows=[];foreach($GLOBALS['stock_alert_rows']??[] as $row){if($row['product_id']===(int)$args[0]&&$row['variation_id']===(int)$args[1]&&$row['notification_sent_at']===null){$rows[]=['id'=>$row['id'],'phone_cipher'=>$row['phone_cipher']];if(count($rows)>=100)break;}}
   return $rows;
  }
  throw new RuntimeException('Unexpected SQL result read: '.$sql);
 }
 function update($table,$data,$where,$format=null,$where_format=null){
  if($table!=='wp_jluxe_stock_alerts')return false;
  foreach($GLOBALS['stock_alert_rows']??[] as $id=>$row){
   if((int)$id===(int)($where['id']??0)&&(!array_key_exists('notification_sent_at',$where)||$row['notification_sent_at']===$where['notification_sent_at'])){$GLOBALS['stock_alert_rows'][$id]=array_merge($row,$data);return 1;}
  }
  return 0;
 }
}
$GLOBALS['wc']=(object)['cart'=>new FakeCart()];
$GLOBALS['wpdb']=new FakeWpdb();
$GLOBALS['users'][42]=new WP_User(42);
$GLOBALS['query_kind']='shop';
$_SERVER['REMOTE_ADDR']='192.0.2.8';
set_error_handler(function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);return false;});

function get_post_meta($id,$key,...$args){return $GLOBALS['post_meta'][$id][$key]??'';}
function get_locale(){return 'fa_IR';}
function untrailingslashit($text){return rtrim($text,'/');}
function get_header(){}
function get_footer(){}
function is_wc_endpoint_url($endpoint){return ($GLOBALS['endpoint']??'')===$endpoint;}
function do_shortcode($code){return '<form data-woo-account>Native Woo account recovery</form>';}
class WP_Query {
 public $args;
 public $posts=[];
 function __construct($args=[]){
  $this->args=$args;
  $GLOBALS['wp_query_calls'][]=$args;
  if(isset($GLOBALS['wp_query_handler']) && is_callable($GLOBALS['wp_query_handler'])){
   $posts=call_user_func($GLOBALS['wp_query_handler'],$args);
   $this->posts=is_array($posts)?$posts:[];
  }else{
   $this->posts=is_array($GLOBALS['wp_query_result']??null)?$GLOBALS['wp_query_result']:[];
  }
 }
 function get($key){return $this->args[$key]??null;}
 function set($key,$value){$this->args[$key]=$value;}
}
function wp_reset_postdata(){}
function wp_timezone(){return new DateTimeZone('Asia/Tehran');}
function wp_date($format,$timestamp,$timezone=null){return (new DateTimeImmutable('@'.$timestamp))->setTimezone($timezone??wp_timezone())->format($format);}
const OBJECT='OBJECT';
const ARRAY_A='ARRAY_A';
function dbDelta($sql){$GLOBALS['dbdelta_calls'][]=$sql;return [];}
class WP_Post { public $ID=1; public $post_status='publish'; public $post_password=''; public $post_type='product'; public $post_title=''; }
function get_page_by_path($slug,...$args){return $GLOBALS['existing_pages'][$slug]??null;}
function taxonomy_exists($taxonomy){return array_key_exists($taxonomy,$GLOBALS['taxonomies']??[]);}
function get_term_by($field,$value,$taxonomy){$terms=$GLOBALS['terms_by_slug'][$taxonomy]??[];if(isset($terms[$value]))return $terms[$value];foreach($terms as $slug=>$term){if(!is_object($term))continue;if('slug'===$field&&isset($term->slug)&&(string)$term->slug===(string)$value)return $term;if('name'===$field&&isset($term->name)&&(string)$term->name===(string)$value)return $term;}return false;}
function wp_count_posts($type='post'){return (object)($GLOBALS['post_counts'][$type]??[]);}
function wp_update_post($data,$errors=false){$GLOBALS['updated_posts'][]=$data;return $data['ID']??0;}
function wp_safe_redirect($url,$status=302){$GLOBALS['redirects'][]=$url;return true;}
function wp_nonce_field(...$a){echo '';}


class WP_REST_Server { const READABLE='GET'; const CREATABLE='POST'; }
function register_rest_route($namespace,$route,$args){$GLOBALS['routes'][$namespace.$route]=$args;}

function wc_reviews_enabled(){return get_option('woocommerce_enable_reviews','yes')==='yes';}

function is_multisite(){return $GLOBALS['multisite']??false;}

/* — R32: هوش مصنوعی دیدگاه‌ها (پاسخ خودکار + خلاصهٔ قابل‌ویرایش) — */
class WP_Comment { public $comment_ID=1; public $comment_post_ID=1; public $comment_parent=0; public $comment_approved="1"; public $comment_type="comment"; public $comment_author=""; public $comment_author_email=""; public $comment_author_url=""; public $comment_content=""; public $user_id=0; }
function get_post($id=0){ $id=(int)$id; $data=$GLOBALS['posts'][$id]??null; if(!$data){return null;} $post=new WP_Post(); foreach($data as $k=>$v){$post->$k=$v;} return $post; }
function get_the_title($id=0){ return get_post_field('post_title',$id); }
function get_posts($args=[]){ $GLOBALS['post_queries'][]=$args; $out=[]; foreach(($GLOBALS['sites_posts']??[]) as $p){ if(($args['post_type']??'post')!==($p['post_type']??'post')) continue; if(($args['post_status']??'publish')!==($p['post_status']??'publish')) continue; $out[]=(int)$p['ID']; } return array_slice($out,0,$args['posts_per_page']??5); }
function wp_insert_comment($data){ static $next=5000; $id=$next++; $comment=new WP_Comment(); foreach(array('comment_ID'=>$id,'comment_parent'=>0,'comment_author'=>'','comment_author_email'=>'','comment_content'=>'','comment_approved'=>1,'comment_type'=>'comment') as $k=>$v){$comment->$k=$v;} foreach($data as $k=>$v){$comment->$k=$v;} $GLOBALS['comments'][$id]=$comment; $GLOBALS['comment_objects'][$id]=$comment; $GLOBALS['inserted_comments'][]=$comment; return $id; }
function delete_post_meta($id,$key){ unset($GLOBALS['post_meta'][$id][$key]); return true; }
function wp_schedule_event($time,$recurrence,$hook,$args=[]){$GLOBALS['scheduled'][$hook.'|'.json_encode($args)]=[$time,$hook,$args,$recurrence];return true;}
function wp_clear_scheduled_hook($hook){ $removed=0; foreach(array_keys($GLOBALS['scheduled']??[]) as $k){ if(strpos($k,$hook.'|')===0){ unset($GLOBALS['scheduled'][$k]); ++$removed; } } return $removed; }

/* — R34..R39: طراحی/UX (۴۰۴، توکن‌ها، آیکون‌ها، بردکرامب، نوار چسبان، preload فونت) — */
class WP_Term { public $term_id=7; public $name='دسته'; public $parent=0; public $slug='cat'; public $taxonomy='product_cat'; public $count=0; }
function wc_get_product_terms($id,$tax,$args=[]){ return $GLOBALS['product_terms'][$id]??[]; }
function get_term($id,$tax){ return ($GLOBALS['terms_by_id'][$id]??null) ?: new WP_Error('invalid_term','Term not found'); }
function get_search_query(){ return $GLOBALS['search_query']??''; }
// R88 — PWA
function has_site_icon(){ return !empty($GLOBALS['site_icon']); }
function get_site_icon_url($size=512){ return !empty($GLOBALS['site_icon']) ? home_url('/wp-content/uploads/icon-'.$size.'.png') : ''; }
function status_header($code){ $GLOBALS['status_header']=$code; }
if(!function_exists('wp_hash_password')){function wp_hash_password($p){return password_hash((string)$p,PASSWORD_DEFAULT);}}
if(!function_exists('wp_check_password')){function wp_check_password($p,$h,$id=''){return password_verify((string)$p,(string)$h);}}
if(!function_exists('checked')){function checked($a,$b=true,$echo=true){$r=((string)$a===(string)$b)?' checked=\'checked\'':'';if($echo)echo $r;return $r;}}
if(!function_exists('selected')){function selected($a,$b=true,$echo=true){$r=((string)$a===(string)$b)?' selected=\'selected\'':'';if($echo)echo $r;return $r;}}
if(!function_exists('esc_textarea')){function esc_textarea($s){return htmlspecialchars((string)$s,ENT_QUOTES);}}
if(!function_exists('get_terms')){function get_terms($args=array()){
 $out=array();
 foreach($GLOBALS['test_terms']??array() as $t){
  if(isset($args['taxonomy'])&&$t->taxonomy!==$args['taxonomy'])continue;
  if(array_key_exists('parent',$args)&&(int)$t->parent!==(int)$args['parent'])continue;
  if(!empty($args['hide_empty'])&&(int)$t->count<1)continue;
  if(isset($args['name__like'])&&stripos((string)$t->name,(string)$args['name__like'])===false)continue;
  if(isset($args['object_ids'])){
   $ids=array_map('intval',(array)$args['object_ids']);
   $objects=$GLOBALS['test_term_objects'][$t->taxonomy][(int)$t->term_id]??array();
   if(!array_intersect($ids,array_map('intval',(array)$objects)))continue;
  }
  $out[]=$t;
 }
 if(($args['orderby']??'')==='count'){
  usort($out,static function($a,$b)use($args){$order=($args['order']??'ASC')==='DESC'?-1:1;return $order*((int)$a->count<=>(int)$b->count);});
 }
 if(isset($args['number'])&&(int)$args['number']>0)$out=array_slice($out,0,(int)$args['number']);
 return $out;
}}
if(!function_exists('term_exists')){function term_exists($id,$tax=''){return isset($GLOBALS['terms_by_id'][(int)$id])?array('term_id'=>(int)$id):null;}}
if(!function_exists('get_term_meta')){function get_term_meta($id,$key='',$single=false){return $GLOBALS['term_meta'][(int)$id][$key]??'';}}
if(!function_exists('wp_dropdown_pages')){function wp_dropdown_pages($a=array()){echo '<select name="'.htmlspecialchars((string)($a['name']??''),ENT_QUOTES).'"></select>';}}
if(!function_exists('is_page')){function is_page(...$a){return !empty($GLOBALS['is_page']);}}
