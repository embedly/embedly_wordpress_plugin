<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly
Description: The Embedly Plugin extends Wordpress's Oembed feature, allowing bloggers to Embed from 230+ services and counting.
Author: Embed.ly Inc
Version: 3.2.1
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


/**
 * Define Connstants
 */
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


/**
 * Embedly WP Class
 */
class WP_Embedly {

  public $embedly_options;  //embedly options array
  public $embedly_settings_page; //embedly settings page

  static $instance; //allows plugin to be called externally without re-constructing

  /**
   * Register hooks with WP Core
   */
  function __construct() {
    global $wpdb;
    self::$instance = $this;

    $this->embedly_options = array('table'    => $wpdb->prefix.'embedly_providers',
                             'active'   => true,
                              'key'      => '');



    //i18n
    add_action('init', array($this, 'i18n' ) );

    //Write default options to database
    add_option('embedly_settings', $this->embedly_options);

    //Update options from database
    $this->embedly_options = get_option('embedly_settings');

    //Activate/De-activate Embedly Hooks
    register_activation_hook(__FILE__, array($this, 'embedly_activate'));
    register_deactivation_hook(__FILE__, array($this, 'embedly_deactivate'));

    //Admin settings page actions
    add_action('admin_menu', array($this, 'embedly_add_settings_page'));
    add_action('admin_print_styles', array($this, 'embedly_enqueue_admin'));
    add_action('admin_print_styles', array($this, 'embedly_menu_icons'));
    add_action('wp_ajax_embedly_update_providers', array($this, 'embedly_ajax_update_providers'));
    add_action('admin_head', array($this, 'embedly_footer_widgets'));    
    add_action('plugins_loaded', array($this, 'add_embedly_providers'));

    //Post page actions
    add_action('wp_head', array($this, 'embedly_enqueue_public'));
    add_action('wp_head', array($this, 'embedly_platform_javascript'), 0);
    add_action('init', array($this, 'embedly_addbuttons'));

  }

  /**
   * Load plugin translation
   */
  function i18n() {
    load_plugin_textdomain('embedly', false, dirname(plugin_basename(__FILE__)).'/lang/');
  }


  /**
   * Generates queries to grab providers for Embedly Settings Page.
   *
   * @param class   $obj      Object retreived from the API
   * @param string  $action   The action to take (insert, update, get, or delete)
   * @param string  $name     Name of the item you wish to modify
   * @param boolean $selected Whether the service is selected (true or false)
   * @param string  $scope    Extra parameter so that get/update can use the same switch case (null or selected)
   * @param boolean $return   Whether to return results or simply run the query
   *
  **/
  function embedly_provider_queries($obj=null, $action=null, $name=null, $selected=false, $scope=null, $return=false) {
    global $wpdb;
    $action   = strtolower($action);
    $sel_val  = ($selected ? 1 : 0);

    switch($action) {
      case 'insert':
        $query  = "INSERT INTO "
          . $this->embedly_options['table']
          . " (name, selected, displayname, domain, type, favicon, regex, about) "
          . "VALUES ('"
          . $wpdb->_escape($obj->name)."',"
          . "true ,'"
          . $wpdb->_escape($obj->displayname)."','"
          . $wpdb->_escape($obj->domain)."','"
          . $wpdb->_escape($obj->type)."','"
          . $wpdb->_escape($obj->favicon)."','"
          . $wpdb->_escape(json_encode($obj->regex))."','"
          . $wpdb->_escape($obj->about)
          . "')";
      break;
      case 'update':
        if($scope == 'selected') {
          $query = "UPDATE ".$this->embedly_options['table']." SET selected=".$wpdb->_escape($sel_val)." WHERE name='".$wpdb->_escape($name)."'";
        }
        else {
          $query = "UPDATE ".$this->embedly_options['table']." SET "
            . "displayname='".$wpdb->_escape($obj->displayname)."', "
            . "domain='".$wpdb->_escape($obj->domain)."', "
            . "type='".$wpdb->_escape($obj->type)."', "
            . "favicon='".$wpdb->_escape($obj->favicon)."', "
            . "regex='".$wpdb->_escape(json_encode($obj->regex))."', "
            . "about='".$wpdb->_escape($obj->about)."' "
            . "WHERE name='".$wpdb->_escape($obj->name)."'";
        }
      break;
      case 'get':
        if($scope == 'selected') {
          $query = $wpdb->get_results("SELECT * FROM ".$this->embedly_options['table']." WHERE selected=true;");
        }
        else {
          $query = $wpdb->get_results("SELECT * FROM ".$this->embedly_options['table']." ORDER BY name;");
        }
      break;
      case 'delete':
        $query = "DELETE FROM ".$this->embedly_options['table']." WHERE name='".$name."';";
      break;
    }
    if(!$return) {
      $results = $wpdb->query($query);
    }
    else {
      return $query;
    }
  }


