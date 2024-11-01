<?php
define('PLUGIN_VERSION','1.0.5');

/**
 * Plugin Name: Super Product Tabs for WooCommerce
 * Plugin URI: http://www.bemoore.com/
 * Description: Allows you to set custom tabs per product.
 * Version: 1.0.5
 * Author: Bob Moore, BeMoore Software
 * Author URI: http://www.bemoore.com/
 * License: GPL2
 * Text Domain: wooc-super-product-tabs
* Requires at least: 3.7
* Tested up to: 4.5.0
*/

function wooc_super_product_tabs_get_tab_index_from_title($title)
{
	return str_replace(" ","_",strtolower($title));
}

function wooc_super_product_tabs_get_category_tabs(&$tabs,$term_id)
{
	if(function_exists ( 'wooc_super_product_tabs_pro_get_category_tabs' ))
		return wooc_super_product_tabs_pro_get_category_tabs($tabs,$term_id);
	else
		return null;
}

function wooc_super_product_tabs_get_global_tabs(&$tabs)
{
	if(function_exists ( 'wooc_super_product_tabs_pro_get_global_tabs' ))
		return wooc_super_product_tabs_pro_get_global_tabs($tabs);
	else
		return null;
}

function wooc_super_product_tabs_get_tabs_form($tabs,$type = 'post')
{
	if($type == 'category')
	{
		$term_id = $_REQUEST['tag_ID'];
		wooc_super_product_tabs_get_category_tabs($tabs,$term_id);		
	}	
	
	if($type == 'global')
		wooc_super_product_tabs_get_global_tabs($tabs);		
?>	
	<div>
		<button id="reset-tab" name="meta-box-button-reset" value="reset" >Reset To Defaults</button>
		<label for="new-tab-title">New Tab</label>
		<input id="new-tab-title" name="new-tab-title" type="text" >
		<label for="new-tab-order">Order</label>
		<input id="new-tab-order" name="new-tab-order" type="text" size="4" >
		<button id="add-new-tab" name="meta-box-button-add-new" >Add New Tab</button>
		<button id="save-tab" name="meta-box-button-save" >Save Tabs</button>
	</div>
<?php	
	//Add the global tabs
	$global_tabs = get_option('meta_box_tabs');	
	
	foreach($global_tabs as $gindex => $gvalues)
	{
		$found_tab = false;
		
		foreach($tabs as $index => $values)
		{
			if ($index == $gindex)
				$found_tab = true;
		}
		
		if(!$found_tab)	//No local tab, add ...
		{
			$tabs[$gindex] = $gvalues;
			
			if(isset($gvalues['hidden']))
				$tabs[$gindex]['hidden'] = 'checked';			
		}
		else
		{
			//A local tab, let's see if it's a default or saved ...
			if(!isset($tabs[$gindex]['saved']))
			{
				if(isset($gvalues['hidden']))
					$tabs[$gindex]['hidden'] = 'checked';			
			}
		}
	}
?>	
	<?php if(count($tabs) > 0) { ?>
	<div id="tabs">
	  <ul>
		<?php foreach($tabs as $index => $values){ 
		if($values['hidden'] == 'checked') {	?>
		<li class="hid"><a href="#tabs-<?php echo $index; ?>"><?php echo $values['title']; ?></a></li>
		<?php }
		else { 
			?>
			<li><a href="#tabs-<?php echo $index; ?>"><?php echo $values['title']; ?></a></li>
		<?php } ?>
	<?php } ?>
	  </ul>
	  
	<?php foreach($tabs as $index => $values){ ?>
	  <div id="tabs-<?php echo $index; ?>">
		<div>
			<input type="checkbox" name="meta_box_tabs[<?php echo $index; ?>][hidden]" value="1" <?php echo $values['hidden']; ?> />
			<label for="meta_box_tabs[<?php echo $index; ?>][hidden]">Hidden</label>
			<?php if(!wooc_super_product_tabs_is_special_tab($index)) { ?>
			<input type="checkbox" name="meta_box_tabs[<?php echo $index; ?>][delete]" value="1"  />
			<label for="meta_box_tabs[<?php echo $index; ?>][delete]">Delete</label>
			<?php } ?>				
		</div>
		<div>
			<label for="meta_box_tabs[<?php echo $index; ?>][title]">Title</label>
			<input id="meta_box_tabs[<?php echo $index; ?>][title]" name="meta_box_tabs[<?php echo $index; ?>][title]" type="text" value="<?php echo $values['title']; ?>" >	
		</div>
		<div>
			<label for="meta_box_tabs[<?php echo $index; ?>][order]">Order</label>
			<input id="meta_box_tabs[<?php echo $index; ?>][order]" name="meta_box_tabs[<?php echo $index; ?>][order]" type="text" size="4" value="<?php echo $values['order']; ?>" >	
		</div>
		<?php if(!wooc_super_product_tabs_is_special_tab($index)) { 
			if($type != 'global'){
			?>
		<div>
			<label for="meta_box_tabs[<?php echo $index; ?>][text]">Content</label>
			<?php 
			$editor_id = 'meta_box_tabs_'. $index; 
			$content = isset($values['text'])?$values['text']:'';
			wp_editor( $content, $editor_id ); 
			?>
		</div>
		<?php } } ?> 
		
		<?php if($type == 'category') { ?>
			<input type="checkbox" name="meta_box_tabs[<?php echo $index; ?>][subcategories]" value="1" <?php echo $values['subcategories']; ?> />
			<label for="meta_box_tabs[<?php echo $index; ?>][subcategories]">Don't apply to subcategories</label>
		<?php } ?> 

	  </div>
	<?php } ?> 
	</div>        
<?php	
	}
}

