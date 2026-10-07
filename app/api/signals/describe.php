<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
global $wpdb;
$cols = $wpdb->get_results("SHOW COLUMNS FROM " . $wpdb->prefix . "rich_signal_memberships");
print_r($cols);
