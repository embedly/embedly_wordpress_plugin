<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly/wordpress
Description: The Embedly Plugin extends Wordpress's automatic embed feature, allowing bloggers to Embed from 500+ services and counting.
Author: Embed.ly Inc
Version: 4.8.2
Author URI: http://embed.ly
License: GPL2

Copyright 2015 Embedly  (email : developer@embed.ly)

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
 * Define Constants
 */
if (!defined('EMBEDLY_URL')) {
    define('EMBEDLY_URL', plugins_url('/embedly'));
}
if (!defined('EMBEDLY_BASE_URI')) {
    define('EMBEDLY_BASE_URI', 'https://api.embedly.com/2/card');

}
if (!defined('EMBEDLY_WP_BASE_KEY')) {
    define('EMBEDLY_WP_BASE_KEY', 'cedd0120dd674379ab8c9689f2cfe588');
}

// maps local settings key => api param name
$settings_map = array(
    'card_controls' => 'cards_controls',
    'card_theme' => 'cards_theme',
    'card_width' => 'cards_width',
    'card_align' => 'cards_align',
);

/**
 * Embedly WP Class
 */
class WP_Embedly
{

    public $embedly_options; //embedly options array
    public $embedly_settings_page; //embedly settings page
    static $instance; //allows plugin to be called externally without re-constructing

    /**
     * Register hooks with WP Core
     */
    function __construct()
    {
        global $wpdb;
        self::$instance = $this;

        // init settings array
        $this->embedly_options = array(
            'active' => true,
            'key' => '',
            'analytics_key' => '',
            'card_controls' => true,
            'card_align' => 'center',
            'card_width' => '',
            'card_theme' => 'light',
            'is_key_valid' => false,
            'is_welcomed' => false,
        );

        //i18n
        add_action('init', array(
            $this,
            'i18n'
        ));

        //Write default options to database
        add_option('embedly_settings', $this->embedly_options);

        //Update options from database
        $this->embedly_options = get_option('embedly_settings');

        register_deactivation_hook(__FILE__, array(
            $this,
            'embedly_deactivate'
        ));


        /**
         * We have to check if a user's embedly api key is valid once in a while for
         * security. If their API key was compromised, or if their acct.
         * was deleted. This ensures plugin functionality, and proper analytics.
         *
         * But we don't need to do it every time the load the page.
         */
        if( !wp_next_scheduled( 'embedly_revalidate_account' ) ) {
            wp_schedule_event( time(), 'hourly', 'embedly_revalidate_account' );
        }

        //Admin settings page actions
        add_action('admin_menu', array(
            $this,
            'embedly_add_settings_page'
        ));

        add_action('admin_print_styles', array(
            $this,
            'embedly_enqueue_admin'
        ));

        add_action('admin_enqueue_scripts', array(
            $this,
            'embedly_localize_config'
        ));

        // action notifies user on admin menu if they don't have a key
        //add_action( 'admin_menu', array(
        //    $this,
        //    'embedly_notify_user_icon'
        //));

        add_action('wp_ajax_embedly_update_option', array(
            $this,
            'embedly_ajax_update_option'
        ));
        add_action('wp_ajax_embedly_save_account', array(
            $this,
            'embedly_save_account',
        ));
        add_action('wp_ajax_embedly_save_api_key', array(
            $this,
            'embedly_save_api_key',
        ));

        // Instead of checking for admin_init action
        // we created a custom cron action on plugin activation.
        // it will revalidate the acct every hour.
        // worst case if a user wants to revalidate immediately
        // just deactivate and reactivate the plugin
        //add_action('embedly_revalidate_account', array(
        //    $this,
        //    'validate_api_key'
        //));

        // action establishes embed.ly the provider of embeds
        add_action('plugins_loaded', array(
            $this,
            'add_embedly_providers'
        ));
    }


