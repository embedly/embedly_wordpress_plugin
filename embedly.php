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

// DEBUGGING
$EMBEDLY_DEBUG = true;

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

        $this->embedly_options = array(
            'active' => true,
            'key' => '',
            'card_chrome' => true,
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
        add_action('wp_ajax_embedly_analytics_active_viewers', array(
            $this,
            'embedly_ajax_get_active_viewers'
        ));

        // action establishes embed.ly the sole provider of embeds
        // (except those unsupported)
        add_action('plugins_loaded', array(
            $this,
            'add_embedly_providers'
        ));
    }


    /**
    * Makes a call for realtime analytics, and returns data to front end
    **/
    function embedly_ajax_get_active_viewers()
    {
        if (isset($this->embedly_options['key']) && !empty($this->embedly_options['key'])) {
            // create curl resource
            $ch = curl_init();
            // set url
            curl_setopt($ch,
                CURLOPT_URL,
                "https://narrate.embed.ly/1/series?key=" . $this->embedly_options['key']);
                // $this->embedly_options['key']);
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
            echo "{active: 'N/A'}";
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
            wp_oembed_add_provider('#https?://(www\.)?twitter\.com/.+?/status(es)?/.*#i', 'https://api.twitter.com/1/statuses/oembed.{format}', true);
            $provider_uri = $this->build_uri_with_options();
            wp_oembed_add_provider('#.*#i', $provider_uri, true);
        }
    }

    function build_uri_with_options()
    {
        $valid_settings = array(
            'card_controls', # boolean
            'card_chrome', # boolean
            'card_theme', # valid: 'dark' or 'light'
            'card_width', # valid: int
            'card_align', # valid: 'left', 'right', 'center'
            );

        // gets the subset of valid_settings that are actually set in plugin
        $set_options = array();
        foreach ($valid_settings as $setting) {
            // $set_options[] = $setting;
            if(isset($this->embedly_options[$setting])) {
                $set_options[] = $setting;
            }
        }

        // option params is a list of url_param => value
        // create a valid $key => $value pair for the url string
        $option_params = array(); # example: '&card_theme' => 'dark'
        foreach ($set_options as $option) {
            $value = $this->embedly_options[$option];
            if ( is_bool($value) ) {
                $param = '&' . $option . '=' . ($value ? '1' : '0');
                $option_params[$option] = $param;
            }
            else {
                $param = '&' . $option . '=' . $value;
                $option_params[$option] = $param;
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
       $feature_status = json_decode($result);
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


    /**
     * The Admin Page.
     **/
    function embedly_settings_page()
    {
        global $wpdb;

        # Begin processing form data

        # Card Defaults
        if(isset($_POST['embedly_align']) && !empty($_POST['embedly_align'])) {
            $this->embedly_save_option('card_align', $_POST['embedly_align']);
        }
        if (isset($_POST['minimal'])) {
            $this->embedly_save_option('card_chrome', $_POST['minimal'] == 'checked' ? false : true );
        }
        if (isset($_POST['card_controls'])) {
            $this->embedly_save_option('card_controls', $_POST['card_controls'] == 'checked' ? true : false );
        }
        if (isset($_POST['card_dark'])) {
            $this->embedly_save_option('card_theme', $_POST['card_dark'] == 'checked' ? 'dark' : 'light' );
        }

        #empty key set when saving
        if (isset($_POST['embedly_key']) && (empty($_POST['embedly_key']) || $_POST['embedly_key'] == __('Please enter your key...', 'embedly'))) {
            $this->embedly_options['key'] = '';
            update_option('embedly_settings', $this->embedly_options);
            $successMessage = __("You didn't enter a key to validate, so for now you only have basic capabilities.", 'embedly');
        }
        #user inputted key when saving
        elseif (isset($_POST['embedly_key']) && !empty($_POST['embedly_key'])) {
            #user key is valid
            $key = trim($_POST['embedly_key']);

            // check if key is valid with embedly_acct_has_feature
            if ($this->embedly_acct_has_feature('oembed', $key)) {
                $this->embedly_options['key'] = $key;
                update_option('embedly_settings', $this->embedly_options);
                $this->embedly_options = get_option('embedly_settings');
                $successMessage        = __('Your API key is now tucked away for safe keeping.', 'embedly');
                $keyValid              = true;
            }
            else {
                $keyValid     = false;
                $errorMessage = __('You have entered an invalid API key. Please try again.', 'embedly');
            }
        }

        #key is already saved
        elseif (!isset($_POST['embedly_key']) && isset($this->embedly_options['key']) && !empty($this->embedly_options['key'])) {
            $keyValid = true;
        }

        #key was set in older version, needs to be resaved.
        elseif (get_option('embedly_key') && (!isset($this->embedly_options['key']) || empty($this->embedly_options['key']))) {
            #Backwards compatible
            $this->embedly_options['key'] = get_option('embedly_key');
            update_option('embedly_settings', $this->embedly_options);
            $this->embedly_options = get_option('embedly_settings');
            delete_option('embedly_key');
            $keyValid = true;
        }

        // some scaffolding work for the new design.
        ?>
            <div class="embedly-wrap">
                <div class="embedly-ui">
        <?php

            if( isset($this->embedly_options['key']) && !empty($this->embedly_options['key']) ) {  ?>

                    <!-- DELETE FOR PRODUCTION -->
                    <?php
                    global $EMBEDLY_DEBUG;
                    if( isset($EMBEDLY_DEBUG) && ($EMBEDLY_DEBUG) ) { ?>

                    <div class="embedly-align-select-container embedly-di">
                        <ul class="align-select">
                            <li><a class="dashicons di-none" href="#" title="Left"></a></li>
                            <li><a class="dashicons di-center" href="#" title="Center"></a></li>
                            <li class="selected"><a class="dashicons di-none di-reverse" href="#" title="Right"></a></li>
                        </ul>
                    </div>

                    <div class="embedly_align_button_wrap">
                      <button type="button-secondary">
                        <span class="dashicons dashicons-align-none"></span>
                      </button>
                      <button type="button-secondary">
                        <span class="dashicons dashicons-align-center "></span>
                      </button>
                      <button type="button-secondary">
                        <div class="dashicon-reverse">
                          <span class="dashicons dashicons-align-none"></span>
                        </div>
                      </button>
                    </div>

                        <h3>
                        MODAL FOR EXISTING USERS
                        <p>
                            DEBUGGING:
                        </p>


                    <form id="embedly_key_form" method="POST" action="">

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
                        </h3>
                    <?php } ?>
                        <!-- END DELETE FOR PRODUCTION -->

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
                        <div class="embedly-analytics">
                            <ul>
                                <li class="active-viewers">
                                    <h1 class="active-count">10</h1>People are actively viewing your embeds.
                                    <a href="" target="_blank" class="button-primary view-realtime-button">
                                        <?php _e('View Realtime', 'embedly')?>
                                    </a>
                                </li>

                                <li><h1>120</h1>People have viewed an embed in the last week.
                                    <a href="" target="_blank" class="button-primary view-historical-button">
                                        <?php _e('View Historical', 'embedly')?>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="embedly-default-card-settings">
                            <ul>
                            <h3>Advanced Options</h3>

                              <li>
                                <input type='hidden' value='unchecked' name='minimal'>
                                <input type='checkbox' value='checked' name='minimal' <?php
                                // returns 'checked' html attr if option 'card_chrome' is set to false
                                checked( $this->embedly_options['card_chrome'], false);
                                ?> /> Minimal Design </li>
                              </li>
                              <li>
                                <input type='hidden' value='unchecked' name='card_controls'>
                                <input type='checkbox' value='checked' name='card_controls' <?php
                                checked( $this->embedly_options['card_controls'], 1);
                                ?> /> Social Buttons </li>
                              </li>
                              <li>
                                <input type='hidden' value='unchecked' name='card_dark'>
                                <input type='checkbox' value='checked' name='card_dark' <?php
                                checked( $this->embedly_options['card_theme'], 'dark');
                                ?> />Cards for Dark Pages</li>
                              </li>

                            <li>Width <input type="text" placeholder="Responsive"/>
                            (responsive if left blank)
                            <!-- TODO: @cstiteler: implement width attr with cleaning -->
                            </li>

                            <li>
                            <select name="embedly_align">
                                <?php
                                    $align_set = isset($this->embedly_options['card_align']);
                                ?>
                                <option value="left" <?php
                                    if( $align_set ) {
                                        selected($this->embedly_options['card_align'], 'left' );
                                    }?>>
                                    Left
                                </option>
                                <option value="center" <?php
                                    if( $align_set ) {
                                        selected($this->embedly_options['card_align'], 'center' );
                                    }?>>
                                    Center
                                </option>
                                <option value="right" <?php
                                    if( $align_set ) {
                                        selected($this->embedly_options['card_align'], 'right' );
                                    }?>>
                                    Right
                                </option>
                            </select></li>

                            </ul>
                        </div>
                    <div class="embedly-input-wrapper">
                        <div class="embedly-api-key-input">
                            Embedly Key
                            <input <?php
                              //locks key as readonly

                              // if (isset($keyValid) && $keyValid) {
                              //     echo 'readonly="readonly" ';
                              // }

                              ?>id="embedly_key" placeholder="<?php
                              _e('Enter your API Key', 'embedly');
                              ?>" name="embedly_key" type="text" class="<?php

                              ?>embedly_key_input" <?php
                              if (!empty($this->embedly_options['key'])) {
                                  echo 'value="' . $this->embedly_options['key'] . '"';
                              }
                              ?> />
                        </div>
                        </div>

                        <div class="embedly-save-settings-input">
                            <input class="button-primary embedly_submit embedly_top_submit" name="submit" type="submit" value="<?php
                              _e('Save', 'embedly');
                              ?>"/>
                        </div>
                        </div>
                        </div>
                    </form>
                </div>
                <?php
            } else {
            ?>


            <!-- MODAL FOR NEW ACCOUNTS -->
          <div class="embedly-ui">
            <div class="embedly-ui-header-outer-wrapper">
              <div class="embedly-ui-header-wrapper">
                <div class="embedly-ui-header">
                </div>
            </div>
        </div>
            <div class="embedly-ui-key-wrap">
            <div class="embedly_key_form embedly-ui-key-form">

            <div class="embedly-sign-up-hero-text">
                <h2 class="section-label"><?php
                  _e("In order to use the Embedly Wordpress Plugin you need to sign up for an API Key." .
                    "Don't worry, it takes less than 2 minutes.", 'embedly');
                  ?></h2>

            </div>

            <form id="embedly_key_form" method="POST" action="">
                <div class="embedly-input-wrapper">
                    <div class="embedly-api-key-input">
                        <input <?php
                          ?>id="embedly_key" placeholder="<?php
                          _e('Enter your API Key', 'embedly');
                          ?>" name="embedly_key" type="text" class="<?php
                          ?>embedly_key_input" <?php
                          if (!empty($this->embedly_options['key'])) {
                              echo 'value="' . $this->embedly_options['key'] . '"';
                          }
                          ?> />
                          <input class="button-primary" name="Submit" type="submit" value="<?php
                            _e('Submit', 'embedly');
                          ?>"/>
                    </div>
                </div>


                <div class="embedly-create-account-btn-wrap">
                    <a href="https://app.embed.ly/login" target="_blank" class="button-primary create_account_button">
                        <?php _e('Create Account', 'embedly')?>
                    </a>
                </div>
            </form>
            </div>
            </div>
        <?php
            } // end else
        ?>

<?php
    global $EMBEDLY_DEBUG;
    if( isset($EMBEDLY_DEBUG) && ($EMBEDLY_DEBUG) ) { ?>
<!-- OLD EMBEDLY KEY ENTRY BOX @cstiteler: DELETE FOR PRODUCTION -->
          <div class="embedly-ui">
            <div class="embedly-ui-header-outer-wrapper">
              <div class="embedly-ui-header-wrapper">
                <div class="embedly-ui-header">
                </div>
              </div>
            </div>
            <?php
              if (isset($errorMessage)) {
              ?>
            <div class="embedly-error embedly-message" id="embedly-error">
              <p><strong><?php
                echo $errorMessage;
                ?></strong></p>
            </div>
            <?php
              } elseif (isset($successMessage) && !isset($errorMessage)) {
              ?>
            <div class="embedly-updated embedly-message" id="embedly-success">
              <p><strong><?php
                echo $successMessage;
                ?></strong></p>
            </div>
            <?php
              }
              ?>
            <div class="embedly-error embedly-ajax-message embedly-message" id="embedly-ajax-error">
              <p><strong><?php
                _e('Something went wrong. Please try again later.', 'embedly');
                ?></strong></p>
            </div>
            <div class="embedly-updated embedly-ajax-message embedly-message" id="embedly-ajax-success">
              <p><strong><?php
                _e("We have sync'd your providers list with our API. Enjoy!", 'embedly');
                ?></strong></p>
            </div>
            <?php {
              ?>
            <form id="embedly_key_form" method="POST" action="">
              <div class="embedly-ui-key-wrap">
                <div class="embedly_key_form embedly-ui-key-form">
                  <fieldset>
                    <h2 class="section-label"><?php
                      _e('Old Embedly Key Form', 'embedly');
                      ?></h2>
                    <span><a href="http://app.embed.ly" target="_new"><?php
                      _e("Lost your key?", 'embedly');
                      ?></a></span>
                    <div class="embedly-input-wrapper">

                    <a href="#" class="embedly-lock-control embedly-unlocked" data-unlocked="<?php
                        _e('Lock this field to prevent editing.', 'embedly');
                        ?>" data-locked="<?php
                        _e('Unlock to edit this field.', 'embedly');
                        ?>" title=""><?php
                        if(isset($keyValid) && $keyValid){
                            _e('Unlock to edit this field.', 'embedly');
                        }else{
                            _e('Lock this field to prevent editing.', 'embedly');} ?></a>


                      <a href="#" class="embedly-lock-control embedly-unlocked" data-unlocked="<?php
                        _e('Lock this field to prevent editing.', 'embedly');
                        ?>" data-locked="<?php
                        _e('Unlock to edit this field.', 'embedly');
                        ?>" title=""><?php
                        if (isset($keyValid) && $keyValid) {
                            _e('Unlock to edit this field.', 'embedly');
                        } else {
                            _e('Lock this field to prevent editing.', 'embedly');
                        }
                        ?></a>


                      <input <?php
                        if (isset($keyValid) && $keyValid) {
                            echo 'readonly="readonly" ';
                        }
                        ?>id="embedly_key" placeholder="<?php
                        _e('Please enter your key...', 'embedly');
                        ?>" name="embedly_key" type="text" class="<?php
                        if (isset($keyValid) && !$keyValid) {
                            echo 'invalid embedly-unlocked-input ';
                        } elseif (!isset($keyValid)) {
                            echo 'embedly-unlocked-input ';
                        } else {
                            echo 'embedly-locked-input ';
                        }
                        ?>embedly_key_input" <?php
                        if (!empty($this->embedly_options['key'])) {
                            echo 'value="' . $this->embedly_options['key'] . '"';
                        }
                        ?> />


                      <input class="button-primary embedly_submit embedly_top_submit" name="submit" type="submit" value="<?php
                        _e('Save Key', 'embedly');
                        ?>"/>
                    </div>

                    <p><?php
                      _e('Add your Embedly Key to embed any URL', 'embedly');
                      ?></p>

                  </fieldset>
                </div>
              </div>
              <div class="embedly-ui-providers">
                <span><a href="http://embed.ly/providers" target="_new"><?php
                  _e("List of supported providers", 'embedly');
                  ?></a></span>
              </div>
            </form>
            <?php
              }
              ?>
          </div>
        </div>
        <?php
    }
    }
}


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
