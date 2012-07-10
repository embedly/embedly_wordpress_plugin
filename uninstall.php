<?php

function embedly_uninstall() {
  global $wpdb;
	$sql     = $wpdb->prepare("DROP TABLE ".$wpdb->prefix."embedly_providers;");
  $results = $wpdb->query($sql);
}
if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
  exit();
}
else {
  delete_option('embedly_settings');
  embedly_uninstall();
}

?>