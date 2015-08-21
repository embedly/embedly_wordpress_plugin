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
            'table' => $wpdb->prefix . 'embedly_providers',
            'active' => true,
            'key' => '',
            'chrome' => false,
            'controls' => true,
            'dark' => false,
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

        //Activate/De-activate Embedly Hooks
        // register_activation_hook(__FILE__, array(
        //     $this,
        //     'embedly_activate'
        // ));

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


        // Do we need these to get platform.js to load?
        // add_action('wp_head', array(
        //     $this,
        //     'embedly_platform_javascript'
        // ), 0);

        // add_action('wp_enqueue_scripts', array(
        //     $this,
        //     'test_global_injection'
        // ));

        // interesting note here..
        // by injecting platform into the footer, i believe it's overriding
        // the attempt to create a new platform from the card, which allows us
        // to maintain a hook to embedly('default'). if we take out all platform
        // injection on our end, then the following embedly_global_settings injection
        // fails because it doesn't have a local reference to platform.
        add_action('admin_head', array(
            $this,
            'embedly_footer_widgets'
        ));
        add_action('wp_head', array(
            $this,
            'embedly_footer_widgets'
        ));

        // injects global settings script into admin/wp_headers
        add_action('wp_head', array(
            $this,
            'embedly_global_settings'
        ));
        add_action('admin_head', array(
            $this,
            'embedly_global_settings'
        ));

        // action notifies user on admin menu if they don't have a key
        add_action( 'admin_menu', array(
            $this,
            'embedly_notify_user_icon'
        ));

        //////////////////// BEGIN BUTTON STUFF

        // add_action('init', array($this, 'embedly_addbuttons'));

        // Turn the button on
        // this may need to be wp_head based on what art mentioned.
        // add_action('admin_head', array(
        //     $this,
        //     'embedly_add_embed_button'
        // ));

        // add the button's css image
        // add_action('admin_enqueue_scripts', array(
        //     $this,
        //     'embedly_button_css'
        // ));

        // sends the api_key to the tinyMCE jquery ajax call
        // add_action('wp_ajax_embedly_get_api_key', array(
        //     $this,
        //     'embedly_get_api_key'
        // ));

        //////////////////// END BUTTON STUFF

        // action establishes embed.ly the sole provider of embeds
        // (except those unsupported)
        add_action('plugins_loaded', array(
            $this,
            'add_embedly_providers'
        ));


        // jquery ajax handler for global settings on cards
        // add_action( 'wp_ajax_nopriv_embedly_get_global_card_settings', array(
        //     $this,
        //     'embedly_get_global_card_settings'
        // ));
        // add_action('wp_ajax_embedly_get_global_card_settings', array(
        //     $this,
        //     'embedly_get_global_card_settings'
        // ));

        // END NEW
    }

    function embedly_global_settings() {
        // testing for now
        $chrome = 0;
        $controls = 0; #shareable
        $key = $this->embedly_options['key'];

        echo "
        <script>
        // testing out defaults..
        (function(w, d) {
            w.embedly('defaults', {
                cards: {
                    chrome: " . $chrome . ",
                    controls: " . $controls . ",
                    key: '" . $key . "',
                }
            });
        })(window, document);
        </script>
     ";
    }

    // function test_global_injection() {
    //     wp_register_script('card_injection', plugins_url('/js/globals.js', __FILE__),
    //         array('jquery'));
    //     wp_localize_script('card_injection', 'myajax', array('ajaxurl' => admin_url('admin-ajax.php')));
    //     wp_enqueue_script('card_injection');
    // }

    /**
    *   endpoint for ajax from front end for global settings. Call like this:
    *   jQuery.post(ajaxurl,{ 'action': 'embedly_get_global_card_settings',}, function(response) {...});
    **/
    // function embedly_get_global_card_settings()
    // {
    //     echo json_encode(array(
    //         'api_key' => $this->embedly_options['key'],
    //         'chrome' => $this->embedly_options['chrome'],
    //         'social_buttons' => $this->embedly_options['shareable']
    //     ));
    //
    //     wp_die();
    // }

    // ajax test for api key to anywhere with ajaxurl in context
    // function embedly_get_api_key()
    // {
    //     echo $this->embedly_options['key'];
    //     wp_die(); // required to return response
    // }

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
                // $list .= $menu[$key][0] . ' ';
                if ($menu[$key][2] == 'embedly') {
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
        ), 'dashicons-align-center');
    }


    /**
     * Enqueue styles/scripts for embedly page(s) only
     **/
    function embedly_enqueue_admin()
    {
        $screen = get_current_screen();
        if ($screen->id == $this->embedly_settings_page) {
            $protocol = is_ssl() ? 'https' : 'http';
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
     * Enqueue platform.js to post for cards.
     **/
    // function embedly_platform_javascript()
    // {
    //     $protocol = is_ssl() ? 'https' : 'http';
    //     wp_enqueue_script('embedly-platform', $protocol . '://cdn.embedly.com/widgets/platform.js');
    // }


    /**
     * Does the work of adding the Embedly providers to wp_oembed
     **/
    function add_embedly_providers()
    {
        # if user entered key, override providers, else, leave alone, for now.
        if (!empty($this->embedly_options['key'])) {
            // delete all current oembed providers
            add_filter('oembed_providers', create_function('', 'return array();'));

            // we provide for everyone
            wp_oembed_add_provider('#.*#i', 'https://api.embedly.com/1/card?key=' . $this->embedly_options['key'], true);

            // except twitter
            wp_oembed_add_provider('#https?://(www\.)?twitter\.com/.+?/status(es)?/.*#i', 'https://api.twitter.com/1/statuses/oembed.{format}', true);
        }
    }

    function embedly_add_embed_button()
    {
        global $typenow;

        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }

        if (!in_array($typenow, array(
            'post',
            'page'
        )))
            return;

        if (get_user_option('rich_editing') == 'true') {
            add_filter("mce_external_plugins", array(
                $this,
                'embedly_add_tinymce_plugin'
            ));
            add_filter('mce_buttons', array(
                $this,
                'embedly_register_embed_button'
            ));
        }

    }

    function embedly_add_tinymce_plugin($plugin_array)
    {
        $plugin_array['embedly_embed_button'] = plugins_url('js/embedly-button.js', __FILE__);
        return $plugin_array;
    }


    function embedly_register_embed_button($buttons)
    {
        array_push($buttons, "embedly_embed_button");
        return $buttons;
    }


    function embedly_button_css()
    {
        wp_enqueue_style('embedly_button.css', plugins_url('css/embedly_button.css', __FILE__));
    }


    // injects platform into the wordpress page, not needed if we get it othwerise
    function embedly_footer_widgets()
    {
        echo "
        <script>
      (function(w, d){
       var id='embedly-platform', n = 'script';
       if (!d.getElementById(id)){
         w.embedly = w.embedly || function() {(w.embedly.q = w.embedly.q || []).push(arguments);};
         var e = d.createElement(n); e.id = id; e.async=1;
         e.src = ('https:' === document.location.protocol ? 'https' : 'http') + '://cdn.embedly.com/widgets/platform.js';
         var s = d.getElementsByTagName(n)[0];
         s.parentNode.insertBefore(e, s);
       }
     })(window, document);</script>";
    }

    /**
    * Function to check if account has specific features enabled
    * but mainly, we care to check if the key is valid
    **/
   function embedly_acct_has_feature($feature, $key = false)
   {
       if ($key) {
           $result = wp_remote_retrieve_body(wp_remote_get('http://api.embed.ly/1/feature?feature=' . $feature . '&key=' . $key));
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


   // /**
   // * replaced with embedly_save_option
   // * update embedly_options with saved options/key
   // **/
   // function embedly_save_global_settings($key, $chrome, $shareable)
   // {
   //     $this->embedly_options['key'] = $key;
   //     $this->embedly_options['chrome'] = $chrome;
   //     $this->embedly_options['controls'] = $shareable;
   //     update_option('embedly_settings', $this->embedly_options);
   //     $this->embedly_options = get_option('embedly_settings');
   // }

    /**
     * The Admin Page.
     **/
    function embedly_settings_page()
    {
        global $wpdb;

        #Begin processing form data
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
            <div class="embedly-ui-header-outer-wrapper">
              <div class="embedly-ui-header-wrapper">
                <div class="embedly-ui-header">
                  <a class="embedly-ui-logo" href="http://embed.ly" target="_blank"><?php
                    _e('Embedly', 'embedly');
                    ?></a>
                </div>
            </div>
        </div>
            <div class="embedly-analytics">
                <ul>

                    <li><h1>10</h1>People are actively viewing your embeds.
                    <input class="button-primary embedly_view_realtime" name="View Realtime" type="submit" value="<?php
                      _e('View Realtime', 'embedly');
                      ?>"/>
                    </li>

                    <li><h1>120</h1>People have viewed an embed in the last week.
                    <input class="button-primary embedly_view_historical" name="View Historical" type="submit" value="<?php
                      _e('View Historical', 'embedly');
                      ?>"/>
                    </li>
                </ul>
            </div>
            <div class="embedly-default-card-settings">
                <ul>
                <h3>Advanced Options</h3>
                <li><input type="checkbox" /> Minimal Design</li>
                <li><input type="checkbox" /> Social Buttons</li>
            </div>
            <div class="embedly-api-key-input-saved">
                Embedly Key
                <input <?php
                  if (isset($keyValid) && $keyValid) {
                      echo 'readonly="readonly" ';
                  }
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


          <div class="embedly-ui">
            <div class="embedly-ui-header-outer-wrapper">
              <div class="embedly-ui-header-wrapper">
                <div class="embedly-ui-header">
                </div>
            </div>
        </div>
            <div class="embedly-api-key-input-new">
                <input <?php
                  ?>id="embedly_key" placeholder="<?php
                  _e('Enter your API Key', 'embedly');
                  ?>" name="embedly_key" type="text" class="<?php
                  ?>embedly_key_input" <?php
                  if (!empty($this->embedly_options['key'])) {
                      echo 'value="' . $this->embedly_options['key'] . '"';
                  }
                  ?> />
                  <input class="button-primary embedly_view_historical" name="Submit" type="submit" value="<?php
                    _e('Submit', 'embedly');
                  ?>"/>
            </div>

            <div class="embedly-sign-up-hero-text">
                In order to use the Embedly Wordpress Plugin you need to sign up for an API Key.
                Don't worry, it takes less than 2 minutes.
            </div>
            <div class="embedly-create-account-btn-wrap">
                <input class="button-primary embedly_view_historical" name="Create Account" type="submit" value="<?php
                  _e('Create Account', 'embedly');
                ?>"/>
          </div>

<!-- OLD EMBEDLY KEY ENTRY BOX  -->
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
                      _e('Embedly Key', 'embedly');
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


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
