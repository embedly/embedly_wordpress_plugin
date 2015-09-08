<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly
Description: The Embedly Plugin extends Wordpress's automatic embed feature, allowing bloggers to Embed from 230+ services and counting.
Author: Embed.ly Inc
Version: 4.0.0
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
    define('EMBEDLY_DIR', WP_PLUGIN_DIR . '/embedly_wordpress_plugin');
}
if (!defined('EMBEDLY_URL')) {
    define('EMBEDLY_URL', WP_PLUGIN_URL . '/embedly_wordpress_plugin');
}
if (!defined('EMBEDLY_BASE_URI')) {
    define('EMBEDLY_BASE_URI', 'https://api.embedly.com/1/card?');
}
if(!defined('SIGNUP_URL')) {
    define('SIGNUP_URL', 'https://app.embed.ly/signup/wordpress');
}

// DEBUGGING
$EMBEDLY_DEBUG = false;

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
            'card_chrome' => false,
            'card_controls' => true,
            'card_align' => 'center',
            'card_theme' => 'light',
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
        // ajax for analytics data
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

        // ajax for key handling logic
        add_action('wp_ajax_embedly_key_input', array(
            $this,
            'embedly_key_input'
        ));

        // action establishes embed.ly the sole provider of embeds
        // (except those unsupported)
        add_action('plugins_loaded', array(
            $this,
            'add_embedly_providers'
        ));
    }

    function embedly_key_input() {
        // receives a key in $_POST, returns on of the valid key states.
        $key = $_POST['key'];
        if ( $this->embedly_acct_has_feature('oembed', $key) ) {
            // better than returning some ambiguous boolean type
            $this->embedly_save_option('key', $key);
            echo 'true';
        } else {
            echo 'false';
        }
        wp_die();
    }

    function embedly_ajax_update_option() {
        // access to the $_POST from the ajax call data object
        if ($_POST['key'] == 'card_width') {
            $this->embedly_save_option($_POST['key'], $this->handle_width_input($_POST['value']));
        } else {
            $this->embedly_save_option($_POST['key'], $_POST['value']);
        }
        echo $this->embedly_options['card_width'];

        wp_die();
    }

    function embedly_ajax_get_historical_viewers()
    {
        // begin delete after impl. analytics key
        // until analytics key implemented.. just return an error
        echo '{"err": true}';
        wp_die();
        // end delete after impl. analytics key

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
            $narrate_url = "https://narrate.embed.ly/1/series?key=" . $this->embedly_options['key'];
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
            echo $output;
            // done ajax call
            wp_die();
        } else {
            // there was some key error
            echo "{active: '-'}";
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
        if ( empty($this->embedly_options['key']) ) {
            foreach ( $menu as $key => $value ) {
                if ($menu[$key][2] == 'embedly') {
                    // accesses the menu item html
                    $menu[$key][0] .= ' <span class="update-plugins count-1"><span class="plugin-count">!</span></span>';
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
        $this->embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', array(
            $this,
            'embedly_settings_page'
        ), 'dashicons-align-center'); // icon looks generally like an embeded card
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
            wp_enqueue_style('google_fonts', $protocol . '://fonts.googleapis.com/css?family=Cabin:400,600');
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
        if (!empty($this->embedly_options['key'])) {
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
        // maps local settings key => api param name
        $settings_map = array(
            'card_controls' => 'cards_controls',
            'card_chrome' => 'cards_chrome',
            'card_theme' => 'cards_theme',
            'card_width' => 'cards_width',
            'card_align' => 'cards_align',
        );

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

    function embedly_delete_option($key) {
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
        // first check if '%',
        $percent = $this->int_before_substring($input, '%');
        if ($percent != 0 && $percent <= 100) {
            return $percent . '%';
        }

        // try for px:
        $pixels = $this->int_before_substring($input, 'px');
        if ($pixels > 0) {
            return $pixels . 'px';
        }

        // try solitary int value.
        $int = intval($input);
        if ($int > 0) {
            return $int . 'px';
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
    function get_class_embedly_api_key_input_container() {
         $class = 'class="embedly-api-key-input-container';
          if ($this->valid_key()) {
              $class .= ' locked_key';
          }
          $class .= '"';
          echo $class;
    }

    function get_value_embedly_key_test() {
        if ($this->valid_key()) {
          echo 'value="' . $this->embedly_options['key'] . '" readonly';
        }
    }

    function get_class_key_icon_span() {
        $class = 'class="dashicons key-icon lock-control-key-icon';
        if ($this->valid_key()) {
            // set the key icon if necessary.
            $class .= ' locked-key-icon';
        }
        $class .= '"';
        echo $class;
    }

    function valid_key() {
        return isset($this->embedly_options['key']) && !empty($this->embedly_options['key']);
    }

    function get_class_api_key_input_container() {
        $class = 'class="embedly-api-key-input-container';
         if ($this->valid_key()) {
             $class .= ' locked_key';
         }
         $class .= '"';
         echo $class;
    }

    function get_value_embedly_max_width() {
        if(isset($this->embedly_options['card_width'])) {
            echo 'value="' . $this->embedly_options['card_width'] . '"';
        }
    }

    function get_current_align() {
        $current_align = 'center'; // default if not set
        if(isset($this->embedly_options['card_align'])) {
          $current_align = $this->embedly_options['card_align'];
        }
        return $current_align;
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
            <div class="embedly-wrap">
                <div class="embedly-ui">
        <?php
            // Decide which modal to display.
            if( $this->valid_key() ) { ?>
                    <!-- DELETE FOR PRODUCTION -->
                    <?php
                    global $EMBEDLY_DEBUG;
                    if( isset($EMBEDLY_DEBUG) && ($EMBEDLY_DEBUG) ) { ?>

                      <!-- Testing settings preview card -->
                      <div class="embedly-settings-test-container">
                      <!-- insert a blockquote here, just update it's values with the settings
                      selections. does platform refresh automatically? -->
                      <a class="embedly-card" href="https://www.youtube.com/watch?v=hY3tzNUjoHc"></a>
                      <!--<a class="embedly-card"
                        data-card-key=<?php echo '"' . $this->get_value_embedly_key_test() . '"'?>
                        data-card-chrome=<?php echo '"' . $this->embedly_options['card_chrome'] . '"' ?>
                        data-card-controls=<? echo '"' . $this->embedly_options['card_controls'] . '"' ?>
                        href="https://www.youtube.com/watch?v=hY3tzNUjoHc">
                            Justin Bieber and Questlove Drum-Off
                      </a> -->
                        <script async src="//cdn.embedly.com/widgets/platform.js" charset="UTF-8"></script>
                      </div>

                      <!-- Testing key input states -->
                      <hr>
                        <div class="embedly-input-wrapper">

                        <h4>
                        DEBUGGING:
                        <?php
                            echo "<p>CURRENT URI: " . $this->build_uri_with_options() . "</p>";
                            if( isset($_POST)) {
                                $output = '';
                                foreach ($_POST as $key => $value) {
                                    if ( is_array($value) ) {
                                        $output .= "POST[" . $key . "]: ";
                                        foreach($value as $element) {
                                            // throw new Exception($element);
                                            $output .= '[' . $element . ']';
                                        }
                                    }else {
                                        $output .= "POST[" . $key . "]: " . $value . ", ";
                                    }

                                }
                                echo $output;
                            }
                        ?>
                        </h4>
            <?php } ?>
                <!-- END DELETE FOR PRODUCTION -->
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

                  <!-- Notifications -->
                  <?php
                    if (isset($errorMessage)) { ?>
                  <div class="embedly-error embedly-message" id="embedly-error">
                    <p><strong><?php echo $errorMessage;?></strong></p>
                  </div>

                  <?php } elseif (isset($successMessage) && !isset($errorMessage)) { ?>
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
                  <!-- END Notifications -->

                    <div class="embedly-ui-key-wrap">
                      <div class="embedly_key_form embedly-ui-key-form">
                        <div class="embedly-analytics">
                          <ul>
                            <li class="active-viewers">
                              <h1 class="active-count">-</h1>
                              People are <strong>actively viewing</strong> your embeds!
                              <br/> <!-- is this acceptable? need to format my h tags for this page.-->

                              <input class="embedly-button" type="button" onclick="window.open('http://app.embed.ly');"
                                value="<?php _e('Realtime Analytics', 'embedly')?>"/>
                            </li>
                            <li class="historical-viewers">
                              <h1 class="weekly-count">Computing...</h1>
                              People have <strong>viewed</strong> an embed in the <strong>last week</strong>.
                              <br/> <!-- is this acceptable? need to format my h tags for this page.-->
<!--                               <input class="embedly-button" type="button" onclick="window.open('http://app.embed.ly');"
                               value="<?php _e('Historical Analytics', 'embedly')?>"/> -->
                            </li>
                          </ul>
                          <!-- LIST OF PROVIDERS LINK -->
                          Check out our <strong><a href='http://embed.ly/providers' target="_blank">list of providers</a></strong>.
                        </div>

                        <!-- BEGIN Embedly API Key input Field -->
                        <hr>
                        <div class="embedly-key-body">
                          <h3>YOUR EMBEDLY API KEY</h3>
                          <div class="embedly-api-key-input-wrapper">
                            <h1 class="valid-outer-text">Lookin' Good</h1>
                            <h1 class="invalid-outer-text">Invalid API key. Try again!</h1>
                            <div
                              <?php $this->get_class_embedly_api_key_input_container(); ?>>
                              <input id="embedly_key_test" type="text" class="embedly_key_input_test"
                                placeholder="<?php _e('Enter your API Key', 'embedly'); ?>"
                                <?php $this->get_value_embedly_key_test(); ?>/>
                              <span <?php $this->get_class_key_icon_span(); ?>> </span>
                            </div>
                            <h1 class="invalid-outer-text">*Required Field</h1>
                          </div>
                        </div>
                        <!-- END Embedly API Key input Field -->


                        <!-- Begin 'Advanced Options' Section -->
                        <hr>

                        <div class="advanced-wrapper">
                        <div class="advanced-header">
                          <a href="#"><h3>ADVANCED EMBED SETTINGS
                          <span class="dashicons dashicons-arrow-right-alt2 embedly-dropdown"></span></h3></a>
                        </div>
                        <div class="advanced-body">
                          Changing these settings will change how your future embeds appear.
                          <div class="embedly-default-card-settings">
                            <!-- Boolean Attributes (ie. Chromeless, Card Theme, etc) -->
                            <ul>
                              <li>
                                <h3>DESIGN</h3>
                                <input class='traditional-card-checkbox' type='checkbox' value='checked' name='minimal' <?php
                                  checked( $this->embedly_options['card_chrome'], 1);
                                  ?> /> TRADITIONAL CARD
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
                                  <input class='embedly-max-width' type="text" name="card_width" placeholder="example: 400px"
                                    <?php $this->get_value_embedly_max_width(); ?>/>
                                  <p>(responsive if left blank)</p>
                                </div>
                              </li>
                              <li>
                                <!-- Card Alignment Options -->
                                <h3>ALIGNMENT</h3>
                                <div class="embedly-align-select-container embedly-di">
                                  <ul class="align-select">
                                    <?php
                                      $current_align = $this->get_current_align();
                                      $sel = ' selected-align-select "';
                                    ?>
                                    <li><span class=
                                      <?php echo '"dashicons di-none align-icon' . ($current_align == 'left' ? $sel : '"'); ?>
                                      title="Left" align-value="left">
                                      <input type='hidden' value='unchecked' name='card_align_left'>
                                      </span>
                                    </li>
                                    <li><span class=
                                      <?php echo '"dashicons di-center align-icon' . ($current_align == 'center' ? $sel : '"'); ?>
                                      title="Center" align-value="center">
                                      <input type='hidden' value='checked' name='card_align_center'>
                                      </span>
                                    </li>
                                    <li><span class=
                                      <?php echo '"dashicons di-none di-reverse align-icon' . ($current_align == 'right' ? $sel : '"'); ?>
                                      title="Right" align-value="right">
                                      <input type='hidden' value='unchecked' name='card_align_right'>
                                      </span>
                                    </li>
                                  </ul>
                                </div>
                              </li>
                            </ul>


                          </div>
                        </div>
                        <!-- END Expandable Options Section -->
                        </div> <!-- END 'Options' Section -->

                        <!-- Saving Settings Button (No longer required.) -->
                        <div class="embedly-save-settings-input" hidden>
                          <input class="embedly-button" name="submit" type="submit" value="<?php
                            _e('Save', 'embedly');
                            ?>"/>
                        </div>
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
                      <!-- Notifications -->
                      <?php
                        // get_notification();
                        if (isset($errorMessage)) { ?>
                      <div class="embedly-error embedly-message" id="embedly-error">
                        <p><strong><?php echo $errorMessage;?></strong></p>
                      </div>

                      <?php } elseif (isset($successMessage) && !isset($errorMessage)) { ?>
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
                      <!-- END Notifications -->


                      <!-- HERO TEXT -->
                      <h1><strong>Embed content from any site!</strong></h1>


                      <!-- Blurb -->
                      <h3>
                        <span class="dashicons dashicons-twitter"></span>
                        We now support <strong>Twitter</strong>! Check out our
                        <a href="http://embed.ly/providers" target="_blank"><strong>long list of providers</strong>.</a>
                      </h3>
                        <h3>Getting started? <strong>Learn more above</strong> about embedly cards for Wordpress.</h3>

                      <!-- Create an embed.ly account button -->
                      <div class="embedly-create-account-btn-wrap">
                        <input class="embedly-button" type="button" onclick=
                          <?php echo '"' . "window.open('" . SIGNUP_URL . "');" . '"' ?>
                          value="<?php _e('GET API KEY', 'embedly')?>"/>
                      </div>


                      &nbsp;

                        <!-- BEGIN Embedly API Key input Field -->
                        <div class="embedly-key-body">
                          <h3><?php _e('GOT YOUR API KEY? PASTE IT HERE', 'embedly'); ?></h3>
                          <div class="embedly-api-key-input-wrapper">
                            <h1 class="valid-outer-text">Lookin' Good</h1>
                            <h1 class="invalid-outer-text">Invalid API key. Try again!</h1>
                            <div
                              <?php $this->get_class_embedly_api_key_input_container(); ?>>
                              <input id="embedly_key_test" type="text" class="embedly_key_input_test"
                                placeholder="<?php _e('Enter your API Key', 'embedly'); ?>"
                                <?php $this->get_value_embedly_key_test(); ?>/>
                              <span <?php $this->get_class_key_icon_span(); ?>> </span>
                            </div>
                            <h1 class="invalid-outer-text">*Required Field</h1>
                          </div>
                        </div>

                      <form id="embedly_key_form" method="POST" action="">
                        <input class="embedly-button" name="Submit" type="submit" value="<?php
                          _e('ACTIVATE PLUGIN', 'embedly');?>"/>
                      </form>

                    </div>
                  </div>
                </div>
              <?php } // END if/else for new/existing account
              ?>
                <footer class="embedly-footer">
                  &copy; <?php _e(date('Y') . ' All Rights Reserved ', 'embedly'); ?>
                  <span class="dashicons dashicons-heart"></span>
                  Built in Boston
                </footer> <?php
    } // END settings page function
} // END WP_Embedly class


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
