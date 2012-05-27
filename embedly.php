<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly
Description: The Embedly Plugin extends Wordpress's Embeds allowing bloggers to Embed from 218 services and counting.
Author: Embed.ly Inc
Version: 2.0.9
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


# Prevent direct access
if(!function_exists('add_action')) {
	echo 'You sneaky devil you...';
	exit;
}

# Load/Define global vars
else {
  global $wpdb, $embedly_options;
}

# Create plugin text domain
load_plugin_textdomain('embedly', false, dirname(plugin_basename(__FILE__)).'/lang/');

# Define the constants if needed
if(!defined('WP_CONTENT_URL')) {
  define('WP_CONTENT_URL', WP_SITEURL.'/wp-content');
}
if(!defined('WP_CONTENT_DIR')) {
  define('WP_CONTENT_DIR', ABSPATH.'wp-content');
}
if(!defined('WP_PLUGIN_URL')) {
  define('WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');
}
if(!defined('WP_PLUGIN_DIR')) {
  define('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins');
}
if(!defined('EMBEDLY_DIR')) {
  define('EMBEDLY_DIR', WP_PLUGIN_DIR.'/embedly');
}
if(!defined('EMBEDLY_URL')) {
  define('EMBEDLY_URL', WP_PLUGIN_URL.'/embedly');
}

# Add JSON support for older PHP versions
if(!function_exists('json_decode')) {
  function json_decode($content, $assoc=false) {
		require_once('inc/JSON.php');
		if($assoc) {
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		} 
    else {
			$json = new Services_JSON;
		}
    return $json->decode($content);
	}
}
if(!function_exists('json_encode')) {
  function json_encode($content) {
    require_once('inc/JSON.php');
    $json = new Services_JSON;
    return $json->encode($content);
  }
}


# Create array of default options
$embedly_options = array(
  'table'    => $wpdb->prefix.'embedly_providers',
  'active'   => true,
  'key'      => ''
);

# Write default options to database
add_option('embedly_settings', $embedly_options);
  
# Update options from database
$embedly_options = get_option('embedly_settings');


/* DB CRUD Methods
 */
function insert_provider($obj){
  global $wpdb, $embedly_options;
  $insert = "INSERT INTO " . $embedly_options['table'] .
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
  global $wpdb, $embedly_options;
  $update = "UPDATE " . $embedly_options['table'] . " ".
            "SET displayname='". $wpdb->escape($obj->displayname) . "', ".
            "domain='". $wpdb->escape($obj->domain) . "', ".
            "type='". $wpdb->escape($obj->type) . "', ".
            "favicon='". $wpdb->escape($obj->favicon) . "', ".
            "regex='". $wpdb->escape(json_encode($obj->regex)) . "', ".
            "about='". $wpdb->escape($obj->about) . "' ".
            "WHERE name='".$wpdb->escape($obj->name)."'";
  $results = $wpdb->query( $update );
}

function update_provider_selected($name, $selected) {
  global $wpdb, $embedly_options;
  if ($selected){
  	$selected = 1;
  } else {
  	$selected = 0;
  }
  $update = "UPDATE " . $embedly_options['table'] . " ".
            "SET selected=". $wpdb->escape($selected) . " ".
            "WHERE name='".$wpdb->escape($name)."'";
  $results = $wpdb->query( $update );
}

function delete_provider($name){
  global $wpdb, $embedly_options;
  $delete = "DELETE FROM ".$embedly_options['table']." WHERE name='".$name."';";
  $results = $wpdb->query($delete);
}

function get_embedly_services(){
  global $wpdb, $embedly_options;
  $results = $wpdb->get_results( "SELECT * FROM ".$embedly_options['table'].";");
  return $results;
}

function get_embedly_selected_services(){
  global $wpdb, $embedly_options;
  $results = $wpdb->get_results( "SELECT * FROM ".$embedly_options['table']." WHERE selected=true;");
  return $results;
}

/**
 * Activation Hooks
 */
function embedly_activate() {
  global $wpdb, $embedly_options;
 //Table doesn't exist
  if($wpdb->get_var("SHOW TABLES LIKE '".$embedly_options['table']."'") != $embedly_options['table']) {
    $sql = "CREATE TABLE " . $embedly_options['table'] . " (
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
  }
  else {
    //Clean Slate
		$sql = "TRUNCATE TABLE ".$embedly_options['table'].";";
		$results = $wpdb->query($sql);
  }
  $result = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/wordpress'));
  $services = json_decode($result);
  foreach($services as $service){
  	insert_provider($service);
  }

}
register_activation_hook( __FILE__, 'embedly_activate' );