  /**
   * Activation Hook
  **/
  function embedly_activate() {
    global $wpdb;

    # Table doesn't exist, let's create it
    if($wpdb->get_var("SHOW TABLES LIKE '".$this->embedly_options['table']."'") != $this->embedly_options['table']) {
      $sql = "CREATE TABLE ".$this->embedly_options['table']." (
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
      require_once(ABSPATH.'wp-admin/includes/upgrade.php');
      dbDelta($sql);
    }

    # Table already exists, wipe it clean and start over
    else {
  		$sql     = "TRUNCATE TABLE ".$this->embedly_options['table'].";";
  		$results = $wpdb->query($sql);
    }

    # Grab new data
    $data     = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/wordpress'));
    $services = json_decode($data);
    foreach($services as $service) {
    	$this->embedly_provider_queries($service, 'insert');
    }
  }


  /**
   * Deactivation Hook
  **/
  function embedly_deactivate() {
    global $wpdb;
  	$sql     = $wpdb->prepare("TRUNCATE TABLE %s;", $this->embedly_options['table']);
    $results = $wpdb->query($sql);
  	delete_option('embedly_settings');
  }


  /**
   * Adds toplevel Embedly settings page
  **/
  function embedly_add_settings_page() {
    $this->embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', array($this, 'embedly_settings_page'));
  }


  /**
   * Enqueue styles/scripts for embedly page(s) only
  **/
  function embedly_enqueue_admin() {
    $screen = get_current_screen();
    if($screen->id == $this->embedly_settings_page) {
      $protocol = is_ssl() ? 'https' : 'http';
      wp_enqueue_style('embedly_admin_styles', EMBEDLY_URL.'/css/embedly-admin.css');
      wp_enqueue_style('google_fonts', $protocol.'://fonts.googleapis.com/css?family=Cabin:400,600');
      wp_enqueue_script('embedly_admin_scripts', EMBEDLY_URL.'/js/embedly.js',array('jquery'),'1.0',true);
    }
    return;
  }

  
  /**
   * Enqueue menu icon styles globally in admin
  **/
  function embedly_menu_icons() {
    wp_enqueue_style('embedly_menu_icons', EMBEDLY_URL.'/css/embedly-icons.css');
  }


  /**
   * Enqueue styles for front-end
  **/
  function embedly_enqueue_public() {
    wp_enqueue_style('embedly_font_end', EMBEDLY_URL.'/css/embedly-frontend.css');
  }


  /**
   * Enqueue platform.js to post for cards.
  **/
  function embedly_platform_javascript()
  {
    $protocol = is_ssl() ? 'https' : 'http';
    wp_enqueue_script('embedly-platform', $protocol.'://cdn.embedly.com/widgets/platform.js');
  }


  /**
   * The list of providers embedly offers is always growing.
   * This is a dynamic way to pull in new providers.
  **/
  function embedly_services_download() {
    $old_services = $this->embedly_provider_queries(null, 'get', null, false, null, true);
    $os_names = array();
    foreach($old_services as $os) {
    	array_push($os_names, $os->name);
    }
    $result   = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/wordpress'));
    $services = json_decode($result);
    if(!$services) {
      return null;
    }

    # Add new services
    $s_names = array();
    foreach($services as $service) {
      if(!in_array($service->name, $os_names)) {
        $this->embedly_provider_queries($service, 'insert');
      }
      else{
        # We need to update the provider if anything has changed.
        $this->embedly_provider_queries($service, 'update');
      }
      array_push($s_names, $service->name);
    }

    # See if any names dissappered
    foreach($os_names as $os_name) {
      if(!in_array($os_name, $s_names)) {
        $this->embedly_provider_queries($os_name, 'delete');
      }
    }

    return $this->embedly_provider_queries(null, 'get', null, false, null, true);
  }