    /**
    * makes sure the key is always valid (in case user, say, deletes their app acct)
    **/
    function validate_api_key()
    {
        if($this->embedly_acct_has_feature('card_details', $this->embedly_options['key'])) {
            $this->embedly_save_option('is_key_valid', true);
        } else {
            $this->embedly_save_option('is_key_valid', false);
        }
    }

    /**
    * receives embedly account data from connection request
    **/
    function embedly_save_account()
    {
        // check nonce
        if( ! wp_verify_nonce($_POST['security'], "embedly_save_account_nonce") ) {
            echo "security exception";
            wp_die("security_exception");
        }

        // verify permission to save account info on 'connect' click
        if(!current_user_can('manage_options')) {
            echo "invalid permissions";
            wp_die("permission_exception");
        }

        // not validating the analytics_key for security.
        // analytics calls will just fail if it's invalid.
        if(isset($_POST) && !empty($_POST)) {
            $api_key = $_POST['api_key'];
            $analytics_key=$_POST['analytics_key'];

            $this->embedly_save_option('key', $api_key);
            $this->embedly_save_option('analytics_key', $analytics_key);
            // need to validate the API key after signup since no longer plugin_load hook.
            $this->validate_api_key();

            // better than returning some ambiguous boolean type
            echo 'true';
            wp_die();
        }
        echo 'false';
        wp_die();
    }

    /**
    * receives embedly api_key from plugin api key input, validates and saves it.
    **/
    function embedly_save_api_key()
    {
        // check nonce
        if( ! wp_verify_nonce($_POST['security'], "embedly_save_account_nonce") ) {
            echo "security exception";
            wp_die("security_exception");
        }

        // verify permission to save account info on 'connect' click
        if(!current_user_can('manage_options')) {
            echo "invalid permissions";
            wp_die("permission_exception");
        }

        if(isset($_POST) && !empty($_POST)) {
            $api_key = $_POST['api_key'];
            if (empty($api_key)) {
                # assume removal intended.
                $this->embedly_save_option('key', '');
                echo 'removed';
                wp_die();
            } else {
                # actually check the key:
                $valid = $this->embedly_acct_has_feature('card_details', $api_key);
            }

            if($valid) {
                $this->embedly_save_option('key', $api_key);
                // better than returning some ambiguous boolean type
                echo 'true';
                wp_die();
            } else {
                echo 'false';
                wp_die();
            }

            // need to validate the API key after signup since no longer plugin_load hook.
            #$this->validate_api_key();

        }
        echo 'false';
        wp_die();
    }

    /**
    * handles request from frontend to update a card setting
    **/
    function embedly_ajax_update_option()
    {
        // verify nonce
        if( ! wp_verify_nonce($_POST['security'], "embedly_update_option_nonce") ) {
            echo "security exception";
            wp_die("security_exception");
        }

        // verify permissions
        if(!current_user_can('manage_options')) {
            echo "invalid permissions";
            wp_die("permission_exception");
        }

        if(!isset($_POST) || empty($_POST)) {
          echo 'ajax-error';
          wp_die("invalid_post");
        }

        // access to the $_POST from the ajax call data object
        if ($_POST['key'] == 'card_width') {
            $this->embedly_save_option($_POST['key'], $this->handle_width_input($_POST['value']));
            // return the width of the card (only back end validated input)
            echo $this->embedly_options['card_width'];
        } else {
            $this->embedly_save_option($_POST['key'], $_POST['value']);
        }

        wp_die();
    }

