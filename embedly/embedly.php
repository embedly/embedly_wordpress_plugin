<?php
/*
Plugin Name: Embedly
Plugin URI: http://api.embed.ly
Description: The Embedly Plugin extends Wordpress's Embeds allowing bloggers to Embed from 73 services and counting.
Author: Embed.ly Inc
Version: 1.4
Author URI: http://embed.ly
License: GPL2

Copyright 2010  Embedly  (email : developer@embed.ly)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'embedly.php')) {
	define('EMBEDLY_FILE', trailingslashit(ABSPATH.PLUGINDIR).'embedly.php');
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'embedly/embedly.php')) {
	define('EMBEDLY_FILE', trailingslashit(ABSPATH.PLUGINDIR).'embedly/embedly.php');
}

/* DB CRUD Methods
 */
function insert_provider($obj){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
  $insert = "INSERT INTO " . $table_name .
            " (name, selected, displayname, domain, type, favicon, regex, about) " .
            "VALUES ('" . $wpdb->escape($obj->name) . "'," 
            . "true ,'" 
            . $wpdb->escape($obj->displayname) . "','" 
            . $wpdb->escape($obj->domain) . "','"
            . $wpdb->escape($obj->type) . "','"
            . $wpdb->escape($obj->favicon) . "','"
            . $wpdb->escape(json_encode($obj->regex)) . "','"
            . $wpdb->escape($obj->about) . "')";
  $results = $wpdb->query( $insert );  
}

function update_provider($obj){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
  $update = "UPDATE " . $table_name . " ".
            "SET displayname='". $wpdb->escape($obj->displayname) . "', ".
            "domain='". $wpdb->escape($obj->domain) . "', ".
            "type='". $wpdb->escape($obj->type) . "', ".
            "favicon='". $wpdb->escape($obj->favicon) . "', ".
            "regex='". $wpdb->escape(json_encode($obj->regex)) . "', ".
            "about='". $wpdb->escape($obj->about) . "' ".
            "WHERE name='".$wpdb->escape($obj->name)."'";
  $results = $wpdb->query( $update );
}

function update_provider_selected($name, $selected){
  global $wpdb;
  if ($selected){
  	$selected = 1;
  } else {
  	$selected = 0;
  }
  $table_name = $wpdb->prefix . "embedly_providers";
  $update = "UPDATE " . $table_name . " ".
            "SET selected=". $wpdb->escape($selected) . " ".
            "WHERE name='".$wpdb->escape($name)."'";
  $results = $wpdb->query( $update );
}

function delete_provider($name){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
  $delete = "DELETE FROM ".$table_name." WHERE name='".$name."';";
  $results = $wpdb->query($delete);
}

function get_embedly_services(){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
  $results = $wpdb->get_results( "SELECT * FROM ".$table_name.";");
  return $results;
}

function get_embedly_selected_services(){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
  $results = $wpdb->get_results( "SELECT * FROM ".$table_name." WHERE selected=true;");
  return $results;
}

/**
 * Activation Hooks
 */
function embedly_Activate(){
  global $wpdb;
	$table_name = $wpdb->prefix . "embedly_providers";
	add_option('embedly_active', true);	
 //Table doesn't exist
  if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    $sql = "CREATE TABLE " . $table_name . " (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            name TINYTEXT NOT NULL, 
            selected TINYINT NOT NULL DEFAULT 1,
            displayname TINYTEXT NOT NULL,
            domain TINYTEXT NULL,
            type TINYTEXT NOT NULL,
            favicon TINYTEXT NOT NULL,
            regex TEXT NOT NULL,
            about TEXT NULL,
            UNIQUE KEY id (id)
     );";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  } else {
    //Clean Slate
		$sql = "TRUNCATE TABLE ".$table_name.";";
		$results = $wpdb->query($sql);
  }
  $result = wp_remote_retrieve_body( wp_remote_get('http://api.embed.ly/v1/api/wordpress'));
  $services = json_decode($result);
  foreach($services as $service){
  	insert_provider($service);
  }
}
register_activation_hook( EMBEDLY_FILE, 'embedly_Activate' );