  /**
   * Updates the selected services
  **/
  function update_embedly_service($selected) {
    $services = $this->embedly_provider_queries(null, 'get', null, false, null, true);
    foreach($services as $service) {
      if(in_array($service->name, $selected)) {
        if(!$service->selected) {
          $this->embedly_provider_queries(null, 'update', $service->name, true, 'selected', false);
          $service->selected = true;
        }
      }
      else {
        if($service->selected) {
          $this->embedly_provider_queries(null, 'update', $service->name, false, 'selected', false);
          $service->selected = false;
        }
      }
    }
    return $services;
  }


  /**
   * Does the work of adding the Embedly providers to wp_oembed
  **/
  function add_embedly_providers() {
    $selected_services = $this->embedly_provider_queries(null, 'get', null, false, 'selected', true);

    // remove default WP oembed providers
    add_filter('oembed_providers', create_function('', 'return array();'));

    if($selected_services && $this->embedly_options['active']) {
      foreach($selected_services as $service) {
        foreach(json_decode($service->regex) as $sre) {
          if(!empty($this->embedly_options['key'])) {
            wp_oembed_add_provider($sre, 'http://api.embed.ly/1/oembed?key='.$this->embedly_options['key'], true);
          }
          else {
            wp_oembed_add_provider($sre, 'http://api.embed.ly/1/oembed', true);
          }
        }
      }
    }

    // Since Embedly does not support Twitter, we have to add it back into the mix.
    wp_oembed_add_provider('#https?://(www\.)?twitter\.com/.+?/status(es)?/.*#i', 'https://api.twitter.com/1/statuses/oembed.{format}', true);
  }


  /**
   * Used for data validation upon form submission
  **/
  function embedly_update_selected_services($services) {
    $result = $this->update_embedly_service($services);
    if($result == null || !$result) {
      return false;
    }
    return true;
  }


  /**
   * Ajax function that looks at embedly for new providers
  **/
  function embedly_ajax_update_providers() {
    $services = $this->embedly_services_download();
    if($services == null) {
      echo json_encode(array('error'=>true));
    }
    else {
      echo json_encode($services);
    }
    die();
  }