function wooc_super_product_tabs_set_default_tabs(&$tabs)
{
	//These are 2 special default tabs
	if(!isset($tabs['description']))
		$tabs['description']['title'] = 'Description';
		
	if(!isset($tabs['reviews']))
		$tabs['reviews']['title'] = 'Reviews';

	$k = 0;
	foreach($tabs as $key => $values)
	{
		//We don't want to overwrite the text
		//if($key != 'description' && $key != 'reviews')
		//	$tabs[$key]['text'] = '';
			
		$tabs[$key]['order'] = $k;
		$tabs[$key]['hidden'] = '';
		$k++;
	}
}

function wooc_super_product_tabs_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "meta-box-nonce");

	$meta_box_tabs = get_post_meta( $object->ID, 'meta_box_tabs' ); 
	$tabs = array();
	wooc_super_product_tabs_set_default_tabs($tabs);
	
	//These are the saved ones.
	foreach ( $meta_box_tabs as $meta_box_tab)
	{ 
		foreach($meta_box_tab as $tab => $values)
		{	
			foreach($values as $tab_key => $tab_value)
			{
				$tabs[$tab][$tab_key] = $tab_value;
			}
			
			if(!isset($tabs[$tab]['hidden']))
				$tabs[$tab]['hidden'] = '';
		}
	}

	wooc_super_product_tabs_get_tabs_form($tabs);
}

function wooc_super_product_tabs_custom_meta_box()
{
    add_meta_box("wooc-super-product-tabs-meta-box", "Super Product Tabs", "wooc_super_product_tabs_meta_box_markup", "product", "normal", "high", null);
}

add_action("add_meta_boxes", "wooc_super_product_tabs_custom_meta_box");

function wooc_super_product_tabs_build_tab_object($request)
{
	$meta_box_tabs = "";
	$meta_box_tabs_value = array();
	$count = 0;

	if(isset($request["meta_box_tabs"]))
	{
		foreach($request["meta_box_tabs"] as $id => $values)
		{
			if(!isset($values['delete']))
			{
				$meta_box_tabs_value[$id] = $values;

				if(!isset($values['hidden']))
					$meta_box_tabs_value[$id]['hidden'] = '';
				else
					$meta_box_tabs_value[$id]['hidden'] = 'checked';
					
				$content_id = 'meta_box_tabs_'. $id;
				
				if(isset($request[$content_id]))
					$meta_box_tabs_value[$id]['text'] = $request[$content_id];
					
				$meta_box_tabs_value[$id]['saved'] = "1";					
			}
		}
	}
	
	if(isset($request["new-tab-title"]) && trim($request["new-tab-title"]) != "")
	{
		$new_id = wooc_super_product_tabs_get_tab_index_from_title($request["new-tab-title"]);
		
		if(wooc_super_product_tabs_is_special_tab($new_id))
		{
			$message = '<p>You cannot add a new tab called Description or Reviews as these are default tabs in WooCommerce.</p>';
			
			add_settings_error(
			'wooc-super-product-tabs',
			'wooc-super-product-tabs',
			$message,
			'error');

			set_transient( 'wooc_super_product_tabs_errors', get_settings_errors(), 30 );
			return;
		}
		else
		{
			$meta_box_tabs_value[$new_id]['title'] = $request["new-tab-title"];
			$meta_box_tabs_value[$new_id]['order'] = $request["new-tab-order"];
			$meta_box_tabs_value[$new_id]['saved'] = "1";
		}
	}
	
	return $meta_box_tabs_value;
}

