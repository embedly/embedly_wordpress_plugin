<?php
/**
 * Embedly TinyMCE Dialog
 *
 * Loads form for entering URL to embed
 * via Embedly TinyMCE button.
 */

define('IFRAME_REQUEST' , true);

/**
 *Load WordPress Admin and Core
 *
 */
require_once('../../../wp-load.php');
require_once( '../../../wp-admin/admin.php');


// edit posts != editing 'pages'
if ( !current_user_can('edit_posts') &amp;&amp; !current_user_can('edit_pages') ) {
  wp_die( __("Access Denied. You must have Author or above permissions.")
}

@header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));

//JS and CSS Assetts for Embedly TinyMCE Dialog
$protocol = is_ssl() ? 'https' : 'http';
// wp_enqueue_script('tiny_mce_popup.js', includes_url( 'js/tinymce/tiny_mce_popup.js', __FILE__ ) );
// wp_enqueue_script('mustache.js', plugins_url( 'tinymce/js/mustache.js', __FILE__ ) );
// wp_enqueue_script('embedly.js', plugins_url( 'tinymce/js/embedly.js', __FILE__ ), array( 'jquery' ) );
wp_enqueue_script('embedly-platform', $protocol.'://cdn.embedly.com/widgets/platform.js');
// wp_enqueue_style('embedly-dialog-css', plugins_url( 'tinymce/css/embedly.css', __FILE__));

//Use Global Embedly to load dialog
global $WP_Embedly;
if ( !$WP_Embedly )
	$WP_Embedly = WP_Embedly::$instance;

//Callback setup to load Dialog
$callback = 'insert_embedly_dialog';
wp_iframe( array( &$WP_Embedly, $callback ) );