  /**
   * Function to check if account has specific features enabled
  **/
  function embedly_acct_has_feature($feature, $key=false) {
    if($key) {
      $result = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/feature?feature='.$feature.'&key='.$key));
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

  /**
   * Trims $service->displayname to fit within the box
  **/
  function embedly_trim_title($title) {
    if(strlen($title) > 10) {
      $strcut   = substr($title, 0, 10);
      $strrev   = strrev($strcut);
      $lastchar = $strrev{0};
      if($lastchar == ' ') {
        echo substr($title, 0, 9).'..';
      }
      else {
        echo substr($title, 0, 10).'..';
      }
    }
    else {
      echo $title;
    }
  }

  /**
   * Add Embedly TinyMCE Widget functionality
  **/
  function embedly_footer_widgets() {
    $url = plugin_dir_url(__FILE__).'tinymce';
    echo '<script data-cfasync="false" type="text/javascript">EMBEDLY_TINYMCE = "'.$url.'";';
    echo 'embedly_key="'.$this->embedly_options['key'].'";';
    if($this->embedly_acct_has_feature('preview', $this->embedly_options['key'])) {
      echo 'embedly_endpoint="preview";';
    }
    else {
      echo 'embedly_endpoint="";';
    }
    echo '</script>';
  }


  function embedly_addbuttons() {
    if(!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
      return;
    }
    if(get_user_option('rich_editing') == 'true') {
      add_filter('mce_external_plugins', array($this, 'add_embedly_tinymce_plugin'));
      add_filter('mce_buttons', array($this, 'register_embedly_button'));
    }
  }


  function register_embedly_button($buttons) {
    array_push($buttons, "|", "embedly");
    return $buttons;
  }


  function add_embedly_tinymce_plugin($plugin_array) {
    $url = plugin_dir_url(__FILE__).'tinymce/editor_plugin.js';
    $plugin_array['embedly'] = $url;
    return $plugin_array;
  }


  function insert_embedly_dialog() { ?>

    <title>{#embedly_dlg.title}</title>
    <div id="embedly_error" style="display:none">
      <fieldset>
        <h2>{#embedly_dlg.no_key_title}</h2>
        <p>{#embedly_dlg.no_key_message} <a href="https://app.embed.ly/signup">{#embedly_dlg.signup_link}</a></p>
        <p>{#embedly_dlg.add_key_message}</p>
      </fieldset>
      <div class="embedly_button_row">
        <button onclick="javascript:EmbedlyDialog.cancel()">{#embedly_dlg.close_button}</button>
      </div>
    </div>
    <div id="embedly_main">
      <span style="display:none;" id="app_title">{#embedly_dlg.title}</span>
      <form action="." method="get" class="embedly_form">
              <fieldset>
                <div>
                  <input type="url" class="embedly_field" id="embedly_url_field" placeholder="{#embedly_dlg.url_field}" name="embedly_url" />
                </div>
                <div class="third-mini last">
                  <button id="embedly_form_lookup" class="primary">{#embedly_dlg.show_button}</button>
                </div>
              </fieldset>
              <fieldset>
                <legend>{#embedly_dlg.hover_title}</legend>
                <div id="embedly-preview-results">
                  <div class="generator-card">
                    <span></span>
                    <div id="card"></div>
                    <textarea style="display:none;" id="snippet" readonly></textarea> 
                  </div>              
                </div>
              </fieldset>
      </form>
      <fieldset>
        <legend>{#embedly_dlg.options_title}</legend>
        <div class="third first">
        <div class="generator-opts">
          <label id="chromeless">
          <input class="embedly_field" id="card-chromeless" type="checkbox"/>
          <span>{#embedly_dlg.border_field}</span>
          </label>
        </div>
        <div class="generator-opts">
          <label id="background">
          <input class="embedly_field" id="card-background" type="checkbox"/>
          <span>{#embedly_dlg.background_field}</span>
          </label>
        </div>
        </div>
        <div class="embedly_button_row">
         <button id="embedly_form_submit" class="primary disabled" disabled="disabled" onclick="javascript:EmbedlyDialog.insert()">{#embedly_dlg.post_button}</button><button onclick="javascript:EmbedlyDialog.cancel()">{#embedly_dlg.cancel_button}</button>
        </div>
      </fieldset>
    </div>
    <?php
  }


  /**
   * The Admin Page.
  **/
  function embedly_settings_page() {
    global $wpdb;
    $services = $this->embedly_provider_queries(null, 'get', null, false, null, true);
    $selServs = array();
    $cnt      = 0;

    #Begin processing form data
    #empty key set when saving
    if(isset($_POST['embedly_key']) && (empty($_POST['embedly_key']) || $_POST['embedly_key'] == __('Please enter your key...', 'embedly'))) {
      $this->embedly_options['key'] = '';
      update_option('embedly_settings', $this->embedly_options);
      $successMessage = __("You didn't enter a key to validate, so for now you only have basic capabilities.", 'embedly');
    }
    #user inputted key when saving
    elseif(isset($_POST['embedly_key']) && !empty($_POST['embedly_key'])) {
      #user key is valid
      $key = trim($_POST['embedly_key']);
      if($this->embedly_acct_has_feature('oembed', $key)) {
        $this->embedly_options['key'] = $key;
        update_option('embedly_settings', $this->embedly_options);
        $this->embedly_options = get_option('embedly_settings');
        $successMessage  = __('Your API key is now tucked away for safe keeping.', 'embedly');
        $keyValid = true;
      }
      #user key is invalid
      else {
        $keyValid = false;
        $errorMessage = __('You have entered an invalid API key. Please try again.', 'embedly');
      }
    }
    #key is already saved
    elseif(!isset($_POST['embedly_key']) && isset($this->embedly_options['key']) && !empty($this->embedly_options['key'])) {
      $keyValid = true;
    }
    #key was set in older version, needs to be resaved.
    elseif(get_option('embedly_key') && (!isset($this->embedly_options['key']) || empty($this->embedly_options['key']))) {
      #Backwards compatible
      $this->embedly_options['key'] = get_option('embedly_key');
      update_option('embedly_settings', $this->embedly_options);
      $this->embedly_options = get_option('embedly_settings');
      delete_option('embedly_key');
      $keyValid = true;
    }

    #no services available
    if($services == null) {
      $errorMessage = __('Hmmm, there were no providers found. Try updating?', 'embedly');
    }
    #saving providers selected
    elseif(isset($_POST['updating_providers'])) {
      foreach($services as $service) {
        if(isset($_POST[$service->name])) {
          $selServs[] .= $service->name;
        }
      }
      # user selected services
      if(isset($selServs)) {
        #saved selected services
        if($this->embedly_update_selected_services($selServs)) {
          $successMessage = sprintf(__('The providers you chose have been saved to the database. %1$sPlease reload%2$s to reflect the changes.', 'embedly'), '<a href="admin.php?page=embedly">', '</a>');
        }
        #failed saving services
        else {
          $errorMessage = __("It would appear that we've encountered a problem while updating your providers. Try again?", 'embedly');
        }
      }
    }
    ?>
    <div class="embedly-wrap">
      <div class="embedly-ui">
        <div class="embedly-ui-header-outer-wrapper">
          <div class="embedly-ui-header-wrapper">
            <div class="embedly-ui-header">
              <a class="embedly-ui-logo" href="http://embed.ly" target="_blank"><?php _e('Embedly', 'embedly'); ?></a>
            </div>
          </div>
        </div>
    <?php if(isset($errorMessage)) { ?>
        <div class="embedly-error embedly-message" id="embedly-error">
          <p><strong><?php echo $errorMessage; ?></strong></p>
        </div>
    <?php } elseif(isset($successMessage) && !isset($errorMessage)) { ?>
        <div class="embedly-updated embedly-message" id="embedly-success">
          <p><strong><?php echo $successMessage; ?></strong></p>
        </div>
    <?php } ?>
        <div class="embedly-error embedly-ajax-message embedly-message" id="embedly-ajax-error">
          <p><strong><?php _e('Something went wrong. Please try again later.', 'embedly'); ?></strong></p>
        </div>
        <div class="embedly-updated embedly-ajax-message embedly-message" id="embedly-ajax-success">
          <p><strong><?php _e("We have sync'd your providers list with our API. Enjoy!", 'embedly'); ?></strong></p>
        </div>
    <?php if($services != null) { ?>
        <form id="embedly_key_form" method="POST" action="">
          <div class="embedly-ui-key-wrap">
            <div class="embedly_key_form embedly-ui-key-form">
              <fieldset>
                <h2 class="section-label"><?php _e('Embedly Key', 'embedly'); ?></h2><span><a href="http://app.embed.ly" target="_new"><?php _e("Lost your key?", 'embedly'); ?></a></span>
                <div class="embedly-input-wrapper">
                  <a href="#" class="embedly-lock-control embedly-unlocked" data-unlocked="<?php _e('Lock this field to prevent editing.', 'embedly'); ?>" data-locked="<?php _e('Unlock to edit this field.', 'embedly'); ?>" title=""><?php if(isset($keyValid) && $keyValid){_e('Unlock to edit this field.', 'embedly');}else{_e('Lock this field to prevent editing.', 'embedly');} ?></a>
                  <input <?php if(isset($keyValid) && $keyValid){echo 'readonly="readonly" ';} ?>id="embedly_key" placeholder="<?php _e('Please enter your key...', 'embedly'); ?>" name="embedly_key" type="text" class="<?php if(isset($keyValid) && !$keyValid){echo 'invalid embedly-unlocked-input ';}elseif(!isset($keyValid)){echo 'embedly-unlocked-input ';}else{echo 'embedly-locked-input ';} ?>embedly_key_input" <?php if(!empty($this->embedly_options['key'])){echo 'value="'.$this->embedly_options['key'].'"';} ?> />
                  <input class="button-primary embedly_submit embedly_top_submit" name="submit" type="submit" value="<?php _e('Save Key', 'embedly'); ?>"/>
                </div>
                <p><?php _e('Add your Embedly Key to embed any URL', 'embedly'); ?></p>
              </fieldset>
            </div>
          </div>
        </form>
        <form id="embedly_providers_form" method="POST" action="">
          <div class="pixel-popper"></div>
          <div class="embedly-ui-service-sorter-wrapper">
            <div class="embedly-ui-quicksand-wrapper quicksand-left-wrapper">
              <div class="embedly-ui-quicksand">
                <p><?php _e('Select', 'embedly'); ?></p>
                <ul class="embedly-actions embedly-action-select" id="embedly-service-select">
                  <li><a class="all active" href="#"><?php _e('All', 'embedly'); ?></a></li>
                  <li><a class="clearselection" href="#"><?php _e('None', 'embedly'); ?></a></li>
                  <li><a class="videos" href="#"><?php _e('Videos', 'embedly'); ?></a></li>
                  <li><a class="audio" href="#"><?php _e('Audio', 'embedly'); ?></a></li>
                  <li><a class="photos" href="#"><?php _e('Photos', 'embedly'); ?></a></li>
                  <li><a class="rich" href="#"><?php _e('Rich Media', 'embedly'); ?></a></li>
                  <li><a class="products" href="#"><?php _e('Products', 'embedly'); ?></a></li>
                </ul>
              </div>
            </div>
            <div class="embedly-ui-quicksand-wrapper quicksand-right-wrapper">
              <div class="embedly-ui-quicksand">
                <p><?php _e('Filter', 'embedly'); ?></p>
                <ul class="embedly-actions embedly-action-filter" id="embedly-service-filter">
                  <li data-value="all"><a class="all active" href="#"><?php _e('All', 'embedly'); ?></a></li>
                  <li data-value="video"><a class="videos" href="#"><?php _e('Videos', 'embedly'); ?></a></li>
                  <li data-value="audio"><a class="audio" href="#"><?php _e('Audio', 'embedly'); ?></a></li>
                  <li data-value="photo"><a class="photos" href="#"><?php _e('Photos', 'embedly'); ?></a></li>
                  <li data-value="rich"><a class="rich" href="#"><?php _e('Rich Media', 'embedly'); ?></a></li>
                  <li data-value="product"><a class="products" href="#"><?php _e('Products', 'embedly'); ?></a></li>
                </ul>
              </div>
            </div>
            <div class="clear"></div>
            <ul id="services-source" class="embedly-service-generator">
    <?php foreach($services as $service) { $cnt++; ?>
              <li class="<?php echo $service->type; ?>" id="<?php echo $service->name; ?>" data-type="<?php echo $service->type; ?>" data-id="id-<?php echo $cnt; ?>">
                <div class="full-service-wrapper">
                  <label for="<?php echo $service->name; ?>-checkbox" class="embedly-icon-name"><?php $this->embedly_trim_title($service->displayname); ?></label>
                  <div class="embedly-icon-wrapper">
                    <input type="checkbox" id="<?php echo $service->name; ?>-checkbox" name="<?php echo $service->name; ?>"<?php if($service->selected == 1) { echo " checked=checked"; } ?>><img src="<?php echo $service->favicon; ?>" title="<?php echo $service->name; ?>" alt="<?php echo $service->displayname; ?>">
                  </div>
                </div>
              </li>
    <?php } ?>
            </ul>
            <div class="clear"></div>
            <input type="hidden" name="updating_providers" value="1" />
            <input class="button-primary embedly_submit embedly_bottom_submit" name="submit" type="submit" value="<?php _e('Save Changes', 'embedly'); ?>"/>
          </form>
    <?php } ?>
          <form id="embedly_update_providers_form"  method="POST" action="." >
            <input class="button-secondary embedly_submit embedly_bottom_secondary" type="submit" name="submit" value="<?php _e('Update Provider List', 'embedly'); ?>"/>
          </form>
        </div>
      </div>
    </div>
    <?php
  }
}


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();