function embedly_deactivate(){
  global $wpdb;
  $table_name = $wpdb->prefix . "embedly_providers";
	$sql = $wpdb->prepare("TRUNCATE TABLE ".$table_name.";");
  $results = $wpdb->query($sql);
	delete_option('embedly_active');
}
register_deactivation_hook( EMBEDLY_FILE, 'embedly_deactivate' );


/**
* Adds Embedly to the settings menu
*/
if ( is_admin() ){
  add_action('admin_menu', 'embedly_admin_menu');
  function embedly_admin_menu() {
    add_menu_page('Embedly', 'Embedly', 'administrator',
                      'embedly', 'embedly_provider_options');
  }
}

/**
* Add the CSS and JavaScript includes to the head element
*/
function admin_register_head() {
  $siteurl = get_option('siteurl');
  $url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__));
  echo "<link rel='stylesheet' type='text/css' href='$url/css/embedly.css' />\n";
  echo "<script src='$url/js/embedly.js' type='text/javascript' ></script>";
}
add_action('admin_head', 'admin_register_head');

/**
* The list of providers embedly offers is always growing. This is a dynamic way to
* pull in new providers.
*/
function embedly_services_download(){  
  $old_services = get_embedly_services();
  $os_names = array();
  foreach ($old_services as $os){
  	array_push($os_names, $os->name);
  }

  $result = wp_remote_retrieve_body( wp_remote_get('http://api.embed.ly/v1/api/wordpress'));
  $services = json_decode($result);
  if (!$services){
    return null;
  }
  //add new services
  $s_names = array();
  foreach($services as $service){
    if (!in_array($service->name, $os_names)){
      insert_provider($service);
    } else{
      //We need to update the provider if anything has change.
    	update_provider($service);
    }
    array_push($s_names, $service->name);
  }

  //See if any names dissappered
  foreach($os_names as $os_name){
  	if (!in_array($os_name, $s_names)){
      delete_provider($os_name);   
    }
  }

  return get_embedly_services();
}

/**
 * Updates the selected services
 */
function update_embedly_service($selected){
  $services = get_embedly_services();
  foreach($services as $service) {
    if(in_array($service->name, $selected) ){
      if (!$service->selected){  
        update_provider_selected($service->name, true);
        $service->selected = true;
      }
    } else{
      if ($service->selected){  
        update_provider_selected($service->name, false);
        $service->selected = false;
      }
    }
  }
  return $services;
}

/**
 * Does the work of adding the Embedly providers to wp_oembed
 */
function add_embedly_providers($the_content){
  $services = get_embedly_selected_services();
	require_once( ABSPATH . WPINC . '/class-oembed.php' );
	$oembed = _wp_oembed_get_object();
	$oembed->providers = array(); 
  if ($services && get_option('embedly_active')) {
    foreach($services as $service) {
      foreach(json_decode($service->regex) as $sre) {
        wp_oembed_add_provider($sre, 'http://api.embed.ly/v1/api/oembed', true );
      }
    }
  }	
}
//add all the providers on init.
add_action('init', 'add_embedly_providers');

/**
 * Ajax function that updates the selected state of providers
 */
function embedly_ajax_update(){
  $providers = $_POST['providers'];
  $services = explode(',', $providers);
  $result = update_embedly_service($services);
  if($result == null){
    echo json_encode(array('error'=>true));
  } else {
    echo json_encode(array('error'=>false));
  }
  die();
}
add_action('wp_ajax_embedly_update', 'embedly_ajax_update');

/**
 * Ajax function that looks at embedly for new providers
 */