function embedly_deactivate(){
  global $wpdb, $embedly_options;
	$sql = $wpdb->prepare("TRUNCATE TABLE ".$embedly_options['table'].";");
  $results = $wpdb->query($sql);
  delete_option('embedly_settings');
}
register_deactivation_hook( __FILE__, 'embedly_deactivate' );


/**
 * Adds toplevel Embedly settings page
 */
function embedly_add_settings_page() {
  global $embedly_settings_page;
  $embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', 'embedly_provider_options');
}
add_action('admin_menu', 'embedly_add_settings_page');




/**
 * Define plugin menu icons
 */
function embedly_menu_icons() {
  ob_start();
?>
<style type="text/css" media="screen">
  #toplevel_page_embedly .wp-menu-image a img {
    display:none;
  }
  #toplevel_page_embedly .wp-menu-image a {
    background: url('<?php echo EMBEDLY_URL; ?>/img/menu-icon.png') no-repeat 5px 7px !important;
  }
  #toplevel_page_embedly:hover .wp-menu-image a, #toplevel_page_embedly.current .wp-menu-image a {
    background-position:5px -25px !important;
  }
</style>
<?php 
  echo ob_get_clean();
}
add_action('admin_head', 'embedly_menu_icons');





/**
* Add the CSS and JavaScript includes to the admin head of our plugin page only
*/
function embedly_admin_head() {
  $url = plugin_dir_url ( __FILE__ );
  echo "<link rel='stylesheet' type='text/css' href='{$url}css/embedly.css' />\n";
  echo "<script src='{$url}js/embedly.js' type='text/javascript' ></script>";
}
add_action( 'admin_head-toplevel_page_embedly', 'embedly_admin_head' );