function wooc_super_product_tabs_save_custom_meta_box($post_id, $post, $update)
{
    if (!isset($_REQUEST["meta-box-nonce"]) || !wp_verify_nonce($_REQUEST["meta-box-nonce"], basename(__FILE__)))
        return $post_id;

    if(!current_user_can("edit_post", $post_id))
        return $post_id;

    if(defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
        return $post_id;

    $slug = "product";
    if($slug != $post->post_type)
        return $post_id;

	//Emergency reset
	$meta_box_tabs_value = array();
/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';
	die();
*/        
	if(!isset($_REQUEST['meta-box-button-reset']))
	{
		if(isset($_REQUEST["meta_box_tabs"]))
		{
			$meta_box_tabs_value = wooc_super_product_tabs_build_tab_object($_REQUEST);
			
			$count = count($meta_box_tabs_value);
			
			//Now check if there are more than 3
			if($count > 3 && !wooc_super_product_tabs_isProActive())
			{
				$extra_tabs = $count - 2;
				
				$message = '<p>There is only one extra tab allowed. You have '.$extra_tabs.'.</p>';
				$message .= '<p>You need the Pro version for this to work.</p>';
				$message .= '<p>You can purchase it <a href="'.wooc_super_product_tabs_get_purchase_url().'" target="_blank">here</a>.';
				
				add_settings_error(
				'wooc-super-product-tabs',
				'wooc-super-product-tabs',
				$message,
				'error');

				set_transient( 'wooc_super_product_tabs_errors', get_settings_errors(), 30 );
				return;
			}
		}
	}		
		
	/*	echo '<pre>';
		print_r($meta_box_tabs_value);
		echo '</pre>';
		
		die();*/
		
		update_post_meta($post_id, "meta_box_tabs", $meta_box_tabs_value);

}

add_action("save_post", "wooc_super_product_tabs_save_custom_meta_box", 10, 3);

function wooc_super_product_tabs_scripts($hook) 
{
	//woocommerce_page_wooc-super-product-tabs-page
	//print_r($hook);
	wp_enqueue_style( 'super-product-tabs-style', plugins_url('css/style.css', __FILE__) );	
	//wp_enqueue_style( 'jquery-ui-style-css');
	
	wp_enqueue_style('wooc_super_product_tabs-admin-ui-css',
                'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.21/themes/base/jquery-ui.css',
                false,
                PLUGIN_VERSION,
                false);
	
	wp_register_script('super_product_tabs', plugins_url('js/ultimate-tabs.js', __FILE__), array('jquery','jquery-ui-core','jquery-ui-tabs'));
	wp_enqueue_script('super_product_tabs');
}

add_action( 'admin_enqueue_scripts', 'wooc_super_product_tabs_scripts',5,1); 

add_action( 'admin_notices', 'wooc_super_product_tabs_admin_notices' );		
 
function wooc_super_product_tabs_admin_notices() {				  
	// If there are no errors, then we'll exit the function		  
	if ( ! ( $errors = get_transient( 'wooc_super_product_tabs_errors' ) ) ) 
	{		    
		return;		  
	}				  
	// Otherwise, build the list of errors that exist in the settings errores		  
	$message = '<div id="wooc-super-product-tabs-message" class="error below-h2"><p><ul>';		  
	foreach ( $errors as $error ) {
		$message .= '<li>' . $error['message'] . '</li>';		  
	}		  
	$message .= '</ul></p></div><!-- #error -->';				  // Write them out to the screen		  
	echo $message;				  
	
	// Clear and the transient and unhook any other notices so we don't see duplicate messages		  
	delete_transient( 'wooc_super_product_tabs_errors' );		  
	remove_action( 'admin_notices', 'wooc_super_product_tabs_admin_notices' );				
}

//Add the tabs
function wooc_super_product_tabs_content($tab_name,$tab)
{
	echo do_shortcode($tab['callback_parameters']); // display "stuff";
}

function wooc_super_product_tabs_is_special_tab($tab)
{
	if($tab == 'description' || $tab == 'reviews')
		return true;
		
	return false;
}

function wooc_super_product_tabs_frontend( $existing_tabs ) {

	$meta_box_tabs = get_post_meta( get_the_ID(), 'meta_box_tabs' ); 

	$count = 0;
	
	//Global tabs
	if(function_exists ( 'wooc_super_product_tabs_pro_get_global_tabs' ))
		wooc_super_product_tabs_pro_get_global_tabs($existing_tabs);
		
	//Category tabs
	//if(function_exists ( 'wooc_super_product_tabs_pro_get_category_tabs' ))
	//	wooc_super_product_tabs_pro_get_category_tabs($existing_tabs);	
	

	//Add back in the description
	//$existing_tabs['description']['text'] = get_post_field('post_content',  get_the_ID() );
	
	//Product tabs
	foreach ( $meta_box_tabs as $meta_box_tab)
	{ 
		foreach($meta_box_tab as $tab => $values)
		{	
			if(isset($values['hidden']) && $values['hidden'] == 'checked')
				unset($existing_tabs[$tab]);
			else
			{
				if( ! wooc_super_product_tabs_is_special_tab($tab) )
				{
					$existing_tabs[$tab]['title'] = $values['title'];
					$existing_tabs[$tab]['priority'] = $values['order'] * 10;
					$existing_tabs[$tab]['callback'] = 'wooc_super_product_tabs_content';	
					$existing_tabs[$tab]['callback_parameters'] = isset($values['text'])? $values['text'] : '';	
				}
			}
		}
	}
	
	//print_r($existing_tabs);
	
	return $existing_tabs;
}

add_filter( 'woocommerce_product_tabs', 'wooc_super_product_tabs_frontend', 98 );

//Product Categories form ...
function wooc_super_product_tabs_add_category_form() 
{
?>
	<div class="postbox">
	<div class="inside">	
	<h3>Super Product Tabs</h3>		
<?php		
	wooc_super_product_tabs_buy_now_message();
	// this will add the custom meta field to the add new term page
	$tabs = array();	
	wooc_super_product_tabs_get_tabs_form($tabs,'category');	
	?>
	</div>
	</div>
<?php
}
//add_action( 'product_cat_add_form_fields', 'wooc_super_product_tabs_add_category_form', 10, 2 );
add_action( 'product_cat_edit_form_fields', 'wooc_super_product_tabs_add_category_form', 10, 2 );


/* WordPress menu items */
add_action( 'admin_menu', 'wooc_super_product_tabs_menu' );

/** Step 1. */
function wooc_super_product_tabs_menu() {
	 add_submenu_page( 'woocommerce', 'Super Product Tabs', 'Super Product Tabs', 'manage_options', 'wooc-super-product-tabs-page', 'wooc_super_product_tabs_options' ); 
}

function wooc_super_product_tabs_get_purchase_url()
{
	return 'http://www.bemoore.com/products/wordpress-plugins-pro/super-product-tabs-pro-for-woocommerce/';
}

function wooc_super_product_tabs_buy_now_message()
{
	if(!wooc_super_product_tabs_isProActive())
		echo '<p class="buynow">The Pro Version needs to be active for this to work. Click <a href="'.wooc_super_product_tabs_get_purchase_url().'" target="_blank" >here</a> to purchase.</p>';
}

function wooc_super_product_tabs_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
?>	
	<div>
	<div class="inside">	
<?php	
	echo '<h3>Super Product Tabs Global Settings</h3>';

	if(!wooc_super_product_tabs_isWooCommerceActive())
		echo '<p>WooCommerce needs to be active for this plugin to work.</p>';
	else 
	{
		//print_r($_POST);
		
		wooc_super_product_tabs_buy_now_message();
?>		
		<form method="post" action="options.php"> 
<?php		
		//These do nothing ...
		settings_fields( 'wooc-super-product-tabs-pro' );	
		do_settings_sections( 'wooc-super-product-tabs-pro' );
?>		
		
<?php		
		$tabs = array();	
		wooc_super_product_tabs_get_tabs_form($tabs,'global');		
		submit_button(); ?>
		</form>		
<?php } ?>
	</div>
	</div>
<?php	
}

function wooc_super_product_tabs_isWooCommerceActive()
{
	//Returns true if WooCommerce active, false otherwise
	return  in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

function wooc_super_product_tabs_isProActive()
{
	//Returns true if the pro version is active, false otherwise
	return in_array( 'super-product-tabs-for-woocommerce-pro/super-product-tabs-for-woocommerce-pro.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}
?>