function embedly_ajax_update_providers(){
  $services = embedly_services_download();
  if ($services == null){
    echo json_encode(array('error'=>true));
  } else {
    echo json_encode($services);
  }
  die();
}
add_action('wp_ajax_embedly_update_providers', 'embedly_ajax_update_providers');


// Add TinyMCE Functionality
function embedly_footer_widgets(){
  $url = get_bloginfo('url').'/wp-content/plugins/embedly/tinymce';
  echo '<script type="text/javascript">EMBEDLY_TINYMCE = "'.$url.'";';
  echo 'embedly_key = "internal";';
  echo 'embedly_endpoint = "oembed";';
  echo '</script>';
}
function embedly_addbuttons(){
  if (! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
    return;
  
  if (get_user_option('rich_editing') == 'true'){
    add_filter('mce_external_plugins', 'add_embedly_tinymce_plugin');
    add_filter('mce_buttons', 'register_embedly_button');
  }
}

function register_embedly_button($buttons){
  array_push($buttons, "|", "embedly");
  return $buttons;
}

function add_embedly_tinymce_plugin($plugin_array){
  $url = get_bloginfo('url');
  $url.= "/wp-content/plugins/embedly/tinymce/editor_plugin.js";
  $plugin_array['embedly'] = $url;
  return $plugin_array;
}

add_action('admin_head', 'embedly_footer_widgets');
add_action('init', 'embedly_addbuttons');

function embedly_change_mce_options($init){
  $ext = 'div[id|class|data-mce-style|style|data-ajax]';
  $ext.= ',p[id|class|style]';
  $ext.= ',img[id|class|style|data-mce-style|data-ajax]';
  
  if ( isset ($init['extended_valid_elements'] ) ) {
    $init['extended_valid_elements'] .= ',' . $ext;
  } else {
    $init['extended_valid_elements'] = $ext;
  }
  
  return $init;
}

add_filter('tiny_mce_before_init', 'embedly_change_mce_options');

/**
 * The Admin Page.
 */
function embedly_provider_options(){
  $services = get_embedly_services();
?>
<div class="wrap">
<div class="icon32" id="embedly-logo"><br></div>
<h2>Embedly</h2>
<?php  if ($services == null) {?>
<div id="message" class="error">
<p><strong>Hmmmm, there where no providers found. Try updating?</strong></p>
</div>
<?php } else { ?>
<p>
The <a href="http://embed.ly" >Embedly</a> plugin allows you to embed content
from the following services using the Embedly <a href="http://api.embed.ly" >oEmbed API</a>. Select the services
you wish to embed in your blog.
</p>
<form id="embedly_providers_form" method="POST" action=".">
<ul class="actions">
  <li><a class="all" href="#">All</a></li>
  <li><a class="clearselection" href="#">Clear</a></li>
  <li><a class="videos" href="#">Videos</a></li>
  <li><a class="audio" href="#">Audio</a></li>
  <li><a class="photos" href="#">Photos</a></li>
  <li><a class="products" href="#">Products</a></li>
</ul>
<div style="clear:both;"></div>
<ul class="generator">
<?php  foreach($services as $service) { ?>
<li class="<?php echo $service->type?>" id="<?php echo $service->name ?>">
<input type="checkbox" name="<?php echo $service->name ?>" <?php if($service->selected == 1){ echo "checked=checked"; }?>>
<a href="#<?php echo $service->name ?>" class="info ">
<img src="<?php echo $service->favicon; ?>" title="<?php echo $service->name; ?>" alt="<?php echo $service->displayname; ?>"><?php echo $service->displayname; ?></a></li>
<?php }?>
</ul>
<div style="clear:both;"></div>
<input class="button-primary embedly_submit" name="submit" type="submit" value="Save"/>
</form>
<?php } ?>
<form id="embedly_update_providers_form"  method="POST" action="." >
<input class="button-secondary embedly_submit" type="submit" name="submit" value="Update Provider List"/>
</form>
</div>
<?php } ?>