    /**
     * Load plugin translation
     */
    function i18n()
    {
        load_plugin_textdomain('embedly', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /**
     * Deactivation Hook
     **/
    function embedly_deactivate()
    {
        wp_clear_scheduled_hook('embedly_revalidate_account');
        delete_option('embedly_settings');
    }


    /**
    * warns user if their key is not set in the settings
    * DEPRECATED
    **/
    function embedly_notify_user_icon()
    {
        if( !empty($this->embedly_options['key'])) {
            return;
        }

        global $menu;
        if ( !$this->valid_key() ) {
            foreach ( $menu as $key => $value ) {
                if ($menu[$key][2] == 'embedly') {
                    // accesses the menu item html
                    $menu[$key][0] .= ' <span class="update-plugins count-1">'.
                      '<span class="plugin-count"'.
                      'title="Please sync your Embedly account to use plugin">'.
                      '!</span></span>';
                    return;
                }
            }
        }
    }

    /**
     * Adds top level Embedly settings page
     **/
    function embedly_add_settings_page()
    {
        if(current_user_can('manage_options')) {
            $icon = 'dashicons-admin-generic';
            if( version_compare( $GLOBALS['wp_version'], '4.1', '>' ) ) {
               $icon = 'dashicons-align-center';
            }

            $this->embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', array(
                    $this,
                    'embedly_settings_page'
                ), $icon);
        }

    }


