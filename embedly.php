<?php
/*
Plugin Name: Embedly
Plugin URI: http://embed.ly
Description: The Embedly Plugin extends Wordpress's Oembed feature, allowing bloggers to Embed from 230+ services and counting.
Author: Embed.ly Inc
Version: 3.2.2
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
        add_filter("mce_external_plugins", "embedly_add_tinymce_plugin");
        add_filter('mce_buttons', 'embedly_register_embed_button');
    }

}

// @ cstiteler -> Some thoughts about WP plugins. Most that I've skimmed throug
// remain functional, having a class based plugin seems to complicate the php.
// So far I've stripped out all the php relating to selecting a provider

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

function embedly_footer_widgets()
{
    echo "<script>
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

function embedly_tinymce_settings($init)
{
    global $allowedposttags;
    $allowedposttags['script'] = array(
        'type' => array(),
        'src' => array()
    );

    $init['allow_html_in_named_anchor'] = 'true';
    // in order to allow insertion of anchor tags into editor, this secure?
    $init['valid_elements']             = '*[*]';
    // $init['extended_valid_elements'] = 'script[charset|defer|language|src|type]';
    $init['extended_valid_elements']    = '*[*]';

    $initArray['setup'] = <<<JS
[function(ed){
  ed.onInit.add(function(ed, evt) {
    // my attempt at getting the card to load in the rich editor, moving on for now
    tinymce.dom.ScriptLoader.load('/wordpress-dev/wp-content/plugins/embedly_wordpress_plugin/js/embedly-platform.js').isDone();
  });

  // Here we can load any of the setup functions in javascript for tinymce
  // with the tiny_mce_before_init action.
  // trying to get the a tag from the editor to show up in the visual wysiwyg box
}][0]
JS;

    return $init;
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
            'key' => ''
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
        register_activation_hook(__FILE__, array(
            $this,
            'embedly_activate'
        ));
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
        add_action('wp_head', array(
            $this,
            'embedly_platform_javascript'
        ), 0);
        // add_action('init', array($this, 'embedly_addbuttons'));

        //Post page actions
        // NEW:

        // this may need to be wp_head based on what art mentioned.
        add_action('admin_head', 'embedly_add_embed_button');
        add_action('admin_head', 'embedly_footer_widgets');
        add_filter('tiny_mce_before_init', 'embedly_tinymce_settings');
        add_action('plugins_loaded', array(
            $this,
            'add_embedly_providers'
        ));

        // this can add the icon via css if nec.
        add_action('admin_enqueue_scripts', 'embedly_button_css');

        // sends the api_key to the tinyMCE jquery ajax call
        add_action('wp_ajax_embedly_get_api_key', array(
            $this,
            'embedly_get_api_key'
        ));

        // END NEW
    }

    function embedly_get_api_key()
    {
        echo $this->embedly_options['key'];
        wp_die(); // required to return response
    }

    /**
     * Load plugin translation
     */
    function i18n()
    {
        load_plugin_textdomain('embedly', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /**
     * Activation Hook
     **/
    function embedly_activate()
    {
        global $wpdb;

        # can probably strip most of this out on activate without providers.
        # Table doesn't exist, let's create it
        if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->embedly_options['table'] . "'") != $this->embedly_options['table']) {
            $sql = "CREATE TABLE " . $this->embedly_options['table'] . " (
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

        # Table already exists, wipe it clean and start over
        else {
            $sql     = "TRUNCATE TABLE " . $this->embedly_options['table'] . ";";
            $results = $wpdb->query($sql);
        }
    }

    /**
     * Deactivation Hook
     **/
    function embedly_deactivate()
    {
        global $wpdb;
        $sql     = $wpdb->prepare("TRUNCATE TABLE %s;", $this->embedly_options['table']);
        $results = $wpdb->query($sql);
        delete_option('embedly_settings');
    }


    /**
     * Adds toplevel Embedly settings page
     **/
    function embedly_add_settings_page()
    {
        $this->embedly_settings_page = add_menu_page('Embedly', 'Embedly', 'activate_plugins', 'embedly', array(
            $this,
            'embedly_settings_page'
        ));
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
    function embedly_platform_javascript()
    {
        $protocol = is_ssl() ? 'https' : 'http';
        wp_enqueue_script('embedly-platform', $protocol . '://cdn.embedly.com/widgets/platform.js');
    }


    /**
     * Used for data validation upon form submission
     **/
    function embedly_update_selected_services($services)
    {
        $result = $this->update_embedly_service($services);
        if ($result == null || !$result) {
            return false;
        }
        return true;
    }


    /**
     * Function to check if account has specific features enabled
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
     * Does the work of adding the Embedly providers to wp_oembed
     **/
    function add_embedly_providers()
    {
        // this doens't work, but something like this is needed?
        $sre = '#(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)#i';

        // remove default WP oembed providers
        // add_filter('oembed_providers', create_function('', 'return array();'));
        wp_oembed_remove_provider('#*#i');

        // can match providers with regex: (or below, with a pattern)
        // if (!empty($this->embedly_options['key'])) {
        //     wp_oembed_add_provider("#*#i", 'http://api.embed.ly/1/oembed?key=' . $this->embedly_options['key'], true);
        // } else {
        //     wp_oembed_add_provider("#*#i", 'http://api.embed.ly/1/oembed', true);
        // }

        // testing overriding ALL providers, this works, just need to narrow it down too try all providers..
        // some regex or pattern
        if (!empty($this->embedly_options['key'])) {
            wp_oembed_add_provider("*", 'http://api.embed.ly/1/oembed?key=' . $this->embedly_options['key'], false);
        } else {
            wp_oembed_add_provider("*", 'http://api.embed.ly/1/oembed', false);
        }

        // Since Embedly does not support Twitter, we have to add it back into the mix.
        wp_oembed_add_provider('#https?://(www\.)?twitter\.com/.+?/status(es)?/.*#i', 'https://api.twitter.com/1/statuses/oembed.{format}', true);

    }

    # still can use some of this for later.
    function insert_embedly_dialog()
    {
?>

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
    function embedly_settings_page()
    {
        global $wpdb;
        // $services = $this->embedly_provider_queries(null, 'get', null, false, null, true);
        $selServs = array();
        $cnt      = 0;

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
            if ($this->embedly_acct_has_feature('oembed', $key)) {
                $this->embedly_options['key'] = $key;
                update_option('embedly_settings', $this->embedly_options);
                $this->embedly_options = get_option('embedly_settings');
                $successMessage        = __('Your API key is now tucked away for safe keeping.', 'embedly');
                $keyValid              = true;
            }
            #user key is invalid
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
    </div>
    <?php
    }
}


//Instantiate a Global Embedly
$WP_Embedly = new WP_Embedly();