/**
* Add CSS to front end for handling Embedly Embeds
*/
function embedly_head(){
  $url = plugin_dir_url ( __FILE__ );
  echo "<link rel='stylesheet' type='text/css' href='{$url}css/embedly.css' />\n";
}
add_action('wp_head', 'embedly_head');

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

  $result = wp_remote_retrieve_body( wp_remote_get('http://api.embed.ly/1/wordpress'));
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
    }
    else{
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
function add_embedly_providers() {
  global $embedly_options;
  $services = get_embedly_selected_services();

  // remove default WP oembed providers
  add_filter('oembed_providers', create_function( '', 'return array();' ));

  if($services && $embedly_options['active']) {
    foreach($services as $service) {
      foreach(json_decode($service->regex) as $sre) {
        if(!empty($embedly_options['key'])) {
          wp_oembed_add_provider($sre, 'http://api.embed.ly/1/oembed?key='.$embedly_options['key'], true );
        }
        else {
          wp_oembed_add_provider($sre, 'http://api.embed.ly/1/oembed', true );
        }
      }
    }
  }
}
//add all the providers on the "plugins_loaded" action.
add_action( 'plugins_loaded', 'add_embedly_providers' );

/**
 * Ajax function that updates the selected state of providers
 */
function embedly_ajax_update(){
  $providers = $_POST['providers'];
  $embedly_options['key'] = $_POST['embedly_key'];
  update_option('embedly_settings', $embedly_options);
  $services = explode(',', $providers);
  $result = update_embedly_service($services);
  if($result == null) {
    echo json_encode(array('error'=>true));
  }
  else {
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
  if($services == null) {
    echo json_encode(array('error'=>true));
  }
  else {
    echo json_encode($services);
  }
  die();
}
add_action('wp_ajax_embedly_update_providers', 'embedly_ajax_update_providers');

function embedly_acct_has_feature($feature) {
  global $embedly_options;
  if(!empty($embedly_options['key'])) {
    $result = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/feature?feature='.$feature.'&key='.$embedly_options['key']));
  }
  else {
    return false;
  }

  $feature_status = json_decode($result);
  if($feature_status) {
    return $feature_status->$feature;
  }
  else {
    return false;
  }
}

// Add TinyMCE Functionality
function embedly_footer_widgets() {
  global $embedly_options;
  echo '<script type="text/javascript">EMBEDLY_TINYMCE = "'.EMBEDLY_URL.'/tinymce";';
  echo 'embedly_key = "'.$embedly_options['key'].'";';
  if(embedly_acct_has_feature('preview')) {
    echo 'embedly_endpoint = "preview";';
  }
  else {
    echo 'embedly_endpoint = "";';
  }
  echo '</script>';
}
function embedly_addbuttons(){
  if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
    return;
  }
  if (get_user_option('rich_editing') == 'true') {
    add_filter('mce_external_plugins', 'add_embedly_tinymce_plugin');
    add_filter('mce_buttons', 'register_embedly_button');
  }
}

function register_embedly_button($buttons){
  array_push($buttons, "|", "embedly");
  return $buttons;
}

function add_embedly_tinymce_plugin($plugin_array) {
  $plugin_array['embedly'] = EMBEDLY_URL.'/tinymce/editor_plugin.js';
  return $plugin_array;
}

add_action('admin_head', 'embedly_footer_widgets');
add_action('init', 'embedly_addbuttons');

/**
 * The Admin Page.
 */
function embedly_provider_options() {
  global $embedly_options;
  $services = get_embedly_services();
?>
<div class="wrap">
  <div class="icon32" id="embedly-logo"><br></div>
  <h2><?php _e('Embedly', 'embedly'); ?></h2>
<?php if($services == null) { ?>
  <div id="message" class="error">
    <p><strong><?php _e('Hmmmm, there where no providers found. Try updating?', 'embedly'); ?></strong></p>
  </div>
<?php } else { ?>
  <form id="embedly_providers_form" method="POST" action=".">
    <div class="embedly_key_form">
      <fieldset>
        <label for='embedly_key'><?php _e('Embedly Key', 'embedly'); ?></label>
        <input id="embedly_key" placeholder="<?php _e('enter your key...', 'embedly'); ?>" name="embedly_key" type="text" style="width:75%;" <?php if(!empty($embedly_options['key'])) { echo 'value="'.$embedly_options['key'].'"'; } ?> />
        <span><a href="http://embed.ly/pricing" target="_new"><?php _e("Don't have a key?", 'embedly'); ?></a></span>
        <p><?php _e('Add your Embedly Key to embed any URL', 'embedly'); ?></p>
        <input class="button-primary embedly_submit" name="submit" type="submit" value="<?php _e('Save', 'embedly'); ?>"/>
      </fieldset>
    </div>
    <div style="clear:both;"></div>
    <hr>
    <h2 class="providers"><?php _e('Providers', 'embedly'); ?></h2>
    <p><?php printf(__('The %1$sEmbedly%2$s plugin allows you to embed content from the following services using the %1$sEmbedly API%2$s. Select the services you wish to embed in your blog.', 'embedly'), '<a href="http://embed.ly" target="_blank">', '</a>'); ?></p>
    <ul class="actions">
      <li><a class="all" href="#"><?php _e('All', 'embedly'); ?></a></li>
      <li><a class="clearselection" href="#"><?php _e('Clear', 'embedly'); ?></a></li>
      <li><a class="videos" href="#"><?php _e('Videos', 'embedly'); ?></a></li>
      <li><a class="audio" href="#"><?php _e('Audio', 'embedly'); ?></a></li>
      <li><a class="photos" href="#"><?php _e('Photos', 'embedly'); ?></a></li>
      <li><a class="products" href="#"><?php _e('Products', 'embedly'); ?></a></li>
    </ul>
    <div style="clear:both;"></div>
    <ul class="generator">
<?php foreach($services as $service) { ?>
      <li class="<?php echo $service->type?>" id="<?php echo $service->name ?>">
        <div class="full_service_wrap">
          <div class="service_icon_wrap">
            <input type="checkbox" name="<?php echo $service->name ?>"<?php if($service->selected == 1) { echo " checked=checked"; } ?>>
            <a href="#<?php echo $service->name ?>" class="info ">
              <img src="<?php echo $service->favicon; ?>" title="<?php echo $service->name; ?>" alt="<?php echo $service->displayname; ?>"><?php echo $service->displayname; ?>
            </a>
          </div>
        </div>
      </li>
<?php } ?>
    </ul>
    <div style="clear:both;"></div>
    <input class="button-primary embedly_submit" name="submit" type="submit" value="<?php _e('Save Changes', 'embedly'); ?>"/>
  </form>
<?php } ?>
  <form id="embedly_update_providers_form"  method="POST" action="." >
    <input class="button-secondary embedly_submit" type="submit" name="submit" value="<?php _e('Update Provider List', 'embedly'); ?>"/>
  </form>
</div>
<?php } ?>