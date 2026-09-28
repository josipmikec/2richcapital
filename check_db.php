<?php
define('WP_USE_THEMES', false);
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'app.2rich.capital';
    $_SERVER['SERVER_NAME'] = 'app.2rich.capital';
    $_SERVER['REQUEST_URI'] = '/';
}
require_once('wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'rich_news_feed';
$results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 5", ARRAY_A);
print_r($results);