    /**
     * Enqueue styles/scripts for embedly page(s) only
     **/
    function embedly_enqueue_admin()
    {
        $screen = get_current_screen();
        if ($screen->id == $this->embedly_settings_page) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('embedly_admin_styles', EMBEDLY_URL . '/css/embedly-admin.css');
            wp_enqueue_style('embedly-fonts', 'https://cdn.embed.ly/wordpress/static/styles/fontspring-stylesheet.css');
            wp_enqueue_script('platform', '//cdn.embedly.com/widgets/platform.js', array(), '1.0', true);
        }
        return;
    }

    /**
    *  Localizes the configuration settings for the user, making EMBEDLY_CONFIG available
    * prior to loading embedly.js
    **/
    function embedly_localize_config()
    {
        global $settings_map;

        // DEPRECATED
        //if($this->valid_key()) {
        //    $analytics_key = $this->embedly_options['analytics_key'];
        //} else {
        //    $analytics_key = 'null';
       // }

        $ajax_url = admin_url( 'admin-ajax.php', 'relative' );

        $current = array();
        foreach ($settings_map as $setting => $api_param) {
            if(isset($this->embedly_options[$setting])) {
                if( is_bool($this->embedly_options[$setting])) {
                    $current[$setting] = $this->embedly_options[$setting] ? '1' : '0';
                } else {
                    $current[$setting] = $this->embedly_options[$setting];
                }
            }
        }

        $embedly_config = array(
            'updateOptionNonce' => wp_create_nonce("embedly_update_option_nonce"),
            'saveAccountNonce' => wp_create_nonce("embedly_save_account_nonce"),
            #'analyticsKey' => $analytics_key,
            'ajaxurl' => $ajax_url,
            'current' => $current,
        );

        wp_register_script('embedly_admin_scripts', EMBEDLY_URL . '/js/embedly.js', array(
                'jquery'
            ), '1.0', true);
        wp_localize_script('embedly_admin_scripts', 'EMBEDLY_CONFIG', $embedly_config);
        wp_enqueue_script('embedly_admin_scripts');
    }


    /**
     * Does the work of adding the Embedly providers to wp_oembed
     **/
    function add_embedly_providers()
    {
        // if user entered valid key, override providers, else, do nothing
        //if ($this->valid_key()) {
        // delete all current oembed providers
        add_filter('oembed_providers', '__return_empty_array');
        // add embedly provider
        $provider_uri = $this->build_uri_with_options();
        wp_oembed_add_provider('#https?://[^\s]+#i', $provider_uri, true);
        //}
    }


    /**
    * construct's a oembed endpoint for cards using embedly_options settings
    *
    * If key is defined, use it. Else, don't.
    **/
    function build_uri_with_options()
    {
        global $settings_map;
        // gets the subset of settings that are actually set in plugin
        $set_options = array();
        if(!empty($settings_map)) {
            foreach ($settings_map as $setting => $api_param) {
                if(isset($this->embedly_options[$setting])) {
                    $set_options[$setting] = $api_param;
                }
            }
        }

        // option params is a list of url_param => value
        // for the url string
        $first = true;
        $option_params = array(); # example: '&card_theme' => 'dark'
        foreach ($set_options as $option => $api_param) {
            $value = $this->embedly_options[$option];

            $delimiter = '&';
            if ( $first ) {
                $delimiter = '?';
                $first = false;
            }

            if ( is_bool($value) ) {
                $whole_param = $delimiter . $api_param . '=' . ($value ? '1' : '0');
                $option_params[$option] = $whole_param;
            }
            else {
                $whole_param = $delimiter . $api_param . '=' . $value;
                $option_params[$option] = $whole_param;
            }
        }

        $base = EMBEDLY_BASE_URI;
        $param_str = '';

        foreach($option_params as $key => $value) {
            $param_str .= $value; # value is the actual uri parameter, at this point
        }

        # generic key w/o analytics, no premium features
        $key = EMBEDLY_WP_BASE_KEY;

        # unless overidden
        if ($this->embedly_options['key']) {
            $key = $this->embedly_options['key'];
            $cards_key_param = '&cards_key=' . $key;
            $param_str .= $cards_key_param;
        }

        $key_param = '&key=' . $key;
        $param_str .= $key_param;

        return $base . $param_str;
    }


    /**
    * legacy function to check if account has specific features enabled
    * but mainly, we care to check if the key is valid
    **/
   function embedly_acct_has_feature($feature, $key = false)
   {
        if ($key) {
           $result = wp_remote_retrieve_body(wp_remote_get(
            'http://api.embed.ly/1/feature?feature=' .
            $feature .
            '&key=' .
            $key));
        } else {
           return false;
        }

        $error_code = 'error_code';
        $feature_status = json_decode($result);
        if (isset($feature_status->$error_code)) {
            return false;
        }
        if ($feature_status) {
           return $feature_status->$feature;
        } else {
           return false;
        }
   }


    /**
    * update embedly_options with a given key: value pair
    **/
    function embedly_save_option($key, $value)
    {
        if(current_user_can('manage_options')) {
            $key = sanitize_key( $key );
            $value = sanitize_text_field( $value );

            $this->embedly_options[$key] = $value;
            update_option('embedly_settings', $this->embedly_options);
            $this->embedly_options = get_option('embedly_settings');

       }
    }

    /**
    * removes a setting
    **/
    function embedly_delete_option($key)
    {
        if(current_user_can('manage_options')) {
            unset($this->embedly_options[$key]);
            update_option('embedly_settings', $this->embedly_options);
            $this->embedly_options = get_option('embedly_settings');
        }
    }


    /**
    * handles 'max width' input for card defaults
    * returns the string corresponding to the correct cards_width
    * card parameter
    **/
    function handle_width_input($input)
    {
        // width can be '%' or 'px'
        // first check if '100%',
        $percent = $this->int_before_substring($input, '%');
        if ($percent != 0 && $percent <= 100) {
            return $percent . '%';
        }

        // try for a value like 300px (platform can only handle >200px?)
        $pixels = $this->int_before_substring($input, 'px');
        if ($pixels > 0) {
            return max($pixels, 200);
        }

        // try solitary int value.
        $int = intval($input);
        if ($int > 0) {
            return max($int, 200);
        }

        return "";
    }


    /**
    * returns valid integer (not inclusive of 0, which indicates failure)
    * preceding a given token $substring.
    * given '100%', '%' returns 100
    * given 'asdf', '%', returns 0
    **/
    function int_before_substring($whole, $substring)
    {
        $pos = strpos($whole, $substring);
        if($pos != false) {
            $preceding = substr($whole, 0, $pos);
            return $percent = intval($preceding);
        }
    }

    /**
    * waterfall failure checks on the key
    **/
    function valid_key()
    {
        if (!isset($this->embedly_options['key'])) {
            return false;
        }
        if (empty($this->embedly_options['key'])) {
            return false;
        }
        if(!isset($this->embedly_options['is_key_valid'])) {
            return false;
        }
        if (!$this->embedly_options['is_key_valid']) {
            return false;
        }

        return true;
    }


    /////////////////////////// BEGIN TEMPLATE FUNCTIONS FOR FORM LOGIC


    /**
    * returns max_width setting as a html value attr
    **/
    function get_value_embedly_max_width()
    {
        if(isset($this->embedly_options['card_width'])) {
          $value = 'value="';
          $width = $this->embedly_options['card_width'];
          if(strpos($width, '%') !== false) {
            $value .= $width;
          } else if($width !== '') {
            // we remove for api call, but replace for user
            $value .= $width . "px";
          }

          echo $value . '" ';
        }
    }

    /**
    * returns embedly api_key if it's set
    **/
    function get_value_embedly_api_key()
    {
        if(isset($this->embedly_options['key'])) {
          $value = 'value="';
          $key = $this->embedly_options['key'];
          $value = $value . $key;
          echo $value . '" ';
        }
    }

    /**
    * returns current card_align value
    **/
    function get_current_align()
    {
        $current_align = 'center'; // default if not set
        if(isset($this->embedly_options['card_align'])) {
          $current_align = $this->embedly_options['card_align'];
        }
        return $current_align;
    }

    /**
    * Builds an href for the Realtime Analytics button
    * DEPRECATED
    */
    function get_onclick_analytics_button() {
        if($this->valid_key()) {
            echo ' href="https://app.embed.ly/r' . '?api_key=' . $this->embedly_options['key'] .
                '&path=analytics' .'" ';
        } else {
            // how to fail gracefully here? (should always have key)
            echo ' href="http://app.embed.ly" ';
        }
    }

    /**
    * sets the class of the preview container.. if dark theme, add "dark-theme" class
    */
    function get_class_card_preview_container() {
      $class = 'class="card-preview-container';
      if($this->embedly_options['card_theme'] == 'dark') {
        $class .= ' dark-theme';
      }
      echo $class . '" ';
    }

    /**
    * fallback for alignment icons (only change nec. to support 3.8, atm)
    **/
    function get_compatible_dashicon($align)
    {
      $base = '"dashicons align-icon ';
      // WP 4.1 has the "new" align icon, else, use old one (until 3.8)
      if( version_compare( $GLOBALS['wp_version'], '4.1', '<' ) ) {
        if($align == 'left') {
          // left is being reversed  to support di-none in 4.1+
          echo $base . 'dashicons-editor-alignleft';
        } else if($align == 'right') {
          echo $base . 'dashicons-editor-alignleft di-reverse';
        } else {
          echo $base . 'dashicons-editor-aligncenter';
        }
      } else {
        if($align == 'left') {
          echo $base . 'di-none';
        } else if($align == 'right'){
          echo $base . 'di-none di-reverse';
        } else {
          echo $base . 'di-center';
        }
      }
    }

    /**
    * Welcome the user one time.
    **/
    function get_welcome_message() {
        if (isset($this->embedly_options['is_welcomed']) && !$this->embedly_options['is_welcomed']) {
            $this->embedly_save_option('is_welcomed', true);
            echo "<h3>You're ready to start embedding.</h3>".
                 "<h2>Paste a URL in a new post and it will automatically embed and measure analytics.</h2>".
                 "<h2>For more on getting started, check out the tutorial below.</h2>";
        } else echo "";
    }
    /////////////////////////// END TEMPLATE FUNCTIONS FOR FORM LOGIC

    /**
     * The Admin Page. Abandon all hope, all ye' who enter here.
     **/
    function embedly_settings_page()
    {
        global $wpdb;
        ######## BEGIN FORM HTML #########
        #debugging:
        #echo $this->build_uri_with_options();

        ?>
          <div class="embedly-wrap">
            <div class="embedly-ui">
              <div class="embedly-input-wrapper">

                <!-- EXISTING USER MODAL -->
                <form id="embedly_key_form" method="POST" action="">
                  <div class="embedly-ui-header-outer-wrapper">
                    <div class="embedly-ui-header-wrapper">
                      <div class="embedly-ui-header">
                        <a class="embedly-ui-logo" href="http://embed.ly" target="_blank"><?php
                          esc_html_e('Embedly', 'embedly');
                          ?></a>
                      </div>
                    </div>
                  </div>

                    <div class="embedly-ui-key-wrap">
                      <div class="embedly_key_form embedly-ui-key-form">

                        <div id="welcome-blurb">
                          <?php $this->get_welcome_message();  ?>
                        </div>

                        <!--
                        <div class="embedly-analytics">
                          <div class="active-viewers">
                            <h1 class="active-count"><img src=<?php echo EMBEDLY_URL . "/img/ajax-loader.gif" ?>></h1>
                            <p>People are <strong>actively viewing</strong> your embeds!</p>
                            <br/>
                            <a class="emb-button" target="_blank" <?php $this->get_onclick_analytics_button(); ?>><?php esc_html_e('Realtime Analytics', 'embedly')?></a>
                          </div>
						  <div class="historical-viewers">
                            <h1 class="weekly-count"><img src=<?php echo EMBEDLY_URL . "/img/ajax-loader.gif" ?>></h1>
                            <p>People have <strong>viewed</strong> an embed in the <strong>last week</strong>.</p>
                          </div>
                        </div> -->

                        <!-- Begin 'Advanced Options' Section -->
                        <hr>

                        <div class="advanced-wrapper dropdown-wrapper">
                          <div class="advanced-header dropdown-header">
                            <a href="#"><h3><?php esc_html_e('ADVANCED EMBED SETTINGS', 'embedly'); ?>
                            <span id="advanced-arrow" class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                          </div>
                          <div class = "advanced-body dropdown-body">
                            <p><?php esc_html_e('Altering these settings will only change the look of your future embeds.  Previously existing embeds will not change.', 'embedly');?>
                           </p></div>
                          <div class="advanced-body dropdown-body">
                            <div class="advanced-selections">
                              <!-- Boolean Attributes (ie. Card Theme, etc) -->
                              <ul>
                                <li>
                                  <h3><?php esc_html_e('TEXT', 'embedly'); ?></h3>
                                  <input class='embedly-dark-checkbox' type='checkbox' value='checked' name='card_dark' <?php
                                    checked( $this->embedly_options['card_theme'], 'dark');
                                    ?> /> <?php esc_html_e('LIGHT TEXT', 'embedly'); ?>
                                </li>
                                <li>
                                  <h3><?php esc_html_e('BUTTONS', 'embedly'); ?></h3>
                                  <input class='embedly-social-checkbox' type='checkbox' value='checked' name='card_controls' <?php
                                    checked( $this->embedly_options['card_controls'], 1);
                                    ?> /> <?php esc_html_e('SHARING BUTTONS', 'embedly'); ?>
                                </li>

                                <li><!-- Width Input Area -->
                                  <div class="max-width-input-container">
                                    <h3><?php esc_html_e('WIDTH', 'embedly'); ?></h3>
                                    <input id='embedly-max-width' type="text" name="card_width" placeholder="<?php esc_attr_e('Responsive if left blank', 'embedly'); ?>"
                                      <?php $this->get_value_embedly_max_width(); ?>/>
                                      <p><i><?php esc_html_e('Example: 400px or 80%.', 'embedly'); ?></i></p>
                                      <!-- <p><i><?php esc_html_e('Responsive if left blank', 'embedly'); ?></i></p> -->
                                  </div>
                                </li>
                                <li>
                                  <!-- Card Alignment Options -->
                                  <h3><?php esc_html_e('ALIGNMENT', 'embedly'); ?></h3>
                                  <div class="embedly-align-select-container embedly-di">
                                    <ul class="align-select">
                                      <?php
                                        $current_align = $this->get_current_align();
                                        $sel = ' selected-align-select"';
                                      ?>
                                      <li><span class=
                                        <?php echo $this->get_compatible_dashicon('left') . ($current_align == 'left' ? $sel : '"'); ?>
                                        title="Left" align-value="left">
                                        <input type='hidden' value='unchecked' name='card_align_left'>
                                        </span>
                                      </li>
                                      <li><span class=
                                        <?php echo $this->get_compatible_dashicon('center') . ($current_align == 'center' ? $sel : '"'); ?>
                                        title="Center" align-value="center">
                                        <input type='hidden' value='checked' name='card_align_center'>
                                        </span>
                                      </li>
                                      <li><span class=
                                        <?php echo $this->get_compatible_dashicon('right') . ($current_align == 'right' ? $sel : '"'); ?>
                                        title="Right" align-value="right">
                                        <input type='hidden' value='unchecked' name='card_align_right'>
                                        </span>
                                      </li>
                                    </ul>
                                  </div>
                                </li>
                              </ul>
                            </div>
                            <!-- preview card -->
                            <div <?php $this->get_class_card_preview_container(); ?>>
                              <h3><?php esc_html_e('CARD PREVIEW', 'embedly'); ?>
                                <span id="embedly-settings-saved"><i><?php esc_html_e('settings saved', 'embedly'); ?> </i></span>
                              </h3>
                              <a class="embedly-card-template"
                                href="https://vimeo.com/80836225">
                              </a>
                            </div>
                          </div>
                        </div> <!-- END 'Options' Section -->

                        <!-- BEGIN TUTORIAL EXPANDER -->
                        <div class="tutorial-wrapper dropdown-wrapper">
                          <div class="tutorial-header dropdown-header">
                            <a href="#"><h3><?php esc_html_e('TUTORIAL', 'embedly'); ?>
                            <span id="tutorial-arrow" class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                          </div>
                          <div class="tutorial-body dropdown-body">
                            <div class="embedly-tutorial-container">
                                <p>Using the plugin is now as easy as pasting a URL into the post editor.
                                We then do our best to find the right embed for that URL, especially if it's one of our
                                    <strong><a href="https://embed.ly/providers" target="_blank"><?php esc_html_e('500+ providers', 'embedly'); ?></a></strong></p>
                                <p>To learn more about how the plugin works, please visit
                                    <strong><a href="https://wordpress.org/plugins/embedly" target="_blank">
                                        <?php esc_html_e('our plugin page', 'embedly'); ?></a></strong></p>

                            </div>
                          </div>
                        </div> <!-- END 'Tutorial' Section -->

                        <!-- BEGIN API KEY EXPANDER -->
                        <div class="tutorial-wrapper dropdown-wrapper">
                          <div class="tutorial-header dropdown-header">
                            <a href="#"><h3><?php esc_html_e('API KEY', 'embedly'); ?>
                            <span id="tutorial-arrow" class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                          </div>
                          <div class="api-key-body dropdown-body">
                              <div class="api-key-input-container">
                                <p><?php esc_html_e('Are you an Embedly Cards member? Enter your API key here to utilize analytics and remove our branding', 'embedly'); ?></p>
                                <input id='embedly-api-key' class='default-input' type="text" name="api_key" placeholder="<?php esc_attr_e('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'embedly'); ?>"
                                    <?php $this->get_value_embedly_api_key(); ?>/>

                                <p><?php esc_html_e('Learn more at', 'embedly'); ?> 
                                    <strong><a href="https://embed.ly/cards" target="_blank"><?php esc_html_e('embed.ly/cards', 'embedly') ?></strong></a></p>
                                <p><?php esc_html_e('Already have an account? You can find your api key and view analytics', 'embedly'); ?> 
                                    <strong><a href="https://app.embed.ly" target="_blank"><?php esc_html_e('here', 'embedly') ?></strong></a></p>
                              </div>
                          </div>
                        </div> <!-- END 'API KEY' Section -->


                      </div>
                    </div>
                  </form>
                <div id="footer">
                  <footer class="embedly-footer">
                    &copy; <?php echo date('Y') . __( ' All Rights Reserved ', 'embedly'); ?>
                    <span class="dashicons dashicons-heart"></span>
                    Built in Boston
                  </footer>
                </div> <?php
    } // END settings page function
} // END WP_Embedly class


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
