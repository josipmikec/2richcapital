<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'rich_news_feed';
$rows = $wpdb->get_results("SELECT id, message, author, created_at FROM {$table} ORDER BY created_at DESC LIMIT 5", ARRAY_A);
echo json_encode($rows, JSON_PRETTY_PRINT);
