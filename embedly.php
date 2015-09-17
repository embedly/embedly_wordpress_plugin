<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly/wordpress
Description: The Embedly Plugin extends Wordpress's automatic embed feature, allowing bloggers to Embed from 300+ services and counting.
Author: Embed.ly Inc
Version: 4.0.0
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
if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', WP_SITEURL . '/wp-content');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}
if (!defined('EMBEDLY_DIR')) {
    define('EMBEDLY_DIR', WP_PLUGIN_DIR . '/embedly');
}
if (!defined('EMBEDLY_URL')) {
    define('EMBEDLY_URL', WP_PLUGIN_URL . '/embedly');
}
if (!defined('EMBEDLY_BASE_URI')) {
    define('EMBEDLY_BASE_URI', 'https://api.embedly.com/1/card?');
}

// DEBUGGING
$EMBEDLY_DEBUG = false;

// maps local settings key => api param name
$settings_map = array(
    'card_controls' => 'cards_controls',
    'card_chrome' => 'cards_chrome',
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
            'card_chrome' => false,
            'card_controls' => true,
            'card_align' => 'center',
            'card_theme' => 'light',
            'key_valid?' => false,
            'welcomed?' => false,
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

        //Admin settings page actions
        add_action('admin_menu', array(
            $this,
            'embedly_add_settings_page'
        ));
        add_action('admin_print_styles', array(
            $this,
            'embedly_enqueue_admin'
        ));
        add_action('wp_head', array(
            $this,
            'embedly_enqueue_public'
        ));

        // action notifies user on admin menu if they don't have a key
        add_action( 'admin_menu', array(
            $this,
            'embedly_notify_user_icon'
        ));
        // ajax requests
        add_action('wp_ajax_embedly_analytics_active_viewers', array(
            $this,
            'embedly_ajax_get_active_viewers'
        ));
        add_action('wp_ajax_embedly_analytics_historical_viewers', array(
            $this,
            'embedly_ajax_get_historical_viewers'
        ));
        add_action('wp_ajax_embedly_update_option', array(
            $this,
            'embedly_ajax_update_option'
        ));
        add_action('wp_ajax_embedly_save_account', array(
            $this,
            'embedly_save_account',
        ));

        // validates api key on load
        add_action('plugins_loaded', array(
          $this,
          'validate_api_key'
        ));

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
        if($this->embedly_acct_has_feature('oembed', $this->embedly_options['key'])) {
            $this->embedly_save_option('key_valid?', true);
        } else {
            $this->embedly_save_option('key_valid?', false);
        }
    }

    /**
    * receives embedly account data from connection request
    **/
    function embedly_save_account()
    {
        // not validating the analytics_key for security.
        // analytics calls will just fail if it's invalid.
        if(isset($_POST) && !empty($_POST)) {
            $api_key = $_POST['api_key'];
            $analytics_key=$_POST['analytics_key'];

            if ($this->embedly_acct_has_feature('oembed', $api_key)) {
                $this->embedly_save_option('key', $api_key);
                $this->embedly_save_option('analytics_key', $analytics_key);

                // better than returning some ambiguous boolean type
                echo 'true';
                wp_die();
            }
        }
        echo 'false';
        wp_die();
    }

    /**
    * handles request from frontend to update a card setting
    **/
    function embedly_ajax_update_option()
    {
        if(!isset($_POST) || empty($_POST)) {
          echo 'ajax-error';
          wp_die();
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

    function embedly_ajax_get_historical_viewers()
    {
        // will eventually need to check also if analytics key exists..
        if($this->valid_key()) {
            $end = date("Ymd");
            $lastweek = time() - (7 * 24 * 60 * 60);
            $start = date("Ymd", $lastweek);
            // turn off till analytics
            $analytics_key = $this->embedly_options['analytics_key'];

            $historical_url =
                "https://t.embed.ly/2/analytics/stats?".
                "start=" . $start .
                "&end=" . $end .
                "&key=" . $analytics_key;

            // create curl resource
            $ch = curl_init();
            // set url
            curl_setopt($ch,
                CURLOPT_URL,
                $historical_url);
            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // $output contains the output string
            $output = curl_exec($ch);
            // close curl resource to free up system resources
            curl_close($ch);
            // send output to frontend
            echo $output;
            // done ajax call
            wp_die();
        } else {
            // there was some key error
            echo '{"err": true}';
            wp_die();
        }
    }

    /**
    * Makes a call for realtime analytics, and returns data to front end
    **/
    function embedly_ajax_get_active_viewers()
    {
        if ($this->valid_key()) {
            $narrate_url = "https://narrate.embed.ly/1/series?key=" .
              $this->embedly_options['analytics_key'];
            // create curl resource
            $ch = curl_init();
            // set url
            curl_setopt($ch,
                CURLOPT_URL,
                $narrate_url);
            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // $output contains the output string
            $output = curl_exec($ch);
            // close curl resource to free up system resources
            curl_close($ch);
            // send output to frontend
            if(empty($output)) {
              echo '{"active": "error"}';
              wp_die();
            }
            echo $output;
            // done ajax call
            wp_die();
        } else {
            // there was some key error
            echo '{"active": "-"}';
            wp_die();
        }

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
        delete_option('embedly_settings');
    }


    /**
    * warns user if their key is not set in the settings
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
     * Adds toplevel Embedly settings page
     **/
    function embedly_add_settings_page()
    {
        $icon = 'dashicons-admin-generic';
        if( version_compare( $GLOBALS['wp_version'], '4.1', '>' ) ) {
           $icon = 'dashicons-align-center';
        }

        $this->embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', array(
                $this,
                'embedly_settings_page'
            ), $icon);
    }


    /**
     * Enqueue styles/scripts for embedly page(s) only
     **/
    function embedly_enqueue_admin()
    {
        $screen = get_current_screen();
        if ($screen->id == $this->embedly_settings_page) {
            $protocol = is_ssl() ? 'https' : 'http';
            wp_enqueue_style('dashicons');
            wp_enqueue_style('embedly_admin_styles', EMBEDLY_URL . '/css/embedly-admin.css');
            wp_enqueue_style('embedly-fonts', $protocol . '://embed.ly/static/styles/fontspring-stylesheet.css');
            // wp_enqueue_style('google_fonts', $protocol . '://fonts.googleapis.com/css?family=Cabin:400,600');
            // controls some of the functionality of the settings page, will need to go through embedly.js at some point
            wp_enqueue_script('embedly_admin_scripts', EMBEDLY_URL . '/js/embedly.js', array(
                'jquery'
            ), '1.0', true);
        }
        return;
    }


    /**
     * Enqueue styles for front-end
     **/
    function embedly_enqueue_public()
    {
        wp_enqueue_style('embedly_front_end', EMBEDLY_URL . '/css/embedly-frontend.css');
    }


    /**
     * Does the work of adding the Embedly providers to wp_oembed
     **/
    function add_embedly_providers()
    {
        # if user entered valid key, override providers, else, do nothing
        if (!empty($this->embedly_options['key']) && $this->valid_key()) {
            // delete all current oembed providers
            add_filter('oembed_providers', create_function('', 'return array();'));
            // except twitter (@cstiteler: test that this works for twitter)
            // wp_oembed_add_provider('#https?://(www\.)?twitter\.com/.+?/status(es)?/.*#i', 'https://api.twitter.com/1/statuses/oembed.{format}', true);
            $provider_uri = $this->build_uri_with_options();
            wp_oembed_add_provider('#.*#i', $provider_uri, true);
        }
    }


    /**
    * construct's a oembed endpoint for cards using embedly_options settings
    **/
    function build_uri_with_options()
    {
        global $settings_map;
        // gets the subset of settings that are actually set in plugin
        $set_options = array();
        foreach ($settings_map as $setting => $api_param) {
            if(isset($this->embedly_options[$setting])) {
                $set_options[$setting] = $api_param;
            }
        }

        // option params is a list of url_param => value
        // for the url string
        $option_params = array(); # example: '&card_theme' => 'dark'
        foreach ($set_options as $option => $api_param) {
            $value = $this->embedly_options[$option];
            if ( is_bool($value) ) {
                $whole_param = '&' . $api_param . '=' . ($value ? '1' : '0');
                $option_params[$option] = $whole_param;
            }
            else {
                $whole_param = '&' . $api_param . '=' . $value;
                $option_params[$option] = $whole_param;
            }
        }

        $base = EMBEDLY_BASE_URI;
        $key = 'key=' . $this->embedly_options['key']; // first param
        $uri = $base . $key;
        foreach($option_params as $key => $value) {
            $uri .= $value; # value is the actual uri parameter, at this point
        }

        return $uri;
    }


    /**
    * Function to check if account has specific features enabled
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
       $this->embedly_options[$key] = $value;
       update_option('embedly_settings', $this->embedly_options);
       $this->embedly_options = get_option('embedly_settings');
    }

    function embedly_delete_option($key)
    {
        unset($this->embedly_options[$key]);
        update_option('embedly_settings', $this->embedly_options);
        $this->embedly_options = get_option('embedly_settings');
    }


    /**
    * handles 'max width' input for card defaults
    * returns the string corresponding to the correct cards_width
    * card paramater
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

    /////////////////////////// BEGIN TEMPLATE FUNCTIONS FOR FORM LOGIC
    function valid_key()
    {
      if (!isset($this->embedly_options['key'])) {
        return false;
      }
      if (empty($this->embedly_options['key'])) {
        return false;
      }
      if(!isset($this->embedly_options['key_valid?'])) {
        return false;
      }
      if (!$this->embedly_options['key_valid?']) {
        return false;
      }

      return true;
    }

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
    * builds a <script> tag that globalizes the current card settings for preview init
    **/
    function get_script_embedly_current_card() {
        global $settings_map;
        $current_card_script = "<script> var current_card = {";
        foreach ($settings_map as $setting => $api_param) {
          if(isset($this->embedly_options[$setting])) {
            $value= '';
            if( is_bool($this->embedly_options[$setting]) ) {
              $value = $this->embedly_options[$setting] ? '1': '0';
            } else {
              $value = $this->embedly_options[$setting];
            }
            $current_card_script .= "'" . $setting . "': '" . $value . "',";
          }
        }
        $current_card_script .= '}</script>';
        echo $current_card_script;
    }

    /**
    * Builds an href for the Realtime Analytics button
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


    function get_welcome_message() {
        if (isset($this->embedly_options['welcomed?']) && !$this->embedly_options['welcomed?']) {
            $this->embedly_save_option('welcomed?', true);
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
        ?>
        <head>
          <?php $this->get_script_embedly_current_card(); ?>
          <script async src="//cdn.embedly.com/widgets/platform.js" charset="UTF-8"></script>
        <head>
          <div class="embedly-wrap">
            <div class="embedly-ui">
              <div class="embedly-input-wrapper">


              <!-- DELETE FOR PRODUCTION -->
              <?php
              global $EMBEDLY_DEBUG;
              if( isset($EMBEDLY_DEBUG) && ($EMBEDLY_DEBUG) ) { ?>

                <!-- Testing key input states -->
                  DEBUGGING:
                  <?php echo "CURRENT URI: " . $this->build_uri_with_options() ;?>
      <?php } ?>
          <!-- END DELETE FOR PRODUCTION -->


        <?php
            // Decide which modal to display.
            if( $this->valid_key() ) { ?>

                <form id="embedly_key_form" method="POST" action="">
                  <div class="embedly-ui-header-outer-wrapper">
                    <div class="embedly-ui-header-wrapper">
                      <div class="embedly-ui-header">
                        <a class="embedly-ui-logo" href="http://embed.ly" target="_blank"><?php
                          _e('Embedly', 'embedly');
                          ?></a>
                      </div>
                    </div>
                  </div>

                    <div class="embedly-ui-key-wrap">
                      <div class="embedly_key_form embedly-ui-key-form">

                        <div id="welcome-blurb">
                          <?php $this->get_welcome_message();  ?>
                        </div>
                        <div class="embedly-analytics">
                          <div class="active-viewers">
                            <h1 class="active-count"><img src=<?php echo EMBEDLY_URL . "/img/ajax-loader.gif" ?>></h1>
                            <p>People are <strong>actively viewing</strong> your embeds!</p>
                            <br/> <!-- is this acceptable? need to format my h tags for this page.-->
                            <a class="emb-button" target="_blank" <?php $this->get_onclick_analytics_button(); ?>><?php _e('Realtime Analytics', 'embedly')?></a>
                          </div>
                          <div class="historical-viewers">
                            <h1 class="weekly-count"><img src=<?php echo EMBEDLY_URL . "/img/ajax-loader.gif" ?>></h1>
                            <p>People have <strong>viewed</strong> an embed in the <strong>last week</strong>.</p>
                          </div>
                        </div>

                        <!-- Begin 'Advanced Options' Section -->
                        <hr>

                        <div class="advanced-wrapper dropdown-wrapper">
                          <div class="advanced-header">
                            <a href="#"><h3>ADVANCED EMBED SETTINGS
                            <span id="advanced-arrow" class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                          </div>
                          <div class = "advanced-body">
                            <p>Changing these settings will change how your future embeds appear.
                           </p></div>
                          <div class="advanced-body">
                            <div class="advanced-selections">
                              <!-- Boolean Attributes (ie. Chromeless, Card Theme, etc) -->
                              <ul>
                                <li>
                                  <h3>DESIGN</h3>
                                  <input class='chrome-card-checkbox' type='checkbox' value='checked' name='minimal' <?php
                                    // checked( $this->embedly_options['card_chrome'], !1); // ¯\_(ツ)_/¯ -php
                                  checked( @$this->embedly_options["card_chrome"] ?: false, false);
                                    ?> /> MINIMAL
                                </li>
                                <li>
                                  <h3>TEXT</h3>
                                  <input class='embedly-dark-checkbox' type='checkbox' value='checked' name='card_dark' <?php
                                    checked( $this->embedly_options['card_theme'], 'dark');
                                    ?> /> LIGHT TEXT
                                </li>
                                <li>
                                  <h3>BUTTONS</h3>
                                  <input class='embedly-social-checkbox' type='checkbox' value='checked' name='card_controls' <?php
                                    checked( $this->embedly_options['card_controls'], 1);
                                    ?> /> SHARING BUTTONS
                                </li>

                                <li><!-- Width Input Area -->
                                  <div class="max-width-input-container">
                                    <h3>WIDTH</h3>
                                    <input id='embedly-max-width' type="text" name="card_width" placeholder="example: 400px"
                                      <?php $this->get_value_embedly_max_width(); ?>/>
                                    <p><i>Responsive if left blank</i></p>
                                  </div>
                                </li>
                                <li>
                                  <!-- Card Alignment Options -->
                                  <h3>ALIGNMENT</h3>
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
                            <!-- preview card.. work in progress -->
                            <div <?php $this->get_class_card_preview_container(); ?>>
                              <h3>CARD PREVIEW
                                <span id="embedly-settings-saved"><i><?php _e('settings saved', 'embedly'); ?> </i></span>
                              </h3>
                              <a class="embedly-card-template"
                                href="https://vimeo.com/80836225">
                              </a>
                            </div>
                          </div>
                        </div> <!-- END 'Options' Section -->


                        <!-- BEGIN TUTORIAL EXPANDER -->
                        <div class="tutorial-wrapper dropdown-wrapper">
                          <div class="tutorial-header">
                            <a href="#"><h3>TUTORIAL
                            <span id="tutorial-arrow" class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                          </div>
                          <div class="tutorial-body">
                            <div class="embedly-tutorial-container">
                              <a id="embedly-tutorial-card"
                                href="https://vimeo.com/62648882"
                                data-card-controls="0" data-card-chrome="0"
                                data-card-width="65%">
                              </a>
                            </div>
                          </div>
                        </div> <!-- END 'Tutorial' Section -->
                      </div>
                    </div>
                  </form>
                <?php  // ELSE: Key is not entered
              } else {  ?>
                  <!-- MODAL FOR NEW ACCOUNTS -->
                <div class="embedly-ui">
                  <div class="embedly-ui-header-outer-wrapper">
                    <div class="embedly-ui-header-wrapper">
                      <div class="embedly-ui-header">
                        <a class="embedly-ui-logo" href="http://embed.ly" target="_blank">
                        <?php _e('Embedly', 'embedly'); ?>
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="embedly-ui-key-wrap embedly-new-user-modal">
                    <div class="embedly_key_form embedly-ui-key-form">
                      <div class="welcome-page-body">
                        <!-- HERO TEXT -->
                        <h1>Embed content from any site!</h1>
                        <section>
                          <!-- Tutorial Video -->
                          <div class="embedly-tutorial-container">
                            <a id="embedly-tutorial-card"
                              href="https://vimeo.com/62648882"
                              data-card-controls="0" data-card-chrome="0"
                              data-card-width="65%">
                            </a>
                          </div>
                        </section>

                        <section>
                          <!-- Blurb -->
                          <div id="embedly-welcome-blurb">
                            <p>
                              <span id="twitter-icon" class="dashicons dashicons-twitter"></span>
                              Now with Twitter support! In addition to the default Wordpress embedding,
                              you get embedding for any article, gfycat, storify, and twitch.  See our growing
                              <a href="http://embed.ly/providers" target="_blank"><strong>growing list of embed providers</strong>.</a>
                            </p>
                              <p>Getting started? <strong>Learn more above</strong> about embedly cards for Wordpress.</p>
                          </div>
                        </section>

                        <section>
                          <!-- Create an embed.ly account button -->
                          <div class="embedly-create-account-btn-wrap">
                            <p><?php _e("Don't Have An Account?", "embedly"); ?></p>
                            <input id='create-account-btn' class="emb-button emb-button-long" type="button"
                              value="<?php _e('GET STARTED HERE!', 'embedly')?>"/>
                            <p>&nbsp;</p>
                            <p><?php _e("Already have an Embedly account?", "embedly"); ?>
                                <strong><a id="preexisting-user" href="https://app.embed.ly" target="_blank"><?php _e('Login', 'embedly'); ?></a></strong>
                            </p>
                          </div>
                          <button id="connect-button" class="emb-button emb-button-long">
                            <div class="inner-connect-button">
                              <span class="inner-button-span">
                                <img id="connect-btn-img" src=<?php echo EMBEDLY_URL . "/img/embedly-white-70-40.svg" ?>>
                              </span>
                              <span class="inner-button-span">
                                ACTIVATE WITH EMBEDLY ACCOUNT
                              </span>
                            </div>
                          </button>


                          <!-- dropdown for selecting a project -->
                          <div id="embedly-which">
                            <p><strong>Which Project Would you Like to Connect?</strong></p>
                            <h4>&nbsp;</h4>
                            <ul id="embedly-which-list"></ul>
                          </div>
                        </section>
                        <section>
                          <div id="embedly-connect-failed-refresh">
                          <p>You may need to refresh the page after connecting</p>
                          </div>
                        </section>
                       </div>
                     </div>
                   </div>
                </div>
              <?php } // END if/else for new/existing account
              ?>
                <div id="footer">
                  <footer class="embedly-footer">
                    &copy; <?php _e(date('Y') . ' All Rights Reserved ', 'embedly'); ?>
                    <span class="dashicons dashicons-heart"></span>
                    Built in Boston
                  </footer>
                </div> <?php
    } // END settings page function
} // END WP_Embedly class


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
